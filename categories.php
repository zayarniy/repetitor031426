<?php
require_once 'config.php';
requireAuth();

$currentUser = getCurrentUser($pdo);
$userId = $currentUser['id'];
$message = '';
$error = '';

// Обработка действий
$action = $_GET['action'] ?? 'list';
$categoryId = $_GET['id'] ?? 0;

// Переключение видимости категории
if (isset($_GET['toggle_hide']) && $categoryId) {
    $stmt = $pdo->prepare("UPDATE categories SET is_hidden = NOT is_hidden WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$categoryId, $userId])) {
        header('Location: categories.php?message=visibility_changed');
        exit();
    }
}

// Очистка всех категорий
if (isset($_GET['clear_all'])) {
    // Проверяем, используются ли категории где-либо
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM categories c
        LEFT JOIN topics t ON t.category_id = c.id
        LEFT JOIN resources r ON r.category_id = c.id
        LEFT JOIN diaries d ON d.category_id = c.id
        LEFT JOIN labels l ON l.category_id = c.id
        WHERE c.user_id = ? AND (t.id IS NOT NULL OR r.id IS NOT NULL OR d.id IS NOT NULL OR l.id IS NOT NULL)
    ");
    $stmt->execute([$userId]);
    $inUse = $stmt->fetchColumn();
    
    if ($inUse > 0) {
        // Если категории используются, делаем их скрытыми вместо удаления
        $stmt = $pdo->prepare("UPDATE categories SET is_hidden = 1 WHERE user_id = ?");
        $stmt->execute([$userId]);
        $message = 'Категории скрыты, так как они используются в других модулях';
    } else {
        // Если не используются, удаляем
        $stmt = $pdo->prepare("DELETE FROM categories WHERE user_id = ?");
        $stmt->execute([$userId]);
        $message = 'Все категории удалены';
    }
    header('Location: categories.php?message=cleared');
    exit();
}

// Добавление/редактирование категории
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
    $name = trim($_POST['name'] ?? '');
    $color = trim($_POST['color'] ?? '#808080');
    $isHidden = isset($_POST['is_hidden']) ? 1 : 0;
    $sortOrder = intval($_POST['sort_order'] ?? 0);
    
    if (empty($name)) {
        $error = 'Название категории обязательно';
    } else {
        // Проверка на уникальность названия для пользователя
        if ($action === 'edit' && $categoryId) {
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE user_id = ? AND name = ? AND id != ?");
            $stmt->execute([$userId, $name, $categoryId]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE user_id = ? AND name = ?");
            $stmt->execute([$userId, $name]);
        }
        
        if ($stmt->fetch()) {
            $error = 'Категория с таким названием уже существует';
        } else {
            if ($action === 'edit' && $categoryId) {
                // Обновление
                $stmt = $pdo->prepare("
                    UPDATE categories SET 
                        name = ?, color = ?, is_hidden = ?, sort_order = ?
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$name, $color, $isHidden, $sortOrder, $categoryId, $userId]);
                $message = 'Категория обновлена';
            } else {
                // Добавление
                $stmt = $pdo->prepare("
                    INSERT INTO categories (user_id, name, color, is_hidden, sort_order)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$userId, $name, $color, $isHidden, $sortOrder]);
                $message = 'Категория добавлена';
            }
            header('Location: categories.php?message=saved');
            exit();
        }
    }
}

// Удаление категории
if (isset($_GET['delete']) && $categoryId) {
    // Проверяем, используется ли категория
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM topics WHERE category_id = ?) as topics,
            (SELECT COUNT(*) FROM resources WHERE category_id = ?) as resources,
            (SELECT COUNT(*) FROM diaries WHERE category_id = ?) as diaries,
            (SELECT COUNT(*) FROM labels WHERE category_id = ?) as labels
    ");
    $stmt->execute([$categoryId, $categoryId, $categoryId, $categoryId]);
    $usage = $stmt->fetch();
    
    if ($usage['topics'] > 0 || $usage['resources'] > 0 || $usage['diaries'] > 0 || $usage['labels'] > 0) {
        // Если используется, просто скрываем
        $stmt = $pdo->prepare("UPDATE categories SET is_hidden = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$categoryId, $userId]);
        $message = 'Категория скрыта, так как используется в других модулях';
    } else {
        // Если не используется, удаляем
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
        $stmt->execute([$categoryId, $userId]);
        $message = 'Категория удалена';
    }
    header('Location: categories.php?message=deleted');
    exit();
}

// Импорт из CSV
if (isset($_POST['import_csv']) && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    if (($handle = fopen($file, "r")) !== FALSE) {
        // Пропускаем заголовок
        $header = fgetcsv($handle, 1000, ",");
        
        $imported = 0;
        $errors = 0;
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (count($data) >= 3) {
                $name = trim($data[0]);
                $color = trim($data[1]);
                $isHidden = isset($data[2]) ? (int)$data[2] : 0;
                $sortOrder = isset($data[3]) ? (int)$data[3] : 0;
                
                // Валидация цвета
                if (!preg_match('/^#[a-f0-9]{6}$/i', $color)) {
                    $color = '#808080';
                }
                
                if (!empty($name)) {
                    // Проверка на существование
                    $stmt = $pdo->prepare("SELECT id FROM categories WHERE user_id = ? AND name = ?");
                    $stmt->execute([$userId, $name]);
                    
                    if ($stmt->fetch()) {
                        // Обновляем существующую
                        $stmt = $pdo->prepare("
                            UPDATE categories SET 
                                color = ?, is_hidden = ?, sort_order = ?
                            WHERE user_id = ? AND name = ?
                        ");
                        $stmt->execute([$color, $isHidden, $sortOrder, $userId, $name]);
                    } else {
                        // Добавляем новую
                        $stmt = $pdo->prepare("
                            INSERT INTO categories (user_id, name, color, is_hidden, sort_order)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$userId, $name, $color, $isHidden, $sortOrder]);
                    }
                    $imported++;
                } else {
                    $errors++;
                }
            }
        }
        fclose($handle);
        $message = "Импорт завершен. Добавлено/обновлено: $imported, ошибок: $errors";
    } else {
        $error = 'Ошибка при открытии файла';
    }
    header('Location: categories.php?message=imported');
    exit();
}

// Экспорт в CSV
if (isset($_GET['export_csv'])) {
    // Получаем все категории пользователя
    $stmt = $pdo->prepare("SELECT name, color, is_hidden, sort_order FROM categories WHERE user_id = ? ORDER BY sort_order, name");
    $stmt->execute([$userId]);
    $categories = $stmt->fetchAll();
    
    // Создаем CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="categories_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    // Заголовки
    fputcsv($output, ['Название', 'Цвет', 'Скрыта', 'Порядок']);
    
    // Данные
    foreach ($categories as $category) {
        fputcsv($output, [
            $category['name'],
            $category['color'],
            $category['is_hidden'],
            $category['sort_order']
        ]);
    }
    fclose($output);
    exit();
}

// Получение списка категорий
$showHidden = isset($_GET['show_hidden']) ? $_GET['show_hidden'] : '0';

$query = "SELECT * FROM categories WHERE user_id = ?";
$params = [$userId];

if ($showHidden === '0') {
    $query .= " AND is_hidden = 0";
}

$query .= " ORDER BY sort_order, name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$categories = $stmt->fetchAll();

// Получение статистики использования категорий
$usageStats = [];
foreach ($categories as $category) {
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM topics WHERE category_id = ?) as topics,
            (SELECT COUNT(*) FROM resources WHERE category_id = ?) as resources,
            (SELECT COUNT(*) FROM diaries WHERE category_id = ?) as diaries,
            (SELECT COUNT(*) FROM labels WHERE category_id = ?) as labels
    ");
    $stmt->execute([$category['id'], $category['id'], $category['id'], $category['id']]);
    $usageStats[$category['id']] = $stmt->fetch();
}

// Получение данных для редактирования
$editCategory = null;
if ($action === 'edit' && $categoryId) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ? AND user_id = ?");
    $stmt->execute([$categoryId, $userId]);
    $editCategory = $stmt->fetch();
    
    if (!$editCategory) {
        header('Location: categories.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Банк категорий - Дневник репетитора</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/spectrum-colorpicker2@2.0.0/dist/spectrum.min.css" rel="stylesheet">
    <style>
        .category-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-left: 4px solid;
            position: relative;
        }
        .category-card.hidden {
            opacity: 0.6;
            background: #f8f9fa;
        }
        .category-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .category-color {
            width: 30px;
            height: 30px;
            border-radius: 6px;
            display: inline-block;
            margin-right: 10px;
            border: 2px solid #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .category-name {
            font-size: 1.2em;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        .usage-badge {
            background: #e9ecef;
            border-radius: 20px;
            padding: 3px 10px;
            font-size: 0.85em;
            margin-right: 5px;
            display: inline-flex;
            align-items: center;
        }
        .usage-badge i {
            margin-right: 3px;
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
        .color-preview {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 2px solid #dee2e6;
            cursor: pointer;
        }
        .sort-handle {
            cursor: move;
            color: #adb5bd;
            font-size: 1.2em;
            margin-right: 10px;
        }
        .sort-handle:hover {
            color: #667eea;
        }
        .stats-section {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
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
                    'saved' => 'Категория успешно сохранена',
                    'deleted' => 'Категория удалена',
                    'visibility_changed' => 'Видимость категории изменена',
                    'cleared' => 'Категории очищены',
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
                <h2><i class="bi bi-tags"></i> Банк категорий</h2>
                <div>
                    <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class="bi bi-upload"></i> Импорт CSV
                    </button>
                    <a href="?export_csv=1" class="btn btn-info me-2">
                        <i class="bi bi-download"></i> Экспорт CSV
                    </a>
                    <a href="?action=add" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Добавить категорию
                    </a>
                </div>
            </div>
            
            <!-- Статистика -->
            <div class="stats-section mb-4">
                <div class="row">
                    <div class="col-md-3">
                        <div class="text-center">
                            <h3 class="mb-0"><?php echo count($categories); ?></h3>
                            <small class="text-muted">Всего категорий</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h3 class="mb-0">
                                <?php 
                                $totalUsage = 0;
                                foreach ($usageStats as $stat) {
                                    $totalUsage += $stat['topics'] + $stat['resources'] + $stat['diaries'] + $stat['labels'];
                                }
                                echo $totalUsage;
                                ?>
                            </h3>
                            <small class="text-muted">Всего использований</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h3 class="mb-0">
                                <?php 
                                $hiddenCount = 0;
                                foreach ($categories as $cat) {
                                    if ($cat['is_hidden']) $hiddenCount++;
                                }
                                echo $hiddenCount;
                                ?>
                            </h3>
                            <small class="text-muted">Скрытых</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h3 class="mb-0">
                                <?php 
                                $mostUsed = 0;
                                foreach ($usageStats as $stat) {
                                    $used = $stat['topics'] + $stat['resources'] + $stat['diaries'] + $stat['labels'];
                                    if ($used > $mostUsed) $mostUsed = $used;
                                }
                                echo $mostUsed;
                                ?>
                            </h3>
                            <small class="text-muted">Макс. использований</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Фильтры -->
            <div class="filter-panel">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <div class="btn-group" role="group">
                            <a href="?show_hidden=0" class="btn <?php echo $showHidden === '0' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                Только видимые
                            </a>
                            <a href="?show_hidden=1" class="btn <?php echo $showHidden === '1' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                Показать все
                            </a>
                        </div>
                    </div>
                    <div class="col-md-6 text-end">
                        <a href="?clear_all=1" class="btn btn-outline-danger" 
                           onclick="return confirm('Вы уверены? Это действие очистит все категории. Категории, используемые в других модулях, будут скрыты.')">
                            <i class="bi bi-trash"></i> Очистить все категории
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Список категорий -->
            <div class="row" id="categories-list">
                <?php if (empty($categories)): ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center py-5">
                            <i class="bi bi-tag" style="font-size: 3rem;"></i>
                            <h4 class="mt-3">Категории не найдены</h4>
                            <p>Создайте первую категорию, нажав кнопку "Добавить категорию"</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($categories as $category): ?>
                        <?php $usage = $usageStats[$category['id']]; ?>
                        <div class="col-md-6 col-lg-4 category-item" data-id="<?php echo $category['id']; ?>">
                            <div class="category-card <?php echo $category['is_hidden'] ? 'hidden' : ''; ?>" 
                                 style="border-left-color: <?php echo htmlspecialchars($category['color']); ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="category-name">
                                        <span class="sort-handle"><i class="bi bi-grip-vertical"></i></span>
                                        <span class="category-color" style="background: <?php echo htmlspecialchars($category['color']); ?>"></span>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </div>
                                    <div>
                                        <span class="badge <?php echo $category['is_hidden'] ? 'bg-secondary' : 'bg-success'; ?>">
                                            <?php echo $category['is_hidden'] ? 'Скрыта' : 'Видима'; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <div class="usage-badge">
                                        <i class="bi bi-book"></i> Темы: <?php echo $usage['topics']; ?>
                                    </div>
                                    <div class="usage-badge">
                                        <i class="bi bi-link"></i> Ресурсы: <?php echo $usage['resources']; ?>
                                    </div>
                                    <div class="usage-badge">
                                        <i class="bi bi-journal"></i> Дневники: <?php echo $usage['diaries']; ?>
                                    </div>
                                    <div class="usage-badge">
                                        <i class="bi bi-tag"></i> Метки: <?php echo $usage['labels']; ?>
                                    </div>
                                </div>
                                
                                <div class="mt-3 d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="bi bi-arrow-up-down"></i> Порядок: <?php echo $category['sort_order']; ?>
                                    </small>
                                    <div>
                                        <a href="?action=edit&id=<?php echo $category['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="?toggle_hide=1&id=<?php echo $category['id']; ?>" class="btn btn-sm btn-outline-warning">
                                            <i class="bi bi-eye<?php echo $category['is_hidden'] ? '' : '-slash'; ?>"></i>
                                        </a>
                                        <a href="?delete=1&id=<?php echo $category['id']; ?>" class="btn btn-sm btn-outline-danger" 
                                           onclick="return confirm('Удалить категорию? Если она используется, она будет скрыта.')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
        <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <!-- Форма добавления/редактирования категории -->
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="bi bi-<?php echo $action === 'add' ? 'plus-circle' : 'pencil'; ?>"></i>
                                <?php echo $action === 'add' ? 'Добавление категории' : 'Редактирование категории'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" class="needs-validation" novalidate>
                                <?php if ($action === 'edit' && $editCategory): ?>
                                    <input type="hidden" name="category_id" value="<?php echo $editCategory['id']; ?>">
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label class="form-label">Название категории *</label>
                                    <input type="text" name="name" class="form-control" 
                                           value="<?php echo $editCategory ? htmlspecialchars($editCategory['name']) : ''; ?>" 
                                           required maxlength="100">
                                    <div class="invalid-feedback">Введите название категории</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Цвет</label>
                                    <div class="d-flex align-items-center">
                                        <input type="color" name="color" id="colorPicker" class="form-control form-control-color" 
                                            value="<?php echo $editCategory ? htmlspecialchars($editCategory['color']) : '#667eea'; ?>" 
                                            style="width: 70px; height: 40px; padding: 5px;">
                                        <input type="text" class="form-control ms-2" id="colorHex" 
                                            value="<?php echo $editCategory ? htmlspecialchars($editCategory['color']) : '#667eea'; ?>" 
                                            maxlength="7" style="width: 100px;">
                                        <div id="colorPreview" class="ms-2" style="width: 40px; height: 40px; border-radius: 8px; border: 2px solid #dee2e6; background: <?php echo $editCategory ? htmlspecialchars($editCategory['color']) : '#667eea'; ?>;"></div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Порядок сортировки</label>
                                    <input type="number" name="sort_order" class="form-control" 
                                           value="<?php echo $editCategory ? htmlspecialchars($editCategory['sort_order']) : '0'; ?>" 
                                           min="0" step="1">
                                    <small class="text-muted">Меньшее число - выше в списке</small>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" name="is_hidden" class="form-check-input" id="isHidden" 
                                           <?php echo ($editCategory && $editCategory['is_hidden']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="isHidden">Скрыть категорию</label>
                                    <small class="text-muted d-block">Скрытые категории не отображаются в списках по умолчанию</small>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <a href="categories.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left"></i> Назад
                                    </a>
                                    <button type="submit" name="save_category" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Сохранить
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <?php if ($action === 'edit' && $editCategory): ?>
                        <!-- Информация об использовании -->
                        <div class="card mt-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Информация об использовании</h5>
                            </div>
                            <div class="card-body">
                                <?php $usage = $usageStats[$editCategory['id']] ?? ['topics'=>0, 'resources'=>0, 'diaries'=>0, 'labels'=>0]; ?>
                                <div class="row">
                                    <div class="col-md-3 text-center">
                                        <h3><?php echo $usage['topics']; ?></h3>
                                        <small class="text-muted">Темы</small>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <h3><?php echo $usage['resources']; ?></h3>
                                        <small class="text-muted">Ресурсы</small>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <h3><?php echo $usage['diaries']; ?></h3>
                                        <small class="text-muted">Дневники</small>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <h3><?php echo $usage['labels']; ?></h3>
                                        <small class="text-muted">Метки</small>
                                    </div>
                                </div>
                                <p class="text-muted mt-3 mb-0">
                                    <i class="bi bi-info-circle"></i> 
                                    Если категория используется в других модулях, при удалении она будет скрыта, а не удалена.
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Модальное окно импорта CSV -->
    <div class="modal fade" id="importModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Импорт категорий из CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Выберите CSV файл</label>
                            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Пример формата CSV:</label>
                            <pre class="bg-light p-2 rounded small">
Название,Цвет,Скрыта,Порядок
Математика,#FF6B6B,0,1
Русский язык,#4ECDC4,0,2
Литература,#45B7D1,1,3
                            </pre>
                            <p class="text-muted small mb-0">
                                <i class="bi bi-info-circle"></i> 
                                Цвет должен быть в HEX формате (#RRGGBB).<br>
                                Скрыта: 0 - видима, 1 - скрыта.<br>
                                Если категория существует, она будет обновлена.
                            </p>
                        </div>
                        
                        <div class="mb-3">
                            <a href="sample_categories.csv" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-download"></i> Скачать пример
                            </a>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" name="import_csv" class="btn btn-primary">Импортировать</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Подключение скриптов -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/spectrum-colorpicker2@2.0.0/dist/spectrum.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    
    <script>
          // Нативный цветовой picker
    const colorPicker = document.getElementById('colorPicker');
    const colorHex = document.getElementById('colorHex');
    const colorPreview = document.getElementById('colorPreview');
    
    if (colorPicker && colorHex && colorPreview) {
        // При изменении color picker
        colorPicker.addEventListener('input', function(e) {
            colorHex.value = e.target.value;
            colorPreview.style.backgroundColor = e.target.value;
        });
        
        // При вводе hex
        colorHex.addEventListener('input', function(e) {
            let value = e.target.value;
            // Проверяем, что это валидный hex цвет
            if (/^#[0-9A-F]{6}$/i.test(value)) {
                colorPicker.value = value;
                colorPreview.style.backgroundColor = value;
            }
        });
        
        // При потере фокуса проверяем и исправляем
        colorHex.addEventListener('blur', function(e) {
            let value = e.target.value;
            if (!/^#[0-9A-F]{6}$/i.test(value)) {
                // Если невалидный, устанавливаем значение из colorPicker
                colorHex.value = colorPicker.value;
            }
        });
    }
    
    // Drag & drop сортировка
    const categoriesList = document.getElementById('categories-list');
    if (categoriesList) {
        new Sortable(categoriesList, {
            animation: 150,
            handle: '.sort-handle',
            ghostClass: 'bg-light',
            onEnd: function(evt) {
                // Здесь можно добавить AJAX для сохранения порядка
                const items = document.querySelectorAll('.category-item');
                const order = [];
                items.forEach((item, index) => {
                    order.push({
                        id: item.dataset.id,
                        order: index
                    });
                });
                
                // Сохраняем порядок через fetch
                fetch('ajax/save_category_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({order: order})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Показываем уведомление (можно добавить toast)
                        console.log('Порядок сохранен');
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        });
    }
    
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
    </script>
</body>
</html>