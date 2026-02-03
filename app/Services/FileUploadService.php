<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileUploadService
{
    private string $uploadsPath;
    private string $uploadsUrl;

    public function __construct()
    {
        $this->uploadsPath = env('SHARED_UPLOADS_PATH');
        $this->uploadsUrl = env('SHARED_UPLOADS_URL');
    }

    /**
     * Upload profile image
     */
    public function uploadProfileImage(UploadedFile $file, int $employeeId): ?array
    {
        try {
            Log::info('FileUploadService::uploadProfileImage started', [
                'employee_id' => $employeeId,
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'uploads_path' => $this->uploadsPath . '',
                'uploads_url' => $this->uploadsUrl
            ]);
            
            $directory = 'users/thumb';
            $filename = 'employee_' . $employeeId . '_' . time() . '.' . $file->getClientOriginalExtension();
            
            return $this->uploadFile($file, $directory, $filename);
        } catch (\Exception $e) {
            Log::error('FileUploadService::uploadProfileImage failed', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'message' => 'فشل في تحديث بيانات هذا الموظف',
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception(message: 'فشل في تحديث بيانات هذا الموظف', code: 500);
        }
    }

    /**
     * Upload document
     */
    public function uploadDocument(UploadedFile $file, int $employeeId, string $directory, string $documentType): ?array
    {
        try {
            $filename = $documentType . '_' . $employeeId . '_' . time() . '.' . $file->getClientOriginalExtension();
            
            return $this->uploadFile($file, $directory, $filename);
        } catch (\Exception $e) {
            Log::error('FileUploadService::uploadDocument failed', [
                'employee_id' => $employeeId,
                'document_type' => $documentType,
                'error' => $e->getMessage(),
                'message' => 'فشل تحميل المستند'
            ]);
            throw new \Exception(message: 'فشل تحميل المستند', code: 500);
        }
    }

    /**
     * Generic file upload method
     */
    private function uploadFile(UploadedFile $file, string $directory, string $filename): ?array
    {
        try {
            Log::info('FileUploadService::uploadFile started', [
                'directory' => $directory,
                'filename' => $filename,
                'uploads_path' => $this->uploadsPath,
                'temp_file' => $file->getPathname(),
                'temp_file_exists' => file_exists($file->getPathname())
            ]);

            // Get file info before moving (to avoid issues after move)
            $originalName = $file->getClientOriginalName();
            $size = $file->getSize();
            $mimeType = $file->getMimeType();
            $tempPath = $file->getPathname();

            // Create directory if it doesn't exist
            $fullDirectory = $this->uploadsPath . '/' . $directory;
            if (!is_dir($fullDirectory)) {
                Log::info('Creating directory', ['directory' => $fullDirectory]);
                if (!mkdir($fullDirectory, 0755, true)) {
                    Log::error('Failed to create directory', [
                        'directory' => $fullDirectory,
                        'message' => 'فشل في تحديث بيانات هذا الموظف',
                    ]);
                    throw new \Exception(message: 'فشل في تحديث بيانات هذا الموظف', code: 500);
                }
            }

            // Check if directory is writable
            if (!is_writable($fullDirectory)) {
                Log::error('Directory is not writable', [
                    'directory' => $fullDirectory,
                    'message' => 'فشل في تحديث بيانات هذا الموظف',
                ]);
                throw new \Exception(message: 'فشل في تحديث بيانات هذا الموظف', code: 500);
            }

            // Full file path
            $fullPath = $fullDirectory . '/' . $filename;
            
            Log::info('Attempting to move file', [
                'from' => $tempPath,
                'to' => $fullPath,
                'directory_writable' => is_writable($fullDirectory)
            ]);

            // Use copy instead of move to avoid temp file issues
            if (copy($tempPath, $fullPath)) {
                $fileUrl = $this->uploadsUrl . '/' . $directory . '/' . $filename;
                
                Log::info('File copied successfully', [
                    'file_path' => $fullPath,
                    'file_url' => $fileUrl,
                    'file_exists' => file_exists($fullPath),
                    'message' => 'تم نسخ الملف بنجاح'
                ]);
                
                return [
                    'file_path' => $fullPath,
                    'file_url' => $fileUrl,
                    'filename' => $filename,
                    'original_name' => $originalName,
                    'size' => $size,
                    'mime_type' => $mimeType
                ];
            }

            Log::error('Failed to copy file', [
                'directory' => $directory,
                'filename' => $filename,
                'message' => 'فشل في تحديث بيانات هذا الموظف',
            ]);
            throw new \Exception(message: 'فشل في تحديث بيانات هذا الموظف', code: 500);
        } catch (\Exception $e) {
            Log::error('FileUploadService::uploadFile failed', [
                'directory' => $directory,
                'filename' => $filename,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception(message: 'فشل في تحديث بيانات هذا الموظف', code: 500);
        }
    }

    /**
     * Delete file
     */
    public function deleteFile(string $filePath): bool
    {
        try {
            if (file_exists($filePath)) {
                return unlink($filePath);
            }
            return true; // File doesn't exist, consider it deleted
        } catch (\Exception $e) {
            Log::error('FileUploadService::deleteFile failed', [
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);
            throw new \Exception(message: 'فشل في تحديث بيانات هذا الموظف', code: 500);
        }
    }

    /**
     * Get file URL from path
     */
    public function getFileUrl(string $filePath): string
    {
        return str_replace($this->uploadsPath, $this->uploadsUrl, $filePath);
    }
}