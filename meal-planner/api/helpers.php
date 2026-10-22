<?php

//Отправка JSON-ответа
function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

//Отправка JSON-ошибки
 
function json_error($message, $code = 400) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

//Проверка авторизации
function check_auth() {
    if (!isset($_SESSION['user_id'])) {
        json_error('Требуется авторизация', 401);
    }
}

//Получение ID текущего пользователя
function get_current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

//Проверка обязательных полей
function validate_required($fields, $data) {
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            json_error("Поле '$field' обязательно для заполнения", 422);
        }
    }
}

function log_error($message, $context = []) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logEntry = date('Y-m-d H:i:s') . ' - ' . $message;
    if (!empty($context)) {
        $logEntry .= ' - ' . json_encode($context);
    }
    error_log($logEntry . PHP_EOL, 3, $logDir . '/error.log');
}
?>