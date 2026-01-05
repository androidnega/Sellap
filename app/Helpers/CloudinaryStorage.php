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
                // CRITICAL: Check Database class availability BEFORE doing anything else
                // Use @ suppression and false flag to prevent autoload errors
                if (!@class_exists('Database', false) && !@class_exists('\Database', false)) {
                    return null;
                }
                
                // Additional check - verify Database methods exist
                if (!@method_exists('Database', 'getInstance')) {
                    return null;
                }
                
                $db = \Database::getInstance()->getConnection();
                $settingsQuery = $db->query("SELECT setting_key, setting_value FROM system_settings");
                $settings = $settingsQuery->fetchAll(\PDO::FETCH_KEY_PAIR);
                
                self::$cloudinaryService = new \App\Services\CloudinaryService();
                self::$cloudinaryService->loadFromSettings($settings);
            } catch (\Error $e) {
                // Return null if Database class not found or any error
                return null;
            } catch (\Exception $e) {
                // Return null if Cloudinary not configured
                return null;
            } catch (\Throwable $e) {
                // Return null for any other throwable
                return null;
            }
        }
        return self::$cloudinaryService;
    }
    
    /**
     * Get CloudinaryLoggingService instance
     */
    public static function getLogger() {
        // CRITICAL: Check Database class availability BEFORE doing anything else
        // Use @ suppression with false flag to prevent ANY autoload attempts
        // Return null immediately if Database doesn't exist - don't even try to create logger
        
        // First check: Does Database class exist? (use false flag to prevent autoload)
        // Wrap in try-catch to be absolutely safe
        try {
            $dbExists = @class_exists('Database', false);
            if (!$dbExists) {
                return null; // Database not available, return null immediately
            }
        } catch (\Throwable $e) {
            // If checking Database fails, return null
            return null;
        }
        
        // Second check: Can we verify Database methods exist? (use false flag)
        // Wrap in try-catch to be absolutely safe
        try {
            $methodExists = @method_exists('Database', 'getInstance');
            if (!$methodExists) {
                return null; // Database methods not available, return null immediately
            }
        } catch (\Throwable $e) {
            // If checking method fails, return null
            return null;
        }
        
        // Only create logger if Database exists and is usable
        if (self::$loggingService === null) {
            // CRITICAL: Use @ suppression on the new operator itself to prevent any errors
            // Also wrap in try-catch to catch ANY errors during instantiation
            // CloudinaryLoggingService constructor is now completely safe and won't throw errors
            try {
                // Use @ suppression to prevent any errors from being thrown during instantiation
                self::$loggingService = @new \App\Services\CloudinaryLoggingService();
                
                // If logger was created but is disabled, that's fine - return it anyway
                // The log() methods will check enabled state
            } catch (\Error $e) {
                // Catch Error first (class not found, etc.) - return null
                self::$loggingService = null;
                return null;
            } catch (\Exception $e) {
                // If logger creation fails, return null
                self::$loggingService = null;
                return null;
            } catch (\Throwable $e) {
                // Catch any other throwable - return null
                self::$loggingService = null;
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
        // CRITICAL: Check Database class availability BEFORE doing anything else
        // Use @ suppression and multiple checks to prevent any autoload errors
        try {
            // First check: Does Database class exist? (use false to prevent autoload)
            if (!@class_exists('Database', false) && !@class_exists('\Database', false)) {
                error_log($message);
                return;
            }
            
            // Second check: Can we verify Database methods exist?
            if (!@method_exists('Database', 'getInstance')) {
                error_log($message);
                return;
            }
        } catch (\Error $e) {
            // If ANY error occurs checking Database, use error_log
            error_log($message);
            return;
        } catch (\Throwable $e) {
            // If ANY throwable occurs checking Database, use error_log
            error_log($message);
            return;
        }
        
        // Only try to get logger if Database checks passed
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
        // CRITICAL: This method MUST NEVER throw an error, even if Database doesn't exist
        // Always fallback to error_log() if anything goes wrong
        // Wrap EVERYTHING in try-catch to ensure no errors escape
        
        try {
            // Immediately fallback to error_log if Database doesn't exist
            // Use @ suppression and false flag to prevent ANY autoload attempts
            if (!@class_exists('Database', false)) {
                error_log($message);
                return;
            }
            
            // Additional check - verify Database methods exist
            if (!@method_exists('Database', 'getInstance')) {
                error_log($message);
                return;
            }
            
            // Only try to get logger if Database checks passed
            // Wrap everything in try-catch to ensure no errors escape
            try {
                $logger = self::getLogger();
                if ($logger) {
                    // Logger exists - try to use it, but fallback if it fails
                    try {
                        $logger->error($message, $context);
                    } catch (\Throwable $e) {
                        // If logger fails, fallback to error_log
                        error_log($message);
                    }
                } else {
                    // Fallback to PHP error_log if logger not available
                    error_log($message);
                }
            } catch (\Throwable $e) {
                // Catch ANY throwable (Error, Exception, etc.) - fallback to error_log
                error_log($message);
            }
        } catch (\Throwable $e) {
            // Catch ANY throwable at the top level - always fallback to error_log
            error_log($message);
        }
    }
}

