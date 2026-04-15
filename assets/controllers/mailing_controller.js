import { Controller } from '@hotwired/stimulus';

/*
 * Handles mailing list interactions:
 * - Toggle list active/inactive status via switch
 * - Lazy-load recipient emails on hover (with tooltip)
 * - Show/hide target type form fields based on dropdown selection
 *
 * Usage (index page):
 *   <div data-controller="mailing"
 *        data-mailing-toggle-url-value="/mailing/__ID__/toggle"
 *        data-mailing-recipients-url-value="/mailing/__ID__/recipients">
 *     <!-- list content with data-action attributes rendered by PHP -->
 *   </div>
 *
 * Usage (edit page / modal — target type selector):
 *   <div data-controller="mailing">
 *     <select data-mailing-target="typeSelect"
 *             data-action="change->mailing#updateTargetFields">...</select>
 *     <div data-mailing-target="field" data-field-type="email">...</div>
 *     <div data-mailing-target="field" data-field-type="user">...</div>
 *     ...
 *   </div>
 */
export default class extends Controller {
    static targets = ['typeSelect', 'field'];

    static values = {
        toggleUrl: { type: String, default: '' },
        recipientsUrl: { type: String, default: '' },
    };

    connect() {
        // Initialize target type fields if selector is present
        if (this.hasTypeSelectTarget) {
            this.updateTargetFields();
        }
    }

    // --- Toggle list active status ---

    toggleActive(event) {
        const checkbox = event.currentTarget;
        const id = checkbox.dataset.listId;
        const label = this.element.querySelector(`[data-label-id="${id}"]`);
        const url = this.toggleUrlValue.replace('__ID__', id);

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    label.textContent = data.active ? 'Active' : 'Inactive';
                    label.className = 'badge ' + (data.active ? 'text-bg-success' : 'text-bg-secondary');
                } else {
                    checkbox.checked = !checkbox.checked;
                    alert('Erreur lors de la mise à jour');
                }
            })
            .catch(() => {
                checkbox.checked = !checkbox.checked;
                alert('Erreur lors de la mise à jour');
            });
    }

    // --- Lazy-load recipient emails on hover ---

    loadRecipients(event) {
        const el = event.currentTarget;
        if (el.dataset.loaded) return;

        el.dataset.loaded = 'true';
        const listId = el.dataset.listId;
        const url = this.recipientsUrlValue.replace('__ID__', listId);

        // Show loading tooltip
        this._setTooltip(el, 'Chargement...');

        fetch(url)
            .then((response) => response.json())
            .then((data) => {
                const badge = el.querySelector('.badge');
                const badgeClass = data.count > 0 ? 'text-bg-info' : 'text-bg-warning';
                badge.classList.remove('text-bg-secondary');
                badge.classList.add(badgeClass);
                badge.textContent = data.count + ' adresse(s)';

                const emailList = data.emails.length > 0 ? data.emails.join('\n') : 'Aucune adresse';
                this._setTooltip(el, emailList);
            })
            .catch(() => {
                const badge = el.querySelector('.badge');
                badge.classList.remove('text-bg-secondary');
                badge.classList.add('text-bg-danger');
                badge.textContent = 'Erreur';
                this._setTooltip(el, 'Erreur de chargement');
            });
    }

    // --- Target type field switching ---

    updateTargetFields() {
        const type = this.typeSelectTarget.value;

        this.fieldTargets.forEach((field) => {
            const fieldType = field.dataset.fieldType;
            const visible = fieldType === type;
            field.style.display = visible ? 'block' : 'none';

            if (!visible) {
                const input = field.querySelector('input, select');
                if (input) input.value = '';
            }
        });
    }

    // --- Private helpers ---

    _setTooltip(el, text) {
        const existing = bootstrap.Tooltip.getInstance(el);
        if (existing) existing.dispose();

        el.setAttribute('title', text);
        new bootstrap.Tooltip(el, { html: true, trigger: 'hover', container: 'body' });
    }
}
