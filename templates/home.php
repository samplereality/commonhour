<?php
// Home: the form that creates an event. public/assets/js/create.js submits it.
// $zones, $default_slot, $needs_passphrase, $max_days and $default_theme come from the app.

$weekdayNames = copy_list('home.weekdays'); // Sunday first
?>
    <div class="stack stack--loose">
      <div class="stack">
        <h1><?= t('home.title') ?></h1>
        <p class="lede"><?= t('home.lede') ?></p>
      </div>

      <noscript><p class="notice notice--error"><?= t('home.noscript') ?></p></noscript>

      <form class="create stack stack--loose" data-create data-mode="dates" novalidate>
        <label class="field">
          <span class="field__label"><?= t('home.field_title') ?></span>
          <input class="field__input" type="text" name="title" maxlength="120" required autocomplete="off" placeholder="<?= t('home.title_placeholder') ?>">
        </label>

        <fieldset class="segmented">
          <legend class="segmented__legend"><?= t('home.field_mode') ?></legend>
          <div class="segmented__options">
            <label class="segmented__option"><input type="radio" name="mode" value="dates" checked><span class="segmented__text"><?= t('home.mode_dates') ?></span></label>
            <label class="segmented__option"><input type="radio" name="mode" value="weekdays"><span class="segmented__text"><?= t('home.mode_weekdays') ?></span></label>
          </div>
        </fieldset>

        <div class="create__dates stack stack--tight">
          <span class="field__label"><?= t('home.field_dates') ?></span>
          <div data-calendar></div>
          <span class="field__hint"><?= t('home.dates_hint', ['max' => $max_days]) ?></span>
        </div>

        <fieldset class="segmented create__weekdays">
          <legend class="segmented__legend"><?= t('home.field_weekdays') ?></legend>
          <div class="segmented__options">
<?php foreach ($weekdayNames as $n => $name): ?>
            <label class="segmented__option"><input type="checkbox" name="weekday" value="<?= e($n) ?>"<?= $n >= 1 && $n <= 5 ? ' checked' : '' ?>><span class="segmented__text"><?= e($name) ?></span></label>
<?php endforeach ?>
          </div>
        </fieldset>

        <div class="create__row">
          <label class="field">
            <span class="field__label"><?= t('home.field_start') ?></span>
            <select class="field__input" name="start_minute">
<?php for ($h = 0; $h < 24; $h++): ?>
              <option value="<?= e($h * 60) ?>"<?= $h === 9 ? ' selected' : '' ?>><?= e(format_minute($h * 60)) ?></option>
<?php endfor ?>
            </select>
          </label>
          <label class="field">
            <span class="field__label"><?= t('home.field_end') ?></span>
            <select class="field__input" name="end_minute">
<?php for ($h = 1; $h <= 24; $h++): ?>
              <option value="<?= e($h * 60) ?>"<?= $h === 17 ? ' selected' : '' ?>><?= $h === 24 ? t('home.midnight') : e(format_minute($h * 60)) ?></option>
<?php endfor ?>
            </select>
          </label>
          <label class="field">
            <span class="field__label"><?= t('home.field_slot') ?></span>
            <select class="field__input" name="slot_minutes">
              <option value="30"<?= $default_slot === 30 ? ' selected' : '' ?>><?= t('home.slot_30') ?></option>
              <option value="15"<?= $default_slot === 15 ? ' selected' : '' ?>><?= t('home.slot_15') ?></option>
            </select>
          </label>
        </div>

        <label class="field">
          <span class="field__label"><?= t('home.field_timezone') ?></span>
          <select class="field__input" name="timezone">
<?php foreach ($zones as $zone): ?>
            <option<?= $zone === 'UTC' ? ' selected' : '' ?>><?= e($zone) ?></option>
<?php endforeach ?>
          </select>
          <span class="field__hint"><?= t('home.timezone_hint') ?></span>
        </label>

<?php if (count($themes) > 1): ?>
        <label class="field">
          <span class="field__label"><?= t('home.field_theme') ?></span>
          <select class="field__input" name="theme">
            <option value=""><?= t('home.theme_default', ['name' => $default_theme]) ?></option>
<?php foreach ($themes as $name): ?>
<?php if ($name !== $default_theme): ?>
            <option value="<?= e($name) ?>"><?= e($name) ?></option>
<?php endif ?>
<?php endforeach ?>
          </select>
        </label>
<?php endif ?>

<?php if ($needs_passphrase): ?>
        <label class="field">
          <span class="field__label"><?= t('home.field_passphrase') ?></span>
          <input class="field__input" type="password" name="passphrase" autocomplete="off" required>
          <span class="field__hint"><?= t('home.passphrase_hint') ?></span>
        </label>
<?php endif ?>

        <?php // A trap for bots. People never see or reach this field. ?>
        <div class="visually-hidden" aria-hidden="true">
          <label><?= t('home.honeypot') ?> <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <p class="notice notice--error" data-create-error role="alert" tabindex="-1" hidden></p>
        <div><button class="button button--primary" type="submit"><?= t('home.submit') ?></button></div>
      </form>
    </div>
<?php json_block('page-data', $data) ?>
