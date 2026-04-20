import { Controller } from '@hotwired/stimulus';
import { reinitCellPlugins } from '../utils/reinit_cell_plugins.js';

/**
 * Drives ajax-list pagination, search, and amount changes via Turbo Frames.
 *
 * The controller lives on the <turbo-frame> element and navigates the frame
 * by setting its `src` attribute to the new URL.
 *
 * Values:
 *   baseUrl  — the HTML endpoint URL (without query params)
 *   params   — JSON-encoded model parameters
 *   tableId  — the unique table identifier (passed to server for consistent IDs)
 *
 * Targets:
 *   search — the search <input>
 *   amount — the amount <select>
 */
export default class extends Controller {
    static targets = ['search', 'amount'];
    static values = {
        baseUrl: String,
        params: { type: Object, default: {} },
        tableId: String,
    };

    connect() {
        this._searchTimer = null;
        this._searchFocused = false;
        this._searchValue = '';

        this._onFrameLoad = this._afterFrameLoad.bind(this);
        this.element.addEventListener('turbo:frame-load', this._onFrameLoad);
    }

    disconnect() {
        if (this._searchTimer) {
            clearTimeout(this._searchTimer);
        }
        this.element.removeEventListener('turbo:frame-load', this._onFrameLoad);
    }

    /**
     * Called when a pagination link is clicked.
     * Reads the target page from data-page attribute.
     */
    goToPage(event) {
        event.preventDefault();
        const link = event.currentTarget;
        const li = link.closest('.page-item');

        // Don't navigate if disabled or already active
        if (li && li.classList.contains('disabled')) {
            return;
        }

        const page = parseInt(link.dataset.page, 10);
        if (isNaN(page) || page < 0) return;

        this._navigate({ page });
    }

    /**
     * Called when the amount <select> changes.
     */
    changeAmount() {
        this._navigate({ page: 0 });
    }

    /**
     * Called on search input. Debounces 500ms before navigating.
     */
    searchChanged() {
        if (this._searchTimer) {
            clearTimeout(this._searchTimer);
        }

        this._searchTimer = setTimeout(() => {
            this._searchTimer = null;
            if (this.hasSearchTarget) {
                this._searchFocused = document.activeElement === this.searchTarget;
                this._searchValue = this.searchTarget.value;
            }
            this._navigate({ page: 0 });
        }, 500);
    }

    _afterFrameLoad() {
        // Restore search input focus and cursor position after frame replacement
        if (this._searchFocused && this.hasSearchTarget) {
            const input = this.searchTarget;
            input.value = this._searchValue;
            input.focus();
            input.setSelectionRange(input.value.length, input.value.length);
        }
        this._searchFocused = false;

        reinitCellPlugins(this.element);
    }

    /**
     * Builds the URL and navigates the turbo-frame.
     */
    _navigate(overrides = {}) {
        const amount = this.hasAmountTarget
            ? this.amountTarget.value
            : '10';
        const search = this.hasSearchTarget
            ? this.searchTarget.value.trim()
            : '';
        const page = overrides.page !== undefined ? overrides.page : 0;

        const url = new URL(this.baseUrlValue, window.location.origin);
        url.searchParams.set('page', page);
        url.searchParams.set('amount', amount);
        url.searchParams.set('search', search);
        url.searchParams.set('tableId', this.tableIdValue);

        if (this.paramsValue && Object.keys(this.paramsValue).length > 0) {
            url.searchParams.set('params', JSON.stringify(this.paramsValue));
        }

        this.element.src = url.toString();
    }
}
