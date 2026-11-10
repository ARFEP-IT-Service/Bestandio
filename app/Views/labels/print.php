<?php
use Bestandio\Models\LabelTemplate;

$query = static fn (array $extra): array => ['ids' => $ids, 'tpl' => $tpl['id']] + $extra;
$groups = ['sheet' => [], 'single' => []];
foreach ($templates as $t) {
    $groups[$t['kind']][] = $t;
}
$slots = array_merge(array_fill(0, $skip, null), $devices);
$pages = $mode === 'sheet' ? array_chunk($slots, max(1, $perSheet)) : [];
?>
<div class="print-toolbar no-print">
  <a class="btn" href="<?= e(url('/devices')) ?>"><?= e(t('action.back')) ?></a>
  <form method="get" action="<?= e(url('/labels')) ?>" class="tpl-form">
    <?php foreach ($ids as $id): ?>
    <input type="hidden" name="ids[]" value="<?= e($id) ?>">
    <?php endforeach; ?>
    <label for="tpl"><?= e(t('label.template')) ?></label>
    <select id="tpl" name="tpl" data-autosubmit>
      <?php foreach ($groups as $kind => $list): ?>
      <optgroup label="<?= e(t('tpl.kind.' . $kind)) ?>">
        <?php foreach ($list as $t): ?>
        <option value="<?= e($t['id']) ?>"<?= e($t['id'] === $tpl['id'] ? ' selected' : '') ?>><?= e(LabelTemplate::displayName($t)) ?><?= e($t['is_default'] ? ' · ' . t('tpl.default') : '') ?></option>
        <?php endforeach; ?>
      </optgroup>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn js-hide"><?= e(t('action.ok')) ?></button>
  </form>
  <button type="button" class="btn btn-primary" data-print><?= e(t('label.print')) ?></button>
  <p class="print-hint"><?= e(t('label.count', ['n' => count($devices)])) ?> · <?= e(t('label.hint')) ?></p>
  <?php if ($mode === 'sheet' && $devices): ?>
  <section class="sheetpick" aria-labelledby="sheetpick-title">
    <h2 id="sheetpick-title"><?= e(t('label.pick_title')) ?></h2>
    <p class="print-hint"><?= e(t('label.pick_hint')) ?></p>
    <div class="sheetpick-body">
      <nav class="sheetpick-grid" aria-label="<?= e(t('label.pick_title')) ?>">
        <?php for ($n = 1; $n <= $perSheet; $n++): ?>
        <?php $state = $n < $start ? 'is-skipped' : ($n === $start ? 'is-start is-filled' : ($n < $start + count($devices) ? 'is-filled' : '')); ?>
        <a class="sheetpick-cell <?= e($state) ?>" href="<?= e(url('/labels', $query(['start' => $n]))) ?>"<?php if ($n === $start): ?> aria-current="true"<?php endif; ?> aria-label="<?= e(t('label.cell', ['n' => $n])) ?>"><?= e($n) ?></a>
        <?php endfor; ?>
      </nav>
      <form method="get" action="<?= e(url('/labels')) ?>" class="skip-form">
        <?php foreach ($ids as $id): ?>
        <input type="hidden" name="ids[]" value="<?= e($id) ?>">
        <?php endforeach; ?>
        <input type="hidden" name="tpl" value="<?= e($tpl['id']) ?>">
        <label for="start"><?= e(t('label.start_at')) ?></label>
        <input id="start" name="start" type="number" inputmode="numeric" min="1" max="<?= e($perSheet) ?>" value="<?= e($start) ?>">
        <button type="submit" class="btn"><?= e(t('action.ok')) ?></button>
      </form>
    </div>
  </section>
  <?php endif; ?>
</div>

<?php if (!$devices): ?>
<p class="print-empty no-print"><?= e(t('label.none')) ?></p>
<?php elseif ($mode === 'sheet'): ?>
<?php foreach ($pages as $page): ?>
<section class="sheet">
  <?php foreach ($page as $d): ?>
  <?php if ($d === null): ?>
  <div class="label label-empty"></div>
  <?php else: ?>
  <div class="label">
    <img class="label-qr" src="<?= e(url('/devices/' . $d['id'] . '/qr')) ?>" alt="">
    <div class="label-text">
      <span class="label-code"><?= e($d['code']) ?></span>
      <span class="label-inv"><?= e($d['inventory_no']) ?></span>
      <?php if ($company !== ''): ?>
      <span class="label-company"><?= e($company) ?></span>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
  <?php endforeach; ?>
</section>
<?php endforeach; ?>
<?php else: ?>
<div class="singles">
  <?php foreach ($devices as $d): ?>
  <div class="label">
    <img class="label-qr" src="<?= e(url('/devices/' . $d['id'] . '/qr')) ?>" alt="">
    <div class="label-text">
      <span class="label-code"><?= e($d['code']) ?></span>
      <span class="label-inv"><?= e($d['inventory_no']) ?></span>
      <?php if ($company !== ''): ?>
      <span class="label-company"><?= e($company) ?></span>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
