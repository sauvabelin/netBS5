import { Controller } from '@hotwired/stimulus';
import { esc } from '../lib/ajax_search.js';

export default class extends Controller {
    static values = { url: String };
    static targets = ['input'];

    connect() {
        this._timeout = null;
        this._popover = null;
        this._onOutsideClick = this._handleOutsideClick.bind(this);
    }

    disconnect() {
        this._destroyPopover();
        clearTimeout(this._timeout);
    }

    search() {
        clearTimeout(this._timeout);
        const query = this.inputTarget.value.trim();
        if (!query) {
            this._destroyPopover();
            return;
        }
        this._timeout = setTimeout(() => this._doSearch(query), 300);
    }

    focusInput() {
        const query = this.inputTarget.value.trim();
        if (query) this._doSearch(query);
    }

    _doSearch(query) {
        fetch(`${this.urlValue}?query=${encodeURIComponent(query)}`)
            .then((r) => r.json())
            .then((results) => this._showResults(results));
    }

    _showResults(results) {
        this._destroyPopover();

        const content = this._buildResultsHtml(results);

        this._popover = new bootstrap.Popover(this.inputTarget, {
            html: true,
            placement: 'bottom',
            content: content,
            trigger: 'manual',
            sanitize: false,
            template: '<div class="popover" role="tooltip"><div class="popover-arrow"></div><div class="popover-body qs-popover p-0"></div></div>',
        });
        this._popover.show();

        document.addEventListener('click', this._onOutsideClick, true);
    }

    _buildResultsHtml(results) {
        if (results.length === 0) {
            return '<div class="qs-content"><p class="m-0 p-3">Aucun résultat</p></div>';
        }
        return '<div class="qs-content">' +
            results.map((r) =>
                `<a class="qs-entry" href="${this._esc(r.path)}">` +
                `<p class="qs-name">${this._esc(r.name)}</p>` +
                `<p class="qs-description">${this._esc(r.description)}</p></a>`
            ).join('') + '</div>';
    }

    _handleOutsideClick(e) {
        if (!this._popover) return;
        const tip = this._popover.tip;
        if (tip && tip.contains(e.target)) return;
        if (this.inputTarget.contains(e.target)) return;
        this._destroyPopover();
    }

    _destroyPopover() {
        if (this._popover) {
            this._popover.dispose();
            this._popover = null;
        }
        document.removeEventListener('click', this._onOutsideClick, true);
    }

    _esc(str) {
        return esc(str);
    }
}
