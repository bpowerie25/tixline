<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AttachmentService
{
    public function storeUploadedFile(UploadedFile $file, Model $attachable): ?Attachment
    {
        $originalName = $file->getClientOriginalName();

        if (! $this->isAllowedMime($file->getMimeType())) {
            Log::warning('Attachment rejected: disallowed MIME type', [
                'filename' => $originalName,
                'mime' => $file->getMimeType(),
            ]);

            return null;
        }

        if ($file->getSize() > config('support.attachments.max_size_bytes')) {
            Log::warning('Attachment rejected: file too large', [
                'filename' => $originalName,
                'size' => $file->getSize(),
                'max' => config('support.attachments.max_size_bytes'),
            ]);

            return null;
        }

        $disk = config('support.attachments.disk', 'local');
        $safeName = $this->sanitizeFilename($file->hashName());
        $directory = 'attachments/'.$attachable->getMorphClass().'/'.$attachable->getKey();
        $path = $file->storeAs($directory, $safeName, $disk);

        return $attachable->attachments()->create([
            'filename' => $safeName,
            'original_filename' => $this->sanitizeOriginalFilename($file->getClientOriginalName()),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'path' => $path,
        ]);
    }

    public function storeFromWebhook(array $attachmentData, Model $attachable): ?Attachment
    {
        $content = base64_decode($attachmentData['content'] ?? '', true);
        if ($content === false) {
            return null;
        }

        $mime = $attachmentData['content_type'] ?? 'application/octet-stream';
        if (! $this->isAllowedMime($mime)) {
            return null;
        }

        $size = strlen($content);
        if ($size > config('support.attachments.max_size_bytes')) {
            return null;
        }

        $disk = config('support.attachments.disk', 'local');
        $safeName = Str::random(40).$this->safeExtension($mime);
        $directory = 'attachments/'.$attachable->getMorphClass().'/'.$attachable->getKey();
        $path = $directory.'/'.$safeName;

        Storage::disk($disk)->put($path, $content);

        $originalName = $this->sanitizeOriginalFilename($attachmentData['filename'] ?? 'attachment');

        return $attachable->attachments()->create([
            'filename' => $safeName,
            'original_filename' => $originalName,
            'mime_type' => $mime,
            'size' => $size,
            'path' => $path,
        ]);
    }

    protected function isAllowedMime(string $mime): bool
    {
        $allowed = config('support.attachments.allowed_mimes', []);

        return in_array(strtolower($mime), $allowed, true);
    }

    protected function sanitizeFilename(string $filename): string
    {
        // Strip any directory traversal
        $filename = basename($filename);
        // Remove null bytes and other dangerous characters
        $filename = preg_replace('/[\x00-\x1f\x7f\/\\\\:]/', '', $filename);
        // Strip double extensions that hide executables
        $filename = preg_replace('/\.(exe|bat|cmd|sh|php|phtml|jsp|cgi|pl|py|rb|ps1|vbs|js|msi|dll|com|scr)$/i', '.blocked', $filename);

        return $filename ?: 'attachment';
    }

    protected function sanitizeOriginalFilename(string $filename): string
    {
        $filename = basename($filename);
        $filename = preg_replace('/[\x00-\x1f\x7f]/', '', $filename);
        // Collapse path traversal attempts
        $filename = str_replace(['../', '..\\', '..'], '', $filename);

        return mb_substr($filename, 0, 255) ?: 'attachment';
    }

    protected function safeExtension(string $mime): string
    {
        $map = [
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/gif' => '.gif',
            'image/webp' => '.webp',
            'application/pdf' => '.pdf',
            'text/plain' => '.txt',
            'text/csv' => '.csv',
            'application/msword' => '.doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '.docx',
            'application/vnd.ms-excel' => '.xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => '.xlsx',
            'application/zip' => '.zip',
            'message/rfc822' => '.eml',
        ];

        return $map[strtolower($mime)] ?? '.bin';
    }
}
