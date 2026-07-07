-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 05, 2026 at 10:15 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `utem_accommodation`
--

-- --------------------------------------------------------

--
-- Table structure for table `booking`
--

CREATE TABLE `booking` (
  `Booking_ID` int(100) NOT NULL,
  `Booking_Date` date NOT NULL,
  `DropOff_Date` date NOT NULL,
  `Pickup_Date` date NOT NULL,
  `Booking_Status` char(100) NOT NULL,
  `Rejection_Reason` text DEFAULT NULL,
  `Rejection_Photo` varchar(255) DEFAULT NULL,
  `Booking_Priority` char(1) NOT NULL,
  `Staff_ID` int(100) NOT NULL,
  `Student_ID` varchar(10) NOT NULL,
  `Dropoff_Photo` varchar(255) DEFAULT NULL,
  `Dropoff_Status` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking`
--

INSERT INTO `booking` (`Booking_ID`, `Booking_Date`, `DropOff_Date`, `Pickup_Date`, `Booking_Status`, `Rejection_Reason`, `Rejection_Photo`, `Booking_Priority`, `Staff_ID`, `Student_ID`, `Dropoff_Photo`, `Dropoff_Status`) VALUES
(156, '2026-05-10', '2026-05-16', '2026-06-16', 'To be dropped off', NULL, NULL, '1', 1, 'S001', NULL, NULL),
(157, '2026-06-01', '2026-06-05', '2026-07-05', 'Stored', NULL, NULL, '2', 2, 'S002', NULL, NULL),
(158, '2026-06-25', '2026-05-01', '2026-08-01', 'Confirmed', NULL, NULL, 'N', 1, 'S001', NULL, NULL),
(159, '2026-06-25', '2026-01-25', '2026-01-26', 'Cancelled_Unpaid', NULL, NULL, 'N', 1, 'S001', NULL, NULL),
(160, '2026-06-25', '2026-01-25', '2026-01-26', 'Cancelled_Unpaid', NULL, NULL, 'N', 1, 'S001', NULL, NULL),
(161, '2026-06-26', '2026-06-26', '2026-06-27', 'Cancelled_Unpaid', NULL, NULL, 'Y', 1, 'S001', NULL, NULL),
(162, '2026-06-26', '2026-06-26', '2026-06-27', 'Cancelled_Unpaid', NULL, NULL, 'Y', 1, 'S001', NULL, NULL),
(163, '2026-07-01', '2026-07-01', '2026-07-02', 'Cancelled_Unpaid', NULL, NULL, 'N', 1, 'd032410358', NULL, NULL),
(164, '2026-07-02', '2026-07-02', '2026-07-03', 'Approved', NULL, NULL, 'Y', 1, 'd032410024', NULL, NULL),
(165, '2026-07-02', '2026-07-02', '2026-07-03', 'Rejected', 'saje je', 'uploads/rejections/reject_165_1782961339.jpeg', 'N', 1, 'd112410057', NULL, NULL),
(166, '2026-07-02', '2026-07-02', '2026-07-03', 'Cancelled_Unpaid', NULL, NULL, 'N', 1, 'd112410057', NULL, NULL),
(167, '2026-07-02', '2026-07-02', '2026-07-03', 'Approved', NULL, NULL, 'N', 1, 'd032410359', NULL, NULL),
(168, '2026-07-02', '2026-07-02', '2026-07-03', 'Cancelled_Unpaid', NULL, NULL, 'N', 1, 'd032410360', NULL, NULL),
(172, '2026-07-05', '2026-07-05', '2026-07-16', 'Cancelled_Unpaid', NULL, NULL, 'N', 1, 'd032410024', NULL, NULL),
(174, '2026-07-05', '2026-07-05', '2026-07-06', 'Rejected', 'tabole', 'uploads/rejection/reject_174_1783272194.jpeg', 'N', 1, 'd112410058', NULL, NULL),
(175, '2026-07-06', '2026-07-06', '2026-07-07', 'Pending', NULL, NULL, 'N', 1, 'd112410057', NULL, NULL),
(177, '2026-07-06', '2026-07-06', '2026-07-07', 'Approved', NULL, NULL, 'N', 1, 'd112410058', NULL, NULL),
(178, '2026-07-06', '2026-07-06', '2026-07-07', 'Approved', NULL, NULL, 'N', 1, 'd032410024', 'uploads/dropoff/dropoff_178_1783282091.jpeg', 'Confirmed');

-- --------------------------------------------------------

--
-- Table structure for table `booking_window`
--

CREATE TABLE `booking_window` (
  `window_id` int(11) NOT NULL,
  `label` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_window`
--

INSERT INTO `booking_window` (`window_id`, `label`, `start_date`, `end_date`, `created_at`) VALUES
(1, 'hari raya', '2026-07-02', '2026-07-31', '2026-07-02 09:38:54');

-- --------------------------------------------------------

--
-- Table structure for table `item`
--

CREATE TABLE `item` (
  `Item_ID` int(100) NOT NULL,
  `Item_Name` varchar(100) NOT NULL,
  `Item_Category` char(100) NOT NULL,
  `Item_Size` char(1) NOT NULL,
  `Quantity` int(3) NOT NULL,
  `Price` decimal(10,2) NOT NULL,
  `Space_ID` int(100) NOT NULL,
  `Booking_ID` int(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `item`
--

INSERT INTO `item` (`Item_ID`, `Item_Name`, `Item_Category`, `Item_Size`, `Quantity`, `Price`, `Space_ID`, `Booking_ID`) VALUES
(1, 'Bag small', 'Bag', 'S', 1, 0.50, 1, 156),
(2, 'Bucket/pail', 'Container', 'M', 1, 0.50, 1, 156),
(3, 'Fan', 'Electrical', 'M', 1, 0.50, 1, 156),
(4, 'Fan', 'Furniture', 'L', 1, 2.00, 2, 157),
(5, 'Bucket/Pail', 'Storage', 'M', 150, 0.50, 6, 158),
(6, 'Other', 'Storage', 'M', 1, 0.50, 4, 159),
(7, 'Bucket/Pail', 'Storage', 'M', 1, 0.50, 3, 160),
(8, 'Bucket/Pail', 'Storage', 'M', 1, 0.50, 6, 161),
(9, 'Bucket/Pail', 'Storage', 'M', 1, 0.50, 6, 162),
(10, 'Big Bag', 'Storage', 'L', 1, 0.50, 7, 163),
(11, 'Bucket/Pail', 'Storage', 'M', 3, 0.50, 7, 164),
(12, 'Bucket/Pail', 'Storage', 'M', 3, 0.50, 7, 165),
(13, 'Bucket/Pail', 'Storage', 'M', 3, 0.50, 7, 166),
(14, 'Bucket/Pail', 'Storage', 'M', 3, 0.50, 7, 167),
(15, 'Bucket/Pail', 'Storage', 'M', 3, 0.50, 7, 168),
(20, 'Big Bag', 'Storage', 'L', 3, 0.50, 7, 172),
(22, 'Large Luggage', 'Storage', 'L', 3, 0.50, 7, 174),
(23, 'Big Box', 'Storage', 'L', 1, 5.00, 7, 175),
(24, 'Bucket/Pail', 'Storage', 'M', 2, 3.00, 7, 175),
(26, 'Bucket/Pail', 'Storage', 'M', 3, 3.00, 7, 177),
(27, 'Bucket/Pail', 'Storage', 'M', 3, 3.00, 7, 178);

-- --------------------------------------------------------

--
-- Table structure for table `payment`
--

CREATE TABLE `payment` (
  `Payment_ID` int(100) NOT NULL,
  `Payment_Method` varchar(100) NOT NULL,
  `Payment_Status` char(1) NOT NULL,
  `Payment_Date` date NOT NULL,
  `Amount` decimal(10,2) NOT NULL,
  `Booking_ID` int(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment`
--

INSERT INTO `payment` (`Payment_ID`, `Payment_Method`, `Payment_Status`, `Payment_Date`, `Amount`, `Booking_ID`) VALUES
(1, 'Online Banking', 'Y', '2026-05-10', 1.50, 156),
(2, 'Cash', 'Y', '2026-06-01', 2.00, 157),
(3, 'Credit/Debit Card', 'Y', '2026-06-01', 2.00, 157),
(4, 'Online', 'N', '2026-06-25', 75.00, 158),
(5, 'Online', 'N', '2026-06-25', 0.50, 159),
(6, 'Online', 'N', '2026-06-25', 0.50, 160),
(7, 'Online Banking', 'P', '2026-06-26', 13.00, 161),
(8, 'Online Banking', 'P', '2026-06-26', 13.00, 162),
(9, 'Online', 'N', '2026-07-01', 7.00, 163),
(10, 'Online Banking', 'Y', '2026-07-02', 19.00, 164),
(11, 'Online', 'N', '2026-07-02', 9.00, 165),
(12, 'Online', 'N', '2026-07-02', 9.00, 166),
(13, 'Online', 'N', '2026-07-02', 1.50, 166),
(14, 'Online', 'N', '2026-07-02', 0.50, 159),
(15, 'Online Banking', 'Y', '2026-07-02', 9.00, 167),
(16, 'Online Banking', 'Y', '2026-07-02', 9.00, 167),
(17, 'Online', 'N', '2026-07-02', 9.00, 168),
(21, 'Online', 'N', '2026-07-05', 21.00, 172),
(23, 'Online', 'N', '2026-07-05', 30.00, 174),
(24, 'Online', 'N', '2026-07-06', 11.00, 175),
(26, 'Online Banking', 'Y', '2026-07-05', 9.00, 177),
(27, 'Online Banking', 'Y', '2026-07-05', 9.00, 177),
(28, 'Online Banking', 'Y', '2026-07-05', 9.00, 178),
(29, 'Online Banking', 'Y', '2026-07-05', 9.00, 178);

-- --------------------------------------------------------

--
-- Table structure for table `residential_college`
--

CREATE TABLE `residential_college` (
  `Residential_ID` int(100) NOT NULL,
  `Residential_Block` varchar(100) NOT NULL,
  `Gender_Type` char(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `residential_college`
--

INSERT INTO `residential_college` (`Residential_ID`, `Residential_Block`, `Gender_Type`) VALUES
(1, 'Satria Lekir', 'F'),
(2, 'Satria Lekiu', 'F'),
(3, 'Satria Kasturi', 'M'),
(4, 'Satria Jebat', 'M'),
(5, 'Satria Tuah', 'M'),
(6, 'Lestari A', 'M'),
(7, 'Lestari B', 'F');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `Staff_ID` int(100) NOT NULL,
  `Staff_Name` varchar(100) NOT NULL,
  `Staff_Mail` varchar(100) NOT NULL,
  `Staff_PhoneNo` int(15) NOT NULL,
  `Position` int(100) NOT NULL,
  `Staff_Password` int(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`Staff_ID`, `Staff_Name`, `Staff_Mail`, `Staff_PhoneNo`, `Position`, `Staff_Password`) VALUES
(1, 'Ahmad Razak', 'ahmad@utem.edu.my', 123456789, 1, 123456),
(2, 'Siti Khadijah', 'siti@utem.edu.my', 123456780, 2, 654321);

-- --------------------------------------------------------

--
-- Table structure for table `storespace`
--

CREATE TABLE `storespace` (
  `Space_ID` int(100) NOT NULL,
  `Residential_ID` int(100) NOT NULL,
  `Size` int(100) NOT NULL,
  `Status` char(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `storespace`
--

INSERT INTO `storespace` (`Space_ID`, `Residential_ID`, `Size`, `Status`) VALUES
(1, 1, 50, 'Y'),
(2, 2, 30, 'Y'),
(3, 3, 41, 'Y'),
(4, 4, 61, 'Y'),
(5, 5, 45, 'Y'),
(6, 6, 55, 'Y'),
(7, 7, 20, 'Y');

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `Student_ID` varchar(10) NOT NULL,
  `Student_Name` varchar(100) NOT NULL,
  `Student_Mail` varchar(30) NOT NULL,
  `Student_Password` varchar(100) NOT NULL,
  `Student_PhoneNo` char(15) NOT NULL,
  `Gender` char(1) NOT NULL,
  `Residential_ID` int(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student`
--

INSERT INTO `student` (`Student_ID`, `Student_Name`, `Student_Mail`, `Student_Password`, `Student_PhoneNo`, `Gender`, `Residential_ID`) VALUES
('d032410004', 'nani', 'd032410004@student.utem.edu.my', 'giraffe123', '01154251043', 'F', 7),
('d032410024', 'biela', 'D032410024@student.utem.edu.my', 'biela123', '0183647589', 'F', 7),
('d032410358', 'salwa suhaimi', 'd032410358@student.utem.edu.my', 'babuji', '0199101564', 'F', 7),
('d032410359', 'orang', 'D032410359@student.utem.edu.my', 'salwa123', '0199101565', 'F', 7),
('d032410360', 'babuntat', 'd032410360@student.utem.edu.my', 'babuntat', '0199101566', 'F', 7),
('d112410057', 'nurin', 'd112410057@student.utem.edu.my', 'nurin123', 'd112410057@stud', 'F', 7),
('d112410058', 'suhaida', 'd112410058@student.utem.edu.my', 'angah123', '0139221564', 'F', 7),
('d112410147', 'jasmine', 'D112410147@student.utem.edu.my', 'una123', '0198253756', 'F', 7),
('S001', 'Faris Baharuddin', 'd032410009@student.utem.edu.my', 'password123', '0198765432', 'M', 4),
('S002', 'Hazim Syahmi Zaid', 'd032410283@student.utem.edu.my', 'password456', '0123456789', 'M', 4);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `booking`
--
ALTER TABLE `booking`
  ADD PRIMARY KEY (`Booking_ID`),
  ADD KEY `FK_Booking_Staff` (`Staff_ID`),
  ADD KEY `FK_Booking_Student` (`Student_ID`);

--
-- Indexes for table `booking_window`
--
ALTER TABLE `booking_window`
  ADD PRIMARY KEY (`window_id`);

--
-- Indexes for table `item`
--
ALTER TABLE `item`
  ADD PRIMARY KEY (`Item_ID`),
  ADD KEY `FK_Item_Booking` (`Booking_ID`),
  ADD KEY `FK_Item_StoreSpace` (`Space_ID`);

--
-- Indexes for table `payment`
--
ALTER TABLE `payment`
  ADD PRIMARY KEY (`Payment_ID`),
  ADD KEY `FK_Payment_Booking` (`Booking_ID`);

--
-- Indexes for table `residential_college`
--
ALTER TABLE `residential_college`
  ADD PRIMARY KEY (`Residential_ID`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`Staff_ID`);

--
-- Indexes for table `storespace`
--
ALTER TABLE `storespace`
  ADD PRIMARY KEY (`Space_ID`),
  ADD KEY `FK_Storespace_ResidentialCollege` (`Residential_ID`);

--
-- Indexes for table `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`Student_ID`),
  ADD KEY `FK_Student_ResidentialCollege` (`Residential_ID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `booking`
--
ALTER TABLE `booking`
  MODIFY `Booking_ID` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=179;

--
-- AUTO_INCREMENT for table `booking_window`
--
ALTER TABLE `booking_window`
  MODIFY `window_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `item`
--
ALTER TABLE `item`
  MODIFY `Item_ID` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `payment`
--
ALTER TABLE `payment`
  MODIFY `Payment_ID` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `residential_college`
--
ALTER TABLE `residential_college`
  MODIFY `Residential_ID` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `Staff_ID` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `storespace`
--
ALTER TABLE `storespace`
  MODIFY `Space_ID` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `booking`
--
ALTER TABLE `booking`
  ADD CONSTRAINT `FK_Booking_Staff` FOREIGN KEY (`Staff_ID`) REFERENCES `staff` (`Staff_ID`),
  ADD CONSTRAINT `FK_Booking_Student` FOREIGN KEY (`Student_ID`) REFERENCES `student` (`Student_ID`);

--
-- Constraints for table `item`
--
ALTER TABLE `item`
  ADD CONSTRAINT `FK_Item_Booking` FOREIGN KEY (`Booking_ID`) REFERENCES `booking` (`Booking_ID`),
  ADD CONSTRAINT `FK_Item_StoreSpace` FOREIGN KEY (`Space_ID`) REFERENCES `storespace` (`Space_ID`);

--
-- Constraints for table `payment`
--
ALTER TABLE `payment`
  ADD CONSTRAINT `FK_Payment_Booking` FOREIGN KEY (`Booking_ID`) REFERENCES `booking` (`Booking_ID`);

--
-- Constraints for table `storespace`
--
ALTER TABLE `storespace`
  ADD CONSTRAINT `FK_Storespace_ResidentialCollege` FOREIGN KEY (`Residential_ID`) REFERENCES `residential_college` (`Residential_ID`);

--
-- Constraints for table `student`
--
ALTER TABLE `student`
  ADD CONSTRAINT `FK_Student_ResidentialCollege` FOREIGN KEY (`Residential_ID`) REFERENCES `residential_college` (`Residential_ID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
