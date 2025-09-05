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
 * states.inc.php
 *
 * CrashAndGrab game states description
 *
 */

/*
   Game state machine is a tool used to facilitate game developpement by doing common stuff that can be set up
   in a very easy way from this configuration file.

   Please check the BGA Studio presentation about game state to understand this, and associated documentation.

   Summary:

   States types:
   _ activeplayer: in this type of state, we expect some action from the active player.
   _ multipleactiveplayer: in this type of state, we expect some action from multiple players (the active players)
   _ game: this is an intermediary state where we don't expect any actions from players. Your game logic must decide what is the next game state.
   _ manager: special type for initial and final state

   Arguments of game states:
   _ name: the name of the GameState, in order you can recognize it on your own code.
   _ description: the description of the current game state is always displayed in the action status bar on
                  the top of the game. Most of the time this is useless for game state with "game" type.
   _ descriptionmyturn: the description of the current game state when it's your turn.
   _ type: defines the type of game states (activeplayer / multipleactiveplayer / game / manager)
   _ action: name of the method to call when this game state become the current game state. Usually, the
             action method is prefixed by "st" (ex: "stMyGameStateName").
   _ possibleactions: array that specify possible player actions on this step. It allows you to use "checkAction"
                      method on both client side (Javacript: this.checkAction) and server side (PHP: self::checkAction).
   _ transitions: the transitions are the possible paths to go from a game state to another. You must name
                  transitions in order to use transition names in "nextState" PHP method, and use IDs to
                  specify the next game state for each transition.
   _ args: name of the method to call to retrieve arguments for this gamestate. Arguments are sent to the
           client side to be used on "onEnteringState" or to set arguments in the gamestate description.
   _ updateGameProgression: when specified, the game progression is updated (=> call to your getGameProgression
                            method).
*/

//    !! It is not a good idea to modify this file when a game is running !!


$machinestates = array(

    // The initial state. Please do not modify.
    1 => array(
        "name" => "gameSetup",
        "description" => "",
        "type" => "manager",
        "action" => "stGameSetup",
        "transitions" => array( "" => 2 )
    ),

    // Note: ID=2 => your first state
    // PLAN PHASE (simultaneous)
    2 => array(
    		"name" => "chooseMoveCard",
    		"description" => clienttranslate('Everyone is choosing their moves.'),
    		"descriptionmyturn" => clienttranslate('You must choose your move DISTANCE and DIRECTION for this round.'),
    		"type" => "multipleactiveplayer",
        'args' => 'argGetAllPlayerSaucerMoves',
    		"possibleactions" => array( "clickMoveDirection", "clickDistance", "undoChooseMoveCard", "confirmMove", "clickUpgradeCardInHand", "undoConfirmMove" ),
    		"transitions" => array( "zigChosen" => 2, "startOver" => 2, "allMovesChosen" => 25, "zombiePass" => 98 )
    ),

    // END ROUND PHASE (game)
    5 => array(
        "name" => "endRoundCleanup",
        "description" => "",
        "type" => "game",
        "action" => "endRoundCleanup",
        "updateGameProgression" => true,
        "transitions" => array( "newRound" => 2, "endRoundPlaceCrashedSaucer" => 37, "chooseCrashSiteRegenerationGateway" => 40, "endRoundCleanUp" => 5 )
    ),

    // END TRAP PHASE - ENTER MOVE PHASE
    7 => array(
        "name" => "endTrapPhase",
        "description" => "",
        "type" => "game",
        "action" => "startMovePhase",
        "updateGameProgression" => false,
        "transitions" => array( "chooseOstrich" => 11, "chooseXValue" => 12, "askTrapBasic" => 19 )
    ),

    // PLAYER HIT ACCELERATOR SO WE NEED TO ASK WHICH DIRECTION THEY WANT TO TRAVEL
    9 => array(
    		"name" => "chooseAcceleratorDirection",
    		"description" => clienttranslate('${actplayer} is choosing their Accelerator direction.'),
    		"descriptionmyturn" => clienttranslate('You must choose the direction you will travel on the Accelerator.'),
        "type" => "activeplayer",
        'args' => 'argGetSaucerAcceleratorMoves',
        "possibleactions" => array( "clickAcceleratorDirection", "clickMoveDirection" ),
    		"transitions" => array( "chooseAcceleratorDirection" => 9, "chooseIfYouWillUseBooster" => 32, "endSaucerTurnCleanUp" => 50, "finalizeMove" => 49, "chooseCrewmembersToPass" => 56, "chooseCrewmembersToTake" => 57, "executingMove" => 70 )
    ),

    10 => array(
    		"name" => "placeCrewmemberChooseCrewmember",
    		"description" => clienttranslate('${saucerHighlighted} must choose a Crewmember to place.'),
    		"descriptionmyturn" => clienttranslate('You must choose a Crewmember to place.'),
    		"type" => "activeplayer",
        'args' => 'argGetLostCrewmembers',
    		"possibleactions" => array( "chooseLostCrewmember" ),
    		"transitions" => array( "placeCrewmemberChooseCrewmember" => 10, "endSaucerTurnCleanUp" => 50 )
    ),

    11 => array(
    		"name" => "chooseOstrich",
    		"description" => clienttranslate('${actplayer} must choose which ostrich they will move.'),
    		"descriptionmyturn" => clienttranslate('You must choose which ostrich you will move.'),
    		"type" => "activeplayer",
    		"possibleactions" => array( "chooseOstrich" ),
    		"transitions" => array( "chooseXValue" => 12, "askTrapBasic" => 19 )
    ),

    12 => array(
    		"name" => "chooseDistanceDuringMoveReveal",
    		"description" => clienttranslate('${actplayer} is choosing their distance.'),
    		"descriptionmyturn" => clienttranslate('You must choose the distance you want to travel.'),
    		"type" => "activeplayer",
        'args' => 'argGetAllXMoves',
    		"possibleactions" => array( "selectXValue" ),
    		"transitions" => array( "checkForRevealDecisions" => 38, "endSaucerTurnCleanUp" => 50 )
    ),

    14 => array(
    		"name" => "askUseZag",
    		"description" => clienttranslate('${actplayer} is deciding whether they will zag.'),
    		"descriptionmyturn" => clienttranslate('Would you like to zag?'),
    		"type" => "activeplayer",
    		"possibleactions" => array( "answerZagQuestion" ),
    		"transitions" => array( "askTrapBasic" => 19, "askUseSkateboard" => 9, "placeCrewmemberChooseCrewmember" => 10, "discardTrapCards" => 15, "askStealOrDraw" => 17, "endGame" => 99 )
    ),

    15 => array(
    		"name" => "discardTrapCards",
    		"description" => clienttranslate('Players are discarding Trap Cards.'),
    		"descriptionmyturn" => clienttranslate('You must discard down to 1 Trap Card.'),
    		"type" => "multipleactiveplayer",
    		"possibleactions" => array( "discardTrapCard" ),
    		"transitions" => array( "placeCrewmemberChooseCrewmember" => 10, "discardTrapCards" => 15 )
    ),

    17 => array(
    		"name" => "askStealOrDraw",
    		"description" => clienttranslate('${actplayer} is claiming their cliff-pushing reward.'),
    		"descriptionmyturn" => clienttranslate('You can draw 2 Zigs or steal an off-colored garment if they have one.'),
    		"type" => "activeplayer",
        'args' => 'argStealableGarments',
    		"possibleactions" => array( "clickDraw2Zigs", "stealGarmentClick" ),
    		"transitions" => array( "placeCrewmemberChooseCrewmember" => 10, "discardTrapCards" => 15, "askStealOrDraw" => 17, "chooseGarmentToSteal" => 23 )
    ),

    19 => array(
    		"name" => "askTrapBasic",
    		"description" => clienttranslate('${actplayer} is dealing with a trap.'),
    		"descriptionmyturn" => clienttranslate('You have been trapped.'),
    		"type" => "activeplayer",
    		"possibleactions" => array( "executeTrap" ),
    		"transitions" => array( "askTrapBasic" => 19 )
    ),

    20 => array(
    		"name" => "claimZag",
    		"description" => clienttranslate('Other players are claiming Zags.'),
    		"descriptionmyturn" => clienttranslate('You may discard 3 matching Zigs to claim a Zag.'),
    		"type" => "multipleactiveplayer",
        'args' => 'argGetOstriches',
    		"possibleactions" => array( "selectZigs", "claimZag", "skipClaimZag", "hideTurnDirection" ),
    		"transitions" => array( "transitionToChooseZig" => 22, "claimZag" => 20, "chooseZig" => 2 )
    ),

    22 => array(
        "name" => "transitionToChooseZig",
        "description" => "",
        "type" => "game",
        "action" => "checkStartOfTurnUpgrades",
        "updateGameProgression" => true,
        "transitions" => array( "chooseZig" => 2 )
    ),

    23 => array(
    		"name" => "askWhichGarmentToSteal",
    		"description" => clienttranslate('${actplayer} is choosing which garment they will steal.'),
    		"descriptionmyturn" => clienttranslate('You must choose which off-colored garment to steal.'),
    		"type" => "activeplayer",
        'args' => 'argStealableGarments',
        "possibleactions" => array( "stealGarmentClick" ),
    		"transitions" => array( "placeCrewmemberChooseCrewmember" => 10, "discardTrapCards" => 15, "askStealOrDraw" => 17 )
    ),

    24 => array(
        "name" => "checkStartOfTurnUpgrades",
        "description" => clienttranslate('Checking for start of turn upgrades...'),
        "type" => "game",
        "action" => "checkStartOfTurnUpgrades",
        "updateGameProgression" => false,
        "transitions" => array( "checkForRevealDecisions" => 38, "askWhichStartOfTurnUpgradeToUse" => 42 )
    ),

    25 => array(
        "name" => "rollRotationDie",
        "description" => "",
        "type" => "game",
        "action" => "rollRotationDie",
        "updateGameProgression" => true,
        "transitions" => array( "playerTurnStart" => 29, "askToRotationalStabilizer" => 68 )
    ),

    26 => array(
    		"name" => "chooseWhichSaucerGoesFirst",
    		"description" => clienttranslate('${actplayer} is choosing which Saucer will go first.'),
    		"descriptionmyturn" => clienttranslate('You must choose which of your Saucers will go first.'),
    		"type" => "activeplayer",
        'args' => 'argGetSaucerGoFirstButtons',
        "possibleactions" => array( "clickSaucerToGoFirst" ),
    		"transitions" => array( "locateCrashedSaucer" => 27, "checkStartOfTurnUpgrades" => 24 )
    ),

    27 => array(
        "name" => "saucerTurnStart",
        "description" => clienttranslate('Starting Saucer turn...'),
        "type" => "game",
        "action" => "saucerTurnStart",
        "updateGameProgression" => true,
        "transitions" => array( "chooseCrashSiteRegenerationGateway" => 40, "askPreTurnToPlaceCrashedSaucer" => 43, "checkStartOfTurnUpgrades" => 24, "beginTurn" => 44 )
    ),

    29 => array(
        "name" => "playerTurnStart",
        "description" => clienttranslate('Starting player turn...'),
        "type" => "game",
        "action" => "playerTurnStart",
        "updateGameProgression" => true,
        "transitions" => array( "chooseWhichSaucerGoesFirst" => 26, "saucerTurnStart" => 27 )
    ),

    32 => array(
    		"name" => "chooseIfYouWillUseBooster",
    		"description" => clienttranslate('${saucerColor} is deciding if they will boost.'),
    		"descriptionmyturn" => clienttranslate('Would you like to use a Booster?'),
    		"type" => "activeplayer",
        'args' => 'argGetSaucerBoosterMoves',
        "possibleactions" => array( "clickUseBooster", "clickSkipBooster", "clickMoveDirection", "clickAcceleratorDirection" ),
    		"transitions" => array( "chooseBoosterDistance" => 33, "endSaucerTurnCleanUp" => 50, "chooseAcceleratorDirection" => 9, "finalizeMove" => 49, "chooseCrewmembersToPass" => 56, "chooseCrewmembersToTake" => 57, "chooseIfYouWillUseBooster" => 32, "chooseCrewmemberToAirlock" => 63, "executingMove" => 70 )
    ),

    33 => array(
        "name" => "chooseBoosterDistance",
        "description" => clienttranslate('${saucerColor} is deciding whether they will use their Boost Amplifier.'),
        "descriptionmyturn" => clienttranslate('Acceleration Regulator: How far would you like to travel during this Boost?'),
        "type" => "activeplayer",
        'args' => 'argChooseBoosterDistance',
        "possibleactions" => array( "selectXValue" ),
        "transitions" => array(  "executingMove" => 70, "chooseIfYouWillUseBooster" => 32, "chooseAcceleratorDirection" => 9, "finalizeMove" => 49, "chooseCrewmembersToPass" => 56, "askToProximityMine" => 67, "endSaucerTurnCleanUp" => 50, "checkForRevealDecisions" => 38, "chooseCrewmembersToTake" => 57, "chooseCrewmemberToAirlock" => 63, "askToWasteAccelerate" => 71, "chooseAcceleratorDistance" => 66, "endGame" => 99 )
    ),

    34 => array(
    		"name" => "allCrashSitesOccupiedChooseSpaceEndRound",
    		"description" => clienttranslate('${actplayer} is choosing where to be placed because all Crash Sites are occupied.'),
    		"descriptionmyturn" => clienttranslate('You must choose a space to place your ${saucerColor} Saucer that is not in the row or column of a Crewmember.'),
    		"type" => "activeplayer",
        'args' => 'argGetAllCrashSitesOccupiedDetails',
        "possibleactions" => array( "chooseSaucerSpace" ),
    		"transitions" => array( "endRoundCleanUp" => 5 )
    ),

    36 => array(
        "name" => "endSaucerTurn",
        "description" => clienttranslate('End of turn'),
        "type" => "game",
        "action" => "endSaucerTurn",
        "updateGameProgression" => true,
        "transitions" => array( "playerTurnStart" => 29, "endRoundCleanUp" => 5 )
    ),

    37 => array(
        "name" => "endRoundPlaceCrashedSaucer",
        "description" => clienttranslate('A crashed Saucer is coming back onto the board.'),
    		"descriptionmyturn" => clienttranslate('Place your crashed Saucer.'),
        "type" => "activeplayer",
        'args' => 'argGetSaucerToPlaceButton',
        "possibleactions" => array( "clickSaucerToPlace" ),
    		"transitions" => array( "allCrashSitesOccupiedChooseSpaceEndRound" => 34, "endRoundCleanUp" => 5, "newRound" => 2  )
    ),

    38 => array(
        "name" => "checkForRevealDecisions",
        "description" => clienttranslate('Checking for reveal decisions...'),
        "type" => "game",
        "action" => "checkForRevealDecisions",
        "updateGameProgression" => true,
        "transitions" => array( "chooseDistanceDuringMoveReveal" => 12, "chooseTimeMachineDirection" => 41, "chooseAcceleratorDirection" => 9, "finalizeMove" => 49, "chooseIfYouWillUseBooster" => 32, "chooseWhetherToHyperdrive" => 45, "chooseCrewmembersToTake" => 57, "chooseCrewmemberToAirlock" => 63, "executingMove" => 70 )
    ),

    39 => array(
    		"name" => "allCrashSitesOccupiedChooseSpacePreTurn",
    		"description" => clienttranslate('${actplayer} is choosing where to be placed because all Crash Sites are occupied.'),
    		"descriptionmyturn" => clienttranslate('You must choose a space to place your ${saucerColor} Saucer that is not in the row or column of a Crewmember.'),
    		"type" => "activeplayer",
        'args' => 'argGetAllCrashSitesOccupiedDetails',
        "possibleactions" => array( "chooseSaucerSpace" ),
    		"transitions" => array( "saucerTurnStart" => 27 )
    ),

    40 => array(
        "name" => "chooseCrashSiteRegenerationGateway",
        "description" => clienttranslate('${saucerColor} is using their Regeneration Gateway.'),
        "descriptionmyturn" => clienttranslate('You must choose where to place your Saucer with your Regeneration Gateway.'),
        "type" => "activeplayer",
        'args' => 'argGetAllUnoccupiedCrashSites',
        "possibleactions" => array( "clickCrashSite", "chooseUpgradeSpace" ),
        "transitions" => array( "saucerTurnStart" => 27, "endSaucerTurnCleanUp" => 50, "endRoundCleanUp" => 5, "chooseWhichSaucerGoesFirst" => 26, "beginTurn" => 44  )
    ),

    41 => array(
        "name" => "chooseTimeMachineDirection",
        "description" => clienttranslate('${saucerColor} is choosing their direction.'),
        "descriptionmyturn" => clienttranslate('You can choose any Direction for your move.'),
        "type" => "activeplayer",
        'args' => 'argGetDirectionHighlights',
        "possibleactions" => array( "clickDirection", "chooseUpgradeSpace" ),
        "transitions" => array( "checkForRevealDecisions" => 38, "endSaucerTurnCleanUp" => 50 )
    ),

    42 => array(
        "name" => "askWhichStartOfTurnUpgradeToUse",
        "description" => clienttranslate('${actplayer} is deciding if they will use an Upgrade.'),
        "descriptionmyturn" => clienttranslate('You must decide if you will activate an Upgrade.'),
        "type" => "activeplayer",
        'args' => 'argGetStartOfTurnUpgradesToActivate',
        "possibleactions" => array( "skipActivateUpgrade", "activateUpgrade" ),
        "transitions" => array( "checkForRevealDecisions" => 38, "chooseBlastOffThrusterSpace" => 60, "chooseSaucerPulseCannon" => 69, "endSaucerTurnCleanUp" => 50  )
    ),

    43 => array(
    		"name" => "askPreTurnToPlaceCrashedSaucer",
    		"description" => clienttranslate('${actplayer} is placing their Saucer.'),
    		"descriptionmyturn" => clienttranslate('You must place your Saucer.'),
    		"type" => "activeplayer",
        'args' => 'argGetSaucerToPlaceButton',
        "possibleactions" => array( "clickSaucer" ),
    		"transitions" => array(  "allCrashSitesOccupiedChooseSpacePreTurn" => 39, "beginTurn" => 44, "endSaucerTurnCleanUp" => 50 )
    ),

    44 => array(
    		"name" => "beginTurn",
    		"description" => clienttranslate('${saucerColorText} is beginning their turn.'),
    		"descriptionmyturn" => clienttranslate('You must begin your turn for ${saucerColorText}.'),
    		"type" => "activeplayer",
        "action" => "beginTurn",
        'args' => 'argGetSaucerMoveCardInfo',
        "possibleactions" => array( "clickBegin" ),
    		"transitions" => array(  "checkStartOfTurnUpgrades" => 24, "endSaucerTurnCleanUp" => 50)
    ),

    45 => array(
        "name" => "chooseWhetherToHyperdrive",
        "description" => clienttranslate('${actplayer} deciding if they will use their Hyperdrive.'),
        "descriptionmyturn" => clienttranslate('You must choose whether you will use Hyperdrive to double your movement.'),
        "type" => "activeplayer",
        'args' => 'argGetHyperdriveHighlights',
        "possibleactions" => array( "clickDirection" ),
        "transitions" => array( "checkForRevealDecisions" => 38, "endSaucerTurnCleanUp" => 50 )
    ),

    49 => array(
    		"name" => "finalizeMove",
    		"description" => clienttranslate('${actplayer} is confirming their move for ${saucerColor}.'),
    		"descriptionmyturn" => clienttranslate('You must confirm or undo your move for ${saucerColor}.'),
    		"type" => "activeplayer",
        'args' => 'argGetSaucerColor',
        "possibleactions" => array( "undoMove" ),
    		"transitions" => array(  "endSaucerTurnCleanUp" => 50, "beginTurn" => 44, "checkStartOfTurnUpgrades" => 24 )
    ),

    50 => array(
        "name" => "endSaucerTurnCleanUp",
        "description" => clienttranslate('Checking for end of turn tasks...'),
        "type" => "game",
        "action" => "endSaucerTurnCleanUp",
        "updateGameProgression" => true,
        "transitions" => array( "endSaucerTurnCleanUp" => 50, "crashPenaltyAskWhichToGiveAway" => 51, "crashPenaltyAskWhichToSteal" => 52, "placeCrewmemberChooseCrewmember" => 10, "endSaucerTurn" => 36, "askWhichEndOfTurnUpgradeToUse" => 53, "askWhichUpgradeToPlay" => 58 )
    ),

    51 => array(
    		"name" => "crashPenaltyAskWhichToGiveAway",
    		"description" => clienttranslate('${saucerWhoCrashedText} is giving away a Crewmember.'),
    		"descriptionmyturn" => clienttranslate('Because ${saucerWhoCrashedText} crashed, choose which Crewmember you will give away and the Saucer who will get it.'),
    		"type" => "activeplayer",
        'args' => 'argGetGiveAwayCrewmembers',
        "possibleactions" => array( "clickCrewmember" ),
    		"transitions" => array(  "endSaucerTurnCleanUp" => 50, "endGame" => 99 )
    ),

    52 => array(
        "name" => "crashPenaltyAskWhichToSteal",
        "description" => clienttranslate('${saucerWhoIsStealingText} is choosing a reward for crashing ${saucerWhoCrashedText}.'),
        "descriptionmyturn" => clienttranslate('You must gain an Energy or choose a Crewmember to steal from ${saucerWhoCrashedText} because you crashed them.'),
        "type" => "activeplayer",
        'args' => 'argGetStealableCrewmembers',
        "possibleactions" => array( "clickCrewmember", "gainEnergy" ),
        "transitions" => array(  "endSaucerTurnCleanUp" => 50, "finalizeMove" => 49, "endGame" => 99 )
    ),

    53 => array(
        "name" => "askWhichEndOfTurnUpgradeToUse",
        "description" => clienttranslate('${saucerColor} is deciding if they will activate an Upgrade.'),
        "descriptionmyturn" => clienttranslate('You must decide if you will activate an Upgrade.'),
        "type" => "activeplayer",
        'args' => 'argGetEndOfTurnUpgradesToActivate',
        "possibleactions" => array( "activateUpgrade", "skipActivateUpgrade" ),
        "transitions" => array(  "endSaucerTurnCleanUp" => 50, "chooseSaucerWormholeGenerator" => 54, "chooseCrashSiteSaucerTeleporter" => 55, "chooseLandingLegsSpace" => 59, "chooseAfterburnerSpace" => 61, "chooseOrganicTriangulatorSpace" => 74, "chooseTractorBeamCrewmember" => 62, "chooseDistressSignalerTakeCrewmember" => 64, "chooseSaucerPulseCannon" => 69, "chooseTileRotationQuakeMaker" => 72 )
    ),

    54 => array(
        "name" => "chooseSaucerWormholeGenerator",
        "description" => clienttranslate('${actplayer} is generating a wormhole.'),
        "descriptionmyturn" => clienttranslate('You must choose a Saucer to swap places with.'),
        "type" => "activeplayer",
        'args' => 'argGetOtherUncrashedSaucers',
        "possibleactions" => array( "chooseSaucer", "skipActivateUpgrade" ),
        "transitions" => array(  "endSaucerTurnCleanUp" => 50 )
    ),

    55 => array(
        "name" => "chooseCrashSiteSaucerTeleporter",
        "description" => clienttranslate('${saucerColor} is teleporting.'),
        "descriptionmyturn" => clienttranslate('Choose a teleportation destination for ${saucerColor}.'),
        "type" => "activeplayer",
        'args' => 'argGetAllUnoccupiedCrashSites',
        "possibleactions" => array( "chooseSaucer", "skipActivateUpgrade", "chooseUpgradeSpace" ),
        "transitions" => array(  "endSaucerTurnCleanUp" => 50 )
    ),

    56 => array(
        "name" => "chooseCrewmembersToPass",
        "description" => clienttranslate('${saucerColorGiving} is passing Crewmembers to ${saucerColorReceiving}.'),
        "descriptionmyturn" => clienttranslate('Choose which Crewmembers ${saucerColorGiving} will pass to ${saucerColorReceiving}.'),
        "type" => "activeplayer",
        'args' => 'argGetPassableCrewmembers',
        "possibleactions" => array( "chooseSaucer", "skipActivateUpgrade" ),
        "transitions" => array(  "endSaucerTurnCleanUp" => 50, "chooseCrewmembersToPass" => 56, "chooseCrewmembersToTake" => 57, "finalizeMove" => 49, "endGame" => 99 )
    ),

    57 => array(
        "name" => "chooseCrewmembersToTake",
        "description" => clienttranslate('${saucerColorGiving} is passing Crewmembers to ${saucerColorReceiving}.'),
        "descriptionmyturn" => clienttranslate('Choose which Crewmembers ${saucerColorGiving} will pass to ${saucerColorReceiving}.'),
        "type" => "activeplayer",
        'args' => 'argGetTakeableCrewmembers',
        "possibleactions" => array( "chooseSaucer", "skipActivateUpgrade" ),
        "transitions" => array(  "endSaucerTurnCleanUp" => 50, "chooseCrewmembersToPass" => 56, "chooseCrewmembersToTake" => 57, "finalizeMove" => 49, "endGame" => 99 )
    ),

    58 => array(
        "name" => "askWhichUpgradeToPlay",
        "description" => clienttranslate('${saucerColor} is choosing a Saucer Upgrade to play.'),
        "descriptionmyturn" => clienttranslate('Choose an Upgrade to play.'),
        "type" => "activeplayer",
        'args' => 'argGetUpgradesToPlay',
        "possibleactions" => array( "chooseUpgrade" ),
        "transitions" => array(  "endSaucerTurnCleanUp" => 50, "checkStartOfTurnUpgrades" => 24 )
    ),

    59 => array(
        "name" => "chooseLandingLegsSpace",
        "description" => clienttranslate('${saucerColor} is choosing a space for ${upgradeName}.'),
        "descriptionmyturn" => clienttranslate('Choose a space to move to for ${upgradeName}.'),
        "type" => "activeplayer",
        'args' => 'argGetLandingLegSpaces',
        "possibleactions" => array( "chooseUpgradeSpace", "skipActivateUpgrade" ),
        "transitions" => array(  "endSaucerTurnCleanUp" => 50, "finalizeMove" => 49, "chooseAcceleratorDirection" => 9, "executingMove" => 70, "chooseCrewmembersToPass" => 56, "chooseCrewmembersToTake" => 57  )
    ),

    60 => array(
        "name" => "chooseBlastOffThrusterSpace",
        "description" => clienttranslate('${saucerColor} is choosing a space for ${upgradeName}.'),
        "descriptionmyturn" => clienttranslate('Choose a space to move to for ${upgradeName}.'),
        "type" => "activeplayer",
        'args' => 'argGetBlastOffThrustersSpaces',
        "possibleactions" => array( "chooseUpgradeSpace", "skipActivateUpgrade" ),
        "transitions" => array(  "endSaucerTurnCleanUp" => 50, "finalizeMove" => 49, "chooseAcceleratorDirection" => 9, "checkForRevealDecisions" => 38, "executingMove" => 70, "checkStartOfTurnUpgrades" => 24)
    ),

    61 => array(
        "name" => "chooseAfterburnerSpace",
        "description" => clienttranslate('${saucerColor} is choosing a space for ${upgradeName}.'),
        "descriptionmyturn" => clienttranslate('Choose a space to move to for ${upgradeName}.'),
        "type" => "activeplayer",
        'args' => 'argGetAfterburnerSpaces',
        "possibleactions" => array( "chooseUpgradeSpace", "skipActivateUpgrade" ),
        "transitions" => array(  "endSaucerTurnCleanUp" => 50, "finalizeMove" => 49, "chooseAcceleratorDirection" => 9, "checkForRevealDecisions" => 38, "chooseCrewmembersToPass" => 56, "chooseCrewmembersToTake" => 57 )
    ),

    62 => array(
        "name" => "chooseTractorBeamCrewmember",
        "description" => clienttranslate('${saucerColor} is using their ${upgradeName}.'),
        "descriptionmyturn" => clienttranslate('Choose 1 Crewmember to pick up.'),
        "type" => "activeplayer",
        'args' => 'argGetTractorBeamCrewmembers',
        "possibleactions" => array( "chooseUpgradeSpace", "skipActivateUpgrade" ),
        "updateGameProgression" => true,
        "transitions" => array(  "endSaucerTurnCleanUp" => 50, "finalizeMove" => 49, "chooseAcceleratorDirection" => 9, "checkForRevealDecisions" => 38, "endGame" => 99 )
    ),

    63 => array(
        "name" => "chooseCrewmemberToAirlock",
        "description" => clienttranslate('${saucerColor} is using their ${upgradeName}.'),
        "descriptionmyturn" => clienttranslate('Airlock: You may exchange the ${crewmemberTakenColor} ${crewmemberTakenTypeString} you just picked up for one of these on the board if you wish.'),
        "type" => "activeplayer",
        'args' => 'argGetAirlockCrewmembers',
        "possibleactions" => array( "chooseUpgradeSpace", "skipActivateUpgrade" ),
        "updateGameProgression" => true,
        "transitions" => array(  "endSaucerTurnCleanUp" => 50, "finalizeMove" => 49, "chooseAcceleratorDirection" => 9, "checkForRevealDecisions" => 38, "askWhichEndOfTurnUpgradeToUse" => 53, "endGame" => 99 )
    ),

    64 => array(
        "name" => "chooseDistressSignalerTakeCrewmember",
        "description" => clienttranslate('${saucerColor} is using their ${upgradeName}.'),
        "descriptionmyturn" => clienttranslate('Distress Signaler: You may take one of these Crewmembers in exchange for one of yours of the same type.'),
        "type" => "activeplayer",
        'args' => 'argGetDistressSignalerTakeCrewmembers',
        "possibleactions" => array( "chooseUpgradeSpace", "skipActivateUpgrade" ),
        "transitions" => array(  "endSaucerTurnCleanUp" => 50, "finalizeMove" => 49, "chooseAcceleratorDirection" => 9, "checkForRevealDecisions" => 38, "chooseDistressSignalerGiveCrewmember" => 65 )
    ),

    65 => array(
        "name" => "chooseDistressSignalerGiveCrewmember",
        "description" => clienttranslate('${saucerColor} is using their ${upgradeName}.'),
        "descriptionmyturn" => clienttranslate('Distress Signaler: You must exchange the ${crewmemberTakenColor} ${crewmemberTakenTypeString} you just picked up for one of these.'),
        "type" => "activeplayer",
        'args' => 'argGetDistressSignalerGiveCrewmembers',
        "possibleactions" => array( "chooseUpgradeSpace", "skipActivateUpgrade" ),
        "transitions" => array(  "endSaucerTurnCleanUp" => 50, "finalizeMove" => 49, "chooseAcceleratorDirection" => 9, "checkForRevealDecisions" => 38 )
    ),
    
    66 => array(
        "name" => "chooseAcceleratorDistance",
        "description" => clienttranslate('${saucerColor} is deciding whether they will use their Acceleration Regulator.'),
        "descriptionmyturn" => clienttranslate('Acceleration Regulator: How far would you like to travel on this Accelerator?'),
        "type" => "activeplayer",
        'args' => 'argChooseAcceleratorDistance',
        "possibleactions" => array( "selectXValue" ),
        "transitions" => array(  "executingMove" => 70, "chooseIfYouWillUseBooster" => 32, "chooseAcceleratorDirection" => 9, "finalizeMove" => 49, "chooseCrewmembersToPass" => 56, "askToProximityMine" => 67, "endSaucerTurnCleanUp" => 50, "checkForRevealDecisions" => 38, "chooseCrewmembersToTake" => 57, "chooseCrewmemberToAirlock" => 63, "askToWasteAccelerate" => 71, "chooseAcceleratorDistance" => 66, "endGame" => 99 )
    ),

    67 => array(
        "name" => "askToProximityMine",
        "description" => clienttranslate('${saucerColor} is deciding whether they will use their Proximity Mines.'),
        "descriptionmyturn" => clienttranslate('Proximity Mines: Would you like to crash this Saucer or collide with them?'),
        "type" => "activeplayer",
        'args' => 'argAskToProximityMine',
        "possibleactions" => array( "choosePhaseShift" ),
        "transitions" => array(  "executingMove" => 70, "chooseIfYouWillUseBooster" => 32, "chooseAcceleratorDirection" => 9, "finalizeMove" => 49, "chooseCrewmembersToPass" => 56, "askToProximityMine" => 67, "endSaucerTurnCleanUp" => 50, "checkForRevealDecisions" => 38, "chooseCrewmembersToTake" => 57, "chooseCrewmemberToAirlock" => 63, "askToWasteAccelerate" => 71, "chooseAcceleratorDistance" => 66, "endGame" => 99 )
    ),

    68 => array(
        "name" => "askToRotationalStabilizer",
        "description" => clienttranslate('${saucerColor} is choosing the turn direction.'),
        "descriptionmyturn" => clienttranslate('Rotational Stabilizer: Choose the turn order.'),
        "type" => "activeplayer",
        'args' => 'argAskToRotationalStabilizer',
        "possibleactions" => array( "chooseTurnDirection" ),
        "transitions" => array(  "playerTurnStart" => 29, "setActivePlayerToProbePlayer" => 73, "endSaucerTurnCleanUp" => 50 )
    ),

    69 => array(
        "name" => "chooseSaucerPulseCannon",
        "description" => clienttranslate('${actplayer} is generating a wormhole.'),
        "descriptionmyturn" => clienttranslate('Pulse Cannon: Which Saucer will you push?'),
        "type" => "activeplayer",
        'args' => 'argGetPulseCannonSaucers',
        "possibleactions" => array( "chooseSaucer", "skipActivateUpgrade" ),
        "transitions" => array(  "endSaucerTurnCleanUp" => 50, "executingMove" => 70, "checkStartOfTurnUpgrades" => 24, "checkForRevealDecisions" => 38, "askWhichEndOfTurnUpgradeToUse" => 53 )
    ),

    70 => array(
        "name" => "executingMove",
        "description" => clienttranslate('executingMove state.'),
        "descriptionmyturn" => clienttranslate('executingMove state'),
        "type" => "game",
        "action" => "executeMove",
        "possibleactions" => array( "chooseUpgradeSpace", "skipActivateUpgrade" ),
        "updateGameProgression" => true,
        "transitions" => array(  "chooseIfYouWillUseBooster" => 32, "chooseAcceleratorDirection" => 9, "finalizeMove" => 49, "chooseCrewmembersToPass" => 56, "askToProximityMine" => 67, "endSaucerTurnCleanUp" => 50, "checkForRevealDecisions" => 38, "chooseCrewmembersToTake" => 57, "chooseCrewmemberToAirlock" => 63, "askToWasteAccelerate" => 71, "chooseAcceleratorDistance" => 66, "executingMove" => 70, "endGame" => 99 )
    ),

    71 => array(
        "name" => "askToWasteAccelerate",
        "description" => clienttranslate('${saucerColor} is deciding whether they will use their Waste Accelerator.'),
        "descriptionmyturn" => clienttranslate('Waste Accelerator: Would you like to use your Waste Accelerator here?'),
        "type" => "activeplayer",
        'args' => 'argAskToWasteAccelerate',
        "possibleactions" => array( "chooseWasteAccelerator", "declineWasteAccelerator" ),
        "transitions" => array(  "executingMove" => 70, "chooseAcceleratorDirection" => 9, "askToWasteAccelerate" => 71, "chooseAcceleratorDistance" => 66, "endSaucerTurnCleanUp" => 50 )
    ),

    72 => array(
        "name" => "chooseTileRotationQuakeMaker",
        "description" => clienttranslate('${actplayer} is rotating a tile.'),
        "descriptionmyturn" => clienttranslate('You may rotate a tile.'),
        "type" => "activeplayer",
        "possibleactions" => array( "rotateTile", "skipActivateUpgrade" ),
        "transitions" => array(  "endSaucerTurnCleanUp" => 50 )
    ),

    73 => array(
        "name" => "setActivePlayerToProbePlayer",
        "description" => clienttranslate('Setting turn order...'),
        "type" => "game",
        "action" => "executeSetActivePlayerToProbePlayer",
        "updateGameProgression" => false,
        "transitions" => array( "playerTurnStart" => 29, "endSaucerTurnCleanUp" => 50 )
    ),

    74 => array(
        "name" => "chooseOrganicTriangulatorSpace",
        "description" => clienttranslate('${saucerColor} is choosing a space for ${upgradeName}.'),
        "descriptionmyturn" => clienttranslate('Choose a space to move to for ${upgradeName}.'),
        "type" => "activeplayer",
        'args' => 'argGetOrganicTriangulatorSpaces',
        "possibleactions" => array( "chooseUpgradeSpace", "skipActivateUpgrade" ),
        "transitions" => array(  "endSaucerTurnCleanUp" => 50, "finalizeMove" => 49, "chooseAcceleratorDirection" => 9, "checkForRevealDecisions" => 38, "chooseCrewmembersToPass" => 56, "chooseCrewmembersToTake" => 57, "askWhichEndOfTurnUpgradeToUse" => 53 )
    ),





/*
    Examples:

    2 => array(
        "name" => "nextPlayer",
        "description" => '',
        "type" => "game",
        "action" => "stNextPlayer",
        "updateGameProgression" => true,
        "transitions" => array( "endGame" => 99, "nextPlayer" => 10 )
    ),

    10 => array(
        "name" => "playerTurn",
        "description" => clienttranslate('${actplayer} must play a card or pass'),
        "descriptionmyturn" => clienttranslate('You must play a card or pass'),
        "type" => "activeplayer",
        "possibleactions" => array( "playCard", "pass" ),
        "transitions" => array( "playCard" => 2, "pass" => 2 )
    ),

*/

    // Zombie pass state (zombie players are automatically managed here)
    98 => array(
        "name" => "zombiePass",
        "description" => clienttranslate('This is a disconnected player.'),
        "type" => "game",
        "action" => "zombiePass",
        "updateGameProgression" => false,
        "transitions" => array( "zigChosen" => 2, "startOver" => 2, "allMovesChosen" => 25 )
    ),

    // Final state.
    // Please do not modify (and do not overload action/args methods).
    99 => array(
        "name" => "gameEnd",
        "description" => clienttranslate("End of game"),
        "type" => "manager",
        "action" => "stGameEnd",
        "args" => "argGameEnd"
    )

);
