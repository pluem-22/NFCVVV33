-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql112.infinityfree.com
-- Generation Time: Sep 02, 2025 at 11:30 PM
-- Server version: 11.4.7-MariaDB
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
-- Database: `if0_39302480_nano_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `nfc_cards`
--

CREATE TABLE `nfc_cards` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `card_uid` varchar(64) NOT NULL,
  `nickname` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `nfc_cards`
--

INSERT INTO `nfc_cards` (`id`, `user_id`, `card_uid`, `nickname`, `is_active`, `created_at`) VALUES
(2, 3, 'BC643D2E', 'ซัน', 1, '2025-08-22 12:50:22'),
(3, 4, 'F2B38D6C', 'แมว', 1, '2025-08-24 09:00:36');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `transaction_id` varchar(255) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price_per_unit` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `transaction_id`, `product_id`, `quantity`, `price_per_unit`, `total_price`, `created_at`) VALUES
(1, 'TXNSEEDBUY1', 1, 1, '20.00', '20.00', '2025-08-22 12:47:14'),
(2, 'TXNT1HQGL2E4229', 1, 5, '20.00', '100.00', '2025-08-24 09:02:45'),
(3, 'TXNT1HQGZ4AB5DD', 1, 100, '20.00', '2000.00', '2025-08-24 09:02:59'),
(4, 'TXNT1HQHBD4C4E8', 1, 5, '20.00', '100.00', '2025-08-24 09:03:11'),
(5, 'TXNT1HS0KF02931', 1, 1, '20.00', '20.00', '2025-08-24 09:36:20'),
(6, 'TXNT1HS0P3DF3A9', 1, 1, '20.00', '20.00', '2025-08-24 09:36:25'),
(7, 'TXNT1L6LA68D735', 1, 10, '20.00', '200.00', '2025-08-26 05:43:58'),
(8, 'TXNT1L6MACD64CD', 3, 1, '60.00', '60.00', '2025-08-26 05:44:34'),
(9, 'TXNT1LI6347BD1E', 2, 10, '15.00', '150.00', '2025-08-26 09:54:03');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `product_name`, `price`, `stock`, `created_at`) VALUES
(1, 'น้ำ', '20.00', 78, '2025-08-22 12:47:14'),
(2, 'ขนม', '15.00', 110, '2025-08-22 12:47:14'),
(3, 'ข้าวกล่อง', '60.00', 49, '2025-08-22 12:47:14');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `transaction_id` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `transaction_date` datetime DEFAULT current_timestamp(),
  `status` varchar(50) DEFAULT 'completed',
  `customer_name` varchar(255) DEFAULT NULL,
  `type` varchar(50) DEFAULT 'buy',
  `is_paid` tinyint(1) DEFAULT 0,
  `is_confirmed` tinyint(1) DEFAULT 0,
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `transaction_id`, `amount`, `transaction_date`, `status`, `customer_name`, `type`, `is_paid`, `is_confirmed`, `user_id`) VALUES
(1, 'TXNSEEDBUY1', '20.00', '2025-08-22 12:47:14', 'completed', 'ผู้ใช้ตัวอย่าง', 'buy', 1, 1, 2),
(2, 'TXNSEEDTOP1', '50.00', '2025-08-22 12:47:14', 'completed', 'ผู้ใช้ตัวอย่าง', 'topup', 1, 1, 2),
(3, 'TXNT1EBOA95325C', '179.00', '2025-08-22 19:50:34', 'completed', 'post', 'topup', 1, 1, 3),
(4, 'TXNT1HQDN868C37', '500.00', '2025-08-24 16:00:59', 'completed', 'ธนสรรค์', 'topup', 1, 1, 4),
(5, 'TXNT1HQGL2E4229', '100.00', '2025-08-24 16:02:45', 'completed', 'ธนสรรค์', 'buy', 1, 1, 4),
(6, 'TXNT1HQGZ4AB5DD', '2000.00', '2025-08-24 16:02:59', 'completed', 'ธนสรรค์', 'buy', 1, 1, 4),
(7, 'TXNT1HQHBD4C4E8', '100.00', '2025-08-24 16:03:11', 'completed', 'ธนสรรค์', 'buy', 1, 1, 4),
(8, 'TXNT1HQKZ5416B0', '2000.00', '2025-08-24 16:05:23', 'completed', 'ธนสรรค์', 'topup', 1, 1, 4),
(9, 'TXNT1HS0C9F62B9', '179.00', '2025-08-24 16:36:12', 'completed', 'ธนสรรค์', 'topup', 1, 1, 4),
(10, 'TXNT1HS0KF02931', '20.00', '2025-08-24 16:36:20', 'completed', 'ธนสรรค์', 'buy', 1, 1, 4),
(11, 'TXNT1HS0P3DF3A9', '20.00', '2025-08-24 16:36:25', 'completed', 'ธนสรรค์', 'buy', 1, 1, 4),
(12, 'TXNT1L6JX061BBC', '200.00', '2025-08-25 22:43:09', 'completed', 'ธนสรรค์', 'topup', 1, 1, 4),
(13, 'TXNT1L6LA68D735', '200.00', '2025-08-25 22:43:58', 'completed', 'ธนสรรค์', 'buy', 1, 1, 4),
(14, 'TXNT1L6MACD64CD', '60.00', '2025-08-25 22:44:34', 'completed', 'ธนสรรค์', 'buy', 1, 1, 4),
(15, 'TXNT1LI5NAC1607', '150.00', '2025-08-26 02:53:47', 'completed', 'ธนสรรค์', 'topup', 1, 1, 4),
(16, 'TXNT1LI6347BD1E', '150.00', '2025-08-26 02:54:03', 'completed', 'ธนสรรค์', 'buy', 1, 1, 4),
(17, 'TXNT1Y57D4A5643', '142.00', '2025-09-01 22:42:49', 'completed', 'ธนสรรค์', 'topup', 1, 1, 4);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `name`, `phone`, `email`, `role`, `created_at`) VALUES
(1, 'pluem', '$2y$10$HFv1tW/N6CGgwlzYx0i51urZr/3V5zB3S5PytO/Y2Tz4eH.YIZjKq', 'ผู้ดูแลระบบ', NULL, 'pluem@example.com', 'admin', '2025-08-22 12:47:14'),
(2, 'demo', '$2y$10$0Jk0eN6Z2Tn3T4yq7r7cOe3e6t9M3u4Xo0b6mM0s3x5Q3m8oQnE3W', 'ผู้ใช้ตัวอย่าง', '0812345678', 'demo@example.com', 'user', '2025-08-22 12:47:14'),
(3, 'obtai_7ma', '$2y$10$sfvoaosj4glcde7LPDNKnO3YUVHIdJ0dcnYWWh32YEjPiirbBBVka', 'post', '0789245614', 'user_1755866991_1241@nano.com', 'user', '2025-08-22 12:49:51'),
(4, 'มีสา', '$2y$10$TTPGqTBV/rVMTjMlvYVD9.Dkm0F4tkTct6CC0ItVis20oEAUmKdm.', 'ธนสรรค์', '0813524711', 'user_1756025930_3135@nano.com', 'user', '2025-08-24 08:58:50');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `nfc_cards`
--
ALTER TABLE `nfc_cards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_card_uid` (`card_uid`),
  ADD UNIQUE KEY `uniq_user_one_card` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_oi_txn_id` (`transaction_id`),
  ADD KEY `idx_oi_product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_transaction_id` (`transaction_id`),
  ADD KEY `idx_txn_user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_username` (`username`),
  ADD UNIQUE KEY `uniq_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `nfc_cards`
--
ALTER TABLE `nfc_cards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `nfc_cards`
--
ALTER TABLE `nfc_cards`
  ADD CONSTRAINT `fk_nfc_cards_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`transaction_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_txn_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
