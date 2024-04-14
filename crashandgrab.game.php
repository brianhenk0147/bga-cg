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
					"NUMBER_OF_PLAYERS" => 11,
					"TURN_ORDER" => 12,

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

				$this->UP_DIRECTION = 'sun';
				$this->DOWN_DIRECTION = 'meteor';
				$this->LEFT_DIRECTION = 'constellation';
				$this->RIGHT_DIRECTION = 'asteroids';

				// colors
				$this->REDCOLOR = "f6033b";
				$this->YELLOWCOLOR = "fedf3d";
				$this->BLUECOLOR = "0090ff";
				$this->GREENCOLOR = "01b508";
				$this->PURPLECOLOR = "b92bba";
				$this->GRAYCOLOR = "c9d2db";


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
				self::setGameStateInitialValue( 'TURN_ORDER', 2 ); // 0=CLOCKWISE, 1=COUNTER-CLOCKWISE, 2=UNKNOWN

        // Init game statistics
        // (note: statistics used in this file must be defined in your stats.inc.php file)
        //self::initStat( 'table', 'table_teststat1', 0 );    // Init a table statistics
        //self::initStat( 'player', 'player_teststat1', 0 );  // Init a player statistics (for all players)


				$this->initializeStats();

				//$numberOfPlayers = 5; // HARDCODE FOR NOW SINCE MORE PLAYERS BREAKS IT

				$numberOfSaucers = $this->getNumberOfSaucers();
				$this->initializeBoard($numberOfSaucers); // randomly choose which tiles to use depending on number of players

				$this->initializeSaucers();

				$this->initializeCrewmembers();

				$this->initializeStartingBonuses(count($players));

				$this->initializeMoveCards();

				$this->dealMoveCards();

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
				$saucers = $this->getAllSaucers();
				$result['hand'] = null;
				foreach( $saucers as $saucer )
				{ // go through each saucer
						$saucerColor = $saucer['ostrich_color'];

	    			self::warn("<b>Saucer Color:</b> $saucerColor"); // log to sql database

						if(is_null($result['hand']))
						{ // first saucer
							self::warn("<b>HAND NULL</b>"); // log to sql database

								$result['hand'] = $this->movementCards->getCardsInLocation( $saucerColor ); // get the cards for this saucer
								//$result['hand'] = $this->movementCards->getCardsInLocation( 'hand' ); // get the cards for this saucer
  					}
						else
						{ // they had a second saucer
														self::warn("<b>HAND not NULL</b>"); // log to sql database
								//array_merge($result['hand'], $this->movementCards->getCardsInLocation( 'hand_{$saucerColor}' ) ); // merge their other saucer with this saucer
								$result['hand'] = array_merge($result['hand'], $this->movementCards->getCardsInLocation( $saucerColor ) ); // merge their other saucer with this saucer
						}
				}



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

				$result['turnOrder'] = $this->getGameStateValue("TURN_ORDER");

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

				$maxGarments = ($this->getNumberOfPlayers() * 3) + 1; // get the maximum number of garments that can be acquired before winning
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
				// TABLE STATS
				self::initStat( 'table', 'turns_number', 0 );

				// PLAYER STATS
				self::initStat( 'player', 'turns_number', 0 );
				self::initStat( 'player', 'rounds_started', 0 );
				self::initStat( 'player', 'zags_claimed', 0 );
				self::initStat( 'player', 'traps_drawn', 0 ); // number of traps the player has drawn

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

		function initializeMoveCards()
		{
				$numberOfSaucers = $this->getNumberOfSaucers();
				// Create Movement Cards
				// type: clockwise, counterclockwise
				// type_arg: 0=X, 1=1, 2=2, 3=3
				$movementCardsList = array(
						array( 'type' => 'unused', 'type_arg' => 0,'nbr' => $numberOfSaucers),
						array( 'type' => 'unused', 'type_arg' => 1,'nbr' => $numberOfSaucers),
						array( 'type' => 'unused', 'type_arg' => 2,'nbr' => $numberOfSaucers)
				);

				$this->movementCards->createCards( $movementCardsList, 'movementCardDeck' ); // create the deck
		}

		// Deal one of each card to each player.
		function dealMoveCards()
		{
				$xCards = $this->movementCards->getCardsOfType( 'unused', 0 );
				$twoCards = $this->movementCards->getCardsOfType( 'unused', 1 );
				$threeCards = $this->movementCards->getCardsOfType( 'unused', 2 );

				$i = 0;
				$allSaucers = $this->getAllSaucers();
				foreach( $allSaucers as $saucer )
				{ // go through each saucer
						$saucerColor = $saucer['ostrich_color'];
						$saucerOwner = $saucer['ostrich_owner'];

						// grab one of each card type to give to this saucer
						$myX = array_values($xCards)[$i];
						$myX_id = $myX['id'];
						$myTwo = array_values($twoCards)[$i];
						$myTwo_id = $myTwo['id'];
						$myThree = array_values($threeCards)[$i];
						$myThree_id = $myThree['id'];

						// set the card_location to the saucer color
						$this->movementCards->moveCard( $myX_id, $saucerColor );
						$this->movementCards->moveCard( $myTwo_id, $saucerColor );
						$this->movementCards->moveCard( $myThree_id, $saucerColor );
/*
						$cards = [
							"x" => $cardX,
							"two" => $card2,
							"three" => $card3,
						];

						self::notifyPlayer( $saucerOwner, 'newZigs', '', array(
								'cards' => $cards
						 ) );
*/
						 $i++;
				}
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

		function initializeCrewmembers()
		{
				$locX = 0;
				$locY = 0;
				$location = 'pile';

				$allSaucers = $this->getAllSaucers();
				foreach( $allSaucers as $saucer )
				{ // go through each saucer
						$color = $saucer['ostrich_color'];
						$type = 0; // insert the HEAD piece

						$sqlGarment = "INSERT INTO garment (garment_x,garment_y,garment_location,garment_color,garment_type) VALUES ";
						$sqlGarment .= "(".$locX.",".$locY.",'".$location."','".$color."',".$type.") ";
						//echo "locX ($locX) locY ($locY) location($location) color ($color) type ($type) <br>";
						//self::DbQUery("INSERT INTO garment (garment_x,garment_y,garment_location,garment_color,garment_type) VALUES (0,0,'pile','f6033b',0)");
						self::DbQuery( $sqlGarment );

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

		function initializeStartingBonuses()
		{
				$activePlayerId = self::getActivePlayerId(); // Current Player = player who played the current player action (the one who made the AJAX request). In general, only use this in multiplayer states. Active Player = player whose turn it is.
				//throw new feException( "this player is going first: $activePlayer");

				$playerIdGoingSecond = $this->getPlayerAfter($activePlayerId);
				$playerIdGoingFirst = $this->getPlayerBefore($playerIdGoingSecond);

//throw new feException( "playerIdGoingFirst: $playerIdGoingFirst playerIdGoingSecond: $playerIdGoingSecond");


				if($this->getNumberOfPlayers() == 2)
				{ // 2 players (4 saucers)

						// give Probe to the player going first
						$playerGoingFirstSaucers = $this->getSaucersForPlayer($playerIdGoingFirst);
						$saucerColor = '';
						foreach( $playerGoingFirstSaucers as $saucer )
						{ // go through each saucer
								if($saucerColor == '')
								{ // this is their first Saucer
										$saucerColor = $saucer['ostrich_color'];
										$this->giveProbe($saucerColor); // saucer of player going first gets the Probe

										$this->gamestate->changeActivePlayer( $playerIdGoingFirst ); // make probe owner go first in turn order
								}
								else
								{ // this is their second Saucer
										$saucerColor = $saucer['ostrich_color'];
										$this->locatePilot($saucerColor); // locate this Saucer's Pilot Crewmember = $saucer['ostrich_color'];
								}
						}

						$playerGoingSecondSaucers = $this->getSaucersForPlayer($playerIdGoingSecond);
						$saucerColor = '';
						foreach( $playerGoingSecondSaucers as $saucer )
						{ // go through each saucer
								if($saucerColor == '')
								{ // this is their first Saucer
										$saucerColor = $saucer['ostrich_color'];
										$this->takeBooster($saucerColor); // give this Saucer a Booster
								}
								else
								{ // this is their second Saucer
										$saucerColor = $saucer['ostrich_color'];
										$this->locatePilot($saucerColor); // locate this Saucer's Pilot Crewmember = $saucer['ostrich_color'];
								}
						}
				}
				else
				{ // all other player counts (1 Saucer per player)

						// give Probe to the player going first
						$playerGoingFirstSaucers = $this->getSaucersForPlayer($playerIdGoingFirst);
						foreach( $playerGoingFirstSaucers as $saucer )
						{ // go through each saucer
								$saucerColor = $saucer['ostrich_color'];

								//$this->locatePilot($saucerColor); // locate this Saucer's Pilot Crewmember = $saucer['ostrich_color'];
								$this->giveProbe($saucerColor); // saucer of player going first gets the Probe

								$this->gamestate->changeActivePlayer( $playerIdGoingFirst ); // make probe owner go first in turn order
						}

						$playerGoingSecondSaucers = $this->getSaucersForPlayer($playerIdGoingSecond);
						foreach( $playerGoingSecondSaucers as $saucer )
						{ // go through each saucer
								$saucerColor = $saucer['ostrich_color'];

								if($this->getNumberOfPlayers() == 3)
								{
										$this->locatePilot($saucerColor); // locate this Saucer's Pilot Crewmember
								}
								else
								{
										$this->takeBooster($saucerColor); // give this Saucer a Booster
								}
						}

						$allSaucers = $this->getAllSaucers();
						foreach( $allSaucers as $saucer )
						{ // go through each saucer
								$saucerColor = $saucer['ostrich_color'];
								$saucerOwner = $saucer['ostrich_owner'];

								if($saucerOwner == "$playerIdGoingFirst" )
								{
										// skip the first and second player
										//throw new feException( "playerIdGoingFirst: $playerIdGoingFirst playerIdGoingSecond: $playerIdGoingSecond");
								}
								else if($saucerOwner == $playerIdGoingSecond)
								{

								}
								else
								{ // this is one of the saucers we haven't set yet
											$this->locatePilot($saucerColor); // locate this Saucer's Pilot Crewmember
								}
						}


					// saucer who is going first gets the Probe
					//   2 players: other saucer
					// saucer who is going second:
					//   4+ saucers: take a Booster
					//   3 saucers: locate a Pilot
					// all other saucers locate a Pilot

				}
		}

		function initializeSaucers()
		{
				$startingLocations = $this->getAllCrashSites(); // get all crash sites
				shuffle($startingLocations); // randomize the order

				//$count = count($startingLocations);
				//throw new feException( "Count starting locations:$count");

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

						self::DbQuery( $sqlOstrich );

						if(($this->getNumberOfPlayers() == 2) && $locationListIndex == 0)
						{ // add a second saucer for this first player
								$locX = $startingLocations[$locationListIndex+2]['board_x'];
								$locY = $startingLocations[$locationListIndex+2]['board_y'];
								$playerColor = $this->getUniqueSaucerColor(); // get a valid color that doesn't match one already assigned to a player
								$sqlOstrich = "INSERT INTO ostrich (ostrich_x,ostrich_y,ostrich_color,ostrich_owner,ostrich_has_zag,ostrich_is_chosen, ostrich_has_crown) VALUES ";
								$sqlOstrich .= "(".$locX.",".$locY.",'".$playerColor."',".$playerId.",0,0,0) ";
								self::DbQuery( $sqlOstrich );

						}
						else if(($this->getNumberOfPlayers() == 2) && $locationListIndex == 1)
						{ // add a second saucer for the second player
								$locX = $startingLocations[$locationListIndex+2]['board_x'];
								$locY = $startingLocations[$locationListIndex+2]['board_y'];
								$playerColor = $this->getUniqueSaucerColor(); // get a valid color that doesn't match one already assigned to a player
								$sqlOstrich = "INSERT INTO ostrich (ostrich_x,ostrich_y,ostrich_color,ostrich_owner,ostrich_has_zag,ostrich_is_chosen, ostrich_has_crown) VALUES ";
								$sqlOstrich .= "(".$locX.",".$locY.",'".$playerColor."',".$playerId.",0,0,0) ";
								self::DbQuery( $sqlOstrich );
						}

						$locationListIndex++; // go to the next starting location
				}

		}

		function getUniqueSaucerColor()
		{
				$possibleColors = array( "f6033b", "01b508", "0090ff", "fedf3d", "b92bba", "c9d2db" );

				// remove colors that belong to a player
				$sqlGetPlayerColors = "SELECT player_color ";
				$sqlGetPlayerColors .= "FROM player ";
				$sqlGetPlayerColors .= "WHERE 1";
				$usedPlayerColors = self::DbQuery( $sqlGetPlayerColors );
				while( $player = mysql_fetch_assoc( $usedPlayerColors ) )
				{ // go through PLAYER colors
						$playerColor = $player['player_color']; // get the color this player was assigned
						if (($key = array_search($playerColor, $possibleColors)) !== false)
						{ // get the index that holds this color
	    					unset($possibleColors[$key]); // remove this color from the list of possible colors
								//$possibleColors[$key] = "used";
						}
				}

				// remove colors that already belong to another ostrich
				$sqlGetSaucerColors = "SELECT ostrich_color ";
				$sqlGetSaucerColors .= "FROM ostrich ";
				$sqlGetSaucerColors .= "WHERE 1";
				$usedColors = self::DbQuery( $sqlGetSaucerColors );
				while( $ostrich = mysql_fetch_assoc( $usedColors ) )
				{
						$playerColor = $ostrich['ostrich_color']; // get the color this player was assigned
						if (($key = array_search($playerColor, $possibleColors)) !== false)
						{ // get the index that holds this color
	    					unset($possibleColors[$key]); // remove this color from the list of possible colors
								//$possibleColors[$key] = "used";
						}
				}

				// take a random color that remains
				shuffle($possibleColors); // randomly order the colors
				foreach($possibleColors as $color)
				{
						//if($color != "used")
						if($color)
						{ // this hasn't already been used
								return $color; // return one of them
						}
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

		function convertColorToText($playerColor)
		{
				switch($playerColor)
				{
						case $this->REDCOLOR:
							return clienttranslate('RED');
						case $this->YELLOWCOLOR:
							return clienttranslate('YELLOW');
						case $this->BLUECOLOR:
							return clienttranslate('BLUE');
						case $this->GREENCOLOR:
							return clienttranslate('GREEN');
						case $this->PURPLECOLOR:
							return clienttranslate('PURPLE');
						case $this->GRAYCOLOR:
							return clienttranslate('GRAY');
				}

				return clienttranslate('UNKNOWN');
		}

		function convertFriendlyColorToHex($colorAsFriendlyText)
		{
				switch($colorAsFriendlyText)
				{
						case "RED":
							return $this->REDCOLOR;
						case "YELLOW":
							return $this->YELLOWCOLOR;
						case "BLUE":
							return $this->BLUECOLOR;
						case "GREEN":
							return $this->GREENCOLOR;
						case "PURPLE":
							return $this->PURPLECOLOR;
						case "GRAY":
							return $this->GRAYCOLOR;
				}

				return "";
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

		function getAllCrashSites()
		{
				return self::getObjectListFromDB( "SELECT board_x, board_y
																					 FROM board
																					 WHERE board_space_type='1' OR
 																					 board_space_type='2' OR
																					 board_space_type='3' OR
																					 board_space_type='4' OR
																					 board_space_type='5' OR
																					 board_space_type='6' OR
																					 board_space_type='7' OR
																					 board_space_type='8' OR
 																					 board_space_type='9' OR
																					 board_space_type='10' OR
 																					 board_space_type='11' OR
																					 board_space_type='12'" );
		}

		function getSaucerGoFirstButtons()
		{
				$result = array();

				$currentPlayer = self::getCurrentPlayerId(); // Current Player = player who played the current player action (the one who made the AJAX request). Active Player = player whose turn it is.

				// all saucers for this player
				$sqlGetSaucerColors = "SELECT ostrich_color ";
				$sqlGetSaucerColors .= "FROM ostrich ";
				$sqlGetSaucerColors .= "WHERE ostrich_owner=$currentPlayer";
				$usedColors = self::DbQuery( $sqlGetSaucerColors );

				$index = 0;
				while( $ostrich = mysql_fetch_assoc( $usedColors ) )
				{ // go through the saucers owned by this player
						$playerColor = $ostrich['ostrich_color']; // get the color this player was assigned
						$playerColorFriendly = $this->convertColorToText($playerColor);

						$result[$index] = array();
						$result[$index]['buttonLabel'] = $playerColorFriendly;
						$result[$index]['hoverOverText'] = '';
						$result[$index]['actionName'] = 'selectSaucerToGoFirst';
						$result[$index]['isDisabled'] = false;
						$result[$index]['makeRed'] = false;

						$index++;
				}

				// remove any that have already gone



				return $result;
		}

		function getMoveCardButtons()
		{
				$result = array();

				$result[0] = array();
				$result[0]['buttonLabel'] = '2';
				$result[0]['hoverOverText'] = '';
				$result[0]['actionName'] = 'moveCardDistance';
				$result[0]['isDisabled'] = false;
				$result[0]['makeRed'] = false;

				$result[1] = array();
				$result[1]['buttonLabel'] = '3';
				$result[1]['hoverOverText'] = '';
				$result[1]['actionName'] = 'moveCardDistance';
				$result[1]['isDisabled'] = false;
				$result[1]['makeRed'] = false;

				$result[2] = array();
				$result[2]['buttonLabel'] = clienttranslate( 'X' ); // we need to translate this one
				$result[2]['hoverOverText'] = '';
				$result[2]['actionName'] = 'moveCardDistance';
				$result[2]['isDisabled'] = true;
				$result[2]['makeRed'] = false;

				$result[3] = array();
				$result[3]['buttonLabel'] = 'sun';
				$result[3]['hoverOverText'] = '';
				$result[3]['actionName'] = 'moveCardDirection';
				$result[3]['isDisabled'] = false;
				$result[3]['makeRed'] = false;

				$result[4] = array();
				$result[4]['buttonLabel'] = 'asteroids';
				$result[4]['hoverOverText'] = '';
				$result[4]['actionName'] = 'moveCardDirection';
				$result[4]['isDisabled'] = true;
				$result[4]['makeRed'] = false;

				$result[5] = array();
				$result[5]['buttonLabel'] = 'meteor';
				$result[5]['hoverOverText'] = '';
				$result[5]['actionName'] = 'moveCardDirection';
				$result[5]['isDisabled'] = false;
				$result[5]['makeRed'] = false;

				$result[6] = array();
				$result[6]['buttonLabel'] = 'constellation';
				$result[6]['hoverOverText'] = '';
				$result[6]['actionName'] = 'moveCardDirection';
				$result[6]['isDisabled'] = false;
				$result[6]['makeRed'] = false;

				$result[7] = array();
				$result[7]['buttonLabel'] = clienttranslate( 'Confirm' ); // we need to translate this one
				$result[7]['hoverOverText'] = '';
				$result[7]['actionName'] = 'confirmMoveCard';
				$result[7]['isDisabled'] = true;
				$result[7]['makeRed'] = true;

				return $result;
		}

		// Gets only the moves for a specific saucer's last move to use to show the available
		// moves when landing on an Accelerator or using a Booster.
		function getSaucerAcceleratorAndBoosterMoves()
		{
				$result = array();

				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				//throw new feException( "saucer whose turn it is:$saucerWhoseTurnItIs player whose turn it is:$playerWhoseTurnItIs" );

				$saucerWhoseTurnItIsDetails = self::getObjectListFromDB( "SELECT ostrich_color, ostrich_owner
																					 FROM ostrich WHERE ostrich_color='$saucerWhoseTurnItIs' ORDER BY ostrich_owner" );
//$allSaucersCount = count($allSaucers);
//throw new feException( "allSaucers Count:$allSaucersCount" );
				$currentPlayerId = 0;
				foreach( $saucerWhoseTurnItIsDetails as $saucer )
				{
						$owner = $saucer['ostrich_owner'];
						$color = $saucer['ostrich_color'];

						if($owner != $currentPlayerId)
						{ // this is a new player we haven't seen yet
								$currentPlayerId = $owner; // save that we're on this owner so we know when we get to a new owner
								$result[$owner] = array(); // create a new array for this player
						}

						$result[$owner][$color] = array(); // every saucer needs an array of values

						$getLastSaucerDistanceType = $this->getSaucerDistanceType($color);
						$movesForSaucer = $this->getMovesForSaucer($color, $getLastSaucerDistanceType);
						foreach( $movesForSaucer as $cardType => $moveCard )
						{ // go through each move card for this saucer

								$directionsWithSpaces = $moveCard['directions'];
								//$count = count($spacesForCard);
								//throw new feException( "spacesForCard Count:$count" );

								$result[$owner][$color][$cardType] = array(); // make an array for the list of spaces available using this card

								foreach( $directionsWithSpaces as $direction => $directionWithSpaces )
								{ // go through each space

										$result[$owner][$color][$cardType][$direction] = array(); // we need an array for the spaces we get with this card type and direction

										foreach( $directionWithSpaces as $space )
										{ // go through each space
												$column = $space['column'];
												$row = $space['row'];

												$formattedSpace = $column.'_'.$row;

												array_push($result[$owner][$color][$cardType][$direction], $formattedSpace);
										}
								}
						}
				}

				return $result;
		}

		function getAllPlayerSaucerMoves()
		{
				$result = array();

				$allSaucers = self::getObjectListFromDB( "SELECT ostrich_color, ostrich_owner
																					 FROM ostrich ORDER BY ostrich_owner" );
//$allSaucersCount = count($allSaucers);
//throw new feException( "allSaucers Count:$allSaucersCount" );
				$currentPlayerId = 0;
				foreach( $allSaucers as $saucer )
				{
						$owner = $saucer['ostrich_owner'];
						$color = $saucer['ostrich_color'];

						if($owner != $currentPlayerId)
						{ // this is a new player we haven't seen yet
								$currentPlayerId = $owner; // save that we're on this owner so we know when we get to a new owner
								$result[$owner] = array(); // create a new array for this player
						}

						$result[$owner][$color] = array(); // every ostrich needs an array of values

						$movesForSaucer = $this->getMovesForSaucer($color);
						foreach( $movesForSaucer as $cardType => $moveCard )
						{ // go through each move card for this saucer

								$directionsWithSpaces = $moveCard['directions'];
								//$count = count($spacesForCard);
								//throw new feException( "spacesForCard Count:$count" );

								$result[$owner][$color][$cardType] = array(); // make an array for the list of spaces available using this card

								foreach( $directionsWithSpaces as $direction => $directionWithSpaces )
								{ // go through each space

										$result[$owner][$color][$cardType][$direction] = array(); // we need an array for the spaces we get with this card type and direction

										foreach( $directionWithSpaces as $space )
										{ // go through each space
												$column = $space['column'];
												$row = $space['row'];

												$formattedSpace = $column.'_'.$row;

												array_push($result[$owner][$color][$cardType][$direction], $formattedSpace);
										}
								}
						}
				}

				return $result;
		}

		function getMovesForSaucer($color, $specificMoveCard='')
		{
				$result = array();

				$availableMoveCards = $this->getAvailableMoveCardsForSaucer($color);

				//$availableMoveCardsCount = count($availableMoveCards);
				//throw new feException( "availableMoveCards Count:$availableMoveCardsCount" );

				$arrayIndex = 0;
				foreach( $availableMoveCards as $distanceType )
				{ // 0, 1, 2
						if($specificMoveCard == '' || $distanceType == $specificMoveCard)
						{ // we are only looking for the moves for a specific distance (because this is for an Accelerator or Booster)
								$result[$distanceType] = array(); // this saucer, this card

								$result[$distanceType]['directions'] = array(); // list of spaces for this saucer, this card

								$saucerX = $this->getSaucerXLocation($color); // this saucer's starting column
								$saucerY = $this->getSaucerYLocation($color); // this saucer's starting row


								$result[$distanceType]['directions']['sun'] = $this->getMoveDestinationsInDirection($saucerX, $saucerY, $distanceType, 'sun'); // destinations for this saucer, this card, in the sun direction
								$result[$distanceType]['directions']['asteroids'] = $this->getMoveDestinationsInDirection($saucerX, $saucerY, $distanceType, 'asteroids'); // destinations for this saucer, this card, in the asteroids direction
								$result[$distanceType]['directions']['meteor'] = $this->getMoveDestinationsInDirection($saucerX, $saucerY, $distanceType, 'meteor'); // destinations for this saucer, this card, in the meteor direction
								$result[$distanceType]['directions']['constellation'] = $this->getMoveDestinationsInDirection($saucerX, $saucerY, $distanceType, 'constellation'); // destinations for this saucer, this card, in the constellation direction
						}
				}

				//$count = count($result);
				//throw new feException( "result Count:$count" );

				return $result;
		}

		function getMoveDestinationsInDirection($startColumn, $startRow, $distanceType, $direction)
		{
				$result = array();

				switch($distanceType)
				{ // 0=X, 1=2, 2=3
						case 0: // X

								$row = $startRow;
								$column = $startColumn;

								$space = array();
								$space['row'] = $row;
								$space['column'] = $column;
								array_push($result, $space); // add this space to the list of move destinations

								switch($direction)
								{
										case $this->UP_DIRECTION:
												return $this->getAllSpacesInColumnUp($startRow, $startColumn);
										case $this->DOWN_DIRECTION:
												return $this->getAllSpacesInColumnDown($startRow, $startColumn);
										break;

										case $this->RIGHT_DIRECTION:
												return $this->getAllSpacesInRowRight($startColumn, $startRow);
										case $this->LEFT_DIRECTION:
												return $this->getAllSpacesInRowLeft($startColumn, $startRow);
										break;

										default:
											throw new feException( "Invalid direction type: $direction");
										break;
								}

						break;
						case 1: // 2
						case 2: // 3
								$offset = 2;
								if($distanceType == 2)
										$offset = 3;

								switch($direction)
								{
										case 'sun':
										$row = $startRow - $offset;
										if($row < 0)
										{ // went off the board
												$row = 0;
										}

										$column = $startColumn;

										break;

										case 'asteroids':
										$row = $startRow;

										$column = $startColumn + $offset;
										$maxColumns = $this->getMaxColumns();
										if($column > $maxColumns)
										{
												$column = $maxColumns;
										}
										break;

										case 'meteor':
										$row = $startRow + $offset;

										$maxRows = $this->getMaxRows();
										if($row > $maxRows)
										{
												$row = $maxRows;
										}

										$column = $startColumn;
										break;

										case 'constellation':
										$row = $startRow;

										$column = $startColumn - $offset;
										if($column < 0)
										{ // went off the board
												$column = 0;
										}
										break;

										default:
											throw new feException( "Invalid direction type: $direction");
										break;
								}
								$space = array();
								$space['row'] = $row;
								$space['column'] = $column;
								array_push($result, $space); // add this space to the list of move destinations
						break;
						default:
							throw new feException( "Invalid distance type: $distanceType");
						break;
				}

				return $result;
		}

		function getAvailableMoveCardsForSaucer($color)
		{
				$result = array();

				$moveCardsForSaucer = $this->getAllMoveCardsForSaucer($color);
				//$moveCardsForSaucerCount = count($moveCardsForSaucer);
				//throw new feException( "moveCardsForSaucer Count:$moveCardsForSaucerCount" );

				$arrayIndex = 0;
				foreach( $moveCardsForSaucer as $card )
				{
						$distanceType = $card['card_type_arg']; // 0, 1, 2
						$usedOrUnused = $card['card_type']; // used, unused


						if($usedOrUnused != 'used')
						{ // this card was not used last round
								$result[$arrayIndex] = $distanceType;

								$arrayIndex++;
						}
				}

				return $result;
		}

		function getAllMoveCardsForSaucer($color)
		{
				return self::getObjectListFromDB( "SELECT * FROM movementCards WHERE card_location='$color'" );
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

		function getAllCrewmembers()
		{
				return self::getObjectListFromDB( "SELECT *
																					 FROM garment ORDER BY garment_color" );
		}

		function getAllSaucers()
		{
				return self::getObjectListFromDB( "SELECT *
																					 FROM ostrich ORDER BY ostrich_owner" );
		}

		function getAllSaucersByPlayer()
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
				$garmentChoosersOstriches = $this->getSaucersForPlayer($garmentChooser); // get all of the ostriches belonging to the player

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
									$ostrichX = $this->getSaucerXLocation($ostrich['ostrich_color']);
									$ostrichY = $this->getSaucerYLocation($ostrich['ostrich_color']);

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
										$ostrichX = $this->getSaucerXLocation($ostrich['ostrich_color']);
										$ostrichY = $this->getSaucerYLocation($ostrich['ostrich_color']);

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
				$ostrichX = $this->getSaucerXLocation($ostrich);
				$ostrichY = $this->getSaucerYLocation($ostrich);

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

				$firstTileX = 1;
				$firstTileY = 1;
				$secondTileX = 5;
				$secondTileY = 1;
				$thirdTileX = 1;
				$thirdTileY = 5;
				$fourthTileX = 5;
				$fourthTileY = 5;
				$seventhTileX = 0; // this will be overwritten
				$eighthTileY = 0; // this will be overwritten

				if($this->getNumberOfPlayers() == 5)
				{ // we need to extend the board by 1
						$secondTileX = 6;
						$thirdTileY = 6;
						$fourthTileX = 6;
						$fourthTileY = 6;
						$seventhTileX = 6;
						$eighthTileY = 6;
				}
				elseif($this->getNumberOfPlayers() == 6)
				{ // we need to extend the board by 2
						$secondTileX = 7;
						$thirdTileY = 7;
						$fourthTileX = 7;
						$fourthTileY = 7;
						$seventhTileX = 7;
						$eighthTileY = 7;
				}

				switch($tilePosition)
				{
						case 1:
							$tileNumber = self::getUniqueValueFromDb("SELECT tile_number FROM tile WHERE tile_x=$firstTileX AND tile_y=$firstTileY LIMIT 1");
							break;
						case 2:
							$tileNumber = self::getUniqueValueFromDb("SELECT tile_number FROM tile WHERE tile_x=$secondTileX AND tile_y=$secondTileY LIMIT 1");
							break;
						case 3:
							$tileNumber = self::getUniqueValueFromDb("SELECT tile_number FROM tile WHERE tile_x=$thirdTileX AND tile_y=$thirdTileY LIMIT 1");
							break;
						case 4:
							$tileNumber = self::getUniqueValueFromDb("SELECT tile_number FROM tile WHERE tile_x=$fourthTileX AND tile_y=$fourthTileY LIMIT 1");
							break;
						case 5:
							$tileNumber = self::getUniqueValueFromDb("SELECT tile_number FROM tile WHERE tile_x=1 AND tile_y=5 LIMIT 1");
							break;
						case 6:
							$tileNumber = self::getUniqueValueFromDb("SELECT tile_number FROM tile WHERE tile_x=5 AND tile_y=1 LIMIT 1");
							break;
						case 7:
							$tileNumber = self::getUniqueValueFromDb("SELECT tile_number FROM tile WHERE tile_x=$seventhTileX AND tile_y=5 LIMIT 1");
							break;
						case 8:
							$tileNumber = self::getUniqueValueFromDb("SELECT tile_number FROM tile WHERE tile_x=5 AND tile_y=$eighthTileY LIMIT 1");
							break;
						case 9:
							$tileNumber = self::getUniqueValueFromDb("SELECT tile_number FROM tile WHERE tile_x=5 AND tile_y=5 LIMIT 1");
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

				$firstTileX = 1;
				$firstTileY = 1;
				$secondTileX = 5;
				$secondTileY = 1;
				$thirdTileX = 1;
				$thirdTileY = 5;
				$fourthTileX = 5;
				$fourthTileY = 5;
  			$seventhTileX = 0; // this will be overwritten
				$eighthTileY = 0; // this will be overwritten

				if($this->getNumberOfPlayers() == 5)
				{ // we need to extend the board by 1
						$secondTileX = 6;
						$thirdTileY = 6;
						$fourthTileX = 6;
						$fourthTileY = 6;
						$seventhTileX = 6;
						$eighthTileY = 6;
				}
				elseif($this->getNumberOfPlayers() == 6)
				{ // we need to extend the board by 2
						$secondTileX = 7;
						$thirdTileY = 7;
						$fourthTileX = 7;
						$fourthTileY = 7;
						$seventhTileX = 7;
						$eighthTileY = 7;
				}

				switch($tilePosition)
				{
						case 1:
							$sideAsInt = self::getUniqueValueFromDb("SELECT tile_use_side_A FROM tile WHERE tile_x=$firstTileX AND tile_y=$firstTileY LIMIT 1");
							break;
						case 2:
							$sideAsInt = self::getUniqueValueFromDb("SELECT tile_use_side_A FROM tile WHERE tile_x=$secondTileX AND tile_y=$secondTileY LIMIT 1");
							break;
						case 3:
							$sideAsInt = self::getUniqueValueFromDb("SELECT tile_use_side_A FROM tile WHERE tile_x=$thirdTileX AND tile_y=$thirdTileY LIMIT 1");
							break;
						case 4:
							$sideAsInt = self::getUniqueValueFromDb("SELECT tile_use_side_A FROM tile WHERE tile_x=$fourthTileX AND tile_y=$fourthTileY LIMIT 1");
							break;
						case 5:
							$sideAsInt = self::getUniqueValueFromDb("SELECT tile_use_side_A FROM tile WHERE tile_x=1 AND tile_y=5 LIMIT 1");
							break;
						case 6:
							$sideAsInt = self::getUniqueValueFromDb("SELECT tile_use_side_A FROM tile WHERE tile_x=5 AND tile_y=1 LIMIT 1");
							break;
						case 7:
							$sideAsInt = self::getUniqueValueFromDb("SELECT tile_use_side_A FROM tile WHERE tile_x=$seventhTileX AND tile_y=5 LIMIT 1");
							break;
						case 8:
							$sideAsInt = self::getUniqueValueFromDb("SELECT tile_use_side_A FROM tile WHERE tile_x=5 AND tile_y=$eighthTileY LIMIT 1");
							break;
						case 9:
							$sideAsInt = self::getUniqueValueFromDb("SELECT tile_use_side_A FROM tile WHERE tile_x=5 AND tile_y=5 LIMIT 1");
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

				$firstTileX = 1;
				$firstTileY = 1;
				$secondTileX = 5;
				$secondTileY = 1;
				$thirdTileX = 1;
				$thirdTileY = 5;
				$fourthTileX = 5;
				$fourthTileY = 5;
				$seventhTileX = 0; // this will be overwritten
				$eighthTileY = 0; // this will be overwritten

				if($this->getNumberOfPlayers() == 5)
				{ // we need to extend the board by 1
						$secondTileX = 6;
						$thirdTileY = 6;
						$fourthTileX = 6;
						$fourthTileY = 6;
						$seventhTileX = 6;
						$eighthTileY = 6;
				}
				elseif($this->getNumberOfPlayers() == 6)
				{ // we need to extend the board by 2
						$secondTileX = 7;
						$thirdTileY = 7;
						$fourthTileX = 7;
						$fourthTileY = 7;
						$seventhTileX = 7;
						$eighthTileY = 7;
				}

				switch($tilePosition)
				{
						case 1:
							$rotation = self::getUniqueValueFromDb("SELECT tile_degree_rotation FROM tile WHERE tile_x=$firstTileX AND tile_y=$firstTileY LIMIT 1");
							break;
						case 2:
							$rotation = self::getUniqueValueFromDb("SELECT tile_degree_rotation FROM tile WHERE tile_x=$secondTileX AND tile_y=$secondTileY LIMIT 1");
							break;
						case 3:
							$rotation = self::getUniqueValueFromDb("SELECT tile_degree_rotation FROM tile WHERE tile_x=$thirdTileX AND tile_y=$thirdTileY LIMIT 1");
							break;
						case 4:
							$rotation = self::getUniqueValueFromDb("SELECT tile_degree_rotation FROM tile WHERE tile_x=$fourthTileX AND tile_y=$fourthTileY LIMIT 1");
							break;
						case 5:
							$rotation = self::getUniqueValueFromDb("SELECT tile_degree_rotation FROM tile WHERE tile_x=1 AND tile_y=5 LIMIT 1");
							break;
						case 6:
							$rotation = self::getUniqueValueFromDb("SELECT tile_degree_rotation FROM tile WHERE tile_x=5 AND tile_y=1 LIMIT 1");
							break;
						case 7:
							$rotation = self::getUniqueValueFromDb("SELECT tile_degree_rotation FROM tile WHERE tile_x=$seventhTileX AND tile_y=5 LIMIT 1");
							break;
						case 8:
							$rotation = self::getUniqueValueFromDb("SELECT tile_degree_rotation FROM tile WHERE tile_x=5 AND tile_y=$eighthTileY LIMIT 1");
							break;
						case 9:
							$rotation = self::getUniqueValueFromDb("SELECT tile_degree_rotation FROM tile WHERE tile_x=5 AND tile_y=5 LIMIT 1");
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

		function getNumberOfRowsFromTilePosition($tilePosition)
		{
				switch($tilePosition)
				{
						case 1: // 4x4
								return 4;
							break;
						case 2: // 4x4
								return 4;
							break;
						case 3: // 4x4
								return 4;
							break;
						case 4: // 4x4
								return 4;
							break;
						case 5: // left-most 4x1 or 4x2
							if($this->getNumberOfPlayers() == 6)
							{
								return 2;
							}
							else
							{
								return 1;
							}
						break;
						case 6: // top-most 4x1 or 4x2
								return 4;
						break;
						case 7: // right-most 4x1 or 4x2
							if($this->getNumberOfPlayers() == 6)
							{
								return 2;
							}
							else
							{
								return 1;
							}
						break;
						case 8: // bottom-most 4x1 or 4x2
								return 4;
						break;
						case 9: // center tile
							if($this->getNumberOfPlayers() == 6)
							{
								return 2;
							}
							else
							{
								return 1;
							}
						break;
				}

		}

		function getNumberOfColumnsFromTilePosition($tilePosition)
		{

				switch($tilePosition)
				{
						case 1: // 4x4
								return 4;
							break;
						case 2: // 4x4
								return 4;
							break;
						case 3: // 4x4
								return 4;
							break;
						case 4: // 4x4
								return 4;
							break;
						case 5: // left-most 4x1 or 4x2
								return 4;
							break;
						case 6: // top-most 4x1 or 4x2
							if($this->getNumberOfPlayers() == 6)
							{
								return 2;
							}
							else
							{
								return 1;
							}
						break;
						case 7: // right-most 4x1 or 4x2
								return 4;
						break;
						case 8: // bottom-most 4x1 or 4x2
							if($this->getNumberOfPlayers() == 6)
							{
								return 2;
							}
							else
							{
								return 1;
							}
						break;
						case 9: // center 1x1 or 2x2 tile
						if($this->getNumberOfPlayers() == 6)
						{
							return 2;
						}
						else
						{
							return 1;
						}
						break;
				}
		}

		function getTileXFromTilePosition($tilePosition)
		{
				$firstTileX = 1;
				$secondTileX = 5;
				$thirdTileX = 1;
				$fourthTileX = 5;
				$seventhTileX = 0; // this will be overwritten

				if($this->getNumberOfPlayers() == 5)
				{ // we need to extend the board by 1
						$secondTileX = 6;
						$fourthTileX = 6;
						$seventhTileX = 6;
				}
				elseif($this->getNumberOfPlayers() == 6)
				{ // we need to extend the board by 2
						$secondTileX = 7;
						$fourthTileX = 7;
						$seventhTileX = 7;
				}

				switch($tilePosition)
				{
						case 1:
							return $firstTileX;
						case 2:
							return $secondTileX;
						case 3:
							return $thirdTileX;
						case 4:
							return $fourthTileX;
						case 5:
							return 1;
						case 6:
							return 5;
						case 7:
							return $seventhTileX;
						case 8:
							return 5;
						case 9:
							return 5;
				}

				return 0;
		}

		function getTileYFromTilePosition($tilePosition)
		{

				$firstTileY = 1;
				$secondTileY = 1;
				$thirdTileY = 5;
				$fourthTileY = 5;
				$eighthTileY = 0; // this will be overwritten

				if($this->getNumberOfPlayers() == 5)
				{ // we need to extend the board by 1
						$thirdTileY = 6;
						$fourthTileY = 6;
						$eighthTileY = 6;
				}
				elseif($this->getNumberOfPlayers() == 6)
				{ // we need to extend the board by 2
						$thirdTileY = 7;
						$fourthTileY = 7;
						$eighthTileY = 7;
				}

				switch($tilePosition)
				{
						case 1:
							return $firstTileY;
						case 2:
							return $secondTileY;
						case 3:
							return $thirdTileY;
						case 4:
							return $fourthTileY;
						case 5:
							return 5;
						case 6:
							return 1;
						case 7:
							return 5;
						case 8:
							return $eighthTileY;
						case 9:
							return 5;
				}

				return 0;
		}

		// tilePosition: The position this tile happens to be in for this game.
		// tileNumber: The unique number designation given to a specific, physical tile.
		function insertBoardTile($tilePosition, $tileNumberToUse, $useSideA, $degreeRotation, $numberOfSaucers)
		{

				$tileX = $this->getTileXFromTilePosition($tilePosition); // get the coordinates on the board where this tile will start
				$tileY = $this->getTileYFromTilePosition($tilePosition); // get the coordinates on the board where this tile will start
				$sqlBoardTile = "INSERT INTO tile (tile_number,tile_position,tile_x,tile_y,tile_use_side_A,tile_degree_rotation) VALUES ";
				$sqlBoardTile .= "(".$tileNumberToUse.",".$tilePosition.",".$tileX.",".$tileY.",".$useSideA.",".$degreeRotation.") ";

				self::DbQuery( $sqlBoardTile );
		}

		function clearBoardSpaceValues($numberOfSaucers)
		{
				$sql = "INSERT INTO board (board_x,board_y,board_space_type) VALUES ";

				$rowColumnCount = 10;
				if($numberOfSaucers == 5)
				{
						$rowColumnCount = 11;
				}
				elseif($numberOfSaucers == 6)
				{
						$rowColumnCount = 12;
				}

				$sql_values = array();
				for( $x=0; $x<$rowColumnCount; $x++ )
				{
						for( $y=0; $y<$rowColumnCount; $y++ )
						{
								$sql_values[] = "('$x','$y','D')";
						}
				}
				$sql .= implode( $sql_values, ',' );

				self::DbQuery( $sql );
		}

		// tilePosition: The position this tile happens to be in for this game.
		// thisTile (tileNumber): The unique number designation given to a specific, physical tile.
		function initializeBoard($numberOfSaucers)
		{
				$this->clearBoardSpaceValues($numberOfSaucers);
				$possibleFourByFourTiles = range(1,4); // array from 1-4
				shuffle($possibleFourByFourTiles); // randomly order the tiles

				// tile_number=which of the 14 tiles this one represents (it does not change from game to game)
				// tile_x=the left-most column where this tile is placed on the board
				// tile_y=the top-most row where this tile is placed on the board
				// tile_use_side_A=this will be 1 if we use the "A" side, otherwise we will use the "B" side of the tile
				// tile_degree_rotation=this will be a 0 if it doesn't rotate, a 1 if it rotates 90 degrees, a 2 if it rotates 180 degrees, and a 3 if it rotates 270 degrees

				// insert base tiles (tile positions 1-4)
				for ($tilePosition = 1; $tilePosition <= 4; $tilePosition++)
				{
						$thisTile = $possibleFourByFourTiles[$tilePosition-1];
						$useSideA = rand(0,1);
						$degreeRotation = rand(0,3);

						// insert into tiles table
						$this->insertBoardTile($tilePosition, $thisTile, $useSideA, $degreeRotation, $numberOfSaucers);
						$this->setBoardTile($thisTile, $useSideA, $degreeRotation, $tilePosition); // use what's in the tile table to set the board for this particular tile
				}

				if($numberOfSaucers == 5)
				{

						$possibleOneByFourTiles = range(5,8); // array from 5-8 representing the physical tile number
						shuffle($possibleOneByFourTiles); // randomly order the tiles

						$tilePosition = 5; // this is the left-most 4x1 tile
						$thisTile = $possibleOneByFourTiles[0]; // this is one of the 4x1 tiles
						$useSideADivider = rand(0,1); // whether we use side A or B
						$degreeRotation = rand(0,1); // first start with 0 or 1 and then we multiply next
						$degreeRotationDivider = $degreeRotation * 2; // we want it to either be 0 or 2, which is 0 degrees or 180 degrees rotated

						$this->insertBoardTile($tilePosition, $thisTile, $useSideADivider, $degreeRotationDivider, $numberOfSaucers);
						$this->setBoardTile($thisTile, $useSideADivider, $degreeRotationDivider, $tilePosition); // use what's in the tile table to set the board for this particular tile

						$tilePosition = 6; // this is the top-most 4x1 tile
						$thisTile = $possibleOneByFourTiles[1]; // this is one of the 4x1 tiles
						$useSideADivider = rand(0,1); // whether we use side A or B
						$degreeRotation = rand(0,1); // first start with 0 or 1 and then we multiply next
						$degreeRotationDivider = ($degreeRotation * 2) + 1; // we want it to either be 1 or 3, which is 90 degrees or 270 degrees rotated

						$this->insertBoardTile($tilePosition, $thisTile, $useSideADivider, $degreeRotationDivider, $numberOfSaucers);
						$this->setBoardTile($thisTile, $useSideADivider, $degreeRotationDivider, $tilePosition); // use what's in the tile table to set the board for this particular tile

						$tilePosition = 7; // this is the right-most 4x1 tile
						$thisTile = $possibleOneByFourTiles[2]; // this is one of the 4x1 tiles
						$useSideADivider = rand(0,1); // whether we use side A or B
						$degreeRotation = rand(0,1); // first start with 0 or 1 and then we multiply next
						$degreeRotationDivider = $degreeRotation * 2; // we want it to either be 0 or 2, which is 0 degrees or 180 degrees rotated

						$this->insertBoardTile($tilePosition, $thisTile, $useSideADivider, $degreeRotationDivider, $numberOfSaucers);
						$this->setBoardTile($thisTile, $useSideADivider, $degreeRotationDivider, $tilePosition); // use what's in the tile table to set the board for this particular tile

						$tilePosition = 8; // this is the bottom-most 4x1 tile
						$thisTile = $possibleOneByFourTiles[3]; // this is one of the 4x1 tiles
						$useSideADivider = rand(0,1); // whether we use side A or B
						$degreeRotation = rand(0,1); // first start with 0 or 1 and then we multiply next
						$degreeRotationDivider = ($degreeRotation * 2) + 1; // we want it to either be 1 or 3, which is 90 degrees or 270 degrees rotated

						$this->insertBoardTile($tilePosition, $thisTile, $useSideADivider, $degreeRotationDivider, $numberOfSaucers);
						$this->setBoardTile($thisTile, $useSideADivider, $degreeRotationDivider, $tilePosition); // use what's in the tile table to set the board for this particular tile

						$tilePosition = 9; // insert 1x1 divider tile (tile position 9)
						$thisTile = 9; // this is the 1x1 tile
						$useSideADivider = rand(0,1); // whether we use side A or B
						$degreeRotationDivider = rand(0,3); // how much we rotate it, not that it really matters with a 1x1 tile

						$this->insertBoardTile($tilePosition, $thisTile, $useSideADivider, $degreeRotationDivider, $numberOfSaucers);
						$this->setBoardTile($thisTile, $useSideADivider, $degreeRotationDivider, $tilePosition); // use what's in the tile table to set the board for this particular tile
				}

				if($numberOfSaucers == 6)
				{
						$possibleTwoByFourTiles = range(10,13); // array from 10-13 representing the physical tile number
						shuffle($possibleTwoByFourTiles); // randomly order the tiles

						$tilePosition = 5; // this is the left-most 4x2 tile
						$thisTile = $possibleTwoByFourTiles[0]; // this is one of the 4x2 tiles
						$useSideADivider = rand(0,1); // whether we use side A or B
						$degreeRotation = rand(0,1); // first start with 0 or 1 and then we multiply next
						$degreeRotationDivider = $degreeRotation * 2; // we want it to either be 0 or 2, which is 0 degrees or 180 degrees rotated

						$this->insertBoardTile($tilePosition, $thisTile, $useSideADivider, $degreeRotationDivider, $numberOfSaucers);
						$this->setBoardTile($thisTile, $useSideADivider, $degreeRotationDivider, $tilePosition); // use what's in the tile table to set the board for this particular tile

						$tilePosition = 6; // this is the top-most 4x2 tile
						$thisTile = $possibleTwoByFourTiles[1]; // this is one of the 4x2 tiles
						$useSideADivider = rand(0,1); // whether we use side A or B
						$degreeRotation = rand(0,1); // first start with 0 or 1 and then we multiply next
						$degreeRotationDivider = ($degreeRotation * 2) + 1; // we want it to either be 1 or 3, which is 90 degrees or 270 degrees rotated

						$this->insertBoardTile($tilePosition, $thisTile, $useSideADivider, $degreeRotationDivider, $numberOfSaucers);
						$this->setBoardTile($thisTile, $useSideADivider, $degreeRotationDivider, $tilePosition); // use what's in the tile table to set the board for this particular tile

						$tilePosition = 7; // this is the right-most 4x2 tile
						$thisTile = $possibleTwoByFourTiles[2]; // this is one of the 4x2 tiles
						$useSideADivider = rand(0,1); // whether we use side A or B
						$degreeRotation = rand(0,1); // first start with 0 or 1 and then we multiply next
						$degreeRotationDivider = $degreeRotation * 2; // we want it to either be 0 or 2, which is 0 degrees or 180 degrees rotated

						$this->insertBoardTile($tilePosition, $thisTile, $useSideADivider, $degreeRotationDivider, $numberOfSaucers);
						$this->setBoardTile($thisTile, $useSideADivider, $degreeRotationDivider, $tilePosition); // use what's in the tile table to set the board for this particular tile

						$tilePosition = 8; // this is the bottom-most 4x2 tile
						$thisTile = $possibleTwoByFourTiles[3]; // this is one of the 4x2 tiles
						$useSideADivider = rand(0,1); // whether we use side A or B
						$degreeRotation = rand(0,1); // first start with 0 or 1 and then we multiply next
						$degreeRotationDivider = ($degreeRotation * 2) + 1; // we want it to either be 1 or 3, which is 90 degrees or 270 degrees rotated

						$this->insertBoardTile($tilePosition, $thisTile, $useSideADivider, $degreeRotationDivider, $numberOfSaucers);
						$this->setBoardTile($thisTile, $useSideADivider, $degreeRotationDivider, $tilePosition); // use what's in the tile table to set the board for this particular tile

						$tilePosition = 9; // insert 2x2 divider tile (tile position 9)
						$thisTile = 14; // this is the 2x2 tile
						$useSideADivider = 0; // we only need one side
						$degreeRotationDivider = rand(0,3); // how much we rotate it

						$this->insertBoardTile($tilePosition, $thisTile, $useSideADivider, $degreeRotationDivider, $numberOfSaucers);
						$this->setBoardTile($thisTile, $useSideADivider, $degreeRotationDivider, $tilePosition); // use what's in the tile table to set the board for this particular tile

				}


		}

		// tileNumber: The unique number designation given to a specific, physical tile. There are 14 different ones.
		// tilePosition: Where on the board a tile is located. There are 9 different places a tile can be located.
		function setBoardTile($tileNumber, $useSideA, $degreeRotation, $tilePosition)
		{
				$tileSpaceValues = array(array());
				switch($tileNumber)
				{
						case 1:
							if($useSideA == 1)
							{ // USE SIDE A
								$tileSpaceValues = array(
									array("B","1","B","S"),
									array("B","B","3","B"),
									array("S","B","B","B"),
									array("2","B","B","B")
								);
							}
							else
							{ // USE SIDE B
								$tileSpaceValues = array(
									array("B","B","2","B"),
									array("1","S","B","B"),
									array("B","B","B","S"),
									array("B","B","B","3")
								);
							}
						break;
						case 2:
							if($useSideA == 1)
							{ // USE SIDE A
								$tileSpaceValues = array(
									array("4","B","S","B"),
									array("B","B","B","5"),
									array("B","S","B","B"),
									array("B","6","B","B")
								);
							}
							else
							{ // USE SIDE B
								$tileSpaceValues = array(
									array("4","B","B","B"),
									array("S","B","B","B"),
									array("B","B","5","B"),
									array("B","6","B","S")
								);
							}
						break;
						case 3:
							if($useSideA == 1)
							{ // USE SIDE A
								$tileSpaceValues = array(
									array("S","B","B","B"),
									array("B","S","B","7"),
									array("B","B","9","B"),
									array("8","B","B","B")
								);
							}
							else
							{ // USE SIDE B
								$tileSpaceValues = array(
									array("8","S","B","B"),
									array("B","B","B","9"),
									array("B","B","S","B"),
									array("B","B","7","B")
								);
							}
						break;
						case 4:
							if($useSideA == 1)
							{ // USE SIDE A
								$tileSpaceValues = array(
									array("B","B","10","B"),
									array("B","B","B","S"),
									array("S","11","B","B"),
									array("B","B","B","12")
								);
							}
							else
							{ // USE SIDE B
								$tileSpaceValues = array(
									array("B","10","B","B"),
									array("B","B","S","B"),
									array("B","S","B","12"),
									array("B","B","11","B")
								);
							}
						break;
						case 5:
							if($useSideA == 1)
							{ // USE SIDE A
								$tileSpaceValues = array(
									array("B","B","B","S")
								);
							}
							else
							{ // USE SIDE B
								$tileSpaceValues = array(
									array("B","B","S","B")
								);
							}
						break;
						case 6:
							if($useSideA == 1)
							{ // USE SIDE A
								$tileSpaceValues = array(
									array("B","B","B","B")
								);
							}
							else
							{ // USE SIDE B
								$tileSpaceValues = array(
									array("B","B","B","B")
								);
							}
						break;
						case 7:
							if($useSideA == 1)
							{ // USE SIDE A
								$tileSpaceValues = array(
									array("B","S","B","B")
								);
							}
							else
							{ // USE SIDE B
								$tileSpaceValues = array(
									array("S","B","B","B")
								);
							}
						break;
						case 8:
							if($useSideA == 1)
							{ // USE SIDE A
								$tileSpaceValues = array(
									array("B","B","B","B")
								);
							}
							else
							{ // USE SIDE B
								$tileSpaceValues = array(
									array("B","B","B","B")
								);
							}
						break;

						case 9:
							if($useSideA == 1)
							{ // USE SIDE A
								$tileSpaceValues = array(
									array("S")
								);
							}
							else
							{ // USE SIDE B
								$tileSpaceValues = array(
									array("B")
								);
							}
						break;
						case 10:
							if($useSideA == 1)
							{ // USE SIDE A
								$tileSpaceValues = array(
									array("B","B","B","B"),
									array("S","B","B","B")
								);
							}
							else
							{ // USE SIDE B
								$tileSpaceValues = array(
									array("B","B","B","B"),
									array("B","S","B","B")
								);
							}
						break;
						case 11:
							if($useSideA == 1)
							{ // USE SIDE A
								$tileSpaceValues = array(
									array("B","B","B","B"),
									array("B","B","S","B")
								);
							}
							else
							{ // USE SIDE B
								$tileSpaceValues = array(
									array("B","B","B","B"),
									array("B","B","B","S")
								);
							}
						break;
						case 12:
							if($useSideA == 1)
							{ // USE SIDE A
								$tileSpaceValues = array(
									array("S","B","B","B"),
									array("B","B","B","B")
								);
							}
							else
							{ // USE SIDE B
								$tileSpaceValues = array(
									array("B","S","B","B"),
									array("B","B","B","B")
								);
							}
						break;
						case 13:
							if($useSideA == 1)
							{ // USE SIDE A
								$tileSpaceValues = array(
									array("B","B","S","B"),
									array("B","B","B","B")
								);
							}
							else
							{ // USE SIDE B
								$tileSpaceValues = array(
									array("B","B","B","S"),
									array("B","B","B","B")
								);
							}
						break;
						case 14:
							if($useSideA == 1)
							{ // USE SIDE A
								$tileSpaceValues = array(
									array("B","B"),
									array("B","S")
								);
							}
							else
							{ // USE SIDE B
								$tileSpaceValues = array(
									array("S","B"),
									array("B","B")
								);
							}
						break;
				}


				for ($rotations = 0; $rotations < $degreeRotation; $rotations++)
				{ // the number of times we want to rotate this tile

						$tileSpaceValues = $this->rotateMatrix90($tileSpaceValues);
				}

				$this->setBoardSpaceValues($tileNumber, $tileSpaceValues, $tilePosition);
		}

		function setBoardSpaceValues($tileNumber, $tileSpaceValues, $tilePosition)
		{
				$startingX = $this->getTileXFromTileNumber($tileNumber);
				$startingY = $this->getTileYFromTileNumber($tileNumber);

				$columns = $this->getNumberOfColumnsFromTilePosition($tilePosition);
				$rows = $this->getNumberOfRowsFromTilePosition($tilePosition);

				for( $x=0; $x<$columns; $x++ )
				{ // go through columns

						for( $y=0; $y<$rows; $y++ )
						{ // go through rows

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

		function getNumberOfSaucers()
		{
				switch($this->getNumberOfPlayers())
				{
						case 2:
							return 4;
						break;
						case 3:
							return 3;
						break;
						case 4:
							return 4;
						break;
						case 5:
							return 5;
						break;
						case 6:
							return 6;
						break;
						default:
							return 0;
							break;
				}
		}

		function getNumberOfPlayers()
		{
			return $this->getGameStateValue("NUMBER_OF_PLAYERS");
/*				$numberOfPlayers = 0;

				$players = self::getObjectListFromDB( "SELECT player_id
																											 FROM player
																											 WHERE 1" );

				foreach( $players as $player )
				{ // go through each player who needs to replace a garment
						$numberOfPlayers++;
				}

				return $numberOfPlayers;
*/
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
				$this->saveSaucerMoveCardDistance($ostrich, 20);
				$this->saveSaucerMoveCardDirection($ostrich, "");

				$sqlUpdate = "UPDATE movementCards SET ";
				$sqlUpdate .= "card_ostrich='',card_location='hand' ";
				$sqlUpdate .= "WHERE card_ostrich='$ostrich'";

				self::DbQuery( $sqlUpdate );
		}

		// This is usually called before a player moves, before they choose X, and before traps are executed to
		// reset their distances and directions in case they were pushed during the last player's movement turn.
		function resetSaucerDistancesAndDirectionsToMoveCard()
		{
			$ostriches = self::getObjectListFromDB( "SELECT ostrich_color, ostrich_zig_direction, ostrich_zig_distance
																										 FROM ostrich
																										 WHERE 1" );

			foreach( $ostriches as $ostrich )
			{ // go through each ostrich

					// set their distance and direction to our zig values
					$this->saveSaucerLastDistance($ostrich['ostrich_color'], $ostrich['ostrich_zig_distance']);
					$this->saveSaucerLastDirection($ostrich['ostrich_color'], $ostrich['ostrich_zig_direction']);
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

		function convertTurnOrderIntToText($turnOrderInt)
		{
				switch($turnOrderInt)
				{
						case 0:
						return clienttranslate("CLOCKWISE");
						case 1:
						return clienttranslate("COUNTER-CLOCKWISE");
						default:
						return clienttranslate("UNKNOWN");
				}
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

		function getMaxColumns()
		{
				return self::getUniqueValueFromDb("SELECT max(board_x) FROM `board`;");
		}

		function getMaxRows()
		{
				return self::getUniqueValueFromDb("SELECT max(board_y) FROM `board`;");
		}

		function getAllSpacesInColumnUp($startingY, $column)
		{
				$result = array();

				$spaces = self::getObjectListFromDB( "SELECT board_x, board_y FROM `board` WHERE board_x=$column AND board_y < $startingY;" );

				foreach( $spaces as $space )
				{ // go through each space

						$spaceArray = array();
						$spaceArray['row'] = $space['board_y'];
						$spaceArray['column'] = $space['board_x'];
						array_push($result, $spaceArray); // add this space to the list of move destinations
				}

				return $result;
		}

		function getAllSpacesInColumnDown($startingY, $column)
		{
				$result = array();

				$spaces = self::getObjectListFromDB( "SELECT board_x, board_y FROM `board` WHERE board_x=$column AND board_y > $startingY;" );

				foreach( $spaces as $space )
				{ // go through each space

						$spaceArray = array();
						$spaceArray['row'] = $space['board_y'];
						$spaceArray['column'] = $space['board_x'];
						array_push($result, $spaceArray); // add this space to the list of move destinations
				}

				return $result;
		}

		function getAllSpacesInRowLeft($startingX, $row)
		{
				$result = array();

				$spaces = self::getObjectListFromDB( "SELECT board_x, board_y FROM `board` WHERE board_y=$row AND board_x < $startingX;" );

				foreach( $spaces as $space )
				{ // go through each space

						$spaceArray = array();
						$spaceArray['row'] = $space['board_y'];
						$spaceArray['column'] = $space['board_x'];

						array_push($result, $spaceArray); // add this space to the list of move destinations
				}

				return $result;
		}

		function getAllSpacesInRowRight($startingX, $row)
		{
				$result = array();

				$spaces = self::getObjectListFromDB( "SELECT board_x, board_y FROM `board` WHERE board_y=$row AND board_x > $startingX;" );

				foreach( $spaces as $space )
				{ // go through each space

						$spaceArray = array();
						$spaceArray['row'] = $space['board_y'];
						$spaceArray['column'] = $space['board_x'];

						array_push($result, $spaceArray); // add this space to the list of move destinations
				}

				return $result;
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

		function moveCrewmemberToBoard($garmentId, $xDestination, $yDestination)
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
								$this->saveSaucerLastDistance($trappedOstrich, $newDistance); // save the new distance to where our move is stored
								$this->saveSaucerMoveCardDistance($trappedOstrich, $newDistance); // save the new distance to where our zig values are stored

								$currentXValue = $this->getSaucerXValue($trappedOstrich);
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
								$this->saveSaucerLastDirection($trappedOstrich, $newDirection); // save the new direction to where we will move them
								$this->saveSaucerMoveCardDirection($trappedOstrich, $newDirection); // save the new direction of their zig

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
								$this->saveSaucerLastDirection($trappedOstrich, $newDirection); // save the new direction to what we use for moving
								$this->saveSaucerMoveCardDirection($trappedOstrich, $newDirection); // save the new direction to where their zig is stored

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
								$this->setBoardTile($tileNumber, $useSideA, $degreeRotation, $tilePosition); // update the board table with each new space value
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
								$this->saveSaucerLastDistance($trappedOstrich, $newDistance); // save the new distance to where our movement is stored
								$this->saveSaucerMoveCardDistance($trappedOstrich, $newDistance); // save the new distance to where our zig values are stored

								$currentXValue = $this->getSaucerXValue($trappedOstrich);
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
								$this->setBoardTile($tileNumber, $useSideA, $degreeRotation, $tilePosition); // update the board table with each new space value
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
								$this->saveSaucerLastDirection($trappedOstrich, $newDirection); // save the new direction to where we check for moving
								$this->saveSaucerMoveCardDirection($trappedOstrich, $newDirection); // save the new direction to where we keep our zig values

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
				$allOstriches = $this->getSaucersInOrder(); // get all ostriches

				foreach($allOstriches as $ostrich)
				{
						$ostrichColor = $ostrich['color'];
						$tilePosition = $this->getTilePositionOfOstrich($ostrichColor); // get which tile they are on
						$tileNumber = $this->getTileNumber($tilePosition); // find the number of that tile

						if($tileNumber == $tileNumberToRotate)
						{	// we need to rotate this ostrich
								$xOffsetOfTile = $this->getTileXFromTileNumber($tileNumberToRotate) - 1;
								$yOffsetOfTile = $this->getTileYFromTileNumber($tileNumberToRotate) - 1;
								$currentOstrichX = $this->getSaucerXLocation($ostrichColor);
								$currentOstrichY = $this->getSaucerYLocation($ostrichColor);
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
						$allPlayersOstriches = $this->getSaucersForPlayer($playerId);
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

		function getSaucersForPlayer($playerId)
		{
				return self::getObjectListFromDB( "SELECT ostrich_color
																										 FROM ostrich
																										 WHERE ostrich_owner=$playerId" );
		}

		// Same as getSaucersForPlayer except in array form.
		function getSaucerArrayForPlayer($playerId)
		{
				$result = array();
				$ostrichesObjectForm = $this->getSaucersForPlayer($playerId);
			  $ostrichIndex = 0;
				foreach($ostrichesObjectForm as $ostrich)
				{
						$result[$ostrichIndex] = $ostrich['ostrich_color'];
						//echo "myostrich ".$result[$ostrichIndex];

						$ostrichIndex++;
				}

				return $result;
		}

		function getSaucersInOrder()
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

		// Return the Saucer color located at a given X/Y position ignoring a specific saucer.
		function getSaucerAt($x, $y, $ignoreColor)
		{
				// get any ostrich at this location
				$ostriches = self::getObjectListFromDB( "SELECT ostrich_color
																										 FROM ostrich
																										 WHERE ostrich_color<>'$ignoreColor' AND ostrich_x=".$x." AND ostrich_y=".$y." " );

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

		function getCrewmemberIdFromColorAndType($color, $typeAsInt)
		{
				return self::getUniqueValueFromDb("SELECT garment_id FROM garment WHERE garment_color='$color' AND garment_type=$typeAsInt LIMIT 1");
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

		// Move one space at a time adding events for the front end as we go for anything that happened and
		// also updating the backend data as we go.
		function getEventsWhileExecutingMove($currentX, $currentY, $distance, $direction, $saucerMoving, $wasPushed)
		{
				$moveEventList = array();
				//$moveEventList[0] = array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => 4, 'destination_Y' => 7);
				//$moveEventList[1] = array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => 2, 'destination_Y' => 7);

				if($this->LEFT_DIRECTION == $direction)
				{ // we're traveling from right to left
					  for ($x = 1; $x <= $distance; $x++)
						{ // go one space at a time over distance
								$thisX = $currentX-$x; // move one space
								$boardValue = $this->getBoardSpaceType($thisX,$currentY); // which type of space did we move onto

								/*echo "The value at ($thisX, $currentY) is: $boardValue <br>";
								throw new feException("The value at ($thisX, $currentY) is: $boardValue");*/

								$this->setSaucerXValue($saucerMoving, $thisX); // set X value for Saucer

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
								{ // this is a CRASH SITE

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));

								}
								else if($boardValue == "S")
								{ // this is an ACCELERATOR

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));
										return $moveEventList; // don't go any further
								}
								else if($boardValue == "D")
								{	// went off a cliff

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));
										return $moveEventList; // don't go any further
								}
								else
								{ // empty space
										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));
								}

								$saucerWeCollideWith = $this->getSaucerAt($thisX, $currentY, $saucerMoving); // get any ostriches that might be at this location
								if($saucerWeCollideWith != "")
								{	// there is an ostrich here
										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));
										$pushedEventList = $this->getEventsWhileExecutingMove($thisX, $thisY, $distance, $direction, $saucerWeCollideWith, true);
										return array_merge($moveEventList, $pushedEventList); // add the pushed event to the original and return so we don't go any further
								}

								//return $moveEventList;
					  }

						return $moveEventList;
				}

				if($this->RIGHT_DIRECTION == $direction)
			 	{
						for ($x = 1; $x <= $distance; $x++)
						{ // go space-by-space starting at your current location until the distance is
							// used up or we run into a skateboard or ostrich

								$thisX = $currentX+$x;
								$boardValue = $this->getBoardSpaceType($thisX,$currentY);

								/*echo "The value at ($thisX, $currentY) is: $boardValue <br>";
								throw new feException("The value at ($thisX, $currentY) is: $boardValue");*/

								$this->setSaucerXValue($saucerMoving, $thisX); // set X value for Saucer

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
								{ // this is a CRASH SITE

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));

								}
								else if($boardValue == "S")
								{ // we hit a skateboard

										// see if they were pushed
										// if so, double the distance and keep going

										// if not, don't go any further
										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));
										return $moveEventList;
								}
								else if($boardValue == "D")
								{	// went off a cliff

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));
										return $moveEventList; // return so we don't go any further
								}

								$saucerWeCollideWith = $this->getSaucerAt($thisX, $currentY, $saucerMoving); // get any ostriches that might be at this location
								if($saucerWeCollideWith != "")
								{	// there is an ostrich here
									array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));
									$pushedEventList = $this->getEventsWhileExecutingMove($thisX, $thisY, $distance, $direction, $saucerWeCollideWith, true);
									return array_merge($moveEventList, $pushedEventList); // add the pushed event to the original and return so we don't go any further
								}
						}

						return $moveEventList;
				}

				if($this->UP_DIRECTION == $direction)
				{
						for ($y = 1; $y <= $distance; $y++)
						{ // go space-by-space starting at your current location until the distance is
							// used up or we run into a skateboard or ostrich

								$thisY = $currentY-$y;
								$boardValue = $this->getBoardSpaceType($currentX,$thisY); // get the space type here

								$this->setSaucerYValue($saucerMoving, $thisY); // set Y value for Saucer

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
								{ // this is a CRASH SITE

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));

								}
								else if($boardValue == "S")
								{ // we hit an accelerator

										// don't go any further
										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));
										return $moveEventList;
								}
								else if($boardValue == "D")
								{	// went off a cliff

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));
										return $moveEventList;
								}
								else
								{ // empty space
										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));
								}

								$saucerWeCollideWith = $this->getSaucerAt($currentX, $thisY, $saucerMoving); // get any ostriches that might be at this location
								if($saucerWeCollideWith != "")
								{	// there is an ostrich here
										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));
										$pushedEventList = $this->getEventsWhileExecutingMove($thisX, $thisY, $distance, $direction, $saucerWeCollideWith, true);
										return array_merge($moveEventList, $pushedEventList); // add the pushed event to the original and return so we don't go any further
								}
						}

						return $moveEventList;
				}

				if($this->DOWN_DIRECTION == $direction)
			 	{
						for ($y = 1; $y <= $distance; $y++)
						{ // go space-by-space starting at your current location until the distance is
							// used up or we run into a skateboard or ostrich

								$thisY = $currentY+$y;
								$boardValue = $this->getBoardSpaceType($currentX,$thisY);

							  $this->setSaucerYValue($saucerMoving, $thisY); // set Y value for Saucer

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

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));

								}
								else if($boardValue == "S")
								{ // we hit an Accelerator

										// don't go any further
										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));
										return $moveEventList;
								}
								else if($boardValue == "D")
								{	// went off a cliff

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));
										return $moveEventList;
								}
								else
								{ // empty space
										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));
								}

								$saucerWeCollideWith = $this->getSaucerAt($currentX, $thisY, $saucerMoving); // get any ostriches that might be at this location
								if($saucerWeCollideWith != "")
								{	// there is an ostrich here
									array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));
									$pushedEventList = $this->getEventsWhileExecutingMove($thisX, $thisY, $distance, $direction, $saucerWeCollideWith, true);
									return array_merge($moveEventList, $pushedEventList); // add the pushed event to the original and return so we don't go any further
								}
						}

						return $moveEventList;
				}

				return $moveEventList; // we should never get here
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

										$this->giveProbe($ostrichMoving);

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

										$this->giveProbe($ostrichMoving);

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

										$this->giveProbe($ostrichMoving);

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

										$this->giveProbe($ostrichMoving);

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

		function takeBooster($saucerColor)
		{

		}

		function isCrashSiteEmpty($crashSiteNumber)
		{
				// get the X and Y position of the given crash site
				$crashSiteX = $this->getXOfCrashSite($crashSiteNumber);
				$crashSiteY = $this->getYOfCrashSite($crashSiteNumber);

				// see if there are any Crewmembers on it
				$allCrewmembers = $this->getAllCrewmembers();
				foreach( $allCrewmembers as $crewmember )
				{ // go through each saucer
						$crewmemberX = $crewmember['garment_x'];
						$crewmemberY = $crewmember['garment_y'];
						$crewmemberLocation = $crewmember['garment_location'];

						if($crewmemberLocation == 'board' &&
						$crewmemberX == $crashSiteX &&
						$crewmemberY == $crashSiteY)
						{ // there is a saucer on this board space
								return false;
						}
				}


				// see if there are any Saucers on it
				$allSaucers = $this->getAllSaucers();
				foreach( $allSaucers as $saucer )
				{ // go through each saucer
						$saucerX = $saucer['ostrich_x'];
						$saucerY = $saucer['ostrich_y'];

						if($crashSiteX == $saucerX && $crashSiteY == $saucerY)
						{ // there is a saucer on this board space
								return false;
						}
				}

				return true;
		}

		// Returns a random empty crash site number (1-12). If it cannot find one, it will return 0;
		function getEmptyCrashSite()
		{
				$crashSiteNumber = rand(1,12);
				$isCrashSiteEmpty = $this->isCrashSiteEmpty($crashSiteNumber);
				$attemptsCount = 0;
				while (!$isCrashSiteEmpty && $attemptsCount < 100)
				{
						$crashSiteNumber = rand(1,12);
						$isCrashSiteEmpty = $this->isCrashSiteEmpty($crashSiteNumber);
						$attemptsCount++;
				}

				if(!$isCrashSiteEmpty)
				{ // we could not find an empty crash site
						return 0;
				}

				return $crashSiteNumber;
		}

		function locatePilot($saucerColor)
		{
				$crashSite = $this->getEmptyCrashSite();
				$locX = $this->getXOfCrashSite($crashSite);
				$locY = $this->getYOfCrashSite($crashSite);
				$type = 0; // this is the Pilot
				$crewmemberId = $this->getCrewmemberIdFromColorAndType($saucerColor, $type);

				$this->moveCrewmemberToBoard($crewmemberId, $locX, $locY);
		}

		function giveProbe($ostrich)
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

				$allOstriches = $this->getSaucersInOrder();

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
							$this->saveSaucerLastDirection($ostrichWeCollideWith, $direction);
							$this->saveSaucerLastDistance($ostrichWeCollideWith, $distance);

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

		function setSaucerXValue($saucer, $xValue)
		{
				$sqlUpdate = "UPDATE ostrich SET ";
				$sqlUpdate .= "ostrich_x=".$xValue." WHERE ";
				$sqlUpdate .= "ostrich_color='".$saucer."'";

				self::DbQuery( $sqlUpdate );
		}

		function setSaucerYValue($saucer, $yValue)
		{
				$sqlUpdate = "UPDATE ostrich SET ";
				$sqlUpdate .= "ostrich_y=".$yValue." WHERE ";
				$sqlUpdate .= "ostrich_color='".$saucer."'";

				self::DbQuery( $sqlUpdate );
		}

		// Save details about the move a particular ostrich will make this round.
		function saveOstrichLocation( $ostrich, $x, $y )
		{
				if($x < 1)
				{ // fell off a cliff
						$x = 0;
				}

				if($x > 8 && $this->getNumberOfPlayers() > 3)
				{ // we've gone off the cliff to the right
						$x = 9;
				}

				if($x > 12 && $this->getNumberOfPlayers() < 4)
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

		function saveSaucerLastDirection( $ostrich, $direction )
		{
				$sqlUpdate = "UPDATE ostrich SET ";
				$sqlUpdate .= "ostrich_last_direction='".$direction."' WHERE ";
				$sqlUpdate .= "ostrich_color='".$ostrich."'";

				self::DbQuery( $sqlUpdate );
		}

		function getSaucerDirection($saucerColor)
		{
				return self::getUniqueValueFromDb("SELECT ostrich_zig_direction FROM ostrich WHERE ostrich_color='$saucerColor'");
		}

		function saveSaucerMoveCardDirection( $ostrich, $direction )
		{
				$sqlUpdate = "UPDATE ostrich SET ";
				$sqlUpdate .= "ostrich_zig_direction='".$direction."' WHERE ";
				$sqlUpdate .= "ostrich_color='".$ostrich."'";

				self::DbQuery( $sqlUpdate );
		}

		function getSaucerDistance( $saucerColor )
		{
				$distanceType = self::getUniqueValueFromDb("SELECT ostrich_zig_distance FROM ostrich WHERE ostrich_color='$saucerColor'"); // 0, 1, 2

				if($distanceType == 0)
				{ // X
						$xValue = $this->getSaucerXValue($saucerColor);
				}
				elseif($distanceType == 1)
				{ // 2
						return 2;
				}
				elseif($distanceType == 2)
				{ // 3
						return 3;
				}
				else
				{
						throw new feException( "Unrecognized distance type ($distanceType)");
				}
		}

		function getSaucerDistanceType( $saucerColor )
		{
				return self::getUniqueValueFromDb("SELECT ostrich_zig_distance FROM ostrich WHERE ostrich_color='$saucerColor'"); // 0, 1, 2
		}

		function getSaucerLastDistance( $saucerColor )
		{
				return self::getUniqueValueFromDb("SELECT ostrich_last_distance FROM ostrich WHERE ostrich_color='$saucerColor'");
		}

		function saveSaucerLastDistance( $ostrich, $distance )
		{
				$sqlUpdate = "UPDATE ostrich SET ";
				$sqlUpdate .= "ostrich_last_distance='".$distance."' WHERE ";
				$sqlUpdate .= "ostrich_color='".$ostrich."'";

				self::DbQuery( $sqlUpdate );
		}



		public function getStateName() {
       $state = $this->gamestate->state();
       return $state['name'];
   }

		function saveSaucerMoveCardDistance( $ostrich, $distance )
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

				$turnOrder = 2; // default to not showing
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

		function getXOfCrashSite($crashSiteNumber)
		{
				return self::getUniqueValueFromDb("SELECT board_x FROM board WHERE board_space_type=$crashSiteNumber");
		}

		function getYOfCrashSite($crashSiteNumber)
		{
				return self::getUniqueValueFromDb("SELECT board_y FROM board WHERE board_space_type=$crashSiteNumber");
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

		function getSaucerXLocation($saucerColor)
		{
				return self::getUniqueValueFromDb("SELECT ostrich_x FROM ostrich WHERE ostrich_color='$saucerColor'");
		}

		function getSaucerYLocation($saucerColor)
		{
				return self::getUniqueValueFromDb("SELECT ostrich_y FROM ostrich WHERE ostrich_color='$saucerColor'");
		}

		function getSaucerXValue( $ostrich )
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
						case "f6033b":
							return "RED";

						case "fedf3d":
							return "YELLOW";

            case "0090ff":
							return "BLUE";

            case "01b508":
							return "GREEN";

						case "b92bba":
							return "PURPLE";

						case "c9d2db":
							return "GRAY";
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

		function setState_AfterMovementEvents($saucerMoving)
		{
				$boardValue = $this->getBoardSpaceTypeForOstrich($saucerMoving); // get the type of space of the ostrich who just moved

				$canUseBoost = false;
				//$canUseBoost = $this->doesOstrichHaveZag($ostrichMoving); // true if they have a boost

				$saucerCrashed = false;
				//$saucerCrashed = $this->isOstrichOffCliff($saucerMoving); // true if they moved off the board

				if($this->isEndGameConditionMet())
				{ // the game has ended
						$this->gamestate->nextState( "endGame" );
				}
				else if($boardValue == "S")
				{ // the saucer onto an accelerator on their turn
						$this->gamestate->nextState( "chooseAcceleratorDirection" ); // need to ask the player which direction they want to go on the skateboard
				}
				else if($canUseBoost &&
								!$saucerCrashed)
				{ // the player has a boost they can use and they have not crashed
						$this->gamestate->nextState( "askUseZag" ); // need to ask the player if they want to use a zag, and if so, which direction they want to travel
				}
				else
				{ // movement is complete
					$this->gamestate->nextState( "endPlayerTurn" ); // temp
					//$this->setState_TrapAndCliffAndGarmentCleanup();
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
				{ // the ostrich walked on the accelerator on their turn
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

//////////////////////////////////////////////////////////////////////////////
//////////// Player actions
////////////

    /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in crashandgrab.action.php)
    */

		function executeChooseDirection()
		{
				$this->saveSaucerLastDirection();

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

		function executeStartMove()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				// move the selected distance in the selected direction
				$this->executeSaucerMove($saucerWhoseTurnItIs);
		}

		function executeClickedSaucerToGoFirst($colorAsFriendlyText)
		{
				$color = $this->convertFriendlyColorToHex($colorAsFriendlyText);

				// set this saucer to the one going next ostrich.ostrich_turns_taken or player.player_turns_taken_this_round

				$this->gamestate->nextState( "locateCrashedSaucer" );
		}

		function executeClickedConfirmMove( $saucer1Color, $saucer1Distance, $saucer1Direction, $saucer2Color, $saucer2Distance, $saucer2Direction )
    {
				// Check that this is the player's turn and that it is a "possible action" at this game state (see states.inc.php)
        self::checkAction( 'confirmMove' );
//throw new feException( "Clicked confirm move.");

				!$this->isValidMove($saucer1Color, $saucer1Distance, $saucer1Direction, $saucer2Color, $saucer2Distance, $saucer2Direction, true); // validate the move in different ways and throw an error if any failures

				$playerConfirming = $this->getOwnerIdOfOstrich($saucer1Color);

				// save the move card and direction we used
				$this->saveSaucerMoveCardDirection($saucer1Color, $saucer1Direction); // save the direction so we have it in case we are pushed before our turn comes up
				$this->saveSaucerMoveCardDistance($saucer1Color, $saucer1Distance); // save the distance so we have it in case we are pushed before our turn comes up

				if($saucer2Color != '')
				{ // we have a second saucer
						$this->saveSaucerMoveCardDirection($saucer2Color, $saucer2Direction); // save the direction so we have it in case we are pushed before our turn comes up
						$this->saveSaucerMoveCardDistance($saucer2Color, $saucer2Distance); // save the distance so we have it in case we are pushed before our turn comes up
				}

				// move to next phase (roll rotation die) when everyone else confirms too
				// and tell the machine state to use transtion "directionChosen" if all players are now unactive
        $this->gamestate->setPlayerNonMultiactive( $playerConfirming, "allMovesChosen" );
		}

		function executeClickedMoveCard( $distance, $color )
    {
				// Check that this is the player's turn and that it is a "possible action" at this game state (see states.inc.php)
        self::checkAction( 'clickDistance' );

				if(is_null($distance))
				{
						throw new feException( "Invalid move card distance.");
				}
				if(is_null($distance))
				{
						throw new feException( "Invalid saucer color.");
				}
				if(false)
				{
						throw new BgaUserException( self::_("This card was used last round. Please choose a different one.") );
				}

				$player_id = self::getCurrentPlayerId(); // Current Player = player who played the current player action (the one who made the AJAX request). Active Player = player whose turn it is.
				$player_name = self::getCurrentPlayerName(); // Current Player = player who played the current player action (the one who made the AJAX request). Active Player = player whose turn it is.

				$card_name = "unknown"; // will be set in the loop next
				$card_distance = -3; // will be set in the loop next

				$sql = "SELECT card_type, card_type_arg ";
        $sql .= "FROM movementCards ";
        $sql .= "WHERE card_type_arg=".$distance." AND card_location='".$color."'";
        $dbres = self::DbQuery( $sql );
        while( $movementCard = mysql_fetch_assoc( $dbres ) )
        {
						$card_distance = $movementCard['card_type_arg'];
						$card_clockwise = $movementCard['card_type'];
						$card_name = $movementCard['card_type_arg']." ".$movementCard['card_type'];
        }

				$card_clockwise_integer = $this->getClockwiseInteger($card_clockwise);

			  $this->movementCards->moveCard( $card_id, 'zigChosen', $player_id );
				$this->saveSaucerMoveCardDistance($ostrich, $card_distance); // put the distance of the zig on this ostrich so we know it has been chosen
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
				$this->saveSaucerMoveCardDirection($ostrich, $direction); // save the direction so we have it in case we are pushed before our turn comes up
				$this->saveSaucerMoveCardDistance($ostrich, $card_distance); // save the distance so we have it in case we are pushed before our turn comes up

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
				$theirOstriches = $this->getSaucersForPlayer($player_id);

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

		// $throwErrors is true if you want this validation to throw errors or false if you want it to just return true/false
		function isValidMove($saucer1Color, $saucer1Distance, $saucer1Direction, $saucer2Color, $saucer2Distance, $saucer2Direction, $throwErrors)
		{
				$isValid = true;
				$currentPlayerId = self::getCurrentPlayerId(); // Current Player = player who played the current player action (the one who made the AJAX request). Active Player = player whose turn it is.
//throw new feException( "isValidMove saucer1Distance ($saucer1Distance)" );
				if( is_null($saucer1Color) || $saucer1Color == '' || is_null($saucer1Distance) || $saucer1Distance == '' || is_null($saucer1Direction) || $saucer1Direction == '' )
				{ // some argument is null for saucer 1
						if($throwErrors)
							throw new BgaUserException( self::_("You must choose a valid move.") );

						$isValid = false;
				}

				if( $this->getNumberOfPlayers() == 2 && (is_null($saucer2Color) || $saucer2Color == '' || is_null($saucer2Distance) || $saucer2Distance == '' || is_null($saucer2Direction) || $saucer2Direction == '') )
				{ // some argument is null for saucer 2
						if($throwErrors)
							throw new BgaUserException( self::_("You must choose a valid move.") );

						$isValid = false;
				}

				if( $saucer1Distance != 0 && $saucer1Distance != 1 && $saucer1Distance != 2 )
				{ // make sure the distance is valid
						if($throwErrors)
							throw new BgaUserException( self::_("This is not a valid move card. ($saucer1Distance)") );

						$isValid = false;
				}

				if( !($saucer1Direction != 'sun' || $saucer1Direction != 'asteroids' || $saucer1Direction != 'meteor' || $saucer1Direction != 'constellation') )
				{ // make sure the direction is valid
						if($throwErrors)
							throw new BgaUserException( self::_("This is not a valid direction. ($saucer1Direction)") );

						$isValid = false;
				}

				if($this->getOwnerIdOfOstrich($saucer1Color) != $currentPlayerId)
				{ // make sure the player confirming the move owns the saucer
						if($throwErrors)
							throw new BgaUserException( self::_("This saucer does not appear to belong to you.") );

						$isValid = false;
				}

				if($this->getNumberOfPlayers() == 2 && $this->getOwnerIdOfOstrich($saucer2Color) != $currentPlayerId)
				{ // make sure the player confirming the move owns the second saucers
						if($throwErrors)
							throw new BgaUserException( self::_("This saucer does not appear to belong to you.") );

						$isValid = false;
				}

				// validate whether the move is more than 1 off the board?

				// validate whether they are using cards that have not already been used?

				return $isValid;
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

		function executeSaucerMove($saucerMoving)
		{
				self::debug( "executeSaucerMove saucerMoving:$saucerMoving" );

				// get list of move events in chronological order (saucers and where they end up, crewmembers picked up and by whom)
				$moveEventList = $this->getMovingEvents($saucerMoving);

				//$eventCount = count($moveEventList);
				//throw new feException( "event count: $eventCount");

				// notify players by sending a list of move events so they can play the animations one after another
				$reversedMoveEventList = array_reverse($moveEventList); // reverse it since it will act like a Stack on the javascript side
				self::notifyAllPlayers( "animateMovement", clienttranslate( 'Saucers are moving.' ), array(
					'moveEventList' => $reversedMoveEventList
				) );

				//$xDestination = $this->getEndingX($currentX, $currentY, $distance, $direction, $saucerMoving);
				//$yDestination = $this->getEndingY($currentX, $currentY, $distance, $direction, $saucerMoving);
				//$boardValue = $this->getBoardSpaceType($xDestination,$yDestination); // get the type of space on which we are ending
				//self::debug( "getMovingEvents xDestination:$xDestination yDestination: $yDestination boardValue: $boardValue" );

				//$this->gamestate->nextState( "playerTurnLocateCrewmembers" );
				//$this->gamestate->nextState( "endPlayerTurn" );
//throw new feException( "check list");

				// decide the state to go to after the move
				// see if they are on an accelerator...
				// if so, put in askAccelerator state
				// see if they have a booster and have not fallen off the board...
				// if so, put in askBoost state
				// if they picked up a crewmember, put in playerTurnLocateCrewmembers state
				// if no, put in endTurnCleanup state
				$this->setState_AfterMovementEvents($saucerMoving);

		}

		function getMovingEvents( $saucerMoving )
		{
				$allEvents = array();
				self::debug( "getMovingEvents saucerMoving:$saucerMoving" );

				$distance = $this->getSaucerDistance($saucerMoving);
				$direction = $this->getSaucerDirection($saucerMoving); // meteor
				$currentX = $this->getSaucerXLocation($saucerMoving); // 7
				$currentY = $this->getSaucerYLocation($saucerMoving); // 5

				self::debug( "getMovingEvents distance:$distance direction: $direction currentX: $currentX currentY: $currentY" );

				$allEvents = $this->getEventsWhileExecutingMove($currentX, $currentY, $distance, $direction, $saucerMoving, false); // move a space at a time picking up crewmembers, colliding, etc.

				//$eventCount = count($allEvents);
				//throw new feException( "event count: $eventCount");


				return $allEvents;
		}

		/// Called when the animation completes for a saucer move.
		function executeMoveComplete()
		{

				$this->gamestate->nextState( "playerTurnLocateCrewmembers" );
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
				$this->saveSaucerLastDirection($ostrichMoving, $newDirection); // update the database with its new last moved direction
				$zigDistance = $this->getZigDistanceForOstrich($ostrichMoving); // get the distance on this ostrich's zig card
				$this->saveSaucerLastDistance($ostrichMoving, $zigDistance); // if this ostrich collided, its distance/direction was set to that of the collider so we also need to reset the distance

				$this->executeMove($ostrichMoving, $ostrichTakingTurn);
		}

		function executeZagMove( $direction)
		{
				$ostrichZagging = $this->getOstrichWhoseTurnItIs(); // you can only zag on your own turn
				$this->saveSaucerLastDirection($ostrichZagging, $direction); // update the database with its new last moved direction

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
				$this->saveSaucerLastDistance($ostrich, $xValue);
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

		// This happens just before each player starts moving.
		function rollRotationDie()
		{
				// if this is not the first round, set the player with the probe to go first

				if($this->getNumberOfPlayers() > 2)
				{ // we don't care about turn order in a 2-player game

						// roll the rotation die
						$turnOrderInt = rand(0,1);
						self::setGameStateValue( 'TURN_ORDER', $turnOrderInt ); // 0=CLOCKWISE, 1=COUNTER-CLOCKWISE, 2=UNKNOWN

						$turnOrderFriendly = $this->convertTurnOrderIntToText($turnOrderInt);

						// notify players of the direction (send clockwise/counter)
						self::notifyAllPlayers( 'updateTurnOrder', clienttranslate( 'The turn direction this round is ${turnOrderFriendly}.' ), array(
										'i18n' => array('turnOrderFriendly'),
										'turnOrderFriendly' => $turnOrderFriendly,
										'turnOrder' => $turnOrderInt
						) );

						$this->gamestate->nextState( "locateCrashedSaucer" ); // locate first player's saucers (there won't ever be any but let's go anyway)
				}
				else
				{ // this is a 2-player game
						$this->gamestate->nextState( "askWhichSaucerGoesFirst" ); // ask whoever has the probe which saucer they'd like to go first
				}

				// tell all players a new round has started where they will send a random move card back of their opponents on to their mat

		}

		// Convert a player ID into a player NAME.
		function getPlayerNameFromPlayerId($playerId)
		{
				if(is_null($playerId) || $playerId == '')
				{
						return '';
				}

				$sql = "SELECT player_name FROM `player` ";
				$sql .= "WHERE player_id=$playerId LIMIT 1";

				if(is_null($playerId) || $playerId == '')
				{
						return 10;
				}

				$name = self::getUniqueValueFromDb($sql);

				return $name;
		}

		function isTurnOrderClockwise()
		{
				return true;
		}

		function getPlayerTurnsTaken($playerId)
		{
				return self::getUniqueValueFromDb("SELECT stats_value FROM `stats` WHERE stats_player_id=$playerId AND stats_type=10 LIMIT 1");
		}

		function haveAllPlayersTakenTheirTurn()
		{
				$turnValueToCompare = -1;

				$allPlayers = self::getObjectListFromDB( "SELECT player_id
																											 FROM player" );
				foreach( $allPlayers as $player )
				{ // go through each player who needs to replace a garment
						//echo "playerID is " + $player['player_id'];
						$playerId = $player['player_id'];
						$turnsForPlayer = $this->getPlayerTurnsTaken($playerId);

						if($turnValueToCompare == -1)
						{
								$turnValueToCompare = $turnsForPlayer;
						}
						elseif($turnsForPlayer != $turnValueToCompare)
						{ // this is a different value
							//throw new feException( "turns for player $turnsForPlayer and turnValueToCompare $turnValueToCompare" );
								return false;
						}
				}

				return true;
		}

		// Called once a player has ended their turn.
		function endPlayerTurn()
		{
				$playerWhoseTurnItWas = self::getActivePlayerId(); // Current Player = player who played the current player action (the one who made the AJAX request). In general, only use this in multiplayer states. Active Player = player whose turn it is.
				$nameOfPlayerWhoseTurnItWas = $this->getPlayerNameFromPlayerId($playerWhoseTurnItWas);

//throw new feException( "incrementing stat for $playerWhoseTurnItWas" );

				self::incStat( 1, 'turns_number', $playerWhoseTurnItWas ); // increase end game player stat
				self::incStat( 1, 'turns_number' ); // increase end game table stat

				self::notifyAllPlayers( "endTurn", clienttranslate( '${player_name} has ended their turn.' ), array(
								'player_name' => $nameOfPlayerWhoseTurnItWas
						) );

				if($this->isTurnOrderClockwise())
				{ // the turn order is going clockwise
						$this->activeNextPlayer(); // go to the next player clockwise in turn order
				}
				else
				{ // the turn order is going counter-clockwise
									//throw new feException( "counterclockwise." );
						$this->activePrevPlayer(); // go to the next player counter-clockwise in turn order
				}

				if($this->haveAllPlayersTakenTheirTurn())
				{ // round is over
						$this->gamestate->setAllPlayersMultiactive(); // set all players to active
						$this->gamestate->nextState( "newRound" );
				}
				else
				{ // someone still has to globals
						$this->gamestate->nextState( "startSaucerMove" );
				}
		}

		function locateCrashedSaucersForPlayer()
		{
				// if the player has any crashed saucers, locate them

				$this->gamestate->nextState( "startSaucerMove" );
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


		// THIS FUNCTION IS FROM THE OSTRICH VERSION... REPLACED BY endPlayerTurn() IN SAUCER VERSION
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

						$this->resetSaucerDistancesAndDirectionsToMoveCard(); // in case this player has been pushed by the previous player, reset their distance and direction to their zig (NOTE: This does NOT have to be done for the first player turn so it's fine to have it here at the end of a turn)

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

		/// This is called when the saucer movement animation is occuring.
		function executingMove()
		{
				// We just need to wait for the animation to complete before we continue.
				// The UI will move us to the next state once the animation completes.
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

		function argGetAllPlayerSaucerMoves()
		{
				return array(
						'playerSaucerMoves' => self::getAllPlayerSaucerMoves()
				);
		}

		function argGetSaucerAcceleratorAndBoosterMoves()
		{
				return array(
						'playerSaucerAcceleratorMoves' => self::getSaucerAcceleratorAndBoosterMoves()
				);
		}

		function argGetMoveCardButtons()
		{
				return array(
						'moveCardButtons' => self::getMoveCardButtons()
				);
		}

		function argGetSaucerGoFirstButtons()
		{
				return array(
						'saucerButtons' => self::getSaucerGoFirstButtons()
				);
		}

		function argGetPlayersWithOstriches()
		{
			return array(
					'allPlayersWithOstriches' => self::getPlayersWithOstriches()
			);
		}

		function argGetOstriches()
		{
				return array(
						'allOstriches' => self::getAllSaucersByPlayer()
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
