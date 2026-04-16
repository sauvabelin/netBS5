import * as Turbo from '@hotwired/turbo';
import * as bootstrap from 'bootstrap';
import './bootstrap.js';
import { showToast } from './lib/toast.js';

// Bootstrap needs to be global for dropdowns/modals/tooltips in server-rendered HTML
window.bootstrap = bootstrap;

// showToast is used by inline scripts (toolbar buttons, updaters)
window.showToast = showToast;

// Turbo Drive disabled — dumpJs()/dumpScript() pattern still in use.
// External scripts loaded dynamically by Turbo race with inline scripts.
// Re-enable once registerJs/registerScript are fully eliminated.
Turbo.session.drive = false;
