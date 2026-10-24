import { Controller } from '@hotwired/stimulus';

/*
 * LDAP page: the "Membres" badge of a group loads its members on first click
 * and expands them inline; later clicks collapse/expand the loaded list.
 */
export default class extends Controller {
    static values = { url: String };

    toggle(event) {
        const badge = event.currentTarget;
        const loaded = badge.parentElement.querySelector('ul.ldap-members');
        if (loaded) {
            loaded.classList.toggle('d-none');
            return;
        }
        if (badge.dataset.loading) return;
        badge.dataset.loading = 'true';

        fetch(this.urlValue.replace('__KEY__', encodeURIComponent(badge.dataset.key)))
            .then((response) => {
                if (!response.ok) throw new Error(response.statusText);
                return response.json();
            })
            .then((members) => {
                const list = document.createElement('ul');
                list.className = 'ldap-members mb-0 mt-1';
                if (members.length === 0) {
                    const item = document.createElement('li');
                    item.className = 'text-muted';
                    item.textContent = 'Aucun membre';
                    list.appendChild(item);
                }
                for (const member of members) {
                    const item = document.createElement('li');
                    item.textContent = `${member.displayName} (${member.uid})`;
                    list.appendChild(item);
                }
                badge.after(list);
            })
            .catch(() => {
                badge.classList.replace('text-bg-secondary', 'text-bg-danger');
                badge.textContent = 'Erreur';
            })
            .finally(() => {
                delete badge.dataset.loading;
            });
    }
}
