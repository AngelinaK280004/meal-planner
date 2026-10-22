<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/meal-planner/api/';
$pos = strpos($requestUri, $basePath);

if ($pos === false) {
    json_error('API endpoint not found', 404);
}

$path = substr($requestUri, $pos + strlen($basePath));
$path = strtok($path, '?');
$method = $_SERVER['REQUEST_METHOD'];

$segments = array_values(array_filter(explode('/', $path)));
$resource = $segments[0] ?? '';
$id = $segments[1] ?? null;
$action = $segments[1] ?? null;
if (isset($segments[2])) { $id = $segments[2]; $action = $segments[1]; }

switch ($resource) {
    case 'auth': require __DIR__ . '/auth.php'; break;
    case 'user': require __DIR__ . '/user.php'; break;
    case 'recipes': require __DIR__ . '/recipes.php'; break;
    case 'favorites': require __DIR__ . '/favorites.php'; break;
    case 'menu': require __DIR__ . '/menu.php'; break;
    case 'shopping-list': require __DIR__ . '/shopping.php'; break;
    case 'dashboard': require __DIR__ . '/dashboard.php'; break;
    case 'admin': require __DIR__ . '/admin.php'; break;
    case 'ingredients':require __DIR__ . '/ingredients.php';break;
    default: json_error('Ресурс не найден', 404);
}
?>