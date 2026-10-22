<?php
check_auth();
$userId = get_current_user_id();

if ($method === 'GET' && !$id) {
    $stmt = $pdo->prepare("
        SELECT sli.list_item_id, sli.ingredient_id, i.name, sli.quantity_required, sli.unit,
               sli.is_purchased, i.category
        FROM shopping_list_items sli
        JOIN shopping_lists sl ON sli.list_id = sl.list_id
        JOIN ingredients i ON sli.ingredient_id = i.ingredient_id
        WHERE sl.user_id = ? AND sl.status = 'active'
        ORDER BY i.category, i.name
    ");
    $stmt->execute([$userId]);
    $items = $stmt->fetchAll();
    
    $grouped = [];
    foreach ($items as $item) {
        $cat = $item['category'] ?: 'Прочее';
        if (!isset($grouped[$cat])) $grouped[$cat] = [];
        $grouped[$cat][] = $item;
    }
    json_response(['groups' => $grouped]);
}

if ($method === 'PUT' && is_numeric($id) && $action === 'purchase') {
    $stmt = $pdo->prepare("
        SELECT sli.ingredient_id, sli.quantity_required, sli.unit
        FROM shopping_list_items sli
        JOIN shopping_lists sl ON sli.list_id = sl.list_id
        WHERE sli.list_item_id = ? AND sl.user_id = ?
    ");
    $stmt->execute([$id, $userId]);
    $item = $stmt->fetch();
    if (!$item) json_error('Позиция не найдена', 404);
    
    $pdo->prepare("UPDATE shopping_list_items SET is_purchased = TRUE WHERE list_item_id = ?")->execute([$id]);
    $pdo->prepare("INSERT INTO product_stock (user_id, ingredient_id, quantity, unit) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)")->execute([$userId, $item['ingredient_id'], $item['quantity_required'], $item['unit']]);
    json_response(['message' => 'Продукт отмечен купленным']);
}

if ($method === 'POST' && $id === 'generate') {
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $weekEnd = date('Y-m-d', strtotime('sunday this week'));
    
    $stmt = $pdo->prepare("SELECT menu_id FROM menus WHERE user_id = ? AND period_start = ? AND period_end = ? AND status = 'draft' ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$userId, $weekStart, $weekEnd]);
    $menu = $stmt->fetch();
    if (!$menu) json_error('Нет активного меню', 400);
    $menuId = $menu['menu_id'];
    
    $stmt = $pdo->prepare("
        SELECT ri.ingredient_id, SUM(ri.quantity * mr.serving_size) as total_needed, ri.unit
        FROM menu_recipes mr
        JOIN recipe_ingredients ri ON mr.recipe_id = ri.recipe_id
        WHERE mr.menu_id = ?
        GROUP BY ri.ingredient_id, ri.unit
    ");
    $stmt->execute([$menuId]);
    $needed = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("SELECT ingredient_id, quantity FROM product_stock WHERE user_id = ?");
    $stmt->execute([$userId]);
    $stocks = [];
    while ($row = $stmt->fetch()) $stocks[$row['ingredient_id']] = $row['quantity'];
    
    $pdo->prepare("INSERT INTO shopping_lists (user_id, menu_id, status) VALUES (?, ?, 'active')")->execute([$userId, $menuId]);
    $listId = $pdo->lastInsertId();
    
    $inserted = 0;
    foreach ($needed as $ing) {
        $have = $stocks[$ing['ingredient_id']] ?? 0;
        $required = max(0, $ing['total_needed'] - $have);
        if ($required > 0.001) {
            $pdo->prepare("INSERT INTO shopping_list_items (list_id, ingredient_id, quantity_required, unit) VALUES (?, ?, ?, ?)")->execute([$listId, $ing['ingredient_id'], $required, $ing['unit']]);
            $inserted++;
        }
    }
    json_response(['message' => "Список покупок создан ($inserted позиций)", 'list_id' => $listId]);
}

if ($method === 'DELETE' && $id === 'clear') {
    $pdo->prepare("DELETE sli FROM shopping_list_items sli JOIN shopping_lists sl ON sli.list_id = sl.list_id WHERE sl.user_id = ? AND sl.status = 'active'")->execute([$userId]);
    json_response(['message' => 'Список покупок очищен']);
}

json_error('Метод не поддерживается', 405);
?>