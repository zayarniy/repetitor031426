<?php
require_once 'config.php';
requireAuth();

$currentUser = getCurrentUser($pdo);
$userId = $currentUser['id'];

// Получение фильтров
$selectedCategory = $_GET['category'] ?? '';
$labelFilterMode = $_GET['label_mode'] ?? 'or';
$selectedLabels = isset($_GET['labels']) ? (array)$_GET['labels'] : [];
$selectedClass = $_GET['class'] ?? '';
$statusFilter = $_GET['status'] ?? 'active';
$lessonStatusFilter = $_GET['lesson_status'] ?? 'all';

// Получение всех категорий для фильтра
$stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? OR user_id IS NULL ORDER BY name");
$stmt->execute([$userId]);
$categories = $stmt->fetchAll();

// Получение меток по выбранной категории
if ($selectedCategory) {
    $stmt = $pdo->prepare("
        SELECT l.* FROM labels l 
        WHERE (l.user_id = ? OR l.user_id IS NULL) 
        AND l.category_id = ? 
        ORDER BY l.name
    ");
    $stmt->execute([$userId, $selectedCategory]);
} else {
    $stmt = $pdo->prepare("
        SELECT l.* FROM labels l 
        WHERE l.user_id = ? OR l.user_id IS NULL 
        ORDER BY l.name
    ");
    $stmt->execute([$userId]);
}
$labels = $stmt->fetchAll();

// Получение классов для фильтра
$stmt = $pdo->prepare("SELECT DISTINCT class FROM students WHERE user_id = ? AND class IS NOT NULL AND class != '' ORDER BY class");
$stmt->execute([$userId]);
$classes = $stmt->fetchAll();

// Получение статистики
// Активные ученики
$stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE user_id = ? AND is_active = 1");
$stmt->execute([$userId]);
$activeStudents = $stmt->fetchColumn();

// Занятия на неделю
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM lessons l
    JOIN students s ON l.student_id = s.id
    WHERE s.user_id = ? AND l.lesson_date BETWEEN ? AND ? AND s.is_active = 1
");
$stmt->execute([$userId, $weekStart, $weekEnd]);
$weeklyLessons = $stmt->fetchColumn();

// Часы в неделю
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(duration), 0) / 60 FROM lessons l
    JOIN students s ON l.student_id = s.id
    WHERE s.user_id = ? AND l.lesson_date BETWEEN ? AND ? AND s.is_active = 1
");
$stmt->execute([$userId, $weekStart, $weekEnd]);
$weeklyHours = round($stmt->fetchColumn(), 1);

// Уроки сегодня
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM lessons l
    JOIN students s ON l.student_id = s.id
    WHERE s.user_id = ? AND l.lesson_date = ? AND s.is_active = 1
");
$stmt->execute([$userId, $today]);
$todayLessons = $stmt->fetchColumn();

// Доход за неделю (возможный и полученный)
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(cost), 0) as potential,
        COALESCE(SUM(CASE WHEN is_paid = 1 THEN cost ELSE 0 END), 0) as paid
    FROM lessons l
    JOIN students s ON l.student_id = s.id
    WHERE s.user_id = ? AND l.lesson_date BETWEEN ? AND ? AND s.is_active = 1
");
$stmt->execute([$userId, $weekStart, $weekEnd]);
$income = $stmt->fetch();

// Получение расписания на неделю с фильтрами
$query = "
    SELECT 
        l.*,
        s.first_name as student_first_name,
        s.last_name as student_last_name,
        s.class,
        d.name as diary_name,
        GROUP_CONCAT(DISTINCT t.name) as topics,
        GROUP_CONCAT(DISTINCT CONCAT(t.name, '|', t.id)) as topics_with_ids
    FROM lessons l
    JOIN students s ON l.student_id = s.id
    JOIN diaries d ON l.diary_id = d.id
    LEFT JOIN lesson_topics lt ON l.id = lt.lesson_id
    LEFT JOIN topics t ON lt.topic_id = t.id
    WHERE s.user_id = :user_id 
        AND l.lesson_date BETWEEN :week_start AND :week_end
";

// Применение фильтров
if ($statusFilter === 'active') {
    $query .= " AND s.is_active = 1";
} elseif ($statusFilter === 'inactive') {
    $query .= " AND s.is_active = 0";
}

if ($lessonStatusFilter === 'completed') {
    $query .= " AND l.is_completed = 1";
} elseif ($lessonStatusFilter === 'not_completed') {
    $query .= " AND l.is_completed = 0";
} elseif ($lessonStatusFilter === 'cancelled') {
    $query .= " AND l.is_cancelled = 1";
}

if ($selectedClass) {
    $query .= " AND s.class = :class";
}

// Фильтр по меткам
if (!empty($selectedLabels)) {
    $placeholders = implode(',', array_fill(0, count($selectedLabels), '?'));
    if ($labelFilterMode === 'and') {
        $query .= " AND l.id IN (
            SELECT lesson_id FROM lesson_labels 
            WHERE label_id IN ($placeholders)
            GROUP BY lesson_id 
            HAVING COUNT(DISTINCT label_id) = " . count($selectedLabels) . "
        )";
    } else {
        $query .= " AND l.id IN (
            SELECT DISTINCT lesson_id FROM lesson_labels 
            WHERE label_id IN ($placeholders)
        )";
    }
}

$query .= " GROUP BY l.id ORDER BY l.lesson_date, l.start_time";

$stmt = $pdo->prepare($query);
$params = [
    ':user_id' => $userId,
    ':week_start' => $weekStart,
    ':week_end' => $weekEnd
];

if ($selectedClass) {
    $params[':class'] = $selectedClass;
}

$stmt->execute($params);

// Если есть фильтр по меткам, добавляем их параметры
if (!empty($selectedLabels)) {
    $stmt = $pdo->prepare($query);
    $params = [
        'user_id' => $userId,
        'week_start' => $weekStart,
        'week_end' => $weekEnd
    ];
    if ($selectedClass) {
        $params['class'] = $selectedClass;
    }
    $index = 1;
    foreach ($selectedLabels as $label) {
        $params['label_' . $index] = $label;
        $index++;
    }
    $stmt->execute($params);
}

$schedule = $stmt->fetchAll();

// Группировка по дням недели
$daysOfWeek = [
    'monday' => [],
    'tuesday' => [],
    'wednesday' => [],
    'thursday' => [],
    'friday' => [],
    'saturday' => [],
    'sunday' => []
];

foreach ($schedule as $lesson) {
    $dayOfWeek = strtolower(date('l', strtotime($lesson['lesson_date'])));
    $daysOfWeek[$dayOfWeek][] = $lesson;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Дашборд - Дневник репетитора</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card .stat-value {
            font-size: 2.5em;
            font-weight: bold;
        }
        .stat-card .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        .schedule-day {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .schedule-day h4 {
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .lesson-item {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        .lesson-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        .lesson-item.completed {
            border-left-color: #28a745;
            background: #f0fff4;
        }
        .lesson-item.cancelled {
            border-left-color: #dc3545;
            background: #fff5f5;
            opacity: 0.7;
        }
        .lesson-item .lesson-time {
            font-weight: bold;
            color: #667eea;
        }
        .lesson-item .lesson-student {
            font-weight: 600;
        }
        .lesson-item .lesson-diary {
            font-size: 0.85em;
            color: #666;
        }
        .lesson-tooltip {
            display: none;
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            z-index: 1000;
            max-width: 300px;
        }
        .lesson-item:hover .lesson-tooltip {
            display: block;
            top: 100%;
            left: 0;
            margin-top: 5px;
        }
        .filter-panel {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .btn-filter {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            transition: all 0.3s;
        }
        .btn-filter:hover {
            transform: translateY(-2px);
            color: white;
        }
        .badge-label {
            background: #e9ecef;
            color: #495057;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85em;
            margin-right: 5px;
            cursor: pointer;
        }
        .badge-label.selected {
            background: #667eea;
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">Дашборд</h2>
            </div>
        </div>
        
        <!-- Статистика -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $activeStudents; ?></div>
                    <div class="stat-label">Активных учеников</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $weeklyLessons; ?></div>
                    <div class="stat-label">Занятий на неделю</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $weeklyHours; ?> ч</div>
                    <div class="stat-label">Часов в неделю</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $todayLessons; ?></div>
                    <div class="stat-label">Уроков сегодня</div>
                </div>
            </div>
        </div>
        
        <!-- Доход -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="stat-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                    <div class="stat-value"><?php echo number_format($income['potential'], 0, ',', ' '); ?> ₽</div>
                    <div class="stat-label">Возможный доход за неделю</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stat-card" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
                    <div class="stat-value"><?php echo number_format($income['paid'], 0, ',', ' '); ?> ₽</div>
                    <div class="stat-label">Полученный доход за неделю</div>
                </div>
            </div>
        </div>
        
        <!-- Фильтры -->
        <div class="filter-panel">
            <form method="GET" action="" id="filterForm">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Категория меток</label>
                        <select name="category" class="form-select" onchange="this.form.submit()">
                            <option value="">Все категории</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $selectedCategory == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Класс</label>
                        <select name="class" class="form-select" onchange="this.form.submit()">
                            <option value="">Все классы</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo htmlspecialchars($class['class']); ?>" <?php echo $selectedClass == $class['class'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['class']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Статус ученика</label>
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="active" <?php echo $statusFilter == 'active' ? 'selected' : ''; ?>>Активные</option>
                            <option value="inactive" <?php echo $statusFilter == 'inactive' ? 'selected' : ''; ?>>Неактивные</option>
                            <option value="all" <?php echo $statusFilter == 'all' ? 'selected' : ''; ?>>Все</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Статус урока</label>
                        <select name="lesson_status" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?php echo $lessonStatusFilter == 'all' ? 'selected' : ''; ?>>Все</option>
                            <option value="completed" <?php echo $lessonStatusFilter == 'completed' ? 'selected' : ''; ?>>Проведенные</option>
                            <option value="not_completed" <?php echo $lessonStatusFilter == 'not_completed' ? 'selected' : ''; ?>>Не проведенные</option>
                            <option value="cancelled" <?php echo $lessonStatusFilter == 'cancelled' ? 'selected' : ''; ?>>Отмененные</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Режим фильтра</label>
                        <select name="label_mode" class="form-select" onchange="this.form.submit()">
                            <option value="or" <?php echo $labelFilterMode == 'or' ? 'selected' : ''; ?>>ИЛИ (любая метка)</option>
                            <option value="and" <?php echo $labelFilterMode == 'and' ? 'selected' : ''; ?>>И (все метки)</option>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12">
                        <label class="form-label">Метки</label>
                        <div>
                            <?php foreach ($labels as $label): ?>
                                <span class="badge-label <?php echo in_array($label['id'], $selectedLabels) ? 'selected' : ''; ?>" 
                                      onclick="toggleLabel(<?php echo $label['id']; ?>)">
                                    <?php echo htmlspecialchars($label['name']); ?>
                                </span>
                                <input type="checkbox" name="labels[]" value="<?php echo $label['id']; ?>" 
                                       id="label_<?php echo $label['id']; ?>" 
                                       <?php echo in_array($label['id'], $selectedLabels) ? 'checked' : ''; ?> 
                                       style="display: none;">
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-12">
                        <button type="submit" class="btn btn-filter">Применить фильтры</button>
                        <a href="dashboard.php" class="btn btn-outline-secondary ms-2">Сбросить</a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Расписание на неделю -->
        <div class="row">
            <div class="col-12">
                <h3 class="mb-3">Расписание на неделю (<?php echo date('d.m', strtotime($weekStart)); ?> - <?php echo date('d.m', strtotime($weekEnd)); ?>)</h3>
            </div>
        </div>
        
        <div class="row">
            <?php
            $days = [
                'monday' => 'Понедельник',
                'tuesday' => 'Вторник',
                'wednesday' => 'Среда',
                'thursday' => 'Четверг',
                'friday' => 'Пятница',
                'saturday' => 'Суббота',
                'sunday' => 'Воскресенье'
            ];
            
            foreach ($days as $dayKey => $dayName):
                $date = date('d.m', strtotime("{$dayKey} this week"));
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="schedule-day">
                    <h4><?php echo $dayName; ?> <small class="text-muted"><?php echo $date; ?></small></h4>
                    <?php if (empty($daysOfWeek[$dayKey])): ?>
                        <p class="text-muted text-center py-3">Нет занятий</p>
                    <?php else: ?>
                        <?php foreach ($daysOfWeek[$dayKey] as $lesson): 
                            $lessonClass = '';
                            if ($lesson['is_completed']) $lessonClass = 'completed';
                            elseif ($lesson['is_cancelled']) $lessonClass = 'cancelled';
                        ?>
                            <div class="lesson-item <?php echo $lessonClass; ?>" 
                                onclick="window.location.href='lessons.php?action=edit&id=<?php echo $lesson['id']; ?>&diary_id=<?php echo $lesson['diary_id']; ?>&from=dashboard'">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="lesson-time">
                                            <?php echo date('H:i', strtotime($lesson['start_time'])); ?> 
                                            (<?php echo floor($lesson['duration'] / 60) . 'ч ' . ($lesson['duration'] % 60) . 'м'; ?>)
                                        </div>
                                        <div class="lesson-student">
                                            <?php echo htmlspecialchars($lesson['student_last_name'] . ' ' . $lesson['student_first_name']); ?>
                                        </div>
                                        <div class="lesson-diary">
                                            <?php echo htmlspecialchars($lesson['diary_name']); ?>
                                        </div>
                                    </div>
                                    <?php if ($lesson['is_paid']): ?>
                                        <span class="badge bg-success">Оплачено</span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Подсказка при наведении -->
                                <div class="lesson-tooltip">
                                    <strong>Темы:</strong>
                                    <?php 
                                    $topics = explode(',', $lesson['topics'] ?? '');
                                    if (!empty($topics[0])):
                                        foreach ($topics as $topic):
                                    ?>
                                        <div>• <?php echo htmlspecialchars(trim($topic)); ?></div>
                                    <?php 
                                        endforeach;
                                    else:
                                    ?>
                                        <div class="text-muted">Нет тем</div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($lesson['comment'])): ?>
                                        <hr class="my-1">
                                        <strong>Комментарий:</strong>
                                        <div><?php echo nl2br(htmlspecialchars($lesson['comment'])); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script>
        function toggleLabel(labelId) {
            const checkbox = document.getElementById('label_' + labelId);
            checkbox.checked = !checkbox.checked;
            document.getElementById('filterForm').submit();
        }
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <? include 'footer.php'?>
</body>
</html>