SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ============================================
-- CREATE AND SELECT DATABASE (Fixes Error #1046)
-- ============================================
CREATE DATABASE IF NOT EXISTS `utem_accommodation`;
USE `utem_accommodation`;

-- ============================================
-- DROP TABLES (in reverse dependency order)
-- ============================================
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `payment`;
DROP TABLE IF EXISTS `item`;
DROP TABLE IF EXISTS `booking`;
DROP TABLE IF EXISTS `storespace`;
DROP TABLE IF EXISTS `student`;
DROP TABLE IF EXISTS `staff`;
DROP TABLE IF EXISTS `residential_college`;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- CREATE TABLES
-- ============================================

CREATE TABLE `booking` (
  `Booking_ID` int(100) NOT NULL,
  `Booking_Date` date NOT NULL,
  `DropOff_Date` date NOT NULL,
  `Pickup_Date` date NOT NULL,
  `Booking_Status` char(100) NOT NULL,
  `Booking_Priority` char(1) NOT NULL,
  `Staff_ID` int(100) NOT NULL,
  `Student_ID` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `payment` (
  `Payment_ID` int(100) NOT NULL,
  `Payment_Method` varchar(100) NOT NULL,
  `Payment_Status` char(1) NOT NULL,
  `Payment_Date` date NOT NULL,
  `Amount` decimal(10,2) NOT NULL,
  `Booking_ID` int(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `residential_college` (
  `Residential_ID` int(100) NOT NULL,
  `Residential_Block` varchar(100) NOT NULL, -- Matched to your text records
  `Gender_Type` char(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `staff` (
  `Staff_ID` int(100) NOT NULL,
  `Staff_Name` varchar(100) NOT NULL,
  `Staff_Mail` varchar(100) NOT NULL,
  `Staff_PhoneNo` int(15) NOT NULL,
  `Position` int(100) NOT NULL,
  `Staff_Password` int(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `storespace` (
  `Space_ID` int(100) NOT NULL,
  `Residential_ID` int(100) NOT NULL,
  `Size` int(100) NOT NULL,
  `Status` char(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `student` (
  `Student_ID` varchar(10) NOT NULL,
  `Student_Name` varchar(100) NOT NULL,
  `Student_Mail` varchar(30) NOT NULL,
  `Student_Password` varchar(100) NOT NULL,
  `Student_PhoneNo` char(15) NOT NULL,
  `Gender` char(1) NOT NULL,
  `Residential_ID` int(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- PRIMARY KEYS & INDEXES
-- ============================================

ALTER TABLE `booking`
  ADD PRIMARY KEY (`Booking_ID`),
  ADD KEY `FK_Booking_Staff` (`Staff_ID`),
  ADD KEY `FK_Booking_Student` (`Student_ID`);

ALTER TABLE `item`
  ADD PRIMARY KEY (`Item_ID`),
  ADD KEY `FK_Item_Booking` (`Booking_ID`),
  ADD KEY `FK_Item_StoreSpace` (`Space_ID`);

ALTER TABLE `payment`
  ADD PRIMARY KEY (`Payment_ID`), -- Added PK for consistency
  ADD KEY `FK_Payment_Booking` (`Booking_ID`);

ALTER TABLE `residential_college`
  ADD PRIMARY KEY (`Residential_ID`);

ALTER TABLE `staff`
  ADD PRIMARY KEY (`Staff_ID`);

ALTER TABLE `storespace`
  ADD PRIMARY KEY (`Space_ID`),
  ADD KEY `FK_Storespace_ResidentialCollege` (`Residential_ID`);

ALTER TABLE `student`
  ADD PRIMARY KEY (`Student_ID`),
  ADD KEY `FK_Student_ResidentialCollege` (`Residential_ID`);

-- ============================================
-- AUTO_INCREMENT Setup
-- ============================================

ALTER TABLE `booking`
  MODIFY `Booking_ID` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=156;

ALTER TABLE `item`
  MODIFY `Item_ID` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `payment`
  MODIFY `Payment_ID` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `residential_college`
  MODIFY `Residential_ID` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8; 


ALTER TABLE `staff`
  MODIFY `Staff_ID` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `storespace`
  MODIFY `Space_ID` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

-- ============================================
-- FOREIGN KEY CONSTRAINTS
-- ============================================

ALTER TABLE `booking`
  ADD CONSTRAINT `FK_Booking_Staff` FOREIGN KEY (`Staff_ID`) REFERENCES `staff` (`Staff_ID`),
  ADD CONSTRAINT `FK_Booking_Student` FOREIGN KEY (`Student_ID`) REFERENCES `student` (`Student_ID`);

ALTER TABLE `item`
  ADD CONSTRAINT `FK_Item_Booking` FOREIGN KEY (`Booking_ID`) REFERENCES `booking` (`Booking_ID`),
  ADD CONSTRAINT `FK_Item_StoreSpace` FOREIGN KEY (`Space_ID`) REFERENCES `storespace` (`Space_ID`);

ALTER TABLE `payment`
  ADD CONSTRAINT `FK_Payment_Booking` FOREIGN KEY (`Booking_ID`) REFERENCES `booking` (`Booking_ID`);

ALTER TABLE `storespace`
  ADD CONSTRAINT `FK_Storespace_ResidentialCollege` FOREIGN KEY (`Residential_ID`) REFERENCES `residential_college` (`Residential_ID`);

ALTER TABLE `student`
  ADD CONSTRAINT `FK_Student_ResidentialCollege` FOREIGN KEY (`Residential_ID`) REFERENCES `residential_college` (`Residential_ID`);

-- ============================================
-- DATA INSERTS
-- ============================================

-- 1. residential_college
INSERT INTO `residential_college` (`Residential_ID`, `Residential_Block`, `Gender_Type`) VALUES
(1, 'Satria Lekir', 'F'),
(2, 'Satria Lekiu', 'F'),
(3, 'Satria Kasturi', 'M'),
(4, 'Satria Jebat', 'M'),
(5, 'Satria Tuah', 'M'),
(6, 'Lestari A', 'M'),
(7, 'Lestari B', 'F');

-- 2. staff
INSERT INTO `staff` (`Staff_ID`, `Staff_Name`, `Staff_Mail`, `Staff_PhoneNo`, `Position`, `Staff_Password`) VALUES
(1, 'Ahmad Razak', 'ahmad@utem.edu.my', 123456789, 1, 123456),
(2, 'Siti Khadijah', 'siti@utem.edu.my', 123456780, 2, 654321);

-- 3. student
INSERT INTO `student` (`Student_ID`, `Student_Name`, `Student_Mail`, `Student_Password`, `Student_PhoneNo`, `Gender`, `Residential_ID`) VALUES
('S001', 'Faris Baharuddin', 'd032410009@student.utem.edu.my', 'password123', '0198765432', 'M', 4),
('S002', 'Hazim Syahmi Zaid', 'd032410283@student.utem.edu.my', 'password456', '0123456789', 'M', 4);

-- 4. storespace
INSERT INTO `storespace` (`Space_ID`, `Residential_ID`, `Size`, `Status`) VALUES
(1, 1, 50, 'Y'),
(2, 2, 30, 'Y');

-- 5. booking
INSERT INTO `booking` (`Booking_ID`, `Booking_Date`, `DropOff_Date`, `Pickup_Date`, `Booking_Status`, `Booking_Priority`, `Staff_ID`, `Student_ID`) VALUES
(156, '2026-05-10', '2026-05-16', '2026-06-16', 'To be dropped off', '1', 1, 'S001'),
(157, '2026-06-01', '2026-06-05', '2026-07-05', 'Stored', '2', 2, 'S002');

-- 6. item
INSERT INTO `item` (`Item_ID`, `Item_Name`, `Item_Category`, `Item_Size`, `Quantity`, `Price`, `Space_ID`, `Booking_ID`) VALUES
(1, 'Bag small', 'Bag', 'S', 1, 0.50, 1, 156),
(2, 'Bucket/pail', 'Container', 'M', 1, 0.50, 1, 156),
(3, 'Fan', 'Electrical', 'M', 1, 0.50, 1, 156),
(4, 'Fan', 'Furniture', 'L', 1, 2.00, 2, 157);

-- 7. payment
INSERT INTO `payment` (`Payment_ID`, `Payment_Method`, `Payment_Status`, `Payment_Date`, `Amount`, `Booking_ID`) VALUES
(1, 'Online Banking', 'Y', '2026-05-10', 1.50, 156),
(2, 'Cash', 'Y', '2026-06-01', 2.00, 157),
(3, 'Credit/Debit Card', 'Y', '2026-06-01', 2.00, 157);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;