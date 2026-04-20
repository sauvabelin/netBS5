import { Controller } from '@hotwired/stimulus';
import { createModal, closeModal } from '../lib/modal_dom.js';
import { submitForm } from '../lib/modal_form.js';
import { showToast } from '../lib/toast.js';
import * as Turbo from '@hotwired/turbo';

export default class extends Controller {
    static values = {
        url: String,
        params: { type: Object, default: {} },
        idsSource: { type: String, default: '' },
        idField: { type: String, default: 'selectedIds' },
        reloadOnSuccess: { type: Boolean, default: false },
    };

    open(event) {
        event.preventDefault();
        const url = this.urlValue || this.element.dataset.modalUrl;
        const body = this._buildFormData();

        fetch(url, { method: 'POST', body })
            .then((response) => this._handleOpenResponse(response, url))
            .catch((err) => createModal(err.message || 'An error occurred'));
    }

    _buildFormData() {
        const formData = new FormData();
        const params = { ...this.paramsValue };

        if (this.idsSourceValue) {
            const source = window[this.idsSourceValue];
            if (source && typeof source.getSelectedIds === 'function') {
                params[this.idFieldValue] = source.getSelectedIds();
            }
        }

        for (const [key, value] of Object.entries(params)) {
            if (Array.isArray(value)) {
                value.forEach((v) => formData.append(key + '[]', v));
            } else {
                formData.append(key, value);
            }
        }

        return formData;
    }

    _handleOpenResponse(response, url) {
        if (response.status === 202) {
            return response.json().then((data) => showToast(data.type, data.message));
        }
        return response.text().then((html) => {
            const modalEl = createModal(html);
            if (modalEl) this._attachFormHandler(modalEl, url);
        });
    }

    _attachFormHandler(modalEl, url) {
        const confirmBtn = modalEl.querySelector('[data-modal-validate]');
        const form = modalEl.querySelector('form');
        if (!confirmBtn || !form) return;

        form.setAttribute('novalidate', 'novalidate');

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            confirmBtn.click();
        });

        confirmBtn.addEventListener('click', () => {
            submitForm(url, form).then((result) => {
                this._handleSubmitResult(result, modalEl, url);
            }).catch((err) => { if (err.message !== 'validation') throw err; });
        });
    }

    _handleSubmitResult(result, modalEl, url) {
        if (result.action === 'redirect') {
            Turbo.visit(result.location);
        } else if (result.action === 'reload') {
            Turbo.visit(window.location.href, { action: 'replace' });
        } else if (result.action === 'toast') {
            showToast(result.type, result.message);
            closeModal(modalEl);
            this.element.dispatchEvent(new CustomEvent('modal:submit-success', { detail: result, bubbles: true }));
            if (this.reloadOnSuccessValue) Turbo.visit(window.location.href, { action: 'replace' });
        } else if (result.action === 'validation_error') {
            modalEl.innerHTML = result.html;
            this._attachFormHandler(modalEl, url);
        }
    }
}
