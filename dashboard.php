<?php
require_once 'config.php';
requireAuth();

$currentUser = getCurrentUser($pdo);
$userId = $currentUser['id'];
// Проверка, что пользователь получен корректно
if (!$currentUser || $userId == 0) {
    // Если не удалось получить пользователя, выходим из системы
    session_destroy();
    header('Location: login.php');
    exit();
}
// Обработка навигации по неделям
$weekOffset = isset($_GET['week_offset']) ? intval($_GET['week_offset']) : 0;
$selectedDate = isset($_GET['week_date']) ? $_GET['week_date'] : '';


// Вычисляем даты начала и конца недели с учетом смещения
if (!empty($selectedDate)) {
    // Используем выбранную дату
    $weekStart = date('Y-m-d', strtotime('monday this week', strtotime($selectedDate)));
    $weekEnd = date('Y-m-d', strtotime('sunday this week', strtotime($selectedDate)));
    $weekOffset = 0; // Сбрасываем смещение при выборе конкретной даты
} elseif ($weekOffset != 0) {
    // Используем смещение в неделях
    $weekStart = date('Y-m-d', strtotime('monday this week ' . ($weekOffset > 0 ? '+' . $weekOffset : $weekOffset) . ' weeks'));
    $weekEnd = date('Y-m-d', strtotime('sunday this week ' . ($weekOffset > 0 ? '+' . $weekOffset : $weekOffset) . ' weeks'));
} else {
    // Текущая неделя
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $weekEnd = date('Y-m-d', strtotime('sunday this week'));
}

// Форматируем даты для отображения
$weekStartDisplay = date('d.m.Y', strtotime($weekStart));
$weekEndDisplay = date('d.m.Y', strtotime($weekEnd));

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

// Получение статистики для выбранной недели
// Активные ученики (всего, не зависит от недели)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE user_id = ? AND is_active = 1");
$stmt->execute([$userId]);
$activeStudents = $stmt->fetchColumn();

// Занятия на выбранную неделю
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

// Уроки сегодня (не зависит от выбранной недели)
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM lessons l
    JOIN students s ON l.student_id = s.id
    WHERE s.user_id = ? AND l.lesson_date = ? AND s.is_active = 1
");
$stmt->execute([$userId, $today]);
$todayLessons = $stmt->fetchColumn();

// Доход за выбранную неделю (возможный и полученный)
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

// Получение расписания на выбранную неделю с фильтрами
$query = "
    SELECT 
        l.*,
        l.diary_id,
        s.first_name as first_name,
        s.last_name as last_name,
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

// Подготавливаем и выполняем запрос
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

// Если есть фильтр по меткам, выполняем запрос с дополнительными параметрами
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

// Копирование занятия
// Копирование занятия
if (isset($_GET['copy']) && $lessonId) {
    try {
        $pdo->beginTransaction();
        
        // Получаем исходное занятие
        $stmt = $pdo->prepare("
            SELECT l.* 
            FROM lessons l
            JOIN diaries d ON l.diary_id = d.id
            WHERE l.id = ? AND d.user_id = ?
        ");
        $stmt->execute([$lessonId, $userId]);
        $sourceLesson = $stmt->fetch();
        
        if ($sourceLesson) {
               $originalDateTime = date('Y-m-d', strtotime($sourceLesson['start_time']));
    $newDate = date('Y-m-d', strtotime($originalDateTime . ' +7 days'));
            // Создаем копию занятия (дата через 7 дней)
            $newDate = date('Y-m-d', strtotime('+7 days'));
            
            $stmt = $pdo->prepare("
                INSERT INTO lessons (
                    diary_id, student_id, lesson_date, start_time, duration, cost,
                    topics_manual, homework_manual, comment,
                    link_url, link_comment,
                    grade_lesson, grade_comment, grade_homework, homework_comment,
                    is_cancelled, is_completed, is_paid
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $sourceLesson['diary_id'],
                $sourceLesson['student_id'],
                $newDate,
                $sourceLesson['start_time'],
                $sourceLesson['duration'],
                $sourceLesson['cost'],
                $sourceLesson['topics_manual'],
                $sourceLesson['homework_manual'],
                $sourceLesson['comment'],
                $sourceLesson['link_url'],
                $sourceLesson['link_comment'],
                null, // grade_lesson
                '',
                null, // grade_homework
                '',
                0, // is_cancelled
                0, // is_completed
                0  // is_paid
            ]);
            
            $newLessonId = $pdo->lastInsertId();
            
            // Копируем связи с темами
            $stmt = $pdo->prepare("SELECT topic_id FROM lesson_topics WHERE lesson_id = ?");
            $stmt->execute([$lessonId]);
            $topics = $stmt->fetchAll();
            
            if (!empty($topics)) {
                $insertStmt = $pdo->prepare("INSERT INTO lesson_topics (lesson_id, topic_id) VALUES (?, ?)");
                foreach ($topics as $topic) {
                    $insertStmt->execute([$newLessonId, $topic['topic_id']]);
                }
            }
            
            // Копируем связи с ресурсами
            $stmt = $pdo->prepare("SELECT resource_id, comment FROM lesson_resources WHERE lesson_id = ?");
            $stmt->execute([$lessonId]);
            $resources = $stmt->fetchAll();
            
            if (!empty($resources)) {
                $insertStmt = $pdo->prepare("INSERT INTO lesson_resources (lesson_id, resource_id, comment) VALUES (?, ?, ?)");
                foreach ($resources as $resource) {
                    $insertStmt->execute([$newLessonId, $resource['resource_id'], $resource['comment']]);
                }
            }
            
            $pdo->commit();
            
            // Перенаправляем на редактирование созданной копии
            header('Location: lessons.php?action=edit&id=' . $newLessonId . '&diary_id=' . $sourceLesson['diary_id'] . '&message=copied');
            exit();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Ошибка при копировании: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Дневник репетитора</title>
    <link rel="manifest" href="manifest.json">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    /* Ваши существующие стили */
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
        z-index: 1;
    }
    .lesson-item:hover {
        z-index: 1000;
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
        font-size: 1rem;
    }
    .lesson-item .lesson-student {
        font-weight: 600;
        font-size: 1rem;
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
        z-index: 9999;
        max-width: 300px;
        min-width: 200px;
        pointer-events: none;
        top: 100%;
        left: 0;
        margin-top: 5px;
    }
    .lesson-tooltip::before {
        content: '';
        position: absolute;
        top: -8px;
        left: 20px;
        width: 0;
        height: 0;
        border-left: 8px solid transparent;
        border-right: 8px solid transparent;
        border-bottom: 8px solid white;
        filter: drop-shadow(0 -2px 2px rgba(0,0,0,0.1));
    }
    .lesson-item:hover .lesson-tooltip {
        display: block;
    }
    .filter-panel {
        background: white;
        border-radius: 15px;
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
    
    /* Стили для навигации по неделям */
    .week-navigation {
        background: white;
        border-radius: 15px;
        padding: 15px 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .btn-group .btn-outline-primary {
        border-color: #dee2e6;
        color: #495057;
    }
    
    .btn-group .btn-outline-primary:hover {
        background: #f8f9fa;
        color: #667eea;
        border-color: #667eea;
    }
    
    .btn-group .btn-outline-primary.active {
        background: #667eea;
        color: white;
        border-color: #667eea;
    }
    
    .current-week-badge {
        background: #28a745;
        color: white;
        font-size: 0.75rem;
        padding: 2px 8px;
        border-radius: 12px;
        margin-left: 8px;
    }
    
    /* Стили для плашки оплачено */
    .paid-badge {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        box-shadow: 0 2px 5px rgba(40, 167, 69, 0.3);
        animation: pulse 2s infinite;
    }
    
    .paid-badge i {
        font-size: 0.8rem;
    }
    
    @keyframes pulse {
        0% {
            box-shadow: 0 2px 5px rgba(40, 167, 69, 0.3);
        }
        50% {
            box-shadow: 0 2px 10px rgba(40, 167, 69, 0.5);
        }
        100% {
            box-shadow: 0 2px 5px rgba(40, 167, 69, 0.3);
        }
    }
    
    .lesson-duration {
        font-size: 0.85rem;
        color: #6c757d;
    }
    
    .lesson-duration i {
        margin-right: 2px;
    }
    
    .lesson-categories {
        display: flex;
        flex-wrap: wrap;
        gap: 2px;
        margin-top: 4px;
    }
    
    .category-badge {
        font-size: 0.7rem;
        padding: 2px 8px;
        border-radius: 12px;
        background-color: #f0f0f0;
        border-left: 3px solid;
        display: inline-block;
        margin-right: 4px;
        margin-bottom: 2px;
        transition: all 0.2s;
    }
    
    .category-badge:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    /* Стили для кнопок действий */
    .lesson-actions {
        margin-left: 10px;
        min-width: 70px;
    }
    
    .lesson-actions .btn {
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        transition: all 0.2s;
        font-size: 0.8rem;
        padding: 0;
    }
    
    .lesson-actions .btn:hover {
        transform: scale(1.05);
    }
    
    .lesson-actions .btn-outline-primary:hover {
        background-color: #667eea;
        color: white;
        border-color: #667eea;
    }
    
    .lesson-actions .btn-outline-info:hover {
        background-color: #17a2b8;
        color: white;
        border-color: #17a2b8;
    }
    
    .lesson-actions .btn-outline-success:hover {
        background-color: #28a745;
        color: white;
        border-color: #28a745;
    }
    
    .lesson-actions .btn-outline-warning:hover {
        background-color: #ffc107;
        color: #333;
        border-color: #ffc107;
    }
    
    /* Для сеточного варианта */
    .lesson-actions .row {
        margin: 0 -2px;
    }
    
    .lesson-actions .col-6 {
        padding: 0 2px;
    }
    
    .lesson-actions .btn.w-100 {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    /* Адаптивность для мобильных */
    @media (max-width: 768px) {
        .week-navigation .d-flex {
            flex-direction: column;
            gap: 10px;
        }
        
        .btn-group {
            width: 100%;
        }
        
        .btn-group .btn {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .lesson-item .d-flex {
            flex-direction: column;
        }
        
        .lesson-actions {
            margin-left: 0;
            margin-top: 10px;
            width: 100%;
        }
        
        .lesson-actions .row {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }
        
        .lesson-actions .col-6 {
            flex: 0 0 calc(50% - 2px);
            max-width: calc(50% - 2px);
        }
        
        .lesson-actions .btn {
            font-size: 0.7rem;
            padding: 4px 0;
        }
        
        .lesson-tooltip {
            max-width: 250px;
            left: 50%;
            transform: translateX(-50%);
        }
        
        .lesson-tooltip::before {
            left: 50%;
            transform: translateX(-50%);
        }
    }

    /* Mobile First - Статистика Grid */
.stats-container {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 14px 8px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.stat-value {
    font-size: 1.5rem;
    font-weight: bold;
    color: #667eea;
    line-height: 1.2;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 0.75rem;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Доход - Grid */
.income-container {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
    margin-bottom: 20px;
}

.income-card {
    border-radius: 16px;
    padding: 16px 12px;
    text-align: center;
    color: white;
}

.income-card.potential {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
}

.income-card.paid {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
}

.income-card .stat-value {
    color: white;
    font-size: 1.6rem;
    margin-bottom: 4px;
}

.income-card .stat-label {
    color: rgba(255,255,255,0.9);
    font-size: 0.85rem;
}

/* Tablet (от 600px) */
@media (min-width: 600px) {
    .stats-container {
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
    }
    
    .stat-card {
        padding: 16px 10px;
    }
    
    .stat-value {
        font-size: 1.8rem;
    }
    
    .income-container {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
}

/* Desktop (от 992px) */
@media (min-width: 992px) {
    .stats-container {
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        padding: 20px;
    }
    
    .stat-value {
        font-size: 2rem;
    }
    
    .stat-label {
        font-size: 0.85rem;
    }
    
    .income-container {
        gap: 20px;
    }
    
    .income-card {
        padding: 24px 20px;
    }
    
    .income-card .stat-value {
        font-size: 2rem;
    }
}

/* Фильтры - Mobile First */
.filters-container {
    margin-bottom: 20px;
    width: 100%;
}

.filters-toggle-btn {
    width: 100%;
    background: white;
    border: none;
    border-radius: 12px;
    padding: 14px 16px;
    font-size: 1rem;
    font-weight: 500;
    color: #333;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: all 0.3s;
}

.filters-toggle-btn:hover {
    background: #f8f9fa;
}

.filters-toggle-btn i:first-child {
    font-size: 1.2rem;
    margin-right: 8px;
    color: #667eea;
}

.toggle-icon {
    transition: transform 0.3s;
}

.filters-toggle-btn.active .toggle-icon {
    transform: rotate(180deg);
}

.filter-panel {
    background: white;
    border-radius: 12px;
    padding: 16px;
    margin-top: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-bottom: 12px;
}

.filter-item {
    margin-bottom: 0;
}

.filter-item.full-width {
    grid-column: 1 / -1;
    margin-bottom: 12px;
}

.form-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #666;
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    font-size: 0.9rem;
    background-color: white;
    transition: all 0.2s;
}

.form-select:focus {
    border-color: #667eea;
    outline: none;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* Метки */
.labels-container {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
}

.badge-label {
    background: #f0f2f5;
    color: #495057;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s;
    user-select: none;
}

.badge-label:hover {
    background: #e4e6e9;
    transform: translateY(-1px);
}

.badge-label.selected {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

/* Кнопки действий */
.filter-actions {
    display: flex;
    gap: 10px;
    margin-top: 16px;
}

.btn-filter {
    flex: 2;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 10px;
    padding: 12px;
    font-weight: 500;
    transition: all 0.3s;
}

.btn-filter:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-outline-secondary {
    flex: 1;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 12px;
    color: #6c757d;
    transition: all 0.3s;
}

.btn-outline-secondary:hover {
    background: #f8f9fa;
    border-color: #667eea;
    color: #667eea;
}

/* Tablet (от 600px) */
@media (min-width: 600px) {
    .filters-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
    }
    
    .filter-panel {
        padding: 20px;
    }
    
    .filter-actions {
        justify-content: flex-end;
    }
    
    .btn-filter {
        width: auto;
        min-width: 150px;
    }
    
    .btn-outline-secondary {
        width: auto;
        min-width: 100px;
    }
}

/* Desktop (от 992px) */
@media (min-width: 992px) {
    .filters-toggle-btn {
        display: none;
    }
    
    .filter-panel {
        display: block !important;
        padding: 20px;
        margin-top: 0;
    }
    
    .filters-grid {
        grid-template-columns: repeat(5, 1fr);
        gap: 15px;
        margin-bottom: 15px;
    }
    
    .filter-item.full-width {
        grid-column: 1 / -1;
    }
    
    .filter-actions {
        justify-content: flex-start;
    }
}
</style>
</head>
<body>
    <?php include 'menu.php'; ?>
    
    <div class="container-fluid py-4">
<!-- Доход - Mobile First (Grid вариант) -->
<div class="income-container">
    <div class="income-card potential">
        <div class="stat-value"><?php echo number_format($income['potential'], 0, ',', ' '); ?> ₽</div>
        <div class="stat-label">Возможный доход за неделю</div>
    </div>
    <div class="income-card paid">
        <div class="stat-value"><?php echo number_format($income['paid'], 0, ',', ' '); ?> ₽</div>
        <div class="stat-label">Полученный доход за неделю</div>
    </div>
</div>
        
        <!-- Статистика - Mobile First (Grid вариант) -->
<div class="stats-container">
    <div class="stat-card">
        <div class="stat-value"><?php echo $activeStudents; ?></div>
        <div class="stat-label">Активных учеников</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $weeklyLessons; ?></div>
        <div class="stat-label">Занятий на неделю</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $weeklyHours; ?> ч</div>
        <div class="stat-label">Часов в неделю</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $todayLessons; ?></div>
        <div class="stat-label">Уроков сегодня</div>
    </div>
</div>


        
       <!-- Фильтры - Mobile First -->
<div class="filters-container">
    <button class="filters-toggle-btn" type="button" id="filtersToggle">
        <i class="bi bi-funnel"></i> Фильтры
        <i class="bi bi-chevron-down toggle-icon"></i>
    </button>
    
    <div class="filter-panel" id="filterPanel" style="display: none;">
        <form method="GET" action="" id="filterForm">
            <?php if ($weekOffset != 0): ?>
                <input type="hidden" name="week_offset" value="<?php echo $weekOffset; ?>">
            <?php endif; ?>
            <?php if (!empty($selectedDate)): ?>
                <input type="hidden" name="week_date" value="<?php echo $selectedDate; ?>">
            <?php endif; ?>
            
            <!-- Фильтры - сетка 2x2 на мобильных -->
            <div class="filters-grid">
                <div class="filter-item">
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
                
                <div class="filter-item">
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
                
                <div class="filter-item">
                    <label class="form-label">Статус ученика</label>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="active" <?php echo $statusFilter == 'active' ? 'selected' : ''; ?>>Активные</option>
                        <option value="inactive" <?php echo $statusFilter == 'inactive' ? 'selected' : ''; ?>>Неактивные</option>
                        <option value="all" <?php echo $statusFilter == 'all' ? 'selected' : ''; ?>>Все</option>
                    </select>
                </div>
                
                <div class="filter-item">
                    <label class="form-label">Статус урока</label>
                    <select name="lesson_status" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?php echo $lessonStatusFilter == 'all' ? 'selected' : ''; ?>>Все</option>
                        <option value="completed" <?php echo $lessonStatusFilter == 'completed' ? 'selected' : ''; ?>>Проведенные</option>
                        <option value="not_completed" <?php echo $lessonStatusFilter == 'not_completed' ? 'selected' : ''; ?>>Не проведенные</option>
                        <option value="cancelled" <?php echo $lessonStatusFilter == 'cancelled' ? 'selected' : ''; ?>>Отмененные</option>
                    </select>
                </div>
            </div>
            
            <div class="filter-item full-width">
                <label class="form-label">Режим фильтра меток</label>
                <select name="label_mode" class="form-select" onchange="this.form.submit()">
                    <option value="or" <?php echo $labelFilterMode == 'or' ? 'selected' : ''; ?>>ИЛИ (любая метка)</option>
                    <option value="and" <?php echo $labelFilterMode == 'and' ? 'selected' : ''; ?>>И (все метки)</option>
                </select>
            </div>
            
            <div class="filter-item full-width">
                <label class="form-label">Метки</label>
                <div class="labels-container">
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
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-filter">Применить фильтры</button>
                <a href="dashboard.php<?php echo $weekOffset != 0 ? '?week_offset=' . $weekOffset : ''; ?>" class="btn btn-outline-secondary">Сбросить</a>
            </div>
        </form>
    </div>
</div>
            <!-- Заголовок и навигация по неделям -->
        <div class="week-navigation">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h4 class="mb-0"><i class="bi bi-calendar-week"></i> Период:</h4>
                
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- Навигация по неделям -->
                    <div class="btn-group" role="group">
                        <a href="?week_offset=<?php echo $weekOffset - 1; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['week_offset' => ''])) : ''; ?>" 
                           class="btn btn-outline-primary" title="Предыдущая неделя">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#weekPickerModal">
                            <i class="bi bi-calendar-range"></i> 
                            <?php echo $weekStartDisplay; ?> - <?php echo $weekEndDisplay; ?>
                            <?php if ($weekOffset == 0 && empty($selectedDate)): ?>
                                <span class="current-week-badge">Текущая</span>
                            <?php endif; ?>
                        </button>
                        <a href="?week_offset=<?php echo $weekOffset + 1; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['week_offset' => ''])) : ''; ?>" 
                           class="btn btn-outline-primary" title="Следующая неделя">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                    
                    <!-- Кнопка для возврата на текущую неделю -->
                    <a href="?week_offset=0<?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['week_offset' => '', 'week_date' => ''])) : ''; ?>" 
                       class="btn btn-outline-secondary" title="Текущая неделя">
                        <i class="bi bi-calendar-check"></i> Сегодня
                    </a>
                </div>
            </div>
        </div>    
<!-- Расписание на выбранную неделю -->
<div class="row">
    <div class="col-12">
        <h3 class="mb-3">
            <i class="bi bi-calendar-range"></i> 
            Расписание на неделю: <?php echo $weekStartDisplay; ?> - <?php echo $weekEndDisplay; ?>
            <?php if ($weekOffset != 0): ?>
                <small class="text-muted">(<?php echo $weekOffset > 0 ? '+' . $weekOffset : $weekOffset; ?> неделя)</small>
            <?php endif; ?>
        </h3>
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
        $date = date('d.m', strtotime("{$dayKey} this week", strtotime($weekStart)));
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
                    
                    // Получаем информацию о дневнике для проверки наличия публичной ссылки
                    $diaryInfoStmt = $pdo->prepare("SELECT public_link FROM diaries WHERE id = ?");
                    $diaryInfoStmt->execute([$lesson['diary_id']]);
                    $diaryInfo = $diaryInfoStmt->fetch();
                    $hasPublicLink = !empty($diaryInfo['public_link']);
                    
                    // Получаем категории тем для этого занятия
                    $topicCategories = [];
                    if (!empty($lesson['topics_with_ids'])) {
                        $categoryStmt = $pdo->prepare("
                            SELECT DISTINCT c.name, c.color 
                            FROM lesson_topics lt
                            JOIN topics t ON lt.topic_id = t.id
                            LEFT JOIN categories c ON t.category_id = c.id
                            WHERE lt.lesson_id = ?
                        ");
                        $categoryStmt->execute([$lesson['id']]);
                        $categories = $categoryStmt->fetchAll();
                        
                        foreach ($categories as $cat) {
                            if (!empty($cat['name'])) {
                                $topicCategories[] = [
                                    'name' => $cat['name'],
                                    'color' => $cat['color'] ?? '#808080'
                                ];
                            }
                        }
                    }
                    
                    // Убираем дубликаты категорий
                    $topicCategories = array_unique($topicCategories, SORT_REGULAR);
                ?>
                    <div class="lesson-item <?php echo $lessonClass; ?>" 
                         onclick="window.location.href='lessons.php?action=edit&id=<?php echo $lesson['id']; ?>&diary_id=<?php echo $lesson['diary_id']; ?>'">
                        
                        <!-- Верхняя строка с датой, временем и статусом оплаты -->
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="lesson-time">
                                    <?php echo date('H:i', strtotime($lesson['start_time'])); ?>
                                </span>
                                <span class="lesson-duration text-muted">
                                    <i class="bi bi-clock"></i> <?php echo floor($lesson['duration'] / 60) . 'ч ' . ($lesson['duration'] % 60) . 'м'; ?>
                                </span>
                            </div>
                            
                            <!-- Плашка оплачено -->
                            <?php if ($lesson['is_paid']): ?>
                                <span class="paid-badge">
                                    <i class="bi bi-currency-dollar"></i>
                                </span>
                            <?php endif; ?>                        
                        </div>
                        
                        <!-- Основная информация -->
                        <div class="d-flex justify-content-between align-items-start">
                            <div style="flex: 1;">
                                <div class="lesson-student">
                                    <?php echo htmlspecialchars($lesson['last_name'] . ' ' . $lesson['first_name']); ?>
                                </div>
                                
                                <!-- Отображение категорий тем -->
                                <?php if (!empty($topicCategories)): ?>
                                    <div class="lesson-categories mt-1">
                                        <?php foreach ($topicCategories as $category): ?>
                                            <span class="category-badge" style="background-color: <?php echo $category['color']; ?>20; border-left: 3px solid <?php echo $category['color']; ?>; padding: 2px 8px; margin-right: 4px; margin-bottom: 2px; display: inline-block; border-radius: 12px; font-size: 0.7rem;">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="lesson-diary">
                                    <?php echo htmlspecialchars($lesson['diary_name']); ?>
                                </div>
                            </div>
                            
                            <!-- Кнопки действий -->

                            
<!-- Кнопки действий (сетка 2x2) -->
<div class="lesson-actions" onclick="event.stopPropagation()">
    <div class="row g-1">
        <div class="col-6">
            <a href="lessons.php?diary_id=<?php echo $lesson['diary_id']; ?>" 
               class="btn btn-sm btn-outline-primary w-100 p-1" 
               style="height: 30px;"
               data-bs-toggle="tooltip" 
               title="Занятия дневника">
                <i class="bi bi-calendar-check"></i>
            </a>
        </div>
        <div class="col-6">
            <a href="students.php?action=view&id=<?php echo $lesson['student_id']; ?>" 
               class="btn btn-sm btn-outline-info w-100 p-1" 
               style="height: 30px;"
               data-bs-toggle="tooltip" 
               title="Ученик">
                <i class="bi bi-person"></i>
            </a>
        </div>
        <div class="col-6">
            <a href="private_diary.php?id=<?php echo $lesson['diary_id']; ?>" 
               class="btn btn-sm btn-outline-info w-100 p-1" 
               style="height: 30px;"
               data-bs-toggle="tooltip" 
               title="Дневник">
                <i class="bi bi-info-circle"></i>
            </a>
        </div>
        <div class="col-6">
<a href="lessons.php?copy=1&id=<?php echo $lesson['id']; ?>&diary_id=<?php echo $lesson['diary_id']; ?>" 
   class="btn btn-sm btn-outline-success w-100 p-1" 
   style="width: 30px; height: 30px;"
   data-bs-toggle="tooltip" 
   title="Создать копию занятия и перейти к редактированию"
   onclick="return confirm('Создать копию этого занятия и перейти к редактированию?')">
    <i class="bi bi-files"></i>
</a>
        </div>
        <div class="col-6">
            <a href="lessons.php?action=edit&id=<?php echo $lesson['id']; ?>&diary_id=<?php echo $lesson['diary_id']; ?>" 
               class="btn btn-sm btn-outline-warning w-100 p-1" 
               style="height: 30px;"
               data-bs-toggle="tooltip" 
               title="Редактировать">
                <i class="bi bi-pencil"></i>
            </a>
        </div>
        <?php if ($hasPublicLink): ?>
            <div class="col-6">
                <a href="public_diary.php?token=<?php echo $diaryInfo['public_link']; ?>" 
                   target="_blank"
                   class="btn btn-sm btn-outline-success w-100 p-1" 
                   style="height: 30px;"
                   data-bs-toggle="tooltip" 
                   title="Публичная ссылка">
                    <i class="bi bi-link"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
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
                            
                            <?php if (!empty($lesson['cost'])): ?>
                                <hr class="my-1">
                                <strong>Стоимость:</strong>
                                <div><?php echo number_format($lesson['cost'], 0, ',', ' '); ?> ₽</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
    
    <!-- Модальное окно выбора недели -->
    <div class="modal fade" id="weekPickerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Выбор недели</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="GET" action="">
                    <div class="modal-body">
                        <?php 
                        // Сохраняем все текущие параметры фильтров
                        foreach ($_GET as $key => $value) {
                            if ($key != 'week_date' && $key != 'week_offset') {
                                if (is_array($value)) {
                                    foreach ($value as $v) {
                                        echo '<input type="hidden" name="' . htmlspecialchars($key) . '[]" value="' . htmlspecialchars($v) . '">';
                                    }
                                } else {
                                    echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
                                }
                            }
                        }
                        ?>
                        <div class="mb-3">
                            <label class="form-label">Выберите дату в нужной неделе</label>
                            <input type="date" name="week_date" class="form-control" 
                                   value="<?php echo $selectedDate ?: date('Y-m-d'); ?>" required>
                            <small class="text-muted">
                                Будет показана неделя, содержащая выбранную дату (пн-вс)
                            </small>
                        </div>
                        <input type="hidden" name="week_offset" value="0">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary">Показать неделю</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleLabel(labelId) {
            const checkbox = document.getElementById('label_' + labelId);
            checkbox.checked = !checkbox.checked;
            document.getElementById('filterForm').submit();
        }
// Переключение панели фильтров
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('filtersToggle');
    const filterPanel = document.getElementById('filterPanel');
    
    if (toggleBtn && filterPanel) {
        // Проверяем, нужно ли показывать фильтры на десктопе
        if (window.innerWidth >= 992) {
            filterPanel.style.display = 'block';
            toggleBtn.classList.add('active');
        }
        
        toggleBtn.addEventListener('click', function() {
            if (filterPanel.style.display === 'none' || filterPanel.style.display === '') {
                filterPanel.style.display = 'block';
                this.classList.add('active');
            } else {
                filterPanel.style.display = 'none';
                this.classList.remove('active');
            }
        });
        
        // При изменении размера окна
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 992) {
                filterPanel.style.display = 'block';
                toggleBtn.classList.add('active');
            } else {
                // Не скрываем автоматически на мобильных, сохраняем состояние пользователя
                if (filterPanel.style.display !== 'block') {
                    filterPanel.style.display = 'none';
                    toggleBtn.classList.remove('active');
                }
            }
        });
    }
});
    </script>
</body>
</html>