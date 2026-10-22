<?php session_start(); ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Гармония вкуса | Планирование питания</title>
    <!-- Bootstrap 5 + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <base href="/meal-planner/">
</head>
<body>

<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
        <a class="navbar-brand" href="/meal-planner/dashboard">
            <!-- Новый логотип: иконка + название -->
            <div class="logo-wrapper">
           <svg width="32" height="32" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <linearGradient id="logoGrad1" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" style="stop-color:#2c7a4d"/>
            <stop offset="100%" style="stop-color:#00cec9"/>
        </linearGradient>
    </defs>
    <circle cx="50" cy="50" r="48" fill="url(#logoGrad1)"/>
    <path d="M50 2 A48 48 0 0 1 50 98 A24 24 0 0 1 50 50 A24 24 0 0 0 50 2 Z" fill="#ffffff"/>
    <circle cx="50" cy="26" r="8" fill="url(#logoGrad1)"/>
    <circle cx="50" cy="74" r="8" fill="#ffffff"/>
    <path d="M32 68 Q30 60 38 58 Q36 64 32 68" fill="#e74c3c"/>
    <path d="M68 32 Q70 40 62 42 Q64 36 68 32" fill="#f39c12"/>
</svg>
                <span style="font-weight: 700; font-size: 1.4rem;">Гармония вкуса</span>
            </div>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0" id="mainNav">
                <li class="nav-item"><a class="nav-link" href="/meal-planner/dashboard"><i class="bi bi-grid-3x3-gap-fill"></i> Главная</a></li>
                <li class="nav-item"><a class="nav-link" href="/meal-planner/recipes"><i class="bi bi-book"></i> Рецепты</a></li>
                <li class="nav-item auth-only"><a class="nav-link" href="/meal-planner/menu"><i class="bi bi-calendar-week"></i> Моё меню</a></li>
                <li class="nav-item auth-only"><a class="nav-link" href="/meal-planner/products"><i class="bi bi-box-seam"></i> Продукты</a></li>
                <li class="nav-item auth-only"><a class="nav-link" href="/meal-planner/shopping"><i class="bi bi-cart3"></i> Покупки</a></li>
                <li class="nav-item auth-only"><a class="nav-link" href="/meal-planner/profile"><i class="bi bi-person-circle"></i> Профиль</a></li>
            </ul>
            <div id="authButtons">
                <button class="btn btn-outline-primary btn-sm" id="loginBtn" data-bs-toggle="modal" data-bs-target="#authModal">Войти</button>
                <button class="btn btn-primary btn-sm ms-2 d-none" id="logoutBtn"><i class="bi bi-box-arrow-right"></i> Выйти</button>
            </div>
        </div>
    </div>
</nav>

<main id="app" class="container py-5 mt-4"></main>

<footer class="text-center py-3 text-secondary border-top mt-5">
    <p class="mb-0">© 2026 Гармония вкуса </p>
</footer>

<!-- Модальное окно авторизации -->
<div class="modal fade" id="authModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="authModalTitle">Вход</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="authForm" novalidate>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" id="authEmail" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Пароль</label>
                        <input type="password" class="form-control" id="authPassword" required minlength="6">
                    </div>
                    <div class="mb-3 d-none" id="nameGroup">
                        <label class="form-label">Имя</label>
                        <input type="text" class="form-control" id="authName">
                    </div>
                    <div id="authError" class="alert alert-danger d-none"></div>
                    <button type="submit" class="btn btn-primary w-100" id="authSubmit">Войти</button>
                    <button type="button" class="btn btn-link w-100 mt-2" id="switchMode">Нет аккаунта? Зарегистрироваться</button>
                </form>
                <hr>
                <button class="btn btn-outline-secondary w-100" id="demoLoginBtn">Демо-доступ</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script type="module" src="js/app.js"></script>
</body>
</html>