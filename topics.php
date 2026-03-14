<?php
require_once 'config.php';
requireAuth();

$currentUser = getCurrentUser($pdo);
$userId = $currentUser['id'];
$message = '';
$error = '';

// Обработка действий
$action = $_GET['action'] ?? 'list';
$topicId = $_GET['id'] ?? 0;

// Получение категорий для фильтрации
$stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? OR user_id IS NULL ORDER BY name");
$stmt->execute([$userId]);
$categories = $stmt->fetchAll();

// Получение всех меток для тем с группировкой по категориям
$stmt = $pdo->prepare("
    SELECT l.*, c.name as category_name, c.color as category_color 
    FROM labels l
    LEFT JOIN categories c ON l.category_id = c.id
    WHERE l.user_id = ? AND (l.label_type = 'topic' OR l.label_type = 'general')
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

// Удаление темы
if (isset($_GET['delete']) && $topicId) {
    try {
        // Проверяем, используется ли тема в занятиях
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_topics WHERE topic_id = ?");
        $stmt->execute([$topicId]);
        $lessonCount = $stmt->fetchColumn();
        
        if ($lessonCount > 0) {
            // Если используется, запрашиваем подтверждение
            $_SESSION['delete_topic_' . $topicId] = true;
            header('Location: topics.php?confirm_delete=' . $topicId);
            exit();
        } else {
            // Если не используется, удаляем
            $pdo->beginTransaction();
            
            // Удаляем ссылки темы
            $stmt = $pdo->prepare("DELETE FROM topic_links WHERE topic_id = ?");
            $stmt->execute([$topicId]);
            
            // Удаляем связи с метками
            $stmt = $pdo->prepare("DELETE FROM topic_labels WHERE topic_id = ?");
            $stmt->execute([$topicId]);
            
            // Удаляем тему
            $stmt = $pdo->prepare("DELETE FROM topics WHERE id = ? AND user_id = ?");
            $stmt->execute([$topicId, $userId]);
            
            $pdo->commit();
            header('Location: topics.php?message=deleted');
            exit();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Ошибка при удалении: ' . $e->getMessage();
    }
}

// Подтверждение удаления темы с занятиями
if (isset($_GET['confirm_delete']) && isset($_SESSION['delete_topic_' . $_GET['confirm_delete']])) {
    $deleteId = $_GET['confirm_delete'];
    unset($_SESSION['delete_topic_' . $deleteId]);
    
    // Получаем информацию о теме
    $stmt = $pdo->prepare("SELECT * FROM topics WHERE id = ? AND user_id = ?");
    $stmt->execute([$deleteId, $userId]);
    $topic = $stmt->fetch();
    
    if ($topic) {
        // Получаем количество занятий с этой темой
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_topics WHERE topic_id = ?");
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
                        <h5 class="mb-0">Подтверждение удаления темы</h5>
                    </div>
                    <div class="card-body">
                        <p class="lead">Тема "<?php echo htmlspecialchars($topic['name']); ?>" используется в <?php echo $lessonCount; ?> занятиях.</p>
                        <p class="text-danger">При удалении темы связи с занятиями будут также удалены!</p>
                        <div class="d-flex justify-content-between">
                            <a href="topics.php" class="btn btn-secondary">Отмена</a>
                            <a href="?force_delete=<?php echo $deleteId; ?>" class="btn btn-danger">Да, удалить тему и связи</a>
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
if (isset($_GET['force_delete']) && $topicId) {
    try {
        $pdo->beginTransaction();
        
        // Удаляем связи с занятиями
        $stmt = $pdo->prepare("DELETE FROM lesson_topics WHERE topic_id = ?");
        $stmt->execute([$topicId]);
        
        // Удаляем ссылки темы
        $stmt = $pdo->prepare("DELETE FROM topic_links WHERE topic_id = ?");
        $stmt->execute([$topicId]);
        
        // Удаляем связи с метками
        $stmt = $pdo->prepare("DELETE FROM topic_labels WHERE topic_id = ?");
        $stmt->execute([$topicId]);
        
        // Удаляем тему
        $stmt = $pdo->prepare("DELETE FROM topics WHERE id = ? AND user_id = ?");
        $stmt->execute([$topicId, $userId]);
        
        $pdo->commit();
        header('Location: topics.php?message=force_deleted');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Ошибка при удалении: ' . $e->getMessage();
    }
}

// Очистка всех тем
if (isset($_GET['clear_all'])) {
    // Проверяем, используются ли темы в занятиях
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM topics t
        LEFT JOIN lesson_topics lt ON lt.topic_id = t.id
        WHERE t.user_id = ? AND lt.id IS NOT NULL
    ");
    $stmt->execute([$userId]);
    $inUse = $stmt->fetchColumn();
    
    if ($inUse > 0) {
        $_SESSION['clear_topics_pending'] = true;
        header('Location: topics.php?confirm_clear_all=1');
        exit();
    } else {
        try {
            $pdo->beginTransaction();
            
            // Удаляем все ссылки тем
            $stmt = $pdo->prepare("DELETE FROM topic_links WHERE topic_id IN (SELECT id FROM topics WHERE user_id = ?)");
            $stmt->execute([$userId]);
            
            // Удаляем связи с метками
            $stmt = $pdo->prepare("DELETE FROM topic_labels WHERE topic_id IN (SELECT id FROM topics WHERE user_id = ?)");
            $stmt->execute([$userId]);
            
            // Удаляем темы
            $stmt = $pdo->prepare("DELETE FROM topics WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            $pdo->commit();
            header('Location: topics.php?message=cleared');
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Ошибка при очистке: ' . $e->getMessage();
        }
    }
}

// Подтверждение очистки всех тем
if (isset($_GET['confirm_clear_all']) && isset($_SESSION['clear_topics_pending'])) {
    unset($_SESSION['clear_topics_pending']);
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
                    <h5 class="mb-0">Подтверждение очистки всех тем</h5>
                </div>
                <div class="card-body">
                    <p class="lead">Некоторые темы используются в занятиях.</p>
                    <p class="text-danger">При удалении всех тем связи с занятиями будут также удалены!</p>
                    <div class="d-flex justify-content-between">
                        <a href="topics.php" class="btn btn-secondary">Отмена</a>
                        <a href="?force_clear_all=1" class="btn btn-danger">Да, удалить все темы и связи</a>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Принудительная очистка всех тем
if (isset($_GET['force_clear_all'])) {
    try {
        $pdo->beginTransaction();
        
        // Удаляем связи с занятиями
        $stmt = $pdo->prepare("
            DELETE FROM lesson_topics 
            WHERE topic_id IN (SELECT id FROM topics WHERE user_id = ?)
        ");
        $stmt->execute([$userId]);
        
        // Удаляем все ссылки тем
        $stmt = $pdo->prepare("DELETE FROM topic_links WHERE topic_id IN (SELECT id FROM topics WHERE user_id = ?)");
        $stmt->execute([$userId]);
        
        // Удаляем связи с метками
        $stmt = $pdo->prepare("DELETE FROM topic_labels WHERE topic_id IN (SELECT id FROM topics WHERE user_id = ?)");
        $stmt->execute([$userId]);
        
        // Удаляем темы
        $stmt = $pdo->prepare("DELETE FROM topics WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        $pdo->commit();
        header('Location: topics.php?message=cleared');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Ошибка при очистке: ' . $e->getMessage();
    }
}

// Добавление/редактирование темы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_topic'])) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $selectedLabels = $_POST['labels'] ?? [];
    
    if (empty($name)) {
        $error = 'Название темы обязательно';
    } else {
        // Проверка на уникальность названия для пользователя
        if ($action === 'edit' && $topicId) {
            $stmt = $pdo->prepare("SELECT id FROM topics WHERE user_id = ? AND name = ? AND id != ?");
            $stmt->execute([$userId, $name, $topicId]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM topics WHERE user_id = ? AND name = ?");
            $stmt->execute([$userId, $name]);
        }
        
        if ($stmt->fetch()) {
            $error = 'Тема с таким названием уже существует';
        } else {
            try {
                $pdo->beginTransaction();
                
                if ($action === 'edit' && $topicId) {
                    // Обновление темы
                    $stmt = $pdo->prepare("
                        UPDATE topics SET 
                            name = ?, description = ?, category_id = ?
                        WHERE id = ? AND user_id = ?
                    ");
                    $stmt->execute([$name, $description, $categoryId, $topicId, $userId]);
                    
                    // Удаляем старые связи с метками
                    $stmt = $pdo->prepare("DELETE FROM topic_labels WHERE topic_id = ?");
                    $stmt->execute([$topicId]);
                    
                    $message = 'Тема обновлена';
                } else {
                    // Добавление новой темы
                    $stmt = $pdo->prepare("
                        INSERT INTO topics (user_id, name, description, category_id)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$userId, $name, $description, $categoryId]);
                    $topicId = $pdo->lastInsertId();
                    $message = 'Тема добавлена';
                }
                
                // Добавляем новые связи с метками
                if (!empty($selectedLabels)) {
                    $stmt = $pdo->prepare("INSERT INTO topic_labels (topic_id, label_id) VALUES (?, ?)");
                    foreach ($selectedLabels as $labelId) {
                        // Проверяем, что метка существует и принадлежит пользователю
                        $checkStmt = $pdo->prepare("SELECT id FROM labels WHERE id = ? AND user_id = ?");
                        $checkStmt->execute([$labelId, $userId]);
                        if ($checkStmt->fetch()) {
                            $stmt->execute([$topicId, $labelId]);
                        }
                    }
                }
                
                // Обработка ссылок
                if (isset($_POST['links']) && is_array($_POST['links'])) {
                    foreach ($_POST['links'] as $link) {
                        if (!empty($link['url'])) {
                            $url = trim($link['url']);
                            $title = trim($link['title'] ?? '');
                            
                            if ($action === 'edit' && isset($link['id']) && !empty($link['id'])) {
                                // Обновление существующей ссылки
                                $stmt = $pdo->prepare("
                                    UPDATE topic_links SET url = ?, title = ? 
                                    WHERE id = ? AND topic_id = ?
                                ");
                                $stmt->execute([$url, $title, $link['id'], $topicId]);
                            } else {
                                // Добавление новой ссылки
                                $stmt = $pdo->prepare("
                                    INSERT INTO topic_links (topic_id, url, title)
                                    VALUES (?, ?, ?)
                                ");
                                $stmt->execute([$topicId, $url, $title]);
                            }
                        }
                    }
                }
                
                // Удаление отмеченных ссылок
                if (isset($_POST['delete_links']) && is_array($_POST['delete_links'])) {
                    $stmt = $pdo->prepare("DELETE FROM topic_links WHERE id = ? AND topic_id = ?");
                    foreach ($_POST['delete_links'] as $linkId) {
                        $stmt->execute([$linkId, $topicId]);
                    }
                }
                
                $pdo->commit();
                header('Location: topics.php?message=saved');
                exit();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Ошибка при сохранении: ' . $e->getMessage();
            }
        }
    }
}

// Импорт из CSV
if (isset($_POST['import_csv']) && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    $parentCategoryId = $_POST['parent_category'] ?? null;
    
    if (($handle = fopen($file, "r")) !== FALSE) {
        // Определяем разделитель
        $firstLine = fgets($handle);
        rewind($handle);
        
        $delimiter = (strpos($firstLine, ';') !== false) ? ';' : ',';
        
        // Пропускаем заголовок
        $header = fgetcsv($handle, 1000, $delimiter);
        
        $imported = 0;
        $errors = 0;
        
        try {
            $pdo->beginTransaction();
            
            while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                if (count($data) >= 2) {
                    $name = trim($data[0]);
                    $description = $data[1] ?? '';
                    $categoryName = isset($data[2]) ? trim($data[2]) : null;
                    
                    if (empty($name)) {
                        $errors++;
                        continue;
                    }
                    
                    // Определяем категорию
                    $catId = $parentCategoryId;
                    if (!empty($categoryName) && !$parentCategoryId) {
                        // Ищем категорию по имени
                        $stmt = $pdo->prepare("SELECT id FROM categories WHERE user_id = ? AND name = ?");
                        $stmt->execute([$userId, $categoryName]);
                        $cat = $stmt->fetch();
                        if ($cat) {
                            $catId = $cat['id'];
                        }
                    }
                    
                    // Проверяем существование темы
                    $stmt = $pdo->prepare("SELECT id FROM topics WHERE user_id = ? AND name = ?");
                    $stmt->execute([$userId, $name]);
                    $existing = $stmt->fetch();
                    
                    if ($existing) {
                        // Обновляем существующую
                        $stmt = $pdo->prepare("
                            UPDATE topics SET description = ?, category_id = ?
                            WHERE id = ? AND user_id = ?
                        ");
                        $stmt->execute([$description, $catId, $existing['id'], $userId]);
                    } else {
                        // Добавляем новую
                        $stmt = $pdo->prepare("
                            INSERT INTO topics (user_id, name, description, category_id)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$userId, $name, $description, $catId]);
                    }
                    $imported++;
                } else {
                    $errors++;
                }
            }
            
            $pdo->commit();
            $message = "Импорт завершен. Добавлено/обновлено: $imported, ошибок: $errors";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Ошибка при импорте: ' . $e->getMessage();
        }
        
        fclose($handle);
    } else {
        $error = 'Ошибка при открытии файла';
    }
    header('Location: topics.php?message=imported');
    exit();
}

// Экспорт в CSV
if (isset($_GET['export_csv'])) {
    // Получаем все темы пользователя
    $stmt = $pdo->prepare("
        SELECT t.name, t.description, c.name as category
        FROM topics t
        LEFT JOIN categories c ON t.category_id = c.id
        WHERE t.user_id = ?
        ORDER BY t.name
    ");
    $stmt->execute([$userId]);
    $topics = $stmt->fetchAll();
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="topics_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    // Заголовки
    fputcsv($output, ['Название', 'Описание', 'Категория']);
    
    // Данные
    foreach ($topics as $topic) {
        fputcsv($output, [
            $topic['name'],
            $topic['description'],
            $topic['category'] ?? ''
        ]);
    }
    fclose($output);
    exit();
}

// Экспорт в JSON
if (isset($_GET['export_json'])) {
    // Получаем все темы с метками и ссылками
    $stmt = $pdo->prepare("
        SELECT 
            t.id,
            t.name,
            t.description,
            c.name as category,
            (SELECT JSON_ARRAYAGG(JSON_OBJECT('url', url, 'title', title)) 
             FROM topic_links WHERE topic_id = t.id) as links,
            (SELECT JSON_ARRAYAGG(l.name) 
             FROM topic_labels tl
             JOIN labels l ON tl.label_id = l.id
             WHERE tl.topic_id = t.id) as labels
        FROM topics t
        LEFT JOIN categories c ON t.category_id = c.id
        WHERE t.user_id = ?
        ORDER BY t.name
    ");
    $stmt->execute([$userId]);
    $topics = $stmt->fetchAll();
    
    $exportData = [
        'export_date' => date('Y-m-d H:i:s'),
        'user_id' => $userId,
        'topics' => $topics
    ];
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="topics_' . date('Y-m-d') . '.json"');
    echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

// Импорт из JSON
if (isset($_POST['import_json']) && isset($_FILES['json_file'])) {
    $file = $_FILES['json_file']['tmp_name'];
    $jsonData = file_get_contents($file);
    $data = json_decode($jsonData, true);
    
    if ($data && isset($data['topics']) && is_array($data['topics'])) {
        try {
            $pdo->beginTransaction();
            
            $imported = 0;
            $errors = 0;
            
            foreach ($data['topics'] as $topicData) {
                $name = trim($topicData['name'] ?? '');
                $description = trim($topicData['description'] ?? '');
                $categoryName = trim($topicData['category'] ?? '');
                $links = $topicData['links'] ?? [];
                $labels = $topicData['labels'] ?? [];
                
                if (empty($name)) {
                    $errors++;
                    continue;
                }
                
                // Определяем категорию
                $categoryId = null;
                if (!empty($categoryName)) {
                    $stmt = $pdo->prepare("SELECT id FROM categories WHERE user_id = ? AND name = ?");
                    $stmt->execute([$userId, $categoryName]);
                    $cat = $stmt->fetch();
                    if ($cat) {
                        $categoryId = $cat['id'];
                    }
                }
                
                // Проверяем существование темы
                $stmt = $pdo->prepare("SELECT id FROM topics WHERE user_id = ? AND name = ?");
                $stmt->execute([$userId, $name]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    $topicId = $existing['id'];
                    // Обновляем тему
                    $stmt = $pdo->prepare("
                        UPDATE topics SET description = ?, category_id = ?
                        WHERE id = ? AND user_id = ?
                    ");
                    $stmt->execute([$description, $categoryId, $topicId, $userId]);
                    
                    // Удаляем старые ссылки
                    $stmt = $pdo->prepare("DELETE FROM topic_links WHERE topic_id = ?");
                    $stmt->execute([$topicId]);
                    
                    // Удаляем старые метки
                    $stmt = $pdo->prepare("DELETE FROM topic_labels WHERE topic_id = ?");
                    $stmt->execute([$topicId]);
                } else {
                    // Добавляем новую тему
                    $stmt = $pdo->prepare("
                        INSERT INTO topics (user_id, name, description, category_id)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$userId, $name, $description, $categoryId]);
                    $topicId = $pdo->lastInsertId();
                }
                
                // Добавляем ссылки
                if (!empty($links)) {
                    $stmt = $pdo->prepare("INSERT INTO topic_links (topic_id, url, title) VALUES (?, ?, ?)");
                    foreach ($links as $link) {
                        if (is_array($link)) {
                            $url = $link['url'] ?? '';
                            $title = $link['title'] ?? '';
                        } else {
                            $url = $link;
                            $title = '';
                        }
                        if (!empty($url)) {
                            $stmt->execute([$topicId, $url, $title]);
                        }
                    }
                }
                
                // Добавляем метки
                if (!empty($labels)) {
                    $stmt = $pdo->prepare("
                        INSERT INTO topic_labels (topic_id, label_id)
                        SELECT ?, id FROM labels WHERE user_id = ? AND name = ?
                    ");
                    foreach ($labels as $labelName) {
                        $stmt->execute([$topicId, $userId, $labelName]);
                    }
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
    header('Location: topics.php?message=imported');
    exit();
}

// Получение списка тем с фильтрацией
$filterCategory = $_GET['filter_category'] ?? '';
$filterLabel = $_GET['filter_label'] ?? '';
$sortBy = $_GET['sort_by'] ?? 'name';
$searchQuery = $_GET['search'] ?? '';

$query = "
    SELECT t.*, c.name as category_name, c.color as category_color,
           GROUP_CONCAT(DISTINCT l.name) as labels,
           GROUP_CONCAT(DISTINCT l.id) as label_ids,
           (SELECT COUNT(*) FROM topic_links WHERE topic_id = t.id) as links_count,
           (SELECT COUNT(*) FROM lesson_topics WHERE topic_id = t.id) as usage_count
    FROM topics t
    LEFT JOIN categories c ON t.category_id = c.id
    LEFT JOIN topic_labels tl ON t.id = tl.topic_id
    LEFT JOIN labels l ON tl.label_id = l.id
    WHERE t.user_id = ?
";
$params = [$userId];

if (!empty($filterCategory)) {
    $query .= " AND t.category_id = ?";
    $params[] = $filterCategory;
}

if (!empty($filterLabel)) {
    $query .= " AND t.id IN (SELECT topic_id FROM topic_labels WHERE label_id = ?)";
    $params[] = $filterLabel;
}

if (!empty($searchQuery)) {
    $query .= " AND (t.name LIKE ? OR t.description LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

$query .= " GROUP BY t.id";

if ($sortBy === 'name') {
    $query .= " ORDER BY t.name";
} elseif ($sortBy === 'category') {
    $query .= " ORDER BY c.name, t.name";
} elseif ($sortBy === 'usage') {
    $query .= " ORDER BY usage_count DESC, t.name";
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$topics = $stmt->fetchAll();

// Получение данных для редактирования
$editTopic = null;
$editTopicLinks = [];
$editTopicLabels = [];

if ($action === 'edit' && $topicId) {
    $stmt = $pdo->prepare("SELECT * FROM topics WHERE id = ? AND user_id = ?");
    $stmt->execute([$topicId, $userId]);
    $editTopic = $stmt->fetch();
    
    if ($editTopic) {
        // Получаем ссылки темы
        $stmt = $pdo->prepare("SELECT * FROM topic_links WHERE topic_id = ? ORDER BY id");
        $stmt->execute([$topicId]);
        $editTopicLinks = $stmt->fetchAll();
        
        // Получаем метки темы
        $stmt = $pdo->prepare("
            SELECT l.id, l.name 
            FROM topic_labels tl
            JOIN labels l ON tl.label_id = l.id
            WHERE tl.topic_id = ?
        ");
        $stmt->execute([$topicId]);
        $editTopicLabels = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        header('Location: topics.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Банк тем - Дневник репетитора</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .topic-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-left: 4px solid;
            position: relative;
        }
        .topic-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .topic-name {
            font-size: 1.2em;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .topic-category {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            color: white;
            margin-bottom: 10px;
        }
        .topic-description {
            color: #666;
            margin-bottom: 15px;
            font-size: 0.95em;
        }
        .topic-links {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }
        .topic-link-item {
            display: flex;
            align-items: center;
            padding: 5px 0;
        }
        .topic-link-item a {
            color: #667eea;
            text-decoration: none;
            margin-left: 5px;
        }
        .topic-link-item a:hover {
            text-decoration: underline;
        }
        .topic-meta {
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
        .btn-outline-danger {
            border-color: #dc3545;
            color: #dc3545;
        }
        .btn-outline-danger:hover {
            background: #dc3545;
            color: white;
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
        .link-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
        }
        .usage-badge {
            background: #28a745;
            color: white;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 0.75em;
            margin-left: 10px;
        }
        .labels-section {
            max-height: 300px;
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
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>
    
    <div class="container-fluid py-4">
        <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                $messages = [
                    'saved' => 'Тема успешно сохранена',
                    'deleted' => 'Тема удалена',
                    'force_deleted' => 'Тема и все связи удалены',
                    'cleared' => 'Все темы удалены',
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
                <h2><i class="bi bi-book"></i> Банк тем</h2>
                <div>
                    <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importCsvModal">
                        <i class="bi bi-upload"></i> Импорт CSV
                    </button>
                    <button type="button" class="btn btn-info me-2" data-bs-toggle="modal" data-bs-target="#importJsonModal">
                        <i class="bi bi-filetype-json"></i> Импорт JSON
                    </button>
                    <a href="?export_csv=1" class="btn btn-warning me-2">
                        <i class="bi bi-filetype-csv"></i> Экспорт CSV
                    </a>
                    <a href="?export_json=1" class="btn btn-secondary me-2">
                        <i class="bi bi-filetype-json"></i> Экспорт JSON
                    </a>
                    <a href="?action=add" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Добавить тему
                    </a>
                </div>
            </div>
            
            <!-- Статистика -->
            <div class="stats-card mb-4">
                <div class="row">
                    <div class="col-md-3">
                        <div class="text-center">
                            <h3 class="mb-0"><?php echo count($topics); ?></h3>
                            <small>Всего тем</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h3 class="mb-0">
                                <?php 
                                $totalLinks = 0;
                                foreach ($topics as $t) {
                                    $totalLinks += $t['links_count'];
                                }
                                echo $totalLinks;
                                ?>
                            </h3>
                            <small>Всего ссылок</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h3 class="mb-0">
                                <?php 
                                $totalUsage = 0;
                                foreach ($topics as $t) {
                                    $totalUsage += $t['usage_count'];
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
                </div>
            </div>
            
            <!-- Фильтры -->
            <div class="filter-panel">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Поиск</label>
                        <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Название или описание">
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
                        <label class="form-label">Сортировка</label>
                        <select name="sort_by" class="form-select">
                            <option value="name" <?php echo $sortBy == 'name' ? 'selected' : ''; ?>>По названию</option>
                            <option value="category" <?php echo $sortBy == 'category' ? 'selected' : ''; ?>>По категории</option>
                            <option value="usage" <?php echo $sortBy == 'usage' ? 'selected' : ''; ?>>По популярности</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Применить
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Список тем -->
            <div class="row">
                <?php if (empty($topics)): ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center py-5">
                            <i class="bi bi-book" style="font-size: 3rem;"></i>
                            <h4 class="mt-3">Темы не найдены</h4>
                            <p>Создайте первую тему, нажав кнопку "Добавить тему"</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($topics as $topic): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="topic-card" style="border-left-color: <?php echo $topic['category_color'] ?? '#808080'; ?>">
                                <div class="topic-name">
                                    <?php echo htmlspecialchars($topic['name']); ?>
                                    <?php if ($topic['usage_count'] > 0): ?>
                                        <span class="usage-badge" title="Используется в занятиях">
                                            <?php echo $topic['usage_count']; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($topic['category_name']): ?>
                                    <div class="topic-category" style="background: <?php echo $topic['category_color'] ?? '#808080'; ?>">
                                        <?php echo htmlspecialchars($topic['category_name']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($topic['description'])): ?>
                                    <div class="topic-description">
                                        <?php echo nl2br(htmlspecialchars($topic['description'])); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Отображение меток темы -->
                                <?php if (!empty($topic['labels'])): ?>
                                    <div class="mb-2">
                                        <?php 
                                        $labels = explode(',', $topic['labels']);
                                        $labelIds = explode(',', $topic['label_ids'] ?? '');
                                        
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
                                
                                <?php
                                // Получаем ссылки для этой темы
                                $stmt = $pdo->prepare("SELECT * FROM topic_links WHERE topic_id = ? LIMIT 3");
                                $stmt->execute([$topic['id']]);
                                $links = $stmt->fetchAll();
                                ?>
                                
                                <?php if (!empty($links)): ?>
                                    <div class="topic-links">
                                        <small class="text-muted"><i class="bi bi-link"></i> Ссылки:</small>
                                        <?php foreach ($links as $link): ?>
                                            <div class="topic-link-item">
                                                <i class="bi bi-link-45deg"></i>
                                                <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank">
                                                    <?php echo htmlspecialchars($link['title'] ?: $link['url']); ?>
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if ($topic['links_count'] > 3): ?>
                                            <small class="text-muted">и еще <?php echo $topic['links_count'] - 3; ?> ссылок...</small>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="topic-meta">
                                    <small><i class="bi bi-link"></i> <?php echo $topic['links_count']; ?></small>
                                    <small><i class="bi bi-tag"></i> <?php echo substr_count($topic['labels'] ?? '', ',') + 1; ?></small>
                                </div>
                                
                                <div class="mt-3 d-flex justify-content-end gap-2">
                                    <a href="?action=edit&id=<?php echo $topic['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i> Ред.
                                    </a>
                                    <a href="?delete=1&id=<?php echo $topic['id']; ?>" class="btn btn-sm btn-outline-danger" 
                                       onclick="return confirm('Удалить тему?')">
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
                <button type="button" class="btn btn-danger" onclick="if(confirm('Очистить все темы?')) window.location.href='?clear_all=1'" title="Очистить все темы">
                    <i class="bi bi-trash"></i>
                </button>
                <a href="?action=add" class="btn btn-primary" title="Добавить тему">
                    <i class="bi bi-plus"></i>
                </a>
            </div>
            
        <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <!-- Форма добавления/редактирования темы -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="bi bi-<?php echo $action === 'add' ? 'plus-circle' : 'pencil'; ?>"></i>
                                <?php echo $action === 'add' ? 'Добавление темы' : 'Редактирование темы'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" id="topicForm">
                                <?php if ($action === 'edit' && $editTopic): ?>
                                    <input type="hidden" name="topic_id" value="<?php echo $editTopic['id']; ?>">
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label class="form-label">Название темы *</label>
                                    <input type="text" name="name" class="form-control" 
                                           value="<?php echo $editTopic ? htmlspecialchars($editTopic['name']) : ''; ?>" 
                                           required maxlength="255">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Описание</label>
                                    <textarea name="description" class="form-control" rows="3"><?php echo $editTopic ? htmlspecialchars($editTopic['description'] ?? '') : ''; ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Категория</label>
                                    <select name="category_id" class="form-select">
                                        <option value="">Без категории</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" 
                                                <?php echo ($editTopic && $editTopic['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Выбор меток из банка меток -->
                                <div class="mb-3">
                                    <label class="form-label">Метки <small class="text-muted">(из банка меток)</small></label>
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
                                                               <?php echo ($editTopicLabels && in_array($label['id'], $editTopicLabels)) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="label_<?php echo $label['id']; ?>">
                                                            <?php echo htmlspecialchars($label['name']); ?>
                                                            <?php if ($label['label_type'] === 'topic'): ?>
                                                                <span class="badge bg-info">тема</span>
                                                            <?php endif; ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle"></i> 
                                        Отображаются только метки типа "Темы" и "Общие". 
                                        <a href="labels.php" target="_blank">Управлять метками</a>
                                    </small>
                                </div>
                                
                                <!-- Ссылки на ресурсы -->
                                <div class="mb-3">
                                    <label class="form-label">Ссылки на ресурсы</label>
                                    <div id="links-container">
                                        <?php if ($action === 'edit' && !empty($editTopicLinks)): ?>
                                            <?php foreach ($editTopicLinks as $index => $link): ?>
                                                <div class="link-item">
                                                    <input type="hidden" name="links[<?php echo $index; ?>][id]" value="<?php echo $link['id']; ?>">
                                                    <div class="row">
                                                        <div class="col-md-5">
                                                            <input type="url" name="links[<?php echo $index; ?>][url]" 
                                                                   class="form-control" placeholder="https://..." 
                                                                   value="<?php echo htmlspecialchars($link['url']); ?>" required>
                                                        </div>
                                                        <div class="col-md-5">
                                                            <input type="text" name="links[<?php echo $index; ?>][title]" 
                                                                   class="form-control" placeholder="Название (необязательно)"
                                                                   value="<?php echo htmlspecialchars($link['title'] ?? ''); ?>">
                                                        </div>
                                                        <div class="col-md-2">
                                                            <button type="button" class="btn btn-outline-danger remove-link" onclick="this.closest('.link-item').remove()">
                                                                <i class="bi bi-x"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="link-item">
                                                <div class="row">
                                                    <div class="col-md-5">
                                                        <input type="url" name="links[0][url]" class="form-control" placeholder="https://...">
                                                    </div>
                                                    <div class="col-md-5">
                                                        <input type="text" name="links[0][title]" class="form-control" placeholder="Название (необязательно)">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <button type="button" class="btn btn-outline-danger remove-link" onclick="this.closest('.link-item').remove()">
                                                            <i class="bi bi-x"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addLink()">
                                        <i class="bi bi-plus"></i> Добавить ссылку
                                    </button>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <a href="topics.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left"></i> Назад
                                    </a>
                                    <button type="submit" name="save_topic" class="btn btn-primary">
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
                            <p><strong>Советы:</strong></p>
                            <ul class="small">
                                <li>Название темы должно быть уникальным</li>
                                <li>Можно добавить несколько ссылок на ресурсы</li>
                                <li>Метки помогут в поиске и фильтрации</li>
                                <li>Категория необязательна</li>
                                <li>Для удобства метки сгруппированы по категориям</li>
                            </ul>
                            
                            <!-- Быстрый переход в банк меток -->
                            <div class="alert alert-info small">
                                <i class="bi bi-tags"></i>
                                <a href="labels.php" target="_blank" class="alert-link">Перейти в банк меток</a> 
                                для создания новых меток.
                            </div>
                            
                            <?php if ($action === 'edit' && $editTopic): ?>
                                <hr>
                                <p><strong>Статистика:</strong></p>
                                <p>Использований в занятиях: <?php 
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_topics WHERE topic_id = ?");
                                    $stmt->execute([$editTopic['id']]);
                                    echo $stmt->fetchColumn();
                                ?></p>
                                <p>Ссылок: <?php echo count($editTopicLinks); ?></p>
                                <p>Меток: <?php echo count($editTopicLabels); ?></p>
                                <p>Создана: <?php echo date('d.m.Y H:i', strtotime($editTopic['created_at'])); ?></p>
                                <p>Обновлена: <?php echo date('d.m.Y H:i', strtotime($editTopic['updated_at'])); ?></p>
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
    
    <!-- Модальное окно импорта CSV -->
    <div class="modal fade" id="importCsvModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Импорт тем из CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Выберите CSV файл</label>
                            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Родительская категория (для всех тем)</label>
                            <select name="parent_category" class="form-select">
                                <option value="">Оставить как в файле</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Пример формата CSV:</label>
                            <pre class="bg-light p-2 rounded small">
Название,Описание,Категория
"Квадратные уравнения","Решение квадратных уравнений","Алгебра"
"Времена глаголов","Present, Past, Future","Английский язык"
                            </pre>
                            <p class="text-muted small mb-0">
                                <i class="bi bi-info-circle"></i> 
                                Разделитель может быть запятой или точкой с запятой.<br>
                                Если категория не существует, она будет пропущена.
                            </p>
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
    
    <!-- Модальное окно импорта JSON -->
    <div class="modal fade" id="importJsonModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Импорт тем из JSON</h5>
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
  "topics": [
    {
      "name": "Квадратные уравнения",
      "description": "Решение квадратных уравнений",
      "category": "Алгебра",
      "links": [
        {"url": "https://example.com", "title": "Видеоурок"}
      ],
      "labels": ["Важно", "ОГЭ"]
    }
  ]
}
                            </pre>
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
        let linkIndex = <?php echo ($action === 'edit' && !empty($editTopicLinks)) ? count($editTopicLinks) : 1; ?>;
        
        function addLink() {
            const container = document.getElementById('links-container');
            const newLink = document.createElement('div');
            newLink.className = 'link-item';
            newLink.innerHTML = `
                <div class="row">
                    <div class="col-md-5">
                        <input type="url" name="links[${linkIndex}][url]" class="form-control" placeholder="https://...">
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="links[${linkIndex}][title]" class="form-control" placeholder="Название (необязательно)">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-outline-danger remove-link" onclick="this.closest('.link-item').remove()">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                </div>
            `;
            container.appendChild(newLink);
            linkIndex++;
        }
        
        // Предпросмотр выбранных меток
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('input[name="labels[]"]');
            const preview = document.getElementById('selected-labels-preview');
            
            function updatePreview() {
                const selected = [];
                checkboxes.forEach(cb => {
                    if (cb.checked) {
                        const label = document.querySelector(`label[for="${cb.id}"]`).innerText.trim();
                        selected.push(`<span class="badge bg-primary">${label}</span>`);
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