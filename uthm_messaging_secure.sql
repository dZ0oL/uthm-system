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
-- Database: `uthm_messaging_secure`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_vault_shares`
--

CREATE TABLE `admin_vault_shares` (
  `share_id` int NOT NULL,
  `user_id` int NOT NULL,
  `share_index` tinyint NOT NULL DEFAULT '3',
  `encrypted_share` text COLLATE utf8mb4_general_ci NOT NULL,
  `key_hash` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_vault_shares`
--

INSERT INTO `admin_vault_shares` (`share_id`, `user_id`, `share_index`, `encrypted_share`, `key_hash`, `created_at`, `updated_at`) VALUES
(1, 3, 3, 'ld/nBKsXvsLSSar2+poi9/sd+JOh+J26t/LVNRF10uR4CCupvIvEAB4hNNp6thcumD/4q07dILoIZ5oYZgMY8Hr+TQvJK+xmIz8kgW3oiroHc0TMZtNEr2E8Po7ZkGLwprmsMD6t93eQ9FWChyshcNK2OSq4GE1W/Vcn69lh8HDmQ3hD+fVJoEuRoIAepevYdde10wd18LJjrhq9kKmvPcZc78I1RflCpbC4lnOqH6AHN3dw3H6BYPGzmaCNEYQaFt2GM2R2rhxUdQSOiSwsKd8T7Lgk2LNPxTM6zGIlMgJ7AqMCNvvss3AEMWkhywyZgjwMCGo+wW7HlGeHi9Pax7Wc3LvcmTihDraIbJUo4c8JO6Q3wu7hFfu282w=', '29c6d2046ea4abfacb01eff18dc2b3acb6127bf4cd732f0911bf4e34f7da3e65', '2026-05-23 08:36:20', '2026-05-23 08:36:20'),
(2, 4, 3, 'RmTAznEhlJU2KCHyB5f68C5InY8ibt8mHEqYy61nFzb2n+jHcwC+BOXHWo6rn2SYx4PmWQ90kZ6AplVrRI8ElhMwKc1bX7cB199C6Hcc6GozPdFp5ixU2CKPMBPMtVx8iCAk+fuhlUXzPGpk4xKNJfFcW2Alu6Kb3KGJbMzN+RcSetAtuQZ9hJrYIxw3ASrkp3echJ0zGEFI+8ywUYShctFjKgLEr4KeuBJaW+kL2mNtbKt4tJggh6B2Ay4G3i71jmBZPZyuPPLS3YSJMNSGHNEPiyT/gQ2BuE482rq4wXJEEX8Du6WlgBFTh2c+12idWo/RHnw8dv/cmbMr4FHWxvOj4e9rdIj5cdfh3hy6YjuF8qFMR5ObtsHYDkM=', '4373f1cf475ec12118ec720523b5574d3fa8c5d3219a52c82cbdde215bd63d3b', '2026-05-23 08:38:52', '2026-05-23 08:38:52'),
(3, 5, 3, 'lGT0bx34Yhrh9bxjHCgzlcbkvoLrEg+5Rg85ofJy/vYrDlCQ+0TNVc+v9o4ltYEEvPcLqijRf3oKzsxrSyjDw9F0YRpCbwiL5FBws4PJZauXcm8EYpwLoEY2pzDzQuX3fhifr+U6h4L3iGdfq4wKl6byZbfx5Ni1MI9csJH48mPrlm8aYvdhq6VjxIUNgLmd6M71790Jtx39OFpETTXJRbmKuacOzFQV9aUQxX3NVcAWnjIA8WE0hmTx8U8P/QWccuZzsUQ8ujczVzpNdiK5PJcmMfv2t08V/Q/ZFrL2Sp+2sPFpBxKenT/cuh9yFY0mHytXYEV79lGCuYUBoWsl37uPyPHpqSuo+KIr+n7Kc8+o8XhGdAmTqcv6e4s=', '452e02fee75850bd8533f1dbe86fd0bd2f87652e5786fda4585c68121b375249', '2026-05-26 00:00:06', '2026-05-26 02:57:36');

-- --------------------------------------------------------

--
-- Table structure for table `backup_shares`
--

CREATE TABLE `backup_shares` (
  `share_id` int NOT NULL,
  `user_id` int NOT NULL,
  `share_index` tinyint NOT NULL DEFAULT '4',
  `encrypted_share` text COLLATE utf8mb4_general_ci NOT NULL,
  `key_hash` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `backup_shares`
--

INSERT INTO `backup_shares` (`share_id`, `user_id`, `share_index`, `encrypted_share`, `key_hash`, `created_at`, `updated_at`) VALUES
(1, 3, 4, '9QJvgU5vMpl/McD12z9naUGixfjWSfP6k4L73x3h9zVR+pnbcKB+G6u375bHIMJcNtF7z+UgE0hvHsGu9qH44NpTyHyXCg1UZyUh+f2yrvO6PF/2f2PbTnD7HSVPHGoS6cOGQJFxgFx/EL7E00sPlubWAMP17Jm7hSPkG8AFUDsgO/dHSiUm1ACN21g27msQQvC4KG3xgq0p20VCDPoNjowhn8vHGVHpM5EoU1ILVWd95kzNQ/jYnge+4Q+f2YoQuA14CndLRrs09CzAfDxawK4i9HuQBdP9wgeqSP3p6devtqYA3aDuXqP8SlGIfLTk2u7R7uk8PazH35vTtOAxv9JLiq/B0rM+qQBz97kgqBdD03V8v0/V5TyjhA8=', '29c6d2046ea4abfacb01eff18dc2b3acb6127bf4cd732f0911bf4e34f7da3e65', '2026-05-23 08:36:20', '2026-05-23 08:36:20'),
(2, 4, 4, 'YpTwoSPhyjyO/gUv0wxQXUhmxiomyInV1oiyBcQ9rmByhes2Zo6YjVu2xPjYIchp6gaf9ChF4kxI6SZZ4ULCaGCoLnPKfjlvtW7x8u4mxpm1I2pV9dCvczzhIuhavobLJdfgA5Fpl6ZOdBjMf30m/JI8t7CPB8xoR3qXKsSoQSAfITXQwv8XaKFpBG0dmlReAu8sOudT/8sSb5Bg9dBC2VJK7b/31h36Vm1AJ4aEMKuU5lliFL+BUDhfR4PxmaeXyi+LSkgVSCpeXYAvQt+KHiFk/Cl30PhgitD3BS6zpODX5bUQsPAfSMJNsiLgCMPk3bmRl0+bghBWHRgpql8XNaP+6brndMmEv/X9ViIb0E5/r5V8tO4R4yRDZ38=', '4373f1cf475ec12118ec720523b5574d3fa8c5d3219a52c82cbdde215bd63d3b', '2026-05-23 08:38:52', '2026-05-23 08:38:52'),
(3, 5, 4, 'fFN4IawHTw6t2kO9CcoQUxtfBAJkOHCvwDPo333mExo8LNdHNYstEs+i1SXvmvy9bQ5NjvE+hWmfv6A9Pal5lsbOavG3OdPVbvPTkwFbzhmsQ+AKlX49NP79fgC5WQE6wSm7St5HVLUn+PrcVphNT6EP9py+O/+zcd7k3ig4rid3ml1S3QmiS7tHevdCPk1HXE2bQupXSPVOkZSRcA/B4xbJiB3xRMgC9URlqffqhvsnddAyD235A82ZANoDhHqbDDfprx5Yeq0gc9TnUbpeYDTEfbEAix5dGiOj8FG3IvUreJN6355PsNQwwyvO6gjuG4rDpFMtRha2nRW61JPq4n+MxtsPe4fIc7SlP4bSrPcBSTaOFde+X3/N3KU=', '452e02fee75850bd8533f1dbe86fd0bd2f87652e5786fda4585c68121b375249', '2026-05-26 00:00:06', '2026-05-26 02:57:36');

-- --------------------------------------------------------

--
-- Table structure for table `messages_backup`
--

CREATE TABLE `messages_backup` (
  `backup_id` int NOT NULL,
  `original_message_id` int NOT NULL,
  `sender_id` int NOT NULL,
  `receiver_id` int DEFAULT NULL,
  `group_id` int DEFAULT NULL,
  `message_content` text COLLATE utf8mb4_general_ci NOT NULL,
  `iv` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `auth_tag` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `encrypted_aes_key` text COLLATE utf8mb4_general_ci,
  `message_type` enum('personal','group','personal_file','group_file') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'personal',
  `signal_header` mediumtext COLLATE utf8mb4_general_ci,
  `signal_prekey_data` mediumtext COLLATE utf8mb4_general_ci,
  `original_timestamp` timestamp NOT NULL,
  `backed_up_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `file_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `file_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages_backup`
--

INSERT INTO `messages_backup` (`backup_id`, `original_message_id`, `sender_id`, `receiver_id`, `group_id`, `message_content`, `iv`, `auth_tag`, `encrypted_aes_key`, `message_type`, `signal_header`, `signal_prekey_data`, `original_timestamp`, `backed_up_at`, `file_name`, `file_size`, `file_type`, `file_path`) VALUES
(1, 1, 4, 3, NULL, '8emJY8tgSWPJI9Z7mQj2L/hK0oafoA==', 'l0FN/HPYoBQl7QLQ', 'KZsKJ1UZCeF8dRPHz5a/Ug==', NULL, 'personal', '{\"n\":0,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"mgjvxfoEp822rr7xbyIfEAPYWPRVnotmot1XdykXYEs\\\",\\\"y\\\":\\\"iMXKVnQxAoaGWa7pfYa9rggxL3J15St_rC8QESMGVDU\\\"}\"}', '{\"ik_dh_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"_WZXQXNNG-o8R4tCPLD4tAXzU42nvmHO982ttOqycMI\\\",\\\"y\\\":\\\"9bHoWj4MXAZcNl8EeAwqIb9z5-0mC268-JUeLHzMlxU\\\"}\",\"ek_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"BZVJCUURP5XNIhL_K4KZqfwmcM3QuLtQFZgI9MRMy64\\\",\\\"y\\\":\\\"TLa59YlkiL4a_9JtmIJW-q_-HOW9sLUP9G5RqVmLxSo\\\"}\",\"spk_id\":1779525451026,\"opk_id\":177952545102600}', '2026-05-23 09:21:00', '2026-05-23 09:21:00', NULL, NULL, NULL, NULL),
(2, 2, 3, 4, NULL, '2kXwsOzIi2qRLPJMUglIU5uU4DAH27V4rw==', 'wofpohYd9JUvWddl', 'dqZ8LY01+++i8Dsfdb65Yg==', NULL, 'personal', '{\"n\":0,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"uuqt-fgTfHvya7ZLieaQQsFWYTAaDu0yqaYu-fgpZaY\\\",\\\"y\\\":\\\"7lCe1hA1zeQr3PYqcPyzPnsXY3Gm-3R9r5egziTwiGw\\\"}\"}', '{\"ik_dh_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"qV6_zAu944fNvul_FMcDYNllvXJ13wgB7pofOhHgcjk\\\",\\\"y\\\":\\\"oWSno2K6izNuq9GZIrI0VgfdtDyXfMWAAFFAARQaSu8\\\"}\",\"ek_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"jRRrJ5GMmMO1SNISMGshowzyO4ptfB0ebB9xjk0lsRY\\\",\\\"y\\\":\\\"CFHaw6rEz-LBqswu8iKZENY-ZWufgbGOQ1s26ZWA0WI\\\"}\",\"spk_id\":1779525562491,\"opk_id\":177952556249100}', '2026-05-23 09:49:47', '2026-05-23 09:49:47', NULL, NULL, NULL, NULL),
(3, 3, 3, 4, NULL, 'hMo=', 'x9BhA2l7H8jzcFi9', 'zPwctU5RxxQMKxqYxeep9Q==', NULL, 'personal', '{\"n\":0,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"63y4_I2iJeMB3orzNY8VHMctXLYh3OOH9fG2DEvT77o\\\",\\\"y\\\":\\\"ZUs_0oje7STGbxq0l-rS-JcVbPG9Rn74CGU6qMkbPBQ\\\"}\"}', '{\"ik_dh_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"qV6_zAu944fNvul_FMcDYNllvXJ13wgB7pofOhHgcjk\\\",\\\"y\\\":\\\"oWSno2K6izNuq9GZIrI0VgfdtDyXfMWAAFFAARQaSu8\\\"}\",\"ek_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"WIOeKrDTIa2lduEdPiBSp930t_sZYfBVthqfKp0d4OE\\\",\\\"y\\\":\\\"dTPwsZ7zUSDwSh3tCXvhq73yYFwl2D1aGGtDbZOM_h0\\\"}\",\"spk_id\":1779593536169,\"opk_id\":177959353616900}', '2026-05-24 09:06:02', '2026-05-24 09:06:02', NULL, NULL, NULL, NULL),
(4, 4, 4, 3, NULL, '6WEyVEE=', 'QseU14s9ICovx08o', 'xPnYAh30JILrBcV90zOW7w==', NULL, 'personal', '{\"n\":0,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"GXEfYNgiREpHoScjDjIbvYGMo_ADWPXTX7SQ1wJtJPU\\\",\\\"y\\\":\\\"-UsvyRyg8mSLHEQ4PoJPEzfAYuak_Vjn5326uW-ls6c\\\"}\"}', '{\"ik_dh_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"_WZXQXNNG-o8R4tCPLD4tAXzU42nvmHO982ttOqycMI\\\",\\\"y\\\":\\\"9bHoWj4MXAZcNl8EeAwqIb9z5-0mC268-JUeLHzMlxU\\\"}\",\"ek_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"SoipmXaLQPulrARuvrTmJu9fnoeJbqOx0pGupYNH4Lw\\\",\\\"y\\\":\\\"S7OwE4FXesa3Ld7cXct4Fhk_1ovLhYxs4fTWzEbDzsk\\\"}\",\"spk_id\":1779525451026,\"opk_id\":177952545102601}', '2026-05-24 09:06:12', '2026-05-24 09:06:12', NULL, NULL, NULL, NULL),
(5, 5, 4, 3, NULL, 'P7eR9w==', 'Yr55Z+X0Ij8aH6x1', 'JZs1m+kt77Bn2DXg8iiopA==', NULL, 'personal', '{\"n\":1,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"GXEfYNgiREpHoScjDjIbvYGMo_ADWPXTX7SQ1wJtJPU\\\",\\\"y\\\":\\\"-UsvyRyg8mSLHEQ4PoJPEzfAYuak_Vjn5326uW-ls6c\\\"}\"}', NULL, '2026-05-24 09:06:18', '2026-05-24 09:06:18', NULL, NULL, NULL, NULL),
(6, 6, 3, 4, NULL, 'Dz3YuqFJM9cXNo5VilYXL3vkPCOPV3xDZorT0usmV6MjavmMTJF13ckNo/5/B7/d7C728feAo1/aL20/2iFq40W3h7Gio5Tkqblf0uf5YxeOWo1p7GVicRle0aYVazSusgtjqCJDEz552uzA/A==', 'hQJ+XpoceC9lI+1A', 'qOhMks+5NNHM9WluiHuzaQ==', NULL, 'personal_file', '{\"n\":1,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"63y4_I2iJeMB3orzNY8VHMctXLYh3OOH9fG2DEvT77o\\\",\\\"y\\\":\\\"ZUs_0oje7STGbxq0l-rS-JcVbPG9Rn74CGU6qMkbPBQ\\\"}\"}', NULL, '2026-05-24 09:06:27', '2026-05-24 09:06:27', '3rd-Receipt-Muhammad Zulhilman Bin Tarmizi (1).pdf', 30964, 'application/pdf', 'uploads/encrypted/5e275cb946a2abc672b56c14d9dc6273'),
(7, 7, 3, 4, NULL, 'dt6NY34bpg==', 'iDaOi91Tod1Vik0u', 'gp4t14QPIHo3LHnpu6h0bg==', NULL, 'personal', '{\"n\":0,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"OR3oh1QsRqYZLdy5o_U24I47OG9mqpTfMYfnRQmfpSo\\\",\\\"y\\\":\\\"0SK78mXKX90Z8VzhewFlvQi8EK34-NRqCSSgmU43rYg\\\"}\"}', '{\"ik_dh_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"qV6_zAu944fNvul_FMcDYNllvXJ13wgB7pofOhHgcjk\\\",\\\"y\\\":\\\"oWSno2K6izNuq9GZIrI0VgfdtDyXfMWAAFFAARQaSu8\\\"}\",\"ek_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"G7t_d0PzLMvuLqqiLb4_WD7aynwJvre5i2X6m1t-qVI\\\",\\\"y\\\":\\\"ipAc0zBHsr782i0GQpynTjS27gCj9Dw9s6uuprspwME\\\"}\",\"spk_id\":1779692827493,\"opk_id\":177969282749300}', '2026-05-25 07:23:33', '2026-05-25 07:23:33', NULL, NULL, NULL, NULL),
(8, 8, 4, 3, NULL, 'WD6NPm+dBsDq', '/3dlTaQ+Edx3T/Nu', 'MHcmUcGBlME7t2oqtmFz5Q==', NULL, 'personal', '{\"n\":0,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"d4PXzxwUFhsNBQd0OVFJjLtDNee5HI6XCUQk_-lyhlo\\\",\\\"y\\\":\\\"ozLUFEoR5IggqVtF3aPth9IznzLJWGSnGD266oo_Zog\\\"}\"}', '{\"ik_dh_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"_WZXQXNNG-o8R4tCPLD4tAXzU42nvmHO982ttOqycMI\\\",\\\"y\\\":\\\"9bHoWj4MXAZcNl8EeAwqIb9z5-0mC268-JUeLHzMlxU\\\"}\",\"ek_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"Gzn9O5Adm78OUlnd4VLcv5Kp_FwJYgY1vKKaUxpL0xE\\\",\\\"y\\\":\\\"JMQYZyKJfHhk8JlkHvCTNBvHYMMW702ui_W0wq1siuk\\\"}\",\"spk_id\":1779525451026,\"opk_id\":177952545102603}', '2026-05-25 08:13:23', '2026-05-25 08:13:23', NULL, NULL, NULL, NULL),
(9, 9, 4, 3, NULL, '9u445Z/GwOBa5hFWMye5EAfPSTv9LuB7', '+ezhj1Y2gvIZbLyk', 'ADmCC+6FXP+/KxSyiIcO9g==', NULL, 'personal', '{\"n\":1,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"d4PXzxwUFhsNBQd0OVFJjLtDNee5HI6XCUQk_-lyhlo\\\",\\\"y\\\":\\\"ozLUFEoR5IggqVtF3aPth9IznzLJWGSnGD266oo_Zog\\\"}\"}', NULL, '2026-05-25 08:13:31', '2026-05-25 08:13:31', NULL, NULL, NULL, NULL),
(10, 10, 4, 3, NULL, 'LMAMDw==', 'ahHvjhQpiLz+0qC0', 'EhyoCzniAo+OtMsqJAQsrg==', NULL, 'personal', '{\"n\":2,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"d4PXzxwUFhsNBQd0OVFJjLtDNee5HI6XCUQk_-lyhlo\\\",\\\"y\\\":\\\"ozLUFEoR5IggqVtF3aPth9IznzLJWGSnGD266oo_Zog\\\"}\"}', NULL, '2026-05-25 08:13:57', '2026-05-25 08:13:57', NULL, NULL, NULL, NULL),
(11, 11, 3, 4, NULL, '2PUMRt5PKTfnFQ47OU/Kk4ha01M=', 'Jtm9imisA8B/BmDk', 'kDAAdamoQNuS3RpRqdgkCQ==', NULL, 'personal', '{\"n\":0,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"iIh4sheF_AfRVEBzRvhb_aig-A9dPkC-MNG9ec1ltfg\\\",\\\"y\\\":\\\"uE-jPUb3XId5GpszkGCD-8W9bOs8Il7j0Zamp7h47Eg\\\"}\"}', '{\"ik_dh_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"qV6_zAu944fNvul_FMcDYNllvXJ13wgB7pofOhHgcjk\\\",\\\"y\\\":\\\"oWSno2K6izNuq9GZIrI0VgfdtDyXfMWAAFFAARQaSu8\\\"}\",\"ek_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"i5gvXzbSFPgV8lgfPZvTN7L4J6ANwEcBYOq3nNcKGLI\\\",\\\"y\\\":\\\"H0wyB8Wl0wJpeTywBx88gfLcYYnBhddgic1uF3kZyEA\\\"}\",\"spk_id\":1779696188114,\"opk_id\":177969618811500}', '2026-05-25 08:14:33', '2026-05-25 08:14:33', NULL, NULL, NULL, NULL),
(12, 12, 4, 3, NULL, '+7O2RQ==', 'TrZrDshfZ/49LFWy', 'L9kBv0bGB9R+PmL9Qf5MbA==', NULL, 'personal', '{\"n\":3,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"d4PXzxwUFhsNBQd0OVFJjLtDNee5HI6XCUQk_-lyhlo\\\",\\\"y\\\":\\\"ozLUFEoR5IggqVtF3aPth9IznzLJWGSnGD266oo_Zog\\\"}\"}', NULL, '2026-05-25 08:15:09', '2026-05-25 08:15:09', NULL, NULL, NULL, NULL),
(13, 13, 3, 4, NULL, 'V+akNZWoW0wAGv07r1TusliP5YHuzVqw3/4K4NHrpqA=', 'and39yMIMfEjzb8v', 'i6FM20HC/CVSqn3VZOjUeQ==', NULL, 'personal', '{\"n\":1,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"iIh4sheF_AfRVEBzRvhb_aig-A9dPkC-MNG9ec1ltfg\\\",\\\"y\\\":\\\"uE-jPUb3XId5GpszkGCD-8W9bOs8Il7j0Zamp7h47Eg\\\"}\"}', NULL, '2026-05-25 08:20:17', '2026-05-25 08:20:17', NULL, NULL, NULL, NULL),
(14, 14, 4, 3, NULL, '3LV/exL4', 'PnRX/MeUvzPoDZYo', 'Tvgmegk1iQvwjjSOlsPdCg==', NULL, 'personal', '{\"n\":4,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"d4PXzxwUFhsNBQd0OVFJjLtDNee5HI6XCUQk_-lyhlo\\\",\\\"y\\\":\\\"ozLUFEoR5IggqVtF3aPth9IznzLJWGSnGD266oo_Zog\\\"}\"}', NULL, '2026-05-25 08:20:46', '2026-05-25 08:20:46', NULL, NULL, NULL, NULL),
(15, 15, 3, 4, NULL, 'ZIs=', 'EYutIybAIitJy+g9', '8cMUo9YcrIBSH+0f4iAB1Q==', NULL, 'personal', '{\"n\":2,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"iIh4sheF_AfRVEBzRvhb_aig-A9dPkC-MNG9ec1ltfg\\\",\\\"y\\\":\\\"uE-jPUb3XId5GpszkGCD-8W9bOs8Il7j0Zamp7h47Eg\\\"}\"}', NULL, '2026-05-25 08:20:54', '2026-05-25 08:20:54', NULL, NULL, NULL, NULL),
(16, 16, 3, 4, NULL, 'YN9/lbOrOA0+MgV4RXd7Jz560R9w/lx6oUaJh+N8V1GfrS1YSvmC9NaZP4fudgOdBgvBuK50H+PfX+pjqyDDg/uMI8dv87S7PLO6wt+Xiq5gY/0PK7wZJX3PtCdxktmHcMQg5+e97Dx65Phb+w==', '3DQTigCa7bPofpqw', 'hirs27h7o+aWtAtyBsJvxQ==', NULL, 'personal_file', '{\"n\":0,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"wBKgaosS7GtBfEUC0v1MbOtY5Tq3evOqeorapbSoL0A\\\",\\\"y\\\":\\\"rNYYtwQGPc7VL0YjQO6iDFqF7-pkXV0ZIKRtRWuBlrI\\\"}\"}', '{\"ik_dh_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"qV6_zAu944fNvul_FMcDYNllvXJ13wgB7pofOhHgcjk\\\",\\\"y\\\":\\\"oWSno2K6izNuq9GZIrI0VgfdtDyXfMWAAFFAARQaSu8\\\"}\",\"ek_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"gV1fCF3oIIvV4rKybjbnYksYx5WDUMM4Ro6bgT_ej-Y\\\",\\\"y\\\":\\\"bbpTHrUMRmM_oQrN_OWC0V10LnqbplyB_Z6jntTnTG8\\\"}\",\"spk_id\":1779696188114,\"opk_id\":177969618811503}', '2026-05-25 12:00:12', '2026-05-25 12:00:12', '3rd-Receipt-Muhammad Zulhilman Bin Tarmizi (1).pdf', 30964, 'application/pdf', 'uploads/encrypted/2a955ef073019d8c7631da17195a3758'),
(17, 17, 5, NULL, 4, 'v9d9uZX3RW5xPMzz3/r2gBP5R8XcASyDGxeONODE1Gqt85YmFEk=', 'ew1GeedEvYKp7vPb', 'zwShghbLIRnuePKys+lIbQ==', NULL, 'group', '{\"type\":\"sk\",\"iter\":0,\"sig\":\"UEY77ipxCpkE7DJDAVj49ZN1TKToXeByils+dLR4YFVUJNy5wOcXW5rQbuXDPajXqsbJM/sKuYeDKXIFSzL1ww==\"}', NULL, '2026-05-26 00:27:38', '2026-05-26 00:27:38', NULL, NULL, NULL, NULL),
(18, 18, 4, NULL, 4, 'aMg=', '5CjBXpD1Q/JD0fJf', 'aueVbs4J5Nwd+WOHUopgNQ==', NULL, 'group', '{\"type\":\"sk\",\"iter\":0,\"sig\":\"hGNMR8WGSA45IfM6EojKVA9ZAnj9U9aGGFUb+tgzL+WqCs+0DWA0KNzRFH0fjwYdDSakowzi7SjqcqXwirf0bA==\"}', NULL, '2026-05-26 00:29:13', '2026-05-26 00:29:13', NULL, NULL, NULL, NULL),
(19, 19, 3, NULL, 4, 'sJc=', 'LGwMOMSn3Cmuf5N/', 'n/Foitr4Tu4CkHLxcMHcVQ==', NULL, 'group', '{\"type\":\"sk\",\"iter\":0,\"sig\":\"Y053Eu3WdBVhqwiEPbwOaETmoiisntrIJ3Nn7B7LlCSfZx+0SRDM0voklCdH1cA+XZAo8r9jXl5H1DErwrCoeQ==\"}', NULL, '2026-05-26 00:46:16', '2026-05-26 00:46:16', NULL, NULL, NULL, NULL),
(20, 20, 3, 5, NULL, '/2nz', 'tMhCLBojSDefu+wB', 'AXXGE/WHzB3KT5UH5pjmEw==', NULL, 'personal', '{\"n\":0,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"kTGLz2NO6F6zgQVaJVI3ZAQoIE-mAPgLaDiGfGYzqQk\\\",\\\"y\\\":\\\"hXZ_6XUPTC79PmkSUcZtgokpCkcFUeXDC8SMLQUN7T0\\\"}\"}', '{\"ik_dh_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"qV6_zAu944fNvul_FMcDYNllvXJ13wgB7pofOhHgcjk\\\",\\\"y\\\":\\\"oWSno2K6izNuq9GZIrI0VgfdtDyXfMWAAFFAARQaSu8\\\"}\",\"ek_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"3eJjb1huIYXyJiwHsAU1nbO3CBH2RFaTG2Cmr17o4pk\\\",\\\"y\\\":\\\"OVvf562LJgrMOeMpKbXc2mOOTR2N-CEkgpgi5hxH9m4\\\"}\",\"spk_id\":1779756348457,\"opk_id\":177975634845700}', '2026-05-26 00:50:56', '2026-05-26 00:50:56', NULL, NULL, NULL, NULL),
(21, 21, 3, 5, NULL, 'HN482t4ESsWWXw==', 'pmjhv50zFpO9srHy', 'gotrvWTiLXCrNqouUlg+FQ==', NULL, 'personal', '{\"n\":1,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"kTGLz2NO6F6zgQVaJVI3ZAQoIE-mAPgLaDiGfGYzqQk\\\",\\\"y\\\":\\\"hXZ_6XUPTC79PmkSUcZtgokpCkcFUeXDC8SMLQUN7T0\\\"}\"}', NULL, '2026-05-26 00:51:07', '2026-05-26 00:51:07', NULL, NULL, NULL, NULL),
(22, 22, 5, 3, NULL, 'jBt7+hMl86bd/v4X+I+pVA==', 'kdu3wuSihx7EnyJG', 'CP7ffoDjsiJiV9WBRhSDUw==', NULL, 'personal', '{\"n\":0,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"OIp5N0-i7ucbZOvmA5ff3dM6OUng3qX4HLT237yqY88\\\",\\\"y\\\":\\\"sM_wgISU8A5utQ8f0Uci7AA45nA0qoHppI9RPG5Ezxs\\\"}\"}', '{\"ik_dh_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"OJ5j8w3gX-nA9Cpq4rkfHv6qhn-X-Mru7AiLMIJgioY\\\",\\\"y\\\":\\\"Z4UKjA9EYhHI9bOrY5ip5xsW_8Q4W_-KJXE17-iQEt8\\\"}\",\"ek_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"NQfG9iIhn4qmGbf2YO_U54PvS2QJq5YEWm6oCwUGuvc\\\",\\\"y\\\":\\\"6P_FdPIaabNvhEGMLG29mRPUlsGV2jDjEDeLmLz9JQw\\\"}\",\"spk_id\":1779756336837,\"opk_id\":177975633683700}', '2026-05-26 00:51:24', '2026-05-26 00:51:24', NULL, NULL, NULL, NULL),
(23, 23, 3, NULL, 4, '+sA=', 'XOc48p2T+tyMgjzc', 'PyY9mUCzshD+LEDTFR4b2w==', NULL, 'group', '{\"type\":\"sk\",\"iter\":1,\"sig\":\"y307WLiYDqj/GzZGrKWjytdqb8jOJ1TPtujXStPxFOPgQ70T1DjtBKk5VVaTfj+7iucz1ESFJxKHupC7oCpJVg==\"}', NULL, '2026-05-26 00:51:47', '2026-05-26 00:51:47', NULL, NULL, NULL, NULL),
(24, 24, 4, 5, NULL, 'CsY=', '9Nt13bybUa5mc4KC', 'LkdbmUPWagcWIkv1WOkTXw==', NULL, 'personal', '{\"n\":0,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"EJxh9AhFho44R1P-xv2tDUle68lrEHmXn9dL-D2QLnc\\\",\\\"y\\\":\\\"ef6rp6zADLdExaK1q0cmYtA1gaX-qadcObHWMZXbzA0\\\"}\"}', '{\"ik_dh_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"_WZXQXNNG-o8R4tCPLD4tAXzU42nvmHO982ttOqycMI\\\",\\\"y\\\":\\\"9bHoWj4MXAZcNl8EeAwqIb9z5-0mC268-JUeLHzMlxU\\\"}\",\"ek_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"0H6V1XyqmOCp7k2SFm-xljbhNRRc394SZzbrYxg_LSk\\\",\\\"y\\\":\\\"swm4YCsnoyD3vUc60FltolhTvJI6hs4Lp22SVGXEUME\\\"}\",\"spk_id\":1779756348457,\"opk_id\":177975634845702}', '2026-05-26 00:55:13', '2026-05-26 00:55:13', NULL, NULL, NULL, NULL),
(25, 25, 4, NULL, 4, 'Tg==', '1gs0QFCmFPDqqE5G', 'DRHG8x5bOAg5OAERsJ5vHA==', NULL, 'group', '{\"type\":\"sk\",\"iter\":0,\"sig\":\"2kHw3a+xbGBMVg5Boi3to0rrvZWbb7t33MZOleNTzVOHgAglWRAWun0Xh9vaAljB9Dhle1r4+3i5QZDt2mP0EQ==\"}', NULL, '2026-05-26 01:12:17', '2026-05-26 01:12:17', NULL, NULL, NULL, NULL),
(26, 26, 5, NULL, 4, 'nQ==', 'DtQ1ankz34gGTNrH', 'nxOO9rj17v3bGKUAEDdvXA==', NULL, 'group', '{\"type\":\"sk\",\"iter\":0,\"sig\":\"1BJI/Q45yNoIAoVYsIrNAXVHPgQ6twRHUCaZiBwR5wyReRXKgcQvi85944g7VvA+Q+DVVdgvdxIZEl1iP7E3zQ==\"}', NULL, '2026-05-26 01:13:04', '2026-05-26 01:13:04', NULL, NULL, NULL, NULL),
(27, 27, 5, 4, NULL, 'OgKv', '33JPf8yOHQr5UvUL', 'Ui75AGGRTEtm/1Y2X15tgA==', NULL, 'personal', '{\"n\":0,\"pn\":0,\"dh\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"VMZk67FaWvyS1tBRXrmmuAI7FtHDAOkYY-vpEL2BOMg\\\",\\\"y\\\":\\\"Ohhs60Uves_EfNEQ74zyWepdHBtaMPTXxPUAQItIps8\\\"}\"}', '{\"ik_dh_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"OJ5j8w3gX-nA9Cpq4rkfHv6qhn-X-Mru7AiLMIJgioY\\\",\\\"y\\\":\\\"Z4UKjA9EYhHI9bOrY5ip5xsW_8Q4W_-KJXE17-iQEt8\\\"}\",\"ek_pub\":\"{\\\"crv\\\":\\\"P-256\\\",\\\"ext\\\":true,\\\"key_ops\\\":[],\\\"kty\\\":\\\"EC\\\",\\\"x\\\":\\\"fyAdHuIVgyMbVssiv5OTHe2qnL3im_HIzTYZ-GhWxb4\\\",\\\"y\\\":\\\"7zs4WtPcoZIuyqQaERe1wiu3GZ5J7yqkiK1zO-ClmW4\\\"}\",\"spk_id\":1779756842266,\"opk_id\":177975684226600}', '2026-05-26 01:13:18', '2026-05-26 01:13:18', NULL, NULL, NULL, NULL),
(28, 28, 4, NULL, 4, 'KBg=', 'XNVIErbC/JleeIli', 'dS9x2qJbONiUZUMVVGqrZg==', NULL, 'group', '{\"type\":\"sk\",\"iter\":0,\"sig\":\"lB7bzN2n4wnThB+IMmChHTVnKxuahelMTbtkSTNs+qRaj/DCHcew2/i7ttwBXifTb8WpjyK5fxu9tv1btqj0xg==\"}', NULL, '2026-05-26 01:14:24', '2026-05-26 01:14:24', NULL, NULL, NULL, NULL),
(29, 29, 5, NULL, 4, '4WiG', 'AEjDomPmwMxHQm1J', 'UuQJ8vxXDpI4Wi+66PMwpw==', NULL, 'group', '{\"type\":\"sk\",\"iter\":1,\"sig\":\"7ZleWa3WNLDQGr2CaOC1tdVx/lOd/+xu2Qpa3Vb3o52MSRy6H1404CVOjak6XJW4Ojzs47fWBeCwGN16sBuQFw==\"}', NULL, '2026-05-26 01:23:21', '2026-05-26 01:23:21', NULL, NULL, NULL, NULL),
(30, 30, 4, NULL, 4, 'JvA7FA==', 'j5WQySBlZelOcqWd', 'bxaPz+wxpEb1JeaImpCvRg==', NULL, 'group', '{\"type\":\"sk\",\"iter\":1,\"sig\":\"KkNAY7vPMMApZqtz0FCZIC+BxNpFGj7Kx43dinDVWj+OyttkLPO0WNjFs5SZx78sQ9rqHd/tXnMffuBlPbEEgw==\"}', NULL, '2026-05-26 01:23:32', '2026-05-26 01:23:32', NULL, NULL, NULL, NULL),
(31, 31, 4, NULL, 4, 'Xtk=', 'soY6XiSKorzJRYpS', '3FqDyRxZ0wyOLZkWJsHbjQ==', NULL, 'group', '{\"type\":\"sk\",\"iter\":0,\"sig\":\"Y7b+Pr4PVMNRu+vSk856jme8LADxzZqhwZY1Ko6E50b03pfeShEVOVUnLVfDwnoP+H0ZA7n/eMCqAvl3EehOng==\"}', NULL, '2026-05-26 01:31:05', '2026-05-26 01:31:05', NULL, NULL, NULL, NULL),
(32, 32, 3, NULL, 4, 'rRtR60E=', 'orqi4rmwpwjywdwE', 'NCfBEIFr1WrVBiRurXoMwA==', NULL, 'group', '{\"type\":\"sk\",\"iter\":0,\"sig\":\"E/8gQW1WDA41NtIK0gSx9G0uyBy9x7VbWgD3RnxLWeX0KMuTxwAOyhQV6AZcyHRAPjnxa9s+GeTho8fPGWjURQ==\"}', NULL, '2026-05-26 01:31:17', '2026-05-26 01:31:17', NULL, NULL, NULL, NULL),
(33, 33, 3, NULL, 5, 'WLw=', 'Nw16CGJSpuQGInEf', 'hg0Oz/AbGRTc4IGZrP8f7A==', NULL, 'group', '{\"type\":\"sk\",\"iter\":0,\"sig\":\"mqq1mHLzP7z3MTLxClUunhwrT90Dye9H+6FTPrZHcAvOCgh1WWjMT7Slti5aG7lHk7UVmzOfgoV7baB1nbxFug==\"}', NULL, '2026-05-26 01:33:00', '2026-05-26 01:33:00', NULL, NULL, NULL, NULL),
(34, 34, 3, NULL, 5, '2uX7ONKOjdjqsKto7mLpPg7S7yol', 'EZ+o8wLZuyMKYEDD', '6xOVx6vv/S3CbCcgCf8WGg==', NULL, 'group', '{\"type\":\"sk\",\"iter\":1,\"sig\":\"jghha/6zqdbkhTZGNBlQk3q8WGWjQKv9IExZ2l+ToBcDy/SZy+fmYPbjz7I+zJnbPbJkJsKXELMv319NlGEA+w==\"}', NULL, '2026-05-26 01:33:20', '2026-05-26 01:33:20', NULL, NULL, NULL, NULL),
(35, 35, 5, NULL, 5, 'JsNTuVik2CXf4qbRV1Adp5VJtQEQwI+ZeIKkV72NRwXpuD8CUxRCWhYyXphK9/umwPH42qpoYHLIanP1K9Mm+nmHeHvpz/LnxAjpsmgxBAclb51TBKvn5VYeN788OCQnpTRpz+Eial6c1/9H4Q==', 'uQsAQt48WO09vivR', 'e03wal+PR1fM1IgEtqpDIg==', NULL, 'group_file', '{\"type\":\"sk\",\"iter\":0,\"sig\":\"NZf+5HmP5dG6NA0Nu8KSkKrcVrC1436KsXNDEaC/1eO8EsIIwOhVcKqi1UDyprr8oLC++xKXLsYrs6DJ7aqsRA==\"}', NULL, '2026-05-26 01:34:35', '2026-05-26 01:34:35', '3rd-Receipt-Muhammad Zulhilman Bin Tarmizi.pdf', 30964, 'application/pdf', 'uploads/encrypted/654d96931db4a9c12d19f515b6072573'),
(36, 36, 5, NULL, 5, 'F32ONVBDF96+iQEhCyZQtriugLpoDpStIoAZUO4=', 'tnBQpa7bJCzBXJp9', 'xiJ/NJ8EwaJKO7FYgRenxA==', NULL, 'group', '{\"type\":\"sk\",\"iter\":1,\"sig\":\"9mx2I5/2rfmSxe5dgrwqIarWu0Hl0rmjTeaY0R97MQA6/44oI6lKa1nBUoImrSn4mYTQ8S7VCbYhPz2RViSanQ==\"}', NULL, '2026-05-26 01:34:35', '2026-05-26 01:34:35', NULL, NULL, NULL, NULL),
(37, 37, 5, NULL, 5, '9/8=', 'eSRmX3DyvQSFiUCa', 'e9+NzWfwqeUSxvLObv3HVg==', NULL, 'group', '{\"type\":\"sk\",\"iter\":0,\"sig\":\"cD0PnMMmk9iDF2jjTFlfR/gvMm1iSx2rtrDoZGh70LwujOutOR3SvNlwscBv8Fbf3lvdw+Q0Wfsy008zTNL+vQ==\"}', NULL, '2026-05-26 02:59:21', '2026-05-26 02:59:21', NULL, NULL, NULL, NULL),
(38, 38, 4, NULL, 5, 'SNI=', 'kuEvHmFsNwIBIcEa', 'pDCKY+GKwT7Kbp9t96avAg==', NULL, 'group', '{\"type\":\"sk\",\"iter\":0,\"sig\":\"KAMmi2H9KeuOwDG5xyAJhM4ztEra66u8ImFcx+IaeBvg0vsP+9NyAG149MxxB7vh2E8ZyZUghcAgTjIoKGRShg==\"}', NULL, '2026-05-26 02:59:28', '2026-05-26 02:59:28', NULL, NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_vault_shares`
--
ALTER TABLE `admin_vault_shares`
  ADD PRIMARY KEY (`share_id`),
  ADD UNIQUE KEY `unique_user_vault_share` (`user_id`);

--
-- Indexes for table `backup_shares`
--
ALTER TABLE `backup_shares`
  ADD PRIMARY KEY (`share_id`),
  ADD UNIQUE KEY `unique_user_backup_share` (`user_id`);

--
-- Indexes for table `messages_backup`
--
ALTER TABLE `messages_backup`
  ADD PRIMARY KEY (`backup_id`),
  ADD UNIQUE KEY `unique_original_message` (`original_message_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_vault_shares`
--
ALTER TABLE `admin_vault_shares`
  MODIFY `share_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `backup_shares`
--
ALTER TABLE `backup_shares`
  MODIFY `share_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `messages_backup`
--
ALTER TABLE `messages_backup`
  MODIFY `backup_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
