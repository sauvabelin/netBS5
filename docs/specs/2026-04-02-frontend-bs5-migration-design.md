# Phase 1: Frontend Pipeline Restoration & Bootstrap 5 Migration

## Goal

Import the original frontend build pipeline, modernize dependencies, and upgrade Bootstrap 4 → 5. The app should look and function identically in light mode after migration. Dark mode (Phase 2) is out of scope.

## Scope

### In scope

- Import `frontend/` folder from original repo into netBS5
- Upgrade Webpack Encore (0.17.1 → latest), switch node-sass → dart-sass
- Upgrade Bootstrap 4.0 → 5.3
- Update all Twig templates for BS5 class/attribute changes
- Update JS Bootstrap API calls
- Update third-party library integrations for BS5 compatibility
- Keep jQuery (used extensively beyond Bootstrap)

### Out of scope

- Dark mode (Phase 2)
- PDF styles (`pdf.css`) — untouched
- Email templates — untouched
- Removing jQuery dependency
- Upgrading Vue.js or other non-Bootstrap JS libraries

---

## 1. Build Pipeline Restoration

### Import frontend folder

Copy `frontend/` from original repo to project root. Contents:
- `assets/js/` — app.js, menu.js, mobile.js
- `assets/scss/` — main.scss, _variables.scss, layout/, bootstrap/, plugin/, mixins/
- `webpack.config.js`
- `package.json`

### Modernize dependencies

**Replace `package.json` with updated versions:**

| Package | Old | New |
|---------|-----|-----|
| `@symfony/webpack-encore` | ^0.17.1 | ^4.x (latest) |
| `node-sass` | ^4.7.2 | remove |
| `sass` (dart-sass) | — | ^1.x |
| `sass-loader` | ^6.0.6 | ^14.x (Encore manages) |
| `uglifyjs-webpack-plugin` | ^1.1.8 | remove (Encore handles) |
| `webpack-notifier` | ^1.5.1 | remove or update |
| `bootstrap` | ^4.0.0 | ^5.3 |
| `jquery` | ^3.3.1 | ^3.7 (keep) |
| `popper.js` | ^1.12.9 | remove (BS5 bundles @popperjs/core) |
| `@popperjs/core` | — | ^2.x |

### Update webpack.config.js

Rewrite for current Encore API:
- Use `Encore.enableSassLoader()` (dart-sass is default)
- Remove manual uglify plugin (Encore handles minification)
- Output path: `../netBS/core/CoreBundle/Resources/public/dist/`
- Single entry: `app` from `./assets/js/app.js`

### Verify build

Run `npm install && npm run build` from `frontend/`. Output should produce `app.css` and `app.js` in the dist directory.

---

## 2. Bootstrap 5 SCSS Migration

### Update main.scss

Change Bootstrap import path if needed (should remain `~bootstrap/scss/bootstrap`).

### Update _variables.scss

Key BS5 variable changes to handle:
- `$font-size-root` → may need adjustment (BS5 uses `$font-size-base` differently)
- `$enable-shadows`, `$enable-gradients`, `$enable-transitions` — still valid
- Spacer maps — syntax unchanged
- Check for any removed variables and replace with BS5 equivalents

### Update custom partials

- `bootstrap/_buttons.scss` — `.btn-xs` custom variant, secondary button override
- `bootstrap/_cards.scss` — card shadow, colored card text overrides
- `bootstrap/_form.scss` — form-group horizontal, well styling
- `bootstrap/_tabs.scss` — tab variants with theme colors
- `plugin/_datetimepicker.scss` — review for BS5 variable name changes
- `plugin/_datatables.scss` — minimal, likely fine
- `plugin/_select2.scss` — review BS5 form-control height/border changes
- `layout/_global.scss`, `layout/_header.scss`, `layout/_menu.scss` — review for BS4-specific mixins/variables

---

## 3. Template Migration (Twig)

### Class renames (automated find/replace)

| BS4 Class | BS5 Class | Occurrences | Files |
|-----------|-----------|-------------|-------|
| `ml-` | `ms-` | 9 | 6 |
| `mr-` | `me-` | 8 | 5 |
| `pl-` | `ps-` | ~2 | ~2 |
| `pr-` | `pe-` | ~2 | ~2 |
| `float-left` | `float-start` | 1 | 1 |
| `float-right` | `float-end` | — | — |
| `text-left` | `text-start` | 3 | 2 |
| `text-right` | `text-end` | 3 | 2 |
| `no-gutters` | `g-0` | 7 | 7 |
| `badge-pill` | `rounded-pill` | check | check |
| `badge-{color}` | `bg-{color}` (+ keep `badge`) | 11 | 3 |
| `btn-block` | `w-100` | 18 | 8 |
| `close` | `btn-close` | 3 | 3 |
| `sr-only` | `visually-hidden` | check | check |
| `input-group-addon` | `input-group-text` | 1 | 1 |

### Structural changes (manual review)

| BS4 Pattern | BS5 Pattern | Occurrences | Files |
|-------------|-------------|-------------|-------|
| `custom-control custom-checkbox` | `form-check` | 15 | 3 |
| `custom-control-input` | `form-check-input` | included above | — |
| `custom-control-label` | `form-check-label` | included above | — |
| `form-group` | keep or use `mb-3` | 19 | 6 |
| `form-row` | `row g-3` | included above | — |
| `form-inline` | removed, use utilities | check | check |

### Data attributes (automated find/replace)

All `data-toggle`, `data-dismiss`, `data-target`, `data-ride`, `data-slide`, `data-parent`, `data-content`, `data-placement` → add `bs-` prefix.

**49 occurrences across 21 files.**

### Close button markup change

BS4:
```html
<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
```

BS5:
```html
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
```

3 files affected.

---

## 4. JavaScript Migration

### Bootstrap JS API calls (24 occurrences, 13 files)

BS5 still supports jQuery plugin syntax if jQuery is loaded before Bootstrap. Since we're keeping jQuery, most `.modal()`, `.tooltip()`, `.popover()` calls should continue working. However, review for:

- Deprecated options/methods
- Changed event names (`show.bs.modal` etc. — already prefixed, should be fine)
- `$().tooltip('dispose')` → verify still works in BS5

Key files:
- `netBS/core/CoreBundle/Resources/views/helper/init.javascript.twig` — popover init
- `netBS/core/CoreBundle/Resources/views/layout/backend.layout.twig` — modal init
- `netBS/core/CoreBundle/Resources/public/js/modal.js` — modal management
- `netBS/iacopo/MailingBundle/Resources/public/js/mailing.js` — tooltip calls

### Badge classes in JS

`mailing.js` uses `badge-success`, `badge-secondary` in JavaScript — update to `bg-success`, `bg-secondary`.

### Third-party libraries

| Library | BS5 Status | Action |
|---------|-----------|--------|
| Select2 | Works with BS5 via theme | Update select2-bootstrap-5-theme |
| X-Editable | Unmaintained, BS4 version | Keep as-is, test compatibility |
| Trumbowyg | No Bootstrap dependency | No change needed |
| DataTables | BS5 styling package available | Update to datatables.net-bs5 |
| DateTimePicker | Tempus Dominus BS5 version | Evaluate update |
| Toastr | No Bootstrap dependency | No change needed |
| Handsontable | No Bootstrap dependency | No change needed |

---

## 5. Verification

After migration:
- Run `npm run build` successfully
- Load every main page type (dashboard, member list, group view, billing, mailing)
- Test modals, dropdowns, tooltips, popovers
- Test form submissions (checkboxes, selects, date pickers)
- Test DataTables rendering and interaction
- Test Select2 dropdowns
- Verify sidebar menu expand/collapse
- Verify mobile responsive layout
- Compare visual appearance against current production

---

## Migration Order

1. Import frontend folder, modernize build pipeline, verify compilation with BS4
2. Upgrade Bootstrap package to 5.3, fix SCSS compilation errors
3. Update custom SCSS partials for BS5
4. Automated class renames in Twig templates
5. Manual structural changes (custom controls, forms, close buttons)
6. Data attribute migration
7. JavaScript API review and fixes
8. Third-party library compatibility
9. Visual verification and testing
