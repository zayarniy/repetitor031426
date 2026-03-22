<?php
$currentUser = getCurrentUser($pdo);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <i class="bi bi-journal-bookmark-fill"></i> Дневник репетитора
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="bi bi-speedometer2"></i> Дашборд
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="students.php">
                        <i class="bi bi-people"></i> Ученики
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-book"></i> Банки
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="categories.php">Категории</a></li>
                        <li><a class="dropdown-item" href="topics.php">Темы</a></li>
                        <li><a class="dropdown-item" href="labels.php">Метки</a></li>
                        <li><a class="dropdown-item" href="resources.php">Ресурсы</a></li>
                                <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="planning.php">
            <i class="bi bi-calendar-week"></i> Планирование
        </a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="diaries.php">
                        <i class="bi bi-journals"></i> Дневники
                    </a>
                </li>
                <li class="nav-item">
    <a class="nav-link" href="payments.php">
        <i class="bi bi-cash-stack"></i> Оплаты
    </a>
</li>
<li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle" href="#" id="statsDropdown" role="button" data-bs-toggle="dropdown">
        <i class="bi bi-graph-up"></i> Статистика
    </a>
    <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="statistics.php">Общая статистика</a></li>
        <li><a class="dropdown-item" href="student_stats.php">По ученикам</a></li>
    </ul>
</li>                <?php if (isAdmin()): ?>
                <li class="nav-item">
                    <a class="nav-link" href="admin.php">
                        <i class="bi bi-gear"></i> Администрирование
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> 
                        <?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php">
                            <i class="bi bi-person"></i> Профиль
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">
                            <i class="bi bi-box-arrow-right"></i> Выход
                        </a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">