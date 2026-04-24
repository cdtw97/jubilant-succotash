<?php
declare(strict_types=1);

/** @var \MyFrancis\Config\AppConfig $app */
$pageTitle = isset($title) && is_string($title) && $title !== ''
    ? $title . ' | ' . $app->name
    : $app->name;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <title><?= e($pageTitle) ?></title>
</head>
<body>
    <header class="site-header">
        <nav class="site-nav">
            <a href="<?= e(route('pages.home')) ?>">Home</a>
            <a href="<?= e(route('pages.about')) ?>">About</a>
        </nav>
    </header>
