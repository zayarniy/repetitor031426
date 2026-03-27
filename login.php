<?php
require_once 'config.php';

// Функция для логирования
function logAuthAttempt($type, $email, $status, $message = '') {
    $logFile = __DIR__ . '/auth_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $logEntry = sprintf(
        "[%s] %s | IP: %s | Email: %s | Status: %s | Message: %s | User-Agent: %s\n",
        $timestamp,
        strtoupper($type),
        $ip,
        $email,
        $status,
        $message,
        $userAgent
    );
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Если уже авторизован, перенаправляем на дашборд
if (isAuthenticated()) {
    logAuthAttempt('REDIRECT', $_SESSION['username'] ?? 'unknown', 'ALREADY_AUTHENTICATED', 'Пользователь уже авторизован');
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

// Обработка входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        logAuthAttempt('ATTEMPT', $email, 'PENDING', 'Попытка входа');
        
        // Просто проверка логина и хешированного пароля
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = $user['is_admin'];
                
                logAuthAttempt('SUCCESS', $email, 'SUCCESS', "Пользователь {$user['username']} успешно вошел в систему");
                
                header('Location: dashboard.php');
                exit();
            } else {
                $error = 'Неверный email или пароль';
                logAuthAttempt('FAILURE', $email, 'FAILED', 'Неверный email или пароль');
            }
        } catch (PDOException $e) {
            $error = 'Ошибка базы данных. Пожалуйста, попробуйте позже.';
            logAuthAttempt('ERROR', $email, 'DB_ERROR', 'Ошибка базы данных: ' . $e->getMessage());
        }
    } elseif (isset($_POST['register'])) {
        // Заглушка для регистрации
        $success = 'Функция регистрации временно недоступна.';
        logAuthAttempt('REGISTER', $_POST['email'] ?? 'unknown', 'DISABLED', 'Попытка регистрации (функция отключена)');
    } elseif (isset($_POST['reset'])) {
        // Заглушка для восстановления пароля
        $success = 'Функция восстановления пароля временно недоступна.';
        logAuthAttempt('RESET', $_POST['email'] ?? 'unknown', 'DISABLED', 'Попытка восстановления пароля (функция отключена)');
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Дневник репетитора - вход</title>
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link rel="manifest" href="manifest.json">
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
        .card-header p {
            color: #777;
            font-size: 14px;
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
        .btn-outline-secondary {
            border-radius: 10px;
            padding: 12px;
        }
        .alert {
            border-radius: 10px;
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
        .footer-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card animate__animated animate__fadeIn">
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
                
                <!-- Форма входа -->
                <form method="POST" action="" id="loginForm">
                    <div class="mb-3">
                        <input type="email" name="email" class="form-control" placeholder="Email" required value="admin@example.com">
                    </div>
                    <div class="mb-3">
                        <input type="password" name="password" class="form-control" placeholder="Пароль" required value="123">
                    </div>
                    <button type="submit" name="login" class="btn btn-login mb-3">Войти</button>
                </form>                
                
                <!-- Форма регистрации (заглушка) -->
                <form method="POST" action="" id="registerForm" style="display: none;">
                    <div class="mb-3">
                        <input type="text" name="username" class="form-control" placeholder="Имя пользователя">
                    </div>
                    <div class="mb-3">
                        <input type="email" name="reg_email" class="form-control" placeholder="Email">
                    </div>
                    <div class="mb-3">
                        <input type="password" name="reg_password" class="form-control" placeholder="Пароль">
                    </div>
                    <div class="mb-3">
                        <input type="password" name="confirm_password" class="form-control" placeholder="Подтвердите пароль">
                    </div>
                    <button type="submit" name="register" class="btn btn-outline-primary w-100 mb-3">Зарегистрироваться</button>
                </form>
                
                <!-- Форма восстановления (заглушка) -->
                <form method="POST" action="" id="resetForm" style="display: none;">
                    <div class="mb-3">
                        <input type="email" name="reset_email" class="form-control" placeholder="Введите ваш Email">
                    </div>
                    <button type="submit" name="reset" class="btn btn-outline-warning w-100 mb-3">Восстановить пароль</button>
                </form>
                
                <div class="footer-links">
                    <a href="#" onclick="showForm('login')">Вход</a> |
                    <a href="#" onclick="showForm('register')">Регистрация</a> |
                    <a href="#" onclick="showForm('reset')">Забыли пароль?</a>
                </div>
                
            </div>
        </div>
    </div>
    
    <script>
        function showForm(type) {
            document.getElementById('loginForm').style.display = type === 'login' ? 'block' : 'none';
            document.getElementById('registerForm').style.display = type === 'register' ? 'block' : 'none';
            document.getElementById('resetForm').style.display = type === 'reset' ? 'block' : 'none';
        }
    </script>
</body>
</html>