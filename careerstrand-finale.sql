-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 11, 2026 at 10:16 PM
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
-- Database: `careerstrand`
--

-- --------------------------------------------------------

--
-- Table structure for table `application`
--

CREATE TABLE `application` (
  `applicationId` int(11) NOT NULL,
  `userId` int(11) DEFAULT NULL,
  `opportunityId` int(11) DEFAULT NULL,
  `appliedAt` datetime DEFAULT current_timestamp(),
  `status` varchar(50) DEFAULT 'pending',
  `motivation` text DEFAULT NULL,
  `compatibilityScore` int(11) DEFAULT NULL,
  `portfolio` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `application`
--

INSERT INTO `application` (`applicationId`, `userId`, `opportunityId`, `appliedAt`, `status`, `motivation`, `compatibilityScore`, `portfolio`) VALUES
(1, 127, 1, '2026-05-06 00:00:00', 'accepted', 'hjhjhj', 0, NULL),
(2, 125, 2, '2026-05-09 12:58:54', 'pending', 'test', 100, 'https://test.com'),
(3, 125, 1, '2026-05-09 12:59:12', 'pending', 'test', 80, 'https://test.com');

-- --------------------------------------------------------

--
-- Table structure for table `calendar`
--

CREATE TABLE `calendar` (
  `calendarId` int(11) NOT NULL,
  `userId` int(11) DEFAULT NULL,
  `courseId` int(11) DEFAULT NULL,
  `startDate` date DEFAULT NULL,
  `endDate` date DEFAULT NULL,
  `progress` int(11) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `calendar`
--

INSERT INTO `calendar` (`calendarId`, `userId`, `courseId`, `startDate`, `endDate`, `progress`, `status`) VALUES
(1, 1, 1, '2026-02-01', '2026-02-20', 60, 'ongoing'),
(2, 3, 2, '2026-03-01', '2026-03-30', 20, 'ongoing'),
(3, NULL, 3, '2026-05-10', '2026-05-11', 3, 'Ongoing');

-- --------------------------------------------------------

--
-- Table structure for table `challenge`
--

CREATE TABLE `challenge` (
  `challengeId` int(11) NOT NULL,
  `groupId` int(11) DEFAULT NULL,
  `managerId` int(11) DEFAULT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'task',
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `difficulty` varchar(50) DEFAULT NULL,
  `deadline` datetime DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `createdAt` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `commentId` int(11) NOT NULL,
  `postId` int(11) DEFAULT NULL,
  `userId` int(11) DEFAULT NULL,
  `parentCommentId` int(11) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `likesCount` int(11) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'active',
  `createdAt` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `course`
--

CREATE TABLE `course` (
  `courseId` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `skill` varchar(100) DEFAULT NULL,
  `difficulty` varchar(50) DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `createdAt` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course`
--

INSERT INTO `course` (`courseId`, `title`, `description`, `category`, `skill`, `difficulty`, `duration`, `status`, `createdAt`) VALUES
(1, 'Intro to C', 'Learn basics of C', 'Programming', 'C', 'Beginner', 20, 'active', '2026-01-01'),
(2, 'Advanced SQL', 'Master SQL queries', 'Database', 'SQL', 'Advanced', 30, 'active', '2026-02-01'),
(3, 'testt', 'tttttttttttttttttttttttttttttttt', 'Programming', 'Problem solving', 'Beginner', 2, 'Availeble', '2026-05-10');

-- --------------------------------------------------------

--
-- Table structure for table `course_videos`
--

CREATE TABLE `course_videos` (
  `video_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `video_path` varchar(255) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_videos`
--

INSERT INTO `course_videos` (`video_id`, `course_id`, `title`, `video_path`, `position`, `created_at`) VALUES
(1, 34, 'test', 'uploads/videos/video_69f8660fbcb002.01316207.mp4', 1, '0000-00-00 00:00:00'),
(2, 34, 'Chapitre 1', 'uploads/videos/vid_69f8686e587a68.08508639.mp4', 2, '0000-00-00 00:00:00'),
(3, 34, 'Chapitre 2', 'uploads/videos/vid_69f8686e5b04b0.78857718.mp4', 3, '0000-00-00 00:00:00'),
(4, 33, 'Chapitre 1', 'uploads/videos/vid_69f8789dc45b20.66642948.mp4', 1, '0000-00-00 00:00:00'),
(5, 35, 'Chapitre 1', 'uploads/videos/vid_69f9c330b31740.72839343.mp4', 1, '0000-00-00 00:00:00'),
(6, 35, 'Chapitre 2', 'uploads/videos/vid_69f9c330b5f851.34344412.mp4', 2, '0000-00-00 00:00:00'),
(7, 3, 'Chapitre 1', 'uploads/videos/vid_69ff9e7481af92.41322352.mp4', 1, '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `event`
--

CREATE TABLE `event` (
  `eventId` int(11) NOT NULL,
  `managerId` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `createdAt` date DEFAULT NULL,
  `sponsorId` int(11) DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `organiser` varchar(255) DEFAULT NULL,
  `time` time DEFAULT NULL,
  `eventMode` varchar(50) DEFAULT 'Online',
  `duration` int(11) DEFAULT 0,
  `formLink` varchar(255) DEFAULT NULL,
  `qrToken` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event`
--

INSERT INTO `event` (`eventId`, `managerId`, `title`, `description`, `type`, `location`, `date`, `capacity`, `status`, `createdAt`, `sponsorId`, `tags`, `organiser`, `time`, `eventMode`, `duration`, `formLink`, `qrToken`) VALUES
(2, 2, 'Workshop AI', 'AI basics', 'Workshop', 'Online', '2026-05-15', 50, 'Upcoming', '2026-04-02', NULL, NULL, NULL, '14:00:00', 'Online', 120, 'https://docs.google.com/forms/u/0/', '10777f8bade27769d79b815c01131f0a46fcb500f0c2426967e5cb15511bee6d'),
(3, 1, 'Portfolio Lab Live', 'Hands-on portfolio review session.', 'Workshop', 'Online', '2026-05-20', 100, 'Upcoming', '2025-07-01', NULL, 'portfolio,design,web', 'CareerStrand Team', '10:00:00', 'Online', 180, 'https://docs.google.com/forms/u/0/', '8866a43e6c3937fef9d09a85194f957f252f73136bf4bf9ec4dc330f0613b61f'),
(4, 1, 'Beginner Build Sprint', '42 teams around design, no-code, and web tracks.', 'Hackathon', 'Online', '2026-06-05', 80, 'Upcoming', '2025-07-05', NULL, 'hackathon,no-code,web', 'CareerStrand Team', '09:00:00', 'Online', 2880, 'https://docs.google.com/forms/u/0/', '2ad4800231bb79ea38c56d390954150f5b6f949f25f58cd233fc24e0f8938846'),
(5, 2, 'Junior Talent Connect', '12 managers joined to review beginner-ready profiles.', 'Career Event', 'Online', '2025-07-30', 50, 'Past', '2025-06-20', NULL, 'career,talent', 'HR Squad', '16:00:00', 'Online', 120, NULL, NULL),
(6, 2, 'CSS Mastery Bootcamp', 'Deep dive into modern CSS techniques.', 'Bootcamp', 'Online', '2026-05-25', 60, 'Upcoming', '2025-07-10', NULL, 'css,frontend', 'Dev Guild', '13:00:00', 'Online', 240, NULL, NULL),
(7, 1, 'AI & No-Code Expo', 'Explore AI tools for non-developers.', 'Workshop', 'Online', '2026-06-10', 120, 'Upcoming', '2025-07-15', NULL, 'AI,no-code', 'CareerStrand Team', '15:00:00', 'Online', 180, NULL, NULL),
(8, 3, 'Open Source Sprint', 'Community hackathon around open source tools.', 'Hackathon', 'Online', '2025-07-12', 200, 'Past', '2025-06-15', NULL, 'open-source,community', 'OSS Collective', '08:00:00', 'Online', 2880, NULL, NULL),
(9, 2, 'Resume Clinic', 'Live CV review with HR professionals.', 'Career Event', 'Sousse', '2025-10-01', 40, 'Past', '2025-07-20', 2, 'career,CV', 'HR Squad', '15:00:00', 'In-person', 180, NULL, NULL),
(72, 1, 'Next.js Masterclass', 'Learn modern React framework with hands-on projects.', 'Workshop', 'Online', '2026-06-15', 100, 'Upcoming', '2026-05-03', 1, 'nextjs,react,frontend', 'TechHub Team', '14:00:00', 'Online', 180, 'https://forms.gle/nextjs-masterclass', NULL),
(73, 2, 'AI for Beginners', 'Introduction to artificial intelligence and machine learning.', 'Bootcamp', 'Tunis', '2026-06-22', 50, 'Upcoming', '2026-05-03', 2, 'AI,ML,beginner', 'StartupBoost', '09:00:00', 'In-person', 480, 'https://forms.gle/ai-beginners', '2392847acfdcfcc33584c53105b52942614c6902e4e9008dc64721fe578d4a5e'),
(74, 1, 'Portfolio Night', 'Showcase your projects and get feedback from experts.', 'Career Event', 'Sousse', '2026-05-30', 80, 'Live', '2026-05-03', NULL, 'portfolio,networking', 'CareerStrand', '18:00:00', 'In-person', 240, NULL, NULL),
(75, 3, 'Hackathon 2026', '24-hour coding competition with prizes.', 'Hackathon', 'Online', '2026-07-10', 150, 'Upcoming', '2026-05-03', 14, 'hackathon,competition', 'CodeLab', '08:00:00', 'Online', 1440, 'https://forms.gle/hackathon2026', '5450cfda2a1489703f342f3422175c1bcdff1cd716dad4fe4e71932c6115acaa'),
(76, 2, 'UI/UX Design Sprint', 'Learn design thinking and prototyping.', 'Workshop', 'Remote', '2026-06-05', 60, 'Upcoming', '2026-05-03', 15, 'design,figma,ux', 'Webify', '10:00:00', 'Online', 360, 'https://forms.gle/uiux-sprint', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `eventform`
--

CREATE TABLE `eventform` (
  `formId` int(11) NOT NULL,
  `eventId` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `formLink` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `groupmember`
--

CREATE TABLE `groupmember` (
  `groupMemberId` int(11) NOT NULL,
  `groupId` int(11) DEFAULT NULL,
  `userId` int(11) DEFAULT NULL,
  `joinedAt` date DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `groupmember`
--

INSERT INTO `groupmember` (`groupMemberId`, `groupId`, `userId`, `joinedAt`, `status`) VALUES
(0, 6, 125, '2026-05-09', 'active'),
(2, 4, 123, '2026-04-27', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `managerprofile`
--

CREATE TABLE `managerprofile` (
  `managerProfileId` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `organization` varchar(255) NOT NULL,
  `categoryFocus` varchar(100) NOT NULL,
  `description` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `opportunity`
--

CREATE TABLE `opportunity` (
  `opportunityId` int(11) NOT NULL,
  `managerId` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `requiredLevel` varchar(50) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'draft',
  `createdAt` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `opportunity`
--

INSERT INTO `opportunity` (`opportunityId`, `managerId`, `title`, `description`, `type`, `category`, `deadline`, `requiredLevel`, `status`, `createdAt`) VALUES
(1, 1, 'fgfgf', 'fgfgfg', 'internship', 'Technical', '2026-06-06', 'Beginner', 'published', '2026-05-05 23:58:27'),
(2, 1, 'testskill', 'test', 'freelance', 'Leadership', '2026-06-06', 'Intermediate', 'published', '2026-05-09 12:51:54'),
(3, 1, 'testuser', 'test', 'volunteer', 'Communication', '2026-06-06', 'Advanced', 'published', '2026-05-09 13:01:12'),
(4, 127, 'testuser2', 'test', 'volunteer', 'Leadership', '2026-06-06', 'Advanced', 'published', '2026-05-09 13:06:18'),
(5, 127, 'bindinn', 'asdadasd', 'internship', 'Technical', '2026-05-14', 'Beginner', 'published', '2026-05-09 13:27:43'),
(6, 1, 'diddy', 'diddy', 'internship', 'Technical', '2026-06-07', 'Advanced', 'published', '2026-05-11 13:34:11');

-- --------------------------------------------------------

--
-- Table structure for table `opportunity_skill`
--

CREATE TABLE `opportunity_skill` (
  `id` int(11) NOT NULL,
  `opportunityId` int(11) NOT NULL,
  `skillName` varchar(100) NOT NULL,
  `requiredLevel` int(11) NOT NULL,
  `weight` float DEFAULT 1,
  `isPrimary` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `opportunity_skill`
--

INSERT INTO `opportunity_skill` (`id`, `opportunityId`, `skillName`, `requiredLevel`, `weight`, `isPrimary`) VALUES
(3, 1, 'Creativity', 50, 1, 1),
(4, 2, 'Figma', 80, 1, 1),
(5, 3, 'Creativity', 80, 1, 1),
(6, 4, 'Communication', 80, 1, 1),
(8, 6, 'Technical', 80, 1, 1),
(9, 5, 'Business', 20, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `participation`
--

CREATE TABLE `participation` (
  `participationId` int(11) NOT NULL,
  `userId` int(11) DEFAULT NULL,
  `eventId` int(11) DEFAULT NULL,
  `registrationDate` date DEFAULT NULL,
  `attendanceStatus` varchar(50) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `attendanceScanAt` datetime DEFAULT NULL,
  `sentiment` varchar(20) DEFAULT 'Neutral'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `participation`
--

INSERT INTO `participation` (`participationId`, `userId`, `eventId`, `registrationDate`, `attendanceStatus`, `status`, `rating`, `feedback`, `attendanceScanAt`, `sentiment`) VALUES
(6, 1, 2, '2026-05-10', 'Confirmed', 'Confirmed', NULL, NULL, '2026-05-04 20:08:56', 'Neutral'),
(7, 2, 3, '2026-05-12', 'Confirmed', 'Confirmed', NULL, '', NULL, 'Neutral'),
(9, 3, 5, '2025-07-25', 'Confirmed', 'Confirmed', 4, 'Very helpful session, met great people!', NULL, 'Positive'),
(10, 2, 6, '2025-07-08', 'Confirmed', 'Confirmed', 5, 'Amazing bootcamp, learned a lot!', NULL, 'Positive'),
(11, 1, 8, '2025-07-10', 'Confirmed', 'Confirmed', 4, 'Great community energy!', NULL, 'Positive'),
(12, 3, 2, '2026-05-18', 'Confirmed', 'Confirmed', NULL, NULL, NULL, 'Neutral'),
(14, 1, 9, '2025-09-28', 'Confirmed', 'Confirmed', 1, 'Very bad event!!', NULL, 'Negative'),
(15, 3, 4, '2026-05-22', 'Confirmed', 'Confirmed', NULL, '', NULL, 'Neutral'),
(19, 1, 75, '2026-05-04', 'Confirmed', 'Confirmed', NULL, 'Ali Ben Salah|ali@gmail.com', '2026-05-04 21:07:37', 'Neutral'),
(20, 3, 77, '2026-05-04', 'Confirmed', 'Confirmed', NULL, 'Leila Mansouri|leila@gmail.com', NULL, 'Neutral'),
(21, 3, 75, '2026-05-04', 'Confirmed', 'Confirmed', NULL, 'Leila Mansouri|leila@gmail.com', NULL, 'Neutral'),
(22, 1, 4, '2026-05-04', 'Cancelled', 'Cancelled', NULL, '', NULL, 'Neutral'),
(23, 1, 4, '2026-05-04', 'Confirmed', 'Confirmed', NULL, NULL, '2026-05-05 11:55:50', 'Neutral'),
(24, 4, 75, '2026-05-04', 'Confirmed', 'Confirmed', NULL, '', NULL, 'Neutral'),
(25, 4, 2, '2026-05-04', 'Pending', 'Pending', NULL, NULL, NULL, 'Neutral'),
(26, 4, 76, '2026-05-04', 'Pending', 'Pending', NULL, NULL, NULL, 'Neutral'),
(27, 1, 7, '2026-05-05', 'Confirmed', 'Confirmed', NULL, '', NULL, 'Neutral'),
(28, 125, 75, '2026-05-09', 'Confirmed', 'Confirmed', NULL, '', '2026-05-09 12:37:31', 'Neutral'),
(29, 125, 73, '2026-05-09', 'Confirmed', 'Confirmed', NULL, '', '2026-05-09 12:48:28', 'Neutral'),
(30, 140, 75, '2026-05-11', 'Confirmed', 'Confirmed', NULL, '', '2026-05-11 13:13:08', 'Neutral');

-- --------------------------------------------------------

--
-- Table structure for table `post`
--

CREATE TABLE `post` (
  `postId` int(11) NOT NULL,
  `groupId` int(11) DEFAULT NULL,
  `userId` int(11) DEFAULT NULL,
  `challengeId` int(11) DEFAULT NULL,
  `postType` varchar(50) NOT NULL DEFAULT 'hub_post',
  `title` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'active',
  `linkedUrl` varchar(255) DEFAULT NULL,
  `createdAt` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `post`
--

INSERT INTO `post` (`postId`, `groupId`, `userId`, `challengeId`, `postType`, `title`, `content`, `status`, `linkedUrl`, `createdAt`) VALUES
(0, 6, 125, NULL, 'question', 'is everyone seeing this ?', 'TEST TEST', 'active', NULL, '2026-05-09 13:41:30');

-- --------------------------------------------------------

--
-- Table structure for table `profile`
--

CREATE TABLE `profile` (
  `profileId` int(11) NOT NULL,
  `userId` int(11) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `photoUrl` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `preferences` varchar(255) DEFAULT NULL,
  `completionScore` int(11) DEFAULT NULL,
  `level` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `profile`
--

INSERT INTO `profile` (`profileId`, `userId`, `bio`, `photoUrl`, `location`, `preferences`, `completionScore`, `level`) VALUES
(1, 125, 'Bing chilling is an Intermediate UI/UX Designer who loves design and finds motivation in creative projects. He recently grew through videos on YouTube. To become a better AI developer, Bing aims to learn more about AI technologies and collaborate with oth', '', '', '', 55, 'Intermediate'),
(2, 133, 'As an Intermediate-level user specializing in Data / AI, I am motivated by the best AI opportunities and have grown through daily progress. To achieve my career goal of becoming a leader, I seek out projects that challenge me and foster g', '', '', '', 35, 'Beginner'),
(3, 127, 'ADN progression supervisor and opportunities audit manager.', '', '', '', 35, 'Beginner'),
(4, 134, 'This member is a highly skilled human resources expert specializing in the healthcare sector. With extensive experience and a strong focus on creativity and expertise, they aim to identify top talent and provide comprehensive career development opportunit', '', '', '', 35, 'Beginner'),
(5, 135, '', '', '', '', 20, 'Beginner'),
(6, 136, '', '', '', '', 20, 'Beginner'),
(9, 139, 'May Miaadi is a beginner-level manager recruiter specializing in Data / AI. She prefers teamwork and aims to become a full stock worker. May seeks professional development opportunities that focus on becoming a skilled developer.', '', 'tunisia', 'design', 55, 'Intermediate'),
(10, 140, 'Nour Nouri is an advanced user aiming to develop herself in marketing through product development and making people buy anything. Her goal is to move closer to her career goal by identifying opportunities that will help her achieve this.', '', '', '', 35, 'Beginner'),
(11, 1, 'Rabi3 Bouden is an Expert UI/UX Designer aiming to become a Full Stock Worker. He values opportunities that align with his career goals and is motivated by creative problem-solving.', '', '', '', 35, 'Beginner');

-- --------------------------------------------------------

--
-- Table structure for table `recruiterprofile`
--

CREATE TABLE `recruiterprofile` (
  `recruiterProfileId` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `companyName` varchar(255) NOT NULL,
  `jobTitle` varchar(100) NOT NULL,
  `industry` varchar(100) NOT NULL,
  `companyWebsite` varchar(255) DEFAULT NULL,
  `opportunityTypes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recruiterprofile`
--

INSERT INTO `recruiterprofile` (`recruiterProfileId`, `userId`, `companyName`, `jobTitle`, `industry`, `companyWebsite`, `opportunityTypes`) VALUES
(1, 134, 'Esprit', 'HR manager', 'Healthcare', 'https://test.com', 'All'),
(2, 139, 'TechCop', 'HR Manager', 'Software & IT', 'https://company.com', 'Job');

-- --------------------------------------------------------

--
-- Table structure for table `skillhub`
--

CREATE TABLE `skillhub` (
  `groupId` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `createdAt` date DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `skillhub`
--

INSERT INTO `skillhub` (`groupId`, `name`, `category`, `description`, `createdAt`, `status`) VALUES
(4, 'club', 'Frontend', 'firas', '2026-04-21', 'active'),
(6, 'Nutriminds', 'Communication', 'book stuff', '2026-05-05', 'active'),
(7, 'facebook', 'Frontend', 'mark', '2026-05-05', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `sponsor`
--

CREATE TABLE `sponsor` (
  `sponsorId` int(11) NOT NULL,
  `userId` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `contribution` varchar(255) DEFAULT NULL,
  `amount` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sponsor`
--

INSERT INTO `sponsor` (`sponsorId`, `userId`, `name`, `company`, `email`, `contribution`, `amount`) VALUES
(1, NULL, 'Karim', 'TechCorp', 'karim@tech.com', 'Funding', 5000),
(2, NULL, 'Nadia', 'AI Solutions', 'nadia@ai.com', 'Equipment', 2000),
(12, NULL, 'Ahmed Ben Ali', 'TechHub Tunisia', 'ahmed@techhub.tn', 'Venue & Equipment', 7500),
(13, NULL, 'Sarra Mansour', 'StartupBoost', 'sarra@startupboost.com', 'Catering & Marketing', 4200),
(14, NULL, 'Mehdi Khelil', 'CodeLab', 'mehdi@codelab.tn', 'Prizes for winners', 3000),
(15, 1, 'Nour Chaabane', 'Webify', 'nour@webify.tn', 'Software licenses', 2500);

-- --------------------------------------------------------

--
-- Table structure for table `submission`
--

CREATE TABLE `submission` (
  `submissionId` int(11) NOT NULL,
  `groupMemberId` int(11) DEFAULT NULL,
  `challengeId` int(11) DEFAULT NULL,
  `projectLink` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `submittedAt` date DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `submissionRank` int(11) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `userquestionnaire`
--

CREATE TABLE `userquestionnaire` (
  `id` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `question` varchar(255) NOT NULL,
  `answer` text NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `userquestionnaire`
--

INSERT INTO `userquestionnaire` (`id`, `userId`, `question`, `answer`, `createdAt`) VALUES
(1, 130, 'Main field', 'Web Development', '2026-05-06 00:43:28'),
(2, 130, 'Experience level', 'Expert', '2026-05-06 00:43:28'),
(3, 130, 'Strongest skills', 'PHP , Data Science , Epstein Science', '2026-05-06 00:43:28'),
(4, 130, 'Work style', 'I like creative problem solving', '2026-05-06 00:43:28'),
(5, 130, 'Career goal', 'be like my supreme leader epstein', '2026-05-06 00:43:28'),
(6, 130, 'What project or experience best proves your interest in Web Development?', 'bro i am simply the best', '2026-05-06 00:43:28'),
(7, 130, 'Which skill do you most want to improve next, and why?', 'gooning cause it\'s essential', '2026-05-06 00:43:28'),
(8, 130, 'What opportunity would help you move closer to your career goal?', 'supreme leader', '2026-05-06 00:43:28'),
(9, 130, 'aiBio', 'mahmoud is a Expert CareerStrand member focused on Web Development. They bring strengths in PHP , Data Science , Epstein Science. I like creative problem solving, and their main goal is to be like my supreme leader epstein. Their answers also show: bro i am simply the best gooning cause it\'s essential supreme leader', '2026-05-06 00:43:28'),
(19, 133, 'Main field', 'Data / AI', '2026-05-08 23:39:35'),
(20, 133, 'Experience level', 'Intermediate', '2026-05-08 23:39:35'),
(21, 133, 'Strongest skills', 'ai', '2026-05-08 23:39:35'),
(22, 133, 'Work style', 'I prefer working independently', '2026-05-08 23:39:35'),
(23, 133, 'Career goal', 'ai', '2026-05-08 23:39:35'),
(24, 133, 'What kind of work in Data / AI motivates you the most?', 'best ai', '2026-05-08 23:39:35'),
(25, 133, 'Which skill or habit has helped you grow recently?', 'daily progress', '2026-05-08 23:39:35'),
(26, 133, 'What opportunity would help you move closer to your career goal?', 'leader project', '2026-05-08 23:39:35'),
(27, 133, 'aiBio', 'As an Intermediate-level user specializing in Data / AI, I am motivated by the best AI opportunities and have grown through daily progress. To achieve my career goal of becoming a leader, I seek out projects that challenge me and foster growth.', '2026-05-08 23:39:35'),
(28, 125, 'Main field', 'UI/UX Design', '2026-05-09 12:19:48'),
(29, 125, 'Experience level', 'Intermediate', '2026-05-09 12:19:48'),
(30, 125, 'Strongest skills', 'ai', '2026-05-09 12:19:48'),
(31, 125, 'Work style', 'I prefer teamwork', '2026-05-09 12:19:48'),
(32, 125, 'Career goal', 'become best ai developper', '2026-05-09 12:19:48'),
(33, 125, 'What kind of work in UI/UX Design motivates you the most?', 'i love design', '2026-05-09 12:19:48'),
(34, 125, 'Which skill or habit has helped you grow recently?', 'videos online', '2026-05-09 12:19:48'),
(35, 125, 'What opportunity would help you move closer to your career goal?', 'still not sure', '2026-05-09 12:19:48'),
(36, 125, 'aiBio', 'Bing chilling is an Intermediate UI/UX Designer who loves design and finds motivation in creative projects. Recently, he has grown through videos on YouTube. To become a better AI developer, Bing aims to learn more about AI technologies and collaborate wi', '2026-05-09 12:19:48'),
(37, 134, 'Main field', 'Human Resources', '2026-05-09 12:24:16'),
(38, 134, 'Experience level', 'Expert', '2026-05-09 12:24:16'),
(39, 134, 'Strongest skills', 'healthcare', '2026-05-09 12:24:16'),
(40, 134, 'Work style', 'I like creative problem solving', '2026-05-09 12:24:16'),
(41, 134, 'Career goal', 'best healthcare', '2026-05-09 12:24:16'),
(42, 134, 'What qualities make a candidate stand out to you in Human Resources?', 'skills', '2026-05-09 12:24:16'),
(43, 134, 'How do you evaluate whether someone is ready for an opportunity?', 'expertise', '2026-05-09 12:24:16'),
(44, 134, 'What kind of professional growth do you want to create for candidates?', 'everything', '2026-05-09 12:24:16'),
(45, 134, 'aiBio', 'A skilled human resources manager with expertise in creating best practices within the healthcare sector, I excel at identifying top talent through my unique blend of creativity and expertise. My goal is to provide comprehensive career development opportu', '2026-05-09 12:24:16'),
(46, 139, 'Main field', 'Data / AI', '2026-05-11 12:49:02'),
(47, 139, 'Experience level', 'Beginner', '2026-05-11 12:49:02'),
(48, 139, 'Strongest skills', 'PHP', '2026-05-11 12:49:02'),
(49, 139, 'Work style', 'I prefer teamwork', '2026-05-11 12:49:02'),
(50, 139, 'Career goal', 'becaume a full stock worker', '2026-05-11 12:49:02'),
(51, 139, 'What qualities make a candidate stand out to you in Data / AI?', 'idk', '2026-05-11 12:49:02'),
(52, 139, 'How do you evaluate whether someone is ready for an opportunity?', 'idk', '2026-05-11 12:49:02'),
(53, 139, 'What kind of professional growth do you want to create for candidates?', 'be a good developper', '2026-05-11 12:49:02'),
(54, 139, 'aiBio', 'May Miaadi is a beginner-level manager recruiter specializing in Data / AI. She prefers teamwork and aims to become a full stock worker. May seeks professional development opportunities that focus on becoming a skilled developer.', '2026-05-11 12:49:02'),
(55, 140, 'Main field', 'Marketing', '2026-05-11 12:56:56'),
(56, 140, 'Experience level', 'Advanced', '2026-05-11 12:56:56'),
(57, 140, 'Strongest skills', 'marketing', '2026-05-11 12:56:56'),
(58, 140, 'Work style', 'I prefer teamwork', '2026-05-11 12:56:56'),
(59, 140, 'Career goal', 'to developpe myself on marketing', '2026-05-11 12:56:56'),
(60, 140, 'What kind of work in Marketing motivates you the most?', 'productes', '2026-05-11 12:56:56'),
(61, 140, 'Which skill or habit has helped you grow recently?', 'make people buy anything', '2026-05-11 12:56:56'),
(62, 140, 'What opportunity would help you move closer to your career goal?', 'idk', '2026-05-11 12:56:56'),
(63, 140, 'aiBio', 'Nour Nouri is an advanced user looking to develop herself in marketing through product development and making people buy anything. She aims to move closer to her career goal by identifying opportunities that will help her achieve this.', '2026-05-11 12:56:56'),
(64, 1, 'Main field', 'UI/UX Design', '2026-05-11 13:19:09'),
(65, 1, 'Experience level', 'Expert', '2026-05-11 13:19:09'),
(66, 1, 'Strongest skills', 'jhb', '2026-05-11 13:19:09'),
(67, 1, 'Work style', 'I like creative problem solving', '2026-05-11 13:19:09'),
(68, 1, 'Career goal', 'becaume a full stock worker', '2026-05-11 13:19:09'),
(69, 1, 'What kind of work in UI/UX Design motivates you the most?', 'ok', '2026-05-11 13:19:09'),
(70, 1, 'Which skill or habit has helped you grow recently?', 'ok', '2026-05-11 13:19:09'),
(71, 1, 'What opportunity would help you move closer to your career goal?', 'ok', '2026-05-11 13:19:09'),
(72, 1, 'aiBio', 'Rabi3 Bouden is an Expert UI/UX Designer aiming to become a Full Stock Worker. Motivated by creative problem-solving, he values opportunities that align with his career goals.', '2026-05-11 13:19:09');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `userId` int(11) NOT NULL,
  `fullName` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `createdAt` date DEFAULT NULL,
  `faceDescriptor` longtext DEFAULT NULL,
  `faceEnabled` tinyint(1) NOT NULL DEFAULT 0,
  `approvalStatus` varchar(50) DEFAULT 'approved',
  `rejectionReason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`userId`, `fullName`, `email`, `password`, `role`, `status`, `createdAt`, `faceDescriptor`, `faceEnabled`, `approvalStatus`, `rejectionReason`) VALUES
(1, 'rabi3 Bouden', 'rabi3@gmail.com', '$2y$10$J5HwBGJYneOWU7q6ziStxeSS9t/BhUc4Iwv0FcRvSluRhvo9sueFW', 'user', 'active', '2026-05-06', NULL, 0, 'approved', NULL),
(123, 'La pikassa', 'epsteinisland@gmail.com', '$2y$10$NaycjXbHzLEi/JypyE1ndeS8s7pZJ7mqcsBYZm/BHUH4uS/jisqoO', 'manager', 'active', '2026-04-06', NULL, 0, 'approved', NULL),
(125, 'Bing chilling', 'bing@gmail.com', '$2y$10$OGnOlF5J5sqU2br/d3yh2OzYFlxue1xJuRIE/pDsRsk65YdrajrGm', 'user', 'active', '2026-05-05', NULL, 0, 'approved', NULL),
(126, 'Firas Ben Abdallah', 'firas@gmail.com', 'password123', 'user', 'active', '2026-05-05', NULL, 0, 'approved', NULL),
(127, 'Admin Manager', 'admin@gmail.com', '$2y$10$gFH/9V2UW6I20511TMIIje9qdYAg43XRcS0AmKsgsOBpfJyJtYvqK', 'admin', 'active', '2026-05-05', NULL, 0, 'approved', NULL),
(130, 'mahmoud ben ali', 'mahmoud@gmail.com', '$2y$10$hZ/.2Wc0zeqqHbsQy7dg9O06ZzErGm9GwudtzoKP..CvbNtAX/Wse', 'user', 'active', '2026-05-06', NULL, 0, 'approved', NULL),
(131, 'test discord', 'testdiscord@gmail.com', '$2y$10$HqQdblCqq3fgQs3P7QI2x.aAtLEBlUHjQPUPeI4qv8wjFrUGe4aoa', 'user', 'active', '2026-05-09', NULL, 0, 'approved', NULL),
(133, 'testtttt test', 'testtt@gmail.com', '$2y$10$6P9cwPqy46ppFONbLX5uzumQkcIWej2G7Ag12g7gJVXbhGj0AHi8i', 'user', 'active', '2026-05-09', NULL, 0, 'approved', NULL),
(134, 'test manager', 'testmanager@gmail.com', '$2y$10$eg00yUuzsDdqk.Ne7mLO7.30GKTrnbCxzeYqFfM9lNC7vDwb96woe', 'manager recruiter', 'active', '2026-05-09', NULL, 0, 'approved', NULL),
(135, 'sdad dsa', 'sad@gmail.com', '$2y$10$2/fJn2tEFuFf2Zw5UvcI0eGkScofw731h3PwoFFCKrdLLMX2DsqWC', 'user', 'active', '2026-05-09', NULL, 0, 'approved', NULL),
(136, 'Firas Ben abdallah', 'bouk@gmail.com', '$2y$10$sWYV4CgvNOubF1JHb/DxFO8mez0fZJ/030t0mUDgC19AQu8d6x8bS', 'user', 'active', '2026-05-10', NULL, 0, 'approved', NULL),
(139, 'may miaadi', 'maymiaadi2003@gmail.com', '$2y$10$m7lovwqTY6BZ/XjNV5pDB.kujBhFwEwQ4CaLs/dAYO9IzOTGBevUG', 'manager recruiter', 'active', '2026-05-11', '[-0.1510012149810791,0.09282789379358292,0.001320699229836464,-0.16884469985961914,-0.10767779499292374,-0.1018596887588501,0.012689642608165741,-0.13061925768852234,0.17486914992332458,-0.13450665771961212,0.2095106840133667,-0.11062725633382797,-0.24697193503379822,-0.05998045951128006,-0.05886908620595932,0.24585482478141785,-0.20853464305400848,-0.16497810184955597,-0.11756984144449234,-0.08402826637029648,0.08279071748256683,0.0010417456505820155,0.006172849331051111,0.15014296770095825,-0.10165418684482574,-0.341077595949173,-0.07929429411888123,-0.08743810653686523,-0.07094521820545197,-0.04390880465507507,0.010290060192346573,0.11232422292232513,-0.17384570837020874,-0.06040415167808533,0.07084444910287857,0.11922026425600052,0.026858745142817497,-0.09028032422065735,0.18036724627017975,-0.03324153274297714,-0.22823430597782135,0.023430535569787025,0.16065825521945953,0.24805906414985657,0.1911962777376175,-0.017171943560242653,0.01115903165191412,-0.08945058286190033,0.1573321521282196,-0.21412073075771332,-0.006768133956938982,0.13839395344257355,0.0628347247838974,0.009133163839578629,0.1266850233078003,-0.1366075873374939,0.04890076816082001,0.18272216618061066,-0.245681494474411,-0.04131702706217766,0.07913199812173843,-0.0658797174692154,-0.08470695465803146,-0.144859179854393,0.1650378406047821,0.1148054376244545,-0.1610819697380066,-0.10504317283630371,0.22018392384052277,-0.13124555349349976,-0.004536016844213009,0.03961855173110962,-0.1788831651210785,-0.22144605219364166,-0.23865334689617157,0.0020832917653024197,0.3999518156051636,0.21394501626491547,-0.1415749043226242,0.07540816813707352,-0.00016429091920144856,0.004744750913232565,0.06263936311006546,0.1125640794634819,-0.026348043233156204,0.044878534972667694,-0.06719274818897247,0.0931539386510849,0.09741747379302979,0.03419537469744682,0.015518710017204285,0.27249908447265625,-0.004150474444031715,-0.012838552705943584,0.011498727835714817,0.016489462926983833,-0.1353839635848999,-0.016091030091047287,-0.21487730741500854,0.005100500304251909,-0.042466577142477036,-0.026833221316337585,-0.012166596949100494,0.0684218481183052,-0.1269863098859787,0.06373614817857742,-0.033792588859796524,-0.005547104869037867,-0.15856780111789703,0.00243844254873693,-0.02465902641415596,0.000002078653551507159,0.07417105883359909,-0.18936483561992645,0.09419479966163635,0.15579400956630707,-0.006845423951745033,0.14202292263507843,0.06100776791572571,0.023525120690464973,0.039453648030757904,-0.10519561171531677,-0.20552267134189606,-0.04641370475292206,0.11529805511236191,-0.054816652089357376,0.11868462711572647,-0.0034554714802652597]', 1, 'approved', NULL),
(140, 'nour nouri', 'nour@gmail.com', '$2y$10$Jv6/DLtE6U8JRbbQF49GF.qpJvCk369dkBiEEt.Du0gh3XuL0HyhW', 'manager', 'active', '2026-05-11', NULL, 0, 'approved', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `userskill`
--

CREATE TABLE `userskill` (
  `userSkillId` int(11) NOT NULL,
  `userId` int(11) DEFAULT NULL,
  `skillName` varchar(100) DEFAULT NULL,
  `level` tinyint(3) UNSIGNED DEFAULT NULL,
  `source` varchar(50) DEFAULT NULL,
  `certificateUrl` varchar(255) DEFAULT NULL,
  `validatedAt` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `userskill`
--

INSERT INTO `userskill` (`userSkillId`, `userId`, `skillName`, `level`, `source`, `certificateUrl`, `validatedAt`) VALUES
(1, 123, 'Frontend', 1, 'manual', NULL, '2026-05-04'),
(2, 125, 'something', 50, 'coursera', 'https://plantuml.com/', '2026-05-06'),
(1, 123, 'Frontend', 1, 'manual', NULL, '2026-05-04'),
(2, 125, 'something', 50, 'coursera', 'https://plantuml.com/', '2026-05-06'),
(1, 123, 'Frontend', 1, 'manual', NULL, '2026-05-04'),
(2, 125, 'something', 50, 'coursera', 'https://plantuml.com/', '2026-05-06'),
(1, 123, 'Frontend', 1, 'manual', NULL, '2026-05-04'),
(2, 125, 'something', 50, 'coursera', 'https://plantuml.com/', '2026-05-06'),
(0, 133, 'Data science', 50, 'Self taught', 'https://test.com', '2026-05-09'),
(0, 133, 'Creativity', 70, 'Coursera', 'https://test.com', '2026-05-09'),
(0, 133, 'Creativity', 70, 'Coursera', 'https://test.com', '2026-05-09'),
(0, 125, 'Creativity', 40, 'Self taught', 'https://test.com', '2026-05-09'),
(0, 125, 'Figma', 100, 'Self taught', 'https://test.com', '2026-05-09'),
(0, 139, 'UI/UX', 50, 'coursera', 'https://plantuml.com/', '2024-06-18');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `application`
--
ALTER TABLE `application`
  ADD PRIMARY KEY (`applicationId`),
  ADD KEY `fk_applications_user` (`userId`),
  ADD KEY `fk_applications_opportunity` (`opportunityId`);

--
-- Indexes for table `calendar`
--
ALTER TABLE `calendar`
  ADD PRIMARY KEY (`calendarId`),
  ADD KEY `fk_calendar_user` (`userId`),
  ADD KEY `fk_calendar_course` (`courseId`);

--
-- Indexes for table `challenge`
--
ALTER TABLE `challenge`
  ADD PRIMARY KEY (`challengeId`),
  ADD KEY `fk_challenge_group` (`groupId`),
  ADD KEY `fk_challenge_manager` (`managerId`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`commentId`),
  ADD KEY `fk_comments_post` (`postId`),
  ADD KEY `fk_comments_user` (`userId`),
  ADD KEY `fk_comments_parent` (`parentCommentId`);

--
-- Indexes for table `course`
--
ALTER TABLE `course`
  ADD PRIMARY KEY (`courseId`);

--
-- Indexes for table `course_videos`
--
ALTER TABLE `course_videos`
  ADD PRIMARY KEY (`video_id`),
  ADD KEY `idx_course_videos_course` (`course_id`);

--
-- Indexes for table `event`
--
ALTER TABLE `event`
  ADD PRIMARY KEY (`eventId`),
  ADD KEY `fk_events_manager` (`managerId`);

--
-- Indexes for table `eventform`
--
ALTER TABLE `eventform`
  ADD PRIMARY KEY (`formId`),
  ADD UNIQUE KEY `eventId` (`eventId`);

--
-- Indexes for table `groupmember`
--
ALTER TABLE `groupmember`
  ADD PRIMARY KEY (`groupMemberId`),
  ADD KEY `fk_groupmember_group` (`groupId`),
  ADD KEY `fk_groupmember_user` (`userId`);

--
-- Indexes for table `managerprofile`
--
ALTER TABLE `managerprofile`
  ADD PRIMARY KEY (`managerProfileId`),
  ADD UNIQUE KEY `userId` (`userId`);

--
-- Indexes for table `opportunity`
--
ALTER TABLE `opportunity`
  ADD PRIMARY KEY (`opportunityId`),
  ADD KEY `fk_opportunity_manager` (`managerId`);

--
-- Indexes for table `opportunity_skill`
--
ALTER TABLE `opportunity_skill`
  ADD PRIMARY KEY (`id`),
  ADD KEY `opportunityId` (`opportunityId`);

--
-- Indexes for table `participation`
--
ALTER TABLE `participation`
  ADD PRIMARY KEY (`participationId`),
  ADD KEY `fk_participation_user` (`userId`),
  ADD KEY `fk_participation_event` (`eventId`);

--
-- Indexes for table `post`
--
ALTER TABLE `post`
  ADD PRIMARY KEY (`postId`),
  ADD KEY `fk_post_group` (`groupId`),
  ADD KEY `fk_post_user` (`userId`),
  ADD KEY `fk_post_challenge` (`challengeId`);

--
-- Indexes for table `profile`
--
ALTER TABLE `profile`
  ADD PRIMARY KEY (`profileId`),
  ADD UNIQUE KEY `userId` (`userId`);

--
-- Indexes for table `recruiterprofile`
--
ALTER TABLE `recruiterprofile`
  ADD PRIMARY KEY (`recruiterProfileId`),
  ADD UNIQUE KEY `userId` (`userId`);

--
-- Indexes for table `skillhub`
--
ALTER TABLE `skillhub`
  ADD PRIMARY KEY (`groupId`);

--
-- Indexes for table `sponsor`
--
ALTER TABLE `sponsor`
  ADD PRIMARY KEY (`sponsorId`);

--
-- Indexes for table `userquestionnaire`
--
ALTER TABLE `userquestionnaire`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_userquestionnaire_user` (`userId`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`userId`),
  ADD UNIQUE KEY `unique_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `application`
--
ALTER TABLE `application`
  MODIFY `applicationId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `calendar`
--
ALTER TABLE `calendar`
  MODIFY `calendarId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `course_videos`
--
ALTER TABLE `course_videos`
  MODIFY `video_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `event`
--
ALTER TABLE `event`
  MODIFY `eventId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

--
-- AUTO_INCREMENT for table `opportunity`
--
ALTER TABLE `opportunity`
  MODIFY `opportunityId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `opportunity_skill`
--
ALTER TABLE `opportunity_skill`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `participation`
--
ALTER TABLE `participation`
  MODIFY `participationId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `profile`
--
ALTER TABLE `profile`
  MODIFY `profileId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `recruiterprofile`
--
ALTER TABLE `recruiterprofile`
  MODIFY `recruiterProfileId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sponsor`
--
ALTER TABLE `sponsor`
  MODIFY `sponsorId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `userquestionnaire`
--
ALTER TABLE `userquestionnaire`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `userId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=141;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `recruiterprofile`
--
ALTER TABLE `recruiterprofile`
  ADD CONSTRAINT `recruiterprofile_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `users` (`userId`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
