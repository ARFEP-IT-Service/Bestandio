<?php
use Bestandio\Core\Auth;
use Bestandio\Core\I18n;
use Bestandio\Core\Request;

$path = Request::path();
$isAt = static fn (string $href): bool => $path === $href || str_starts_with($path, $href . '/');
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
// Phone: everything except devices and scan lives in "More".
$more = array_values(array_filter($nav, static fn ($n) => !in_array($n[0], ['/devices', '/scan'], true)));
$moreActive = false;
foreach ($more as [$href]) {
    $moreActive = $moreActive || $isAt($href);
}
$onDevices = $isAt('/devices') && $path !== '/devices/new';
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" data-sw="<?= e(url('/sw.js')) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="light dark">
<meta name="referrer" content="same-origin">
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#141e26" media="(prefers-color-scheme: dark)">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Bestandio">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<title><?= e($title) ?> · Bestandio</title>
<link rel="manifest" href="<?= e(url('/manifest.json')) ?>">
<link rel="icon" type="image/svg+xml" href="<?= e(asset('logo.svg')) ?>">
<link rel="apple-touch-icon" href="<?= e(asset('icons/apple-touch-icon.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
<script src="<?= e(asset('app.js')) ?>" defer></script>
</head>
<body class="<?= e($user === null ? 'is-guest' : 'is-app') ?>"<?php if (in_array(http_response_code(), [409, 422], true)): ?> data-unsaved<?php endif; ?>>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="<?= e(url('/')) ?>"><img class="brand-logo" src="<?= e(asset('logo.svg')) ?>" width="34" height="34" alt=""><span class="brand-name">Bestandio</span></a>
    <?php if ($user !== null): ?>
    <span class="topbar-title" aria-hidden="true"><?= e($title) ?></span>
    <?php endif; ?>
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
      <a href="<?= e(url($href)) ?>"<?php if ($isAt($href)): ?> aria-current="page"<?php endif; ?>><?= e(t($key)) ?></a>
      <?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <?php endif; ?>
  </div>
</header>
<?php if ($user !== null): ?>
<nav class="tabbar" aria-label="<?= e(t('nav.label')) ?>">
  <a class="tab" href="<?= e(url('/devices')) ?>"<?php if ($onDevices): ?> aria-current="page"<?php endif; ?>>
    <span class="tab-icon tab-icon-list" aria-hidden="true"></span><span class="tab-label"><?= e(t('nav.devices')) ?></span>
  </a>
  <a class="tab tab-scan" href="<?= e(url('/scan')) ?>"<?php if ($isAt('/scan')): ?> aria-current="page"<?php endif; ?>>
    <span class="tab-scan-button" aria-hidden="true"><span class="tab-icon tab-icon-scan"></span></span><span class="tab-label"><?= e(t('nav.scan')) ?></span>
  </a>
  <?php if (Auth::can('edit')): ?>
  <a class="tab" href="<?= e(url('/devices/new')) ?>"<?php if ($path === '/devices/new'): ?> aria-current="page"<?php endif; ?>>
    <span class="tab-icon tab-icon-new" aria-hidden="true"></span><span class="tab-label"><?= e(t('nav.new')) ?></span>
  </a>
  <?php endif; ?>
  <details class="tab tab-more" data-more>
    <summary<?php if ($moreActive): ?> class="is-active"<?php endif; ?>>
      <span class="tab-icon tab-icon-more" aria-hidden="true"></span><span class="tab-label"><?= e(t('nav.more')) ?></span>
    </summary>
    <div class="more-sheet">
      <ul class="more-list">
        <?php foreach ($more as [$href, $key, $perm]): ?>
        <?php if (Auth::can($perm)): ?>
        <li><a href="<?= e(url($href)) ?>"<?php if ($isAt($href)): ?> aria-current="page"<?php endif; ?>><?= e(t($key)) ?></a></li>
        <?php endif; ?>
        <?php endforeach; ?>
      </ul>
      <form method="post" action="<?= e(url('/lang')) ?>" class="more-lang">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="back" value="<?= e($path) ?>">
        <label for="lang-select-more"><?= e(t('nav.language')) ?></label>
        <select id="lang-select-more" name="lang" data-autosubmit>
          <?php foreach (I18n::LANGS as $code): ?>
          <option value="<?= e($code) ?>"<?= e($code === $lang ? ' selected' : '') ?>><?= e(t('lang.' . $code)) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="js-hide"><?= e(t('action.ok')) ?></button>
      </form>
      <form method="post" action="<?= e(url('/logout')) ?>" class="more-logout">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <button type="submit" class="btn-danger"><?= e(t('nav.logout')) ?> · <?= e($user['username']) ?></button>
      </form>
      <p class="more-version">Bestandio <?= e(BESTANDIO_VERSION) ?></p>
    </div>
  </details>
</nav>
<?php endif; ?>
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
