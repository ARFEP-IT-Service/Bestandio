<?php
use Bestandio\Core\Auth;

$fields = ['inventory_no', 'category', 'manufacturer', 'model', 'serial_no', 'location', 'status', 'purchase_date', 'warranty_until', 'note', 'created_at', 'updated_at'];
$display = static function (string $field, ?string $value): string {
    if ($value === null || $value === '') {
        return '–';
    }
    return match ($field) {
        'status' => t('status.' . $value),
        'purchase_date', 'warranty_until' => fmt_date($value),
        'created_at', 'updated_at' => fmt_date($value, true),
        default => $value,
    };
};
?>
<div class="pagehead">
  <h1><?= e($device['inventory_no']) ?> <span class="badge badge-<?= e($device['status']) ?>"><?= e(t('status.' . $device['status'])) ?></span></h1>
  <div class="actions">
    <?php if (Auth::can('edit')): ?>
    <a class="btn btn-primary" href="<?= e(url('/devices/' . $device['id'] . '/edit')) ?>"><?= e(t('action.edit')) ?></a>
    <?php if ($device['status'] !== 'retired'): ?>
    <form method="post" action="<?= e(url('/devices/' . $device['id'] . '/retire')) ?>" data-confirm="<?= e(t('device.retire_confirm')) ?>">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <button type="submit" class="btn-danger"><?= e(t('device.retire')) ?></button>
    </form>
    <?php endif; ?>
    <?php endif; ?>
    <a class="btn btn-secondary" href="<?= e(url('/devices')) ?>"><?= e(t('action.back')) ?></a>
  </div>
</div>

<dl class="details panel">
  <?php foreach ($fields as $f): ?>
  <div>
    <dt><?= e(t('device.' . $f)) ?></dt>
    <dd class="<?= e($f === 'note' ? 'prewrap' : '') ?>"><?= e($display($f, $device[$f] === null ? null : (string) $device[$f])) ?></dd>
  </div>
  <?php endforeach; ?>
</dl>

<h2><?= e(t('history.title')) ?></h2>
<?php if ($history): ?>
<div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <th scope="col"><?= e(t('history.when')) ?></th>
        <th scope="col"><?= e(t('history.who')) ?></th>
        <th scope="col"><?= e(t('history.field')) ?></th>
        <th scope="col"><?= e(t('history.old')) ?></th>
        <th scope="col"><?= e(t('history.new')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($history as $h): ?>
      <tr>
        <td data-label="<?= e(t('history.when')) ?>"><?= e(fmt_date($h['changed_at'], true)) ?></td>
        <td data-label="<?= e(t('history.who')) ?>"><?= e($h['username'] ?? t('history.deleted_user')) ?></td>
        <?php if ($h['field'] === '_created'): ?>
        <td data-label="<?= e(t('history.field')) ?>" colspan="3"><?= e(t('history.created')) ?></td>
        <?php else: ?>
        <td data-label="<?= e(t('history.field')) ?>"><?= e(t('device.' . $h['field'])) ?></td>
        <td data-label="<?= e(t('history.old')) ?>" class="prewrap"><?= e($display($h['field'], $h['old_value'])) ?></td>
        <td data-label="<?= e(t('history.new')) ?>" class="prewrap"><?= e($display($h['field'], $h['new_value'])) ?></td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else: ?>
<p class="empty"><?= e(t('history.none')) ?></p>
<?php endif; ?>
