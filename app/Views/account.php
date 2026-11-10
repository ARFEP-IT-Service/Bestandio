<div class="pagehead">
  <h1><?= e(t('account.title')) ?></h1>
</div>
<?php foreach ($errors as $err): ?>
<p class="flash flash-error" role="alert"><?= e(t($err)) ?></p>
<?php endforeach; ?>
<form method="post" action="<?= e(url('/account')) ?>" class="form panel narrow">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input type="text" name="username" autocomplete="username" value="<?= e($user['username']) ?>" hidden>
  <label for="current"><?= e(t('account.current')) ?></label>
  <input id="current" name="current" type="password" autocomplete="current-password" required>
  <label for="password"><?= e(t('account.new')) ?></label>
  <input id="password" name="password" type="password" autocomplete="new-password" minlength="10" required>
  <p class="hint"><?= e(t('user.hint.password')) ?></p>
  <label for="password2"><?= e(t('user.password_repeat')) ?></label>
  <input id="password2" name="password2" type="password" autocomplete="new-password" minlength="10" required>
  <div class="actions">
    <button type="submit" class="btn-primary"><?= e(t('action.save')) ?></button>
  </div>
</form>
