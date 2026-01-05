<?php

namespace App\Services;

/**
 * Cloudinary Logging Service
 * Replaces file-based logging with Cloudinary storage
 * All logs are uploaded to Cloudinary and never stored on server
 */
class CloudinaryLoggingService {
    
    private $cloudinaryService;
    private $logBuffer = [];
    private $bufferSize = 50; // Buffer logs before uploading
    private $logFolder = 'sellapp/logs';
    private $enabled = true;
    
    public function __construct() {
        try {
            // Check if Database class is available
            if (!class_exists('Database')) {
                $this->enabled = false;
                return;
            }
            
            $db = \Database::getInstance()->getConnection();
            $settingsQuery = $db->query("SELECT setting_key, setting_value FROM system_settings");
            $settings = $settingsQuery->fetchAll(\PDO::FETCH_KEY_PAIR);
            
            $this->cloudinaryService = new CloudinaryService();
            $this->cloudinaryService->loadFromSettings($settings);
            
            // Check if Cloudinary is configured
            if (!$this->cloudinaryService->isConfigured()) {
                $this->enabled = false;
            }
        } catch (\Exception $e) {
            $this->enabled = false;
        }
    }
    
    /**
     * Log a message to Cloudinary
     * 
     * @param string $message Log message
     * @param string $level Log level (error, warning, info, debug)
     * @param array $context Additional context data
     */
    public function log($message, $level = 'info', $context = []) {
        if (!$this->enabled) {
            return;
        }
        
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ];
        
        $this->logBuffer[] = $logEntry;
        
        // Upload when buffer is full
        if (count($this->logBuffer) >= $this->bufferSize) {
            $this->flush();
        }
    }
    
    /**
     * Flush log buffer to Cloudinary
     */
    public function flush() {
        if (empty($this->logBuffer) || !$this->enabled) {
            return;
        }
        
        try {
            // Create log file content
            $logContent = '';
            foreach ($this->logBuffer as $entry) {
                $logContent .= sprintf(
                    "[%s] %s: %s | IP: %s | URI: %s | Context: %s\n",
                    $entry['timestamp'],
                    strtoupper($entry['level']),
                    $entry['message'],
                    $entry['ip'],
                    $entry['uri'],
                    json_encode($entry['context'])
                );
            }
            
            // Create temporary file in memory (not on disk)
            // Use php://temp which stays in memory for small files
            $tempFile = fopen('php://temp', 'r+');
            if ($tempFile === false) {
                return;
            }
            
            fwrite($tempFile, $logContent);
            rewind($tempFile);
            $tempPath = stream_get_meta_data($tempFile)['uri'];
            
            // Upload to Cloudinary
            $dateFolder = date('Y/m/d');
            $fileName = 'log_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.txt';
            $folder = $this->logFolder . '/' . $dateFolder;
            
            $result = $this->cloudinaryService->uploadRawFile($tempPath, $folder, [
                'resource_type' => 'raw',
                'public_id' => $fileName,
                'overwrite' => false
            ]);
            
            // Close temp file (automatically deleted)
            fclose($tempFile);
            
            // Clear buffer on success
            if ($result['success']) {
                $this->logBuffer = [];
            }
        } catch (\Exception $e) {
            // Silently fail - don't break application if logging fails
        }
    }
    
    /**
     * Log error
     */
    public function error($message, $context = []) {
        $this->log($message, 'error', $context);
    }
    
    /**
     * Log warning
     */
    public function warning($message, $context = []) {
        $this->log($message, 'warning', $context);
    }
    
    /**
     * Log info
     */
    public function info($message, $context = []) {
        $this->log($message, 'info', $context);
    }
    
    /**
     * Log debug
     */
    public function debug($message, $context = []) {
        $this->log($message, 'debug', $context);
    }
    
    /**
     * Destructor - flush remaining logs
     */
    public function __destruct() {
        $this->flush();
    }
}

