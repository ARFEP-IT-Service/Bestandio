<div class="pagehead">
  <h1><?= e(t('import.title')) ?></h1>
</div>
<p class="muted"><?= e(t('import.intro')) ?></p>

<?php if ($errors): ?>
<div class="flash flash-error" role="alert">
  <p><?= e(t('import.error.title')) ?></p>
  <ul>
    <?php foreach (array_slice($errors, 0, 20) as [$key, $params]): ?>
    <li><?= e(t($key, $params)) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<form method="post" action="<?= e(url('/import')) ?>" enctype="multipart/form-data" class="form panel">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <label for="file"><?= e(t('import.file')) ?></label>
  <input id="file" name="file" type="file" accept=".json,application/json">
  <p class="hint"><?= e(t('import.max_size')) ?></p>
  <label for="json"><?= e(t('import.paste')) ?></label>
  <textarea id="json" name="json" rows="12" spellcheck="false" class="mono"><?= e($json) ?></textarea>
  <div class="actions">
    <button type="submit" class="btn-primary"><?= e(t('import.check')) ?></button>
  </div>
</form>
<p class="hint"><?= e(t('import.privacy')) ?></p>
