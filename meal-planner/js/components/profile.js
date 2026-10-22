import { apiRequest, showToast } from '../api.js';

export class Profile {
    static async render() {
        const app = document.getElementById('app');
        app.innerHTML = '<div class="text-center py-5"><div class="loading-spinner"></div><p class="mt-2">Загрузка...</p></div>';

        try {
            const [profile, prefs, allergens, excluded, favorites, ratings] = await Promise.all([
                apiRequest('/user/profile'),
                apiRequest('/user/preferences'),
                apiRequest('/user/allergens'),
                apiRequest('/user/excluded'),
                apiRequest('/favorites'),
                apiRequest('/recipes/ratings')
            ]);

            let dietaryOptions = [];
            try {
                dietaryOptions = await apiRequest('/user/dietary-options');
            } catch (e) {
                dietaryOptions = [
                    { option_id: 1, mask_value: 1, name: 'Вегетарианство' },
                    { option_id: 2, mask_value: 2, name: 'Веганство' },
                    { option_id: 3, mask_value: 4, name: 'Без глютена' },
                    { option_id: 4, mask_value: 8, name: 'Без лактозы' },
                    { option_id: 5, mask_value: 16, name: 'Кето' },
                    { option_id: 6, mask_value: 32, name: 'Без сахара' }
                ];
            }

            const cuisines = [
                { code: 'russian', name: 'Русская' },
                { code: 'european', name: 'Европейская' },
                { code: 'asian', name: 'Азиатская' },
                { code: 'italian', name: 'Итальянская' },
                { code: 'greek', name: 'Греческая' },
                { code: 'american', name: 'Американская' },
                { code: 'caucasian', name: 'Кавказская' },
                { code: 'any', name: 'Любая' }
            ];

            const defaultPrefs = {
                dishes_breakfast: 1, drinks_breakfast: 1,
                dishes_lunch: 1, drinks_lunch: 1,
                dishes_dinner: 1, drinks_dinner: 1,
                dishes_snack: 1, drinks_snack: 1,
                max_difficulty: 2,
                meals_per_day: 3,
                preferred_cuisine: 'any',
                dietary_mask: 0
            };
            const mergedPrefs = { ...defaultPrefs, ...prefs };

            app.innerHTML = `
                <div class="row g-4">
                    <!-- Личные данные -->
                    <div class="col-lg-4">
                        <div class="card p-4">
                            <h5><i class="bi bi-person-badge"></i> Личные данные</h5>
                            <div class="mb-3"><label>Имя</label><input class="form-control" id="name" value="${this.escapeHtml(profile.name)}"></div>
                            <div class="mb-3"><label>Возраст, лет</label><input type="number" class="form-control" id="age" value="${profile.age || ''}"></div>
                            <div class="mb-3"><label>Вес, кг</label><input type="number" step="0.1" class="form-control" id="weight" value="${profile.weight_kg || ''}"></div>
                            <div class="mb-3"><label>Рост, см</label><input type="number" step="0.1" class="form-control" id="height" value="${profile.height_cm || ''}"></div>
                            <div class="mb-3"><label>Пол</label>
                                <select class="form-select" id="gender">
                                    <option value="M" ${profile.gender === 'M' ? 'selected' : ''}>Мужской</option>
                                    <option value="F" ${profile.gender === 'F' ? 'selected' : ''}>Женский</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card p-4">
                            <h5><i class="bi bi-graph-up"></i> Цель и активность</h5>
                            <div class="mb-3"><label>Уровень активности</label>
                                <select class="form-select" id="activity">
                                    <option value="sedentary" ${profile.activity_level === 'sedentary' ? 'selected' : ''}>Сидячий</option>
                                    <option value="light" ${profile.activity_level === 'light' ? 'selected' : ''}>Лёгкая активность</option>
                                    <option value="moderate" ${profile.activity_level === 'moderate' ? 'selected' : ''}>Умеренная</option>
                                    <option value="active" ${profile.activity_level === 'active' ? 'selected' : ''}>Активная</option>
                                    <option value="very_active" ${profile.activity_level === 'very_active' ? 'selected' : ''}>Очень активная</option>
                                </select>
                            </div>
                            <div class="mb-3"><label>Цель</label>
                                <select class="form-select" id="goal">
                                    <option value="weight_loss" ${profile.goal_type === 'weight_loss' ? 'selected' : ''}>Похудение</option>
                                    <option value="maintenance" ${profile.goal_type === 'maintenance' ? 'selected' : ''}>Поддержание веса</option>
                                    <option value="muscle_gain" ${profile.goal_type === 'muscle_gain' ? 'selected' : ''}>Набор массы</option>
                                </select>
                            </div>
                            <div class="mb-3"><label>Приёмов пищи в день</label><input type="number" class="form-control" id="mealsPerDay" value="${mergedPrefs.meals_per_day}" min="1" max="6"></div>
                            <div class="mb-3"><label>Макс. сложность рецептов</label>
                                <select class="form-select" id="difficulty">
                                    <option value="1" ${mergedPrefs.max_difficulty == 1 ? 'selected' : ''}>Лёгкие ★</option>
                                    <option value="2" ${mergedPrefs.max_difficulty == 2 ? 'selected' : ''}>Средние ★★</option>
                                    <option value="3" ${mergedPrefs.max_difficulty == 3 ? 'selected' : ''}>Любые ★★★</option>
                                </select>
                            </div>
                            <div class="mb-3"><label>Предпочитаемая кухня</label>
                                <select class="form-select" id="cuisine">
                                    ${cuisines.map(c => `<option value="${c.code}" ${mergedPrefs.preferred_cuisine === c.code ? 'selected' : ''}>${c.name}</option>`).join('')}
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card p-4">
                            <h5><i class="bi bi-shield-exclamation"></i> Аллергии</h5>
                            <div id="allergensList" class="mb-2"></div>
                            <select class="form-select mb-2" id="allergenSelect">
                                <option value="milk">Молочные продукты</option>
                                <option value="nuts">Орехи</option>
                                <option value="gluten">Глютен</option>
                                <option value="eggs">Яйца</option>
                                <option value="seafood">Морепродукты</option>
                                <option value="soy">Соя</option>
                                <option value="sesame">Кунжут</option>
                            </select>
                            <button class="btn btn-sm btn-outline-primary w-100" id="addAllergenBtn">+ Добавить аллерген</button>
                        </div>
                    </div>

                    <div class="col-12 mt-2">
                        <div class="card p-4">
                            <h5><i class="bi bi-egg-fried"></i> Количество блюд и напитков по приёмам</h5>
                            <div class="row">
                                <div class="col-md-3">Завтрак: <input type="number" id="dishes_breakfast" class="form-control" value="${mergedPrefs.dishes_breakfast}" min="0" max="3"> блюд, <input type="number" id="drinks_breakfast" class="form-control" value="${mergedPrefs.drinks_breakfast}" min="0" max="2"> напитков</div>
                                <div class="col-md-3">Обед: <input type="number" id="dishes_lunch" class="form-control" value="${mergedPrefs.dishes_lunch}" min="0" max="3"> блюд, <input type="number" id="drinks_lunch" class="form-control" value="${mergedPrefs.drinks_lunch}" min="0" max="2"> напитков</div>
                                <div class="col-md-3">Ужин: <input type="number" id="dishes_dinner" class="form-control" value="${mergedPrefs.dishes_dinner}" min="0" max="3"> блюд, <input type="number" id="drinks_dinner" class="form-control" value="${mergedPrefs.drinks_dinner}" min="0" max="2"> напитков</div>
                                <div class="col-md-3">Перекус: <input type="number" id="dishes_snack" class="form-control" value="${mergedPrefs.dishes_snack}" min="0" max="3"> блюд, <input type="number" id="drinks_snack" class="form-control" value="${mergedPrefs.drinks_snack}" min="0" max="2"> напитков</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 mt-2">
                        <div class="card p-4">
                            <h5><i class="bi bi-flower1"></i> Диетические ограничения (выберите подходящие)</h5>
                            <div id="dietaryOptionsList" class="row g-2 mb-3">
                                ${dietaryOptions.map(opt => `
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input dietary-check" type="checkbox" 
                                                   value="${opt.mask_value}" id="opt_${opt.option_id}"
                                                   ${(mergedPrefs.dietary_mask & opt.mask_value) ? 'checked' : ''}>
                                            <label class="form-check-label" for="opt_${opt.option_id}">
                                                ${opt.name}
                                            </label>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                            <div class="mt-2">
                                <strong>Выбранные ограничения:</strong>
                                <div id="selectedDietaryCards" class="d-flex flex-wrap gap-2 mt-2"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card p-4">
                            <h5><i class="bi bi-x-octagon"></i> Исключённые продукты</h5>
                            <div id="excludedList" class="mb-2"></div>
                            <div class="input-group">
                                <input type="text" class="form-control" id="excludedInput" placeholder="Название продукта">
                                <button class="btn btn-outline-primary" id="addExcludedBtn">Исключить</button>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5><i class="bi bi-heart-fill text-danger"></i> Избранные рецепты</h5>
                                <button class="btn btn-sm btn-outline-primary" id="openFavoritesModalBtn">
                                    <i class="bi bi-plus-lg"></i> Выбрать блюда
                                </button>
                            </div>
                            <div id="favoritesList" class="row"></div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card p-4">
                            <h5><i class="bi bi-star-fill text-warning"></i> Мои оценки</h5>
                            <div id="ratingsList" class="table-responsive"></div>
                        </div>
                    </div>

                    <div class="col-12 text-center">
                        <button class="btn btn-primary btn-lg px-5" id="saveBtn">Сохранить профиль</button>
                    </div>
                </div>

                <div class="modal fade" id="favoritesModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="bi bi-search-heart"></i> Выберите блюда для добавления в избранное</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div id="recipesListForModal" class="row g-3">
                                    <div class="text-center py-4"><div class="loading-spinner-sm"></div> Загрузка рецептов...</div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            const renderLists = () => {
                document.getElementById('allergensList').innerHTML = allergens.map(a => `
                    <div class="bg-white border rounded p-2 mb-2 d-flex justify-content-between align-items-center">
                        <span>${this.getAllergenName(a)}</span>
                        <button class="btn btn-sm btn-outline-danger remove-item" data-type="allergen" data-value="${a}">Удалить</button>
                    </div>
                `).join('');

                document.getElementById('excludedList').innerHTML = excluded.map(e => `
                    <div class="bg-white border rounded p-2 mb-2 d-flex justify-content-between align-items-center">
                        <span>${this.escapeHtml(e)}</span>
                        <button class="btn btn-sm btn-outline-danger remove-item" data-type="excluded" data-value="${e}">Удалить</button>
                    </div>
                `).join('');

                document.querySelectorAll('.remove-item').forEach(btn => {
                    btn.addEventListener('click', async () => {
                        const type = btn.dataset.type;
                        const value = btn.dataset.value;
                        await apiRequest(`/user/${type}s/${encodeURIComponent(value)}`, 'DELETE');
                        location.reload();
                    });
                });
            };
            renderLists();

            const dietaryChecks = document.querySelectorAll('.dietary-check');
            const updateSelectedCards = () => {
                let currentMask = 0;
                dietaryChecks.forEach(cb => {
                    if (cb.checked) currentMask |= parseInt(cb.value);
                });
                const selectedOpts = dietaryOptions.filter(opt => (currentMask & opt.mask_value));
                const container = document.getElementById('selectedDietaryCards');
                container.innerHTML = selectedOpts.map(opt => `
                    <div class="bg-white border rounded p-2 d-flex align-items-center gap-2">
                        <span>${opt.name}</span>
                        <i class="bi bi-x-circle text-danger remove-dietary" data-mask="${opt.mask_value}" style="cursor:pointer"></i>
                    </div>
                `).join('');
                document.querySelectorAll('.remove-dietary').forEach(el => {
                    el.addEventListener('click', () => {
                        const mask = parseInt(el.dataset.mask);
                        dietaryChecks.forEach(cb => {
                            if (parseInt(cb.value) === mask) cb.checked = false;
                        });
                        updateSelectedCards();
                    });
                });
            };
            dietaryChecks.forEach(cb => cb.addEventListener('change', updateSelectedCards));
            updateSelectedCards();

            this.renderFavorites(favorites);

            this.renderRatings(ratings);

            document.getElementById('addAllergenBtn').onclick = async () => {
                const val = document.getElementById('allergenSelect').value;
                await apiRequest('/user/allergens', 'POST', { value: val });
                location.reload();
            };
            document.getElementById('addExcludedBtn').onclick = async () => {
                const val = document.getElementById('excludedInput').value.trim();
                if (val) {
                    await apiRequest('/user/excluded', 'POST', { value: val });
                    location.reload();
                }
            };

            document.getElementById('saveBtn').onclick = async () => {
                let dietaryMask = 0;
                dietaryChecks.forEach(cb => {
                    if (cb.checked) dietaryMask |= parseInt(cb.value);
                });

                const data = {
                    name: document.getElementById('name').value,
                    age: parseInt(document.getElementById('age').value) || 0,
                    weight_kg: parseFloat(document.getElementById('weight').value) || 0,
                    height_cm: parseFloat(document.getElementById('height').value) || 0,
                    gender: document.getElementById('gender').value,
                    activity_level: document.getElementById('activity').value,
                    goal_type: document.getElementById('goal').value,
                    max_difficulty: parseInt(document.getElementById('difficulty').value),
                    meals_per_day: parseInt(document.getElementById('mealsPerDay').value),
                    preferred_cuisine: document.getElementById('cuisine').value,
                    dishes_breakfast: parseInt(document.getElementById('dishes_breakfast').value) || 0,
                    drinks_breakfast: parseInt(document.getElementById('drinks_breakfast').value) || 0,
                    dishes_lunch: parseInt(document.getElementById('dishes_lunch').value) || 0,
                    drinks_lunch: parseInt(document.getElementById('drinks_lunch').value) || 0,
                    dishes_dinner: parseInt(document.getElementById('dishes_dinner').value) || 0,
                    drinks_dinner: parseInt(document.getElementById('drinks_dinner').value) || 0,
                    dishes_snack: parseInt(document.getElementById('dishes_snack').value) || 0,
                    drinks_snack: parseInt(document.getElementById('drinks_snack').value) || 0,
                    dietary_mask: dietaryMask
                };
                try {
                    await apiRequest('/user/profile', 'PUT', data);
                    showToast('Профиль успешно сохранён!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } catch (err) {
                    showToast(err.message, 'danger');
                }
            };

            const openModalBtn = document.getElementById('openFavoritesModalBtn');
            const modalElement = document.getElementById('favoritesModal');
            if (modalElement && openModalBtn) {
                const bsModal = new bootstrap.Modal(modalElement);
                openModalBtn.addEventListener('click', async () => {
                    const modalBody = modalElement.querySelector('.modal-body');
                    modalBody.innerHTML = '<div class="text-center py-4"><div class="loading-spinner-sm"></div> Загрузка рецептов...</div>';
                    bsModal.show();
                    try {
                        const allRecipes = await apiRequest('/recipes?limit=200');
                        const favoriteIds = new Set(favorites.map(f => f.recipe_id));
                        if (allRecipes && allRecipes.length) {
                            modalBody.innerHTML = `
                                <div class="row g-3">
                                    ${allRecipes.map(recipe => `
                                        <div class="col-md-6">
                                            <div class="border rounded p-3 d-flex justify-content-between align-items-center">
                                                <div><strong>${this.escapeHtml(recipe.name)}</strong><br><small>${Math.round(recipe.calories_per_serving || 0)} ккал</small></div>
                                                ${favoriteIds.has(recipe.recipe_id) 
                                                    ? `<span class="badge bg-secondary">В избранном</span>`
                                                    : `<button class="btn btn-sm btn-primary add-to-favorites-btn" data-id="${recipe.recipe_id}">Добавить</button>`}
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            `;
                            modalBody.querySelectorAll('.add-to-favorites-btn').forEach(btn => {
                                btn.addEventListener('click', async () => {
                                    const recipeId = parseInt(btn.dataset.id);
                                    await apiRequest('/favorites', 'POST', { recipe_id: recipeId });
                                    showToast('Рецепт добавлен в избранное', 'success');
                                    bsModal.hide();
                                    location.reload();
                                });
                            });
                        } else {
                            modalBody.innerHTML = '<p class="text-muted text-center">Нет доступных рецептов</p>';
                        }
                    } catch (error) {
                        modalBody.innerHTML = '<div class="alert alert-danger">Не удалось загрузить список рецептов</div>';
                    }
                });
            }
        } catch (err) {
            document.getElementById('app').innerHTML = `<div class="alert alert-danger">${err.message}</div>`;
        }
    }

    static renderFavorites(favs) {
        const container = document.getElementById('favoritesList');
        if (!container) return;
        if (!favs || !favs.length) {
            container.innerHTML = '<p class="text-muted">Нет избранных рецептов</p>';
            return;
        }
        container.style.display = 'flex';
        container.style.flexWrap = 'wrap';
        container.style.gap = '10px';
        container.innerHTML = favs.map(f => `
            <div class="bg-white border rounded p-2 d-flex justify-content-between align-items-center" style="flex: 0 0 auto;">
                <span>${this.escapeHtml(f.name)} (${Math.round(f.calories_per_serving)} ккал)</span>
                <button class="btn btn-sm btn-outline-danger ms-2 remove-fav" data-id="${f.recipe_id}">Удалить</button>
            </div>
        `).join('');
        document.querySelectorAll('.remove-fav').forEach(btn => {
            btn.addEventListener('click', async () => {
                await apiRequest(`/favorites/remove/${btn.dataset.id}`, 'DELETE');
                location.reload();
            });
        });
    }

    static renderRatings(ratings) {
        const container = document.getElementById('ratingsList');
        if (!container) return;
        if (!ratings || !ratings.length) {
            container.innerHTML = '<p class="text-muted">Вы ещё не оценили ни одного рецепта</p>';
            return;
        }
        container.innerHTML = `
            <table class="table table-sm">
                <thead>
                    <tr><th>Рецепт</th><th>Оценка</th><th>Дата</th></tr>
                </thead>
                <tbody>
                    ${ratings.map(r => `
                        <tr>
                            <td>${this.escapeHtml(r.name)}</td>
                            <td>${'★'.repeat(r.rating)}${'☆'.repeat(5 - r.rating)}</td>
                            <td>${r.rated_date}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }

    static getAllergenName(code) {
        const names = {
            milk: 'Молоко', nuts: 'Орехи', gluten: 'Глютен',
            eggs: 'Яйца', seafood: 'Морепродукты', soy: 'Соя', sesame: 'Кунжут'
        };
        return names[code] || code;
    }

    static escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[m]));
    }
}