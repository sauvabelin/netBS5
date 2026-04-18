import { validateForm } from './form_validation.js';

export function submitForm(url, formElement) {
    if (!validateForm(formElement)) {
        return Promise.reject(new Error('validation'));
    }

    return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(new FormData(formElement)),
    }).then((response) => parseResponse(response));
}

export function parseResponse(response) {
    const code = response.status;

    return response.text().then((text) => {
        if (code === 202) {
            const data = JSON.parse(text);
            return { action: 'toast', ...data };
        }
        if (code === 201) {
            return { action: 'reload' };
        }

        let data;
        try { data = JSON.parse(text); } catch (e) { data = text; }

        if (data === 'redirected') {
            return { action: 'redirect', location: response.headers.get('Location') };
        }

        return { action: 'validation_error', html: text };
    });
}
