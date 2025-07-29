-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jul 29, 2025 at 11:24 AM
-- Server version: 8.0.41-cll-lve
-- PHP Version: 8.1.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `timeless_health`
--

-- --------------------------------------------------------

--
-- Table structure for table `about_us`
--

CREATE TABLE `about_us` (
  `id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `department_id` bigint UNSIGNED NOT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `clients_date_and_time` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `meeting_link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `user_id`, `department_id`, `reason`, `clients_date_and_time`, `created_at`, `updated_at`, `meeting_link`, `status`) VALUES
(20, 13, 6, 'test', '2025-05-03 23:01:00', '2025-05-03 21:58:44', '2025-05-03 22:03:35', 'https://meet.google.com/vaq-tgwa-qsu', 'completed'),
(24, 13, 7, 'test', '2025-06-02 10:00:00', '2025-06-01 19:22:44', '2025-06-04 00:06:40', 'https://meet.google.com/vaq-tgwa-qsu', 'completed'),
(25, 13, 7, 'test', NULL, '2025-06-01 19:24:26', '2025-06-01 19:24:26', NULL, 'pending'),
(26, 13, 7, 'teste1', NULL, '2025-06-01 19:30:24', '2025-06-01 19:30:24', NULL, 'pending'),
(27, 13, 4, 'test', NULL, '2025-06-01 19:43:36', '2025-06-01 19:43:36', NULL, 'pending'),
(28, 39, 7, 'test', NULL, '2025-06-01 20:14:50', '2025-06-01 20:14:50', NULL, 'pending'),
(29, 39, 7, 'appointment', NULL, '2025-06-01 20:26:53', '2025-06-01 20:26:53', NULL, 'pending'),
(31, 13, 7, 'fever', NULL, '2025-06-01 21:14:16', '2025-06-01 21:14:16', NULL, 'pending'),
(32, 13, 7, 'fever', NULL, '2025-06-01 21:28:05', '2025-06-01 21:28:05', NULL, 'pending'),
(33, 13, 7, 'fever', NULL, '2025-06-01 21:32:09', '2025-06-01 21:32:09', NULL, 'pending'),
(34, 13, 7, 'fever', NULL, '2025-06-01 21:33:39', '2025-06-01 21:33:39', NULL, 'pending'),
(35, 39, 7, 'fever', '2025-06-11 10:10:00', '2025-06-01 21:35:49', '2025-06-13 09:24:40', 'https://meet.google.com/oiq-kphh-hpi', 'pending'),
(38, 55, 7, 'I have boils around my armpit which are not clearing and rashes around the area too', '2025-06-05 16:40:00', '2025-06-04 11:32:13', '2025-06-05 15:56:38', 'https://meet.google.com/wts-dkpu-jyj', 'completed'),
(39, 55, 7, 'Doctor	I have boils around my armpit which are not clearing and rashes around the area too', NULL, '2025-06-05 10:24:42', '2025-06-05 10:24:42', NULL, 'pending'),
(40, 79, 7, 'Health issues \nChest pain body pain and sore throat', '2025-06-11 16:00:00', '2025-06-10 18:07:44', '2025-06-11 15:31:51', 'https://meet.google.com/dva-pznk-xcw', 'completed'),
(41, 79, 7, 'Hepatitis B virus', NULL, '2025-06-10 18:09:09', '2025-06-10 18:09:09', NULL, 'pending'),
(42, 79, 7, 'Hepatitis', NULL, '2025-07-21 12:36:23', '2025-07-21 12:36:23', NULL, 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `appointment__department__doctors`
--

CREATE TABLE `appointment__department__doctors` (
  `id` bigint UNSIGNED NOT NULL,
  `department_id` bigint UNSIGNED DEFAULT NULL,
  `doctor_id` bigint UNSIGNED DEFAULT NULL,
  `appointment_id` bigint UNSIGNED DEFAULT NULL,
  `available_data_and_time` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `meeting_link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointment__department__doctors`
--

INSERT INTO `appointment__department__doctors` (`id`, `department_id`, `doctor_id`, `appointment_id`, `available_data_and_time`, `created_at`, `updated_at`, `meeting_link`, `status`) VALUES
(53, 7, 14, 24, '2025-06-02 10:00:00', '2025-06-04 00:06:40', '2025-06-04 00:06:40', 'https://meet.google.com/vaq-tgwa-qsu', 'completed'),
(54, 7, 12, 38, '2025-06-04 16:50:00', '2025-06-04 15:42:16', '2025-06-04 15:42:16', 'https://meet.google.com/wts-dkpu-jyj', 'pending'),
(55, 7, 12, 38, '2025-06-05 16:30:00', '2025-06-05 12:07:35', '2025-06-05 12:07:35', 'https://meet.google.com/wts-dkpu-jyj', 'pending'),
(56, 7, 12, 38, '2025-06-05 16:40:00', '2025-06-05 15:11:50', '2025-06-05 15:11:50', 'https://meet.google.com/wts-dkpu-jyj', 'pending'),
(57, 7, 12, 38, '2025-06-05 16:40:00', '2025-06-05 15:56:38', '2025-06-05 15:56:38', 'https://meet.google.com/wts-dkpu-jyj', 'completed'),
(58, 7, 12, 38, '2025-06-05 16:40:00', '2025-06-05 15:57:40', '2025-06-05 15:57:40', 'https://meet.google.com/wts-dkpu-jyj', 'completed'),
(59, 7, 12, 40, '2025-06-11 10:30:00', '2025-06-11 06:51:19', '2025-06-11 06:51:19', 'https://meet.google.com/dva-pznk-xcw', 'pending'),
(61, 7, 12, 40, '2025-06-11 16:00:00', '2025-06-11 09:54:15', '2025-06-11 09:54:15', 'https://meet.google.com/dva-pznk-xcw', 'pending'),
(62, 7, 12, 40, '2025-06-11 16:00:00', '2025-06-11 15:31:51', '2025-06-11 15:31:51', 'https://meet.google.com/dva-pznk-xcw', 'completed'),
(63, 7, 15, 35, '2025-06-11 10:10:00', '2025-06-13 09:24:39', '2025-06-13 09:24:39', 'https://meet.google.com/oiq-kphh-hpi', 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `billing_cycles`
--

CREATE TABLE `billing_cycles` (
  `id` bigint UNSIGNED NOT NULL,
  `plans_id` bigint UNSIGNED NOT NULL,
  `duration` int NOT NULL,
  `price` decimal(8,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `billing_cycles`
--

INSERT INTO `billing_cycles` (`id`, `plans_id`, `duration`, `price`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1800.00, '2025-05-01 18:40:00', '2025-05-01 18:40:00'),
(2, 2, 1, 2250.00, '2025-04-15 11:49:12', '2025-04-15 11:49:12'),
(3, 3, 1, 3150.00, '2025-04-15 11:49:46', '2025-04-15 11:49:46');

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cache`
--

INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
('@edim michael|102.88.52.242', 'i:2;', 1751556436),
('@edim michael|102.88.52.242:timer', 'i:1751556436;', 1751556436),
('08a35293e09f508494096c1c1b3819edb9df50db', 'i:1;', 1752527919),
('08a35293e09f508494096c1c1b3819edb9df50db:timer', 'i:1752527919;', 1752527919),
('1352246e33277e9d3c9090a434fa72cfa6536ae2', 'i:1;', 1750360135),
('1352246e33277e9d3c9090a434fa72cfa6536ae2:timer', 'i:1750360135;', 1750360135),
('17ba0791499db908433b80f37c5fbc89b870084b', 'i:2;', 1748936373),
('17ba0791499db908433b80f37c5fbc89b870084b:timer', 'i:1748936373;', 1748936373),
('1d513c0bcbe33b2e7440e5e14d0b22ef95c9d673', 'i:1;', 1749914574),
('1d513c0bcbe33b2e7440e5e14d0b22ef95c9d673:timer', 'i:1749914574;', 1749914574),
('1f1362ea41d1bc65be321c0a378a20159f9a26d0', 'i:1;', 1749482581),
('1f1362ea41d1bc65be321c0a378a20159f9a26d0:timer', 'i:1749482581;', 1749482581),
('215bb47da8fac3342b858ac3db09b033c6c46e0b', 'i:1;', 1753218352),
('215bb47da8fac3342b858ac3db09b033c6c46e0b:timer', 'i:1753218352;', 1753218352),
('2a459380709e2fe4ac2dae5733c73225ff6cfee1', 'i:3;', 1748944387),
('2a459380709e2fe4ac2dae5733c73225ff6cfee1:timer', 'i:1748944387;', 1748944387),
('2d0c8af807ef45ac17cafb2973d866ba8f38caa9', 'i:1;', 1750798772),
('2d0c8af807ef45ac17cafb2973d866ba8f38caa9:timer', 'i:1750798772;', 1750798772),
('35e995c107a71caeb833bb3b79f9f54781b33fa1', 'i:1;', 1749457203),
('35e995c107a71caeb833bb3b79f9f54781b33fa1:timer', 'i:1749457203;', 1749457203),
('3c26dffc8a2e8804dfe2c8a1195cfaa5ef6d0014', 'i:1;', 1750532617),
('3c26dffc8a2e8804dfe2c8a1195cfaa5ef6d0014:timer', 'i:1750532617;', 1750532617),
('450ddec8dd206c2e2ab1aeeaa90e85e51753b8b7', 'i:1;', 1749548639),
('450ddec8dd206c2e2ab1aeeaa90e85e51753b8b7:timer', 'i:1749548639;', 1749548639),
('4cd66dfabbd964f8c6c4414b07cdb45dae692e19', 'i:1;', 1750869273),
('4cd66dfabbd964f8c6c4414b07cdb45dae692e19:timer', 'i:1750869273;', 1750869273),
('511a418e72591eb7e33f703f04c3fa16df6c90bd', 'i:2;', 1748914779),
('511a418e72591eb7e33f703f04c3fa16df6c90bd:timer', 'i:1748914779;', 1748914779),
('59129aacfb6cebbe2c52f30ef3424209f7252e82', 'i:1;', 1748948720),
('59129aacfb6cebbe2c52f30ef3424209f7252e82:timer', 'i:1748948720;', 1748948720),
('64e095fe763fc62418378753f9402623bea9e227', 'i:1;', 1748940594),
('64e095fe763fc62418378753f9402623bea9e227:timer', 'i:1748940593;', 1748940593),
('6fb84aed32facd1299ee1e77c8fd2b1a6352669e', 'i:1;', 1753692848),
('6fb84aed32facd1299ee1e77c8fd2b1a6352669e:timer', 'i:1753692848;', 1753692848),
('8e63fd3e77796b102589b1ba1e4441c7982e4132', 'i:1;', 1753577039),
('8e63fd3e77796b102589b1ba1e4441c7982e4132:timer', 'i:1753577039;', 1753577039),
('8ee51caaa2c2f4ee2e5b4b7ef5a89db7df1068d7', 'i:1;', 1751809604),
('8ee51caaa2c2f4ee2e5b4b7ef5a89db7df1068d7:timer', 'i:1751809604;', 1751809604),
('8effee409c625e1a2d8f5033631840e6ce1dcb64', 'i:2;', 1749036707),
('8effee409c625e1a2d8f5033631840e6ce1dcb64:timer', 'i:1749036707;', 1749036707),
('a72b20062ec2c47ab2ceb97ac1bee818f8b6c6cb', 'i:2;', 1748994591),
('a72b20062ec2c47ab2ceb97ac1bee818f8b6c6cb:timer', 'i:1748994591;', 1748994591),
('adegunlehintitilayo@gmail.com|197.211.63.15', 'i:1;', 1750618064),
('adegunlehintitilayo@gmail.com|197.211.63.15:timer', 'i:1750618064;', 1750618064),
('admin@gmail.com|102.219.153.42', 'i:2;', 1748939809),
('admin@gmail.com|102.219.153.42:timer', 'i:1748939809;', 1748939809),
('admin@timelesshealthcare247|105.113.117.247', 'i:1;', 1752082306),
('admin@timelesshealthcare247|105.113.117.247:timer', 'i:1752082306;', 1752082306),
('admin@timelesshealthcare247|105.113.81.84', 'i:1;', 1749052426),
('admin@timelesshealthcare247|105.113.81.84:timer', 'i:1749052425;', 1749052425),
('b37f6ddcefad7e8657837d3177f9ef2462f98acf', 'i:2;', 1750767043),
('b37f6ddcefad7e8657837d3177f9ef2462f98acf:timer', 'i:1750767043;', 1750767043),
('b74f5ee9461495ba5ca4c72a7108a23904c27a05', 'i:2;', 1749555120),
('b74f5ee9461495ba5ca4c72a7108a23904c27a05:timer', 'i:1749555120;', 1749555120),
('b888b29826bb53dc531437e723738383d8339b56', 'i:1;', 1749764869),
('b888b29826bb53dc531437e723738383d8339b56:timer', 'i:1749764869;', 1749764869),
('c097638f92de80ba8d6c696b26e6e601a5f61eb7', 'i:1;', 1753103839),
('c097638f92de80ba8d6c696b26e6e601a5f61eb7:timer', 'i:1753103839;', 1753103839),
('ca3512f4dfa95a03169c5a670a4c91a19b3077b4', 'i:2;', 1749122880),
('ca3512f4dfa95a03169c5a670a4c91a19b3077b4:timer', 'i:1749122880;', 1749122880),
('d02560dd9d7db4467627745bd6701e809ffca6e3', 'i:5;', 1749122895),
('d02560dd9d7db4467627745bd6701e809ffca6e3:timer', 'i:1749122895;', 1749122895),
('daron.larson6@gmail.com|65.130.1.114', 'i:3;', 1751219341),
('daron.larson6@gmail.com|65.130.1.114:timer', 'i:1751219341;', 1751219341),
('doctor@timelesshealthcare247.com|102.219.153.42', 'i:1;', 1749121470),
('doctor@timelesshealthcare247.com|102.219.153.42:timer', 'i:1749121470;', 1749121470),
('doctor@timelesshealthcare247.com|102.88.111.112', 'i:1;', 1748986182),
('doctor@timelesshealthcare247.com|102.88.111.112:timer', 'i:1748986182;', 1748986182),
('doctor@timelesshealthcare247.com|102.90.116.150', 'i:2;', 1750109362),
('doctor@timelesshealthcare247.com|102.90.116.150:timer', 'i:1750109362;', 1750109362),
('doctor@timelesshealthcare247.com|102.90.116.49', 'i:1;', 1749848512),
('doctor@timelesshealthcare247.com|102.90.116.49:timer', 'i:1749848512;', 1749848512),
('doctor@timelesshealthcare247.com|102.90.117.232', 'i:1;', 1749408858),
('doctor@timelesshealthcare247.com|102.90.117.232:timer', 'i:1749408858;', 1749408858),
('doctor@timelesshealthcare247.com|105.113.81.84', 'i:1;', 1749052327),
('doctor@timelesshealthcare247.com|105.113.81.84:timer', 'i:1749052326;', 1749052326),
('donatus@timelesshealthcare247.com|102.219.153.42', 'i:1;', 1749556762),
('donatus@timelesshealthcare247.com|102.219.153.42:timer', 'i:1749556762;', 1749556762),
('donatus@timelesshealthcare247.com|105.112.22.126', 'i:1;', 1749187379),
('donatus@timelesshealthcare247.com|105.112.22.126:timer', 'i:1749187379;', 1749187379),
('donatus@timelesshealthcare247.com|129.205.124.243', 'i:2;', 1749557362),
('donatus@timelesshealthcare247.com|129.205.124.243:timer', 'i:1749557362;', 1749557362),
('donatusvictor76@gmail.com|105.113.102.197', 'i:3;', 1753037552),
('donatusvictor76@gmail.com|105.113.102.197:timer', 'i:1753037552;', 1753037552),
('donatusvictor76@gmail.com|105.113.85.69', 'i:1;', 1753692716),
('donatusvictor76@gmail.com|105.113.85.69:timer', 'i:1753692716;', 1753692716),
('e62d7f1eb43d87c202d2f164ba61297e71be80f4', 'i:1;', 1750534472),
('e62d7f1eb43d87c202d2f164ba61297e71be80f4:timer', 'i:1750534472;', 1750534472),
('e6c3dd630428fd54834172b8fd2735fed9416da4', 'i:1;', 1748965702),
('e6c3dd630428fd54834172b8fd2735fed9416da4:timer', 'i:1748965702;', 1748965702),
('emediong moses|129.222.206.134', 'i:1;', 1748940545),
('emediong moses|129.222.206.134:timer', 'i:1748940545;', 1748940545),
('emediong@timelesshealthcare247|129.222.206.134', 'i:1;', 1748940580),
('emediong@timelesshealthcare247|129.222.206.134:timer', 'i:1748940580;', 1748940580),
('esther@timelesshealthcare247.com|105.112.210.146', 'i:1;', 1752745298),
('esther@timelesshealthcare247.com|105.112.210.146:timer', 'i:1752745298;', 1752745298),
('fakeemail@yahoo.com|102.219.153.42', 'i:2;', 1749122793),
('fakeemail@yahoo.com|102.219.153.42:timer', 'i:1749122793;', 1749122793),
('hugolehmann92@outlook.com|188.126.89.42', 'i:2;', 1751208889),
('hugolehmann92@outlook.com|188.126.89.42:timer', 'i:1751208889;', 1751208889),
('isobel72@hotmail.com|65.130.1.114', 'i:5;', 1751219288),
('isobel72@hotmail.com|65.130.1.114:timer', 'i:1751219288;', 1751219288),
('kvngmnm|102.91.72.139', 'i:1;', 1749548606),
('kvngmnm|102.91.72.139:timer', 'i:1749548606;', 1749548606),
('kvngmnm|105.112.230.164', 'i:1;', 1749564560),
('kvngmnm|105.112.230.164:timer', 'i:1749564560;', 1749564560),
('kvngmnm|197.210.53.67', 'i:1;', 1749578443),
('kvngmnm|197.210.53.67:timer', 'i:1749578443;', 1749578443),
('ndianabasi@timelesshealthcare247.com|105.116.11.10', 'i:1;', 1749319069),
('ndianabasi@timelesshealthcare247.com|105.116.11.10:timer', 'i:1749319069;', 1749319069),
('parkercatherine622@gmail.com|188.126.89.42', 'i:2;', 1751208900),
('parkercatherine622@gmail.com|188.126.89.42:timer', 'i:1751208900;', 1751208900),
('patience@timelesshealthcare247.com|105.116.0.170', 'i:1;', 1749586200),
('patience@timelesshealthcare247.com|105.116.0.170:timer', 'i:1749586200;', 1749586200),
('patience@timelesshealthcare247.com|197.211.63.15', 'i:1;', 1749074377),
('patience@timelesshealthcare247.com|197.211.63.15:timer', 'i:1749074377;', 1749074377),
('patiencemoses124@gmail.com|197.211.63.131', 'i:1;', 1749123469),
('patiencemoses124@gmail.com|197.211.63.131:timer', 'i:1749123469;', 1749123469),
('patiencemoses124@gmail.com|197.211.63.15', 'i:1;', 1749471772),
('patiencemoses124@gmail.com|197.211.63.15:timer', 'i:1749471772;', 1749471772),
('patient@gmail.com|102.219.153.42', 'i:1;', 1749122741),
('patient@gmail.com|102.219.153.42:timer', 'i:1749122741;', 1749122741),
('patient@yahoo.com|102.219.153.42', 'i:1;', 1749122821),
('patient@yahoo.com|102.219.153.42:timer', 'i:1749122821;', 1749122821),
('patient@yahoo.com|102.90.101.73', 'i:1;', 1753089699),
('patient@yahoo.com|102.90.101.73:timer', 'i:1753089699;', 1753089699),
('priscilla.ushie@yahoo.com|102.91.5.100', 'i:1;', 1751658122),
('priscilla.ushie@yahoo.com|102.91.5.100:timer', 'i:1751658121;', 1751658121),
('priscilla@timelesshealthcare247.com|102.91.5.100', 'i:2;', 1751658191),
('priscilla@timelesshealthcare247.com|102.91.5.100:timer', 'i:1751658191;', 1751658191),
('quincy.huels@yatdew.com|65.130.1.114', 'i:2;', 1751219478),
('quincy.huels@yatdew.com|65.130.1.114:timer', 'i:1751219478;', 1751219478),
('rosa.brown@rezult.org|188.126.89.42', 'i:4;', 1751208803),
('rosa.brown@rezult.org|188.126.89.42:timer', 'i:1751208803;', 1751208803),
('sharon|197.211.63.15', 'i:2;', 1750618090),
('sharon|197.211.63.15:timer', 'i:1750618090;', 1750618090),
('skila|102.88.110.36', 'i:1;', 1749040801),
('skila|102.88.110.36:timer', 'i:1749040801;', 1749040801),
('skila|102.91.5.100', 'i:4;', 1751658059),
('skila|102.91.5.100:timer', 'i:1751658059;', 1751658059),
('uzonna@timelesshealthcare247.com|102.88.111.112', 'i:2;', 1748986220),
('uzonna@timelesshealthcare247.com|102.88.111.112:timer', 'i:1748986220;', 1748986220),
('vykturdonalty@gmail.comm|102.219.153.42', 'i:1;', 1749122602),
('vykturdonalty@gmail.comm|102.219.153.42:timer', 'i:1749122602;', 1749122602),
('zinny|102.90.81.210', 'i:1;', 1749036660),
('zinny|102.90.81.210:timer', 'i:1749036660;', 1749036660);

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_us`
--

CREATE TABLE `contact_us` (
  `id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `created_at`, `updated_at`) VALUES
(4, 'Dieticians', '2025-05-01 22:37:47', '2025-05-01 22:37:47'),
(5, 'Fitness & Gymnastics', '2025-05-01 22:38:08', '2025-05-01 22:38:08'),
(6, 'Obstetricians & Gynecologists', '2025-05-01 22:38:46', '2025-05-01 22:38:46'),
(7, 'Doctor', '2025-05-24 18:32:09', '2025-05-24 18:32:09'),
(8, 'Nurse', '2025-06-14 11:08:59', '2025-06-14 11:08:59');

-- --------------------------------------------------------

--
-- Table structure for table `doctors`
--

CREATE TABLE `doctors` (
  `id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `doctors`
--

INSERT INTO `doctors` (`id`, `created_at`, `updated_at`) VALUES
(1, '2025-04-17 11:06:05', '2025-04-17 11:06:05');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_details`
--

CREATE TABLE `doctor_details` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `age` int NOT NULL,
  `qualification` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fee` decimal(8,2) NOT NULL,
  `department` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `doctor_details`
--

INSERT INTO `doctor_details` (`id`, `user_id`, `age`, `qualification`, `fee`, `department`, `active`, `created_at`, `updated_at`) VALUES
(10, 20, 35, 'Doctor', 0.00, '7', 1, '2025-06-01 20:07:53', '2025-06-01 20:07:53'),
(12, 60, 42, 'Doctor', 0.00, '7', 1, '2025-06-02 12:19:39', '2025-06-02 12:19:39'),
(14, 69, 30, 'Doctor', 0.00, '7', 1, '2025-06-03 23:36:22', '2025-06-03 23:36:22'),
(15, 72, 40, 'Doctor', 0.00, '7', 1, '2025-06-10 23:26:25', '2025-06-10 23:26:25');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_recommendations`
--

CREATE TABLE `doctor_recommendations` (
  `id` bigint UNSIGNED NOT NULL,
  `complain` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `medication` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `dosage` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `patient_id` bigint UNSIGNED NOT NULL,
  `doctor_id` bigint UNSIGNED NOT NULL,
  `patient_complain_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `features`
--

CREATE TABLE `features` (
  `id` bigint UNSIGNED NOT NULL,
  `plans_id` bigint UNSIGNED NOT NULL,
  `feature_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `features`
--

INSERT INTO `features` (`id`, `plans_id`, `feature_name`, `created_at`, `updated_at`) VALUES
(1, 1, '247/Access', '2025-04-15 11:50:56', '2025-04-15 11:50:56'),
(2, 2, '247/Access', '2025-04-15 11:51:19', '2025-04-15 11:51:19'),
(3, 3, '247/Access', '2025-04-15 11:51:58', '2025-04-15 11:51:58'),
(4, 1, 'Follow up & Support', '2025-04-29 19:48:30', '2025-04-29 19:48:30'),
(5, 1, 'Dedicated Care', '2025-04-29 19:48:30', '2025-04-29 19:48:30'),
(6, 1, 'Personalized Wellness Plans', '2025-04-29 19:50:11', '2025-04-29 19:50:11'),
(7, 1, 'Fitness Recommendations', '2025-04-29 19:50:55', '2025-04-29 19:50:55'),
(8, 1, 'Nutritional Guidance.', '2025-04-29 19:51:31', '2025-04-29 19:51:31'),
(9, 1, 'Health Education Resources', '2025-04-29 19:52:17', '2025-04-29 19:52:17'),
(10, 1, 'Preventive Health Programs', '2025-04-29 19:52:42', '2025-04-29 19:52:42'),
(11, 1, 'Health Information Centralise', '2025-04-29 19:53:24', '2025-04-29 19:53:24'),
(12, 2, 'Follow up & Support', '2025-04-29 19:48:30', '2025-04-29 19:48:30'),
(13, 2, 'Dedicated Care', '2025-04-29 19:48:30', '2025-04-29 19:48:30'),
(14, 2, 'Personalized Wellness Plans', '2025-04-29 19:50:11', '2025-04-29 19:50:11'),
(15, 2, 'Fitness Recommendations', '2025-04-29 19:50:55', '2025-04-29 19:50:55'),
(16, 2, 'Nutritional Guidance.', '2025-04-29 19:51:31', '2025-04-29 19:51:31'),
(17, 2, 'Health Education Resources', '2025-04-29 19:52:17', '2025-04-29 19:52:17'),
(18, 2, 'Preventive Health Programs', '2025-04-29 19:52:42', '2025-04-29 19:52:42'),
(19, 2, 'Health Information Centralise', '2025-04-29 19:53:24', '2025-04-29 19:53:24'),
(20, 3, 'Follow up & Support', '2025-04-29 19:48:30', '2025-04-29 19:48:30'),
(21, 3, 'Dedicated Care', '2025-04-29 19:48:30', '2025-04-29 19:48:30'),
(22, 3, 'Personalized Wellness Plans', '2025-04-29 19:50:11', '2025-04-29 19:50:11'),
(23, 3, 'Fitness Recommendations', '2025-04-29 19:50:55', '2025-04-29 19:50:55'),
(24, 3, 'Nutritional Guidance.', '2025-04-29 19:51:31', '2025-04-29 19:51:31'),
(25, 3, 'Health Education Resources', '2025-04-29 19:52:17', '2025-04-29 19:52:17'),
(26, 3, 'Preventive Health Programs', '2025-04-29 19:52:42', '2025-04-29 19:52:42'),
(27, 3, 'Health Information Centralise', '2025-04-29 19:53:24', '2025-04-29 19:53:24');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` bigint UNSIGNED NOT NULL,
  `invoice_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `plan_id` bigint UNSIGNED NOT NULL,
  `generated_at` date NOT NULL,
  `duration` int NOT NULL,
  `due_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('unpaid','paid','overdue') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `reference` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NGN',
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `invoice_number`, `user_id`, `plan_id`, `generated_at`, `duration`, `due_date`, `amount`, `status`, `reference`, `payment_method`, `currency`, `paid_at`, `created_at`, `updated_at`) VALUES
(3, '545708', 11, 1, '2025-04-29', 1, '2025-05-29', 100.00, 'paid', '681127b2e05f6', 'bank_transfer', 'NGN', '2025-04-29 19:26:52', '2025-04-29 19:25:31', '2025-04-29 19:26:52'),
(17, '979284', 13, 2, '2025-05-15', 1, '2025-06-15', 2250.00, 'unpaid', NULL, NULL, 'NGN', NULL, '2025-05-15 13:07:33', '2025-05-15 13:07:33'),
(19, '512270', 13, 2, '2025-05-21', 1, '2025-06-21', 2250.00, 'unpaid', NULL, NULL, 'NGN', NULL, '2025-05-21 10:12:54', '2025-05-21 10:12:54'),
(20, '803937', 20, 1, '2025-05-26', 6, '2025-11-26', 10800.00, 'unpaid', NULL, NULL, 'NGN', NULL, '2025-05-26 07:16:39', '2025-05-26 07:16:39'),
(21, '575149', 21, 1, '2025-05-26', 1, '2025-06-26', 1800.00, 'unpaid', NULL, NULL, 'NGN', NULL, '2025-05-26 07:45:07', '2025-05-26 07:45:07'),
(22, '619127', 24, 1, '2025-05-28', 1, '2025-06-28', 1800.00, 'unpaid', NULL, NULL, 'NGN', NULL, '2025-05-28 17:57:39', '2025-05-28 17:57:39'),
(23, '778648', 13, 1, '2025-06-01', 1, '2025-07-01', 1800.00, 'unpaid', NULL, NULL, 'NGN', NULL, '2025-06-01 18:41:48', '2025-06-01 18:41:48'),
(25, '860450', 55, 1, '2025-06-02', 1, '2025-07-02', 1800.00, 'paid', '683d8c06dcf90', 'bank_transfer', 'NGN', '2025-06-02 11:44:07', '2025-06-02 11:33:18', '2025-06-02 11:44:07'),
(26, '206618', 72, 3, '2025-06-09', 1, '2025-07-09', 3150.00, 'unpaid', NULL, NULL, 'NGN', NULL, '2025-06-09 11:42:02', '2025-06-09 11:42:02'),
(27, '728208', 74, 1, '2025-06-09', 1, '2025-07-09', 1800.00, 'unpaid', NULL, NULL, 'NGN', NULL, '2025-06-09 16:21:16', '2025-06-09 16:21:16'),
(28, '115300', 79, 1, '2025-06-10', 1, '2025-07-10', 1800.00, 'unpaid', NULL, NULL, 'NGN', NULL, '2025-06-10 11:32:36', '2025-06-10 11:32:36'),
(29, '435332', 79, 1, '2025-06-10', 1, '2025-07-10', 1800.00, 'unpaid', NULL, NULL, 'NGN', NULL, '2025-06-10 11:34:13', '2025-06-10 11:34:13'),
(30, '847155', 79, 1, '2025-06-10', 1, '2025-07-10', 1800.00, 'paid', '684872f448533', 'bank_transfer', 'NGN', '2025-06-10 18:02:39', '2025-06-10 18:01:19', '2025-06-10 18:02:39'),
(31, '783031', 92, 1, '2025-07-06', 1, '2025-08-06', 1800.00, 'unpaid', NULL, NULL, 'NGN', NULL, '2025-07-06 13:47:09', '2025-07-06 13:47:09'),
(32, '802176', 93, 1, '2025-07-14', 3, '2025-10-14', 5400.00, 'paid', '68757426c6c01', 'bank_transfer', 'NGN', '2025-07-14 21:20:27', '2025-07-14 21:18:25', '2025-07-14 21:20:27'),
(35, '704936', 94, 1, '2025-07-22', 1, '2025-08-22', 1800.00, 'unpaid', NULL, NULL, 'NGN', NULL, '2025-07-22 21:06:07', '2025-07-22 21:06:07'),
(36, '248004', 95, 1, '2025-07-27', 1, '2025-08-27', 1800.00, 'unpaid', NULL, NULL, 'NGN', NULL, '2025-07-27 00:44:00', '2025-07-27 00:44:00'),
(37, '882826', 96, 1, '2025-07-28', 1, '2025-08-28', 1800.00, 'paid', '68873a8abaa21', 'bank', 'NGN', '2025-07-28 08:54:54', '2025-07-28 08:53:25', '2025-07-28 08:54:54');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint UNSIGNED NOT NULL,
  `reserved_at` int UNSIGNED DEFAULT NULL,
  `available_at` int UNSIGNED NOT NULL,
  `created_at` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medical_histories`
--

CREATE TABLE `medical_histories` (
  `id` bigint UNSIGNED NOT NULL,
  `medical_condition` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `messege` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `user_id` bigint UNSIGNED NOT NULL,
  `medications` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `allergies` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `family_medical_history` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `previous_surgeries_or_hospitalizations` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medical_records`
--

CREATE TABLE `medical_records` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `doctor_id` bigint UNSIGNED NOT NULL,
  `diagnosis` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `medications` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `test_result` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `test_image` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `extra_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `conducted_on` date NOT NULL,
  `month` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `medical_records`
--

INSERT INTO `medical_records` (`id`, `user_id`, `doctor_id`, `diagnosis`, `medications`, `test_result`, `test_image`, `extra_notes`, `conducted_on`, `month`, `created_at`, `updated_at`) VALUES
(10, 79, 12, '1)Dyspepsia \n2) viral hepatitis ( B)\n3) MYALGIA', 'caps Omeprazole 20 mg twice a day for 10 days ( 30 minutes before good and drug)\n\nTabs athrotec one twice a day for a week ( after meals)\n\nTabs methocarbamol 1g twice a day for a week .\n\nLifestyle modicum and advice .', 'Nil', NULL, 'Has been tested for hep B , and on tenofovir and livoln forte prior consultation', '2025-06-11', 'June', '2025-06-11 15:36:39', '2025-06-11 15:36:39');

-- --------------------------------------------------------

--
-- Table structure for table `meetings`
--

CREATE TABLE `meetings` (
  `id` bigint UNSIGNED NOT NULL,
  `room_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `scheduled_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int UNSIGNED NOT NULL,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(24, '0001_01_01_000000_create_users_table', 1),
(25, '0001_01_01_000001_create_cache_table', 1),
(26, '0001_01_01_000002_create_jobs_table', 1),
(27, '2025_01_14_114012_create_doctors_table', 1),
(28, '2025_01_14_124517_create_patients_table', 1),
(29, '2025_01_15_144928_create_doctor_details_table', 1),
(30, '2025_01_15_145545_create_admins_table', 1),
(31, '2025_01_15_192850_create_departments_table', 1),
(32, '2025_01_19_135213_create_appointments_table', 1),
(33, '2025_01_20_004936_create_appointment__department__doctors_table', 1),
(34, '2025_01_20_081759_create_medical_histories_table', 1),
(35, '2025_01_24_011827_create_pricings_table', 1),
(36, '2025_01_24_013331_create_services_table', 1),
(37, '2025_01_24_013412_create_about_us_table', 1),
(38, '2025_01_24_013436_create_contact_us_table', 1),
(39, '2025_01_25_072049_create_plans_table', 1),
(40, '2025_01_25_072104_create_features_table', 1),
(41, '2025_01_25_072129_create_billing_cycles_table', 1),
(42, '2025_01_25_072204_create_user_plans_table', 1),
(43, '2025_02_03_124645_create_patient_complains_table', 1),
(44, '2025_02_05_135621_create_invoices_table', 1),
(45, '2025_02_13_121607_create_doctor_recommendations_table', 1),
(46, '2025_02_14_112251_create_meetings_table', 1),
(47, '2025_02_19_132728_create_patient_prescriptions_table', 2),
(48, '2025_02_24_172334_create_medical_records_table', 2),
(49, '2025_04_12_180332_update_users_data', 2),
(50, '2025_04_12_181032_add_columns_to_user_table', 3),
(51, '2025_04_26_132742_appointments_update', 4),
(52, '2025_04_26_145251_appointments_update2', 5),
(53, '2025_04_26_145500_appointments_update3', 6);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_reset_tokens`
--

INSERT INTO `password_reset_tokens` (`email`, `token`, `created_at`) VALUES
('aajijola3@gmail.com', '$2y$12$x0IlQfl.8MSdlpjOQUgeOut8VA2zXAPM5g9i1z9iF7koVudexJqLK', '2025-04-21 12:02:01'),
('donatusvictor76@gmail.com', '$2y$12$yCGxbJe0bIanfPKgi2yzOOZxylBFatBIzVnVFhJxvqiTzZ3zcxQ4u', '2025-07-09 07:29:14'),
('finestedim@gmail.com', '$2y$12$i1ZCmj9sIsYDUSVyRZQB5.VGAMI3aTHNgoZcESnmz.i5bVu4JhMfm', '2025-05-15 13:02:32'),
('maildavo@yahoo.com', '$2y$12$Ers5aAVzJqhI/eDwnbIkYeBpqyFZLv/hni1Ws71r3r4aFKvjPIimS', '2025-05-01 12:49:00'),
('mm@gmail.com', '$2y$12$X7YXX9JFBEHKae6DMSKjR.MnGPTfAxJwylbYtcy.56hdCsGVs5Dym', '2025-04-12 17:49:02'),
('swagerlomoh1155@gmail.com', '$2y$12$m579w4u0F2r3QJCJ5BQ0eeR5R6OeZLP7ICqlhuaKdIHSuQcjP2Zc6', '2025-06-10 09:48:29');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_complains`
--

CREATE TABLE `patient_complains` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `department_id` bigint UNSIGNED NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','replied') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `responded_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `patient_complains`
--

INSERT INTO `patient_complains` (`id`, `user_id`, `department_id`, `message`, `subject`, `status`, `responded_by`, `created_at`, `updated_at`) VALUES
(5, 96, 7, 'Just a meal plan I can follow.', 'I need a personalised meal plan.', 'pending', NULL, '2025-07-28 08:57:09', '2025-07-28 08:57:09');

-- --------------------------------------------------------

--
-- Table structure for table `patient_prescriptions`
--

CREATE TABLE `patient_prescriptions` (
  `id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `plans`
--

CREATE TABLE `plans` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `plans`
--

INSERT INTO `plans` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Single', 'plan 1', '2025-04-15 11:46:44', '2025-04-15 11:46:44'),
(2, 'Partner Care', 'plan 2', '2025-04-15 11:47:38', '2025-04-15 11:47:38'),
(3, 'Family', 'plan 3', '2025-04-15 11:48:07', '2025-04-15 11:48:07');

-- --------------------------------------------------------

--
-- Table structure for table `pricings`
--

CREATE TABLE `pricings` (
  `id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `referral_codes`
--

CREATE TABLE `referral_codes` (
  `id` bigint UNSIGNED NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registrations_count` int NOT NULL DEFAULT '0',
  `clicks_count` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `referral_codes`
--

INSERT INTO `referral_codes` (`id`, `code`, `email`, `name`, `registrations_count`, `clicks_count`, `created_at`, `updated_at`) VALUES
(9, '2R9EZZJU', 'vicken408@gmail.com', 'test user', 0, 1, '2025-06-16 20:31:27', '2025-07-09 08:14:26'),
(12, 'VD4H27FL', 'nyaknoubom@gmail.com', 'Yankyvibes', 0, 4, '2025-07-09 08:28:03', '2025-07-15 14:47:04');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('0bQ9ZvyPDiOhRYfEINjx3Uv5cqi5zMvAO7qSLe4o', NULL, '110.40.186.63', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiNGVBdDVSZHk1dHZVeGlhVTg5T2RlSVRMdHpOMjh3QU5iM084d2pZdSI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MzY6Imh0dHA6Ly93d3cudGltZWxlc3NoZWFsdGhjYXJlMjQ3LmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1753776020),
('1oB5FV0MekDmiuOjkGTL1iZEcmtcPIR0ygWipxVZ', NULL, '66.220.149.115', 'meta-externalagent/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/crawler)', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiNXNEbUlNYUFpZ0E2RDNKenExcDRBazliZlpYMVNBR1JoTm9WR2toZCI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MzI6Imh0dHA6Ly90aW1lbGVzc2hlYWx0aGNhcmUyNDcuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1753779634),
('64iDv8InkgSPTAN5PoTT3G2azugZOY13Xnzqt1gq', NULL, '20.171.207.166', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiMU10NjkzdHkydnFNTjlPM0hPSVpadmg4VWZmMzlLdVFsQnFPUUdWQiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MzM6Imh0dHBzOi8vdGltZWxlc3NoZWFsdGhjYXJlMjQ3LmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1753783538),
('cKA1ABBwY2XG9ewE94hjx0SvoSVUIzxmLc1WszPi', NULL, '205.169.39.52', 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/106.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiQWNXSDhVam9MTDZDdUFyNkRUMFZwNkRFd1pPQ1o5aGJOdG9jMTVEciI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MzI6Imh0dHA6Ly90aW1lbGVzc2hlYWx0aGNhcmUyNDcuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1753785303),
('FjLHEpnun0sM0D239A1HVijAIfQiBWXGARz67CbX', NULL, '170.106.65.93', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiemY4b3dGVGVEUHFheXVwNnZSNVlBYVdaR3B5R083WEJvUFJJSERyaiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MzI6Imh0dHA6Ly90aW1lbGVzc2hlYWx0aGNhcmUyNDcuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1753779757),
('gqmksfa2U4r3vWmFkNEJgMz9SSUxLRJypjID9OOw', NULL, '69.63.184.40', 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiVThTV3IxNUdJc0NEMGU1Nnp5Q1VZS2VMc2dQVHlPMU02cHdXNjc3aiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MzI6Imh0dHA6Ly90aW1lbGVzc2hlYWx0aGNhcmUyNDcuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1753783693),
('KukQu62QBXwyoWxZKpXFwIUl789DrqLDNjC9gvRp', NULL, '172.182.213.193', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36; compatible; OAI-SearchBot/1.0; +https://openai.com/searchbot', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiU29mYnZQZGFzTGkzdko4a0lEVXFSSnRCWEU5QlZIb1pwY0NFdjBRbiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MzM6Imh0dHBzOi8vdGltZWxlc3NoZWFsdGhjYXJlMjQ3LmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1753783538),
('RKVWdVdLFqiKBRxGhogj79GUIzjtta9JUNJGD6pz', NULL, '122.51.104.231', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiS3RudHlubHRWdElzMEpXNWJMdGQzZHZpVkExWU5raUtpS0ZBcGNCYiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MzI6Imh0dHA6Ly90aW1lbGVzc2hlYWx0aGNhcmUyNDcuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1753787549),
('ZswW9mnJNJU9u3TKt8WxOyx7ZaxuDr3EQjQD4KRV', NULL, '102.90.117.155', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiYXJBa1ZlUFozejlRMVFVSWUwd1lOenBXb211dkFFMmFQamN0VnB3QiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MzM6Imh0dHBzOi8vdGltZWxlc3NoZWFsdGhjYXJlMjQ3LmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1753781918);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `surname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `otherNames` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tel` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_role` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'patient',
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `medicalConditions` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `medications` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `preferredLanguage` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `termsAccepted` tinyint(1) NOT NULL,
  `country` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `gender` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dateOfBirth` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `surname`, `otherNames`, `address`, `tel`, `email`, `user_role`, `password`, `medicalConditions`, `medications`, `username`, `preferredLanguage`, `termsAccepted`, `country`, `state`, `created_at`, `updated_at`, `gender`, `dateOfBirth`, `email_verified_at`) VALUES
(11, 'Edu', 'David', '148 Old Odukpani Road, Calabar', '07062806268', 'maildavo@yahoo.com', 'admin', '$2y$12$CzQQVG0/AKNnXpprjKeEPuIWw5JNZCTZ1TZGR9PPEZi/QwgCGUrRu', 'Nil', 'no', 'Bob', 'english', 1, 'Nigeria', 'Cross River', '2025-04-29 19:21:40', '2025-06-03 07:39:26', 'male', '1990-02-24', '2025-06-03 07:39:26'),
(13, 'Peters', 'Patient', 'Lagos', '09158722616', 'patient@timelesshealthcare247.com', 'patient', '$2y$12$lG/nHIiZpZQ6bDquQLi1Huj/xNP/rhBzSucbmtgptjERK1Fq9fqWO', NULL, 'no', 'patient', 'english', 1, 'Nigeria', 'Lagos', '2025-05-03 21:30:27', '2025-05-03 21:30:27', 'male', '2000-02-08', NULL),
(19, 'Aguwa', 'Nnamdi Peter', 'Enamel Quarters Onitsha Owerri-Road, Anambra State.', '07065642993', 'aguwa.nnamdip@yahoo.com', 'patient', '$2y$12$hBTABAZPPSNsXisnV38rK.tuWxGkE8P/Bkaac.1EYckafSSydeksW', NULL, 'no', 'Mr. Peter', 'english', 1, 'Nigeria', 'Lagos', '2025-05-24 16:26:58', '2025-05-24 16:26:58', 'male', '1990-10-01', NULL),
(20, 'Undebe', 'Williams', 'National orthopaedic hospital Enugu', '08032495669', 'williams@timelesshealthcare247.com', 'doctor', '$2y$12$Uq6R8rw9SWVhIxK3lrnzxu0io/Mu8TQVDEOshOuVaYf.Ooef4Aq16', NULL, 'yes', 'Dr Willz', 'english', 1, 'Nigeria', 'Enugu', '2025-05-26 07:15:14', '2025-06-01 20:07:53', 'male', '1990-10-02', NULL),
(21, 'Ibomi', 'Precious cletus', 'Asari eso Calabar', '08163681113', 'preciousibomi@gmail.com', 'patient', '$2y$12$YT1jjmFXiu0O1Shn1rzy4.pIFnlNIM8/P.Z2ynmVzD2t1pb.AjNyi', 'Iron deficiency anaemia', 'yes', 'Presh', 'english', 1, 'Nigeria', 'Cross River', '2025-05-26 07:44:37', '2025-05-26 07:44:37', 'female', '1998-07-17', NULL),
(22, 'Boniface', 'Gift', 'Benin', '09167871303', 'giftluv83@gmail.com', 'patient', '$2y$12$jGOWwmGA/J2LPNjjNXU8L.1gH/ikaLWlRYoPXna1SSRh1a6jciqcO', NULL, 'yes', 'Boniface', 'english', 1, 'Nigeria', 'Imo', '2025-05-26 08:14:21', '2025-05-26 08:14:21', 'female', '1997-07-17', NULL),
(23, 'Orji', 'Phinihas', 'No 4,Nnana lane,Obed Camp,Zone 7,ogbete,Enugu state,Nigeria.', '09137699299', 'phinihasorji@gmail.com', 'patient', '$2y$12$DocMerGWtxuBKzqxSy6JbuU2PaimmvBtOWgB9PgpYZMy53xfpaWnC', 'None', 'no', 'Phinihas', 'english', 1, 'Nigeria', 'Enugu', '2025-05-26 09:11:49', '2025-05-26 09:11:49', 'male', '2003-10-30', NULL),
(24, 'Johnson', 'Promise', 'Njikoka, Amamkpu, Anambra state', '08111158225', 'chiemelapromise30@gmail.com', 'patient', '$2y$12$7Bkh5jqhC.ak/zmD8rMg8ue6EE23lgESSMNIOSwT6UK5BirmIlQBq', 'None', 'no', 'Jprom', 'english', 1, 'Nigeria', 'Anambra', '2025-05-28 17:55:29', '2025-05-28 17:55:29', 'male', '1997-05-01', NULL),
(25, 'Egba', 'Fidelis Chidimma', 'Ado Ekiti', '07063734716', 'egbafidelischidimma@gmail.com', 'patient', '$2y$12$8MRyO99Ipg68jmENxpXvJu/KRiMsYYbFtR7rFWsHPB.ap6Q1AumfC', 'Headache \nWeakness \nLoss of appetite', 'no', 'Fidelis', 'english', 1, 'Nigeria', 'Ekiti', '2025-05-28 21:04:44', '2025-05-28 21:04:44', 'male', '1998-07-27', NULL),
(26, 'Egbo', 'Lilian', '13 Esistedo Street, off Navy Town Road, Alakija lagos', '08062689804', 'nkemlilian11@gmail.com', 'patient', '$2y$12$TfpfEyKvEUos/rTpHWCBXOc1zBev8Tvrw70EKRy0EEbBahUdeotAe', 'Hormonal Imbalance', 'no', 'Lynkem', 'english', 1, 'Nigeria', 'Lagos', '2025-05-28 23:33:10', '2025-05-28 23:33:10', 'female', '1982-04-30', NULL),
(27, 'Thomas', 'Patience', 'Calabar', '07030421160', 'thomaspatience738@gmail.com', 'patient', '$2y$12$4JyMlQAUVyLi4ygI55.8KeSdUWwnsqeDzii3Blux3zbVRTVWT5cVG', 'General health', 'yes', 'PeckySmart', 'english', 1, 'Nigeria', 'Cross River', '2025-05-30 09:08:54', '2025-05-30 09:08:54', 'female', '1992-09-19', NULL),
(39, 'victor', 'ubom', 'Lagos', '09073663615', 'vykturdonalty@gmail.com', 'patient', '$2y$12$.FjiqQfvqtEmkoLcDfu8sOwoHRHwWZX7axVDKzrPPksxGbnElily.', 'test', 'yes', 'ubom', 'english', 1, 'Nigeria', 'Lagos', '2025-06-01 20:11:03', '2025-06-01 20:11:03', 'male', '1970-02-08', NULL),
(48, 'Moses', 'Emediong', 'no 2 sakiru street omotoye estate mulero, Agege lagos', '08106178459', 'emediong@timelesshealthcare247.com', 'patient', '$2y$12$K.PmXCwOFBvvDemqZWzxhO9Xas1lKIv4pJx.Kh4iDvLZtwLtO2bBC', NULL, 'no', 'emediong@timelesshealthcare247.com', 'english', 1, 'Nigeria', 'Lagos', '2025-06-02 08:21:30', '2025-06-03 08:48:54', 'female', '1997-06-14', '2025-06-03 08:48:54'),
(49, 'Moses', 'Patience', 'Oron road, Uyo, Nigeria', '07088270894', 'patiencemoses124@gmail.com', 'patient', '$2y$12$rPnjntFFnxFEJUKv2F.KUehyZPnPPHq7YPih/4pb1SWj15jv/Z6Sy', 'Nil', 'no', 'patience@timelesshealthcare247.com', 'english', 1, 'Nigeria', 'Akwa Ibom', '2025-06-02 08:21:50', '2025-06-02 08:21:50', 'female', '1999-08-24', NULL),
(55, 'Onyekwere', 'Goodness Reuben', '34 Okoroagbor Street', '09134718483', 'goodnessonyekwere007@gmail.com', 'patient', '$2y$12$BXBwN96dBBXAN9y90d7nXuH9mI2slCKyNmVtJMYW6p/tn2fsJIaFS', 'Nil', 'no', 'Zinny', 'english', 1, 'Nigeria', 'Cross River', '2025-06-02 11:32:33', '2025-06-04 11:31:05', 'female', '1997-05-15', '2025-06-04 11:31:05'),
(60, 'Nwankwo', 'Uzonna', 'Geff Ozor Ave', '08143348789', 'uzonna@timelesshealthcare247.com', 'doctor', '$2y$12$zxe/eFtHn6Y1wb6a9r.oRO1Yf8sunCVZKIx6PfUyv0v2FTt8WSi0m', NULL, 'no', 'uzonna', NULL, 1, 'Nigeria', 'Enugu', '2025-06-02 12:18:10', '2025-06-03 15:47:22', 'male', '1983-01-11', '2025-06-03 15:47:22'),
(61, 'Umez', 'Glory', 'Ph', '08129612011', 'gloryumezurike98@gmail.com', 'patient', '$2y$12$p19d8TcKjLFinc.dEoZ1HuFhhZui/tw2PMso6/Y2nYNbOhp7PWjw6', 'None just incase I need clarity or an urgent need', 'no', 'Glory umez', 'english', 1, 'Nigeria', 'Rivers', '2025-06-02 14:29:05', '2025-06-02 14:29:05', 'female', '1998-04-24', NULL),
(62, 'William', 'Victor', '16 Akinpelu', '09036802727', 'victor@timelesshealthcare247.com', 'patient', '$2y$12$AOwF6GRFKI4irolDbxNv5.tuPIbt6Enn.5GS1Gu3mENruNChUMasC', NULL, 'no', 'vicken', 'english', 1, 'Nigeria', 'Lagos', '2025-06-02 18:29:35', '2025-06-03 01:38:48', 'male', '2002-05-21', '2025-06-03 01:38:48'),
(63, 'Nwali', 'Dave', '52 Yedseram Street, Maitama, Abuja.', '08039647422', 'david.cnwali@gmail.com', 'patient', '$2y$12$i1io0JVZjMljmYxF7.ZW9.BvGyfTv97.CMgAXIZpb2OHKFN4bvIUe', 'None that I know of', 'yes', 'SID', 'english', 1, 'Nigeria', 'Abuja', '2025-06-03 07:35:58', '2025-06-03 07:35:58', 'male', '1986-01-25', NULL),
(65, 'Admin', 'Admin', 'Nil', '08089606631', 'admin@timelesshealthcare247.com', 'admin', '$2y$12$v9/jdilPINNISXe4xr9Pq.QaN4qgGVSaXAYN4DcrDyeoupA52TvrC', NULL, 'no', 'admin@timelesshealthcare247.com', 'english', 1, 'Nigeria', 'Cross River', '2025-06-03 08:30:40', '2025-06-03 09:52:46', 'male', '2024-11-01', '2025-06-03 09:52:46'),
(69, 'Doc', 'Admin', 'River view', '08143349897', 'social@timelesshealthcare247.com', 'doctor', '$2y$12$f34HQup4.8Yc0p.kWxNMheuSgneXoK5IscrMb18H/2X6iIfyBzo9m', NULL, 'no', 'DocAdmin', 'english', 1, 'Nigeria', 'Lagos', '2025-06-03 23:35:15', '2025-06-03 23:49:04', 'male', '1990-02-24', '2025-06-03 23:49:04'),
(70, 'Ushie', 'Priscilla Biskila Bekongim', 'Lagos', '07064976462', 'biskilaushie@gmail.com', 'patient', '$2y$12$uU4q9FTXryVzPabldHs43e88ms7xQ6ClgUHemW0852fvmlB9l/CTO', 'None', 'no', 'Skila', 'english', 1, 'Nigeria', 'Cross River', '2025-06-04 12:40:33', '2025-06-04 12:40:33', 'female', '2000-03-30', NULL),
(71, 'patient', 'test', 'lagos', '07033686813', 'donatusubomvictor@gmail.com', 'patient', '$2y$12$MhHL6pXkoaNErITCnIhGee8jkn4DMJGPp2QxVhTNW4848lF0nl5K2', NULL, 'no', 'DonatusUbom', 'english', 1, 'Nigeria', 'Lagos', '2025-06-05 11:17:57', '2025-06-05 11:21:21', 'male', '2000-02-08', '2025-06-05 11:21:21'),
(72, 'AZUKA', 'CHIJIOKE HILARY', 'No 24 Amaechi street Emene, Enugu', '08063284628', 'azuka@timelesshealthcare247.com', 'doctor', '$2y$12$.kWbyi9xovCRKQrtUon.HOte/4nVurQMHBRZopVdEkVZhOxQH8xUK', NULL, 'no', 'Azuka', 'english', 1, 'Nigeria', 'Enugu', '2025-06-06 17:36:25', '2025-06-10 23:26:25', 'male', '1987-10-10', '2025-06-09 11:41:03'),
(73, 'Fadayini', 'Titus', 'Onike Roundabout', '07013231356', 'fiyinfadayini@gmail.com', 'patient', '$2y$12$C87dzKpWDCnFV/Sq3EFejexDN6p/LOqS415AspxMJ5EDW3n8xWqQK', NULL, 'no', 'Mad', 'english', 1, 'Nigeria', 'Lagos', '2025-06-09 08:18:15', '2025-06-09 08:19:03', 'male', '1999-03-21', '2025-06-09 08:19:03'),
(74, 'Edim', 'Edim', '19 Victor Bala Street, Life Camp', '09119113553', 'edim@timelesshealthcare247.com', 'patient', '$2y$12$kRcA6yMj.v6z3dLt8hpRceC0jSnNmcZasuG3HUlOL23ohYUokCUli', 'I want to work on my BMI/Cholesterol Level', 'yes', 'edim@timelesshealthcare247.com', NULL, 1, 'Nigeria', 'Abuja', '2025-06-09 15:21:33', '2025-06-09 15:22:01', 'male', '1998-12-06', '2025-06-09 15:22:01'),
(75, 'Mohammed', 'Mohammed', 'Federal lawcost Gombe State', '08165630882', 'swagerlomoh1155@gmail.com', 'patient', '$2y$12$wtW28F/tn2cwEjOl/0hz9.YXZpiYUevMCHd2I8tl2K64X2c/8EE8q', 'Hepatitis', 'yes', 'Kvngmnm', 'english', 1, 'Nigeria', 'Gombe', '2025-06-10 07:28:43', '2025-06-10 07:28:43', 'male', '1998-02-15', NULL),
(79, 'Mohammed', 'Ndatsu', 'Federal lawcost Gombe', '08129637775', 'mohammedndatsu1155@gmail.com', 'patient', '$2y$12$I08.czHK2.aKwbowSj36zOxOvOWjya58ku.LrCvwO1bPD0.H4ZKXO', 'Hepatitis B virus', 'yes', 'Kvngmnm01', 'english', 1, 'Nigeria', 'Gombe', '2025-06-10 11:30:40', '2025-06-10 11:31:39', 'male', '1998-02-15', '2025-06-10 11:31:39'),
(80, 'Amarachukwu', 'Juliet Nonye', 'Winners chapel behind church Awka', '08037466842', 'julietnonye11@gmail.com', 'patient', '$2y$12$cHkbtGH2dnL18fNSyVhMXONiy9ysWlfYynF695E2nG35rz3lQkUAq', 'Nothing', 'no', 'Julietnonye', 'english', 1, 'Nigeria', 'Anambra', '2025-06-12 21:46:37', '2025-06-12 21:46:37', 'female', '1986-08-12', NULL),
(81, 'Osim', 'Osim Eyam', '6 Atu Street', '08166950835', 'osim424@gmail.com', 'patient', '$2y$12$J5FkfIo4wK9XnF6thfLZPe1abhD1P1j09m7Qy803WbtYAyemFyjSO', 'Nil', 'no', 'Osim424', 'english', 1, 'Nigeria', 'Cross River', '2025-06-14 13:46:19', '2025-06-14 13:46:19', 'male', '1995-03-23', NULL),
(85, 'Ugochukwu', 'Chioma gloria', '9 chibayk avenue', '07068947165', 'gloriachioma82@gmail.com', 'patient', '$2y$12$Xd0wmm2HsrWCxFK65P19wOOUORLaADkOzfqXxEUs9/Cw8AiVbsPxe', 'Hydronephrosis', 'no', 'gloriachioma82@gmail.com', 'english', 1, 'Nigeria', 'Rivers', '2025-06-19 18:59:32', '2025-06-19 19:07:56', 'female', '1988-10-10', '2025-06-19 19:07:56'),
(86, 'Abdullahi', 'Khadijah', '33 Alimi-oke street Orile Oshodi Lagos', '09078939765', 'abdullahikhadijah52@gmail.com', 'patient', '$2y$12$Un4Yq121EAuxsALHLgtCb.ONZcCIGTzodxk4oXHq/QE1HwXfnZPAS', 'Sickle cell\nAsthma', 'yes', 'Abolore', 'english', 1, 'Nigeria', 'Lagos', '2025-06-21 19:00:51', '2025-06-21 19:00:51', 'female', '2006-02-21', NULL),
(87, 'Lazeez', 'Abiodun', 'Mubo phase 2 copper\'s lodge', '08106523552', 'adegunlehintitilayo@gmail.com', 'patient', '$2y$12$SvwoVqkfep9o00m5Y5EjxuBXrW6LXjk9YvBEsFG9deKps1cW1fPxe', 'Sickle cell', 'yes', 'Sharon', 'english', 1, 'Nigeria', 'Kwara', '2025-06-21 19:32:59', '2025-06-21 19:32:59', 'female', '2025-12-18', NULL),
(88, 'Falarungbon', '\'Tofunmi Joseph', 'Matogbun rd, Oke-Aro.', '08023629253', 'falasbukola@gmail.com', 'patient', '$2y$12$8OFByEAbR6Xe4ig6McDRxeWJQFrTY52Ybv/cy/8TitsyAEwFPtEl.', 'A young  boy living  with  Sickle cell Anemia.', 'yes', 'Tofunmi', 'english', 1, 'Nigeria', 'Ogun', '2025-06-21 22:50:28', '2025-06-24 12:10:29', 'male', '2014-10-08', '2025-06-24 12:10:29'),
(89, 'IDOKO', 'JOSHUA', 'IFEDORE AKURE ONDO STATE', '08034756102', 'godsgift6700@gmail.com', 'patient', '$2y$12$ZBeMlL.MNfk6gQs0ZtweQuD./wYGynt0oAJ1vzW.na7STtYH8ixY2', NULL, 'no', 'Giftsmart', 'english', 1, 'Nigeria', 'Ondo', '2025-06-22 21:46:53', '2025-06-22 21:46:53', 'male', '1998-11-21', NULL),
(90, 'Ayami', 'Elijah Eno', 'Obubra, Mile 1', '08135679587', 'princeayami@gmail.com', 'patient', '$2y$12$TRbHbGBlNtfimUix5wFFS.KGKbByQG/6tdEM83.hJ8eP9t8iH1VHS', NULL, 'no', 'Elijah', 'english', 1, 'Nigeria', 'Cross River', '2025-06-24 20:52:06', '2025-06-24 20:58:32', 'male', '1994-09-28', '2025-06-24 20:58:32'),
(91, 'Edu', 'Patient', 'Rivers', '08089606630', 'edu@timelesshealthcare247.com', 'patient', '$2y$12$LPPvLMA8GPvQPcTiHu3oR.43UP5mYMQuGITHC2QCZKCiQF8KgWrAC', 'Ulcer', 'yes', 'edu@timelesshealthcare247.com', 'english', 1, 'Nigeria', 'Rivers', '2025-06-25 16:32:54', '2025-06-25 16:33:33', 'male', '1990-02-04', '2025-06-25 16:33:33'),
(92, 'Isaac', 'Mfreke', 'Apo waru, Abuja', '08131312638', 'mfrekeabasiisaac39@gmail.com', 'patient', '$2y$12$o85pEUJgE29KlrPyoEKKAO8OC4ywmHAMr.xmD/0BAH3TdNlk.jT9O', 'Cough', 'yes', 'Dontaly', 'english', 1, 'Nigeria', 'Abuja', '2025-07-06 13:45:08', '2025-07-06 13:45:44', 'male', '1995-09-15', '2025-07-06 13:45:44'),
(93, 'Ajibola', 'Gbemisola', 'No 30', '08149330878', 'gbemisolaajibola2@gmail.com', 'patient', '$2y$12$Ultk1/tJq6yUUXkXdc9Q8u.dKTABkzcyl1l.17U8w7L8uaBEGz8Qu', 'No', 'no', 'Steph93', 'english', 1, 'Nigeria', 'Oyo', '2025-07-14 21:17:02', '2025-07-14 21:17:39', 'female', '1993-11-08', '2025-07-14 21:17:39'),
(94, 'Peter', 'imoh victor', '12 jeida village, kuje, abuja', '09072193891', 'peterimoh20@gmail.com', 'patient', '$2y$12$69v2CWVr949DgPUTyRItJu5sSLPm3PWAA/6TX9VTihJliY9wvZEzi', 'cervical spondylosis', 'no', 'imoh', 'english', 1, 'Nigeria', 'Akwa Ibom', '2025-07-22 21:04:25', '2025-07-22 21:04:52', 'male', '1995-07-10', '2025-07-22 21:04:52'),
(95, 'Lawrence', 'Walter', 'PortHarcourt', '08112852942', 'lawrencewalter092@gmail.com', 'patient', '$2y$12$Tf8Ed.QNq8nVf4oMr0BhbeCD/rPb0XPiuCZgcF1cE/NOEVmChI/ee', 'Malaria', 'yes', 'Law01', 'english', 1, 'Nigeria', 'Edo', '2025-07-27 00:42:28', '2025-07-27 00:42:59', 'male', '2025-07-27', '2025-07-27 00:42:59'),
(96, 'Victor', 'Donatus', '13 Sangoremi Street, Isheri, Magodo', '08137790780', 'donatusvictor76@gmail.com', 'patient', '$2y$12$WXEm/0lllBMrNL6VU8Z25.eetmWfN.rnysbS2U2cAIHCQlIda6Nt.', NULL, 'no', 'Donatus', 'english', 1, 'Nigeria', 'Lagos', '2025-07-28 08:52:35', '2025-07-28 08:53:08', 'male', '1995-02-08', '2025-07-28 08:53:08');

-- --------------------------------------------------------

--
-- Table structure for table `user_plans`
--

CREATE TABLE `user_plans` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `plans_id` bigint UNSIGNED NOT NULL,
  `duration` int NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_plans`
--

INSERT INTO `user_plans` (`id`, `user_id`, `plans_id`, `duration`, `start_date`, `end_date`, `status`, `created_at`, `updated_at`) VALUES
(2, 11, 1, 1, '2025-04-29', '2025-05-29', 0, '2025-04-29 19:26:52', '2025-05-30 09:08:58'),
(12, 55, 1, 1, '2025-06-02', '2025-07-02', 0, '2025-06-02 11:44:07', '2025-07-21 11:51:55'),
(13, 62, 3, 1, '2025-07-21', '2025-09-21', 1, '2025-06-02 11:44:07', '2025-07-21 10:16:34'),
(15, 13, 1, 4, '2025-06-28', '2025-10-28', 1, '2025-06-28 23:36:00', '2025-06-28 23:36:00'),
(16, 93, 1, 3, '2025-07-14', '2025-10-14', 1, '2025-07-14 21:20:27', '2025-07-14 21:20:27'),
(17, 91, 1, 1, '2025-07-21', '2025-08-21', 1, '2025-07-21 09:31:01', '2025-07-21 09:37:38'),
(18, 96, 1, 1, '2025-07-28', '2025-08-28', 1, '2025-07-28 08:54:54', '2025-07-28 08:54:54');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `about_us`
--
ALTER TABLE `about_us`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `appointments_user_id_foreign` (`user_id`),
  ADD KEY `appointments_department_id_foreign` (`department_id`);

--
-- Indexes for table `appointment__department__doctors`
--
ALTER TABLE `appointment__department__doctors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `appointment__department__doctors_department_id_foreign` (`department_id`),
  ADD KEY `appointment__department__doctors_doctor_id_foreign` (`doctor_id`),
  ADD KEY `appointment__department__doctors_appointment_id_foreign` (`appointment_id`);

--
-- Indexes for table `billing_cycles`
--
ALTER TABLE `billing_cycles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `billing_cycles_plans_id_foreign` (`plans_id`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `contact_us`
--
ALTER TABLE `contact_us`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `departments_name_unique` (`name`);

--
-- Indexes for table `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `doctor_details`
--
ALTER TABLE `doctor_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `doctor_details_user_id_foreign` (`user_id`);

--
-- Indexes for table `doctor_recommendations`
--
ALTER TABLE `doctor_recommendations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `doctor_recommendations_patient_id_foreign` (`patient_id`),
  ADD KEY `doctor_recommendations_doctor_id_foreign` (`doctor_id`),
  ADD KEY `doctor_recommendations_patient_complain_id_foreign` (`patient_complain_id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `features`
--
ALTER TABLE `features`
  ADD PRIMARY KEY (`id`),
  ADD KEY `features_plans_id_foreign` (`plans_id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoices_invoice_number_unique` (`invoice_number`),
  ADD KEY `invoices_user_id_foreign` (`user_id`),
  ADD KEY `invoices_plan_id_foreign` (`plan_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `medical_histories`
--
ALTER TABLE `medical_histories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `medical_histories_user_id_foreign` (`user_id`);

--
-- Indexes for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `medical_records_user_id_foreign` (`user_id`),
  ADD KEY `medical_records_doctor_id_foreign` (`doctor_id`);

--
-- Indexes for table `meetings`
--
ALTER TABLE `meetings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `meetings_room_name_unique` (`room_name`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `patient_complains`
--
ALTER TABLE `patient_complains`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_complains_user_id_foreign` (`user_id`),
  ADD KEY `patient_complains_department_id_foreign` (`department_id`),
  ADD KEY `patient_complains_responded_by_foreign` (`responded_by`);

--
-- Indexes for table `patient_prescriptions`
--
ALTER TABLE `patient_prescriptions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `plans`
--
ALTER TABLE `plans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pricings`
--
ALTER TABLE `pricings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `referral_codes`
--
ALTER TABLE `referral_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `referral_codes_code_unique` (`code`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_tel_unique` (`tel`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- Indexes for table `user_plans`
--
ALTER TABLE `user_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_plans_user_id_foreign` (`user_id`),
  ADD KEY `user_plans_plans_id_foreign` (`plans_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `about_us`
--
ALTER TABLE `about_us`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `appointment__department__doctors`
--
ALTER TABLE `appointment__department__doctors`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `billing_cycles`
--
ALTER TABLE `billing_cycles`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `contact_us`
--
ALTER TABLE `contact_us`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `doctor_details`
--
ALTER TABLE `doctor_details`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `doctor_recommendations`
--
ALTER TABLE `doctor_recommendations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `features`
--
ALTER TABLE `features`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `medical_histories`
--
ALTER TABLE `medical_histories`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `medical_records`
--
ALTER TABLE `medical_records`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `meetings`
--
ALTER TABLE `meetings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_complains`
--
ALTER TABLE `patient_complains`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `patient_prescriptions`
--
ALTER TABLE `patient_prescriptions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plans`
--
ALTER TABLE `plans`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `pricings`
--
ALTER TABLE `pricings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `referral_codes`
--
ALTER TABLE `referral_codes`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

--
-- AUTO_INCREMENT for table `user_plans`
--
ALTER TABLE `user_plans`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `appointment__department__doctors`
--
ALTER TABLE `appointment__department__doctors`
  ADD CONSTRAINT `appointment__department__doctors_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointment__department__doctors_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointment__department__doctors_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `doctor_details` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `billing_cycles`
--
ALTER TABLE `billing_cycles`
  ADD CONSTRAINT `billing_cycles_plans_id_foreign` FOREIGN KEY (`plans_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `doctor_details`
--
ALTER TABLE `doctor_details`
  ADD CONSTRAINT `doctor_details_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `doctor_recommendations`
--
ALTER TABLE `doctor_recommendations`
  ADD CONSTRAINT `doctor_recommendations_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `doctor_details` (`id`),
  ADD CONSTRAINT `doctor_recommendations_patient_complain_id_foreign` FOREIGN KEY (`patient_complain_id`) REFERENCES `patient_complains` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `doctor_recommendations_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `features`
--
ALTER TABLE `features`
  ADD CONSTRAINT `features_plans_id_foreign` FOREIGN KEY (`plans_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoices_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `medical_histories`
--
ALTER TABLE `medical_histories`
  ADD CONSTRAINT `medical_histories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD CONSTRAINT `medical_records_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `doctor_details` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `medical_records_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `patient_complains`
--
ALTER TABLE `patient_complains`
  ADD CONSTRAINT `patient_complains_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `patient_complains_responded_by_foreign` FOREIGN KEY (`responded_by`) REFERENCES `doctor_details` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `patient_complains_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_plans`
--
ALTER TABLE `user_plans`
  ADD CONSTRAINT `user_plans_plans_id_foreign` FOREIGN KEY (`plans_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_plans_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
