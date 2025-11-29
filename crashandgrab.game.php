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
					"CURRENT_TURN" => 13

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
				$this->upgradeCards->autoreshuffle_trigger = array('obj' => $this, 'method' => 'deckAutoReshuffle'); // add a callback method so we know when the deck has been reshuffled

				$this->UP_DIRECTION = 'sun';
				$this->DOWN_DIRECTION = 'meteor';
				$this->LEFT_DIRECTION = 'constellation';
				$this->RIGHT_DIRECTION = 'asteroids';

				// colors
				$this->REDCOLOR = "b83a4b";
				$this->YELLOWCOLOR = "f8e946";
				$this->BLUECOLOR = "009add";
				$this->GREENCOLOR = "228848";
				$this->PURPLECOLOR = "753bbd";
				//$this->GRAYCOLOR = "c9d2db";
				$this->PINKCOLOR = "ff3eb5";


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

				 $this->initializeUpgradeCards();


        // TODO: setup the initial game situation here

				$this->setGameStateValue("CURRENT_ROUND", 1); // start on round 1
				$this->setGameStateValue("CURRENT_TURN", 1); // start on turn 1



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

				$result['databaseIdToCollectorIdMapping'] = $this->getDatabaseIdToCollectorIdMapping();

				// put the cards that are in this player's hand into the array that is returned to the UI/javascript/client layer with the key "hand"
				$saucers = $this->getSaucersForPlayer($player_id);



				// get this player's saucers and all of this player's move cards
				$result['saucer1'] = '';
				$result['saucer2'] = '';
				$result['hand'] = null;
				foreach( $saucers as $saucer )
				{ // go through each saucer owned by the player
						$saucerColor = $saucer['ostrich_color'];

//self::warn("<b>Saucer Color:</b> $saucerColor"); // log to sql database

								if(is_null($result['hand']))
								{ // first saucer
//self::warn("<b>HAND NULL</b>"); // log to sql database

										// save the color representing saucer 1
										$result['saucer1'] = $saucerColor;

										$result['hand'] = $this->movementCards->getCardsInLocation( $saucerColor ); // get the cards for this saucer
										//$result['hand'] = $this->movementCards->getCardsInLocation( 'hand' ); // get the cards for this saucer

								}
								else
								{ // they had a second saucer

//self::warn("<b>HAND not NULL</b>"); // log to sql database

										// save the color representing saucer 2
										$result['saucer2'] = $saucerColor;

										// merge their other saucer movement cards with this saucer
										$result['hand'] = array_merge($result['hand'], $this->movementCards->getCardsInLocation( $saucerColor ) ); // merge their other saucer with this saucer

								}
				}

				$result['chosenMoveCards'] = $this->getChosenMoveCards($player_id);

				$result['upgradeCardContent'] = $this->getAllUpgradeCardContent();
				$result['playedUpgrades'] = $this->getAllPlayedUpgradesBySaucer();
				$result['discardedUpgrades'] = $this->getAllDiscardedUpgrades();

				$result['upgradeList'] = $this->getUpgradeList();


				// get the board layout
        $result['board'] = self::getObjectListFromDB( "SELECT board_x x, board_y y, board_space_type space_type
                                                       FROM board
                                                       WHERE board_space_type IS NOT NULL" );

  			// get the ostrich positions
				$result['ostrich'] = self::getObjectListFromDB( "SELECT ostrich_x x,ostrich_y y, ostrich_color color, ostrich_owner owner, ostrich_last_direction last_direction, ostrich_has_zag has_zag, ostrich_has_crown, booster_quantity, energy_quantity, has_override_token
				                                               FROM ostrich
				                                               WHERE 1 ORDER BY ostrich_owner, ostrich_color" );

				$result['lastMovedOstrich'] = $this->LAST_MOVED_OSTRICH;

				// go through each crewmember for this saucer and set the primary and extras
				$this->setCrewmemberPrimaryAndExtrasForAllSaucers();
				$result['garment'] = self::getObjectListFromDB( "SELECT garment_x,garment_y,garment_location,garment_color,garment_type,is_primary FROM garment ORDER BY garment_location,garment_type");

				$result['turnOrder'] = $this->getGameStateValue("TURN_ORDER"); // 0=CLOCKWISE, 1=COUNTER-CLOCKWISE, 2=UNKNOWN
				$result['probePlayer'] = $this->getStartPlayer(); // get the owner of the saucer with the probe
				$result['turnOrderFriendly'] = $this->convertTurnOrderIntToText($result['turnOrder']);
				$result['turnOrderArray'] = $this->createTurnOrderArray($result['probePlayer'], $result['turnOrder']);

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
		$numberOfPlayers = $this->getNumberOfPlayers();
		if($numberOfPlayers == 2)
		{
			$numberOfPlayers = 4; // since they each control 2 saucers, we need to treat them like a 4-player game for purposes of counting
		}				
		$maxCrewmembersNeededToWin = ($numberOfPlayers * 4); // get the maximum number of garments that can be acquired before someone wins

		$numberOfSeatedCrewmembers = $this->countTotalSeatedCrewmembers();

		$percentageCompleted = intval(100 * ($numberOfSeatedCrewmembers / $maxCrewmembersNeededToWin)); // divide current by max and multiply by 100 to get an integer between 1-100

		//throw new feException( "PROGRESSION number of garments acquired " . $numberOfGarmentsAcquired . " and max garments " . $maxGarments . " and percentageCompleted " . $percentageCompleted);

        return $percentageCompleted;
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////

    /*
        In this space, you can put any utility methods useful for your game logic
    */

	// called when the Upgrade deck runs out of cards
	function deckAutoReshuffle()
	{
		//$this->resetEquipmentDeckAfterReshuffle();

		self::notifyAllPlayers( "upgradeDeckReshuffled", clienttranslate( 'The Upgrade deck has been reshuffled.' ), array(
			'allUpgrades' => $this->getUpgradeList()
		) );
	}	
	
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
				self::initStat( 'player', 'times_you_crashed', 0 );
				self::initStat( 'player', 'distance_you_were_pushed', 0 );
				self::initStat( 'player', '2s_played', 0 );

				self::initStat( 'player', '3s_played', 0 );
				self::initStat( 'player', 'Xs_played', 0 );

				self::initStat( 'player', 'accelerators_used', 0 );
				self::initStat( 'player', 'boosters_used', 0 );

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

		function initializeUpgradeCards()
		{
				// Create Movement Cards
				// type: Deface Paint, Twirlybird
				// type_arg: probably don't need... should mimic card id

				$cardsList = array(
						array( 'type' => 'Blast Off Thrusters', 'type_arg' => 1, 'card_location' => 'deck', 'nbr' => 1),
						array( 'type' => 'Wormhole Generator', 'type_arg' => 2, 'card_location' => 'deck', 'nbr' => 1),
						array( 'type' => 'Afterburner', 'type_arg' => 3, 'card_location' => 'deck', 'nbr' => 1),
						array( 'type' => 'Pulse Cannon', 'type_arg' => 4, 'card_location' => 'deck', 'nbr' => 1),
						array( 'type' => 'Tractor Beam', 'type_arg' => 5, 'card_location' => 'deck', 'nbr' => 1),
						array( 'type' => 'Saucer Teleporter', 'type_arg' => 6, 'card_location' => 'deck', 'nbr' => 1),
						array( 'type' => 'Cloaking Device', 'type_arg' => 7, 'card_location' => 'deck', 'nbr' => 1),
						array( 'type' => 'Waste Accelerator', 'type_arg' => 8, 'card_location' => 'deck','nbr' => 1),
						array( 'type' => 'Hyperdrive', 'type_arg' => 9, 'card_location' => 'deck','nbr' => 1),
						array( 'type' => 'Scavenger Bot', 'type_arg' => 10, 'card_location' => 'deck','nbr' => 1),
						array( 'type' => 'Distress Signaler', 'type_arg' => 11, 'card_location' => 'deck','nbr' => 1),
						array( 'type' => 'Time Machine', 'type_arg' => 12, 'card_location' => 'deck','nbr' => 1),
						array( 'type' => 'Regeneration Gateway', 'type_arg' => 13, 'card_location' => 'deck','nbr' => 1),
						array( 'type' => 'Kinetic Siphon', 'type_arg' => 14, 'card_location' => 'deck','nbr' => 1),
						array( 'type' => 'Cargo Hold', 'type_arg' => 15, 'card_location' => 'deck','nbr' => 1),
						array( 'type' => 'Proximity Mines', 'type_arg' => 16, 'card_location' => 'deck','nbr' => 1),
						array( 'type' => 'Landing Legs', 'type_arg' => 17, 'card_location' => 'deck','nbr' => 1),
						array( 'type' => 'Quake Maker', 'type_arg' => 18, 'card_location' => 'deck', 'nbr' => 1),
						array( 'type' => 'Airlock', 'type_arg' => 20, 'card_location' => 'deck','nbr' => 1),
						array( 'type' => 'Acceleration Regulator', 'type_arg' => 24, 'card_location' => 'deck','nbr' => 1),
						array( 'type' => 'Boost Amplifier', 'type_arg' => 25, 'card_location' => 'deck','nbr' => 1),
						array( 'type' => 'Organic Triangulator', 'type_arg' => 26, 'card_location' => 'deck','nbr' => 1)
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
						//self::DbQUery("INSERT INTO garment (garment_x,garment_y,garment_location,garment_color,garment_type) VALUES (0,0,'pile','b83a4b',0)");
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

				if($this->getNumberOfPlayers() < 6)
				{ // 2-5 players

						// insert an extras set of crewmembers
						$unusedColor = $this->getUniqueSaucerColor();
						$sqlCrewmemberPilot = "INSERT INTO garment (garment_x,garment_y,garment_location,garment_color,garment_type) VALUES ";
						$sqlCrewmemberPilot .= "(0,0,'pile','".$unusedColor."',0) ";
						self::DbQuery( $sqlCrewmemberPilot );

						$sqlCrewmemberEngineer = "INSERT INTO garment (garment_x,garment_y,garment_location,garment_color,garment_type) VALUES ";
						$sqlCrewmemberEngineer .= "(0,0,'pile','".$unusedColor."',1) ";
						self::DbQuery( $sqlCrewmemberEngineer );

						$sqlCrewmemberDoctor = "INSERT INTO garment (garment_x,garment_y,garment_location,garment_color,garment_type) VALUES ";
						$sqlCrewmemberDoctor .= "(0,0,'pile','".$unusedColor."',2) ";
						self::DbQuery( $sqlCrewmemberDoctor );

						$sqlCrewmemberScientist = "INSERT INTO garment (garment_x,garment_y,garment_location,garment_color,garment_type) VALUES ";
						$sqlCrewmemberScientist .= "(0,0,'pile','".$unusedColor."',3) ";
						self::DbQuery( $sqlCrewmemberScientist );
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
										$this->giveProbe($saucerColor, "Starting"); // saucer of player going first gets the Probe

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
								$this->giveProbe($saucerColor, "Starting"); // saucer of player going first gets the Probe

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

		// get a valid color that doesn't match one already assigned to a player
		function getUniqueSaucerColor()
		{
				$possibleColors = array( "b83a4b", "228848", "009add", "f8e946", "753bbd", "ff3eb5" );

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

		// used for text in message log chat describing the value on a Move Card
		function convertDistanceTypeToString($distanceType)
		{
				switch($distanceType)
				{
						case "0":
						case 0:
						return "0-5";

						case "1":
						case 1:
						return "2";

						case "2":
						case 2:
						return "3";

						default:
						return "new value";
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
						case $this->PINKCOLOR:
							return clienttranslate('PINK');
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
						case "PINK":
							return $this->PINKCOLOR;
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

		function getSaucerGoingLast()
		{
				$saucerWithProbe = $this->getSaucerWithProbe();
				$playerWithProbe = $this->getOwnerIdOfOstrich($saucerWithProbe);

				$playerGoingLast = $this->getPlayerAfter($playerWithProbe);

				$clockwise = $this->isTurnOrderClockwise(); // true or false
				if($clockwise)
				{
						$playerGoingLast = $this->getPlayerBefore($playerWithProbe);
				}

				$saucersForPlayer = $this->getSaucersForPlayer($playerGoingLast);
				foreach( $saucersForPlayer as $saucer )
				{ // go through each saucer owned by this player

						// they will only have 1 so just return the first one
						return $saucer['ostrich_color'];
				}
		}

		function getSaucerGoingSecondToLast()
		{
				$saucerWithProbe = $this->getSaucerWithProbe();
				$playerWithProbe = $this->getOwnerIdOfOstrich($saucerWithProbe);

				// go back 2
				$playerGoingSecondToLast = $this->getPlayerAfter($playerWithProbe);
				$playerGoingSecondToLast = $this->getPlayerAfter($playerGoingSecondToLast);

				$clockwise = $this->isTurnOrderClockwise(); // true or false
				if($clockwise)
				{
						// go back 2
						$playerGoingSecondToLast = $this->getPlayerBefore($playerWithProbe);
						$playerGoingSecondToLast = $this->getPlayerBefore($playerGoingSecondToLast);
				}

				$saucersForPlayer = $this->getSaucersForPlayer($playerGoingSecondToLast);
				foreach( $saucersForPlayer as $saucer )
				{ // go through each saucer owned by this player

						// they will only have 1 so just return the first one
						return $saucer['ostrich_color'];
				}
		}

		// get a list of what the turn order would be if it were Clockwise versus Counterclockwise.
		function getClockwiseCounterTurnOrders()
		{
			$orderOptions = array();

			$saucerWithProbe = $this->getSaucerWithProbe();
			$playerWithProbe = $this->getOwnerIdOfOstrich($saucerWithProbe);

			$orderOptions['clockwise'] = array();
			$orderOptions['counterClockwise'] = array();

			// CLOCKWISE
			array_push($orderOptions['clockwise'], $saucerWithProbe);
			$playerCount = 1;
			$max = 10;
			$currentPlayer = $this->getPlayerAfter($playerWithProbe);
			while ($currentPlayer != $playerWithProbe && $playerCount < $max) {

				$saucersForPlayer = $this->getSaucersForPlayer($currentPlayer);
				foreach( $saucersForPlayer as $saucer )
				{ // go through each saucer owned by this player (should just be 1)
					
						$color = $saucer['ostrich_color']; // get the color this player was assigned
						array_push($orderOptions['clockwise'], $color);
				}

				$currentPlayer = $this->getPlayerAfter($currentPlayer);
				$playerCount += 1;
			}

			// COUNTER-CLOCKWISE
			array_push($orderOptions['counterClockwise'], $saucerWithProbe);
			$playerCount = 1;
			$currentPlayer = $this->getPlayerBefore($playerWithProbe);
			while ($currentPlayer != $playerWithProbe && $playerCount < $max) {

				$saucersForPlayer = $this->getSaucersForPlayer($currentPlayer);
				foreach( $saucersForPlayer as $saucer )
				{ // go through each saucer owned by this player (should just be 1)
					
						$color = $saucer['ostrich_color']; // get the color this player was assigned
						array_push($orderOptions['counterClockwise'], $color);
				}

				$currentPlayer = $this->getPlayerBefore($currentPlayer);
				$playerCount += 1;
			}

			return $orderOptions;
		}

		function getSaucerToGoSecond()
		{
				$result = array();

				//$currentPlayer = self::getCurrentPlayerId(); // Current Player = player who played the current player action (the one who made the AJAX request). Active Player = player whose turn it is.
				$activePlayer = self::getActivePlayerId(); // Current Player = player who played the current player action (the one who made the AJAX request). Active Player = player whose turn it is.

				$saucerWithProbe = $this->getSaucerWithProbe();
				$playerWithProbe = $this->getOwnerIdOfOstrich($saucerWithProbe);
				$clockwisePlayer = $this->getPlayerAfter($playerWithProbe);
				$counterClockwisePlayer = $this->getPlayerBefore($playerWithProbe);

				$clockwiseSaucers = $this->getSaucersForPlayer($clockwisePlayer);
				foreach( $clockwiseSaucers as $saucer )
				{ // go through each saucer owned by this player

						$playerColor = $saucer['ostrich_color']; // get the color this player was assigned
						$playerColorFriendly = $this->convertColorToText($playerColor);

						$result['clockwise'] = array();
						$result['clockwise']['saucerColor'] = $playerColor;
						$result['clockwise']['buttonLabel'] = $playerColorFriendly;
						$result['clockwise']['hoverOverText'] = '';
						$result['clockwise']['actionName'] = 'selectSaucerToGoFirst';
						$result['clockwise']['isDisabled'] = false;
						$result['clockwise']['makeRed'] = false;
				}

				$counterClockwiseSaucers = $this->getSaucersForPlayer($counterClockwisePlayer);
				foreach( $counterClockwiseSaucers as $saucer )
				{ // go through each saucer owned by this player

						$playerColor = $saucer['ostrich_color']; // get the color this player was assigned
						$playerColorFriendly = $this->convertColorToText($playerColor);

						$result['counterclockwise'] = array();
						$result['counterclockwise']['saucerColor'] = $playerColor;
						$result['counterclockwise']['buttonLabel'] = $playerColorFriendly;
						$result['counterclockwise']['hoverOverText'] = '';
						$result['counterclockwise']['actionName'] = 'selectSaucerToGoFirst';
						$result['counterclockwise']['isDisabled'] = false;
						$result['counterclockwise']['makeRed'] = false;
				}

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

		function notifyPlayersOfUndoCardSelection($cardOwnerId, $saucerColor)
		{
				$otherPlayers = $this->getAllPlayersExcept($cardOwnerId);

				foreach( $otherPlayers as $player )
				{ // go through each saucer

						$playerId = $player['player_id'];

						self::notifyPlayer( $playerId, 'cardUnchosen', '', array(
							'saucer_choosing' => $saucerColor
						 ) );

				}

		}

		function getCardChosenState($cardId)
		{
				return self::getUniqueValueFromDb("SELECT card_chosen_state FROM movementCards WHERE card_id=$cardId");
		}

		function unchooseCardsForSaucer($saucerColor)
		{
			$sqlUpdate = "UPDATE movementCards SET ";
			$sqlUpdate .= "card_chosen_state='unchosen' ";
			$sqlUpdate .= "WHERE card_location='$saucerColor'";

			self::DbQuery( $sqlUpdate );
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
				$sqlUpdate .= "value_1='0',value_2='0',value_3='0',value_4='0'";

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
		function getSaucerAcceleratorAndBoosterMoves($moveType='regular', $saucerColor='', $usingBooster=false, $usingAccelerator=false)
		{
				$result = array();
				if($saucerColor == '')
				{
					$saucerColor = $this->getSaucerWhoseTurnItIs();
				}


				//throw new feException( "saucerColor:$saucerColor moveType:$moveType" );

				$saucerDetails = self::getObjectListFromDB( "SELECT ostrich_color, ostrich_owner
																					 FROM ostrich WHERE ostrich_color='$saucerColor' ORDER BY ostrich_owner" );
//$allSaucersCount = count($allSaucers);
//throw new feException( "allSaucers Count:$allSaucersCount" );
				$currentPlayerId = 0;
				foreach( $saucerDetails as $saucer )
				{ // go through the details of this Saucer (should just be 1)
						$owner = $saucer['ostrich_owner'];
						$color = $saucer['ostrich_color'];

						if($owner != $currentPlayerId)
						{ // this is a new player we haven't seen yet
								$currentPlayerId = $owner; // save that we're on this owner so we know when we get to a new owner
								$result[$owner] = array(); // create a new array for this player
						}

						$result[$owner][$color] = array(); // every saucer needs an array of values

						if($moveType == "Landing Legs")
						{ // we are moving from Landing Legs

								// put it in the same format as when using a move card
								$landingLegsMoves = $this->getValidSpacesForUpgrade($saucerColor, "Landing Legs"); // in order of right, left, down, up
								$result[$owner][$color]['none'] = array(); // using 'none' for move card key because we don't care about that for Landing Legs
								$result[$owner][$color]['none'][$this->UP_DIRECTION] = array(); // we need an array for the spaces we get with this card type and direction
								$result[$owner][$color]['none'][$this->DOWN_DIRECTION] = array(); // we need an array for the spaces we get with this card type and direction
								$result[$owner][$color]['none'][$this->LEFT_DIRECTION] = array(); // we need an array for the spaces we get with this card type and direction
								$result[$owner][$color]['none'][$this->RIGHT_DIRECTION] = array(); // we need an array for the spaces we get with this card type and direction

								$index = 0;
								foreach($landingLegsMoves as $formattedSpace)
								{
										if($index == 0)
										{ // right
											array_push($result[$owner][$color]['none'][$this->RIGHT_DIRECTION], $formattedSpace);
										}
										elseif($index == 1)
										{ // left
											array_push($result[$owner][$color]['none'][$this->LEFT_DIRECTION], $formattedSpace);
										}
										elseif($index == 2)
										{ // down
											array_push($result[$owner][$color]['none'][$this->DOWN_DIRECTION], $formattedSpace);
										}
										elseif($index == 3)
										{ // up
											array_push($result[$owner][$color]['none'][$this->UP_DIRECTION], $formattedSpace);
										}

										$index++;
								}
						}
						else
						{ // we are moving from a movement card
								$getLastSaucerDistanceType = $this->getSaucerDistanceType($color); // 0, 1, 2

								$movesForSaucer = $this->getMovesForSaucer($color, $getLastSaucerDistanceType, '', $usingBooster, $usingAccelerator); // go in any direction
								/*
								$movesForSaucer = array();
								if($this->hasOverrideToken($color) || 
								   $this->canSaucerChooseDirection($color) || 
								   ($this->doesSaucerHaveUpgradePlayed($color, "Time Machine") &&
								   $this->getUpgradeTimesActivatedThisRound($color, "Time Machine") < 1 &&
								   $this->isUpgradePlayable($color, 'Time Machine')))
								{ // this Saucer can go in any direction
									//throw new feException( "can choose direction" );
									$movesForSaucer = $this->getMovesForSaucer($color, $getLastSaucerDistanceType, ''); // go in any direction
								}
								else
								{ // this saucer is going in a specific direction
									//throw new feException( "cannot choose direction" );
									$directionSelected = $this->getSaucerDirection($color);
									$movesForSaucer = $this->getMovesForSaucer($color, $getLastSaucerDistanceType, $directionSelected); // specify the direction
								}
								*/
								
								foreach( $movesForSaucer as $cardType => $moveCard )
								{ // go through each move card for this saucer

										$directionsWithSpaces = $moveCard['directions'];
										//$count = count($directionsWithSpaces);
										//throw new feException( "directionsWithSpaces Count:$count" );

										$result[$owner][$color][$cardType] = array(); // make an array for the list of spaces available using this card

										foreach( $directionsWithSpaces as $direction => $directionWithSpaces )
										{ // go through each direction

												$result[$owner][$color][$cardType][$direction] = array(); // we need an array for the spaces we get with this card type and direction

												foreach( $directionWithSpaces as $space )
												{ // go through each space

														$column = $space['column'];
														$row = $space['row'];

														
														//echo("[$owner][$color][$cardType][$direction]:($row,$column)");
														//echo("<br>");
														

														$formattedSpace = $column.'_'.$row;

														array_push($result[$owner][$color][$cardType][$direction], $formattedSpace);
												}
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
							$result[$crashSiteIndex] = array();
							$result[$crashSiteIndex]['x'] = $locX; // 1, 2, 3
							$result[$crashSiteIndex]['y'] = $locY; // 1, 2, 3
							$result[$crashSiteIndex]['number'] = $this->getBoardSpaceType($locX, $locY); // 1, 2, 3
							$result[$crashSiteIndex]['numberAsInt'] = (int)$this->getBoardSpaceType($locX, $locY); // 1, 2, 3
						}
				}

				//$count = count($result);
				//throw new feException( "Count:".$count);

				// sort the crash sites
				usort($result, function($a, $b) {
					return $a['numberAsInt'] <=> $b['numberAsInt'];
				});

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

		// returns TRUE if there is a different saucer in the row or column
		// of the sourceSaucer
		function isSaucerInRowOrColumnOfSaucer($sourceSaucer)
		{
				$sourceSaucerX = $this->getSaucerXLocation($sourceSaucer);
				$sourceSaucerY = $this->getSaucerYLocation($sourceSaucer);

				$allSaucers = $this->getAllSaucers();
				foreach( $allSaucers as $saucer )
				{ // go through each crewmember
						$saucerX = $saucer['ostrich_x'];
						$saucerY = $saucer['ostrich_y'];
						$saucerColor = $saucer['ostrich_color'];

						if($saucerColor != $sourceSaucer && (
							$sourceSaucerX == $saucerX || $sourceSaucerY == $saucerY))
						{ // this is not our source saucer but it is in the same row or column
								if(!$this->isSaucerCrashed($saucerColor))
								{ // the saucer isn't crashed (if they are crashed they aren't really in their row or column anymore)
									return true;
								}
						}
				}

				return false;
		}

		// Returns true if the given saucer picked up or stole a crewmember this turn.
		function didSaucerPickUpOrStealCrewmemberThisTurn($saucerColor)
		{
			$currentTurn = $this->getGameStateValue("CURRENT_TURN");

			$crewmembersOnSaucer = $this->getCrewmembersOnSaucer($saucerColor);
			foreach( $crewmembersOnSaucer as $crewmember )
			{ // go through each crewmember on this saucer
				$turnAcquired = $crewmember['turn_acquired'];

				//throw new feException( "CURRENT TURN $currentTurn and TURN ACQUIRED $turnAcquired" );
				

				if($turnAcquired == $currentTurn)
				{ // this crewmember was acquired this turn

					return true;
				}
			}

			//throw new feException( "did not find any for CURRENT TURN $currentTurn and saucer color $saucerColor" );
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

/*
if($color == '009add')
{
						//$count = count($movesForSaucer);
						//throw new feException( "movesForSaucer Count:$count" );

						foreach(array_keys($movesForSaucer) as $paramName)
						{
						   echo($paramName);
						   echo("<br>");
						}
}
*/
						foreach( $movesForSaucer as $cardType => $moveCard )
						{ // go through each move card for this saucer

								$directionsWithSpaces = $moveCard['directions'];
								//$count = count($directionsWithSpaces);
								//throw new feException( "directionsWithSpaces Count:$count" );

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

		function getMovesForSaucer($color, $specificMoveCard='', $specificDirection='', $usingBooster=false, $usingAccelerator=false)
		{
			//throw new feException( "getMovesForSaucer color:$color specificMoveCard:$specificMoveCard specificDirection:$specificDirection" );
				$result = array();
				if($color == '009add')
				{
//throw new feException( "753bbd specificMoveCard:$specificMoveCard" );
				}
				$availableMoveCards = $this->getAvailableMoveCardsForSaucer($color);

				//$availableMoveCardsCount = count($availableMoveCards);
				//throw new feException( "availableMoveCards Count:$availableMoveCardsCount" );

				$arrayIndex = 0;
				foreach( $availableMoveCards as $distanceType )
				{ // 0, 1, 2
						if($specificMoveCard == '' || $distanceType == $specificMoveCard)
						{ // we are only looking for all moves or this specific distance (because this is for an Accelerator or Booster)
//throw new feException( "specificMoveCard:$specificMoveCard" );
								$result[$distanceType] = array(); // this saucer, this card

								$result[$distanceType]['directions'] = array(); // list of spaces for this saucer, this card

								$saucerX = $this->getSaucerXLocation($color); // this saucer's starting column
								$saucerY = $this->getSaucerYLocation($color); // this saucer's starting row

								$originalDistance = $this->getSaucerOriginalTurnDistance($color); // if we are using a Booster, you just boost the distance you moved at the beginning of your turn... it might have been modified by Hyperdrive or a specific card... but we just go that distance

								if($specificDirection == '' || $specificDirection == 'sun')
								{
									//throw new feException( "sun specificDirection:$specificDirection" );

									if($usingBooster)
									{ // we are using a booster

										$result[$distanceType]['directions']['sun'] = $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, $originalDistance, 'sun'); // destinations for this saucer, this card, in the sun direction
										
										if($this->doesSaucerHaveUpgradePlayed($color, "Boost Amplifier"))
										{ // this player has Boost Amplifier
											if($originalDistance != 1)
											{
												$result[$distanceType]['directions']['sun'] = array_merge($result[$distanceType]['directions']['sun'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 1, 'sun')); // destinations for this saucer, this card, in the sun direction
											}

											if($originalDistance != 2)
											{
												$result[$distanceType]['directions']['sun'] = array_merge($result[$distanceType]['directions']['sun'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 2, 'sun')); // destinations for this saucer, this card, in the sun direction
											}

											if($originalDistance != 3)
											{
												$result[$distanceType]['directions']['sun'] = array_merge($result[$distanceType]['directions']['sun'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 3, 'sun')); // destinations for this saucer, this card, in the sun direction
											}
											
											if($originalDistance != 4)
											{
												$result[$distanceType]['directions']['sun'] = array_merge($result[$distanceType]['directions']['sun'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 4, 'sun')); // destinations for this saucer, this card, in the sun direction
											}
											
											if($originalDistance != 5)
											{
												$result[$distanceType]['directions']['sun'] = array_merge($result[$distanceType]['directions']['sun'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 5, 'sun')); // destinations for this saucer, this card, in the sun direction
											}
											
											if($originalDistance != 6)
											{
												$result[$distanceType]['directions']['sun'] = array_merge($result[$distanceType]['directions']['sun'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 6, 'sun')); // destinations for this saucer, this card, in the sun direction
											}

											if($this->doesSaucerHaveUpgradePlayed($color, "Hyperdrive"))
											{ // this player has Hyperdrive

												if($originalDistance != 8)
												{
													$result[$distanceType]['directions']['sun'] = array_merge($result[$distanceType]['directions']['sun'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 8, 'sun')); // destinations for this saucer, this card, in the sun direction
												}
												
												if($originalDistance != 10)
												{
													$result[$distanceType]['directions']['sun'] = array_merge($result[$distanceType]['directions']['sun'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 10, 'sun')); // destinations for this saucer, this card, in the sun direction
												}
											
												if($originalDistance != 12)
												{
													$result[$distanceType]['directions']['sun'] = array_merge($result[$distanceType]['directions']['sun'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 12, 'sun')); // destinations for this saucer, this card, in the sun direction
												}
											}
										}
									}
									elseif($usingAccelerator)
									{ // they are using an accelerator

										// just give them the option to go that distance... not what they chose for their move card
										$lastDistanceChosen = $this->getSaucerLastDistance($color);
										$result[$distanceType]['directions']['sun'] = $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, $lastDistanceChosen, 'sun'); // destinations for this saucer, this card, in the sun direction
									}
									elseif($distanceType == 1)
									{ // played a 2 card
										$result[$distanceType]['directions']['sun'] = $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 2, 'sun'); // destinations for this saucer, this card, in the sun direction

										if($this->doesSaucerHaveUpgradePlayed($color, "Hyperdrive"))
										{ // this player has Hyperdrive
											$result[$distanceType]['directions']['sun'] = array_merge($result[$distanceType]['directions']['sun'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 4, 'sun')); // destinations for this saucer, this card, in the sun direction
										}
									}
									elseif($distanceType == 2)
									{ // played a 3 card
										$result[$distanceType]['directions']['sun'] = $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 3, 'sun'); // destinations for this saucer, this card, in the sun direction

										if($this->doesSaucerHaveUpgradePlayed($color, "Hyperdrive"))
										{ // this player has Hyperdrive
											$result[$distanceType]['directions']['sun'] = array_merge($result[$distanceType]['directions']['sun'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 6, 'sun')); // destinations for this saucer, this card, in the sun direction
										}
									}
									elseif($distanceType == 0)
									{ // played a 0-5 card

										$result[$distanceType]['directions']['sun'] = $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 0, 'sun'); // destinations for this saucer, this card, in the sun direction
										$result[$distanceType]['directions']['sun'] = array_merge($result[$distanceType]['directions']['sun'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 1, 'sun')); // destinations for this saucer, this card, in the sun direction
										$result[$distanceType]['directions']['sun'] = array_merge($result[$distanceType]['directions']['sun'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 2, 'sun')); // destinations for this saucer, this card, in the sun direction
										$result[$distanceType]['directions']['sun'] = array_merge($result[$distanceType]['directions']['sun'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 3, 'sun')); // destinations for this saucer, this card, in the sun direction
										$result[$distanceType]['directions']['sun'] = array_merge($result[$distanceType]['directions']['sun'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 4, 'sun')); // destinations for this saucer, this card, in the sun direction
										$result[$distanceType]['directions']['sun'] = array_merge($result[$distanceType]['directions']['sun'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 5, 'sun')); // destinations for this saucer, this card, in the sun direction

										if($this->doesSaucerHaveUpgradePlayed($color, "Hyperdrive"))
										{ // this player has Hyperdrive
											$result[$distanceType]['directions']['sun'] = array_merge($result[$distanceType]['directions']['sun'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 6, 'sun')); // destinations for this saucer, this card, in the sun direction
											$result[$distanceType]['directions']['sun'] = array_merge($result[$distanceType]['directions']['sun'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 8, 'sun')); // destinations for this saucer, this card, in the sun direction
											$result[$distanceType]['directions']['sun'] = array_merge($result[$distanceType]['directions']['sun'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 10, 'sun')); // destinations for this saucer, this card, in the sun direction
										}
									}

								}

								if($specificDirection == '' || $specificDirection == 'asteroids')
								{
									//throw new feException( "asteroids specificDirection:$specificDirection" );
									
									if($usingBooster)
									{ // we are using a booster

										$result[$distanceType]['directions']['asteroids'] = $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, $originalDistance, 'asteroids'); // destinations for this saucer, this card, in the asteroids direction
										
										if($this->doesSaucerHaveUpgradePlayed($color, "Boost Amplifier"))
										{ // this player has Boost Amplifier
											if($originalDistance != 1)
											{
												$result[$distanceType]['directions']['asteroids'] = array_merge($result[$distanceType]['directions']['asteroids'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 1, 'asteroids')); // destinations for this saucer, this card, in the asteroids direction
											}

											if($originalDistance != 2)
											{
												$result[$distanceType]['directions']['asteroids'] = array_merge($result[$distanceType]['directions']['asteroids'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 2, 'asteroids')); // destinations for this saucer, this card, in the asteroids direction
											}

											if($originalDistance != 3)
											{
												$result[$distanceType]['directions']['asteroids'] = array_merge($result[$distanceType]['directions']['asteroids'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 3, 'asteroids')); // destinations for this saucer, this card, in the asteroids direction
											}
											
											if($originalDistance != 4)
											{
												$result[$distanceType]['directions']['asteroids'] = array_merge($result[$distanceType]['directions']['asteroids'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 4, 'asteroids')); // destinations for this saucer, this card, in the asteroids direction
											}
											
											if($originalDistance != 5)
											{
												$result[$distanceType]['directions']['asteroids'] = array_merge($result[$distanceType]['directions']['asteroids'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 5, 'asteroids')); // destinations for this saucer, this card, in the asteroids direction
											}
											
											if($originalDistance != 6)
											{
												$result[$distanceType]['directions']['asteroids'] = array_merge($result[$distanceType]['directions']['asteroids'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 6, 'asteroids')); // destinations for this saucer, this card, in the asteroids direction
											}

											if($this->doesSaucerHaveUpgradePlayed($color, "Hyperdrive"))
											{ // this player has Hyperdrive

												if($originalDistance != 8)
												{
													$result[$distanceType]['directions']['asteroids'] = array_merge($result[$distanceType]['directions']['asteroids'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 8, 'asteroids')); // destinations for this saucer, this card, in the asteroids direction
												}
												
												if($originalDistance != 10)
												{
													$result[$distanceType]['directions']['asteroids'] = array_merge($result[$distanceType]['directions']['asteroids'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 10, 'asteroids')); // destinations for this saucer, this card, in the asteroids direction
												}
											
												if($originalDistance != 12)
												{
													$result[$distanceType]['directions']['asteroids'] = array_merge($result[$distanceType]['directions']['asteroids'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 12, 'asteroids')); // destinations for this saucer, this card, in the asteroids direction
												}
											}
										}
									}
									elseif($usingAccelerator)
									{ // they are using an accelerator

										// just give them the option to go that distance... not what they chose for their move card
										$lastDistanceChosen = $this->getSaucerLastDistance($color);
										$result[$distanceType]['directions']['asteroids'] = $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, $lastDistanceChosen, 'asteroids'); // destinations for this saucer, this card, in the sun direction
									}
									elseif($distanceType == 1)
									{ // played a 2 card
										$result[$distanceType]['directions']['asteroids'] = $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 2, 'asteroids'); // destinations for this saucer, this card, in the sun direction

										if($this->doesSaucerHaveUpgradePlayed($color, "Hyperdrive"))
										{ // this player has Hyperdrive
											$result[$distanceType]['directions']['asteroids'] = array_merge($result[$distanceType]['directions']['asteroids'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 4, 'asteroids')); // destinations for this saucer, this card, in the sun direction
										}
									}
									elseif($distanceType == 2)
									{ // played a 3 card
										$result[$distanceType]['directions']['asteroids'] = $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 3, 'asteroids'); // destinations for this saucer, this card, in the sun direction

										if($this->doesSaucerHaveUpgradePlayed($color, "Hyperdrive"))
										{ // this player has Hyperdrive
											$result[$distanceType]['directions']['asteroids'] = array_merge($result[$distanceType]['directions']['asteroids'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 6, 'asteroids')); // destinations for this saucer, this card, in the sun direction
										}
									}
									elseif($distanceType == 0)
									{ // played a 0-5 card

										$result[$distanceType]['directions']['asteroids'] = $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 0, 'asteroids'); // destinations for this saucer, this card, in the sun direction
										$result[$distanceType]['directions']['asteroids'] = array_merge($result[$distanceType]['directions']['asteroids'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 1, 'asteroids')); // destinations for this saucer, this card, in the sun direction
										$result[$distanceType]['directions']['asteroids'] = array_merge($result[$distanceType]['directions']['asteroids'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 2, 'asteroids')); // destinations for this saucer, this card, in the sun direction
										$result[$distanceType]['directions']['asteroids'] = array_merge($result[$distanceType]['directions']['asteroids'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 3, 'asteroids')); // destinations for this saucer, this card, in the sun direction
										$result[$distanceType]['directions']['asteroids'] = array_merge($result[$distanceType]['directions']['asteroids'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 4, 'asteroids')); // destinations for this saucer, this card, in the sun direction
										$result[$distanceType]['directions']['asteroids'] = array_merge($result[$distanceType]['directions']['asteroids'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 5, 'asteroids')); // destinations for this saucer, this card, in the sun direction

										if($this->doesSaucerHaveUpgradePlayed($color, "Hyperdrive"))
										{ // this player has Hyperdrive
											$result[$distanceType]['directions']['asteroids'] = array_merge($result[$distanceType]['directions']['asteroids'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 6, 'asteroids')); // destinations for this saucer, this card, in the sun direction
											$result[$distanceType]['directions']['asteroids'] = array_merge($result[$distanceType]['directions']['asteroids'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 8, 'asteroids')); // destinations for this saucer, this card, in the sun direction
											$result[$distanceType]['directions']['asteroids'] = array_merge($result[$distanceType]['directions']['asteroids'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 10, 'asteroids')); // destinations for this saucer, this card, in the sun direction
										}
									}
								}

								if($specificDirection == '' || $specificDirection == 'meteor')
								{
									//throw new feException( "meteor specificDirection:$specificDirection" );
									
									if($usingBooster)
									{ // we are using a booster

										$result[$distanceType]['directions']['meteor'] = $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, $originalDistance, 'meteor'); // destinations for this saucer, this card, in the meteor direction
										
										if($this->doesSaucerHaveUpgradePlayed($color, "Boost Amplifier"))
										{ // this player has Boost Amplifier
											if($originalDistance != 1)
											{
												$result[$distanceType]['directions']['meteor'] = array_merge($result[$distanceType]['directions']['meteor'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 1, 'meteor')); // destinations for this saucer, this card, in the meteor direction
											}

											if($originalDistance != 2)
											{
												$result[$distanceType]['directions']['meteor'] = array_merge($result[$distanceType]['directions']['meteor'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 2, 'meteor')); // destinations for this saucer, this card, in the meteor direction
											}

											if($originalDistance != 3)
											{
												$result[$distanceType]['directions']['meteor'] = array_merge($result[$distanceType]['directions']['meteor'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 3, 'meteor')); // destinations for this saucer, this card, in the meteor direction
											}
											
											if($originalDistance != 4)
											{
												$result[$distanceType]['directions']['meteor'] = array_merge($result[$distanceType]['directions']['meteor'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 4, 'meteor')); // destinations for this saucer, this card, in the meteor direction
											}
											
											if($originalDistance != 5)
											{
												$result[$distanceType]['directions']['meteor'] = array_merge($result[$distanceType]['directions']['meteor'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 5, 'meteor')); // destinations for this saucer, this card, in the meteor direction
											}
											
											if($originalDistance != 6)
											{
												$result[$distanceType]['directions']['meteor'] = array_merge($result[$distanceType]['directions']['meteor'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 6, 'meteor')); // destinations for this saucer, this card, in the meteor direction
											}

											if($this->doesSaucerHaveUpgradePlayed($color, "Hyperdrive"))
											{ // this player has Hyperdrive

												if($originalDistance != 8)
												{
													$result[$distanceType]['directions']['meteor'] = array_merge($result[$distanceType]['directions']['meteor'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 8, 'meteor')); // destinations for this saucer, this card, in the meteor direction
												}
												
												if($originalDistance != 10)
												{
													$result[$distanceType]['directions']['meteor'] = array_merge($result[$distanceType]['directions']['meteor'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 10, 'meteor')); // destinations for this saucer, this card, in the meteor direction
												}
											
												if($originalDistance != 12)
												{
													$result[$distanceType]['directions']['meteor'] = array_merge($result[$distanceType]['directions']['meteor'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 12, 'meteor')); // destinations for this saucer, this card, in the meteor direction
												}
											}
										}
									}
									elseif($usingAccelerator)
									{ // they are using an accelerator

										// just give them the option to go that distance... not what they chose for their move card
										$lastDistanceChosen = $this->getSaucerLastDistance($color);
										$result[$distanceType]['directions']['meteor'] = $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, $lastDistanceChosen, 'meteor'); // destinations for this saucer, this card, in the meteor direction
									}
									elseif($distanceType == 1)
									{ // played a 2 card
										$result[$distanceType]['directions']['meteor'] = $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 2, 'meteor'); // destinations for this saucer, this card, in the sun direction

										if($this->doesSaucerHaveUpgradePlayed($color, "Hyperdrive"))
										{ // this player has Hyperdrive
											$result[$distanceType]['directions']['meteor'] = array_merge($result[$distanceType]['directions']['meteor'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 4, 'meteor')); // destinations for this saucer, this card, in the sun direction
										}
									}
									elseif($distanceType == 2)
									{ // played a 3 card
										$result[$distanceType]['directions']['meteor'] = $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 3, 'meteor'); // destinations for this saucer, this card, in the sun direction

										if($this->doesSaucerHaveUpgradePlayed($color, "Hyperdrive"))
										{ // this player has Hyperdrive
											$result[$distanceType]['directions']['meteor'] = array_merge($result[$distanceType]['directions']['meteor'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 6, 'meteor')); // destinations for this saucer, this card, in the sun direction
										}
									}
									elseif($distanceType == 0)
									{ // played a 0-5 card

										$result[$distanceType]['directions']['meteor'] = $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 0, 'meteor'); // destinations for this saucer, this card, in the sun direction
										$result[$distanceType]['directions']['meteor'] = array_merge($result[$distanceType]['directions']['meteor'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 1, 'meteor')); // destinations for this saucer, this card, in the sun direction
										$result[$distanceType]['directions']['meteor'] = array_merge($result[$distanceType]['directions']['meteor'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 2, 'meteor')); // destinations for this saucer, this card, in the sun direction
										$result[$distanceType]['directions']['meteor'] = array_merge($result[$distanceType]['directions']['meteor'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 3, 'meteor')); // destinations for this saucer, this card, in the sun direction
										$result[$distanceType]['directions']['meteor'] = array_merge($result[$distanceType]['directions']['meteor'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 4, 'meteor')); // destinations for this saucer, this card, in the sun direction
										$result[$distanceType]['directions']['meteor'] = array_merge($result[$distanceType]['directions']['meteor'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 5, 'meteor')); // destinations for this saucer, this card, in the sun direction

										if($this->doesSaucerHaveUpgradePlayed($color, "Hyperdrive"))
										{ // this player has Hyperdrive
											$result[$distanceType]['directions']['meteor'] = array_merge($result[$distanceType]['directions']['meteor'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 6, 'meteor')); // destinations for this saucer, this card, in the sun direction
											$result[$distanceType]['directions']['meteor'] = array_merge($result[$distanceType]['directions']['meteor'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 8, 'meteor')); // destinations for this saucer, this card, in the sun direction
											$result[$distanceType]['directions']['meteor'] = array_merge($result[$distanceType]['directions']['meteor'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 10, 'meteor')); // destinations for this saucer, this card, in the sun direction
										}
									}
								}

								if($specificDirection == '' || $specificDirection == 'constellation')
								{
									//throw new feException( "constallation specificDirection:$specificDirection" );
									
									if($usingBooster)
									{ // we are using a booster

										$result[$distanceType]['directions']['constellation'] = $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, $originalDistance, 'constellation'); // destinations for this saucer, this card, in the constellation direction
										
										if($this->doesSaucerHaveUpgradePlayed($color, "Boost Amplifier"))
										{ // this player has Boost Amplifier
											if($originalDistance != 1)
											{
												$result[$distanceType]['directions']['constellation'] = array_merge($result[$distanceType]['directions']['constellation'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 1, 'constellation')); // destinations for this saucer, this card, in the constellation direction
											}

											if($originalDistance != 2)
											{
												$result[$distanceType]['directions']['constellation'] = array_merge($result[$distanceType]['directions']['constellation'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 2, 'constellation')); // destinations for this saucer, this card, in the constellation direction
											}

											if($originalDistance != 3)
											{
												$result[$distanceType]['directions']['constellation'] = array_merge($result[$distanceType]['directions']['constellation'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 3, 'constellation')); // destinations for this saucer, this card, in the constellation direction
											}
											
											if($originalDistance != 4)
											{
												$result[$distanceType]['directions']['constellation'] = array_merge($result[$distanceType]['directions']['constellation'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 4, 'constellation')); // destinations for this saucer, this card, in the constellation direction
											}
											
											if($originalDistance != 5)
											{
												$result[$distanceType]['directions']['constellation'] = array_merge($result[$distanceType]['directions']['constellation'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 5, 'constellation')); // destinations for this saucer, this card, in the constellation direction
											}
											
											if($originalDistance != 6)
											{
												$result[$distanceType]['directions']['constellation'] = array_merge($result[$distanceType]['directions']['constellation'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 6, 'constellation')); // destinations for this saucer, this card, in the constellation direction
											}

											if($this->doesSaucerHaveUpgradePlayed($color, "Hyperdrive"))
											{ // this player has Hyperdrive

												if($originalDistance != 8)
												{
													$result[$distanceType]['directions']['constellation'] = array_merge($result[$distanceType]['directions']['constellation'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 8, 'constellation')); // destinations for this saucer, this card, in the constellation direction
												}
												
												if($originalDistance != 10)
												{
													$result[$distanceType]['directions']['constellation'] = array_merge($result[$distanceType]['directions']['constellation'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 10, 'constellation')); // destinations for this saucer, this card, in the constellation direction
												}
											
												if($originalDistance != 12)
												{
													$result[$distanceType]['directions']['constellation'] = array_merge($result[$distanceType]['directions']['constellation'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 12, 'constellation')); // destinations for this saucer, this card, in the constellation direction
												}
											}
										}
									}
									elseif($usingAccelerator)
									{ // they are using an accelerator

										// just give them the option to go that distance... not what they chose for their move card
										$lastDistanceChosen = $this->getSaucerLastDistance($color);
										$result[$distanceType]['directions']['constellation'] = $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, $lastDistanceChosen, 'constellation'); // destinations for this saucer, this card, in the constellation direction
									}
									elseif($distanceType == 1)
									{ // played a 2 card
										$result[$distanceType]['directions']['constellation'] = $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 2, 'constellation'); // destinations for this saucer, this card, in the sun direction

										if($this->doesSaucerHaveUpgradePlayed($color, "Hyperdrive"))
										{ // this player has Hyperdrive
											$result[$distanceType]['directions']['constellation'] = array_merge($result[$distanceType]['directions']['constellation'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 4, 'constellation')); // destinations for this saucer, this card, in the sun direction
										}
									}
									elseif($distanceType == 2)
									{ // played a 3 card
										$result[$distanceType]['directions']['constellation'] = $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 3, 'constellation'); // destinations for this saucer, this card, in the sun direction
										//throw new feException( "constallation distanceType:$distanceType" );
										if($this->doesSaucerHaveUpgradePlayed($color, "Hyperdrive"))
										{ // this player has Hyperdrive
											//throw new feException( "hyperdrive constallation distanceType:$distanceType" );
											$result[$distanceType]['directions']['constellation'] = array_merge($result[$distanceType]['directions']['constellation'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 6, 'constellation')); // destinations for this saucer, this card, in the sun direction
										}
									}
									elseif($distanceType == 0)
									{ // played a 0-5 card

										$result[$distanceType]['directions']['constellation'] = $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 0, 'constellation'); // destinations for this saucer, this card, in the sun direction
										$result[$distanceType]['directions']['constellation'] = array_merge($result[$distanceType]['directions']['constellation'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 1, 'constellation')); // destinations for this saucer, this card, in the sun direction
										$result[$distanceType]['directions']['constellation'] = array_merge($result[$distanceType]['directions']['constellation'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 2, 'constellation')); // destinations for this saucer, this card, in the sun direction
										$result[$distanceType]['directions']['constellation'] = array_merge($result[$distanceType]['directions']['constellation'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 3, 'constellation')); // destinations for this saucer, this card, in the sun direction
										$result[$distanceType]['directions']['constellation'] = array_merge($result[$distanceType]['directions']['constellation'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 4, 'constellation')); // destinations for this saucer, this card, in the sun direction
										$result[$distanceType]['directions']['constellation'] = array_merge($result[$distanceType]['directions']['constellation'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 5, 'constellation')); // destinations for this saucer, this card, in the sun direction

										if($this->doesSaucerHaveUpgradePlayed($color, "Hyperdrive"))
										{ // this player has Hyperdrive
											$result[$distanceType]['directions']['constellation'] = array_merge($result[$distanceType]['directions']['constellation'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 6, 'constellation')); // destinations for this saucer, this card, in the sun direction
											$result[$distanceType]['directions']['constellation'] = array_merge($result[$distanceType]['directions']['constellation'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 8, 'constellation')); // destinations for this saucer, this card, in the sun direction
											$result[$distanceType]['directions']['constellation'] = array_merge($result[$distanceType]['directions']['constellation'], $this->getMoveDestinationsInDirection($color, $saucerX, $saucerY, 10, 'constellation')); // destinations for this saucer, this card, in the sun direction
										}
									}
								}

								//$countSun = count($result[$distanceType]['directions']['sun']);
								//throw new feException( "countSun:$countSun" );

						}
				}

				//$count = count($result);
				//throw new feException( "result Count:$count" );

				/*
				$countDirections = 0;
				foreach($result as $moveCard)
				{
					foreach($moveCard as $directions)
					{
						$arrayKeys = array_keys($directions);
						foreach($arrayKeys as $directionKey)
						{
							$countDirections++;
							echo($directionKey);
							echo("<br>");
						}
					}
				}
				//throw new feException( "result Count Directions:$countDirections" );
				*/
				
				return $result;
		}

		// Returns an array of spaces starting at the startRow, startColumn in a specific $direction for the specified $distance.
		function getMoveDestinationsInDirection($saucerColor, $startColumn, $startRow, $distance, $direction)
		{
				$result = array();
//throw new feException( "distanceType: $distanceType direction: $direction");
				
								switch($direction)
								{
										case 'sun':
												$row = $startRow - $distance; // default
												for ($x = ($startRow - 1); $x >= ($startRow - $distance); $x--) 
												{ // second part is the CONTINUATION CONDITION not the ENDING CONDITION

													
												  	$spaceType = $this->getBoardSpaceType($startColumn, $x);
													  //echo("($startColumn,$x):$spaceType");
													  //echo("<br>");
														if($spaceType == "S")
														{ // found an accelerator

																// stop at the accelerator
																$row = $x;
																break; // exit the loop
														}
												}

												$spaceTypeAfter = $this->getBoardSpaceType($startColumn, $row);
												if($spaceTypeAfter != "S" && $row < 0)
												{ // went off the board
														$row = 0;
												}

												$column = $startColumn;

												$space = array();
												$space['row'] = $row;
												$space['column'] = $column;
												array_push($result, $space); // add this space to the list of move destinations

										break;

										case 'asteroids':

												$row = $startRow;

												$column = $startColumn + $distance; // default
												//throw new feException( "saucerColor:$saucerColor row:$row column:$column");
												for ($y = ($startColumn + 1); $y <= ($startColumn + $distance); $y++) 
												{ // second part is the CONTINUATION CONDIATION not the ENDING CONDITION
														$spaceType = $this->getBoardSpaceType($y, $startRow);
//if($saucerColor == '753bbd' && $spaceType == 'S')
/*
if($saucerColor == '228848')
{
echo("($startRow,$y):$spaceType");
echo("<br>");
}
*/
//if($spaceType == 'S')
//{
//throw new feException( "saucerColor:$saucerColor spaceType:$spaceType row:$row column:$column");
//}
														if($spaceType == "S")
														{ // found an accelerator
//throw new feException( "saucerColor:$saucerColor row:$row column:$column");
																// stop at the accelerator
																$column = $y;
																break; // exit the loop
														}
												}

												$maxColumns = $this->getMaxColumns();
												$spaceTypeAfter = $this->getBoardSpaceType($column, $startRow);
												if($spaceTypeAfter != "S" && $column > $maxColumns)
												{ // went off the board

														$column = $maxColumns;
												}

												$space = array();
												$space['row'] = $row;
												$space['column'] = $column;
												array_push($result, $space); // add this space to the list of move destinations

										break;

										case 'meteor':
										if($saucerColor == 'b83a4b')
										{
											//throw new feException( "saucerColor:$saucerColor startRow:$startRow startColumn:$startColumn distance:$distance");
										}
												$row = $startRow + $distance;
												for ($x = ($startRow + 1); $x <= ($startRow + $distance); $x++) 
												{ // second part is the CONTINUATION CONDIATION not the ENDING CONDITION
														$spaceType = $this->getBoardSpaceType($startColumn, $x);
														//if($saucerColor == 'b83a4b')
														//{
														//throw new feException( "saucerColor:$saucerColor x:$x startColumn:$startColumn spaceType:$spaceType");
														//}
														/*
														if($saucerColor == '228848')
														{
														echo("($startColumn,$x):$spaceType");
														echo("<br>");
														}
														*/
														if($spaceType == "S")
														{ // found an accelerator

																// stop at the accelerator
																$row = $x;
																break; // exit the loop
														}
												}

												$maxRows = $this->getMaxRows();
												$spaceTypeAfter = $this->getBoardSpaceType($startColumn, $row);
												if($spaceTypeAfter != "S" && $row > $maxRows)
												{ // went off the board

														$row = $maxRows;
												}

												$column = $startColumn;

												$space = array();
												$space['row'] = $row;
												$space['column'] = $column;
												array_push($result, $space); // add this space to the list of move destinations

										break;

										case 'constellation':
												$row = $startRow;

												$column = $startColumn - $distance;
												//throw new feException( "saucerColor:$saucerColor row:$row column:$column startColumn:$startColumn offset:$offset");
												for ($y = ($startColumn - 1); $y >= ($startColumn - $distance); $y--) 
												{ // second part is the CONTINUATION CONDIATION not the ENDING CONDITION
													//throw new feException( "y:$y");
													$spaceType = $this->getBoardSpaceType($y, $startRow);
													
													//echo("($y,$startRow):$spaceType");
													//echo("<br>");
														
														
														if($spaceType == "S")
														{ // found an accelerator

																// stop at the accelerator
																$column = $y;
																break; // exit the loop
														}
												}

												$spaceTypeAfter = $this->getBoardSpaceType($column, $startRow);
												if($spaceTypeAfter != "S" && $column < 0)
												{ // went off the board

														$column = 0;
												}

												$space = array();
												$space['row'] = $row;
												$space['column'] = $column;
												array_push($result, $space); // add this space to the list of move destinations

										break;

										default:
											throw new feException( "Invalid direction type: $direction");
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
				return self::getObjectListFromDB( "SELECT * FROM garment ORDER BY garment_color" );
		}

		function getAllSaucers()
		{
				return self::getObjectListFromDB( "SELECT *
																					 FROM ostrich ORDER BY ostrich_owner, ostrich_color" );
		}

		function getAllCrewmemberSetColors()
		{
				return self::getObjectListFromDB( "SELECT DISTINCT garment_color
																				 FROM garment" );
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

		function getPulseCannonSaucers()
		{
				$result = array();
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				$allOtherSaucers = self::getObjectListFromDB( "SELECT ostrich_color, ostrich_owner
																					 FROM ostrich WHERE ostrich_color<>'$saucerWhoseTurnItIs' ORDER BY ostrich_owner" );

				$myRow = $this->getSaucerXLocation($saucerWhoseTurnItIs);
				$myColumn = $this->getSaucerYLocation($saucerWhoseTurnItIs);

				foreach( $allOtherSaucers as $saucer )
				{
						$saucerColor = $saucer['ostrich_color'];
						$saucerColorText = $this->convertColorToText($saucerColor);

						$saucerDetails = array();
						$saucerDetails['saucerColor'] = $saucerColor;
						$saucerDetails['saucerColorText'] = $saucerColorText;

						$theirRow = $this->getSaucerXLocation($saucerColor);
						$theirColumn = $this->getSaucerYLocation($saucerColor);

						$isCrashed = $this->isSaucerCrashed($saucerColor);
						if(!$isCrashed && ($myRow == $theirRow || $myColumn == $theirColumn))
						{ // they are not crashed and they are in my row or column
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

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Pulse Cannon'))
				{
						$upgradeArray = array();
						$upgradeArray['collectorNumber'] = 4;
						$upgradeArray['upgradeName'] = $this->getUpgradeTitleFromCollectorNumber(4);

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

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Quake Maker'))
				{
						$upgradeArray = array();
						$upgradeArray['collectorNumber'] = 18;
						$upgradeArray['upgradeName'] = $this->getUpgradeTitleFromCollectorNumber(18);

						array_push($result, $upgradeArray);
				}

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Organic Triangulator'))
				{
						$upgradeArray = array();
						$upgradeArray['collectorNumber'] = 26;
						$upgradeArray['upgradeName'] = $this->getUpgradeTitleFromCollectorNumber(26);

						array_push($result, $upgradeArray);
				}

				return $result;
		}

		function doesPlayerHaveAnyEndOfTurnUpgradesToActivate($saucerColor)
		{
				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Wormhole Generator') &&
				   $this->getUpgradeTimesActivatedThisRound($saucerColor, 'Wormhole Generator') < 1 &&
					 $this->getAskedToActivateUpgrade($saucerColor, 'Wormhole Generator') == false &&
					 $this->isUpgradePlayable($saucerColor, 'Wormhole Generator'))
				{ // they have played this upgrade but they have not yet activated it
//throw new feException( "wormhole");
					if(!$this->isSaucerCrashed($saucerColor))
					{ // they are not crashed

						return true;
					}
				}

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Pulse Cannon') &&
				   $this->getUpgradeTimesActivatedThisRound($saucerColor, 'Pulse Cannon') < 2 &&
					 $this->getAskedToActivateUpgrade($saucerColor, 'Pulse Cannon') == false &&
					 $this->isUpgradePlayable($saucerColor, 'Pulse Cannon'))
				{ // they have played this upgrade but they have not yet activated it
//throw new feException( "pulse");
						if($this->isSaucerInRowOrColumnOfSaucer($saucerColor))
						{ // there is another saucer in the row or column of our saucer
							//throw new feException( "true dat");
							
							if(!$this->isSaucerCrashed($saucerColor))
							{ // they are not crashed
								return true;
							}
						}
				}


				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Afterburner') &&
				   $this->getUpgradeTimesActivatedThisRound($saucerColor, 'Afterburner') < 1 &&
					 $this->getAskedToActivateUpgrade($saucerColor, 'Afterburner') == false &&
					 $this->isUpgradePlayable($saucerColor, 'Afterburner'))
				{ // they have played this upgrade, they have not yet activated it, and they have not yet indicated whether they want to activate it
//throw new feException( "after");
						if(!$this->isSaucerCrashed($saucerColor))
						{ // they are not crashed

								return true;
						}
				}

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Tractor Beam') &&
				   $this->getUpgradeTimesActivatedThisRound($saucerColor, 'Tractor Beam') < 1 &&
					 $this->getAskedToActivateUpgrade($saucerColor, 'Tractor Beam') == false &&
					 $this->isUpgradePlayable($saucerColor, 'Tractor Beam'))
				{ // they have played this upgrade, they have not yet activated it, and they have not yet indicated whether they want to activate it
//throw new feException( "tractor");
						if(!$this->isSaucerCrashed($saucerColor))
						{ // they are not crashed

								$crewmembersWithinTractor = $this->getCrewmembersWithinTractorBeam($saucerColor);
								if(count($crewmembersWithinTractor) > 0)
								{ // there is a crewmember within 3 of saucer and on row or column

										return true;
								}
						}
				}

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Saucer Teleporter') &&
				   $this->getUpgradeTimesActivatedThisRound($saucerColor, 'Saucer Teleporter') < 1 &&
					 $this->getAskedToActivateUpgrade($saucerColor, 'Saucer Teleporter') == false &&
					 $this->isUpgradePlayable($saucerColor, 'Saucer Teleporter'))
				{ // they have played this upgrade but they have not yet activated it
//throw new feException( "teleporter");
						if(!$this->isSaucerCrashed($saucerColor))
						{ // they are not crashed

								return true;
						}
				}


				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Cloaking Device') &&
				   $this->getUpgradeTimesActivatedThisRound($saucerColor, 'Cloaking Device') < 1 &&
					 $this->getAskedToActivateUpgrade($saucerColor, 'Cloaking Device') == false &&
					 $this->isUpgradePlayable($saucerColor, 'Cloaking Device'))
				{ // they have played this upgrade but they have not yet activated it

					//$cloakingDeviceTimesActivated = $this->getUpgradeTimesActivatedThisRound($saucerColor, 'Cloaking Device');
					//throw new feException( "true saucer:$saucerColor cloakingDeviceTimesActivated:$cloakingDeviceTimesActivated");
//throw new feException( "cloaking");

						if(!$this->isSaucerCrashed($saucerColor))
						{ // they are not crashed

								return true;
						}
				}


				//$isPlayed = $this->doesSaucerHaveUpgradePlayed($saucerColor, 'Distress Signaler');
				//$timesActivated = $this->getUpgradeTimesActivatedThisRound($saucerColor, 'Distress Signaler');
				//$askedToActivate =  $this->getAskedToActivateUpgrade($saucerColor, 'Distress Signaler');
				//$isPlayable = $this->isUpgradePlayable($saucerColor, 'Distress Signaler');
				//throw new feException( "isPlayed:$isPlayed timesActivated:$timesActivated askedToActivate:$askedToActivate isPlayable:$isPlayable");
				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Distress Signaler') &&
				   $this->getUpgradeTimesActivatedThisRound($saucerColor, 'Distress Signaler') < 1 &&
					 $this->getAskedToActivateUpgrade($saucerColor, 'Distress Signaler') == false &&
					 $this->isUpgradePlayable($saucerColor, 'Distress Signaler'))
				{ // they have played this upgrade, they have not yet activated it, and they have not yet indicated whether they want to activate it
//throw new feException( "distress");
						$distressSignalableCrewmembers = $this->getDistressSignalableTakeCrewmembers($saucerColor);
						//$distressCount = count($distressSignalableCrewmembers);
						//throw new feException( "distressCount:$distressCount");
						if(count($distressSignalableCrewmembers) > 0)
						{
								return true;
						}
				}

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Landing Legs') &&
				   $this->getUpgradeTimesActivatedThisRound($saucerColor, 'Landing Legs') < 1 &&
					 $this->getAskedToActivateUpgrade($saucerColor, 'Landing Legs') == false &&
					 $this->isUpgradePlayable($saucerColor, 'Landing Legs'))
				{ // they have played this upgrade, they have not yet activated it, and they have not yet indicated whether they want to activate it
//throw new feException( "landing");
						if(!$this->isSaucerCrashed($saucerColor))
						{ // they are not crashed

								return true;
						}
				}

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Quake Maker') &&
				   $this->getUpgradeTimesActivatedThisRound($saucerColor, 'Quake Maker') < 1 &&
					 $this->getAskedToActivateUpgrade($saucerColor, 'Quake Maker') == false &&
					 $this->isUpgradePlayable($saucerColor, 'Quake Maker'))
				{ // they have played this upgrade but they have not yet activated it
//throw new feException( "quake");
						return true;
				}

				//$isPlayed = $this->doesSaucerHaveUpgradePlayed($saucerColor, 'Organic Triangulator');
				//$timesActivated = $this->getUpgradeTimesActivatedThisRound($saucerColor, 'Organic Triangulator');
				//$askedToActivate =  $this->getAskedToActivateUpgrade($saucerColor, 'Organic Triangulator');
				//$isPlayable = $this->isUpgradePlayable($saucerColor, 'Organic Triangulator');
				//throw new feException( "isPlayed:$isPlayed timesActivated:$timesActivated askedToActivate:$askedToActivate isPlayable:$isPlayable");
				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Organic Triangulator') &&
				   $this->getUpgradeTimesActivatedThisRound($saucerColor, 'Organic Triangulator') < 1 &&
					 $this->getAskedToActivateUpgrade($saucerColor, 'Organic Triangulator') == false &&
					 $this->isUpgradePlayable($saucerColor, 'Organic Triangulator'))
				{ // they have played this upgrade, they have not yet activated it, and they have not yet indicated whether they want to activate it
//throw new feException( "after");

					if(!$this->didSaucerPickUpOrStealCrewmemberThisTurn($saucerColor))
					{ // the saucer did not pick up a crewmember

						//throw new feException( "true dat");
						if(!$this->isSaucerCrashed($saucerColor))
						{ // they are not crashed

								return true;
						}
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

						case 4:
								return clienttranslate( 'Pulse Cannon');

						case 5:
								return clienttranslate( 'Tractor Beam');

						case 6:
								return clienttranslate( 'Saucer Teleporter');

						case 7:
								return clienttranslate( 'Cloaking Device');

						case 8:
							return clienttranslate( 'Waste Accelerator');

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
								return clienttranslate( 'Kinetic Siphon');

						case 15:
								return clienttranslate( 'Cargo Hold');

						case 16:
								return clienttranslate( 'Proximity Mines');

						case 17:
								return clienttranslate( 'Landing Legs');

						case 18:
								return clienttranslate( 'Quake Maker');

						case 19:
								return clienttranslate( 'Rotational Stabilizer');

						case 20:
							return clienttranslate( 'Airlock');

						case 24:
							return clienttranslate( 'Acceleration Regulator');

						case 25:
							return clienttranslate( 'Boost Amplifier');

						case 26:
							return clienttranslate( 'Organic Triangulator');
				}
		}

		function getUpgradeEffectFromCollectorNumber($collectorNumber)
		{
				switch($collectorNumber)
				{
						// Blast Off Thrusters
						case 1:
								return clienttranslate( 'At the start of your turn, move 1 space (but not diagonally) onto an empty space.');

						// Wormhole Generator
						case 2:
								return clienttranslate( 'At the end of your turn, swap locations with any Saucer.');

						// Afterburner
						case 3:
								return clienttranslate( 'At the end of your turn, move onto any empty space in your row or column.');

						// Pulse Cannon
						case 4:
								return clienttranslate( 'At the end of your turn, push a Saucer in your row or column 1 space away from you.');

						// Tractor Beam
						case 5:
								return clienttranslate( 'At the end of your turn, pick up a Crewmember on your row or column up to 3 spaces away from you.');

						// Saucer Teleporter
						case 6:
								return clienttranslate( 'At the end of your turn, if you have not crashed, move to any empty Crash Site.');

						// Cloaking Device
						case 7:
								return clienttranslate( 'At the end of your turn, remove your Saucer from the board and place it at the end of the round.');

						// Waste Accelerator
						case 8:
							return clienttranslate( 'Once on your turn, use an empty Crash Site as an Accelerator.');

						// Hyperdrive
						case 9:
								return clienttranslate( 'Double your movement.');

						// Scavenger Bot
						case 10:
								return clienttranslate( 'When your Saucer is placed, take a Booster and an Energy.');

						// Distress Signaler
						case 11:
								return clienttranslate( 'At the end of your turn, take a Crewmember of your color from any Saucer and give them one of the same type.');

						// Time Machine
						case 12:
								return clienttranslate( 'Choose your Move Card direction after you reveal it.');

						// Regeneration Gateway
						case 13:
								return clienttranslate( 'When your Saucer is placed, you choose the Crash Site.');

						// Kinetic Siphon
						case 14:
								return clienttranslate( 'Once on your turn, when you push a Saucer, take a Booster.');

						// Cargo Hold
						case 15:
								return clienttranslate( 'Take 3 Boosters.');

						// Proximity Mines
						case 16:
								return clienttranslate( 'Crash any Saucer you collide with instead of pushing it.');

						// Landing Legs
						case 17:
								return clienttranslate( 'At the end of your turn, move 1 space in any direction (not diagonally).');

						// Quake Maker
						case 18:
							return clienttranslate( 'At the end of your turn, rotate a tile to any orientation.');

						// Rotational Stabilizer
						case 19:
								return clienttranslate( 'After the Probe player takes their turn, you choose whether the turn order is clockwise or counter-clockwise.');

						// Airlock
						case 20:
								return clienttranslate( 'When you pick up a Crewmember, you may exchange it with any other Crewmember on the board.');

						// Acceleration Regulator
						case 24:
							return clienttranslate( 'On your turn, move 1-4 off each Accelerator.');

						// Acceleration Regulator
						case 25:
							return clienttranslate( 'Move 1-6 when you use a Booster.');

						// Organic Triangulator
						case 26:
							return clienttranslate( 'At the end of your turn where you did not pick up or steal a Crewmember, move to any empty space.');
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

				// Pulse Cannon
				$result[4] = array();
				$result[4]['name'] = $this->getUpgradeTitleFromCollectorNumber(4);
				$result[4]['effect'] = $this->getUpgradeEffectFromCollectorNumber(4);

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

				// Waste Accelerator
				$result[8] = array();
				$result[8]['name'] = $this->getUpgradeTitleFromCollectorNumber(8);
				$result[8]['effect'] = $this->getUpgradeEffectFromCollectorNumber(8);

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

				// Kinetic Siphon
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

				// Quake Maker
				$result[18] = array();
				$result[18]['name'] = $this->getUpgradeTitleFromCollectorNumber(18);
				$result[18]['effect'] = $this->getUpgradeEffectFromCollectorNumber(18);

				// Rotational Stabilizer
				$result[19] = array();
				$result[19]['name'] = $this->getUpgradeTitleFromCollectorNumber(19);
				$result[19]['effect'] = $this->getUpgradeEffectFromCollectorNumber(19);

				// Airlock
				$result[20] = array();
				$result[20]['name'] = $this->getUpgradeTitleFromCollectorNumber(20);
				$result[20]['effect'] = $this->getUpgradeEffectFromCollectorNumber(20);

				// Acceleration Regulator
				$result[24] = array();
				$result[24]['name'] = $this->getUpgradeTitleFromCollectorNumber(24);
				$result[24]['effect'] = $this->getUpgradeEffectFromCollectorNumber(24);

				// Boost Amplifier
				$result[25] = array();
				$result[25]['name'] = $this->getUpgradeTitleFromCollectorNumber(25);
				$result[25]['effect'] = $this->getUpgradeEffectFromCollectorNumber(25);

				// Organic Triangulator
				$result[26] = array();
				$result[26]['name'] = $this->getUpgradeTitleFromCollectorNumber(26);
				$result[26]['effect'] = $this->getUpgradeEffectFromCollectorNumber(26);

				return $result;
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

		function getCrewmembersWithinTractorBeam($saucerColor)
		{
				$crewmembersWithinTractor = array();

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

						if($distanceAwayX + $distanceAwayY <= 3)
						{ // it is within 3
							if($crewmemberX == $xLocation || $crewmemberY == $yLocation)
							{ // they are on their row or column
								array_push($crewmembersWithinTractor, $crewmember);
							}
						}
				}

				return $crewmembersWithinTractor;
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
						$this->getUpgradeTimesActivatedThisRound($saucerColor, 'Blast Off Thrusters') < 1 &&
						$this->isUpgradePlayable($saucerColor, 'Blast Off Thrusters'))
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
						$this->getUpgradeTimesActivatedThisRound($saucerColor, 'Wormhole Generator') < 1 &&
						$this->isUpgradePlayable($saucerColor, 'Wormhole Generator'))
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

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Pulse Cannon') &&
						$this->getUpgradeTimesActivatedThisRound($saucerColor, 'Pulse Cannon') < 1 &&
						$this->isUpgradePlayable($saucerColor, 'Pulse Cannon'))
				{ // they have played Pulse Cannon but they have not yet activated it this round

						if(!$this->isSaucerCrashed($saucerColor))
						{ // they are not crashed

								if($this->isSaucerInRowOrColumnOfSaucer($saucerColor))
								{ // there is another saucer in the row or column of our saucer

										$result[$index] = array();
										$result[$index]['buttonId'] = 'upgradeButton_4';
										$result[$index]['buttonLabel'] = $this->getUpgradeTitleFromCollectorNumber(4);
										$result[$index]['hoverOverText'] = '';
										$result[$index]['actionName'] = 'activateUpgrade';
										$result[$index]['isDisabled'] = false;
										$result[$index]['makeRed'] = false;

										$index++;
								}
						}
				}

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Afterburner') &&
						$this->getUpgradeTimesActivatedThisRound($saucerColor, 'Afterburner') < 1 &&
						$this->isUpgradePlayable($saucerColor, 'Afterburner'))
				{ // they have played Afterburner but they have not yet activated it this round

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
						$this->getUpgradeTimesActivatedThisRound($saucerColor, 'Tractor Beam') < 1 &&
						$this->isUpgradePlayable($saucerColor, 'Tractor Beam'))
				{ // they have played Tractor Beam but they have not yet activated it this round

						if(!$this->isSaucerCrashed($saucerColor))
						{ // they are not crashed

								$crewmembersWithinTractor = $this->getCrewmembersWithinTractorBeam($saucerColor);
								if(count($crewmembersWithinTractor) > 0)
								{ // there is a crewmember within 3 of saucer and on row or column

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
				}

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Saucer Teleporter') &&
						$this->getUpgradeTimesActivatedThisRound($saucerColor, 'Saucer Teleporter') < 1 &&
						$this->isUpgradePlayable($saucerColor, 'Saucer Teleporter'))
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
						$this->getUpgradeTimesActivatedThisRound($saucerColor, 'Cloaking Device') < 1 &&
						$this->isUpgradePlayable($saucerColor, 'Cloaking Device'))
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

				//$isPlayed = $this->doesSaucerHaveUpgradePlayed($saucerColor, 'Distress Signaler');
				//$timesActivated = $this->getUpgradeTimesActivatedThisRound($saucerColor, 'Distress Signaler');
				//$isPlayable = $this->isUpgradePlayable($saucerColor, 'Distress Signaler');
				//throw new feException( "isPlayed:$isPlayed timesActivated:$timesActivated isPlayable:$isPlayable");
				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Distress Signaler') &&
						$this->getUpgradeTimesActivatedThisRound($saucerColor, 'Distress Signaler') < 1 &&
						$this->isUpgradePlayable($saucerColor, 'Distress Signaler'))
				{ // they have played Distress Signaler but they have not yet activated it this round

						$distressSignalableCrewmembers = $this->getDistressSignalableTakeCrewmembers($saucerColor);
						//$countDistress = count($distressSignalableCrewmembers);

						//throw new feException( "countDistress:$countDistress");
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
						$this->getUpgradeTimesActivatedThisRound($saucerColor, 'Landing Legs') < 1 &&
						$this->isUpgradePlayable($saucerColor, 'Landing Legs'))
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

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Quake Maker') &&
						$this->getUpgradeTimesActivatedThisRound($saucerColor, 'Quake Maker') < 1 &&
						$this->isUpgradePlayable($saucerColor, 'Quake Maker'))
				{ // they have played Quake Maker but they have not yet activated it this round

								$result[$index] = array();
								$result[$index]['buttonId'] = 'upgradeButton_18';
								$result[$index]['buttonLabel'] = $this->getUpgradeTitleFromCollectorNumber(18);
								$result[$index]['hoverOverText'] = '';
								$result[$index]['actionName'] = 'activateUpgrade';
								$result[$index]['isDisabled'] = false;
								$result[$index]['makeRed'] = false;

								$index++;

				}

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Organic Triangulator') &&
						$this->getUpgradeTimesActivatedThisRound($saucerColor, 'Organic Triangulator') < 1 &&
						$this->isUpgradePlayable($saucerColor, 'Organic Triangulator'))
				{ // they have played Organic Triangulator but they have not yet activated it this round

						if(!$this->isSaucerCrashed($saucerColor))
						{ // they are not crashed

								$result[$index] = array();
								$result[$index]['buttonId'] = 'upgradeButton_26';
								$result[$index]['buttonLabel'] = $this->getUpgradeTitleFromCollectorNumber(26);
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
				$upgradesInDeck = $this->getUpgradesInDeck();

				/*
				if(count($upgradesInDeck) < 2)
				{ // we will be exhausting the deck

						// notify all players to reshuffle
						self::notifyAllPlayers( "reshuffleUpgrades", clienttranslate( 'Reshuffling the Upgrades Deck.' ), array(
		            'upgrades_in_deck' => $this->getAllUpgrades();
		        ));
				}
				*/

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

		// Crewmembers in one saucer that match the color of their friendly saucer in a 2-player game.
		// NOTE: These crewmembers don't necessarily have is_passible set (meaning the saucers passed by 
		// one another with it on board).
		function getPassableCrewmembersFromSaucer($saucerGiving)
		{
				$result = array();

				$saucerReceiving = $this->getPlayersOtherSaucer($saucerGiving);
//throw new feException( "getPassableCrewmembersFromSaucer saucerReceiving:$saucerReceiving");
				$saucerColorFriendlyGiver = $this->convertColorToHighlightedText($saucerGiving);
				$saucerColorFriendlyReceiver = $this->convertColorToHighlightedText($saucerReceiving);


				$allPassableCrewmembersFromCrashedSaucer = self::getObjectListFromDB( "SELECT garment_id, garment_color, garment_type, is_passable
																									FROM garment
																									WHERE garment_location='$saucerGiving' AND garment_color='$saucerReceiving'" );
//$countFound = count($allPassableCrewmembersFromCrashedSaucer);
//throw new feException( "getPassableCrewmembersFromSaucer countFound:$countFound");
				$crewmemberIndex = 0;
				foreach( $allPassableCrewmembersFromCrashedSaucer as $crewmember )
				{ // go through all this saucer's matching crewmembers

						$crewmemberColor = $crewmember['garment_color'];
						$crewmemberType = $this->convertGarmentTypeIntToString($crewmember['garment_type']);
						$crewmemberId = $crewmember['garment_id'];
						$isPassable = $crewmember['is_passable'];

						$result[$crewmemberIndex] = array();
						$result[$crewmemberIndex]['crewmemberType'] = $crewmemberType;
						$result[$crewmemberIndex]['crewmemberColor'] = $crewmemberColor;
						$result[$crewmemberIndex]['crewmemberId'] = $crewmemberId;
						$result[$crewmemberIndex]['isPassable'] = $isPassable;
						$crewmemberIndex++;
				}
				//$countReturning = count($result);
				//throw new feException( "getPassableCrewmembersFromSaucer countReturning:$countReturning");

				return $result;
		}

		function getStealableCrewmembersFromSaucer($crashedSaucer)
		{
				$currentPlayer = self::getCurrentPlayerId(); // Current Player = player who played the current player action (the one who made the AJAX request). Active Player = player whose turn it is.
				//$activePlayer = self::getActivePlayerId(); // Current Player = player who played the current player action (the one who made the AJAX request). Active Player = player whose turn it is.
				
				$result = array();
				$stealerSaucer = $this->getOstrichWhoseTurnItIs(); // the only time you can steal garments is if it's your turn so it's always this ostrich who gets to steal
				$stealerOwner = $this->getOwnerIdOfOstrich($stealerSaucer);

				$saucerColorFriendlyCrashed = $this->convertColorToHighlightedText($crashedSaucer);
				$saucerColorFriendlyStealer = $this->convertColorToHighlightedText($stealerSaucer);

				$totalCrewmembersOfStealer = $this->getSeatedCrewmembersForSaucer($stealerSaucer);
				$totalCrewmembersOfCrashed = $this->getSeatedCrewmembersForSaucer($crashedSaucer);

				// get offcolored crewmembers
				$allStealableCrewmembersFromCrashedSaucer = self::getObjectListFromDB( "SELECT garment_id, garment_color, garment_type
																									FROM garment
																									WHERE garment_location='$crashedSaucer' AND garment_color<>'$crashedSaucer'" );

				

				if($this->getNotifiedOfStealingOutcome($currentPlayer) == 0)
				{ // we have not yet sent this notification (since this is in an arg for a state, we will get multiple notifications if we do not do this)
					
					if($totalCrewmembersOfStealer > $totalCrewmembersOfCrashed)
					{ // stealer has more Crewmembers than the crashed saucer
							// notify this player (notify all will result in multiple message logs because this happens in args to a state) that stealer may not steal from crashed because they have more crewmembers
							self::notifyPlayer( $currentPlayer, "cannotSteal", clienttranslate( '${stealer_color} has more stationed Crewmembers than ${stealee_color} so they may not steal any from them.' ), array(
									'stealer_color' => $saucerColorFriendlyStealer,
									'stealee_color' => $saucerColorFriendlyCrashed
							) );

							$this->setNotifiedOfStealingOutcome($currentPlayer, 1);

							return $result;
					}
					elseif(count($allStealableCrewmembersFromCrashedSaucer) == 0)
					{ // they have nothing to steal

						// this player (notify all will result in multiple message logs because this happens in args to a state) 
						self::notifyPlayer( $currentPlayer, "nothingToSteal", clienttranslate( '${stealee_color} has nothing for ${stealer_color} to steal.' ), array(
							'stealer_color' => $saucerColorFriendlyStealer,
							'stealee_color' => $saucerColorFriendlyCrashed
						) );

						$this->setNotifiedOfStealingOutcome($currentPlayer, 1);

						return $result;
					}
				}

				
				$crewmemberIndex = 0;
				foreach( $allStealableCrewmembersFromCrashedSaucer as $crewmember )
				{ // go through all this saucer's off-colored crewmembers

						$crewmemberColor = $crewmember['garment_color'];
						$crewmemberType = $this->convertGarmentTypeIntToString($crewmember['garment_type']);
						$crewmemberId = $crewmember['garment_id'];

						$result[$crewmemberIndex] = array();
						$result[$crewmemberIndex]['crewmemberType'] = $crewmemberType;
						$result[$crewmemberIndex]['crewmemberColor'] = $crewmemberColor;
						$result[$crewmemberIndex]['crewmemberId'] = $crewmemberId;
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
							$crewmemberId = $garment['garment_id'];

							if($garmentColor != $ostrich)
							{ // we found an off-colored garment
									$result[$garmentIndex] = array();
									$result[$garmentIndex]['garmentType'] = $garmentType;
									$result[$garmentIndex]['garmentColor'] = $garmentColor;
									$result[$garmentIndex]['crewmemberId'] = $crewmemberId;
									
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
								if($spaceType != "S" && $spaceType != "D")
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
								if($spaceType != "S" && $spaceType != "D")
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
								if($spaceType != "S" && $spaceType != "D")
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
								if($spaceType != "S" && $spaceType != "D")
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
							$afterburnerMoves = $this->getAfterburnerMoves($saucerColor, $currentSaucerX, $currentSaucerY);

							//$validSpace = array_merge($validSpaces, $afterburnerMoves);
							return $afterburnerMoves;
						break;

						case "Organic Triangulator":
							$organicMoves = $this->getOrganicTriangulatorMoves($saucerColor, $currentSaucerX, $currentSaucerY);

							//$validSpace = array_merge($validSpaces, $afterburnerMoves);
							return $organicMoves;
						break;

						case "Acceleration Regulator":
							$result = array();

							// normally you just accelerate the distance you just moved
							$lastMovedDistance = $this->getSaucerLastDistance($saucerColor);

							// SUN
							$result[1]['directions']['sun'] = $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 1, 'sun'); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['sun'] = array_merge($result[1]['directions']['sun'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 2, 'sun')); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['sun'] = array_merge($result[1]['directions']['sun'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 3, 'sun')); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['sun'] = array_merge($result[1]['directions']['sun'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 4, 'sun')); // destinations for this saucer, this card, in the sun direction
							if($lastMovedDistance != 0 && $lastMovedDistance != 1 && $lastMovedDistance != 2 && $lastMovedDistance != 3 && $lastMovedDistance != 4)
							{
								$result[1]['directions']['sun'] = array_merge($result[1]['directions']['sun'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, $lastMovedDistance, 'sun')); // destinations for this saucer, this card, in the sun direction
							}
							if($this->doesSaucerHaveUpgradePlayed($saucerColor, "Hyperdrive"))
							{ // they have hyperdrive as well so we need to add 6 and 8 as options

								if($lastMovedDistance != 6)
								{ // the last distance is already 6 so we don't want to add another one

									// add 6
									$result[1]['directions']['sun'] = array_merge($result[1]['directions']['sun'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 6, 'sun'));
								}

								if($lastMovedDistance != 8)
								{ // the last distance is already 8 so we don't want to add another one

									// add 8
									$result[1]['directions']['sun'] = array_merge($result[1]['directions']['sun'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 8, 'sun'));
								}
							}


							// ASTEROIDS
							$result[1]['directions']['asteroids'] = $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 1, 'asteroids'); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['asteroids'] = array_merge($result[1]['directions']['asteroids'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 2, 'asteroids')); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['asteroids'] = array_merge($result[1]['directions']['asteroids'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 3, 'asteroids')); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['asteroids'] = array_merge($result[1]['directions']['asteroids'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 4, 'asteroids')); // destinations for this saucer, this card, in the sun direction
							if($lastMovedDistance != 0 && $lastMovedDistance != 1 && $lastMovedDistance != 2 && $lastMovedDistance != 3 && $lastMovedDistance != 4)
							{
								$result[1]['directions']['asteroids'] = array_merge($result[1]['directions']['asteroids'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, $lastMovedDistance, 'asteroids')); // destinations for this saucer, this card, in the sun direction
							}
							if($this->doesSaucerHaveUpgradePlayed($saucerColor, "Hyperdrive"))
							{ // they have hyperdrive as well so we need to add 6 and 8 as options

								if($lastMovedDistance != 6)
								{ // the last distance is already 6 so we don't want to add another one

									// add 6
									$result[1]['directions']['asteroids'] = array_merge($result[1]['directions']['asteroids'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 6, 'asteroids'));
								}

								if($lastMovedDistance != 8)
								{ // the last distance is already 8 so we don't want to add another one

									// add 8
									$result[1]['directions']['asteroids'] = array_merge($result[1]['directions']['asteroids'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 8, 'asteroids'));
								}
							}

							// METEOR
							$result[1]['directions']['meteor'] = $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 1, 'meteor'); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['meteor'] = array_merge($result[1]['directions']['meteor'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 2, 'meteor')); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['meteor'] = array_merge($result[1]['directions']['meteor'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 3, 'meteor')); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['meteor'] = array_merge($result[1]['directions']['meteor'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 4, 'meteor')); // destinations for this saucer, this card, in the sun direction
							if($lastMovedDistance != 0 && $lastMovedDistance != 1 && $lastMovedDistance != 2 && $lastMovedDistance != 3 && $lastMovedDistance != 4)
							{
								$result[1]['directions']['meteor'] = array_merge($result[1]['directions']['meteor'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, $lastMovedDistance, 'meteor')); // destinations for this saucer, this card, in the sun direction
							}
							if($this->doesSaucerHaveUpgradePlayed($saucerColor, "Hyperdrive"))
							{ // they have hyperdrive as well so we need to add 6 and 8 as options

								if($lastMovedDistance != 6)
								{ // the last distance is already 6 so we don't want to add another one

									// add 6
									$result[1]['directions']['meteor'] = array_merge($result[1]['directions']['meteor'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 6, 'meteor'));
								}

								if($lastMovedDistance != 8)
								{ // the last distance is already 8 so we don't want to add another one

									// add 8
									$result[1]['directions']['meteor'] = array_merge($result[1]['directions']['meteor'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 8, 'meteor'));
								}
							}

							// CONSTELLATION
							$result[1]['directions']['constellation'] = $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 1, 'constellation'); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['constellation'] = array_merge($result[1]['directions']['constellation'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 2, 'constellation')); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['constellation'] = array_merge($result[1]['directions']['constellation'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 3, 'constellation')); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['constellation'] = array_merge($result[1]['directions']['constellation'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 4, 'constellation')); // destinations for this saucer, this card, in the sun direction
							if($lastMovedDistance != 0 && $lastMovedDistance != 1 && $lastMovedDistance != 2 && $lastMovedDistance != 3 && $lastMovedDistance != 4)
							{
								$result[1]['directions']['constellation'] = array_merge($result[1]['directions']['constellation'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, $lastMovedDistance, 'constellation')); // destinations for this saucer, this card, in the sun direction
							}
							if($this->doesSaucerHaveUpgradePlayed($saucerColor, "Hyperdrive"))
							{ // they have hyperdrive as well so we need to add 6 and 8 as options

								if($lastMovedDistance != 6)
								{ // the last distance is already 6 so we don't want to add another one

									// add 6
									$result[1]['directions']['constellation'] = array_merge($result[1]['directions']['constellation'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 6, 'constellation'));
								}

								if($lastMovedDistance != 8)
								{ // the last distance is already 8 so we don't want to add another one

									// add 8
									$result[1]['directions']['constellation'] = array_merge($result[1]['directions']['constellation'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 8, 'constellation'));
								}
							}


							foreach( $result as $movesInDirection )
								{ // go through each move card for this saucer

										$directionsWithSpaces = $movesInDirection['directions'];
										//$count = count($spacesForCard);
										//throw new feException( "spacesForCard Count:$count" );

										foreach( $directionsWithSpaces as $direction => $directionWithSpaces )
										{ // go through each direction

												foreach( $directionWithSpaces as $space )
												{ // go through each space

														$column = $space['column'];
														$row = $space['row'];

														$formattedSpace = $column.'_'.$row;
														array_push($validSpaces, $formattedSpace);
												}
										}
								}

						break;

						case "Boost Amplifier":
							$result = array();

							// normally you just accelerate the distance you just moved
							$originalTurnDistance = $this->getSaucerOriginalTurnDistance($saucerColor);

							// SUN
							$result[1]['directions']['sun'] = $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 1, 'sun'); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['sun'] = array_merge($result[1]['directions']['sun'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 2, 'sun')); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['sun'] = array_merge($result[1]['directions']['sun'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 3, 'sun')); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['sun'] = array_merge($result[1]['directions']['sun'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 4, 'sun')); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['sun'] = array_merge($result[1]['directions']['sun'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 5, 'sun')); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['sun'] = array_merge($result[1]['directions']['sun'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 6, 'sun')); // destinations for this saucer, this card, in the sun direction
							if($originalTurnDistance != 0 && $originalTurnDistance != 1 && $originalTurnDistance != 2 && $originalTurnDistance != 3 && $originalTurnDistance != 4 && $originalTurnDistance != 5 && $originalTurnDistance != 6)
							{
								$result[1]['directions']['sun'] = array_merge($result[1]['directions']['sun'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, $originalTurnDistance, 'sun')); // destinations for this saucer, this card, in the sun direction
							}
							if($this->doesSaucerHaveUpgradePlayed($saucerColor, "Hyperdrive"))
							{ // they have hyperdrive as well so we need to add 6 and 8 as options

								if($originalTurnDistance != 8)
								{ // the last distance is already 8 so we don't want to add another one

									// add 8
									$result[1]['directions']['sun'] = array_merge($result[1]['directions']['sun'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 8, 'sun'));
								}

								if($originalTurnDistance != 10)
								{ // the last distance is already 10 so we don't want to add another one

									// add 10
									$result[1]['directions']['sun'] = array_merge($result[1]['directions']['sun'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 10, 'sun'));
								}

								if($originalTurnDistance != 12)
								{ // the last distance is already 12 so we don't want to add another one

									// add 12
									$result[1]['directions']['sun'] = array_merge($result[1]['directions']['sun'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 12, 'sun'));
								}
							}


							// ASTEROIDS
							$result[1]['directions']['asteroids'] = $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 1, 'asteroids'); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['asteroids'] = array_merge($result[1]['directions']['asteroids'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 2, 'asteroids')); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['asteroids'] = array_merge($result[1]['directions']['asteroids'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 3, 'asteroids')); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['asteroids'] = array_merge($result[1]['directions']['asteroids'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 4, 'asteroids')); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['asteroids'] = array_merge($result[1]['directions']['asteroids'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 5, 'asteroids')); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['asteroids'] = array_merge($result[1]['directions']['asteroids'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 6, 'asteroids')); // destinations for this saucer, this card, in the sun direction
							if($originalTurnDistance != 0 && $originalTurnDistance != 1 && $originalTurnDistance != 2 && $originalTurnDistance != 3 && $originalTurnDistance != 4 && $originalTurnDistance != 5 && $originalTurnDistance != 6)
							{
								$result[1]['directions']['asteroids'] = array_merge($result[1]['directions']['asteroids'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, $originalTurnDistance, 'asteroids')); // destinations for this saucer, this card, in the sun direction
							}
							if($this->doesSaucerHaveUpgradePlayed($saucerColor, "Hyperdrive"))
							{ // they have hyperdrive as well so we need to add 6 and 8 as options

								if($originalTurnDistance != 8)
								{ // the last distance is already 8 so we don't want to add another one

									// add 8
									$result[1]['directions']['asteroids'] = array_merge($result[1]['directions']['asteroids'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 8, 'asteroids'));
								}

								if($originalTurnDistance != 10)
								{ // the last distance is already 10 so we don't want to add another one

									// add 10
									$result[1]['directions']['asteroids'] = array_merge($result[1]['directions']['asteroids'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 10, 'asteroids'));
								}

								if($originalTurnDistance != 12)
								{ // the last distance is already 12 so we don't want to add another one

									// add 12
									$result[1]['directions']['asteroids'] = array_merge($result[1]['directions']['asteroids'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 12, 'asteroids'));
								}
							}

							// METEOR
							$result[1]['directions']['meteor'] = $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 1, 'meteor'); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['meteor'] = array_merge($result[1]['directions']['meteor'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 2, 'meteor')); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['meteor'] = array_merge($result[1]['directions']['meteor'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 3, 'meteor')); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['meteor'] = array_merge($result[1]['directions']['meteor'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 4, 'meteor')); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['meteor'] = array_merge($result[1]['directions']['meteor'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 5, 'meteor')); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['meteor'] = array_merge($result[1]['directions']['meteor'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 6, 'meteor')); // destinations for this saucer, this card, in the sun direction
							if($originalTurnDistance != 0 && $originalTurnDistance != 1 && $originalTurnDistance != 2 && $originalTurnDistance != 3 && $originalTurnDistance != 4 && $originalTurnDistance != 5 && $originalTurnDistance != 6)
							{
								$result[1]['directions']['meteor'] = array_merge($result[1]['directions']['meteor'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, $originalTurnDistance, 'meteor')); // destinations for this saucer, this card, in the sun direction
							}
							if($this->doesSaucerHaveUpgradePlayed($saucerColor, "Hyperdrive"))
							{ // they have hyperdrive as well so we need to add 6 and 8 as options

								if($originalTurnDistance != 8)
								{ // the last distance is already 8 so we don't want to add another one

									// add 8
									$result[1]['directions']['meteor'] = array_merge($result[1]['directions']['meteor'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 8, 'meteor'));
								}

								if($originalTurnDistance != 10)
								{ // the last distance is already 10 so we don't want to add another one

									// add 10
									$result[1]['directions']['meteor'] = array_merge($result[1]['directions']['meteor'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 10, 'meteor'));
								}

								if($originalTurnDistance != 12)
								{ // the last distance is already 12 so we don't want to add another one

									// add 12
									$result[1]['directions']['meteor'] = array_merge($result[1]['directions']['meteor'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 12, 'meteor'));
								}
							}

							// CONSTELLATION
							$result[1]['directions']['constellation'] = $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 1, 'constellation'); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['constellation'] = array_merge($result[1]['directions']['constellation'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 2, 'constellation')); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['constellation'] = array_merge($result[1]['directions']['constellation'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 3, 'constellation')); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['constellation'] = array_merge($result[1]['directions']['constellation'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 4, 'constellation')); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['constellation'] = array_merge($result[1]['directions']['constellation'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 5, 'constellation')); // destinations for this saucer, this card, in the sun direction
							$result[1]['directions']['constellation'] = array_merge($result[1]['directions']['constellation'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 6, 'constellation')); // destinations for this saucer, this card, in the sun direction
							if($originalTurnDistance != 0 && $originalTurnDistance != 1 && $originalTurnDistance != 2 && $originalTurnDistance != 3 && $originalTurnDistance != 4 && $originalTurnDistance != 5 && $originalTurnDistance != 6)
							{
								$result[1]['directions']['constellation'] = array_merge($result[1]['directions']['constellation'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, $originalTurnDistance, 'constellation')); // destinations for this saucer, this card, in the sun direction
							}
							if($this->doesSaucerHaveUpgradePlayed($saucerColor, "Hyperdrive"))
							{ // they have hyperdrive as well so we need to add 8, 10, 12 as options

								if($originalTurnDistance != 8)
								{ // the last distance is already 8 so we don't want to add another one

									// add 8
									$result[1]['directions']['constellation'] = array_merge($result[1]['directions']['constellation'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 8, 'constellation'));
								}

								if($originalTurnDistance != 10)
								{ // the last distance is already 10 so we don't want to add another one

									// add 10
									$result[1]['directions']['constellation'] = array_merge($result[1]['directions']['constellation'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 10, 'constellation'));
								}

								if($originalTurnDistance != 12)
								{ // the last distance is already 12 so we don't want to add another one

									// add 12
									$result[1]['directions']['constellation'] = array_merge($result[1]['directions']['constellation'], $this->getMoveDestinationsInDirection($saucerColor, $currentSaucerX, $currentSaucerY, 12, 'constellation'));
								}
							}


							foreach( $result as $movesInDirection )
								{ // go through each move card for this saucer

										$directionsWithSpaces = $movesInDirection['directions'];
										//$count = count($spacesForCard);
										//throw new feException( "spacesForCard Count:$count" );

										foreach( $directionsWithSpaces as $direction => $directionWithSpaces )
										{ // go through each direction

												foreach( $directionWithSpaces as $space )
												{ // go through each space

														$column = $space['column'];
														$row = $space['row'];

														$formattedSpace = $column.'_'.$row;
														array_push($validSpaces, $formattedSpace);
												}
										}
								}

						break;

						case "Landing Legs":
							//throw new feException( "currentSaucerX:$currentSaucerX currentSaucerY:$currentSaucerY" );
								$xPlusOne = $currentSaucerX + 1;
								//throw new feException( "xPlusOne:$xPlusOne currentSaucerY:$currentSaucerY" );
								array_push($validSpaces, $xPlusOne.'_'.$currentSaucerY);

								$xMinusOne = $currentSaucerX - 1;
								//throw new feException( "xMinusOne:$xMinusOne currentSaucerY:$currentSaucerY" );
								array_push($validSpaces, $xMinusOne.'_'.$currentSaucerY);

								$yPlusOne = $currentSaucerY + 1;
								//throw new feException( "currentSaucerX:$currentSaucerX yPlusOne:$yPlusOne" );
								array_push($validSpaces, $currentSaucerX.'_'.$yPlusOne);

								$yMinusOne = $currentSaucerY - 1;
								//throw new feException( "currentSaucerX:$currentSaucerX yMinusOne:$yMinusOne" );
								array_push($validSpaces, $currentSaucerX.'_'.$yMinusOne);
						break;

						case "Hyperdrive":
							$directionSelected = $this->getSaucerDirection($saucerColor);
							$distanceSelected = $this->getSaucerDistanceType($saucerColor);
							$movesForSaucer = $this->getMovesForSaucer($saucerColor, $distanceSelected, $directionSelected);

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
													array_push($validSpaces, $formattedSpace);
											}
									}
							}

						break;

						case "Time Machine":
							//$directionSelected = $this->getSaucerDirection($saucerColor);
							$distanceSelected = $this->getSaucerDistanceType($saucerColor);
							$movesForSaucer = $this->getMovesForSaucer($saucerColor, $distanceSelected, '');

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
													array_push($validSpaces, $formattedSpace);
											}
									}
							}

						break;
				}

				return $validSpaces;
		}

		function getAfterburnerMoves($saucerColor, $xLocation, $yLocation)
		{
			$moveList = array();

			// get all non-accelerator, non-crash-site spaces in our row or column
			$spacesInRowCol = self::getObjectListFromDB( "SELECT *
														  FROM board
														  WHERE board_space_type <> 'S' AND board_space_type <> 'D' AND (board_x=$xLocation OR board_y=$yLocation)" );


			$countSpaces = count($spacesInRowCol);
			foreach($spacesInRowCol as $space)
			{ // go through each non-accelerator, non-crash-site spaces in our row or column

					$spaceX = $space['board_x'];
					$spaceY = $space['board_y'];

					// see if there is a Saucer here
					$saucerWeCollideWith = $this->getSaucerAt($spaceX, $spaceY, $saucerColor);
					
					// see if there is a Crewmember here
					$crewmemberId = $this->getGarmentIdAt($spaceX, $spaceY);

					if($saucerWeCollideWith == '' && $crewmemberId == 0)
					{ // there is no crewmember nor saucer here

						$column = $spaceX;
						$row = $spaceY;

						$formattedSpace = $column.'_'.$row;

						array_push($moveList, $formattedSpace);
					}
					
			}

			return $moveList;
		}

		function getOrganicTriangulatorMoves($saucerColor, $xLocation, $yLocation)
		{
			$moveList = array();

			// get all non-accelerator, non-crash-site spaces in our row or column
			$spacesInRowCol = self::getObjectListFromDB( "SELECT *
														  FROM board
														  WHERE board_space_type <> 'S' AND board_space_type <> 'D'" );


			$countSpaces = count($spacesInRowCol);
			foreach($spacesInRowCol as $space)
			{ // go through each non-accelerator, non-crash-site spaces in our row or column

					$spaceX = $space['board_x'];
					$spaceY = $space['board_y'];

					// see if there is a Saucer here
					$saucerWeCollideWith = $this->getSaucerAt($spaceX, $spaceY, $saucerColor);
					
					// see if there is a Crewmember here
					$crewmemberId = $this->getGarmentIdAt($spaceX, $spaceY);

					if($saucerWeCollideWith == '' && $crewmemberId == 0)
					{ // there is no crewmember nor saucer here

						$column = $spaceX;
						$row = $spaceY;

						$formattedSpace = $column.'_'.$row;

						array_push($moveList, $formattedSpace);
					}
					
			}

			return $moveList;
		}

		// any crate not in the row or column of any of the garment chooser's ostriches is valid
		function getValidGarmentSpawnSpaces()
		{
				$result = array();

				$garmentChooser = self::getActivePlayerId(); // find the garment chooser
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

				$passableCrewmembers = $this->getPassableCrewmembersFromSaucer($saucerAsking);
				$numberOfPassableCrewmembers = count($passableCrewmembers);
				if($numberOfPassableCrewmembers < 1)
				{ // they do not have crewmembers that are of the color of their friendly saucer
//throw new feException( "no passable");
						return false;
				}

				foreach($passableCrewmembers as $crewmember)
				{ // go through each crewmember on this saucer that matches the saucer on their team
					$isPassable = $crewmember['isPassable']; 
					if($isPassable == 1)
					{ // this crewmember was on board when they passed by their friendly saucer
						return true;
					}
				}

				return false; // if we get here, then the two saucers never passed each other with the crewmember on board
		}

		function canSaucerTakeCrewmembers($saucerAsking)
		{

				if($this->getNumberOfPlayers() != 2)
				{ // this is not a 2-player game

//throw new feException( "not 2 player");

						return false;
				}

				if($this->getSkippedTaking($saucerAsking) == 1)
				{ // they already skipped taking this round


//throw new feException( "skipped taking");

						return false;
				}

				$otherSaucerOfPlayer = $this->getPlayersOtherSaucer($saucerAsking);
				$takeableCrewmembers = $this->getPassableCrewmembersFromSaucer($otherSaucerOfPlayer);
				$numberOfTakeableCrewmembers = count($takeableCrewmembers);
				//throw new feException( "numberOfTakeableCrewmembers: $numberOfTakeableCrewmembers");
				if($numberOfTakeableCrewmembers < 1)
				{ // they do not have crewmembers to take


//throw new feException( "no one to take");

						return false;
				}


								//throw new feException( "can take");
				foreach($takeableCrewmembers as $crewmember)
				{ // go through each crewmember on this saucer that matches the saucer on their team
					$isPassable = $crewmember['isPassable']; 
					if($isPassable == 1)
					{ // this crewmember was on board when they passed by their friendly saucer
						return true;
					}
				}
				
//throw new feException( "no ispassible");
				return false; // if we return here, then the two saucers never passed next to one another with the crewmember on board
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

				if($garmentX < ($firstTileX+4) && $garmentY < ($firstTileY+4))
				{ // ostrich is on tile 1
						return 1;
				}
				else if($garmentX < ($secondTileX+4) && $garmentY < ($secondTileY+4))
				{ // ostrich is on tile 2
						return 2;
				}
				else if($garmentX < ($thirdTileX+4) && $garmentY < ($thirdTileY+4))
				{ // ostrich is on tile 3
						return 3;
				}
				else if($garmentX < ($fourthTileX+4) && $garmentY < ($fourthTileY+4))
				{ // ostrich is on tile 4
						return 4;
				}
				else
				{ // we'll assume they are on one of the extension tiles
						return 0;
				}
		}

		// Tile Position is where the tiles are from a layout perspective.
		// Returns 0 if it is not one of the 4 main tiles.
		function getTilePositionOfOstrich($ostrich)
		{
			if($this->isSaucerCrashed($ostrich))
			{ // the saucer we are checking is crashed
				return 0; // don't rotate it
			}
			
			$tileNumber = null;
			$ostrichX = $this->getSaucerXLocation($ostrich);
			$ostrichY = $this->getSaucerYLocation($ostrich);

				//echo "getting the tile position of $ostrich ostrich with x $ostrichX and y $ostrichY";



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

				if($ostrichX < ($firstTileX+4) && $ostrichY < ($firstTileY+4))
				{ // ostrich is on tile 1
						return 1;
				}
				else if($ostrichX < ($secondTileX+4) && $ostrichY < ($secondTileY+4))
				{ // ostrich is on tile 2
						return 2;
				}
				else if($ostrichX < ($thirdTileX+4) && $ostrichY < ($thirdTileY+4))
				{ // ostrich is on tile 3
						return 3;
				}
				else if($ostrichX < ($fourthTileX+4) && $ostrichY < ($fourthTileY+4))
				{ // ostrich is on tile 4
						return 4;
				}
				else
				{ // we'll assume they are on one of the extension tiles
						return 0;
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
									array("2","B","B","B"),
									array("S","B","B","B")
								);
							}
							else
							{ // USE SIDE B
								$tileSpaceValues = array(
									array("S","2","B","B"),
									array("B","B","1","B"),
									array("B","B","B","3"),
									array("B","B","B","S")
								);
							}
						break;
						case 2:
							if($useSideA == 1)
							{ // USE SIDE A
								$tileSpaceValues = array(
									array("B","B","S","B"),
									array("B","B","B","5"),
									array("4","S","B","B"),
									array("B","B","6","B")
								);
							}
							else
							{ // USE SIDE B
								$tileSpaceValues = array(
									array("B","4","B","B"),
									array("B","B","S","B"),
									array("6","B","B","S"),
									array("B","B","5","B")
								);
							}
						break;
						case 3:
							if($useSideA == 1)
							{ // USE SIDE A
								$tileSpaceValues = array(
									array("B","7","B","B"),
									array("B","B","9","B"),
									array("S","B","B","8"),
									array("B","S","B","B")
								);
							}
							else
							{ // USE SIDE B
								$tileSpaceValues = array(
									array("B","B","S","B"),
									array("B","8","B","B"),
									array("S","B","B","9"),
									array("B","B","7","B")
								);
							}
						break;
						case 4:
							if($useSideA == 1)
							{ // USE SIDE A
								$tileSpaceValues = array(
									array("B","B","11","B"),
									array("10","B","B","B"),
									array("B","B","S","B"),
									array("B","12","B","S")
								);
							}
							else
							{ // USE SIDE B
								$tileSpaceValues = array(
									array("B","B","10","B"),
									array("12","S","B","B"),
									array("B","B","B","11"),
									array("B","B","B","S")
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
									array("B","B","B","S"),
									array("B","B","B","B")
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
									array("B","S","B","B"),
									array("B","B","B","B")
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
									array("B","B","B","B"),
									array("B","B","B","S")
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
									array("B","B","B","B"),
									array("B","S","B","B")
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

		function setNotifiedOfStealingOutcome($playerId, $newValue)
		{
			$sql = "UPDATE player SET notified_of_stealing_outcome=$newValue WHERE player_id=$playerId";
			self::DbQuery( $sql );
		}

		function getNotifiedOfStealingOutcome($playerId)
		{
			return self::getUniqueValueFromDb("SELECT notified_of_stealing_outcome FROM player WHERE player_id=$playerId");
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
						case 3:
						case 4:
							return 2;
						case 5:
							return 3;
						case 6:
							return 4;
						default:
							return 0;
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

				$spaces = self::getObjectListFromDB( "SELECT board_x, board_y FROM `board` WHERE board_x=$column AND board_y < $startingY AND board_y > + ($startingY - $maxDistance) ORDER BY board_y DESC" );

				// add the space the saucer is on
				$spaceArray = array();
				$spaceArray['row'] = $startingY;
				$spaceArray['column'] = $column;
				//throw new feException( "startColumn:$startColumn startRow:$startRow");
				array_push($result, $spaceArray); // add this space to the list of move destinations

				foreach( $spaces as $space )
				{ // go through each space
					  $x = $space['board_x'];
						$y = $space['board_y'];

						$spaceArray = array();
						$spaceArray['row'] = $y;
						$spaceArray['column'] = $x;

						$spaceType = $this->getBoardSpaceType($x, $y);
						//throw new feException( "x:$x y:$y");
						//if($startingY == 9)
						//{
						//echo("($x,$y):$spaceType");
						//echo("<br>");
						//}
						array_push($result, $spaceArray); // add this space to the list of move destinations
						if($spaceType == "S" || $spaceType == "D")
						{ // accelerator or off the board

									return $result;
						}
				}

				return $result;
		}

		function getSpacesInColumnDown($startingY, $column, $maxDistance)
		{
				$result = array();

				$spaces = self::getObjectListFromDB( "SELECT board_x, board_y FROM `board` WHERE board_x=$column AND board_y > $startingY AND board_y < ($startingY + $maxDistance) ORDER BY board_y ASC" );

				// add the space the saucer is on
				$spaceArray = array();
				$spaceArray['row'] = $startingY;
				$spaceArray['column'] = $column;
				//throw new feException( "startColumn:$startColumn startRow:$startRow");
				array_push($result, $spaceArray); // add this space to the list of move destinations

				foreach( $spaces as $space )
				{ // go through each space

						$x = $space['board_x'];
						$y = $space['board_y'];

						$spaceArray = array();
						$spaceArray['row'] = $y;
						$spaceArray['column'] = $x;

						$spaceType = $this->getBoardSpaceType($x, $y);
						//throw new feException( "x:$x y:$y");

						array_push($result, $spaceArray); // add this space to the list of move destinations
						if($spaceType == "S" || $spaceType == "D")
						{ // accelerator or off the board

									return $result;
						}
				}

				return $result;
		}

		function getSpacesInRowLeft($startingX, $row, $maxDistance)
		{
				$result = array();

				$spaces = self::getObjectListFromDB( "SELECT board_x, board_y FROM `board` WHERE board_y=$row AND board_x < $startingX AND board_x > ($startingX - $maxDistance) ORDER BY board_x DESC" );

				// add the space the saucer is on
				$spaceArray = array();
				$spaceArray['row'] = $row;
				$spaceArray['column'] = $startingX;
				//throw new feException( "startColumn:$startColumn startRow:$startRow");
				array_push($result, $spaceArray); // add this space to the list of move destinations

				foreach( $spaces as $space )
				{ // go through each space

						$x = $space['board_x'];
						$y = $space['board_y'];

						$spaceArray = array();
						$spaceArray['row'] = $y;
						$spaceArray['column'] = $x;

						$spaceType = $this->getBoardSpaceType($x, $y);
						//throw new feException( "x:$x y:$y");

						array_push($result, $spaceArray); // add this space to the list of move destinations
						if($spaceType == "S" || $spaceType == "D")
						{ // accelerator or off the board

									return $result;
						}
				}

				return $result;
		}

		function getSpacesInRowRight($startingX, $row, $maxDistance)
		{
				$result = array();

				$spaces = self::getObjectListFromDB( "SELECT board_x, board_y FROM `board` WHERE board_y=$row AND board_x > $startingX AND board_x < ($startingX + $maxDistance) ORDER BY board_x ASC" );

				// add the space the saucer is on
				$spaceArray = array();
				$spaceArray['row'] = $row;
				$spaceArray['column'] = $startingX;
				//throw new feException( "startColumn:$startColumn startRow:$startRow");
				array_push($result, $spaceArray); // add this space to the list of move destinations

				foreach( $spaces as $space )
				{ // go through each space

						$x = $space['board_x'];
						$y = $space['board_y'];

						$spaceArray = array();
						$spaceArray['row'] = $y;
						$spaceArray['column'] = $x;

						$spaceType = $this->getBoardSpaceType($x, $y);
						//throw new feException( "x:$x y:$y");

						array_push($result, $spaceArray); // add this space to the list of move destinations
						if($spaceType == "S" || $spaceType == "D")
						{ // accelerator or off the board

									return $result;
						}
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

		function saveRoundPickedUp($crewmemberId)
		{
			$newValue = $this->getGameStateValue("CURRENT_ROUND");
			$sql = "UPDATE garment SET round_acquired=$newValue WHERE garment_id=$crewmemberId";
			self::DbQuery( $sql );

			$turnAcquired = $this->getGameStateValue("CURRENT_TURN");
			$sqlTurn = "UPDATE garment SET turn_acquired=$turnAcquired WHERE garment_id=$crewmemberId";
			self::DbQuery( $sqlTurn );
		}

		function setSaucerGivenWithDistress($saucerColor, $newValue)
		{
				$sql = "UPDATE ostrich SET given_with_distress=$newValue WHERE ostrich_color='$saucerColor'";
				self::DbQuery( $sql );
		}

		function getSaucerGivenWithDistress()
		{
				return self::getUniqueValueFromDb("SELECT ostrich_color FROM ostrich WHERE given_with_distress=1");
		}

		function getHighestAirlockExchangeableId()
		{
				$highestValue = $this->getHighestAirlockExchangeableMax();
				return self::getUniqueValueFromDb("SELECT garment_id FROM garment WHERE airlock_exchangeable=$highestValue LIMIT 1");
		}

		function getHighestAirlockExchangeableMax()
		{
				return self::getUniqueValueFromDb("SELECT MAX(airlock_exchangeable) AS max FROM garment");
		}

		function getAirlockExchangeableCrewmembers()
		{
				return self::getObjectListFromDB( "SELECT * FROM `garment` WHERE airlock_exchangeable>0" );
		}

		function getAirlockExchangeableCrewmembersForSaucer($saucerColor)
		{
				return self::getObjectListFromDB( "SELECT * FROM `garment` WHERE airlock_exchangeable>0 AND garment_location='$saucerColor'" );
		}

		function getLostCrewmembers()
		{
				$lostCrewmembers = array();

				// get the next up crewmember for each saucer
				$allSaucers = $this->getAllCrewmemberSetColors();
				foreach( $allSaucers as $saucer )
				{ // go through each saucer
						$saucerColor = $saucer['garment_color'];
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

				// go through each crewmember for this saucer and set the primary and extras
				$this->setCrewmemberPrimaryAndExtras($saucerColor, $garmentType);

				$this->saveRoundPickedUp($crewmemberId); // record the round this crewmember was picked up in for Organic Triangulator
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
				$this->updatePlayerScores(); // update the player boards with current scores

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
				// see where this crewmember is (board, saucer, etc.) before the move to the saucer
				$currentLocation = $this->getCrewmemberLocationFromId($garmentId);
				$isPrimary = $this->isPrimaryCrewmember($garmentId);

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
						'CREWMEMBERIMAGE' => 'CREWMEMBERIMAGE_'.$garmentTypeString.'_'.$garmentColor,
						'sourceLocation' => $currentLocation,
						'isPrimary' => $isPrimary
				) );
		}

		function placeCrewmemberOnSpace($garmentId, $xDestination, $yDestination)
		{
				// see where this crewmember is (board, saucer, etc.) before the move to the saucer
				$currentLocation = $this->getCrewmemberLocationFromId($garmentId);
				$isPrimary = $this->isPrimaryCrewmember($garmentId);

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
						'CREWMEMBERIMAGE' => 'CREWMEMBERIMAGE_'.$garmentTypeString.'_'.$garmentColor,
						'sourceLocation' => $currentLocation,
						'isPrimary' => $isPrimary
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

				if($this->getNumberOfPlayers() == 2)
				{ // each player has 2 saucers so we must check for crashes of either saucer

					// see if either of their saucers crashed in case they crashed their own saucer
					$saucerOwner = $this->getOwnerIdOfOstrich($saucer);
					$saucersForPlayer = $this->getSaucersForPlayer($saucerOwner);
					foreach($saucersForPlayer as $saucerOfPlayer)
					{
						$colorOfSaucer = $saucerOfPlayer['ostrich_color'];
						
						// see if the saucer is crashed
						$saucerCrashed = $this->isSaucerCrashed($colorOfSaucer);	

						$timesActivatedCloakingDevice = $this->getUpgradeTimesActivatedThisRound($colorOfSaucer, "Cloaking Device");
						if($timesActivatedCloakingDevice > 0)
						{	// the used Cloaking Device to remove themself from the board

								// do not penalize them for "crashing"
								$saucerCrashed = false;
						}

						// get details on that saucer
						$saucerCrashDetails = $this->getSaucerCrashDetailsForSaucer($colorOfSaucer);

						foreach($saucerCrashDetails as $saucerDetail)
						{ // should just be one but it's a list of records

								$crashPenaltyRendered = $saucerDetail['crash_penalty_rendered'];

								//throw new feException( "saucerCrashed:$saucerCrashed saucerWhoseTurnItIs:$saucerWhoseTurnItIs saucer: $saucer crashPenaltyRendered: $crashPenaltyRendered");

								if($saucerCrashed == true &&
									$saucerWhoseTurnItIs == $colorOfSaucer &&
									$crashPenaltyRendered == false)
								{ // this saucer crashed on their turn and they have not yet paid the penalty

										if($this->doesSaucerHaveOffColoredCrewmember($colorOfSaucer))
										{ // saucer has an off-colored crewmember

												return true;
										}
								}
						}
					}
				}
				else
				{ // each player only has 1 saucer

					$timesActivatedCloakingDevice = $this->getUpgradeTimesActivatedThisRound($saucer, "Cloaking Device");
					if($saucerWhoseTurnItIs == $saucer && $timesActivatedCloakingDevice > 0)
					{	// the used Cloaking Device to remove themself from the board

							// do not penalize them for "crashing"
							return false;
					}

					// see if the saucer is crashed
					$saucerCrashed = $this->isSaucerCrashed($saucer);	

					// get details on that saucer
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
				}

				return false; // if we haven't returned true yet, we do not have a pending penalty
		}

		function setCrewmemberPrimaryAndExtrasForAllSaucers()
		{
				$allSaucers = $this->getAllSaucers();
				foreach($allSaucers as $saucer)
				{
						$saucerColor = $saucer['ostrich_color'];
						$this->setCrewmemberPrimaryAndExtras($saucerColor, 0);
						$this->setCrewmemberPrimaryAndExtras($saucerColor, 1);
						$this->setCrewmemberPrimaryAndExtras($saucerColor, 2);
						$this->setCrewmemberPrimaryAndExtras($saucerColor, 3);
				}
		}

		// Go through all the crewmembers of a specific type for a saucer and reset
		// whether each is the primary or extra.
		function setCrewmemberPrimaryAndExtras($saucerColor, $crewmemberTypeAsInt)
		{
			if($saucerColor == 'b83a4b')
			{
				//throw new feException( "saucerColor:$saucerColor");
			}

			if($saucerColor == '753bbd')
			{
				//throw new feException( "saucerColor:$saucerColor");
			}

				// set primary for this saucer and crewmember type to 0
				$setToZero = "UPDATE garment SET is_primary=0
								WHERE garment_location='$saucerColor' AND garment_type=$crewmemberTypeAsInt" ;
				self::DbQuery( $setToZero );
				//throw new feException( "setToZero sql query:$setToZero");

				$crewmembersForSaucerOfType = self::getObjectListFromDB( "SELECT *
																			FROM garment
																			WHERE garment_location='$saucerColor' AND garment_type=$crewmemberTypeAsInt ORDER BY is_primary DESC" );

			  $foundPrimary = false;
				$currentPrimaryCrewmemberId = 100;
				foreach($crewmembersForSaucerOfType as $crewmember)
				{ // go through each crewmember of this type on this saucer
						$crewmemberColor = $crewmember['garment_color'];
						$crewmemberTypeAsInt = $crewmember['garment_type'];
						$crewmemberId = $crewmember['garment_id'];

						if($currentPrimaryCrewmemberId == 100)
						{ // this is the first crewmember (the current primary)

								$currentPrimaryCrewmemberId = $crewmemberId; // save this in case we need it
						}

						if($crewmemberColor == $saucerColor)
						{ // this crewmember matches

								// set them to primary
								$sqlPrimary = "UPDATE garment SET is_primary=1
												WHERE garment_id=$crewmemberId" ;
								self::DbQuery( $sqlPrimary );
								$foundPrimary = true;
						}
						else
						{
								// set them to extra
								$sqlExtra = "UPDATE garment SET is_primary=0
												WHERE garment_id=$crewmemberId" ;
								self::DbQuery( $sqlExtra );
						}
				}

				if(!$foundPrimary)
				{ // we didn't find a crewmember that matches the saucer color

						// keep the original primary as the primary
						$sqlOriginalPrimary = "UPDATE garment SET is_primary=1
										WHERE garment_id=$currentPrimaryCrewmemberId" ;
						self::DbQuery( $sqlOriginalPrimary );

						$foundPrimary = true;
				}
		}

		function getPrimaryCrewmemberId($saucerColor, $crewmemberTypeAsInt)
		{
				$isPrimary = self::getObjectListFromDB( "SELECT garment_id
																			FROM garment
																			WHERE garment_location='$saucerColor' AND garment_type=$crewmemberTypeAsInt AND is_primary=1" );

				foreach( $isPrimary as $crewmember )
				{ // go through each primary (should be at most 1)

							$crewmemberId = $crewmember['garment_id'];

							return $crewmemberId;
				}

				return ""; // we don't have a primary crewmember of this type
		}

		function isPrimaryCrewmember($crewmemberId)
		{
				$isPrimary = self::getUniqueValueFromDb( "SELECT is_primary
																				FROM garment
																				WHERE garment_id=$crewmemberId" );

				if($isPrimary == 0)
				{
						return false;
				}
				else
				{
						return true;
				}
		}

		// Returns how many crewmembers of a particular type a saucer has.
		function countCrewmembersOfType($saucerColor, $crewmemberTypeAsInt)
		{
				$crewmembersForSaucerOfType = self::getObjectListFromDB( "SELECT *
																				FROM garment
																				WHERE garment_location='$saucerColor' AND garment_type=$crewmemberTypeAsInt" );

				return count($crewmembersForSaucerOfType);
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
				//throw new feException( "countCrewmembersNeededForPlayerCount:$countCrewmembersNeededForPlayerCount countCrewmembersOnBoard:$countCrewmembersOnBoard countLostCrewmembers:$countLostCrewmembers");

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
							 $crashRewardAcquired < 1)
						{ // this saucer was crashed by the saucer whose turn it is and they have not rendered their penalty

								if($ownerOfSaucerColor == $ownerOfSaucerWhoseTurnItIs)
								{ // the player crashed their own saucer

										$crasherFriendly = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);
										$crasheeFriendly = $this->convertColorToHighlightedText($saucerColor);
										
										self::notifyAllPlayers( "crashedOwnSaucer", clienttranslate( '${crasherFriendly} must suffer a crash penalty because they crashed their partner, ${crasheeFriendly}.' ), array(
											'crasherFriendly' => $crasherFriendly,
											'crasheeFriendly' => $crasheeFriendly
									) );

								}
								else
								{
										//echo "saucerColor:$saucerColor <br> saucerIsCrashed:$saucerIsCrashed <br> saucerWasCrashedBy:$saucerWasCrashedBy <br> crashRewardAcquired:$crashRewardAcquired <br>";
										return $saucerColor; // just return the first one we find like this
								}
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

		function hasOverrideToken($saucerColor)
		{
				$overrideTokenValue = self::getUniqueValueFromDb("SELECT has_override_token FROM ostrich WHERE ostrich_color='$saucerColor'");

				if($overrideTokenValue == 1 || $overrideTokenValue == '1')
				{
						return true;
				}
				else
				{
						return false;
				}
		}

		function setHasOverrideToken($saucerColor, $value)
		{
				$sql = "UPDATE ostrich SET has_override_token=$value WHERE ";
				$sql .= "ostrich_color='".$saucerColor."'";
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

		function setCrewmemberPassable($crewmemberId, $value)
		{
			$sql = "UPDATE garment SET is_passable=$value WHERE ";
				$sql .= "garment_id=$crewmemberId";
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

		function getDatabaseIdToCollectorIdMapping()
		{
				$mapping = array();

				$sql = "SELECT * FROM upgradeCards ORDER BY card_id ASC ";
				$equipmentListFromDb = self::getCollectionFromDb( $sql );

				$index = 0;
				foreach( $equipmentListFromDb as $card )
				{
						$cardId = $card['card_id'];
						$collectorNumber = $card['card_type_arg'];

						$mapping[$cardId] = $collectorNumber;
				}

				return $mapping;
		}

		function getUpgradeList()
		{
				$equipmentList = array();

				$sql = "SELECT * FROM upgradeCards ORDER BY card_type_arg DESC ";
				$equipmentListFromDb = self::getCollectionFromDb( $sql );

				$index = 0;
				foreach( $equipmentListFromDb as $card )
				{
						$cardId = $card['card_id'];
						$collectorNumber = $card['card_type_arg'];
						$location = $card['card_location'];
						$locationArg = $card['card_location_arg'];
						$equipName = $this->getUpgradeTitleFromCollectorNumber($collectorNumber);
						$equipEffect = $this->getUpgradeEffectFromCollectorNumber($collectorNumber);

						$equipmentList[$index] = array( 'card_id' => $cardId, 'card_type_arg' => $collectorNumber, 'upgrade_name' => $equipName, 'upgrade_effect' => $equipEffect, 'card_location' => $location, 'card_location_arg' => $locationArg);

						$index++;
				}

				return $equipmentList;
		}

		function getAllDiscardedUpgrades()
		{
				return self::getObjectListFromDB("SELECT * FROM upgradeCards WHERE card_location='discard'");
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

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Blast Off Thrusters') &&
				   $this->getUpgradeTimesActivatedThisRound($saucerColor, 'Blast Off Thrusters') < 1 &&
					 $this->getAskedToActivateUpgrade($saucerColor, 'Blast Off Thrusters') == false &&
					 $this->isUpgradePlayable($saucerColor, 'Blast Off Thrusters'))
				{ // they have played this upgrade but they have not yet activated it
					//throw new feException( "Blast Off Thrusters ");

						if(!$this->isSaucerCrashed($saucerColor))
						{ // they are not crashed
								$upgradeArray = array();
								$upgradeArray['collectorNumber'] = 1;
								$upgradeArray['upgradeName'] = $this->getUpgradeTitleFromCollectorNumber(1);

								array_push($result, $upgradeArray);
						}
				}

				return $result;
		}

		// Activated means the player has chosen to use it this round.
		function activateUpgrade($saucerColor, $upgradeName, $notify=true)
		{
				$collectorNumber = $this->convertUpgradeNameToCollectorNumber($upgradeName);
				$this->activateUpgradeWithCollectorNumber($saucerColor, $collectorNumber);
				$saucerColorFriendly = $this->convertColorToHighlightedText($saucerColor);

				if($notify)
				{ // we want to add a note about this in the message log
					self::notifyAllPlayers( "activateUpgrade", clienttranslate( '${saucer_color_friendly} activated ${upgrade_name}.' ), array(
							'saucer_color_friendly' => $saucerColorFriendly,
							'upgrade_name' => $upgradeName,
							'color' => $saucerColor
					) );
				}
		}

		function activateUpgradeWithCollectorNumber($saucerColor, $collectorNumber)
		{
				$sql = "UPDATE upgradeCards SET times_activated_this_round=times_activated_this_round+1 WHERE ";
				$sql .= "card_location='".$saucerColor."' AND card_type_arg=$collectorNumber AND card_is_played=1";
				self::DbQuery( $sql );

				// increase stat for activating
				$ownerActivating = $this->getOwnerIdOfOstrich($saucerColor);
				self::incStat( 1, 'upgrades_activated', $ownerActivating );
		}

		// Activated means the player has chosen to use it this round.
		function resetAllUpgradesActivatedThisRound()
		{
				$sql = "UPDATE upgradeCards SET times_activated_this_round=0,asked_to_activate_this_round=0";
				self::DbQuery( $sql );
		}

		function resetUpgradeActivatedThisRound($upgradeName, $saucerColor)
		{
				$collectorNumber = $this->convertUpgradeNameToCollectorNumber($upgradeName);
				$cardId = $this->getUpgradeCardId($saucerColor, $upgradeName);

				$sql = "UPDATE upgradeCards SET times_activated_this_round=0,asked_to_activate_this_round=0 WHERE ";

				if($cardId)
				{ // we were able to find a cardId
						$sql .= "card_id=$cardId";
				}
				else
				{ // we were not able to find a cardId so just use collectorNumber
						$sql .= "card_type_arg=$collectorNumber";
				}

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
		function getSaucerThatCrashed($onlyThosePendingCrashPenalty=false)
		{
				$saucerWithProbe = $this->getSaucerWithProbe();
				$ownerOfSaucerWithProbe = $this->getOwnerIdOfOstrich($saucerWithProbe);

				$player = $ownerOfSaucerWithProbe; // start with the player who has the probe
				$numberOfPlayers = $this->getNumberOfPlayers();

				for($i=0; $i<$numberOfPlayers; $i++)
				{ // go through each player in clockwise order

						// does this player have any Saucers who are crashed?
						$allPlayersSaucers = $this->getSaucersForPlayer($player);
						foreach( $allPlayersSaucers as $saucer )
						{ // go through each saucer owned by this player

							$crashPenaltyRendered = $saucer['crash_penalty_rendered']; // 0 if the penalty/reward for crashing has been given for this crash

							if($this->isSaucerCrashed($saucer['ostrich_color']))
							{ // this Saucer has crashed
								//throw new feException( "returning saucer: ".$saucer['ostrich_color']);

								if(!$onlyThosePendingCrashPenalty || 
									($onlyThosePendingCrashPenalty && $crashPenaltyRendered < 1))
								{ // either don't care if the crash penalty was rendered for this
									  // OR we only want to return a saucer who has not yet had their crash penalty rendered and this crash has not had its penalty rendered
	
									  return $saucer['ostrich_color'];
								}
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

		function placeSaucerOnSpace($saucerColor, $locX, $locY, $slideThere=true)
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

				// see if we are within 1 space of our other saucer
				$this->checkIfPassedByOtherSaucer($saucerColor, $locX, $locY);

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
								'location_description' => $locationDescription,
								'slide' => $slideThere
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
									'ostrichName' => $this->getOstrichName($ostrichColor),
									'slide' => true
				) );


		}

		function countTotalSeatedCrewmembers()
		{
				return self::getUniqueValueFromDb("SELECT COUNT(garment_id) FROM garment WHERE garment_location<>'board' AND garment_location<>'pile' AND garment_location<>'chosen' AND is_primary=1");
		}

		function countTotalCrewmembersForSaucer($saucerColor)
		{
				return self::getUniqueValueFromDb("SELECT COUNT(garment_id) FROM garment WHERE garment_location='$saucerColor'");
		}

		function getSeatedCrewmembersForSaucer($saucerColor)
		{
				$distinctCrewmemberTypes = self::getObjectListFromDB("SELECT DISTINCT(garment_type) FROM garment WHERE garment_location='$saucerColor'");

				$count = count($distinctCrewmemberTypes);

				return $count;
		}

		function getUpgradesInDeck()
		{
				return self::getObjectListFromDB("SELECT * FROM upgradeCards WHERE card_location='deck'");
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
									'yDestination' => $newGarmentY,
									'slide' => true
							) );
					}
				}
		}

		function rotateCrewmembersNumberOfTimes($tileNumberToRotate, $numberOfTimesClockwise)
		{

				$allGarmentsOnBoard = self::getObjectListFromDB( "SELECT garment_id, garment_color, garment_type
																							FROM garment WHERE garment_location='board'" );
//throw new feException( "rotateCrewmembersNumberOfTimes tileNumberToRotate:$tileNumberToRotate");

				foreach( $allGarmentsOnBoard as $garment )
				{ // go through all the garments

					$garmentId = $garment['garment_id'];
					$garmentColor = $garment['garment_color'];
					$garmentType = $garment['garment_type'];

					$tilePositionOfGarment = $this->getTilePositionOfGarment($garmentId); // get which tile it is on
					$tileNumberOfGarment = $this->getTileNumber($tilePositionOfGarment); // find the number of that tile

					//throw new feException( "crewmember tileNumberToRotate:$tileNumberToRotate tileNumberOfGarment:$tileNumberOfGarment tilePositionOfGarment:$tilePositionOfGarment");

					if($tileNumberOfGarment == $tileNumberToRotate)
					{	// we need to rotate this garment
							$xOffsetOfTile = $this->getTileXFromTileNumber($tileNumberToRotate) - 1;
							$yOffsetOfTile = $this->getTileYFromTileNumber($tileNumberToRotate) - 1;
							$currentGarmentX = $this->getGarmentXLocation($garmentId);
							$currentGarmentY = $this->getGarmentYLocation($garmentId);
							$newGarmentX = 0;
							$newGarmentY = 0;

							//throw new feException( "numberOfTimesClockwise: $numberOfTimesClockwise");
							if($numberOfTimesClockwise == 1)
							{
										// rotate 1 clockwise
										$newGarmentX = (($xOffsetOfTile+$yOffsetOfTile+4)-$currentGarmentY)+1; //newX=(xOffset+yOffset+4)-oldY+1
										$newGarmentY = $currentGarmentX+($yOffsetOfTile-$xOffsetOfTile); //newY=oldX+(yOffset-xOffset)
							}
							elseif($numberOfTimesClockwise == 2)
							{
										// rotate 180
										//$newGarmentX = (($xOffsetOfTile+$yOffsetOfTile+4)-$currentGarmentY)+1; //newX=(xOffset+yOffset+4)-oldY+1
										//$newGarmentY = (($xOffsetOfTile+$yOffsetOfTile+4)-$currentGarmentX)+1; //newY=(yOffset+xOffset+4)-oldX+1

										//$newGarmentX = (($xOffsetOfTile+$yOffsetOfTile+5)-$currentGarmentX); //newX=(xOffset+yOffset+4)-oldY+1
										//$newGarmentY = (($xOffsetOfTile+$yOffsetOfTile+5)-$currentGarmentY); //newY=(yOffset+xOffset+4)-oldX+1

										// convert to 1-based matrices
										$currentCrewmemberX1 = $currentGarmentX - $xOffsetOfTile; // 1-4
										$currentCrewmemberY1 = $currentGarmentY - $yOffsetOfTile; // 1-4

										// rotate it as if it had no offsets
										$newCrewmemberX1 = 5 - $currentCrewmemberX1; // 1-4
										$newCrewmemberY1 = 5 - $currentCrewmemberY1; // 1-4

										// add in the offsets
										$newGarmentX = $newCrewmemberX1 + $xOffsetOfTile;
										$newGarmentY = $newCrewmemberY1 + $yOffsetOfTile;


/*
										echo "xOffsetOfTile:$xOffsetOfTile <br>";
										echo "yOffsetOfTile:$yOffsetOfTile <br>";
										echo "currentGarmentX:$currentGarmentX <br>";
										echo "currentGarmentY:$currentGarmentY <br>";
										echo "currentCrewmemberX1:$currentCrewmemberX1 <br>";
										echo "currentCrewmemberY1:$currentCrewmemberY1 <br>";
										echo "newCrewmemberX1:$newCrewmemberX1 <br>";
										echo "newCrewmemberY1:$newCrewmemberY1 <br>";
										echo "newGarmentX:$newGarmentX <br>";
										echo "newGarmentY:$newGarmentY <br>";
*/

							}
							elseif($numberOfTimesClockwise == 3)
							{
										// rotate 1 counter-clockwise
										$newGarmentX = $currentGarmentY+($xOffsetOfTile-$yOffsetOfTile); //newX=oldY+(xOffset-yOffset)
										$newGarmentY = (($xOffsetOfTile+$yOffsetOfTile+4)-$currentGarmentX)+1; //newY=(yOffset+xOffset+4)-oldX+1
							}
							else
							{
										// don't rotate
										$newGarmentX = $currentGarmentX;
										$newGarmentY = $currentGarmentY;
							}




							$sql = "UPDATE garment SET garment_x=$newGarmentX,garment_y=$newGarmentY WHERE garment_id=$garmentId";
							self::DbQuery( $sql );

							$garmentTypeString = $this->convertGarmentTypeIntToString($garmentType);

							// notify players that this garment has been moved
							self::notifyAllPlayers( "moveGarmentToBoard", "", array(
									'garmentColor' => $garmentColor,
								  'garmentType' => $garmentTypeString,
									'xDestination' => $newGarmentX,
									'yDestination' => $newGarmentY,
									'slide' => true
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
										'ostrichName' => $this->getOstrichName($ostrichColor),
										'slide' => true
								) );
						}
				}
		}

		function rotateSaucersNumberOfTimes($tileNumberToRotate, $numberOfTimesClockwise)
		{
				$allOstriches = $this->getSaucersInOrder(); // get all saucers

				foreach($allOstriches as $ostrich)
				{
						$ostrichColor = $ostrich['color'];
						$tilePosition = $this->getTilePositionOfOstrich($ostrichColor); // get which tile they are on (like 1,2,3,4 are the main tile positions)
						$tileNumber = $this->getTileNumber($tilePosition); // find the unique identifier for the tile

						//throw new feException( "saucer tileNumberToRotate:$tileNumberToRotate tileNumber:$tileNumber tilePosition:$tilePosition");

						if($tileNumber == $tileNumberToRotate)
						{	// we need to rotate this ostrich
								$xOffsetOfTile = $this->getTileXFromTileNumber($tileNumberToRotate) - 1;
								$yOffsetOfTile = $this->getTileYFromTileNumber($tileNumberToRotate) - 1;
								$currentOstrichX = $this->getSaucerXLocation($ostrichColor);
								$currentOstrichY = $this->getSaucerYLocation($ostrichColor);
								$newOstrichX = 0;
								$newOstrichY = 0;

								//throw new feException( "found saucer on tileNumber($tileNumber) equal to tileNumberToRotate($tileNumberToRotate)");
								if($numberOfTimesClockwise == 1)
								{
										// rotate 1 clockwise
										$newOstrichX = (($xOffsetOfTile+$yOffsetOfTile+4)-$currentOstrichY)+1; //newX=(xOffset+yOffset+4)-oldY+1
										$newOstrichY = $currentOstrichX+($yOffsetOfTile-$xOffsetOfTile); //newY=oldX+(yOffset-xOffset)
								}
								elseif($numberOfTimesClockwise == 2)
								{
										// rotate 180
										//$newOstrichX = (($xOffsetOfTile+$yOffsetOfTile+4)-$currentOstrichY)+1; //newX=(xOffset+yOffset+4)-oldY+1
										//$newOstrichY = (($xOffsetOfTile+$yOffsetOfTile+4)-$currentOstrichX)+1; //newY=(yOffset+xOffset+4)-oldX+1
										//$newOstrichX = (($xOffsetOfTile+$yOffsetOfTile+4)-$currentOstrichY)+1; //newX=(xOffset+yOffset+4)-oldY+1
										//$newOstrichY = (($xOffsetOfTile+$yOffsetOfTile+4)-$currentOstrichX)+1; //newY=(yOffset+xOffset+4)-oldX+1
										//$newOstrichX = (($xOffsetOfTile+$yOffsetOfTile+5)-$currentOstrichX); //newX=(xOffset+yOffset+4)-oldY+1
										//$newOstrichY = (($xOffsetOfTile+$yOffsetOfTile+5)-$currentOstrichY); //newY=(yOffset+xOffset+4)-oldX+1

										// rotate 90 once
										//$newOstrichX = (($xOffsetOfTile+$yOffsetOfTile+4)-$currentOstrichY)+1; //newX=(xOffset+yOffset+4)-oldY+1
										//$newOstrichY = $currentOstrichX+($yOffsetOfTile-$xOffsetOfTile); //newY=oldX+(yOffset-xOffset)

										// rotate 90 again
										//$newOstrichX = (($xOffsetOfTile+$yOffsetOfTile+4)-$newOstrichY)+1; //newX=(xOffset+yOffset+4)-oldY+1
										//$newOstrichY = $newOstrichX+($yOffsetOfTile-$xOffsetOfTile)-1; //newY=oldX+(yOffset-xOffset)

										// convert to 1-based matrices
										$currentSaucerX1 = $currentOstrichX - $xOffsetOfTile; // 1-4
										$currentSaucerY1 = $currentOstrichY - $yOffsetOfTile; // 1-4

										// rotate it as if it had no offsets
										$newSaucerX1 = 5 - $currentSaucerX1; // 1-4
										$newSaucerY1 = 5 - $currentSaucerY1; // 1-4

										// add in the offsets
										$newOstrichX = $newSaucerX1 + $xOffsetOfTile;
										$newOstrichY = $newSaucerY1 + $yOffsetOfTile;
/*
										echo "xOffsetOfTile:$xOffsetOfTile <br>";
										echo "yOffsetOfTile:$yOffsetOfTile <br>";
										echo "currentOstrichX:$currentOstrichX <br>";
										echo "currentOstrichY:$currentOstrichY <br>";
										echo "currentSaucerX1:$currentSaucerX1 <br>";
										echo "currentSaucerY1:$currentSaucerY1 <br>";
										echo "newSaucerX1:$newSaucerX1 <br>";
										echo "newSaucerY1:$newSaucerY1 <br>";
										echo "newOstrichX:$newOstrichX <br>";
										echo "newOstrichY:$newOstrichY <br>";
*/

								}
								elseif($numberOfTimesClockwise == 3)
								{
										// rotate 1 counter-clockwise
										$newOstrichX = $currentOstrichY+($xOffsetOfTile-$yOffsetOfTile); //newX=oldY+(xOffset-yOffset)
										$newOstrichY = (($xOffsetOfTile+$yOffsetOfTile+4)-$currentOstrichX)+1; //newY=(yOffset+xOffset+4)-oldX+1
								}
								else
								{
										// don't rotate
										$newOstrichX = $currentOstrichX;
										$newOstrichY = $currentOstrichY;
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
										'ostrichName' => $this->getOstrichName($ostrichColor),
										'slide' => true
								) );
						}
				}
		}

		function resetTrapsDrawnThisRound()
		{
				$sql = "UPDATE player SET player_traps_drawn_this_round=0";
				self::DbQuery( $sql );
		}

		function resetAllOstrichZigs()
		{
				$sql = "UPDATE ostrich SET ostrich_zig_distance=20, ostrich_zig_direction='', saucer_original_turn_distance=13" ;
				self::DbQuery( $sql );
		}
		function resetOstrichChosen()
		{
				$sql = "UPDATE ostrich SET ostrich_is_chosen=0" ;
				self::DbQuery( $sql );
		}

		function resetXValueChoices()
		{
				$sql = "UPDATE ostrich SET ostrich_chosen_x_value=11" ;
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

		function resetSaucersInDOM()
		{
				$allSaucers = $this->getAllSaucers();
				foreach( $allSaucers as $saucer )
				{ // go through each saucer
						$saucerX = $saucer['ostrich_x'];
						$saucerY = $saucer['ostrich_y'];
						$saucerColor = $saucer['ostrich_color'];
						$saucerOwner = $saucer['ostrich_owner'];

						self::notifyAllPlayers( "resetSaucerPosition", "", array(
								'x' => $saucerX,
								'y' => $saucerY,
								'color' => $saucerColor,
								'owner' => $saucerOwner
						) );
				}
		}

		function resetSaucers()
		{

			$sql = "UPDATE ostrich SET skipped_passing=0, skipped_taking=0, passed_by_other_saucer=0, skipped_boosting=0, given_with_distress=0, spaces_moved=0, distance_remaining=0, pushed_on_saucer_turn='0', saucer_original_turn_distance=13" ;
			self::DbQuery( $sql );
		}

		function resetCrewmembers()
		{
				$sql = "UPDATE garment SET airlock_exchangeable=0, taken_with_distress=0, is_passable=0" ;
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
						throw new BgaUserException( self::_("Only the player who picked up the Crewmember can choose where it can be placed.") );
				}

				if($this->isValidGarmentSpawnLocation($xLocation, $yLocation))
				{ // this is a valid location

						$garmentId = $this->getChosenGarmentId();  // get the garment ID

						$this->moveGarmentToBoard($garmentId, $xLocation, $yLocation); // update the garment type and let players know

						$this->gamestate->nextState( "endSaucerTurnCleanUp" ); // DETERMINE NEXT STATE
				}
				else
				{ // NOT a valid location
						throw new BgaUserException( self::_("That is not a valid space to place a Crewmember.") );
				}
		}



		function getSaucerIsChosen($saucerColor)
		{
				$isChosenValue = self::getUniqueValueFromDb("SELECT ostrich_is_chosen FROM ostrich WHERE ostrich_color='$saucerColor'");

				if($isChosenValue == 1)
				{
						return true;
				}
				else
				{
						return false;
				}
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

		// NOTE: we need to order by ostrich_is_chosen so we can use this for determining turn order
		function getSaucersForPlayer($playerId)
		{
				return self::getObjectListFromDB( "SELECT ostrich_color, ostrich_turns_taken, ostrich_is_chosen, ostrich_color color, ostrich_owner owner, 'name' ownerName, crash_penalty_rendered
																										 FROM ostrich
																										 WHERE ostrich_owner=$playerId ORDER BY ostrich_is_chosen, ostrich_color" );
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


		  			$current_player_id = $this->getCurrentPlayerId();

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

		function hasPlayerChosen($playerId)
		{
				$ostrichSql = "SELECT SUM(ostrich_is_chosen) ";
				$ostrichSql .= "FROM ostrich ";
				$ostrichSql .= "WHERE ostrich_owner=$playerId";
				$result = self::getUniqueValueFromDb($ostrichSql);

				if($result > 0)
				{ // this player has chosen
					return true;
				}
				else
				{ // this player has not chosen
					return false;
				}
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

		function getLowestTurnsASaucerHasTaken()
		{
				$minTurnsSql = "SELECT MIN(ostrich_turns_taken) ";
				$minTurnsSql .= "FROM ostrich ";

				return self::getUniqueValueFromDb($minTurnsSql);
		}

		function countTurnsTakenByPlayer($playerId)
		{
				$turnsSql = "SELECT SUM(ostrich_turns_taken) ";
				$turnsSql .= "FROM ostrich WHERE ostrich_owner=$playerId";

				return self::getUniqueValueFromDb($turnsSql);
		}

		function getSaucerWhoseTurnItIs()
		{
				$minimumSaucerTurns = $this->getLowestTurnsASaucerHasTaken(); // min number turns a saucer has taken
				$clockwiseAsInt = $this->getGameStateValue("TURN_ORDER"); // 0=CLOCKWISE, 1=COUNTER-CLOCKWISE, 2=UNKNOWN
				$nextPlayer = $this->getStartPlayer(); // get the player with the probe
				$numberOfPlayers = $this->getNumberOfPlayers();

				if($numberOfPlayers > 2)
				{ // 1 saucer per player
						for ($x = 0; $x <= $numberOfPlayers; $x++)
						{ // go through each player

								$saucersForPlayer = $this->getSaucersForPlayer($nextPlayer);
								foreach( $saucersForPlayer as $saucer )
								{ // go through each saucer of this player
										$saucerColor = $saucer['ostrich_color'];
										$turnsThisSaucerHasTaken = $saucer['ostrich_turns_taken'];
										$saucerIsChosen = $saucer['ostrich_is_chosen']; // 1 if this saucer has been chosen to go first when controlling 2 saucers

										if($turnsThisSaucerHasTaken == $minimumSaucerTurns)
										{ // no saucer has taken fewer turns than this saucer

												// since we're going in turn order and it is also ordered with ostrich_is_chosen first, we know it is this saucer's turn
												return $saucerColor;
										}
								}

								// move onto the next player in turn order
								if($this->isTurnOrderClockwise())
								{ // we are going CLOCKWISE
										$nextPlayer = $this->getPlayerAfter( $nextPlayer );
								}
								else
								{ // we are going COUNTERCLOCKWISE
										$nextPlayer = $this->getPlayerBefore( $nextPlayer );
								}
						}
				}
				else
				{ // just 2 players and 2 saucers per player

						$probePlayer = $nextPlayer;
						$countOfTurnsTakenProbePlayer = $this->countTurnsTakenByPlayer($probePlayer);
						$nextPlayer = $this->getPlayerAfter( $probePlayer );
						$saucerTakingTurn = ""; // we may need to save a saucer color while we look at the other one
						$countOfTurnsTakenOtherPlayer = $this->countTurnsTakenByPlayer($nextPlayer);
//throw new feException( "probe player ($probePlayer) has count ($countOfTurnsTakenProbePlayer) while other player ($nextPlayer) has count ($countOfTurnsTakenOtherPlayer)");
						if($countOfTurnsTakenOtherPlayer < $countOfTurnsTakenProbePlayer)
						{ // other player is going now
//throw new feException( "other player going");
								$saucersForPlayer = $this->getSaucersForPlayer($nextPlayer);
								foreach( $saucersForPlayer as $saucer )
								{ // go through each saucer of this player
										$saucerColor = $saucer['ostrich_color'];
										$turnsThisSaucerHasTaken = $saucer['ostrich_turns_taken'];
										$saucerIsChosen = $saucer['ostrich_is_chosen']; // 1 if this saucer has been chosen to go first when controlling 2 saucers

										if($turnsThisSaucerHasTaken == $minimumSaucerTurns)
										{ // no saucer has taken fewer turns than this saucer

												if($saucerIsChosen == 1)
												{ // this saucer was chosen to go first this round

														// since we're going in turn order and it is also ordered with ostrich_is_chosen first, we know it is this saucer's turn
														return $saucerColor;
												}
												else
												{ // this saucer was not chosen to go first this round

														// save this saucer because it is taking its turn as long as there isn't another saucer for this player with the same number of turns who was chosen
														$saucerTakingTurn = $saucerColor;
												}
										}
								}

								// if we still and we haven't returned yet, we likely have a saucer who is
								return $saucerTakingTurn;
						}
						else
						{ // probe player is going now
//throw new feException( "probe player going");
								$saucersForPlayer = $this->getSaucersForPlayer($probePlayer);
								$saucerTakingTurn = ""; // we may need to save a saucer color while we look at the other one
								foreach( $saucersForPlayer as $saucer )
								{ // go through each saucer of this player
										$saucerColor = $saucer['ostrich_color'];
										$turnsThisSaucerHasTaken = $saucer['ostrich_turns_taken'];
										$saucerIsChosen = $saucer['ostrich_is_chosen']; // 1 if this saucer has been chosen to go first when controlling 2 saucers

										if($turnsThisSaucerHasTaken == $minimumSaucerTurns)
										{ // no saucer has taken fewer turns than this saucer

												if($saucerIsChosen == 1)
												{ // this saucer was chosen to go first this round

														// since we're going in turn order and it is also ordered with ostrich_is_chosen first, we know it is this saucer's turn
														return $saucerColor;
												}
												else
												{ // this saucer was not chosen to go first this round

														// save this saucer because it is taking its turn as long as there isn't another saucer for this player with the same number of turns who was chosen
														$saucerTakingTurn = $saucerColor;
												}
										}
								}

								// if we still and we haven't returned yet, we likely have a saucer who is
								return $saucerTakingTurn;
						}
				}

				return ""; // there are multiple ostriches, neither has gone, and neither has been chosen to go first
		}

		function getOstrichWhoseTurnItIs()
		{
				$activePlayer = self::getActivePlayerId(); // Current Player = player who played the current player action (the one who made the AJAX request). Active Player = player whose turn it is.

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

						if($saucerRecord['ostrich_chosen_x_value'] == 11)
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

		function getCrewmemberIdFromColorAndTypeText($color, $typeAsText)
		{
			$typeAsInt = $this->convertGarmentTypeStringToInt($typeAsText);
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

		// This sets any Crewmembers that were on board one saucer when it passed next to
		// the other saucer on their team in a 2-player game.
		function checkIfPassedByOtherSaucer($saucerMoving, $saucerMovingX, $saucerMovingY)
		{
			//echo "Checking saucer $saucerMoving when they are at space ($saucerMovingX, $saucerMovingY).";

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

										//$this->setPassedByOtherSaucer($saucerMoving, 1);
										foreach($passableCrewmembers as $crewmember)
										{
											$crewmemberId = $crewmember['crewmemberId'];
											//throw new feException("setting crewmemberId $crewmemberId to passable");
											//echo "Setting Crewmember $crewmemberId to passable.";
											$this->setCrewmemberPassable($crewmemberId, 1);
										}

										foreach($receivableCrewmembers as $crewmember)
										{
											$crewmemberId = $crewmember['crewmemberId'];
											//throw new feException("setting crewmemberId $crewmemberId to passable");
											//echo "Setting Crewmember $crewmemberId to passable.";
											$this->setCrewmemberPassable($crewmemberId, 1);
										}
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

										//$this->setPassedByOtherSaucer($saucerMoving, 1);

										foreach($passableCrewmembers as $crewmember)
										{
											$crewmemberId = $crewmember['crewmemberId'];
											//throw new feException("setting crewmemberId $crewmemberId to passable");
											//echo "Setting Crewmember $crewmemberId to passable.";
											$this->setCrewmemberPassable($crewmemberId, 1);
										}

										foreach($receivableCrewmembers as $crewmember)
										{
											$crewmemberId = $crewmember['crewmemberId'];
											//throw new feException("setting crewmemberId $crewmemberId to passable");
											//echo "Setting Crewmember $crewmemberId to passable.";
											$this->setCrewmemberPassable($crewmemberId, 1);
										}
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
			//throw new feException("getEventsWhileExecutingMove distance:$distance direction:$direction");

				$moveEventList = array();
				//$moveEventList[0] = array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => 4, 'destination_Y' => 7);
				//$moveEventList[1] = array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => 2, 'destination_Y' => 7);

				$playerMoving = $this->getOwnerIdOfOstrich($saucerMoving);
				//$saucerWhoseTurnItIs = $this->getSaucerWhoseTurnItIs(); // this is NOT the saucer moving if they are being pushed

				// see if we are within 1 space of our other saucer
				//$this->checkIfPassedByOtherSaucer($saucerMoving, $currentX, $currentY);

				// get the type of movement we're doing
				$moveType = $this->getMoveTypeWeAreExecuting();
				//throw new feException("moveType:$moveType");

				if($this->LEFT_DIRECTION == $direction)
				{ // we're traveling from right to left
					  for ($x = 1; $x <= $distance; $x++)
						{ // go one space at a time over distance
								$thisX = $currentX-$x; // move one space
								$boardValue = $this->getBoardSpaceType($thisX, $currentY); // which type of space did we move onto

								// see if we are within 1 space of our other saucer and set any crewmembers we're next to to be passable
								$this->checkIfPassedByOtherSaucer($saucerMoving, $thisX, $currentY);

								//echo "The value at ($thisX, $currentY) is: $boardValue <br>";
								//throw new feException("The value at ($thisX, $currentY) is: $boardValue");

								//if($thisX < 0)
							//		throw new feException("setSaucerXValue($saucerMoving, $thisX) with distance $distance");

								$this->setSaucerXValue($saucerMoving, $thisX); // set X value for Saucer

								if($wasPushed)
								{
									self::incStat( 1, 'distance_you_were_pushed', $playerMoving );
									self::incStat( 1, 'distance_moved', $playerMoving );
								}
								else
								{
									self::incStat( 1, 'distance_moved', $playerMoving );
								}

								if($moveType != "Blast Off Thrusters" && $moveType != "Landing Legs" && $moveType != "Pulse Cannon")
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

										$ownerPickingUp = $this->getOwnerIdOfOstrich($saucerMoving);
										self::incStat( 1, 'crewmembers_picked_up', $ownerPickingUp );

										if($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Airlock") &&
										$this->isUpgradePlayable($saucerMoving, 'Airlock'))
										{ // they have Airlock

												//throw new feException( "Airlock");
												// mark that this crewmember could be exchanged
												$this->incrementAirlockExchangeable($garmentId);
										}

										$crewmemberColor = $this->getCrewmemberColorFromId($garmentId);
										$crewmemberTypeId = $this->getCrewmemberTypeIdFromId($garmentId);
										$crewmemberType = $this->convertGarmentTypeIntToString($crewmemberTypeId);


										// add the animation for the saucer moving onto the space of the crewmember
										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));

										// see how many crewmembers we have of this type
										$countOfCrewmembersOfType = $this->countCrewmembersOfType($saucerMoving, $crewmemberTypeId);

										// see if this crewmembers is the first of this type for this saucer or its color matches the saucer
										if($saucerMoving == $crewmemberColor || $countOfCrewmembersOfType == 1)
										{ // this goes on the spot on the player mat
//throw new feException( "crewmemberPickup");
												// add an animation event for the crewmember sliding to the saucer
												array_push($moveEventList, array( 'event_type' => 'crewmemberPickup', 'saucer_moving' => $saucerMoving, 'crewmember_color' => $crewmemberColor, 'crewmember_type' => $crewmemberType));
										}
										else
										{ // this goes to the extras

												// add an animation event for the crewmember sliding to the extras spot on the mat
												array_push($moveEventList, array( 'event_type' => 'crewmemberPickupExtras', 'saucer_moving' => $saucerMoving, 'crewmember_color' => $crewmemberColor, 'crewmember_type' => $crewmemberType));
										}

										// see if we need to move any extras off saucer to the extras area
										if($saucerMoving == $crewmemberColor && $countOfCrewmembersOfType > 1)
										{ // it's your color and you already had one on your mat

												$crewmembersOnBoard = $this->getCrewmembersOnSaucer($saucerMoving);
												foreach($crewmembersOnBoard as $crewmember)
												{ // go through each crewmember on our saucer

														// get the crewmember coords
														$crewmemberTypeIntOnSaucer = $crewmember['garment_type'];
														$crewmemberColorOnSaucer = $crewmember['garment_color'];
														$crewmemberTypeOnSaucer = $this->convertGarmentTypeIntToString($crewmemberTypeIntOnSaucer);

														if($crewmemberTypeIntOnSaucer == $crewmemberTypeId && $crewmemberColorOnSaucer != $crewmemberColor)
														{ // this is of the type we just got but not the exact one we just got

																// add an animation event for all crewmembers of a type sliding to the extras so a new one can take its place
																array_push($moveEventList, array( 'event_type' => 'crewmemberPickupMoveToExtras', 'saucer_moving' => $saucerMoving, 'crewmember_color' => $crewmemberColorOnSaucer, 'crewmember_type' => $crewmemberTypeOnSaucer));
														}
												}
										}
								}
								else if($this->isCrashSite($boardValue) && $saucerWeCollideWith == "")
								{ // this is an empty CRASH SITE

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));

										if($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Waste Accelerator") &&
 										   $this->getUpgradeTimesActivatedThisRound($saucerMoving, "Waste Accelerator") < 1 &&
											$this->isUpgradePlayable($saucerMoving, 'Waste Accelerator') && 
											!$wasPushed)
										{ // they have Waste Accelerator played and they haven't used it yet this round and they are not being pushed

											array_push($moveEventList, array( 'event_type' => 'midMoveQuestion', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));

											// do not move any further because they will need to answer a question
											return $moveEventList;
										}

								}
								else if($boardValue == "S")
								{ // this is an ACCELERATOR
										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));

										if($wasPushed)
										{ // the saucer moving was pushed onto this accelerator

												array_push($moveEventList, array( 'event_type' => 'pushedOntoAccelerator', 'saucer_moving' => $saucerMoving, 'spaces_pushed' => $distance, 'destination_X' => $thisX, 'destination_Y' => $currentY));

												$pushedOntoAcceleratorEventList = $this->getEventsWhileExecutingMove($thisX, $currentY, $distance, $direction, $saucerMoving, $wasPushed);
												return array_merge($moveEventList, $pushedOntoAcceleratorEventList); // add the pushed event to the original and return so we don't go any further
										}
										else
										{ // the saucer moving moved onto the accelerator on their turn

												array_push($moveEventList, array( 'event_type' => 'movedOntoAccelerator', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));

												return $moveEventList; // don't go any further
										}
								}
								else if($boardValue == "D")
								{	// went off a cliff

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));

										array_push($moveEventList, array( 'event_type' => 'saucerCrashed', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));

										return $moveEventList; // don't go any further
								}
								else
								{ // empty space

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));

										if($saucerWeCollideWith != "")
										{	// there is a saucer here

												// the saucer we collide with will start their movement over
												$this->setSpacesMoved($saucerWeCollideWith, 0);

												if(($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Kinetic Siphon") &&
												$this->getUpgradeTimesActivatedThisRound($saucerMoving, "Kinetic Siphon") < 1 &&
												$this->isUpgradePlayable($saucerMoving, 'Kinetic Siphon')) && 
												   !$wasPushed)
												{ // this saucer has kinetic siphon played

													$this->giveSaucerBooster($saucerMoving); // give them a booster
													$this->activateUpgradeWithCollectorNumber($saucerMoving, 14); // make as played so we don't give it to them more than once/turn
												}

												if(($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Proximity Mines") &&
												   $this->isUpgradePlayable($saucerMoving, 'Proximity Mines')) && 
												   !$wasPushed)
												{ // this saucer has proximity mines played

													array_push($moveEventList, array( 'event_type' => 'midMoveQuestion', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));

														// mark in the database that this saucer has been collided with and will need to execute its move if we decide to collide with it
														// we'll clear these out if they choose not to phase shift
														$this->setPushedOnSaucerTurn($saucerWeCollideWith, $saucerMoving);
														$this->setPushedDistance($saucerWeCollideWith, $distance);
														$this->setPushedDirection($saucerWeCollideWith, $direction);

														// do not move any further because they will need to answer a question
														return $moveEventList;
												}
										}
								}


								if($saucerWeCollideWith != "")
								{	// there is a saucer here

									// since we collided with another saucer, exhaust all movement remaining... 
									// otherwise pushing a saucer on a Crash Site with Waste Accelerator won't work
									$fullMoveDistance = $this->getSaucerLastDistance( $saucerMoving );
									$this->setSpacesMoved($saucerMoving, $fullMoveDistance);


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
								if($wasPushed)
								{
									self::incStat( 1, 'distance_you_were_pushed', $playerMoving );
									self::incStat( 1, 'distance_moved', $playerMoving );
								}
								else
								{
									self::incStat( 1, 'distance_moved', $playerMoving );
								}

								if($moveType != "Blast Off Thrusters" && $moveType != "Landing Legs" && $moveType != "Pulse Cannon")
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

										$ownerPickingUp = $this->getOwnerIdOfOstrich($saucerMoving);
										self::incStat( 1, 'crewmembers_picked_up', $ownerPickingUp );

//throw new feException( "pre-Airlock right");
										if($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Airlock") &&
										$this->isUpgradePlayable($saucerMoving, 'Airlock'))
										{ // they have Airlock

												//throw new feException( "Airlock");
												// mark that this crewmember could be exchanged
												$this->incrementAirlockExchangeable($garmentId);
										}

										$crewmemberColor = $this->getCrewmemberColorFromId($garmentId);
										$crewmemberTypeId = $this->getCrewmemberTypeIdFromId($garmentId);
										$crewmemberType = $this->convertGarmentTypeIntToString($crewmemberTypeId);

										// add the animation for the saucer moving onto the space of the crewmember
										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));

										// see how many crewmembers we have of this type
										$countOfCrewmembersOfType = $this->countCrewmembersOfType($saucerMoving, $crewmemberTypeId);

										// see if this crewmembers is the first of this type for this saucer or its color matches the saucer
										if($saucerMoving == $crewmemberColor || $countOfCrewmembersOfType == 1)
										{ // this goes on the spot on the player mat
//throw new feException( "crewmemberPickup");
												// add an animation event for the crewmember sliding to the saucer
												array_push($moveEventList, array( 'event_type' => 'crewmemberPickup', 'saucer_moving' => $saucerMoving, 'crewmember_color' => $crewmemberColor, 'crewmember_type' => $crewmemberType));
										}
										else
										{ // this goes to the extras

												// add an animation event for the crewmember sliding to the extras spot on the mat
												array_push($moveEventList, array( 'event_type' => 'crewmemberPickupExtras', 'saucer_moving' => $saucerMoving, 'crewmember_color' => $crewmemberColor, 'crewmember_type' => $crewmemberType));
										}

										// see if we need to move any extras off saucer to the extras area
										if($saucerMoving == $crewmemberColor && $countOfCrewmembersOfType > 1)
										{ // it's your color and you already had one on your mat

												$crewmembersOnBoard = $this->getCrewmembersOnSaucer($saucerMoving);
												foreach($crewmembersOnBoard as $crewmember)
												{ // go through each crewmember on our saucer

														// get the crewmember coords
														$crewmemberTypeIntOnSaucer = $crewmember['garment_type'];
														$crewmemberColorOnSaucer = $crewmember['garment_color'];
														$crewmemberTypeOnSaucer = $this->convertGarmentTypeIntToString($crewmemberTypeIntOnSaucer);

														if($crewmemberTypeIntOnSaucer == $crewmemberTypeId && $crewmemberColorOnSaucer != $crewmemberColor)
														{ // this is of the type we just got but not the exact one we just got

																// add an animation event for all crewmembers of a type sliding to the extras so a new one can take its place
																array_push($moveEventList, array( 'event_type' => 'crewmemberPickupMoveToExtras', 'saucer_moving' => $saucerMoving, 'crewmember_color' => $crewmemberColorOnSaucer, 'crewmember_type' => $crewmemberTypeOnSaucer));
														}
												}
										}
								}
								else if($this->isCrashSite($boardValue) && $saucerWeCollideWith == "")
								{ // this is an empty CRASH SITE

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));

										if($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Waste Accelerator") &&
 										   $this->getUpgradeTimesActivatedThisRound($saucerMoving, "Waste Accelerator") < 1 &&
											$this->isUpgradePlayable($saucerMoving, 'Waste Accelerator') && 
											!$wasPushed)
										{ // they have Waste Accelerator played and they haven't used it yet this round and they are not being pushed

											array_push($moveEventList, array( 'event_type' => 'midMoveQuestion', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));

											// do not move any further because they will need to answer a question
											return $moveEventList;
										}

								}
								else if($boardValue == "S")
								{ // we hit an accelerator
//throw new feException( "end on S");
										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));

										if($wasPushed)
										{ // the saucer moving was pushed onto this accelerator
//throw new feException( "waspushed");
												array_push($moveEventList, array( 'event_type' => 'pushedOntoAccelerator', 'saucer_moving' => $saucerMoving, 'spaces_pushed' => $distance, 'destination_X' => $thisX, 'destination_Y' => $currentY));

												$pushedOntoAcceleratorEventList = $this->getEventsWhileExecutingMove($thisX, $currentY, $distance, $direction, $saucerMoving, $wasPushed);
												return array_merge($moveEventList, $pushedOntoAcceleratorEventList); // add the pushed event to the original and return so we don't go any further
										}
										else
										{ // the saucer moving moved onto the accelerator on their turn

												array_push($moveEventList, array( 'event_type' => 'movedOntoAccelerator', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));

												return $moveEventList; // don't go any further
										}
								}
								else if($boardValue == "D")
								{	// went off a cliff

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));

										array_push($moveEventList, array( 'event_type' => 'saucerCrashed', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));

										return $moveEventList; // return so we don't go any further
								}
								else
								{ // empty space

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));

										if($saucerWeCollideWith != "")
										{	// there is a saucer here

												// the saucer we collide with will start their movement over
												$this->setSpacesMoved($saucerWeCollideWith, 0);

												if(($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Kinetic Siphon") &&
												$this->getUpgradeTimesActivatedThisRound($saucerMoving, "Kinetic Siphon") < 1 &&
												$this->isUpgradePlayable($saucerMoving, 'Kinetic Siphon')) && 
												   !$wasPushed)
												{ // this saucer has kinetic siphon played

													$this->giveSaucerBooster($saucerMoving); // give them a booster
													$this->activateUpgradeWithCollectorNumber($saucerMoving, 14); // make as played so we don't give it to them more than once/turn
												}

												if(($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Proximity Mines") &&
												   $this->isUpgradePlayable($saucerMoving, 'Proximity Mines')) && 
												   !$wasPushed)
												{ // this saucer has proximity mines played and it's their turn

													array_push($moveEventList, array( 'event_type' => 'midMoveQuestion', 'saucer_moving' => $saucerMoving, 'destination_X' => $thisX, 'destination_Y' => $currentY));

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

									// since we collided with another saucer, exhaust all movement remaining... 
									// otherwise pushing a saucer on a Crash Site with Waste Accelerator won't work
									$fullMoveDistance = $this->getSaucerLastDistance( $saucerMoving );
									$this->setSpacesMoved($saucerMoving, $fullMoveDistance);

									array_push($moveEventList, array( 'event_type' => 'saucerPush', 'saucer_moving' => $saucerMoving, 'saucer_pushed' => $saucerWeCollideWith, 'spaces_pushed' => $distance));
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
								if($wasPushed)
								{
									self::incStat( 1, 'distance_you_were_pushed', $playerMoving );
									self::incStat( 1, 'distance_moved', $playerMoving );
								}
								else
								{
									self::incStat( 1, 'distance_moved', $playerMoving );
								}

								if($moveType != "Blast Off Thrusters" && $moveType != "Landing Legs" && $moveType != "Pulse Cannon")
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

										$ownerPickingUp = $this->getOwnerIdOfOstrich($saucerMoving);
										self::incStat( 1, 'crewmembers_picked_up', $ownerPickingUp );

										if($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Airlock") &&
										$this->isUpgradePlayable($saucerMoving, 'Airlock'))
										{ // they have Airlock

												//throw new feException( "Airlock");
												// mark that this crewmember could be exchanged
												$this->incrementAirlockExchangeable($garmentId);
										}

										$crewmemberColor = $this->getCrewmemberColorFromId($garmentId);
										$crewmemberTypeId = $this->getCrewmemberTypeIdFromId($garmentId);
										$crewmemberType = $this->convertGarmentTypeIntToString($crewmemberTypeId);

										// add the animation for the saucer moving onto the space of the crewmember
										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));

										// see how many crewmembers we have of this type
										$countOfCrewmembersOfType = $this->countCrewmembersOfType($saucerMoving, $crewmemberTypeId);

										// see if this crewmembers is the first of this type for this saucer or its color matches the saucer
										if($saucerMoving == $crewmemberColor || $countOfCrewmembersOfType == 1)
										{ // this goes on the spot on the player mat
//throw new feException( "crewmemberPickup");
												// add an animation event for the crewmember sliding to the saucer
												array_push($moveEventList, array( 'event_type' => 'crewmemberPickup', 'saucer_moving' => $saucerMoving, 'crewmember_color' => $crewmemberColor, 'crewmember_type' => $crewmemberType));
										}
										else
										{ // this goes to the extras

												// add an animation event for the crewmember sliding to the extras spot on the mat
												array_push($moveEventList, array( 'event_type' => 'crewmemberPickupExtras', 'saucer_moving' => $saucerMoving, 'crewmember_color' => $crewmemberColor, 'crewmember_type' => $crewmemberType));
										}

										// see if we need to move any extras off saucer to the extras area
										if($saucerMoving == $crewmemberColor && $countOfCrewmembersOfType > 1)
										{ // it's your color and you already had one on your mat

												$crewmembersOnBoard = $this->getCrewmembersOnSaucer($saucerMoving);
												foreach($crewmembersOnBoard as $crewmember)
												{ // go through each crewmember on our saucer

														// get the crewmember coords
														$crewmemberTypeIntOnSaucer = $crewmember['garment_type'];
														$crewmemberColorOnSaucer = $crewmember['garment_color'];
														$crewmemberTypeOnSaucer = $this->convertGarmentTypeIntToString($crewmemberTypeIntOnSaucer);

														if($crewmemberTypeIntOnSaucer == $crewmemberTypeId && $crewmemberColorOnSaucer != $crewmemberColor)
														{ // this is of the type we just got but not the exact one we just got

																// add an animation event for all crewmembers of a type sliding to the extras so a new one can take its place
																array_push($moveEventList, array( 'event_type' => 'crewmemberPickupMoveToExtras', 'saucer_moving' => $saucerMoving, 'crewmember_color' => $crewmemberColorOnSaucer, 'crewmember_type' => $crewmemberTypeOnSaucer));
														}
												}
										}
								}
								else if($this->isCrashSite($boardValue) && $saucerWeCollideWith == "")
								{ // this is an empty CRASH SITE

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));

										if($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Waste Accelerator") &&
 										   $this->getUpgradeTimesActivatedThisRound($saucerMoving, "Waste Accelerator") < 1 &&
											$this->isUpgradePlayable($saucerMoving, 'Waste Accelerator') && 
											!$wasPushed)
										{ // they have Waste Accelerator played and they haven't used it yet this round and they are not being pushed

											array_push($moveEventList, array( 'event_type' => 'midMoveQuestion', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));

											// do not move any further because they will need to answer a question
											return $moveEventList;
										}

								}
								else if($boardValue == "S")
								{ // we hit an accelerator

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));

										if($wasPushed)
										{ // the saucer moving was pushed onto this accelerator

												array_push($moveEventList, array( 'event_type' => 'pushedOntoAccelerator', 'saucer_moving' => $saucerMoving, 'spaces_pushed' => $distance, 'destination_X' => $currentX, 'destination_Y' => $thisY));

												$pushedOntoAcceleratorEventList = $this->getEventsWhileExecutingMove($currentX, $thisY, $distance, $direction, $saucerMoving, $wasPushed);
												return array_merge($moveEventList, $pushedOntoAcceleratorEventList); // add the pushed event to the original and return so we don't go any further
										}
										else
										{ // the saucer moving moved onto the accelerator on their turn

												array_push($moveEventList, array( 'event_type' => 'movedOntoAccelerator', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));

												return $moveEventList; // don't go any further
										}
								}
								else if($boardValue == "D")
								{	// went off a cliff

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));

										array_push($moveEventList, array( 'event_type' => 'saucerCrashed', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));

										return $moveEventList;
								}
								else
								{ // empty space

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));

										if($saucerWeCollideWith != "")
										{	// there is a saucer here

												// the saucer we collide with will start their movement over
												$this->setSpacesMoved($saucerWeCollideWith, 0);

												if(($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Kinetic Siphon") &&
												$this->getUpgradeTimesActivatedThisRound($saucerMoving, "Kinetic Siphon") < 1 &&
												$this->isUpgradePlayable($saucerMoving, 'Kinetic Siphon')) && 
												   !$wasPushed)
												{ // this saucer has kinetic siphon played

													$this->giveSaucerBooster($saucerMoving); // give them a booster
													$this->activateUpgradeWithCollectorNumber($saucerMoving, 14); // make as played so we don't give it to them more than once/turn
												}

												if(($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Proximity Mines") &&
												   $this->isUpgradePlayable($saucerMoving, 'Proximity Mines')) && 
												   !$wasPushed)
												{ // this saucer has proximity mines played

													array_push($moveEventList, array( 'event_type' => 'midMoveQuestion', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));


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

									// since we collided with another saucer, exhaust all movement remaining... 
									// otherwise pushing a saucer on a Crash Site with Waste Accelerator won't work
									$fullMoveDistance = $this->getSaucerLastDistance( $saucerMoving );
									$this->setSpacesMoved($saucerMoving, $fullMoveDistance);

										array_push($moveEventList, array( 'event_type' => 'saucerPush', 'saucer_moving' => $saucerMoving, 'saucer_pushed' => $saucerWeCollideWith, 'spaces_pushed' => $distance));

										$pushedEventList = $this->getEventsWhileExecutingMove($currentX, $thisY, $distance, $direction, $saucerWeCollideWith, true);
										return array_merge($moveEventList, $pushedEventList); // add the pushed event to the original and return so we don't go any further
								}
						}

						return $moveEventList;
				}

				if($this->DOWN_DIRECTION == $direction)
			 	{
					//throw new feException( "DOWN distance:"+$distance);

						for ($y = 1; $y <= $distance; $y++)
						{ // go space-by-space starting at your current location until the distance is
							// used up or we run into a skateboard or ostrich

								$thisY = $currentY+$y;

								$boardValue = $this->getBoardSpaceType($currentX, $thisY);
								//throw new feException( "boardValue:$boardValue");

								// see if we are within 1 space of our other saucer
								$this->checkIfPassedByOtherSaucer($saucerMoving, $currentX, $thisY);

							    $this->setSaucerYValue($saucerMoving, $thisY); // set Y value for Saucer
								if($wasPushed)
								{
									self::incStat( 1, 'distance_you_were_pushed', $playerMoving );
									self::incStat( 1, 'distance_moved', $playerMoving );
								}
								else
								{
									self::incStat( 1, 'distance_moved', $playerMoving );
								}

								if($moveType != "Blast Off Thrusters" && $moveType != "Landing Legs" && $moveType != "Pulse Cannon")
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

										$ownerPickingUp = $this->getOwnerIdOfOstrich($saucerMoving);
										self::incStat( 1, 'crewmembers_picked_up', $ownerPickingUp );

										if($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Airlock") &&
										$this->isUpgradePlayable($saucerMoving, 'Airlock'))
										{ // they have Airlock

												//throw new feException( "Airlock");
												// mark that this crewmember could be exchanged
												$this->incrementAirlockExchangeable($garmentId);
										}

										$crewmemberColor = $this->getCrewmemberColorFromId($garmentId);
										$crewmemberTypeId = $this->getCrewmemberTypeIdFromId($garmentId);
										$crewmemberType = $this->convertGarmentTypeIntToString($crewmemberTypeId);

										// add the animation for the saucer moving onto the space of the crewmember
										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));

										// see how many crewmembers we have of this type
										$countOfCrewmembersOfType = $this->countCrewmembersOfType($saucerMoving, $crewmemberTypeId);

										// see if this crewmembers is the first of this type for this saucer or its color matches the saucer
										if($saucerMoving == $crewmemberColor || $countOfCrewmembersOfType == 1)
										{ // this goes on the spot on the player mat
//throw new feException( "crewmemberPickup");
												// add an animation event for the crewmember sliding to the saucer
												array_push($moveEventList, array( 'event_type' => 'crewmemberPickup', 'saucer_moving' => $saucerMoving, 'crewmember_color' => $crewmemberColor, 'crewmember_type' => $crewmemberType));
										}
										else
										{ // this goes to the extras

												// add an animation event for the crewmember sliding to the extras spot on the mat
												array_push($moveEventList, array( 'event_type' => 'crewmemberPickupExtras', 'saucer_moving' => $saucerMoving, 'crewmember_color' => $crewmemberColor, 'crewmember_type' => $crewmemberType));
										}

										// see if we need to move any extras off saucer to the extras area
										if($saucerMoving == $crewmemberColor && $countOfCrewmembersOfType > 1)
										{ // it's your color and you already had one on your mat

												$crewmembersOnBoard = $this->getCrewmembersOnSaucer($saucerMoving);
												foreach($crewmembersOnBoard as $crewmember)
												{ // go through each crewmember on our saucer

														// get the crewmember coords
														$crewmemberTypeIntOnSaucer = $crewmember['garment_type'];
														$crewmemberColorOnSaucer = $crewmember['garment_color'];
														$crewmemberTypeOnSaucer = $this->convertGarmentTypeIntToString($crewmemberTypeIntOnSaucer);

														if($crewmemberTypeIntOnSaucer == $crewmemberTypeId && $crewmemberColorOnSaucer != $crewmemberColor)
														{ // this is of the type we just got but not the exact one we just got

																// add an animation event for all crewmembers of a type sliding to the extras so a new one can take its place
																array_push($moveEventList, array( 'event_type' => 'crewmemberPickupMoveToExtras', 'saucer_moving' => $saucerMoving, 'crewmember_color' => $crewmemberColorOnSaucer, 'crewmember_type' => $crewmemberTypeOnSaucer));
														}
												}
										}
								}
								else if($this->isCrashSite($boardValue) && $saucerWeCollideWith == "")
								{ // this is an empty CRASH SITE
//throw new feException( "crash site");
										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));

										if($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Waste Accelerator") &&
 										   $this->getUpgradeTimesActivatedThisRound($saucerMoving, "Waste Accelerator") < 1 &&
											$this->isUpgradePlayable($saucerMoving, 'Waste Accelerator') && 
											!$wasPushed)
										{ // they have Waste Accelerator played and they haven't used it yet this round and they are not being pushed

											array_push($moveEventList, array( 'event_type' => 'midMoveQuestion', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));

											// do not move any further because they will need to answer a question
											return $moveEventList;
										}
								}
								else if($boardValue == "S")
								{ // we hit an Accelerator

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));

										if($wasPushed)
										{ // the saucer moving was pushed onto this accelerator

												array_push($moveEventList, array( 'event_type' => 'pushedOntoAccelerator', 'saucer_moving' => $saucerMoving, 'spaces_pushed' => $distance, 'destination_X' => $currentX, 'destination_Y' => $thisY));

												$pushedOntoAcceleratorEventList = $this->getEventsWhileExecutingMove($currentX, $thisY, $distance, $direction, $saucerMoving, $wasPushed);
												return array_merge($moveEventList, $pushedOntoAcceleratorEventList); // add the pushed event to the original and return so we don't go any further
										}
										else
										{ // the saucer moving moved onto the accelerator on their turn

												array_push($moveEventList, array( 'event_type' => 'movedOntoAccelerator', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));

												return $moveEventList; // don't go any further
										}
								}
								else if($boardValue == "D")
								{	// went off a cliff

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));

										array_push($moveEventList, array( 'event_type' => 'saucerCrashed', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));

										return $moveEventList;
								}
								else
								{ // empty space

										array_push($moveEventList, array( 'event_type' => 'saucerMove', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));

										if($saucerWeCollideWith != "")
										{	// there is a saucer here

												// the saucer we collide with will start their movement over
												$this->setSpacesMoved($saucerWeCollideWith, 0);

												if(($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Kinetic Siphon") &&
												$this->getUpgradeTimesActivatedThisRound($saucerMoving, "Kinetic Siphon") < 1 &&
												$this->isUpgradePlayable($saucerMoving, 'Kinetic Siphon')) && 
												   !$wasPushed)
												{ // this saucer has kinetic siphon played

													$this->giveSaucerBooster($saucerMoving); // give them a booster
													$this->activateUpgradeWithCollectorNumber($saucerMoving, 14); // make as played so we don't give it to them more than once/turn
												}

												if(($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Proximity Mines") &&
												   $this->isUpgradePlayable($saucerMoving, 'Proximity Mines')) && 
												   !$wasPushed)
												{ // this saucer has proximity mines played

													array_push($moveEventList, array( 'event_type' => 'midMoveQuestion', 'saucer_moving' => $saucerMoving, 'destination_X' => $currentX, 'destination_Y' => $thisY));

													
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
								{	// there is another saucer here

									// since we collided with another saucer, exhaust all movement remaining... 
									// otherwise pushing a saucer on a Crash Site with Waste Accelerator won't work
									$fullMoveDistance = $this->getSaucerLastDistance( $saucerMoving );
									$this->setSpacesMoved($saucerMoving, $fullMoveDistance);

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

		function getSaucerWithProbe()
		{
				return self::getUniqueValueFromDb("SELECT ostrich_color FROM ostrich WHERE ostrich_has_crown=1");
		}

		// Returns true if the saucer is on the same space as any of the crewmembers given. False otherwise.
		function isSaucerOnAnyOfTheseCrewmembers($saucerColor, $crewmemberList)
		{
			$saucerX = $this->getSaucerXLocation($saucerColor);
			$saucerY = $this->getSaucerYLocation($saucerColor);
			foreach( $crewmemberList as $crewmember )
			{ // go through each saucer
				$crewmemberX = $crewmember['garment_x'];
				$crewmemberY = $crewmember['garment_y'];

				if($saucerX == $crewmemberX && $saucerY == $crewmemberY)
				{
					return true;
				}
			}

			return false;
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

		function isCrashSite($boardSpaceValue)
		{
			if($boardSpaceValue == "1" ||
			$boardSpaceValue == "2" ||
			$boardSpaceValue == "3" ||
			$boardSpaceValue == "4" ||
			$boardSpaceValue == "5" ||
			$boardSpaceValue == "6" ||
			$boardSpaceValue == "7" ||
			$boardSpaceValue == "8" ||
			$boardSpaceValue == "9" ||
			$boardSpaceValue == "10" ||
			$boardSpaceValue == "11" ||
			$boardSpaceValue == "12" )
			{
				return true;
			}
			else
			{
				return false;
			}
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

		function giveProbe($newSaucerGettingProbe, $reason)
		{
				$currentSaucerWithProbe = $this->getSaucerWithProbe();

				$ownerOfNewSaucerWithProbe = $this->getOwnerIdOfOstrich($newSaucerGettingProbe);

				$playerName = $this->getOwnerNameOfOstrich($newSaucerGettingProbe);

				// get the colored version and friendly name of the hex color
				$saucerColorFriendly = $this->convertColorToHighlightedText($newSaucerGettingProbe);

				$message = clienttranslate( '${ostrichName} gets the Probe and will go first this round!' ); // default
				
				switch($reason)
				{
					case "Starting":
						$message = clienttranslate( '${ostrichName} has been randomly chosen to start with the Probe and will go first this round.' );
						break;
					case "Least":
						$message = clienttranslate( '${ostrichName} gets the Probe because they have the least stationed Crewmembers.' );
						break;
					case "TiedWentLast":
						$message = clienttranslate( '${ostrichName} gets the Probe because they are tied for the least stationed Crewmembers and won the tie-breaker of going later in the previous round.' );
						break;
				}
				

						// set all ostriches to not have the crown
						$sqlAll = "UPDATE ostrich SET ostrich_has_crown=0" ;
						self::DbQuery( $sqlAll );

						// set the one who got the crown
						$sqlCrown = "UPDATE ostrich SET ostrich_has_crown=1
													WHERE ostrich_color='$newSaucerGettingProbe' " ;
						self::DbQuery( $sqlCrown );



						self::notifyAllPlayers( "saucerGivenProbe", $message, array(
								'ostrichName' => $saucerColorFriendly,
								'player_name' => $playerName,
								'color' => $newSaucerGettingProbe,
								'owner' => $ownerOfNewSaucerWithProbe
						) );
				

				// count how many times this saucer has gotten the probe
				self::incStat( 1, 'rounds_started', $ownerOfNewSaucerWithProbe );
		}

		function incrementAirlockExchangeable($crewmemberId)
		{
				// get the highest exchangeable value
				$highestAmount = $this->getHighestAirlockExchangeableMax();

				$sql = "UPDATE garment SET airlock_exchangeable=$highestAmount+1
								WHERE garment_id=$crewmemberId" ;
				self::DbQuery( $sql );
		}

		function removeAirlockExchangeableForCrewmember($crewmemberId)
		{
				$sql = "UPDATE garment SET airlock_exchangeable=0
								WHERE garment_id=$crewmemberId" ;
				self::DbQuery( $sql );
		}

		function removeAirlockExchangeableForAll($saucerColor)
		{
				$sql = "UPDATE garment SET airlock_exchangeable=0" ;
				self::DbQuery( $sql );
		}

		function setSaucerToChosen($ostrich)
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
				return true; // trying unlimited boosters

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

		// Check all Saucers and see if any are off the board. 
		// If so, notify players that they fell off, set their respawn order, and update stats.
		function sendCliffFallsToPlayers()
		{
				$ostrichTakingTurn = $this->getOstrichWhoseTurnItIs();
				$ownerOfOstrichTakingTurn = $this->getOwnerIdOfOstrich($ostrichTakingTurn); // get the player whose turn it is

				$allOstriches = $this->getSaucersInOrder();

				foreach($allOstriches as $ostrichObject)
				{ // go through each saucer
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
											$timesActivatedCloakingDevice = $this->getUpgradeTimesActivatedThisRound($ostrichColor, "Cloaking Device");
											if($timesActivatedCloakingDevice > 0)
											{ // they didn't crash... they used cloaking device
												// do nothing
											}
											else
											{ // they did crash
												self::incStat( 1, 'times_you_crashed', $ownerOfOstrich ); // add a that you ran off a cliff

												if(!$this->doesSaucerHaveOffColoredCrewmember($ostrichColor))
												{ // they don't have an off-colored crewmember to give away so they will not have a penalty

													$this->markCrashPenaltyRendered($ostrichColor); // make sure no one gets an energy for knocking them off
												}
											}
										}
										else
										{ // the ostrich was pushed off a cliff by the saucer taking their turn

/* Removing because we don't need to do this because we will do it in the updateGameLogForEvents method.
												self::notifyAllPlayers( "ostrichWasPushedOffCliff", clienttranslate( '${saucerWhoIsStealingText} is stealing a Crewmember from ${saucerWhoCrashedText}.' ), array(
														'player_name' => self::getActivePlayerName(),
														'ostrichName' => $this->getOstrichName($ostrichColor),
														'saucerWhoCrashedText' => $ostrichColorText,
														'saucerWhoIsStealingText' => $saucerMurdererText
												) );
*/
												self::incStat( 1, 'saucers_you_crashed', $ownerOfOstrichTakingTurn ); // add stat that the current player pushed an ostrich off a cliff
												self::incStat( 1, 'times_you_crashed', $ownerOfOstrich ); // add a stat that the owner of the ostrich who fell off the cliff was pushed off a cliff
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

		function getSaucerAcceleratorDistanceOptions($saucerColor)
		{
			$arrayOfDistance = array();

			// normally you just accelerate the distance you just moved
			$lastMovedDistance = $this->getSaucerLastDistance($saucerColor);

			if($this->doesSaucerHaveUpgradePlayed($saucerColor, "Acceleration Regulator"))
			{ // they have acceleration regulator

				array_push($arrayOfDistance, 1);
				array_push($arrayOfDistance, 2);
				array_push($arrayOfDistance, 3);
				array_push($arrayOfDistance, 4);

				if($lastMovedDistance != 0 && $lastMovedDistance != 1 && $lastMovedDistance != 2 && $lastMovedDistance != 3 && $lastMovedDistance != 4)
				{ // the last moved distnace is something other than 1-4 (or 0) so let them choose that if they wish as well
					
					array_push($arrayOfDistance, $lastMovedDistance);
				}

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, "Hyperdrive"))
				{ // they also have hyperdrive

					array_push($arrayOfDistance, 6);
					array_push($arrayOfDistance, 8);
				}
			}
			else
			{ // they don't have acceleration regulator (shouldn't get here)

				// the only button should be for their last moved distance
				array_push($arrayOfDistance, $lastMovedDistance);
			}

			// sort the array in ascending order
			sort($arrayOfDistance);

			return $arrayOfDistance;
		}

		function getSaucerBoosterDistanceOptions($saucerColor)
		{
			$arrayOfDistance = array();

			// normally you just accelerate the distance you just moved
			$lastMovedDistance = $this->getSaucerLastDistance($saucerColor);

			if($this->doesSaucerHaveUpgradePlayed($saucerColor, "Boost Amplifier"))
			{ // they have boost amplifier

				array_push($arrayOfDistance, 1);
				array_push($arrayOfDistance, 2);
				array_push($arrayOfDistance, 3);
				array_push($arrayOfDistance, 4);
				array_push($arrayOfDistance, 5);
				array_push($arrayOfDistance, 6);

				if($lastMovedDistance != 0 && $lastMovedDistance != 1 && $lastMovedDistance != 2 && $lastMovedDistance != 3 && $lastMovedDistance != 4 && $lastMovedDistance != 5 && $lastMovedDistance != 6)
				{ // the last moved distnace is something other than 1-6 (or 0) so let them choose that if they wish as well
					
					array_push($arrayOfDistance, $lastMovedDistance);
				}

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, "Hyperdrive"))
				{ // they also have hyperdrive

					array_push($arrayOfDistance, 8);
					array_push($arrayOfDistance, 10);
					array_push($arrayOfDistance, 12);
				}
			}
			else
			{ // they don't have boost amplifier (shouldn't get here)

				// the only button should be for their last moved distance
				array_push($arrayOfDistance, $lastMovedDistance);
			}

			// sort the array in ascending order
			sort($arrayOfDistance);

			return $arrayOfDistance;
		}

		function getSaucerOriginalTurnDistanceOptions($saucerColor)
		{
			$arrayOfDistance = array();


			// get the type of card they played
			$distanceType = self::getUniqueValueFromDb("SELECT ostrich_zig_distance FROM ostrich WHERE ostrich_color='$saucerColor'"); // 0, 1, 2

			$distanceInteger = 0;
			if($distanceType == 0)
			{ // X was played
				array_push($arrayOfDistance, 0);
				array_push($arrayOfDistance, 1);
				array_push($arrayOfDistance, 2);
				array_push($arrayOfDistance, 3);
				array_push($arrayOfDistance, 4);
				array_push($arrayOfDistance, 5);

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, "Hyperdrive"))
				 { // this player has Hyperdrive
					array_push($arrayOfDistance, 6);
					array_push($arrayOfDistance, 8);
					array_push($arrayOfDistance, 10);
				 }
			}
			elseif($distanceType == 1)
			{ // 2 was played
				array_push($arrayOfDistance, 2);

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, "Hyperdrive"))
				 { // this player has Hyperdrive
					array_push($arrayOfDistance, 4);
				 }
			}
			elseif($distanceType == 2)
			{ // 3 was played
				array_push($arrayOfDistance, 3);

				if($this->doesSaucerHaveUpgradePlayed($saucerColor, "Hyperdrive"))
				 { // this player has Hyperdrive
					array_push($arrayOfDistance, 6);
				 }
			}
			else
			{
				throw new feException( "Unrecognized distance type ($distanceType)");
			}

			return $arrayOfDistance;
		}

		function getSaucerDistanceType( $saucerColor )
		{
				return self::getUniqueValueFromDb("SELECT ostrich_zig_distance FROM ostrich WHERE ostrich_color='$saucerColor'"); // 0, 1, 2
		}

		// Returns the distance the Saucer most recently traveled.
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

				//$this->setSaucerOriginalTurnDistance( $ostrich, $distance ); // set the original turn distance too
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

		function getSaucerOriginalTurnDistance( $saucerColor )
		{
				return self::getUniqueValueFromDb("SELECT saucer_original_turn_distance FROM ostrich WHERE ostrich_color='$saucerColor'");
		}

		function setSaucerOriginalTurnDistance( $saucerColor, $distance )
		{
				$sqlUpdate = "UPDATE ostrich SET ";
				$sqlUpdate .= "saucer_original_turn_distance='".$distance."' WHERE ";
				$sqlUpdate .= "ostrich_color='".$saucerColor."'";

				self::DbQuery( $sqlUpdate );
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
				self::notifyAllPlayers( 'boosterAcquired', clienttranslate( '${saucer_color_text} gained a ${BOOSTERDISC}.' ), array(
								'player_id' => $player_id,
								'boosterPosition' => $boosterPosition,
								'saucerColor' => $saucerColor,
								'player_name' => $player_name,
								'saucer_color_text' => $colorHighlightedText,
								'BOOSTERDISC' => 'BOOSTERDISC'
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
				self::notifyAllPlayers( 'energyAcquired', clienttranslate( '${saucer_color_text} gained a ${ENERGYCUBE}.' ), array(
								'player_id' => $player_id,
								'energyPosition' => $energyPosition,
								'saucerColor' => $saucerColor,
								'player_name' => $player_name,
								'saucer_color_text' => $colorHighlightedText,
								'ENERGYCUBE' => 'ENERGYCUBE'
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

		function getSaucerHoldingCrewmemberId($crewmemberId)
		{
				return self::getUniqueValueFromDb("SELECT garment_location FROM garment WHERE garment_id=$crewmemberId");
		}

		function getSaucerHoldingCrewmemberTypeColor($garmentTypeAsString, $garmentColor)
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
						case "b83a4b":
							return "RED";

						case "f8e946":
							return "YELLOW";

            case "009add":
							return "BLUE";

            case "228848":
							return "GREEN";

						case "753bbd":
							return "PURPLE";

						case "ff3eb5":
							return "PINK";
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
				$saucerWhoseTurnItIs = $this->getSaucerWhoseTurnItIs(); // the saucer whose turn it is (empty string if we don't know)
				$ownerOfSaucerWhoseTurnItIs = $this->getOwnerIdOfOstrich($saucerWhoseTurnItIs); // get the player whose turn it is
				$activePlayer = self::getActivePlayerId(); // Current Player = player who played the current player action (the one who made the AJAX request). Active Player = player whose turn it is.


//throw new feException( "saucerWhoseTurnItIs:$saucerWhoseTurnItIs activePlayer:$activePlayer");
				if($ownerOfSaucerWhoseTurnItIs != $activePlayer)
				{ // we entered into this state after another player took an action on this player's turn, like
					// someone was pushed onto a crewmember and got to airlock it before the current player turn continued

						// reset control of turn to player whose turn it is
						$this->gamestate->changeActivePlayer($ownerOfSaucerWhoseTurnItIs);
				}

				$nextSaucerWithPendingCrashReward = $this->nextPendingCrashReward($saucerWhoseTurnItIs);
				$needToPlaceCrewmember = $this->doesCrewmemberNeedToBePlaced();
				//echo "needToPlaceCrewmember is ($needToPlaceCrewmember) for ostrich $saucerWhoseTurnItIs <br>";
				//echo "nextSaucerWithPendingCrashReward is ($nextSaucerWithPendingCrashReward) for ostrich $saucerWhoseTurnItIs <br>";
				//throw new feException( "nextSaucerWithPendingCrashReward:$nextSaucerWithPendingCrashReward needToPlaceCrewmember:$needToPlaceCrewmember");

				//$hasPendingCrashPenalty = $this->hasPendingCrashPenalty($saucerWhoseTurnItIs);
				//$skippedGivingAway = $this->getSkippedGivingAway($saucerWhoseTurnItIs);
				//throw new feException( "hasPendingCrashPenalty:$hasPendingCrashPenalty skippedGivingAway:$skippedGivingAway");
				//throw new feException( "afternotify");
				if($this->isEndGameConditionMet())
				{ // the game has ended
					$this->goToEndGame(); // end the game
				}
				elseif($this->hasPendingCrashPenalty($saucerWhoseTurnItIs) && $this->getSkippedGivingAway($saucerWhoseTurnItIs) != 1)
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
//throw new feException( "endMovementTurn");
// somewhere before this, the state is getting set to state 50 (endSaucerTurnCleanUp)
							$this->gamestate->nextState( "endSaucerTurn" );
				}
		}

		function goToEndGame()
		{
			$saucerWhoseTurnItIs = $this->getSaucerWhoseTurnItIs();

			// add one to turn count for current player because normally this only get incremented at the end of a turn
			$saucerOwner = $this->getOwnerIdOfOstrich($saucerWhoseTurnItIs); // get the owner of the ostrich
			self::incStat( 1, 'turns_number', $saucerOwner ); // increase end game player stat

			$this->gamestate->nextState( "endGame" );
		}

		// Note: A pushed saucer does not go in here.
		function setState_AfterMovementEvents($saucerMoving, $moveType, $wasPushed=false)
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				$currentState = $this->getStateName();

				// reset pushed setting so we don't think a saucer was pushed when we do our next move
				$this->resetPushedForAllSaucers();

//throw new feException("setState_AfterMovementEvents with saucerWhoseTurnItIs ($saucerWhoseTurnItIs) saucerMoving ($saucerMoving) moveType ($moveType) wasPushed ($wasPushed).");
				$boardValue = $this->getBoardSpaceTypeForOstrich($saucerWhoseTurnItIs); // get the type of space of the ostrich who just moved

				if($boardValue != "S")
				{
					// since saucers don't get moved in the DOM while moving, we need to put them in the right spot
					// before any special cases happen post-move... but we don't want to reset them if we're on an 
					// accelerator because then the saucer won't be on the accelerator
					// THIS DIDN'T WORK BECAUSE IT MADE ANIMATIONS VERY JUMPY AND TOO QUICK IN MANY PLACES
					//$this->resetSaucersInDOM();
				}

				// count crewmembers they can exchange with Airlock if they have it
				$airlockExchangeableCrewmembers = $this->getAirlockExchangeableCrewmembers();
				//$count = count($airlockExchangeableCrewmembers);
				//throw new feException("airlockExchangeableCrewmembers count ($count).");

				/*
				if($this->isEndGameConditionMet())
				{ // the game has ended
					$this->goToEndGame(); // end the game
				}
				else
				*/ 
				if($boardValue == "S")
				{ // the saucer onto an accelerator on their turn
					//throw new feException("accelerator");
					if($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Acceleration Regulator") &&
		  			   $this->isUpgradePlayable($saucerMoving, 'Acceleration Regulator') && 
					   !$wasPushed)
					{ // acceleration regulator is played and it doesn't have summoning sickness
						$this->gamestate->nextState( "chooseAcceleratorDistance" ); // need to ask the player which direction they want to go on the accelerator
					}
					else
					{
						$this->gamestate->nextState( "chooseAcceleratorDirection" ); // need to ask the player which direction they want to go on the accelerator
					}
				}
				else if($this->canSaucerBoost($saucerWhoseTurnItIs) && $this->getSkippedBoosting($saucerWhoseTurnItIs) == 0 && $moveType == 'regular')
				{ // the player has a boost they can use and they have not crashed
						$this->gamestate->nextState( "chooseIfYouWillUseBooster" ); // need to ask the player if they want to use a zag, and if so, which direction they want to travel
				}
				elseif(count($airlockExchangeableCrewmembers) > 0)
				{ // this saucer has at least one crewmember they can exchange


						if($saucerMoving != $saucerWhoseTurnItIs)
						{ // the moving saucer was pushed into picking up a crewmember (this shouldn't happen because pushed saucers don't come in this method)
								$ownerOfSaucerMoving = $this->getOwnerIdOfOstrich($saucerMoving);
								$this->gamestate->changeActivePlayer($ownerOfSaucerMoving);
						}
						
						$this->gamestate->nextState( "chooseCrewmemberToAirlock" );
				}
				else if($this->canSaucerPassCrewmembers($saucerWhoseTurnItIs) && $moveType != "Blast Off Thrusters")
				{ // they passed by their own Saucer and can pass them a Crewmember
						$this->gamestate->nextState( "chooseCrewmembersToPass" ); // need to ask the player if they want to use a zag, and if so, which direction they want to travel
				}
				else if($this->canSaucerTakeCrewmembers($saucerWhoseTurnItIs) && $moveType != "Blast Off Thrusters")
				{ // they passed by their other Saucer and can take from them
						$this->gamestate->nextState( "chooseCrewmembersToTake" ); // need to ask the player if they want to use a zag, and if so, which direction they want to travel
				}
				else if($currentState == "crashPenaltyAskWhichToSteal")
				{ // they were just asked which penalty they wanted for crashing someone
//throw new feException("crashPenaltyAskWhichToSteal ($currentState).");
						$this->gamestate->nextState( "endSaucerTurnCleanUp" );
				}
				else
				{ // movement is complete
						if($moveType == 'Landing Legs' || $moveType == 'Afterburner' || $moveType == 'Organic Triangulator')
						{ // this is a bonus move from an Upgrade

								//$currentState = $this->getStateName();
								//throw new feException("landing legs or afterburner currentState:$currentState");

								// reset the upgrade value_1 _2 _3 _4 for all saucers so we know movement for Landing Legs is complete
								$this->resetAllUpgradeValues();
//throw new feException("moveType ($moveType).");
								// we don't want them to be able to undo their turn to skip finalizeMove
								$this->gamestate->nextState( "endSaucerTurnCleanUp" );
						}
						elseif($moveType == 'Blast Off Thrusters')
						{
								// see if we have any other start of turn upgrades we want to use
								$this->gamestate->nextState( "checkStartOfTurnUpgrades" );
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
				$saucerWhoseTurnItIs = $this->getSaucerWhoseTurnItIs();
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

		// We want to rotate a tile with Quake Maker (or could work for other rotations wish some small modifications).
		// tilePosition: the tile we want to rotate
		// timesRotated: how many times we want to rotate it 90 degrees clockwise, so "3" would be 270 degrees clockwise.
		function executeRotateTile($tilePosition, $timesRotated)
		{
				// rotate a tile chosen by Quake Maker

				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				// mark that we have activated Quake Maker
				$this->activateUpgradeWithCollectorNumber($saucerWhoseTurnItIs, 18);

				// find the board tile the victim ostrich is on
				//$tilePosition = $this->getTilePositionOfOstrich($trappedOstrich); // get which tile they are on
				$tileNumber = $this->getTileNumber($tilePosition); // find the number of that tile

				$sideOfTile = $this->getTileSide($tilePosition); // get whether this tile is on side A or B

				$oldDegreeRotation = $this->getTileRotation($tilePosition); // tile_degree_rotation
				$degreeRotation = $oldDegreeRotation + $timesRotated;
				if($degreeRotation > 3)
				{
					$degreeRotation = $degreeRotation - 4;
				}
				//throw new feException( "oldDegreeRotation:$oldDegreeRotation degreeRotation: $degreeRotation tilePosition:$tilePosition tileNumber: $tileNumber");

				// convert this to an integer 1 or 0
				$useSideA = 1;
				if($sideOfTile == "B")
				{ // this tile is on side B
						$useSideA = 0;
				}

				// rotate that tile
				//echo "setting board tile number $tileNumber at position $tilePosition with useSideA of $useSideA and degree rotation $degreeRotation";
				$this->setBoardTile($tileNumber, $useSideA, $degreeRotation, $tilePosition); // update the board table with each new space value
				$this->updateTileRotations($tileNumber, $degreeRotation); // also update the tile table with the new degree rotation


				//$this->rotateOstriches($tileNumber, true); // rotate any ostriches on it clockwise
				$this->rotateSaucersNumberOfTimes($tileNumber, $timesRotated);
				//$this->rotateGarments($tileNumber, true); // rotate any garments on it clockwise
				$this->rotateCrewmembersNumberOfTimes($tileNumber, $timesRotated);

				$saucerMovingHighlightedText = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);

				// notify players of what changed
				self::notifyAllPlayers( "executeTrapRotateTile", clienttranslate( '${saucerMovingHighlightedText} rotated a board tile.' ), array(
									'saucerMovingHighlightedText' => $saucerMovingHighlightedText,
									'tileNumber' => $tileNumber,
									'oldDegreeRotation' => $oldDegreeRotation,
									'newDegreeRotation' => $degreeRotation,
									'tileSide' => $sideOfTile,
									'tilePosition' => $tilePosition
				) );

				$this->gamestate->nextState( "endSaucerTurnCleanUp" );
		}

		function executePulseCannonSelectSaucer($saucerColor)
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$mySaucerX = $this->getSaucerXLocation($saucerWhoseTurnItIs);
				$mySaucerY = $this->getSaucerYLocation($saucerWhoseTurnItIs);

				$chosenSaucerX = $this->getSaucerXLocation($saucerColor);
				$chosenSaucerY = $this->getSaucerYLocation($saucerColor);

				$cardId = $this->getUpgradeCardId($saucerWhoseTurnItIs, "Pulse Cannon");

//throw new feException( "cardId:$cardId with saucerWhoseTurnItIs: $saucerWhoseTurnItIs saucerColor:$saucerColor");
				$this->setUpgradeValue1($cardId, 1); // 1 distance
				$this->setUpgradeValue2($cardId, "Pulse Cannon");
				$this->setUpgradeValue4($cardId, $saucerColor); // save the saucer we are pushing

				if($mySaucerX == $chosenSaucerX)
				{ // the saucer is on my column
//throw new feException( "the saucer is on my column");
						if($mySaucerY < $chosenSaucerY)
						{	// i am lower than the saucer
								$this->setPushedOnSaucerTurn($saucerColor, $saucerWhoseTurnItIs);

								$this->setUpgradeValue3($cardId, $this->DOWN_DIRECTION);


								//$this->setPushedDistance($saucerColor, 1);
								//$this->setPushedDirection($saucerColor, $this->UP_DIRECTION);

						}
						else
						{ // i am higher than the saucer
								$this->setPushedOnSaucerTurn($saucerColor, $saucerWhoseTurnItIs);

								$this->setUpgradeValue3($cardId, $this->UP_DIRECTION);


								//$this->setPushedDistance($saucerColor, 1);
								//$this->setPushedDirection($saucerColor, $this->DOWN_DIRECTION);
						}
				}
				elseif($mySaucerY == $chosenSaucerY)
				{ // the saucer is on my row
//throw new feException( "the saucer is on my row");
						if($mySaucerX < $chosenSaucerX)
						{	// i am to the left of the saucer
								$this->setPushedOnSaucerTurn($saucerColor, $saucerWhoseTurnItIs);

								$this->setUpgradeValue3($cardId, $this->RIGHT_DIRECTION);

								//$this->setPushedDistance($saucerColor, 1);
								//$this->setPushedDirection($saucerColor, $this->RIGHT_DIRECTION);
						}
						else
						{ // i am to the right of the saucer
								$this->setPushedOnSaucerTurn($saucerColor, $saucerWhoseTurnItIs);

								$this->setUpgradeValue3($cardId, $this->LEFT_DIRECTION);


								//$this->setPushedDistance($saucerColor, 1);
								//$this->setPushedDirection($saucerColor, $this->LEFT_DIRECTION);
						}
				}

				// update the X and Y and notify all players
				//$this->placeSaucerOnSpace($saucerColor, $mySaucerX, $mySaucerY);
				//$this->placeSaucerOnSpace($saucerWhoseTurnItIs, $chosenSaucerX, $chosenSaucerY);

				// mark that we have activated it
				$this->activateUpgradeWithCollectorNumber($saucerWhoseTurnItIs, 4);

				if(($this->doesSaucerHaveUpgradePlayed($saucerWhoseTurnItIs, "Kinetic Siphon") &&
					$this->getUpgradeTimesActivatedThisRound($saucerWhoseTurnItIs, "Kinetic Siphon") < 1 &&
					$this->isUpgradePlayable($saucerWhoseTurnItIs, 'Kinetic Siphon')))
				{ // this saucer has kinetic siphon played

					$this->giveSaucerBooster($saucerWhoseTurnItIs); // give them a booster
					$this->activateUpgradeWithCollectorNumber($saucerWhoseTurnItIs, 14); // make as played so we don't give it to them more than once/turn
				}

				$this->gamestate->nextState( "executingMove" );
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

		function executeActivateWasteAccelerator()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				$this->activateUpgrade($saucerWhoseTurnItIs, "Waste Accelerator");

				//throw new feException( "executeSkipPhaseShifter");

				if($this->doesSaucerHaveUpgradePlayed($saucerWhoseTurnItIs, "Acceleration Regulator") &&
		  			   $this->isUpgradePlayable($saucerWhoseTurnItIs, 'Acceleration Regulator'))
				{ // acceleration regulator is played and it doesn't have summoning sickness
						$this->gamestate->nextState( "chooseAcceleratorDistance" ); // need to ask the player which direction they want to go on the accelerator
				}
				else
				{
					// let them choose the direction they will travel on this "accelerator"
					$this->gamestate->nextState( "chooseAcceleratorDirection" );
				}
		}

		function executeSkipWasteAccelerator()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				$distance = $this->getSaucerLastDistance($saucerWhoseTurnItIs);
				$spacesMoved = $this->getSpacesMoved($saucerWhoseTurnItIs);
				$spacesLeft = $distance - $spacesMoved;

				//echo "spacesLeft:$spacesLeft";

				if($spacesLeft == 0)
				{ // this saucer is done moving
					//throw new feException( "skipping");
					// set asked_to_activate_this_round to 1 so we don't ask again... only needed if they end their movement on a Crash Site
					$this->setAskedToActivateUpgrade($saucerWhoseTurnItIs, "Waste Accelerator");
				}

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
				self::incStat( 1, 'times_you_crashed', $ownerOfMurderee ); // add a stat that the owner of the ostrich who fell off the cliff was pushed off a cliff

				// move them off the board and notify players
				$this->placeSaucerOnSpace($saucerCrashed, 0, 0);

				// say they're already been penalized for "crashing" so no one gets a reward for it
				//$this->markCrashPenaltyRendered($saucerCrashed);

				// reset pushed setting so we don't think a saucer was pushed when we do our next move
				$this->resetPushedForAllSaucers();

				//throw new feException( "executeSkipPhaseShifter");

				// figure out which type of move this is
				$moveType = $this->getMoveTypeWeAreExecuting();

				$this->setState_AfterMovementEvents($saucerWhoseTurnItIs, $moveType);
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

		// Coming from using either: 
		//    Regeneration Gateway: When your Saucer is placed, you choose the Crash Site.
		//    Saucer Teleporter: At the end of your turn, if you have not crashed, move to any empty Crash Site.
		function executeChooseCrashSite( $crashSiteNumber )
		{
				$saucerToPlace = $this->getSaucerWhoseTurnItIs();
				$playerWhoseTurnItIs = $this->getOwnerIdOfOstrich($saucerToPlace);

				$crashSiteX = $this->getXOfCrashSite($crashSiteNumber);
				$crashSiteY = $this->getYOfCrashSite($crashSiteNumber);

				

				$currentState = $this->getStateName();
				if($currentState == 'chooseCrashSiteSaucerTeleporter')
				{ // we are choosing a Crash Site from Saucer Teleporter

					// place the saucer whose turn it is
					$this->placeSaucerOnSpace($saucerToPlace, $crashSiteX, $crashSiteY);

					$this->gamestate->nextState( "endSaucerTurnCleanUp" );
				}
				elseif($currentState == 'chooseCrashSiteRegenerationGateway')
				{ // we are choosing a Crash Site from Regeneration Gateway
					
					$saucerToPlace = $this->getLocationOfUpgradeCard("Regeneration Gateway");

					$stateUsedIn = $this->getUpgradeValue5($saucerToPlace, "Regeneration Gateway");

					$this->placeSaucerOnSpace($saucerToPlace, $crashSiteX, $crashSiteY);

					// award if they have Scavenger Bot
					if($this->doesSaucerHaveUpgradePlayed($saucerToPlace, 'Scavenger Bot') &&
					$this->isUpgradePlayable($saucerToPlace, 'Scavenger Bot'))
					{ // this saucer has Scavenger Bot

						// give a Booster
						$this->giveSaucerBooster($saucerToPlace);								
			
						// give an Energy
						$this->giveSaucerEnergy($saucerToPlace);
					}
					

					if($stateUsedIn == "BEFORE_TURN")
					{ // this is being used before the player's turn

						if($this->getSaucersPerPlayer() == 1)
						{ // players are only controlling a single saucer
		//throw new feException( "1 saucer per player." );
		
							// now this saucer can choose any direction
							$this->setCanChooseDirection($saucerToPlace, 1);
							$this->gamestate->nextState( "beginTurn" );
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
					else
					{ // this is being used at the end of the round

						// continue the end round clean-up
						$this->gamestate->nextState( "endRoundCleanUp");
					}
				}

		}

		function executeTractorBeamCrewmember($crewmemberType, $crewmemberColor)
		{
			//throw new feException( "executeTractorBeamCrewmember");
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerColorHighlightedText = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);

				self::notifyAllPlayers( "tractorBeamUsed", clienttranslate( '${saucerColorHighlightedText} used Tractor Beam.' ), array(
					'saucerColorHighlightedText' => $saucerColorHighlightedText
				) );

				$this->moveCrewmemberToSaucerMat($saucerWhoseTurnItIs, $crewmemberType, $crewmemberColor);

				// increment stat for picking up crewmembers
				$ownerPickingUp = $this->getOwnerIdOfOstrich($saucerWhoseTurnItIs);
				self::incStat( 1, 'crewmembers_picked_up', $ownerPickingUp );

				$crewmemberId = $this->getCrewmemberIdFromColorAndTypeText($crewmemberColor, $crewmemberType);
				$this->saveRoundPickedUp($crewmemberId);

				if($this->isEndGameConditionMet())
				{ // the game has ended
					$this->goToEndGame(); // end the game
				}
				else
				{
					$this->gamestate->nextState( "endSaucerTurnCleanUp" );
				}
		}

		// Called when a player chooses a Distress Signaler crewmember to TAKE.
		// We need to move a Crewmember from a target saucer to the Distress Signaler's saucer.
		// The arguments are the crewmember type ("pilot") and color ("b83a4b") being taken.
		function executeDistressSignalerTakeCrewmember($crewmemberType, $crewmemberColor)
		{
			//throw new feException( "crewmemberType:$crewmemberType crewmemberColor:$crewmemberColor");
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerColorHighlightedText = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);

				self::notifyAllPlayers( "distressSignalerUsed", clienttranslate( '${saucerColorHighlightedText} used Distress Signaler.' ), array(
					'saucerColorHighlightedText' => $saucerColorHighlightedText
				) );

				// get the ID of the crewmember taken
				$crewmemberId = $this->getGarmentIdFromType($crewmemberType, $crewmemberColor);

				// get the original saucer holding that Crewmember
				$saucerHoldingTakenCrewmember = $this->getCrewmemberLocationFromId($crewmemberId);

				// mark this Saucer as having given away a crewmember
				$this->setSaucerGivenWithDistress($saucerHoldingTakenCrewmember, 1);

				// mark this Crewmember as taken so we know which one to swap with it
				$this->setCrewmemberTakenWithDistress($crewmemberId, 1);

				// update the database and tell the UI about the crewmember moving to the saucer
				$this->moveCrewmemberToSaucerMat($saucerWhoseTurnItIs, $crewmemberType, $crewmemberColor);

				// increment stat for picking up crewmembers
				$ownerPickingUp = $this->getOwnerIdOfOstrich($saucerWhoseTurnItIs);
				self::incStat( 1, 'crewmembers_you_stole', $ownerPickingUp );
				$previousOwner = $this->getOwnerIdOfOstrich($saucerHoldingTakenCrewmember);
				self::incStat( 1, 'crewmembers_stolen_from_you', $previousOwner );

				// move to the state where they can choose which to give (don't try to skip this... we want smooth transitions)
				$this->gamestate->nextState( "chooseDistressSignalerGiveCrewmember" );
		}

		// Called when a player chooses a Distress Signaler crewmember to GIVE.
		function executeDistressSignalerGiveCrewmember($crewmemberType, $crewmemberColor)
		{
				// get the Crewmember ID we preiously took (taken_with_distress=1)
				$crewmemberTakenId = $this->getCrewmemberIdTakenWithDistress();

				// get the saucer who had a Crewmember taken from them
				$saucerWhoHeldTakenCrewmember = $this->getSaucerGivenWithDistress();

	//throw new feException( "crewmemberTakenId $crewmemberTakenId");

				// reset crewmember taken with distress signaler since we might have another one we can take
				$this->setCrewmemberTakenWithDistress($crewmemberTakenId, 0);

				// reset this Saucer as having given away a crewmember
				$this->setSaucerGivenWithDistress($saucerWhoHeldTakenCrewmember, 0);

				// update the database and tell the UI about the crewmember moving to the saucer
				$this->moveCrewmemberToSaucerMat($saucerWhoHeldTakenCrewmember, $crewmemberType, $crewmemberColor);

				//throw new feException( "afternotify");
				if($this->isEndGameConditionMet())
				{ // the game has ended
					$this->goToEndGame(); // end the game
				}
				else
				{
					$this->gamestate->nextState( "endSaucerTurnCleanUp" );
				}
		}

		// Moves a Crewmember from the board or another saucer to a new saucer.
		function moveCrewmemberToSaucerMat($saucerColorReceiving, $crewmemberType, $crewmemberColor)
		{
				$crewmemberId = $this->getGarmentIdFromType($crewmemberType, $crewmemberColor);
				$crewmemberColor = $this->getCrewmemberColorFromId($crewmemberId);
				$crewmemberTypeId = $this->getCrewmemberTypeIdFromId($crewmemberId);
				$crewmemberType = $this->convertGarmentTypeIntToString($crewmemberTypeId);

				//echo "The garment at ($thisX,$currentY) is: $garmentId <br>";

				// before we change anything in the database, check if we already have a primary for this crewmember type
				$currentPrimaryCrewmemberId = $this->getPrimaryCrewmemberId($saucerColorReceiving, $crewmemberTypeId);

				// see where this crewmember is (board, saucer, etc.) before the move to the saucer
				$currentLocation = $this->getCrewmemberLocationFromId($crewmemberId);
				//$saucerGivingPrimaryCrewmemberId = $this->getPrimaryCrewmemberId($currentLocation, $crewmemberTypeId);

				if($currentLocation != "board" && $currentLocation != "pile")
				{ // saucer to saucer transfer
					// go through each crewmember for the GIVING saucer and set the primary and extras to make sure it's accurate
					$this->setCrewmemberPrimaryAndExtras($currentLocation, $crewmemberTypeId);
				}

				// see if it was primary on the originating saucer (if it's coming from a saucer)
				$wasPreviouslyPrimary = $this->isPrimaryCrewmember($crewmemberId);
				//throw new feException( "wasPreviouslyPrimary for crewmember ($crewmemberId): $wasPreviouslyPrimary saucerGivingPrimaryCrewmemberId: $saucerGivingPrimaryCrewmemberId");

				

				// give the garment to the saucer in the database (set garment_location to the color)
				$this->giveCrewmemberToSaucer($crewmemberId, $saucerColorReceiving);

				$saucerMovingHighlightedText = $this->convertColorToHighlightedText($saucerColorReceiving);
				$isPrimary = $this->isPrimaryCrewmember($crewmemberId);
				//throw new feException( "isPrimary for crewmember ($crewmemberId): $isPrimary");
				if($isPrimary)
				{ // this crewmember will be going to the saucer mat for this crewmember type
//throw new feException( "currentPrimaryCrewmemberId:$currentPrimaryCrewmemberId crewmemberId:$crewmemberId");
						if($currentPrimaryCrewmemberId !== '' &&
							 $crewmemberId !== $currentPrimaryCrewmemberId)
						{ // we want to replace the current crewmember of this type on this mat with the new one
//throw new feException( "extras currentPrimaryCrewmemberId:$currentPrimaryCrewmemberId");

								$currentPrimaryCrewmemberColor = $this->getCrewmemberColorFromId($currentPrimaryCrewmemberId);
								$currentPrimaryCrewmemberTypeId = $this->getCrewmemberTypeIdFromId($currentPrimaryCrewmemberId);
								$currentPrimaryCrewmemberType = $this->convertGarmentTypeIntToString($currentPrimaryCrewmemberTypeId);
								// move the existing primary to the extras
								self::notifyAllPlayers( "moveCrewmemberToSaucerExtras", '', array(
									'crewmemberType' => $currentPrimaryCrewmemberType,
									'crewmemberColor' => $currentPrimaryCrewmemberColor,
									'sourceSaucerColor' => $currentLocation,
									'destinationSaucerColor' => $saucerColorReceiving,
									'isPrimary' => $isPrimary
								) );

						}

						//throw new feException( "crewmemberId: $crewmemberId currentLocation:$currentLocation wasPreviouslyPrimary:$wasPreviouslyPrimary");
						if($currentLocation != "board" && $currentLocation != "pile" &&
							$wasPreviouslyPrimary)
						{ // this is moving from a Saucer to another Saucer and it was Primary on the other saucer

								// go through each crewmember for the GIVING saucer and set the primary and extras
								$this->setCrewmemberPrimaryAndExtras($currentLocation, $crewmemberTypeId);

								// get the new primary on the saucer giving the crewmember
								$newPrimaryCrewmemberId = $this->getPrimaryCrewmemberId($currentLocation, $crewmemberTypeId);

								//throw new feException( "newPrimaryCrewmemberId:$newPrimaryCrewmemberId currentLocation:$currentLocation crewmemberTypeId:$crewmemberTypeId");

								if($newPrimaryCrewmemberId != "")
								{ // there is an extras that can slide into the primary slot

										$newPrimaryCrewmemberColor = $this->getCrewmemberColorFromId($newPrimaryCrewmemberId);
										$newPrimaryCrewmemberTypeId = $this->getCrewmemberTypeIdFromId($newPrimaryCrewmemberId);
										$newPrimaryCrewmemberType = $this->convertGarmentTypeIntToString($newPrimaryCrewmemberTypeId);

										self::notifyAllPlayers( "moveCrewmemberToSaucerPrimary", '', array(
											'crewmemberType' => $newPrimaryCrewmemberType,
											'crewmemberColor' => $newPrimaryCrewmemberColor,
											'sourceSaucerColor' => $currentLocation,
											'destinationSaucerColor' => $currentLocation,
											'isPrimary' => true
										) );

								}
						}


//throw new feException( "after currentPrimaryCrewmemberId:$currentPrimaryCrewmemberId crewmemberId:$crewmemberId");
						// move this crewmember to the saucer mat for this crewmember type
						self::notifyAllPlayers( "moveCrewmemberToSaucerPrimary", clienttranslate( '${saucerMovingHighlightedText} picked up ${CREWMEMBERIMAGE}.' ), array(
							'saucerMovingHighlightedText' => $saucerMovingHighlightedText,
							'CREWMEMBERIMAGE' => 'CREWMEMBERIMAGE_'.$crewmemberType.'_'.$crewmemberColor,
							'crewmemberType' => $crewmemberType,
							'crewmemberColor' => $crewmemberColor,
							'sourceSaucerColor' => $currentLocation,
							'destinationSaucerColor' => $saucerColorReceiving,
							'isPrimary' => $isPrimary
						) );
				}
				else
				{ // this crewmember will be going to the extras space on the mat

						// move this crewmember to the extras for this saucer
						self::notifyAllPlayers( "moveCrewmemberToSaucerExtras", '', array(
							'crewmemberType' => $crewmemberType,
							'crewmemberColor' => $crewmemberColor,
							'sourceSaucerColor' => $currentLocation,
							'destinationSaucerColor' => $saucerColorReceiving,
							'isPrimary' => $isPrimary
						) );
				}

				$this->updatePlayerScores(); // update the player boards with current scores
		}

		function executeAirlockCrewmember($crewmemberType, $crewmemberColor)
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerColorHighlightedText = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);

				self::notifyAllPlayers( "airlockUsed", clienttranslate( '${saucerColorHighlightedText} used Airlock.' ), array(
					'saucerColorHighlightedText' => $saucerColorHighlightedText
				) );

				$crewmemberTypeId = $this->convertGarmentTypeStringToInt($crewmemberType);
				$crewmemberOnBoardId = $this->getCrewmemberIdFromColorAndType($crewmemberColor, $crewmemberTypeId);
				$crashSiteX = $this->getGarmentXLocation($crewmemberOnBoardId);
				$crashSiteY = $this->getGarmentYLocation($crewmemberOnBoardId);

				// give the crewmember to the saucer int he DB and notify the UI
				$this->moveCrewmemberToSaucerMat($saucerWhoseTurnItIs, $crewmemberType, $crewmemberColor);

				// get the ID of the highest crewmember available for exchange (in case they picked up 2 on the same turn)
				$crewmemberIdTaken = $this->getHighestAirlockExchangeableId();

				//throw new feException( "executeAirlockCrewmember crewmemberIdTaken: $crewmemberIdTaken");

				// mark this crewmember as taken so we don't offer another exchange with it
				$this->removeAirlockExchangeableForCrewmember($crewmemberIdTaken);

				// place the Crewmember they originally took
				$this->placeCrewmemberOnSpace($crewmemberIdTaken, $crashSiteX, $crashSiteY);

				if($this->isEndGameConditionMet())
				{ // the game has ended
					$this->goToEndGame(); // end the game
				}
				else
				{
					// allow them to undo if they wish
					$this->gamestate->nextState( "finalizeMove" );
				}
		}

		function executeSkipAirlock()
		{
			$saucerWhoseTurnItIs = $this->getSaucerWhoseTurnItIs();

			// do not ask if this player wants to airlock their crewmembers again
			$this->removeAirlockExchangeableForAll($saucerWhoseTurnItIs);

			// allow them to undo if they wish
			$this->gamestate->nextState( "finalizeMove" );
		}

		function executeStealCrewmember( $stolenTypeText, $stolenColor, $areWePassing, $areWeTaking )
		{
				$saucerReceiving = $this->getOstrichWhoseTurnItIs();
				$saucerGiving = $this->getSaucerThatCrashed(true);

				$moveType = $this->getMoveTypeWeAreExecuting();
				
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

				$saucerReceivingHighlightedText = $this->convertColorToHighlightedText($saucerReceiving);
				$saucerGivingHighlightedText = $this->convertColorToHighlightedText($saucerGiving);
				self::notifyAllPlayers( "stealing", clienttranslate( '${saucerReceivingHighlightedText} is taking a Crewmember from ${saucerGivingHighlightedText}.' ), array(
					'saucerGivingHighlightedText' => $saucerGivingHighlightedText,
					'saucerReceivingHighlightedText' => $saucerReceivingHighlightedText
				) );

				//throw new feException( "executeStealCrewmember");
				// give the crewmember to the saucer in the DB and notify the UI
				$this->moveCrewmemberToSaucerMat($saucerReceiving, $stolenTypeText, $stolenColor);

				$crewmemberId = $this->getCrewmemberIdFromColorAndTypeText($stolenColor, $stolenTypeText);
				$this->saveRoundPickedUp($crewmemberId);

				// mark that the reward for this crash has been acquired so we don't let them have multiple rewards

				if($this->isEndGameConditionMet())
				{ // the game has ended

					if(!$areWePassing && !$areWeTaking)
					{
						// increment any stats related to thefts
						$ownerOfStealer = $this->getOwnerIdOfOstrich($saucerReceiving);
						$ownerOfStealee = $this->getOwnerIdOfOstrich($saucerGiving);
						self::incStat( 1, 'crewmembers_you_stole', $ownerOfStealer ); // add stat that says the player using played a trap
						self::incStat( 1, 'crewmembers_stolen_from_you', $ownerOfStealee ); // add stat that the owner of the ostrich targeted was targeted by a trap
					}

					$this->goToEndGame(); // end the game
				}
				elseif($areWePassing)
				{ // we are passing a Crewmember to our other Saucer
					
					$this->setState_AfterMovementEvents($saucerGiving, $moveType, true); // set to true because we're already passed the boosting if we're passing so that is safest

				}
				elseif($areWeTaking)
				{
					$this->setState_AfterMovementEvents($saucerReceiving, $moveType, true); // set to true because we're already passed the boosting if we're taking so that is safest
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

		function executeGiveAwayCrewmember($crewmemberTypeText, $crewmemberColor, $saucerReceiving)
		{
				$saucerGiving = $this->getOstrichWhoseTurnItIs();
				$saucerGivingHighlightedText = $this->convertColorToHighlightedText($saucerGiving);
				$saucerReceivingHighlightedText = $this->convertColorToHighlightedText($saucerReceiving);

				self::notifyAllPlayers( "givingAway", clienttranslate( '${saucerGivingHighlightedText} is passing a Crewmember to ${saucerReceivingHighlightedText}.' ), array(
					'saucerGivingHighlightedText' => $saucerGivingHighlightedText,
					'saucerReceivingHighlightedText' => $saucerReceivingHighlightedText
				) );

				//throw new feException( "moveCrewmemberToSaucerMat $saucerReceiving $crewmemberTypeText $crewmemberColor");

				// give the crewmember to the saucer int he DB and notify the UI
				$this->moveCrewmemberToSaucerMat($saucerReceiving, $crewmemberTypeText, $crewmemberColor);

				// mark that the reward for this crash has been acquired so we don't let them have multiple rewards
				$this->markCrashPenaltyRendered($saucerGiving);

				if($this->isEndGameConditionMet())
				{ // the game has ended
					$this->goToEndGame(); // end the game
				}
				else
				{
					$this->gamestate->nextState( "endSaucerTurnCleanUp" );
				}
		}

		function executeChooseOstrichToGoNext()
		{
				$ostrich = $this->getOstrichWhoseTurnItIs();
				$this->setSaucerToChosen($ostrich);

				$this->gamestate->nextState( "zigChosen" ); // stay in this phase
		}

		// Clicked Saucer button during Saucer placement to put them on a random Crash Site.
		function executeClickedSaucerToPlace($colorAsHex)
		{
				$currentState = $this->getStateName();

				// place it at a random location
				$foundUnoccupiedCrashSite = $this->randomlyPlaceSaucer($colorAsHex);

				if($this->doesSaucerHaveUpgradePlayed($colorAsHex, 'Scavenger Bot') &&
				$this->isUpgradePlayable($colorAsHex, 'Scavenger Bot'))
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

								// go to begin turn and they will be asked their direction before they move
								$this->setCanChooseDirection($colorAsHex, 1);
								$this->gamestate->nextState( "beginTurn" );
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
				$this->setSaucerToChosen($colorHex);

				$this->updateTurnOrder(0); // clockwise/counter doesn't matter because this only happens in 2-player games

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


				// since saucers don't get moved in the DOM while moving, we need to put them in the right spot
				// before anything gets spawned to avoid a saucer being in the same space as a crewmember or another saucer
				$this->resetSaucersInDOM();

				
				// notify all players that this move is finished so the card can be returned to hand
				self::notifyAllPlayers( "confirmedMovement", '', array(
            'saucer_color' => $saucerWhoseTurnItIs
		        ) );

				$this->gamestate->nextState( "endSaucerTurnCleanUp" );
		}

		function executeClickedUndoConfirmMove( $saucer1Color, $saucer2Color )
    	{
				// Check that this is the player's turn and that it is a "possible action" at this game state (see states.inc.php)
        		self::checkAction( 'undoConfirmMove', false );
//throw new feException( "Clicked confirm move saucer1Distance=$saucer1Distance and saucer1Direction=$saucer1Direction.");
				$playerUndoing = $this->getOwnerIdOfOstrich($saucer1Color);

				// update the database so we know this card is unchosen
				$this->unchooseCardsForSaucer($saucer1Color);

				// tell everyone this is chosen so the card back can be placed on this saucer mat
				$this->notifyPlayersOfUndoCardSelection($playerUndoing, $saucer1Color);

				if($saucer2Color != '')
				{ // we have a second saucer

						// update the database so we know this card is unchosen
						$this->unchooseCardsForSaucer($saucer2Color);

						// tell everyone this is chosen so the card back can be placed on this saucer mat
						$this->notifyPlayersOfUndoCardSelection($playerUndoing, $saucer2Color);
				}

				// set this player back to active in the multiactive state
				$players = array();
				array_push($players, $playerUndoing);
				$this->gamestate->setPlayersMultiactive( $players, '', false);
		}

		// A player selected their move card(s) and clicked Confirm.
		function executeClickedConfirmMove( $saucer1Color, $saucer1Distance, $saucer1Direction, $saucer2Color, $saucer2Distance, $saucer2Direction )
    	{
				// Check that this is the player's turn and that it is a "possible action" at this game state (see states.inc.php)
        		self::checkAction( 'confirmMove' );
//throw new feException( "Clicked confirm move saucer1Distance=$saucer1Distance and saucer1Direction=$saucer1Direction.");

				!$this->isValidMove($saucer1Color, $saucer1Distance, $saucer1Direction, $saucer2Color, $saucer2Distance, $saucer2Direction, true); // validate the move in different ways and throw an error if any failures

				// reset whether we've asked about upgrades so you can use upgrades both before and after your turn (Pulse Cannon)
				$this->resetAllUpgradesActivatedThisRound();

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

		// called when Rotational Stabilizer player chooses who goes second in turn order
		function executeChooseTurnOrder($turnOrderInt)
		{
				//throw new feexception("turnOrderInt:$turnOrderInt");
				$this->updateTurnOrder($turnOrderInt); // 0=CLOCKWISE, 1=COUNTER-CLOCKWISE, 2=UNKNOWN

				// award override tokens
				$this->giveOverrideTokens();

				$this->gamestate->nextState( "setActivePlayerToProbePlayer");
		}

		// called after Rotational Stabilizer player chooses who goes second in turn order just to change active player in a game state
		function executeSetActivePlayerToProbePlayer()
		{
			$probeSaucer = $this->getSaucerWithProbe();
			$probePlayer = $this->getOwnerIdOfOstrich($probeSaucer);
			$this->gamestate->changeActivePlayer( $probePlayer ); // make probe owner go first in turn order (since currently the player with Rotational Stabilizer has control of the turn)

			$this->gamestate->nextState( "playerTurnStart" ); // start the PLAYER turn (not the SAUCER turn)
		}

		function executeSaucerMove($saucerMoving)
		{
//throw new feException( "executeSaucerMove saucer moving: $saucerMoving");
				//self::debug( "executeSaucerMove saucerMoving:$saucerMoving" );

				$crewmembersBeforeMoving = $this->getAllCrewmembers();

				// get list of move events in chronological order (saucers and where they end up, crewmembers picked up and by whom)
				$moveEventList = $this->getMovingEvents($saucerMoving);

				//$eventCount = count($moveEventList);
				//throw new feException( "event count: $eventCount");

				// notify players by sending a list of move events so they can play the animations one after another
				$reversedMoveEventList = array_reverse($moveEventList); // reverse it since it will act like a Stack on the javascript side
				self::notifyAllPlayers( "animateMovement", '', array(
					'moveEventList' => $reversedMoveEventList
				) );
				$lastEventType = $this->getLastEventTypeFromEventList($moveEventList);
				$wasAPushEvent = $this->wasThereSpecificEventInEventList($moveEventList, 'saucerPush');
				//throw new feException( "lastEventType:$lastEventType");

				// tell the players what happened in the game log
				$this->updateGameLogForEvents($saucerMoving, $moveEventList);

				// see if any saucers fell off cliffs and notify everyone if they did
				$this->sendCliffFallsToPlayers();

				// figure out which type of move this is
				$moveType = $this->getMoveTypeWeAreExecuting();

				// calculate spaces left for use with Phase Shifter
				$distance = $this->getSaucerLastDistance($saucerMoving);
				$spacesMoved = $this->getSpacesMoved($saucerMoving);
				$spacesLeft = $distance - $spacesMoved;
//throw new feException( "distance:$distance spacesMoved:$spacesMoved spacesLeft:$spacesLeft");



				$saucerX = $this->getSaucerXLocation($saucerMoving);
				$saucerY = $this->getSaucerYLocation($saucerMoving);
//throw new feException( "moveType:$moveType saucerX:$saucerX saucerY:$saucerY saucerMoving:$saucerMoving");

				// since saucers don't get moved in the DOM while moving, we need to put them in the right spot
				// before we go into any special phases after movement
				// DIDN'T WORK BECAUSE YOU LOSE ANIMATION...THEY SNAP TO THE FINAL SPACE INSTEAD OF ANIMATING
				//if($spacesLeft == 0)
				//{
				//	$this->resetSaucersInDOM();
				//}

				$spaceType = $this->getBoardSpaceType($saucerX, $saucerY);

				// count crewmembers they can exchange with Airlock if they have it
				$airlockExchangeableCrewmembers = $this->getAirlockExchangeableCrewmembers();


				// see if we ended move sequence because we need to activate an upgrade
				$saucerWeCollideWith = $this->getSaucerAt($saucerX, $saucerY, $saucerMoving);
				/*
				if($this->isEndGameConditionMet())
				{ // the game has ended
					$this->goToEndGame(); // end the game
				}
				else
				*/
				if(count($airlockExchangeableCrewmembers) > 0 && $spaceType != "S" && $saucerWeCollideWith == "")
				{ // there is at least one crewmember that can be exchanged

						// get the ID of the highest crewmember available for exchange (in case they picked up 2 on the same turn)
						$crewmemberIdTaken = $this->getHighestAirlockExchangeableId();
						//throw new feException( "executeAirlockCrewmember crewmemberIdTaken: $crewmemberIdTaken");
						$saucerHoldingCrewmember = $this->getSaucerHoldingCrewmemberId($crewmemberIdTaken);
						$saucerWhoseTurnItIs = $this->getSaucerWhoseTurnItIs();

						//throw new feException( "saucerHoldingCrewmember: $saucerHoldingCrewmember saucerMoving: $saucerMoving saucerWhoseTurnItIs: $saucerWhoseTurnItIs");
						if($saucerWhoseTurnItIs != $saucerHoldingCrewmember)
						{ // the moving saucer was pushed into picking up a crewmember
								$ownerOfTakenCrewmember = $this->getOwnerIdOfOstrich($saucerHoldingCrewmember);
								//throw new feException( "owner: $ownerOfTakenCrewmember");
								$this->gamestate->changeActivePlayer($ownerOfTakenCrewmember);
						}
						//throw new feException( "1");
						$this->gamestate->nextState( "chooseCrewmemberToAirlock" );
				}
				elseif($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Proximity Mines") && $saucerWeCollideWith != "" &&
				$this->isUpgradePlayable($saucerMoving, 'Proximity Mines'))
				{ // this saucer has proximity mines played and we are colliding with another saucer

						$this->gamestate->nextState( "askToProximityMine" );
				}
				elseif($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Waste Accelerator") &&
					  ($this->getUpgradeTimesActivatedThisRound($saucerMoving, "Waste Accelerator") < 1) &&
					  $this->getAskedToActivateUpgrade($saucerMoving, "Waste Accelerator") == false &&
					  $this->isUpgradePlayable($saucerMoving, 'Waste Accelerator') &&
					  $this->isCrashSite($spaceType) &&
					  !$this->isSaucerOnAnyOfTheseCrewmembers($saucerMoving, $crewmembersBeforeMoving) &&
					  $wasAPushEvent == false &&
					  $lastEventType != "crewmemberPickup")
				{ // this saucer has Waste Accelerator played and unused, we are on a Crash Site, they did not collide with someone here, and they did not just pick up a crewmember here
					//throw new feException( "saucerWeCollideWith:$saucerWeCollideWith");

					
					// make that we have already asked so we don't ask over and over again
					//$this->setAskedToActivateUpgrade($saucerMoving, "Waste Accelerator");


					$this->gamestate->nextState( "askToWasteAccelerate" );
				}
				elseif($moveType == 'Landing Legs')
				{ // the moved because they had Landing Legs

						if($spaceType == 'S')
						{
							//throw new feException( "yes accelerator");

							if($this->doesSaucerHaveUpgradePlayed($saucerMoving, "Acceleration Regulator") &&
							   $this->isUpgradePlayable($saucerMoving, 'Acceleration Regulator'))
							{ // acceleration regulator is played and it doesn't have summoning sickness
								$this->gamestate->nextState( "chooseAcceleratorDistance" ); // need to ask the player which direction they want to go on the accelerator
							}
							else
							{
								// ask them which direction they want to travel on the accelerator
								$this->gamestate->nextState( "chooseAcceleratorDirection" );
							}
						}
						else
						{ // we are not on an accelerator (if we are on an accelerator we need to know we are still executing Landing Legs)


								//$currentState = $this->getStateName();
								//throw new feException( "no accelerator currentState:$currentState");

								// decide the state to go to after the move
								$this->setState_AfterMovementEvents($saucerMoving, $moveType);
						}
				}
				elseif($moveType == 'Pulse Cannon')
				{ // the moved because they had Pulse Cannon
						$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
						$stateUsedIn = $this->getUpgradeValue5($saucerWhoseTurnItIs, "Pulse Cannon");

						// get the saucer we pushed with pulse cannon
						$pushedSaucer = $this->getUpgradeValue4($saucerWhoseTurnItIs, "Pulse Cannon");

						//throw new feException( "executeSaucerMove Pulse Cannon saucerWhoseTurnItIs:$saucerWhoseTurnItIs stateUsedIn:$stateUsedIn pushedSaucer:$pushedSaucer");

						// reset the upgrade value_1 and value_2 for all saucers so we know movement upgrades have been used already
						$this->resetAllUpgradeValues();

						// must set the pushed_on_saucer_turn to empty string so we know that this saucer is not moving on the next movement execution
						$this->setPushedOnSaucerTurn($pushedSaucer, '');

						if($stateUsedIn == "askWhichStartOfTurnUpgradeToUse")
						{ // this was used at start of turn

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
						{ // we used it at end of turn

								// decide the state to go to after the move
								$this->setState_AfterMovementEvents($saucerMoving, $moveType);
						}
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
				elseif($this->getUpgradeValue2($saucerColor, "Pulse Cannon") != 0)
				{ // we have set the direction for a pulse cannon push
						$moveType = 'Pulse Cannon';
				}
				elseif($this->getUpgradeValue2($saucerColor, "Afterburner") != 0)
				{
						$moveType = 'Afterburner';
				}
				elseif($this->getUpgradeValue2($saucerColor, "Organic Triangulator") != 0)
				{
						$moveType = 'Organic Triangulator';
				}
				elseif($this->getUpgradeValue2($saucerColor, "Acceleration Regulator") != 0)
				{
						$moveType = 'Acceleration Regulator';
				}
				elseif($this->getUpgradeValue2($saucerColor, "Boost Amplifier") != 0)
				{
						$moveType = 'Boost Amplifier';
				}
//throw new feException( "moveType: $moveType");
				return $moveType;
		}

		function getLastEventTypeFromEventList($eventList)
		{
			$eventType = "unknown";
			foreach($eventList as $event)
			{
					$eventType = $event['event_type']; // saucerMove
			}
			//throw new feException( "eventType: $eventType");
			return $eventType;
		}

		function wasThereSpecificEventInEventList($eventList, $eventTypeInQuestion)
		{
			foreach($eventList as $event)
			{

					$eventType = $event['event_type'];
					if($eventType == $eventTypeInQuestion)
					{
						return true;
					}
			}
			//throw new feException( "eventType: $eventType");
			return false;
		}

		function insertDoneMovings($eventList)
		{
			$newList = array();
			
			$currentSaucerMoving = '';
			foreach($eventList as $event)
			{

					$eventType = $event['event_type'];
					
					
					if($eventType == 'saucerMove')
					{
						$saucerForEvent = $event['saucer_moving'];
						if($currentSaucerMoving == '')
						{ // this is the first saucer move event

							// save it so we know when we've moved onto a new saucer
							$currentSaucerMoving = $saucerForEvent;
						}
						elseif($currentSaucerMoving != $saucerForEvent)
						{ // this is a different saucer moving (there must have been a saucerPush event in there)

							// the previous one is done moving so let's add a doneMoving event for them
							$lastSaucerMovingX = $this->getSaucerXLocation($currentSaucerMoving); // 7
							$lastSaucerMovingY = $this->getSaucerYLocation($currentSaucerMoving); // 5
							array_push($newList, array( 'event_type' => 'doneMoving', 'saucer_moving' => $currentSaucerMoving, 'destination_X' => $lastSaucerMovingX, 'destination_Y' => $lastSaucerMovingY));

							// save it so we know when we've moved onto a new saucer
							$currentSaucerMoving = $saucerForEvent;
						}
					}

					// add this event to the list we will be returning
					array_push($newList, $event);
			}

			// add a doneMoving event for the final saucer as well
			$finalX = $this->getSaucerXLocation($currentSaucerMoving); // 7
			$finalY = $this->getSaucerYLocation($currentSaucerMoving); // 5
			array_push($newList, array( 'event_type' => 'doneMoving', 'saucer_moving' => $currentSaucerMoving, 'destination_X' => $finalX, 'destination_Y' => $finalY));

			return $newList;
		}

		function updateGameLogForEvents($saucerMoving, $eventList)
		{
				$saucerMovingHighlightedText = $this->convertColorToHighlightedText($saucerMoving);

				
				$saucerMoveDistance = $this->getSaucerLastDistance($saucerMoving);
				if($saucerMoveDistance == 11)
				{ // they were pushed at the end of the turn (maybe always from Pulse Cannon?)
					$saucerMoveDistance = 1;
				}

				$messageText = clienttranslate( '${saucerMovingHighlightedText} is moving ${distance} spaces.' );

				if($saucerMoveDistance == 1)
				{ // we are only moving 1 spaces
						$messageText = clienttranslate( '${saucerMovingHighlightedText} is moving ${distance} space.' );
				}

				self::notifyAllPlayers( "saucerMove", $messageText, array(
					'saucerMovingHighlightedText' => $saucerMovingHighlightedText,
					'distance' => $saucerMoveDistance
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

							$crewmemberId = $this->getCrewmemberIdFromColorAndTypeText($crewmemberColor, $crewmemberType);
							$this->saveRoundPickedUp($crewmemberId);
						}
				}


		}

		function getMovingEvents( $saucerMoving )
		{
				$allEvents = array();

				// if the saucer moving was pushed, it's a different saucer whose turn it is and sometimes we need to know this
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				//self::debug( "getMovingEvents saucerMoving:$saucerMoving" );

				$lastTraveledDistance = $this->getSaucerLastDistance($saucerMoving); //2, 3, 5, etc
				$spacesMoved = $this->getSpacesMoved($saucerMoving);

				// get modified distance (if using Accleration Regulator or Boost Amplifier)

				$direction = $this->getSaucerDirection($saucerMoving); // meteor

				$wasPushed = $this->wasThisSaucerPushed($saucerMoving);
				if($wasPushed)
				{ // this saucer was pushed
						$pushedDistance = $this->getPushedDistance($saucerMoving);
						$lastTraveledDistance = $pushedDistance;

						$pushedDirection = $this->getPushedDirection($saucerMoving);
						$direction = $pushedDirection;
				}

				$distance = $lastTraveledDistance - $spacesMoved;

				$currentX = $this->getSaucerXLocation($saucerMoving); // 7
				$currentY = $this->getSaucerYLocation($saucerMoving); // 5

				$moveType = $this->getMoveTypeWeAreExecuting();
				//throw new feException( "moveType:$moveType");
				if($moveType == 'Landing Legs' || $moveType == 'Blast Off Thrusters' || $moveType == 'Pulse Cannon')
				{ // we are moving from a start or end of turn upgrade
						$distance = $this->getUpgradeValue1($saucerWhoseTurnItIs, $moveType);
						$direction = $this->getUpgradeValue3($saucerWhoseTurnItIs, $moveType);

						//throw new feException( "distance:$distance direction:$direction");
				}

				//throw new feException( "distance:$distance");

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

				// at each point a saucer is done moving, we need to add an event to tell the front end that
				$allEvents = $this->insertDoneMovings($allEvents);

				return $allEvents;
		}

		// This is called when a player clicks a direction button to use an Accelerator, a Booster, or choose their direction if they have Time Machine.
		function executeDirectionClick( $direction )
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs(); // you can only zag on your own turn
				$ownerOfSaucerWhoseTurnItIs = $this->getOwnerIdOfOstrich($saucerWhoseTurnItIs);

				$currentState = $this->getStateName();

				$this->saveSaucerMoveCardDirection($saucerWhoseTurnItIs, $direction); // save the direction

				$moveType = $this->getMoveTypeWeAreExecuting();
				//throw new feException( "moveType: $moveType currentState:$currentState");

				if($currentState == "chooseIfYouWillUseBooster")
				{ // they are boosting

						$this->saveSaucerLastDirection($saucerWhoseTurnItIs, $direction); // update the database with its new last moved direction

						// We need to set the last distance traveled back to the original turn distance because that is always used
						// for the Booster distance, even if someone just used an Acceleration Regulator for a different distance. We do this 
						// so we can always use the last distance traveled during our move execution.
						$originalDistance = $this->getSaucerOriginalTurnDistance($saucerWhoseTurnItIs);
						$this->saveSaucerLastDistance($saucerWhoseTurnItIs, $originalDistance); 


						// set the number of spaces moved back to 0 since we're starting a new movement
						$this->setSpacesMoved($saucerWhoseTurnItIs, 0);
						//throw new feException( "booster saucerWhoseTurnItIs: $saucerWhoseTurnItIs");

						self::incStat( 1, 'boosters_used', $ownerOfSaucerWhoseTurnItIs );

						$this->notifyPlayersOfBoosterUsage($saucerWhoseTurnItIs);
						$this->decrementBoosterForSaucer($saucerWhoseTurnItIs); // must come after notification

						if($this->doesSaucerHaveUpgradePlayed($saucerWhoseTurnItIs, "Boost Amplifier"))
						{ // this player has Boost Amplifier

							// they get to choose how far they will go on the Booster
							$this->gamestate->nextState( "chooseBoosterDistance" );
						}
						else
						{
							$this->gamestate->nextState( "executingMove" );
							//$this->executeSaucerMove($saucerWhoseTurnItIs);
						}
				}
				elseif($currentState == "chooseAcceleratorDirection")
				{ // they are accelerating

					// set the number of spaces moved back to 0 since we're starting a new movement
					$this->setSpacesMoved($saucerWhoseTurnItIs, 0);

					$distanceType = $this->getSaucerDistanceType($saucerWhoseTurnItIs);
					$cardId = $this->getMoveCardIdFromSaucerDistanceType($saucerWhoseTurnItIs, $distanceType);
					$cardState = $this->getCardChosenState($cardId);

						if($moveType == "Landing Legs")
						{ // they are using landing legs
//throw new feException( "landing");
								// landing legs direction gets set in the upgrade values
								$upgradeCardId = $this->getUpgradeCardId($saucerWhoseTurnItIs, "Landing Legs");
								$this->setUpgradeValue3($upgradeCardId, $direction);

						}

//throw new feException( "no landing:$moveType");


						self::incStat( 1, 'accelerators_used', $ownerOfSaucerWhoseTurnItIs );

						$this->gamestate->nextState( "executingMove" );
						//$this->executeSaucerMove($saucerWhoseTurnItIs);
				}
				elseif($currentState == "chooseTimeMachineDirection")
				{ // they have chosen their time machine direction

						// set their direction
						$this->saveSaucerMoveCardDirection($saucerWhoseTurnItIs, $direction); // save the direction so we have it in case we are pushed before our turn comes up

						// specify that we have already set the direction this round so we don't ask again
						$this->activateUpgrade($saucerWhoseTurnItIs, "Time Machine", false);

						// set asked_to_activate_this_round to 1 so we don't ask again
						$this->setAskedToActivateUpgrade($saucerWhoseTurnItIs, "Time Machine");

						// this saucer can no longer choose their direction from being crashed
						$this->setCanChooseDirection($saucerWhoseTurnItIs, 0);



						// notify the player so they can rotate the card on the UI
						$saucerWhoseTurnItIs = $this->getSaucerWhoseTurnItIs();
						
						if($saucerWhoseTurnItIs != "")
						{
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
						}

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
//throw new feException( "executeSkipActivateSpecificEndOfTurnUpgrade");
				

				if($this->doesPlayerHaveAnyEndOfTurnUpgradesToActivate($saucerWhoseTurnItIs))
				{ // see if they want to use any end of turn upgrades
						$this->gamestate->nextState( "askWhichEndOfTurnUpgradeToUse" );
				}
				else
				{
						$this->gamestate->nextState( "endSaucerTurnCleanUp" );
				}
		}

		// skip a single start of turn upgrade after you chose to activate it
		function executeSkipActivateSpecificStartOfTurnUpgrade($collectorNumber)
		{
				self::checkAction( 'skipActivateUpgrade' ); // make sure we can take this action from this state
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				$this->setAskedToActivateUpgradeWithCollectorNumber($saucerWhoseTurnItIs, $collectorNumber);

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

		function executeActivateUpgrade($collectorNumber)
		{
				self::checkAction( 'activateUpgrade' ); // make sure we can take this action from this state
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				// set this to activated
				$this->activateUpgradeWithCollectorNumber($saucerWhoseTurnItIs, $collectorNumber);

				$nameOfUpgrade = $this->getUpgradeTitleFromCollectorNumber($collectorNumber);
				$colorName = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);

				// notify all players that is has been used
				self::notifyAllPlayers( 'upgradeUsed', clienttranslate( '${color_name} used ${name_of_upgrade}.' ), array(
						'name_of_upgrade' => $nameOfUpgrade,
						'color_name' => $colorName
				) );


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
				elseif($collectorNumber == 26)
				{ // Organic Triangulator

						$this->gamestate->nextState( "chooseOrganicTriangulatorSpace" );
				}
				elseif($collectorNumber == 4)
				{ // Pulse Cannon
						$cardId = $this->getUpgradeCardId($saucerWhoseTurnItIs, "Pulse Cannon");
						$currentState = $this->getStateName();

						//throw new feException( "setting cardId $cardId value1 to 1 and value 2 to $direction" );

						// save the state name so we know we used pulse cannon this turn and we know whether we did it
						// at the before or after we move so we know which state to go in afterwards
						$this->setUpgradeValue5($cardId, $currentState);

						$this->gamestate->nextState( "chooseSaucerPulseCannon" );
				}
				elseif($collectorNumber == 5)
				{ // Tractor Beam

						$this->gamestate->nextState( "chooseTractorBeamCrewmember" );
				}
				elseif($collectorNumber == 6)
				{ // Saucer Teleporter
						$this->gamestate->nextState( "chooseCrashSiteSaucerTeleporter" );

						$cardId = $this->getUpgradeCardId($saucerWhoseTurnItIs, "Saucer Teleporter");
						$currentState = $this->getStateName();

						$this->setUpgradeValue5($cardId, $currentState);
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
				elseif($collectorNumber == 18)
				{ // Quake Maker
						$this->gamestate->nextState( "chooseTileRotationQuakeMaker" );
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

				// take away Energy for playing it
				$this->decrementEnergyForSaucer($color);
				$this->decrementEnergyForSaucer($color);

				$energyQuantity = $this->getEnergyCountForSaucer($color);

				// get some additional notification details
				$nameOfUpgrade = $this->getUpgradeTitleFromCollectorNumber($collectorNumber);
				$colorName = $this->convertColorToHighlightedText($color);

				// notify all players that is has been played
				self::notifyAllPlayers( 'upgradePlayed', clienttranslate( '${color_name} played ${name_of_upgrade}.' ), array(
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

						// they get 3 boosters when playing it
						$this->giveSaucerBooster($color);
						$this->giveSaucerBooster($color);
						$this->giveSaucerBooster($color);
				}

				// increase stat for how many upgrades have been played
				self::incStat( 1, 'upgrades_played', $playerId );

				$drawnUpgrades = $this->upgradeCards->getCardsInLocation('drawn');
				foreach( $drawnUpgrades as $card )
        { // go through all the drawn cards

						$collectorNumberDiscarded = $card['type_arg']; // collector number
						$databaseIdDiscarded = $card['id']; // database id
						$nameOfUpgradeDiscarded = $this->getUpgradeTitleFromCollectorNumber($collectorNumberDiscarded); // name of upgrade discarded

						if($databaseIdDiscarded != $databaseId)
						{ // this is an upgrade the player did not choose to play

								// notify all players that is has been played
								self::notifyAllPlayers( 'upgradeDiscarded', clienttranslate( '${color_name} discarded the upgrade ${UPGRADEMESSAGELOG}.' ), array(
										'saucerColor' => $color,
										'collectorNumber' => $collectorNumberDiscarded,
										'databaseId' => $databaseIdDiscarded,
										'playerId' => $playerId,
										'player_name' => $playerName,
										'name_of_upgrade' => $nameOfUpgradeDiscarded,
										'color_name' => $colorName,
										'energyQuantity' => $energyQuantity,
										'UPGRADEMESSAGELOG' => "upgrade_$collectorNumberDiscarded"
								) );
						}
				}

				// put unselected cards into discard pile
				$this->upgradeCards->moveAllCardsInLocation('drawn', 'discard');

				$this->gamestate->nextState( "endSaucerTurnCleanUp" );
		}

		// Happens when:
		//    - Acceleration Regulator: A player selects a distance to move off an Accelerator.
		//    - Hyperdrive: A player has Hyperdrive and chose the distance they will travel.
		//    - Boost Amplifier: A player used a Booster and can choose the distance they will travel with it.
		function executeSelectDistanceValue($xValue)
		{
				$saucer = $this->getSaucerWhoseTurnItIs();
				$playerId = $this->getOwnerIdOfOstrich($saucer);
				$currentState = $this->getStateName();

				switch($currentState)
				{
					// Acceleration Regulator
					case "chooseAcceleratorDistance":
						//throw new feException( "Acceleration Regulator" );

						if(!$this->isValidDistanceForUpgrade($xValue, $saucer, "Acceleration Regulator"))
						{ // this is not a valid space
							throw new BgaUserException( self::_("That is not a valid distance.") );
						}

						$normalAcceleratorDistance = $this->getSaucerLastDistance($saucer);
						if($xValue != $normalAcceleratorDistance)
						{ // they chose a value other than what they would be able to do without Acceleration Regulator

							// increment the state for using it
							self::incStat( 1, 'upgrades_activated', $playerId );
						}
						
						$this->saveSaucerLastDistance($saucer, $xValue); // when we push someone, they go the distance we just went so we need to save how far we're going
						$cardId = $this->getUpgradeCardId($saucer, "Acceleration Regulator");
						$this->setUpgradeValue3($cardId, $xValue); // we need to save the distance we chose for this Accelerator, which also shows it is active

						

						// notify players of the selection
						$highlightedSaucerColor = $this->convertColorToHighlightedText($saucer);
						self::notifyAllPlayers( 'accelerationRegulatorSelected', clienttranslate( '${saucer_color_highlighted} used Acceleration Regulator to go ${xValue} off the Accelerator.' ), array(
								'ostrich' => $saucer,
								'xValue' => $xValue,
								'player_name' => self::getActivePlayerName(),
								'saucer_color_highlighted' => $highlightedSaucerColor
						) );

						$this->gamestate->nextState( "chooseAcceleratorDirection" );
					break;

					// Boost Amplifier
					case "chooseBoosterDistance":
						//throw new feException( "Boost Amplifier" );

						if(!$this->isValidDistanceForUpgrade($xValue, $saucer, "Boost Amplifier"))
						{ // this is not a valid space
							throw new BgaUserException( self::_("That is not a valid distance.") );
						}

						$normalBoosterDistance = $this->getSaucerOriginalTurnDistance($saucer);
						if($xValue != $normalBoosterDistance)
						{ // they chose a value other than what they would be able to do without Boost Amplifier

							// increment the state for using it
							self::incStat( 1, 'upgrades_activated', $playerId );
						}
						
						$this->saveSaucerLastDistance($saucer, $xValue); // when we push someone, they go the distance we just went so we need to save how far we're going
						$cardId = $this->getUpgradeCardId($saucer, "Boost Amplifier");
						$this->setUpgradeValue3($cardId, $xValue); // we need to save the distance we chose for this Boost, which also shows it is active

						// notify players of the selection
						$highlightedSaucerColor = $this->convertColorToHighlightedText($saucer);
						self::notifyAllPlayers( 'boostAmplifierSelected', clienttranslate( '${saucer_color_highlighted} used Boost Amplifier to go ${xValue} with their Booster.' ), array(
								'ostrich' => $saucer,
								'xValue' => $xValue,
								'player_name' => self::getActivePlayerName(),
								'saucer_color_highlighted' => $highlightedSaucerColor
						) );

						$this->gamestate->nextState( "executingMove" );
					break;


					// Hyperdrive
					case "chooseDistanceDuringMoveReveal":

						$isValid = $this->isValidXSelection($saucer, $xValue);
						if(!$isValid)
						{
							throw new BgaUserException( self::_("That is not a valid space.") );
						}

						// increment the state for using it
						self::incStat( 1, 'upgrades_activated', $playerId );

						$this->saveSaucerLastDistance($saucer, $xValue); // when we push someone, they go the distance we just went so we need to save how far we're going
						$this->saveOstrichXValue($saucer, $xValue); // save the value we chose for X
						$this->setSaucerOriginalTurnDistance( $saucer, $xValue ); // set the original turn distance too so we know how much to go with a Booster

						$highlightedSaucerColor = $this->convertColorToHighlightedText($saucer);
						self::notifyAllPlayers( 'hyperdriveSelected', clienttranslate( '${saucer_color_highlighted} set their distance to ${xValue} using Hyperdrive.' ), array(
								'ostrich' => $saucer,
								'xValue' => $xValue,
								'player_name' => self::getActivePlayerName(),
								'saucer_color_highlighted' => $highlightedSaucerColor
						) );

						$this->gamestate->nextState( "checkForRevealDecisions" );
						//$this->setState_PreMovement(); // set the player's phase based on what that player has available to them

					break;
					default: 
						throw new feException( "Cannot choose the distance in this state. ($currentState)" );
					break;
				}

			// increase stat for activating
			$ownerActivating = $this->getOwnerIdOfOstrich($saucer);
			self::incStat( 1, 'upgrades_activated', $ownerActivating );
		}

		function isValidXSelection($saucerColor, $xValue)
		{
			$maxDistance = 5;

			if($this->doesSaucerHaveUpgradePlayed($saucerColor, "Hyperdrive"))
			{ // this player activated hyperdrive this round
				$maxDistance = $maxDistance * 2;
			}

			if($xValue > $maxDistance)
			{ // they are trying to go further than their max distance allows
				return false;
			}
			
			return true;
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

				self::notifyAllPlayers( 'zagUsed', clienttranslate( '${saucer_friendly_color} is boosting by using a ${BOOSTERDISC}.' ), array(
						'ostrich' => $saucerColor,
						'boosterQuantity' => $boosterQuantity,
						'player_name' => self::getActivePlayerName(),
						'saucer_friendly_color' => $colorFriendlyText,
						'BOOSTERDISC' => 'BOOSTERDISC'
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

		function isValidDistanceForUpgrade($distance, $saucerColor, $upgradeName)
		{
			switch($upgradeName)
			{
				case "Acceleration Regulator":
					if($distance == 1 || $distance == 2 || $distance == 3 || $distance == 4)
					{
						return true;
					}

					$normalAcceleratorDistance = $this->getSaucerLastDistance($saucerColor);
					if($distance == $normalAcceleratorDistance)
					{ // they aren't using Acceleration Regulator and just using their last distance chosen
						return true;
					}

					if($this->doesSaucerHaveUpgradePlayed($saucerColor, "Hyperdrive"))
					{ // they have Hyperdrive too
						if($distance == 6 || $distance == 8)
						{
							return true;
						}
					}
				break;

				case "Boost Amplifier":
					if($distance == 1 || $distance == 2 || $distance == 3 || $distance == 4 || $distance == 5 || $distance == 6)
					{
						return true;
					}

					$normalBoosterDistance = $this->getSaucerOriginalTurnDistance($saucerColor);
					if($distance == $normalBoosterDistance)
					{ // they aren't using Boost Amplifier and just using their last distance chosen
						return true;
					}

					if($this->doesSaucerHaveUpgradePlayed($saucerColor, "Hyperdrive"))
					{ // they have Hyperdrive too
						if($distance == 8 || $distance == 10 || $distance == 12)
						{
							return true;
						}
					}
				break;
			}

			return false;
		}

		function isValidSpaceForUpgrade($xLocation, $yLocation, $saucerColor, $upgradeName)
		{
				$validSpaces = $this->getValidSpacesForUpgrade($saucerColor, $upgradeName);

				$stringOfSpace = $xLocation.'_'.$yLocation;

				foreach( $validSpaces as $space )
				{ // go through each space

						if($stringOfSpace == $space)
						{
								return true;
						}
				}

				return false;
		}

		function argGetValidGarmentSpawnSpaces()
		{
				return array(
						'validGarmentSpawnSpaces' => self::getValidGarmentSpawnSpaces(),
						'playerIdRespawningGarment' => self::getPlayerIdRespawningGarment(),
						'playerNameRespawningGarment' => self::getPlayerNameById(self::getPlayerIdRespawningGarment())
				);
		}

		function executeChooseUpgradeSpace($xLocation, $yLocation)
		{
				self::checkAction( 'chooseUpgradeSpace', false ); // Check that this is player's turn and that it is a "possible action" at this game state (see states.inc.php) -- the false argument says don't check if we are the active player because we might be replacing a garment on another player's turn
				$formattedSpace = $xLocation.'_'.$yLocation;
				$saucerColor = $this->getOstrichWhoseTurnItIs();
				$direction = $this->getDirectionFromLocation($saucerColor, $xLocation, $yLocation);

				$skipNotify = false; // true if we want to skip adding to the message log which upgrade was used

				$cardId = 0;
				$upgradeName = "";
				$currentState = $this->getStateName();
				//throw new feException( "currentState: $currentState" );
				if($currentState == "chooseBlastOffThrusterSpace")
				{
						$cardId = $this->getUpgradeCardId($saucerColor, "Blast Off Thrusters");
						$upgradeName = $this->getUpgradeTitleFromCollectorNumber(1);

						if(!$this->isValidSpaceForUpgrade($xLocation, $yLocation, $saucerColor, $upgradeName))
						{ // this is not a valid space
								throw new BgaUserException( self::_("That is not a valid space for this upgrade.") );
						}

						// teleport saucer to new location
						$this->placeSaucerOnSpace($saucerColor, $xLocation, $yLocation, false);

						// since we're not moving traditionally, we need to specify the next state
						$this->setState_AfterMovementEvents($saucerColor, "Blast Off Thrusters");
				}
				elseif($currentState == "chooseAfterburnerSpace")
				{
						$cardId = $this->getUpgradeCardId($saucerColor, "Afterburner");
						$upgradeName = $this->getUpgradeTitleFromCollectorNumber(3);

						if(!$this->isValidSpaceForUpgrade($xLocation, $yLocation, $saucerColor, $upgradeName))
						{ // this is not a valid space
								throw new BgaUserException( self::_("That is not a valid space for this upgrade.") );
						}

						// teleport saucer to new location
						$this->placeSaucerOnSpace($saucerColor, $xLocation, $yLocation);

						// since we're not moving traditionally, we need to specify the next state
						$this->setState_AfterMovementEvents($saucerColor, "Afterburner");
				}
				elseif($currentState == "chooseOrganicTriangulatorSpace")
				{
						$cardId = $this->getUpgradeCardId($saucerColor, "Organic Triangulator");
						$upgradeName = $this->getUpgradeTitleFromCollectorNumber(26);

						if(!$this->isValidSpaceForUpgrade($xLocation, $yLocation, $saucerColor, $upgradeName))
						{ // this is not a valid space
								throw new BgaUserException( self::_("That is not a valid space for this upgrade.") );
						}

						// teleport saucer to new location
						$this->placeSaucerOnSpace($saucerColor, $xLocation, $yLocation);

						// since we're not moving traditionally, we need to specify the next state
						$this->setState_AfterMovementEvents($saucerColor, "Organic Triangulator");
				}
				elseif($currentState == "chooseLandingLegsSpace")
				{
						$cardId = $this->getUpgradeCardId($saucerColor, "Landing Legs");
						$upgradeName = $this->getUpgradeTitleFromCollectorNumber(17);

						// specify that this is landing legs so we know how to handle movement on accelerators if we use it to move onto one
						$this->setUpgradeValue1($cardId, 1); // 1 distance
						$this->setUpgradeValue2($cardId, $upgradeName); // set to Landing Legs so we know the move type
						$this->setUpgradeValue3($cardId, $direction);

						if(!$this->isValidSpaceForUpgrade($xLocation, $yLocation, $saucerColor, $upgradeName))
						{ // this is not a valid space
								throw new BgaUserException( self::_("That is not a valid space for this upgrade.") );
						}

						// move saucer to space since it might not be empty
						$this->gamestate->nextState( "executingMove" );

						// since we're not moving traditionally, we need to specify the next state
						//$this->setState_AfterMovementEvents($saucerColor, "Landing Legs");
				}
				elseif($currentState == "chooseCrashSiteSaucerTeleporter")
				{ // choosing en empty crash site

					$cardId = $this->getUpgradeCardId($saucerColor, "Saucer Teleporter");
					$upgradeName = $this->getUpgradeTitleFromCollectorNumber(6);

					$crashSiteNumber = $this->getBoardSpaceType($xLocation, $yLocation);
					$this->executeChooseCrashSite($crashSiteNumber);
				}
				elseif($currentState == "chooseCrashSiteRegenerationGateway")
				{ // choosing en empty crash site 
					
					//I DON'T THINK THIS EVER TRIGGERS HERE (IT TRIGGERS IN executeChooseCrashSite)

					if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Scavenger Bot') &&
					$this->isUpgradePlayable($saucerColor, 'Scavenger Bot'))
					{ // this saucer has Scavenger Bot

						// give a Booster
						$this->giveSaucerBooster($saucerColor);								
		
						// give an Energy
						$this->giveSaucerEnergy($saucerColor);
					}

					$cardId = $this->getUpgradeCardId($saucerColor, "Regeneration Gateway");
					$upgradeName = $this->getUpgradeTitleFromCollectorNumber(13);

					$crashSiteNumber = $this->getBoardSpaceType($xLocation, $yLocation);
					$this->executeChooseCrashSite($crashSiteNumber);
				}
				elseif($currentState == "chooseTimeMachineDirection")
				{ // choosing a direction for Time Machine
					$cardId = $this->getUpgradeCardId($saucerColor, "Time Machine");
					$upgradeName = $this->getUpgradeTitleFromCollectorNumber(12);

					//$skipNotify = true;

					$this->executeDirectionClick($direction);
				}

				// increment the stat for using this upgrade
				$ownerActivating = $this->getOwnerIdOfOstrich($saucerColor);
				self::incStat( 1, 'upgrades_activated', $ownerActivating );
				
				if(!$skipNotify)
				{ // we don't want to skip notifying

					// notify the player so they can rotate the card on the UI
					$saucerColorHighlighted = $this->convertColorToHighlightedText($saucerColor);
					self::notifyAllPlayers( 'upgradeMove', clienttranslate( '${saucerColorHighlighted} used ${upgradeName} to move.' ), array(
							'upgradeName' => $upgradeName,
							'saucerColorHighlighted' => $saucerColorHighlighted
					) );
				}
		}

		function executeChooseAnySpaceForSaucer($xLocation, $yLocation)
		{
				self::checkAction( 'chooseSaucerSpace', false ); // Check that this is player's turn and that it is a "possible action" at this game state (see states.inc.php) -- the false argument says don't check if we are the active player because we might be replacing a garment on another player's turn
				$formattedSpace = $xLocation.'_'.$yLocation;
				$saucerColor = $this->getOstrichWhoseTurnItIs();

				$currentState = $this->getStateName();
				if($currentState == "chooseCrashSiteRegenerationGateway")
				{ // choosing en empty crash site

					//I DON'T THINK THIS EVER TRIGGERS HERE (IT TRIGGERS IN executeChooseCrashSite)

					if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Scavenger Bot') &&
					$this->isUpgradePlayable($saucerColor, 'Scavenger Bot'))
					{ // this saucer has Scavenger Bot

						// give a Booster
						$this->giveSaucerBooster($saucerColor);								
		
						// give an Energy
						$this->giveSaucerEnergy($saucerColor);
					}
				}

				$allValidSpaces = $this->getAllSpacesNotInCrewmemberRowColumn();
				foreach( $allValidSpaces as $space )
				{ // go through each valid space
						//echo "playerID is " + $player['player_id'];



						if($space == $formattedSpace)
						{ // this space is valid

								// locate the saucer there and notify all players
								$this->placeSaucerOnSpace($saucerColor, $xLocation, $yLocation);

								if($currentState == "allCrashSitesOccupiedChooseSpaceEndRound")
								{ // we are placing a crashed saucer at the end of a round
										$this->gamestate->nextState( "endRoundCleanUp" ); // back to end round clean-up to see if we need to place any others
								}
								elseif($currentState == "allCrashSitesOccupiedChooseSpacePreTurn")
								{ // we are placing a crashed saucer before a player's turn

									$this->setCanChooseDirection($saucerColor, 1);
									$this->gamestate->nextState( "beginTurn" ); // let them choose direction
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

						if($this->doesSaucerHaveUpgradePlayed($saucerColor, 'Rotational Stabilizer') &&
						$this->isUpgradePlayable($saucerColor, 'Rotational Stabilizer'))
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
//throw new feException( "test2" );
				if($this->getNumberOfPlayers() > 2)
				{ // more than 2 players

						// get the chosen turn order if someone has played rotational stabilizer
						$rotationalStabilizerOwner = $this->getRotationalStabilizerOwner();
						if($rotationalStabilizerOwner != '')
						{ // someone has Rotational Stabalizer

								// set the player who has rotational stabilizer to the active player
								$this->gamestate->changeActivePlayer( $rotationalStabilizerOwner ); // set the active player (this cannot be done in an activeplayer game state)

								// let that player choose the direction
								$this->gamestate->nextState( "askToRotationalStabilizer" );
						}
						else
						{ // usual case

								// roll the rotation die
								$turnOrderInt = rand(0,1);

								// set the turn order and go to playerTurnStart state
								$this->updateTurnOrder($turnOrderInt); // 0=CLOCKWISE, 1=COUNTER-CLOCKWISE, 2=UNKNOWN

								// award override tokens
								$this->giveOverrideTokens();

								$this->gamestate->nextState( "playerTurnStart" ); // start the PLAYER turn (not the SAUCER turn)
						}

				}
				else
				{ // 2 players

						$this->gamestate->nextState( "playerTurnStart" ); // start the PLAYER turn (not the SAUCER turn)
				}

				// tell all players a new round has started where they will send a random move card back of their opponents on to their mat

		}

		function updateTurnOrder($turnOrderInt)
		{
				self::setGameStateValue( 'TURN_ORDER', $turnOrderInt ); // 0=CLOCKWISE, 1=COUNTER-CLOCKWISE, 2=UNKNOWN

				$turnOrderFriendly = $this->convertTurnOrderIntToText($turnOrderInt);
				$playerWithProbe = $this->getStartPlayer(); // get the owner of the saucer with the probe

				$turnOrder = $this->createTurnOrderArray($playerWithProbe, $turnOrderInt);
				
					// notify players of the direction (send clockwise/counter)
					self::notifyAllPlayers( 'updateTurnOrder', clienttranslate( 'The turn direction this round is ${turnOrderFriendly}.' ), array(
									'i18n' => array('turnOrderFriendly'),
									'turnOrderFriendly' => $turnOrderFriendly,
									'turnOrder' => $turnOrderInt,
									'probePlayer' => $playerWithProbe,
									'turnOrderArray' => $turnOrder
					) );
				
		}

		function createTurnOrderArray($playerWithProbe, $turnOrderInt)
		{
				$turnOrderArraySaucers = array();
				$turnIndex = 1;
				$numberOfPlayers = $this->getNumberOfPlayers();

				// assume we are going CLOCKWISE
				$nextPlayer = $this->getPlayerAfter( $playerWithProbe );

				if($turnOrderInt == 1)
				{ // we are going COUNTERCLOCKWISE
						$nextPlayer = $this->getPlayerBefore( $playerWithProbe );
				}

				// add this player's saucers to the SAUCER array
				$saucersForPlayer = $this->getSaucersForPlayer($playerWithProbe);
				foreach( $saucersForPlayer as $saucer )
				{ // go through each saucer of this player
						$saucerColor = $saucer['ostrich_color'];
						$turnOrder = $turnIndex;

						if($numberOfPlayers == 2)
						{ // this is a 2 player game

								if($this->hasPlayerChosen($playerWithProbe))
								{ // this player has chosen which of its saucers will go first

										$thisSaucerIsChosen = $this->getSaucerIsChosen($saucerColor);
										if(!$thisSaucerIsChosen)
										{ // this saucer was NOT the one chosen
												$turnOrder = 3; // they will go third
										}
								}
						}

						$saucerOrder = array();
						array_push($saucerOrder, $turnOrder);
						array_push($saucerOrder, $saucerColor);
						array_push($turnOrderArraySaucers, $saucerOrder);
				}

				while($nextPlayer != $playerWithProbe)
				{ // go through each player
						$turnIndex++;
						$turnOrder = $turnIndex;

						$saucersForPlayer = $this->getSaucersForPlayer($nextPlayer);
						foreach( $saucersForPlayer as $saucer )
						{ // go through each saucer of this player
								$saucerColor = $saucer['ostrich_color'];

								$currentState = $this->getStateName();
								if($this->isPreTurnOrderState($currentState) && $turnIndex > 1)
								{ // turn order has not yet been chosen this round

									$turnOrder = 0; // we do not know turn order yet
								}
								else
								{ // turn order has been chosen this round

									if($numberOfPlayers == 2)
									{ // this is a 2 player game

											if($this->hasPlayerChosen($nextPlayer))
											{ // this player has chosen which of its saucers will go first (and it's not the probe player so they are going second and fourth)

													$thisSaucerIsChosen = $this->getSaucerIsChosen($saucerColor);
													if($thisSaucerIsChosen)
													{
															$turnOrder = 2; // they will go second
													}
													else { // this saucer was NOT the one chosen
															$turnOrder = 4; // they will go fourth because this player does not have the probe
													}
											}
											else
											{ // this player has not chosen
													$turnOrder = 0; // we don't know their order yet
											}
									}
								}

								$saucerOrder = array();
								array_push($saucerOrder, $turnOrder);
								array_push($saucerOrder, $saucerColor);
								array_push($turnOrderArraySaucers, $saucerOrder);
						}

						if($turnOrderInt == 1)
						{ // we are going COUNTERCLOCKWISE
								$nextPlayer = $this->getPlayerBefore( $nextPlayer );
						}
						else
						{ // we are going CLOCKWISE
								$nextPlayer = $this->getPlayerAfter( $nextPlayer );
						}
				}

				return $turnOrderArraySaucers;
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
			$clockwiseAsInt = $this->getGameStateValue("TURN_ORDER"); // 0=CLOCKWISE, 1=COUNTER-CLOCKWISE, 2=UNKNOWN
			if($clockwiseAsInt == 0)
			{
				return true;
			}
			else
			{
				return false;
			}
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

		function getUpgradeValue3($saucerColor, $upgradeNameOrCollectorNumber)
		{
				$cardId = $this->getUpgradeCardId($saucerColor, $upgradeNameOrCollectorNumber);

				if($cardId == 0 || $cardId == '' || is_null($cardId))
				{
						return 0;
				}
//throw new feException( "cardId $cardId" );
				return self::getUniqueValueFromDb("SELECT value_3 FROM `upgradeCards` WHERE card_id=$cardId LIMIT 1");
		}

		function getUpgradeValue4($saucerColor, $upgradeNameOrCollectorNumber)
		{
				$cardId = $this->getUpgradeCardId($saucerColor, $upgradeNameOrCollectorNumber);

				if($cardId == 0 || $cardId == '' || is_null($cardId))
				{
						return 0;
				}
//throw new feException( "cardId $cardId" );
				return self::getUniqueValueFromDb("SELECT value_4 FROM `upgradeCards` WHERE card_id=$cardId LIMIT 1");
		}

		function getUpgradeValue5($saucerColor, $upgradeNameOrCollectorNumber)
		{
				$cardId = $this->getUpgradeCardId($saucerColor, $upgradeNameOrCollectorNumber);

				if($cardId == 0 || $cardId == '' || is_null($cardId))
				{
						return 0;
				}
//throw new feException( "cardId $cardId" );
				return self::getUniqueValueFromDb("SELECT value_5 FROM `upgradeCards` WHERE card_id=$cardId LIMIT 1");
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

		function setUpgradeValue3($cardId, $newValue)
		{
				$sqlUpdate = "UPDATE upgradeCards SET ";
				$sqlUpdate .= "value_3='$newValue' WHERE ";
				$sqlUpdate .= "card_id=$cardId";

				self::DbQuery( $sqlUpdate );
		}

		function setUpgradeValue4($cardId, $newValue)
		{
				$sqlUpdate = "UPDATE upgradeCards SET ";
				$sqlUpdate .= "value_4='$newValue' WHERE ";
				$sqlUpdate .= "card_id=$cardId";

				self::DbQuery( $sqlUpdate );
		}

		function setUpgradeValue5($cardId, $newValue)
		{
				$sqlUpdate = "UPDATE upgradeCards SET ";
				$sqlUpdate .= "value_5='$newValue' WHERE ";
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

				// reset crewmember properties
				$this->resetCrewmembers();

				// reset their override token setting in case they had one
				$this->setHasOverrideToken($saucerWhoseTurnItIs, 0);

//throw new feException( "incrementing stat for $playerWhoseTurnItWas" );

				$this->incrementSaucerTurnsTaken($saucerWhoseTurnItIs);
				self::incStat( 1, 'turns_number', $playerWhoseTurnItWas ); // increase end game player stat
				self::incStat( 1, 'turns_number' ); // increase end game table stat

				self::notifyAllPlayers( "endTurn", clienttranslate( '${saucer_color_highlighted} has ended their turn.' ), array(
								'player_name' => $nameOfPlayerWhoseTurnItWas,
								'saucer_color_highlighted' => $highlightedSaucerColor,
								'allCrewmembers' => $this->getAllCrewmembers(),
								'current_round' => $this->getGameStateValue("CURRENT_ROUND")
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

				// increment turn count
				$currentTurn = $this->getGameStateValue('CURRENT_TURN');
				$this->setGameStateValue('CURRENT_TURN', $currentTurn + 1);

				//throw new feException( "afternotify");
				if($this->isEndGameConditionMet())
				{ // the game has ended
					$this->goToEndGame(); // end the game
				}
				elseif($this->haveAllSaucersTakenTheirTurn())
				{ // round is over
						//throw new feException( "round ends" );
						$this->gamestate->nextState( "endRoundCleanUp" );
				}
				else
				{ // someone still has to take their turn
						//throw new feException( "round does not end" );

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

						case "Pulse Cannon":
								return 4;

						case "Tractor Beam":
								return 5;

						case "Saucer Teleporter":
								return 6;

						case "Cloaking Device":
								return 7;

						case "Waste Accelerator":
								return 8;

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

						case "Kinetic Siphon":
								return 14;

						case "Cargo Hold":
								return 15;

						case "Proximity Mines":
								return 16;

						case "Landing Legs":
								return 17;

						case "Quake Maker":
								return 18;

						case "Rotational Stabilizer":
								return 19;

						case "Airlock":
								return 20;

						case "Acceleration Regulator":
								return 24;

						case "Boost Amplifier":
								return 25;

						case "Organic Triangulator":
								return 26;

						default:
								return 0;
				}
		}

		function isUpgradePlayable($saucerColor, $upgradeName)
		{
			$sql = "SELECT is_playable FROM upgradeCards WHERE card_location='$saucerColor'";

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

					case "Pulse Cannon":
					case 4:
							$sql .= " AND card_type_arg=4";
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

					case "Waste Accelerator":
					case 8:
							$sql .= " AND card_type_arg=8";
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

					case "Kinetic Siphon":
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

					case "Quake Maker":
					case 18:
							$sql .= " AND card_type_arg=18";
							break;

					case "Rotational Stabilizer":
					case 19:
							$sql .= " AND card_type_arg=19";
							break;

					case "Airlock":
					case 20:
							$sql .= " AND card_type_arg=20";
							break;

					case "Acceleration Regulator":
					case 24:
							$sql .= " AND card_type_arg=24";
							break;

					case "Boost Amplifier":
					case 25:
							$sql .= " AND card_type_arg=25";
							break;

					case "Organic Triangulator":
					case 3:
							$sql .= " AND card_type_arg=26";
							break;
			}

			// add a limit of 1 mainly just during testing where the same saucer may have multiple copies of the same upgrade in hand
			$sql .= " LIMIT 1";

			$isPlayable = self::getUniqueValueFromDb($sql);

			if($isPlayable == 1)
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

		function isPreTurnOrderState($stateName)
		{
			switch($stateName)
			{
				case "chooseMoveCard":
					return true;
			}

			return false;
		}

		function getLocationOfUpgradeCard($upgradeName)
		{
			$sql = "SELECT card_location FROM upgradeCards WHERE ";

			switch($upgradeName)
			{
					case "Blast Off Thrusters":
					case 1:
							$sql .= " card_type_arg=1";
							break;

					case "Wormhole Generator":
					case 2:
							$sql .= " card_type_arg=2";
							break;

					case "Afterburner":
					case 3:
							$sql .= " card_type_arg=3";
							break;

					case "Pulse Cannon":
					case 4:
							$sql .= " card_type_arg=4";
							break;

					case "Tractor Beam":
					case 5:
							$sql .= " card_type_arg=5";
							break;

					case "Saucer Teleporter":
					case 6:
							$sql .= " card_type_arg=6";
							break;

					case "Cloaking Device":
					case 7:
							$sql .= " card_type_arg=7";
							break;

					case "Waste Accelerator":
					case 8:
							$sql .= " card_type_arg=8";
							break;

					 case "Hyperdrive":
					case 9:
							$sql .= " card_type_arg=9";
							break;

					case "Scavenger Bot":
					case 10:
							$sql .= " card_type_arg=10";
							break;

					case "Distress Signaler":
					case 11:
							$sql .= " card_type_arg=11";
							break;

					case "Time Machine":
					case 12:
							$sql .= " card_type_arg=12";
							break;

					case "Regeneration Gateway":
					case 13:
							$sql .= " card_type_arg=13";
							break;

					case "Kinetic Siphon":
					case 14:
							$sql .= " card_type_arg=14";
							break;

					case "Cargo Hold":
					case 15:
							$sql .= " card_type_arg=15";
							break;

					case "Proximity Mines":
					case 16:
							$sql .= " card_type_arg=16";
							break;

					case "Landing Legs":
					case 17:
							$sql .= " card_type_arg=17";
							break;

					case "Quake Maker":
					case 18:
							$sql .= " card_type_arg=18";
							break;

					case "Rotational Stabilizer":
					case 19:
							$sql .= " card_type_arg=19";
							break;

					case "Airlock":
					case 20:
							$sql .= " card_type_arg=20";
							break;

					case "Acceleration Regulator":
					case 24:
							$sql .= " card_type_arg=24";
							break;

					case "Boost Amplifier":
					case 25:
							$sql .= " card_type_arg=25";
							break;

					case "Organic Triangulator":
					case 26:
							$sql .= " card_type_arg=26";
							break;
			}

			// add a limit of 1 mainly just during testing where the same saucer may have multiple copies of the same upgrade in hand
			$sql .= " LIMIT 1";

			$location = self::getUniqueValueFromDb($sql);

			return $location;
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

						case "Pulse Cannon":
						case 4:
								$sql .= " AND card_type_arg=4";
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

						case "Waste Accelerator":
						case 8:
								$sql .= " AND card_type_arg=8";
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

						case "Kinetic Siphon":
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

						case "Quake Maker":
						case 18:
								$sql .= " AND card_type_arg=18";
								break;

						case "Rotational Stabilizer":
						case 19:
								$sql .= " AND card_type_arg=19";
								break;

						case "Airlock":
						case 20:
								$sql .= " AND card_type_arg=20";
								break;

						case "Acceleration Regulator":
						case 24:
							$sql .= " AND card_type_arg=24";
							break;

						case "Boost Amplifier":
						case 25:
							$sql .= " AND card_type_arg=25";
							break;

						case "Organic Triangulator":
						case 26:
							$sql .= " AND card_type_arg=26";
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

						case "Pulse Cannon":
						case 4:
								$sql .= " AND card_type_arg=4";
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

						case "Waste Accelerator":
						case 8:
								$sql .= " AND card_type_arg=8";
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

						case "Kinetic Siphon":
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

						case "Quake Maker":
						case 18:
								$sql .= " AND card_type_arg=18";
								break;

						case "Rotational Stabilizer":
						case 19:
								$sql .= " AND card_type_arg=19";
								break;

						case "Airlock":
						case 20:
								$sql .= " AND card_type_arg=20";
								break;

						case "Acceleration Regulator":
						case 24:
								$sql .= " AND card_type_arg=24";
								break;

						case "Boost Amplifier":
						case 25:
								$sql .= " AND card_type_arg=25";
								break;

						case "Organic Triangulator":
						case 26:
								$sql .= " AND card_type_arg=26";
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

						case "Pulse Cannon":
						case 4:
								$sql .= " AND card_type_arg=4";
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

						case "Waste Accelerator":
						case 8:
								$sql .= " AND card_type_arg=8";
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

						case "Kinetic Siphon":
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

						case "Quake Maker":
						case 18:
								$sql .= " AND card_type_arg=18";
								break;

						case "Rotational Stabilizer":
						case 19:
								$sql .= " AND card_type_arg=19";
								break;

						case "Airlock":
						case 20:
								$sql .= " AND card_type_arg=20";
								break;

						case "Acceleration Regulator":
						case 24:
								$sql .= " AND card_type_arg=24";
								break;

						case "Boost Amplifier":
						case 25:
								$sql .= " AND card_type_arg=25";
								break;

						case "Organic Triangulator":
						case 26:
								$sql .= " AND card_type_arg=26";
								break;
				}

				// add a limit of 1 mainly just during testing where the same saucer may have multiple copies of the same upgrade in hand
				$sql .= "  ORDER BY times_activated_this_round DESC LIMIT 1";

				return self::getUniqueValueFromDb($sql);
		}

		function getUpgradeCardId($saucerColor, $nameOrCollectorNumber)
		{
			$sql = "SELECT card_id FROM upgradeCards WHERE card_location='$saucerColor' AND card_is_played=1";

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

						case "Pulse Cannon":
						case 4:
								$sql .= " AND card_type_arg=4";
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

						case "Waste Accelerator":
						case 8:
								$sql .= " AND card_type_arg=8";
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

						case "Kinetic Siphon":
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

						case "Acceleration Regulator":
						case 24:
								$sql .= " AND card_type_arg=24";
								break;

						case "Boost Amplifier":
						case 25:
								$sql .= " AND card_type_arg=25";
								break;

						case "Organic Triangulator":
						case 26:
								$sql .= " AND card_type_arg=26";
								break;
				}

				// add a limit of 1 mainly just during testing where the same saucer may have multiple copies of the same upgrade in hand
				$sql .= "  ORDER BY times_activated_this_round DESC LIMIT 1";

				$valueToReturn = self::getUniqueValueFromDb($sql);

				//if($nameOrCollectorNumber == "Time Machine")
				//	throw new feException( "valueToReturn:".$valueToReturn." name:".$nameOrCollectorNumber." sql for cardId:".$sql);


				return $valueToReturn;
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

				//throw new feException( "saucerWhoseTurnItIs:$saucerWhoseTurnItIs");

				// Set the last distance traveled to the full 

				if($this->isSaucerCrashed($saucerWhoseTurnItIs))
				{ // this saucer is crashed

						if($this->doesSaucerHaveUpgradePlayed($saucerWhoseTurnItIs, "Regeneration Gateway") &&
						$this->isUpgradePlayable($saucerWhoseTurnItIs, 'Regeneration Gateway'))
						{ // Regeneration Gateway active for player

								// save the state this was played in
								$cardId = $this->getUpgradeCardId($saucerWhoseTurnItIs, "Regeneration Gateway");
								$this->setUpgradeValue5($cardId, "BEFORE_TURN");

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
					//throw new feException( "begin turn saucerWhoseTurnItIs:$saucerWhoseTurnItIs");
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

								// put the Crewmember on this crash site
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
			//throw new feException( "undoSavePoint");
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
				$ownerPlaying = $this->getOwnerIdOfOstrich($saucerWhoseTurnItIs);

				$distanceString = $this->convertDistanceTypeToString($distanceType);


				if(($distanceType == 0 && !$this->hasSaucerChosenX($saucerWhoseTurnItIs)) || 
					($this->doesSaucerHaveUpgradePlayed($saucerWhoseTurnItIs, "Hyperdrive") && $this->getSaucerOriginalTurnDistance($saucerWhoseTurnItIs) == 13))
				{ // saucer played an X and has not yet chosen its value or they have Hyperdrive and haven't selected their turn distance yet

						//throw new feException( "distanceType:$distanceType saucerWhoseTurnItIs:$saucerWhoseTurnItIs");

						// ask them to choose distance 0-5
						$this->gamestate->nextState( "chooseDistanceDuringMoveReveal" );
				}
				else
				{ // they did NOT play an X or has already chosen its value

						if($this->hasOverrideToken($saucerWhoseTurnItIs) || 
						  $this->canSaucerChooseDirection($saucerWhoseTurnItIs) || 
							 ($this->doesSaucerHaveUpgradePlayed($saucerWhoseTurnItIs, "Time Machine") &&
							 $this->getUpgradeTimesActivatedThisRound($saucerWhoseTurnItIs, "Time Machine") < 1 &&
							 $this->isUpgradePlayable($saucerWhoseTurnItIs, 'Time Machine')))
						{ // saucer has an override token or a Time Machine and has not yet chosen its value

									$saucerHighlightedText = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);

									if($this->hasOverrideToken($saucerWhoseTurnItIs))
									{ // from Override Token

											// notify players AND destroy the override token
											self::notifyAllPlayers( "useOverrideToken", clienttranslate( '${saucer_color_highlighted} is using their Override Token.' ), array(
													'saucer_color_highlighted' => $saucerHighlightedText
											) );

											// tell the game we've used it so we don't ask again
											$this->setHasOverrideToken($saucerWhoseTurnItIs, 0);
									}
									else if($this->canSaucerChooseDirection($saucerWhoseTurnItIs))
									{
										self::notifyAllPlayers( "wasCrashed", clienttranslate( '${saucer_color_highlighted} crashed before their turn so they are choosing their direction.' ), array(
											'saucer_color_highlighted' => $saucerHighlightedText
										) );
									}
									else
									{
											self::notifyAllPlayers( "useTimeMachine", clienttranslate( '${saucer_color_highlighted} is using their Time Machine.' ), array(
													'saucer_color_highlighted' => $saucerHighlightedText
											) );
									}

									$originalDistance = $this->getSaucerOriginalTurnDistance($saucerWhoseTurnItIs);
									$this->saveSaucerLastDistance($saucerWhoseTurnItIs, $originalDistance); // we need to set the last traveled distance to the original distance at the start of the player turn so we can always use the value for last distance traveled (other than when boosting)

									$this->gamestate->nextState( "chooseTimeMachineDirection" );
						}
						else
						{ // saucer does NOT have a reveal upgrade active or they have already chosen whether to activate it

							// reset whether we've asked about upgrades so you can use upgrades both before and after your turn (Pulse Cannon)
							$this->resetUpgradeActivatedThisRound("Pulse Cannon", $saucerWhoseTurnItIs); // just reset pulse cannon because if you do all, it will mess up others like Time Machine
							//$this->resetAllUpgradesActivatedThisRound();

							self::notifyAllPlayers( "cardRevealed", clienttranslate( '${saucer_color_highlighted} revealed a ${distance_string} in the ${direction} direction.' ), array(
								'saucer_color' => $saucerWhoseTurnItIs,
								'distance_type' => $distanceType,
								'distance_string' => $distanceString,
								'direction' => $direction,
								'saucer_color_highlighted' => $saucerColorFriendly
						) 	);

									if($distanceType == 0)
									{ // played a 0-5

										// increment stat
										self::incStat( 1, 'Xs_played', $ownerPlaying );
									}
									if($distanceType == 1)
									{ // played a 2

										// increment stat
										self::incStat( 1, '2s_played', $ownerPlaying );

										if($this->hasAvailableBoosterSlot($saucerWhoseTurnItIs))
										{ // has an available booster slot

												// give a booster
												$this->giveSaucerBooster($saucerWhoseTurnItIs);
										}

										if(!$this->doesSaucerHaveUpgradePlayed($saucerWhoseTurnItIs, "Hyperdrive"))
										{ // they don't have Hyperdrive (because if they do, they have already set this by selecting a value and we don't want to overwrite it)

											$this->saveSaucerLastDistance($saucerWhoseTurnItIs, 2); // update the last distance traveled so we can use last distance traveled any time we execute a move
											$this->setSaucerOriginalTurnDistance( $saucerWhoseTurnItIs, 2 ); // set the original turn distance too so we know how far to go with a Booster
										}
									}
									elseif($distanceType == 2)
									{ // played a 3

										// update stats
										self::incStat( 1, '3s_played', $ownerPlaying );

										// give an energy
										$this->giveSaucerEnergy($saucerWhoseTurnItIs);

										if(!$this->doesSaucerHaveUpgradePlayed($saucerWhoseTurnItIs, "Hyperdrive"))
										{ // they don't have Hyperdrive (because if they do, they have already set this by selecting a value and we don't want to overwrite it)

											$this->saveSaucerLastDistance($saucerWhoseTurnItIs, 3); // update the last distance traveled so we can use last distance traveled any time we execute a move
											$this->setSaucerOriginalTurnDistance( $saucerWhoseTurnItIs, 3); // set the original turn distance too so we know how far to go with a Booster
										}
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

		function removeSummoningSickness()
		{
			$sqlUpdate = "UPDATE upgradeCards SET ";
				$sqlUpdate .= "is_playable='1' WHERE card_is_played='1'";

				self::DbQuery( $sqlUpdate );
		}

		// IN PHASE: endRoundPhase
		// PURPOSE: Do anything we need to do at the end of a round and then go to the PLAN phase, such as:
		//    1. Place any saucers that crashed. (starting with Probe player and going clockwise)
		//    2. Move the Probe.
		function endRoundCleanup()
		{
//throw new feException( "endRoundCleanup");

				// allow upgrades that were played this round to be used
				$this->removeSummoningSickness();

				// erase all choices players made for their X value
				$this->resetXValueChoices();

				// mark all crash penalties to 0 
				$this->resetCrashPenalties();

				// reset value for who murdered a saucer
				$this->resetAllCliffPushers();

				// make all turn-related saucer values 0
				$this->resetSaucers();

				// reset any saucers who got to choose their direction
				$this->resetAllSaucerChooseDirection();

				// set all card to the unchosen state
				$this->resetAllCardChosenState();

				// mark all upgrades as not having been activated yet in the round
				$this->resetAllUpgradesActivatedThisRound();

				// reset all choices for who goes first
				$this->resetOstrichChosen(); // mark all ostriches as not yet chosen
			

				// starting with Probe player and going clockwise, check each Saucer to see if one crashed
				$crashedSaucer = $this->getSaucerThatCrashed();
				if($crashedSaucer != '')
				{ // we found a saucer that is crashed
					//throw new feException( "Crashed saucer: ".$crashedSaucer);
						$ownerOfSaucer = $this->getOwnerIdOfOstrich($crashedSaucer);

						//throw new feException( "Activating player ".$ownerOfSaucer.".");

						// make the saucer owner active and ask them to click a button to place it
						$this->gamestate->changeActivePlayer( $ownerOfSaucer );

						if($this->doesSaucerHaveUpgradePlayed($crashedSaucer, "Regeneration Gateway") &&
						$this->isUpgradePlayable($crashedSaucer, 'Regeneration Gateway'))
						{ // Regeneration Gateway active for player

							// save which state this was used in
							$cardId = $this->getUpgradeCardId($crashedSaucer, "Regeneration Gateway");
							$this->setUpgradeValue5($cardId, "AFTER_ROUND");

							$this->gamestate->nextState( "chooseCrashSiteRegenerationGateway" );
						}
						else
						{ // player does NOT have Regeneration Gateway active

								// randomly place Saucer
								$this->executeClickedSaucerToPlace($crashedSaucer);
								//$this->gamestate->nextState( "endRoundPlaceCrashedSaucer" );
								$this->gamestate->nextState( "endRoundCleanUp" );
						}
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
		}

		function giveOverrideTokens()
		{
//			throw new feException( "giveOverrideTokens");
				$numberOfPlayers = $this->getNumberOfPlayers();
				$saucerWithProbe = $this->getSaucerWithProbe();
				$playerWithProbe = $this->getOwnerIdOfOstrich($saucerWithProbe);

				if($numberOfPlayers > 4)
				{ // 5 or 6 player game

						// give the last player in turn order
						$saucerGoingLast = $this->getSaucerGoingLast();
						$this->setHasOverrideToken($saucerGoingLast, 1);

						$saucerGoingLastHighlightedText = $this->convertColorToHighlightedText($saucerGoingLast);
						self::notifyAllPlayers( "giveOverrideToken", clienttranslate( '${saucer_color_highlighted} is going last so they will get to move in any direction.' ), array(
								'saucer_color' => $saucerGoingLast,
								'saucer_color_highlighted' => $saucerGoingLastHighlightedText
						) );
				}

				if($numberOfPlayers > 5)
				{ // 6-player game

						// also give an override token to the player who is going second-to-last
						$saucerGoingSecondToLast = $this->getSaucerGoingSecondToLast();
						$this->setHasOverrideToken($saucerGoingSecondToLast, 1);

						$saucerGoingSecondToLastHighlightedText = $this->convertColorToHighlightedText($saucerGoingSecondToLast);
						self::notifyAllPlayers( "giveOverrideToken", clienttranslate( '${saucer_color_highlighted} is going second to last so they will get to move in any direction.' ), array(
								'saucer_color' => $saucerGoingSecondToLast,
								'saucer_color_highlighted' => $saucerGoingLastHighlightedText
						) );
				}
		}

		function moveTheProbe()
		{
				// see which players have the least total crewmembers
				$playersDictionary = array();

				$allPlayers = $this->getAllPlayers();
				foreach( $allPlayers as $player )
				{ // go through each player
						$playerId = $player['player_id'];
						$playersDictionary[$playerId] = array();
						$playersDictionary[$playerId]['crewmemberCount'] = 0;
						$playersDictionary[$playerId]['playerId'] = $playerId;
						$playersDictionary[$playerId]['saucerColor'] = 'unknown';

						$allPlayersSaucers = $this->getSaucersForPlayer($playerId);
						foreach( $allPlayersSaucers as $saucer )
						{ // go through each saucer owned by this player

								$saucerColor = $saucer['ostrich_color'];
								$totalCrewmembersOfSaucer = $this->getSeatedCrewmembersForSaucer($saucerColor);
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
								$allPlayersSaucers = $this->getSaucersForPlayer($playerId);
								foreach( $allPlayersSaucers as $saucer )
								{ // go through each saucer owned by this player

										$saucerColor = $saucer['ostrich_color'];

										// they get the probe
										$this->giveProbe($saucerColor, "Least");
								}

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
											$playerId = $playerDetails['playerId']; // we already know they were the most recent to go so they get the probe

											$saucerColor = $playerDetails['saucerColor'];
											if($playerId == $currentPlayer)
											{ // this player is tied for the lowest total crewmembers

													$allPlayersSaucers = $this->getSaucersForPlayer($playerId);
													foreach( $allPlayersSaucers as $saucer )
													{ // go through each saucer owned by this player

															$saucerColor = $saucer['ostrich_color'];


															$this->giveProbe($saucerColor, "TiedWentLast");
													}

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
				$saucerWhoseTurnItIsColorFriendly = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);
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
				$saucerWhoseTurnItIsColorFriendly = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);
				$upgradeName = $this->getUpgradeTitleFromCollectorNumber(3);


				$validSpaces = $this->getValidSpacesForUpgrade($saucerWhoseTurnItIs, "Afterburner");

				// return both the location of all the
				return array(
						'saucerColor' => $saucerWhoseTurnItIsColorFriendly,
						'upgradeName' => $upgradeName,
						'validSpaces' => $validSpaces
				);
		}

		function argGetOrganicTriangulatorSpaces()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerWhoseTurnItIsColorFriendly = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);
				$upgradeName = $this->getUpgradeTitleFromCollectorNumber(26);


				$validSpaces = $this->getValidSpacesForUpgrade($saucerWhoseTurnItIs, "Organic Triangulator");

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
				$saucerWhoseTurnItIsColorFriendly = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);
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
				$saucerWhoseTurnItIsColorFriendly = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);

				// return both the location of all the
				return array(
						'saucerColor' => $saucerWhoseTurnItIsColorFriendly,
						'emptyCrashSites' => self::getAllUnoccupiedCrashSites()
				);
		}

		function argGetEndOfTurnUpgradesToActivate()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerWhoseTurnItIsColorFriendly = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);

				return array(
						'endOfTurnUpgradesToActivate' => self::getEndOfTurnUpgradesToActivateForSaucer($saucerWhoseTurnItIs),
						'saucerColor' => $saucerWhoseTurnItIsColorFriendly,
				);
		}

		function argGetStartOfTurnUpgradesToActivate()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();

				return array(
						'startOfTurnUpgradesToActivate' => self::getStartOfTurnUpgradesToActivateForSaucer($saucerWhoseTurnItIs)
				);
		}

		function argGetPulseCannonSaucers()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerWhoseTurnItIsColorFriendly = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);
				$upgradeName = $this->getUpgradeTitleFromCollectorNumber(4);

				$validSaucers = self::getPulseCannonSaucers();
				$stateUsedIn = $this->getUpgradeValue5($saucerWhoseTurnItIs, "Pulse Cannon"); // askWhichStartOfTurnUpgradeToUse
				$stateUsedInShort = "EndOfTurn";
				if($stateUsedIn == "askWhichStartOfTurnUpgradeToUse")
				{
						$stateUsedInShort = "StartOfTurn";
				}

				//throw new feException( "stateUsedIn: $stateUsedIn" );

				// return both the location of all the
				return array(
						'saucerColor' => $saucerWhoseTurnItIsColorFriendly,
						'upgradeName' => $upgradeName,
						'validSaucers' => $validSaucers,
						'stateUsedIn' => $stateUsedInShort
				);
		}

		function argGetTractorBeamCrewmembers()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerWhoseTurnItIsColorFriendly = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);
				$upgradeName = $this->getUpgradeTitleFromCollectorNumber(5);

				$validCrewmembers = $this->getCrewmembersWithinTractorBeam($saucerWhoseTurnItIs);

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
				$saucerWhoseTurnItIsColorFriendly = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);
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
				$saucerWhoseTurnItIsColorFriendly = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);
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
				$saucerWhoseTurnItIsColorFriendly = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);
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

		function argAskToWasteAccelerate()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerWhoseTurnItIsColorFriendly = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);
				//$saucerToCrash = $this->nextPendingCrashReward($saucerWhoseTurnItIs);

//throw new feException( "saucerWhoseTurnItIs: $saucerWhoseTurnItIs saucerToCrash: $saucerToCrash" );
				return array(
						'saucerColor' => $saucerWhoseTurnItIsColorFriendly
				);
		}

		function argChooseAcceleratorDistance()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerWhoseTurnItIsColorFriendly = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);
				//$saucerToCrash = $this->nextPendingCrashReward($saucerWhoseTurnItIs);

				$distanceOptions = $this->getSaucerAcceleratorDistanceOptions($saucerWhoseTurnItIs); // return an array of the distances the saucer can travel for their turn
				$moves = $this->getValidSpacesForUpgrade($saucerWhoseTurnItIs, "Acceleration Regulator");

				$startingXLocation = $this->getSaucerXLocation($saucerWhoseTurnItIs);
				$startingYLocation = $this->getSaucerYLocation($saucerWhoseTurnItIs);

//throw new feException( "saucerWhoseTurnItIs: $saucerWhoseTurnItIs saucerToCrash: $saucerToCrash" );
				return array(
						'saucerColor' => $saucerWhoseTurnItIsColorFriendly,
						'distanceOptions' => $distanceOptions,
						'currentSpaceOptions' => $moves,
						'startingXLocation' => $startingXLocation,
						'startingYLocation' => $startingYLocation
				);
		}

		function argChooseBoosterDistance()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerWhoseTurnItIsColorFriendly = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);
				//$saucerToCrash = $this->nextPendingCrashReward($saucerWhoseTurnItIs);

				$distanceOptions = $this->getSaucerBoosterDistanceOptions($saucerWhoseTurnItIs); // return an array of the distances the saucer can travel for their turn
				$moves = $this->getValidSpacesForUpgrade($saucerWhoseTurnItIs, "Boost Amplifier");

				$startingXLocation = $this->getSaucerXLocation($saucerWhoseTurnItIs);
				$startingYLocation = $this->getSaucerYLocation($saucerWhoseTurnItIs);

//throw new feException( "saucerWhoseTurnItIs: $saucerWhoseTurnItIs saucerToCrash: $saucerToCrash" );
				return array(
						'saucerColor' => $saucerWhoseTurnItIsColorFriendly,
						'distanceOptions' => $distanceOptions,
						'currentSpaceOptions' => $moves,
						'startingXLocation' => $startingXLocation,
						'startingYLocation' => $startingYLocation
				);
		}

		function argAskToRotationalStabilizer()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerWhoseTurnItIsColorFriendly = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);
				//$saucerToCrash = $this->nextPendingCrashReward($saucerWhoseTurnItIs);

//throw new feException( "saucerWhoseTurnItIs: $saucerWhoseTurnItIs saucerToCrash: $saucerToCrash" );
				return array(
						'saucerColor' => $saucerWhoseTurnItIsColorFriendly,
						'saucerOrder' => self::getClockwiseCounterTurnOrders()
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
				$ownerOfSaucerStealing = $this->getOwnerIdOfOstrich($saucerStealing);
				$crashedSaucer = $this->nextPendingCrashReward($saucerStealing);
				$crashedSaucerText = $this->convertColorToHighlightedText($crashedSaucer);
				$saucerStealingText = $this->convertColorToHighlightedText($saucerStealing);

				$stealableCrewmembers = array();

				
				$stealableCrewmembers = self::getStealableCrewmembersFromSaucer($crashedSaucer);
				

				// return both the location of all the
				return array(
						'saucerWhoCrashed' => $crashedSaucer,
						'saucerWhoCrashedText' => $crashedSaucerText,
						'saucerWhoIsStealingText' => $saucerStealingText,
						'stealableCrewmembers' => $stealableCrewmembers
				);
		}

		function argGetPassableCrewmembers()
		{
				$saucerColorGiving = $this->getOstrichWhoseTurnItIs();
				$saucerColorReceiving = $this->getPlayersOtherSaucer($saucerColorGiving);
				$saucerGivingText = $this->convertColorToHighlightedText($saucerColorGiving);
				$saucerReceivingText = $this->convertColorToHighlightedText($saucerColorReceiving);

				$eligibleToPass = array();

				// get crewmember types that are eligible to be passed
				$passable = $this->getPassableCrewmembersFromSaucer($saucerColorGiving);

				foreach($passable as $crewmember)
				{ // go through each crewmember on this saucer that matches the saucer on their team
					$isPassable = $crewmember['isPassable']; 
					if($isPassable == 1)
					{ // this crewmember was on board when they passed by their friendly saucer
						array_push($eligibleToPass, $crewmember);
					}
				}

				// return both the location of all the
				return array(
						'saucerColorGiving' => $saucerGivingText,
						'saucerColorReceiving' => $saucerReceivingText,
						'passableCrewmembers' => $eligibleToPass
				);
		}

		function argGetTakeableCrewmembers()
		{
				$saucerColorReceiving = $this->getOstrichWhoseTurnItIs();
				$saucerColorGiving = $this->getPlayersOtherSaucer($saucerColorReceiving);
				$saucerGivingText = $this->convertColorToHighlightedText($saucerColorGiving);
				$saucerReceivingText = $this->convertColorToHighlightedText($saucerColorReceiving);

				$eligibleToPass = array();

				// get crewmember types that are eligible to be passed
				$passable = $this->getPassableCrewmembersFromSaucer($saucerColorGiving);

				foreach($passable as $crewmember)
				{ // go through each crewmember on this saucer that matches the saucer on their team
					$isPassable = $crewmember['isPassable']; 
					if($isPassable == 1)
					{ // this crewmember was on board when they passed by their friendly saucer
						array_push($eligibleToPass, $crewmember);
					}
				}

				// return both the location of all the
				return array(
						'saucerColorGiving' => $saucerGivingText,
						'saucerColorReceiving' => $saucerReceivingText,
						'takeableCrewmembers' => $eligibleToPass
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
				$saucerGivingAwayText = $this->convertColorToHighlightedText($saucerGivingAway);

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
				$saucerWhoseTurnItIs = $this->getSaucerWhoseTurnItIs();
				$saucerWhoseTurnItIsColorFriendly = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);

				return array(
						'otherUncrashedSaucers' => self::getOtherUncrashedSaucers(),
						'saucerColor' => $saucerWhoseTurnItIsColorFriendly
				);
		}

		function argGetAllXMoves()
		{
				$saucerColor = $this->getOstrichWhoseTurnItIs();
				
				$direction = $this->getSaucerDirection($saucerColor);
				if($this->hasOverrideToken($saucerColor) || 
				$this->canSaucerChooseDirection($saucerColor) || 
				($this->doesSaucerHaveUpgradePlayed($saucerColor, "Time Machine") &&
				$this->getUpgradeTimesActivatedThisRound($saucerColor, "Time Machine") < 1 &&
				$this->isUpgradePlayable($saucerColor, 'Time Machine')))
				{ // this saucer can go in any direction
					$direction = '';
				}

				$distanceOptions = $this->getSaucerOriginalTurnDistanceOptions($saucerColor); // return an array of the distances the saucer can travel for their turn

				$startingXLocation = $this->getSaucerXLocation($saucerColor);
				$startingYLocation = $this->getSaucerYLocation($saucerColor);
				return array(
						'playerSaucerMoves' => self::getSaucerAcceleratorAndBoosterMoves(),
						'direction' => $direction,
						'startingXLocation' => $startingXLocation,
						'startingYLocation' => $startingYLocation,
						'distanceOptions' => $distanceOptions
				);
		}

		// get the space we want to highlight when someone is choosing whether to use Hyperdrive
		function argGetHyperdriveHighlights()
		{
			$saucerColor = $this->getSaucerWhoseTurnItIs();
//			$moves = $this->getSaucerEligibleSpaces($saucerColor);
			$moves = $this->getValidSpacesForUpgrade($saucerColor, "Hyperdrive");

			//throw new feException( "saucerWhoseTurnItIs: $saucerWhoseTurnItIs saucerToCrash: $saucerToCrash" );

			return array(
					'currentSpaceOptions' => $moves
			);
		}

		function argGetDirectionHighlights()
		{
			$saucerColor = $this->getSaucerWhoseTurnItIs();
			$saucerWhoseTurnItIsColorFriendly = $this->convertColorToHighlightedText($saucerColor);
//			$moves = $this->getSaucerEligibleSpaces($saucerColor);
			$moves = $this->getValidSpacesForUpgrade($saucerColor, "Time Machine");

			//throw new feException( "saucerWhoseTurnItIs: $saucerWhoseTurnItIs saucerToCrash: $saucerToCrash" );

			return array(
					'currentSpaceOptions' => $moves,
					'saucerColor' =>$saucerWhoseTurnItIsColorFriendly
			);
		}

		function argGetSaucerAcceleratorMoves()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerHighlightedText = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);

				$moveType = $this->getMoveTypeWeAreExecuting();

				$moves = self::getSaucerAcceleratorAndBoosterMoves($moveType, $saucerWhoseTurnItIs, false, true);

				return array(
						'playerSaucerAcceleratorMoves' => $moves,
						'saucerColor' => $saucerHighlightedText
				);
		}

		function argGetSaucerBoosterMoves()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerHighlightedText = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);

				$moveType = $this->getMoveTypeWeAreExecuting();

				$moves = self::getSaucerAcceleratorAndBoosterMoves($moveType, $saucerWhoseTurnItIs, true, false);

				return array(
						'playerSaucerBoosterMoves' => $moves,
						'saucerColor' => $saucerHighlightedText
				);
		}

		function argGetSaucerColor()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$saucerColorFriendly = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);

				// return the saucer color so it can be used in the description
				return array(
						'saucerColor' => $saucerColorFriendly
				);
		}

		function argGetSaucerMoveCardInfo()
		{
				$saucerWhoseTurnItIs = $this->getOstrichWhoseTurnItIs();
				$distanceType = $this->getSaucerDistanceType($saucerWhoseTurnItIs); // 0=X, 1=2, 2=3
				$direction = $this->getSaucerDirection($saucerWhoseTurnItIs);
				$saucerColorText = $this->convertColorToHighlightedText($saucerWhoseTurnItIs);
				return array(
						'distanceType' => $distanceType,
						'saucerColor' => $saucerWhoseTurnItIs,
						'direction' => $direction,
						'saucerColorText' => $saucerColorText
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
			$activePlayer = self::getActivePlayerId(); // Current Player = player who played the current player action (the one who made the AJAX request). Active Player = player whose turn it is.
			//throw new feException( "activePlayer: $activePlayer" );
			$saucersForPlayer = $this->getSaucersForPlayer($activePlayer);

			$allSaucerMoves = array();
			foreach($saucersForPlayer as $saucer)
			{
				$saucerColor = $saucer['ostrich_color'];
				//$moves = $this->getSaucerAcceleratorAndBoosterMoves('regular', $color);
				$directionSelected = $this->getSaucerDirection($saucerColor);
				$distanceSelected = $this->getSaucerDistanceType($saucerColor);
				$movesForSaucer = $this->getMovesForSaucer($saucerColor, $distanceSelected, $directionSelected);
	
				array_push($allSaucerMoves, $movesForSaucer);
			}

			return array(
						'saucerButtons' => self::getSaucerGoFirstButtons(),
						'saucerMoves' => $allSaucerMoves
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

		function resetAllSaucerChooseDirection()
		{
			$sql = "UPDATE ostrich SET ostrich_is_dizzy=0";
			self::DbQuery( $sql );
		}
		
		function setCanChooseDirection($saucerColor, $value)
		{
			$sql = "UPDATE ostrich SET ostrich_is_dizzy=$value WHERE ";
				$sql .= "ostrich_color='$saucerColor'";
				self::DbQuery( $sql );
		}

		function canSaucerChooseDirection($saucerColor)
		{
			$canChoose = self::getUniqueValueFromDb("SELECT ostrich_is_dizzy FROM ostrich WHERE ostrich_color='$saucerColor'");

			if($canChoose == 0)
			{
				return false;
			}
			else
			{
				return true;
			}
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
		$saucerWhoseTurnItIs = $this->getSaucerWhoseTurnItIs();

        // Make sure player is in a non blocking status for role turn
        switch ($statename) 
		{
			case 'chooseMoveCard':
				$saucer1Color = ''; // ff0000, 0000ff, etc.
				$saucer1Distance = '0'; // 0, 1, 2
				$saucer1Direction = 'sun'; // asteroids
				$saucer2Color = ''; // ff0000, 0000ff, etc.
				$saucer2Distance = '0'; // 0, 1, 2
				$saucer2Direction = 'sun'; // asteroids
	
				$saucersForPlayer = $this->getSaucersForPlayer($active_player);
				foreach( $saucersForPlayer as $saucer )
				{ // go through each saucer of this player
					if($saucer1Color == '')
					{ // this is the first saucer we've seen from this player
						$saucer1Color = $saucer['ostrich_color'];
					}
					else
					{ // this is the second saucer we've seen from this player
						$saucer2Color = $saucer['ostrich_color'];
					}
				}
	
				$this->executeClickedConfirmMove( $saucer1Color, $saucer1Distance, $saucer1Direction, $saucer2Color, $saucer2Distance, $saucer2Direction );
	
			break;

			case 'askWhichUpgradeToPlay':

				// get the database ID of a card available for them to choose
				$databaseId = 0;
				$upgradeList = $this->upgradeCards->getCardsInLocation('drawn');
				foreach( $upgradeList as $card )
				{ // go through all the cards drawn
					$databaseId = $card['id']; // get the database id of the card
				}
				$this->executeClickedUpgradeCardInHand($databaseId, $saucerWhoseTurnItIs);
			break;

			case 'setActivePlayerToProbePlayer':
			case 'chooseTileRotationQuakeMaker':
			case 'askToWasteAccelerate':
			case 'chooseSaucerPulseCannon':
			case 'askToRotationalStabilizer':
			case 'askToProximityMine':
			case 'argChooseAcceleratorDistance':
			case 'chooseDistressSignalerGiveCrewmember':
			case 'chooseDistressSignalerTakeCrewmember':
			case 'chooseCrewmemberToAirlock':
			case 'chooseTractorBeamCrewmember':
			case 'chooseAfterburnerSpace':
			case 'chooseOrganicTriangulatorSpace':
			case 'chooseBlastOffThrusterSpace':
			case 'chooseLandingLegsSpace':
			case 'chooseCrewmembersToTake':
			case 'chooseCrewmembersToPass':
			case 'chooseCrashSiteSaucerTeleporter':
			case 'chooseSaucerWormholeGenerator':
			case 'askWhichEndOfTurnUpgradeToUse':
			case 'crashPenaltyAskWhichToSteal':
			case 'crashPenaltyAskWhichToGiveAway':
			case 'chooseWhetherToHyperdrive':
			case 'askWhichStartOfTurnUpgradeToUse':
			case 'chooseTimeMachineDirection':
			case 'chooseDistanceDuringMoveReveal':
			case 'finalizeMove':
			case 'chooseIfYouWillUseBooster':
			case 'beginTurn':
				$this->gamestate->nextState( "endSaucerTurnCleanUp" );
			break;

			case 'placeCrewmemberChooseCrewmember':
				
				// find a valid crewmember to place
				$crewmemberTypeString = '';
				$crewmemberColor = '';
				$lostCrewmembers = $this->getLostCrewmembers(); // the first crewmember in the queue for each saucer
				foreach($lostCrewmembers as $crewmember)
				{
					$crewmemberColor = $crewmember['garment_color'];
					$crewmemberTypeInt = $crewmember['garment_type'];
					$crewmemberTypeString = $this->convertGarmentTypeIntToString($crewmemberTypeInt);
				}
				
				// randomly place it
				$this->executeReplaceGarmentChooseGarment($crewmemberTypeString, $crewmemberColor);
			break;

			case 'chooseCrashSiteRegenerationGateway':
			case 'askPreTurnToPlaceCrashedSaucer':
				// place it at a random location
				$foundUnoccupiedCrashSite = $this->randomlyPlaceSaucer($saucerWhoseTurnItIs);

				// end their turn
				$this->gamestate->nextState( "endSaucerTurnCleanUp" );


			break;
			case 'chooseAcceleratorDirection':
				// move them off the board (they will be randomly placed at the end of the round)
				$this->placeSaucerOnSpace($saucerWhoseTurnItIs, 0, 0);

				// do not give the next player who takes a turn credit for crashing them
				$this->markCrashPenaltyRendered($saucerWhoseTurnItIs);

				// end their turn
				$this->gamestate->nextState( "endSaucerTurnCleanUp" );
			break;

			default:
				throw new feException( "Zombie mode not supported at this game state: ".$statename );
			break;
        }        
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
