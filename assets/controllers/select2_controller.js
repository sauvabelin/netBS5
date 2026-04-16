import { Controller } from '@hotwired/stimulus';
import { fetchResults, renderDropdownItems, wireSearchInput, esc } from '../lib/ajax_search.js';

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
        this._multiple = select.multiple;

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
        if (this._multiple) {
            this._buildMultiSelectWidget(select);
        } else if (select.options.length > 8) {
            this._wrapWithSearch(select);
        }
    }

    _initAjaxMode(select) {
        if (this._multiple) {
            this._buildAjaxMultiWidget(select);
        } else {
            this._buildAjaxWidget(select);
        }
    }

    // --- Static single-select with search filter ---

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

    // --- Static multi-select with tags ---

    _buildMultiSelectWidget(select) {
        const { tagsContainer, searchInput, results } = this._buildMultiScaffold(select, 'select2-multi-wrapper', 'max-height:200px;overflow-y:auto;display:none;');

        const renderOptions = () => {
            const query = searchInput.value.toLowerCase();
            const selectedValues = new Set(Array.from(select.selectedOptions).map((o) => o.value));
            const opts = Array.from(select.options).filter((opt) =>
                opt.value && !selectedValues.has(opt.value)
                && (query === '' || opt.text.toLowerCase().includes(query))
            );
            if (opts.length === 0) {
                results.innerHTML = '<span class="list-group-item py-1 px-2 text-muted">Aucun résultat</span>';
            } else {
                results.innerHTML = opts.map((opt) =>
                    `<a href="#" class="list-group-item list-group-item-action py-1 px-2" data-value="${esc(opt.value)}">${esc(opt.text)}</a>`
                ).join('');
            }
        };

        const refresh = () => {
            this._renderTags(select, tagsContainer, refresh);
            renderOptions();
        };

        results.addEventListener('click', (e) => {
            e.preventDefault();
            const el = e.target.closest('[data-value]');
            if (!el) return;
            const opt = Array.from(select.options).find((o) => o.value === el.dataset.value);
            if (opt) {
                opt.selected = true;
                select.dispatchEvent(new Event('change'));
                refresh();
                searchInput.focus();
            }
        });

        searchInput.addEventListener('focus', () => {
            results.style.display = 'block';
            renderOptions();
        });

        searchInput.addEventListener('input', () => {
            results.style.display = 'block';
            renderOptions();
        });

        this._renderTags(select, tagsContainer, refresh);
    }

    // --- AJAX single-select ---

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

        results.addEventListener('click', (e) => {
            e.preventDefault();
            const el = e.target.closest('[data-id]');
            if (!el) return;
            this._selectSingle(select, el.dataset.id, el.dataset.text);
            display.innerHTML = esc(el.dataset.text);
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

    // --- AJAX multi-select with tags ---

    _buildAjaxMultiWidget(select) {
        const { tagsContainer, searchInput, results } = this._buildMultiScaffold(select, 'select2-ajax-multi-wrapper position-relative', 'position:absolute;z-index:1070;width:100%;max-height:200px;overflow-y:auto;box-shadow:0 2px 8px rgba(0,0,0,.15);display:none;');

        const refresh = () => {
            this._renderTags(select, tagsContainer, refresh);
            if (results.style.display !== 'none') {
                this._doAjaxSearch(searchInput.value.trim(), results);
            }
        };

        results.addEventListener('click', (e) => {
            e.preventDefault();
            const el = e.target.closest('[data-id]');
            if (!el) return;
            this._addOption(select, el.dataset.id, el.dataset.text);
            refresh();
            searchInput.value = '';
            searchInput.focus();
            this._doAjaxSearch('', results);
        });

        searchInput.addEventListener('focus', () => {
            results.style.display = 'block';
            this._doAjaxSearch(searchInput.value.trim(), results);
        });

        wireSearchInput(searchInput, results, (query) => {
            results.style.display = 'block';
            this._doAjaxSearch(query, results);
        });

        this._renderTags(select, tagsContainer, refresh);
    }

    // --- Shared helpers ---

    _buildMultiScaffold(select, wrapperClass, resultsStyle) {
        select.style.display = 'none';

        this._wrapper = document.createElement('div');
        this._wrapper.className = wrapperClass;

        const tagsContainer = document.createElement('div');
        tagsContainer.className = 'd-flex flex-wrap gap-1 mb-1';

        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.className = 'form-control form-control-sm';
        searchInput.placeholder = 'Rechercher...';
        searchInput.autocomplete = 'off';

        const results = document.createElement('div');
        results.className = 'list-group';
        results.style.cssText = resultsStyle;

        this._wrapper.appendChild(tagsContainer);
        this._wrapper.appendChild(searchInput);
        this._wrapper.appendChild(results);
        select.parentNode.insertBefore(this._wrapper, select.nextSibling);

        this._outsideClickHandler = (e) => {
            if (this._wrapper && !this._wrapper.contains(e.target)) {
                results.style.display = 'none';
            }
        };
        document.addEventListener('click', this._outsideClickHandler, true);

        return { tagsContainer, searchInput, results };
    }

    _renderTags(select, tagsContainer, onRemove) {
        tagsContainer.innerHTML = '';
        Array.from(select.selectedOptions).forEach((opt) => {
            if (!opt.value) return;
            const tag = document.createElement('span');
            tag.className = 'badge bg-primary d-inline-flex align-items-center gap-1';
            tag.innerHTML = esc(opt.text) + '<button type="button" class="btn-close btn-close-white" style="font-size:.6em;" aria-label="Retirer"></button>';
            tag.querySelector('button').addEventListener('click', () => {
                opt.selected = false;
                select.dispatchEvent(new Event('change'));
                onRemove();
            });
            tagsContainer.appendChild(tag);
        });
    }

    _doAjaxSearch(query, resultsEl) {
        fetchResults(this.urlValue, this.ajaxClassValue, query, this.nullOptionValue)
            .then((items) => {
                renderDropdownItems(items, resultsEl);
            });
    }

    _selectSingle(select, id, text) {
        const opts = Array.from(select.options);
        opts.forEach((opt) => { opt.selected = false; });
        let option = opts.find((opt) => String(opt.value) === String(id));
        if (!option) {
            option = new Option(text, id, true, true);
            select.appendChild(option);
        }
        option.selected = true;
        select.dispatchEvent(new Event('change'));
    }

    _addOption(select, id, text) {
        let option = Array.from(select.options).find((opt) => String(opt.value) === String(id));
        if (!option) {
            option = new Option(text, id, false, true);
            select.appendChild(option);
        }
        option.selected = true;
        select.dispatchEvent(new Event('change'));
    }

    _getDisplayText(select) {
        const selected = Array.from(select.selectedOptions);
        if (selected.length === 0 || !selected[0].value) return '<span class="text-muted">Aucun</span>';
        return esc(selected.map((o) => o.text).join(', '));
    }
}
