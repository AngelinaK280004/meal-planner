<?php
check_auth();
$userId = get_current_user_id();

// Получение списка избранного
if ($method === 'GET') {
    $stmt = $pdo->prepare("
        SELECT r.recipe_id, r.name, r.calories_per_serving, r.cooking_time, r.difficulty, r.image_url
        FROM user_favorites f
        JOIN recipes r ON f.recipe_id = r.recipe_id
        WHERE f.user_id = ?
        ORDER BY r.name
    ");
    $stmt->execute([$userId]);
    json_response($stmt->fetchAll());
}

//Удаление из избранного
if ($method === 'DELETE' && !empty($action) && $action === 'remove' && is_numeric($id)) {
    $stmt = $pdo->prepare("DELETE FROM user_favorites WHERE user_id = ? AND recipe_id = ?");
    $stmt->execute([$userId, $id]);
    json_response(['message' => 'Рецепт удалён из избранного']);
}

// Добавление в избранное 
if ($method === 'POST') {
    $recipeId = null;
    
   
    if (is_numeric($id)) {
        $recipeId = (int)$id;
    } elseif (!empty($action) && $action === 'add' && is_numeric($id)) {
        $recipeId = (int)$id;
    } else {
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['recipe_id'])) {
            $recipeId = (int)$data['recipe_id'];
        } elseif (isset($data['id'])) { 
            $recipeId = (int)$data['id'];
        }
    }
    
    if (!$recipeId) {
        json_error('Не указан ID рецепта (передайте recipe_id в теле запроса или в URL)', 422);
    }
    
    // Проверяем существование рецепта
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM recipes WHERE recipe_id = ?");
    $stmt->execute([$recipeId]);
    if (!$stmt->fetchColumn()) {
        json_error('Рецепт не найден', 404);
    }
    
    // Добавляем 
    $stmt = $pdo->prepare("INSERT IGNORE INTO user_favorites (user_id, recipe_id) VALUES (?, ?)");
    $stmt->execute([$userId, $recipeId]);
    
    $message = ($stmt->rowCount() > 0) ? 'Рецепт добавлен в избранное' : 'Рецепт уже в избранном';
    json_response(['message' => $message]);
}

json_error('Метод не поддерживается', 405);
?>