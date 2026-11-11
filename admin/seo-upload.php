<?php
// Receives one image for a one-time signed upload link (created by seo_create_upload_link, admin only).
// The browser sends the raw file body: PUT /admin/seo-upload?token=…  Content-Type: image/jpeg|png|webp
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';
require __DIR__ . '/_inc/seo_articles.php';

header('Content-Type: application/json; charset=utf-8');
if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST'], true)) {
    http_response_code(405);
    echo json_encode(['error' => 'Use PUT to upload the file.']);
    exit;
}
try {
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > SEO_IMAGE_MAX_BYTES) throw new InvalidArgumentException('The image must be 5 MB or smaller.');
    $in = fopen('php://input', 'rb');
    $body = (string) stream_get_contents($in, SEO_IMAGE_MAX_BYTES + 1);
    echo json_encode(seo_receive_upload((string) ($_GET['token'] ?? ''), $body), JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e instanceof InvalidArgumentException ? $e->getMessage() : admin_error($e, 'upload the image')]);
}
