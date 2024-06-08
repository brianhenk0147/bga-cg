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
 * CrashAndGrab.view.php
 *
 * This is your "view" file.
 *
 * The method "build_page" below is called each time the game interface is displayed to a player, ie:
 * _ when the game starts
 * _ when a player refreshes the game page (F5)
 *
 * "build_page" method allows you to dynamically modify the HTML generated for the game interface. In
 * particular, you can set here the values of variables elements defined in crashandgrab_crashandgrab.tpl (elements
 * like {MY_VARIABLE_ELEMENT}), and insert HTML block elements (also defined in your HTML template file)
 *
 * Note: if the HTML of your game interface is always the same, you don't have to place anything here.
 *
 */

  require_once( APP_BASE_PATH."view/common/game.view.php" );

  class view_crashandgrab_crashandgrab extends game_view
  {
    function getGameName() {
        return "crashandgrab";
    }
  	function build_page( $viewArgs )
  	{
  	    // Get players & players number
        $players = $this->game->loadPlayersBasicInfos();
        $players_nbr = count( $players );

        /*********** Place your code below:  ************/


        /*

        // Examples: set the value of some element defined in your tpl file like this: {MY_VARIABLE_ELEMENT}

        // Display a specific number / string
        $this->tpl['MY_VARIABLE_ELEMENT'] = $number_to_display;

        // Display a string to be translated in all languages:
        $this->tpl['MY_VARIABLE_ELEMENT'] = self::_("A string to be translated");

        // Display some HTML content of your own:
        $this->tpl['MY_VARIABLE_ELEMENT'] = self::raw( $some_html_code );

        */

        $this->tpl['MY_HAND'] = self::_("Your Zigs");


        $this->tpl['TILE_1_NUMBER'] = $this->game->getTileNumber(1);
        $this->tpl['TILE_1_SIDE'] = $this->game->getTileSide(1);
        $this->tpl['TILE_1_ROTATION'] = $this->game->getTileRotation(1);
        $this->tpl['TILE_2_NUMBER'] = $this->game->getTileNumber(2);
        $this->tpl['TILE_2_SIDE'] = $this->game->getTileSide(2);
        $this->tpl['TILE_2_ROTATION'] = $this->game->getTileRotation(2);
        $this->tpl['TILE_3_NUMBER'] = $this->game->getTileNumber(3);
        $this->tpl['TILE_3_SIDE'] = $this->game->getTileSide(3);
        $this->tpl['TILE_3_ROTATION'] = $this->game->getTileRotation(3);
        $this->tpl['TILE_4_NUMBER'] = $this->game->getTileNumber(4);
        $this->tpl['TILE_4_SIDE'] = $this->game->getTileSide(4);
        $this->tpl['TILE_4_ROTATION'] = $this->game->getTileRotation(4);
        $this->tpl['TILE_5_NUMBER'] = $this->game->getTileNumber(5);
        $this->tpl['TILE_5_SIDE'] = $this->game->getTileSide(5);
        $this->tpl['TILE_5_ROTATION'] = $this->game->getTileRotation(5);
        $this->tpl['TILE_6_NUMBER'] = $this->game->getTileNumber(6);
        $this->tpl['TILE_6_SIDE'] = $this->game->getTileSide(6);
        $this->tpl['TILE_6_ROTATION'] = $this->game->getTileRotation(6);
        $this->tpl['TILE_7_NUMBER'] = $this->game->getTileNumber(7);
        $this->tpl['TILE_7_SIDE'] = $this->game->getTileSide(7);
        $this->tpl['TILE_7_ROTATION'] = $this->game->getTileRotation(7);
        $this->tpl['TILE_8_NUMBER'] = $this->game->getTileNumber(8);
        $this->tpl['TILE_8_SIDE'] = $this->game->getTileSide(8);
        $this->tpl['TILE_8_ROTATION'] = $this->game->getTileRotation(8);
        $this->tpl['TILE_9_NUMBER'] = $this->game->getTileNumber(9);
        $this->tpl['TILE_9_SIDE'] = $this->game->getTileSide(9);
        $this->tpl['TILE_9_ROTATION'] = $this->game->getTileRotation(9);

        /*

        // Example: display a specific HTML block for each player in this game.
        // (note: the block is defined in your .tpl file like this:
        //      <!-- BEGIN myblock -->
        //          ... my HTML code ...
        //      <!-- END myblock -->


        $this->page->begin_block( "crashandgrab_crashandgrab", "myblock" );
        foreach( $players as $player )
        {
            $this->page->insert_block( "myblock", array(
                                                    "PLAYER_NAME" => $player['player_name'],
                                                    "SOME_VARIABLE" => $some_value
                                                    ...
                                                     ) );
        }

        */

        /*
        $this->page->begin_block( "crashandgrab_crashandgrab", "square" );

                $hor_scale = 36;
                $ver_scale = 36;
                for( $x=0; $x<=16; $x++ )
                {
                    for( $y=0; $y<=16; $y++ )
                    {
                        $this->page->insert_block( "square", array(
                            'X' => $x,
                            'Y' => $y,
                            'LEFT' => round( ($x-1)*$hor_scale+10 ),
                            'TOP' => round( ($y-1)*$ver_scale+7 )
                        ) );
                    }
                }
        */

        // get the player name, color, and ID for everyone
        $players = $this->game->loadPlayersBasicInfos();


        global $g_user;
        $current_player_id = $g_user->get_id();

        // get all saucers
        $allSaucers = $this->game->getAllSaucers();

        // get the saucers this player owns
        $thisPlayersSaucers = $this->game->getSaucersForPlayer($current_player_id);

        // SAUCER MAT AREAS
        $this->page->begin_block( "crashandgrab_crashandgrab", "saucer" );
        foreach( $thisPlayersSaucers as $saucer )
        {
                $this->page->insert_block( "saucer", array(
                                                    "PLAYER_COLOR" => $saucer['color'],
                                                    "PLAYER_ID" => $saucer['owner'],
                                                    "PLAYER_NAME" => $saucer['ownerName']
                                                     ) );

        }


        // GARMENTS
        $this->page->begin_block( "crashandgrab_crashandgrab", "lost_crewmembers" );
        $playerIndex = 0;
        foreach( $allSaucers as $saucer )
        {

                $this->page->insert_block( "lost_crewmembers", array(
                                                    "PLAYER_COLOR" => $saucer['ostrich_color'],
                                                    "PLAYER_ID" => $saucer['ostrich_owner']
                                                     ) );
                $playerIndex++;
        }


        /*********** Do not change anything below this line  ************/
  	}
  }
