<?php
require __DIR__ . '/config.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Phương thức không được hỗ trợ.');
}
verify_csrf();
$pdo = db();
$treeId = trim((string) ($_POST['ma_cay'] ?? ''));
$pdo->beginTransaction();
try {
    $history = $pdo->prepare('DELETE FROM lich_su_cham_soc WHERE ma_cay = ?');
    $history->execute([$treeId]);
    $tree = $pdo->prepare('DELETE FROM cay_xanh WHERE ma_cay = ?');
    $tree->execute([$treeId]);
    $pdo->commit();
} catch (Throwable $exception) {
    $pdo->rollBack();
    throw $exception;
}
redirect('trees.php');