import { Controller } from '@hotwired/stimulus';
import { showToast } from '../lib/toast.js';
import { createModal, closeModal } from '../lib/modal_dom.js';
import { submitForm, parseResponse } from '../lib/modal_form.js';
import * as Turbo from '@hotwired/turbo';

export default class extends Controller {
    static values = {
        addUrl: String,
        createUrl: String,
        itemsClass: String,
        idsSource: { type: String, default: '' },
        staticIds: { type: Array, default: [] },
    };

    addToList(event) {
        event.preventDefault();
        const el = event.currentTarget;
        const listId = el.dataset.listId;
        const listClass = el.dataset.listClass || this.itemsClassValue;
        const ids = this._resolveIds();

        this._postAddToList(listId, listClass, ids);
    }

    createList(event) {
        event.preventDefault();
        const itemsClass = this.itemsClassValue;
        const ids = this._resolveIds();

        const formData = new FormData();
        formData.append('itemClass', itemsClass);

        fetch(this.createUrlValue, { method: 'POST', body: formData })
            .then((response) => {
                if (response.status === 202) {
                    return response.json().then((data) => {
                        if (data.listId) {
                            this._postAddToList(data.listId, itemsClass, ids);
                        }
                    });
                }
                return response.text().then((html) => {
                    const modalEl = createModal(html);
                    this._attachCreateFormHandler(modalEl, itemsClass, ids);
                });
            });
    }

    _attachCreateFormHandler(modalEl, itemsClass, ids) {
        const confirmBtn = modalEl.querySelector('[data-modal-validate]');
        const form = modalEl.querySelector('form');
        if (!confirmBtn || !form) return;

        confirmBtn.addEventListener('click', () => {
            submitForm(this.createUrlValue, form).then((result) => {
                if (result.action === 'toast') {
                    closeModal(modalEl);
                    if (result.listId) {
                        this._postAddToList(result.listId, itemsClass, ids);
                    }
                } else if (result.action === 'reload') {
                    Turbo.visit(window.location.href, { action: 'replace' });
                } else if (result.action === 'validation_error') {
                    modalEl.innerHTML = result.html;
                    this._attachCreateFormHandler(modalEl, itemsClass, ids);
                }
            });
        });
    }

    _postAddToList(listId, itemsClass, ids) {
        const formData = new FormData();
        formData.append('itemsClass', itemsClass);
        formData.append('listId', listId);
        ids.forEach((id) => formData.append('selectedIds[]', id));

        fetch(this.addUrlValue, { method: 'POST', body: formData })
            .then((r) => r.json())
            .then((data) => showToast('success', 'Éléments ajoutés avec succès, la liste en contient désormais ' + data.count))
            .catch(() => showToast('error', 'Une erreur est survenue, essayez avec une autre liste'));
    }

    _resolveIds() {
        if (this.staticIdsValue.length > 0) return this.staticIdsValue;
        if (this.idsSourceValue) {
            const source = window[this.idsSourceValue];
            if (source && typeof source.getSelectedIds === 'function') {
                return source.getSelectedIds();
            }
        }
        return [];
    }
}
