<?php
// The private admin page for one event, reached through the link shown once
// at creation. public/assets/js/admin.js fills the data-* containers and
// sends the changes. $event, $themes, $default_theme and $max_days come from the app.

$weekdayNames = copy_list('home.weekdays'); // Sunday first
?>
    <div class="admin stack stack--loose" data-admin>
      <header class="stack">
        <h1 data-admin-title><?= t('admin.title', ['title' => $event['title']]) ?></h1>
        <p class="lede"><?= t('admin.lede') ?></p>
        <p><a class="button" href="<?= e(url('/e/' . $event['slug'])) ?>"><?= t('admin.back') ?></a></p>
      </header>

      <noscript><p class="notice notice--error"><?= t('admin.noscript') ?></p></noscript>
      <p class="notice" data-admin-status role="status" tabindex="-1" hidden></p>

      <section class="section">
        <h2><?= t('admin.details_title') ?></h2>
        <form class="stack" data-admin-details>
          <label class="field">
            <span class="field__label"><?= t('home.field_title') ?></span>
            <input class="field__input" type="text" name="title" maxlength="120" required autocomplete="off" value="<?= e($event['title']) ?>">
          </label>
<?php if (count($themes) > 1): ?>
          <label class="field">
            <span class="field__label"><?= t('home.field_theme') ?></span>
            <select class="field__input" name="theme">
              <option value=""><?= t('home.theme_default', ['name' => $default_theme]) ?></option>
<?php foreach ($themes as $name): ?>
<?php if ($name !== $default_theme): ?>
              <option value="<?= e($name) ?>"<?= $name === $event['theme'] ? ' selected' : '' ?>><?= e($name) ?></option>
<?php endif ?>
<?php endforeach ?>
            </select>
            <span class="field__hint"><?= t('admin.theme_hint') ?></span>
          </label>
<?php endif ?>
          <div><button class="button button--primary" type="submit"><?= t('admin.save') ?></button></div>
        </form>
      </section>

      <section class="section">
        <h2><?= t('admin.days_title') ?></h2>
        <form class="stack" data-admin-days>
<?php if ($event['mode'] === 'dates'): ?>
          <p class="muted"><?= t('admin.dates_hint', ['max' => $max_days]) ?></p>
          <div data-calendar></div>
<?php else: ?>
          <p class="muted"><?= t('admin.weekdays_hint') ?></p>
          <fieldset class="segmented">
            <legend class="segmented__legend"><?= t('home.field_weekdays') ?></legend>
            <div class="segmented__options">
<?php foreach ($weekdayNames as $n => $name): ?>
<?php $has = in_array($n, $event['days'], true) ?>
              <label class="segmented__option"><input type="checkbox" name="weekday" value="<?= e($n) ?>"<?= $has ? ' checked disabled' : '' ?>><span class="segmented__text"><?= e($name) ?></span></label>
<?php endforeach ?>
            </div>
          </fieldset>
<?php endif ?>
          <div><button class="button button--primary" type="submit"><?= t('admin.days_save') ?></button></div>
        </form>
      </section>

      <section class="section">
        <h2 data-admin-people-title><?= t('admin.people_title') ?></h2>
        <p class="muted"><?= t('admin.people_hint') ?></p>
        <div data-admin-people></div>
      </section>

      <section class="section">
        <h2><?= t('admin.delete_title') ?></h2>
        <p class="muted" data-admin-expiry></p>
        <div class="confirm" data-confirm>
          <button class="button button--danger" type="button" data-confirm-ask><?= t('admin.delete') ?></button>
          <div class="confirm__step" data-confirm-step hidden>
            <p class="confirm__question"><?= t('admin.delete_question') ?></p>
            <button class="button button--danger" type="button" data-admin-delete><?= t('admin.delete_confirm') ?></button>
            <button class="button" type="button" data-confirm-cancel><?= t('admin.cancel') ?></button>
          </div>
        </div>
      </section>
    </div>
<?php json_block('page-data', $data) ?>
