import { Controller } from '@hotwired/stimulus';
import { confirmMethod } from '../lib/turbo_confirm.js';

export default class extends Controller {
    static values = {
        url: String,
        idsSource: { type: String, default: '' },
        staticIds: { type: Array, default: [] },
        idField: { type: String, default: 'selectedIds' },
        extraData: { type: Object, default: {} },
    };

    async submit(event) {
        event.preventDefault();
        // Capture the button before any `await`: event.currentTarget is cleared once
        // the synchronous dispatch ends, and _buildData reads its dataset.
        const button = event.currentTarget;

        // post-link builds and submits a form programmatically, bypassing Turbo's
        // form-intercept entirely — so honour data-turbo-confirm ourselves.
        const confirmMsg = button.dataset.turboConfirm;
        if (confirmMsg && !(await confirmMethod(confirmMsg, button))) {
            return;
        }

        const data = this._buildData(button);
        this._postForm(this.urlValue, data);
    }

    _buildData(button) {
        const data = Object.assign({}, this.extraDataValue);
        data[this.idFieldValue] = this._resolveIds();
        this._mergeDynamicAttrs(data, button);
        return data;
    }

    _resolveIds() {
        if (this.staticIdsValue.length > 0) {
            return this.staticIdsValue;
        }

        if (this.idsSourceValue) {
            const source = window[this.idsSourceValue];
            if (source && typeof source.getSelectedIds === 'function') {
                return source.getSelectedIds();
            }
        }

        return [];
    }

    _mergeDynamicAttrs(data, element) {
        if (element.dataset.exporter) {
            data.exporterAlias = element.dataset.exporter;
        }
        if (element.dataset.exporterAlias) {
            data.exporterAlias = element.dataset.exporterAlias;
        }
        if (element.dataset.item) {
            data.updatedClass = element.dataset.item;
        }
    }

    _postForm(url, data) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = url;

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'data';
        input.value = JSON.stringify(data);
        form.appendChild(input);

        document.body.appendChild(form);
        form.submit();
    }
}
