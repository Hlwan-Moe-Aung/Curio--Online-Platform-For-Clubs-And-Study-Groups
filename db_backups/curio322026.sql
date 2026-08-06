-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 02, 2026 at 12:40 PM
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
-- Database: `curio`
--

-- --------------------------------------------------------

--
-- Table structure for table `communities`
--

CREATE TABLE `communities` (
  `id` int(11) NOT NULL,
  `leader_name` varchar(100) NOT NULL,
  `leader_email` varchar(100) NOT NULL,
  `community_name` varchar(100) NOT NULL,
  `type` enum('club','study_group') NOT NULL,
  `category` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `purpose` text NOT NULL,
  `disband_reason` text DEFAULT NULL,
  `appeal` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','disband_pending') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `pending_leader` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `communities`
--

INSERT INTO `communities` (`id`, `leader_name`, `leader_email`, `community_name`, `type`, `category`, `description`, `profile_pic`, `purpose`, `disband_reason`, `appeal`, `status`, `created_at`, `pending_leader`) VALUES
(25, 'Alpha', 'alpha@gmail.com', 'Hidden Gems', 'club', 'social', 'A community-driven guide to the most affordable, high-quality, or \"secret\" food spots and cafes around the university. Members share photos, price ranges, and \"best-time-to-visit\" tips.', '1772435367_69a537a75cbb6.jfif', 'To help students (especially freshmen) navigate local food options on a budget and support local small businesses.', NULL, 'This club promotes student welfare by helping them manage living costs. It is low-risk, highly visual, and encourages positive community interaction without the controversy often found in political or social clubs.', 'approved', '2026-03-02 05:49:37', NULL),
(26, 'Beta', 'beta@gmail.com', 'DSA Mastery & Interview Prep', 'study_group', 'cs', 'A dedicated space for students to practice coding challenges, discuss algorithmic complexity, and prepare for technical interviews. We focus on solving one LeetCode / Hackerrank problem together every day.', '1772433400_69a52ff801b7e.jfif', 'To bridge the gap between classroom theory and practical coding interviews. Our goal is to ensure every member can confidently explain Big O notation and implement core data structures (Trees, Graphs, HashMaps) by the end of the semester.', NULL, 'This group directly supports the university\'s \"Career Readiness\" and \"Academic Excellence\" goals. It provides a peer-to-peer learning environment that reduces the load on teaching assistants and helps improve the student placement rate for internships.', 'approved', '2026-03-02 06:34:49', NULL),
(27, 'Gamma', 'gamma@gmail.com', 'Class of 2025: The Digital Time Capsule', 'club', 'creative', 'A collaborative project to document student life in 2025. We collect photos of current campus fashion, trending slang, favorite hangouts, and screenshots of global events that define our university years.', '1772435011_69a53643a6f17.jfif', 'To create a digital archive for future generations. On Graduation Day, this club won\'t be active anymore. Providing a window back in time for students 10 or 20 years from now.', NULL, 'This club fosters a sense of legacy and history. It is a time-bound project that will eventually be archived (Disbanded), allowing the Admin to test the system\'s ability to handle \"Project-Based\" communities that don\'t last forever.', 'approved', '2026-03-02 06:59:44', NULL),
(28, 'Zeta', 'zeta@gmail.com', 'Zero-Budget', 'club', 'creative', 'Focusing on origami, sketching, or cardistry is purely about the \"Create\" aspect. It’s about making something out of nothing.', NULL, 'To share everyone creative ideas.', NULL, 'I would like to create warm and welcome environment for creative enthusiastic ', 'pending', '2026-03-02 09:40:52', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `community_bans`
--

CREATE TABLE `community_bans` (
  `id` int(11) NOT NULL,
  `community_id` int(11) NOT NULL,
  `user_email` varchar(100) NOT NULL,
  `reason` text DEFAULT NULL,
  `banned_until` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `community_exams`
--

CREATE TABLE `community_exams` (
  `id` int(11) NOT NULL,
  `community_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `exam_date` datetime NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `created_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `community_files`
--

CREATE TABLE `community_files` (
  `id` int(11) NOT NULL,
  `community_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `uploaded_by` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `id` int(11) NOT NULL,
  `community_id` int(11) NOT NULL,
  `user_email` varchar(255) NOT NULL,
  `role` enum('member','moderator','banned') DEFAULT 'member',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `community_id`, `user_email`, `role`, `joined_at`) VALUES
(31, 25, 'beta@gmail.com', 'member', '2026-03-02 06:03:25'),
(32, 26, 'alpha@gmail.com', 'member', '2026-03-02 06:37:19'),
(33, 27, 'delta@gmail.com', 'member', '2026-03-02 07:04:41'),
(34, 25, 'epsilon@gmail.com', 'member', '2026-03-02 09:07:41');

-- --------------------------------------------------------

--
-- Table structure for table `membership_requests`
--

CREATE TABLE `membership_requests` (
  `id` int(11) NOT NULL,
  `community_id` int(11) NOT NULL,
  `user_email` varchar(255) NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `appeal` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `membership_requests`
--

INSERT INTO `membership_requests` (`id`, `community_id`, `user_email`, `user_name`, `appeal`, `status`, `created_at`) VALUES
(34, 25, 'beta@gmail.com', 'Beta', 'I love foods~~ pls accept me', 'approved', '2026-03-02 06:03:01'),
(35, 26, 'alpha@gmail.com', 'Alpha', 'I am interested in DSA since 15', 'approved', '2026-03-02 06:37:13'),
(36, 27, 'delta@gmail.com', 'Delta', 'I love photography', 'approved', '2026-03-02 07:04:22'),
(37, 27, 'beta@gmail.com', 'Beta', 'I want to see amazing photos ', 'pending', '2026-03-02 08:45:51'),
(38, 25, 'epsilon@gmail.com', 'Epsilon', 'I like foods pls', 'approved', '2026-03-02 09:07:26'),
(39, 25, 'zeta@gmail.com', 'Zeta', 'I want to know more about foods.\r\n', 'pending', '2026-03-02 09:16:15');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `sender_email` varchar(255) NOT NULL,
  `receiver_email` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('creation','membership','report','post','system') NOT NULL,
  `status` enum('unread','read') DEFAULT 'unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `sender_email`, `receiver_email`, `title`, `message`, `type`, `status`, `created_at`) VALUES
(255, 'system', 'alpha@gmail.com', 'Request Sent: Hidden Gems', 'You have submitted a request to create the club \'Hidden Gems\'. Please wait for Admin approval.', 'creation', 'unread', '2026-03-02 05:49:37'),
(256, 'admin@gmail.com', 'alpha@gmail.com', 'Congratulations! Hidden Gems Approved', 'Sounds great', 'creation', 'read', '2026-03-02 05:50:09'),
(257, 'System', 'alpha@gmail.com', 'Community Terminated: nma', 'Notice: The community \'nma\' has been disbanded by the Site Administrator.\nReason: Test groups are to disband ', 'system', 'unread', '2026-03-02 05:50:58'),
(258, 'System', 'alpha@gmail.com', 'New Join Request: Hidden Gems', 'User Beta has requested to join your Club. \n\nAppeal: I love foods~~ pls accept me \n\nClick here to manage requests: ../views/manage_group.php?id=25', 'membership', 'unread', '2026-03-02 06:03:01'),
(259, 'System', 'beta@gmail.com', 'Request Sent: Hidden Gems', 'Your request to join Hidden Gems has been submitted to the leader (alpha@gmail.com). Please wait for approval.', 'system', 'unread', '2026-03-02 06:03:01'),
(260, 'System', 'beta@gmail.com', 'Request Approved: Hidden Gems', 'Congratulations! Your request to join Hidden Gems has been approved by the leader. You can now access the group dashboard.', 'membership', 'read', '2026-03-02 06:03:25'),
(261, 'system', 'beta@gmail.com', 'Request Sent: DSA Mastery & Interview Prep', 'You have submitted a request to create the study_group \'DSA Mastery & Interview Prep\'. Please wait for Admin approval.', 'creation', 'unread', '2026-03-02 06:34:49'),
(262, 'admin@gmail.com', 'beta@gmail.com', 'Congratulations! DSA Mastery & Interview Prep Approved', 'That\'s great.', 'creation', 'unread', '2026-03-02 06:35:17'),
(263, 'System', 'beta@gmail.com', 'New Join Request: DSA Mastery & Interview Prep', 'User Alpha has requested to join your Study Group. \n\nAppeal: I am interested in DSA since 15 \n\nClick here to manage requests: ../views/manage_group.php?id=26', 'membership', 'unread', '2026-03-02 06:37:13'),
(264, 'System', 'alpha@gmail.com', 'Request Sent: DSA Mastery & Interview Prep', 'Your request to join DSA Mastery & Interview Prep has been submitted to the leader (beta@gmail.com). Please wait for approval.', 'system', 'unread', '2026-03-02 06:37:13'),
(265, 'System', 'alpha@gmail.com', 'Request Approved: DSA Mastery & Interview Prep', 'Congratulations! Your request to join DSA Mastery & Interview Prep has been approved by the leader. You can now access the group dashboard.', 'membership', 'unread', '2026-03-02 06:37:19'),
(266, 'alpha@gmail.com', 'beta@gmail.com', 'Approval Request: Data Structures and Algorithms Cheat Sheet', 'User alpha@gmail.com uploaded a material to DSA Mastery & Interview Prep. Review it here: ../views/studyMaterials.php?id=26', '', 'unread', '2026-03-02 06:54:32'),
(267, 'beta@gmail.com', 'alpha@gmail.com', 'Material Approved: Data Structures and Algorithms Cheat Sheet', 'Your uploaded material \'Data Structures and Algorithms Cheat Sheet\' for community \'DSA Mastery & Interview Prep\' has been approved by the leader.', '', 'unread', '2026-03-02 06:54:50'),
(268, 'system', 'gamma@gmail.com', 'Request Sent: Class of 2025: The Digital Time Capsule', 'You have submitted a request to create the club \'Class of 2025: The Digital Time Capsule\'. Please wait for Admin approval.', 'creation', 'unread', '2026-03-02 06:59:44'),
(269, 'admin@gmail.com', 'gamma@gmail.com', 'Congratulations! Class of 2025: The Digital Time Capsule Approved', 'Sounds Interesting', 'creation', 'unread', '2026-03-02 07:00:01'),
(270, 'System', 'gamma@gmail.com', 'New Join Request: Class of 2025: The Digital Time Capsule', 'User Delta has requested to join your Club. \n\nAppeal: I love photography \n\nClick here to manage requests: ../views/manage_group.php?id=27', 'membership', 'unread', '2026-03-02 07:04:22'),
(271, 'System', 'delta@gmail.com', 'Request Sent: Class of 2025: The Digital Time Capsule', 'Your request to join Class of 2025: The Digital Time Capsule has been submitted to the leader (gamma@gmail.com). Please wait for approval.', 'system', 'unread', '2026-03-02 07:04:22'),
(272, 'System', 'delta@gmail.com', 'Request Approved: Class of 2025: The Digital Time Capsule', 'Congratulations! Your request to join Class of 2025: The Digital Time Capsule has been approved by the leader. You can now access the group dashboard.', 'membership', 'unread', '2026-03-02 07:04:41'),
(273, 'System', 'gamma@gmail.com', 'New Join Request: Class of 2025: The Digital Time Capsule', 'User Beta has requested to join your Club. \n\nAppeal: I want to see amazing photos  \n\nClick here to manage requests: ../views/manage_group.php?id=27', 'membership', 'unread', '2026-03-02 08:45:51'),
(274, 'System', 'beta@gmail.com', 'Request Sent: Class of 2025: The Digital Time Capsule', 'Your request to join Class of 2025: The Digital Time Capsule has been submitted to the leader (gamma@gmail.com). Please wait for approval.', 'system', 'read', '2026-03-02 08:45:51'),
(275, 'System', 'alpha@gmail.com', 'New Join Request: Hidden Gems', 'User Epsilon has requested to join your Club. \n\nAppeal: I like foods pls \n\nClick here to manage requests: ../views/manage_group.php?id=25', 'membership', 'unread', '2026-03-02 09:07:26'),
(276, 'System', 'epsilon@gmail.com', 'Request Sent: Hidden Gems', 'Your request to join Hidden Gems has been submitted to the leader (alpha@gmail.com). Please wait for approval.', 'system', 'unread', '2026-03-02 09:07:26'),
(277, 'System', 'epsilon@gmail.com', 'Request Approved: Hidden Gems', 'Congratulations! Your request to join Hidden Gems has been approved by the leader. You can now access the group dashboard.', 'membership', 'unread', '2026-03-02 09:07:41'),
(278, 'System', 'alpha@gmail.com', 'New Join Request: Hidden Gems', 'User Zeta has requested to join your Club. \n\nAppeal: I want to know more about foods.\r\n \n\nClick here to manage requests: ../views/manage_group.php?id=25', 'membership', 'read', '2026-03-02 09:16:15'),
(279, 'System', 'zeta@gmail.com', 'Request Sent: Hidden Gems', 'Your request to join Hidden Gems has been submitted to the leader (alpha@gmail.com). Please wait for approval.', 'system', 'unread', '2026-03-02 09:16:15'),
(280, 'alpha@gmail.com', 'admin@gmail.com', '🚨 Disband Request: Hidden Gems', 'Leader (alpha@gmail.com) has requested to disband the community: Hidden Gems.\n\nReason: Testing Disbandment\n\nPlease review this request in the admin dashboard: admin_dashboard.php#Disband', 'system', 'unread', '2026-03-02 09:30:42'),
(281, 'system', 'zeta@gmail.com', 'Request Sent: Zero-Budget', 'You have submitted a request to create the club \'Zero-Budget\'. Please wait for Admin approval.', 'creation', 'unread', '2026-03-02 09:40:52'),
(282, 'epsilon@gmail.com', 'admin@gmail.com', 'Misinformation', 'New report filed by epsilon@gmail.com regarding post #25.', 'report', 'unread', '2026-03-02 09:51:41'),
(283, 'System', 'alpha@gmail.com', 'Disband Request Declined: Hidden Gems', 'The admin declined the disband request for \'Hidden Gems\'.\n\nAdmin Feedback: You shouldn\'t. Pls think about your family.', 'system', 'unread', '2026-03-02 09:53:53');

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` int(11) NOT NULL,
  `community_id` int(11) NOT NULL,
  `author_email` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `post_image` varchar(255) DEFAULT NULL,
  `type` enum('public','private') DEFAULT 'private',
  `status` enum('pending_approval','approved') DEFAULT 'pending_approval',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`id`, `community_id`, `author_email`, `title`, `content`, `post_image`, `type`, `status`, `created_at`) VALUES
(23, 25, 'alpha@gmail.com', 'New Membership Rule Update', 'Important!! \r\n      We have updated our community guidelines. Please ensure all your posts include the exact price of the meal to help our fellow students on a budget. Posts without prices will be rejected starting next week.', '1772431253_download.jfif', 'public', 'approved', '2026-03-02 06:00:53'),
(24, 25, 'beta@gmail.com', 'Cafe Ciel- Good Coffee but Expensive', 'Checked out the new Cafe Nero. The coffee is 10/10 but it\'s 7,000 a cup. Probably too expensive for a daily visit but good for a treat after exams.', '1772431707_cafe nero.jfif', 'private', 'approved', '2026-03-02 06:06:24'),
(25, 25, 'beta@gmail.com', 'The 3,000 Curry Secret', 'Found a tiny stall behind the Science Building. They sell full chicken curry sets for only 3,000 if you show your student ID. It’s not on Google Maps!', '1772431861_Japanese Katsu Curry.jfif', 'private', 'approved', '2026-03-02 06:11:01'),
(26, 26, 'beta@gmail.com', 'Weekly Mock Interview Session', 'Hello everyone! This Friday at 4:00 PM in the theater (Room 202), we will be holding our first Mock Interview session. We will be practicing \'Linked List Reversal\' on the whiteboard. Please review the \'Two-Pointer Technique\' before coming. Check the resources tab for the pre-read material!', '1772433708_Lecture Theatre Redesign - Estates and Facilities.jfif', 'public', 'approved', '2026-03-02 06:41:48'),
(27, 26, 'alpha@gmail.com', 'Data Structures and Algorithms Cheat Sheet', 'Mastering Data Structures and Algorithms (DSA) is critical because it evaluates your problem-solving aptitude and logical thinking. Top-tier companies use DSA to gauge how you handle scalability and optimization. It provides a universal framework to assess your ability to write efficient code under constraints.\r\n\r\nYou can check DSA cheet sheet in Study Materials. \r\n\r\nThe material is from zerotomastery.io \r\nMaster the Coding Interview: Data Structures & Algorithms course.', '1772434370_dsa.jfif', 'private', 'approved', '2026-03-02 06:52:50'),
(28, 27, 'gamma@gmail.com', 'Monthly Theme: \"The Commute\"', 'Hi everyone! This month\'s archive theme is \'How we get to class.\' Please submit private posts showing your commute—whether it\'s the crowded campus shuttle, your favorite playlist for the walk, or the specific coffee shop you stop at every morning. Let\'s show the future how we moved!', '1772434979_photography.jfif', 'public', 'approved', '2026-03-02 07:02:59'),
(29, 27, 'delta@gmail.com', 'Snapshot of the 2025 Cafeteria Menu', 'I realized that food prices change every year. I took a high-res photo of the current cafeteria menu boards and the daily special. I think 2035 students will be shocked at how cheap (or expensive!) this looks to them.', '1772435246_cafeteria menu.jfif', 'private', 'approved', '2026-03-02 07:07:26'),
(30, 25, 'beta@gmail.com', '2,000 Artisanal Coffee from a Hidden Vending Machine?', 'I found this vending machine on the 4th floor of the Old Arts Building behind the heavy blue doors. It’s the only one on campus that serves \'Premium Roast\' for just $1. Most people think it’s broken, but if you hit the button twice, it works! It’s the best-kept secret for late-night study sessions.', '1772443178_KIRIN Vending Machine.jfif', 'private', 'pending_approval', '2026-03-02 09:19:38');

-- --------------------------------------------------------

--
-- Table structure for table `post_comments`
--

CREATE TABLE `post_comments` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_email` varchar(100) NOT NULL,
  `comment_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `post_likes`
--

CREATE TABLE `post_likes` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_email` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `post_likes`
--

INSERT INTO `post_likes` (`id`, `post_id`, `user_email`, `created_at`) VALUES
(11, 25, 'beta@gmail.com', '2026-03-02 06:11:21'),
(12, 24, 'beta@gmail.com', '2026-03-02 06:11:22'),
(13, 25, 'alpha@gmail.com', '2026-03-02 06:11:29'),
(14, 24, 'alpha@gmail.com', '2026-03-02 06:11:30'),
(15, 23, 'alpha@gmail.com', '2026-03-02 06:11:35'),
(16, 27, 'alpha@gmail.com', '2026-03-02 06:55:29');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `reporter_email` varchar(255) NOT NULL,
  `item_type` enum('material','post','user','community') NOT NULL,
  `item_id` int(11) NOT NULL,
  `reason_category` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `evidence_file` varchar(255) DEFAULT NULL,
  `status` enum('pending','under_review','resolved','dismissed') DEFAULT 'pending',
  `admin_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reports`
--

INSERT INTO `reports` (`id`, `reporter_email`, `item_type`, `item_id`, `reason_category`, `description`, `evidence_file`, `status`, `admin_note`, `created_at`, `updated_at`) VALUES
(26, 'epsilon@gmail.com', 'post', 25, 'Misinformation', 'There is no such shop', NULL, 'pending', NULL, '2026-03-02 09:51:41', '2026-03-02 09:51:41');

-- --------------------------------------------------------

--
-- Table structure for table `studymaterial`
--

CREATE TABLE `studymaterial` (
  `id` int(11) NOT NULL,
  `community_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('pdf','doc','ppt','video','image','other') DEFAULT 'other',
  `category` enum('notes','assignment','lecture','reference','exam','other') DEFAULT 'other',
  `original_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `uploaded_by` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') DEFAULT 'approved'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `studymaterial`
--

INSERT INTO `studymaterial` (`id`, `community_id`, `title`, `description`, `type`, `category`, `original_name`, `file_path`, `uploaded_by`, `uploaded_at`, `status`) VALUES
(24, 26, 'Data Structures and Algorithms Cheat Sheet', '', 'pdf', 'notes', 'DataStructures_Cheatsheet_Zero_To_Mastery_V1.01.pdf', '../uploads/1772434472_69a53428f0d13.pdf', 'alpha@gmail.com', '2026-03-02 06:54:32', 'approved');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` datetime DEFAULT current_timestamp(),
  `ban_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `fullname`, `email`, `google_id`, `password`, `role`, `created_at`, `last_activity`, `ban_until`) VALUES
(1, 'Admin', 'admin@gmail.com', NULL, '$2y$10$jBveO8gKqjmeDMD7F6s2Gu75.kRrs/Jo94rx0eglt.NOtKpAAmyAm', 'admin', '2026-02-27 05:01:41', '2026-03-02 17:14:53', NULL),
(2, 'Alpha', 'alpha@gmail.com', NULL, '$2y$10$t5L/5OqmWwitVQvRq5HAte0zjTugGcLjKmsPAc6vKKk8GYtJiMf6i', 'user', '2026-02-25 12:44:50', '2026-03-02 16:06:38', NULL),
(3, 'Beta', 'beta@gmail.com', NULL, '$2y$10$6TUv43MnDhlY.Dw1.E4Xh.Q5hiboVixrW0/q5K.xkhK2ejQ6a81di', 'user', '2026-02-25 12:47:56', '2026-03-02 17:14:03', '2026-02-28 09:41:00'),
(4, 'Gamma', 'gamma@gmail.com', NULL, '$2y$10$9EJLaNQ2FMruyZr.SAQYreIGLvXDkj/gpMAGoUSsDfu27XwqgOb/2', 'user', '2026-02-25 12:48:56', '2026-03-02 16:18:56', NULL),
(5, 'Delta', 'delta@gmail.com', NULL, '$2y$10$dumXXJbErrwsxum5P26M2uokOjYb4OoE2LC0AyeE25aXIvE8c5D.2', 'user', '2026-02-26 14:11:36', '2026-03-02 17:13:37', NULL),
(15, 'Epsilon', 'epsilon@gmail.com', NULL, '$2y$10$bhg7i9HPZHCpsS0Xu8vzxebBn8yMOlJRqBSdOpZUd2EdKWPhg6Ewy', 'user', '2026-03-02 08:49:22', '2026-03-02 17:13:42', NULL),
(16, 'Zeta', 'zeta@gmail.com', NULL, '$2y$10$ah0hVnRuQPE9RH8vSec.8eTL76vErcEXmjDWE4hVl4Y.twe5eibPe', 'user', '2026-03-02 09:15:59', '2026-03-02 16:19:19', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `communities`
--
ALTER TABLE `communities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `leader_email` (`leader_email`);

--
-- Indexes for table `community_bans`
--
ALTER TABLE `community_bans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `community_id` (`community_id`);

--
-- Indexes for table `community_exams`
--
ALTER TABLE `community_exams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_exams_community` (`community_id`),
  ADD KEY `idx_exams_date` (`exam_date`),
  ADD KEY `fk_exams_user` (`created_by`);

--
-- Indexes for table `community_files`
--
ALTER TABLE `community_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_files_community` (`community_id`),
  ADD KEY `idx_files_uploaded` (`uploaded_at`),
  ADD KEY `fk_files_user` (`uploaded_by`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `community_id` (`community_id`),
  ADD KEY `user_email` (`user_email`);

--
-- Indexes for table `membership_requests`
--
ALTER TABLE `membership_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `community_id` (`community_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `community_id` (`community_id`),
  ADD KEY `author_email` (`author_email`);

--
-- Indexes for table `post_comments`
--
ALTER TABLE `post_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indexes for table `post_likes`
--
ALTER TABLE `post_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_like` (`post_id`,`user_email`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reporter_email` (`reporter_email`);

--
-- Indexes for table `studymaterial`
--
ALTER TABLE `studymaterial`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sm_community` (`community_id`),
  ADD KEY `idx_sm_type` (`type`),
  ADD KEY `idx_sm_category` (`category`),
  ADD KEY `idx_sm_uploaded` (`uploaded_at`),
  ADD KEY `fk_sm_user` (`uploaded_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `communities`
--
ALTER TABLE `communities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `community_bans`
--
ALTER TABLE `community_bans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `community_exams`
--
ALTER TABLE `community_exams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `community_files`
--
ALTER TABLE `community_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `membership_requests`
--
ALTER TABLE `membership_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=284;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `post_comments`
--
ALTER TABLE `post_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `post_likes`
--
ALTER TABLE `post_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `studymaterial`
--
ALTER TABLE `studymaterial`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `communities`
--
ALTER TABLE `communities`
  ADD CONSTRAINT `communities_ibfk_1` FOREIGN KEY (`leader_email`) REFERENCES `users` (`email`) ON UPDATE CASCADE;

--
-- Constraints for table `community_bans`
--
ALTER TABLE `community_bans`
  ADD CONSTRAINT `community_bans_ibfk_1` FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `community_exams`
--
ALTER TABLE `community_exams`
  ADD CONSTRAINT `fk_exams_community` FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_exams_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`email`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `community_files`
--
ALTER TABLE `community_files`
  ADD CONSTRAINT `fk_files_community` FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_files_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`email`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `members`
--
ALTER TABLE `members`
  ADD CONSTRAINT `members_ibfk_1` FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `members_ibfk_2` FOREIGN KEY (`user_email`) REFERENCES `users` (`email`) ON UPDATE CASCADE;

--
-- Constraints for table `membership_requests`
--
ALTER TABLE `membership_requests`
  ADD CONSTRAINT `membership_requests_ibfk_1` FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `posts_ibfk_1` FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `posts_ibfk_2` FOREIGN KEY (`author_email`) REFERENCES `users` (`email`) ON UPDATE CASCADE;

--
-- Constraints for table `post_comments`
--
ALTER TABLE `post_comments`
  ADD CONSTRAINT `post_comments_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `post_likes`
--
ALTER TABLE `post_likes`
  ADD CONSTRAINT `post_likes_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`reporter_email`) REFERENCES `users` (`email`) ON DELETE CASCADE;

--
-- Constraints for table `studymaterial`
--
ALTER TABLE `studymaterial`
  ADD CONSTRAINT `fk_sm_community` FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sm_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`email`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
