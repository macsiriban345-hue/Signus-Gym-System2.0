-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 11, 2025 at 04:40 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `signus_gym`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `CheckInUser` (IN `user_id` INT)   BEGIN
    INSERT INTO attendance (user_id, check_in) VALUES (user_id, NOW());
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `CheckOutUser` (IN `user_id` INT)   BEGIN
    UPDATE attendance 
    SET check_out = NOW(),
        duration_minutes = TIMESTAMPDIFF(MINUTE, check_in, NOW())
    WHERE user_id = user_id 
    AND check_out IS NULL 
    ORDER BY check_in DESC 
    LIMIT 1;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `CompleteAccountSetup` (IN `user_token` VARCHAR(255), IN `user_username` VARCHAR(50), IN `user_password` VARCHAR(255))   BEGIN
    DECLARE token_user_id INT;
    DECLARE token_expires TIMESTAMP;
    
    -- Check if token is valid
    SELECT user_id, expires_at INTO token_user_id, token_expires
    FROM account_setup_tokens 
    WHERE token = user_token 
    AND used = FALSE 
    AND expires_at > NOW();
    
    IF token_user_id IS NOT NULL THEN
        -- Update user with credentials
        UPDATE users 
        SET username = user_username,
            password_hash = user_password,
            status = 'active',
            setup_token = NULL,
            token_expires_at = NULL
        WHERE id = token_user_id;
        
        -- Mark token as used
        UPDATE account_setup_tokens 
        SET used = TRUE 
        WHERE token = user_token;
        
        SELECT 1 as success, 'Account setup completed successfully!' as message;
    ELSE
        SELECT 0 as success, 'Invalid or expired setup token.' as message;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `ConfirmPayment` (IN `payment_id` INT, IN `admin_id` INT)   BEGIN
    DECLARE user_id INT;
    DECLARE membership_id INT;
    DECLARE setup_token VARCHAR(255);
    
    -- Get user and membership IDs
    SELECT p.user_id, p.membership_id INTO user_id, membership_id 
    FROM payments p WHERE p.id = payment_id;
    
    -- Generate setup token
    SET setup_token = SHA2(CONCAT(NOW(), RAND()), 256);
    
    -- Update payment status
    UPDATE payments 
    SET status = 'confirmed',
        confirmed_by = admin_id,
        confirmed_at = NOW()
    WHERE id = payment_id;
    
    -- Update membership payment status
    UPDATE memberships 
    SET payment_status = 'confirmed',
        payment_date = NOW()
    WHERE id = membership_id;
    
    -- Set user to pending_setup and store token
    UPDATE users 
    SET status = 'pending_setup',
        setup_token = setup_token,
        token_expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY)
    WHERE id = user_id;
    
    -- Store token in account_setup_tokens table
    INSERT INTO account_setup_tokens (user_id, token, expires_at)
    VALUES (user_id, setup_token, DATE_ADD(NOW(), INTERVAL 7 DAY));
    
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `MarkMessageAsRead` (IN `message_id` INT, IN `user_id` INT)   BEGIN
    UPDATE message_status 
    SET is_read = 1, 
        read_at = NOW() 
    WHERE message_id = message_id 
    AND user_id = user_id;
    
    IF ROW_COUNT() = 0 THEN
        INSERT INTO message_status (message_id, user_id, is_read, read_at) 
        VALUES (message_id, user_id, 1, NOW());
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `SendMessage` (IN `sender_id` INT, IN `receiver_id` INT, IN `message_subject` VARCHAR(255), IN `message_text` TEXT)   BEGIN
    DECLARE new_message_id INT;
    
    -- Insert the message
    INSERT INTO messages (sender_id, receiver_id, subject, message) 
    VALUES (sender_id, receiver_id, message_subject, message_text);
    
    -- Get the new message ID
    SET new_message_id = LAST_INSERT_ID();
    
    -- Create message status record
    INSERT INTO message_status (message_id, user_id, is_read) 
    VALUES (new_message_id, receiver_id, 0);
    
    SELECT new_message_id as message_id;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `account_setup_tokens`
--

CREATE TABLE `account_setup_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `account_setup_tokens`
--
DELIMITER $$
CREATE TRIGGER `cleanup_expired_tokens` BEFORE INSERT ON `account_setup_tokens` FOR EACH ROW BEGIN
    -- Delete expired tokens
    DELETE FROM account_setup_tokens WHERE expires_at < NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `active_members`
-- (See below for the actual view)
--
CREATE TABLE `active_members` (
`id` int(11)
,`first_name` varchar(50)
,`last_name` varchar(50)
,`email` varchar(100)
,`contact_number` varchar(20)
,`user_type` enum('admin','trainer','gymrat')
,`status` enum('pending','active','suspended','pending_setup')
,`membership_type` enum('walk-in','membership')
,`amount` decimal(10,2)
,`expiration_date` date
,`payment_status` enum('pending','confirmed','failed')
);

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `is_published` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `admin_id`, `title`, `content`, `is_published`, `created_at`, `updated_at`) VALUES
(1, 1, 'GYM SYSTEM UPGRADE COMPLETE', 'We are excited to announce the successful implementation of our new gym monitoring system. Members can now track their progress, schedule coaching sessions, and manage their memberships through our digital platform. Our trainers have access to enhanced client management tools, and administrators can efficiently monitor gym operations.', 1, '2025-10-31 07:33:46', '2025-10-31 07:33:46'),
(2, 1, 'PREMIUM EQUIPMENT ARRIVAL', 'We have invested in state-of-the-art fitness equipment to enhance your training experience. New additions include advanced functional training stations, Olympic lifting platforms, and recovery technology. Our certified trainers will provide complimentary orientation sessions throughout the week.', 1, '2025-10-29 07:33:46', '2025-10-31 07:33:46'),
(3, 1, 'Q1 FITNESS CHALLENGE ANNOUNCEMENT', 'Prepare for our quarterly transformation challenge starting January 1st. This 12-week program includes personalized coaching, nutrition guidance, and progress assessments. Members who achieve their goals will receive exclusive rewards and recognition. Registration opens December 20th.', 1, '2025-10-26 07:33:46', '2025-10-31 07:33:46'),
(4, 1, 'HOLIDAY SCHEDULE UPDATE', 'Please note our updated operating hours during the holiday season. We will be closing early on December 24th and 31st, and will be closed on December 25th and January 1st. Regular 24/7 access will resume on January 2nd.', 1, '2025-10-30 07:33:46', '2025-10-31 07:33:46');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `check_in` datetime DEFAULT current_timestamp(),
  `check_out` datetime DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `user_id`, `check_in`, `check_out`, `duration_minutes`, `created_at`) VALUES
(1, 5, '2023-12-15 06:30:00', '2023-12-15 08:15:00', 105, '2025-10-31 07:33:46'),
(2, 6, '2023-12-15 07:00:00', '2023-12-15 08:45:00', 105, '2025-10-31 07:33:46'),
(3, 8, '2023-12-15 17:30:00', '2023-12-15 19:00:00', 90, '2025-10-31 07:33:46'),
(4, 5, '2023-12-14 06:45:00', '2023-12-14 08:30:00', 105, '2025-10-31 07:33:46'),
(5, 6, '2023-12-14 18:00:00', '2023-12-14 19:30:00', 90, '2025-10-31 07:33:46'),
(6, 5, '2025-10-28 15:33:47', '2025-10-28 17:03:47', NULL, '2025-10-31 07:33:47'),
(7, 6, '2025-10-28 15:33:47', '2025-10-28 17:33:47', NULL, '2025-10-31 07:33:47'),
(8, 8, '2025-10-29 15:33:47', '2025-10-29 16:48:47', NULL, '2025-10-31 07:33:47');

-- --------------------------------------------------------

--
-- Stand-in structure for view `attendance_summary`
-- (See below for the actual view)
--
CREATE TABLE `attendance_summary` (
`user_id` int(11)
,`first_name` varchar(50)
,`last_name` varchar(50)
,`total_visits` bigint(21)
,`avg_duration` decimal(14,4)
,`last_visit` datetime
);

-- --------------------------------------------------------

--
-- Table structure for table `coaching_services`
--

CREATE TABLE `coaching_services` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `service_type` enum('personal','online','hybrid') NOT NULL,
  `appointment_date` datetime NOT NULL,
  `appointment_end` datetime DEFAULT NULL,
  `assigned_trainer_id` int(11) DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled','no-show') DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `coaching_services`
--

INSERT INTO `coaching_services` (`id`, `user_id`, `service_type`, `appointment_date`, `appointment_end`, `assigned_trainer_id`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 5, 'personal', '2023-12-18 09:00:00', NULL, 2, 'scheduled', 'Focus on strength training and form correction', '2025-10-31 07:33:46', '2025-10-31 07:33:46'),
(2, 6, 'online', '2023-12-19 14:00:00', NULL, 3, 'scheduled', 'Nutrition consultation and workout plan review', '2025-10-31 07:33:46', '2025-10-31 07:33:46'),
(3, 8, 'hybrid', '2023-12-20 16:00:00', NULL, 4, 'scheduled', 'Initial assessment and goal setting', '2025-10-31 07:33:46', '2025-10-31 07:33:46'),
(4, 5, 'personal', '2023-12-15 10:00:00', NULL, 2, 'completed', 'Completed - Good progress on bench press', '2025-10-31 07:33:46', '2025-10-31 07:33:46'),
(6, 9, 'personal', '2025-11-08 20:00:00', NULL, 2, 'scheduled', '', '2025-10-31 08:50:45', '2025-10-31 08:50:45');

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment_text` text NOT NULL,
  `is_approved` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `comments`
--

INSERT INTO `comments` (`id`, `announcement_id`, `user_id`, `comment_text`, `is_approved`, `created_at`, `updated_at`) VALUES
(1, 1, 5, 'The new system is amazing! Love the progress tracking features.', 1, '2025-10-31 05:33:46', '2025-10-31 07:33:46'),
(2, 1, 6, 'Great improvement! The coaching scheduling is so convenient.', 1, '2025-10-31 06:33:46', '2025-10-31 07:33:46'),
(3, 2, 2, 'I will be conducting equipment orientation sessions daily at 6 PM this week.', 1, '2025-10-30 07:33:46', '2025-10-31 07:33:46'),
(4, 3, 8, 'Excited to join the challenge! When can we register?', 1, '2025-10-31 04:33:46', '2025-10-31 07:33:46');

-- --------------------------------------------------------

--
-- Stand-in structure for view `financial_summary`
-- (See below for the actual view)
--
CREATE TABLE `financial_summary` (
`payment_day` date
,`transaction_count` bigint(21)
,`daily_revenue` decimal(32,2)
,`payment_method` enum('gcash','direct')
);

-- --------------------------------------------------------

--
-- Table structure for table `memberships`
--

CREATE TABLE `memberships` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `membership_type` enum('walk-in','membership') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `start_date` date NOT NULL,
  `expiration_date` date DEFAULT NULL,
  `payment_method` enum('gcash','direct','pending') DEFAULT 'pending',
  `payment_status` enum('pending','confirmed','failed') DEFAULT 'pending',
  `payment_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `memberships`
--

INSERT INTO `memberships` (`id`, `user_id`, `membership_type`, `amount`, `start_date`, `expiration_date`, `payment_method`, `payment_status`, `payment_date`, `created_at`, `updated_at`) VALUES
(1, 5, 'membership', 500.00, '2023-11-01', '2023-12-01', 'pending', 'confirmed', NULL, '2025-10-31 07:33:46', '2025-10-31 07:33:46'),
(2, 6, 'membership', 500.00, '2023-11-15', '2023-12-15', 'pending', 'confirmed', NULL, '2025-10-31 07:33:46', '2025-10-31 07:33:46'),
(3, 7, 'walk-in', 60.00, '2023-12-01', NULL, 'pending', 'confirmed', '2025-11-11 14:45:10', '2025-10-31 07:33:46', '2025-11-11 14:45:10'),
(4, 8, 'membership', 500.00, '2023-12-01', '2024-01-01', 'pending', 'confirmed', NULL, '2025-10-31 07:33:46', '2025-10-31 07:33:46'),
(5, 9, 'membership', 500.00, '2025-10-31', '2025-11-30', 'pending', 'confirmed', '2025-10-31 08:49:17', '2025-10-31 08:49:06', '2025-10-31 08:49:17'),
(6, 10, 'walk-in', 60.00, '2025-11-11', NULL, 'pending', 'confirmed', '2025-11-11 14:48:26', '2025-11-11 14:47:10', '2025-11-11 14:48:26');

--
-- Triggers `memberships`
--
DELIMITER $$
CREATE TRIGGER `set_membership_expiration` BEFORE INSERT ON `memberships` FOR EACH ROW BEGIN
    IF NEW.membership_type = 'membership' AND NEW.expiration_date IS NULL THEN
        SET NEW.expiration_date = DATE_ADD(NEW.start_date, INTERVAL 30 DAY);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `member_progress`
-- (See below for the actual view)
--
CREATE TABLE `member_progress` (
`user_id` int(11)
,`first_name` varchar(50)
,`last_name` varchar(50)
,`measurement_type` enum('weight','body_fat','muscle_mass','chest','waist','arms','thighs')
,`value` decimal(8,2)
,`unit` varchar(20)
,`measured_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `subject`, `message`, `is_read`, `created_at`) VALUES
(1, 5, 2, 'Question about my training program', 'Hi Mike, I have some questions about the exercises in my strength program. When would be a good time to discuss?', 1, '2025-10-29 07:33:46'),
(2, 2, 5, 'Re: Question about my training program', 'Hi Juan! I am available tomorrow at 3 PM. We can go through your program together.', 1, '2025-10-30 07:33:46'),
(3, 6, 3, 'Nutrition Consultation', 'Hello Sarah, I would like to schedule a nutrition consultation for next week.', 1, '2025-10-28 07:33:46'),
(4, 3, 6, 'Re: Nutrition Consultation', 'Hi Maria! I have openings on Tuesday and Thursday. Let me know what works for you.', 0, '2025-10-29 07:33:46'),
(5, 8, 4, 'Initial Assessment', 'Hi David, I am interested in the hybrid coaching program. Can we schedule an initial assessment?', 1, '2025-10-30 07:33:46'),
(6, 1, 5, 'Membership Renewal', 'Hi Juan, your membership is expiring soon. Please visit the front desk to renew.', 0, '2025-10-31 02:33:46'),
(7, 1, 6, 'Gym Maintenance', 'Hi Maria, just wanted to inform you about the scheduled maintenance this Saturday from 6-8 AM.', 0, '2025-10-31 04:33:46'),
(8, 1, 3, 'hi ', 'Hi', 0, '2025-11-11 14:49:28'),
(9, 2, 1, '', 'Hi', 0, '2025-11-11 14:50:07');

--
-- Triggers `messages`
--
DELIMITER $$
CREATE TRIGGER `create_message_status` AFTER INSERT ON `messages` FOR EACH ROW BEGIN
    INSERT INTO message_status (message_id, user_id, is_read) 
    VALUES (NEW.id, NEW.receiver_id, 0);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `message_status`
--

CREATE TABLE `message_status` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `is_read` tinyint(4) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `message_status`
--

INSERT INTO `message_status` (`id`, `message_id`, `user_id`, `is_read`, `read_at`) VALUES
(1, 1, 2, 1, '2025-10-29 07:33:46'),
(2, 2, 5, 1, '2025-10-30 07:33:46'),
(3, 3, 3, 1, '2025-10-28 07:33:46'),
(4, 4, 6, 0, NULL),
(5, 5, 4, 1, '2025-10-30 07:33:46'),
(6, 6, 5, 0, NULL),
(7, 7, 6, 0, NULL),
(8, 8, 3, 0, NULL),
(9, 9, 1, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `membership_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('gcash','direct') NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `gcash_number` varchar(20) DEFAULT NULL,
  `payment_date` timestamp NULL DEFAULT NULL,
  `status` enum('pending','confirmed','failed') DEFAULT 'pending',
  `confirmed_by` int(11) DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `user_id`, `membership_id`, `amount`, `payment_method`, `reference_number`, `gcash_number`, `payment_date`, `status`, `confirmed_by`, `confirmed_at`, `created_at`, `updated_at`) VALUES
(1, 5, 1, 500.00, 'gcash', 'GCASH123456', '09151234567', '2023-11-01 02:30:00', 'confirmed', NULL, NULL, '2025-10-31 07:33:46', '2025-10-31 07:33:46'),
(2, 6, 2, 500.00, 'direct', 'CASH001', NULL, '2023-11-15 06:20:00', 'confirmed', NULL, NULL, '2025-10-31 07:33:46', '2025-10-31 07:33:46'),
(3, 7, 3, 60.00, 'gcash', 'GCASH789012', '09153456789', '2023-12-01 01:15:00', 'confirmed', 1, '2025-11-11 14:45:10', '2025-10-31 07:33:46', '2025-11-11 14:45:10'),
(4, 8, 4, 500.00, 'gcash', 'GCASH345678', '09154567890', '2023-12-01 08:45:00', 'confirmed', NULL, NULL, '2025-10-31 07:33:46', '2025-10-31 07:33:46'),
(5, 9, 5, 500.00, '', NULL, NULL, NULL, 'confirmed', 1, '2025-10-31 08:49:17', '2025-10-31 08:49:06', '2025-10-31 08:49:17'),
(6, 10, 6, 60.00, '', NULL, NULL, NULL, 'confirmed', 1, '2025-11-11 14:48:26', '2025-11-11 14:47:11', '2025-11-11 14:48:26');

-- --------------------------------------------------------

--
-- Stand-in structure for view `pending_setup_users`
-- (See below for the actual view)
--
CREATE TABLE `pending_setup_users` (
`id` int(11)
,`first_name` varchar(50)
,`last_name` varchar(50)
,`email` varchar(100)
,`contact_number` varchar(20)
,`setup_token` varchar(255)
,`membership_type` enum('walk-in','membership')
,`amount` decimal(10,2)
,`payment_method` enum('gcash','direct')
);

-- --------------------------------------------------------

--
-- Table structure for table `progress_tracking`
--

CREATE TABLE `progress_tracking` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `measurement_type` enum('weight','body_fat','muscle_mass','chest','waist','arms','thighs') NOT NULL,
  `value` decimal(8,2) NOT NULL,
  `unit` varchar(20) DEFAULT 'kg',
  `notes` text DEFAULT NULL,
  `measured_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `progress_tracking`
--

INSERT INTO `progress_tracking` (`id`, `user_id`, `measurement_type`, `value`, `unit`, `notes`, `measured_at`, `created_at`) VALUES
(1, 5, 'weight', 80.50, 'kg', NULL, '2023-11-30 16:00:00', '2025-10-31 07:33:46'),
(2, 5, 'weight', 79.80, 'kg', NULL, '2023-12-14 16:00:00', '2025-10-31 07:33:46'),
(3, 5, 'chest', 102.50, 'cm', NULL, '2023-11-30 16:00:00', '2025-10-31 07:33:46'),
(4, 5, 'chest', 104.20, 'cm', NULL, '2023-12-14 16:00:00', '2025-10-31 07:33:46'),
(5, 6, 'weight', 60.50, 'kg', NULL, '2023-11-30 16:00:00', '2025-10-31 07:33:46'),
(6, 6, 'weight', 60.20, 'kg', NULL, '2023-12-14 16:00:00', '2025-10-31 07:33:46'),
(7, 6, 'arms', 32.50, 'cm', NULL, '2025-10-31 07:33:47', '2025-10-31 07:33:47'),
(8, 6, 'waist', 72.00, 'cm', NULL, '2025-10-31 07:33:47', '2025-10-31 07:33:47'),
(9, 8, 'weight', 58.00, 'kg', NULL, '2025-10-31 07:33:47', '2025-10-31 07:33:47'),
(10, 8, 'body_fat', 22.50, '%', NULL, '2025-10-31 07:33:47', '2025-10-31 07:33:47');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES
(1, 'gym_name', 'Signus Gym', 'Name of the gym', '2025-10-31 07:33:46'),
(2, 'gym_address', '123 Fitness Street, Gym City', 'Physical address of the gym', '2025-10-31 07:33:46'),
(3, 'gym_phone', '+1 234 567 8900', 'Contact phone number', '2025-10-31 07:33:46'),
(4, 'gym_email', 'info@signusgym.com', 'Contact email address', '2025-10-31 07:33:46'),
(5, 'walk_in_price', '60', 'Price for walk-in sessions in PHP', '2025-10-31 07:33:46'),
(6, 'membership_price', '500', 'Price for monthly membership in PHP', '2025-10-31 07:33:46'),
(7, 'gcash_number', '09652432510', 'GCash number for payments', '2025-10-31 07:33:46'),
(8, 'business_hours', '24/7', 'Gym operating hours', '2025-10-31 07:33:46'),
(9, 'coaching_types', 'personal,online,hybrid', 'Available coaching types', '2025-10-31 07:33:46'),
(10, 'membership_duration', '30', 'Membership duration in days', '2025-10-31 07:33:46'),
(11, 'max_photo_size', '5242880', 'Maximum profile photo size in bytes (5MB)', '2025-10-31 07:33:46'),
(12, 'allowed_photo_types', 'jpg,jpeg,png,gif', 'Allowed profile photo file types', '2025-10-31 07:33:46');

-- --------------------------------------------------------

--
-- Stand-in structure for view `trainer_schedule`
-- (See below for the actual view)
--
CREATE TABLE `trainer_schedule` (
`id` int(11)
,`appointment_date` datetime
,`service_type` enum('personal','online','hybrid')
,`status` enum('scheduled','completed','cancelled','no-show')
,`client_first_name` varchar(50)
,`client_last_name` varchar(50)
,`client_contact` varchar(20)
,`trainer_first_name` varchar(50)
,`trainer_last_name` varchar(50)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `unread_messages_count`
-- (See below for the actual view)
--
CREATE TABLE `unread_messages_count` (
`user_id` int(11)
,`unread_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `user_type` enum('admin','trainer','gymrat') NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `setup_token` varchar(255) DEFAULT NULL,
  `token_expires_at` timestamp NULL DEFAULT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','active','suspended','pending_setup') DEFAULT 'pending',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `contact_number`, `profile_photo`, `user_type`, `username`, `password_hash`, `setup_token`, `token_expires_at`, `registration_date`, `status`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 'User', 'admin@signus.com', '09123456789', NULL, 'admin', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, '2025-10-31 07:33:46', 'active', NULL, '2025-10-31 07:33:46', '2025-10-31 07:33:46'),
(2, 'Mike', 'Johnson', 'trainer1@signus.com', '09123456788', NULL, 'trainer', 'trainer1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, '2025-10-31 07:33:46', 'active', NULL, '2025-10-31 07:33:46', '2025-10-31 07:33:46'),
(3, 'Sarah', 'Wilson', 'trainer2@signus.com', '09123456787', NULL, 'trainer', 'trainer2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, '2025-10-31 07:33:46', 'active', NULL, '2025-10-31 07:33:46', '2025-10-31 07:33:46'),
(4, 'David', 'Brown', 'trainer3@signus.com', '09123456786', NULL, 'trainer', 'trainer3', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, '2025-10-31 07:33:46', 'active', NULL, '2025-10-31 07:33:46', '2025-10-31 07:33:46'),
(5, 'Juan', 'Dela Cruz', 'juan.dela.cruz@email.com', '09151234567', NULL, 'gymrat', 'juan.delacruz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, '2025-10-31 07:33:46', 'active', NULL, '2025-10-31 07:33:46', '2025-10-31 07:33:46'),
(6, 'Maria', 'Santos', 'maria.santos@email.com', '09152345678', NULL, 'gymrat', 'maria.santos', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, '2025-10-31 07:33:46', 'active', NULL, '2025-10-31 07:33:46', '2025-10-31 07:33:46'),
(7, 'Pedro', 'Reyes', 'pedro.reyes@email.com', '09153456789', NULL, 'gymrat', NULL, NULL, NULL, NULL, '2025-10-31 07:33:46', 'pending_setup', NULL, '2025-10-31 07:33:46', '2025-10-31 07:33:46'),
(8, 'Anna', 'Gonzales', 'anna.gonzales@email.com', '09154567890', NULL, 'gymrat', 'anna.gonzales', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, '2025-10-31 07:33:46', 'active', NULL, '2025-10-31 07:33:46', '2025-10-31 07:33:46'),
(9, 'makku ', 'SIRIBAN', 'macsiriban26@gmail.com', '09652432510', NULL, 'gymrat', 'welcomegymrat2', '$2y$10$KWJQtdV9UykjEOb6iYHlFuLOC98bLyzGtCaOI9xV.yvvEuajtVL.u', NULL, NULL, '2025-10-31 08:49:06', 'active', NULL, '2025-10-31 08:49:06', '2025-10-31 08:49:47'),
(10, 'Criselina', 'menor', 'menordeedad10@gmail.com', '09020250501', NULL, 'gymrat', 'menor', '$2y$10$MaVGs4lhtAp7GW2mpEQllOa.Lx6mUmiHYdnBT8PMxqPaFlOu49m92', NULL, NULL, '2025-11-11 14:47:10', 'active', NULL, '2025-11-11 14:47:10', '2025-11-11 14:48:48');

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `update_last_login` BEFORE UPDATE ON `users` FOR EACH ROW BEGIN
    IF NEW.last_login IS NULL AND OLD.last_login IS NOT NULL THEN
        SET NEW.last_login = OLD.last_login;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `user_conversations`
-- (See below for the actual view)
--
CREATE TABLE `user_conversations` (
`id` int(11)
,`sender_id` int(11)
,`receiver_id` int(11)
,`subject` varchar(255)
,`message` text
,`is_read` tinyint(4)
,`created_at` timestamp
,`sender_first_name` varchar(50)
,`sender_last_name` varchar(50)
,`receiver_first_name` varchar(50)
,`receiver_last_name` varchar(50)
,`read_status` tinyint(4)
);

-- --------------------------------------------------------

--
-- Table structure for table `user_goals`
--

CREATE TABLE `user_goals` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `goal_type` enum('weight_loss','muscle_gain','endurance','strength','general_fitness') NOT NULL,
  `target_value` decimal(8,2) DEFAULT NULL,
  `current_value` decimal(8,2) DEFAULT NULL,
  `target_date` date DEFAULT NULL,
  `status` enum('active','completed','abandoned') DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_goals`
--

INSERT INTO `user_goals` (`id`, `user_id`, `goal_type`, `target_value`, `current_value`, `target_date`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 5, 'weight_loss', 75.00, 80.50, '2024-03-15', 'active', NULL, '2025-10-31 07:33:46', '2025-10-31 07:33:46'),
(2, 5, 'strength', 100.00, 85.00, '2024-02-01', 'active', NULL, '2025-10-31 07:33:46', '2025-10-31 07:33:46'),
(3, 6, 'muscle_gain', 65.00, 60.50, '2024-04-01', 'active', NULL, '2025-10-31 07:33:46', '2025-10-31 07:33:46'),
(4, 8, 'endurance', NULL, NULL, '2024-01-31', 'active', NULL, '2025-10-31 07:33:46', '2025-10-31 07:33:46');

-- --------------------------------------------------------

--
-- Table structure for table `workout_exercises`
--

CREATE TABLE `workout_exercises` (
  `id` int(11) NOT NULL,
  `workout_plan_id` int(11) NOT NULL,
  `exercise_name` varchar(255) NOT NULL,
  `sets` int(11) DEFAULT NULL,
  `reps` varchar(50) DEFAULT NULL,
  `weight` varchar(100) DEFAULT NULL,
  `rest_time` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `day_of_week` enum('monday','tuesday','wednesday','thursday','friday','saturday','sunday') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workout_exercises`
--

INSERT INTO `workout_exercises` (`id`, `workout_plan_id`, `exercise_name`, `sets`, `reps`, `weight`, `rest_time`, `notes`, `day_of_week`, `created_at`) VALUES
(1, 1, 'Barbell Bench Press', 4, '8-10', '60-80kg', NULL, NULL, 'monday', '2025-10-31 07:33:46'),
(2, 1, 'Barbell Squat', 4, '6-8', '80-100kg', NULL, NULL, 'monday', '2025-10-31 07:33:46'),
(3, 1, 'Deadlift', 3, '4-6', '100-120kg', NULL, NULL, 'wednesday', '2025-10-31 07:33:46'),
(4, 1, 'Overhead Press', 4, '8-10', '40-50kg', NULL, NULL, 'wednesday', '2025-10-31 07:33:46'),
(5, 2, 'Treadmill Running', 1, '30min', 'Moderate Pace', NULL, NULL, 'monday', '2025-10-31 07:33:46'),
(6, 2, 'Dumbbell Press', 3, '12-15', '15-20kg', NULL, NULL, 'monday', '2025-10-31 07:33:46');

-- --------------------------------------------------------

--
-- Table structure for table `workout_plans`
--

CREATE TABLE `workout_plans` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `trainer_id` int(11) DEFAULT NULL,
  `plan_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `difficulty` enum('beginner','intermediate','advanced') DEFAULT 'beginner',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workout_plans`
--

INSERT INTO `workout_plans` (`id`, `user_id`, `trainer_id`, `plan_name`, `description`, `difficulty`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 5, 2, 'Strength Foundation Program', '12-week strength building program focusing on compound lifts', 'intermediate', 1, '2025-10-31 07:33:46', '2025-10-31 07:33:46'),
(2, 6, 3, 'Weight Loss Transformation', 'Cardio and strength circuit for fat loss', 'beginner', 1, '2025-10-31 07:33:46', '2025-10-31 07:33:46'),
(3, 8, 4, 'General Fitness Maintenance', 'Balanced workout routine for overall health', 'beginner', 1, '2025-10-31 07:33:46', '2025-10-31 07:33:46');

-- --------------------------------------------------------

--
-- Structure for view `active_members`
--
DROP TABLE IF EXISTS `active_members`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `active_members`  AS SELECT `u`.`id` AS `id`, `u`.`first_name` AS `first_name`, `u`.`last_name` AS `last_name`, `u`.`email` AS `email`, `u`.`contact_number` AS `contact_number`, `u`.`user_type` AS `user_type`, `u`.`status` AS `status`, `m`.`membership_type` AS `membership_type`, `m`.`amount` AS `amount`, `m`.`expiration_date` AS `expiration_date`, `m`.`payment_status` AS `payment_status` FROM (`users` `u` join `memberships` `m` on(`u`.`id` = `m`.`user_id`)) WHERE `u`.`status` = 'active' AND `m`.`payment_status` = 'confirmed' AND (`m`.`expiration_date` is null OR `m`.`expiration_date` >= curdate()) ;

-- --------------------------------------------------------

--
-- Structure for view `attendance_summary`
--
DROP TABLE IF EXISTS `attendance_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `attendance_summary`  AS SELECT `u`.`id` AS `user_id`, `u`.`first_name` AS `first_name`, `u`.`last_name` AS `last_name`, count(`a`.`id`) AS `total_visits`, avg(`a`.`duration_minutes`) AS `avg_duration`, max(`a`.`check_in`) AS `last_visit` FROM (`users` `u` left join `attendance` `a` on(`u`.`id` = `a`.`user_id`)) WHERE `u`.`user_type` = 'gymrat' GROUP BY `u`.`id`, `u`.`first_name`, `u`.`last_name` ;

-- --------------------------------------------------------

--
-- Structure for view `financial_summary`
--
DROP TABLE IF EXISTS `financial_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `financial_summary`  AS SELECT cast(`payments`.`payment_date` as date) AS `payment_day`, count(0) AS `transaction_count`, sum(`payments`.`amount`) AS `daily_revenue`, `payments`.`payment_method` AS `payment_method` FROM `payments` WHERE `payments`.`status` = 'confirmed' GROUP BY cast(`payments`.`payment_date` as date), `payments`.`payment_method` ORDER BY cast(`payments`.`payment_date` as date) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `member_progress`
--
DROP TABLE IF EXISTS `member_progress`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `member_progress`  AS SELECT `pt`.`user_id` AS `user_id`, `u`.`first_name` AS `first_name`, `u`.`last_name` AS `last_name`, `pt`.`measurement_type` AS `measurement_type`, `pt`.`value` AS `value`, `pt`.`unit` AS `unit`, `pt`.`measured_at` AS `measured_at` FROM (`progress_tracking` `pt` join `users` `u` on(`pt`.`user_id` = `u`.`id`)) ORDER BY `pt`.`measured_at` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `pending_setup_users`
--
DROP TABLE IF EXISTS `pending_setup_users`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `pending_setup_users`  AS SELECT `u`.`id` AS `id`, `u`.`first_name` AS `first_name`, `u`.`last_name` AS `last_name`, `u`.`email` AS `email`, `u`.`contact_number` AS `contact_number`, `u`.`setup_token` AS `setup_token`, `m`.`membership_type` AS `membership_type`, `p`.`amount` AS `amount`, `p`.`payment_method` AS `payment_method` FROM ((`users` `u` join `memberships` `m` on(`u`.`id` = `m`.`user_id`)) join `payments` `p` on(`u`.`id` = `p`.`user_id`)) WHERE `u`.`status` = 'pending_setup' AND `u`.`username` is null AND `u`.`password_hash` is null AND `p`.`status` = 'confirmed' ;

-- --------------------------------------------------------

--
-- Structure for view `trainer_schedule`
--
DROP TABLE IF EXISTS `trainer_schedule`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `trainer_schedule`  AS SELECT `cs`.`id` AS `id`, `cs`.`appointment_date` AS `appointment_date`, `cs`.`service_type` AS `service_type`, `cs`.`status` AS `status`, `u`.`first_name` AS `client_first_name`, `u`.`last_name` AS `client_last_name`, `u`.`contact_number` AS `client_contact`, `t`.`first_name` AS `trainer_first_name`, `t`.`last_name` AS `trainer_last_name` FROM ((`coaching_services` `cs` join `users` `u` on(`cs`.`user_id` = `u`.`id`)) left join `users` `t` on(`cs`.`assigned_trainer_id` = `t`.`id`)) WHERE `cs`.`status` = 'scheduled' ORDER BY `cs`.`appointment_date` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `unread_messages_count`
--
DROP TABLE IF EXISTS `unread_messages_count`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `unread_messages_count`  AS SELECT `m`.`receiver_id` AS `user_id`, count(`m`.`id`) AS `unread_count` FROM (`messages` `m` left join `message_status` `ms` on(`m`.`id` = `ms`.`message_id` and `ms`.`user_id` = `m`.`receiver_id`)) WHERE `ms`.`is_read` = 0 OR `ms`.`id` is null GROUP BY `m`.`receiver_id` ;

-- --------------------------------------------------------

--
-- Structure for view `user_conversations`
--
DROP TABLE IF EXISTS `user_conversations`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `user_conversations`  AS SELECT `m`.`id` AS `id`, `m`.`sender_id` AS `sender_id`, `m`.`receiver_id` AS `receiver_id`, `m`.`subject` AS `subject`, `m`.`message` AS `message`, `m`.`is_read` AS `is_read`, `m`.`created_at` AS `created_at`, `sender`.`first_name` AS `sender_first_name`, `sender`.`last_name` AS `sender_last_name`, `receiver`.`first_name` AS `receiver_first_name`, `receiver`.`last_name` AS `receiver_last_name`, `ms`.`is_read` AS `read_status` FROM (((`messages` `m` join `users` `sender` on(`m`.`sender_id` = `sender`.`id`)) join `users` `receiver` on(`m`.`receiver_id` = `receiver`.`id`)) left join `message_status` `ms` on(`m`.`id` = `ms`.`message_id` and `ms`.`user_id` = `m`.`receiver_id`)) ORDER BY `m`.`created_at` DESC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_setup_tokens`
--
ALTER TABLE `account_setup_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_setup_tokens_token` (`token`),
  ADD KEY `idx_setup_tokens_expires` (`expires_at`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_announcements_published` (`is_published`,`created_at`),
  ADD KEY `idx_announcements_admin_id` (`admin_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attendance_user_id` (`user_id`),
  ADD KEY `idx_attendance_date` (`check_in`),
  ADD KEY `idx_attendance_check_out` (`check_out`);

--
-- Indexes for table `coaching_services`
--
ALTER TABLE `coaching_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_coaching_user_id` (`user_id`),
  ADD KEY `idx_coaching_trainer_id` (`assigned_trainer_id`),
  ADD KEY `idx_coaching_date` (`appointment_date`),
  ADD KEY `idx_coaching_status` (`status`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_comments_announcement` (`announcement_id`,`created_at`),
  ADD KEY `idx_comments_user` (`user_id`);

--
-- Indexes for table `memberships`
--
ALTER TABLE `memberships`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_memberships_user_id` (`user_id`),
  ADD KEY `idx_memberships_status` (`payment_status`),
  ADD KEY `idx_memberships_expiration` (`expiration_date`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_messages_sender` (`sender_id`),
  ADD KEY `idx_messages_receiver` (`receiver_id`),
  ADD KEY `idx_messages_created` (`created_at`);

--
-- Indexes for table `message_status`
--
ALTER TABLE `message_status`
  ADD PRIMARY KEY (`id`),
  ADD KEY `message_id` (`message_id`),
  ADD KEY `idx_message_status_user` (`user_id`),
  ADD KEY `idx_message_status_read` (`is_read`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `membership_id` (`membership_id`),
  ADD KEY `confirmed_by` (`confirmed_by`),
  ADD KEY `idx_payments_user_id` (`user_id`),
  ADD KEY `idx_payments_status` (`status`),
  ADD KEY `idx_payments_date` (`payment_date`);

--
-- Indexes for table `progress_tracking`
--
ALTER TABLE `progress_tracking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_progress_user_id` (`user_id`),
  ADD KEY `idx_progress_measured` (`measured_at`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_users_email` (`email`),
  ADD KEY `idx_users_username` (`username`),
  ADD KEY `idx_users_status` (`status`),
  ADD KEY `idx_users_user_type` (`user_type`),
  ADD KEY `idx_users_setup_token` (`setup_token`);

--
-- Indexes for table `user_goals`
--
ALTER TABLE `user_goals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_goals_user_id` (`user_id`),
  ADD KEY `idx_goals_status` (`status`);

--
-- Indexes for table `workout_exercises`
--
ALTER TABLE `workout_exercises`
  ADD PRIMARY KEY (`id`),
  ADD KEY `workout_plan_id` (`workout_plan_id`);

--
-- Indexes for table `workout_plans`
--
ALTER TABLE `workout_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `trainer_id` (`trainer_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account_setup_tokens`
--
ALTER TABLE `account_setup_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `coaching_services`
--
ALTER TABLE `coaching_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `memberships`
--
ALTER TABLE `memberships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `message_status`
--
ALTER TABLE `message_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `progress_tracking`
--
ALTER TABLE `progress_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `user_goals`
--
ALTER TABLE `user_goals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `workout_exercises`
--
ALTER TABLE `workout_exercises`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `workout_plans`
--
ALTER TABLE `workout_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `account_setup_tokens`
--
ALTER TABLE `account_setup_tokens`
  ADD CONSTRAINT `account_setup_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `coaching_services`
--
ALTER TABLE `coaching_services`
  ADD CONSTRAINT `coaching_services_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `coaching_services_ibfk_2` FOREIGN KEY (`assigned_trainer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `memberships`
--
ALTER TABLE `memberships`
  ADD CONSTRAINT `memberships_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `message_status`
--
ALTER TABLE `message_status`
  ADD CONSTRAINT `message_status_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `message_status_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `password_reset_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`membership_id`) REFERENCES `memberships` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `progress_tracking`
--
ALTER TABLE `progress_tracking`
  ADD CONSTRAINT `progress_tracking_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_goals`
--
ALTER TABLE `user_goals`
  ADD CONSTRAINT `user_goals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `workout_exercises`
--
ALTER TABLE `workout_exercises`
  ADD CONSTRAINT `workout_exercises_ibfk_1` FOREIGN KEY (`workout_plan_id`) REFERENCES `workout_plans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `workout_plans`
--
ALTER TABLE `workout_plans`
  ADD CONSTRAINT `workout_plans_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `workout_plans_ibfk_2` FOREIGN KEY (`trainer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
