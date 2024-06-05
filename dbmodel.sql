
-- ------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- CrashAndGrab implementation : © <Your name here> <Your email address here>
--
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----

-- dbmodel.sql

-- This is the file where you are describing the database schema of your game
-- Basically, you just have to export from PhpMyAdmin your table structure and copy/paste
-- this export here.
-- Note that the database itself and the standard tables ("global", "stats", "gamelog" and "player") are
-- already created and must not be created here

-- Note: The database schema is created from this file when the game starts. If you modify this file,
--       you have to restart a game to see your changes in database.

-- Example 1: create a standard "card" table to be used with the "Deck" tools (see example game "hearts"):

-- CREATE TABLE IF NOT EXISTS `card` (
--   `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
--   `card_type` varchar(16) NOT NULL,
--   `card_type_arg` int(11) NOT NULL,
--   `card_location` varchar(16) NOT NULL,
--   `card_location_arg` int(11) NOT NULL,
--   PRIMARY KEY (`card_id`)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `movementCards` (
  `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `card_type` varchar(16) NOT NULL,
  `card_type_arg` int(11) NOT NULL,
  `card_location` varchar(30) NOT NULL,
  `card_location_arg` int(11) NOT NULL,
  `card_ostrich` varchar(16) NOT NULL,
  PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `trapCards` (
  `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `card_type` varchar(16) NOT NULL,
  `card_type_arg` int(11) NOT NULL,
  `card_location` varchar(30) NOT NULL,
  `card_location_arg` int(11) NOT NULL,
  `card_ostrich` varchar(16) NOT NULL,
  PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `upgradeCards` (
  `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `card_type` varchar(16) NOT NULL,
  `card_type_arg` int(11) NOT NULL,
  `card_location` varchar(30) NOT NULL,
  `card_location_arg` int(11) NOT NULL,
  `card_is_played` smallint(5) unsigned NOT NULL DEFAULT '0',
  `times_activated_this_round` smallint(5) unsigned NOT NULL DEFAULT '0',
  `asked_to_activate_this_round` smallint(5) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `board` (
  `board_x` smallint(5) unsigned NOT NULL,
  `board_y` smallint(5) unsigned NOT NULL,
  `board_space_type` varchar(16) NOT NULL,
  PRIMARY KEY (`board_x`,`board_y`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `ostrich` (
  `ostrich_x` smallint(5) unsigned NOT NULL,
  `ostrich_y` smallint(5) unsigned NOT NULL,
  `ostrich_owner` int(10) unsigned NOT NULL,
  `ostrich_color` varchar(16) NOT NULL,
  `ostrich_last_direction` varchar(16) NOT NULL,
  `ostrich_last_distance` int(10) unsigned NOT NULL,
  `ostrich_zig_direction` varchar(16) NOT NULL,
  `ostrich_zig_distance` int(10) unsigned NOT NULL DEFAULT '20',
  `ostrich_chosen_x_value` int(10) unsigned NOT NULL DEFAULT '10',
  `ostrich_last_turn_order` smallint(5) unsigned NOT NULL,
  `ostrich_has_zag` smallint(5) unsigned NOT NULL,
  `ostrich_is_chosen` smallint(5) unsigned NOT NULL,
  `ostrich_turns_taken` int(10) unsigned NOT NULL,
  `ostrich_has_crown` smallint(5) unsigned NOT NULL,
  `ostrich_is_dizzy` smallint(5) unsigned NOT NULL,
  `ostrich_cliff_respawn_order` smallint(5) unsigned NOT NULL,
  `ostrich_causing_cliff_fall` varchar(16) NOT NULL,
  `ostrich_steal_garment_order` smallint(5) unsigned NOT NULL DEFAULT '0',
  `crash_penalty_rendered` smallint(5) unsigned NOT NULL,
  `energy_quantity` int(10) unsigned NOT NULL,
  `booster_quantity` int(10) unsigned NOT NULL,
  `passed_by_other_saucer` smallint(5) unsigned NOT NULL DEFAULT '0',
  `skipped_passing` smallint(5) unsigned NOT NULL DEFAULT '0',
  `skipped_taking` smallint(5) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`ostrich_color`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `tile` (
  `tile_number` smallint(5) unsigned NOT NULL,
  `tile_position` smallint(5) unsigned NOT NULL,
  `tile_x` smallint(5) unsigned NOT NULL,
  `tile_y` smallint(5) unsigned NOT NULL,
  `tile_use_side_A` smallint(5) unsigned NOT NULL,
  `tile_degree_rotation` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`tile_number`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `garment` (
  `garment_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `garment_x` smallint(5) unsigned NOT NULL,
  `garment_y` smallint(5) unsigned NOT NULL,
  `garment_location` varchar(30) NOT NULL,
  `garment_color` varchar(16) NOT NULL,
  `garment_type` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`garment_id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `garmentReplacementQueue` (
  `order_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ostrich_color` varchar(16) NOT NULL,
  `player` int(10) unsigned NOT NULL,
  PRIMARY KEY (`order_id`)
) ENGINE=InnoDB;

-- Example 2: add a custom field to the standard "player" table
 ALTER TABLE `player` ADD `player_turns_taken_this_round` INT UNSIGNED NOT NULL DEFAULT '0';
 ALTER TABLE `player` ADD `player_custom_turn_order` INT UNSIGNED NOT NULL DEFAULT '0';
 ALTER TABLE `player` ADD `player_traps_drawn_this_round` INT UNSIGNED NOT NULL DEFAULT '0';
