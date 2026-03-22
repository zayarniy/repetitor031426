<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'data' => [], 'error' => ''];

try {
    require_once dirname(__FILE__) . '/config.php';
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Не авторизован');
    }
    
    $userId = (int)$_SESSION['user_id'];
    $studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 3;
    
    if (!$studentId) {
        throw new Exception('Не выбран ученик');
    }
    
    // Проверяем существование таблицы
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'payments'");
    if ($tableCheck->rowCount() == 0) {
        throw new Exception('Таблица payments не существует');
    }
    
    // Используем прямой запрос без подготовленного LIMIT
    $sql = "
        SELECT 
            id,
            amount,
            payment_date,
            bank_account,
            payment_method,
            description
        FROM payments
        WHERE user_id = $userId AND student_id = $studentId
        ORDER BY payment_date DESC, created_at DESC
        LIMIT $limit
    ";
    
    $stmt = $pdo->query($sql);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $methods = [
        'cash' => 'Наличные',
        'card' => 'Карта',
        'bank_transfer' => 'Банковский перевод',
        'online' => 'Онлайн-платеж',
        'other' => 'Другое'
    ];
    
    $formattedPayments = [];
    foreach ($payments as $payment) {
        $formattedPayments[] = [
            'id' => $payment['id'],
            'amount' => (float)$payment['amount'],
            'payment_date' => $payment['payment_date'],
            'bank_account' => $payment['bank_account'] ?? '',
            'payment_method' => $payment['payment_method'],
            'payment_method_name' => $methods[$payment['payment_method']] ?? 'Другое',
            'description' => $payment['description'] ?? ''
        ];
    }
    
    $response['success'] = true;
    $response['data'] = $formattedPayments;
    
} catch (PDOException $e) {
    $response['error'] = 'Ошибка БД: ' . $e->getMessage();
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>