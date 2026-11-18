import { Controller } from '@hotwired/stimulus';

/**
 * On a form: once submitted, swaps the visible content (`idle`, hidden not
 * removed, so its fields are still sent) for a spinner (`busy`) and ignores
 * further submits. A second login submit races the first one (see
 * LoginChallengeController), and the redirects through Hydra take a moment.
 *
 * Usage: data-controller="submit-busy" data-action="submit->submit-busy#submit"
 * Invalid forms never get here: form_validation.js stops them in the capture phase.
 */
export default class extends Controller {
    static targets = ['idle', 'busy'];

    connect() {
        this.busy = false;
        // Back button restores the page from the bfcache: allow a new submit.
        this.onPageShow = (event) => {
            if (event.persisted) this.setBusy(false);
        };
        window.addEventListener('pageshow', this.onPageShow);
    }

    disconnect() {
        window.removeEventListener('pageshow', this.onPageShow);
    }

    submit(event) {
        if (this.busy) {
            event.preventDefault();
            return;
        }
        this.setBusy(true);
    }

    setBusy(busy) {
        this.busy = busy;
        this.idleTarget.classList.toggle('d-none', busy);
        this.busyTarget.classList.toggle('d-none', !busy);
    }
}
