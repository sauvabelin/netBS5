import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        targetId: String,
    };

    toggle() {
        this.element.closest('tr').classList.toggle('bg-primary');
        const target = document.getElementById('togglable-' + this.targetIdValue);
        if (target) target.classList.toggle('active');
    }
}
