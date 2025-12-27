-- Migration to add icon_class column to devices table
-- This column stores the exact Font Awesome icon class selected for each device
-- Run this migration to fix the icon selection display issue

ALTER TABLE devices 
ADD COLUMN icon_class VARCHAR(100) DEFAULT NULL 
AFTER subchoice;

-- Optional: Add index for faster queries if needed
-- CREATE INDEX idx_devices_icon_class ON devices(icon_class);

-- Note: Existing devices will have NULL icon_class values
-- The system will fall back to using device type and subchoice for these devices
-- When you edit and save these devices, the icon_class will be populated