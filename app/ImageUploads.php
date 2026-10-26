<?php
declare(strict_types=1);

final class ImageUploads
{
    public const MAX_BYTES = 2 * 1024 * 1024;
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(private readonly string $directory) {}

    public static function isValidName(string $name): bool
    {
        return preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/D', $name) === 1;
    }

    public function receive(string $field): ?string
    {
        $file = $_FILES[$field] ?? null;
        if ($file === null || (is_array($file) && ($file['error'] ?? null) === UPLOAD_ERR_NO_FILE)) {
            return null;
        }
        if (!is_array($file) || ($file['error'] ?? null) !== UPLOAD_ERR_OK
            || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Photo upload failed. Choose a JPG, PNG, or WebP image up to 2 MB.');
        }
        $size = filesize($file['tmp_name']);
        if ($size === false || $size === 0 || $size > self::MAX_BYTES) {
            throw new RuntimeException('Choose a photo up to 2 MB.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $image = @getimagesize($file['tmp_name']);
        if (!isset(self::TYPES[$mime]) || !$image || ($image['mime'] ?? '') !== $mime
            || $image[0] < 1 || $image[1] < 1 || $image[0] > 4096 || $image[1] > 4096) {
            throw new RuntimeException('Use a valid JPG, PNG, or WebP image, no larger than 4096 × 4096 pixels.');
        }
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Photo storage is unavailable.');
        }
        $name = bin2hex(random_bytes(16)) . '.' . self::TYPES[$mime];
        if (!move_uploaded_file($file['tmp_name'], $this->directory . '/' . $name)) {
            throw new RuntimeException('Your photo could not be saved. Please try again.');
        }
        return $name;
    }

    public function discard(?string $name): void
    {
        if ($name !== null && self::isValidName($name)) {
            $path = $this->directory . '/' . $name;
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function serve(string $name): never
    {
        if (!self::isValidName($name) || !is_file($this->directory . '/' . $name)) {
            http_response_code(404);
            exit('Photo not found.');
        }
        $path = $this->directory . '/' . $name;
        $mime = array_search(pathinfo($name, PATHINFO_EXTENSION), self::TYPES, true);
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . filesize($path));
        if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
            readfile($path);
        }
        exit;
    }
}
