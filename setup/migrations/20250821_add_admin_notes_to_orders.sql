-- Migration: Add admin_notes column to orders table
-- Date: 2025-08-21
-- Description: Add admin_notes column to store payment confirmation and rejection notes

ALTER TABLE `orders` ADD COLUMN `admin_notes` TEXT DEFAULT NULL AFTER `notes`;
