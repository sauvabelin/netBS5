import { Controller } from '@hotwired/stimulus';
import { fetchResults, renderDropdownItems, wireSearchInput } from '../lib/ajax_search.js';

export default class extends Controller {
    static values = {
        ajaxClass: { type: String, default: '' },
        nullOption: { type: String, default: '0' },
        url: { type: String, default: '' },
    };

    connect() {
        const select = this.element.tagName === 'SELECT' ? this.element : this.element.querySelector('select');
        if (!select) return;

        this._select = select;

        if (this.ajaxClassValue) {
            this._initAjaxMode(select);
        } else {
            this._initStaticMode(select);
        }
    }

    disconnect() {
        if (this._outsideClickHandler) {
            document.removeEventListener('click', this._outsideClickHandler, true);
            this._outsideClickHandler = null;
        }
        if (this._wrapper) {
            if (this._wrapper.parentNode && this._select) {
                this._wrapper.parentNode.insertBefore(this._select, this._wrapper);
            }
            this._wrapper.remove();
            this._wrapper = null;
        }
        if (this._select) {
            this._select.style.display = '';
        }
    }

    _initStaticMode(select) {
        if (select.options.length <= 8) return;
        this._wrapWithSearch(select);
    }

    _initAjaxMode(select) {
        this._buildAjaxWidget(select);
    }

    _wrapWithSearch(select) {
        this._wrapper = document.createElement('div');
        this._wrapper.className = 'select2-search-wrapper';

        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.className = 'form-control form-control-sm mb-1';
        searchInput.placeholder = 'Rechercher...';
        searchInput.autocomplete = 'off';

        select.parentNode.insertBefore(this._wrapper, select);
        this._wrapper.appendChild(searchInput);
        this._wrapper.appendChild(select);

        searchInput.addEventListener('input', () => {
            const query = searchInput.value.toLowerCase();
            Array.from(select.options).forEach((opt) => {
                opt.hidden = query !== '' && !opt.text.toLowerCase().includes(query);
            });
        });
    }

    _buildAjaxWidget(select) {
        select.style.display = 'none';

        this._wrapper = document.createElement('div');
        this._wrapper.className = 'select2-ajax-wrapper position-relative';

        const display = document.createElement('div');
        display.className = 'form-control form-control-sm d-flex align-items-center';
        display.style.cursor = 'pointer';
        display.style.minHeight = '2rem';
        display.innerHTML = this._getDisplayText(select);

        const dropdown = document.createElement('div');
        dropdown.style.display = 'none';

        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.className = 'form-control form-control-sm mt-1';
        searchInput.placeholder = 'Rechercher...';
        searchInput.autocomplete = 'off';

        const results = document.createElement('div');
        results.className = 'list-group';
        results.style.cssText = 'position:absolute;z-index:1070;width:100%;max-height:200px;overflow-y:auto;box-shadow:0 2px 8px rgba(0,0,0,.15);';

        dropdown.appendChild(searchInput);
        dropdown.appendChild(results);
        this._wrapper.appendChild(display);
        this._wrapper.appendChild(dropdown);
        select.parentNode.insertBefore(this._wrapper, select.nextSibling);

        // Single click listener on results (not inside _doAjaxSearch to avoid duplication)
        results.addEventListener('click', (e) => {
            e.preventDefault();
            const el = e.target.closest('[data-id]');
            if (!el) return;
            this._selectOption(select, el.dataset.id, el.dataset.text);
            display.innerHTML = this._esc(el.dataset.text);
            dropdown.style.display = 'none';
        });

        display.addEventListener('click', () => {
            const isOpen = dropdown.style.display !== 'none';
            dropdown.style.display = isOpen ? 'none' : 'block';
            if (!isOpen) {
                searchInput.value = '';
                searchInput.focus();
                this._doAjaxSearch('', results);
            }
        });

        wireSearchInput(searchInput, results, (query) => {
            this._doAjaxSearch(query, results);
        });

        this._outsideClickHandler = (e) => {
            if (this._wrapper && !this._wrapper.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        };
        document.addEventListener('click', this._outsideClickHandler, true);
    }

    _doAjaxSearch(query, resultsEl) {
        fetchResults(this.urlValue, this.ajaxClassValue, query, this.nullOptionValue)
            .then((items) => {
                renderDropdownItems(items, resultsEl);
            });
    }

    _selectOption(select, id, text) {
        Array.from(select.options).forEach((opt) => { opt.selected = false; });
        let option = Array.from(select.options).find((opt) => String(opt.value) === String(id));
        if (!option) {
            option = new Option(text, id, true, true);
            select.appendChild(option);
        }
        option.selected = true;
        select.dispatchEvent(new Event('change'));
    }

    _getDisplayText(select) {
        const selected = Array.from(select.selectedOptions);
        if (selected.length === 0 || !selected[0].value) return '<span class="text-muted">Aucun</span>';
        return this._esc(selected.map((o) => o.text).join(', '));
    }

    _esc(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
}
