// Style guide only: draws the fake event with the real grid scripts, shows
// each token's current value, and checks contrast.

import { pageData } from './util.js';
import { buildModel, makeLabels } from './timezone.js';
import { PaintGrid } from './grid.js';
import { HeatGrid, renderLegend } from './heatmap.js';
import { renderRespondents, renderSlotDetail } from './panels.js';
import { Calendar } from './calendar.js';

const data = pageData();
const copy = data.copy;
const $ = (selector) => document.querySelector(selector);

// ----- The fake event -----

const model = buildModel(data.event, data.event.timezone, makeLabels());
const responses = data.responses.map((r) => ({ name: r.name, slots: new Set(r.slots) }));

const mine = new PaintGrid($('[data-sg-mine]'), { copy });
mine.render(model, copy.mine_title);
mine.setSelected(data.mine);

const heat = new HeatGrid($('[data-sg-heat]'), { copy });
heat.render(model, copy.group_title);
heat.setResponses(responses);
renderLegend($('[data-sg-legend]'), responses.length, copy);

// One person highlighted, with the panels that sit beside the heatmap.
const detail = $('[data-sg-detail]');
const highlighted = new HeatGrid($('[data-sg-highlight]'), {
  copy,
  onInspect: (info) => renderSlotDetail(detail, info, copy),
});
highlighted.render(model, copy.group_title);
highlighted.setResponses(responses);
renderRespondents($('[data-sg-respondents]'), responses, copy, {
  active: responses[2].name,
  onHighlight: (name) => highlighted.setHighlight(name),
});
// Start with a slot inspected: Tuesday at 10:00.
const tuesdayAtTen = highlighted.rows().find((r) => r.row.minute === 600)?.cells[1];
renderSlotDetail(detail, tuesdayAtTen, copy);
renderSlotDetail($('[data-sg-detail-empty]'), null, copy);

new Calendar($('[data-sg-calendar]'), {
  copy,
  month: '2026-11-01',
  min: '2026-11-10',
  selected: ['2026-11-16', '2026-11-17', '2026-11-18', '2026-11-19', '2026-11-20'],
});

new Calendar($('[data-sg-calendar-locked]'), {
  copy,
  month: '2026-11-01',
  min: '2026-11-10',
  locked: ['2026-11-16', '2026-11-17', '2026-11-18'],
  selected: ['2026-11-23', '2026-11-24'],
});

// ----- Tokens and contrast -----

const canvas = document.createElement('canvas');
canvas.width = canvas.height = 1;
const ctx = canvas.getContext('2d', { willReadFrequently: true });

/** Any CSS color (drawn over an optional backdrop) as [r, g, b] in 0-255. */
function toRgb(color, backdrop) {
  ctx.clearRect(0, 0, 1, 1);
  for (const c of [backdrop, color]) {
    if (!c) continue;
    ctx.fillStyle = c;
    ctx.fillRect(0, 0, 1, 1);
  }
  return Array.from(ctx.getImageData(0, 0, 1, 1).data.slice(0, 3));
}

function luminance([r, g, b]) {
  const [R, G, B] = [r, g, b].map((v) => {
    const s = v / 255;
    return s <= 0.04045 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * R + 0.7152 * G + 0.0722 * B;
}

function contrast(fg, bg) {
  const [a, b] = [luminance(fg), luminance(bg)].sort((x, y) => y - x);
  return (a + 0.05) / (b + 0.05);
}

function showTokenValues() {
  const styles = getComputedStyle(document.documentElement);
  for (const row of document.querySelectorAll('[data-token]')) {
    const value = styles.getPropertyValue(row.dataset.token).trim();
    row.querySelector('[data-token-value]').textContent = value;
    if (CSS.supports('color', value)) {
      row.dataset.kind = 'color';
    } else {
      delete row.dataset.kind;
    }
  }
}

function checkContrast() {
  const table = document.querySelector('[data-contrast-table]');
  if (!table) return;
  for (const row of table.querySelectorAll('[data-contrast]')) {
    const sample = getComputedStyle(row.querySelector('[data-fg]'));
    const bg = toRgb(sample.backgroundColor);
    const fg = toRgb(sample.color, sample.backgroundColor);
    const ratio = contrast(fg, bg);
    const pass = ratio >= parseFloat(row.dataset.min);
    row.dataset.pass = String(pass);
    row.querySelector('[data-contrast-ratio]').textContent = `${ratio.toFixed(2)}:1`;
    row.querySelector('[data-contrast-verdict]').textContent = pass
      ? table.dataset.passLabel
      : table.dataset.failLabel;
  }
}

function refresh() {
  showTokenValues();
  checkContrast();
}

refresh();
matchMedia('(prefers-color-scheme: dark)').addEventListener('change', refresh);
