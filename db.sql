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