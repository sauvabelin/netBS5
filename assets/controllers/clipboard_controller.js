import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        sourceSelector: String,
    };

    copy() {
        const source = document.querySelector(this.sourceSelectorValue);
        if (!source || !navigator.clipboard) return;

        navigator.clipboard.writeText(source.value).then(() => {
            const btn = this.element;
            const originalText = btn.textContent;
            btn.textContent = 'Copié !';
            btn.classList.add('btn-success');
            btn.classList.remove('btn-primary');
            setTimeout(() => {
                btn.textContent = originalText;
                btn.classList.add('btn-primary');
                btn.classList.remove('btn-success');
            }, 2000);
        });
    }
}
