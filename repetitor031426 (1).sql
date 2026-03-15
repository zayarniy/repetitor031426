р-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1
-- Время создания: Мар 14 2026 г., 23:42
-- Версия сервера: 10.4.32-MariaDB
-- Версия PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `repetitor031426`
--

-- --------------------------------------------------------

--
-- Структура таблицы `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `color` varchar(7) DEFAULT '#808080',
  `is_hidden` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `categories`
--

INSERT INTO `categories` (`id`, `user_id`, `name`, `color`, `is_hidden`, `sort_order`, `created_at`, `updated_at`) VALUES
(2, 1, 'ЕГЭ информатика 2026', '#667eea', 0, 0, '2026-03-14 20:13:55', '2026-03-14 20:13:55'),
(3, 1, 'Информатика', '#667eea', 0, 0, '2026-03-14 20:27:32', '2026-03-14 20:27:32'),
(4, 1, 'Яндекс. Учебник. Программирование на Python', '#e8d264', 0, 0, '2026-03-14 20:57:34', '2026-03-14 20:57:34');

-- --------------------------------------------------------

--
-- Структура таблицы `diaries`
--

CREATE TABLE `diaries` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `lesson_cost` decimal(10,2) DEFAULT NULL,
  `lesson_duration` int(11) DEFAULT NULL,
  `public_link` varchar(100) DEFAULT NULL,
  `is_public` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `diaries`
--

INSERT INTO `diaries` (`id`, `user_id`, `student_id`, `category_id`, `name`, `description`, `lesson_cost`, `lesson_duration`, `public_link`, `is_public`, `created_at`, `updated_at`) VALUES
(1, 1, 1, NULL, 'Худяков Василий - подготовка к внутреннему экзамену', '', 2500.00, 90, NULL, 0, '2026-03-14 18:47:23', '2026-03-14 18:47:23'),
(2, 1, 2, NULL, 'Ходаков Роман', '', 2000.00, 120, NULL, 0, '2026-03-14 19:17:09', '2026-03-14 19:17:09');

-- --------------------------------------------------------

--
-- Структура таблицы `diary_comments`
--

CREATE TABLE `diary_comments` (
  `id` int(11) NOT NULL,
  `diary_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `homework_tasks`
--

CREATE TABLE `homework_tasks` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `lesson_id` int(11) DEFAULT NULL,
  `task_text` text NOT NULL,
  `labels` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `homework_tasks`
--

INSERT INTO `homework_tasks` (`id`, `user_id`, `lesson_id`, `task_text`, `labels`, `created_at`, `updated_at`) VALUES
(1, 1, 4, 'Прочитать презентации в Яндекс.Учебнике', NULL, '2026-03-14 21:18:34', '2026-03-14 21:18:34');

-- --------------------------------------------------------

--
-- Структура таблицы `import_export_log`
--

CREATE TABLE `import_export_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `operation_type` enum('import','export') NOT NULL,
  `module_type` enum('students','categories','topics','labels','resources','diaries') NOT NULL,
  `file_format` enum('csv','json') NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `status` enum('pending','success','error') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `labels`
--

CREATE TABLE `labels` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `label_type` enum('resource','topic','student','lesson','general') DEFAULT 'general',
  `is_hidden` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `lessons`
--

CREATE TABLE `lessons` (
  `id` int(11) NOT NULL,
  `diary_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `lesson_date` date NOT NULL,
  `start_time` time DEFAULT '12:00:00',
  `duration` int(11) DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `topics_manual` text DEFAULT NULL,
  `homework_manual` text DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `link_url` varchar(500) DEFAULT NULL,
  `link_comment` varchar(255) DEFAULT NULL,
  `grade_lesson` int(11) DEFAULT NULL CHECK (`grade_lesson` between 0 and 5),
  `grade_comment` text DEFAULT NULL,
  `grade_homework` int(11) DEFAULT NULL CHECK (`grade_homework` between 0 and 5),
  `homework_comment` text DEFAULT NULL,
  `is_cancelled` tinyint(1) DEFAULT 0,
  `is_completed` tinyint(1) DEFAULT 0,
  `is_paid` tinyint(1) DEFAULT 0,
  `public_link` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `lessons`
--

INSERT INTO `lessons` (`id`, `diary_id`, `student_id`, `lesson_date`, `start_time`, `duration`, `cost`, `topics_manual`, `homework_manual`, `comment`, `link_url`, `link_comment`, `grade_lesson`, `grade_comment`, `grade_homework`, `homework_comment`, `is_cancelled`, `is_completed`, `is_paid`, `public_link`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '2026-03-15', '09:30:00', 90, 2500.00, '', '', '', '', '', 0, '', 0, '', 0, 0, 0, NULL, '2026-03-14 19:04:01', '2026-03-14 20:39:59'),
(2, 2, 2, '2026-03-15', '11:00:00', 120, 2000.00, '', '', '', '', '', NULL, '', NULL, '', 0, 0, 0, NULL, '2026-03-14 19:29:33', '2026-03-14 19:29:33'),
(3, 1, 1, '2026-02-22', '15:00:00', 90, 2500.00, '', '', '', '', '', 0, '', NULL, '', 0, 1, 1, NULL, '2026-03-14 20:18:52', '2026-03-14 20:22:07'),
(4, 1, 1, '2026-03-01', '15:00:00', 90, 2500.00, '', 'Прочитать презентации в Яндекс.Учебнике', '', '', '', 0, '', 2, '', 0, 1, 1, NULL, '2026-03-14 20:23:40', '2026-03-14 21:18:34'),
(5, 1, 1, '2026-03-07', '15:00:00', 30, 2500.00, '', '', 'Василий не вышел, но мама оплатила занятие. Мы позанимались лишний час не следующем занятии', '', '', 0, '', 0, '', 1, 0, 1, NULL, '2026-03-14 20:25:47', '2026-03-14 20:41:26'),
(6, 1, 1, '2026-03-09', '13:00:00', 150, 2500.00, '', '', '', 'https://disk.yandex.ru/i/nDhYETxcOAXd5g', 'Презентация по СС Полякова', 0, '', 0, '', 0, 1, 1, NULL, '2026-03-14 20:26:45', '2026-03-14 20:33:11');

-- --------------------------------------------------------

--
-- Структура таблицы `lesson_labels`
--

CREATE TABLE `lesson_labels` (
  `lesson_id` int(11) NOT NULL,
  `label_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `lesson_resources`
--

CREATE TABLE `lesson_resources` (
  `id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `resource_id` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `lesson_topics`
--

CREATE TABLE `lesson_topics` (
  `lesson_id` int(11) NOT NULL,
  `topic_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `lesson_topics`
--

INSERT INTO `lesson_topics` (`lesson_id`, `topic_id`, `created_at`) VALUES
(1, 2, '2026-03-14 20:39:59'),
(1, 29, '2026-03-14 20:39:59'),
(3, 1, '2026-03-14 20:22:07'),
(4, 29, '2026-03-14 21:18:34'),
(4, 36, '2026-03-14 21:18:34'),
(4, 37, '2026-03-14 21:18:34'),
(4, 39, '2026-03-14 21:18:34'),
(4, 40, '2026-03-14 21:18:34'),
(4, 45, '2026-03-14 21:18:34'),
(6, 29, '2026-03-14 20:33:11'),
(6, 35, '2026-03-14 20:33:11');

-- --------------------------------------------------------

--
-- Структура таблицы `parents`
--

CREATE TABLE `parents` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `relationship` varchar(50) DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `messenger_contact` varchar(255) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `resources`
--

CREATE TABLE `resources` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `url` varchar(500) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('page','document','video','audio','other') DEFAULT 'page',
  `labels` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `resource_labels`
--

CREATE TABLE `resource_labels` (
  `resource_id` int(11) NOT NULL,
  `label_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `statistics`
--

CREATE TABLE `statistics` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `period_type` enum('day','week','month','year') NOT NULL,
  `period_date` date NOT NULL,
  `total_lessons` int(11) DEFAULT 0,
  `completed_lessons` int(11) DEFAULT 0,
  `cancelled_lessons` int(11) DEFAULT 0,
  `total_income` decimal(10,2) DEFAULT 0.00,
  `paid_income` decimal(10,2) DEFAULT 0.00,
  `average_grade` decimal(3,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `class` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `lesson_cost` decimal(10,2) DEFAULT NULL,
  `lesson_duration` int(11) DEFAULT NULL,
  `lessons_per_week` int(11) DEFAULT NULL,
  `goals` text DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `messenger1` varchar(255) DEFAULT NULL,
  `messenger2` varchar(255) DEFAULT NULL,
  `messenger3` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `students`
--

INSERT INTO `students` (`id`, `user_id`, `first_name`, `last_name`, `middle_name`, `class`, `phone`, `lesson_cost`, `lesson_duration`, `lessons_per_week`, `goals`, `birth_date`, `start_date`, `end_date`, `city`, `email`, `messenger1`, `messenger2`, `messenger3`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Василий', 'Худяков', '', '', '', 2500.00, 90, 1, 'Подготовка к экзамену в МГИМО', NULL, NULL, NULL, '', '', '', '', '', 1, '2026-03-14 18:38:28', '2026-03-14 18:38:28'),
(2, 1, 'Роман', 'Ходаков', '', '9', '', 2000.00, 120, 1, 'Помощь в учебе. Хорошая оценка на экзамене', '2010-01-08', NULL, NULL, '', '', '', '', '', 1, '2026-03-14 19:06:02', '2026-03-14 19:06:02');

-- --------------------------------------------------------

--
-- Структура таблицы `student_comments`
--

CREATE TABLE `student_comments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `student_history`
--

CREATE TABLE `student_history` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `change_type` enum('create','update','delete','activate','deactivate') NOT NULL,
  `old_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_data`)),
  `new_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_data`)),
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `student_history`
--

INSERT INTO `student_history` (`id`, `student_id`, `user_id`, `change_type`, `old_data`, `new_data`, `changed_at`) VALUES
(1, 1, 1, 'create', NULL, '{\"last_name\":\"\\u0425\\u0443\\u0434\\u044f\\u043a\\u043e\\u0432\",\"first_name\":\"\\u0412\\u0430\\u0441\\u0438\\u043b\\u0438\\u0439\",\"middle_name\":\"\",\"class\":\"\",\"phone\":\"\",\"email\":\"\",\"lesson_cost\":\"2500\",\"lesson_duration\":\"90\",\"lessons_per_week\":\"1\",\"goals\":\"\\u041f\\u043e\\u0434\\u0433\\u043e\\u0442\\u043e\\u0432\\u043a\\u0430 \\u043a \\u044d\\u043a\\u0437\\u0430\\u043c\\u0435\\u043d\\u0443 \\u0432 \\u041c\\u0413\\u0418\\u041c\\u041e\",\"birth_date\":\"\",\"start_date\":\"\",\"end_date\":\"\",\"city\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"save_student\":\"\"}', '2026-03-14 18:38:28'),
(2, 2, 1, 'create', NULL, '{\"last_name\":\"\\u0425\\u043e\\u0434\\u0430\\u043a\\u043e\\u0432\",\"first_name\":\"\\u0420\\u043e\\u043c\\u0430\\u043d\",\"middle_name\":\"\",\"class\":\"9\",\"phone\":\"\",\"email\":\"\",\"lesson_cost\":\"2000\",\"lesson_duration\":\"120\",\"lessons_per_week\":\"1\",\"goals\":\"\\u041f\\u043e\\u043c\\u043e\\u0449\\u044c \\u0432 \\u0443\\u0447\\u0435\\u0431\\u0435. \\u0425\\u043e\\u0440\\u043e\\u0448\\u0430\\u044f \\u043e\\u0446\\u0435\\u043d\\u043a\\u0430 \\u043d\\u0430 \\u044d\\u043a\\u0437\\u0430\\u043c\\u0435\\u043d\\u0435\",\"birth_date\":\"2010-01-08\",\"start_date\":\"\",\"end_date\":\"\",\"city\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"save_student\":\"\"}', '2026-03-14 19:06:02'),
(3, 2, 1, 'update', '{\"id\":2,\"user_id\":1,\"first_name\":\"\\u0420\\u043e\\u043c\\u0430\\u043d\",\"last_name\":\"\\u0425\\u043e\\u0434\\u0430\\u043a\\u043e\\u0432\",\"middle_name\":\"\",\"class\":\"9\",\"phone\":\"\",\"lesson_cost\":\"2000.00\",\"lesson_duration\":120,\"lessons_per_week\":1,\"goals\":\"\\u041f\\u043e\\u043c\\u043e\\u0449\\u044c \\u0432 \\u0443\\u0447\\u0435\\u0431\\u0435. \\u0425\\u043e\\u0440\\u043e\\u0448\\u0430\\u044f \\u043e\\u0446\\u0435\\u043d\\u043a\\u0430 \\u043d\\u0430 \\u044d\\u043a\\u0437\\u0430\\u043c\\u0435\\u043d\\u0435\",\"birth_date\":\"2010-01-08\",\"start_date\":null,\"end_date\":null,\"city\":\"\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"is_active\":1,\"created_at\":\"2026-03-14 22:06:02\",\"updated_at\":\"2026-03-14 22:06:02\"}', '{\"id\":2,\"user_id\":1,\"first_name\":\"\\u0420\\u043e\\u043c\\u0430\\u043d\",\"last_name\":\"\\u0425\\u043e\\u0434\\u0430\\u043a\\u043e\\u0432\",\"middle_name\":\"\",\"class\":\"9\",\"phone\":\"\",\"lesson_cost\":\"2000.00\",\"lesson_duration\":120,\"lessons_per_week\":1,\"goals\":\"\\u041f\\u043e\\u043c\\u043e\\u0449\\u044c \\u0432 \\u0443\\u0447\\u0435\\u0431\\u0435. \\u0425\\u043e\\u0440\\u043e\\u0448\\u0430\\u044f \\u043e\\u0446\\u0435\\u043d\\u043a\\u0430 \\u043d\\u0430 \\u044d\\u043a\\u0437\\u0430\\u043c\\u0435\\u043d\\u0435\",\"birth_date\":\"2010-01-08\",\"start_date\":null,\"end_date\":null,\"city\":\"\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"is_active\":1,\"created_at\":\"2026-03-14 22:06:02\",\"updated_at\":\"2026-03-14 22:06:02\"}', '2026-03-14 19:16:34');

-- --------------------------------------------------------

--
-- Структура таблицы `student_labels`
--

CREATE TABLE `student_labels` (
  `student_id` int(11) NOT NULL,
  `label_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `topics`
--

CREATE TABLE `topics` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `topics`
--

INSERT INTO `topics` (`id`, `user_id`, `category_id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 1, 2, '3. Базы данных. Файловая система', 'ЕГЭ 2026', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(2, 1, 2, '4. Кодирование и декодирование информации', 'ЕГЭ 2027', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(3, 1, 2, '5. Анализ и построение алгоритмов для исполнителей', 'ЕГЭ 2028', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(4, 1, 2, '6. Анализ программ', 'ЕГЭ 2029', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(5, 1, 2, '7. Кодирование и декодирование информации. Передача информации', 'ЕГЭ 2030', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(6, 1, 2, '8. Перебор слов и системы счисления', 'ЕГЭ 2031', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(7, 1, 2, '9. Эксель в Excel', 'ЕГЭ 2032', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(8, 1, 2, '9. Эксель в Python', 'ЕГЭ 2033', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(9, 1, 2, '10. Поиск символов в текстовом редакторе', 'ЕГЭ 2034', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(10, 1, 2, '11. Вычисление количества информации', 'ЕГЭ 2035', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(11, 1, 2, '12. Выполнение алгоритмов для исполнителей', 'ЕГЭ 2036', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(12, 1, 2, '13. IP адреса', 'ЕГЭ 2037', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(13, 1, 2, '14. Кодирование чисел. Системы счисления', 'ЕГЭ 2038', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(14, 1, 2, '15. Преобразование логических выражений', 'ЕГЭ 2039', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(15, 1, 2, '16. Рекурсивные алгоритмы', 'ЕГЭ 2040', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(16, 1, 2, '17. Проверка на делимость', 'ЕГЭ 2041', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(17, 1, 2, '18. Робот-сборщик монет', 'ЕГЭ 2042', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(18, 1, 2, '19. Выигрышная стратегия. Задание 1', 'ЕГЭ 2043', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(19, 1, 2, '20. Выигрышная стратегия. Задание 2', 'ЕГЭ 2044', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(20, 1, 2, '21. Выигрышная стратегия. Задание 3', 'ЕГЭ 2045', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(21, 1, 2, '22. Анализ программы с циклами и условными операторами', 'ЕГЭ 2046', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(22, 1, 2, '22. Посимвольная обработка восьмеричных чисел', 'ЕГЭ 2047', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(23, 1, 2, '22. Многопроцессорные системы', 'ЕГЭ 2048', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(24, 1, 2, '23. Количество программ', 'ЕГЭ 2049', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(25, 1, 2, '24. Обработка символьных строк', 'ЕГЭ 2050', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(26, 1, 2, '25. Обработка целочисленной информации', 'ЕГЭ 2051', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(27, 1, 2, '26. Обработка целочисленной информации', 'ЕГЭ 2052', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(28, 1, 2, '27. Программирование', 'ЕГЭ 2053', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(29, 1, 2, 'Яндекс. Учебник. Программирование', 'ЕГЭ 2054', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(30, 1, 2, 'Программирование', 'ЕГЭ 2055', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(31, 1, 2, 'КЕГЭ', 'ЕГЭ 2056', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(32, 1, 2, 'Повторение', 'ЕГЭ 2057', '2026-03-14 20:14:15', '2026-03-14 20:14:15'),
(33, 1, 2, '1. Анализ информационных моделей', '', '2026-03-14 20:24:02', '2026-03-14 20:24:02'),
(34, 1, 2, '2. Построение таблиц истинности логических выражений', '', '2026-03-14 20:24:32', '2026-03-14 20:24:32'),
(35, 1, 3, 'Системы счисления', '', '2026-03-14 20:27:56', '2026-03-14 20:27:56'),
(36, 1, 4, 'Ввод/вывод и арифметика', '', '2026-03-14 21:11:21', '2026-03-14 21:11:21'),
(37, 1, 4, 'Введение в программирование', '', '2026-03-14 21:11:21', '2026-03-14 21:11:21'),
(38, 1, 4, 'Вывод, типы данных и переменные', '', '2026-03-14 21:11:21', '2026-03-14 21:11:21'),
(39, 1, 4, 'Арифметика строк', '', '2026-03-14 21:11:21', '2026-03-14 21:11:21'),
(40, 1, 4, 'Арифметика чисел', '', '2026-03-14 21:11:21', '2026-03-14 21:11:21'),
(41, 1, 4, 'Разбор задач', '', '2026-03-14 21:11:21', '2026-03-14 21:11:21'),
(42, 1, 4, 'Ветвление, условный оператор', '', '2026-03-14 21:11:21', '2026-03-14 21:11:21'),
(43, 1, 4, 'Условный оператор, операции сравнения', '', '2026-03-14 21:11:21', '2026-03-14 21:11:21'),
(44, 1, 4, 'Составные условия, логический тип', '', '2026-03-14 21:11:21', '2026-03-14 21:11:21'),
(45, 1, 4, 'Арифметика и условия', '', '2026-03-14 21:11:21', '2026-03-14 21:11:21'),
(46, 1, 4, 'Цикл с параметром', '', '2026-03-14 21:11:21', '2026-03-14 21:11:21'),
(47, 1, 4, 'Переменная цикла for', '', '2026-03-14 21:11:21', '2026-03-14 21:11:21'),
(48, 1, 4, 'Варианты цикла for', '', '2026-03-14 21:11:21', '2026-03-14 21:11:21'),
(49, 1, 4, 'Цикл WHILE', '', '2026-03-14 21:11:21', '2026-03-14 21:11:21'),
(50, 1, 4, 'Индексы строк', '', '2026-03-14 21:11:21', '2026-03-14 21:11:21'),
(51, 1, 4, 'Срезы строк', '', '2026-03-14 21:11:21', '2026-03-14 21:11:21'),
(52, 1, 4, 'Сравнение строк', '', '2026-03-14 21:11:21', '2026-03-14 21:11:21'),
(53, 1, 4, 'Методы строк', '', '2026-03-14 21:11:21', '2026-03-14 21:11:21'),
(54, 1, 4, 'Функции. Python', '', '2026-03-14 21:11:21', '2026-03-14 21:11:21'),
(55, 1, 4, 'Массивы и основные операции с ними', '', '2026-03-14 21:11:21', '2026-03-14 21:11:21'),
(56, 1, 4, 'Добавление элементов в массив', '', '2026-03-14 21:11:21', '2026-03-14 21:11:21'),
(57, 1, 4, 'Индексы элементов, изменение массива, срезы', '', '2026-03-14 21:11:21', '2026-03-14 21:11:21'),
(58, 1, 4, 'Два типа циклов по массиву', '', '2026-03-14 21:11:21', '2026-03-14 21:11:21'),
(59, 1, 4, 'Задача поиска элемента и нахождения максимального значения', '', '2026-03-14 21:11:21', '2026-03-14 21:11:21'),
(60, 1, 4, 'Использование массивов для решения задач', '', '2026-03-14 21:11:21', '2026-03-14 21:11:21');

-- --------------------------------------------------------

--
-- Структура таблицы `topic_labels`
--

CREATE TABLE `topic_labels` (
  `topic_id` int(11) NOT NULL,
  `label_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `topic_links`
--

CREATE TABLE `topic_links` (
  `id` int(11) NOT NULL,
  `topic_id` int(11) NOT NULL,
  `url` varchar(500) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_admin` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_token_expires` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `first_name`, `last_name`, `middle_name`, `phone`, `is_active`, `is_admin`, `created_at`, `updated_at`, `last_login`, `reset_token`, `reset_token_expires`) VALUES
(1, 'admin', 'admin@example.com', '$2y$10$SKAL/j7Pg0fPn4QNkjTCmO9jBEag7ktCW6gT2MBDaiRwvLS64mOlG', 'Admin', 'User', NULL, NULL, 1, 1, '2026-03-14 17:33:31', '2026-03-14 17:33:31', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_category_per_user` (`user_id`,`name`);

--
-- Индексы таблицы `diaries`
--
ALTER TABLE `diaries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `public_link` (`public_link`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_diary_created` (`created_at`),
  ADD KEY `student_id` (`student_id`);

--
-- Индексы таблицы `diary_comments`
--
ALTER TABLE `diary_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `diary_id` (`diary_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `homework_tasks`
--
ALTER TABLE `homework_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `lesson_id` (`lesson_id`);
ALTER TABLE `homework_tasks` ADD FULLTEXT KEY `idx_homework_search` (`task_text`);

--
-- Индексы таблицы `import_export_log`
--
ALTER TABLE `import_export_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `labels`
--
ALTER TABLE `labels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_label_per_user` (`user_id`,`name`),
  ADD KEY `category_id` (`category_id`);

--
-- Индексы таблицы `lessons`
--
ALTER TABLE `lessons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `public_link` (`public_link`),
  ADD KEY `idx_lesson_date` (`lesson_date`),
  ADD KEY `idx_lesson_completed` (`is_completed`),
  ADD KEY `idx_lesson_paid` (`is_paid`),
  ADD KEY `idx_lesson_student_date` (`student_id`,`lesson_date`),
  ADD KEY `idx_lesson_diary_date` (`diary_id`,`lesson_date`);

--
-- Индексы таблицы `lesson_labels`
--
ALTER TABLE `lesson_labels`
  ADD PRIMARY KEY (`lesson_id`,`label_id`),
  ADD KEY `label_id` (`label_id`),
  ADD KEY `idx_lesson_labels` (`lesson_id`,`label_id`);

--
-- Индексы таблицы `lesson_resources`
--
ALTER TABLE `lesson_resources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lesson_id` (`lesson_id`),
  ADD KEY `resource_id` (`resource_id`);

--
-- Индексы таблицы `lesson_topics`
--
ALTER TABLE `lesson_topics`
  ADD PRIMARY KEY (`lesson_id`,`topic_id`),
  ADD KEY `topic_id` (`topic_id`);

--
-- Индексы таблицы `parents`
--
ALTER TABLE `parents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Индексы таблицы `resources`
--
ALTER TABLE `resources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_resource_type` (`type`);

--
-- Индексы таблицы `resource_labels`
--
ALTER TABLE `resource_labels`
  ADD PRIMARY KEY (`resource_id`,`label_id`),
  ADD KEY `label_id` (`label_id`),
  ADD KEY `idx_resource_labels` (`resource_id`,`label_id`);

--
-- Индексы таблицы `statistics`
--
ALTER TABLE `statistics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_period_per_user` (`user_id`,`period_type`,`period_date`);

--
-- Индексы таблицы `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_student_name` (`last_name`,`first_name`),
  ADD KEY `idx_student_class` (`class`),
  ADD KEY `idx_student_active` (`is_active`);

--
-- Индексы таблицы `student_comments`
--
ALTER TABLE `student_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `student_history`
--
ALTER TABLE `student_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `student_labels`
--
ALTER TABLE `student_labels`
  ADD PRIMARY KEY (`student_id`,`label_id`),
  ADD KEY `label_id` (`label_id`),
  ADD KEY `idx_student_labels` (`student_id`,`label_id`);

--
-- Индексы таблицы `topics`
--
ALTER TABLE `topics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_topic_per_user` (`user_id`,`name`),
  ADD KEY `category_id` (`category_id`);

--
-- Индексы таблицы `topic_labels`
--
ALTER TABLE `topic_labels`
  ADD PRIMARY KEY (`topic_id`,`label_id`),
  ADD KEY `label_id` (`label_id`),
  ADD KEY `idx_topic_labels` (`topic_id`,`label_id`);

--
-- Индексы таблицы `topic_links`
--
ALTER TABLE `topic_links`
  ADD PRIMARY KEY (`id`),
  ADD KEY `topic_id` (`topic_id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Индексы таблицы `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_session_token` (`session_token`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `diaries`
--
ALTER TABLE `diaries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `diary_comments`
--
ALTER TABLE `diary_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `homework_tasks`
--
ALTER TABLE `homework_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `import_export_log`
--
ALTER TABLE `import_export_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `labels`
--
ALTER TABLE `labels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `lessons`
--
ALTER TABLE `lessons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `lesson_resources`
--
ALTER TABLE `lesson_resources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `parents`
--
ALTER TABLE `parents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `resources`
--
ALTER TABLE `resources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `statistics`
--
ALTER TABLE `statistics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `student_comments`
--
ALTER TABLE `student_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `student_history`
--
ALTER TABLE `student_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `topics`
--
ALTER TABLE `topics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT для таблицы `topic_links`
--
ALTER TABLE `topic_links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `diaries`
--
ALTER TABLE `diaries`
  ADD CONSTRAINT `diaries_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `diaries_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `diaries_ibfk_3` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `diary_comments`
--
ALTER TABLE `diary_comments`
  ADD CONSTRAINT `diary_comments_ibfk_1` FOREIGN KEY (`diary_id`) REFERENCES `diaries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `diary_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `homework_tasks`
--
ALTER TABLE `homework_tasks`
  ADD CONSTRAINT `homework_tasks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `homework_tasks_ibfk_2` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `import_export_log`
--
ALTER TABLE `import_export_log`
  ADD CONSTRAINT `import_export_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `labels`
--
ALTER TABLE `labels`
  ADD CONSTRAINT `labels_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `labels_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `lessons`
--
ALTER TABLE `lessons`
  ADD CONSTRAINT `lessons_ibfk_1` FOREIGN KEY (`diary_id`) REFERENCES `diaries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lessons_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `lesson_labels`
--
ALTER TABLE `lesson_labels`
  ADD CONSTRAINT `lesson_labels_ibfk_1` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lesson_labels_ibfk_2` FOREIGN KEY (`label_id`) REFERENCES `labels` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `lesson_resources`
--
ALTER TABLE `lesson_resources`
  ADD CONSTRAINT `lesson_resources_ibfk_1` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lesson_resources_ibfk_2` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `lesson_topics`
--
ALTER TABLE `lesson_topics`
  ADD CONSTRAINT `lesson_topics_ibfk_1` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lesson_topics_ibfk_2` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `parents`
--
ALTER TABLE `parents`
  ADD CONSTRAINT `parents_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `resources`
--
ALTER TABLE `resources`
  ADD CONSTRAINT `resources_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `resources_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `resource_labels`
--
ALTER TABLE `resource_labels`
  ADD CONSTRAINT `resource_labels_ibfk_1` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `resource_labels_ibfk_2` FOREIGN KEY (`label_id`) REFERENCES `labels` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `statistics`
--
ALTER TABLE `statistics`
  ADD CONSTRAINT `statistics_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `student_comments`
--
ALTER TABLE `student_comments`
  ADD CONSTRAINT `student_comments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `student_history`
--
ALTER TABLE `student_history`
  ADD CONSTRAINT `student_history_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `student_labels`
--
ALTER TABLE `student_labels`
  ADD CONSTRAINT `student_labels_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_labels_ibfk_2` FOREIGN KEY (`label_id`) REFERENCES `labels` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `topics`
--
ALTER TABLE `topics`
  ADD CONSTRAINT `topics_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `topics_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `topic_labels`
--
ALTER TABLE `topic_labels`
  ADD CONSTRAINT `topic_labels_ibfk_1` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `topic_labels_ibfk_2` FOREIGN KEY (`label_id`) REFERENCES `labels` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `topic_links`
--
ALTER TABLE `topic_links`
  ADD CONSTRAINT `topic_links_ibfk_1` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
