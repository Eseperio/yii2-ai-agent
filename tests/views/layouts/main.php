<?php

/** @var string $content */

$this->beginPage();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Yii2 AI Agent Test App</title>
    <?php $this->head(); ?>
</head>
<body>
<?php $this->beginBody(); ?>
<?= $content ?>
<?php $this->endBody(); ?>
</body>
</html>
<?php $this->endPage(); ?>
