<?php
// Простой скрипт для хеширования пароля
$password = '123';
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

echo "Пароль: " . $password . "\n";
echo "Хешированный пароль: " . $hashedPassword . "\n";
echo "\n";
echo "Проверка: " . (password_verify($password, $hashedPassword) ? 'Верно' : 'Неверно') . "\n";
?>