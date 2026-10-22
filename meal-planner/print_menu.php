<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /meal-planner/');
    exit;
}
require_once 'api/config.php';

$userId = $_SESSION['user_id'];
$period_start = $_GET['start'] ?? date('Y-m-d', strtotime('monday this week'));
$period_end   = $_GET['end']   ?? date('Y-m-d', strtotime('sunday this week'));

// Получаем целевые КБЖУ и цель пользователя
$stmt = $pdo->prepare("
    SELECT daily_calories_goal, goal_type 
    FROM anthropometry 
    WHERE user_id = ? 
    ORDER BY record_date DESC LIMIT 1
");
$stmt->execute([$userId]);
$target = $stmt->fetch();
$targetCalories = $target['daily_calories_goal'] ?? 2000;
$goalRaw = $target['goal_type'] ?? 'maintenance';

$goalName = match($goalRaw) {
    'weight_loss' => 'Похудение',
    'muscle_gain' => 'Набор массы',
    'maintenance' => 'Поддержание веса',
    default => 'Не указано'
};

// Получаем настройки пользователя
$stmt = $pdo->prepare("
    SELECT meals_per_day, 
           dishes_breakfast, drinks_breakfast,
           dishes_lunch, drinks_lunch,
           dishes_dinner, drinks_dinner,
           dishes_snack, drinks_snack
    FROM user_preferences_core 
    WHERE user_id = ?
");
$stmt->execute([$userId]);
$prefs = $stmt->fetch();
$mealsPerDay = $prefs['meals_per_day'] ?? 3;

$mealTypesOrder = ['breakfast', 'lunch', 'dinner', 'snack'];
$usedMealTypes = array_slice($mealTypesOrder, 0, $mealsPerDay);
$mealTypeNames = [
    'breakfast' => 'Завтрак',
    'lunch'     => 'Обед',
    'dinner'    => 'Ужин',
    'snack'     => 'Перекус'
];

// Получаем меню за период 
$stmt = $pdo->prepare("
    SELECT mr.day_number, mr.meal_type, mr.serving_size,
           r.name, r.calories_per_serving, r.is_drink
    FROM menu_recipes mr
    JOIN menus m ON mr.menu_id = m.menu_id
    JOIN recipes r ON mr.recipe_id = r.recipe_id
    WHERE m.user_id = ? AND m.status = 'draft'
      AND m.period_start = ? AND m.period_end = ?
    ORDER BY mr.day_number, FIELD(mr.meal_type, 'breakfast','lunch','dinner','snack')
");
$stmt->execute([$userId, $period_start, $period_end]);
$rows = $stmt->fetchAll();

// Группируем по дням и типам приёма пищи
$menuByDay = [];
$dayCalories = [];
foreach ($rows as $row) {
    $day = $row['day_number'];
    $meal = $row['meal_type'];
    if (!isset($menuByDay[$day][$meal])) {
        $menuByDay[$day][$meal] = [];
    }
    $menuByDay[$day][$meal][] = $row;
    $dayCalories[$day] = ($dayCalories[$day] ?? 0) + $row['calories_per_serving'] * $row['serving_size'];
}

$daysCount = (strtotime($period_end) - strtotime($period_start)) / 86400 + 1;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Меню - Гармония вкуса</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .logo { font-size: 24px; font-weight: bold; color: #2c7a4d; }
        .period { color: #666; margin-top: 5px; }
        .goal-info { margin: 10px 0; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
        th { background: #f5f5f5; }
        .meal-title { font-weight: bold; background: #e8f5e9; width: 100px; }
        .sub-item { margin-left: 10px; font-size: 0.85em; color: #555; }
        .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #999; }
        @media print { body { margin: 0; } .no-print { display: none; } }
        button { margin-top: 20px; padding: 10px 20px; background: #2c7a4d; color: white; border: none; border-radius: 8px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo"> Гармония вкуса</div>
        <div class="period">Меню на период с <?= date('d.m.Y', strtotime($period_start)) ?> по <?= date('d.m.Y', strtotime($period_end)) ?></div>
        <div class="goal-info">Цель: <?= htmlspecialchars($goalName) ?> | Целевая калорийность: <?= (int)$targetCalories ?> ккал/день</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>День</th>
                <?php foreach ($usedMealTypes as $mt): ?>
                    <th><?= $mealTypeNames[$mt] ?></th>
                <?php endforeach; ?>
                <th>Итого ккал</th>
            </tr>
        </thead>
        <tbody>
            <?php for ($day = 1; $day <= $daysCount; $day++): 
                $date = date('d.m.Y', strtotime($period_start) + ($day-1)*86400);
                $weekdayNum = (int)date('N', strtotime($period_start) + ($day-1)*86400); // приведение к int
                $weekdayName = match($weekdayNum) {
                    1 => 'ПН', 2 => 'ВТ', 3 => 'СР', 4 => 'ЧТ', 5 => 'ПТ', 6 => 'СБ', 7 => 'ВС',
                    default => ''
                };
            ?>
                <tr>
                    <td class="meal-title">
                        <?= $weekdayName ?><br>
                        <small><?= $date ?></small>
                    </td>
                    <?php foreach ($usedMealTypes as $mt): ?>
                        <td>
                            <?php if (isset($menuByDay[$day][$mt])): ?>
                                <?php foreach ($menuByDay[$day][$mt] as $item): ?>
                                    <div>
                                        <strong><?= htmlspecialchars($item['name']) ?></strong>
                                        <?php if ($item['serving_size'] > 1): ?> (x<?= $item['serving_size'] ?>)<?php endif; ?>
                                        <br><span class="sub-item"><?= $item['calories_per_serving'] * $item['serving_size'] ?> ккал</span>
                                        <?php if ($item['is_drink']): ?>
                                            <span class="sub-item"> напиток</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="sub-item">—</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                    <td class="fw-bold"><?= $dayCalories[$day] ?? 0 ?> ккал</td>
                </tr>
            <?php endfor; ?>
        </tbody>
    </table>

    <div class="footer">
        Гармония вкуса — ваш персональный планировщик питания
    </div>
    <div class="no-print" style="text-align: center;">
        <button onclick="window.print()"> Печать</button>
        <button onclick="window.close()">Закрыть</button>
    </div>
</body>
</html>