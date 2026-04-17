import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['searchInput', 'group'];

    connect() {
        this.selectedTypes = [];
        this.selectedCategories = [];
    }

    search() {
        this.filter();
    }

    toggleType(event) {
        const type = event.params.type;
        const idx = this.selectedTypes.indexOf(type);
        if (idx === -1) this.selectedTypes.push(type);
        else this.selectedTypes.splice(idx, 1);
        event.currentTarget.classList.toggle('active');
        this.filter();
    }

    toggleCategory(event) {
        const cat = event.params.category;
        const idx = this.selectedCategories.indexOf(cat);
        if (idx === -1) this.selectedCategories.push(cat);
        else this.selectedCategories.splice(idx, 1);
        event.currentTarget.classList.toggle('active');
        this.filter();
    }

    filter() {
        const query = this.hasSearchInputTarget ? this.searchInputTarget.value.toLowerCase() : '';

        this.groupTargets.forEach((el) => {
            const name = el.dataset.groupName.toLowerCase();
            const type = el.dataset.groupType;
            const category = el.dataset.groupCategory;

            const matchesName = query === '' || name.includes(query);
            const matchesCategory = this.selectedCategories.length === 0 || this.selectedCategories.includes(category);
            const matchesType = this.selectedTypes.length === 0 || this.selectedTypes.includes(type);

            el.style.display = matchesName && matchesCategory && matchesType ? '' : 'none';
        });
    }
}
