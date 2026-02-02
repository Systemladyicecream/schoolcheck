-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql100.byetcluster.com
-- Generation Time: Feb 01, 2026 at 10:15 PM
-- Server version: 11.4.9-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `b6_40957347_schoolcheck`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_years`
--

CREATE TABLE `academic_years` (
  `year_id` int(11) NOT NULL,
  `year_name` varchar(10) NOT NULL,
  `term` varchar(10) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `inspection_count` int(11) DEFAULT 10
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `academic_years`
--

INSERT INTO `academic_years` (`year_id`, `year_name`, `term`, `is_active`, `created_at`, `inspection_count`) VALUES
(1, '2568', '2', 1, '2026-01-03 08:07:05', 20),
(3, '2569', '1', 0, '2026-01-19 08:09:54', 20);

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `class_id` int(11) NOT NULL,
  `level_name` varchar(20) NOT NULL,
  `room_number` varchar(10) NOT NULL,
  `academic_year_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`class_id`, `level_name`, `room_number`, `academic_year_id`) VALUES
(1, '1', '/1', 1),
(2, '4', '/1', 1),
(3, '2', '/1', 1),
(4, '3', '/1', 1),
(5, '5', '/1', 1),
(6, '6', '/1', 1);

-- --------------------------------------------------------

--
-- Table structure for table `inspections`
--

CREATE TABLE `inspections` (
  `inspection_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `inspector_id` int(11) NOT NULL,
  `inspection_date` datetime DEFAULT current_timestamp(),
  `result_status` enum('pass','fail') NOT NULL,
  `note` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inspections`
--

INSERT INTO `inspections` (`inspection_id`, `student_id`, `inspector_id`, `inspection_date`, `result_status`, `note`, `updated_by`, `updated_at`) VALUES
(4, 3, 1, '2026-01-04 10:30:08', 'pass', NULL, NULL, '2026-01-04 17:44:26'),
(11, 3, 3, '2026-01-11 00:00:00', 'pass', '', NULL, NULL),
(12, 4, 3, '2026-01-11 00:00:00', 'pass', '', NULL, NULL),
(15, 3, 3, '2026-01-12 00:00:00', 'pass', '', NULL, NULL),
(16, 4, 3, '2026-01-12 00:00:00', 'fail', '', NULL, NULL),
(28, 3, 3, '2026-01-18 00:00:00', 'pass', '', NULL, NULL),
(29, 4, 3, '2026-01-18 00:00:00', 'pass', '', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `inspection_rules`
--

CREATE TABLE `inspection_rules` (
  `rule_id` int(11) NOT NULL,
  `rule_category` enum('hair','uniform','shoes','nails','other') NOT NULL,
  `rule_name` varchar(100) NOT NULL,
  `score_deduction` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inspection_rules`
--

INSERT INTO `inspection_rules` (`rule_id`, `rule_category`, `rule_name`, `score_deduction`, `is_active`) VALUES
(1, 'hair', 'ผมยาวเกินกำหนด', 5, 1),
(2, 'hair', 'ย้อมสีผม', 10, 1),
(3, 'uniform', 'เสื้อหลุดนอกกางเกง', 5, 1),
(4, 'uniform', 'ไม่ปักชื่อ', 5, 1),
(5, 'shoes', 'สวมรองเท้าผิดระเบียบ', 5, 1),
(6, 'nails', 'เล็บยาว', 2, 1),
(8, 'hair', 'ถุงเท้าไม่ถูกระเบียบ', 3, 1),
(9, 'hair', 'สีผมไม่ถูกระเบียบ', 5, 1);

-- --------------------------------------------------------

--
-- Table structure for table `inspection_violations`
--

CREATE TABLE `inspection_violations` (
  `violation_id` int(11) NOT NULL,
  `inspection_id` int(11) NOT NULL,
  `rule_id` int(11) NOT NULL,
  `violation_detail` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inspection_violations`
--

INSERT INTO `inspection_violations` (`violation_id`, `inspection_id`, `rule_id`, `violation_detail`) VALUES
(2, 16, 1, NULL),
(3, 16, 3, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `login_logs`
--

CREATE TABLE `login_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `login_time` datetime NOT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_logs`
--

INSERT INTO `login_logs` (`log_id`, `user_id`, `login_time`, `ip_address`, `user_agent`) VALUES
(1, 1, '2026-01-19 14:00:12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36'),
(2, 5, '2026-01-19 14:23:43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36'),
(3, 3, '2026-01-19 14:24:05', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36'),
(4, 1, '2026-01-19 14:29:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36'),
(5, 3, '2026-01-19 14:39:06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36'),
(6, 3, '2026-01-19 14:41:19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36'),
(7, 1, '2026-01-19 14:47:34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36'),
(8, 4, '2026-01-19 14:53:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36'),
(9, 1, '2026-01-19 14:54:20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36'),
(10, 3, '2026-01-19 15:00:36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36'),
(11, 3, '2026-01-19 15:03:19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36'),
(12, 1, '2026-01-19 15:06:41', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36'),
(13, 1, '2026-01-21 07:30:46', '104.28.246.148', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36'),
(14, 1, '2026-01-21 07:34:03', '184.22.12.25', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 [FBAN/FBIOS;FBAV/544.0.0.20.406;FBBV/860355073;FBDV/iPhone14,5;FBMD/iPhone;FBSN/iOS;FBSV/26.2;FBSS/3;FBCR/;FBID/phone;FBLC/th_TH;FBOP/80]'),
(15, 1, '2026-01-21 18:11:10', '223.24.197.151', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 [FBAN/FBIOS;FBAV/544.0.0.20.406;FBBV/860355073;FBDV/iPhone14,5;FBMD/iPhone;FBSN/iOS;FBSV/26.2;FBSS/3;FBCR/;FBID/phone;FBLC/th_TH;FBOP/80]'),
(16, 1, '2026-01-25 03:20:39', '104.28.246.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36'),
(17, 1, '2026-01-25 03:35:40', '104.28.246.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36'),
(18, 3, '2026-01-25 03:37:36', '104.28.246.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36'),
(19, 1, '2026-01-25 03:45:36', '104.28.246.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36'),
(20, 1, '2026-01-25 03:47:02', '104.28.246.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36'),
(21, 1, '2026-01-25 03:49:51', '184.22.15.127', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Mobile/15E148 Safari/604.1'),
(22, 4, '2026-01-25 04:17:23', '104.28.246.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36'),
(23, 1, '2026-01-25 04:17:40', '104.28.246.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36'),
(24, 1, '2026-01-25 22:08:15', '49.237.72.168', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 [FBAN/FBIOS;FBAV/545.0.0.12.108;FBBV/865730320;FBDV/iPhone14,5;FBMD/iPhone;FBSN/iOS;FBSV/26.2;FBSS/3;FBCR/;FBID/phone;FBLC/th_TH;FBOP/80]'),
(25, 1, '2026-01-28 21:37:56', '184.22.53.108', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Mobile/15E148 Safari/604.1'),
(26, 1, '2026-01-28 21:39:20', '184.22.53.108', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `student_code` varchar(20) NOT NULL,
  `prefix` varchar(20) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `current_class_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `student_code`, `prefix`, `first_name`, `last_name`, `gender`, `current_class_id`) VALUES
(3, '67003', 'ด.ช.', 'มานะ', 'อดทน', NULL, 1),
(4, '67004', 'นาย', 'น้ำใจ', 'ใจจริง', NULL, 1),
(10, '67005', 'นาย', 'กิตติศักดิ์', 'วงศ์สวัสดิ์', NULL, 1),
(11, '67002', 'นาย', 'ณัฐพงษ์', 'ใจมั่น', NULL, 1),
(12, '67006', 'นาย', 'ธนภัทร', 'เจริญสุข', NULL, 3),
(13, '67007', 'นาย', 'จิรวัฒน์', ' สุขประเสริฐ', NULL, 3),
(14, '67008', 'นาย', 'พีรพล', 'แก้วมณี', NULL, 3),
(15, '67009', 'นาย', 'วรวุฒิ', ' ศรีสมบูรณ์', NULL, 3),
(16, '67010', 'นาย', 'อภิสิทธิ์', ' รักชาติ', NULL, 3),
(17, '67011', 'นาย', 'ธีรเทพ', 'พงษ์พานิช', NULL, 4),
(18, '67012', 'นาย', 'ศุภชัย', 'มีทรัพย์', NULL, 4),
(19, '67013', 'นาย', 'เอกภพ', 'พลอยงาม', NULL, 4),
(20, '67014', 'นาย', 'ชานนท์', 'รุ่งเรือง', NULL, 4),
(21, '67015', 'นาย', 'ปวีณ', 'วิเศษศรี', NULL, 4),
(22, '67016', 'นาย', 'นนท์', 'ราชาวี', NULL, 2),
(23, '67017', 'นาย', 'พีรเดช', 'ยาใจ', NULL, 2),
(24, '67018', 'นางสาว', 'อัญชลี', 'น้อมใจ', NULL, 2),
(25, '67019', 'นางสาว', 'กนกพร', 'อ่อนใจ', NULL, 2),
(26, '67020', 'นางสาว', 'นันทนา', 'ศรีใจ', NULL, 2),
(27, '67021', 'นาย', 'ทันที', 'ใจดี', NULL, 5),
(28, '67022', 'นาย', 'สมนัส', 'วาจาดี', NULL, 5),
(29, '67023', 'นางสาว', 'ยาบาลี', 'หงส์ษา', NULL, 5),
(30, '67024', 'นางสาว', 'รวี', 'สมใจ', NULL, 5),
(31, '67025', 'นางสาว', 'ราวดี', 'เดชคุณ', NULL, 5),
(32, '67026', 'นาย', 'ธนวัฒน์', 'นามี', NULL, 6),
(33, '67027', 'นาย', 'สมผา', 'มีนา', NULL, 6),
(35, '67028', 'นาย', 'ศร', 'สมศรี', NULL, 6),
(36, '67029', 'นางสาว', 'วิภานี', 'ชาละวัน', NULL, 6),
(37, '67030', 'นางสาว', 'บุรีนินท์', 'ยารี', NULL, 6);

-- --------------------------------------------------------

--
-- Table structure for table `teacher_class_assignments`
--

CREATE TABLE `teacher_class_assignments` (
  `user_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `teacher_class_assignments`
--

INSERT INTO `teacher_class_assignments` (`user_id`, `class_id`) VALUES
(3, 1),
(5, 2),
(7, 3),
(6, 4),
(8, 5),
(9, 6);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','teacher','director','deputy_director','executive') DEFAULT 'teacher',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password_hash`, `full_name`, `role`, `last_login`, `created_at`, `status`) VALUES
(1, 'Admin', 'Admin1234', 'วรเมธ คำตั้งหน้า', 'admin', '2026-01-28 21:39:20', '2026-01-03 08:07:05', 'active'),
(3, 'Woramet', 'Woramet1234', 'วรเมธ คำตั้งหน้า', 'teacher', '2026-01-25 03:37:36', '2026-01-04 12:59:46', 'active'),
(4, 'director', '1234', 'ผู้อำนวยการโรงเรียน', 'director', '2026-01-25 04:17:23', '2026-01-11 13:36:50', 'active'),
(5, 'Pichaya', 'Pichaya12345', 'นายพิชญะ ยาเครือ', 'teacher', '2026-01-19 14:23:43', '2026-01-11 16:02:11', 'active'),
(6, 'Phumiphat', '$2y$10$.EcdrtCC3K/R850B18JDNu2hvXZ0arbOHKInVl//T6./32xttXi2u', 'นายภูมิพัฒน์ สุวรรณพรม', 'teacher', '2026-01-19 13:37:58', '2026-01-19 06:27:23', 'active'),
(7, 'Padiphat', '$2y$10$o6PfKCYoDVxwvmGLxaiYIOMo/OcrZ8rRJTi.FOlYQleqhNu6J4hlW', 'ปดิพัทธ์ จงเจริญ', 'teacher', '2026-01-19 13:46:20', '2026-01-19 06:28:45', 'active'),
(8, 'Yanin', '$2y$10$BiYuwXKqtrBpDwxhr1Y6veA8xbG10VPWZ3o3bWceMsdhWxNJv4yUO', 'ญานิน ยาเครือ', 'teacher', '2026-01-19 13:36:42', '2026-01-19 06:29:44', 'active'),
(9, 'Sikikanya', '$2y$10$6f0DAi6KyEp9OrMdz4P6IOfcGhVn7RvXOoH7X55fxT8B/X4L6FX6O', 'สิริกัญญา อ้มพรม', 'teacher', '2026-01-19 13:36:20', '2026-01-19 06:31:11', 'active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_years`
--
ALTER TABLE `academic_years`
  ADD PRIMARY KEY (`year_id`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`class_id`),
  ADD KEY `academic_year_id` (`academic_year_id`);

--
-- Indexes for table `inspections`
--
ALTER TABLE `inspections`
  ADD PRIMARY KEY (`inspection_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `inspector_id` (`inspector_id`);

--
-- Indexes for table `inspection_rules`
--
ALTER TABLE `inspection_rules`
  ADD PRIMARY KEY (`rule_id`);

--
-- Indexes for table `inspection_violations`
--
ALTER TABLE `inspection_violations`
  ADD PRIMARY KEY (`violation_id`),
  ADD KEY `inspection_id` (`inspection_id`),
  ADD KEY `rule_id` (`rule_id`);

--
-- Indexes for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `student_code` (`student_code`),
  ADD KEY `current_class_id` (`current_class_id`);

--
-- Indexes for table `teacher_class_assignments`
--
ALTER TABLE `teacher_class_assignments`
  ADD PRIMARY KEY (`user_id`,`class_id`),
  ADD KEY `class_id` (`class_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_years`
--
ALTER TABLE `academic_years`
  MODIFY `year_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `class_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `inspections`
--
ALTER TABLE `inspections`
  MODIFY `inspection_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `inspection_rules`
--
ALTER TABLE `inspection_rules`
  MODIFY `rule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `inspection_violations`
--
ALTER TABLE `inspection_violations`
  MODIFY `violation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`year_id`);

--
-- Constraints for table `inspections`
--
ALTER TABLE `inspections`
  ADD CONSTRAINT `inspections_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `inspections_ibfk_2` FOREIGN KEY (`inspector_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `inspection_violations`
--
ALTER TABLE `inspection_violations`
  ADD CONSTRAINT `inspection_violations_ibfk_1` FOREIGN KEY (`inspection_id`) REFERENCES `inspections` (`inspection_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inspection_violations_ibfk_2` FOREIGN KEY (`rule_id`) REFERENCES `inspection_rules` (`rule_id`);

--
-- Constraints for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD CONSTRAINT `login_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`current_class_id`) REFERENCES `classes` (`class_id`);

--
-- Constraints for table `teacher_class_assignments`
--
ALTER TABLE `teacher_class_assignments`
  ADD CONSTRAINT `teacher_class_assignments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `teacher_class_assignments_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
