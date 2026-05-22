const FEEDBACK_CLASS = 'js-validation-feedback';

export function validateForm(form) {
    clearValidationErrors(form);
    if (form.checkValidity()) return true;

    let first = null;
    for (const el of form.elements) {
        if (!el.willValidate || el.checkValidity()) continue;
        // A required <select> with zero selectable options can never be made valid
        // by the user. Let it through so the server can render a meaningful error
        // instead of silently blocking the submit.
        if (el.tagName === 'SELECT' && !el.multiple && !hasSelectableOption(el)) continue;
        if (!first) first = el;
        markInvalid(el);
    }

    if (!first) return true;

    first.focus({ preventScroll: true });
    first.scrollIntoView({ block: 'center', behavior: 'smooth' });
    return false;
}

function hasSelectableOption(select) {
    return Array.from(select.options).some((opt) => opt.value !== '');
}

function clearValidationErrors(form) {
    form.querySelectorAll('.is-invalid').forEach((el) => {
        el.classList.remove('is-invalid');
        if (el._validationAbort) {
            el._validationAbort.abort();
            el._validationAbort = null;
        }
    });
    form.querySelectorAll('.' + FEEDBACK_CLASS).forEach((el) => el.remove());
}

function markInvalid(el) {
    el.classList.add('is-invalid');

    const feedback = document.createElement('div');
    feedback.className = `invalid-feedback d-block ${FEEDBACK_CLASS}`;
    feedback.textContent = frenchMessageFor(el);

    const anchor = el.closest('.input-group') || el;
    anchor.parentNode.insertBefore(feedback, anchor.nextSibling);

    const controller = new AbortController();
    el._validationAbort = controller;

    const onInput = () => {
        if (el.checkValidity()) {
            el.classList.remove('is-invalid');
            feedback.remove();
            controller.abort();
            el._validationAbort = null;
        } else {
            feedback.textContent = frenchMessageFor(el);
        }
    };
    el.addEventListener('input', onInput, { signal: controller.signal });
    el.addEventListener('change', onInput, { signal: controller.signal });
}

function frenchMessageFor(el) {
    const v = el.validity;
    if (v.valueMissing) {
        if (el.type === 'checkbox' || el.type === 'radio') return 'Veuillez cocher cette case.';
        if (el.tagName === 'SELECT') return 'Veuillez sélectionner une option.';
        return 'Ce champ est obligatoire.';
    }
    if (v.typeMismatch) {
        if (el.type === 'email') return 'Veuillez saisir une adresse email valide.';
        if (el.type === 'url') return 'Veuillez saisir une URL valide.';
        return "Le format saisi n'est pas valide.";
    }
    if (v.tooShort) return `Ce champ doit contenir au moins ${el.minLength} caractères (actuellement ${el.value.length}).`;
    if (v.tooLong) return `Ce champ doit contenir au plus ${el.maxLength} caractères.`;
    if (v.patternMismatch) return el.title || "Le format saisi n'est pas valide.";
    if (v.rangeUnderflow) return `La valeur doit être supérieure ou égale à ${el.min}.`;
    if (v.rangeOverflow) return `La valeur doit être inférieure ou égale à ${el.max}.`;
    if (v.stepMismatch) return "La valeur saisie n'est pas dans un incrément valide.";
    if (v.badInput) return "La valeur saisie n'est pas valide.";
    return 'Ce champ contient une erreur.';
}

function disableNativeValidation(root) {
    const forms = root instanceof HTMLFormElement ? [root] : root.querySelectorAll('form');
    forms.forEach((form) => {
        if (!form.hasAttribute('novalidate')) form.setAttribute('novalidate', 'novalidate');
    });
}

export function initGlobalFormValidation() {
    disableNativeValidation(document);

    document.addEventListener('turbo:load', () => disableNativeValidation(document));
    document.addEventListener('turbo:frame-load', (e) => {
        if (e.target) disableNativeValidation(e.target);
    });

    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.hasAttribute('data-skip-validation')) return;
        if (!validateForm(form)) {
            e.preventDefault();
            e.stopPropagation();
        }
    }, true);

    // Server-side validation errors are surfaced via HTTP 422 responses, set
    // globally by App\EventListener\InvalidFormStatusListener. Turbo natively
    // re-renders 4xx responses, so no client-side body-swap is needed.
}
