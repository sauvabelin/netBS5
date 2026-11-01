import { Controller } from '@hotwired/stimulus';

/* Inserts the chosen facture-text placeholder at the cursor of the input target. */
export default class extends Controller {
    static targets = ['input'];

    insert(event) {
        const value = event.target.value;
        if (!value || !this.hasInputTarget) return;

        const input = this.inputTarget;
        const start = input.selectionStart ?? input.value.length;
        const end = input.selectionEnd ?? input.value.length;
        input.value = input.value.slice(0, start) + value + input.value.slice(end);
        input.focus();
        input.setSelectionRange(start + value.length, start + value.length);
        event.target.value = '';
    }
}
