import { Controller } from '@hotwired/stimulus';

/*
 * Initializes FullCalendar on an element.
 * FullCalendar is still loaded via CDN <script> tag.
 *
 * Usage:
 *   <div data-controller="calendar"
 *        data-calendar-events-url-value="/api/events"
 *        data-calendar-initial-view-value="dayGridMonth"
 *        data-calendar-initial-date-value="2026-01-15">
 *   </div>
 */
export default class extends Controller {
    static values = {
        eventsUrl: { type: String, default: '' },
        eventSources: { type: Array, default: [] },
        initialView: { type: String, default: 'dayGridMonth' },
        initialDate: { type: String, default: '' },
    };

    connect() {
        if (typeof FullCalendar === 'undefined') {
            console.error('FullCalendar not loaded');
            return;
        }

        const options = {
            locale: 'fr',
            initialView: this.initialViewValue,
            firstDay: 1,
            headerToolbar: {
                left: 'prev,next',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay',
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

        this.calendar = new FullCalendar.Calendar(this.element, options);
        this.calendar.render();
    }

    disconnect() {
        if (this.calendar) {
            this.calendar.destroy();
            this.calendar = null;
        }
    }
}
