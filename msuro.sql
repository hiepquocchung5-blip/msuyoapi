-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jan 29, 2026 at 06:35 AM
-- Server version: 10.6.24-MariaDB-cll-lve
-- PHP Version: 8.3.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `msuro`
--

DELIMITER $$
--
-- Procedures
--
$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('GOD','FINANCE','STAFF') DEFAULT 'STAFF',
  `is_active` tinyint(1) DEFAULT 1,
  `is_online` tinyint(1) DEFAULT 0,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password_hash`, `role`, `is_active`, `is_online`, `last_login`, `created_at`) VALUES
(1, 'surogodadmin', '$2y$10$fHKLpsXM2jWHeSemYdoINueDxWG5GsfEz7dFEskJAsErd8DrR7QHG', 'GOD', 1, 0, NULL, '2026-01-28 05:16:15'),
(2, 'finance_kbz', '$2y$10$YourHashedPasswordHere', 'FINANCE', 1, 1, NULL, '2026-01-28 05:16:15'),
(3, 'finance_wave', '$2y$10$YourHashedPasswordHere', 'FINANCE', 1, 1, NULL, '2026-01-28 05:16:15'),
(4, 'agent_mandalay', '$2y$10$YourHashedPasswordHere', 'FINANCE', 1, 0, NULL, '2026-01-28 05:16:15'),
(5, 'agent_yangon', '$2y$10$YourHashedPasswordHere', 'FINANCE', 1, 0, NULL, '2026-01-28 05:16:15');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `target_table` varchar(50) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `characters`
--

CREATE TABLE `characters` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `char_key` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `island_id` bigint(20) UNSIGNED NOT NULL,
  `svg_data` longtext DEFAULT NULL,
  `price` decimal(12,2) DEFAULT 0.00,
  `is_premium` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `characters`
--

INSERT INTO `characters` (`id`, `char_key`, `name`, `island_id`, `svg_data`, `price`, `is_premium`) VALUES
(1, 'luna', 'Luna Aurelia', 1, '{\"rarity\":\"R\"}', 0.00, 0),
(2, 'mika', 'Mika Kohana', 2, '{\"rarity\":\"R\"}', 0.00, 0),
(3, 'kira', 'Kira Ignis', 3, '{\"rarity\":\"SR\"}', 50000.00, 1),
(4, 'yami', 'Yami Noctyra', 4, '{\"rarity\":\"SSR\"}', 100000.00, 1),
(5, 'glacia', 'Glacia Frost', 5, '{\"rarity\":\"SR\"}', 150000.00, 1),
(6, 'sky', 'Celestia Sky', 6, '{\"rarity\":\"SSR\"}', 200000.00, 1),
(7, 'bio', 'Ivy Thorn', 7, '{\"rarity\":\"SR\"}', 250000.00, 1),
(8, 'cyber', 'Unit V-77', 8, '{\"rarity\":\"UR\"}', 300000.00, 1),
(9, 'gold', 'Penny Gear', 9, '{\"rarity\":\"SR\"}', 400000.00, 1),
(10, 'void', 'Xenon', 10, '{\"rarity\":\"UR\"}', 500000.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `message` text NOT NULL,
  `type` enum('user','system','win','jackpot') DEFAULT 'user',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `is_pinned` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daily_rewards`
--

CREATE TABLE `daily_rewards` (
  `user_id` bigint(20) NOT NULL,
  `streak_days` int(11) DEFAULT 0,
  `last_claim_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `game_logs`
--

CREATE TABLE `game_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `machine_id` bigint(20) UNSIGNED DEFAULT NULL,
  `bet` decimal(12,2) NOT NULL,
  `win` decimal(12,2) NOT NULL,
  `result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`result`)),
  `xp_earned` int(11) DEFAULT 0,
  `is_gamble_win` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `global_jackpots`
--

CREATE TABLE `global_jackpots` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `current_amount` decimal(20,2) NOT NULL DEFAULT 5000000.00,
  `contribution_rate` decimal(5,4) NOT NULL DEFAULT 0.0100,
  `must_drop_by` decimal(20,2) DEFAULT NULL,
  `last_won_by` varchar(50) DEFAULT NULL,
  `last_won_amount` decimal(20,2) DEFAULT NULL,
  `last_won_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `global_jackpots`
--

INSERT INTO `global_jackpots` (`id`, `name`, `current_amount`, `contribution_rate`, `must_drop_by`, `last_won_by`, `last_won_amount`, `last_won_at`) VALUES
(1, 'GRAND SURO JACKPOT', 5000000.00, 0.0100, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `islands`
--

CREATE TABLE `islands` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `desc` varchar(255) DEFAULT NULL,
  `unlock_price` decimal(12,2) DEFAULT 0.00,
  `rtp_rate` decimal(5,2) DEFAULT 96.50,
  `hostess_char_id` varchar(50) DEFAULT NULL,
  `atmosphere_type` enum('neon_rain','sunset','ash','snow','clouds','spores','static','steam','stars','none') DEFAULT 'none',
  `icon_emoji` varchar(10) DEFAULT '?️',
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `islands`
--

INSERT INTO `islands` (`id`, `name`, `slug`, `desc`, `unlock_price`, `rtp_rate`, `hostess_char_id`, `atmosphere_type`, `icon_emoji`, `is_active`) VALUES
(1, 'SuroVegas', 'vegas', NULL, 0.00, 96.50, 'luna', 'neon_rain', '🎰', 1),
(2, 'Kohana Paradise', 'kohana', NULL, 0.00, 94.00, 'mika', 'sunset', '🏖️', 1),
(3, 'Inferna Atoll', 'inferna', NULL, 50000.00, 92.00, 'kira', 'ash', '🌋', 1),
(4, 'Noctyra Isle', 'noctyra', NULL, 100000.00, 98.00, 'yami', 'stars', '🦇', 1),
(5, 'Glacia Peaks', 'glacia', NULL, 150000.00, 95.00, 'glacia', 'snow', '❄️', 1),
(6, 'Sky Sanctum', 'sky', NULL, 200000.00, 97.00, 'sky', 'clouds', '☁️', 1),
(7, 'BioDome X', 'bio', NULL, 250000.00, 93.00, 'bio', 'spores', '🌿', 1),
(8, 'Cyber Slum', 'cyber', NULL, 300000.00, 96.00, 'cyber', 'static', '🦾', 1),
(9, 'Gold City', 'gold', NULL, 400000.00, 95.50, 'gold', 'steam', '⚙️', 1),
(10, 'Void Station', 'void', NULL, 500000.00, 99.00, 'void', 'stars', '🚀', 1);

-- --------------------------------------------------------

--
-- Table structure for table `level_rewards`
--

CREATE TABLE `level_rewards` (
  `level` int(11) NOT NULL,
  `xp_required` bigint(20) NOT NULL,
  `reward_mmk` decimal(12,2) DEFAULT 0.00,
  `unlock_item` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `machines`
--

CREATE TABLE `machines` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `island_id` bigint(20) UNSIGNED NOT NULL,
  `machine_number` int(11) NOT NULL,
  `status` enum('free','occupied','maintenance') DEFAULT 'free',
  `current_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `last_played_at` timestamp NULL DEFAULT NULL,
  `total_laps` bigint(20) DEFAULT 0,
  `total_payout` decimal(20,2) DEFAULT 0.00,
  `paint_skin` varchar(50) DEFAULT 'default',
  `sticker_char_id` varchar(50) DEFAULT NULL,
  `jackpot_seed` decimal(12,2) DEFAULT 0.00,
  `session_token` varchar(64) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `machines`
--

INSERT INTO `machines` (`id`, `island_id`, `machine_number`, `status`, `current_user_id`, `last_played_at`, `total_laps`, `total_payout`, `paint_skin`, `sticker_char_id`, `jackpot_seed`, `session_token`, `updated_at`) VALUES
(1, 1, 1, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(2, 1, 2, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(3, 1, 3, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(4, 1, 4, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(5, 1, 5, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(6, 1, 6, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(7, 1, 7, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(8, 1, 8, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(9, 1, 9, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(10, 1, 10, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(11, 1, 11, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(12, 1, 12, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(13, 1, 13, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(14, 1, 14, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(15, 1, 15, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(16, 1, 16, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(17, 1, 17, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(18, 1, 18, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(19, 1, 19, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(20, 1, 20, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(21, 1, 21, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(22, 1, 22, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(23, 1, 23, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(24, 1, 24, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(25, 1, 25, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(26, 1, 26, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(27, 1, 27, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(28, 1, 28, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(29, 1, 29, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(30, 1, 30, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(31, 1, 31, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(32, 1, 32, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(33, 1, 33, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(34, 1, 34, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(35, 1, 35, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(36, 1, 36, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(37, 1, 37, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(38, 1, 38, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(39, 1, 39, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(40, 1, 40, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(41, 1, 41, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(42, 1, 42, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(43, 1, 43, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(44, 1, 44, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(45, 1, 45, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(46, 1, 46, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(47, 1, 47, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(48, 1, 48, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(49, 1, 49, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(50, 1, 50, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(51, 1, 51, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(52, 1, 52, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(53, 1, 53, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(54, 1, 54, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(55, 1, 55, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(56, 1, 56, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(57, 1, 57, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(58, 1, 58, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(59, 1, 59, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(60, 1, 60, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(61, 1, 61, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(62, 1, 62, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(63, 1, 63, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(64, 1, 64, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(65, 1, 65, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(66, 1, 66, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(67, 1, 67, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(68, 1, 68, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(69, 1, 69, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(70, 1, 70, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(71, 1, 71, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(72, 1, 72, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(73, 1, 73, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(74, 1, 74, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(75, 1, 75, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(76, 1, 76, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(77, 1, 77, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(78, 1, 78, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(79, 1, 79, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(80, 1, 80, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(81, 1, 81, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(82, 1, 82, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(83, 1, 83, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(84, 1, 84, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(85, 1, 85, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(86, 1, 86, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(87, 1, 87, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(88, 1, 88, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(89, 1, 89, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(90, 1, 90, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(91, 1, 91, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(92, 1, 92, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(93, 1, 93, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(94, 1, 94, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(95, 1, 95, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(96, 1, 96, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(97, 1, 97, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(98, 1, 98, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(99, 1, 99, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(100, 1, 100, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(101, 2, 1, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(102, 2, 2, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(103, 2, 3, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(104, 2, 4, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(105, 2, 5, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(106, 2, 6, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(107, 2, 7, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(108, 2, 8, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(109, 2, 9, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(110, 2, 10, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(111, 2, 11, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(112, 2, 12, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(113, 2, 13, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(114, 2, 14, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(115, 2, 15, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(116, 2, 16, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(117, 2, 17, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(118, 2, 18, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(119, 2, 19, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(120, 2, 20, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(121, 2, 21, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(122, 2, 22, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(123, 2, 23, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(124, 2, 24, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(125, 2, 25, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(126, 2, 26, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(127, 2, 27, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(128, 2, 28, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(129, 2, 29, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(130, 2, 30, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(131, 2, 31, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(132, 2, 32, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(133, 2, 33, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(134, 2, 34, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(135, 2, 35, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(136, 2, 36, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(137, 2, 37, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(138, 2, 38, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(139, 2, 39, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(140, 2, 40, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(141, 2, 41, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(142, 2, 42, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(143, 2, 43, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(144, 2, 44, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(145, 2, 45, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(146, 2, 46, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(147, 2, 47, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(148, 2, 48, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(149, 2, 49, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(150, 2, 50, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(151, 2, 51, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(152, 2, 52, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(153, 2, 53, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(154, 2, 54, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(155, 2, 55, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(156, 2, 56, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(157, 2, 57, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(158, 2, 58, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(159, 2, 59, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(160, 2, 60, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(161, 2, 61, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(162, 2, 62, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(163, 2, 63, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(164, 2, 64, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(165, 2, 65, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(166, 2, 66, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(167, 2, 67, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(168, 2, 68, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(169, 2, 69, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(170, 2, 70, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(171, 2, 71, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(172, 2, 72, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(173, 2, 73, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(174, 2, 74, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(175, 2, 75, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(176, 2, 76, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(177, 2, 77, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(178, 2, 78, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(179, 2, 79, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(180, 2, 80, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(181, 2, 81, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(182, 2, 82, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(183, 2, 83, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(184, 2, 84, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(185, 2, 85, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(186, 2, 86, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(187, 2, 87, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(188, 2, 88, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(189, 2, 89, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(190, 2, 90, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(191, 2, 91, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(192, 2, 92, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(193, 2, 93, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(194, 2, 94, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(195, 2, 95, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(196, 2, 96, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(197, 2, 97, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(198, 2, 98, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(199, 2, 99, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(200, 2, 100, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(201, 3, 1, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(202, 3, 2, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(203, 3, 3, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(204, 3, 4, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(205, 3, 5, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(206, 3, 6, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(207, 3, 7, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(208, 3, 8, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(209, 3, 9, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(210, 3, 10, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(211, 3, 11, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(212, 3, 12, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(213, 3, 13, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(214, 3, 14, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(215, 3, 15, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(216, 3, 16, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(217, 3, 17, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(218, 3, 18, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(219, 3, 19, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(220, 3, 20, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(221, 3, 21, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(222, 3, 22, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(223, 3, 23, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(224, 3, 24, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(225, 3, 25, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(226, 3, 26, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(227, 3, 27, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(228, 3, 28, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(229, 3, 29, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(230, 3, 30, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(231, 3, 31, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(232, 3, 32, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(233, 3, 33, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(234, 3, 34, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(235, 3, 35, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(236, 3, 36, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(237, 3, 37, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(238, 3, 38, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(239, 3, 39, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(240, 3, 40, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(241, 3, 41, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(242, 3, 42, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(243, 3, 43, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(244, 3, 44, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(245, 3, 45, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(246, 3, 46, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(247, 3, 47, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(248, 3, 48, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(249, 3, 49, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(250, 3, 50, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(251, 3, 51, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(252, 3, 52, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(253, 3, 53, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(254, 3, 54, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(255, 3, 55, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(256, 3, 56, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(257, 3, 57, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(258, 3, 58, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(259, 3, 59, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(260, 3, 60, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(261, 3, 61, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(262, 3, 62, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(263, 3, 63, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(264, 3, 64, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(265, 3, 65, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(266, 3, 66, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(267, 3, 67, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(268, 3, 68, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(269, 3, 69, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(270, 3, 70, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(271, 3, 71, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(272, 3, 72, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(273, 3, 73, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(274, 3, 74, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(275, 3, 75, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(276, 3, 76, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(277, 3, 77, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(278, 3, 78, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(279, 3, 79, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(280, 3, 80, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(281, 3, 81, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(282, 3, 82, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(283, 3, 83, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(284, 3, 84, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(285, 3, 85, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(286, 3, 86, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(287, 3, 87, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(288, 3, 88, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(289, 3, 89, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(290, 3, 90, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(291, 3, 91, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(292, 3, 92, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(293, 3, 93, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(294, 3, 94, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(295, 3, 95, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(296, 3, 96, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(297, 3, 97, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(298, 3, 98, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(299, 3, 99, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(300, 3, 100, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(301, 4, 1, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(302, 4, 2, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(303, 4, 3, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(304, 4, 4, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(305, 4, 5, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(306, 4, 6, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(307, 4, 7, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(308, 4, 8, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(309, 4, 9, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(310, 4, 10, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(311, 4, 11, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(312, 4, 12, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(313, 4, 13, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(314, 4, 14, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(315, 4, 15, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(316, 4, 16, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(317, 4, 17, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(318, 4, 18, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(319, 4, 19, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(320, 4, 20, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(321, 4, 21, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(322, 4, 22, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(323, 4, 23, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(324, 4, 24, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(325, 4, 25, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(326, 4, 26, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(327, 4, 27, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(328, 4, 28, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(329, 4, 29, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(330, 4, 30, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(331, 4, 31, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(332, 4, 32, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(333, 4, 33, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(334, 4, 34, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(335, 4, 35, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(336, 4, 36, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(337, 4, 37, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(338, 4, 38, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(339, 4, 39, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(340, 4, 40, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(341, 4, 41, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(342, 4, 42, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(343, 4, 43, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(344, 4, 44, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(345, 4, 45, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(346, 4, 46, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(347, 4, 47, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(348, 4, 48, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(349, 4, 49, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(350, 4, 50, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(351, 4, 51, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(352, 4, 52, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(353, 4, 53, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(354, 4, 54, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(355, 4, 55, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(356, 4, 56, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(357, 4, 57, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(358, 4, 58, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(359, 4, 59, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(360, 4, 60, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(361, 4, 61, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(362, 4, 62, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(363, 4, 63, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(364, 4, 64, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(365, 4, 65, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(366, 4, 66, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(367, 4, 67, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(368, 4, 68, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(369, 4, 69, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(370, 4, 70, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(371, 4, 71, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(372, 4, 72, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(373, 4, 73, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(374, 4, 74, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(375, 4, 75, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(376, 4, 76, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(377, 4, 77, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(378, 4, 78, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(379, 4, 79, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(380, 4, 80, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(381, 4, 81, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(382, 4, 82, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(383, 4, 83, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(384, 4, 84, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(385, 4, 85, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(386, 4, 86, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(387, 4, 87, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(388, 4, 88, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(389, 4, 89, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(390, 4, 90, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(391, 4, 91, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(392, 4, 92, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(393, 4, 93, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(394, 4, 94, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(395, 4, 95, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(396, 4, 96, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(397, 4, 97, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(398, 4, 98, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(399, 4, 99, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(400, 4, 100, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(401, 5, 1, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(402, 5, 2, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(403, 5, 3, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(404, 5, 4, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(405, 5, 5, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(406, 5, 6, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(407, 5, 7, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(408, 5, 8, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(409, 5, 9, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(410, 5, 10, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(411, 5, 11, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(412, 5, 12, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(413, 5, 13, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(414, 5, 14, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(415, 5, 15, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(416, 5, 16, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(417, 5, 17, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(418, 5, 18, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(419, 5, 19, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(420, 5, 20, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(421, 5, 21, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(422, 5, 22, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(423, 5, 23, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(424, 5, 24, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(425, 5, 25, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(426, 5, 26, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(427, 5, 27, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(428, 5, 28, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(429, 5, 29, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(430, 5, 30, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(431, 5, 31, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(432, 5, 32, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(433, 5, 33, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(434, 5, 34, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(435, 5, 35, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(436, 5, 36, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(437, 5, 37, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(438, 5, 38, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(439, 5, 39, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(440, 5, 40, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(441, 5, 41, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(442, 5, 42, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(443, 5, 43, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(444, 5, 44, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(445, 5, 45, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(446, 5, 46, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(447, 5, 47, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(448, 5, 48, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(449, 5, 49, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(450, 5, 50, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(451, 5, 51, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(452, 5, 52, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(453, 5, 53, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(454, 5, 54, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(455, 5, 55, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(456, 5, 56, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(457, 5, 57, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(458, 5, 58, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(459, 5, 59, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(460, 5, 60, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(461, 5, 61, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(462, 5, 62, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(463, 5, 63, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(464, 5, 64, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(465, 5, 65, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(466, 5, 66, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(467, 5, 67, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(468, 5, 68, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(469, 5, 69, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(470, 5, 70, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(471, 5, 71, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(472, 5, 72, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(473, 5, 73, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(474, 5, 74, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(475, 5, 75, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(476, 5, 76, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(477, 5, 77, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(478, 5, 78, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(479, 5, 79, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(480, 5, 80, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(481, 5, 81, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(482, 5, 82, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:15'),
(483, 5, 83, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(484, 5, 84, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(485, 5, 85, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(486, 5, 86, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(487, 5, 87, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(488, 5, 88, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(489, 5, 89, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(490, 5, 90, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(491, 5, 91, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(492, 5, 92, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(493, 5, 93, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(494, 5, 94, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(495, 5, 95, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(496, 5, 96, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(497, 5, 97, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(498, 5, 98, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(499, 5, 99, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(500, 5, 100, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(501, 6, 1, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(502, 6, 2, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(503, 6, 3, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(504, 6, 4, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(505, 6, 5, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(506, 6, 6, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(507, 6, 7, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(508, 6, 8, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(509, 6, 9, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(510, 6, 10, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(511, 6, 11, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(512, 6, 12, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(513, 6, 13, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(514, 6, 14, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(515, 6, 15, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(516, 6, 16, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(517, 6, 17, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(518, 6, 18, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(519, 6, 19, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(520, 6, 20, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(521, 6, 21, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(522, 6, 22, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(523, 6, 23, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(524, 6, 24, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(525, 6, 25, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(526, 6, 26, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(527, 6, 27, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(528, 6, 28, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(529, 6, 29, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(530, 6, 30, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(531, 6, 31, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(532, 6, 32, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(533, 6, 33, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(534, 6, 34, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(535, 6, 35, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(536, 6, 36, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16');
INSERT INTO `machines` (`id`, `island_id`, `machine_number`, `status`, `current_user_id`, `last_played_at`, `total_laps`, `total_payout`, `paint_skin`, `sticker_char_id`, `jackpot_seed`, `session_token`, `updated_at`) VALUES
(537, 6, 37, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(538, 6, 38, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(539, 6, 39, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(540, 6, 40, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(541, 6, 41, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(542, 6, 42, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(543, 6, 43, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(544, 6, 44, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(545, 6, 45, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(546, 6, 46, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(547, 6, 47, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(548, 6, 48, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(549, 6, 49, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(550, 6, 50, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(551, 6, 51, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(552, 6, 52, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(553, 6, 53, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(554, 6, 54, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(555, 6, 55, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(556, 6, 56, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(557, 6, 57, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(558, 6, 58, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(559, 6, 59, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(560, 6, 60, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(561, 6, 61, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(562, 6, 62, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(563, 6, 63, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(564, 6, 64, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(565, 6, 65, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(566, 6, 66, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(567, 6, 67, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(568, 6, 68, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(569, 6, 69, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(570, 6, 70, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(571, 6, 71, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(572, 6, 72, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(573, 6, 73, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(574, 6, 74, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(575, 6, 75, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(576, 6, 76, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(577, 6, 77, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(578, 6, 78, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(579, 6, 79, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(580, 6, 80, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(581, 6, 81, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(582, 6, 82, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(583, 6, 83, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(584, 6, 84, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(585, 6, 85, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(586, 6, 86, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(587, 6, 87, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(588, 6, 88, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(589, 6, 89, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(590, 6, 90, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(591, 6, 91, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(592, 6, 92, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(593, 6, 93, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(594, 6, 94, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(595, 6, 95, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(596, 6, 96, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(597, 6, 97, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(598, 6, 98, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(599, 6, 99, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(600, 6, 100, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(601, 7, 1, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(602, 7, 2, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(603, 7, 3, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(604, 7, 4, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(605, 7, 5, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(606, 7, 6, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(607, 7, 7, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(608, 7, 8, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(609, 7, 9, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(610, 7, 10, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(611, 7, 11, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(612, 7, 12, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(613, 7, 13, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(614, 7, 14, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(615, 7, 15, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(616, 7, 16, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(617, 7, 17, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(618, 7, 18, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(619, 7, 19, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(620, 7, 20, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(621, 7, 21, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(622, 7, 22, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(623, 7, 23, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(624, 7, 24, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(625, 7, 25, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(626, 7, 26, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(627, 7, 27, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(628, 7, 28, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(629, 7, 29, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(630, 7, 30, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(631, 7, 31, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(632, 7, 32, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(633, 7, 33, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(634, 7, 34, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(635, 7, 35, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(636, 7, 36, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(637, 7, 37, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(638, 7, 38, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(639, 7, 39, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(640, 7, 40, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(641, 7, 41, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(642, 7, 42, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(643, 7, 43, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(644, 7, 44, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(645, 7, 45, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(646, 7, 46, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(647, 7, 47, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(648, 7, 48, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(649, 7, 49, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(650, 7, 50, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(651, 7, 51, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(652, 7, 52, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(653, 7, 53, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(654, 7, 54, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(655, 7, 55, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(656, 7, 56, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(657, 7, 57, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(658, 7, 58, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(659, 7, 59, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(660, 7, 60, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(661, 7, 61, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(662, 7, 62, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(663, 7, 63, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(664, 7, 64, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(665, 7, 65, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(666, 7, 66, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(667, 7, 67, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(668, 7, 68, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(669, 7, 69, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(670, 7, 70, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(671, 7, 71, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(672, 7, 72, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(673, 7, 73, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(674, 7, 74, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(675, 7, 75, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(676, 7, 76, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(677, 7, 77, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(678, 7, 78, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(679, 7, 79, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(680, 7, 80, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(681, 7, 81, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(682, 7, 82, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(683, 7, 83, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(684, 7, 84, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(685, 7, 85, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(686, 7, 86, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(687, 7, 87, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(688, 7, 88, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(689, 7, 89, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(690, 7, 90, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(691, 7, 91, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(692, 7, 92, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(693, 7, 93, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(694, 7, 94, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(695, 7, 95, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(696, 7, 96, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(697, 7, 97, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(698, 7, 98, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(699, 7, 99, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(700, 7, 100, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(701, 8, 1, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(702, 8, 2, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(703, 8, 3, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(704, 8, 4, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(705, 8, 5, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(706, 8, 6, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(707, 8, 7, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(708, 8, 8, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(709, 8, 9, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(710, 8, 10, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(711, 8, 11, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(712, 8, 12, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(713, 8, 13, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(714, 8, 14, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(715, 8, 15, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(716, 8, 16, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(717, 8, 17, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(718, 8, 18, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(719, 8, 19, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(720, 8, 20, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(721, 8, 21, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(722, 8, 22, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(723, 8, 23, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(724, 8, 24, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(725, 8, 25, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(726, 8, 26, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(727, 8, 27, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(728, 8, 28, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(729, 8, 29, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(730, 8, 30, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(731, 8, 31, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(732, 8, 32, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(733, 8, 33, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(734, 8, 34, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(735, 8, 35, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(736, 8, 36, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(737, 8, 37, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(738, 8, 38, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(739, 8, 39, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(740, 8, 40, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(741, 8, 41, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(742, 8, 42, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(743, 8, 43, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(744, 8, 44, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(745, 8, 45, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(746, 8, 46, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(747, 8, 47, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(748, 8, 48, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(749, 8, 49, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(750, 8, 50, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(751, 8, 51, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(752, 8, 52, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(753, 8, 53, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(754, 8, 54, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(755, 8, 55, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(756, 8, 56, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(757, 8, 57, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(758, 8, 58, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(759, 8, 59, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(760, 8, 60, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(761, 8, 61, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(762, 8, 62, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(763, 8, 63, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(764, 8, 64, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(765, 8, 65, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(766, 8, 66, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(767, 8, 67, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(768, 8, 68, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(769, 8, 69, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(770, 8, 70, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(771, 8, 71, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(772, 8, 72, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(773, 8, 73, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(774, 8, 74, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(775, 8, 75, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(776, 8, 76, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(777, 8, 77, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(778, 8, 78, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(779, 8, 79, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(780, 8, 80, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(781, 8, 81, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(782, 8, 82, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(783, 8, 83, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(784, 8, 84, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(785, 8, 85, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(786, 8, 86, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(787, 8, 87, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(788, 8, 88, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(789, 8, 89, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(790, 8, 90, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(791, 8, 91, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(792, 8, 92, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(793, 8, 93, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(794, 8, 94, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(795, 8, 95, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(796, 8, 96, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(797, 8, 97, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(798, 8, 98, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(799, 8, 99, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(800, 8, 100, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(801, 9, 1, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(802, 9, 2, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(803, 9, 3, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(804, 9, 4, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(805, 9, 5, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(806, 9, 6, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(807, 9, 7, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(808, 9, 8, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(809, 9, 9, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(810, 9, 10, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(811, 9, 11, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(812, 9, 12, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(813, 9, 13, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(814, 9, 14, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(815, 9, 15, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(816, 9, 16, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(817, 9, 17, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(818, 9, 18, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(819, 9, 19, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(820, 9, 20, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(821, 9, 21, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(822, 9, 22, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(823, 9, 23, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(824, 9, 24, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(825, 9, 25, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(826, 9, 26, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(827, 9, 27, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(828, 9, 28, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(829, 9, 29, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(830, 9, 30, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(831, 9, 31, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(832, 9, 32, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(833, 9, 33, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(834, 9, 34, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(835, 9, 35, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(836, 9, 36, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(837, 9, 37, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(838, 9, 38, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(839, 9, 39, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(840, 9, 40, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(841, 9, 41, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(842, 9, 42, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(843, 9, 43, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(844, 9, 44, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(845, 9, 45, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(846, 9, 46, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(847, 9, 47, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(848, 9, 48, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(849, 9, 49, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(850, 9, 50, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(851, 9, 51, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(852, 9, 52, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(853, 9, 53, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(854, 9, 54, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(855, 9, 55, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(856, 9, 56, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(857, 9, 57, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(858, 9, 58, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(859, 9, 59, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(860, 9, 60, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(861, 9, 61, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(862, 9, 62, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(863, 9, 63, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(864, 9, 64, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(865, 9, 65, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(866, 9, 66, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(867, 9, 67, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(868, 9, 68, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(869, 9, 69, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(870, 9, 70, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(871, 9, 71, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(872, 9, 72, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(873, 9, 73, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(874, 9, 74, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(875, 9, 75, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(876, 9, 76, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(877, 9, 77, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(878, 9, 78, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(879, 9, 79, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(880, 9, 80, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(881, 9, 81, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(882, 9, 82, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(883, 9, 83, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(884, 9, 84, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(885, 9, 85, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(886, 9, 86, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(887, 9, 87, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(888, 9, 88, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(889, 9, 89, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(890, 9, 90, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(891, 9, 91, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(892, 9, 92, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(893, 9, 93, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(894, 9, 94, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(895, 9, 95, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(896, 9, 96, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(897, 9, 97, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(898, 9, 98, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(899, 9, 99, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(900, 9, 100, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(901, 10, 1, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(902, 10, 2, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(903, 10, 3, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(904, 10, 4, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(905, 10, 5, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(906, 10, 6, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(907, 10, 7, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(908, 10, 8, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(909, 10, 9, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(910, 10, 10, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(911, 10, 11, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(912, 10, 12, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(913, 10, 13, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(914, 10, 14, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(915, 10, 15, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(916, 10, 16, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(917, 10, 17, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(918, 10, 18, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(919, 10, 19, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(920, 10, 20, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(921, 10, 21, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(922, 10, 22, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(923, 10, 23, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(924, 10, 24, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(925, 10, 25, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(926, 10, 26, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(927, 10, 27, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(928, 10, 28, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(929, 10, 29, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(930, 10, 30, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(931, 10, 31, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(932, 10, 32, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(933, 10, 33, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(934, 10, 34, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(935, 10, 35, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(936, 10, 36, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(937, 10, 37, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(938, 10, 38, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(939, 10, 39, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(940, 10, 40, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(941, 10, 41, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(942, 10, 42, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(943, 10, 43, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(944, 10, 44, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(945, 10, 45, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(946, 10, 46, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(947, 10, 47, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(948, 10, 48, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(949, 10, 49, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(950, 10, 50, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(951, 10, 51, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(952, 10, 52, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(953, 10, 53, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(954, 10, 54, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(955, 10, 55, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(956, 10, 56, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(957, 10, 57, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(958, 10, 58, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(959, 10, 59, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(960, 10, 60, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(961, 10, 61, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(962, 10, 62, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(963, 10, 63, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(964, 10, 64, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(965, 10, 65, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(966, 10, 66, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(967, 10, 67, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(968, 10, 68, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(969, 10, 69, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(970, 10, 70, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(971, 10, 71, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(972, 10, 72, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(973, 10, 73, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(974, 10, 74, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(975, 10, 75, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(976, 10, 76, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(977, 10, 77, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(978, 10, 78, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(979, 10, 79, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(980, 10, 80, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(981, 10, 81, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(982, 10, 82, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(983, 10, 83, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(984, 10, 84, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(985, 10, 85, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(986, 10, 86, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(987, 10, 87, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(988, 10, 88, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(989, 10, 89, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(990, 10, 90, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(991, 10, 91, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(992, 10, 92, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(993, 10, 93, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(994, 10, 94, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(995, 10, 95, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(996, 10, 96, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(997, 10, 97, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(998, 10, 98, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(999, 10, 99, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16'),
(1000, 10, 100, 'free', NULL, NULL, 0, 0.00, 'default', NULL, 0.00, NULL, '2026-01-28 05:16:16');

--
-- Triggers `machines`
--
DELIMITER $$
CREATE TRIGGER `clear_token_on_free` BEFORE UPDATE ON `machines` FOR EACH ROW BEGIN
    IF NEW.status = 'free' THEN
        SET NEW.session_token = NULL;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `marketing_events`
--

CREATE TABLE `marketing_events` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `type` varchar(50) NOT NULL,
  `multiplier` decimal(5,2) DEFAULT 1.00,
  `target_island_id` int(11) DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `provider_name` varchar(50) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`id`, `admin_id`, `provider_name`, `account_name`, `account_number`, `logo_url`, `is_active`) VALUES
(1, 2, 'KBZPay', 'Suro Official (Maung)', '09111222333', NULL, 1),
(2, 2, 'KBZPay', 'Suro VIP (Win)', '09444555666', NULL, 1),
(3, 3, 'WavePay', 'Suro Official (Aung)', '09777888999', NULL, 1),
(4, 4, 'CB Pay', 'Mandalay Agent', '09222333444', NULL, 1),
(5, 1, 'USDT (TRC20)', 'Suro Crypto', 'T9...WalletAddr', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `security_alerts`
--

CREATE TABLE `security_alerts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `risk_level` varchar(20) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `key_name` varchar(50) NOT NULL,
  `value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`key_name`, `value`) VALUES
('auto_approve_limit', '50000'),
('global_announcement', ''),
('maintenance_mode', '0'),
('min_deposit', '1000'),
('welcome_bonus', '0'),
('whale_threshold', '1000000');

-- --------------------------------------------------------

--
-- Table structure for table `tournaments`
--

CREATE TABLE `tournaments` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `desc` text DEFAULT NULL,
  `entry_fee` decimal(12,2) DEFAULT 0.00,
  `prize_pool` decimal(12,2) DEFAULT 100000.00,
  `spin_limit` int(11) DEFAULT 50,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `island_id` int(11) DEFAULT NULL,
  `min_level` int(11) DEFAULT 1,
  `status` enum('upcoming','active','ended') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `tournaments`
--

INSERT INTO `tournaments` (`id`, `title`, `desc`, `entry_fee`, `prize_pool`, `spin_limit`, `start_time`, `end_time`, `island_id`, `min_level`, `status`) VALUES
(1, 'Daily Sprint', 'Highest total win in 50 spins. Quick & Fast!', 5000.00, 500000.00, 50, '2026-01-28 11:46:15', '2026-01-29 11:46:15', NULL, 1, 'active'),
(2, 'Whale Wars', 'High rollers only. Massive prizes.', 100000.00, 5000000.00, 50, '2026-01-28 11:46:15', '2026-01-31 11:46:15', NULL, 1, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `tournament_entries`
--

CREATE TABLE `tournament_entries` (
  `id` int(11) NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `current_score` decimal(12,2) DEFAULT 0.00,
  `spins_used` int(11) DEFAULT 0,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('deposit','withdraw','bonus','commission') NOT NULL,
  `amount` decimal(16,2) NOT NULL,
  `payment_method_id` int(11) DEFAULT NULL,
  `transaction_last_digits` varchar(6) DEFAULT NULL,
  `external_ref_id` varchar(100) DEFAULT NULL,
  `proof_image` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `processed_by_admin_id` int(11) DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `balance` decimal(16,2) NOT NULL DEFAULT 5000.00,
  `level` int(11) NOT NULL DEFAULT 1,
  `xp` bigint(20) NOT NULL DEFAULT 0,
  `active_pet_id` varchar(50) DEFAULT 'luna',
  `owned_islands` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`owned_islands`)),
  `is_agent` tinyint(1) DEFAULT 0,
  `referral_code` varchar(10) DEFAULT NULL,
  `referrer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `commission_balance` decimal(16,2) DEFAULT 0.00,
  `is_trusted` tinyint(1) DEFAULT 0,
  `status` enum('active','banned','suspended') DEFAULT 'active',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_muted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_characters`
--

CREATE TABLE `user_characters` (
  `id` int(11) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `char_key` varchar(50) NOT NULL,
  `is_new` tinyint(1) DEFAULT 1,
  `obtained_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_items`
--

CREATE TABLE `user_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(50) NOT NULL,
  `amount` int(11) DEFAULT 1,
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_tokens`
--

CREATE TABLE `user_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `withdrawal_banks`
--

CREATE TABLE `withdrawal_banks` (
  `id` int(11) NOT NULL,
  `bank_name` varchar(50) NOT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `withdrawal_banks`
--

INSERT INTO `withdrawal_banks` (`id`, `bank_name`, `logo_url`, `is_active`) VALUES
(1, 'KBZPay', NULL, 1),
(2, 'WavePay', NULL, 1),
(3, 'CB Pay', NULL, 1),
(4, 'AYA Pay', NULL, 1),
(5, 'UAB Pay', NULL, 1),
(6, 'Yoma Bank', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `withdrawal_limits`
--

CREATE TABLE `withdrawal_limits` (
  `id` int(11) NOT NULL,
  `deposit_amount` decimal(16,2) NOT NULL,
  `max_withdraw` decimal(16,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `withdrawal_limits`
--

INSERT INTO `withdrawal_limits` (`id`, `deposit_amount`, `max_withdraw`) VALUES
(1, 10000.00, 30000.00),
(2, 20000.00, 61000.00),
(3, 50000.00, 150000.00),
(4, 100000.00, 300000.00),
(5, 1000000.00, 3000000.00),
(6, 20000000.00, 60000000.00);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `characters`
--
ALTER TABLE `characters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `char_key` (`char_key`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_chat_time` (`created_at`),
  ADD KEY `idx_chat_pinned` (`is_pinned`);

--
-- Indexes for table `daily_rewards`
--
ALTER TABLE `daily_rewards`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `game_logs`
--
ALTER TABLE `game_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `global_jackpots`
--
ALTER TABLE `global_jackpots`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `islands`
--
ALTER TABLE `islands`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `level_rewards`
--
ALTER TABLE `level_rewards`
  ADD PRIMARY KEY (`level`);

--
-- Indexes for table `machines`
--
ALTER TABLE `machines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `island_machine` (`island_id`,`machine_number`);

--
-- Indexes for table `marketing_events`
--
ALTER TABLE `marketing_events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_pm` (`admin_id`);

--
-- Indexes for table `security_alerts`
--
ALTER TABLE `security_alerts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`key_name`);

--
-- Indexes for table `tournaments`
--
ALTER TABLE `tournaments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tournament_entries`
--
ALTER TABLE `tournament_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_tourney` (`tournament_id`,`user_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD UNIQUE KEY `referral_code` (`referral_code`);

--
-- Indexes for table `user_characters`
--
ALTER TABLE `user_characters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_char` (`user_id`,`char_key`);

--
-- Indexes for table `user_items`
--
ALTER TABLE `user_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_tokens`
--
ALTER TABLE `user_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `withdrawal_banks`
--
ALTER TABLE `withdrawal_banks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `withdrawal_limits`
--
ALTER TABLE `withdrawal_limits`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `characters`
--
ALTER TABLE `characters`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `game_logs`
--
ALTER TABLE `game_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `global_jackpots`
--
ALTER TABLE `global_jackpots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `islands`
--
ALTER TABLE `islands`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `machines`
--
ALTER TABLE `machines`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1001;

--
-- AUTO_INCREMENT for table `marketing_events`
--
ALTER TABLE `marketing_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `security_alerts`
--
ALTER TABLE `security_alerts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tournaments`
--
ALTER TABLE `tournaments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tournament_entries`
--
ALTER TABLE `tournament_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_characters`
--
ALTER TABLE `user_characters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_items`
--
ALTER TABLE `user_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_tokens`
--
ALTER TABLE `user_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `withdrawal_banks`
--
ALTER TABLE `withdrawal_banks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `withdrawal_limits`
--
ALTER TABLE `withdrawal_limits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `user_characters`
--
ALTER TABLE `user_characters`
  ADD CONSTRAINT `user_characters_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_tokens`
--
ALTER TABLE `user_tokens`
  ADD CONSTRAINT `user_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
