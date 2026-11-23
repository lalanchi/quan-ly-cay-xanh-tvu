<?php
require __DIR__ . '/config.php';
require_login();

$pdo = db();
$totalTrees = (int) $pdo->query('SELECT COUNT(*) FROM cay_xanh')->fetchColumn();
$needsCare = (int) $pdo->query("SELECT COUNT(*) FROM cay_xanh WHERE tinh_trang = 'Cần chăm sóc'")->fetchColumn();
$diseased = (int) $pdo->query("SELECT COUNT(*) FROM cay_xanh WHERE tinh_trang = 'Bị sâu bệnh'")->fetchColumn();
$areaCount = (int) $pdo->query('SELECT COUNT(*) FROM khu_vuc')->fetchColumn();
$hasTreeData = $totalTrees > 0;
$recent = $pdo->query('SELECT ls.ngay_thuc_hien, ls.hinh_thuc, c.ma_cay, lc.ten_loai_cay, nd.ho_ten FROM lich_su_cham_soc ls JOIN cay_xanh c ON c.ma_cay = ls.ma_cay JOIN loai_cay lc ON lc.ma_loai_cay = c.ma_loai_cay LEFT JOIN nguoi_dung nd ON nd.ma_nd = ls.ma_nd_thuc_hien ORDER BY ls.ngay_thuc_hien DESC, ls.ma_lich DESC LIMIT 5')->fetchAll();
$pageTitle = 'Tổng quan';
require __DIR__ . '/header.php';
?>
<section class="dashboard-banner">
    <div>
        <div class="eyebrow">TRƯỜNG ĐẠI HỌC TRÀ VINH <span aria-hidden="true">/</span> HỆ THỐNG CÂY XANH</div>
        <h1>Kính chào <?= e($_SESSION['user']['ho_ten'] ?? 'bạn') ?></h1>
        <p>Theo dõi hiện trạng, khu vực và công tác chăm sóc cây trong khuôn viên trường.</p>
    </div>
    <div class="dashboard-actions">
        <a class="button button-primary" href="trees.php">Tra cứu hồ sơ cây <span aria-hidden="true">→</span></a>
        <?php if ($_SESSION['user']['quyen'] === 'Quản trị viên'): ?>
            <a class="button button-light" href="tree_form.php">+ Thêm cây mới</a>
        <?php endif; ?>
    </div>
</section>
<section class="metric-grid">
    <article class="metric metric-green"><span>Tổng số cây</span><strong><?= $hasTreeData ? number_format($totalTrees) : '—' ?></strong><small><?= $hasTreeData ? 'Hồ sơ đang được quản lý' : 'Chưa có hồ sơ cây xanh' ?></small></article>
    <article class="metric metric-yellow"><span>Cần chăm sóc</span><strong><?= $needsCare > 0 ? number_format($needsCare) : '—' ?></strong><small><?= $needsCare > 0 ? 'Cây cần được kiểm tra' : 'Chưa ghi nhận trường hợp' ?></small></article>
    <article class="metric metric-red"><span>Bị sâu bệnh</span><strong><?= $diseased > 0 ? number_format($diseased) : '—' ?></strong><small><?= $diseased > 0 ? 'Cây cần được xử lý' : 'Chưa ghi nhận trường hợp' ?></small></article>
    <article class="metric metric-blue"><span>Khu vực quản lý</span><strong><?= $areaCount > 0 ? number_format($areaCount) : '—' ?></strong><small><?= $areaCount > 0 ? 'Khu vực trong hệ thống' : 'Chưa có dữ liệu khu vực' ?></small></article>
</section>
<section class="content-section">
    <div class="section-heading"><div><div class="eyebrow">DỊCH VỤ</div><h2>Truy cập nhanh</h2><p class="muted">Các chức năng chính của hệ thống</p></div></div>
    <div class="quick-grid">
        <a class="quick-link" href="trees.php"><span class="quick-symbol" aria-hidden="true">♧</span><span><strong>Danh mục cây xanh</strong><small>Tra cứu hồ sơ, tình trạng và lịch sử chăm sóc</small></span><b aria-hidden="true">→</b></a>
        <?php if ($_SESSION['user']['quyen'] === 'Quản trị viên'): ?>
            <a class="quick-link" href="tree_form.php"><span class="quick-symbol" aria-hidden="true">＋</span><span><strong>Thêm cây mới</strong><small>Ghi nhận hồ sơ cây mới vào hệ thống</small></span><b aria-hidden="true">→</b></a>
        <?php endif; ?>
        <a class="quick-link" href="map.php"><span class="quick-symbol" aria-hidden="true">⌖</span><span><strong>Bản đồ khuôn viên</strong><small>Xem vị trí cây theo dữ liệu tọa độ GPS</small></span><b aria-hidden="true">→</b></a>
        <a class="quick-link" href="stats.php"><span class="quick-symbol" aria-hidden="true">▥</span><span><strong>Báo cáo thống kê</strong><small>Tổng hợp cây theo tình trạng và khu vực</small></span><b aria-hidden="true">→</b></a>
    </div>
</section>
<section class="content-section">
    <div class="section-heading"><div><div class="eyebrow">CẬP NHẬT HOẠT ĐỘNG</div><h2>Lịch chăm sóc gần đây</h2><p class="muted">Các hoạt động mới nhất được ghi nhận</p></div><a class="text-link" href="trees.php">Xem danh mục cây <span aria-hidden="true">→</span></a></div>
    <div class="table-wrap"><table><thead><tr><th>Ngày</th><th>Mã cây</th><th>Loại cây</th><th>Hoạt động</th><th>Người thực hiện</th></tr></thead><tbody>
    <?php if (!$recent): ?><tr><td colspan="5" class="empty">Chưa có hoạt động chăm sóc.</td></tr><?php endif; ?>
    <?php foreach ($recent as $item): ?><tr><td><?= e($item['ngay_thuc_hien']) ?></td><td><strong><?= e($item['ma_cay']) ?></strong></td><td><?= e($item['ten_loai_cay']) ?></td><td><?= e($item['hinh_thuc']) ?></td><td><?= e($item['ho_ten'] ?? 'Chưa xác định') ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<?php require __DIR__ . '/footer.php'; ?>