import { Controller } from '@hotwired/stimulus';
import { createModal, closeModal } from '../lib/modal_dom.js';
import { submitForm } from '../lib/modal_form.js';
import { showToast } from '../lib/toast.js';

export default class extends Controller {
    static values = { url: String };

    open(event) {
        event.preventDefault();
        const url = this.urlValue || this.element.dataset.modalUrl;

        fetch(url, { method: 'POST' })
            .then((response) => this.handleOpenResponse(response, url))
            .catch((err) => createModal(err.message || 'An error occurred'));
    }

    handleOpenResponse(response, url) {
        if (response.status === 202) {
            return response.json().then((data) => showToast(data.type, data.message));
        }
        return response.text().then((html) => {
            const modalEl = createModal(html);
            this.attachFormHandler(modalEl, url);
        });
    }

    attachFormHandler(modalEl, url) {
        const confirmBtn = modalEl.querySelector('[data-modal-validate]');
        const form = modalEl.querySelector('form');
        if (!confirmBtn || !form) return;

        confirmBtn.addEventListener('click', () => {
            submitForm(url, form).then((result) => {
                this.handleSubmitResult(result, modalEl, url);
            });
        });
    }

    handleSubmitResult(result, modalEl, url) {
        if (result.action === 'redirect') {
            window.location.href = result.location;
        } else if (result.action === 'reload') {
            location.reload();
        } else if (result.action === 'toast') {
            showToast(result.type, result.message);
            closeModal(modalEl);
        } else if (result.action === 'validation_error') {
            modalEl.innerHTML = result.html;
            this.attachFormHandler(modalEl, url);
        }
    }
}
