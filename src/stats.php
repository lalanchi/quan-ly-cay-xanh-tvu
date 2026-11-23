<?php
require __DIR__ . '/config.php';
require_login();

$pdo = db();
$totalTrees = (int) $pdo->query('SELECT COUNT(*) FROM cay_xanh')->fetchColumn();
$statuses = $pdo->query('SELECT tinh_trang, COUNT(*) AS so_cay FROM cay_xanh GROUP BY tinh_trang')->fetchAll();
$statusCounts = [
    'Tốt' => 0,
    'Cần chăm sóc' => 0,
    'Bị sâu bệnh' => 0,
];
foreach ($statuses as $row) {
    if (isset($statusCounts[$row['tinh_trang']])) {
        $statusCounts[$row['tinh_trang']] = (int) $row['so_cay'];
    }
}

$areaRows = $pdo->query(
    'SELECT kv.ma_khu, kv.ten_khu, c.tinh_trang, COUNT(c.ma_cay) AS so_cay
     FROM khu_vuc kv
     LEFT JOIN cay_xanh c ON c.ma_khu = kv.ma_khu
     GROUP BY kv.ma_khu, kv.ten_khu, c.tinh_trang
     ORDER BY kv.ten_khu'
)->fetchAll();
$areasById = [];
foreach ($areaRows as $row) {
    $areaId = $row['ma_khu'];
    if (!isset($areasById[$areaId])) {
        $areasById[$areaId] = [
            'ten_khu' => $row['ten_khu'],
            'so_cay' => 0,
            'tot' => 0,
            'can_cham_soc' => 0,
            'sau_benh' => 0,
        ];
    }
    $count = (int) $row['so_cay'];
    $areasById[$areaId]['so_cay'] += $count;
    if ($row['tinh_trang'] === 'Tốt') {
        $areasById[$areaId]['tot'] += $count;
    } elseif ($row['tinh_trang'] === 'Cần chăm sóc') {
        $areasById[$areaId]['can_cham_soc'] += $count;
    } elseif ($row['tinh_trang'] === 'Bị sâu bệnh') {
        $areasById[$areaId]['sau_benh'] += $count;
    }
}
$areas = array_values($areasById);
$maxArea = max(1, ...array_map(static fn(array $area): int => $area['so_cay'], $areas));

$careTypes = $pdo->query(
    'SELECT hinh_thuc, COUNT(*) AS so_lan
     FROM lich_su_cham_soc
     GROUP BY hinh_thuc
     ORDER BY hinh_thuc'
)->fetchAll();
$maxCareType = max(1, ...array_map(static fn(array $row): int => (int) $row['so_lan'], $careTypes));
$totalCareRecords = array_sum(array_map(static fn(array $row): int => (int) $row['so_lan'], $careTypes));

$treeRows = $pdo->query(
    'SELECT c.ma_cay, c.tinh_trang, c.ghi_chu, lc.ten_loai_cay, kv.ten_khu,
            COUNT(ls.ma_lich) AS so_lan_cham_soc, MAX(ls.ngay_thuc_hien) AS cham_soc_cuoi
     FROM cay_xanh c
     JOIN loai_cay lc ON lc.ma_loai_cay = c.ma_loai_cay
     JOIN khu_vuc kv ON kv.ma_khu = c.ma_khu
     LEFT JOIN lich_su_cham_soc ls ON ls.ma_cay = c.ma_cay
     GROUP BY c.ma_cay, c.tinh_trang, c.ghi_chu, lc.ten_loai_cay, kv.ten_khu
     ORDER BY c.ma_cay'
)->fetchAll();
$attentionTrees = array_values(array_filter(
    $treeRows,
    static fn(array $tree): bool => in_array($tree['tinh_trang'], ['Cần chăm sóc', 'Bị sâu bệnh'], true)
));
$diseasedCount = $statusCounts['Bị sâu bệnh'];
$careNeededCount = $statusCounts['Cần chăm sóc'];
$healthyTargetPercent = 60;
$pageTitle = 'Thống kê';
require __DIR__ . '/header.php';
?>
<section class="page-heading stats-page-heading">
    <div>
        <div class="eyebrow">BÁO CÁO TỔNG HỢP</div>
        <h1>Thống kê cây xanh</h1>
        <p class="muted">Tình trạng cây và hoạt động chăm sóc trên toàn khuôn viên.</p>
    </div>
    <a class="button button-light" href="trees.php">Xem danh sách cây <span aria-hidden="true">→</span></a>
</section>

<?php if ($totalTrees === 0): ?>
<section class="empty-state">
    <span class="empty-state-mark" aria-hidden="true">♧</span>
    <h2>Chưa có dữ liệu thống kê</h2>
    <p>Biểu đồ và báo cáo sẽ hiển thị khi hồ sơ cây xanh được thêm vào hệ thống.</p>
    <a class="button button-primary" href="trees.php">Mở danh mục cây</a>
</section>
<?php else: ?>
<section class="stats-metric-grid" aria-label="Tổng quan tình trạng cây">
    <article class="stats-metric stats-metric-total">
        <span class="stats-metric-icon" aria-hidden="true">♧</span>
        <span class="stats-metric-label">Tổng số cây</span>
        <strong><?= number_format($totalTrees) ?></strong>
        <small>Trong toàn bộ khuôn viên</small>
    </article>
    <article class="stats-metric stats-metric-good">
        <span class="stats-metric-icon" aria-hidden="true">✓</span>
        <span class="stats-metric-label">Cây khỏe mạnh</span>
        <strong><?= number_format($statusCounts['Tốt']) ?></strong>
        <small>Thực tế: <?= $totalTrees > 0 ? number_format($statusCounts['Tốt'] / $totalTrees * 100, 1) : '0.0' ?>%</small>
        <small class="stats-metric-target">Mục tiêu: <?= number_format($healthyTargetPercent) ?>%</small>
    </article>
    <article class="stats-metric stats-metric-care">
        <span class="stats-metric-icon" aria-hidden="true">✳</span>
        <span class="stats-metric-label">Cần chăm sóc</span>
        <strong><?= number_format($careNeededCount) ?></strong>
        <small>Cây cần được theo dõi</small>
    </article>
    <article class="stats-metric stats-metric-ill">
        <span class="stats-metric-icon" aria-hidden="true">!</span>
        <span class="stats-metric-label">Bị sâu bệnh</span>
        <strong><?= number_format($diseasedCount) ?></strong>
        <small>Cây cần xử lý tình trạng bệnh</small>
    </article>
</section>

<section class="stats-panel">
    <div class="stats-panel-heading">
        <div>
            <div class="eyebrow">SỨC KHỎE CÂY XANH</div>
            <h2>Phân loại theo tình trạng</h2>
        </div>
        <span class="stats-total-pill"><?= number_format($totalTrees) ?> cây</span>
    </div>
    <div class="status-chart">
        <?php foreach ([
            'Tốt' => ['tone' => 'good', 'count' => $statusCounts['Tốt']],
            'Cần chăm sóc' => ['tone' => 'care', 'count' => $careNeededCount],
            'Bị sâu bệnh' => ['tone' => 'ill', 'count' => $diseasedCount],
        ] as $name => $status): ?>
        <?php $share = $totalTrees > 0 ? $status['count'] / $totalTrees * 100 : 0; ?>
        <div class="status-chart-row">
            <div class="status-chart-label">
                <span class="status-dot status-dot-<?= e($status['tone']) ?>" aria-hidden="true"></span>
                <span><?= e($name) ?></span>
            </div>
            <div class="status-chart-track" role="progressbar" aria-label="<?= e($name) ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) round($share) ?>">
                <span class="status-chart-fill status-chart-fill-<?= e($status['tone']) ?>" style="width:<?= $status['count'] > 0 ? max(1, $share) : 0 ?>%"></span>
            </div>
            <strong><?= number_format($status['count']) ?></strong>
            <small><?= number_format($share, 1) ?>%</small>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="stats-panel">
    <div class="stats-panel-heading">
        <div>
            <div class="eyebrow">HOẠT ĐỘNG CHĂM SÓC</div>
            <h2>Lịch chăm sóc theo hình thức</h2>
            <p class="muted">Tổng cộng <?= number_format($totalCareRecords) ?> lượt đã ghi nhận</p>
        </div>
    </div>
    <?php if ($careTypes): ?>
    <div class="care-type-grid">
        <?php foreach ($careTypes as $careType): ?>
        <?php $count = (int) $careType['so_lan']; $share = $totalCareRecords > 0 ? $count / $totalCareRecords * 100 : 0; ?>
        <article class="care-type-card">
            <div class="care-type-card-heading">
                <span class="care-type-icon" aria-hidden="true"><?= $careType['hinh_thuc'] === 'Xử lý sâu bệnh' ? '✳' : ($careType['hinh_thuc'] === 'Tưới nước' ? '≈' : ($careType['hinh_thuc'] === 'Bón phân' ? '+' : '⌁')) ?></span>
                <span><?= e($careType['hinh_thuc'] ?? 'Chưa phân loại') ?></span>
            </div>
            <strong><?= number_format($count) ?></strong>
            <small>lượt chăm sóc · <?= number_format($share, 1) ?>% tổng số</small>
            <span class="care-type-track" aria-hidden="true"><span style="width:<?= $count > 0 ? max(1, $count / $maxCareType * 100) : 0 ?>%"></span></span>
        </article>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="empty">Chưa có lịch sử chăm sóc.</p>
    <?php endif; ?>
</section>

<section class="stats-panel">
    <div class="stats-panel-heading">
        <div>
            <div class="eyebrow">PHÂN BỐ CÂY XANH</div>
            <h2>Tình trạng theo khu vực</h2>
            <p class="muted">So sánh số lượng cây và tình trạng tại từng khu vực.</p>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Khu vực</th><th>Tổng cây</th><th>Tốt</th><th>Cần chăm sóc</th><th>Bị sâu bệnh</th><th>Phân bố</th></tr></thead>
            <tbody>
            <?php foreach ($areas as $area): ?>
            <tr>
                <td><strong><?= e($area['ten_khu']) ?></strong></td>
                <td><?= number_format($area['so_cay']) ?></td>
                <td><?= $area['tot'] > 0 ? number_format($area['tot']) : '—' ?></td>
                <td><?= $area['can_cham_soc'] > 0 ? number_format($area['can_cham_soc']) : '—' ?></td>
                <td><?= $area['sau_benh'] > 0 ? number_format($area['sau_benh']) : '—' ?></td>
                <td class="area-bar-cell"><span class="bar-track"><span class="bar-fill bar-blue" style="width:<?= $area['so_cay'] > 0 ? max(2, round($area['so_cay'] / $maxArea * 100)) : 0 ?>%"></span></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="stats-panel attention-panel">
    <div class="stats-panel-heading">
        <div>
            <div class="eyebrow">ƯU TIÊN THEO DÕI</div>
            <h2>Cây cần chăm sóc và cây bị sâu bệnh</h2>
            <p class="muted">Danh sách lấy trực tiếp từ tình trạng hiện tại của hồ sơ cây.</p>
        </div>
        <span class="attention-count"><?= number_format(count($attentionTrees)) ?> cây cần chú ý</span>
    </div>
    <?php if ($attentionTrees): ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Mã cây</th><th>Loại cây</th><th>Khu vực</th><th>Tình trạng</th><th>Lịch chăm sóc</th><th>Lần gần nhất</th><th>Ghi chú</th></tr></thead>
            <tbody>
            <?php foreach ($attentionTrees as $tree): ?>
            <?php $isDiseased = $tree['tinh_trang'] === 'Bị sâu bệnh'; ?>
            <tr>
                <td><a class="text-link" href="care.php?ma_cay=<?= rawurlencode($tree['ma_cay']) ?>"><?= e($tree['ma_cay']) ?></a></td>
                <td><?= e($tree['ten_loai_cay']) ?></td>
                <td><?= e($tree['ten_khu']) ?></td>
                <td><span class="status <?= $isDiseased ? 'status-ill' : 'status-care' ?>"><?= e($tree['tinh_trang']) ?></span></td>
                <td><?= number_format((int) $tree['so_lan_cham_soc']) ?></td>
                <td><?= e($tree['cham_soc_cuoi'] ?? 'Chưa có') ?></td>
                <td><?= e($tree['ghi_chu'] ?: 'Chưa có ghi chú') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="stats-all-good"><span aria-hidden="true">✓</span><strong>Không có cây cần chú ý</strong><small>Tất cả cây hiện đang ở trạng thái tốt.</small></div>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php require __DIR__ . '/footer.php'; ?>
