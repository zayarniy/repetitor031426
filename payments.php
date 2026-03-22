<?php
require_once 'config.php';
requireAuth();

$currentUser = getCurrentUser($pdo);
$userId = $currentUser['id'];
$message = '';
$error = '';

// Обработка действий
$action = $_GET['action'] ?? 'list';
$paymentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Получение списка учеников
$stmt = $pdo->prepare("
    SELECT id, last_name, first_name, middle_name, class 
    FROM students 
    WHERE user_id = ? AND is_active = 1 
    ORDER BY last_name, first_name
");
$stmt->execute([$userId]);
$students = $stmt->fetchAll();

// Получение категорий оплат
$stmt = $pdo->prepare("
    SELECT * FROM payment_categories 
    WHERE user_id = ? 
    ORDER BY name
");
$stmt->execute([$userId]);
$paymentCategories = $stmt->fetchAll();

// Сохранение оплаты
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payment'])) {
    $studentId = intval($_POST['student_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
    $bankAccount = trim($_POST['bank_account'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? 'other';
    $receiptNumber = trim($_POST['receipt_number'] ?? '');
    $selectedCategories = $_POST['categories'] ?? [];
    
    if ($studentId == 0) {
        $error = 'Выберите ученика';
    } elseif ($amount <= 0) {
        $error = 'Сумма оплаты должна быть больше 0';
    } else {
        try {
            $pdo->beginTransaction();
            
            if ($action === 'edit' && $paymentId) {
                // Обновление оплаты
                $stmt = $pdo->prepare("
                    UPDATE payments SET 
                        student_id = ?, amount = ?, payment_date = ?, 
                        bank_account = ?, description = ?, payment_method = ?, receipt_number = ?
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([
                    $studentId, $amount, $paymentDate, $bankAccount, 
                    $description, $paymentMethod, $receiptNumber, $paymentId, $userId
                ]);
                
                // Удаляем старые категории
                $stmt = $pdo->prepare("DELETE FROM payment_category_links WHERE payment_id = ?");
                $stmt->execute([$paymentId]);
                
                $message = 'Оплата обновлена';
            } else {
                // Создание новой оплаты
                $stmt = $pdo->prepare("
                    INSERT INTO payments (user_id, student_id, amount, payment_date, bank_account, description, payment_method, receipt_number)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $userId, $studentId, $amount, $paymentDate, $bankAccount, 
                    $description, $paymentMethod, $receiptNumber
                ]);
                $paymentId = $pdo->lastInsertId();
                $message = 'Оплата добавлена';
            }
            
            // Добавляем категории
            if (!empty($selectedCategories)) {
                $stmt = $pdo->prepare("INSERT INTO payment_category_links (payment_id, category_id) VALUES (?, ?)");
                foreach ($selectedCategories as $categoryId) {
                    $stmt->execute([$paymentId, $categoryId]);
                }
            }
            
            $pdo->commit();
            header('Location: payments.php?message=saved');
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Ошибка при сохранении: ' . $e->getMessage();
        }
    }
}

// Удаление оплаты
if (isset($_GET['delete']) && $paymentId) {
    $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$paymentId, $userId])) {
        header('Location: payments.php?message=deleted');
        exit();
    }
}

// Получение данных для редактирования
$editPayment = null;
$selectedCategories = [];

if (($action === 'edit' || $action === 'view') && $paymentId) {
    $stmt = $pdo->prepare("
        SELECT p.*, s.last_name, s.first_name, s.middle_name
        FROM payments p
        JOIN students s ON p.student_id = s.id
        WHERE p.id = ? AND p.user_id = ?
    ");
    $stmt->execute([$paymentId, $userId]);
    $editPayment = $stmt->fetch();
    
    if ($editPayment) {
        // Получаем категории оплаты
        $stmt = $pdo->prepare("SELECT category_id FROM payment_category_links WHERE payment_id = ?");
        $stmt->execute([$paymentId]);
        $selectedCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        header('Location: payments.php');
        exit();
    }
}

// Фильтры
$filterStudent = $_GET['filter_student'] ?? '';
$filterCategory = $_GET['filter_category'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$filterDateTo = $_GET['date_to'] ?? date('Y-m-d');
$filterPeriod = $_GET['period'] ?? 'month';

// Получение списка оплат
$query = "
    SELECT p.*, s.last_name, s.first_name, s.middle_name, s.class,
           GROUP_CONCAT(DISTINCT pc.name) as category_names
    FROM payments p
    JOIN students s ON p.student_id = s.id
    LEFT JOIN payment_category_links pcl ON p.id = pcl.payment_id
    LEFT JOIN payment_categories pc ON pcl.category_id = pc.id
    WHERE p.user_id = ?
";
$params = [$userId];

if (!empty($filterStudent)) {
    $query .= " AND p.student_id = ?";
    $params[] = $filterStudent;
}

if (!empty($filterCategory)) {
    $query .= " AND p.id IN (SELECT payment_id FROM payment_category_links WHERE category_id = ?)";
    $params[] = $filterCategory;
}

if (!empty($filterDateFrom)) {
    $query .= " AND p.payment_date >= ?";
    $params[] = $filterDateFrom;
}

if (!empty($filterDateTo)) {
    $query .= " AND p.payment_date <= ?";
    $params[] = $filterDateTo;
}

$query .= " GROUP BY p.id ORDER BY p.payment_date DESC, p.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Статистика
$totalAmount = 0;
$studentStats = [];
$categoryStats = [];

foreach ($payments as $payment) {
    $totalAmount += $payment['amount'];
    
    // Статистика по ученикам
    $studentKey = $payment['student_id'];
    if (!isset($studentStats[$studentKey])) {
        $studentStats[$studentKey] = [
            'name' => $payment['last_name'] . ' ' . $payment['first_name'],
            'total' => 0,
            'count' => 0
        ];
    }
    $studentStats[$studentKey]['total'] += $payment['amount'];
    $studentStats[$studentKey]['count']++;
    
    // Статистика по категориям
    if (!empty($payment['category_names'])) {
        $categories = explode(',', $payment['category_names']);
        foreach ($categories as $cat) {
            $cat = trim($cat);
            if (!isset($categoryStats[$cat])) {
                $categoryStats[$cat] = [
                    'total' => 0,
                    'count' => 0
                ];
            }
            $categoryStats[$cat]['total'] += $payment['amount'];
            $categoryStats[$cat]['count']++;
        }
    }
}

// Экспорт в CSV
if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="payments_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Заголовки
    fputcsv($output, ['Дата', 'Ученик', 'Сумма', 'Счет', 'Способ оплаты', 'Номер квитанции', 'Категории', 'Описание']);
    
    // Данные
    foreach ($payments as $payment) {
        fputcsv($output, [
            date('d.m.Y', strtotime($payment['payment_date'])),
            $payment['last_name'] . ' ' . $payment['first_name'],
            number_format($payment['amount'], 2, '.', ''),
            $payment['bank_account'],
            $payment['payment_method'],
            $payment['receipt_number'],
            $payment['category_names'],
            $payment['description']
        ]);
    }
    
    fclose($output);
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Учет оплат - Дневник репетитора</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-number {
            font-size: 2em;
            font-weight: bold;
            color: #28a745;
            line-height: 1.2;
        }
        
        .stats-label {
            color: #666;
            font-size: 0.9em;
        }
        
        .filter-panel {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
        }
        
        .payment-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .payment-row {
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .payment-row:hover {
            background: #f8f9fa;
        }
        
        .amount-positive {
            color: #28a745;
            font-weight: bold;
        }
        
        .category-badge {
            background: #e9ecef;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 0.7rem;
            display: inline-block;
            margin-right: 4px;
            margin-bottom: 4px;
        }
        
        .quick-actions {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .quick-actions .btn {
            width: 50px;
            height: 50px;
            border-radius: 25px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .bank-history-item {
            padding: 8px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .bank-history-item:hover {
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>
    
    <div class="container-fluid py-4">
        <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                $messages = [
                    'saved' => 'Оплата успешно сохранена',
                    'deleted' => 'Оплата удалена'
                ];
                echo $messages[$_GET['message']] ?? 'Операция выполнена успешно';
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($action === 'list'): ?>
            <!-- Заголовок и кнопки действий -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-cash-stack"></i> Учет оплат</h2>
                <div>
                    <a href="?export_csv=1" class="btn btn-success me-2">
                        <i class="bi bi-filetype-csv"></i> Экспорт CSV
                    </a>
                    <a href="?action=add" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Добавить оплату
                    </a>
                </div>
            </div>
            
            <!-- Фильтры -->
            <div class="filter-panel">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Ученик</label>
                        <select name="filter_student" class="form-select">
                            <option value="">Все ученики</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['id']; ?>" <?php echo $filterStudent == $student['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Категория</label>
                        <select name="filter_category" class="form-select">
                            <option value="">Все категории</option>
                            <?php foreach ($paymentCategories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $filterCategory == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Дата с</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo $filterDateFrom; ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Дата по</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo $filterDateTo; ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Период</label>
                        <select name="period" class="form-select">
                            <option value="week" <?php echo $filterPeriod == 'week' ? 'selected' : ''; ?>>Неделя</option>
                            <option value="month" <?php echo $filterPeriod == 'month' ? 'selected' : ''; ?>>Месяц</option>
                            <option value="quarter" <?php echo $filterPeriod == 'quarter' ? 'selected' : ''; ?>>Квартал</option>
                            <option value="year" <?php echo $filterPeriod == 'year' ? 'selected' : ''; ?>>Год</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Применить
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Статистика -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo number_format($totalAmount, 0, ',', ' '); ?> ₽</div>
                        <div class="stats-label">Всего поступлений</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo count($payments); ?></div>
                        <div class="stats-label">Количество операций</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo count($studentStats); ?></div>
                        <div class="stats-label">Учеников</div>
                    </div>
                </div>
            </div>
            
            <!-- Статистика по ученикам -->
            <?php if (!empty($studentStats)): ?>
            <div class="stats-card mb-4">
                <h5><i class="bi bi-people"></i> Статистика по ученикам</h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Ученик</th>
                                <th class="text-center">Кол-во оплат</th>
                                <th class="text-end">Сумма</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($studentStats as $stat): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($stat['name']); ?></td>
                                <td class="text-center"><?php echo $stat['count']; ?></td>
                                <td class="text-end amount-positive"><?php echo number_format($stat['total'], 0, ',', ' '); ?> ₽</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Статистика по категориям -->
            <?php if (!empty($categoryStats)): ?>
            <div class="stats-card mb-4">
                <h5><i class="bi bi-tags"></i> Статистика по категориям</h5>
                <div class="row">
                    <?php foreach ($categoryStats as $cat => $stat): ?>
                    <div class="col-md-3 mb-2">
                        <div class="p-2 bg-light rounded">
                            <div class="fw-bold"><?php echo htmlspecialchars($cat); ?></div>
                            <div class="text-success"><?php echo number_format($stat['total'], 0, ',', ' '); ?> ₽</div>
                            <small class="text-muted"><?php echo $stat['count']; ?> операций</small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Список оплат -->
            <div class="stats-card">
                <h5><i class="bi bi-list-ul"></i> Список оплат</h5>
                <div class="table-responsive">
                    <table class="table table-hover payment-table">
                        <thead>
                            <tr>
                                <th>Дата</th>
                                <th>Ученик</th>
                                <th>Сумма</th>
                                <th>Счет</th>
                                <th>Способ</th>
                                <th>Категории</th>
                                <th>Описание</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="bi bi-cash-stack" style="font-size: 2rem;"></i>
                                        <p class="mt-2">Нет записей об оплатах</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payments as $payment): ?>
                                <tr class="payment-row" onclick="window.location.href='?action=edit&id=<?php echo $payment['id']; ?>'">
                                    <td><?php echo date('d.m.Y', strtotime($payment['payment_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($payment['last_name'] . ' ' . $payment['first_name']); ?></td>
                                    <td class="amount-positive"><?php echo number_format($payment['amount'], 0, ',', ' '); ?> ₽</td>
                                    <td><?php echo htmlspecialchars($payment['bank_account'] ?: '—'); ?></td>
                                    <td>
                                        <?php 
                                        $methods = [
                                            'cash' => 'Наличные',
                                            'card' => 'Карта',
                                            'bank_transfer' => 'Перевод',
                                            'online' => 'Онлайн',
                                            'other' => 'Другое'
                                        ];
                                        echo $methods[$payment['payment_method']] ?? 'Другое';
                                        ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($payment['category_names'])): ?>
                                            <?php foreach (explode(',', $payment['category_names']) as $cat): ?>
                                                <span class="category-badge"><?php echo htmlspecialchars(trim($cat)); ?></span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['description'] ?: '—'); ?></td>
                                    <td>
                                        <div onclick="event.stopPropagation()">
                                            <a href="?delete=1&id=<?php echo $payment['id']; ?>" 
                                               class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('Удалить запись об оплате?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <!-- Форма добавления/редактирования оплаты -->
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="bi bi-<?php echo $action === 'add' ? 'plus-circle' : 'pencil'; ?>"></i>
                                <?php echo $action === 'add' ? 'Добавление оплаты' : 'Редактирование оплаты'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" id="paymentForm">
                                <?php if ($action === 'edit' && $editPayment): ?>
                                    <input type="hidden" name="payment_id" value="<?php echo $editPayment['id']; ?>">
                                <?php endif; ?>
                                
                              <div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Ученик *</label>
        <div class="input-group">
            <select name="student_id" class="form-select" required id="student_select">
                <option value="">Выберите ученика</option>
                <?php foreach ($students as $student): ?>
                    <option value="<?php echo $student['id']; ?>" 
                        <?php echo ($editPayment && $editPayment['student_id'] == $student['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? '')); ?>
                        <?php if ($student['class']): ?>(<?php echo htmlspecialchars($student['class']); ?> класс)<?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-outline-secondary" onclick="loadLastPayments()" 
                    title="Выбрать из последних оплат">
                <i class="bi bi-clock-history"></i>
            </button>
        </div>
    </div>
    
    <div class="col-md-6 mb-3">
        <label class="form-label">Сумма *</label>
        <input type="number" name="amount" class="form-control" 
               value="<?php echo $editPayment ? htmlspecialchars($editPayment['amount']) : ''; ?>" 
               step="100" min="0" required>
    </div>
</div> 
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Дата оплаты *</label>
                                        <input type="date" name="payment_date" class="form-control" 
                                               value="<?php echo $editPayment ? $editPayment['payment_date'] : date('Y-m-d'); ?>" 
                                               required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Способ оплаты</label>
                                        <select name="payment_method" class="form-select">
                                            <option value="bank_transfer" <?php echo ($editPayment && $editPayment['payment_method'] == 'bank_transfer') ? 'selected' : ''; ?>>Банковский перевод</option>
                                            <option value="cash" <?php echo ($editPayment && $editPayment['payment_method'] == 'cash') ? 'selected' : ''; ?>>Наличные</option>
                                            <option value="card" <?php echo ($editPayment && $editPayment['payment_method'] == 'card') ? 'selected' : ''; ?>>Карта</option>
                                            <option value="online" <?php echo ($editPayment && $editPayment['payment_method'] == 'online') ? 'selected' : ''; ?>>Онлайн-платеж</option>
                                            <option value="other" <?php echo ($editPayment && $editPayment['payment_method'] == 'other') ? 'selected' : ''; ?>>Другое</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Банк / Счет</label>
                                        <div class="input-group">
                                            <input type="text" name="bank_account" id="bank_account" class="form-control" 
                                                   value="<?php echo $editPayment ? htmlspecialchars($editPayment['bank_account'] ?? '') : ''; ?>"
                                                   placeholder="Название банка или номер счета"
                                                   autocomplete="off">
                                            <button type="button" class="btn btn-outline-secondary" onclick="showBankHistory()">
                                                <i class="bi bi-clock-history"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Номер квитанции</label>
                                        <input type="text" name="receipt_number" class="form-control" 
                                               value="<?php echo $editPayment ? htmlspecialchars($editPayment['receipt_number'] ?? '') : ''; ?>"
                                               placeholder="Необязательно">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Категории оплаты</label>
                                    <div class="border rounded p-3" style="max-height: 150px; overflow-y: auto;">
                                        <?php if (empty($paymentCategories)): ?>
                                            <p class="text-muted">Нет категорий. <a href="#" onclick="event.preventDefault(); addCategory()">Создайте категорию</a></p>
                                        <?php else: ?>
                                            <?php foreach ($paymentCategories as $category): ?>
                                                <div class="form-check">
                                                    <input type="checkbox" name="categories[]" value="<?php echo $category['id']; ?>" 
                                                           class="form-check-input" 
                                                           id="cat_<?php echo $category['id']; ?>"
                                                           <?php echo (in_array($category['id'], $selectedCategories)) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="cat_<?php echo $category['id']; ?>">
                                                        <?php echo htmlspecialchars($category['name']); ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Описание</label>
                                    <textarea name="description" class="form-control" rows="3" 
                                              placeholder="Дополнительная информация об оплате"><?php echo $editPayment ? htmlspecialchars($editPayment['description'] ?? '') : ''; ?></textarea>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <a href="payments.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left"></i> Назад
                                    </a>
                                    <button type="submit" name="save_payment" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Сохранить
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <!-- Информационный блок -->
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-info-circle"></i> Информация</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Советы:</strong></p>
                            <ul class="small">
                                <li>Записывайте все поступления для точного учета</li>
                                <li>Используйте категории для группировки платежей</li>
                                <li>История банков поможет быстро заполнить поле</li>
                                <li>Экспортируйте данные для отчетности</li>
                            </ul>
                            <hr>
                            <p><strong>Быстрые действия:</strong></p>
                            <button type="button" class="btn btn-sm btn-outline-secondary w-100 mb-2" onclick="quickAddAmount(1000)">
                                +1000 ₽
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary w-100 mb-2" onclick="quickAddAmount(2000)">
                                +2000 ₽
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary w-100" onclick="quickAddAmount(5000)">
                                +5000 ₽
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Модальное окно истории банков -->
    <div class="modal fade" id="bankHistoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">История банковских счетов</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="bankHistoryList">
                        <p class="text-muted text-center py-3">Загрузка...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно добавления категории -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Добавить категорию оплаты</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Название категории</label>
                        <input type="text" id="new_category_name" class="form-control" placeholder="Например: Абонемент, Разовое занятие">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Цвет</label>
                        <input type="color" id="new_category_color" class="form-control form-control-color" value="#28a745">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-primary" onclick="saveCategory()">Сохранить</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Получение истории банков
        function showBankHistory() {
            const modal = new bootstrap.Modal(document.getElementById('bankHistoryModal'));
            const listContainer = document.getElementById('bankHistoryList');
            
            listContainer.innerHTML = '<p class="text-muted text-center py-3">Загрузка...</p>';
            
            fetch('get_bank_history.php')
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        listContainer.innerHTML = '<p class="text-muted text-center py-3">Нет сохраненных счетов</p>';
                    } else {
                        listContainer.innerHTML = data.map(item => `
                            <div class="bank-history-item" onclick="selectBankAccount('${item.bank_account.replace(/'/g, "\\'")}')">
                                <strong>${item.bank_account}</strong><br>
                                <small class="text-muted">Последнее: ${item.last_used}</small>
                            </div>
                        `).join('');
                    }
                })
                .catch(error => {
                    listContainer.innerHTML = '<p class="text-muted text-center py-3">Ошибка загрузки</p>';
                });
            
            modal.show();
        }
        
        function selectBankAccount(account) {
            document.getElementById('bank_account').value = account;
            bootstrap.Modal.getInstance(document.getElementById('bankHistoryModal')).hide();
        }
        
        // Быстрое добавление суммы
        function quickAddAmount(amount) {
            const amountInput = document.querySelector('input[name="amount"]');
            if (amountInput) {
                amountInput.value = amount;
            }
        }
        
        // Добавление категории
        function addCategory() {
            const modal = new bootstrap.Modal(document.getElementById('addCategoryModal'));
            modal.show();
        }
        
        function saveCategory() {
            const name = document.getElementById('new_category_name').value;
            const color = document.getElementById('new_category_color').value;
            
            if (!name) {
                alert('Введите название категории');
                return;
            }
            
            fetch('add_payment_category.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'name=' + encodeURIComponent(name) + '&color=' + encodeURIComponent(color)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Ошибка: ' + data.error);
                }
            })
            .catch(error => {
                alert('Ошибка при сохранении');
            });
        }


// Функция для получения последних оплат ученика
function loadLastPayments() {
    const studentId = document.querySelector('select[name="student_id"]').value;
    if (!studentId) {
        alert('Сначала выберите ученика');
        return;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('lastPaymentsModal'));
    const listContainer = document.getElementById('lastPaymentsList');
    
    listContainer.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Загрузка...</p></div>';
    modal.show();
    
    fetch(`get_last_payments.php?student_id=${studentId}&limit=3`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Полученные данные:', data); // Для отладки
            
            // Проверяем структуру ответа
            if (!data.success) {
                throw new Error(data.error || 'Неизвестная ошибка');
            }
            
            // data.data - это массив оплат
            if (!data.data || !Array.isArray(data.data)) {
                throw new Error('Некорректный формат данных');
            }
            
            if (data.data.length === 0) {
                listContainer.innerHTML = '<p class="text-muted text-center py-3">Нет предыдущих оплат для этого ученика</p>';
            } else {
                listContainer.innerHTML = data.data.map(payment => {
                    const date = new Date(payment.payment_date);
                    const formattedDate = date.toLocaleDateString('ru-RU');
                    
                    return `
                        <div class="payment-history-item mb-2 p-3 border rounded" 
                             onclick='fillPaymentData(${JSON.stringify(payment)})'
                             style="cursor: pointer;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>${formattedDate}</strong>
                                    <span class="badge bg-success ms-2">${payment.amount.toLocaleString('ru-RU')} ₽</span>
                                </div>
                                <i class="bi bi-arrow-right-circle text-primary"></i>
                            </div>
                            <div class="small text-muted mt-1">
                                ${payment.payment_method_name} 
                                ${payment.bank_account ? ' • ' + escapeHtml(payment.bank_account) : ''}
                            </div>
                            ${payment.description ? `<div class="small text-muted mt-1">${escapeHtml(payment.description)}</div>` : ''}
                        </div>
                    `;
                }).join('');
            }
        })
        .catch(error => {
            console.error('Ошибка:', error);
            listContainer.innerHTML = `<div class="alert alert-danger text-center py-3">Ошибка загрузки данных: ${error.message}</div>`;
        });
}

// Функция для экранирования HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Функция для заполнения формы данными из выбранной оплаты
function fillPaymentData(payment) {
    document.querySelector('input[name="amount"]').value = payment.amount;
    document.querySelector('input[name="bank_account"]').value = payment.bank_account || '';
    document.querySelector('select[name="payment_method"]').value = payment.payment_method;
    
    // Если есть описание, заполняем
    if (payment.description) {
        document.querySelector('textarea[name="description"]').value = payment.description;
    }
    
    // Закрываем модальное окно
    bootstrap.Modal.getInstance(document.getElementById('lastPaymentsModal')).hide();
}

// Функция для экранирования HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Функция для заполнения формы данными из выбранной оплаты
function fillPaymentData(payment) {
    document.querySelector('input[name="amount"]').value = payment.amount;
    document.querySelector('input[name="bank_account"]').value = payment.bank_account || '';
    document.querySelector('select[name="payment_method"]').value = payment.payment_method;
    
    // Если нужно заполнить категории
    if (payment.categories && payment.categories.length > 0) {
        document.querySelectorAll('input[name="categories[]"]').forEach(checkbox => {
            const categoryId = parseInt(checkbox.value);
            checkbox.checked = payment.categories.includes(categoryId);
        });
    }
    
    if (payment.description) {
        document.querySelector('textarea[name="description"]').value = payment.description;
    }
    
    bootstrap.Modal.getInstance(document.getElementById('lastPaymentsModal')).hide();
}

    </script>

    <!-- Модальное окно выбора последних оплат -->
<div class="modal fade" id="lastPaymentsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Последние оплаты ученика</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="lastPaymentsList">
                    <p class="text-muted text-center py-3">Выберите ученика для просмотра истории</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>
</body>
</html>