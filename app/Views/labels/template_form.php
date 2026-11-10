<?php
use Bestandio\Models\LabelTemplate;

$err = static fn (string $f): string => isset($errors[$f]) ? t($errors[$f]) : '';
$sheet = $values['kind'] === 'sheet';
$number = static function (string $name, string $step) use ($values, $err): void {
    $min = LabelTemplate::NUMBERS[$name][0];
    $max = LabelTemplate::NUMBERS[$name][1];
    ?>
      <div>
        <label for="<?= e($name) ?>"><?= e(t('tpl.field.' . $name)) ?></label>
        <input id="<?= e($name) ?>" name="<?= e($name) ?>" type="number" inputmode="decimal" min="<?= e($min) ?>" max="<?= e($max) ?>" step="<?= e($step) ?>" required value="<?= e($values[$name] ?? '') ?>"<?php if ($err($name) !== ''): ?> aria-invalid="true" aria-describedby="<?= e($name) ?>-error"<?php endif; ?>>
        <p class="error-text" id="<?= e($name) ?>-error"><?= e($err($name)) ?></p>
      </div>
    <?php
};
?>
<div class="pagehead">
  <h1><?= e($title) ?> · <?= e(t('tpl.kind.' . $values['kind'])) ?></h1>
</div>
<?php if ($errors): ?>
<p class="flash flash-error" role="alert"><?= e(t('validation.check')) ?></p>
<?php endif; ?>
<form method="post" action="<?= e($id === null ? url('/labels/templates') : url('/labels/templates/' . $id)) ?>" class="form panel" data-dirty-guard>
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <?php if ($id === null): ?>
  <input type="hidden" name="kind" value="<?= e($values['kind']) ?>">
  <?php endif; ?>
  <label for="name"><?= e(t('tpl.name')) ?></label>
  <input id="name" name="name" type="text" maxlength="100" required value="<?= e($values['name']) ?>">
  <p class="error-text"><?= e($err('name')) ?></p>

  <fieldset>
    <legend><?= e(t($sheet ? 'tpl.label_on_sheet' : 'tpl.label_size')) ?></legend>
    <div class="grid">
      <?php $number('width_mm', '0.01'); ?>
      <?php $number('height_mm', '0.01'); ?>
      <?php $number('dpi', '1'); ?>
      <div>
        <label for="orientation"><?= e(t('tpl.orientation')) ?></label>
        <select id="orientation" name="orientation">
          <?php foreach (LabelTemplate::ORIENTATIONS as $o): ?>
          <option value="<?= e($o) ?>"<?= e($values['orientation'] === $o ? ' selected' : '') ?>><?= e(t('tpl.orientation.' . $o)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <p class="hint"><?= e(t($sheet ? 'tpl.hint_sheet_orientation' : 'tpl.hint_single_orientation')) ?></p>
  </fieldset>

  <?php if ($sheet): ?>
  <fieldset>
    <legend><?= e(t('tpl.sheet_grid')) ?></legend>
    <div class="grid">
      <?php $number('sheet_cols', '1'); ?>
      <?php $number('sheet_rows', '1'); ?>
      <?php $number('margin_top', '0.01'); ?>
      <?php $number('margin_bottom', '0.01'); ?>
      <?php $number('margin_left', '0.01'); ?>
      <?php $number('margin_right', '0.01'); ?>
      <?php $number('gap_x', '0.01'); ?>
      <?php $number('gap_y', '0.01'); ?>
    </div>
    <p class="hint"><?= e(t('tpl.hint_fit')) ?></p>
  </fieldset>
  <?php endif; ?>

  <div class="actions">
    <button type="submit" class="btn-primary"><?= e(t('action.save')) ?></button>
    <a class="btn btn-secondary" href="<?= e(url('/labels/templates')) ?>"><?= e(t('action.cancel')) ?></a>
  </div>
</form>
