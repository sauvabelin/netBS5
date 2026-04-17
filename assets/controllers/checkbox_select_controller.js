import { Controller } from '@hotwired/stimulus';

/*
 * Manages checkbox selection in tables with select-all and count display.
 * Replaces netbsTable.js and ajaxTable.js.
 *
 * Usage:
 *   <div data-controller="checkbox-select"
 *        data-checkbox-select-all-ids-value='[1,2,3]'>
 *     <input type="checkbox" data-action="change->checkbox-select#toggleAll"
 *            data-checkbox-select-target="selectAll">
 *     <input type="checkbox" data-action="change->checkbox-select#toggle"
 *            data-checkbox-select-target="checkbox" value="1">
 *     <span data-checkbox-select-target="count">0</span>
 *   </div>
 */
export default class extends Controller {
    static targets = ['selectAll', 'checkbox', 'count'];
    static values = {
        allIds: { type: Array, default: [] },
    };

    get selectedIds() {
        return this.checkboxTargets
            .filter((cb) => cb.checked)
            .map((cb) => parseInt(cb.value, 10));
    }

    getSelectedIds() {
        const selected = this.selectedIds;
        return selected.length === 0 ? this.allIdsValue : selected;
    }

    toggle() {
        this.updateCount();
    }

    toggleAll() {
        const checked = this.selectAllTarget.checked;
        this.checkboxTargets.forEach((cb) => (cb.checked = checked));
        this.updateCount();
    }

    updateCount() {
        if (this.hasCountTarget) {
            this.countTarget.textContent = this.selectedIds.length;
        }
    }
}
