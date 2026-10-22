<?php
$userId = get_current_user_id();

// Получение списка рецептов
if ($method === 'GET' && !$id) {
    $search = $_GET['search'] ?? '';
    $category = $_GET['category'] ?? '';
    $calMin = (int)($_GET['cal_min'] ?? 0);
    $calMax = (int)($_GET['cal_max'] ?? 9999);
    $timeMax = (int)($_GET['time_max'] ?? 999);
    $difficulty = $_GET['difficulty'] ?? '';
    $isDrink = isset($_GET['is_drink']) ? (int)$_GET['is_drink'] : null;
    
    $sql = "SELECT r.recipe_id, r.name, r.cooking_time, r.difficulty, r.image_url,
                   r.calories_per_serving, r.protein_per_serving, r.carbs_per_serving, r.fat_per_serving,
                   r.is_drink, rc.name as category_name
            FROM recipes r
            LEFT JOIN recipe_categories rc ON r.category_id = rc.category_id
            WHERE 1=1";
    $params = [];
    
    if ($search !== '') { 
        $searchEscaped = addcslashes($search, '%_');
        $sql .= " AND r.name LIKE ?"; 
        $params[] = "%$searchEscaped%"; 
    }
    if ($category !== '' && is_numeric($category)) { 
        $sql .= " AND r.category_id = ?"; 
        $params[] = (int)$category; 
    }
    if ($calMin > 0) { $sql .= " AND r.calories_per_serving >= ?"; $params[] = $calMin; }
    if ($calMax < 9999) { $sql .= " AND r.calories_per_serving <= ?"; $params[] = $calMax; }
    if ($timeMax < 999) { $sql .= " AND r.cooking_time <= ?"; $params[] = $timeMax; }
    if ($difficulty && in_array($difficulty, [1,2,3])) { $sql .= " AND r.difficulty = ?"; $params[] = (int)$difficulty; }
    if ($isDrink !== null) { $sql .= " AND r.is_drink = ?"; $params[] = $isDrink; }
    
    $sql .= " ORDER BY r.name LIMIT 50";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $recipes = $stmt->fetchAll();
    
    if ($userId) {
        $favStmt = $pdo->prepare("SELECT recipe_id FROM user_favorites WHERE user_id = ?");
        $favStmt->execute([$userId]);
        $favorites = $favStmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($recipes as &$recipe) {
            $recipe['is_favorite'] = in_array($recipe['recipe_id'], $favorites);
        }
    }
    json_response($recipes);
}

// Получение деталей рецепта
if ($method === 'GET' && is_numeric($id)) {
    $stmt = $pdo->prepare("
        SELECT r.*, rc.name as category_name
        FROM recipes r
        LEFT JOIN recipe_categories rc ON r.category_id = rc.category_id
        WHERE r.recipe_id = ?
    ");
    $stmt->execute([$id]);
    $recipe = $stmt->fetch();
    if (!$recipe) json_error('Рецепт не найден', 404);
    
    $stmt = $pdo->prepare("
        SELECT ri.quantity, ri.unit, ri.notes, i.name, i.ingredient_id
        FROM recipe_ingredients ri
        JOIN ingredients i ON ri.ingredient_id = i.ingredient_id
        WHERE ri.recipe_id = ?
    ");
    $stmt->execute([$id]);
    $recipe['ingredients'] = $stmt->fetchAll();
    
    if ($userId) {
        $stmt = $pdo->prepare("SELECT 1 FROM user_favorites WHERE user_id = ? AND recipe_id = ?");
        $stmt->execute([$userId, $id]);
        $recipe['is_favorite'] = (bool)$stmt->fetch();
    }
    json_response($recipe);
}

// Получение категорий
if ($method === 'GET' && $id === 'categories') {
    $stmt = $pdo->query("SELECT category_id, name FROM recipe_categories ORDER BY name");
    json_response($stmt->fetchAll());
}

// Гибридные рекомендации 
if ($method === 'GET' && $id === 'recommendations') {
    if (!$userId) json_error('Требуется авторизация', 401);
    
    // Получаем предпочтения пользователя
    $stmt = $pdo->prepare("SELECT max_difficulty, preferred_cuisine, dishes_per_meal, drinks_per_meal FROM user_preferences_core WHERE user_id = ?");
    $stmt->execute([$userId]);
    $prefs = $stmt->fetch();
    $maxDiff = $prefs['max_difficulty'] ?? 2;
    $prefCuisine = $prefs['preferred_cuisine'] ?? 'any';
    
    // Загружаем оценки всех пользователей для коллаборативной фильтрации
    $ratingsStmt = $pdo->query("SELECT user_id, recipe_id, rating FROM recipe_ratings");
    $ratings = $ratingsStmt->fetchAll();
    
    // Вычисляем среднюю оценку для каждого рецепта
    $avgRatings = [];
    $userRatings = [];
    foreach ($ratings as $r) {
        if (!isset($avgRatings[$r['recipe_id']])) {
            $avgRatings[$r['recipe_id']] = ['sum' => 0, 'count' => 0];
        }
        $avgRatings[$r['recipe_id']]['sum'] += $r['rating'];
        $avgRatings[$r['recipe_id']]['count']++;
        
        if ($r['user_id'] == $userId) {
            $userRatings[$r['recipe_id']] = $r['rating'];
        }
    }
    $collabScores = [];
    foreach ($avgRatings as $rid => $data) {
        $collabScores[$rid] = $data['sum'] / $data['count'];
    }
    
    // Контентная фильтрация
    $stmt = $pdo->prepare("
        SELECT recipe_id, name, calories_per_serving, cooking_time, difficulty, cuisine
        FROM recipes WHERE is_drink = 0 AND difficulty <= ?
    ");
    $stmt->execute([$maxDiff]);
    $candidates = $stmt->fetchAll();
    
    $recommendations = [];
    foreach ($candidates as $rec) {
        if (isset($userRatings[$rec['recipe_id']])) continue;
        
        // Контентная оценка
        $contentScore = ($prefCuisine === 'any' || $rec['cuisine'] == $prefCuisine) ? 1.0 : 0.5;
        $collabScore = $collabScores[$rec['recipe_id']] ?? 3.0; // нейтральная оценка
        $hybridScore = 0.6 * $contentScore + 0.4 * ($collabScore / 5);
        $recommendations[] = [
            'recipe_id' => $rec['recipe_id'],
            'name' => $rec['name'],
            'calories_per_serving' => $rec['calories_per_serving'],
            'cooking_time' => $rec['cooking_time'],
            'score' => round($hybridScore, 2),
            'avg_rating' => round($collabScore, 1)
        ];
    }
    usort($recommendations, fn($a, $b) => $b['score'] <=> $a['score']);
    json_response(array_slice($recommendations, 0, 10));
}

// Добавление/обновление оценки рецепта
if ($method === 'POST' && $id === 'rate') {
    check_auth();
    // Повторно получаем userId после check_auth() для надёжности
    $userId = get_current_user_id();
    
    $data = json_decode(file_get_contents('php://input'), true);
    validate_required(['recipe_id', 'rating'], $data);
    $recipeId = (int)$data['recipe_id'];
    $rating = (int)$data['rating'];
    
    if ($rating < 1 || $rating > 5) json_error('Оценка должна быть от 1 до 5', 422);
    
    // Проверяем существование рецепта
    $exists = $pdo->prepare("SELECT 1 FROM recipes WHERE recipe_id = ?");
    $exists->execute([$recipeId]);
    if (!$exists->fetch()) json_error('Рецепт не найден', 404);
    
    $stmt = $pdo->prepare("
        INSERT INTO recipe_ratings (user_id, recipe_id, rating) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE rating = VALUES(rating), updated_at = NOW()
    ");
    $stmt->execute([$userId, $recipeId, $rating]);
    json_response(['message' => 'Оценка сохранена']);
}

// Получение истории оценок пользователя
if ($method === 'GET' && $id === 'ratings') {
    check_auth();
    $userId = get_current_user_id();
    
    $stmt = $pdo->prepare("
        SELECT r.recipe_id, r.name, rr.rating, DATE_FORMAT(rr.updated_at, '%d.%m.%Y') as rated_date
        FROM recipe_ratings rr
        JOIN recipes r ON rr.recipe_id = r.recipe_id
        WHERE rr.user_id = ?
        ORDER BY rr.updated_at DESC
    ");
    $stmt->execute([$userId]);
    json_response($stmt->fetchAll());
}
// Получение альтернативных рецептов для замены
// Оценка рецепта
if ($method === 'POST' && $id === 'rate') {
    check_auth();
    $data = json_decode(file_get_contents('php://input'), true);
    validate_required(['recipe_id', 'rating'], $data);
    $rating = (int)$data['rating'];
    if ($rating < 1 || $rating > 5) json_error('Оценка от 1 до 5', 422);
    $stmt = $pdo->prepare("
        INSERT INTO recipe_ratings (user_id, recipe_id, rating) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE rating = VALUES(rating), updated_at = NOW()
    ");
    $stmt->execute([$userId, $data['recipe_id'], $rating]);
    json_response(['message' => 'Оценка сохранена']);
}

// Получение рекомендаций
if ($method === 'GET' && $id === 'alternatives') {
    $recipeId = (int)($_GET['recipe_id'] ?? 0);
    $calMax = (int)($_GET['cal_max'] ?? 1000);
    $stmt = $pdo->prepare("
        SELECT recipe_id, name, calories_per_serving, cooking_time, difficulty
        FROM recipes
        WHERE recipe_id != ? AND calories_per_serving <= ?
        ORDER BY RAND() LIMIT 5
    ");
    $stmt->execute([$recipeId, $calMax]);
    json_response($stmt->fetchAll());
}
json_error('Метод не поддерживается', 405);