<?php
require_once 'config.php';
requireAuth();

$currentUser = getCurrentUser($pdo);
$userId = $currentUser['id'];
$message = '';
$error = '';

// Обработка действий
$action = $_GET['action'] ?? 'list';
$studentId = $_GET['id'] ?? 0;

// Активация/деактивация ученика
if (isset($_GET['toggle_active']) && $studentId) {
    $stmt = $pdo->prepare("UPDATE students SET is_active = NOT is_active WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$studentId, $userId])) {
        // Запись в историю
        $stmt = $pdo->prepare("INSERT INTO student_history (student_id, user_id, change_type) VALUES (?, ?, ?)");
        $stmt->execute([$studentId, $userId, 'toggle_active']);
        header('Location: students.php?message=status_changed');
        exit();
    }
}

// Удаление ученика
if (isset($_GET['delete']) && $studentId) {
    // Сначала записываем в историю (для архивации)
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? AND user_id = ?");
    $stmt->execute([$studentId, $userId]);
    $studentData = $stmt->fetch();
    
    if ($studentData) {
        $stmt = $pdo->prepare("INSERT INTO student_history (student_id, user_id, change_type, old_data) VALUES (?, ?, ?, ?)");
        $stmt->execute([$studentId, $userId, 'delete', json_encode($studentData)]);
        
        // Удаляем ученика (каскадно удалятся все связанные записи)
        $stmt = $pdo->prepare("DELETE FROM students WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$studentId, $userId])) {
            header('Location: students.php?message=deleted');
            exit();
        }
    }
}

// Обработка добавления/редактирования ученика
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_student'])) {
    $isEdit = isset($_POST['student_id']) && !empty($_POST['student_id']);
    
    // Основные поля (обязательные)
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    
    // Проверка обязательных полей
    if (empty($firstName) || empty($lastName)) {
        $error = 'Имя и фамилия обязательны для заполнения';
    } else {
        // Остальные поля
        $class = trim($_POST['class'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $lessonCost = !empty($_POST['lesson_cost']) ? floatval($_POST['lesson_cost']) : null;
        $lessonDuration = !empty($_POST['lesson_duration']) ? intval($_POST['lesson_duration']) : null;
        $lessonsPerWeek = !empty($_POST['lessons_per_week']) ? intval($_POST['lessons_per_week']) : null;
        $goals = trim($_POST['goals'] ?? '');
        
        // Дополнительная информация
        $birthDate = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
        $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $city = trim($_POST['city'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $messenger1 = trim($_POST['messenger1'] ?? '');
        $messenger2 = trim($_POST['messenger2'] ?? '');
        $messenger3 = trim($_POST['messenger3'] ?? '');
        
        // Получаем старые данные для истории
        $oldData = null;
        if ($isEdit) {
            $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? AND user_id = ?");
            $stmt->execute([$_POST['student_id'], $userId]);
            $oldData = $stmt->fetch();
        }
        
        if ($isEdit) {
            // Обновление
            $stmt = $pdo->prepare("
                UPDATE students SET 
                    first_name = ?, last_name = ?, middle_name = ?, class = ?, phone = ?,
                    lesson_cost = ?, lesson_duration = ?, lessons_per_week = ?, goals = ?,
                    birth_date = ?, start_date = ?, end_date = ?, city = ?, email = ?,
                    messenger1 = ?, messenger2 = ?, messenger3 = ?
                WHERE id = ? AND user_id = ?
            ");
            
            $params = [
                $firstName, $lastName, $middleName, $class, $phone,
                $lessonCost, $lessonDuration, $lessonsPerWeek, $goals,
                $birthDate, $startDate, $endDate, $city, $email,
                $messenger1, $messenger2, $messenger3,
                $_POST['student_id'], $userId
            ];
            
            if ($stmt->execute($params)) {
                // Получаем новые данные
                $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
                $stmt->execute([$_POST['student_id']]);
                $newData = $stmt->fetch();
                
                // Запись в историю
                $stmt = $pdo->prepare("INSERT INTO student_history (student_id, user_id, change_type, old_data, new_data) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$_POST['student_id'], $userId, 'update', json_encode($oldData), json_encode($newData)]);
                
                $message = 'Данные ученика успешно обновлены';
            }
        } else {
            // Добавление
            $stmt = $pdo->prepare("
                INSERT INTO students (
                    user_id, first_name, last_name, middle_name, class, phone,
                    lesson_cost, lesson_duration, lessons_per_week, goals,
                    birth_date, start_date, end_date, city, email,
                    messenger1, messenger2, messenger3, is_active
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            
            $params = [
                $userId, $firstName, $lastName, $middleName, $class, $phone,
                $lessonCost, $lessonDuration, $lessonsPerWeek, $goals,
                $birthDate, $startDate, $endDate, $city, $email,
                $messenger1, $messenger2, $messenger3
            ];
            
            if ($stmt->execute($params)) {
                $newStudentId = $pdo->lastInsertId();
                
                // Запись в историю
                $stmt = $pdo->prepare("INSERT INTO student_history (student_id, user_id, change_type, new_data) VALUES (?, ?, ?, ?)");
                $stmt->execute([$newStudentId, $userId, 'create', json_encode($_POST)]);
                
                $message = 'Ученик успешно добавлен';
            }
        }
    }
}

// Обработка добавления/редактирования родителя
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_parent'])) {
    $studentId = $_POST['student_id'] ?? 0;
    $parentId = $_POST['parent_id'] ?? 0;
    
    $relationship = trim($_POST['relationship'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $messenger = trim($_POST['messenger'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($fullName)) {
        $error = 'ФИО родителя обязательно для заполнения';
    } else {
        if ($parentId) {
            // Обновление
            $stmt = $pdo->prepare("
                UPDATE parents SET 
                    relationship = ?, full_name = ?, phone = ?, messenger_contact = ?, email = ?
                WHERE id = ? AND student_id IN (SELECT id FROM students WHERE id = ? AND user_id = ?)
            ");
            $stmt->execute([$relationship, $fullName, $phone, $messenger, $email, $parentId, $studentId, $userId]);
            $message = 'Данные родителя обновлены';
        } else {
            // Добавление
            $stmt = $pdo->prepare("
                INSERT INTO parents (student_id, relationship, full_name, phone, messenger_contact, email)
                SELECT ?, ?, ?, ?, ?, ? FROM dual WHERE EXISTS (SELECT 1 FROM students WHERE id = ? AND user_id = ?)
            ");
            $stmt->execute([$studentId, $relationship, $fullName, $phone, $messenger, $email, $studentId, $userId]);
            $message = 'Родитель добавлен';
        }
    }
}

// Удаление родителя
if (isset($_GET['delete_parent']) && isset($_GET['student_id'])) {
    $parentId = $_GET['delete_parent'];
    $studentId = $_GET['student_id'];
    
    $stmt = $pdo->prepare("DELETE FROM parents WHERE id = ? AND student_id IN (SELECT id FROM students WHERE id = ? AND user_id = ?)");
    if ($stmt->execute([$parentId, $studentId, $userId])) {
        header('Location: students.php?action=view&id=' . $studentId . '&tab=parents&message=parent_deleted');
        exit();
    }
}

// Обработка комментариев
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_comment'])) {
    $studentId = $_POST['student_id'] ?? 0;
    $commentId = $_POST['comment_id'] ?? 0;
    $comment = trim($_POST['comment'] ?? '');
    
    if (!empty($comment)) {
        if ($commentId) {
            // Обновление
            $stmt = $pdo->prepare("
                UPDATE student_comments SET comment = ? 
                WHERE id = ? AND student_id IN (SELECT id FROM students WHERE id = ? AND user_id = ?)
            ");
            $stmt->execute([$comment, $commentId, $studentId, $userId]);
        } else {
            // Добавление
            $stmt = $pdo->prepare("
                INSERT INTO student_comments (student_id, user_id, comment)
                SELECT ?, ?, ? FROM dual WHERE EXISTS (SELECT 1 FROM students WHERE id = ? AND user_id = ?)
            ");
            $stmt->execute([$studentId, $userId, $comment, $studentId, $userId]);
        }
        header('Location: students.php?action=view&id=' . $studentId . '&tab=comments');
        exit();
    }
}

// Удаление комментария
if (isset($_GET['delete_comment']) && isset($_GET['student_id'])) {
    $commentId = $_GET['delete_comment'];
    $studentId = $_GET['student_id'];
    
    $stmt = $pdo->prepare("DELETE FROM student_comments WHERE id = ? AND student_id IN (SELECT id FROM students WHERE id = ? AND user_id = ?)");
    if ($stmt->execute([$commentId, $studentId, $userId])) {
        header('Location: students.php?action=view&id=' . $studentId . '&tab=comments&message=comment_deleted');
        exit();
    }
}

// Экспорт в JSON
if (isset($_GET['export']) && $studentId) {
    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            (SELECT JSON_ARRAYAGG(JSON_OBJECT(
                'relationship', relationship,
                'full_name', full_name,
                'phone', phone,
                'messenger', messenger_contact,
                'email', email
            )) FROM parents WHERE student_id = s.id) as parents,
            (SELECT JSON_ARRAYAGG(JSON_OBJECT(
                'comment', comment,
                'created_at', created_at
            )) FROM student_comments WHERE student_id = s.id) as comments,
            (SELECT JSON_ARRAYAGG(JSON_OBJECT(
                'change_type', change_type,
                'old_data', old_data,
                'new_data', new_data,
                'changed_at', changed_at
            )) FROM student_history WHERE student_id = s.id) as history
        FROM students s
        WHERE s.id = ? AND s.user_id = ?
    ");
    $stmt->execute([$studentId, $userId]);
    $studentData = $stmt->fetch();
    
    if ($studentData) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="student_' . $studentId . '_' . date('Y-m-d') . '.json"');
        echo json_encode($studentData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit();
    }
}

// Импорт из JSON (заглушка)
if (isset($_POST['import_json']) && isset($_FILES['json_file'])) {
    // Здесь будет логика импорта
    $message = 'Функция импорта в разработке';
}

// Получение списка учеников с фильтрацией
$filterClass = $_GET['filter_class'] ?? '';
$searchName = $_GET['search'] ?? '';
$filterActive = $_GET['filter_active'] ?? 'active';

$query = "SELECT s.*, 
          (SELECT MIN(CONCAT(lesson_date, ' ', start_time)) FROM lessons WHERE student_id = s.id AND lesson_date >= CURDATE()) as next_lesson
          FROM students s 
          WHERE s.user_id = ?";

$params = [$userId];

if ($filterActive === 'active') {
    $query .= " AND s.is_active = 1";
} elseif ($filterActive === 'inactive') {
    $query .= " AND s.is_active = 0";
}

if (!empty($filterClass)) {
    $query .= " AND s.class = ?";
    $params[] = $filterClass;
}

if (!empty($searchName)) {
    $query .= " AND CONCAT(s.last_name, ' ', s.first_name, ' ', COALESCE(s.middle_name, '')) LIKE ?";
    $params[] = "%$searchName%";
}

$query .= " ORDER BY s.last_name, s.first_name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Получение уникальных классов для фильтра
$stmt = $pdo->prepare("SELECT DISTINCT class FROM students WHERE user_id = ? AND class IS NOT NULL AND class != '' ORDER BY class");
$stmt->execute([$userId]);
$classes = $stmt->fetchAll();

// Получение данных ученика для просмотра/редактирования
$student = null;
$parents = [];
$comments = [];
$history = [];

if ($action === 'view' || $action === 'edit' && $studentId) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? AND user_id = ?");
    $stmt->execute([$studentId, $userId]);
    $student = $stmt->fetch();
    
    if ($student) {
        // Получение родителей
        $stmt = $pdo->prepare("SELECT * FROM parents WHERE student_id = ? ORDER BY full_name");
        $stmt->execute([$studentId]);
        $parents = $stmt->fetchAll();
        
        // Получение комментариев
        $stmt = $pdo->prepare("
            SELECT sc.*, u.first_name, u.last_name 
            FROM student_comments sc
            JOIN users u ON sc.user_id = u.id
            WHERE sc.student_id = ? 
            ORDER BY sc.created_at DESC
        ");
        $stmt->execute([$studentId]);
        $comments = $stmt->fetchAll();
        
        // Получение истории
        $stmt = $pdo->prepare("
            SELECT sh.*, u.first_name, u.last_name 
            FROM student_history sh
            JOIN users u ON sh.user_id = u.id
            WHERE sh.student_id = ? 
            ORDER BY sh.changed_at DESC
            LIMIT 50
        ");
        $stmt->execute([$studentId]);
        $history = $stmt->fetchAll();
    }
}

// Получение текущей вкладки
$currentTab = $_GET['tab'] ?? 'main';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ученики - Дневник репетитора</title>
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .student-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            border-left: 4px solid #667eea;
        }
        .student-card.inactive {
            border-left-color: #dc3545;
            opacity: 0.8;
            background: #f8f9fa;
        }
        .student-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .student-name {
            font-size: 1.2em;
            font-weight: 600;
            color: #333;
        }
        .student-class {
            display: inline-block;
            padding: 3px 10px;
            background: #e9ecef;
            border-radius: 20px;
            font-size: 0.85em;
        }
        .next-lesson {
            font-size: 0.9em;
            color: #28a745;
        }
        .student-actions {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
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
        .nav-tabs .nav-link.active {
            color: #667eea;
            font-weight: 600;
            border-bottom: 3px solid #667eea;
        }
        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 0.9em;
        }
        .info-value {
            margin-bottom: 10px;
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
        }
        .history-item {
            padding: 10px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.9em;
        }
        .history-item:last-child {
            border-bottom: none;
        }
        .badge-success {
            background: #28a745;
            color: white;
        }
        .badge-danger {
            background: #dc3545;
            color: white;
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
                    'status_changed' => 'Статус ученика изменен',
                    'deleted' => 'Ученик удален',
                    'parent_deleted' => 'Родитель удален',
                    'comment_deleted' => 'Комментарий удален'
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
            <!-- Список учеников -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-people"></i> Ученики</h2>
                <a href="?action=add" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Добавить
                </a>
            </div>
            
            <!-- Фильтры -->
            <div class="filter-panel">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Поиск по ФИО</label>
                        <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($searchName); ?>" placeholder="Введите имя или фамилию">
                    </div>
                    <div class="col-md-3">
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
                    <div class="col-md-3">
                        <label class="form-label">Статус</label>
                        <select name="filter_active" class="form-select">
                            <option value="active" <?php echo $filterActive == 'active' ? 'selected' : ''; ?>>Активные</option>
                            <option value="inactive" <?php echo $filterActive == 'inactive' ? 'selected' : ''; ?>>Неактивные</option>
                            <option value="all" <?php echo $filterActive == 'all' ? 'selected' : ''; ?>>Все</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Применить
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Список учеников -->
            <div class="row">
                <?php if (empty($students)): ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center py-5">
                            <i class="bi bi-person-x" style="font-size: 3rem;"></i>
                            <h4 class="mt-3">Ученики не найдены</h4>
                            <p>Добавьте первого ученика, нажав кнопку "Добавить ученика"</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($students as $student): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="student-card <?php echo !$student['is_active'] ? 'inactive' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="student-name">
                                            <?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? '')); ?>
                                        </div>
                                        <?php if (!empty($student['class'])): ?>
                                            <span class="student-class mt-2">
                                                <i class="bi bi-backpack"></i> <?php echo htmlspecialchars($student['class']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="badge <?php echo $student['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo $student['is_active'] ? 'Активен' : 'Неактивен'; ?>
                                    </span>
                                </div>
                                
                                <?php if (!empty($student['phone'])): ?>
                                    <div class="mt-2">
                                        <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($student['phone']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($student['next_lesson']): ?>
                                    <div class="next-lesson mt-2">
                                        <i class="bi bi-calendar-check"></i> 
                                        Ближайшее занятие: <?php echo date('d.m.Y H:i', strtotime($student['next_lesson'])); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-muted mt-2">
                                        <i class="bi bi-calendar-x"></i> Нет запланированных занятий
                                    </div>
                                <?php endif; ?>
                                
                                <div class="student-actions">
                                    <div class="btn-group w-100" role="group">
                                        <a href="?action=view&id=<?php echo $student['id']; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-eye"></i> Просмотр
                                        </a>
                                        <a href="?action=edit&id=<?php echo $student['id']; ?>" class="btn btn-outline-secondary btn-sm">
                                            <i class="bi bi-pencil"></i> Ред.
                                        </a>
                                        <a href="?toggle_active=1&id=<?php echo $student['id']; ?>" class="btn btn-outline-warning btn-sm" onclick="return confirm('Изменить статус ученика?')">
                                            <i class="bi bi-power"></i>
                                        </a>
                                        <a href="?delete=1&id=<?php echo $student['id']; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Вы уверены? Это действие удалит все данные ученика!')">
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
            <!-- Форма добавления/редактирования ученика -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="bi bi-<?php echo $action === 'add' ? 'plus-circle' : 'pencil'; ?>"></i>
                    <?php echo $action === 'add' ? 'Добавление ученика' : 'Редактирование ученика'; ?>
                </h2>
                <a href="students.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Вернуться к списку
                </a>
            </div>
            
            <form method="POST" action="" class="needs-validation" novalidate>
                <?php if ($action === 'edit' && $student): ?>
                    <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-8">
                        <!-- Основная информация -->
                        <div class="card mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-person"></i> Основная информация</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Фамилия *</label>
                                        <input type="text" name="last_name" class="form-control" 
                                               value="<?php echo $student ? htmlspecialchars($student['last_name']) : ''; ?>" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Имя *</label>
                                        <input type="text" name="first_name" class="form-control" 
                                               value="<?php echo $student ? htmlspecialchars($student['first_name']) : ''; ?>" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Отчество</label>
                                        <input type="text" name="middle_name" class="form-control" 
                                               value="<?php echo $student ? htmlspecialchars($student['middle_name'] ?? '') : ''; ?>">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Класс</label>
                                        <input type="text" name="class" class="form-control" 
                                               value="<?php echo $student ? htmlspecialchars($student['class'] ?? '') : ''; ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Телефон</label>
                                        <input type="text" name="phone" class="form-control" 
                                               value="<?php echo $student ? htmlspecialchars($student['phone'] ?? '') : ''; ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control" 
                                               value="<?php echo $student ? htmlspecialchars($student['email'] ?? '') : ''; ?>">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Стоимость занятия (₽)</label>
                                        <input type="number" name="lesson_cost" class="form-control" 
                                               value="<?php echo $student ? htmlspecialchars($student['lesson_cost'] ?? '') : ''; ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Длительность (мин)</label>
                                        <input type="number" name="lesson_duration" class="form-control" 
                                               value="<?php echo $student ? htmlspecialchars($student['lesson_duration'] ?? '60') : '60'; ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Занятий в неделю</label>
                                        <input type="number" name="lessons_per_week" class="form-control" 
                                               value="<?php echo $student ? htmlspecialchars($student['lessons_per_week'] ?? '1') : '1'; ?>">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Цели обучения</label>
                                    <textarea name="goals" class="form-control" rows="3"><?php echo $student ? htmlspecialchars($student['goals'] ?? '') : ''; ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Дополнительная информация -->
                        <div class="card mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Дополнительная информация</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Дата рождения</label>
                                        <input type="date" name="birth_date" class="form-control" 
                                               value="<?php echo $student ? htmlspecialchars($student['birth_date'] ?? '') : ''; ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Дата начала занятий</label>
                                        <input type="date" name="start_date" class="form-control" 
                                               value="<?php echo $student ? htmlspecialchars($student['start_date'] ?? '') : ''; ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Планируемая дата окончания</label>
                                        <input type="date" name="end_date" class="form-control" 
                                               value="<?php echo $student ? htmlspecialchars($student['end_date'] ?? '') : ''; ?>">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Город</label>
                                        <input type="text" name="city" class="form-control" 
                                               value="<?php echo $student ? htmlspecialchars($student['city'] ?? '') : ''; ?>">
                                    </div>
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label">Мессенджер 1</label>
                                        <input type="text" name="messenger1" class="form-control" 
                                               value="<?php echo $student ? htmlspecialchars($student['messenger1'] ?? '') : ''; ?>" placeholder="Telegram, WhatsApp и т.д.">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Мессенджер 2</label>
                                        <input type="text" name="messenger2" class="form-control" 
                                               value="<?php echo $student ? htmlspecialchars($student['messenger2'] ?? '') : ''; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Мессенджер 3</label>
                                        <input type="text" name="messenger3" class="form-control" 
                                               value="<?php echo $student ? htmlspecialchars($student['messenger3'] ?? '') : ''; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" name="save_student" class="btn btn-primary btn-lg">
                            <i class="bi bi-save"></i> Сохранить
                        </button>
                    </div>
                    
                    <div class="col-md-4">
                        <!-- Информационные блоки -->
                        <div class="card mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-info-square"></i> Подсказка</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-0">
                                    <i class="bi bi-asterisk text-danger"></i> - обязательные поля.<br>
                                    Все остальные поля можно оставить пустыми и заполнить позже.
                                </p>
                            </div>
                        </div>
                        
                        <?php if ($action === 'edit' && $student): ?>
                            <div class="card mb-4">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0"><i class="bi bi-calendar"></i> Информация</h5>
                                </div>
                                <div class="card-body">
                                    <p class="mb-1">
                                        <small class="text-muted">Добавлен:</small><br>
                                        <strong><?php echo date('d.m.Y H:i', strtotime($student['created_at'])); ?></strong>
                                    </p>
                                    <p class="mb-0">
                                        <small class="text-muted">Обновлен:</small><br>
                                        <strong><?php echo date('d.m.Y H:i', strtotime($student['updated_at'])); ?></strong>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0"><i class="bi bi-download"></i> Экспорт</h5>
                                </div>
                                <div class="card-body">
                                    <a href="?export=1&id=<?php echo $student['id']; ?>" class="btn btn-outline-success w-100">
                                        <i class="bi bi-filetype-json"></i> Экспорт в JSON
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
            
        <?php elseif ($action === 'view' && $student): ?>
            <!-- Просмотр ученика с вкладками -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="bi bi-person-badge"></i> 
                    <?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? '')); ?>
                    <?php if (!$student['is_active']): ?>
                        <span class="badge bg-secondary ms-2">Неактивен</span>
                    <?php endif; ?>
                </h2>
                <div>
                    <a href="?action=edit&id=<?php echo $student['id']; ?>" class="btn btn-primary">
                        <i class="bi bi-pencil"></i> Редактировать
                    </a>
                    <a href="students.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Назад
                    </a>
                </div>
            </div>
            
            <!-- Вкладки -->
            <ul class="nav nav-tabs mb-4" id="studentTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $currentTab == 'main' ? 'active' : ''; ?>" 
                            data-bs-toggle="tab" data-bs-target="#main" type="button">
                        <i class="bi bi-person"></i> Основное
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $currentTab == 'additional' ? 'active' : ''; ?>" 
                            data-bs-toggle="tab" data-bs-target="#additional" type="button">
                        <i class="bi bi-info-circle"></i> Дополнительно
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $currentTab == 'parents' ? 'active' : ''; ?>" 
                            data-bs-toggle="tab" data-bs-target="#parents" type="button">
                        <i class="bi bi-people"></i> Родители 
                        <?php if (count($parents) > 0): ?>
                            <span class="badge bg-primary"><?php echo count($parents); ?></span>
                        <?php endif; ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $currentTab == 'comments' ? 'active' : ''; ?>" 
                            data-bs-toggle="tab" data-bs-target="#comments" type="button">
                        <i class="bi bi-chat"></i> Комментарии
                        <?php if (count($comments) > 0): ?>
                            <span class="badge bg-primary"><?php echo count($comments); ?></span>
                        <?php endif; ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $currentTab == 'history' ? 'active' : ''; ?>" 
                            data-bs-toggle="tab" data-bs-target="#history" type="button">
                        <i class="bi bi-clock-history"></i> История
                    </button>
                </li>
            </ul>
            
            <!-- Содержимое вкладок -->
            <div class="tab-content">
                <!-- Вкладка "Основное" -->
                <div class="tab-pane fade <?php echo $currentTab == 'main' ? 'show active' : ''; ?>" id="main">
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-label">ФИО</div>
                                    <div class="info-value">
                                        <?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? '')); ?>
                                    </div>
                                    
                                    <div class="info-label">Класс</div>
                                    <div class="info-value"><?php echo htmlspecialchars($student['class'] ?? 'Не указан'); ?></div>
                                    
                                    <div class="info-label">Телефон</div>
                                    <div class="info-value"><?php echo htmlspecialchars($student['phone'] ?? 'Не указан'); ?></div>
                                    
                                    <div class="info-label">Email</div>
                                    <div class="info-value"><?php echo htmlspecialchars($student['email'] ?? 'Не указан'); ?></div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="info-label">Стоимость занятия</div>
                                    <div class="info-value">
                                        <?php echo $student['lesson_cost'] ? number_format($student['lesson_cost'], 0, ',', ' ') . ' ₽' : 'Не указана'; ?>
                                    </div>
                                    
                                    <div class="info-label">Длительность занятия</div>
                                    <div class="info-value">
                                        <?php 
                                        if ($student['lesson_duration']) {
                                            $hours = floor($student['lesson_duration'] / 60);
                                            $minutes = $student['lesson_duration'] % 60;
                                            echo ($hours > 0 ? $hours . ' ч ' : '') . ($minutes > 0 ? $minutes . ' мин' : '');
                                        } else {
                                            echo 'Не указана';
                                        }
                                        ?>
                                    </div>
                                    
                                    <div class="info-label">Занятий в неделю</div>
                                    <div class="info-value"><?php echo $student['lessons_per_week'] ?? 'Не указано'; ?></div>
                                    
                                    <div class="info-label">Цели обучения</div>
                                    <div class="info-value"><?php echo nl2br(htmlspecialchars($student['goals'] ?? 'Не указаны')); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Вкладка "Дополнительно" -->
                <div class="tab-pane fade <?php echo $currentTab == 'additional' ? 'show active' : ''; ?>" id="additional">
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-label">Дата рождения</div>
                                    <div class="info-value">
                                        <?php echo $student['birth_date'] ? date('d.m.Y', strtotime($student['birth_date'])) : 'Не указана'; ?>
                                    </div>
                                    
                                    <div class="info-label">Дата начала занятий</div>
                                    <div class="info-value">
                                        <?php echo $student['start_date'] ? date('d.m.Y', strtotime($student['start_date'])) : 'Не указана'; ?>
                                    </div>
                                    
                                    <div class="info-label">Планируемая дата окончания</div>
                                    <div class="info-value">
                                        <?php echo $student['end_date'] ? date('d.m.Y', strtotime($student['end_date'])) : 'Не указана'; ?>
                                    </div>
                                    
                                    <div class="info-label">Город</div>
                                    <div class="info-value"><?php echo htmlspecialchars($student['city'] ?? 'Не указан'); ?></div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="info-label">Мессенджер 1</div>
                                    <div class="info-value"><?php echo htmlspecialchars($student['messenger1'] ?? 'Не указан'); ?></div>
                                    
                                    <div class="info-label">Мессенджер 2</div>
                                    <div class="info-value"><?php echo htmlspecialchars($student['messenger2'] ?? 'Не указан'); ?></div>
                                    
                                    <div class="info-label">Мессенджер 3</div>
                                    <div class="info-value"><?php echo htmlspecialchars($student['messenger3'] ?? 'Не указан'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Вкладка "Родители" -->
                <div class="tab-pane fade <?php echo $currentTab == 'parents' ? 'show active' : ''; ?>" id="parents">
                    <div class="card">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Список родителей</h5>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addParentModal">
                                <i class="bi bi-plus"></i> Добавить родителя
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (empty($parents)): ?>
                                <p class="text-muted text-center py-3">Родители не добавлены</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Родство</th>
                                                <th>ФИО</th>
                                                <th>Телефон</th>
                                                <th>Мессенджер</th>
                                                <th>Email</th>
                                                <th>Действия</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($parents as $parent): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($parent['relationship'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($parent['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($parent['phone'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($parent['messenger_contact'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($parent['email'] ?? '-'); ?></td>
                                                <td>
                                                    <a href="?delete_parent=<?php echo $parent['id']; ?>&student_id=<?php echo $student['id']; ?>" 
                                                       class="btn btn-sm btn-outline-danger" 
                                                       onclick="return confirm('Удалить родителя?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Вкладка "Комментарии" -->
                <div class="tab-pane fade <?php echo $currentTab == 'comments' ? 'show active' : ''; ?>" id="comments">
                    <div class="card">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Комментарии</h5>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCommentModal">
                                <i class="bi bi-plus"></i> Добавить комментарий
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (empty($comments)): ?>
                                <p class="text-muted text-center py-3">Комментариев нет</p>
                            <?php else: ?>
                                <?php foreach ($comments as $comment): ?>
                                    <div class="comment-item">
                                        <div class="comment-meta">
                                            <i class="bi bi-person"></i> <?php echo htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']); ?>
                                            <span class="float-end">
                                                <i class="bi bi-clock"></i> <?php echo date('d.m.Y H:i', strtotime($comment['created_at'])); ?>
                                                <?php if ($comment['user_id'] == $userId): ?>
                                                    <a href="?delete_comment=<?php echo $comment['id']; ?>&student_id=<?php echo $student['id']; ?>" 
                                                       class="text-danger ms-2" onclick="return confirm('Удалить комментарий?')">
                                                        <i class="bi bi-x-circle"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="comment-text">
                                            <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                                        </div>
                                        <?php if ($comment['updated_at'] != $comment['created_at']): ?>
                                            <div class="text-muted mt-1 small">
                                                <i class="bi bi-pencil"></i> Изменено: <?php echo date('d.m.Y H:i', strtotime($comment['updated_at'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Вкладка "История" -->
                <div class="tab-pane fade <?php echo $currentTab == 'history' ? 'show active' : ''; ?>" id="history">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">История изменений</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($history)): ?>
                                <p class="text-muted text-center py-3">История изменений пуста</p>
                            <?php else: ?>
                                <?php foreach ($history as $record): ?>
                                    <div class="history-item">
                                        <div class="d-flex justify-content-between">
                                            <span>
                                                <?php
                                                $actionText = '';
                                                $badgeClass = '';
                                                switch ($record['change_type']) {
                                                    case 'create':
                                                        $actionText = 'Создание';
                                                        $badgeClass = 'badge-success';
                                                        break;
                                                    case 'update':
                                                        $actionText = 'Обновление';
                                                        $badgeClass = 'badge-primary';
                                                        break;
                                                    case 'delete':
                                                        $actionText = 'Удаление';
                                                        $badgeClass = 'badge-danger';
                                                        break;
                                                    case 'toggle_active':
                                                        $actionText = 'Изменение статуса';
                                                        $badgeClass = 'badge-warning';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $badgeClass; ?>"><?php echo $actionText; ?></span>
                                            </span>
                                            <span class="text-muted">
                                                <i class="bi bi-person"></i> <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?>
                                                <i class="bi bi-clock ms-2"></i> <?php echo date('d.m.Y H:i:s', strtotime($record['changed_at'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Модальное окно для добавления родителя -->
    <div class="modal fade" id="addParentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Добавить родителя</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Родство</label>
                            <input type="text" name="relationship" class="form-control" placeholder="Например: мать, отец">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">ФИО *</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Телефон</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Мессенджер</label>
                            <input type="text" name="messenger" class="form-control" placeholder="Telegram, WhatsApp...">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" name="save_parent" class="btn btn-primary">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно для добавления комментария -->
    <div class="modal fade" id="addCommentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Добавить комментарий</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Комментарий</label>
                            <textarea name="comment" class="form-control" rows="5" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" name="save_comment" class="btn btn-primary">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно для импорта JSON -->
    <div class="modal fade" id="importJsonModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Импорт из JSON</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Выберите JSON файл</label>
                            <input type="file" name="json_file" class="form-control" accept=".json">
                        </div>
                        <p class="text-muted small">
                            <i class="bi bi-info-circle"></i> 
                            Файл должен быть в формате JSON, экспортированный из системы.
                        </p>
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
    </script>
</body>
</html>