import { Controller } from '@hotwired/stimulus';
import flatpickr from 'flatpickr';
import { flatpickrDefaults } from '../lib/flatpickr_defaults.js';
import 'flatpickr/dist/flatpickr.min.css';

export default class extends Controller {
    static values = {
        format: { type: String, default: 'd.m.Y' },
        enableTime: { type: Boolean, default: false },
        allowInput: { type: Boolean, default: true },
    };

    connect() {
        this._fp = flatpickr(this.element, {
            ...flatpickrDefaults,
            dateFormat: this.formatValue,
            enableTime: this.enableTimeValue,
            allowInput: this.allowInputValue,
        });
    }

    disconnect() {
        if (this._fp) {
            this._fp.destroy();
            this._fp = null;
        }
    }
}
