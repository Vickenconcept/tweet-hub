-- Appointments
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

-- Appointment Department Doctors
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

-- Billing Cycles
INSERT INTO `billing_cycles` (`id`, `plans_id`, `duration`, `price`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1800.00, '2025-05-01 18:40:00', '2025-05-01 18:40:00'),
(2, 2, 1, 2250.00, '2025-04-15 11:49:12', '2025-04-15 11:49:12'),
(3, 3, 1, 3150.00, '2025-04-15 11:49:46', '2025-04-15 11:49:46');

-- Departments
INSERT INTO `departments` (`id`, `name`, `created_at`, `updated_at`) VALUES
(4, 'Dieticians', '2025-05-01 22:37:47', '2025-05-01 22:37:47'),
(5, 'Fitness & Gymnastics', '2025-05-01 22:38:08', '2025-05-01 22:38:08'),
(6, 'Obstetricians & Gynecologists', '2025-05-01 22:38:46', '2025-05-01 22:38:46'),
(7, 'Doctor', '2025-05-24 18:32:09', '2025-05-24 18:32:09'),
(8, 'Nurse', '2025-06-14 11:08:59', '2025-06-14 11:08:59');

-- Doctors
INSERT INTO `doctors` (`id`, `created_at`, `updated_at`) VALUES
(1, '2025-04-17 11:06:05', '2025-04-17 11:06:05');

-- Doctor Details
INSERT INTO `doctor_details` (`id`, `user_id`, `age`, `qualification`, `fee`, `department`, `active`, `created_at`, `updated_at`) VALUES
(10, 20, 35, 'Doctor', 0.00, '7', 1, '2025-06-01 20:07:53', '2025-06-01 20:07:53'),
(12, 60, 42, 'Doctor', 0.00, '7', 1, '2025-06-02 12:19:39', '2025-06-02 12:19:39'),
(14, 69, 30, 'Doctor', 0.00, '7', 1, '2025-06-03 23:36:22', '2025-06-03 23:36:22'),
(15, 72, 40, 'Doctor', 0.00, '7', 1, '2025-06-10 23:26:25', '2025-06-10 23:26:25');

-- Features
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

-- Medical Records
INSERT INTO `medical_records` (`id`, `user_id`, `doctor_id`, `diagnosis`, `medications`, `test_result`, `test_image`, `extra_notes`, `conducted_on`, `month`, `created_at`, `updated_at`) VALUES
(10, 79, 12, '1)Dyspepsia \n2) viral hepatitis ( B)\n3) MYALGIA', 'caps Omeprazole 20 mg twice a day for 10 days ( 30 minutes before good and drug)\n\nTabs athrotec one twice a day for a week ( after meals)\n\nTabs methocarbamol 1g twice a day for a week .\n\nLifestyle modicum and advice .', 'Nil', NULL, 'Has been tested for hep B , and on tenofovir and livoln forte prior consultation', '2025-06-11', 'June', '2025-06-11 15:36:39', '2025-06-11 15:36:39');

-- Patient Complains
INSERT INTO `patient_complains` (`id`, `user_id`, `department_id`, `message`, `subject`, `status`, `responded_by`, `created_at`, `updated_at`) VALUES
(5, 96, 7, 'Just a meal plan I can follow.', 'I need a personalised meal plan.', 'pending', NULL, '2025-07-28 08:57:09', '2025-07-28 08:57:09');

-- Plans
INSERT INTO `plans` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Single', 'plan 1', '2025-04-15 11:46:44', '2025-04-15 11:46:44'),
(2, 'Partner Care', 'plan 2', '2025-04-15 11:47:38', '2025-04-15 11:47:38'),
(3, 'Family', 'plan 3', '2025-04-15 11:48:07', '2025-04-15 11:48:07');

-- User Plans
INSERT INTO `user_plans` (`id`, `user_id`, `plans_id`, `duration`, `start_date`, `end_date`, `status`, `created_at`, `updated_at`) VALUES
(2, 11, 1, 1, '2025-04-29', '2025-05-29', 0, '2025-04-29 19:26:52', '2025-05-30 09:08:58'),
(12, 55, 1, 1, '2025-06-02', '2025-07-02', 0, '2025-06-02 11:44:07', '2025-07-21 11:51:55'),
(13, 62, 3, 1, '2025-07-21', '2025-09-21', 1, '2025-06-02 11:44:07', '2025-07-21 10:16:34'),
(15, 13, 1, 4, '2025-06-28', '2025-10-28', 1, '2025-06-28 23:36:00', '2025-06-28 23:36:00'),
(16, 93, 1, 3, '2025-07-14', '2025-10-14', 1, '2025-07-14 21:20:27', '2025-07-14 21:20:27'),
(17, 91, 1, 1, '2025-07-21', '2025-08-21', 1, '2025-07-21 09:31:01', '2025-07-21 09:37:38'),
(18, 96, 1, 1, '2025-07-28', '2025-08-28', 1, '2025-07-28 08:54:54', '2025-07-28 08:54:54'); 