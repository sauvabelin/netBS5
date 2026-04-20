/**
 * Re-initializes per-cell plugins on a subtree after it becomes visible.
 * Used by client-list (on DataTable page/search/perpage events) and
 * ajax-list (on turbo:frame-load).
 *
 * All plugin calls feature-detect their dependencies — silent no-op if a
 * plugin isn't loaded on the current page.
 */
export function reinitCellPlugins(root) {
    if (!root) return;

    // x-editable (jQuery plugin — legacy, feature-detect)
    if (window.$ && window.$.fn && window.$.fn.editable) {
        window.$(root).find('.editable').editable();
    }

    // select2 (jQuery plugin — legacy, feature-detect)
    // Skip already-initialized instances to avoid double-binding.
    if (window.$ && window.$.fn && window.$.fn.select2) {
        window.$(root)
            .find('select.select2:not(.select2-hidden-accessible)')
            .select2();
    }
}
