<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * CrashAndGrab implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on https://boardgamearena.com.
 * See http://en.doc.boardgamearena.com/Studio for more information.
 * -----
 *
 * crashandgrab.action.php
 *
 * CrashAndGrab main action entry point
 *
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *
 * If you define a method "myAction" here, then you can call it from your javascript code with:
 * this.ajaxcall( "/crashandgrab/crashandgrab/myAction.html", ...)
 *
 */


  class action_crashandgrab extends APP_GameAction
  {
    // Constructor: please do not modify
   	public function __default()
  	{
  	    if( self::isArg( 'notifwindow') )
  	    {
            $this->view = "common_notifwindow";
  	        $this->viewArgs['table'] = self::getArg( "table", AT_posint, true );
  	    }
  	    else
  	    {
            $this->view = "crashandgrab_crashandgrab";
            self::trace( "Complete reinitialization of board game" );
      }
  	}

  	// TODO: defines your action entry points there


    /*

    Example:

    public function myAction()
    {
        self::setAjaxMode();

        // Retrieve arguments
        // Note: these arguments correspond to what has been sent through the javascript "ajaxcall" method
        $arg1 = self::getArg( "myArgument1", AT_posint, true );
        $arg2 = self::getArg( "myArgument2", AT_posint, true );

        // Then, call the appropriate method in your game logic, like "playCard" or "myAction"
        $this->game->myAction( $arg1, $arg2 );

        self::ajaxResponse( );
    }

    */

    // A player has chosen their Zig card.
    public function actClickedMoveCard()
    {
        self::setAjaxMode();
        $distance = self::getArg( "distance", AT_alphanum, true ); // 0, 1, 2
        $color = self::getArg( "color", AT_alphanum, true ); // ff0000, 0000ff, etc.

        $this->game->executeClickedMoveCard( $distance, $color );
        self::ajaxResponse( );
    }

    public function actClickUpgradeInHand()
    {
        self::setAjaxMode();
        $databaseId = self::getArg( "upgradeDatabaseId", AT_posint, true ); // 1, 2, 3

        $color = $this->game->getOstrichWhoseTurnItIs();

        $this->game->executeClickedUpgradeCardInHand( $databaseId, $color );
        self::ajaxResponse( );
    }

    public function actSkipActivateStartOfTurnUpgrade()
    {
        self::setAjaxMode();

        $this->game->executeSkipActivateStartOfTurnUpgrade();
        self::ajaxResponse( );
    }

    public function actSkipActivateEndOfTurnUpgrade()
    {
      self::setAjaxMode();

      $this->game->executeSkipActivateEndOfTurnUpgrade();
      self::ajaxResponse( );
    }

    public function actSkipActivateSpecificEndOfTurnUpgrade()
    {
      self::setAjaxMode();
      $collectorNumber = self::getArg( "collectorNumber", AT_posint, true ); // 1, 2, 3

      $this->game->executeSkipActivateSpecificEndOfTurnUpgrade($collectorNumber);
      self::ajaxResponse( );
    }

    public function actSkipActivateSpecificStartOfTurnUpgrade()
    {
      self::setAjaxMode();
      $collectorNumber = self::getArg( "collectorNumber", AT_posint, true ); // 1, 2, 3

      $this->game->executeSkipActivateSpecificStartOfTurnUpgrade($collectorNumber);
      self::ajaxResponse( );
    }

    public function actActivateUpgrade()
    {
        self::setAjaxMode();
        $collectorNumber = self::getArg( "collectorNumber", AT_posint, true ); // 1, 2, 3

        $this->game->executeActivateUpgrade( $collectorNumber );
        self::ajaxResponse( );
    }

    public function actClickedStartMove()
    {
        self::setAjaxMode();
        $this->game->executeStartMove();
        self::ajaxResponse( );
    }

    public function actWormholeSelectSaucer()
    {
        self::setAjaxMode();
        $saucerColor = self::getArg( "saucerColor", AT_alphanum, true ); // ff0000, 0000ff, etc.

        $this->game->executeWormholeSelectSaucer( $saucerColor );
        self::ajaxResponse( );
    }

    public function actExecuteChooseCrashSite()
    {
        self::setAjaxMode();
        $crashSiteNumber = self::getArg( "crashSiteNumber", AT_posint, true ); // 1, 2, 3

        $this->game->executeChooseCrashSite( $crashSiteNumber );
        self::ajaxResponse( );
    }

    public function actActivateProximityMines()
    {
        self::setAjaxMode();
        $saucerWhoCrashed = self::getArg( "saucerWhoCrashed", AT_alphanum, true ); // ff0000, 0000ff, etc.
        $this->game->executeActivateProximityMines($saucerWhoCrashed);
        self::ajaxResponse( );
    }

    public function actSkipProximityMines()
    {
        self::setAjaxMode();
        $this->game->executeSkipProximityMines();
        self::ajaxResponse( );
    }

    public function actActivatePhaseShifter()
    {
        self::setAjaxMode();
        $this->game->executeActivatePhaseShifter();
        self::ajaxResponse( );
    }

    public function actSkipPhaseShifter()
    {
        self::setAjaxMode();
        $this->game->executeSkipPhaseShifter();
        self::ajaxResponse( );
    }

    public function actActivateHyperdrive()
    {
        self::setAjaxMode();
        $this->game->executeActivateHyperdrive();
        self::ajaxResponse( );
    }

    public function actSkipHyperdrive()
    {
        self::setAjaxMode();
        $this->game->executeSkipHyperdrive();
        self::ajaxResponse( );
    }

    public function actSkipGiveAwayCrewmember()
    {
        self::setAjaxMode();
        $this->game->executeSkipGiveAwayCrewmember();
        self::ajaxResponse( );
    }

    public function actSkipStealCrewmember()
    {
        self::setAjaxMode();
        $saucerWhoCrashed = self::getArg( "saucerWhoCrashed", AT_alphanum, true ); // ff0000, 0000ff, etc.
        $this->game->executeSkipStealCrewmember($saucerWhoCrashed);
        self::ajaxResponse( );
    }

    public function actSkipPassCrewmember()
    {
        self::setAjaxMode();
        $this->game->executeSkipPassCrewmember();
        self::ajaxResponse( );
    }

    public function actSkipTakeCrewmember()
    {
        self::setAjaxMode();
        $this->game->executeSkipTakeCrewmember();
        self::ajaxResponse( );
    }

    public function actExecuteTractorBeamCrewmember()
    {
        self::setAjaxMode();
        $crewmemberType = self::getArg( "crewmemberType", AT_alphanum, true ); // scientist
        $crewmemberColor = self::getArg( "crewmemberColor", AT_alphanum, true ); // ff0000, 0000ff, etc.

        $this->game->executeTractorBeamCrewmember( $crewmemberType, $crewmemberColor);
        self::ajaxResponse( );
    }

    public function actExecuteDistressSignalerTakeCrewmember()
    {
        self::setAjaxMode();
        $crewmemberType = self::getArg( "crewmemberType", AT_alphanum, true ); // scientist
        $crewmemberColor = self::getArg( "crewmemberColor", AT_alphanum, true ); // ff0000, 0000ff, etc.

        $this->game->executeDistressSignalerTakeCrewmember( $crewmemberType, $crewmemberColor);
        self::ajaxResponse( );
    }

    public function actExecuteDistressSignalerGiveCrewmember()
    {
        self::setAjaxMode();
        $crewmemberType = self::getArg( "crewmemberType", AT_alphanum, true ); // scientist
        $crewmemberColor = self::getArg( "crewmemberColor", AT_alphanum, true ); // ff0000, 0000ff, etc.

        $this->game->executeDistressSignalerGiveCrewmember( $crewmemberType, $crewmemberColor);
        self::ajaxResponse( );
    }

    public function actExecuteAirlockCrewmember()
    {
        self::setAjaxMode();
        $crewmemberType = self::getArg( "crewmemberType", AT_alphanum, true ); // scientist
        $crewmemberColor = self::getArg( "crewmemberColor", AT_alphanum, true ); // ff0000, 0000ff, etc.

        $this->game->executeAirlockCrewmember( $crewmemberType, $crewmemberColor);
        self::ajaxResponse( );
    }

    public function actExecuteStealCrewmember()
    {
        self::setAjaxMode();
        $stolenType = self::getArg( "stolenType", AT_alphanum, true ); // scientist
        $stolenColor = self::getArg( "stolenColor", AT_alphanum, true ); // ff0000, 0000ff, etc.

        $this->game->executeStealCrewmember( $stolenType, $stolenColor, false, false );
        self::ajaxResponse( );
    }

    public function actExecutePassCrewmember()
    {
        self::setAjaxMode();
        $stolenType = self::getArg( "stolenType", AT_alphanum, true ); // scientist
        $stolenColor = self::getArg( "stolenColor", AT_alphanum, true ); // ff0000, 0000ff, etc.

        $this->game->executeStealCrewmember( $stolenType, $stolenColor, true, false );
        self::ajaxResponse( );
    }

    public function actExecuteTakeCrewmember()
    {
        self::setAjaxMode();
        $stolenType = self::getArg( "stolenType", AT_alphanum, true ); // scientist
        $stolenColor = self::getArg( "stolenColor", AT_alphanum, true ); // ff0000, 0000ff, etc.

        $this->game->executeStealCrewmember( $stolenType, $stolenColor, false, true );
        self::ajaxResponse( );
    }

    public function actExecuteGiveAwayCrewmember()
    {
        self::setAjaxMode();
        $stolenType = self::getArg( "stolenType", AT_alphanum, true ); // scientist
        $stolenColor = self::getArg( "stolenColor", AT_alphanum, true ); // ff0000, 0000ff, etc.
        $sauceToGiveToColor = self::getArg( "saucerColor", AT_alphanum, true ); // ff0000, 0000ff, etc.

        $this->game->executeGiveAwayCrewmember( $stolenType, $stolenColor, $sauceToGiveToColor );
        self::ajaxResponse( );
    }

    public function actExecuteEnergyRewardSelection()
    {
        self::setAjaxMode();
        $saucerWhoCrashed = self::getArg( "saucerWhoCrashed", AT_alphanum, true ); // ff0000, 0000ff, etc.

        $this->game->executeEnergyRewardSelection($saucerWhoCrashed);
        self::ajaxResponse( );
    }

    public function actClickedAcceleratorDirection()
    {
      self::setAjaxMode();

      $direction = self::getArg( "direction", AT_alphanum, true ); // asteroids

      //$this->game->executeStartAcceleratorOrBoosterMove($direction);
      $this->game->executeDirectionClick($direction);

      self::ajaxResponse( );

    }

    public function actClickedSaucerToPlace()
    {
        self::setAjaxMode();
        $colorAsHex = self::getArg( "colorAsHex", AT_alphanum, true ); // BLUE, RED

        $this->game->executeClickedSaucerToPlace( $colorAsHex );
        self::ajaxResponse( );
    }

    public function actClickedSaucerToGoFirst()
    {
        self::setAjaxMode();
        $colorhex = self::getArg( "colorHex", AT_alphanum, true ); // BLUE, RED

        $this->game->executeClickedSaucerToGoFirst( $colorhex );
        self::ajaxResponse( );
    }

    public function actClickedBeginTurn()
    {
        self::setAjaxMode();

        $this->game->executeClickedBeginTurn();
        self::ajaxResponse( );
    }

    public function actClickedUndoMove()
    {
        self::setAjaxMode();

        $this->game->executeClickedUndoMove();
        self::ajaxResponse( );
    }

    public function actClickedFinalizeMove()
    {
        self::setAjaxMode();

        $this->game->executeClickedFinalizeMove();
        self::ajaxResponse( );
    }

    public function actClickedConfirmMove()
    {
        self::setAjaxMode();
        $saucer1Color = self::getArg( "saucer1Color", AT_alphanum, true ); // 0, 1, 2
        $saucer1Distance = self::getArg( "saucer1Distance", AT_alphanum, true ); // ff0000, 0000ff, etc.
        $saucer1Direction = self::getArg( "saucer1Direction", AT_alphanum, true ); // asteroids
        $saucer2Color = self::getArg( "saucer2Color", AT_alphanum, true ); // ff0000, 0000ff, etc.
        $saucer2Distance = self::getArg( "saucer2Distance", AT_alphanum, true ); // 0, 1, 2
        $saucer2Direction = self::getArg( "saucer2Direction", AT_alphanum, true ); // asteroids

        $this->game->executeClickedConfirmMove( $saucer1Color, $saucer1Distance, $saucer1Direction, $saucer2Color, $saucer2Distance, $saucer2Direction );
        self::ajaxResponse( );
    }

    // A player has chosen their Zig Direction.
    public function actChooseZigDirection()
    {
        self::setAjaxMode();
        $direction = self::getArg( "direction", AT_alphanum, true ); // CACTUS, RIVER, etc.

        $this->game->executeChooseZigDirection( $direction );
        self::ajaxResponse( );
    }

    public function actChooseOstrichToGoNext()
    {
      self::setAjaxMode();
      $ostrich_color = self::getArg( "ostrich", AT_alphanum, true ); // ff0000, 0000ff, etc.

      $this->game->executeChooseOstrichToGoNext( $ostrich_color );
      self::ajaxResponse( );
    }

    public function actStartZigPhaseOver()
    {
        self::setAjaxMode();

        $this->game->executeStartZigPhaseOver();
        self::ajaxResponse( );
    }

    // The player is indicating they do not want to play a trap.
    public function actNoTrap()
    {
        self::setAjaxMode();
        $card_id = self::getArg( "id", AT_posint, true );

        $this->game->noTrap();
        self::ajaxResponse( );
    }

    public function actSetTrap()
    {
        self::setAjaxMode();
        $ostrich_color = self::getArg( "ostrich", AT_alphanum, true ); // ff0000, 0000ff, etc.

        $this->game->executeSetTrap( $ostrich_color );
        self::ajaxResponse( );
    }

    // not used yet
    public function actEndPlanPhase()
    {
      self::setAjaxMode();

      $this->game->startTrapPhase();

      self::ajaxResponse( );
    }

    // The player is saying they will not use a Zag during Move phase.
    public function actSkipBooster()
    {
        self::setAjaxMode();
        $this->game->executeSkipBooster();
        self::ajaxResponse( );
    }

    // The player is saying they will not CLAIM a Zag in the Plan phase.
    public function actSkipZag()
    {
        self::setAjaxMode();
        $this->game->executeSkipZag();
        self::ajaxResponse( );
    }

    public function actSelectXValue()
    {
        self::setAjaxMode();
        $x_value = self::getArg( "xValue",  AT_int, true );
        $this->game->executeSelectXValue($x_value);
        self::ajaxResponse( );
    }

    // The player has chosen their ostrich so we can now move them.
    public function actExecuteMove()
    {
        self::setAjaxMode();
        $ostrich_color = self::getArg( "ostrich", AT_alphanum, true ); // ff0000, 0000ff, etc.
        $ostrich_taking_turn = self::getArg( "ostrichTakingTurn", AT_alphanum, true ); // ff0000, 0000ff, etc.
        $this->game->executeMove(  $ostrich_color, $ostrich_taking_turn );
        self::ajaxResponse( );
    }

    public function actExecuteDirectionClick()
    {
        self::setAjaxMode();
        $direction = self::getArg( "direction", AT_alphanum, true ); // BRIDGE, MOUNTAIN, etc.

        $this->game->executeDirectionClick( $direction );
        self::ajaxResponse( );
    }

    public function actReplaceGarment()
    {
        self::setAjaxMode();
        $ostrich_color = self::getArg( "ostrich", AT_alphanum, true ); // ff0000, 0000ff, etc.
        $skateboard_direction = self::getArg( "direction", AT_alphanum, true ); // BRIDGE, MOUNTAIN, etc.

        $this->game->executeSkateboardMove(  $ostrich_color, $ostrich_color, $skateboard_direction );
        self::ajaxResponse( );
    }

    // The player has chosen a garment from the pile.
    public function actReplaceGarmentChooseGarment()
    {
      self::setAjaxMode();
      $garment_type = self::getArg( "garmentType", AT_alphanum, true ); // head, legs, etc.
      $garment_color = self::getArg( "garmentColor", AT_alphanum, true ); // ff0000, etc

      //echo "Replacing garment at ($destination_x, $destination_y). <br>";

      $this->game->executeReplaceGarmentChooseGarment(  $garment_type, $garment_color );
      self::ajaxResponse( );
    }

    // The player has chose the place where they would like a new garment to spawn.
    public function actReplaceGarmentChooseSpace()
    {
      self::setAjaxMode();
      $destination_x = self::getArg( "garmentDestinationX", AT_posint, true );
      $destination_y = self::getArg( "garmentDestinationY", AT_posint, true );

      //echo "Replacing garment at ($destination_x, $destination_y). <br>";

      $this->game->executeReplaceGarmentChooseSpace( $destination_x, $destination_y );
      self::ajaxResponse( );
    }

    public function actChooseUpgradeSpace()
    {
        self::setAjaxMode();
        $destination_x = self::getArg( "chosenX", AT_posint, true );
        $destination_y = self::getArg( "chosenY", AT_posint, true );

        //echo "Replacing garment at ($destination_x, $destination_y). <br>";

        $this->game->executeChooseUpgradeSpace( $destination_x, $destination_y );
        self::ajaxResponse( );
    }

    public function actChooseAnySpaceForSaucer()
    {
        self::setAjaxMode();
        $destination_x = self::getArg( "garmentDestinationX", AT_posint, true );
        $destination_y = self::getArg( "garmentDestinationY", AT_posint, true );

        //echo "Replacing garment at ($destination_x, $destination_y). <br>";

        $this->game->executeChooseAnySpaceForSaucer( $destination_x, $destination_y );
        self::ajaxResponse( );
    }

    public function actStealGarment()
    {
        self::setAjaxMode();
        $garment_type = self::getArg( "garmentType", AT_alphanum, true ); // head, legs, etc.
        $garment_color = self::getArg( "garmentColor", AT_alphanum, true ); // ff0000, etc


        //echo "Replacing garment at ($destination_x, $destination_y). <br>";

        $this->game->executeStealGarment(  $garment_type, $garment_color );
        self::ajaxResponse( );
    }

    public function actDiscardGarment()
    {
        self::setAjaxMode();
        $garment_type = self::getArg( "garmentType", AT_alphanum, true ); // head, legs, etc.
        $garment_color = self::getArg( "garmentColor", AT_alphanum, true ); // ff0000, etc


        //echo "Replacing garment at ($destination_x, $destination_y). <br>";

        $this->game->executeDiscardGarment(  $garment_type, $garment_color );
        self::ajaxResponse( );
    }

    // The player pushed someone off a cliff and chose to draw 2 zigs instead of stealing a garment.
    public function actDraw2Zigs()
    {
        self::setAjaxMode();
        $this->game->executeDraw2Zigs();
        self::ajaxResponse( );
    }

    public function actAskWhichGarmentToSteal()
    {
        self::setAjaxMode();
        $this->game->executeAskWhichGarmentToSteal();
        self::ajaxResponse( );
    }

    public function actClaimZag()
    {
        self::setAjaxMode();

        $ostrich_color = self::getArg( "ostrich", AT_alphanum, true ); // ff0000, 0000ff, etc.
        $cards_raw = self::getArg( "cardsDiscarded", AT_numberlist, true ); // the list of cards they chose to discard

        // Removing last ';' if exists
        if( substr( $cards_raw, -1 ) == ';' )
            $cards_raw = substr( $cards_raw, 0, -1 );
        if( $cards_raw == '' )
            $cards_discarded = array();
        else
            $cards_discarded = explode( ';', $cards_raw );


        $this->game->executeClaimZag(  $ostrich_color, $cards_discarded );
        self::ajaxResponse( );
    }

    public function actRespawnOstrich()
    {
      self::setAjaxMode();
      $this->game->executeRespawnOstrich();
      self::ajaxResponse( );
    }

    public function actDiscardTrap()
    {
        self::setAjaxMode();

        $cardDiscarded = self::getArg( "cardDiscarded", AT_posint, true ); // the trap card they are discarding

        $this->game->executeDiscardTrap( $cardDiscarded );
        self::ajaxResponse( );
    }

    public function actExecuteTrap()
    {
        self::setAjaxMode();
        $this->game->executeTrapUsage();
        self::ajaxResponse( );
    }

  }
