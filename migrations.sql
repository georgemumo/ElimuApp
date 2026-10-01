-- ============================================================================
-- MIGRATION: Add missing columns to existing tables
-- ============================================================================
-- Run this script to update your existing database with the new columns

-- 1. Add missing columns to `parents` table
ALTER TABLE `parents` ADD COLUMN `phone` VARCHAR(15) NULL AFTER `full_name`;
ALTER TABLE `parents` ADD COLUMN `profile_pic` VARCHAR(255) NULL AFTER `relationship`;

-- 2. Add missing columns to `students` table
ALTER TABLE `students` ADD COLUMN `profile_pic` VARCHAR(255) NULL AFTER `status`;

-- 3. Add missing columns to `teachers` table
ALTER TABLE `teachers` ADD COLUMN `phone` VARCHAR(15) NULL AFTER `phone_number`;
ALTER TABLE `teachers` ADD COLUMN `profile_pic` VARCHAR(255) NULL AFTER `specialization`;

-- ============================================================================
-- VERIFICATION: Check the updated table structures
-- ============================================================================
-- Uncomment to verify:
-- DESCRIBE `parents`;
-- DESCRIBE `students`;
-- DESCRIBE `teachers`;
