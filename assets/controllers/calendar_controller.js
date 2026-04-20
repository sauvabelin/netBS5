import { Controller } from '@hotwired/stimulus';

const FULLCALENDAR_SRC = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js';
let fullCalendarPromise = null;

const HTML_ESCAPE = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
const escapeHtml = (s) => String(s).replace(/[&<>"']/g, (c) => HTML_ESCAPE[c]);

function loadFullCalendar() {
    if (typeof window.FullCalendar !== 'undefined') return Promise.resolve();
    if (fullCalendarPromise) return fullCalendarPromise;

    fullCalendarPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = FULLCALENDAR_SRC;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => {
            fullCalendarPromise = null;
            reject(new Error('Failed to load FullCalendar'));
        };
        document.head.appendChild(script);
    });
    return fullCalendarPromise;
}

export default class extends Controller {
    static values = {
        eventsUrl: { type: String, default: '' },
        eventSources: { type: Array, default: [] },
        initialView: { type: String, default: 'dayGridMonth' },
        initialDate: { type: String, default: '' },
    };

    async connect() {
        try {
            await loadFullCalendar();
        } catch (e) {
            console.error(e);
            return;
        }
        if (!this.element.isConnected) return;

        const options = {
            locale: 'fr',
            initialView: this.initialViewValue,
            firstDay: 1,
            headerToolbar: {
                left: 'prev,next',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay',
            },
            eventDidMount: (info) => {
                if (info.event.url) {
                    info.el.setAttribute('target', '_blank');
                    info.el.setAttribute('rel', 'noopener');
                }
                const { description, location } = info.event.extendedProps;
                const parts = [`<strong>${escapeHtml(info.event.title)}</strong>`];
                if (location) parts.push(escapeHtml(location));
                if (description) parts.push(escapeHtml(description));
                new bootstrap.Tooltip(info.el, {
                    title: parts.join('<br>'),
                    html: true,
                    placement: 'top',
                    container: 'body',
                    trigger: 'hover',
                });
            },
            eventWillUnmount: (info) => {
                bootstrap.Tooltip.getInstance(info.el)?.dispose();
            },
        };

        if (this.eventsUrlValue) {
            options.events = this.eventsUrlValue;
        } else if (this.eventSourcesValue.length > 0) {
            options.eventSources = this.eventSourcesValue;
        }

        if (this.initialDateValue) {
            options.initialDate = this.initialDateValue;
        }

        this.calendar = new window.FullCalendar.Calendar(this.element, options);
        this.calendar.render();
    }

    disconnect() {
        if (this.calendar) {
            this.calendar.destroy();
            this.calendar = null;
        }
    }
}
