Đây là dự án quản lý thiết bị CMMS được xây dựng trên nền tảng PHP kết hợp với javascritp dùng mariaDB làm cơ sở dự liệu
cấu trúc của cơ sở dự liệu
-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: db:3306
-- Generation Time: Sep 07, 2025 at 09:52 AM
-- Server version: 10.11.11-MariaDB-ubu2204
-- PHP Version: 8.2.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cmms`
--

-- --------------------------------------------------------

--
-- Table structure for table `areas`
--

CREATE TABLE `areas` (
  `id` int(11) NOT NULL,
  `industry_id` int(11) NOT NULL,
  `workshop_id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bom_history`
--

CREATE TABLE `bom_history` (
  `id` int(11) NOT NULL,
  `bom_id` int(11) NOT NULL,
  `action` enum('created','updated','item_added','item_removed','item_updated') NOT NULL,
  `changes` longtext DEFAULT NULL COMMENT 'Chi tiết thay đổi (JSON format)',
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bom_items`
--

CREATE TABLE `bom_items` (
  `id` int(11) NOT NULL,
  `bom_id` int(11) NOT NULL COMMENT 'ID BOM',
  `part_id` int(11) NOT NULL COMMENT 'ID linh kiện',
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00 COMMENT 'Số lượng cần thiết',
  `unit` varchar(20) NOT NULL COMMENT 'Đơn vị tính',
  `position` varchar(100) DEFAULT NULL COMMENT 'Vị trí lắp đặt',
  `priority` enum('Low','Medium','High','Critical') DEFAULT 'Medium' COMMENT 'Độ ưu tiên',
  `maintenance_interval` int(11) DEFAULT NULL COMMENT 'Chu kỳ thay thế (giờ)',
  `notes` text DEFAULT NULL COMMENT 'Ghi chú riêng cho item này',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

CREATE TABLE `equipment` (
  `id` int(11) NOT NULL,
  `code` varchar(30) NOT NULL,
  `name` varchar(200) NOT NULL,
  `industry_id` int(11) NOT NULL,
  `workshop_id` int(11) NOT NULL,
  `line_id` int(11) DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `machine_type_id` int(11) NOT NULL,
  `equipment_group_id` int(11) DEFAULT NULL,
  `owner_user_id` int(11) DEFAULT NULL COMMENT 'Người chủ quản thiết bị',
  `backup_owner_user_id` int(11) DEFAULT NULL COMMENT 'Người chủ quản phụ/dự phòng',
  `manufacturer` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `manufacture_year` year(4) DEFAULT NULL,
  `installation_date` date DEFAULT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `maintenance_frequency_days` int(11) DEFAULT NULL COMMENT 'Tần suất bảo trì định kỳ (số ngày)',
  `maintenance_frequency_type` enum('daily','weekly','monthly','quarterly','yearly','custom') DEFAULT 'monthly' COMMENT 'Loại tần suất bảo trì',
  `specifications` text DEFAULT NULL,
  `technical_specs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`technical_specs`)),
  `location_details` varchar(200) DEFAULT NULL,
  `criticality` enum('Low','Medium','High','Critical') DEFAULT 'Medium',
  `status` enum('active','inactive','maintenance','broken') NOT NULL DEFAULT 'active',
  `image_path` varchar(255) DEFAULT NULL,
  `manual_path` varchar(255) DEFAULT NULL,
  `settings_images` text DEFAULT NULL COMMENT 'JSON array chứa đường dẫn ảnh thông số cài đặt máy',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng quản lý thiết bị với thông tin đầy đủ về vị trí, chủ quản, và bảo trì';

-- --------------------------------------------------------

--
-- Table structure for table `equipment_groups`
--

CREATE TABLE `equipment_groups` (
  `id` int(11) NOT NULL,
  `machine_type_id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `equipment_move_history`
--

CREATE TABLE `equipment_move_history` (
  `id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `old_industry_id` int(11) DEFAULT NULL,
  `old_workshop_id` int(11) DEFAULT NULL,
  `old_line_id` int(11) DEFAULT NULL,
  `old_area_id` int(11) DEFAULT NULL,
  `old_machine_type_id` int(11) DEFAULT NULL,
  `old_equipment_group_id` int(11) DEFAULT NULL,
  `new_industry_id` int(11) DEFAULT NULL,
  `new_workshop_id` int(11) DEFAULT NULL,
  `new_line_id` int(11) DEFAULT NULL,
  `new_area_id` int(11) DEFAULT NULL,
  `new_machine_type_id` int(11) DEFAULT NULL,
  `new_equipment_group_id` int(11) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `moved_by` int(11) DEFAULT NULL,
  `moved_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `industries`
--

CREATE TABLE `industries` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `machine_bom`
--

CREATE TABLE `machine_bom` (
  `id` int(11) NOT NULL,
  `machine_type_id` int(11) NOT NULL COMMENT 'ID dòng máy',
  `bom_name` varchar(255) NOT NULL COMMENT 'Tên BOM',
  `bom_code` varchar(50) DEFAULT NULL COMMENT 'Mã BOM (tự sinh hoặc tự đặt)',
  `version` varchar(20) DEFAULT '1.0' COMMENT 'Phiên bản BOM',
  `description` text DEFAULT NULL COMMENT 'Mô tả BOM',
  `effective_date` date DEFAULT NULL COMMENT 'Ngày hiệu lực',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `machine_types`
--

CREATE TABLE `machine_types` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `specifications` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `module_permissions`
--

CREATE TABLE `module_permissions` (
  `id` int(11) NOT NULL,
  `role` enum('Admin','Supervisor','Production Manager','User') NOT NULL,
  `module_name` varchar(50) NOT NULL,
  `can_view` tinyint(1) DEFAULT 1,
  `can_create` tinyint(1) DEFAULT 0,
  `can_edit` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0,
  `can_export` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `onhand`
--

CREATE TABLE `onhand` (
  `ID` int(11) NOT NULL,
  `ItemCode` varchar(255) DEFAULT NULL,
  `Itemname` varchar(255) DEFAULT NULL,
  `Locator` varchar(255) DEFAULT NULL,
  `Lotnumber` varchar(255) DEFAULT NULL,
  `Onhand` decimal(10,2) DEFAULT NULL,
  `UOM` varchar(255) DEFAULT NULL,
  `Price` decimal(10,2) DEFAULT NULL,
  `OH_Value` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parts`
--

CREATE TABLE `parts` (
  `id` int(11) NOT NULL,
  `part_code` varchar(50) NOT NULL COMMENT 'Mã linh kiện (VKH0074, VHC0301...)',
  `part_name` varchar(255) NOT NULL COMMENT 'Tên linh kiện',
  `description` text DEFAULT NULL COMMENT 'Mô tả chi tiết',
  `unit` varchar(20) NOT NULL DEFAULT 'Cái' COMMENT 'Đơn vị tính (Cái, Bộ, Kg, m...)',
  `category` varchar(100) DEFAULT NULL COMMENT 'Phân loại linh kiện (Điện, Cơ khí, Hóa chất...)',
  `specifications` text DEFAULT NULL COMMENT 'Thông số kỹ thuật',
  `manufacturer` varchar(255) DEFAULT NULL COMMENT 'Nhà sản xuất',
  `supplier_code` varchar(100) DEFAULT NULL COMMENT 'Mã nhà cung cấp chính',
  `supplier_name` varchar(255) DEFAULT NULL COMMENT 'Tên nhà cung cấp chính',
  `unit_price` decimal(15,2) DEFAULT 0.00 COMMENT 'Giá đơn vị',
  `min_stock` decimal(10,2) DEFAULT 0.00 COMMENT 'Mức tồn kho tối thiểu',
  `max_stock` decimal(10,2) DEFAULT 0.00 COMMENT 'Mức tồn kho tối đa',
  `lead_time` int(11) DEFAULT 0 COMMENT 'Thời gian giao hàng (ngày)',
  `notes` text DEFAULT NULL COMMENT 'Ghi chú',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parts_history`
--

CREATE TABLE `parts_history` (
  `id` int(11) NOT NULL,
  `part_id` int(11) DEFAULT NULL,
  `action` varchar(50) DEFAULT NULL,
  `changes` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `part_suppliers`
--

CREATE TABLE `part_suppliers` (
  `id` int(11) NOT NULL,
  `part_id` int(11) NOT NULL,
  `supplier_code` varchar(100) NOT NULL COMMENT 'Mã nhà cung cấp',
  `supplier_name` varchar(255) NOT NULL COMMENT 'Tên nhà cung cấp',
  `part_number` varchar(100) DEFAULT NULL COMMENT 'Mã linh kiện của nhà cung cấp',
  `unit_price` decimal(15,2) DEFAULT 0.00 COMMENT 'Giá từ nhà cung cấp này',
  `min_order_qty` decimal(10,2) DEFAULT 1.00 COMMENT 'Số lượng đặt hàng tối thiểu',
  `lead_time` int(11) DEFAULT 0 COMMENT 'Thời gian giao hàng (ngày)',
  `is_preferred` tinyint(1) DEFAULT 0 COMMENT 'Nhà cung cấp ưu tiên',
  `contact_info` text DEFAULT NULL COMMENT 'Thông tin liên hệ',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `production_lines`
--

CREATE TABLE `production_lines` (
  `id` int(11) NOT NULL,
  `workshop_id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_requests`
--

CREATE TABLE `purchase_requests` (
  `id` int(11) NOT NULL,
  `part_id` int(11) NOT NULL,
  `part_code` varchar(50) NOT NULL,
  `part_name` varchar(255) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transaction`
--

CREATE TABLE `transaction` (
  `ID` int(11) NOT NULL,
  `Number` varchar(255) DEFAULT NULL,
  `Status` varchar(255) DEFAULT NULL,
  `Comment` varchar(255) DEFAULT NULL,
  `Requester` varchar(255) DEFAULT NULL,
  `TransactionDate` datetime DEFAULT NULL,
  `TransactionType` varchar(255) DEFAULT NULL,
  `ItemCode` varchar(255) DEFAULT NULL,
  `ItemDesc` varchar(255) DEFAULT NULL,
  `Locator` varchar(255) DEFAULT NULL,
  `Department` varchar(255) DEFAULT NULL,
  `Brandy` varchar(255) DEFAULT NULL,
  `Reason` varchar(255) DEFAULT NULL,
  `Lotnumber` varchar(255) DEFAULT NULL,
  `UOM` varchar(255) DEFAULT NULL,
  `TransactedQty` decimal(10,2) DEFAULT NULL,
  `Price` decimal(10,2) DEFAULT NULL,
  `TotalAmount` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('Admin','Supervisor','Production Manager','User') NOT NULL DEFAULT 'User',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_bom_details`
-- (See below for the actual view)
--
CREATE TABLE `v_bom_details` (
);

-- --------------------------------------------------------

--
-- Table structure for table `workshops`
--

CREATE TABLE `workshops` (
  `id` int(11) NOT NULL,
  `industry_id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `areas`
--
ALTER TABLE `areas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_area_code_workshop` (`workshop_id`,`code`),
  ADD KEY `idx_areas_industry` (`industry_id`),
  ADD KEY `idx_areas_workshop` (`workshop_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `bom_history`
--
ALTER TABLE `bom_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bom_id` (`bom_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`);

--
-- Indexes for table `bom_items`
--
ALTER TABLE `bom_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_bom_part` (`bom_id`,`part_id`),
  ADD KEY `idx_bom_id` (`bom_id`),
  ADD KEY `idx_part_id` (`part_id`),
  ADD KEY `idx_priority` (`priority`);

--
-- Indexes for table `equipment`
--
ALTER TABLE `equipment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `workshop_id` (`workshop_id`),
  ADD KEY `line_id` (`line_id`),
  ADD KEY `area_id` (`area_id`),
  ADD KEY `equipment_group_id` (`equipment_group_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_equipment_location` (`industry_id`,`workshop_id`,`line_id`,`area_id`),
  ADD KEY `idx_equipment_type` (`machine_type_id`,`equipment_group_id`),
  ADD KEY `idx_equipment_status` (`status`),
  ADD KEY `idx_equipment_criticality` (`criticality`),
  ADD KEY `idx_equipment_owner` (`owner_user_id`),
  ADD KEY `idx_equipment_backup_owner` (`backup_owner_user_id`),
  ADD KEY `idx_equipment_maintenance_freq` (`maintenance_frequency_days`,`maintenance_frequency_type`);

--
-- Indexes for table `equipment_groups`
--
ALTER TABLE `equipment_groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_group_code` (`machine_type_id`,`code`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `equipment_move_history`
--
ALTER TABLE `equipment_move_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `equipment_id` (`equipment_id`),
  ADD KEY `moved_by` (`moved_by`);

--
-- Indexes for table `industries`
--
ALTER TABLE `industries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `machine_bom`
--
ALTER TABLE `machine_bom`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_machine_bom` (`machine_type_id`,`bom_code`),
  ADD KEY `idx_machine_type` (`machine_type_id`),
  ADD KEY `idx_bom_code` (`bom_code`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `machine_types`
--
ALTER TABLE `machine_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_code` (`code`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `module_permissions`
--
ALTER TABLE `module_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_role_module` (`role`,`module_name`);

--
-- Indexes for table `onhand`
--
ALTER TABLE `onhand`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `idx_onhand_itemcode` (`ItemCode`);

--
-- Indexes for table `parts`
--
ALTER TABLE `parts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_part_code` (`part_code`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_supplier` (`supplier_code`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `parts_history`
--
ALTER TABLE `parts_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `part_suppliers`
--
ALTER TABLE `part_suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_part_supplier` (`part_id`,`supplier_code`),
  ADD KEY `idx_part_id` (`part_id`),
  ADD KEY `idx_supplier` (`supplier_code`),
  ADD KEY `idx_preferred` (`is_preferred`);

--
-- Indexes for table `production_lines`
--
ALTER TABLE `production_lines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_line_code` (`workshop_id`,`code`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `part_id` (`part_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `transaction`
--
ALTER TABLE `transaction`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `idx_transaction_itemcode` (`ItemCode`),
  ADD KEY `idx_transaction_date` (`TransactionDate`),
  ADD KEY `idx_transaction_type` (`TransactionType`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `workshops`
--
ALTER TABLE `workshops`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_workshop_code` (`industry_id`,`code`),
  ADD KEY `created_by` (`created_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `areas`
--
ALTER TABLE `areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bom_history`
--
ALTER TABLE `bom_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bom_items`
--
ALTER TABLE `bom_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `equipment_groups`
--
ALTER TABLE `equipment_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `equipment_move_history`
--
ALTER TABLE `equipment_move_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `industries`
--
ALTER TABLE `industries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `machine_bom`
--
ALTER TABLE `machine_bom`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `machine_types`
--
ALTER TABLE `machine_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `module_permissions`
--
ALTER TABLE `module_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `onhand`
--
ALTER TABLE `onhand`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parts`
--
ALTER TABLE `parts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parts_history`
--
ALTER TABLE `parts_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `part_suppliers`
--
ALTER TABLE `part_suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `production_lines`
--
ALTER TABLE `production_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transaction`
--
ALTER TABLE `transaction`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `workshops`
--
ALTER TABLE `workshops`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Structure for view `v_bom_details`
--
DROP TABLE IF EXISTS `v_bom_details`;

CREATE ALGORITHM=UNDEFINED DEFINER=`cf`@`%` SQL SECURITY DEFINER VIEW `v_bom_details`  AS SELECT `mb`.`id` AS `bom_id`, `mb`.`bom_name` AS `bom_name`, `mb`.`bom_code` AS `bom_code`, `mb`.`version` AS `version`, `mt`.`name` AS `machine_type_name`, `mt`.`code` AS `machine_type_code`, `bi`.`id` AS `item_id`, `p`.`part_code` AS `part_code`, `p`.`part_name` AS `part_name`, `bi`.`quantity` AS `quantity`, `bi`.`unit` AS `unit`, `bi`.`position` AS `position`, `bi`.`priority` AS `priority`, `bi`.`maintenance_interval` AS `maintenance_interval`, `p`.`unit_price` AS `unit_price`, `bi`.`quantity`* `p`.`unit_price` AS `total_cost`, `p`.`supplier_name` AS `supplier_name`, `bi`.`notes` AS `item_notes`, coalesce(`oh`.`Onhand`,0) AS `stock_quantity`, coalesce(`oh`.`UOM`,`p`.`unit`) AS `stock_unit`, CASE WHEN coalesce(`oh`.`Onhand`,0) < `p`.`min_stock` THEN 'Low' WHEN coalesce(`oh`.`Onhand`,0) = 0 THEN 'Out of Stock' ELSE 'OK' END AS `stock_status` FROM (((((`machine_bom` `mb` join `machine_types` `mt` on(`mb`.`machine_type_id` = `mt`.`id`)) join `bom_items` `bi` on(`mb`.`id` = `bi`.`bom_id`)) join `parts` `p` on(`bi`.`part_id` = `p`.`id`)) left join `part_inventory_mapping` `pim` on(`p`.`id` = `pim`.`part_id`)) left join `onhand` `oh` on(`pim`.`item_code` = `oh`.`ItemCode`)) ORDER BY `mb`.`id` ASC, `bi`.`id` ASC ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `areas`
--
ALTER TABLE `areas`
  ADD CONSTRAINT `areas_ibfk_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `bom_history`
--
ALTER TABLE `bom_history`
  ADD CONSTRAINT `bom_history_ibfk_1` FOREIGN KEY (`bom_id`) REFERENCES `machine_bom` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bom_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `bom_items`
--
ALTER TABLE `bom_items`
  ADD CONSTRAINT `bom_items_ibfk_1` FOREIGN KEY (`bom_id`) REFERENCES `machine_bom` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bom_items_ibfk_2` FOREIGN KEY (`part_id`) REFERENCES `parts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `equipment`
--
ALTER TABLE `equipment`
  ADD CONSTRAINT `equipment_backup_owner_fk` FOREIGN KEY (`backup_owner_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `equipment_ibfk_1` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`),
  ADD CONSTRAINT `equipment_ibfk_2` FOREIGN KEY (`workshop_id`) REFERENCES `workshops` (`id`),
  ADD CONSTRAINT `equipment_ibfk_3` FOREIGN KEY (`line_id`) REFERENCES `production_lines` (`id`),
  ADD CONSTRAINT `equipment_ibfk_4` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`),
  ADD CONSTRAINT `equipment_ibfk_5` FOREIGN KEY (`machine_type_id`) REFERENCES `machine_types` (`id`),
  ADD CONSTRAINT `equipment_ibfk_6` FOREIGN KEY (`equipment_group_id`) REFERENCES `equipment_groups` (`id`),
  ADD CONSTRAINT `equipment_ibfk_7` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `equipment_owner_fk` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `equipment_groups`
--
ALTER TABLE `equipment_groups`
  ADD CONSTRAINT `equipment_groups_ibfk_1` FOREIGN KEY (`machine_type_id`) REFERENCES `machine_types` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `equipment_groups_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `equipment_move_history`
--
ALTER TABLE `equipment_move_history`
  ADD CONSTRAINT `equipment_move_history_ibfk_1` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `equipment_move_history_ibfk_2` FOREIGN KEY (`moved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `industries`
--
ALTER TABLE `industries`
  ADD CONSTRAINT `industries_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `machine_bom`
--
ALTER TABLE `machine_bom`
  ADD CONSTRAINT `machine_bom_ibfk_1` FOREIGN KEY (`machine_type_id`) REFERENCES `machine_types` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `machine_bom_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `machine_types`
--
ALTER TABLE `machine_types`
  ADD CONSTRAINT `machine_types_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `parts`
--
ALTER TABLE `parts`
  ADD CONSTRAINT `parts_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `part_suppliers`
--
ALTER TABLE `part_suppliers`
  ADD CONSTRAINT `part_suppliers_ibfk_1` FOREIGN KEY (`part_id`) REFERENCES `parts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `production_lines`
--
ALTER TABLE `production_lines`
  ADD CONSTRAINT `production_lines_ibfk_1` FOREIGN KEY (`workshop_id`) REFERENCES `workshops` (`id`),
  ADD CONSTRAINT `production_lines_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  ADD CONSTRAINT `purchase_requests_ibfk_1` FOREIGN KEY (`part_id`) REFERENCES `parts` (`id`),
  ADD CONSTRAINT `purchase_requests_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `workshops`
--
ALTER TABLE `workshops`
  ADD CONSTRAINT `workshops_ibfk_1` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`),
  ADD CONSTRAINT `workshops_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

cấu trúc thư mục của dự án
cmms/
├── assets
│   ├── css
│   │   ├── bom.css
│   │   ├── equipment-view.css
│   │   ├── equipment.css
│   │   ├── structure.css
│   │   └── style.css
│   ├── images
│   │   └── logo.png
│   ├── js
│   │   ├── bom-parts.js
│   │   ├── bom.js
│   │   ├── equipment-add.js
│   │   ├── equipment-edit.js
│   │   ├── equipment-view.js
│   │   ├── equipment.js
│   │   ├── main.js
│   │   └── structure.js
│   └── uploads
│       ├── bom
│       ├── equipment_images
│       ├── equipment_manuals
│       ├── equipment_settings
│       ├── manuals
│       └── temp
├── config
│   ├── auth.php
│   ├── config.php
│   ├── database.php
│   ├── equipment_helpers.php
│   └── functions.php
├── includes
│   ├── footer.php
│   ├── header.php
│   └── sidebar.php
├── index.php
├── login.php
├── logout.php
└── modules
    ├── bom
    │   ├── add.php
    │   ├── api
    │   │   ├── bom.php
    │   │   ├── export.php
    │   │   └── parts.php
    │   ├── config.php
    │   ├── edit.php
    │   ├── imports
    │   │   ├── bom_import.php
    │   │   └── parts_import.php
    │   ├── index.php
    │   ├── parts
    │   │   ├── add.php
    │   │   ├── edit.php
    │   │   ├── index.php
    │   │   └── view.php
    │   ├── reports
    │   │   ├── shortage_report.php
    │   │   └── stock_report.php
    │   └── view.php
    ├── equipment
    │   ├── add.php
    │   ├── api
    │   │   └── equipment.php
    │   ├── edit.php
    │   ├── index.php
    │   ├── uploads
    │   │   └── equipment
    │   │       └── images
    │   └── view.php
    ├── inventory
    │   └── transactions.php
    └── structure
        ├── api
        │   ├── areas.php
        │   ├── equipment_groups.php
        │   ├── industries.php
        │   ├── lines.php
        │   ├── machine_types.php
        │   └── workshops.php
        ├── index.php
        └── views
            ├── areas.php
            ├── equipment_groups.php
            ├── industries.php
            ├── lines.php
            ├── machine_types.php
            └── workshops.php
            
