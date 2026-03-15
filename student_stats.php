<?php
require_once 'config.php';
requireAuth();

$currentUser = getCurrentUser($pdo);
$userId = $currentUser['id'];

$studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$period = $_GET['period'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-1 year'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Получение списка учеников для выпадающего списка
$stmt = $pdo->prepare("
    SELECT id, last_name, first_name, middle_name, class 
    FROM students 
    WHERE user_id = ? 
    ORDER BY last_name, first_name
");
$stmt->execute([$userId]);
$students = $stmt->fetchAll();

// Если ученик не выбран, берем первого из списка
if ($studentId == 0 && !empty($students)) {
    $studentId = $students[0]['id'];
}

// Получение информации об ученике
$student = null;
if ($studentId > 0) {
    $stmt = $pdo->prepare("
        SELECT s.*, 
               (SELECT COUNT(*) FROM diaries WHERE student_id = s.id) as diaries_count,
               (SELECT COUNT(*) FROM lessons l JOIN diaries d ON l.diary_id = d.id WHERE d.student_id = s.id) as total_lessons,
               (SELECT COUNT(*) FROM lessons l JOIN diaries d ON l.diary_id = d.id WHERE d.student_id = s.id AND l.is_completed = 1) as completed_lessons,
               (SELECT COUNT(*) FROM lessons l JOIN diaries d ON l.diary_id = d.id WHERE d.student_id = s.id AND l.is_cancelled = 1) as cancelled_lessons,
               (SELECT SUM(cost) FROM lessons l JOIN diaries d ON l.diary_id = d.id WHERE d.student_id = s.id) as total_cost,
               (SELECT SUM(cost) FROM lessons l JOIN diaries d ON l.diary_id = d.id WHERE d.student_id = s.id AND l.is_paid = 1) as paid_cost,
               (SELECT AVG(grade_lesson) FROM lessons l JOIN diaries d ON l.diary_id = d.id WHERE d.student_id = s.id AND l.grade_lesson IS NOT NULL) as avg_grade,
               (SELECT AVG(grade_homework) FROM lessons l JOIN diaries d ON l.diary_id = d.id WHERE d.student_id = s.id AND l.grade_homework IS NOT NULL) as avg_homework
        FROM students s
        WHERE s.id = ? AND s.user_id = ?
    ");
    $stmt->execute([$studentId, $userId]);
    $student = $stmt->fetch();
}

// Получение статистики по темам
$topicsStats = [];
if ($studentId > 0) {
    $dateFilter = "";
    $params = [$studentId];
    
    if ($period !== 'all') {
        if ($period === 'month') {
            $dateFilter = " AND l.lesson_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        } elseif ($period === 'quarter') {
            $dateFilter = " AND l.lesson_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
        } elseif ($period === 'year') {
            $dateFilter = " AND l.lesson_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        }
    } elseif (!empty($dateFrom) && !empty($dateTo)) {
        $dateFilter = " AND l.lesson_date BETWEEN ? AND ?";
        $params[] = $dateFrom;
        $params[] = $dateTo;
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            t.id,
            t.name as topic_name,
            c.name as category_name,
            c.color as category_color,
            COUNT(DISTINCT l.id) as lessons_count,
            AVG(l.grade_lesson) as avg_grade,
            MAX(l.grade_lesson) as max_grade,
            MIN(l.grade_lesson) as min_grade,
            GROUP_CONCAT(DISTINCT DATE_FORMAT(l.lesson_date, '%d.%m.%Y') ORDER BY l.lesson_date DESC SEPARATOR ', ') as dates,
            SUM(CASE WHEN l.grade_lesson >= 4 THEN 1 ELSE 0 END) as good_grades,
            SUM(CASE WHEN l.grade_lesson < 4 AND l.grade_lesson IS NOT NULL THEN 1 ELSE 0 END) as bad_grades
        FROM topics t
        JOIN lesson_topics lt ON t.id = lt.topic_id
        JOIN lessons l ON lt.lesson_id = l.id
        JOIN diaries d ON l.diary_id = d.id
        LEFT JOIN categories c ON t.category_id = c.id
        WHERE d.student_id = ? AND l.is_completed = 1
        $dateFilter
        GROUP BY t.id, t.name, c.name, c.color
        ORDER BY lessons_count DESC, t.name
    ");
    
    $stmt->execute($params);
    $topicsStats = $stmt->fetchAll();
}

// Получение динамики успеваемости по месяцам
$progressStats = [];
if ($studentId > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(l.lesson_date, '%Y-%m') as month,
            COUNT(*) as lessons_count,
            AVG(l.grade_lesson) as avg_grade,
            AVG(l.grade_homework) as avg_homework,
            SUM(l.cost) as monthly_cost,
            SUM(CASE WHEN l.is_paid = 1 THEN l.cost ELSE 0 END) as paid_cost
        FROM lessons l
        JOIN diaries d ON l.diary_id = d.id
        WHERE d.student_id = ? AND l.is_completed = 1
        GROUP BY month
        ORDER BY month DESC
        LIMIT 12
    ");
    $stmt->execute([$studentId]);
    $progressStats = $stmt->fetchAll();
}

// Получение последних занятий
$recentLessons = [];
if ($studentId > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            l.*,
            d.name as diary_name,
            GROUP_CONCAT(DISTINCT t.name SEPARATOR ', ') as topics
        FROM lessons l
        JOIN diaries d ON l.diary_id = d.id
        LEFT JOIN lesson_topics lt ON l.id = lt.lesson_id
        LEFT JOIN topics t ON lt.topic_id = t.id
        WHERE d.student_id = ?
        GROUP BY l.id
        ORDER BY l.lesson_date DESC, l.start_time DESC
        LIMIT 10
    ");
    $stmt->execute([$studentId]);
    $recentLessons = $stmt->fetchAll();
}

// Получение статистики по оценкам
$gradeDistribution = [];
if ($studentId > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            l.grade_lesson,
            COUNT(*) as count
        FROM lessons l
        JOIN diaries d ON l.diary_id = d.id
        WHERE d.student_id = ? AND l.grade_lesson IS NOT NULL
        GROUP BY l.grade_lesson
        ORDER BY l.grade_lesson
    ");
    $stmt->execute([$studentId]);
    $gradeDistribution = $stmt->fetchAll();
}

// Получение статистики по дням недели
$weekdayStats = [];
if ($studentId > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            DAYOFWEEK(l.lesson_date) as day_of_week,
            CASE DAYOFWEEK(l.lesson_date)
                WHEN 1 THEN 'Воскресенье'
                WHEN 2 THEN 'Понедельник'
                WHEN 3 THEN 'Вторник'
                WHEN 4 THEN 'Среда'
                WHEN 5 THEN 'Четверг'
                WHEN 6 THEN 'Пятница'
                WHEN 7 THEN 'Суббота'
            END as day_name,
            COUNT(*) as lessons_count,
            AVG(l.grade_lesson) as avg_grade
        FROM lessons l
        JOIN diaries d ON l.diary_id = d.id
        WHERE d.student_id = ?
        GROUP BY day_of_week
        ORDER BY day_of_week
    ");
    $stmt->execute([$studentId]);
    $weekdayStats = $stmt->fetchAll();
}

// Формирование данных для графиков
$gradeLabels = [];
$gradeCounts = [];
foreach ($gradeDistribution as $grade) {
    $gradeLabels[] = 'Оценка ' . $grade['grade_lesson'];
    $gradeCounts[] = $grade['count'];
}

$monthLabels = [];
$monthGrades = [];
$monthCosts = [];
foreach (array_reverse($progressStats) as $stat) {
    $monthLabels[] = date('M Y', strtotime($stat['month'] . '-01'));
    $monthGrades[] = round($stat['avg_grade'] ?? 0, 1);
    $monthCosts[] = $stat['monthly_cost'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Статистика ученика - Дневник репетитора</title>
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .student-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
            line-height: 1.2;
        }
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        .topic-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid;
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
        
        .filter-panel {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .progress-indicator {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            margin-top: 10px;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>
    
    <div class="container-fluid py-4">
        <!-- Заголовок и выбор ученика -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-person-lines-fill"></i> Статистика ученика</h2>
            <a href="statistics.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> К общей статистике
            </a>
        </div>
        
        <!-- Форма выбора ученика и периода -->
        <div class="filter-panel">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Выберите ученика</label>
                    <select name="student_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">Выберите ученика</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo $studentId == $s['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['last_name'] . ' ' . $s['first_name'] . ' ' . ($s['middle_name'] ?? '')); ?>
                                <?php if ($s['class']): ?>(<?php echo htmlspecialchars($s['class']); ?> класс)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Период</label>
                    <select name="period" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?php echo $period == 'all' ? 'selected' : ''; ?>>За все время</option>
                        <option value="month" <?php echo $period == 'month' ? 'selected' : ''; ?>>За месяц</option>
                        <option value="quarter" <?php echo $period == 'quarter' ? 'selected' : ''; ?>>За квартал</option>
                        <option value="year" <?php echo $period == 'year' ? 'selected' : ''; ?>>За год</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Дата с</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo $dateFrom; ?>" onchange="this.form.submit()">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Дата по</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo $dateTo; ?>" onchange="this.form.submit()">
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-filter"></i> Применить
                    </button>
                </div>
            </form>
        </div>
        
        <?php if ($student): ?>
            <!-- Шапка с информацией об ученике -->
            <div class="student-header">
                <div class="row">
                    <div class="col-md-8">
                        <h2><?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? '')); ?></h2>
                        <?php if ($student['class']): ?>
                            <p class="mb-2"><i class="bi bi-backpack"></i> <?php echo htmlspecialchars($student['class']); ?> класс</p>
                        <?php endif; ?>
                        <?php if ($student['goals']): ?>
                            <p><i class="bi bi-bullseye"></i> <?php echo nl2br(htmlspecialchars($student['goals'])); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 text-end">
                        <p><i class="bi bi-calendar"></i> Начало: <?php echo $student['start_date'] ? date('d.m.Y', strtotime($student['start_date'])) : 'не указано'; ?></p>
                        <p><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($student['phone'] ?? 'телефон не указан'); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Основные показатели -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $student['total_lessons'] ?? 0; ?></div>
                        <div class="stat-label">Всего занятий</div>
                        <div class="progress-indicator">
                            <div class="progress-fill" style="width: <?php echo $student['total_lessons'] ? 100 : 0; ?>%"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $student['completed_lessons'] ?? 0; ?></div>
                        <div class="stat-label">Проведено</div>
                        <small class="text-muted">отменено: <?php echo $student['cancelled_lessons'] ?? 0; ?></small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($student['avg_grade'] ?? 0, 1); ?></div>
                        <div class="stat-label">Средняя оценка</div>
                        <small class="text-muted">ДЗ: <?php echo number_format($student['avg_homework'] ?? 0, 1); ?></small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($student['paid_cost'] ?? 0, 0, ',', ' '); ?> ₽</div>
                        <div class="stat-label">Оплачено</div>
                        <small class="text-muted">долг: <?php echo number_format(($student['total_cost'] ?? 0) - ($student['paid_cost'] ?? 0), 0, ',', ' '); ?> ₽</small>
                    </div>
                </div>
            </div>
            
            <!-- Графики -->
            <div class="row">
                <div class="col-md-6">
                    <div class="chart-container">
                        <h5>Динамика успеваемости</h5>
                        <canvas id="progressChart"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-container">
                        <h5>Распределение оценок</h5>
                        <canvas id="gradesChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="chart-container">
                        <h5>Занятия по дням недели</h5>
                        <canvas id="weekdayChart"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-container">
                        <h5>Финансовая динамика</h5>
                        <canvas id="financeChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Статистика по темам -->
            <div class="stat-card">
                <h5 class="mb-3"><i class="bi bi-book"></i> Изученные темы</h5>
                <?php if (empty($topicsStats)): ?>
                    <p class="text-muted text-center py-3">Нет данных по темам за выбранный период</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Тема</th>
                                    <th>Категория</th>
                                    <th class="text-center">Занятий</th>
                                    <th class="text-center">Ср. оценка</th>
                                    <th class="text-center">Успеваемость</th>
                                    <th>Даты</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topicsStats as $topic): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($topic['topic_name']); ?></td>
                                        <td>
                                            <?php if ($topic['category_name']): ?>
                                                <span class="badge" style="background: <?php echo $topic['category_color'] ?? '#808080'; ?>; color: white;">
                                                    <?php echo htmlspecialchars($topic['category_name']); ?>
                                                </span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"><?php echo $topic['lessons_count']; ?></td>
                                        <td class="text-center">
                                            <span class="grade-badge grade-<?php echo round($topic['avg_grade']); ?>">
                                                <?php echo number_format($topic['avg_grade'], 1); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div style="width: 100px; margin: 0 auto;">
                                                <div class="progress" style="height: 10px;">
                                                    <?php 
                                                    $goodPercent = $topic['lessons_count'] > 0 ? ($topic['good_grades'] / $topic['lessons_count']) * 100 : 0;
                                                    $badPercent = $topic['lessons_count'] > 0 ? ($topic['bad_grades'] / $topic['lessons_count']) * 100 : 0;
                                                    ?>
                                                    <div class="progress-bar bg-success" style="width: <?php echo $goodPercent; ?>%" 
                                                         title="Хороших оценок: <?php echo $topic['good_grades']; ?>"></div>
                                                    <div class="progress-bar bg-warning" style="width: <?php echo $badPercent; ?>%" 
                                                         title="Плохих оценок: <?php echo $topic['bad_grades']; ?>"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php 
                                                $dates = explode(', ', $topic['dates']);
                                                echo implode(', ', array_slice($dates, 0, 3));
                                                if (count($dates) > 3) echo '...';
                                                ?>
                                            </small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Последние занятия -->
            <div class="stat-card">
                <h5 class="mb-3"><i class="bi bi-clock-history"></i> Последние занятия</h5>
                <?php if (empty($recentLessons)): ?>
                    <p class="text-muted text-center py-3">Нет проведенных занятий</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Дата</th>
                                    <th>Время</th>
                                    <th>Дневник</th>
                                    <th>Темы</th>
                                    <th class="text-center">Оценка</th>
                                    <th class="text-end">Стоимость</th>
                                    <th class="text-center">Статус</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentLessons as $lesson): ?>
                                    <tr>
                                        <td><?php echo date('d.m.Y', strtotime($lesson['lesson_date'])); ?></td>
                                        <td><?php echo date('H:i', strtotime($lesson['start_time'])); ?></td>
                                        <td><?php echo htmlspecialchars($lesson['diary_name']); ?></td>
                                        <td>
                                            <?php if ($lesson['topics']): ?>
                                                <small><?php echo htmlspecialchars(substr($lesson['topics'], 0, 50)) . (strlen($lesson['topics']) > 50 ? '...' : ''); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($lesson['grade_lesson'] !== null): ?>
                                                <span class="grade-badge grade-<?php echo $lesson['grade_lesson']; ?>">
                                                    <?php echo $lesson['grade_lesson']; ?>
                                                </span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end"><?php echo number_format($lesson['cost'] ?? 0, 0, ',', ' '); ?> ₽</td>
                                        <td class="text-center">
                                            <?php if ($lesson['is_completed']): ?>
                                                <span class="badge bg-success">Проведено</span>
                                            <?php elseif ($lesson['is_cancelled']): ?>
                                                <span class="badge bg-danger">Отменено</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Запланировано</span>
                                            <?php endif; ?>
                                            <?php if ($lesson['is_paid']): ?>
                                                <span class="badge bg-info">Оплачено</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
        <?php elseif ($studentId > 0): ?>
            <div class="alert alert-danger">Ученик не найден</div>
        <?php else: ?>
            <div class="alert alert-info text-center py-5">
                <i class="bi bi-person" style="font-size: 3rem;"></i>
                <h4 class="mt-3">Выберите ученика для просмотра статистики</h4>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // График успеваемости
    new Chart(document.getElementById('progressChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($monthLabels); ?>,
            datasets: [{
                label: 'Средняя оценка',
                data: <?php echo json_encode($monthGrades); ?>,
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 5,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
    
    // График распределения оценок
    new Chart(document.getElementById('gradesChart'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($gradeLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($gradeCounts); ?>,
                backgroundColor: [
                    '#28a745', '#17a2b8', '#ffc107', '#fd7e14', '#dc3545', '#6c757d'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    
    // График по дням недели
    new Chart(document.getElementById('weekdayChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($weekdayStats, 'day_name')); ?>,
            datasets: [{
                label: 'Количество занятий',
                data: <?php echo json_encode(array_column($weekdayStats, 'lessons_count')); ?>,
                backgroundColor: '#667eea',
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
    
    // Финансовый график
    new Chart(document.getElementById('financeChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($monthLabels); ?>,
            datasets: [{
                label: 'Доход (₽)',
                data: <?php echo json_encode($monthCosts); ?>,
                backgroundColor: '#28a745',
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString() + ' ₽';
                        }
                    }
                }
            }
        }
    });
    </script>
</body>
</html>