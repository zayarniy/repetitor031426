<?php
require_once 'config.php';
requireAuth();

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT DISTINCT bank_account, MAX(created_at) as last_used
    FROM payments
    WHERE user_id = ? AND bank_account IS NOT NULL AND bank_account != ''
    GROUP BY bank_account
    ORDER BY last_used DESC
    LIMIT 10
");
$stmt->execute([$userId]);
$history = $stmt->fetchAll();

header('Content-Type: application/json');
echo json_encode($history);
?>