import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        theme: { type: String, default: 'snow' },
    };

    async connect() {
        if (!window.Quill) {
            await this._loadScript('/bundles/netbscore/lib/quill/quill.min.js');
        }

        this._quill = new Quill(this.element, {
            theme: this.themeValue,
        });
    }

    disconnect() {
        if (this._quill) {
            const container = this._quill.container;
            if (container) {
                const toolbar = container.previousElementSibling;
                if (toolbar && toolbar.classList.contains('ql-toolbar')) {
                    toolbar.remove();
                }
            }
            this._quill = null;
        }
    }

    _loadScript(src) {
        return new Promise((resolve, reject) => {
            if (document.querySelector('script[src="' + src + '"]')) { resolve(); return; }
            const script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }
}
