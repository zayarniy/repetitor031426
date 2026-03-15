<?php
require_once 'config.php';
requireAuth();

$currentUser = getCurrentUser($pdo);
$userId = $currentUser['id'];
$message = '';
$error = '';

// Обработка действий
$action = $_GET['action'] ?? 'list';
$planningId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Получение категорий для фильтрации
$stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? OR user_id IS NULL ORDER BY name");
$stmt->execute([$userId]);
$categories = $stmt->fetchAll();

// Получение всех тем с категориями
$stmt = $pdo->prepare("
    SELECT t.*, c.name as category_name, c.color as category_color 
    FROM topics t
    LEFT JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ?
    ORDER BY c.name, t.name
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

// Получение меток для фильтрации (только для планирований)
$stmt = $pdo->prepare("
    SELECT l.*, c.name as category_name, c.color as category_color 
    FROM labels l
    LEFT JOIN categories c ON l.category_id = c.id
    WHERE l.user_id = ? AND (l.label_type = 'general' OR l.label_type = 'planning')
    ORDER BY c.name, l.name
");
$stmt->execute([$userId]);
$allLabels = $stmt->fetchAll();

// Получение списка учеников
$stmt = $pdo->prepare("
    SELECT id, last_name, first_name, middle_name, class 
    FROM students 
    WHERE user_id = ? AND is_active = 1 
    ORDER BY last_name, first_name
");
$stmt->execute([$userId]);
$students = $stmt->fetchAll();



// Переключение видимости планирования
if (isset($_GET['toggle_active']) && $planningId) {
    $stmt = $pdo->prepare("UPDATE plannings SET is_active = NOT is_active WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$planningId, $userId])) {
        // Запись в историю
        $stmt = $pdo->prepare("INSERT INTO planning_history (planning_id, user_id, action) VALUES (?, ?, 'toggle_active')");
        $stmt->execute([$planningId, $userId]);
        header('Location: planning.php?message=toggled');
        exit();
    }
}

// Удаление планирования
if (isset($_GET['delete']) && $planningId) {
    try {
        $pdo->beginTransaction();

        // Запись в историю перед удалением
        $stmt = $pdo->prepare("INSERT INTO planning_history (planning_id, user_id, action) VALUES (?, ?, 'delete')");
        $stmt->execute([$planningId, $userId]);

        // Удаление планирования (каскадно удалятся все строки и связи)
        $stmt = $pdo->prepare("DELETE FROM plannings WHERE id = ? AND user_id = ?");
        $stmt->execute([$planningId, $userId]);

        $pdo->commit();
        header('Location: planning.php?message=deleted');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Ошибка при удалении: ' . $e->getMessage();
    }
}

// Создание копии планирования
if (isset($_GET['copy']) && $planningId) {
    try {
        $pdo->beginTransaction();

        // Получаем исходное планирование
        $stmt = $pdo->prepare("SELECT * FROM plannings WHERE id = ? AND user_id = ?");
        $stmt->execute([$planningId, $userId]);
        $sourcePlanning = $stmt->fetch();

        if ($sourcePlanning) {
            // Создаем копию
            $newName = $sourcePlanning['name'] . ' (копия)';
            $stmt = $pdo->prepare("
                INSERT INTO plannings (user_id, student_id, category_id, name, description, is_template, is_active)
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $userId,
                $sourcePlanning['student_id'],
                $sourcePlanning['category_id'],
                $newName,
                $sourcePlanning['description'],
                $sourcePlanning['is_template']
            ]);
            $newPlanningId = $pdo->lastInsertId();

            // Копируем строки с их связями
            $stmt = $pdo->prepare("SELECT * FROM planning_rows WHERE planning_id = ? ORDER BY sort_order");
            $stmt->execute([$planningId]);
            $rows = $stmt->fetchAll();

            if (!empty($rows)) {
                $insertStmt = $pdo->prepare("
                    INSERT INTO planning_rows (planning_id, lesson_number, lesson_date, topics_text, resources_text, homework, notes, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($rows as $row) {
                    $insertStmt->execute([
                        $newPlanningId,
                        $row['lesson_number'],
                        $row['lesson_date'],
                        $row['topics_text'],
                        $row['resources_text'],
                        $row['homework'],
                        $row['notes'],
                        $row['sort_order']
                    ]);
                    $newRowId = $pdo->lastInsertId();

                    // Копируем связи с темами
                    if (!empty($row['id'])) {
                        $topicStmt = $pdo->prepare("SELECT topic_id FROM planning_row_topics WHERE row_id = ?");
                        $topicStmt->execute([$row['id']]);
                        $topics = $topicStmt->fetchAll();

                        if (!empty($topics)) {
                            $insertTopicStmt = $pdo->prepare("INSERT INTO planning_row_topics (row_id, topic_id) VALUES (?, ?)");
                            foreach ($topics as $topic) {
                                $insertTopicStmt->execute([$newRowId, $topic['topic_id']]);
                            }
                        }
                    }

                    // Копируем связи с ресурсами
                    if (!empty($row['id'])) {
                        $resourceStmt = $pdo->prepare("SELECT resource_id FROM planning_row_resources WHERE row_id = ?");
                        $resourceStmt->execute([$row['id']]);
                        $resources = $resourceStmt->fetchAll();

                        if (!empty($resources)) {
                            $insertResourceStmt = $pdo->prepare("INSERT INTO planning_row_resources (row_id, resource_id) VALUES (?, ?)");
                            foreach ($resources as $resource) {
                                $insertResourceStmt->execute([$newRowId, $resource['resource_id']]);
                            }
                        }
                    }
                }
            }

            // Копируем метки
            $stmt = $pdo->prepare("SELECT label_id FROM planning_labels WHERE planning_id = ?");
            $stmt->execute([$planningId]);
            $labels = $stmt->fetchAll();

            if (!empty($labels)) {
                $insertStmt = $pdo->prepare("INSERT INTO planning_labels (planning_id, label_id) VALUES (?, ?)");
                foreach ($labels as $label) {
                    $insertStmt->execute([$newPlanningId, $label['label_id']]);
                }
            }

            // Запись в историю
            $stmt = $pdo->prepare("INSERT INTO planning_history (planning_id, user_id, action, details) VALUES (?, ?, 'copy', ?)");
            $stmt->execute([$newPlanningId, $userId, 'Скопировано из ID: ' . $planningId]);

            $pdo->commit();
            header('Location: planning.php?message=copied');
            exit();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Ошибка при копировании: ' . $e->getMessage();
    }
}

// Сохранение планирования (основная информация)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_planning'])) {
    $name = trim($_POST['name'] ?? '');
    $studentId = !empty($_POST['student_id']) ? intval($_POST['student_id']) : null;
    $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $description = trim($_POST['description'] ?? '');
    $isTemplate = isset($_POST['is_template']) ? 1 : 0;
    $selectedLabels = $_POST['labels'] ?? [];

    if (empty($name)) {
        $error = 'Название планирования обязательно';
    } else {
        try {
            $pdo->beginTransaction();

            if ($action === 'edit' && $planningId) {
                // Обновление планирования
                $stmt = $pdo->prepare("
                    UPDATE plannings SET 
                        name = ?, student_id = ?, category_id = ?, description = ?, is_template = ?
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$name, $studentId, $categoryId, $description, $isTemplate, $planningId, $userId]);

                // Удаляем старые связи с метками
                $stmt = $pdo->prepare("DELETE FROM planning_labels WHERE planning_id = ?");
                $stmt->execute([$planningId]);

                // Запись в историю
                $stmt = $pdo->prepare("INSERT INTO planning_history (planning_id, user_id, action) VALUES (?, ?, 'update')");
                $stmt->execute([$planningId, $userId]);

                $message = 'Планирование обновлено';
            } else {
                // Создание нового планирования
                $stmt = $pdo->prepare("
                    INSERT INTO plannings (user_id, student_id, category_id, name, description, is_template, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$userId, $studentId, $categoryId, $name, $description, $isTemplate]);
                $planningId = $pdo->lastInsertId();

                // Запись в историю
                $stmt = $pdo->prepare("INSERT INTO planning_history (planning_id, user_id, action) VALUES (?, ?, 'create')");
                $stmt->execute([$planningId, $userId]);

                $message = 'Планирование создано';
            }

            // Добавляем новые связи с метками
            if (!empty($selectedLabels)) {
                $stmt = $pdo->prepare("INSERT INTO planning_labels (planning_id, label_id) VALUES (?, ?)");
                foreach ($selectedLabels as $labelId) {
                    $labelId = intval($labelId);
                    if ($labelId > 0) {
                        $stmt->execute([$planningId, $labelId]);
                    }
                }
            }

            $pdo->commit();
            header('Location: planning.php?message=saved');
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Ошибка при сохранении: ' . $e->getMessage();
        }
    }
}

// Сохранение строк планирования с выбранными темами и ресурсами
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_rows']) && $planningId) {
    try {
        $pdo->beginTransaction();

        // Получаем существующие строки для сохранения их ID
        $stmt = $pdo->prepare("SELECT id FROM planning_rows WHERE planning_id = ?");
        $stmt->execute([$planningId]);
        $existingRowIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Удаляем старые связи для существующих строк
        if (!empty($existingRowIds)) {
            $placeholders = implode(',', array_fill(0, count($existingRowIds), '?'));
            $stmt = $pdo->prepare("DELETE FROM planning_row_topics WHERE row_id IN ($placeholders)");
            $stmt->execute($existingRowIds);

            $stmt = $pdo->prepare("DELETE FROM planning_row_resources WHERE row_id IN ($placeholders)");
            $stmt->execute($existingRowIds);
        }

        // Удаляем существующие строки
        $stmt = $pdo->prepare("DELETE FROM planning_rows WHERE planning_id = ?");
        $stmt->execute([$planningId]);

        // Добавляем новые строки
        if (isset($_POST['rows']) && is_array($_POST['rows'])) {
            $insertRowStmt = $pdo->prepare("
                INSERT INTO planning_rows (planning_id, lesson_number, lesson_date, topics_text, resources_text, homework, notes, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $insertTopicStmt = $pdo->prepare("INSERT INTO planning_row_topics (row_id, topic_id) VALUES (?, ?)");
            $insertResourceStmt = $pdo->prepare("INSERT INTO planning_row_resources (row_id, resource_id) VALUES (?, ?)");

            foreach ($_POST['rows'] as $index => $row) {
                $lessonNumber = intval($row['lesson_number'] ?? ($index + 1));
                $lessonDate = !empty($row['lesson_date']) ? $row['lesson_date'] : null;
                $topicsText = trim($row['topics_text'] ?? '');
                $resourcesText = trim($row['resources_text'] ?? '');
                $homework = trim($row['homework'] ?? '');
                $notes = trim($row['notes'] ?? '');

                $insertRowStmt->execute([
                    $planningId,
                    $lessonNumber,
                    $lessonDate,
                    $topicsText,
                    $resourcesText,
                    $homework,
                    $notes,
                    $index
                ]);

                $newRowId = $pdo->lastInsertId();

                // Сохраняем выбранные темы
                if (isset($row['topics']) && is_array($row['topics'])) {
                    foreach ($row['topics'] as $topicId) {
                        $topicId = intval($topicId);
                        if ($topicId > 0) {
                            $insertTopicStmt->execute([$newRowId, $topicId]);
                        }
                    }
                }

                // Сохраняем выбранные ресурсы
                if (isset($row['resources']) && is_array($row['resources'])) {
                    foreach ($row['resources'] as $resourceId) {
                        $resourceId = intval($resourceId);
                        if ($resourceId > 0) {
                            $insertResourceStmt->execute([$newRowId, $resourceId]);
                        }
                    }
                }
            }
        }

        // Запись в историю
        $stmt = $pdo->prepare("INSERT INTO planning_history (planning_id, user_id, action) VALUES (?, ?, 'update_rows')");
        $stmt->execute([$planningId, $userId]);

        $pdo->commit();
        header('Location: planning.php?action=edit&id=' . $planningId . '&tab=rows&message=rows_saved');
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Ошибка при сохранении строк: ' . $e->getMessage();
    }
}

// Экспорт в CSV
if (isset($_GET['export_csv']) && $planningId) {
    // Получаем информацию о планировании
    $stmt = $pdo->prepare("
        SELECT p.*, s.last_name, s.first_name, s.middle_name, c.name as category_name
        FROM plannings p
        LEFT JOIN students s ON p.student_id = s.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = ? AND p.user_id = ?
    ");
    $stmt->execute([$planningId, $userId]);
    $planning = $stmt->fetch();

    if ($planning) {
        // Получаем строки планирования с темами и ресурсами
        $stmt = $pdo->prepare("
            SELECT pr.*,
                   GROUP_CONCAT(DISTINCT t.name SEPARATOR ', ') as topics_names,
                   GROUP_CONCAT(DISTINCT r.description SEPARATOR ', ') as resources_names
            FROM planning_rows pr
            LEFT JOIN planning_row_topics prt ON pr.id = prt.row_id
            LEFT JOIN topics t ON prt.topic_id = t.id
            LEFT JOIN planning_row_resources prr ON pr.id = prr.row_id
            LEFT JOIN resources r ON prr.resource_id = r.id
            WHERE pr.planning_id = ?
            GROUP BY pr.id
            ORDER BY pr.sort_order
        ");
        $stmt->execute([$planningId]);
        $rows = $stmt->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="planning_' . $planningId . '_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        // Заголовки
        fputcsv($output, ['Номер занятия', 'Дата', 'Темы', 'Ресурсы', 'Домашнее задание', 'Примечание']);

        // Данные
        foreach ($rows as $row) {
            $topics = $row['topics_names'] ?: $row['topics_text'];
            $resources = $row['resources_names'] ?: $row['resources_text'];

            fputcsv($output, [
                $row['lesson_number'],
                $row['lesson_date'] ? date('d.m.Y', strtotime($row['lesson_date'])) : '',
                $topics,
                $resources,
                $row['homework'],
                $row['notes']
            ]);
        }

        fclose($output);
        exit();
    }
}

// Получение данных для редактирования
$editPlanning = null;
$planningRows = [];
$selectedLabels = [];

if (($action === 'edit' || $action === 'view') && $planningId) {
    $stmt = $pdo->prepare("
        SELECT p.*, s.last_name, s.first_name, s.middle_name, s.class, c.name as category_name, c.color as category_color
        FROM plannings p
        LEFT JOIN students s ON p.student_id = s.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = ? AND p.user_id = ?
    ");
    $stmt->execute([$planningId, $userId]);
    $editPlanning = $stmt->fetch();

    if ($editPlanning) {
        // Получаем строки планирования с выбранными темами и ресурсами
        $stmt = $pdo->prepare("
            SELECT pr.*,
                   GROUP_CONCAT(DISTINCT prt.topic_id) as selected_topic_ids,
                   GROUP_CONCAT(DISTINCT prr.resource_id) as selected_resource_ids
            FROM planning_rows pr
            LEFT JOIN planning_row_topics prt ON pr.id = prt.row_id
            LEFT JOIN planning_row_resources prr ON pr.id = prr.row_id
            WHERE pr.planning_id = ?
            GROUP BY pr.id
            ORDER BY pr.sort_order
        ");
        $stmt->execute([$planningId]);
        $planningRows = $stmt->fetchAll();

        // Получаем метки
        $stmt = $pdo->prepare("SELECT label_id FROM planning_labels WHERE planning_id = ?");
        $stmt->execute([$planningId]);
        $selectedLabels = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Получаем историю
        $stmt = $pdo->prepare("
            SELECT ph.*, u.first_name, u.last_name
            FROM planning_history ph
            JOIN users u ON ph.user_id = u.id
            WHERE ph.planning_id = ?
            ORDER BY ph.created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$planningId]);
        $history = $stmt->fetchAll();
    } else {
        header('Location: planning.php');
        exit();
    }
}

// Получение списка планирований с фильтрацией
$filterCategory = $_GET['filter_category'] ?? '';
$filterLabel = $_GET['filter_label'] ?? '';
$filterStudent = $_GET['filter_student'] ?? '';
$filterStatus = $_GET['filter_status'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

$query = "
    SELECT 
        p.*,
        s.last_name as student_last_name,
        s.first_name as student_first_name,
        s.middle_name as student_middle_name,
        s.class as student_class,
        c.name as category_name,
        c.color as category_color,
        (SELECT COUNT(*) FROM planning_rows WHERE planning_id = p.id) as rows_count,
        GROUP_CONCAT(DISTINCT l.name) as labels
    FROM plannings p
    LEFT JOIN students s ON p.student_id = s.id
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN planning_labels pl ON p.id = pl.planning_id
    LEFT JOIN labels l ON pl.label_id = l.id
    WHERE p.user_id = ?
";
$params = [$userId];

if (!empty($filterCategory)) {
    $query .= " AND p.category_id = ?";
    $params[] = $filterCategory;
}

if (!empty($filterStudent)) {
    $query .= " AND p.student_id = ?";
    $params[] = $filterStudent;
}

if (!empty($filterLabel)) {
    $query .= " AND p.id IN (SELECT planning_id FROM planning_labels WHERE label_id = ?)";
    $params[] = $filterLabel;
}

if ($filterStatus === 'active') {
    $query .= " AND p.is_active = 1";
} elseif ($filterStatus === 'inactive') {
    $query .= " AND p.is_active = 0";
} elseif ($filterStatus === 'template') {
    $query .= " AND p.is_template = 1";
}

if (!empty($searchQuery)) {
    $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

$query .= " GROUP BY p.id ORDER BY p.updated_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$plannings = $stmt->fetchAll();


// Определение текущей вкладки
$currentTab = $_GET['tab'] ?? 'info';
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Планирования - Дневник репетитора</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .planning-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border-left: 4px solid;
            position: relative;
        }

        .planning-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        .planning-card.inactive {
            opacity: 0.7;
            background: #f8f9fa;
        }

        .planning-name {
            font-size: 1.2em;
            font-weight: 600;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .planning-student {
            color: #666;
            margin-bottom: 10px;
            font-size: 0.95em;
        }

        .planning-category {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            color: white;
            margin-bottom: 10px;
        }

        .planning-meta {
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }

        .rows-table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .rows-table td {
            vertical-align: middle;
        }

        .row-input {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 5px;
            width: 100%;
        }

        .remove-row {
            color: #dc3545;
            cursor: pointer;
            font-size: 1.2em;
        }

        .remove-row:hover {
            color: #a71d2a;
        }

        .label-badge {
            background: #e9ecef;
            border-radius: 15px;
            padding: 2px 10px;
            font-size: 0.8em;
            display: inline-block;
            margin-right: 5px;
            margin-bottom: 5px;
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
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            margin-bottom: 10px;
        }

        .template-badge {
            background: #ffc107;
            color: #333;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75em;
        }

        .nav-tabs .nav-link.active {
            color: #667eea;
            font-weight: 600;
            border-bottom: 3px solid #667eea;
        }

        .topic-badge {
            background: #e9ecef;
            border-radius: 15px;
            padding: 3px 10px;
            font-size: 0.8em;
            display: inline-block;
            margin-right: 5px;
            margin-bottom: 5px;
            border-left: 3px solid;
            cursor: pointer;
        }

        .topic-badge.selected {
            background: #667eea;
            color: white;
        }

        .resource-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 8px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .resource-item.selected {
            background: #e3f2fd;
            border-left: 3px solid #667eea;
        }

        .selection-panel {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <?php include 'menu.php'; ?>

    <div class="container-fluid py-4">
        <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php
                $messages = [
                    'saved' => 'Планирование успешно сохранено',
                    'deleted' => 'Планирование удалено',
                    'toggled' => 'Статус планирования изменен',
                    'copied' => 'Копия планирования создана',
                    'rows_saved' => 'Строки планирования сохранены'
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

        <?php if ($action === 'list'): ?>
            <!-- Заголовок и кнопки действий -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-calendar-week"></i> Планирования</h2>
                <a href="?action=add" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Создать планирование
                </a>
            </div>

            <!-- Фильтры -->
            <div class="filter-panel">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Поиск</label>
                        <input type="text" name="search" class="form-control"
                            value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Название...">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Категория</label>
                        <select name="filter_category" class="form-select">
                            <option value="">Все категории</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $filterCategory == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Метка</label>
                        <select name="filter_label" class="form-select">
                            <option value="">Все метки</option>
                            <?php foreach ($allLabels as $label): ?>
                                <option value="<?php echo $label['id']; ?>" <?php echo $filterLabel == $label['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Ученик</label>
                        <select name="filter_student" class="form-select">
                            <option value="">Все ученики</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['id']; ?>" <?php echo $filterStudent == $student['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Статус</label>
                        <select name="filter_status" class="form-select">
                            <option value="all" <?php echo $filterStatus == 'all' ? 'selected' : ''; ?>>Все</option>
                            <option value="active" <?php echo $filterStatus == 'active' ? 'selected' : ''; ?>>Активные
                            </option>
                            <option value="inactive" <?php echo $filterStatus == 'inactive' ? 'selected' : ''; ?>>Неактивные
                            </option>
                            <option value="template" <?php echo $filterStatus == 'template' ? 'selected' : ''; ?>>Шаблоны
                            </option>
                        </select>
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Применить
                        </button>
                    </div>
                </form>
            </div>

            <!-- Список планирований -->
            <div class="row">
                <?php if (empty($plannings)): ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center py-5">
                            <i class="bi bi-calendar" style="font-size: 3rem;"></i>
                            <h4 class="mt-3">Планирования не найдены</h4>
                            <p>Создайте первое планирование, нажав кнопку "Создать планирование"</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($plannings as $planning):
                        // Получаем дату последнего запланированного занятия
                        $stmt = $pdo->prepare("
                SELECT lesson_date 
                FROM planning_rows 
                WHERE planning_id = ? AND lesson_date IS NOT NULL 
                ORDER BY lesson_date DESC 
                LIMIT 1
            ");
                        $stmt->execute([$planning['id']]);
                        $lastLesson = $stmt->fetch();
                        $lastLessonDate = $lastLesson ? date('d.m.Y', strtotime($lastLesson['lesson_date'])) : 'не указана';

                        // Получаем ближайшее запланированное занятие (если есть)
                        $stmt = $pdo->prepare("
                SELECT lesson_date 
                FROM planning_rows 
                WHERE planning_id = ? AND lesson_date >= CURDATE() 
                ORDER BY lesson_date ASC 
                LIMIT 1
            ");
                        $stmt->execute([$planning['id']]);
                        $nextLesson = $stmt->fetch();
                        $nextLessonDate = $nextLesson ? date('d.m.Y', strtotime($nextLesson['lesson_date'])) : null;
                        ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="planning-card <?php echo !$planning['is_active'] ? 'inactive' : ''; ?>"
                                style="border-left-color: <?php echo $planning['category_color'] ?? '#808080'; ?>">

                                <div class="planning-name">
                                    <?php echo htmlspecialchars($planning['name']); ?>
                                    <?php if ($planning['is_template']): ?>
                                        <span class="template-badge">Шаблон</span>
                                    <?php endif; ?>
                                </div>

                                <div class="planning-student">
                                    <i class="bi bi-person"></i>
                                    <?php if ($planning['student_id']): ?>
                                        <?php echo htmlspecialchars($planning['student_last_name'] . ' ' . $planning['student_first_name']); ?>
                                        <?php if ($planning['student_class']): ?>
                                            <small>(
                                                <?php echo htmlspecialchars($planning['student_class']); ?> класс)
                                            </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Не назначено</span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($planning['category_name']): ?>
                                    <div class="planning-category"
                                        style="background: <?php echo $planning['category_color'] ?? '#808080'; ?>">
                                        <?php echo htmlspecialchars($planning['category_name']); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($planning['description'])): ?>
                                    <div class="small text-muted mb-2">
                                        <?php echo nl2br(htmlspecialchars(substr($planning['description'], 0, 100) . (strlen($planning['description']) > 100 ? '...' : ''))); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($planning['labels'])): ?>
                                    <div class="mb-2">
                                        <?php
                                        $labels = explode(',', $planning['labels']);
                                        foreach ($labels as $label):
                                            if (trim($label)):
                                                ?>
                                                <span class="label-badge">
                                                    <?php echo htmlspecialchars(trim($label)); ?>
                                                </span>
                                            <?php
                                            endif;
                                        endforeach;
                                        ?>
                                    </div>
                                <?php endif; ?>

                                <div class="planning-meta">
                                    <span title="Количество занятий">
                                        <i class="bi bi-list-check"></i>
                                        <?php echo $planning['rows_count']; ?> занятий
                                    </span>
                                    <span title="Последнее занятие">
                                        <i class="bi bi-calendar-check"></i>
                                        <?php echo $lastLessonDate; ?>
                                    </span>
                                    <span title="Последнее обновление">
                                        <i class="bi bi-clock"></i>
                                        <?php echo date('d.m.Y', strtotime($planning['updated_at'])); ?>
                                    </span>
                                </div>

                                <?php if ($nextLessonDate): ?>
                                    <div class="mt-2">
                                        <span class="badge bg-success">
                                            <i class="bi bi-calendar-week"></i> Ближайшее:
                                            <?php echo $nextLessonDate; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <div class="mt-3 d-flex justify-content-end gap-2">
                                    <a href="?action=view&id=<?php echo $planning['id']; ?>" class="btn btn-sm btn-outline-primary"
                                        title="Просмотр">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="?action=edit&id=<?php echo $planning['id']; ?>"
                                        class="btn btn-sm btn-outline-secondary" title="Редактировать">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="?copy=1&id=<?php echo $planning['id']; ?>" class="btn btn-sm btn-outline-info"
                                        title="Создать копию">
                                        <i class="bi bi-files"></i>
                                    </a>
                                    <a href="?toggle_active=1&id=<?php echo $planning['id']; ?>"
                                        class="btn btn-sm btn-outline-warning" title="Скрыть/Показать">
                                        <i class="bi bi-eye<?php echo $planning['is_active'] ? '' : '-slash'; ?>"></i>
                                    </a>
                                    <a href="?delete=1&id=<?php echo $planning['id']; ?>" class="btn btn-sm btn-outline-danger"
                                        title="Удалить" onclick="return confirm('Удалить планирование?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <!-- Форма редактирования планирования -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="bi bi-<?php echo $action === 'add' ? 'plus-circle' : 'pencil'; ?>"></i>
                                <?php echo $action === 'add' ? 'Создание планирования' : 'Редактирование планирования'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <ul class="nav nav-tabs mb-3">
                                <li class="nav-item">
                                    <a class="nav-link <?php echo $currentTab == 'info' ? 'active' : ''; ?>"
                                        href="?action=<?php echo $action; ?>&id=<?php echo $planningId; ?>&tab=info">
                                        Основная информация
                                    </a>
                                </li>
                                <?php if ($action === 'edit'): ?>
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo $currentTab == 'rows' ? 'active' : ''; ?>"
                                            href="?action=edit&id=<?php echo $planningId; ?>&tab=rows">
                                            Строки планирования
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo $currentTab == 'history' ? 'active' : ''; ?>"
                                            href="?action=edit&id=<?php echo $planningId; ?>&tab=history">
                                            История
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>

                            <?php if ($currentTab == 'info'): ?>
                                <!-- Форма основной информации -->
                                <form method="POST" action="">
                                    <?php if ($action === 'edit' && $editPlanning): ?>
                                        <input type="hidden" name="planning_id" value="<?php echo $editPlanning['id']; ?>">
                                    <?php endif; ?>

                                    <div class="mb-3">
                                        <label class="form-label">Название планирования *</label>
                                        <input type="text" name="name" class="form-control"
                                            value="<?php echo $editPlanning ? htmlspecialchars($editPlanning['name']) : ''; ?>"
                                            required maxlength="255">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Описание</label>
                                        <textarea name="description" class="form-control"
                                            rows="3"><?php echo $editPlanning ? htmlspecialchars($editPlanning['description'] ?? '') : ''; ?></textarea>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Ученик</label>
                                            <select name="student_id" class="form-select">
                                                <option value="">Не назначать</option>
                                                <?php foreach ($students as $student): ?>
                                                    <option value="<?php echo $student['id']; ?>" <?php echo ($editPlanning && $editPlanning['student_id'] == $student['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? '')); ?>
                                                        <?php if ($student['class']): ?>(<?php echo htmlspecialchars($student['class']); ?>
                                                            класс)<?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Категория</label>
                                            <select name="category_id" class="form-select">
                                                <option value="">Без категории</option>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?php echo $category['id']; ?>" <?php echo ($editPlanning && $editPlanning['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($category['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Метки</label>
                                        <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                                            <?php if (empty($allLabels)): ?>
                                                <p class="text-muted">Нет доступных меток. Создайте метки в банке меток.</p>
                                            <?php else: ?>
                                                <?php foreach ($allLabels as $label): ?>
                                                    <div class="form-check">
                                                        <input type="checkbox" name="labels[]" value="<?php echo $label['id']; ?>"
                                                            class="form-check-input" id="label_<?php echo $label['id']; ?>" <?php echo (in_array($label['id'], $selectedLabels)) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="label_<?php echo $label['id']; ?>">
                                                            <?php echo htmlspecialchars($label['name']); ?>
                                                            <?php if ($label['category_name']): ?>
                                                                <small
                                                                    class="text-muted">(<?php echo htmlspecialchars($label['category_name']); ?>)</small>
                                                            <?php endif; ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="mb-3 form-check">
                                        <input type="checkbox" name="is_template" class="form-check-input" id="isTemplate" <?php echo ($editPlanning && $editPlanning['is_template']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="isTemplate">Это шаблон</label>
                                        <small class="text-muted d-block">Шаблоны можно использовать для создания новых
                                            планирований</small>
                                    </div>

                                    <div class="d-flex justify-content-between">
                                        <a href="planning.php" class="btn btn-outline-secondary">
                                            <i class="bi bi-arrow-left"></i> Назад
                                        </a>
                                        <button type="submit" name="save_planning" class="btn btn-primary">
                                            <i class="bi bi-save"></i> Сохранить
                                        </button>
                                    </div>
                                </form>

                            <?php elseif ($currentTab == 'rows' && $action === 'edit'): ?>
                                <!-- Форма строк планирования с выбором тем и ресурсов -->
                                <form method="POST" action="" id="rowsForm">
                                    <div class="mb-3">
                                        <button type="button" class="btn btn-success" onclick="addRow()">
                                            <i class="bi bi-plus-circle"></i> Добавить строку
                                        </button>
                                        <button type="button" class="btn btn-warning" onclick="exportToCSV()">
                                            <i class="bi bi-filetype-csv"></i> Экспорт в CSV
                                        </button>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-bordered rows-table" id="rowsTable">
                                            <thead>
                                                <tr>
                                                    <th style="width: 5%">№</th>
                                                    <th style="width: 10%">Дата</th>
                                                    <th style="width: 25%">Темы</th>
                                                    <th style="width: 25%">Ресурсы</th>
                                                    <th style="width: 20%">Домашнее задание</th>
                                                    <th style="width: 10%">Примечание</th>
                                                    <th style="width: 5%"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="rowsBody">
                                                <?php if (!empty($planningRows)): ?>
                                                    <?php foreach ($planningRows as $index => $row):
                                                        $selectedTopicIds = $row['selected_topic_ids'] ? explode(',', $row['selected_topic_ids']) : [];
                                                        $selectedResourceIds = $row['selected_resource_ids'] ? explode(',', $row['selected_resource_ids']) : [];
                                                        ?>
                                                        <tr class="planning-row" data-index="<?php echo $index; ?>">
                                                            <td>
                                                                <input type="number" name="rows[<?php echo $index; ?>][lesson_number]"
                                                                    class="form-control form-control-sm"
                                                                    value="<?php echo $row['lesson_number']; ?>" min="1">
                                                            </td>
                                                            <td>
                                                                <input type="date" name="rows[<?php echo $index; ?>][lesson_date]"
                                                                    class="form-control form-control-sm"
                                                                    value="<?php echo $row['lesson_date']; ?>">
                                                            </td>
                                                            <td>
                                                                <div class="mb-2">
                                                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                                                        onclick="openTopicsModal(<?php echo $index; ?>)">
                                                                        <i class="bi bi-plus-circle"></i> Выбрать темы
                                                                    </button>
                                                                </div>
                                                                <div id="topics-container-<?php echo $index; ?>" class="mb-2">
                                                                    <?php foreach ($selectedTopicIds as $topicId): ?>
                                                                        <?php if (!empty($topicId)): ?>
                                                                            <?php
                                                                            $topic = array_filter($allTopics, function ($t) use ($topicId) {
                                                                                return $t['id'] == $topicId;
                                                                            });
                                                                            $topic = reset($topic);
                                                                            if ($topic):
                                                                                ?>
                                                                                <span class="topic-badge"
                                                                                    style="border-left-color: <?php echo $topic['category_color'] ?? '#808080'; ?>">
                                                                                    <?php echo htmlspecialchars($topic['name']); ?>
                                                                                    <input type="hidden"
                                                                                        name="rows[<?php echo $index; ?>][topics][]"
                                                                                        value="<?php echo $topicId; ?>">
                                                                                    <i class="bi bi-x-circle-fill ms-1" style="cursor: pointer;"
                                                                                        onclick="removeTopic(this, <?php echo $index; ?>, <?php echo $topicId; ?>)"></i>
                                                                                </span>
                                                                            <?php endif; ?>
                                                                        <?php endif; ?>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                                <textarea name="rows[<?php echo $index; ?>][topics_text]"
                                                                    class="form-control form-control-sm"
                                                                    placeholder="Или введите вручную..."><?php echo htmlspecialchars($row['topics_text']); ?></textarea>
                                                            </td>
                                                            <td>
                                                                <div class="mb-2">
                                                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                                                        onclick="openResourcesModal(<?php echo $index; ?>)">
                                                                        <i class="bi bi-plus-circle"></i> Выбрать ресурсы
                                                                    </button>
                                                                </div>
                                                                <div id="resources-container-<?php echo $index; ?>" class="mb-2">
                                                                    <?php foreach ($selectedResourceIds as $resourceId): ?>
                                                                        <?php if (!empty($resourceId)): ?>
                                                                            <?php
                                                                            $resource = array_filter($allResources, function ($r) use ($resourceId) {
                                                                                return $r['id'] == $resourceId;
                                                                            });
                                                                            $resource = reset($resource);
                                                                            if ($resource):
                                                                                ?>
                                                                                <div class="resource-item selected">
                                                                                    <span>
                                                                                        <i class="bi bi-link"></i>
                                                                                        <?php echo htmlspecialchars($resource['description'] ?: substr($resource['url'], 0, 30)); ?>
                                                                                    </span>
                                                                                    <input type="hidden"
                                                                                        name="rows[<?php echo $index; ?>][resources][]"
                                                                                        value="<?php echo $resourceId; ?>">
                                                                                    <i class="bi bi-x-circle-fill" style="cursor: pointer;"
                                                                                        onclick="removeResource(this, <?php echo $index; ?>, <?php echo $resourceId; ?>)"></i>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                        <?php endif; ?>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                                <textarea name="rows[<?php echo $index; ?>][resources_text]"
                                                                    class="form-control form-control-sm"
                                                                    placeholder="Или введите вручную..."><?php echo htmlspecialchars($row['resources_text']); ?></textarea>
                                                            </td>
                                                            <td>
                                                                <textarea name="rows[<?php echo $index; ?>][homework]"
                                                                    class="form-control form-control-sm"
                                                                    rows="2"><?php echo htmlspecialchars($row['homework']); ?></textarea>
                                                            </td>
                                                            <td>
                                                                <input type="text" name="rows[<?php echo $index; ?>][notes]"
                                                                    class="form-control form-control-sm"
                                                                    value="<?php echo htmlspecialchars($row['notes']); ?>">
                                                            </td>
                                                            <td>
                                                                <i class="bi bi-x-circle-fill remove-row" onclick="removeRow(this)"></i>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr class="planning-row" data-index="0">
                                                        <td><input type="number" name="rows[0][lesson_number]"
                                                                class="form-control form-control-sm" value="1" min="1"></td>
                                                        <td><input type="date" name="rows[0][lesson_date]"
                                                                class="form-control form-control-sm"></td>
                                                        <td>
                                                            <div class="mb-2">
                                                                <button type="button" class="btn btn-sm btn-outline-primary"
                                                                    onclick="openTopicsModal(0)">
                                                                    <i class="bi bi-plus-circle"></i> Выбрать темы
                                                                </button>
                                                            </div>
                                                            <div id="topics-container-0" class="mb-2"></div>
                                                            <textarea name="rows[0][topics_text]"
                                                                class="form-control form-control-sm"
                                                                placeholder="Или введите вручную..."></textarea>
                                                        </td>
                                                        <td>
                                                            <div class="mb-2">
                                                                <button type="button" class="btn btn-sm btn-outline-primary"
                                                                    onclick="openResourcesModal(0)">
                                                                    <i class="bi bi-plus-circle"></i> Выбрать ресурсы
                                                                </button>
                                                            </div>
                                                            <div id="resources-container-0" class="mb-2"></div>
                                                            <textarea name="rows[0][resources_text]"
                                                                class="form-control form-control-sm"
                                                                placeholder="Или введите вручную..."></textarea>
                                                        </td>
                                                        <td><textarea name="rows[0][homework]" class="form-control form-control-sm"
                                                                rows="2"></textarea></td>
                                                        <td><input type="text" name="rows[0][notes]"
                                                                class="form-control form-control-sm"></td>
                                                        <td><i class="bi bi-x-circle-fill remove-row" onclick="removeRow(this)"></i>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="d-flex justify-content-between mt-3">
                                        <a href="planning.php" class="btn btn-outline-secondary">
                                            <i class="bi bi-arrow-left"></i> Назад
                                        </a>
                                        <button type="submit" name="save_rows" class="btn btn-primary">
                                            <i class="bi bi-save"></i> Сохранить строки
                                        </button>
                                    </div>
                                </form>

                            <?php elseif ($currentTab == 'history' && $action === 'edit'): ?>
                                <!-- История изменений -->
                                <div class="card">
                                    <div class="card-body">
                                        <?php if (empty($history)): ?>
                                            <p class="text-muted text-center py-3">История изменений пуста</p>
                                        <?php else: ?>
                                            <?php foreach ($history as $record): ?>
                                                <div class="border-bottom py-2">
                                                    <div class="d-flex justify-content-between">
                                                        <span>
                                                            <strong><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></strong>
                                                            <?php
                                                            $actions = [
                                                                'create' => 'создал(а) планирование',
                                                                'update' => 'обновил(а) информацию',
                                                                'update_rows' => 'обновил(а) строки',
                                                                'toggle_active' => 'изменил(а) статус',
                                                                'copy' => 'создал(а) копию',
                                                                'delete' => 'удалил(а) планирование'
                                                            ];
                                                            echo $actions[$record['action']] ?? $record['action'];
                                                            ?>
                                                        </span>
                                                        <small
                                                            class="text-muted"><?php echo date('d.m.Y H:i', strtotime($record['created_at'])); ?></small>
                                                    </div>
                                                    <?php if (!empty($record['details'])): ?>
                                                        <small
                                                            class="text-muted"><?php echo htmlspecialchars($record['details']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Информационный блок -->
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-info-circle"></i> Информация</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>О планированиях:</strong></p>
                            <ul class="small">
                                <li>Планирование можно назначить конкретному ученику</li>
                                <li>Шаблоны не привязаны к ученикам</li>
                                <li>Можно добавлять неограниченное количество строк</li>
                                <li>Дата необязательна для заполнения</li>
                                <li>Используйте экспорт в CSV для печати</li>
                                <li>Темы и ресурсы можно выбирать из банков</li>
                            </ul>

                            <?php if ($action === 'edit' && $editPlanning): ?>
                                <hr>
                                <p><strong>Статистика:</strong></p>
                                <p>Строк в плане: <?php echo count($planningRows); ?></p>
                                <p>Создан: <?php echo date('d.m.Y H:i', strtotime($editPlanning['created_at'])); ?></p>
                                <p>Обновлен: <?php echo date('d.m.Y H:i', strtotime($editPlanning['updated_at'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($action === 'view' && $editPlanning): ?>
            <!-- Просмотр планирования -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="bi bi-calendar-week"></i>
                    <?php echo htmlspecialchars($editPlanning['name']); ?>
                    <?php if (!$editPlanning['is_active']): ?>
                        <span class="badge bg-secondary">Скрыто</span>
                    <?php endif; ?>
                    <?php if ($editPlanning['is_template']): ?>
                        <span class="badge bg-warning">Шаблон</span>
                    <?php endif; ?>
                </h2>
                <div>
                    <a href="?export_csv=1&id=<?php echo $editPlanning['id']; ?>" class="btn btn-success me-2">
                        <i class="bi bi-filetype-csv"></i> Экспорт CSV
                    </a>
                    <a href="?action=edit&id=<?php echo $editPlanning['id']; ?>" class="btn btn-primary me-2">
                        <i class="bi bi-pencil"></i> Редактировать
                    </a>
                    <a href="planning.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Назад
                    </a>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <!-- Таблица с планом -->
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">План занятий</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($planningRows)): ?>
                                <p class="text-muted text-center py-3">Нет запланированных занятий</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>№</th>
                                                <th>Дата</th>
                                                <th>Темы</th>
                                                <th>Ресурсы</th>
                                                <th>Домашнее задание</th>
                                                <th>Примечание</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($planningRows as $row):
                                                // Получаем имена выбранных тем
                                                $topicNames = [];
                                                if (!empty($row['selected_topic_ids'])) {
                                                    $topicIds = explode(',', $row['selected_topic_ids']);
                                                    foreach ($topicIds as $topicId) {
                                                        foreach ($allTopics as $topic) {
                                                            if ($topic['id'] == $topicId) {
                                                                $topicNames[] = $topic['name'];
                                                                break;
                                                            }
                                                        }
                                                    }
                                                }

                                                // Получаем имена выбранных ресурсов
                                                $resourceNames = [];
                                                if (!empty($row['selected_resource_ids'])) {
                                                    $resourceIds = explode(',', $row['selected_resource_ids']);
                                                    foreach ($resourceIds as $resourceId) {
                                                        foreach ($allResources as $resource) {
                                                            if ($resource['id'] == $resourceId) {
                                                                $resourceNames[] = $resource['description'] ?: $resource['url'];
                                                                break;
                                                            }
                                                        }
                                                    }
                                                }
                                                ?>
                                                <tr>
                                                    <td class="text-center"><?php echo $row['lesson_number']; ?></td>
                                                    <td><?php echo $row['lesson_date'] ? date('d.m.Y', strtotime($row['lesson_date'])) : ''; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($topicNames)): ?>
                                                            <?php foreach ($topicNames as $topicName): ?>
                                                                <span class="topic-badge"><?php echo htmlspecialchars($topicName); ?></span>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                        <?php if (!empty($row['topics_text'])): ?>
                                                            <div><?php echo nl2br(htmlspecialchars($row['topics_text'])); ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($resourceNames)): ?>
                                                            <?php foreach ($resourceNames as $resourceName): ?>
                                                                <div class="small"><?php echo htmlspecialchars($resourceName); ?></div>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                        <?php if (!empty($row['resources_text'])): ?>
                                                            <div><?php echo nl2br(htmlspecialchars($row['resources_text'])); ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo nl2br(htmlspecialchars($row['homework'])); ?></td>
                                                    <td><?php echo htmlspecialchars($row['notes']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Информация о планировании -->
                    <div class="card mb-3">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Информация</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Ученик:</strong><br>
                                <?php if ($editPlanning['student_id']): ?>
                                    <?php echo htmlspecialchars($editPlanning['last_name'] . ' ' . $editPlanning['first_name'] . ' ' . ($editPlanning['middle_name'] ?? '')); ?>
                                    <?php if ($editPlanning['class']): ?>(<?php echo htmlspecialchars($editPlanning['class']); ?>
                                        класс)<?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">Не назначен</span>
                                <?php endif; ?>
                            </p>

                            <?php if ($editPlanning['category_name']): ?>
                                <p><strong>Категория:</strong><br>
                                    <span class="badge"
                                        style="background: <?php echo $editPlanning['category_color'] ?? '#808080'; ?>; color: white;">
                                        <?php echo htmlspecialchars($editPlanning['category_name']); ?>
                                    </span>
                                </p>
                            <?php endif; ?>

                            <?php if (!empty($selectedLabels)): ?>
                                <p><strong>Метки:</strong><br>
                                    <?php foreach ($selectedLabels as $labelId): ?>
                                        <?php
                                        $label = array_filter($allLabels, function ($l) use ($labelId) {
                                            return $l['id'] == $labelId;
                                        });
                                        $label = reset($label);
                                        if ($label):
                                            ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($label['name']); ?></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </p>
                            <?php endif; ?>

                            <?php if (!empty($editPlanning['description'])): ?>
                                <p><strong>Описание:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($editPlanning['description'])); ?>
                                </p>
                            <?php endif; ?>

                            <p><small class="text-muted">Создано:
                                    <?php echo date('d.m.Y H:i', strtotime($editPlanning['created_at'])); ?></small></p>
                            <p><small class="text-muted">Обновлено:
                                    <?php echo date('d.m.Y H:i', strtotime($editPlanning['updated_at'])); ?></small></p>
                        </div>
                    </div>

                    <!-- Действия -->
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Действия</h5>
                        </div>
                        <div class="card-body">
                            <a href="?copy=1&id=<?php echo $editPlanning['id']; ?>" class="btn btn-outline-info w-100 mb-2">
                                <i class="bi bi-files"></i> Создать копию
                            </a>
                            <a href="?toggle_active=1&id=<?php echo $editPlanning['id']; ?>"
                                class="btn btn-outline-warning w-100 mb-2">
                                <i class="bi bi-eye<?php echo $editPlanning['is_active'] ? '-slash' : ''; ?>"></i>
                                <?php echo $editPlanning['is_active'] ? 'Скрыть' : 'Показать'; ?>
                            </a>
                            <?php if ($editPlanning['student_id']): ?>
                                <?php
                                $stmt = $pdo->prepare("SELECT id FROM diaries WHERE student_id = ? LIMIT 1");
                                $stmt->execute([$editPlanning['student_id']]);
                                $diary = $stmt->fetch();
                                if ($diary):
                                    ?>
                                    <a href="lessons.php?diary_id=<?php echo $diary['id']; ?>"
                                        class="btn btn-outline-success w-100 mb-2">
                                        <i class="bi bi-calendar-check"></i> Перейти к занятиям
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
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
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="topicSearch" placeholder="Поиск по названию...">
                        </div>
                        <div class="col-md-6">
                            <select class="form-select" id="topicCategoryFilter">
                                <option value="">Все категории</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="selection-panel" id="topicsList">
                        <?php foreach ($allTopics as $topic): ?>
                            <div class="form-check topic-item" data-id="<?php echo $topic['id']; ?>"
                                data-name="<?php echo strtolower($topic['name']); ?>"
                                data-category="<?php echo $topic['category_id']; ?>">
                                <input type="checkbox" class="form-check-input topic-checkbox"
                                    value="<?php echo $topic['id']; ?>" id="modal_topic_<?php echo $topic['id']; ?>">
                                <label class="form-check-label" for="modal_topic_<?php echo $topic['id']; ?>">
                                    <?php echo htmlspecialchars($topic['name']); ?>
                                    <?php if ($topic['category_name']): ?>
                                        <small
                                            class="text-muted">(<?php echo htmlspecialchars($topic['category_name']); ?>)</small>
                                    <?php endif; ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-primary" onclick="applyTopicsSelection()">Применить</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно выбора ресурсов -->
    <div class="modal fade" id="resourcesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Выбор ресурсов</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="resourceSearch"
                                placeholder="Поиск по описанию...">
                        </div>
                        <div class="col-md-6">
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

                    <div class="selection-panel" id="resourcesList">
                        <?php foreach ($allResources as $resource):
                            $typeIcon = 'bi-file-earmark';
                            if ($resource['type'] === 'page')
                                $typeIcon = 'bi-file-earmark-text';
                            elseif ($resource['type'] === 'document')
                                $typeIcon = 'bi-file-earmark-pdf';
                            elseif ($resource['type'] === 'video')
                                $typeIcon = 'bi-camera-reels';
                            elseif ($resource['type'] === 'audio')
                                $typeIcon = 'bi-mic';
                            ?>
                            <div class="form-check resource-item" data-id="<?php echo $resource['id']; ?>"
                                data-name="<?php echo strtolower($resource['description'] ?: $resource['url']); ?>"
                                data-type="<?php echo $resource['type']; ?>">
                                <input type="checkbox" class="form-check-input resource-checkbox"
                                    value="<?php echo $resource['id']; ?>"
                                    id="modal_resource_<?php echo $resource['id']; ?>">
                                <label class="form-check-label" for="modal_resource_<?php echo $resource['id']; ?>">
                                    <i class="bi <?php echo $typeIcon; ?> me-1"></i>
                                    <?php echo htmlspecialchars($resource['description'] ?: substr($resource['url'], 0, 50)); ?>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($resource['url']); ?></small>
                                    <?php if ($resource['category_name']): ?>
                                        <br>
                                        <small class="text-muted">Категория:
                                            <?php echo htmlspecialchars($resource['category_name']); ?></small>
                                    <?php endif; ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-primary" onclick="applyResourcesSelection()">Применить</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Быстрые действия -->
    <div class="quick-actions">
        <a href="?action=add" class="btn btn-primary" title="Создать планирование">
            <i class="bi bi-plus"></i>
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentRowIndex = 0;
        let rowIndex = <?php echo !empty($planningRows) ? count($planningRows) : 1; ?>;

        // Функции для работы со строками
        function addRow() {
            const tbody = document.getElementById('rowsBody');
            const newRow = document.createElement('tr');
            newRow.className = 'planning-row';
            newRow.dataset.index = rowIndex;
            newRow.innerHTML = `
                <td><input type="number" name="rows[${rowIndex}][lesson_number]" class="form-control form-control-sm" value="${rowIndex + 1}" min="1"></td>
                <td><input type="date" name="rows[${rowIndex}][lesson_date]" class="form-control form-control-sm"></td>
                <td>
                    <div class="mb-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="openTopicsModal(${rowIndex})">
                            <i class="bi bi-plus-circle"></i> Выбрать темы
                        </button>
                    </div>
                    <div id="topics-container-${rowIndex}" class="mb-2"></div>
                    <textarea name="rows[${rowIndex}][topics_text]" class="form-control form-control-sm" placeholder="Или введите вручную..."></textarea>
                </td>
                <td>
                    <div class="mb-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="openResourcesModal(${rowIndex})">
                            <i class="bi bi-plus-circle"></i> Выбрать ресурсы
                        </button>
                    </div>
                    <div id="resources-container-${rowIndex}" class="mb-2"></div>
                    <textarea name="rows[${rowIndex}][resources_text]" class="form-control form-control-sm" placeholder="Или введите вручную..."></textarea>
                </td>
                <td><textarea name="rows[${rowIndex}][homework]" class="form-control form-control-sm" rows="2"></textarea></td>
                <td><input type="text" name="rows[${rowIndex}][notes]" class="form-control form-control-sm"></td>
                <td><i class="bi bi-x-circle-fill remove-row" onclick="removeRow(this)"></i></td>
            `;
            tbody.appendChild(newRow);
            rowIndex++;
        }

        function removeRow(element) {
            if (confirm('Удалить строку?')) {
                const row = element.closest('tr');
                row.remove();
            }
        }

        // Функции для работы с темами
        function openTopicsModal(rowIdx) {
            currentRowIndex = rowIdx;
            const modal = new bootstrap.Modal(document.getElementById('topicsModal'));

            // Сбрасываем фильтры
            document.getElementById('topicSearch').value = '';
            document.getElementById('topicCategoryFilter').value = '';

            // Отмечаем уже выбранные темы
            const container = document.getElementById(`topics-container-${rowIdx}`);
            const selectedInputs = container.querySelectorAll('input[name^="rows"][name$="[topics][]"]');
            const selectedIds = Array.from(selectedInputs).map(input => input.value);

            document.querySelectorAll('#topicsList .topic-checkbox').forEach(cb => {
                cb.checked = selectedIds.includes(cb.value);
            });

            modal.show();
        }

        function applyTopicsSelection() {
            const container = document.getElementById(`topics-container-${currentRowIndex}`);
            container.innerHTML = '';

            document.querySelectorAll('#topicsList .topic-checkbox:checked').forEach(cb => {
                const topicId = cb.value;
                const topicDiv = cb.closest('.topic-item');
                const topicName = topicDiv.querySelector('label').innerText.split('(')[0].trim();
                const categoryColor = '<?php echo isset($topic["category_color"]) ? $topic["category_color"] : "#808080"; ?>';

                const span = document.createElement('span');
                span.className = 'topic-badge';
                span.style.borderLeftColor = categoryColor;
                span.innerHTML = `
                    ${topicName}
                    <input type="hidden" name="rows[${currentRowIndex}][topics][]" value="${topicId}">
                    <i class="bi bi-x-circle-fill ms-1" style="cursor: pointer;" onclick="removeTopic(this, ${currentRowIndex}, ${topicId})"></i>
                `;
                container.appendChild(span);
            });

            bootstrap.Modal.getInstance(document.getElementById('topicsModal')).hide();
        }

        function removeTopic(element, rowIdx, topicId) {
            element.closest('.topic-badge').remove();
        }

        // Функции для работы с ресурсами
        function openResourcesModal(rowIdx) {
            currentRowIndex = rowIdx;
            const modal = new bootstrap.Modal(document.getElementById('resourcesModal'));

            // Сбрасываем фильтры
            document.getElementById('resourceSearch').value = '';
            document.getElementById('resourceTypeFilter').value = '';

            // Отмечаем уже выбранные ресурсы
            const container = document.getElementById(`resources-container-${rowIdx}`);
            const selectedInputs = container.querySelectorAll('input[name^="rows"][name$="[resources][]"]');
            const selectedIds = Array.from(selectedInputs).map(input => input.value);

            document.querySelectorAll('#resourcesList .resource-checkbox').forEach(cb => {
                cb.checked = selectedIds.includes(cb.value);
            });

            modal.show();
        }

        function applyResourcesSelection() {
            const container = document.getElementById(`resources-container-${currentRowIndex}`);
            container.innerHTML = '';

            document.querySelectorAll('#resourcesList .resource-checkbox:checked').forEach(cb => {
                const resourceId = cb.value;
                const resourceDiv = cb.closest('.resource-item');
                const label = resourceDiv.querySelector('label').innerText;
                const description = label.split('\n')[0].trim();

                const div = document.createElement('div');
                div.className = 'resource-item selected';
                div.innerHTML = `
                    <span>
                        <i class="bi bi-link"></i>
                        ${description}
                    </span>
                    <input type="hidden" name="rows[${currentRowIndex}][resources][]" value="${resourceId}">
                    <i class="bi bi-x-circle-fill" style="cursor: pointer;" onclick="removeResource(this, ${currentRowIndex}, ${resourceId})"></i>
                `;
                container.appendChild(div);
            });

            bootstrap.Modal.getInstance(document.getElementById('resourcesModal')).hide();
        }

        function removeResource(element, rowIdx, resourceId) {
            element.closest('.resource-item').remove();
        }

        // Фильтрация тем
        document.getElementById('topicSearch')?.addEventListener('input', filterTopics);
        document.getElementById('topicCategoryFilter')?.addEventListener('change', filterTopics);

        function filterTopics() {
            const search = document.getElementById('topicSearch').value.toLowerCase();
            const category = document.getElementById('topicCategoryFilter').value;

            document.querySelectorAll('#topicsList .topic-item').forEach(item => {
                let show = true;

                if (search && !item.dataset.name.includes(search)) {
                    show = false;
                }

                if (category && item.dataset.category != category) {
                    show = false;
                }

                item.style.display = show ? 'block' : 'none';
            });
        }

        // Фильтрация ресурсов
        document.getElementById('resourceSearch')?.addEventListener('input', filterResources);
        document.getElementById('resourceTypeFilter')?.addEventListener('change', filterResources);

        function filterResources() {
            const search = document.getElementById('resourceSearch').value.toLowerCase();
            const type = document.getElementById('resourceTypeFilter').value;

            document.querySelectorAll('#resourcesList .resource-item').forEach(item => {
                let show = true;

                if (search && !item.dataset.name.includes(search)) {
                    show = false;
                }

                if (type && item.dataset.type != type) {
                    show = false;
                }

                item.style.display = show ? 'block' : 'none';
            });
        }

        // Экспорт в CSV
        function exportToCSV() {
            const rows = [];
            const headers = ['Номер занятия', 'Дата', 'Темы', 'Ресурсы', 'Домашнее задание', 'Примечание'];
            rows.push(headers.join(';'));

            document.querySelectorAll('#rowsBody tr').forEach(row => {
                const rowData = [];

                // Номер занятия
                const lessonNumber = row.querySelector('input[name^="rows"][name$="[lesson_number]"]')?.value || '';
                rowData.push(lessonNumber);

                // Дата
                const lessonDate = row.querySelector('input[name^="rows"][name$="[lesson_date]"]')?.value || '';
                if (lessonDate) {
                    const date = new Date(lessonDate);
                    rowData.push(date.toLocaleDateString('ru-RU'));
                } else {
                    rowData.push('');
                }

                // Темы (выбранные + текст)
                const topicsContainer = row.querySelector('[id^="topics-container-"]');
                const selectedTopics = topicsContainer ? Array.from(topicsContainer.querySelectorAll('input')).map(inp => inp.closest('.topic-badge')?.innerText.trim() || '').filter(t => t).join(', ') : '';
                const topicsText = row.querySelector('textarea[name^="rows"][name$="[topics_text]"]')?.value || '';
                rowData.push([selectedTopics, topicsText].filter(Boolean).join('; '));

                // Ресурсы (выбранные + текст)
                const resourcesContainer = row.querySelector('[id^="resources-container-"]');
                const selectedResources = resourcesContainer ? Array.from(resourcesContainer.querySelectorAll('.resource-item span')).map(span => span.innerText.trim()).join(', ') : '';
                const resourcesText = row.querySelector('textarea[name^="rows"][name$="[resources_text]"]')?.value || '';
                rowData.push([selectedResources, resourcesText].filter(Boolean).join('; '));

                // ДЗ
                rowData.push(row.querySelector('textarea[name^="rows"][name$="[homework]"]')?.value || '');

                // Примечание
                rowData.push(row.querySelector('input[name^="rows"][name$="[notes]"]')?.value || '');

                rows.push(rowData.map(cell => cell.replace(/;/g, ',')).join(';'));
            });

            const csvContent = rows.join('\n');
            const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'planning_export.csv';
            link.click();
        }
    </script>
</body>

</html>