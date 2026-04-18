// Custom HTML5 form validation — in French, with Bootstrap 5 .is-invalid styling.
// Replaces the browser's native validation popup so:
//   - all invalid fields are flagged simultaneously (not one-at-a-time)
//   - messages are in French and context-specific
//   - invalid fields turn red via .is-invalid, with a matching .invalid-feedback below.

const FEEDBACK_CLASS = 'js-validation-feedback';

export function validateForm(form) {
    clearValidationErrors(form);

    const invalid = [];
    for (const el of form.elements) {
        if (!el.willValidate) continue;
        if (el.checkValidity()) continue;
        invalid.push(el);
        markInvalid(el);
    }

    if (invalid.length === 0) return true;

    invalid[0].focus({ preventScroll: true });
    invalid[0].scrollIntoView({ block: 'center', behavior: 'smooth' });
    return false;
}

export function clearValidationErrors(form) {
    form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
    form.querySelectorAll('.' + FEEDBACK_CLASS).forEach((el) => el.remove());
}

function markInvalid(el) {
    el.classList.add('is-invalid');

    const feedback = document.createElement('div');
    feedback.className = `invalid-feedback d-block ${FEEDBACK_CLASS}`;
    feedback.textContent = frenchMessageFor(el);

    const anchor = el.closest('.input-group') || el;
    anchor.parentNode.insertBefore(feedback, anchor.nextSibling);

    const onInput = () => {
        if (el.checkValidity()) {
            el.classList.remove('is-invalid');
            feedback.remove();
            el.removeEventListener('input', onInput);
            el.removeEventListener('change', onInput);
        } else {
            feedback.textContent = frenchMessageFor(el);
        }
    };
    el.addEventListener('input', onInput);
    el.addEventListener('change', onInput);
}

function frenchMessageFor(el) {
    const v = el.validity;
    if (v.valueMissing) {
        if (el.type === 'checkbox' || el.type === 'radio') return 'Veuillez cocher cette case.';
        if (el.tagName === 'SELECT') return 'Veuillez sélectionner une option.';
        return 'Ce champ est obligatoire.';
    }
    if (v.typeMismatch) {
        if (el.type === 'email') return "Veuillez saisir une adresse email valide.";
        if (el.type === 'url') return 'Veuillez saisir une URL valide.';
        return "Le format saisi n'est pas valide.";
    }
    if (v.tooShort) {
        return `Ce champ doit contenir au moins ${el.minLength} caractères (actuellement ${el.value.length}).`;
    }
    if (v.tooLong) {
        return `Ce champ doit contenir au plus ${el.maxLength} caractères.`;
    }
    if (v.patternMismatch) {
        return el.title || "Le format saisi n'est pas valide.";
    }
    if (v.rangeUnderflow) return `La valeur doit être supérieure ou égale à ${el.min}.`;
    if (v.rangeOverflow) return `La valeur doit être inférieure ou égale à ${el.max}.`;
    if (v.stepMismatch) return "La valeur saisie n'est pas dans un incrément valide.";
    if (v.badInput) return "La valeur saisie n'est pas valide.";
    return 'Ce champ contient une erreur.';
}

// Apply novalidate so the browser's native popup never fires — our custom
// validation handles it instead.
export function disableNativeValidation(root) {
    const scope = root instanceof HTMLFormElement ? null : root;
    const forms = scope ? scope.querySelectorAll('form') : [root];
    forms.forEach((form) => {
        if (!form.hasAttribute('novalidate')) {
            form.setAttribute('novalidate', 'novalidate');
        }
    });
}

export function initGlobalFormValidation() {
    disableNativeValidation(document);

    document.addEventListener('turbo:load', () => disableNativeValidation(document));
    document.addEventListener('turbo:frame-load', (e) => {
        if (e.target && e.target.querySelectorAll) disableNativeValidation(e.target);
    });
    document.addEventListener('turbo:render', () => disableNativeValidation(document));

    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.hasAttribute('data-skip-validation')) return;
        if (!validateForm(form)) {
            e.preventDefault();
            e.stopPropagation();
        }
    }, true);
}
