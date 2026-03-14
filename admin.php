<?php
require_once 'config.php';
requireAuth();

// Проверка прав администратора
if (!isAdmin()) {
    header('Location: dashboard.php');
    exit();
}

$currentUser = getCurrentUser($pdo);
$userId = $currentUser['id'];
$message = '';
$error = '';

// Обработка действий
$action = $_GET['action'] ?? 'list';
$targetUserId = $_GET['id'] ?? 0;

// Переключение на другого пользователя (имперсонация)
if (isset($_GET['switch_to']) && $targetUserId) {
    $stmt = $pdo->prepare("SELECT id, username, email, first_name, last_name, is_admin FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$targetUserId]);
    $targetUser = $stmt->fetch();
    
    if ($targetUser) {
        // Сохраняем информацию о том, что это режим администратора
        $_SESSION['admin_mode'] = true;
        $_SESSION['original_user_id'] = $userId;
        $_SESSION['user_id'] = $targetUser['id'];
        $_SESSION['username'] = $targetUser['username'];
        $_SESSION['is_admin'] = $targetUser['is_admin'];
        
        header('Location: dashboard.php?message=switched');
        exit();
    }
}

// Возврат из режима администратора
if (isset($_GET['switch_back'])) {
    if (isset($_SESSION['admin_mode']) && isset($_SESSION['original_user_id'])) {
        $originalUserId = $_SESSION['original_user_id'];
        
        $stmt = $pdo->prepare("SELECT id, username, email, first_name, last_name, is_admin FROM users WHERE id = ?");
        $stmt->execute([$originalUserId]);
        $originalUser = $stmt->fetch();
        
        if ($originalUser) {
            $_SESSION['user_id'] = $originalUser['id'];
            $_SESSION['username'] = $originalUser['username'];
            $_SESSION['is_admin'] = $originalUser['is_admin'];
            unset($_SESSION['admin_mode']);
            unset($_SESSION['original_user_id']);
            
            header('Location: admin.php?message=switched_back');
            exit();
        }
    }
}

// Добавление/редактирование пользователя
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $isAdmin = isset($_POST['is_admin']) ? 1 : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($email) || empty($firstName) || empty($lastName)) {
        $error = 'Заполните обязательные поля';
    } else {
        // Проверка уникальности
        if ($action === 'edit' && $targetUserId) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $stmt->execute([$username, $email, $targetUserId]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
        }
        
        if ($stmt->fetch()) {
            $error = 'Пользователь с таким именем или email уже существует';
        } else {
            try {
                if ($action === 'edit' && $targetUserId) {
                    // Обновление пользователя
                    if (!empty($password)) {
                        // Смена пароля
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("
                            UPDATE users SET 
                                username = ?, email = ?, first_name = ?, last_name = ?, 
                                middle_name = ?, phone = ?, is_admin = ?, is_active = ?, 
                                password_hash = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$username, $email, $firstName, $lastName, $middleName, $phone, $isAdmin, $isActive, $hashedPassword, $targetUserId]);
                    } else {
                        // Без смены пароля
                        $stmt = $pdo->prepare("
                            UPDATE users SET 
                                username = ?, email = ?, first_name = ?, last_name = ?, 
                                middle_name = ?, phone = ?, is_admin = ?, is_active = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$username, $email, $firstName, $lastName, $middleName, $phone, $isAdmin, $isActive, $targetUserId]);
                    }
                    $message = 'Пользователь обновлен';
                } else {
                    // Добавление нового пользователя
                    if (empty($password)) {
                        $error = 'Пароль обязателен при создании пользователя';
                    } else {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("
                            INSERT INTO users (username, email, password_hash, first_name, last_name, middle_name, phone, is_admin, is_active)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$username, $email, $hashedPassword, $firstName, $lastName, $middleName, $phone, $isAdmin, $isActive]);
                        $message = 'Пользователь добавлен';
                    }
                }
                
                if (!isset($error)) {
                    header('Location: admin.php?message=saved');
                    exit();
                }
            } catch (Exception $e) {
                $error = 'Ошибка при сохранении: ' . $e->getMessage();
            }
        }
    }
}

// Смена пароля пользователя
if (isset($_GET['reset_password']) && $targetUserId) {
    // Генерируем временный пароль
    $tempPassword = bin2hex(random_bytes(4)); // 8 символов
    $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    if ($stmt->execute([$hashedPassword, $targetUserId])) {
        $_SESSION['temp_password'] = $tempPassword;
        header('Location: admin.php?action=view&id=' . $targetUserId . '&message=password_reset');
        exit();
    }
}

// Включение/отключение пользователя
if (isset($_GET['toggle_active']) && $targetUserId && $targetUserId != $userId) {
    $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
    if ($stmt->execute([$targetUserId])) {
        header('Location: admin.php?message=toggled');
        exit();
    }
}

// Удаление пользователя
if (isset($_GET['delete']) && $targetUserId && $targetUserId != $userId) {
    try {
        // Проверяем, есть ли у пользователя данные
        $stmt = $pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM students WHERE user_id = ?) as students,
                (SELECT COUNT(*) FROM categories WHERE user_id = ?) as categories,
                (SELECT COUNT(*) FROM topics WHERE user_id = ?) as topics,
                (SELECT COUNT(*) FROM labels WHERE user_id = ?) as labels,
                (SELECT COUNT(*) FROM resources WHERE user_id = ?) as resources,
                (SELECT COUNT(*) FROM diaries WHERE user_id = ?) as diaries
        ");
        $stmt->execute([$targetUserId, $targetUserId, $targetUserId, $targetUserId, $targetUserId, $targetUserId]);
        $dataCount = $stmt->fetch();
        
        $totalItems = $dataCount['students'] + $dataCount['categories'] + $dataCount['topics'] + 
                      $dataCount['labels'] + $dataCount['resources'] + $dataCount['diaries'];
        
        if ($totalItems > 0) {
            // Если есть данные, запрашиваем подтверждение
            $_SESSION['delete_user_' . $targetUserId] = true;
            header('Location: admin.php?confirm_delete=' . $targetUserId);
            exit();
        } else {
            // Если нет данных, удаляем
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$targetUserId]);
            header('Location: admin.php?message=deleted');
            exit();
        }
    } catch (Exception $e) {
        $error = 'Ошибка при удалении: ' . $e->getMessage();
    }
}

// Подтверждение удаления пользователя с данными
if (isset($_GET['confirm_delete']) && isset($_SESSION['delete_user_' . $_GET['confirm_delete']])) {
    $deleteId = $_GET['confirm_delete'];
    unset($_SESSION['delete_user_' . $deleteId]);
    
    // Получаем информацию о пользователе
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$deleteId]);
    $deleteUser = $stmt->fetch();
    
    if ($deleteUser) {
        // Получаем статистику данных
        $stmt = $pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM students WHERE user_id = ?) as students,
                (SELECT COUNT(*) FROM categories WHERE user_id = ?) as categories,
                (SELECT COUNT(*) FROM topics WHERE user_id = ?) as topics,
                (SELECT COUNT(*) FROM labels WHERE user_id = ?) as labels,
                (SELECT COUNT(*) FROM resources WHERE user_id = ?) as resources,
                (SELECT COUNT(*) FROM diaries WHERE user_id = ?) as diaries
        ");
        $stmt->execute([$deleteId, $deleteId, $deleteId, $deleteId, $deleteId, $deleteId]);
        $stats = $stmt->fetch();
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
                        <h5 class="mb-0">Подтверждение удаления пользователя</h5>
                    </div>
                    <div class="card-body">
                        <p class="lead">Пользователь "<?php echo htmlspecialchars($deleteUser['first_name'] . ' ' . $deleteUser['last_name']); ?>" имеет следующие данные:</p>
                        <ul>
                            <li>Учеников: <?php echo $stats['students']; ?></li>
                            <li>Категорий: <?php echo $stats['categories']; ?></li>
                            <li>Тем: <?php echo $stats['topics']; ?></li>
                            <li>Меток: <?php echo $stats['labels']; ?></li>
                            <li>Ресурсов: <?php echo $stats['resources']; ?></li>
                            <li>Дневников: <?php echo $stats['diaries']; ?></li>
                        </ul>
                        <p class="text-danger">При удалении пользователя все его данные будут также удалены!</p>
                        <div class="d-flex justify-content-between">
                            <a href="admin.php" class="btn btn-secondary">Отмена</a>
                            <a href="?force_delete=<?php echo $deleteId; ?>" class="btn btn-danger">Да, удалить пользователя и все данные</a>
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

// Принудительное удаление пользователя с данными
if (isset($_GET['force_delete']) && $targetUserId && $targetUserId != $userId) {
    try {
        $pdo->beginTransaction();
        
        // Удаляем данные пользователя (каскадно удалятся все связанные записи)
        $tables = ['students', 'categories', 'topics', 'labels', 'resources', 'diaries'];
        foreach ($tables as $table) {
            $stmt = $pdo->prepare("DELETE FROM $table WHERE user_id = ?");
            $stmt->execute([$targetUserId]);
        }
        
        // Удаляем пользователя
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$targetUserId]);
        
        $pdo->commit();
        header('Location: admin.php?message=force_deleted');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Ошибка при удалении: ' . $e->getMessage();
    }
}

// Получение списка пользователей
$stmt = $pdo->query("
    SELECT 
        u.*,
        (SELECT COUNT(*) FROM students WHERE user_id = u.id) as students_count,
        (SELECT COUNT(*) FROM diaries WHERE user_id = u.id) as diaries_count,
        (SELECT COUNT(*) FROM topics WHERE user_id = u.id) as topics_count,
        (SELECT COUNT(*) FROM resources WHERE user_id = u.id) as resources_count
    FROM users u
    ORDER BY u.is_active DESC, u.last_name, u.first_name
");
$users = $stmt->fetchAll();

// Получение данных для просмотра/редактирования
$editUser = null;
if (($action === 'view' || $action === 'edit') && $targetUserId) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$targetUserId]);
    $editUser = $stmt->fetch();
    
    if (!$editUser) {
        header('Location: admin.php');
        exit();
    }
    
    // Получаем статистику пользователя
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM students WHERE user_id = ?) as students,
            (SELECT COUNT(*) FROM categories WHERE user_id = ?) as categories,
            (SELECT COUNT(*) FROM topics WHERE user_id = ?) as topics,
            (SELECT COUNT(*) FROM labels WHERE user_id = ?) as labels,
            (SELECT COUNT(*) FROM resources WHERE user_id = ?) as resources,
            (SELECT COUNT(*) FROM diaries WHERE user_id = ?) as diaries,
            (SELECT COUNT(*) FROM lessons l JOIN diaries d ON l.diary_id = d.id WHERE d.user_id = ?) as lessons
    ");
    $stmt->execute([$targetUserId, $targetUserId, $targetUserId, $targetUserId, $targetUserId, $targetUserId, $targetUserId]);
    $userStats = $stmt->fetch();
}

// Общая статистика системы
$stmt = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM users WHERE is_active = 1) as active_users,
        (SELECT COUNT(*) FROM users WHERE is_admin = 1) as admin_users,
        (SELECT COUNT(*) FROM students) as total_students,
        (SELECT COUNT(*) FROM diaries) as total_diaries,
        (SELECT COUNT(*) FROM lessons) as total_lessons
");
$systemStats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Администрирование - Дневник репетитора</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .user-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-left: 4px solid #667eea;
        }
        .user-card.inactive {
            border-left-color: #dc3545;
            opacity: 0.8;
            background: #f8f9fa;
        }
        .user-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .user-name {
            font-size: 1.2em;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .user-email {
            color: #666;
            margin-bottom: 10px;
        }
        .user-meta {
            display: flex;
            gap: 15px;
            margin-top: 10px;
            font-size: 0.9em;
            color: #666;
            flex-wrap: wrap;
        }
        .user-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
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
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .admin-badge {
            background: #ffc107;
            color: #000;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75em;
        }
        .switch-mode-alert {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .stat-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>
    
    <div class="container-fluid py-4">
        <?php if (isset($_SESSION['admin_mode']) && isset($_SESSION['original_user_id'])): ?>
            <div class="switch-mode-alert">
                <span>
                    <i class="bi bi-person-workspace"></i> 
                    Вы в режиме администратора. Просматриваете данные пользователя.
                </span>
                <a href="?switch_back=1" class="btn btn-light btn-sm">
                    <i class="bi bi-arrow-return-left"></i> Вернуться в свой аккаунт
                </a>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                $messages = [
                    'saved' => 'Пользователь успешно сохранен',
                    'deleted' => 'Пользователь удален',
                    'force_deleted' => 'Пользователь и все данные удалены',
                    'toggled' => 'Статус пользователя изменен',
                    'switched' => 'Переключено на пользователя',
                    'switched_back' => 'Возврат в свой аккаунт',
                    'password_reset' => 'Пароль сброшен. Временный пароль показан на странице пользователя.'
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
        
        <?php if (isset($_SESSION['temp_password'])): ?>
            <div class="alert alert-warning">
                <strong>Временный пароль:</strong> <?php echo $_SESSION['temp_password']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" onclick="removeTempPassword()"></button>
            </div>
            <?php unset($_SESSION['temp_password']); ?>
        <?php endif; ?>
        
        <?php if ($action === 'list'): ?>
            <!-- Заголовок и кнопки действий -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-gear"></i> Администрирование</h2>
                <a href="?action=add" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Добавить пользователя
                </a>
            </div>
            
            <!-- Статистика системы -->
            <div class="stats-card mb-4">
                <div class="row">
                    <div class="col-md-2">
                        <div class="text-center">
                            <h3 class="mb-0"><?php echo $systemStats['total_users']; ?></h3>
                            <small>Всего пользователей</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-center">
                            <h3 class="mb-0"><?php echo $systemStats['active_users']; ?></h3>
                            <small>Активных</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-center">
                            <h3 class="mb-0"><?php echo $systemStats['admin_users']; ?></h3>
                            <small>Администраторов</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-center">
                            <h3 class="mb-0"><?php echo $systemStats['total_students']; ?></h3>
                            <small>Учеников</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-center">
                            <h3 class="mb-0"><?php echo $systemStats['total_diaries']; ?></h3>
                            <small>Дневников</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-center">
                            <h3 class="mb-0"><?php echo $systemStats['total_lessons']; ?></h3>
                            <small>Занятий</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Список пользователей -->
            <div class="row">
                <?php foreach ($users as $user): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="user-card <?php echo !$user['is_active'] ? 'inactive' : ''; ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="user-name">
                                    <?php echo htmlspecialchars($user['last_name'] . ' ' . $user['first_name'] . ' ' . ($user['middle_name'] ?? '')); ?>
                                    <?php if ($user['is_admin']): ?>
                                        <span class="admin-badge ms-2">Admin</span>
                                    <?php endif; ?>
                                </div>
                                <span class="badge <?php echo $user['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo $user['is_active'] ? 'Активен' : 'Неактивен'; ?>
                                </span>
                            </div>
                            
                            <div class="user-email">
                                <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
                                <br>
                                <i class="bi bi-person"></i> @<?php echo htmlspecialchars($user['username']); ?>
                            </div>
                            
                            <?php if (!empty($user['phone'])): ?>
                                <div><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($user['phone']); ?></div>
                            <?php endif; ?>
                            
                            <div class="user-meta">
                                <span class="user-meta-item" title="Ученики">
                                    <i class="bi bi-people"></i> <?php echo $user['students_count']; ?>
                                </span>
                                <span class="user-meta-item" title="Дневники">
                                    <i class="bi bi-journals"></i> <?php echo $user['diaries_count']; ?>
                                </span>
                                <span class="user-meta-item" title="Темы">
                                    <i class="bi bi-book"></i> <?php echo $user['topics_count']; ?>
                                </span>
                                <span class="user-meta-item" title="Ресурсы">
                                    <i class="bi bi-link"></i> <?php echo $user['resources_count']; ?>
                                </span>
                            </div>
                            
                            <div class="mt-3 d-flex justify-content-end gap-2">
                                <a href="?action=view&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($user['id'] != $userId): ?>
                                    <a href="?switch_to=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-info" 
                                       onclick="return confirm('Переключиться на пользователя <?php echo htmlspecialchars($user['first_name']); ?>?')">
                                        <i class="bi bi-person-switch"></i>
                                    </a>
                                    <a href="?toggle_active=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-warning">
                                        <i class="bi bi-power"></i>
                                    </a>
                                    <a href="?delete=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-danger" 
                                       onclick="return confirm('Удалить пользователя?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
        <?php elseif ($action === 'view' && $editUser): ?>
            <!-- Просмотр пользователя -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="bi bi-person-badge"></i> 
                    Пользователь: <?php echo htmlspecialchars($editUser['first_name'] . ' ' . $editUser['last_name']); ?>
                </h2>
                <div>
                    <?php if ($editUser['id'] != $userId): ?>
                        <a href="?switch_to=<?php echo $editUser['id']; ?>" class="btn btn-info me-2" 
                           onclick="return confirm('Переключиться на этого пользователя?')">
                            <i class="bi bi-person-switch"></i> Переключиться
                        </a>
                        <a href="?reset_password=<?php echo $editUser['id']; ?>" class="btn btn-warning me-2" 
                           onclick="return confirm('Сбросить пароль пользователя? Будет сгенерирован временный пароль.')">
                            <i class="bi bi-key"></i> Сбросить пароль
                        </a>
                    <?php endif; ?>
                    <a href="?action=edit&id=<?php echo $editUser['id']; ?>" class="btn btn-primary me-2">
                        <i class="bi bi-pencil"></i> Редактировать
                    </a>
                    <a href="admin.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Назад
                    </a>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Основная информация</h5>
                        </div>
                        <div class="card-body">
                            <table class="table">
                                <tr>
                                    <th style="width: 40%">Имя пользователя:</th>
                                    <td><?php echo htmlspecialchars($editUser['username']); ?></td>
                                </tr>
                                <tr>
                                    <th>Email:</th>
                                    <td><?php echo htmlspecialchars($editUser['email']); ?></td>
                                </tr>
                                <tr>
                                    <th>Фамилия:</th>
                                    <td><?php echo htmlspecialchars($editUser['last_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Имя:</th>
                                    <td><?php echo htmlspecialchars($editUser['first_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Отчество:</th>
                                    <td><?php echo htmlspecialchars($editUser['middle_name'] ?? '-'); ?></td>
                                </tr>
                                <tr>
                                    <th>Телефон:</th>
                                    <td><?php echo htmlspecialchars($editUser['phone'] ?? '-'); ?></td>
                                </tr>
                                <tr>
                                    <th>Роль:</th>
                                    <td>
                                        <?php if ($editUser['is_admin']): ?>
                                            <span class="badge bg-warning">Администратор</span>
                                        <?php else: ?>
                                            <span class="badge bg-info">Репетитор</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Статус:</th>
                                    <td>
                                        <?php if ($editUser['is_active']): ?>
                                            <span class="badge bg-success">Активен</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Неактивен</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Дата регистрации:</th>
                                    <td><?php echo date('d.m.Y H:i', strtotime($editUser['created_at'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Последний вход:</th>
                                    <td><?php echo $editUser['last_login'] ? date('d.m.Y H:i', strtotime($editUser['last_login'])) : 'Никогда'; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Статистика пользователя</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="stat-box">
                                        <div class="stat-number"><?php echo $userStats['students']; ?></div>
                                        <div>Учеников</div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="stat-box">
                                        <div class="stat-number"><?php echo $userStats['categories']; ?></div>
                                        <div>Категорий</div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="stat-box">
                                        <div class="stat-number"><?php echo $userStats['topics']; ?></div>
                                        <div>Тем</div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="stat-box">
                                        <div class="stat-number"><?php echo $userStats['labels']; ?></div>
                                        <div>Меток</div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="stat-box">
                                        <div class="stat-number"><?php echo $userStats['resources']; ?></div>
                                        <div>Ресурсов</div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="stat-box">
                                        <div class="stat-number"><?php echo $userStats['diaries']; ?></div>
                                        <div>Дневников</div>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="stat-box">
                                        <div class="stat-number"><?php echo $userStats['lessons']; ?></div>
                                        <div>Занятий</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <!-- Форма добавления/редактирования пользователя -->
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="bi bi-<?php echo $action === 'add' ? 'plus-circle' : 'pencil'; ?>"></i>
                                <?php echo $action === 'add' ? 'Добавление пользователя' : 'Редактирование пользователя'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" class="needs-validation" novalidate>
                                <?php if ($action === 'edit' && $editUser): ?>
                                    <input type="hidden" name="user_id" value="<?php echo $editUser['id']; ?>">
                                <?php endif; ?>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Имя пользователя *</label>
                                        <input type="text" name="username" class="form-control" 
                                               value="<?php echo $editUser ? htmlspecialchars($editUser['username']) : ''; ?>" 
                                               required maxlength="50">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email *</label>
                                        <input type="email" name="email" class="form-control" 
                                               value="<?php echo $editUser ? htmlspecialchars($editUser['email']) : ''; ?>" 
                                               required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Фамилия *</label>
                                        <input type="text" name="last_name" class="form-control" 
                                               value="<?php echo $editUser ? htmlspecialchars($editUser['last_name']) : ''; ?>" 
                                               required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Имя *</label>
                                        <input type="text" name="first_name" class="form-control" 
                                               value="<?php echo $editUser ? htmlspecialchars($editUser['first_name']) : ''; ?>" 
                                               required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Отчество</label>
                                        <input type="text" name="middle_name" class="form-control" 
                                               value="<?php echo $editUser ? htmlspecialchars($editUser['middle_name'] ?? '') : ''; ?>">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Телефон</label>
                                    <input type="text" name="phone" class="form-control" 
                                           value="<?php echo $editUser ? htmlspecialchars($editUser['phone'] ?? '') : ''; ?>">
                                </div>
                                
                                <?php if ($action === 'add' || ($action === 'edit' && $editUser['id'] == $userId)): ?>
                                    <div class="mb-3">
                                        <label class="form-label">Пароль <?php echo $action === 'add' ? '*' : '(оставьте пустым, чтобы не менять)'; ?></label>
                                        <input type="password" name="password" class="form-control" 
                                               <?php echo $action === 'add' ? 'required' : ''; ?>
                                               minlength="6">
                                        <?php if ($action === 'add'): ?>
                                            <small class="text-muted">Минимум 6 символов</small>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input type="checkbox" name="is_admin" class="form-check-input" id="isAdmin"
                                                   <?php echo ($editUser && $editUser['is_admin']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="isAdmin">Администратор</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input type="checkbox" name="is_active" class="form-check-input" id="isActive"
                                                   <?php echo ($editUser && $editUser['is_active']) ? 'checked' : ''; ?>
                                                   <?php echo ($editUser && $editUser['id'] == $userId) ? 'disabled' : ''; ?>>
                                            <label class="form-check-label" for="isActive">Активен</label>
                                            <?php if ($editUser && $editUser['id'] == $userId): ?>
                                                <small class="text-muted d-block">Нельзя деактивировать себя</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <a href="admin.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left"></i> Назад
                                    </a>
                                    <button type="submit" name="save_user" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Сохранить
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Удаление временного пароля из сессии при закрытии уведомления
        function removeTempPassword() {
            fetch('admin.php?remove_temp_password=1', {
                method: 'POST'
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