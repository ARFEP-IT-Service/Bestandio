<?php
use Bestandio\Core\Auth;
use Bestandio\Core\I18n;
use Bestandio\Core\Request;
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="referrer" content="same-origin">
<title><?= e($title) ?> · Bestandio</title>
<link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
<script src="<?= e(asset('app.js')) ?>" defer></script>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="<?= e(url('/')) ?>">Bestandio</a>
    <?php if ($user !== null): ?>
    <nav class="mainnav" aria-label="<?= e(t('nav.label')) ?>">
      <a href="<?= e(url('/devices')) ?>"><?= e(t('nav.devices')) ?></a>
      <a href="<?= e(url('/categories')) ?>"><?= e(t('nav.categories')) ?></a>
      <a href="<?= e(url('/locations')) ?>"><?= e(t('nav.locations')) ?></a>
      <a href="<?= e(url('/scan')) ?>"><?= e(t('nav.scan')) ?></a>
      <?php if (Auth::can('import')): ?>
      <a href="<?= e(url('/import')) ?>"><?= e(t('nav.import')) ?></a>
      <?php endif; ?>
      <?php if (Auth::can('users')): ?>
      <a href="<?= e(url('/users')) ?>"><?= e(t('nav.users')) ?></a>
      <?php endif; ?>
      <?php if (Auth::can('settings')): ?>
      <a href="<?= e(url('/settings')) ?>"><?= e(t('nav.settings')) ?></a>
      <?php endif; ?>
      <a href="<?= e(url('/account')) ?>"><?= e(t('nav.account')) ?></a>
    </nav>
    <?php endif; ?>
    <div class="topbar-tools">
      <form method="post" action="<?= e(url('/lang')) ?>" class="langform">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="back" value="<?= e(Request::path()) ?>">
        <label class="visually-hidden" for="lang-select"><?= e(t('nav.language')) ?></label>
        <select id="lang-select" name="lang" data-autosubmit>
          <?php foreach (I18n::LANGS as $code): ?>
          <option value="<?= e($code) ?>"<?= e($code === $lang ? ' selected' : '') ?>><?= e(t('lang.' . $code)) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-small js-hide"><?= e(t('action.ok')) ?></button>
      </form>
      <?php if ($user !== null): ?>
      <form method="post" action="<?= e(url('/logout')) ?>">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <button type="submit" class="btn-small" title="<?= e($user['username']) ?>"><?= e(t('nav.logout')) ?></button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</header>
<main class="container">
  <?php foreach ($flashes as $f): ?>
  <p class="flash flash-<?= e($f['type']) ?>" role="status"><?= e(t($f['key'], $f['params'])) ?></p>
  <?php endforeach; ?>
  <?php require $__template; ?>
</main>
<footer class="footer">
  <span>Bestandio <?= e(BESTANDIO_VERSION) ?></span>
  <?php if ($user !== null): ?>
  <span><?= e(t('nav.signed_in_as', ['name' => $user['username'], 'role' => t('role.' . $user['role'])])) ?></span>
  <?php endif; ?>
</footer>
</body>
</html>
