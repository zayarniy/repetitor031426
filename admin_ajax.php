<?php
require_once 'config.php';
session_start();

if (isset($_POST['remove_temp_password']) || isset($_GET['remove_temp_password'])) {
    unset($_SESSION['temp_password']);
    echo json_encode(['success' => true]);
}
?>