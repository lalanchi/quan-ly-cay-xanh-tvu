<?php
require __DIR__ . '/config.php';
require_login();

$search = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT c.*, lc.ten_loai_cay, kv.ten_khu, nd.ho_ten AS nguoi_phu_trach, (SELECT COUNT(*) FROM lich_su_cham_soc ls WHERE ls.ma_cay = c.ma_cay) AS so_lan_cham_soc FROM cay_xanh c JOIN loai_cay lc ON lc.ma_loai_cay = c.ma_loai_cay JOIN khu_vuc kv ON kv.ma_khu = c.ma_khu LEFT JOIN nguoi_dung nd ON nd.ma_nd = c.ma_nd_phu_trach';
$params = [];
if ($search !== '') {
    $sql .= ' WHERE c.ma_cay LIKE ? OR lc.ten_loai_cay LIKE ? OR kv.ten_khu LIKE ? OR c.tinh_trang LIKE ?';
    $term = '%' . $search . '%';
    $params = [$term, $term, $term, $term];
}
$sql .= ' ORDER BY c.ma_cay';
$statement = db()->prepare($sql);
$statement->execute($params);
$trees = $statement->fetchAll();
$pageTitle = 'Danh sách cây';
require __DIR__ . '/header.php';
?>
<section class="page-heading"><div><div class="eyebrow">HỒ SƠ CÂY XANH</div><h1>Danh sách cây</h1><p class="muted">Tra cứu cây, tình trạng và lịch sử chăm sóc.</p></div><?php if ($_SESSION['user']['quyen'] === 'Quản trị viên'): ?><a class="button button-primary" href="tree_form.php">+ Thêm cây</a><?php endif; ?></section>
<form class="filter-bar" method="get"><label class="search-field">Tìm theo mã cây, loại cây, khu vực hoặc tình trạng<input name="q" value="<?= e($search) ?>" placeholder="Ví dụ: C00001, Phượng, Khu Hiệu bộ"></label><button class="button button-dark" type="submit">Tìm kiếm</button><?php if ($search !== ''): ?><a class="button button-light" href="trees.php">Xóa lọc</a><?php endif; ?></form>
<div class="table-wrap"><table><thead><tr><th>Mã cây / Loài</th><th>Khu vực</th><th>Ngày trồng</th><th>Tình trạng</th><th>Phụ trách</th><th>Chăm sóc</th><th>Thao tác</th></tr></thead><tbody>
<?php if (!$trees): ?><tr><td colspan="7" class="empty">Không tìm thấy cây phù hợp.</td></tr><?php endif; ?>
<?php foreach ($trees as $tree): ?><tr>
    <td><strong><?= e($tree['ma_cay']) ?></strong><small class="table-sub"> <?= e($tree['ten_loai_cay']) ?></small></td><td><?= e($tree['ten_khu']) ?></td><td><?= e($tree['ngay_trong'] ?: '—') ?></td>
    <td><span class="status status-<?= $tree['tinh_trang'] === 'Tốt' ? 'good' : ($tree['tinh_trang'] === 'Cần chăm sóc' ? 'care' : 'ill') ?>"><?= e($tree['tinh_trang']) ?></span></td><td><?= e($tree['nguoi_phu_trach'] ?? 'Chưa phân công') ?></td>
    <td><a class="text-link" href="care.php?ma_cay=<?= rawurlencode($tree['ma_cay']) ?>"><?= (int) $tree['so_lan_cham_soc'] ?> lần · Xem</a></td><td class="actions">
    <?php if ($_SESSION['user']['quyen'] === 'Quản trị viên'): ?><a class="text-link" href="tree_form.php?ma_cay=<?= rawurlencode($tree['ma_cay']) ?>">Sửa</a><form method="post" action="tree_delete.php" onsubmit="return confirm('Xóa cây này và toàn bộ lịch sử chăm sóc?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="ma_cay" value="<?= e($tree['ma_cay']) ?>"><button class="link-danger" type="submit">Xóa</button></form><?php else: ?><span class="muted">Nhân viên</span><?php endif; ?>
    </td>
</tr><?php endforeach; ?>
</tbody></table></div>
<?php require __DIR__ . '/footer.php'; ?>