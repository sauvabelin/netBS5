import { Controller } from '@hotwired/stimulus';
import { showToast } from '../lib/toast.js';

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

        // Ensure popover is wide enough for input + buttons
        this._popover.tip.style.maxWidth = 'none';
        this._popover.tip.style.minWidth = '300px';

        // Wire up buttons after popover is shown
        const tip = this._popover.tip;
        tip.querySelector('.editable-save').addEventListener('click', () => this._save(tip));
        tip.querySelector('.editable-cancel').addEventListener('click', () => this._destroyPopover());

        // Wire AJAX search for entity fields
        if (tip.querySelector('.editable-search-input')) {
            this._wireAjaxSearch(tip);
        }

        // Focus the input
        const input = tip.querySelector('input:not([type="hidden"]), textarea, select');
        if (input) input.focus();

        // Close on click outside
        setTimeout(() => {
            this._outsideClickHandler = (e) => {
                if (!tip.contains(e.target) && !this.element.contains(e.target)) {
                    this._destroyPopover();
                }
            };
            document.addEventListener('click', this._outsideClickHandler, true);
        }, 0);
    }

    _destroyPopover() {
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
                inputHtml = `<textarea class="form-control form-control-sm">${this._esc(value)}</textarea>`;
                break;

            case 'select':
                inputHtml = this._buildSelectHtml(value);
                break;

            case 'select2':
                if (this._isAjaxSelect()) {
                    inputHtml = this._buildAjaxSearchHtml(value);
                } else {
                    inputHtml = this._buildSelectHtml(value);
                }
                break;

            case 'checklist':
                const checked = value === '1' ? 'checked' : '';
                inputHtml = `<div class="form-check">
                    <input type="checkbox" class="form-check-input" ${checked} value="1">
                    <label class="form-check-label">Oui</label>
                </div>`;
                break;

            case 'hochetdatepicker':
                const isoVal = this._ddmmyyyyToIso(value);
                inputHtml = `<input type="date" class="form-control form-control-sm" value="${isoVal}">`;
                break;

            default: // text, number, etc.
                inputHtml = `<input type="text" class="form-control form-control-sm" value="${this._esc(value)}">`;
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

    _buildAjaxSearchHtml(currentValue) {
        const source = this._parseSource();
        const current = source.find((o) => String(o.id) === String(currentValue));
        const displayText = current ? current.text : 'Rien';

        return `<div class="editable-ajax-search" style="min-width:250px;position:relative;">
            <div class="mb-1 text-muted small">Actuel: <strong>${this._esc(displayText)}</strong></div>
            <input type="text" class="form-control form-control-sm editable-search-input"
                   placeholder="Rechercher..." value="" autocomplete="off">
            <input type="hidden" class="editable-search-value" value="${this._esc(currentValue)}">
            <div class="editable-search-results list-group"
                 style="position:absolute;z-index:1070;width:100%;max-height:200px;overflow-y:auto;display:none;box-shadow:0 2px 8px rgba(0,0,0,.15);"></div>
        </div>`;
    }

    _buildSelectHtml(currentValue) {
        const source = this._parseSource();
        const options = source.map((item) => {
            const val = item.value !== undefined ? item.value : item.id;
            const label = item.text || item.label || val;
            const selected = String(val) === String(currentValue) ? 'selected' : '';
            return `<option value="${this._esc(val)}" ${selected}>${this._esc(label)}</option>`;
        }).join('');
        return `<select class="form-select form-select-sm">${options}</select>`;
    }

    // --- Save ---

    _save(tip) {
        const type = this.element.dataset.type;
        let newValue;

        if (type === 'checklist') {
            const cb = tip.querySelector('input[type="checkbox"]');
            newValue = cb.checked ? ['1'] : [''];
        } else if (type === 'select2' && this._isAjaxSelect()) {
            const hidden = tip.querySelector('.editable-search-value');
            newValue = hidden ? hidden.value : '';
        } else if (type === 'hochetdatepicker') {
            const input = tip.querySelector('input');
            newValue = this._isoToDdmmyyyy(input.value);
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

        fetch(this.element.dataset.url, { method: 'POST', body: formData })
            .then((response) => {
                if (!response.ok) {
                    return response.json().then((data) => {
                        throw new Error(data.message || 'Erreur');
                    });
                }
                return response.json();
            })
            .then((data) => {
                this._updateDisplayValue(type, newValue, data);
                this._destroyPopover();
                showToast('success', 'Valeur modifiée avec succès');
            })
            .catch((err) => {
                showToast('error', err.message || 'Erreur interne');
            });
    }

    _updateDisplayValue(type, sentValue, responseData) {
        const emptyText = this.element.dataset.emptytext || 'Rien';

        if (type === 'checklist') {
            const checked = Array.isArray(sentValue) && sentValue.includes('1');
            this.element.textContent = checked ? 'Oui' : 'Non';
            this.element.dataset.value = checked ? '1' : '';
        } else if (type === 'select' || type === 'select2') {
            const source = this._parseSource();
            const match = source.find((o) => String(o.value ?? o.id) === String(sentValue));
            this.element.textContent = match ? (match.text || match.label) : (sentValue || emptyText);
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
        let timer = null;

        // Load all results on focus
        searchInput.addEventListener('focus', () => {
            doSearch(searchInput.value.trim());
        });

        const doSearch = (query) => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                const params = new URLSearchParams({ ajaxClass, nullOption, query });
                fetch(`${url}?${params}`)
                    .then((r) => r.json())
                    .then((data) => {
                        const items = data.results || data;
                        results.innerHTML = items.map((item) =>
                            `<a href="#" class="list-group-item list-group-item-action py-1 px-2"
                                data-id="${this._esc(item.id)}" data-text="${this._esc(item.text)}">
                                ${this._esc(item.text)}
                            </a>`
                        ).join('') || '<span class="list-group-item py-1 px-2 text-muted">Aucun résultat</span>';
                        results.style.display = 'block';
                    });
            }, 300);
        };

        searchInput.addEventListener('input', () => {
            doSearch(searchInput.value.trim());
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

    _isAjaxSelect() {
        return this.element.classList.contains('ajax-editable')
            || !!this.element.dataset.ajaxClass;
    }

    _esc(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    _ddmmyyyyToIso(val) {
        if (!val) return '';
        const parts = val.split('.');
        if (parts.length === 3) return `${parts[2]}-${parts[1]}-${parts[0]}`;
        return val;
    }

    _isoToDdmmyyyy(val) {
        if (!val) return '';
        const parts = val.split('-');
        if (parts.length === 3) return `${parts[2]}.${parts[1]}.${parts[0]}`;
        return val;
    }
}
