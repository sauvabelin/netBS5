import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['item', 'pageButton'];
    static values = {
        page: { type: Number, default: 1 },
        perPage: { type: Number, default: 1 },
        total: Number,
    };

    get totalPages() {
        return Math.ceil(this.totalValue / this.perPageValue);
    }

    pageValueChanged() {
        this.itemTargets.forEach((item, index) => {
            const visible = index >= (this.pageValue - 1) * this.perPageValue
                         && index < this.pageValue * this.perPageValue;
            item.style.display = visible ? '' : 'none';
        });

        if (this.hasPageButtonTarget) {
            this.pageButtonTargets.forEach((btn) => {
                const page = parseInt(btn.dataset.newsChannelPageParam, 10);
                btn.closest('.page-item').classList.toggle('active', page === this.pageValue);
            });
        }
    }

    next() {
        if (this.pageValue < this.totalPages) this.pageValue++;
    }

    previous() {
        if (this.pageValue > 1) this.pageValue--;
    }

    goToPage(event) {
        this.pageValue = parseInt(event.params.page, 10);
    }
}
