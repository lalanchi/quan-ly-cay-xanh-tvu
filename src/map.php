<?php
require __DIR__ . '/config.php';
require_login();
$trees = db()->query("SELECT c.ma_cay, c.toa_do_gps, c.tinh_trang, lc.ten_loai_cay, kv.ten_khu, CASE WHEN c.ghi_chu LIKE '%Tọa độ ước lượng, chưa đo GPS thực địa.%' THEN 'Ước lượng - chưa đo GPS thực địa' ELSE 'Chưa xác minh thực địa' END AS do_tin_cay FROM cay_xanh c JOIN loai_cay lc ON lc.ma_loai_cay=c.ma_loai_cay JOIN khu_vuc kv ON kv.ma_khu=c.ma_khu WHERE c.toa_do_gps IS NOT NULL AND c.toa_do_gps <> '' ORDER BY c.ma_cay")->fetchAll();
$universityCenter = ['lat' => 9.9347, 'lng' => 106.3451];
$validTrees = array_values(array_filter($trees, static function (array $tree): bool {
    $coordinates = explode(',', (string) $tree['toa_do_gps']);
    if (count($coordinates) !== 2 || !is_numeric(trim($coordinates[0])) || !is_numeric(trim($coordinates[1]))) {
        return false;
    }

    $latitude = (float) trim($coordinates[0]);
    $longitude = (float) trim($coordinates[1]);
    return abs($latitude) <= 90 && abs($longitude) <= 180;
}));
$totalTrees = (int) db()->query('SELECT COUNT(*) FROM cay_xanh')->fetchColumn();
$treeMapCenter = $validTrees
    ? (static function (array $tree): array {
        [$latitude, $longitude] = array_map('trim', explode(',', (string) $tree['toa_do_gps']));
        return ['lat' => (float) $latitude, 'lng' => (float) $longitude];
    })($validTrees[0])
    : $universityCenter;
$pageTitle = 'Bản đồ GPS';
require __DIR__ . '/header.php';
?>
<section class="page-heading"><div><div class="eyebrow">VỊ TRÍ TRONG KHUÔN VIÊN</div><h1>Bản đồ cây xanh</h1><p class="muted">Tất cả cây có tọa độ được đánh dấu; chọn cây trong danh sách để xem vị trí.</p></div><span class="count-label"><?= count($validTrees) ?> / <?= $totalTrees ?> cây có tọa độ hợp lệ</span></section>
<div class="alert alert-error map-config-alert">Lưu ý: <?= count(array_filter($validTrees, static fn(array $tree): bool => str_starts_with($tree['do_tin_cay'], 'Ước lượng'))) ?> vị trí là ước lượng, chưa đo GPS thực địa; các vị trí còn lại cũng chưa được xác minh. Hãy đo tại chỗ trước khi dùng làm dữ liệu chính thức.</div>
<div class="map-layout">
    <div id="tree-map" class="map-canvas" aria-label="Bản đồ OpenStreetMap vị trí cây xanh">
    </div>
    <aside class="map-sidebar">
        <h2>Danh sách vị trí</h2>
        <label class="map-search">Tìm cây
            <input id="map-tree-search" type="search" placeholder="Mã cây, loài, khu vực..." autocomplete="off" aria-controls="map-tree-list">
        </label>
        <p id="map-tree-count" class="map-tree-count" aria-live="polite"></p>
        <p id="map-tree-empty" class="muted" hidden><?= $validTrees ? 'Không tìm thấy cây phù hợp.' : 'Chưa có cây được gán tọa độ hợp lệ.' ?></p>
        <div id="map-tree-list">
        <?php foreach ($validTrees as $tree): ?>
            <button class="map-tree" type="button" data-tree-id="<?= e($tree['ma_cay']) ?>" data-search="<?= e(implode(' ', [$tree['ma_cay'], $tree['ten_loai_cay'], $tree['ten_khu'], $tree['tinh_trang'], $tree['do_tin_cay']])) ?>">
                <strong><?= e($tree['ma_cay']) ?></strong>
                <span><?= e($tree['ten_loai_cay']) ?></span>
                <span class="map-tree-coordinates"><?= e($tree['toa_do_gps']) ?></span>
                <small><?= e($tree['ten_khu']) ?> · <?= e($tree['tinh_trang']) ?> · <?= e($tree['do_tin_cay']) ?></small>
            </button>
        <?php endforeach; ?>
        </div>
    </aside>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
const treePoints = <?= json_encode($validTrees, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const validTreePoints = treePoints.flatMap((tree) => {
    const values = tree.toa_do_gps.split(',').map((part) => Number(part.trim()));
    if (values.length !== 2 || values.some((value) => !Number.isFinite(value)) || Math.abs(values[0]) > 90 || Math.abs(values[1]) > 180) return [];
    return [{ tree, position: [values[0], values[1]] }];
});
const mapMarkers = new Map();
const treeMap = L.map('tree-map').setView(
    [<?= json_encode($treeMapCenter['lat'], JSON_THROW_ON_ERROR) ?>, <?= json_encode($treeMapCenter['lng'], JSON_THROW_ON_ERROR) ?>],
    17
);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
}).addTo(treeMap);

function createTreePopup(tree) {
    const popup = document.createElement('div');
    [tree.ma_cay, tree.ten_loai_cay, tree.ten_khu, tree.tinh_trang, tree.toa_do_gps, tree.do_tin_cay].forEach((value, index) => {
        const line = index === 0 ? document.createElement('strong') : document.createElement('div');
        line.textContent = value;
        popup.append(line);
    });
    return popup;
}

validTreePoints.forEach(({ tree, position }) => {
    const marker = L.circleMarker(position, {
        radius: 7,
        color: '#ffffff',
        weight: 2,
        fillColor: tree.tinh_trang === 'Bị sâu bệnh' ? '#b94a48' : (tree.tinh_trang === 'Cần chăm sóc' ? '#d3ad1e' : '#43825d'),
        fillOpacity: 0.9
    }).addTo(treeMap);
    marker.bindPopup(createTreePopup(tree));
    mapMarkers.set(tree.ma_cay, marker);
});

const mapTreeSearch = document.getElementById('map-tree-search');
const mapTreeButtons = [...document.querySelectorAll('.map-tree')];
const mapTreeCount = document.getElementById('map-tree-count');
const mapTreeEmpty = document.getElementById('map-tree-empty');
const normalizeSearchText = (value) => value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/gi, 'd')
    .toLocaleLowerCase('vi')
    .trim();

function filterMapTrees() {
    const query = normalizeSearchText(mapTreeSearch.value);
    let visibleCount = 0;

    mapTreeButtons.forEach((button) => {
        const matches = normalizeSearchText(button.dataset.search).includes(query);
        const marker = mapMarkers.get(button.dataset.treeId);
        button.hidden = !matches;
        if (!matches) button.classList.remove('is-selected');

        if (marker) {
            if (matches && !treeMap.hasLayer(marker)) marker.addTo(treeMap);
            if (!matches && treeMap.hasLayer(marker)) treeMap.removeLayer(marker);
        }

        if (matches) visibleCount += 1;
    });

    mapTreeCount.textContent = `Đang hiển thị ${visibleCount} / ${mapTreeButtons.length} cây`;
    mapTreeEmpty.hidden = visibleCount > 0;
}

mapTreeSearch.addEventListener('input', filterMapTrees);
filterMapTrees();

if (validTreePoints.length > 1) {
    treeMap.fitBounds(L.latLngBounds(validTreePoints.map(({ position }) => position)), { padding: [40, 40], maxZoom: 17 });
} else if (validTreePoints.length === 1) {
    treeMap.setView(validTreePoints[0].position, 18);
}

document.querySelectorAll('.map-tree').forEach((button) => button.addEventListener('click', () => {
    const marker = mapMarkers.get(button.dataset.treeId);
    if (!marker) return;
    treeMap.setView(marker.getLatLng(), 19);
    marker.openPopup();
    mapTreeButtons.forEach((item) => item.classList.toggle('is-selected', item === button));
}));
</script>
<?php require __DIR__ . '/footer.php'; ?>