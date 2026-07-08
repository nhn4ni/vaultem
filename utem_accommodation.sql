-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 08, 2026 at 12:17 PM
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
CREATE DATABASE IF NOT EXISTS `utem_accommodation` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `utem_accommodation`;

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
(1, 'Ahmad Razak', 'ahmad@utem.edu.my', 123456789, 1, 0),
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
(7, 7, 23, 'Y');

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
('d032410334', 'Aleya Syuhada', 'd032410334@student.utem.edu.my', 'Aleya123#', '0194832345', 'F', 7);

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
  MODIFY `Booking_ID` int(100) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `booking_window`
--
ALTER TABLE `booking_window`
  MODIFY `window_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `item`
--
ALTER TABLE `item`
  MODIFY `Item_ID` int(100) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment`
--
ALTER TABLE `payment`
  MODIFY `Payment_ID` int(100) NOT NULL AUTO_INCREMENT;

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
