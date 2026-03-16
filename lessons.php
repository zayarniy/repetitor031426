<?php
require_once 'config.php';
requireAuth();

$currentUser = getCurrentUser($pdo);
$userId = $currentUser['id'];
$message = '';
$error = '';

// Получение ID дневника из параметров
$diaryId = $_GET['diary_id'] ?? 0;
$lessonId = $_GET['id'] ?? 0;
$action = $_GET['action'] ?? 'list';

// Получение информации о дневнике
$diary = null;
if ($diaryId) {
    $stmt = $pdo->prepare("
        SELECT d.*, s.id as student_id, s.first_name, s.last_name, s.middle_name
        FROM diaries d
        JOIN students s ON d.student_id = s.id
        WHERE d.id = ? AND d.user_id = ?
    ");
    $stmt->execute([$diaryId, $userId]);
    $diary = $stmt->fetch();
    
    if (!$diary) {
        header('Location: diaries.php');
        exit();
    }
}

// Получение категорий и меток для фильтрации
$stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? OR user_id IS NULL ORDER BY name");
$stmt->execute([$userId]);
$categories = $stmt->fetchAll();

// Получение всех тем с категориями
$stmt = $pdo->prepare("
    SELECT t.*, c.name as category_name, c.color as category_color
    FROM topics t
    LEFT JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ?
    ORDER BY t.name
");
$stmt->execute([$userId]);
$allTopics = $stmt->fetchAll();

// Получение всех ресурсов
$stmt = $pdo->prepare("
    SELECT r.*, c.name as category_name
    FROM resources r
    LEFT JOIN categories c ON r.category_id = c.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$userId]);
$allResources = $stmt->fetchAll();

// Получение всех домашних заданий для поиска
$stmt = $pdo->prepare("
    SELECT DISTINCT homework_manual 
    FROM lessons l
    JOIN diaries d ON l.diary_id = d.id
    WHERE d.user_id = ? AND l.homework_manual IS NOT NULL AND l.homework_manual != ''
    ORDER BY l.created_at DESC
    LIMIT 100
");
$stmt->execute([$userId]);
$allHomework = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Получение меток для фильтрации
$stmt = $pdo->prepare("
    SELECT l.*, c.name as category_name 
    FROM labels l
    LEFT JOIN categories c ON l.category_id = c.id
    WHERE l.user_id = ? AND (l.label_type = 'lesson' OR l.label_type = 'general')
    ORDER BY c.name, l.name
");
$stmt->execute([$userId]);
$lessonLabels = $stmt->fetchAll();

// Генерация публичной ссылки для занятия
if (isset($_GET['generate_link']) && $lessonId) {
    $publicLink = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("
        UPDATE lessons l
        JOIN diaries d ON l.diary_id = d.id
        SET l.public_link = ?
        WHERE l.id = ? AND d.user_id = ?
    ");
    if ($stmt->execute([$publicLink, $lessonId, $userId])) {
        header('Location: lessons.php?action=edit&id=' . $lessonId . '&diary_id=' . $diaryId . '&message=link_generated');
        exit();
    }
}

// Удаление публичной ссылки
if (isset($_GET['remove_link']) && $lessonId) {
    $stmt = $pdo->prepare("
        UPDATE lessons l
        JOIN diaries d ON l.diary_id = d.id
        SET l.public_link = NULL
        WHERE l.id = ? AND d.user_id = ?
    ");
    $stmt->execute([$lessonId, $userId]);
    header('Location: lessons.php?action=edit&id=' . $lessonId . '&diary_id=' . $diaryId . '&message=link_removed');
    exit();
}

// Удаление занятия
if (isset($_GET['delete']) && $lessonId) {
    $stmt = $pdo->prepare("
        DELETE l FROM lessons l
        JOIN diaries d ON l.diary_id = d.id
        WHERE l.id = ? AND d.user_id = ?
    ");
    if ($stmt->execute([$lessonId, $userId])) {
        header('Location: lessons.php?diary_id=' . $diaryId . '&message=deleted');
        exit();
    }
}

// Получение категорий для фильтрации ресурсов
$stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? OR user_id IS NULL ORDER BY name");
$stmt->execute([$userId]);
$resourceCategories = $stmt->fetchAll();

// Получение меток для фильтрации ресурсов (только метки типа resource или general)
$stmt = $pdo->prepare("
    SELECT l.*, c.name as category_name, c.color as category_color 
    FROM labels l
    LEFT JOIN categories c ON l.category_id = c.id
    WHERE l.user_id = ? AND (l.label_type = 'resource' OR l.label_type = 'general')
    ORDER BY c.name, l.name
");
$stmt->execute([$userId]);
$resourceLabels = $stmt->fetchAll();

// Получаем все ресурсы с их метками для фильтрации
$stmt = $pdo->prepare("
    SELECT r.*, 
           GROUP_CONCAT(DISTINCT l.id) as label_ids,
           GROUP_CONCAT(DISTINCT l.name) as label_names
    FROM resources r
    LEFT JOIN resource_labels rl ON r.id = rl.resource_id
    LEFT JOIN labels l ON rl.label_id = l.id
    WHERE r.user_id = ?
    GROUP BY r.id
    ORDER BY r.created_at DESC
");
$stmt->execute([$userId]);
$allResourcesWithLabels = $stmt->fetchAll();


// Сохранение занятия
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lesson'])) {
    $lessonDate = $_POST['lesson_date'] ?? date('Y-m-d');
    $startTime = $_POST['start_time'] ?? '12:00:00';
    $duration = intval($_POST['duration'] ?? $diary['lesson_duration'] ?? 60);
    $cost = !empty($_POST['cost']) ? floatval($_POST['cost']) : ($diary['lesson_cost'] ?? null);
    
    $topicsManual = trim($_POST['topics_manual'] ?? '');
    $homeworkManual = trim($_POST['homework_manual'] ?? '');
    $comment = trim($_POST['comment'] ?? '');
    
    $linkUrl = trim($_POST['link_url'] ?? '');
    $linkComment = trim($_POST['link_comment'] ?? '');
    
    $gradeLesson = isset($_POST['grade_lesson']) && $_POST['grade_lesson'] !== '' ? intval($_POST['grade_lesson']) : null;
    $gradeComment = trim($_POST['grade_comment'] ?? '');
    $gradeHomework = isset($_POST['grade_homework']) && $_POST['grade_homework'] !== '' ? intval($_POST['grade_homework']) : null;
    $homeworkComment = trim($_POST['homework_comment'] ?? '');
    
    $isCancelled = isset($_POST['is_cancelled']) ? 1 : 0;
    $isCompleted = isset($_POST['is_completed']) ? 1 : 0;
    $isPaid = isset($_POST['is_paid']) ? 1 : 0;
    
    $selectedTopics = $_POST['topics'] ?? [];
    $selectedResources = $_POST['resources'] ?? [];
    $resourceComments = $_POST['resource_comments'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        if ($lessonId) {
            // Обновление существующего занятия
            $stmt = $pdo->prepare("
                UPDATE lessons l
                JOIN diaries d ON l.diary_id = d.id
                SET
                    l.lesson_date = ?, l.start_time = ?, l.duration = ?, l.cost = ?,
                    l.topics_manual = ?, l.homework_manual = ?, l.comment = ?,
                    l.link_url = ?, l.link_comment = ?,
                    l.grade_lesson = ?, l.grade_comment = ?, l.grade_homework = ?, l.homework_comment = ?,
                    l.is_cancelled = ?, l.is_completed = ?, l.is_paid = ?
                WHERE l.id = ? AND d.user_id = ?
            ");
            $stmt->execute([
                $lessonDate, $startTime, $duration, $cost,
                $topicsManual, $homeworkManual, $comment,
                $linkUrl, $linkComment,
                $gradeLesson, $gradeComment, $gradeHomework, $homeworkComment,
                $isCancelled, $isCompleted, $isPaid,
                $lessonId, $userId
            ]);
            
            // Удаляем старые связи
            $stmt = $pdo->prepare("DELETE FROM lesson_topics WHERE lesson_id = ?");
            $stmt->execute([$lessonId]);
            
            $stmt = $pdo->prepare("DELETE FROM lesson_resources WHERE lesson_id = ?");
            $stmt->execute([$lessonId]);
            
            $message = 'Занятие обновлено';
        } else {
            // Создание нового занятия
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
                $diaryId, $diary['student_id'],
                $lessonDate, $startTime, $duration, $cost,
                $topicsManual, $homeworkManual, $comment,
                $linkUrl, $linkComment,
                $gradeLesson, $gradeComment, $gradeHomework, $homeworkComment,
                $isCancelled, $isCompleted, $isPaid
            ]);
            $lessonId = $pdo->lastInsertId();
            $message = 'Занятие добавлено';
        }
        
        // Добавляем выбранные темы
        if (!empty($selectedTopics)) {
            $stmt = $pdo->prepare("INSERT INTO lesson_topics (lesson_id, topic_id) VALUES (?, ?)");
            foreach ($selectedTopics as $topicId) {
                $stmt->execute([$lessonId, $topicId]);
            }
        }
        
        // Добавляем выбранные ресурсы с комментариями
        if (!empty($selectedResources)) {
            $stmt = $pdo->prepare("INSERT INTO lesson_resources (lesson_id, resource_id, comment) VALUES (?, ?, ?)");
            foreach ($selectedResources as $resourceId) {
                $comment = $resourceComments[$resourceId] ?? '';
                $stmt->execute([$lessonId, $resourceId, $comment]);
            }
        }
        
        // Сохраняем домашнее задание в общий банк для поиска
        if (!empty($homeworkManual)) {
            $stmt = $pdo->prepare("
                INSERT INTO homework_tasks (user_id, lesson_id, task_text) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$userId, $lessonId, $homeworkManual]);
        }
        
        $pdo->commit();
        header('Location: lessons.php?diary_id=' . $diaryId . '&message=saved');
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Ошибка при сохранении: ' . $e->getMessage();
    }
}

// Получение данных для редактирования
$editLesson = null;
$selectedTopicIds = [];
$selectedResources = [];

if ($lessonId) {
    $stmt = $pdo->prepare("
        SELECT l.* 
        FROM lessons l
        JOIN diaries d ON l.diary_id = d.id
        WHERE l.id = ? AND d.user_id = ?
    ");
    $stmt->execute([$lessonId, $userId]);
    $editLesson = $stmt->fetch();
    
    if ($editLesson) {
        // Получаем выбранные темы
        $stmt = $pdo->prepare("SELECT topic_id FROM lesson_topics WHERE lesson_id = ?");
        $stmt->execute([$lessonId]);
        $selectedTopicIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Получаем выбранные ресурсы с комментариями
        $stmt = $pdo->prepare("SELECT resource_id, comment FROM lesson_resources WHERE lesson_id = ?");
        $stmt->execute([$lessonId]);
        while ($row = $stmt->fetch()) {
            $selectedResources[$row['resource_id']] = $row['comment'];
        }
    }
}

// Получение списка занятий с фильтрацией
$filterDate = $_GET['filter_date'] ?? '';
$filterStatus = $_GET['filter_status'] ?? 'all';
$filterPaid = $_GET['filter_paid'] ?? 'all';
$filterTopic = $_GET['filter_topic'] ?? '';

$query = "
    SELECT l.*,
           GROUP_CONCAT(DISTINCT t.name) as topic_names,
           COUNT(DISTINCT lr.resource_id) as resources_count
    FROM lessons l
    LEFT JOIN lesson_topics lt ON l.id = lt.lesson_id
    LEFT JOIN topics t ON lt.topic_id = t.id
    LEFT JOIN lesson_resources lr ON l.id = lr.lesson_id
    WHERE l.diary_id = ?
";
$params = [$diaryId];

if (!empty($filterDate)) {
    $query .= " AND l.lesson_date = ?";
    $params[] = $filterDate;
}

if ($filterStatus === 'completed') {
    $query .= " AND l.is_completed = 1";
} elseif ($filterStatus === 'cancelled') {
    $query .= " AND l.is_cancelled = 1";
} elseif ($filterStatus === 'planned') {
    $query .= " AND l.is_completed = 0 AND l.is_cancelled = 0";
}

if ($filterPaid === 'paid') {
    $query .= " AND l.is_paid = 1";
} elseif ($filterPaid === 'unpaid') {
    $query .= " AND l.is_paid = 0";
}

if (!empty($filterTopic)) {
    $query .= " AND lt.topic_id = ?";
    $params[] = $filterTopic;
}

$query .= " GROUP BY l.id ORDER BY l.lesson_date DESC, l.start_time DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$lessons = $stmt->fetchAll();

// Группировка занятий по месяцам для удобного отображения
$groupedLessons = [];
foreach ($lessons as $lesson) {
    $month = date('Y-m', strtotime($lesson['lesson_date']));
    if (!isset($groupedLessons[$month])) {
        $groupedLessons[$month] = [];
    }
    $groupedLessons[$month][] = $lesson;
}

// Получение публичной ссылки для просмотра
$publicView = isset($_GET['public']) && isset($_GET['token']);
if ($publicView) {
    $token = $_GET['token'];
    $stmt = $pdo->prepare("
        SELECT l.*, d.name as diary_name, s.first_name, s.last_name, s.middle_name
        FROM lessons l
        JOIN diaries d ON l.diary_id = d.id
        JOIN students s ON l.student_id = s.id
        WHERE l.public_link = ?
    ");
    $stmt->execute([$token]);
    $publicLesson = $stmt->fetch();
    
    if (!$publicLesson) {
        header('Location: diaries.php');
        exit();
    }
    
    // Получаем темы для публичного просмотра
    $stmt = $pdo->prepare("
        SELECT t.name 
        FROM lesson_topics lt
        JOIN topics t ON lt.topic_id = t.id
        WHERE lt.lesson_id = ?
    ");
    $stmt->execute([$publicLesson['id']]);
    $publicTopics = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Дневник ученика - Занятия</title>
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
        }
        .lesson-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-left: 4px solid;
            cursor: pointer;
        }
        .lesson-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .lesson-card.completed {
            border-left-color: #28a745;
            background: #f8fff9;
        }
        .lesson-card.cancelled {
            border-left-color: #dc3545;
            background: #fff8f8;
            opacity: 0.8;
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
        .lesson-topics {
            margin-top: 10px;
            color: #666;
        }
        .topic-badge {
            background: #e9ecef;
            border-radius: 15px;
            padding: 2px 10px;
            font-size: 0.85em;
            display: inline-block;
            margin-right: 5px;
            margin-bottom: 5px;
        }
        .lesson-meta {
            display: flex;
            gap: 15px;
            margin-top: 10px;
            font-size: 0.9em;
            color: #666;
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
        .month-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            margin: 20px 0 15px;
        }
        .form-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .section-title {
            font-size: 1.1em;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        .topic-selector {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 10px;
        }
        .resource-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
        }
        .grade-select {
            width: 80px;
            display: inline-block;
        }
        .quick-actions {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        .quick-actions .btn {
            width: 50px;
            height: 50px;
            border-radius: 25px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            margin-bottom: 10px;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .homework-search {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 10px;
        }
        .homework-item {
            padding: 8px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
        }
        .homework-item:hover {
            background: #f8f9fa;
        }
        .homework-item:last-child {
            border-bottom: none;
        }
        .public-view {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
        }
        .public-lesson {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        /* Добавьте в секцию <style> */
.resource-item-check {
    padding: 10px;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    margin-bottom: 8px;
    transition: all 0.2s;
}
.resource-item-check:hover {
    background-color: #f8f9fa;
    border-color: #dee2e6;
}
.resource-item-check .badge {
    font-size: 0.75rem;
    padding: 3px 8px;
}
#activeFilters .badge {
    font-size: 0.75rem;
    padding: 3px 8px;
}

/* Стили для бейджей оценок */
.grade-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: 500;
    margin-right: 5px;
    margin-bottom: 5px;
}

.grade-badge i {
    margin-right: 3px;
}

.grade-badge.grade-5 {
    background: #28a745;
    color: white;
}

.grade-badge.grade-4 {
    background: #17a2b8;
    color: white;
}

.grade-badge.grade-3 {
    background: #ffc107;
    color: #333;
}

.grade-badge.grade-2 {
    background: #fd7e14;
    color: white;
}

.grade-badge.grade-1 {
    background: #dc3545;
    color: white;
}

.grade-badge.grade-0 {
    background: #6c757d;
    color: white;
}

/* Стили для предпросмотра комментариев */
.comment-preview, .homework-preview {
    background: #f8f9fa;
    border-radius: 6px;
    padding: 4px 8px;
    margin-top: 2px;
    border-left: 2px solid #667eea;
    color: #495057;
}

/* Стили для секций карточки */
.lesson-grades, .lesson-statuses {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.lesson-comment, .lesson-homework, .homework-comment {
    margin-bottom: 8px;
}

/* Адаптивность для мобильных */
@media (max-width: 768px) {
    .lesson-card .row > div {
        margin-bottom: 10px;
    }
    
    .lesson-grades, .lesson-statuses {
        justify-content: flex-start;
    }
}

/* Улучшенные стили для карточки */
.lesson-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border-left: 4px solid;
    cursor: pointer;
}

.lesson-card:hover {
    transform: translateX(5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

.lesson-card.completed {
    border-left-color: #28a745;
    background: #f8fff9;
}

.lesson-card.cancelled {
    border-left-color: #dc3545;
    background: #fff8f8;
    opacity: 0.8;
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

.topic-badge {
    background: #e9ecef;
    border-radius: 15px;
    padding: 2px 10px;
    font-size: 0.8em;
    display: inline-block;
    margin-right: 5px;
    margin-bottom: 5px;
}
    </style>
</head>
<body>
    <?php if (!$publicView): ?>
        <?php include 'menu.php'; ?>
    <?php endif; ?>
    
    <div class="container-fluid py-4">
        <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                $messages = [
                    'saved' => 'Занятие успешно сохранено',
                    'deleted' => 'Занятие удалено',
                    'link_generated' => 'Публичная ссылка сгенерирована',
                    'link_removed' => 'Публичная ссылка удалена'
                ];
                echo $messages[$_GET['message']] ?? 'Операция выполнена успешно';
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($publicView && isset($publicLesson)): ?>
            <!-- Публичный просмотр занятия -->
            <div class="public-view">
                <div class="public-lesson">
                    <div class="text-center mb-4">
                        <i class="bi bi-journal-bookmark-fill" style="font-size: 3rem; color: #667eea;"></i>
                        <h2 class="mt-3">Занятие по расписанию</h2>
                        <p class="text-muted">Дневник: <?php echo htmlspecialchars($publicLesson['diary_name']); ?></p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Ученик:</strong> 
                                <?php echo htmlspecialchars($publicLesson['last_name'] . ' ' . $publicLesson['first_name'] . ' ' . ($publicLesson['middle_name'] ?? '')); ?>
                            </p>
                            <p><strong>Дата:</strong> <?php echo date('d.m.Y', strtotime($publicLesson['lesson_date'])); ?></p>
                            <p><strong>Время:</strong> <?php echo date('H:i', strtotime($publicLesson['start_time'])); ?></p>
                            <p><strong>Длительность:</strong> 
                                <?php 
                                $hours = floor($publicLesson['duration'] / 60);
                                $minutes = $publicLesson['duration'] % 60;
                                echo ($hours > 0 ? $hours . ' ч ' : '') . ($minutes > 0 ? $minutes . ' мин' : '');
                                ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Статус:</strong> 
                                <?php if ($publicLesson['is_completed']): ?>
                                    <span class="badge bg-success">Проведено</span>
                                <?php elseif ($publicLesson['is_cancelled']): ?>
                                    <span class="badge bg-danger">Отменено</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Запланировано</span>
                                <?php endif; ?>
                            </p>
                            <?php if ($publicLesson['grade_lesson'] !== null): ?>
                                <p><strong>Оценка за занятие:</strong> <?php echo $publicLesson['grade_lesson']; ?>/5</p>
                            <?php endif; ?>
                            <?php if ($publicLesson['grade_homework'] !== null): ?>
                                <p><strong>Оценка за ДЗ:</strong> <?php echo $publicLesson['grade_homework']; ?>/5</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($publicTopics)): ?>
                        <div class="mt-3">
                            <strong>Темы занятия:</strong>
                            <div class="mt-2">
                                <?php foreach ($publicTopics as $topic): ?>
                                    <span class="topic-badge"><?php echo htmlspecialchars($topic); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($publicLesson['topics_manual'])): ?>
                        <div class="mt-3">
                            <strong>Темы (вручную):</strong>
                            <p><?php echo nl2br(htmlspecialchars($publicLesson['topics_manual'])); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($publicLesson['homework_manual'])): ?>
                        <div class="mt-3">
                            <strong>Домашнее задание:</strong>
                            <p><?php echo nl2br(htmlspecialchars($publicLesson['homework_manual'])); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($publicLesson['comment'])): ?>
                        <div class="mt-3">
                            <strong>Комментарий:</strong>
                            <p><?php echo nl2br(htmlspecialchars($publicLesson['comment'])); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($publicLesson['link_url'])): ?>
                        <div class="mt-3">
                            <strong>Ссылка:</strong>
                            <p>
                                <a href="<?php echo htmlspecialchars($publicLesson['link_url']); ?>" target="_blank">
                                    <?php echo htmlspecialchars($publicLesson['link_url']); ?>
                                </a>
                            </p>
                            <?php if (!empty($publicLesson['link_comment'])): ?>
                                <small class="text-muted"><?php echo htmlspecialchars($publicLesson['link_comment']); ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info mt-4">
                        <i class="bi bi-info-circle"></i>
                        Это публичная ссылка на занятие. Вы можете поделиться ею с учеником.
                    </div>
                </div>
            </div>
            
        <?php elseif ($action === 'edit' || $lessonId): ?>
            <!-- Форма редактирования занятия -->
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>
                            <i class="bi bi-calendar-plus"></i>
                            <?php echo $lessonId ? 'Редактирование занятия' : 'Новое занятие'; ?>
                            <small class="text-muted"><?php echo htmlspecialchars($diary['last_name'] . ' ' . $diary['first_name']); ?></small>
                        </h2>
                        <div>
                            <?php if ($lessonId && $editLesson): ?>
                                <?php if ($editLesson['public_link']): ?>
                                    <button class="btn btn-sm btn-outline-info me-2" onclick="copyLessonLink()">
                                        <i class="bi bi-link"></i> Копировать ссылку
                                    </button>
                                    <a href="?remove_link=1&id=<?php echo $lessonId; ?>&diary_id=<?php echo $diaryId; ?>" 
                                       class="btn btn-sm btn-outline-warning me-2"
                                       onclick="return confirm('Удалить публичную ссылку?')">
                                        <i class="bi bi-link-45deg"></i> Удалить ссылку
                                    </a>
                                <?php else: ?>
                                    <a href="?generate_link=1&id=<?php echo $lessonId; ?>&diary_id=<?php echo $diaryId; ?>" 
                                       class="btn btn-sm btn-outline-success me-2">
                                        <i class="bi bi-link-45deg"></i> Создать ссылку
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                            <a href="lessons.php?diary_id=<?php echo $diaryId; ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Назад
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <form method="POST" action="" id="lessonForm">
                <div class="row">
                    <div class="col-md-8">
                        <!-- Основная информация -->
                        <div class="form-section">
                            <h5 class="section-title">Основная информация</h5>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Дата занятия *</label>
                                    <input type="date" name="lesson_date" class="form-control" 
                                           value="<?php echo $editLesson ? $editLesson['lesson_date'] : date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Время начала</label>
                                    <input type="time" name="start_time" class="form-control" 
                                           value="<?php echo $editLesson ? date('H:i', strtotime($editLesson['start_time'])) : '12:00'; ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Длительность (мин)</label>
                                    <input type="number" name="duration" class="form-control" 
                                           value="<?php echo $editLesson ? $editLesson['duration'] : ($diary['lesson_duration'] ?? 60); ?>"
                                           step="15" min="15">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Стоимость (₽)</label>
                                    <input type="number" name="cost" class="form-control" 
                                           value="<?php echo $editLesson ? $editLesson['cost'] : ($diary['lesson_cost'] ?? ''); ?>"
                                           step="100" min="0">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Статусы</label>
                                    <div class="d-flex gap-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="is_completed" class="form-check-input" id="isCompleted"
                                                   <?php echo ($editLesson && $editLesson['is_completed']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="isCompleted">Проведено</label>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" name="is_cancelled" class="form-check-input" id="isCancelled"
                                                   <?php echo ($editLesson && $editLesson['is_cancelled']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="isCancelled">Отменено</label>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" name="is_paid" class="form-check-input" id="isPaid"
                                                   <?php echo ($editLesson && $editLesson['is_paid']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="isPaid">Оплачено</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Темы занятия -->
                        <div class="form-section">
                            <h5 class="section-title">Темы занятия</h5>
                            
<!-- В разделе "Темы занятия" замените кнопку выбора тем: -->
<div class="mb-3">
    <label class="form-label">Выбрать из банка тем</label>
    <button type="button" class="btn btn-sm btn-outline-primary mb-2" onclick="openTopicsModal()">
        <i class="bi bi-plus-circle"></i> Выбрать темы
    </button>
    
    <div id="selected-topics" class="mt-2">
        <?php if (!empty($selectedTopicIds)): ?>
            <?php foreach ($allTopics as $topic): ?>
                <?php if (in_array($topic['id'], $selectedTopicIds)): ?>
                    <span class="topic-badge">
                        <?php echo htmlspecialchars($topic['name']); ?>
                        <input type="hidden" name="topics[]" value="<?php echo $topic['id']; ?>">
                    </span>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
                            
                            <div class="mb-3">
                                <label class="form-label">Темы (ввести вручную)</label>
                                <textarea name="topics_manual" class="form-control" rows="2" 
                                          placeholder="Можно ввести темы вручную"><?php echo $editLesson ? htmlspecialchars($editLesson['topics_manual'] ?? '') : ''; ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Домашнее задание -->
                        <div class="form-section">
                            <h5 class="section-title">Домашнее задание</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Поиск из уже заданных</label>
                                <button type="button" class="btn btn-sm btn-outline-primary mb-2" data-bs-toggle="modal" data-bs-target="#homeworkModal">
                                    <i class="bi bi-search"></i> Найти ДЗ
                                </button>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Домашнее задание</label>
                                <textarea name="homework_manual" class="form-control" rows="3" 
                                          placeholder="Введите домашнее задание"><?php echo $editLesson ? htmlspecialchars($editLesson['homework_manual'] ?? '') : ''; ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Оценка за ДЗ (0-5)</label>
                                    <select name="grade_homework" class="form-select grade-select">
                                        <option value="">—</option>
                                        <?php for ($i = 0; $i <= 5; $i++): ?>
                                            <option value="<?php echo $i; ?>" 
                                                <?php echo ($editLesson && $editLesson['grade_homework'] == $i) ? 'selected' : ''; ?>>
                                                <?php echo $i; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Комментарий к ДЗ</label>
                                    <input type="text" name="homework_comment" class="form-control" 
                                           value="<?php echo $editLesson ? htmlspecialchars($editLesson['homework_comment'] ?? '') : ''; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Ресурсы -->
                        <div class="form-section">
                            <h5 class="section-title">Ресурсы</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Выбрать из банка ресурсов</label>
                                <button type="button" class="btn btn-sm btn-outline-primary mb-2" data-bs-toggle="modal" data-bs-target="#resourcesModal">
                                    <i class="bi bi-plus-circle"></i> Добавить ресурсы
                                </button>
                                
                                <div id="selected-resources" class="mt-2">
                                    <?php if (!empty($selectedResources)): ?>
                                        <?php foreach ($allResources as $resource): ?>
                                            <?php if (isset($selectedResources[$resource['id']])): ?>
                                                <div class="resource-item" id="resource-<?php echo $resource['id']; ?>">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($resource['description'] ?: $resource['url']); ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($resource['url']); ?></small>
                                                        </div>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeResource(<?php echo $resource['id']; ?>)">
                                                            <i class="bi bi-x"></i>
                                                        </button>
                                                    </div>
                                                    <div class="mt-2">
                                                        <input type="text" name="resource_comments[<?php echo $resource['id']; ?>]" 
                                                               class="form-control form-control-sm" 
                                                               placeholder="Комментарий к ресурсу"
                                                               value="<?php echo htmlspecialchars($selectedResources[$resource['id']]); ?>">
                                                        <input type="hidden" name="resources[]" value="<?php echo $resource['id']; ?>">
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Ссылка на ресурс</label>
                                <input type="url" name="link_url" class="form-control" 
                                       value="<?php echo $editLesson ? htmlspecialchars($editLesson['link_url'] ?? '') : ''; ?>"
                                       placeholder="https://...">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Комментарий к ссылке</label>
                                <input type="text" name="link_comment" class="form-control" 
                                       value="<?php echo $editLesson ? htmlspecialchars($editLesson['link_comment'] ?? '') : ''; ?>"
                                       placeholder="Описание ссылки">
                            </div>
                        </div>
                        
                        <!-- Комментарий и оценки -->
                        <div class="form-section">
                            <h5 class="section-title">Комментарий и оценки</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Комментарий к занятию</label>
                                <textarea name="comment" class="form-control" rows="3"><?php echo $editLesson ? htmlspecialchars($editLesson['comment'] ?? '') : ''; ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Оценка за занятие (0-5)</label>
                                    <select name="grade_lesson" class="form-select grade-select">
                                        <option value="">—</option>
                                        <?php for ($i = 0; $i <= 5; $i++): ?>
                                            <option value="<?php echo $i; ?>" 
                                                <?php echo ($editLesson && $editLesson['grade_lesson'] == $i) ? 'selected' : ''; ?>>
                                                <?php echo $i; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Комментарий к оценке</label>
                                    <input type="text" name="grade_comment" class="form-control" 
                                           value="<?php echo $editLesson ? htmlspecialchars($editLesson['grade_comment'] ?? '') : ''; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" name="save_lesson" class="btn btn-primary btn-lg mb-4">
                            <i class="bi bi-save"></i> Сохранить занятие
                        </button>
                    </div>
                    
                    <div class="col-md-4">
                        <!-- Информационный блок -->
                        <div class="form-section">
                            <h5 class="section-title">Информация</h5>
                            
                            <p><strong>Ученик:</strong> 
                                <?php echo htmlspecialchars($diary['last_name'] . ' ' . $diary['first_name'] . ' ' . ($diary['middle_name'] ?? '')); ?>
                            </p>
                            <p><strong>Дневник:</strong> <?php echo htmlspecialchars($diary['name']); ?></p>
                            <p><strong>Стандартная стоимость:</strong> 
                                <?php echo $diary['lesson_cost'] ? number_format($diary['lesson_cost'], 0, ',', ' ') . ' ₽' : 'Не указана'; ?>
                            </p>
                            <p><strong>Стандартная длительность:</strong> 
                                <?php echo $diary['lesson_duration'] ? $diary['lesson_duration'] . ' мин' : '60 мин'; ?>
                            </p>
                            
                            <?php if ($lessonId && $editLesson): ?>
                                <hr>
                                <p><small class="text-muted">Создано: <?php echo date('d.m.Y H:i', strtotime($editLesson['created_at'])); ?></small></p>
                                <p><small class="text-muted">Обновлено: <?php echo date('d.m.Y H:i', strtotime($editLesson['updated_at'])); ?></small></p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Быстрые действия -->
                        <div class="form-section">
                            <h5 class="section-title">Быстрые действия</h5>
                            
                            <button type="button" class="btn btn-outline-secondary w-100 mb-2" onclick="clearAllFields()">
                                <i class="bi bi-eraser"></i> Очистить все поля
                            </button>
                            
                            <?php if ($lessonId): ?>
                                <a href="?delete=1&id=<?php echo $lessonId; ?>&diary_id=<?php echo $diaryId; ?>" 
                                   class="btn btn-outline-danger w-100"
                                   onclick="return confirm('Удалить занятие?')">
                                    <i class="bi bi-trash"></i> Удалить занятие
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
            
        <?php else: ?>
            <!-- Список занятий -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="bi bi-calendar-check"></i> 
                    Занятия: <?php echo htmlspecialchars($diary['last_name'] . ' ' . $diary['first_name']); ?>
                    <small class="text-muted"><?php echo htmlspecialchars($diary['name']); ?></small>
                </h2>
                <div>
                    <a href="?action=edit&diary_id=<?php echo $diaryId; ?>" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Добавить занятие
                    </a>
                    <a href="diaries.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> К дневникам
                    </a>
                </div>
            </div>
            
            <!-- Статистика -->
            <?php
            $totalLessons = count($lessons);
            $completedLessons = 0;
            $cancelledLessons = 0;
            $totalCost = 0;
            $paidCost = 0;
            
            foreach ($lessons as $l) {
                if ($l['is_completed']) $completedLessons++;
                if ($l['is_cancelled']) $cancelledLessons++;
                if ($l['cost']) {
                    $totalCost += $l['cost'];
                    if ($l['is_paid']) $paidCost += $l['cost'];
                }
            }
            ?>
            
            <div class="stats-card mb-4">
                <div class="row">
                    <div class="col-md-3">
                        <div class="text-center">
                            <h3 class="mb-0"><?php echo $totalLessons; ?></h3>
                            <small>Всего занятий</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h3 class="mb-0"><?php echo $completedLessons; ?></h3>
                            <small>Проведено</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h3 class="mb-0"><?php echo number_format($totalCost, 0, ',', ' '); ?> ₽</h3>
                            <small>Общая стоимость</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h3 class="mb-0"><?php echo number_format($paidCost, 0, ',', ' '); ?> ₽</h3>
                            <small>Оплачено</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Фильтры -->
            <div class="filter-panel">
                <form method="GET" action="" class="row g-3">
                    <input type="hidden" name="diary_id" value="<?php echo $diaryId; ?>">
                    
                    <div class="col-md-2">
                        <label class="form-label">Дата</label>
                        <input type="date" name="filter_date" class="form-control" value="<?php echo $filterDate; ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Статус</label>
                        <select name="filter_status" class="form-select">
                            <option value="all" <?php echo $filterStatus == 'all' ? 'selected' : ''; ?>>Все</option>
                            <option value="planned" <?php echo $filterStatus == 'planned' ? 'selected' : ''; ?>>Запланированные</option>
                            <option value="completed" <?php echo $filterStatus == 'completed' ? 'selected' : ''; ?>>Проведенные</option>
                            <option value="cancelled" <?php echo $filterStatus == 'cancelled' ? 'selected' : ''; ?>>Отмененные</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Оплата</label>
                        <select name="filter_paid" class="form-select">
                            <option value="all" <?php echo $filterPaid == 'all' ? 'selected' : ''; ?>>Все</option>
                            <option value="paid" <?php echo $filterPaid == 'paid' ? 'selected' : ''; ?>>Оплачено</option>
                            <option value="unpaid" <?php echo $filterPaid == 'unpaid' ? 'selected' : ''; ?>>Не оплачено</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Тема</label>
                        <select name="filter_topic" class="form-select">
                            <option value="">Все темы</option>
                            <?php foreach ($allTopics as $topic): ?>
                                <option value="<?php echo $topic['id']; ?>" <?php echo $filterTopic == $topic['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($topic['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Применить
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Список занятий по месяцам -->            
<?php if (empty($lessons)): ?>
    <div class="alert alert-info text-center py-5">
        <i class="bi bi-calendar-x" style="font-size: 3rem;"></i>
        <h4 class="mt-3">Занятия не найдены</h4>
        <p>Добавьте первое занятие, нажав кнопку "Добавить занятие"</p>
    </div>
<?php else: ?>
    <?php foreach ($groupedLessons as $month => $monthLessons): ?>
        <div class="month-header">
            <h4 class="mb-0"><?php echo date('F Y', strtotime($month . '-01')); ?></h4>
        </div>
        
        <?php foreach ($monthLessons as $lesson): 
            $statusClass = $lesson['is_completed'] ? 'completed' : ($lesson['is_cancelled'] ? 'cancelled' : 'planned');
            
            // Получаем комментарий к ДЗ, если есть
            $homeworkComment = !empty($lesson['homework_comment']) ? $lesson['homework_comment'] : '';
            
            // Получаем оценку за занятие и ДЗ
            $gradeLesson = isset($lesson['grade_lesson']) && $lesson['grade_lesson'] !== '' ? $lesson['grade_lesson'] : null;
            $gradeHomework = isset($lesson['grade_homework']) && $lesson['grade_homework'] !== '' ? $lesson['grade_homework'] : null;
            
            // Получаем комментарий к занятию
            $lessonComment = !empty($lesson['comment']) ? $lesson['comment'] : '';
            
            // Получаем домашнее задание
            $homework = !empty($lesson['homework_manual']) ? $lesson['homework_manual'] : '';
        ?>
            <div class="lesson-card <?php echo $statusClass; ?>" onclick="window.location.href='?action=edit&id=<?php echo $lesson['id']; ?>&diary_id=<?php echo $diaryId; ?>'">
                <div class="row">
                    <div class="col-md-2">
                        <div class="lesson-date"><?php echo date('d.m.Y', strtotime($lesson['lesson_date'])); ?></div>
                        <div class="lesson-time"><?php echo date('H:i', strtotime($lesson['start_time'])); ?></div>
                        <div class="lesson-duration mt-1">
                            <small class="text-muted">
                                <i class="bi bi-clock"></i> <?php echo $lesson['duration']; ?> мин
                            </small>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <!-- Оценки -->
                        <div class="lesson-grades mb-2">
                            <?php if ($gradeLesson !== null): ?>
                                <span class="grade-badge grade-<?php echo $gradeLesson; ?>" title="Оценка за занятие">
                                    <i class="bi bi-star-fill"></i> <?php echo $gradeLesson; ?>/5
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($gradeHomework !== null): ?>
                                <span class="grade-badge grade-<?php echo $gradeHomework; ?>" title="Оценка за ДЗ">
                                    <i class="bi bi-journal-check"></i> <?php echo $gradeHomework; ?>/5
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Статусы -->
                        <div class="lesson-statuses mb-2">
                            <?php if ($lesson['is_completed']): ?>
                                <span class="badge bg-success"><i class="bi bi-check-circle"></i> Проведено</span>
                            <?php elseif ($lesson['is_cancelled']): ?>
                                <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Отменено</span>
                            <?php else: ?>
                                <span class="badge bg-warning"><i class="bi bi-clock"></i> Запланировано</span>
                            <?php endif; ?>
                            
                            <?php if ($lesson['is_paid']): ?>
                                <span class="badge bg-info"><i class="bi bi-currency-ruble"></i> Оплачено</span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Стоимость (теперь внизу карточки) -->
                        <?php if ($lesson['cost']): ?>
                            <div class="lesson-cost text-muted small">
                                <i class="bi bi-currency-ruble"></i> <?php echo number_format($lesson['cost'], 0, ',', ' '); ?> ₽
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-4">
                        <!-- Темы занятия -->
                        <?php if (!empty($lesson['topic_names'])): ?>
                            <div class="lesson-topics mb-2">
                                <small class="text-muted"><i class="bi bi-book"></i> Темы:</small>
                                <div class="mt-1">
                                    <?php 
                                    $topics = explode(',', $lesson['topic_names']);
                                    foreach (array_slice($topics, 0, 2) as $topic):
                                    ?>
                                        <span class="topic-badge"><?php echo htmlspecialchars(trim($topic)); ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($topics) > 2): ?>
                                        <span class="topic-badge">+<?php echo count($topics) - 2; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Комментарий к занятию -->
                        <?php if (!empty($lessonComment)): ?>
                            <div class="lesson-comment mb-2">
                                <small class="text-muted"><i class="bi bi-chat"></i> Комментарий:</small>
                                <div class="comment-preview small">
                                    <?php echo nl2br(htmlspecialchars(substr($lessonComment, 0, 50) . (strlen($lessonComment) > 50 ? '...' : ''))); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Домашнее задание -->
                        <?php if (!empty($homework)): ?>
                            <div class="lesson-homework mb-2">
                                <small class="text-muted"><i class="bi bi-journal-text"></i> ДЗ:</small>
                                <div class="homework-preview small">
                                    <?php echo nl2br(htmlspecialchars(substr($homework, 0, 50) . (strlen($homework) > 50 ? '...' : ''))); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Комментарий к ДЗ -->
                        <?php if (!empty($homeworkComment)): ?>
                            <div class="homework-comment">
                                <small class="text-muted"><i class="bi bi-chat-dots"></i> Комм. к ДЗ:</small>
                                <div class="comment-preview small">
                                    <?php echo nl2br(htmlspecialchars(substr($homeworkComment, 0, 50) . (strlen($homeworkComment) > 50 ? '...' : ''))); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-3">
                        <!-- Ресурсы -->
                        <?php if ($lesson['resources_count'] > 0): ?>
                            <div class="lesson-resources mb-2">
                                <small class="text-muted"><i class="bi bi-link"></i> Ресурсы: <?php echo $lesson['resources_count']; ?></small>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Ссылка на ресурс -->
                        <?php if (!empty($lesson['link_url'])): ?>
                            <div class="lesson-link mb-2">
                                <small class="text-muted"><i class="bi bi-box-arrow-up-right"></i> 
                                    <a href="<?php echo htmlspecialchars($lesson['link_url']); ?>" target="_blank" class="text-decoration-none">
                                        <?php echo htmlspecialchars(substr($lesson['link_url'], 0, 20) . '...'); ?>
                                    </a>
                                </small>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Мета-информация -->
                        <div class="lesson-meta mt-2">
                            <small class="text-muted">
                                <i class="bi bi-tag"></i> Ресурсов: <?php echo $lesson['resources_count'] ?? 0; ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>
<?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- Модальное окно выбора тем -->
    <div class="modal fade" id="topicsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Выбор тем</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Категории</h6>
                            <select class="form-select mb-3" id="topicCategoryFilter">
                                <option value="">Все категории</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <h6>Поиск</h6>
                            <input type="text" class="form-control mb-3" id="topicSearch" placeholder="Поиск по названию...">
                        </div>
                    </div>
                    
                    <div class="topic-selector" id="topicsList">
                        <?php foreach ($allTopics as $topic): ?>
                            <div class="form-check topic-item" data-category="<?php echo $topic['category_id']; ?>" data-name="<?php echo strtolower($topic['name']); ?>">
                                <input type="checkbox" class="form-check-input topic-checkbox" 
                                       value="<?php echo $topic['id']; ?>" 
                                       id="topic_<?php echo $topic['id']; ?>"
                                       <?php echo (in_array($topic['id'], $selectedTopicIds)) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="topic_<?php echo $topic['id']; ?>">
                                    <?php echo htmlspecialchars($topic['name']); ?>
                                    <?php if ($topic['category_name']): ?>
                                        <small class="text-muted">(<?php echo htmlspecialchars($topic['category_name']); ?>)</small>
                                    <?php endif; ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-primary" onclick="applyTopics()">Применить</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно выбора ресурсов -->
    <!-- Модальное окно выбора ресурсов -->
<div class="modal fade" id="resourcesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Выбор ресурсов</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Фильтры -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Категория</label>
                        <select class="form-select" id="resourceCategoryFilter">
                            <option value="">Все категории</option>
                            <?php foreach ($resourceCategories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Метка</label>
                        <select class="form-select" id="resourceLabelFilter">
                            <option value="">Все метки</option>
                            <?php foreach ($resourceLabels as $label): ?>
                                <option value="<?php echo $label['id']; ?>" 
                                        data-category="<?php echo $label['category_id']; ?>">
                                    <?php echo htmlspecialchars($label['name']); ?>
                                    <?php if ($label['category_name']): ?>
                                        (<?php echo htmlspecialchars($label['category_name']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Тип ресурса</label>
                        <select class="form-select" id="resourceTypeFilter">
                            <option value="">Все типы</option>
                            <option value="page">Страница</option>
                            <option value="document">Документ</option>
                            <option value="video">Видео</option>
                            <option value="audio">Аудио</option>
                            <option value="other">Другое</option>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">Поиск по названию</label>
                        <input type="text" class="form-control" id="resourceSearch" placeholder="Введите текст для поиска...">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="button" class="btn btn-outline-secondary w-100" onclick="clearResourceFilters()">
                            <i class="bi bi-eraser"></i> Сбросить фильтры
                        </button>
                    </div>
                </div>
                
                <!-- Информация о количестве и выбранных фильтрах -->
                <div class="mb-2 small">
                    <span id="resourceCount" class="badge bg-primary">0</span> ресурсов найдено
                    <span id="activeFilters" class="ms-2"></span>
                </div>
                
                <!-- Список ресурсов -->
                <div class="topic-selector" id="resourcesList">
                    <?php foreach ($allResourcesWithLabels as $resource): 
                        // Определяем иконку для типа ресурса
                        $typeIcon = 'bi-file-earmark';
                        if ($resource['type'] === 'page') $typeIcon = 'bi-file-earmark-text';
                        else if ($resource['type'] === 'document') $typeIcon = 'bi-file-earmark-pdf';
                        else if ($resource['type'] === 'video') $typeIcon = 'bi-camera-reels';
                        else if ($resource['type'] === 'audio') $typeIcon = 'bi-mic';
                        
                        // Получаем ID меток для фильтрации
                        $labelIds = $resource['label_ids'] ? explode(',', $resource['label_ids']) : [];
                        $labelNames = $resource['label_names'] ? explode(',', $resource['label_names']) : [];
                    ?>
                        <div class="form-check resource-item-check mb-2" 
                             data-id="<?php echo $resource['id']; ?>"
                             data-description="<?php echo htmlspecialchars($resource['description'] ?: 'Ресурс'); ?>"
                             data-url="<?php echo htmlspecialchars($resource['url']); ?>"
                             data-type="<?php echo $resource['type']; ?>"
                             data-category="<?php echo $resource['category_id']; ?>"
                             data-labels="<?php echo implode(',', $labelIds); ?>"
                             data-search="<?php echo strtolower(htmlspecialchars($resource['description'] ?: $resource['url'])); ?>">
                            <div class="d-flex align-items-start">
                                <input type="checkbox" class="form-check-input resource-checkbox me-2" 
                                       value="<?php echo $resource['id']; ?>" 
                                       id="resource_<?php echo $resource['id']; ?>">
                                <label class="form-check-label flex-grow-1" for="resource_<?php echo $resource['id']; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center">
                                                <i class="bi <?php echo $typeIcon; ?> me-2 text-primary"></i>
                                                <strong><?php echo htmlspecialchars($resource['description'] ?: substr($resource['url'], 0, 50) . '...'); ?></strong>
                                            </div>
                                            
                                            <!-- Метки ресурса -->
                                            <?php if (!empty($labelNames)): ?>
                                                <div class="mt-1">
                                                    <?php foreach ($labelNames as $labelName): ?>
                                                        <?php if (trim($labelName)): ?>
                                                            <span class="badge bg-light text-dark me-1"><?php echo htmlspecialchars(trim($labelName)); ?></span>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Категория и тип -->
                                            <div class="mt-1">
                                                <?php 
                                                // Находим категорию
                                                $catName = '';
                                                foreach ($resourceCategories as $cat) {
                                                    if ($cat['id'] == $resource['category_id']) {
                                                        $catName = $cat['name'];
                                                        break;
                                                    }
                                                }
                                                ?>
                                                <?php if ($catName): ?>
                                                    <span class="badge" style="background: #e9ecef; color: #333;">
                                                        <i class="bi bi-folder"></i> <?php echo htmlspecialchars($catName); ?>
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <span class="badge bg-secondary ms-1">
                                                    <?php 
                                                    $typeNames = ['page' => 'Страница', 'document' => 'Документ', 'video' => 'Видео', 'audio' => 'Аудио', 'other' => 'Другое'];
                                                    echo $typeNames[$resource['type']] ?? 'Другое';
                                                    ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <a href="<?php echo htmlspecialchars($resource['url']); ?>" 
                                           target="_blank" 
                                           class="btn btn-sm btn-outline-primary ms-2"
                                           title="Перейти по ссылке"
                                           onclick="event.stopPropagation()">
                                            <i class="bi bi-box-arrow-up-right"></i>
                                        </a>
                                    </div>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Сообщение, если ничего не найдено -->
                <div id="noResourcesMessage" class="text-center py-4 text-muted" style="display: none;">
                    <i class="bi bi-search" style="font-size: 2rem;"></i>
                    <p class="mt-2">Ресурсы не найдены</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="applyResources()">Применить</button>
            </div>
        </div>
    </div>
</div>
    
    <!-- Модальное окно поиска домашних заданий -->
    <div class="modal fade" id="homeworkModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Поиск домашних заданий</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="text" class="form-control mb-3" id="homeworkSearch" placeholder="Поиск по тексту...">
                    
                    <div class="homework-search" id="homeworkList">
                        <?php foreach ($allHomework as $homework): ?>
                            <?php if (!empty($homework)): ?>
                                <div class="homework-item" onclick="selectHomework('<?php echo htmlspecialchars(addslashes($homework)); ?>')">
                                    <?php echo htmlspecialchars(substr($homework, 0, 100)) . (strlen($homework) > 100 ? '...' : ''); ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    

<script>
// Глобальные переменные для хранения выбранных элементов
let selectedTopics = <?php echo json_encode($selectedTopicIds); ?>;
let selectedResourcesData = <?php echo json_encode($selectedResources); ?>;

// Функции для работы с темами
function openTopicsModal() {
    const modal = new bootstrap.Modal(document.getElementById('topicsModal'));
    
    // Обновляем чекбоксы в соответствии с выбранными темами
    document.querySelectorAll('#topicsList .topic-checkbox').forEach(cb => {
        cb.checked = selectedTopics.includes(parseInt(cb.value));
    });
    
    modal.show();
}

function applyTopics() {
    selectedTopics = [];
    document.querySelectorAll('#topicsList .topic-checkbox:checked').forEach(cb => {
        selectedTopics.push(parseInt(cb.value));
    });
    
    // Обновляем отображение выбранных тем
    const container = document.getElementById('selected-topics');
    container.innerHTML = '';
    
    selectedTopics.forEach(topicId => {
        const label = document.querySelector(`label[for="topic_${topicId}"]`).innerText;
        const span = document.createElement('span');
        span.className = 'topic-badge';
        span.innerHTML = `${label} <input type="hidden" name="topics[]" value="${topicId}">`;
        container.appendChild(span);
    });
    
    bootstrap.Modal.getInstance(document.getElementById('topicsModal')).hide();
}

// Функции для работы с ресурсами
function openResourcesModal() {
    const modal = new bootstrap.Modal(document.getElementById('resourcesModal'));
    
    // Обновляем чекбоксы в соответствии с выбранными ресурсами
    document.querySelectorAll('#resourcesList .resource-checkbox').forEach(cb => {
        cb.checked = selectedResourcesData.hasOwnProperty(cb.value);
    });
    
    modal.show();
}

function applyResources() {
    const selected = [];
    document.querySelectorAll('#resourcesList .resource-checkbox:checked').forEach(cb => {
        selected.push(parseInt(cb.value));
    });
    
    // Обновляем контейнер с выбранными ресурсами
    const container = document.getElementById('selected-resources');
    container.innerHTML = '';
    
    selected.forEach(resourceId => {
        const resourceDiv = document.querySelector(`#resourcesList .resource-checkbox[value="${resourceId}"]`).closest('.resource-item-check');
        
        // Получаем данные из data-атрибутов
        const description = resourceDiv.dataset.description || 'Ресурс';
        const url = resourceDiv.dataset.url || '';
        const type = resourceDiv.dataset.type || 'other';
        
        // Иконка в зависимости от типа
        let icon = 'bi-file-earmark';
        if (type === 'page') icon = 'bi-file-earmark-text';
        else if (type === 'document') icon = 'bi-file-earmark-pdf';
        else if (type === 'video') icon = 'bi-camera-reels';
        else if (type === 'audio') icon = 'bi-mic';
        
        const div = document.createElement('div');
        div.className = 'resource-item';
        div.id = `resource-${resourceId}`;
        div.innerHTML = `
            <div class="d-flex justify-content-between align-items-start">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center">
                        <i class="bi ${icon} me-2"></i>
                        <strong>${description}</strong>
                        <a href="${url}" target="_blank" class="btn btn-sm btn-link text-primary ms-2 p-0" title="Перейти по ссылке">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeResource(${resourceId})">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div class="mt-2">
                <input type="text" name="resource_comments[${resourceId}]" 
                       class="form-control form-control-sm" 
                       placeholder="Комментарий к ресурсу"
                       value="${selectedResourcesData[resourceId] || ''}">
                <input type="hidden" name="resources[]" value="${resourceId}">
            </div>
        `;
        container.appendChild(div);
        
        // Обновляем selectedResourcesData
        if (!selectedResourcesData[resourceId]) {
            selectedResourcesData[resourceId] = '';
        }
    });
    
    // Удаляем ресурсы, которые были сняты
    Object.keys(selectedResourcesData).forEach(resourceId => {
        if (!selected.includes(parseInt(resourceId))) {
            delete selectedResourcesData[resourceId];
        }
    });
    
    bootstrap.Modal.getInstance(document.getElementById('resourcesModal')).hide();
}


function removeResource(resourceId) {
    document.getElementById(`resource-${resourceId}`).remove();
    delete selectedResourcesData[resourceId];
    
    // Снимаем чекбокс в модальном окне
    const checkbox = document.querySelector(`#resourcesList .resource-checkbox[value="${resourceId}"]`);
    if (checkbox) {
        checkbox.checked = false;
    }
}

// Функции для работы с ДЗ
function selectHomework(text) {
    document.querySelector('textarea[name="homework_manual"]').value = text;
    bootstrap.Modal.getInstance(document.getElementById('homeworkModal')).hide();
}

// Фильтрация тем
document.addEventListener('DOMContentLoaded', function() {
    const categoryFilter = document.getElementById('topicCategoryFilter');
    const searchInput = document.getElementById('topicSearch');
    
    if (categoryFilter) {
        categoryFilter.addEventListener('change', filterTopics);
    }
    
    if (searchInput) {
        searchInput.addEventListener('input', filterTopics);
    }
    
    // Фильтрация ресурсов
    const resourceSearch = document.getElementById('resourceSearch');
    if (resourceSearch) {
        resourceSearch.addEventListener('input', function(e) {
            const search = e.target.value.toLowerCase();
            
            document.querySelectorAll('#resourcesList .resource-item-check').forEach(item => {
                const text = item.innerText.toLowerCase();
                item.style.display = text.includes(search) ? 'block' : 'none';
            });
        });
    }
    
    // Фильтрация ДЗ
    const homeworkSearch = document.getElementById('homeworkSearch');
    if (homeworkSearch) {
        homeworkSearch.addEventListener('input', function(e) {
            const search = e.target.value.toLowerCase();
            
            document.querySelectorAll('#homeworkList .homework-item').forEach(item => {
                const text = item.innerText.toLowerCase();
                item.style.display = text.includes(search) ? 'block' : 'none';
            });
        });
    }
});

function filterTopics() {
    const category = document.getElementById('topicCategoryFilter').value;
    const search = document.getElementById('topicSearch').value.toLowerCase();
    
    document.querySelectorAll('#topicsList .topic-item').forEach(item => {
        let show = true;
        const itemCategory = item.dataset.category;
        const itemName = item.dataset.name || '';
        
        if (category && itemCategory != category && !(category === 'null' && !itemCategory)) {
            show = false;
        }
        
        if (search && !itemName.includes(search)) {
            show = false;
        }
        
        item.style.display = show ? 'block' : 'none';
    });
}

// Очистка всех полей
function clearAllFields() {
    if (confirm('Очистить все поля формы?')) {
        document.getElementById('lessonForm').reset();
        document.getElementById('selected-topics').innerHTML = '';
        document.getElementById('selected-resources').innerHTML = '';
        selectedTopics = [];
        selectedResourcesData = {};
    }
}

// Копирование публичной ссылки
function copyLessonLink() {
    const link = "<?php echo $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/lessons.php?public=1&token=' . ($editLesson['public_link'] ?? ''); ?>";
    navigator.clipboard.writeText(link).then(() => {
        alert('Ссылка скопирована в буфер обмена');
    });
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    // Исправляем проблему с модальными окнами Bootstrap
    var modalElements = document.querySelectorAll('.modal');
    modalElements.forEach(function(modalEl) {
        new bootstrap.Modal(modalEl);
    });
});


document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const openModals = document.querySelectorAll('.modal.show');
        openModals.forEach(modal => {
            bootstrap.Modal.getInstance(modal).hide();
        });
    }
});

// Функции для фильтрации ресурсов
function filterResources() {
    const category = document.getElementById('resourceCategoryFilter').value;
    const label = document.getElementById('resourceLabelFilter').value;
    const type = document.getElementById('resourceTypeFilter').value;
    const search = document.getElementById('resourceSearch').value.toLowerCase();
    
    let visibleCount = 0;
    
    document.querySelectorAll('#resourcesList .resource-item-check').forEach(item => {
        let show = true;
        
        // Фильтр по категории
        if (category) {
            const itemCategory = item.dataset.category;
            if (itemCategory != category && !(category === 'null' && !itemCategory)) {
                show = false;
            }
        }
        
        // Фильтр по метке
        if (show && label) {
            const itemLabels = item.dataset.labels ? item.dataset.labels.split(',') : [];
            if (!itemLabels.includes(label)) {
                show = false;
            }
        }
        
        // Фильтр по типу
        if (show && type) {
            if (item.dataset.type !== type) {
                show = false;
            }
        }
        
        // Поиск по тексту
        if (show && search) {
            const searchText = item.dataset.search || '';
            if (!searchText.includes(search)) {
                show = false;
            }
        }
        
        item.style.display = show ? 'block' : 'none';
        if (show) visibleCount++;
    });
    
    // Обновляем счетчик
    document.getElementById('resourceCount').textContent = visibleCount;
    
    // Показываем/скрываем сообщение о пустом результате
    const noResourcesMsg = document.getElementById('noResourcesMessage');
    if (visibleCount === 0) {
        noResourcesMsg.style.display = 'block';
    } else {
        noResourcesMsg.style.display = 'none';
    }
    
    // Обновляем отображение активных фильтров
    updateActiveFilters();
}

function updateActiveFilters() {
    const category = document.getElementById('resourceCategoryFilter');
    const label = document.getElementById('resourceLabelFilter');
    const type = document.getElementById('resourceTypeFilter');
    const search = document.getElementById('resourceSearch');
    
    const filters = [];
    
    if (category.value) {
        const catText = category.options[category.selectedIndex].text;
        filters.push(`<span class="badge bg-info me-1">Категория: ${catText}</span>`);
    }
    
    if (label.value) {
        const labelText = label.options[label.selectedIndex].text.split('(')[0].trim();
        filters.push(`<span class="badge bg-success me-1">Метка: ${labelText}</span>`);
    }
    
    if (type.value) {
        const typeText = type.options[type.selectedIndex].text;
        filters.push(`<span class="badge bg-warning me-1">Тип: ${typeText}</span>`);
    }
    
    if (search.value) {
        filters.push(`<span class="badge bg-secondary me-1">Поиск: ${search.value}</span>`);
    }
    
    document.getElementById('activeFilters').innerHTML = filters.join('');
}

function clearResourceFilters() {
    document.getElementById('resourceCategoryFilter').value = '';
    document.getElementById('resourceLabelFilter').value = '';
    document.getElementById('resourceTypeFilter').value = '';
    document.getElementById('resourceSearch').value = '';
    filterResources();
}

// Обновляем фильтр меток при изменении категории
document.getElementById('resourceCategoryFilter')?.addEventListener('change', function() {
    const categoryId = this.value;
    const labelFilter = document.getElementById('resourceLabelFilter');
    
    // Показываем только метки, принадлежащие выбранной категории
    Array.from(labelFilter.options).forEach(option => {
        if (option.value === '') return;
        
        const optionCategory = option.dataset.category;
        if (categoryId && optionCategory != categoryId) {
            option.style.display = 'none';
        } else {
            option.style.display = 'block';
        }
    });
    
    filterResources();
});

// Добавляем обработчики событий
document.addEventListener('DOMContentLoaded', function() {
    // Фильтры для ресурсов
    const resourceCategoryFilter = document.getElementById('resourceCategoryFilter');
    const resourceLabelFilter = document.getElementById('resourceLabelFilter');
    const resourceTypeFilter = document.getElementById('resourceTypeFilter');
    const resourceSearch = document.getElementById('resourceSearch');
    
    if (resourceCategoryFilter) {
        resourceCategoryFilter.addEventListener('change', filterResources);
    }
    
    if (resourceLabelFilter) {
        resourceLabelFilter.addEventListener('change', filterResources);
    }
    
    if (resourceTypeFilter) {
        resourceTypeFilter.addEventListener('change', filterResources);
    }
    
    if (resourceSearch) {
        resourceSearch.addEventListener('input', filterResources);
    }
    
    // Обновляем счетчик при открытии модального окна
    const resourcesModal = document.getElementById('resourcesModal');
    if (resourcesModal) {
        resourcesModal.addEventListener('shown.bs.modal', function() {
            filterResources();
        });
    }
});

// Обновляем функцию openResourcesModal
function openResourcesModal() {
    const modal = new bootstrap.Modal(document.getElementById('resourcesModal'));
    
    // Обновляем чекбоксы в соответствии с выбранными ресурсами
    document.querySelectorAll('#resourcesList .resource-checkbox').forEach(cb => {
        cb.checked = selectedResourcesData.hasOwnProperty(cb.value);
    });
    
    // Сбрасываем фильтры
    clearResourceFilters();
    
    modal.show();
}
    </script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>