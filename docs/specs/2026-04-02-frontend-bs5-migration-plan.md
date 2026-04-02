# Frontend Pipeline Restoration & Bootstrap 5 Migration — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Import the original frontend build pipeline, modernize it, and upgrade Bootstrap 4 → 5 so the app looks and functions identically.

**Architecture:** Copy the original `frontend/` folder into netBS5, rewrite `package.json` and `webpack.config.js` for modern Encore/dart-sass, upgrade Bootstrap to 5.3, fix all SCSS compilation errors, then migrate all Twig templates and JS for BS5 class/attribute/API changes.

**Tech Stack:** Symfony Webpack Encore 4.x, dart-sass, Bootstrap 5.3, jQuery 3.7, Node 22

---

## File Structure

**New files:**
- `frontend/package.json` — modernized dependencies
- `frontend/webpack.config.js` — rewritten for Encore 4.x

**Copied from original repo (then modified):**
- `frontend/assets/js/app.js`
- `frontend/assets/js/menu.js`
- `frontend/assets/js/mobile.js`
- `frontend/assets/scss/main.scss`
- `frontend/assets/scss/_variables.scss`
- `frontend/assets/scss/layout/_global.scss`
- `frontend/assets/scss/layout/_header.scss`
- `frontend/assets/scss/layout/_menu.scss`
- `frontend/assets/scss/bootstrap/_buttons.scss`
- `frontend/assets/scss/bootstrap/_cards.scss`
- `frontend/assets/scss/bootstrap/_form.scss`
- `frontend/assets/scss/bootstrap/_tabs.scss`
- `frontend/assets/scss/plugin/_datetimepicker.scss`
- `frontend/assets/scss/plugin/_datatables.scss`
- `frontend/assets/scss/plugin/_select2.scss`
- `frontend/assets/scss/mixins/user-select.scss`

**Modified Twig templates (BS5 class/attribute migration):**
- `netBS/core/CoreBundle/Resources/views/layout/base.layout.twig`
- `netBS/core/CoreBundle/Resources/views/layout/backend.layout.twig`
- `netBS/core/CoreBundle/Resources/views/layout/modal.layout.twig`
- `netBS/core/CoreBundle/Resources/views/helper/init.javascript.twig`
- `netBS/core/CoreBundle/Resources/views/partial/header.partial.twig`
- `netBS/core/CoreBundle/Resources/views/renderer/ajax.renderer.twig`
- `netBS/core/CoreBundle/Resources/views/renderer/netbs.renderer.twig`
- `netBS/core/CoreBundle/Resources/views/form/base.theme.twig`
- All templates with BS4 data-* attributes and class names (21+ files total)

**Modified JS files:**
- `netBS/core/CoreBundle/Resources/public/js/modal.js`
- `netBS/iacopo/MailingBundle/Resources/public/js/mailing.js`

**Updated library files:**
- `netBS/core/CoreBundle/Resources/public/lib/bootstrap/` — update to BS5

---

### Task 1: Import frontend folder from original repo

**Files:**
- Create: `frontend/` (entire directory tree)

- [ ] **Step 1: Copy the frontend folder**

```bash
cp -r /home/iacopo/Documents/Sauvabelin/original_netbs/netBS/frontend/assets /home/iacopo/Documents/Sauvabelin/netBS5/frontend/assets
```

This copies only the `assets/` subdirectory. We will write fresh `package.json` and `webpack.config.js` in the next task.

- [ ] **Step 2: Verify the copied files**

```bash
find /home/iacopo/Documents/Sauvabelin/netBS5/frontend -type f | sort
```

Expected output should include:
```
frontend/assets/js/app.js
frontend/assets/js/menu.js
frontend/assets/js/mobile.js
frontend/assets/scss/_variables.scss
frontend/assets/scss/bootstrap/_buttons.scss
frontend/assets/scss/bootstrap/_cards.scss
frontend/assets/scss/bootstrap/_form.scss
frontend/assets/scss/bootstrap/_tabs.scss
frontend/assets/scss/layout/_global.scss
frontend/assets/scss/layout/_header.scss
frontend/assets/scss/layout/_menu.scss
frontend/assets/scss/main.scss
frontend/assets/scss/mixins/user-select.scss
frontend/assets/scss/plugin/_datatables.scss
frontend/assets/scss/plugin/_datetimepicker.scss
frontend/assets/scss/plugin/_select2.scss
```

- [ ] **Step 3: Commit**

```bash
git add frontend/
git commit -m "Import frontend assets from original netBS repo"
```

---

### Task 2: Create modern build pipeline

**Files:**
- Create: `frontend/package.json`
- Create: `frontend/webpack.config.js`

- [ ] **Step 1: Write package.json**

```json
{
  "private": true,
  "scripts": {
    "dev": "encore dev",
    "watch": "encore dev --watch",
    "build": "encore production"
  },
  "devDependencies": {
    "@symfony/webpack-encore": "^4.0",
    "sass": "^1.77",
    "sass-loader": "^14.0",
    "webpack-notifier": "^1.15"
  },
  "dependencies": {
    "bootstrap": "^5.3",
    "jquery": "^3.7",
    "@popperjs/core": "^2.11"
  }
}
```

- [ ] **Step 2: Write webpack.config.js**

```javascript
const Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('../netBS/core/CoreBundle/Resources/public/dist/')
    .setPublicPath('/bundles/netbscore/dist')
    .setManifestKeyPrefix('dist')
    .addEntry('app', './assets/js/app.js')
    .enableSassLoader()
    .autoProvidejQuery()
    .enableSourceMaps(!Encore.isProduction())
    .cleanupOutputBeforeBuild()
    .enableSingleRuntimeChunk()
;

module.exports = Encore.getWebpackConfig();
```

Key changes from original:
- Output path adjusted for netBS5 directory structure (`../netBS/` not `../src/NetBS/`)
- `setPublicPath` matches Symfony asset path (`/bundles/netbscore/dist`)
- `setManifestKeyPrefix('dist')` so manifest keys match the original `dist/app.css` pattern
- Removed manual uglifier plugin (Encore handles it)
- Removed compressed outputStyle (Encore handles it in production)
- Added `enableSingleRuntimeChunk()` (required by modern Encore)

- [ ] **Step 3: Install dependencies**

```bash
cd /home/iacopo/Documents/Sauvabelin/netBS5/frontend && npm install
```

Expected: installs without errors. Creates `node_modules/` and `package-lock.json`.

- [ ] **Step 4: Add node_modules to .gitignore**

Check if `node_modules` is already in `.gitignore`. If not, add it:

```bash
echo "frontend/node_modules/" >> /home/iacopo/Documents/Sauvabelin/netBS5/.gitignore
```

- [ ] **Step 5: Commit**

```bash
git add frontend/package.json frontend/package-lock.json frontend/webpack.config.js .gitignore
git commit -m "Add modern build pipeline with Webpack Encore 4 and dart-sass"
```

---

### Task 3: Migrate _variables.scss for Bootstrap 5

The original `_variables.scss` is a full copy of Bootstrap 4's variables file with customizations. Bootstrap 5 changed many variable names and removed several features. We need to rewrite this file to only contain the **customized overrides** (not the full variable list) and make them BS5-compatible.

**Files:**
- Modify: `frontend/assets/scss/_variables.scss`

- [ ] **Step 1: Replace _variables.scss with BS5-compatible overrides**

The original file is ~900 lines — a full copy of BS4 variables with a few customizations mixed in. For BS5, we only need to declare our overrides before importing Bootstrap. Replace the entire file with:

```scss
// Import Bootstrap functions first (needed for color manipulation)
@import "~bootstrap/scss/functions";

// App-specific layout variables
$header-height: 3.6rem;
$base-padding: 1rem;

// Typography
$font-family-sans-serif: 'Roboto', "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
$font-size-base: 0.95rem;
$font-size-root: 0.85rem; // BS5 still supports this — sets <html> font-size

$headings-font-weight: 300;

// Theme colors — keep BS4 palette
$primary:       #007bff;
$secondary:     #6c757d;
$success:       #28a745;
$info:          #17a2b8;
$warning:       #ffc107;
$danger:        #dc3545;
$light:         #fff;      // Custom: pure white instead of BS default #f8f9fa
$dark:          #343a40;

// Components
$border-radius:     2px;
$border-radius-lg:  4px;
$border-radius-sm:  2px;

$enable-shadows:    false;
$enable-gradients:  false;

// Buttons
$btn-font-weight: 700;

// Forms
$input-padding-y: 0.5625rem;   // Original: $input-btn-padding-y * 1.5
$input-padding-x: 0.5rem;      // Original: $input-btn-padding-x / 2

// Cards
$card-cap-bg: #fff;
$card-bg: #fff;
```

This preserves all the visual customizations from the original while being BS5-compatible. Variables that were just BS4 defaults (with `!default`) are dropped since BS5 provides its own defaults.

- [ ] **Step 2: Verify no compilation errors yet** (we'll do a full build test in Task 5)

---

### Task 4: Migrate SCSS partials for Bootstrap 5

**Files:**
- Modify: `frontend/assets/scss/main.scss`
- Modify: `frontend/assets/scss/bootstrap/_buttons.scss`
- Modify: `frontend/assets/scss/bootstrap/_cards.scss`
- Modify: `frontend/assets/scss/bootstrap/_form.scss`
- Modify: `frontend/assets/scss/bootstrap/_tabs.scss`
- Modify: `frontend/assets/scss/plugin/_datetimepicker.scss`
- Modify: `frontend/assets/scss/plugin/_select2.scss`
- Modify: `frontend/assets/scss/layout/_header.scss`
- Modify: `frontend/assets/scss/layout/_menu.scss`
- Modify: `frontend/assets/scss/mixins/user-select.scss`

- [ ] **Step 1: Update main.scss**

Replace with:

```scss
// Custom variables (must come before Bootstrap import)
@import "variables";

// Bootstrap 5
@import "~bootstrap/scss/bootstrap";

// Layout
@import "layout/menu";
@import "layout/header";
@import "layout/global";

// Plugin overrides
@import "plugin/datetimepicker";
@import "plugin/datatables";
@import "plugin/select2";

// Bootstrap component overrides
@import "bootstrap/cards";
@import "bootstrap/buttons";
@import "bootstrap/tabs";
@import "bootstrap/form";
```

Changes:
- Removed `@import "mixins/user-select"` — the `user-select` mixin is no longer needed (all modern browsers support `user-select` natively, and BS5 provides its own utility)
- Removed `@import "~bootstrap/scss/functions"` from here (it's imported in `_variables.scss`)

- [ ] **Step 2: Update bootstrap/_buttons.scss**

Replace with:

```scss
.btn {
  &.btn-xs {
    padding: 0 0.5rem 0.2rem 0.5rem;
  }
}

.btn-secondary {
  --bs-btn-bg: #fff;
  --bs-btn-border-color: #{$gray-400};
  --bs-btn-color: #{$gray-700};
  --bs-btn-hover-bg: #{$gray-200};
  --bs-btn-hover-border-color: #{$gray-400};
  --bs-btn-hover-color: #{$gray-900};
  --bs-btn-active-bg: #{$gray-300};
  --bs-btn-active-border-color: #{$gray-400};
}
```

BS5 uses CSS custom properties for button variants. The `button-variant()` mixin still exists but the output changed. Using CSS vars directly preserves the white background + gray border look.

- [ ] **Step 3: Update bootstrap/_cards.scss**

No changes needed — the file uses standard CSS selectors and `$border-radius` / `$gray-*` variables that still exist in BS5. Keep as-is.

- [ ] **Step 4: Update bootstrap/_form.scss**

Replace with:

```scss
.form-horizontal {
  .mb-3 {
    margin-bottom: 0 !important;
    padding: 0;
  }

  &.well {
    background: $gray-100;
    padding: calc($spacer / 2) calc($spacer * 1.5);
    border-radius: $border-radius-lg;
    border: 1px solid $gray-200;
  }
}

label {
  color: $gray-600;
}
```

Changes:
- `.form-group` → `.mb-3` (BS5 replacement)
- Replaced `$spacer/2` with `calc($spacer / 2)` (dart-sass deprecates `/` for division)

- [ ] **Step 5: Update bootstrap/_tabs.scss**

Replace `theme-color("primary")` etc. with direct variable references:

```scss
.nav-tabs {
  margin-top: -10px;
  background-color: white;
  margin-left: 0;

  > li.nav-item {
    margin-bottom: -4px;

    a.nav-link {
      padding: 11px 20px;
      margin-right: 0;
      min-width: 60px;
      text-align: center;
      border-radius: 0;
      color: $body-color;
      border-width: 0;
      outline: none;

      .icon {
        font-size: 1.538rem;
        vertical-align: middle;
        margin-right: 6px;
        line-height: 17px;
      }

      &:hover {
        background: transparent;
        color: $primary;
      }

      &:active {
        background-color: transparent;
      }

      &.active {
        background: transparent;
        border-bottom: 1px solid $primary;

        .icon {
          color: #555;
        }

        &:hover, &:focus {
          color: $body-color;
        }
      }
    }
  }
}

.tab-content {
  background: white;
  padding: 20px;
  margin-bottom: 40px;
  border-radius: 0 0 3px 3px;

  .tab-pane {
    h1, h2, h3, h4, h5, h6 {
      &:first-child {
        margin-top: 5px;
      }
    }
  }
}

@mixin tabs-color($color) {
  > li.nav-item {
    a.nav-link {
      &:hover, &:focus {
        color: $color;
      }
      &.active {
        border-bottom: 2px solid $color;
      }
    }
  }
}

.nav-tabs-success { @include tabs-color($success); }
.nav-tabs-warning { @include tabs-color($warning); }
.nav-tabs-danger  { @include tabs-color($danger); }
```

Changes: `theme-color("primary")` → `$primary`, `theme-color("success")` → `$success`, etc. The `theme-color()` function was removed in BS5.

- [ ] **Step 6: Update plugin/_datetimepicker.scss**

Replace all `theme-color("...")` calls with direct variables. Also replace `@include box-shadow(...)` with native `box-shadow:` (BS5 removed the mixin). Also replace `input-group-addon` with `input-group-text`:

```scss
/*------------------------------------------------------------------
  [Bootstrap dateTime Picker]
*/
.datetimepicker {
  padding: 4px 12px;

  &.input-group {
    padding: 4px 0;
  }

  .input-group-text {
    padding: 0 13px;
    font-size: 1.846rem;
    line-height: 23px;

    > i {
      vertical-align: middle;
    }
  }

  &.input-group-sm {
    .input-group-text {
      font-size: 1.538rem;
      line-height: 21px;
      padding: 0 11px;
    }
  }

  &.input-group-lg {
    .input-group-text {
      padding: 0 15px;
    }
  }

  table {
    border-collapse: separate;
    border-spacing: 7px 2px;

    thead tr th {
      padding: 10px 4px 8px;

      &.prev, &.next {
        padding: 0;
        > .icon { font-size: 1.615rem; }
        &:hover {
          background-color: transparent;
          color: $primary;
        }
      }

      &.switch {
        font-weight: 600;
        font-size: 1.077rem;
        &:hover {
          background-color: transparent;
          color: lighten($body-color, 10%);
        }
      }

      &.dow {
        font-weight: 400;
        font-size: 1.077rem;
        padding-top: 10px;
      }
    }

    tbody tr td {
      line-height: 31px;
      padding: 0 8px;

      &.day {
        border-radius: 50%;
        color: lighten($body-color, 20%);

        &.old, &.new { color: lighten($body-color, 45%); }

        &.active {
          background: $primary;
          color: $light;
          text-shadow: none;
          &:hover { background: darken($primary, 10%); }
        }
      }

      .year, .month, .hour, .minute {
        color: lighten($body-color, 20%);
        &.old, &.new { color: lighten($body-color, 45%); }
        &.active {
          background: $primary;
          color: $light;
          text-shadow: none;
          &:hover { background: darken($primary, 10%); }
        }
      }

      fieldset legend {
        font-size: 1.308rem;
        font-weight: 400;
        color: lighten($body-color, 10%);
        margin-bottom: 5px;
      }
    }
  }

  &.dropdown-menu {
    box-shadow: 0 2px 4px rgba(0, 0, 0, .08);
    border-color: rgba(0, 0, 0, 0.1);
    padding: 10px;

    &:before {
      border-bottom-color: rgba(0, 0, 0, 0.08);
    }
  }
}
```

- [ ] **Step 7: Update plugin/_select2.scss**

Replace `theme-color("...")` calls with direct variables. Also `$input-height` calculation may need review — BS5 changed how it's computed. Keep the variable reference and test.

```scss
$select2-border-width: 1px;

.select2-container--default {

  .select2-selection--single, .select2-selection--multiple {
    border: $select2-border-width solid $input-border-color;
    border-radius: $border-radius;
  }

  .select2-selection--single {
    height: $input-height;

    .select2-selection__rendered {
      padding: 0 15px;
      height: calc(#{$input-height} - #{$select2-border-width * 2});
      line-height: calc(#{$input-height} - #{$select2-border-width * 2});
      font-size: 1.077rem;
      color: $input-color;

      .select2-selection__clear {
        right: 25px;
        font-size: 1.538rem;
      }
    }

    .select2-selection__arrow {
      height: calc(#{$input-height} - .1538rem);
      width: 30px;

      b {
        border: 0;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        margin: 0;
      }
    }
  }

  .select2-selection--multiple {
    min-height: calc(#{$input-height} - #{$select2-border-width * 2});

    .select2-selection__clear {
      margin-top: 8px;
      margin-right: 0;
      font-size: 1.538rem;
    }

    .select2-selection__rendered {
      padding: 4px 12px;
    }

    .select2-selection__choice {
      border-radius: 0;
      background-color: darken($light, 5%);
      color: lighten($body-color, 10%);
      border-width: 0;
      padding: 4px 6px;
      line-height: 18px;
    }

    .select2-selection__choice__remove {
      color: lighten($body-color, 15%);
      margin-right: 3px;
      &:hover { color: lighten($body-color, 5%); }
    }

    .select2-search--inline .select2-search__field {
      line-height: calc(#{$input-height} - 1.692rem);
    }
  }

  &.select2-container--default.select2-container--focus {
    .select2-selection--multiple {
      border: $select2-border-width solid $input-border-color;
    }
  }

  .select2-results__group {
    font-size: 0.9231rem;
    color: lighten($body-color, 10%);
  }

  .select2-results__option {
    padding: 10px 6px;
  }

  .select2-results__option[aria-selected="true"] {
    background-color: darken($light, 3%);
  }

  .select2-results__option--highlighted[aria-selected] {
    background-color: $primary;
  }

  .select2-dropdown {
    z-index: $zindex-popover + 2;
    border-width: $select2-border-width;
    border-color: $input-border-color;

    &--above {
      border-radius: $border-radius $border-radius 0 0;
      box-shadow: 0 -2px 6px rgba(0, 0, 0, 0.12);
    }

    &--below {
      border-radius: 0 0 $border-radius $border-radius;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
    }
  }

  .select2-search--dropdown {
    background-color: lighten($input-border-color, 10%);
    border-bottom: $select2-border-width solid $input-border-color;

    .select2-search__field {
      background-color: transparent;
      border-width: 0;
      outline: none;
    }
  }
}
```

- [ ] **Step 8: Update layout/_header.scss**

Remove the duplicate `@import "./../variables";` at the top — variables are already imported via main.scss before this file. Keep the rest as-is (it uses `$gray-*` variables which still exist in BS5).

```scss
.main-header {
  width: 100%;
  height: $header-height;
  background: white;
  border-bottom: 1px solid #e3e3e3;

  .header-sections {
    width: 100%;
    height: 100%;
    display: flex;

    .header-section {
      height: 100%;
      display: flex;
      align-items: center;

      &.header-search {
        padding: 0 $base-padding;
        width: 20rem;

        .input-group-text, input {
          padding: 0.3rem 0.6rem;
        }
      }

      &.user-menu-section, &.mobile-menu-section {
        padding-right: $base-padding;
      }

      &.section-right {
        margin-left: auto;
      }
    }
  }
}

.qs-popover {
  padding: 0;

  .qs-entry {
    display: block;
    font-size: 0.9rem;
    padding: 0.5rem 1rem;

    &:hover {
      text-decoration: none;
      background: $gray-200;
    }

    p {
      margin: 0;
      &.qs-name { color: $gray-700; }
      &.qs-description {
        font-size: 0.8rem;
        color: $gray-600;
      }
    }
  }
}
```

- [ ] **Step 9: Update layout/_menu.scss**

Remove the duplicate `@import "variables";` line at top. Replace `@include user-select(none)` with native `user-select: none;`. Keep everything else.

Replace line 2 (`@import "variables";`) — remove it.
Replace line 113 (`@include user-select(none);`) with `user-select: none;`.

- [ ] **Step 10: Remove mixins/user-select.scss**

This mixin is no longer needed — all modern browsers support `user-select` natively and BS5 no longer includes this mixin.

```bash
rm frontend/assets/scss/mixins/user-select.scss
rmdir frontend/assets/scss/mixins
```

- [ ] **Step 11: Commit**

```bash
git add frontend/
git commit -m "Migrate SCSS partials for Bootstrap 5 compatibility"
```

---

### Task 5: First build test

- [ ] **Step 1: Run the build**

```bash
cd /home/iacopo/Documents/Sauvabelin/netBS5/frontend && npx encore production
```

- [ ] **Step 2: Fix any compilation errors**

If there are SCSS errors, they will show variable names or mixin references that don't exist in BS5. Fix them one by one:

Common issues to watch for:
- `theme-color("...")` → use `$primary`, `$success`, etc. directly
- `@include box-shadow(...)` → use native `box-shadow:` property
- `$spacer/2` → `calc($spacer / 2)` (dart-sass `/` deprecation)
- Removed variables like `$yiq-*`, `$custom-control-*`, `$enable-hover-media-query`, `$enable-print-styles` — delete any references
- `str-replace()` function — removed in BS5, but only used in variables we've already dropped

- [ ] **Step 3: Verify output files exist**

```bash
ls -la /home/iacopo/Documents/Sauvabelin/netBS5/netBS/core/CoreBundle/Resources/public/dist/
```

Expected: `app.css`, `app.js`, `manifest.json`, `runtime.js` (new in modern Encore), `entrypoints.json` (new)

- [ ] **Step 4: Commit**

```bash
git add frontend/ netBS/core/CoreBundle/Resources/public/dist/
git commit -m "First successful BS5 build — compiled dist files"
```

---

### Task 6: Update base.layout.twig for Encore runtime chunk

Modern Encore generates a `runtime.js` file. The base template needs to load it.

**Files:**
- Modify: `netBS/core/CoreBundle/Resources/views/layout/base.layout.twig`

- [ ] **Step 1: Update script loading in base.layout.twig**

Find the script tags at the bottom of the file (lines 36-39):

```html
<script src="{{ asset('bundles/netbscore/lib/jquery/jquery.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('bundles/netbscore/lib/bootstrap/js/bootstrap.bundle.min.js') }}" type="text/javascript"></script>

<script src="{{ asset('bundles/netbscore/dist/app.js') }}"></script>
```

Replace with:

```html
<script src="{{ asset('bundles/netbscore/lib/jquery/jquery.min.js') }}" type="text/javascript"></script>

<script src="{{ asset('bundles/netbscore/dist/runtime.js') }}"></script>
<script src="{{ asset('bundles/netbscore/dist/app.js') }}"></script>
```

Note: We remove the separate `bootstrap.bundle.min.js` include because Bootstrap JS is now bundled inside `app.js` via npm. jQuery stays as a separate script loaded before `app.js` so it's available when Bootstrap initializes.

- [ ] **Step 2: Verify the page loads**

Start the dev server (`docker-compose up`) and load any page. Check the browser console for JS errors. The CSS may look wrong (BS5 class changes not yet applied) — that's expected at this point.

- [ ] **Step 3: Commit**

```bash
git add netBS/core/CoreBundle/Resources/views/layout/base.layout.twig
git commit -m "Update base template for Encore runtime chunk and bundled Bootstrap JS"
```

---

### Task 7: Automated BS4 → BS5 class renames in Twig templates

This task handles all the simple find-and-replace class renames across all `.twig` files.

**Files:**
- Modify: All `.twig` files containing BS4 classes (run replacements across the repo)

- [ ] **Step 1: Margin/padding left-right → start-end**

Run these replacements across all `.twig` files. Be careful to match only CSS class contexts (inside `class="..."` or as standalone class tokens), not arbitrary text:

```
ml-   →  ms-
mr-   →  me-
pl-   →  ps-
pr-   →  pe-
```

These are safe to replace globally since `ml-0` through `ml-5`, `mr-0` through `mr-5`, etc. are only used as Bootstrap utility classes.

- [ ] **Step 2: Float and text alignment**

```
float-left   →  float-start
float-right  →  float-end
text-left    →  text-start
text-right   →  text-end
```

- [ ] **Step 3: No-gutters, btn-block, sr-only**

```
no-gutters   →  g-0
btn-block    →  w-100
sr-only      →  visually-hidden
```

Note: `btn-block` → `w-100` is a simplification. In BS4, `btn-block` made the button full-width AND added vertical spacing. The `w-100` class only handles width. For buttons inside flex containers or stacked buttons, you may need to wrap them in a `d-grid gap-2` container. Review visually after replacement.

- [ ] **Step 4: Badge classes**

In `.twig` files, replace:
```
badge-primary    →  text-bg-primary
badge-secondary  →  text-bg-secondary
badge-success    →  text-bg-success
badge-danger     →  text-bg-danger
badge-warning    →  text-bg-warning
badge-info       →  text-bg-info
badge-light      →  text-bg-light
badge-dark       →  text-bg-dark
badge-pill       →  rounded-pill
```

- [ ] **Step 5: Input group addon**

```
input-group-addon  →  input-group-text
```

(1 occurrence in `form/base.theme.twig`)

- [ ] **Step 6: Data attributes — add `bs-` prefix**

Across all `.twig` files:
```
data-toggle=      →  data-bs-toggle=
data-dismiss=     →  data-bs-dismiss=
data-target=      →  data-bs-target=
data-ride=        →  data-bs-ride=
data-slide=       →  data-bs-slide=
data-slide-to=    →  data-bs-slide-to=
data-parent=      →  data-bs-parent=
data-content=     →  data-bs-content=
data-placement=   →  data-bs-placement=
data-trigger=     →  data-bs-trigger=
data-spy=         →  data-bs-spy=
```

**Important:** Do NOT rename `data-modal`, `data-modal-url`, `data-modal-validate`, `data-type`, `data-helper`, `data-helper-id`, `data-helper-class`, `data-helper-placement`, `data-dynamic` — these are custom application attributes, not Bootstrap attributes.

49 occurrences across 21 files.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "Automated BS4→BS5 class and data-attribute renames in Twig templates"
```

---

### Task 8: Manual structural changes in Twig templates

These changes require understanding the surrounding markup, not just find-replace.

**Files:**
- Modify: `netBS/core/CoreBundle/Resources/views/renderer/ajax.renderer.twig`
- Modify: `netBS/core/CoreBundle/Resources/views/renderer/netbs.renderer.twig`
- Modify: `netBS/core/CoreBundle/Resources/views/layout/modal.layout.twig`
- Modify: `netBS/core/CoreBundle/Resources/views/export/check_export.html.twig`
- Modify: `netBS/core/CoreBundle/Resources/views/renderer/toolbar/logger.button.twig`
- Modify: `netBS/core/SecureBundle/Resources/views/login/login.html.twig`

- [ ] **Step 1: Replace custom-control checkboxes with form-check**

In `ajax.renderer.twig` and `netbs.renderer.twig`, replace:

```html
<div class="custom-control custom-checkbox">
    <input id="..." class="custom-control-input" type="checkbox">
    <label class="custom-control-label" for="..."></label>
</div>
```

With:

```html
<div class="form-check">
    <input id="..." class="form-check-input" type="checkbox">
    <label class="form-check-label" for="..."></label>
</div>
```

Also in `login.html.twig` if custom controls are used there.

- [ ] **Step 2: Replace close buttons**

In `modal.layout.twig` (line 10), replace:

```html
<button type="button" data-dismiss="modal" aria-hidden="true" class="close"><span class="fas fa-times"></span></button>
```

With:

```html
<button type="button" data-bs-dismiss="modal" aria-label="Close" class="btn-close"></button>
```

Do the same in `check_export.html.twig` and `logger.button.twig`.

- [ ] **Step 3: Review form-group usage**

`form-group` still works functionally in BS5 (it just adds `margin-bottom`), but the recommended replacement is `mb-3`. Since the custom `_form.scss` already targets `.form-group`, keep it as-is for now — it won't break anything.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "Manual BS5 structural changes: form-check, btn-close"
```

---

### Task 9: JavaScript migration

**Files:**
- Modify: `netBS/core/CoreBundle/Resources/views/layout/backend.layout.twig`
- Modify: `netBS/core/CoreBundle/Resources/views/helper/init.javascript.twig`
- Modify: `netBS/core/CoreBundle/Resources/public/js/modal.js`
- Modify: `netBS/iacopo/MailingBundle/Resources/public/js/mailing.js`

- [ ] **Step 1: Update tooltip init in backend.layout.twig**

Line 66, replace:

```html
<script>$(function(){ $('[data-toggle="tooltip"]').tooltip({html:true, trigger:'hover', container:'body'}); });</script>
```

With:

```html
<script>$(function(){ $('[data-bs-toggle="tooltip"]').tooltip({html:true, trigger:'hover', container:'body'}); });</script>
```

(The selector needs to match the new `data-bs-toggle` attribute we renamed in Task 7.)

- [ ] **Step 2: Update popover init in init.javascript.twig**

The jQuery `.popover()` call should still work in BS5 when jQuery is loaded (BS5 includes a jQuery plugin bridge). However, the `data-original-title` attribute was removed in BS5. Update the helper code:

Replace:
```javascript
elem.attr("data-original-title", response.title);
elem.attr("data-content", response.content);
elem.popover("show");
```

With:
```javascript
elem.popover('dispose');
elem.popover({
    html: true,
    placement: pos === null ? "top" : pos,
    container: 'body',
    title: response.title,
    content: response.content
});
elem.popover("show");
```

BS5 popovers need `title` and `content` passed as options, not as data attributes.

- [ ] **Step 3: Update modal.js**

The `modal.js` file uses jQuery-based `.modal()` calls. BS5 still supports these when jQuery is present. The main concern is the `data-dynamic` attribute in the generated HTML (line 50) — this is a custom attribute, not a Bootstrap one, so it's fine.

Review `modal.js` and verify that:
- `.modal()` calls work (they should — BS5 jQuery bridge)
- `hidden.bs.modal` event name is correct (it is — BS5 uses the same name)

No changes needed if jQuery is loaded before Bootstrap.

- [ ] **Step 4: Update badge classes in mailing.js**

In `netBS/iacopo/MailingBundle/Resources/public/js/mailing.js`, replace:

```javascript
'badge-success'    →  'text-bg-success'
'badge-secondary'  →  'text-bg-secondary'
```

Search for all `badge-` class references in this file and update them.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "Migrate JavaScript for BS5: tooltip selectors, popover API, badge classes"
```

---

### Task 10: Update Bootstrap library files

The project loads Bootstrap JS from `lib/bootstrap/js/bootstrap.bundle.min.js` (which is now bundled via webpack). But it also has standalone Bootstrap CSS in `lib/bootstrap/css/` that might be referenced elsewhere. We need to update the lib files to BS5 versions as a fallback.

**Files:**
- Modify: `netBS/core/CoreBundle/Resources/public/lib/bootstrap/`

- [ ] **Step 1: Update Bootstrap JS bundle**

Copy the BS5 `bootstrap.bundle.min.js` from the npm package to the lib directory:

```bash
cp /home/iacopo/Documents/Sauvabelin/netBS5/frontend/node_modules/bootstrap/dist/js/bootstrap.bundle.min.js \
   /home/iacopo/Documents/Sauvabelin/netBS5/netBS/core/CoreBundle/Resources/public/lib/bootstrap/js/bootstrap.bundle.min.js
```

Even though the base template no longer loads this directly (Task 6 removed it), the modal.layout.twig or other templates might load Bootstrap JS independently for modals rendered outside the main layout.

- [ ] **Step 2: Update Bootstrap CSS files**

```bash
cp /home/iacopo/Documents/Sauvabelin/netBS5/frontend/node_modules/bootstrap/dist/css/bootstrap.css \
   /home/iacopo/Documents/Sauvabelin/netBS5/netBS/core/CoreBundle/Resources/public/lib/bootstrap/css/bootstrap.css

cp /home/iacopo/Documents/Sauvabelin/netBS5/frontend/node_modules/bootstrap/dist/css/bootstrap.min.css \
   /home/iacopo/Documents/Sauvabelin/netBS5/netBS/core/CoreBundle/Resources/public/lib/bootstrap/css/bootstrap.min.css
```

- [ ] **Step 3: Commit**

```bash
git add netBS/core/CoreBundle/Resources/public/lib/bootstrap/
git commit -m "Update Bootstrap library files to v5.3"
```

---

### Task 11: Rebuild and visual verification

- [ ] **Step 1: Clean rebuild**

```bash
cd /home/iacopo/Documents/Sauvabelin/netBS5/frontend && npx encore production
```

Expected: builds without errors.

- [ ] **Step 2: Start the app**

```bash
cd /home/iacopo/Documents/Sauvabelin/netBS5 && docker-compose up -d
```

- [ ] **Step 3: Manual testing checklist**

Load each page type and verify visually:

- [ ] Dashboard — layout, cards, sidebar menu
- [ ] Member list — DataTables rendering, pagination, checkboxes
- [ ] Member detail — tabs, forms, badges
- [ ] Group view — statistics, links
- [ ] Billing page — DataTables, modals
- [ ] Mailing — email editor, badge colors
- [ ] Login page — form styling, checkbox
- [ ] Modals — open/close, form submission, tooltips
- [ ] Dropdowns — header dropdowns, select2
- [ ] Mobile — responsive layout, hamburger menu
- [ ] Popovers — helper popovers on hover
- [ ] Tooltips — on hover elements

- [ ] **Step 4: Fix any visual regressions found during testing**

Common things to look for:
- Spacing differences (BS5 changed some default margins/paddings)
- Button styling (especially `.btn-secondary` with our white background override)
- Form inputs height/padding
- Select2 height mismatch with form inputs
- Close button appearance in modals

- [ ] **Step 5: Final rebuild and commit**

```bash
cd /home/iacopo/Documents/Sauvabelin/netBS5/frontend && npx encore production
git add -A
git commit -m "Final BS5 migration: fix visual regressions from testing"
```
