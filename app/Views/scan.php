<div class="pagehead">
  <h1><?= e(t('scan.title')) ?></h1>
</div>

<?php if ($notFound): ?>
<p class="flash flash-error" role="alert"><?= e(t('scan.not_found', ['code' => $input])) ?></p>
<?php endif; ?>

<div class="scan" data-scan data-jsqr="<?= e(asset('vendor/jsqr-1.4.0/jsQR.js')) ?>">
  <section class="panel">
    <h2><?= e(t('scan.camera')) ?></h2>
    <p class="flash flash-warning" data-scan-insecure hidden><?= e(t('scan.need_https')) ?></p>
    <p class="flash flash-error" data-scan-error hidden><?= e(t('scan.camera_error')) ?></p>
    <div class="actions">
      <button type="button" class="btn-primary" data-scan-start><?= e(t('scan.start')) ?></button>
      <button type="button" class="btn-secondary" data-scan-stop hidden><?= e(t('scan.stop')) ?></button>
    </div>
    <video class="scan-video" data-scan-video playsinline muted hidden></video>
    <p class="hint" data-scan-status hidden><?= e(t('scan.searching')) ?></p>
  </section>

  <section class="panel">
    <h2><?= e(t('scan.photo')) ?></h2>
    <label for="scan-photo"><?= e(t('scan.photo_choose')) ?></label>
    <input id="scan-photo" type="file" accept="image/*" capture="environment" data-scan-photo>
    <p class="flash flash-error" data-scan-nocode hidden><?= e(t('scan.no_code_in_photo')) ?></p>
  </section>

  <section class="panel">
    <h2><?= e(t('scan.manual')) ?></h2>
    <form method="get" action="<?= e(url('/scan')) ?>" class="inline-form" data-scan-form>
      <label class="visually-hidden" for="scan-code"><?= e(t('scan.code')) ?></label>
      <input id="scan-code" name="code" type="text" required maxlength="64" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="<?= e(t('scan.placeholder')) ?>" value="<?= e($input) ?>">
      <button type="submit" class="btn-primary"><?= e(t('scan.open')) ?></button>
    </form>
  </section>
</div>
<script src="<?= e(asset('scan.js')) ?>" defer></script>
