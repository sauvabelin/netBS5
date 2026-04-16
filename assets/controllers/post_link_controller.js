import { Controller } from '@hotwired/stimulus';

/*
 * Replaces the legacy jQuery PostLink(url, data) function.
 * Creates a hidden form and POSTs it, causing a full page navigation.
 *
 * Usage example (Twig):
 *   <a href="#"
 *      data-controller="post-link"
 *      data-post-link-url-value="{{ path('some_route') }}"
 *      data-post-link-ids-source-value="tableId"
 *      data-post-link-id-field-value="selectedIds"
 *      data-post-link-extra-data-value='{{ { itemsClass: "App\\Entity\\Foo" }|json_encode }}'
 *      data-action="post-link#submit">
 *       Export
 *   </a>
 */
export default class extends Controller {
    static values = {
        url: String,
        idsSource: { type: String, default: '' },
        staticIds: { type: Array, default: [] },
        idField: { type: String, default: 'selectedIds' },
        extraData: { type: Object, default: {} },
    };

    submit(event) {
        event.preventDefault();
        const data = this._buildData(event);
        this._postForm(this.urlValue, data);
    }

    _buildData(event) {
        const data = Object.assign({}, this.extraDataValue);
        data[this.idFieldValue] = this._resolveIds();
        this._mergeDynamicAttrs(data, event.currentTarget);
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
