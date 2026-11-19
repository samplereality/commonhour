// The home page: the form that creates an event.

import { api, pageData } from './util.js';
import { browserZone } from './timezone.js';
import { Calendar } from './calendar.js';

const data = pageData();
const copy = data.copy;
const form = document.querySelector('[data-create]');
const error = form.querySelector('[data-create-error]');

// Dates are picked in the creator's own "today".
const now = new Date();
const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
const calendar = new Calendar(form.querySelector('[data-calendar]'), { copy, min: today });

// Dates or days of the week: the form shows one picker or the other.
const syncMode = () => {
  form.dataset.mode = form.elements.mode.value;
};
for (const radio of form.querySelectorAll('[name="mode"]')) radio.addEventListener('change', syncMode);
syncMode();

// Default to the browser's time zone.
const zone = form.elements.timezone;
const mine = browserZone();
if (![...zone.options].some((o) => o.value === mine)) zone.add(new Option(mine, mine));
zone.value = mine;

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  const fields = form.elements;
  const mode = fields.mode.value;
  const payload = {
    title: fields.title.value,
    mode,
    days:
      mode === 'dates'
        ? calendar.selected
        : [...form.querySelectorAll('[name="weekday"]:checked')].map((box) => Number(box.value)),
    start_minute: Number(fields.start_minute.value),
    end_minute: Number(fields.end_minute.value),
    slot_minutes: Number(fields.slot_minutes.value),
    timezone: fields.timezone.value,
    theme: fields.theme ? fields.theme.value : null,
    passphrase: fields.passphrase ? fields.passphrase.value : '',
    website: fields.website.value,
  };

  const button = form.querySelector('[type="submit"]');
  button.disabled = true;
  error.hidden = true;
  try {
    const created = await api('POST', data.api, payload);
    try {
      // The event page shows the private admin link once, from here.
      sessionStorage.setItem(`created:${created.slug}`, JSON.stringify({ admin: `${data.eventBase}${created.slug}/admin/${created.admin_token}` }));
    } catch {
      // storage unavailable: the event still exists, the admin link is lost
    }
    location.assign(data.eventBase + created.slug);
  } catch (err) {
    error.textContent = err.message || copy.error_generic;
    error.hidden = false;
    error.focus();
    button.disabled = false;
  }
});
