/**
 * Custom Turbo confirm dialog using Bootstrap's Modal component.
 * Replaces the native browser confirm() for all data-turbo-confirm attributes.
 *
 * Register in app.js:
 *   import { confirmMethod } from './lib/turbo_confirm.js';
 *   Turbo.setConfirmMethod(confirmMethod);
 */
export function confirmMethod(message, element) {
    return new Promise((resolve) => {
        const el = document.createElement('div');
        el.className = 'modal fade';
        el.setAttribute('tabindex', '-1');
        el.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirmation</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="button" class="btn btn-danger">Confirmer</button>
                    </div>
                </div>
            </div>`;

        el.querySelector('.modal-body p').textContent = message;
        const confirmBtn = el.querySelector('.btn-danger');

        let confirmed = false;

        confirmBtn.addEventListener('click', () => {
            confirmed = true;
            modal.hide();
        });

        el.addEventListener('hidden.bs.modal', () => {
            modal.dispose();
            el.remove();
            resolve(confirmed);
        });

        const modal = new bootstrap.Modal(el);
        modal.show();
    });
}
