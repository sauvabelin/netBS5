import { Controller } from '@hotwired/stimulus';
import { esc } from '../lib/ajax_search.js';

/*
 * Edits a rule (see NetBS\CoreBundle\Rule\Rule) as JSON in a hidden input.
 * Conditions: one sentence-like row each (« Si le membre a la fonction [CP] »), combined by the
 * connector chosen on the second row of their group (et / ou), with at most one level of nested groups.
 * Expression: one expert expression, editable by admins only (config.canExpert).
 */
export default class extends Controller {
    static targets = ['input', 'editor', 'summary', 'sentence', 'errors', 'mode', 'glossary'];
    static values = {
        context: String,
        config: Object,
        describeUrl: String,
        select2Url: String,
    };

    connect() {
        this.labels = { ...(this.configValue.labels || {}) };
        this.rule = this._parse(this.inputTarget.value);
        this._render();
        // kept so disconnect() can remove them: a reconnect (modal re-attach, Turbo restore)
        // would otherwise register them twice and one click would add two conditions
        this._listeners = [
            // capture: the select2 controller dispatches non-bubbling change events on its select
            ['change', (e) => this._onChange(e), true],
            ['input', (e) => this._onInput(e), false],
            ['click', (e) => this._onClick(e), false],
        ];
        this._listeners.forEach(([type, handler, capture]) => this.element.addEventListener(type, handler, capture));
    }

    disconnect() {
        clearTimeout(this._timer);
        this._listeners.forEach(([type, handler, capture]) => this.element.removeEventListener(type, handler, capture));
    }

    // --- state ---

    _parse(json) {
        try {
            const rule = JSON.parse(json || '');
            if (rule && (rule.mode === 'expert' || (rule.mode === 'blocks' && Array.isArray(rule.items)))) return rule;
        } catch (e) { /* empty or invalid: start fresh */ }
        return this._emptyRule();
    }

    _emptyRule() {
        return { mode: 'blocks', op: 'and', items: [] };
    }

    _isEmpty() {
        return this.rule.mode === 'expert' ? (this.rule.expression || '').trim() === '' : this.rule.items.length === 0;
    }

    _newCondition() {
        const first = Object.keys(this.configValue.blocks)[0];
        return { block: first, not: false, params: this._defaultParams(first) };
    }

    _defaultParams(blockId) {
        const params = {};
        const block = this.configValue.blocks[blockId];
        if (!block) return params;
        Object.entries(block.params).forEach(([name, p]) => {
            if (p.kind === 'operator') params[name] = 'eq';
            else if (p.kind === 'choice') params[name] = Object.keys(p.choices)[0];
            else if (p.kind === 'int') params[name] = p.min ?? 0;
            else if (p.kind === 'entities') params[name] = [];
        });
        return params;
    }

    _at(path) {
        // path "" = root group, "2" = root item 2, "2.1" = item 1 of nested group 2
        let node = this.rule;
        if (path === '') return node;
        path.split('.').forEach((i) => { node = node.items[parseInt(i, 10)]; });
        return node;
    }

    _parentAndIndex(path) {
        const parts = path.split('.');
        const index = parseInt(parts.pop(), 10);
        return [this._at(parts.join('.')), index];
    }

    _sync() {
        this.inputTarget.value = JSON.stringify(this.rule);
        this._renderSummary();
        clearTimeout(this._timer);
        this._timer = setTimeout(() => this._describe(), 300);
    }

    async _describe() {
        try {
            const response = await fetch(this.describeUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ context: this.contextValue, rule: this.rule }),
            });
            if (!response.ok) return;
            const data = await response.json();
            this.sentenceTarget.textContent = data.sentence;
            this.errorsTarget.innerHTML = data.errors.map((m) => `<div>${esc(m)}</div>`).join('');
        } catch (e) { /* the server validates again on save */ }
    }

    // --- events ---

    _onChange(e) {
        const el = e.target;
        const field = el.dataset.field;
        if (!field) return;

        if (field === 'op') {
            this._at(el.dataset.path).op = el.value;
            this._render();
        } else if (field === 'phrase') {
            // "id" or "!id" for the negated form
            const condition = this._at(el.dataset.path);
            const not = el.value.startsWith('!');
            const block = not ? el.value.substring(1) : el.value;
            if (block !== condition.block) {
                condition.block = block;
                condition.params = this._defaultParams(block);
            }
            condition.not = not;
            this._render();
        } else if (field.startsWith('param:')) {
            const name = field.substring(6);
            const condition = this._at(el.dataset.path);
            if (el.multiple) {
                const target = el.dataset.target;
                condition.params[name] = Array.from(el.selectedOptions).map((o) => {
                    this.labels[`${target}:${o.value}`] = o.text;
                    return parseInt(o.value, 10);
                });
            } else if (el.type === 'number') {
                condition.params[name] = el.value === '' ? null : parseInt(el.value, 10);
            } else {
                condition.params[name] = el.value;
            }
        } else {
            return;
        }
        this._sync();
    }

    _onInput(e) {
        if (e.target.dataset.field === 'expression') {
            this.rule.expression = e.target.value;
            this._sync();
        }
    }

    _onClick(e) {
        const button = e.target.closest('[data-action-name]');
        if (!button) return;
        e.preventDefault();

        const path = button.dataset.path;
        switch (button.dataset.actionName) {
            case 'add-condition':
                this._at(path).items.push(this._newCondition());
                break;
            case 'add-group':
                this.rule.items.push({ op: 'or', items: [this._newCondition(), this._newCondition()] });
                break;
            case 'remove': {
                const [parent, index] = this._parentAndIndex(path);
                parent.items.splice(index, 1);
                // an empty nested group is invalid: drop it with its last condition
                if (parent !== this.rule && parent.items.length === 0) {
                    this.rule.items.splice(this.rule.items.indexOf(parent), 1);
                }
                break;
            }
            case 'to-expert':
                if (!this._isEmpty() && !window.confirm('Les conditions actuelles seront effacées et remplacées par une expression. Continuer ?')) return;
                this.rule = { mode: 'expert', expression: '' };
                this.glossaryTarget.open = true;
                break;
            case 'to-blocks':
                if (!this._isEmpty() && !window.confirm("L'expression sera effacée et remplacée par des conditions. Continuer ?")) return;
                this.rule = this._emptyRule();
                break;
            default:
                return;
        }
        this._render();
        this._sync();
    }

    // --- rendering ---

    _render() {
        const expert = this.rule.mode === 'expert';
        const canExpert = this.configValue.canExpert;
        this.glossaryTarget.hidden = !expert;

        if (expert && !canExpert) {
            this.editorTarget.innerHTML = `<div class="mb-2">
                <span class="badge text-bg-warning">Expression avancée</span>
                <span class="small text-muted">seul un administrateur peut la modifier</span>
                <pre class="bg-body-tertiary border rounded p-2 mt-1 mb-0 small">${esc(this.rule.expression)}</pre></div>`;
            this.modeTarget.innerHTML = '<button type="button" class="btn btn-link" data-action-name="to-blocks">Remplacer par des conditions</button>';
        } else if (expert) {
            this.editorTarget.innerHTML = `<textarea class="form-control font-monospace mb-2" rows="3" data-field="expression"
                placeholder="${esc(this.configValue.example || '')}">${esc(this.rule.expression)}</textarea>`;
            this.modeTarget.innerHTML = '<button type="button" class="btn btn-link" data-action-name="to-blocks"><i class="fas fa-chevron-left me-1"></i>Revenir aux conditions</button>';
        } else {
            this.editorTarget.innerHTML = this._root();
            this.modeTarget.innerHTML = canExpert
                ? '<button type="button" class="btn btn-link text-muted" data-action-name="to-expert">Écrire une expression (avancé)<i class="fas fa-chevron-right ms-1"></i></button>'
                : '';
        }
        this._renderSummary();
    }

    _renderSummary() {
        // the summary repeats the conditions as a sentence; an expression already is one
        this.summaryTarget.hidden = this.rule.mode === 'expert' || this._isEmpty();
    }

    _root() {
        const root = this.rule;
        if (root.items.length === 0) {
            return `<div class="rule-empty">${esc(this.configValue.emptyText)}</div>
                <div class="rule-add ms-0">${this._addButton('add-condition', '', 'Ajouter une condition')}</div>`;
        }
        return `${this._rows(root, '', true)}
            <div class="rule-add">
                ${this._addButton('add-condition', '', 'Ajouter une condition')}
                ${this._addButton('add-group', '', 'Ajouter un ensemble de conditions')}
            </div>`;
    }

    _addButton(action, path, label) {
        return `<button type="button" class="btn btn-link" data-action-name="${action}" data-path="${path}"><i class="fas fa-plus me-1"></i>${label}</button>`;
    }

    _rows(group, path, root) {
        const prefix = path === '' ? '' : `${path}.`;
        return group.items.map((item, i) => {
            const itemPath = `${prefix}${i}`;
            const body = item.items ? this._nested(item, itemPath) : `<div class="rule-body">${this._condition(item, itemPath)}</div>`;
            const title = item.items ? 'Retirer l\'ensemble' : 'Retirer la condition';
            return `<div class="rule-row">
                <div class="rule-connector">${this._connector(group, path, i, root)}</div>
                ${body}
                <button type="button" class="btn btn-sm btn-link rule-remove" data-action-name="remove" data-path="${itemPath}" title="${title}" aria-label="${title}">
                    <i class="fas fa-times"></i></button>
            </div>`;
        }).join('');
    }

    _connector(group, path, index, root) {
        // first row: « Si » (nothing inside a group); second row: the et / ou choice for the whole group
        if (index === 0) return root ? 'Si' : '';
        if (index === 1) {
            return `<select class="form-select form-select-sm" data-field="op" data-path="${path}" title="Toutes les conditions (et) ou au moins une (ou)">
                <option value="and" ${group.op === 'and' ? 'selected' : ''}>et</option>
                <option value="or" ${group.op === 'or' ? 'selected' : ''}>ou</option>
            </select>`;
        }
        return group.op === 'and' ? 'et' : 'ou';
    }

    _nested(group, path) {
        return `<div class="rule-group-nested">
            ${this._rows(group, path, false)}
            <div class="rule-add">${this._addButton('add-condition', path, 'Ajouter une condition')}</div>
        </div>`;
    }

    _condition(condition, path) {
        const blocks = this.configValue.blocks;
        const block = blocks[condition.block];
        const selected = condition.not ? `!${condition.block}` : condition.block;

        const options = [];
        if (!block) options.push([selected, `condition supprimée ou inconnue (${condition.block})`]);
        Object.entries(blocks).forEach(([id, b]) => {
            options.push([id, b.label]);
            if (b.negatedLabel) options.push([`!${id}`, b.negatedLabel]);
            // a negation saved without a negated form (e.g. by an older editor) stays visible
            else if (condition.not && id === condition.block) options.push([`!${id}`, `non : ${b.label}`]);
        });
        const phrase = `<select class="form-select form-select-sm w-auto" data-field="phrase" data-path="${path}">
            ${options.map(([v, label]) => `<option value="${esc(v)}" ${v === selected ? 'selected' : ''}>${esc(label)}</option>`).join('')}
        </select>`;

        const params = block ? Object.entries(block.params).map(([name, p]) =>
            this._param(name, p, condition.params[name], path)).join('') : '';

        return phrase + params;
    }

    _param(name, p, value, path) {
        const attrs = `data-field="param:${esc(name)}" data-path="${path}"`;
        if (p.kind === 'operator' || p.kind === 'choice') {
            const options = Object.entries(p.choices).map(([v, label]) =>
                `<option value="${esc(v)}" ${v === value ? 'selected' : ''}>${esc(label)}</option>`).join('');
            return `<select class="form-select form-select-sm w-auto" ${attrs}>${options}</select>`;
        }
        if (p.kind === 'int') {
            return `<input type="number" class="form-control form-control-sm" style="width:6rem" ${attrs}
                value="${esc(String(value ?? ''))}" ${p.min !== null ? `min="${p.min}"` : ''} ${p.max !== null ? `max="${p.max}"` : ''}>`;
        }
        // entities: ajax multi-select handled by the select2 controller
        const ids = Array.isArray(value) ? value : [];
        const options = ids.map((id) => {
            const label = this.labels[`${p.target}:${id}`] ?? `#${id} (supprimé)`;
            return `<option value="${esc(String(id))}" selected>${esc(label)}</option>`;
        }).join('');
        return `<div data-controller="select2" data-select2-ajax-class-value="${esc(p.ajaxClass)}"
                     data-select2-url-value="${esc(this.select2UrlValue)}" style="min-width:160px;flex:1">
                    <select multiple ${attrs} data-target="${esc(p.target)}">${options}</select>
                </div>`;
    }
}
