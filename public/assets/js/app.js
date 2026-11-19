// Entry point, loaded on every page. Each page names its own script.

const scripts = {
  home: './create.js',
  event: './event.js',
  admin: './admin.js',
  styleguide: './styleguide.js',
};

const script = scripts[document.body.dataset.page];
if (script) import(script);
