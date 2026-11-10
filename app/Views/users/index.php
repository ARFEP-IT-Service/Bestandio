<div class="pagehead">
  <h1><?= e(t('user.title')) ?></h1>
  <div class="actions">
    <a class="btn btn-primary" href="<?= e(url('/users/new')) ?>"><?= e(t('user.new')) ?></a>
  </div>
</div>
<div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <th scope="col"><?= e(t('login.username')) ?></th>
        <th scope="col"><?= e(t('user.role')) ?></th>
        <th scope="col"><?= e(t('user.active')) ?></th>
        <th scope="col"><?= e(t('device.created_at')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td data-label="<?= e(t('login.username')) ?>"><a href="<?= e(url('/users/' . $u['id'] . '/edit')) ?>"><?= e($u['username']) ?></a></td>
        <td data-label="<?= e(t('user.role')) ?>"><?= e(t('role.' . $u['role'])) ?></td>
        <td data-label="<?= e(t('user.active')) ?>"><?= e((int) $u['active'] === 1 ? t('common.yes') : t('common.no')) ?></td>
        <td data-label="<?= e(t('device.created_at')) ?>"><?= e(fmt_date($u['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<p class="hint"><?= e(t('user.roles_hint')) ?></p>
