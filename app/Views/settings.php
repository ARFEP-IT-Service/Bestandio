<?php
$err = static fn (string $f): string => isset($errors[$f]) ? t($errors[$f]) : '';
?>
<div class="pagehead">
  <h1><?= e(t('settings.title')) ?></h1>
</div>
<?php if ($errors): ?>
<p class="flash flash-error" role="alert"><?= e(t('validation.check')) ?></p>
<?php endif; ?>

<form method="post" action="<?= e(url('/settings')) ?>" class="form panel" data-dirty-guard>
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <fieldset>
    <legend><?= e(t('settings.company')) ?></legend>
    <label for="company_name"><?= e(t('settings.company_name')) ?></label>
    <input id="company_name" name="company_name" type="text" maxlength="100" value="<?= e($values['company_name']) ?>">
    <p class="hint"><?= e(t('settings.company_hint')) ?></p>
    <p class="error-text"><?= e($err('company_name')) ?></p>
  </fieldset>

  <div class="actions">
    <button type="submit" class="btn-primary"><?= e(t('action.save')) ?></button>
  </div>
</form>

<section class="panel">
  <h2><?= e(t('tpl.title')) ?></h2>
  <p class="hint"><?= e(t('tpl.settings_hint')) ?></p>
  <div class="actions">
    <a class="btn btn-secondary" href="<?= e(url('/labels/templates')) ?>"><?= e(t('tpl.manage')) ?></a>
  </div>
</section>
