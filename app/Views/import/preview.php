<?php
use Bestandio\Models\Device;

$show = static function (string $field, string $value): string {
    if ($value === '') {
        return '–';
    }
    return match ($field) {
        'scanned_at' => fmt_date($value, true),
        'ram_mb' => t('device.ram_value', ['mb' => $value, 'gb' => rtrim(rtrim(number_format((int) $value / 1024, 1, '.', ''), '0'), '.')]),
        default => $value,
    };
};
$err = static fn (string $f): string => isset($errors[$f]) ? t($errors[$f]) : '';
$fields = ['manufacturer', 'model', 'serial_no', 'cpu', 'ram_mb', 'storage', 'os_name', 'os_version', 'scanned_at'];
?>
<div class="pagehead">
  <h1><?= e(t('import.preview')) ?></h1>
</div>
<p class="flash flash-warning"><?= e(t('import.not_saved_yet')) ?></p>
<?php if ($ignored > 0): ?>
<p class="muted"><?= e(t('import.ignored', ['n' => $ignored])) ?></p>
<?php endif; ?>

<h2><?= e(t('import.scan_data')) ?></h2>
<dl class="details panel">
  <?php foreach ($fields as $f): ?>
  <?php if (array_key_exists($f, $data)): ?>
  <div>
    <dt><?= e(t('device.' . $f)) ?></dt>
    <dd><?= e($show($f, (string) $data[$f])) ?></dd>
  </div>
  <?php endif; ?>
  <?php endforeach; ?>
  <?php if (array_key_exists('software', $data)): ?>
  <div>
    <dt><?= e(t('device.software')) ?></dt>
    <dd><?= e(count($data['software'])) ?></dd>
  </div>
  <?php endif; ?>
</dl>

<form method="post" action="<?= e(url('/import/confirm')) ?>" class="form">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="token" value="<?= e($token) ?>">
  <?php if (isset($errors['target'])): ?>
  <p class="flash flash-error" role="alert"><?= e(t('import.choose_target')) ?></p>
  <?php endif; ?>

  <?php foreach ($matches as $m): ?>
  <section class="panel">
    <label class="check"><input type="radio" name="target" value="<?= e($m['id']) ?>"<?= e($target === (string) $m['id'] ? ' checked' : '') ?>>
      <?= e(t('import.update', ['inv' => $m['inventory_no']])) ?></label>
    <?php $diff = $diffs[(int) $m['id']]; ?>
    <?php if ($diff): ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th scope="col"><?= e(t('history.field')) ?></th>
            <th scope="col"><?= e(t('history.old')) ?></th>
            <th scope="col"><?= e(t('history.new')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($diff as $f => [$old, $new]): ?>
          <tr>
            <td data-label="<?= e(t('history.field')) ?>"><?= e(t('device.' . $f)) ?></td>
            <td data-label="<?= e(t('history.old')) ?>"><?= e($show($f, $old)) ?></td>
            <td data-label="<?= e(t('history.new')) ?>"><?= e($show($f, $new)) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <p class="muted"><?= e(t('import.no_changes')) ?></p>
    <?php endif; ?>
  </section>
  <?php endforeach; ?>

  <section class="panel">
    <label class="check"><input type="radio" name="target" value="new"<?= e($target === 'new' ? ' checked' : '') ?>>
      <?= e($matches ? t('import.create_instead') : t('import.create')) ?></label>
    <?php if ($dupes): ?>
    <div class="flash flash-warning" role="alert">
      <p><?= e(t('device.dup_serial', ['list' => implode(', ', $dupes)])) ?></p>
      <label class="check"><input type="checkbox" name="confirm_duplicate" value="1"> <?= e(t('device.dup_confirm')) ?></label>
    </div>
    <?php endif; ?>
    <div class="grid">
      <div class="field">
        <label for="inventory_no"><?= e(t('device.inventory_no')) ?> *</label>
        <input id="inventory_no" name="inventory_no" type="text" maxlength="64" value="<?= e($device['inventory_no']) ?>">
        <p class="error-text"><?= e($err('inventory_no')) ?></p>
      </div>
      <div class="field">
        <label for="status"><?= e(t('device.status')) ?></label>
        <select id="status" name="status">
          <?php foreach (Device::STATUSES as $st): ?>
          <option value="<?= e($st) ?>"<?= e($device['status'] === $st ? ' selected' : '') ?>><?= e(t('status.' . $st)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="category_id"><?= e(t('device.category')) ?> *</label>
        <select id="category_id" name="category_id">
          <option value=""><?= e(t('form.choose')) ?></option>
          <?php foreach ($categories as $cid => $cname): ?>
          <option value="<?= e($cid) ?>"<?= e((int) $device['category_id'] === $cid ? ' selected' : '') ?>><?= e($cname) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="error-text"><?= e($err('category_id')) ?></p>
      </div>
      <div class="field">
        <label for="location_id"><?= e(t('device.location')) ?> *</label>
        <select id="location_id" name="location_id">
          <option value=""><?= e(t('form.choose')) ?></option>
          <?php foreach ($locations as $lid => $lname): ?>
          <option value="<?= e($lid) ?>"<?= e((int) $device['location_id'] === $lid ? ' selected' : '') ?>><?= e($lname) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="error-text"><?= e($err('location_id')) ?></p>
      </div>
    </div>
  </section>

  <div class="actions">
    <button type="submit" class="btn-primary"><?= e(t('import.confirm')) ?></button>
    <a class="btn btn-secondary" href="<?= e(url('/import')) ?>"><?= e(t('action.cancel')) ?></a>
  </div>
</form>
