// Time-zone conversion and the grid's layout model.
//
// The server stores every slot as a UTC instant. Which column (day) and row
// (clock time) a slot lands in depends only on the zone the viewer chooses, so
// that is worked out here.

const formatters = new Map();

function formatterFor(zone) {
  let f = formatters.get(zone);
  if (!f) {
    f = new Intl.DateTimeFormat('en-US', {
      timeZone: zone,
      hourCycle: 'h23',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
    });
    formatters.set(zone, f);
  }
  return f;
}

/** A UTC epoch second as { date: 'YYYY-MM-DD', minute: minutes after midnight } in a zone. */
export function wallClock(epoch, zone) {
  const p = {};
  for (const { type, value } of formatterFor(zone).formatToParts(epoch * 1000)) p[type] = value;
  return { date: `${p.year}-${p.month}-${p.day}`, minute: (Number(p.hour) % 24) * 60 + Number(p.minute) };
}

export function browserZone() {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
  } catch {
    return 'UTC';
  }
}

export function isValidZone(zone) {
  try {
    formatterFor(zone);
    return true;
  } catch {
    return false;
  }
}

/** Day and time labels in the page's language. */
export function makeLabels(locale = document.documentElement.lang || 'en') {
  const opt = (o) => new Intl.DateTimeFormat(locale, { timeZone: 'UTC', ...o });
  const weekday = opt({ weekday: 'short' });
  const weekdayLong = opt({ weekday: 'long' });
  const date = opt({ month: 'short', day: 'numeric' });
  const hour = opt({ hour: 'numeric' });
  const time = opt({ hour: 'numeric', minute: '2-digit' });
  const noon = (iso) => new Date(`${iso}T12:00:00Z`);
  const sunday = (n) => new Date(Date.UTC(2023, 0, 1 + n, 12)); // 1 Jan 2023 was a Sunday
  return {
    dayName: (iso) => weekday.format(noon(iso)),
    dayDate: (iso) => date.format(noon(iso)),
    weekdayName: (n) => weekday.format(sunday(n)),
    weekdayLongName: (n) => weekdayLong.format(sunday(n)),
    hour: (minute) => hour.format(new Date(minute * 60000)),
    time: (minute) => time.format(new Date(minute * 60000)),
  };
}

/**
 * Lay an event out as columns (days) and rows (clock times) for one zone.
 *
 * Returns { columns, rows, cells } where cells maps "columnKey|minute" to
 * { keys: [slot, ...] }. A position with no entry has no slot there: that
 * happens when the viewer's zone pushes part of a day into the next column.
 * A cell holds two slots only in the hour that repeats when clocks go back.
 */
export function buildModel(event, zone, labels) {
  const cells = new Map();
  const columnKeys = new Set();
  const minutes = new Set();
  const place = (columnKey, minute, slot) => {
    columnKeys.add(columnKey);
    minutes.add(minute);
    const id = `${columnKey}|${minute}`;
    if (!cells.has(id)) cells.set(id, { keys: [] });
    cells.get(id).keys.push(slot);
  };

  let columns;
  if (event.mode === 'weekdays') {
    for (const slot of event.slots) {
      const [weekday, minute] = slot.split(':').map(Number);
      place(String(weekday), minute, slot);
    }
    columns = event.days.map((n) => ({ key: String(n), name: labels.weekdayName(n), date: '' }));
  } else {
    for (const slot of event.slots) {
      const { date, minute } = wallClock(slot, zone);
      place(date, minute, slot);
    }
    columns = [...columnKeys].sort().map((iso) => ({ key: iso, name: labels.dayName(iso), date: labels.dayDate(iso) }));
  }

  const sorted = [...minutes].sort((a, b) => a - b);
  const rows = sorted.map((minute, i) => {
    const gap = i > 0 && minute - sorted[i - 1] !== event.slot_minutes;
    const rule = minute % 60 === 0 ? 'hour' : minute % 30 === 0 ? 'half' : 'slot';
    return {
      minute,
      rule,
      gap, // a jump in time, e.g. late evening followed by early morning
      labelled: rule === 'hour' || i === 0 || gap,
      short: minute % 60 === 0 ? labels.hour(minute) : labels.time(minute),
      long: labels.time(minute),
    };
  });

  return { columns, rows, cells, slotMinutes: event.slot_minutes };
}
