-- SUROPARA DATABASE SCHEMA v10.0 (FINAL PRODUCTION)
-- FEATURES: Agents, Jackpots, Mystery Drops, Anti-Cheat, RTP Scheduler, & Full World Data

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+06:30"; -- Myanmar Standard Time

-- ==========================================
-- 1. USERS & AGENT SYSTEM
-- ==========================================

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) NOT NULL UNIQUE,
  `password_hash` varchar(255) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  
  -- Economy
  `balance` decimal(16, 2) NOT NULL DEFAULT 5000.00, -- Welcome Bonus
  `level` int(11) NOT NULL DEFAULT 1,
  `xp` bigint(20) NOT NULL DEFAULT 0,
  
  -- Inventory & Customization
  `active_pet_id` varchar(50) DEFAULT 'luna',
  `owned_islands` json DEFAULT NULL, -- IDs: [1, 2]
  
  -- Agent / Affiliate System
  `is_agent` tinyint(1) DEFAULT 0,
  `referral_code` varchar(10) UNIQUE,
  `referrer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `commission_balance` decimal(16, 2) DEFAULT 0.00,
  
  -- Trust & Security
  `is_trusted` tinyint(1) DEFAULT 0, -- For Auto-Withdrawal rules
  `status` enum('active', 'banned', 'suspended') DEFAULT 'active',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);

CREATE TABLE `user_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `token` varchar(64) NOT NULL UNIQUE,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- ==========================================
-- 2. GAME WORLD (ISLANDS & CHARACTERS)
-- ==========================================

CREATE TABLE `islands` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `desc` varchar(255) DEFAULT NULL,
  
  -- Economy Config
  `unlock_price` decimal(12, 2) DEFAULT 0.00,
  `rtp_rate` decimal(5, 2) DEFAULT 96.50, -- Base RTP
  
  -- Visuals
  `hostess_char_id` varchar(50) DEFAULT NULL,
  `atmosphere_type` enum('neon_rain', 'sunset', 'ash', 'snow', 'clouds', 'spores', 'static', 'steam', 'stars', 'none') DEFAULT 'none',
  `icon_emoji` varchar(10) DEFAULT '🏝️',
  
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
);

CREATE TABLE `characters` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `char_key` varchar(50) UNIQUE NOT NULL,
  `name` varchar(100) NOT NULL,
  `island_id` bigint(20) UNSIGNED NOT NULL,
  
  -- SVG Engine Data (JSON)
  `svg_data` longtext, 
  
  `price` decimal(12, 2) DEFAULT 0.00,
  `is_premium` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
);

-- ==========================================
-- 3. MACHINES & JACKPOTS
-- ==========================================

CREATE TABLE `machines` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `island_id` bigint(20) UNSIGNED NOT NULL,
  `machine_number` int(11) NOT NULL,
  
  -- Live Status
  `status` enum('free', 'occupied', 'maintenance') DEFAULT 'free',
  `current_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `last_played_at` timestamp NULL DEFAULT NULL,
  
  -- Heatmap Stats
  `total_laps` bigint(20) DEFAULT 0,
  `total_payout` decimal(20, 2) DEFAULT 0.00,
  
  -- Visuals
  `paint_skin` varchar(50) DEFAULT 'default',
  `sticker_char_id` varchar(50) DEFAULT NULL,
  
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `island_machine` (`island_id`, `machine_number`)
);

CREATE TABLE `global_jackpots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL, -- e.g., 'GRAND', 'MAJOR'
  `current_amount` decimal(20, 2) NOT NULL DEFAULT 0.00,
  `contribution_rate` decimal(5, 4) NOT NULL DEFAULT 0.0100, -- 1% of bets
  `must_drop_by` decimal(20, 2) DEFAULT NULL, -- Trigger threshold
  `last_won_by` varchar(50) DEFAULT NULL,
  `last_won_amount` decimal(20, 2) DEFAULT NULL,
  `last_won_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
);

-- ==========================================
-- 4. GAMEPLAY LOGS & MYSTERY BONUSES
-- ==========================================

CREATE TABLE `game_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `machine_id` bigint(20) UNSIGNED NOT NULL,
  `bet` decimal(12,2) NOT NULL,
  `win` decimal(12,2) NOT NULL,
  `result` json NOT NULL, -- ["7","7","7"]
  `xp_earned` int(11) DEFAULT 0,
  `is_gamble_win` tinyint(1) DEFAULT 0, -- Did they double it?
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user_time` (`user_id`, `created_at`),
  INDEX `idx_machine_time` (`machine_id`, `created_at`)
);

CREATE TABLE `user_items` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('freespin', 'multiplier_x2', 'cashback_10') NOT NULL,
  `amount` int(11) DEFAULT 1, -- e.g. 5 Spins
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
);

-- ==========================================
-- 5. FINANCE & ADMIN
-- ==========================================

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('GOD', 'FINANCE', 'STAFF') DEFAULT 'STAFF',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `provider_name` varchar(50) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
);

CREATE TABLE `transactions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('deposit', 'withdraw', 'bonus', 'commission') NOT NULL,
  `amount` decimal(16, 2) NOT NULL,
  `payment_method_id` int(11) DEFAULT NULL,
  
  -- Verification
  `transaction_last_digits` varchar(6) DEFAULT NULL,
  `proof_image` varchar(255) DEFAULT NULL,
  
  -- Status
  `status` enum('pending', 'approved', 'rejected') DEFAULT 'pending',
  `processed_by_admin_id` int(11) DEFAULT NULL,
  `admin_note` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);

CREATE TABLE `security_alerts` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `risk_level` enum('low', 'medium', 'high', 'critical') NOT NULL,
  `event_type` varchar(50) NOT NULL, -- e.g. 'RATE_LIMIT', 'WHALE_WIN'
  `details` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);

CREATE TABLE `marketing_events` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `title` varchar(100) NOT NULL,
    `type` enum('xp_boost','rtp_boost','deposit_bonus') NOT NULL,
    `multiplier` decimal(5,2) DEFAULT 1.00,
    `target_island_id` int(11) DEFAULT NULL,
    `start_time` datetime NOT NULL,
    `end_time` datetime NOT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    PRIMARY KEY (`id`)
);

CREATE TABLE `system_settings` (
    `key_name` varchar(50) NOT NULL,
    `value` text,
    PRIMARY KEY (`key_name`)
);

-- ==========================================
-- 6. DATA INJECTION (10 Islands & 10 Girls)
-- ==========================================

INSERT INTO `islands` (`id`, `name`, `slug`, `desc`, `unlock_price`, `atmosphere_type`, `icon_emoji`, `hostess_char_id`) VALUES
(1, 'SuroVegas', 'vegas', 'The Neon Capital of Night', 0.00, 'neon_rain', '🎰', 'luna'),
(2, 'Kohana Paradise', 'kohana', 'Eternal Summer Beach', 0.00, 'sunset', '🏖️', 'mika'),
(3, 'Inferna Atoll', 'inferna', 'Volcanic Jackpots', 50000.00, 'ash', '🌋', 'kira'),
(4, 'Noctyra Isle', 'noctyra', 'Realm of Shadows', 100000.00, 'stars', '🦇', 'yami'),
(5, 'Glacia Peaks', 'glacia', 'Frozen Fortune', 150000.00, 'snow', '❄️', 'glacia'),
(6, 'Sky Sanctum', 'sky', 'City in the Clouds', 200000.00, 'clouds', '☁️', 'sky'),
(7, 'BioDome X', 'bio', 'Overgrown Ruins', 250000.00, 'spores', '🌿', 'bio'),
(8, 'Cyber Slum', 'cyber', 'Rusty Tech District', 300000.00, 'static', '🦾', 'cyber'),
(9, 'Gold City', 'gold', 'Steampunk Gears', 400000.00, 'steam', '⚙️', 'gold'),
(10, 'Void Station', 'void', 'Deep Space Gateway', 500000.00, 'stars', '🚀', 'void');

INSERT INTO `characters` (`char_key`, `name`, `island_id`, `price`, `svg_data`) VALUES
('luna', 'Luna Aurelia', 1, 0.00, '{"colors": ["#2B2E6D", "#4B0082"]}'),
('mika', 'Mika Kohana', 2, 0.00, '{"colors": ["#FFD700", "#FF69B4"]}'),
('kira', 'Kira Ignis', 3, 50000.00, '{"colors": ["#FF4500", "#330000"]}'),
('yami', 'Yami Noctyra', 4, 100000.00, '{"colors": ["#220022", "#000000"]}'),
('glacia', 'Glacia Frost', 5, 150000.00, '{"colors": ["#E0FFFF", "#00FFFF"]}'),
('sky', 'Celestia Sky', 6, 200000.00, '{"colors": ["#FFFFFF", "#87CEEB"]}'),
('bio', 'Ivy Thorn', 7, 250000.00, '{"colors": ["#228B22", "#006400"]}'),
('cyber', 'Unit 77', 8, 300000.00, '{"colors": ["#C0C0C0", "#00FF00"]}'),
('gold', 'Penny Gear', 9, 400000.00, '{"colors": ["#8B4513", "#B8860B"]}'),
('void', 'Xenon', 10, 500000.00, '{"colors": ["#191970", "#4B0082"]}');

-- Jackpot Seed
INSERT INTO `global_jackpots` (`name`, `current_amount`, `contribution_rate`) VALUES 
('GRAND SURO JACKPOT', 5000000.00, 0.02);

-- System Config
INSERT INTO `system_settings` (`key_name`, `value`) VALUES 
('maintenance_mode', '0'),
('welcome_bonus', '5000'),
('whale_threshold', '1000000'),
('auto_approve_limit', '50000');

-- Payment Methods
INSERT INTO `payment_methods` (`provider_name`, `account_name`, `account_number`) VALUES 
('KBZPay', 'Suropara Official', '09123456789'),
('WavePay', 'Suropara Official', '09987654321');

-- Default Admin
INSERT INTO `admin_users` (`username`, `password_hash`, `role`) VALUES 
('root', '$2y$10$YourHashedPasswordHere', 'GOD');

-- ==========================================
-- 7. PROCEDURES
-- ==========================================

DELIMITER //
CREATE PROCEDURE GenerateMachines()
BEGIN
    DECLARE i INT DEFAULT 1; 
    DECLARE m INT DEFAULT 1; 
    WHILE i <= 10 DO 
        SET m = 1;
        WHILE m <= 100 DO 
            INSERT IGNORE INTO machines (island_id, machine_number, status, paint_skin)
            VALUES (i, m, 'free', 'default');
            SET m = m + 1;
        END WHILE;
        SET i = i + 1;
    END WHILE;
END //
DELIMITER ;

CALL GenerateMachines();

-- DAILY MISSIONS SYSTEM

CREATE TABLE IF NOT EXISTS `daily_missions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `action_type` VARCHAR(50) NOT NULL, -- 'spin', 'win_total', 'play_specific'
  `target_val` INT NOT NULL,
  `reward_mmk` INT NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1
);

CREATE TABLE IF NOT EXISTS `user_mission_progress` (
  `user_id` BIGINT(20) UNSIGNED NOT NULL,
  `mission_id` INT NOT NULL,
  `progress` INT DEFAULT 0,
  `is_claimed` TINYINT(1) DEFAULT 0,
  `tracking_date` DATE NOT NULL,
  PRIMARY KEY (`user_id`, `mission_id`, `tracking_date`)
);

-- Seed Default Missions
INSERT INTO `daily_missions` (`action_type`, `target_val`, `reward_mmk`, `description`) VALUES
('spin', 50, 2000, 'Spin the reels 50 times today.'),
('win_total', 50000, 5000, 'Win a total of 50,000 MMK.'),
('spin', 200, 10000, 'Hardcore: Spin 200 times today.');

ALTER TABLE `users` 
    ADD COLUMN IF NOT EXISTS `pnl_lifetime` DECIMAL(20,2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS `current_month_big_wins` INT DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `tracking_month` INT DEFAULT 0;

    ALTER TABLE `machines` 
ADD COLUMN IF NOT EXISTS `free_spins` INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS `bonus_mode` VARCHAR(20) NULL DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `bonus_spins_left` INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS `laps_since_bonus` INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS `session_spins` INT DEFAULT 0;

-- Add Session Safety & Anti-Ghosting Columns
ALTER TABLE `machines` 
ADD COLUMN IF NOT EXISTS `last_ping_at` DATETIME NULL DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `last_client_ip` VARCHAR(45) NULL DEFAULT NULL;
ADD COLUMN IF NOT EXISTS session_win_streak INT DEFAULT 0;

-- ============================================================================
-- SUROPARA DB PATCH: ADD MISSING VOLATILITY COLUMN & LEVEL CONFIGS
-- Fixes SQLSTATE[42S22]: Unknown column 'volatility'
-- Fixes SQLSTATE[42S02]: Table 'level_configs' doesn't exist
-- ============================================================================

-- 1. Add the column to the islands table safely
ALTER TABLE `islands` 
ADD COLUMN IF NOT EXISTS `volatility` ENUM('low', 'medium', 'high', 'extreme') DEFAULT 'medium';

-- 2. Apply thematic volatility settings to your specific islands
-- (This makes the UI in PlayView.js dynamic and affects the spin math)

-- Low Volatility (Frequent small wins)
UPDATE `islands` SET `volatility` = 'low' WHERE `id` IN (2, 7); -- Kohana Paradise, BioDome X

-- Medium Volatility (Standard - The default, but let's be explicit for some)
UPDATE `islands` SET `volatility` = 'medium' WHERE `id` IN (1, 5, 6, 9); -- SuroVegas, Glacia, Sky, Gold City

-- High Volatility (Less frequent, but bigger payouts)
UPDATE `islands` SET `volatility` = 'high' WHERE `id` IN (3, 4, 8); -- Inferna Atoll, Noctyra Isle, Cyber Slum

-- Extreme Volatility (Very rare hits, massive jackpots)
UPDATE `islands` SET `volatility` = 'extreme' WHERE `id` = 10; -- Void Station

-- ==========================================
-- 3. LEVELING SYSTEM (Fix for missing table)
-- ==========================================

CREATE TABLE IF NOT EXISTS `level_configs` (
  `level` int(11) NOT NULL,
  `xp_required` bigint(20) NOT NULL,
  `reward_mmk` decimal(12, 2) DEFAULT 0.00,
  PRIMARY KEY (`level`)
);

-- SEED DATA (Only insert if empty to avoid duplicates on re-run)
INSERT IGNORE INTO `level_configs` (`level`, `xp_required`, `reward_mmk`) VALUES 
(1, 0, 0), 
(2, 100, 5000), 
(3, 500, 10000), 
(4, 2000, 50000),
(5, 5000, 100000),
(6, 10000, 250000),
(7, 25000, 500000),
(8, 50000, 1000000),
(9, 100000, 2000000),
(10, 200000, 50000000);

-- ============================================================================
-- SUROPARA V2/V3 - MACHINE NOTEBOOK SYSTEM
-- Run this in production to establish the machine maintenance logging table
-- ============================================================================

CREATE TABLE IF NOT EXISTS `machine_notes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `machine_id` BIGINT(20) UNSIGNED NOT NULL,
    `admin_id` INT(11) NOT NULL,
    `note` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Strict Relational Integrity (If a machine or admin is deleted, clean up notes)
    CONSTRAINT `fk_machine_notes_mid` FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_machine_notes_aid` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add index for fast querying when an admin opens a specific machine's notebook
CREATE INDEX `idx_machine_notes_mid` ON `machine_notes` (`machine_id`);

-- ============================================================================
-- SUROPARA V3: DATABASE-DRIVEN REEL SPAWN RATES
-- ============================================================================

CREATE TABLE IF NOT EXISTS `reel_spawn_rates` (
    `island_id` BIGINT(20) UNSIGNED NOT NULL,
    `reel_index` INT(11) NOT NULL, -- 1, 2, or 3
    `sym_1` INT NOT NULL DEFAULT 10,   -- 7 (Grand Jackpot)
    `sym_2` INT NOT NULL DEFAULT 40,   -- Character
    `sym_3` INT NOT NULL DEFAULT 100,  -- Bar
    `sym_4` INT NOT NULL DEFAULT 200,  -- Bell
    `sym_5` INT NOT NULL DEFAULT 200,  -- Melon
    `sym_6` INT NOT NULL DEFAULT 250,  -- Cherry
    `sym_7` INT NOT NULL DEFAULT 200,  -- Replay
    PRIMARY KEY (`island_id`, `reel_index`),
    CONSTRAINT `fk_spawn_island` FOREIGN KEY (`island_id`) REFERENCES `islands` (`id`) ON DELETE CASCADE
);

-- Seed Initial Data for the 5 V3 Islands (Reels 1, 2, and 3)
-- Adjust these numbers in the Admin Portal later to tweak the exact feel of each island.
INSERT IGNORE INTO `reel_spawn_rates` (`island_id`, `reel_index`, `sym_1`, `sym_2`, `sym_3`, `sym_4`, `sym_5`, `sym_6`, `sym_7`) VALUES
(1, 1, 10, 40, 100, 200, 200, 250, 200), (1, 2, 5, 30, 80, 220, 220, 245, 200), (1, 3, 2, 20, 60, 250, 250, 218, 200),
(2, 1, 15, 45, 110, 190, 190, 260, 190), (2, 2, 8, 35, 90, 210, 210, 250, 190), (2, 3, 4, 25, 70, 240, 240, 220, 190),
(3, 1, 8,  35, 90,  210, 210, 240, 210), (3, 2, 4, 25, 70, 230, 230, 230, 210), (3, 3, 1, 15, 50, 260, 260, 200, 210),
(4, 1, 12, 42, 105, 195, 195, 255, 195), (4, 2, 6, 32, 85, 215, 215, 248, 195), (4, 3, 3, 22, 65, 245, 245, 215, 195),
(5, 1, 10, 40, 100, 200, 200, 250, 200), (5, 2, 5, 30, 80, 220, 220, 245, 200), (5, 3, 2, 20, 60, 250, 250, 218, 200);

-- ============================================================================
-- SUROPARA V3: INDEPENDENT ESCALATING GRAND JACKPOTS
-- ============================================================================

-- 1. Add the necessary control columns to the global_jackpots table
ALTER TABLE `global_jackpots` 
ADD COLUMN IF NOT EXISTS `island_id` INT DEFAULT NULL UNIQUE,
ADD COLUMN IF NOT EXISTS `base_seed` DECIMAL(20,2) DEFAULT 3000000.00,
ADD COLUMN IF NOT EXISTS `trigger_amount` DECIMAL(20,2) DEFAULT 3600000.00,
ADD COLUMN IF NOT EXISTS `max_amount` DECIMAL(20,2) DEFAULT 7200000.00;

-- 2. Clear old generic jackpots (Optional, but ensures a clean V3 start)
TRUNCATE TABLE `global_jackpots`;

-- 3. Insert the 5 Island-Specific Jackpots with escalating thresholds
INSERT IGNORE INTO `global_jackpots` 
(`name`, `island_id`, `current_amount`, `contribution_rate`, `base_seed`, `trigger_amount`, `max_amount`) 
VALUES
('Kyoto Zen GJP',       1, 3000000.00,  0.010, 3000000.00,  3600000.00,  7200000.00),
('Okinawa Tropic GJP',  2, 4000000.00,  0.015, 4000000.00,  4500000.00,  8100000.00),
('Osaka Neon GJP',      3, 5000000.00,  0.020, 5000000.00,  6000000.00, 10000000.00),
('Tokyo Cyber GJP',     4, 7500000.00,  0.025, 7500000.00,  9000000.00, 15000000.00),
('Ginza Gold GJP',      5, 10000000.00, 0.030, 10000000.00, 12000000.00, 20000000.00);

-- ============================================================================
-- SUROPARA V5.1 - DYNAMIC SYMBOL PAYOUTS
-- Decouples win multipliers from PHP and puts them in the DB for Admin control
-- ============================================================================

CREATE TABLE IF NOT EXISTS `island_symbol_payouts` (
    `island_id` BIGINT(20) UNSIGNED NOT NULL,
    `sym_1_mult` DECIMAL(8,2) NOT NULL DEFAULT 100.00,  -- GJP / 7s
    `sym_2_mult` DECIMAL(8,2) NOT NULL DEFAULT 20.00,   -- Character (High Tier)
    `sym_3_mult` DECIMAL(8,2) NOT NULL DEFAULT 10.00,   -- BAR (Bonus Trigger)
    `sym_4_mult` DECIMAL(8,2) NOT NULL DEFAULT 10.00,   -- Bell
    `sym_5_mult` DECIMAL(8,2) NOT NULL DEFAULT 15.00,   -- Melon
    `sym_6_mult` DECIMAL(8,2) NOT NULL DEFAULT 2.00,    -- Cherry (Bleed Filler)
    `sym_7_mult` DECIMAL(8,2) NOT NULL DEFAULT 0.00,    -- Replay (Free Spin)
    PRIMARY KEY (`island_id`),
    CONSTRAINT `fk_payouts_island` FOREIGN KEY (`island_id`) REFERENCES `islands` (`id`) ON DELETE CASCADE
);

-- Seed defaults for the 5 V3 Islands
INSERT IGNORE INTO `island_symbol_payouts` (`island_id`) VALUES (1), (2), (3), (4), (5);