<?php
require_once __DIR__ . '/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/main.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/sidebar.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/tables.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/forms.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <?php if (isset($extraCSS)): ?>
        <?php foreach ($extraCSS as $css): ?>
            <link rel="stylesheet" href="<?php echo APP_URL . $css; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <div class="app-container">
