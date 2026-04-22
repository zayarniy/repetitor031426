<?php
require_once 'config.php';

// Если уже авторизован, перенаправляем на дашборд
if (isAuthenticated()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

// Проверка куки "запомнить меня"
if (isset($_COOKIE['remember_token']) && !isset($_SESSION['user_id'])) {
    $token = $_COOKIE['remember_token'];
    
    $stmt = $pdo->prepare("
        SELECT id, username, email, is_admin, remember_token, token_expires 
        FROM users 
        WHERE remember_token = ? AND token_expires > NOW()
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = $user['is_admin'];
        header('Location: dashboard.php');
        exit();
    }
}

// Обработка входа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? true : false;
    
    // Специальный администратор для тестирования
    if ($email === 'admin@example.com' && $password === '123') {
        // Проверяем, существует ли такой пользователь в БД
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute(['admin@example.com']);
        $user = $stmt->fetch();
        
        if (!$user) {
            // Создаем тестового администратора
            $hashedPassword = password_hash('123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, is_admin) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute(['admin', 'admin@example.com', $hashedPassword, 'Admin', 'User', 1]);
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute(['admin@example.com']);
            $user = $stmt->fetch();
        } else {
            // Проверяем пароль
            if (!password_verify('123', $user['password_hash'])) {
                $hashedPassword = password_hash('123', PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
                $stmt->execute([$hashedPassword, 'admin@example.com']);
            }
        }
        
        // Успешный вход
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = $user['is_admin'];
        
        // Обработка "запомнить меня"
        if ($remember) {
            $rememberToken = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            $stmt = $pdo->prepare("UPDATE users SET remember_token = ?, token_expires = ? WHERE id = ?");
            $stmt->execute([$rememberToken, $expires, $user['id']]);
            
            setcookie('remember_token', $rememberToken, time() + (86400 * 30), '/');
        }
        
        header('Location: dashboard.php');
        exit();
    } else {
        // Обычная проверка для других пользователей
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = $user['is_admin'];
            
            // Обработка "запомнить меня"
            if ($remember) {
                $rememberToken = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                
                $stmt = $pdo->prepare("UPDATE users SET remember_token = ?, token_expires = ? WHERE id = ?");
                $stmt->execute([$rememberToken, $expires, $user['id']]);
                
                setcookie('remember_token', $rememberToken, time() + (86400 * 30), '/');
            }
            
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Неверный email или пароль';
        }
    }
} elseif (isset($_POST['register'])) {
    $success = 'Функция регистрации временно недоступна. Используйте admin@example.com / 123';
} elseif (isset($_POST['reset'])) {
    $success = 'Функция восстановления пароля временно недоступна.';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в систему - Дневник репетитора</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container {
            max-width: 400px;
            width: 90%;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            backdrop-filter: blur(10px);
            background: rgba(255,255,255,0.95);
        }
        .card-header {
            background: transparent;
            border-bottom: 2px solid #f0f0f0;
            text-align: center;
            padding: 25px 20px 15px;
        }
        .card-header h3 {
            color: #333;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .card-body {
            padding: 30px;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: none;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: transform 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
        .footer-links {
            text-align: center;
            margin-top: 20px;
        }
        .footer-links a {
            color: #667eea;
            text-decoration: none;
            margin: 0 10px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card">
            <div class="card-header">
                <h3>Добро пожаловать!</h3>
                <p>Войдите в систему дневника репетитора</p>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="" id="loginForm">
                    <div class="mb-3">
                        <input type="email" name="email" class="form-control" placeholder="Email" required value="admin@example.com">
                    </div>
                    <div class="mb-3">
                        <input type="password" name="password" class="form-control" placeholder="Пароль" required value="123">
                    </div>
                    
                    <!-- Чекбокс "Запомнить меня" -->
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="remember" class="form-check-input" id="rememberCheck">
                        <label class="form-check-label" for="rememberCheck">Запомнить меня</label>
                    </div>
                    
                    <button type="submit" name="login" class="btn btn-login mb-3">Войти</button>
                </form>
                
                <div class="footer-links">
                    <a href="#" onclick="showForm('register')">Регистрация</a> |
                    <a href="#" onclick="showForm('reset')">Забыли пароль?</a>
                </div>
                
                <div class="mt-3 text-center">
                    <small class="text-muted">
                        Тестовый доступ: admin@example.com / 123
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function showForm(type) {
            alert('Функция временно недоступна');
        }
    </script>
</body>
</html>