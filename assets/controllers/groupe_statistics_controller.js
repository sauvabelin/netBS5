import { Controller } from '@hotwired/stimulus';
import { loadScript } from '../lib/load_script.js';

export default class extends Controller {
    static values = {
        dataUrl: String,
    };

    static targets = ['canvas', 'begin', 'steps', 'end', 'total', 'hommes', 'femmes'];

    connect() {
        this._loadChart();
    }

    disconnect() {
        if (this._chart) {
            this._chart.destroy();
            this._chart = null;
        }
    }

    async _loadChart() {
        if (!window.Chart) {
            await loadScript('https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.js');
        }

        this._chart = new Chart(this.canvasTarget, {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    this._dataset('Tout', 'rgba(124,33,193,0.11)', '#7c21c1'),
                    this._dataset('Hommes', 'rgba(0,123,255,0.11)', '#007bff'),
                    this._dataset('Femmes', 'rgba(255,0,3,0.1)', '#ff0003'),
                ],
            },
            options: {
                aspectRatio: 3,
                legend: { display: false },
                scales: {
                    xAxes: [{
                        ticks: {
                            callback: (d) => {
                                const pad = (v) => (v < 10 ? '0' + v : v);
                                return pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + '.' + d.getFullYear();
                            },
                        },
                    }],
                    yAxes: [{
                        ticks: {
                            callback: (value) => (Math.floor(value) === value ? value : undefined),
                        },
                    }],
                },
            },
        });

        this.refreshData();
    }

    _dataset(label, bg, border) {
        return {
            label, data: [], backgroundColor: bg, borderWidth: 3,
            borderColor: border, pointRadius: 1, pointHoverRadius: 4,
            pointHoverBackgroundColor: border,
        };
    }

    refreshData() {
        const params = new URLSearchParams({
            begin: this.beginTarget.value,
            end: this.endTarget.value,
            steps: this.stepsTarget.value,
        });

        fetch(this.dataUrlValue + '?' + params)
            .then((r) => r.json())
            .then((data) => {
                if (!this._chart) return;
                const ds = this._chart.data.datasets;
                this._chart.data.labels = data.map((it) => new Date(it.pallier.date));
                ds[0].data = data.map((it) => it.countAll);
                ds[1].data = data.map((it) => it.countHomme);
                ds[2].data = data.map((it) => it.countAll - it.countHomme);
                this.toggleSeries();
            });
    }

    toggleSeries() {
        const chart = this._chart;
        if (!chart) return;
        const ds = chart.data.datasets;
        const showTotal = this.totalTarget.checked;
        const showHommes = this.hommesTarget.checked;
        const showFemmes = this.femmesTarget.checked;

        ds[0].hidden = !showTotal;
        ds[1].hidden = !showHommes;
        ds[2].hidden = !showFemmes;

        const ranges = ds
            .filter((s) => !s.hidden)
            .map((s) => {
                const vals = s.data.filter((v) => v != null);
                return vals.length ? { min: Math.min(...vals), max: Math.max(...vals) } : null;
            })
            .filter(Boolean);

        if (ranges.length > 0) {
            const min = Math.min(...ranges.map((r) => r.min));
            const max = Math.max(...ranges.map((r) => r.max));
            const diff = Math.ceil((max - min) / 20);
            chart.options.scales.yAxes[0].ticks.suggestedMin = Math.max(0, min - diff);
            chart.options.scales.yAxes[0].ticks.suggestedMax = max + diff;
        }

        chart.update();
    }
}
