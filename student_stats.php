<?php
require_once 'config.php';
requireAuth();

$currentUser = getCurrentUser($pdo);
$userId = $currentUser['id'];

$studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$period = $_GET['period'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-1 year'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$activeTab = $_GET['tab'] ?? 'charts';

// Получение списка учеников
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

// Получение всех занятий для статистики
$lessonsData = [];
$topicsData = [];
$progressStats = [];
$gradeDistribution = [];
$weekdayStats = [];
$financialStats = [];
$topicsByDate = [];

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

    // Получаем все занятия с темами
    $stmt = $pdo->prepare("
        SELECT 
            l.id,
            l.lesson_date,
            l.start_time,
            l.duration,
            l.cost,
            l.topics_manual,
            l.homework_manual,
            l.comment,
            l.grade_lesson,
            l.grade_homework,
            l.homework_comment,
            l.is_completed,
            l.is_cancelled,
            l.is_paid,
            d.name as diary_name,
            GROUP_CONCAT(DISTINCT CONCAT(t.id, '|', t.name, '|', COALESCE(c.name, 'Без категории'), '|', COALESCE(c.color, '#808080')) SEPARATOR '||') as topics_with_categories
        FROM lessons l
        JOIN diaries d ON l.diary_id = d.id
        LEFT JOIN lesson_topics lt ON l.id = lt.lesson_id
        LEFT JOIN topics t ON lt.topic_id = t.id
        LEFT JOIN categories c ON t.category_id = c.id
        WHERE d.student_id = ? AND l.is_completed = 1
        $dateFilter
        GROUP BY l.id
        ORDER BY l.lesson_date DESC, l.start_time DESC
    ");
    $stmt->execute($params);
    $lessonsData = $stmt->fetchAll();
    // Массив для хранения ID занятий по датам
    $lessonIdsByDate = [];

    foreach ($lessonsData as $lesson) {
        $date = $lesson['lesson_date'];
        $lessonIdsByDate[$date] = $lesson['id'];
    }
    // Функция для парсинга тем
    function parseTopicsWithCategories($topicsString)
    {
        $result = [];
        if (empty($topicsString))
            return $result;

        $topics = explode('||', $topicsString);
        foreach ($topics as $topic) {
            if (empty($topic))
                continue;
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

    // Собираем темы по датам для календаря
    $allDates = [];
    $allTopics = [];

    foreach ($lessonsData as $lesson) {
        $date = $lesson['lesson_date'];
        $allDates[] = $date;

        $topics = parseTopicsWithCategories($lesson['topics_with_categories']);
        foreach ($topics as $topic) {
            $topicKey = $topic['category'] . '|' . $topic['name'];
            if (!isset($allTopics[$topicKey])) {
                $allTopics[$topicKey] = [
                    'category' => $topic['category'],
                    'name' => $topic['name'],
                    'color' => $topic['color']
                ];
            }
            $topicsByDate[$date][$topicKey] = true;
        }
    }

    // Сортируем даты
    $allDates = array_unique($allDates);
    sort($allDates);

    // Сортируем темы по категориям и названиям
    uasort($allTopics, function ($a, $b) {
        if ($a['category'] != $b['category']) {
            return strcmp($a['category'], $b['category']);
        }
        return strcmp($a['name'], $b['name']);
    });

    // Статистика по темам
    $stmt = $pdo->prepare("
        SELECT 
            t.id,
            t.name as topic_name,
            COALESCE(c.name, 'Без категории') as category_name,
            COALESCE(c.color, '#808080') as category_color,
            COUNT(*) as usage_count,
            MAX(l.lesson_date) as last_used
        FROM lessons l
        JOIN lesson_topics lt ON l.id = lt.lesson_id
        JOIN topics t ON lt.topic_id = t.id
        LEFT JOIN categories c ON t.category_id = c.id
        WHERE l.diary_id IN (SELECT id FROM diaries WHERE student_id = ?) AND l.is_completed = 1
        $dateFilter
        GROUP BY t.id, t.name, c.name, c.color
        ORDER BY usage_count DESC, t.name
    ");
    $params2 = [$studentId];
    if ($period !== 'all' && empty($dateFrom)) {
        $params2 = [$studentId];
    } elseif (!empty($dateFrom) && !empty($dateTo)) {
        $params2 = [$studentId, $dateFrom, $dateTo];
    }
    $stmt->execute($params2);
    $topicsData = $stmt->fetchAll();

    // Динамика успеваемости
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(l.lesson_date, '%Y-%m') as month,
            COUNT(*) as lessons_count,
            AVG(l.grade_lesson) as avg_grade,
            AVG(l.grade_homework) as avg_homework
        FROM lessons l
        JOIN diaries d ON l.diary_id = d.id
        WHERE d.student_id = ? AND l.is_completed = 1
        $dateFilter
        GROUP BY month
        ORDER BY month DESC
        LIMIT 12
    ");
    $stmt->execute($params);
    $progressStats = $stmt->fetchAll();

    // Распределение оценок
    $stmt = $pdo->prepare("
        SELECT 
            l.grade_lesson,
            COUNT(*) as count
        FROM lessons l
        JOIN diaries d ON l.diary_id = d.id
        WHERE d.student_id = ? AND l.grade_lesson IS NOT NULL AND l.is_completed = 1
        $dateFilter
        GROUP BY l.grade_lesson
        ORDER BY l.grade_lesson
    ");
    $stmt->execute($params);
    $gradeDistribution = $stmt->fetchAll();

    // Занятия по дням недели
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
        WHERE d.student_id = ? AND l.is_completed = 1
        $dateFilter
        GROUP BY day_of_week
        ORDER BY day_of_week
    ");
    $stmt->execute($params);
    $weekdayStats = $stmt->fetchAll();

    // Финансовая динамика
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(l.lesson_date, '%Y-%m') as month,
            SUM(l.cost) as total_cost,
            SUM(CASE WHEN l.is_paid = 1 THEN l.cost ELSE 0 END) as paid_cost
        FROM lessons l
        JOIN diaries d ON l.diary_id = d.id
        WHERE d.student_id = ? AND l.is_completed = 1
        $dateFilter
        GROUP BY month
        ORDER BY month DESC
        LIMIT 12
    ");
    $stmt->execute($params);
    $financialStats = $stmt->fetchAll();

    // Формирование данных для графиков
    $monthLabels = [];
    $monthGrades = [];
    $monthHomework = [];
    $monthCosts = [];
    $monthPaid = [];

    foreach (array_reverse($progressStats) as $stat) {
        $monthLabels[] = date('M Y', strtotime($stat['month'] . '-01'));
        $monthGrades[] = round($stat['avg_grade'] ?? 0, 1);
        $monthHomework[] = round($stat['avg_homework'] ?? 0, 1);
    }

    foreach (array_reverse($financialStats) as $stat) {
        $monthCosts[] = $stat['total_cost'] ?? 0;
        $monthPaid[] = $stat['paid_cost'] ?? 0;
    }

    $gradeLabels = [];
    $gradeCounts = [];
    foreach ($gradeDistribution as $grade) {
        $gradeLabels[] = 'Оценка ' . $grade['grade_lesson'];
        $gradeCounts[] = $grade['count'];
    }

    $weekdayLabels = array_column($weekdayStats, 'day_name');
    $weekdayCounts = array_column($weekdayStats, 'lessons_count');
}

// Формирование данных для последних занятий
$recentLessons = array_slice($lessonsData, 0, 10);
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Статистика ученика - Дневник репетитора</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
            line-height: 1.2;
        }

        .filter-panel {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }

        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .nav-tabs .nav-link {
            color: #495057;
            border: none;
            padding: 12px 20px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .nav-tabs .nav-link:hover {
            color: #667eea;
            background: transparent;
        }

        .nav-tabs .nav-link.active {
            color: #667eea;
            border-bottom: 3px solid #667eea;
            background: transparent;
        }

        .topic-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 8px;
            border-left: 4px solid;
            transition: all 0.2s;
        }

        .topic-item:hover {
            transform: translateX(5px);
            background: #e9ecef;
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

        .grade-5 {
            background: #28a745;
            color: white;
        }

        .grade-4 {
            background: #17a2b8;
            color: white;
        }

        .grade-3 {
            background: #ffc107;
            color: #333;
        }

        .grade-2 {
            background: #fd7e14;
            color: white;
        }

        .grade-1 {
            background: #dc3545;
            color: white;
        }

        .topic-check {
            color: #28a745;
            font-size: 1.2rem;
        }

        .topic-check.empty {
            color: #dee2e6;
        }

        .lesson-tooltip {
            display: none;
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            max-width: 300px;
            pointer-events: none;
        }

        .lesson-row:hover .lesson-tooltip {
            display: block;
        }

        .lesson-row {
            position: relative;
            cursor: pointer;
        }

        .topic-badge {
            background: #e9ecef;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 0.75rem;
            display: inline-block;
            margin-right: 4px;
            margin-bottom: 4px;
        }

        /* Объединенные стили для календаря */
        .calendar-container {
            max-height: 1000px;
            overflow-x: auto;
            overflow-y: auto;
            border-radius: 12px;
            border: 1px solid #e9ecef;
            position: relative;
            /* Для корректной работы sticky */
        }

        .calendar-table {
            background: white;
            font-size: 0.85rem;
            min-width: 100%;
            border-collapse: collapse;
            /* Важно для sticky */
        }

        .calendar-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 8px;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 20;
            /* Выше, чем у фиксированных колонок */
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .calendar-table td {
            padding: 8px 6px;
            vertical-align: middle;
            border: 1px solid #e9ecef;
        }

        .calendar-table .fixed-column {
            position: sticky;
            left: 0;
            background: white;
            z-index: 15;
            /* Между th (20) и обычными ячейками (1) */
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
            font-weight: 500;
        }

        /* Обновляем фон фиксированной колонки при наведении на строку */
        .topic-row {
            transition: background 0.2s;
        }

        .topic-row:hover {
            background: #f8f9fa;
        }

        .topic-row:hover .fixed-column {
            background: #f8f9fa;
        }

        .topic-badge-calendar {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            background: #f8f9fa;
            color: #333;
            border-left: 4px solid;
            max-width: 250px;
        }

        .topic-category {
            font-size: 0.7rem;
            color: #666;
            margin-top: 2px;
        }

        .check-icon {
            font-size: 1.2rem;
            display: inline-block;
            width: 24px;
            height: 24px;
            line-height: 24px;
            text-align: center;
            border-radius: 50%;
        }

        .check-icon.passed {
            background: #28a745;
            color: white;
            box-shadow: 0 2px 5px rgba(40, 167, 69, 0.3);
        }

        .check-icon.not-passed {
            background: #e9ecef;
            color: #adb5bd;
        }

        /* Добавляем индикатор скролла */
        .calendar-container::-webkit-scrollbar {
            height: 8px;
            width: 8px;
        }

        .calendar-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .calendar-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        .calendar-container::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Адаптивность */
        @media (max-width: 768px) {

            .calendar-table th,
            .calendar-table td {
                padding: 6px 4px;
                font-size: 0.75rem;
            }

            .topic-badge-calendar {
                padding: 4px 8px;
                font-size: 0.7rem;
            }

            .check-icon {
                font-size: 1rem;
                width: 20px;
                height: 20px;
                line-height: 20px;
            }

            /* Уменьшаем отступы для мобильных */
            .student-header {
                padding: 20px;
            }

            .stat-card {
                padding: 15px;
            }

            .filter-panel {
                padding: 15px;
            }
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
                <input type="hidden" name="tab" value="<?php echo $activeTab; ?>">
                <div class="col-md-4">
                    <label class="form-label">Выберите ученика</label>
                    <select name="student_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">Выберите ученика</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo $studentId == $s['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['last_name'] . ' ' . $s['first_name'] . ' ' . ($s['middle_name'] ?? '')); ?>
                                <?php if ($s['class']): ?>(<?php echo htmlspecialchars($s['class']); ?>
                                    класс)<?php endif; ?>
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
                    <input type="date" name="date_from" class="form-control" value="<?php echo $dateFrom; ?>"
                        onchange="this.form.submit()">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Дата по</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo $dateTo; ?>"
                        onchange="this.form.submit()">
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
                        <h2><?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? '')); ?>
                        </h2>
                        <?php if ($student['class']): ?>
                            <p class="mb-2"><i class="bi bi-backpack"></i> <?php echo htmlspecialchars($student['class']); ?>
                                класс</p>
                        <?php endif; ?>
                        <?php if ($student['goals']): ?>
                            <p><i class="bi bi-bullseye"></i> <?php echo nl2br(htmlspecialchars($student['goals'])); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 text-end">
                        <p><i class="bi bi-calendar"></i> Начало:
                            <?php echo $student['start_date'] ? date('d.m.Y', strtotime($student['start_date'])) : 'не указано'; ?>
                        </p>
                        <p><i class="bi bi-telephone"></i>
                            <?php echo htmlspecialchars($student['phone'] ?? 'телефон не указан'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Вкладки -->
            <ul class="nav nav-tabs mb-4" id="statsTabs">
                <li class="nav-item">
                    <a class="nav-link <?php echo $activeTab == 'charts' ? 'active' : ''; ?>"
                        href="?student_id=<?php echo $studentId; ?>&period=<?php echo $period; ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>&tab=charts">
                        <i class="bi bi-graph-up"></i> Графики
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activeTab == 'topics' ? 'active' : ''; ?>"
                        href="?student_id=<?php echo $studentId; ?>&period=<?php echo $period; ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>&tab=topics">
                        <i class="bi bi-book"></i> Изучаемые темы
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activeTab == 'calendar' ? 'active' : ''; ?>"
                        href="?student_id=<?php echo $studentId; ?>&period=<?php echo $period; ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>&tab=calendar">
                        <i class="bi bi-calendar-week"></i> Пройденные темы по датам
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activeTab == 'lessons' ? 'active' : ''; ?>"
                        href="?student_id=<?php echo $studentId; ?>&period=<?php echo $period; ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>&tab=lessons">
                        <i class="bi bi-clock-history"></i> Последние занятия
                    </a>
                </li>
            </ul>

            <!-- Вкладка: Графики -->
            <?php if ($activeTab == 'charts'): ?>
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
            <?php endif; ?>

            <!-- Вкладка: Изучаемые темы -->
            <?php if ($activeTab == 'topics'): ?>
                <div class="stat-card">
                    <h5 class="mb-3"><i class="bi bi-book"></i> Изучаемые темы</h5>
                    <?php if (empty($topicsData)): ?>
                        <p class="text-muted text-center py-3">Нет данных по темам за выбранный период</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Тема</th>
                                        <th>Категория</th>
                                        <th class="text-center">Кол-во занятий</th>
                                        <th>Последнее использование</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topicsData as $topic): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($topic['topic_name']); ?></td>
                                            <td>
                                                <span class="badge"
                                                    style="background: <?php echo $topic['category_color'] ?? '#808080'; ?>; color: white;">
                                                    <?php echo htmlspecialchars($topic['category_name']); ?>
                                                </span>
                                            </td>
                                            <td class="text-center"><?php echo $topic['usage_count']; ?></td>
                                            <td><?php echo date('d.m.Y', strtotime($topic['last_used'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Вкладка: Пройденные темы по датам -->
            <?php if ($activeTab == 'calendar'): ?>
                <div class="stat-card">
                    <h5 class="mb-3"><i class="bi bi-calendar-week"></i> Пройденные темы по датам</h5>
                    <?php if (empty($allDates) || empty($allTopics)): ?>
                        <p class="text-muted text-center py-3">Нет данных по темам за выбранный период</p>
                    <?php else: ?>
                        <div class="calendar-container">
                            <table class="table table-bordered calendar-table">
                                <thead>
                                    <tr>
                                        <th class="fixed-column" style="min-width: 250px;">Тема / Дата</th>
                                        <?php foreach ($allDates as $date): ?>
                                            <th class="text-center" style="min-width: 70px;">
                                                <?php echo date('d.m', strtotime($date)); ?>
                                                <br>
                                                <small class="opacity-75">
                                                    <?php echo date('D', strtotime($date)); ?>
                                                </small>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $currentCategory = '';
                                    foreach ($allTopics as $topicKey => $topic):
                                        $showCategoryHeader = ($topic['category'] != $currentCategory);
                                        $currentCategory = $topic['category'];
                                        ?>
                                        <?php if ($showCategoryHeader): ?>
                                            <tr class="category-separator">
                                                <td colspan="<?php echo count($allDates) + 1; ?>" class="bg-light fw-bold">
                                                    <i class="bi bi-folder-fill me-2" style="color: <?php echo $topic['color']; ?>;"></i>
                                                    <?php echo htmlspecialchars($topic['category']); ?>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                        <tr class="topic-row">
                                            <td class="fixed-column">
                                                <div class="d-flex flex-column">
                                                    <span class="topic-badge-calendar"
                                                        style="border-left-color: <?php echo $topic['color']; ?>; background: <?php echo $topic['color']; ?>10;">
                                                        <i class="bi bi-dot"
                                                            style="color: <?php echo $topic['color']; ?>; font-size: 1.2rem;"></i>
                                                        <?php echo htmlspecialchars($topic['name']); ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <?php foreach ($allDates as $date): ?>
                                                <td class="text-center">
                                                    <?php if (isset($topicsByDate[$date][$topicKey])): ?>
                                                        <span class="check-icon passed"
                                                            title="Тема изучена <?php echo date('d.m.Y', strtotime($date)); ?>">
                                                            <i class="bi bi-check-lg"></i>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="check-icon not-passed" title="Тема не изучалась">
                                                            <i class="bi bi-dash-lg"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3 d-flex gap-4 align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <span class="check-icon passed"><i class="bi bi-check-lg"></i></span>
                                <span class="text-muted">тема изучалась</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="check-icon not-passed"><i class="bi bi-dash-lg"></i></span>
                                <span class="text-muted">тема не изучалась</span>
                            </div>
                            <div class="ms-auto">
                                <span class="badge bg-light text-dark">
                                    <i class="bi bi-calendar-range"></i> Всего:
                                    <?php echo count($allDates); ?> занятий
                                </span>
                                <span class="badge bg-light text-dark ms-2">
                                    <i class="bi bi-book"></i> Тем:
                                    <?php echo count($allTopics); ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Вкладка: Последние занятия -->
            <?php if ($activeTab == 'lessons'): ?>
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
                                        <th>Темы</th>
                                        <th class="text-center">Оценка за занятие</th>
                                        <th class="text-center">Оценка за ДЗ</th>
                                        <th class="text-end">Стоимость</th>
                                        <th class="text-center">Статус</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentLessons as $lesson):
                                        $topics = parseTopicsWithCategories($lesson['topics_with_categories']);
                                        ?>
                                        <tr class="lesson-row">
                                            <td><?php echo date('d.m.Y', strtotime($lesson['lesson_date'])); ?></td>
                                            <td><?php echo date('H:i', strtotime($lesson['start_time'])); ?></td>
                                            <td>
                                                <?php foreach (array_slice($topics, 0, 3) as $topic): ?>
                                                    <span class="topic-badge" style="border-left-color: <?php echo $topic['color']; ?>;">
                                                        <?php echo htmlspecialchars($topic['name']); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                                <?php if (count($topics) > 3): ?>
                                                    <span class="topic-badge">+<?php echo count($topics) - 3; ?></span>
                                                <?php endif; ?>

                                                <!-- Тултип с полной информацией -->
                                                <div class="lesson-tooltip">
                                                    <strong>Темы:</strong>
                                                    <?php foreach ($topics as $topic): ?>
                                                        <div>• <?php echo htmlspecialchars($topic['name']); ?></div>
                                                    <?php endforeach; ?>

                                                    <?php if (!empty($lesson['comment'])): ?>
                                                        <hr class="my-1">
                                                        <strong>Комментарий к занятию:</strong>
                                                        <div><?php echo nl2br(htmlspecialchars($lesson['comment'])); ?></div>
                                                    <?php endif; ?>

                                                    <?php if (!empty($lesson['homework_comment'])): ?>
                                                        <hr class="my-1">
                                                        <strong>Комментарий к ДЗ:</strong>
                                                        <div><?php echo nl2br(htmlspecialchars($lesson['homework_comment'])); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($lesson['grade_lesson'] !== null && $lesson['grade_lesson'] > 0): ?>
                                                    <span class="grade-badge grade-<?php echo $lesson['grade_lesson']; ?>">
                                                        <?php echo $lesson['grade_lesson']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($lesson['grade_homework'] !== null && $lesson['grade_homework'] > 0): ?>
                                                    <span class="grade-badge grade-<?php echo $lesson['grade_homework']; ?>">
                                                        <?php echo $lesson['grade_homework']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php echo $lesson['cost'] ? number_format($lesson['cost'], 0, ',', ' ') : '0'; ?> ₽
                                            </td>
                                            <td class="text-center">
                                                <?php if ($lesson['is_paid']): ?>
                                                    <span class="badge bg-success">Оплачено</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Не оплачено</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

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
        <?php if ($activeTab == 'charts'): ?>
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
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 5,
                            ticks: { stepSize: 1 }
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
                        backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#fd7e14', '#dc3545', '#6c757d']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'bottom' } }
                }
            });

            // График по дням недели
            new Chart(document.getElementById('weekdayChart'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($weekdayLabels); ?>,
                    datasets: [{
                        label: 'Количество занятий',
                        data: <?php echo json_encode($weekdayCounts); ?>,
                        backgroundColor: '#667eea',
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
                }
            });

            // Финансовый график
            new Chart(document.getElementById('financeChart'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($monthLabels); ?>,
                    datasets: [
                        {
                            label: 'Общая стоимость',
                            data: <?php echo json_encode($monthCosts); ?>,
                            backgroundColor: '#ffc107',
                            borderRadius: 5
                        },
                        {
                            label: 'Оплачено',
                            data: <?php echo json_encode($monthPaid); ?>,
                            backgroundColor: '#28a745',
                            borderRadius: 5
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            ticks: {
                                callback: function (value) { return value.toLocaleString() + ' ₽'; }
                            }
                        }
                    }
                }
            });
        <?php endif; ?>
    </script>
</body>

</html>