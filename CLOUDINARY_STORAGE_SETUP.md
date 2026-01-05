# Cloudinary Storage Setup - Zero Server Storage

This document describes the Cloudinary-based storage system that ensures **NO data, logs, or files are stored on the server**.

## Overview

All application data is stored in Cloudinary to:
- **Prevent disk space usage** on the server
- **Improve server uptime** by reducing I/O operations
- **Eliminate file system dependencies**
- **Centralize all storage** in the cloud

## Components

### 1. Database Sessions (`app/Services/DatabaseSessionHandler.php`)
- Stores PHP sessions in database instead of file system
- Automatically creates `sessions` table
- Handles session garbage collection
- **No files created on server**

### 2. Cloudinary Logging Service (`app/Services/CloudinaryLoggingService.php`)
- Replaces file-based `error_log()` calls
- Buffers logs and uploads to Cloudinary in batches
- Stores logs in `sellapp/logs/YYYY/MM/DD/` folders
- **No log files on server**

### 3. Cloudinary Storage Helper (`app/Helpers/CloudinaryStorage.php`)
- Centralized helper for all Cloudinary operations
- Provides `uploadFile()`, `uploadFromString()`, `uploadPDF()` methods
- Handles file uploads directly to Cloudinary
- **No uploaded files stored on server**

### 4. Error Log Replacement (`app/Helpers/ErrorLogReplacement.php`)
- Provides wrapper functions for error logging
- Routes all errors to Cloudinary instead of file system
- **No error logs on server**

## Configuration

### Required Cloudinary Settings

Ensure these are set in `system_settings` table:
- `cloudinary_cloud_name`
- `cloudinary_api_key`
- `cloudinary_api_secret`

### Database Setup

The system automatically creates a `sessions` table for database sessions:
```sql
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    data TEXT,
    last_activity INT UNSIGNED NOT NULL,
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Usage

### Uploading Files

**Old way (stores on server):**
```php
move_uploaded_file($_FILES['image']['tmp_name'], 'uploads/image.jpg');
```

**New way (Cloudinary only):**
```php
$result = CloudinaryStorage::uploadFile($_FILES['image'], 'sellapp/products');
if ($result['success']) {
    $imageUrl = $result['secure_url']; // Use this URL in database
}
```

### Logging

**Old way (stores on server):**
```php
error_log("Error message");
```

**New way (Cloudinary only):**
```php
CloudinaryStorage::logError("Error message");
// Or use the logger directly
$logger = CloudinaryStorage::getLogger();
$logger->error("Error message", ['context' => 'value']);
```

### Uploading PDFs/Receipts

```php
$pdfContent = generatePDF(); // Your PDF generation code
$result = CloudinaryStorage::uploadPDF($pdfContent, 'receipt_123.pdf', 'sellapp/receipts');
if ($result['success']) {
    $pdfUrl = $result['secure_url'];
}
```

## File Storage Structure in Cloudinary

```
sellapp/
├── logs/
│   └── YYYY/MM/DD/
│       └── log_YYYY-MM-DD_HH-MM-SS_*.txt
├── backups/
│   └── company_X_YYYYMMDD_HHMMSS.zip
├── receipts/
│   └── receipt_*.pdf
├── products/
│   └── product_images/
└── uploads/
    └── user_uploads/
```

## Migration Checklist

- [x] Database session handler created
- [x] Cloudinary logging service created
- [x] Cloudinary storage helper created
- [x] Session configuration updated
- [ ] Update all file upload handlers to use CloudinaryStorage
- [ ] Update all error_log() calls to use CloudinaryStorage::logError()
- [ ] Update PDF/receipt generation to upload to Cloudinary
- [ ] Remove local file storage fallbacks
- [ ] Test all file operations

## Benefits

1. **Zero Disk Usage**: No files stored on server
2. **Better Performance**: Cloudinary CDN for fast file delivery
3. **Automatic Backups**: Cloudinary handles redundancy
4. **Scalability**: No server storage limits
5. **Uptime**: Reduced server I/O operations

## Important Notes

- **Temp files**: Temporary files created with `tmpfile()` or `php://temp` are automatically deleted after upload
- **No fallback**: System requires Cloudinary to be configured - no local storage fallback
- **Database sessions**: Sessions are stored in database, not file system
- **Log buffering**: Logs are buffered and uploaded in batches (50 logs per batch)

## Troubleshooting

### Cloudinary Not Configured
If Cloudinary credentials are missing, the system will:
- Log errors to CloudinaryStorage (which may fail silently)
- Use database sessions (always works)
- File uploads will fail (must configure Cloudinary)

### Session Issues
If database sessions fail, check:
1. Database connection is working
2. `sessions` table exists
3. Database user has CREATE TABLE permissions

### Log Upload Failures
If logs fail to upload:
- Check Cloudinary credentials
- Verify network connectivity
- Check Cloudinary service status
- Logs are buffered, so temporary failures won't lose data

