<?php
$pageTitle = $pageTitle ?? 'Quản lý cây xanh';
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | Cây xanh TVU</title>
    <link rel="stylesheet" href="style.css?v=11">
    <?php if (in_array($pageTitle, ['Bản đồ GPS', 'Thêm cây', 'Cập nhật cây'], true)): ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <?php endif; ?>
</head>
<body>
<header class="site-header">
    <div class="utility-bar">
        <div class="utility-inner">
            <div class="utility-links"><a href="login.php">Cổng thông tin nội bộ</a><a href="login.php#portal-intro">Hệ thống cây xanh</a><a href="login.php#support">Hỗ trợ</a></div>
            <span>TRƯỜNG ĐẠI HỌC TRÀ VINH</span>
        </div>
    </div>
    <div class="topbar">
        <a class="brand" href="index.php" aria-label="Cổng thông tin quản lý cây xanh TVU">
            <span class="brand-mark">TVU</span>
            <span class="brand-copy"><strong>QUẢN LÝ CÂY XANH</strong><small>TRƯỜNG ĐẠI HỌC TRÀ VINH</small></span>
        </a>
        <span class="brand-note"><strong>KHÔNG GIAN XANH</strong><small>Chung tay chăm sóc khuôn viên trường</small></span>
        <?php if (!empty($_SESSION['user'])): ?>
        <div class="account">
            <span class="account-avatar" aria-hidden="true">T</span>
            <span class="account-details"><?= e($_SESSION['user']['ho_ten'] ?? 'Người dùng') ?><small><?= e($_SESSION['user']['quyen'] ?? '') ?></small></span>
            <a class="button button-light" href="logout.php">Đăng xuất</a>
        </div>
        <?php endif; ?>
    </div>
    <?php if (!empty($_SESSION['user'])): ?>
        <nav class="primary-nav" aria-label="Điều hướng chính">
            <div class="nav-inner">
                <div class="nav-links">
                    <a href="index.php" <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'aria-current="page"' : '' ?>>Tổng quan</a>
                    <a href="trees.php" <?= basename($_SERVER['PHP_SELF']) === 'trees.php' ? 'aria-current="page"' : '' ?>>Danh sách cây</a>
                    <a href="map.php" <?= basename($_SERVER['PHP_SELF']) === 'map.php' ? 'aria-current="page"' : '' ?>>Bản đồ</a>
                    <a href="stats.php" <?= basename($_SERVER['PHP_SELF']) === 'stats.php' ? 'aria-current="page"' : '' ?>>Thống kê</a>
                </div>
            </div>
        </nav>
    <?php else: ?>
        <nav class="primary-nav" aria-label="Điều hướng chính">
            <div class="nav-inner">
                <div class="nav-links">
                    <a href="login.php" aria-current="page">Trang chủ</a>
                    <a href="login.php#portal-intro">Giới thiệu</a>
                    <a href="login.php#support">Hỗ trợ tài khoản</a>
                </div>
            </div>
        </nav>
    <?php endif; ?>
</header>
<main class="container">