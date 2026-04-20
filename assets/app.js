import * as Turbo from '@hotwired/turbo';
import * as bootstrap from 'bootstrap';
import './bootstrap.js';
import { showToast } from './lib/toast.js';
import { confirmMethod } from './lib/turbo_confirm.js';
import { initGlobalFormValidation } from './lib/form_validation.js';

// Globals for server-rendered HTML (toolbar buttons, mass updaters) that call these inline.
window.bootstrap = bootstrap;
window.showToast = showToast;

Turbo.setConfirmMethod(confirmMethod);
initGlobalFormValidation();
