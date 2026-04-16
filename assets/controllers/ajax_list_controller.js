import { Controller } from '@hotwired/stimulus';
import { showToast } from '../lib/toast.js';

/**
 * Drives ajax-list pagination, search, and amount changes via Turbo Frames.
 *
 * The controller lives on the <turbo-frame> element and navigates the frame
 * by setting its `src` attribute to the new URL. After each frame load,
 * it re-initializes jQuery plugins (x-editable, modals) via the
 * turbo:frame-load event.
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
        select2Url: { type: String, default: '' },
    };

    connect() {
        this._searchTimer = null;
        this._searchFocused = false;
        this._searchValue = '';

        // Listen for turbo:frame-load to re-init jQuery plugins after content replacement
        this._onFrameLoad = this._afterFrameLoad.bind(this);
        this.element.addEventListener('turbo:frame-load', this._onFrameLoad);

        // Also init plugins on first connect (initial server-rendered content)
        // Use requestAnimationFrame to ensure DOM is settled
        requestAnimationFrame(() => this._initPlugins());
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
        // Reset to page 0 when amount changes
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
            // Save search state so we can restore focus after frame load
            if (this.hasSearchTarget) {
                this._searchFocused = document.activeElement === this.searchTarget;
                this._searchValue = this.searchTarget.value;
            }
            // Reset to page 0 on new search
            this._navigate({ page: 0 });
        }, 500);
    }

    /**
     * Called after turbo:frame-load. Re-inits plugins and restores search focus.
     */
    _afterFrameLoad() {
        this._initPlugins();

        // Restore search input focus and cursor position after frame replacement
        if (this._searchFocused && this.hasSearchTarget) {
            const input = this.searchTarget;
            input.value = this._searchValue;
            input.focus();
            // Place cursor at end
            input.setSelectionRange(input.value.length, input.value.length);
        }
        this._searchFocused = false;
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

        // Pass model parameters as JSON
        if (this.paramsValue && Object.keys(this.paramsValue).length > 0) {
            url.searchParams.set('params', JSON.stringify(this.paramsValue));
        }

        // Navigate the turbo-frame by setting its src
        this.element.src = url.toString();
    }

    /**
     * Re-initialize jQuery plugins (x-editable, modals) inside the table.
     * Called after initial render and after each Turbo Frame content replacement.
     */
    _initPlugins() {
        if (typeof $ === 'undefined' || !$.fn || !$.fn.editable) return;

        const tableId = this.tableIdValue;
        const select2Url = this.select2UrlValue;
        const $table = $(`#${tableId}`);
        if (!$table.length) return;

        // x-editable: ajax-based select2 fields
        $table.find('.ajax-editable').each(function () {
            const $elem = $(this);
            const multiple = $elem.attr('data-multiple') === '1';
            const ajaxClass = $elem.attr('data-ajax-class');
            const nullOption = $elem.attr('data-null-option');
            const source = $elem.attr('data-original-source');

            $elem.editable({
                tpl: '<select style="width:300px;">',
                type: 'select2',
                placement: 'bottom',
                source: function () {
                    return JSON.parse(source);
                },
                error: function (response) {
                    if (response.status === 500) {
                        showToast('error',
                            'Erreur interne, veuillez contacter le chef comm'
                        );
                    } else {
                        let txt = response.responseText;
                        if (
                            response.responseJSON &&
                            response.responseJSON.hasOwnProperty('message')
                        )
                            txt = response.responseJSON.message;
                        showToast('warning', txt);
                    }
                },
                success: function () {
                    showToast('success', 'Valeur modifi\u00e9e avec succ\u00e8s. <a href="#" onclick="location.reload(); return false;" class="text-white text-decoration-underline">Actualiser</a>');
                },
                select2: {
                    multiple: multiple,
                    dropdownParent: $elem,
                    ajax: {
                        dataType: 'json',
                        url: select2Url,
                        delay: 350,
                        data: function (params) {
                            return {
                                ajaxClass: ajaxClass,
                                nullOption: nullOption,
                                query: params.term,
                            };
                        },
                    },
                },
            });
        });

        // x-editable: simple editable fields
        $table.find('.editable').editable({
            select2: { width: 250 },
            error: function (response) {
                if (response.status === 500) {
                    showToast('error',
                        'Erreur interne, veuillez contacter le chef comm'
                    );
                } else {
                    let txt = response.responseText;
                    if (
                        response.responseJSON &&
                        response.responseJSON.hasOwnProperty('message')
                    )
                        txt = response.responseJSON.message;
                    showToast('warning', txt);
                }
            },
            success: function () {
                showToast('success', 'Valeur modifiee avec succes');
            },
        });

    }
}
