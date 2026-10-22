import { apiRequest, showToast } from '../api.js';

export class Products {
    static async render() {
        const app = document.getElementById('app');
        app.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-4"><h3><i class="bi bi-box-seam"></i> Мои продукты</h3><button class="btn btn-primary" id="addProductBtn"><i class="bi bi-plus-lg"></i> Добавить продукт</button></div>
            <div class="mb-3"><label>Примерные продукты:</label><div id="suggestedProducts" class="d-flex flex-wrap gap-1"></div></div>
            <div id="productsList"></div>
            <div class="modal fade" id="addProductModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5>Добавить продукт</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><div class="mb-3"><label>Ингредиент</label><input type="text" id="productSearch" class="form-control" placeholder="Начните вводить..."><div id="searchResults" class="mt-2"></div></div>
            <div class="row g-3"><div class="col-6"><label>Количество</label><input type="number" id="productQuantity" class="form-control" step="any" value="100"></div><div class="col-6"><label>Единица</label><select id="productUnit" class="form-select"><option value="г">г</option><option value="кг">кг</option><option value="мл">мл</option><option value="л">л</option><option value="шт">шт</option></select></div></div></div>
            <div class="modal-footer"><button class="btn btn-primary" id="confirmAddBtn" disabled>Добавить</button><button class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button></div></div></div></div>
        `;
        await this.loadSuggestedProducts();
        await this.loadProducts();
        
        let selectedIngredient = null;
        const searchInput = document.getElementById('productSearch');
        const searchResults = document.getElementById('searchResults');
        const confirmBtn = document.getElementById('confirmAddBtn');
        
        searchInput.addEventListener('input', debounce(async () => {
            const query = searchInput.value.trim();
            if (query.length < 2) { searchResults.innerHTML = ''; return; }
            const ingredients = await apiRequest(`/ingredients/search?q=${encodeURIComponent(query)}`);
            searchResults.innerHTML = ingredients.map(i => `<div class="list-group-item list-group-item-action ingredient-result" data-id="${i.ingredient_id}" data-name="${i.name}">${this.escapeHtml(i.name)}</div>`).join('');
            document.querySelectorAll('.ingredient-result').forEach(el => el.addEventListener('click', () => { selectedIngredient = { id: el.dataset.id, name: el.dataset.name }; searchInput.value = el.dataset.name; searchResults.innerHTML = ''; confirmBtn.disabled = false; }));
        }, 300));
        
        document.getElementById('addProductBtn').addEventListener('click', () => { selectedIngredient = null; searchInput.value = ''; document.getElementById('productQuantity').value = 100; confirmBtn.disabled = true; new bootstrap.Modal(document.getElementById('addProductModal')).show(); });
        confirmBtn.addEventListener('click', async () => {
            if (!selectedIngredient) { showToast('Выберите ингредиент', 'danger'); return; }
            const quantity = parseFloat(document.getElementById('productQuantity').value);
            const unit = document.getElementById('productUnit').value;
            if (isNaN(quantity) || quantity <= 0) { showToast('Введите корректное количество', 'danger'); return; }
            await apiRequest('/user/products', 'POST', { ingredient_id: selectedIngredient.id, quantity, unit });
            showToast('Продукт добавлен', 'success');
            bootstrap.Modal.getInstance(document.getElementById('addProductModal')).hide();
            await this.loadProducts();
        });
    }
    
    static async loadSuggestedProducts() {
        try {
            const suggested = await apiRequest('/ingredients/suggested');
            const container = document.getElementById('suggestedProducts');
            container.innerHTML = suggested.map(p => `
                <button class="btn btn-sm btn-outline-secondary add-suggested" data-id="${p.ingredient_id}" data-name="${p.name}">+ ${this.escapeHtml(p.name)}</button>
            `).join('');
            document.querySelectorAll('.add-suggested').forEach(btn => {
                btn.addEventListener('click', async () => {
                    await apiRequest('/user/products', 'POST', { ingredient_id: btn.dataset.id, quantity: 100, unit: 'г' });
                    await this.loadProducts();
                });
            });
        } catch (err) { console.error(err); }
    }
    
    static async loadProducts() {
        const products = await apiRequest('/user/products');
        const container = document.getElementById('productsList');
        if (!products.length) { container.innerHTML = `<div class="card text-center p-5"><i class="bi bi-box-seam fs-1 text-secondary"></i><h5>Нет продуктов</h5><button class="btn btn-primary mt-3" id="emptyAddBtn">Добавить первый</button></div>`; document.getElementById('emptyAddBtn')?.addEventListener('click', () => document.getElementById('addProductBtn').click()); return; }
        container.innerHTML = `
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Продукт</th><th>Категория</th><th>Количество</th><th>Ед.</th><th></th></tr></thead>
                    <tbody>
                        ${products.map(p => `
                            <tr>
                                <td>${this.escapeHtml(p.name)}</td>
                                <td>${p.category || '-'}</td>
                                <td><input type="number" class="form-control form-control-sm quantity-edit" data-id="${p.stock_id}" value="${p.quantity}" step="any" style="width:100px"></td>
                                <td>${p.unit}</td>
                                <td><button class="btn btn-sm btn-outline-danger delete-product" data-id="${p.stock_id}"><i class="bi bi-trash"></i></button></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;

        document.querySelectorAll('.quantity-edit').forEach(input => {
            input.addEventListener('change', async (e) => {
                const newQty = parseFloat(e.target.value);
                if (isNaN(newQty) || newQty < 0) return;
                await apiRequest(`/user/products/${input.dataset.id}`, 'PUT', { quantity: newQty, unit: 'г' }); // unit можно не менять
                showToast('Количество обновлено', 'success');
            });
        });
        document.querySelectorAll('.delete-product').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (confirm('Удалить продукт?')) {
                    await apiRequest(`/user/products/${btn.dataset.id}`, 'DELETE');
                    await this.loadProducts();
                }
            });
        });
    }
    
    static escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[m]));
    }
}

function debounce(fn, delay) { let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn.apply(this, args), delay); }; }