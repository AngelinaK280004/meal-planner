<?php
check_auth();
$userId = get_current_user_id();

// Получение списка предложенных ингредиентов 
if ($method === 'GET' && $id === 'suggested') {
    $stmt = $pdo->prepare("
        SELECT ingredient_id, name, unit
        FROM ingredients
        ORDER BY RAND()
        LIMIT 20
    ");
    $stmt->execute();
    json_response($stmt->fetchAll());
}

// Поиск ингредиентов по названию
if ($method === 'GET' && $id === 'search') {
    $query = $_GET['q'] ?? '';
    if (strlen($query) < 2) json_response([]);
    $stmt = $pdo->prepare("
        SELECT ingredient_id, name, unit
        FROM ingredients
        WHERE name LIKE ?
        LIMIT 10
    ");
    $stmt->execute(["%$query%"]);
    json_response($stmt->fetchAll());
}

json_error('Метод не поддерживается', 405);
?>