import { checkAuth, apiRequest, showToast } from './api.js';
import { Dashboard } from './components/dashboard.js';
import { Recipes } from './components/recipes.js';
import { Menu } from './components/menu.js';
import { Products } from './components/products.js';
import { Shopping } from './components/shopping.js';
import { Profile } from './components/profile.js';

window.AppState = { isAuthenticated: false, user: null };

const routes = {
    '/dashboard': Dashboard, '/recipes': Recipes, '/menu': Menu,
    '/products': Products, '/shopping': Shopping, '/profile': Profile
};

async function router() {
    const path = window.location.pathname.replace('/meal-planner', '') || '/dashboard';
    const component = routes[path];
    if (!component) { window.history.replaceState({}, '', '/meal-planner/dashboard'); return Dashboard.render(); }
    if (!AppState.isAuthenticated && path !== '/dashboard' && path !== '/recipes') {
        window.history.replaceState({}, '', '/meal-planner/dashboard');
        return Dashboard.render();
    }
    await component.render();
}

document.addEventListener('click', (e) => {
    const link = e.target.closest('#mainNav a');
    if (link) {
        e.preventDefault();
        window.history.pushState({}, '', link.getAttribute('href'));
        router();
    }
});

document.getElementById('logoutBtn').addEventListener('click', async () => {
    await apiRequest('/auth/logout', 'POST');
    AppState.isAuthenticated = false;
    updateNavbar();
    router();
    showToast('Вы вышли из системы', 'success');
});

window.addEventListener('popstate', router);

document.addEventListener('DOMContentLoaded', async () => {
    AppState.isAuthenticated = await checkAuth();
    updateNavbar();
    router();
});

function updateNavbar() {
    document.querySelectorAll('.auth-only').forEach(el => el.style.display = AppState.isAuthenticated ? '' : 'none');
    document.getElementById('loginBtn').classList.toggle('d-none', AppState.isAuthenticated);
    document.getElementById('logoutBtn').classList.toggle('d-none', !AppState.isAuthenticated);
}

const authModal = document.getElementById('authModal');
const authForm = document.getElementById('authForm');
const authEmail = document.getElementById('authEmail');
const authPassword = document.getElementById('authPassword');
const authName = document.getElementById('authName');
const authSubmit = document.getElementById('authSubmit');
const authModalTitle = document.getElementById('authModalTitle');
const switchModeBtn = document.getElementById('switchMode');
const authError = document.getElementById('authError');
const nameGroup = document.getElementById('nameGroup');
const demoLoginBtn = document.getElementById('demoLoginBtn');
let isLoginMode = true;

switchModeBtn.addEventListener('click', () => {
    isLoginMode = !isLoginMode;
    if (isLoginMode) {
        authSubmit.textContent = 'Войти';
        authModalTitle.textContent = 'Вход';
        switchModeBtn.textContent = 'Нет аккаунта? Зарегистрироваться';
        nameGroup.classList.add('d-none');
    } else {
        authSubmit.textContent = 'Зарегистрироваться';
        authModalTitle.textContent = 'Регистрация';
        switchModeBtn.textContent = 'Уже есть аккаунт? Войти';
        nameGroup.classList.remove('d-none');
    }
    authError.classList.add('d-none');
});

authForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    authError.classList.add('d-none');
    
    try {
        let response;
        if (isLoginMode) {
            response = await apiRequest('/auth/login', 'POST', { email: authEmail.value, password: authPassword.value });
        } else {
            response = await apiRequest('/auth/register', 'POST', { email: authEmail.value, password: authPassword.value, name: authName.value });
        }
        AppState.isAuthenticated = true;
        updateNavbar();
        bootstrap.Modal.getInstance(authModal).hide();
        router();
        showToast(response.message, 'success');
        authEmail.value = '';
        authPassword.value = '';
        authName.value = '';
    } catch (err) {
        authError.textContent = err.message;
        authError.classList.remove('d-none');
    }
});

demoLoginBtn.addEventListener('click', async () => {
    try {
        await apiRequest('/auth/login', 'POST', { email: 'demo@example.com', password: 'password123' });
        AppState.isAuthenticated = true;
        updateNavbar();
        bootstrap.Modal.getInstance(authModal).hide();
        router();
        showToast('Демо-доступ активирован', 'success');
    } catch (err) {
        authError.textContent = 'Ошибка демо-входа: ' + err.message;
        authError.classList.remove('d-none');
    }
});