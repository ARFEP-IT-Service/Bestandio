<section class="panel narrow">
  <h1><?= e(t('setup.title')) ?></h1>
  <p class="muted"><?= e(t('setup.intro')) ?></p>
  <?php if ($notice !== null): ?>
  <p class="flash flash-success" role="status"><?= e(t($notice)) ?></p>
  <?php endif; ?>
  <?php foreach ($errors as $err): ?>
  <p class="flash flash-error" role="alert"><?= e(t($err)) ?></p>
  <?php endforeach; ?>
  <form method="post" action="<?= e(url('/setup')) ?>" class="form">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <fieldset>
      <legend><?= e(t('setup.database')) ?></legend>
      <label for="db_host"><?= e(t('setup.db_host')) ?></label>
      <input id="db_host" name="db_host" type="text" required value="<?= e($values['db_host']) ?>">
      <label for="db_port"><?= e(t('setup.db_port')) ?></label>
      <input id="db_port" name="db_port" type="number" min="1" max="65535" required value="<?= e($values['db_port']) ?>">
      <label for="db_name"><?= e(t('setup.db_name')) ?></label>
      <input id="db_name" name="db_name" type="text" required value="<?= e($values['db_name']) ?>">
      <label for="db_user"><?= e(t('setup.db_user')) ?></label>
      <input id="db_user" name="db_user" type="text" required autocomplete="off" value="<?= e($values['db_user']) ?>">
      <label for="db_pass"><?= e(t('setup.db_pass')) ?></label>
      <input id="db_pass" name="db_pass" type="password" autocomplete="off" value="<?= e($values['db_pass'] ?? '') ?>">
      <button type="submit" name="action" value="test" formnovalidate class="btn-secondary"><?= e(t('setup.test')) ?></button>
    </fieldset>
    <fieldset>
      <legend><?= e(t('setup.admin')) ?></legend>
      <label for="admin_user"><?= e(t('login.username')) ?></label>
      <input id="admin_user" name="admin_user" type="text" autocomplete="off" pattern="[A-Za-z0-9._\-]{3,64}" value="<?= e($values['admin_user']) ?>">
      <p class="hint"><?= e(t('user.hint.username')) ?></p>
      <label for="admin_pass"><?= e(t('login.password')) ?></label>
      <input id="admin_pass" name="admin_pass" type="password" autocomplete="new-password" minlength="10" value="<?= e($values['admin_pass'] ?? '') ?>">
      <p class="hint"><?= e(t('user.hint.password')) ?></p>
      <label for="admin_pass2"><?= e(t('user.password_repeat')) ?></label>
      <input id="admin_pass2" name="admin_pass2" type="password" autocomplete="new-password" minlength="10" value="<?= e($values['admin_pass2'] ?? '') ?>">
    </fieldset>
    <button type="submit" name="action" value="install" class="btn-primary"><?= e(t('setup.install')) ?></button>
  </form>
</section>
