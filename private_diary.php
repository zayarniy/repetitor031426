<?php
require_once 'config.php';
requireAuth();

$currentUser = getCurrentUser($pdo);
$userId = $currentUser['id'];

// Получаем ID дневника из URL
$diaryId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$diaryId) {
    header('Location: diaries.php');
    exit();
}

// Получаем информацию о дневнике с данными ученика
$stmt = $pdo->prepare("
    SELECT 
        d.*,
        s.id as student_id,
        s.first_name as student_first_name,
        s.last_name as student_last_name,
        s.middle_name as student_middle_name,
        s.class as student_class,
        s.phone as student_phone,
        s.email as student_email,
        s.goals as student_goals,
        s.birth_date as student_birth_date,
        s.start_date as student_start_date,
        s.end_date as student_end_date,
        s.city as student_city,
        s.messenger1 as student_messenger1,
        s.messenger2 as student_messenger2,
        s.messenger3 as student_messenger3,
        s.created_at as student_created_at,
        c.name as category_name,
        c.color as category_color,
        u.first_name as tutor_first_name,
        u.last_name as tutor_last_name
    FROM diaries d
    JOIN students s ON d.student_id = s.id
    JOIN users u ON d.user_id = u.id
    LEFT JOIN categories c ON d.category_id = c.id
    WHERE d.id = ? AND d.user_id = ?
");
$stmt->execute([$diaryId, $userId]);
$diary = $stmt->fetch();

if (!$diary) {
    header('Location: diaries.php');
    exit();
}

// Получаем статистику по занятиям
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_lessons,
        SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed_lessons,
        SUM(CASE WHEN is_cancelled = 1 THEN 1 ELSE 0 END) as cancelled_lessons,
        SUM(CASE WHEN is_paid = 0 AND is_completed = 1 THEN 1 ELSE 0 END) as unpaid_lessons,
        SUM(CASE WHEN is_paid = 1 THEN cost ELSE 0 END) as paid_sum,
        SUM(cost) as total_sum,
        AVG(grade_lesson) as avg_grade,
        AVG(grade_homework) as avg_homework,
        SUM(duration) as total_minutes
    FROM lessons 
    WHERE diary_id = ?
");
$stmt->execute([$diaryId]);
$stats = $stmt->fetch();

// Получаем статистику по темам
$stmt = $pdo->prepare("
    SELECT 
        t.id,
        t.name as topic_name,
        COALESCE(c.name, 'Без категории') as category_name,
        COALESCE(c.color, '#808080') as category_color,
        COUNT(DISTINCT l.id) as lessons_count,
        COUNT(*) as total_occurrences,
        MIN(l.grade_lesson) as min_grade,
        MAX(l.grade_lesson) as max_grade,
        AVG(l.grade_lesson) as avg_grade,
        COUNT(CASE WHEN l.grade_lesson IS NOT NULL THEN 1 END) as graded_count,
        MAX(l.lesson_date) as last_used
    FROM topics t
    JOIN lesson_topics lt ON t.id = lt.topic_id
    JOIN lessons l ON lt.lesson_id = l.id
    LEFT JOIN categories c ON t.category_id = c.id
    WHERE l.diary_id = ? AND l.is_completed = 1
    GROUP BY t.id, t.name, c.name, c.color
    ORDER BY total_occurrences DESC, t.name
");
$stmt->execute([$diaryId]);
$topicsStats = $stmt->fetchAll();

// Группируем темы по категориям
$groupedTopics = [];
foreach ($topicsStats as $topic) {
    $category = $topic['category_name'];
    if (!isset($groupedTopics[$category])) {
        $groupedTopics[$category] = [
            'color' => $topic['category_color'],
            'topics' => []
        ];
    }
    $groupedTopics[$category]['topics'][] = $topic;
}

// Получаем комментарии к ученику
$stmt = $pdo->prepare("
    SELECT sc.*, u.first_name, u.last_name 
    FROM student_comments sc
    JOIN users u ON sc.user_id = u.id
    WHERE sc.student_id = ? 
    ORDER BY sc.created_at DESC
    LIMIT 10
");
$stmt->execute([$diary['student_id']]);
$studentComments = $stmt->fetchAll();

// Получаем последние 10 занятий
$stmt = $pdo->prepare("
    SELECT 
        l.*,
        GROUP_CONCAT(DISTINCT t.name SEPARATOR ', ') as topic_names
    FROM lessons l
    LEFT JOIN lesson_topics lt ON l.id = lt.lesson_id
    LEFT JOIN topics t ON lt.topic_id = t.id
    WHERE l.diary_id = ?
    GROUP BY l.id
    ORDER BY l.lesson_date DESC, l.start_time DESC    
");
$stmt->execute([$diaryId]);
$recentLessons = $stmt->fetchAll();

// Получаем информацию о родителях
$stmt = $pdo->prepare("
    SELECT * FROM parents 
    WHERE student_id = ?
    ORDER BY full_name
");
$stmt->execute([$diary['student_id']]);
$parents = $stmt->fetchAll();

// Форматирование времени
$totalHours = floor(($stats['total_minutes'] ?? 0) / 60);
$totalMinutes = ($stats['total_minutes'] ?? 0) % 60;
$totalTimeFormatted = '';
if ($totalHours > 0) {
    $totalTimeFormatted .= $totalHours . ' ч ';
}
if ($totalMinutes > 0 || $totalHours == 0) {
    $totalTimeFormatted .= $totalMinutes . ' мин';
}

// Экспорт в CSV
if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="diary_' . $diaryId . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Заголовки
    fputcsv($output, ['Дата', 'Время', 'Длительность', 'Темы', 'ДЗ', 'Оценка', 'Оплата', 'Комментарий']);
    
    // Данные
    foreach ($recentLessons as $lesson) {
        fputcsv($output, [
            date('d.m.Y', strtotime($lesson['lesson_date'])),
            date('H:i', strtotime($lesson['start_time'])),
            $lesson['duration'] . ' мин',
            $lesson['topic_names'] ?: $lesson['topics_manual'],
            $lesson['homework_manual'],
            $lesson['grade_lesson'] ?? '-',
            $lesson['is_paid'] ? 'Оплачено' : 'Не оплачено',
            $lesson['comment']
        ]);
    }
    
    fclose($output);
    exit();
}

// Экспорт в JSON
if (isset($_GET['export_json'])) {
    $exportData = [
        'diary' => $diary,
        'statistics' => $stats,
        'topics_statistics' => $topicsStats,
        'recent_lessons' => $recentLessons,
        'student_comments' => $studentComments,
        'parents' => $parents,
        'export_date' => date('Y-m-d H:i:s')
    ];
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="diary_' . $diaryId . '_' . date('Y-m-d') . '.json"');
    echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Дневник - <?php echo htmlspecialchars($diary['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        
        .diary-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            position: relative;
        }
        
        .action-buttons {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
        }
        
        .action-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            color: white;
        }
        
        .action-btn i {
            font-size: 1.2rem;
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
            font-size: 2.2em;
            font-weight: bold;
            color: #667eea;
            line-height: 1.2;
        }
        
        .stats-label {
            color: #666;
            font-size: 0.9em;
        }
        
        .info-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .info-title {
            font-size: 1.1em;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #333;
        }
        
        .info-title i {
            color: #667eea;
        }
        
        .info-item {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 0.9em;
        }
        
        .info-value {
            color: #333;
        }
        
        .topic-category {
            margin-bottom: 20px;
        }
        
        .topic-category-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid;
        }
        
        .topic-table {
            font-size: 0.9em;
        }
        
        .topic-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .grade-cell {
            font-weight: 600;
        }
        
        .grade-high {
            color: #28a745;
        }
        
        .grade-medium {
            color: #ffc107;
        }
        
        .grade-low {
            color: #dc3545;
        }
        
        .lesson-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #667eea;
        }
        
        .lesson-card.completed {
            border-left-color: #28a745;
        }
        
        .lesson-card.cancelled {
            border-left-color: #dc3545;
            opacity: 0.7;
        }
        
        .lesson-card.planned {
            border-left-color: #ffc107;
        }
        
        .comment-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 3px solid #667eea;
        }
        
        .comment-meta {
            font-size: 0.85em;
            color: #666;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
        }
        
        .badge-unpaid {
            background: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9em;
        }
        
        .progress {
            height: 8px;
            border-radius: 4px;
        }
        
        .progress-bar {
            border-radius: 4px;
        }
        
        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            border-radius: 12px;
            padding: 8px 0;
            min-width: 200px;
        }
        
        .dropdown-item {
            padding: 10px 20px;
            transition: all 0.2s;
        }
        
        .dropdown-item:hover {
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            padding-left: 25px;
        }
        
        .dropdown-item i {
            width: 22px;
            margin-right: 8px;
        }
        
        .copy-link-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .copy-link-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .action-buttons {
                position: static;
                justify-content: flex-end;
                margin-bottom: 20px;
            }
            
            .diary-header {
                padding-top: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>
    
    <div class="container-fluid py-4">
        <!-- Шапка дневника -->
        <div class="diary-header">
            <div class="action-buttons">
                <!-- Кнопка перехода к занятиям -->
                <a href="lessons.php?diary_id=<?php echo $diaryId; ?>" class="action-btn" data-bs-toggle="tooltip" title="Перейти к занятиям">
                    <i class="bi bi-calendar-check"></i>
                </a>
                
                <!-- Кнопка информации об ученике -->
                <a href="students.php?action=view&id=<?php echo $diary['student_id']; ?>" class="action-btn" data-bs-toggle="tooltip" title="Информация об ученике">
                    <i class="bi bi-person"></i>
                </a>
                
                <!-- Кнопка возврата к списку дневников -->
                <a href="diaries.php" class="action-btn" data-bs-toggle="tooltip" title="К списку дневников">
                    <i class="bi bi-journals"></i>
                </a>
                
                <!-- Кнопка копирования публичной ссылки (если есть) -->
                <?php if ($diary['public_link']): ?>
                    <button class="action-btn copy-link-btn" onclick="copyPublicLink()" data-bs-toggle="tooltip" title="Копировать публичную ссылку">
                        <i class="bi bi-link"></i>
                    </button>
                <?php endif; ?>
                
                <!-- Выпадающее меню экспорта -->
                <div class="dropdown">
                    <button class="action-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-toggle="tooltip" title="Экспорт данных">
                        <i class="bi bi-download"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="?id=<?php echo $diaryId; ?>&export_csv=1">
                                <i class="bi bi-filetype-csv text-success"></i> Экспорт в CSV
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="?id=<?php echo $diaryId; ?>&export_json=1">
                                <i class="bi bi-filetype-json text-warning"></i> Экспорт в JSON
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <h1 class="mb-2"><?php echo htmlspecialchars($diary['name']); ?></h1>
            <p class="mb-1">
                <i class="bi bi-person-circle"></i> 
                <?php echo htmlspecialchars($diary['student_last_name'] . ' ' . $diary['student_first_name'] . ' ' . ($diary['student_middle_name'] ?? '')); ?>
                <?php if ($diary['student_class']): ?>
                    <span class="badge bg-light text-dark ms-2"><?php echo htmlspecialchars($diary['student_class']); ?> класс</span>
                <?php endif; ?>
            </p>
            <?php if ($diary['category_name']): ?>
                <span class="badge" style="background: <?php echo $diary['category_color'] ?? '#808080'; ?>; color: white;">
                    <i class="bi bi-tag"></i> <?php echo htmlspecialchars($diary['category_name']); ?>
                </span>
            <?php endif; ?>
            <?php if (!empty($diary['description'])): ?>
                <div class="mt-3">
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($diary['description'])); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Статистика -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['total_lessons'] ?? 0; ?></div>
                    <div class="stats-label">Всего занятий</div>
                    <small class="text-muted">
                        Проведено: <?php echo $stats['completed_lessons'] ?? 0; ?> | 
                        Отменено: <?php echo $stats['cancelled_lessons'] ?? 0; ?>
                    </small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo number_format($stats['paid_sum'] ?? 0, 0, ',', ' '); ?> ₽</div>
                    <div class="stats-label">Оплачено</div>
                    <small class="text-muted">
                        Долг: <?php echo number_format(($stats['total_sum'] ?? 0) - ($stats['paid_sum'] ?? 0), 0, ',', ' '); ?> ₽
                    </small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['unpaid_lessons'] ?? 0; ?></div>
                    <div class="stats-label">Не оплачено занятий</div>
                    <?php if (($stats['unpaid_lessons'] ?? 0) > 0): ?>
                        <span class="badge-unpaid">Требуется оплата</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $totalTimeFormatted ?: '0 мин'; ?></div>
                    <div class="stats-label">Общее время</div>
                </div>
            </div>
        </div>
        
        <!-- Дополнительная статистика -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="stats-card">
                    <h6 class="mb-3"><i class="bi bi-graph-up"></i> Успеваемость</h6>
                    <div class="row">
                        <div class="col-6">
                            <div class="text-center">
                                <div class="stats-number" style="font-size: 1.8em;"><?php echo round($stats['avg_grade'] ?? 0, 1); ?></div>
                                <div class="stats-label">Ср. оценка</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center">
                                <div class="stats-number" style="font-size: 1.8em;"><?php echo round($stats['avg_homework'] ?? 0, 1); ?></div>
                                <div class="stats-label">Ср. оценка ДЗ</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stats-card">
                    <h6 class="mb-3"><i class="bi bi-calendar-week"></i> Период</h6>
                    <div class="row">
                        <div class="col-6">
                            <div class="text-center">
                                <div class="stats-number" style="font-size: 1.2em;"><?php echo $diary['student_start_date'] ? date('d.m.Y', strtotime($diary['student_start_date'])) : '—'; ?></div>
                                <div class="stats-label">Начало</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center">
                                <div class="stats-number" style="font-size: 1.2em;"><?php echo $diary['student_end_date'] ? date('d.m.Y', strtotime($diary['student_end_date'])) : '—'; ?></div>
                                <div class="stats-label">Окончание</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Статистика по темам -->
        <div class="info-section">
            <div class="info-title">
                <i class="bi bi-book"></i> Статистика по темам
            </div>
            
            <?php if (empty($groupedTopics)): ?>
                <p class="text-muted text-center py-3">Нет данных по изученным темам</p>
            <?php else: ?>
                <?php foreach ($groupedTopics as $category => $data): ?>
                    <div class="topic-category">
                        <div class="topic-category-header" style="border-left-color: <?php echo $data['color']; ?>;">
                            <span class="fw-bold"><?php echo htmlspecialchars($category); ?></span>
                            <span class="badge bg-secondary"><?php echo count($data['topics']); ?> тем</span>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover topic-table">
                                <thead>
                                    <tr>
                                        <th>Тема</th>
                                        <th class="text-center">Всего</th>
                                        <th class="text-center">В занятиях</th>
                                        <th class="text-center">Мин</th>
                                        <th class="text-center">Макс</th>
                                        <th class="text-center">Средний</th>
                                        <th class="text-center">Оценок</th>
                                        <th>Последнее</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data['topics'] as $topic): 
                                        $avgGradeClass = '';
                                        if ($topic['avg_grade'] >= 4) $avgGradeClass = 'grade-high';
                                        elseif ($topic['avg_grade'] >= 3) $avgGradeClass = 'grade-medium';
                                        elseif ($topic['avg_grade'] > 0) $avgGradeClass = 'grade-low';
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($topic['topic_name']); ?></td>
                                            <td class="text-center"><?php echo $topic['total_occurrences']; ?></td>
                                            <td class="text-center"><?php echo $topic['lessons_count']; ?></td>
                                            <td class="text-center grade-cell <?php echo $topic['min_grade'] ? ($topic['min_grade'] >= 4 ? 'grade-high' : ($topic['min_grade'] >= 3 ? 'grade-medium' : 'grade-low')) : ''; ?>">
                                                <?php echo $topic['min_grade'] ?? '—'; ?>
                                            </td>
                                            <td class="text-center grade-cell <?php echo $topic['max_grade'] ? ($topic['max_grade'] >= 4 ? 'grade-high' : ($topic['max_grade'] >= 3 ? 'grade-medium' : 'grade-low')) : ''; ?>">
                                                <?php echo $topic['max_grade'] ?? '—'; ?>
                                            </td>
                                            <td class="text-center grade-cell <?php echo $avgGradeClass; ?>">
                                                <?php echo $topic['avg_grade'] ? round($topic['avg_grade'], 1) : '—'; ?>
                                            </td>
                                            <td class="text-center"><?php echo $topic['graded_count']; ?></td>
                                            <td><?php echo date('d.m.Y', strtotime($topic['last_used'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Информация об ученике -->
        <div class="info-section">
            <div class="info-title">
                <i class="bi bi-person-vcard"></i> Информация об ученике
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="info-item">
                        <span class="info-label">Телефон:</span>
                        <span class="info-value float-end"><?php echo htmlspecialchars($diary['student_phone'] ?? 'Не указан'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email:</span>
                        <span class="info-value float-end"><?php echo htmlspecialchars($diary['student_email'] ?? 'Не указан'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Город:</span>
                        <span class="info-value float-end"><?php echo htmlspecialchars($diary['student_city'] ?? 'Не указан'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Дата рождения:</span>
                        <span class="info-value float-end"><?php echo $diary['student_birth_date'] ? date('d.m.Y', strtotime($diary['student_birth_date'])) : 'Не указана'; ?></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-item">
                        <span class="info-label">Мессенджеры:</span>
                        <span class="info-value float-end">
                            <?php 
                            $messengers = [];
                            if (!empty($diary['student_messenger1'])) $messengers[] = $diary['student_messenger1'];
                            if (!empty($diary['student_messenger2'])) $messengers[] = $diary['student_messenger2'];
                            if (!empty($diary['student_messenger3'])) $messengers[] = $diary['student_messenger3'];
                            echo htmlspecialchars(implode(', ', $messengers)) ?: 'Не указаны';
                            ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($diary['student_goals'])): ?>
                <div class="mt-3">
                    <span class="info-label">Цели обучения:</span>
                    <div class="p-3 bg-light rounded mt-2">
                        <?php echo nl2br(htmlspecialchars($diary['student_goals'])); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Родители -->
        <?php if (!empty($parents)): ?>
        <div class="info-section">
            <div class="info-title">
                <i class="bi bi-people"></i> Родители
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Родство</th>
                            <th>ФИО</th>
                            <th>Телефон</th>
                            <th>Email</th>
                            <th>Мессенджер</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($parents as $parent): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($parent['relationship'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($parent['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($parent['phone'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($parent['email'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($parent['messenger_contact'] ?? '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Комментарии к ученику -->
        <?php if (!empty($studentComments)): ?>
        <div class="info-section">
            <div class="info-title">
                <i class="bi bi-chat"></i> Последние комментарии
            </div>
            
            <?php foreach ($studentComments as $comment): ?>
                <div class="comment-item">
                    <div class="comment-meta">
                        <span><i class="bi bi-person"></i> <?php echo htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']); ?></span>
                        <span><i class="bi bi-clock"></i> <?php echo date('d.m.Y H:i', strtotime($comment['created_at'])); ?></span>
                    </div>
                    <div class="comment-text">
                        <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Последние занятия -->
        <div class="info-section">
            <div class="info-title">
                <i class="bi bi-calendar-check"></i> Последние занятия
                <a href="lessons.php?diary_id=<?php echo $diaryId; ?>" class="btn btn-sm btn-outline-primary ms-auto">
                    Все занятия
                </a>
            </div>
            
            <?php if (empty($recentLessons)): ?>
                <p class="text-muted text-center py-3">В этом дневнике пока нет занятий</p>
            <?php else: ?>
                <?php foreach ($recentLessons as $lesson): 
                    $statusClass = $lesson['is_completed'] ? 'completed' : ($lesson['is_cancelled'] ? 'cancelled' : 'planned');
                ?>
                    <div class="lesson-card <?php echo $statusClass; ?>">
                        <div class="row">
                            <div class="col-md-2">
                                <div class="fw-bold"><?php echo date('d.m.Y', strtotime($lesson['lesson_date'])); ?></div>
                                <div class="text-primary"><?php echo date('H:i', strtotime($lesson['start_time'])); ?></div>
                            </div>
                            <div class="col-md-3">
                                <?php if (!empty($lesson['topic_names'])): ?>
                                    <small class="text-muted">Темы:</small>
                                    <div><?php echo htmlspecialchars(substr($lesson['topic_names'], 0, 50)) . (strlen($lesson['topic_names']) > 50 ? '...' : ''); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3">
                                <?php if (!empty($lesson['homework_manual'])): ?>
                                    <small class="text-muted">ДЗ:</small>
                                    <div><?php echo htmlspecialchars(substr($lesson['homework_manual'], 0, 50)) . (strlen($lesson['homework_manual']) > 50 ? '...' : ''); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-2">
                                <?php if ($lesson['grade_lesson'] !== null): ?>
                                    <span class="badge bg-primary">Оценка: <?php echo $lesson['grade_lesson']; ?>/5</span>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-2 text-end">
                                <?php if ($lesson['is_paid']): ?>
                                    <span class="badge bg-success">Оплачено</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Не оплачено</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Инициализация тултипов
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    function copyPublicLink() {
        const link = "<?php 
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'];
            $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
            $basePath = rtrim($scriptPath, '/');
            echo $protocol . $host . $basePath . '/public_diary.php?token=' . $diary['public_link']; 
        ?>";
        
        navigator.clipboard.writeText(link).then(() => {
            // Временное уведомление
            const toast = document.createElement('div');
            toast.className = 'position-fixed top-0 end-0 m-3 p-3 bg-success text-white rounded shadow';
            toast.style.zIndex = '9999';
            toast.innerHTML = '<i class="bi bi-check-circle"></i> Ссылка скопирована';
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 2000);
        }).catch(err => {
            console.error('Ошибка копирования:', err);
        });
    }
    </script>
</body>
</html>