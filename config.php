<?php
session_start();

// Параметры подключения к БД

define('DB_HOST', '127.0.0.1');
define('PORT', '3306');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'repetitor031426');

/*
define('DB_HOST', '127.0.0.1');
define('PORT', '3308');
define('DB_USER', 'host1340522_user26');
define('DB_PASS', 'a32d3bd4');
define('DB_NAME', 'host1340522_repetitor031426');
*/

// Подключение к базе данных
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

// Функция для проверки авторизации
function isAuthenticated()
{
    return isset($_SESSION['user_id']);
}

// Функция для проверки прав администратора
function isAdmin()
{
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

// Функция для перенаправления неавторизованных пользователей
function requireAuth()
{
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit();
    }
}

// Функция для получения текущего пользователя
function getCurrentUser($pdo)
{
    if (!isset($_SESSION['user_id']))
        return null;

    $stmt = $pdo->prepare("SELECT id, username, email, first_name, last_name, middle_name, phone, is_admin FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}


// Функция для получения полного URL публичной ссылки
function getPublicDiaryUrl($publicLink)
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
    $basePath = rtrim($scriptPath, '/');

    return $protocol . $host . $basePath . '/public_diary.php?token=' . $publicLink;
}



?>