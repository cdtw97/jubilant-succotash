<?php
declare(strict_types=1);

require dirname(__DIR__) . '/layouts/header.php';
?>
<main class="container">
    <h1><?= e($heading ?? 'About') ?></h1>
    <p><?= e($text ?? '') ?></p>
</main>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
