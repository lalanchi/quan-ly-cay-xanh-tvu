<?php
require __DIR__ . '/config.php';
require_admin();

$pdo = db();
$id = trim((string) ($_GET['ma_cay'] ?? $_POST['ma_cay'] ?? ''));
$editing = $id !== '';
$tree = ['ma_cay' => '', 'ma_loai_cay' => '', 'ma_khu' => '', 'ngay_trong' => '', 'toa_do_gps' => '', 'tinh_trang' => 'Tốt', 'ma_nd_phu_trach' => '', 'ghi_chu' => ''];
if ($editing) {
    $statement = $pdo->prepare('SELECT * FROM cay_xanh WHERE ma_cay = ?');
    $statement->execute([$id]);
    $tree = $statement->fetch() ?: null;
    if (!$tree) {
        http_response_code(404);
        exit('Không tìm thấy cây.');
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $tree = [
        'ma_cay' => $editing ? $id : '',
        'ma_loai_cay' => trim((string) ($_POST['ma_loai_cay'] ?? '')),
        'ma_khu' => trim((string) ($_POST['ma_khu'] ?? '')),
        'ngay_trong' => trim((string) ($_POST['ngay_trong'] ?? '')),
        'toa_do_gps' => trim((string) ($_POST['toa_do_gps'] ?? '')),
        'tinh_trang' => trim((string) ($_POST['tinh_trang'] ?? '')),
        'ma_nd_phu_trach' => trim((string) ($_POST['ma_nd_phu_trach'] ?? '')),
        'ghi_chu' => trim((string) ($_POST['ghi_chu'] ?? '')),
    ];
    if (!in_array($tree['tinh_trang'], ['Tốt', 'Cần chăm sóc', 'Bị sâu bệnh'], true)) {
        $error = 'Tình trạng cây không hợp lệ.';
    } elseif ($tree['toa_do_gps'] !== '') {
        if (!preg_match('/\A(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\z/', $tree['toa_do_gps'], $coordinates)) {
            $error = 'Tọa độ GPS cần có định dạng vĩ độ, kinh độ, ngăn cách bằng dấu phẩy.';
        } elseif ((float) $coordinates[1] < -90 || (float) $coordinates[1] > 90 || (float) $coordinates[2] < -180 || (float) $coordinates[2] > 180) {
            $error = 'Vĩ độ phải từ -90 đến 90 và kinh độ phải từ -180 đến 180.';
        }
    }

    if ($error === '') {
        try {
            if ($editing) {
                $statement = $pdo->prepare('UPDATE cay_xanh SET ma_loai_cay=?, ma_khu=?, ngay_trong=?, toa_do_gps=?, tinh_trang=?, ma_nd_phu_trach=?, ghi_chu=? WHERE ma_cay=?');
                $statement->execute([$tree['ma_loai_cay'], $tree['ma_khu'], $tree['ngay_trong'] ?: null, $tree['toa_do_gps'] ?: null, $tree['tinh_trang'], $tree['ma_nd_phu_trach'] ?: null, $tree['ghi_chu'] ?: null, $id]);
            } else {
                $next = (int) $pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(ma_cay, 2) AS UNSIGNED)), 0) + 1 FROM cay_xanh WHERE ma_cay LIKE 'C%'")->fetchColumn();
                if ($next > 99999) {
                    throw new RuntimeException('Đã đạt giới hạn mã cây.');
                }
                $newId = 'C' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
                $statement = $pdo->prepare('INSERT INTO cay_xanh (ma_cay, ma_loai_cay, ma_khu, ngay_trong, toa_do_gps, tinh_trang, ma_nd_phu_trach, ghi_chu) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $statement->execute([$newId, $tree['ma_loai_cay'], $tree['ma_khu'], $tree['ngay_trong'] ?: null, $tree['toa_do_gps'] ?: null, $tree['tinh_trang'], $tree['ma_nd_phu_trach'] ?: null, $tree['ghi_chu'] ?: null]);
            }
            redirect('trees.php');
        } catch (PDOException $exception) {
            $error = 'Không thể lưu cây. Hãy kiểm tra loại cây, khu vực và dữ liệu nhập.';
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }
    }
}

$species = $pdo->query('SELECT ma_loai_cay, ten_loai_cay FROM loai_cay ORDER BY ten_loai_cay')->fetchAll();
$areas = $pdo->query('SELECT ma_khu, ten_khu FROM khu_vuc ORDER BY ten_khu')->fetchAll();
$staff = $pdo->query('SELECT ma_nd, ho_ten FROM nguoi_dung ORDER BY ho_ten')->fetchAll();
$pageTitle = $editing ? 'Cập nhật cây' : 'Thêm cây';
require __DIR__ . '/header.php';
?>
<section class="page-heading"><div><div class="eyebrow">QUẢN LÝ HỒ SƠ</div><h1><?= $editing ? 'Cập nhật thông tin cây' : 'Thêm cây mới' ?></h1><p class="muted">Mã cây sẽ được hệ thống tạo tự động.</p></div><a class="button button-light" href="trees.php">← Danh sách</a></section>
<form class="form-panel" method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><?php if ($editing): ?><input type="hidden" name="ma_cay" value="<?= e($id) ?>"><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error form-full"><?= e($error) ?></div><?php endif; ?>
    <label>Loại cây<select name="ma_loai_cay" required><option value="">Chọn loại cây</option><?php foreach ($species as $item): ?><option value="<?= e($item['ma_loai_cay']) ?>" <?= $tree['ma_loai_cay'] === $item['ma_loai_cay'] ? 'selected' : '' ?>><?= e($item['ten_loai_cay']) ?></option><?php endforeach; ?></select></label>
    <label>Khu vực<select name="ma_khu" required><option value="">Chọn khu vực</option><?php foreach ($areas as $item): ?><option value="<?= e($item['ma_khu']) ?>" <?= $tree['ma_khu'] === $item['ma_khu'] ? 'selected' : '' ?>><?= e($item['ten_khu']) ?></option><?php endforeach; ?></select></label>
    <label>Ngày trồng<input type="date" name="ngay_trong" value="<?= e($tree['ngay_trong']) ?>"></label>
    <label>Tình trạng<select name="tinh_trang" required><?php foreach (['Tốt', 'Cần chăm sóc', 'Bị sâu bệnh'] as $status): ?><option <?= $tree['tinh_trang'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></label>
    <div class="form-full gps-field">
        <label for="toa-do-gps">Tọa độ GPS <small>(vĩ độ, kinh độ)</small></label>
        <div class="gps-controls"><input id="toa-do-gps" name="toa_do_gps" value="<?= e($tree['toa_do_gps']) ?>" placeholder="Nhập vĩ độ,kinh độ" inputmode="decimal" autocomplete="off"><button class="button button-light" id="capture-location" type="button">Lấy vị trí hiện tại</button></div>
        <small class="gps-help" id="location-status" aria-live="polite">Đứng cạnh cây rồi lấy vị trí bằng thiết bị có GPS. Trình duyệt cần được cấp quyền vị trí.</small>
        <div id="tree-location-map" class="tree-location-map" aria-label="Bản đồ chọn vị trí cây"></div>
        <small class="gps-help">Chọn điểm trên bản đồ hoặc kéo ghim để cập nhật tọa độ. Chỉ lưu vị trí đã kiểm tra tại thực địa.</small>
        <a id="open-tree-location" class="gps-map-link" href="https://maps.google.com/" target="_blank" rel="noopener noreferrer">Mở tọa độ trên Google Maps</a>
    </div>
    <label>Người phụ trách<select name="ma_nd_phu_trach"><option value="">Chưa phân công</option><?php foreach ($staff as $person): ?><option value="<?= e($person['ma_nd']) ?>" <?= $tree['ma_nd_phu_trach'] === $person['ma_nd'] ? 'selected' : '' ?>><?= e($person['ho_ten']) ?></option><?php endforeach; ?></select></label>
    <label class="form-full">Ghi chú<textarea name="ghi_chu" rows="3" maxlength="200"><?= e($tree['ghi_chu']) ?></textarea></label>
    <div class="form-actions form-full"><button class="button button-primary" type="submit">Lưu thông tin</button><a class="button button-light" href="trees.php">Hủy</a></div>
</form>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
const locationButton = document.getElementById('capture-location');
const locationInput = document.getElementById('toa-do-gps');
const locationStatus = document.getElementById('location-status');
const openLocationLink = document.getElementById('open-tree-location');

function parseTreeCoordinates(value) {
    const match = value.trim().match(/^(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)$/);
    if (!match) return null;
    const lat = Number(match[1]);
    const lng = Number(match[2]);
    if (!Number.isFinite(lat) || !Number.isFinite(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180) return null;
    return { lat, lng };
}

function updateOpenMapLink() {
    if (!openLocationLink) return;
    const coordinates = parseTreeCoordinates(locationInput.value);
    openLocationLink.href = coordinates
        ? `https://maps.google.com/?q=${encodeURIComponent(`${coordinates.lat},${coordinates.lng}`)}`
        : 'https://maps.google.com/';
}

locationInput.addEventListener('input', updateOpenMapLink);
updateOpenMapLink();

const initialPosition = parseTreeCoordinates(locationInput.value);
const defaultCenter = [9.9347, 106.3451];
const treeLocationMap = L.map('tree-location-map').setView(
    initialPosition ? [initialPosition.lat, initialPosition.lng] : defaultCenter,
    initialPosition ? 18 : 15
);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
}).addTo(treeLocationMap);

let treeLocationMarker;

function setTreeLocation(position, message) {
    const coordinates = [Number(position.lat), Number(position.lng)];
    if (!treeLocationMarker) {
        treeLocationMarker = L.marker(coordinates, { draggable: true, title: 'Vị trí cây' }).addTo(treeLocationMap);
        treeLocationMarker.on('dragend', () => {
            const draggedPosition = treeLocationMarker.getLatLng();
            setTreeLocation(draggedPosition, 'Đã di chuyển ghim. Hãy xác nhận đúng vị trí cây trước khi lưu.');
        });
    } else {
        treeLocationMarker.setLatLng(coordinates);
    }
    treeLocationMap.setView(coordinates, Math.max(treeLocationMap.getZoom(), 18));
    locationInput.value = `${coordinates[0].toFixed(7)},${coordinates[1].toFixed(7)}`;
    locationStatus.textContent = message;
    updateOpenMapLink();
}

treeLocationMap.on('click', (event) => {
    setTreeLocation(event.latlng, 'Đã chọn vị trí trên bản đồ. Hãy xác nhận ghim đúng vị trí cây trước khi lưu.');
});

if (initialPosition) {
    treeLocationMarker = L.marker([initialPosition.lat, initialPosition.lng], {
        draggable: true,
        title: 'Vị trí cây'
    }).addTo(treeLocationMap);
    treeLocationMarker.on('dragend', () => {
        const draggedPosition = treeLocationMarker.getLatLng();
        setTreeLocation(draggedPosition, 'Đã di chuyển ghim. Hãy xác nhận đúng vị trí cây trước khi lưu.');
    });
    locationStatus.textContent = 'Đang hiển thị tọa độ đã lưu. Hãy xác minh vị trí tại thực địa.';
}

locationInput.addEventListener('change', () => {
    const coordinates = parseTreeCoordinates(locationInput.value);
    if (coordinates) setTreeLocation(coordinates, 'Đã cập nhật ghim theo tọa độ nhập.');
});

locationButton.addEventListener('click', () => {
    if (!navigator.geolocation) {
        locationStatus.textContent = 'Trình duyệt này không hỗ trợ lấy vị trí. Bạn có thể nhập tọa độ đo được.';
        return;
    }

    locationButton.disabled = true;
    locationStatus.textContent = 'Đang lấy vị trí thiết bị…';
    navigator.geolocation.getCurrentPosition(
        (position) => {
            const { latitude, longitude, accuracy } = position.coords;
            const coordinates = { lat: latitude, lng: longitude };
            setTreeLocation(coordinates, `Độ chính xác thiết bị ước tính ±${Math.round(accuracy)} m. Hãy kiểm tra ghim tại thực địa trước khi lưu.`);
            locationButton.disabled = false;
        },
        (error) => {
            const messages = {
                1: 'Bạn chưa cấp quyền vị trí. Hãy cho phép truy cập vị trí trong trình duyệt rồi thử lại.',
                2: 'Thiết bị chưa xác định được vị trí. Hãy bật GPS hoặc thử ở nơi thoáng hơn.',
                3: 'Lấy vị trí quá thời gian chờ. Hãy thử lại.'
            };
            locationStatus.textContent = messages[error.code] || 'Không thể lấy vị trí thiết bị. Hãy thử lại hoặc nhập tọa độ đo được.';
            locationButton.disabled = false;
        },
        { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 }
    );
});
</script>
<?php require __DIR__ . '/footer.php'; ?>