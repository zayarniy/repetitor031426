<?php
require_once '../config.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['order']) && is_array($data['order'])) {
        $currentUser = getCurrentUser($pdo);
        $userId = $currentUser['id'];
        
        try {
            $pdo->beginTransaction();
            
            foreach ($data['order'] as $item) {
                $stmt = $pdo->prepare("UPDATE categories SET sort_order = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$item['order'], $item['id'], $userId]);
            }
            
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?>