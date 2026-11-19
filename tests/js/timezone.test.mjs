// Tests for the browser-side time-zone layout (public/assets/js/timezone.js).
//
//   node --test tests/js/
//
// Needs only Node 22 or later; nothing to install. The slot instants below are
// written out by hand in UTC, so they check the layout independently of the
// PHP code that produces them on the server (tests/SlotsTest.php covers that).

import test from 'node:test';
import assert from 'node:assert/strict';
import { buildModel, makeLabels, wallClock } from '../../public/assets/js/timezone.js';

const labels = makeLabels('en-US');
const HALF_HOUR = 1800;

/** Slot starts for one day: `count` half-hour slots from the given UTC time. */
function day(isoDate, utcHour, count = 16) {
  const first = Date.UTC(...isoDate.split('-').map((n, i) => (i === 1 ? Number(n) - 1 : Number(n))), utcHour) / 1000;
  return Array.from({ length: count }, (_, i) => first + i * HALF_HOUR);
}

function event(slots, over = {}) {
  return { mode: 'dates', slot_minutes: 30, timezone: 'America/New_York', days: [], slots, ...over };
}

/** Every slot placed in the model, in no particular order. */
const placed = (model) => [...model.cells.values()].flatMap((cell) => cell.keys);

function assertEachSlotOnce(model, slots) {
  const seen = placed(model);
  assert.equal(seen.length, slots.length, 'no slot is missing or shown twice');
  assert.deepEqual([...seen].sort(), [...slots].sort());
}

// 9:00 to 17:00 in New York on 7 and 8 December 2026 (EST, five hours behind UTC).
const december = [...day('2026-12-07', 14), ...day('2026-12-08', 14)];

test('an event reads in its own zone as it was set up', () => {
  const model = buildModel(event(december), 'America/New_York', labels);
  assert.deepEqual(model.columns.map((c) => c.key), ['2026-12-07', '2026-12-08']);
  assert.equal(model.rows.length, 16);
  assert.equal(model.rows[0].minute, 9 * 60);
  assert.equal(model.rows.at(-1).minute, 16 * 60 + 30);
  assert.equal(model.rows.some((row) => row.gap), false);
  assert.equal(model.cells.size, 32, 'every position has a slot');
  assertEachSlotOnce(model, december);
});

test('a New York event reads six hours later in Rome, on the same days', () => {
  const model = buildModel(event(december), 'Europe/Rome', labels);
  assert.deepEqual(model.columns.map((c) => c.key), ['2026-12-07', '2026-12-08']);
  assert.equal(model.rows[0].minute, 15 * 60);
  assert.equal(model.rows.at(-1).minute, 22 * 60 + 30);
  assert.deepEqual(model.cells.get(`2026-12-07|${15 * 60}`).keys, [december[0]]);
  assert.equal(model.cells.size, 32);
  assertEachSlotOnce(model, december);
});

test('a New York event wraps past midnight in Tokyo', () => {
  const model = buildModel(event(december), 'Asia/Tokyo', labels);
  // 9:00 in New York is 23:00 in Tokyo; the rest of the day lands on the next date.
  assert.deepEqual(model.columns.map((c) => c.key), ['2026-12-07', '2026-12-08', '2026-12-09']);
  assert.deepEqual(model.cells.get(`2026-12-07|${23 * 60}`).keys, [december[0]]);
  assert.deepEqual(model.cells.get(`2026-12-08|0`).keys, [december[2]]);
  assert.deepEqual(model.cells.get(`2026-12-09|${6 * 60 + 30}`).keys, [december.at(-1)]);

  // Positions with no slot are left empty rather than invented.
  assert.equal(model.cells.has('2026-12-07|0'), false);
  assert.equal(model.cells.has(`2026-12-09|${23 * 60}`), false);

  // Rows run 0:00 to 6:30, then jump to 23:00; the jump is marked.
  assert.deepEqual(model.rows.map((r) => r.minute), [...Array(14).keys()].map((i) => i * 30).concat([1380, 1410]));
  assert.deepEqual(model.rows.filter((r) => r.gap).map((r) => r.minute), [1380]);
  assertEachSlotOnce(model, december);
});

// 9:00 to 17:00 in New York from 30 October to 3 November 2026. The clocks go
// back on 1 November, so the same wall-clock hours start an hour later in UTC.
const dstDates = ['2026-10-30', '2026-10-31', '2026-11-01', '2026-11-02', '2026-11-03'];
const november = [
  ...day('2026-10-30', 13),
  ...day('2026-10-31', 13),
  ...day('2026-11-01', 14),
  ...day('2026-11-02', 14),
  ...day('2026-11-03', 14),
];

test('a range across the November clock change has no missing or duplicated slots', () => {
  const model = buildModel(event(november), 'America/New_York', labels);
  assert.deepEqual(model.columns.map((c) => c.key), dstDates);
  assert.deepEqual(model.rows.map((r) => r.minute), [...Array(16).keys()].map((i) => 540 + i * 30));
  assert.equal(model.cells.size, 5 * 16, 'a full rectangle: nothing void');
  for (const cell of model.cells.values()) assert.equal(cell.keys.length, 1);
  assertEachSlotOnce(model, november);
});

test('the same range seen from Rome shifts by an hour after the change, and loses nothing', () => {
  // Rome left summer time a week earlier, so it is five hours ahead of New
  // York until 1 November and six hours ahead after.
  const model = buildModel(event(november), 'Europe/Rome', labels);
  assert.deepEqual(model.columns.map((c) => c.key), dstDates);
  assert.equal(model.cells.has(`2026-10-30|${14 * 60}`), true);
  assert.equal(model.cells.has(`2026-10-30|${22 * 60}`), false);
  assert.equal(model.cells.has(`2026-11-02|${14 * 60}`), false);
  assert.equal(model.cells.has(`2026-11-02|${22 * 60 + 30}`), true);
  assert.equal(model.rows.length, 18); // 14:00 to 22:30
  for (const cell of model.cells.values()) assert.equal(cell.keys.length, 1);
  assertEachSlotOnce(model, november);
});

test('a whole day on the clock change keeps both passes through the repeated hour', () => {
  // Midnight to midnight on 1 November in New York is 25 hours: 04:00 UTC to 05:00 UTC.
  const slots = day('2026-11-01', 4, 50);
  const model = buildModel(event(slots), 'America/New_York', labels);
  assert.deepEqual(model.columns.map((c) => c.key), ['2026-11-01']);
  assert.equal(model.rows.length, 48);
  assert.equal(model.cells.get('2026-11-01|60').keys.length, 2, '1:00 happens twice');
  assert.equal(model.cells.get('2026-11-01|90').keys.length, 2, '1:30 happens twice');
  assert.equal(model.cells.get('2026-11-01|120').keys.length, 1);
  assertEachSlotOnce(model, slots);
});

test('a whole day on the spring change simply has no 2 o’clock hour', () => {
  // 8 March 2026 in New York is 23 hours: 05:00 UTC to 04:00 UTC.
  const slots = day('2026-03-08', 5, 46);
  const model = buildModel(event(slots), 'America/New_York', labels);
  assert.equal(model.rows.length, 46);
  assert.equal(model.cells.has('2026-03-08|120'), false);
  assert.equal(model.cells.has('2026-03-08|150'), false);
  assert.deepEqual(model.rows.filter((r) => r.gap).map((r) => r.minute), [180]);
  assertEachSlotOnce(model, slots);
});

test('weekly events are never converted', () => {
  const weekly = { mode: 'weekdays', slot_minutes: 30, timezone: 'America/New_York', days: [1, 3], slots: ['1:540', '1:570', '3:540', '3:570'] };
  for (const zone of ['America/New_York', 'Asia/Tokyo']) {
    const model = buildModel(weekly, zone, labels);
    assert.deepEqual(model.columns.map((c) => c.name), ['Mon', 'Wed']);
    assert.deepEqual(model.rows.map((r) => r.minute), [540, 570]);
    assert.deepEqual(model.cells.get('3|570').keys, ['3:570']);
  }
});

test('wallClock reads one instant in three zones', () => {
  const nineInNewYork = Date.UTC(2026, 11, 7, 14) / 1000;
  assert.deepEqual(wallClock(nineInNewYork, 'America/New_York'), { date: '2026-12-07', minute: 540 });
  assert.deepEqual(wallClock(nineInNewYork, 'Europe/Rome'), { date: '2026-12-07', minute: 900 });
  assert.deepEqual(wallClock(nineInNewYork, 'Asia/Tokyo'), { date: '2026-12-07', minute: 1380 });
  // Midnight is minute 0 of the new day, never minute 1440 of the old one.
  assert.deepEqual(wallClock(Date.UTC(2026, 11, 7, 15) / 1000, 'Asia/Tokyo'), { date: '2026-12-08', minute: 0 });
});
