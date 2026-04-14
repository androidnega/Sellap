<?php
/**
 * Dedicated automigrate entrypoint.
 * This bridges /automigrate directly into the main router flow.
 */

if (!isset($_SERVER['REQUEST_URI']) || trim((string)$_SERVER['REQUEST_URI']) === '') {
    $_SERVER['REQUEST_URI'] = '/automigrate';
}

// Force router target route for consistent behavior.
$_SERVER['REQUEST_URI'] = '/automigrate';

require __DIR__ . '/index.php';
