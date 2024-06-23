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
    		"descriptionmyturn" => clienttranslate('${you} must choose your move DISTANCE and DIRECTION for this round.'),
    		"type" => "multipleactiveplayer",
        'args' => 'argGetAllPlayerSaucerMoves',
    		"possibleactions" => array( "clickMoveDirection", "clickDistance", "undoChooseMoveCard", "confirmMove", "clickUpgradeCardInHand" ),
    		"transitions" => array( "zigChosen" => 2, "startOver" => 2, "allMovesChosen" => 25 )
    ),

    // SET TRAPS PHASE (simultaneous timed)

    3 => array(
    		"name" => "setTrapsPhase",
    		"description" => clienttranslate("Other ostriches are setting traps. 😬"),
    		"descriptionmyturn" => clienttranslate('${you} choose an ostrich to target with your trap.'),
    		"type" => "multipleactiveplayer",
    		"possibleactions" => array( "setTrap" ),
    		"transitions" => array( "setTrap" => 3, "notTrap" => 3, "allTrappersDone" => 7 )
    ),

    // MOVE PHASE (individual)

    4 => array(
    		"name" => "executeMove",
    		"description" => clienttranslate('${actplayer} is moving.'),
    		"descriptionmyturn" => clienttranslate('${you} must execute your movement.'),
    		"type" => "activeplayer",
        'args' => 'argExecuteMove',
    		"possibleactions" => array( "askToUseZig", "useSkateboard", "placeCrewmemberChooseCrewmember" ),
    		"transitions" => array( "askUseZag" => 14, "askUseSkateboard" => 9, "endTurn" => 8, "placeCrewmemberChooseCrewmember" => 10, "discardTrapCards" => 15, "askToRespawn" => 16, "askStealOrDraw" => 17, "askWhichGarmentToDiscard" => 18, "allTrappersDone" => 7, "endGame" => 99 )
    ),

    // END ROUND PHASE (game)

    5 => array(
        "name" => "endRoundCleanup",
        "description" => "",
        "type" => "game",
        "action" => "endRoundCleanup",
        "updateGameProgression" => true,
        "transitions" => array( "newRound" => 2, "endRoundPlaceCrashedSaucer" => 37 )
    ),

    // END PLAN PHASE - ENTER TRAP PHASE

    6 => array(
        "name" => "endPlanPhase",
        "description" => "",
        "type" => "game",
        "action" => "startTrapPhase",
        "updateGameProgression" => false,
        "transitions" => array( "startSetTraps" => 3, "allTrappersDone" => 7 )
    ),

    // END TRAP PHASE - ENTER MOVE PHASE
    7 => array(
        "name" => "endTrapPhase",
        "description" => "",
        "type" => "game",
        "action" => "startMovePhase",
        "updateGameProgression" => false,
        "transitions" => array( "nextMovementTurn" => 4, "chooseOstrich" => 11, "chooseXValue" => 12, "askTrapBasic" => 19 )
    ),

    // PLAYER HIT ACCELERATOR SO WE NEED TO ASK WHICH DIRECTION THEY WANT TO TRAVEL
    9 => array(
    		"name" => "chooseAcceleratorDirection",
    		"description" => clienttranslate('${actplayer} is choosing their Accelerator direction.'),
    		"descriptionmyturn" => clienttranslate('${you} must choose the direction you will travel on the Accelerator.'),
        "type" => "activeplayer",
        'args' => 'argGetSaucerAcceleratorAndBoosterMoves',
        "possibleactions" => array( "clickAcceleratorDirection", "clickMoveDirection" ),
    		"transitions" => array( "chooseAcceleratorDirection" => 9, "chooseIfYouWillUseBooster" => 32, "playerTurnLocateCrewmembers" => 35, "endSaucerTurnCleanUp" => 50, "finalizeMove" => 49, "chooseCrewmembersToPass" => 56, "chooseCrewmembersToTake" => 57 )
    ),

    10 => array(
    		"name" => "placeCrewmemberChooseCrewmember",
    		"description" => clienttranslate('${saucerHighlighted} must choose a Crewmember to place.'),
    		"descriptionmyturn" => clienttranslate('You must choose a Crewmember to place.'),
    		"type" => "activeplayer",
        'args' => 'argGetLostCrewmembers',
    		"possibleactions" => array( "chooseLostCrewmember" ),
    		"transitions" => array( "replaceGarmentChooseGarment" => 13, "endTurn" => 8, "placeCrewmemberChooseCrewmember" => 10, "endSaucerTurnCleanUp" => 50 )
    ),

    11 => array(
    		"name" => "chooseOstrich",
    		"description" => clienttranslate('${actplayer} must choose which ostrich they will move.'),
    		"descriptionmyturn" => clienttranslate('${you} must choose which ostrich you will move.'),
    		"type" => "activeplayer",
    		"possibleactions" => array( "chooseOstrich" ),
    		"transitions" => array( "chooseXValue" => 12, "askTrapBasic" => 19, "executeMove" => 4 )
    ),

    12 => array(
    		"name" => "chooseDistanceDuringMoveReveal",
    		"description" => clienttranslate('${actplayer} is choosing their distance.'),
    		"descriptionmyturn" => clienttranslate('${you} must choose the distance you want to travel.'),
    		"type" => "activeplayer",
        'args' => 'argGetAllXMoves',
    		"possibleactions" => array( "selectXValue" ),
    		"transitions" => array( "checkForRevealDecisions" => 38 )
    ),

    13 => array(
    		"name" => "placeCrewmemberChooseSpace",
    		"description" => clienttranslate('${actplayer} must choose the space where the new garment will go.'),
    		"descriptionmyturn" => clienttranslate('${you} must choose the space where the new garment will go.'),
    		"type" => "activeplayer",
        'args' => 'argGetValidGarmentSpawnSpaces',
    		"possibleactions" => array( "chooseCrewmemberPlacingSpace" ),
    		"transitions" => array( "nextMovementTurn" => 4, "endTurn" => 8, "placeCrewmemberChooseCrewmember" => 10 )
    ),

    14 => array(
    		"name" => "askUseZag",
    		"description" => clienttranslate('${actplayer} is deciding whether they will zag.'),
    		"descriptionmyturn" => clienttranslate('Would you like to zag?'),
    		"type" => "activeplayer",
    		"possibleactions" => array( "answerZagQuestion" ),
    		"transitions" => array( "askTrapBasic" => 19, "executeMove" => 4, "endTurn" => 8, "askUseSkateboard" => 9, "placeCrewmemberChooseCrewmember" => 10, "discardTrapCards" => 15, "askToRespawn" => 16, "askStealOrDraw" => 17, "askWhichGarmentToDiscard" => 18, "endGame" => 99 )
    ),

    15 => array(
    		"name" => "discardTrapCards",
    		"description" => clienttranslate('Players are discarding Trap Cards.'),
    		"descriptionmyturn" => clienttranslate('${you} must discard down to 1 Trap Card.'),
    		"type" => "multipleactiveplayer",
    		"possibleactions" => array( "discardTrapCard" ),
    		"transitions" => array( "endTurn" => 8, "placeCrewmemberChooseCrewmember" => 10, "discardTrapCards" => 15 )
    ),

    16 => array(
    		"name" => "askToRespawn",
    		"description" => clienttranslate('${actplayer} is climbing back up.'),
    		"descriptionmyturn" => clienttranslate('${you} should get this ostrich back in the action.'),
    		"type" => "activeplayer",
    		"possibleactions" => array( "answerRespawn" ),
    		"transitions" => array( "endTurn" => 8, "placeCrewmemberChooseCrewmember" => 10, "discardTrapCards" => 15, "askToRespawn" => 16, "nextMovementTurn" => 4, "askStealOrDraw" => 17 )
    ),

    17 => array(
    		"name" => "askStealOrDraw",
    		"description" => clienttranslate('${actplayer} is claiming their cliff-pushing reward.'),
    		"descriptionmyturn" => clienttranslate('${you} can draw 2 Zigs or steal an off-colored garment if they have one.'),
    		"type" => "activeplayer",
        'args' => 'argStealableGarments',
    		"possibleactions" => array( "clickDraw2Zigs", "stealGarmentClick" ),
    		"transitions" => array( "endTurn" => 8, "placeCrewmemberChooseCrewmember" => 10, "discardTrapCards" => 15, "askToRespawn" => 16, "askStealOrDraw" => 17, "chooseGarmentToSteal" => 23, "endTurn" => 8 )
    ),

    18 => array(
    		"name" => "askWhichGarmentToDiscard",
    		"description" => clienttranslate('${actplayer} is sadly deciding which garment to discard.'),
    		"descriptionmyturn" => clienttranslate('${you} must choose an off-colored garment to discard.'),
    		"type" => "activeplayer",
        'args' => 'argDiscardableGarments',
    		"possibleactions" => array( "discardGarmentClick" ),
    		"transitions" => array( "endTurn" => 8, "placeCrewmemberChooseCrewmember" => 10, "discardTrapCards" => 15, "endTurn" => 8, "askToRespawn" => 16, "askWhichGarmentToDiscard" => 18 )
    ),

    19 => array(
    		"name" => "askTrapBasic",
    		"description" => clienttranslate('${actplayer} is dealing with a trap.'),
    		"descriptionmyturn" => clienttranslate('${you} have been trapped.'),
    		"type" => "activeplayer",
    		"possibleactions" => array( "executeTrap" ),
    		"transitions" => array( "askTrapBasic" => 19, "nextMovementTurn" => 4 )
    ),

    20 => array(
    		"name" => "claimZag",
    		"description" => clienttranslate('Other players are claiming Zags.'),
    		"descriptionmyturn" => clienttranslate('${you} may discard 3 matching Zigs to claim a Zag.'),
    		"type" => "multipleactiveplayer",
        'args' => 'argGetOstriches',
    		"possibleactions" => array( "selectZigs", "claimZag", "skipClaimZag", "hideTurnDirection" ),
    		"transitions" => array( "transitionToChooseZig" => 22, "claimZag" => 20, "chooseZig" => 2 )
    ),

    22 => array(
        "name" => "transitionToChooseZig",
        "description" => "",
        "type" => "game",
        "action" => "transitionToChooseZig",
        "updateGameProgression" => true,
        "transitions" => array( "chooseZig" => 2 )
    ),

    23 => array(
    		"name" => "askWhichGarmentToSteal",
    		"description" => clienttranslate('${actplayer} is choosing which garment they will steal.'),
    		"descriptionmyturn" => clienttranslate('${you} must choose which off-colored garment to steal.'),
    		"type" => "activeplayer",
        'args' => 'argStealableGarments',
        "possibleactions" => array( "stealGarmentClick" ),
    		"transitions" => array( "endTurn" => 8, "placeCrewmemberChooseCrewmember" => 10, "discardTrapCards" => 15, "askToRespawn" => 16, "askStealOrDraw" => 17, "endTurn" => 8 )
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
        "transitions" => array( "playerTurnStart" => 29 )
    ),

    26 => array(
    		"name" => "chooseWhichSaucerGoesFirst",
    		"description" => clienttranslate('${actplayer} is choosing which Saucer will go first.'),
    		"descriptionmyturn" => clienttranslate('${you} must choose which of your Saucers will go first.'),
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
        "updateGameProgression" => false,
        "transitions" => array( "chooseWhichSaucerGoesFirst" => 26, "saucerTurnStart" => 27 )
    ),

    31 => array(
    		"name" => "chooseDirectionAfterPlacement",
    		"description" => clienttranslate('${actplayer} is placing their Saucer.'),
    		"descriptionmyturn" => clienttranslate('${you} must choose the direction in which your Saucer will travel.'),
    		"type" => "activeplayer",
        "possibleactions" => array( "clickDirection" ),
    		"transitions" => array(  "saucerTurnStart" => 27 )
    ),

    32 => array(
    		"name" => "chooseIfYouWillUseBooster",
    		"description" => clienttranslate('${actplayer} is deciding if they will boost.'),
    		"descriptionmyturn" => clienttranslate('Would you like to use a Booster?'),
    		"type" => "activeplayer",
        'args' => 'argGetSaucerAcceleratorAndBoosterMoves',
        "possibleactions" => array( "clickUseBooster", "clickSkipBooster", "clickMoveDirection", "clickAcceleratorDirection" ),
    		"transitions" => array( "chooseBoosterDirection" => 33, "playerTurnLocateCrewmembers" => 35, "endSaucerTurnCleanUp" => 50, "chooseAcceleratorDirection" => 9, "finalizeMove" => 49, "chooseCrewmembersToPass" => 56, "chooseCrewmembersToTake" => 57, "chooseIfYouWillUseBooster" => 32 )
    ),

    34 => array(
    		"name" => "allCrashSitesOccupiedChooseSpaceEndRound",
    		"description" => clienttranslate('${actplayer} is choosing where to be placed because all Crash Sites are occupied.'),
    		"descriptionmyturn" => clienttranslate('${you} must choose a space to place your ${saucerColor} Saucer that is not in the row or column of a Crewmember.'),
    		"type" => "activeplayer",
        'args' => 'argGetAllCrashSitesOccupiedDetails',
        "possibleactions" => array( "chooseSaucerSpace" ),
    		"transitions" => array( "endRoundCleanUp" => 5 )
    ),

    35 => array(
    		"name" => "playerTurnLocateCrewmembers",
    		"description" => clienttranslate('${actplayer} is locating a crewmember.'),
    		"descriptionmyturn" => clienttranslate('${you} must choose a crewmember to locate.'),
    		"type" => "activeplayer",
        "possibleactions" => array( "clickCrewmemberToLocate" ),
    		"transitions" => array( "endSaucerTurnCleanUp" => 50 )
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
        "description" => clienttranslate('${actplayer} is placing a crashed Saucer.'),
    		"descriptionmyturn" => clienttranslate('${you} must choose a Saucer to place.'),
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
        "updateGameProgression" => false,
        "transitions" => array( "chooseDistanceDuringMoveReveal" => 12, "chooseTimeMachineDirection" => 41, "chooseAcceleratorDirection" => 9, "finalizeMove" => 49, "chooseIfYouWillUseBooster" => 32, "chooseWhetherToHyperdrive" => 45, "chooseCrewmembersToTake" => 57, "chooseCrewmemberToAirlock" => 63 )
    ),

    39 => array(
    		"name" => "allCrashSitesOccupiedChooseSpacePreTurn",
    		"description" => clienttranslate('${actplayer} is choosing where to be placed because all Crash Sites are occupied.'),
    		"descriptionmyturn" => clienttranslate('${you} must choose a space to place your ${saucerColor} Saucer that is not in the row or column of a Crewmember.'),
    		"type" => "activeplayer",
        'args' => 'argGetAllCrashSitesOccupiedDetails',
        "possibleactions" => array( "chooseSaucerSpace" ),
    		"transitions" => array( "chooseDirectionAfterPlacement" => 31 )
    ),

    40 => array(
        "name" => "chooseCrashSiteRegenerationGateway",
        "description" => clienttranslate('${actplayer} is using their Regeneration Gateway.'),
        "descriptionmyturn" => clienttranslate('${you} must choose where to place your Saucer with your Regeneration Gateway.'),
        "type" => "activeplayer",
        'args' => 'argGetAllUnoccupiedCrashSites',
        "possibleactions" => array( "clickCrashSite" ),
        "transitions" => array( "saucerTurnStart" => 27, "endSaucerTurnCleanUp" => 50  )
    ),

    41 => array(
        "name" => "chooseTimeMachineDirection",
        "description" => clienttranslate('${actplayer} is using their Time Machine.'),
        "descriptionmyturn" => clienttranslate('${you} must choose a Direction for your move because of your Time Machine.'),
        "type" => "activeplayer",
        "possibleactions" => array( "clickDirection" ),
        "transitions" => array( "checkForRevealDecisions" => 38  )
    ),

    42 => array(
        "name" => "askWhichStartOfTurnUpgradeToUse",
        "description" => clienttranslate('${actplayer} is deciding if they will use an Upgrade.'),
        "descriptionmyturn" => clienttranslate('${you} must decide if you will use an Upgrade.'),
        "type" => "activeplayer",
        'args' => 'argGetStartOfTurnUpgradesToActivate',
        "possibleactions" => array( "skipActivateUpgrade", "activateUpgrade" ),
        "transitions" => array( "checkForRevealDecisions" => 38, "chooseBlastOffThrusterSpace" => 60  )
    ),

    43 => array(
    		"name" => "askPreTurnToPlaceCrashedSaucer",
    		"description" => clienttranslate('${actplayer} is placing their Saucer.'),
    		"descriptionmyturn" => clienttranslate('${you} must place your Saucer.'),
    		"type" => "activeplayer",
        'args' => 'argGetSaucerToPlaceButton',
        "possibleactions" => array( "clickSaucer" ),
    		"transitions" => array(  "chooseDirectionAfterPlacement" => 31, "allCrashSitesOccupiedChooseSpacePreTurn" => 39 )
    ),

    44 => array(
    		"name" => "beginTurn",
    		"description" => clienttranslate('${saucerColor} is beginning their turn.'),
    		"descriptionmyturn" => clienttranslate('You must begin your turn for ${saucerColor}.'),
    		"type" => "activeplayer",
        "action" => "beginTurn",
        'args' => 'argGetSaucerColor',
        "possibleactions" => array( "clickBegin" ),
    		"transitions" => array(  "checkStartOfTurnUpgrades" => 24 )
    ),

    45 => array(
        "name" => "chooseWhetherToHyperdrive",
        "description" => clienttranslate('${actplayer} deciding if they will use their Hyperdrive.'),
        "descriptionmyturn" => clienttranslate('${you} must choose whether you will use Hyperdrive to double your movement.'),
        "type" => "activeplayer",
        "possibleactions" => array( "clickDirection" ),
        "transitions" => array( "checkForRevealDecisions" => 38  )
    ),

    49 => array(
    		"name" => "finalizeMove",
    		"description" => clienttranslate('${actplayer} is confirming their move for ${saucerColor}.'),
    		"descriptionmyturn" => clienttranslate('You must confirm or undo your move for ${saucerColor}.'),
    		"type" => "activeplayer",
        'args' => 'argGetSaucerColor',
        "possibleactions" => array( "undoMove" ),
    		"transitions" => array(  "endSaucerTurnCleanUp" => 50, "beginTurn" => 44 )
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
    		"description" => clienttranslate('${actplayer} is giving away a Crewmember.'),
    		"descriptionmyturn" => clienttranslate('${you} must choose which Crewmember you will give to PLAYERNAME because you crashed.'),
    		"type" => "activeplayer",
        'args' => 'argGetGiveAwayCrewmembers',
        "possibleactions" => array( "clickCrewmember" ),
    		"transitions" => array(  "endSaucerTurnCleanUp" => 50 )
    ),

    52 => array(
        "name" => "crashPenaltyAskWhichToSteal",
        "description" => clienttranslate('${saucerWhoIsStealingText} is stealing a Crewmember from ${saucerWhoCrashedText}.'),
        "descriptionmyturn" => clienttranslate('You must gain an Energy or choose a Crewmember to steal from ${saucerWhoCrashedText} because you crashed them.'),
        "type" => "activeplayer",
        'args' => 'argGetStealableCrewmembers',
        "possibleactions" => array( "clickCrewmember", "gainEnergy" ),
        "transitions" => array(  "endSaucerTurnCleanUp" => 50 )
    ),

    53 => array(
        "name" => "askWhichEndOfTurnUpgradeToUse",
        "description" => clienttranslate('${actplayer} is deciding if they will activate an Upgrade.'),
        "descriptionmyturn" => clienttranslate('${you} must decide if you will activate an Upgrade.'),
        "type" => "activeplayer",
        'args' => 'argGetEndOfTurnUpgradesToActivate',
        "possibleactions" => array( "activateUpgrade", "skipActivateUpgrade" ),
        "transitions" => array(  "endSaucerTurnCleanUp" => 50, "chooseSaucerWormholeGenerator" => 54, "chooseCrashSiteSaucerTeleporter" => 55, "chooseLandingLegsSpace" => 59, "chooseAfterburnerSpace" => 61, "chooseTractorBeamCrewmember" => 62 )
    ),

    54 => array(
        "name" => "chooseSaucerWormholeGenerator",
        "description" => clienttranslate('${actplayer} is generating a wormhole.'),
        "descriptionmyturn" => clienttranslate('${you} must choose a Saucer to swap places with.'),
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
        "possibleactions" => array( "chooseSaucer", "skipActivateUpgrade" ),
        "transitions" => array(  "endSaucerTurnCleanUp" => 50 )
    ),

    56 => array(
        "name" => "chooseCrewmembersToPass",
        "description" => clienttranslate('${saucerColorGiving} is passing Crewmembers to ${saucerColorReceiving}.'),
        "descriptionmyturn" => clienttranslate('Choose which Crewmembers ${saucerColorGiving} will pass to ${saucerColorReceiving}.'),
        "type" => "activeplayer",
        'args' => 'argGetPassableCrewmembers',
        "possibleactions" => array( "chooseSaucer", "skipActivateUpgrade" ),
        "transitions" => array(  "endSaucerTurnCleanUp" => 50, "chooseCrewmembersToPass" => 56, "chooseCrewmembersToTake" => 57, "finalizeMove" => 49 )
    ),

    57 => array(
        "name" => "chooseCrewmembersToTake",
        "description" => clienttranslate('${saucerColorGiving} is passing Crewmembers to ${saucerColorReceiving}.'),
        "descriptionmyturn" => clienttranslate('Choose which Crewmembers ${saucerColorGiving} will pass to ${saucerColorReceiving}.'),
        "type" => "activeplayer",
        'args' => 'argGetTakeableCrewmembers',
        "possibleactions" => array( "chooseSaucer", "skipActivateUpgrade" ),
        "transitions" => array(  "endSaucerTurnCleanUp" => 50, "chooseCrewmembersToPass" => 56, "chooseCrewmembersToTake" => 57, "finalizeMove" => 49 )
    ),

    58 => array(
        "name" => "askWhichUpgradeToPlay",
        "description" => clienttranslate('${saucerColor} is choosing an Upgrade to play.'),
        "descriptionmyturn" => clienttranslate('Choose an Upgrade to play.'),
        "type" => "activeplayer",
        'args' => 'argGetUpgradesToPlay',
        "possibleactions" => array( "chooseUpgrade" ),
        "transitions" => array(  "endSaucerTurnCleanUp" => 50 )
    ),

    59 => array(
        "name" => "chooseLandingLegsSpace",
        "description" => clienttranslate('${saucerColor} is choosing a space for ${upgradeName}.'),
        "descriptionmyturn" => clienttranslate('Choose a space to move to for ${upgradeName}.'),
        "type" => "activeplayer",
        'args' => 'argGetLandingLegSpaces',
        "possibleactions" => array( "chooseUpgradeSpace", "skipActivateUpgrade" ),
        "transitions" => array(  "endSaucerTurnCleanUp" => 50, "finalizeMove" => 49, "chooseAcceleratorDirection" => 9 )
    ),

    60 => array(
        "name" => "chooseBlastOffThrusterSpace",
        "description" => clienttranslate('${saucerColor} is choosing a space for ${upgradeName}.'),
        "descriptionmyturn" => clienttranslate('Choose a space to move to for ${upgradeName}.'),
        "type" => "activeplayer",
        'args' => 'argGetBlastOffThrustersSpaces',
        "possibleactions" => array( "chooseUpgradeSpace", "skipActivateUpgrade" ),
        "transitions" => array(  "endSaucerTurnCleanUp" => 50, "finalizeMove" => 49, "chooseAcceleratorDirection" => 9, "checkForRevealDecisions" => 38 )
    ),

    61 => array(
        "name" => "chooseAfterburnerSpace",
        "description" => clienttranslate('${saucerColor} is choosing a space for ${upgradeName}.'),
        "descriptionmyturn" => clienttranslate('Choose a space to move to for ${upgradeName}.'),
        "type" => "activeplayer",
        'args' => 'argGetAfterburnerSpaces',
        "possibleactions" => array( "chooseUpgradeSpace", "skipActivateUpgrade" ),
        "transitions" => array(  "endSaucerTurnCleanUp" => 50, "finalizeMove" => 49, "chooseAcceleratorDirection" => 9, "checkForRevealDecisions" => 38 )
    ),

    62 => array(
        "name" => "chooseTractorBeamCrewmember",
        "description" => clienttranslate('${saucerColor} is using their ${upgradeName}.'),
        "descriptionmyturn" => clienttranslate('Choose a Crewmember to pick up.'),
        "type" => "activeplayer",
        'args' => 'argGetTractorBeamCrewmembers',
        "possibleactions" => array( "chooseUpgradeSpace", "skipActivateUpgrade" ),
        "transitions" => array(  "endSaucerTurnCleanUp" => 50, "finalizeMove" => 49, "chooseAcceleratorDirection" => 9, "checkForRevealDecisions" => 38 )
    ),

    63 => array(
        "name" => "chooseCrewmemberToAirlock",
        "description" => clienttranslate('${saucerColor} is using their ${upgradeName}.'),
        "descriptionmyturn" => clienttranslate('Airlock: You may exchange the ${crewmemberTakenColor} ${crewmemberTakenTypeString} you just picked up for one of these if you wish.'),
        "type" => "activeplayer",
        'args' => 'argGetAirlockCrewmembers',
        "possibleactions" => array( "chooseUpgradeSpace", "skipActivateUpgrade" ),
        "transitions" => array(  "endSaucerTurnCleanUp" => 50, "finalizeMove" => 49, "chooseAcceleratorDirection" => 9, "checkForRevealDecisions" => 38 )
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
        "descriptionmyturn" => clienttranslate('${you} must play a card or pass'),
        "type" => "activeplayer",
        "possibleactions" => array( "playCard", "pass" ),
        "transitions" => array( "playCard" => 2, "pass" => 2 )
    ),

*/

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
