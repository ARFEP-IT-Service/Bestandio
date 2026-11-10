<?php
use Bestandio\Core\Auth;
use Bestandio\Core\I18n;
use Bestandio\Core\Request;

$path = Request::path();
$nav = [
    ['/devices', 'nav.devices', 'view'],
    ['/scan', 'nav.scan', 'view'],
    ['/categories', 'nav.categories', 'view'],
    ['/locations', 'nav.locations', 'view'],
    ['/import', 'nav.import', 'import'],
    ['/users', 'nav.users', 'users'],
    ['/settings', 'nav.settings', 'settings'],
    ['/account', 'nav.account', 'view'],
];
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="referrer" content="same-origin">
<title><?= e($title) ?> · Bestandio</title>
<link rel="icon" type="image/svg+xml" href="<?= e(asset('logo.svg')) ?>">
<link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
<script src="<?= e(asset('app.js')) ?>" defer></script>
</head>
<body class="<?= e($user === null ? 'is-guest' : 'is-app') ?>">
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="<?= e(url('/')) ?>"><img class="brand-logo" src="<?= e(asset('logo.svg')) ?>" width="34" height="34" alt=""><span>Bestandio</span></a>
    <div class="topbar-tools">
      <form method="post" action="<?= e(url('/lang')) ?>" class="langform">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="back" value="<?= e($path) ?>">
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
        <button type="submit" class="btn-small btn-ghost" title="<?= e($user['username']) ?>"><?= e(t('nav.logout')) ?></button>
      </form>
      <?php endif; ?>
    </div>
    <?php if ($user !== null): ?>
    <nav class="mainnav" aria-label="<?= e(t('nav.label')) ?>">
      <?php foreach ($nav as [$href, $key, $perm]): ?>
      <?php if (Auth::can($perm)): ?>
      <a href="<?= e(url($href)) ?>"<?php if ($path === $href || str_starts_with($path, $href . '/')): ?> aria-current="page"<?php endif; ?>><?= e(t($key)) ?></a>
      <?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <?php endif; ?>
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
