<?php
use Bestandio\Models\Device;

$err = static fn (string $f): string => isset($errors[$f]) ? t($errors[$f]) : '';
$action = $id === null ? url('/devices') : url('/devices/' . $id);
?>
<div class="pagehead">
  <h1><?= e($title) ?></h1>
</div>

<?php if ($errors): ?>
<p class="flash flash-error" role="alert"><?= e(t('validation.check')) ?></p>
<?php endif; ?>

<?php if (!$categories || !$locations): ?>
<p class="flash flash-error" role="alert"><?= e(t('device.need_lists')) ?></p>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" class="form panel" data-dirty-guard>
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

  <?php if ($dupes): ?>
  <div class="flash flash-warning" role="alert">
    <p><?= e(t('device.dup_serial', ['list' => implode(', ', $dupes)])) ?></p>
    <label class="check"><input type="checkbox" name="confirm_duplicate" value="1"> <?= e(t('device.dup_confirm')) ?></label>
  </div>
  <?php endif; ?>

  <div class="grid">
    <div class="field">
      <label for="inventory_no"><?= e(t('device.inventory_no')) ?> *</label>
      <input id="inventory_no" name="inventory_no" type="text" required maxlength="64" value="<?= e($device['inventory_no']) ?>" aria-invalid="<?= e(isset($errors['inventory_no']) ? 'true' : 'false') ?>">
      <p class="error-text"><?= e($err('inventory_no')) ?></p>
    </div>
    <div class="field">
      <label for="category_id"><?= e(t('device.category')) ?> *</label>
      <select id="category_id" name="category_id" required>
        <option value=""><?= e(t('form.choose')) ?></option>
        <?php foreach ($categories as $cid => $cname): ?>
        <option value="<?= e($cid) ?>"<?= e((int) $device['category_id'] === $cid ? ' selected' : '') ?>><?= e($cname) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="error-text"><?= e($err('category_id')) ?></p>
    </div>
    <div class="field">
      <label for="manufacturer"><?= e(t('device.manufacturer')) ?></label>
      <input id="manufacturer" name="manufacturer" type="text" maxlength="100" value="<?= e($device['manufacturer']) ?>">
      <p class="error-text"><?= e($err('manufacturer')) ?></p>
    </div>
    <div class="field">
      <label for="model"><?= e(t('device.model')) ?></label>
      <input id="model" name="model" type="text" maxlength="100" value="<?= e($device['model']) ?>">
      <p class="error-text"><?= e($err('model')) ?></p>
    </div>
    <div class="field">
      <label for="serial_no"><?= e(t('device.serial_no')) ?></label>
      <input id="serial_no" name="serial_no" type="text" maxlength="100" value="<?= e($device['serial_no']) ?>">
      <p class="error-text"><?= e($err('serial_no')) ?></p>
    </div>
    <div class="field">
      <label for="location_id"><?= e(t('device.location')) ?> *</label>
      <select id="location_id" name="location_id" required>
        <option value=""><?= e(t('form.choose')) ?></option>
        <?php foreach ($locations as $lid => $lname): ?>
        <option value="<?= e($lid) ?>"<?= e((int) $device['location_id'] === $lid ? ' selected' : '') ?>><?= e($lname) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="error-text"><?= e($err('location_id')) ?></p>
    </div>
    <div class="field">
      <label for="status"><?= e(t('device.status')) ?> *</label>
      <select id="status" name="status" required>
        <?php foreach (Device::STATUSES as $st): ?>
        <option value="<?= e($st) ?>"<?= e($device['status'] === $st ? ' selected' : '') ?>><?= e(t('status.' . $st)) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="error-text"><?= e($err('status')) ?></p>
    </div>
    <div class="field">
      <label for="purchase_date"><?= e(t('device.purchase_date')) ?></label>
      <input id="purchase_date" name="purchase_date" type="date" value="<?= e($device['purchase_date']) ?>">
      <p class="error-text"><?= e($err('purchase_date')) ?></p>
    </div>
    <div class="field">
      <label for="warranty_until"><?= e(t('device.warranty_until')) ?></label>
      <input id="warranty_until" name="warranty_until" type="date" value="<?= e($device['warranty_until']) ?>">
      <p class="error-text"><?= e($err('warranty_until')) ?></p>
    </div>
    <div class="field wide">
      <label for="note"><?= e(t('device.note')) ?></label>
      <textarea id="note" name="note" rows="4" maxlength="5000"><?= e($device['note']) ?></textarea>
      <p class="hint"><?= e(t('device.note_hint')) ?></p>
      <p class="error-text"><?= e($err('note')) ?></p>
    </div>
  </div>

  <div class="actions">
    <button type="submit" class="btn-primary"><?= e(t('action.save')) ?></button>
    <a class="btn btn-secondary" href="<?= e($id === null ? url('/devices') : url('/devices/' . $id)) ?>"><?= e(t('action.cancel')) ?></a>
  </div>
</form>
