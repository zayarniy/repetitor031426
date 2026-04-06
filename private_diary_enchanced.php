<?php
require_once 'config.php';
requireAuth();

$currentUser = getCurrentUser($pdo);
$userId = $currentUser['id'];

// Получаем ID дневника из URL
$diaryId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$diaryId) {
    header('Location: diaries.php');
    exit();
}

// Получаем информацию о дневнике с данными ученика
$stmt = $pdo->prepare("
    SELECT 
        d.*,
        s.id as student_id,
        s.first_name as student_first_name,
        s.last_name as student_last_name,
        s.middle_name as student_middle_name,
        s.class as student_class,
        s.phone as student_phone,
        s.email as student_email,
        s.goals as student_goals,
        s.birth_date as student_birth_date,
        s.start_date as student_start_date,
        s.end_date as student_end_date,
        s.city as student_city,
        s.messenger1 as student_messenger1,
        s.messenger2 as student_messenger2,
        s.messenger3 as student_messenger3,
        s.created_at as student_created_at,
        c.name as category_name,
        c.color as category_color,
        u.first_name as tutor_first_name,
        u.last_name as tutor_last_name
    FROM diaries d
    JOIN students s ON d.student_id = s.id
    JOIN users u ON d.user_id = u.id
    LEFT JOIN categories c ON d.category_id = c.id
    WHERE d.id = ? AND d.user_id = ?
");
$stmt->execute([$diaryId, $userId]);
$diary = $stmt->fetch();

if (!$diary) {
    header('Location: diaries.php');
    exit();
}

// Получаем статистику по занятиям
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_lessons,
        SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed_lessons,
        SUM(CASE WHEN is_cancelled = 1 THEN 1 ELSE 0 END) as cancelled_lessons,
        SUM(CASE WHEN is_paid = 0 AND is_completed = 1 THEN 1 ELSE 0 END) as unpaid_lessons,
        SUM(CASE WHEN is_paid = 1 THEN cost ELSE 0 END) as paid_sum,
        SUM(cost) as total_sum,
        AVG(grade_lesson) as avg_grade,
        AVG(grade_homework) as avg_homework,
        SUM(duration) as total_minutes
    FROM lessons 
    WHERE diary_id = ?
");
$stmt->execute([$diaryId]);
$stats = $stmt->fetch();

// Получаем статистику по темам
$stmt = $pdo->prepare("
    SELECT 
        t.id,
        t.name as topic_name,
        COALESCE(c.name, 'Без категории') as category_name,
        COALESCE(c.color, '#808080') as category_color,
        COUNT(DISTINCT l.id) as lessons_count,
        COUNT(*) as total_occurrences,
        MIN(l.grade_lesson) as min_grade,
        MAX(l.grade_lesson) as max_grade,
        AVG(l.grade_lesson) as avg_grade,
        COUNT(CASE WHEN l.grade_lesson IS NOT NULL THEN 1 END) as graded_count,
        MAX(l.lesson_date) as last_used
    FROM topics t
    JOIN lesson_topics lt ON t.id = lt.topic_id
    JOIN lessons l ON lt.lesson_id = l.id
    LEFT JOIN categories c ON t.category_id = c.id
    WHERE l.diary_id = ? AND l.is_completed = 1
    GROUP BY t.id, t.name, c.name, c.color
    ORDER BY total_occurrences DESC, t.name
");
$stmt->execute([$diaryId]);
$topicsStats = $stmt->fetchAll();

// Группируем темы по категориям
$groupedTopics = [];
foreach ($topicsStats as $topic) {
    $category = $topic['category_name'];
    if (!isset($groupedTopics[$category])) {
        $groupedTopics[$category] = [
            'color' => $topic['category_color'],
            'topics' => []
        ];
    }
    $groupedTopics[$category]['topics'][] = $topic;
}

// Получаем статистику по оценкам
$stmt = $pdo->prepare("
    SELECT 
        grade_lesson,
        grade_homework
    FROM lessons 
    WHERE diary_id = ? AND is_completed = 1
");
$stmt->execute([$diaryId]);
$grades = $stmt->fetchAll();

$gradeStats = [
    'lesson' => ['total' => 0, 'count' => 0, 'avg' => 0, 'distribution' => []],
    'homework' => ['total' => 0, 'count' => 0, 'avg' => 0, 'distribution' => []]
];

foreach ($grades as $grade) {
    // Оценки за занятие
    if ($grade['grade_lesson'] !== null && $grade['grade_lesson'] !== '' && $grade['grade_lesson'] > 0) {
        $gradeStats['lesson']['total'] += $grade['grade_lesson'];
        $gradeStats['lesson']['count']++;
        $val = (int)$grade['grade_lesson'];
        $gradeStats['lesson']['distribution'][$val] = ($gradeStats['lesson']['distribution'][$val] ?? 0) + 1;
    }
    
    // Оценки за домашнюю работу
    if ($grade['grade_homework'] !== null && $grade['grade_homework'] !== '' && $grade['grade_homework'] > 0) {
        $gradeStats['homework']['total'] += $grade['grade_homework'];
        $gradeStats['homework']['count']++;
        $val = (int)$grade['grade_homework'];
        $gradeStats['homework']['distribution'][$val] = ($gradeStats['homework']['distribution'][$val] ?? 0) + 1;
    }
}

$gradeStats['lesson']['avg'] = $gradeStats['lesson']['count'] > 0 
    ? round($gradeStats['lesson']['total'] / $gradeStats['lesson']['count'], 1) : 0;
$gradeStats['homework']['avg'] = $gradeStats['homework']['count'] > 0 
    ? round($gradeStats['homework']['total'] / $gradeStats['homework']['count'], 1) : 0;

// Получаем динамику успеваемости по месяцам
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(l.lesson_date, '%Y-%m') as month,
        COUNT(*) as lessons_count,
        AVG(l.grade_lesson) as avg_grade,
        AVG(l.grade_homework) as avg_homework,
        SUM(l.cost) as total_cost
    FROM lessons l
    WHERE l.diary_id = ? AND l.is_completed = 1
    GROUP BY month
    ORDER BY month DESC
    LIMIT 12
");
$stmt->execute([$diaryId]);
$progressStats = $stmt->fetchAll();

// Получаем последние 10 занятий
$stmt = $pdo->prepare("
    SELECT 
        l.*,
        GROUP_CONCAT(DISTINCT t.name SEPARATOR ', ') as topic_names
    FROM lessons l
    LEFT JOIN lesson_topics lt ON l.id = lt.lesson_id
    LEFT JOIN topics t ON lt.topic_id = t.id
    WHERE l.diary_id = ?
    GROUP BY l.id
    ORDER BY l.lesson_date DESC, l.start_time DESC
    LIMIT 10
");
$stmt->execute([$diaryId]);
$recentLessons = $stmt->fetchAll();

// Получаем комментарии к ученику
$stmt = $pdo->prepare("
    SELECT sc.*, u.first_name, u.last_name 
    FROM student_comments sc
    JOIN users u ON sc.user_id = u.id
    WHERE sc.student_id = ? 
    ORDER BY sc.created_at DESC
    LIMIT 10
");
$stmt->execute([$diary['student_id']]);
$studentComments = $stmt->fetchAll();

// Получаем информацию о родителях
$stmt = $pdo->prepare("
    SELECT * FROM parents 
    WHERE student_id = ?
    ORDER BY full_name
");
$stmt->execute([$diary['student_id']]);
$parents = $stmt->fetchAll();

// Форматирование времени
$totalHours = floor(($stats['total_minutes'] ?? 0) / 60);
$totalMinutes = ($stats['total_minutes'] ?? 0) % 60;
$totalTimeFormatted = '';
if ($totalHours > 0) {
    $totalTimeFormatted .= $totalHours . ' ч ';
}
if ($totalMinutes > 0 || $totalHours == 0) {
    $totalTimeFormatted .= $totalMinutes . ' мин';
}

// Данные для графиков
$monthLabels = [];
$monthGrades = [];
$monthHomework = [];
$monthCosts = [];

foreach (array_reverse($progressStats) as $stat) {
    $monthLabels[] = date('M Y', strtotime($stat['month'] . '-01'));
    $monthGrades[] = round($stat['avg_grade'] ?? 0, 1);
    $monthHomework[] = round($stat['avg_homework'] ?? 0, 1);
    $monthCosts[] = $stat['total_cost'] ?? 0;
}

$gradeLabels = [];
$gradeCounts = [];
for ($i = 5; $i >= 1; $i--) {
    $gradeLabels[] = 'Оценка ' . $i;
    $gradeCounts[] = $gradeStats['lesson']['distribution'][$i] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Дневник - <?php echo htmlspecialchars($diary['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        
        .diary-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            position: relative;
        }
        
        .action-buttons {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
        }
        
        .action-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            color: white;
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
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
            line-height: 1.2;
        }
        
        .stats-label {
            color: #666;
            font-size: 0.9em;
        }
        
        .info-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .info-title {
            font-size: 1.1em;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #333;
        }
        
        .info-title i {
            color: #667eea;
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
        
        .topic-category {
            margin-bottom: 20px;
        }
        
        .topic-category-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid;
        }
        
        .topic-table {
            font-size: 0.9em;
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
        
        .lesson-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #667eea;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .lesson-card:hover {
            transform: translateX(5px);
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
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
        
        .comment-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 3px solid #667eea;
        }
        
        .comment-meta {
            font-size: 0.85em;
            color: #666;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
        }
        
        .badge-unpaid {
            background: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9em;
        }
        
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .progress {
            height: 8px;
            border-radius: 4px;
        }
        
        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            border-radius: 12px;
            padding: 8px 0;
            min-width: 200px;
        }
        
        .dropdown-item {
            padding: 10px 20px;
            transition: all 0.2s;
        }
        
        .dropdown-item:hover {
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            padding-left: 25px;
        }
        
        .dropdown-item i {
            width: 22px;
            margin-right: 8px;
        }
        
        .nav-tabs {
            border-bottom: none;
            gap: 5px;
            background: white;
            padding: 10px 10px 0 10px;
            border-radius: 15px 15px 0 0;
        }
        
        .nav-tabs .nav-link {
            color: #495057;
            border: none;
            padding: 12px 20px;
            font-weight: 500;
            transition: all 0.3s;
            border-radius: 12px 12px 0 0;
            background: #f8f9fa;
        }
        
        .nav-tabs .nav-link:hover {
            color: #667eea;
            background: #e9ecef;
        }
        
        .nav-tabs .nav-link.active {
            color: #667eea;
            background: white;
            border-bottom: 3px solid #667eea;
        }
        
        .tab-content {
            background: white;
            border-radius: 0 0 15px 15px;
            padding: 25px;
            min-height: 500px;
        }
        
        @media (max-width: 768px) {
            .action-buttons {
                position: static;
                justify-content: flex-end;
                margin-bottom: 20px;
            }
            
            .stats-number {
                font-size: 1.5em;
            }
            
            .topic-table {
                font-size: 0.75em;
            }
            
            .nav-tabs {
                flex-wrap: wrap;
            }
            
            .nav-tabs .nav-link {
                padding: 8px 12px;
                font-size: 0.85rem;
                flex: 1;
                text-align: center;
            }
            
            .tab-content {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>
    
    <div class="container-fluid py-4">
        <!-- Шапка дневника -->
        <div class="diary-header">
            <div class="action-buttons">
                <a href="lessons.php?diary_id=<?php echo $diaryId; ?>" class="action-btn" data-bs-toggle="tooltip" title="Перейти к занятиям">
                    <i class="bi bi-calendar-check"></i>
                </a>
                <a href="students.php?action=view&id=<?php echo $diary['student_id']; ?>" class="action-btn" data-bs-toggle="tooltip" title="Информация об ученике">
                    <i class="bi bi-person"></i>
                </a>
                <a href="diaries.php" class="action-btn" data-bs-toggle="tooltip" title="К списку дневников">
                    <i class="bi bi-journals"></i>
                </a>
                <?php if ($diary['public_link']): ?>
                    <button class="action-btn copy-link-btn" onclick="copyPublicLink()" data-bs-toggle="tooltip" title="Копировать публичную ссылку">
                        <i class="bi bi-link"></i>
                    </button>
                <?php endif; ?>
                <div class="dropdown">
                    <button class="action-btn" type="button" data-bs-toggle="dropdown" data-bs-toggle="tooltip" title="Экспорт данных">
                        <i class="bi bi-download"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="?id=<?php echo $diaryId; ?>&export_csv=1"><i class="bi bi-filetype-csv text-success"></i> Экспорт в CSV</a></li>
                        <li><a class="dropdown-item" href="?id=<?php echo $diaryId; ?>&export_json=1"><i class="bi bi-filetype-json text-warning"></i> Экспорт в JSON</a></li>
                    </ul>
                </div>
            </div>
            
            <h1 class="mb-2"><?php echo htmlspecialchars($diary['name']); ?></h1>
            <p class="mb-1">
                <i class="bi bi-person-circle"></i> 
                <?php echo htmlspecialchars($diary['student_last_name'] . ' ' . $diary['student_first_name'] . ' ' . ($diary['student_middle_name'] ?? '')); ?>
                <?php if ($diary['student_class']): ?>
                    <span class="badge bg-light text-dark ms-2"><?php echo htmlspecialchars($diary['student_class']); ?> класс</span>
                <?php endif; ?>
            </p>
            <?php if ($diary['category_name']): ?>
                <span class="badge" style="background: <?php echo $diary['category_color'] ?? '#808080'; ?>; color: white;">
                    <i class="bi bi-tag"></i> <?php echo htmlspecialchars($diary['category_name']); ?>
                </span>
            <?php endif; ?>
            <?php if (!empty($diary['description'])): ?>
                <div class="mt-3">
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($diary['description'])); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Статистика -->
        <div class="row mb-4">
            <div class="col-6 col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['total_lessons'] ?? 0; ?></div>
                    <div class="stats-label">Всего занятий</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['completed_lessons'] ?? 0; ?></div>
                    <div class="stats-label">Проведено</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo number_format($stats['paid_sum'] ?? 0, 0, ',', ' '); ?> ₽</div>
                    <div class="stats-label">Оплачено</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $totalTimeFormatted ?: '0 мин'; ?></div>
                    <div class="stats-label">Общее время</div>
                </div>
            </div>
        </div>
        
        <!-- Вкладки -->
        <div class="card shadow-sm mb-4" style="border: none; overflow: hidden;">
            <ul class="nav nav-tabs" id="statsTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#overview">
                        <i class="bi bi-info-circle"></i> Обзор
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#topics">
                        <i class="bi bi-book"></i> Темы
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#grades">
                        <i class="bi bi-graph-up"></i> Успеваемость
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#lessons">
                        <i class="bi bi-clock-history"></i> Занятия
                    </a>
                </li>
            </ul>
            
            <div class="tab-content">
                <!-- Вкладка: Обзор -->
                <div class="tab-pane fade show active" id="overview">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-section">
                                <div class="info-title">
                                    <i class="bi bi-person-vcard"></i> Информация об ученике
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Телефон:</span>
                                    <span class="info-value float-end"><?php echo htmlspecialchars($diary['student_phone'] ?? 'Не указан'); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Email:</span>
                                    <span class="info-value float-end"><?php echo htmlspecialchars($diary['student_email'] ?? 'Не указан'); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Город:</span>
                                    <span class="info-value float-end"><?php echo htmlspecialchars($diary['student_city'] ?? 'Не указан'); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Начало занятий:</span>
                                    <span class="info-value float-end"><?php echo $diary['student_start_date'] ? date('d.m.Y', strtotime($diary['student_start_date'])) : 'Не указано'; ?></span>
                                </div>
                                <?php if (!empty($diary['student_goals'])): ?>
                                    <div class="mt-3">
                                        <span class="info-label">Цели обучения:</span>
                                        <div class="p-3 bg-light rounded mt-2">
                                            <?php echo nl2br(htmlspecialchars($diary['student_goals'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-section">
                                <div class="info-title">
                                    <i class="bi bi-pie-chart"></i> Финансы
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Общая стоимость:</span>
                                    <span class="info-value float-end"><?php echo number_format($stats['total_sum'] ?? 0, 0, ',', ' '); ?> ₽</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Оплачено:</span>
                                    <span class="info-value float-end"><?php echo number_format($stats['paid_sum'] ?? 0, 0, ',', ' '); ?> ₽</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Долг:</span>
                                    <span class="info-value float-end text-danger"><?php echo number_format(($stats['total_sum'] ?? 0) - ($stats['paid_sum'] ?? 0), 0, ',', ' '); ?> ₽</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Неоплаченных занятий:</span>
                                    <span class="info-value float-end"><?php echo $stats['unpaid_lessons'] ?? 0; ?></span>
                                </div>
                            </div>
                            
                            <?php if (!empty($parents)): ?>
                            <div class="info-section">
                                <div class="info-title">
                                    <i class="bi bi-people"></i> Родители
                                </div>
                                <?php foreach ($parents as $parent): ?>
                                    <div class="info-item">
                                        <span class="info-label"><?php echo htmlspecialchars($parent['relationship'] ?? 'Родитель'); ?>:</span>
                                        <span class="info-value float-end"><?php echo htmlspecialchars($parent['full_name']); ?></span>
                                    </div>
                                    <?php if (!empty($parent['phone'])): ?>
                                        <div class="info-item">
                                            <span class="info-label">Телефон:</span>
                                            <span class="info-value float-end"><?php echo htmlspecialchars($parent['phone']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Вкладка: Темы -->
                <div class="tab-pane fade" id="topics">
                    <?php if (empty($groupedTopics)): ?>
                        <p class="text-muted text-center py-5">Нет данных по изученным темам</p>
                    <?php else: ?>
                        <?php foreach ($groupedTopics as $category => $data): ?>
                            <div class="topic-category">
                                <div class="topic-category-header" style="border-left-color: <?php echo $data['color']; ?>;">
                                    <span class="fw-bold"><?php echo htmlspecialchars($category); ?></span>
                                    <span class="badge bg-secondary"><?php echo count($data['topics']); ?> тем</span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover topic-table">
                                        <thead>
                                            <tr>
                                                <th>Тема</th>
                                                <th class="text-center">Всего</th>
                                                <th class="text-center">В занятиях</th>
                                                <th class="text-center">Мин</th>
                                                <th class="text-center">Макс</th>
                                                <th class="text-center">Средний</th>
                                                <th>Последнее</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($data['topics'] as $topic): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($topic['topic_name']); ?></td>
                                                <td class="text-center"><?php echo $topic['total_occurrences']; ?></td>
                                                <td class="text-center"><?php echo $topic['lessons_count']; ?></td>
                                                <td class="text-center"><?php echo $topic['min_grade'] ?? '—'; ?></td>
                                                <td class="text-center"><?php echo $topic['max_grade'] ?? '—'; ?></td>
                                                <td class="text-center"><?php echo $topic['avg_grade'] ? round($topic['avg_grade'], 1) : '—'; ?></td>
                                                <td><?php echo date('d.m.Y', strtotime($topic['last_used'])); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Вкладка: Успеваемость -->
                <div class="tab-pane fade" id="grades">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="chart-container">
                                <h5>Динамика успеваемости</h5>
                                <canvas id="progressChart" style="height: 300px;"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="chart-container">
                                <h5>Распределение оценок</h5>
                                <canvas id="gradesChart" style="height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="stats-card text-center">
                                <h5><i class="bi bi-star-fill text-warning"></i> Оценки за занятия</h5>
                                <div class="stats-number" style="font-size: 2.5em;"><?php echo $gradeStats['lesson']['avg']; ?></div>
                                <div class="stats-label">Средняя оценка</div>
                                <small class="text-muted">Выставлено: <?php echo $gradeStats['lesson']['count']; ?> оценок</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stats-card text-center">
                                <h5><i class="bi bi-journal-check text-success"></i> Оценки за ДЗ</h5>
                                <div class="stats-number" style="font-size: 2.5em;"><?php echo $gradeStats['homework']['avg']; ?></div>
                                <div class="stats-label">Средняя оценка</div>
                                <small class="text-muted">Выставлено: <?php echo $gradeStats['homework']['count']; ?> оценок</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Вкладка: Занятия -->
                <div class="tab-pane fade" id="lessons">
                    <?php if (empty($recentLessons)): ?>
                        <p class="text-muted text-center py-5">В этом дневнике пока нет занятий</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Дата</th>
                                        <th>Время</th>
                                        <th>Темы</th>
                                        <th class="text-center">Оценка</th>
                                        <th class="text-end">Стоимость</th>
                                        <th class="text-center">Статус</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentLessons as $lesson): 
                                        $statusClass = $lesson['is_completed'] ? 'completed' : ($lesson['is_cancelled'] ? 'cancelled' : 'planned');
                                    ?>
                                    <tr class="lesson-row" onclick="window.location.href='lessons.php?action=edit&id=<?php echo $lesson['id']; ?>&diary_id=<?php echo $diaryId; ?>'">
                                        <td><?php echo date('d.m.Y', strtotime($lesson['lesson_date'])); ?> <br><small><?php echo date('H:i', strtotime($lesson['start_time'])); ?></small></td>
                                        <td><?php echo $lesson['duration']; ?> мин</td>
                                        <td>
                                            <?php if (!empty($lesson['topic_names'])): ?>
                                                <small><?php echo htmlspecialchars(substr($lesson['topic_names'], 0, 50)) . (strlen($lesson['topic_names']) > 50 ? '...' : ''); ?></small>
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
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end"><?php echo $lesson['cost'] ? number_format($lesson['cost'], 0, ',', ' ') : '0'; ?> ₽</td>
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
                        <div class="mt-3 text-center">
                            <a href="lessons.php?diary_id=<?php echo $diaryId; ?>" class="btn btn-outline-primary">Все занятия</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // График успеваемости
    new Chart(document.getElementById('progressChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($monthLabels); ?>,
            datasets: [
                {
                    label: 'Средняя оценка за занятие',
                    data: <?php echo json_encode($monthGrades); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Средняя оценка за ДЗ',
                    data: <?php echo json_encode($monthHomework); ?>,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: { beginAtZero: true, max: 5, ticks: { stepSize: 1 } }
            }
        }
    });
    
    // График распределения оценок
    new Chart(document.getElementById('gradesChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($gradeLabels); ?>,
            datasets: [{
                label: 'Количество',
                data: <?php echo json_encode($gradeCounts); ?>,
                backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#fd7e14', '#dc3545'],
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
    
    function copyPublicLink() {
        const link = "<?php 
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'];
            $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
            $basePath = rtrim($scriptPath, '/');
            echo $protocol . $host . $basePath . '/public_diary.php?token=' . $diary['public_link']; 
        ?>";
        
        navigator.clipboard.writeText(link).then(() => {
            const toast = document.createElement('div');
            toast.className = 'position-fixed top-0 end-0 m-3 p-3 bg-success text-white rounded shadow';
            toast.style.zIndex = '9999';
            toast.innerHTML = '<i class="bi bi-check-circle"></i> Ссылка скопирована';
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 2000);
        });
    }
    
    // Инициализация тултипов
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    </script>
</body>
</html>