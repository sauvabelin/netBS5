import { Controller } from '@hotwired/stimulus';

/*
 * Initializes Bootstrap 5 tooltips on elements with data-bs-toggle="tooltip".
 * Attach to a container element; it will initialize all tooltips within.
 * Re-initializes on Turbo frame loads automatically via Stimulus lifecycle.
 */
export default class extends Controller {
    connect() {
        this.tooltips = [];
        this.element.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
            this.tooltips.push(new bootstrap.Tooltip(el, { html: true, trigger: 'hover', container: 'body' }));
        });
    }

    disconnect() {
        this.tooltips.forEach((t) => t.dispose());
        this.tooltips = [];
    }
}
