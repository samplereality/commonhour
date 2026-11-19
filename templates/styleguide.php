<?php
// The style guide: every component drawn with the active theme.
// $tokens comes from the theme file; $data holds the fake event, which
// public/assets/js/styleguide.js draws with the real grid scripts.

// Pairs checked for contrast: [foreground token, background token, minimum ratio].
$pairs = [
    ['--ink', '--bg', 4.5],
    ['--ink', '--surface', 4.5],
    ['--ink', '--surface-sunken', 4.5],
    ['--ink-muted', '--bg', 4.5],
    ['--ink-muted', '--surface', 4.5],
    ['--accent', '--bg', 4.5],
    ['--accent', '--surface', 4.5],
    ['--accent-ink', '--accent', 4.5],
    ['--accent-ink', '--accent-hover', 4.5],
    ['--error', '--bg', 4.5],
    ['--error', '--surface', 4.5],
    ['--cell-highlight-ink', '--cell-highlight', 4.5],
    ['--focus', '--bg', 3],
    ['--focus', '--surface', 3],
    ['--border-strong', '--surface', 3],
    ['--cell-selected', '--cell-empty', 3],
    ['--cell-selected-ink', '--cell-selected', 4.5],
];

?>
    <div class="sg stack stack--loose" data-styleguide>
      <div class="stack">
        <h1><?= t('styleguide.title') ?></h1>
        <p class="lede"><?= t('styleguide.lede', ['theme' => $theme]) ?></p>
<?php if (count($themes) > 1): ?>
        <p class="cluster">
          <span class="muted"><?= t('styleguide.theme_label') ?></span>
<?php foreach ($themes as $name): ?>
          <a class="button<?= $name === $theme ? ' button--primary' : '' ?>" href="<?= e(url('/styleguide?theme=' . $name)) ?>"><?= e($name) ?></a>
<?php endforeach ?>
        </p>
<?php endif ?>
      </div>

      <section class="sg__section">
        <h2><?= t('styleguide.event_title') ?></h2>
        <p class="muted"><?= t('styleguide.event_note') ?></p>
        <div class="event__grids">
          <div class="event__pane">
            <h3><?= t('grid.mine_title') ?></h3>
            <p class="muted small"><?= t('grid.mine_hint') ?></p>
            <div data-sg-mine></div>
          </div>
          <div class="event__pane">
            <h3><?= t('grid.group_title') ?></h3>
            <div class="legend" data-sg-legend></div>
            <div data-sg-heat></div>
          </div>
        </div>
      </section>

      <section class="sg__section">
        <h2><?= t('styleguide.highlight_title') ?></h2>
        <p class="muted"><?= t('styleguide.highlight_note') ?></p>
        <div class="event__grids">
          <div class="event__pane">
            <div data-sg-highlight></div>
            <div class="slot-detail" data-sg-detail></div>
          </div>
          <div class="event__pane">
            <div class="respondents" data-sg-respondents></div>
            <h3><?= t('styleguide.detail_empty_title') ?></h3>
            <div class="slot-detail" data-sg-detail-empty></div>
          </div>
        </div>
      </section>

      <section class="sg__section">
        <h2><?= t('styleguide.panels_title') ?></h2>
        <div class="sg__columns">
          <form class="panel signin">
            <h3><?= t('signin.title') ?></h3>
            <label class="field">
              <span class="field__label"><?= t('signin.name') ?></span>
              <input class="field__input" type="text" value="<?= t('styleguide.sample_name') ?>" autocomplete="off">
            </label>
            <label class="field">
              <span class="field__label"><?= t('signin.password') ?></span>
              <input class="field__input" type="password" autocomplete="off">
              <span class="field__hint"><?= t('signin.password_hint') ?></span>
            </label>
            <p class="field__error"><?= t('styleguide.signin_error') ?></p>
            <div><button class="button button--primary" type="button"><?= t('signin.submit') ?></button></div>
          </form>
          <div class="stack">
            <div class="share">
              <label class="field__label" for="sg-share"><?= t('share.label') ?></label>
              <div class="share__row">
                <input class="field__input share__link" id="sg-share" type="text" readonly value="<?= t('styleguide.sample_url') ?>">
                <button class="button share__copy" type="button"><?= t('share.copy') ?></button>
              </div>
            </div>
            <div class="cluster">
<?php foreach (['saved', 'saving', 'error'] as $state): ?>
              <span class="save-state" data-state="<?= e($state) ?>"><?= e(copy_list('js')['save_' . $state]) ?></span>
<?php endforeach ?>
            </div>
            <div class="tabs" role="tablist">
              <button class="tabs__tab" type="button" role="tab" aria-selected="true"><?= t('grid.tab_mine') ?></button>
              <button class="tabs__tab" type="button" role="tab" aria-selected="false"><?= t('grid.tab_group') ?></button>
            </div>
            <fieldset class="segmented">
              <legend class="segmented__legend"><?= t('grid.mode_label') ?></legend>
              <div class="segmented__options">
                <label class="segmented__option"><input type="radio" name="sg-touch" checked><span class="segmented__text"><?= t('grid.mode_paint') ?></span></label>
                <label class="segmented__option"><input type="radio" name="sg-touch"><span class="segmented__text"><?= t('grid.mode_scroll') ?></span></label>
              </div>
            </fieldset>
            <p class="notice"><?= t('styleguide.notice_info') ?></p>
            <p class="notice notice--error"><?= t('styleguide.notice_error') ?></p>
          </div>
        </div>
      </section>

      <section class="sg__section">
        <h2><?= t('styleguide.buttons_title') ?></h2>
        <div class="sg-states">
<?php foreach (['rest', 'hover', 'active', 'focus', 'disabled'] as $state): ?>
          <span class="sg-states__head"><?= t('styleguide.state_' . $state) ?></span>
<?php endforeach ?>
<?php foreach (['default' => '', 'primary' => ' button--primary', 'quiet' => ' button--quiet', 'danger' => ' button--danger'] as $kind => $class): ?>
<?php foreach (['rest', 'hover', 'active', 'focus', 'disabled'] as $state): ?>
          <button class="button<?= e($class) ?>" type="button"<?= $state === 'disabled' ? ' disabled' : ($state === 'rest' ? '' : ' data-force="' . e($state) . '"') ?>><?= t('styleguide.button_' . $kind) ?></button>
<?php endforeach ?>
<?php endforeach ?>
        </div>
      </section>

      <section class="sg__section">
        <h2><?= t('styleguide.forms_title') ?></h2>
        <div class="sg__columns">
          <div class="stack">
            <label class="field">
              <span class="field__label"><?= t('home.field_title') ?></span>
              <input class="field__input" type="text" placeholder="<?= t('home.title_placeholder') ?>">
            </label>
            <label class="field">
              <span class="field__label"><?= t('home.field_slot') ?></span>
              <select class="field__input">
                <option><?= t('home.slot_30') ?></option>
                <option><?= t('home.slot_15') ?></option>
              </select>
            </label>
            <fieldset class="segmented">
              <legend class="segmented__legend"><?= t('home.field_mode') ?></legend>
              <div class="segmented__options">
                <label class="segmented__option"><input type="radio" name="sg-mode" checked><span class="segmented__text"><?= t('home.mode_dates') ?></span></label>
                <label class="segmented__option"><input type="radio" name="sg-mode"><span class="segmented__text"><?= t('home.mode_weekdays') ?></span></label>
              </div>
            </fieldset>
            <fieldset class="segmented">
              <legend class="segmented__legend"><?= t('home.field_weekdays') ?></legend>
              <div class="segmented__options">
<?php foreach (copy_list('home.weekdays') as $n => $name): ?>
                <label class="segmented__option"><input type="checkbox"<?= $n >= 1 && $n <= 5 ? ' checked' : '' ?>><span class="segmented__text"><?= e($name) ?></span></label>
<?php endforeach ?>
              </div>
            </fieldset>
            <label class="field field--error">
              <span class="field__label"><?= t('styleguide.field_error_label') ?></span>
              <input class="field__input" type="text" value="<?= t('styleguide.field_error_value') ?>" aria-invalid="true">
              <span class="field__error"><?= t('errors.times') ?></span>
            </label>
          </div>
          <div class="stack stack--tight">
            <h3><?= t('styleguide.calendar_title') ?></h3>
            <div data-sg-calendar></div>
          </div>
        </div>
      </section>

      <section class="sg__section">
        <h2><?= t('styleguide.admin_title') ?></h2>
<?php $sgPeople = copy_list('styleguide.sample_people') ?>
        <ul class="admin-list">
          <li class="admin-list__item">
            <span class="admin-list__name"><?= e($sgPeople[0] ?? '') ?></span>
            <span class="admin-list__detail"><?= t('styleguide.admin_sample_detail') ?></span>
            <div class="confirm"><button class="button" type="button"><?= t('styleguide.admin_remove') ?></button></div>
          </li>
          <li class="admin-list__item">
            <span class="admin-list__name"><?= e($sgPeople[1] ?? '') ?></span>
            <span class="admin-list__detail"><?= t('styleguide.admin_sample_detail') ?></span>
            <div class="confirm">
              <div class="confirm__step">
                <p class="confirm__question"><?= t('styleguide.admin_remove_question') ?></p>
                <button class="button button--danger" type="button"><?= t('styleguide.admin_remove_confirm') ?></button>
                <button class="button" type="button"><?= t('styleguide.admin_cancel') ?></button>
              </div>
            </div>
          </li>
        </ul>
        <div class="confirm">
          <div class="confirm__step">
            <p class="confirm__question"><?= t('admin.delete_question') ?></p>
            <button class="button button--danger" type="button"><?= t('admin.delete_confirm') ?></button>
            <button class="button" type="button"><?= t('admin.cancel') ?></button>
          </div>
        </div>
        <div class="stack stack--tight">
          <p class="muted small"><?= t('styleguide.calendar_locked_note') ?></p>
          <div data-sg-calendar-locked></div>
        </div>
      </section>

      <section class="sg__section">
        <h2><?= t('styleguide.type_title') ?></h2>
        <div class="stack">
          <h1><?= t('styleguide.type_display') ?></h1>
          <h2><?= t('styleguide.type_section') ?></h2>
          <h3><?= t('styleguide.type_sub') ?></h3>
          <p><?= t('styleguide.type_body') ?> <a href="#main"><?= t('styleguide.type_link') ?></a>.</p>
          <p class="muted"><?= t('styleguide.type_muted') ?></p>
          <p class="small"><?= t('styleguide.type_small') ?></p>
          <p class="mono"><?= t('styleguide.type_mono') ?></p>
        </div>
      </section>

      <section class="sg__section">
        <h2><?= t('styleguide.contrast_title') ?></h2>
        <p class="muted"><?= t('styleguide.contrast_note') ?></p>
        <table class="sg-table" data-contrast-table data-pass-label="<?= t('styleguide.contrast_pass') ?>" data-fail-label="<?= t('styleguide.contrast_fail') ?>">
          <thead>
            <tr>
              <th scope="col"><?= t('styleguide.contrast_sample') ?></th>
              <th scope="col"><?= t('styleguide.contrast_pair') ?></th>
              <th scope="col"><?= t('styleguide.contrast_need') ?></th>
              <th scope="col"><?= t('styleguide.contrast_ratio') ?></th>
              <th scope="col"><?= t('styleguide.contrast_result') ?></th>
            </tr>
          </thead>
          <tbody>
<?php foreach ($pairs as [$fg, $bg, $min]): ?>
            <tr data-contrast data-min="<?= e($min) ?>">
              <td><span class="sg-sample" data-fg="<?= e($fg) ?>" data-bg="<?= e($bg) ?>">Aa</span></td>
              <td><code><?= t('styleguide.contrast_on', ['fg' => $fg, 'bg' => $bg]) ?></code></td>
              <td><?= e($min) ?>:1</td>
              <td data-contrast-ratio></td>
              <td class="sg-verdict" data-contrast-verdict></td>
            </tr>
<?php endforeach ?>
          </tbody>
        </table>
      </section>

      <section class="sg__section">
        <h2><?= t('styleguide.tokens_title') ?></h2>
        <p class="muted"><?= t('styleguide.tokens_note') ?></p>
        <div class="sg__columns">
<?php foreach ($tokens as $group => $items): ?>
          <table class="sg-table">
            <caption><?= e($group) ?></caption>
            <thead>
              <tr>
                <th scope="col"><?= t('styleguide.token_name') ?></th>
                <th scope="col"><span class="visually-hidden"><?= t('styleguide.contrast_sample') ?></span></th>
                <th scope="col"><?= t('styleguide.token_value') ?></th>
              </tr>
            </thead>
            <tbody>
<?php foreach ($items as $token): ?>
              <tr data-token="<?= e($token['name']) ?>">
                <th scope="row"><code><?= e($token['name']) ?></code></th>
                <td><span class="sg-swatch" data-bg="<?= e($token['name']) ?>"></span></td>
                <td><code data-token-value><?= e($token['value']) ?></code></td>
              </tr>
<?php endforeach ?>
            </tbody>
          </table>
<?php endforeach ?>
        </div>
      </section>
    </div>
<?php json_block('page-data', $data) ?>
