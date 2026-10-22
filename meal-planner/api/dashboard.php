<?php
check_auth();
$userId = get_current_user_id();

// Получаем целевые КБЖУ пользователя
$stmt = $pdo->prepare("
    SELECT daily_calories_goal, daily_protein_goal, daily_fat_goal, daily_carbs_goal
    FROM anthropometry
    WHERE user_id = ?
    ORDER BY record_date DESC LIMIT 1
");
$stmt->execute([$userId]);
$goals = $stmt->fetch();
if (!$goals) {
    $goals = [
        'daily_calories_goal' => 2000,
        'daily_protein_goal' => 100,
        'daily_fat_goal' => 66,
        'daily_carbs_goal' => 225
    ];
}

$today = date('Y-m-d');

// Находим активное меню
$stmt = $pdo->prepare("
    SELECT menu_id, period_start, period_end
    FROM menus
    WHERE user_id = ? AND status = 'draft'
      AND period_start <= ? AND period_end >= ?
    ORDER BY created_at DESC LIMIT 1
");
$stmt->execute([$userId, $today, $today]);
$menu = $stmt->fetch();

$todayStats = ['total_cal' => 0.0, 'total_protein' => 0.0, 'total_fat' => 0.0, 'total_carbs' => 0.0];
$mealsStats = ['breakfast' => 0.0, 'lunch' => 0.0, 'dinner' => 0.0, 'snack' => 0.0];
$nextMeal = null;

if ($menu) {
    $menuId = $menu['menu_id'];
    $periodStart = $menu['period_start'];
    
    // Вычисляем номер дня в периоде 
    $dayNumber = (int)((strtotime($today) - strtotime($periodStart)) / 86400) + 1;
    if ($dayNumber < 1) $dayNumber = 1;
    
    // Получаем настройки пользователя
    $stmt = $pdo->prepare("SELECT meals_per_day FROM user_preferences_core WHERE user_id = ?");
    $stmt->execute([$userId]);
    $prefs = $stmt->fetch();
    $mealsPerDay = $prefs['meals_per_day'] ?? 3;
    $mealTypes = ['breakfast', 'lunch', 'dinner', 'snack'];
    $usedMealTypes = array_slice($mealTypes, 0, $mealsPerDay);
    
    // Получаем все рецепты на сегодняшний день
    $stmt = $pdo->prepare("
        SELECT r.calories_per_serving, r.protein_per_serving, r.fat_per_serving, r.carbs_per_serving,
               mr.meal_type, mr.serving_size
        FROM menu_recipes mr
        JOIN recipes r ON mr.recipe_id = r.recipe_id
        WHERE mr.menu_id = ? AND mr.day_number = ?
    ");
    $stmt->execute([$menuId, $dayNumber]);
    $items = $stmt->fetchAll();
    
    foreach ($items as $item) {
        $mult = $item['serving_size'];
        $todayStats['total_cal'] += $item['calories_per_serving'] * $mult;
        $todayStats['total_protein'] += $item['protein_per_serving'] * $mult;
        $todayStats['total_fat'] += $item['fat_per_serving'] * $mult;
        $todayStats['total_carbs'] += $item['carbs_per_serving'] * $mult;
        
        if (in_array($item['meal_type'], $usedMealTypes)) {
            $mealsStats[$item['meal_type']] += $item['calories_per_serving'] * $mult;
        }
    }
    
    // Округляем итоговые значения после суммирования
    $todayStats['total_cal'] = (int)round($todayStats['total_cal']);
    $todayStats['total_protein'] = (int)round($todayStats['total_protein']);
    $todayStats['total_fat'] = (int)round($todayStats['total_fat']);
    $todayStats['total_carbs'] = (int)round($todayStats['total_carbs']);
    foreach ($mealsStats as &$val) {
        $val = (int)round($val);
    }
    unset($val);
    
    // Определяем следующий приём пищи
    $hour = (int)date('H');
    // Приёмы в хронологическом порядке
    $mealOrder = ['breakfast' => 8, 'lunch' => 13, 'snack' => 16, 'dinner' => 19];
    $mealNames = ['breakfast' => 'Завтрак', 'lunch' => 'Обед', 'dinner' => 'Ужин', 'snack' => 'Перекус'];
    foreach ($mealOrder as $meal => $mealHour) {
        if ($mealHour > $hour && in_array($meal, $usedMealTypes)) {
            $nextMeal = $mealNames[$meal];
            break;
        }
    }
    if (!$nextMeal && !empty($usedMealTypes)) {
        $nextMeal = 'Завтрак (завтра)';
    }
}

json_response([
    'goals' => $goals,
    'today_stats' => $todayStats,
    'meals' => $mealsStats,
    'next_meal' => $nextMeal ?? 'Нет запланированных приёмов пищи'
]);