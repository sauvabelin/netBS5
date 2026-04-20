import { Controller } from '@hotwired/stimulus';

/*
 * Auto-shows Bootstrap 5 toast elements on connect.
 * Each toast is rendered server-side in Twig as BS5 markup.
 * Add data-turbo-temporary to prevent Turbo Drive from caching toasts.
 */
export default class extends Controller {
    static targets = ['item'];

    connect() {
        this.itemTargets.forEach((el) => {
            const toast = new bootstrap.Toast(el, { delay: 5000 });
            toast.show();
            el.addEventListener('hidden.bs.toast', () => el.remove());
        });
    }
}
