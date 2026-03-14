<?php
require_once 'config.php';
requireAuth();

$currentUser = getCurrentUser($pdo);
$userId = $currentUser['id'];
$message = '';
$error = '';

// Обработка действий
$action = $_GET['action'] ?? 'list';
$resourceId = $_GET['id'] ?? 0;

// Получение категорий для фильтрации
$stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? OR user_id IS NULL ORDER BY name");
$stmt->execute([$userId]);
$categories = $stmt->fetchAll();

// Получение всех меток для ресурсов с группировкой по категориям
$stmt = $pdo->prepare("
    SELECT l.*, c.name as category_name, c.color as category_color 
    FROM labels l
    LEFT JOIN categories c ON l.category_id = c.id
    WHERE l.user_id = ? AND (l.label_type = 'resource' OR l.label_type = 'general')
    ORDER BY c.name, l.name
");
$stmt->execute([$userId]);
$allLabels = $stmt->fetchAll();

// Группировка меток по категориям для удобного отображения
$groupedLabels = [];
foreach ($allLabels as $label) {
    $catName = $label['category_name'] ?? 'Без категории';
    if (!isset($groupedLabels[$catName])) {
        $groupedLabels[$catName] = [
            'color' => $label['category_color'] ?? '#808080',
            'labels' => []
        ];
    }
    $groupedLabels[$catName]['labels'][] = $label;
}

// Типы ресурсов
$resourceTypes = [
    'page' => 'Страница',
    'document' => 'Документ',
    'video' => 'Видео',
    'audio' => 'Звук',
    'other' => 'Другое'
];

// Очистка меток ресурса
if (isset($_GET['clear_labels']) && $resourceId) {
    $stmt = $pdo->prepare("UPDATE resources SET labels = NULL WHERE id = ? AND user_id = ?");
    $stmt->execute([$resourceId, $userId]);
    
    // Также удаляем связи с метками
    $stmt = $pdo->prepare("DELETE FROM resource_labels WHERE resource_id = ?");
    $stmt->execute([$resourceId]);
    
    header('Location: resources.php?message=labels_cleared');
    exit();
}

// Удаление ресурса
if (isset($_GET['delete']) && $resourceId) {
    try {
        // Проверяем, используется ли ресурс в занятиях
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_resources WHERE resource_id = ?");
        $stmt->execute([$resourceId]);
        $lessonCount = $stmt->fetchColumn();
        
        if ($lessonCount > 0) {
            // Если используется, запрашиваем подтверждение
            $_SESSION['delete_resource_' . $resourceId] = true;
            header('Location: resources.php?confirm_delete=' . $resourceId);
            exit();
        } else {
            // Если не используется, удаляем
            $pdo->beginTransaction();
            
            // Удаляем связи с метками
            $stmt = $pdo->prepare("DELETE FROM resource_labels WHERE resource_id = ?");
            $stmt->execute([$resourceId]);
            
            // Удаляем ресурс
            $stmt = $pdo->prepare("DELETE FROM resources WHERE id = ? AND user_id = ?");
            $stmt->execute([$resourceId, $userId]);
            
            $pdo->commit();
            header('Location: resources.php?message=deleted');
            exit();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Ошибка при удалении: ' . $e->getMessage();
    }
}

// Подтверждение удаления ресурса с занятиями
if (isset($_GET['confirm_delete']) && isset($_SESSION['delete_resource_' . $_GET['confirm_delete']])) {
    $deleteId = $_GET['confirm_delete'];
    unset($_SESSION['delete_resource_' . $deleteId]);
    
    // Получаем информацию о ресурсе
    $stmt = $pdo->prepare("SELECT * FROM resources WHERE id = ? AND user_id = ?");
    $stmt->execute([$deleteId, $userId]);
    $resource = $stmt->fetch();
    
    if ($resource) {
        // Получаем количество занятий с этим ресурсом
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_resources WHERE resource_id = ?");
        $stmt->execute([$deleteId]);
        $lessonCount = $stmt->fetchColumn();
        ?>
        <!DOCTYPE html>
        <html lang="ru">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Подтверждение удаления</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body>
            <div class="container mt-5">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">Подтверждение удаления ресурса</h5>
                    </div>
                    <div class="card-body">
                        <p class="lead">Ресурс "<?php echo htmlspecialchars($resource['description'] ?: $resource['url']); ?>" используется в <?php echo $lessonCount; ?> занятиях.</p>
                        <p class="text-danger">При удалении ресурса связи с занятиями будут также удалены!</p>
                        <div class="d-flex justify-content-between">
                            <a href="resources.php" class="btn btn-secondary">Отмена</a>
                            <a href="?force_delete=<?php echo $deleteId; ?>" class="btn btn-danger">Да, удалить ресурс и связи</a>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
}

// Принудительное удаление с удалением связей
if (isset($_GET['force_delete']) && $resourceId) {
    try {
        $pdo->beginTransaction();
        
        // Удаляем связи с занятиями
        $stmt = $pdo->prepare("DELETE FROM lesson_resources WHERE resource_id = ?");
        $stmt->execute([$resourceId]);
        
        // Удаляем связи с метками
        $stmt = $pdo->prepare("DELETE FROM resource_labels WHERE resource_id = ?");
        $stmt->execute([$resourceId]);
        
        // Удаляем ресурс
        $stmt = $pdo->prepare("DELETE FROM resources WHERE id = ? AND user_id = ?");
        $stmt->execute([$resourceId, $userId]);
        
        $pdo->commit();
        header('Location: resources.php?message=force_deleted');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Ошибка при удалении: ' . $e->getMessage();
    }
}

// Очистка всех ресурсов
if (isset($_GET['clear_all'])) {
    // Проверяем, используются ли ресурсы в занятиях
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM resources r
        LEFT JOIN lesson_resources lr ON lr.resource_id = r.id
        WHERE r.user_id = ? AND lr.id IS NOT NULL
    ");
    $stmt->execute([$userId]);
    $inUse = $stmt->fetchColumn();
    
    if ($inUse > 0) {
        $_SESSION['clear_resources_pending'] = true;
        header('Location: resources.php?confirm_clear_all=1');
        exit();
    } else {
        try {
            $pdo->beginTransaction();
            
            // Удаляем связи с метками для всех ресурсов
            $stmt = $pdo->prepare("DELETE FROM resource_labels WHERE resource_id IN (SELECT id FROM resources WHERE user_id = ?)");
            $stmt->execute([$userId]);
            
            // Удаляем ресурсы
            $stmt = $pdo->prepare("DELETE FROM resources WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            $pdo->commit();
            header('Location: resources.php?message=cleared');
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Ошибка при очистке: ' . $e->getMessage();
        }
    }
}

// Подтверждение очистки всех ресурсов
if (isset($_GET['confirm_clear_all']) && isset($_SESSION['clear_resources_pending'])) {
    unset($_SESSION['clear_resources_pending']);
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Подтверждение очистки</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="card">
                <div class="card-header bg-warning">
                    <h5 class="mb-0">Подтверждение очистки всех ресурсов</h5>
                </div>
                <div class="card-body">
                    <p class="lead">Некоторые ресурсы используются в занятиях.</p>
                    <p class="text-danger">При удалении всех ресурсов связи с занятиями будут также удалены!</p>
                    <div class="d-flex justify-content-between">
                        <a href="resources.php" class="btn btn-secondary">Отмена</a>
                        <a href="?force_clear_all=1" class="btn btn-danger">Да, удалить все ресурсы и связи</a>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Принудительная очистка всех ресурсов
if (isset($_GET['force_clear_all'])) {
    try {
        $pdo->beginTransaction();
        
        // Удаляем связи с занятиями
        $stmt = $pdo->prepare("
            DELETE FROM lesson_resources 
            WHERE resource_id IN (SELECT id FROM resources WHERE user_id = ?)
        ");
        $stmt->execute([$userId]);
        
        // Удаляем связи с метками
        $stmt = $pdo->prepare("DELETE FROM resource_labels WHERE resource_id IN (SELECT id FROM resources WHERE user_id = ?)");
        $stmt->execute([$userId]);
        
        // Удаляем ресурсы
        $stmt = $pdo->prepare("DELETE FROM resources WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        $pdo->commit();
        header('Location: resources.php?message=cleared');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Ошибка при очистке: ' . $e->getMessage();
    }
}

// Добавление/редактирование ресурса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_resource'])) {
    $url = trim($_POST['url'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = $_POST['type'] ?? 'page';
    $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $labelsText = trim($_POST['labels_text'] ?? '');
    $selectedLabels = $_POST['labels'] ?? [];
    
    if (empty($url)) {
        $error = 'URL ресурса обязателен';
    } else {
        try {
            $pdo->beginTransaction();
            
            if ($action === 'edit' && $resourceId) {
                // Обновление ресурса
                $stmt = $pdo->prepare("
                    UPDATE resources SET 
                        url = ?, description = ?, type = ?, category_id = ?, labels = ?
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$url, $description, $type, $categoryId, $labelsText, $resourceId, $userId]);
                
                // Удаляем старые связи с метками
                $stmt = $pdo->prepare("DELETE FROM resource_labels WHERE resource_id = ?");
                $stmt->execute([$resourceId]);
                
                $message = 'Ресурс обновлен';
            } else {
                // Добавление нового ресурса
                $stmt = $pdo->prepare("
                    INSERT INTO resources (user_id, url, description, type, category_id, labels)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$userId, $url, $description, $type, $categoryId, $labelsText]);
                $resourceId = $pdo->lastInsertId();
                $message = 'Ресурс добавлен';
            }
            
            // Добавляем новые связи с метками из банка меток
            if (!empty($selectedLabels)) {
                $stmt = $pdo->prepare("INSERT INTO resource_labels (resource_id, label_id) VALUES (?, ?)");
                foreach ($selectedLabels as $labelId) {
                    // Проверяем, что метка существует и принадлежит пользователю
                    $checkStmt = $pdo->prepare("SELECT id FROM labels WHERE id = ? AND user_id = ?");
                    $checkStmt->execute([$labelId, $userId]);
                    if ($checkStmt->fetch()) {
                        $stmt->execute([$resourceId, $labelId]);
                    }
                }
            }
            
            $pdo->commit();
            header('Location: resources.php?message=saved');
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Ошибка при сохранении: ' . $e->getMessage();
        }
    }
}

// Получение списка ресурсов с фильтрацией
$filterCategory = $_GET['filter_category'] ?? '';
$filterType = $_GET['filter_type'] ?? '';
$filterLabel = $_GET['filter_label'] ?? '';
$searchQuery = $_GET['search'] ?? '';

$query = "
    SELECT r.*, c.name as category_name, c.color as category_color,
           GROUP_CONCAT(DISTINCT l.name) as labels_names,
           GROUP_CONCAT(DISTINCT l.id) as label_ids,
           (SELECT COUNT(*) FROM lesson_resources WHERE resource_id = r.id) as usage_count
    FROM resources r
    LEFT JOIN categories c ON r.category_id = c.id
    LEFT JOIN resource_labels rl ON r.id = rl.resource_id
    LEFT JOIN labels l ON rl.label_id = l.id
    WHERE r.user_id = ?
";
$params = [$userId];

if (!empty($filterCategory)) {
    $query .= " AND r.category_id = ?";
    $params[] = $filterCategory;
}

if (!empty($filterType)) {
    $query .= " AND r.type = ?";
    $params[] = $filterType;
}

if (!empty($filterLabel)) {
    $query .= " AND r.id IN (SELECT resource_id FROM resource_labels WHERE label_id = ?)";
    $params[] = $filterLabel;
}

if (!empty($searchQuery)) {
    $query .= " AND (r.url LIKE ? OR r.description LIKE ? OR r.labels LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

$query .= " GROUP BY r.id ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$resources = $stmt->fetchAll();

// Получение данных для редактирования
$editResource = null;
$editResourceLabels = [];

if ($action === 'edit' && $resourceId) {
    $stmt = $pdo->prepare("SELECT * FROM resources WHERE id = ? AND user_id = ?");
    $stmt->execute([$resourceId, $userId]);
    $editResource = $stmt->fetch();
    
    if ($editResource) {
        // Получаем метки ресурса из связей
        $stmt = $pdo->prepare("
            SELECT l.id 
            FROM resource_labels rl
            JOIN labels l ON rl.label_id = l.id
            WHERE rl.resource_id = ?
        ");
        $stmt->execute([$resourceId]);
        $editResourceLabels = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        header('Location: resources.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Банк ресурсов - Дневник репетитора</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .resource-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-left: 4px solid;
            position: relative;
        }
        .resource-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .resource-url {
            font-size: 0.9em;
            margin-bottom: 10px;
            word-break: break-all;
        }
        .resource-url a {
            color: #667eea;
            text-decoration: none;
        }
        .resource-url a:hover {
            text-decoration: underline;
        }
        .resource-description {
            color: #333;
            margin-bottom: 15px;
            font-size: 1em;
        }
        .resource-type {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            margin-bottom: 10px;
        }
        .resource-type.page { background: #e3f2fd; color: #0d47a1; }
        .resource-type.document { background: #f3e5f5; color: #4a148c; }
        .resource-type.video { background: #ffebee; color: #b71c1c; }
        .resource-type.audio { background: #e8f5e8; color: #1b5e20; }
        .resource-type.other { background: #fafafa; color: #616161; }
        
        .resource-category {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            color: white;
            margin-left: 5px;
        }
        .resource-meta {
            display: flex;
            gap: 15px;
            margin-top: 10px;
            font-size: 0.9em;
            color: #666;
        }
        .label-badge {
            background: #e9ecef;
            border-radius: 15px;
            padding: 3px 10px;
            font-size: 0.8em;
            display: inline-block;
            margin-right: 5px;
            margin-bottom: 5px;
            border-left: 3px solid;
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
        .labels-section {
            max-height: 250px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 10px;
        }
        .label-category {
            font-weight: 600;
            margin-top: 10px;
            margin-bottom: 5px;
            padding-left: 5px;
            border-left: 3px solid;
        }
        .label-category:first-child {
            margin-top: 0;
        }
        .label-checkbox {
            margin-left: 15px;
            margin-bottom: 5px;
        }
        .usage-badge {
            background: #28a745;
            color: white;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 0.75em;
            margin-left: 10px;
        }
        .type-icon {
            font-size: 1.2em;
            margin-right: 5px;
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
                    'saved' => 'Ресурс успешно сохранен',
                    'deleted' => 'Ресурс удален',
                    'force_deleted' => 'Ресурс и все связи удалены',
                    'cleared' => 'Все ресурсы удалены',
                    'labels_cleared' => 'Метки ресурса очищены'
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
                <h2><i class="bi bi-link"></i> Банк ресурсов</h2>
                <div>
                    <a href="?action=add" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Добавить ресурс
                    </a>
                </div>
            </div>
            
            <!-- Статистика -->
            <div class="stats-card mb-4">
                <div class="row">
                    <div class="col-md-3">
                        <div class="text-center">
                            <h3 class="mb-0"><?php echo count($resources); ?></h3>
                            <small>Всего ресурсов</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h3 class="mb-0">
                                <?php 
                                $totalUsage = 0;
                                foreach ($resources as $r) {
                                    $totalUsage += $r['usage_count'];
                                }
                                echo $totalUsage;
                                ?>
                            </h3>
                            <small>Использований в занятиях</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h3 class="mb-0"><?php echo count($allLabels); ?></h3>
                            <small>Доступных меток</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h3 class="mb-0"><?php echo count($categories); ?></h3>
                            <small>Категорий</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Фильтры -->
            <div class="filter-panel">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Поиск</label>
                        <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="URL, описание или метки">
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
                        <label class="form-label">Тип ресурса</label>
                        <select name="filter_type" class="form-select">
                            <option value="">Все типы</option>
                            <?php foreach ($resourceTypes as $typeKey => $typeName): ?>
                                <option value="<?php echo $typeKey; ?>" <?php echo $filterType == $typeKey ? 'selected' : ''; ?>>
                                    <?php echo $typeName; ?>
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
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Применить
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Список ресурсов -->
            <div class="row">
                <?php if (empty($resources)): ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center py-5">
                            <i class="bi bi-link" style="font-size: 3rem;"></i>
                            <h4 class="mt-3">Ресурсы не найдены</h4>
                            <p>Добавьте первый ресурс, нажав кнопку "Добавить ресурс"</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($resources as $resource): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="resource-card" style="border-left-color: <?php echo $resource['category_color'] ?? '#808080'; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="resource-type <?php echo $resource['type']; ?>">
                                        <?php 
                                        $icons = [
                                            'page' => 'bi-file-earmark-text',
                                            'document' => 'bi-file-earmark',
                                            'video' => 'bi-camera-reels',
                                            'audio' => 'bi-mic',
                                            'other' => 'bi-file-earmark'
                                        ];
                                        ?>
                                        <i class="bi <?php echo $icons[$resource['type']] ?? 'bi-file-earmark'; ?>"></i>
                                        <?php echo $resourceTypes[$resource['type']] ?? 'Другое'; ?>
                                    </div>
                                    <?php if ($resource['usage_count'] > 0): ?>
                                        <span class="usage-badge" title="Используется в занятиях">
                                            <?php echo $resource['usage_count']; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($resource['category_name']): ?>
                                    <div class="resource-category" style="background: <?php echo $resource['category_color'] ?? '#808080'; ?>">
                                        <?php echo htmlspecialchars($resource['category_name']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="resource-url">
                                    <i class="bi bi-link-45deg"></i>
                                    <a href="<?php echo htmlspecialchars($resource['url']); ?>" target="_blank">
                                        <?php 
                                        $url = parse_url($resource['url'], PHP_URL_HOST);
                                        echo htmlspecialchars($url ?: $resource['url']); 
                                        ?>
                                    </a>
                                </div>
                                
                                <?php if (!empty($resource['description'])): ?>
                                    <div class="resource-description">
                                        <?php echo nl2br(htmlspecialchars($resource['description'])); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Отображение меток ресурса -->
                                <?php if (!empty($resource['labels_names'])): ?>
                                    <div class="mb-2">
                                        <?php 
                                        $labels = explode(',', $resource['labels_names']);
                                        $labelIds = explode(',', $resource['label_ids'] ?? '');
                                        
                                        // Получаем цвета меток из их категорий
                                        $labelColors = [];
                                        if (!empty($labelIds)) {
                                            $placeholders = implode(',', array_fill(0, count($labelIds), '?'));
                                            $colorStmt = $pdo->prepare("
                                                SELECT l.id, c.color 
                                                FROM labels l
                                                LEFT JOIN categories c ON l.category_id = c.id
                                                WHERE l.id IN ($placeholders)
                                            ");
                                            $colorStmt->execute($labelIds);
                                            while ($row = $colorStmt->fetch()) {
                                                $labelColors[$row['id']] = $row['color'] ?? '#808080';
                                            }
                                        }
                                        
                                        foreach ($labels as $index => $label):
                                            $labelId = $labelIds[$index] ?? 0;
                                            $color = $labelColors[$labelId] ?? '#808080';
                                        ?>
                                            <span class="label-badge" style="border-left-color: <?php echo $color; ?>">
                                                <?php echo htmlspecialchars(trim($label)); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Отображение текстовых меток (если есть) -->
                                <?php if (!empty($resource['labels']) && empty($resource['labels_names'])): ?>
                                    <div class="mb-2">
                                        <?php 
                                        $textLabels = explode(';', $resource['labels']);
                                        foreach ($textLabels as $label):
                                            if (trim($label)):
                                        ?>
                                            <span class="label-badge" style="border-left-color: #808080;">
                                                <?php echo htmlspecialchars(trim($label)); ?>
                                            </span>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="resource-meta">
                                    <small><i class="bi bi-calendar"></i> <?php echo date('d.m.Y', strtotime($resource['created_at'])); ?></small>
                                    <small><i class="bi bi-tag"></i> <?php echo substr_count($resource['labels_names'] ?? '', ',') + 1; ?></small>
                                </div>
                                
                                <div class="mt-3 d-flex justify-content-end gap-2">
                                    <a href="?action=edit&id=<?php echo $resource['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i> Ред.
                                    </a>
                                    <?php if (!empty($resource['labels_names']) || !empty($resource['labels'])): ?>
                                        <a href="?clear_labels=1&id=<?php echo $resource['id']; ?>" class="btn btn-sm btn-outline-warning" 
                                           onclick="return confirm('Очистить все метки ресурса?')">
                                            <i class="bi bi-eraser"></i> Очистить метки
                                        </a>
                                    <?php endif; ?>
                                    <a href="?delete=1&id=<?php echo $resource['id']; ?>" class="btn btn-sm btn-outline-danger" 
                                       onclick="return confirm('Удалить ресурс?')">
                                        <i class="bi bi-trash"></i> Удалить
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Быстрые действия -->
            <div class="quick-actions">
                <button type="button" class="btn btn-danger" onclick="if(confirm('Очистить все ресурсы?')) window.location.href='?clear_all=1'" title="Очистить все ресурсы">
                    <i class="bi bi-trash"></i>
                </button>
                <a href="?action=add" class="btn btn-primary" title="Добавить ресурс">
                    <i class="bi bi-plus"></i>
                </a>
            </div>
            
        <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <!-- Форма добавления/редактирования ресурса -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="bi bi-<?php echo $action === 'add' ? 'plus-circle' : 'pencil'; ?>"></i>
                                <?php echo $action === 'add' ? 'Добавление ресурса' : 'Редактирование ресурса'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" id="resourceForm">
                                <?php if ($action === 'edit' && $editResource): ?>
                                    <input type="hidden" name="resource_id" value="<?php echo $editResource['id']; ?>">
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label class="form-label">URL *</label>
                                    <input type="url" name="url" class="form-control" 
                                           value="<?php echo $editResource ? htmlspecialchars($editResource['url']) : ''; ?>" 
                                           required placeholder="https://...">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Описание</label>
                                    <textarea name="description" class="form-control" rows="3" 
                                              placeholder="Краткое описание ресурса"><?php echo $editResource ? htmlspecialchars($editResource['description'] ?? '') : ''; ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Тип ресурса</label>
                                        <select name="type" class="form-select">
                                            <?php foreach ($resourceTypes as $typeKey => $typeName): ?>
                                                <option value="<?php echo $typeKey; ?>" 
                                                    <?php echo ($editResource && $editResource['type'] == $typeKey) ? 'selected' : ''; ?>>
                                                    <?php echo $typeName; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Категория</label>
                                        <select name="category_id" class="form-select">
                                            <option value="">Без категории</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['id']; ?>" 
                                                    <?php echo ($editResource && $editResource['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Метки из банка меток -->
                                <div class="mb-3">
                                    <label class="form-label">Метки из банка <small class="text-muted">(выберите из списка)</small></label>
                                    <div class="labels-section">
                                        <?php if (empty($groupedLabels)): ?>
                                            <p class="text-muted text-center py-3">
                                                Нет доступных меток. 
                                                <a href="labels.php?action=add" target="_blank">Создайте метки</a> в банке меток.
                                            </p>
                                        <?php else: ?>
                                            <?php foreach ($groupedLabels as $catName => $catData): ?>
                                                <div class="label-category" style="border-left-color: <?php echo $catData['color']; ?>">
                                                    <?php echo htmlspecialchars($catName); ?>
                                                </div>
                                                <?php foreach ($catData['labels'] as $label): ?>
                                                    <div class="form-check label-checkbox">
                                                        <input type="checkbox" name="labels[]" value="<?php echo $label['id']; ?>" 
                                                               class="form-check-input" 
                                                               id="label_<?php echo $label['id']; ?>"
                                                               <?php echo ($editResourceLabels && in_array($label['id'], $editResourceLabels)) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="label_<?php echo $label['id']; ?>">
                                                            <?php echo htmlspecialchars($label['name']); ?>
                                                            <?php if ($label['label_type'] === 'resource'): ?>
                                                                <span class="badge bg-info">ресурс</span>
                                                            <?php endif; ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle"></i> 
                                        Отображаются только метки типа "Ресурсы" и "Общие".
                                    </small>
                                </div>
                                
                                <!-- Текстовые метки (через точку с запятой) -->
                                <div class="mb-3">
                                    <label class="form-label">Текстовые метки <small class="text-muted">(через точку с запятой)</small></label>
                                    <input type="text" name="labels_text" class="form-control" 
                                           value="<?php echo $editResource ? htmlspecialchars($editResource['labels'] ?? '') : ''; ?>" 
                                           placeholder="метка1; метка2; метка3">
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle"></i> 
                                        Можно вводить метки вручную, разделяя точкой с запятой
                                    </small>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <a href="resources.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left"></i> Назад
                                    </a>
                                    <button type="submit" name="save_resource" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Сохранить
                                    </button>
                                </div>
                            </form>
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
                            <p><strong>Типы ресурсов:</strong></p>
                            <ul class="small list-unstyled">
                                <li><i class="bi bi-file-earmark-text text-primary"></i> Страница - веб-страницы</li>
                                <li><i class="bi bi-file-earmark text-success"></i> Документ - PDF, Word и т.д.</li>
                                <li><i class="bi bi-camera-reels text-danger"></i> Видео - YouTube, Vimeo</li>
                                <li><i class="bi bi-mic text-warning"></i> Звук - аудиофайлы</li>
                                <li><i class="bi bi-file-earmark text-secondary"></i> Другое - прочие ресурсы</li>
                            </ul>
                            
                            <hr>
                            
                            <p><strong>Метки:</strong></p>
                            <ul class="small">
                                <li>Можно выбрать из банка меток</li>
                                <li>Можно ввести вручную через ;</li>
                                <li>Кнопка "Очистить метки" удаляет все метки</li>
                            </ul>
                            
                            <div class="alert alert-info small">
                                <i class="bi bi-tags"></i>
                                <a href="labels.php" target="_blank" class="alert-link">Перейти в банк меток</a> 
                                для создания новых меток.
                            </div>
                            
                            <?php if ($action === 'edit' && $editResource): ?>
                                <hr>
                                <p><strong>Статистика:</strong></p>
                                <p>Использований в занятиях: <?php 
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_resources WHERE resource_id = ?");
                                    $stmt->execute([$editResource['id']]);
                                    echo $stmt->fetchColumn();
                                ?></p>
                                <p>Создан: <?php echo date('d.m.Y H:i', strtotime($editResource['created_at'])); ?></p>
                                <p>Обновлен: <?php echo date('d.m.Y H:i', strtotime($editResource['updated_at'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Предпросмотр выбранных меток -->
                    <div class="card mt-3">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-eye"></i> Выбранные метки</h5>
                        </div>
                        <div class="card-body">
                            <div id="selected-labels-preview" class="d-flex flex-wrap gap-1">
                                <span class="text-muted">Нет выбранных меток</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Предпросмотр выбранных меток
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('input[name="labels[]"]');
            const preview = document.getElementById('selected-labels-preview');
            
            function updatePreview() {
                const selected = [];
                checkboxes.forEach(cb => {
                    if (cb.checked) {
                        const label = document.querySelector(`label[for="${cb.id}"]`).innerText.trim();
                        // Убираем бейдж из текста
                        const cleanLabel = label.replace(/\s*<span.*<\/span>/, '').trim();
                        selected.push(`<span class="badge bg-primary">${cleanLabel}</span>`);
                    }
                });
                
                if (selected.length > 0) {
                    preview.innerHTML = selected.join(' ');
                } else {
                    preview.innerHTML = '<span class="text-muted">Нет выбранных меток</span>';
                }
            }
            
            checkboxes.forEach(cb => {
                cb.addEventListener('change', updatePreview);
            });
            
            updatePreview();
        });
    </script>
</body>
</html>