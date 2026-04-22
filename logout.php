<?php
session_start();
require_once 'config.php';

// Удаляем токен "запомнить меня" из базы данных
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL, token_expires = NULL WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
}

// Удаляем куку
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Уничтожаем сессию
session_destroy();

header('Location: login.php');
exit();
?>