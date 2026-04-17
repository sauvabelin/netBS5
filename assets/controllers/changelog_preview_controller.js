import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        previewUrl: String,
    };

    showChange(event) {
        const td = event.target.closest('td');
        if (!td || event.target.tagName === 'LABEL' || event.target.tagName === 'INPUT') return;

        const tr = td.parentElement;
        const id = tr.getAttribute('data-id');
        if (!id) return;

        // Highlight active row
        this.element.querySelectorAll('tr').forEach((r) => r.classList.remove('active'));
        tr.classList.add('active');

        // Fetch preview
        const detailsEl = document.getElementById('_modif_details');
        if (!detailsEl) return;

        fetch(this.previewUrlValue + '?logId=' + id)
            .then((r) => r.text())
            .then((html) => { detailsEl.innerHTML = html; });
    }
}
