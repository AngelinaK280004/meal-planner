import { apiRequest, showToast } from '../api.js';

export class Menu {
    static async render() {
        const app = document.getElementById('app');
        app.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
                <h3><i class="bi bi-calendar-week"></i> Моё меню</h3>
                <div class="d-flex gap-2 flex-wrap">
                    <input type="date" id="periodStart" class="form-control w-auto" value="${this.getMonday()}">
                    <span>—</span>
                    <input type="date" id="periodEnd" class="form-control w-auto" value="${this.getSunday()}">
                    <button id="optimizeMenuBtn" class="btn btn-success">Автоматизировать</button>
                    <button class="btn btn-outline-info" onclick="window.open('/meal-planner/print_menu.php?start='+document.getElementById('periodStart').value+'&end='+document.getElementById('periodEnd').value, '_blank')">Печать меню</button>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-3"><label>Кол-во приёмов пищи в день:</label><input type="number" id="mealsPerDay" class="form-control" value="3" min="1" max="4"></div>
            </div>
            <div id="mealSettings" class="row mb-3 g-2"></div>
            <div id="menuCalendar" class="table-responsive"></div>
            <div id="dayStats" class="row mt-3"></div>

            <div class="modal fade" id="alternativesModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header"><h5>Выберите замену</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body" id="alternativesList"></div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="addRecipeModal" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 id="addModalTitle">Добавить блюдо</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="text" id="recipeSearchInput" class="form-control mb-3" placeholder="Поиск по названию...">
                            <div id="recipeListContainer" class="row g-3"></div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="recipeModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content" id="recipeModalContent"></div>
                </div>
            </div>
        `;

        document.getElementById('mealsPerDay').addEventListener('change', () => this.renderMealSettings());
        document.getElementById('optimizeMenuBtn').addEventListener('click', () => this.optimizeMenu());
        document.getElementById('periodStart').addEventListener('change', () => this.loadMenu());
        document.getElementById('periodEnd').addEventListener('change', () => this.loadMenu());

        this.renderMealSettings();
        await this.loadMenu();
    }

    static getMonday() {
        let d = new Date();
        d.setDate(d.getDate() - (d.getDay() + 6) % 7);
        return d.toISOString().split('T')[0];
    }
    static getSunday() {
        let d = new Date(this.getMonday());
        d.setDate(d.getDate() + 6);
        return d.toISOString().split('T')[0];
    }

    static renderMealSettings() {
        const mealsPerDay = parseInt(document.getElementById('mealsPerDay').value);
        const mealNames = ['breakfast', 'lunch', 'dinner', 'snack'];
        const mealLabels = { breakfast: 'Завтрак', lunch: 'Обед', dinner: 'Ужин', snack: 'Перекус' };
        const container = document.getElementById('mealSettings');
        container.innerHTML = '';
        for (let i = 0; i < mealsPerDay; i++) {
            const mt = mealNames[i];
            const div = document.createElement('div');
            div.className = 'col-md-3';
            div.innerHTML = `
                <div class="card p-2">
                    <h6>${mealLabels[mt]}</h6>
                    <label class="form-label small">Блюд:</label>
                    <input type="number" class="form-control form-control-sm dishes-input" data-meal="${mt}" value="1" min="1" max="3">
                    <label class="form-label small mt-2">Напитков:</label>
                    <input type="number" class="form-control form-control-sm drinks-input" data-meal="${mt}" value="1" min="0" max="2">
                </div>
            `;
            container.appendChild(div);
        }
    }

    static getMealSettings() {
        const mealsPerDay = parseInt(document.getElementById('mealsPerDay').value);
        const mealNames = ['breakfast', 'lunch', 'dinner', 'snack'].slice(0, mealsPerDay);
        const dishes = {};
        const drinks = {};
        mealNames.forEach(mt => {
            dishes[mt] = parseInt(document.querySelector(`.dishes-input[data-meal="${mt}"]`)?.value || 1);
            drinks[mt] = parseInt(document.querySelector(`.drinks-input[data-meal="${mt}"]`)?.value || 1);
        });
        return { mealsPerDay, mealNames, dishes, drinks };
    }

    static async loadMenu() {
        const start = document.getElementById('periodStart').value;
        const end = document.getElementById('periodEnd').value;
        const { mealsPerDay, mealNames, dishes, drinks } = this.getMealSettings();

        const menu = await apiRequest(`/menu?start_date=${start}&end_date=${end}`);
        const daysCount = Math.ceil((new Date(end) - new Date(start)) / 86400000) + 1;
        const days = ['ПН', 'ВТ', 'СР', 'ЧТ', 'ПТ', 'СБ', 'ВС'];
        const mealLabels = { breakfast: 'Завтрак', lunch: 'Обед', dinner: 'Ужин', snack: 'Перекус' };

        let html = '<table class="table table-bordered"><thead><tr><th>День</th>';
        mealNames.forEach(mt => html += `<th>${mealLabels[mt]}</th>`);
        html += '<th>Итого ккал</th></tr></thead><tbody>';

        let dayCalories = [];

        for (let d = 1; d <= daysCount; d++) {
            let dayMeals = menu[d] || [];
            let totalDayCal = 0;
            html += `<tr><td class="fw-bold">${days[(d - 1) % 7]}<br><small>${this.formatDate(start, d - 1)}</small></td>`;

            for (let mt of mealNames) {
                let items = dayMeals.filter(m => m.meal_type === mt);
                let dishesItems = items.filter(m => m.is_drink === 0);
                let drinksItems = items.filter(m => m.is_drink === 1);

                let displayDishes = dishesItems.slice(0, dishes[mt]);
                let displayDrinks = drinksItems.slice(0, drinks[mt]);

                html += `<td style="min-width:220px">`;

                for (let meal of displayDishes) {
                    totalDayCal += meal.calories_per_serving;
                    html += `
                        <div class="menu-item border rounded p-1 mb-1">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="recipe-name" style="cursor:pointer" data-recipe-id="${meal.recipe_id}">${this.escapeHtml(meal.name)}</span>
                                <div class="rating-stars" data-recipe-id="${meal.recipe_id}">
                                    ${[1, 2, 3, 4, 5].map(s => `<i class="bi bi-star-fill rating-star" data-value="${s}" style="cursor:pointer; color:#ddd;"></i>`).join('')}
                                </div>
                            </div>
                            <small>${meal.calories_per_serving} ккал</small>
                            <button class="btn btn-sm btn-outline-secondary w-100 mt-1 replace-btn" 
                                data-day="${d}" 
                                data-meal="${mt}" 
                                data-menu-id="${meal.menu_recipe_id}" 
                                data-recipe-id="${meal.recipe_id}"
                                data-calories="${meal.calories_per_serving}">
                                Заменить
                            </button>
                        </div>
                    `;
                }
                if (dishesItems.length < dishes[mt]) {
                    html += `<button class="btn btn-sm btn-outline-success w-100 mb-2 add-dish" data-day="${d}" data-meal="${mt}">+ Добавить блюдо</button>`;
                }

                for (let drink of displayDrinks) {
                    totalDayCal += drink.calories_per_serving;
                    html += `
                        <div class="menu-item border rounded p-1 mb-1">
                            <div class="d-flex justify-content-between">
                                <span class="recipe-name" style="cursor:pointer" data-recipe-id="${drink.recipe_id}">${this.escapeHtml(drink.name)}</span>
                            </div>
                            <small>${drink.calories_per_serving} ккал</small>
                            <button class="btn btn-sm btn-outline-secondary w-100 mt-1 replace-btn" 
                                data-day="${d}" 
                                data-meal="${mt}" 
                                data-menu-id="${drink.menu_recipe_id}" 
                                data-recipe-id="${drink.recipe_id}"
                                data-calories="${drink.calories_per_serving}">
                                Заменить
                            </button>
                        </div>
                    `;
                }
                if (drinksItems.length < drinks[mt] && drinks[mt] > 0) {
                    html += `<button class="btn btn-sm btn-outline-success w-100 add-drink" data-day="${d}" data-meal="${mt}">+ Добавить напиток</button>`;
                }

                html += `</td>`;
            }
            html += `<td class="fw-bold">${totalDayCal} ккал</td></tr>`;
            dayCalories.push(totalDayCal);
        }
        html += '</tbody></table>';
        document.getElementById('menuCalendar').innerHTML = html;

        this.attachEventHandlers();
        this.renderDayStats(dayCalories);
    }

    static attachEventHandlers() {
        document.querySelectorAll('.recipe-name').forEach(el => {
            el.addEventListener('click', async (e) => {
                const recipeId = el.dataset.recipeId;
                if (recipeId) await this.showRecipeDetails(recipeId);
            });
        });

        document.querySelectorAll('.rating-star').forEach(star => {
            star.addEventListener('click', async (e) => {
                e.stopPropagation();
                const rating = parseInt(star.dataset.value);
                const container = star.closest('.rating-stars');
                const recipeId = container.dataset.recipeId;
                try {
                    await apiRequest('/recipes/rate', 'POST', { recipe_id: recipeId, rating: rating });
                    const stars = container.querySelectorAll('.rating-star');
                    stars.forEach((s, idx) => {
                        s.style.color = idx < rating ? '#ffc107' : '#ddd';
                    });
                    showToast('Оценка сохранена', 'success');
                } catch (err) {
                    showToast(err.message, 'danger');
                }
            });
        });

        document.querySelectorAll('.replace-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const day = btn.dataset.day;
                const meal = btn.dataset.meal;
                const menuId = btn.dataset.menuId;
                const currentCalories = parseInt(btn.dataset.calories) || 500;
                const calMax = Math.floor(currentCalories * 1.2);
                
                try {
                    const alt = await apiRequest(`/menu/alternatives?meal_id=${menuId}&cal_max=${calMax}`);
                    if (!alt.length) {
                        showToast('Нет подходящих альтернатив с учётом ваших ограничений', 'warning');
                        return;
                    }
                    
                    const modalBody = document.getElementById('alternativesList');
                    modalBody.innerHTML = alt.map(a => `
                        <div class="border-bottom p-2 d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${this.escapeHtml(a.name)}</strong><br>
                                <small>${a.calories_per_serving} ккал • ${a.cooking_time} мин • ${'★'.repeat(a.difficulty)}</small>
                            </div>
                            <button class="btn btn-sm btn-primary select-alt" data-recipe-id="${a.recipe_id}">Выбрать</button>
                        </div>
                    `).join('');
                    
                    const modal = new bootstrap.Modal(document.getElementById('alternativesModal'));
                    modal.show();

                    document.querySelectorAll('.select-alt').forEach(btnAlt => {
                        btnAlt.replaceWith(btnAlt.cloneNode(true));
                    });
                    
                    document.querySelectorAll('.select-alt').forEach(btnAlt => {
                        btnAlt.addEventListener('click', async () => {
                            const newRecipeId = btnAlt.dataset.recipeId;
                            try {
                                if (menuId) {
                                    await apiRequest(`/menu/remove/${menuId}`, 'DELETE');
                                }
                                await apiRequest('/menu/assign', 'POST', { 
                                    recipe_id: parseInt(newRecipeId), 
                                    meal_type: meal, 
                                    day_number: parseInt(day), 
                                    serving_size: 1 
                                });
                                modal.hide();
                                await this.loadMenu(); 
                                showToast('Блюдо успешно заменено', 'success');
                            } catch (err) {
                                console.error('Ошибка замены:', err);
                                showToast(err.message || 'Ошибка при замене', 'danger');
                            }
                        });
                    });
                } catch (err) {
                    console.error('Ошибка загрузки альтернатив:', err);
                    showToast(err.message, 'danger');
                }
            });
        });

        document.querySelectorAll('.add-dish').forEach(btn => {
            btn.addEventListener('click', async () => {
                const day = btn.dataset.day;
                const meal = btn.dataset.meal;
                await this.showRecipePicker('dish', day, meal);
            });
        });

        document.querySelectorAll('.add-drink').forEach(btn => {
            btn.addEventListener('click', async () => {
                const day = btn.dataset.day;
                const meal = btn.dataset.meal;
                await this.showRecipePicker('drink', day, meal);
            });
        });
    }

    static async showRecipePicker(type, day, meal) {
        const isDrink = (type === 'drink') ? 1 : 0;
        const modalElement = document.getElementById('addRecipeModal');
        const modalTitle = document.getElementById('addModalTitle');
        const container = document.getElementById('recipeListContainer');
        const searchInput = document.getElementById('recipeSearchInput');
        
        modalTitle.textContent = (isDrink ? 'Выберите напиток' : 'Выберите блюдо');

        const loadRecipes = async (search = '') => {
            container.innerHTML = '<div class="col-12 text-center py-4"><div class="loading-spinner-sm"></div> Загрузка...</div>';
            try {
                let url = `/recipes?is_drink=${isDrink}&limit=100`;
                if (search.trim().length >= 2) url += `&search=${encodeURIComponent(search)}`;
                const recipes = await apiRequest(url);
                if (!recipes.length) {
                    container.innerHTML = '<div class="col-12 text-center py-4">Нет рецептов</div>';
                    return;
                }
                container.innerHTML = recipes.map(r => `
                    <div class="col-md-6">
                        <div class="border rounded p-2 d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${this.escapeHtml(r.name)}</strong><br>
                                <small>${r.calories_per_serving} ккал • ${r.cooking_time} мин • ${'★'.repeat(r.difficulty)}</small>
                            </div>
                            <button class="btn btn-sm btn-primary select-recipe-btn" data-id="${r.recipe_id}">Выбрать</button>
                        </div>
                    </div>
                `).join('');
                document.querySelectorAll('.select-recipe-btn').forEach(btnSel => {
                    btnSel.addEventListener('click', async () => {
                        const recipeId = btnSel.dataset.id;
                        try {
                            await apiRequest('/menu/assign', 'POST', {
                                recipe_id: parseInt(recipeId),
                                meal_type: meal,
                                day_number: parseInt(day),
                                serving_size: 1
                            });
                            bootstrap.Modal.getInstance(modalElement).hide();
                            await this.loadMenu();
                            showToast('Рецепт добавлен', 'success');
                        } catch (err) {
                            showToast(err.message, 'danger');
                        }
                    });
                });
            } catch (err) {
                container.innerHTML = `<div class="col-12 text-center text-danger">Ошибка загрузки: ${err.message}</div>`;
            }
        };
        
        await loadRecipes('');
        
        let timeout;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => loadRecipes(e.target.value), 400);
        });
        
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
        
        modalElement.addEventListener('hidden.bs.modal', () => {
            searchInput.value = '';
        }, { once: true });
    }

    static renderDayStats(dayCalories) {
        let statsHtml = '<div class="col-12"><div class="card"><div class="card-body"><h5>Калорийность по дням</h5><div class="progress-stacked">';
        dayCalories.forEach((cal, idx) => {
            let percent = (cal / 2000) * 100;
            statsHtml += `<div class="progress-bar" style="width: ${percent}%; background-color: hsl(${idx * 30}, 70%, 50%);">${cal}</div>`;
        });
        statsHtml += `</div></div></div></div>`;
        document.getElementById('dayStats').innerHTML = statsHtml;
    }

    static async optimizeMenu() {
        const start = document.getElementById('periodStart').value;
        const end = document.getElementById('periodEnd').value;
        const { mealsPerDay, mealNames, dishes, drinks } = this.getMealSettings();

        try {
            await apiRequest('/menu/optimize', 'POST', {
                period_start: start,
                period_end: end,
                meals_per_day: mealsPerDay,
                meals_order: mealNames,
                dishes_breakfast: dishes.breakfast || 1,
                drinks_breakfast: drinks.breakfast || 1,
                dishes_lunch: dishes.lunch || 1,
                drinks_lunch: drinks.lunch || 1,
                dishes_dinner: dishes.dinner || 1,
                drinks_dinner: drinks.dinner || 1,
                dishes_snack: dishes.snack || 1,
                drinks_snack: drinks.snack || 1
            });
            showToast('Меню оптимизировано', 'success');
            await this.loadMenu();
        } catch (err) {
            showToast(err.message, 'danger');
        }
    }

    static async showRecipeDetails(recipeId) {
        try {
            const recipe = await apiRequest(`/recipes/${recipeId}`);
            const modalContent = document.getElementById('recipeModalContent');
            modalContent.innerHTML = `
                <div class="modal-header">
                    <h5>${this.escapeHtml(recipe.name)}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    ${recipe.image_url ? `<img src="${recipe.image_url}" class="img-fluid mb-3 rounded">` : ''}
                    <p><strong>Время:</strong> ${recipe.cooking_time} мин • <strong>Сложность:</strong> ${'★'.repeat(recipe.difficulty)}</p>
                    <p><strong>КБЖУ:</strong> ${recipe.calories_per_serving} ккал | Б: ${recipe.protein_per_serving} г | Ж: ${recipe.fat_per_serving} г | У: ${recipe.carbs_per_serving} г</p>
                    <h6>Ингредиенты:</h6>
                    <ul>${recipe.ingredients.map(i => `<li>${i.name} – ${i.quantity} ${i.unit}</li>`).join('')}</ul>
                    <h6>Приготовление:</h6>
                    <p>${recipe.instructions.replace(/\n/g, '<br>')}</p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                </div>
            `;
            new bootstrap.Modal(document.getElementById('recipeModal')).show();
        } catch (err) {
            showToast(err.message, 'danger');
        }
    }

    static formatDate(start, offset) {
        let d = new Date(start);
        d.setDate(d.getDate() + offset);
        return d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'numeric' });
    }

    static escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[m]));
    }
}