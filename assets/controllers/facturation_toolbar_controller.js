import { Controller } from '@hotwired/stimulus';
import { showToast } from '../lib/toast.js';
import * as Turbo from '@hotwired/turbo';

export default class extends Controller {
    static values = {
        resolveModelUrl: String,
        markPrintedUrl: String,
        unmarkPrintedUrl: String,
        tableId: String,
    };

    connect() {
        // Model badge loading uses global event delegation because badges
        // are rendered in table rows OUTSIDE this controller's element.
        this._handleBadgeHover = (e) => {
            const badge = e.target.closest && e.target.closest('.facture-model-auto');
            if (!badge || badge.dataset.loaded) return;
            badge.dataset.loaded = 'true';
            const factureId = badge.dataset.factureId;
            const url = this.resolveModelUrlValue.replace('__ID__', factureId);
            fetch(url)
                .then((r) => r.json())
                .then((data) => { badge.textContent = data.model; });
        };
        document.addEventListener('mouseenter', this._handleBadgeHover, true);
    }

    disconnect() {
        document.removeEventListener('mouseenter', this._handleBadgeHover, true);
    }

    markPrinted() {
        const ids = this._getSelectedIds();
        if (ids.length === 0) { showToast('warning', 'Veuillez sélectionner des factures'); return; }
        if (!confirm('Marquer ' + ids.length + ' facture(s) comme imprimée(s) ?')) return;
        this._postMarkAction(this.markPrintedUrlValue, ids, 'facture(s) marquée(s) comme imprimée(s)');
    }

    unmarkPrinted() {
        const ids = this._getSelectedIds();
        if (ids.length === 0) { showToast('warning', 'Veuillez sélectionner des factures'); return; }
        if (!confirm('Marquer ' + ids.length + ' facture(s) comme non-imprimée(s) ?')) return;
        this._postMarkAction(this.unmarkPrintedUrlValue, ids, 'facture(s) marquée(s) comme non-imprimée(s)');
    }

    _postMarkAction(url, ids, successMsg) {
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'ids=' + encodeURIComponent(JSON.stringify(ids)),
        })
            .then((r) => r.json())
            .then((data) => {
                showToast('success', data.count + ' ' + successMsg);
                Turbo.visit(window.location.href, { action: 'replace' });
            })
            .catch(() => showToast('error', 'Erreur lors de la mise à jour'));
    }

    _getSelectedIds() {
        const source = window[this.tableIdValue];
        if (source && typeof source.getSelectedIds === 'function') {
            return source.getSelectedIds();
        }
        return [];
    }
}
