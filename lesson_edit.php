<?php
require_once 'config.php';
requireAuth();

$lessonId = $_GET['id'] ?? 0;
$from = $_GET['from'] ?? 'dashboard';

// Здесь будет логика получения данных занятия
// Пока просто заглушка
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактирование занятия</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'menu.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <h2>Редактирование занятия #<?php echo $lessonId; ?></h2>
                <p class="text-muted">Здесь будет форма редактирования занятия</p>
                <a href="<?php echo $from; ?>.php" class="btn btn-primary">Вернуться</a>
            </div>
        </div>
    </div>
</body>
</html>