-- phpMyAdmin SQL Dump
-- version 4.0.10deb1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Aug 17, 2015 at 02:09 PM
-- Server version: 5.5.44-0ubuntu0.14.04.1
-- PHP Version: 5.5.9-1ubuntu4.11

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `pid2`
--

-- --------------------------------------------------------

--
-- Table structure for table `crowd_mapping`
--

CREATE TABLE IF NOT EXISTS `crowd_mapping` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dataset_id` int(11) DEFAULT NULL,
  `original_name` varchar(255) NOT NULL DEFAULT '' COMMENT 'Name as supplied in csv',
  `hg_id` varchar(255) DEFAULT NULL,
  `hg_uri` varchar(255) DEFAULT NULL,
  `hg_name` varchar(255) DEFAULT NULL COMMENT 'Name according to APi source',
  `hg_geometry` text,
  `hg_type` varchar(255) DEFAULT NULL COMMENT 'Name according to APi source',
  `created_on` datetime NOT NULL,
  `updated_on` datetime DEFAULT NULL,
  `status` int(11) DEFAULT NULL COMMENT 'The type of match',
  `hg_dataset` varchar(255) DEFAULT NULL COMMENT 'Name according to APi source',
  PRIMARY KEY (`id`),
  KEY `record_dataset` (`dataset_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=4 ;

-- --------------------------------------------------------

--
-- Table structure for table `datasets`
--

CREATE TABLE IF NOT EXISTS `datasets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(127) DEFAULT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `skip_first_row` int(1) DEFAULT NULL,
  `status` int(11) unsigned DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `place_column` int(11) DEFAULT NULL,
  `liesin_column` int(11) DEFAULT NULL,
  `startdate_column` int(11) DEFAULT NULL,
  `enddate_column` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dataset_user` (`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=31 ;

-- --------------------------------------------------------

--
-- Table structure for table `field_mapping`
--

CREATE TABLE IF NOT EXISTS `field_mapping` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dataset_id` int(11) DEFAULT NULL,
  `placename` int(11) DEFAULT NULL COMMENT 'The key of the column that holds the term to search on',
  `liesin` int(11) DEFAULT NULL COMMENT 'The key of the column that has holds the term to search within',
  `hg_type` varchar(63) DEFAULT NULL COMMENT 'The type of PiT to search for',
  `hg_dataset` varchar(127) DEFAULT NULL COMMENT 'The dataset that should be returned',
  `search_option` int(4) DEFAULT NULL COMMENT 'The type of exactness to search on',
  `geometry` int(1) DEFAULT NULL COMMENT 'Whether to fetch geometries or not',
  `date_begin` int(11) DEFAULT NULL COMMENT 'The key of the column that has holds the start date',
  `date_end` int(11) DEFAULT NULL COMMENT 'The key of the column that has holds the end date',
  `created_on` datetime DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  `status` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `record_dataset` (`dataset_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Which column in the dataset has what info' AUTO_INCREMENT=22 ;

-- --------------------------------------------------------

--
-- Table structure for table `records`
--

CREATE TABLE IF NOT EXISTS `records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dataset_id` int(11) DEFAULT NULL,
  `original_name` varchar(255) NOT NULL DEFAULT '' COMMENT 'Name as supplied in csv',
  `hg_id` varchar(255) DEFAULT NULL,
  `hg_uri` varchar(255) DEFAULT NULL,
  `hg_name` varchar(255) DEFAULT NULL COMMENT 'Name according to APi source',
  `hg_geometry` text,
  `hg_type` varchar(255) DEFAULT NULL COMMENT 'Name according to APi source',
  `hg_dataset` varchar(255) DEFAULT NULL COMMENT 'Name according to APi source',
  `created_on` datetime NOT NULL,
  `updated_on` datetime DEFAULT NULL,
  `status` int(11) DEFAULT NULL COMMENT 'The type of match',
  `hits` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `record_dataset` (`dataset_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1699 ;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL DEFAULT '',
  `password` varchar(255) DEFAULT NULL,
  `salt` varchar(255) NOT NULL DEFAULT '',
  `roles` varchar(255) NOT NULL DEFAULT '',
  `name` varchar(100) NOT NULL DEFAULT '',
  `time_created` int(11) unsigned NOT NULL DEFAULT '0',
  `username` varchar(100) DEFAULT NULL,
  `isEnabled` tinyint(1) NOT NULL DEFAULT '1',
  `confirmationToken` varchar(100) DEFAULT NULL,
  `timePasswordResetRequested` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_email` (`email`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=4 ;

-- --------------------------------------------------------

--
-- Table structure for table `user_custom_fields`
--

CREATE TABLE IF NOT EXISTS `user_custom_fields` (
  `user_id` int(11) NOT NULL,
  `attribute` varchar(50) NOT NULL DEFAULT '',
  `value` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`user_id`,`attribute`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `crowd_mapping`
--
ALTER TABLE `crowd_mapping`
  ADD CONSTRAINT `record_ibfk_2` FOREIGN KEY (`dataset_id`) REFERENCES `datasets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `datasets`
--
ALTER TABLE `datasets`
  ADD CONSTRAINT `dataset_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `records`
--
ALTER TABLE `records`
  ADD CONSTRAINT `record_ibfk_1` FOREIGN KEY (`dataset_id`) REFERENCES `datasets` (`id`) ON DELETE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
