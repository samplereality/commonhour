// Small helpers shared by the page scripts.

/** Create an element: el('div', 'grid__cell', { role: 'gridcell' }, 'text'). */
export function el(tag, className = '', attrs = {}, text = null) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  for (const [name, value] of Object.entries(attrs)) {
    if (value !== null && value !== undefined && value !== false) node.setAttribute(name, value === true ? '' : value);
  }
  if (text !== null) node.textContent = text;
  return node;
}

/** Fill {placeholders} in a copy string. */
export function fmt(template, vars = {}) {
  return String(template ?? '').replace(/\{(\w+)\}/g, (m, key) => (key in vars ? vars[key] : m));
}

/** The JSON block a template wrote with json_block(). */
export function pageData(id = 'page-data') {
  const node = document.getElementById(id);
  return node ? JSON.parse(node.textContent) : {};
}

export class ApiError extends Error {
  constructor(status, code, message) {
    super(message || code);
    this.status = status;
    this.code = code;
  }
}

/** Call the JSON API. Writes carry the CSRF token from the page's meta tag. */
export async function api(method, url, body, { keepalive = false } = {}) {
  const headers = { Accept: 'application/json' };
  const init = { method, headers, credentials: 'same-origin', keepalive };
  if (method !== 'GET') {
    headers['Content-Type'] = 'application/json';
    headers['X-CSRF-Token'] = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    init.body = JSON.stringify(body ?? {});
  }
  let response;
  try {
    response = await fetch(url, init);
  } catch {
    throw new ApiError(0, 'network', '');
  }
  let data = null;
  try {
    data = await response.json();
  } catch {
    // fall through with data = null
  }
  if (!response.ok || !data) {
    throw new ApiError(response.status, data?.error?.code ?? 'server', data?.error?.message ?? '');
  }
  return data;
}

/** Wire up every [data-copy] button to copy the value of the input it names. */
export function enableCopyButtons(root, copiedLabel) {
  for (const button of root.querySelectorAll('[data-copy]')) {
    const label = button.textContent;
    button.addEventListener('click', async () => {
      const input = document.getElementById(button.dataset.copy);
      if (!input) return;
      try {
        await navigator.clipboard.writeText(input.value);
      } catch {
        input.select(); // clipboard blocked: leave the text selected to copy by hand
        return;
      }
      button.textContent = copiedLabel;
      setTimeout(() => {
        button.textContent = label;
      }, 1800);
    });
  }
}
