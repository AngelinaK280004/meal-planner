const API_BASE = '/meal-planner/api';

export async function apiRequest(endpoint, method = 'GET', body = null) {
    const options = {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin'
    };
    if (body) options.body = JSON.stringify(body);
    
    const response = await fetch(API_BASE + endpoint, options);
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || `Ошибка ${response.status}`);
    return data;
}

export async function checkAuth() {
    try {
        await apiRequest('/auth/check');
        return true;
    } catch { return false; }
}

export function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer') || (() => {
        const c = document.createElement('div');
        c.id = 'toastContainer';
        c.className = 'toast-container';
        document.body.appendChild(c);
        return c;
    })();
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-bg-${type === 'success' ? 'success' : 'danger'} border-0`;
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    container.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
    bsToast.show();
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
}