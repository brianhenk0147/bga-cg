<?php

/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * CrashAndGrab implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * stats.inc.php
 *
 * CrashAndGrab game statistics description
 *
 */

/*
    In this file, you are describing game statistics, that will be displayed at the end of the
    game.

    !! After modifying this file, you must use "Reload  statistics configuration" in BGA Studio backoffice
    ("Control Panel" / "Manage Game" / "Your Game")

    There are 2 types of statistics:
    _ table statistics, that are not associated to a specific player (ie: 1 value for each game).
    _ player statistics, that are associated to each players (ie: 1 value for each player in the game).

    Statistics types can be "int" for integer, "float" for floating point values, and "bool" for boolean

    Once you defined your statistics there, you can start using "initStat", "setStat" and "incStat" method
    in your game logic, using statistics names defined below.

    !! It is not a good idea to modify this file when a game is running !!

    If your game is already public on BGA, please read the following before any change:
    http://en.doc.boardgamearena.com/Post-release_phase#Changes_that_breaks_the_games_in_progress

    Notes:
    * Statistic index is the reference used in setStat/incStat/initStat PHP method
    * Statistic index must contains alphanumerical characters and no space. Example: 'turn_played'
    * Statistics IDs must be >=10
    * Two table statistics can't share the same ID, two player statistics can't share the same ID
    * A table statistic can have the same ID than a player statistics
    * Statistics ID is the reference used by BGA website. If you change the ID, you lost all historical statistic data. Do NOT re-use an ID of a deleted statistic
    * Statistic name is the English description of the statistic as shown to players

*/

$stats_type = array(

    // Statistics global to table
    "table" => array(

        "turns_number" => array("id"=> 10,
                    "name" => totranslate("Turns Taken"),
                    "type" => "int" ),


/*
        Examples:


        "table_teststat1" => array(   "id"=> 10,
                                "name" => totranslate("table test stat 1"),
                                "type" => "int" ),

        "table_teststat2" => array(   "id"=> 11,
                                "name" => totranslate("table test stat 2"),
                                "type" => "float" )
*/
    ),

    // Statistics existing for each player
    "player" => array(

        "turns_number" => array("id"=> 10,
                    "name" => totranslate("Turns Taken"),
                    "type" => "int" ),

        "rounds_started" => array("id"=> 11,
                    "name" => totranslate("Rounds With Probe"),
                    "type" => "int" ),

        "crewmembers_picked_up" => array("id"=> 12,
                    "name" => totranslate("Crewmembers Picked Up"),
                    "type" => "int" ),

        "crewmembers_you_stole" => array("id"=> 13,
                    "name" => totranslate("Crewmembers You Stole"),
                    "type" => "int" ),

        "crewmembers_stolen_from_you" => array("id"=> 14,
                    "name" => totranslate("Crewmembers Stolen from You"),
                    "type" => "int" ),

        "saucers_you_crashed" => array("id"=> 15,
                    "name" => totranslate("Saucers You Crashed"),
                    "type" => "int" ),

        "times_you_crashed" => array("id"=> 16,
                    "name" => totranslate("Times You Crashed"),
                    "type" => "int" ),

        "distance_you_were_pushed" => array("id"=> 17,
                    "name" => totranslate("Distance You Were Pushed"),
                    "type" => "int" ),

        "2s_played" => array("id"=> 18,
                    "name" => totranslate("2s Played"),
                    "type" => "int" ),

        "3s_played" => array("id"=> 19,
                    "name" => totranslate("3s Played"),
                    "type" => "int" ),

        "Xs_played" => array("id"=> 20,
                    "name" => totranslate("0-5s Played"),
                    "type" => "int" ),

        "accelerators_used" => array("id"=> 21,
                    "name" => totranslate("Accelerators Used"),
                    "type" => "int" ),

        "boosters_used" => array("id"=> 22,
                    "name" => totranslate("Boosters Used"),
                    "type" => "int" ),

        "distance_moved" => array("id"=> 23,
                    "name" => totranslate("Distance Moved"),
                    "type" => "int" ),

        "upgrades_played" => array("id"=> 24,
                    "name" => totranslate("Upgrades Collected"),
                    "type" => "int" ),

        "upgrades_activated" => array("id"=> 25,
                    "name" => totranslate("Upgrade Uses"),
                    "type" => "int" ),


/*
        Examples:


        "player_teststat1" => array(   "id"=> 10,
                                "name" => totranslate("player test stat 1"),
                                "type" => "int" ),

        "player_teststat2" => array(   "id"=> 11,
                                "name" => totranslate("player test stat 2"),
                                "type" => "float" )

*/
    )

);
