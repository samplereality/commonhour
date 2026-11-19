// Heatmap shading. Turns each element's data-ratio (0 to 1) into a data-level
// (0 to --heat-steps) that the stylesheet maps onto the theme's color ramp.

const MAX_STEPS = 12; // app.css defines data-level rules up to this number
const DEFAULT_STEPS = 6;

/** The number of shades the active theme asks for. */
export function heatSteps(el = document.documentElement) {
  const n = parseInt(getComputedStyle(el).getPropertyValue('--heat-steps'), 10);
  return Number.isFinite(n) && n > 0 ? Math.min(n, MAX_STEPS) : DEFAULT_STEPS;
}

/** Zero stays level 0; any availability at all gets at least level 1. */
export function levelFor(ratio, steps) {
  if (!(ratio > 0)) return 0;
  return Math.max(1, Math.min(steps, Math.round(ratio * steps)));
}

export function applyHeatLevels(root = document) {
  const steps = heatSteps();
  for (const el of root.querySelectorAll('[data-ratio]')) {
    el.dataset.level = String(levelFor(parseFloat(el.dataset.ratio), steps));
  }
}
