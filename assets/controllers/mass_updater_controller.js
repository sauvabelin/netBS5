import { Controller } from '@hotwired/stimulus';
import { showToast } from '../lib/toast.js';

export default class extends Controller {
    static values = {
        updaterId: String,
        skipFields: { type: Array, default: [] },
    };

    connect() {
        this._bindDeleteButtons();
        this._lockIdentityFields();
    }

    propagateAll() {
        const genericForm = this.element.querySelector('.page-aside form');
        if (!genericForm) {
            showToast('warning', 'Formulaire générique introuvable');
            return;
        }

        const updates = this._collectGenericValues(genericForm);
        if (updates.length === 0) {
            showToast('info', 'Aucun champ à appliquer — remplissez les champs à gauche d\'abord');
            return;
        }

        let rowCount = 0;
        const tableId = 'mass-items-table-' + this.updaterIdValue;
        const tbody = document.getElementById(tableId)?.querySelector('tbody');
        if (!tbody) return;

        tbody.querySelectorAll('tr').forEach((row) => {
            let updated = false;
            updates.forEach(({ fieldName, value, text }) => {
                const target = row.querySelector('[name$="][' + fieldName + ']"]');
                if (!target) return;

                if (target.tagName === 'SELECT') {
                    this._setSelectValue(target, value, text);
                } else {
                    target.value = value;
                }
                target.dispatchEvent(new Event('change', { bubbles: true }));
                updated = true;
            });
            if (updated) rowCount++;
        });

        showToast('success', rowCount + ' ligne(s) pré-remplie(s)');
    }

    addRow() {
        const tableId = 'mass-items-table-' + this.updaterIdValue;
        const protoId = 'mass-prototype-' + this.updaterIdValue;
        const tbody = document.getElementById(tableId).querySelector('tbody');
        const proto = document.getElementById(protoId);
        if (!proto) return;

        const row = proto.querySelector('tr').cloneNode(true);
        const count = tbody.querySelectorAll('tr').length;

        row.querySelectorAll('td').forEach((td) => {
            const sub = td.querySelector('input, select, textarea');
            if (!sub) return;
            const subId = sub.getAttribute('id');
            const subName = sub.getAttribute('name');
            if (subId) sub.setAttribute('id', subId.replace('__name__', count));
            if (subName) sub.setAttribute('name', subName.replace('__name__', count));
        });

        tbody.appendChild(row);
        this._bindDeleteButtons();
    }

    removeRow(event) {
        event.currentTarget.closest('tr').remove();
    }

    // --- Private ---

    _collectGenericValues(form) {
        const updates = [];
        form.querySelectorAll('[name]').forEach((el) => {
            if (!el.name) return;
            const nameParts = el.name.match(/\[(\w+)\]$/);
            if (!nameParts) return;
            const fieldName = nameParts[1];

            if (fieldName === '_token') return;
            if (this.skipFieldsValue.includes(fieldName)) return;
            if (!el.value) return;

            // Get display text for selects (needed for select2 visual update)
            let text = el.value;
            if (el.tagName === 'SELECT') {
                const selected = el.querySelector('option:checked');
                text = selected ? selected.text : el.value;
            }

            updates.push({ fieldName, value: el.value, text });
        });
        return updates;
    }

    _setSelectValue(target, value, text) {
        // Use select2 controller's public API if available
        const wrapper = target.closest('[data-controller~="select2"]');
        if (wrapper && window.Stimulus) {
            const ctrl = window.Stimulus.getControllerForElementAndIdentifier(wrapper, 'select2');
            if (ctrl && ctrl.setValue) {
                ctrl.setValue(value, text);
                return;
            }
        }

        // Fallback: set native select directly
        if (!target.querySelector('option[value="' + CSS.escape(value) + '"]')) {
            target.appendChild(new Option(text, value, true, true));
        }
        target.value = value;
    }

    _lockIdentityFields() {
        if (this.skipFieldsValue.length === 0) return;
        const tableId = 'mass-items-table-' + this.updaterIdValue;
        const table = document.getElementById(tableId);
        if (!table) return;

        this.skipFieldsValue.forEach((fieldName) => {
            table.querySelectorAll('[name$="][' + fieldName + ']"]').forEach((el) => {
                // Make the select2 wrapper non-interactive but keep the value submittable
                const wrapper = el.closest('[data-controller~="select2"]');
                if (wrapper) {
                    const visual = wrapper.querySelector('.select2-ajax-wrapper, .select2-multi-wrapper, .select2-search-wrapper');
                    if (visual) {
                        visual.style.pointerEvents = 'none';
                        visual.style.opacity = '0.6';
                    }
                }
                // For plain inputs, make readonly
                if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                    el.readOnly = true;
                    el.style.opacity = '0.6';
                }
            });
        });
    }

    _bindDeleteButtons() {
        const id = this.updaterIdValue;
        this.element.querySelectorAll('[data-delete-element-' + id + ']').forEach((btn) => {
            if (btn.dataset.bound) return;
            btn.dataset.bound = 'true';
            btn.addEventListener('click', (e) => e.currentTarget.closest('tr').remove());
        });
    }
}
