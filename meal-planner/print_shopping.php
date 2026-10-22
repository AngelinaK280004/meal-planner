<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /meal-planner/');
    exit;
}
require_once 'api/config.php';

$userId = $_SESSION['user_id'];

// Получаем активный список покупок
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
    $grouped[$cat][] = $item;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Список покупок - Гармония вкуса</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .logo { font-size: 24px; font-weight: bold; color: #2c7a4d; }
        .date { color: #666; margin-top: 5px; }
        .category { margin-bottom: 20px; page-break-inside: avoid; }
        .category-title { font-size: 18px; font-weight: bold; border-left: 4px solid #2c7a4d; padding-left: 10px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; }
        .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #999; }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
        button { margin-top: 20px; padding: 10px 20px; background: #2c7a4d; color: white; border: none; border-radius: 8px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo"> Гармония вкуса</div>
        <div class="date">Список покупок от <?= date('d.m.Y') ?></div>
    </div>

    <?php if (empty($grouped)): ?>
        <p>Список покупок пуст.</p>
    <?php else: ?>
        <?php foreach ($grouped as $category => $products): ?>
            <div class="category">
                <div class="category-title"><?= htmlspecialchars($category) ?></div>
                <table>
                    <thead>
                        <tr><th>Продукт</th><th>Количество</th><th>Отметка</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['name']) ?></td>
                                <td><?= $p['quantity_required'] ?> <?= $p['unit'] ?></td>
                                <td><input type="checkbox" <?= $p['is_purchased'] ? 'checked' : '' ?> disabled></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="footer">
        Гармония вкуса — планируйте питание с удовольствием
    </div>
    <div class="no-print" style="text-align: center;">
        <button onclick="window.print()"> Печать</button>
        <button onclick="window.close()">Закрыть</button>
    </div>
</body>
</html>