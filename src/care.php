<?php
require __DIR__ . '/config.php';
require_login();

$pdo = db();
$treeId = trim((string) ($_GET['ma_cay'] ?? $_POST['ma_cay'] ?? ''));
$statement = $pdo->prepare('SELECT c.ma_cay, c.tinh_trang, lc.ten_loai_cay, kv.ten_khu FROM cay_xanh c JOIN loai_cay lc ON lc.ma_loai_cay=c.ma_loai_cay JOIN khu_vuc kv ON kv.ma_khu=c.ma_khu WHERE c.ma_cay=?');
$statement->execute([$treeId]);
$tree = $statement->fetch();
if (!$tree) {
    http_response_code(404);
    exit('Không tìm thấy cây.');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $date = (string) ($_POST['ngay_thuc_hien'] ?? '');
    $type = (string) ($_POST['hinh_thuc'] ?? '');
    if (!in_array($type, ['Tưới nước', 'Bón phân', 'Cắt tỉa', 'Xử lý sâu bệnh'], true)) {
        $error = 'Hình thức chăm sóc không hợp lệ.';
    } elseif (!$date || $date > date('Y-m-d')) {
        $error = 'Ngày thực hiện không hợp lệ.';
    } else {
        $next = (int) $pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(ma_lich, 3) AS UNSIGNED)), 0) + 1 FROM lich_su_cham_soc WHERE ma_lich LIKE 'LS%'")->fetchColumn();
        if ($next > 99999999) {
            $error = 'Đã đạt giới hạn mã lịch chăm sóc.';
        } else {
            $newId = 'LS' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
            $insert = $pdo->prepare('INSERT INTO lich_su_cham_soc (ma_lich, ma_cay, ngay_thuc_hien, hinh_thuc, ma_nd_thuc_hien, ghi_chu) VALUES (?, ?, ?, ?, ?, ?)');
            $insert->execute([$newId, $treeId, $date, $type, $_SESSION['user']['ma_nd'], trim((string) ($_POST['ghi_chu'] ?? '')) ?: null]);
            redirect('care.php?ma_cay=' . rawurlencode($treeId));
        }
    }
}

$history = $pdo->prepare('SELECT ls.*, nd.ho_ten FROM lich_su_cham_soc ls LEFT JOIN nguoi_dung nd ON nd.ma_nd=ls.ma_nd_thuc_hien WHERE ls.ma_cay=? ORDER BY ls.ngay_thuc_hien DESC, ls.ma_lich DESC');
$history->execute([$treeId]);
$pageTitle = 'Lịch chăm sóc';
require __DIR__ . '/header.php';
?>
<section class="page-heading"><div><div class="eyebrow">HỒ SƠ CHĂM SÓC</div><h1><?= e($tree['ma_cay']) ?> · <?= e($tree['ten_loai_cay']) ?></h1><p class="muted"><?= e($tree['ten_khu']) ?> · Tình trạng: <?= e($tree['tinh_trang']) ?></p></div><a class="button button-light" href="trees.php">← Danh sách cây</a></section>
<section class="split-layout">
    <form class="form-panel care-form" method="post"><h2>Ghi nhận chăm sóc</h2><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="ma_cay" value="<?= e($treeId) ?>">
        <?php if ($error): ?><div class="alert alert-error form-full"><?= e($error) ?></div><?php endif; ?>
        <label>Ngày thực hiện<input type="date" name="ngay_thuc_hien" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" required></label>
        <label>Hình thức<select name="hinh_thuc" required><?php foreach (['Tưới nước', 'Bón phân', 'Cắt tỉa', 'Xử lý sâu bệnh'] as $type): ?><option><?= e($type) ?></option><?php endforeach; ?></select></label>
        <label class="form-full">Ghi chú<textarea name="ghi_chu" rows="3" maxlength="200" placeholder="Mô tả ngắn công việc đã thực hiện"></textarea></label>
        <div class="form-actions form-full"><button class="button button-primary" type="submit">Lưu lịch sử</button></div>
    </form>
    <section class="history-list"><h2>Lịch sử đã ghi nhận</h2><?php if (!$history->rowCount()): ?><p class="empty">Cây chưa có lịch sử chăm sóc.</p><?php endif; ?><?php foreach ($history as $item): ?><article class="history-item"><div class="history-date"><?= e($item['ngay_thuc_hien']) ?></div><div><strong><?= e($item['hinh_thuc']) ?></strong><p><?= e($item['ghi_chu'] ?: 'Không có ghi chú') ?></p><small><?= e($item['ho_ten'] ?? 'Người dùng không còn trong hệ thống') ?></small></div></article><?php endforeach; ?></section>
</section>
<?php require __DIR__ . '/footer.php'; ?>