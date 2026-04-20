import { startStimulusApp } from '@symfony/stimulus-bundle';

const app = startStimulusApp();

// Exposed for inline scripts in server-rendered HTML that access controllers by identifier.
window.Stimulus = app;
