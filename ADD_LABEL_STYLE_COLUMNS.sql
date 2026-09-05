-- AMPNM: Add device label style columns
-- Run this if the columns don't already exist

ALTER TABLE `devices`
    ADD COLUMN IF NOT EXISTS `name_text_color`   VARCHAR(20) DEFAULT '#ffffff'    COMMENT 'Label text color (hex)',
    ADD COLUMN IF NOT EXISTS `name_text_bold`    TINYINT(1)  DEFAULT 0            COMMENT '1 = bold label',
    ADD COLUMN IF NOT EXISTS `name_text_italic`  TINYINT(1)  DEFAULT 0            COMMENT '1 = italic label',
    ADD COLUMN IF NOT EXISTS `name_text_vadjust` INT         DEFAULT 0            COMMENT 'Label vertical offset (-80 to 60)';

