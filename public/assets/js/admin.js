// The private admin page: rename the event, add days, remove a respondent,
// delete the event. Every request carries the token from the private link.

import { api, el, fmt, pageData } from './util.js';
import { Calendar } from './calendar.js';

const data = pageData();
const copy = data.copy;
const root = document.querySelector('[data-admin]');
const $ = (selector) => root.querySelector(selector);

let event = data.event;
let responses = data.responses;

const status = $('[data-admin-status]');

function say(message, isError = false) {
  status.textContent = message;
  status.classList.toggle('notice--error', isError);
  status.hidden = false;
  status.focus();
}

/** Send one admin action; on success adopt the fresh state and redraw. */
async function send(action, body, button, done) {
  if (button) button.disabled = true;
  try {
    const result = await api('POST', `${data.api}/${action}`, { token: data.token, ...body });
    if (result.event) {
      event = result.event;
      responses = result.responses;
      draw();
    }
    done?.(result);
  } catch (error) {
    say(error.message || copy.error_generic, true);
  } finally {
    if (button) button.disabled = false;
  }
}

/**
 * Two-step buttons: the first press reveals "are you sure?" with the real
 * button beside a cancel. Markup: [data-confirm] > [data-confirm-ask] +
 * [data-confirm-step] containing [data-confirm-cancel].
 */
function enableConfirm(box) {
  const ask = box.querySelector('[data-confirm-ask]');
  const step = box.querySelector('[data-confirm-step]');
  const toggle = (open) => {
    ask.hidden = open;
    step.hidden = !open;
    (open ? step.querySelector('[data-confirm-cancel]') : ask).focus();
  };
  ask.addEventListener('click', () => toggle(true));
  step.querySelector('[data-confirm-cancel]').addEventListener('click', () => toggle(false));
}

// ----- Drawing -----

function drawPeople() {
  $('[data-admin-people-title]').textContent = fmt(copy.respondents_title, { count: responses.length });
  const container = $('[data-admin-people]');
  if (!responses.length) {
    container.replaceChildren(el('p', 'muted', {}, copy.admin_people_empty));
    return;
  }
  const list = el('ul', 'admin-list');
  for (const person of responses) {
    const item = el('li', 'admin-list__item');
    const who = el('span', 'admin-list__name', {}, person.name);
    const detail = el('span', 'admin-list__detail', {}, [
      fmt(copy.respondents_slots, { count: person.count }),
      person.locked ? copy.admin_has_password : null,
    ].filter(Boolean).join(' · '));

    const box = el('div', 'confirm', { 'data-confirm': true });
    const ask = el('button', 'button', { type: 'button', 'data-confirm-ask': true }, copy.admin_remove);
    ask.setAttribute('aria-label', fmt(copy.admin_remove_label, { name: person.name }));
    const step = el('div', 'confirm__step', { 'data-confirm-step': true, hidden: true });
    const yes = el('button', 'button button--danger', { type: 'button' }, copy.admin_remove_confirm);
    yes.addEventListener('click', () =>
      send('remove', { response_id: person.id }, yes, () => say(fmt(copy.admin_removed, { name: person.name })))
    );
    step.append(
      el('p', 'confirm__question', {}, fmt(copy.admin_remove_question, { name: person.name })),
      yes,
      el('button', 'button', { type: 'button', 'data-confirm-cancel': true }, copy.admin_cancel)
    );
    box.append(ask, step);
    enableConfirm(box);

    item.append(who, detail, box);
    list.append(item);
  }
  container.replaceChildren(list);
}

let calendar = null;

function drawDays() {
  const holder = $('[data-calendar]');
  if (event.mode === 'dates') {
    const now = new Date();
    const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    const firstOpen = event.days.find((d) => d >= today) ?? today;
    calendar = new Calendar(holder, { copy, min: today, locked: event.days, month: firstOpen });
    return;
  }
  for (const box of root.querySelectorAll('[name="weekday"]')) {
    const has = event.days.includes(Number(box.value));
    box.checked = has;
    box.disabled = has;
  }
}

function draw() {
  $('[data-admin-title]').textContent = fmt(copy.admin_title, { title: event.title });
  const expires = new Date(event.expires_at.replace(' ', 'T') + 'Z');
  const when = new Intl.DateTimeFormat(document.documentElement.lang || 'en', { dateStyle: 'long' }).format(expires);
  $('[data-admin-expiry]').textContent = fmt(copy.admin_expiry, { date: when });
  drawPeople();
  drawDays();
}

// ----- Actions -----

const details = $('[data-admin-details]');
details.addEventListener('submit', (e) => {
  e.preventDefault();
  const body = { title: details.elements.title.value };
  if (details.elements.theme) body.theme = details.elements.theme.value;
  const themeBefore = event.theme ?? '';
  send('update', body, details.querySelector('[type="submit"]'), () => {
    details.elements.title.value = event.title;
    // A new theme only shows once the page is loaded again.
    if ((event.theme ?? '') !== themeBefore) location.reload();
    else say(copy.admin_saved);
  });
});

const days = $('[data-admin-days]');
days.addEventListener('submit', (e) => {
  e.preventDefault();
  const added =
    event.mode === 'dates'
      ? calendar.selected.filter((d) => !event.days.includes(d))
      : [...days.querySelectorAll('[name="weekday"]:checked:not(:disabled)')].map((box) => Number(box.value));
  if (!added.length) {
    say(copy.admin_days_none, true);
    return;
  }
  send('update', { add_days: added }, days.querySelector('[type="submit"]'), () =>
    say(fmt(added.length === 1 ? copy.admin_days_added_one : copy.admin_days_added, { count: added.length }))
  );
});

enableConfirm($('[data-confirm]'));
const del = $('[data-admin-delete]');
del.addEventListener('click', () => send('delete', {}, del, () => location.assign(data.home)));

draw();
