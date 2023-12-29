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
        "transitions" => array( "" => 21 )
    ),

    // Note: ID=2 => your first state
    // PLAN PHASE (simultaneous)

    2 => array(
    		"name" => "chooseMoveCard",
    		"description" => clienttranslate('Everyone is choosing their Move.'),
    		"descriptionmyturn" => clienttranslate('${you} must choose a Move card.'),
    		"type" => "multipleactiveplayer",
        'args' => 'argGetAllPlayerSaucerMoves',
    		"possibleactions" => array( "clickDistance", "undoChooseMoveCard", "confirmMove" ),
    		"transitions" => array( "zigChosen" => 2, "startOver" => 2, "directionsChosen" => 6 )
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
    		"possibleactions" => array( "askToUseZig", "useSkateboard", "askToReplaceGarment" ),
    		"transitions" => array( "askUseZag" => 14, "askUseSkateboard" => 9, "endTurn" => 8, "askToReplaceGarment" => 10, "discardTrapCards" => 15, "askToRespawn" => 16, "askStealOrDraw" => 17, "askWhichGarmentToDiscard" => 18, "allTrappersDone" => 7, "endGame" => 99 )
    ),

    // END ROUND PHASE (game)

    5 => array(
        "name" => "endRoundPhase",
        "description" => "",
        "type" => "game",
        "action" => "endRoundCleanup",
        "updateGameProgression" => true,
        "transitions" => array( "newRound" => 21 )
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

    // END ONE PLAYER'S MOVE TURN
    8 => array(
        "name" => "endMoveTurn",
        "description" => "",
        "type" => "game",
        "action" => "movementTurnCleanup",
        "updateGameProgression" => false,
        "transitions" => array( "nextMovementTurn" => 4, "endRound" => 5, "chooseOstrich" => 11, "chooseXValue" => 12, "askTrapBasic" => 19 )
    ),

    // PLAYER HIT SKATEBOARD SO WE NEED TO ASK WHICH DIRECTION THEY WANT TO TRAVEL
    9 => array(
    		"name" => "askUseSkateboard",
    		"description" => clienttranslate('${actplayer} is choosing where they will ride their skateboard.'),
    		"descriptionmyturn" => clienttranslate('${you} must choose the direction you will travel on the skateboard.'),
    		"type" => "activeplayer",
    		"possibleactions" => array( "executeSkateboardMove" ),
    		"transitions" => array( "askUseZag" => 14, "askUseSkateboard" => 9, "endTurn" => 8, "askToReplaceGarment" => 10, "discardTrapCards" => 15, "askStealOrDraw" => 17, "askWhichGarmentToDiscard" => 18, "askToRespawn" => 16, "endGame" => 99 )
    ),

    10 => array(
    		"name" => "replaceGarmentChooseGarment",
    		"description" => "",
    		"descriptionmyturn" => "",
    		"type" => "activeplayer",
        'args' => 'argGetGarmentsValidForRespawn',
    		"possibleactions" => array( "replaceGarmentClick" ),
    		"transitions" => array( "replaceGarmentChooseGarment" => 13, "endTurn" => 8, "askToReplaceGarment" => 10 )
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
    		"name" => "chooseXValue",
    		"description" => clienttranslate('${actplayer} is revealing their Zig.'),
    		"descriptionmyturn" => clienttranslate('${you} must choose the distance you want to travel.'),
    		"type" => "activeplayer",
    		"possibleactions" => array( "selectXValue" ),
    		"transitions" => array( "askTrapBasic" => 19, "nextMovementTurn" => 4, "endTurn" => 8, "allTrappersDone" => 7 )
    ),

    13 => array(
    		"name" => "replaceGarmentChooseSpace",
    		"description" => clienttranslate('${actplayer} must choose the space where the new garment will go.'),
    		"descriptionmyturn" => clienttranslate('${you} must choose the space where the new garment will go.'),
    		"type" => "activeplayer",
        'args' => 'argGetValidGarmentSpawnSpaces',
    		"possibleactions" => array( "spaceClick" ),
    		"transitions" => array( "nextMovementTurn" => 4, "endTurn" => 8, "askToReplaceGarment" => 10 )
    ),

    14 => array(
    		"name" => "askUseZag",
    		"description" => clienttranslate('${actplayer} is deciding whether they will zag.'),
    		"descriptionmyturn" => clienttranslate('Would you like to zag?'),
    		"type" => "activeplayer",
    		"possibleactions" => array( "answerZagQuestion" ),
    		"transitions" => array( "askTrapBasic" => 19, "executeMove" => 4, "endTurn" => 8, "askUseSkateboard" => 9, "askToReplaceGarment" => 10, "discardTrapCards" => 15, "askToRespawn" => 16, "askStealOrDraw" => 17, "askWhichGarmentToDiscard" => 18, "endGame" => 99 )
    ),

    15 => array(
    		"name" => "discardTrapCards",
    		"description" => clienttranslate('Players are discarding Trap Cards.'),
    		"descriptionmyturn" => clienttranslate('${you} must discard down to 1 Trap Card.'),
    		"type" => "multipleactiveplayer",
    		"possibleactions" => array( "discardTrapCard" ),
    		"transitions" => array( "endTurn" => 8, "askToReplaceGarment" => 10, "discardTrapCards" => 15 )
    ),

    16 => array(
    		"name" => "askToRespawn",
    		"description" => clienttranslate('${actplayer} is climbing back up.'),
    		"descriptionmyturn" => clienttranslate('${you} should get this ostrich back in the action.'),
    		"type" => "activeplayer",
    		"possibleactions" => array( "answerRespawn" ),
    		"transitions" => array( "endTurn" => 8, "askToReplaceGarment" => 10, "discardTrapCards" => 15, "askToRespawn" => 16, "nextMovementTurn" => 4, "askStealOrDraw" => 17 )
    ),

    17 => array(
    		"name" => "askStealOrDraw",
    		"description" => clienttranslate('${actplayer} is claiming their cliff-pushing reward.'),
    		"descriptionmyturn" => clienttranslate('${you} can draw 2 Zigs or steal an off-colored garment if they have one.'),
    		"type" => "activeplayer",
        'args' => 'argStealableGarments',
    		"possibleactions" => array( "clickDraw2Zigs", "stealGarmentClick" ),
    		"transitions" => array( "endTurn" => 8, "askToReplaceGarment" => 10, "discardTrapCards" => 15, "askToRespawn" => 16, "askStealOrDraw" => 17, "chooseGarmentToSteal" => 23, "endTurn" => 8 )
    ),

    18 => array(
    		"name" => "askWhichGarmentToDiscard",
    		"description" => clienttranslate('${actplayer} is sadly deciding which garment to discard.'),
    		"descriptionmyturn" => clienttranslate('${you} must choose an off-colored garment to discard.'),
    		"type" => "activeplayer",
        'args' => 'argDiscardableGarments',
    		"possibleactions" => array( "discardGarmentClick" ),
    		"transitions" => array( "endTurn" => 8, "askToReplaceGarment" => 10, "discardTrapCards" => 15, "endTurn" => 8, "askToRespawn" => 16, "askWhichGarmentToDiscard" => 18 )
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

    21 => array(
        "name" => "chooseFirstState",
        "description" => "",
        "type" => "game",
        "action" => "chooseFirstState",
        "updateGameProgression" => true,
        "transitions" => array( "claimZag" => 20, "transitionToChooseZig" => 22, "chooseZig" => 2 )
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
    		"transitions" => array( "endTurn" => 8, "askToReplaceGarment" => 10, "discardTrapCards" => 15, "askToRespawn" => 16, "askStealOrDraw" => 17, "endTurn" => 8 )
    ),

    30 => array(
    		"name" => "chooseMoveCardDirection",
    		"description" => clienttranslate('${actplayer} is choosing which garment they will steal.'),
    		"descriptionmyturn" => clienttranslate('${you} must choose which off-colored garment to steal.'),
    		"type" => "activeplayer",
        'args' => 'argStealableGarments',
        "possibleactions" => array( "stealGarmentClick" ),
    		"transitions" => array( "endTurn" => 8, "askToReplaceGarment" => 10, "discardTrapCards" => 15, "askToRespawn" => 16, "askStealOrDraw" => 17, "endTurn" => 8 )
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
