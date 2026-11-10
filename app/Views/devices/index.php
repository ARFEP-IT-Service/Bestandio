<?php
use Bestandio\Core\Auth;
use Bestandio\Models\Device;

$query = array_filter($filter) + ['sort' => $sort, 'dir' => $dir];
$sortLink = static function (string $key) use ($query, $sort, $dir): string {
    $next = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
    return url('/devices', ['sort' => $key, 'dir' => $next] + $query);
};
$sortMark = static fn (string $key): string => $sort === $key ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
$columns = ['inventory_no', 'category', 'manufacturer', 'model', 'serial_no', 'location', 'status', 'warranty_until'];
?>
<div class="pagehead">
  <h1><?= e(t('device.list_title')) ?></h1>
  <div class="actions">
    <?php if (Auth::can('edit')): ?>
    <a class="btn btn-primary" href="<?= e(url('/devices/new')) ?>"><?= e(t('device.new')) ?></a>
    <?php endif; ?>
    <a class="btn btn-secondary" href="<?= e(url('/devices/export', $query)) ?>"><?= e(t('device.export')) ?></a>
  </div>
</div>

<form method="get" action="<?= e(url('/devices')) ?>" class="filters" role="search">
  <div class="field grow">
    <label for="q"><?= e(t('device.search')) ?></label>
    <input id="q" name="q" type="search" maxlength="100" value="<?= e($filter['q']) ?>">
  </div>
  <div class="field">
    <label for="f-category"><?= e(t('device.category')) ?></label>
    <select id="f-category" name="category">
      <option value=""><?= e(t('filter.all')) ?></option>
      <?php foreach ($categories as $cid => $cname): ?>
      <option value="<?= e($cid) ?>"<?= e($filter['category'] === $cid ? ' selected' : '') ?>><?= e($cname) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="f-location"><?= e(t('device.location')) ?></label>
    <select id="f-location" name="location">
      <option value=""><?= e(t('filter.all')) ?></option>
      <?php foreach ($locations as $lid => $lname): ?>
      <option value="<?= e($lid) ?>"<?= e($filter['location'] === $lid ? ' selected' : '') ?>><?= e($lname) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="f-status"><?= e(t('device.status')) ?></label>
    <select id="f-status" name="status">
      <option value=""><?= e(t('filter.all')) ?></option>
      <?php foreach (Device::STATUSES as $st): ?>
      <option value="<?= e($st) ?>"<?= e($filter['status'] === $st ? ' selected' : '') ?>><?= e(t('status.' . $st)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <input type="hidden" name="sort" value="<?= e($sort) ?>">
  <input type="hidden" name="dir" value="<?= e($dir) ?>">
  <div class="field buttons">
    <button type="submit" class="btn-primary"><?= e(t('filter.apply')) ?></button>
    <a class="btn btn-secondary" href="<?= e(url('/devices')) ?>"><?= e(t('filter.reset')) ?></a>
  </div>
</form>

<p class="muted"><?= e(t('device.count', ['n' => $total])) ?></p>

<?php if ($rows): ?>
<div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <?php foreach ($columns as $col): ?>
        <th scope="col"><a href="<?= e($sortLink($col)) ?>"><?= e(t('device.' . $col)) ?><?= e($sortMark($col)) ?></a></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $row): ?>
      <tr>
        <td data-label="<?= e(t('device.inventory_no')) ?>"><a href="<?= e(url('/devices/' . $row['id'])) ?>"><?= e($row['inventory_no']) ?></a></td>
        <td data-label="<?= e(t('device.category')) ?>"><?= e($row['category']) ?></td>
        <td data-label="<?= e(t('device.manufacturer')) ?>"><?= e($row['manufacturer']) ?></td>
        <td data-label="<?= e(t('device.model')) ?>"><?= e($row['model']) ?></td>
        <td data-label="<?= e(t('device.serial_no')) ?>"><?= e($row['serial_no']) ?></td>
        <td data-label="<?= e(t('device.location')) ?>"><?= e($row['location']) ?></td>
        <td data-label="<?= e(t('device.status')) ?>"><span class="badge badge-<?= e($row['status']) ?>"><?= e(t('status.' . $row['status'])) ?></span></td>
        <td data-label="<?= e(t('device.warranty_until')) ?>"><?= e(fmt_date($row['warranty_until'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else: ?>
<p class="empty"><?= e(t('device.none')) ?></p>
<?php endif; ?>

<?php if ($pages > 1): ?>
<nav class="pager" aria-label="<?= e(t('pager.label')) ?>">
  <?php if ($page > 1): ?>
  <a class="btn btn-secondary" href="<?= e(url('/devices', ['page' => $page - 1] + $query)) ?>"><?= e(t('pager.prev')) ?></a>
  <?php endif; ?>
  <span><?= e(t('pager.status', ['page' => $page, 'pages' => $pages])) ?></span>
  <?php if ($page < $pages): ?>
  <a class="btn btn-secondary" href="<?= e(url('/devices', ['page' => $page + 1] + $query)) ?>"><?= e(t('pager.next')) ?></a>
  <?php endif; ?>
</nav>
<?php endif; ?>
