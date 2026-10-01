<?php
// multipart POST: product_id + image. Validates size + real MIME, random safe filename, restaurant-specific folder.
require_once __DIR__ . '/../../includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);
start_secure_session(); require_csrf();
$u = require_permission('manage_products');
$rid = tenant_id($u);
$pid = (int)($_POST['product_id'] ?? 0);

$st = db()->prepare('SELECT p.image_path, r.slug FROM products p JOIN restaurants r ON r.id = p.restaurant_id
                     WHERE p.id=? AND p.restaurant_id=? AND p.deleted_at IS NULL');
$st->execute([$pid, $rid]);
$row = $st->fetch();
if (!$row) json_error('Item not found.', 404);

$f = $_FILES['image'] ?? null;
if (!$f || $f['error'] !== UPLOAD_ERR_OK) json_error('Choose an image to upload.', 422);
if ($f['size'] > 3 * 1024 * 1024) json_error('Image must be under 3 MB.', 422);
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
$ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
if (!$ext || !@getimagesize($f['tmp_name'])) json_error('Only JPG, PNG or WebP images are allowed.', 422);

$dir = UPLOAD_DIR . $row['slug'] . '/products/';
if (!is_dir($dir) && !mkdir($dir, 0755, true)) json_error('Could not save the image.', 500);
$file = bin2hex(random_bytes(12)) . '.' . $ext;
if (!move_uploaded_file($f['tmp_name'], $dir . $file)) json_error('Could not save the image.', 500);

$rel = 'uploads/restaurants/' . $row['slug'] . '/products/' . $file;
db()->prepare('UPDATE products SET image_path=? WHERE id=? AND restaurant_id=?')->execute([$rel, $pid, $rid]);
if ($row['image_path'] && str_starts_with($row['image_path'], 'uploads/restaurants/' . $row['slug'] . '/')) {
    @unlink(__DIR__ . '/../../' . $row['image_path']);       // remove the old file, only inside this restaurant's folder
}
log_activity('product.image', (int)$u['id'], $rid, 'product', $pid);
json_out(['ok' => true, 'image' => public_url($rel)]);
