<?php

/**
 * Cloudinary Storage Helper
 * Centralized helper for all Cloudinary storage operations
 * Ensures NO files are stored on server
 */
class CloudinaryStorage {
    
    private static $cloudinaryService = null;
    private static $loggingService = null;
    
    /**
     * Get CloudinaryService instance
     */
    public static function getService() {
        if (self::$cloudinaryService === null) {
            try {
                // Check if Database class is available
                if (!class_exists('Database')) {
                    return null;
                }
                
                $db = \Database::getInstance()->getConnection();
                $settingsQuery = $db->query("SELECT setting_key, setting_value FROM system_settings");
                $settings = $settingsQuery->fetchAll(\PDO::FETCH_KEY_PAIR);
                
                self::$cloudinaryService = new \App\Services\CloudinaryService();
                self::$cloudinaryService->loadFromSettings($settings);
            } catch (\Exception $e) {
                // Return null if Cloudinary not configured
                return null;
            }
        }
        return self::$cloudinaryService;
    }
    
    /**
     * Get CloudinaryLoggingService instance
     */
    public static function getLogger() {
        // Only create logger if Database class is available
        // Use multiple checks with @ suppression to prevent autoload errors
        if (!@class_exists('Database') && !@class_exists('\Database')) {
            return null;
        }
        
        // Additional check - verify Database methods exist
        if (!@method_exists('Database', 'getInstance')) {
            return null;
        }
        
        if (self::$loggingService === null) {
            try {
                // Wrap in try-catch to catch any errors during instantiation
                self::$loggingService = new \App\Services\CloudinaryLoggingService();
            } catch (\Error $e) {
                // Catch Error first (class not found, etc.)
                return null;
            } catch (\Exception $e) {
                // If logger creation fails, return null
                return null;
            } catch (\Throwable $e) {
                // Catch any other throwable
                return null;
            }
        }
        return self::$loggingService;
    }
    
    /**
     * Upload file from uploaded file array
     * Replaces move_uploaded_file() - uploads directly to Cloudinary
     * 
     * @param array $file $_FILES array element
     * @param string $folder Cloudinary folder
     * @param array $options Additional upload options
     * @return array Result with secure_url or error
     */
    public static function uploadFile($file, $folder = 'sellapp/uploads', $options = []) {
        $service = self::getService();
        if (!$service || !$service->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Cloudinary not configured'
            ];
        }
        
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return [
                'success' => false,
                'error' => 'Invalid file upload'
            ];
        }
        
        // Determine resource type based on file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        $resourceType = in_array($extension, $imageExtensions) ? 'image' : 'raw';
        
        if ($resourceType === 'image') {
            $result = $service->uploadImage($file['tmp_name'], $folder, $options);
        } else {
            $result = $service->uploadRawFile($file['tmp_name'], $folder, $options);
        }
        
        // Delete temp file immediately after upload
        @unlink($file['tmp_name']);
        
        return $result;
    }
    
    /**
     * Upload file from string content
     * 
     * @param string $content File content
     * @param string $filename Filename
     * @param string $folder Cloudinary folder
     * @param string $resourceType 'image' or 'raw'
     * @return array Result with secure_url or error
     */
    public static function uploadFromString($content, $filename, $folder = 'sellapp/uploads', $resourceType = 'raw') {
        $service = self::getService();
        if (!$service || !$service->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Cloudinary not configured'
            ];
        }
        
        // Create temporary file in memory
        $tempFile = tmpfile();
        if ($tempFile === false) {
            return [
                'success' => false,
                'error' => 'Failed to create temporary file'
            ];
        }
        
        fwrite($tempFile, $content);
        $tempPath = stream_get_meta_data($tempFile)['uri'];
        
        try {
            if ($resourceType === 'image') {
                $result = $service->uploadImage($tempPath, $folder);
            } else {
                $result = $service->uploadRawFile($tempPath, $folder, [
                    'public_id' => pathinfo($filename, PATHINFO_FILENAME)
                ]);
            }
        } finally {
            // Always close temp file (automatically deleted)
            fclose($tempFile);
        }
        
        return $result;
    }
    
    /**
     * Upload PDF/receipt to Cloudinary
     * 
     * @param string $pdfContent PDF content
     * @param string $filename Filename
     * @param string $folder Folder (default: sellapp/receipts)
     * @return array Result with secure_url
     */
    public static function uploadPDF($pdfContent, $filename, $folder = 'sellapp/receipts') {
        return self::uploadFromString($pdfContent, $filename, $folder, 'raw');
    }
    
    /**
     * Log message to Cloudinary (replaces error_log)
     * 
     * @param string $message Log message
     * @param string $level Log level
     */
    public static function log($message, $level = 'info') {
        // Only try to log if Database class is available
        if (!class_exists('Database') || !class_exists('\Database')) {
            error_log($message);
            return;
        }
        
        // Additional check - verify Database methods exist
        if (!method_exists('Database', 'getInstance')) {
            error_log($message);
            return;
        }
        
        try {
            $logger = self::getLogger();
            if ($logger) {
                $logger->log($message, $level);
            } else {
                error_log($message);
            }
        } catch (\Error $e) {
            // Catch Error (class not found, etc.) - fallback to error_log
            error_log($message);
        } catch (\Exception $e) {
            error_log($message);
        } catch (\Throwable $e) {
            // Catch any other throwable - fallback to error_log
            error_log($message);
        }
    }
    
    /**
     * Log error (replaces error_log for errors)
     */
    public static function logError($message, $context = []) {
        // Only try to log if Database class is available
        // This prevents errors when called before database config is loaded
        if (!class_exists('Database') || !class_exists('\Database')) {
            // Fallback to PHP error_log if Database not available
            error_log($message);
            return;
        }
        
        // Additional check - verify Database methods exist
        if (!method_exists('Database', 'getInstance')) {
            error_log($message);
            return;
        }
        
        try {
            $logger = self::getLogger();
            if ($logger) {
                $logger->error($message, $context);
            } else {
                // Fallback to PHP error_log if logger not available
                error_log($message);
            }
        } catch (\Error $e) {
            // Catch Error (class not found, etc.) - fallback to error_log
            error_log($message);
        } catch (\Exception $e) {
            // Fallback to PHP error_log if Cloudinary logging fails
            error_log($message);
        } catch (\Throwable $e) {
            // Catch any other throwable - fallback to error_log
            error_log($message);
        }
    }
}

