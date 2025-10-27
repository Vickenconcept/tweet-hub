<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    protected Cloudinary $cloudinary;

    public function __construct()
    {
        $this->cloudinary = new Cloudinary();
    }

    /**
     * Upload a file to Cloudinary
     */
    public function uploadFile($file, $type = 'image')
    {
        try {
            $filename = $file->getClientOriginalName();
            $resourceType = $this->getResourceType($file);
            $mimeType = $file->getMimeType();
            
            // Build upload options based on file type
            $uploadOptions = [
                'resource_type' => $resourceType,
                'folder' => "digital_content/{$resourceType}",
                'public_id' => pathinfo($filename, PATHINFO_FILENAME),
                'use_filename' => false,
                'unique_filename' => true,
            ];
            
            // Add format and quality options for images only
            if ($resourceType === 'image') {
                // Check if it's a GIF - preserve GIF format for animated GIFs
                if (str_contains(strtolower($filename), '.gif') || $mimeType === 'image/gif') {
                    $uploadOptions['format'] = 'gif'; // Preserve GIF format for animated GIFs
                } else {
                    $uploadOptions['format'] = 'jpg'; // Force JPEG format for other images
                    $uploadOptions['quality'] = 'auto'; // Auto quality for optimal file size
                }
            }
            
            $cloudinaryResponse = $this->cloudinary->uploadApi()->upload($file->getRealPath(), $uploadOptions);

            Log::info('File uploaded to Cloudinary successfully', [
                'public_id' => $cloudinaryResponse['public_id'],
                'secure_url' => $cloudinaryResponse['secure_url'],
                'original_name' => $filename,
                'resource_type' => $resourceType,
                'mime_type' => $mimeType
            ]);

            $secureUrl = $cloudinaryResponse['secure_url'];
            
            // For images, ensure proper format for Twitter compatibility
            if ($resourceType === 'image') {
                if (str_contains(strtolower($filename), '.gif') || $mimeType === 'image/gif') {
                    // For GIFs, ensure .gif extension in URL
                    if (strpos($secureUrl, '.gif') === false) {
                        $secureUrl = str_replace('/upload/', '/upload/f_gif/', $secureUrl);
                    }
                } else {
                    // For other images, ensure JPEG format
                    if (strpos($secureUrl, '.jpg') === false && strpos($secureUrl, '.jpeg') === false) {
                        $secureUrl = str_replace('/upload/', '/upload/f_jpg/', $secureUrl);
                    }
                }
            }

            // Set correct MIME type based on format
            $returnMimeType = $mimeType;
            if ($resourceType === 'image') {
                if (str_contains(strtolower($filename), '.gif') || $mimeType === 'image/gif') {
                    $returnMimeType = 'image/gif';
                } else {
                    $returnMimeType = 'image/jpeg';
                }
            }

            return [
                'file_path' => $secureUrl,
                'file_type' => $resourceType,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $returnMimeType,
                'size' => $file->getSize(),
                'original_filename' => $file->getClientOriginalName(),
                'cloudinary_public_id' => $cloudinaryResponse['public_id'],
            ];

        } catch (\Exception $e) {
            Log::error('Cloudinary upload failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName()
            ]);
            throw $e;
        }
    }

    /**
     * Upload a file from file path (for AI generated images)
     */
    public function uploadFileFromPath($filePath, $filename = 'ai_generated.png')
    {
        try {
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($fileInfo, $filePath);
            finfo_close($fileInfo);
            
            // Determine resource type
            $resourceType = 'image';
            if (str_starts_with($mimeType, 'video/')) {
                $resourceType = 'video';
            } elseif (str_starts_with($mimeType, 'audio/')) {
                $resourceType = 'audio';
            }
            
            // Build upload options
            $uploadOptions = [
                'resource_type' => $resourceType,
                'folder' => "digital_content/{$resourceType}",
                'public_id' => pathinfo($filename, PATHINFO_FILENAME),
                'use_filename' => false,
                'unique_filename' => true,
            ];
            
            // Add format and quality options for images
            if ($resourceType === 'image') {
                if (str_contains(strtolower($filename), '.gif') || $mimeType === 'image/gif') {
                    $uploadOptions['format'] = 'gif';
                } else {
                    $uploadOptions['format'] = 'png';
                    $uploadOptions['quality'] = 'auto';
                }
            }
            
            $cloudinaryResponse = $this->cloudinary->uploadApi()->upload($filePath, $uploadOptions);
            
            Log::info('File uploaded from path to Cloudinary successfully', [
                'public_id' => $cloudinaryResponse['public_id'],
                'secure_url' => $cloudinaryResponse['secure_url'],
                'file_path' => $filePath
            ]);
            
            $secureUrl = $cloudinaryResponse['secure_url'];
            
            return [
                'file_path' => $secureUrl,
                'file_type' => $resourceType,
                'original_name' => $filename,
                'mime_type' => $mimeType,
                'size' => filesize($filePath),
                'cloudinary_public_id' => $cloudinaryResponse['public_id'],
            ];
            
        } catch (\Exception $e) {
            Log::error('Cloudinary upload from path failed', [
                'error' => $e->getMessage(),
                'file_path' => $filePath
            ]);
            throw $e;
        }
    }
    
    /**
     * Get resource type based on file mime type
     */
    private function getResourceType($file)
    {
        $mimeType = $file->getMimeType();
        
        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        } elseif (str_starts_with($mimeType, 'image/')) {
            return 'image';
        } elseif (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        } else {
            return 'raw';
        }
    }

    /**
     * Delete a file from Cloudinary
     */
    public function deleteFile($publicId)
    {
        try {
            $result = $this->cloudinary->uploadApi()->destroy($publicId);
            
            Log::info('File deleted from Cloudinary', [
                'public_id' => $publicId,
                'result' => $result
            ]);
            
            return $result;
        } catch (\Exception $e) {
            Log::error('Cloudinary deletion failed', [
                'error' => $e->getMessage(),
                'public_id' => $publicId
            ]);
            throw $e;
        }
    }
}
