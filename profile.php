<?php
require_once 'config.php';
requireAuth();

$currentUser = getCurrentUser($pdo);
$userId = $currentUser['id'];
$message = '';
$error = '';

// Обработка обновления профиля
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $middleName = trim($_POST['middle_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        // Проверка на уникальность email
        if ($email !== $currentUser['email']) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) {
                $error = 'Этот email уже используется';
            }
        }
        
        if (!$error) {
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, middle_name = ?, phone = ?, email = ? WHERE id = ?");
            if ($stmt->execute([$firstName, $lastName, $middleName, $phone, $email, $userId])) {
                $message = 'Профиль успешно обновлен';
                $currentUser = getCurrentUser($pdo); // Обновляем данные
            } else {
                $error = 'Ошибка при обновлении профиля';
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Проверка текущего пароля
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!password_verify($currentPassword, $user['password_hash'])) {
            $error = 'Неверный текущий пароль';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Новый пароль должен быть не менее 6 символов';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Пароли не совпадают';
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            if ($stmt->execute([$hashedPassword, $userId])) {
                $message = 'Пароль успешно изменен';
            } else {
                $error = 'Ошибка при изменении пароля';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль пользователя</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .profile-container {
            max-width: 600px;
            margin: 30px auto;
        }
        .profile-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-size: 3em;
            font-weight: bold;
        }
        .nav-tabs {
            border-bottom: 2px solid #f0f0f0;
            margin-bottom: 20px;
        }
        .nav-tabs .nav-link {
            border: none;
            color: #666;
            font-weight: 500;
            padding: 10px 20px;
        }
        .nav-tabs .nav-link.active {
            color: #667eea;
            border-bottom: 2px solid #667eea;
            background: transparent;
        }
        .btn-save {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px;
            width: 100%;
            font-weight: 600;
        }
        .btn-save:hover {
            transform: translateY(-2px);
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>
    
    <div class="container profile-container">
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1)); ?>
                </div>
                <h3><?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></h3>
                <p class="text-muted"><?php echo $currentUser['is_admin'] ? 'Администратор' : 'Репетитор'; ?></p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button">Профиль</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button">Безопасность</button>
                </li>
            </ul>
            
            <div class="tab-content">
                <!-- Вкладка профиля -->
                <div class="tab-pane fade show active" id="profile">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Имя *</label>
                            <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($currentUser['first_name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Фамилия *</label>
                            <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($currentUser['last_name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Отчество</label>
                            <input type="text" name="middle_name" class="form-control" value="<?php echo htmlspecialchars($currentUser['middle_name'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Телефон</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Имя пользователя</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($currentUser['username']); ?>" disabled>
                            <small class="text-muted">Имя пользователя изменить нельзя</small>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-save">Сохранить изменения</button>
                    </form>
                </div>
                
                <!-- Вкладка смены пароля -->
                <div class="tab-pane fade" id="password">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Текущий пароль</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Новый пароль</label>
                            <input type="password" name="new_password" class="form-control" required>
                            <small class="text-muted">Минимум 6 символов</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Подтвердите новый пароль</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-save">Изменить пароль</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>