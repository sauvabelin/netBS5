// Bootstrap JS is loaded via <script> tag (needed as global for x-editable).
// Move to importmap import here once x-editable is removed (Phase 6/7).
import * as Turbo from '@hotwired/turbo';
import './bootstrap.js';

// Disable Turbo Drive — body scripts from dumpJs()/dumpScript() break during
// Turbo body swap (jQuery plugins not loaded in time). Re-enable once jQuery
// plugins are removed (Phase 5/6). Turbo Frames still work.
Turbo.session.drive = false;
