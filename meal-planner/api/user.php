<?php
// api/user.php
check_auth();
$userId = get_current_user_id();

if ($method === 'GET' && $id === 'profile') {
    $stmt = $pdo->prepare("
        SELECT u.email, u.name, u.is_admin,
               a.height_cm, a.weight_kg, a.age, a.gender,
               a.daily_calories_goal, a.daily_protein_goal, a.daily_fat_goal, a.daily_carbs_goal,
               a.activity_level, a.goal_type
        FROM users u
        LEFT JOIN anthropometry a ON u.user_id = a.user_id AND a.record_date = CURDATE()
        WHERE u.user_id = ?
    ");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch();
    if (!$profile) json_error('Профиль не найден', 404);
    json_response($profile);
}

if ($method === 'GET' && $id === 'dietary-options') {
    $stmt = $pdo->query("SELECT option_id, name, mask_value, description FROM dietary_options ORDER BY mask_value");
    json_response($stmt->fetchAll());
}

if ($method === 'GET' && $id === 'preferences') {
    $stmt = $pdo->prepare("
        SELECT max_difficulty, meals_per_day, preferred_cuisine, dietary_mask,
               dishes_breakfast, drinks_breakfast,
               dishes_lunch, drinks_lunch,
               dishes_dinner, drinks_dinner,
               dishes_snack, drinks_snack
        FROM user_preferences_core WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $prefs = $stmt->fetch();
    if (!$prefs) {
        $prefs = [
            'max_difficulty' => 2,
            'meals_per_day' => 3,
            'preferred_cuisine' => 'any',
            'dietary_mask' => 0,
            'dishes_breakfast' => 1, 'drinks_breakfast' => 1,
            'dishes_lunch' => 1, 'drinks_lunch' => 1,
            'dishes_dinner' => 1, 'drinks_dinner' => 1,
            'dishes_snack' => 1, 'drinks_snack' => 1
        ];
    }
    json_response($prefs);
}

if ($method === 'PUT' && $id === 'profile') {
    $data = json_decode(file_get_contents('php://input'), true);
    if ($data === null) json_error('Некорректный JSON', 400);

    validate_required(['height_cm', 'weight_kg', 'age', 'gender', 'activity_level', 'goal_type'], $data);

    $allowed_genders = ['M', 'F'];
    $allowed_activity = ['sedentary', 'light', 'moderate', 'active', 'very_active'];
    $allowed_goals = ['weight_loss', 'muscle_gain', 'maintenance'];

    if (!in_array($data['gender'], $allowed_genders)) json_error('Недопустимое значение gender', 422);
    if (!in_array($data['activity_level'], $allowed_activity)) json_error('Недопустимое значение activity_level', 422);
    if (!in_array($data['goal_type'], $allowed_goals)) json_error('Недопустимое значение goal_type', 422);

    $gender = $data['gender'];
    $weight = (float)$data['weight_kg'];
    $height = (float)$data['height_cm'];
    $age = (int)$data['age'];
    $activity = $data['activity_level'];
    $goal = $data['goal_type'];

    // BMR (Миффлин-Сан-Жеор)
    if ($gender === 'M') {
        $bmr = 10 * $weight + 6.25 * $height - 5 * $age + 5;
    } else {
        $bmr = 10 * $weight + 6.25 * $height - 5 * $age - 161;
    }

    $coeff = [
        'sedentary' => 1.2, 'light' => 1.375, 'moderate' => 1.55,
        'active' => 1.725, 'very_active' => 1.9
    ];
    $tdee = round($bmr * ($coeff[$activity] ?? 1.55));

    if ($goal === 'weight_loss') $targetCalories = round($tdee * 0.85);
    elseif ($goal === 'muscle_gain') $targetCalories = round($tdee * 1.15);
    else $targetCalories = $tdee;

    $protein = round(($targetCalories * 0.25) / 4, 2);
    $fat = round(($targetCalories * 0.30) / 9, 2);
    $carbs = round(($targetCalories * 0.45) / 4, 2);

    // Сохраняем антропометрию
    $stmt = $pdo->prepare("
        INSERT INTO anthropometry (user_id, height_cm, weight_kg, age, gender, bmr_calories,
            daily_calories_goal, daily_protein_goal, daily_fat_goal, daily_carbs_goal,
            activity_level, goal_type, record_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())
        ON DUPLICATE KEY UPDATE
            height_cm = VALUES(height_cm), weight_kg = VALUES(weight_kg), age = VALUES(age),
            gender = VALUES(gender), bmr_calories = VALUES(bmr_calories),
            daily_calories_goal = VALUES(daily_calories_goal),
            daily_protein_goal = VALUES(daily_protein_goal),
            daily_fat_goal = VALUES(daily_fat_goal),
            daily_carbs_goal = VALUES(daily_carbs_goal),
            activity_level = VALUES(activity_level), goal_type = VALUES(goal_type)
    ");
    $stmt->execute([$userId, $height, $weight, $age, $gender, $bmr,
                   $targetCalories, $protein, $fat, $carbs, $activity, $goal]);

    $maxDifficulty = $data['max_difficulty'] ?? 2;
    $mealsPerDay = $data['meals_per_day'] ?? 3;
    $preferredCuisine = $data['preferred_cuisine'] ?? 'any';

    $dishes_breakfast = $data['dishes_breakfast'] ?? 1;
    $drinks_breakfast = $data['drinks_breakfast'] ?? 1;
    $dishes_lunch = $data['dishes_lunch'] ?? 1;
    $drinks_lunch = $data['drinks_lunch'] ?? 1;
    $dishes_dinner = $data['dishes_dinner'] ?? 1;
    $drinks_dinner = $data['drinks_dinner'] ?? 1;
    $dishes_snack = $data['dishes_snack'] ?? 1;
    $drinks_snack = $data['drinks_snack'] ?? 1;

    $dietaryMask = 0;
    if (isset($data['dietary_mask'])) {
        $dietaryMask = (int)$data['dietary_mask'];
    } elseif (isset($data['dietary_options']) && is_array($data['dietary_options'])) {
        $placeholders = implode(',', array_fill(0, count($data['dietary_options']), '?'));
        $stmtOpt = $pdo->prepare("SELECT mask_value FROM dietary_options WHERE name IN ($placeholders)");
        $stmtOpt->execute($data['dietary_options']);
        $masks = $stmtOpt->fetchAll(PDO::FETCH_COLUMN);
        $dietaryMask = array_reduce($masks, fn($c, $m) => $c | $m, 0);
    }

    $stmt = $pdo->prepare("
        INSERT INTO user_preferences_core (
            user_id, max_difficulty, meals_per_day, preferred_cuisine, dietary_mask,
            dishes_breakfast, drinks_breakfast,
            dishes_lunch, drinks_lunch,
            dishes_dinner, drinks_dinner,
            dishes_snack, drinks_snack
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            max_difficulty = VALUES(max_difficulty),
            meals_per_day = VALUES(meals_per_day),
            preferred_cuisine = VALUES(preferred_cuisine),
            dietary_mask = VALUES(dietary_mask),
            dishes_breakfast = VALUES(dishes_breakfast),
            drinks_breakfast = VALUES(drinks_breakfast),
            dishes_lunch = VALUES(dishes_lunch),
            drinks_lunch = VALUES(drinks_lunch),
            dishes_dinner = VALUES(dishes_dinner),
            drinks_dinner = VALUES(drinks_dinner),
            dishes_snack = VALUES(dishes_snack),
            drinks_snack = VALUES(drinks_snack)
    ");
    $stmt->execute([$userId, $maxDifficulty, $mealsPerDay, $preferredCuisine, $dietaryMask,
        $dishes_breakfast, $drinks_breakfast,
        $dishes_lunch, $drinks_lunch,
        $dishes_dinner, $drinks_dinner,
        $dishes_snack, $drinks_snack]);

    json_response([
        'message' => 'Профиль обновлён',
        'daily_calories_goal' => $targetCalories,
        'daily_protein_goal' => $protein,
        'daily_fat_goal' => $fat,
        'daily_carbs_goal' => $carbs
    ]);
}

if ($method === 'GET' && $id === 'allergens') {
    $stmt = $pdo->prepare("SELECT allergen_type FROM user_allergens WHERE user_id = ?");
    $stmt->execute([$userId]);
    json_response($stmt->fetchAll(PDO::FETCH_COLUMN));
}
if ($method === 'POST' && $id === 'allergens') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['value'])) json_error('Не указан аллерген', 422);
    $stmt = $pdo->prepare("INSERT IGNORE INTO user_allergens (user_id, allergen_type) VALUES (?, ?)");
    $stmt->execute([$userId, $data['value']]);
    json_response(['message' => 'Аллерген добавлен']);
}
if ($method === 'DELETE' && strpos($id, 'allergens/') === 0) {
    $value = urldecode(substr($id, 10));
    $stmt = $pdo->prepare("DELETE FROM user_allergens WHERE user_id = ? AND allergen_type = ?");
    $stmt->execute([$userId, $value]);
    json_response(['message' => 'Аллерген удалён']);
}

if ($method === 'GET' && $id === 'diets') {
    $stmt = $pdo->prepare("SELECT restriction_type FROM user_dietary_restrictions WHERE user_id = ?");
    $stmt->execute([$userId]);
    json_response($stmt->fetchAll(PDO::FETCH_COLUMN));
}
if ($method === 'POST' && $id === 'diets') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['value'])) json_error('Не указано ограничение', 422);
    $stmt = $pdo->prepare("INSERT IGNORE INTO user_dietary_restrictions (user_id, restriction_type) VALUES (?, ?)");
    $stmt->execute([$userId, $data['value']]);
    json_response(['message' => 'Ограничение добавлено']);
}
if ($method === 'DELETE' && strpos($id, 'diets/') === 0) {
    $value = urldecode(substr($id, 6));
    $stmt = $pdo->prepare("DELETE FROM user_dietary_restrictions WHERE user_id = ? AND restriction_type = ?");
    $stmt->execute([$userId, $value]);
    json_response(['message' => 'Ограничение удалено']);
}

if ($method === 'GET' && $id === 'excluded') {
    $stmt = $pdo->prepare("SELECT excluded_product_name FROM user_excluded_products WHERE user_id = ?");
    $stmt->execute([$userId]);
    json_response($stmt->fetchAll(PDO::FETCH_COLUMN));
}
if ($method === 'POST' && $id === 'excluded') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['value'])) json_error('Не указан продукт', 422);
    $stmt = $pdo->prepare("INSERT IGNORE INTO user_excluded_products (user_id, excluded_product_name) VALUES (?, ?)");
    $stmt->execute([$userId, $data['value']]);
    json_response(['message' => 'Продукт исключён']);
}
if ($method === 'DELETE' && strpos($id, 'excluded/') === 0) {
    $value = urldecode(substr($id, 9));
    $stmt = $pdo->prepare("DELETE FROM user_excluded_products WHERE user_id = ? AND excluded_product_name = ?");
    $stmt->execute([$userId, $value]);
    json_response(['message' => 'Продукт восстановлен']);
}

if ($method === 'GET' && $id === 'products') {
    $stmt = $pdo->prepare("
        SELECT ps.stock_id, ps.ingredient_id, i.name, ps.quantity, ps.unit, i.calories_per_100g, i.category
        FROM product_stock ps
        JOIN ingredients i ON ps.ingredient_id = i.ingredient_id
        WHERE ps.user_id = ?
        ORDER BY i.category, i.name
    ");
    $stmt->execute([$userId]);
    json_response($stmt->fetchAll());
}
if ($method === 'POST' && $id === 'products') {
    $data = json_decode(file_get_contents('php://input'), true);
    validate_required(['ingredient_id', 'quantity', 'unit'], $data);
    $stmt = $pdo->prepare("
        INSERT INTO product_stock (user_id, ingredient_id, quantity, unit)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
    ");
    $stmt->execute([$userId, $data['ingredient_id'], $data['quantity'], $data['unit']]);
    json_response(['message' => 'Продукт добавлен'], 201);
}
if ($method === 'PUT' && is_numeric($id)) {
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("UPDATE product_stock SET quantity = ?, unit = ? WHERE stock_id = ? AND user_id = ?");
    $stmt->execute([$data['quantity'], $data['unit'], $id, $userId]);
    json_response(['message' => 'Продукт обновлён']);
}
if ($method === 'DELETE' && is_numeric($id)) {
    $stmt = $pdo->prepare("DELETE FROM product_stock WHERE stock_id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    json_response(['message' => 'Продукт удалён']);
}

if ($method === 'GET' && $id === 'counts') {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM product_stock WHERE user_id = ? AND quantity > 0");
    $stmt->execute([$userId]);
    $products = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_favorites WHERE user_id = ?");
    $stmt->execute([$userId]);
    $favorites = $stmt->fetch();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM shopping_list_items sli
        JOIN shopping_lists sl ON sli.list_id = sl.list_id
        WHERE sl.user_id = ? AND sl.status = 'active'
    ");
    $stmt->execute([$userId]);
    $shopping = $stmt->fetch();

    json_response([
        'products' => (int)$products['count'],
        'favorites' => (int)$favorites['count'],
        'shopping' => (int)$shopping['count']
    ]);
}

json_error('Метод не поддерживается', 405);
?>