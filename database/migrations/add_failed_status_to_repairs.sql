-- Migration: Add 'failed' status to repairs_new table
-- This allows technicians to mark repairs as failed

ALTER TABLE repairs_new 
MODIFY COLUMN status ENUM('pending','in_progress','completed','delivered','cancelled','failed') DEFAULT 'pending';

