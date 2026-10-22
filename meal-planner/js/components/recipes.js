import { apiRequest, showToast } from '../api.js';

export class Recipes {
    static async render() {
        const app = document.getElementById('app');
        app.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3><i class="bi bi-book"></i> Рецепты</h3>
                ${window.AppState.isAuthenticated ? `<button id="showRecommendations" class="btn btn-success"><i class="bi bi-stars"></i> Рекомендации</button>` : ''}
            </div>
            <div class="card mb-4"><div class="card-body">
                <div class="row g-3">
                    <div class="col-12"><input type="text" id="searchInput" class="form-control" placeholder="Поиск рецептов..."></div>
                    <div class="col-md-3"><select id="categorySelect" class="form-select"><option value="">Все категории</option></select></div>
                    <div class="col-md-2"><input type="number" id="calMin" class="form-control" placeholder="Ккал от"></div>
                    <div class="col-md-2"><input type="number" id="calMax" class="form-control" placeholder="Ккал до"></div>
                    <div class="col-md-2"><input type="number" id="timeMax" class="form-control" placeholder="Время, мин"></div>
                    <div class="col-md-3"><select id="difficultySelect" class="form-select"><option value="">Сложность</option><option value="1">Лёгкий ★</option><option value="2">Средний ★★</option><option value="3">Сложный ★★★</option></select></div>
                </div>
            </div></div>
            <div id="recipesGrid" class="row g-4"></div>
            <div class="modal fade" id="recipeModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content" id="recipeModalContent">Загрузка...</div></div></div>
        `;
        
        await this.loadCategories();
        await this.loadRecipes();
        
        const applyFilters = () => this.loadRecipes();
        document.getElementById('searchInput').addEventListener('input', debounce(applyFilters, 500));
        document.getElementById('categorySelect').addEventListener('change', applyFilters);
        document.getElementById('calMin').addEventListener('input', debounce(applyFilters, 500));
        document.getElementById('calMax').addEventListener('input', debounce(applyFilters, 500));
        document.getElementById('timeMax').addEventListener('input', debounce(applyFilters, 500));
        document.getElementById('difficultySelect').addEventListener('change', applyFilters);
        
        if (window.AppState.isAuthenticated) {
            document.getElementById('showRecommendations').addEventListener('click', () => this.showRecommendations());
        }
    }
    
    static async loadCategories() {
        try {
            const cats = await apiRequest('/recipes/categories');
            const select = document.getElementById('categorySelect');
            cats.forEach(c => select.innerHTML += `<option value="${c.category_id}">${c.name}</option>`);
        } catch (err) { console.error(err); }
    }
    
    static async loadRecipes() {
        const params = new URLSearchParams();
        if (document.getElementById('searchInput')?.value) params.append('search', document.getElementById('searchInput').value);
        if (document.getElementById('categorySelect')?.value) params.append('category', document.getElementById('categorySelect').value);
        if (document.getElementById('calMin')?.value) params.append('cal_min', document.getElementById('calMin').value);
        if (document.getElementById('calMax')?.value) params.append('cal_max', document.getElementById('calMax').value);
        if (document.getElementById('timeMax')?.value) params.append('time_max', document.getElementById('timeMax').value);
        if (document.getElementById('difficultySelect')?.value) params.append('difficulty', document.getElementById('difficultySelect').value);
        
        try {
            const recipes = await apiRequest(`/recipes?${params.toString()}`);
            const grid = document.getElementById('recipesGrid');
            if (!recipes.length) { grid.innerHTML = '<div class="col-12 text-center py-5"><i class="bi bi-emoji-frown fs-1"></i><p>Рецептов не найдено</p></div>'; return; }
            
            grid.innerHTML = recipes.map(r => `
                <div class="col-md-4 col-sm-6">
                    <div class="card recipe-card h-100" data-id="${r.recipe_id}">
                        <img src="${r.image_url || 'https://placehold.co/400x300?text=Recipe'}" class="card-img-top" style="height: 180px; object-fit: cover;">
                        <div class="card-body">
                            <h5 class="card-title">${escapeHtml(r.name)}</h5>
                            <p><i class="bi bi-fire"></i> ${r.calories_per_serving} ккал • <i class="bi bi-clock"></i> ${r.cooking_time} мин<br><span class="difficulty-badge">${'★'.repeat(r.difficulty)}</span> ${r.is_favorite ? ' ❤️' : ''}</p>
                        </div>
                    </div>
                </div>
            `).join('');
            document.querySelectorAll('.recipe-card').forEach(card => card.addEventListener('click', () => this.showRecipeDetails(card.dataset.id)));
        } catch (err) { console.error(err); }
    }
    
    static async showRecipeDetails(id) {
        try {
            const recipe = await apiRequest(`/recipes/${id}`);
            const modalContent = document.getElementById('recipeModalContent');
            
            const stepByStepBtn = recipe.instructions && recipe.instructions.trim()
                ? `<button class="btn btn-outline-success" id="stepByStepBtn"><i class="bi bi-list-ol"></i> Пошаговый режим</button>`
                : '';
            
            modalContent.innerHTML = `
                <div class="modal-header">
                    <h5>${escapeHtml(recipe.name)}</h5>
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
                    ${window.AppState.isAuthenticated ? 
                        `<button class="btn ${recipe.is_favorite ? 'btn-danger' : 'btn-outline-primary'}" id="favoriteBtn">
                            ${recipe.is_favorite ? 'Удалить из избранного' : 'В избранное'}
                        </button>` : ''}
                    ${stepByStepBtn}
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                </div>
            `;
            
            if (window.AppState.isAuthenticated) {
                document.getElementById('favoriteBtn').addEventListener('click', async () => {
                    if (recipe.is_favorite) await apiRequest(`/favorites/remove/${id}`, 'DELETE');
                    else await apiRequest(`/favorites/add/${id}`, 'POST');
                    bootstrap.Modal.getInstance(document.getElementById('recipeModal')).hide();
                    this.loadRecipes();
                });
            }
            
            const stepBtn = document.getElementById('stepByStepBtn');
            if (stepBtn) {
                stepBtn.addEventListener('click', () => {
                    const steps = recipe.instructions.split(/\n/).filter(s => s.trim());
                    let current = 0;
                    const showStep = () => {
                        if (current >= steps.length) {
                            alert('Приятного аппетита!');
                            return;
                        }
                        alert(`Шаг ${current+1} из ${steps.length}\n\n${steps[current]}`);
                        current++;
                        setTimeout(showStep, 3000);
                    };
                    showStep();
                });
            }
            
            new bootstrap.Modal(document.getElementById('recipeModal')).show();
        } catch (err) { showToast(err.message, 'danger'); }
    }
    
    static async showRecommendations() {
        try {
            const recs = await apiRequest('/recipes/recommendations');
            const modalContent = document.getElementById('recipeModalContent');
            modalContent.innerHTML = `
                <div class="modal-header"><h5><i class="bi bi-stars text-warning"></i> Рекомендации</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body"><div class="row">${recs.map(r => `<div class="col-md-6 mb-2"><div class="card"><div class="card-body"><h6>${escapeHtml(r.name)}</h6><p>${r.calories_per_serving} ккал • ${r.cooking_time} мин</p><button class="btn btn-sm btn-primary view-rec" data-id="${r.recipe_id}">Подробнее</button></div></div></div>`).join('')}</div></div>
            `;
            document.querySelectorAll('.view-rec').forEach(btn => btn.addEventListener('click', () => this.showRecipeDetails(btn.dataset.id)));
            new bootstrap.Modal(document.getElementById('recipeModal')).show();
        } catch (err) { showToast(err.message, 'danger'); }
    }
}

function escapeHtml(str) { if (!str) return ''; return str.replace(/[&<>]/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[m])); }
function debounce(fn, delay) { let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn.apply(this, args), delay); }; }