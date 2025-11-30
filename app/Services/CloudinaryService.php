<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;

/**
 * Cloudinary Service
 * Handles all image upload and management operations
 */
class CloudinaryService {
    
    private $cloudinary;
    private $uploadApi;
    private $cloudName;
    private $apiKey;
    private $apiSecret;
    
    public function __construct($cloudName = null, $apiKey = null, $apiSecret = null) {
        // Check if Cloudinary classes are available
        if (!class_exists('\Cloudinary\Cloudinary')) {
            throw new \RuntimeException('Cloudinary library not installed. Please run: composer require cloudinary/cloudinary_php');
        }
        
        // Load credentials from parameters, then environment variables, then empty defaults
        $this->cloudName = $cloudName ?: getenv('CLOUDINARY_CLOUD_NAME') ?: '';
        $this->apiKey = $apiKey ?: getenv('CLOUDINARY_API_KEY') ?: '';
        $this->apiSecret = $apiSecret ?: getenv('CLOUDINARY_API_SECRET') ?: '';
        
        $this->initialize();
    }
    
    /**
     * Initialize Cloudinary configuration
     */
    private function initialize() {
        // Only initialize if we have all required credentials
        if (empty($this->cloudName) || empty($this->apiKey) || empty($this->apiSecret)) {
            $this->cloudinary = null;
            $this->uploadApi = null;
            return;
        }
        
        try {
            // Initialize Cloudinary by passing configuration directly to constructor
            // This is the recommended approach that works reliably
            $this->cloudinary = new Cloudinary([
                'cloud' => [
                    'cloud_name' => $this->cloudName,
                    'api_key' => $this->apiKey,
                    'api_secret' => $this->apiSecret
                ],
                'url' => [
                    'secure' => true
                ]
            ]);
            
            // UploadApi can be created from the Cloudinary instance
            $this->uploadApi = $this->cloudinary->uploadApi();
        } catch (\Exception $e) {
            // If initialization fails, set to null and handle gracefully
            $this->cloudinary = null;
            $this->uploadApi = null;
            error_log("Cloudinary initialization failed: " . $e->getMessage());
            // Re-throw if we have credentials but initialization still failed
            // This allows callers to handle the error appropriately
            if (!empty($this->cloudName) && !empty($this->apiKey) && !empty($this->apiSecret)) {
                throw $e;
            }
        }
    }
    
    /**
     * Upload image to Cloudinary
     * 
     * @param string $filePath Path to the file to upload
     * @param string $folder Folder name in Cloudinary (optional)
     * @param array $options Additional upload options
     * @return array Upload result with public_id and secure_url
     */
    public function uploadImage($filePath, $folder = 'sellapp', $options = []) {
        try {
            $defaultOptions = [
                'folder' => $folder,
                'resource_type' => 'image',
                'quality' => 'auto',
                'fetch_format' => 'auto'
            ];
            
            $uploadOptions = array_merge($defaultOptions, $options);
            
            $result = $this->uploadApi->upload($filePath, $uploadOptions);
            
            return [
                'success' => true,
                'public_id' => $result['public_id'],
                'secure_url' => $result['secure_url'],
                'width' => $result['width'] ?? null,
                'height' => $result['height'] ?? null,
                'format' => $result['format'] ?? null,
                'bytes' => $result['bytes'] ?? null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Upload image from base64 data
     * 
     * @param string $base64Data Base64 encoded image data
     * @param string $folder Folder name in Cloudinary
     * @param array $options Additional upload options
     * @return array Upload result
     */
    public function uploadBase64Image($base64Data, $folder = 'sellapp', $options = []) {
        try {
            $defaultOptions = [
                'folder' => $folder,
                'resource_type' => 'image',
                'quality' => 'auto',
                'fetch_format' => 'auto'
            ];
            
            $uploadOptions = array_merge($defaultOptions, $options);
            
            $result = $this->uploadApi->upload($base64Data, $uploadOptions);
            
            return [
                'success' => true,
                'public_id' => $result['public_id'],
                'secure_url' => $result['secure_url'],
                'width' => $result['width'] ?? null,
                'height' => $result['height'] ?? null,
                'format' => $result['format'] ?? null,
                'bytes' => $result['bytes'] ?? null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete image from Cloudinary
     * 
     * @param string $publicId Public ID of the image to delete
     * @return array Delete result
     */
    public function deleteImage($publicId) {
        try {
            $result = $this->uploadApi->destroy($publicId);
            
            return [
                'success' => true,
                'result' => $result['result']
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate optimized image URL with transformations
     * 
     * @param string $publicId Public ID of the image
     * @param array $transformations Image transformations
     * @return string Optimized image URL
     */
    public function getOptimizedUrl($publicId, $transformations = []) {
        try {
            $defaultTransformations = [
                'quality' => 'auto',
                'fetch_format' => 'auto'
            ];
            
            $finalTransformations = array_merge($defaultTransformations, $transformations);
            
            return $this->cloudinary->image($publicId)->resize($finalTransformations)->toUrl();
        } catch (\Exception $e) {
            return '';
        }
    }
    
    /**
     * Get image information
     * 
     * @param string $publicId Public ID of the image
     * @return array Image information
     */
    public function getImageInfo($publicId) {
        try {
            $result = $this->cloudinary->adminApi()->asset($publicId);
            
            return [
                'success' => true,
                'public_id' => $result['public_id'],
                'secure_url' => $result['secure_url'],
                'width' => $result['width'],
                'height' => $result['height'],
                'format' => $result['format'],
                'bytes' => $result['bytes'],
                'created_at' => $result['created_at']
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Load configuration from database settings
     * 
     * @param array $settings Database settings array
     * @return void
     */
    public function loadFromSettings($settings) {
        $this->cloudName = $settings['cloudinary_cloud_name'] ?? '';
        $this->apiKey = $settings['cloudinary_api_key'] ?? '';
        $this->apiSecret = $settings['cloudinary_api_secret'] ?? '';
        
        // Reinitialize with new settings
        $this->initialize();
    }
    
    /**
     * Upload raw file (e.g., zip, pdf, etc.) to Cloudinary
     * 
     * @param string $filePath Path to the file to upload
     * @param string $folder Folder name in Cloudinary (optional)
     * @param array $options Additional upload options
     * @return array Upload result with public_id and secure_url
     */
    public function uploadRawFile($filePath, $folder = 'sellapp/backups', $options = []) {
        try {
            if (!file_exists($filePath)) {
                return [
                    'success' => false,
                    'error' => 'File not found: ' . $filePath
                ];
            }
            
            $defaultOptions = [
                'folder' => $folder,
                'resource_type' => 'raw',
                'use_filename' => true,
                'unique_filename' => true
            ];
            
            $uploadOptions = array_merge($defaultOptions, $options);
            
            $result = $this->uploadApi->upload($filePath, $uploadOptions);
            
            return [
                'success' => true,
                'public_id' => $result['public_id'],
                'secure_url' => $result['secure_url'],
                'bytes' => $result['bytes'] ?? null,
                'format' => $result['format'] ?? null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * List all backups from Cloudinary
     * 
     * @param string $folder Folder to search in (default: sellapp/backups)
     * @param int $maxResults Maximum number of results to return
     * @return array List of backup files
     */
    public function listBackups($folder = 'sellapp/backups', $maxResults = 100) {
        try {
            if (!$this->isConfigured()) {
                return [
                    'success' => false,
                    'error' => 'Cloudinary not configured',
                    'backups' => []
                ];
            }
            
            $adminApi = $this->cloudinary->adminApi();
            
            // Search for raw files in the backups folder
            $result = $adminApi->assets([
                'resource_type' => 'raw',
                'type' => 'upload',
                'prefix' => $folder,
                'max_results' => $maxResults
            ]);
            
            $backups = [];
            if (isset($result['resources'])) {
                foreach ($result['resources'] as $resource) {
                    $backups[] = [
                        'public_id' => $resource['public_id'],
                        'secure_url' => $resource['secure_url'],
                        'bytes' => $resource['bytes'] ?? 0,
                        'format' => $resource['format'] ?? 'zip',
                        'created_at' => $resource['created_at'] ?? null,
                        'filename' => basename($resource['public_id'])
                    ];
                }
            }
            
            return [
                'success' => true,
                'backups' => $backups,
                'total' => count($backups)
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'backups' => []
            ];
        }
    }
    
    /**
     * Download backup from Cloudinary
     * 
     * @param string $publicId Public ID of the backup
     * @param string $savePath Path to save the downloaded file
     * @return array Download result
     */
    public function downloadBackup($publicId, $savePath) {
        try {
            if (!$this->isConfigured()) {
                return [
                    'success' => false,
                    'error' => 'Cloudinary not configured'
                ];
            }
            
            // Get the secure URL
            $url = $this->cloudinary->raw($publicId)->toUrl();
            
            // Download the file
            $fileContent = file_get_contents($url);
            
            if ($fileContent === false) {
                return [
                    'success' => false,
                    'error' => 'Failed to download file from Cloudinary'
                ];
            }
            
            // Ensure directory exists
            $dir = dirname($savePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            // Save file
            $written = file_put_contents($savePath, $fileContent);
            
            if ($written === false) {
                return [
                    'success' => false,
                    'error' => 'Failed to save downloaded file'
                ];
            }
            
            return [
                'success' => true,
                'path' => $savePath,
                'size' => $written
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if Cloudinary is properly configured
     * 
     * @return bool
     */
    public function isConfigured() {
        // Check if credentials are set (this is the primary check)
        if (empty($this->cloudName) || empty($this->apiKey) || empty($this->apiSecret)) {
            return false;
        }
        
        // If credentials are set but objects are null, try to initialize
        if ($this->cloudinary === null || $this->uploadApi === null) {
            try {
                $this->initialize();
            } catch (\Exception $e) {
                // If initialization fails even with credentials, return false
                // but log the error for debugging
                error_log("Cloudinary re-initialization failed: " . $e->getMessage());
                return false;
            }
        }
        
        // Return true if we have credentials and objects are initialized
        return $this->cloudinary !== null && $this->uploadApi !== null;
    }
}

