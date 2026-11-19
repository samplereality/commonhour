<?php
// The shell around every page. $content is the rendered page template.
// The theme's stylesheet loads first, then the base stylesheet.
?>
<!doctype html>
<html lang="<?= t('meta.lang') ?>" data-theme="<?= e($theme) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light dark">
  <meta name="description" content="<?= t('meta.description') ?>">
<?php if ($noindex): ?>
  <meta name="robots" content="noindex">
<?php endif ?>
  <link rel="icon" href="data:,">
  <title><?= $title === null ? e($site_name) : t('meta.title_pattern', ['page' => $title]) ?></title>
  <link rel="stylesheet" href="<?= e(theme_url('theme.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
<?php if ($page === 'styleguide'): ?>
  <link rel="stylesheet" href="<?= e(url('/styleguide/tokens.css?theme=' . $theme)) ?>">
<?php endif ?>
<?php if (in_array($page, ['home', 'event', 'admin'], true)): ?>
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<?php endif ?>
  <script type="module" src="<?= e(asset('js/app.js')) ?>"></script>
</head>
<body class="page page--<?= e($page) ?>" data-page="<?= e($page) ?>">
  <a class="skip-link" href="#main"><?= t('nav.skip') ?></a>
<?php partial('header', ['page' => $page]) ?>
  <main id="main" class="page__main">
<?= $content ?>
  </main>
<?php partial('footer') ?>
</body>
</html>
