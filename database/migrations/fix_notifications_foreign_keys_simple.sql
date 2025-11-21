-- =====================================================
-- Quick Fix for Notifications Foreign Key Error
-- Run this in phpMyAdmin
-- =====================================================

-- Step 1: Delete orphaned notifications
DELETE n FROM notifications n
LEFT JOIN users u ON n.user_id = u.id
WHERE u.id IS NULL;

-- Step 2: Delete notifications with invalid company_id
DELETE n FROM notifications n
LEFT JOIN companies c ON n.company_id = c.id
WHERE c.id IS NULL;

-- Step 3: Drop existing foreign keys (handles both existing and non-existing constraints)
DROP PROCEDURE IF EXISTS drop_notification_fks;
DELIMITER //
CREATE PROCEDURE drop_notification_fks()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE fk_name VARCHAR(255);
    DECLARE cur CURSOR FOR 
        SELECT CONSTRAINT_NAME 
        FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'notifications'
        AND CONSTRAINT_TYPE = 'FOREIGN KEY';
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO fk_name;
        IF done THEN
            LEAVE read_loop;
        END IF;
        SET @sql = CONCAT('ALTER TABLE notifications DROP FOREIGN KEY ', fk_name);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END LOOP;
    CLOSE cur;
END//
DELIMITER ;

CALL drop_notification_fks();
DROP PROCEDURE drop_notification_fks;

-- Step 4: Add the foreign key constraints
ALTER TABLE notifications
  ADD CONSTRAINT notifications_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  ADD CONSTRAINT notifications_ibfk_2 FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE;
