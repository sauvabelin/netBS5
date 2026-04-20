import { Controller } from '@hotwired/stimulus';
import { showToast } from '../lib/toast.js';
import { fetchResults, renderDropdownItems, wireSearchInput, esc } from '../lib/ajax_search.js';

/**
 * Replaces jQuery x-editable with a Bootstrap Popover-based inline editor.
 * Reads the same data attributes (data-pk, data-url, data-type, data-value,
 * data-name, data-source, data-title) and POSTs to the same XEditableController.
 */
export default class extends Controller {

    connect() {
        this._popover = null;
    }

    disconnect() {
        this._destroyPopover();
    }

    // --- Main action ---

    open(event) {
        event.preventDefault();
        if (this._popover) {
            this._destroyPopover();
            return;
        }
        this._showPopover();
    }

    // --- Popover lifecycle ---

    _showPopover() {
        const title = this.element.dataset.title || 'Edit';
        const html = this._buildFormHtml();

        this._popover = new bootstrap.Popover(this.element, {
            html: true,
            sanitize: false,
            placement: 'right',
            trigger: 'manual',
            title: title,
            content: html,
            container: 'body',
            customClass: 'editable-popover',
        });
        this._popover.show();

        const tip = this._popover.tip;
        tip.querySelector('.editable-save').addEventListener('click', () => this._save(tip));
        tip.querySelector('.editable-cancel').addEventListener('click', () => this._destroyPopover());

        if (tip.querySelector('.editable-search-input')) {
            this._wireAjaxSearch(tip);
        }

        const fpInput = tip.querySelector('.editable-flatpickr');
        if (fpInput) {
            this._initFlatpickr(fpInput).catch(() => {
                fpInput.placeholder = 'jj.mm.aaaa';
            });
        }

        const input = tip.querySelector('input:not([type="hidden"]), textarea, select');
        if (input) input.focus();

        setTimeout(() => {
            this._outsideClickHandler = (e) => {
                if (!tip.contains(e.target)
                    && !this.element.contains(e.target)
                    && !e.target.closest('.flatpickr-calendar')) {
                    this._destroyPopover();
                }
            };
            document.addEventListener('click', this._outsideClickHandler, true);
        }, 0);
    }

    _destroyPopover() {
        if (this._editableFp) {
            this._editableFp.destroy();
            this._editableFp = null;
        }
        if (this._popover) {
            this._popover.dispose();
            this._popover = null;
        }
        if (this._outsideClickHandler) {
            document.removeEventListener('click', this._outsideClickHandler, true);
            this._outsideClickHandler = null;
        }
    }

    // --- Build edit form HTML ---

    _buildFormHtml() {
        const type = this.element.dataset.type;
        const value = this.element.dataset.value || '';
        let inputHtml;

        switch (type) {
            case 'textarea':
                inputHtml = `<textarea class="form-control form-control-sm">${esc(value)}</textarea>`;
                break;

            case 'select':
                inputHtml = this._buildSelectHtml(value);
                break;

            case 'select2':
                inputHtml = this._isAjaxDropdown() ? this._buildAjaxDropdownHtml() : this._buildSelectHtml(value);
                break;

            case 'checklist':
                const checked = value === '1' ? 'checked' : '';
                inputHtml = `<div class="form-check">
                    <input type="checkbox" class="form-check-input" ${checked} value="1">
                    <label class="form-check-label">Oui</label>
                </div>`;
                break;

            case 'hochetdatepicker':
                inputHtml = `<input type="text" class="form-control form-control-sm editable-flatpickr" value="${esc(value)}">`;
                break;

            default: // text, number, etc.
                inputHtml = `<input type="text" class="form-control form-control-sm" value="${esc(value)}">`;
                break;
        }

        return `<div class="editable-popover-form">
            <div class="d-flex align-items-start gap-1">
                <div class="flex-grow-1">${inputHtml}</div>
                <button type="button" class="btn btn-sm btn-primary editable-save"><i class="fa fa-check"></i></button>
                <button type="button" class="btn btn-sm btn-secondary editable-cancel"><i class="fa fa-times"></i></button>
            </div>
        </div>`;
    }

    _buildAjaxDropdownHtml() {
        const value = this.element.dataset.value || '';
        return `<div class="editable-ajax-search" style="min-width:220px;position:relative;">
            <input type="text" class="form-control form-control-sm editable-search-input"
                   placeholder="Rechercher..." value="" autocomplete="off">
            <input type="hidden" class="editable-search-value" value="${esc(value)}">
            <div class="editable-search-results list-group"
                 style="position:absolute;top:100%;left:0;width:100%;z-index:1070;max-height:250px;overflow-y:auto;display:none;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.15);"></div>
        </div>`;
    }

    _buildSelectHtml(currentValue) {
        const source = this._parseSource();
        const options = source.map((item) => {
            const val = item.value !== undefined ? item.value : item.id;
            const label = item.text || item.label || val;
            const selected = String(val) === String(currentValue) ? 'selected' : '';
            return `<option value="${esc(val)}" ${selected}>${esc(label)}</option>`;
        }).join('');
        return `<select class="form-select form-select-sm">${options}</select>`;
    }

    // --- Save ---

    _save(tip) {
        if (this._saving) return;

        const type = this.element.dataset.type;
        let newValue;

        if (type === 'checklist') {
            const cb = tip.querySelector('input[type="checkbox"]');
            newValue = cb.checked ? ['1'] : [''];
        } else if (this._isAjaxDropdown()) {
            const hidden = tip.querySelector('.editable-search-value');
            newValue = hidden ? hidden.value : '';
        } else if (type === 'hochetdatepicker') {
            const input = tip.querySelector('.editable-flatpickr');
            newValue = input.value;
        } else {
            const input = tip.querySelector('input, textarea, select');
            newValue = input.value;
        }

        const formData = new FormData();
        formData.append('name', this.element.dataset.name);
        formData.append('pk', this.element.dataset.pk);

        if (Array.isArray(newValue)) {
            newValue.forEach((v) => formData.append('value[]', v));
        } else {
            formData.append('value', newValue);
        }

        this._saving = true;
        fetch(this.element.dataset.url, {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' },
        })
            .then((response) => {
                const ct = response.headers.get('content-type') || '';
                if (!ct.includes('application/json')) {
                    throw new Error('Erreur serveur (' + response.status + ')');
                }
                return response.json().then((data) => {
                    if (!response.ok) throw new Error(data.message || 'Erreur');
                    return data;
                });
            })
            .then((data) => {
                this._updateDisplayValue(type, newValue, data);
                this._destroyPopover();
                showToast('success', 'Valeur modifiée avec succès');
            })
            .catch((err) => {
                showToast('error', err.message || 'Erreur interne');
            })
            .finally(() => {
                this._saving = false;
            });
    }

    _updateDisplayValue(type, sentValue, responseData) {
        const emptyText = this.element.dataset.emptytext || 'Rien';

        if (type === 'checklist') {
            const checked = Array.isArray(sentValue) && sentValue.includes('1');
            this.element.textContent = checked ? 'Oui' : 'Non';
            this.element.dataset.value = checked ? '1' : '';
        } else if (type === 'select' || type === 'select2') {
            let label = responseData.newLabel || sentValue || emptyText;
            if (!this._isAjaxDropdown()) {
                const match = this._parseSource().find((o) => String(o.value ?? o.id) === String(sentValue));
                if (match) label = match.text || match.label;
            }
            this.element.textContent = label;
            this.element.dataset.value = sentValue || '';
        } else if (type === 'hochetdatepicker') {
            this.element.textContent = sentValue || emptyText;
            this.element.dataset.value = sentValue || '';
        } else {
            this.element.textContent = responseData.newLabel || sentValue || emptyText;
            this.element.dataset.value = sentValue || '';
        }
    }

    // --- AJAX search for entity fields ---

    _wireAjaxSearch(tip) {
        const searchInput = tip.querySelector('.editable-search-input');
        const hiddenInput = tip.querySelector('.editable-search-value');
        const results = tip.querySelector('.editable-search-results');
        const ajaxClass = this.element.dataset.ajaxClass;
        const nullOption = this.element.dataset.nullOption || '0';
        const url = this.element.dataset.editableSelect2UrlValue || '/netBS/netbs/select2/results';

        wireSearchInput(searchInput, results, (query) => {
            fetchResults(url, ajaxClass, query, nullOption).then((items) => {
                renderDropdownItems(items, results);
            });
        });

        results.addEventListener('click', (e) => {
            e.preventDefault();
            const item = e.target.closest('[data-id]');
            if (!item) return;
            hiddenInput.value = item.dataset.id;
            searchInput.value = item.dataset.text;
            results.style.display = 'none';
        });
    }

    // --- Flatpickr (lazy-loaded only when a date popover opens) ---

    async _initFlatpickr(input) {
        const [fp, defaults] = await Promise.all([
            import('flatpickr'),
            import('../lib/flatpickr_defaults.js'),
        ]);
        const format = this.element.dataset.format || 'd.m.Y';
        this._editableFp = fp.default(input, {
            ...defaults.flatpickrDefaults,
            dateFormat: format,
        });
    }

    // --- Helpers ---

    _parseSource() {
        try {
            const raw = this.element.dataset.source
                || this.element.dataset.originalSource
                || '[]';
            return JSON.parse(raw);
        } catch (e) {
            return [];
        }
    }

    _isAjaxDropdown() {
        return this.element.dataset.type === 'select2'
            && (this.element.classList.contains('ajax-editable') || !!this.element.dataset.ajaxClass);
    }

}
