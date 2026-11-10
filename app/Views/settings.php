<?php
$err = static fn (string $f): string => isset($errors[$f]) ? t($errors[$f]) : '';
$num = static function (string $name, string $step) use ($values, $err): array {
    return ['name' => $name, 'step' => $step, 'value' => $values[$name], 'error' => $err($name)];
};
$sheet = [
    $num('sheet_cols', '1'), $num('sheet_rows', '1'),
    $num('sheet_margin_top', '0.1'), $num('sheet_margin_bottom', '0.1'),
    $num('sheet_margin_left', '0.1'), $num('sheet_margin_right', '0.1'),
    $num('sheet_gap_x', '0.1'), $num('sheet_gap_y', '0.1'),
];
$single = [$num('single_width', '0.1'), $num('single_height', '0.1')];
?>
<div class="pagehead">
  <h1><?= e(t('settings.title')) ?></h1>
</div>
<?php if ($errors): ?>
<p class="flash flash-error" role="alert"><?= e(t('validation.check')) ?></p>
<?php endif; ?>

<form method="post" action="<?= e(url('/settings')) ?>" class="form panel">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <fieldset>
    <legend><?= e(t('settings.company')) ?></legend>
    <label for="company_name"><?= e(t('settings.company_name')) ?></label>
    <input id="company_name" name="company_name" type="text" maxlength="100" value="<?= e($values['company_name']) ?>">
    <p class="hint"><?= e(t('settings.company_hint')) ?></p>
    <p class="error-text"><?= e($err('company_name')) ?></p>
  </fieldset>

  <fieldset>
    <legend><?= e(t('settings.sheet')) ?></legend>
    <div class="grid">
      <?php foreach ($sheet as $f): ?>
      <div class="field">
        <label for="<?= e($f['name']) ?>"><?= e(t('settings.' . $f['name'])) ?></label>
        <input id="<?= e($f['name']) ?>" name="<?= e($f['name']) ?>" type="number" min="0" step="<?= e($f['step']) ?>" required value="<?= e($f['value']) ?>">
        <p class="error-text"><?= e($f['error']) ?></p>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if ($labelSize !== null): ?>
    <p class="hint"><?= e(t('settings.label_size', ['w' => $labelSize[0], 'h' => $labelSize[1]])) ?></p>
    <?php endif; ?>
  </fieldset>

  <fieldset>
    <legend><?= e(t('settings.single')) ?></legend>
    <div class="grid">
      <?php foreach ($single as $f): ?>
      <div class="field">
        <label for="<?= e($f['name']) ?>"><?= e(t('settings.' . $f['name'])) ?></label>
        <input id="<?= e($f['name']) ?>" name="<?= e($f['name']) ?>" type="number" min="0" step="<?= e($f['step']) ?>" required value="<?= e($f['value']) ?>">
        <p class="error-text"><?= e($f['error']) ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </fieldset>

  <div class="actions">
    <button type="submit" class="btn-primary"><?= e(t('action.save')) ?></button>
  </div>
</form>
