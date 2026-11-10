<section class="panel narrow">
  <h1><?= e(t('login.title')) ?></h1>
  <?php if ($error !== null): ?>
  <p class="flash flash-error" role="alert"><?= e(t($error)) ?></p>
  <?php endif; ?>
  <form method="post" action="<?= e(url('/login')) ?>" class="form">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <label for="username"><?= e(t('login.username')) ?></label>
    <input id="username" name="username" type="text" autocomplete="username" required maxlength="64" value="<?= e($username) ?>" autofocus>
    <label for="password"><?= e(t('login.password')) ?></label>
    <input id="password" name="password" type="password" autocomplete="current-password" required>
    <button type="submit" class="btn-primary"><?= e(t('login.submit')) ?></button>
  </form>
</section>
