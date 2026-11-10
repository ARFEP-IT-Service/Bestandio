<?php
use Bestandio\Models\User;
?>
<div class="pagehead">
  <h1><?= e($title) ?></h1>
</div>
<?php foreach ($errors as $err): ?>
<p class="flash flash-error" role="alert"><?= e(t($err)) ?></p>
<?php endforeach; ?>
<form method="post" action="<?= e($id === null ? url('/users') : url('/users/' . $id)) ?>" class="form panel narrow">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <label for="username"><?= e(t('login.username')) ?></label>
  <?php if ($id === null): ?>
  <input id="username" name="username" type="text" required autocomplete="off" pattern="[A-Za-z0-9._\-]{3,64}" value="<?= e($edit['username']) ?>">
  <p class="hint"><?= e(t('user.hint.username')) ?></p>
  <?php else: ?>
  <input id="username" type="text" value="<?= e($edit['username']) ?>" disabled>
  <?php endif; ?>

  <label for="role"><?= e(t('user.role')) ?></label>
  <select id="role" name="role">
    <?php foreach (User::ROLES as $r): ?>
    <option value="<?= e($r) ?>"<?= e($edit['role'] === $r ? ' selected' : '') ?>><?= e(t('role.' . $r)) ?></option>
    <?php endforeach; ?>
  </select>

  <?php if ($id !== null): ?>
  <label class="check"><input type="checkbox" name="active" value="1"<?= e((int) $edit['active'] === 1 ? ' checked' : '') ?>> <?= e(t('user.active')) ?></label>
  <?php endif; ?>

  <label for="password"><?= e($id === null ? t('login.password') : t('user.password_new_optional')) ?></label>
  <input id="password" name="password" type="password" autocomplete="new-password" minlength="12"<?= e($id === null ? ' required' : '') ?>>
  <p class="hint"><?= e(t('user.hint.password')) ?></p>
  <label for="password2"><?= e(t('user.password_repeat')) ?></label>
  <input id="password2" name="password2" type="password" autocomplete="new-password" minlength="12"<?= e($id === null ? ' required' : '') ?>>

  <div class="actions">
    <button type="submit" class="btn-primary"><?= e(t('action.save')) ?></button>
    <a class="btn btn-secondary" href="<?= e(url('/users')) ?>"><?= e(t('action.cancel')) ?></a>
  </div>
</form>
