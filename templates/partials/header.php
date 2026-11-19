  <header class="site-header">
    <div class="site-header__inner">
<?php partial('wordmark') ?>
      <nav class="site-nav">
        <a class="site-nav__link" href="<?= e(url('/')) ?>"<?= $page === 'home' ? ' aria-current="page"' : '' ?>><?= t('nav.home') ?></a>
      </nav>
    </div>
  </header>
