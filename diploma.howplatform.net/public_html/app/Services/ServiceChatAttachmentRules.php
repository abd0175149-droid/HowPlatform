<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\HTTP\Files\UploadedFile;

final class ServiceChatAttachmentRules
{
    public const MAX_FILE_BYTES = 150 * 1024 * 1024;

    public const ALLOWED_MIME = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'video/mp4', 'video/webm', 'video/ogg',
        'application/pdf',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip', 'application/x-rar-compressed',
        'audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/webm', 'audio/wav',
    ];

    private const EXT_FALLBACK = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
        'mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogg' => 'video/ogg',
        'pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'zip' => 'application/zip', 'rar' => 'application/x-rar-compressed',
        'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'oga' => 'audio/ogg', 'ogg' => 'audio/ogg', 'weba' => 'audio/webm', 'm4a' => 'audio/mp4',
    ];

    public static function normalizeMime(?UploadedFile $file, string $current = ''): string
    {
        $mime = strtolower(trim($current));
        if ($mime === '' && $file instanceof UploadedFile) {
            $mime = strtolower((string) ($file->getMimeType() ?: ''));
            if ($mime === '') {
                $ext = strtolower((string) $file->getClientExtension());
                if ($ext !== '') {
                    $mime = self::EXT_FALLBACK[$ext] ?? '';
                }
            }
        }
        return $mime;
    }

    public static function isAllowedMime(string $mime): bool
    {
        return $mime !== '' && in_array($mime, self::ALLOWED_MIME, true);
    }

    public static function classify(string $mime, string $clientName): string
    {
        $mime = strtolower($mime);
        $name = strtolower($clientName);

        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }
        if (str_starts_with($mime, 'audio/')) {
            if (preg_match('/^voice_[0-9]+/i', $name)) {
                return 'voice_recording';
            }
            return 'audio';
        }
        return 'document';
    }
}
