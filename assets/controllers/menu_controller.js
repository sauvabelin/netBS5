import { Controller } from '@hotwired/stimulus';

/*
 * Collapsible sidebar menu with accordion behavior.
 * Submenus start collapsed, active path is opened.
 * With Turbo Drive enabled, the sidebar has data-turbo-permanent
 * so it persists across navigations without re-initializing.
 */
export default class extends Controller {

    // --- Lifecycle ---

    connect() {
        this.topLis = this.element.querySelectorAll(':scope > ul > li:not(.menu-category)');
        this.initAccordion();
        this.restoreActiveItem();
    }

    // --- Setup ---

    initAccordion() {
        this.topLis.forEach((topLi) => {
            this.prepareSubmenu(topLi);
        });
    }

    prepareSubmenu(li) {
        const submenu = li.querySelector(':scope > ul');
        if (!submenu) return;

        submenu.dataset.originalHeight = submenu.offsetHeight + 'px';
        submenu.style.height = '0px';

        // Toggle handler on the <a> only (not <li>), so leaf link clicks
        // bubble to Turbo's document listener for SPA navigation.
        const toggle = li.querySelector(':scope > a');
        if (toggle) {
            toggle.addEventListener('click', (event) => {
                event.preventDefault();
                // Close sibling top-level sections if this is a top-level item
                if (Array.from(this.topLis).includes(li)) {
                    this.closeAllExcept(li);
                }
                this.toggle(li);
            });
        }

        submenu.querySelectorAll(':scope > li').forEach((childLi) => {
            this.prepareSubmenu(childLi);
        });
    }

    restoreActiveItem() {
        let item = this.element.querySelector('li.active');
        while (item && item !== this.element) {
            if (item.tagName === 'LI') this.open(item);
            item = item.parentElement;
        }
    }

    // --- Primitives ---

    closeAllExcept(keep) {
        this.topLis.forEach((li) => {
            if (li !== keep) this.close(li);
        });
    }

    toggle(li) {
        li.classList.contains('active') ? this.close(li) : this.open(li);
    }

    open(li) {
        li.classList.add('active');
        const submenu = li.querySelector(':scope > ul');
        if (submenu) submenu.style.height = submenu.dataset.originalHeight;
    }

    close(li) {
        li.classList.remove('active');
        const submenu = li.querySelector(':scope > ul');
        if (submenu) submenu.style.height = '0px';
        li.querySelectorAll('li').forEach((child) => this.close(child));
    }
}
