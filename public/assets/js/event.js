// The event page: sign-in, your grid, the group heatmap, and keeping both
// in step with the server.

import { api, enableCopyButtons, fmt, pageData } from './util.js';
import { browserZone, buildModel, isValidZone, makeLabels } from './timezone.js';
import { PaintGrid, createAutosaver } from './grid.js';
import { HeatGrid, renderLegend, renderTable } from './heatmap.js';
import { renderRespondents, renderSlotDetail } from './panels.js';

const POLL_MS = 15000;

const data = pageData();
const copy = data.copy;
const root = document.querySelector('[data-event]');
const $ = (selector) => root.querySelector(selector);

let event = data.event;
let responses = data.responses;
let version = data.version;
let me = data.me; // the name this visitor is signed in as, or null
let mySlots = new Set(responses.find((r) => r.name === me)?.slots ?? []);
let highlight = null; // the respondent pinned by a click or tap, if any
let model = null;
let gone = false;

const labels = makeLabels();
const weekly = event.mode === 'weekdays';
let zone = weekly ? event.timezone : startingZone();

// ----- Pieces -----

const saveState = $('[data-save]');
const autosave = createAutosaver({
  save: async (slots) => {
    try {
      await api('PUT', `${data.api}/responses/me`, { slots: [...slots] }, { keepalive: slots.size < 2000 });
    } catch (error) {
      if (error.status === 401 || error.status === 404) {
        signedOut();
        throw { fatal: true };
      }
      throw error;
    }
  },
  onState: (state) => {
    saveState.hidden = false;
    saveState.dataset.state = state;
    saveState.textContent = copy[`save_${state}`];
  },
});

const mine = new PaintGrid($('[data-mine-grid]'), {
  copy,
  onChange: (selected) => {
    mySlots = selected;
    autosave.queue(selected);
    drawGroup();
  },
});

const heat = new HeatGrid($('[data-heat-grid]'), {
  copy,
  onInspect: (info) => renderSlotDetail($('[data-detail]'), info, copy),
});

// ----- Drawing -----

/** Everyone's slots as sets, with this visitor's unsaved changes included. */
function currentResponses() {
  return responses.map((r) => ({ name: r.name, slots: r.name === me ? mySlots : new Set(r.slots) }));
}

function drawGroup() {
  const list = currentResponses();
  heat.setResponses(list);
  renderLegend($('[data-legend]'), list.length, copy);
  renderRespondents($('[data-respondents]'), list, copy, {
    me,
    active: highlight,
    onHighlight: (name) => heat.setHighlight(name),
    onPin: (name) => {
      highlight = name;
    },
  });
  renderTable($('[data-table]'), model, heat, copy, fmt(copy.table_caption, { title: event.title }));
}

function drawAll() {
  model = buildModel(event, zone, labels);
  $('[data-title]').textContent = event.title;
  const zoneNote = $('[data-zone-note]');
  zoneNote.hidden = weekly || zone === event.timezone;
  zoneNote.textContent = fmt(copy.zone_note, { zone, event_zone: event.timezone });
  heat.render(model, copy.group_title);
  renderSlotDetail($('[data-detail]'), null, copy);
  drawMine();
  drawGroup();
}

function drawMine() {
  $('[data-signin]').hidden = me !== null;
  $('[data-mine]').hidden = me === null;
  if (me === null) return;
  $('[data-me]').textContent = fmt(copy.signed_in_as, { name: me });
  mine.render(model, copy.mine_title);
  mine.setSelected(mySlots);
}

// ----- Sign-in -----

const signin = $('[data-signin]');
const signinError = $('[data-signin-error]');

signin.addEventListener('submit', async (e) => {
  e.preventDefault();
  const button = signin.querySelector('[type="submit"]');
  button.disabled = true;
  signinError.hidden = true;
  try {
    const result = await api('POST', `${data.api}/signin`, {
      name: signin.elements.name.value,
      password: signin.elements.password.value,
    });
    signin.elements.password.value = '';
    adopt(result);
    me = result.me;
    mySlots = new Set(responses.find((r) => r.name === me)?.slots ?? []);
    drawMine();
    drawGroup();
  } catch (error) {
    signinError.textContent = error.message || copy.error_generic;
    signinError.hidden = false;
  } finally {
    button.disabled = false;
  }
});

$('[data-signout]').addEventListener('click', async () => {
  await autosave.flush();
  try {
    await api('POST', `${data.api}/signout`);
  } catch {
    // the cookie simply stays; signing in under another name replaces it
  }
  signedOut();
});

function signedOut() {
  me = null;
  mySlots = new Set();
  saveState.hidden = true;
  drawMine();
  drawGroup();
}

// ----- Staying current -----

function adopt(snapshot) {
  version = snapshot.version;
  if (snapshot.unchanged) return false;
  const layoutChanged = JSON.stringify(snapshot.event.slots) !== JSON.stringify(event.slots);
  event = snapshot.event;
  responses = snapshot.responses;
  if (layoutChanged) drawAll();
  return true;
}

let polling = false;
let lastPoll = Date.now();

async function poll() {
  if (polling || gone || document.visibilityState !== 'visible') return;
  polling = true;
  lastPoll = Date.now();
  try {
    const snapshot = await api('GET', `${data.api}?since=${encodeURIComponent(version)}`);
    if (me !== null && snapshot.me === null) signedOut();
    if (adopt(snapshot)) {
      $('[data-title]').textContent = event.title;
      drawGroup();
    }
  } catch (error) {
    if (error.status === 404) {
      gone = true;
      $('[data-gone]').hidden = false;
    }
  } finally {
    polling = false;
  }
}

setInterval(poll, POLL_MS);
const pollSoon = () => {
  if (Date.now() - lastPoll > 2000) poll();
};
document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'visible') pollSoon();
  else autosave.flush();
});
window.addEventListener('focus', pollSoon);
window.addEventListener('pagehide', () => autosave.flush());

// ----- Time zone -----

function startingZone() {
  let saved = null;
  try {
    saved = localStorage.getItem('zone');
  } catch {
    // storage unavailable: fall back to the browser's zone
  }
  return saved && isValidZone(saved) ? saved : browserZone();
}

const zoneSelect = $('[data-zone]');
if (weekly) {
  $('[data-zone-field]').hidden = true;
  const note = $('[data-weekly-note]');
  note.textContent = fmt(copy.weekly_note, { zone: event.timezone });
  note.hidden = false;
} else {
  if (![...zoneSelect.options].some((o) => o.value === zone)) {
    zoneSelect.add(new Option(zone, zone));
  }
  zoneSelect.value = zone;
  zoneSelect.addEventListener('change', () => {
    zone = zoneSelect.value;
    try {
      localStorage.setItem('zone', zone);
    } catch {
      // not remembered, still applied
    }
    drawAll();
  });
}

// ----- Tabs (narrow screens) and touch mode -----

const panes = $('[data-panes]');
for (const tab of root.querySelectorAll('[data-tab-for]')) {
  tab.addEventListener('click', () => {
    panes.dataset.tab = tab.dataset.tabFor;
    for (const other of root.querySelectorAll('[data-tab-for]')) {
      other.setAttribute('aria-selected', String(other === tab));
    }
  });
}

for (const radio of root.querySelectorAll('[name="touch-mode"]')) {
  radio.addEventListener('change', () => radio.checked && mine.setTouchMode(radio.value));
}

// ----- Links -----

$('#share-link').value = location.origin + location.pathname;
enableCopyButtons(root, copy.copied);

// Shown to the person who just created the event, until they dismiss it.
const createdKey = `created:${event.slug}`;
let created = null;
try {
  created = JSON.parse(sessionStorage.getItem(createdKey));
} catch {
  // nothing to show
}
if (created?.admin) {
  $('#admin-link').value = location.origin + created.admin;
  $('[data-created]').hidden = false;
  $('[data-created-dismiss]').addEventListener('click', () => {
    $('[data-created]').hidden = true;
    try {
      sessionStorage.removeItem(createdKey);
    } catch {
      // already gone
    }
  });
}

drawAll();
