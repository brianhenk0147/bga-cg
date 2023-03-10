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
  * crashandgrab.game.php
  *
  * This is the main file for your game logic.
  *
  * In this PHP file, you are going to defines the rules of the game.
  *
  */


require_once( APP_GAMEMODULE_PATH.'module/table/table.game.php' );


class CrashAndGrab extends Table
{
	function __construct( )
	{
		//throw new feException( "construct" );
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();

				// INTEGER VALUES ONLY!
        self::initGameStateLabels( array(
					"CURRENT_ROUND" => 10,
					"NUMBER_OF_PLAYERS" => 11

            //    "my_second_global_variable" => 11,
            //      ...
            //    "my_first_game_variant" => 100,
            //    "my_second_game_variant" => 101,
            //      ...
        ) );

				// create Movement Card Deck
				$this->movementCards = self::getNew( "module.common.deck" );
        $this->movementCards->init( "movementCards" );
				$this->movementCards->autoreshuffle_custom = array('movementCardDeck' => 'discard');
				$this->movementCards->autoreshuffle = true; // automatically reshuffle when you run out of cards

				// create Trap Deck
				$this->trapCards = self::getNew( "module.common.deck" );
        $this->trapCards->init( "trapCards" );
				$this->trapCards->autoreshuffle_custom = array('trapCardDeck' => 'discard');
				$this->trapCards->autoreshuffle = true; // automatically reshuffle when you run out of cards

				$this->UP_DIRECTION = "MOUNTAIN";
				$this->DOWN_DIRECTION = "BRIDGE";
				$this->LEFT_DIRECTION = "RIVER";
				$this->RIGHT_DIRECTION = "CACTUS";

				$this->LAST_MOVED_OSTRICH = "";
	}

    protected function getGameName( )
    {
		// Used for translations and stuff. Please do not modify.
        return "crashandgrab";
    }

    /*
        setupNewGame:

        This method is called only once, when a new game is launched.
        In this method, you must setup the game according to the game rules, so that
        the game is ready to be played.
    */
    protected function setupNewGame( $players, $options = array() )
    {

			//throw new feException( "setupNewGame" );

        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/orange/brown
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos['player_colors'];

        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
        $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = array();
        foreach( $players as $player_id => $player )
        {
            $color = array_shift( $default_colors );
            $values[] = "('".$player_id."','$color','".$player['player_canal']."','".addslashes( $player['player_name'] )."','".addslashes( $player['player_avatar'] )."')";
        }
        $sql .= implode( $values, ',' );
        self::DbQuery( $sql );
        self::reattributeColorsBasedOnPreferences( $players, $gameinfos['player_colors'] );
        self::reloadPlayersBasicInfos();





        /************ Start the game initialization *****/

        // Init global values with their initial values
        self::setGameStateInitialValue( 'NUMBER_OF_PLAYERS', count($players) );

        // Init game statistics
        // (note: statistics used in this file must be defined in your stats.inc.php file)
        //self::initStat( 'table', 'table_teststat1', 0 );    // Init a table statistics
        //self::initStat( 'player', 'player_teststat1', 0 );  // Init a player statistics (for all players)


				$this->initializeStats();

				$this->initializeBoard(count($players)); // randomly choose which tiles to use depending on number of players

				$this->initializeOstriches();

				$this->initializeGarments();

				$this->initializeZigCards();

				// And then deal 4 zig cards to each player
		     foreach( $players as $player_id => $player )
		     {
		        $cards = $this->movementCards->pickCards( 4, 'movementCardDeck', $player_id );
						$this->updateZigDrawStats($cards, $player_id); // update the statistics about zig cards being drawn

		        // Notify player about their cards
		        self::notifyPlayer( $player_id, 'newZigs', '', array(
		            'cards' => $cards
		         ) );

		     }

				 $this->initializeTrapCards();


				 // for testing, draw some trap cards
				 foreach( $players as $player_id => $player )
		     {
					 	if($player_id % 2 == 0)
						{ // have every other player draw a trap for testing
		        		$this->drawTrap($player_id);
						}
		     }


        // TODO: setup the initial game situation here

				$this->setGameStateValue("CURRENT_ROUND", 1); // start on round 1



        // Activate first player (which is in general a good idea :) )
        //$this->activeNextPlayer();

        /************ End of the game initialization *****/
    }

    /*
        getAllDatas:

        Gather all informations about current game situation (visible by the current player).

        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    protected function getAllDatas()
    {
        $result = array();

        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score FROM player ";
        $result['players'] = self::getCollectionFromDb( $sql );

        // TODO: Gather all information about current game situation (visible by player $current_player_id).

				$player_id = self::getCurrentPlayerId();    // !! We must only return informations visible by this player !!

/*
				// get any cards that are in this player's hand
				$playerhands = $this->movementCards->getCardsInLocation( 'hand', $player_id );
				foreach( $playerhands as $card )
				{
					$cardid = $card['id']; // internal id
					$cardtype = $card['type']; // clockwise or counterclockwise
					$cardtypearg = $card['type_arg']; // 0, 1, 2, 3

					$msgHandCard = "<b>Initial Hand Card:</b> id is $cardid with type of $cardtype and type_arg of $cardtypearg for this card.";
    			self::warn($msgHandCard);
				}
*/
				$result['stateName'] = $this->getStateName(); // send the state name in case the client needs it

				// put the cards that are in this player's hand into the array that is returned to the UI/javascript/client layer with the key "hand"
        $result['hand'] = $this->movementCards->getCardsInLocation( 'hand', $player_id );

        // put the cards that are played by a player into the array that is returned to the UI/javascript/client layer with the key "played_playerid"
        $result['played'] = self::getObjectListFromDB( "SELECT card_id id, card_type turn, card_type_arg distance, card_location_arg player, card_ostrich color, ostrich_last_direction
                                                       FROM movementCards
																											 JOIN ostrich ON movementCards.card_ostrich=ostrich.ostrich_color
                                                       WHERE card_location='played'" );

				$result['zigChosen'] = self::getObjectListFromDB( "SELECT card_id id, card_type turn, card_type_arg distance, card_location_arg player, card_ostrich color, ostrich_last_direction
                                                       FROM movementCards
																											 JOIN ostrich ON movementCards.card_ostrich=ostrich.ostrich_color
                                                       WHERE card_location='zigChosen'" );

				// put the cards that have been discarded into the array that is returned to the UI/javascript/client layer with the key "played_playerid"
				$result['discard'] = $this->movementCards->getCardsInLocation( 'discard', $player_id );

				// get any cards that are in this player's hand
				$traphands = $this->trapCards->getCardsInLocation( 'hand', $player_id );
				foreach( $traphands as $card )
				{
					$cardid = $card['id']; // internal id
					$cardtype = $card['type']; // name
					$cardtypearg = $card['type_arg']; // card id 0, 1, 2, 3

					$msgHandCard = "<b>Initial Trap Hand Card:</b> id is $cardid with type of $cardtype and type_arg of $cardtypearg for this card.";
					self::warn($msgHandCard);
				}

				// put the trap cards that are in all player hands
				$result['trapHands'] = $this->trapCards->getCardsInLocation( 'hand' );

				// get the board layout
        $result['board'] = self::getObjectListFromDB( "SELECT board_x x, board_y y, board_space_type space_type
                                                       FROM board
                                                       WHERE board_space_type IS NOT NULL" );

  			// get the ostrich positions
				$result['ostrich'] = self::getObjectListFromDB( "SELECT ostrich_x x,ostrich_y y, ostrich_color color, ostrich_owner owner, ostrich_last_direction last_direction, ostrich_has_zag has_zag, ostrich_has_crown
				                                               FROM ostrich
				                                               WHERE 1" );

				$result['lastMovedOstrich'] = $this->LAST_MOVED_OSTRICH;

				$result['garment'] = self::getObjectListFromDB( "SELECT garment_x,garment_y,garment_location,garment_color,garment_type FROM garment ORDER BY garment_location,garment_type");

        return $result;
    }

    /*
        getGameProgression:

        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).

        This method is called each time we are in a game state with the "updateGameProgression" property set to true
        (see states.inc.php)
    */
    function getGameProgression()
    {
				$players = self::loadPlayersBasicInfos();
				$numberOfPlayers = count($players); // get the number of players in the game

				$maxGarments = ($numberOfPlayers * 3) + 1; // get the maximum number of garments that can be acquired before winning
				$numberOfGarmentsAcquired = $this->countTotalAcquiredGarments();

				$percentageCompleted = intval(100 * ($numberOfGarmentsAcquired / $maxGarments)); // divide current by max and multiply by 100 to get an integer between 1-100

				//throw new feException( "PROGRESSION number of garments acquired " . $numberOfGarmentsAcquired . " and max garments " . $maxGarments . " and percentageCompleted " . $percentageCompleted);

        return $percentageCompleted;
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////

    /*
        In this space, you can put any utility methods useful for your game logic
    */

		function initializeStats()
		{
				self::initStat( 'table', 'x_drawn', 0 );
				self::initStat( 'table', 'ones_drawn', 0 );
				self::initStat( 'table', 'twos_drawn', 0 );
				self::initStat( 'table', 'threes_drawn', 0 );

				self::initStat( 'player', 'turns_number', 0 );
				self::initStat( 'player', 'rounds_started', 0 );
				self::initStat( 'player', 'zags_claimed', 0 );
				self::initStat( 'player', 'traps_drawn', 0 ); // number of traps the player has drawn
				self::initStat( 'player', 'x_drawn', 0 );
				self::initStat( 'player', 'ones_drawn', 0 );
				self::initStat( 'player', 'twos_drawn', 0 );
				self::initStat( 'player', 'threes_drawn', 0 );

				self::initStat( 'player', 'ran_off_cliff', 0 );
				self::initStat( 'player', 'pushed_ostrich_off_cliff', 0 );
				self::initStat( 'player', 'was_pushed_off_cliff', 0 );
				self::initStat( 'player', 'pushed_an_ostrich', 0 );
				self::initStat( 'player', 'was_pushed', 0 );

				self::initStat( 'player', 'i_used_trap', 0 );
				self::initStat( 'player', 'trap_used_on_me', 0 );

				self::initStat( 'player', 'garments_i_stole', 0 );
				self::initStat( 'player', 'garments_stolen_from_me', 0 );
		}

		function initializeZigCards()
		{
				// Create Movement Cards
				// type: clockwise, counterclockwise
				// type_arg: 0=X, 1=1, 2=2, 3=3
				$movementCardsList = array(
						array( 'type' => 'clockwise', 'type_arg' => 0, 'card_location' => 'movementCardDeck','nbr' => 4),
						array( 'type' => 'clockwise', 'type_arg' => 1, 'card_location' => 'movementCardDeck','nbr' => 6),
						array( 'type' => 'clockwise', 'type_arg' => 2, 'card_location' => 'movementCardDeck','nbr' => 9),
						array( 'type' => 'clockwise', 'type_arg' => 3, 'card_location' => 'movementCardDeck','nbr' => 11),
						array( 'type' => 'counterclockwise', 'type_arg' => 0, 'card_location' => 'movementCardDeck','nbr' => 4),
						array( 'type' => 'counterclockwise', 'type_arg' => 1, 'card_location' => 'movementCardDeck','nbr' => 6),
						array( 'type' => 'counterclockwise', 'type_arg' => 2, 'card_location' => 'movementCardDeck','nbr' => 9),
						array( 'type' => 'counterclockwise', 'type_arg' => 3, 'card_location' => 'movementCardDeck','nbr' => 11)
				);

				$this->movementCards->createCards( $movementCardsList, 'movementCardDeck' ); // create the deck

				$this->movementCards->shuffle( 'movementCardDeck' ); // shuffle it
		}

		function initializeTrapCards()
		{


				// Create Movement Cards
				// type: Deface Paint, Twirlybird
				// type_arg: probably don't need... should mimic card id

				$trapCardsList = array(
						array( 'type' => 'Blastorocket', 'type_arg' => 0, 'card_location' => 'trapCardDeck','nbr' => 1),
						array( 'type' => 'Boulderdash', 'type_arg' => 1, 'card_location' => 'trapCardDeck','nbr' => 1),
						array( 'type' => 'Deface Paint', 'type_arg' => 2, 'card_location' => 'trapCardDeck','nbr' => 1),
//						array( 'type' => 'Dizzerydoo', 'type_arg' => 3, 'card_location' => 'trapCardDeck','nbr' => 1),
//						array( 'type' => 'Gadget Gobbler', 'type_arg' => 4, 'card_location' => 'trapCardDeck','nbr' => 1),
//						array( 'type' => 'Kleptocopter', 'type_arg' => 5, 'card_location' => 'trapCardDeck','nbr' => 1),
						array( 'type' => 'Krazy Crane', 'type_arg' => 6, 'card_location' => 'trapCardDeck','nbr' => 1),
//						array( 'type' => 'Overheater', 'type_arg' => 7, 'card_location' => 'trapCardDeck','nbr' => 1),
						array( 'type' => 'Rooster Booster', 'type_arg' => 8, 'card_location' => 'trapCardDeck','nbr' => 1),
//						array( 'type' => 'Scrambler', 'type_arg' => 9, 'card_location' => 'trapCardDeck','nbr' => 1),
//						array( 'type' => 'Stinkbomb', 'type_arg' => 10, 'card_location' => 'trapCardDeck','nbr' => 1),
						array( 'type' => 'Twirlybird', 'type_arg' => 11, 'card_location' => 'trapCardDeck','nbr' => 1)
				);


				$this->trapCards->createCards( $trapCardsList, 'trapCardDeck' ); // create the deck

				$this->trapCards->shuffle( 'trapCardDeck' ); // shuffle it
		}

		function initializeGarments()
		{

				//$ostriches = $this->getOstrichesInOrder(); // get all ostriches

				$ostriches = self::getObjectListFromDB( "SELECT ostrich_color color, ostrich_owner owner
																											 FROM ostrich
																											 WHERE 1" );


				shuffle($ostriches); // randomize the order

				for($i=0; $i < count($ostriches); $i++)
				{ // go through our ostriches

						$thisOstrich = $ostriches[$i];
						$color = $thisOstrich['color'];
						$owner = $thisOstrich['owner'];
						$locX = 0;
						$locY = 0;
						$location = 'pile'; // by default, assign to the garment pile

						// HEAD
						$type = 0; // HEAD
						if($i==0)
						{ // this ostrich is first player (so it is NOT a starting garment)

								// set the NON-starting-garment attributes
								$locX = 0;
								$locY = 0;
								$location = 'pile';


								$this->giveCrown($color); // give the ostrich the crown
								$this->gamestate->changeActivePlayer( $owner ); // set the active player (this cannot be done in an activeplayer game state)
						}
						else if($i==1 || $i==2 || ($i==3 && $this->getNumberOfPlayers() > 4) || ($i==4 && $this->getNumberOfPlayers() > 5))
						{ // this is a starting garment

								// get the X and Y positions of the ostrich to which the garment belongs
								$ostrichX = self::getUniqueValueFromDb("SELECT ostrich_x FROM ostrich WHERE ostrich_color='$color'");
								$ostrichY = self::getUniqueValueFromDb("SELECT ostrich_y FROM ostrich WHERE ostrich_color='$color'");

								$furthestEmptyCrates = $this->getFurthestEmptyCrates($ostrichX, $ostrichY);

								//NOTE: We are randomizing ties instead of letting the player choose like in the physical version.
								shuffle($furthestEmptyCrates); // randomize in case of ties
								$locX = $furthestEmptyCrates[0]['board_x'];
								$locY = $furthestEmptyCrates[0]['board_y'];

								$location = 'board';
						}
						else
						{ // this is NOT a starting garment and NOT the first player
								$locX = 0;
								$locY = 0;
								$location = 'pile';
						}

						// insert the HEAD piece
						$sqlGarment = "INSERT INTO garment (garment_x,garment_y,garment_location,garment_color,garment_type) VALUES ";
						$sqlGarment .= "(".$locX.",".$locY.",'".$location."','".$color."',".$type.") ";
						//echo "locX ($locX) locY ($locY) location($location) color ($color) type ($type) <br>";
						//self::DbQUery("INSERT INTO garment (garment_x,garment_y,garment_location,garment_color,garment_type) VALUES (0,0,'pile','ff0000',0)");
						self::DbQuery( $sqlGarment );

						// the rest will all be in the garment
						$locX = 0;
						$locY = 0;
						$location = 'pile';


						// BODY
						$type = 1; // BODY
						$sqlGarment = "INSERT INTO garment (garment_x,garment_y,garment_location,garment_color,garment_type) VALUES ";
						$sqlGarment .= "(".$locX.",".$locY.",'".$location."','".$color."',".$type.") ";
						self::DbQuery( $sqlGarment );

						// LEGS
						$type = 2; // LEGS
						$sqlGarment = "INSERT INTO garment (garment_x,garment_y,garment_location,garment_color,garment_type) VALUES ";
						$sqlGarment .= "(".$locX.",".$locY.",'".$location."','".$color."',".$type.") ";
						self::DbQuery( $sqlGarment );

						// FEET
						$type = 3; // FEET
						$sqlGarment = "INSERT INTO garment (garment_x,garment_y,garment_location,garment_color,garment_type) VALUES ";
						$sqlGarment .= "(".$locX.",".$locY.",'".$location."','".$color."',".$type.") ";
						self::DbQuery( $sqlGarment );


				}

		}

		function initializeOstriches()
		{
				$startingLocations = $this->getStartingOstrichLocations();
				shuffle($startingLocations); // randomize the order

				$locationListIndex = 0;
				$sqlGetPlayers = "SELECT player_id, player_color ";
				$sqlGetPlayers .= "FROM player ";
				$sqlGetPlayers .= "WHERE 1";
				$dbres = self::DbQuery( $sqlGetPlayers );
				while( $player = mysql_fetch_assoc( $dbres ) )
				{
						$playerId = $player['player_id'];
						$playerColor = $player['player_color'];

						$locX = $startingLocations[$locationListIndex]['board_x'];
						$locY = $startingLocations[$locationListIndex]['board_y'];


						$sqlOstrich = "INSERT INTO ostrich (ostrich_x,ostrich_y,ostrich_color,ostrich_owner,ostrich_has_zag,ostrich_is_chosen, ostrich_has_crown) VALUES ";
						$sqlOstrich .= "(".$locX.",".$locY.",'".$playerColor."',".$playerId.",0,0,0) ";
						//$sqlOstrich .= "('".$locX."','".$locY."','".addslashes($player['player_color'])."','".$player_id."')";

						if(($this->getNumberOfPlayers() == 2 || $this->getNumberOfPlayers() == 3) && $locationListIndex == 0)
						{
								// add a second ostrich for this player
						}
						else if(($this->getNumberOfPlayers() == 2 || $this->getNumberOfPlayers() == 3) && $locationListIndex == 1)
						{
								// add a second ostrich for this player
						}
						else if($this->getNumberOfPlayers() == 3 && $locationListIndex == 2)
						{
								// add a second ostrich for this player
						}

						self::DbQuery( $sqlOstrich );



						$locationListIndex++; // go to the next starting location
				}

				if(false)
				{ // has multiple ostriches
						// go through players again and add another ostrich for each
				}

		}

		// Get the list of possible moves (x => y => true)
    function getPossibleMoves( $player_id )
    {
        $result = array();

				// get ostrich x
				$ostrich = $this->
				// get ostrich y
				// get all crates
				// add them to possible moves if it's empty and not in row or column

        $board = self::getBoard();

        for( $x=1; $x<=8; $x++ )
        {
            for( $y=1; $y<=8; $y++ )
            {
                $returned = self::getTurnedOverDiscs( $x, $y, $player_id, $board );
                if( count( $returned ) == 0 )
                {
                    // No discs returned => not a possible move
                }
                else
                {
                    // Okay => set this coordinate to "true"
                    if( ! isset( $result[$x] ) )
                        $result[$x] = array();

                    $result[$x][$y] = true;
                }
            }
        }

        return $result;
    }

		function convertGarmentTypeIntToString($garmentAsInt)
		{
				switch($garmentAsInt)
				{
						case 0:
							return "head";
						case 1:
							return "body";
						case 2:
							return "legs";
						case 3:
							return "feet";
				}

				return "";
		}

		function convertGarmentTypeStringToInt($garmentAsString)
		{
				switch($garmentAsString)
				{
						case "head":
							return 0;
						case "body":
							return 1;
						case "legs":
							return 2;
						case "feet":
							return 3;
				}

				return 4;
		}

		function getDegreesRotated($direction)
		{
				switch($direction)
				{
						case "MOUNTAIN":
							return 45;
						case "CACTUS":
							return 315;
						case "BRIDGE":
							return 225;
						case "RIVER":
							return 135;
				}

				return 0;
		}

		function getDirectionFromDegrees($directionAsInt)
		{
				switch($directionAsInt)
				{
						case 45:
							return "MOUNTAIN";
						case 315:
							return "CACTUS";
						case 225:
							return "BRIDGE";
						case 135:
							return "RIVER";
				}

				return "";
		}

		function getRotatedDirection($oldDirectionAsString, $degreesAsInt, $isClockwise)
		{
				$rotationAsInt = $this->getDegreesRotated($oldDirectionAsString); // get the old rotation

				if($isClockwise)
				{ // rotate CLOCKWISE
						$rotationAsInt += $degreesAsInt; // add the number of degrees
						if($rotationAsInt > 315)
						{ // we've rotated more than 360 degrees
								$amountOver = $rotationAsInt - 315;
								$rotationAsInt = $amountOver - 45;
						}
				}
				else
				{ // rotate COUNTER-CLOCKWISE
						$rotationAsInt -= $degreesAsInt; // subtract the number of degrees
						if($rotationAsInt < 45)
						{ // we've rotated below 0
								$amountUnder = 45 - $rotationAsInt;
								$rotationAsInt = 405 - $amountUnder;
						}
				}

				$newDirectionAsString = $this->getDirectionFromDegrees($rotationAsInt);

				return $newDirectionAsString;
		}

		function getStartingOstrichLocations()
		{
				return self::getObjectListFromDB( "SELECT board_x, board_y
																					 FROM board
																					 WHERE board_space_type='O'" );
		}

		function getPlayersWithOstriches()
		{
				$result = array();

				$allOstriches = self::getObjectListFromDB( "SELECT ostrich_color, ostrich_owner, ostrich_zig_distance, ostrich_zig_direction
																					 FROM ostrich ORDER BY ostrich_owner" );

				$currentPlayerId = 0;
				foreach( $allOstriches as $ostrich )
				{
						$owner = $ostrich['ostrich_owner'];
						$color = $ostrich['ostrich_color'];
						$distance = $ostrich['ostrich_zig_distance'];
						$direction = $ostrich['ostrich_zig_direction'];

						if($owner != $currentPlayerId)
						{ // this is a new player we haven't seen yet
								$result[$owner] = array(); // create a new array for this player
						}

						$result[$owner][$color] = array(); // every ostrich needs an array of values
						$result[$owner][$color]['zigDistance'] = $distance; // add distance to the array of values for this ostrich
						$result[$owner][$color]['zigDirection'] = $direction; // add direction to the array of values for this ostrich
				}

				return $result;
		}

		function getAllOstrichesAndOwners()
		{
				$result = array();

				$allOstriches = self::getObjectListFromDB( "SELECT ostrich_color, ostrich_owner
																					 FROM ostrich ORDER BY ostrich_owner" );

				$currentPlayer = 0;
				$ostrichCount = 0;
				$currentPlayerOstrichList = array();
				foreach( $allOstriches as $ostrich )
				{
						if($currentPlayer != 0 )
						{ // this is the first loop

						}

						if($ostrich['ostrich_owner'] == $currentPlayer)
						{ // this is the second ostrich for a player
							$ostrichCount++;
						}
						else
						{ // this is a new player
							$result[$ostrich['ostrich_owner']] = array(); // create a new array for this new player
							$ostrichCount = 0; // reset ostrich count
						}


						$currentPlayer = $ostrich['ostrich_owner']; // always set the current player to the one for this ostrich
						$result[$currentPlayer][$ostrichCount] = $ostrich['ostrich_color']; // set the result


				}

				return $result;
		}

		function getPlayerIdRespawningGarment()
		{
				return $this->peekGarmentReplacementQueue();
		}

		function getGarmentsValidForRespawn()
		{
				$result = array();

				$allGarmentsInPile = self::getObjectListFromDB( "SELECT garment_id, garment_color, garment_type
																							FROM garment
																							WHERE garment_location='pile'" );

				$garmentIndex = 0;
				foreach( $allGarmentsInPile as $garment )
				{ // go through all this ostrich's garments

					$garmentColor = $garment['garment_color'];
					$garmentType = $this->convertGarmentTypeIntToString($garment['garment_type']);

					$result[$garmentIndex] = array();
					$result[$garmentIndex]['garmentType'] = $garmentType;
					$result[$garmentIndex]['garmentColor'] = $garmentColor;
					$garmentIndex++;
				}

				return $result;
		}

		function getDiscardableGarments()
		{
				$result = array();
				$ostrichWhoMustDiscard = $this->getOstrichWhoseTurnItIs(); // get the ostrich who must discard

				$allGarmentsFromThisOstrich = self::getObjectListFromDB( "SELECT garment_id, garment_color, garment_type
																							FROM garment
																							WHERE garment_location='$ostrichWhoMustDiscard'" );

				$garmentIndex = 0;
				foreach( $allGarmentsFromThisOstrich as $garment )
				{ // go through all this ostrich's garments

					$garmentColor = $garment['garment_color'];
					$garmentType = $this->convertGarmentTypeIntToString($garment['garment_type']);
					if($garmentColor != $ostrichWhoMustDiscard)
					{ // we found an off-colored garment
							$result[$garmentIndex] = array();
							$result[$garmentIndex]['garmentType'] = $garmentType;
							$result[$garmentIndex]['garmentColor'] = $garmentColor;
							$garmentIndex++;
					}
				}

				return $result;
		}

		function getNextOstrichToStealFromValue()
		{
				return self::getUniqueValueFromDb("SELECT max(ostrich_steal_garment_order) FROM ostrich");
		}

		function canOstrichBeStolenFrom($ostrich)
		{
				$stealGarmentOrder = self::getUniqueValueFromDb("SELECT ostrich_steal_garment_order FROM ostrich WHERE ostrich_color='$ostrich'");
				if($stealGarmentOrder > 0)
				{
						return true;
				}
				else
				{
					return false;
				}
		}

		function setOstrichToStealFromOrder($ostrichWhoFell)
		{
				$value = $this->getNextOstrichToStealFromValue();
				$value = $value + 1; // add one

				$sqlUpdate = "UPDATE ostrich SET ";
				$sqlUpdate .= "ostrich_steal_garment_order=$value WHERE ";
				$sqlUpdate .= "ostrich_color='$ostrichWhoFell'";

				self::DbQuery( $sqlUpdate );
		}

		function getStealableGarments()
		{
				$result = array();
				$stealerOstrich = $this->getOstrichWhoseTurnItIs(); // the only time you can steal garments is if it's your turn so it's always this ostrich who gets to steal
				$stealerOwner = $this->getOwnerIdOfOstrich($stealerOstrich);

				$nextOstrichToStealFromValue = $this->getNextOstrichToStealFromValue();

				// get all ostriches that are off a cliff and have not had their garment stolen yet
				$allOstrichesOffCliffsAndNotStolen = self::getObjectListFromDB( "SELECT ostrich_color, ostrich_owner, ostrich_zig_distance, ostrich_zig_direction
																					 FROM ostrich WHERE ostrich_cliff_respawn_order<>0 AND ostrich_steal_garment_order=$nextOstrichToStealFromValue ORDER BY ostrich_owner" );

				$garmentIndex = 0;
				foreach( $allOstrichesOffCliffsAndNotStolen as $ostrichObject)
				{ // go through all ostriches that are not mine
						$ostrich = $ostrichObject['ostrich_color'];
						$allGarmentsFromThisOstrich = self::getObjectListFromDB( "SELECT garment_id, garment_color, garment_type
																									FROM garment
																									WHERE garment_location='$ostrich'" );


						foreach( $allGarmentsFromThisOstrich as $garment )
						{ // go through all this ostrich's garments

							$garmentColor = $garment['garment_color'];
							$garmentType = $this->convertGarmentTypeIntToString($garment['garment_type']);
							if($garmentColor != $ostrich)
							{ // we found an off-colored garment
									$result[$garmentIndex] = array();
									$result[$garmentIndex]['garmentType'] = $garmentType;
									$result[$garmentIndex]['garmentColor'] = $garmentColor;
									$garmentIndex++;
							}
						}
				}

				return $result;
		}

		// any crate not in the row or column of any of the garment chooser's ostriches is valid
		function getValidGarmentSpawnSpaces()
		{
				$result = array();

				$garmentChooser = $this->peekGarmentReplacementQueue(); // find the garment chooser
				$garmentChoosersOstriches = $this->getAllPlayersOstriches($garmentChooser); // get all of the ostriches belonging to the player

				// go through each crate
				$allCrates = self::getObjectListFromDB( "SELECT board_x, board_y
																							FROM board
																							WHERE board_space_type='C' OR board_space_type='O'" );

				$validCrateId = 0;
				foreach( $allCrates as $crate )
				{ // go through each crate
							$crateX = $crate['board_x'];
							$crateY = $crate['board_y'];

							$crateValid = true;
							foreach($garmentChoosersOstriches as $ostrich)
							{
									$ostrichX = $this->getOstrichXLocation($ostrich['ostrich_color']);
									$ostrichY = $this->getOstrichYLocation($ostrich['ostrich_color']);

									if($crateX == $ostrichX || $crateY == $ostrichY)
									{ // this crate is in the row or column of this ostrich
											$crateValid = false;
									}

									$ostrichHere = $this->getOstrichAt($crateX, $crateY); // see if an ostrich is here
									$garmentHere = $this->getGarmentIdAt($crateX, $crateY); // see if a garment is here

									if($garmentHere != 0 || $ostrichHere != "")
									{ // this crate is NOT EMPTY

											$crateValid = false;
									}
							}

							if($crateValid)
							{ // this crate is valid

									// add it to our list valid crates
									$result[$validCrateId] = array();
									$result[$validCrateId]['x'] = $crateX;
									$result[$validCrateId]['y'] = $crateY;

									$validCrateId++; // increase our index by one so we're ready for the next crate
							}

				}

				if(count($result) == 0)
				{ // we didn't find ANY valid crates

						// make all spaces not on their row or column valid
						$allNonSkateboardBoardSpaces = self::getObjectListFromDB( "SELECT board_x, board_y
																									FROM board
																									WHERE board_space_type <> 'S' AND board_space_type <> 'D'" );

						$validSpaceId = 0;
						foreach($allNonSkateboardBoardSpaces as $space)
						{
								$spaceX = $space['board_x'];
								$spaceY = $space['board_y'];

								$spaceValid = true;
								foreach($garmentChoosersOstriches as $ostrich)
								{
										$ostrichX = $this->getOstrichXLocation($ostrich['ostrich_color']);
										$ostrichY = $this->getOstrichYLocation($ostrich['ostrich_color']);

										if($spaceX == $ostrichX || $spaceY == $ostrichY)
										{ // this crate is in the row or column of this ostrich
												$spaceValid = false;
										}

										$ostrichHere = $this->getOstrichAt($spaceX, $spaceY); // see if an ostrich is here
										$garmentHere = $this->getGarmentIdAt($spaceX, $spaceY); // see if a garment is here

										if($garmentHere != 0 || $ostrichHere != "")
										{ // this crate is NOT EMPTY

												$spaceValid = false;
										}
								}

								if($spaceValid)
								{ // this crate is valid

										// add it to our list valid crates
										$result[$validSpaceId] = array();
										$result[$validSpaceId]['x'] = $spaceX;
										$result[$validSpaceId]['y'] = $spaceY;

										$validSpaceId++; // increase our index by one so we're ready for the next crate
								}
						}
				}

				return $result;
		}

		function isValidGarmentSpawnLocation($xLocation, $yLocation)
		{
				$isValid = false;

				$getValidGarmentSpawnSpaces = $this->getValidGarmentSpawnSpaces();

				foreach( $getValidGarmentSpawnSpaces as $space )
				{ // go through all the spaces
						$spaceX = $space['x'];
						$spaceY = $space['y'];

						if($spaceX == $xLocation && $spaceY == $yLocation)
						{	// the space in question is in the list
								$isValid = true;
						}
				}

				return $isValid;
		}

		function getAllTrapsThatHaveBeenSet()
		{
			return self::getObjectListFromDB( "SELECT card_id, card_location ostrichTargeted, card_location_arg playerWhoPlayed
																				 FROM trapCards
																				 WHERE card_location<>'trapCardDeck' AND card_location<>'discard' AND card_location<>'hand'" );

			// TODO: Order these somehow... either randomly or based on order in which they were played
		}

		function doesOstrichHaveOffColoredGarmentToDiscard($ostrich)
		{
				$allGarmentsFromThisOstrich = self::getObjectListFromDB( "SELECT garment_id, garment_color
																							FROM garment
																							WHERE garment_location='$ostrich'" );


				foreach( $allGarmentsFromThisOstrich as $garment )
				{ // go through all the this ostriches garments
					//throw new feException( "current player id: " . $current_player_id . " player_id:" . $player_id);

					$garmentColor = $garment['garment_color'];
					if($garmentColor != $ostrich)
					{ // we found an off-colored garment
							return true;
					}
				}

				return false;
		}

		function doesOstrichHaveZag($ostrich)
		{
				$hasZagValue = self::getUniqueValueFromDb("SELECT ostrich_has_zag
																				 FROM ostrich
																				 WHERE ostrich_color='$ostrich'" );

				if($hasZagValue == 1)
				{
					return true;
				}
				else {
					return false;
				}
		}

		function doesOstrichHaveCrown($ostrich)
		{
				$hasCrownValue = self::getUniqueValueFromDb("SELECT ostrich_has_crown
																				 FROM ostrich
																				 WHERE ostrich_color='$ostrich'" );

				if($hasCrownValue == 1)
				{
					return true;
				}
				else {
					return false;
				}
		}

		function getNumberOfTilesToUse($numberOfPlayers)
		{
				switch($numberOfPlayers)
				{
						case 2:
						case 4:
							return 4;
						case 3:
						case 5:
						case 6:
							return 6;
				}

				return 4;
		}

		function getTilePositionOfGarment($garmentId)
		{
				$garmentX = $this->getGarmentXLocation($garmentId);
				$garmentY = $this->getGarmentYLocation($garmentId);

				//echo "getting the tile position of $ostrich ostrich with x $ostrichX and y $ostrichY";

				if($garmentX < 5 && $garmentY < 5)
				{ // ostrich is on tile 1
						return 1;
				}
				else if($garmentX < 9 && $garmentY < 5)
				{ // ostrich is on tile 2
						return 2;
				}
				else if($garmentX < 5 && $garmentY < 9)
				{ // ostrich is on tile 3
						return 3;
				}
				else if($garmentX < 9 && $garmentY < 9)
				{ // ostrich is on tile 4
						return 4;
				}
				else if($garmentX < 13 && $garmentY < 9)
				{ // ostrich is on tile 5
						return 5;
				}
				else
				{ // we'll assume they are on tile 6
						return 6;
				}
		}

		function getTilePositionOfOstrich($ostrich)
		{
				$ostrichX = $this->getOstrichXLocation($ostrich);
				$ostrichY = $this->getOstrichYLocation($ostrich);

				//echo "getting the tile position of $ostrich ostrich with x $ostrichX and y $ostrichY";

				if($ostrichX < 5 && $ostrichY < 5)
				{ // ostrich is on tile 1
						return 1;
				}
				else if($ostrichX < 9 && $ostrichY < 5)
				{ // ostrich is on tile 2
						return 2;
				}
				else if($ostrichX < 5 && $ostrichY < 9)
				{ // ostrich is on tile 3
						return 3;
				}
				else if($ostrichX < 9 && $ostrichY < 9)
				{ // ostrich is on tile 4
						return 4;
				}
				else if($ostrichX < 13 && $ostrichY < 9)
				{ // ostrich is on tile 5
						return 5;
				}
				else
				{ // we'll assume they are on tile 6
						return 6;
				}
		}

		function getTileNumber($tilePosition)
		{
				$tileNumber = null;

				switch($tilePosition)
				{
						case 1:
							$tileNumber = self::getUniqueValueFromDb("SELECT tile_number FROM tile WHERE tile_x=1 AND tile_y=1");
							break;
						case 2:
							$tileNumber = self::getUniqueValueFromDb("SELECT tile_number FROM tile WHERE tile_x=5 AND tile_y=1");
							break;
						case 3:
							$tileNumber = self::getUniqueValueFromDb("SELECT tile_number FROM tile WHERE tile_x=1 AND tile_y=5");
							break;
						case 4:
							$tileNumber = self::getUniqueValueFromDb("SELECT tile_number FROM tile WHERE tile_x=5 AND tile_y=5");
							break;
						case 5:
							$tileNumber = self::getUniqueValueFromDb("SELECT tile_number FROM tile WHERE tile_x=9 AND tile_y=1");
							break;
						case 6:
							$tileNumber = self::getUniqueValueFromDb("SELECT tile_number FROM tile WHERE tile_x=9 AND tile_y=5");
							break;
				}

				if($tileNumber == null)
				{
						return 0;
				}
				else
				{
						return $tileNumber;
				}
		}

		function getTileSide($tilePosition)
		{
				$sideAsInt = 2;
				switch($tilePosition)
				{
						case 1:
							$sideAsInt = self::getUniqueValueFromDb("SELECT tile_use_side_A FROM tile WHERE tile_x=1 AND tile_y=1");
							break;
						case 2:
							$sideAsInt = self::getUniqueValueFromDb("SELECT tile_use_side_A FROM tile WHERE tile_x=5 AND tile_y=1");
							break;
						case 3:
							$sideAsInt = self::getUniqueValueFromDb("SELECT tile_use_side_A FROM tile WHERE tile_x=1 AND tile_y=5");
							break;
						case 4:
							$sideAsInt = self::getUniqueValueFromDb("SELECT tile_use_side_A FROM tile WHERE tile_x=5 AND tile_y=5");
							break;
						case 5:
							$sideAsInt = self::getUniqueValueFromDb("SELECT tile_use_side_A FROM tile WHERE tile_x=9 AND tile_y=1");
							break;
						case 6:
							$sideAsInt = self::getUniqueValueFromDb("SELECT tile_use_side_A FROM tile WHERE tile_x=9 AND tile_y=5");
							break;
				}

				if($sideAsInt == 0)
				{
						return "B";
				}
				else if($sideAsInt == 1)
				{
						return "A";
				}
				else
				{
						return "";
				}
		}

		function getTileRotation($tilePosition)
		{
				$rotation = 5;
				switch($tilePosition)
				{
						case 1:
							$rotation = self::getUniqueValueFromDb("SELECT tile_degree_rotation FROM tile WHERE tile_x=1 AND tile_y=1");
							break;
						case 2:
							$rotation = self::getUniqueValueFromDb("SELECT tile_degree_rotation FROM tile WHERE tile_x=5 AND tile_y=1");
							break;
						case 3:
							$rotation = self::getUniqueValueFromDb("SELECT tile_degree_rotation FROM tile WHERE tile_x=1 AND tile_y=5");
							break;
						case 4:
							$rotation = self::getUniqueValueFromDb("SELECT tile_degree_rotation FROM tile WHERE tile_x=5 AND tile_y=5");
							break;
						case 5:
							$rotation = self::getUniqueValueFromDb("SELECT tile_degree_rotation FROM tile WHERE tile_x=9 AND tile_y=1");
							break;
						case 6:
							$rotation = self::getUniqueValueFromDb("SELECT tile_degree_rotation FROM tile WHERE tile_x=9 AND tile_y=5");
							break;
				}

				if($rotation == null)
				{
						return 6;
				}
				else
				{
						return $rotation;
				}
		}

		function getTileXFromTileNumber($tileNumber)
		{
				return self::getUniqueValueFromDb("SELECT tile_x FROM tile WHERE tile_number=$tileNumber");
		}

		function getTileYFromTileNumber($tileNumber)
		{
				return self::getUniqueValueFromDb("SELECT tile_y FROM tile WHERE tile_number=$tileNumber");
		}

		function getTileX($tilePosition)
		{
				switch($tilePosition)
				{
						case 1:
							return 1;
						case 2:
							return 5;
						case 3:
							return 1;
						case 4:
							return 5;
						case 5:
							return 9;
						case 6:
							return 9;
				}

				return 0;
		}

		function getTileY($tilePosition)
		{
				switch($tilePosition)
				{
						case 1:
							return 1;
						case 2:
							return 1;
						case 3:
							return 5;
						case 4:
							return 5;
						case 5:
							return 1;
						case 6:
							return 5;
				}

				return 0;
		}

		// tilePosition: The position this tile happens to be in for this game.
		// tileNumber: The unique number designation given to a specific, physical tile.
		function insertBoardTile($tilePosition, $tileNumberToUse, $useSideA, $degreeRotation)
		{

				$tileX = $this->getTileX($tilePosition); // get the coordinates on the board where this tile will start
				$tileY = $this->getTileY($tilePosition); // get the coordinates on the board where this tile will start
				$sqlBoardTile = "INSERT INTO tile (tile_number,tile_x,tile_y,tile_use_side_A,tile_degree_rotation) VALUES ";
				$sqlBoardTile .= "(".$tileNumberToUse.",".$tileX.",".$tileY.",".$useSideA.",".$degreeRotation.") ";

				self::DbQuery( $sqlBoardTile );
		}

		function clearBoardSpaceValues()
		{
				$sql = "INSERT INTO board (board_x,board_y,board_space_type) VALUES ";

				$sql_values = array();
				for( $x=0; $x<14; $x++ )
				{
						for( $y=0; $y<10; $y++ )
						{
								$sql_values[] = "('$x','$y','D')";
						}
				}
				$sql .= implode( $sql_values, ',' );

				self::DbQuery( $sql );
		}

		// tilePosition: The position this tile happens to be in for this game.
		// thisTile (tileNumber): The unique number designation given to a specific, physical tile.
		function initializeBoard($numberOfPlayers)
		{
				$this->clearBoardSpaceValues();
				$possibleTiles = range(1,6); // array from 1-6
				shuffle($possibleTiles); // randomly order the tiles

				$numberOfTilesToUse = $this->getNumberOfTilesToUse($numberOfPlayers);

				for ($tilePosition = 1; $tilePosition <= $numberOfTilesToUse; $tilePosition++)
				{
						$thisTile = $possibleTiles[$tilePosition];
						$useSideA = rand(0,1);
						$degreeRotation = rand(0,3);

						// insert into tiles table
						$this->insertBoardTile($tilePosition, $thisTile, $useSideA, $degreeRotation);
						$this->setBoardTile($thisTile, $useSideA, $degreeRotation); // use what's in the tile table to set the board for this particular tile
				}
		}

		// tileNumber: The unique number designation given to a specific, physical tile.
		function setBoardTile($tileNumber, $useSideA, $degreeRotation)
		{
				$tileSpaceValues = array(array());
				switch($tileNumber)
				{
						case 1:
							if($useSideA == 1)
							{ // USE SIDE A
								$tileSpaceValues = array(
									array("B","B","B","C"),
									array("B","B","O","B"),
									array("B","B","B","S"),
									array("S","B","B","B")
								);
							}
							else
							{ // USE SIDE B
								$tileSpaceValues = array(
									array("C","B","B","S"),
									array("B","B","B","B"),
									array("B","B","O","B"),
									array("S","B","B","B")
								);
							}
						break;
						case 2:
							if($useSideA == 1)
							{ // USE SIDE A
								$tileSpaceValues = array(
									array("B","B","S","C"),
									array("O","B","B","B"),
									array("B","B","B","B"),
									array("B","S","B","B")
								);
							}
							else
							{ // USE SIDE B
								$tileSpaceValues = array(
									array("B","B","S","B"),
									array("B","B","B","O"),
									array("S","B","B","B"),
									array("C","B","B","B")
								);
							}
						break;
						case 3:
							if($useSideA == 1)
							{ // USE SIDE A
								$tileSpaceValues = array(
									array("S","B","B","B"),
									array("B","B","O","B"),
									array("B","B","B","B"),
									array("B","S","B","C")
								);
							}
							else
							{ // USE SIDE B
								$tileSpaceValues = array(
									array("B","S","B","C"),
									array("B","B","B","B"),
									array("O","B","B","B"),
									array("B","B","S","B")
								);
							}
						break;
						case 4:
							if($useSideA == 1)
							{ // USE SIDE A
								$tileSpaceValues = array(
									array("B","B","B","B"),
									array("B","C","B","S"),
									array("S","B","O","B"),
									array("B","B","B","B")
								);
							}
							else
							{ // USE SIDE B
								$tileSpaceValues = array(
									array("B","B","B","B"),
									array("B","B","S","B"),
									array("S","B","B","C"),
									array("B","O","B","B")
								);
							}
						break;
						case 5:
							if($useSideA == 1)
							{ // USE SIDE A
								$tileSpaceValues = array(
									array("S","B","B","B"),
									array("B","B","O","B"),
									array("B","C","B","S"),
									array("B","B","B","B")
								);
							}
							else
							{ // USE SIDE B
								$tileSpaceValues = array(
									array("S","C","B","B"),
									array("B","B","O","B"),
									array("B","B","B","B"),
									array("B","S","B","B")
								);
							}
						break;
						case 6:
							if($useSideA == 1)
							{ // USE SIDE A
								$tileSpaceValues = array(
									array("B","B","O","B"),
									array("B","B","B","S"),
									array("S","C","B","B"),
									array("B","B","B","B")
								);
							}
							else
							{ // USE SIDE B
								$tileSpaceValues = array(
									array("B","B","B","B"),
									array("B","O","B","B"),
									array("B","B","C","S"),
									array("S","B","B","B")
								);
							}
						break;
				}


				for ($rotations = 0; $rotations < $degreeRotation; $rotations++)
				{ // the number of times we want to rotate this tile

						$tileSpaceValues = $this->rotateMatrix90($tileSpaceValues);
				}

				$this->setBoardSpaceValues($tileNumber, $tileSpaceValues);
		}

		function setBoardSpaceValues($tileNumber, $tileSpaceValues)
		{
				$startingX = $this->getTileXFromTileNumber($tileNumber);
				$startingY = $this->getTileYFromTileNumber($tileNumber);

				for( $x=0; $x<4; $x++ )
				{ // go through 4 columns

						for( $y=0; $y<4; $y++ )
						{ // go through 4 rows

								$disc_value = $tileSpaceValues[$y][$x];
								$boardX = $x+$startingX;
								$boardY = $y+$startingY;
								$sql = "UPDATE board SET board_space_type='$disc_value' WHERE board_x='$boardX' AND board_y='$boardY'";
								self::DbQuery( $sql );
						}
				}

		}

		function updateTileRotations($tileNumber, $rotations)
		{
				$sql = "UPDATE tile SET tile_degree_rotation=$rotations WHERE tile_number=$tileNumber";
				self::DbQuery( $sql );
		}

		function setTrapCardTarget($playerUsing, $ostrichTargeted)
		{
				$sql = "UPDATE trapCards SET card_location='$ostrichTargeted' WHERE card_location_arg=$playerUsing AND card_location='hand'";
				self::DbQuery( $sql );
		}

		// Set the PLAYER turn order.
		function setIndividualTurnOrder($player, $turnOrder)
		{
				$sql = "UPDATE player SET player_custom_turn_order=$turnOrder WHERE player_id=$player";
				self::DbQuery( $sql );
		}

		function setGarmentLocation($garmentId, $newLocation)
		{
				$sql = "UPDATE garment SET garment_location='$newLocation' WHERE garment_id=$garmentId";
				self::DbQuery( $sql );
		}

		function getNumberOfPlayers()
		{
				$numberOfPlayers = 0;

				$players = self::getObjectListFromDB( "SELECT player_id
																											 FROM player
																											 WHERE 1" );

				foreach( $players as $player )
				{ // go through each player who needs to replace a garment
						$numberOfPlayers++;
				}

				return $numberOfPlayers;
		}

		function setOstrichToNotStealable($ostrich)
		{
				$sqlUpdate = "UPDATE ostrich SET ";
				$sqlUpdate .= "ostrich_steal_garment_order=0 ";
				$sqlUpdate .= "WHERE ostrich_color='$ostrich' ";

				self::DbQuery( $sqlUpdate );
		}

		function resetTrapCardValues($trapCardId)
		{
				$sqlUpdate = "UPDATE trapCards SET ";
				$sqlUpdate .= "card_location_arg=0 ";
				$sqlUpdate .= "WHERE card_id=$trapCardId";

				self::DbQuery( $sqlUpdate );
		}

		function resetOstrichZigToDefault($ostrich)
		{
				$this->saveOstrichZigDistance($ostrich, 20);
				$this->saveOstrichZigDirection($ostrich, "");

				$sqlUpdate = "UPDATE movementCards SET ";
				$sqlUpdate .= "card_ostrich='',card_location='hand' ";
				$sqlUpdate .= "WHERE card_ostrich='$ostrich'";

				self::DbQuery( $sqlUpdate );
		}

		// This is usually called before a player moves, before they choose X, and before traps are executed to
		// reset their distances and directions in case they were pushed during the last player's movement turn.
		function resetOstrichDistancesAndDirectionsToZigs()
		{
			$ostriches = self::getObjectListFromDB( "SELECT ostrich_color, ostrich_zig_direction, ostrich_zig_distance
																										 FROM ostrich
																										 WHERE 1" );

			foreach( $ostriches as $ostrich )
			{ // go through each ostrich

					// set their distance and direction to our zig values
					$this->saveOstrichDistance($ostrich['ostrich_color'], $ostrich['ostrich_zig_distance']);
					$this->saveOstrichDirection($ostrich['ostrich_color'], $ostrich['ostrich_zig_direction']);
			}
		}

		function resetOstrichSpawnOrderForAll()
		{
			$sqlUpdate = "UPDATE ostrich SET ";
			$sqlUpdate .= "ostrich_cliff_respawn_order=0, ostrich_causing_cliff_fall='', ostrich_steal_garment_order=0";

			self::DbQuery( $sqlUpdate );
		}

		function resetOstrichSpawnOrderForOstrich($ostrich)
		{
				$sqlUpdate = "UPDATE ostrich SET ";
				$sqlUpdate .= "ostrich_cliff_respawn_order=0, ostrich_causing_cliff_fall='', ostrich_steal_garment_order=0 ";
				$sqlUpdate .= "WHERE ostrich_color='$ostrich' ";

				self::DbQuery( $sqlUpdate );
		}

		function resetCliffPusher($ostrichToReset)
		{
			$sqlUpdate = "UPDATE ostrich SET ";
			$sqlUpdate .= "ostrich_causing_cliff_fall='' ";
			$sqlUpdate .= "WHERE ostrich_color='$ostrichToReset' ";

			self::DbQuery( $sqlUpdate );
		}

		function resetTurnOrder($clockwise)
		{
				$startingOstrichPlayerId = $this->getStartPlayer(); // get the owner of the ostrich with the crown
				//echo "resetTurnOrder clockwise $clockwise with startingOstirchPlayerId $startingOstrichPlayerId <br>";

				$this->setIndividualTurnOrder($startingOstrichPlayerId, 1); // set the player with the crown to #1 in turn order

				$currentPlayer = $startingOstrichPlayerId; // this will get updated each loop iteration
				for($i = 2; $i <= $this->getNumberOfPlayers(); $i++)
				{ // loop through one less than the number of players since we already know who's going first

						if($clockwise == "clockwise")
						{ // CLOCKWISE
								$currentPlayer = $this->getPlayerAfter( $currentPlayer ); // go one clockwise in natural turn order
								$this->setIndividualTurnOrder($currentPlayer, $i);
						}
						else
						{ // COUNTER-CLOCKWISE
								$currentPlayer = $this->getPlayerBefore( $currentPlayer ); // go one counter-clockwise in natural turn order
								$this->setIndividualTurnOrder($currentPlayer, $i);
						}
				}
		}

		function getGarmentsOstrichHasOfType($ostrich, $garmentTypeAsInt)
		{
				return self::getObjectListFromDB( "SELECT garment_id
																											 FROM garment
																											 WHERE garment_location='$ostrich' AND garment_type=$garmentTypeAsInt" );

		}

		function getGarmentsInPile()
		{
				return self::getObjectListFromDB( "SELECT garment_id, garment_x, garment_y, garment_location, garment_color, garment_type
																					 FROM garment
																					 WHERE garment_location='pile'" );
		}

		function giveGarmentToOstrich($garmentId, $ostrich)
		{
				$garmentX = self::getUniqueValueFromDb("SELECT garment_x FROM garment WHERE garment_id=$garmentId");
				$garmentY = self::getUniqueValueFromDb("SELECT garment_y FROM garment WHERE garment_id=$garmentId");

				// update the database to give the garment to the ostrich
				$sql = "UPDATE garment SET garment_location='$ostrich',garment_x=0,garment_y=0 WHERE garment_id=$garmentId";
				self::DbQuery( $sql );

				$garmentColor = self::getUniqueValueFromDb("SELECT garment_color FROM garment WHERE garment_id=$garmentId");
				$garmentType = self::getUniqueValueFromDb("SELECT garment_type FROM garment WHERE garment_id=$garmentId");
				$acquiringPlayer = self::getUniqueValueFromDb("SELECT ostrich_owner FROM ostrich WHERE ostrich_color='$ostrich'");

				$garmentTypeString = $this->convertGarmentTypeIntToString($garmentType);

				// see if we are wearing this or putting it in our backpack
				$garmentsOfTypeThisOstrichHas = $this->getGarmentsOstrichHasOfType($ostrich, $garmentType);
				$wearingOrBackpack = "wearing";
				$garmentsOfThisType = count($garmentsOfTypeThisOstrichHas);
				if($garmentsOfThisType > 1)
				{
						$wearingOrBackpack = "backpack";
				}

				// notify players that this garment has been acquired
				self::notifyAllPlayers( "acquireGarment", clienttranslate( '${acquiringOstrichOwnerName} ${ostrichName} ostrich got the ${garmentColorFriendly} ${garmentType} garment.' ), array(
						'acquiringOstrich' => $ostrich,
						'garmentColor' => $garmentColor,
					  'garmentType' => $garmentTypeString,
						'acquiringPlayer' => $acquiringPlayer,
						'wearingOrBackpack' => $wearingOrBackpack,
						'garmentX' => $garmentX,
						'garmentY' => $garmentY,
						'numberOfThisType' => $garmentsOfThisType,
						'ostrichName' => $this->getOstrichName($ostrich),
						'garmentColorFriendly' => $this->getOstrichName($garmentColor),
						'acquiringOstrichOwnerName' => self::getPlayerNameById($acquiringPlayer)
				) );
		}

		function moveGarmentToPile($garmentId)
		{
			// update the database to return it to the pile
			$sql = "UPDATE garment SET garment_x=0,garment_y=0,garment_location='pile' WHERE garment_id=$garmentId";
			self::DbQuery( $sql );

			$garmentColor = self::getUniqueValueFromDb("SELECT garment_color FROM garment WHERE garment_id=$garmentId");
			$garmentType = self::getUniqueValueFromDb("SELECT garment_type FROM garment WHERE garment_id=$garmentId");

			$garmentTypeString = $this->convertGarmentTypeIntToString($garmentType);

			// notify players that this garment has been acquired
			self::notifyAllPlayers( "garmentDiscarded", clienttranslate( '${player_name} is discarding the ${garmentColorText} ${garmentType} garment.' ), array(
					'garmentColor' => $garmentColor,
					'garmentType' => $garmentTypeString,
					'player_name' => self::getActivePlayerName(),
					'garmentColorText' => $this->getOstrichName($garmentColor)
			) );

				$this->setState_TrapAndCliffAndGarmentCleanup();
		}

		function moveGarmentToBoard($garmentId, $xDestination, $yDestination)
		{
				// update the database to give the garment to the ostrich
				$sql = "UPDATE garment SET garment_x=$xDestination,garment_y=$yDestination,garment_location='board' WHERE garment_id=$garmentId";
				self::DbQuery( $sql );

				$garmentColor = self::getUniqueValueFromDb("SELECT garment_color FROM garment WHERE garment_id=$garmentId");
				$garmentType = self::getUniqueValueFromDb("SELECT garment_type FROM garment WHERE garment_id=$garmentId");

				$garmentTypeString = $this->convertGarmentTypeIntToString($garmentType);

				// notify players that this garment has been acquired
				self::notifyAllPlayers( "replacementGarmentSpaceChosen", clienttranslate( 'A garment is being replaced.' ), array(
						'garmentColor' => $garmentColor,
					  'garmentType' => $garmentTypeString,
						'xDestination' => $xDestination,
						'yDestination' => $yDestination
				) );

				if($this->countGarmentReplacementQueue() > 0)
				{ // another garment must be replaced

					$this->gamestate->nextState( "askToReplaceGarment" );
				}
				else
				{ // no more garments need to be chosen
						$this->endMovementTurn();
				}

		}

		function isOstrichOffCliff($ostrich)
		{
				$ostriches = self::getObjectListFromDB( "SELECT ostrich_color, ostrich_owner, ostrich_x, ostrich_y
																											 FROM ostrich
																											 WHERE ostrich_color='$ostrich'" );

				foreach( $ostriches as $ostrich )
				{ // go through each ostrich (should only be 1)

					$player = $ostrich['ostrich_owner'];
					$x = $ostrich['ostrich_x'];
					$y = $ostrich['ostrich_y'];

					if($this->getBoardSpaceType($x, $y) == "D")
					{ // this ostrich is off a cliff
							return true;
					}
				}

				return false;
		}

		function isOstrichDizzy($ostrich)
		{
				return self::getUniqueValueFromDb("SELECT ostrich_is_dizzy FROM ostrich WHERE ostrich_color='$ostrich'");;
		}

		function areAnyOstrichesOffCliffs()
		{
				$ostriches = self::getObjectListFromDB( "SELECT ostrich_color, ostrich_owner, ostrich_x, ostrich_y
																											 FROM ostrich
																											 WHERE 1" );

				foreach( $ostriches as $ostrich )
				{ // go through each ostrich

					$player = $ostrich['ostrich_owner'];
					$x = $ostrich['ostrich_x'];
					$y = $ostrich['ostrich_y'];

					if($this->getBoardSpaceType($x, $y) == "D")
					{ // this ostrich is off a cliff
							return true;
					}
				}

				return false;
		}

		function getNextOstrichToRespawn()
		{
				$orderForNextOstrichRespawn = self::getUniqueValueFromDb("SELECT min(ostrich_cliff_respawn_order) FROM ostrich WHERE ostrich_cliff_respawn_order<>0");
				if($orderForNextOstrichRespawn == 0)
				{ // there are no ostriches needing respawning

							return ""; // this should never be called, but if so, return
				}

				$ostrichesGoingNext = self::getObjectListFromDB( "SELECT ostrich_color, ostrich_x, ostrich_y, ostrich_causing_cliff_fall
																										 FROM ostrich
																										 WHERE ostrich_cliff_respawn_order=$orderForNextOstrichRespawn" );

				foreach( $ostrichesGoingNext as $ostrichGoingNext )
				{
						return $ostrichGoingNext;
				}

				return "";
		}

		function getNextTrapPlayedOnOstrich($ostrich)
		{
				$trapsPlayedOnThisOstrich = self::getObjectListFromDB("SELECT card_id FROM trapCards WHERE card_location='$ostrich'");

				foreach( $trapsPlayedOnThisOstrich as $trapPlayed )
				{
						return $trapPlayed['card_id']; // just return the first one
				}

				return 0; // if we didn't find any
		}

		function respawnAnOstrich()
		{
				//echo "Respawning an ostrich.";

				// figure out which ostrich was first to be knocked off
				$ostrichToRespawn = $this->getNextOstrichToRespawn();

				if($ostrichToRespawn == "")
				{
					throw new feException( "Could not find the next ostrich to respawn");
					return;
				}

				// figure out the deets about this ostrich
				$ostrichColor = $ostrichToRespawn['ostrich_color'];
				$ostrichX = $ostrichToRespawn['ostrich_x'];
				$ostrichY = $ostrichToRespawn['ostrich_y'];



				// NOTE: We decided to randomize the list of closest crates and then taking the first one instead of letting the player choose, like in the physical version.
				$closestCrates = $this->getClosestEmptyCrates($ostrichX, $ostrichY); // find the closest crates
				shuffle($closestCrates); // randomize in case of ties
				//echo "there are ".count($closestCrates)." crates and the first one has x ".$closestCrates[0]['board_x']." and y ".$closestCrates[0]['board_y'];

				$this->saveOstrichLocation( $ostrichColor, $closestCrates[0]['board_x'], $closestCrates[0]['board_y'] ); // move the ostrich to its new location
				$this->resetOstrichSpawnOrderForOstrich($ostrichColor); // take them out of the queue for ostrich respawning

				// notify the players that the ostrich has respawned so they know where it is
				$ostrichTakingTurn = $this->getOstrichWhoseTurnItIs();
				$boardValue = $this->getBoardSpaceType($closestCrates[0]['board_x'], $closestCrates[0]['board_y']);
				$ostrichOwner = $this->getOwnerIdOfOstrich($ostrichColor);
				self::notifyAllPlayers( "moveOstrich", clienttranslate( '${player_name} pulled the ${ostrichName} ostrich back up and onto a crate.' ), array(
									'color' => $ostrichColor,
									'ostrichTakingTurn' => $ostrichTakingTurn,
									'x' => $closestCrates[0]['board_x'],
								  'y' => $closestCrates[0]['board_y'],
									'spaceType' => $boardValue,
									'ostrichMovingHasZag' => false,
									'player_name' => self::getActivePlayerName(),
									'ostrichName' => $this->getOstrichName($ostrichColor)
				) );


		}

		function triggerTrap()
		{
				$trapCardToExecute = $this->getTrapCardToExecute(); // OBJECT of the card with multiple keys/values
				$trapCardOwner = $this->getOwnerOfTrapCard($trapCardToExecute['uniqueCardId']); // the player who played the trap card
				$trapCardOwnerName = self::getPlayerNameById($trapCardOwner);

				$trappedOstrich = $this->getOstrichWhoseTurnItIs();	// find the ostrich whose turn it is
				$trappedOstrichName = $this->getOstrichName($trappedOstrich); // get the name of the trapped ostrich
				$ownerOfTrappedOstrich = $this->getOwnerIdOfOstrich($trappedOstrich); // the player who owns the ostrich being trapped
				switch($trapCardToExecute['trapName'])
				{
						case "Blastorocket":

								$currentDistance = $this->getOstrichDistance($trappedOstrich);
								$newDistance = $currentDistance * 2; // double the distance
								$this->saveOstrichDistance($trappedOstrich, $newDistance); // save the new distance to where our move is stored
								$this->saveOstrichZigDistance($trappedOstrich, $newDistance); // save the new distance to where our zig values are stored

								$currentXValue = $this->getOstrichXValue($trappedOstrich);
								$newXValue = $currentXValue * 2; // double the X value in case they used an X
								$this->saveOstrichXValue($trappedOstrich, $newXValue); // save the new X value

								// set the old and new values for the messaging to players
								$oldZigValue = $currentDistance;
								$newZigValue = $newDistance;
								if($currentXValue != 0)
								{	// this ostrich played an X
										$oldZigValue = $currentXValue;
										$newZigValue = $newXValue;
								}

								// notify players of what changed
								self::notifyAllPlayers( "executeTrapDescription", clienttranslate( '${player_name} doubled the ${ostrichName} ostrich Zig from a ${oldZigValue} to a ${newZigValue}!' ), array(
													'player_name' => $trapCardOwnerName,
													'ostrichName' => $trappedOstrichName,
													'oldZigValue' => $oldZigValue,
													'newZigValue' => $newZigValue
								) );

						break;

						case "Boulderdash":

								$oldDirection = $this->getOstrichDirection($trappedOstrich);
								$newDirection = $this->getRotatedDirection($oldDirection, 90, false); // rotate it 90 degrees clockwise
								$this->saveOstrichDirection($trappedOstrich, $newDirection); // save the new direction to where we will move them
								$this->saveOstrichZigDirection($trappedOstrich, $newDirection); // save the new direction of their zig

								// notify players of what changed
								self::notifyAllPlayers( "executeTrapRotateZig", clienttranslate( '${player_name} rotated the ${trappedOstrichName} ostrich Zig from ${oldDirection} to ${newDirectionValue}!' ), array(
									'newDirectionValue' => $newDirection,
									'oldDirection' => $oldDirection,
									'playerTrapped' => $ownerOfTrappedOstrich,
									'trappedOstrichName' => self::getPlayerNameById($trapCardOwner),
									'player_name' => $trapCardOwnerName
								) );

						break;

						case "Deface Paint":

								$oldDirection = $this->getOstrichDirection($trappedOstrich);
								$newDirection = $this->getRotatedDirection($oldDirection, 90, true); // rotate it 90 degrees clockwise
								$this->saveOstrichDirection($trappedOstrich, $newDirection); // save the new direction to what we use for moving
								$this->saveOstrichZigDirection($trappedOstrich, $newDirection); // save the new direction to where their zig is stored

								// notify players of what changed
								self::notifyAllPlayers( "executeTrapRotateZig", clienttranslate( '${player_name} rotated the ${trappedOstrichName} ostrich Zig from ${oldDirection} to ${newDirectionValue}!' ), array(
									'newDirectionValue' => $newDirection,
									'playerTrapped' => $ownerOfTrappedOstrich,
									'oldDirection' => $oldDirection,
									'trappedOstrichName' => self::getPlayerNameById($trapCardOwner),
									'player_name' => $trapCardOwnerName
								) );

						break;

						case "Krazy Crane":

								// find the board tile the victim ostrich is on
								$tilePosition = $this->getTilePositionOfOstrich($trappedOstrich); // get which tile they are on
								$tileNumber = $this->getTileNumber($tilePosition); // find the number of that tile

								$sideOfTile = $this->getTileSide($tilePosition); // get whether this tile is on side A or B

								// convert this to an integer 1 or 0
								$useSideA = 1;
								if($sideOfTile == "B")
								{ // this tile is on side B
										$useSideA = 0;
								}

								$oldDegreeRotation = $this->getTileRotation($tilePosition); // get current rotation
								$degreeRotation = 0; // this will be set to the new degree rotation
								// increase the degree rotation by 1
								if($oldDegreeRotation == 3)
								{
										$degreeRotation = 0;
								}
								else
								{
										$degreeRotation = $oldDegreeRotation + 1;
								}

								// rotate that tile
								//echo "setting board tile number $tileNumber at position $tilePosition with useSideA of $useSideA and degree rotation $degreeRotation";
								$this->setBoardTile($tileNumber, $useSideA, $degreeRotation); // update the board table with each new space value
								$this->updateTileRotations($tileNumber, $degreeRotation); // also update the tile table with the new degree rotation


								$this->rotateOstriches($tileNumber, true); // rotate any ostriches on it clockwise
								$this->rotateGarments($tileNumber, true); // rotate any garments on it clockwise

								// notify players of what changed
								self::notifyAllPlayers( "executeTrapRotateTile", clienttranslate( '${player_name} turned the tile ${ostrichName} is on 90 degrees clockwise!' ), array(
													'player_name' => $trapCardOwnerName,
													'ostrichName' => $trappedOstrichName,
													'tileNumber' => $tileNumber,
													'oldDegreeRotation' => $oldDegreeRotation,
													'newDegreeRotation' => $degreeRotation,
													'tileSide' => $sideOfTile,
													'tilePosition' => $tilePosition
								) );

						break;

						case "Rooster Booster":

								$currentDistance = $this->getOstrichDistance($trappedOstrich);
								$newDistance = 5; // make their distance 5
								$this->saveOstrichDistance($trappedOstrich, $newDistance); // save the new distance to where our movement is stored
								$this->saveOstrichZigDistance($trappedOstrich, $newDistance); // save the new distance to where our zig values are stored

								$currentXValue = $this->getOstrichXValue($trappedOstrich);
								$newXValue = 5; // make their distance 5
								$this->saveOstrichXValue($trappedOstrich, $newXValue); // save the new X value

								// set the old and new values for the messaging to players
								$oldZigValue = $currentDistance;
								$newZigValue = $newDistance;
								if($currentXValue != 0)
								{	// this ostrich played an X
										$oldZigValue = $currentXValue;
										$newZigValue = $newXValue;
								}

								// notify players of what changed
								self::notifyAllPlayers( "executeTrapDescription", clienttranslate( '${player_name} set the ${trappedOstrichName} ostrich Zig from a ${oldZigValue} to a ${newZigValue}!' ), array(
													'player_name' => $trapCardOwnerName,
													'ostrichName' => $trappedOstrichName,
													'oldZigValue' => $oldZigValue,
													'newZigValue' => $newZigValue,
													'trappedOstrichName' => $trappedOstrichName
								) );

						break;

						case "Twirlybird":

								// find the board tile the victim ostrich is on
								$tilePosition = $this->getTilePositionOfOstrich($trappedOstrich); // get which tile they are on
								$tileNumber = $this->getTileNumber($tilePosition); // find the number of that tile

								$sideOfTile = $this->getTileSide($tilePosition); // get whether this tile is on side A or B

								// convert this to an integer 1 or 0
								$useSideA = 1;
								if($sideOfTile == "B")
								{ // this tile is on side B
										$useSideA = 0;
								}

								$oldDegreeRotation = $this->getTileRotation($tilePosition); // get current rotation
								$degreeRotation = 0; // this will be set to the new degree rotation
								// increase the degree rotation by 1 COUNTER-CLOCKWISE
								if($oldDegreeRotation == 0)
								{
										$degreeRotation = 3;
								}
								else
								{
										$degreeRotation = $oldDegreeRotation - 1;
								}

								// rotate that tile
								//echo "setting board tile number $tileNumber at position $tilePosition with useSideA of $useSideA and degree rotation $degreeRotation";
								$this->setBoardTile($tileNumber, $useSideA, $degreeRotation); // update the board table with each new space value
								$this->updateTileRotations($tileNumber, $degreeRotation); // also update the tile table with the new degree rotation


								$this->rotateOstriches($tileNumber, false); // rotate any ostriches on it clockwise
								$this->rotateGarments($tileNumber, false); // rotate any garments on it clockwise

								// notify players of what changed
								self::notifyAllPlayers( "executeTrapRotateTile", clienttranslate( '${player_name} turned the tile ${ostrichName} is on 90 degrees clockwise!' ), array(
													'player_name' => $trapCardOwnerName,
													'ostrichName' => $trappedOstrichName,
													'tileNumber' => $tileNumber,
													'oldDegreeRotation' => $oldDegreeRotation,
													'newDegreeRotation' => $degreeRotation,
													'tileSide' => $sideOfTile,
													'tilePosition' => $tilePosition
								) );

						break;

						default:
								$oldDirection = $this->getOstrichDirection($trappedOstrich);
								$newDirection = $this->getRotatedDirection($oldDirection, 90, false); // rotate it 90 degrees clockwise
								$this->saveOstrichDirection($trappedOstrich, $newDirection); // save the new direction to where we check for moving
								$this->saveOstrichZigDirection($trappedOstrich, $newDirection); // save the new direction to where we keep our zig values

								// notify players of what changed
								$trapCardOwnerName = self::getPlayerNameById($trapCardOwner);
								self::notifyAllPlayers( "executeTrapRotateZig", clienttranslate( '${player_name} rotated the ${trappedOstrichName} ostrich Zig from ${oldDirection} to ${newDirectionValue}!' ), array(
									'newDirectionValue' => $newDirection,
									'playerTrapped' => $ownerOfTrappedOstrich,
									'player_name' => $trapCardOwnerName,
									'trappedOstrichName' => $trappedOstrichName,
									'oldDirection' => $oldDirection
								) );
						break;
				}

				$this->discardTrapCard($trapCardToExecute['uniqueCardId'], false); // discard the trap card (and reset any other fields needed)
		}

		// I could not get the PHP queue to persist data so I'm using a database table and
		// writing my own queue...
		function popReplacementQueue()
		{
				// get all the items in our database table queue
				$playersWhoNeedToReplace = self::getObjectListFromDB( "SELECT order_id, player, ostrich_color
																											 FROM garmentReplacementQueue
																											 WHERE 1" );

				$playerToPop = null;
				$orderIdToPop = 1000;
				foreach( $playersWhoNeedToReplace as $playerInQueue )
				{ // go through each player who needs to replace a garment

						$orderId = $playerInQueue['order_id']; // get the priority order for this item
						if($orderId < $orderIdToPop)
						{ // this item has the lowest priority number

								$playerToPop = $playerInQueue['player'];
								$orderIdToPop = $orderId;
						}
        }

				// delete it from the queue
				$sql = "DELETE FROM garmentReplacementQueue WHERE order_id=$orderIdToPop";
				self::DbQuery( $sql );

				return $playerToPop;
		}

		function peekGarmentReplacementQueue()
		{
				// get all the items in our database table queue
				$playersWhoNeedToReplace = self::getObjectListFromDB( "SELECT order_id, player, ostrich_color
																											 FROM garmentReplacementQueue
																											 WHERE 1" );

				$playerToPop = null;
				$orderIdToPop = 1000;
				foreach( $playersWhoNeedToReplace as $playerInQueue )
				{ // go through each player who needs to replace a garment

						$orderId = $playerInQueue['order_id']; // get the priority order for this item
						if($orderId < $orderIdToPop)
						{ // this item has the lowest priority number

								$playerToPop = $playerInQueue['player'];
								$orderIdToPop = $orderId;
						}
				}

				return $playerToPop;
		}

		function countGarmentReplacementQueue()
		{
				$countOfQueue = 0;
				$playersWhoNeedToReplace = self::getObjectListFromDB( "SELECT order_id, player, ostrich_color
																											 FROM garmentReplacementQueue
																											 WHERE 1" );
				foreach( $playersWhoNeedToReplace as $playerInQueue )
				{ // go through each player who needs to replace a garment
						$countOfQueue++;
				}

				return $countOfQueue;
		}

		function countTotalAcquiredGarments()
		{
				return self::getUniqueValueFromDb("SELECT COUNT(garment_id) FROM garment WHERE (garment_location<>'pile' AND garment_location<>'pile' AND garment_location<>'chosen')");
		}

		function countUniqueGarmentsForPlayer($player_id)
		{
				$numberUniqueGarments = 0;

						$ostrichSql = "SELECT ostrich_color ";
						$ostrichSql .= "FROM ostrich ";
						$ostrichSql .= "WHERE ostrich_owner=$player_id";
						$dbresOstrich = self::DbQuery( $ostrichSql );
						while( $ostrich = mysql_fetch_assoc( $dbresOstrich ) )
						{ // go through each ostrich of this player

								$ostrichColor = $ostrich['ostrich_color']; // save which color ostrich this is

								// set the garment types to false
								$hasHeadGarment = false;
								$hasBodyGarment = false;
								$hasLegsGarment = false;
								$hasFeetGarment = false;

								$garmentSql = "SELECT garment_type ";
								$garmentSql .= "FROM garment ";
								$garmentSql .= "WHERE garment_location='$ostrichColor'";
								$dbresGarment = self::DbQuery( $garmentSql );
								while( $ostrich = mysql_fetch_assoc( $dbresGarment ) )
								{ // go through each garment this ostrich has
										$garmentType = $ostrich['garment_type'];

										switch($garmentType)
										{
												case 0:
														$hasHeadGarment = true;
												break;
												case 1:
														$hasBodyGarment = true;
												break;
												case 2:
														$hasLegsGarment = true;
												break;
												case 3:
														$hasFeetGarment = true;
												break;
										}
								}

								if($hasHeadGarment)
								{
										$numberUniqueGarments++;
								}

								if($hasBodyGarment)
								{
										$numberUniqueGarments++;
								}

								if($hasLegsGarment)
								{
										$numberUniqueGarments++;
								}

								if($hasFeetGarment)
								{
										$numberUniqueGarments++;
								}

						}

						return $numberUniqueGarments;
		}

		function getTrapsDrawnThisRound($playerid)
		{
				return self::getUniqueValueFromDb("SELECT player_traps_drawn_this_round FROM player WHERE player_id=$playerid");
		}

		function getTrapCardToExecute()
		{
				$ostrichWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$trapCardsPlayedOnOstrich = self::getObjectListFromDB( "SELECT card_location ostrichPlayedOn, card_location_arg playerWhoPlayedTrap, card_id uniqueCardId, card_type_arg typeOfTrap, card_type trapName
																											 FROM trapCards
																											 WHERE card_location='$ostrichWhoseTurnItIs'" );

			  foreach($trapCardsPlayedOnOstrich as $trapCard)
				{
						return $trapCard; // just return the first one
				}
		}

		function rotateGarments($tileNumberToRotate, $isClockwise)
		{

				$allGarmentsOnBoard = self::getObjectListFromDB( "SELECT garment_id, garment_color, garment_type
																							FROM garment WHERE garment_location='board'" );


				foreach( $allGarmentsOnBoard as $garment )
				{ // go through all the garments

					$garmentId = $garment['garment_id'];
					$garmentColor = $garment['garment_color'];
					$garmentType = $garment['garment_type'];

					$tilePositionOfGarment = $this->getTilePositionOfGarment($garmentId); // get which tile it is on
					$tileNumberOfGarment = $this->getTileNumber($tilePositionOfGarment); // find the number of that tile

					if($tileNumberOfGarment == $tileNumberToRotate)
					{	// we need to rotate this garment
							$xOffsetOfTile = $this->getTileXFromTileNumber($tileNumberToRotate) - 1;
							$yOffsetOfTile = $this->getTileYFromTileNumber($tileNumberToRotate) - 1;
							$currentGarmentX = $this->getGarmentXLocation($garmentId);
							$currentGarmentY = $this->getGarmentYLocation($garmentId);
							$newGarmentX = 0;
							$newGarmentY = 0;

							if($isClockwise)
							{
									$newGarmentX = (($xOffsetOfTile+$yOffsetOfTile+4)-$currentGarmentY)+1; //newX=(xOffset+yOffset+4)-oldY+1
									$newGarmentY = $currentGarmentX+($yOffsetOfTile-$xOffsetOfTile); //newY=oldX+(yOffset-xOffset)
							}
							else
							{
									$newGarmentX = $currentGarmentY+($xOffsetOfTile-$yOffsetOfTile); //newX=oldY+(xOffset-yOffset)
									$newGarmentY = (($xOffsetOfTile+$yOffsetOfTile+4)-$currentGarmentX)+1; //newY=(yOffset+xOffset+4)-oldX+1
							}

							$sql = "UPDATE garment SET garment_x=$newGarmentX,garment_y=$newGarmentY WHERE garment_id=$garmentId";
							self::DbQuery( $sql );

							$garmentTypeString = $this->convertGarmentTypeIntToString($garmentType);

							// notify players that this garment has been moved
							self::notifyAllPlayers( "moveGarmentToBoard", "", array(
									'garmentColor' => $garmentColor,
								  'garmentType' => $garmentTypeString,
									'xDestination' => $newGarmentX,
									'yDestination' => $newGarmentY
							) );
					}
				}
		}

		function rotateOstriches($tileNumberToRotate, $isClockwise)
		{
				$allOstriches = $this->getOstrichesInOrder(); // get all ostriches

				foreach($allOstriches as $ostrich)
				{
						$ostrichColor = $ostrich['color'];
						$tilePosition = $this->getTilePositionOfOstrich($ostrichColor); // get which tile they are on
						$tileNumber = $this->getTileNumber($tilePosition); // find the number of that tile

						if($tileNumber == $tileNumberToRotate)
						{	// we need to rotate this ostrich
								$xOffsetOfTile = $this->getTileXFromTileNumber($tileNumberToRotate) - 1;
								$yOffsetOfTile = $this->getTileYFromTileNumber($tileNumberToRotate) - 1;
								$currentOstrichX = $this->getOstrichXLocation($ostrichColor);
								$currentOstrichY = $this->getOstrichYLocation($ostrichColor);
								$newOstrichX = 0;
								$newOstrichY = 0;

								if($isClockwise)
								{
										$newOstrichX = (($xOffsetOfTile+$yOffsetOfTile+4)-$currentOstrichY)+1; //newX=(xOffset+yOffset+4)-oldY+1
										$newOstrichY = $currentOstrichX+($yOffsetOfTile-$xOffsetOfTile); //newY=oldX+(yOffset-xOffset)
								}
								else
								{
										$newOstrichX = $currentOstrichY+($xOffsetOfTile-$yOffsetOfTile); //newX=oldY+(xOffset-yOffset)
										$newOstrichY = (($xOffsetOfTile+$yOffsetOfTile+4)-$currentOstrichX)+1; //newY=(yOffset+xOffset+4)-oldX+1
								}

								$sql = "UPDATE ostrich SET ostrich_x=$newOstrichX,ostrich_y=$newOstrichY WHERE ostrich_color='$ostrichColor'";
								self::DbQuery( $sql );

								self::notifyAllPlayers( "moveOstrich", "", array(
										'color' => $ostrichColor,
										'ostrichTakingTurn' => $ostrichColor,
										'x' => $newOstrichX,
									  'y' => $newOstrichY,
										'spaceType' => "B",
										'ostrichMovingHasZag' => false,
										'ostrichMovingIsOffCliff' => false,
										'ostrichName' => $this->getOstrichName($ostrichColor)
								) );
						}
				}
		}

		function resetTrapsDrawnThisRound()
		{
				$sql = "UPDATE player SET player_traps_drawn_this_round=0";
				self::DbQuery( $sql );
		}

		// Make all ostriches not dizzy.
		function resetDizziness()
		{
			$sql = "UPDATE ostrich SET ostrich_is_dizzy=0";
			self::DbQuery( $sql );
		}

		function resetAllOstrichZigs()
		{
				$sql = "UPDATE ostrich SET ostrich_zig_distance=20, ostrich_zig_direction=''" ;
				self::DbQuery( $sql );
		}

		function resetOstrichChosen()
		{
				$sql = "UPDATE ostrich SET ostrich_is_chosen=0" ;
				self::DbQuery( $sql );
		}

		function resetXValueChoices()
		{
				$sql = "UPDATE ostrich SET ostrich_chosen_x_value=0" ;
				self::DbQuery( $sql );
		}

		function resetDiscardedZigs()
		{
				$sql = "UPDATE movementCards SET card_location_arg=0,card_ostrich='' WHERE card_location='discard'" ;
				self::DbQuery( $sql );
		}

		function getPlayersWithAnyTrapsInHand()
		{
			$playersWithTrapCards = array();
			$allPlayers = self::getObjectListFromDB( "SELECT player_id
																										 FROM player" );
			foreach( $allPlayers as $player )
			{ // go through each player who needs to replace a garment
					//echo "playerID is " + $player['player_id'];
					$trapCardsForThisPlayer = $this->countTrapCardsInPlayerHand($player['player_id']); // count the number of trap cards this player has

					if( $trapCardsForThisPlayer > 0 )
					{ // this player needs to discard at least one trap card

							array_push($playersWithTrapCards, $player['player_id']); // add this playerid to the array we will return
					}
			}

			return $playersWithTrapCards;
		}

		function getPlayersWhoCanClaimZag()
		{
				$playersWhoCanClaimAZag = array();
				$allPlayers = self::getObjectListFromDB( "SELECT player_id
																											 FROM player" );
				foreach( $allPlayers as $player )
				{ // go through each player
						$playerId = $player['player_id']; // get the player ID of this player

						$allPlayersOstrichesHaveZag = true;
						$allPlayersOstriches = $this->getAllPlayersOstriches($playerId);
						foreach( $allPlayersOstriches as $ostrich )
						{ // go through each ostrich owned by this player
								if(!$this->doesOstrichHaveZag($ostrich['ostrich_color']))
								{ // this one doesn't have a Zag
										$allPlayersOstrichesHaveZag = false; // so not ALL of this player's ostriches have a Zag
								}
						}

						if(!$allPlayersOstrichesHaveZag && $this->has3MatchingCards($player['player_id']))
						{ // player has an ostrich without a zag and has at least 3 matching cards
								array_push($playersWhoCanClaimAZag, $player['player_id']); // add this playerid to the array we will return
						}
				}

				return $playersWhoCanClaimAZag;
		}

		function doTheseCardsMatch($cards)
		{
				$distanceChecking = null;
				foreach( $cards as $card )
				{ // go through all the cards
						$distance = $this->getZigDistanceForZigCardId($card); // grab the player ID before we discard the card
						if($distance == 0)
						{ // this is an X so it will match with anything

						}
						else
						{ // not an X so let's do more checking
							if($distanceChecking == null)
							{ // this is the first non-X card we have found
									$distanceChecking = $distance; // save this as the distance we are trying to match
							}
							else
							{ // this is a subsequent card
									if($distanceChecking != $distance)
									{ // this is not an X and the distance doesn't match a previous distance
											return false;
									}
							}
						}

				}

				return true; // we haven't found a mismatch
		}

		// True if this player has at least 3 matching cards in hand, false otherwise.
		function has3MatchingCards($playerId)
		{
				$cards = $this->movementCards->getCardsInLocation( 'hand', $playerId );

				$onesInHand = 0;
				$twosInHand = 0;
				$threesInHand = 0;
				$xInHand = 0;
				foreach( $cards as $card )
        { // go through all the cards in this player's hand
						$distance = $card['type_arg']; // get the distance of this card

						if($distance == 0)
								$xInHand++;
						else if($distance == 1)
								$onesInHand++;
						else if($distance == 2)
								$twosInHand++;
						else if($distance == 3)
								$threesInHand++;
				}
				//echo "Ones: $onesInHand Twos: $twosInHand Threes: $threesInHand Xs: $xInHand";

				$countOnes = $onesInHand + $xInHand;
				$countTwos = $twosInHand + $xInHand;
				$countThrees = $threesInHand + $xInHand;
				//echo "Ones: $countOnes Twos: $countTwos Threes: $countThrees";
				if($countOnes > 2 || $countTwos > 2 || $countThrees > 2)
				{ // we have matching cards enough to claim a zag
						return true;
				}
				else
				{
						return false;
				}
		}

		function getPlayersWithMoreThan1TrapCard()
		{
				//echo "getPlayers trap!";
				$playersWithMoreThan1TrapCard = array();
				$allPlayers = self::getObjectListFromDB( "SELECT player_id
																											 FROM player" );
				foreach( $allPlayers as $player )
				{ // go through each player who needs to replace a garment
						//echo "playerID is " + $player['player_id'];
						$trapCardsForThisPlayer = $this->countTrapCardsInPlayerHand($player['player_id']); // count the number of trap cards this player has

						if( $trapCardsForThisPlayer > 1 )
						{ // this player needs to discard at least one trap card

								array_push($playersWithMoreThan1TrapCard, $player['player_id']); // add this playerid to the array we will return
						}
				}

				return $playersWithMoreThan1TrapCard;
		}

		function countTrapCardsInPlayerHand($player)
		{
				//echo "count trap cards for player ID " + $player;
				$count = 0;
				$trapCards = $this->trapCards->getCardsInLocation( 'hand', $player );

				foreach( $trapCards as $trapCard )
				{ // go through each trap card in the player's hand
						$count++;
				}

				return $count;
		}

		function addToGarmentReplacementQueue($playerToAdd, $ostrichToAdd)
		{
				$sqlGarment = "INSERT INTO garmentReplacementQueue (player, ostrich_color) VALUES ";
				$sqlGarment .= "(".$playerToAdd.",'".$ostrichToAdd."') ";
				self::DbQuery( $sqlGarment );
		}

		function rotateMatrix90( $matrix )
		{
		    $matrix = array_values( $matrix );
		    $matrix90 = array();

		    // make each new row = reversed old column
		    foreach( array_keys( $matrix[0] ) as $column ){
		        $matrix90[] = array_reverse( array_column( $matrix, $column ) );
		    }

		    return $matrix90;
		}

		function getPlayerIdForZigCardId($cardId)
		{
				return self::getUniqueValueFromDb("SELECT card_location_arg FROM movementCards WHERE card_id=$cardId");
		}

		function getZigDistanceForZigCardId($cardId)
		{
				return self::getUniqueValueFromDb("SELECT card_type_arg FROM movementCards WHERE card_id=$cardId");
		}

		function getZigDistanceForOstrich($ostrich)
		{
				return self::getUniqueValueFromDb("SELECT card_type_arg FROM movementCards WHERE card_ostrich='$ostrich' AND (card_location='played' OR card_location='zigChosen')");
		}

		function getCardIdOfPlayerChosenZig($player_id)
		{
				return self::getUniqueValueFromDb("SELECT card_id FROM movementCards WHERE card_location_arg=$player_id AND card_location='zigChosen'");
		}

		function getOstrichOfPlayerChosenZig($player_id)
		{
			 	return self::getUniqueValueFromDb("SELECT card_ostrich FROM movementCards WHERE card_location_arg=$player_id AND card_location='zigChosen'");
		}

		function getPlayerIdForTrapCardId($cardId)
		{
				return self::getUniqueValueFromDb("SELECT card_location_arg FROM trapCards WHERE card_id=$cardId");
		}

		function getAllPlayersOstriches($playerId)
		{
				return self::getObjectListFromDB( "SELECT ostrich_color
																										 FROM ostrich
																										 WHERE ostrich_owner=$playerId" );
		}

		// Same as getAllPlayersOstriches except in array form.
		function getMyOstriches($playerId)
		{
				$result = array();
				$ostrichesObjectForm = $this->getAllPlayersOstriches($playerId);
			  $ostrichIndex = 0;
				foreach($ostrichesObjectForm as $ostrich)
				{
						$result[$ostrichIndex] = $ostrich['ostrich_color'];
						//echo "myostrich ".$result[$ostrichIndex];

						$ostrichIndex++;
				}

				return $result;
		}

		function getOstrichesInOrder()
		{
		        $result = array();


		        $ostriches = self::getObjectListFromDB( "SELECT ostrich_color color, ostrich_owner owner, 'name' ownerName
						                                               FROM ostrich
						                                               WHERE 1" );

						global $g_user;
		  			$current_player_id = $g_user->get_id();

						// put YOUR ostriches first
						foreach( $ostriches as $ostrich )
						{ // go through each player ($player_id is they KEY and $player is the VALUE)
							//throw new feException( "current player id: " . $current_player_id . " player_id:" . $player_id);

							$player = $ostrich['owner'];
							$ostrich['ownerName'] = self::getPlayerNameById($player); // get the name of the player

							if($current_player_id == $player)
							{ // your ostrich
								array_push($result, $ostrich);
							}
						}

						// push OTHER players so they are listed at the bottom
						foreach( $ostriches as $ostrich )
						{ // go through each player ($player_id is they KEY and $player is the VALUE)
							//throw new feException( "current player id: " . $current_player_id . " player_id:" . $player_id);

							$player = $ostrich['owner'];
							$ostrich['ownerName'] = self::getPlayerNameById($player); // get the name of the player

							if($current_player_id != $player)
							{ // opponent ostrich
								array_push($result, $ostrich);
							}
						}

		        return $result;
		}

		function getFurthestEmptyCrates($x, $y)
		{
				$furthestCrates = array();

				$allCrates = self::getObjectListFromDB( "SELECT board_x, board_y
																							FROM board
																							WHERE board_space_type='C' OR board_space_type='O'" );

				$furthestCrateDistance = 0;

				// go through all the crates
				foreach( $allCrates as $crate )
				{ // go through each

					$crateX = $crate['board_x'];
					$crateY = $crate['board_y'];
					$distanceAwayX = abs($x - $crateX);
					$distanceAwayY = abs($y - $crateY);
					$totalDistance = $distanceAwayX + $distanceAwayY;
					$ostrichHere = $this->getOstrichAt($crateX, $crateY); // see if an ostrich is here
					$garmentHere = $this->getGarmentIdAt($crateX, $crateY); // see if a garment is here

					if($garmentHere != 0 || $ostrichHere != "")
					{ // this crate is NOT EMPTY

							// do nothing
					}
					else if($totalDistance > $furthestCrateDistance)
					{ // we have a new furthest crate

							$furthestCrates = array(); // clear out array in case other further crates had been added to it

							array_push($furthestCrates, $crate); // add crate to new array

							$furthestCrateDistance = $totalDistance; // this is now the furthest distance we've found
					}
					else if($totalDistance == $furthestCrateDistance)
					{ // this is the same distance as another crate but still the current furthest we've found

							array_push($furthestCrates, $crate); // add this crate to the existing array
					}
				}

				return $furthestCrates;

		}

		function getClosestEmptyCrates($x, $y)
		{
				$closestCrates = array();

				$allCrates = self::getObjectListFromDB( "SELECT board_x, board_y
																							FROM board
																							WHERE board_space_type='C' OR board_space_type='O'" );

				$closestCrateDistance = 1000;

				// go through all the crates
				foreach( $allCrates as $crate )
				{ // go through each crate

					$crateX = $crate['board_x'];
					$crateY = $crate['board_y'];
					$distanceAwayX = abs($x - $crateX);
					$distanceAwayY = abs($y - $crateY);
					$totalDistance = $distanceAwayX + $distanceAwayY;
					$ostrichHere = $this->getOstrichAt($crateX, $crateY); // see if an ostrich is here
					$garmentHere = $this->getGarmentIdAt($crateX, $crateY); // see if a garment is here

					if($garmentHere != 0 || $ostrichHere != "")
					{ // this crate is NOT EMPTY

							// do nothing
					}
				  else if($totalDistance < $closestCrateDistance)
					{ // we have a new closest crate

							$closestCrates = array(); // clear out array in case other further crates had been added to it

							array_push($closestCrates, $crate); // add crate to new array

							$closestCrateDistance = $totalDistance; // this is now the closest distance we've found
					}
					else if($totalDistance == $closestCrateDistance)
					{ // this is the same distance as another crate but still the current closest we've found

							array_push($closestCrates, $crate); // add this crate to the existing array
					}
				}

				return $closestCrates;
		}

		// Return the ostrich color located at a given X/Y position.
		function getOstrichAt($x, $y)
		{
				// get any ostrich at this location
				$ostriches = self::getObjectListFromDB( "SELECT ostrich_color
																										 FROM ostrich
																										 WHERE ostrich_x=".$x." AND ostrich_y=".$y." " );

				foreach( $ostriches as $ostrich )
				{ // go through each player ($player_id is they KEY and $player is the VALUE)

						// if we find an ostrich at this location, return its color
						return $ostrich['ostrich_color'];
				}

				return ""; // if we don't find an ostrich here, return empty string

		}

		// This gets the player whose turn is next.
		// Used mainly after a player has been active when it was not their turn, like they were pushed
		// into a garment and now must place it before the next player goes.
		// Note: Before calling this, make sure it's not the end of the round because otherwise all players will take turns again.
		function getPlayerWhoseTurnIsNext()
		{
				$lowestNumberOfRoundsCompleted = self::getUniqueValueFromDb("SELECT MIN(player_turns_taken_this_round) FROM player"); // will be 0 if player hasn't gone, will be 1 if one of their ostriches has gone, will be 2 if 2 of their ostriches have gone

				// get the players who have the lowest number of rounds completed
				$playersWhoStillNeedToGo = self::getObjectListFromDB( "SELECT player_id, player_custom_turn_order
																										 FROM player
																										 WHERE player_turns_taken_this_round=$lowestNumberOfRoundsCompleted
																										 ORDER BY player_custom_turn_order ASC" );

				// from those, return the one who is lowest in turn order
				foreach( $playersWhoStillNeedToGo as $player )
				{ // through each player who still needs to go

						return $player['player_id']; // return the first one since we ordered by player_custom_turn_order so they will be next to go
				}

				return null; // should never get here
		}

		function getOstrichWhoseTurnItIs()
		{
				$activePlayer = self::getActivePlayerId(); // get who the active player is
				$ostrichColor = "";
				$turnsTaken = 1000;

				//echo "activeplayer $activePlayer <br>";

				// get all of their ostriches
				$numberOfOstrichesThisPlayerHas = 0;
				$ostrichSql = "SELECT ostrich_color, ostrich_turns_taken, ostrich_is_chosen ";
				$ostrichSql .= "FROM ostrich ";
				$ostrichSql .= "WHERE ostrich_owner=$activePlayer";
				$dbresOstrich = self::DbQuery( $ostrichSql );
				while( $ostrich = mysql_fetch_assoc( $dbresOstrich ) )
				{ // go through each ostrich of this player
						$numberOfOstrichesThisPlayerHas++; // add one to the number of ostriches this player has

						$ostrichColor = $ostrich['ostrich_color']; // save which color ostrich this is

						if($turnsTaken == 1000)
						{ // this is the first ostrich

								$turnsTaken = $ostrich['ostrich_turns_taken']; // save how many turns this ostrich has taken

								if($ostrich['ostrich_is_chosen'] == 1)
								{ // they have selected this ostrich to go next

										return $ostrich['ostrich_color']; // return this ostrich because the player has already said it's going next
								}
						}
						else
						{ // this is the second ostrich
								if($turnsTaken == $ostrich['ostrich_turns_taken'])
								{ // these ostriches have taken the same number of turns so we don't know which one goes next
											if($ostrich['ostrich_is_chosen'] == 1)
											{ // they have selected this ostrich to go first

													return $ostrich['ostrich_color']; // return this second ostrich because it is going next
											}
								}
								else
								{ // one of these ostriches has taken fewer turns than the other

											if($turnsTaken > $ostrich['ostrich_turns_taken'])
											{ // the second ostrich has taken fewer turns

														return $ostrich['ostrich_color']; // so return it since it's next
											}
											else
											{ // the first ostrich has taken fewer turns

														return $ostrichColor; // so return it since it's next
											}
								}
						}
				}

				if($numberOfOstrichesThisPlayerHas == 1)
				{ // if that player only has 1 ostrich, return that ostrich
						return $ostrichColor;
				}

				return ""; // there are multiple ostriches, neither has gone, and neither has been chosen to go first
		}

		// Returns TRUE if the given ostrich played an X on their Zig. False otherwise.
		function hasOstrichPlayedX($ostrich)
		{
				$sql = "SELECT card_type_arg ";
				$sql .= "FROM movementCards ";
				$sql .= "WHERE card_location='played' AND card_ostrich='".$ostrich."'";
				$dbres = self::DbQuery( $sql );
				while( $movementCard = mysql_fetch_assoc( $dbres ) )
				{ // go through all cards this ostrich has played (should just be 1)

						if($movementCard['card_type_arg'] == 0)
						{ // this is an X
								return true;
						}
				}

				return false; // this ostrich has not played an X
		}

		function hasOstrichChosenX($ostrich)
		{

				$chosenZigDistance = $this->getZigDistanceForOstrich($ostrich);

				$sql = "SELECT ostrich_chosen_x_value ";
				$sql .= "FROM ostrich ";
				$sql .= "WHERE ostrich_color='".$ostrich."'";
				$dbres = self::DbQuery( $sql );
				while( $ostrichRecord = mysql_fetch_assoc( $dbres ) )
				{ // get our ostrich

						if($ostrichRecord['ostrich_chosen_x_value'] == 0)
						{ // X has not been selected
								return false;
						}
						else {
								return true;
						}
				}
		}

		// Which type of board space is at this X/Y location?
		function getBoardSpaceType($x, $y)
		{
			  $boardValue = self::getUniqueValueFromDb("SELECT board_space_type FROM board WHERE board_x=$x AND board_y=$y");

				return $boardValue;
		}

		// On which type of board space is this ostrich located?
		function getBoardSpaceTypeForOstrich($ostrich)
		{
				$x = self::getUniqueValueFromDb("SELECT ostrich_x FROM ostrich WHERE ostrich_color='$ostrich'");
				$y = self::getUniqueValueFromDb("SELECT ostrich_y FROM ostrich WHERE ostrich_color='$ostrich'");

			  $boardValue = self::getUniqueValueFromDb("SELECT board_space_type FROM board WHERE board_x=$x AND board_y=$y");

				return $boardValue;
		}

		function getGarmentIdFromType($garmentTypeAsString, $garmentColor)
		{
			  $garmentAsInt = $this->convertGarmentTypeStringToInt($garmentTypeAsString);
				return self::getUniqueValueFromDb("SELECT garment_id FROM garment WHERE garment_color='$garmentColor' AND garment_type='$garmentAsInt'");
		}

		function getChosenGarmentId()
		{
			return self::getUniqueValueFromDb("SELECT garment_id FROM garment WHERE garment_location='chosen'");
		}

		// Return the garment ID located at a given X/Y position or 0 if there isn't one.
		function getGarmentIdAt($x, $y)
		{
				// get any ostrich at this location
				$garments = self::getObjectListFromDB( "SELECT garment_id
																										 FROM garment
																										 WHERE garment_x=$x AND garment_y=$y" );

				foreach( $garments as $garment )
				{ // through each garment returned

						// if we find at garment at this location, return its ID
						return $garment['garment_id'];
				}

				return 0; // if we don't find a garment here, return 0
		}

		function getEndingX($currentX, $currentY, $distance, $direction, $ostrichMoving)
		{
				// if moving left, subtract 2 from x
				if($this->LEFT_DIRECTION == $direction)
				{
					  for ($x = 1; $x <= $distance; $x++)
						{
								$thisX = $currentX-$x;
								$boardValue = $this->getBoardSpaceType($thisX,$currentY);

								/*echo "The value at ($thisX, $currentY) is: $boardValue <br>";
								throw new feException("The value at ($thisX, $currentY) is: $boardValue");*/

								$garmentId = $this->getGarmentIdAt($thisX,$currentY); // get a garment here if there is one
								//echo "The garment at ($thisX,$currentY) is: $garmentId <br>";
								if($garmentId != 0)
								{ // there is a garment here
										$this->giveGarmentToOstrich($garmentId, $ostrichMoving); // give the garment to the ostrich (set garment_location to the color)
										$ownerOfOstrichMoving = $this->getOwnerIdOfOstrich($ostrichMoving);
										$this->addToGarmentReplacementQueue($ownerOfOstrichMoving, $ostrichMoving);
										$this->updatePlayerScores(); // update the player boards with current scores
								}
								else if($boardValue == "C" || $boardValue == "O")
								{ // this is an EMPTY CRATE

										$ownerOfOstrichMoving = $this->getOwnerIdOfOstrich($ostrichMoving);

										$trapsDrawnThisRound = $this->getTrapsDrawnThisRound($ownerOfOstrichMoving);
										if($trapsDrawnThisRound == 0)
										{
												$this->drawTrap($ownerOfOstrichMoving);
										}

										$this->giveCrown($ostrichMoving);

								}
								else if($boardValue == "S")
								{ // we hit a skateboard

										// don't go any further
										return $thisX;
								}
								else if($boardValue == "D")
								{	// went off a cliff

										return $thisX; // don't go any further
								}

								$ostrichWeCollideWith = $this->getOstrichAt($thisX, $currentY); // get any ostriches that might be at this location
								if($ostrichWeCollideWith != "")
								{	// there is an ostrich here
										return $thisX;
								}
					  }

						// we didn't hit anything special so we can go the full distance
						return $currentX-$distance;
				}

				// if moving right, add 2 to x
				if($this->RIGHT_DIRECTION == $direction)
			 	{
						for ($x = 1; $x <= $distance; $x++)
						{ // go space-by-space starting at your current location until the distance is
							// used up or we run into a skateboard or ostrich

								$thisX = $currentX+$x;
								$boardValue = $this->getBoardSpaceType($thisX,$currentY);

								/*echo "The value at ($thisX, $currentY) is: $boardValue <br>";
								throw new feException("The value at ($thisX, $currentY) is: $boardValue");*/

								$garmentId = $this->getGarmentIdAt($thisX,$currentY); // get a garment here if there is one
								//echo "The garment at ($thisX,$currentY) is: $garmentId <br>";
								if($garmentId != 0)
								{ // there is a garment here
										$this->giveGarmentToOstrich($garmentId, $ostrichMoving); // give the garment to the ostrich (set garment_location to the color)
										$ownerOfOstrichMoving = $this->getOwnerIdOfOstrich($ostrichMoving);
										$this->addToGarmentReplacementQueue($ownerOfOstrichMoving, $ostrichMoving);
										$this->updatePlayerScores(); // update the player boards with current scores
								}
								else if($boardValue == "C" || $boardValue == "O")
								{ // this is an EMPTY CRATE

										$ownerOfOstrichMoving = $this->getOwnerIdOfOstrich($ostrichMoving);
										$trapsDrawnThisRound = $this->getTrapsDrawnThisRound($ownerOfOstrichMoving);
										if($trapsDrawnThisRound == 0)
										{
												$this->drawTrap($ownerOfOstrichMoving);
										}

										$this->giveCrown($ostrichMoving);

								}
								else if($boardValue == "S")
								{ // we hit a skateboard

										// don't go any further
										return $thisX;
								}
								else if($boardValue == "D")
								{	// went off a cliff

										return $thisX; // don't go any further
								}

								$ostrichWeCollideWith = $this->getOstrichAt($thisX, $currentY); // get any ostriches that might be at this location
								if($ostrichWeCollideWith != "")
								{	// there is an ostrich here
										return $thisX;
								}
						}

						// we didn't hit anything special so we can go the full distance
						return $currentX+$distance;
				}

				return $currentX;
		}

		function getEndingY($currentX, $currentY, $distance, $direction, $ostrichMoving)
		{
			//throw new feException( "GETY Distance ".$distance." Direction ".$direction." Current X ".$currentX." Current Y ".$currentY." DOWN_DIRECTION:".$this->getGameStateValue("DOWN_DIRECTION"));

				// if moving up, subtract 2 from y
				if($this->UP_DIRECTION == $direction)
				{
						for ($y = 1; $y <= $distance; $y++)
						{ // go space-by-space starting at your current location until the distance is
							// used up or we run into a skateboard or ostrich

								$thisY = $currentY-$y;
								$boardValue = $this->getBoardSpaceType($currentX,$thisY); // get the space type here

								$garmentId = $this->getGarmentIdAt($currentX, $thisY); // get a garment here if there is one
								//echo "The garment at ($currentX, $thisY) is: $garmentId <br>";
								if($garmentId != 0)
								{ // there is a garment here
										$this->giveGarmentToOstrich($garmentId, $ostrichMoving); // give the garment to the ostrich (set garment_location to the color)
										$ownerOfOstrichMoving = $this->getOwnerIdOfOstrich($ostrichMoving);
										$this->addToGarmentReplacementQueue($ownerOfOstrichMoving, $ostrichMoving);
										$this->updatePlayerScores(); // update the player boards with current scores
								}
								else if($boardValue == "C" || $boardValue == "O")
								{ // this is an EMPTY CRATE

									$ownerOfOstrichMoving = $this->getOwnerIdOfOstrich($ostrichMoving);
									$trapsDrawnThisRound = $this->getTrapsDrawnThisRound($ownerOfOstrichMoving);
									if($trapsDrawnThisRound == 0)
									{
											$this->drawTrap($ownerOfOstrichMoving);
									}

										$this->giveCrown($ostrichMoving);

								}
								else if($boardValue == "S")
								{ // we hit a skateboard

										// don't go any further
										return $thisY;
								}
								else if($boardValue == "D")
								{	// went off a cliff

										return $thisY; // don't go any further
								}

								$ostrichWeCollideWith = $this->getOstrichAt($currentX, $thisY); // get any ostriches that might be at this location
								if($ostrichWeCollideWith != "")
								{	// there is an ostrich here
										return $thisY;
								}
						}

						// we didn't hit anything special so we can go the full distance
						return $currentY-$distance;
				}

				// if moving down, add 2 to y
				if($this->DOWN_DIRECTION == $direction)
			 	{
						for ($y = 1; $y <= $distance; $y++)
						{ // go space-by-space starting at your current location until the distance is
							// used up or we run into a skateboard or ostrich

								$thisY = $currentY+$y;
								$boardValue = $this->getBoardSpaceType($currentX,$thisY);

								$garmentId = $this->getGarmentIdAt($currentX, $thisY); // get a garment here if there is one
								//echo "The garment at ($currentX, $thisY) is: $garmentId <br>";
								if($garmentId != 0)
								{ // there is a garment here
										$this->giveGarmentToOstrich($garmentId, $ostrichMoving); // give the garment to the ostrich (set garment_location to the color)
										$ownerOfOstrichMoving = $this->getOwnerIdOfOstrich($ostrichMoving);
										$this->addToGarmentReplacementQueue($ownerOfOstrichMoving, $ostrichMoving);
										$this->updatePlayerScores(); // update the player boards with current scores
								}
								else if($boardValue == "C" || $boardValue == "O")
								{ // this is an EMPTY CRATE

										$ownerOfOstrichMoving = $this->getOwnerIdOfOstrich($ostrichMoving);
										$trapsDrawnThisRound = $this->getTrapsDrawnThisRound($ownerOfOstrichMoving);
										if($trapsDrawnThisRound == 0)
										{
												$this->drawTrap($ownerOfOstrichMoving);
										}

										$this->giveCrown($ostrichMoving);

								}
								else if($boardValue == "S")
								{ // we hit a skateboard

										// don't go any further
										return $thisY;
								}
								else if($boardValue == "D")
								{	// went off a cliff

										return $thisY; // don't go any further
								}

								$ostrichWeCollideWith = $this->getOstrichAt($currentX, $thisY); // get any ostriches that might be at this location
								if($ostrichWeCollideWith != "")
								{	// there is an ostrich here
										return $thisY;
								}
						}

						// we didn't hit anything special so we can go the full distance
						return $currentY+$distance;
				}

				return $currentY;
		}

		function updatePlayerScores()
		{
				$allPlayers = self::getObjectListFromDB( "SELECT player_id
																											 FROM player" );

				foreach($allPlayers as $player)
				{
						$player_id = $player['player_id'];
						$thisPlayerScore = $this->countUniqueGarmentsForPlayer($player_id);
						$sqlAll = "UPDATE player SET player_score=$thisPlayerScore WHERE player_id='$player_id'"; // update score in the database
						self::DbQuery( $sqlAll );

						// update score on player boards
						self::notifyAllPlayers( "updateScore", "", array(
								'player_id' => $player['player_id'],
								'player_score' => $thisPlayerScore
						) );
				}
		}

		function getOstrichWithCrown()
		{
				return self::getUniqueValueFromDb("SELECT ostrich_color FROM ostrich WHERE ostrich_has_crown=1");
		}

		function giveCrown($ostrich)
		{
				$ostrichWithCrown = $this->getOstrichWithCrown();

				$playerName = $this->getOwnerNameOfOstrich($ostrich);

				if($ostrich == $ostrichWithCrown)
				{
						self::notifyAllPlayers( "crownAcquired", clienttranslate( '${player_name} ${ostrichName} already has the crown.' ), array(
								'ostrichName' => $this->getOstrichName($ostrich),
								'player_name' => $playerName,
								'color' => $ostrich
						) );
				}
				else
				{
						// set all ostriches to not have the crown
						$sqlAll = "UPDATE ostrich SET ostrich_has_crown=0" ;
						self::DbQuery( $sqlAll );

						// set the one who got the crown
						$sqlCrown = "UPDATE ostrich SET ostrich_has_crown=1
													WHERE ostrich_color='$ostrich' " ;
						self::DbQuery( $sqlCrown );

						self::notifyAllPlayers( "crownAcquired", clienttranslate( '${player_name} ${ostrichName} snagged the crown!' ), array(
								'color' => $ostrich,
								'ostrichName' => $this->getOstrichName($ostrich),
								'player_name' => $playerName
						) );
				}
		}

		function setOstrichToChosen($ostrich)
		{
				$sql = "UPDATE ostrich SET ostrich_is_chosen=1
											WHERE ostrich_color='$ostrich' " ;
				self::DbQuery( $sql );
		}

		 function drawTrap($player)
		 {
				 $cards = $this->trapCards->pickCards( 1, 'trapCardDeck', $player );

				 $this->incrementTrapsDrawnThisRound($player);

				 // notify player about their card (only they get to know which card they got)
				 self::notifyPlayer( $player, 'iGetNewTrapCard', '', array(
						 'cards' => $cards
					) );

				 // notify players that this trap card has been drawn (they don't get to know which card was drawn, just that it happened)
				 self::notifyAllPlayers( "someoneDrewNewTrapCard", clienttranslate( '${player_name} drew a trap card. Watch out!' ), array(
						 'acquiringPlayer' => $player,
						 'player_name' => self::getPlayerNameById($player)
				 ) );

				 self::incStat( 1, 'traps_drawn', $player ); // increase end game player stat
		 }

		 function incrementTrapsDrawnThisRound($playerId)
		 {
				 $sql = "UPDATE player SET player_traps_drawn_this_round=player_traps_drawn_this_round+1
	 										WHERE player_id='$playerId' " ;
	 						self::DbQuery( $sql );
		 }

		function incrementPlayerRound($playerId)
		{
			// mark that this player has take their turn this ROUND
			$sql = "UPDATE player SET player_turns_taken_this_round=player_turns_taken_this_round+1
										WHERE player_id='$playerId' " ;
						self::DbQuery( $sql );
		}

		// Determine where the ostrich moving ends this movement and tell players where they ended.
		function sendOstrichMoveToPlayers($ostrichMoving, $ostrichTakingTurn, $beingPushed)
		{
				$player_name = self::getCurrentPlayerName(); // get the name of the current player

				$distance = 0;
				$direction = "UNDEFINED";
				$currentX = 0;
				$currentY = 0;

				//echo "sendOtrichMoveToPlayers";

				// get the details about the ostrich that is moving
				$sqlGetOstrich = "SELECT ostrich_x, ostrich_y, ostrich_owner, ostrich_color, ostrich_last_direction, ostrich_last_distance, ostrich_last_turn_order, ostrich_chosen_x_value ";
				$sqlGetOstrich .= "FROM ostrich ";
				$sqlGetOstrich .= "WHERE ostrich_color='".$ostrichMoving."' ";
				$dbres = self::DbQuery( $sqlGetOstrich );
				while( $thisOstrich = mysql_fetch_assoc( $dbres ) )
				{ // find the ostrich moving
						$xValue = $thisOstrich['ostrich_chosen_x_value']; // set the X value if they've set it

						if($xValue != 0 && !$beingPushed)
						{ // they chose an X this round AND they are NOT being pushed (so the X value they chose should be how far they travel)
								$distance = $xValue; // use the X value they chose as their distance
						}
						else
						{ // they did NOT choose an X OR they are being pushed
							$distance = $thisOstrich['ostrich_last_distance']; // their distance was set either when they were pushed or when they chose their zig so use that as their distance
						}

						$direction = $thisOstrich['ostrich_last_direction'];
						$currentX = $thisOstrich['ostrich_x'];
						$currentY = $thisOstrich['ostrich_y'];
				}

				// figure out where their movement will end
				//echo "Distance ".$distance." Direction ".$direction." Current X ".$currentX." Current Y ".$currentY;
				$xDestination = $this->getEndingX($currentX, $currentY, $distance, $direction, $ostrichMoving);
				$yDestination = $this->getEndingY($currentX, $currentY, $distance, $direction, $ostrichMoving);
				$boardValue = $this->getBoardSpaceType($xDestination,$yDestination); // get the type of space on which we are ending

				$ostrichMovingHasZag = $this->doesOstrichHaveZag($ostrichMoving); // see if the ostrich moving has a zag
				$ostrichMovingIsOffCliff = $this->isOstrichOffCliff($ostrichMoving);

				$verbToUse = "moved";
				if($beingPushed)
				{
						$verbToUse = "pushed";
				}
				self::notifyAllPlayers( "moveOstrich", clienttranslate( '${player_name} ${verb} the ${ostrichName} ostrich.' ), array(
						'color' => $ostrichMoving,
						'ostrichTakingTurn' => $ostrichTakingTurn,
						'x' => $xDestination,
					  'y' => $yDestination,
						'spaceType' => $boardValue,
						'ostrichMovingHasZag' => $ostrichMovingHasZag,
						'ostrichMovingIsOffCliff' => $ostrichMovingIsOffCliff,
						'player_name' => self::getActivePlayerName(),
						'ostrichName' => $this->getOstrichName($ostrichMoving),
						'verb' => $verbToUse
				) );

				$this->sendCollisionMoveToPlayers($ostrichMoving, $ostrichTakingTurn, $xDestination, $yDestination, $direction, $distance); // if this moved caused a collision, execute the collision move

				$this->saveOstrichLocation($ostrichMoving, $xDestination, $yDestination); // save ostrich location of ostrich moving to DB after move ends
		}

		// Check all ostriches and see if any are off the cliff. If so, notify players that they fell off, set their respawn order, and update stats.
		function sendCliffFallsToPlayers()
		{
				$ostrichTakingTurn = $this->getOstrichWhoseTurnItIs();
				$ownerOfOstrichTakingTurn = $this->getOwnerIdOfOstrich($ostrichTakingTurn); // get the player whose turn it is

				$allOstriches = $this->getOstrichesInOrder();

				foreach($allOstriches as $ostrichObject)
				{
						$ostrichColor = $ostrichObject['color'];

						$boardValue = $this->getBoardSpaceTypeForOstrich($ostrichColor); // get the type of space of the ostrich who just moved
						$ownerOfOstrich = $this->getOwnerIdOfOstrich($ostrichColor); // get the player who controls the ostrich moving


						if($boardValue == "D")
						{ // the ostrich who just move went off a cliff

								$this->setRespawnOrder($ostrichColor, $ostrichTakingTurn); // mark the order this ostrich will respawn based on any other ostriches that may have fallen off during the current movement
								$this->setGarmentStealableForOstrich($ostrichColor); // mark that this ostrich has a stealable garment now

//self::debug( "sendCliffFallsToPlayers ostrich:$ostrichColor ostrichTakingTurn:$ostrichTakingTurn" );
								if($ostrichColor == $ostrichTakingTurn)
								{ // the ostrich ran off a cliff on their own turn

											self::notifyAllPlayers( "ostrichRanOffCliff", clienttranslate( '${player_name} ran the ${ostrichName} ostrich off a cliff.' ), array(
													'player_name' => self::getActivePlayerName(),
													'ostrichName' => $this->getOstrichName($ostrichColor)
											) );

											self::incStat( 1, 'ran_off_cliff', $ownerOfOstrich ); // add a that you ran off a cliff
								}
								else
								{ // the ostrich was pushed off a cliff by the player taking their turn

										self::notifyAllPlayers( "ostrichWasPushedOffCliff", clienttranslate( '${player_name} pushed the ${ostrichName} ostrich off a cliff.' ), array(
												'player_name' => self::getActivePlayerName(),
												'ostrichName' => $this->getOstrichName($ostrichColor)
										) );

										self::incStat( 1, 'pushed_ostrich_off_cliff', $ownerOfOstrichTakingTurn ); // add stat that the current player pushed an ostrich off a cliff
										self::incStat( 1, 'was_pushed_off_cliff', $ownerOfOstrich ); // add a stat that the owner of the ostrich who fell off the cliff was pushed off a cliff
								}
						}
				}
		}

		function sendCollisionMoveToPlayers($ostrichMoving, $ostrichTakingTurn, $xDestination, $yDestination, $direction, $distance)
		{
				$ostrichWeCollideWith = $this->getOstrichAt($xDestination, $yDestination); // get any ostriches that might be at this location
				$ownerOfOstrichTakingTurn = $this->getOwnerIdOfOstrich($ostrichTakingTurn); // get the player whose turn it is

				if($ostrichWeCollideWith != "" && $ostrichWeCollideWith != $ostrichMoving)
				{ // we collide with an ostrich that is not us
							//echo "ostrich we collide with is $ostrichWeCollideWith";

							$ownerOfOstrichWeCollideWith = $this->getOwnerIdOfOstrich($ostrichWeCollideWith); // get the OWNER (player) of the ostrich who we are pushing
							self::incStat( 1, 'pushed_an_ostrich', $ownerOfOstrichTakingTurn ); // add a stat that you pushed an ostrich
							self::incStat( 1, 'was_pushed', $ownerOfOstrichWeCollideWith ); // add a stat that an ostrich pushed you

							// set the distance and direction of the ostrich we are colliding with to be the same as ours
							$this->saveOstrichDirection($ostrichWeCollideWith, $direction);
							$this->saveOstrichDistance($ostrichWeCollideWith, $distance);

							$this->sendOstrichMoveToPlayers($ostrichWeCollideWith, $ostrichMoving, true); // move the ostrich we collided with

							$this->sendCollisionSkateboardMoveToPlayers($ostrichWeCollideWith, $ostrichTakingTurn);
				}
		}

		function sendCollisionSkateboardMoveToPlayers($ostrichWhoWasJushPushed, $ostrichWhoseTurnItIs)
		{
				$boardValue = $this->getBoardSpaceTypeForOstrich($ostrichWhoWasJushPushed);

				if($boardValue == "S")
				{ // the ostrich who was pushed landed on a skateboard
						$this->sendOstrichMoveToPlayers($ostrichWhoWasJushPushed, $ostrichWhoseTurnItIs, true); // move the ostrich on the skateboard
				}
		}

		function takeAwayZag ($ostrich)
		{
			$sqlUpdate = "UPDATE ostrich SET ";
			$sqlUpdate .= "ostrich_has_zag=0 WHERE ";
			$sqlUpdate .= "ostrich_color='".$ostrich."'";

			self::DbQuery( $sqlUpdate );
		}

		function setRespawnOrder($ostrichMoving, $ostrichTakingTurn)
		{
				$value = self::getUniqueValueFromDb("SELECT max(ostrich_cliff_respawn_order) FROM ostrich");
				$value = $value + 1; // add one

				$sqlUpdate = "UPDATE ostrich SET ";
				$sqlUpdate .= "ostrich_cliff_respawn_order=$value,ostrich_causing_cliff_fall='$ostrichTakingTurn',ostrich_is_dizzy=1 WHERE ";
				$sqlUpdate .= "ostrich_color='$ostrichMoving'";

				self::DbQuery( $sqlUpdate );
		}

		function setGarmentStealableForOstrich($ostrich)
		{
				if($this->doesOstrichHaveOffColoredGarmentToDiscard($ostrich))
				{ // this ostrich has at least one off-colored garment
						$this->setOstrichToStealFromOrder($ostrich); // add this ostrich to the queue of those that need to be stolen from
				}
				else
				{ // this ostrich did not have any garments to steal
						self::notifyAllPlayers( "noGarmentsToSteal", clienttranslate( '${ostrichName} did not have any off-colored garments to steal.' ), array(
							'ostrichName' => $this->getOstrichName($ostrich)
						) );
				}
		}

		// Save details about the move a particular ostrich will make this round.
		function saveOstrichMove( $ostrich, $direction, $distance, $turnOrder, $cardId )
		{
				$sqlUpdate = "UPDATE ostrich SET ";
				$sqlUpdate .= "ostrich_last_direction='".$direction."', ostrich_last_distance=".$distance.", ostrich_last_turn_order=".$turnOrder." WHERE ";
				$sqlUpdate .= "ostrich_color='".$ostrich."'";

				self::DbQuery( $sqlUpdate );

				// write the ostrich on which this card was played to the zig table so we can put it on the right mat on F5
				$zigUpdate = "UPDATE movementCards SET ";
				$zigUpdate .= "card_ostrich='".$ostrich."' WHERE ";
				$zigUpdate .= "card_id=".$cardId."";

				self::DbQuery( $zigUpdate );
		}

		// Save details about the move a particular ostrich will make this round.
		function saveOstrichLocation( $ostrich, $x, $y )
		{
				if($x < 1)
				{ // fell off a cliff
						$x = 0;
				}

				if($x > 8 && $this->getGameStateValue("NUMBER_OF_PLAYERS") > 3)
				{ // we've gone off the cliff to the right
						$x = 9;
				}

				if($x > 12 && $this->getGameStateValue("NUMBER_OF_PLAYERS") < 4)
				{ // we've gone off the cliff to the right
						$x = 9;
				}

				if($y < 1)
				{ // fell off a cliff
						$y = 0;
				}

				if($y > 8)
				{ // we've gone off the cliff on the bottom
						$y = 9;
				}

				$sqlUpdate = "UPDATE ostrich SET ";
				$sqlUpdate .= "ostrich_x=".$x.", ostrich_y=".$y." WHERE ";
				$sqlUpdate .= "ostrich_color='".$ostrich."'";

				self::DbQuery( $sqlUpdate );
		}

		function getOstrichDirection($ostrich)
		{
				return self::getUniqueValueFromDb("SELECT ostrich_last_direction FROM ostrich WHERE ostrich_color='$ostrich'");
		}

		function saveOstrichDirection( $ostrich, $direction )
		{
				$sqlUpdate = "UPDATE ostrich SET ";
				$sqlUpdate .= "ostrich_last_direction='".$direction."' WHERE ";
				$sqlUpdate .= "ostrich_color='".$ostrich."'";

				self::DbQuery( $sqlUpdate );
		}

		function getOstrichZigDirection($ostrich)
		{
				return self::getUniqueValueFromDb("SELECT ostrich_zig_direction FROM ostrich WHERE ostrich_color='$ostrich'");
		}

		function saveOstrichZigDirection( $ostrich, $direction )
		{
				$sqlUpdate = "UPDATE ostrich SET ";
				$sqlUpdate .= "ostrich_zig_direction='".$direction."' WHERE ";
				$sqlUpdate .= "ostrich_color='".$ostrich."'";

				self::DbQuery( $sqlUpdate );
		}

		function getOstrichDistance( $ostrich )
		{
				return self::getUniqueValueFromDb("SELECT ostrich_last_distance FROM ostrich WHERE ostrich_color='$ostrich'");
		}

		function saveOstrichDistance( $ostrich, $distance )
		{
				$sqlUpdate = "UPDATE ostrich SET ";
				$sqlUpdate .= "ostrich_last_distance='".$distance."' WHERE ";
				$sqlUpdate .= "ostrich_color='".$ostrich."'";

				self::DbQuery( $sqlUpdate );
		}

		function getOstrichZigDistance( $ostrich )
		{
				return self::getUniqueValueFromDb("SELECT ostrich_zig_distance FROM ostrich WHERE ostrich_color='$ostrich'");
		}

		public function getStateName() {
       $state = $this->gamestate->state();
       return $state['name'];
   }

		function saveOstrichZigDistance( $ostrich, $distance )
		{
				$sqlUpdate = "UPDATE ostrich SET ";
				$sqlUpdate .= "ostrich_zig_distance='".$distance."' WHERE ";
				$sqlUpdate .= "ostrich_color='".$ostrich."'";

				self::DbQuery( $sqlUpdate );
		}

		function saveZigOstrich( $ostrich, $cardId )
		{
				$sqlUpdate = "UPDATE movementCards SET ";
				$sqlUpdate .= "card_ostrich='".$ostrich."' WHERE ";
				$sqlUpdate .= "card_id=".$cardId;

				self::DbQuery( $sqlUpdate );
		}

		function updateTurnOrder($show)
		{
				$playerIdList = self::getObjectListFromDB( "SELECT player_id FROM player" );

				$turnOrder = 2;
				if($show == true)
				{ // show the turn direction arrows
						$turnOrder = self::getUniqueValueFromDb("SELECT ostrich_last_turn_order FROM ostrich WHERE ostrich_has_crown=1");
				}

				// notify players of the direction (send clockwise/counter)
				self::notifyAllPlayers( 'updateTurnOrder', "", array(
								'players' => $playerIdList,
								'turnOrder' => $turnOrder
				) );
		}

		function getGarmentLocation($garmentTypeInt, $garmentColor)
		{
				return self::getUniqueValueFromDb("SELECT garment_location FROM garment WHERE garment_color='$garmentColor' AND garment_type=$garmentTypeInt");
		}

		function getGarmentXLocation($garmentId)
		{
				return self::getUniqueValueFromDb("SELECT garment_x FROM garment WHERE garment_id=$garmentId");
		}

		function getGarmentYLocation($garmentId)
		{
				return self::getUniqueValueFromDb("SELECT garment_y FROM garment WHERE garment_id=$garmentId");
		}

		function getOstrichXLocation($ostrich)
		{
				return self::getUniqueValueFromDb("SELECT ostrich_x FROM ostrich WHERE ostrich_color='$ostrich'");
		}

		function getOstrichYLocation($ostrich)
		{
				return self::getUniqueValueFromDb("SELECT ostrich_y FROM ostrich WHERE ostrich_color='$ostrich'");
		}

		function getOstrichXValue( $ostrich )
		{
				return self::getUniqueValueFromDb("SELECT ostrich_chosen_x_value FROM ostrich WHERE ostrich_color='$ostrich'");
		}

		function saveOstrichXValue( $ostrich, $distance )
		{
				$sqlUpdate = "UPDATE ostrich SET ";
				$sqlUpdate .= "ostrich_chosen_x_value='".$distance."' WHERE ";
				$sqlUpdate .= "ostrich_color='".$ostrich."'";

				self::DbQuery( $sqlUpdate );
		}

		function saveOstrichZag( $ostrich, $zagStatus )
		{
				$sqlUpdate = "UPDATE ostrich SET ";
				$sqlUpdate .= "ostrich_has_zag='".$zagStatus."' WHERE ";
				$sqlUpdate .= "ostrich_color='".$ostrich."'";

				self::DbQuery( $sqlUpdate );
		}

		function getClockwiseInteger( $clockwiseText )
		{
			if(strtolower($clockwiseText)=="clockwise")
			{
				return 0;
			}
			else {
				return 1;
			}
		}

		// THIS ONLY RETURNS A MEANINGFUL NUMBER WHEN THE TRAP HAS BEEN PLAYED ON AN OSTRICH
		function getOwnerOfTrapCard($trapCardId)
		{
				return self::getUniqueValueFromDb("SELECT card_location_arg FROM trapCards WHERE card_id=$trapCardId");
		}

		function getOwnerIdOfOstrich($ostrich)
		{
				return self::getUniqueValueFromDb("SELECT ostrich_owner FROM ostrich WHERE ostrich_color='$ostrich'");
		}

		function getOwnerNameOfOstrich($ostrich)
		{
				return self::getUniqueValueFromDb( "SELECT p.player_name name FROM player p JOIN ostrich o ON p.player_id=o.ostrich_owner WHERE o.ostrich_color='$ostrich' LIMIT 1" );
		}

		function getMurdererOfOstrich($ostrichMurdered)
		{
				$valueToReturn = self::getUniqueValueFromDb("SELECT ostrich_causing_cliff_fall FROM ostrich WHERE ostrich_color='$ostrichMurdered'");
				if($valueToReturn == null)
				{
						return "";
				}
				else
				{
					return $valueToReturn;
				}
		}

		function getOstrichHoldingGarment($garmentTypeAsString, $garmentColor)
		{
			  $garmentAsInt = $this->convertGarmentTypeStringToInt($garmentTypeAsString);
				return self::getUniqueValueFromDb("SELECT garment_location FROM garment WHERE garment_color='$garmentColor' AND garment_type='$garmentAsInt'");
		}

		function getStartPlayer()
		{
				return self::getUniqueValueFromDb("SELECT ostrich_owner FROM ostrich WHERE ostrich_has_crown=1");
		}

		function getOstrichName($ostrichColor)
		{
				switch($ostrichColor)
				{
						case "ff0000":
							return "RED";

						case "ffa500":
							return "YELLOW";

            case "0000ff":
							return "BLUE";

            case "008000":
							return "GREEN";
				}

				return "unknown";
		}

		function isEndGameConditionMet()
		{
				$playerSql = "SELECT player_id ";
        $playerSql .= "FROM player ";
        $dbresPlayer = self::DbQuery( $playerSql );
        while( $player = mysql_fetch_assoc( $dbresPlayer ) )
        { // go through each player
						$player_id = $player['player_id'];
						$numberOfOstrichesThisPlayerHas = 0;
						$numberOfFullyGarmentedOstrichesThisPlayerHas = 0;

						$ostrichSql = "SELECT ostrich_color ";
		        $ostrichSql .= "FROM ostrich ";
						$ostrichSql .= "WHERE ostrich_owner=$player_id";
		        $dbresOstrich = self::DbQuery( $ostrichSql );
		        while( $ostrich = mysql_fetch_assoc( $dbresOstrich ) )
		        { // go through each ostrich of this player
								$numberOfOstrichesThisPlayerHas++; // add one to the number of ostriches this player has

								$ostrichColor = $ostrich['ostrich_color']; // save which color ostrich this is

								// set the garment types to false
								$hasHeadGarment = false;
								$hasBodyGarment = false;
								$hasLegsGarment = false;
								$hasFeetGarment = false;

								$garmentSql = "SELECT garment_type ";
				        $garmentSql .= "FROM garment ";
								$garmentSql .= "WHERE garment_location='$ostrichColor'";
				        $dbresGarment = self::DbQuery( $garmentSql );
				        while( $ostrich = mysql_fetch_assoc( $dbresGarment ) )
				        { // go through each garment this ostrich has
										$garmentType = $ostrich['garment_type'];

										switch($garmentType)
										{
												case 0:
														$hasHeadGarment = true;
												break;
												case 1:
														$hasBodyGarment = true;
												break;
												case 2:
														$hasLegsGarment = true;
												break;
												case 3:
														$hasFeetGarment = true;
												break;
										}
								}

								if($hasHeadGarment && $hasBodyGarment && $hasLegsGarment && $hasFeetGarment)
								//if($hasFeetGarment)
								{ // this ostrich has all required garment types
										$numberOfFullyGarmentedOstrichesThisPlayerHas++; // add one to the number of fully garmented ostriches this player has
								}
						}

						if($numberOfOstrichesThisPlayerHas == $numberOfFullyGarmentedOstrichesThisPlayerHas)
						{ // all of this player's ostriches are fully garmented
								return true;
						}
        }

				return false;
		}

		// Set the state when we are STARTING a player's turn based on which actions are available to them.
		/*
				1. finish all moves
				2. steal/lose garments for cliff falls
				3. respawn ostriches that fell off a cliff
				4. discard traps if you have too many
				5. replace garments if any were acquired
		*/
		function setState_PreMovement()
		{
				$ostrichWhoseTurnItIs = $this->getOstrichWhoseTurnItIs(); // the ostrich whose turn it is (empty string if we don't know)
				$trapPlayedOnOstrichWhoseTurnItIs = $this->getNextTrapPlayedOnOstrich($ostrichWhoseTurnItIs);

				// signify that this player has taken a turn for end game stats
				$ostrichOwner = $this->getOwnerIdOfOstrich($ostrichWhoseTurnItIs); // get the owner of the ostrich
				self::incStat( 1, 'turns_number', $ostrichOwner ); // increase end game player stat

				//echo "ostrichHasPlayedX is $ostrichHasPlayedX for ostrich $ostrich_whose_turn_it_is <br>";

				if($ostrichWhoseTurnItIs == "")
				{ // the active player needs to choose their ostrich (players have multiple ostriches and the active player has both ostriches who have not yet moved)

						$this->gamestate->nextState( "chooseOstrich" ); // go to state where active player will choose their ostrich

				}
				else if($this->hasOstrichPlayedX($ostrichWhoseTurnItIs) && !$this->hasOstrichChosenX($ostrichWhoseTurnItIs))
				{ // we know which ostrich is moving (players have a single ostrich or the active player has already moved their other ostrich) and they played an X

						$this->gamestate->nextState( "chooseXValue" ); // go to the state where the active player can choose their X value

				}
				else if($trapPlayedOnOstrichWhoseTurnItIs != 0)
				{ // we know which ostrich is moving, the ostrich did NOT play an X, and the ostrich has at least one trap played on them

						$this->gamestate->nextState( "askTrapBasic" ); // go to the resolve trap phase

				}
				else
				{ // we know which ostrich is moving, the ostrich did NOT play an X, and there NO TRAPS played on them

						$this->gamestate->nextState( "nextMovementTurn" ); // use the nextMovementTurn transition to go to the part of the turn where the move is executed
				}
		}

		// This sets the state after all movement has completed for the current ostrich.
		function setState_TrapAndCliffAndGarmentCleanup()
		{

				if($this->areAnyOstrichesOffCliffs())
				{ // at least one ostrich has fallen off a cliff
						$ostrichToRespawnObject = $this->getNextOstrichToRespawn(); // get the next ostrich up next for respawning


						if($ostrichToRespawnObject == "")
						{ // we could not find an ostrich to respawn (this shouldn't happen)
								throw new feException( "Could not find the next ostrich to respawn"); // unexpected exception
								$this->endMovementTurn(); // end the turn
						}
						$ostrichToRespawn = $ostrichToRespawnObject['ostrich_color'];
						$ownerOfOstrichToRespawn = $this->getOwnerIdOfOstrich($ostrichToRespawn);
						$ostrichMurderer = $ostrichToRespawnObject['ostrich_causing_cliff_fall'];


						if($ostrichMurderer == "")
						{ // this ostrich has already been punished

								$this->gamestate->nextState( "askToRespawn" ); // ask the player to respawn the ostrich who fell
						}
						else if($ostrichMurderer == $ostrichToRespawn)
						{ // this ostrich ran off a cliff

									if($this->doesOstrichHaveOffColoredGarmentToDiscard($ostrichToRespawn))
									{ // this ostrich has a garment to discard

												$this->gamestate->nextState( "askWhichGarmentToDiscard" ); // transition to garment discard phase
									}
									else
									{ // this ostrich does NOT have a garment to discard
											$this->gamestate->nextState( "askToRespawn" ); // ask the player to respawn the ostrich who fell
									}
						}
						else
						{ // this ostrich was pushed off a cliff
								//$ownerOfPusher = $this->getOwnerIdOfOstrich($ostrichToRespawn['ostrich_causing_cliff_fall']);
								//$this->gamestate->changeActivePlayer( $ownerOfPusher ); // set the active player (this cannot be done in an activeplayer game state)
								$this->gamestate->nextState( "askStealOrDraw" ); // transition to ask if the player would like to steal a garment or draw a card

						}

						//$this->respawnAnOstrich();
				}
				else if( !empty($this->getPlayersWithMoreThan1TrapCard()) )
				{ // at least one player has more than one Trap Card

						$playersWhoNeedToDiscard = $this->getPlayersWithMoreThan1TrapCard();

						$playersToDiscardCount = count($playersWhoNeedToDiscard);
						//echo "Count of playersWhoNeedtoDiscard: $playersToDiscardCount <br>";

						//echo "going to discardTrapCards <br>";

						$this->gamestate->setPlayersMultiactive( $playersWhoNeedToDiscard, "discardTrapCards", true ); // set the players who need to discard to be active
						$this->gamestate->nextState( "discardTrapCards" ); // transition to discard phase
				}
				else if($this->countGarmentReplacementQueue() > 0)
				{ // we need to replace a garment
					$this->gamestate->nextState( "askToReplaceGarment" );
				}
				else
				{ // there is nothing special the player can do so we can end their turn

						$this->endMovementTurn(); // end the turn
				}
		}

		// An action during a player's movement has just completed and we need to know which state to be in next.
		function setState_PostMovement($ostrichMoving, $ostrichTakingTurn)
		{

				$boardValue = $this->getBoardSpaceTypeForOstrich($ostrichMoving); // get the type of space of the ostrich who just moved

				$canUseZag = $this->doesOstrichHaveZag($ostrichMoving); // true if they have a zag
				$ostrichMovingIsOffCliff = $this->isOstrichOffCliff($ostrichMoving);




				$this->LAST_MOVED_OSTRICH = $ostrichMoving; // save the last ostrich to have moved

				if($this->isEndGameConditionMet())
				{ // the game has ended
						$this->gamestate->nextState( "endGame" );
				}
				else if($boardValue == "S" && $ostrichMoving == $ostrichTakingTurn)
				{ // the ostrich walked on the skateboard on their turn
						$this->gamestate->nextState( "askUseSkateboard" ); // need to ask the player which direction they want to go on the skateboard
				}
				else if($canUseZag &&
								($ostrichMoving == $ostrichTakingTurn) &&
								!$ostrichMovingIsOffCliff)
				{ // the player has a zag they can use and the ostrich moving is the one taking their turn and they have not fallen off a cliff

						$this->gamestate->nextState( "askUseZag" ); // need to ask the player if they want to use a zag, and if so, which direction they want to travel
				}
				else
				{ // movement is complete
					$this->setState_TrapAndCliffAndGarmentCleanup();
				}
		}

		function updateZigDrawStats($cardsDrawn, $playerDrew)
		{

				foreach($cardsDrawn as $card)
				{
						$cardid = $card['id']; // internal id
						$wise = $card['type']; // clockwise or counterclockwise
						$distance = $card['type_arg']; // distance 0, 1, 2, 3
						$play = $card['location_arg']; // player ID

						//echo "distance drawn $distance";

						switch($distance)
						{
								case 0:
										self::incStat( 1, 'x_drawn', $playerDrew ); // increase end game player stat
										self::incStat( 1, 'x_drawn' ); // increase end game table stat
								break;
								case 1:
										self::incStat( 1, 'ones_drawn', $playerDrew ); // increase end game player stat
										self::incStat( 1, 'ones_drawn' ); // increase end game table stat
								break;
								case 2:
										self::incStat( 1, 'twos_drawn', $playerDrew ); // increase end game player stat
										self::incStat( 1, 'twos_drawn' ); // increase end game table stat
								break;
								case 3:
										self::incStat( 1, 'threes_drawn', $playerDrew ); // increase end game player stat
										self::incStat( 1, 'threes_drawn' ); // increase end game table stat
								break;
						}
				}


		}

//////////////////////////////////////////////////////////////////////////////
//////////// Player actions
////////////

    /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in crashandgrab.action.php)
    */

		function executeChooseDirection()
		{
				$this->saveOstrichDirection();

		}

		// The current PLAYER is using a trap on a specific OSTRICH.
		function executeSetTrap($ostrichTargeted)
		{
				$playerUsing = self::getCurrentPlayerId(); // Current Player = player who played the current player action (the one who made the AJAX request). Active Player = player whose turn it is.

				$this->setTrapCardTarget($playerUsing, $ostrichTargeted); // Change the location of the trap card in the playerUsing's hand to be played on the ostrichTargeted.

				$ownerOfOstrichTarget = $this->getOwnerIdOfOstrich($ostrichTargeted);
				self::incStat( 1, 'i_used_trap', $playerUsing ); // add stat that says the player using played a trap
				self::incStat( 1, 'trap_used_on_me', $ownerOfOstrichTarget ); // add stat that the owner of the ostrich targeted was targeted by a trap

				// Make this player unactive now
        // and tell the machine state to use transtion "directionChosen" if all players are now unactive
        $this->gamestate->setPlayerNonMultiactive( $playerUsing, "allTrappersDone" );
		}

		function executeChooseOstrichToGoNext()
		{
				$ostrich = $this->getOstrichWhoseTurnItIs();
				$this->setOstrichToChosen($ostrich);

				$this->gamestate->nextState( "zigChosen" ); // stay in this phase
		}

		function executeChooseZig( $ostrich, $card_id )
    {
				// Check that this is the player's turn and that it is a "possible action" at this game state (see states.inc.php)
        self::checkAction( 'chooseZig' );

				if($card_id == 0)
				{
						throw new BgaUserException( self::_("Please select a Zig.") );
				}

				$player_id = self::getCurrentPlayerId(); // Current Player = player who played the current player action (the one who made the AJAX request). Active Player = player whose turn it is.
				$player_name = self::getCurrentPlayerName(); // Current Player = player who played the current player action (the one who made the AJAX request). Active Player = player whose turn it is.

				$card_name = "unknown"; // will be set in the loop next
				$card_distance = -3; // will be set in the loop next
				$card_clockwise = -3; // will be set in the loop next

				$sql = "SELECT card_type, card_type_arg ";
        $sql .= "FROM movementCards ";
        $sql .= "WHERE card_id=".$card_id;
        $dbres = self::DbQuery( $sql );
        while( $movementCard = mysql_fetch_assoc( $dbres ) )
        {
						$card_distance = $movementCard['card_type_arg'];
						$card_clockwise = $movementCard['card_type'];
						$card_name = $movementCard['card_type_arg']." ".$movementCard['card_type'];
        }

				$card_clockwise_integer = $this->getClockwiseInteger($card_clockwise);

			  $this->movementCards->moveCard( $card_id, 'zigChosen', $player_id );
				$this->saveOstrichZigDistance($ostrich, $card_distance); // put the distance of the zig on this ostrich so we know it has been chosen
				$this->saveZigOstrich($ostrich, $card_id); // mark this zig as belonging to this ostrich

        // notify active player that their zig selection was registered so they can move that zig card to its mat
				self::notifyPlayer( $player_id, 'zigChosen', clienttranslate( '${player_name} can now choose a direction for that Zig.' ), array(
					'ostrich_color' => $ostrich,
					'card_id' => $card_id,
					'player_id' => $player_id,
					'distance' => $card_distance,
					'clockwise' => $card_clockwise_integer,
					'player_name' => self::getCurrentPlayerName()
				 ) );

				$this->gamestate->nextState( "zigChosen" ); // keep them in the choose Zig phase because we still need to choose direction
		}

		// Called when a player has chosen their direction.
		// This saves the move they selected to the database for use during the Move Phase.
    function executeChooseZigDirection( $direction )
    {
        //self::checkAction( "giveCards" );

				$player_id = self::getCurrentPlayerId(); // Current Player = player who played the current player action (the one who made the AJAX request). Active Player = player whose turn it is.
				$player_name = self::getCurrentPlayerName(); // Current Player = player who played the current player action (the one who made the AJAX request). Active Player = player whose turn it is.

				$card_id = $this->getCardIdOfPlayerChosenZig($player_id);
				$ostrich = $this->getOstrichOfPlayerChosenZig($player_id);

				// move our card to played (face-down)
        $this->movementCards->moveCard( $card_id, 'played', $player_id );

				$card_name = "unknown"; // will be set in the loop next
				$card_distance = -3; // will be set in the loop next
				$card_clockwise = -3; // will be set in the loop next

				$sql = "SELECT card_type, card_type_arg ";
        $sql .= "FROM movementCards ";
        $sql .= "WHERE card_id=".$card_id;
        $dbres = self::DbQuery( $sql );
        while( $movementCard = mysql_fetch_assoc( $dbres ) )
        {
						$card_distance = $movementCard['card_type_arg'];
						$card_clockwise = $movementCard['card_type'];
						$card_name = $movementCard['card_type_arg']." ".$movementCard['card_type'];
        }

				$card_clockwise_integer = $this->getClockwiseInteger($card_clockwise);

				$hasCrown = $this->doesOstrichHaveCrown($ostrich); // true if this ostrich is the starting player
				if($hasCrown)
				{ // this is the starting player and they have chosen their zig so we can update the turn order now
						$this->resetTurnOrder($card_clockwise);

						self::incStat( 1, 'rounds_started', $player_id ); // increase end game player stat
				}

				$this->saveOstrichMove($ostrich, $direction, $card_distance, $card_clockwise_integer, $card_id); // save the direction to the ostrich table in the ostrich_last_direction field
				$this->saveOstrichZigDirection($ostrich, $direction); // save the direction so we have it in case we are pushed before our turn comes up
				$this->saveOstrichZigDistance($ostrich, $card_distance); // save the distance so we have it in case we are pushed before our turn comes up

				//throw new feException( "Color ".$ostrich);

				self::notifyPlayer( $player_id, "iChoseDirection", clienttranslate( '${player_name} chose a direction.' ), array(
            'player_id' => $player_id,
            'player_name' => $player_name,
            'card_name' => $card_name,
						'card_id' => $card_id,
						'distance' => $card_distance,
						'color' => $ostrich,
						'clockwise' => $card_clockwise_integer,
						'degreesRotated' => $this->getDegreesRotated($direction)
        ) );

				self::notifyAllPlayers( "otherPlayerPlayedZig", clienttranslate( '${player_name} chose a direction.' ), array(
            'player_id' => $player_id,
            'player_name' => $player_name,
						'color' => $ostrich
        ) );

        // Make this player unactive now
        // (and tell the machine state to use transtion "directionsChosen" if all players are now unactive
        $this->gamestate->setPlayerNonMultiactive( $player_id, "directionsChosen" );
    }

		function executeStartZigPhaseOver()
		{
				$player_id = self::getCurrentPlayerId();
				$player_name = self::getCurrentPlayerName(); // Current Player = player who played the current player action (the one who made the AJAX request). Active Player = player whose turn it is.

				// get all of this player's ostriches
				$theirOstriches = $this->getAllPlayersOstriches($player_id);

				$chosenCards = $this->movementCards->getCardsInLocation( 'zigChosen', $player_id ); // get the cards that were selected but their direciton wasn't set
				$playedCards = $this->movementCards->getCardsInLocation( 'played', $player_id ); // get the cards that were selected and had their direction set

				// reset their zig distance and direction
				foreach( $theirOstriches as $ostrich )
		    { // go through all of this player's ostriches

						$this->resetOstrichZigToDefault($ostrich['ostrich_color']);
				}

				// notify player so the card they had played can go back into their hand
				self::notifyPlayer( $player_id, "iStartZigPhaseOver", clienttranslate( "${player_name} must choose a Zig card and then a direction." ), array(
					'chosenCards' => $chosenCards,
					'playedCards' => $playedCards
			  ) );

				$this->gamestate->nextState( "startOver" ); // stay in the chooseZig phase
		}

		// The player is indicating that they do NOT want to play a trap.
		function noTrap()
    {
        // Check that this is the player's turn and that it is a "possible action" at this game state (see states.inc.php)
        //self::checkAction( 'playCard' );

        $player_id = self::getCurrentPlayerId();


				// Make this player unactive now
        // and tell the machine state to use transtion "directionChosen" if all players are now unactive
        $this->gamestate->setPlayerNonMultiactive( $player_id, "allTrappersDone" );
    }

		function startTrapPhase()
		{
			$playersWithTraps = $this->getPlayersWithAnyTrapsInHand(); // get all players with trap cards in their hand
			$this->gamestate->setPlayersMultiactive( $playersWithTraps, "allTrappersDone", true ); // set all players with trap cards in hand to be active

			if(count($playersWithTraps) == 0)
			{ // no one has any trap cards
					$this->gamestate->nextState( "allTrappersDone" ); // go to the set trap phase
			}
			else
			{
					$this->gamestate->nextState( "startSetTraps" ); // go to the set trap phase
			}
		}

		// This is called once after all traps have been set.
		function startMovePhase()
		{
				$this->notifyPlayersAboutTrapsSet(); // now that traps are set, notify all players about them
				$this->updateTurnOrder(true); // send notification to update the turn order arrow

				$startPlayer = $this->getStartPlayer(); // get the player who owns the ostrich with the crown
				$this->gamestate->changeActivePlayer( $startPlayer ); // set the active player (this cannot be done in an activeplayer game state)

				$this->setState_PreMovement(); // set the player's phase based on what that player has available to them
		}

		function executeMove( $ostrichMoving, $ostrichTakingTurn )
		{
				self::debug( "executeMove ostrichMoving:$ostrichMoving ostrichTakingTurn:$ostrichTakingTurn" );

				if($ostrichMoving == "")
				{
						$ostrichMoving = $this->getOstrichWhoseTurnItIs();
				}

				if($ostrichTakingTurn == "")
				{
						$ostrichTakingTurn = $ostrichMoving;
				}

				$this->sendOstrichMoveToPlayers($ostrichMoving, $ostrichTakingTurn, false); // determine where the ostrich moving ends this movement and tell players where they ended

				$this->sendCliffFallsToPlayers(); // tell players if the ostrich moving fell off a cliff and update stats

				$this->setState_PostMovement($ostrichMoving, $ostrichTakingTurn);
		}

		function executeMoveInNewDirection( $ostrichMoving, $ostrichTakingTurn, $newDirection)
		{
				$this->saveOstrichDirection($ostrichMoving, $newDirection); // update the database with its new last moved direction
				$zigDistance = $this->getZigDistanceForOstrich($ostrichMoving); // get the distance on this ostrich's zig card
				$this->saveOstrichDistance($ostrichMoving, $zigDistance); // if this ostrich collided, its distance/direction was set to that of the collider so we also need to reset the distance

				$this->executeMove($ostrichMoving, $ostrichTakingTurn);
		}

		function executeZagMove( $direction)
		{
				$ostrichZagging = $this->getOstrichWhoseTurnItIs(); // you can only zag on your own turn
				$this->saveOstrichDirection($ostrichZagging, $direction); // update the database with its new last moved direction

				$this->takeAwayZag($ostrichZagging); // take the zag away from the ostrich

				$this->notifyPlayersOfZagUsage($ostrichZagging);

				$this->executeMove($ostrichZagging, $ostrichZagging);
		}

		function executeRespawnOstrich()
		{
				$this->respawnAnOstrich(); // respawn the next ostrich up for respawning

				$this->setState_TrapAndCliffAndGarmentCleanup(); // see which state we need next
				//$this->setState_PreMovement(); // set the player's phase based on what that player has available to them (REMOVED because X running off a cliff on their own turn caused them to keep going forever)
		}

		function executeTrapUsage()
		{
				$this->triggerTrap(); // trigger the next trap for this ostrich

				$this->setState_PreMovement(); // set the player's phase based on what that player has available to them
		}

		function executeDraw2Zigs()
		{
				$activeOstrich = $this->getOstrichWhoseTurnItIs();
				$ownerOfActiveOstrich = $this->getOwnerIdOfOstrich($activeOstrich);

				$cards = $this->movementCards->pickCards( 2, 'movementCardDeck', $ownerOfActiveOstrich );
				$this->updateZigDrawStats($cards, $ownerOfActiveOstrich); // update the statistics about zig cards being drawn

				// Notify player about their cards
				self::notifyPlayer( $ownerOfActiveOstrich, 'drawZigs', '', array(
						'cards' => $cards
				) );

				$this->respawnAnOstrich(); // respawn the next ostrich up for respawning

				if($this->getNextOstrichToStealFromValue() != 0)
				{ // there is another ostrich off the cliff who needs a garment stolen or zigs drawn
						$this->gamestate->nextState( "askStealOrDraw" ); // transition to ask if the player would like to steal a garment or draw a card
				}
				else
				{
						$this->setState_TrapAndCliffAndGarmentCleanup(); // set the state now that moving is complete
				}
		}

		function executeAskWhichGarmentToSteal()
		{
				$this->gamestate->nextState( "chooseGarmentToSteal" );
		}

		// A player would like to discard 3 cards to claim a zag.
		function executeClaimZag($ostrich, $cardsDiscarded)
		{
				self::checkAction( 'claimZag' ); // make sure it is a "possible action" at this game state (see states.inc.php)

				if(count($cardsDiscarded) != 3)
				{	// they didn't select exactly 3 cards
						throw new BgaUserException( self::_("You must select exactly 3 matching Zigs.") );
				}

				if($this->doesOstrichHaveZag($ostrich))
				{ // the ostrich already has a zag (shouldn't happen)
						throw new feException( "This ostrich already has a Zag." );
				}

				if(!$this->doTheseCardsMatch($cardsDiscarded))
				{ // the 3 selected Zigs do not match
						throw new BgaUserException( self::_("These Zigs do not match.") );
				}

				// transition to choose zig phase
				$currentPlayer = self::getCurrentPlayerId(); // Current Player = player who played the current player action (the one who made the AJAX request). Active Player = player whose turn it is.
				$this->gamestate->setPlayerNonMultiactive( $currentPlayer, "transitionToChooseZig" ); // set this player to not active and use transition chooseZig if all players are done


				$this->saveOstrichZag($ostrich, 1); // give the ostrich a zag token (set ostrich.ostrich_has_zag to true)
				$player_id = 0; // this will be set based on the cards discarded

				// discard the 3 cards
				foreach( $cardsDiscarded as $card )
		    { // go through all the cards that were played
								$player_id = $this->getPlayerIdForZigCardId($card); // grab the player ID before we discard the card
								$this->movementCards->moveCard( $card, 'discard'); // move the card to the discard pile
				}

 				$replacementCards = $this->movementCards->pickCards( 3, 'movementCardDeck', $player_id ); // draw 3 replacement cards
				$this->updateZigDrawStats($replacementCards, $player_id); // update the statistics about zig cards being drawn

				$player_name = self::getCurrentPlayerName();
				self::notifyAllPlayers( 'zagClaimed', clienttranslate( '${player_name} claimed a Zag.' ), array(
								'player_id' => $player_id,
								'discardedCards' => $cardsDiscarded,
								'newCards' => $replacementCards,
								'ostrich' => $ostrich,
								'player_name' => $player_name
				) );

    		self::incStat( 1, 'zags_claimed', $player_id ); // increase end game player stat
		}

		function executeSelectXValue($xValue)
		{
				$ostrich = $this->getOstrichWhoseTurnItIs();
				$this->saveOstrichDistance($ostrich, $xValue);
				$this->saveOstrichXValue($ostrich, $xValue);

				$this->notifyPlayersOfXSelection($ostrich, $xValue);

				$this->setState_PreMovement(); // set the player's phase based on what that player has available to them
		}

		function executeDiscardTrap($trapCardId)
		{
					$this->discardTrapCard($trapCardId, true); // discard the card

					$this->setState_TrapAndCliffAndGarmentCleanup(); // set the phase depending on whether there are any traps to discards or garments to replace
		}

		function notifyPlayersOfZagUsage($ostrichUsing)
		{
			self::notifyAllPlayers( 'zagUsed', "", array(
					'ostrich' => $ostrichUsing
			) );
		}

		function notifyPlayersAboutTrapsSet()
		{
				$trapsSet = $this->getAllTrapsThatHaveBeenSet(); // find all the traps that have been set in the order they will be triggered

				foreach($trapsSet as $trapSet)
				{ // go through each trap set in the order it will be triggered

						$cardId = $trapSet['card_id'];
						$playerPlayingTrap = $trapSet['playerWhoPlayed'];
						$ostrichTargeted = $trapSet['ostrichTargeted'];

						$players = self::loadPlayersBasicInfos();
						foreach($players as $player)
						{ // notify each player about this trap
							$id = $player['player_id'];
							$name = $player['player_name'];
self::debug( "notifyPlayersAboutTrapsSet player_id:$id ostrichTakingTurn:$name" );

								if($player['player_id'] == $playerPlayingTrap)
								{ // send this to the player who played the trap
										self::notifyPlayer( $player['player_id'], 'myTrapSet', clienttranslate( '${player_name} plays a trap on ${nameOfOstrichTargeted}.' ), array(
												'cardId' => $cardId,
												'ostrichTargeted' => $ostrichTargeted,
												'nameOfOstrichTargeted' => $this->getOstrichName($ostrichTargeted),
												'player_name' => $player['player_name']
										 ) );
							 	}
								else
								{ // send this to a player who did NOT play the trap
										self::notifyPlayer( $player['player_id'], 'otherPlayerTrapSet', clienttranslate( '${player_name} played a trap on ${nameOfOstrichTargeted}.' ), array(
												'ostrichTargeted' => $ostrichTargeted,
												'nameOfOstrichTargeted' => $this->getOstrichName($ostrichTargeted),
												'playerWhoPlayed' => $playerPlayingTrap,
												'player_name' => $player['player_name']
										 ) );
								}
					 	}
				}

		}

		function notifyPlayersOfXSelection($ostrichUsing, $xValue)
		{
			//$player_name = self::getCurrentPlayerName();


			self::notifyAllPlayers( 'xSelected', clienttranslate( '${player_name} set their X Zig for the ${ostrich} ostrich to ${xValue}.' ), array(
					'ostrich' => $ostrichUsing,
					'xValue' => $xValue,
					'player_name' => self::getActivePlayerName()
			) );
		}

		// A garment has been selected for spawning.
		function executeReplaceGarmentChooseGarment($garmentTypeString, $garmentColor)
		{
				self::checkAction( 'replaceGarmentClick', false ); // Check that this is player's turn and that it is a "possible action" at this game state (see states.inc.php) -- the false argument says don't check if we are the active player because we might be replacing a garment on another player's turn

				if($this->getGarmentLocation($this->convertGarmentTypeStringToInt($garmentTypeString), $garmentColor) != 'pile')
				{ // hey... this isn't in the garment pile
						throw new BgaUserException( self::_("You can only choose new garments to spawn from the garment pile.") );
				}

				$currentPlayerId = $this->getCurrentPlayerId(); // the player who clicked on a garment during the choose garment phase
				$playerIdSpawningGarment = $this->getPlayerIdRespawningGarment(); // the player who gets to choose a new garment
				if($currentPlayerId != $playerIdSpawningGarment)
				{	// the player who clicked is not the same player who is up for replacing a garment
						throw new BgaUserException( self::_("Only the player who picked up the garment can choose a new one to place.") );
				}

				// set the garment chosen to location of chosen
				//$garmentTypeAsString = $this->convertGarmentTypeIntToString($garmentType);
				$garmentId = $this->getGarmentIdFromType($garmentTypeString, $garmentColor);
				$this->setGarmentLocation($garmentId, "chosen");

				//$garmentTypeString = $this->convertGarmentTypeIntToString($garmentType);
				// send notification that this garment was chosen
				self::notifyAllPlayers( "replacementGarmentChosen", clienttranslate( 'A garment is being replaced.' ), array(
						'garmentColor' => $garmentColor,
					  'garmentType' => $garmentTypeString
				) );

				$garmentsLeft = $this->getGarmentsInPile(); // see how many garments are available for placing
				if(count($garmentsLeft) > 0)
				{ // there is at least 1 garment that can be placed
						$this->gamestate->nextState( "replaceGarmentChooseGarment" ); // go to replaceGarmentChooseSpace state so they can choose where the garment will go
				}
				else
				{ // all garments have been acquired... they must fight for the rest
						$this->setState_TrapAndCliffAndGarmentCleanup(); // DETERMINE NEXT STATE
				}

		}

		function executeReplaceGarmentChooseSpace($xLocation, $yLocation)
		{
				self::checkAction( 'spaceClick', false ); // Check that this is player's turn and that it is a "possible action" at this game state (see states.inc.php) -- the false argument says don't check if we are the active player because we might be replacing a garment on another player's turn

				$currentPlayerId = $this->getCurrentPlayerId(); // the player who clicked on a space during the choose garment replacement phase
				$playerIdSpawningGarment = $this->getPlayerIdRespawningGarment(); // the player who gets to choose a new garment
				if($currentPlayerId != $playerIdSpawningGarment)
				{	// the player who clicked is not the same player who is up for replacing a garment
						throw new BgaUserException( self::_("Only the player who picked up the garment can choose where it can be placed.") );
				}

				if($this->isValidGarmentSpawnLocation($xLocation, $yLocation))
				{ // this is a valid location

						$garmentId = $this->getChosenGarmentId();  // get the garment ID

						$this->moveGarmentToBoard($garmentId, $xLocation, $yLocation); // update the garment type and let players know

						$playerRemoved = $this->popReplacementQueue(); // remove this from the garment replacement queue
						//$queueCount = $this->countGarmentReplacementQueue();
						//echo "array count after is $queueCount <br>";

						$this->setState_TrapAndCliffAndGarmentCleanup(); // DETERMINE NEXT STATE
				}
				else
				{ // NOT a valid location
						throw new BgaUserException( self::_("That is not a valid space to place a garment.") );
				}
		}

		function executeDiscardGarment($garmentType, $garmentColor)
		{
				self::checkAction( 'discardGarmentClick' ); // Check that this is player's turn and that it is a "possible action" at this game state (see states.inc.php)

				$ostrichHoldingGarmentChosen = $this->getOstrichHoldingGarment($garmentType, $garmentColor); // get the ostrich holding the garment of type and color
				$ostrichWhoseTurnItIs = $this->getOstrichWhoseTurnItIs(); // get the ostrich whose turn it is

				if($ostrichHoldingGarmentChosen != $ostrichWhoseTurnItIs)
				{ // make sure the active player is discarding their own garment
						throw new BgaUserException( self::_("You can only discard your own garments.") );
				}

				$garmentId = $this->getGarmentIdFromType($garmentType, $garmentColor);
				$this->moveGarmentToPile($garmentId); // update the database putting it back in the pile and send notification to all players
				$this->resetCliffPusher($ostrichHoldingGarmentChosen);

				$this->updatePlayerScores(); // update player boards with current scores

				$this->setState_TrapAndCliffAndGarmentCleanup(); // set the state now that moving is complete
		}

		function executeStealGarment($garmentType, $garmentColor)
		{
				self::checkAction( 'stealGarmentClick' ); // Check that this is player's turn and that it is a "possible action" at this game state (see states.inc.php)

				$ostrichHoldingGarmentChosen = $this->getOstrichHoldingGarment($garmentType, $garmentColor); // get the ostrich holding the garment of type and color
				if(!$this->canOstrichBeStolenFrom($ostrichHoldingGarmentChosen))
				{ // this ostrich cannot be stolen from
						throw new BgaUserException( self::_("You can only steal off-colored garments from the ostrich you pushed off a cliff.") );
				}

				$murdererOfOstrichHoldingGarmentChosen = $this->getMurdererOfOstrich($ostrichHoldingGarmentChosen); // get the ostrich who pushed this ostrich off the cliff
				$ostrichWhoseTurnItIs = $this->getOstrichWhoseTurnItIs(); // get the ostrich whose turn it is (the one stealing the garment)

				if($murdererOfOstrichHoldingGarmentChosen != $ostrichWhoseTurnItIs)
				{ // make sure the active player pushed the owner of that garment off a cliff
						throw new BgaUserException( self::_("You can only steal from the ostrich you pushed off a cliff.") );
				}

				$garmentId = $this->getGarmentIdFromType($garmentType, $garmentColor);
				$this->giveGarmentToOstrich($garmentId, $murdererOfOstrichHoldingGarmentChosen); // update the database giving it to the new player and send notification to all players
				$this->resetCliffPusher($ostrichHoldingGarmentChosen);

				// update stealing stats
				$ownerOfOstrichStolenFrom = $this->getOwnerIdOfOstrich($ostrichHoldingGarmentChosen);
				$ownerOfOstrichWhoIsStealing = $this->getOwnerIdOfOstrich($ostrichWhoseTurnItIs);
				self::incStat( 1, 'garments_i_stole', $ownerOfOstrichWhoIsStealing );
				self::incStat( 1, 'garments_stolen_from_me', $ownerOfOstrichStolenFrom );

				$this->setOstrichToNotStealable($ostrichHoldingGarmentChosen); // set that the ostrich who had their garment stolen is no longer stealable so they don't get stolen from again

				$this->updatePlayerScores(); // update player boards with current scores since someone may have gone up or down in points

				if($this->getNextOstrichToStealFromValue() != 0)
				{ // there is another ostrich off the cliff who needs a garment stolen or zigs drawn
						$this->gamestate->nextState( "askStealOrDraw" ); // transition to ask if the player would like to steal a garment or draw a card
				}
				else
				{
						$this->setState_TrapAndCliffAndGarmentCleanup(); // set the state now that moving is complete
				}
		}

		// PURPOSE: The player is saying they would like to end their movement turn now.
		// 					Everyone is down to a max of 1 trap card.
		//          All garments have been replaced.
		//          All ostriches who fell off cliffs have respawned.
		function endMovementTurn()
		{
			  $player_id = self::getActivePlayerId(); // Current Player = player who played the current player action (the one who made the AJAX request). Active Player = player whose turn it is.

			  $this->incrementPlayerRound($player_id); // mark that the player has completed their turn this round

				$this->resetOstrichSpawnOrderForAll(); // reset the order in which ostriches spawn now since a player's turn is over so it's ready for next turn

			  $this->gamestate->nextState( "endTurn" ); // use the endTurn transition to go to the movementTurnCleanup state
		}

		// IN PHASE: ExecuteMovement
		// PURPOSE: The player has a Zag and they just finished moving but they do not want to use their Zag.
		//          We need to figure out which state to put them in.
		function executeNoZag()
		{
				$this->sendCliffFallsToPlayers(); // check if the ostrich moving fell off a cliff, and if so, tell players and update stats
				$this->setState_TrapAndCliffAndGarmentCleanup(); // set the state now that moving is complete
		}

		// IN PHASE: Plan
		// PURPOSE: The player CAN claim a Zag but they choose NOT to claim one.
		function executeSkipZag()
		{
				$currentPlayer = self::getCurrentPlayerId(); // Current Player = player who played the current player action (the one who made the AJAX request). Active Player = player whose turn it is.

				$this->gamestate->setPlayerNonMultiactive( $currentPlayer, "transitionToChooseZig" ); // set this player to not active and use transition chooseZig if all players are done
		}

		function discardTrapCard($trapCardId, $showMessage)
		{
				$messageToSend = "";
				if($showMessage == true)
				{
						$messageToSend = clienttranslate( '${player_name} moved a trap card to the discard pile.' );
				}

				$playerDiscarding = $this->getPlayerIdForTrapCardId($trapCardId); // grab the player ID before we discard the card
				$this->trapCards->moveCard( $trapCardId, 'discard'); // move the card to the discard pile
				$this->resetTrapCardValues($trapCardId);

				self::notifyAllPlayers( 'trapDiscarded', $messageToSend, array(
						'playerDiscarding' => $playerDiscarding,
						'discardedCard' => $trapCardId,
						'player_name' => self::getActivePlayerName()
				) );
		}

		// Called after claimZag to transition to chooseZigPhase and set all players to active.
		function transitionToChooseZig()
		{
				$this->gamestate->nextState( "chooseZig" );
				$this->gamestate->setAllPlayersMultiactive(); // set all players to active since everyone will have to choose a zig
		}

		function chooseFirstState()
		{
				$playersWhoCanClaimAZag = $this->getPlayersWhoCanClaimZag();
				if(count($playersWhoCanClaimAZag) > 0)
				{ // there is at least one player who can claim a zag

						// go to the state where those players can claim their zag
						$this->gamestate->nextState( "claimZag" );
						$this->gamestate->setPlayersMultiactive( $playersWhoCanClaimAZag, "transitionToChooseZig", true ); // set the players who can claim a zag to active
				}
				else
				{ // no players can claim a zag

						// skip the claim zag state and go stright to the choosing zig state
						$this->transitionToChooseZig();
				}
		}

		// This is called automatically when we move into the endMoveTurn phase.
		// IN PHASE: endMoveTurn phase (game)
		// PURPOSE: Perform all the end turn cleanup stuff and then either end the round or go to the next player's movement turn.
		function movementTurnCleanup()
		{
			$currentRound = $this->getGameStateValue("CURRENT_ROUND"); // keep track of the current round somewhere

			$lowestRoundsPlayedByAPlayer = 1000; // will hold the number of rounds played by the player who has played the fewest rounds

			$sql = "SELECT player_turns_taken_this_round ";
			$sql .= "FROM player ";
			$sql .= "WHERE 1";
			$dbres = self::DbQuery( $sql );
			while( $player = mysql_fetch_assoc( $dbres ) )
			{
					if($player['player_turns_taken_this_round'] < $lowestRoundsPlayedByAPlayer)
					{ // this player has played the fewest rounds so far
						  $lowestRoundsPlayedByAPlayer = $player['player_turns_taken_this_round'];
					}
			}

/*
			if($currentRound > 1)
				throw new feException( "Lowest completed is ".$lowestRoundsPlayedByAPlayer." and current round is ".$currentRound);
*/
  		if($lowestRoundsPlayedByAPlayer >= $currentRound)
			{ // all player have gone this round
						$this->gamestate->nextState( "endRound" ); // use the endTurn transition to go to the next player's turn
			}
	  	else
			{ // at least one player still has to take a turn this round
						$nextPlayer = $this->getPlayerWhoseTurnIsNext(); // figure out which player goes next
						$this->gamestate->changeActivePlayer( $nextPlayer ); // set the active player (this cannot be done in an activeplayer game state)

						$this->resetOstrichDistancesAndDirectionsToZigs(); // in case this player has been pushed by the previous player, reset their distance and direction to their zig (NOTE: This does NOT have to be done for the first player turn so it's fine to have it here at the end of a turn)

						$this->setState_PreMovement(); // move into one of the phases of a player taking their turn depending on that player's options
			}

		}

		function endMovementPhase()
		{

		}

		// IN PHASE: endRoundPhase
		// PURPOSE: Do anything we need to do at the end of a round and then ove to the PLAN phase.
		function endRoundCleanup()
		{

			  $this->gamestate->setAllPlayersMultiactive(); // set all players to active

				$this->resetTrapsDrawnThisRound();
				$this->resetDizziness(); // make all ostriches not dizzy
				$this->resetOstrichChosen(); // mark all ostriches as not yet chosen
				$this->resetXValueChoices(); // erase all choices players made for their X value
				$this->resetAllOstrichZigs(); // reset ostrich zig values

				// discard all cards played this rounds
				$playedCards = $this->movementCards->getCardsInLocation( 'played' ); // get all the cards that have been played this round
				foreach( $playedCards as $card )
        { // go through all the cards that were played
					  $card_id = $card['id'];
						$player_id = $card['location_arg'];
						$this->movementCards->moveCard( $card_id, 'discard', $player_id ); // move the card to the discard pile

						self::notifyAllPlayers( 'discardPlayedZig', "", array(
                'player_id' => $player_id
            ) );
				}

				$this->resetDiscardedZigs(); // then any cards that are discarded need to be reset to factory default


				// DRAW NEW CARD
				$players = self::loadPlayersBasicInfos();

				global $g_user;
				$current_player_id = $g_user->get_id();

				foreach( $players as $player_id => $player )
		    {
						$cardsInPlayerHand = $this->movementCards->getCardsInLocation( 'hand', $player_id ); // get the Zigs in this player's hand

						if(count($cardsInPlayerHand) < 4)
						{ // they have under the hand limit
				        $cards = $this->movementCards->pickCards( 1, 'movementCardDeck', $player_id );
								$this->updateZigDrawStats($cards, $player_id); // update the statistics about zig cards being drawn

				        // Notify player about their cards
				        self::notifyPlayer( $player_id, 'drawZigs', '', array(
				            'cards' => $cards
				         ) );
					 }
		    }

				$this->updateTurnOrder(false); // hide the turn direction arrows

				//throw new feException( "CURRENT ROUND IS ".$this->getGameStateValue("CURRENT_ROUND")." and we are increasing it by 1.");
				$this->setGameStateValue("CURRENT_ROUND", $this->getGameStateValue("CURRENT_ROUND")+1); // increment the round by 1

		  	$this->gamestate->nextState( "newRound" ); // use the newRound transition to go to the plan phase
		}


//////////////////////////////////////////////////////////////////////////////
//////////// Game state arguments
////////////

    /*
        Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
        These methods function is to return some additional information that is specific to the current
        game state.
    */

    /*

    Example for game state "MyGameState":

    function argMyGameState()
    {
        // Get some values from the current game situation in database...

        // return values:
        return array(
            'variable1' => $value1,
            'variable2' => $value2,
            ...
        );
    }
    */

		function argGetPlayersWithOstriches()
		{
			return array(
					'allPlayersWithOstriches' => self::getPlayersWithOstriches()
			);
		}

		function argGetOstriches()
		{
				return array(
						'allOstriches' => self::getAllOstrichesAndOwners()
				);
		}

		function argStealableGarments()
		{
				return array(
						'stealableGarments' => self::getStealableGarments()
				);
		}

		function argDiscardableGarments()
		{
				return array(
						'discardableGarments' => self::getDiscardableGarments()
				);
		}

		function argGetValidGarmentSpawnSpaces()
		{
				return array(
						'validGarmentSpawnSpaces' => self::getValidGarmentSpawnSpaces(),
						'playerIdRespawningGarment' => self::getPlayerIdRespawningGarment(),
						'playerNameRespawningGarment' => self::getPlayerNameById(self::getPlayerIdRespawningGarment())
				);
		}

		function argGetGarmentsValidForRespawn()
		{
				return array(
						'garmentsValidForRespawn' => self::getGarmentsValidForRespawn(),
						'playerIdRespawningGarment' => self::getPlayerIdRespawningGarment(),
						'playerNameRespawningGarment' => self::getPlayerNameById(self::getPlayerIdRespawningGarment())
				);
		}

		// Called during executeMove state so we know whether or not to show all the direction buttons.
		function argExecuteMove()
		{

			$ostrich = $this->getOstrichWhoseTurnItIs();

			$sql = "
        	SELECT ostrich_is_dizzy FROM ostrich WHERE ostrich_color='$ostrich'
    	";
    	$dizzy = self::getNonEmptyObjectFromDB( $sql );

			return array(
          //  'isDizzy' => $this->isOstrichDizzy( $this->getOstrichWhoseTurnItIs() )
					'isDizzy' => $dizzy['ostrich_is_dizzy']
        );
		}

		function argReplaceGarmentChooseSpace()
    {
        return array(
            'possibleMoves' => self::getPossibleMoves( self::getActivePlayerId() )
        );
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */

    /*

    Example for game state "MyGameState":

    function stMyGameState()
    {
        // Do some stuff ...

        // (very often) go to another gamestate
        $this->gamestate->nextState( 'some_gamestate_transition' );
    }
    */

//////////////////////////////////////////////////////////////////////////////
//////////// Zombie
////////////

    /*
        zombieTurn:

        This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player ends appropriately
        (ex: pass).

        Important: your zombie code will be called when the player leaves the game. This action is triggered
        from the main site and propagated to the gameserver from a server, not from a browser.
        As a consequence, there is no current player associated to this action. In your zombieTurn function,
        you must _never_ use getCurrentPlayerId() or getCurrentPlayerName(), otherwise it will fail with a "Not logged" error message.
    */

    function zombieTurn( $state, $active_player )
    {
    	$statename = $state['name'];

        if ($state['type'] === "activeplayer") {
            switch ($statename) {
                default:
                    $this->gamestate->nextState( "zombiePass" );
                	break;
            }

            return;
        }

        if ($state['type'] === "multipleactiveplayer") {
            // Make sure player is in a non blocking status for role turn
            $this->gamestate->setPlayerNonMultiactive( $active_player, '' );

            return;
        }

        throw new feException( "Zombie mode not supported at this game state: ".$statename );
    }

///////////////////////////////////////////////////////////////////////////////////:
////////// DB upgrade
//////////

    /*
        upgradeTableDb:

        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.

    */

    function upgradeTableDb( $from_version )
    {
        // $from_version is the current version of this game database, in numerical form.
        // For example, if the game was running with a release of your game named "140430-1345",
        // $from_version is equal to 1404301345

        // Example:
//        if( $from_version <= 1404301345 )
//        {
//            // ! important ! Use DBPREFIX_<table_name> for all tables
//
//            $sql = "ALTER TABLE DBPREFIX_xxxxxxx ....";
//            self::applyDbUpgradeToAllDB( $sql );
//        }
//        if( $from_version <= 1405061421 )
//        {
//            // ! important ! Use DBPREFIX_<table_name> for all tables
//
//            $sql = "CREATE TABLE DBPREFIX_xxxxxxx ....";
//            self::applyDbUpgradeToAllDB( $sql );
//        }
//        // Please add your future database scheme changes here
//
//


    }
}
