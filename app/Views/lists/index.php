<?php
use Bestandio\Core\Auth;

$canEdit = Auth::can('lists');
?>
<div class="pagehead">
  <h1><?= e(t('list.' . $kind)) ?></h1>
</div>

<?php if ($canEdit): ?>
<form method="post" action="<?= e(url('/' . $kind)) ?>" class="inline-form panel">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <label for="new-name"><?= e(t('list.new')) ?></label>
  <input id="new-name" name="name" type="text" required maxlength="100">
  <button type="submit" class="btn-primary"><?= e(t('action.add')) ?></button>
</form>
<?php endif; ?>

<?php if ($items): ?>
<ul class="itemlist">
  <?php foreach ($items as $item): ?>
  <li>
    <?php if ($canEdit): ?>
    <form method="post" action="<?= e(url('/' . $kind . '/' . $item['id'])) ?>" class="inline-form">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <label class="visually-hidden" for="name-<?= e($item['id']) ?>"><?= e(t('list.name')) ?></label>
      <input id="name-<?= e($item['id']) ?>" name="name" type="text" required maxlength="100" value="<?= e($item['name']) ?>">
      <button type="submit" class="btn-secondary"><?= e(t('action.rename')) ?></button>
    </form>
    <span class="muted"><?= e(t('list.used', ['n' => $item['used']])) ?></span>
    <?php if ((int) $item['used'] === 0): ?>
    <form method="post" action="<?= e(url('/' . $kind . '/' . $item['id'] . '/delete')) ?>" data-confirm="<?= e(t('list.delete_confirm')) ?>">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <button type="submit" class="btn-danger"><?= e(t('action.delete')) ?></button>
    </form>
    <?php endif; ?>
    <?php else: ?>
    <span><?= e($item['name']) ?></span>
    <span class="muted"><?= e(t('list.used', ['n' => $item['used']])) ?></span>
    <?php endif; ?>
  </li>
  <?php endforeach; ?>
</ul>
<?php else: ?>
<p class="empty"><?= e(t('list.none')) ?></p>
<?php endif; ?>
