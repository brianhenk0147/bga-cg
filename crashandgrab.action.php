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
    public function actNoZag()
    {
        self::setAjaxMode();
        $this->game->executeNoZag();
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

    // The player has chosen the direction they would like to skateboard or after being dizzy.
    public function actExecuteMoveInNewDirection()
    {
        self::setAjaxMode();
        $ostrich_color = self::getArg( "ostrich", AT_alphanum, true ); // ff0000, 0000ff, etc.
        $ostrich_taking_turn = self::getArg( "ostrichTakingTurn", AT_alphanum, true ); // ff0000, 0000ff, etc.
        $direction = self::getArg( "direction", AT_alphanum, true ); // BRIDGE, MOUNTAIN, etc.

        $this->game->executeMoveInNewDirection(  $ostrich_color, $ostrich_taking_turn, $direction );
        self::ajaxResponse( );
    }

    // The player has chosen the direction they would like to zag.
    public function actExecuteZagMove()
    {
        self::setAjaxMode();
        $direction = self::getArg( "direction", AT_alphanum, true ); // BRIDGE, MOUNTAIN, etc.

        $this->game->executeZagMove( $direction );
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
