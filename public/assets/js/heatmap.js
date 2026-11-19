// The group heatmap: shading by head count, who-is-free details, one-person
// highlight, the legend, and the table version for screen readers.

import { el, fmt } from './util.js';
import { renderGrid, enableKeys, describe } from './grid.js';
import { heatSteps, levelFor } from './heat.js';

export class HeatGrid {
  #container;
  #copy;
  #onInspect;
  #view = null;
  #responses = [];
  #highlight = null;
  #inspected = null;

  /** onInspect(info) is called with a cell's details when it is hovered, tapped or focused. */
  constructor(container, { copy, onInspect = () => {} }) {
    this.#container = container;
    this.#copy = copy;
    this.#onInspect = onInspect;
  }

  get total() {
    return this.#responses.length;
  }

  render(model, label) {
    this.#view = renderGrid(this.#container, model, { variant: 'heat', label, copy: this.#copy });
    this.#inspected = null;
    for (const entry of this.#view.entries) {
      if (entry.cell) entry.el.append(el('span', 'grid__count', { 'aria-hidden': 'true' }));
    }
    const keys = enableKeys(this.#view, { onFocus: (entry) => this.#inspect(entry) });
    const inspectFrom = (event) => {
      const entry = keys.entryFor(event.target);
      if (entry?.cell) this.#inspect(entry);
    };
    this.#view.grid.addEventListener('pointerover', inspectFrom);
    this.#view.grid.addEventListener('click', inspectFrom);
    this.#update();
  }

  /** @param {{name: string, slots: Set}[]} responses */
  setResponses(responses) {
    this.#responses = responses;
    this.#update();
  }

  /** Show only one person's slots, or pass null to show everyone again. */
  setHighlight(name) {
    this.#highlight = name;
    this.#applyHighlight();
  }

  /** Details of every cell, row by row, for the table view. */
  rows() {
    if (!this.#view) return [];
    const rows = [];
    for (const entry of this.#view.entries) {
      (rows[entry.r] ??= { row: entry.row, cells: [] }).cells.push(entry.cell ? this.#info(entry) : null);
    }
    return rows;
  }

  #info(entry) {
    return {
      ...describe(entry),
      count: entry.available.length,
      total: this.#responses.length,
      available: entry.available,
      unavailable: this.#responses.map((r) => r.name).filter((name) => !entry.available.includes(name)),
    };
  }

  #inspect(entry) {
    if (this.#inspected) this.#inspected.el.removeAttribute('data-inspect');
    this.#inspected = entry;
    entry.el.setAttribute('data-inspect', '');
    this.#onInspect(this.#info(entry));
  }

  #update() {
    if (!this.#view) return;
    const steps = heatSteps();
    const total = this.#responses.length;
    for (const entry of this.#view.entries) {
      if (!entry.cell) continue;
      entry.available = this.#responses
        .filter((r) => entry.cell.keys.every((key) => r.slots.has(key)))
        .map((r) => r.name);
      const count = entry.available.length;
      const ratio = total ? count / total : 0;
      entry.el.dataset.count = String(count);
      entry.el.dataset.ratio = ratio.toFixed(4);
      entry.el.dataset.level = String(levelFor(ratio, steps));
      const text = fmt(this.#copy.cell_count, { ...describe(entry), count, total });
      entry.el.setAttribute('aria-label', text);
      entry.el.title = text;
      entry.el.firstElementChild.textContent = String(count);
    }
    this.#applyHighlight();
    if (this.#inspected) this.#onInspect(this.#info(this.#inspected));
  }

  #applyHighlight() {
    if (!this.#view) return;
    const person = this.#responses.find((r) => r.name === this.#highlight) ?? null;
    this.#view.grid.toggleAttribute('data-highlighting', person !== null);
    for (const entry of this.#view.entries) {
      if (!entry.cell) continue;
      entry.el.toggleAttribute('data-highlight', person !== null && entry.cell.keys.every((k) => person.slots.has(k)));
    }
  }
}

/** The scale from 0 to N people. One swatch per count, or per shade when N is large. */
export function renderLegend(container, total, copy) {
  const steps = heatSteps();
  const scale = el('span', 'legend__scale');
  const swatches = Math.min(total, steps);
  for (let i = 0; i <= swatches; i++) {
    const count = swatches === total ? i : Math.round((i / swatches) * total);
    const ratio = total ? count / total : 0;
    scale.append(
      el('span', 'legend__step', {
        'data-count': count,
        'data-ratio': ratio.toFixed(4),
        'data-level': levelFor(ratio, steps),
        title: fmt(copy.legend_scale, { count, total }),
      })
    );
  }
  container.replaceChildren(
    el('span', 'legend__label', {}, copy.legend_label),
    el('span', 'legend__end', {}, '0'),
    scale,
    el('span', 'legend__end', {}, String(total))
  );
}

/** The same information as the heatmap, as a real table (visually hidden). */
export function renderTable(container, model, heat, copy, caption) {
  const table = el('table');
  table.append(el('caption', '', {}, caption));
  const head = el('tr');
  head.append(el('th', '', { scope: 'col' }, copy.time_column));
  for (const column of model.columns) {
    head.append(el('th', '', { scope: 'col' }, column.date ? `${column.name} ${column.date}` : column.name));
  }
  const thead = el('thead');
  thead.append(head);
  const tbody = el('tbody');
  for (const { row, cells } of heat.rows()) {
    const tr = el('tr');
    tr.append(el('th', '', { scope: 'row' }, row.long));
    for (const info of cells) {
      const text = info
        ? fmt(copy.table_cell, { count: info.count, total: info.total, names: info.available.join(', ') || copy.table_nobody })
        : copy.table_void;
      tr.append(el('td', '', {}, text));
    }
    tbody.append(tr);
  }
  table.append(thead, tbody);
  container.replaceChildren(table);
}
