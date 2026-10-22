<?php

// Регистрация
if ($method === 'POST' && $id === 'register') {
    $data = json_decode(file_get_contents('php://input'), true);
    validate_required(['email', 'password', 'name'], $data);
    
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) json_error('Пользователь с таким email уже существует', 409);
    
    $hash = password_hash($data['password'], PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, name) VALUES (?, ?, ?)");
    $stmt->execute([$data['email'], $hash, $data['name']]);
    
    $_SESSION['user_id'] = $pdo->lastInsertId();
    json_response(['message' => 'Регистрация успешна', 'user_id' => $_SESSION['user_id']], 201);
}

// Вход
if ($method === 'POST' && $id === 'login') {
    $data = json_decode(file_get_contents('php://input'), true);
    validate_required(['email', 'password'], $data);
    
    $stmt = $pdo->prepare("SELECT user_id, password_hash, name, is_admin FROM users WHERE email = ? AND is_active = TRUE");
    $stmt->execute([$data['email']]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($data['password'], $user['password_hash'])) {
        json_error('Неверный email или пароль', 401);
    }
    
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['is_admin'] = $user['is_admin'];
    
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    $stmt->execute([$user['user_id']]);
    
    json_response(['message' => 'Вход выполнен успешно', 'user' => ['id' => $user['user_id'], 'name' => $user['name'], 'is_admin' => $user['is_admin']]]);
}

// Выход
if ($method === 'POST' && $id === 'logout') {
    session_destroy();
    json_response(['message' => 'Вы вышли из системы']);
}

// Проверка сессии
if ($method === 'GET' && $id === 'check') {
    if (isset($_SESSION['user_id'])) {
        json_response(['authenticated' => true, 'user_id' => $_SESSION['user_id'], 'is_admin' => $_SESSION['is_admin'] ?? false]);
    } else {
        json_response(['authenticated' => false]);
    }
}

json_error('Метод не поддерживается', 405);
?>