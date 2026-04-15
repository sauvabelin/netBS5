import { Controller } from '@hotwired/stimulus';
import { renderFamilyList } from '../lib/family_renderer.js';

export default class extends Controller {
    static targets = ['nom', 'familleId', 'results'];
    static values = { searchUrl: String };

    connect() {
        this.timeout = null;
        this.selectedId = this.familleIdTarget.value || null;
        this.actualNom = this.nomTarget.value;
        this.families = [];
    }

    onNomInput() {
        const val = this.nomTarget.value;
        if (val === this.actualNom) return;
        if (this.selectedId !== null) this.clearSelection();
        this.debouncedSearch(val);
    }

    selectFamily(event) {
        event.preventDefault();
        const el = event.currentTarget;
        this.selectedId = el.dataset.familyId;
        this.actualNom = el.dataset.familyNom;
        this.updateFormFields();
        this.refreshResults();
    }

    clearSelection() {
        this.selectedId = null;
        this.actualNom = null;
        this.familleIdTarget.value = '';
        this.element.querySelectorAll('[data-show-without-family]').forEach((el) => {
            el.style.display = '';
        });
    }

    debouncedSearch(term) {
        clearTimeout(this.timeout);
        this.timeout = setTimeout(() => {
            fetch(`${this.searchUrlValue}?term=${encodeURIComponent(term)}`)
                .then((r) => r.json())
                .then((families) => {
                    this.families = families;
                    this.refreshResults();
                });
        }, 700);
    }

    updateFormFields() {
        this.familleIdTarget.value = this.selectedId;
        this.nomTarget.value = this.actualNom;
    }

    refreshResults() {
        this.resultsTarget.innerHTML = renderFamilyList(this.families, this.selectedId);
    }

    disconnect() {
        clearTimeout(this.timeout);
    }
}
