<?php
use Bestandio\Models\LabelTemplate;

$size = static fn (array $t): string => LabelTemplate::num($t['width_mm']) . ' × ' . LabelTemplate::num($t['height_mm']) . ' mm';
?>
<div class="pagehead">
  <h1><?= e(t('tpl.title')) ?></h1>
  <div class="actions">
    <a class="btn btn-primary" href="<?= e(url('/labels/templates/new', ['kind' => 'single'])) ?>"><?= e(t('tpl.new_single')) ?></a>
    <a class="btn btn-secondary" href="<?= e(url('/labels/templates/new', ['kind' => 'sheet'])) ?>"><?= e(t('tpl.new_sheet')) ?></a>
  </div>
</div>
<p class="hint"><?= e(t('tpl.hint')) ?></p>
<div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <th scope="col"><?= e(t('tpl.name')) ?></th>
        <th scope="col"><?= e(t('tpl.kind')) ?></th>
        <th scope="col"><?= e(t('tpl.size')) ?></th>
        <th scope="col"><?= e(t('tpl.dpi')) ?></th>
        <th scope="col"><?= e(t('tpl.default')) ?></th>
        <th scope="col"><span class="visually-hidden"><?= e(t('tpl.actions')) ?></span></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($templates as $tpl): ?>
      <?php $builtin = LabelTemplate::isBuiltin($tpl); ?>
      <tr>
        <td data-label="<?= e(t('tpl.name')) ?>">
          <?php if ($builtin): ?>
          <?= e(LabelTemplate::displayName($tpl)) ?> <span class="badge badge-stock"><?= e(t('tpl.builtin')) ?></span>
          <?php else: ?>
          <a href="<?= e(url('/labels/templates/' . $tpl['id'] . '/edit')) ?>"><?= e(LabelTemplate::displayName($tpl)) ?></a>
          <?php endif; ?>
        </td>
        <td data-label="<?= e(t('tpl.kind')) ?>"><?= e(t('tpl.kind.' . $tpl['kind'])) ?></td>
        <td data-label="<?= e(t('tpl.size')) ?>">
          <?= e($size($tpl)) ?><?php if ($tpl['kind'] === 'sheet'): ?> · <?= e($tpl['sheet_cols'] . ' × ' . $tpl['sheet_rows']) ?><?php endif; ?><?php if ($tpl['orientation'] === 'landscape'): ?> · <?= e(t('tpl.orientation.landscape_short')) ?><?php endif; ?>
        </td>
        <td data-label="<?= e(t('tpl.dpi')) ?>"><?= e($tpl['dpi']) ?></td>
        <td data-label="<?= e(t('tpl.default')) ?>">
          <?php if ($tpl['is_default']): ?>
          <span class="badge badge-in_use"><?= e(t('tpl.default')) ?></span>
          <?php else: ?>
          <form method="post" action="<?= e(url('/labels/templates/' . $tpl['id'] . '/default')) ?>" class="inline-action">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <button type="submit" class="btn-small btn-ghost"><?= e(t('tpl.make_default')) ?></button>
          </form>
          <?php endif; ?>
        </td>
        <td class="row-actions">
          <a class="btn btn-small btn-secondary" href="<?= e(url('/labels/templates/new', ['copy' => $tpl['id']])) ?>"><?= e(t('tpl.copy')) ?></a>
          <?php if (!$builtin && !$tpl['is_default']): ?>
          <form method="post" action="<?= e(url('/labels/templates/' . $tpl['id'] . '/delete')) ?>" class="inline-action" data-confirm="<?= e(t('tpl.delete_confirm')) ?>">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <button type="submit" class="btn-small btn-ghost btn-ghost-danger"><?= e(t('action.delete')) ?></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
