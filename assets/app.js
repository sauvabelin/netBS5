// Bootstrap JS is loaded via <script> tag (needed as global for x-editable).
// Move to importmap import here once x-editable is removed (Phase 6/7).
import * as Turbo from '@hotwired/turbo';
import './bootstrap.js';

// Disable Turbo Drive globally until jQuery plugins are removed (Phase 4+).
// Turbo Frames still work — only full-page Drive is disabled.
Turbo.session.drive = false;
