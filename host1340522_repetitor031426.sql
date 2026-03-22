-- phpMyAdmin SQL Dump
-- version 
-- https://www.phpmyadmin.net/
--
-- Хост: localhost:3308
-- Время создания: Мар 22 2026 г., 19:04
-- Версия сервера: 8.0.45-36
-- Версия PHP: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `host1340522_repetitor031426`
--

-- --------------------------------------------------------

--
-- Структура таблицы `categories`
--

CREATE TABLE `categories` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `color` varchar(7) COLLATE utf8mb4_general_ci DEFAULT '#808080',
  `is_hidden` tinyint(1) DEFAULT '0',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `categories`
--

INSERT INTO `categories` (`id`, `user_id`, `name`, `color`, `is_hidden`, `sort_order`, `created_at`) VALUES
(2, 1, 'ЕГЭ информатика 2026', '#667eea', 0, 0, '2026-03-14 20:13:55'),
(3, 1, 'Информатика', '#667eea', 0, 0, '2026-03-14 20:27:32'),
(4, 1, 'Яндекс. Учебник. Программирование на Python', '#e8d264', 0, 0, '2026-03-14 20:57:34'),
(5, 1, 'Без категории', '#808080', 0, 0, '2026-03-16 21:53:10'),
(6, 2, 'Без категории', '#808080', 0, 0, '2026-03-16 23:33:30'),
(7, 1, 'Колледж МГИМО', '#072cd5', 0, 0, '2026-03-17 11:16:27'),
(8, 1, 'Помощь по учебе', '#6fea66', 0, 0, '2026-03-17 11:18:39'),
(9, 1, 'Записи занятий', '#c7ea66', 0, 0, '2026-03-17 17:41:48');

-- --------------------------------------------------------

--
-- Структура таблицы `diaries`
--

CREATE TABLE `diaries` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `student_id` int DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `lesson_cost` decimal(10,2) DEFAULT NULL,
  `lesson_duration` int DEFAULT NULL,
  `public_link` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_public` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `diaries`
--

INSERT INTO `diaries` (`id`, `user_id`, `student_id`, `category_id`, `name`, `description`, `lesson_cost`, `lesson_duration`, `public_link`, `is_public`, `created_at`) VALUES
(1, 1, 1, 7, 'Худяков Василий', '', '2500.00', 90, '7ab71e7c16543bb892d6505e49bdb05f', 0, '2026-03-14 18:47:23'),
(2, 1, 2, 8, 'Ходаков Роман', '', '2000.00', 120, NULL, 0, '2026-03-14 19:17:09'),
(4, 1, 3, 2, 'Пелагеин Илья', '', '1500.00', 60, '36266b47041aee563f6064956c01ffee', 0, '2026-03-15 19:16:44'),
(5, 1, 9, 2, 'Нуретдинов Раиль', 'Подготовка к ЕГЭ', '2000.00', 90, '4d7dc5f5d06a63ff30f530d489920462', 0, '2026-03-16 19:40:40'),
(6, 1, 5, 7, 'Курин Тимофей', '', '2500.00', 90, NULL, 0, '2026-03-16 19:48:41'),
(7, 1, 4, 2, 'Князькина Алина', '', '2500.00', 90, NULL, 0, '2026-03-16 21:14:03'),
(8, 1, 7, 2, 'Родин Дмитрий', '', '2000.00', 90, NULL, 0, '2026-03-17 11:10:07'),
(9, 1, 8, 8, 'Тупикова Мария', '', '1500.00', 60, NULL, 0, '2026-03-17 11:21:25'),
(10, 1, 6, 2, 'Пустовойт Серафим', '', '2500.00', 90, NULL, 0, '2026-03-17 11:36:23');

-- --------------------------------------------------------

--
-- Структура таблицы `diary_comments`
--

CREATE TABLE `diary_comments` (
  `id` int NOT NULL,
  `diary_id` int NOT NULL,
  `user_id` int NOT NULL,
  `comment` text COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `homework_tasks`
--

CREATE TABLE `homework_tasks` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `lesson_id` int DEFAULT NULL,
  `task_text` text COLLATE utf8mb4_general_ci NOT NULL,
  `labels` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `homework_tasks`
--

INSERT INTO `homework_tasks` (`id`, `user_id`, `lesson_id`, `task_text`, `labels`, `created_at`) VALUES
(1, 1, 4, 'Прочитать презентации в Яндекс.Учебнике', NULL, '2026-03-14 21:18:34'),
(2, 1, 4, 'Прочитать презентации в Яндекс.Учебнике', NULL, '2026-03-14 23:53:14'),
(3, 1, 4, 'Прочитать презентации в Яндекс.Учебнике', NULL, '2026-03-15 01:49:32'),
(4, 1, 4, 'Прочитать презентации в Яндекс.Учебнике', NULL, '2026-03-15 01:50:17'),
(5, 1, 6, 'https://inf-oge.sdamgia.ru/test?id=29300576\r\nДоделать задания в Яндекс.Учебнике', NULL, '2026-03-15 06:39:55'),
(6, 1, 1, 'Выполнить все задания в Яндекс.Учебнике.', NULL, '2026-03-15 07:30:20'),
(7, 1, 1, 'Выполнить все задания в Яндекс.Учебнике.\r\nВыполнить задания по ссылке:\r\nhttps://inf-ege.sdamgia.ru/test?id=20044510', NULL, '2026-03-15 08:15:26'),
(8, 1, 1, 'Выполнить все задания в Яндекс.Учебнике.\r\nВыполнить задания по ссылке:\r\nhttps://inf-ege.sdamgia.ru/test?id=20044510', NULL, '2026-03-15 08:24:08'),
(9, 1, 1, 'Выполнить все задания в Яндекс.Учебнике.\r\nВыполнить задания по ссылке:\r\nhttps://inf-ege.sdamgia.ru/test?id=20044510', NULL, '2026-03-15 08:48:40'),
(10, 1, 6, 'Решить задачи на системы счисления на РешуОГЭ\r\nhttps://inf-oge.sdamgia.ru/test?id=29300576\r\n\r\nДоделать задания в Яндекс.Учебнике', NULL, '2026-03-15 12:47:47'),
(11, 1, 1, 'Выполнить все задания в Яндекс.Учебнике.\r\nВыполнить задания по ссылке:\r\nhttps://inf-ege.sdamgia.ru/test?id=20044510', NULL, '2026-03-15 12:49:13'),
(12, 1, 6, 'Решить задачи на системы счисления на РешуОГЭ\r\nhttps://inf-oge.sdamgia.ru/test?id=29300576\r\n\r\nДоделать задания в Яндекс.Учебнике', NULL, '2026-03-15 12:52:12'),
(13, 1, 3, 'Задачи на Excel (3 задание ЕГЭ)\r\nhttps://inf-ege.sdamgia.ru/test?id=19891584', NULL, '2026-03-15 12:54:17'),
(14, 1, 4, 'Прочитать презентации в Яндекс.Учебнике', NULL, '2026-03-15 14:03:54'),
(15, 1, 6, 'Решить задачи на системы счисления на РешуОГЭ\r\nhttps://inf-oge.sdamgia.ru/test?id=29300576\r\n\r\nДоделать задания в Яндекс.Учебнике', NULL, '2026-03-15 14:19:36'),
(16, 1, 4, 'Прочитать презентации в Яндекс.Учебнике', NULL, '2026-03-15 14:19:54'),
(17, 1, 20, 'Прорешать сложную задачу', NULL, '2026-03-16 19:43:35'),
(18, 1, 20, 'Прорешать сложную задачу', NULL, '2026-03-16 23:38:59'),
(19, 1, 24, 'https://inf-ege.sdamgia.ru/test?theme=246\r\n2,3 и 4 задачи (на маски)', NULL, '2026-03-17 17:40:37'),
(20, 1, 24, 'https://inf-ege.sdamgia.ru/test?theme=246\r\n2,3 и 4 задачи (на маски)', NULL, '2026-03-17 17:46:10'),
(21, 1, 24, 'https://inf-ege.sdamgia.ru/test?theme=246\r\n2,3 и 4 задачи (на маски)', NULL, '2026-03-17 22:10:47'),
(22, 1, 24, 'https://inf-ege.sdamgia.ru/test?theme=246\r\n2,3 и 4 задачи (на маски)', NULL, '2026-03-17 23:18:01'),
(23, 1, 6, 'Решить задачи на системы счисления на РешуОГЭ\r\nhttps://inf-oge.sdamgia.ru/test?id=29300576\r\n\r\nДоделать задания в Яндекс.Учебнике', NULL, '2026-03-19 08:05:54'),
(24, 1, 4, 'Прочитать презентации в Яндекс.Учебнике', NULL, '2026-03-19 08:55:01'),
(25, 1, 6, 'Решить задачи на системы счисления на РешуОГЭ\r\nhttps://inf-oge.sdamgia.ru/test?id=29300576\r\n\r\nДоделать задания в Яндекс.Учебнике', NULL, '2026-03-19 08:56:27'),
(26, 1, 63, 'Задание на исполнителя (6 задача)\r\nhttps://inf-ege.sdamgia.ru/test?id=20083655', NULL, '2026-03-19 09:17:47'),
(27, 1, 3, 'Задачи на Excel (3 задание ЕГЭ)\r\nhttps://inf-ege.sdamgia.ru/test?id=19891584', NULL, '2026-03-19 09:19:47'),
(28, 1, 63, 'Задание на исполнителя (6 задача)\r\nhttps://inf-ege.sdamgia.ru/test?id=20083655', NULL, '2026-03-19 09:25:37'),
(29, 1, 24, 'https://inf-ege.sdamgia.ru/test?theme=246\r\n2,3 и 4 задачи (на маски)', NULL, '2026-03-19 10:47:34'),
(30, 1, 29, 'https://inf-ege.sdamgia.ru/test?theme=426\r\n85688', NULL, '2026-03-19 16:30:37'),
(31, 1, 29, 'https://inf-ege.sdamgia.ru/test?theme=426\r\n85688', NULL, '2026-03-19 16:31:32'),
(32, 1, 29, 'https://inf-ege.sdamgia.ru/test?theme=426\r\n85688', NULL, '2026-03-19 16:36:23'),
(33, 1, 29, 'https://inf-ege.sdamgia.ru/test?theme=426\r\n85688', NULL, '2026-03-20 05:23:22'),
(34, 1, 20, 'Прорешать сложную задачу', NULL, '2026-03-21 08:10:06'),
(35, 1, 20, 'Прорешать сложную задачу', NULL, '2026-03-21 09:31:18'),
(36, 1, 32, 'Выполнить видео 4', NULL, '2026-03-21 10:45:20'),
(37, 1, 1, 'Выполнить все задания в Яндекс.Учебнике.\r\nВыполнить задания по ссылке:\r\nhttps://inf-ege.sdamgia.ru/test?id=20044510', NULL, '2026-03-22 08:26:44'),
(38, 1, 1, 'Выполнить все задания в Яндекс.Учебнике.\r\nВыполнить задания по ссылке:\r\nhttps://inf-ege.sdamgia.ru/test?id=20044510', NULL, '2026-03-22 08:27:18'),
(39, 1, 63, 'Задание на исполнителя (6 задача)\r\nhttps://inf-ege.sdamgia.ru/test?id=20083655', NULL, '2026-03-22 11:16:14'),
(40, 1, 6, 'Решить задачи на системы счисления на РешуОГЭ\r\nhttps://inf-oge.sdamgia.ru/test?id=29300576\r\n\r\nДоделать задания в Яндекс.Учебнике', NULL, '2026-03-22 13:06:24'),
(41, 1, 4, 'Прочитать презентации в Яндекс.Учебнике', NULL, '2026-03-22 13:32:51'),
(42, 1, 4, 'Прочитать презентации в Яндекс.Учебнике', NULL, '2026-03-22 13:33:25'),
(43, 1, 4, 'Прочитать презентации в Яндекс.Учебнике', NULL, '2026-03-22 13:35:54'),
(44, 1, 64, 'На системы счисления\r\nhttps://inf-oge.sdamgia.ru/test?id=29525467\r\nПродолжить решать задачи из Яндекс.Учебника', NULL, '2026-03-22 13:38:07'),
(45, 1, 64, 'На системы счисления\r\nhttps://inf-oge.sdamgia.ru/test?id=29525467\r\nПродолжить решать задачи из Яндекс.Учебника', NULL, '2026-03-22 13:39:30'),
(46, 1, 64, 'На системы счисления\r\nhttps://inf-oge.sdamgia.ru/test?id=29525467\r\nПродолжить решать задачи из Яндекс.Учебника', NULL, '2026-03-22 13:41:12'),
(47, 1, 64, 'На системы счисления\r\nhttps://inf-oge.sdamgia.ru/test?id=29525467\r\nПродолжить решать задачи из Яндекс.Учебника', NULL, '2026-03-22 13:41:21'),
(48, 1, 64, 'На системы счисления\r\nhttps://inf-oge.sdamgia.ru/test?id=29525467\r\nПродолжить решать задачи из Яндекс.Учебника', NULL, '2026-03-22 13:50:37'),
(49, 1, 64, 'На системы счисления\r\nhttps://inf-oge.sdamgia.ru/test?id=29525467\r\nПродолжить решать задачи из Яндекс.Учебника', NULL, '2026-03-22 14:05:13');

-- --------------------------------------------------------

--
-- Структура таблицы `import_export_log`
--

CREATE TABLE `import_export_log` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `operation_type` enum('import','export') COLLATE utf8mb4_general_ci NOT NULL,
  `module_type` enum('students','categories','topics','labels','resources','diaries') COLLATE utf8mb4_general_ci NOT NULL,
  `file_format` enum('csv','json') COLLATE utf8mb4_general_ci NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('pending','success','error') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `error_message` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `labels`
--

CREATE TABLE `labels` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `category_id` int DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `label_type` enum('resource','topic','student','lesson','general') COLLATE utf8mb4_general_ci DEFAULT 'general',
  `is_hidden` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `labels`
--

INSERT INTO `labels` (`id`, `user_id`, `category_id`, `name`, `label_type`, `is_hidden`, `created_at`) VALUES
(1, 1, NULL, '3D Blender', 'topic', 0, '2026-03-17 11:26:16');

-- --------------------------------------------------------

--
-- Структура таблицы `lessons`
--

CREATE TABLE `lessons` (
  `id` int NOT NULL,
  `diary_id` int NOT NULL,
  `student_id` int NOT NULL,
  `lesson_date` date NOT NULL,
  `start_time` time DEFAULT '12:00:00',
  `duration` int DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `topics_manual` text COLLATE utf8mb4_general_ci,
  `homework_manual` text COLLATE utf8mb4_general_ci,
  `comment` text COLLATE utf8mb4_general_ci,
  `link_url` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `link_comment` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `grade_lesson` int DEFAULT NULL,
  `grade_comment` text COLLATE utf8mb4_general_ci,
  `grade_homework` int DEFAULT NULL,
  `homework_comment` text COLLATE utf8mb4_general_ci,
  `is_cancelled` tinyint(1) DEFAULT '0',
  `is_completed` tinyint(1) DEFAULT '0',
  `is_paid` tinyint(1) DEFAULT '0',
  `public_link` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

--
-- Дамп данных таблицы `lessons`
--

INSERT INTO `lessons` (`id`, `diary_id`, `student_id`, `lesson_date`, `start_time`, `duration`, `cost`, `topics_manual`, `homework_manual`, `comment`, `link_url`, `link_comment`, `grade_lesson`, `grade_comment`, `grade_homework`, `homework_comment`, `is_cancelled`, `is_completed`, `is_paid`, `public_link`, `created_at`) VALUES
(1, 1, 1, '2026-03-15', '09:30:00', 90, '2500.00', '', 'Выполнить все задания в Яндекс.Учебнике.\r\nВыполнить задания по ссылке:\r\nhttps://inf-ege.sdamgia.ru/test?id=20044510', 'Урок начался на 20 минут позже из-за проблем с Интернетом.\r\nНа уроке работал хорошо, но еще очень слабо сформирован навык программирования.', 'https://disk.yandex.ru/i/umWnmsSnAw07pg', 'Запись решения 10 задачи', 3, '', 0, '', 0, 1, 1, NULL, '2026-03-14 19:04:01'),
(2, 2, 2, '2026-03-15', '11:00:00', 120, '2000.00', 'Создание простого класса параллелограм.\r\nТекстурирование в Blender.', '', 'Работал самостоятельно. Потребовались небольшие подсказки по тому, как правильно описать класс.', '', '', 5, '', 0, '', 0, 1, 1, NULL, '2026-03-14 19:29:33'),
(3, 1, 1, '2026-02-22', '15:00:00', 90, '2500.00', '', 'Задачи на Excel (3 задание ЕГЭ)\r\nhttps://inf-ege.sdamgia.ru/test?id=19891584', 'Не плохие навыки работы в Excel. Для 5 нужно научиться решать задачи ЕГЭ', '', '', 4, '', 3, '', 0, 1, 1, NULL, '2026-03-14 20:18:52'),
(4, 1, 1, '2026-03-01', '15:00:00', 90, '2500.00', '', 'Прочитать презентации в Яндекс.Учебнике', '', 'https://education.yandex.ru/kids/', 'Вход в Яндекс.Учебник', 0, '', 2, 'Домашнее задание не выполнил.', 0, 1, 1, NULL, '2026-03-14 20:23:40'),
(5, 1, 1, '2026-03-07', '15:00:00', 0, '2500.00', '', '', 'Василий не вышел, но занятие оплатили. \r\nМы позанимались лишний час на следующем занятии.\r\nНужно еще полчаса.', '', '', 0, '', 0, '', 1, 0, 1, NULL, '2026-03-14 20:25:47'),
(6, 1, 1, '2026-03-09', '13:00:00', 150, '2500.00', '', 'Решить задачи на системы счисления на РешуОГЭ\r\nhttps://inf-oge.sdamgia.ru/test?id=29300576\r\n\r\nДоделать задания в Яндекс.Учебнике', '', 'https://disk.yandex.ru/i/nDhYETxcOAXd5g', 'Презентация по СС Полякова', 3, '', 3, 'Не выполнил', 0, 1, 1, NULL, '2026-03-14 20:26:45'),
(7, 2, 2, '2026-03-22', '11:00:00', 120, '2000.00', '', '', '', '', '', 0, '', 0, '', 0, 1, 1, NULL, '2026-03-15 12:14:41'),
(8, 4, 3, '2026-03-18', '10:00:00', 0, '0.00', 'Вайбкодинг', '', '', '', '', 0, '', 0, '', 1, 0, 0, NULL, '2026-03-15 19:17:54'),
(9, 4, 3, '2026-03-16', '11:30:00', 60, '1500.00', '', '', '', '', '', 0, '', 0, '', 0, 1, 1, NULL, '2026-03-15 20:30:24'),
(11, 4, 3, '2026-02-18', '14:00:00', 60, '1500.00', '', '', '', '', '', 0, '', 0, '', 0, 1, 1, NULL, '2026-03-16 08:37:23'),
(12, 4, 3, '2026-02-19', '15:00:00', 60, '1500.00', '', '', '', '', '', 0, '', 0, '', 0, 1, 1, NULL, '2026-03-16 08:38:40'),
(13, 4, 3, '2026-02-09', '13:00:00', 60, '1500.00', '', '', '', '', '', 0, '', 0, '', 0, 1, 1, NULL, '2026-03-16 08:39:57'),
(14, 4, 3, '2026-02-10', '14:00:00', 60, '1500.00', '', '', '', '', '', 0, '', 0, '', 0, 1, 1, NULL, '2026-03-16 08:40:29'),
(15, 4, 3, '2026-02-02', '23:00:00', 60, '1500.00', '', '', '', '', '', NULL, '', NULL, '', 0, 1, 1, NULL, '2026-03-16 09:31:45'),
(16, 4, 3, '2026-01-27', '12:00:00', 60, '1500.00', '', '', '', '', '', 0, '', 0, '', 0, 1, 1, NULL, '2026-03-16 09:34:04'),
(18, 4, 3, '2026-03-05', '15:00:00', 60, '1500.00', '', '', 'Илье тяжело решать подобные задачи. Навык программирования пока очень слабый', '', '', 0, '', 0, '', 0, 1, 1, NULL, '2026-03-16 13:49:21'),
(19, 4, 3, '2026-03-04', '14:30:00', 60, '1500.00', '', '', '', '', '', 0, '', 0, '', 0, 1, 1, NULL, '2026-03-16 13:54:44'),
(20, 5, 9, '2026-03-16', '17:00:00', 120, '2000.00', '', 'Прорешать сложную задачу', 'Занятие прошло тяжело, так как был новый тип задач, который нужно было решать аналитически.\r\nНужно потренироваться', 'https://kompege.ru/variant?kim=25135392', '', 5, '', 0, '', 0, 1, 1, NULL, '2026-03-16 19:43:35'),
(21, 6, 5, '2026-03-16', '19:00:00', 0, '0.00', '', '', '', '', '', 0, '', 0, '', 1, 0, 0, NULL, '2026-03-16 19:49:06'),
(24, 7, 4, '2026-03-17', '19:00:00', 90, '2500.00', '', 'https://inf-ege.sdamgia.ru/test?theme=246\r\n2,3 и 4 задачи (на маски)', '', '', '', 0, '', 0, '', 0, 1, 1, NULL, '2026-03-16 21:16:30'),
(26, 1, 1, '2026-03-18', '11:00:00', 0, '0.00', '', '', 'Василий просто не вышел на занятие.', '', '', 0, '', 0, '', 1, 0, 1, NULL, '2026-03-16 23:11:48'),
(27, 5, 9, '2026-03-21', '11:00:00', 90, '2000.00', 'нужно потренироваться решать 15 задачи аналитически', '', 'Как всегда работал хорошо', 'https://docs.google.com/document/d/1GQktYOC6SVranP-V2kAh9LDMF1IyGNxviWHRrqstjKM/edit?usp=sharing', 'Ответы', 5, '', 0, '', 0, 1, 1, NULL, '2026-03-16 23:15:28'),
(28, 8, 7, '2026-03-22', '09:00:00', 0, '0.00', 'Машина Тьюринга', '', '', 'https://kpolyakov.spb.ru/prog/turing.htm', 'Машина Тьюринга Полякова', 0, '', 0, '', 1, 0, 0, NULL, '2026-03-17 11:13:46'),
(29, 8, 7, '2026-03-19', '18:00:00', 90, '2000.00', 'Машина Тьюринга\r\nПрорешали задачи из РешуЕГЭ.\r\nРешает сам, но пока часто ошибается', 'https://inf-ege.sdamgia.ru/test?theme=426\r\n85688', 'Задачу понял, но пока часто ошибается', 'https://kpolyakov.spb.ru/prog/turing.htm', '', 0, '', 0, '', 0, 1, 1, NULL, '2026-03-17 11:14:27'),
(30, 6, 5, '2026-03-19', '20:00:00', 90, '2500.00', 'Рассказал, как прошел демоэкзамен\r\nSMSS 2022. Установили MS SMS 22.\r\nНаучились переводить JSON в CSV с помощью командной строки PS.\r\nПопробовали вайб-кодинг и создали редактор вопросов', '', '', '', '', 0, '', 0, '', 0, 1, 1, NULL, '2026-03-17 11:15:16'),
(31, 6, 5, '2026-03-21', '08:30:00', 90, '2500.00', 'Начали создавать систему проведения викторин.\r\nСоздали первый промпт и первые БД и веб-страницы', '', '', '', '', 0, '', 0, '', 0, 1, 1, NULL, '2026-03-17 11:16:00'),
(32, 9, 8, '2026-03-21', '10:00:00', 60, '1500.00', 'Моделирование в 3D.\r\nСоздание пера.\r\n4 урок', 'Выполнить видео 4', '', '', '', 0, '', 0, '', 0, 1, 1, NULL, '2026-03-17 11:25:43'),
(33, 10, 6, '2026-03-19', '13:00:00', 90, '2500.00', 'Разбор пробника от Статграда', '', '', '', '', 3, 'Сложный пробник. Много ошибок', 0, '', 0, 1, 1, NULL, '2026-03-17 11:38:10'),
(34, 7, 4, '2026-03-11', '11:00:00', 90, '2500.00', 'Объяснение новой темы. Объяснял через решение по формулам. Лишь в конце урока не много упомянул, что можно решать с помощью компьютера.', '', '', '', '', 3, '', 4, '', 0, 1, 1, NULL, '2026-03-17 17:47:54'),
(36, 7, 4, '2025-09-11', '12:00:00', 90, '2500.00', '', '', '', '', '', 0, '', 0, '', 0, 1, 1, NULL, '2026-03-17 21:57:09'),
(37, 7, 4, '2025-09-18', '12:00:00', 90, '2500.00', '', '', '', '', '', NULL, '', NULL, '', 0, 1, 1, NULL, '2026-03-17 21:58:18'),
(38, 7, 4, '2025-09-25', '12:00:00', 90, '2500.00', '', '', '', '', '', NULL, '', NULL, '', 0, 1, 1, NULL, '2026-03-17 21:59:34'),
(39, 7, 4, '2025-10-02', '12:00:00', 90, '2500.00', '', '', '', '', '', 0, '', 0, '', 0, 1, 1, NULL, '2026-03-17 22:00:22'),
(40, 7, 4, '2025-10-09', '12:00:00', 90, '2500.00', '', '', '', '', '', NULL, '', NULL, '', 0, 1, 1, NULL, '2026-03-17 22:01:19'),
(41, 7, 4, '2025-10-23', '12:00:00', 90, '2500.00', '', '', '', '', '', NULL, '', NULL, '', 0, 1, 1, NULL, '2026-03-17 22:02:49'),
(42, 7, 4, '2025-10-30', '12:00:00', 90, '2500.00', '', '', '', '', '', NULL, '', NULL, '', 0, 1, 1, NULL, '2026-03-17 22:03:52'),
(43, 7, 4, '2025-11-06', '12:00:00', 90, '2500.00', '', '', '', '', '', NULL, '', NULL, '', 0, 1, 1, NULL, '2026-03-17 22:04:52'),
(44, 7, 4, '2025-11-13', '12:00:00', 90, '2500.00', '', '', '', '', '', NULL, '', NULL, '', 0, 1, 1, NULL, '2026-03-17 22:05:41'),
(45, 7, 4, '2025-11-20', '12:00:00', 90, '2500.00', '', '', '', '', '', 0, '', 0, '', 0, 1, 1, NULL, '2026-03-17 22:06:09'),
(47, 7, 4, '2025-11-27', '12:00:00', 90, '2500.00', '', '', '', '', '', 0, '', 0, '', 0, 1, 1, NULL, '2026-03-17 22:08:04'),
(48, 7, 4, '2025-12-04', '12:00:00', 90, '2500.00', '', '', '', '', '', 0, '', 0, '', 0, 1, 1, NULL, '2026-03-17 22:08:37'),
(49, 7, 4, '2025-12-04', '12:00:00', 90, '2500.00', '', '', '', '', '', 0, '', 0, '', 0, 1, 1, NULL, '2026-03-17 22:09:21'),
(50, 7, 4, '2025-12-11', '12:00:00', 90, '2500.00', '', '', '', '', '', NULL, '', NULL, '', 0, 1, 1, NULL, '2026-03-17 22:18:51'),
(51, 7, 4, '2025-12-18', '12:00:00', 90, '2500.00', '', '', '', '', '', NULL, '', NULL, '', 0, 1, 1, NULL, '2026-03-17 22:19:52'),
(52, 7, 4, '2025-12-25', '12:00:00', 90, '2500.00', '', '', '', '', '', NULL, '', NULL, '', 0, 1, 1, NULL, '2026-03-17 22:20:51'),
(53, 7, 4, '2026-01-15', '12:00:00', 90, '2500.00', '', '', '', '', '', 0, '', 0, '', 0, 1, 1, NULL, '2026-03-17 22:21:29'),
(54, 7, 4, '2026-01-21', '12:00:00', 90, '2500.00', '', '', '', '', '', 0, '', 0, '', 0, 1, 1, NULL, '2026-03-17 22:22:47'),
(55, 7, 4, '2026-01-28', '12:00:00', 90, '2500.00', '', '', '', '', '', 0, '', 0, '', 0, 1, 1, NULL, '2026-03-17 22:23:53'),
(56, 7, 4, '2026-02-02', '12:00:00', 90, '2500.00', '', '', '', '', '', 0, '', 0, '', 0, 1, 1, NULL, '2026-03-17 22:25:06'),
(57, 7, 4, '2026-02-11', '12:00:00', 90, '2500.00', '', '', '', '', '', NULL, '', NULL, '', 0, 1, 1, NULL, '2026-03-17 22:27:11'),
(58, 7, 4, '2026-02-17', '12:00:00', 90, '2500.00', '', '', '', '', '', NULL, '', NULL, '', 0, 1, 1, NULL, '2026-03-17 22:27:45'),
(59, 7, 4, '2026-03-03', '12:00:00', 90, '2500.00', '', '', '', '', '', 0, '', 0, '', 0, 1, 1, NULL, '2026-03-17 22:28:20'),
(60, 4, 3, '2026-01-19', '12:00:00', 60, '1500.00', '', '', '', '', '', NULL, '', NULL, '', 0, 1, 1, NULL, '2026-03-17 22:53:43'),
(61, 4, 3, '2026-01-22', '12:00:00', 60, '1500.00', '', '', '', '', '', 0, '', 0, '', 0, 1, 1, NULL, '2026-03-17 22:54:14'),
(62, 4, 3, '2026-03-22', '18:00:00', 60, '1500.00', '', '', 'На уроке был сосредоточен.\r\nЗаписывал в тетрадь. Граммотно отвечал на вопросы.', '', '', 5, '', 0, '', 0, 1, 1, NULL, '2026-03-18 18:48:25'),
(63, 1, 1, '2026-03-19', '11:00:00', 90, '2500.00', '', 'Задание на исполнителя (6 задача)\r\nhttps://inf-ege.sdamgia.ru/test?id=20083655', 'На уроке работал хорошо.\r\nДо 5 не хватает навыка программирования.\r\nНужно обязательно больше программировать и пройти курс в Яндекс.Учебнике.', '', '', 4, '', 2, '', 0, 1, 1, NULL, '2026-03-19 03:19:17'),
(64, 1, 1, '2026-03-22', '14:00:00', 180, '5000.00', 'Системы счисления.\r\nВыполнили домашние задания', 'На системы счисления\r\nhttps://inf-oge.sdamgia.ru/test?id=29525467\r\nПродолжить решать задачи из Яндекс.Учебника', 'Опоздал на 15 минут\r\nНа уроке работал очень хорошо.\r\nОшибки в простом счете. Нужно больше тренироваться на перевод чисел в разные системы счисления\r\nЗадачи на программирование решает хорошо. Но это пока очень простые задачи.', '', '', 4, '', 0, '', 0, 1, 1, NULL, '2026-03-19 03:21:03'),
(65, 10, 6, '2026-03-21', '14:00:00', 90, '2500.00', '', '', '', '', '', 0, '', 0, '', 0, 1, 0, NULL, '2026-03-19 11:47:05'),
(66, 8, 7, '2026-03-11', '18:00:00', 90, '2000.00', '', '', '', '', '', NULL, '', NULL, '', 0, 1, 1, NULL, '2026-03-19 16:31:59'),
(67, 8, 7, '2026-03-12', '18:00:00', 90, '2000.00', 'Решали только с помощью компьютера', '', '', '', '', NULL, '', 4, 'Решил все задачи кроме одной, где нужно было подсчитать дополнительную информацию', 0, 1, 1, NULL, '2026-03-19 16:34:22'),
(68, 4, 3, '2026-03-23', '11:00:00', 60, '1500.00', '', '', '', '', '', NULL, '', NULL, '', 0, 0, 0, NULL, '2026-03-22 15:57:59');

-- --------------------------------------------------------

--
-- Структура таблицы `lesson_labels`
--

CREATE TABLE `lesson_labels` (
  `lesson_id` int NOT NULL,
  `label_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `lesson_resources`
--

CREATE TABLE `lesson_resources` (
  `id` int NOT NULL,
  `lesson_id` int NOT NULL,
  `resource_id` int NOT NULL,
  `comment` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `lesson_resources`
--

INSERT INTO `lesson_resources` (`id`, `lesson_id`, `resource_id`, `comment`, `created_at`) VALUES
(6, 24, 2, '', '2026-03-19 10:47:34'),
(10, 33, 3, '', '2026-03-19 19:03:00'),
(13, 27, 4, '', '2026-03-21 09:39:38'),
(14, 32, 1, '', '2026-03-21 10:45:20');

-- --------------------------------------------------------

--
-- Структура таблицы `lesson_topics`
--

CREATE TABLE `lesson_topics` (
  `lesson_id` int NOT NULL,
  `topic_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `lesson_topics`
--

INSERT INTO `lesson_topics` (`lesson_id`, `topic_id`, `created_at`) VALUES
(1, 9, '2026-03-22 08:27:18'),
(1, 65, '2026-03-22 08:27:18'),
(1, 66, '2026-03-22 08:27:18'),
(3, 1, '2026-03-19 09:19:47'),
(4, 33, '2026-03-22 13:35:54'),
(4, 61, '2026-03-22 13:35:54'),
(4, 62, '2026-03-22 13:35:54'),
(4, 63, '2026-03-22 13:35:54'),
(4, 64, '2026-03-22 13:35:54'),
(4, 65, '2026-03-22 13:35:54'),
(6, 35, '2026-03-22 13:06:24'),
(6, 63, '2026-03-22 13:06:24'),
(6, 64, '2026-03-22 13:06:24'),
(8, 4, '2026-03-20 05:25:32'),
(9, 4, '2026-03-18 15:10:05'),
(11, 1, '2026-03-16 08:38:50'),
(12, 2, '2026-03-16 08:38:57'),
(13, 33, '2026-03-17 22:32:47'),
(13, 34, '2026-03-17 22:32:47'),
(14, 1, '2026-03-17 22:47:19'),
(15, 1, '2026-03-16 09:31:45'),
(15, 33, '2026-03-16 09:31:45'),
(15, 34, '2026-03-16 09:31:45'),
(16, 33, '2026-03-17 22:45:59'),
(16, 34, '2026-03-17 22:45:59'),
(18, 3, '2026-03-16 19:44:58'),
(19, 3, '2026-03-16 13:56:04'),
(20, 14, '2026-03-21 09:31:18'),
(24, 10, '2026-03-19 10:47:34'),
(24, 26, '2026-03-19 10:47:34'),
(26, 4, '2026-03-22 08:49:31'),
(27, 31, '2026-03-21 09:39:38'),
(27, 32, '2026-03-21 09:39:38'),
(28, 11, '2026-03-21 21:45:02'),
(29, 11, '2026-03-20 05:23:22'),
(30, 84, '2026-03-19 22:58:43'),
(30, 85, '2026-03-19 22:58:43'),
(31, 84, '2026-03-22 06:43:36'),
(32, 86, '2026-03-21 10:45:20'),
(33, 1, '2026-03-19 19:03:00'),
(33, 2, '2026-03-19 19:03:00'),
(33, 3, '2026-03-19 19:03:00'),
(33, 4, '2026-03-19 19:03:00'),
(33, 33, '2026-03-19 19:03:00'),
(33, 34, '2026-03-19 19:03:00'),
(34, 10, '2026-03-17 23:55:50'),
(36, 29, '2026-03-17 21:58:40'),
(36, 33, '2026-03-17 21:58:40'),
(37, 1, '2026-03-17 21:58:18'),
(37, 2, '2026-03-17 21:58:18'),
(37, 29, '2026-03-17 21:58:18'),
(38, 2, '2026-03-17 21:59:34'),
(38, 5, '2026-03-17 21:59:34'),
(39, 5, '2026-03-17 22:01:31'),
(40, 13, '2026-03-17 22:01:19'),
(41, 7, '2026-03-17 22:02:49'),
(41, 13, '2026-03-17 22:02:49'),
(42, 13, '2026-03-17 22:03:52'),
(42, 23, '2026-03-17 22:03:52'),
(42, 29, '2026-03-17 22:03:52'),
(43, 4, '2026-03-17 22:04:52'),
(43, 7, '2026-03-17 22:04:52'),
(44, 1, '2026-03-17 22:05:41'),
(44, 9, '2026-03-17 22:05:41'),
(45, 24, '2026-03-17 22:15:22'),
(47, 14, '2026-03-17 22:16:00'),
(47, 34, '2026-03-17 22:16:00'),
(48, 3, '2026-03-17 22:17:10'),
(48, 14, '2026-03-17 22:17:10'),
(49, 3, '2026-03-17 22:14:40'),
(49, 6, '2026-03-17 22:14:40'),
(49, 11, '2026-03-17 22:14:40'),
(50, 2, '2026-03-17 22:18:51'),
(50, 6, '2026-03-17 22:18:51'),
(50, 11, '2026-03-17 22:18:51'),
(51, 3, '2026-03-17 22:19:52'),
(51, 6, '2026-03-17 22:19:52'),
(52, 16, '2026-03-17 22:20:51'),
(53, 16, '2026-03-17 22:22:17'),
(54, 18, '2026-03-17 22:24:00'),
(54, 19, '2026-03-17 22:24:00'),
(54, 20, '2026-03-17 22:24:00'),
(55, 18, '2026-03-17 22:24:04'),
(55, 19, '2026-03-17 22:24:04'),
(55, 20, '2026-03-17 22:24:04'),
(56, 87, '2026-03-17 22:26:28'),
(57, 87, '2026-03-17 22:27:11'),
(58, 28, '2026-03-17 22:27:45'),
(59, 1, '2026-03-17 22:28:56'),
(60, 33, '2026-03-17 22:53:43'),
(61, 33, '2026-03-18 00:40:12'),
(62, 10, '2026-03-22 15:57:37'),
(63, 4, '2026-03-22 11:16:14'),
(64, 35, '2026-03-22 14:05:13'),
(64, 61, '2026-03-22 14:05:13'),
(64, 63, '2026-03-22 14:05:13'),
(65, 4, '2026-03-21 19:03:28'),
(65, 5, '2026-03-21 19:03:28'),
(65, 6, '2026-03-21 19:03:28'),
(65, 8, '2026-03-21 19:03:28'),
(65, 9, '2026-03-21 19:03:28'),
(65, 10, '2026-03-21 19:03:28'),
(66, 10, '2026-03-19 16:31:59'),
(67, 10, '2026-03-19 16:34:22'),
(68, 10, '2026-03-22 15:57:59');

-- --------------------------------------------------------

--
-- Структура таблицы `parents`
--

CREATE TABLE `parents` (
  `id` int NOT NULL,
  `student_id` int NOT NULL,
  `relationship` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `full_name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `messenger_contact` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `parents`
--

INSERT INTO `parents` (`id`, `student_id`, `relationship`, `full_name`, `phone`, `messenger_contact`, `email`, `created_at`) VALUES
(1, 1, 'мама', 'Худякова Юлия', '+7(916)651-46-17', '', '', '2026-03-15 00:27:16'),
(3, 8, 'отец', 'Тупиков Павел', '', '', '', '2026-03-17 11:19:33'),
(4, 3, 'мама', 'Орлова Дарья Николаевна', '+79050035155', '', '', '2026-03-17 23:01:15');

-- --------------------------------------------------------

--
-- Структура таблицы `plannings`
--

CREATE TABLE `plannings` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `student_id` int DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `is_template` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `plannings`
--

INSERT INTO `plannings` (`id`, `user_id`, `student_id`, `category_id`, `name`, `description`, `is_active`, `is_template`, `created_at`) VALUES
(1, 1, 1, NULL, 'Худяков Василий - подготовка к внутреннему экзамену', '', 1, 0, '2026-03-14 22:55:29');

-- --------------------------------------------------------

--
-- Структура таблицы `planning_history`
--

CREATE TABLE `planning_history` (
  `id` int NOT NULL,
  `planning_id` int NOT NULL,
  `user_id` int NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `details` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `planning_history`
--

INSERT INTO `planning_history` (`id`, `planning_id`, `user_id`, `action`, `details`, `created_at`) VALUES
(1, 1, 1, 'create', NULL, '2026-03-14 22:55:29'),
(2, 1, 1, 'update_rows', NULL, '2026-03-14 23:23:31'),
(3, 1, 1, 'update_rows', NULL, '2026-03-14 23:23:53'),
(4, 1, 1, 'update_rows', NULL, '2026-03-14 23:59:38'),
(5, 1, 1, 'update_rows', NULL, '2026-03-15 00:02:50'),
(6, 1, 1, 'toggle_active', NULL, '2026-03-15 00:11:17'),
(7, 1, 1, 'toggle_active', NULL, '2026-03-15 00:11:21'),
(8, 1, 1, 'toggle_active', NULL, '2026-03-15 00:11:26'),
(9, 1, 1, 'update_rows', NULL, '2026-03-15 00:13:21'),
(10, 1, 1, 'toggle_active', NULL, '2026-03-15 00:16:07'),
(11, 1, 1, 'toggle_active', NULL, '2026-03-15 01:20:06'),
(12, 1, 1, 'toggle_active', NULL, '2026-03-15 01:20:08'),
(13, 1, 1, 'update_rows', NULL, '2026-03-15 01:24:02'),
(14, 1, 1, 'update_rows', NULL, '2026-03-15 01:28:48'),
(15, 1, 1, 'update_rows', NULL, '2026-03-15 01:33:16'),
(16, 1, 1, 'update_rows', NULL, '2026-03-15 01:34:21'),
(17, 1, 1, 'update_rows', NULL, '2026-03-15 01:43:04');

-- --------------------------------------------------------

--
-- Структура таблицы `planning_labels`
--

CREATE TABLE `planning_labels` (
  `planning_id` int NOT NULL,
  `label_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `planning_rows`
--

CREATE TABLE `planning_rows` (
  `id` int NOT NULL,
  `planning_id` int NOT NULL,
  `lesson_number` int NOT NULL,
  `lesson_date` date DEFAULT NULL,
  `topics_text` text COLLATE utf8mb4_general_ci,
  `resources_text` text COLLATE utf8mb4_general_ci,
  `topics` text COLLATE utf8mb4_general_ci,
  `resources` text COLLATE utf8mb4_general_ci,
  `homework` text COLLATE utf8mb4_general_ci,
  `notes` text COLLATE utf8mb4_general_ci,
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `planning_rows`
--

INSERT INTO `planning_rows` (`id`, `planning_id`, `lesson_number`, `lesson_date`, `topics_text`, `resources_text`, `topics`, `resources`, `homework`, `notes`, `sort_order`, `created_at`) VALUES
(80, 1, 1, '2026-03-15', '', '', NULL, NULL, '', '', 0, '2026-03-15 01:43:04'),
(81, 1, 2, '2026-03-22', '', '', NULL, NULL, '', '', 1, '2026-03-15 01:43:04'),
(82, 1, 3, '2026-03-29', '', '', NULL, NULL, '', '', 2, '2026-03-15 01:43:04'),
(83, 1, 4, '2026-04-05', '', '', NULL, NULL, '', '', 3, '2026-03-15 01:43:04'),
(84, 1, 5, '2026-04-12', '', '', NULL, NULL, '', '', 4, '2026-03-15 01:43:04'),
(85, 1, 7, '2026-04-26', '', '', NULL, NULL, '', '', 5, '2026-03-15 01:43:04'),
(86, 1, 6, '2026-04-19', '', '', NULL, NULL, '', '', 6, '2026-03-15 01:43:04'),
(87, 1, 8, '2026-05-03', '', '', NULL, NULL, '', '', 7, '2026-03-15 01:43:04'),
(88, 1, 9, '2026-05-10', '', '', NULL, NULL, '', '', 8, '2026-03-15 01:43:04'),
(89, 1, 10, '2026-05-17', '', '', NULL, NULL, '', '', 9, '2026-03-15 01:43:04'),
(90, 1, 11, '2026-05-24', '', '', NULL, NULL, '', '', 10, '2026-03-15 01:43:04'),
(91, 1, 12, '2026-05-31', '', '', NULL, NULL, '', '', 11, '2026-03-15 01:43:04'),
(92, 1, 13, '2026-06-07', '', '', NULL, NULL, '', '', 12, '2026-03-15 01:43:04');

-- --------------------------------------------------------

--
-- Структура таблицы `planning_row_resources`
--

CREATE TABLE `planning_row_resources` (
  `row_id` int NOT NULL,
  `resource_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `planning_row_topics`
--

CREATE TABLE `planning_row_topics` (
  `row_id` int NOT NULL,
  `topic_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `planning_row_topics`
--

INSERT INTO `planning_row_topics` (`row_id`, `topic_id`, `created_at`) VALUES
(80, 4, '2026-03-15 01:43:04'),
(80, 67, '2026-03-15 01:43:04'),
(80, 68, '2026-03-15 01:43:04'),
(80, 69, '2026-03-15 01:43:04'),
(81, 2, '2026-03-15 01:43:04'),
(81, 70, '2026-03-15 01:43:04'),
(81, 71, '2026-03-15 01:43:04'),
(81, 72, '2026-03-15 01:43:04'),
(82, 9, '2026-03-15 01:43:04'),
(82, 73, '2026-03-15 01:43:04'),
(82, 74, '2026-03-15 01:43:04'),
(82, 75, '2026-03-15 01:43:04'),
(83, 10, '2026-03-15 01:43:04'),
(83, 76, '2026-03-15 01:43:04'),
(83, 77, '2026-03-15 01:43:04'),
(83, 78, '2026-03-15 01:43:04'),
(84, 79, '2026-03-15 01:43:04'),
(84, 80, '2026-03-15 01:43:04'),
(85, 15, '2026-03-15 01:43:04'),
(85, 81, '2026-03-15 01:43:04'),
(85, 82, '2026-03-15 01:43:04'),
(86, 80, '2026-03-15 01:43:04'),
(86, 82, '2026-03-15 01:43:04');

-- --------------------------------------------------------

--
-- Структура таблицы `resources`
--

CREATE TABLE `resources` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `category_id` int DEFAULT NULL,
  `url` varchar(500) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `type` enum('page','document','video','audio','other') COLLATE utf8mb4_general_ci DEFAULT 'page',
  `labels` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `resources`
--

INSERT INTO `resources` (`id`, `user_id`, `category_id`, `url`, `description`, `type`, `labels`, `created_at`) VALUES
(1, 1, 8, 'https://www.youtube.com/playlist?list=PLDNB1CDToAx8ah0pelchEZnfXWREolNxT', 'Создание Совы в Blender', 'video', '3D; Blender', '2026-03-17 11:27:37'),
(2, 1, 9, 'https://disk.yandex.ru/d/Y8X3IQSjHZF91g', 'Запись урока с Князькиной Алиной от 17.03.2026.\r\nРазбор домашней работы - 11 задача\r\nОбъяснение решения 25 задачи. Довольно сложные задачи попались для объяснения.', 'video', 'запись урока', '2026-03-17 17:43:17'),
(3, 1, 2, 'https://disk.yandex.ru/d/TzEtZZ135eec7w', 'Пробник от Статграда 03.03.2026', 'document', 'пробник; сложный', '2026-03-19 10:52:35'),
(4, 1, 2, 'https://kompege.ru/variant?kim=25135392', 'Пробник ЕГРК 12.12.2025', 'page', 'сложно', '2026-03-21 09:12:58');

-- --------------------------------------------------------

--
-- Структура таблицы `resource_labels`
--

CREATE TABLE `resource_labels` (
  `resource_id` int NOT NULL,
  `label_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `statistics`
--

CREATE TABLE `statistics` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `period_type` enum('day','week','month','year') COLLATE utf8mb4_general_ci NOT NULL,
  `period_date` date NOT NULL,
  `total_lessons` int DEFAULT '0',
  `completed_lessons` int DEFAULT '0',
  `cancelled_lessons` int DEFAULT '0',
  `total_income` decimal(10,2) DEFAULT '0.00',
  `paid_income` decimal(10,2) DEFAULT '0.00',
  `average_grade` decimal(3,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `students`
--

CREATE TABLE `students` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `last_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `middle_name` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `class` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `lesson_cost` decimal(10,2) DEFAULT NULL,
  `lesson_duration` int DEFAULT NULL,
  `lessons_per_week` int DEFAULT NULL,
  `goals` text COLLATE utf8mb4_general_ci,
  `birth_date` date DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `messenger1` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `messenger2` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `messenger3` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `students`
--

INSERT INTO `students` (`id`, `user_id`, `first_name`, `last_name`, `middle_name`, `class`, `phone`, `lesson_cost`, `lesson_duration`, `lessons_per_week`, `goals`, `birth_date`, `start_date`, `end_date`, `city`, `email`, `messenger1`, `messenger2`, `messenger3`, `is_active`, `created_at`) VALUES
(1, 1, 'Василий', 'Худяков', '', '4 курс', '', '2500.00', 90, 1, 'Подготовка к экзамену в МГИМО', NULL, '2026-02-21', '2026-06-06', 'Москва', '', 'Telegram:@Neon_tm122', '', '', 1, '2026-03-14 18:38:28'),
(2, 1, 'Роман', 'Ходаков', '', '9', '', '2000.00', 120, 1, 'Помощь в учебе. Хорошая оценка на экзамене', '2010-01-08', NULL, NULL, '', '', '', '', '', 1, '2026-03-14 19:06:02'),
(3, 1, 'Илья', 'Пелагеин', '', '11', '', '1500.00', 90, 1, 'Сдача ЕГЭ по информатике', NULL, '2024-10-14', '2026-05-31', 'Одинцово', '', '', '', '', 1, '2026-03-15 15:32:09'),
(4, 1, 'Алина', 'Князькина', '', '3 курс', '', '2500.00', 90, 1, 'Сдача ЕГЭ по информатике', NULL, NULL, NULL, 'Одинцово', '', '', '', '', 1, '2026-03-15 15:33:55'),
(5, 1, 'Тимофей', 'Курин', '', '4 курс', '', NULL, 60, 1, '', NULL, NULL, NULL, '', '', '', '', '', 1, '2026-03-15 15:34:28'),
(6, 1, 'Серафим', 'Пустовойт', '', '11', '', NULL, 60, 1, '', NULL, NULL, NULL, '', '', '', '', '', 1, '2026-03-15 15:34:54'),
(7, 1, 'Дмитрий', 'Родин', '', '11', '', NULL, 60, 1, '', NULL, NULL, NULL, '', '', '', '', '', 1, '2026-03-15 15:35:13'),
(8, 1, 'Мария', 'Тупикова', 'Павловна', '7', '', NULL, 60, 1, '', NULL, NULL, NULL, '', '', '', '', '', 1, '2026-03-15 15:35:33'),
(9, 1, 'Раиль', 'Нуретдинов', '', '11', '', NULL, 60, 1, '', NULL, NULL, NULL, '', '', '', '', '', 1, '2026-03-15 15:36:26');

-- --------------------------------------------------------

--
-- Структура таблицы `student_comments`
--

CREATE TABLE `student_comments` (
  `id` int NOT NULL,
  `student_id` int NOT NULL,
  `user_id` int NOT NULL,
  `comment` text COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `student_comments`
--

INSERT INTO `student_comments` (`id`, `student_id`, `user_id`, `comment`, `created_at`) VALUES
(1, 1, 1, 'Не адекватная оценка своих знаний', '2026-03-15 00:27:41'),
(2, 1, 1, 'Самостоятельно не работает.\r\nЕсли так будет продолжаться дальше, то он не сможет сдать внутренний экзамен', '2026-03-15 07:31:22'),
(3, 1, 1, 'Приходится изучать программирование с самого начала, что конечно очень плохо, так как очень мало времени.', '2026-03-15 07:39:04'),
(4, 4, 1, 'Очень старательная студентка.\r\nОтветственно подходит к обучению.', '2026-03-17 18:12:49'),
(5, 3, 1, 'В журнале отражены не все занятия.\r\nНа первых уроках изучали HTML и делали проект для школы, в которой учился Илья.\r\nМимоходом затрагивали темы ЕГЭ, но Илья практически все забыл к началу нового учебного года', '2026-03-17 22:56:56'),
(6, 1, 1, 'В очередной раз просто не вышел на занятие.\r\nНи он сам не мама не отвечают.\r\nЕсли такое повторится еще раз, нужно от него отказываться.', '2026-03-18 08:54:50'),
(7, 1, 1, 'Очень сложная систуация.\r\nУченик учится не хочет. Домашние задания не выполняет.\r\nНе понятно, что будут спрашивать на экзамене.\r\nНаписано, что задания будут соответствовать ЕГЭ, но выложены варианты ЕГЭ за 2018 год', '2026-03-22 11:13:12');

-- --------------------------------------------------------

--
-- Структура таблицы `student_history`
--

CREATE TABLE `student_history` (
  `id` int NOT NULL,
  `student_id` int NOT NULL,
  `user_id` int NOT NULL,
  `change_type` enum('create','update','delete','activate','deactivate') COLLATE utf8mb4_general_ci NOT NULL,
  `old_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `new_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `changed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

--
-- Дамп данных таблицы `student_history`
--

INSERT INTO `student_history` (`id`, `student_id`, `user_id`, `change_type`, `old_data`, `new_data`, `changed_at`) VALUES
(1, 1, 1, 'create', NULL, '{\"last_name\":\"\\u0425\\u0443\\u0434\\u044f\\u043a\\u043e\\u0432\",\"first_name\":\"\\u0412\\u0430\\u0441\\u0438\\u043b\\u0438\\u0439\",\"middle_name\":\"\",\"class\":\"\",\"phone\":\"\",\"email\":\"\",\"lesson_cost\":\"2500\",\"lesson_duration\":\"90\",\"lessons_per_week\":\"1\",\"goals\":\"\\u041f\\u043e\\u0434\\u0433\\u043e\\u0442\\u043e\\u0432\\u043a\\u0430 \\u043a \\u044d\\u043a\\u0437\\u0430\\u043c\\u0435\\u043d\\u0443 \\u0432 \\u041c\\u0413\\u0418\\u041c\\u041e\",\"birth_date\":\"\",\"start_date\":\"\",\"end_date\":\"\",\"city\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"save_student\":\"\"}', '2026-03-14 18:38:28'),
(2, 2, 1, 'create', NULL, '{\"last_name\":\"\\u0425\\u043e\\u0434\\u0430\\u043a\\u043e\\u0432\",\"first_name\":\"\\u0420\\u043e\\u043c\\u0430\\u043d\",\"middle_name\":\"\",\"class\":\"9\",\"phone\":\"\",\"email\":\"\",\"lesson_cost\":\"2000\",\"lesson_duration\":\"120\",\"lessons_per_week\":\"1\",\"goals\":\"\\u041f\\u043e\\u043c\\u043e\\u0449\\u044c \\u0432 \\u0443\\u0447\\u0435\\u0431\\u0435. \\u0425\\u043e\\u0440\\u043e\\u0448\\u0430\\u044f \\u043e\\u0446\\u0435\\u043d\\u043a\\u0430 \\u043d\\u0430 \\u044d\\u043a\\u0437\\u0430\\u043c\\u0435\\u043d\\u0435\",\"birth_date\":\"2010-01-08\",\"start_date\":\"\",\"end_date\":\"\",\"city\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"save_student\":\"\"}', '2026-03-14 19:06:02'),
(3, 2, 1, 'update', '{\"id\":2,\"user_id\":1,\"first_name\":\"\\u0420\\u043e\\u043c\\u0430\\u043d\",\"last_name\":\"\\u0425\\u043e\\u0434\\u0430\\u043a\\u043e\\u0432\",\"middle_name\":\"\",\"class\":\"9\",\"phone\":\"\",\"lesson_cost\":\"2000.00\",\"lesson_duration\":120,\"lessons_per_week\":1,\"goals\":\"\\u041f\\u043e\\u043c\\u043e\\u0449\\u044c \\u0432 \\u0443\\u0447\\u0435\\u0431\\u0435. \\u0425\\u043e\\u0440\\u043e\\u0448\\u0430\\u044f \\u043e\\u0446\\u0435\\u043d\\u043a\\u0430 \\u043d\\u0430 \\u044d\\u043a\\u0437\\u0430\\u043c\\u0435\\u043d\\u0435\",\"birth_date\":\"2010-01-08\",\"start_date\":null,\"end_date\":null,\"city\":\"\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"is_active\":1,\"created_at\":\"2026-03-14 22:06:02\",\"updated_at\":\"2026-03-14 22:06:02\"}', '{\"id\":2,\"user_id\":1,\"first_name\":\"\\u0420\\u043e\\u043c\\u0430\\u043d\",\"last_name\":\"\\u0425\\u043e\\u0434\\u0430\\u043a\\u043e\\u0432\",\"middle_name\":\"\",\"class\":\"9\",\"phone\":\"\",\"lesson_cost\":\"2000.00\",\"lesson_duration\":120,\"lessons_per_week\":1,\"goals\":\"\\u041f\\u043e\\u043c\\u043e\\u0449\\u044c \\u0432 \\u0443\\u0447\\u0435\\u0431\\u0435. \\u0425\\u043e\\u0440\\u043e\\u0448\\u0430\\u044f \\u043e\\u0446\\u0435\\u043d\\u043a\\u0430 \\u043d\\u0430 \\u044d\\u043a\\u0437\\u0430\\u043c\\u0435\\u043d\\u0435\",\"birth_date\":\"2010-01-08\",\"start_date\":null,\"end_date\":null,\"city\":\"\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"is_active\":1,\"created_at\":\"2026-03-14 22:06:02\",\"updated_at\":\"2026-03-14 22:06:02\"}', '2026-03-14 19:16:34'),
(4, 1, 1, 'update', '{\"id\":1,\"user_id\":1,\"first_name\":\"\\u0412\\u0430\\u0441\\u0438\\u043b\\u0438\\u0439\",\"last_name\":\"\\u0425\\u0443\\u0434\\u044f\\u043a\\u043e\\u0432\",\"middle_name\":\"\",\"class\":\"\",\"phone\":\"\",\"lesson_cost\":\"2500.00\",\"lesson_duration\":90,\"lessons_per_week\":1,\"goals\":\"\\u041f\\u043e\\u0434\\u0433\\u043e\\u0442\\u043e\\u0432\\u043a\\u0430 \\u043a \\u044d\\u043a\\u0437\\u0430\\u043c\\u0435\\u043d\\u0443 \\u0432 \\u041c\\u0413\\u0418\\u041c\\u041e\",\"birth_date\":null,\"start_date\":null,\"end_date\":null,\"city\":\"\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"is_active\":1,\"created_at\":\"2026-03-14 21:38:28\",\"updated_at\":\"2026-03-14 21:38:28\"}', '{\"id\":1,\"user_id\":1,\"first_name\":\"\\u0412\\u0430\\u0441\\u0438\\u043b\\u0438\\u0439\",\"last_name\":\"\\u0425\\u0443\\u0434\\u044f\\u043a\\u043e\\u0432\",\"middle_name\":\"\",\"class\":\"\",\"phone\":\"\",\"lesson_cost\":\"2500.00\",\"lesson_duration\":90,\"lessons_per_week\":1,\"goals\":\"\\u041f\\u043e\\u0434\\u0433\\u043e\\u0442\\u043e\\u0432\\u043a\\u0430 \\u043a \\u044d\\u043a\\u0437\\u0430\\u043c\\u0435\\u043d\\u0443 \\u0432 \\u041c\\u0413\\u0418\\u041c\\u041e\",\"birth_date\":null,\"start_date\":null,\"end_date\":null,\"city\":\"\\u041c\\u043e\\u0441\\u043a\\u0432\\u0430\",\"email\":\"\",\"messenger1\":\"Telegram:@Neon_tm122\",\"messenger2\":\"\",\"messenger3\":\"\",\"is_active\":1,\"created_at\":\"2026-03-14 21:38:28\",\"updated_at\":\"2026-03-15 10:32:51\"}', '2026-03-15 07:32:51'),
(5, 3, 1, 'create', NULL, '{\"last_name\":\"\\u041f\\u0435\\u043b\\u0430\\u0433\\u0435\\u0438\\u043d\",\"first_name\":\"\\u0418\\u043b\\u044c\\u044f\",\"middle_name\":\"\",\"class\":\"11\",\"phone\":\"\",\"email\":\"\",\"lesson_cost\":\"1500\",\"lesson_duration\":\"90\",\"lessons_per_week\":\"1\",\"goals\":\"\\u0421\\u0434\\u0430\\u0447\\u0430 \\u0415\\u0413\\u042d \\u043f\\u043e \\u0438\\u043d\\u0444\\u043e\\u0440\\u043c\\u0430\\u0442\\u0438\\u043a\\u0435\",\"birth_date\":\"\",\"start_date\":\"\",\"end_date\":\"\",\"city\":\"\\u041e\\u0434\\u0438\\u043d\\u0446\\u043e\\u0432\\u043e\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"save_student\":\"\"}', '2026-03-15 15:32:09'),
(6, 4, 1, 'create', NULL, '{\"last_name\":\"\\u041a\\u043d\\u044f\\u0437\\u044c\\u043a\\u0438\\u043d\\u0430\",\"first_name\":\"\\u0410\\u043b\\u0438\\u043d\\u0430\",\"middle_name\":\"\",\"class\":\"\",\"phone\":\"\",\"email\":\"\",\"lesson_cost\":\"2500\",\"lesson_duration\":\"90\",\"lessons_per_week\":\"1\",\"goals\":\"\\u0421\\u0434\\u0430\\u0447\\u0430 \\u0415\\u0413\\u042d \\u043f\\u043e \\u0438\\u043d\\u0444\\u043e\\u0440\\u043c\\u0430\\u0442\\u0438\\u043a\\u0435\",\"birth_date\":\"\",\"start_date\":\"\",\"end_date\":\"\",\"city\":\"\\u041e\\u0434\\u0438\\u043d\\u0446\\u043e\\u0432\\u043e\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"save_student\":\"\"}', '2026-03-15 15:33:55'),
(7, 5, 1, 'create', NULL, '{\"last_name\":\"\\u041a\\u0443\\u0440\\u0438\\u043d\",\"first_name\":\"\\u0422\\u0438\\u043c\\u043e\\u0444\\u0435\\u0439\",\"middle_name\":\"\",\"class\":\"\",\"phone\":\"\",\"email\":\"\",\"lesson_cost\":\"\",\"lesson_duration\":\"60\",\"lessons_per_week\":\"1\",\"goals\":\"\",\"birth_date\":\"\",\"start_date\":\"\",\"end_date\":\"\",\"city\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"save_student\":\"\"}', '2026-03-15 15:34:28'),
(8, 6, 1, 'create', NULL, '{\"last_name\":\"\\u041f\\u0443\\u0441\\u0442\\u043e\\u0432\\u043e\\u0439\\u0442\",\"first_name\":\"\\u0421\\u0435\\u0440\\u0430\\u0444\\u0438\\u043c\",\"middle_name\":\"\",\"class\":\"\",\"phone\":\"\",\"email\":\"\",\"lesson_cost\":\"\",\"lesson_duration\":\"60\",\"lessons_per_week\":\"1\",\"goals\":\"\",\"birth_date\":\"\",\"start_date\":\"\",\"end_date\":\"\",\"city\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"save_student\":\"\"}', '2026-03-15 15:34:54'),
(9, 7, 1, 'create', NULL, '{\"last_name\":\"\\u0420\\u043e\\u0434\\u0438\\u043d\",\"first_name\":\"\\u0414\\u043c\\u0438\\u0442\\u0440\\u0438\\u0439\",\"middle_name\":\"\",\"class\":\"\",\"phone\":\"\",\"email\":\"\",\"lesson_cost\":\"\",\"lesson_duration\":\"60\",\"lessons_per_week\":\"1\",\"goals\":\"\",\"birth_date\":\"\",\"start_date\":\"\",\"end_date\":\"\",\"city\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"save_student\":\"\"}', '2026-03-15 15:35:13'),
(10, 8, 1, 'create', NULL, '{\"last_name\":\"\\u0422\\u0443\\u043f\\u0438\\u043a\\u043e\\u0432\\u0430\",\"first_name\":\"\\u041c\\u0430\\u0440\\u0438\\u044f\",\"middle_name\":\"\",\"class\":\"\",\"phone\":\"\",\"email\":\"\",\"lesson_cost\":\"\",\"lesson_duration\":\"60\",\"lessons_per_week\":\"1\",\"goals\":\"\",\"birth_date\":\"\",\"start_date\":\"\",\"end_date\":\"\",\"city\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"save_student\":\"\"}', '2026-03-15 15:35:33'),
(11, 9, 1, 'create', NULL, '{\"last_name\":\"\\u041d\\u0443\\u0440\\u0435\\u0438\\u0442\\u0434\\u0438\\u043d\\u043e\\u0432\",\"first_name\":\"\\u0420\\u0430\\u0438\\u043b\\u044c\",\"middle_name\":\"\",\"class\":\"\",\"phone\":\"\",\"email\":\"\",\"lesson_cost\":\"\",\"lesson_duration\":\"60\",\"lessons_per_week\":\"1\",\"goals\":\"\",\"birth_date\":\"\",\"start_date\":\"\",\"end_date\":\"\",\"city\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"save_student\":\"\"}', '2026-03-15 15:36:26'),
(12, 9, 1, 'update', '{\"id\":9,\"user_id\":1,\"first_name\":\"\\u0420\\u0430\\u0438\\u043b\\u044c\",\"last_name\":\"\\u041d\\u0443\\u0440\\u0435\\u0438\\u0442\\u0434\\u0438\\u043d\\u043e\\u0432\",\"middle_name\":\"\",\"class\":\"\",\"phone\":\"\",\"lesson_cost\":null,\"lesson_duration\":60,\"lessons_per_week\":1,\"goals\":\"\",\"birth_date\":null,\"start_date\":null,\"end_date\":null,\"city\":\"\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"is_active\":1,\"created_at\":\"2026-03-15 18:36:26\",\"updated_at\":\"2026-03-15 18:36:26\"}', '{\"id\":9,\"user_id\":1,\"first_name\":\"\\u0420\\u0430\\u0438\\u043b\\u044c\",\"last_name\":\"\\u041d\\u0443\\u0440\\u0435\\u0442\\u0434\\u0438\\u043d\\u043e\\u0432\",\"middle_name\":\"\",\"class\":\"\",\"phone\":\"\",\"lesson_cost\":null,\"lesson_duration\":60,\"lessons_per_week\":1,\"goals\":\"\",\"birth_date\":null,\"start_date\":null,\"end_date\":null,\"city\":\"\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"is_active\":1,\"created_at\":\"2026-03-15 18:36:26\",\"updated_at\":\"2026-03-17 01:48:49\"}', '2026-03-16 22:48:49'),
(13, 8, 1, 'update', '{\"id\":8,\"user_id\":1,\"first_name\":\"\\u041c\\u0430\\u0440\\u0438\\u044f\",\"last_name\":\"\\u0422\\u0443\\u043f\\u0438\\u043a\\u043e\\u0432\\u0430\",\"middle_name\":\"\",\"class\":\"\",\"phone\":\"\",\"lesson_cost\":null,\"lesson_duration\":60,\"lessons_per_week\":1,\"goals\":\"\",\"birth_date\":null,\"start_date\":null,\"end_date\":null,\"city\":\"\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"is_active\":1,\"created_at\":\"2026-03-15 18:35:33\",\"updated_at\":\"2026-03-15 18:35:33\"}', '{\"id\":8,\"user_id\":1,\"first_name\":\"\\u041c\\u0430\\u0440\\u0438\\u044f\",\"last_name\":\"\\u0422\\u0443\\u043f\\u0438\\u043a\\u043e\\u0432\\u0430\",\"middle_name\":\"\\u041f\\u0430\\u0432\\u043b\\u043e\\u0432\\u043d\\u0430\",\"class\":\"7\",\"phone\":\"\",\"lesson_cost\":null,\"lesson_duration\":60,\"lessons_per_week\":1,\"goals\":\"\",\"birth_date\":null,\"start_date\":null,\"end_date\":null,\"city\":\"\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"is_active\":1,\"created_at\":\"2026-03-15 18:35:33\",\"updated_at\":\"2026-03-17 14:19:54\"}', '2026-03-17 11:19:54'),
(14, 4, 1, 'update', '{\"id\":4,\"user_id\":1,\"first_name\":\"\\u0410\\u043b\\u0438\\u043d\\u0430\",\"last_name\":\"\\u041a\\u043d\\u044f\\u0437\\u044c\\u043a\\u0438\\u043d\\u0430\",\"middle_name\":\"\",\"class\":\"\",\"phone\":\"\",\"lesson_cost\":\"2500.00\",\"lesson_duration\":90,\"lessons_per_week\":1,\"goals\":\"\\u0421\\u0434\\u0430\\u0447\\u0430 \\u0415\\u0413\\u042d \\u043f\\u043e \\u0438\\u043d\\u0444\\u043e\\u0440\\u043c\\u0430\\u0442\\u0438\\u043a\\u0435\",\"birth_date\":null,\"start_date\":null,\"end_date\":null,\"city\":\"\\u041e\\u0434\\u0438\\u043d\\u0446\\u043e\\u0432\\u043e\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"is_active\":1,\"created_at\":\"2026-03-15 18:33:55\",\"updated_at\":\"2026-03-15 18:33:55\"}', '{\"id\":4,\"user_id\":1,\"first_name\":\"\\u0410\\u043b\\u0438\\u043d\\u0430\",\"last_name\":\"\\u041a\\u043d\\u044f\\u0437\\u044c\\u043a\\u0438\\u043d\\u0430\",\"middle_name\":\"\",\"class\":\"3 \\u043a\\u0443\\u0440\\u0441\",\"phone\":\"\",\"lesson_cost\":\"2500.00\",\"lesson_duration\":90,\"lessons_per_week\":1,\"goals\":\"\\u0421\\u0434\\u0430\\u0447\\u0430 \\u0415\\u0413\\u042d \\u043f\\u043e \\u0438\\u043d\\u0444\\u043e\\u0440\\u043c\\u0430\\u0442\\u0438\\u043a\\u0435\",\"birth_date\":null,\"start_date\":null,\"end_date\":null,\"city\":\"\\u041e\\u0434\\u0438\\u043d\\u0446\\u043e\\u0432\\u043e\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"is_active\":1,\"created_at\":\"2026-03-15 18:33:55\",\"updated_at\":\"2026-03-17 14:34:09\"}', '2026-03-17 11:34:09'),
(15, 5, 1, 'update', '{\"id\":5,\"user_id\":1,\"first_name\":\"\\u0422\\u0438\\u043c\\u043e\\u0444\\u0435\\u0439\",\"last_name\":\"\\u041a\\u0443\\u0440\\u0438\\u043d\",\"middle_name\":\"\",\"class\":\"\",\"phone\":\"\",\"lesson_cost\":null,\"lesson_duration\":60,\"lessons_per_week\":1,\"goals\":\"\",\"birth_date\":null,\"start_date\":null,\"end_date\":null,\"city\":\"\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"is_active\":1,\"created_at\":\"2026-03-15 18:34:28\",\"updated_at\":\"2026-03-15 18:34:28\"}', '{\"id\":5,\"user_id\":1,\"first_name\":\"\\u0422\\u0438\\u043c\\u043e\\u0444\\u0435\\u0439\",\"last_name\":\"\\u041a\\u0443\\u0440\\u0438\\u043d\",\"middle_name\":\"\",\"class\":\"4 \\u043a\\u0443\\u0440\\u0441\",\"phone\":\"\",\"lesson_cost\":null,\"lesson_duration\":60,\"lessons_per_week\":1,\"goals\":\"\",\"birth_date\":null,\"start_date\":null,\"end_date\":null,\"city\":\"\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"is_active\":1,\"created_at\":\"2026-03-15 18:34:28\",\"updated_at\":\"2026-03-17 14:34:43\"}', '2026-03-17 11:34:43'),
(16, 6, 1, 'update', '{\"id\":6,\"user_id\":1,\"first_name\":\"\\u0421\\u0435\\u0440\\u0430\\u0444\\u0438\\u043c\",\"last_name\":\"\\u041f\\u0443\\u0441\\u0442\\u043e\\u0432\\u043e\\u0439\\u0442\",\"middle_name\":\"\",\"class\":\"\",\"phone\":\"\",\"lesson_cost\":null,\"lesson_duration\":60,\"lessons_per_week\":1,\"goals\":\"\",\"birth_date\":null,\"start_date\":null,\"end_date\":null,\"city\":\"\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"is_active\":1,\"created_at\":\"2026-03-15 18:34:54\",\"updated_at\":\"2026-03-15 18:34:54\"}', '{\"id\":6,\"user_id\":1,\"first_name\":\"\\u0421\\u0435\\u0440\\u0430\\u0444\\u0438\\u043c\",\"last_name\":\"\\u041f\\u0443\\u0441\\u0442\\u043e\\u0432\\u043e\\u0439\\u0442\",\"middle_name\":\"\",\"class\":\"11\",\"phone\":\"\",\"lesson_cost\":null,\"lesson_duration\":60,\"lessons_per_week\":1,\"goals\":\"\",\"birth_date\":null,\"start_date\":null,\"end_date\":null,\"city\":\"\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"is_active\":1,\"created_at\":\"2026-03-15 18:34:54\",\"updated_at\":\"2026-03-17 14:34:52\"}', '2026-03-17 11:34:52'),
(17, 9, 1, 'update', '{\"id\":9,\"user_id\":1,\"first_name\":\"\\u0420\\u0430\\u0438\\u043b\\u044c\",\"last_name\":\"\\u041d\\u0443\\u0440\\u0435\\u0442\\u0434\\u0438\\u043d\\u043e\\u0432\",\"middle_name\":\"\",\"class\":\"\",\"phone\":\"\",\"lesson_cost\":null,\"lesson_duration\":60,\"lessons_per_week\":1,\"goals\":\"\",\"birth_date\":null,\"start_date\":null,\"end_date\":null,\"city\":\"\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"is_active\":1,\"created_at\":\"2026-03-15 18:36:26\",\"updated_at\":\"2026-03-17 01:48:49\"}', '{\"id\":9,\"user_id\":1,\"first_name\":\"\\u0420\\u0430\\u0438\\u043b\\u044c\",\"last_name\":\"\\u041d\\u0443\\u0440\\u0435\\u0442\\u0434\\u0438\\u043d\\u043e\\u0432\",\"middle_name\":\"\",\"class\":\"11\",\"phone\":\"\",\"lesson_cost\":null,\"lesson_duration\":60,\"lessons_per_week\":1,\"goals\":\"\",\"birth_date\":null,\"start_date\":null,\"end_date\":null,\"city\":\"\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"is_active\":1,\"created_at\":\"2026-03-15 18:36:26\",\"updated_at\":\"2026-03-17 14:35:03\"}', '2026-03-17 11:35:03'),
(18, 7, 1, 'update', '{\"id\":7,\"user_id\":1,\"first_name\":\"\\u0414\\u043c\\u0438\\u0442\\u0440\\u0438\\u0439\",\"last_name\":\"\\u0420\\u043e\\u0434\\u0438\\u043d\",\"middle_name\":\"\",\"class\":\"\",\"phone\":\"\",\"lesson_cost\":null,\"lesson_duration\":60,\"lessons_per_week\":1,\"goals\":\"\",\"birth_date\":null,\"start_date\":null,\"end_date\":null,\"city\":\"\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"is_active\":1,\"created_at\":\"2026-03-15 18:35:13\",\"updated_at\":\"2026-03-15 18:35:13\"}', '{\"id\":7,\"user_id\":1,\"first_name\":\"\\u0414\\u043c\\u0438\\u0442\\u0440\\u0438\\u0439\",\"last_name\":\"\\u0420\\u043e\\u0434\\u0438\\u043d\",\"middle_name\":\"\",\"class\":\"11\",\"phone\":\"\",\"lesson_cost\":null,\"lesson_duration\":60,\"lessons_per_week\":1,\"goals\":\"\",\"birth_date\":null,\"start_date\":null,\"end_date\":null,\"city\":\"\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"is_active\":1,\"created_at\":\"2026-03-15 18:35:13\",\"updated_at\":\"2026-03-17 14:35:10\"}', '2026-03-17 11:35:10'),
(19, 1, 1, 'update', '{\"id\":1,\"user_id\":1,\"first_name\":\"\\u0412\\u0430\\u0441\\u0438\\u043b\\u0438\\u0439\",\"last_name\":\"\\u0425\\u0443\\u0434\\u044f\\u043a\\u043e\\u0432\",\"middle_name\":\"\",\"class\":\"\",\"phone\":\"\",\"lesson_cost\":\"2500.00\",\"lesson_duration\":90,\"lessons_per_week\":1,\"goals\":\"\\u041f\\u043e\\u0434\\u0433\\u043e\\u0442\\u043e\\u0432\\u043a\\u0430 \\u043a \\u044d\\u043a\\u0437\\u0430\\u043c\\u0435\\u043d\\u0443 \\u0432 \\u041c\\u0413\\u0418\\u041c\\u041e\",\"birth_date\":null,\"start_date\":null,\"end_date\":null,\"city\":\"\\u041c\\u043e\\u0441\\u043a\\u0432\\u0430\",\"email\":\"\",\"messenger1\":\"Telegram:@Neon_tm122\",\"messenger2\":\"\",\"messenger3\":\"\",\"is_active\":1,\"created_at\":\"2026-03-14 21:38:28\",\"updated_at\":\"2026-03-15 10:32:51\"}', '{\"id\":1,\"user_id\":1,\"first_name\":\"\\u0412\\u0430\\u0441\\u0438\\u043b\\u0438\\u0439\",\"last_name\":\"\\u0425\\u0443\\u0434\\u044f\\u043a\\u043e\\u0432\",\"middle_name\":\"\",\"class\":\"4 \\u043a\\u0443\\u0440\\u0441\",\"phone\":\"\",\"lesson_cost\":\"2500.00\",\"lesson_duration\":90,\"lessons_per_week\":1,\"goals\":\"\\u041f\\u043e\\u0434\\u0433\\u043e\\u0442\\u043e\\u0432\\u043a\\u0430 \\u043a \\u044d\\u043a\\u0437\\u0430\\u043c\\u0435\\u043d\\u0443 \\u0432 \\u041c\\u0413\\u0418\\u041c\\u041e\",\"birth_date\":null,\"start_date\":null,\"end_date\":null,\"city\":\"\\u041c\\u043e\\u0441\\u043a\\u0432\\u0430\",\"email\":\"\",\"messenger1\":\"Telegram:@Neon_tm122\",\"messenger2\":\"\",\"messenger3\":\"\",\"is_active\":1,\"created_at\":\"2026-03-14 21:38:28\",\"updated_at\":\"2026-03-17 14:35:19\"}', '2026-03-17 11:35:19'),
(20, 3, 1, 'update', '{\"id\":3,\"user_id\":1,\"first_name\":\"\\u0418\\u043b\\u044c\\u044f\",\"last_name\":\"\\u041f\\u0435\\u043b\\u0430\\u0433\\u0435\\u0438\\u043d\",\"middle_name\":\"\",\"class\":\"11\",\"phone\":\"\",\"lesson_cost\":\"1500.00\",\"lesson_duration\":90,\"lessons_per_week\":1,\"goals\":\"\\u0421\\u0434\\u0430\\u0447\\u0430 \\u0415\\u0413\\u042d \\u043f\\u043e \\u0438\\u043d\\u0444\\u043e\\u0440\\u043c\\u0430\\u0442\\u0438\\u043a\\u0435\",\"birth_date\":null,\"start_date\":null,\"end_date\":null,\"city\":\"\\u041e\\u0434\\u0438\\u043d\\u0446\\u043e\\u0432\\u043e\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"is_active\":1,\"created_at\":\"2026-03-15 18:32:09\",\"updated_at\":\"2026-03-15 18:32:09\"}', '{\"id\":3,\"user_id\":1,\"first_name\":\"\\u0418\\u043b\\u044c\\u044f\",\"last_name\":\"\\u041f\\u0435\\u043b\\u0430\\u0433\\u0435\\u0438\\u043d\",\"middle_name\":\"\",\"class\":\"11\",\"phone\":\"\",\"lesson_cost\":\"1500.00\",\"lesson_duration\":90,\"lessons_per_week\":1,\"goals\":\"\\u0421\\u0434\\u0430\\u0447\\u0430 \\u0415\\u0413\\u042d \\u043f\\u043e \\u0438\\u043d\\u0444\\u043e\\u0440\\u043c\\u0430\\u0442\\u0438\\u043a\\u0435\",\"birth_date\":null,\"start_date\":\"2024-10-14\",\"end_date\":\"2026-05-31\",\"city\":\"\\u041e\\u0434\\u0438\\u043d\\u0446\\u043e\\u0432\\u043e\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"is_active\":1,\"created_at\":\"2026-03-15 18:32:09\",\"updated_at\":\"2026-03-18 02:02:02\"}', '2026-03-17 23:02:02'),
(21, 1, 1, 'update', '{\"id\":1,\"user_id\":1,\"first_name\":\"\\u0412\\u0430\\u0441\\u0438\\u043b\\u0438\\u0439\",\"last_name\":\"\\u0425\\u0443\\u0434\\u044f\\u043a\\u043e\\u0432\",\"middle_name\":\"\",\"class\":\"4 \\u043a\\u0443\\u0440\\u0441\",\"phone\":\"\",\"lesson_cost\":\"2500.00\",\"lesson_duration\":90,\"lessons_per_week\":1,\"goals\":\"\\u041f\\u043e\\u0434\\u0433\\u043e\\u0442\\u043e\\u0432\\u043a\\u0430 \\u043a \\u044d\\u043a\\u0437\\u0430\\u043c\\u0435\\u043d\\u0443 \\u0432 \\u041c\\u0413\\u0418\\u041c\\u041e\",\"birth_date\":null,\"start_date\":null,\"end_date\":null,\"city\":\"\\u041c\\u043e\\u0441\\u043a\\u0432\\u0430\",\"email\":\"\",\"messenger1\":\"Telegram:@Neon_tm122\",\"messenger2\":\"\",\"messenger3\":\"\",\"is_active\":1,\"created_at\":\"2026-03-14 21:38:28\",\"updated_at\":\"2026-03-17 14:35:19\"}', '{\"id\":1,\"user_id\":1,\"first_name\":\"\\u0412\\u0430\\u0441\\u0438\\u043b\\u0438\\u0439\",\"last_name\":\"\\u0425\\u0443\\u0434\\u044f\\u043a\\u043e\\u0432\",\"middle_name\":\"\",\"class\":\"4 \\u043a\\u0443\\u0440\\u0441\",\"phone\":\"\",\"lesson_cost\":\"2500.00\",\"lesson_duration\":90,\"lessons_per_week\":1,\"goals\":\"\\u041f\\u043e\\u0434\\u0433\\u043e\\u0442\\u043e\\u0432\\u043a\\u0430 \\u043a \\u044d\\u043a\\u0437\\u0430\\u043c\\u0435\\u043d\\u0443 \\u0432 \\u041c\\u0413\\u0418\\u041c\\u041e\",\"birth_date\":null,\"start_date\":\"2026-02-21\",\"end_date\":\"2026-06-06\",\"city\":\"\\u041c\\u043e\\u0441\\u043a\\u0432\\u0430\",\"email\":\"\",\"messenger1\":\"Telegram:@Neon_tm122\",\"messenger2\":\"\",\"messenger3\":\"\",\"is_active\":1,\"created_at\":\"2026-03-14 21:38:28\",\"updated_at\":\"2026-03-22 16:31:03\"}', '2026-03-22 13:31:03');

-- --------------------------------------------------------

--
-- Структура таблицы `student_labels`
--

CREATE TABLE `student_labels` (
  `student_id` int NOT NULL,
  `label_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `topics`
--

CREATE TABLE `topics` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `category_id` int DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `topics`
--

INSERT INTO `topics` (`id`, `user_id`, `category_id`, `name`, `description`, `created_at`) VALUES
(1, 1, 2, '3. Базы данных. Файловая система', '', '2026-03-14 20:14:15'),
(2, 1, 2, '4. Кодирование и декодирование информации', '', '2026-03-14 20:14:15'),
(3, 1, 2, '5. Анализ и построение алгоритмов для исполнителей', '', '2026-03-14 20:14:15'),
(4, 1, 2, '6. Анализ программ', 'ЕГЭ 2029', '2026-03-14 20:14:15'),
(5, 1, 2, '7. Кодирование и декодирование информации. Передача информации', '', '2026-03-14 20:14:15'),
(6, 1, 2, '8. Перебор слов и системы счисления', '', '2026-03-14 20:14:15'),
(7, 1, 2, '9. Эксель в Excel', '', '2026-03-14 20:14:15'),
(8, 1, 2, '9. Эксель в Python', '', '2026-03-14 20:14:15'),
(9, 1, 2, '10. Поиск символов в текстовом редакторе', '', '2026-03-14 20:14:15'),
(10, 1, 2, '11. Вычисление количества информации', '', '2026-03-14 20:14:15'),
(11, 1, 2, '12. Выполнение алгоритмов для исполнителей', '', '2026-03-14 20:14:15'),
(13, 1, 2, '14. Кодирование чисел. Системы счисления', 'ЕГЭ 2038', '2026-03-14 20:14:15'),
(14, 1, 2, '15. Преобразование логических выражений', '', '2026-03-14 20:14:15'),
(15, 1, 2, '16. Рекурсивные алгоритмы', '', '2026-03-14 20:14:15'),
(16, 1, 2, '17. Проверка на делимость', 'ЕГЭ 2041', '2026-03-14 20:14:15'),
(17, 1, 2, '18. Робот-сборщик монет', '', '2026-03-14 20:14:15'),
(18, 1, 2, '19. Выигрышная стратегия. Задание 1', '', '2026-03-14 20:14:15'),
(19, 1, 2, '20. Выигрышная стратегия. Задание 2', '', '2026-03-14 20:14:15'),
(20, 1, 2, '21. Выигрышная стратегия. Задание 3', '', '2026-03-14 20:14:15'),
(21, 1, 2, '22.  Анализ программы с циклами и условными операторами 2', '', '2026-03-14 20:14:15'),
(22, 1, 2, '22. Посимвольная обработка восьмеричных чисел', '', '2026-03-14 20:14:15'),
(23, 1, 2, '22. Многопроцессорные системы', '', '2026-03-14 20:14:15'),
(24, 1, 2, '23. Количество программ', '', '2026-03-14 20:14:15'),
(25, 1, 2, '24. Обработка символьных строк', '', '2026-03-14 20:14:15'),
(26, 1, 2, '25. Обработка целочисленной информации', 'ЕГЭ 2051', '2026-03-14 20:14:15'),
(27, 1, 2, '26. Обработка целочисленной информации', 'ЕГЭ 2052', '2026-03-14 20:14:15'),
(28, 1, 2, '27. Программирование', '', '2026-03-14 20:14:15'),
(29, 1, 2, 'Яндекс. Учебник. Программирование', '', '2026-03-14 20:14:15'),
(30, 1, 2, 'Программирование', '', '2026-03-14 20:14:15'),
(31, 1, 2, 'КЕГЭ', '', '2026-03-14 20:14:15'),
(32, 1, 2, 'Повторение', '', '2026-03-14 20:14:15'),
(33, 1, 2, '1. Анализ информационных моделей', '', '2026-03-14 20:24:02'),
(34, 1, 2, '2. Построение таблиц истинности логических выражений', '', '2026-03-14 20:24:32'),
(35, 1, 3, 'Системы счисления', '', '2026-03-14 20:27:56'),
(61, 1, 4, '1. Введение в программирование', '', '2026-03-14 23:52:01'),
(62, 1, 4, '2. Ввод/вывод и арифметика', '', '2026-03-14 23:52:01'),
(63, 1, 4, '3. Арифметика строк', '', '2026-03-14 23:52:01'),
(64, 1, 4, '4. Арифметика чисел', '', '2026-03-14 23:52:01'),
(65, 1, 4, '5. Разбор задач', '', '2026-03-14 23:52:01'),
(66, 1, 4, '6. Арифметика и условия', '', '2026-03-14 23:52:02'),
(67, 1, 4, '7. Цикл с параметром', '', '2026-03-14 23:52:02'),
(68, 1, 4, '8. Переменная цикла for', '', '2026-03-14 23:52:02'),
(69, 1, 4, '9. Варианты цикла for', '', '2026-03-14 23:52:02'),
(70, 1, 4, '10. Цикл WHILE', '', '2026-03-14 23:52:02'),
(71, 1, 4, '11. Индексы строк', '', '2026-03-14 23:52:02'),
(72, 1, 4, '12. Срезы строк', '', '2026-03-14 23:52:02'),
(73, 1, 4, '13. Сравнение строк', '', '2026-03-14 23:52:02'),
(74, 1, 4, '14. Методы строк', '', '2026-03-14 23:52:02'),
(75, 1, 4, '15. Функции. Python', '', '2026-03-14 23:52:02'),
(76, 1, 4, '16. Массивы и основные операции с ними', '', '2026-03-14 23:52:02'),
(77, 1, 4, '17. Добавление элементов в массив', '', '2026-03-14 23:52:02'),
(78, 1, 4, '18. Два типа циклов по массиву', '', '2026-03-14 23:52:02'),
(79, 1, 4, '19. Задача поиска элемента и нахождения максимального значения', '', '2026-03-14 23:52:02'),
(80, 1, 4, '20. Использование массивов для решения задач', '', '2026-03-14 23:52:02'),
(81, 1, 4, '22. Рекурсия', '', '2026-03-15 01:30:15'),
(82, 1, 4, '21. Функции', '', '2026-03-15 01:31:23'),
(83, 1, 2, '13. Организация компьютерных сетей. Адресация', '', '2026-03-16 21:37:21'),
(84, 1, 7, 'Подготовка к ВКР', '', '2026-03-17 11:16:50'),
(85, 1, 7, 'Подготовка к ДЭ', 'Демонстрационный экзамен', '2026-03-17 11:17:54'),
(86, 1, 8, 'Выполнение домашнего задания', '', '2026-03-17 11:19:02'),
(87, 1, 2, 'Пробник', 'Самостоятельное решение задач', '2026-03-17 22:25:53');

-- --------------------------------------------------------

--
-- Структура таблицы `topic_labels`
--

CREATE TABLE `topic_labels` (
  `topic_id` int NOT NULL,
  `label_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `topic_links`
--

CREATE TABLE `topic_links` (
  `id` int NOT NULL,
  `topic_id` int NOT NULL,
  `url` varchar(500) COLLATE utf8mb4_general_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `last_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `middle_name` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `is_admin` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  `reset_token` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reset_token_expires` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `first_name`, `last_name`, `middle_name`, `phone`, `is_active`, `is_admin`, `created_at`, `last_login`, `reset_token`, `reset_token_expires`) VALUES
(1, 'admin', 'admin@example.com', '$2y$10$1rre./k5Xik/76loI5m.0ukYN5GlwMzqSAbfao4lJHPXWV4eSuI86', 'Андрей', 'Заярный', '', '', 1, 1, '2026-03-14 17:33:31', NULL, NULL, NULL),
(2, 'orlova', 'orlova@mail.ru', '$2y$10$Sl11oFAPr7MGiCfWkAOEGewR.4KCEDpy6104TLxz9UvF5xT.EHY0O', 'Дарья', 'Орлова', 'Николаевна', '', 1, 0, '2026-03-16 23:32:14', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `session_token` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_general_ci,
  `expires_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
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
-- Индексы таблицы `plannings`
--
ALTER TABLE `plannings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_planning_user` (`user_id`),
  ADD KEY `idx_planning_student` (`student_id`),
  ADD KEY `idx_planning_active` (`is_active`);

--
-- Индексы таблицы `planning_history`
--
ALTER TABLE `planning_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `planning_id` (`planning_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `planning_labels`
--
ALTER TABLE `planning_labels`
  ADD PRIMARY KEY (`planning_id`,`label_id`),
  ADD KEY `label_id` (`label_id`);

--
-- Индексы таблицы `planning_rows`
--
ALTER TABLE `planning_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_planning_rows_planning` (`planning_id`),
  ADD KEY `idx_planning_rows_number` (`lesson_number`);

--
-- Индексы таблицы `planning_row_resources`
--
ALTER TABLE `planning_row_resources`
  ADD PRIMARY KEY (`row_id`,`resource_id`),
  ADD KEY `resource_id` (`resource_id`);

--
-- Индексы таблицы `planning_row_topics`
--
ALTER TABLE `planning_row_topics`
  ADD PRIMARY KEY (`row_id`,`topic_id`),
  ADD KEY `topic_id` (`topic_id`);

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT для таблицы `diaries`
--
ALTER TABLE `diaries`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT для таблицы `diary_comments`
--
ALTER TABLE `diary_comments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `homework_tasks`
--
ALTER TABLE `homework_tasks`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT для таблицы `import_export_log`
--
ALTER TABLE `import_export_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `labels`
--
ALTER TABLE `labels`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `lessons`
--
ALTER TABLE `lessons`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `lesson_resources`
--
ALTER TABLE `lesson_resources`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT для таблицы `parents`
--
ALTER TABLE `parents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `plannings`
--
ALTER TABLE `plannings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `planning_history`
--
ALTER TABLE `planning_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT для таблицы `planning_rows`
--
ALTER TABLE `planning_rows`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT для таблицы `resources`
--
ALTER TABLE `resources`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `statistics`
--
ALTER TABLE `statistics`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `students`
--
ALTER TABLE `students`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT для таблицы `student_comments`
--
ALTER TABLE `student_comments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT для таблицы `student_history`
--
ALTER TABLE `student_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `topics`
--
ALTER TABLE `topics`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

--
-- AUTO_INCREMENT для таблицы `topic_links`
--
ALTER TABLE `topic_links`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

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
-- Ограничения внешнего ключа таблицы `plannings`
--
ALTER TABLE `plannings`
  ADD CONSTRAINT `plannings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `plannings_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `plannings_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `planning_history`
--
ALTER TABLE `planning_history`
  ADD CONSTRAINT `planning_history_ibfk_1` FOREIGN KEY (`planning_id`) REFERENCES `plannings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `planning_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `planning_labels`
--
ALTER TABLE `planning_labels`
  ADD CONSTRAINT `planning_labels_ibfk_1` FOREIGN KEY (`planning_id`) REFERENCES `plannings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `planning_labels_ibfk_2` FOREIGN KEY (`label_id`) REFERENCES `labels` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `planning_rows`
--
ALTER TABLE `planning_rows`
  ADD CONSTRAINT `planning_rows_ibfk_1` FOREIGN KEY (`planning_id`) REFERENCES `plannings` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `planning_row_resources`
--
ALTER TABLE `planning_row_resources`
  ADD CONSTRAINT `planning_row_resources_ibfk_1` FOREIGN KEY (`row_id`) REFERENCES `planning_rows` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `planning_row_resources_ibfk_2` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `planning_row_topics`
--
ALTER TABLE `planning_row_topics`
  ADD CONSTRAINT `planning_row_topics_ibfk_1` FOREIGN KEY (`row_id`) REFERENCES `planning_rows` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `planning_row_topics_ibfk_2` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE;

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
