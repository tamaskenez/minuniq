-- phpMyAdmin SQL Dump
-- version 4.8.5
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: May 12, 2019 at 10:06 PM
-- Server version: 5.7.25
-- PHP Version: 7.3.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Database: `minuniq`
--
CREATE DATABASE IF NOT EXISTS `minuniq` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `minuniq`;

-- --------------------------------------------------------

--
-- Table structure for table `finished_games`
--

CREATE TABLE `finished_games` (
  `game_id` int(11) NOT NULL,
  `game_type_id` int(11) NOT NULL,
  `finished` tinyint(1) NOT NULL DEFAULT '0',
  `winner_player_id` int(11) NOT NULL,
  `winner_number` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `games`
--

CREATE TABLE `games` (
  `game_type_id` int(11) NOT NULL,
  `participants` int(11) NOT NULL,
  `winner_number` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `games`
--

INSERT INTO `games` (`game_type_id`, `participants`, `winner_number`) VALUES
(0, 0, NULL),
(1, 0, NULL),
(2, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `game_numbers`
--

CREATE TABLE `game_numbers` (
  `game_type_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `picked_number` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `player`
--

CREATE TABLE `player` (
  `player_id` int(11) NOT NULL,
  `email` varchar(256) NOT NULL,
  `balance` decimal(12,2) NOT NULL DEFAULT '0.00',
  `participation` bit(64) NOT NULL DEFAULT b'0' COMMENT 'Bit mask, i-th bit is set if player participating in game of type i.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `finished_games`
--
ALTER TABLE `finished_games`
  ADD PRIMARY KEY (`game_id`);

--
-- Indexes for table `games`
--
ALTER TABLE `games`
  ADD PRIMARY KEY (`game_type_id`);

--
-- Indexes for table `game_numbers`
--
ALTER TABLE `game_numbers`
  ADD KEY `game_type_id_ix` (`game_type_id`);

--
-- Indexes for table `player`
--
ALTER TABLE `player`
  ADD PRIMARY KEY (`player_id`),
  ADD UNIQUE KEY `email_ix` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `player`
--
ALTER TABLE `player`
  MODIFY `player_id` int(11) NOT NULL AUTO_INCREMENT;
