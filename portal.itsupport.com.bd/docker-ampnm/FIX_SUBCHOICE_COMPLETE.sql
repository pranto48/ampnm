-- ============================================================
-- AMPNM Icon System - Complete Database Fix
-- Date: December 15, 2025
-- Purpose: Add subchoice column to devices table for icon variant storage
-- ============================================================

-- STEP 1: Add subchoice column
-- This adds the column if it doesn't exist
ALTER TABLE devices 
ADD COLUMN subchoice TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER type;

-- STEP 2: Update existing devices to have default subchoice
UPDATE devices 
SET subchoice = 0 
WHERE subchoice IS NULL OR subchoice = 0;

-- STEP 3: Verify the column was added
SELECT 
    'Column verification:' AS step,
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'devices'
  AND COLUMN_NAME IN ('id', 'name', 'type', 'subchoice', 'icon_size', 'icon_url');

-- STEP 4: Show sample data
SELECT 
    'Sample data:' AS step,
    id, name, type, subchoice, icon_size
FROM devices
LIMIT 5;

-- STEP 5: Count devices
SELECT 
    'Device count:' AS step,
    COUNT(*) as total_devices,
    SUM(CASE WHEN subchoice IS NOT NULL THEN 1 ELSE 0 END) as with_subchoice,
    SUM(CASE WHEN subchoice IS NULL THEN 1 ELSE 0 END) as null_subchoice
FROM devices;

-- ============================================================
-- SUCCESS MESSAGE
-- ============================================================
SELECT '✅ Database migration complete! subchoice column added to devices table.' AS status;
SELECT '  Next steps:' AS info;
SELECT '   1. Pull latest code from GitHub' AS step_1;
SELECT '   2. Restart Docker container' AS step_2;
SELECT '   3. Hard refresh browser (Ctrl+F5)' AS step_3;
SELECT '   4. Test icon changes on map' AS step_4;
