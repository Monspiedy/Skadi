<?php

declare(strict_types=1);

function imageUploadPolicy(string $context): array
{
    $policies = [
        'game_cover' => [
            'directory' => 'uploads/covers',
            'maximum_bytes' => 5 * 1024 * 1024,
            'minimum_width' => 320,
            'minimum_height' => 180,
            'maximum_width' => 4000,
            'maximum_height' => 4000,
        ],
        'game_screenshot' => [
            'directory' => 'uploads/screenshots',
            'maximum_bytes' => 8 * 1024 * 1024,
            'minimum_width' => 640,
            'minimum_height' => 360,
            'maximum_width' => 7680,
            'maximum_height' => 4320,
        ],
        'avatar' => [
            'directory' => 'uploads/avatars',
            'maximum_bytes' => 2 * 1024 * 1024,
            'minimum_width' => 64,
            'minimum_height' => 64,
            'maximum_width' => 2048,
            'maximum_height' => 2048,
        ],
        'guide_thumbnail' => [
            'directory' => 'uploads/guides',
            'maximum_bytes' => 3 * 1024 * 1024,
            'minimum_width' => 320,
            'minimum_height' => 180,
            'maximum_width' => 4000,
            'maximum_height' => 4000,
        ],
        'community_image' => [
            'directory' => 'uploads/community',
            'maximum_bytes' => 5 * 1024 * 1024,
            'minimum_width' => 320,
            'minimum_height' => 180,
            'maximum_width' => 5000,
            'maximum_height' => 5000,
        ],
    ];

    if (!isset($policies[$context])) {
        throw new InvalidArgumentException('Unknown upload context.');
    }

    return $policies[$context] + [
        'extensions' => [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ],
        'mime_extensions' => [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ],
    ];
}

function uploadErrorMessage(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The image exceeds the server upload limit.',
        UPLOAD_ERR_PARTIAL => 'The image upload was interrupted. Please try again.',
        UPLOAD_ERR_NO_FILE => 'Choose an image to upload.',
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION =>
            'The server could not receive the image.',
        default => 'The image upload failed.',
    };
}

function imageAltTextError(string $altText, int $fileCount = 1): ?string
{
    $length = mb_strlen($altText);
    if ($length < 1 || $length > 255 || preg_match('/[\p{C}]/u', $altText) === 1) {
        return 'Alternative text must be 1-255 readable characters.';
    }

    if ($fileCount > 1) {
        $longestSuffix = ' ' . $fileCount;
        if ($length + mb_strlen($longestSuffix) > 255) {
            return 'Alternative text is too long for numbered screenshots.';
        }
    }

    return null;
}

function validateUploadedImage(array $file, string $context): array
{
    $policy = imageUploadPolicy($context);
    $error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

    if ($error !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => uploadErrorMessage($error)];
    }

    $temporaryPath = $file['tmp_name'] ?? '';
    if (!is_string($temporaryPath) || $temporaryPath === '' || !is_file($temporaryPath)) {
        return ['valid' => false, 'error' => 'The uploaded image could not be read.'];
    }

    $actualSize = filesize($temporaryPath);
    if ($actualSize === false || $actualSize < 1 || $actualSize > $policy['maximum_bytes']) {
        return [
            'valid' => false,
            'error' => 'The image must be smaller than ' . formatByteLimit($policy['maximum_bytes']) . '.',
        ];
    }

    $originalName = is_string($file['name'] ?? null) ? $file['name'] : '';
    $submittedExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!isset($policy['extensions'][$submittedExtension])) {
        return ['valid' => false, 'error' => 'Only JPG, PNG and WebP images are allowed.'];
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = $fileInfo->file($temporaryPath);
    if (!is_string($detectedMime) || !isset($policy['mime_extensions'][$detectedMime])) {
        return ['valid' => false, 'error' => 'The file content is not an allowed image type.'];
    }

    if ($policy['extensions'][$submittedExtension] !== $detectedMime) {
        return ['valid' => false, 'error' => 'The filename extension does not match the image content.'];
    }

    $imageDetails = @getimagesize($temporaryPath);
    if ($imageDetails === false || ($imageDetails['mime'] ?? null) !== $detectedMime) {
        return ['valid' => false, 'error' => 'The file is not a readable JPG, PNG or WebP image.'];
    }

    $width = (int) ($imageDetails[0] ?? 0);
    $height = (int) ($imageDetails[1] ?? 0);
    if (
        $width < $policy['minimum_width']
        || $height < $policy['minimum_height']
        || $width > $policy['maximum_width']
        || $height > $policy['maximum_height']
    ) {
        return [
            'valid' => false,
            'error' => sprintf(
                'Image dimensions must be between %d×%d and %d×%d pixels.',
                $policy['minimum_width'],
                $policy['minimum_height'],
                $policy['maximum_width'],
                $policy['maximum_height']
            ),
        ];
    }

    return [
        'valid' => true,
        'temporary_path' => $temporaryPath,
        'extension' => $policy['mime_extensions'][$detectedMime],
        'mime' => $detectedMime,
        'size' => $actualSize,
        'width' => $width,
        'height' => $height,
        'directory' => $policy['directory'],
    ];
}

function formatByteLimit(int $bytes): string
{
    return number_format($bytes / 1024 / 1024, 0) . ' MB';
}

function storeValidatedImage(array $validatedImage): string
{
    if (empty($validatedImage['valid'])) {
        throw new InvalidArgumentException('Only a validated image can be stored.');
    }

    $relativeDirectory = (string) $validatedImage['directory'];
    $absoluteDirectory = SKADI_ROOT . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
    if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0755, true) && !is_dir($absoluteDirectory)) {
        throw new RuntimeException('The upload directory could not be created.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $validatedImage['extension'];
    $relativePath = $relativeDirectory . '/' . $filename;
    $absolutePath = $absoluteDirectory . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file((string) $validatedImage['temporary_path'], $absolutePath)) {
        throw new RuntimeException('The validated image could not be stored.');
    }

    return $relativePath;
}

function isManagedGameUploadPath(string $storagePath): bool
{
    return preg_match(
        '#\Auploads/(?:covers|screenshots)/[a-f0-9]{32}\.(?:jpg|png|webp)\z#',
        $storagePath
    ) === 1;
}

function deleteManagedGameUpload(string $storagePath): bool
{
    if (!isManagedGameUploadPath($storagePath)) {
        return false;
    }

    $absolutePath = SKADI_ROOT . '/' . str_replace('/', DIRECTORY_SEPARATOR, $storagePath);
    if (!is_file($absolutePath)) {
        return true;
    }

    return unlink($absolutePath);
}

function isManagedAvatarUploadPath(string $storagePath): bool
{
    return preg_match(
        '#\Auploads/avatars/[a-f0-9]{32}\.(?:jpg|png|webp)\z#',
        $storagePath
    ) === 1;
}

function deleteManagedAvatarUpload(string $storagePath): bool
{
    if (!isManagedAvatarUploadPath($storagePath)) {
        return false;
    }

    $absolutePath = SKADI_ROOT . '/' . str_replace('/', DIRECTORY_SEPARATOR, $storagePath);
    if (!is_file($absolutePath)) {
        return true;
    }

    return unlink($absolutePath);
}

function isManagedGuideUploadPath(string $storagePath): bool
{
    return preg_match('#\Auploads/guides/[a-f0-9]{32}\.(?:jpg|png|webp)\z#', $storagePath) === 1;
}

function deleteManagedGuideUpload(string $storagePath): bool
{
    if (!isManagedGuideUploadPath($storagePath)) {
        return false;
    }
    $absolutePath = SKADI_ROOT . '/' . str_replace('/', DIRECTORY_SEPARATOR, $storagePath);
    return !is_file($absolutePath) || unlink($absolutePath);
}

function isManagedCommunityUploadPath(string $storagePath): bool
{
    return preg_match('#\Auploads/community/[a-f0-9]{32}\.(?:jpg|png|webp)\z#', $storagePath) === 1;
}

function deleteManagedCommunityUpload(string $storagePath): bool
{
    if (!isManagedCommunityUploadPath($storagePath)) {
        return false;
    }
    $absolutePath = SKADI_ROOT . '/' . str_replace('/', DIRECTORY_SEPARATOR, $storagePath);
    return !is_file($absolutePath) || unlink($absolutePath);
}

function normalizeMultipleUploads(array $files): array
{
    if (!isset($files['name']) || !is_array($files['name'])) {
        return [$files];
    }

    $normalized = [];
    foreach (array_keys($files['name']) as $index) {
        $normalized[] = [
            'name' => $files['name'][$index] ?? '',
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$index] ?? 0,
        ];
    }

    return $normalized;
}
