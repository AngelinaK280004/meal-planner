import { apiRequest } from '../api.js';

export class Dashboard {
    static async render() {
        const app = document.getElementById('app');
        app.innerHTML = '<div class="text-center py-5"><div class="loading-spinner"></div><p class="mt-2">Загрузка...</p></div>';
        
        if (!window.AppState.isAuthenticated) {
            app.innerHTML = `
                <div class="row justify-content-center">
                    <div class="col-md-8 col-lg-6">
                        <div class="card text-center p-5">
                            <i class="bi bi-egg-fried display-1 text-primary mb-3"></i>
                            <h1 class="mb-3">Гармония вкуса</h1>
                            <p class="text-secondary mb-4">Ваш персональный помощник для здорового рациона</p>
                            <div>
                                <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#authModal">Войти</button>
                                <button class="btn btn-outline-primary" id="demoLoginBtnHeader">Демо-доступ</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('demoLoginBtnHeader')?.addEventListener('click', async () => {
                await apiRequest('/auth/login', 'POST', { email: 'demo@example.com', password: 'password123' });
                window.AppState.isAuthenticated = true;
                location.reload();
            });
            return;
        }
        
        try {
            const [dashboard, counts] = await Promise.all([
                apiRequest('/dashboard'),
                apiRequest('/user/counts')
            ]);
            
            const goals = dashboard.goals || {};
            const stats = dashboard.today_stats || {};
            
            const totalCal = parseFloat(stats.total_cal) || 0;
            const totalProtein = parseFloat(stats.total_protein) || 0;
            const totalFat = parseFloat(stats.total_fat) || 0;
            const totalCarbs = parseFloat(stats.total_carbs) || 0;
            
            const goalCal = parseFloat(goals.daily_calories_goal) || 0;
            const goalProtein = parseFloat(goals.daily_protein_goal) || 0;
            const goalFat = parseFloat(goals.daily_fat_goal) || 0;
            const goalCarbs = parseFloat(goals.daily_carbs_goal) || 0;
            
            const isOverCal = (totalCal > goalCal) && (goalCal > 0);
            const isOverProtein = (totalProtein > goalProtein) && (goalProtein > 0);
            const isOverFat = (totalFat > goalFat) && (goalFat > 0);
            const isOverCarbs = (totalCarbs > goalCarbs) && (goalCarbs > 0);
            
            console.log('isOverCarbs (перед рендером):', isOverCarbs);
            
            const calcPercent = (curr, goal) => {
                if (goal <= 0) return 0;
                let p = (curr / goal) * 100;
                p = Math.max(0, Math.min(100, p));
                return p.toFixed(0);
            };
            
            const calPercent = calcPercent(totalCal, goalCal);
            const proteinPercent = calcPercent(totalProtein, goalProtein);
            const fatPercent = calcPercent(totalFat, goalFat);
            const carbsPercent = calcPercent(totalCarbs, goalCarbs);
            
            app.innerHTML = `
                <div class="row g-4 mb-5">
                    <div class="col-md-6 col-lg-3"><div class="card p-3 text-center"><i class="bi bi-box-seam fs-1 text-primary"></i><h3 class="mt-2">${counts?.products || 0}</h3><p class="text-secondary mb-0">Продуктов</p></div></div>
                    <div class="col-md-6 col-lg-3"><div class="card p-3 text-center"><i class="bi bi-cart3 fs-1 text-warning"></i><h3 class="mt-2">${counts?.shopping || 0}</h3><p class="text-secondary mb-0">К покупке</p></div></div>
                    <div class="col-md-6 col-lg-3"><div class="card p-3 text-center"><i class="bi bi-heart fs-1 text-danger"></i><h3 class="mt-2">${counts?.favorites || 0}</h3><p class="text-secondary mb-0">В избранном</p></div></div>
                    <div class="col-md-6 col-lg-3"><div class="card p-3 text-center"><i class="bi bi-calendar-week fs-1 text-success"></i><h3 class="mt-2">${new Date().toLocaleDateString('ru-RU', { weekday: 'long', day: 'numeric', month: 'long' })}</h3><p class="text-secondary mb-0">Сегодня</p></div></div>
                </div>
                <div class="row g-4">
                    <div class="col-lg-7"><div class="card p-4"><h5 class="mb-3"><i class="bi bi-fire"></i> Питание сегодня</h5>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Калории</span>
                                <span class="${isOverCal ? 'text-danger fw-bold' : ''}">${Math.round(totalCal)} / ${Math.round(goalCal)} ккал</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width: ${calPercent}%; background-color: ${isOverCal ? '#dc3545' : '#198754'};"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Белки</span>
                                <span class="${isOverProtein ? 'text-danger fw-bold' : ''}">${Math.round(totalProtein)} / ${Math.round(goalProtein)} г</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width: ${proteinPercent}%; background-color: ${isOverProtein ? '#dc3545' : '#0dcaf0'};"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Жиры</span>
                                <span class="${isOverFat ? 'text-danger fw-bold' : ''}">${Math.round(totalFat)} / ${Math.round(goalFat)} г</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width: ${fatPercent}%; background-color: ${isOverFat ? '#dc3545' : '#ffc107'};"></div>
                            </div>
                        </div>
                        
                        <div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Углеводы</span>
                                <span class="${isOverCarbs ? 'text-danger fw-bold' : ''}">${Math.round(totalCarbs)} / ${Math.round(goalCarbs)} г</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width: ${carbsPercent}%; background-color: ${isOverCarbs ? '#dc3545' : '#6c757d'};"></div>
                            </div>
                        </div>
                        
                    </div></div>
                    <div class="col-lg-5"><div class="card p-4"><h5 class="mb-3"><i class="bi bi-clock-history"></i> Приёмы пищи</h5>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between"><span><i class="bi bi-sun"></i> Завтрак</span><span class="fw-bold">${Math.round(dashboard.meals?.breakfast || 0)} ккал</span></li>
                            <li class="list-group-item d-flex justify-content-between"><span><i class="bi bi-brightness-alt-high"></i> Обед</span><span class="fw-bold">${Math.round(dashboard.meals?.lunch || 0)} ккал</span></li>
                            <li class="list-group-item d-flex justify-content-between"><span><i class="bi bi-moon"></i> Ужин</span><span class="fw-bold">${Math.round(dashboard.meals?.dinner || 0)} ккал</span></li>
                            <li class="list-group-item d-flex justify-content-between"><span><i class="bi bi-cup-straw"></i> Перекус</span><span class="fw-bold">${Math.round(dashboard.meals?.snack || 0)} ккал</span></li>
                        </ul>
                        ${dashboard.next_meal ? `<div class="mt-3 text-center text-success"><i class="bi bi-bell"></i> Следующий: ${dashboard.next_meal}</div>` : ''}
                    </div></div>
                </div>
            `;
        } catch (err) {
            app.innerHTML = `<div class="alert alert-danger">${err.message}</div>`;
        }
    }
}