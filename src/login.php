<?php
require __DIR__ . '/config.php';

if (!empty($_SESSION['user'])) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string) ($_POST['ten_dang_nhap'] ?? ''));
    $password = (string) ($_POST['mat_khau'] ?? '');
    $statement = db()->prepare('SELECT ma_nd, ten_dang_nhap, mat_khau, ho_ten, quyen FROM nguoi_dung WHERE ten_dang_nhap = ? LIMIT 1');
    $statement->execute([$username]);
    $user = $statement->fetch();

    if ($user && (password_verify($password, $user['mat_khau']) || hash_equals((string) $user['mat_khau'], $password))) {
        if (!password_get_info($user['mat_khau'])['algo']) {
            $upgrade = db()->prepare('UPDATE nguoi_dung SET mat_khau = ? WHERE ma_nd = ?');
            $upgrade->execute([password_hash($password, PASSWORD_DEFAULT), $user['ma_nd']]);
        }
        session_regenerate_id(true);
        unset($user['mat_khau']);
        $_SESSION['user'] = $user;
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $_SESSION['_created_at'] = time();
        $_SESSION['_last_activity_at'] = time();
        redirect('index.php');
    }
    $error = 'Tên đăng nhập hoặc mật khẩu không chính xác.';
}

$pageTitle = 'Đăng nhập';
require __DIR__ . '/header.php';
?>
<section class="login-wrap">
    <section class="portal-hero" id="portal-intro">
        <div class="portal-hero-copy">
            <div class="portal-kicker"><span class="portal-kicker-mark">TVU</span> TRƯỜNG ĐẠI HỌC TRÀ VINH <span class="portal-divider">|</span> HỆ THỐNG NỘI BỘ</div>
            <h1>Chung tay vun đắp<br><span>không gian xanh</span></h1>
            <p>Nền tảng theo dõi, quản lý và chăm sóc cây xanh trong khuôn viên Trường Đại học Trà Vinh.</p>
            <div class="portal-hero-foot"><span>QUẢN LÝ CÂY XANH</span><span>·</span><span>KHUÔN VIÊN XANH — TRƯỜNG HỌC XANH</span></div>
        </div>
        <div class="portal-hero-art" aria-hidden="true">
            <span class="art-sun"></span>
            <span class="art-orbit art-orbit-one"></span>
            <span class="art-orbit art-orbit-two"></span>
            <span class="art-leaf art-leaf-one"></span>
            <span class="art-leaf art-leaf-two"></span>
            <span class="art-leaf art-leaf-three"></span>
            <span class="art-ground"></span>
            <span class="art-caption">CÙNG GÌN GIỮ MÀU XANH HỌC ĐƯỜNG</span>
        </div>
    </section>
    <div class="login-shell">
        <form class="login-panel" id="support" method="post">
            <div class="eyebrow">TRUY CẬP HỆ THỐNG</div>
            <h2>Đăng nhập hệ thống</h2>
            <p class="muted">Sử dụng tài khoản được cấp để tiếp tục.</p>
            <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <label>Tên đăng nhập<input name="ten_dang_nhap" autocomplete="username" required autofocus></label>
            <label>Mật khẩu<input type="password" name="mat_khau" autocomplete="current-password" required></label>
            <button class="button button-primary button-wide" type="submit">Đăng nhập</button>
            <div class="demo-accounts"><strong>Hỗ trợ tài khoản</strong><br>Liên hệ quản trị viên nếu bạn chưa có thông tin đăng nhập.</div>
        </form>
    </div>
    <section class="portal-services" aria-label="Các chức năng hệ thống">
        <div class="portal-services-heading"><span class="eyebrow">DỊCH VỤ NỘI BỘ</span><h2>Các chức năng hệ thống</h2></div>
        <div class="portal-service-grid">
            <article class="portal-service"><span class="service-icon" aria-hidden="true">♧</span><span><strong>Hồ sơ cây xanh</strong><small>Tra cứu thông tin và tình trạng cây</small></span></article>
            <article class="portal-service"><span class="service-icon" aria-hidden="true">⌖</span><span><strong>Bản đồ khuôn viên</strong><small>Quan sát vị trí cây theo tọa độ</small></span></article>
            <article class="portal-service"><span class="service-icon" aria-hidden="true">✳</span><span><strong>Chăm sóc cây</strong><small>Ghi nhận hoạt động chăm sóc</small></span></article>
            <article class="portal-service"><span class="service-icon" aria-hidden="true">▤</span><span><strong>Báo cáo tổng hợp</strong><small>Theo dõi dữ liệu theo khu vực</small></span></article>
        </div>
    </section>
</section>
<?php require __DIR__ . '/footer.php'; ?>