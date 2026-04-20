import { Controller } from '@hotwired/stimulus';

/*
 * Toggles mobile menu visibility.
 */
export default class extends Controller {
    static targets = ['menu'];

    toggle() {
        this.menuTarget.classList.toggle('visible');
    }
}
