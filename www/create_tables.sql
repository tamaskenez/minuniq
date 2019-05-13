-- phpMyAdmin SQL Dump
-- version 4.8.5
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: May 13, 2019 at 01:53 PM
-- Server version: 5.7.25
-- PHP Version: 7.3.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Database: `minuniq`
--

-- --------------------------------------------------------

--
-- Table structure for table `current_game`
--

CREATE TABLE `current_game` (
  `game_type_id` int(11) NOT NULL,
  `num_players` int(11) NOT NULL,
  `winner_number` int(11) DEFAULT NULL,
  `game_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `current_game`
--

INSERT INTO `current_game` (`game_type_id`, `num_players`, `winner_number`, `game_id`) VALUES
(0, 0, NULL, NULL),
(1, 0, NULL, NULL),
(2, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `game_history`
--

CREATE TABLE `game_history` (
  `game_id` int(11) NOT NULL,
  `game_type_id` int(11) NOT NULL,
  `finished` tinyint(1) NOT NULL DEFAULT '0',
  `winner_player_email` varchar(256) DEFAULT NULL,
  `winner_number` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `game_picked_numbers`
--

CREATE TABLE `game_picked_numbers` (
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
  `balance` decimal(12,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `current_game`
--
ALTER TABLE `current_game`
  ADD PRIMARY KEY (`game_type_id`);

--
-- Indexes for table `game_history`
--
ALTER TABLE `game_history`
  ADD PRIMARY KEY (`game_id`);

--
-- Indexes for table `game_picked_numbers`
--
ALTER TABLE `game_picked_numbers`
  ADD UNIQUE KEY `game_type_player_ix` (`game_type_id`,`player_id`);

--
-- Indexes for table `player`
--
ALTER TABLE `player`
  ADD PRIMARY KEY (`player_id`),
  ADD UNIQUE KEY `email_ix` (`email`) USING HASH;

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `game_history`
--
ALTER TABLE `game_history`
  MODIFY `game_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `player`
--
ALTER TABLE `player`
  MODIFY `player_id` int(11) NOT NULL AUTO_INCREMENT;
