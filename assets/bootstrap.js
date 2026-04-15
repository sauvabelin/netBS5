import { startStimulusApp } from '@symfony/stimulus-bundle';

const app = startStimulusApp();
// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);

// Expose Stimulus app globally so inline scripts (toolbar buttons) can access controllers
window.Stimulus = app;
