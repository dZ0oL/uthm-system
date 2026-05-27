-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: May 27, 2026 at 07:26 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `uthm_messaging`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_reset_requests`
--

CREATE TABLE `admin_reset_requests` (
  `request_id` int NOT NULL,
  `user_id` int NOT NULL,
  `status` enum('otp_pending','pending','completed','rejected') COLLATE utf8mb4_general_ci DEFAULT 'otp_pending',
  `otp` varchar(6) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `otp_expiry` datetime DEFAULT NULL,
  `requested_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_reset_requests`
--

INSERT INTO `admin_reset_requests` (`request_id`, `user_id`, `status`, `otp`, `otp_expiry`, `requested_at`, `approved_by`, `approved_at`, `rejection_reason`) VALUES
(1, 2, 'rejected', NULL, NULL, '2026-05-23 16:50:39', 1, '2026-05-23 16:51:35', NULL),
(2, 2, 'completed', NULL, NULL, '2026-05-23 17:15:35', 1, '2026-05-23 17:35:37', NULL),
(3, 2, 'rejected', NULL, NULL, '2026-05-26 10:08:01', 1, '2026-05-26 13:05:50', NULL),
(4, 2, 'completed', NULL, NULL, '2026-05-26 13:14:08', 1, '2026-05-26 13:14:53', NULL),
(5, 2, 'rejected', NULL, NULL, '2026-05-26 13:32:47', 1, '2026-05-26 13:33:26', 'Too much requests');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int NOT NULL,
  `user_id` int NOT NULL,
  `action` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `details` text COLLATE utf8mb4_general_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `user_id`, `action`, `details`, `ip_address`, `timestamp`) VALUES
(1, 1, 'Account Created', 'Head of Admin account created via system setup', '127.0.0.1', '2026-05-23 07:50:07'),
(10, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-23 08:07:11'),
(11, 1, 'Change Password', 'Admin changed password', '::1', '2026-05-23 08:07:52'),
(12, 1, 'Logout', NULL, '::1', '2026-05-23 08:07:59'),
(14, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-23 08:09:04'),
(15, 1, 'Register Admin', 'Registered new admin: Aiman (m-3513759@moe-dl.edu.my), assigned user_id: 2', '::1', '2026-05-23 08:09:42'),
(16, 1, 'Logout', NULL, '::1', '2026-05-23 08:10:07'),
(17, 2, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-23 08:11:21'),
(18, 2, 'Change Password', 'Admin changed password', '::1', '2026-05-23 08:11:39'),
(19, 2, 'Logout', NULL, '::1', '2026-05-23 08:12:09'),
(20, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-23 08:21:20'),
(21, 1, 'Deactivate User', 'Deactivated user_id: 2', NULL, '2026-05-23 08:33:04'),
(22, 1, 'Activate User', 'Activated user_id: 2', NULL, '2026-05-23 08:33:06'),
(23, 1, 'Register Staff', 'Registered staff: Hilman (zulhilmantarmizi@gmail.com)', NULL, '2026-05-23 08:36:17'),
(24, 1, 'Key Generation', 'ECDH keys generated for user_id: 3', '::1', '2026-05-23 08:36:20'),
(25, 3, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-23 08:37:30'),
(26, 3, 'Signal Key Registration', 'Signal Protocol keys registered', '::1', '2026-05-23 08:37:31'),
(27, 3, 'Force Password Change', 'Temporary password changed after account recovery', '::1', '2026-05-23 08:37:50'),
(28, 3, 'Device Share Pickup', 'SSS share 1 delivered and removed from server', '::1', '2026-05-23 08:37:50'),
(29, 1, 'Register Staff', 'Registered staff: Iman (kevindezul@gmail.com)', NULL, '2026-05-23 08:38:49'),
(30, 1, 'Key Generation', 'ECDH keys generated for user_id: 4', '::1', '2026-05-23 08:38:52'),
(31, 4, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-23 08:39:22'),
(32, 4, 'Signal Key Registration', 'Signal Protocol keys registered', '::1', '2026-05-23 08:39:22'),
(33, 4, 'Force Password Change', 'Temporary password changed after account recovery', '::1', '2026-05-23 08:39:36'),
(34, 4, 'Device Share Pickup', 'SSS share 1 delivered and removed from server', '::1', '2026-05-23 08:39:36'),
(35, 1, 'Logout', NULL, '::1', '2026-05-23 08:44:12'),
(36, 2, 'Admin Reset Request', 'Password reset requested for: m-3513759@moe-dl.edu.my', '::1', '2026-05-23 08:50:39'),
(37, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-23 08:51:19'),
(38, 1, 'Reject Admin Reset', 'Rejected admin reset request ID: 1', NULL, '2026-05-23 08:51:35'),
(39, 3, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-23 08:52:27'),
(40, 4, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-23 08:52:28'),
(41, 1, 'Create Group', 'Created group: Meeting Group', NULL, '2026-05-23 09:09:49'),
(42, 2, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-23 09:12:35'),
(43, 2, 'Logout', NULL, '::1', '2026-05-23 09:13:28'),
(44, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-23 09:14:00'),
(45, 1, 'Logout', NULL, '::1', '2026-05-23 09:14:02'),
(46, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-23 09:14:09'),
(47, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-23 09:14:27'),
(48, 1, 'Logout', NULL, '::1', '2026-05-23 09:14:46'),
(49, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-23 09:15:07'),
(50, 2, 'Admin Reset OTP Sent', 'Verification OTP sent to: m-3513759@moe-dl.edu.my', '::1', '2026-05-23 09:15:35'),
(51, 4, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-23 09:19:37'),
(52, 3, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-23 09:19:38'),
(53, 3, 'Change Password', 'Password and encryption key updated', '::1', '2026-05-23 09:20:28'),
(54, 4, 'Send Message', 'Sent encrypted personal message to user_id: 3', '::1', '2026-05-23 09:21:00'),
(55, 4, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-23 09:34:45'),
(56, 3, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-23 09:34:47'),
(57, 2, 'Admin Reset OTP Sent', 'Verification OTP sent to: m-3513759@moe-dl.edu.my', '::1', '2026-05-23 09:35:04'),
(58, 2, 'Admin Reset Verified', 'Identity verified, reset pending HOA approval: m-3513759@moe-dl.edu.my', '::1', '2026-05-23 09:35:15'),
(59, 1, 'Approve Admin Reset', 'Approved and reset password for: Aiman (m-3513759@moe-dl.edu.my)', NULL, '2026-05-23 09:35:37'),
(60, 2, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-23 09:36:35'),
(61, 2, 'Change Password', 'Admin changed password', '::1', '2026-05-23 09:36:54'),
(62, 2, 'Logout', NULL, '::1', '2026-05-23 09:37:18'),
(63, 3, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-23 09:38:11'),
(64, 4, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-23 09:38:25'),
(65, 3, 'Change Password', 'Password and encryption key updated', '::1', '2026-05-23 09:38:44'),
(66, 3, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-23 09:47:16'),
(67, 3, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-23 09:47:51'),
(68, 3, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-23 09:48:27'),
(69, 4, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-23 09:48:40'),
(70, 3, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-23 09:49:03'),
(71, 4, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-23 09:49:18'),
(72, 3, 'Send Message', 'Sent encrypted personal message to user_id: 4', '::1', '2026-05-23 09:49:46'),
(73, 3, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-23 10:01:22'),
(74, 4, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-23 10:01:23'),
(75, 1, 'Logout', NULL, '::1', '2026-05-23 10:01:27'),
(76, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-24 03:31:27'),
(77, 3, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-24 03:32:02'),
(78, 4, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-24 03:32:16'),
(79, 4, 'Signal Key Registration', 'Signal Protocol keys registered', '::1', '2026-05-24 03:32:16'),
(80, 3, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-24 09:05:15'),
(81, 4, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-24 09:05:15'),
(82, 3, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-24 09:05:37'),
(83, 4, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-24 09:05:50'),
(84, 3, 'Send Message', 'Sent encrypted personal message to user_id: 4', '::1', '2026-05-24 09:06:02'),
(85, 4, 'Send Message', 'Sent encrypted personal message to user_id: 3', '::1', '2026-05-24 09:06:12'),
(86, 4, 'Send Message', 'Sent encrypted personal message to user_id: 3', '::1', '2026-05-24 09:06:18'),
(87, 3, 'Send File', 'File sent: 3rd-Receipt-Muhammad Zulhilman Bin Tarmizi (1).pdf (application/pdf)', '::1', '2026-05-24 09:06:27'),
(88, 1, 'Logout', NULL, '::1', '2026-05-24 11:33:23'),
(89, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-25 04:59:40'),
(90, 3, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-25 05:00:36'),
(91, 4, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-25 05:00:45'),
(92, 4, 'Signal Key Registration', 'Signal Protocol keys registered', '::1', '2026-05-25 05:00:45'),
(93, 3, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-25 06:09:05'),
(94, 4, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-25 06:09:06'),
(95, 1, 'Logout', NULL, '::1', '2026-05-25 06:09:09'),
(96, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-25 06:36:25'),
(97, 1, 'Logout', NULL, '::1', '2026-05-25 06:37:19'),
(98, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-25 06:44:15'),
(99, 3, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-25 06:45:28'),
(100, 4, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-25 06:45:44'),
(101, 4, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-25 06:46:46'),
(102, 3, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-25 06:57:30'),
(103, 1, 'Logout', NULL, '::1', '2026-05-25 07:04:24'),
(104, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-25 07:04:34'),
(105, 3, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-25 07:05:57'),
(106, 1, 'Logout', NULL, '::1', '2026-05-25 07:06:50'),
(107, 4, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-25 07:07:07'),
(108, 4, 'Signal Key Registration', 'Signal Protocol keys registered', '::1', '2026-05-25 07:07:07'),
(109, 4, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-25 07:08:26'),
(110, 2, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-25 07:08:47'),
(111, 2, 'Logout', NULL, '::1', '2026-05-25 07:10:02'),
(112, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-25 07:10:14'),
(113, 3, 'Send Message', 'Sent encrypted personal message to user_id: 4', '::1', '2026-05-25 07:23:33'),
(114, 1, 'Logout', NULL, '::1', '2026-05-25 08:02:37'),
(115, 4, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-25 08:03:08'),
(116, 4, 'Signal Key Registration', 'Signal Protocol keys registered', '::1', '2026-05-25 08:03:08'),
(117, 4, 'Send Message', 'Sent encrypted personal message to user_id: 3', '::1', '2026-05-25 08:13:23'),
(118, 4, 'Send Message', 'Sent encrypted personal message to user_id: 3', '::1', '2026-05-25 08:13:31'),
(119, 4, 'Send Message', 'Sent encrypted personal message to user_id: 3', '::1', '2026-05-25 08:13:57'),
(120, 3, 'Send Message', 'Sent encrypted personal message to user_id: 4', '::1', '2026-05-25 08:14:33'),
(121, 4, 'Send Message', 'Sent encrypted personal message to user_id: 3', '::1', '2026-05-25 08:15:09'),
(122, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-25 08:19:02'),
(123, 3, 'Send Message', 'Sent encrypted personal message to user_id: 4', '::1', '2026-05-25 08:20:17'),
(124, 4, 'Send Message', 'Sent encrypted personal message to user_id: 3', '::1', '2026-05-25 08:20:46'),
(125, 3, 'Send Message', 'Sent encrypted personal message to user_id: 4', '::1', '2026-05-25 08:20:54'),
(126, 3, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-25 08:53:57'),
(127, 3, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-25 09:08:14'),
(128, 4, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-25 09:08:37'),
(129, 3, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-25 09:08:38'),
(130, 1, 'Logout', NULL, '::1', '2026-05-25 09:09:00'),
(131, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-25 11:58:23'),
(132, 1, 'Logout', NULL, '::1', '2026-05-25 11:59:14'),
(133, 3, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-25 11:59:31'),
(134, 3, 'Signal Key Registration', 'Signal Protocol keys registered', '::1', '2026-05-25 11:59:31'),
(135, 3, 'Send File', 'File sent: 3rd-Receipt-Muhammad Zulhilman Bin Tarmizi (1).pdf (application/pdf)', '::1', '2026-05-25 12:00:12'),
(136, 3, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-25 12:00:18'),
(137, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-25 23:52:17'),
(138, 3, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-25 23:53:43'),
(139, 3, 'Signal Key Registration', 'Signal Protocol keys registered', '::1', '2026-05-25 23:53:43'),
(140, 3, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-25 23:54:19'),
(141, 1, 'Register Staff', 'Registered staff: Tarmizi (tarmizihilman13@gmail.com)', NULL, '2026-05-26 00:00:01'),
(142, 1, 'Key Generation', 'ECDH keys generated for user_id: 5', '::1', '2026-05-26 00:00:05'),
(143, 1, 'Create Group', 'Created group: IT Team', NULL, '2026-05-26 00:01:01'),
(144, 1, 'Create Group', 'Created group: Payroll Team', NULL, '2026-05-26 00:01:51'),
(145, 1, 'Create Group', 'Created group: Audit Team', NULL, '2026-05-26 00:02:38'),
(146, 3, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 00:16:04'),
(147, 3, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-26 00:16:41'),
(148, 5, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 00:17:01'),
(149, 5, 'Signal Key Registration', 'Signal Protocol keys registered', '::1', '2026-05-26 00:17:02'),
(150, 5, 'Force Password Change', 'Temporary password changed after account recovery', '::1', '2026-05-26 00:26:18'),
(151, 5, 'Device Share Pickup', 'SSS share 1 delivered and removed from server', '::1', '2026-05-26 00:26:18'),
(152, 3, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 00:26:53'),
(153, 3, 'Signal Key Registration', 'Signal Protocol keys registered', '::1', '2026-05-26 00:26:53'),
(154, 5, 'Send Message', 'Sent encrypted group message in group_id: 4', '::1', '2026-05-26 00:27:38'),
(155, 1, 'Logout', NULL, '::1', '2026-05-26 00:28:43'),
(156, 4, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 00:28:55'),
(157, 4, 'Signal Key Registration', 'Signal Protocol keys registered', '::1', '2026-05-26 00:28:55'),
(158, 4, 'Send Message', 'Sent encrypted group message in group_id: 4', '::1', '2026-05-26 00:29:13'),
(159, 5, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-26 00:44:15'),
(160, 3, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-26 00:44:16'),
(161, 4, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-26 00:44:19'),
(162, 3, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 00:45:36'),
(163, 3, 'Signal Key Registration', 'Signal Protocol keys registered', '::1', '2026-05-26 00:45:36'),
(164, 5, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 00:45:48'),
(165, 5, 'Signal Key Registration', 'Signal Protocol keys registered', '::1', '2026-05-26 00:45:48'),
(166, 3, 'Send Message', 'Sent encrypted group message in group_id: 4', '::1', '2026-05-26 00:46:16'),
(167, 3, 'Send Message', 'Sent encrypted personal message to user_id: 5', '::1', '2026-05-26 00:50:56'),
(168, 3, 'Send Message', 'Sent encrypted personal message to user_id: 5', '::1', '2026-05-26 00:51:07'),
(169, 5, 'Send Message', 'Sent encrypted personal message to user_id: 3', '::1', '2026-05-26 00:51:23'),
(170, 3, 'Send Message', 'Sent encrypted group message in group_id: 4', '::1', '2026-05-26 00:51:47'),
(171, 3, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 00:52:12'),
(172, 3, 'Signal Key Registration', 'Signal Protocol keys registered', '::1', '2026-05-26 00:52:12'),
(173, 3, 'Signal Key Registration', 'Signal Protocol keys registered', '::1', '2026-05-26 00:52:38'),
(174, 3, 'Signal Key Registration', 'Signal Protocol keys registered', '::1', '2026-05-26 00:52:46'),
(175, 3, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-26 00:53:07'),
(176, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 00:53:25'),
(177, 4, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 00:54:02'),
(178, 4, 'Signal Key Registration', 'Signal Protocol keys registered', '::1', '2026-05-26 00:54:02'),
(179, 4, 'Send Message', 'Sent encrypted personal message to user_id: 5', '::1', '2026-05-26 00:55:13'),
(180, 4, 'Send Message', 'Sent encrypted group message in group_id: 4', '::1', '2026-05-26 01:12:17'),
(181, 5, 'Send Message', 'Sent encrypted group message in group_id: 4', '::1', '2026-05-26 01:13:04'),
(182, 5, 'Send Message', 'Sent encrypted personal message to user_id: 4', '::1', '2026-05-26 01:13:18'),
(183, 4, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-26 01:13:50'),
(184, 5, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-26 01:13:51'),
(185, 4, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 01:14:01'),
(186, 5, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 01:14:08'),
(187, 4, 'Send Message', 'Sent encrypted group message in group_id: 4', '::1', '2026-05-26 01:14:24'),
(188, 5, 'Send Message', 'Sent encrypted group message in group_id: 4', '::1', '2026-05-26 01:23:21'),
(189, 4, 'Send Message', 'Sent encrypted group message in group_id: 4', '::1', '2026-05-26 01:23:32'),
(190, 1, 'Logout', NULL, '::1', '2026-05-26 01:23:43'),
(191, 3, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 01:23:58'),
(192, 3, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-26 01:27:40'),
(193, 5, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-26 01:28:00'),
(194, 4, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-26 01:28:24'),
(195, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 01:30:15'),
(196, 3, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 01:30:30'),
(197, 3, 'Signal Key Registration', 'Signal Protocol keys registered', '::1', '2026-05-26 01:30:30'),
(198, 4, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 01:30:45'),
(199, 4, 'Signal Key Registration', 'Signal Protocol keys registered', '::1', '2026-05-26 01:30:45'),
(200, 4, 'Send Message', 'Sent encrypted group message in group_id: 4', '::1', '2026-05-26 01:31:05'),
(201, 3, 'Send Message', 'Sent encrypted group message in group_id: 4', '::1', '2026-05-26 01:31:17'),
(202, 1, 'Create Group', 'Created group: Audit group', NULL, '2026-05-26 01:32:49'),
(203, 3, 'Send Message', 'Sent encrypted group message in group_id: 5', '::1', '2026-05-26 01:33:00'),
(204, 3, 'Send Message', 'Sent encrypted group message in group_id: 5', '::1', '2026-05-26 01:33:20'),
(205, 1, 'Logout', NULL, '::1', '2026-05-26 01:33:32'),
(206, 5, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 01:33:59'),
(207, 5, 'Signal Key Registration', 'Signal Protocol keys registered', '::1', '2026-05-26 01:33:59'),
(208, 5, 'Send File', 'File sent: 3rd-Receipt-Muhammad Zulhilman Bin Tarmizi.pdf (application/pdf)', '::1', '2026-05-26 01:34:35'),
(209, 5, 'Send Message', 'Sent encrypted group message in group_id: 5', '::1', '2026-05-26 01:34:35'),
(210, 5, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-26 01:44:43'),
(211, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 01:56:14'),
(212, 5, 'Recovery Request Submitted', 'Recovery request submitted via OTP verification for: tarmizihilman13@gmail.com', '::1', '2026-05-26 01:59:17'),
(213, 1, 'Logout', NULL, '::1', '2026-05-26 02:07:26'),
(214, 2, 'Admin Reset OTP Sent', 'Verification OTP sent to: m-3513759@moe-dl.edu.my', '::1', '2026-05-26 02:08:01'),
(215, 2, 'Admin Reset Verified', 'Identity verified, reset pending HOA approval: m-3513759@moe-dl.edu.my', '::1', '2026-05-26 02:08:13'),
(216, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 02:08:58'),
(217, 1, 'Approve Recovery', 'Approved recovery request ID: 1', NULL, '2026-05-26 02:57:24'),
(218, 1, 'Recovery Initiated', 'SSS recovery initiated for user_id: 5, request_id: 1', '::1', '2026-05-26 02:57:32'),
(219, 1, 'Recovery Completed', 'New keys issued for user_id: 5, request_id: 1', '::1', '2026-05-26 02:57:36'),
(220, 3, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-26 02:57:55'),
(221, 5, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 02:58:36'),
(222, 5, 'Signal Key Registration', 'Signal Protocol keys registered', '::1', '2026-05-26 02:58:36'),
(223, 5, 'Force Password Change', 'Temporary password changed after account recovery', '::1', '2026-05-26 02:58:55'),
(224, 5, 'Send Message', 'Sent encrypted group message in group_id: 5', '::1', '2026-05-26 02:59:21'),
(225, 4, 'Send Message', 'Sent encrypted group message in group_id: 5', '::1', '2026-05-26 02:59:28'),
(226, 1, 'Logout', NULL, '::1', '2026-05-26 03:00:08'),
(227, 5, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-26 03:00:12'),
(228, 4, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-26 03:00:12'),
(229, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 03:08:37'),
(230, 1, 'Deactivate User', 'Deactivated user_id: 2', NULL, '2026-05-26 03:09:21'),
(231, 1, 'Activate User', 'Activated user_id: 2', NULL, '2026-05-26 03:09:25'),
(232, 5, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 03:15:05'),
(233, 5, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-26 03:15:35'),
(234, 1, 'Logout', NULL, '::1', '2026-05-26 03:34:27'),
(235, 5, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 04:36:47'),
(236, 5, 'Device Share Pickup', 'SSS share 1 delivered and removed from server', '::1', '2026-05-26 04:36:47'),
(237, 5, 'Signal Key Registration', 'Signal Protocol keys registered', '::1', '2026-05-26 04:36:47'),
(238, 5, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-26 04:37:19'),
(239, 5, 'Recovery Request Submitted', 'Recovery request submitted via OTP verification for: tarmizihilman13@gmail.com', '::1', '2026-05-26 04:38:23'),
(240, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 04:39:36'),
(241, 1, 'Logout', NULL, '::1', '2026-05-26 04:39:57'),
(242, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 04:40:17'),
(243, 1, 'Reject Recovery', 'Rejected recovery request ID: 2', NULL, '2026-05-26 04:41:34'),
(244, 1, 'Logout', NULL, '::1', '2026-05-26 04:50:24'),
(245, 5, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 04:50:45'),
(246, 5, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-26 04:52:49'),
(247, 5, 'Recovery Request Submitted', 'Recovery request submitted via OTP verification for: tarmizihilman13@gmail.com', '::1', '2026-05-26 04:53:27'),
(248, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 04:56:16'),
(249, 1, 'Reject Recovery', 'Rejected recovery request ID: 3', NULL, '2026-05-26 04:58:03'),
(250, 1, 'Logout', NULL, '::1', '2026-05-26 05:00:25'),
(251, 5, 'Recovery Request Submitted', 'Recovery request submitted via OTP verification for: tarmizihilman13@gmail.com', '::1', '2026-05-26 05:00:45'),
(252, 5, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 05:01:09'),
(253, 5, 'Signal Key Registration', 'Signal Protocol keys registered', '::1', '2026-05-26 05:01:09'),
(254, 5, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-26 05:01:43'),
(255, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 05:01:52'),
(256, 1, 'Reject Recovery', 'Rejected recovery request ID: 4', NULL, '2026-05-26 05:03:11'),
(257, 1, 'Logout', NULL, '::1', '2026-05-26 05:03:16'),
(258, 5, 'Recovery Request Submitted', 'Recovery request submitted via OTP verification for: tarmizihilman13@gmail.com', '::1', '2026-05-26 05:03:38'),
(259, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 05:03:46'),
(260, 1, 'Reject Admin Reset', 'Rejected admin reset request ID: 3', NULL, '2026-05-26 05:05:50'),
(261, 1, 'Logout', NULL, '::1', '2026-05-26 05:12:48'),
(262, 2, 'Admin Reset OTP Sent', 'Verification OTP sent to: m-3513759@moe-dl.edu.my', '::1', '2026-05-26 05:14:08'),
(263, 2, 'Admin Reset Verified', 'Identity verified, reset pending HOA approval: m-3513759@moe-dl.edu.my', '::1', '2026-05-26 05:14:20'),
(264, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 05:14:35'),
(265, 1, 'Approve Admin Reset', 'Approved and reset password for: Aiman (m-3513759@moe-dl.edu.my)', NULL, '2026-05-26 05:14:53'),
(266, 1, 'Logout', NULL, '::1', '2026-05-26 05:15:18'),
(267, 2, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 05:15:35'),
(268, 2, 'Change Password', 'Admin changed password', '::1', '2026-05-26 05:31:28'),
(269, 2, 'Logout', NULL, '::1', '2026-05-26 05:32:38'),
(270, 2, 'Admin Reset OTP Sent', 'Verification OTP sent to: m-3513759@moe-dl.edu.my', '::1', '2026-05-26 05:32:47'),
(271, 2, 'Admin Reset Verified', 'Identity verified, reset pending HOA approval: m-3513759@moe-dl.edu.my', '::1', '2026-05-26 05:32:58'),
(272, 1, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 05:33:03'),
(273, 1, 'Reject Admin Reset', 'Rejected admin reset request ID: 5', NULL, '2026-05-26 05:33:26'),
(274, 1, 'Logout', NULL, '::1', '2026-05-26 05:38:57'),
(275, 5, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 05:39:05'),
(276, 5, 'Logout', 'User logged out — session token cleared', '::1', '2026-05-26 05:48:55'),
(277, 4, 'Login', 'New session started — previous device sessions invalidated', '::1', '2026-05-26 05:49:04'),
(278, 4, 'Signal Key Registration', 'Signal Protocol keys registered', '::1', '2026-05-26 05:49:04');

-- --------------------------------------------------------

--
-- Table structure for table `contacts`
--

CREATE TABLE `contacts` (
  `contact_id` int NOT NULL,
  `user_id` int NOT NULL,
  `contact_user_id` int NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `conversation_reads`
--

CREATE TABLE `conversation_reads` (
  `user_id` int NOT NULL,
  `chat_type` enum('personal','group') COLLATE utf8mb4_general_ci NOT NULL,
  `chat_id` int NOT NULL,
  `last_read_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `conversation_reads`
--

INSERT INTO `conversation_reads` (`user_id`, `chat_type`, `chat_id`, `last_read_at`) VALUES
(3, 'personal', 4, '2026-05-26 02:20:46'),
(3, 'personal', 5, '2026-05-26 02:20:47'),
(3, 'group', 1, '2026-05-26 02:20:45'),
(3, 'group', 3, '2026-05-26 02:20:45'),
(3, 'group', 4, '2026-05-26 01:32:03'),
(3, 'group', 5, '2026-05-26 02:20:44'),
(4, 'personal', 3, '2026-05-26 05:49:25'),
(4, 'personal', 5, '2026-05-26 05:49:37'),
(4, 'group', 1, '2026-05-26 05:50:24'),
(4, 'group', 2, '2026-05-26 05:49:16'),
(4, 'group', 4, '2026-05-26 01:31:57'),
(4, 'group', 5, '2026-05-26 05:49:31'),
(5, 'personal', 3, '2026-05-26 05:40:59'),
(5, 'personal', 4, '2026-05-26 05:42:10'),
(5, 'group', 2, '2026-05-26 05:42:51'),
(5, 'group', 4, '2026-05-26 01:27:56'),
(5, 'group', 5, '2026-05-26 05:48:50');

-- --------------------------------------------------------

--
-- Table structure for table `groups`
--

CREATE TABLE `groups` (
  `group_id` int NOT NULL,
  `group_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `created_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `groups`
--

INSERT INTO `groups` (`group_id`, `group_name`, `description`, `created_by`, `created_at`) VALUES
(1, 'Meeting Group', 'This group is for meeting purposes', 1, '2026-05-23 09:09:49'),
(2, 'IT Team', 'This group only for IT team', 1, '2026-05-26 00:01:01'),
(3, 'Payroll Team', 'This group for Payroll Team only', 1, '2026-05-26 00:01:51'),
(5, 'Audit group', 'This group for annual report team', 1, '2026-05-26 01:32:49');

-- --------------------------------------------------------

--
-- Table structure for table `group_members`
--

CREATE TABLE `group_members` (
  `membership_id` int NOT NULL,
  `group_id` int NOT NULL,
  `user_id` int NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `group_members`
--

INSERT INTO `group_members` (`membership_id`, `group_id`, `user_id`, `joined_at`) VALUES
(1, 1, 3, '2026-05-23 09:09:49'),
(2, 1, 4, '2026-05-23 09:09:49'),
(3, 2, 4, '2026-05-26 00:01:01'),
(4, 2, 5, '2026-05-26 00:01:01'),
(5, 3, 3, '2026-05-26 00:01:51'),
(9, 5, 3, '2026-05-26 01:32:49'),
(10, 5, 4, '2026-05-26 01:32:49'),
(11, 5, 5, '2026-05-26 01:32:49');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int NOT NULL,
  `sender_id` int NOT NULL,
  `receiver_id` int DEFAULT NULL,
  `group_id` int DEFAULT NULL,
  `message_content` text COLLATE utf8mb4_general_ci NOT NULL,
  `message_type` enum('personal','group','personal_file','group_file') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'personal',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `iv` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `auth_tag` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `signal_header` mediumtext COLLATE utf8mb4_general_ci,
  `signal_prekey_data` mediumtext COLLATE utf8mb4_general_ci,
  `encrypted_aes_key` text COLLATE utf8mb4_general_ci,
  `file_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Original filename',
  `file_size` int DEFAULT NULL COMMENT 'File size in bytes',
  `file_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'MIME type',
  `file_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Server path to encrypted blob',
  `ecdh_content` text COLLATE utf8mb4_general_ci,
  `ecdh_iv` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ecdh_auth_tag` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`message_id`, `sender_id`, `receiver_id`, `group_id`, `message_content`, `message_type`, `timestamp`, `iv`, `auth_tag`, `signal_header`, `signal_prekey_data`, `encrypted_aes_key`, `file_name`, `file_size`, `file_type`, `file_path`, `ecdh_content`, `ecdh_iv`, `ecdh_auth_tag`) VALUES
(1, 4, 3, NULL, '8emJY8tgSWPJI9Z7mQj2L/hK0oafoA==', 'personal', '2026-05-23 09:21:00', 'l0FN/HPYoBQl7QLQ', 'KZsKJ1UZCeF8dRPHz5a/Ug==', '{\"n\":0,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"mgjvxfoEp822rr7xbyIfEAPYWPRVnotmot1XdykXYEs\\\",\\\"y\\\":\\\"iMXKVnQxAoaGWa7pfYa9rggxL3J15St_rC8QESMGVDU\\\"}\"}', '{\"ik_dh_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"_WZXQXNNG-o8R4tCPLD4tAXzU42nvmHO982ttOqycMI\\\",\\\"y\\\":\\\"9bHoWj4MXAZcNl8EeAwqIb9z5-0mC268-JUeLHzMlxU\\\"}\",\"ek_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"BZVJCUURP5XNIhL_K4KZqfwmcM3QuLtQFZgI9MRMy64\\\",\\\"y\\\":\\\"TLa59YlkiL4a_9JtmIJW-q_-HOW9sLUP9G5RqVmLxSo\\\"}\",\"spk_id\":1779525451026,\"opk_id\":177952545102600}', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 3, 4, NULL, '2kXwsOzIi2qRLPJMUglIU5uU4DAH27V4rw==', 'personal', '2026-05-23 09:49:46', 'wofpohYd9JUvWddl', 'dqZ8LY01+++i8Dsfdb65Yg==', '{\"n\":0,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"uuqt-fgTfHvya7ZLieaQQsFWYTAaDu0yqaYu-fgpZaY\\\",\\\"y\\\":\\\"7lCe1hA1zeQr3PYqcPyzPnsXY3Gm-3R9r5egziTwiGw\\\"}\"}', '{\"ik_dh_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"qV6_zAu944fNvul_FMcDYNllvXJ13wgB7pofOhHgcjk\\\",\\\"y\\\":\\\"oWSno2K6izNuq9GZIrI0VgfdtDyXfMWAAFFAARQaSu8\\\"}\",\"ek_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"jRRrJ5GMmMO1SNISMGshowzyO4ptfB0ebB9xjk0lsRY\\\",\\\"y\\\":\\\"CFHaw6rEz-LBqswu8iKZENY-ZWufgbGOQ1s26ZWA0WI\\\"}\",\"spk_id\":1779525562491,\"opk_id\":177952556249100}', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 3, 4, NULL, 'hMo=', 'personal', '2026-05-24 09:06:02', 'x9BhA2l7H8jzcFi9', 'zPwctU5RxxQMKxqYxeep9Q==', '{\"n\":0,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"63y4_I2iJeMB3orzNY8VHMctXLYh3OOH9fG2DEvT77o\\\",\\\"y\\\":\\\"ZUs_0oje7STGbxq0l-rS-JcVbPG9Rn74CGU6qMkbPBQ\\\"}\"}', '{\"ik_dh_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"qV6_zAu944fNvul_FMcDYNllvXJ13wgB7pofOhHgcjk\\\",\\\"y\\\":\\\"oWSno2K6izNuq9GZIrI0VgfdtDyXfMWAAFFAARQaSu8\\\"}\",\"ek_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"WIOeKrDTIa2lduEdPiBSp930t_sZYfBVthqfKp0d4OE\\\",\\\"y\\\":\\\"dTPwsZ7zUSDwSh3tCXvhq73yYFwl2D1aGGtDbZOM_h0\\\"}\",\"spk_id\":1779593536169,\"opk_id\":177959353616900}', NULL, NULL, NULL, NULL, NULL, 'Ewo=', 'ZauW8W2ijOV0KJ+m', 'K8NGCbY0RBdryqb3TStlaA=='),
(4, 4, 3, NULL, '6WEyVEE=', 'personal', '2026-05-24 09:06:12', 'QseU14s9ICovx08o', 'xPnYAh30JILrBcV90zOW7w==', '{\"n\":0,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"GXEfYNgiREpHoScjDjIbvYGMo_ADWPXTX7SQ1wJtJPU\\\",\\\"y\\\":\\\"-UsvyRyg8mSLHEQ4PoJPEzfAYuak_Vjn5326uW-ls6c\\\"}\"}', '{\"ik_dh_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"_WZXQXNNG-o8R4tCPLD4tAXzU42nvmHO982ttOqycMI\\\",\\\"y\\\":\\\"9bHoWj4MXAZcNl8EeAwqIb9z5-0mC268-JUeLHzMlxU\\\"}\",\"ek_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"SoipmXaLQPulrARuvrTmJu9fnoeJbqOx0pGupYNH4Lw\\\",\\\"y\\\":\\\"S7OwE4FXesa3Ld7cXct4Fhk_1ovLhYxs4fTWzEbDzsk\\\"}\",\"spk_id\":1779525451026,\"opk_id\":177952545102601}', NULL, NULL, NULL, NULL, NULL, 'I7d2UeI=', 'Yo3/r/5HANm/8TWN', 'nqF7+duJ8F6nXubXKNI/4Q=='),
(5, 4, 3, NULL, 'P7eR9w==', 'personal', '2026-05-24 09:06:18', 'Yr55Z+X0Ij8aH6x1', 'JZs1m+kt77Bn2DXg8iiopA==', '{\"n\":1,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"GXEfYNgiREpHoScjDjIbvYGMo_ADWPXTX7SQ1wJtJPU\\\",\\\"y\\\":\\\"-UsvyRyg8mSLHEQ4PoJPEzfAYuak_Vjn5326uW-ls6c\\\"}\"}', NULL, NULL, NULL, NULL, NULL, NULL, '31Qniw==', 'O/jU40wXNEG3BGFD', 'dtF26fd09ScAPjlyGJkjzg=='),
(6, 3, 4, NULL, 'Dz3YuqFJM9cXNo5VilYXL3vkPCOPV3xDZorT0usmV6MjavmMTJF13ckNo/5/B7/d7C728feAo1/aL20/2iFq40W3h7Gio5Tkqblf0uf5YxeOWo1p7GVicRle0aYVazSusgtjqCJDEz552uzA/A==', 'personal_file', '2026-05-24 09:06:27', 'hQJ+XpoceC9lI+1A', 'qOhMks+5NNHM9WluiHuzaQ==', '{\"n\":1,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"63y4_I2iJeMB3orzNY8VHMctXLYh3OOH9fG2DEvT77o\\\",\\\"y\\\":\\\"ZUs_0oje7STGbxq0l-rS-JcVbPG9Rn74CGU6qMkbPBQ\\\"}\"}', NULL, NULL, '3rd-Receipt-Muhammad Zulhilman Bin Tarmizi (1).pdf', 30964, 'application/pdf', 'uploads/encrypted/5e275cb946a2abc672b56c14d9dc6273', NULL, NULL, NULL),
(7, 3, 4, NULL, 'dt6NY34bpg==', 'personal', '2026-05-25 07:23:33', 'iDaOi91Tod1Vik0u', 'gp4t14QPIHo3LHnpu6h0bg==', '{\"n\":0,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"OR3oh1QsRqYZLdy5o_U24I47OG9mqpTfMYfnRQmfpSo\\\",\\\"y\\\":\\\"0SK78mXKX90Z8VzhewFlvQi8EK34-NRqCSSgmU43rYg\\\"}\"}', '{\"ik_dh_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"qV6_zAu944fNvul_FMcDYNllvXJ13wgB7pofOhHgcjk\\\",\\\"y\\\":\\\"oWSno2K6izNuq9GZIrI0VgfdtDyXfMWAAFFAARQaSu8\\\"}\",\"ek_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"G7t_d0PzLMvuLqqiLb4_WD7aynwJvre5i2X6m1t-qVI\\\",\\\"y\\\":\\\"ipAc0zBHsr782i0GQpynTjS27gCj9Dw9s6uuprspwME\\\"}\",\"spk_id\":1779692827493,\"opk_id\":177969282749300}', NULL, NULL, NULL, NULL, NULL, 'ColOoh/ctg==', 'hPin9ELZl9IHLv6X', '1kpZwMniJz+fOc9LlhjcAw=='),
(8, 4, 3, NULL, 'WD6NPm+dBsDq', 'personal', '2026-05-25 08:13:23', '/3dlTaQ+Edx3T/Nu', 'MHcmUcGBlME7t2oqtmFz5Q==', '{\"n\":0,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"d4PXzxwUFhsNBQd0OVFJjLtDNee5HI6XCUQk_-lyhlo\\\",\\\"y\\\":\\\"ozLUFEoR5IggqVtF3aPth9IznzLJWGSnGD266oo_Zog\\\"}\"}', '{\"ik_dh_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"_WZXQXNNG-o8R4tCPLD4tAXzU42nvmHO982ttOqycMI\\\",\\\"y\\\":\\\"9bHoWj4MXAZcNl8EeAwqIb9z5-0mC268-JUeLHzMlxU\\\"}\",\"ek_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"Gzn9O5Adm78OUlnd4VLcv5Kp_FwJYgY1vKKaUxpL0xE\\\",\\\"y\\\":\\\"JMQYZyKJfHhk8JlkHvCTNBvHYMMW702ui_W0wq1siuk\\\"}\",\"spk_id\":1779525451026,\"opk_id\":177952545102603}', NULL, NULL, NULL, NULL, NULL, 'JmUg4inQSGNX', 'xmIlkcebB2ELjfNG', 'aZW5bVmB97aDP+OPe+JLpA=='),
(9, 4, 3, NULL, '9u445Z/GwOBa5hFWMye5EAfPSTv9LuB7', 'personal', '2026-05-25 08:13:31', '+ezhj1Y2gvIZbLyk', 'ADmCC+6FXP+/KxSyiIcO9g==', '{\"n\":1,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"d4PXzxwUFhsNBQd0OVFJjLtDNee5HI6XCUQk_-lyhlo\\\",\\\"y\\\":\\\"ozLUFEoR5IggqVtF3aPth9IznzLJWGSnGD266oo_Zog\\\"}\"}', NULL, NULL, NULL, NULL, NULL, NULL, 'P6YA4PiLzLc0xPqyuvbf2rhM4RoR70/2', 'fiefyX72u0UuVA4B', 'AXEQThn7K6EAt4xz4vQTzw=='),
(10, 4, 3, NULL, 'LMAMDw==', 'personal', '2026-05-25 08:13:57', 'ahHvjhQpiLz+0qC0', 'EhyoCzniAo+OtMsqJAQsrg==', '{\"n\":2,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"d4PXzxwUFhsNBQd0OVFJjLtDNee5HI6XCUQk_-lyhlo\\\",\\\"y\\\":\\\"ozLUFEoR5IggqVtF3aPth9IznzLJWGSnGD266oo_Zog\\\"}\"}', NULL, NULL, NULL, NULL, NULL, NULL, 'JylQMA==', 'HeY7oA9Gei4NFaY4', 'jPpDTcXjywFhuANG0tTbYg=='),
(11, 3, 4, NULL, '2PUMRt5PKTfnFQ47OU/Kk4ha01M=', 'personal', '2026-05-25 08:14:33', 'Jtm9imisA8B/BmDk', 'kDAAdamoQNuS3RpRqdgkCQ==', '{\"n\":0,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"iIh4sheF_AfRVEBzRvhb_aig-A9dPkC-MNG9ec1ltfg\\\",\\\"y\\\":\\\"uE-jPUb3XId5GpszkGCD-8W9bOs8Il7j0Zamp7h47Eg\\\"}\"}', '{\"ik_dh_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"qV6_zAu944fNvul_FMcDYNllvXJ13wgB7pofOhHgcjk\\\",\\\"y\\\":\\\"oWSno2K6izNuq9GZIrI0VgfdtDyXfMWAAFFAARQaSu8\\\"}\",\"ek_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"i5gvXzbSFPgV8lgfPZvTN7L4J6ANwEcBYOq3nNcKGLI\\\",\\\"y\\\":\\\"H0wyB8Wl0wJpeTywBx88gfLcYYnBhddgic1uF3kZyEA\\\"}\",\"spk_id\":1779696188114,\"opk_id\":177969618811500}', NULL, NULL, NULL, NULL, NULL, '3V0TYCGV85Na+qaCbquwve4FRKs=', 'f5bRIG9HGGJyW4CU', 'ecfzc/8dOb3JbqvVVPknbQ=='),
(12, 4, 3, NULL, '+7O2RQ==', 'personal', '2026-05-25 08:15:09', 'TrZrDshfZ/49LFWy', 'L9kBv0bGB9R+PmL9Qf5MbA==', '{\"n\":3,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"d4PXzxwUFhsNBQd0OVFJjLtDNee5HI6XCUQk_-lyhlo\\\",\\\"y\\\":\\\"ozLUFEoR5IggqVtF3aPth9IznzLJWGSnGD266oo_Zog\\\"}\"}', NULL, NULL, NULL, NULL, NULL, NULL, 'movanQ==', 'dV270gRz1PjfD5cq', 'sD9+bqVwI+bK5x95RPu7jg=='),
(13, 3, 4, NULL, 'V+akNZWoW0wAGv07r1TusliP5YHuzVqw3/4K4NHrpqA=', 'personal', '2026-05-25 08:20:17', 'and39yMIMfEjzb8v', 'i6FM20HC/CVSqn3VZOjUeQ==', '{\"n\":1,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"iIh4sheF_AfRVEBzRvhb_aig-A9dPkC-MNG9ec1ltfg\\\",\\\"y\\\":\\\"uE-jPUb3XId5GpszkGCD-8W9bOs8Il7j0Zamp7h47Eg\\\"}\"}', NULL, NULL, NULL, NULL, NULL, NULL, 'PmsbptnD8sqMQtrSxQMNySxcHjR7m840VujeGLmZies=', 'RmmprJYww36/eTb2', 'E3K+1oXJ5ZLh4q4pcZrW1A=='),
(14, 4, 3, NULL, '3LV/exL4', 'personal', '2026-05-25 08:20:46', 'PnRX/MeUvzPoDZYo', 'Tvgmegk1iQvwjjSOlsPdCg==', '{\"n\":4,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"d4PXzxwUFhsNBQd0OVFJjLtDNee5HI6XCUQk_-lyhlo\\\",\\\"y\\\":\\\"ozLUFEoR5IggqVtF3aPth9IznzLJWGSnGD266oo_Zog\\\"}\"}', NULL, NULL, NULL, NULL, NULL, NULL, 'WaOl7jcE', 'aycDrQEZzbIOODwf', '09VI78FJLwmGuPFSR+eY1A=='),
(15, 3, 4, NULL, 'ZIs=', 'personal', '2026-05-25 08:20:54', 'EYutIybAIitJy+g9', '8cMUo9YcrIBSH+0f4iAB1Q==', '{\"n\":2,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"iIh4sheF_AfRVEBzRvhb_aig-A9dPkC-MNG9ec1ltfg\\\",\\\"y\\\":\\\"uE-jPUb3XId5GpszkGCD-8W9bOs8Il7j0Zamp7h47Eg\\\"}\"}', NULL, NULL, NULL, NULL, NULL, NULL, 'qdQ=', 'h0hILNXbPCA7v564', 'WTFKWhH9fasYVjRBke1m/g=='),
(16, 3, 4, NULL, 'YN9/lbOrOA0+MgV4RXd7Jz560R9w/lx6oUaJh+N8V1GfrS1YSvmC9NaZP4fudgOdBgvBuK50H+PfX+pjqyDDg/uMI8dv87S7PLO6wt+Xiq5gY/0PK7wZJX3PtCdxktmHcMQg5+e97Dx65Phb+w==', 'personal_file', '2026-05-25 12:00:12', '3DQTigCa7bPofpqw', 'hirs27h7o+aWtAtyBsJvxQ==', '{\"n\":0,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"wBKgaosS7GtBfEUC0v1MbOtY5Tq3evOqeorapbSoL0A\\\",\\\"y\\\":\\\"rNYYtwQGPc7VL0YjQO6iDFqF7-pkXV0ZIKRtRWuBlrI\\\"}\"}', '{\"ik_dh_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"qV6_zAu944fNvul_FMcDYNllvXJ13wgB7pofOhHgcjk\\\",\\\"y\\\":\\\"oWSno2K6izNuq9GZIrI0VgfdtDyXfMWAAFFAARQaSu8\\\"}\",\"ek_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"gV1fCF3oIIvV4rKybjbnYksYx5WDUMM4Ro6bgT_ej-Y\\\",\\\"y\\\":\\\"bbpTHrUMRmM_oQrN_OWC0V10LnqbplyB_Z6jntTnTG8\\\"}\",\"spk_id\":1779696188114,\"opk_id\":177969618811503}', NULL, '3rd-Receipt-Muhammad Zulhilman Bin Tarmizi (1).pdf', 30964, 'application/pdf', 'uploads/encrypted/2a955ef073019d8c7631da17195a3758', NULL, NULL, NULL),
(20, 3, 5, NULL, '/2nz', 'personal', '2026-05-26 00:50:56', 'tMhCLBojSDefu+wB', 'AXXGE/WHzB3KT5UH5pjmEw==', '{\"n\":0,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"kTGLz2NO6F6zgQVaJVI3ZAQoIE-mAPgLaDiGfGYzqQk\\\",\\\"y\\\":\\\"hXZ_6XUPTC79PmkSUcZtgokpCkcFUeXDC8SMLQUN7T0\\\"}\"}', '{\"ik_dh_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"qV6_zAu944fNvul_FMcDYNllvXJ13wgB7pofOhHgcjk\\\",\\\"y\\\":\\\"oWSno2K6izNuq9GZIrI0VgfdtDyXfMWAAFFAARQaSu8\\\"}\",\"ek_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"3eJjb1huIYXyJiwHsAU1nbO3CBH2RFaTG2Cmr17o4pk\\\",\\\"y\\\":\\\"OVvf562LJgrMOeMpKbXc2mOOTR2N-CEkgpgi5hxH9m4\\\"}\",\"spk_id\":1779756348457,\"opk_id\":177975634845700}', NULL, NULL, NULL, NULL, NULL, 'VRqX', 'rcekLAGQeY49++VA', '+3IO17fhMVNu+qcb0JhiDw=='),
(21, 3, 5, NULL, 'HN482t4ESsWWXw==', 'personal', '2026-05-26 00:51:07', 'pmjhv50zFpO9srHy', 'gotrvWTiLXCrNqouUlg+FQ==', '{\"n\":1,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"kTGLz2NO6F6zgQVaJVI3ZAQoIE-mAPgLaDiGfGYzqQk\\\",\\\"y\\\":\\\"hXZ_6XUPTC79PmkSUcZtgokpCkcFUeXDC8SMLQUN7T0\\\"}\"}', NULL, NULL, NULL, NULL, NULL, NULL, 'vV4wDx9/r//ZOw==', '67RQryWUSR3Q5+8a', 'baxZtR36sRRI5zAG9ywv4w=='),
(22, 5, 3, NULL, 'jBt7+hMl86bd/v4X+I+pVA==', 'personal', '2026-05-26 00:51:23', 'kdu3wuSihx7EnyJG', 'CP7ffoDjsiJiV9WBRhSDUw==', '{\"n\":0,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"OIp5N0-i7ucbZOvmA5ff3dM6OUng3qX4HLT237yqY88\\\",\\\"y\\\":\\\"sM_wgISU8A5utQ8f0Uci7AA45nA0qoHppI9RPG5Ezxs\\\"}\"}', '{\"ik_dh_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"OJ5j8w3gX-nA9Cpq4rkfHv6qhn-X-Mru7AiLMIJgioY\\\",\\\"y\\\":\\\"Z4UKjA9EYhHI9bOrY5ip5xsW_8Q4W_-KJXE17-iQEt8\\\"}\",\"ek_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"NQfG9iIhn4qmGbf2YO_U54PvS2QJq5YEWm6oCwUGuvc\\\",\\\"y\\\":\\\"6P_FdPIaabNvhEGMLG29mRPUlsGV2jDjEDeLmLz9JQw\\\"}\",\"spk_id\":1779756336837,\"opk_id\":177975633683700}', NULL, NULL, NULL, NULL, NULL, 'z1ZZLM+QeXGjcXdJUeQp4g==', 'Ol5KnY+dhybOE15F', 'aOpX6XwzZ5+LVVK/CeSc6w=='),
(24, 4, 5, NULL, 'CsY=', 'personal', '2026-05-26 00:55:13', '9Nt13bybUa5mc4KC', 'LkdbmUPWagcWIkv1WOkTXw==', '{\"n\":0,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"EJxh9AhFho44R1P-xv2tDUle68lrEHmXn9dL-D2QLnc\\\",\\\"y\\\":\\\"ef6rp6zADLdExaK1q0cmYtA1gaX-qadcObHWMZXbzA0\\\"}\"}', '{\"ik_dh_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"_WZXQXNNG-o8R4tCPLD4tAXzU42nvmHO982ttOqycMI\\\",\\\"y\\\":\\\"9bHoWj4MXAZcNl8EeAwqIb9z5-0mC268-JUeLHzMlxU\\\"}\",\"ek_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"0H6V1XyqmOCp7k2SFm-xljbhNRRc394SZzbrYxg_LSk\\\",\\\"y\\\":\\\"swm4YCsnoyD3vUc60FltolhTvJI6hs4Lp22SVGXEUME\\\"}\",\"spk_id\":1779756348457,\"opk_id\":177975634845702}', NULL, NULL, NULL, NULL, NULL, 'z4k=', 'VE02jqPihRD4YdpC', '89/OVFRmNEBHiRQD3cv1og=='),
(27, 5, 4, NULL, 'OgKv', 'personal', '2026-05-26 01:13:18', '33JPf8yOHQr5UvUL', 'Ui75AGGRTEtm/1Y2X15tgA==', '{\"n\":0,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"VMZk67FaWvyS1tBRXrmmuAI7FtHDAOkYY-vpEL2BOMg\\\",\\\"y\\\":\\\"Ohhs60Uves_EfNEQ74zyWepdHBtaMPTXxPUAQItIps8\\\"}\"}', '{\"ik_dh_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"OJ5j8w3gX-nA9Cpq4rkfHv6qhn-X-Mru7AiLMIJgioY\\\",\\\"y\\\":\\\"Z4UKjA9EYhHI9bOrY5ip5xsW_8Q4W_-KJXE17-iQEt8\\\"}\",\"ek_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"fyAdHuIVgyMbVssiv5OTHe2qnL3im_HIzTYZ-GhWxb4\\\",\\\"y\\\":\\\"7zs4WtPcoZIuyqQaERe1wiu3GZ5J7yqkiK1zO-ClmW4\\\"}\",\"spk_id\":1779756842266,\"opk_id\":177975684226600}', NULL, NULL, NULL, NULL, NULL, 'csKp', '5HGnQiXsQ1kReLLe', 'gnuIrhKEQ5EtIF4YKpdX1g=='),
(33, 3, NULL, 5, 'WLw=', 'group', '2026-05-26 01:33:00', 'Nw16CGJSpuQGInEf', 'hg0Oz/AbGRTc4IGZrP8f7A==', '{\"type\":\"sk\",\"iter\":0,\"sig\":\"mqq1mHLzP7z3MTLxClUunhwrT90Dye9H+6FTPrZHcAvOCgh1WWjMT7Slti5aG7lHk7UVmzOfgoV7baB1nbxFug==\"}', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(34, 3, NULL, 5, '2uX7ONKOjdjqsKto7mLpPg7S7yol', 'group', '2026-05-26 01:33:20', 'EZ+o8wLZuyMKYEDD', '6xOVx6vv/S3CbCcgCf8WGg==', '{\"type\":\"sk\",\"iter\":1,\"sig\":\"jghha/6zqdbkhTZGNBlQk3q8WGWjQKv9IExZ2l+ToBcDy/SZy+fmYPbjz7I+zJnbPbJkJsKXELMv319NlGEA+w==\"}', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(35, 5, NULL, 5, 'JsNTuVik2CXf4qbRV1Adp5VJtQEQwI+ZeIKkV72NRwXpuD8CUxRCWhYyXphK9/umwPH42qpoYHLIanP1K9Mm+nmHeHvpz/LnxAjpsmgxBAclb51TBKvn5VYeN788OCQnpTRpz+Eial6c1/9H4Q==', 'group_file', '2026-05-26 01:34:35', 'uQsAQt48WO09vivR', 'e03wal+PR1fM1IgEtqpDIg==', '{\"type\":\"sk\",\"iter\":0,\"sig\":\"NZf+5HmP5dG6NA0Nu8KSkKrcVrC1436KsXNDEaC/1eO8EsIIwOhVcKqi1UDyprr8oLC++xKXLsYrs6DJ7aqsRA==\"}', NULL, NULL, '3rd-Receipt-Muhammad Zulhilman Bin Tarmizi.pdf', 30964, 'application/pdf', 'uploads/encrypted/654d96931db4a9c12d19f515b6072573', NULL, NULL, NULL),
(36, 5, NULL, 5, 'F32ONVBDF96+iQEhCyZQtriugLpoDpStIoAZUO4=', 'group', '2026-05-26 01:34:35', 'tnBQpa7bJCzBXJp9', 'xiJ/NJ8EwaJKO7FYgRenxA==', '{\"type\":\"sk\",\"iter\":1,\"sig\":\"9mx2I5/2rfmSxe5dgrwqIarWu0Hl0rmjTeaY0R97MQA6/44oI6lKa1nBUoImrSn4mYTQ8S7VCbYhPz2RViSanQ==\"}', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(37, 5, NULL, 5, '9/8=', 'group', '2026-05-26 02:59:21', 'eSRmX3DyvQSFiUCa', 'e9+NzWfwqeUSxvLObv3HVg==', '{\"type\":\"sk\",\"iter\":0,\"sig\":\"cD0PnMMmk9iDF2jjTFlfR/gvMm1iSx2rtrDoZGh70LwujOutOR3SvNlwscBv8Fbf3lvdw+Q0Wfsy008zTNL+vQ==\"}', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(38, 4, NULL, 5, 'SNI=', 'group', '2026-05-26 02:59:28', 'kuEvHmFsNwIBIcEa', 'pDCKY+GKwT7Kbp9t96avAg==', '{\"type\":\"sk\",\"iter\":0,\"sig\":\"KAMmi2H9KeuOwDG5xyAJhM4ztEra66u8ImFcx+IaeBvg0vsP+9NyAG149MxxB7vh2E8ZyZUghcAgTjIoKGRShg==\"}', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `recovery_otps`
--

CREATE TABLE `recovery_otps` (
  `otp_id` int NOT NULL,
  `email` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `otp_code` varchar(6) COLLATE utf8mb4_general_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recovery_otps`
--

INSERT INTO `recovery_otps` (`otp_id`, `email`, `otp_code`, `expires_at`, `used`, `created_at`) VALUES
(1, 'tarmizihilman13@gmail.com', '858390', '2026-05-26 02:09:04', 1, '2026-05-26 01:59:04'),
(2, 'tarmizihilman13@gmail.com', '411628', '2026-05-26 04:47:48', 1, '2026-05-26 04:37:48'),
(3, 'tarmizihilman13@gmail.com', '808234', '2026-05-26 05:03:12', 1, '2026-05-26 04:53:12'),
(4, 'tarmizihilman13@gmail.com', '536209', '2026-05-26 05:10:32', 1, '2026-05-26 05:00:32'),
(5, 'tarmizihilman13@gmail.com', '344673', '2026-05-26 05:13:21', 1, '2026-05-26 05:03:21');

-- --------------------------------------------------------

--
-- Table structure for table `recovery_requests`
--

CREATE TABLE `recovery_requests` (
  `request_id` int NOT NULL,
  `user_id` int NOT NULL,
  `reason` text COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('pending','approved','rejected','completed') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `approved_by` int DEFAULT NULL,
  `request_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_date` timestamp NULL DEFAULT NULL,
  `new_key_hash` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rejection_reason` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recovery_requests`
--

INSERT INTO `recovery_requests` (`request_id`, `user_id`, `reason`, `status`, `approved_by`, `request_date`, `approved_date`, `new_key_hash`, `rejection_reason`) VALUES
(1, 5, 'Forgot password', 'completed', 1, '2026-05-26 01:59:17', '2026-05-26 02:57:36', '452e02fee75850bd8533f1dbe86fd0bd2f87652e5786fda4585c68121b375249', NULL),
(2, 5, 'Forgot password', 'rejected', 1, '2026-05-26 04:38:23', '2026-05-26 04:41:34', NULL, 'Too much request'),
(3, 5, 'Forgot password', 'rejected', 1, '2026-05-26 04:53:27', '2026-05-26 04:58:03', NULL, 'Not valid'),
(4, 5, 'Device lost or damaged', 'rejected', 1, '2026-05-26 05:00:45', '2026-05-26 05:03:11', NULL, 'None'),
(5, 5, 'Account access issue', 'pending', NULL, '2026-05-26 05:03:38', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `signal_prekeys`
--

CREATE TABLE `signal_prekeys` (
  `prekey_id` bigint UNSIGNED NOT NULL,
  `user_id` int NOT NULL,
  `public_key` mediumtext NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `signal_prekeys`
--

INSERT INTO `signal_prekeys` (`prekey_id`, `user_id`, `public_key`, `used`, `created_at`) VALUES
(177952545102600, 3, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"IjZGfGpDvT3s-TYUzZUNWWHAsEbBe2j18kAYy_5eFEU\",\"y\":\"4_KK6mskCOMxBOYMe5JX6Q9XMuvs7ecXDZ-8l3alxak\"}', 1, '2026-05-23 08:37:31'),
(177952545102601, 3, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"UcdX3CujhpPP3Ns7jp3GCDshxPJl1FSrXMnZ8M91i6o\",\"y\":\"zgHEBqdZfRaPKcsd5AKMFCLYXUUCgMHUWiRGGH5_TWM\"}', 1, '2026-05-23 08:37:31'),
(177952545102602, 3, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"HH-YZZH7tC3RiUrzd9beC1Wy-hTxcoEfVUFbqEytbKo\",\"y\":\"kVI_pKD3EE2t2AKTVOdls3wxVGVNKFgvOmZGJOsfhc4\"}', 1, '2026-05-23 08:37:31'),
(177952545102603, 3, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"nqqosd1LMz3tUr4MkMHy2PNYt6f194VrPlko6Y6GKE4\",\"y\":\"AohfYBD9OualZChb0KzjKK3CPGtbbx64xJHxoFfGjbI\"}', 1, '2026-05-23 08:37:31'),
(177952545102604, 3, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"gdyHml-B0FCf6ckLnfZ_a6vaKw5bUaNDKXXeZwj6UwM\",\"y\":\"HYZPQTUBYa3UJFwzHpgDMCpj8OwzE5vCizZPv-GTdZg\"}', 1, '2026-05-23 08:37:31'),
(177952545102605, 3, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"KYCoCalupqztBWCDMaftS4tTWZlGAxFpI24wKXt3n8A\",\"y\":\"LNAccnDRNokR5zmCBpVd8RZE-ohin67CEhct8jbhaIs\"}', 1, '2026-05-23 08:37:31'),
(177952545102606, 3, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"KUJjabapdl9uk4Wsde1TyDsJCS7O5AE87A1kNd_iYqI\",\"y\":\"yuudm0bSdISb9WBss0k3k9rBzqPbsO-hWnNGVbSQfn8\"}', 1, '2026-05-23 08:37:31'),
(177952545102707, 3, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"FMtN3gUznMQjSHr-Wy7xjcWm9B9Vr9KZRHgyhmmcrNU\",\"y\":\"XJ74ZDZJjFa-IGg2Xgc8dp9uwX51jEjggNj5xpIJbOQ\"}', 1, '2026-05-23 08:37:31'),
(177952556249100, 4, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"KJiUk9QHOH3ICodLBZax8lzfbxpiGK6AWsnfMLETSik\",\"y\":\"smtUOkYL6u40rbX8K1VcZfZPDhzv0alenYwYniL-uC0\"}', 1, '2026-05-23 08:39:22'),
(177959353616900, 4, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"F18fiG5PMjLHui8TwJBYlEnqRJ0bs2Y2EA_-V_FdXKA\",\"y\":\"hO8rUtN5eUuhIhw6FF2zLxMAm4Jm_WTw_EQKAl95_Mk\"}', 1, '2026-05-24 03:32:16'),
(177959353616901, 4, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"8icQda4M6Yl60e1psmGI_IczEIvXHblgAcsCoKhPseM\",\"y\":\"7xd0co52mKX6GRsNxxvrjFSGI_El3BAOLKSv80KwEuc\"}', 1, '2026-05-24 03:32:16'),
(177969282749300, 4, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"GTlA7130G14YLbn3YRAU68bnxE0uHIBJd-UX1ftOIWI\",\"y\":\"bbe9uhP6NF15R-4yYUgkjuwqCOIBdWkJfgtT5KnjO3Q\"}', 1, '2026-05-25 07:07:07'),
(177969618811500, 4, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"Huk1zh8sutyr8VgTi31PZf_orqMdjiYtfO82__-hoks\",\"y\":\"_GlHbMG0OiK-uf4G5pPPBTRg12uCKw5S8MlYZx6t5A0\"}', 1, '2026-05-25 08:03:08'),
(177969618811501, 4, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"8mxCwL6yTSrOuSQoecHznG3g_ICyo04lXx_veIkefsc\",\"y\":\"uQc-04yZ-W0fz0kq3jP7k3Hp2GJKsBvW676klovpck0\"}', 1, '2026-05-25 08:03:08'),
(177969618811502, 4, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"T7C_3iomwEggbzfzxXhpIRgkrcGbZv10lBVMC2Q_Iic\",\"y\":\"kwQYSgkX0nSfCvFzY27uyRUMA3Q1FTkyYlUP-lDKyqo\"}', 1, '2026-05-25 08:03:08'),
(177969618811503, 4, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"O0-8RQO-_zV2jFrhrpSu0kY5saNkyaM9j10soMUERvk\",\"y\":\"JHc5EtxXhEPa7b_g2j628ejgxqqP0wftWN6VV63i2ho\"}', 1, '2026-05-25 08:03:08'),
(177975633683700, 3, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"PbGt-y_uSmGbf-W377a0VbvcJPLMlUM_whQcTPvV1Io\",\"y\":\"YKYsozwc-nmZ-ySUXXrSG2byTxYxr_mg-jvlOuwtmek\"}', 1, '2026-05-26 00:45:36'),
(177975684226600, 4, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"m9vgH82HSMgLpIc1rJzBKNV9YuUUJE9cHMfSb3-wLVc\",\"y\":\"gxwjp2QWASGHvbBjMbQkfrybXwZP4LxJrPuuOT8gHGo\"}', 1, '2026-05-26 00:54:02'),
(177975903080800, 3, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"w6x7Ik4uUIdhH_ld7B5GzfDx5P16DRKqFzmfs9acLCU\",\"y\":\"NkrfeGQpGmcp7vZZBY7Rq6ajZi8LRwYMKDuHvaoJ-3k\"}', 0, '2026-05-26 01:30:30'),
(177975903080801, 3, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"QWoykg_FgzcxJKvRwuQvmoJ6BheL3EX4Gcivc-meI5c\",\"y\":\"9JxZGG61Bhn-thVSoLMCur8ydeGvPNKDcQKWrI1B5mo\"}', 0, '2026-05-26 01:30:30'),
(177975903080802, 3, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"7LoyHwTwjn11chE_z04RLYbdqtJ9oB5zsMCLRgmY_pA\",\"y\":\"Om18uTtqaJZ001NXsdGq6-jeLGTOgQuVN3mfZi2v0pY\"}', 0, '2026-05-26 01:30:30'),
(177975903080803, 3, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"fKe3WRzZVBC14FFVg_g4SHbfSPPNE2_M9sRnmNyLzmc\",\"y\":\"aUyV0Nj6YjH9EtRzZBF8Mpy5bledKsO5QKxM1LaJSDE\"}', 0, '2026-05-26 01:30:30'),
(177975903080804, 3, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"3vmgSOY_H3KxuorYv5I-lLRED-iCIZn8cLa7USOc-_8\",\"y\":\"ZTsfSZaMgT1VQlcHyyfKMgPN0qBKIJaGmqH4Uh__agk\"}', 0, '2026-05-26 01:30:30'),
(177975903080805, 3, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"wugqPAFah-9vFa9iqe5-Yj1X1Mu2h1hRcXqnoS0azAQ\",\"y\":\"UdH2u3_XBF34PGl5-OtA9AQjn5MFzdByQr9gdt09tN4\"}', 0, '2026-05-26 01:30:30'),
(177975903080906, 3, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"OREthxojJ6-oK7UEGdLLdMJ08Qb5SqeT7RPD0FoH8w0\",\"y\":\"Kg6Axo4nPb6y3zGxnd-VkQj0UcJYE2XNzDuhUw28MAM\"}', 0, '2026-05-26 01:30:30'),
(177975903080907, 3, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"9X690wRONVIQOe0kbY5pLX2Jg03D7a1RdhLxQyfDDhU\",\"y\":\"SvL426ierW7HWSQNTmM1AbmpWG1t4b2ykgmdA2p-rp4\"}', 0, '2026-05-26 01:30:30'),
(177975903080908, 3, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"4ssZ2DNR85c2mw4rVqGf7LBsmc42YQ1Q6YHHawCA-2Y\",\"y\":\"kk5bmnN1yRD1L7qwBT8JDm_goVmdfpBsvnkP7awzekU\"}', 0, '2026-05-26 01:30:30'),
(177975903080909, 3, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"FzDSjbQfNjMIZcvLYAcnf_MKViznoZUnipIpqO-TQVc\",\"y\":\"_pcFl9Zmb_M9qgezv6AzswrSiRbk_uV3Iy9yueiYq4E\"}', 0, '2026-05-26 01:30:30'),
(177977166915200, 5, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"iiv0ru8epK7ZmmyWzi45pwuWwV7jjD_wQldmDHI_yZY\",\"y\":\"bFVz4SlzwO_Sm5FIntcCjP23O5BKnebjtJioCMUDe-8\"}', 0, '2026-05-26 05:01:09'),
(177977166915201, 5, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"xHJYbEkhctWXp8ZdN6sMCMkDvDiZ1jgl9BfVcmGSBH8\",\"y\":\"S2oI3Fieb8Gn0a46BAK3f9dVz3OmK7TC_fXPNWg-8lg\"}', 0, '2026-05-26 05:01:09'),
(177977166915202, 5, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"0Lb6dCCS3uDYYj_WF-2sBkVoMHsCB3uoMOIJ51MPqBg\",\"y\":\"e8jydkmHjygc4bAD5bszAmHIUjPdbwhXyXzhMYjScVo\"}', 0, '2026-05-26 05:01:09'),
(177977166915203, 5, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"MJUNx7Bc5V8bp7CBRa-eQZsLXozlrBTnwNesCaPB63U\",\"y\":\"OW-0-uUGyrItyGB7Cae-SPhdBvh7nive8BvM5aLZE0U\"}', 0, '2026-05-26 05:01:09'),
(177977166915204, 5, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"w5QxNOWxpDaJ9dgn3J-sCy8E2S_gPJ0sLfo-9JS9sVA\",\"y\":\"8Mx1TTrR2dbHoyh7X1feUNxEUe_yQO64wyB-E_h87KE\"}', 0, '2026-05-26 05:01:09'),
(177977166915205, 5, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"lyn3uXDZlWZSaGKxlq72ZX6LqOTwKD0pQ5MpJR-waK8\",\"y\":\"8eiiYTjwqxWl1Uz_qVmLGRbp-YKvmX9Yyu2gZzahC2c\"}', 0, '2026-05-26 05:01:09'),
(177977166915306, 5, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"Q1UTgqkkQ_rGpFBIvvAtSEhrXkIXGu_MVogbeNqYxLA\",\"y\":\"136GVLNP8mu8-Z-hw8u8rUzrTfSVc-0uixyg0CW12N8\"}', 0, '2026-05-26 05:01:09'),
(177977166915307, 5, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"_JFy9FM0DvYTaGy44b2zUzeYtUyLpUS2iMlT6PhXToA\",\"y\":\"TXXgpTr-Drfb1Kpxr4-zBgMTlKG90MxLAeepiJXrNkw\"}', 0, '2026-05-26 05:01:09'),
(177977166915308, 5, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"ab6iKyIHwxttA__kKUWG_y08oV7JgeTM0fqwNRqF7O4\",\"y\":\"ttl0M2kd9mqv7hpKXuPiU54IWvD44q5kMryCxs-OCtQ\"}', 0, '2026-05-26 05:01:09'),
(177977166915309, 5, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"DL8Cf0qDh-GrSpqk0dgcKaVAt99yNCCuytzCdEEp-Ns\",\"y\":\"O_-gKej5K45DU5qYuT7mqUJddaKEnxPkvWLgueF-Tis\"}', 0, '2026-05-26 05:01:09'),
(177977454469300, 4, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"83Y4ey107OeMfA9dWJ7L8MPn2rbdTc2sWNqj6haD1a0\",\"y\":\"vAHmF6CO4zo9YwwAmsXhLsQmmabN5F0eizU-RrJRhSw\"}', 0, '2026-05-26 05:49:04'),
(177977454469301, 4, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"Rnr3rgrZN5mJXvPiogCrtNPhZdl2Be0QNJqNgzwYeoU\",\"y\":\"izdky3A5tFEQaLRH-B1y49mZKrsWfRAIMwCiEiAE9Uo\"}', 0, '2026-05-26 05:49:04'),
(177977454469302, 4, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"gp7izJZwUXwPpFVpvR_qMXsJrzBnl8Pfam7NPSXpYa4\",\"y\":\"mIyfVlNQnQtVa5H-nP4yx3xg7BarYI9BQIQUyPohYAU\"}', 0, '2026-05-26 05:49:04'),
(177977454469303, 4, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"cl3wA4ZlWlfnI0gzQYXMSL_9bUAhsnc30dXvkQms6dc\",\"y\":\"pyX1L1-uGE92dv3zRtJTqUShYfIy_J9JCG9Sch5qDNU\"}', 0, '2026-05-26 05:49:04'),
(177977454469304, 4, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"a1oUh88ZtWWkbJWDUuVui45u_4vRTfk2ClvzDsgkFNY\",\"y\":\"Lv53jA7lw_ZnaFYv1WLmg66vkRMlwML-F-23FV0EKSg\"}', 0, '2026-05-26 05:49:04'),
(177977454469305, 4, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"r1Tv9Vnk2HLtatC3hWX-Tm6yvJqL_KiBRvA4wb-xOxo\",\"y\":\"EnFKXMWEeQ3DVezmASJdQkd90SXnnkkxXB9-m26f7QM\"}', 0, '2026-05-26 05:49:04'),
(177977454469306, 4, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"JGl0hMuUlZ0Ikn_yPj-yD3x-y8zNxe8a9JMuYpkdZHc\",\"y\":\"jdDYRapCK7JKCiEy2iOuQHe2sUGh5MJBF24wYVN-_zE\"}', 0, '2026-05-26 05:49:04'),
(177977454469307, 4, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"QN95AcHw91u20HbHhiJAwGQI8ZiD8y03o3PQQtZ9apU\",\"y\":\"MyekoIWmu4pCaJkL0w24YojfmN3dLVW8qREhiOxDCVw\"}', 0, '2026-05-26 05:49:04'),
(177977454469308, 4, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"xfk0xxPwqQAEp99X9qQ0LwsPiOGtTGXcW6_K2ctheeE\",\"y\":\"hSvQG5ZzWSlQQLivYj0Wpvzy7Jbisy69lGOFVQmhJ8w\"}', 0, '2026-05-26 05:49:04'),
(177977454469309, 4, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"NxzD3-3zs-pNTycDWa1JCovwJZikfk2FNK1eAzB3zzU\",\"y\":\"WaONQVuqret0B68Jrn4WlHiZNDBuW7UBxTNFhGmscrA\"}', 0, '2026-05-26 05:49:04');

-- --------------------------------------------------------

--
-- Table structure for table `signal_sender_keys`
--

CREATE TABLE `signal_sender_keys` (
  `id` int UNSIGNED NOT NULL,
  `group_id` int NOT NULL,
  `sender_id` int NOT NULL,
  `member_id` int NOT NULL,
  `encrypted_dist` mediumtext NOT NULL,
  `dist_iv` varchar(100) NOT NULL,
  `dist_auth_tag` varchar(100) NOT NULL,
  `iteration` int UNSIGNED NOT NULL DEFAULT '0',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `signal_sender_keys`
--

INSERT INTO `signal_sender_keys` (`id`, `group_id`, `sender_id`, `member_id`, `encrypted_dist`, `dist_iv`, `dist_auth_tag`, `iteration`, `updated_at`) VALUES
(1, 4, 5, 3, 'q54GGyWdds9Tq69c4fttI94BtqnLxwZNACb5nEoUyIF+m+ECKIvUgVABhHHODGoKHqTn8mW/8JqrHqeid/uEYQmlw+S06OFJD/JjWzxN400r1Pkw5pcD4QuEVYhaXbFePYHhKkXaTgCxYJjF02AK5yHRbLI2yxSSkEYbT5TlCm3OF/rLQXSIY2UqYKAVU7lM8zzAwy0iK5Ryv5otV0CMnxMylQxqhJVd9h/92uWvn20Ym1GEHWgJbgcG/C3HDYUxEbcJ5xumLVX3Q3BajMlDZsrTOcST/++ze+ycDTp8YHbB3uHTZcP2rHVETDvz/CBwXy5xLST3N//nkhWshmy5+8rDjjLijuGC2g==', '0urLweCDl39lsFoa', '84CEFJMmJ+BbHJRjzRVByQ==', 0, '2026-05-26 01:13:03'),
(2, 4, 5, 4, 'mQL0eTWHkb93ZGpOpnpgKWtY9F2OEBQ5NboxGsK2gQiU6CItNszZs3nu3lk3NBQcUR9O0zYg2IjYv4ytDO2Azogfa+MR0Gh0cT2gOg1RuFIP/5REiXiFE1eHIPqzTmvcJmo9mpR+JblU7sADJ/IUd9G2zC7ByOT0LScTgzEjUP2PNmtJryoVMjbI3q6Gy9v4WJIeAH4Q7/l2GUq6KmzWWoEpBTchG0c8DLCsClqbGpLkuV5HCPeRbQM4M4J7AFqkSHLj5oI2s9DKt3DlxvjCvnuwPJMA/Gtn1iIAmpR8KrlqbPJIsvulFp0AZYw4fXZz7TSVLzd8trqwJInD9Vv/0iMJQ2kLyO4Uig==', 'CmE76nPV8jNRXjFO', 'zbS2UIxw601iglFnuYh7fw==', 0, '2026-05-26 01:13:03'),
(3, 4, 4, 3, 'f8GswR2z/kg18Yg0OBLQYj1JGrZrkXEvk2cjpX5aDL320yuVoJv0Wb4YMNEvJisLa4xQ2oqBMEozbZ9NCyubOlAUTZMdLasICtAgP+bs8xRoupGVEZQwri7FsOO3WEqF+glGq6r9uDa70JX8QKwmLLL+k0TplVqFN7/pZH/vqapPKUju3TpVzw6caZvm0sKdRPRNBqDuQkyaOqxgvJMohv58LuTykjHhhlWzr6YaIQGFe3UP7wDlT6IpCNXH5U6KJm8cwwP7XiXZj5CypulaiSqTnDFb3SFHe2HLeT4Ag4CnWtRr50fX7sVQcPaEfHE6Zrno8iWUFL5i/yXeflQUrYJOWCoaJpS33w==', 'JOn4slE+jeiJxGHa', 'ldJA9dX0o+R9hY8M/AIKjQ==', 0, '2026-05-26 01:31:05'),
(4, 4, 4, 5, 'OwQiiX0RjGCF/alqG8INDUQ+JxEuqKhj7DvCBAwwxiEKJFVnIRI+ibKzjA8fDfTNMDotEmyDLwuScEmt4Rwn/MAdgSfZKJQTOnOcsGSHEuuYjw2VpBGWYXhkLr18MfVyABgJfRVrhGdLfEzFy5dS4l3XbCHRMs3GBLUpO3lyMQFGV55+yLkYJPDfdlXekhW6cApzTHxNELUaIBXBORvIoOJxt1Mfh+2Kodd0/P67HUWVTBfCoDTx0xiXK5eDaKK6Z3kI810MQRoxrAS5YoKhJJYxtph6Oyz9CHAewVNrhv4pB3qZe6zFECyU0jq6hLq6jUbtaTZrJqaxuKEfnMBgQLR/RqFNY6pGKg==', 'JJrexAPZ7ulhrcC0', 'Qj75i/VMwR+ybT4u5LdOyQ==', 0, '2026-05-26 01:31:05'),
(5, 4, 3, 4, 'UgRaG+5Na/Q4wP226WIbZckLMkvcZPL/w1SYVNkpE7TE42c6IzltLwvWYbg3817tfWWnhoNHJHkyS+3huwiexNG4hW3XD7fI+Btbqi3+edBJEnCAGeXd6DM4w8Ckz75EIVe1WHtmrYzIy2RSAGX8w6Z5LueBUN2LqFAX0VbhehppjSpHRxRDKHt8yG2+ETrJVtsfSQIOZv115uwufGiT54ZCtjT/yCX1hn5mOoaKDAihORY3KyyBTmJWMBrWWjO8JgupgwA1j5pcO7cSWYmEp/A98CDhBbyyw84hnKevFaWj7uL94oobSDVAstDyDbngx2fRTxPEBZfrBYnnwrTg9D8nKX+vxV/R+g==', 'iZfGVj3j5ktj6ASX', 'CTyHCSQfEFHFC8LOR+Xljg==', 0, '2026-05-26 01:31:17'),
(6, 4, 3, 5, 'AustXbte5nd+l+MEXy8yIZNxLU4/+4zPhwcCXk1BDsw8Jsv2zL0sJhnfDPHx8y9Gq0ZsMyIMjgZOQwstQuR3koDXBz36Z03ZpbSmB4uuziERBVD7fuEgHWFmCKDGYOLoIDmDLz8KWY8T/EO4umipovTwQ3EA8P05SlVJVNV+8MPGEEbRqadilSNVyZ5dY2HUQ2eg//ue/ChnvdlMyIfJczxep+3y0GGdq9QR+MZfoM771i2jw+LVlYJYeExMHnDOmfSSiz5IxRQHfB3HlJvxjHhAZOSKbEFiYsMIQchtoKKInaAvJKLgEl3P+pMoIUHkLsO/qABY6lXnRXRwKnD2gl1TpQ+jtG7EQQ==', '3BvVpPQsx4fdQzlO', 'wKk8+oCJEHj3/Qa03EwlHQ==', 0, '2026-05-26 01:31:17'),
(17, 5, 3, 4, 'BDVsfDASpVpT7T7KkHQ8ZcrZL61gHRAz7dMOrR8fcNoYZV6nSSSWJ/gLNipirsNZ2kfKnd79kWudkqlXEfwvfnhbjQWkcNXEhHk+Hzgan7fxugIM+LQuJa9dCCZFlu7wvofHXEjxnFH8LBzxPwyLPsx1a4rucR4QWDEuWrvcSRMtJbTbSQuWoeoZh0FMCu8jmGPCmm00vb9gHHOy/BA9e/nvpIRy+I52SunWYcEjHhjMSv5cb9V2N7/j1JHZNjYeclSVBEf9lYSTBlxZgObyunv9i3+iiGz7gVYLTdMH4VjXO7MRBGLP2yF63n+139VKDRUNh3OJMLF6Jj+4uHvUSGuZyJt7LkhR1w==', 'opD5KbFYQSkSdTlO', 'Rn2PNFH0o4fO6CQkrj+iTw==', 0, '2026-05-26 01:33:00'),
(18, 5, 3, 5, 'icMN6j+4nZ35QzHuLeR5Sqd7aE/CzK9iOSIPyAJMvcUcyJ7uMgU/b8Fpkq0L0bWpQehZIByROPXdeKrBAQgSm5/6u6OrqgddWAwqVO4d5bCytWB0C0EZN+3L8lv6ptZA55nlyunSiFVYm3kj3hj6W55qxmSv5MpZJJFItdq2ngm1F43QLWJJQach2P1tXrC/ID0hKp57VJYMKLW7bljG+jJm574xiR0SYV+XLvgT3IphBlS7yIv4+cJHcIT1mtDpafLt/VbsMQjDSboQRvQ+u2gOSp5YfMjEDj6mek0v72YwR0AMr7/HMCzNE6vHbZoZGLw6TmUYGCoWDX78YIjV9Z3BuNEcraaj3g==', '8hjtPusN2g2Mu7E1', 'fhoH70slRh6874EAw7ZGhQ==', 0, '2026-05-26 01:33:00'),
(19, 5, 5, 3, '6xzJWaynyVCbni8whljErZq2uRgOtXO8u7zHOZEznHMa9xoUMjVTyuqTq9qwfUjIcWP7uCbzgPpG4a/vcUqEiyOnjGv2AXomHvu9L/6k715yZg5cPaSqFcFAkSHJ5PiDSMUU1Cdp6HbSnYpr9FgzmhYrAXnnXYHAs0phJRdEwR3wZfYIfvRLnWSwxjqowtwR86BMt32XbUug5GAZxhLqUHEvM5u4BVg4kUSXlA0fCZQulg8yP3/SEBrIIMq5ELfswbv5YyTPaml43NwWZdUv/V9Z0c5Jn/qto0V5wJmzsShjqrxvMQnWcbI1ewVIU6H3m9K7aiMDuofPz7TcCY/h3qF8bt2pAX5icQ==', 'KLCXg8nG//grCKim', '50DtyRVTX6q7dHWz4zcpJg==', 0, '2026-05-26 02:59:21'),
(20, 5, 5, 4, 'lmu1Sd0TiQTCZtj4GHzptuXPb7BVZqFe+jTWzOcrCr4mVTOSanh/FB6DUmK2Kv+nsIqoGNeD/IjJCVcF0R0XJiVAfNID/XePAwzyiIZ7DSMgBRk2mcSM2kXTfwRT+c3e58X5fEmdcPAgkB6l+QqRjrujem+9s5fA3Ot1ebuo0S9kWlIpNeO92jMnFiVONxzsfA6DqGvT3SHcMlBsgdTuFo0LMVLLEKJp7FHSj3nUF5x1O7SFuMN0dbaUZM8AgOI8wfXlBIcV0qrlFsAZ/vxvfl3Mt4rDm82zQupa42peJrWqtlpbtDptwjv69S403w/n2uqmUV6jjLjWvya2Vyhn3nUKzgjcXOa3bg==', 'V2XEIUwEy/kO7zta', 'wNJJ9mxeSOezuM5O9ekpmw==', 0, '2026-05-26 02:59:21'),
(23, 5, 4, 3, 'PvVPgVfhvYy0oiovMPr+eJaygkq4UjFnPb3hAGhwDe5MrKteEMEKlTcxzcvH0xbRowbOYgbdnzT2x0F7SGIO4TK/3gd2253m3MeAbMHx+Y9/FXWjfsZA3i//c69G6M9C0Mn1ebVfj/PUiOKKVpWJduInS0nfWQ53PIW2u3vOD9CDtpITxzkCuUSUPLuQst0AxJWsZPwLw/xtr3ZtR1VwOJZ6B+5JvMVzLKgdEYWMYf4Fnphx2KHnoAh3B7Jm7KOzDjs6+4SLtgkm/AkJXawimyGavVuEzdQJoivDA2P1IO1mLnbfbishQ7x8kvoWAlFpHVMvNd5GwN2aph0XxMMITEC2DPCFqmyFHQ==', 'TKbUATnd8O3EgjMA', 'IjjHfwaKDbf5Plc0OuPTYw==', 0, '2026-05-26 02:59:28'),
(24, 5, 4, 5, 'aKhvHi86qtfAThvgOkMtXwZ2zO34Drs1NdyxxLVhls5bLsftKsIdhUsH1CTv0ZfveH0JHIXDelWEFXXKZm2DjHo3TU4SJzxU/RB7FzNsEt1A2BhdpbUrJiQ9W8uYcZKuZOPvXM/dmQ/10ls1u1Ub+JrTDmPNLWIShHhquqOCVSVI67ePREfwJU33NagRwkFHQ2DLULkmIsvzcUGHFoFoc+/baqyfAOABtlj2v6mV6Ti15Ef4SaDGy7VyKZgl8TxX6Ldpkgdih8z5HGjqUbFoJM6DSnb0zTqDCctWvsnMZyDXw3V65qhmnBn93uNQAbrg/23MHWkc/O3NzlrW3DBddm6i++MktC87Mw==', 'od19yJP8n8oHlR1k', 'qeJLj2RtUrHhNZ8ZIrM7Rw==', 0, '2026-05-26 02:59:28');

-- --------------------------------------------------------

--
-- Table structure for table `sss_device_shares`
--

CREATE TABLE `sss_device_shares` (
  `user_id` int NOT NULL,
  `encrypted_share` text NOT NULL,
  `share_iv` varchar(128) NOT NULL,
  `share_auth_tag` varchar(128) NOT NULL,
  `eph_public_key` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sss_shares`
--

CREATE TABLE `sss_shares` (
  `share_id` int NOT NULL,
  `user_id` int NOT NULL,
  `share_index` tinyint NOT NULL,
  `encrypted_share` text COLLATE utf8mb4_general_ci NOT NULL,
  `storage_location` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sss_shares`
--

INSERT INTO `sss_shares` (`share_id`, `user_id`, `share_index`, `encrypted_share`, `storage_location`, `created_at`, `updated_at`) VALUES
(1, 3, 5, 'yRkSgzIpWUkdfWesLXhm2UiKRgxHPGTT7Udt1pwoJwT693iB5lA9YgRMqwlm35OLGJgmeoJVeGlKtS3afXg8bXt6yZXyZ6VDt0QLJbRA/QOt4xRh3KJ2NmWATpwh8CPARDBWus1rQWJmpYCqVR2kCIns/JJywlKhAOMvqBZPt7HuogjV5MU8A//Xz3eZZ3AAFVGeiCbZqYIXmzCBrLc8G10UBNeaS5ZeH9+svoO6dknuZjutHtIbFURoWxU4tjvlqWv2Vo7XFue7PewdumNY1wvLNAGV4jmu10ddFXJ/JMu1ptBCFU4Kbd53+iG8Uj84E2Fn9QCqgQsCYP2o0h6gMwyAmCdsOYqb3DkOLeNsSl1TgUTzh7M0vCstscg=', 'main_server', '2026-05-23 08:36:20', '2026-05-23 08:36:20'),
(2, 4, 5, '9CRmI5PWLkQTXSntZRyRLpNpW5O1AoYvy+MhiFRYzTWlomOR20fzln37bMkLqOFr6pVSwDr8IzjHWu5H6jR3Zb3fjmHuiRlRDLObpK/fzlN3hieo7LhZVrBYT6FApPcMxakAOrXOJCnG/Md00vIpTi7ADpzvAvX1QURukiRUrHVRyUuCfGBQ6HI7qs0RuaYWTxSWhkbJZyggS4BlQMsYy1MbIH0VdoWMRlaHQPcI62dmxJ+D7fPfecSSxIq7s9dmdHl6+vSZDZZ5A/0YHm9P3+NBuCGPdoBNVl9Cb3+svHf60Dcp890xHYWcgOWKqSb6N2ccvTApl9R5IelW16g5YnHjczyx61fyKGnjk+a3AD3f3go+MEcYhPiBNGk=', 'main_server', '2026-05-23 08:38:52', '2026-05-23 08:38:52'),
(3, 5, 5, 'C5uaZfXLD6Xl4yPNcr27wPmW05KR19weCP1k2H7AVp8RPIELjPCTVgW9Ae+eAgu4eXxCjBRQsl+mNAgz2ODdzEs6WVTJmDQ8t7Bg52pfmld2tNctB1vkKUD5em/mytORxjTWwcOvM6ncHZBqlURvz7FK6Wf0mkq4VugzrRcrUjxT7BlGVFb6RNtoLXIJlCLaUlHwshO51PPOIzgCEYAQYTstP5WmSJzUsJ9gol+fKxh7UElfinusyPAYSmbyEmapuAdlLUm3fE7H4OTgCQBQ4zHgAZr5Pwxnh8YP4OE9KIUj91zOrOe/oygf7bcSPEADQ6P+rlYalA7jGJrAmGFVvd6jNVn0tyK00j8yOZAH3Z+37U1W+fnTNPLFY74=', 'main_server', '2026-05-26 00:00:05', '2026-05-26 02:57:36');

-- --------------------------------------------------------

--
-- Table structure for table `sss_shares_secondary`
--

CREATE TABLE `sss_shares_secondary` (
  `share_id` int NOT NULL,
  `user_id` int NOT NULL,
  `share_index` tinyint NOT NULL DEFAULT '2',
  `encrypted_share` text COLLATE utf8mb4_general_ci NOT NULL,
  `storage_location` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'main_server_secondary',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sss_shares_secondary`
--

INSERT INTO `sss_shares_secondary` (`share_id`, `user_id`, `share_index`, `encrypted_share`, `storage_location`, `created_at`, `updated_at`) VALUES
(1, 3, 2, 'pDdP1Gc3fGYEXgNfCx0L4iw0ufJp0QRBfewwyXEpTqWGuAKZcMVeUyt2V47nMDPeH/Cs5rNw1dLBz7H5ehWBp6FYT4qFHv0N+Ipzeeu5SWhNwzYfIFKTwHp2joT64pQBxQ0Cie/3DKdibeF0H836tYhhNGePgCdMq+6UN5hmLWaMhPMBy38U0rH7d+XVI8HLmmCH0fIFUqzc2QmRKC09KcJtpttNOFIg+Sscq9rsUjYeUO/p4dtfnjsiMD+g2yS6YhqIPw+tOQvlLUDpJuA5hKGHnRmh7rt0l9d277+JUARcWwMoG2hAVUo2Z/aeYTD9O7liIbnOgTLBCDAbAK2ZfhcIYJ+BzicQARTvIWrjPpyx8TBaaXk4NvniWHE=', 'main_server_secondary', '2026-05-23 08:36:20', '2026-05-23 08:36:20'),
(2, 4, 2, 'mmhNxDZ+ZOX30zSi+irC22G4Q6bbP7aDnyUcGO2Mvq2/Mi2GEHSsb1D1L9dKoEiScRBIQv8LVAf7y8RVrkLuABD9xtxix/SWgXPU/mI20Bi+WWUzaHipKcNz2BVYCoBhEqWPkzAo23yNFpnX4kSzaJwATW1/iMT8pnf/pSCVHN9P+fWSTU+7rOoRO5+15f2eQjCcIpmWFnLkHJD3JPCVKZ2IlOKTIxbD9sSuUmS0SY4HlNRJi4uoBzoPU51eIo0u2esFb9yw0P1m8Hjm7nK0mtFWB+shbyX2YJlhPOubIJINYKicRFDBuxoU6RUVrp1orWkVxvztRGZn0tTI5O+zTcU5M+5O82XrqLG8DVddP45oHxKnZipDq59LCrI=', 'main_server_secondary', '2026-05-23 08:38:52', '2026-05-23 08:38:52'),
(3, 5, 2, 'oK6Cm0OxsOygveIGf+oW+oQ233v4Wqh7k1qtdz+tnEUtFp+pBKqyBMzRINxDVWK8AV43OptI3eAv3iRjMcFZshfW+YHmYoJVo2H8Rt0xwtf23V3SiSnNi5NMB7WRqv7zgxMakPVaBR3EIPMc+GU3Dee+Woix9ufCgZzN0sgLybDB8QWqyi+nVJR69/+dfm2R1QET/xm6SvtGNC/l807g3Ttsr4P1ManLqabbKxq1SW0SbV7dy5DBsXq+JyO0dNYJOJVuDMlc4ukzyxo6xgRCz6lTbEkodb+Hwm4KnyYGEBuBk29OKCroAXpj5AQFP5RiIcLcAN4vIcA6954FOBNyKe+5p9yEIoY6eif+DyQN103yR2hTG1+VqmZCH44=', 'main_server_secondary', '2026-05-26 00:00:05', '2026-05-26 00:00:05');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('staff','admin') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'staff',
  `staff_id` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `department` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_general_ci DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ecdh_public_key` text COLLATE utf8mb4_general_ci,
  `ik_dh_public` mediumtext COLLATE utf8mb4_general_ci,
  `ik_sign_public` mediumtext COLLATE utf8mb4_general_ci,
  `spk_id` bigint UNSIGNED DEFAULT NULL,
  `spk_public` mediumtext COLLATE utf8mb4_general_ci,
  `spk_signature` text COLLATE utf8mb4_general_ci,
  `encrypted_ik_dh` mediumtext COLLATE utf8mb4_general_ci,
  `ik_dh_iv` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ik_dh_auth_tag` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `encrypted_ik_sign` mediumtext COLLATE utf8mb4_general_ci,
  `ik_sign_iv` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ik_sign_auth_tag` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `encrypted_private_key` text COLLATE utf8mb4_general_ci,
  `key_iv` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `key_auth_tag` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `key_hash` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password_change_required` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = must change password on next login',
  `is_head_admin` tinyint(1) NOT NULL DEFAULT '0',
  `session_token` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Current active session token — changes on every new login'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `password`, `role`, `staff_id`, `department`, `status`, `created_at`, `ecdh_public_key`, `ik_dh_public`, `ik_sign_public`, `spk_id`, `spk_public`, `spk_signature`, `encrypted_ik_dh`, `ik_dh_iv`, `ik_dh_auth_tag`, `encrypted_ik_sign`, `ik_sign_iv`, `ik_sign_auth_tag`, `encrypted_private_key`, `key_iv`, `key_auth_tag`, `key_hash`, `password_change_required`, `is_head_admin`, `session_token`) VALUES
(1, 'Zul', 'ai230047@student.uthm.edu.my', '$2y$10$X/w9vN97aku1nghHCCwLU.T3yDbiHCbh4zUekfJqXShaaHXRoNeba', 'admin', 'ADM001', 'Administrative', 'active', '2026-05-23 07:50:07', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, '2d98947aff849b3e126a7d003dda9efa18509cf66563d09561bf309c198eae52'),
(2, 'Aiman', 'm-3513759@moe-dl.edu.my', '$2y$10$UvCysMOviXe7VGWDS35zROABOGSrTdNpnHjYA4ocOyFLwahTU4tUi', 'admin', 'ADM002', 'Administrative', 'active', '2026-05-23 08:09:42', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'dd16e64547be5213d2f6b946062bfce99364d68b3ce585e4717883d64200ac15'),
(3, 'Hilman', 'zulhilmantarmizi@gmail.com', '$2y$10$UQRKU2fLw2zZ6XMWNH1i7u9uF/GODuI3ZcjjsF/85vloAqDuP6Dsy', 'staff', 'ST001', 'Payroll', 'active', '2026-05-23 08:36:17', '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"llToMkGWNDf0gS9cQ4Ggset58AZ55CV5m4WX0kQPcZI\",\"y\":\"J3r4FWiyIPr7ijg0ihcM_tMdK1vA2WnkQRLSR0X4o6k\"}', '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"qV6_zAu944fNvul_FMcDYNllvXJ13wgB7pofOhHgcjk\",\"y\":\"oWSno2K6izNuq9GZIrI0VgfdtDyXfMWAAFFAARQaSu8\"}', '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[\"verify\"],\"kty\":\"EC\",\"x\":\"CNIJxnq2BfPs0Q1TkqPVIQvX-8y1SaaL8NN3wRqMSx8\",\"y\":\"Eg3PI3rFhJHHymudTrSc9yMmqShlNx4YYakTYzgR8Zw\"}', 1779759030808, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"aUvYfwgX8PlR0j_P3Zp5omP99dZMEtzRn2rgDDDSfVQ\",\"y\":\"MwDEKVg0zB958WJoIi1m0UdZ7sL_ysYIkX1ZNPnvNB4\"}', '8nfqu7zTvOYdIPmGPU3KUZ3+LFJWW70jj6TzB0MGlpwE/QGhHdNjRwR3OV6Z2dNDbygYHSJvd1hWsSPW5HjMUA==', '7Rn0OAbpSa7VMvOmwgyByQH3kjHJODOUo7nbC6y9IFyd0h48RdlmCkQ/A8l4384Ox80CY/yeRjmunFR3ug85JLUqgXenyGSdQHcumTyccQZQq2aVOEy/yLaDxM803f8bIN+9mZ2ULopJ2wNLkFMFpKoOShzX7KJdzDKMuEFzTX5iB+mIA3nZHzfZ/r0btBavPaUzQY1ERsH0ie49+/3Uq+n/3WCXzCxWrCz8uufflI5jyaOE/K4jGIQl6E6gae+sefOJYsw8clhIEbDm1rUQkTg4Lm4=', 'g8c/lnIQaB3BpCu1WtCBCA==.PHSYKjXQDXnvaB9K', '7wg6vhGZiE6a1AtGGO62pA==', 'm3SnP4Te6Y1KPfqcand4Vt94zXtE0dNoCTCG/uJAj+lgBWpC+COapMwM//6W2gTNNQVygz+f18f9jz8/6Yf/hv0q4sXtlO+QtggghNMRNHrnBMtlv3uLlQ/YjSFfx+Py7Jhyte62HH1bVZ2NQBtr/H0ajksu5xNiMbPp12SG/1dFl4sWTUIIOuxdqXPsOdNtzKyqwJuVrhPwL9Gq4ZXrGzuPCefmGzktFDjcpq9jBwlKILKCcARmnqkJ5dLRpZhxaG3i/xdC9JVQsR0BA9c=', '4gkfUQ3f66hmhk0wDagbyw==.lhyqmcw0vh55SDuT', 'cL0itRGwQW/rERQ+rIKqxQ==', 'ZmrWtJJSMmK/FVSUnJJKoIRUP0i2uFncf9aHXqUi97j91pZPG6iJQF4XvSMi54N1HPxYTvrvXpfOjTdUDhIuAVbzn8lmUi4CF3Eg50zaUqha7+z4QwO6k03E0vMqI4kjmp03Y9AlAa/iLZVgmsk5lKfsfPMkhM1MmIkI+MhvliYoNphXVlqoKwwyWoKdK6+ILwh6Y1v7rMtRo3jGaRGpMDqURzGh2tCF5O2NuQRukMExbwXkgt+kn2rAAonoTU1lEkOApCOaSy29YhP7JPEX/YVzfxwpP2shgq0ezZNx2mE=', 'kkpGMkoAY0trJz5B/sSfbA==.KEYQrZ0znULjPSOj', 'KJP3lGR8fTvKcrfBVXj+FA==', '29c6d2046ea4abfacb01eff18dc2b3acb6127bf4cd732f0911bf4e34f7da3e65', 0, 0, NULL),
(4, 'Iman', 'kevindezul@gmail.com', '$2y$10$a0L0eL71la2Q4J51cdXbsu7mijAPJISI5l8EemVauJltzYhAQTdOK', 'staff', 'ST002', 'IT', 'active', '2026-05-23 08:38:49', '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"8duCC7yPBbXuBCkXjQ6uwCgCCoGQZ7F0tbj4_OdoZSw\",\"y\":\"fAzhsdsxvAz1QHiyHUuL9Aq4G98MEQ1pH7V5NuE_8nQ\"}', '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"_WZXQXNNG-o8R4tCPLD4tAXzU42nvmHO982ttOqycMI\",\"y\":\"9bHoWj4MXAZcNl8EeAwqIb9z5-0mC268-JUeLHzMlxU\"}', '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[\"verify\"],\"kty\":\"EC\",\"x\":\"OspHObW-uTHJFt50cLqWHnOt6-p_jgNywIQAOnbLTGw\",\"y\":\"x0-_ZsshX7N4vxjynyUJX7lFnka-JX6ugeEeKaAvYvg\"}', 1779774544693, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"E8snuy-BwNPwaOKxTSsU3xG0AMfiAwPNrp9XqQKC964\",\"y\":\"VItejxFdf4KsAaDwQFHDg5qoSpGXcc5wpOL-rnjPN0k\"}', 'FhXuTAbqS7Il+Pxwg9nC4vmNDhNHKcfTICKOpd88k+lWKDLQXuPzppn6bsl2H9Ww/eVRHchrczJDi+l01gTIaQ==', 'bS2i2x6HZMg1R+/zxmBXYk66vYz1SNveBNdY45hiX7sZz2sHeR3JPkRKIqo8fM11Uj/MF//00nu4M4QdX7PFxJa3rTwxlhO3YsBRLH7D9enIHWHrJODz0PRShs8DmPZ3PDd40kF+0JbpxiXB7GPoxT+wLAXuoq6Ryae0IHr2YM0w+P7p/XInkv5M6LjSUoGQfw2c6qkp631e7JKwiEh5DtnKFtlX1mbW40SC1mUrz9whNxT8lNxieLVVZ/PeTzCE8iFX/XV0VfeiKDYVNwQCJp1Y2kA=', 'mA66OmEVyQf3m7R91UEe3g==.0TBHqnzVuyXBrRfp', '7LBU5fqk2IGRxk7CwJEhgQ==', '2zAJF9brNHha7vUE/yUWomeRDEDafQ9/9EDqU6wxj8X2yKqqnr98Guif/sXlXJgN9WW8Vg7/EBh5e1+CTwVJL7fCOyTI0ZTJG6wOgMzkBl2F1IVVmMmnsX/dhE/vas0Juqwrg0J7rQVpDqeU/MYgZgNlNGx3L+PnqlEcdGIycLHvXfO/OcatiJkJPbyH7/83/C2+uqCgCDNz/dooyU47st02BZ90U8uip5vNp5NuaMhtAd79/KeR+YH0BB43nUrkFYNujDQqiLdYbnKoJLk=', '0DUkJz4pa/sGkNfGi24dlA==.dcJFkMnSCVsSRiDu', 'F9Snb/Rc3qxo1DXZqAW0aw==', 'krNR2Qwcsm3X2dw+JpdFmIcy4UzABoZ/ZVBNdevPZuj8x2jo5ncLOmtmw6z8nTTYFN1OMSbWXaf/bQSdsW5Tg4cigzYL6KOZ2oLKsSD5oYN9hjHsqa2s/XJnd+Q/t66bZQNecl3v3AAlEV3t53BS4o8sInuo7x/cDiXbUXsy/lZAtetX1GwNwcORTz2XTyrEQ2JUqIj+K/mHxgoHjFv1GneLYocuwtnBThV14YDLQhWWAw/w00m5Y0WWm4ggmjB8YcZVUOtq8oWetiNQ/s+OXOHbTA4OBlC58Evd+o29bPo=', 'N3QRaUKAVZoP6Me0QLorLQ==.Et+s9uyUsczJFFXs', '6fyMYmNFQL9vaW5DKrWZHg==', '4373f1cf475ec12118ec720523b5574d3fa8c5d3219a52c82cbdde215bd63d3b', 0, 0, 'd3aa22a5f0898bed99f466c5ec472f9a7ed50d8deface3a606c3bb1b255a42e0'),
(5, 'Tarmizi', 'tarmizihilman13@gmail.com', '$2y$10$ioh5f/jCoYeIlLTXutWWLuLxIaBvCFolhBBkKfuvsZ0UFOfXnXHvG', 'staff', 'ST003', 'IT', 'active', '2026-05-26 00:00:01', '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"TTYANZqu9V_OXhhOxg_-jQr1dYFTbGakBJ1MShaBy1M\",\"y\":\"7wflzfSAeL7FHen9XKveG_i6_u8LxUS2H3s5F-MZWxI\"}', '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"IQGwWsQ1APtr4i2VneMuXIutud0CtzVt7KUe_ad0fcw\",\"y\":\"VU4DQ88mjASX4RU2bukTaTYh0MNtJfx_NPi-Duus5wE\"}', '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[\"verify\"],\"kty\":\"EC\",\"x\":\"R6t5N9hcsR5VHn52uKkmyhx32LlkArz_4zZZj2yF4sQ\",\"y\":\"uUEdatOmFu5Tt_Zdfx9xGcvGCOgOjMNepJmmwmI-FXg\"}', 1779771669152, '{\"crv\":\"P-256\",\"ext\":true,\"key_ops\":[],\"kty\":\"EC\",\"x\":\"mNS20RV4AuPvpMf_JqvADgQj21E7MfcTByTLOAT-9-s\",\"y\":\"eLvv0T_8kFmrzFp_jew2N6_z00HHIZjHqaWowxSgu4k\"}', 'QuyDawLnjAw0VEeOuk9hmmtCfzLvR2JEgLYTi2JRiyHah0AT+3JSnbQk45y420Bh9kGZme9G+hZ2FKkLi+EoFA==', 'wsrzl+ikfrsCn/pCpAXKKAj1N0yvzu1JCyC1ThLH+O2XwZCIW9hhXdI9leCW9IrRFO2IZFeSjdwCeeurCMEp4Vbc5IZzLSzzx1oeMAllT9vaaUJeXigCkBbxikoPoZD+1LEI09wd3bhKMLjxZuRmR4jsFTv+Jo1PadpnQmMxLoKKkVEMEDJpewKhvAEkY3QqHrrrSimFneFXJcJhGb7dbBqvTRPmfCnvIOAetBffInJMKJ+zRe/mRUQ94REvls6ly6+paWFZm8nX53GRQSlTGieTsRY=', 'ba6AFi6GusmVY2lGninp5w==.l2OkpQ1gABQr+n0m', 'OoXOdS/ihDB7+PDCRQ/X7w==', '7QEwa3NYYOAolsfWeVIi2jy1xOY7qqk//ZgU9YKaOMwhN6n3+SYdSLgCmI05EDxAHR0+j3Aop2KkRu7feTFMY2+DoSFS87m/HuZUC7BMKZl4R9zCz6UzvEYfya5EVot9jgBudJo2BlKWV7sABVekxkfW66k35Sh2geGZw0ohJ/gsLxfokjwTXnY6Kd1saJOXaNZiMtiEAqVQVHObVA5lv+GyIFN7u6bQkK9xvQ67kdTJrLrBe1lxLj4EIo1f9/C/33CztP3YiY2UgKqr8+w=', 'uWSIvER52eoLvE14DbKprw==.2rJ4y+/EKK/wTb+a', 'g9koEOiNOESCZk18lA+UkA==', 'JIGJENmKYAUE/T9u3UQJ+1HRiaY2To2qIQld23pL1m2GRUy8kjIsZ5RRY+cY73uqotrtQAxZ+IMHRwfLj4IUFZwGt9rL9ZrwtR7Maz3Yi7xK/jCi/EDCv+n/2w1KCkuW9AsU5L9BSGy5XGh8o6EFnkwS5T8DzKGLNxvAS416FQjwp8WiRkj53KCGuPhy/Ahpy6YLVYCSE82tRKkyC4rs2lTdoLyqt7G6pJ5umXYxK8fwcpXRlLBT90l2WmPf3c8pnO7z0ghHD6SOLSzHSUbkN+4haW8RxN7hLbVA6A0JVn8=', 'PABD/4cHRmIpFO3HBmGflw==.fqauuQP7ixgqQmxz', 'QzMXETnE+UrGSkBqL2DltA==', '452e02fee75850bd8533f1dbe86fd0bd2f87652e5786fda4585c68121b375249', 0, 0, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_reset_requests`
--
ALTER TABLE `admin_reset_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `contacts`
--
ALTER TABLE `contacts`
  ADD PRIMARY KEY (`contact_id`),
  ADD UNIQUE KEY `unique_contact` (`user_id`,`contact_user_id`),
  ADD KEY `contact_user_id` (`contact_user_id`);

--
-- Indexes for table `conversation_reads`
--
ALTER TABLE `conversation_reads`
  ADD PRIMARY KEY (`user_id`,`chat_type`,`chat_id`);

--
-- Indexes for table `groups`
--
ALTER TABLE `groups`
  ADD PRIMARY KEY (`group_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `group_members`
--
ALTER TABLE `group_members`
  ADD PRIMARY KEY (`membership_id`),
  ADD UNIQUE KEY `unique_membership` (`group_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `group_id` (`group_id`);

--
-- Indexes for table `recovery_otps`
--
ALTER TABLE `recovery_otps`
  ADD PRIMARY KEY (`otp_id`),
  ADD KEY `email` (`email`);

--
-- Indexes for table `recovery_requests`
--
ALTER TABLE `recovery_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `signal_prekeys`
--
ALTER TABLE `signal_prekeys`
  ADD PRIMARY KEY (`prekey_id`,`user_id`),
  ADD KEY `idx_user_available` (`user_id`,`used`);

--
-- Indexes for table `signal_sender_keys`
--
ALTER TABLE `signal_sender_keys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_dist` (`group_id`,`sender_id`,`member_id`);

--
-- Indexes for table `sss_device_shares`
--
ALTER TABLE `sss_device_shares`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `sss_shares`
--
ALTER TABLE `sss_shares`
  ADD PRIMARY KEY (`share_id`),
  ADD UNIQUE KEY `unique_user_share` (`user_id`,`share_index`);

--
-- Indexes for table `sss_shares_secondary`
--
ALTER TABLE `sss_shares_secondary`
  ADD PRIMARY KEY (`share_id`),
  ADD UNIQUE KEY `unique_user_secondary_share` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_reset_requests`
--
ALTER TABLE `admin_reset_requests`
  MODIFY `request_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=279;

--
-- AUTO_INCREMENT for table `contacts`
--
ALTER TABLE `contacts`
  MODIFY `contact_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `groups`
--
ALTER TABLE `groups`
  MODIFY `group_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `group_members`
--
ALTER TABLE `group_members`
  MODIFY `membership_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `recovery_otps`
--
ALTER TABLE `recovery_otps`
  MODIFY `otp_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `recovery_requests`
--
ALTER TABLE `recovery_requests`
  MODIFY `request_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `signal_sender_keys`
--
ALTER TABLE `signal_sender_keys`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `sss_shares`
--
ALTER TABLE `sss_shares`
  MODIFY `share_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `sss_shares_secondary`
--
ALTER TABLE `sss_shares_secondary`
  MODIFY `share_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_reset_requests`
--
ALTER TABLE `admin_reset_requests`
  ADD CONSTRAINT `admin_reset_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `admin_reset_requests_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `contacts`
--
ALTER TABLE `contacts`
  ADD CONSTRAINT `contacts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `contacts_ibfk_2` FOREIGN KEY (`contact_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `groups`
--
ALTER TABLE `groups`
  ADD CONSTRAINT `groups_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `group_members`
--
ALTER TABLE `group_members`
  ADD CONSTRAINT `group_members_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups` (`group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`group_id`) REFERENCES `groups` (`group_id`) ON DELETE CASCADE;

--
-- Constraints for table `recovery_requests`
--
ALTER TABLE `recovery_requests`
  ADD CONSTRAINT `recovery_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `recovery_requests_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `sss_shares`
--
ALTER TABLE `sss_shares`
  ADD CONSTRAINT `sss_shares_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `sss_shares_secondary`
--
ALTER TABLE `sss_shares_secondary`
  ADD CONSTRAINT `sss_shares_secondary_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
