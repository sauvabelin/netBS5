import { Controller } from '@hotwired/stimulus';
import { esc } from '../lib/ajax_search.js';

/*
 * Edits a rule (see NetBS\CoreBundle\Rule\Rule) as JSON in a hidden input.
 * Conditions: one sentence-like row each (« Si le membre a la fonction [CP] »), in one flat list
 * combined by the connector chosen on the second row (et / ou). The JSON format still allows one level
 * of nested groups (the server parses and evaluates them), but this editor no longer creates them: a rule
 * that has some is shown read-only until it is replaced.
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

    _at(index) {
        return this.rule.items[parseInt(index, 10)];
    }

    _hasNestedGroups() {
        return this.rule.mode === 'blocks' && this.rule.items.some((item) => Array.isArray(item.items));
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
            this.rule.op = el.value;
            this._render();
        } else if (field === 'phrase') {
            // "id" or "!id" for the negated form
            const condition = this._at(el.dataset.index);
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
            const condition = this._at(el.dataset.index);
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

        switch (button.dataset.actionName) {
            case 'add-condition':
                this.rule.items.push(this._newCondition());
                break;
            case 'remove':
                this.rule.items.splice(parseInt(button.dataset.index, 10), 1);
                break;
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
        } else if (this._hasNestedGroups()) {
            // saved by an older editor: kept and evaluated as is, but no longer editable here
            this.editorTarget.innerHTML = `<div class="rule-empty mb-2">Cette règle utilise des ensembles de conditions imbriqués,
                que l'éditeur ne gère plus. Elle reste appliquée telle quelle ; pour la modifier, remplacez-la.</div>`;
            this.modeTarget.innerHTML = '<button type="button" class="btn btn-link" data-action-name="to-blocks">Remplacer par des conditions</button>';
        } else {
            this.editorTarget.innerHTML = this._root();
            this.modeTarget.innerHTML = canExpert
                ? '<button type="button" class="btn btn-link text-muted" data-action-name="to-expert">Écrire une expression avancée<i class="fas fa-chevron-right ms-1"></i></button>'
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
        const add = `<button type="button" class="btn btn-link" data-action-name="add-condition"><i class="fas fa-plus me-1"></i>Ajouter une condition</button>`;
        if (root.items.length === 0) {
            return `<div class="rule-empty">${esc(this.configValue.emptyText)}</div>
                <div class="rule-add ms-0">${add}</div>`;
        }
        const rows = root.items.map((condition, i) => `<div class="rule-row">
                <div class="rule-connector">${this._connector(i)}</div>
                <div class="rule-body">${this._condition(condition, i)}</div>
                <button type="button" class="btn btn-sm btn-link rule-remove" data-action-name="remove" data-index="${i}" title="Retirer la condition" aria-label="Retirer la condition">
                    <i class="fas fa-times"></i></button>
            </div>`).join('');
        return `${rows}<div class="rule-add">${add}</div>`;
    }

    _connector(index) {
        // first row: « Si »; second row: the et / ou choice for the whole rule; then plain « et » / « ou »
        if (index === 0) return 'Si';
        if (index === 1) {
            return `<select class="form-select form-select-sm" data-field="op" title="Toutes les conditions (et) ou au moins une (ou)">
                <option value="and" ${this.rule.op === 'and' ? 'selected' : ''}>et</option>
                <option value="or" ${this.rule.op === 'or' ? 'selected' : ''}>ou</option>
            </select>`;
        }
        return this.rule.op === 'and' ? 'et' : 'ou';
    }

    _condition(condition, index) {
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
        const phrase = `<select class="form-select form-select-sm w-auto" data-field="phrase" data-index="${index}">
            ${options.map(([v, label]) => `<option value="${esc(v)}" ${v === selected ? 'selected' : ''}>${esc(label)}</option>`).join('')}
        </select>`;

        const params = block ? Object.entries(block.params).map(([name, p]) =>
            this._param(name, p, condition.params[name], index)).join('') : '';

        return phrase + params;
    }

    _param(name, p, value, index) {
        const attrs = `data-field="param:${esc(name)}" data-index="${index}"`;
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
