import { apiRequest, showToast } from '../api.js';

export class Shopping {
    static async render() {
        const app = document.getElementById('app');
        app.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3><i class="bi bi-cart3"></i> Список покупок</h3>
                <div>
                    <button id="generateBtn" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Сгенерировать</button>
                    <button id="clearBtn" class="btn btn-outline-secondary ms-2"><i class="bi bi-trash"></i> Очистить</button>
                    <button class="btn btn-outline-info ms-2" onclick="window.open('/meal-planner/print_shopping.php', '_blank')"><i class="bi bi-printer"></i> Печать</button>
                </div>
            </div>
            <div id="shoppingGroups"></div>
        `;
        await this.loadList();
        document.getElementById('generateBtn').addEventListener('click', async () => { await apiRequest('/shopping-list/generate', 'POST'); showToast('Список сгенерирован', 'success'); await this.loadList(); });
        document.getElementById('clearBtn').addEventListener('click', async () => { if (confirm('Очистить список?')) { await apiRequest('/shopping-list/clear', 'DELETE'); await this.loadList(); } });
    }
    
    static async loadList() {
        const data = await apiRequest('/shopping-list');
        const groups = data.groups || {};
        const container = document.getElementById('shoppingGroups');
        if (Object.keys(groups).length === 0) { container.innerHTML = '<div class="card text-center p-5"><i class="bi bi-cart3 fs-1 text-secondary"></i><h5>Список пуст</h5></div>'; return; }
        let total = 0, purchased = 0, html = '';
        for (const cat in groups) {
            html += `<div class="shopping-group mb-4"><h5><i class="bi bi-tag"></i> ${cat.toUpperCase()}</h5><div class="list-group">`;
            for (const item of groups[cat]) { 
                total++; 
                if (item.is_purchased) purchased++; 
                html += `<label class="list-group-item d-flex justify-content-between">
                    <div>
                        <input type="checkbox" class="form-check-input me-2 purchase-check" data-id="${item.list_item_id}" ${item.is_purchased ? 'checked' : ''}>
                        <span class="${item.is_purchased ? 'text-decoration-line-through' : ''}">${this.escapeHtml(item.name)}</span>
                    </div>
                    <span class="badge bg-primary">${Math.round(item.quantity_required)} ${item.unit}</span>
                </label>`; 
            }
            html += `</div></div>`;
        }
        container.innerHTML = `<div class="card mb-3"><div class="card-body"><div class="d-flex justify-content-between"><span>${purchased} из ${total} куплено</span><div class="progress w-50"><div class="progress-bar bg-success" style="width: ${(purchased / total * 100)}%"></div></div></div></div></div>${html}`;
        document.querySelectorAll('.purchase-check').forEach(cb => cb.addEventListener('change', async (e) => { if (e.target.checked) { await apiRequest(`/shopping-list/${cb.dataset.id}/purchase`, 'PUT'); await this.loadList(); } }));
    }
    
    static escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[m]));
    }
}