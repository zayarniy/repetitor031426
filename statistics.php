<?php
require_once 'config.php';
requireAuth();

$currentUser = getCurrentUser($pdo);
$userId = $currentUser['id'];
$isAdmin = isAdmin();

// Получение параметров фильтрации
$period = $_GET['period'] ?? 'month';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$filterClass = $_GET['filter_class'] ?? '';
$filterStatus = $_GET['filter_status'] ?? 'all';
$userId = $currentUser['id'];

// Для администратора - выбор пользователя
$selectedUserId = $_GET['user_id'] ?? ($isAdmin ? 0 : $userId);

// Получение списка пользователей для администратора
$users = [];
if ($isAdmin) {
    $stmt = $pdo->query("SELECT id, username, first_name, last_name FROM users WHERE is_active = 1 ORDER BY last_name, first_name");
    $users = $stmt->fetchAll();
}

// Получение классов для фильтра
$stmt = $pdo->prepare("SELECT DISTINCT class FROM students WHERE user_id = ? AND class IS NOT NULL AND class != '' ORDER BY class");
$stmt->execute([$selectedUserId ?: $userId]);
$classes = $stmt->fetchAll();

// Основная статистика
$stats = [];

// 1. Общая статистика по занятиям
$query = "
    SELECT 
        COUNT(*) as total_lessons,
        SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed_lessons,
        SUM(CASE WHEN is_cancelled = 1 THEN 1 ELSE 0 END) as cancelled_lessons,
        SUM(CASE WHEN is_paid = 1 THEN cost ELSE 0 END) as paid_sum,
        SUM(cost) as total_sum,
        AVG(CASE WHEN grade_lesson IS NOT NULL THEN grade_lesson END) as avg_grade,
        AVG(CASE WHEN grade_homework IS NOT NULL THEN grade_homework END) as avg_homework_grade,
        SUM(duration) as total_minutes
    FROM lessons l
    JOIN diaries d ON l.diary_id = d.id
    WHERE d.user_id = ? AND l.lesson_date BETWEEN ? AND ?
";

$stmt = $pdo->prepare($query);
$stmt->execute([$selectedUserId ?: $userId, $dateFrom, $dateTo]);
$stats['general'] = $stmt->fetch();

// 2. Статистика по ученикам
$query = "
    SELECT 
        s.id,
        s.last_name,
        s.first_name,
        s.middle_name,
        s.class,
        COUNT(l.id) as lessons_count,
        SUM(CASE WHEN l.is_completed = 1 THEN 1 ELSE 0 END) as completed_count,
        SUM(CASE WHEN l.is_cancelled = 1 THEN 1 ELSE 0 END) as cancelled_count,
        SUM(l.cost) as total_cost,
        SUM(CASE WHEN l.is_paid = 1 THEN l.cost ELSE 0 END) as paid_cost,
        AVG(l.grade_lesson) as avg_grade,
        AVG(l.grade_homework) as avg_homework
    FROM students s
    LEFT JOIN diaries d ON d.student_id = s.id AND d.user_id = s.user_id
    LEFT JOIN lessons l ON l.diary_id = d.id AND l.lesson_date BETWEEN ? AND ?
    WHERE s.user_id = ?
";

$params = [$dateFrom, $dateTo, $selectedUserId ?: $userId];

if (!empty($filterClass)) {
    $query .= " AND s.class = ?";
    $params[] = $filterClass;
}

if ($filterStatus === 'active') {
    $query .= " AND s.is_active = 1";
} elseif ($filterStatus === 'inactive') {
    $query .= " AND s.is_active = 0";
}

$query .= " GROUP BY s.id ORDER BY lessons_count DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$stats['students'] = $stmt->fetchAll();

// 3. Статистика по дням/неделям/месяцам
if ($period === 'day') {
    $groupFormat = "%Y-%m-%d";
    $selectFormat = "DATE(l.lesson_date) as period";
} elseif ($period === 'week') {
    $groupFormat = "%Y-%u";
    $selectFormat = "CONCAT(YEAR(l.lesson_date), '-W', WEEK(l.lesson_date)) as period";
} else {
    $groupFormat = "%Y-%m";
    $selectFormat = "DATE_FORMAT(l.lesson_date, '%Y-%m') as period";
}

$query = "
    SELECT 
        $selectFormat,
        COUNT(*) as lessons_count,
        SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed_count,
        SUM(CASE WHEN is_cancelled = 1 THEN 1 ELSE 0 END) as cancelled_count,
        SUM(cost) as total_cost,
        SUM(CASE WHEN is_paid = 1 THEN cost ELSE 0 END) as paid_cost,
        SUM(duration) as total_minutes,
        AVG(grade_lesson) as avg_grade
    FROM lessons l
    JOIN diaries d ON l.diary_id = d.id
    WHERE d.user_id = ? AND l.lesson_date BETWEEN ? AND ?
    GROUP BY period
    ORDER BY l.lesson_date
";

$stmt = $pdo->prepare($query);
$stmt->execute([$selectedUserId ?: $userId, $dateFrom, $dateTo]);
$stats['timeline'] = $stmt->fetchAll();

// 4. Статистика по темам
$query = "
    SELECT 
        t.id,
        t.name,
        t.description,
        c.name as category_name,
        c.color as category_color,
        COUNT(DISTINCT lt.lesson_id) as usage_count,
        COUNT(DISTINCT l.id) as lessons_count,
        AVG(l.grade_lesson) as avg_grade
    FROM topics t
    LEFT JOIN lesson_topics lt ON lt.topic_id = t.id
    LEFT JOIN lessons l ON lt.lesson_id = l.id AND l.lesson_date BETWEEN ? AND ?
    LEFT JOIN diaries d ON l.diary_id = d.id AND d.user_id = t.user_id
    LEFT JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ?
    GROUP BY t.id
    HAVING usage_count > 0
    ORDER BY usage_count DESC
    LIMIT 20
";

$stmt = $pdo->prepare($query);
$stmt->execute([$dateFrom, $dateTo, $selectedUserId ?: $userId]);
$stats['topics'] = $stmt->fetchAll();

// 5. Статистика по ресурсам
$query = "
    SELECT 
        r.id,
        r.description,
        r.url,
        r.type,
        c.name as category_name,
        COUNT(DISTINCT lr.lesson_id) as usage_count,
        COUNT(DISTINCT l.id) as lessons_count
    FROM resources r
    LEFT JOIN lesson_resources lr ON lr.resource_id = r.id
    LEFT JOIN lessons l ON lr.lesson_id = l.id AND l.lesson_date BETWEEN ? AND ?
    LEFT JOIN diaries d ON l.diary_id = d.id AND d.user_id = r.user_id
    LEFT JOIN categories c ON r.category_id = c.id
    WHERE r.user_id = ?
    GROUP BY r.id
    HAVING usage_count > 0
    ORDER BY usage_count DESC
    LIMIT 20
";

$stmt = $pdo->prepare($query);
$stmt->execute([$dateFrom, $dateTo, $selectedUserId ?: $userId]);
$stats['resources'] = $stmt->fetchAll();

// 6. Финансовая статистика
$query = "
    SELECT 
        DATE_FORMAT(l.lesson_date, '%Y-%m') as month,
        COUNT(*) as lessons_count,
        SUM(cost) as total_cost,
        SUM(CASE WHEN is_paid = 1 THEN cost ELSE 0 END) as paid_cost,
        AVG(cost) as avg_cost,
        SUM(duration) as total_minutes,
        COUNT(DISTINCT s.id) as students_count
    FROM lessons l
    JOIN diaries d ON l.diary_id = d.id
    JOIN students s ON d.student_id = s.id
    WHERE d.user_id = ? AND l.lesson_date BETWEEN ? AND ?
    GROUP BY month
    ORDER BY month
";

$stmt = $pdo->prepare($query);
$stmt->execute([$selectedUserId ?: $userId, $dateFrom, $dateTo]);
$stats['financial'] = $stmt->fetchAll();

// 7. Статистика по оценкам
$query = "
    SELECT 
        COUNT(*) as total_lessons,
        SUM(CASE WHEN grade_lesson = 5 THEN 1 ELSE 0 END) as grade_5,
        SUM(CASE WHEN grade_lesson = 4 THEN 1 ELSE 0 END) as grade_4,
        SUM(CASE WHEN grade_lesson = 3 THEN 1 ELSE 0 END) as grade_3,
        SUM(CASE WHEN grade_lesson = 2 THEN 1 ELSE 0 END) as grade_2,
        SUM(CASE WHEN grade_lesson = 1 THEN 1 ELSE 0 END) as grade_1,
        SUM(CASE WHEN grade_lesson = 0 THEN 1 ELSE 0 END) as grade_0,
        AVG(grade_lesson) as avg_grade,
        AVG(grade_homework) as avg_homework
    FROM lessons l
    JOIN diaries d ON l.diary_id = d.id
    WHERE d.user_id = ? AND l.lesson_date BETWEEN ? AND ? AND l.is_completed = 1
";

$stmt = $pdo->prepare($query);
$stmt->execute([$selectedUserId ?: $userId, $dateFrom, $dateTo]);
$stats['grades'] = $stmt->fetch();

// 8. Статистика по дням недели
$query = "
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
        AVG(l.grade_lesson) as avg_grade,
        SUM(l.duration) as total_minutes
    FROM lessons l
    JOIN diaries d ON l.diary_id = d.id
    WHERE d.user_id = ? AND l.lesson_date BETWEEN ? AND ?
    GROUP BY day_of_week
    ORDER BY day_of_week
";

$stmt = $pdo->prepare($query);
$stmt->execute([$selectedUserId ?: $userId, $dateFrom, $dateTo]);
$stats['weekdays'] = $stmt->fetchAll();

// 9. Статистика по времени дня
$query = "
    SELECT 
        HOUR(l.start_time) as hour,
        COUNT(*) as lessons_count,
        AVG(l.grade_lesson) as avg_grade
    FROM lessons l
    JOIN diaries d ON l.diary_id = d.id
    WHERE d.user_id = ? AND l.lesson_date BETWEEN ? AND ?
    GROUP BY hour
    ORDER BY hour
";

$stmt = $pdo->prepare($query);
$stmt->execute([$selectedUserId ?: $userId, $dateFrom, $dateTo]);
$stats['hours'] = $stmt->fetchAll();

// 10. Долги учеников
$query = "
    SELECT 
        s.id,
        s.last_name,
        s.first_name,
        s.middle_name,
        s.class,
        COUNT(l.id) as unpaid_lessons,
        SUM(l.cost) as total_debt
    FROM students s
    JOIN diaries d ON d.student_id = s.id AND d.user_id = s.user_id
    JOIN lessons l ON l.diary_id = d.id 
    WHERE s.user_id = ? 
        AND l.lesson_date <= ? 
        AND l.is_paid = 0 
        AND l.is_cancelled = 0
    GROUP BY s.id
    HAVING total_debt > 0
    ORDER BY total_debt DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute([$selectedUserId ?: $userId, $dateTo]);
$stats['debts'] = $stmt->fetchAll();

// Вычисление итогов
$totalLessons = $stats['general']['total_lessons'] ?? 0;
$completedLessons = $stats['general']['completed_lessons'] ?? 0;
$totalIncome = $stats['general']['paid_sum'] ?? 0;
$expectedIncome = $stats['general']['total_sum'] ?? 0;
$avgGrade = round($stats['general']['avg_grade'] ?? 0, 1);
$totalHours = round(($stats['general']['total_minutes'] ?? 0) / 60, 1);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Статистика - Дневник репетитора</title>
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            transition: transform 0.3s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .stats-card .stats-number {
            font-size: 2.5em;
            font-weight: bold;
            line-height: 1.2;
        }
        .stats-card .stats-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
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
        .table-stat {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .table-stat thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .table-stat th {
            font-weight: 500;
            border: none;
        }
        .table-stat td {
            vertical-align: middle;
        }
        .badge-grade {
            background: #28a745;
            color: white;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.85em;
        }
        .badge-debt {
            background: #dc3545;
            color: white;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.85em;
        }
        .section-title {
            margin: 30px 0 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-title i {
            color: #667eea;
            font-size: 1.5em;
        }
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .quick-stat-item {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .quick-stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }
        .quick-stat-label {
            color: #666;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>
    
    <div class="container-fluid py-4">
        <h2 class="mb-4"><i class="bi bi-graph-up"></i> Статистика и аналитика</h2>
        
        <!-- Фильтры -->
        <div class="filter-panel">
            <form method="GET" action="" class="row g-3">
                <?php if ($isAdmin): ?>
                <div class="col-md-2">
                    <label class="form-label">Пользователь</label>
                    <select name="user_id" class="form-select">
                        <option value="0">Все пользователи</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $selectedUserId == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['last_name'] . ' ' . $user['first_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="col-md-2">
                    <label class="form-label">Период</label>
                    <select name="period" class="form-select">
                        <option value="day" <?php echo $period == 'day' ? 'selected' : ''; ?>>По дням</option>
                        <option value="week" <?php echo $period == 'week' ? 'selected' : ''; ?>>По неделям</option>
                        <option value="month" <?php echo $period == 'month' ? 'selected' : ''; ?>>По месяцам</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Дата с</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo $dateFrom; ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Дата по</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo $dateTo; ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Класс</label>
                    <select name="filter_class" class="form-select">
                        <option value="">Все классы</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo htmlspecialchars($class['class']); ?>" <?php echo $filterClass == $class['class'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Статус</label>
                    <select name="filter_status" class="form-select">
                        <option value="all" <?php echo $filterStatus == 'all' ? 'selected' : ''; ?>>Все ученики</option>
                        <option value="active" <?php echo $filterStatus == 'active' ? 'selected' : ''; ?>>Активные</option>
                        <option value="inactive" <?php echo $filterStatus == 'inactive' ? 'selected' : ''; ?>>Неактивные</option>
                    </select>
                </div>
                
                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-filter"></i> Применить фильтры
                    </button>
                    <a href="statistics.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise"></i> Сбросить
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Быстрая статистика -->
        <div class="quick-stats">
            <div class="quick-stat-item">
                <div class="quick-stat-value"><?php echo $totalLessons; ?></div>
                <div class="quick-stat-label">Всего занятий</div>
            </div>
            <div class="quick-stat-item">
                <div class="quick-stat-value"><?php echo $completedLessons; ?></div>
                <div class="quick-stat-label">Проведено</div>
            </div>
            <div class="quick-stat-item">
                <div class="quick-stat-value"><?php echo number_format($totalIncome, 0, ',', ' '); ?> ₽</div>
                <div class="quick-stat-label">Получено</div>
            </div>
            <div class="quick-stat-item">
                <div class="quick-stat-value"><?php echo number_format($expectedIncome - $totalIncome, 0, ',', ' '); ?> ₽</div>
                <div class="quick-stat-label">Долг</div>
            </div>
            <div class="quick-stat-item">
                <div class="quick-stat-value"><?php echo $avgGrade; ?></div>
                <div class="quick-stat-label">Ср. оценка</div>
            </div>
            <div class="quick-stat-item">
                <div class="quick-stat-value"><?php echo $totalHours; ?> ч</div>
                <div class="quick-stat-label">Часов</div>
            </div>
        </div>
        
        <!-- Графики -->
        <div class="row">
            <div class="col-md-8">
                <div class="chart-container">
                    <h5>Динамика занятий и дохода</h5>
                    <canvas id="timelineChart"></canvas>
                </div>
            </div>
            <div class="col-md-4">
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
                    <h5>Занятия по часам</h5>
                    <canvas id="hourChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Финансовая статистика -->
        <div class="section-title">
            <i class="bi bi-currency-ruble"></i>
            <h4>Финансовая статистика</h4>
        </div>
        
        <div class="table-stat">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Период</th>
                        <th class="text-center">Занятий</th>
                        <th class="text-center">Учеников</th>
                        <th class="text-end">Сумма</th>
                        <th class="text-end">Оплачено</th>
                        <th class="text-end">Средний чек</th>
                        <th class="text-center">Часов</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['financial'] as $row): ?>
                    <tr>
                        <td><?php echo $row['month']; ?></td>
                        <td class="text-center"><?php echo $row['lessons_count']; ?></td>
                        <td class="text-center"><?php echo $row['students_count']; ?></td>
                        <td class="text-end"><?php echo number_format($row['total_cost'], 0, ',', ' '); ?> ₽</td>
                        <td class="text-end"><?php echo number_format($row['paid_cost'], 0, ',', ' '); ?> ₽</td>
                        <td class="text-end"><?php echo number_format($row['avg_cost'], 0, ',', ' '); ?> ₽</td>
                        <td class="text-center"><?php echo round($row['total_minutes'] / 60, 1); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Статистика по ученикам -->
        <div class="section-title">
            <i class="bi bi-people"></i>
            <h4>Статистика по ученикам</h4>
        </div>
        
        <div class="table-stat">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Ученик</th>
                        <th>Класс</th>
                        <th class="text-center">Всего</th>
                        <th class="text-center">Проведено</th>
                        <th class="text-center">Отменено</th>
                        <th class="text-end">Сумма</th>
                        <th class="text-end">Оплачено</th>
                        <th class="text-center">Оценка</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['students'] as $student): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? '')); ?>
                        </td>
                        <td><?php echo htmlspecialchars($student['class'] ?? '-'); ?></td>
                        <td class="text-center"><?php echo $student['lessons_count']; ?></td>
                        <td class="text-center"><?php echo $student['completed_count']; ?></td>
                        <td class="text-center"><?php echo $student['cancelled_count']; ?></td>
                        <td class="text-end"><?php echo number_format($student['total_cost'], 0, ',', ' '); ?> ₽</td>
                        <td class="text-end"><?php echo number_format($student['paid_cost'], 0, ',', ' '); ?> ₽</td>
                        <td class="text-center">
                            <?php if ($student['avg_grade']): ?>
                                <span class="badge-grade"><?php echo round($student['avg_grade'], 1); ?></span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Долги учеников -->
        <?php if (!empty($stats['debts'])): ?>
        <div class="section-title">
            <i class="bi bi-exclamation-triangle text-danger"></i>
            <h4 class="text-danger">Задолженности</h4>
        </div>
        
        <div class="table-stat">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Ученик</th>
                        <th>Класс</th>
                        <th class="text-center">Неоплаченных занятий</th>
                        <th class="text-end">Сумма долга</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['debts'] as $debt): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($debt['last_name'] . ' ' . $debt['first_name'] . ' ' . ($debt['middle_name'] ?? '')); ?>
                        </td>
                        <td><?php echo htmlspecialchars($debt['class'] ?? '-'); ?></td>
                        <td class="text-center"><?php echo $debt['unpaid_lessons']; ?></td>
                        <td class="text-end">
                            <span class="badge-debt"><?php echo number_format($debt['total_debt'], 0, ',', ' '); ?> ₽</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Популярные темы -->
        <?php if (!empty($stats['topics'])): ?>
        <div class="section-title">
            <i class="bi bi-book"></i>
            <h4>Популярные темы</h4>
        </div>
        
        <div class="table-stat">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Тема</th>
                        <th>Категория</th>
                        <th class="text-center">Использований</th>
                        <th class="text-center">Занятий</th>
                        <th class="text-center">Ср. оценка</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['topics'] as $topic): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($topic['name']); ?></td>
                        <td>
                            <?php if ($topic['category_name']): ?>
                                <span class="badge" style="background: <?php echo $topic['category_color'] ?? '#808080'; ?>; color: white;">
                                    <?php echo htmlspecialchars($topic['category_name']); ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?php echo $topic['usage_count']; ?></td>
                        <td class="text-center"><?php echo $topic['lessons_count']; ?></td>
                        <td class="text-center">
                            <?php if ($topic['avg_grade']): ?>
                                <span class="badge-grade"><?php echo round($topic['avg_grade'], 1); ?></span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Популярные ресурсы -->
        <?php if (!empty($stats['resources'])): ?>
        <div class="section-title">
            <i class="bi bi-link"></i>
            <h4>Популярные ресурсы</h4>
        </div>
        
        <div class="table-stat">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Ресурс</th>
                        <th>Тип</th>
                        <th>Категория</th>
                        <th class="text-center">Использований</th>
                        <th class="text-center">Занятий</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['resources'] as $resource): ?>
                    <tr>
                        <td>
                            <a href="<?php echo htmlspecialchars($resource['url']); ?>" target="_blank" class="text-decoration-none">
                                <?php echo htmlspecialchars($resource['description'] ?: substr($resource['url'], 0, 50)); ?>
                                <i class="bi bi-box-arrow-up-right small"></i>
                            </a>
                        </td>
                        <td>
                            <?php 
                            $types = ['page' => 'Страница', 'document' => 'Документ', 'video' => 'Видео', 'audio' => 'Аудио', 'other' => 'Другое'];
                            echo $types[$resource['type']] ?? 'Другое';
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($resource['category_name'] ?? '-'); ?></td>
                        <td class="text-center"><?php echo $resource['usage_count']; ?></td>
                        <td class="text-center"><?php echo $resource['lessons_count']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
    // Данные для графиков
    const timelineData = <?php echo json_encode($stats['timeline']); ?>;
    const gradesData = <?php echo json_encode($stats['grades']); ?>;
    const weekdaysData = <?php echo json_encode($stats['weekdays']); ?>;
    const hoursData = <?php echo json_encode($stats['hours']); ?>;
    
    // График динамики
    new Chart(document.getElementById('timelineChart'), {
        type: 'line',
        data: {
            labels: timelineData.map(d => d.period),
            datasets: [{
                label: 'Занятия',
                data: timelineData.map(d => d.lessons_count),
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                yAxisID: 'y',
                tension: 0.4
            }, {
                label: 'Доход (тыс. ₽)',
                data: timelineData.map(d => (d.paid_cost / 1000).toFixed(1)),
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                yAxisID: 'y1',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Количество занятий'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Доход (тыс. ₽)'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                },
            }
        }
    });
    
    // График оценок
    if (gradesData) {
        new Chart(document.getElementById('gradesChart'), {
            type: 'doughnut',
            data: {
                labels: ['5', '4', '3', '2', '1', '0'],
                datasets: [{
                    data: [
                        gradesData.grade_5 || 0,
                        gradesData.grade_4 || 0,
                        gradesData.grade_3 || 0,
                        gradesData.grade_2 || 0,
                        gradesData.grade_1 || 0,
                        gradesData.grade_0 || 0
                    ],
                    backgroundColor: [
                        '#28a745',
                        '#17a2b8',
                        '#ffc107',
                        '#fd7e14',
                        '#dc3545',
                        '#6c757d'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
    }
    
    // График по дням недели
    if (weekdaysData.length > 0) {
        new Chart(document.getElementById('weekdayChart'), {
            type: 'bar',
            data: {
                labels: weekdaysData.map(d => d.day_name),
                datasets: [{
                    label: 'Количество занятий',
                    data: weekdaysData.map(d => d.lessons_count),
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
    }
    
    // График по часам
    if (hoursData.length > 0) {
        new Chart(document.getElementById('hourChart'), {
            type: 'line',
            data: {
                labels: hoursData.map(d => d.hour + ':00'),
                datasets: [{
                    label: 'Занятий',
                    data: hoursData.map(d => d.lessons_count),
                    borderColor: '#fd7e14',
                    backgroundColor: 'rgba(253, 126, 20, 0.1)',
                    fill: true,
                    tension: 0.4
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
    }
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>