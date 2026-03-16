<?php
require_once 'config.php';

// Получаем токен из URL
$token = isset($_GET['token']) ? $_GET['token'] : '';

if (empty($token)) {
    header('Location: index.php');
    exit();
}

// Получаем информацию о дневнике по публичной ссылке
$stmt = $pdo->prepare("
    SELECT 
        d.*,
        s.first_name as student_first_name,
        s.last_name as student_last_name,
        s.middle_name as student_middle_name,
        s.class as student_class,
        s.is_active as student_is_active,
        c.name as category_name,
        c.color as category_color,
        u.first_name as tutor_first_name,
        u.last_name as tutor_last_name
    FROM diaries d
    JOIN students s ON d.student_id = s.id
    JOIN users u ON d.user_id = u.id
    LEFT JOIN categories c ON d.category_id = c.id
    WHERE d.public_link = ?
");
$stmt->execute([$token]);
$diary = $stmt->fetch();

if (!$diary) {
    // Дневник не найден
    $error = 'Дневник не найден или ссылка недействительна';
}

// Проверяем, активен ли ученик
if ($diary && !$diary['student_is_active']) {
    $error = 'Ученик не активен. Дневник временно недоступен.';
}

// Получаем последние 20 занятий из этого дневника с категориями тем
$lessons = [];
if ($diary && !isset($error)) {
    $stmt = $pdo->prepare("
        SELECT 
            l.*,
            GROUP_CONCAT(DISTINCT CONCAT(t.id, '|', t.name, '|', COALESCE(c.name, 'Без категории'), '|', COALESCE(c.color, '#808080')) SEPARATOR '||') as topics_with_categories
        FROM lessons l
        LEFT JOIN lesson_topics lt ON l.id = lt.lesson_id
        LEFT JOIN topics t ON lt.topic_id = t.id
        LEFT JOIN categories c ON t.category_id = c.id
        WHERE l.diary_id = ?
        GROUP BY l.id
        ORDER BY l.lesson_date DESC, l.start_time DESC
        LIMIT 20
    ");
    $stmt->execute([$diary['id']]);
    $lessons = $stmt->fetchAll();
}

// Функция для парсинга тем с категориями
function parseTopicsWithCategories($topicsString) {
    $result = [];
    if (empty($topicsString)) return $result;
    
    $topics = explode('||', $topicsString);
    foreach ($topics as $topic) {
        if (empty($topic)) continue;
        $parts = explode('|', $topic);
        if (count($parts) >= 4) {
            $result[] = [
                'id' => $parts[0],
                'name' => $parts[1],
                'category' => $parts[2],
                'color' => $parts[3]
            ];
        }
    }
    return $result;
}

// Группировка занятий по месяцам
$groupedLessons = [];
foreach ($lessons as $lesson) {
    $month = date('Y-m', strtotime($lesson['lesson_date']));
    if (!isset($groupedLessons[$month])) {
        $groupedLessons[$month] = [];
    }
    
    // Парсим темы с категориями
    $lesson['topics_parsed'] = parseTopicsWithCategories($lesson['topics_with_categories']);
    
    // Группируем темы по категориям для этого занятия
    $groupedTopics = [];
    foreach ($lesson['topics_parsed'] as $topic) {
        $category = $topic['category'];
        if (!isset($groupedTopics[$category])) {
            $groupedTopics[$category] = [
                'color' => $topic['color'],
                'topics' => []
            ];
        }
        $groupedTopics[$category]['topics'][] = $topic['name'];
    }
    $lesson['topics_by_category'] = $groupedTopics;
    
    $groupedLessons[$month][] = $lesson;
}

// Статистика
$totalLessons = count($lessons);
$completedLessons = 0;
$cancelledLessons = 0;
$avgGrade = 0;
$gradeSum = 0;
$gradeCount = 0;

// Статистика по категориям тем
$categoryStats = [];

foreach ($lessons as $lesson) {
    if ($lesson['is_completed']) {
        $completedLessons++;
    }
    if ($lesson['is_cancelled']) {
        $cancelledLessons++;
    }
    if ($lesson['grade_lesson'] !== null && $lesson['grade_lesson'] !== '') {
        $gradeSum += $lesson['grade_lesson'];
        $gradeCount++;
    }
    
    // Собираем статистику по категориям
    $topics = parseTopicsWithCategories($lesson['topics_with_categories']);
    foreach ($topics as $topic) {
        $category = $topic['category'];
        if (!isset($categoryStats[$category])) {
            $categoryStats[$category] = [
                'count' => 0,
                'color' => $topic['color'],
                'topics' => []
            ];
        }
        $categoryStats[$category]['count']++;
        if (!in_array($topic['name'], $categoryStats[$category]['topics'])) {
            $categoryStats[$category]['topics'][] = $topic['name'];
        }
    }
}

$avgGrade = $gradeCount > 0 ? round($gradeSum / $gradeCount, 1) : 0;
$activePercentage = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Публичный дневник - <?php echo $diary ? htmlspecialchars($diary['name']) : 'Дневник не найден'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .public-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .diary-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .diary-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .diary-header h1 {
            font-size: 2.5em;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }
        
        .diary-header .student-info {
            font-size: 1.2em;
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }
        
        .diary-header .tutor-info {
            position: absolute;
            bottom: 20px;
            right: 30px;
            font-size: 0.9em;
            opacity: 0.8;
            z-index: 1;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #667eea;
            line-height: 1.2;
        }
        
        .stats-label {
            color: #666;
            font-size: 0.9em;
        }
        
        .category-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }
        
        .category-stat-item {
            background: #f8f9fa;
            border-radius: 20px;
            padding: 8px 15px;
            border-left: 4px solid;
            font-size: 0.9em;
        }
        
        .lesson-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-left: 4px solid;
        }
        
        .lesson-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .lesson-card.completed {
            border-left-color: #28a745;
        }
        
        .lesson-card.cancelled {
            border-left-color: #dc3545;
            opacity: 0.7;
        }
        
        .lesson-card.planned {
            border-left-color: #ffc107;
        }
        
        .lesson-date {
            font-size: 1.1em;
            font-weight: 600;
            color: #333;
        }
        
        .lesson-time {
            color: #667eea;
            font-weight: 500;
        }
        
        .grade-badge {
            display: inline-block;
            width: 30px;
            height: 30px;
            line-height: 30px;
            text-align: center;
            border-radius: 50%;
            font-weight: bold;
            margin-right: 5px;
        }
        
        .grade-5 { background: #28a745; color: white; }
        .grade-4 { background: #17a2b8; color: white; }
        .grade-3 { background: #ffc107; color: #333; }
        .grade-2 { background: #fd7e14; color: white; }
        .grade-1 { background: #dc3545; color: white; }
        .grade-0 { background: #6c757d; color: white; }
        
        .category-section {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 10px;
            background: #f8f9fa;
        }
        
        .category-title {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .category-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
            margin-right: 8px;
        }
        
        .topic-badge {
            background: white;
            border-radius: 15px;
            padding: 4px 12px;
            font-size: 0.85em;
            display: inline-block;
            margin-right: 5px;
            margin-bottom: 5px;
            border-left: 3px solid;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .month-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            margin: 30px 0 15px;
        }
        
        .info-item {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 0.9em;
        }
        
        .info-value {
            color: #333;
        }
        
        .watermark {
            text-align: center;
            margin-top: 40px;
            padding: 20px;
            color: #666;
            font-size: 0.9em;
            border-top: 1px solid rgba(0,0,0,0.1);
        }
        
        .error-container {
            max-width: 500px;
            margin: 100px auto;
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .error-container h1 {
            color: #dc3545;
            margin-bottom: 20px;
        }
        
        .btn-public {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 25px;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
            transition: transform 0.3s;
        }
        
        .btn-public:hover {
            transform: translateY(-2px);
            color: white;
        }
        
        .tooltip-inner {
            background-color: #333;
        }
        
        .stat-highlight {
            font-size: 1.1em;
            font-weight: 600;
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="public-container">
        <?php if (isset($error)): ?>
            <!-- Страница ошибки -->
            <div class="error-container">
                <i class="bi bi-exclamation-triangle" style="font-size: 4rem; color: #dc3545;"></i>
                <h1 class="mt-3">Дневник не найден</h1>
                <p class="text-muted"><?php echo htmlspecialchars($error); ?></p>
                <p>Возможно, ссылка устарела или была удалена.</p>
                <a href="<?php echo dirname($_SERVER['SCRIPT_NAME']); ?>/" class="btn-public">
                    <i class="bi bi-house"></i> На главную
                </a>
            </div>
        <?php else: ?>
            <!-- Шапка дневника -->
            <div class="diary-header">
                <h1>
                    <i class="bi bi-journal-bookmark-fill"></i> 
                    <?php echo htmlspecialchars($diary['name']); ?>
                </h1>
                <div class="student-info">
                    <i class="bi bi-person-circle"></i> 
                    <?php 
                    echo htmlspecialchars($diary['student_last_name'] . ' ' . $diary['student_first_name'] . ' ' . ($diary['student_middle_name'] ?? '')); 
                    if ($diary['student_class']): 
                        echo ' (' . htmlspecialchars($diary['student_class']) . ' класс)';
                    endif;
                    ?>
                </div>
                <?php if ($diary['category_name']): ?>
                    <div class="mt-2">
                        <span class="badge" style="background: <?php echo $diary['category_color'] ?? '#808080'; ?>; color: white; padding: 5px 15px;">
                            <i class="bi bi-tag"></i> <?php echo htmlspecialchars($diary['category_name']); ?>
                        </span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($diary['description'])): ?>
                    <div class="mt-3" style="max-width: 600px;">
                        <p><?php echo nl2br(htmlspecialchars($diary['description'])); ?></p>
                    </div>
                <?php endif; ?>
                <div class="tutor-info">
                    <i class="bi bi-person-workspace"></i> Репетитор: <?php echo htmlspecialchars($diary['tutor_first_name'] . ' ' . $diary['tutor_last_name']); ?>
                </div>
            </div>
            
            <!-- Статистика -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo $totalLessons; ?></div>
                        <div class="stats-label">Всего занятий</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo $completedLessons; ?></div>
                        <div class="stats-label">Проведено</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo $cancelledLessons; ?></div>
                        <div class="stats-label">Отменено</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo $avgGrade; ?></div>
                        <div class="stats-label">Средняя оценка</div>
                    </div>
                </div>
            </div>
            
            <!-- Статистика по категориям -->
            <?php if (!empty($categoryStats)): ?>
            <div class="stats-card mb-4">
                <h5><i class="bi bi-tags"></i> Изучаемые темы по категориям</h5>
                <div class="category-stats">
                    <?php foreach ($categoryStats as $category => $stat): ?>
                        <div class="category-stat-item" style="border-left-color: <?php echo $stat['color']; ?>;" 
                             title="<?php echo htmlspecialchars(implode(', ', $stat['topics'])); ?>">
                            <span class="stat-highlight"><?php echo htmlspecialchars($category); ?></span>
                            <span class="badge bg-secondary ms-2"><?php echo $stat['count']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Информация о дневнике -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="stats-card">
                        <h5><i class="bi bi-info-circle"></i> Информация о занятиях</h5>
                        <!--
                        <div class="info-item">
                            <span class="info-label">Стоимость занятия:</span>
                            <span class="info-value float-end">
                                <?php echo $diary['lesson_cost'] ? number_format($diary['lesson_cost'], 0, ',', ' ') . ' ₽' : 'Не указана'; ?>
                            </span>
                        </div>
                    -->
                        <div class="info-item">
                            <span class="info-label">Длительность занятия:</span>
                            <span class="info-value float-end">
                                <?php 
                                if ($diary['lesson_duration']) {
                                    $hours = floor($diary['lesson_duration'] / 60);
                                    $minutes = $diary['lesson_duration'] % 60;
                                    echo ($hours > 0 ? $hours . ' ч ' : '') . ($minutes > 0 ? $minutes . ' мин' : '');
                                } else {
                                    echo 'Не указана';
                                }
                                ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Дата создания:</span>
                            <span class="info-value float-end"><?php echo date('d.m.Y', strtotime($diary['created_at'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Последнее обновление:</span>
                            <span class="info-value float-end"><?php echo date('d.m.Y', strtotime($diary['updated_at'])); ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="stats-card">
                        <h5><i class="bi bi-graph-up"></i> Прогресс</h5>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Выполнено занятий</span>
                                <span><?php echo $completedLessons; ?> / <?php echo $totalLessons; ?></span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-success" style="width: <?php echo $activePercentage; ?>%"></div>
                            </div>
                        </div>
                        <?php if ($gradeCount > 0): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Средний балл</span>
                                <span><?php echo $avgGrade; ?> / 5</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <?php $gradePercent = round(($avgGrade / 5) * 100); ?>
                                <div class="progress-bar bg-info" style="width: <?php echo $gradePercent; ?>%"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Список занятий -->
            <h3 class="mb-3"><i class="bi bi-calendar-check"></i> Последние занятия</h3>
            
            <?php if (empty($lessons)): ?>
                <div class="alert alert-info text-center py-5">
                    <i class="bi bi-calendar-x" style="font-size: 3rem;"></i>
                    <h4 class="mt-3">В этом дневнике пока нет занятий</h4>
                </div>
            <?php else: ?>
                <?php foreach ($groupedLessons as $month => $monthLessons): ?>
                    <div class="month-header">
                        <h4 class="mb-0"><?php echo date('F Y', strtotime($month . '-01')); ?></h4>
                    </div>
                    
                    <?php foreach ($monthLessons as $lesson): 
                        $statusClass = $lesson['is_completed'] ? 'completed' : ($lesson['is_cancelled'] ? 'cancelled' : 'planned');
                    ?>
                        <div class="lesson-card <?php echo $statusClass; ?>">
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="lesson-date"><?php echo date('d.m.Y', strtotime($lesson['lesson_date'])); ?></div>
                                    <div class="lesson-time"><?php echo date('H:i', strtotime($lesson['start_time'])); ?></div>
                                    <div class="text-muted small">
                                        <i class="bi bi-clock"></i> <?php echo $lesson['duration']; ?> мин
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="mb-2">
                                        <?php if ($lesson['is_completed']): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle"></i> Проведено</span>
                                        <?php elseif ($lesson['is_cancelled']): ?>
                                            <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Отменено</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning"><i class="bi bi-clock"></i> Запланировано</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($lesson['grade_lesson'] !== null && $lesson['grade_lesson'] !== ''): ?>
                                        <div class="mb-1">
                                            <span class="grade-badge grade-<?php echo $lesson['grade_lesson']; ?>">
                                                <?php echo $lesson['grade_lesson']; ?>
                                            </span>
                                            <small class="text-muted">оценка за занятие</small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($lesson['grade_homework'] !== null && $lesson['grade_homework'] !== ''): ?>
                                        <div class="mb-1">
                                            <span class="grade-badge grade-<?php echo $lesson['grade_homework']; ?>">
                                                <?php echo $lesson['grade_homework']; ?>
                                            </span>
                                            <small class="text-muted">оценка за ДЗ</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-5">
                                    <!-- Темы, сгруппированные по категориям -->
                                    <?php if (!empty($lesson['topics_by_category'])): ?>
                                        <div class="mb-2">
                                            <small class="text-muted"><i class="bi bi-book"></i> Темы:</small>
                                            <?php foreach ($lesson['topics_by_category'] as $category => $data): ?>
                                                <div class="category-section">
                                                    <div class="category-title">
                                                        <div class="category-color" style="background: <?php echo $data['color']; ?>;"></div>
                                                        <?php echo htmlspecialchars($category); ?>
                                                    </div>
                                                    <div>
                                                        <?php foreach ($data['topics'] as $topic): ?>
                                                            <span class="topic-badge" style="border-left-color: <?php echo $data['color']; ?>;">
                                                                <?php echo htmlspecialchars($topic); ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($lesson['comment'])): ?>
                                        <div class="mb-2">
                                            <small class="text-muted"><i class="bi bi-chat"></i> Комментарий:</small>
                                            <div class="small bg-light p-2 rounded">
                                                <?php echo nl2br(htmlspecialchars($lesson['comment'])); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($lesson['homework_manual'])): ?>
                                        <div class="mb-2">
                                            <small class="text-muted"><i class="bi bi-journal-text"></i> ДЗ:</small>
                                            <div class="small bg-light p-2 rounded">
                                                <?php echo nl2br(htmlspecialchars($lesson['homework_manual'])); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-2">
                                    <?php if (!empty($lesson['link_url'])): ?>
                                        <div class="mb-2">
                                            <small class="text-muted"><i class="bi bi-link"></i> Ссылка:</small>
                                            <div>
                                                <a href="<?php echo htmlspecialchars($lesson['link_url']); ?>" target="_blank" class="small text-break">
                                                    <?php echo htmlspecialchars(substr($lesson['link_url'], 0, 30) . (strlen($lesson['link_url']) > 30 ? '...' : '')); ?>
                                                </a>
                                            </div>
                                            <?php if (!empty($lesson['link_comment'])): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($lesson['link_comment']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Водяной знак -->
            <div class="watermark">
                <i class="bi bi-journal-bookmark-fill"></i>
                Создано в системе "Дневник репетитора" • 
                <?php echo date('Y'); ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Инициализация всплывающих подсказок
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>