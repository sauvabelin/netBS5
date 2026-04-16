/**
 * Shared AJAX search/dropdown logic for entity selection.
 * Used by editable_controller.js and select2_controller.js.
 */

export function fetchResults(url, ajaxClass, query, nullOption) {
    if (!ajaxClass) return Promise.resolve([]);
    const params = new URLSearchParams({ ajaxClass, nullOption: nullOption || '0', query: query || '' });
    return fetch(`${url}?${params}`)
        .then((r) => r.json())
        .then((data) => data.results || data);
}

export function renderDropdownItems(items, container) {
    container.innerHTML = items.map((item) =>
        `<a href="#" class="list-group-item list-group-item-action py-1 px-2"
            data-id="${esc(item.id)}" data-text="${esc(item.text)}">
            ${esc(item.text)}
        </a>`
    ).join('') || '<span class="list-group-item py-1 px-2 text-muted">Aucun résultat</span>';
    container.style.display = 'block';
}

export function wireSearchInput(input, resultsContainer, searchFn) {
    let timer = null;

    const doSearch = (query) => {
        clearTimeout(timer);
        timer = setTimeout(() => searchFn(query), 300);
    };

    input.addEventListener('focus', () => doSearch(input.value.trim()));
    input.addEventListener('input', () => doSearch(input.value.trim()));
}

export function esc(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
