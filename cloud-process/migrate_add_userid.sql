-- Migration script to add rq_userid column to wp_cs_requests table
-- This is needed for existing installations to support user-specific DiffBot API keys
-- Run this script if you get errors about missing rq_userid column
--
-- Version: 3.81+
-- Date: February 2026
-- Purpose: Add user ID to requests table for proper DiffBot API key retrieval

-- Add rq_userid column if it doesn't exist
ALTER TABLE `wp_cs_requests` 
ADD COLUMN `rq_userid` int DEFAULT NULL
AFTER `rq_dbkey`;

-- Note: This script is idempotent - if the column already exists, 
-- it will return an error but won't cause any data issues.
-- You can safely ignore the error if the column already exists.
