<?php
// One event. The grids, legend, respondent list and details are drawn by
// public/assets/js/event.js into the empty data-* containers below.
//
// The two panes are in reading order (yours, then the group), so a theme can
// place them side by side or stacked with CSS alone.
?>
    <div class="event stack stack--loose" data-event>
      <header class="event__head stack">
        <h1 class="event__title" data-title><?= e($event['title']) ?></h1>

        <div class="panel event__created" data-created hidden>
          <h2><?= t('event.created_title') ?></h2>
          <p><?= t('event.created_body') ?></p>
          <div class="share">
            <label class="field__label" for="admin-link"><?= t('event.admin_label') ?></label>
            <div class="share__row">
              <input class="field__input share__link" id="admin-link" type="text" readonly>
              <button class="button share__copy" type="button" data-copy="admin-link"><?= t('share.copy') ?></button>
            </div>
          </div>
          <div><button class="button" type="button" data-created-dismiss><?= t('event.created_dismiss') ?></button></div>
        </div>

        <div class="share">
          <label class="field__label" for="share-link"><?= t('share.label') ?></label>
          <div class="share__row">
            <input class="field__input share__link" id="share-link" type="text" readonly value="<?= e(url('/e/' . $event['slug'])) ?>">
            <button class="button share__copy" type="button" data-copy="share-link"><?= t('share.copy') ?></button>
          </div>
        </div>
      </header>

      <noscript><p class="notice notice--error"><?= t('event.noscript') ?></p></noscript>
      <p class="notice notice--error" data-gone role="alert" hidden><?= t('event.gone') ?></p>

      <div class="event__bar">
        <label class="field field--inline" data-zone-field>
          <span class="field__label"><?= t('timezone.label') ?></span>
          <select class="field__input" data-zone>
<?php foreach ($zones as $zone): ?>
            <option><?= e($zone) ?></option>
<?php endforeach ?>
          </select>
        </label>
        <p class="notice" data-weekly-note hidden></p>
        <p class="notice" data-zone-note hidden></p>
        <span class="save-state" data-save data-state="saved" role="status" hidden></span>
      </div>

      <div class="tabs event__tabs" role="tablist" aria-label="<?= t('grid.tabs_label') ?>">
        <button class="tabs__tab" type="button" role="tab" aria-selected="true" aria-controls="pane-mine" data-tab-for="mine"><?= t('grid.tab_mine') ?></button>
        <button class="tabs__tab" type="button" role="tab" aria-selected="false" aria-controls="pane-group" data-tab-for="group"><?= t('grid.tab_group') ?></button>
      </div>

      <div class="event__grids" data-panes data-tab="mine">
        <section class="event__pane event__pane--mine" id="pane-mine">
          <h2><?= t('grid.mine_title') ?></h2>

          <form class="panel signin" data-signin>
            <h3><?= t('signin.title') ?></h3>
            <label class="field">
              <span class="field__label"><?= t('signin.name') ?></span>
              <input class="field__input" type="text" name="name" maxlength="40" required autocomplete="off">
            </label>
            <label class="field">
              <span class="field__label"><?= t('signin.password') ?></span>
              <input class="field__input" type="password" name="password" autocomplete="off">
              <span class="field__hint"><?= t('signin.password_hint') ?></span>
            </label>
            <p class="field__error" data-signin-error role="alert" hidden></p>
            <div><button class="button button--primary" type="submit"><?= t('signin.submit') ?></button></div>
          </form>

          <div class="event__mine stack stack--tight" data-mine hidden>
            <p class="muted small">
              <span data-me></span>
              <button class="button button--quiet button--inline" type="button" data-signout><?= t('signin.signout') ?></button>
            </p>
            <p class="muted small"><?= t('grid.mine_hint') ?></p>
            <fieldset class="segmented segmented--touch">
              <legend class="segmented__legend"><?= t('grid.mode_label') ?></legend>
              <div class="segmented__options">
                <label class="segmented__option"><input type="radio" name="touch-mode" value="paint" checked><span class="segmented__text"><?= t('grid.mode_paint') ?></span></label>
                <label class="segmented__option"><input type="radio" name="touch-mode" value="scroll"><span class="segmented__text"><?= t('grid.mode_scroll') ?></span></label>
              </div>
            </fieldset>
            <div data-mine-grid></div>
          </div>
        </section>

        <section class="event__pane event__pane--group" id="pane-group">
          <h2><?= t('grid.group_title') ?></h2>
          <div class="legend" data-legend></div>
          <div data-heat-grid></div>
          <div class="slot-detail" data-detail aria-live="polite"></div>
          <div class="respondents" data-respondents></div>
          <div class="visually-hidden" data-table></div>
        </section>
      </div>
    </div>
<?php json_block('page-data', $data) ?>
