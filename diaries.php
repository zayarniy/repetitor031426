<?php
require_once 'config.php';
requireAuth();

$currentUser = getCurrentUser($pdo);
$userId = $currentUser['id'];
$message = '';
$error = '';

// Генерация уникальной публичной ссылки
function generatePublicLink() {
    return bin2hex(random_bytes(16));
}

// Обработка действий
$action = $_GET['action'] ?? 'list';
$diaryId = $_GET['id'] ?? 0;

// Получение категорий для фильтрации
$stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? OR user_id IS NULL ORDER BY name");
$stmt->execute([$userId]);
$categories = $stmt->fetchAll();

// Получение списка учеников для назначения дневника
$stmt = $pdo->prepare("SELECT id, last_name, first_name, middle_name, class FROM students WHERE user_id = ? AND is_active = 1 ORDER BY last_name, first_name");
$stmt->execute([$userId]);
$students = $stmt->fetchAll();

// Генерация публичной ссылки
if (isset($_GET['generate_link']) && $diaryId) {
    $publicLink = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("UPDATE diaries SET public_link = ? WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$publicLink, $diaryId, $userId])) {
        header('Location: diaries.php?action=view&id=' . $diaryId . '&message=link_generated');
        exit();
    }
}

// Удаление публичной ссылки
if (isset($_GET['remove_link']) && $diaryId) {
    $stmt = $pdo->prepare("UPDATE diaries SET public_link = NULL WHERE id = ? AND user_id = ?");
    $stmt->execute([$diaryId, $userId]);
    header('Location: diaries.php?action=view&id=' . $diaryId . '&message=link_removed');
    exit();
}

// Создание копии дневника
if (isset($_GET['copy']) && $diaryId) {
    try {
        $pdo->beginTransaction();
        
        // Получаем исходный дневник
        $stmt = $pdo->prepare("SELECT * FROM diaries WHERE id = ? AND user_id = ?");
        $stmt->execute([$diaryId, $userId]);
        $sourceDiary = $stmt->fetch();
        
        if ($sourceDiary) {
            // Создаем копию
            $newName = $sourceDiary['name'] . ' (копия)';
            $stmt = $pdo->prepare("
                INSERT INTO diaries (user_id, student_id, category_id, name, description, lesson_cost, lesson_duration, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $userId,
                $sourceDiary['student_id'],
                $sourceDiary['category_id'],
                $newName,
                $sourceDiary['description'],
                $sourceDiary['lesson_cost'],
                $sourceDiary['lesson_duration']
            ]);
            $newDiaryId = $pdo->lastInsertId();
            
            // Копируем комментарии
            $stmt = $pdo->prepare("SELECT * FROM diary_comments WHERE diary_id = ?");
            $stmt->execute([$diaryId]);
            $comments = $stmt->fetchAll();
            
            if (!empty($comments)) {
                $stmt = $pdo->prepare("INSERT INTO diary_comments (diary_id, user_id, comment, created_at) VALUES (?, ?, ?, ?)");
                foreach ($comments as $comment) {
                    $stmt->execute([$newDiaryId, $comment['user_id'], $comment['comment'], $comment['created_at']]);
                }
            }
            
            $pdo->commit();
            header('Location: diaries.php?message=copied');
            exit();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Ошибка при копировании: ' . $e->getMessage();
    }
}

// Удаление дневника
if (isset($_GET['delete']) && $diaryId) {
    try {
        // Проверяем, есть ли занятия в дневнике
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE diary_id = ?");
        $stmt->execute([$diaryId]);
        $lessonCount = $stmt->fetchColumn();
        
        if ($lessonCount > 0) {
            // Если есть занятия, запрашиваем подтверждение
            $_SESSION['delete_diary_' . $diaryId] = true;
            header('Location: diaries.php?confirm_delete=' . $diaryId);
            exit();
        } else {
            // Если нет занятий, удаляем
            $pdo->beginTransaction();
            
            // Удаляем комментарии
            $stmt = $pdo->prepare("DELETE FROM diary_comments WHERE diary_id = ?");
            $stmt->execute([$diaryId]);
            
            // Удаляем дневник
            $stmt = $pdo->prepare("DELETE FROM diaries WHERE id = ? AND user_id = ?");
            $stmt->execute([$diaryId, $userId]);
            
            $pdo->commit();
            header('Location: diaries.php?message=deleted');
            exit();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Ошибка при удалении: ' . $e->getMessage();
    }
}

// Подтверждение удаления дневника с занятиями
if (isset($_GET['confirm_delete']) && isset($_SESSION['delete_diary_' . $_GET['confirm_delete']])) {
    $deleteId = $_GET['confirm_delete'];
    unset($_SESSION['delete_diary_' . $deleteId]);
    
    // Получаем информацию о дневнике
    $stmt = $pdo->prepare("SELECT * FROM diaries WHERE id = ? AND user_id = ?");
    $stmt->execute([$deleteId, $userId]);
    $diary = $stmt->fetch();
    
    if ($diary) {
        // Получаем количество занятий
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE diary_id = ?");
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
                        <h5 class="mb-0">Подтверждение удаления дневника</h5>
                    </div>
                    <div class="card-body">
                        <p class="lead">Дневник "<?php echo htmlspecialchars($diary['name']); ?>" содержит <?php echo $lessonCount; ?> занятий.</p>
                        <p class="text-danger">При удалении дневника все занятия будут также удалены!</p>
                        <div class="d-flex justify-content-between">
                            <a href="diaries.php" class="btn btn-secondary">Отмена</a>
                            <a href="?force_delete=<?php echo $deleteId; ?>" class="btn btn-danger">Да, удалить дневник и все занятия</a>
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

// Принудительное удаление с занятиями
if (isset($_GET['force_delete']) && $diaryId) {
    try {
        $pdo->beginTransaction();
        
        // Удаляем занятия (каскадно удалятся все связи)
        $stmt = $pdo->prepare("DELETE FROM lessons WHERE diary_id = ?");
        $stmt->execute([$diaryId]);
        
        // Удаляем комментарии
        $stmt = $pdo->prepare("DELETE FROM diary_comments WHERE diary_id = ?");
        $stmt->execute([$diaryId]);
        
        // Удаляем дневник
        $stmt = $pdo->prepare("DELETE FROM diaries WHERE id = ? AND user_id = ?");
        $stmt->execute([$diaryId, $userId]);
        
        $pdo->commit();
        header('Location: diaries.php?message=force_deleted');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Ошибка при удалении: ' . $e->getMessage();
    }
}

// Добавление/редактирование дневника
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_diary'])) {
    $name = trim($_POST['name'] ?? '');
    $studentId = !empty($_POST['student_id']) ? intval($_POST['student_id']) : null;
    $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $description = trim($_POST['description'] ?? '');
    $lessonCost = !empty($_POST['lesson_cost']) ? floatval($_POST['lesson_cost']) : null;
    $lessonDuration = !empty($_POST['lesson_duration']) ? intval($_POST['lesson_duration']) : 60;
    $comment = trim($_POST['comment'] ?? '');
    
    if (empty($name)) {
        $error = 'Название дневника обязательно';
    } elseif (empty($studentId)) {
        $error = 'Необходимо выбрать ученика';
    } else {
        try {
            $pdo->beginTransaction();
            
            if ($action === 'edit' && $diaryId) {
                // Обновление дневника
                $stmt = $pdo->prepare("
                    UPDATE diaries SET 
                        student_id = ?, category_id = ?, name = ?, description = ?,
                        lesson_cost = ?, lesson_duration = ?
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$studentId, $categoryId, $name, $description, $lessonCost, $lessonDuration, $diaryId, $userId]);
                
                // Добавляем комментарий об изменении
                if (!empty($comment)) {
                    $stmt = $pdo->prepare("INSERT INTO diary_comments (diary_id, user_id, comment) VALUES (?, ?, ?)");
                    $stmt->execute([$diaryId, $userId, $comment]);
                }
                
                $message = 'Дневник обновлен';
            } else {
                // Добавление нового дневника
                $stmt = $pdo->prepare("
                    INSERT INTO diaries (user_id, student_id, category_id, name, description, lesson_cost, lesson_duration)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$userId, $studentId, $categoryId, $name, $description, $lessonCost, $lessonDuration]);
                $diaryId = $pdo->lastInsertId();
                
                // Добавляем комментарий о создании
                if (!empty($comment)) {
                    $stmt = $pdo->prepare("INSERT INTO diary_comments (diary_id, user_id, comment) VALUES (?, ?, ?)");
                    $stmt->execute([$diaryId, $userId, $comment]);
                }
                
                $message = 'Дневник добавлен';
            }
            
            $pdo->commit();
            header('Location: diaries.php?message=saved');
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Ошибка при сохранении: ' . $e->getMessage();
        }
    }
}

// Импорт из CSV
if (isset($_POST['import_csv']) && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    
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
                if (count($data) >= 4) {
                    $studentName = trim($data[0]); // ФИО ученика
                    $name = trim($data[1]); // Название дневника
                    $description = $data[2] ?? '';
                    $lessonCost = !empty($data[3]) ? floatval($data[3]) : null;
                    $lessonDuration = !empty($data[4]) ? intval($data[4]) : 60;
                    $categoryName = $data[5] ?? '';
                    
                    if (empty($name) || empty($studentName)) {
                        $errors++;
                        continue;
                    }
                    
                    // Ищем ученика по ФИО
                    $nameParts = explode(' ', $studentName, 3);
                    $lastName = $nameParts[0] ?? '';
                    $firstName = $nameParts[1] ?? '';
                    $middleName = $nameParts[2] ?? '';
                    
                    $stmt = $pdo->prepare("
                        SELECT id FROM students 
                        WHERE user_id = ? AND last_name LIKE ? AND first_name LIKE ? 
                        AND (middle_name LIKE ? OR (middle_name IS NULL AND ? = ''))
                    ");
                    $stmt->execute([$userId, "%$lastName%", "%$firstName%", "%$middleName%", $middleName]);
                    $student = $stmt->fetch();
                    
                    if (!$student) {
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
                    
                    // Проверяем существование дневника
                    $stmt = $pdo->prepare("SELECT id FROM diaries WHERE user_id = ? AND name = ? AND student_id = ?");
                    $stmt->execute([$userId, $name, $student['id']]);
                    $existing = $stmt->fetch();
                    
                    if ($existing) {
                        // Обновляем существующий
                        $stmt = $pdo->prepare("
                            UPDATE diaries SET 
                                description = ?, lesson_cost = ?, lesson_duration = ?, category_id = ?
                            WHERE id = ? AND user_id = ?
                        ");
                        $stmt->execute([$description, $lessonCost, $lessonDuration, $categoryId, $existing['id'], $userId]);
                    } else {
                        // Добавляем новый
                        $stmt = $pdo->prepare("
                            INSERT INTO diaries (user_id, student_id, category_id, name, description, lesson_cost, lesson_duration)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$userId, $student['id'], $categoryId, $name, $description, $lessonCost, $lessonDuration]);
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
    header('Location: diaries.php?message=imported');
    exit();
}

// Экспорт в CSV
if (isset($_GET['export_csv'])) {
    // Получаем все дневники пользователя
    $stmt = $pdo->prepare("
        SELECT 
            CONCAT(s.last_name, ' ', s.first_name, ' ', COALESCE(s.middle_name, '')) as student_name,
            d.name,
            d.description,
            d.lesson_cost,
            d.lesson_duration,
            c.name as category
        FROM diaries d
        JOIN students s ON d.student_id = s.id
        LEFT JOIN categories c ON d.category_id = c.id
        WHERE d.user_id = ?
        ORDER BY s.last_name, d.name
    ");
    $stmt->execute([$userId]);
    $diaries = $stmt->fetchAll();
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="diaries_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    // Заголовки
    fputcsv($output, ['Ученик', 'Название дневника', 'Описание', 'Стоимость занятия', 'Длительность (мин)', 'Категория']);
    
    // Данные
    foreach ($diaries as $diary) {
        fputcsv($output, [
            $diary['student_name'],
            $diary['name'],
            $diary['description'],
            $diary['lesson_cost'],
            $diary['lesson_duration'],
            $diary['category'] ?? ''
        ]);
    }
    fclose($output);
    exit();
}

// Экспорт в JSON
if (isset($_GET['export_json'])) {
    // Получаем все дневники с комментариями
    $stmt = $pdo->prepare("
        SELECT 
            d.id,
            CONCAT(s.last_name, ' ', s.first_name, ' ', COALESCE(s.middle_name, '')) as student_name,
            d.name,
            d.description,
            d.lesson_cost,
            d.lesson_duration,
            d.public_link,
            c.name as category,
            d.created_at,
            d.updated_at,
            (SELECT JSON_ARRAYAGG(JSON_OBJECT('comment', comment, 'created_at', created_at)) 
             FROM diary_comments WHERE diary_id = d.id) as comments
        FROM diaries d
        JOIN students s ON d.student_id = s.id
        LEFT JOIN categories c ON d.category_id = c.id
        WHERE d.user_id = ?
        ORDER BY d.created_at DESC
    ");
    $stmt->execute([$userId]);
    $diaries = $stmt->fetchAll();
    
    $exportData = [
        'export_date' => date('Y-m-d H:i:s'),
        'user_id' => $userId,
        'diaries' => $diaries
    ];
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="diaries_' . date('Y-m-d') . '.json"');
    echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

// Импорт из JSON
if (isset($_POST['import_json']) && isset($_FILES['json_file'])) {
    $file = $_FILES['json_file']['tmp_name'];
    $jsonData = file_get_contents($file);
    $data = json_decode($jsonData, true);
    
    if ($data && isset($data['diaries']) && is_array($data['diaries'])) {
        try {
            $pdo->beginTransaction();
            
            $imported = 0;
            $errors = 0;
            
            foreach ($data['diaries'] as $diaryData) {
                $studentName = $diaryData['student_name'] ?? '';
                $name = trim($diaryData['name'] ?? '');
                $description = trim($diaryData['description'] ?? '');
                $lessonCost = $diaryData['lesson_cost'] ?? null;
                $lessonDuration = $diaryData['lesson_duration'] ?? 60;
                $categoryName = $diaryData['category'] ?? '';
                $comments = $diaryData['comments'] ?? [];
                
                if (empty($name) || empty($studentName)) {
                    $errors++;
                    continue;
                }
                
                // Ищем ученика по ФИО
                $nameParts = explode(' ', $studentName, 3);
                $lastName = $nameParts[0] ?? '';
                $firstName = $nameParts[1] ?? '';
                $middleName = $nameParts[2] ?? '';
                
                $stmt = $pdo->prepare("
                    SELECT id FROM students 
                    WHERE user_id = ? AND last_name LIKE ? AND first_name LIKE ? 
                    AND (middle_name LIKE ? OR (middle_name IS NULL AND ? = ''))
                ");
                $stmt->execute([$userId, "%$lastName%", "%$firstName%", "%$middleName%", $middleName]);
                $student = $stmt->fetch();
                
                if (!$student) {
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
                
                // Проверяем существование дневника
                $stmt = $pdo->prepare("SELECT id FROM diaries WHERE user_id = ? AND name = ? AND student_id = ?");
                $stmt->execute([$userId, $name, $student['id']]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    $diaryId = $existing['id'];
                    // Обновляем существующий
                    $stmt = $pdo->prepare("
                        UPDATE diaries SET 
                            description = ?, lesson_cost = ?, lesson_duration = ?, category_id = ?
                        WHERE id = ? AND user_id = ?
                    ");
                    $stmt->execute([$description, $lessonCost, $lessonDuration, $categoryId, $diaryId, $userId]);
                } else {
                    // Добавляем новый
                    $stmt = $pdo->prepare("
                        INSERT INTO diaries (user_id, student_id, category_id, name, description, lesson_cost, lesson_duration)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$userId, $student['id'], $categoryId, $name, $description, $lessonCost, $lessonDuration]);
                    $diaryId = $pdo->lastInsertId();
                }
                
                // Добавляем комментарии
                if (!empty($comments)) {
                    $stmt = $pdo->prepare("INSERT INTO diary_comments (diary_id, user_id, comment, created_at) VALUES (?, ?, ?, ?)");
                    foreach ($comments as $comment) {
                        if (is_array($comment)) {
                            $commentText = $comment['comment'] ?? '';
                            $commentDate = $comment['created_at'] ?? date('Y-m-d H:i:s');
                            if (!empty($commentText)) {
                                $stmt->execute([$diaryId, $userId, $commentText, $commentDate]);
                            }
                        }
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
    header('Location: diaries.php?message=imported');
    exit();
}

// Получение списка дневников с фильтрацией
$filterCategory = $_GET['filter_category'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$filterStudent = $_GET['student'] ?? '';

$query = "
    SELECT 
        d.*,
        s.last_name as student_last_name,
        s.first_name as student_first_name,
        s.middle_name as student_middle_name,
        s.class as student_class,
        c.name as category_name,
        c.color as category_color,
        (SELECT COUNT(*) FROM lessons WHERE diary_id = d.id) as lessons_count,
        (SELECT COUNT(*) FROM diary_comments WHERE diary_id = d.id) as comments_count
    FROM diaries d
    JOIN students s ON d.student_id = s.id
    LEFT JOIN categories c ON d.category_id = c.id
    WHERE d.user_id = ?
";
$params = [$userId];

if (!empty($filterCategory)) {
    $query .= " AND d.category_id = ?";
    $params[] = $filterCategory;
}

if (!empty($filterStudent)) {
    $query .= " AND d.student_id = ?";
    $params[] = $filterStudent;
}

if (!empty($filterDateFrom)) {
    $query .= " AND DATE(d.created_at) >= ?";
    $params[] = $filterDateFrom;
}

if (!empty($filterDateTo)) {
    $query .= " AND DATE(d.created_at) <= ?";
    $params[] = $filterDateTo;
}

$query .= " ORDER BY d.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$diaries = $stmt->fetchAll();

// Получение данных для просмотра/редактирования
$editDiary = null;
$diaryComments = [];
$diaryLessons = [];

if (($action === 'view' || $action === 'edit') && $diaryId) {
    $stmt = $pdo->prepare("
        SELECT d.*, 
               s.last_name, 
               s.first_name, 
               s.middle_name, 
               s.class,
               c.name as category_name,
               c.color as category_color
        FROM diaries d
        JOIN students s ON d.student_id = s.id
        LEFT JOIN categories c ON d.category_id = c.id
        WHERE d.id = ? AND d.user_id = ?
    ");
    $stmt->execute([$diaryId, $userId]);
    $editDiary = $stmt->fetch();
    
    if ($editDiary) {
        // Получаем комментарии
        $stmt = $pdo->prepare("
            SELECT dc.*, u.first_name, u.last_name 
            FROM diary_comments dc
            JOIN users u ON dc.user_id = u.id
            WHERE dc.diary_id = ? 
            ORDER BY dc.created_at DESC
        ");
        $stmt->execute([$diaryId]);
        $diaryComments = $stmt->fetchAll();
        
        // Получаем последние занятия
        $stmt = $pdo->prepare("
            SELECT l.* 
            FROM lessons l
            WHERE l.diary_id = ? 
            ORDER BY l.lesson_date DESC, l.start_time DESC
            LIMIT 5
        ");
        $stmt->execute([$diaryId]);
        $diaryLessons = $stmt->fetchAll();
    } else {
        header('Location: diaries.php');
        exit();
    }
}

// Получение публичной ссылки для просмотра
$publicView = isset($_GET['public']) && isset($_GET['token']);
if ($publicView) {
    $token = $_GET['token'];
    $stmt = $pdo->prepare("
        SELECT d.*, s.last_name, s.first_name, s.middle_name
        FROM diaries d
        JOIN students s ON d.student_id = s.id
        WHERE d.public_link = ? AND d.user_id = ?
    ");
    $stmt->execute([$token, $userId]);
    $publicDiary = $stmt->fetch();
    
    if (!$publicDiary) {
        header('Location: diaries.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Дневники - Дневник репетитора</title>
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .diary-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-left: 4px solid;
            position: relative;
        }
        .diary-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .diary-name {
            font-size: 1.2em;
            font-weight: 600;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .diary-student {
            color: #666;
            margin-bottom: 10px;
            font-size: 0.95em;
        }
        .diary-student i {
            color: #667eea;
        }
        .diary-category {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            color: white;
            margin-bottom: 10px;
        }
        .diary-meta {
            display: flex;
            gap: 15px;
            margin-top: 10px;
            font-size: 0.9em;
            color: #666;
            flex-wrap: wrap;
        }
        .diary-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .public-link-badge {
            background: #28a745;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            display: inline-flex;
            align-items: center;
            gap: 3px;
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
        .comment-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 3px solid #667eea;
        }
        .comment-meta {
            font-size: 0.85em;
            color: #666;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
        }
        .lesson-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 8px;
            font-size: 0.9em;
        }
        .lesson-item .date {
            font-weight: 600;
            color: #667eea;
        }
        .public-view {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .public-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .public-header h1 {
            color: #333;
            font-size: 2em;
        }
        .public-info {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .copy-link-btn {
            cursor: pointer;
        }

        /* Добавьте в секцию <style> */
.copy-notification {
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Стили для блока с публичной ссылкой */
.public-link-card {
    background: #f8f9fa;
    border-left: 4px solid #28a745;
}

.public-link-input-group {
    background: white;
    border-radius: 8px;
    overflow: hidden;
}

.public-link-input {
    border: 1px solid #dee2e6;
    border-right: none;
    font-size: 0.9em;
}

.public-link-input:focus {
    outline: none;
    box-shadow: none;
    border-color: #dee2e6;
}

.btn-copy {
    border: 1px solid #dee2e6;
    border-left: none;
    background: white;
    transition: all 0.2s;
}

.btn-copy:hover {
    background: #e9ecef;
    color: #667eea;
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
                    'saved' => 'Дневник успешно сохранен',
                    'deleted' => 'Дневник удален',
                    'force_deleted' => 'Дневник и все занятия удалены',
                    'copied' => 'Копия дневника создана',
                    'link_generated' => 'Публичная ссылка сгенерирована',
                    'link_removed' => 'Публичная ссылка удалена',
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
        
        <?php if ($publicView && isset($publicDiary)): ?>
            <!-- Публичный просмотр дневника -->
            <div class="public-view">
                <div class="public-header">
                    <i class="bi bi-journal-bookmark-fill" style="font-size: 3rem; color: #667eea;"></i>
                    <h1><?php echo htmlspecialchars($publicDiary['name']); ?></h1>
                    <p class="text-muted">
                        Дневник ученика: <?php echo htmlspecialchars($publicDiary['last_name'] . ' ' . $publicDiary['first_name'] . ' ' . ($publicDiary['middle_name'] ?? '')); ?>
                    </p>
                </div>
                
                <div class="public-info">
                    <h4>Информация о дневнике</h4>
                    <hr>
                    
                    <?php if (!empty($publicDiary['description'])): ?>
                        <div class="mb-3">
                            <strong>Описание:</strong>
                            <p><?php echo nl2br(htmlspecialchars($publicDiary['description'])); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Стоимость занятия:</strong> 
                                <?php echo $publicDiary['lesson_cost'] ? number_format($publicDiary['lesson_cost'], 0, ',', ' ') . ' ₽' : 'Не указана'; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Длительность занятия:</strong> 
                                <?php 
                                if ($publicDiary['lesson_duration']) {
                                    $hours = floor($publicDiary['lesson_duration'] / 60);
                                    $minutes = $publicDiary['lesson_duration'] % 60;
                                    echo ($hours > 0 ? $hours . ' ч ' : '') . ($minutes > 0 ? $minutes . ' мин' : '');
                                } else {
                                    echo 'Не указана';
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                    
                    <p><strong>Дата создания:</strong> <?php echo date('d.m.Y H:i', strtotime($publicDiary['created_at'])); ?></p>
                    <p><strong>Последнее обновление:</strong> <?php echo date('d.m.Y H:i', strtotime($publicDiary['updated_at'])); ?></p>
                    
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i>
                        Это публичная ссылка на дневник. Вы можете поделиться ею с учеником.
                    </div>
                </div>
            </div>
            
        <?php elseif ($action === 'list'): ?>
<!-- Заголовок и кнопки действий -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-journals"></i> Дневники</h2>
    
    <div class="btn-group">
    <a href="?action=add" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Создать
    </a>
    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
        <span class="visually-hidden">Toggle Dropdown</span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <li>
            <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#importCsvModal">
                <i class="bi bi-upload text-success"></i> Импорт CSV
            </button>
        </li>
        <li>
            <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#importJsonModal">
                <i class="bi bi-filetype-json text-info"></i> Импорт JSON
            </button>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
            <a class="dropdown-item" href="?export_csv=1">
                <i class="bi bi-filetype-csv text-warning"></i> Экспорт CSV
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="?export_json=1">
                <i class="bi bi-filetype-json text-secondary"></i> Экспорт JSON
            </a>
        </li>
    </ul>
</div>
</div>
            
            <!-- Статистика -->
            <div class="stats-card mb-4">
                <div class="row">
                    <div class="col-md-3">
                        <div class="text-center">
                            <h3 class="mb-0"><?php echo count($diaries); ?></h3>
                            <small>Всего дневников</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h3 class="mb-0">
                                <?php 
                                $totalLessons = 0;
                                foreach ($diaries as $d) {
                                    $totalLessons += $d['lessons_count'];
                                }
                                echo $totalLessons;
                                ?>
                            </h3>
                            <small>Всего занятий</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h3 class="mb-0">
                                <?php 
                                $publicCount = 0;
                                foreach ($diaries as $d) {
                                    if ($d['public_link']) $publicCount++;
                                }
                                echo $publicCount;
                                ?>
                            </h3>
                            <small>Публичных ссылок</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h3 class="mb-0"><?php echo count($students); ?></h3>
                            <small>Активных учеников</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Фильтры -->
            <div class="filter-panel">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
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
                    <div class="col-md-3">
                        <label class="form-label">Ученик</label>
                        <select name="student" class="form-select">
                            <option value="">Все ученики</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['id']; ?>" <?php echo $filterStudent == $student['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Дата с</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo $filterDateFrom; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Дата по</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo $filterDateTo; ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Применить
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Список дневников -->
<!-- Список дневников -->
<!-- Список дневников -->
<div class="row">
    <?php if (empty($diaries)): ?>
        <div class="col-12">
            <div class="alert alert-info text-center py-5">
                <i class="bi bi-journal" style="font-size: 3rem;"></i>
                <h4 class="mt-3">Дневники не найдены</h4>
                <p>Создайте первый дневник, нажав кнопку "Создать дневник"</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($diaries as $diary): ?>
            <div class="col-md-6 col-lg-4"  onclick="window.location.href='lessons.php?diary_id=<?php echo $diary['id']; ?>'">
                <div class="diary-card" style="border-left-color: <?php echo $diary['category_color'] ?? '#808080'; ?>">
                    <div class="diary-name">
                        <?php echo htmlspecialchars($diary['name']); ?>
                            <?php if ($diary['public_link']): ?>
                                <a href="<?php echo getPublicDiaryUrl($diary['public_link']); ?>" 
                                target="_blank" 
                                class="public-link-badge" 
                                title="Открыть публичную ссылку"
                                onclick="event.stopPropagation();">
                                    <i class="bi bi-link"></i>
                                </a>
                            <?php endif; ?>
                    </div>
                    
                    <div class="diary-student">
                        <i class="bi bi-person"></i>
                        <?php 
                        // Получаем информацию об ученике для этого дневника
                        $stmtStudent = $pdo->prepare("SELECT last_name, first_name, middle_name, class FROM students WHERE id = ?");
                        $stmtStudent->execute([$diary['student_id']]);
                        $student = $stmtStudent->fetch();
                        if ($student):
                        ?>
                            <?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? '')); ?>
                            <?php if ($student['class']): ?>
                                <small class="text-muted">(<?php echo htmlspecialchars($student['class']); ?> )</small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">Ученик не найден</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($diary['category_name']): ?>
                        <div class="diary-category" style="background: <?php echo $diary['category_color'] ?? '#808080'; ?>">
                            <?php echo htmlspecialchars($diary['category_name']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($diary['description'])): ?>
                        <div class="small text-muted mb-2">
                            <?php echo nl2br(htmlspecialchars(substr($diary['description'], 0, 100) . (strlen($diary['description']) > 100 ? '...' : ''))); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="diary-meta">
                        <span class="diary-meta-item">
                            <i class="bi bi-currency-ruble"></i>
                            <?php echo $diary['lesson_cost'] ? number_format($diary['lesson_cost'], 0, ',', ' ') : '—'; ?>
                        </span>
                        <span class="diary-meta-item">
                            <i class="bi bi-clock"></i>
                            <?php echo $diary['lesson_duration'] ? $diary['lesson_duration'] . ' мин' : '—'; ?>
                        </span>
                        <span class="diary-meta-item">
                            <i class="bi bi-calendar-check"></i>
                            <?php echo $diary['lessons_count']; ?> занятий
                        </span>
                    </div>

                    <div class="mt-3 d-flex justify-content-end gap-2">
<a href="private_diary.php?id=<?php echo $diary['id']; ?>" class="btn btn-sm btn-outline-primary" title="Детальный просмотр">
    <i class="bi bi-info-circle"></i>
</a>
                                <a href="lessons.php?diary_id=<?php echo $diary['id']; ?>" class="btn btn-sm btn-outline-success" title="Перейти к занятиям">
            <i class="bi bi-calendar-check"></i>
        </a>
        <!--
                        <a href="?action=view&id=<?php echo $diary['id']; ?>" class="btn btn-sm btn-outline-primary" title="Просмотр">
                            <i class="bi bi-eye"></i>
                        </a>-->
                        <a href="?action=edit&id=<?php echo $diary['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Редактировать">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <a href="?copy=1&id=<?php echo $diary['id']; ?>" class="btn btn-sm btn-outline-info" title="Создать копию"
                           onclick="return confirm('Создать копию дневника?')">
                            <i class="bi bi-files"></i>
                        </a>
                        <a href="?delete=1&id=<?php echo $diary['id']; ?>" class="btn btn-sm btn-outline-danger" title="Удалить"
                           onclick="return confirm('Удалить дневник?')">
                            <i class="bi bi-trash"></i>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
            
        <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <!-- Форма добавления/редактирования дневника -->

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-<?php echo $action === 'add' ? 'plus-circle' : 'pencil'; ?>"></i>
                        <?php echo $action === 'add' ? 'Создание дневника' : 'Редактирование дневника'; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <?php if ($action === 'edit' && $editDiary): ?>
                            <input type="hidden" name="diary_id" value="<?php echo $editDiary['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Название дневника *</label>
                            <input type="text" name="name" class="form-control" 
                                   value="<?php echo $editDiary ? htmlspecialchars($editDiary['name']) : ''; ?>" 
                                   required maxlength="255">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Ученик *</label>
                            <select name="student_id" class="form-select" required>
                                <option value="">Выберите ученика</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>" 
                                        <?php echo ($editDiary && $editDiary['student_id'] == $student['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? '')); ?>
                                        <?php if ($student['class']): ?>
                                            (<?php echo htmlspecialchars($student['class']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Категория</label>
                            <select name="category_id" class="form-select">
                                <option value="">Без категории</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" 
                                        <?php echo ($editDiary && $editDiary['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Описание</label>
                            <textarea name="description" class="form-control" rows="3"><?php echo $editDiary ? htmlspecialchars($editDiary['description'] ?? '') : ''; ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Стоимость занятия (₽)</label>
                                <input type="number" name="lesson_cost" class="form-control" 
                                       value="<?php echo $editDiary ? htmlspecialchars($editDiary['lesson_cost'] ?? '') : ''; ?>" 
                                       step="100" min="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Длительность занятия (мин)</label>
                                <input type="number" name="lesson_duration" class="form-control" 
                                       value="<?php echo $editDiary ? htmlspecialchars($editDiary['lesson_duration'] ?? '60') : '60'; ?>" 
                                       step="15" min="0">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Комментарий к изменению</label>
                            <textarea name="comment" class="form-control" rows="2" placeholder="Что было изменено? (необязательно)"></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="diaries.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Назад
                            </a>
                            <button type="submit" name="save_diary" class="btn btn-primary">
                                <i class="bi bi-save"></i> Сохранить
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Блок с публичной ссылкой (только для режима редактирования) -->
            <?php if ($action === 'edit' && $editDiary): ?>
                <div class="card mb-3">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-link"></i> Публичная ссылка</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($editDiary['public_link']): 
                            // Формируем полный URL с учетом подпапки
                            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
                            $host = $_SERVER['HTTP_HOST'];
                            $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
                            $basePath = rtrim($scriptPath, '/');
                            $publicUrl = $protocol . $host . $basePath . '/public_diary.php?token=' . $editDiary['public_link'];
                        ?>
                            <p class="text-muted small">По этой ссылке ученик может просматривать дневник без авторизации:</p>
                            <div class="input-group mb-2">
                                <input type="text" class="form-control form-control-sm" 
                                       value="<?php echo $publicUrl; ?>" 
                                       id="publicLink" readonly>
                                <button class="btn btn-sm btn-outline-primary" type="button" onclick="copyPublicLink()">
                                    <i class="bi bi-files"></i>
                                </button>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle"></i> 
                                    Ссылка действительна, пока вы её не удалите
                                </small>
                                <a href="?remove_link=1&id=<?php echo $editDiary['id']; ?>" 
                                   class="btn btn-sm btn-outline-danger" 
                                   onclick="return confirm('Удалить публичную ссылку? После удаления старая ссылка перестанет работать.')">
                                    <i class="bi bi-trash"></i> Удалить
                                </a>
                            </div>
                        <?php else: ?>
                            <p class="text-muted small">Создайте публичную ссылку, чтобы ученик мог просматривать дневник:</p>
                            <a href="?generate_link=1&id=<?php echo $editDiary['id']; ?>" 
                               class="btn btn-success w-100"
                               onclick="return confirm('Создать публичную ссылку для этого дневника?')">
                                <i class="bi bi-link-45deg"></i> Сгенерировать ссылку
                            </a>
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-info-circle"></i> 
                                Ссылка будет доступна только для просмотра, без возможности редактирования
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Информационный блок -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Информация</h5>
                </div>
                <div class="card-body">
                    <p><strong>О дневниках:</strong></p>
                    <ul class="small">
                        <li>Каждый дневник привязан к конкретному ученику</li>
                        <li>Можно создать несколько дневников для одного ученика</li>
                        <li>Стоимость и длительность можно менять в каждом занятии</li>
                        <li>Публичная ссылка позволяет ученику просматривать дневник</li>
                    </ul>
                    
                    <?php if ($action === 'edit' && $editDiary): ?>
                        <hr>
                        <p><strong>Статистика:</strong></p>
                        <p>Занятий в дневнике: <?php 
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE diary_id = ?");
                            $stmt->execute([$editDiary['id']]);
                            echo $stmt->fetchColumn();
                        ?></p>
                        <p>Комментариев: <?php echo count($diaryComments); ?></p>
                        <p>Создан: <?php echo date('d.m.Y H:i', strtotime($editDiary['created_at'])); ?></p>
                        <p>Обновлен: <?php echo date('d.m.Y H:i', strtotime($editDiary['updated_at'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

            
        <?php elseif ($action === 'view' && $editDiary): ?>
            <!-- Просмотр дневника -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="bi bi-journal-bookmark-fill"></i> 
                    <?php echo htmlspecialchars($editDiary['name']); ?>
                </h2>
                <div>
                    <a href="?action=edit&id=<?php echo $editDiary['id']; ?>" class="btn btn-primary">
                        <i class="bi bi-pencil"></i> Редактировать
                    </a>
                    <a href="diaries.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Назад
                    </a>
                </div>
            </div>
            
            <!-- Информация о дневнике -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Информация о дневнике</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Ученик:</strong> 
                                        <?php echo htmlspecialchars($editDiary['last_name'] . ' ' . $editDiary['first_name'] . ' ' . ($editDiary['middle_name'] ?? '')); ?>
                                        <?php if ($editDiary['class']): ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($editDiary['class']); ?></span>
                                        <?php endif; ?>
                                    </p>
                                    <p><strong>Категория:</strong> 
                                        <?php if ($editDiary['category_name']): ?>
                                            <span class="badge" style="background: <?php echo $editDiary['category_color'] ?? '#808080'; ?>; color: white;">
                                                <?php echo htmlspecialchars($editDiary['category_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Не указана</span>
                                        <?php endif; ?>
                                    </p>
                                    <p><strong>Стоимость занятия:</strong> 
                                        <?php echo $editDiary['lesson_cost'] ? number_format($editDiary['lesson_cost'], 0, ',', ' ') . ' ₽' : 'Не указана'; ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Длительность занятия:</strong> 
                                        <?php 
                                        if ($editDiary['lesson_duration']) {
                                            $hours = floor($editDiary['lesson_duration'] / 60);
                                            $minutes = $editDiary['lesson_duration'] % 60;
                                            echo ($hours > 0 ? $hours . ' ч ' : '') . ($minutes > 0 ? $minutes . ' мин' : '');
                                        } else {
                                            echo 'Не указана';
                                        }
                                        ?>
                                    </p>
                                    <p><strong>Создан:</strong> <?php echo date('d.m.Y H:i', strtotime($editDiary['created_at'])); ?></p>
                                    <p><strong>Обновлен:</strong> <?php echo date('d.m.Y H:i', strtotime($editDiary['updated_at'])); ?></p>
                                </div>
                            </div>
                            
                            <?php if (!empty($editDiary['description'])): ?>
                                <hr>
                                <p><strong>Описание:</strong></p>
                                <p><?php echo nl2br(htmlspecialchars($editDiary['description'])); ?></p>
                            <?php endif; ?>
                            
<?php if ($editDiary['public_link']): ?>
                        <hr>
                        <p><strong>Публичная ссылка:</strong></p>
                        <div class="input-group">
                            <input type="text" class="form-control" 
                                   value="<?php echo getPublicDiaryUrl($editDiary['public_link']); ?>" 
                                   id="publicLinkView" readonly>
                            <button class="btn btn-outline-primary" type="button" onclick="copyPublicLink('publicLinkView')">
                                <i class="bi bi-files"></i> Копировать
                            </button>
                        </div>
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> 
                            По этой ссылке ученик может просматривать дневник без авторизации
                        </small>
                    <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Действия</h5>
                        </div>
                        <div class="card-body">
                            <a href="lessons.php?diary_id=<?php echo $editDiary['id']; ?>" class="btn btn-outline-primary w-100 mb-2">
                                <i class="bi bi-calendar-plus"></i> Перейти к занятиям
                            </a>
                            <a href="?copy=1&id=<?php echo $editDiary['id']; ?>" class="btn btn-outline-info w-100 mb-2" 
                               onclick="return confirm('Создать копию дневника?')">
                                <i class="bi bi-files"></i> Создать копию
                            </a>
                            <?php if (!$editDiary['public_link']): ?>
                                <a href="?generate_link=1&id=<?php echo $editDiary['id']; ?>" class="btn btn-outline-success w-100 mb-2">
                                    <i class="bi bi-link-45deg"></i> Сгенерировать ссылку
                                </a>
                            <?php else: ?>
                                <a href="?remove_link=1&id=<?php echo $editDiary['id']; ?>" class="btn btn-outline-warning w-100 mb-2" 
                                   onclick="return confirm('Удалить публичную ссылку?')">
                                    <i class="bi bi-link-45deg"></i> Удалить ссылку
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Последние занятия -->
            <div class="card mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Последние занятия</h5>
                    <a href="lessons.php?diary_id=<?php echo $editDiary['id']; ?>" class="btn btn-sm btn-primary">
                        Все занятия
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($diaryLessons)): ?>
                        <p class="text-muted text-center py-3">В этом дневнике еще нет занятий</p>
                    <?php else: ?>
                        <?php foreach ($diaryLessons as $lesson): ?>
                            <div class="lesson-item">
                                <div class="d-flex justify-content-between">
                                    <span class="date">
                                        <?php echo date('d.m.Y', strtotime($lesson['lesson_date'])); ?> 
                                        <?php echo date('H:i', strtotime($lesson['start_time'])); ?>
                                    </span>
                                    <span class="badge <?php echo $lesson['is_completed'] ? 'bg-success' : ($lesson['is_cancelled'] ? 'bg-danger' : 'bg-warning'); ?>">
                                        <?php 
                                        if ($lesson['is_completed']) echo 'Проведено';
                                        elseif ($lesson['is_cancelled']) echo 'Отменено';
                                        else echo 'Запланировано';
                                        ?>
                                    </span>
                                </div>
                                <?php if ($lesson['topics_manual']): ?>
                                    <div class="small text-muted mt-1"><?php echo htmlspecialchars($lesson['topics_manual']); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Комментарии к дневнику -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">История изменений и комментарии</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($diaryComments)): ?>
                        <p class="text-muted text-center py-3">Комментариев нет</p>
                    <?php else: ?>
                        <?php foreach ($diaryComments as $comment): ?>
                            <div class="comment-item">
                                <div class="comment-meta">
                                    <span>
                                        <i class="bi bi-person"></i> 
                                        <?php echo htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']); ?>
                                    </span>
                                    <span>
                                        <i class="bi bi-clock"></i> 
                                        <?php echo date('d.m.Y H:i', strtotime($comment['created_at'])); ?>
                                    </span>
                                </div>
                                <div class="comment-text">
                                    <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Модальное окно импорта CSV -->
    <div class="modal fade" id="importCsvModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Импорт дневников из CSV</h5>
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
Ученик,Название дневника,Описание,Стоимость,Длительность,Категория
"Иванов Иван","Основной дневник","Подготовка к ЕГЭ",1500,90,"Математика"
"Петрова Мария","Английский язык","General English",1200,60,"Иностранные языки"
                            </pre>
                            <p class="text-muted small mb-0">
                                <i class="bi bi-info-circle"></i> 
                                Ученик должен существовать в системе. Разделитель может быть запятой или точкой с запятой.
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
                    <h5 class="modal-title">Импорт дневников из JSON</h5>
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
  "diaries": [
    {
      "student_name": "Иванов Иван",
      "name": "Основной дневник",
      "description": "Подготовка к ЕГЭ",
      "lesson_cost": 1500,
      "lesson_duration": 90,
      "category": "Математика",
      "comments": [
        {"comment": "Начали подготовку", "created_at": "2024-01-15 10:00:00"}
      ]
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
    
    <script>
function copyPublicLink() {
    const linkInput = document.getElementById('publicLink');
    if (!linkInput) return;
    
    linkInput.select();
    linkInput.setSelectionRange(0, 99999); // Для мобильных устройств
    
    try {
        // Современный метод копирования
        navigator.clipboard.writeText(linkInput.value).then(() => {
            showCopyNotification('Ссылка скопирована!');
        }).catch(err => {
            // Fallback для старых браузеров
            document.execCommand('copy');
            showCopyNotification('Ссылка скопирована!');
        });
    } catch (err) {
        // Еще один fallback
        document.execCommand('copy');
        showCopyNotification('Ссылка скопирована!');
    }
}

function showCopyNotification(message) {
    // Удаляем предыдущее уведомление, если есть
    const oldAlert = document.querySelector('.copy-notification');
    if (oldAlert) oldAlert.remove();
    
    const alert = document.createElement('div');
    alert.className = 'alert alert-success alert-dismissible fade show copy-notification position-fixed top-0 end-0 m-3';
    alert.style.zIndex = '9999';
    alert.style.boxShadow = '0 4px 15px rgba(0,0,0,0.2)';
    alert.style.minWidth = '250px';
    alert.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="bi bi-check-circle-fill me-2"></i>
            <span>${message}</span>
            <button type="button" class="btn-close ms-3" data-bs-dismiss="alert"></button>
        </div>
    `;
    document.body.appendChild(alert);
    
    setTimeout(() => {
        alert.remove();
    }, 3000);
}

// Добавляем обработчик для кнопки копирования через Enter
document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && e.target.classList.contains('copy-link-btn')) {
        e.preventDefault();
        copyPublicLink();
    }
});
    </script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>    
</body>

</html>
