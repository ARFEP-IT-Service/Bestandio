<?php
use Bestandio\Core\Auth;

$canEdit = Auth::can('lists');
$count = static fn (int $n): string => t($n === 1 ? 'list.used_one' : 'list.used', ['n' => $n]);
$max = max(1, ...array_map(static fn ($i) => (int) $i['used'], $items ?: [['used' => 1]]));
?>
<div class="pagehead">
  <h1><?= e(t('list.' . $kind)) ?> <span class="count-pill"><?= e(count($items)) ?></span></h1>
</div>

<div class="list-layout<?= e($canEdit ? ' has-aside' : '') ?>">
  <?php if ($canEdit): ?>
  <aside class="panel list-aside">
    <h2><?= e(t('list.new')) ?></h2>
    <form method="post" action="<?= e(url('/' . $kind)) ?>" class="add-form">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <label class="visually-hidden" for="new-name"><?= e(t('list.name')) ?></label>
      <input id="new-name" name="name" type="text" required maxlength="100" placeholder="<?= e(t('list.new_placeholder')) ?>">
      <button type="submit" class="btn-primary"><?= e(t('action.add')) ?></button>
    </form>
    <p class="hint"><?= e(t('list.delete_hint')) ?></p>
  </aside>
  <?php endif; ?>

  <?php if ($items): ?>
  <ul class="namelist">
    <?php foreach ($items as $item): ?>
    <?php $id = (int) $item['id']; $used = (int) $item['used']; ?>
    <li class="namelist-row<?= e($editId === $id ? ' is-editing' : '') ?>">
      <?php if ($editId === $id): ?>
      <form method="post" action="<?= e(url('/' . $kind . '/' . $id)) ?>" class="rename-form">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <label class="visually-hidden" for="name-<?= e($id) ?>"><?= e(t('list.name')) ?></label>
        <input id="name-<?= e($id) ?>" name="name" type="text" required maxlength="100" value="<?= e($item['name']) ?>" autofocus>
        <button type="submit" class="btn-primary btn-small"><?= e(t('action.save')) ?></button>
        <a class="btn btn-small btn-ghost" href="<?= e(url('/' . $kind)) ?>"><?= e(t('action.cancel')) ?></a>
      </form>
      <?php else: ?>
      <span class="namelist-name"><?= e($item['name']) ?></span>
      <progress class="namelist-bar" value="<?= e($used) ?>" max="<?= e($max) ?>" aria-hidden="true"></progress>
      <span class="namelist-count<?= e($used === 0 ? ' is-zero' : '') ?>"><?= e($count($used)) ?></span>
      <?php if ($canEdit): ?>
      <span class="namelist-actions">
        <a class="btn btn-small btn-ghost" href="<?= e(url('/' . $kind, ['edit' => $id])) ?>"><?= e(t('action.rename')) ?></a>
        <?php if ($used === 0): ?>
        <form method="post" action="<?= e(url('/' . $kind . '/' . $id . '/delete')) ?>" data-confirm="<?= e(t('list.delete_confirm')) ?>">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <button type="submit" class="btn-small btn-ghost btn-ghost-danger"><?= e(t('action.delete')) ?></button>
        </form>
        <?php endif; ?>
      </span>
      <?php endif; ?>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php else: ?>
  <p class="empty"><?= e(t('list.none')) ?></p>
  <?php endif; ?>
</div>
