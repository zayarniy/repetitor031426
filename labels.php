<?php
require_once 'config.php';
requireAuth();

$currentUser = getCurrentUser($pdo);
$userId = $currentUser['id'];
$message = '';
$error = '';

// Обработка действий
$action = $_GET['action'] ?? 'list';
$labelId = $_GET['id'] ?? 0;

// Получение категорий для меток
$stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? OR user_id IS NULL ORDER BY name");
$stmt->execute([$userId]);
$categories = $stmt->fetchAll();

// Создаем категорию "Без категории" если её нет в списке
$hasNoCategory = false;
foreach ($categories as $cat) {
    if ($cat['name'] === 'Без категории') {
        $hasNoCategory = true;
        break;
    }
}

if (!$hasNoCategory) {
    // Добавляем служебную категорию "Без категории"
    $stmt = $pdo->prepare("INSERT IGNORE INTO categories (user_id, name, color, is_hidden) VALUES (?, 'Без категории', '#808080', 0)");
    $stmt->execute([$userId]);
    
    // Перезагружаем категории
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? OR user_id IS NULL ORDER BY name");
    $stmt->execute([$userId]);
    $categories = $stmt->fetchAll();
}

// Переключение видимости метки
if (isset($_GET['toggle_hide']) && $labelId) {
    $stmt = $pdo->prepare("UPDATE labels SET is_hidden = NOT is_hidden WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$labelId, $userId])) {
        header('Location: labels.php?message=visibility_changed');
        exit();
    }
}

// Очистка всех меток
if (isset($_GET['clear_all'])) {
    // Проверяем, используются ли метки где-либо
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM student_labels WHERE label_id IN (SELECT id FROM labels WHERE user_id = ?)) as students,
            (SELECT COUNT(*) FROM topic_labels WHERE label_id IN (SELECT id FROM labels WHERE user_id = ?)) as topics,
            (SELECT COUNT(*) FROM resource_labels WHERE label_id IN (SELECT id FROM labels WHERE user_id = ?)) as resources,
            (SELECT COUNT(*) FROM lesson_labels WHERE label_id IN (SELECT id FROM labels WHERE user_id = ?)) as lessons
    ");
    $stmt->execute([$userId, $userId, $userId, $userId]);
    $usage = $stmt->fetch();
    
    $totalUsage = $usage['students'] + $usage['topics'] + $usage['resources'] + $usage['lessons'];
    
    if ($totalUsage > 0) {
        // Если метки используются, запрашиваем подтверждение
        $_SESSION['clear_pending'] = true;
        header('Location: labels.php?confirm_clear=1');
        exit();
    } else {
        // Если не используются, удаляем
        $stmt = $pdo->prepare("DELETE FROM labels WHERE user_id = ?");
        $stmt->execute([$userId]);
        $message = 'Все метки удалены';
        header('Location: labels.php?message=cleared');
        exit();
    }
}

// Подтверждение очистки с удалением связей
if (isset($_GET['confirm_clear']) && isset($_SESSION['clear_pending'])) {
    unset($_SESSION['clear_pending']);
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Подтверждение очистки</title>
        <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="card">
                <div class="card-header bg-warning">
                    <h5 class="mb-0">Подтверждение очистки меток</h5>
                </div>
                <div class="card-body">
                    <p class="lead">Метки используются в следующих модулях:</p>
                    <ul>
                        <li>Ученики: <?php echo $usage['students']; ?> связей</li>
                        <li>Темы: <?php echo $usage['topics']; ?> связей</li>
                        <li>Ресурсы: <?php echo $usage['resources']; ?> связей</li>
                        <li>Занятия: <?php echo $usage['lessons']; ?> связей</li>
                    </ul>
                    <p class="text-danger">При удалении меток все связи будут также удалены!</p>
                    <div class="d-flex justify-content-between">
                        <a href="labels.php" class="btn btn-secondary">Отмена</a>
                        <a href="?force_clear=1" class="btn btn-danger">Да, удалить все метки и связи</a>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Принудительная очистка с удалением связей
if (isset($_GET['force_clear'])) {
    try {
        $pdo->beginTransaction();
        
        // Удаляем все связи
        $stmt = $pdo->prepare("DELETE FROM student_labels WHERE label_id IN (SELECT id FROM labels WHERE user_id = ?)");
        $stmt->execute([$userId]);
        
        $stmt = $pdo->prepare("DELETE FROM topic_labels WHERE label_id IN (SELECT id FROM labels WHERE user_id = ?)");
        $stmt->execute([$userId]);
        
        $stmt = $pdo->prepare("DELETE FROM resource_labels WHERE label_id IN (SELECT id FROM labels WHERE user_id = ?)");
        $stmt->execute([$userId]);
        
        $stmt = $pdo->prepare("DELETE FROM lesson_labels WHERE label_id IN (SELECT id FROM labels WHERE user_id = ?)");
        $stmt->execute([$userId]);
        
        // Удаляем метки
        $stmt = $pdo->prepare("DELETE FROM labels WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        $pdo->commit();
        $message = 'Все метки и связи удалены';
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Ошибка при удалении: ' . $e->getMessage();
    }
    header('Location: labels.php?message=cleared');
    exit();
}

// Добавление/редактирование метки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_label'])) {
    $name = trim($_POST['name'] ?? '');
    $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $labelType = $_POST['label_type'] ?? 'general';
    $isHidden = isset($_POST['is_hidden']) ? 1 : 0;
    
    if (empty($name)) {
        $error = 'Название метки обязательно';
    } else {
        // Проверка на уникальность названия для пользователя
        if ($action === 'edit' && $labelId) {
            $stmt = $pdo->prepare("SELECT id FROM labels WHERE user_id = ? AND name = ? AND id != ?");
            $stmt->execute([$userId, $name, $labelId]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM labels WHERE user_id = ? AND name = ?");
            $stmt->execute([$userId, $name]);
        }
        
        if ($stmt->fetch()) {
            $error = 'Метка с таким названием уже существует';
        } else {
            if ($action === 'edit' && $labelId) {
                // Обновление
                $stmt = $pdo->prepare("
                    UPDATE labels SET 
                        name = ?, category_id = ?, label_type = ?, is_hidden = ?
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$name, $categoryId, $labelType, $isHidden, $labelId, $userId]);
                $message = 'Метка обновлена';
            } else {
                // Добавление
                $stmt = $pdo->prepare("
                    INSERT INTO labels (user_id, name, category_id, label_type, is_hidden)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$userId, $name, $categoryId, $labelType, $isHidden]);
                $message = 'Метка добавлена';
            }
            header('Location: labels.php?message=saved');
            exit();
        }
    }
}

// Удаление метки
if (isset($_GET['delete']) && $labelId) {
    // Проверяем, используется ли метка
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM student_labels WHERE label_id = ?) as students,
            (SELECT COUNT(*) FROM topic_labels WHERE label_id = ?) as topics,
            (SELECT COUNT(*) FROM resource_labels WHERE label_id = ?) as resources,
            (SELECT COUNT(*) FROM lesson_labels WHERE label_id = ?) as lessons
    ");
    $stmt->execute([$labelId, $labelId, $labelId, $labelId]);
    $usage = $stmt->fetch();
    
    if ($usage['students'] > 0 || $usage['topics'] > 0 || $usage['resources'] > 0 || $usage['lessons'] > 0) {
        // Если используется, запрашиваем подтверждение
        $_SESSION['delete_label_' . $labelId] = true;
        header('Location: labels.php?confirm_delete=' . $labelId);
        exit();
    } else {
        // Если не используется, удаляем
        $stmt = $pdo->prepare("DELETE FROM labels WHERE id = ? AND user_id = ?");
        $stmt->execute([$labelId, $userId]);
        header('Location: labels.php?message=deleted');
        exit();
    }
}

// Подтверждение удаления с удалением связей
if (isset($_GET['confirm_delete']) && isset($_SESSION['delete_label_' . $_GET['confirm_delete']])) {
    $deleteId = $_GET['confirm_delete'];
    unset($_SESSION['delete_label_' . $deleteId]);
    
    // Получаем информацию о метке
    $stmt = $pdo->prepare("SELECT * FROM labels WHERE id = ? AND user_id = ?");
    $stmt->execute([$deleteId, $userId]);
    $label = $stmt->fetch();
    
    if ($label) {
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
                        <h5 class="mb-0">Подтверждение удаления метки</h5>
                    </div>
                    <div class="card-body">
                        <p class="lead">Метка "<?php echo htmlspecialchars($label['name']); ?>" используется в следующих модулях:</p>
                        <ul>
                            <li>Ученики: <?php 
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_labels WHERE label_id = ?");
                                $stmt->execute([$deleteId]);
                                echo $stmt->fetchColumn(); 
                            ?> связей</li>
                            <li>Темы: <?php 
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM topic_labels WHERE label_id = ?");
                                $stmt->execute([$deleteId]);
                                echo $stmt->fetchColumn(); 
                            ?> связей</li>
                            <li>Ресурсы: <?php 
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM resource_labels WHERE label_id = ?");
                                $stmt->execute([$deleteId]);
                                echo $stmt->fetchColumn(); 
                            ?> связей</li>
                            <li>Занятия: <?php 
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_labels WHERE label_id = ?");
                                $stmt->execute([$deleteId]);
                                echo $stmt->fetchColumn(); 
                            ?> связей</li>
                        </ul>
                        <p class="text-danger">При удалении метки все связи будут также удалены!</p>
                        <div class="d-flex justify-content-between">
                            <a href="labels.php" class="btn btn-secondary">Отмена</a>
                            <a href="?force_delete=<?php echo $deleteId; ?>" class="btn btn-danger">Да, удалить метку и связи</a>
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
if (isset($_GET['force_delete']) && $labelId) {
    try {
        $pdo->beginTransaction();
        
        // Удаляем все связи
        $stmt = $pdo->prepare("DELETE FROM student_labels WHERE label_id = ?");
        $stmt->execute([$labelId]);
        
        $stmt = $pdo->prepare("DELETE FROM topic_labels WHERE label_id = ?");
        $stmt->execute([$labelId]);
        
        $stmt = $pdo->prepare("DELETE FROM resource_labels WHERE label_id = ?");
        $stmt->execute([$labelId]);
        
        $stmt = $pdo->prepare("DELETE FROM lesson_labels WHERE label_id = ?");
        $stmt->execute([$labelId]);
        
        // Удаляем метку
        $stmt = $pdo->prepare("DELETE FROM labels WHERE id = ? AND user_id = ?");
        $stmt->execute([$labelId, $userId]);
        
        $pdo->commit();
        $message = 'Метка и все связи удалены';
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Ошибка при удалении: ' . $e->getMessage();
    }
    header('Location: labels.php?message=deleted');
    exit();
}

// Импорт из JSON
if (isset($_POST['import_json']) && isset($_FILES['json_file'])) {
    $file = $_FILES['json_file']['tmp_name'];
    $jsonData = file_get_contents($file);
    $data = json_decode($jsonData, true);
    
    if ($data && isset($data['labels']) && is_array($data['labels'])) {
        try {
            $pdo->beginTransaction();
            
            $imported = 0;
            $errors = 0;
            
            foreach ($data['labels'] as $labelData) {
                $name = trim($labelData['name'] ?? '');
                $categoryName = trim($labelData['category'] ?? 'Без категории');
                $labelType = $labelData['type'] ?? 'general';
                $isHidden = isset($labelData['is_hidden']) ? (int)$labelData['is_hidden'] : 0;
                
                if (empty($name)) {
                    $errors++;
                    continue;
                }
                
                // Находим или создаем категорию
                $stmt = $pdo->prepare("SELECT id FROM categories WHERE user_id = ? AND name = ?");
                $stmt->execute([$userId, $categoryName]);
                $category = $stmt->fetch();
                
                if ($category) {
                    $categoryId = $category['id'];
                } else {
                    // Создаем новую категорию
                    $stmt = $pdo->prepare("INSERT INTO categories (user_id, name, color) VALUES (?, ?, '#808080')");
                    $stmt->execute([$userId, $categoryName]);
                    $categoryId = $pdo->lastInsertId();
                }
                
                // Проверяем существование метки
                $stmt = $pdo->prepare("SELECT id FROM labels WHERE user_id = ? AND name = ?");
                $stmt->execute([$userId, $name]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    // Обновляем существующую
                    $stmt = $pdo->prepare("
                        UPDATE labels SET 
                            category_id = ?, label_type = ?, is_hidden = ?
                        WHERE id = ? AND user_id = ?
                    ");
                    $stmt->execute([$categoryId, $labelType, $isHidden, $existing['id'], $userId]);
                } else {
                    // Добавляем новую
                    $stmt = $pdo->prepare("
                        INSERT INTO labels (user_id, name, category_id, label_type, is_hidden)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$userId, $name, $categoryId, $labelType, $isHidden]);
                }
                $imported++;
            }
            
            $pdo->commit();
            $message = "Импорт завершен. Добавлено/обновлено: $imported, ошибок: $errors";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Ошибка при импорте: ' . $e->getMessage();
        }
    } else {
        $error = 'Неверный формат JSON файла';
    }
    header('Location: labels.php?message=imported');
    exit();
}

// Экспорт в JSON
if (isset($_GET['export_json'])) {
    // Получаем все метки пользователя с категориями
    $stmt = $pdo->prepare("
        SELECT 
            l.name,
            l.label_type as type,
            l.is_hidden,
            c.name as category
        FROM labels l
        LEFT JOIN categories c ON l.category_id = c.id
        WHERE l.user_id = ?
        ORDER BY c.name, l.name
    ");
    $stmt->execute([$userId]);
    $labels = $stmt->fetchAll();
    
    $exportData = [
        'export_date' => date('Y-m-d H:i:s'),
        'user_id' => $userId,
        'labels' => $labels
    ];
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="labels_' . date('Y-m-d') . '.json"');
    echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

// Получение списка меток с фильтрацией
$filterCategory = $_GET['filter_category'] ?? '';
$filterType = $_GET['filter_type'] ?? '';
$filterHidden = $_GET['filter_hidden'] ?? '0';
$searchQuery = $_GET['search'] ?? '';

$query = "
    SELECT l.*, c.name as category_name, c.color as category_color 
    FROM labels l
    LEFT JOIN categories c ON l.category_id = c.id
    WHERE l.user_id = ?
";
$params = [$userId];

if ($filterCategory !== '') {
    $query .= " AND l.category_id = ?";
    $params[] = $filterCategory;
}

if ($filterType !== '') {
    $query .= " AND l.label_type = ?";
    $params[] = $filterType;
}

if ($filterHidden === '0') {
    $query .= " AND l.is_hidden = 0";
}

if (!empty($searchQuery)) {
    $query .= " AND l.name LIKE ?";
    $params[] = "%$searchQuery%";
}

$query .= " ORDER BY c.name, l.name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$labels = $stmt->fetchAll();

// Группировка меток по категориям
$groupedLabels = [];
foreach ($labels as $label) {
    $categoryName = $label['category_name'] ?? 'Без категории';
    if (!isset($groupedLabels[$categoryName])) {
        $groupedLabels[$categoryName] = [
            'color' => $label['category_color'] ?? '#808080',
            'labels' => []
        ];
    }
    $groupedLabels[$categoryName]['labels'][] = $label;
}

// Получение статистики использования меток
$usageStats = [];
foreach ($labels as $label) {
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM student_labels WHERE label_id = ?) as students,
            (SELECT COUNT(*) FROM topic_labels WHERE label_id = ?) as topics,
            (SELECT COUNT(*) FROM resource_labels WHERE label_id = ?) as resources,
            (SELECT COUNT(*) FROM lesson_labels WHERE label_id = ?) as lessons
    ");
    $stmt->execute([$label['id'], $label['id'], $label['id'], $label['id']]);
    $usageStats[$label['id']] = $stmt->fetch();
}

// Получение данных для редактирования
$editLabel = null;
if ($action === 'edit' && $labelId) {
    $stmt = $pdo->prepare("SELECT * FROM labels WHERE id = ? AND user_id = ?");
    $stmt->execute([$labelId, $userId]);
    $editLabel = $stmt->fetch();
    
    if (!$editLabel) {
        header('Location: labels.php');
        exit();
    }
}

// Получение категории "Без категории" для использования по умолчанию
$stmt = $pdo->prepare("SELECT id FROM categories WHERE user_id = ? AND name = 'Без категории'");
$stmt->execute([$userId]);
$noCategory = $stmt->fetch();
$noCategoryId = $noCategory ? $noCategory['id'] : null;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Банк меток - Дневник репетитора</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .category-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid;
        }
        .category-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        .category-color {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            margin-right: 10px;
        }
        .label-item {
            display: inline-flex;
            align-items: center;
            background: #f8f9fa;
            border-radius: 30px;
            padding: 8px 16px;
            margin: 0 8px 8px 0;
            transition: all 0.3s;
            border: 1px solid #dee2e6;
        }
        .label-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        .label-item.hidden {
            opacity: 0.6;
            background: #e9ecef;
        }
        .label-name {
            font-weight: 500;
            margin-right: 8px;
        }
        .label-type {
            font-size: 0.7em;
            padding: 2px 8px;
            border-radius: 12px;
            background: #e9ecef;
            margin-right: 8px;
        }
        .label-type.resource { background: #cff4fc; color: #055160; }
        .label-type.topic { background: #d1e7dd; color: #0a3622; }
        .label-type.student { background: #fff3cd; color: #664d03; }
        .label-type.lesson { background: #f8d7da; color: #58151c; }
        .label-type.general { background: #e2e3e5; color: #41464b; }
        
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
        .stats-card {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .usage-badge {
            font-size: 0.75em;
            padding: 2px 6px;
            border-radius: 10px;
            background: white;
            margin-left: 5px;
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
                    'saved' => 'Метка успешно сохранена',
                    'deleted' => 'Метка удалена',
                    'visibility_changed' => 'Видимость метки изменена',
                    'cleared' => 'Метки очищены',
                    'imported' => 'Импорт выполнен успешно'
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
                <h2><i class="bi bi-tags"></i> Банк меток</h2>
                <div>
                    <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class="bi bi-upload"></i> Импорт JSON
                    </button>
                    <a href="?export_json=1" class="btn btn-info me-2">
                        <i class="bi bi-download"></i> Экспорт JSON
                    </a>
                    <a href="?action=add" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Добавить метку
                    </a>
                </div>
            </div>
            
            <!-- Статистика -->
            <div class="stats-card mb-4">
                <div class="row">
                    <div class="col-md-3">
                        <div class="text-center">
                            <h3 class="mb-0"><?php echo count($labels); ?></h3>
                            <small class="text-muted">Всего меток</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h3 class="mb-0">
                                <?php 
                                $totalUsage = 0;
                                foreach ($usageStats as $stat) {
                                    $totalUsage += $stat['students'] + $stat['topics'] + $stat['resources'] + $stat['lessons'];
                                }
                                echo $totalUsage;
                                ?>
                            </h3>
                            <small class="text-muted">Всего использований</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h3 class="mb-0"><?php echo count($groupedLabels); ?></h3>
                            <small class="text-muted">Категорий меток</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h3 class="mb-0">
                                <?php 
                                $byType = ['resource'=>0, 'topic'=>0, 'student'=>0, 'lesson'=>0, 'general'=>0];
                                foreach ($labels as $l) {
                                    $byType[$l['label_type']]++;
                                }
                                echo $byType['resource'] + $byType['topic'] + $byType['student'] + $byType['lesson'];
                                ?>
                            </h3>
                            <small class="text-muted">Специализированных</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Фильтры -->
            <div class="filter-panel">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Поиск</label>
                        <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Название метки">
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
                        <label class="form-label">Тип метки</label>
                        <select name="filter_type" class="form-select">
                            <option value="">Все типы</option>
                            <option value="resource" <?php echo $filterType == 'resource' ? 'selected' : ''; ?>>Ресурсы</option>
                            <option value="topic" <?php echo $filterType == 'topic' ? 'selected' : ''; ?>>Темы</option>
                            <option value="student" <?php echo $filterType == 'student' ? 'selected' : ''; ?>>Ученики</option>
                            <option value="lesson" <?php echo $filterType == 'lesson' ? 'selected' : ''; ?>>Занятия</option>
                            <option value="general" <?php echo $filterType == 'general' ? 'selected' : ''; ?>>Общие</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Видимость</label>
                        <select name="filter_hidden" class="form-select">
                            <option value="0" <?php echo $filterHidden == '0' ? 'selected' : ''; ?>>Только видимые</option>
                            <option value="1" <?php echo $filterHidden == '1' ? 'selected' : ''; ?>>Все</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Применить
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Метки по категориям -->
            <?php if (empty($groupedLabels)): ?>
                <div class="alert alert-info text-center py-5">
                    <i class="bi bi-tag" style="font-size: 3rem;"></i>
                    <h4 class="mt-3">Метки не найдены</h4>
                    <p>Создайте первую метку, нажав кнопку "Добавить метку"</p>
                </div>
            <?php else: ?>
                <?php foreach ($groupedLabels as $categoryName => $categoryData): ?>
                    <div class="category-section" style="border-left-color: <?php echo htmlspecialchars($categoryData['color']); ?>">
                        <div class="category-header">
                            <div class="category-color" style="background: <?php echo htmlspecialchars($categoryData['color']); ?>"></div>
                            <h4 class="mb-0"><?php echo htmlspecialchars($categoryName); ?></h4>
                            <span class="badge bg-secondary ms-3"><?php echo count($categoryData['labels']); ?> меток</span>
                        </div>
                        
                        <div class="labels-container">
                            <?php foreach ($categoryData['labels'] as $label): ?>
                                <?php $usage = $usageStats[$label['id']]; ?>
                                <div class="label-item <?php echo $label['is_hidden'] ? 'hidden' : ''; ?>">
                                    <span class="label-name"><?php echo htmlspecialchars($label['name']); ?></span>
                                    <span class="label-type <?php echo $label['label_type']; ?>">
                                        <?php 
                                        $typeNames = [
                                            'resource' => 'Р', 'topic' => 'Т', 
                                            'student' => 'У', 'lesson' => 'З', 'general' => 'О'
                                        ];
                                        echo $typeNames[$label['label_type']] ?? 'О';
                                        ?>
                                    </span>
                                    <?php if ($usage['students'] + $usage['topics'] + $usage['resources'] + $usage['lessons'] > 0): ?>
                                        <span class="usage-badge" title="Использований">
                                            <?php echo $usage['students'] + $usage['topics'] + $usage['resources'] + $usage['lessons']; ?>
                                        </span>
                                    <?php endif; ?>
                                    <div class="btn-group ms-2">
                                        <a href="?action=edit&id=<?php echo $label['id']; ?>" class="btn btn-sm btn-link p-0 me-2" title="Редактировать">
                                            <i class="bi bi-pencil text-primary"></i>
                                        </a>
                                        <a href="?toggle_hide=1&id=<?php echo $label['id']; ?>" class="btn btn-sm btn-link p-0 me-2" title="<?php echo $label['is_hidden'] ? 'Показать' : 'Скрыть'; ?>">
                                            <i class="bi bi-eye<?php echo $label['is_hidden'] ? '' : '-slash'; ?> text-warning"></i>
                                        </a>
                                        <a href="?delete=1&id=<?php echo $label['id']; ?>" class="btn btn-sm btn-link p-0" title="Удалить" onclick="return confirm('Удалить метку?')">
                                            <i class="bi bi-trash text-danger"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Быстрые действия -->
            <div class="quick-actions">
                <button type="button" class="btn btn-danger mb-2" onclick="if(confirm('Очистить все метки?')) window.location.href='?clear_all=1'" title="Очистить все метки">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
            
        <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <!-- Форма добавления/редактирования метки -->
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="bi bi-<?php echo $action === 'add' ? 'plus-circle' : 'pencil'; ?>"></i>
                                <?php echo $action === 'add' ? 'Добавление метки' : 'Редактирование метки'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" class="needs-validation" novalidate>
                                <?php if ($action === 'edit' && $editLabel): ?>
                                    <input type="hidden" name="label_id" value="<?php echo $editLabel['id']; ?>">
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label class="form-label">Название метки *</label>
                                    <input type="text" name="name" class="form-control" 
                                           value="<?php echo $editLabel ? htmlspecialchars($editLabel['name']) : ''; ?>" 
                                           required maxlength="100">
                                    <div class="invalid-feedback">Введите название метки</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Категория</label>
                                    <select name="category_id" class="form-select">
                                        <option value="">Без категории</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" 
                                                <?php echo ($editLabel && $editLabel['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">По умолчанию новые метки в категории "Без категории"</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Тип метки</label>
                                    <select name="label_type" class="form-select">
                                        <option value="general" <?php echo ($editLabel && $editLabel['label_type'] == 'general') ? 'selected' : ''; ?>>Общая</option>
                                        <option value="resource" <?php echo ($editLabel && $editLabel['label_type'] == 'resource') ? 'selected' : ''; ?>>Ресурсы</option>
                                        <option value="topic" <?php echo ($editLabel && $editLabel['label_type'] == 'topic') ? 'selected' : ''; ?>>Темы</option>
                                        <option value="student" <?php echo ($editLabel && $editLabel['label_type'] == 'student') ? 'selected' : ''; ?>>Ученики</option>
                                        <option value="lesson" <?php echo ($editLabel && $editLabel['label_type'] == 'lesson') ? 'selected' : ''; ?>>Занятия</option>
                                    </select>
                                    <small class="text-muted d-block mt-1">
                                        <span class="badge bg-info">Ресурсы</span> - для фильтрации ресурсов<br>
                                        <span class="badge bg-success">Темы</span> - для фильтрации тем<br>
                                        <span class="badge bg-warning">Ученики</span> - для фильтрации учеников<br>
                                        <span class="badge bg-danger">Занятия</span> - для фильтрации занятий
                                    </small>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" name="is_hidden" class="form-check-input" id="isHidden" 
                                           <?php echo ($editLabel && $editLabel['is_hidden']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="isHidden">Скрыть метку</label>
                                    <small class="text-muted d-block">Скрытые метки не отображаются в списках по умолчанию</small>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <a href="labels.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left"></i> Назад
                                    </a>
                                    <button type="submit" name="save_label" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Сохранить
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <?php if ($action === 'edit' && $editLabel): ?>
                        <!-- Информация об использовании -->
                        <?php $usage = $usageStats[$editLabel['id']] ?? ['students'=>0, 'topics'=>0, 'resources'=>0, 'lessons'=>0]; ?>
                        <div class="card mt-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Информация об использовании</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 text-center">
                                        <h3><?php echo $usage['students']; ?></h3>
                                        <small class="text-muted">Ученики</small>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <h3><?php echo $usage['topics']; ?></h3>
                                        <small class="text-muted">Темы</small>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <h3><?php echo $usage['resources']; ?></h3>
                                        <small class="text-muted">Ресурсы</small>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <h3><?php echo $usage['lessons']; ?></h3>
                                        <small class="text-muted">Занятия</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Модальное окно импорта JSON -->
    <div class="modal fade" id="importModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Импорт меток из JSON</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Выберите JSON файл</label>
                            <input type="file" name="json_file" class="form-control" accept=".json" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Пример формата JSON:</label>
                            <pre class="bg-light p-2 rounded small">
{
  "labels": [
    {
      "name": "Важно",
      "category": "Приоритет",
      "type": "general",
      "is_hidden": 0
    },
    {
      "name": "ОГЭ",
      "category": "Экзамены",
      "type": "topic",
      "is_hidden": 0
    }
  ]
}
                            </pre>
                            <p class="text-muted small mb-0">
                                <i class="bi bi-info-circle"></i> 
                                type: resource, topic, student, lesson, general<br>
                                Если категория не существует, она будет создана.
                            </p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" name="import_json" class="btn btn-primary">Импортировать</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Валидация формы
        (function() {
            'use strict';
            var forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
        
        // Подсказки при наведении
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>