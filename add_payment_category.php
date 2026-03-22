<?php
require_once 'config.php';
requireAuth();

$userId = $_SESSION['user_id'];
$name = trim($_POST['name'] ?? '');
$color = $_POST['color'] ?? '#808080';

if (empty($name)) {
    echo json_encode(['success' => false, 'error' => 'Название категории обязательно']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO payment_categories (user_id, name, color)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$userId, $name, $color]);
    
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>