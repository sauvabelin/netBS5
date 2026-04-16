import { Controller } from '@hotwired/stimulus';

/*
 * Shows a help popover on hover after a 300ms delay.
 * Fetches content from the server via AJAX.
 *
 * Usage:
 *   <span data-controller="helper-popover"
 *         data-helper-popover-id-value="123"
 *         data-helper-popover-class-value="base64encodedClass"
 *         data-helper-popover-url-value="/helper/get"
 *         data-helper-popover-placement-value="top"
 *         data-action="mouseenter->helper-popover#mouseenter mouseleave->helper-popover#mouseleave">
 *     ?
 *   </span>
 */
export default class extends Controller {
    static values = {
        id: String,
        class: String,
        url: String,
        placement: { type: String, default: 'top' },
    };

    connect() {
        this.timer = null;
        this.popoverInstance = null;
        this.active = false;
    }

    // --- Actions (broad strokes) ---

    mouseenter() {
        this.active = true;
        this.timer = setTimeout(() => this.fetchAndShow(), 300);
    }

    mouseleave() {
        this.active = false;
        clearTimeout(this.timer);
        this.disposePopover();
    }

    disconnect() {
        this.mouseleave();
    }

    // --- Internals ---

    fetchAndShow() {
        if (!this.classValue || !this.idValue) return;
        fetch(this.urlValue, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ class: this.classValue, id: this.idValue }),
        })
            .then((r) => r.json())
            .then((data) => {
                if (this.active) this.showPopover(data.title, data.content);
            });
    }

    showPopover(title, content) {
        this.disposePopover();
        this.popoverInstance = new bootstrap.Popover(this.element, {
            html: true,
            placement: this.placementValue,
            container: 'body',
            title,
            content,
        });
        this.popoverInstance.show();
    }

    disposePopover() {
        if (this.popoverInstance) {
            this.popoverInstance.dispose();
            this.popoverInstance = null;
        }
    }
}
