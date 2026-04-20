import { Controller } from '@hotwired/stimulus';

/*
 * Collapsible sidebar menu with accordion behavior.
 * Uses 'open' class for expanded state (JS-controlled),
 * separate from 'active' class for current-page highlight (server-set).
 */
export default class extends Controller {

    connect() {
        this._topLis = new Set(this.element.querySelectorAll(':scope > ul > li:not(.menu-category)'));
        this._handleClick = this._onClick.bind(this);
        this.element.addEventListener('click', this._handleClick);

        // Suppress transitions so the active section appears instantly
        const submenus = this.element.querySelectorAll('li > ul');
        submenus.forEach((ul) => { ul.style.transition = 'none'; });
        this._collapseAll(submenus);
        this._openActivePath();
        void this.element.offsetHeight; // flush styles before restoring transitions
        submenus.forEach((ul) => { ul.style.transition = ''; });
    }

    disconnect() {
        this.element.removeEventListener('click', this._handleClick);
    }

    _onClick(event) {
        const a = event.target.closest('a');
        if (!a || a.hasAttribute('href')) return;
        event.preventDefault();

        const li = a.closest('li');
        if (!li) return;

        if (this._topLis.has(li)) this._closeAllExcept(li);
        this._toggle(li);
    }

    _collapseAll(submenus) {
        submenus.forEach((ul) => { ul.style.height = '0px'; });
    }

    _openActivePath() {
        let item = this.element.querySelector('li.active');
        while (item && item !== this.element) {
            if (item.tagName === 'LI' && item.querySelector(':scope > ul')) {
                this._open(item);
            }
            item = item.parentElement;
        }
    }

    _closeAllExcept(keep) {
        this._topLis.forEach((li) => {
            if (li !== keep) this._close(li);
        });
    }

    _toggle(li) {
        li.classList.contains('open') ? this._close(li) : this._open(li);
    }

    _open(li) {
        li.classList.add('open');
        const sub = li.querySelector(':scope > ul');
        if (!sub) return;
        this._resizeAncestors(li, [sub]);
    }

    _close(li) {
        li.classList.remove('open');
        const sub = li.querySelector(':scope > ul');
        if (sub) sub.style.height = '0px';
        li.querySelectorAll('li.open').forEach((child) => {
            child.classList.remove('open');
            const childSub = child.querySelector(':scope > ul');
            if (childSub) childSub.style.height = '0px';
        });
        this._resizeAncestors(li);
    }

    // Batch-read scrollHeights then batch-write to avoid layout thrashing
    _resizeAncestors(li, extra = []) {
        const targets = [...extra];
        let el = li.parentElement;
        while (el && el !== this.element) {
            if (el.tagName === 'UL' && el.style.height && el.style.height !== '0px') {
                targets.push(el);
            }
            el = el.parentElement;
        }
        const heights = targets.map((t) => t.scrollHeight);
        targets.forEach((t, i) => { t.style.height = heights[i] + 'px'; });
    }
}
