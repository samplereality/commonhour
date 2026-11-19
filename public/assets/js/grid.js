// The grid: rendering, painting your own availability, and autosave.
//
// Markup (the same for both grids, so a theme styles it once):
//
//   .grid.grid--mine | .grid--heat        role=grid
//     .grid__row.grid__row--head          day names
//     .grid__row[data-rule][data-gap]     one per clock time
//       .grid__time                       label
//       .grid__cell[data-selected]        one per day
//       .grid__cell.grid__cell--void      no slot here in this time zone
//
// Scripts only ever toggle attributes; all appearance is in the stylesheets.

import { el, fmt } from './util.js';

/**
 * Draw a grid into `container`.
 * Returns { grid, entries, at(col, row) }; each entry is
 * { el, cell (or null if void), column, row, c, r }.
 */
export function renderGrid(container, model, { variant, label, copy }) {
  const grid = el('div', `grid grid--${variant}`, { role: 'grid', 'aria-label': label });

  const head = el('div', 'grid__row grid__row--head', { role: 'row' });
  const corner = el('div', 'grid__corner', { role: 'columnheader' });
  corner.append(el('span', 'visually-hidden', {}, copy.time_column));
  head.append(corner);
  for (const column of model.columns) {
    const day = el('div', 'grid__day', { role: 'columnheader' });
    day.append(el('span', 'grid__day-name', {}, column.name));
    if (column.date) day.append(el('span', 'grid__day-date', {}, column.date));
    head.append(day);
  }
  grid.append(head);

  const entries = [];
  const lookup = [];
  model.rows.forEach((row, r) => {
    const rowEl = el('div', 'grid__row', { role: 'row', 'data-rule': row.rule, 'data-gap': row.gap });
    const time = el('div', 'grid__time', { role: 'rowheader' });
    time.append(el('span', row.labelled ? 'grid__time-label' : 'visually-hidden', {}, row.labelled ? row.short : row.long));
    rowEl.append(time);
    lookup.push([]);
    model.columns.forEach((column, c) => {
      const cell = model.cells.get(`${column.key}|${row.minute}`) ?? null;
      const cellEl = el('div', cell ? 'grid__cell' : 'grid__cell grid__cell--void', {
        role: 'gridcell',
        tabindex: '-1',
        'aria-disabled': cell ? null : 'true',
      });
      const entry = { el: cellEl, cell, column, row, c, r };
      entries.push(entry);
      lookup[r].push(entry);
      rowEl.append(cellEl);
    });
    grid.append(rowEl);
  });

  const scroll = el('div', 'grid-scroll');
  scroll.append(grid);
  container.replaceChildren(scroll);

  return { grid, entries, at: (c, r) => lookup[r]?.[c] ?? null };
}

/** "Mon Nov 16 9:30 AM" for a cell, used in accessible labels. */
export function describe(entry) {
  return {
    day: entry.column.date ? `${entry.column.name} ${entry.column.date}` : entry.column.name,
    time: entry.row.long,
  };
}

/**
 * Arrow-key movement with a single tab stop. Calls onFocus(entry) when the
 * focused cell changes and onKey(event, entry) for other keys.
 */
export function enableKeys(view, { onFocus, onKey }) {
  const byEl = new Map(view.entries.map((entry) => [entry.el, entry]));
  let current = view.entries.find((entry) => entry.cell) ?? null;
  if (current) current.el.tabIndex = 0;

  const setCurrent = (entry, focus) => {
    if (!entry || !entry.cell) return;
    if (current) current.el.tabIndex = -1;
    current = entry;
    entry.el.tabIndex = 0;
    if (focus) entry.el.focus();
  };

  const step = (from, dc, dr) => {
    let { c, r } = from;
    for (;;) {
      c += dc;
      r += dr;
      const next = view.at(c, r);
      if (!next) return null;
      if (next.cell) return next; // skip positions with no slot
    }
  };

  const arrows = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] };

  view.grid.addEventListener('keydown', (event) => {
    const entry = byEl.get(event.target);
    if (!entry) return;
    const delta = arrows[event.key];
    if (delta) {
      event.preventDefault();
      const next = step(entry, delta[0], delta[1]);
      if (next) {
        setCurrent(next, true);
        onKey?.(event, next, entry);
      }
      return;
    }
    onKey?.(event, entry, entry);
  });

  view.grid.addEventListener('focusin', (event) => {
    const entry = byEl.get(event.target);
    if (entry) onFocus?.(entry);
  });

  return { entryFor: (node) => byEl.get(node?.closest?.('.grid__cell')) ?? null, setCurrent };
}

/**
 * Your own availability. Press and drag paints a rectangle from the first
 * cell to the current one; starting on an empty cell adds, starting on a
 * filled cell removes. Shift+arrows does the same from the keyboard, and
 * space toggles one cell.
 */
export class PaintGrid {
  #container;
  #copy;
  #onChange;
  #view = null;
  #keys = null;
  #selected = new Set();
  #drag = null;
  #keyDrag = null;
  #touchMode = 'paint';
  #readOnly = false;

  constructor(container, { copy, onChange = () => {}, readOnly = false }) {
    this.#container = container;
    this.#copy = copy;
    this.#onChange = onChange;
    this.#readOnly = readOnly;
  }

  get selected() {
    return new Set(this.#selected);
  }

  setSelected(slots) {
    this.#selected = new Set(slots);
    this.#paint();
  }

  /** 'paint': touches paint and the grid does not scroll. 'scroll': the reverse. */
  setTouchMode(mode) {
    this.#touchMode = mode;
    if (this.#view) this.#view.grid.dataset.touch = mode;
  }

  render(model, label) {
    this.#drag = this.#keyDrag = null;
    this.#view = renderGrid(this.#container, model, { variant: 'mine', label, copy: this.#copy });
    const { grid } = this.#view;
    grid.dataset.touch = this.#touchMode;
    grid.setAttribute('aria-multiselectable', 'true');
    this.#paint();
    if (this.#readOnly) return;

    this.#keys = enableKeys(this.#view, {
      onKey: (event, entry, from) => this.#key(event, entry, from),
    });
    grid.addEventListener('pointerdown', (e) => this.#down(e));
    grid.addEventListener('pointermove', (e) => this.#move(e));
    grid.addEventListener('pointerup', (e) => this.#up(e));
    grid.addEventListener('pointercancel', (e) => this.#cancel(e));
    grid.addEventListener('keyup', (e) => {
      if (e.key === 'Shift') this.#keyDrag = null;
    });
    grid.addEventListener('focusout', (e) => {
      if (!grid.contains(e.relatedTarget)) this.#keyDrag = null; // focus left the grid
    });
  }

  #isOn(entry, set = this.#selected) {
    return entry.cell !== null && entry.cell.keys.every((key) => set.has(key));
  }

  #paint() {
    if (!this.#view) return;
    for (const entry of this.#view.entries) {
      if (!entry.cell) continue;
      const on = this.#isOn(entry);
      if (on === entry.on) continue;
      entry.on = on;
      entry.el.toggleAttribute('data-selected', on);
      entry.el.setAttribute('aria-selected', String(on));
      entry.el.setAttribute('aria-label', fmt(on ? this.#copy.cell_free : this.#copy.cell_empty, describe(entry)));
    }
  }

  /** The selection `base` with the rectangle between two cells added or removed. */
  #withRect(base, a, b, mode) {
    const next = new Set(base);
    const [c1, c2] = [Math.min(a.c, b.c), Math.max(a.c, b.c)];
    const [r1, r2] = [Math.min(a.r, b.r), Math.max(a.r, b.r)];
    for (let r = r1; r <= r2; r++) {
      for (let c = c1; c <= c2; c++) {
        const entry = this.#view.at(c, r);
        if (!entry?.cell) continue;
        for (const key of entry.cell.keys) {
          if (mode === 'add') next.add(key);
          else next.delete(key);
        }
      }
    }
    return next;
  }

  #entryAt(x, y) {
    const node = document.elementFromPoint(x, y);
    const entry = this.#keys.entryFor(node);
    return entry && this.#view.grid.contains(entry.el) ? entry : null;
  }

  #down(event) {
    if (event.pointerType === 'mouse' && event.button !== 0) return;
    if (event.pointerType === 'touch' && this.#touchMode === 'scroll') return;
    const entry = this.#keys.entryFor(event.target);
    if (!entry?.cell || this.#drag) return;
    event.preventDefault();
    this.#view.grid.setPointerCapture(event.pointerId);
    this.#keys.setCurrent(entry, false);
    this.#drag = {
      id: event.pointerId,
      anchor: entry,
      last: entry,
      mode: this.#isOn(entry) ? 'remove' : 'add',
      base: new Set(this.#selected),
    };
    this.#view.grid.dataset.painting = this.#drag.mode;
    this.#selected = this.#withRect(this.#drag.base, entry, entry, this.#drag.mode);
    this.#paint();
  }

  #move(event) {
    const drag = this.#drag;
    if (!drag || event.pointerId !== drag.id) return;
    const entry = this.#entryAt(event.clientX, event.clientY);
    if (!entry || entry === drag.last) return;
    drag.last = entry;
    this.#selected = this.#withRect(drag.base, drag.anchor, entry, drag.mode);
    this.#paint();
  }

  #up(event) {
    if (!this.#drag || event.pointerId !== this.#drag.id) return;
    this.#drag = null;
    delete this.#view.grid.dataset.painting;
    this.#onChange(this.selected);
  }

  #cancel(event) {
    if (!this.#drag || event.pointerId !== this.#drag.id) return;
    this.#selected = this.#drag.base;
    this.#drag = null;
    delete this.#view.grid.dataset.painting;
    this.#paint();
  }

  #key(event, entry, from) {
    if (event.key === ' ' || event.key === 'Enter') {
      event.preventDefault();
      this.#keyDrag = null;
      this.#selected = this.#withRect(this.#selected, entry, entry, this.#isOn(entry) ? 'remove' : 'add');
      this.#paint();
      this.#onChange(this.selected);
      return;
    }
    if (!event.key.startsWith('Arrow')) return;
    if (!event.shiftKey) {
      this.#keyDrag = null;
      return;
    }
    // Shift+arrow: paint from the cell where shift was first held.
    if (!this.#keyDrag) {
      this.#keyDrag = { anchor: from, mode: this.#isOn(from) ? 'remove' : 'add', base: new Set(this.#selected) };
    }
    this.#selected = this.#withRect(this.#keyDrag.base, this.#keyDrag.anchor, entry, this.#keyDrag.mode);
    this.#paint();
    this.#onChange(this.selected);
  }
}

/**
 * Saves in the background a short while after the last change.
 * `save(payload)` returns a promise; reject with { fatal: true } to stop retrying.
 * `onState` hears 'saving', 'saved' or 'error'.
 */
export function createAutosaver({ save, delay = 500, onState = () => {} }) {
  let timer = null;
  let pending = null;
  let saving = false;
  let failures = 0;

  async function flush() {
    clearTimeout(timer);
    if (saving || pending === null) return;
    const payload = pending;
    pending = null;
    saving = true;
    onState('saving');
    try {
      await save(payload);
      saving = false;
      failures = 0;
      if (pending !== null) flush();
      else onState('saved');
    } catch (error) {
      saving = false;
      onState('error');
      if (error?.fatal) return;
      if (pending === null) pending = payload; // keep the newest unsaved state
      failures = Math.min(failures + 1, 6);
      timer = setTimeout(flush, 1500 * failures);
    }
  }

  return {
    queue(payload) {
      pending = payload;
      onState('saving');
      clearTimeout(timer);
      timer = setTimeout(flush, delay);
    },
    flush,
    get busy() {
      return saving || pending !== null;
    },
  };
}
