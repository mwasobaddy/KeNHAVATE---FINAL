<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class FileUploadSecurityService
{
    /**
     * Allowed MIME types for different file categories
     */
    protected array $allowedMimeTypes = [
        'documents' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'text/csv',
        ],
        'images' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
        ],
        'archives' => [
            'application/zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
            'application/gzip',
        ],
        'videos' => [
            'video/mp4',
            'video/avi',
            'video/quicktime',
            'video/x-msvideo',
        ]
    ];

    /**
     * Forbidden file extensions (security risks)
     */
    protected array $forbiddenExtensions = [
        'exe', 'bat', 'com', 'scr', 'pif', 'cmd', 'vbs', 'js', 'jar',
        'php', 'asp', 'aspx', 'jsp', 'pl', 'py', 'rb', 'sh', 'ps1',
        'msi', 'deb', 'rpm', 'dmg', 'app', 'pkg'
    ];

    /**
     * Maximum file sizes by category (in bytes)
     */
    protected array $maxFileSizes = [
        'documents' => 10 * 1024 * 1024, // 10MB
        'images' => 5 * 1024 * 1024,     // 5MB
        'archives' => 50 * 1024 * 1024,  // 50MB
        'videos' => 100 * 1024 * 1024,   // 100MB
        'default' => 10 * 1024 * 1024,   // 10MB default
    ];

    /**
     * Validate uploaded file for security
     */
    public function validateFile(UploadedFile $file, string $context = 'general'): array
    {
        $result = [
            'valid' => false,
            'errors' => [],
            'warnings' => [],
            'metadata' => []
        ];

        try {
            // Basic file validation
            if (!$file->isValid()) {
                $result['errors'][] = 'File upload failed or corrupted';
                return $result;
            }

            // File size validation
            $fileSize = $file->getSize();
            $category = $this->detectFileCategory($file);
            $maxSize = $this->maxFileSizes[$category] ?? $this->maxFileSizes['default'];

            if ($fileSize > $maxSize) {
                $result['errors'][] = "File size exceeds maximum allowed size of " . $this->formatBytes($maxSize);
                return $result;
            }

            // Extension validation
            $extension = strtolower($file->getClientOriginalExtension());
            if (in_array($extension, $this->forbiddenExtensions)) {
                $result['errors'][] = "File type '{$extension}' is not allowed for security reasons";
                return $result;
            }

            // MIME type validation
            $mimeType = $file->getMimeType();
            if (!$this->isAllowedMimeType($mimeType)) {
                $result['errors'][] = "File type '{$mimeType}' is not allowed";
                return $result;
            }

            // File name validation
            $fileName = $file->getClientOriginalName();
            if (!$this->isValidFileName($fileName)) {
                $result['errors'][] = 'File name contains invalid characters';
                return $result;
            }

            // Content validation (basic header checks)
            $contentValidation = $this->validateFileContent($file);
            if (!$contentValidation['valid']) {
                $result['errors'] = array_merge($result['errors'], $contentValidation['errors']);
                return $result;
            }

            // Virus scan (if enabled)
            if (config('app.enable_virus_scan', false)) {
                $virusScanResult = $this->scanForViruses($file);
                if (!$virusScanResult['clean']) {
                    $result['errors'][] = 'File failed security scan';
                    $this->logSecurityEvent('virus_detected', $file, $virusScanResult);
                    return $result;
                }
            }

            // All validations passed
            $result['valid'] = true;
            $result['metadata'] = [
                'original_name' => $fileName,
                'size' => $fileSize,
                'mime_type' => $mimeType,
                'extension' => $extension,
                'category' => $category,
                'hash' => hash_file('sha256', $file->getRealPath()),
            ];

        } catch (Exception $e) {
            $result['errors'][] = 'File validation failed: ' . $e->getMessage();
            Log::error('File validation error', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
                'context' => $context
            ]);
        }

        return $result;
    }

    /**
     * Securely store uploaded file
     */
    public function storeFile(UploadedFile $file, string $directory, string $context = 'general'): array
    {
        $validation = $this->validateFile($file, $context);
        
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors'],
                'path' => null,
                'metadata' => null
            ];
        }

        try {
            // Generate secure filename
            $secureFilename = $this->generateSecureFilename($file);
            
            // Store file with restricted permissions
            $path = $file->storeAs(
                $directory,
                $secureFilename,
                ['disk' => 'private', 'visibility' => 'private']
            );

            // Store metadata
            $metadata = array_merge($validation['metadata'], [
                'stored_path' => $path,
                'stored_filename' => $secureFilename,
                'upload_timestamp' => now(),
                'uploader_id' => auth()->id(),
                'upload_ip' => request()->ip(),
                'context' => $context
            ]);

            // Log successful upload
            $this->logSecurityEvent('file_uploaded', $file, $metadata);

            return [
                'success' => true,
                'errors' => [],
                'path' => $path,
                'metadata' => $metadata
            ];

        } catch (Exception $e) {
            Log::error('File storage error', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
                'context' => $context
            ]);

            return [
                'success' => false,
                'errors' => ['File storage failed: ' . $e->getMessage()],
                'path' => null,
                'metadata' => null
            ];
        }
    }

    /**
     * Validate file content headers for basic file type verification
     */
    protected function validateFileContent(UploadedFile $file): array
    {
        $result = ['valid' => true, 'errors' => []];

        try {
            $filePath = $file->getRealPath();
            $fileHeader = file_get_contents($filePath, false, null, 0, 1024);

            // PDF validation
            if ($file->getMimeType() === 'application/pdf') {
                if (!str_starts_with($fileHeader, '%PDF-')) {
                    $result['valid'] = false;
                    $result['errors'][] = 'File claims to be PDF but content validation failed';
                }
            }

            // JPEG validation
            if (in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'])) {
                if (!str_starts_with($fileHeader, "\xFF\xD8\xFF")) {
                    $result['valid'] = false;
                    $result['errors'][] = 'File claims to be JPEG but content validation failed';
                }
            }

            // PNG validation
            if ($file->getMimeType() === 'image/png') {
                if (!str_starts_with($fileHeader, "\x89PNG\x0D\x0A\x1A\x0A")) {
                    $result['valid'] = false;
                    $result['errors'][] = 'File claims to be PNG but content validation failed';
                }
            }

            // ZIP validation
            if ($file->getMimeType() === 'application/zip') {
                if (!str_starts_with($fileHeader, "PK\x03\x04") && !str_starts_with($fileHeader, "PK\x05\x06")) {
                    $result['valid'] = false;
                    $result['errors'][] = 'File claims to be ZIP but content validation failed';
                }
            }

        } catch (Exception $e) {
            // Content validation is optional, don't fail upload for this
            Log::warning('File content validation error', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage()
            ]);
        }

        return $result;
    }

    /**
     * Basic virus scanning (placeholder for actual antivirus integration)
     */
    protected function scanForViruses(UploadedFile $file): array
    {
        // This is a placeholder for actual virus scanning integration
        // In production, integrate with ClamAV, VirusTotal API, or similar service
        
        try {
            // Simulate virus scanning
            $filePath = $file->getRealPath();
            $fileSize = filesize($filePath);
            
            // Basic suspicious file detection
            if ($fileSize > 100 * 1024 * 1024) { // Files over 100MB are suspicious
                return [
                    'clean' => false,
                    'reason' => 'File size exceeds security threshold'
                ];
            }

            // Check for suspicious patterns in file content (basic example)
            $content = file_get_contents($filePath, false, null, 0, 4096);
            $suspiciousPatterns = [
                'eval(',
                'exec(',
                'system(',
                'shell_exec(',
                'passthru(',
                'base64_decode(',
                'str_rot13(',
                'gzinflate(',
                'gzuncompress(',
                'str_replace(',
            ];

            foreach ($suspiciousPatterns as $pattern) {
                if (stripos($content, $pattern) !== false) {
                    return [
                        'clean' => false,
                        'reason' => 'Suspicious code patterns detected'
                    ];
                }
            }

            return ['clean' => true, 'reason' => 'File passed basic security checks'];

        } catch (Exception $e) {
            Log::error('Virus scan error', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage()
            ]);
            
            // Fail secure - reject file if scanning fails
            return [
                'clean' => false,
                'reason' => 'Security scan failed'
            ];
        }
    }

    /**
     * Generate secure filename
     */
    protected function generateSecureFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = now()->format('Y-m-d_H-i-s');
        $randomString = Str::random(16);
        $userId = auth()->id() ?? 'anonymous';
        
        return "{$timestamp}_{$userId}_{$randomString}.{$extension}";
    }

    /**
     * Detect file category based on MIME type
     */
    protected function detectFileCategory(UploadedFile $file): string
    {
        $mimeType = $file->getMimeType();
        
        foreach ($this->allowedMimeTypes as $category => $types) {
            if (in_array($mimeType, $types)) {
                return $category;
            }
        }
        
        return 'default';
    }

    /**
     * Check if MIME type is allowed
     */
    protected function isAllowedMimeType(string $mimeType): bool
    {
        foreach ($this->allowedMimeTypes as $types) {
            if (in_array($mimeType, $types)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Validate filename for security
     */
    protected function isValidFileName(string $filename): bool
    {
        // Check for dangerous characters
        $dangerousChars = ['..', '/', '\\', '<', '>', ':', '"', '|', '?', '*', "\0"];
        
        foreach ($dangerousChars as $char) {
            if (strpos($filename, $char) !== false) {
                return false;
            }
        }
        
        // Check filename length
        if (strlen($filename) > 255) {
            return false;
        }
        
        // Check for valid UTF-8
        if (!mb_check_encoding($filename, 'UTF-8')) {
            return false;
        }
        
        return true;
    }

    /**
     * Format bytes for human readable display
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = floor(log($bytes, 1024));
        
        return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    /**
     * Log security events
     */
    protected function logSecurityEvent(string $event, UploadedFile $file, array $metadata = []): void
    {
        Log::info('File security event', [
            'event' => $event,
            'filename' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata,
            'timestamp' => now()
        ]);

        // Also log to audit trail
        if (class_exists(\App\Services\AuditService::class)) {
            // Map file events to valid audit actions
            $actionMap = [
                'uploaded' => 'challenge_participation',
                'deleted' => 'challenge_participation',
                'virus_detected' => 'account_reporting',
                'size_exceeded' => 'challenge_participation',
                'type_rejected' => 'challenge_participation',
            ];
            
            $auditAction = $actionMap[$event] ?? 'challenge_participation';
            
            app(\App\Services\AuditService::class)->log(
                $auditAction,
                'File',
                null, // No numeric ID for file operations
                null,
                array_merge([
                    'filename' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ], $metadata)
            );
        }
    }

    /**
     * Get allowed file types for frontend validation
     */
    public function getAllowedFileTypes(): array
    {
        $allTypes = [];
        foreach ($this->allowedMimeTypes as $category => $types) {
            $allTypes = array_merge($allTypes, $types);
        }
        
        return array_unique($allTypes);
    }

    /**
     * Get maximum file size for category
     */
    public function getMaxFileSize(string $category = 'default'): int
    {
        return $this->maxFileSizes[$category] ?? $this->maxFileSizes['default'];
    }

    /**
     * Quarantine suspicious file
     */
    public function quarantineFile(string $filePath, string $reason): bool
    {
        try {
            $quarantinePath = 'quarantine/' . basename($filePath);
            
            if (Storage::disk('private')->exists($filePath)) {
                Storage::disk('private')->move($filePath, $quarantinePath);
                
                Log::warning('File quarantined', [
                    'original_path' => $filePath,
                    'quarantine_path' => $quarantinePath,
                    'reason' => $reason,
                    'timestamp' => now()
                ]);
                
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            Log::error('Failed to quarantine file', [
                'file_path' => $filePath,
                'reason' => $reason,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
}
