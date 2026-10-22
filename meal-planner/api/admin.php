<?php
check_auth();
$userId = get_current_user_id();
$stmt = $pdo->prepare("SELECT is_admin FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$isAdmin = $stmt->fetchColumn();
if (!$isAdmin) json_error('Доступ запрещён. Требуются права администратора.', 403);

if ($method === 'POST' && $resource === 'recipes') {
    $data = json_decode(file_get_contents('php://input'), true);
    validate_required(['name', 'category_id', 'cooking_time', 'difficulty', 'instructions', 'calories_per_serving', 'protein_per_serving', 'fat_per_serving', 'carbs_per_serving'], $data);
    
    $cal = (float)$data['calories_per_serving'];
    $prot = (float)$data['protein_per_serving'];
    $fat = (float)$data['fat_per_serving'];
    $carbs = (float)$data['carbs_per_serving'];
    
    $calculatedCal = $prot * 4 + $fat * 9 + $carbs * 4;
    $diff = abs($cal - $calculatedCal) / max($cal, 0.01);
    if ($diff > 0.05) json_error('Несоответствие КБЖУ: калории должны равняться 4×белки + 9×жиры + 4×углеводы (±5%)', 422);
    
    $isDrink = isset($data['is_drink']) ? (int)$data['is_drink'] : 0;
    $cuisine = $data['cuisine'] ?? 'any';
    
    $stmt = $pdo->prepare("
        INSERT INTO recipes (name, category_id, cooking_time, difficulty, description,
                           instructions, image_url, calories_per_serving,
                           protein_per_serving, fat_per_serving, carbs_per_serving,
                           is_drink, cuisine)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['name'], $data['category_id'], $data['cooking_time'], $data['difficulty'],
        $data['description'] ?? '', $data['instructions'], $data['image_url'] ?? '',
        $cal, $prot, $fat, $carbs, $isDrink, $cuisine
    ]);
    
    $recipeId = $pdo->lastInsertId();
    
    if (isset($data['ingredients']) && is_array($data['ingredients'])) {
        foreach ($data['ingredients'] as $ing) {
            $pdo->prepare("INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit, notes) VALUES (?, ?, ?, ?, ?)")->execute([$recipeId, $ing['ingredient_id'], $ing['quantity'], $ing['unit'], $ing['notes'] ?? '']);
        }
    }
    
    json_response(['message' => 'Рецепт успешно создан', 'recipe_id' => $recipeId], 201);
}

if ($method === 'GET' && $resource === 'users') {
    $stmt = $pdo->query("SELECT user_id, email, name, registration_date, last_login, is_active, is_admin FROM users ORDER BY user_id");
    json_response($stmt->fetchAll());
}

json_error('Метод не поддерживается', 405);
?>