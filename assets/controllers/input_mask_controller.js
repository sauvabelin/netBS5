import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { pattern: String };

    connect() {
        this._mask = this._parseMask(this.patternValue);
        if (!this._mask) return;

        this._onInput = this._handleInput.bind(this);
        this.element.addEventListener('input', this._onInput);

        if (!this.element.value) {
            this.element.placeholder = this._mask;
        } else {
            this.element.value = this._applyMask(this.element.value);
        }
    }

    disconnect() {
        if (this._onInput) {
            this.element.removeEventListener('input', this._onInput);
        }
    }

    _parseMask(raw) {
        if (!raw) return null;
        const match = raw.match(/'mask'\s*:\s*'([^']+)'/);
        return match ? match[1] : null;
    }

    _handleInput() {
        const cursorPos = this.element.selectionStart;
        const oldLen = this.element.value.length;
        this.element.value = this._applyMask(this.element.value);
        const newLen = this.element.value.length;
        const newPos = cursorPos + (newLen - oldLen);
        this.element.setSelectionRange(newPos, newPos);
    }

    _applyMask(raw) {
        const digits = raw.replace(/[^0-9a-zA-Z]/g, '');
        let result = '';
        let di = 0;

        for (let mi = 0; mi < this._mask.length && di < digits.length; mi++) {
            const mc = this._mask[mi];
            if (mc === '9' || mc === 'a' || mc === '*') {
                result += digits[di++];
            } else {
                result += mc;
            }
        }

        return result;
    }
}
