import * as Turbo from '@hotwired/turbo';
import * as bootstrap from 'bootstrap';
import './bootstrap.js';
import { showToast } from './lib/toast.js';
import { confirmMethod } from './lib/turbo_confirm.js';

// Bootstrap needs to be global for dropdowns/modals/tooltips in server-rendered HTML
window.bootstrap = bootstrap;

// showToast is used by inline scripts (toolbar buttons, updaters)
window.showToast = showToast;

// Replace native confirm() with a Bootstrap modal for data-turbo-confirm
Turbo.setConfirmMethod(confirmMethod);
