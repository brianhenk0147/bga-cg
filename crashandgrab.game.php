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

				$this->upgradeCards = self::getNew( "module.common.deck" );
        $this->upgradeCards->init( "upgradeCards" );
				$this->upgradeCards->autoreshuffle_custom = array('upgradeCardDeck' => 'discard');
				$this->upgradeCards->autoreshuffle = true; // automatically reshuffle when you run out of cards

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
				//$this->GRAYCOLOR = "c9d2db";
				$this->ORANGECOLOR = "e77324";


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
			  /************ Required setup for game engine *****/

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
        $sql .= implode( ',', $values );
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

				$this->initializeStartingEnergy();

				$this->initializeMoveCards();

				$this->dealMoveCards();

				 $this->initializeTrapCards();
				 $this->initializeUpgradeCards();


        // TODO: setup the initial game situation here

				$this->setGameStateValue("CURRENT_ROUND", 1); // start on round 1



        // Activate first player (which is in general a good idea :) )
        //$this->activeNextPlayer();
				$this->gamestate->setAllPlayersMultiactive(); // set all players to active since everyone will have to choose a move card

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
				$saucers = $this->getSaucersForPlayer($player_id);



				// get this player's saucers and all of this player's move cards
				$result['saucer1'] = '';
				$result['saucer2'] = '';
				$result['hand'] = null;
				foreach( $saucers as $saucer )
				{ // go through each saucer owned by the player
						$saucerColor = $saucer['ostrich_color'];

self::warn("<b>Saucer Color:</b> $saucerColor"); // log to sql database

								if(is_null($result['hand']))
								{ // first saucer
self::warn("<b>HAND NULL</b>"); // log to sql database

										// save the color representing saucer 1
										$result['saucer1'] = $saucerColor;

										$result['hand'] = $this->movementCards->getCardsInLocation( $saucerColor ); // get the cards for this saucer
										//$result['hand'] = $this->movementCards->getCardsInLocation( 'hand' ); // get the cards for this saucer

								}
								else
								{ // they had a second saucer

self::warn("<b>HAND not NULL</b>"); // log to sql database

										// save the color representing saucer 2
										$result['saucer2'] = $saucerColor;

										// merge their other saucer movement cards with this saucer
										$result['hand'] = array_merge($result['hand'], $this->movementCards->getCardsInLocation( $saucerColor ) ); // merge their other saucer with this saucer

								}
				}

				$result['chosenMoveCards'] = $this->getChosenMoveCards($player_id);

				$result['upgradeCardContent'] = $this->getAllUpgradeCardContent();
				$result['playedUpgrades'] = $this->getAllPlayedUpgradesBySaucer();




				// get the board layout
        $result['board'] = self::getObjectListFromDB( "SELECT board_x x, board_y y, board_space_type space_type
                                                       FROM board
                                                       WHERE board_space_type IS NOT NULL" );

  			// get the ostrich positions
				$result['ostrich'] = self::getObjectListFromDB( "SELECT ostrich_x x,ostrich_y y, ostrich_color color, ostrich_owner owner, ostrich_last_direction last_direction, ostrich_has_zag has_zag, ostrich_has_crown, booster_quantity, energy_quantity
				                                               FROM ostrich
				                                               WHERE 1 ORDER BY ostrich_owner, ostrich_color" );

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
				self::initStat( 'player', 'crewmembers_picked_up', 0 );
				self::initStat( 'player', 'crewmembers_you_stole', 0 );

				self::initStat( 'player', 'crewmembers_stolen_from_you', 0 );
				self::initStat( 'player', 'saucers_you_crashed', 0 );
				self::initStat( 'player', 'times_you_were_crashed', 0 );
				self::initStat( 'player', 'times_you_crashed_yourself', 0 );
				self::initStat( 'player', '2s_played', 0 );

				self::initStat( 'player', '3s_played', 0 );
				self::initStat( 'player', 'Xs_played', 0 );

				self::initStat( 'player', 'accelerators_used', 0 );
				self::initStat( 'player', 'distance_moved', 0 );
				self::initStat( 'player', 'upgrades_played', 0 );
				self::initStat( 'player', 'upgrades_activated', 0 );
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

		function initializeUpgradeCards()
		{
				// Create Movement Cards
				// type: Deface Paint, Twirlybird
				// type_arg: probably don't need... should mimic card id

				$cardsList = array(
						array( 'type' => 'Blast Off Thrusters', 'type_arg' => 1, 'card_location' => 'deck', 'nbr' => 8),
						array( 'type' => 'Wormhole Generator', 'type_arg' => 2, 'card_location' => 'deck', 'nbr' => 1),
						array( 'type' => 'Afterburner', 'type_arg' => 3, 'card_location' => 'deck', 'nbr' => 8),
						array( 'type' => 'Tractor Beam', 'type_arg' => 5, 'card_location' => 'deck', 'nbr' => 1),
						array( 'type' => 'Saucer Teleporter', 'type_arg' => 6, 'card_location' => 'deck', 'nbr' => 1),
						array( 'type' => 'Cloaking Device', 'type_arg' => 7, 'card_location' => 'deck', 'nbr' => 1),
						array( 'type' => 'Hyperdrive', 'type_arg' => 9, 'card_location' => 'deck','nbr' => 1),
						array( 'type' => 'Scavenger Bot', 'type_arg' => 10, 'card_location' => 'deck','nbr' => 1),
						array( 'type' => 'Distress Signaler', 'type_arg' => 11, 'card_location' => 'deck','nbr' => 1),
						array( 'type' => 'Time Machine', 'type_arg' => 12, 'card_location' => 'deck','nbr' => 1),
						array( 'type' => 'Regeneration Gateway', 'type_arg' => 13, 'card_location' => 'deck','nbr' => 1),
						array( 'type' => 'Phase Shifter', 'type_arg' => 14, 'card_location' => 'deck','nbr' => 1),
						array( 'type' => 'Cargo Hold', 'type_arg' => 15, 'card_location' => 'deck','nbr' => 1),
						array( 'type' => 'Proximity Mines', 'type_arg' => 16, 'card_location' => 'deck','nbr' => 1),
						array( 'type' => 'Landing Legs', 'type_arg' => 17, 'card_location' => 'deck','nbr' => 8),
						array( 'type' => 'Airlock', 'type_arg' => 20, 'card_location' => 'deck','nbr' => 1)
				);

				if($this->getNumberOfPlayers() > 2)
				{
						array_push($cardsList, array( 'type' => 'Rotational Stabilizer', 'type_arg' => 19, 'card_location' => 'deck','nbr' => 1));
				}


				$this->upgradeCards->createCards( $cardsList, 'deck' ); // create the deck

				// shuffle the deck
				$this->upgradeCards->shuffle( 'deck' );
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

		function initializeStartingEnergy()
		{
				$allSaucers = $this->getAllSaucers();
				foreach( $allSaucers as $saucer )
				{ // go through each saucer

						$saucerColor = $saucer['ostrich_color'];
						$this->incrementEnergyForSaucer($saucerColor);
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
										$this->giveSaucerBooster($saucerColor); // give this Saucer a Booster
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
//throw new feException( "all other player counts");
						// give Probe to the player going first
						$playerGoingFirstSaucers = $this->getSaucersForPlayer($playerIdGoingFirst);
						foreach( $playerGoingFirstSaucers as $saucer )
						{ // go through each saucer
								$saucerColor = $saucer['ostrich_color'];

								//$this->locatePilot($saucerColor); // locate this Saucer's Pilot Crewmember = $saucer['ostrich_color'];
								$this->giveProbe($saucerColor); // saucer of player going first gets the Probe

								$this->gamestate->changeActivePlayer( $playerIdGoingFirst ); // make probe owner go first in turn order
						}
//throw new feException( "hamburger");
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
									//throw new feException( "sausage");
										$this->giveSaucerBooster($saucerColor); // give this Saucer a Booster
								}
						}
//throw new feException( "hot dog");
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
//throw new feException( "end of initializeStartingBonuses");
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
				{ // go through each player
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
				$possibleColors = array( "f6033b", "01b508", "0090ff", "fedf3d", "b92bba", "e77324" );

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

		function convertDistanceTypeToString($distanceType)
		{
				switch($distanceType)
				{
						case "0":
						case 0:
						return "X";

						case "1":
						case 1:
						return "2";

						case "2":
						case 2:
						return "3";
				}
		}

		// returns the given saucer color in text form in its color (example: "RED" where "RED" is in the color red)
		function convertColorToHighlightedText($saucerColor)
		{
				$saucerColorText = $this->convertColorToText($saucerColor);
				$saucerColorHex = $this->convertFriendlyColorToHex($saucerColorText);
				$colorHighlightedText = '<span style="color:#'.$saucerColorHex.'; font-weight:bold">'.$saucerColorText.'</span>';

				return $colorHighlightedText;
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
						case $this->ORANGECOLOR:
							return clienttranslate('ORANGE');
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
						case "ORANGE":
							return $this->ORANGECOLOR;
				}

				return "";
		}

		function convertGarmentTypeIntToString($garmentAsInt)
		{
				switch($garmentAsInt)
				{
						case 0:
							return "pilot";
						case 1:
							return "engineer";
						case 2:
							return "doctor";
						case 3:
							return "scientist";
				}

				return "";
		}

		function convertGarmentTypeStringToInt($garmentAsString)
		{
				switch($garmentAsString)
				{
						case "pilot":
						case "head":
							return 0;
						case "engineer":
						case "body":
							return 1;
						case "doctor":
						case "legs":
							return 2;
						case "scientist":
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

		function getDirectionFromLocation($saucerColor, $xLocation, $yLocation)
		{
				$currentXLocation = $this->getSaucerXLocation($saucerColor);
				$currentYLocation = $this->getSaucerYLocation($saucerColor);

				if($currentXLocation == $xLocation)
				{ // we are moving up or down
						if($yLocation > $currentYLocation)
						{ // we are moving down
								return "meteor";
						}
						else
						{ // we are moving up
								return "sun";
						}
				}

				if($currentYLocation == $yLocation)
				{ // we are moving left or right
						if($xLocation > $currentXLocation)
						{ // we are moving right
								return "asteroids";
						}
						else
						{ // we are moving left
								return "constellation";
						}
				}

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

		function getSaucerToPlaceButton()
		{
				$result = array();

				$currentState = $this->getStateName();
				$saucerToPlace = "unknown";
				if($currentState == "endRoundPlaceCrashedSaucer")
				{ // we are placing a crashed saucer at the end of a round
						$saucerToPlace = $this->getSaucerThatCrashed();
				}
				elseif($currentState == "askPreTurnToPlaceCrashedSaucer")
				{ // we are placing a crashed saucer before a player's turn
						$saucerToPlace = $this->getOstrichWhoseTurnItIs();
				}

				$saucerColorFriendly = $this->convertColorToText($saucerToPlace);

				$result['saucerColor'] = $saucerToPlace;
				$result['buttonLabel'] = $saucerColorFriendly;
				$result['hoverOverText'] = '';
				$result['actionName'] = 'selectSaucerToPlace';
				$result['isDisabled'] = false;
				$result['makeRed'] = false;

				return $result;
		}

		function getSaucerGoFirstButtons()
		{
				$result = array();

				//$currentPlayer = self::getCurrentPlayerId(); // Current Player = player who played the current player action (the one who made the AJAX request). Active Player = player whose turn it is.
				$activePlayer = self::getActivePlayerId(); // Current Player = player who played the current player action (the one who made the AJAX request). Active Player = player whose turn it is.


				// all saucers for this player
				$sqlGetSaucerColors = "SELECT ostrich_color ";
				$sqlGetSaucerColors .= "FROM ostrich ";
				$sqlGetSaucerColors .= "WHERE ostrich_owner=$activePlayer";
				$usedColors = self::DbQuery( $sqlGetSaucerColors );

				$index = 0;
				while( $ostrich = mysql_fetch_assoc( $usedColors ) )
				{ // go through the saucers owned by this player
						$playerColor = $ostrich['ostrich_color']; // get the color this player was assigned
						$playerColorFriendly = $this->convertColorToText($playerColor);

						$result[$index] = array();
						$result[$index]['saucerColor'] = $playerColor;
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

		function notifyPlayersOfCardSelection($cardOwnerId, $saucerColor)
		{
				$otherPlayers = $this->getAllPlayersExcept($cardOwnerId);

				foreach( $otherPlayers as $player )
				{ // go through each saucer

						$playerId = $player['player_id'];

						self::notifyPlayer( $playerId, 'cardChosen', '', array(
							'saucer_choosing' => $saucerColor
						 ) );

				}

		}

		function getCardChosenState($cardId)
		{
				return self::getUniqueValueFromDb("SELECT card_chosen_state FROM movementCards WHERE card_id=$cardId");
		}

		function setCardChosenState($saucer1CardId, $newValue)
		{
				$sqlUpdate = "UPDATE movementCards SET ";
				$sqlUpdate .= "card_chosen_state='$newValue' WHERE ";
				$sqlUpdate .= "card_id=$saucer1CardId";

				self::DbQuery( $sqlUpdate );
		}

		function resetAllCardChosenState()
		{
				$sqlUpdate = "UPDATE movementCards SET ";
				$sqlUpdate .= "card_chosen_state='unchosen'";

				self::DbQuery( $sqlUpdate );
		}

		function resetAllUpgradeValues()
		{
				$sqlUpdate = "UPDATE upgradeCards SET ";
				$sqlUpdate .= "value_1='0',value_2='0'";

				self::DbQuery( $sqlUpdate );
		}

		function getMoveCardIdFromSaucerDistanceType($saucerColor, $distanceType)
		{
				$cardId = ''; // default to empty

				$cardIds = self::getObjectListFromDB( "SELECT card_id FROM movementCards WHERE card_location='$saucerColor' AND card_type_arg=$distanceType" );

				foreach( $cardIds as $id )
				{ // there will likely just be 1

						$cardId = $id['card_id'];
				}

				return $cardId;
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

		function getAllUnoccupiedCrashSites()
		{
				$result = array();

				$allCrashSites = $this->getAllCrashSites();

				$locX = 15;
				$locY = 15;
				$crashSiteIndex = -1;
				foreach( $allCrashSites as $crashSite )
				{ // go through each crash site
						$crashSiteIndex++;
						$locX = $crashSite['board_x'];
						$locY = $crashSite['board_y'];

						$saucerHere = $this->getOstrichAt($locX, $locY); // see if a saucer is here
						$crewmemberHere = $this->getGarmentIdAt($locX, $locY); // see if a crewmember is here

						//throw new feException( "At X=".$locX." and Y=".$locY." we have saucerHere=".$saucerHere." and crewmemberHere=".$crewmemberHere);

						if($crewmemberHere != 0 || $saucerHere != "")
						{ // there already a saucer or crewmember here
								//throw new feException( "We are continuing because at X=".$locX." and Y=".$locY." we have saucerHere=".$saucerHere." and crewmemberHere=".$crewmemberHere);

								// go to next crash site
								continue;
						}
						else
						{ // this crash site is unoccupied

								$result[$crashSiteIndex] = $this->getBoardSpaceType($locX, $locY); // 1, 2, 3
						}
				}

				//$count = count($result);
				//throw new feException( "Count:".$count);

				// sort the crash sites
				sort($result);

				return $result;
		}

		function getAllSpacesNotInCrewmemberRowColumn()
		{
				$result = array();

				$allSpaces = self::getObjectListFromDB( "SELECT board_x, board_y
																							FROM board" );

				$validSpaceId = 0;
				foreach($allSpaces as $space)
				{ // go through all spaces
						$column = $space['board_x'];
						$row = $space['board_y'];

						$saucerHere = $this->getOstrichAt($column, $row);

						if(!$this->isInCrewmemberRowOrColumn($row, $column) &&
						 $row != 0 && $column != 0 &&
						 	$row != $this->getMaxRows() && $column != $this->getMaxColumns() &&
							$saucerHere == "")
						{ // this space is NOT in the row or column of a crewmember, is not off the edge of the board, and does not have a Saucer in it

								// format it in a consistent way so we can understand it on the front end
								$formattedSpace = $column.'_'.$row;

								// add this space to the list
								array_push($result, $formattedSpace);
						}
				}

				return $result;
		}

		function isInCrewmemberRowOrColumn($row, $column)
		{
				$allCrewmembers = $this->getAllCrewmembers();
				foreach( $allCrewmembers as $crewmember )
				{ // go through each crewmember
						$crewmemberX = $crewmember['garment_x'];
						$crewmemberY = $crewmember['garment_y'];

						if($crewmemberX == $column || $crewmemberY == $row)
						{
								return true;
						}
				}

				return false;
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

								if($specificMoveCard == '')
										$distanceType = 3; // go max distance

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
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				switch($distanceType)
				{ // 0=X, 1=2, 2=3, 3=max
						case 3: // max
						case 0: // X

								$row = $startRow;
								$column = $startColumn;

								$space = array();
								$space['row'] = $row;
								$space['column'] = $column;
								array_push($result, $space); // add this space to the list of move destinations

								$maxDistance = 5;
								if($distanceType == 3)
								{ // max distance
										$maxDistance = 20;
								}
								elseif($this->getUpgradeTimesActivatedThisRound($saucerWhoseTurnItIs, "Hyperdrive") > 0)
								{ // this player activated hyperdrive this round
										$maxDistance = $maxDistance * 2;
								}

//throw new feException( "maxDistance: $maxDistance");
								switch($direction)
								{
										case $this->UP_DIRECTION:
												return $this->getSpacesInColumnUp($startRow, $startColumn, $maxDistance);
										case $this->DOWN_DIRECTION:
												return $this->getSpacesInColumnDown($startRow, $startColumn, $maxDistance);
										break;

										case $this->RIGHT_DIRECTION:
												return $this->getSpacesInRowRight($startColumn, $startRow, $maxDistance);
										case $this->LEFT_DIRECTION:
												return $this->getSpacesInRowLeft($startColumn, $startRow, $maxDistance);
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

								if($this->getUpgradeTimesActivatedThisRound($saucerWhoseTurnItIs, "Hyperdrive") > 0)
								{ // this player activated hyperdrive this round
										$offset = $offset * 2;
								}

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
																					 FROM ostrich ORDER BY ostrich_owner, ostrich_color" );
		}

		function getAllPlayers()
		{
				return self::getObjectListFromDB( "SELECT *
																					 FROM player" );
		}

		function getAllPlayersExcept($playerId)
		{
				return self::getObjectListFromDB( "SELECT *
																					 FROM player
																					 WHERE player_id<>$playerId" );
		}

		function getAllSaucersNotOwnedByPlayer($playerId)
		{
				return self::getObjectListFromDB( "SELECT *
																					 FROM ostrich
																					 WHERE ostrich_owner<>$playerId" );
		}

		// Returns all saucers other than the one in the argument as text like RED.
		function getAllOtherSaucers($saucerToSkip)
		{
				$result = array();

				$allOstriches = self::getObjectListFromDB( "SELECT ostrich_color, ostrich_owner
																					 FROM ostrich ORDER BY ostrich_owner" );

				foreach( $allOstriches as $ostrich )
				{
						$colorAsHex = $ostrich['ostrich_color']; // ffffff
						$colorAsText = $this->convertColorToText($colorAsHex); // RED

						if($colorAsHex != $saucerToSkip)
						{
								array_push($result, $colorAsText);
						}
				}

				return $result;
		}

		// Returns all saucers of other players other than the one in the argument as text like ff0000.
		function getAllOtherPlayerSaucersHex($saucerToSkip)
		{
				$result = array();

				$allOstriches = self::getObjectListFromDB( "SELECT ostrich_color, ostrich_owner
																					 FROM ostrich ORDER BY ostrich_owner" );

			  $ownerOfSaucerToSkip = $this->getOwnerIdOfOstrich($saucerToSkip);
				foreach( $allOstriches as $ostrich )
				{
						$colorAsHex = $ostrich['ostrich_color']; // ffffff
						$ownerOfThisSaucer = $this->getOwnerIdOfOstrich($colorAsHex);

						if($ownerOfSaucerToSkip != $ownerOfThisSaucer)
						{ // this saucer has a different owning player than the saucer to skip

								array_push($result, $colorAsHex);
						}
				}

				return $result;
		}

		function getOtherUncrashedSaucers()
		{
				$result = array();
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				$allOtherSaucers = self::getObjectListFromDB( "SELECT ostrich_color, ostrich_owner
																					 FROM ostrich WHERE ostrich_color<>'$saucerWhoseTurnItIs' ORDER BY ostrich_owner" );


				foreach( $allOtherSaucers as $saucer )
				{
						$saucerColor = $saucer['ostrich_color'];
						$saucerColorText = $this->convertColorToText($saucerColor);

						$saucerDetails = array();
						$saucerDetails['saucerColor'] = $saucerColor;
						$saucerDetails['saucerColorText'] = $saucerColorText;

						$isCrashed = $this->isSaucerCrashed($saucerColor);
						if(!$isCrashed)
						{ // they are not crashed
								array_push($result, $saucerDetails);
						}
				}

				return $result;
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

		function getAllPlayedEndOfTurnUpgradesForSaucer($saucerColor)
		{
				$result = array();

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Wormhole Generator'))
				{
						$upgradeArray = array();
						$upgradeArray['collectorNumber'] = 2;
						$upgradeArray['upgradeName'] = $this->getUpgradeTitleFromCollectorNumber(2);

						array_push($result, $upgradeArray);
				}

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Afterburner'))
				{
						$upgradeArray = array();
						$upgradeArray['collectorNumber'] = 3;
						$upgradeArray['upgradeName'] = $this->getUpgradeTitleFromCollectorNumber(3);

						array_push($result, $upgradeArray);
				}

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Tractor Beam'))
				{
						$upgradeArray = array();
						$upgradeArray['collectorNumber'] = 5;
						$upgradeArray['upgradeName'] = $this->getUpgradeTitleFromCollectorNumber(5);

						array_push($result, $upgradeArray);
				}

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Saucer Teleporter'))
				{
						$upgradeArray = array();
						$upgradeArray['collectorNumber'] = 6;
						$upgradeArray['upgradeName'] = $this->getUpgradeTitleFromCollectorNumber(6);

						array_push($result, $upgradeArray);
				}

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Cloaking Device'))
				{
						$upgradeArray = array();
						$upgradeArray['collectorNumber'] = 7;
						$upgradeArray['upgradeName'] = $this->getUpgradeTitleFromCollectorNumber(7);

						array_push($result, $upgradeArray);
				}

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Distress Signaler'))
				{
						$upgradeArray = array();
						$upgradeArray['collectorNumber'] = 11;
						$upgradeArray['upgradeName'] = $this->getUpgradeTitleFromCollectorNumber(11);

						array_push($result, $upgradeArray);
				}

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Landing Legs'))
				{
						$upgradeArray = array();
						$upgradeArray['collectorNumber'] = 17;
						$upgradeArray['upgradeName'] = $this->getUpgradeTitleFromCollectorNumber(17);

						array_push($result, $upgradeArray);
				}

				return $result;
		}

		function doesPlayerHaveAnyEndOfTurnUpgradesToActivate($saucerColor)
		{
				//$isWormholePlayed = $this->doesSaucerHaveUpgradePlayed($saucerColor, 'Wormhole Generator');
				//$isWormholdActivated = $this->getUpgradeTimesActivatedThisRound($saucerColor, 'Wormhole Generator');
				//throw new feException( "saucer:$saucerColor isWormholePlayed:$isWormholePlayed isWormholdActivated:$isWormholdActivated");

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Wormhole Generator') &&
				   $this->getUpgradeTimesActivatedThisRound($saucerColor, 'Wormhole Generator') < 1 &&
					 $this->getAskedToActivateUpgrade($saucerColor, 'Wormhole Generator') == false)
				{ // they have played this upgrade but they have not yet activated it

						return true;
				}

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Afterburner') &&
				   $this->getUpgradeTimesActivatedThisRound($saucerColor, 'Afterburner') < 1 &&
					 $this->getAskedToActivateUpgrade($saucerColor, 'Afterburner') == false)
				{ // they have played this upgrade, they have not yet activated it, and they have not yet indicated whether they want to activate it

						if(!$this->isSaucerCrashed($saucerColor))
						{ // they are not crashed

								return true;
						}
				}

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Tractor Beam') &&
				   $this->getUpgradeTimesActivatedThisRound($saucerColor, 'Tractor Beam') < 1 &&
					 $this->getAskedToActivateUpgrade($saucerColor, 'Tractor Beam') == false)
				{ // they have played this upgrade, they have not yet activated it, and they have not yet indicated whether they want to activate it

						if(!$this->isSaucerCrashed($saucerColor))
						{ // they are not crashed

								$crewmembersWithin2 = $this->getCrewmembersWithin2($saucerColor);
								if(count($crewmembersWithin2) > 0)
								{ // there is a crewmember within 2 of saucer

										return true;
								}
						}
				}

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Saucer Teleporter') &&
				   $this->getUpgradeTimesActivatedThisRound($saucerColor, 'Saucer Teleporter') < 1 &&
					 $this->getAskedToActivateUpgrade($saucerColor, 'Saucer Teleporter') == false)
				{ // they have played this upgrade but they have not yet activated it

						if(!$this->isSaucerCrashed($saucerColor))
						{ // they are not crashed

								return true;
						}
				}


				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Cloaking Device') &&
				   $this->getUpgradeTimesActivatedThisRound($saucerColor, 'Cloaking Device') < 1 &&
					 $this->getAskedToActivateUpgrade($saucerColor, 'Cloaking Device') == false)
				{ // they have played this upgrade but they have not yet activated it

					//$cloakingDeviceTimesActivated = $this->getUpgradeTimesActivatedThisRound($saucerColor, 'Cloaking Device');
					//throw new feException( "true saucer:$saucerColor cloakingDeviceTimesActivated:$cloakingDeviceTimesActivated");

						if(!$this->isSaucerCrashed($saucerColor))
						{ // they are not crashed

								return true;
						}
				}

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Distress Signaler') &&
				   $this->getUpgradeTimesActivatedThisRound($saucerColor, 'Distress Signaler') < 1 &&
					 $this->getAskedToActivateUpgrade($saucerColor, 'Distress Signaler') == false)
				{ // they have played this upgrade, they have not yet activated it, and they have not yet indicated whether they want to activate it

						$distressSignalableCrewmembers = $this->getDistressSignalableTakeCrewmembers($saucerColor);
						if(count($distressSignalableCrewmembers) > 0)
						{
								return true;
						}
				}

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Landing Legs') &&
				   $this->getUpgradeTimesActivatedThisRound($saucerColor, 'Landing Legs') < 1 &&
					 $this->getAskedToActivateUpgrade($saucerColor, 'Landing Legs') == false)
				{ // they have played this upgrade, they have not yet activated it, and they have not yet indicated whether they want to activate it

						if(!$this->isSaucerCrashed($saucerColor))
						{ // they are not crashed

								return true;
						}
				}

				//$cloakingDeviceTimesActivated = $this->getUpgradeTimesActivatedThisRound($saucerColor, 'Cloaking Device');
				//throw new feException( "false saucer:$saucerColor cloakingDeviceTimesActivated:$cloakingDeviceTimesActivated");

//throw new feException( "doesPlayerHaveAnyEndOfTurnUpgrades false");
				return false;
		}

		function getUpgradeTitleFromCollectorNumber($collectorNumber)
		{
				switch($collectorNumber)
				{
						case 1:
								return clienttranslate( 'Blast Off Thrusters');

						case 2:
								return clienttranslate( 'Wormhole Generator');

						case 3:
								return clienttranslate( 'Afterburner');

						case 5:
								return clienttranslate( 'Tractor Beam');

						case 6:
								return clienttranslate( 'Saucer Teleporter');

						case 7:
								return clienttranslate( 'Cloaking Device');

						case 9:
								return clienttranslate( 'Hyperdrive');

						case 10:
								return clienttranslate( 'Scavenger Bot');

						case 11:
								return clienttranslate( 'Distress Signaler');

						case 12:
								return clienttranslate( 'Time Machine');

						case 13:
								return clienttranslate( 'Regeneration Gateway');

						case 14:
								return clienttranslate( 'Phase Shifter');

						case 15:
								return clienttranslate( 'Cargo Hold');

						case 16:
								return clienttranslate( 'Proximity Mines');

						case 17:
								return clienttranslate( 'Landing Legs');

						case 19:
								return clienttranslate( 'Rotational Stabilizer');

						case 20:
							return clienttranslate( 'Airlock');
				}
		}

		function getUpgradeEffectFromCollectorNumber($collectorNumber)
		{
				switch($collectorNumber)
				{
						// Blast Off Thrusters
						case 1:
								return clienttranslate( 'At the start of your turn, move 1 space onto an empty space.');

						// Wormhole Generator
						case 2:
								return clienttranslate( 'At the end of your turn, swap locations with any Saucer.');

						// Afterburner
						case 3:
								return clienttranslate( 'At the end of your turn, move onto any empty space in your row or column.');

						// Tractor Beam
						case 5:
								return clienttranslate( 'At the end of your turn, pick up a Crewmember up to 2 spaces away from you.');

						// Saucer Teleporter
						case 6:
								return clienttranslate( 'At the end of your turn, if you have not crashed, move to any empty Crash Site.');

						// Cloaking Device
						case 7:
								return clienttranslate( 'At the end of your turn, remove your Saucer from the board and place it at the end of the round.');

						// Hyperdrive
						case 9:
								return clienttranslate( 'Double your movement.');

						// Scavenger Bot
						case 10:
								return clienttranslate( 'When your Saucer is located, take your Booster and an Energy.');

						// Distress Signaler
						case 11:
								return clienttranslate( 'At the end of your turn, take a Crewmember of your color from any Saucer and give them one of the same type.');

						// Time Machine
						case 12:
								return clienttranslate( 'Choose your Move Card direction after you reveal it.');

						// Regeneration Gateway
						case 13:
								return clienttranslate( 'When your Saucer is located, you choose the Crash Site.');

						// Phase Shifter
						case 14:
								return clienttranslate( 'Move through other Saucers.');

						// Cargo Hold
						case 15:
								return clienttranslate( 'Take a Booster. You may hold an additional Booster.');

						// Proximity Mines
						case 16:
								return clienttranslate( 'Crash any Saucer you collide with instead of pushing it.');

						// Landing Legs
						case 17:
								return clienttranslate( 'At the end of your turn, move one space in any direction.');

						// Rotational Stabilizer
						case 19:
								return clienttranslate( 'At the start of the round, you choose whether the turn order is clockwise or counter-clockwise.');

						// Airlock
						case 20:
								return clienttranslate( 'When you pick up a Crewmember, you may exchange it with any other Crewmember on the board.');
				}
		}

		function getAllUpgradeCardContent()
		{
				$result = array();

				// Upgrade Back
				$result[0] = array();
				$result[0]['name'] = '';
				$result[0]['effect'] = '';

				// Blast Off Thrusters
				$result[1] = array();
				$result[1]['name'] = $this->getUpgradeTitleFromCollectorNumber(1);
				$result[1]['effect'] = $this->getUpgradeEffectFromCollectorNumber(1);

				// Wormhole Generator
				$result[2] = array();
				$result[2]['name'] = $this->getUpgradeTitleFromCollectorNumber(2);
				$result[2]['effect'] = $this->getUpgradeEffectFromCollectorNumber(2);

				// Afterburner
				$result[3] = array();
				$result[3]['name'] = $this->getUpgradeTitleFromCollectorNumber(3);
				$result[3]['effect'] = $this->getUpgradeEffectFromCollectorNumber(3);

				// Tractor Beam
				$result[5] = array();
				$result[5]['name'] = $this->getUpgradeTitleFromCollectorNumber(5);
				$result[5]['effect'] = $this->getUpgradeEffectFromCollectorNumber(5);

				// Saucer Teleporter
				$result[6] = array();
				$result[6]['name'] = $this->getUpgradeTitleFromCollectorNumber(6);
				$result[6]['effect'] = $this->getUpgradeEffectFromCollectorNumber(6);

				// Cloaking Device
				$result[7] = array();
				$result[7]['name'] = $this->getUpgradeTitleFromCollectorNumber(7);
				$result[7]['effect'] = $this->getUpgradeEffectFromCollectorNumber(7);

				// Hyperdrive
				$result[9] = array();
				$result[9]['name'] = $this->getUpgradeTitleFromCollectorNumber(9);
				$result[9]['effect'] = $this->getUpgradeEffectFromCollectorNumber(9);

				// Scavenger Bot
				$result[10] = array();
				$result[10]['name'] = $this->getUpgradeTitleFromCollectorNumber(10);
				$result[10]['effect'] = $this->getUpgradeEffectFromCollectorNumber(10);

				// Distress Signaler
				$result[11] = array();
				$result[11]['name'] = $this->getUpgradeTitleFromCollectorNumber(11);
				$result[11]['effect'] = $this->getUpgradeEffectFromCollectorNumber(11);

				// Time Machine
				$result[12] = array();
				$result[12]['name'] = $this->getUpgradeTitleFromCollectorNumber(12);
				$result[12]['effect'] = $this->getUpgradeEffectFromCollectorNumber(12);

				// Regeneration Gateway
				$result[13] = array();
				$result[13]['name'] = $this->getUpgradeTitleFromCollectorNumber(13);
				$result[13]['effect'] = $this->getUpgradeEffectFromCollectorNumber(13);

				// Phase Shifter
				$result[14] = array();
				$result[14]['name'] = $this->getUpgradeTitleFromCollectorNumber(14);
				$result[14]['effect'] = $this->getUpgradeEffectFromCollectorNumber(14);

				// Cargo Hold
				$result[15] = array();
				$result[15]['name'] = $this->getUpgradeTitleFromCollectorNumber(15);
				$result[15]['effect'] = $this->getUpgradeEffectFromCollectorNumber(15);

				// Proximity Mines
				$result[16] = array();
				$result[16]['name'] = $this->getUpgradeTitleFromCollectorNumber(16);
				$result[16]['effect'] = $this->getUpgradeEffectFromCollectorNumber(16);

				// Landing Legs
				$result[17] = array();
				$result[17]['name'] = $this->getUpgradeTitleFromCollectorNumber(17);
				$result[17]['effect'] = $this->getUpgradeEffectFromCollectorNumber(17);

				// Rotational Stabilizer
				$result[19] = array();
				$result[19]['name'] = $this->getUpgradeTitleFromCollectorNumber(19);
				$result[19]['effect'] = $this->getUpgradeEffectFromCollectorNumber(19);

				// Airlock
				$result[20] = array();
				$result[20]['name'] = $this->getUpgradeTitleFromCollectorNumber(20);
				$result[20]['effect'] = $this->getUpgradeEffectFromCollectorNumber(20);

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

		function getDistressSignalableGiveCrewmembers($saucerColor)
		{
				$giveableCrewmembers = array();

				// get crewmember taken ID
				$crewmemberTakenId = $this->getCrewmemberIdTakenWithDistress();
				if($crewmemberTakenId == '')
						return $giveableCrewmembers;

				// get crewmember taken type
				$crewmemberTakenTypeId = $this->getCrewmemberTypeIdFromId($crewmemberTakenId);
				$crewmemberTakenTypeString = $this->convertGarmentTypeIntToString($crewmemberTakenTypeId);

				// get all crewmembers the saucer whose turn it is has taken of that type
				$crewmembersOnSaucer = $this->getCrewmembersOnSaucer($saucerColor);
				foreach( $crewmembersOnSaucer as $crewmember )
				{ // go through each crewmember on this saucer
						$crewmemberTypeOnSaucer = $crewmember['garment_type'];
						$crewmemberIdOnSaucer = $crewmember['garment_id'];

						if($crewmemberTypeOnSaucer == $crewmemberTakenTypeId)
						{ // we found a matching type on this saucer

								if($crewmemberIdOnSaucer != $crewmemberTakenId)
								{ // this crewmember isn't the one we just took
										array_push($giveableCrewmembers, $crewmember);
								}
						}
				}

				return $giveableCrewmembers;
		}

		function getDistressSignalableTakeCrewmembers($saucerAsking)
		{
				$distressSignalableCrewmembers = array();
				$saucerAskingCrewmembersOnOtherSaucer = array();

				// get all crewmembers of this saucer color on other saucers
				$allCrewmembers = $this->getAllCrewmembers();
				foreach( $allCrewmembers as $crewmember )
				{ // go through each crewmember
						$saucerWhoHasThis = $crewmember['garment_location'];
						$crewmemberColor = $crewmember['garment_color'];

						if($saucerWhoHasThis != 'board' && $saucerWhoHasThis != 'pile' && $crewmemberColor == $saucerAsking && $saucerWhoHasThis != $saucerAsking)
						{ // this crewmember is of our sacuer's color and is not on our saucer
								//throw new feException( "getDistressSignalableCrewmembers saucer $saucerWhoHasThis has a crewmember of color $crewmemberColor on it which matches us ($saucerAsking)");

								array_push($saucerAskingCrewmembersOnOtherSaucer, $crewmember);
						}
				}

				// include it in the list we return if it matches a type the saucer asking has
				foreach( $saucerAskingCrewmembersOnOtherSaucer as $crewmember )
				{ // go through each crewmember of our type on another saucer
						$crewmemberType = $crewmember['garment_type'];

						// see if our saucer has a matching type to this crewmember of ours on another saucer
						$hasMatchingType = $this->doesSaucerHaveCrewmemberOfType($saucerAsking, $crewmemberType);

						if($hasMatchingType)
						{ // this saucer has a matching type to this crewmember of ours on another saucer
								//throw new feException( "getDistressSignalableCrewmembers found this matching type:$crewmemberType on this saucer:$saucerAsking");
								array_push($distressSignalableCrewmembers, $crewmember);
						}
				}

				return $distressSignalableCrewmembers;
		}

		function doesSaucerHaveCrewmemberOfType($saucerColor, $crewmemberTypeAsking)
		{
				$crewmembersOnSaucer = $this->getCrewmembersOnSaucer($saucerColor);
				foreach( $crewmembersOnSaucer as $crewmember )
				{ // go through each crewmember on this saucer
						$crewmemberTypeOnSaucer = $crewmember['garment_type'];
						if($crewmemberTypeOnSaucer == $crewmemberTypeAsking)
						{ // we found a matching type on this saucer
								return true;
						}
				}

				// we did not find a matching type
				return false;
		}

		function getCrewmembersWithin2($saucerColor)
		{
				$crewmembersWithin2 = array();

				$xLocation = $this->getSaucerXLocation($saucerColor);
				$yLocation = $this->getSaucerYLocation($saucerColor);

				$crewmembersOnBoard = $this->getCrewmembersOnBoard();
				foreach($crewmembersOnBoard as $crewmember)
				{ // go through each crewmember on the board

						// get the crewmember coords
						$crewmemberX = $crewmember['garment_x'];
						$crewmemberY = $crewmember['garment_y'];

						// calculate how far they are away from the saucer in each direction
						$distanceAwayX = abs($xLocation - $crewmemberX);
						$distanceAwayY = abs($yLocation - $crewmemberY);

						if($distanceAwayX + $distanceAwayY <= 2)
						{ // it is within 2
								array_push($crewmembersWithin2, $crewmember);
						}
				}

				return $crewmembersWithin2;
		}

		function getCrewmembersSaucerCanGiveAway($saucerGivingAway)
		{
				$result = array();
				$allCrewmembersSaucerCanGiveAway = self::getObjectListFromDB( "SELECT garment_id, garment_color, garment_type
																									FROM garment
																									WHERE garment_location='$saucerGivingAway' AND garment_color<>'$saucerGivingAway'" );

				$crewmemberIndex = 0;
				foreach( $allCrewmembersSaucerCanGiveAway as $crewmember )
				{ // go through all this saucer's off-colored crewmembers

						$crewmemberColor = $crewmember['garment_color'];
						$crewmemberType = $this->convertGarmentTypeIntToString($crewmember['garment_type']);

						$result[$crewmemberIndex] = array();
						$result[$crewmemberIndex]['crewmemberType'] = $crewmemberType;
						$result[$crewmemberIndex]['crewmemberColor'] = $crewmemberColor;
						$crewmemberIndex++;
				}

				return $result;
		}

		function getStartOfTurnUpgradesToActivateForSaucer($saucerColor)
		{
				$result = array();

				$index = 0;

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Blast Off Thrusters') &&
						$this->getUpgradeTimesActivatedThisRound($saucerColor, 'Blast Off Thrusters') < 1)
				{ // they have played Blast Off Thrusters but they have not yet activated it this round

						if(!$this->isSaucerCrashed($saucerColor))
						{ // they are not crashed

								$result[$index] = array();
								$result[$index]['buttonId'] = 'upgradeButton_1';
								$result[$index]['buttonLabel'] = $this->getUpgradeTitleFromCollectorNumber(1);
								$result[$index]['hoverOverText'] = '';
								$result[$index]['actionName'] = 'activateUpgrade';
								$result[$index]['isDisabled'] = false;
								$result[$index]['makeRed'] = false;

								$index++;
						}
				}

				return $result;
		}

		function getEndOfTurnUpgradesToActivateForSaucer($saucerColor)
		{
				$result = array();

				$index = 0;

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Wormhole Generator') &&
						$this->getUpgradeTimesActivatedThisRound($saucerColor, 'Wormhole Generator') < 1)
				{ // they have played Wormhold Generator but they have not yet activated it this round

						if(!$this->isSaucerCrashed($saucerColor))
						{ // they are not crashed

								$result[$index] = array();
								$result[$index]['buttonId'] = 'upgradeButton_2';
								$result[$index]['buttonLabel'] = $this->getUpgradeTitleFromCollectorNumber(2);
								$result[$index]['hoverOverText'] = '';
								$result[$index]['actionName'] = 'activateUpgrade';
								$result[$index]['isDisabled'] = false;
								$result[$index]['makeRed'] = false;

								$index++;
						}
				}

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Afterburner') &&
						$this->getUpgradeTimesActivatedThisRound($saucerColor, 'Afterburner') < 1)
				{ // they have played Wormhold Generator but they have not yet activated it this round

						if(!$this->isSaucerCrashed($saucerColor))
						{ // they are not crashed

								$result[$index] = array();
								$result[$index]['buttonId'] = 'upgradeButton_3';
								$result[$index]['buttonLabel'] = $this->getUpgradeTitleFromCollectorNumber(3);
								$result[$index]['hoverOverText'] = '';
								$result[$index]['actionName'] = 'activateUpgrade';
								$result[$index]['isDisabled'] = false;
								$result[$index]['makeRed'] = false;

								$index++;
						}
				}

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Tractor Beam') &&
						$this->getUpgradeTimesActivatedThisRound($saucerColor, 'Tractor Beam') < 1)
				{ // they have played Tractor Beam but they have not yet activated it this round

						if(!$this->isSaucerCrashed($saucerColor))
						{ // they are not crashed

								$result[$index] = array();
								$result[$index]['buttonId'] = 'upgradeButton_5';
								$result[$index]['buttonLabel'] = $this->getUpgradeTitleFromCollectorNumber(5);
								$result[$index]['hoverOverText'] = '';
								$result[$index]['actionName'] = 'activateUpgrade';
								$result[$index]['isDisabled'] = false;
								$result[$index]['makeRed'] = false;

								$index++;
						}
				}

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Saucer Teleporter') &&
						$this->getUpgradeTimesActivatedThisRound($saucerColor, 'Saucer Teleporter') < 1)
				{ // they have played Saucer Teleporter but they have not yet activated it this round

						if(!$this->isSaucerCrashed($saucerColor))
						{ // they are not crashed
								$result[$index] = array();
								$result[$index]['buttonId'] = 'upgradeButton_6';
								$result[$index]['buttonLabel'] = $this->getUpgradeTitleFromCollectorNumber(6);
								$result[$index]['hoverOverText'] = '';
								$result[$index]['actionName'] = 'activateUpgrade';
								$result[$index]['isDisabled'] = false;
								$result[$index]['makeRed'] = false;

								$index++;
						}
				}

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Cloaking Device') &&
						$this->getUpgradeTimesActivatedThisRound($saucerColor, 'Cloaking Device') < 1)
				{ // they have played Cloaking Device but they have not yet activated it this round

						if(!$this->isSaucerCrashed($saucerColor))
						{ // they are not crashed
								$result[$index] = array();
								$result[$index]['buttonId'] = 'upgradeButton_7';
								$result[$index]['buttonLabel'] = $this->getUpgradeTitleFromCollectorNumber(7);
								$result[$index]['hoverOverText'] = '';
								$result[$index]['actionName'] = 'activateUpgrade';
								$result[$index]['isDisabled'] = false;
								$result[$index]['makeRed'] = false;

								$index++;
						}
				}

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Distress Signaler') &&
						$this->getUpgradeTimesActivatedThisRound($saucerColor, 'Distress Signaler') < 1)
				{ // they have played Distress Signaler but they have not yet activated it this round

						$distressSignalableCrewmembers = $this->getDistressSignalableTakeCrewmembers($saucerColor);
						if(count($distressSignalableCrewmembers) > 0)
						{
								$result[$index] = array();
								$result[$index]['buttonId'] = 'upgradeButton_11';
								$result[$index]['buttonLabel'] = $this->getUpgradeTitleFromCollectorNumber(11);
								$result[$index]['hoverOverText'] = '';
								$result[$index]['actionName'] = 'activateUpgrade';
								$result[$index]['isDisabled'] = false;
								$result[$index]['makeRed'] = false;

								$index++;
						}
				}

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Landing Legs') &&
						$this->getUpgradeTimesActivatedThisRound($saucerColor, 'Landing Legs') < 1)
				{ // they have played this upgrade but they have not yet activated it this round

						if(!$this->isSaucerCrashed($saucerColor))
						{ // they are not crashed

								$result[$index] = array();
								$result[$index]['buttonId'] = 'upgradeButton_17';
								$result[$index]['buttonLabel'] = $this->getUpgradeTitleFromCollectorNumber(17);
								$result[$index]['hoverOverText'] = '';
								$result[$index]['actionName'] = 'activateUpgrade';
								$result[$index]['isDisabled'] = false;
								$result[$index]['makeRed'] = false;

								$index++;
						}
				}

				return $result;
		}




		function getUpgradesToPlay()
		{
				$drawnCardsCount = $this->countDrawnCards();

				// draw 2 upgrades
				if($drawnCardsCount < 2)
				{
						$this->upgradeCards->pickCardsForLocation( 1, 'deck', 'drawn' );
				}

				$drawnCardsCount = $this->countDrawnCards();

				if($drawnCardsCount < 2)
				{
						$this->upgradeCards->pickCardsForLocation( 1, 'deck', 'drawn' );
				}


				$upgradeList = $this->upgradeCards->getCardsInLocation('drawn');

				//$count = count($upgradeList);
				//throw new feException( "getUpgradesToPlay count:$count");

				return $upgradeList;
		}

		function getPassableCrewmembersFromSaucer($saucerGiving)
		{
				$result = array();

				$saucerReceiving = $this->getPlayersOtherSaucer($saucerGiving);
//throw new feException( "getPassableCrewmembersFromSaucer saucerReceiving:$saucerReceiving");
				$saucerColorFriendlyGiver = $this->convertColorToHighlightedText($saucerGiving);
				$saucerColorFriendlyReceiver = $this->convertColorToHighlightedText($saucerReceiving);


				$allPassableCrewmembersFromCrashedSaucer = self::getObjectListFromDB( "SELECT garment_id, garment_color, garment_type
																									FROM garment
																									WHERE garment_location='$saucerGiving' AND garment_color='$saucerReceiving'" );
//$countFound = count($allPassableCrewmembersFromCrashedSaucer);
//throw new feException( "getPassableCrewmembersFromSaucer countFound:$countFound");
				$crewmemberIndex = 0;
				foreach( $allPassableCrewmembersFromCrashedSaucer as $crewmember )
				{ // go through all this saucer's matching crewmembers

						$crewmemberColor = $crewmember['garment_color'];
						$crewmemberType = $this->convertGarmentTypeIntToString($crewmember['garment_type']);

						$result[$crewmemberIndex] = array();
						$result[$crewmemberIndex]['crewmemberType'] = $crewmemberType;
						$result[$crewmemberIndex]['crewmemberColor'] = $crewmemberColor;
						$crewmemberIndex++;
				}
				//$countReturning = count($result);
				//throw new feException( "getPassableCrewmembersFromSaucer countReturning:$countReturning");

				return $result;
		}

		function getStealableCrewmembersFromSaucer($crashedSaucer)
		{
				$result = array();
				$stealerSaucer = $this->getOstrichWhoseTurnItIs(); // the only time you can steal garments is if it's your turn so it's always this ostrich who gets to steal
				$stealerOwner = $this->getOwnerIdOfOstrich($stealerSaucer);

				$saucerColorFriendlyCrashed = $this->convertColorToHighlightedText($crashedSaucer);
				$saucerColorFriendlyStealer = $this->convertColorToHighlightedText($stealerSaucer);

				$totalCrewmembersOfStealer = $this->getTotalCrewmembersForSaucer($stealerSaucer);
				$totalCrewmembersOfCrashed = $this->getTotalCrewmembersForSaucer($crashedSaucer);
				if($totalCrewmembersOfStealer > $totalCrewmembersOfCrashed)
				{ // stealer has more Crewmembers than the crashed saucer
						// notify all players that stealer may not steal from crashed because they have more crewmembers
						self::notifyAllPlayers( "cannotSteal", clienttranslate( '${stealer_color} has more Crewmembers than ${stealee_color} so they may not steal any from them.' ), array(
								'stealer_color' => $saucerColorFriendlyStealer,
								'stealee_color' => $saucerColorFriendlyCrashed
						) );
						return $result;
				}

				$allStealableCrewmembersFromCrashedSaucer = self::getObjectListFromDB( "SELECT garment_id, garment_color, garment_type
																									FROM garment
																									WHERE garment_location='$crashedSaucer' AND garment_color<>'$crashedSaucer'" );

				$crewmemberIndex = 0;
				foreach( $allStealableCrewmembersFromCrashedSaucer as $crewmember )
				{ // go through all this saucer's off-colored crewmembers

						$crewmemberColor = $crewmember['garment_color'];
						$crewmemberType = $this->convertGarmentTypeIntToString($crewmember['garment_type']);

						$result[$crewmemberIndex] = array();
						$result[$crewmemberIndex]['crewmemberType'] = $crewmemberType;
						$result[$crewmemberIndex]['crewmemberColor'] = $crewmemberColor;
						$crewmemberIndex++;
				}

				return $result;
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

		function getValidSpacesForUpgrade($saucerColor, $upgradeName)
		{
				$validSpaces = array();

				$currentSaucerX = $this->getSaucerXLocation($saucerColor);
				$currentSaucerY = $this->getSaucerYLocation($saucerColor);

				switch($upgradeName)
				{
						case "Blast Off Thrusters":
								$xPlusOne = $currentSaucerX + 1;

								$spaceType = $this->getBoardSpaceType($xPlusOne, $currentSaucerY);
								if($spaceType != "C" && $spaceType != "O" && $spaceType != "S" && $spaceType != "D")
								{ // empty space
											$saucerHere = $this->getSaucerAt($xPlusOne, $currentSaucerY, $saucerColor);
											$crewmemberId = $this->getGarmentIdAt($xPlusOne, $currentSaucerY);

											if($saucerHere == "" && $crewmemberId == 0)
											{	// there is no Saucer and no Crewmember here
													array_push($validSpaces, $xPlusOne.'_'.$currentSaucerY);
											}
								}


								$xMinusOne = $currentSaucerX - 1;

								$spaceType = $this->getBoardSpaceType($xMinusOne, $currentSaucerY);
								if($spaceType != "C" && $spaceType != "O" && $spaceType != "S" && $spaceType != "D")
								{ // empty space
											$saucerHere = $this->getSaucerAt($xMinusOne, $currentSaucerY, $saucerColor);
											$crewmemberId = $this->getGarmentIdAt($xMinusOne, $currentSaucerY);

											if($saucerHere == "" && $crewmemberId == 0)
											{	// there is no Saucer and no Crewmember here
													array_push($validSpaces, $xMinusOne.'_'.$currentSaucerY);
											}
								}


								$yPlusOne = $currentSaucerY + 1;
								$spaceType = $this->getBoardSpaceType($currentSaucerX, $yPlusOne);
								if($spaceType != "C" && $spaceType != "O" && $spaceType != "S" && $spaceType != "D")
								{ // empty space
											$saucerHere = $this->getSaucerAt($currentSaucerX, $yPlusOne, $saucerColor);
											$crewmemberId = $this->getGarmentIdAt($currentSaucerX, $yPlusOne);

											if($saucerHere == "" && $crewmemberId == 0)
											{	// there is no Saucer and no Crewmember here
													array_push($validSpaces, $currentSaucerX.'_'.$yPlusOne);
											}
								}


								$yMinusOne = $currentSaucerY - 1;
								$spaceType = $this->getBoardSpaceType($currentSaucerX, $yMinusOne);
								if($spaceType != "C" && $spaceType != "O" && $spaceType != "S" && $spaceType != "D")
								{ // empty space
											$saucerHere = $this->getSaucerAt($currentSaucerX, $yMinusOne, $saucerColor);
											$crewmemberId = $this->getGarmentIdAt($currentSaucerX, $yMinusOne);

											if($saucerHere == "" && $crewmemberId == 0)
											{	// there is no Saucer and no Crewmember here
													array_push($validSpaces, $currentSaucerX.'_'.$yMinusOne);
											}
								}

						break;

						case "Afterburner":
								// get moves with max distance
								$movesForSaucer = $this->getMovesForSaucer($saucerColor);
								foreach( $movesForSaucer as $cardType => $moveCard )
								{ // go through each move card for this saucer

										$directionsWithSpaces = $moveCard['directions'];
										//$count = count($spacesForCard);
										//throw new feException( "spacesForCard Count:$count" );

										foreach( $directionsWithSpaces as $direction => $directionWithSpaces )
										{ // go through each direction

												foreach( $directionWithSpaces as $space )
												{ // go through each space

														$column = $space['column'];
														$row = $space['row'];

														$formattedSpace = $column.'_'.$row;

														$spaceType = $this->getBoardSpaceType($column, $row);
														if($spaceType != "C" && $spaceType != "O" && $spaceType != "S" && $spaceType != "D")
														{ // empty space
																	$saucerHere = $this->getSaucerAt($column, $row, $saucerColor);
																	$crewmemberId = $this->getGarmentIdAt($column, $row);

																	if($saucerHere == "" && $crewmemberId == 0)
																	{	// there is no Saucer and no Crewmember here
																			array_push($validSpaces, $formattedSpace);
																	}
														}
												}
										}
								}

						break;

						case "Landing Legs":
								$xPlusOne = $currentSaucerX + 1;
								array_push($validSpaces, $xPlusOne.'_'.$currentSaucerY);

								$xMinusOne = $currentSaucerX - 1;
								array_push($validSpaces, $xMinusOne.'_'.$currentSaucerY);

								$yPlusOne = $currentSaucerY + 1;
								array_push($validSpaces, $currentSaucerX.'_'.$yPlusOne);

								$yMinusOne = $currentSaucerY - 1;
								array_push($validSpaces, $currentSaucerX.'_'.$yMinusOne);
						break;
				}

				return $validSpaces;
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


		function doesSaucerHaveOffColoredCrewmember($ostrich)
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

		function canSaucerPassCrewmembers($saucerAsking)
		{
				if($this->getNumberOfPlayers() != 2)
				{ // this is not a 2-player game
//throw new feException( "not 2 player");
						return false;
				}

				if($this->getSkippedPassing($saucerAsking) == 1)
				{ // they already skipped passing this round
//throw new feException( "skipped passing");
						return false;
				}

				$passedByOtherSaucer = $this->getPassedByOtherSaucer($saucerAsking);
				if($passedByOtherSaucer != 1)
				{ // they did not pass by their other saucer this turn
//throw new feException( "did not pass");
						return false;
				}

				$numberOfPassableCrewmembers = count($this->getPassableCrewmembersFromSaucer($saucerAsking));
				if($numberOfPassableCrewmembers < 1)
				{ // they do not have crewmembers to pass
//throw new feException( "no passable");
						return false;
				}

				// if we didn't find a reason not to allow them to pass, they can
				return true;
		}

		function canSaucerTakeCrewmembers($saucerAsking)
		{

				if($this->getNumberOfPlayers() != 2)
				{ // this is not a 2-player game

//						throw new feException( "not 2 player");

						return false;
				}

				if($this->getSkippedTaking($saucerAsking) == 1)
				{ // they already skipped taking this round


//								throw new feException( "skipped taking");

						return false;
				}

				$passedByOtherSaucer = $this->didThisSaucerPassByOtherSaucer($saucerAsking);
				if($passedByOtherSaucer == false)
				{ // they did not pass by their other saucer this turn


//								throw new feException( "did not pass");

						return false;
				}

				$otherSaucerOfPlayer = $this->getPlayersOtherSaucer($saucerAsking);
				$numberOfTakeableCrewmembers = count($this->getPassableCrewmembersFromSaucer($otherSaucerOfPlayer));
				//throw new feException( "numberOfTakeableCrewmembers: $numberOfTakeableCrewmembers");
				if($numberOfTakeableCrewmembers < 1)
				{ // they do not have crewmembers to take


//								throw new feException( "no one to take");

						return false;
				}


//								throw new feException( "can take");

				// if we didn't find a reason not to allow them to take, they can
				return true;
		}

		function didThisSaucerPassByOtherSaucer($saucerAsking)
		{
				$passedBy = self::getUniqueValueFromDb("SELECT passed_by_other_saucer
																				 FROM ostrich
																				 WHERE ostrich_color='$saucerAsking'" );
			  if($passedBy == 0)
				{
						return false;
				}
				else
				{
						return true;
				}
		}

		function canSaucerBoost($saucerColor)
		{
				$boosterCount = $this->getBoosterCountForSaucer($saucerColor);

				$isSaucerCrashed = $this->isSaucerCrashed($saucerColor);

				if($boosterCount > 0 && !$isSaucerCrashed)
				{ // they have a booster and they are not crashed
						return true;
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
				$sql .= implode( ',', $sql_values );

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

		function setCardToPlayed($cardId)
		{
				$sql = "UPDATE upgradeCards SET card_is_played=1 WHERE card_id=$cardId";
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

		// Returns the number of saucers each player controls.
		function getSaucersPerPlayer()
		{
				if($this->getNumberOfPlayers() < 3)
				{
						return 2;
				}
				else
				{
						return 1;
				}
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

		function crewmembersNeededForPlayerCount()
		{
				switch($this->getNumberOfPlayers())
				{
						case 2:
							return 2;
						break;
						case 3:
							//return 4;
							return 2; // this will change to 4 if 3-player games alllow controlling 2 saucers
						break;
						case 4:
							return 2;
						break;
						case 3:
							return 5;
						break;
						case 4:
							return 6;
						break;
						default:
							return 0;
							break;
				}
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

		function resetAllCliffPushers()
		{
			$sqlUpdate = "UPDATE ostrich SET ";
			$sqlUpdate .= "ostrich_causing_cliff_fall='' ";

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

		function getSpacesInColumnUp($startingY, $column, $maxDistance)
		{
				$result = array();

				$spaces = self::getObjectListFromDB( "SELECT board_x, board_y FROM `board` WHERE board_x=$column AND board_y <= $startingY AND board_y >= + ($startingY - $maxDistance);" );

				foreach( $spaces as $space )
				{ // go through each space

						$spaceArray = array();
						$spaceArray['row'] = $space['board_y'];
						$spaceArray['column'] = $space['board_x'];
						array_push($result, $spaceArray); // add this space to the list of move destinations
				}

				return $result;
		}

		function getSpacesInColumnDown($startingY, $column, $maxDistance)
		{
				$result = array();

				$spaces = self::getObjectListFromDB( "SELECT board_x, board_y FROM `board` WHERE board_x=$column AND board_y >= $startingY AND board_y <= ($startingY + $maxDistance);" );

				foreach( $spaces as $space )
				{ // go through each space

						$spaceArray = array();
						$spaceArray['row'] = $space['board_y'];
						$spaceArray['column'] = $space['board_x'];
						array_push($result, $spaceArray); // add this space to the list of move destinations
				}

				return $result;
		}

		function getSpacesInRowLeft($startingX, $row, $maxDistance)
		{
				$result = array();

				$spaces = self::getObjectListFromDB( "SELECT board_x, board_y FROM `board` WHERE board_y=$row AND board_x <= $startingX AND board_x >= ($startingX - $maxDistance);" );

				foreach( $spaces as $space )
				{ // go through each space

						$spaceArray = array();
						$spaceArray['row'] = $space['board_y'];
						$spaceArray['column'] = $space['board_x'];

						array_push($result, $spaceArray); // add this space to the list of move destinations
				}

				return $result;
		}

		function getSpacesInRowRight($startingX, $row, $maxDistance)
		{
				$result = array();

				$spaces = self::getObjectListFromDB( "SELECT board_x, board_y FROM `board` WHERE board_y=$row AND board_x >= $startingX AND board_x <= ($startingX + $maxDistance);" );

				foreach( $spaces as $space )
				{ // go through each space

						$spaceArray = array();
						$spaceArray['row'] = $space['board_y'];
						$spaceArray['column'] = $space['board_x'];

						array_push($result, $spaceArray); // add this space to the list of move destinations
				}

				return $result;
		}

		function getCrewmemberIdTakenWithDistress()
		{
				return self::getUniqueValueFromDb("SELECT garment_id FROM garment WHERE taken_with_distress=1");
		}

		function setCrewmemberTakenWithDistress($crewmemberId, $newValue)
		{
				$sql = "UPDATE garment SET taken_with_distress=$newValue WHERE garment_id=$crewmemberId";
				self::DbQuery( $sql );
		}

		function setSaucerGivenWithDistress($saucerColor, $newValue)
		{
				$sql = "UPDATE ostrich SET given_with_distress=$newValue WHERE ostrich_color='$saucerColor'";
				self::DbQuery( $sql );
		}

		function getSaucerGivenWithDistress()
		{
				return self::getUniqueValueFromDb("SELECT saucer_color FROM ostrich WHERE given_with_distress=1");
		}

		function getHighestAirlockExchangeableId()
		{
				$highestValue = $this->getHighestAirlockExchangeableMax();
				return self::getUniqueValueFromDb("SELECT garment_id FROM garment WHERE airlock_exchangeable=$highestValue");
		}

		function getHighestAirlockExchangeableMax()
		{
				return self::getUniqueValueFromDb("SELECT MAX(airlock_exchangeable) AS max FROM garment");
		}

		function getAirlockExchangeableCrewmembersForSaucer($saucerColor)
		{
				return self::getObjectListFromDB( "SELECT * FROM `garment` WHERE airlock_exchangeable>0 AND garment_location='$saucerColor'" );
		}

		function getLostCrewmembers()
		{
				$lostCrewmembers = array();

				// get the next up crewmember for each saucer
				$allSaucers = $this->getAllSaucers();
				foreach( $allSaucers as $saucer )
				{ // go through each saucer
						$saucerColor = $saucer['ostrich_color'];
						$nextColorList = self::getObjectListFromDB( "SELECT garment_id, garment_x, garment_y, garment_location, garment_color, garment_type
																							 FROM garment
																							 WHERE garment_location='pile' AND garment_color='$saucerColor' ORDER BY garment_type ASC LIMIT 1" );
						foreach($nextColorList as $crewmember)
						{
								array_push($lostCrewmembers, $crewmember);
						}

				}

				return $lostCrewmembers;
		}

		function getCrewmembersOnBoard()
		{
				 return self::getObjectListFromDB( "SELECT *
																					 FROM garment
																					 WHERE garment_location='board'" );
	  }

		function getCrewmembersOnSaucer($saucerColor)
		{
				return self::getObjectListFromDB( "SELECT *
																					FROM garment
																					WHERE garment_location='$saucerColor'" );
		}

		function giveCrewmemberToSaucer($crewmemberId, $saucerColor)
		{
				$garmentX = self::getUniqueValueFromDb("SELECT garment_x FROM garment WHERE garment_id=$crewmemberId");
				$garmentY = self::getUniqueValueFromDb("SELECT garment_y FROM garment WHERE garment_id=$crewmemberId");

				// update the database to give the crewmember to the saucer
				$sql = "UPDATE garment SET garment_location='$saucerColor',garment_x=0,garment_y=0 WHERE garment_id=$crewmemberId";
				self::DbQuery( $sql );

				$garmentColor = self::getUniqueValueFromDb("SELECT garment_color FROM garment WHERE garment_id=$crewmemberId");
				$garmentType = self::getUniqueValueFromDb("SELECT garment_type FROM garment WHERE garment_id=$crewmemberId");
				$acquiringPlayer = self::getUniqueValueFromDb("SELECT ostrich_owner FROM ostrich WHERE ostrich_color='$saucerColor'");
/*
				$garmentTypeString = $this->convertGarmentTypeIntToString($garmentType);

				// see if we are wearing this or putting it in our backpack
				$garmentsOfTypeThisOstrichHas = $this->getGarmentsOstrichHasOfType($saucerColor, $garmentType);
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
*/
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

				$this->gamestate->nextState( "endSaucerTurnCleanUp" );
		}

		function moveCrewmemberToBoard($garmentId, $xDestination, $yDestination)
		{
				// update the database to give the garment to the ostrich
				$sql = "UPDATE garment SET garment_x=$xDestination,garment_y=$yDestination,garment_location='board' WHERE garment_id=$garmentId";
				self::DbQuery( $sql );

				$garmentColor = self::getUniqueValueFromDb("SELECT garment_color FROM garment WHERE garment_id=$garmentId");
				$garmentType = self::getUniqueValueFromDb("SELECT garment_type FROM garment WHERE garment_id=$garmentId");

				$garmentTypeString = $this->convertGarmentTypeIntToString($garmentType);

				// get the crash site integer
				$crashSiteInteger = $this->getBoardSpaceType($xDestination, $yDestination);

				// notify players that this garment has been acquired
				self::notifyAllPlayers( "replacementGarmentSpaceChosen", clienttranslate( '${CREWMEMBERIMAGE} has been placed on Crash Site ${crash_site_integer}.' ), array(
						'garmentColor' => $garmentColor,
					  'garmentType' => $garmentTypeString,
						'crash_site_integer' => $crashSiteInteger,
						'xDestination' => $xDestination,
						'yDestination' => $yDestination,
						'CREWMEMBERIMAGE' => 'CREWMEMBERIMAGE_'.$garmentTypeString.'_'.$garmentColor
				) );
		}

		function placeCrewmemberOnSpace($garmentId, $xDestination, $yDestination)
		{
				// update the database with the crewmember's new position
				$sql = "UPDATE garment SET garment_x=$xDestination,garment_y=$yDestination,garment_location='board' WHERE garment_id=$garmentId";
				self::DbQuery( $sql );

				$garmentColor = self::getUniqueValueFromDb("SELECT garment_color FROM garment WHERE garment_id=$garmentId");
				$garmentType = self::getUniqueValueFromDb("SELECT garment_type FROM garment WHERE garment_id=$garmentId");

				$garmentTypeString = $this->convertGarmentTypeIntToString($garmentType);

				$crashSite = $this->getBoardSpaceType($xDestination, $yDestination);

				// notify players that this garment has been acquired
				self::notifyAllPlayers( "replacementGarmentSpaceChosen", clienttranslate( '${CREWMEMBERIMAGE} has been placed on Crash Site ${crashSiteNumber}.' ), array(
						'garmentColor' => $garmentColor,
					  'garmentType' => $garmentTypeString,
						'xDestination' => $xDestination,
						'yDestination' => $yDestination,
						'crashSiteNumber' => $crashSite,
						'CREWMEMBERIMAGE' => 'CREWMEMBERIMAGE_'.$garmentTypeString.'_'.$garmentColor
				) );
		}

		// Returns true if all of the following is true for the given saucer:
		// - it is the saucer's turn
		// - the saucer crashed
		// - the saucer has not yet paid the penalty for crashing
		// - the saucer has an off-colored crewmember
		function hasPendingCrashPenalty($saucer)
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				$timesActivatedCloakingDevice = $this->getUpgradeTimesActivatedThisRound($saucer, "Cloaking Device");
				if($timesActivatedCloakingDevice > 0)
				{	// the used Cloaking Device to remove themself from the board

						// do not penalize them for "crashing"
						return false;
				}

				$saucerCrashed = $this->isSaucerCrashed($saucer);
				$saucerCrashDetails = $this->getSaucerCrashDetailsForSaucer($saucer);

				foreach($saucerCrashDetails as $saucerDetail)
				{ // should just be one but it's a list of records

						$crashPenaltyRendered = $saucerDetail['crash_penalty_rendered'];

						//throw new feException( "saucerCrashed:$saucerCrashed saucerWhoseTurnItIs:$saucerWhoseTurnItIs saucer: $saucer crashPenaltyRendered: $crashPenaltyRendered");

						if($saucerCrashed == true &&
							 $saucerWhoseTurnItIs == $saucer &&
							 $crashPenaltyRendered == false)
						{ // this saucer crashed on their turn and they have not yet paid the penalty

								if($this->doesSaucerHaveOffColoredCrewmember($saucer))
								{ // saucer has an off-colored crewmember

										return true;
								}
								else
								{ // saucer does NOT have an off-colored crewmember

										return false;
								}
						}
				}

				return false;
		}

		// Returns true if all of the following are true:
		// a crewmember needs to be placed
		// there is at least 1 lost crewmember
		function doesCrewmemberNeedToBePlaced()
		{
				$countCrewmembersNeededForPlayerCount = $this->crewmembersNeededForPlayerCount();

				$crewmembersOnBoard = $this->getCrewmembersOnBoard();
				$countCrewmembersOnBoard = count($crewmembersOnBoard);

				$lostCrewmembers = $this->getLostCrewmembers();
				$countLostCrewmembers = count($lostCrewmembers);

				//throw new feException( "countCrewmembersNeededForPlayerCount:$countCrewmembersNeededForPlayerCount");
				//throw new feException( "countCrewmembersOnBoard:$countCrewmembersOnBoard");
				//throw new feException( "countLostCrewmembers:$countLostCrewmembers");

				if($countCrewmembersOnBoard < $countCrewmembersNeededForPlayerCount)
				{ // we are missing a crewmember on the board

						if($countLostCrewmembers > 0)
						{ // there is at least 1 lost crewmember available

								// we gotta place one
								return true;
						}
						else
						{ // there are no lost crewmembers left

								// no placing needed
								return false;
						}
				}
				else
				{ // we are NOT missing any crewmembers from the board

						// no placnig needed
						return false;
				}
		}

		// Returns a saucer color if all of the following is true for the given saucer:
		// - they crashed another saucer
		// - they haven't gotten their reward yet
		// Otherwise it returns ''.
		function nextPendingCrashReward($saucerWhoseTurnItIs)
		{
				$allSaucersCrashDetails = $this->getSaucerCrashDetailsForAllSaucers();
				$ownerOfSaucerWhoseTurnItIs = $this->getOwnerIdOfOstrich($saucerWhoseTurnItIs);

				//echo "looking through saucers in nextPendingCrashReward()<br>";

				foreach($allSaucersCrashDetails as $saucerCrashDetails)
				{ // go through all saucers
						$saucerColor = $saucerCrashDetails['ostrich_color'];
						$saucerIsCrashed = $this->isSaucerCrashed($saucerColor);
						$saucerWasCrashedBy = $saucerCrashDetails['ostrich_causing_cliff_fall'];
						$crashRewardAcquired = $saucerCrashDetails['crash_penalty_rendered'];
						$ownerOfSaucerColor = $this->getOwnerIdOfOstrich($saucerColor);

						//if($saucerIsCrashed && $saucerWasCrashedBy != $saucerWhoseTurnItIs)
						//	throw new feException( "saucerColor:$saucerColor <br> saucerIsCrashed:$saucerIsCrashed <br> saucerWasCrashedBy:$saucerWasCrashedBy <br> crashRewardAcquired:$crashRewardAcquired <br>" );

						if($saucerColor != $saucerWhoseTurnItIs &&
						   $saucerIsCrashed &&
						   $saucerWasCrashedBy == $saucerWhoseTurnItIs &&
							 $ownerOfSaucerColor != $ownerOfSaucerWhoseTurnItIs &&
							 $crashRewardAcquired < 1)
						{ // this saucer was crashed by the saucer whose turn it is and they have not rendered their penalty (and the two saucers are not owned by the same player)
//echo "saucerColor:$saucerColor <br> saucerIsCrashed:$saucerIsCrashed <br> saucerWasCrashedBy:$saucerWasCrashedBy <br> crashRewardAcquired:$crashRewardAcquired <br>";
								return $saucerColor; // just return the first one we find like this
						}
				}

//echo "RETURNING EMPTY";
				return ''; // no saucers meet the requirement
		}

		function markCrashPenaltyRendered($crashedSaucerColor)
		{
				$sql = "UPDATE ostrich SET crash_penalty_rendered=1 WHERE ";
				$sql .= "ostrich_color='".$crashedSaucerColor."'";
				self::DbQuery( $sql );
		}

		function getSkippedPassing($saucerColor)
		{
				return self::getUniqueValueFromDb("SELECT skipped_passing FROM ostrich WHERE ostrich_color='$saucerColor'");
		}

		function setSkippedGivingAway($saucerColor, $value)
		{
				$sql = "UPDATE ostrich SET skipped_giving_away=$value WHERE ";
				$sql .= "ostrich_color='".$saucerColor."'";
				self::DbQuery( $sql );
		}

		function getSkippedGivingAway($saucerColor)
		{
				return self::getUniqueValueFromDb("SELECT skipped_giving_away FROM ostrich WHERE ostrich_color='$saucerColor'");
		}

		function setSkippedPassing($saucerColor, $value)
		{
				$sql = "UPDATE ostrich SET skipped_passing=$value WHERE ";
				$sql .= "ostrich_color='".$saucerColor."'";
				self::DbQuery( $sql );
		}

		function setSkippedBoosting($saucerColor, $value)
		{
				$sql = "UPDATE ostrich SET skipped_boosting=$value WHERE ";
				$sql .= "ostrich_color='".$saucerColor."'";
				self::DbQuery( $sql );
		}

		function getSkippedTaking($saucerColor)
		{
				return self::getUniqueValueFromDb("SELECT skipped_taking FROM ostrich WHERE ostrich_color='$saucerColor'");
		}

		function setSkippedTaking($saucerColor, $value)
		{
				$sql = "UPDATE ostrich SET skipped_taking=$value WHERE ";
				$sql .= "ostrich_color='".$saucerColor."'";
				self::DbQuery( $sql );
		}

		function getPassedByOtherSaucer($saucerColor)
		{
				return self::getUniqueValueFromDb("SELECT passed_by_other_saucer FROM ostrich WHERE ostrich_color='$saucerColor'");
		}

		function setPassedByOtherSaucer($saucerColor, $value)
		{
				$sql = "UPDATE ostrich SET passed_by_other_saucer=$value WHERE ";
				$sql .= "ostrich_color='".$saucerColor."'";
				self::DbQuery( $sql );
		}

		function setAskedToActivateForAllStartOfTurnSaucerUpgrades($saucerColor)
		{
			//throw new feException( "setAskedToActivateForAllSaucerUpgrades for saucer:$saucerColor");
				$allStartOfTurnUpgrades = $this->getAllPlayedStartOfTurnUpgradesForSaucer($saucerColor);

				//throw new feException( "setAskedToActivateForAllSaucerUpgrades for saucer:$saucerColor");
				foreach( $allStartOfTurnUpgrades as $upgrade )
				{ // go through each start of turn upgrade they have played

						$collectorNumber = $upgrade['collectorNumber'];

						$this->setAskedToActivateUpgradeWithCollectorNumber($saucerColor, $collectorNumber);
				}
		}

		function setAskedToActivateForAllEndOfTurnSaucerUpgrades($saucerColor)
		{
			//throw new feException( "setAskedToActivateForAllSaucerUpgrades for saucer:$saucerColor");
				$allEndOfTurnUpgrades = $this->getAllPlayedEndOfTurnUpgradesForSaucer($saucerColor);

				//throw new feException( "setAskedToActivateForAllSaucerUpgrades for saucer:$saucerColor");
				foreach( $allEndOfTurnUpgrades as $upgrade )
				{ // go through each start of turn upgrade they have played

						$collectorNumber = $upgrade['collectorNumber'];

						$this->setAskedToActivateUpgradeWithCollectorNumber($saucerColor, $collectorNumber);
				}
		}

		function setAskedToActivateUpgrade($saucerColor, $upgradeName)
		{
				$collectorNumber = $this->convertUpgradeNameToCollectorNumber($upgradeName);
				$this->setAskedToActivateUpgradeWithCollectorNumber($saucerColor, $collectorNumber);
		}

		function setAskedToActivateUpgradeWithCollectorNumber($saucerColor, $collectorNumber)
		{
				$sql = "UPDATE upgradeCards SET asked_to_activate_this_round=1 WHERE ";
				$sql .= "card_location='".$saucerColor."' AND card_type_arg=$collectorNumber";
				self::DbQuery( $sql );
		}

		function getChosenMoveCards($playerId)
		{
				$chosenMoveCards = array();

/*
				// put the cards that are played by a player into the array that is returned to the UI/javascript/client layer with the key "played_playerid"
				$result['played'] = self::getObjectListFromDB( "SELECT card_id id, card_type turn, card_type_arg distance, card_location_arg player, card_ostrich color, ostrich_last_direction
																											 FROM movementCards
																											 JOIN ostrich ON movementCards.card_ostrich=ostrich.ostrich_color
																											 WHERE card_location='played'" );

				$result['zigChosen'] = self::getObjectListFromDB( "SELECT card_id id, card_type turn, card_type_arg distance, card_location_arg player, card_ostrich color, ostrich_last_direction
																											 FROM movementCards
																											 JOIN ostrich ON movementCards.card_ostrich=ostrich.ostrich_color
																											 WHERE card_location='zigChosen'" );

				// get the move cards each player has played for each saucer
				$chosenMoveCards = self::getObjectListFromDB( "SELECT ostrich_color, ostrich_zig_direction, ostrich_zig_distance
																											 FROM ostrich
																											 WHERE ostrich_owner=$playerId" );
*/
				$chosenMoveCards = self::getObjectListFromDB( "SELECT *
																							 FROM ostrich INNER JOIN movementCards ON ostrich.ostrich_color=movementCards.card_location
																							 WHERE movementCards.card_chosen_state<>'unchosen'" );

				//$count = count($chosenMoveCards);
				//throw new feException( "count chosenMoveCards:$count");


				return $chosenMoveCards;
		}

		function getAllPlayedUpgradesBySaucer()
		{
				$result = array();

				$allSaucers = $this->getAllSaucers();
				foreach($allSaucers as $saucer)
				{
						$saucerColor = $saucer['ostrich_color'];
						//throw new feException( "saucerColor:$saucerColor");
						$result[$saucerColor] = $this->getPlayedUpgradesForSaucer($saucerColor);
				}

				return $result;
		}

		function getPlayedUpgradesForSaucer($saucerColor)
		{
				return self::getObjectListFromDB("SELECT * FROM upgradeCards WHERE card_location='$saucerColor' AND card_is_played=1");
		}

		function getAllPlayedStartOfTurnUpgradesForSaucer($saucerColor)
		{
				$result = array();

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Blast Off Thrusters'))
				{
					//throw new feException( "Blast Off Thrusters ");
						$upgradeArray = array();
						$upgradeArray['collectorNumber'] = 1;
						$upgradeArray['upgradeName'] = $this->getUpgradeTitleFromCollectorNumber(1);

						array_push($result, $upgradeArray);
				}

				return $result;
		}

		function getAllStartOfTurnUpgradesToActivateForSaucer($saucerColor)
		{
				$result = array();
//throw new feException( "getAllStartOfTurnUpgradesToActivateForSaucer");
				$allPlayedStartOfTurnUpgrades = $this->getAllPlayedStartOfTurnUpgradesForSaucer($saucerColor);
				foreach($allPlayedStartOfTurnUpgrades as $upgrade)
				{
						$collectorNumber = $upgrade['collectorNumber'];
						$upgradeName = $this->getUpgradeTitleFromCollectorNumber($collectorNumber);
//throw new feException( "played start of turn upgrade:$upgradeName");
						if($this->getUpgradeTimesActivatedThisRound($saucerColor, $upgradeName) < 1 &&
							$this->getAskedToActivateUpgrade($saucerColor, $upgradeName) == false)
						{ // this has not been activated and we have not yet asked

								$upgradeArray = array();
								$upgradeArray['collectorNumber'] = $collectorNumber;
								$upgradeArray['upgradeName'] = $upgradeName;

								array_push($result, $upgradeArray);
						}
				}

				return $result;
		}

		// Activated means the player has chosen to use it this round.
		function activateUpgrade($saucerColor, $upgradeName)
		{
				$collectorNumber = $this->convertUpgradeNameToCollectorNumber($upgradeName);
				$this->activateUpgradeWithCollectorNumber($saucerColor, $collectorNumber);
				$saucerColorFriendly = $this->convertColorToHighlightedText($saucerColor);

				self::notifyAllPlayers( "activateUpgrade", clienttranslate( '${saucer_color_friendly} activated ${upgrade_name}.' ), array(
						'saucer_color_friendly' => $saucerColorFriendly,
						'upgrade_name' => $upgradeName,
						'color' => $saucerColor
				) );
		}

		function activateUpgradeWithCollectorNumber($saucerColor, $collectorNumber)
		{
				$sql = "UPDATE upgradeCards SET times_activated_this_round=times_activated_this_round+1 WHERE ";
				$sql .= "card_location='".$saucerColor."' AND card_type_arg=$collectorNumber AND card_is_played=1";
				self::DbQuery( $sql );
		}

		// Activated means the player has chosen to use it this round.
		function resetAllUpgradesActivatedThisRound()
		{
				$sql = "UPDATE upgradeCards SET times_activated_this_round=0,asked_to_activate_this_round=0";
				self::DbQuery( $sql );
		}

		function getSaucerCrashDetailsForSaucer($saucer)
		{
				return self::getObjectListFromDB( "SELECT ostrich_color, ostrich_owner, ostrich_x, ostrich_y, ostrich_causing_cliff_fall, crash_penalty_rendered
																						FROM ostrich
																						WHERE ostrich_color='$saucer' LIMIT 1" );
		}

		function getSaucerCrashDetailsForAllSaucers()
		{
				return self::getObjectListFromDB( "SELECT ostrich_color, ostrich_owner, ostrich_x, ostrich_y, ostrich_causing_cliff_fall, crash_penalty_rendered
																					 FROM ostrich" );
		}

		function isSaucerCrashed($saucerColor)
		{
				$ostriches = self::getObjectListFromDB( "SELECT ostrich_color, ostrich_owner, ostrich_x, ostrich_y
																								 FROM ostrich
																								 WHERE ostrich_color='$saucerColor' LIMIT 1" );

				foreach( $ostriches as $ostrich )
				{ // go through each ostrich (should only be 1)

						$player = $ostrich['ostrich_owner'];
						$x = $ostrich['ostrich_x'];
						$y = $ostrich['ostrich_y'];
						$color = $ostrich['ostrich_color'];

						$boardSpaceType = $this->getBoardSpaceType($x, $y);

						//throw new feException( "Times activated:$timesActivatedCloakingDevice color:$color boardSpaceType:$boardSpaceType");

						if($boardSpaceType == "D")
						{ // this saucer is off the board and they did not activate Cloaking Device this turn
								return true;
						}
				}

				return false;
		}

		function isOstrichDizzy($ostrich)
		{
				return self::getUniqueValueFromDb("SELECT ostrich_is_dizzy FROM ostrich WHERE ostrich_color='$ostrich'");
		}

		// Starting with the Probe player and going clockwise, get the next saucer who is in a
		// crashed state. Return '' when there are none.
		function getSaucerThatCrashed()
		{
				$saucerWithProbe = $this->getOstrichWithCrown();
				$ownerOfSaucerWithProbe = $this->getOwnerIdOfOstrich($saucerWithProbe);

				$player = $ownerOfSaucerWithProbe; // start with the player who has the probe
				$numberOfPlayers = $this->getNumberOfPlayers();

				for($i=0; $i<$numberOfPlayers; $i++)
				{ // go through each player in clockwise order

						// does this player have any Saucers who are crashed?
						$allPlayersSaucers = $this->getSaucersForPlayer($player);
						foreach( $allPlayersSaucers as $saucer )
						{ // go through each saucer owned by this player

								if($this->isSaucerCrashed($saucer['ostrich_color']))
								{ // this Saucer has crashed
									//throw new feException( "returning saucer: ".$saucer['ostrich_color']);

										return $saucer['ostrich_color'];
								}
						}

						$player = $this->getPlayerAfter( $player ); // check the next player
				}

				return ''; // we didn't find any
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

		function placeSaucerOnSpace($saucerColor, $locX, $locY)
		{
				// put the saucer on this location
				$sqlOstrich = "UPDATE ostrich SET ostrich_x=".$locX.",ostrich_y=".$locY." WHERE ";
				$sqlOstrich .= "ostrich_color='".$saucerColor."'";
				self::DbQuery( $sqlOstrich );

				$saucerColorHighlighted = $this->convertColorToHighlightedText($saucerColor);

				// get the crash site integer
				$crashSiteNumber = $this->getBoardSpaceType($locX, $locY);

				// describe where they were placed
				$locationDescription = clienttranslate("was placed on Crash Site");
				if($crashSiteNumber == "D")
				{ // it was placed off the board
						$locationDescription = clienttranslate("move off the board");
						$crashSiteNumber = "";
				}

				// notify all players of this saucer's new location
				$boardValue = $this->getBoardSpaceType($locX, $locY);
				$ostrichOwner = $this->getOwnerIdOfOstrich($saucerColor);
				self::notifyAllPlayers( "moveOstrich", clienttranslate( '${color_highlighted} ${location_description} ${crash_site_integer}.' ), array(
								'color' => $saucerColor,
								'color_highlighted' => $saucerColorHighlighted,
								'ostrichTakingTurn' => $saucerColor,
								'x' => $locX,
								'y' => $locY,
								'crash_site_integer' => $crashSiteNumber,
								'spaceType' => $boardValue,
								'ostrichMovingHasZag' => false,
								'player_name' => self::getActivePlayerName(),
								'ostrichName' => $this->getOstrichName($saucerColor),
								'location_description' => $locationDescription
				) );
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

		function getTotalCrewmembersForSaucer($saucerColor)
		{
				return self::getUniqueValueFromDb("SELECT COUNT(garment_id) FROM garment WHERE garment_location='$saucerColor'");
		}

		function countDrawnCards()
		{
				return self::getUniqueValueFromDb("SELECT COUNT(card_id) FROM upgradeCards WHERE card_location='drawn'");
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
				$sql = "UPDATE ostrich SET ostrich_chosen_x_value=10" ;
				self::DbQuery( $sql );
		}

		function resetCrashPenalties()
		{

			$sql = "UPDATE ostrich SET crash_penalty_rendered=0" ;
			self::DbQuery( $sql );
		}

		function resetSpacesMovedAllSaucers()
		{
				$sql = "UPDATE ostrich SET spaces_moved=0" ;
				self::DbQuery( $sql );
		}

		function resetSaucers()
		{

			$sql = "UPDATE ostrich SET skipped_passing=0, skipped_taking=0, passed_by_other_saucer=0, skipped_boosting=0, given_with_distress=0, spaces_moved=0, distance_remaining=0, pushed_on_saucer_turn='0'" ;
			self::DbQuery( $sql );
		}

		function resetCrewmembers()
		{
				$sql = "UPDATE garment SET airlock_exchangeable=0, taken_with_distress=0" ;
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
				return self::getObjectListFromDB( "SELECT ostrich_color, ostrich_turns_taken, ostrich_color color, ostrich_owner owner, 'name' ownerName
																										 FROM ostrich
																										 WHERE ostrich_owner=$playerId ORDER BY ostrich_color" );
		}

		function getFirstSaucerForPlayer($playerId)
		{
				return self::getUniqueValueFromDb( "SELECT ostrich_color
																										 FROM ostrich
																										 WHERE ostrich_owner=$playerId ORDER BY ostrich_color LIMIT 1" );
		}

		function getCollectorNumberFromDatabaseId($databaseId)
		{
			return self::getUniqueValueFromDb("SELECT card_type_arg FROM upgradeCards WHERE card_id=$databaseId");
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


		        $ostriches = self::getObjectListFromDB( "SELECT ostrich_color color, ostrich_owner owner, 'name' ownerName, ostrich_causing_cliff_fall
						                                               FROM ostrich
						                                               WHERE 1 ORDER BY ostrich_owner, ostrich_color" );

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

		function isThisFirstTurnInRoundForPlayer($playerWhoseTurnItIs)
		{
				if($this->getSaucersPerPlayer() == 1)
				{ // players are only controlling a single saucer
						return true;
				}

				$turnsTaken = -1; // this will hold the lowest number of turns taken by the player's saucers

				$ostrichSql = "SELECT ostrich_color, ostrich_turns_taken, ostrich_is_chosen ";
				$ostrichSql .= "FROM ostrich ";
				$ostrichSql .= "WHERE ostrich_owner=$playerWhoseTurnItIs";
				$dbresOstrich = self::DbQuery( $ostrichSql );
				while( $saucer = mysql_fetch_assoc( $dbresOstrich ) )
				{ // go through each ostrich of this player

						$saucerColor = $saucer['ostrich_color']; // save which color ostrich this is
						$saucerTurnsTaken = $saucer['ostrich_turns_taken']; // see how many turns this ostrich has taken

						if($turnsTaken == -1)
						{ // this is the first saucer we've seen for this player
								$turnsTaken = $saucerTurnsTaken;
						}
						else
						{ // this is the second saucer we've seen for this player
								if($turnsTaken == $saucerTurnsTaken)
								{ // they have both taken the same number of turns
										return true;
								}
								else
								{
										return false;
								}
						}
				}
		}

		function getPlayersOtherSaucer($firstSaucer)
		{
				$saucerOwner = $this->getOwnerIdOfOstrich($firstSaucer);

				$ostrichSql = "SELECT ostrich_color ";
				$ostrichSql .= "FROM ostrich ";
				$ostrichSql .= "WHERE ostrich_owner=$saucerOwner AND ostrich_color<>'$firstSaucer'";

				return self::getUniqueValueFromDb($ostrichSql);
		}

		function getOstrichWhoseTurnItIs()
		{
				$activePlayer = self::getActivePlayerId(); // get who the active player is
				$firstSaucerColor = "";
				$turnsTakenByFirstSaucer = 1000;

				//echo "activeplayer $activePlayer <br>";

				// get all of their ostriches
				$numberOfOstrichesThisPlayerHas = 0;
				$ostrichSql = "SELECT ostrich_color, ostrich_turns_taken, ostrich_is_chosen ";
				$ostrichSql .= "FROM ostrich ";
				$ostrichSql .= "WHERE ostrich_owner=$activePlayer";
				$dbresOstrich = self::DbQuery( $ostrichSql );
				while( $ostrich = mysql_fetch_assoc( $dbresOstrich ) )
				{ // go through each saucer of this player
						$numberOfOstrichesThisPlayerHas++; // add one to the number of ostriches this player has

						$saucerIsChosenToGoFirst = $ostrich['ostrich_is_chosen'];
						$thisSaucerColor = $ostrich['ostrich_color']; // save which color ostrich this is
						$turnsTakenByThisSaucer = $ostrich['ostrich_turns_taken'];

						if($turnsTakenByFirstSaucer == 1000)
						{ // this is the first saucer we are checking out

								// just save data
								$turnsTakenByFirstSaucer = $turnsTakenByThisSaucer; // save how many turns this ostrich has taken
								$firstSaucerColor = $thisSaucerColor;
						}
						else
						{ // this is the second saucer we are checking out
								if($turnsTakenByFirstSaucer == $turnsTakenByThisSaucer)
								{ // these saucers have taken the same number of turns so we don't know which one goes next

											if($saucerIsChosenToGoFirst == 1)
											{ // they have selected this saucer to go first
//echo "if saucerIsChosenToGoFirst == 1 <br>";
													return $thisSaucerColor; // return this second ostrich because it is going next
											}
											else
											{ // they must have selected the other to go first
//echo "else saucerIsChosenToGoFirst == 1 <br>";
													return $firstSaucerColor; // return this ostrich because the player has already said it's going next
											}
								}
								else
								{ // one of these ostriches has taken fewer turns than the other

											if($turnsTakenByFirstSaucer > $turnsTakenByThisSaucer)
											{ // the second ostrich has taken fewer turns
//echo "if turnsTakenByFirstSaucer > turnsTakenByThisSaucer <br>";
														return $thisSaucerColor; // so return it since it's next
											}
											else
											{ // the first ostrich has taken fewer turns
//echo "else turnsTakenByFirstSaucer > turnsTakenByThisSaucer <br>";
														return $firstSaucerColor; // so return it since it's next
											}
								}
						}
				}

				if($numberOfOstrichesThisPlayerHas == 1)
				{ // if that player only has 1 ostrich, return that ostrich
						return $firstSaucerColor;
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

		function hasSaucerChosenX($saucerColor)
		{

				$chosenZigDistance = $this->getZigDistanceForOstrich($saucerColor);

				$sql = "SELECT ostrich_chosen_x_value ";
				$sql .= "FROM ostrich ";
				$sql .= "WHERE ostrich_color='".$saucerColor."'";
				$dbres = self::DbQuery( $sql );
				while( $saucerRecord = mysql_fetch_assoc( $dbres ) )
				{ // get our ostrich

						if($saucerRecord['ostrich_chosen_x_value'] == 10)
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
			//throw new feException("Getting board space type at ($x, $y).");

			  $boardValue = self::getUniqueValueFromDb("SELECT board_space_type FROM board WHERE board_x=$x AND board_y=$y");

				return $boardValue;
		}

		// On which type of board space is this ostrich located?
		function getBoardSpaceTypeForOstrich($ostrich)
		{
			//throw new feException("Getting board space type for saucer ($ostrich).");
				$x = self::getUniqueValueFromDb("SELECT ostrich_x FROM ostrich WHERE ostrich_color='$ostrich'");
				$y = self::getUniqueValueFromDb("SELECT ostrich_y FROM ostrich WHERE ostrich_color='$ostrich'");

			  $boardValue = self::getUniqueValueFromDb("SELECT board_space_type FROM board WHERE board_x=$x AND board_y=$y");

				return $boardValue;
		}

		function getCrewmemberIdFromColorAndType($color, $typeAsInt)
		{
				return self::getUniqueValueFromDb("SELECT garment_id FROM garment WHERE garment_color='$color' AND garment_type=$typeAsInt LIMIT 1");
		}

		function getCrewmemberColorFromId($crewmemberId)
		{
				return self::getUniqueValueFromDb("SELECT garment_color FROM garment WHERE garment_id=$crewmemberId LIMIT 1");
		}

		function getCrewmemberTypeIdFromId($crewmemberId)
		{
				return self::getUniqueValueFromDb("SELECT garment_type FROM garment WHERE garment_id=$crewmemberId LIMIT 1");
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

		function checkIfPassedByOtherSaucer($saucerMoving, $saucerMovingX, $saucerMovingY)
		{
				if($this->getNumberOfPlayers() != 2)
				{ // each player only has 1 saucer each

						// this is only for 2-player games so we can skip
						return;
				}

				//if($saucerMovingX == 2 && $saucerMovingY == 5)
				//		throw new feException("saucer moving ($saucerMoving) has X, Y ($saucerMovingX, $saucerMovingY)");

				$otherSaucer = $this->getPlayersOtherSaucer($saucerMoving);
				$otherSaucerX = $this->getSaucerXLocation($otherSaucer);
				$otherSaucerY = $this->getSaucerYLocation($otherSaucer);

				$passableCrewmembers = $this->getPassableCrewmembersFromSaucer($saucerMoving);
				$receivableCrewmembers = $this->getPassableCrewmembersFromSaucer($otherSaucer);




				if($saucerMovingX == $otherSaucerX)
				{	// they are on the same row
					//throw new feException("row saucer moving ($saucerMoving) has X, Y ($saucerMovingX, $saucerMovingY) and the other saucer ($otherSaucer) has X, Y ($otherSaucerX, $otherSaucerY)");
						if(abs($saucerMovingY - $otherSaucerY) < 2)
						{ // they are within 1 space of them
							$subtract = $saucerMovingY - $otherSaucerY;
							$absSubtract = abs($saucerMovingY - $otherSaucerY);
							//throw new feException("subtract:$subtract absSubtract:$absSubtract");
							//throw new feException("set1 saucer moving ($saucerMoving) has X, Y ($saucerMovingX, $saucerMovingY) and the other saucer ($otherSaucer) has X, Y ($otherSaucerX, $otherSaucerY)");

								if(count($passableCrewmembers) > 0 || count($receivableCrewmembers) > 0)
								{ // the saucer had something to give or pass at this point

											//if($saucerMovingX == 2 && $saucerMovingY == 5)
											//		throw new feException("subtract:$subtract absSubtract:$absSubtract");

										$this->setPassedByOtherSaucer($saucerMoving, 1);
								}
						}
				}

				if($saucerMovingY == $otherSaucerY)
				{	// they are on the same column
					//throw new feException("col saucer moving ($saucerMoving) has X, Y ($saucerMovingX, $saucerMovingY) and the other saucer ($otherSaucer) has X, Y ($otherSaucerX, $otherSaucerY)");
						if(abs($saucerMovingX - $otherSaucerX) < 2)
						{ // they are within 1 space of them

							$subtract = $saucerMovingY - $otherSaucerY;
							$absSubtract = abs($saucerMovingY - $otherSaucerY);
														//throw new feException("subtract:$subtract absSubtract:$absSubtract");
							//throw new feException("set2 saucer moving ($saucerMoving) has X, Y ($saucerMovingX, $saucerMovingY) and the other saucer ($otherSaucer) has X, Y ($otherSaucerX, $otherSaucerY)");
								if(count($passableCrewmembers) > 0 || count($receivableCrewmembers) > 0)
								{ // the saucer had something to give or pass at this point

									//if($saucerMovingX == 2 && $saucerMovingY == 5)
									//		throw new feException("subtract:$subtract absSubtract:$absSubtract");

										$this->setPassedByOtherSaucer($saucerMoving, 1);
								}
						}
				}

				//if($saucerMovingX == 2 && $saucerMovingY == 5)
				//		throw new feException("passed");


		}

		// Move one space at a time adding events for the front end as we go for anything that happened and
		// also updating the backend data as we go.
		function getEventsWhileExecutingMove($currentX, $currentY, $distance, $direction, $saucerMoving, $wasPushed)
		{
				$moveEventList = array();
				//$moveEventList[0] = array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => 4, 'destination_Y' => 7);
				//$moveEventList[1] = array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => 2, 'destination_Y' => 7);

				// see if we are within 1 space of our other saucer
				$this->checkIfPassedByOtherSaucer($saucerMoving, $currentX, $currentY);

				// get the type of movement we're doing
				$moveType = $this->getMoveTypeWeAreExecuting();

				if($this->LEFT_DIRECTION == $direction)
				{ // we're traveling from right to left
					  for ($x = 1; $x <= $distance; $x++)
						{ // go one space at a time over distance
								$thisX = $currentX-$x; // move one space
								$boardValue = $this->getBoardSpaceType($thisX, $currentY); // which type of space did we move onto

								// see if we are within 1 space of our other saucer
								$this->checkIfPassedByOtherSaucer($saucerMoving, $thisX, $currentY);

								//echo "The value at ($thisX, $currentY) is: $boardValue <br>";
								//throw new feException("The value at ($thisX, $currentY) is: $boardValue");

								//if($thisX < 0)
							//		throw new feException("setSaucerXValue($saucerMoving, $thisX) with distance $distance");

								$this->setSaucerXValue($saucerMoving, $thisX); // set X value for Saucer

								if($moveType != "Blast Off Thrusters" && $moveType != "Landing Legs")
								{ // we are moving because of blast off thrusters or landing legs
										// we have moved a space so we need to update that in the database so we know when we have moved all our spaces
										$this->incrementSpacesMoved($saucerMoving);
								}

								$saucerWeCollideWith = $this->getSaucerAt($thisX, $currentY, $saucerMoving); // get any ostriches that might be at this location
								$garmentId = $this->getGarmentIdAt($thisX,$currentY); // get a garment here if there is one
								//echo "The garment at ($thisX,$currentY) is: $garmentId <br>";
								if($garmentId != 0)
								{ // there is a crewmember here

										$this->giveCrewmemberToSaucer($garmentId, $saucerMoving); // give the garment to the ostrich (set garment_location to the color)
										//$ownerOfOstrichMoving = $this->getOwnerIdOfOstrich($ostrichMoving);
										//$this->addToGarmentReplacementQueue($ownerOfOstrichMoving, $ostrichMoving);
										$this->updatePlayerScores(); // update the player boards with current scores

										if($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Airlock"))
										{ // they have Airlock

												//throw new feException( "Airlock");
												// mark that this crewmember could be exchanged
												$this->incrementAirlockExchangeable($garmentId);
										}

										$crewmemberColor = $this->getCrewmemberColorFromId($garmentId);
										$crewmemberTypeId = $this->getCrewmemberTypeIdFromId($garmentId);
										$crewmemberType = $this->convertGarmentTypeIntToString($crewmemberTypeId);

										// add an animation event for the crewmember sliding to the saucer
										array_push($moveEventList, array( 'event_type' => 'crewmemberPickup', 'saucer_moving' => $saucerMoving, 'crewmember_color' => $crewmemberColor, 'crewmember_type' => $crewmemberType));

										// add the animation for the saucer moving onto the space of the crewmember
										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));
								}
								else if($boardValue == "C" || $boardValue == "O")
								{ // this is a CRASH SITE

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));

								}
								else if($boardValue == "S")
								{ // this is an ACCELERATOR
										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));

										if($wasPushed)
										{ // the saucer moving was pushed onto this accelerator

												array_push($moveEventList, array( 'event_type' => 'pushedOntoAccelerator', 'saucer_moving' => $saucerMoving, 'spaces_pushed' => $distance));

												$pushedOntoAcceleratorEventList = $this->getEventsWhileExecutingMove($thisX, $currentY, $distance, $direction, $saucerMoving, $wasPushed);
												return array_merge($moveEventList, $pushedOntoAcceleratorEventList); // add the pushed event to the original and return so we don't go any further
										}
										else
										{ // the saucer moving moved onto the accelerator on their turn

												array_push($moveEventList, array( 'event_type' => 'movedOntoAccelerator', 'saucer_moving' => $saucerMoving));

												return $moveEventList; // don't go any further
										}
								}
								else if($boardValue == "D")
								{	// went off a cliff

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));

										array_push($moveEventList, array( 'event_type' => 'saucerCrashed', 'saucer_moving' => $saucerMoving));

										return $moveEventList; // don't go any further
								}
								else
								{ // empty space

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));

										if($saucerWeCollideWith != "")
										{	// there is a saucer here

												// the saucer we collide with will start their movement over
												$this->setSpacesMoved($saucerWeCollideWith, 0);

												if($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Phase Shifter") ||
												   $this->doesSaucerHaveUpgradePlayed($saucerMoving, "Proximity Mines"))
												{ // this saucer has phase shifter or proximity mines played

														// mark in the database that this saucer has been collided with and will need to execute its move if we decide to collide with it
														// we'll clear these out if they choose not to phase shift
														$this->setPushedOnSaucerTurn($saucerWeCollideWith, $saucerMoving);
														$this->setPushedDistance($saucerWeCollideWith, $distance);
														$this->setPushedDirection($saucerWeCollideWith, $direction);

														// do not move any further because
														return $moveEventList;
												}
										}
								}


								if($saucerWeCollideWith != "")
								{	// there is a saucer here

//throw new feException("colliding with:$saucerWeCollideWith");
										array_push($moveEventList, array( 'event_type' => 'saucerPush', 'saucer_moving' => $saucerMoving, 'saucer_pushed' => $saucerWeCollideWith, 'spaces_pushed' => $distance));

										$pushedEventList = $this->getEventsWhileExecutingMove($thisX, $currentY, $distance, $direction, $saucerWeCollideWith, true);
										//$pushedEventCount = count($pushedEventList);
										//$moveEventCount = count($moveEventList);
										//throw new feException("pushedEventCount:$pushedEventCount moveEventCount:$moveEventCount");
										$combinedList = array_merge($moveEventList, $pushedEventList); // add the pushed event to the original
										//$combinedCount = count($combinedList);
										//throw new feException("combinedCount:$combinedCount");
										return $combinedList; // return so we don't go any further
								}

								//return $moveEventList;
					  }

//$countOfEvents = count($moveEventList);
//throw new feException("events returned:$countOfEvents");
						return $moveEventList;
				}

				if($this->RIGHT_DIRECTION == $direction)
			 	{
					//throw new feException("Direction ($direction) and distance: $distance");
						for ($x = 1; $x <= $distance; $x++)
						{ // go space-by-space starting at your current location until the distance is
							// used up or we run into a skateboard or ostrich

								$thisX = $currentX+$x;
								$boardValue = $this->getBoardSpaceType($thisX, $currentY);

								// see if we are within 1 space of our other saucer
								$this->checkIfPassedByOtherSaucer($saucerMoving, $thisX, $currentY);

								/*echo "The value at ($thisX, $currentY) is: $boardValue <br>";
								throw new feException("The value at ($thisX, $currentY) is: $boardValue");*/

								$this->setSaucerXValue($saucerMoving, $thisX); // set X value for Saucer

								if($moveType != "Blast Off Thrusters" && $moveType != "Landing Legs")
								{ // we are moving because of blast off thrusters or landing legs
										// we have moved a space so we need to update that in the database so we know when we have moved all our spaces
										$this->incrementSpacesMoved($saucerMoving);
								}

								$saucerWeCollideWith = $this->getSaucerAt($thisX, $currentY, $saucerMoving); // get any ostriches that might be at this location
								$garmentId = $this->getGarmentIdAt($thisX,$currentY); // get a garment here if there is one
								//echo "The garment at ($thisX,$currentY) is: $garmentId <br>";
								if($garmentId != 0)
								{ // there is a garment here
										$this->giveCrewmemberToSaucer($garmentId, $saucerMoving); // give the garment to the ostrich (set garment_location to the color)
										//$ownerOfOstrichMoving = $this->getOwnerIdOfOstrich($ostrichMoving);
										//$this->addToGarmentReplacementQueue($ownerOfOstrichMoving, $ostrichMoving);
										$this->updatePlayerScores(); // update the player boards with current scores

										if($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Airlock"))
										{ // they have Airlock

												//throw new feException( "Airlock");
												// mark that this crewmember could be exchanged
												$this->incrementAirlockExchangeable($garmentId);
										}

										$crewmemberColor = $this->getCrewmemberColorFromId($garmentId);
										$crewmemberTypeId = $this->getCrewmemberTypeIdFromId($garmentId);
										$crewmemberType = $this->convertGarmentTypeIntToString($crewmemberTypeId);

										// add an animation event for the crewmember sliding to the saucer
										array_push($moveEventList, array( 'event_type' => 'crewmemberPickup', 'saucer_moving' => $saucerMoving, 'crewmember_color' => $crewmemberColor, 'crewmember_type' => $crewmemberType));

										// add the animation for the saucer moving onto the space of the crewmember
										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));
								}
								else if($boardValue == "C" || $boardValue == "O")
								{ // this is a CRASH SITE

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));

								}
								else if($boardValue == "S")
								{ // we hit an accelerator
//throw new feException( "end on S");
										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));

										if($wasPushed)
										{ // the saucer moving was pushed onto this accelerator
//throw new feException( "waspushed");
												array_push($moveEventList, array( 'event_type' => 'pushedOntoAccelerator', 'saucer_moving' => $saucerMoving, 'spaces_pushed' => $distance));

												$pushedOntoAcceleratorEventList = $this->getEventsWhileExecutingMove($thisX, $currentY, $distance, $direction, $saucerMoving, $wasPushed);
												return array_merge($moveEventList, $pushedOntoAcceleratorEventList); // add the pushed event to the original and return so we don't go any further
										}
										else
										{ // the saucer moving moved onto the accelerator on their turn

												array_push($moveEventList, array( 'event_type' => 'movedOntoAccelerator', 'saucer_moving' => $saucerMoving));

												return $moveEventList; // don't go any further
										}
								}
								else if($boardValue == "D")
								{	// went off a cliff

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));

										array_push($moveEventList, array( 'event_type' => 'saucerCrashed', 'saucer_moving' => $saucerMoving));

										return $moveEventList; // return so we don't go any further
								}
								else
								{ // empty space

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));

										if($saucerWeCollideWith != "")
										{	// there is a saucer here

												// the saucer we collide with will start their movement over
												$this->setSpacesMoved($saucerWeCollideWith, 0);

												if($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Phase Shifter") ||
												   $this->doesSaucerHaveUpgradePlayed($saucerMoving, "Proximity Mines"))
												{ // this saucer has phase shifter or proximity mines played

														// mark in the database that this saucer has been collided with and will need to execute its move if we decide to collid with it
														// we'll clear these out if they choose not to phase shift
														$this->setPushedOnSaucerTurn($saucerWeCollideWith, $saucerMoving);
														$this->setPushedDistance($saucerWeCollideWith, $distance);
														$this->setPushedDirection($saucerWeCollideWith, $direction);

														// do not move any further because
														return $moveEventList;
												}
										}
								}


								if($saucerWeCollideWith != "")
								{	// there is an ostrich here

									//array_push($moveEventList, array( 'event_type' => 'saucerPush', 'saucer_moving' => $saucerMoving, 'saucer_pushed' => $saucerWeCollideWith, 'spaces_pushed' => $distance));
//throw new feException("saucerPush added. now adding X, Y ($thisX, $currentY) and distance, direction ($distance, $direction), and saucerWeCollideWith ($saucerWeCollideWith)");

									$pushedEventList = $this->getEventsWhileExecutingMove($thisX, $currentY, $distance, $direction, $saucerWeCollideWith, true);

//$countEventList = count($moveEventList);
//$countPushedEventList = count($pushedEventList);
//throw new feException("countEventList ($countEventList) and countPushedEventList ($countPushedEventList)");

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
								$boardValue = $this->getBoardSpaceType($currentX, $thisY); // get the space type here

								// see if we are within 1 space of our other saucer
								$this->checkIfPassedByOtherSaucer($saucerMoving, $currentX, $thisY);

								$this->setSaucerYValue($saucerMoving, $thisY); // set Y value for Saucer

								if($moveType != "Blast Off Thrusters" && $moveType != "Landing Legs")
								{ // we are moving because of blast off thrusters or landing legs
										// we have moved a space so we need to update that in the database so we know when we have moved all our spaces
										$this->incrementSpacesMoved($saucerMoving);
								}

								$saucerWeCollideWith = $this->getSaucerAt($currentX, $thisY, $saucerMoving); // get any ostriches that might be at this location
								$garmentId = $this->getGarmentIdAt($currentX, $thisY); // get a garment here if there is one
								//echo "The garment at ($currentX, $thisY) is: $garmentId <br>";
								if($garmentId != 0)
								{ // there is a garment here
										$this->giveCrewmemberToSaucer($garmentId, $saucerMoving); // give the garment to the ostrich (set garment_location to the color)
										//$ownerOfOstrichMoving = $this->getOwnerIdOfOstrich($ostrichMoving);
										//$this->addToGarmentReplacementQueue($ownerOfOstrichMoving, $ostrichMoving);
										$this->updatePlayerScores(); // update the player boards with current scores

										if($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Airlock"))
										{ // they have Airlock

												//throw new feException( "Airlock");
												// mark that this crewmember could be exchanged
												$this->incrementAirlockExchangeable($garmentId);
										}

										$crewmemberColor = $this->getCrewmemberColorFromId($garmentId);
										$crewmemberTypeId = $this->getCrewmemberTypeIdFromId($garmentId);
										$crewmemberType = $this->convertGarmentTypeIntToString($crewmemberTypeId);

										// add an animation event for the crewmember sliding to the saucer
										array_push($moveEventList, array( 'event_type' => 'crewmemberPickup', 'saucer_moving' => $saucerMoving, 'crewmember_color' => $crewmemberColor, 'crewmember_type' => $crewmemberType));

										// add the animation for the saucer moving onto the space of the crewmember
										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));
								}
								else if($boardValue == "C" || $boardValue == "O")
								{ // this is a CRASH SITE

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));

								}
								else if($boardValue == "S")
								{ // we hit an accelerator

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));

										if($wasPushed)
										{ // the saucer moving was pushed onto this accelerator

												array_push($moveEventList, array( 'event_type' => 'pushedOntoAccelerator', 'saucer_moving' => $saucerMoving, 'spaces_pushed' => $distance));

												$pushedOntoAcceleratorEventList = $this->getEventsWhileExecutingMove($currentX, $thisY, $distance, $direction, $saucerMoving, $wasPushed);
												return array_merge($moveEventList, $pushedOntoAcceleratorEventList); // add the pushed event to the original and return so we don't go any further
										}
										else
										{ // the saucer moving moved onto the accelerator on their turn

												array_push($moveEventList, array( 'event_type' => 'movedOntoAccelerator', 'saucer_moving' => $saucerMoving));

												return $moveEventList; // don't go any further
										}
								}
								else if($boardValue == "D")
								{	// went off a cliff

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));

										array_push($moveEventList, array( 'event_type' => 'saucerCrashed', 'saucer_moving' => $saucerMoving));

										return $moveEventList;
								}
								else
								{ // empty space

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));

										if($saucerWeCollideWith != "")
										{	// there is a saucer here

												// the saucer we collide with will start their movement over
												$this->setSpacesMoved($saucerWeCollideWith, 0);

												if($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Phase Shifter") ||
	  	  									 $this->doesSaucerHaveUpgradePlayed($saucerMoving, "Proximity Mines"))
												{ // this saucer has phase shifter or proximity mines played

														// mark in the database that this saucer has been collided with and will need to execute its move if we decide to collid with it
														// we'll clear these out if they choose not to phase shift
														$this->setPushedOnSaucerTurn($saucerWeCollideWith, $saucerMoving);
														$this->setPushedDistance($saucerWeCollideWith, $distance);
														$this->setPushedDirection($saucerWeCollideWith, $direction);

														// do not move any further because
														return $moveEventList;
												}
										}
								}

								if($saucerWeCollideWith != "")
								{	// there is an ostrich here

										array_push($moveEventList, array( 'event_type' => 'saucerPush', 'saucer_moving' => $saucerMoving, 'saucer_pushed' => $saucerWeCollideWith, 'spaces_pushed' => $distance));

										$pushedEventList = $this->getEventsWhileExecutingMove($currentX, $thisY, $distance, $direction, $saucerWeCollideWith, true);
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

								$boardValue = $this->getBoardSpaceType($currentX, $thisY);

								// see if we are within 1 space of our other saucer
								$this->checkIfPassedByOtherSaucer($saucerMoving, $currentX, $thisY);

							  $this->setSaucerYValue($saucerMoving, $thisY); // set Y value for Saucer

								if($moveType != "Blast Off Thrusters" && $moveType != "Landing Legs")
								{ // we are moving because of blast off thrusters or landing legs
										// we have moved a space so we need to update that in the database so we know when we have moved all our spaces
										$this->incrementSpacesMoved($saucerMoving);
								}

								$saucerWeCollideWith = $this->getSaucerAt($currentX, $thisY, $saucerMoving); // get any ostriches that might be at this location
								$garmentId = $this->getGarmentIdAt($currentX, $thisY); // get a garment here if there is one
								//echo "The garment at ($currentX, $thisY) is: $garmentId <br>";
								if($garmentId != 0)
								{ // there is a garment here
										$this->giveCrewmemberToSaucer($garmentId, $saucerMoving); // give the garment to the ostrich (set garment_location to the color)
										//$ownerOfOstrichMoving = $this->getOwnerIdOfOstrich($ostrichMoving);
										//$this->addToGarmentReplacementQueue($ownerOfOstrichMoving, $ostrichMoving);
										$this->updatePlayerScores(); // update the player boards with current scores

										if($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Airlock"))
										{ // they have Airlock

												//throw new feException( "Airlock");
												// mark that this crewmember could be exchanged
												$this->incrementAirlockExchangeable($garmentId);
										}

										$crewmemberColor = $this->getCrewmemberColorFromId($garmentId);
										$crewmemberTypeId = $this->getCrewmemberTypeIdFromId($garmentId);
										$crewmemberType = $this->convertGarmentTypeIntToString($crewmemberTypeId);

										// add an animation event for the crewmember sliding to the saucer
										array_push($moveEventList, array( 'event_type' => 'crewmemberPickup', 'saucer_moving' => $saucerMoving, 'crewmember_color' => $crewmemberColor, 'crewmember_type' => $crewmemberType));

										// add the animation for the saucer moving onto the space of the crewmember
										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));
								}
								else if($boardValue == "C" || $boardValue == "O")
								{ // this is an EMPTY CRATE

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));

								}
								else if($boardValue == "S")
								{ // we hit an Accelerator

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));

										if($wasPushed)
										{ // the saucer moving was pushed onto this accelerator

												array_push($moveEventList, array( 'event_type' => 'pushedOntoAccelerator', 'saucer_moving' => $saucerMoving, 'spaces_pushed' => $distance));

												$pushedOntoAcceleratorEventList = $this->getEventsWhileExecutingMove($currentX, $thisY, $distance, $direction, $saucerMoving, $wasPushed);
												return array_merge($moveEventList, $pushedOntoAcceleratorEventList); // add the pushed event to the original and return so we don't go any further
										}
										else
										{ // the saucer moving moved onto the accelerator on their turn

												array_push($moveEventList, array( 'event_type' => 'movedOntoAccelerator', 'saucer_moving' => $saucerMoving));

												return $moveEventList; // don't go any further
										}
								}
								else if($boardValue == "D")
								{	// went off a cliff

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));

										array_push($moveEventList, array( 'event_type' => 'saucerCrashed', 'saucer_moving' => $saucerMoving));

										return $moveEventList;
								}
								else
								{ // empty space

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));

										if($saucerWeCollideWith != "")
										{	// there is a saucer here

												// the saucer we collide with will start their movement over
												$this->setSpacesMoved($saucerWeCollideWith, 0);

												if($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Phase Shifter") ||
												   $this->doesSaucerHaveUpgradePlayed($saucerMoving, "Proximity Mines"))
												{ // this saucer has phase shifter or proximity mines played

														// mark in the database that this saucer has been collided with and will need to execute its move if we decide to collid with it
														// we'll clear these out if they choose not to phase shift
														$this->setPushedOnSaucerTurn($saucerWeCollideWith, $saucerMoving);
														$this->setPushedDistance($saucerWeCollideWith, $distance);
														$this->setPushedDirection($saucerWeCollideWith, $direction);

														// do not move any further because
														return $moveEventList;
												}
										}
								}

								if($saucerWeCollideWith != "")
								{	// there is an ostrich here

									array_push($moveEventList, array( 'event_type' => 'saucerPush', 'saucer_moving' => $saucerMoving, 'saucer_pushed' => $saucerWeCollideWith, 'spaces_pushed' => $distance));

									$pushedEventList = $this->getEventsWhileExecutingMove($currentX, $thisY, $distance, $direction, $saucerWeCollideWith, true);
									return array_merge($moveEventList, $pushedEventList); // add the pushed event to the original and return so we don't go any further
								}
						}

						return $moveEventList;
				}

				return $moveEventList; // we should never get here
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
		function getRandomEmptyCrashSite()
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
				$crashSite = $this->getRandomEmptyCrashSite();
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

				// get the colored version and friendly name of the hex color
				$saucerColorFriendly = $this->convertColorToHighlightedText($ostrich);

				if($ostrich == $ostrichWithCrown)
				{
						self::notifyAllPlayers( "crownAcquired", clienttranslate( '${ostrichName} already has the Probe.' ), array(
								'ostrichName' => $saucerColorFriendly,
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



						self::notifyAllPlayers( "crownAcquired", clienttranslate( '${ostrichName} has the Probe and will go first this round!' ), array(
								'color' => $ostrich,
								'ostrichName' => $saucerColorFriendly,
								'player_name' => $playerName
						) );
				}
		}

		function incrementAirlockExchangeable($crewmemberId)
		{
				// get the highest exchangeable value
				$highestAmount = $this->getHighestAirlockExchangeableMax();

				$sql = "UPDATE garment SET airlock_exchangeable=$highestAmount+1
								WHERE garment_id=$crewmemberId" ;
				self::DbQuery( $sql );
		}

		function removeAirlockExchangeable($crewmemberId)
		{
				$sql = "UPDATE garment SET airlock_exchangeable=0
								WHERE garment_id=$crewmemberId" ;
				self::DbQuery( $sql );
		}

		function setOstrichToChosen($ostrich)
		{
				$sql = "UPDATE ostrich SET ostrich_is_chosen=1
											WHERE ostrich_color='$ostrich' " ;
				self::DbQuery( $sql );
		}

		 function incrementTrapsDrawnThisRound($playerId)
		 {
				 $sql = "UPDATE player SET player_traps_drawn_this_round=player_traps_drawn_this_round+1
	 										WHERE player_id='$playerId' " ;
	 						self::DbQuery( $sql );
		 }

		 function incrementSaucerTurnsTaken($saucerColor)
		 {
			 	$updateSql = "UPDATE ostrich SET ostrich_turns_taken=ostrich_turns_taken+1
										  WHERE ostrich_color='$saucerColor' " ;
				self::DbQuery( $updateSql );
		 }

		function incrementPlayerRound($playerId)
		{
			// mark that this player has take their turn this ROUND
			$sql = "UPDATE player SET player_turns_taken_this_round=player_turns_taken_this_round+1
										WHERE player_id='$playerId' " ;
						self::DbQuery( $sql );
		}

		function incrementEnergyForSaucer($saucerColor)
		{
			// add one to energy total
			$sql = "UPDATE ostrich SET energy_quantity=energy_quantity+1
										WHERE ostrich_color='$saucerColor' ";
						self::DbQuery( $sql );
		}

		function decrementEnergyForSaucer($saucerColor)
		{
			// add one to energy total
			$sql = "UPDATE ostrich SET energy_quantity=energy_quantity-1
										WHERE ostrich_color='$saucerColor' ";
						self::DbQuery( $sql );
		}

		function getEnergyCountForSaucer($saucerColor)
		{
				return self::getUniqueValueFromDb("SELECT energy_quantity FROM ostrich WHERE ostrich_color='$saucerColor' ");
		}

		function incrementBoosterForSaucer($saucerColor)
		{
				// add one to booster total
				$sql = "UPDATE ostrich SET booster_quantity=booster_quantity+1
											WHERE ostrich_color='$saucerColor' " ;
							self::DbQuery( $sql );
		}

		function decrementBoosterForSaucer($saucerColor)
		{
				$currentBoosterQuantity = $this->getBoosterCountForSaucer($saucerColor);

				if($currentBoosterQuantity > 0)
				{
						// add one to booster total
						$sql = "UPDATE ostrich SET booster_quantity=booster_quantity-1
													WHERE ostrich_color='$saucerColor' " ;
									self::DbQuery( $sql );
				}
				else
				{
						self::debug( "decrementBoosterForSaucer tried to set a negative value for booster_quantity." );
				}

		}

		function getBoosterCountForSaucer($saucerColor)
		{
				return self::getUniqueValueFromDb("SELECT booster_quantity FROM ostrich WHERE ostrich_color='$saucerColor' ");
		}

		// True if the player can be given a Booster. False otherwise.
		function hasAvailableBoosterSlot($saucerColor)
		{
				$boosterCount = $this->getBoosterCountForSaucer($saucerColor);

				if($boosterCount == 0)
				{ // they do not have a Booster

						return true;
				}

				if($boosterCount == 1 && $this->doesSaucerHaveUpgradePlayed($saucerColor, "Cargo Hold"))
				{ // this saucer can carry an extra booster because of their upgrade Cargo Hold

						return true;
				}

				return false;
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
						$saucerMurderer = $ostrichObject['ostrich_causing_cliff_fall'];




						$boardValue = $this->getBoardSpaceTypeForOstrich($ostrichColor); // get the type of space of the ostrich who just moved
						$ownerOfOstrich = $this->getOwnerIdOfOstrich($ostrichColor); // get the player who controls the ostrich moving


						if($boardValue == "D")
						{ // this saucer is off a cliff
//echo "ostrichColor:".$ostrichColor." ostrichColorText:".$ostrichColorText." saucerMurderer:".$saucerMurderer." saucerMurdererText:".$saucerMurdererText;
								if($saucerMurderer == '')
								{ // we have not yet set this saucer's murderer

										// set the saucer murderer so we know to give them a reward and put in the message log
										$this->setSaucerMurderer($ostrichColor, $ostrichTakingTurn);
//throw new feException( "murdered: $ostrichColor murderer: $ostrichTakingTurn");
										$ostrichColorText = $this->convertColorToHighlightedText($ostrichColor);
										$saucerMurdererText = $this->convertColorToHighlightedText($ostrichTakingTurn);


		//self::debug( "sendCliffFallsToPlayers ostrich:$ostrichColor ostrichTakingTurn:$ostrichTakingTurn" );
										if($ownerOfOstrich == $ownerOfOstrichTakingTurn)
										{ // the ostrich ran off a cliff on their own turn (or in a 2-player game they crashed a saucer of their own color)

/* Removing because we don't need to do this because we will do it in the updateGameLogForEvents method.
													self::notifyAllPlayers( "ostrichRanOffCliff", clienttranslate( '${saucerWhoCrashedText} crashed.' ), array(
															'player_name' => self::getActivePlayerName(),
															'ostrichName' => $this->getOstrichName($ostrichColor),
															'saucerWhoCrashedText' => $ostrichColorText
													) );
*/
													self::incStat( 1, 'times_you_crashed_yourself', $ownerOfOstrich ); // add a that you ran off a cliff
										}
										else
										{ // the ostrich was pushed off a cliff by the player taking their turn

/* Removing because we don't need to do this because we will do it in the updateGameLogForEvents method.
												self::notifyAllPlayers( "ostrichWasPushedOffCliff", clienttranslate( '${saucerWhoIsStealingText} is stealing a Crewmember from ${saucerWhoCrashedText}.' ), array(
														'player_name' => self::getActivePlayerName(),
														'ostrichName' => $this->getOstrichName($ostrichColor),
														'saucerWhoCrashedText' => $ostrichColorText,
														'saucerWhoIsStealingText' => $saucerMurdererText
												) );
*/
												self::incStat( 1, 'saucers_you_crashed', $ownerOfOstrichTakingTurn ); // add stat that the current player pushed an ostrich off a cliff
												self::incStat( 1, 'times_you_were_crashed', $ownerOfOstrich ); // add a stat that the owner of the ostrich who fell off the cliff was pushed off a cliff
										}
								}
						}
				}
		}



		function takeAwayZag ($ostrich)
		{
			$sqlUpdate = "UPDATE ostrich SET ";
			$sqlUpdate .= "ostrich_has_zag=0 WHERE ";
			$sqlUpdate .= "ostrich_color='".$ostrich."'";

			self::DbQuery( $sqlUpdate );
		}

		function setSaucerMurderer($saucerMurdered, $saucerMurderer)
		{
				$sqlUpdate = "UPDATE ostrich SET ";
				$sqlUpdate .= "ostrich_causing_cliff_fall='$saucerMurderer' WHERE ";
				$sqlUpdate .= "ostrich_color='$saucerMurdered'";

				self::DbQuery( $sqlUpdate );
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
//throw new feException( "distanceType: $distanceType");

				$distanceInteger = 0;

				if($distanceType == 0)
				{ // X
						$distanceInteger = $this->getSaucerXValue($saucerColor);

				}
				elseif($distanceType == 1)
				{ // 2
						$distanceInteger = 2;
				}
				elseif($distanceType == 2)
				{ // 3
						$distanceInteger = 3;
				}
				else
				{
						throw new feException( "Unrecognized distance type ($distanceType)");
				}

				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				if($this->getUpgradeTimesActivatedThisRound($saucerWhoseTurnItIs, "Hyperdrive") > 0)
				{ // this player activated hyperdrive this round
						$distanceInteger = $distanceInteger * 2;
				}

				return $distanceInteger;
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



		function getStateName()
		{
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
/* OBSOLETE
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
*/
		function getXOfCrashSite($crashSiteNumber)
		{
				return self::getUniqueValueFromDb("SELECT board_x FROM board WHERE board_space_type=$crashSiteNumber");
		}

		function getYOfCrashSite($crashSiteNumber)
		{
				return self::getUniqueValueFromDb("SELECT board_y FROM board WHERE board_space_type=$crashSiteNumber");
		}

		function getCrewmemberLocationFromId($crewmemberId)
		{
				return self::getUniqueValueFromDb("SELECT garment_location FROM garment WHERE garment_id=$crewmemberId");
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

		function giveSaucerBooster($saucerColor)
		{
			//throw new feException( "giveSaucerBooster saucerColor:$saucerColor");
				$this->incrementBoosterForSaucer($saucerColor); // give the saucer a token
				//throw new feException( "incremented booster count saucerColor:$saucerColor");
				$boosterPosition = $this->getBoosterCountForSaucer($saucerColor); // 1, 2, 3, 4, etc.
//throw new feException( "got booster position saucerColor:$saucerColor");

				$player_id = $this->getOwnerIdOfOstrich($saucerColor);
//throw new feException( "got player id saucerColor:$saucerColor");

				// show the saucer color in its color
				$colorHighlightedText = $this->convertColorToHighlightedText($saucerColor);

				$player_name = $this->getPlayerNameFromPlayerId($player_id);
				self::notifyAllPlayers( 'boosterAcquired', clienttranslate( '${saucer_color_text} gained a Booster.' ), array(
								'player_id' => $player_id,
								'boosterPosition' => $boosterPosition,
								'saucerColor' => $saucerColor,
								'player_name' => $player_name,
								'saucer_color_text' => $colorHighlightedText
				) );
		}

		function giveSaucerEnergy($saucerColor)
		{
				$this->incrementEnergyForSaucer($saucerColor); // give the saucer a token
				$energyPosition = $this->getEnergyCountForSaucer($saucerColor); // 1, 2, 3, 4, etc.
				$player_id = self::getCurrentPlayerId(); // Current Player = player who played the current player action (the one who made the AJAX request). Active Player = player whose turn it is.

				// show the saucer color in its color
				$colorHighlightedText = $this->convertColorToHighlightedText($saucerColor);


				$player_name = self::getCurrentPlayerName();
				self::notifyAllPlayers( 'energyAcquired', clienttranslate( '${saucer_color_text} gained an Energy.' ), array(
								'player_id' => $player_id,
								'energyPosition' => $energyPosition,
								'saucerColor' => $saucerColor,
								'player_name' => $player_name,
								'saucer_color_text' => $colorHighlightedText
				) );
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

						case "e77324":
							return "ORANGE";
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
							//throw new feException( "isEndGameConditionMet numberOfOstrichesThisPlayerHas:$numberOfOstrichesThisPlayerHas numberOfFullyGarmentedOstrichesThisPlayerHas:$numberOfFullyGarmentedOstrichesThisPlayerHas");
								return true;
						}
        }
//throw new feException( "isEndGameConditionMet numberOfOstrichesThisPlayerHas:$numberOfOstrichesThisPlayerHas numberOfFullyGarmentedOstrichesThisPlayerHas:$numberOfFullyGarmentedOstrichesThisPlayerHas");
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
				else if($this->hasOstrichPlayedX($ostrichWhoseTurnItIs) && !$this->hasSaucerChosenX($ostrichWhoseTurnItIs))
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

		// This sets the state after all movement has completed for the current ostrich. Considerations:
		//   1. Saucer Suicide - ask which Crewmember they want to lose and, if lowest total tied, ask them who gets it
		//   2. Saucer Murdered - ask murderer if they want to steal a crewmember or take energy, if steal, ask which to steal
		//   3. Garment Acquired - ask player whose turn it is which crewmember to place
		//   4. End of Turn Upgrade Effects - if they have any end of turn upgrade effects, ask if they want to use any of them
		//   5. Ship Upgrade - if they have 2 Energy, ask if they wish to upgrade, and if so, ask which they want to play
		function endSaucerTurnCleanUp()
		{

				// in case a saucer was pushed and then goes later in the round, we want to make sure all saucers get spaces moved set back to 0
				$this->resetSpacesMovedAllSaucers();

//throw new feException( "endSaucerTurnCleanUp");
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs(); // the saucer whose turn it is (empty string if we don't know)
				$nextSaucerWithPendingCrashReward = $this->nextPendingCrashReward($saucerWhoseTurnItIs);
				$needToPlaceCrewmember = $this->doesCrewmemberNeedToBePlaced();
				//echo "needToPlaceCrewmember is ($needToPlaceCrewmember) for ostrich $saucerWhoseTurnItIs <br>";
				//echo "nextSaucerWithPendingCrashReward is ($nextSaucerWithPendingCrashReward) for ostrich $saucerWhoseTurnItIs <br>";
				if($this->hasPendingCrashPenalty($saucerWhoseTurnItIs) && $this->getSkippedGivingAway($saucerWhoseTurnItIs) != 1)
				{ // player whose turn it is crashed and hasn't yet been penalized for crashing on their own turn
						$this->gamestate->nextState( "crashPenaltyAskWhichToGiveAway" );
				}
				elseif($nextSaucerWithPendingCrashReward != '')
				{ // the saucer whose turn it is crashed another saucer and can either steal from them or take an energy and they haven't gotten their reward yet
//throw new feException( "nextSaucerWithPendingCrashReward: $nextSaucerWithPendingCrashReward");
						$this->gamestate->nextState( "crashPenaltyAskWhichToSteal" );
				}
				elseif($needToPlaceCrewmember)
				{ // a crewmember needs to be respawned and there is at least 1 lost crewmember available

						$this->gamestate->nextState( "placeCrewmemberChooseCrewmember" );
				}
				elseif($this->doesPlayerHaveAnyEndOfTurnUpgradesToActivate($saucerWhoseTurnItIs))
				{ // see if they want to use any end of turn upgrades
						$this->gamestate->nextState( "askWhichEndOfTurnUpgradeToUse" );
				}
				elseif($this->getEnergyCountForSaucer($saucerWhoseTurnItIs) > 1)
				{ // see if they can upgrade their ship
						$this->gamestate->nextState( "askWhichUpgradeToPlay" );
				}
				else
				{

					// there is nothing special the player can do so we can end their turn
//throw new feException( "endMovementTurn");

							$this->gamestate->nextState( "endSaucerTurn" );
							//$this->endMovementTurn(); // end the turn (ostrich version)


/*
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

											if($this->doesSaucerHaveOffColoredCrewmember($ostrichToRespawn))
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
*/
				}
		}

		function setState_AfterMovementEvents($saucerMoving, $moveType, $wasPushed=false)
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				$currentState = $this->getStateName();

				// reset pushed setting so we don't think a saucer was pushed when we do our next move
				$this->resetPushedForAllSaucers();

			//throw new feException("Getting board space type after move events for saucer ($saucerMoving).");
				$boardValue = $this->getBoardSpaceTypeForOstrich($saucerWhoseTurnItIs); // get the type of space of the ostrich who just moved

				// count crewmembers they can exchange with Airlock if they have it
				$airlockExchangeableCrewmembers = array();
				if($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Airlock"))
				{ // they have Airlock

						$airlockExchangeableCrewmembers = $this->getAirlockExchangeableCrewmembersForSaucer($saucerWhoseTurnItIs);
				}

				if($this->isEndGameConditionMet())
				{ // the game has ended
						$this->gamestate->nextState( "endGame" );
				}
				else if($boardValue == "S")
				{ // the saucer onto an accelerator on their turn
						$this->gamestate->nextState( "chooseAcceleratorDirection" ); // need to ask the player which direction they want to go on the skateboard
				}
				else if($this->canSaucerBoost($saucerWhoseTurnItIs) && $this->getSkippedBoosting($saucerWhoseTurnItIs) == 0 && $moveType == 'regular')
				{ // the player has a boost they can use and they have not crashed
						$this->gamestate->nextState( "chooseIfYouWillUseBooster" ); // need to ask the player if they want to use a zag, and if so, which direction they want to travel
				}
				elseif(count($airlockExchangeableCrewmembers) > 0)
				{ // this saucer has at least one crewmember they can exchange

						if($saucerMoving != $saucerWhoseTurnItIs)
						{ // the moving saucer was pushed into picking up a crewmember
								$ownerOfSaucerMoving = $this->getOwnerIdOfOstrich($saucerMoving);
								$this->changeActivePlayer($ownerOfSaucerMoving);
						}
						$this->gamestate->nextState( "chooseCrewmemberToAirlock" );
				}
				else if($this->canSaucerPassCrewmembers($saucerWhoseTurnItIs))
				{ // they passed by their own Saucer and can pass them a Crewmember
						$this->gamestate->nextState( "chooseCrewmembersToPass" ); // need to ask the player if they want to use a zag, and if so, which direction they want to travel
				}
				else if($this->canSaucerTakeCrewmembers($saucerWhoseTurnItIs))
				{ // they passed by their other Saucer and can take from them
						$this->gamestate->nextState( "chooseCrewmembersToTake" ); // need to ask the player if they want to use a zag, and if so, which direction they want to travel
				}
				else if($currentState == "crashPenaltyAskWhichToSteal")
				{ // they were just asked which penalty they wanted for crashing someone
						$this->gamestate->nextState( "endSaucerTurnCleanUp" );
				}
				else
				{ // movement is complete
						if($moveType == 'Landing Legs' || $moveType == 'Afterburner')
						{ // this is a bonus move from an Upgrade

								// we don't want them to be able to undo their turn to skip finalizeMove
								$this->gamestate->nextState( "endSaucerTurnCleanUp" );
						}
						else
						{ // this is a regular moves

								// we do want them to be able to undo their turn
								$this->gamestate->nextState( "finalizeMove" );
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
				$this->saveSaucerLastDirection();

		}

		function executeWormholeSelectSaucer($saucerColor)
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$mySaucerX = $this->getSaucerXLocation($saucerWhoseTurnItIs);
				$mySaucerY = $this->getSaucerYLocation($saucerWhoseTurnItIs);

				$chosenSaucerX = $this->getSaucerXLocation($saucerColor);
				$chosenSaucerY = $this->getSaucerYLocation($saucerColor);

				// update the X and Y and notify all players
				$this->placeSaucerOnSpace($saucerColor, $mySaucerX, $mySaucerY);
				$this->placeSaucerOnSpace($saucerWhoseTurnItIs, $chosenSaucerX, $chosenSaucerY);

				// mark that we have activated it
				$this->activateUpgradeWithCollectorNumber($saucerWhoseTurnItIs, 2);

				$this->gamestate->nextState( "endSaucerTurnCleanUp" );
		}

		function executeEnergyRewardSelection($saucerCrashed)
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				// give the saucer an energy
				$this->giveSaucerEnergy($saucerWhoseTurnItIs);

//throw new feException( "executeEnergyRewardSelection with saucerCrashed: $saucerCrashed");

				// mark that the reward for this crash has been acquired so we don't let them have multiple rewards
				$this->markCrashPenaltyRendered($saucerCrashed);

				$this->gamestate->nextState( "endSaucerTurnCleanUp" );
		}

		function executeActivatePhaseShifter()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				$this->activateUpgrade($saucerWhoseTurnItIs, "Phase Shifter");

				// set asked_to_activate_this_round to 1 so we don't ask again
				//$this->setAskedToActivateUpgrade($saucerWhoseTurnItIs, "Phase Shifter");

				// reset pushed setting so we don't think a saucer was pushed when we do our next move
				$this->resetPushedForAllSaucers();

				//throw new feException( "executeSkipPhaseShifter");

				// finish any movement that still remains
				$this->gamestate->nextState( "executingMove" );
		}

		function executeSkipPhaseShifter()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				// set asked_to_activate_this_round to 1 so we don't ask again
				//$this->setAskedToActivateUpgrade($saucerWhoseTurnItIs, "Phase Shifter");

				// finish any movement that still remains
				$this->gamestate->nextState( "executingMove" );
		}

		function executeActivateProximityMines($saucerCrashed)
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				$this->activateUpgrade($saucerWhoseTurnItIs, "Proximity Mines");

				// set the saucer murderer so we know to give them a reward and put in the message log
				$this->setSaucerMurderer($saucerCrashed, $saucerWhoseTurnItIs);

				$ownerOfMurderer = $this->getOwnerIdOfOstrich($saucerWhoseTurnItIs);
				$ownerOfMurderee = $this->getOwnerIdOfOstrich($saucerCrashed);

				self::incStat( 1, 'saucers_you_crashed', $ownerOfMurderer ); // add stat that the current player pushed an ostrich off a cliff
				self::incStat( 1, 'times_you_were_crashed', $ownerOfMurderee ); // add a stat that the owner of the ostrich who fell off the cliff was pushed off a cliff

				// move them off the board and notify players
				$this->placeSaucerOnSpace($saucerCrashed, 0, 0);

				// say they're already been penalized for "crashing" so no one gets a reward for it
				//$this->markCrashPenaltyRendered($saucerCrashed);

				// reset pushed setting so we don't think a saucer was pushed when we do our next move
				$this->resetPushedForAllSaucers();

				//throw new feException( "executeSkipPhaseShifter");

				// finish any movement that still remains
				$this->gamestate->nextState( "executingMove" );
		}

		function executeSkipProximityMines()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				// finish any movement that still remains
				$this->gamestate->nextState( "executingMove" );
		}

		function executeActivateHyperdrive()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				$this->activateUpgrade($saucerWhoseTurnItIs, "Hyperdrive");

				// set asked_to_activate_this_round to 1 so we don't ask again
				$this->setAskedToActivateUpgrade($saucerWhoseTurnItIs, "Hyperdrive");

				$this->gamestate->nextState( "checkForRevealDecisions" );
		}

		function executeSkipHyperdrive()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				// set asked_to_activate_this_round to 1 so we don't ask again
				$this->setAskedToActivateUpgrade($saucerWhoseTurnItIs, "Hyperdrive");

				$this->gamestate->nextState( "checkForRevealDecisions" );
		}

		function executeSkipGiveAwayCrewmember()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				// mark that the player chose not to pass this round
				$this->setSkippedGivingAway($saucerWhoseTurnItIs, 1);

				// figure out which type of move this is
				$moveType = $this->getMoveTypeWeAreExecuting();

				$this->setState_AfterMovementEvents($saucerWhoseTurnItIs, $moveType); // set to true because we're already passed the boosting if we're giving away so that is safest
		}

		function executeSkipStealCrewmember($saucerWhoCrashed)
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				// mark that the player chose not to pass this round
				//$this->setSkippedStealing($saucerWhoseTurnItIs, 1);

				// mark this as the penalty for this saucer being satisfied (it's possible there were multiple saucers they crashed)
				$this->markCrashPenaltyRendered($saucerWhoCrashed);

				// figure out which type of move this is
				$moveType = $this->getMoveTypeWeAreExecuting();

				$this->setState_AfterMovementEvents($saucerWhoseTurnItIs, $moveType); // set to true because we're already passed the boosting if we're stealing so that is safest
		}

		function executeSkipPassCrewmember()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				// mark that the player chose not to pass this round
				$this->setSkippedPassing($saucerWhoseTurnItIs, 1);

				// figure out which type of move this is
				$moveType = $this->getMoveTypeWeAreExecuting();

				$this->setState_AfterMovementEvents($saucerWhoseTurnItIs, $moveType); // set to true because we're already passed the boosting if we're passing so that is safest
		}

		function executeSkipTakeCrewmember()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				// mark that the player chose not to take this round
				$this->setSkippedTaking($saucerWhoseTurnItIs, 1);

				// figure out which type of move this is
				$moveType = $this->getMoveTypeWeAreExecuting();

				$this->setState_AfterMovementEvents($saucerWhoseTurnItIs, $moveType); // set to true because we're already passed the boosting if we're taking so that is safest
		}

		function executeChooseCrashSite( $crashSiteNumber )
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				$crashSiteX = $this->getXOfCrashSite($crashSiteNumber);
				$crashSiteY = $this->getYOfCrashSite($crashSiteNumber);

				$this->placeSaucerOnSpace($saucerWhoseTurnItIs, $crashSiteX, $crashSiteY);

				$this->gamestate->nextState( "endSaucerTurnCleanUp" );
		}

		function executeTractorBeamCrewmember($crewmemberType, $crewmemberColor)
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerMovingHighlightedText = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);

				$crewmemberId = $this->getGarmentIdFromType($crewmemberType, $crewmemberColor);
				//echo "The garment at ($thisX,$currentY) is: $garmentId <br>";

				$this->giveCrewmemberToSaucer($crewmemberId, $saucerWhoseTurnItIs); // give the garment to the ostrich (set garment_location to the color)
				//$ownerOfOstrichMoving = $this->getOwnerIdOfOstrich($ostrichMoving);
				//$this->addToGarmentReplacementQueue($ownerOfOstrichMoving, $ostrichMoving);
				$this->updatePlayerScores(); // update the player boards with current scores

				$crewmemberColor = $this->getCrewmemberColorFromId($crewmemberId);
				$crewmemberTypeId = $this->getCrewmemberTypeIdFromId($crewmemberId);
				$crewmemberType = $this->convertGarmentTypeIntToString($crewmemberTypeId);

				// notify
				self::notifyAllPlayers( "crewmemberPickup", clienttranslate( '${saucerMovingHighlightedText} picks up ${CREWMEMBERIMAGE} with their Tractor Beam.' ), array(
					'saucerMovingHighlightedText' => $saucerMovingHighlightedText,
					'CREWMEMBERIMAGE' => 'CREWMEMBERIMAGE_'.$crewmemberType.'_'.$crewmemberColor,
					'crewmemberType' => $crewmemberType,
					'crewmemberColor' => $crewmemberColor,
					'saucerColor' => $saucerWhoseTurnItIs
				) );

				$this->gamestate->nextState( "endSaucerTurnCleanUp" );
		}

		// Called when a player chooses a Distress Signaler crewmember to TAKE.
		function executeDistressSignalerTakeCrewmember($crewmemberType, $crewmemberColor)
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerMovingHighlightedText = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);

				$crewmemberId = $this->getGarmentIdFromType($crewmemberType, $crewmemberColor);
				//echo "The garment at ($thisX,$currentY) is: $garmentId <br>";
				// get the owner of that Crewmember
				$saucerHoldingTakenCrewmember = $this->getCrewmemberLocationFromId($crewmemberId);
				$saucerHoldingTakenHighlightedText = $this->convertColorToHighlightedText($saucerHoldingTakenCrewmember);

				// mark this Saucer has having given away a crewmember
				$this->setSaucerGivenWithDistress($saucerHoldingTakenCrewmember, 1);

				// mark this Crewmember as taken so we know which one to swap with it
				$this->setCrewmemberTakenWithDistress($crewmemberId, 1);

				$this->giveCrewmemberToSaucer($crewmemberId, $saucerWhoseTurnItIs); // give the garment to the ostrich (set garment_location to the color)
				//$ownerOfOstrichMoving = $this->getOwnerIdOfOstrich($ostrichMoving);
				//$this->addToGarmentReplacementQueue($ownerOfOstrichMoving, $ostrichMoving);
				$this->updatePlayerScores(); // update the player boards with current scores

				$crewmemberColor = $this->getCrewmemberColorFromId($crewmemberId);
				$crewmemberTypeId = $this->getCrewmemberTypeIdFromId($crewmemberId);
				$crewmemberType = $this->convertGarmentTypeIntToString($crewmemberTypeId);

				// notify
				self::notifyAllPlayers( "crewmemberPickup", clienttranslate( '${saucerMovingHighlightedText} picks up ${CREWMEMBERIMAGE} with their Distress Signaler.' ), array(
					'saucerMovingHighlightedText' => $saucerMovingHighlightedText,
					'CREWMEMBERIMAGE' => 'CREWMEMBERIMAGE_'.$crewmemberType.'_'.$crewmemberColor,
					'crewmemberType' => $crewmemberType,
					'crewmemberColor' => $crewmemberColor,
					'saucerColor' => $saucerWhoseTurnItIs
				) );

				// get all the valid crewmembers to give
				$crewmembersValidToGive = $this->getDistressSignalableGiveCrewmembers($saucerWhoseTurnItIs);
//$count = count($crewmembersValidToGive);
//throw new feException( "count $count");
				if(count($crewmembersValidToGive) > 1)
				{ // you have more than 1 possible crewmember to give

						// move to the state where they can choose which to give
						$this->gamestate->nextState( "chooseDistressSignalerGiveCrewmember" );

				}
				else
				{ // there is only one crewmember of this type they can give so let's just do interface

						// reset crewmember taken with distress signaler since we might have another one we can take
						$this->setCrewmemberTakenWithDistress($crewmemberId, 0);

						// get the Crewmember taken (taken_with_distress=1)
						//$crewmemberTakenId = $this->getCrewmemberIdTakenWithDistress();
						$crewmemberGivenId = 0;
						foreach($crewmembersValidToGive as $crewmember)
						{ // there is only 1

								$crewmemberGivenId = $crewmember['garment_id'];
						}

//throw new feException( "giving crewmember $crewmemberGivenId to saucer $saucerHoldingTakenCrewmember");
						$this->giveCrewmemberToSaucer($crewmemberGivenId, $saucerHoldingTakenCrewmember);
						$this->updatePlayerScores(); // update the player boards with current scores

						$crewmemberColor = $this->getCrewmemberColorFromId($crewmemberGivenId);
						$crewmemberTypeId = $this->getCrewmemberTypeIdFromId($crewmemberGivenId);
						$crewmemberType = $this->convertGarmentTypeIntToString($crewmemberTypeId);

						// notify
						self::notifyAllPlayers( "crewmemberPickup", clienttranslate( '${saucerMovingHighlightedText} is given ${CREWMEMBERIMAGE} because of Distress Signaler.' ), array(
							'saucerMovingHighlightedText' => $saucerHoldingTakenHighlightedText,
							'CREWMEMBERIMAGE' => 'CREWMEMBERIMAGE_'.$crewmemberType.'_'.$crewmemberColor,
							'crewmemberType' => $crewmemberType,
							'crewmemberColor' => $crewmemberColor,
							'saucerColor' => $saucerHoldingTakenCrewmember
						) );

						$this->gamestate->nextState( "endSaucerTurnCleanUp" );
				}
		}

		// Called when a player chooses a Distress Signaler crewmember to GIVE.
		function executeDistressSignalerGiveCrewmember($crewmemberType, $crewmemberColor)
		{
				// get the Crewmember taken (taken_with_distress=1)
				$crewmemberTakenId = $this->getCrewmemberIdTakenWithDistress();
//if($crewmemberTakenId == '')
//	throw new feException( "crewmemberTakenId $crewmemberTakenId");




				// get the owner of that Crewmember that was taken with Distress Signaler
				$saucerHoldingTakenCrewmember = $this->getSaucerGivenWithDistress();
				$saucerHoldingTakenHighlightedText = $this->convertColorToHighlightedText($saucerHoldingTakenCrewmember);

				// reset crewmember taken with distress signaler since we might have another one we can take
				$this->setCrewmemberTakenWithDistress($crewmemberTakenId, 0);

				// reset this Saucer as having given away a crewmember
				$this->setSaucerGivenWithDistress($saucerHoldingTakenCrewmember, 0);

				// get the Crewmember we're giving
				$crewmemberGivingId = $this->getGarmentIdFromType($crewmemberType, $crewmemberColor);
				//echo "The garment at ($thisX,$currentY) is: $garmentId <br>";
throw new feException( "crewmemberGivingId $crewmemberGivingId to saucer $saucerHoldingTakenCrewmember");
				$this->giveCrewmemberToSaucer($crewmemberGivingId, $saucerHoldingTakenCrewmember); // give the garment to the ostrich (set garment_location to the color)
				//$ownerOfOstrichMoving = $this->getOwnerIdOfOstrich($ostrichMoving);
				//$this->addToGarmentReplacementQueue($ownerOfOstrichMoving, $ostrichMoving);
				$this->updatePlayerScores(); // update the player boards with current scores

				$crewmemberGivingColor = $this->getCrewmemberColorFromId($crewmemberGivingId);
				$crewmemberGivingTypeId = $this->getCrewmemberTypeIdFromId($crewmemberGivingId);
				$crewmemberGivingType = $this->convertGarmentTypeIntToString($crewmemberGivingTypeId);

				// notify
				self::notifyAllPlayers( "crewmemberPickup", clienttranslate( '${saucerMovingHighlightedText} is given ${CREWMEMBERIMAGE} because of Distress Signaler.' ), array(
					'saucerMovingHighlightedText' => $saucerHoldingTakenHighlightedText,
					'CREWMEMBERIMAGE' => 'CREWMEMBERIMAGE_'.$crewmemberGivingType.'_'.$crewmemberGivingColor,
					'crewmemberType' => $crewmemberGivingType,
					'crewmemberColor' => $crewmemberGivingColor,
					'saucerColor' => $saucerHoldingTakenCrewmember
				) );

				//throw new feException( "afternotify");

				$this->gamestate->nextState( "endSaucerTurnCleanUp" );
		}

		function executeAirlockCrewmember($crewmemberType, $crewmemberColor)
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerMovingHighlightedText = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);

				$crewmemberId = $this->getGarmentIdFromType($crewmemberType, $crewmemberColor);
				//echo "The garment at ($thisX,$currentY) is: $garmentId <br>";

				$this->giveCrewmemberToSaucer($crewmemberId, $saucerWhoseTurnItIs); // give the garment to the ostrich (set garment_location to the color)
				//$ownerOfOstrichMoving = $this->getOwnerIdOfOstrich($ostrichMoving);
				//$this->addToGarmentReplacementQueue($ownerOfOstrichMoving, $ostrichMoving);
				$this->updatePlayerScores(); // update the player boards with current scores

				$crewmemberColor = $this->getCrewmemberColorFromId($crewmemberId);
				$crewmemberTypeId = $this->getCrewmemberTypeIdFromId($crewmemberId);
				$crewmemberType = $this->convertGarmentTypeIntToString($crewmemberTypeId);

				// notify
				self::notifyAllPlayers( "crewmemberPickup", clienttranslate( '${saucerMovingHighlightedText} used Airlock to exchange the Crewmember they picked up to get ${CREWMEMBERIMAGE} instead.' ), array(
					'saucerMovingHighlightedText' => $saucerMovingHighlightedText,
					'CREWMEMBERIMAGE' => 'CREWMEMBERIMAGE_'.$crewmemberType.'_'.$crewmemberColor,
					'crewmemberType' => $crewmemberType,
					'crewmemberColor' => $crewmemberColor,
					'saucerColor' => $saucerWhoseTurnItIs
				) );

				// get the ID of the highest crewmember available for exchange (in case they picked up 2 on the same turn)
				$crewmemberIdTaken = $this->getHighestAirlockExchangeableId();

				//throw new feException( "executeAirlockCrewmember crewmemberIdTaken: $crewmemberIdTaken");

				// mark this crewmember as taken so we don't offer another exchange with it
				$this->removeAirlockExchangeable($crewmemberIdTaken);

				// place the Crewmember they originally took
				$this->randomlyPlaceCrewmember($crewmemberIdTaken);

				$this->gamestate->nextState( "endSaucerTurnCleanUp" );
		}

		function executeStealCrewmember( $stolenTypeText, $stolenColor, $areWePassing, $areWeTaking )
		{
				$saucerReceiving = $this->getOstrichWhoseTurnItIs();
				$saucerGiving = $this->getSaucerThatCrashed();
//throw new feException( "saucerReceiving:$saucerReceiving");
				if($areWePassing)
				{ // we are passing a crewmember from one of our saucers to the other one
						$saucerGiving = $this->getOstrichWhoseTurnItIs();
						$saucerReceiving = $this->getPlayersOtherSaucer($saucerGiving);
				}
				elseif($areWeTaking)
				{ // we are taking a crewmember from one of our saucers to the other one
						$saucerReceiving = $this->getOstrichWhoseTurnItIs();
						$saucerGiving = $this->getPlayersOtherSaucer($saucerReceiving);
				}

				$stolenTypeInt = $this->convertGarmentTypeStringToInt($stolenTypeText);

				//throw new feException( "saucerStealing:$saucerStealing saucerCrashed: $saucerCrashed stolenColor: $stolenColor stolenTypeInt: $stolenTypeInt");

				// change the owner of the crewmember in the database
				$sql = "UPDATE garment SET garment_location='$saucerReceiving' WHERE garment_location='$saucerGiving' AND garment_color='$stolenColor' AND garment_type=$stolenTypeInt";
				self::DbQuery( $sql );

				$stealingSaucerColorText = $this->convertColorToHighlightedText($saucerReceiving);
				$stolenFromSaucerColorText = $this->convertColorToHighlightedText($saucerGiving);
//throw new feException( "saucerStealing:$stealingSaucerColorText saucerCrashed: $stolenFromSaucerColorText");
//throw new feException( "stolenTypeText:$stolenTypeText");

				// notify all players so the crewmember can move from one saucer to another
				self::notifyAllPlayers( "stealCrewmember", clienttranslate( '${stealingSaucerColorText} took a ${CREWMEMBERIMAGE} from ${stolenFromSaucerColorText}.' ), array(
						'stealingSaucerColorText' => $stealingSaucerColorText,
						'stolenFromSaucerColorText' => $stolenFromSaucerColorText,
            'crewmemberType' => $stolenTypeText,
						'crewmemberColor' => $stolenColor,
						'saucerColorStealing' => $saucerReceiving,
						'CREWMEMBERIMAGE' => 'CREWMEMBERIMAGE_'.$stolenTypeText.'_'.$stolenColor
        ) );



				// mark that the reward for this crash has been acquired so we don't let them have multiple rewards

				if($areWePassing)
				{ // we are passing a Crewmember to our other Saucer
						$this->setState_AfterMovementEvents($saucerGiving, true); // set to true because we're already passed the boosting if we're passing so that is safest

				}
				elseif($areWeTaking)
				{
						$this->setState_AfterMovementEvents($saucerReceiving, true); // set to true because we're already passed the boosting if we're taking so that is safest
				}
				else
				{ // this is a standard steal, not passing between saucers
						$this->markCrashPenaltyRendered($saucerGiving);

						// increment any stats related to thefts
						$ownerOfStealer = $this->getOwnerIdOfOstrich($saucerReceiving);
						$ownerOfStealee = $this->getOwnerIdOfOstrich($saucerGiving);
						self::incStat( 1, 'crewmembers_you_stole', $ownerOfStealer ); // add stat that says the player using played a trap
						self::incStat( 1, 'crewmembers_stolen_from_you', $ownerOfStealee ); // add stat that the owner of the ostrich targeted was targeted by a trap

						$this->gamestate->nextState( "endSaucerTurnCleanUp" );
				}
		}

		function executeGiveAwayCrewmember($crewmemberTypeText, $crewmemberColor, $saucerToGiveToColorHex)
		{
				$saucerGiving = $this->getOstrichWhoseTurnItIs();
				$givingSaucerColorText = $this->convertColorToText($saucerToGiveToColorHex);

				$crewmemberTypeInt = $this->convertGarmentTypeStringToInt($crewmemberTypeText);

				// change the owner of the crewmember in the database
				$sql = "UPDATE garment SET garment_location='$saucerToGiveToColorHex' WHERE garment_location='$saucerGiving' AND garment_color='$crewmemberColor' AND garment_type=$crewmemberTypeInt";
				self::DbQuery( $sql );

				// increment any stats related to thefts
				//self::incStat( 1, 'i_used_trap', $playerUsing ); // add stat that says the player using played a trap
				//self::incStat( 1, 'trap_used_on_me', $ownerOfOstrichTarget ); // add stat that the owner of the ostrich targeted was targeted by a trap

				$receivingSaucerColorText = $this->convertColorToHighlightedText($saucerToGiveToColorHex);
				$givingSaucerColorText = $this->convertColorToHighlightedText($saucerGiving);
	//throw new feException( "saucerStealing:$stealingSaucerColorText saucerCrashed: $stolenFromSaucerColorText");
	//throw new feException( "stolenTypeText:$stolenTypeText");

				// notify all players so the crewmember can move from one saucer to another
				self::notifyAllPlayers( "stealCrewmember", clienttranslate( '${givingSaucerColorText} gave a ${CREWMEMBERIMAGE} to ${receivingSaucerColorText}.' ), array(
						'givingSaucerColorText' => $givingSaucerColorText,
						'receivingSaucerColorText' => $receivingSaucerColorText,
						'crewmemberType' => $crewmemberTypeText,
						'crewmemberColor' => $crewmemberColor,
						'saucerColorStealing' => $saucerToGiveToColorHex,
						'CREWMEMBERIMAGE' => 'CREWMEMBERIMAGE_'.$crewmemberTypeText.'_'.$crewmemberColor
				) );

				// mark that the reward for this crash has been acquired so we don't let them have multiple rewards
				$this->markCrashPenaltyRendered($saucerGiving);

				$this->gamestate->nextState( "endSaucerTurnCleanUp" );
		}

		function executeChooseOstrichToGoNext()
		{
				$ostrich = $this->getOstrichWhoseTurnItIs();
				$this->setOstrichToChosen($ostrich);

				$this->gamestate->nextState( "zigChosen" ); // stay in this phase
		}

		function executeClickedSaucerToPlace($colorAsHex)
		{
				$currentState = $this->getStateName();

				// place it at a random location
				$foundUnoccupiedCrashSite = $this->randomlyPlaceSaucer($colorAsHex);

				if($this->doesSaucerHaveUpgradePlayed($colorAsHex, 'Scavenger Bot'))
				{ // this saucer has Scavenger Bot

						// give a Booster
						if($this->hasAvailableBoosterSlot($colorAsHex))
						{ // has an available booster slot
								$this->giveSaucerBooster($colorAsHex);
						}

						// give an Energy
						$this->giveSaucerEnergy($colorAsHex);
				}

				if($foundUnoccupiedCrashSite)
				{ // the saucer was successfully placed

						if($currentState == "endRoundPlaceCrashedSaucer")
						{ // we are placing a crashed saucer at the end of a round

								// go back to end round clean-up to see if we have more crashed saucers to place
								$this->gamestate->nextState( "endRoundCleanUp" );
						}
						elseif($currentState == "askPreTurnToPlaceCrashedSaucer")
						{ // we are placing a crashed saucer before a player's turn

								// now this saucer can choose any direction
								$this->gamestate->nextState( "chooseDirectionAfterPlacement" );
						}
				}
				else
				{ // there were no unoccupied crash sites

						if($currentState == "endRoundPlaceCrashedSaucer")
						{ // we are placing a crashed saucer at the end of a round

							// let the player choose a space to be placed
							$this->gamestate->nextState( "allCrashSitesOccupiedChooseSpaceEndRound" );
						}
						elseif($currentState == "askPreTurnToPlaceCrashedSaucer")
						{ // we are placing a crashed saucer before a player's turn

							// let the player choose a space to be placed
							$this->gamestate->nextState( "allCrashSitesOccupiedChooseSpacePreTurn" );
						}
				}
		}


		function executeClickedSaucerToGoFirst($colorHex)
		{
				// set this saucer to the one going next ostrich_is_chosen
				$this->setOstrichToChosen($colorHex);

				$this->gamestate->nextState( "locateCrashedSaucer" );
		}

		function executeClickedBeginTurn()
		{
				$this->gamestate->nextState( "checkStartOfTurnUpgrades" );
		}

		// The player has just moved their saucer but decided to undo it.
		function executeClickedUndoMove()
		{
				$this->checkAction('undoMove');

				// reset the database to the restore point right before the player started moving
        $this->undoRestorePoint();

				// go back to the where the player was starting their move
				$this->gamestate->nextState( "beginTurn" );
		}

		// The player has just moved their saucer and decided not to undo it.
		function executeClickedFinalizeMove()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$distanceType = $this->getSaucerDistanceType($saucerWhoseTurnItIs);

				$cardId = $this->getMoveCardIdFromSaucerDistanceType($saucerWhoseTurnItIs, $distanceType);

				// update the location back to unchosen
				$this->setCardChosenState($cardId, 'unchosen');

				// update the card direction to none?


				// notify all players that this move is finished so the card can be returned to hand
				self::notifyAllPlayers( "confirmedMovement", '', array(
            'saucer_color' => $saucerWhoseTurnItIs
        ) );

				$this->gamestate->nextState( "endSaucerTurnCleanUp" );
		}

		// A player selected their move card(s) and clicked Confirm.
		function executeClickedConfirmMove( $saucer1Color, $saucer1Distance, $saucer1Direction, $saucer2Color, $saucer2Distance, $saucer2Direction )
    {
				// Check that this is the player's turn and that it is a "possible action" at this game state (see states.inc.php)
        self::checkAction( 'confirmMove' );
//throw new feException( "Clicked confirm move saucer1Distance=$saucer1Distance and saucer1Direction=$saucer1Direction.");

				!$this->isValidMove($saucer1Color, $saucer1Distance, $saucer1Direction, $saucer2Color, $saucer2Distance, $saucer2Direction, true); // validate the move in different ways and throw an error if any failures

				$playerConfirming = $this->getOwnerIdOfOstrich($saucer1Color);

				// save the move card and direction we used
				$this->saveSaucerMoveCardDirection($saucer1Color, $saucer1Direction); // save the direction so we have it in case we are pushed before our turn comes up
				$this->saveSaucerMoveCardDistance($saucer1Color, $saucer1Distance); // save the distance so we have it in case we are pushed before our turn comes up

				$saucer1CardId = $this->getMoveCardIdFromSaucerDistanceType($saucer1Color, $saucer1Distance);

				// update the database so we know this card is chosen
				$this->setCardChosenState($saucer1CardId, 'chosen');

				// tell everyone this is chosen so the card back can be placed on this saucer mat
				$this->notifyPlayersOfCardSelection($playerConfirming, $saucer1Color);

				if($saucer2Color != '')
				{ // we have a second saucer
						$this->saveSaucerMoveCardDirection($saucer2Color, $saucer2Direction); // save the direction so we have it in case we are pushed before our turn comes up
						$this->saveSaucerMoveCardDistance($saucer2Color, $saucer2Distance); // save the distance so we have it in case we are pushed before our turn comes up

						$saucer2CardId = $this->getMoveCardIdFromSaucerDistanceType($saucer2Color, $saucer2Distance);

						// update the database so we know this card is chosen
						$this->setCardChosenState($saucer2CardId, 'chosen');

						// tell everyone this is chosen so the card back can be placed on this saucer mat
						$this->notifyPlayersOfCardSelection($playerConfirming, $saucer2Color);
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
							throw new BgaUserException( self::_("Please choose your distance and direction for your move before confirming.") );

						$isValid = false;
				}

				if( $this->getNumberOfPlayers() == 2 && (is_null($saucer2Color) || $saucer2Color == '' || is_null($saucer2Distance) || $saucer2Distance == '' || is_null($saucer2Direction) || $saucer2Direction == '') )
				{ // some argument is null for saucer 2
						if($throwErrors)
							throw new BgaUserException( self::_("Please choose your distance and direction for your move before confirming.") );

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
				//$this->updateTurnOrder(true); // send notification to update the turn order arrow

				$startPlayer = $this->getStartPlayer(); // get the player who owns the ostrich with the crown
				$this->gamestate->changeActivePlayer( $startPlayer ); // set the active player (this cannot be done in an activeplayer game state)

				$this->setState_PreMovement(); // set the player's phase based on what that player has available to them
		}

		function executeSaucerMove($saucerMoving)
		{
//throw new feException( "executeSaucerMove saucer moving: $saucerMoving");
				//self::debug( "executeSaucerMove saucerMoving:$saucerMoving" );

				// get list of move events in chronological order (saucers and where they end up, crewmembers picked up and by whom)
				$moveEventList = $this->getMovingEvents($saucerMoving);

				//$eventCount = count($moveEventList);
				//throw new feException( "event count: $eventCount");

				// notify players by sending a list of move events so they can play the animations one after another
				$reversedMoveEventList = array_reverse($moveEventList); // reverse it since it will act like a Stack on the javascript side
				self::notifyAllPlayers( "animateMovement", '', array(
					'moveEventList' => $reversedMoveEventList
				) );

				// tell the players what happened in the game log
				$this->updateGameLogForEvents($saucerMoving, $moveEventList);

				// see if any saucers fell off cliffs and notify everyone if they did
				$this->sendCliffFallsToPlayers();

				// calculate spaces left for use with Phase Shifter
				$distance = $this->getSaucerDistance($saucerMoving);
				$spacesMoved = $this->getSpacesMoved($saucerMoving);
				$spacesLeft = $distance - $spacesMoved;

				// figure out which type of move this is
				$moveType = $this->getMoveTypeWeAreExecuting();
				$saucerX = $this->getSaucerXLocation($saucerMoving);
				$saucerY = $this->getSaucerYLocation($saucerMoving);
//throw new feException( "moveType:$moveType saucerX:$saucerX saucerY:$saucerY saucerMoving:$saucerMoving");

				// see if we ended move sequence because we need to activate an upgrade
				$saucerWeCollideWith = $this->getSaucerAt($saucerX, $saucerY, $saucerMoving);
				if($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Phase Shifter") && $saucerWeCollideWith != "" && $spacesLeft > 0)
				{ // this saucer has phase shifter played and we are colliding with another saucer and we have at least 1 space left after colliding

						$this->gamestate->nextState( "askToPhaseShift" );
				}
				elseif($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Proximity Mines") && $saucerWeCollideWith != "")
				{ // this saucer has proximity mines played and we are colliding with another saucer

						$this->gamestate->nextState( "askToProximityMine" );
				}
				elseif($moveType == 'Landing Legs')
				{ // the moved because they had Landing Legs

						// reset the upgrade value_1 and value_2 for all saucers so we know movement upgrades have been used already
						$this->resetAllUpgradeValues();

						// decide the state to go to after the move
						$this->setState_AfterMovementEvents($saucerMoving, $moveType);
				}
				elseif($moveType == 'Blast Off Thrusters')
				{ // the moved because they had Blast Off Thrusters
						$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

						// reset the upgrade value_1 and value_2 for all saucers so we know movement upgrades have been used already
						$this->resetAllUpgradeValues();

						// decide the state to go to after the move
						$countOfStartOfTurnUpgrades = count($this->getAllStartOfTurnUpgradesToActivateForSaucer($saucerWhoseTurnItIs));
						//throw new feException( "countOfStartOfTurnUpgrades:$countOfStartOfTurnUpgrades");
						if($countOfStartOfTurnUpgrades > 0)
						{ // saucer has at least one start of turn upgrade active
		//throw new feException( "checkStartOfTurnUpgrades true");
								// ask which one they want to use or if they want to skip
								$this->gamestate->nextState( "askWhichStartOfTurnUpgradeToUse" );
						}
						else
						{ // no start of turn upgrades active

								$this->gamestate->nextState( "checkForRevealDecisions" );
						}
				}
				else
				{ // regular or end of turn upgrade

						// decide the state to go to after the move
						$this->setState_AfterMovementEvents($saucerMoving, $moveType);
				}


		}

		function getMoveTypeWeAreExecuting()
		{
				$saucerColor = $this->getOstrichWhoseTurnItIs();

				//$blast = $this->getUpgradeValue2($saucerColor, "Blast Off Thrusters");
				//throw new feException( "blast: $blast");

				$moveType = 'regular';
				if($this->getUpgradeValue2($saucerColor, "Blast Off Thrusters") != 0)
				{
						$moveType = 'Blast Off Thrusters';
				}
				elseif($this->getUpgradeValue2($saucerColor, "Landing Legs") != 0)
				{
						$moveType = 'Landing Legs';
				}
				elseif($this->getUpgradeValue2($saucerColor, "Afterburner") != 0)
				{
						$moveType = 'Afterburner';
				}
//throw new feException( "moveType: $moveType");
				return $moveType;
		}

		function updateGameLogForEvents($saucerMoving, $eventList)
		{
				$saucerMovingHighlightedText = $this->convertColorToHighlightedText($saucerMoving);

				$spaces = "spaces";
				$saucerMoveDistance = $this->getSaucerDistance($saucerMoving);
				if($saucerMoveDistance == 1)
				{ // we are only moving 1 spaces
						$spaces = "space";
				}

				self::notifyAllPlayers( "saucerMove", clienttranslate( '${saucerMovingHighlightedText} is moving ${distance} ${spaces}.' ), array(
					'saucerMovingHighlightedText' => $saucerMovingHighlightedText,
					'distance' => $saucerMoveDistance,
					'spaces' => $spaces
				) );

				foreach($eventList as $event)
				{
						$eventType = $event['event_type']; // saucerMove
						$saucerMoving = $event['saucer_moving'];

						$saucerMovingHighlightedText = $this->convertColorToHighlightedText($saucerMoving);

						//throw new feException( "event count: $eventCount");

						if($eventType == 'saucerMove')
						{
/* REPLACED SPECIFYING EVERY SPACE MOVED WITH JUST SAYING HOW FAR THEY ARE MOVING
								$destinationX = $event['destination_X'];
								$destinationY = $event['destination_Y'];

								self::notifyAllPlayers( "saucerMove", clienttranslate( '${saucerMovingHighlightedText} moves to ${xDestination}, ${yDestination}.' ), array(
									'saucerMovingHighlightedText' => $saucerMovingHighlightedText,
									'xDestination' => $destinationX,
									'yDestination' => $destinationY
								) );
*/
						}
						elseif($eventType == 'saucerCrashed')
						{ // the saucer moving went off the board

								self::notifyAllPlayers( "saucerCrashed", clienttranslate( '${saucerMovingHighlightedText} has crashed.' ), array(
									'saucerMovingHighlightedText' => $saucerMovingHighlightedText
								) );

						}
						elseif($eventType == 'movedOntoAccelerator')
						{ // the saucer moving walked onto an accelerator on their turn

								self::notifyAllPlayers( "movedOntoAccelerator", clienttranslate( '${saucerMovingHighlightedText} is taking an Accelerator.' ), array(
									'saucerMovingHighlightedText' => $saucerMovingHighlightedText
								) );

						}
						elseif($eventType == 'pushedOntoAccelerator')
						{ // the saucer moving was pushed onto an accelerator

								$spacesPushed = $event['spaces_pushed'];

								self::notifyAllPlayers( "pushedOntoAccelerator", clienttranslate( '${saucerMovingHighlightedText} was pushed onto an Accelerator and will go ${spacesPushed} spaces.' ), array(
									'saucerMovingHighlightedText' => $saucerMovingHighlightedText,
									'spacesPushed' => $spacesPushed
								) );

						}
						elseif($eventType == 'saucerPush')
						{ // the saucer moving pushed another saucer

								$saucerPushed = $event['saucer_pushed'];
								$saucerPushedHighlightedText = $this->convertColorToHighlightedText($saucerPushed);
								$spacesPushed = $event['spaces_pushed'];

								self::notifyAllPlayers( "saucerPushed", clienttranslate( '${saucerMovingHighlightedText} has pushed ${saucerPushedHighlightedText} ${spacesPushed} spaces.' ), array(
									'saucerMovingHighlightedText' => $saucerMovingHighlightedText,
									'saucerPushedHighlightedText' => $saucerPushedHighlightedText,
									'spacesPushed' => $spacesPushed
								) );

						}
						elseif($eventType == 'crewmemberPickup')
						{ // they picked up a crewmember
							$crewmemberColor = $event['crewmember_color'];
							$crewmemberType = $event['crewmember_type'];

							self::notifyAllPlayers( "crewmemberPickupAnimated", clienttranslate( '${saucerMovingHighlightedText} picks up ${CREWMEMBERIMAGE}.' ), array(
								'saucerMovingHighlightedText' => $saucerMovingHighlightedText,
								'CREWMEMBERIMAGE' => 'CREWMEMBERIMAGE_'.$crewmemberType.'_'.$crewmemberColor
							) );

						}
				}


		}

		function getMovingEvents( $saucerMoving )
		{
				$allEvents = array();
				//self::debug( "getMovingEvents saucerMoving:$saucerMoving" );

				$originalDistance = $this->getSaucerDistance($saucerMoving); //2, 3, 5, etc
				$spacesMoved = $this->getSpacesMoved($saucerMoving);

				$direction = $this->getSaucerDirection($saucerMoving); // meteor

				$wasPushed = $this->wasThisSaucerPushed($saucerMoving);
				if($wasPushed)
				{ // this saucer was pushed
						$pushedDistance = $this->getPushedDistance($saucerMoving);
						$originalDistance = $pushedDistance;

						$pushedDirection = $this->getPushedDirection($saucerMoving);
						$direction = $pushedDirection;
				}

				$distance = $originalDistance - $spacesMoved;

				$currentX = $this->getSaucerXLocation($saucerMoving); // 7
				$currentY = $this->getSaucerYLocation($saucerMoving); // 5

				$moveType = $this->getMoveTypeWeAreExecuting();
				//throw new feException( "moveType:$moveType");
				if($moveType == 'Landing Legs' || $moveType == 'Blast Off Thrusters')
				{ // we are moving from a start or end of turn upgrade
						$distance = $this->getUpgradeValue1($saucerMoving, $moveType);
						$direction = $this->getUpgradeValue2($saucerMoving, $moveType);

						//throw new feException( "distance:$distance direction:$direction");
				}

				if($distance == 0)
				{ // this saucer has exhausted all of its movement already so we don't need to check for more

						// reset pushed setting so we don't think a saucer was pushed when we do our next move
						$this->resetPushedForAllSaucers();

//throw new feException( "distance == 0");
						// return no events
						return $allEvents;
				}

//throw new feException( "moveType: $moveType distance: $distance direction: $direction");
				self::debug( "getMovingEvents distance:$distance direction: $direction currentX: $currentX currentY: $currentY spacesMoved: $spacesMoved" );

				// get all events until we run into something that stops us
				$allEvents = $this->getEventsWhileExecutingMove($currentX, $currentY, $distance, $direction, $saucerMoving, $wasPushed); // move a space at a time picking up crewmembers, colliding, etc.

				//$eventCount = count($allEvents);
				//throw new feException( "event count: $eventCount");

				return $allEvents;
		}

		function executeDirectionClick( $direction )
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs(); // you can only zag on your own turn

				$currentState = $this->getStateName();

				$this->saveSaucerMoveCardDirection($saucerWhoseTurnItIs, $direction); // save the direction

				if($currentState == "chooseIfYouWillUseBooster")
				{ // they are boosting

						$this->saveSaucerLastDirection($saucerWhoseTurnItIs, $direction); // update the database with its new last moved direction

						// set the number of spaces moved back to 0 since we're starting a new movement
						$this->setSpacesMoved($saucerWhoseTurnItIs, 0);
						//throw new feException( "booster saucerWhoseTurnItIs: $saucerWhoseTurnItIs");

						$this->notifyPlayersOfBoosterUsage($saucerWhoseTurnItIs);
						$this->decrementBoosterForSaucer($saucerWhoseTurnItIs); // must come after notification

						$this->gamestate->nextState( "executingMove" );
						//$this->executeSaucerMove($saucerWhoseTurnItIs);
				}
				elseif($currentState == "chooseAcceleratorDirection")
				{ // they are accelerating

						// set the number of spaces moved back to 0 since we're starting a new movement
						$this->setSpacesMoved($saucerWhoseTurnItIs, 0);

						$distanceType = $this->getSaucerDistanceType($saucerWhoseTurnItIs);
						$cardId = $this->getMoveCardIdFromSaucerDistanceType($saucerWhoseTurnItIs, $distanceType);
						$cardState = $this->getCardChosenState($cardId);

						$this->gamestate->nextState( "executingMove" );
						//$this->executeSaucerMove($saucerWhoseTurnItIs);
				}
				elseif($currentState == "chooseTimeMachineDirection")
				{ // they have chosen their time machine direction

						// set their direction
						$this->saveSaucerMoveCardDirection($saucerWhoseTurnItIs, $direction); // save the direction so we have it in case we are pushed before our turn comes up

						// specify that we have already set the direction this round so we don't ask again
						$this->activateUpgrade($saucerWhoseTurnItIs, "Time Machine");



						// notify the player so they can rotate the card on the UI
						$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
						$playerWhoseTurnItIs = $this->getOwnerIdOfOstrich($saucerWhoseTurnItIs);
						$saucerColorHighlighted = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);
						$distanceType = $this->getSaucerDistanceType($saucerWhoseTurnItIs);
						$distanceText = $this->convertDistanceTypeToString($distanceType);
						$cardId = $this->getMoveCardIdFromSaucerDistanceType($saucerWhoseTurnItIs, $distanceType, $direction);
						$revealed = $this->getCardChosenState($cardId);
						//self::notifyPlayer( $playerWhoseTurnItIs, 'moveCardChange', '', array(
						self::notifyAllPlayers( 'moveCardChange', clienttranslate( '${saucerColorHighlighted} has changed their Move Card to a ${newDistanceText} in the ${newDirection} direction.' ), array(
								'saucerColor' => $saucerWhoseTurnItIs,
								'saucerColorHighlighted' => $saucerColorHighlighted,
								'newDirection' => $direction,
								'newDistanceType' => $distanceType,
								'newDistanceText' => $distanceText,
								'revealed' => $revealed
						) );

						// see if we have any other reveal decisions to make
						$this->gamestate->nextState( "checkForRevealDecisions" );
				}
				else
				{	// they chose their direction after starting turn crashed
						//$this->saveSaucerLastDirection($saucerWhoseTurnItIs, $direction); // update the database with its new last moved direction

						//$distance = $this->getZigDistanceForOstrich($saucerWhoseTurnItIs); // get the distance on this saucer's move card
						//$this->saveSaucerLastDistance($saucerWhoseTurnItIs, $distance); // if this saucer collided, its distance/direction was set to that of the collider so we also need to reset the distance

						$this->gamestate->nextState( "saucerTurnStart" );
				}

		}

		function executeRespawnOstrich()
		{
				$this->respawnAnOstrich(); // respawn the next ostrich up for respawning

				$this->gamestate->nextState( "endSaucerTurnCleanUp" );
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
						$this->gamestate->nextState( "endSaucerTurnCleanUp" ); // set the state now that moving is complete
				}
		}

		function executeAskWhichGarmentToSteal()
		{
				$this->gamestate->nextState( "chooseGarmentToSteal" );
		}

		function executeSkipActivateStartOfTurnUpgrade()
		{
				self::checkAction( 'skipActivateUpgrade' ); // make sure we can take this action from this state
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				$this->setAskedToActivateForAllStartOfTurnSaucerUpgrades($saucerWhoseTurnItIs);

				$this->gamestate->nextState( "checkForRevealDecisions" );
		}

		// skip ALL end of turn upgrades
		function executeSkipActivateEndOfTurnUpgrade()
		{
				self::checkAction( 'skipActivateUpgrade' ); // make sure we can take this action from this state
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				$this->setAskedToActivateForAllEndOfTurnSaucerUpgrades($saucerWhoseTurnItIs);

				$this->gamestate->nextState( "endSaucerTurnCleanUp" );
		}

		// skip a single end of turn upgrade after you chose to activate it
		function executeSkipActivateSpecificEndOfTurnUpgrade($collectorNumber)
		{
				self::checkAction( 'skipActivateUpgrade' ); // make sure we can take this action from this state
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				$this->setAskedToActivateUpgradeWithCollectorNumber($saucerWhoseTurnItIs, $collectorNumber);

				$this->gamestate->nextState( "endSaucerTurnCleanUp" );
		}

		// skip a single start of turn upgrade after you chose to activate it
		function executeSkipActivateSpecificStartOfTurnUpgrade($collectorNumber)
		{
				self::checkAction( 'skipActivateUpgrade' ); // make sure we can take this action from this state
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				$this->setAskedToActivateUpgradeWithCollectorNumber($saucerWhoseTurnItIs, $collectorNumber);

				$this->gamestate->nextState( "endSaucerTurnCleanUp" );
		}

		function executeActivateUpgrade($collectorNumber)
		{
				self::checkAction( 'activateUpgrade' ); // make sure we can take this action from this state
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				// set this to activated
				$this->activateUpgradeWithCollectorNumber($saucerWhoseTurnItIs, $collectorNumber);

				if($collectorNumber == 1)
				{ // Blast Off Thrusters

						$this->gamestate->nextState( "chooseBlastOffThrusterSpace" );
				}
				elseif($collectorNumber == 2)
				{ // Wormhole Generator
						$this->gamestate->nextState( "chooseSaucerWormholeGenerator" );
				}
				elseif($collectorNumber == 3)
				{ // Afterburner

						$this->gamestate->nextState( "chooseAfterburnerSpace" );
				}
				elseif($collectorNumber == 5)
				{ // Tractor Beam

						$this->gamestate->nextState( "chooseTractorBeamCrewmember" );
				}
				elseif($collectorNumber == 6)
				{ // Saucer Teleporter
						$this->gamestate->nextState( "chooseCrashSiteSaucerTeleporter" );
				}
				elseif($collectorNumber == 7)
				{ // Cloaking Device
						// move them off the board and notify players
						$this->placeSaucerOnSpace($saucerWhoseTurnItIs, 0, 0);

						// say they're already been penalized for "crashing" so no one gets a reward for it
						$this->markCrashPenaltyRendered($saucerWhoseTurnItIs);

						$this->gamestate->nextState( "endSaucerTurnCleanUp" );
				}
				elseif($collectorNumber == 11)
				{ // Distress Signaler

						$this->gamestate->nextState( "chooseDistressSignalerTakeCrewmember" );
				}
				elseif($collectorNumber == 17)
				{ // Landing Legs

						$this->gamestate->nextState( "chooseLandingLegsSpace" );
				}
				else
				{
						// the collector number did not match
						throw new BgaUserException( self::_("This upgrade could not be activated.") );
				}
		}

		function executeClickedUpgradeCardInHand($databaseId, $color)
		{
				self::checkAction( 'chooseUpgrade' ); // make sure we can take this action from this state

				$collectorNumber = $this->getCollectorNumberFromDatabaseId($databaseId);
				$playerId = $this->getOwnerIdOfOstrich($color);
				$playerName = $this->getPlayerNameFromPlayerId($playerId);

				// mark it as played
				$this->setCardToPlayed($databaseId);

				// move card to saucer
				$this->upgradeCards->moveCard($databaseId, $color);

				// put unselected cards back in deck
				$this->upgradeCards->moveAllCardsInLocation('drawn', 'deck');

				// take away Energy for playing it
				$this->decrementEnergyForSaucer($color);
				$this->decrementEnergyForSaucer($color);

				$energyQuantity = $this->getEnergyCountForSaucer($color);

				// get some additional notification details
				$nameOfUpgrade = $this->getUpgradeTitleFromCollectorNumber($collectorNumber);
				$colorName = $this->convertColorToHighlightedText($color);

				// notify all players that is has been played
				self::notifyAllPlayers( 'upgradePlayed', clienttranslate( '${color_name} played the upgrade ${name_of_upgrade}.' ), array(
						'saucerColor' => $color,
						'collectorNumber' => $collectorNumber,
						'databaseId' => $databaseId,
						'playerId' => $playerId,
						'player_name' => $playerName,
						'name_of_upgrade' => $nameOfUpgrade,
						'color_name' => $colorName,
						'energyQuantity' => $energyQuantity
				) );

				if($collectorNumber == 15)
				{ // they just played Cargo Hold

						// they get a booster when playing it
						$this->giveSaucerBooster($color);
				}

				$this->gamestate->nextState( "endSaucerTurnCleanUp" );
		}

		function executeSelectXValue($xValue)
		{
				$ostrich = $this->getOstrichWhoseTurnItIs();
				$this->saveSaucerLastDistance($ostrich, $xValue);
				$this->saveOstrichXValue($ostrich, $xValue);

				$this->notifyPlayersOfXSelection($ostrich, $xValue);

				$this->gamestate->nextState( "checkForRevealDecisions" );
				//$this->setState_PreMovement(); // set the player's phase based on what that player has available to them
		}

		function executeDiscardTrap($trapCardId)
		{
					$this->discardTrapCard($trapCardId, true); // discard the card

					$this->gamestate->nextState( "endSaucerTurnCleanUp" ); // set the phase depending on whether there are any traps to discards or garments to replace
		}

		function notifyPlayersOfBoosterUsage($saucerColor)
		{
				$boosterQuantity = $this->getBoosterCountForSaucer($saucerColor); // 1, 2

				$colorFriendlyText = $this->convertColorToHighlightedText($saucerColor);

				self::notifyAllPlayers( 'zagUsed', clienttranslate( '${saucer_friendly_color} is boosting.' ), array(
						'ostrich' => $saucerColor,
						'boosterQuantity' => $boosterQuantity,
						'player_name' => self::getActivePlayerName(),
						'saucer_friendly_color' => $colorFriendlyText
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

			$highlightedSaucerColor = $this->convertColorToHighlightedText($ostrichUsing);


			self::notifyAllPlayers( 'xSelected', clienttranslate( '${saucer_color_highlighted} set their distance to ${xValue}.' ), array(
					'ostrich' => $ostrichUsing,
					'xValue' => $xValue,
					'player_name' => self::getActivePlayerName(),
					'saucer_color_highlighted' => $highlightedSaucerColor
			) );
		}

		// A garment has been selected for spawning.
		function executeReplaceGarmentChooseGarment($garmentTypeString, $garmentColor)
		{
				self::checkAction( 'chooseLostCrewmember', false ); // Check that this is player's turn and that it is a "possible action" at this game state (see states.inc.php) -- the false argument says don't check if we are the active player because we might be replacing a garment on another player's turn

				if($this->getGarmentLocation($this->convertGarmentTypeStringToInt($garmentTypeString), $garmentColor) != 'pile')
				{ // hey... this isn't in the garment pile
						throw new BgaUserException( self::_("You can only choose new garments to spawn from the garment pile.") );
				}
/*
				$currentPlayerId = $this->getCurrentPlayerId(); // the player who clicked on a garment during the choose garment phase
				$playerIdSpawningGarment = $this->getPlayerIdRespawningGarment(); // the player who gets to choose a new garment
				if($currentPlayerId != $playerIdSpawningGarment)
				{	// the player who clicked is not the same player who is up for replacing a garment
						throw new BgaUserException( self::_("Only the player who picked up the garment can choose a new one to place.") );
				}
*/

				$crewmemberId = $this->getGarmentIdFromType($garmentTypeString, $garmentColor);
				$foundUnoccupiedCrashSite = $this->randomlyPlaceCrewmember($crewmemberId);


				if($foundUnoccupiedCrashSite)
				{ // there is at least 1 crash site unoccupied

						// see which other end of saucer turn clean-up there is to do
						$this->gamestate->nextState( "endSaucerTurnCleanUp" );




						// set the garment chosen to location of chosen
						//$garmentTypeAsString = $this->convertGarmentTypeIntToString($garmentType);



/*
						//$garmentTypeString = $this->convertGarmentTypeIntToString($garmentType);
						// send notification that this garment was chosen
						self::notifyAllPlayers( "replacementGarmentChosen", clienttranslate( 'A garment is being replaced.' ), array(
								'garmentColor' => $garmentColor,
								'garmentType' => $garmentTypeString
						) );
*/
				}
				else
				{ // all crash sites are occupied

						// they get to
						$this->gamestate->nextState( "placeCrewmemberChooseSpace" );
				}
		}

		function executeReplaceGarmentChooseSpace($xLocation, $yLocation)
		{
				self::checkAction( 'chooseCrewmemberPlacingSpace', false ); // Check that this is player's turn and that it is a "possible action" at this game state (see states.inc.php) -- the false argument says don't check if we are the active player because we might be replacing a garment on another player's turn

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

						$this->gamestate->nextState( "endSaucerTurnCleanUp" ); // DETERMINE NEXT STATE
				}
				else
				{ // NOT a valid location
						throw new BgaUserException( self::_("That is not a valid space to place a garment.") );
				}
		}

		function executeChooseUpgradeSpace($xLocation, $yLocation)
		{
				self::checkAction( 'chooseUpgradeSpace', false ); // Check that this is player's turn and that it is a "possible action" at this game state (see states.inc.php) -- the false argument says don't check if we are the active player because we might be replacing a garment on another player's turn
				$formattedSpace = $xLocation.'_'.$yLocation;
				$saucerColor = $this->getOstrichWhoseTurnItIs();
				$direction = $this->getDirectionFromLocation($saucerColor, $xLocation, $yLocation);

				$cardId = 0;
				$upgradeName = "";
				$currentState = $this->getStateName();
				//throw new feException( "currentState: $currentState" );
				if($currentState == "chooseBlastOffThrusterSpace")
				{
						$cardId = $this->getUpgradeCardId($saucerColor, "Blast Off Thrusters");
						$upgradeName = $this->getUpgradeTitleFromCollectorNumber(1);

						//throw new feException( "setting cardId $cardId value1 to 1 and value 2 to $direction" );
						$this->setUpgradeValue1($cardId, 1);
						$this->setUpgradeValue2($cardId, $direction);

						$this->gamestate->nextState( "executingMove" );
						//$this->executeSaucerMove($saucerColor);
				}
				elseif($currentState == "chooseAfterburnerSpace")
				{
						$cardId = $this->getUpgradeCardId($saucerColor, "Afterburner");
						$upgradeName = $this->getUpgradeTitleFromCollectorNumber(3);

						// teleport saucer to new location
						$this->placeSaucerOnSpace($saucerColor, $xLocation, $yLocation);
				}
				elseif($currentState == "chooseLandingLegsSpace")
				{
						$cardId = $this->getUpgradeCardId($saucerColor, "Landing Legs");
						$upgradeName = $this->getUpgradeTitleFromCollectorNumber(17);

						//throw new feException( "setting cardId $cardId value1 to 1 and value 2 to $direction" );
						$this->setUpgradeValue1($cardId, 1);
						$this->setUpgradeValue2($cardId, $direction);

						$this->gamestate->nextState( "executingMove" );
						//$this->executeSaucerMove($saucerColor);
				}

				// notify the player so they can rotate the card on the UI
				$saucerColorHighlighted = $this->convertColorToHighlightedText($saucerColor);
				self::notifyAllPlayers( 'upgradeMove', clienttranslate( '${saucerColorHighlighted} used ${upgradeName} to move.' ), array(
						'upgradeName' => $upgradeName,
						'saucerColorHighlighted' => $saucerColorHighlighted
				) );

				if($currentState == "chooseAfterburnerSpace")
				{
						// since we're not moving traditionally, we need to specify the next state
						$this->setState_AfterMovementEvents($saucerColor, "Afterburner");
				}
				//throw new BgaUserException( self::_("That is not a valid space to place a Saucer.") );
		}

		function executeChooseAnySpaceForSaucer($xLocation, $yLocation)
		{
				self::checkAction( 'chooseSaucerSpace', false ); // Check that this is player's turn and that it is a "possible action" at this game state (see states.inc.php) -- the false argument says don't check if we are the active player because we might be replacing a garment on another player's turn
				$formattedSpace = $xLocation.'_'.$yLocation;
				$saucerColor = $this->getOstrichWhoseTurnItIs();

				$allValidSpaces = $this->getAllSpacesNotInCrewmemberRowColumn();
				foreach( $allValidSpaces as $space )
				{ // go through each player who needs to replace a garment
						//echo "playerID is " + $player['player_id'];



						if($space == $formattedSpace)
						{ // this space is valid

								// locate the saucer there and notify all players
								$this->placeSaucerOnSpace($saucerColor, $xLocation, $yLocation);

								$currentState = $this->getStateName();
								$saucerToPlace = "unknown";
								if($currentState == "allCrashSitesOccupiedChooseSpaceEndRound")
								{ // we are placing a crashed saucer at the end of a round
										$this->gamestate->nextState( "endRoundCleanUp" ); // back to end round clean-up to see if we need to place any others
								}
								elseif($currentState == "allCrashSitesOccupiedChooseSpacePreTurn")
								{ // we are placing a crashed saucer before a player's turn
										$this->gamestate->nextState( "chooseDirectionAfterPlacement" ); // let them choose direction
								}


								return true;
						}
				}

				throw new BgaUserException( self::_("That is not a valid space to place a Saucer.") );
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
		// PURPOSE: The player has a Booster and they just finished moving but they do not want to use their Booster.
		//          We need to figure out which state to put them in.
		function executeSkipBooster()
		{
			//throw new feException( "executeSkipBooster" );
				//$this->sendCliffFallsToPlayers(); // check if the ostrich moving fell off a cliff, and if so, tell players and update stats
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$this->setSkippedBoosting($saucerWhoseTurnItIs, 1);

				//$this->gamestate->nextState( "finalizeMove" ); // set the state now that moving is complete

				// figure out which type of move this is
				$moveType = $this->getMoveTypeWeAreExecuting();

				$this->setState_AfterMovementEvents($saucerWhoseTurnItIs, $moveType); // set to false because we know we're boosting (where it is false)
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


		function getRotationalStabilizerOwner()
		{
				// see if rotational stabalizer has been played
				$allSaucers = $this->getAllSaucers();
				foreach($allSaucers as $saucer)
				{
						$saucerColor = $saucer['ostrich_color'];
						$saucerOwner = $saucer['ostrich_owner'];

						if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Rotational Stabilizer'))
						{
								// get the value
								return $saucerOwner;
						}
				}

				// we didn't find a rotational stabilizer in play
				return '';
		}

		// This happens just before each player starts moving.
		function rollRotationDie()
		{
				// if this is not the first round, set the player with the probe to go first

				if($this->getNumberOfPlayers() > 2)
				{ // we don't care about turn order in a 2-player game

						// get the chosen turn order if someone has played rotational stabilizer
						$rotationalStabilizerOwner = $this->getRotationalStabilizerOwner();
						if($rotationalStabilizerOwner != '')
						{ // someone has Rotational Stabalizer

								// set the player who has rotational stabilizer to the active player
								$this->gamestate->changeActivePlayer( $rotationalStabilizerOwner ); // set the active player (this cannot be done in an activeplayer game state)

								// let that player choose the direction
								$this->gamestate->nextState( "askToRotationalStabilizer" );

								// TODO: MOVE TO CLICK EVENT WHEN SOMEONE CHOOSES
								// set it to that order
								//self::setGameStateValue( 'TURN_ORDER', $rotationalStabilizerValue ); // 0=CLOCKWISE, 1=COUNTER-CLOCKWISE, 2=UNKNOWN

						}
						else
						{ // usual case

								// roll the rotation die
								$turnOrderInt = rand(0,1);

								// set the turn order
								$this->updateTurnOrder($turnOrderInt); // 0=CLOCKWISE, 1=COUNTER-CLOCKWISE, 2=UNKNOWN
						}

				}

				// tell all players a new round has started where they will send a random move card back of their opponents on to their mat

		}

		function updateTurnOrder($turnOrderInt)
		{
				self::setGameStateValue( 'TURN_ORDER', $turnOrderInt ); // 0=CLOCKWISE, 1=COUNTER-CLOCKWISE, 2=UNKNOWN

				$turnOrderFriendly = $this->convertTurnOrderIntToText($turnOrderInt);

				// notify players of the direction (send clockwise/counter)
				self::notifyAllPlayers( 'updateTurnOrder', clienttranslate( 'The turn direction this round is ${turnOrderFriendly}.' ), array(
								'i18n' => array('turnOrderFriendly'),
								'turnOrderFriendly' => $turnOrderFriendly,
								'turnOrder' => $turnOrderInt
				) );

				$this->gamestate->nextState( "playerTurnStart" ); // start the PLAYER turn (not the SAUCER turn)
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

		function getUpgradeValue1($saucerColor, $upgradeNameOrCollectorNumber)
		{
				$cardId = $this->getUpgradeCardId($saucerColor, $upgradeNameOrCollectorNumber);

				if($cardId == 0 || $cardId == '' || is_null($cardId))
				{
						return 0;
				}

				return self::getUniqueValueFromDb("SELECT value_1 FROM `upgradeCards` WHERE card_id=$cardId LIMIT 1");
		}

		function getUpgradeValue2($saucerColor, $upgradeNameOrCollectorNumber)
		{
				$cardId = $this->getUpgradeCardId($saucerColor, $upgradeNameOrCollectorNumber);

				if($cardId == 0 || $cardId == '' || is_null($cardId))
				{
						return 0;
				}
//throw new feException( "cardId $cardId" );
				return self::getUniqueValueFromDb("SELECT value_2 FROM `upgradeCards` WHERE card_id=$cardId LIMIT 1");
		}

		function setUpgradeValue1($cardId, $newValue)
		{
				$sqlUpdate = "UPDATE upgradeCards SET ";
				$sqlUpdate .= "value_1='$newValue' WHERE ";
				$sqlUpdate .= "card_id=$cardId";

				self::DbQuery( $sqlUpdate );
		}

		function setUpgradeValue2($cardId, $newValue)
		{
				$sqlUpdate = "UPDATE upgradeCards SET ";
				$sqlUpdate .= "value_2='$newValue' WHERE ";
				$sqlUpdate .= "card_id=$cardId";

				self::DbQuery( $sqlUpdate );
		}

		function haveAllSaucersTakenTheirTurn()
		{
				$turnValueToCompare = -1;

				// go through all players
				// count turns taken for all their saucers
				// if any of a player's saucers have taken less turns than the other, return false
				// if any player has taken less turns than another players, return false

				$allPlayers = self::getObjectListFromDB( "SELECT player_id
																											 FROM player" );
				foreach( $allPlayers as $player )
				{ // go through each player
						//echo "playerID is " + $player['player_id'];
						$playerId = $player['player_id'];
						$turnsForPlayer = $this->getPlayerTurnsTaken($playerId);
						$thisPlayerFirstSaucerTurnsTaken = -1;

						$saucersForPlayer = $this->getSaucersForPlayer($playerId);
						foreach( $saucersForPlayer as $saucer )
						{ // go through each saucer
								$saucerColor = $saucer['ostrich_color'];
								$turnsThisSaucerHasTaken = $saucer['ostrich_turns_taken'];

								if($thisPlayerFirstSaucerTurnsTaken == -1)
								{ // we are looking at the player's first saucer

										// just save data
										$thisPlayerFirstSaucerTurnsTaken = $turnsThisSaucerHasTaken;
								}
								else
								{ // we are looking at this player's second saucer

										if($thisPlayerFirstSaucerTurnsTaken != $turnsThisSaucerHasTaken)
										{ // one of this player's saucers has taken more turns than the other

												// all saucers have NOT yet taken their turn
												return false;
										}
								}
						}


						if($turnValueToCompare == -1)
						{ // this is the first player we have looked at

								// just save turn data
								$turnValueToCompare = $turnsForPlayer;
						}
						elseif($turnsForPlayer != $turnValueToCompare)
						{ // this is NOT the first player we're looking at and they have taken a different number of turns than the first player we looked at

//throw new feException( "turns for player $turnsForPlayer and turnValueToCompare $turnValueToCompare" );
								// one player has taken fewer turns than another
								return false;
						}
				}

				// we have found no mistmatches between player or saucer turn counts
				return true;
		}

		// Called once a player's saucer has ended their turn, after clean-up.
		function endSaucerTurn()
		{
				$playerWhoseTurnItWas = self::getActivePlayerId(); // Current Player = player who played the current player action (the one who made the AJAX request). In general, only use this in multiplayer states. Active Player = player whose turn it is.
				$nameOfPlayerWhoseTurnItWas = $this->getPlayerNameFromPlayerId($playerWhoseTurnItWas);
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$highlightedSaucerColor = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);

//throw new feException( "incrementing stat for $playerWhoseTurnItWas" );

				$this->incrementSaucerTurnsTaken($saucerWhoseTurnItIs);
				self::incStat( 1, 'turns_number', $playerWhoseTurnItWas ); // increase end game player stat
				self::incStat( 1, 'turns_number' ); // increase end game table stat

				self::notifyAllPlayers( "endTurn", clienttranslate( '${saucer_color_highlighted} has ended their turn.' ), array(
								'player_name' => $nameOfPlayerWhoseTurnItWas,
								'saucer_color_highlighted' => $highlightedSaucerColor
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

				if($this->haveAllSaucersTakenTheirTurn())
				{ // round is over
						$this->gamestate->nextState( "endRoundCleanUp" );
				}
				else
				{ // someone still has to take their turn
						$this->gamestate->nextState( "playerTurnStart" );
				}
		}

		// This is the start of the turn for the PLAYER. They have not yet necessarily chosen which
		// of their saucers will take a turn.
		function playerTurnStart()
		{
				$playerWhoseTurnItIs = self::getActivePlayerId(); // Current Player = player who played the current player action (the one who made the AJAX request). In general, only use this in multiplayer states. Active Player = player whose turn it is.

				if($this->getSaucersPerPlayer() == 1)
				{ // players are only controlling a single saucer
//throw new feException( "1 saucer per player." );
						$this->gamestate->nextState( "saucerTurnStart" ); // their saucer can just go
				}
				else
				{ // players are controlling 2 saucers each

						if($this->isThisFirstTurnInRoundForPlayer($playerWhoseTurnItIs))
						{ // it is the player's first turn this round

								// they choose which of their two saucers will take the first turn this round
								$this->gamestate->nextState( "chooseWhichSaucerGoesFirst" );
						}
						else
						{ // one of their saucers has already taken a turn this round so now their other saucer will go

								$this->gamestate->nextState( "saucerTurnStart" ); // their saucer can just go
						}
				}
		}

		function convertUpgradeNameToCollectorNumber($upgradeName)
		{
				switch($upgradeName)
				{
						case "Blast Off Thrusters":
								return 1;

						case "Wormhole Generator":
								return 2;

						case "Afterburner":
								return 3;

						case "Tractor Beam":
								return 5;

						case "Saucer Teleporter":
								return 6;

						case "Cloaking Device":
								return 7;

						case "Hyperdrive":
								return 9;

						case "Scavenger Bot":
								return 10;

						case "Distress Signaler":
								return 11;

						case "Time Machine":
								return 12;

						case "Regeneration Gateway":
								return 13;

						case "Phase Shifter":
								return 14;

						case "Cargo Hold":
								return 15;

						case "Proximity Mines":
								return 16;

						case "Landing Legs":
								return 17;

						case "Rotational Stabilizer":
								return 19;

						case "Airlock":
								return 20;

						default:
								return 0;
				}
		}

		// Returns 1 if the saucer has this upgrade in play (but hasn't necessarily chosen to activate it this round).
		function doesSaucerHaveUpgradePlayed($saucerColor, $upgradeName)
		{

				$sql = "SELECT card_is_played FROM upgradeCards WHERE card_location='$saucerColor'";

				switch($upgradeName)
				{
						case "Blast Off Thrusters":
						case 1:
								$sql .= " AND card_type_arg=1";
								break;

						case "Wormhole Generator":
						case 2:
								$sql .= " AND card_type_arg=2";
								break;

						case "Afterburner":
						case 3:
								$sql .= " AND card_type_arg=3";
								break;

						case "Tractor Beam":
						case 5:
								$sql .= " AND card_type_arg=5";
								break;

						case "Saucer Teleporter":
						case 6:
								$sql .= " AND card_type_arg=6";
								break;

						case "Cloaking Device":
						case 7:
								$sql .= " AND card_type_arg=7";
								break;

					 	case "Hyperdrive":
						case 9:
								$sql .= " AND card_type_arg=9";
								break;

						case "Scavenger Bot":
						case 10:
								$sql .= " AND card_type_arg=10";
								break;

						case "Distress Signaler":
						case 11:
								$sql .= " AND card_type_arg=11";
								break;

						case "Time Machine":
						case 12:
								$sql .= " AND card_type_arg=12";
								break;

						case "Regeneration Gateway":
						case 13:
								$sql .= " AND card_type_arg=13";
								break;

						case "Phase Shifter":
						case 14:
								$sql .= " AND card_type_arg=14";
								break;

						case "Cargo Hold":
						case 15:
								$sql .= " AND card_type_arg=15";
								break;

						case "Proximity Mines":
						case 16:
								$sql .= " AND card_type_arg=16";
								break;

						case "Landing Legs":
						case 17:
								$sql .= " AND card_type_arg=17";
								break;

						case "Rotational Stabilizer":
						case 19:
								$sql .= " AND card_type_arg=19";
								break;

						case "Airlock":
						case 20:
								$sql .= " AND card_type_arg=20";
								break;
				}

				// add a limit of 1 mainly just during testing where the same saucer may have multiple copies of the same upgrade in hand
				$sql .= " LIMIT 1";

				$isPlayed = self::getUniqueValueFromDb($sql);

				if($isPlayed == 1)
				{
					//throw new feException( "true saucerColor:$saucerColor upgradeName:$upgradeName" );
						return true;
				}
				else
				{
					//throw new feException( "false saucerColor:$saucerColor upgradeName:$upgradeName" );
						return false;
				}
		}

		function getAskedToActivateUpgrade($saucerColor, $upgradeName)
		{
				$sql = "SELECT asked_to_activate_this_round FROM upgradeCards WHERE card_location='$saucerColor' && card_is_played=1";

				switch($upgradeName)
				{
						case "Blast Off Thrusters":
						case 1:
								$sql .= " AND card_type_arg=1";
								break;

						case "Wormhole Generator":
						case 2:
								$sql .= " AND card_type_arg=2";
								break;

						case "Afterburner":
						case 3:
								$sql .= " AND card_type_arg=3";
								break;

						case "Tractor Beam":
						case 5:
								$sql .= " AND card_type_arg=5";
								break;

						case "Saucer Teleporter":
						case 6:
								$sql .= " AND card_type_arg=6";
								break;

						case "Cloaking Device":
						case 7:
								$sql .= " AND card_type_arg=7";
								break;

						case "Hyperdrive":
						case 9:
								$sql .= " AND card_type_arg=9";
								break;

						case "Scavenger Bot":
						case 10:
								$sql .= " AND card_type_arg=10";
								break;

						case "Distress Signaler":
						case 11:
								$sql .= " AND card_type_arg=11";
								break;

						case "Time Machine":
						case 12:
								$sql .= " AND card_type_arg=12";
								break;

						case "Regeneration Gateway":
						case 13:
								$sql .= " AND card_type_arg=13";
								break;

						case "Phase Shifter":
						case 14:
								$sql .= " AND card_type_arg=14";
								break;

						case "Cargo Hold":
						case 15:
								$sql .= " AND card_type_arg=15";
								break;

						case "Proximity Mines":
						case 16:
								$sql .= " AND card_type_arg=16";
								break;

						case "Landing Legs":
						case 17:
								$sql .= " AND card_type_arg=17";
								break;

						case "Rotational Stabilizer":
						case 19:
								$sql .= " AND card_type_arg=19";
								break;

						case "Airlock":
						case 20:
								$sql .= " AND card_type_arg=20";
								break;
				}

				// add a limit of 1 mainly just during testing where the same saucer may have multiple copies of the same upgrade in hand
				$sql .= "  ORDER BY times_activated_this_round DESC LIMIT 1";

				$asked = self::getUniqueValueFromDb($sql);

				if($asked > 0)
				{
						return true;
				}
				else
				{
						return false;
				}
		}

		// Returns the number of times this saucer has actived this upgrade this round.
		function getUpgradeTimesActivatedThisRound($saucerColor, $upgradeName)
		{
				$sql = "SELECT times_activated_this_round FROM upgradeCards WHERE card_location='$saucerColor' && card_is_played=1";

				switch($upgradeName)
				{
						case "Blast Off Thrusters":
						case 1:
								$sql .= " AND card_type_arg=1";
								break;

						case "Wormhole Generator":
						case 2:
								$sql .= " AND card_type_arg=2";
								break;

						case "Afterburner":
						case 3:
								$sql .= " AND card_type_arg=3";
								break;

						case "Tractor Beam":
						case 5:
								$sql .= " AND card_type_arg=5";
								break;

						case "Saucer Teleporter":
						case 6:
								$sql .= " AND card_type_arg=6";
								break;

						case "Cloaking Device":
						case 7:
								$sql .= " AND card_type_arg=7";
								break;

						case "Hyperdrive":
						case 9:
								$sql .= " AND card_type_arg=9";
								break;

						case "Scavenger Bot":
						case 10:
								$sql .= " AND card_type_arg=10";
								break;

						case "Distress Signaler":
						case 11:
								$sql .= " AND card_type_arg=11";
								break;

						case "Time Machine":
						case 12:
								$sql .= " AND card_type_arg=12";
								break;

						case "Regeneration Gateway":
						case 13:
								$sql .= " AND card_type_arg=13";
								break;

						case "Phase Shifter":
						case 14:
								$sql .= " AND card_type_arg=14";
								break;

						case "Cargo Hold":
						case 15:
								$sql .= " AND card_type_arg=15";
								break;

						case "Proximity Mines":
						case 16:
								$sql .= " AND card_type_arg=16";
								break;

						case "Landing Legs":
						case 17:
								$sql .= " AND card_type_arg=17";
								break;

						case "Rotational Stabilizer":
						case 19:
								$sql .= " AND card_type_arg=19";
								break;

						case "Airlock":
						case 20:
								$sql .= " AND card_type_arg=20";
								break;
				}

				// add a limit of 1 mainly just during testing where the same saucer may have multiple copies of the same upgrade in hand
				$sql .= "  ORDER BY times_activated_this_round DESC LIMIT 1";

				return self::getUniqueValueFromDb($sql);
		}

		function getUpgradeCardId($saucerColor, $nameOrCollectorNumber)
		{
			$sql = "SELECT card_id FROM upgradeCards WHERE card_location='$saucerColor' && card_is_played=1";

			switch($nameOrCollectorNumber)
			{
						case "Blast Off Thrusters":
						case 1:
								$sql .= " AND card_type_arg=1";
								break;

						case "Wormhole Generator":
						case 2:
								$sql .= " AND card_type_arg=2";
								break;

						case "Afterburner":
						case 3:
								$sql .= " AND card_type_arg=3";
								break;

						case "Tractor Beam":
						case 5:
								$sql .= " AND card_type_arg=5";
								break;

						case "Saucer Teleporter":
						case 6:
								$sql .= " AND card_type_arg=6";
								break;

						case "Cloaking Device":
						case 7:
								$sql .= " AND card_type_arg=7";
								break;

						case "Hyperdrive":
						case 9:
								$sql .= " AND card_type_arg=9";
								break;

						case "Scavenger Bot":
						case 10:
								$sql .= " AND card_type_arg=10";
								break;

						case "Distress Signaler":
						case 11:
								$sql .= " AND card_type_arg=11";
								break;

						case "Time Machine":
						case 12:
								$sql .= " AND card_type_arg=12";
								break;

						case "Regeneration Gateway":
						case 13:
								$sql .= " AND card_type_arg=13";
								break;

						case "Phase Shifter":
						case 14:
								$sql .= " AND card_type_arg=14";
								break;

						case "Cargo Hold":
						case 15:
								$sql .= " AND card_type_arg=15";
								break;

						case "Proximity Mines":
						case 16:
								$sql .= " AND card_type_arg=16";
								break;

						case "Landing Legs":
						case 17:
								$sql .= " AND card_type_arg=17";
								break;

						case "Rotational Stabilizer":
						case 19:
								$sql .= " AND card_type_arg=19";
								break;

						case "Airlock":
						case 20:
								$sql .= " AND card_type_arg=20";
								break;
				}

				// add a limit of 1 mainly just during testing where the same saucer may have multiple copies of the same upgrade in hand
				$sql .= "  ORDER BY times_activated_this_round DESC LIMIT 1";

				return self::getUniqueValueFromDb($sql);
		}

		function getSkippedBoosting($saucerColor)
		{
				$sql = "SELECT skipped_boosting FROM ostrich WHERE ostrich_color='$saucerColor'";

				return self::getUniqueValueFromDb($sql);
		}

		// this is the start of the turn for the SAUCER which comes after the player has chosen which of their
		// saucers will take their first turn.
		function saucerTurnStart()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				if($this->isSaucerCrashed($saucerWhoseTurnItIs))
				{ // this saucer is crashed

						if($this->doesSaucerHaveUpgradePlayed($saucerWhoseTurnItIs, "Regeneration Gateway"))
						{ // Regeneration Gateway active for player

								$this->gamestate->nextState( "chooseCrashSiteRegenerationGateway" );
						}
						else
						{ // player does NOT have Regeneration Gateway active

								// randomly place Saucer
								$this->gamestate->nextState( "askPreTurnToPlaceCrashedSaucer" );
						}
				}
				else
				{ // this saucer is NOT crashed
						$this->gamestate->nextState( "beginTurn" );
				}
		}

		function countUnoccupiedCrashSites()
		{
				$numberOfUnoccupiedCrashSites = 0;

				// get all crash sites
				$allCrashSites = $this->getAllCrashSites();

				$locX = 15;
				$locY = 15;

				foreach( $allCrashSites as $crashSite )
				{ // go through each crash site
						$locX = $crashSite['board_x'];
						$locY = $crashSite['board_y'];

						$saucerHere = $this->getOstrichAt($locX, $locY); // see if a saucer is here
						$crewmemberHere = $this->getGarmentIdAt($locX, $locY); // see if a crewmember is here

						//throw new feException( "At X=".$locX." and Y=".$locY." we have saucerHere=".$saucerHere." and crewmemberHere=".$crewmemberHere);

						if($crewmemberHere != 0 || $saucerHere != "")
						{ // there already a saucer or crewmember here
								//throw new feException( "We are continuing because at X=".$locX." and Y=".$locY." we have saucerHere=".$saucerHere." and crewmemberHere=".$crewmemberHere);

								// go to next crash site
								continue;
						}
						else
						{ // this crash site is unoccupied

								$numberOfUnoccupiedCrashSites++; // add one to our count
						}
				}

				return $numberOfUnoccupiedCrashSites;
		}

		// Place the given crewmember in a random location.
		// Returns true if an unoccupied crash site was found, false otherwise.
		function randomlyPlaceCrewmember($crewmemberId)
		{
				// get all crash sites
				$allCrashSites = $this->getAllCrashSites();
				shuffle($allCrashSites); // randomize the order

				$locX = 15;
				$locY = 15;

				foreach( $allCrashSites as $crashSite )
				{ // go through each crash site
						$locX = $crashSite['board_x'];
						$locY = $crashSite['board_y'];

						$saucerHere = $this->getOstrichAt($locX, $locY); // see if a saucer is here
						$crewmemberHere = $this->getGarmentIdAt($locX, $locY); // see if a crewmember is here

						//throw new feException( "At X=".$locX." and Y=".$locY." we have saucerHere=".$saucerHere." and crewmemberHere=".$crewmemberHere);

						if($crewmemberHere != 0 || $saucerHere != "")
						{ // there already a saucer or crewmember here
								//throw new feException( "We are continuing because at X=".$locX." and Y=".$locY." we have saucerHere=".$saucerHere." and crewmemberHere=".$crewmemberHere);

								// go to next crash site
								continue;
						}
						else
						{ // this crash site is unoccupied

								//throw new feException( "We found a good spot at X=".$locX." and Y=".$locY." we have saucerHere=".$saucerHere." and crewmemberHere=".$crewmemberHere);

								// put the saucer on this crash site
								$this->placeCrewmemberOnSpace($crewmemberId, $locX, $locY);

								return true; // we found an unoccupied crash site
						}
				}

				return false; // we could not find an unoccupied crash sites
		}

		// Place the given saucer in a random location.
		// Returns true if an unoccupied crash site was found, false otherwise.
		function randomlyPlaceSaucer($saucerColor)
		{
				// get all crash sites
				$allCrashSites = $this->getAllCrashSites();
				shuffle($allCrashSites); // randomize the order

				$locX = 15;
				$locY = 15;

				foreach( $allCrashSites as $crashSite )
				{ // go through each crash site
						$locX = $crashSite['board_x'];
						$locY = $crashSite['board_y'];

						$saucerHere = $this->getOstrichAt($locX, $locY); // see if a saucer is here
						$crewmemberHere = $this->getGarmentIdAt($locX, $locY); // see if a crewmember is here

						//throw new feException( "At X=".$locX." and Y=".$locY." we have saucerHere=".$saucerHere." and crewmemberHere=".$crewmemberHere);

						if($crewmemberHere != 0 || $saucerHere != "")
						{ // there already a saucer or crewmember here
								//throw new feException( "We are continuing because at X=".$locX." and Y=".$locY." we have saucerHere=".$saucerHere." and crewmemberHere=".$crewmemberHere);

								// go to next crash site
								continue;
						}
						else
						{ // this crash site is unoccupied

								//throw new feException( "We found a good spot at X=".$locX." and Y=".$locY." we have saucerHere=".$saucerHere." and crewmemberHere=".$crewmemberHere);

								// put the saucer on this crash site
								$this->placeSaucerOnSpace($saucerColor, $locX, $locY);

								return true; // we found an unoccupied crash site
						}
				}

				return false; // we could not find an unoccupied crash sites
		}

		function beginTurn()
		{
				// save the current state of the database before the move
				$this->undoSavePoint();
		}

		function checkStartOfTurnUpgrades()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$countOfStartOfTurnUpgrades = count($this->getAllStartOfTurnUpgradesToActivateForSaucer($saucerWhoseTurnItIs));
				if($countOfStartOfTurnUpgrades > 0)
				{ // saucer has at least one start of turn upgrade active
//throw new feException( "checkStartOfTurnUpgrades true");
						// ask which one they want to use or if they want to skip
						$this->gamestate->nextState( "askWhichStartOfTurnUpgradeToUse" );
				}
				else
				{ // no start of turn upgrades active

						$this->gamestate->nextState( "checkForRevealDecisions" );
				}
		}

		function checkForRevealDecisions()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$distanceType = $this->getSaucerDistanceType($saucerWhoseTurnItIs); // 0=X, 1=2, 2=3
				$direction = $this->getSaucerDirection($saucerWhoseTurnItIs); // sun
				$saucerColorFriendly = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);

				$distanceString = $this->convertDistanceTypeToString($distanceType);

				self::notifyAllPlayers( "cardRevealed", clienttranslate( '${saucer_color_highlighted} revealed a ${distance_string} in the ${direction} direction.' ), array(
						'saucer_color' => $saucerWhoseTurnItIs,
						'distance_type' => $distanceType,
						'distance_string' => $distanceString,
						'direction' => $direction,
						'saucer_color_highlighted' => $saucerColorFriendly
				) );

				if($distanceType == 0 && !$this->hasSaucerChosenX($saucerWhoseTurnItIs))
				{ // saucer played an X and has not yet chosen its value

						// ask them to choose distance 0-5
						$this->gamestate->nextState( "chooseDistanceDuringMoveReveal" );
				}
				else
				{ // they did NOT play an X or has already chosen its value

						if($this->doesSaucerHaveUpgradePlayed($saucerWhoseTurnItIs, "Time Machine") &&
						$this->getUpgradeTimesActivatedThisRound($saucerWhoseTurnItIs, "Time Machine") < 1)
						{ // saucer has Time Machine active and has not yet chosen its value

									$this->gamestate->nextState( "chooseTimeMachineDirection" );
						}
						elseif($this->doesSaucerHaveUpgradePlayed($saucerWhoseTurnItIs, "Hyperdrive") &&
									 $this->getAskedToActivateUpgrade($saucerWhoseTurnItIs, "Hyperdrive") == false)
						{ // saucer has Hyperdrive active and has not yet chosen its value

									$this->gamestate->nextState( "chooseWhetherToHyperdrive" );
						}
						else
						{ // saucer does NOT have a reveal upgrade active or they have already chosen whether to activate it

									if($distanceType == 1)
									{ // played a 2

												if($this->hasAvailableBoosterSlot($saucerWhoseTurnItIs))
												{ // has an available booster slot

														// give a booster
														$this->giveSaucerBooster($saucerWhoseTurnItIs);
												}
									}
									elseif($distanceType == 2)
									{ // played a 3

												// give an energy
												$this->giveSaucerEnergy($saucerWhoseTurnItIs);
									}

									//throw new feException( "executeStartMove with saucer: $saucerWhoseTurnItIs");

									// move the selected distance in the selected direction
									//$ownerOfSaucerWhoseTurnItIs = $this->getOwnerIdOfOstrich($saucerWhoseTurnItIs);

									//$nextPlayer = $this->getPlayerWhoseTurnIsNext(); // figure out which player goes next
									//$this->gamestate->changeActivePlayer( $nextPlayer ); // set the active player (this cannot be done in an activeplayer game state)
									$this->gamestate->nextState( "executingMove" );
									//$this->executeSaucerMove($saucerWhoseTurnItIs);
						}
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
		// PURPOSE: Do anything we need to do at the end of a round and then go to the PLAN phase, such as:
		//    1. Place any saucers that crashed. (starting with Probe player and going clockwise)
		//    2. Move the Probe.
		function endRoundCleanup()
		{
				// starting with Probe player and going clockwise, check each Saucer to see if one crashed
				$crashedSaucer = $this->getSaucerThatCrashed();
				if($crashedSaucer != '')
				{ // we found a saucer that is crashed
					//throw new feException( "Crashed saucer: ".$crashedSaucer);
						$ownerOfSaucer = $this->getOwnerIdOfOstrich($crashedSaucer);

						//throw new feException( "Activating player ".$ownerOfSaucer.".");

						// make the saucer owner active and ask them to click a button to place it
						$this->gamestate->changeActivePlayer( $ownerOfSaucer );
						$this->gamestate->nextState( "endRoundPlaceCrashedSaucer" );
				}
				else
				{ // no saucers are crashed

						//throw new feException( "CURRENT ROUND IS ".$this->getGameStateValue("CURRENT_ROUND")." and we are increasing it by 1.");
						$this->setGameStateValue("CURRENT_ROUND", $this->getGameStateValue("CURRENT_ROUND")+1); // increment the round by 1

						// move the Probe
						$this->moveTheProbe();

						$this->gamestate->setAllPlayersMultiactive(); // set all players to active
				  	$this->gamestate->nextState( "newRound" ); // use the newRound transition to go to the plan phase

				}

				// erase all choices players made for their X value
				$this->resetXValueChoices();

				// mark all crash penalties to 0
				$this->resetCrashPenalties();

				// reset value for who murdered a saucer
				$this->resetAllCliffPushers();

				// reset crewmember properties
				$this->resetCrewmembers();

				// make all turn-related saucer values 0
				$this->resetSaucers();

				// set all card to the unchosen state
				$this->resetAllCardChosenState();

				// mark all upgrades as not having been activated yet in the round
				$this->resetAllUpgradesActivatedThisRound();

				// reset all choices for who goes first
				$this->resetOstrichChosen(); // mark all ostriches as not yet chosen

/*
				$this->gamestate->setAllPlayersMultiactive(); // set all players to active

				$this->resetTrapsDrawnThisRound();
				$this->resetDizziness(); // make all ostriches not dizzy
				$this->resetOstrichChosen(); // mark all ostriches as not yet chosen
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
*/

		}

		function moveTheProbe()
		{
				// see which players have the least total crewmembers
				$playersDictionary = array();

				$allPlayers = $this->getAllPlayers();
				foreach( $allPlayers as $player )
				{
						$playerId = $player['player_id'];
						$playersDictionary[$playerId] = array();
						$playersDictionary[$playerId]['crewmemberCount'] = 0;
						$playersDictionary[$playerId]['playerId'] = $playerId;
						$playersDictionary[$playerId]['saucerColor'] = 'unknown';

						$allPlayersSaucers = $this->getSaucersForPlayer($playerId);
						foreach( $allPlayersSaucers as $saucer )
						{ // go through each saucer owned by this player

								$saucerColor = $saucer['ostrich_color'];
								$totalCrewmembersOfSaucer = $this->getTotalCrewmembersForSaucer($saucerColor);
								$playersDictionary[$playerId]['saucerColor'] = $saucerColor;

								$playersDictionary[$playerId]['crewmemberCount'] += $totalCrewmembersOfSaucer;
						}
				}

				// figure out what the lowest total crewmember count is
				$lowestCrewmemberCount = 100;
				foreach( $playersDictionary as $playerCounts )
				{
						if($playerCounts['crewmemberCount'] < $lowestCrewmemberCount)
						{
								$lowestCrewmemberCount = $playerCounts['crewmemberCount'];
						}
				}

				// gather all the players with the lowest total crewmembers
				$playersWithLeastCrewmembers = array();
				foreach( $playersDictionary as $playerCounts )
				{
						if($playerCounts['crewmemberCount'] == $lowestCrewmemberCount)
						{
								array_push($playersWithLeastCrewmembers, $playerCounts);
						}
				}

				if(count($playersWithLeastCrewmembers) == 1)
				{ // if they are the only one

						foreach( $playersWithLeastCrewmembers as $playerCounts )
						{
								$playerId = $playerCounts['playerId'];
								$saucerColor = $this->getFirstSaucerForPlayer($playerId);

								// they get the probe
								$this->giveProbe($saucerColor);

								// make them go first in turn order
								$this->gamestate->changeActivePlayer( $playerId );

								return; // we don't need to go any further
						}
				}
				else
				{ // more than one saucer is tied for least total crewmembers

							// give the probe to the player who went most recently
							$probeSaucerPlayerId = $this->getStartPlayer(); // get the owner of the saucer with the probe
							//echo "resetTurnOrder clockwise $clockwise with startingOstirchPlayerId $startingOstrichPlayerId <br>";

							$clockwiseAsInt = $this->getGameStateValue("TURN_ORDER"); // 0=CLOCKWISE, 1=COUNTER-CLOCKWISE, 2=UNKNOWN

							$currentPlayer = $probeSaucerPlayerId; // this will get updated each loop iteration
							for($i = 2; $i <= $this->getNumberOfPlayers(); $i++)
							{ // loop through one less than the number of players since we already know who's going first

									// go back one in turn order from the current player
									if($clockwiseAsInt == 0)
									{ // CLOCKWISE
											$currentPlayer = $this->getPlayerBefore( $currentPlayer ); // go one back in natural turn order
									}
									else
									{ // COUNTER-CLOCKWISE
											$currentPlayer = $this->getPlayerAfter( $currentPlayer ); // go one back in natural turn order
									}

									foreach($playersWithLeastCrewmembers as $playerDetails)
									{
											$playerId = $playerDetails['playerId'];
											$saucerColor = $playerDetails['saucerColor'];
											if($playerId == $currentPlayer)
											{ // this player is tied for the lowest total crewmembers

													// we already know they were the most recent to go so they get the probe
													$this->giveProbe($saucerColor);

													// make them go first in turn order
													$this->gamestate->changeActivePlayer( $playerId );

													return; // we don't need to go any further
											}
									}
							}
				}
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

		function argGetSaucerColor()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerColorFriendly = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);

				// return the saucer color so it can be used in the description
				return array(
						'saucerColor' => $saucerColorFriendly
				);
		}

		// Get 2 things:
		// 1. The color of the Saucer we are placing to show in the description box in case the player has 2 Saucers.
		// 2. The coordinates of all spaces that are valid for placing so we can highlight them on the front end.
		function argGetAllCrashSitesOccupiedDetails()
		{
				$currentState = $this->getStateName();
				$saucerToPlace = "unknown";
				if($currentState == "endRoundPlaceCrashedSaucer")
				{ // we are placing a crashed saucer at the end of a round
						$saucerToPlace = $this->getSaucerThatCrashed();
				}
				elseif($currentState == "askPreTurnToPlaceCrashedSaucer")
				{ // we are placing a crashed saucer before a player's turn
						$saucerToPlace = $this->getOstrichWhoseTurnItIs();
				}

				$saucerColorFriendly = $this->convertColorToText($saucerToPlace);

				// return both the location of all the
				return array(
						'saucerColor' => $saucerColorFriendly,
						'validPlacements' => self::getAllSpacesNotInCrewmemberRowColumn()
				);
		}

		function argGetLandingLegSpaces()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerWhoseTurnItIsColorFriendly = $this->convertColorToText($saucerWhoseTurnItIs);
				$upgradeName = $this->getUpgradeTitleFromCollectorNumber(17);


				$validSpaces = $this->getValidSpacesForUpgrade($saucerWhoseTurnItIs, "Landing Legs");

				// return both the location of all the
				return array(
						'saucerColor' => $saucerWhoseTurnItIsColorFriendly,
						'upgradeName' => $upgradeName,
						'validSpaces' => $validSpaces
				);
		}

		function argGetAfterburnerSpaces()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerWhoseTurnItIsColorFriendly = $this->convertColorToText($saucerWhoseTurnItIs);
				$upgradeName = $this->getUpgradeTitleFromCollectorNumber(3);


				$validSpaces = $this->getValidSpacesForUpgrade($saucerWhoseTurnItIs, "Afterburner");

				// return both the location of all the
				return array(
						'saucerColor' => $saucerWhoseTurnItIsColorFriendly,
						'upgradeName' => $upgradeName,
						'validSpaces' => $validSpaces
				);
		}

		function argGetBlastOffThrustersSpaces()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerWhoseTurnItIsColorFriendly = $this->convertColorToText($saucerWhoseTurnItIs);
				$upgradeName = $this->getUpgradeTitleFromCollectorNumber(1);


				$validSpaces = $this->getValidSpacesForUpgrade($saucerWhoseTurnItIs, "Blast Off Thrusters");

				// return both the location of all the
				return array(
						'saucerColor' => $saucerWhoseTurnItIsColorFriendly,
						'upgradeName' => $upgradeName,
						'validSpaces' => $validSpaces
				);
		}

		function argGetAllUnoccupiedCrashSites()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerWhoseTurnItIsColorFriendly = $this->convertColorToText($saucerWhoseTurnItIs);

				// return both the location of all the
				return array(
						'saucerColor' => $saucerWhoseTurnItIsColorFriendly,
						'emptyCrashSites' => self::getAllUnoccupiedCrashSites()
				);
		}

		function argGetEndOfTurnUpgradesToActivate()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				return array(
						'endOfTurnUpgradesToActivate' => self::getEndOfTurnUpgradesToActivateForSaucer($saucerWhoseTurnItIs)
				);
		}

		function argGetStartOfTurnUpgradesToActivate()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				return array(
						'startOfTurnUpgradesToActivate' => self::getStartOfTurnUpgradesToActivateForSaucer($saucerWhoseTurnItIs)
				);
		}

		function argGetTractorBeamCrewmembers()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerWhoseTurnItIsColorFriendly = $this->convertColorToText($saucerWhoseTurnItIs);
				$upgradeName = $this->getUpgradeTitleFromCollectorNumber(5);

				$validCrewmembers = $this->getCrewmembersWithin2($saucerWhoseTurnItIs);

				// return both the location of all the
				return array(
						'saucerColor' => $saucerWhoseTurnItIsColorFriendly,
						'upgradeName' => $upgradeName,
						'validCrewmembers' => $validCrewmembers
				);
		}

		function argGetDistressSignalerTakeCrewmembers()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerWhoseTurnItIsColorFriendly = $this->convertColorToText($saucerWhoseTurnItIs);
				$upgradeName = $this->getUpgradeTitleFromCollectorNumber(11);

				$validCrewmembers = $this->getDistressSignalableTakeCrewmembers($saucerWhoseTurnItIs);

				// return both the location of all the
				return array(
						'saucerColor' => $saucerWhoseTurnItIsColorFriendly,
						'upgradeName' => $upgradeName,
						'validCrewmembers' => $validCrewmembers
				);
		}

		function argGetDistressSignalerGiveCrewmembers()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerWhoseTurnItIsColorFriendly = $this->convertColorToText($saucerWhoseTurnItIs);
				$upgradeName = $this->getUpgradeTitleFromCollectorNumber(11);

				$validCrewmembers = $this->getDistressSignalableGiveCrewmembers($saucerWhoseTurnItIs);

				$crewmemberTakenColor = "UNKNOWN";
				$crewmemberTakenColorFriendly = "UNKNOWN";
				$crewmemberTypeId = 0;
				$crewmemberTakenTypeString = "UNKNOWN";
				$crewmemberId = $this->getCrewmemberIdTakenWithDistress();
				if($crewmemberId != '')
				{
						$crewmemberTakenColor = $this->getCrewmemberColorFromId($crewmemberId);
						$crewmemberTakenColorFriendly = $this->convertColorToHighlightedText($crewmemberTakenColor);
						$crewmemberTypeId = $this->getCrewmemberTypeIdFromId($crewmemberId);
						$crewmemberTakenTypeString = $this->convertGarmentTypeIntToString($crewmemberTypeId);
				}

				// return both the location of all the
				return array(
						'saucerColor' => $saucerWhoseTurnItIsColorFriendly,
						'upgradeName' => $upgradeName,
						'crewmemberTakenColor' => $crewmemberTakenColorFriendly,
						'crewmemberTakenTypeString' => $crewmemberTakenTypeString,
						'validCrewmembers' => $validCrewmembers
				);
		}

		function argAskToProximityMine()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerWhoseTurnItIsColorFriendly = $this->convertColorToText($saucerWhoseTurnItIs);
				//$saucerToCrash = $this->nextPendingCrashReward($saucerWhoseTurnItIs);

				$saucerX = $this->getSaucerXLocation($saucerWhoseTurnItIs);
				$saucerY = $this->getSaucerYLocation($saucerWhoseTurnItIs);

				$saucerToCrash = $this->getSaucerAt($saucerX, $saucerY, $saucerWhoseTurnItIs);

//throw new feException( "saucerWhoseTurnItIs: $saucerWhoseTurnItIs saucerToCrash: $saucerToCrash" );
				return array(
						'saucerColor' => $saucerWhoseTurnItIsColorFriendly,
						'saucerToCrash' => $saucerToCrash
				);
		}

		function argAskToRotationalStabilizer()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerWhoseTurnItIsColorFriendly = $this->convertColorToText($saucerWhoseTurnItIs);
				//$saucerToCrash = $this->nextPendingCrashReward($saucerWhoseTurnItIs);

//throw new feException( "saucerWhoseTurnItIs: $saucerWhoseTurnItIs saucerToCrash: $saucerToCrash" );
				return array(
						'saucerColor' => $saucerWhoseTurnItIsColorFriendly
				);
		}

		function argGetAirlockCrewmembers()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerWhoseTurnItIsColorFriendly = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);
				$upgradeName = $this->getUpgradeTitleFromCollectorNumber(20);

				$validCrewmembers = $this->getCrewmembersOnBoard();

				// get the ID of the highest crewmember available for exchange (in case they picked up 2 on the same turn)
				$crewmemberId = $this->getHighestAirlockExchangeableId();

				$crewmemberTakenColorHex = $this->getCrewmemberColorFromId($crewmemberId);
				$crewmemberTakenColorString = $this->convertColorToHighlightedText($crewmemberTakenColorHex);
				$crewmemberTakenTypeId = $this->getCrewmemberTypeIdFromId($crewmemberId);
				$crewmemberTakenTypeString = $this->convertGarmentTypeIntToString($crewmemberTakenTypeId);

				// return both the location of all the
				return array(
						'saucerColor' => $saucerWhoseTurnItIsColorFriendly,
						'upgradeName' => $upgradeName,
						'validCrewmembers' => $validCrewmembers,
						'crewmemberTakenColor' => $crewmemberTakenColorString,
						'crewmemberTakenTypeString' => $crewmemberTakenTypeString
				);
		}

		function argGetStealableCrewmembers()
		{
				$saucerStealing = $this->getOstrichWhoseTurnItIs();
				$crashedSaucer = $this->nextPendingCrashReward($saucerStealing);
				$crashedSaucerText = $this->convertColorToHighlightedText($crashedSaucer);
				$saucerStealingText = $this->convertColorToHighlightedText($saucerStealing);
				// return both the location of all the
				return array(
						'saucerWhoCrashed' => $crashedSaucer,
						'saucerWhoCrashedText' => $crashedSaucerText,
						'saucerWhoIsStealingText' => $saucerStealingText,
						'stealableCrewmembers' => self::getStealableCrewmembersFromSaucer($crashedSaucer)
				);
		}

		function argGetPassableCrewmembers()
		{
				$saucerColorGiving = $this->getOstrichWhoseTurnItIs();
				$saucerColorReceiving = $this->getPlayersOtherSaucer($saucerColorGiving);
				$saucerGivingText = $this->convertColorToHighlightedText($saucerColorGiving);
				$saucerReceivingText = $this->convertColorToHighlightedText($saucerColorReceiving);

//$count = count($this->getPassableCrewmembersFromSaucer($saucerColorGiving));
//throw new feException( "count passable: ".$count );

				// return both the location of all the
				return array(
						'saucerColorGiving' => $saucerGivingText,
						'saucerColorReceiving' => $saucerReceivingText,
						'passableCrewmembers' => self::getPassableCrewmembersFromSaucer($saucerColorGiving)
				);
		}

		function argGetTakeableCrewmembers()
		{
				$saucerColorReceiving = $this->getOstrichWhoseTurnItIs();
				$saucerColorGiving = $this->getPlayersOtherSaucer($saucerColorReceiving);
				$saucerGivingText = $this->convertColorToHighlightedText($saucerColorGiving);
				$saucerReceivingText = $this->convertColorToHighlightedText($saucerColorReceiving);

//$count = count($this->getPassableCrewmembersFromSaucer($saucerColorGiving));
//throw new feException( "count takeable: ".$count );

				// return both the location of all the
				return array(
						'saucerColorGiving' => $saucerGivingText,
						'saucerColorReceiving' => $saucerReceivingText,
						'takeableCrewmembers' => self::getPassableCrewmembersFromSaucer($saucerColorGiving)
				);
		}

		function argGetUpgradesToPlay()
		{
				$saucerColor = $this->getOstrichWhoseTurnItIs();
				$saucerColorText = $this->convertColorToHighlightedText($saucerColor);

				// return both the location of all the
				return array(
						'saucerColor' => $saucerColorText,
						'upgradeList' => self::getUpgradesToPlay()
				);
		}

		function argGetGiveAwayCrewmembers()
		{
				$saucerGivingAway = $this->getOstrichWhoseTurnItIs();
				$saucerGivingAwayText = $this->convertColorToText($saucerGivingAway);

				// get a list of all the saucers other than the one who is giving away
				$otherSaucers = $this->getAllOtherPlayerSaucersHex($saucerGivingAway);

				// return both the location of all the
				return array(
						'saucerWhoCrashed' => $saucerGivingAway,
						'saucerWhoCrashedText' => $saucerGivingAwayText,
						'otherSaucers' => $otherSaucers,
						'giveAwayCrewmembers' => self::getCrewmembersSaucerCanGiveAway($saucerGivingAway)
				);
		}

		function argGetLostCrewmembers()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerHighlightedText = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);
				return array(
						'saucerHighlighted' => $saucerHighlightedText,
						'lostCrewmembers' => self::getLostCrewmembers()
				);
		}

		function argGetAllPlayerSaucerMoves()
		{
				return array(
						'playerSaucerMoves' => self::getAllPlayerSaucerMoves()
				);
		}

		function argGetOtherUncrashedSaucers()
		{
				return array(
						'otherUncrashedSaucers' => self::getOtherUncrashedSaucers()
				);
		}

		function argGetAllXMoves()
		{
				$saucerColor = $this->getOstrichWhoseTurnItIs();
				$direction = $this->getSaucerDirection($saucerColor);
				$startingXLocation = $this->getSaucerXLocation($saucerColor);
				$startingYLocation = $this->getSaucerYLocation($saucerColor);
				return array(
						'playerSaucerMoves' => self::getSaucerAcceleratorAndBoosterMoves(),
						'direction' => $direction,
						'startingXLocation' => $startingXLocation,
						'startingYLocation' => $startingYLocation
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

		function argGetSaucerToPlaceButton()
		{
			return array(
					'saucerButton' => self::getSaucerToPlaceButton()
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

		function incrementSpacesMoved($saucerColor)
		{
				$sql = "UPDATE ostrich SET spaces_moved=spaces_moved+1 WHERE ";
				$sql .= "ostrich_color='$saucerColor'";
				self::DbQuery( $sql );
		}

		function setSpacesMoved($saucerColor, $newValue)
		{
				$sql = "UPDATE ostrich SET spaces_moved=$newValue WHERE ";
				$sql .= "ostrich_color='$saucerColor'";
				self::DbQuery( $sql );
		}

		function getSpacesMoved($saucerColor)
		{
				return self::getUniqueValueFromDb("SELECT spaces_moved FROM ostrich WHERE ostrich_color='$saucerColor'");
		}

		function resetPushedForAllSaucers()
		{
				$sql = "UPDATE ostrich SET pushed_on_saucer_turn='',pushed_distance=0,pushed_direction='0'";
				self::DbQuery( $sql );
		}

		function setPushedOnSaucerTurn($saucerColor, $newValue)
		{
				$sql = "UPDATE ostrich SET pushed_on_saucer_turn='$newValue' WHERE ";
				$sql .= "ostrich_color='$saucerColor'";
				self::DbQuery( $sql );
		}

		function wasThisSaucerPushed($saucerColor)
		{
				$pushedOnTurn = self::getUniqueValueFromDb( "SELECT pushed_on_saucer_turn
																								FROM ostrich
																								WHERE ostrich_color='$saucerColor' LIMIT 1" );
				if($pushedOnTurn != '' && $pushedOnTurn != 0 && $pushedOnTurn != '0')
				{
						return true;
				}

				return false;
		}

		function getPushedDirection($saucerColor)
		{
				return self::getUniqueValueFromDb("SELECT pushed_direction FROM ostrich WHERE ostrich_color='$saucerColor'");
		}

		function setPushedDirection($saucerColor, $newValue)
		{
				$sql = "UPDATE ostrich SET pushed_direction='$newValue' WHERE ";
				$sql .= "ostrich_color='$saucerColor'";
				self::DbQuery( $sql );
		}

		function getPushedDistance($saucerColor)
		{
				return self::getUniqueValueFromDb("SELECT pushed_distance FROM ostrich WHERE ostrich_color='$saucerColor'");
		}

		function setPushedDistance($saucerColor, $newValue)
		{
				$sql = "UPDATE ostrich SET pushed_distance='$newValue' WHERE ";
				$sql .= "ostrich_color='$saucerColor'";
				self::DbQuery( $sql );
		}

		function getPushedSaucerMoving()
		{
				$saucerPushed = self::getObjectListFromDB( "SELECT ostrich_color
																									FROM ostrich
																									WHERE pushed_on_saucer_turn<>'0' AND pushed_on_saucer_turn<>'' LIMIT 1" );
				if(count($saucerPushed) < 1)
				{ // we did not find any saucers that are pushed and still need to move
						return '';
				}
				else
				{
						foreach($saucerPushed as $saucer)
						{
								return $saucer['ostrich_color'];
						}

				}
		}

		// new version where we do each space move individually
		function executeMove()
		{
				// determine the saucer that is executing a move
				$saucerMoving = $this->getPushedSaucerMoving();
//throw new feException( "saucerMoving: $saucerMoving");
				if($saucerMoving == '')
				{ // we didn't find a saucer that was pushed and still needs to move
							$saucerMoving = $this->getOstrichWhoseTurnItIs();
				}

				$this->executeSaucerMove($saucerMoving);

//throw new feException( "saucerMoving:$saucerMoving");

				// move spaces until we get to 0 movement or hit an event
						// get next spaces

						// send notify for moves to client

						// reasons to stop early:
						// crash site unused waste accelerator: ask if they want to use it


						// saucer:
						 		// phase shift: ask if they wish to use
								// proximity mines: ask if they wish to use
								// collide:
										// set distance_remaining of collidee to distance gone for collider
										// set distance_remaining to 0 for collider
										// set pushed_on_saucer_turn of collidee to collider
										// set state to executingMove

				// when distance_remaining gets to 0 for both saucer and any saucers with pushed_on_saucer_turn set
						// set pushed_on_saucer to '' if any are set
						// see if anyone fell off
						// go to the next state
//throw new feException( "end argExecutingMove");
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
