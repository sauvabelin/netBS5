import { Controller } from '@hotwired/stimulus';

/*
 * Fills counts on hover: when the pointer enters a table row, every
 * [data-lazy-count-url] element in it is replaced by the "count" the URL
 * returns ({"count": n}), once. On failure shows "?" and retries on the next
 * hover. Attach to a container, since list rows can't carry attributes.
 *
 * Usage:
 *   <div data-controller="lazy-count" data-action="mouseover->lazy-count#load">
 *     ... <tr><td><span data-lazy-count-url="/count">…</span></td></tr> ...
 *   </div>
 */
export default class extends Controller {
    load(event) {
        const row = event.target.closest('tr');
        if (!row) return;
        row.querySelectorAll('[data-lazy-count-url]:not([data-lazy-count-loading])').forEach((el) => this.fetch(el));
    }

    fetch(el) {
        el.dataset.lazyCountLoading = '';
        fetch(el.dataset.lazyCountUrl, { headers: { Accept: 'application/json' } })
            .then((r) => {
                if (!r.ok) throw new Error(r.status);
                return r.json();
            })
            .then((data) => {
                el.textContent = data.count;
                el.classList.remove('text-muted');
                el.removeAttribute('title');
                el.removeAttribute('data-lazy-count-url');
            })
            .catch(() => {
                el.textContent = '?';
                el.title = 'Erreur, survoler à nouveau pour réessayer';
                delete el.dataset.lazyCountLoading;
            });
    }
}
