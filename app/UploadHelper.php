<?php



declare(strict_types=1);



namespace App;



class UploadHelper

{

    private const ALLOWED_LOGO = ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/svg+xml'];

    private const ALLOWED_AD_IMAGE = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];

    private const ALLOWED_AD_VIDEO = ['video/mp4', 'video/webm'];

    private const MAX_LOGO_BYTES = 2097152;

    private const MAX_AD_IMAGE_BYTES = 5242880;

    private const MAX_AD_VIDEO_BYTES = 52428800;



    /** @return array{success: bool, path?: string, error?: string} */

    public static function storeLogo(array $file, string $projectRoot): array

    {

        return self::storeMedia($file, $projectRoot, 'assets/uploads', 'site-logo', self::ALLOWED_LOGO, self::MAX_LOGO_BYTES, true);

    }



    /** @return array{success: bool, path?: string, error?: string} */

    public static function storeAdImage(array $file, string $projectRoot): array

    {

        return self::storeMedia($file, $projectRoot, 'assets/uploads/media/banners', 'banner', self::ALLOWED_AD_IMAGE, self::MAX_AD_IMAGE_BYTES, false);

    }



    /** @return array{success: bool, path?: string, error?: string} */

    public static function storeAdVideo(array $file, string $projectRoot): array

    {

        return self::storeMedia($file, $projectRoot, 'assets/uploads/media/clips', 'clip', self::ALLOWED_AD_VIDEO, self::MAX_AD_VIDEO_BYTES, false);

    }



    /** @param list<string> $allowedMimes @return array{success: bool, path?: string, error?: string} */

    private static function storeMedia(

        array $file,

        string $projectRoot,

        string $relativeDir,

        string $namePrefix,

        array $allowedMimes,

        int $maxBytes,

        bool $fixedFilename

    ): array {

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {

            return ['success' => false, 'error' => 'no_file'];

        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {

            return ['success' => false, 'error' => 'upload_failed'];

        }

        if (($file['size'] ?? 0) > $maxBytes) {

            return ['success' => false, 'error' => 'too_large'];

        }



        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;

        if ($finfo) {

            finfo_close($finfo);

        }

        if (!in_array($mime, $allowedMimes, true)) {

            return ['success' => false, 'error' => 'invalid_type'];

        }



        $ext = match ($mime) {

            'image/png' => 'png',

            'image/jpeg' => 'jpg',

            'image/webp' => 'webp',

            'image/gif' => 'gif',

            'image/svg+xml' => 'svg',

            'video/mp4' => 'mp4',

            'video/webm' => 'webm',

            default => 'bin',

        };



        $dir = $projectRoot . '/' . $relativeDir;

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {

            return ['success' => false, 'error' => 'mkdir_failed'];

        }



        $filename = $fixedFilename ? ($namePrefix . '.' . $ext) : ($namePrefix . '-' . bin2hex(random_bytes(8)) . '.' . $ext);

        $dest = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {

            return ['success' => false, 'error' => 'move_failed'];

        }



        return ['success' => true, 'path' => $relativeDir . '/' . $filename];

    }

}


