let nextId = 0;

export function createModal(content) {
    const el = document.createElement('div');
    el.id = 'dn_modal_' + (++nextId);
    el.className = 'modal fade netbs-modal';
    el.tabIndex = -1;
    el.setAttribute('aria-hidden', 'true');
    el.innerHTML = content;

    document.body.appendChild(el);
    const bsModal = new bootstrap.Modal(el);
    bsModal.show();

    // Initialize Select2 widgets inside the modal (if Select2Triggerer is available)
    if (typeof Select2Triggerer !== 'undefined') {
        const $el = $(el);
        Select2Triggerer.triggerSelect2($el.find('[data-type="select2"]'));
        Select2Triggerer.triggerAjaxSelect2($el.find('[data-type="ajax-select2"]'));
    }

    el.addEventListener('hidden.bs.modal', () => {
        bsModal.dispose();
        el.remove();
    });

    return el;
}

export function closeModal(el) {
    const instance = bootstrap.Modal.getInstance(el);
    if (instance) instance.hide();
}
