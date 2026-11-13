<?php
declare(strict_types=1);
/**
 * Accepted image types: JPG/PNG/WebP. Safe, size-limited Base64 data URIs.
 * User-supplied SVG and remote URLs are never accepted for uploaded imagery.
 */
const LMS_IMAGE_MAX_BYTES = 1048576; // 1 MiB raw upload (Base64 ~1.4 MiB)
function lmsUploadImage(string $field, ?string $existing = null, ?string $removeField = null): ?string {
    if ($removeField && ($_POST[$removeField] ?? '') === '1') return null;
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return $existing;
    $file = $_FILES[$field];
    if (($file['error'] ?? -1) !== UPLOAD_ERR_OK) throw new InvalidArgumentException('Image upload failed. Check the file size and try again.');
    if (!is_uploaded_file((string)($file['tmp_name'] ?? ''))) throw new InvalidArgumentException('Invalid image upload.');
    if ((int)($file['size'] ?? 0) < 1 || (int)$file['size'] > LMS_IMAGE_MAX_BYTES) throw new InvalidArgumentException('Upload an image no larger than 1 MB.');
    $bytes = file_get_contents((string)$file['tmp_name']);
    if ($bytes === false || strlen($bytes) > LMS_IMAGE_MAX_BYTES) throw new InvalidArgumentException('Unable to read this image.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
    if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) throw new InvalidArgumentException('Only JPG, PNG and WebP images are accepted.');
    $dimensions = @getimagesizefromstring($bytes);
    if ($dimensions === false || $dimensions[0] < 1 || $dimensions[1] < 1 || $dimensions[0] > 6000 || $dimensions[1] > 6000) throw new InvalidArgumentException('Image dimensions must be between 1 and 6,000 pixels.');
    return 'data:'.$mime.';base64,'.base64_encode($bytes);
}
function lmsImageDataUri(?string $stored): ?string {
    if (!$stored || strlen($stored) > 2_000_000 || !preg_match('~^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/]+={0,2})$~D', $stored, $m)) return null;
    // Decode to verify stored Base64 integrity; browser decodes it again for display.
    $bytes = base64_decode($m[2], true);
    if ($bytes === false || strlen($bytes) > LMS_IMAGE_MAX_BYTES) return null;
    return $stored;
}
function lmsImageHtml(?string $stored, string $alt, string $class = 'lms-photo'): string {
    $uri = lmsImageDataUri($stored);
    if ($uri === null) return '';
    return '<img class="'.htmlspecialchars($class, ENT_QUOTES, 'UTF-8').'" src="'.htmlspecialchars($uri, ENT_QUOTES, 'UTF-8').'" alt="'.htmlspecialchars($alt, ENT_QUOTES, 'UTF-8').'" loading="lazy" decoding="async">';
}
