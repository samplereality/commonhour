// A month calendar for picking dates. Click a day to toggle it, or press and
// drag across days to paint a block: starting on an unselected day adds,
// starting on a selected day removes.

import { el, fmt } from './util.js';

const iso = (y, m, d) => `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;

export class Calendar {
  #root;
  #copy;
  #onChange;
  #selected;
  #min;
  #locked;
  #year;
  #month;
  #days = []; // { el, date, week, weekday, disabled }
  #drag = null;
  #locale;

  /**
   * `min` is the earliest pickable date (ISO); earlier days are disabled.
   * `locked` dates are part of the selection and cannot be taken out of it.
   */
  constructor(root, { copy, selected = [], locked = [], min = null, onChange = () => {}, month = null }) {
    this.#root = root;
    this.#copy = copy;
    this.#onChange = onChange;
    this.#locked = new Set(locked);
    this.#selected = new Set([...selected, ...locked]);
    this.#min = min;
    this.#locale = document.documentElement.lang || 'en';
    const start = month ?? min ?? iso(new Date().getFullYear(), new Date().getMonth(), 1);
    this.#year = Number(start.slice(0, 4));
    this.#month = Number(start.slice(5, 7)) - 1;
    this.#draw();
  }

  get selected() {
    return [...this.#selected].sort();
  }

  #draw() {
    const utc = (o) => new Intl.DateTimeFormat(this.#locale, { timeZone: 'UTC', ...o });
    const first = new Date(Date.UTC(this.#year, this.#month, 1));
    const daysInMonth = new Date(Date.UTC(this.#year, this.#month + 1, 0)).getUTCDate();
    const fullDate = utc({ weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });

    const prev = el('button', 'button button--quiet calendar__nav', { type: 'button', 'aria-label': this.#copy.calendar_prev }, '‹');
    const next = el('button', 'button button--quiet calendar__nav', { type: 'button', 'aria-label': this.#copy.calendar_next }, '›');
    prev.disabled = this.#min !== null && iso(this.#year, this.#month, 1) <= this.#min.slice(0, 8) + '01';
    prev.addEventListener('click', () => this.#go(-1));
    next.addEventListener('click', () => this.#go(1));
    const head = el('div', 'calendar__head');
    head.append(prev, el('span', 'calendar__month', { 'aria-live': 'polite' }, utc({ month: 'long', year: 'numeric' }).format(first)), next);

    const grid = el('div', 'calendar__grid');
    const weekdayName = utc({ weekday: 'short' });
    for (let w = 0; w < 7; w++) {
      grid.append(el('span', 'calendar__weekday', { 'aria-hidden': 'true' }, weekdayName.format(new Date(Date.UTC(2023, 0, 1 + w)))));
    }
    for (let blank = 0; blank < first.getUTCDay(); blank++) {
      grid.append(el('span', 'calendar__day calendar__day--blank', { 'aria-hidden': 'true' }));
    }

    this.#days = [];
    for (let d = 1; d <= daysInMonth; d++) {
      const date = iso(this.#year, this.#month, d);
      const position = first.getUTCDay() + d - 1;
      const button = el('button', 'calendar__day', {
        type: 'button',
        'data-date': date,
        'aria-label': fullDate.format(new Date(Date.UTC(this.#year, this.#month, d))),
        tabindex: '-1',
      }, String(d));
      const day = { el: button, date, week: Math.floor(position / 7), weekday: position % 7 };
      day.disabled = (this.#min !== null && date < this.#min) || this.#locked.has(date);
      button.disabled = day.disabled;
      this.#days.push(day);
      grid.append(button);
    }
    (this.#days.find((d) => !d.disabled) ?? this.#days[0]).el.tabIndex = 0;

    grid.addEventListener('pointerdown', (e) => this.#down(e));
    grid.addEventListener('pointermove', (e) => this.#move(e));
    grid.addEventListener('pointerup', (e) => this.#end(e, true));
    grid.addEventListener('pointercancel', (e) => this.#end(e, false));
    grid.addEventListener('click', (e) => this.#click(e));
    grid.addEventListener('keydown', (e) => this.#key(e));

    const summary = el('p', 'calendar__summary', { 'aria-live': 'polite' });
    this.#root.replaceChildren(head, grid, summary);
    this.#root.classList.add('calendar');
    this.#paint();
  }

  #go(delta) {
    const moved = new Date(Date.UTC(this.#year, this.#month + delta, 1));
    this.#year = moved.getUTCFullYear();
    this.#month = moved.getUTCMonth();
    this.#draw();
  }

  #paint() {
    for (const day of this.#days) {
      const on = this.#selected.has(day.date);
      day.el.toggleAttribute('data-selected', on);
      day.el.toggleAttribute('data-locked', this.#locked.has(day.date));
      day.el.setAttribute('aria-pressed', String(on));
    }
    const count = this.#selected.size;
    this.#root.querySelector('.calendar__summary').textContent =
      count === 0 ? this.#copy.calendar_none : fmt(count === 1 ? this.#copy.calendar_one : this.#copy.calendar_many, { count });
  }

  #dayFrom(node) {
    const button = node?.closest?.('.calendar__day[data-date]');
    return button ? this.#days.find((d) => d.el === button && !d.disabled) ?? null : null;
  }

  #withBlock(base, a, b, mode) {
    const next = new Set(base);
    const [w1, w2] = [Math.min(a.week, b.week), Math.max(a.week, b.week)];
    const [d1, d2] = [Math.min(a.weekday, b.weekday), Math.max(a.weekday, b.weekday)];
    for (const day of this.#days) {
      if (day.disabled || day.week < w1 || day.week > w2 || day.weekday < d1 || day.weekday > d2) continue;
      if (mode === 'add') next.add(day.date);
      else next.delete(day.date);
    }
    return next;
  }

  #down(event) {
    if (event.pointerType === 'mouse' && event.button !== 0) return;
    const day = this.#dayFrom(event.target);
    if (!day || this.#drag) return;
    event.preventDefault();
    event.currentTarget.setPointerCapture(event.pointerId);
    this.#drag = { id: event.pointerId, anchor: day, mode: this.#selected.has(day.date) ? 'remove' : 'add', base: new Set(this.#selected) };
    this.#selected = this.#withBlock(this.#drag.base, day, day, this.#drag.mode);
    this.#paint();
  }

  #move(event) {
    const drag = this.#drag;
    if (!drag || event.pointerId !== drag.id) return;
    const day = this.#dayFrom(document.elementFromPoint(event.clientX, event.clientY));
    if (!day) return;
    this.#selected = this.#withBlock(drag.base, drag.anchor, day, drag.mode);
    this.#paint();
  }

  #end(event, commit) {
    const drag = this.#drag;
    if (!drag || event.pointerId !== drag.id) return;
    this.#drag = null;
    if (!commit) {
      this.#selected = drag.base;
      this.#paint();
      return;
    }
    this.#onChange(this.selected);
  }

  /** Keyboard activation only (pointer presses are handled above). */
  #click(event) {
    if (event.detail !== 0) return;
    const day = this.#dayFrom(event.target);
    if (!day) return;
    if (this.#selected.has(day.date)) this.#selected.delete(day.date);
    else this.#selected.add(day.date);
    this.#paint();
    this.#onChange(this.selected);
  }

  #key(event) {
    const delta = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[event.key];
    const index = this.#days.findIndex((d) => d.el === event.target);
    if (!delta || index < 0) return;
    event.preventDefault();
    const next = this.#days[index + delta];
    if (!next || next.disabled) return;
    this.#days[index].el.tabIndex = -1;
    next.el.tabIndex = 0;
    next.el.focus();
  }
}
