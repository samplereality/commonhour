// Small pieces of the group view that sit beside the heatmap. Kept separate
// so the event page and the style guide draw them the same way.

import { el, fmt } from './util.js';

/** "Tue 10:00 AM, 4 of 6 available" with the two lists of names. */
export function renderSlotDetail(container, info, copy) {
  if (!info) {
    container.replaceChildren(el('p', 'slot-detail__hint', {}, copy.detail_hint));
    return;
  }
  const list = (label, names, modifier) => {
    const group = el('div', 'slot-detail__group');
    group.append(el('p', 'slot-detail__label', {}, label));
    const ul = el('ul', `slot-detail__list${modifier}`);
    for (const name of names) ul.append(el('li', '', {}, name));
    if (!names.length) ul.append(el('li', 'slot-detail__none', {}, copy.detail_none));
    group.append(ul);
    return group;
  };
  const groups = el('div', 'slot-detail__groups');
  groups.append(
    list(copy.detail_available, info.available, ''),
    list(copy.detail_unavailable, info.unavailable, ' slot-detail__list--out')
  );
  container.replaceChildren(
    el('h3', 'slot-detail__when', {}, `${info.day} ${info.time}`),
    el('p', 'slot-detail__count', {}, fmt(copy.detail_count, info)),
    groups
  );
}

/**
 * The list of people. Hovering or focusing a name calls onHighlight(name);
 * leaving calls onHighlight(null). Clicking pins the highlight (for touch)
 * and calls onPin(name).
 */
export function renderRespondents(container, responses, copy, { onHighlight = () => {}, onPin = () => {}, me = null, active = null } = {}) {
  let pinned = active;
  const title = el('h3', 'respondents__title', {}, fmt(copy.respondents_title, { count: responses.length }));
  if (!responses.length) {
    container.replaceChildren(title, el('p', 'respondents__empty', {}, copy.respondents_empty));
    return;
  }
  const list = el('ul', 'respondents__list');
  const buttons = new Map();
  const show = (name) => {
    for (const [n, button] of buttons) button.toggleAttribute('data-active', n === name);
    onHighlight(name);
  };
  for (const response of responses) {
    const button = el('button', 'respondents__item', { type: 'button', 'aria-pressed': 'false' });
    button.append(
      el('span', 'respondents__name', {}, response.name === me ? fmt(copy.respondents_you, { name: response.name }) : response.name),
      el('span', 'respondents__count', {}, fmt(copy.respondents_slots, { count: response.slots.size }))
    );
    button.addEventListener('pointerenter', (e) => e.pointerType !== 'touch' && show(response.name));
    button.addEventListener('pointerleave', (e) => e.pointerType !== 'touch' && show(pinned));
    button.addEventListener('focus', () => show(response.name));
    button.addEventListener('blur', () => show(pinned));
    button.addEventListener('click', () => {
      pinned = pinned === response.name ? null : response.name;
      onPin(pinned);
      for (const [n, b] of buttons) b.setAttribute('aria-pressed', String(n === pinned));
      show(pinned);
    });
    buttons.set(response.name, button);
    const item = el('li');
    item.append(button);
    list.append(item);
  }
  container.replaceChildren(title, list);
  if (pinned && buttons.has(pinned)) {
    buttons.get(pinned).setAttribute('aria-pressed', 'true');
    show(pinned);
  }
}
