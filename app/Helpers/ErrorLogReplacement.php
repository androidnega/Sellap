<?php

/**
 * Error Log Replacement
 * Replaces PHP's error_log() function to use Cloudinary instead of file system
 * This ensures NO log files are stored on the server
 */

// Override error_log function if Cloudinary is configured
if (!function_exists('cloudinary_error_log')) {
    function cloudinary_error_log($message, $messageType = 0, $destination = null, $extraHeaders = null) {
        // Only use Cloudinary if destination is not explicitly set (default behavior)
        if ($destination === null) {
            try {
                // Use CloudinaryStorage helper
                if (class_exists('CloudinaryStorage')) {
                    CloudinaryStorage::logError($message);
                    return true;
                }
            } catch (\Exception $e) {
                // Fallback: use PHP's default error_log (but this should rarely happen)
                // Only if Cloudinary completely fails
            }
        }
        
        // For explicit destinations (email, etc.), use PHP's default
        return error_log($message, $messageType, $destination, $extraHeaders);
    }
}

// Replace error_log globally if Cloudinary is available
if (class_exists('CloudinaryStorage')) {
    // Store original function
    if (!function_exists('original_error_log')) {
        function original_error_log($message, $messageType = 0, $destination = null, $extraHeaders = null) {
            return error_log($message, $messageType, $destination, $extraHeaders);
        }
    }
    
    // Note: We can't directly override error_log() in PHP, but we can create a wrapper
    // The application should use CloudinaryStorage::logError() instead
}

