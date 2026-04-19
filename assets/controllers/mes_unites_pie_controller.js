import { Controller } from '@hotwired/stimulus';
import { loadScript } from '../lib/load_script.js';

export default class extends Controller {
    static values = { data: Array };
    static targets = ['canvas'];

    async connect() {
        this._disconnected = false;
        if (!window.Chart) {
            await loadScript('https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.js');
        }
        if (this._disconnected) return;
        this._chart = new Chart(this.canvasTarget, {
            type: 'doughnut',
            data: {
                labels: this.dataValue.map((d) => d.label),
                datasets: [{
                    data: this.dataValue.map((d) => d.value),
                    backgroundColor: this.dataValue.map((d) => d.color),
                    borderWidth: 0,
                }],
            },
            options: {
                cutoutPercentage: 55,
                legend: { display: false },
                tooltips: {
                    callbacks: {
                        label: (item, data) => {
                            const label = data.labels[item.index];
                            const value = data.datasets[0].data[item.index];
                            return `${label}: ${value}`;
                        },
                    },
                },
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: 0 },
            },
        });
    }

    disconnect() {
        this._disconnected = true;
        if (this._chart) {
            this._chart.destroy();
            this._chart = null;
        }
    }
}
