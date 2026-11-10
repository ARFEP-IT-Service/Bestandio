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
    </div>
    <div class="scan-live" data-scan-live role="dialog" aria-modal="true" aria-label="<?= e(t('scan.camera')) ?>" hidden>
      <video class="scan-video" data-scan-video playsinline muted></video>
      <div class="scan-frame" data-scan-frame aria-hidden="true"><span class="scan-line"></span></div>
      <p class="scan-live-hint"><?= e(t('scan.searching')) ?></p>
      <div class="scan-live-bar">
        <button type="button" class="scan-live-btn" data-scan-stop aria-label="<?= e(t('scan.stop')) ?>" title="<?= e(t('scan.stop')) ?>">
          <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
        <button type="button" class="scan-live-btn" data-scan-menu-toggle aria-expanded="false" aria-controls="scan-live-menu" aria-label="<?= e(t('scan.more_options')) ?>" title="<?= e(t('scan.more_options')) ?>">
          <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" fill="currentColor"><circle cx="5" cy="12" r="2.2"/><circle cx="12" cy="12" r="2.2"/><circle cx="19" cy="12" r="2.2"/></svg>
        </button>
      </div>
      <ul class="scan-live-menu" id="scan-live-menu" data-scan-menu hidden>
        <li><button type="button" data-scan-use-photo><?= e(t('scan.photo_choose')) ?></button></li>
        <li><button type="button" data-scan-use-manual><?= e(t('scan.manual')) ?></button></li>
      </ul>
    </div>
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
