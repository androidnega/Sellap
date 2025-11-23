<?php
/**
 * Web-accessible duplicate customer cleanup
 * DELETE THIS FILE after running!
 */

// Security: Add a simple password protection
$PASSWORD = 'fix2025'; // Change this!

if (!isset($_GET['password']) || $_GET['password'] !== $PASSWORD) {
    die('Access denied. Use: ?password=fix2025');
}

require_once __DIR__ . '/../database/cleanup_duplicate_customers.php';

