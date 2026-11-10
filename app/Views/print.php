<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title><?= e($title) ?> · Bestandio</title>
<link rel="stylesheet" href="<?= e(asset('print.css')) ?>">
<link rel="stylesheet" href="<?= e(url('/labels/style', ['mode' => $mode])) ?>">
<script src="<?= e(asset('app.js')) ?>" defer></script>
</head>
<body class="print-page print-<?= e($mode) ?>">
<?php require $__template; ?>
</body>
</html>
