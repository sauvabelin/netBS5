import { Controller } from '@hotwired/stimulus';

/*
 * Client-side paginated + text-filtered list controller.
 *
 * Used by NetBSRenderer, which renders ALL matching rows server-side in one shot.
 * This controller then shows/hides rows based on the active page, page size, and
 * search query — no server round-trips after initial render.
 *
 * Share the same target names as ajax_list_controller so the Twig UI chrome in
 * _list_body.twig binds identically by just swapping controllerName.
 */
export default class extends Controller {
    static targets = ['amount', 'search', 'tbody', 'info', 'pagination'];

    connect() {
        this.rows = Array.from(this.tbodyTarget.querySelectorAll('tr'));
        this.rowTexts = this.rows.map((r) => r.textContent.toLowerCase());
        this.amount = this.hasAmountTarget ? parseInt(this.amountTarget.value, 10) || 10 : 10;
        this.page = 0;
        this.search = '';
        this._render();
    }

    disconnect() {
        clearTimeout(this._searchTimer);
    }

    searchChanged(event) {
        clearTimeout(this._searchTimer);
        const q = event.target.value.trim().toLowerCase();
        this._searchTimer = setTimeout(() => {
            this.search = q;
            this.page = 0;
            this._render();
        }, 150);
    }

    changeAmount(event) {
        this.amount = parseInt(event.target.value, 10) || 10;
        this.page = 0;
        this._render();
    }

    /**
     * Returns the ids of all rows matching the active search filter,
     * regardless of which page they're currently on. Used by bulk actions
     * when nothing is explicitly checked ("apply to the whole filtered set").
     */
    getFilteredIds() {
        const terms = this.search ? this.search.split(/\s+/).filter(Boolean) : [];
        const ids = [];
        for (let i = 0; i < this.rows.length; i++) {
            if (terms.length > 0 && !terms.every((t) => this.rowTexts[i].includes(t))) continue;
            const id = parseInt(this.rows[i].dataset.rowId, 10);
            if (!Number.isNaN(id)) ids.push(id);
        }
        return ids;
    }

    goToPage(event) {
        event.preventDefault();
        const page = parseInt(event.currentTarget.dataset.page, 10);
        if (Number.isNaN(page)) return;
        this.page = page;
        this._render();
    }

    _render() {
        const terms = this.search ? this.search.split(/\s+/).filter(Boolean) : [];
        const matched = [];
        for (let i = 0; i < this.rows.length; i++) {
            if (terms.length === 0 || terms.every((t) => this.rowTexts[i].includes(t))) {
                matched.push(i);
            }
        }

        const total = matched.length;
        const totalPages = this.amount > 0 ? Math.max(0, Math.ceil(total / this.amount) - 1) : 0;
        if (this.page > totalPages) this.page = totalPages;
        if (this.page < 0) this.page = 0;

        const start = this.page * this.amount;
        const end = start + this.amount;
        const visible = new Set(matched.slice(start, end));

        for (let i = 0; i < this.rows.length; i++) {
            this.rows[i].hidden = !visible.has(i);
        }

        if (this.hasInfoTarget) {
            const firstItem = total === 0 ? 0 : start + 1;
            const lastItem = Math.min(end, total);
            this.infoTarget.textContent = `Showing ${firstItem} to ${lastItem} of ${total} entries`;
        }

        if (this.hasPaginationTarget) {
            this.paginationTarget.innerHTML = this._paginationHtml(totalPages);
        }
    }

    _paginationHtml(totalPages) {
        const page = this.page;
        const parts = [];

        parts.push(this._pageLi('Previous', page - 1, page === 0, false, 'previous'));

        let numbers;
        if (totalPages < 5) {
            numbers = [];
            for (let i = 0; i <= totalPages; i++) numbers.push(i);
        } else if (page === 0 || page === 1) {
            numbers = [0, 1, 2, totalPages];
        } else if (page === totalPages || page === totalPages - 1) {
            numbers = [totalPages - 3, totalPages - 2, totalPages - 1, totalPages];
        } else {
            numbers = [0, page - 1, page, page + 1, totalPages];
        }

        for (const i of numbers) {
            parts.push(this._pageLi(String(i + 1), i, false, i === page, ''));
        }

        parts.push(this._pageLi('Next', page + 1, page >= totalPages, false, 'next'));

        return parts.join('');
    }

    _pageLi(label, target, disabled, active, extra) {
        const classes = ['paginate_button', 'page-item'];
        if (extra) classes.push(extra);
        if (disabled) classes.push('disabled');
        if (active) classes.push('active');
        return `<li class="${classes.join(' ')}">`
            + `<a href="#" class="page-link" data-action="click->client-list#goToPage" data-page="${target}">${label}</a>`
            + `</li>`;
    }
}
