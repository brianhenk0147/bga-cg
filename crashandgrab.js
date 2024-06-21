/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * CrashAndGrab implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * crashandgrab.js
 *
 * CrashAndGrab user interface script
 *
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter",
    "ebg/stock",
    "ebg/counter"
],
function (dojo, declare) {
    return declare("bgagame.crashandgrab", ebg.core.gamegui, {
        constructor: function(){
            console.log('crashandgrab constructor');

            // Here, you can init the global variables of your user interface
            // Example:
            // this.myGlobalValue = 0;

            // colors
            this.REDCOLOR = "f6033b";
            this.YELLOWCOLOR = "fedf3d";
            this.BLUECOLOR = "0090ff";
            this.GREENCOLOR = "01b508";
            this.PURPLECOLOR = "b92bba";
            this.ORANGECOLOR = "e77324";


            // directions
            this.UP_DIRECTION = 'sun';
    				this.DOWN_DIRECTION = 'meteor';
    				this.LEFT_DIRECTION = 'constellation';
    				this.RIGHT_DIRECTION = 'asteroids';

            // saucer1/saucer2 agnostic... just whichever on they are currently choosing now
            this.SAUCER_SELECTED = ''; // when choosing Move cards, this is the saucer the player is choosing moves for
            this.MOVE_CARD_SELECTED = ''; // when choosing Move cards, this is set to the id of the move card currently selected

            this.CHOSEN_MOVE_CARD_SAUCER_1 = ''; // the move card chosen for Saucer 1 this round
            this.CHOSEN_DIRECTION_SAUCER_1 = ''; // the direction chosen for Saucer 1 this round
            this.CHOSEN_MOVE_CARD_SAUCER_2 = ''; // the move card chosen for Saucer 2 this round
            this.CHOSEN_DIRECTION_SAUCER_2 = ''; // the direction chosen for Saucer 2 this round

            // give away crewmember
            this.GIVE_AWAY_TYPE = '';
            this.GIVE_AWAY_COLOR = '';
            this.GIVE_AWAY_SAUCER = '';

            // selecting X and Y values for X move cards
            this.startingXLocation = '';
            this.startingYLocation = '';

            // saucers this player controls
            this.lastMovedOstrich = ""; // this is the color of the ostrich that was last moved
            this.playerSaucerMoves = null; // save the list of players/saucers/move cards/spaces so it can be used elsewhere

            this.ANIMATION_SPEED_MOVING_SAUCER = 300; // the speed of moving saucers (lower is faster)
            this.ANIMATION_SPEED_CREWMEMBER_PICKUP = 900; // the speed of moving a crewmember from the board to a saucer (lower is faster)

            // zig cards
            this.movementcardwidth = 82;
            this.movementcardheight = 82;

            // upgrade cards
            this.trapHand = null;
            //this.upgradecardwidth = 82;
            //this.upgradecardheight = 58;

            this.upgradecardwidth = 230;
            this.upgradecardheight = 164;

            // saucer mat
            this.saucermatwidth = 154;
            this.saucermatheight = 154;

            // sub-states
            this.playedCardThisTurn = false; // true if I have chosen the Zig I will play this round
            this.choseDirectionThisTurn = false; // true if I have chosen the DIRECTION of the Zig I will play this round
            //this.finishedTrapping = false; // true if we have either set our trap or chosen not to set a trap or we don't have any traps
            this.ostrichChosen = false; // true when the player selects which ostrich they will move this turn
            this.canZag = true; // true if the ostrich has a zag token and has not fallen off a cliff this round
            this.mustSkateboard = false; // true if the player landed on a skateboard space

            // chosen card
            this.chosen_card_id = -1;
            this.chosen_card_direction="";

            this.saucer1HasZag = false; // true if ostrich 1 has a zag
            this.saucer2HasZag = false; // true if ostrich 2 has a zag
            this.canWeClaimZag = false; // true if we have 3 matching Zigs
            this.mustChooseZagDiscards = false; // true if the player has chosen to discard Zigs for a Zag but they haven't chosen yet
            this.askedZag = false; // true if the player has declined to claim a Zag

            // replacing garments
            this.chosenGarmentType = null;
            this.chosenGarmentColor = null;

            // other
            this.hasMultipleOstriches = false; // will set to true if this player is controlling multiple ostriches

            // counters for player boards
            this.energy_counters={};
            this.booster_counters={};
        },

        /*
            setup:

            This method must set up the game user interface according to current game situation specified
            in parameters.

            The method is called each time the game interface is displayed to a player, ie:
            _ when the game starts
            _ when a player refreshes the game page (F5)

            "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
        */

        setup: function( gamedatas )
        {
            console.log( "Starting game setup" );

            // Setting up player boards
            var numberOfPlayers = 0;
            for( var player_id in gamedatas.players )
            {
                var player = gamedatas.players[player_id];
//$('overall_player_board_'+player_id).style.removeProperty('height'); // remove
  //              dojo.addClass( 'overall_player_board_'+player_id, 'player_board_container' );
              console.log("player:"+player);

              numberOfPlayers++; // count number of players to use later
            }


            for( var i in gamedatas.ostrich )
            { // go through each saucer
                var saucer = gamedatas.ostrich[i];
console.log("owner:"+saucer.owner+" color:"+saucer.color);

                // place the player board framework for this saucer
                this.placePlayerBoardForSaucer(saucer.owner, saucer.color); // put everything for this saucer on the player's board

                // place any boosters they have on their saucer mat
                var boosterQuantity = saucer.booster_quantity;
                if(boosterQuantity == 1)
                { // they have a booster

                    // place it on the mat
                    var matBoosterLocationHtmlId = 'booster_acquired_'+saucer.color;
                    dojo.place( this.format_block( 'jstpl_booster', {
                         location: saucer.color,
                         position: boosterQuantity
                    } ) , matBoosterLocationHtmlId);
                }
                else if(boosterQuantity == 2)
                { // they have a Cargo Hold with an additional booster

                    // place it on the Cargo Hold card

                }
                // update the player board with the value of how many boosters
                this.booster_counters[saucer.color].setValue(boosterQuantity);

                // place any energy they have on their saucer mat
                var energyQuantity = saucer.energy_quantity;
                for (let i = 1; i <= energyQuantity; i++)
                {
                    var matEnergyLocationHtmlId = 'energy_acquired_'+saucer.color;
                    dojo.place( this.format_block( 'jstpl_energy', {
                         location: saucer.color,
                         position: i
                    } ) , matEnergyLocationHtmlId);
                }

                // update the player board with the value of how many boosters
                this.energy_counters[saucer.color].setValue(energyQuantity);
            }



            this.placeBoard(numberOfPlayers);

            this.initializeMoveCards();

            this.initializePlayedUpgrades();



            for( var i in gamedatas.board )
            {
                var square = gamedatas.board[i];

                if( square.space_type !== null )
                {
/*
                    // temporarily show the space type on the screen
                    var main = $('square_'+square.x+'_'+square.y);
                    console.log("setting innerHTML of " + square.x + ", " + square.y);
                    main.innerHTML = square.space_type;
*/
                    // temporarily show the space type on the screen
                    var main = $('square_'+square.x+'_'+square.y);
                    console.log("setting innerHTML of " + square.x + ", " + square.y);
                    var type = square.space_type;
                    if(type =='S')
                    {
                      //main.style.backgroundColor='rgba(0, 255, 0, 0.3)'; // transparent green
                    }
                    if(type =='D')
                    { // board edge
                      //main.style.backgroundColor='yellow';
                    }
                    if(type =='C')
                    {
                      main.style.backgroundColor='rgba(0, 0, 255, 0.3)';
                    }
                    if(type =='O')
                    {
                      main.style.backgroundColor='rgba(255, 0, 0, 0.3)';
                    }

                }
            }

            // First Param: css class to target
            // Second Param: type of events
            // Third Param: the method that will be called when the event defined by the second parameter happen
            this.addEventToClass( "space", "onclick", "onClickSpace");

            for( var i in gamedatas.ostrich )
            { // go through each ostrich
                var singleOstrich = gamedatas.ostrich[i];

                if(singleOstrich.ostrich_has_crown == 1)
                { // this ostrich has the crown
                    console.log("gets crown:" + singleOstrich.color);
                    this.putProbeOnPlayerBoard(singleOstrich.color); // place it on their player board on the right

                    var arrowX = 0;
                    if(singleOstrich.ostrich_last_turn_order == 1)
                    { // we're going counter-clockwise
                      arrowX = 35;
                    }

                    for( var player_id in gamedatas.players )
                    {
                        this.putArrowOnPlayerBoard(arrowX, 0, player_id); // draw a zig into this player's hand
                        console.log("stateName:" + gamedatas.stateName);
                        if(gamedatas.stateName == 'chooseZigPhase' || gamedatas.stateName == 'claimZag' || gamedatas.stateName == 'askTrapBasic' || gamedatas.stateName == 'setTrapPhase')
                        { // hide the turn direction
                            console.log("we need to hideTurnDirection");
                            this.setTurnDirectionArrow(70, 0, player_id); // we don't want to show the turn direction arrow until it has been chosen for this round
                        }
                        else
                        {
                            console.log("we do NOT want to hideTurnDirection");
                        }
                    }
                }



                this.putSaucerOnTile( singleOstrich.x, singleOstrich.y, singleOstrich.owner, singleOstrich.color ); // add the ostrich to the board

            }

            this.lastMovedOstrich = this.gamedatas.lastMovedOstrich; // this is the color of the ostrich that was last moved

            var currentLocation = "";
            var currentType = "";
            var currentTypeLocationCount = 0;
            for( var i in gamedatas.garment )
            {
                var garment = gamedatas.garment[i];
                var color = garment.garment_color; // the color of the crewmember
                var location = garment.garment_location; // the color of the player who has this
                var typeInt = garment.garment_type;
                var typeString = this.convertCrewmemberType(typeInt);
                var x = garment.garment_x;
                var y = garment.garment_y;
                var wearingOrBackpack = "wearing";

                console.log("garment color:"+color+" typeString:"+typeString+" location:"+location);

                if(currentLocation == location && currentType == typeInt)
                { // this garment is in the same location and same type as the last one
                    currentTypeLocationCount += 1;
                }
                else
                { // this garment is in a different location as the previous one
                    currentTypeLocationCount = 1;
                    currentLocation = location;
                    currentType = typeInt;
                }

                if(currentTypeLocationCount > 1)
                { // this is at least the second garment of this type
                    wearingOrBackpack = "backpack";
                }
                else
                { // this is the first garment of this type
                    wearingOrBackpack = "wearing";
                }

                if( location == "board" )
                { // this garment is out on the board
                    dojo.place( this.format_block( 'jstpl_garment', {
                          color: color,
                          garment_type: typeString,
                          size: "crewmember",
                          small: ""
                    } ) , 'square_'+x+'_'+y );
                }
                else if(location == "pile")
                { // this garment is in the garment pile
                    var pileLocationHtmlId = 'garment_holder_'+typeString+'_'+color;
                    console.log('pileLocationHtmlId:'+pileLocationHtmlId);
                    dojo.place( this.format_block( 'jstpl_garment', {
                          color: color,
                          garment_type: typeString,
                          size: "crewmember",
                          small: ""
                    } ) , pileLocationHtmlId );
                }
                else if(location == "chosen")
                { // this garment is chosen to be replaced but not yet placed
                    dojo.place( this.format_block( 'jstpl_garment', {
                          color: color,
                          garment_type: typeString,
                          size: "crewmember",
                          small: ""
                    } ) , 'replacement_garment_chosen_holder' );
                }
                else
                { // this crewmember has been claimed by a player
                    // var matLocationHtmlId = 'mat_'+typeString+"_"+wearingOrBackpack+"_"+currentTypeLocationCount+"_"+location;
                    //location = '0090ff';
                    var matLocationHtmlId = 'player_board_saucer_mat_'+typeString+'_'+location; // example: player_board_saucer_mat_pilot_01b508
                    var crewmemberHtmlId = 'crewmember_'+typeString+'_'+color; // example: crewmember_pilot_01b508

                    console.log('matLocationHtmlId:'+matLocationHtmlId+' crewmemberHtmlId:'+crewmemberHtmlId);

                    dojo.place( this.format_block( 'jstpl_garment', {
                          color: color,
                          garment_type: typeString,
                          size: "crewmember",
                          small: ""
                    } ) , matLocationHtmlId );


                    dojo.addClass( crewmemberHtmlId, 'played_'+typeString);

                    // in 2-player games, we must adjust the location of crewmembers because
                    // they get pushed down by the number of upgrades their teammat has
                    this.adjustCrewmemberLocationBasedOnUpgrades(location, typeString);
                }

            }



            this.updateTurnOrder(this.gamedatas.turnOrder);

            // First Param: css class to target
            // Second Param: type of events
            // Third Param: the method that will be called when the event defined by the second parameter happen
            this.addEventToClass('direction_token', 'onclick', 'onClick_direction'); // add the click handler to all direction tokens


            // set these:
//            this.iHaveZag = false; // true if this player has a zag
//            this.canWeClaimZag = false; // true if we have 3 matching Zigs
//            this.mustChooseZagDiscards = false; // true if the player has chosen to discard Zigs for a Zag but they haven't chosen yet
//            this.askedZag = false; // true if the player has declined to claim a Zag





            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();

            console.log( "Ending game setup" );
        },


                ///////////////////////////////////////////////////
                //// Player's action

                /*

                    Here, you are defining methods to handle player's action (ex: results of mouse click on
                    game objects).

                    Most of the time, these methods:
                    _ check the action is possible at this game state.
                    _ make a call to the game server

                */

                onUpgradeHandSelectionChanged: function( evt )
                {
                    console.log( "An upgrade card was selected." );

                    if(this.isCurrentPlayerActive() && this.checkAction( 'chooseUpgrade', true ))
                    { // player is allowed to choose an upgrade (nomessage parameter is true so that an error message is not displayed)

                        var cardsSelected = this.upgradesAvailable.getSelectedItems(); // get the trap cards that were selected

                        if( cardsSelected.length == 1 )
                        { // one card is selected
                              console.log( "A single card is selected." );

                              for( var i in cardsSelected )
                              {
                                actClickUpgradeInHand
                                  this.sendDiscardTrap(cardsSelected[i].id); // put the card IDs in a semicolon-delimited list
                              }
                        }
                    }
                    else
                    { // we are not in a state where we can select trap cards
                        this.trapHand.unselectAll();
                        var unselectedCards = this.upgradesAvailable.getUnselectedItems(); // get the cards that were selected
                        for( var i in unselectedCards )
                        {
                        //    var htmlIdOfCard = 'trap_hand_'+this.player_id+'_item_'+unselectedCards[i].id;
                        //    dojo.removeClass( htmlIdOfCard, 'cardSelected' ); // give this card a new CSS class
                        //    dojo.addClass( htmlIdOfCard, 'cardUnselected' ); // give this card a new CSS class

                        }
                    }

                },

                onTrapHandSelectionChanged: function( )
                {
                    console.log( "A trap card was selected." );

                    if(this.isCurrentPlayerActive() && this.checkAction( 'discardTrapCard', true ))
                    { // player is allowed to discard a trap card (nomessage parameter is true so that an error message is not displayed)

                        var trapsSelected = this.trapHand.getSelectedItems(); // get the trap cards that were selected

                        if( trapsSelected.length == 1 )
                        { // one card is selected
                              console.log( "A single trap card is selected." );

                              for( var i in trapsSelected )
                              {
                                  this.sendDiscardTrap(trapsSelected[i].id); // put the card IDs in a semicolon-delimited list
                              }
                        }
                    }
                    else
                    { // we are not in a state where we can select trap cards
                        this.trapHand.unselectAll();
                        var unselectedCards = this.trapHand.getUnselectedItems(); // get the cards that were selected
                        for( var i in unselectedCards )
                        {
                            var htmlIdOfCard = 'trap_hand_'+this.player_id+'_item_'+unselectedCards[i].id;
                            dojo.removeClass( htmlIdOfCard, 'cardSelected' ); // give this card a new CSS class
                            dojo.addClass( htmlIdOfCard, 'cardUnselected' ); // give this card a new CSS class

                        }
                    }
                },

                onClick_selectSaucerToPlace: function( evt )
                {
                    var htmlIdOfButton = evt.currentTarget.id;
                    console.log( "Clicked saucer to place with node "+htmlIdOfButton+"." );
                    var color = htmlIdOfButton.split('_')[2]; // BLUE, RED



                    //if(this.isCurrentPlayerActive() && this.checkAction( 'clickSaucer', true ))
                    //{ // player is allowed to confirm move (nomessage parameter is true so that an error message is not displayed)

                        this.ajaxcall( "/crashandgrab/crashandgrab/actClickedSaucerToPlace.html", {
                                                                                    colorAsHex: color,
                                                                                    lock: true
                                                                                 },
                                         this, function( result ) {

                                            // What to do after the server call if it succeeded
                                            // (most of the time: nothing)

                                         }, function( is_error) {

                                            // What to do after the server call in anyway (success or failure)
                                            // (most of the time: nothing)
                      } );
                  //}

                },

                onClick_selectSaucerToGoFirst: function( evt )
                {
                    var htmlIdOfButton = evt.currentTarget.id; // saucer_button_ffffff
                    console.log( "Clicked saucer to go first with node "+htmlIdOfButton+"." );
                    var colorHex = htmlIdOfButton.split('_')[2]; // BLUE, RED

                    if(this.isCurrentPlayerActive() && this.checkAction( 'clickSaucerToGoFirst', true ))
                    { // player is allowed to confirm move (nomessage parameter is true so that an error message is not displayed)

                        this.ajaxcall( "/crashandgrab/crashandgrab/actClickedSaucerToGoFirst.html", {
                                                                                    colorHex: colorHex,
                                                                                    lock: true
                                                                                 },
                                         this, function( result ) {

                                            // What to do after the server call if it succeeded
                                            // (most of the time: nothing)



                                         }, function( is_error) {

                                            // What to do after the server call in anyway (success or failure)
                                            // (most of the time: nothing)

                        } );
                    }
                },

                onClick_beginTurn: function( evt )
                {
                    console.log( "Clicked start turn button." );

                    this.ajaxcall( "/crashandgrab/crashandgrab/actClickedBeginTurn.html", {
                                                                                lock: true
                                                                             },
                                     this, function( result ) {

                                        // What to do after the server call if it succeeded
                                        // (most of the time: nothing)


                                     }, function( is_error) {

                                        // What to do after the server call in anyway (success or failure)
                                        // (most of the time: nothing)

                    } );

                },

                onClick_activateHyperdrive: function (evt )
                {
                    console.log( "Clicked activate hyperdrive button." );

                    this.ajaxcall( "/crashandgrab/crashandgrab/actActivateHyperdrive.html", {
                                                                                lock: true
                                                                             },
                                     this, function( result ) {

                                        // What to do after the server call if it succeeded
                                        // (most of the time: nothing)


                                     }, function( is_error) {

                                        // What to do after the server call in anyway (success or failure)
                                        // (most of the time: nothing)

                    } );

                },

                onClick_skipHyperdrive: function (evt )
                {
                    console.log( "Clicked skip hyperdrive button." );

                    this.ajaxcall( "/crashandgrab/crashandgrab/actSkipHyperdrive.html", {
                                                                                lock: true
                                                                             },
                                     this, function( result ) {

                                        // What to do after the server call if it succeeded
                                        // (most of the time: nothing)


                                     }, function( is_error) {

                                        // What to do after the server call in anyway (success or failure)
                                        // (most of the time: nothing)

                    } );
                },

                onClick_undoMove: function( evt )
                {
                    console.log( "Clicked undo move button." );

                    this.ajaxcall( "/crashandgrab/crashandgrab/actClickedUndoMove.html", {
                                                                                lock: true
                                                                             },
                                     this, function( result ) {

                                        // What to do after the server call if it succeeded
                                        // (most of the time: nothing)


                                     }, function( is_error) {

                                        // What to do after the server call in anyway (success or failure)
                                        // (most of the time: nothing)

                    } );

                },

                onClick_finalizeMove: function( evt )
                {
                    console.log( "Clicked finalize move button." );

                    this.ajaxcall( "/crashandgrab/crashandgrab/actClickedFinalizeMove.html", {
                                                                                lock: true
                                                                             },
                                     this, function( result ) {

                                        // What to do after the server call if it succeeded
                                        // (most of the time: nothing)


                                     }, function( is_error) {

                                        // What to do after the server call in anyway (success or failure)
                                        // (most of the time: nothing)

                    } );

                },

                onClick_confirmMove: function( evt )
                {
                    console.log( "Clicked confirm move button." );

                    if(this.isCurrentPlayerActive() && this.checkAction( 'confirmMove', true ))
                    { // player is allowed to confirm move (nomessage parameter is true so that an error message is not displayed)

                        var saucer1Color = this.gamedatas.saucer1; // 01b508
                        var saucer1Distance = this.CHOSEN_MOVE_CARD_SAUCER_1; // move_card_1_01b508
                        if(saucer1Distance != '')
                          saucer1Distance = saucer1Distance.split('_')[2]; // 0, 1, 2

                        var saucer1Direction = this.CHOSEN_DIRECTION_SAUCER_1; // direction_asteroids
                        if(saucer1Direction != '')
                          saucer1Direction = saucer1Direction.split('_')[1]; // asteroids

                        var saucer2Color = this.gamedatas.saucer2; // 01b508
                        var saucer2Distance = this.CHOSEN_MOVE_CARD_SAUCER_2;
                        if(saucer2Distance != '')
                          saucer2Distance = saucer2Distance.split('_')[2]; // 0, 1, 2

                        var saucer2Direction = this.CHOSEN_DIRECTION_SAUCER_2; // direction_asteroids
                        if(saucer2Direction != '')
                          saucer2Direction = saucer2Direction.split('_')[1]; // asteroids


                        this.ajaxcall( "/crashandgrab/crashandgrab/actClickedConfirmMove.html", {
                                                                                    saucer1Color: saucer1Color,
                                                                                    saucer1Distance: saucer1Distance,
                                                                                    saucer1Direction: saucer1Direction,
                                                                                    saucer2Color: saucer2Color,
                                                                                    saucer2Distance: saucer2Distance,
                                                                                    saucer2Direction: saucer2Direction,
                                                                                    lock: true
                                                                                 },
                                         this, function( result ) {

                                            // What to do after the server call if it succeeded
                                            // (most of the time: nothing)



                                         }, function( is_error) {

                                            // What to do after the server call in anyway (success or failure)
                                            // (most of the time: nothing)

                        } );
                    }
                },

                // The player is selecting a card for any of these reasons:
                //     A) They are choosing the move card they will play this round.
                onClick_moveCard: function( evt )
                {
                    var htmlIdOfCard = evt.currentTarget.id; // move_card_2_f6033b
                    console.log( "A move card was clicked with node "+htmlIdOfCard+"." );
                    var color = htmlIdOfCard.split('_')[3]; // 0090ff
                    var distanceType = htmlIdOfCard.split('_')[2]; // 0,1,2
                    var saucerNumber = 2;
                    if(color == this.gamedatas.saucer1)
                    {
                        saucerNumber = 1;
                    }

                    this.selectMoveCard(distanceType, color, saucerNumber);
                },

                selectMoveCard: function (distanceType, color, saucerNumber)
                {
                    var htmlIdOfCard = 'move_card_'+distanceType+'_'+color; // move_card_2_f6033b
                    var htmlIdOfButton = 'moveCard_'+saucerNumber+'_distance_'+distanceType+'_button'; // moveCard_1_distance_2_button
                    if(!this.isCurrentPlayerActive())
                    {
                        console.log( "We are not the current active player." );
                        return;
                    }

                    if(this.MOVE_CARD_SELECTED == htmlIdOfCard)
                    {
                        console.log( "This move card is already selected." );
                        return;
                    }

                    if( !this.checkPossibleActions('clickDistance') )
                    { // we are not allowed to select cards based on our current state
                        console.log( "The current state does not allow this card to be clicked." );
                        return;
                    }

                    if( (this.gamedatas.saucer1 == color && this.CHOSEN_MOVE_CARD_SAUCER_1 == htmlIdOfCard) ||
                      (this.gamedatas.saucer2 == color && this.CHOSEN_MOVE_CARD_SAUCER_2 == htmlIdOfCard) )
                    { // this move card is already set
                        console.log( "This move card is already set." );
                        return;
                    }

                    if(color != this.SAUCER_SELECTED)
                    { // no saucer is selected or a different saucer is selected
//                        console.log( "The saucer belonging to this card is not selected." );
//                        return;

                          // select this saucer instead
                          this.selectSaucer(color);
                    }

                    // if a different move card is chosen, bring that move card back to your hand
                    if( (this.CHOSEN_MOVE_CARD_SAUCER_1 != '' && this.gamedatas.saucer1 == color && this.CHOSEN_MOVE_CARD_SAUCER_1 != htmlIdOfCard) )
                    {
                        this.returnMoveCardToHandOfSaucer(color);
                        this.unselectAllDirections();
                        this.CHOSEN_MOVE_CARD_SAUCER_1 = '';
                    }

                    if(this.CHOSEN_MOVE_CARD_SAUCER_2 != '' && this.gamedatas.saucer2 == color && this.CHOSEN_MOVE_CARD_SAUCER_2 != htmlIdOfCard)
                    { // a different move card is currently set
                        this.returnMoveCardToHandOfSaucer(color);
                        this.unselectAllDirections();
                        this.CHOSEN_MOVE_CARD_SAUCER_2 = '';
                    }

                    this.MOVE_CARD_SELECTED = htmlIdOfCard; // this is the currently selected move card
                    this.saveMoveCardSelection(color, htmlIdOfCard); // save the move card for this saucer in case it is the final selection

                    // add selected highlight around the move card
                    this.unselectAllMoveCards(); // UNSELECT ALL other move cards so we can select a different one
                    this.selectSpecificMoveCard(htmlIdOfCard);

                    //this.highlightAllMoveCardsForSaucer(color); // HIGHLIGHT ALL move cards for this saucer
                    //this.unhighlightSpecificMoveCard(htmlIdOfCard); // UNHIGHLIGHT this SPECIFIC move card
                    this.unhighlightAllMoveCards(); // UNhighlight ALL move cards

                    //this.highlightDirectionsForSaucer(color); // HIGHLIGHT the DIRECTIONS for the selected saucer and move card
                    this.makeAllDirectionTokensClickable(); // make it clear you can click on directions

                    // see if we have chosen the direction yet
                    var directionOnly = '';
                    if(saucerNumber == 2)
                    {
                        if(this.CHOSEN_DIRECTION_SAUCER_2 != '')
                        { // the direction has been chosen
                            directionOnly = this.CHOSEN_DIRECTION_SAUCER_2.split('_')[1]; // asteroids
                        }

                    }
                    else
                    {
                        if(this.CHOSEN_DIRECTION_SAUCER_1 != '')
                        { // the direction has been chosen
                            directionOnly = this.CHOSEN_DIRECTION_SAUCER_1.split('_')[1]; // asteroids
                        }
                    }

                    var moveCardSelected = this.MOVE_CARD_SELECTED.split('_')[2]; // 0, 1, 2
                    this.highlightPossibleMoveSelections(this.playerSaucerMoves, this.player_id, this.SAUCER_SELECTED, moveCardSelected, directionOnly); // highlight possible destinations on board


                    if(saucerNumber == '1')
                    {
                        // remove all distance button highlights
                        dojo.query( '.saucer1DistanceButtonSelected' ).removeClass( 'saucer1DistanceButtonSelected' );

                        // highlight this distance button
                        dojo.addClass( htmlIdOfButton, 'saucer1DistanceButtonSelected' );

                        if( this.CHOSEN_DIRECTION_SAUCER_1 != '' )
                        { // we have everything we need for the move card

                            // slide the card to the saucer mat
                            this.placeMoveCard(this.gamedatas.saucer1, distanceType, directionOnly, true);
                        }
                    }
                    else
                    {
                        // remove all distance button highlights
                        dojo.query( '.saucer2DistanceButtonSelected' ).removeClass( 'saucer2DistanceButtonSelected' );

                        // highlight this distance button
                        dojo.addClass( htmlIdOfButton, 'saucer2DistanceButtonSelected' );

                        if(this.CHOSEN_DIRECTION_SAUCER_2 != '' )
                        { // we heve everything we need for the move card

                            // slide the card to the saucer mat
                            this.placeMoveCard(this.gamedatas.saucer2, distanceType, directionOnly, true);
                        }
                    }
                },

                onClick_saucerButtonClick: function (evt)
                {
                    var htmlIdOfButton = evt.currentTarget.id; // wormhole_saucer_button_01b508
                    var type = htmlIdOfButton.split('_')[0]; // wormhole
                    var saucerColor = htmlIdOfButton.split('_')[3]; // b92bba
                    console.log( "A saucer button was clicked during wormhole generation activation with node "+htmlIdOfButton+"." );

                    switch(type)
                    {
                        case 'wormhole':
                            this.ajaxcall( "/crashandgrab/crashandgrab/actWormholeSelectSaucer.html", { saucerColor: saucerColor, lock: true }, this, function( result ) {}, function( is_error ) {} );
                        break;
                    }
                },

                onClick_SaucerButton: function(evt)
                {
                    var htmlIdOfButton = evt.currentTarget.id; // saucer_f6033b_button
                    console.log( "A saucer button was clicked during move card selection with node "+htmlIdOfButton+"." );
                    var saucerColor = htmlIdOfButton.split('_')[1]; // b92bba
                    this.selectSaucer(saucerColor);
                },

                onClick_saucerDuringMoveCardSelection: function( evt )
                {
                    var htmlIdOfSaucer = evt.currentTarget.id;
                    console.log( "A saucer was clicked during move card selection with node "+htmlIdOfSaucer+"." );
                    var saucerColor = htmlIdOfSaucer.split('_')[1]; // b92bba
                    this.selectSaucer(saucerColor);
                },

                selectSaucer: function( saucerColor )
                {
                    var htmlIdOfSaucer = 'saucer_'+saucerColor; // saucer_f6033b
                    if(!this.isCurrentPlayerActive())
                    {
                        console.log( "We are not the current active player." );
                        return;
                    }

                    if(this.SAUCER_SELECTED == htmlIdOfSaucer)
                    { // this saucer is already selected
                        console.log("This saucer is already selected.");
                        return;
                    }

                    if(this.MOVE_CARD_SELECTED != '')
                    {
                        var moveCardColor = this.MOVE_CARD_SELECTED.split('_')[3]; // 0090ff
                        if(moveCardColor != saucerColor)
                        { // a different saucer's move card is currently selected
                            this.unselectSpecificMoveCard(this.MOVE_CARD_SELECTED);
                        }
                    }

                    this.SAUCER_SELECTED = saucerColor; // save which saucer is now selected


                    //this.highlightAllPlayerSaucers(this.player_id); // highlight all player saucers
                    this.unhighlightAllSaucers(); // UNhighlight ALL saucers
                    this.selectSpecificSaucer(saucerColor); // select that saucer

                    if((this.gamedatas.saucer1 == saucerColor && this.CHOSEN_MOVE_CARD_SAUCER_1 == '') ||
                       (this.gamedatas.saucer2 == saucerColor && this.CHOSEN_MOVE_CARD_SAUCER_2 == ''))
                    { // we have not yet chosen a move card for this saucer
                        //this.highlightAllMoveCardsForSaucer(saucerColor); // highlight the move cards now that it's time to choose one
                        this.removeClickableFromAllDirectionTokens(); // we don't want direction tokens to appear clickable until a move card is selected
                    }

                    this.makeMoveCardsForSaucerClickable(saucerColor);


                    this.selectSelectedMoveCard(saucerColor); // select the move card that is currently selected by this saucer (or none if none are selected)

                    this.selectSelectedDirection(saucerColor); // select the direction that is currently selected by this saucer (or none if none are selected)

                    this.highlightSpacesForSelectedSaucer(saucerColor); // highlight the board spaces for the selected saucer and move card (or none if none are selected)
                },

                onClick_direction: function( evt )
                {
                    var htmlIdOfToken = evt.currentTarget.id;
                    console.log( "A direction token was clicked with node "+htmlIdOfToken+"." );
                    var direction = htmlIdOfToken.split('_')[1]; // sun, constellation


                    if(this.checkPossibleActions('clickAcceleratorDirection'))
                    { // we are clicking on a direction as we hit an accelerator
                        //dojo.stopEvent( evt ); // Preventing default browser reaction

                        //this.showMessage( _("Accelerator direction click."), 'error' );

                        this.chooseAcceleratorDirection(direction);
                    }
                    else if(this.checkPossibleActions('clickMoveDirection'))
                    { // we are clicking on a direction while selecting our move card for the round
                        //dojo.stopEvent( evt ); // Preventing default browser reaction

                        //this.showMessage( _("Move direction click."), 'error' );

                        var saucerNumber = 1;
                        if(this.gamedatas.saucer2 == this.SAUCER_SELECTED)
                        {
                            saucerNumber = 2;
                        }

                        this.chooseMoveCardDirection(direction, saucerNumber);

                    }
                    else
                    {
                        //this.showMessage( _("You cannot do anything with this right now."), 'error' );
                        return;
                    }
                },

                clickDistance: function( node )
                {
                    var distance = node.split('_')[2]; // 0, 1, 2
                    var color = node.split('_')[3]; // fedf3d

                    this.ajaxcall( "/crashandgrab/crashandgrab/actClickedMoveCard.html", {
                                                                                distance: distance,
                                                                                color: color,
                                                                                lock: true
                                                                             },
                                     this, function( result ) {

                                        // What to do after the server call if it succeeded
                                        // (most of the time: nothing)



                                     }, function( is_error) {

                                        // What to do after the server call in anyway (success or failure)
                                        // (most of the time: nothing)

                    } );

                },

                getMoveCardBackgroundX: function(saucerColor)
                {
                    switch(saucerColor)
                    {

                        case "f6033b":
                            return 0;

                        case "fedf3d":
                            return 2*this.movementcardwidth;

                        case "0090ff":
                            return this.movementcardwidth;

                        case "01b508":
                            return 0;

                        case "b92bba":
                            return 2*this.movementcardwidth;

                        case "e77324":
                            return this.movementcardwidth;

                        default:
                            return 0;
                    }
                },

                getMoveCardBackgroundY: function(saucerColor)
                {
                    switch(saucerColor)
                    {

                        case "f6033b":
                            return this.movementcardheight;

                        case "fedf3d":
                            return 0;

                        case "0090ff":
                            return 0;

                        case "01b508":
                            return 0;

                        case "b92bba":
                            return this.movementcardheight;

                        case "e77324":
                            return this.movementcardheight;

                        default:
                            return 0;
                    }
                },

                saveMoveCardSelection: function(color, htmlIdOfCard)
                {
                    // set the move card for this saucer in case it is the final selection
                    if(this.gamedatas.saucer1 == color)
                    {
                        this.CHOSEN_MOVE_CARD_SAUCER_1 = htmlIdOfCard;
                    }
                    else
                    {
                        this.CHOSEN_MOVE_CARD_SAUCER_2 = htmlIdOfCard;
                    }
                },

                saveDirectionSelection: function(saucerColor, htmlIdOfToken)
                {
                    // set the move card for this direction in case it is the final selection
                    if(this.gamedatas.saucer1 == saucerColor)
                    {
                        this.CHOSEN_DIRECTION_SAUCER_1 = htmlIdOfToken;
                    }
                    else
                    {
                        this.CHOSEN_DIRECTION_SAUCER_2 = htmlIdOfToken;
                    }
                },

                onExecuteTrap: function( evt )
                {
                  if (this.checkAction( 'executeTrap', false ))
                  { // player is allowed to execute a trap

                      if ( this.isCurrentPlayerActive() )
                      { // the active player is clicking
                          this.ajaxcall( "/crashandgrab/crashandgrab/actExecuteTrap.html", { lock: true }, this, function( result ) {}, function( is_error ) {} );
                      }
                  }
                },

                onClickSpace: function( evt )
                { // a player clicked on a space

                    if ( !this.isCurrentPlayerActive() )
                    { // someone other than the active player is clicking
                        return;
                    }

                    var node = evt.currentTarget.id; //square_3_4
                    var chosenSpaceX = node.split('_')[1]; // x location of the space
                    var chosenSpaceY = node.split('_')[2]; // y location of the space

                    console.log("clicked on space " + chosenSpaceX + " " + chosenSpaceY);

                    var hasClassUp = dojo.hasClass(node, 'spaceClick_'+this.UP_DIRECTION);
                    var hasClassLeft = dojo.hasClass(node, 'spaceClick_'+this.LEFT_DIRECTION);
                    var hasClassRight = dojo.hasClass(node, 'spaceClick_'+this.RIGHT_DIRECTION);
                    var hasClassDown = dojo.hasClass(node, 'spaceClick_'+this.DOWN_DIRECTION);

                    if (this.checkPossibleActions( 'chooseCrewmemberPlacingSpace', true ))
                    { // player clicks on a garment (it must be checkPossibleActions because they could be replacing the garment on another player's turn so we don't want it to check for active player)

                            if(this.chosenSpaceX != 0 && this.chosenSpaceY != 0)
                            { // the player has chosen both a destination space and a garment
                                this.ajaxcall( "/crashandgrab/crashandgrab/actReplaceGarmentChooseSpace.html", {garmentDestinationX: chosenSpaceX, garmentDestinationY: chosenSpaceY, lock: true }, this, function( result ) {}, function( is_error ) {} );
                            }

                    }
                    else if (this.checkPossibleActions( 'chooseUpgradeSpace' ))
                    { // we are choosing a space when activating an upgrade
                        this.ajaxcall( "/crashandgrab/crashandgrab/actChooseUpgradeSpace.html", {chosenX: chosenSpaceX, chosenY: chosenSpaceY, lock: true }, this, function( result ) {}, function( is_error ) {} );
                        this.unhighlightAllSpaces();
                    }
                    else if (this.checkPossibleActions( 'chooseSaucerSpace', true ))
                    { // we are choosing a space to place a Saucer
                        if(this.chosenSpaceX != 0 && this.chosenSpaceY != 0)
                        { // the player has chosen both a destination space and a garment
                            this.ajaxcall( "/crashandgrab/crashandgrab/actChooseAnySpaceForSaucer.html", {garmentDestinationX: chosenSpaceX, garmentDestinationY: chosenSpaceY, lock: true }, this, function( result ) {}, function( is_error ) {} );
                            this.unhighlightAllSpaces();
                        }
                    }
                    else if (this.checkPossibleActions( 'selectXValue', true ))
                    { // we just revealed an X and are choosing its value
                        console.log('chosenSpaceX:'+chosenSpaceX+' chosenSpaceY:'+chosenSpaceY+' this.chosenSpaceX:'+this.chosenSpaceX+' this.chosenSpaceY:'+this.chosenSpaceY+' this.startingXLocation:'+this.startingXLocation+' this.startingYLocation:'+this.startingYLocation);

                        var distance = 0;
                        if(chosenSpaceX != this.startingXLocation && chosenSpaceY != this.startingYLocation)
                        { // we are not moving in a straight line

                            // not valid
                            return;
                        }
                        else if(chosenSpaceX == this.startingXLocation)
                        { // we are traveling left and right
                            distance = Math.abs(chosenSpaceY - this.startingYLocation);
                        }
                        else if(chosenSpaceY == this.startingYLocation)
                        { // we are traveling up and down
                            distance = Math.abs(chosenSpaceX - this.startingXLocation);
                        }

                        console.log('sending distance:'+distance);
                        this.sendXValue(distance);
                    }
                    else if (this.checkPossibleActions( 'clickAcceleratorDirection', true ))
                    {
                        console.log('clicked space instead of accelerator direction token');
                        if(hasClassUp)
                        {
                            this.chooseAcceleratorDirection(this.UP_DIRECTION);
                        }
                        else if(hasClassLeft)
                        {
                            this.chooseAcceleratorDirection(this.LEFT_DIRECTION);
                        }
                        else if(hasClassRight)
                        {
                            this.chooseAcceleratorDirection(this.RIGHT_DIRECTION);
                        }
                        else if(hasClassDown)
                        {
                            this.chooseAcceleratorDirection(this.DOWN_DIRECTION);
                        }
                    }
                    else if (this.checkPossibleActions( 'clickMoveDirection', true ))
                    { // we are choosing a direction for the saucer to go
                        console.log('clicked space instead of direction token');

                        var saucerNumber = 1;
                        if(this.gamedatas.saucer2 == this.SAUCER_SELECTED)
                        {
                            saucerNumber = 2;
                        }

                        if(hasClassUp)
                        {
                            this.chooseMoveCardDirection(this.UP_DIRECTION, saucerNumber);
                        }
                        else if(hasClassLeft)
                        {
                            this.chooseMoveCardDirection(this.LEFT_DIRECTION, saucerNumber);
                        }
                        else if(hasClassRight)
                        {
                            this.chooseMoveCardDirection(this.RIGHT_DIRECTION, saucerNumber);
                        }
                        else if(hasClassDown)
                        {
                            this.chooseMoveCardDirection(this.DOWN_DIRECTION, saucerNumber);
                        }

                    }
                    else
                    { // we cannot perform a garment-clicking action at this time
                        return; // ignore the click
                    }


                    /* VERSION WHERE WE CHECK FOR SPECIFIC ACTIONS
                    if(this.checkAction( 'spawnGarment', true ))
                    { // player wants to move a garment from Pile to Board (nomessage parameter is true so that an error message is not displayed)

                    }
                    else if (this.checkAction( 'rearrangeGarment', true ))
                    { // player wants to change which garment they are wearing (nomessage parameter is true so that an error message is not displayed)

                    }
                    else
                    { // we cannot perform a garment-clicking action at this time
                        return; // ignore the click
                    }
                    */
                },



        ///////////////////////////////////////////////////
        //// Game & client states

        // onEnteringState: this method is called each time we are entering into a new game state AFTER onUpdateActionButtons.
        //                  You can use this method to perform some user interface changes at this moment.
        // To get args, use args.args (not args like in onUpdateActionButtons ).
        onEnteringState: function( stateName, args )
        {
            console.log( 'Entering state: '+stateName );



            switch( stateName )
            {

            /* Example:

            case 'myGameState':

                // Show some HTML block at this game state
                dojo.style( 'my_html_block_id', 'display', 'block' );

                break;
           */
                  case 'endRoundCleanup':
                  case 'playerTurnStart':
                  this.unhighlightAllSpaces();

                      // reset all player move card selections
                      this.SAUCER_SELECTED = ''; // when choosing Move cards, this is the saucer the player is choosing moves for
                      this.MOVE_CARD_SELECTED = ''; // when choosing Move cards, this is set to the id of the move card currently selected
                      this.CHOSEN_MOVE_CARD_SAUCER_1 = ''; // the move card chosen for Saucer 1 this round
                      this.CHOSEN_DIRECTION_SAUCER_1 = ''; // the direction chosen for Saucer 1 this round
                      this.CHOSEN_MOVE_CARD_SAUCER_2 = ''; // the move card chosen for Saucer 2 this round
                      this.CHOSEN_DIRECTION_SAUCER_2 = ''; // the direction chosen for Saucer 2 this round

                      // reset the variables that hold give away crewmember choices
                      this.GIVE_AWAY_TYPE = '';
                      this.GIVE_AWAY_COLOR = '';
                      this.GIVE_AWAY_SAUCER = '';

                      // make sure all move cards are not in player hands
                      //this.returnMoveCardToHandOfSaucer(this.gamedatas.saucer1);
                      //if(this.gamedatas.saucer2 != '')
                      //    this.returnMoveCardToHandOfSaucer(this.gamedatas.saucer2);


                  break;

                  case 'chooseAcceleratorDirection':
                    if( this.isCurrentPlayerActive() )
                    { // this is the active player

                    }
                  break;
                  case 'chooseIfYouWillUseBooster':

                    if( this.isCurrentPlayerActive() )
                    { // this is the active player

                    }

                  break;

                  case 'chooseMoveCard':
                    this.hideTurnDirection();

                    if(this.gamedatas.saucer2 == '')
                    { // each player only controls one saucer

                        // select that saucer to save them a click
                        this.selectSaucer(this.gamedatas.saucer1);
                    }
                  break;

                  case 'crashPenaltyAskWhichToGiveAway':



                  break;

                  case 'claimZag':
                      console.log( "onEnteringState->claimZag" );

                  break;

                  case 'chooseZigPhase':
                      console.log( "onEnteringState->chooseZigPhase" );
                  break;

                  case 'executeMove':
                      console.log( "onEnteringState->executeMove" );

                      console.log( "entering args.args.isDizzy="+args.args.isDizzy );


                  break;
                  case 'placeCrewmemberChooseCrewmember':
                  console.log( "onEnteringState->placeCrewmemberChooseCrewmember" );
                  /*
                  var playerIdRespawningGarment = args.args.playerIdRespawningGarment;
                  var playerNameRespawningGarment = args.args.playerNameRespawningGarment;


                  if( this.player_id == playerIdRespawningGarment )
                  { // this player is the one who respawns this garment
                      var activePlayerText = _("You must choose a new garment from the pile to place on the board.");
                      this.setPlayerInstructions(activePlayerText);

                      var garmentsValidForRespawn = args.args.garmentsValidForRespawn;
                      console.log("garmentsValidForRespawn:");
                      console.log(garmentsValidForRespawn);

                      const respawnGarments = Object.keys(garmentsValidForRespawn);
                      for (const respawnGarmentKey of respawnGarments)
                      { // go through each player
                          var garmentColor = garmentsValidForRespawn[respawnGarmentKey]['garmentColor'];
                          var garmentType = garmentsValidForRespawn[respawnGarmentKey]['garmentType'];
                          var htmlIdOfGarment = 'garment_'+garmentType+'_'+garmentColor;
                          dojo.addClass( htmlIdOfGarment, 'highlighted_garment' );
                      }
                  }
                  else
                  {
                      var otherPlayerText = dojo.string.substitute( _("${playerNameReplacing} is placing a new garment."), {
                          playerNameReplacing: playerNameRespawningGarment
                      } );
                      this.setPlayerInstructions(otherPlayerText);
                  }
*/
                  break;

                  case 'replaceGarmentChooseSpace':
                  console.log( "onEnteringState->replaceGarmentChooseSpace" );
                  var playerIdRespawningGarmentSpace = args.args.playerIdRespawningGarment;
                  var playerNameRespawningGarmentSpace = args.args.playerNameRespawningGarment;


                      var validGarmentSpawnSpaces = args.args.validGarmentSpawnSpaces;
                      console.log("validGarmentSpawnSpaces:");
                      console.log(validGarmentSpawnSpaces);

                      const spaces = Object.keys(validGarmentSpawnSpaces);
                      for (const spaceKey of spaces)
                      { // go through each player
                          var x = validGarmentSpawnSpaces[spaceKey]['x'];
                          var y = validGarmentSpawnSpaces[spaceKey]['y'];
                          var htmlIdOfSpace = 'square_'+x+'_'+y;
                          dojo.addClass( htmlIdOfSpace, 'highlighted_square' );
                      }


                  break;

                  case 'askStealOrDraw':
                      console.log( "onEnteringState->askStealOrDraw" );
                      if( this.isCurrentPlayerActive() )
                      { // this is the active player

                      }
                  break;


                  case 'askWhichGarmentToSteal':
                      if( this.isCurrentPlayerActive() )
                      { // this is the active player so they need to discard
                          var stealableGarments = args.args.stealableGarments;
                          console.log("stealableGarments:");
                          console.log(stealableGarments);

                          const garments = Object.keys(stealableGarments);
                          for (const garmentKey of garments)
                          { // go through each player
                              var garmentColor = stealableGarments[garmentKey]['garmentColor'];
                              var garmentType = stealableGarments[garmentKey]['garmentType'];
                              var htmlIdOfGarment = 'crewmember_'+garmentType+'_'+garmentColor;
                              //dojo.addClass( htmlIdOfGarment, 'highlighted_garment' );
                          }
                      }
                  break;

                  case 'askWhichGarmentToDiscard':
                  console.log( "onEnteringState->askWhichGarmentToDiscard" );

                  if( this.isCurrentPlayerActive() )
                  { // this is the active player so they need to discard
                      var discardableGarments = args.args.discardableGarments;
                      console.log("discardableGarments:");
                      console.log(discardableGarments);

                      const discardableGarmentKeys = Object.keys(discardableGarments);
                      for (const discardableGarmentKey of discardableGarmentKeys)
                      { // go through each player
                          var garmentColor = discardableGarments[discardableGarmentKey]['garmentColor'];
                          var garmentType = discardableGarments[discardableGarmentKey]['garmentType'];
                          var htmlIdOfGarment = 'crewmember_'+garmentType+'_'+garmentColor;
                          dojo.addClass( htmlIdOfGarment, 'highlighted_garment' );
                      }
                  }

                  break;

                  default:
//this.unhighlightAllDirections(); // UNhighlight ALL directions
                  break;
            }
        },

        // onLeavingState: this method is called each time we are leaving a game state.
        //                 You can use this method to perform some user interface changes at this moment.
        //
        onLeavingState: function( stateName )
        {
            console.log( 'Leaving state: '+stateName );

            switch( stateName )
            {

            /* Example:

            case 'myGameState':

                // Hide the HTML block we are displaying only during this game state
                dojo.style( 'my_html_block_id', 'display', 'none' );

                break;
           */
             case 'chooseMoveCard':
               if( this.isCurrentPlayerActive() )
               { // this is the active player
                   this.unselectAllSaucers();
                   this.unhighlightAllSaucers();

                   //this.unselectAllMoveCards();
                   this.unhighlightAllMoveCards();

                   this.unselectAllDirections();
                   this.unhighlightAllDirections();

                   this.unhighlightAllSpaces();
               }

             case 'chooseAcceleratorDirection':

               if( this.isCurrentPlayerActive() )
               { // this is the active player
                  this.unhighlightAllDirections();
               }

             break;

             case 'chooseIfYouWillUseBooster':

               if( this.isCurrentPlayerActive() )
               { // this is the active player
                  this.unhighlightAllDirections();
               }

             break;

            case 'executeMove':
            console.log( "onLeavingState->executeMove" );

            //this.ostrichChosen = false; // true when the player selects which ostrich they will move this turn
            break;

            case 'placeCrewmemberChooseCrewmember':
            console.log( "onLeavingState->placeCrewmemberChooseCrewmember" );
this.unhighlightAllGarments();
            break;

            case 'replaceGarmentChooseSpace':
            console.log( "onLeavingState->replaceGarmentChooseGarment" );

            this.unhighlightAllSpaces();
            break;

            case 'askWhichGarmentToSteal':
                console.log( "onLeavingState->askWhichGarmentToSteal" );
                this.unhighlightAllGarments();
            break;

            case 'askWhichGarmentToDiscard':
                console.log( "onLeavingState->askWhichGarmentToDiscard" );
                this.unhighlightAllGarments();
            break;
            }
        },

        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        // To get args, use just args (not args.args like in onEnteringState).
        onUpdateActionButtons: function( stateName, args )
        {
            console.log( 'onUpdateActionButtons: '+stateName );
            console.log( 'IsCurrentPlayerActive():'+this.isCurrentPlayerActive() );

            switch( stateName )
            {
/*
                 Example:

                 case 'myGameState':

                    // Add 3 action buttons in the action status bar:

                    this.addActionButton( 'button_1_id', _('Button 1 label'), 'onMyMethodToCall1' );
                    this.addActionButton( 'button_2_id', _('Button 2 label'), 'onMyMethodToCall2' );
                    this.addActionButton( 'button_3_id', _('Button 3 label'), 'onMyMethodToCall3' );
                    break;
*/                case 'chooseMoveCard':
                      console.log( "onUpdateActionButtons->chooseMoveCard" );

                      if( this.isCurrentPlayerActive() )
                      { // this player is active

                          // create a place to put saucer 1 move selection button
                          var holderDiv = $('generalactions');
                          dojo.place( this.format_block( 'jstpl_actionButtonHolder', {
                              saucerNumber: 1
                          } ) , holderDiv );

                          if(this.gamedatas.saucer2 != '')
                          { // players are controlling 2 saucers

                              // create a place to put saucer 2 move selection button
                              var holderDiv = $('generalactions');
                              dojo.place( this.format_block( 'jstpl_actionButtonHolder', {
                                  saucerNumber: 2
                              } ) , holderDiv );
                          }

                          this.playerSaucerMoves = args.playerSaucerMoves; // save the list of players/saucers/move cards/spaces so it can be used elsewhere
                          this.showChooseMoveCardButtons();

                          if(this.SAUCER_SELECTED == '')
                          { // NO saucer is selected

                              if(!this.hasSaucerChosenMoveAndDirection(this.gamedatas.saucer1))
                              { // this saucer still needs to choose move card or direction
                                  this.highlightSpecificPlayerSaucer(this.gamedatas.saucer1); // highlight it
                              }

                              if(!this.hasSaucerChosenMoveAndDirection(this.gamedatas.saucer2))
                              { // this saucer still needs to choose move card or direction
                                  this.highlightSpecificPlayerSaucer(this.gamedatas.saucer2); // highlight it
                              }
                          }
                          else
                          { // a saucer is selected

                              // select just the saucer selected
                              this.selectSpecificSaucer(this.SAUCER_SELECTED);
                              //this.unhighlightAllSaucers(); // unhighlight all saucers now that they selected one

                              if(this.MOVE_CARD_SELECTED == '')
                              { // NO move card is selected

                                  // highlight the move cards
                                  //this.unhighlightAllMoveCards();
                                  //this.highlightAllMoveCardsForSaucer(this.SAUCER_SELECTED);
                              }
                              else
                              { // a move card is chosen for the selected saucer

                                  // select Move card
                                  this.unhighlightAllMoveCards();
                                  this.selectSpecificMoveCard(this.MOVE_CARD_SELECTED);

                                  // highlight all spaces available
                                  var moveCardSelected = this.MOVE_CARD_SELECTED.split('_')[2]; // 0, 1, 2
                                  this.highlightPossibleMoveSelections(args.playerSaucerMoves, this.player_id, this.SAUCER_SELECTED, moveCardSelected);
                              }
                          }

                          var buttonLabel = "Confirm";
                          var isDisabled = false; // TODO: Update this to be disabled until moves are selected
                          var hoverOverText = "Confirm your moves."; // hover over text or '' if we don't want a hover over
                          var actionName = "confirmMove"; // shoot, useEquipment
                          var makeRed = false;

                          this.addButtonToActionBar(buttonLabel, isDisabled, hoverOverText, actionName, makeRed);


                        //var buttonLabel = playerSaucerMoves[key]['buttonLabel'];
                        //var isDisabled = playerSaucerMoves[key]['isDisabled'];
                        //var hoverOverText = playerSaucerMoves[key]['hoverOverText']; // hover over text or '' if we don't want a hover over
                        //var actionName = playerSaucerMoves[key]['actionName']; // shoot, useEquipment
                        //var makeRed = playerSaucerMoves[key]['makeRed'];

                        //this.addButtonToActionBar(buttonLabel, isDisabled, hoverOverText, actionName, makeRed);
                      }

                  break;

                  case 'beginTurn':
                      if ( this.isCurrentPlayerActive() )
                      { // we are the active player

                          // add a button for starting turn (since this is the undo save point we can't just go straight into the turn)
                          var undoButtonLabel = 'Start Turn';
                          var undoIsDisabled = false;
                          var undoHoverOverText = ''; // hover over text or '' if we don't want a hover over
                          var undoActionName = 'beginTurn'; // such as selectSaucerToGoFirst
                          var undoMakeRed = false;
                          this.addButtonToActionBar(undoButtonLabel, undoIsDisabled, undoHoverOverText, undoActionName, undoMakeRed);
                      }
                  break;

                  case 'finalizeMove':
                  if ( this.isCurrentPlayerActive() )
                  { // we are the active player

                      // add a button for confirming the move
                      var finalizeButtonLabel = 'Confirm';
                      var finalizeIsDisabled = false;
                      var finalizeHoverOverText = ''; // hover over text or '' if we don't want a hover over
                      var finalizeActionName = 'finalizeMove'; // such as selectSaucerToGoFirst
                      var finalizeMakeRed = false;
                      this.addButtonToActionBar(finalizeButtonLabel, finalizeIsDisabled, finalizeHoverOverText, finalizeActionName, finalizeMakeRed);

                      // add a button for undo'ing the move
                      var undoButtonLabel = 'Undo Move';
                      var undoIsDisabled = false;
                      var undoHoverOverText = ''; // hover over text or '' if we don't want a hover over
                      var undoActionName = 'undoMove'; // such as selectSaucerToGoFirst
                      var undoMakeRed = true;
                      this.addButtonToActionBar(undoButtonLabel, undoIsDisabled, undoHoverOverText, undoActionName, undoMakeRed);
                  }

                  break;

                  case 'askWhichStartOfTurnUpgradeToUse':
                      if ( this.isCurrentPlayerActive() )
                      { // we are the active player

                          var startOfTurnUpgradeList = args.startOfTurnUpgradesToActivate;

                          const startOfTurnUpgradeListKeys = Object.keys(startOfTurnUpgradeList);
                          for (const upgradeKey of startOfTurnUpgradeListKeys)
                          { // go through each upgrade we could activate
                              var buttonLabel = startOfTurnUpgradeList[upgradeKey]['buttonLabel'];
                              var buttonId = startOfTurnUpgradeList[upgradeKey]['buttonId'];
                              var isDisabled = startOfTurnUpgradeList[upgradeKey]['isDisabled'];
                              var hoverOverText = startOfTurnUpgradeList[upgradeKey]['hoverOverText']; // hover over text or '' if we don't want a hover over
                              var actionName = startOfTurnUpgradeList[upgradeKey]['actionName']; // such as onClick_activateUpgrade
                              var makeRed = startOfTurnUpgradeList[upgradeKey]['makeRed'];

                              this.addActionButton( buttonId, _(buttonLabel), 'onClick_'+actionName );
                          }

                          // add a skip button in case they do not want to activate an available upgrade
                          var skipActivateUpgradeButtonLabel = 'Skip';
                          var skipActivateUpgradeIsDisabled = false;
                          var skipActivateUpgradeHoverOverText = ''; // hover over text or '' if we don't want a hover over
                          var skipActivateUpgradeActionName = 'skipActivateStartOfTurnUpgrade'; // such as selectSaucerToGoFirst
                          var skipActivateUpgradeMakeRed = true;
                          this.addButtonToActionBar(skipActivateUpgradeButtonLabel, skipActivateUpgradeIsDisabled, skipActivateUpgradeHoverOverText, skipActivateUpgradeActionName, skipActivateUpgradeMakeRed);
                      }
                  break;

                  case 'askWhichEndOfTurnUpgradeToUse':
                      if ( this.isCurrentPlayerActive() )
                      { // we are the active player
                          var endOfTurnUpgradeList = args.endOfTurnUpgradesToActivate;

                          const endOfTurnUpgradeListKeys = Object.keys(endOfTurnUpgradeList);
                          for (const upgradeKey of endOfTurnUpgradeListKeys)
                          { // go through each upgrade we could activate
                              var buttonLabel = endOfTurnUpgradeList[upgradeKey]['buttonLabel'];
                              var buttonId = endOfTurnUpgradeList[upgradeKey]['buttonId'];
                              var isDisabled = endOfTurnUpgradeList[upgradeKey]['isDisabled'];
                              var hoverOverText = endOfTurnUpgradeList[upgradeKey]['hoverOverText']; // hover over text or '' if we don't want a hover over
                              var actionName = endOfTurnUpgradeList[upgradeKey]['actionName']; // such as onClick_activateUpgrade
                              var makeRed = endOfTurnUpgradeList[upgradeKey]['makeRed'];

                              this.addActionButton( buttonId, _(buttonLabel), 'onClick_'+actionName );
                          }

                          // add a skip button in case they do not want to activate an available upgrade
                          var skipActivateUpgradeButtonLabel = 'Skip';
                          var skipActivateUpgradeIsDisabled = false;
                          var skipActivateUpgradeHoverOverText = ''; // hover over text or '' if we don't want a hover over
                          var skipActivateUpgradeActionName = 'skipActivateEndOfTurnUpgrade'; // such as selectSaucerToGoFirst
                          var skipActivateUpgradeMakeRed = true;
                          this.addButtonToActionBar(skipActivateUpgradeButtonLabel, skipActivateUpgradeIsDisabled, skipActivateUpgradeHoverOverText, skipActivateUpgradeActionName, skipActivateUpgradeMakeRed);
                      }
                  break;

                  case 'askWhichUpgradeToPlay':
                      if ( this.isCurrentPlayerActive() )
                      { // we are the active player
                          // create a place to hold upgrade cards
                          //this.placeUpgradeHolders();

                          this.createUpgradeHandStock();


                          var upgradeList = args.upgradeList;
                          console.log('upgrade list:');
                          console.log(upgradeList);

                          var cardIndex = 0;
                          const upgradeListKeys = Object.keys(upgradeList);
                          for (const upgradeKey of upgradeListKeys)
                          { // go through each upgrade we could play
                              var card = upgradeList[upgradeKey];
                              var collectorNumber = card.type_arg;
                              var saucerColor = card.location;
                              var databaseId = card.id;
                              var cardOwner = card.location_arg;
                              cardIndex++;

                              console.log('adding collectorNumber '+ collectorNumber + ' to upgradesAvailable');

                              // just add the upgrade to the upgrades available stock
                              this.upgradesAvailable.addToStockWithId( collectorNumber, databaseId );

                              // put the upgrade card in its holder
                              //this.placeUpgradeCard(collectorNumber, databaseId, cardIndex);
                              //this.addActionButton( 'upgrade_button_'+databaseId, '<div class="saucer saucer_button saucer_color_'+color+'"></div>', 'onClick_saucerButtonClick', null, null, 'gray');
                          }
                      }
                  break;

                  case 'chooseBlastOffThrusterSpace':

                      if( this.isCurrentPlayerActive() )
                      { // this player is active
                            var validSpaces = args.validSpaces;
                            this.highlightAllTheseSpaces(validSpaces);

                            this.addActionButton( 'skipButton_1', _('Skip'), 'onClick_skipActivateSpecificStartOfTurnUpgrade', null, false, 'red' );
                      }

                  break;

                  case 'chooseAfterburnerSpace':

                      if( this.isCurrentPlayerActive() )
                      { // this player is active
                            var validSpaces = args.validSpaces;
                            this.highlightAllTheseSpaces(validSpaces);

                            this.addActionButton( 'skipButton_3', _('Skip'), 'onClick_skipActivateSpecificStartOfTurnUpgrade', null, false, 'red' );
                      }

                  break;

                  case 'chooseTractorBeamCrewmember':

                      if( this.isCurrentPlayerActive() )
                      { // this player is active

                            // add a button for each offcolored crewmember they have
                            this.showTractorBeamCrewmemberButtons(args.validCrewmembers);

                            console.log('valid crew:');
                            console.log(args.validCrewmembers);

                            // add a skip button in case they do not want to for some reason
                            this.addActionButton( 'skipButton', _('Skip'), 'onClick_skipActivateSpecificStartOfTurnUpgrade', null, false, 'red' );
                      }

                  break;

                  case 'chooseLandingLegsSpace':

                      if( this.isCurrentPlayerActive() )
                      { // this player is active
                          var validSpaces = args.validSpaces;
                          this.highlightAllTheseSpaces(validSpaces);

                          this.addActionButton( 'skipButton_17', _('Skip'), 'onClick_skipActivateSpecificEndOfTurnUpgrade', null, false, 'red' );
                      }

                  break;

                  case 'chooseSaucerWormholeGenerator':

                      if( this.isCurrentPlayerActive() )
                      { // this player is active
                          var saucerButtonList = args.otherUncrashedSaucers;

                          const saucerButtonKeys = Object.keys(saucerButtonList);
                          for (const buttonKey of saucerButtonKeys)
                          { // go through each button

                              var color = saucerButtonList[buttonKey]['saucerColor'];
                              var buttonLabel = saucerButtonList[buttonKey]['saucerColorText'];
                              var isDisabled = false;
                              var hoverOverText = ''; // hover over text or '' if we don't want a hover over
                              var actionName = 'saucerButtonClick'; // selectSaucerToGoFirst
                              var makeRed = false;

                              //this.addButtonToActionBar(buttonLabel, isDisabled, hoverOverText, actionName, makeRed);
                              this.addActionButton( 'wormhole_saucer_button_'+color, '<div class="saucer saucer_button saucer_color_'+color+'"></div>', 'onClick_saucerButtonClick', null, null, 'gray');
                          }

                          // add a skip button in case they do not want to activate an available upgrade
                          var skipActivateUpgradeButtonLabel = 'Skip';
                          var skipActivateUpgradeIsDisabled = false;
                          var skipActivateUpgradeHoverOverText = ''; // hover over text or '' if we don't want a hover over
                          var skipActivateUpgradeActionName = 'skipActivateSpecificEndOfTurnUpgrade'; // such as selectSaucerToGoFirst
                          var skipActivateUpgradeMakeRed = true;
                          //this.addButtonToActionBar(skipActivateUpgradeButtonLabel, skipActivateUpgradeIsDisabled, skipActivateUpgradeHoverOverText, skipActivateUpgradeActionName, skipActivateUpgradeMakeRed);
                          this.addActionButton( 'skipButton_2', _('Skip'), 'onClick_skipActivateSpecificEndOfTurnUpgrade', null, false, 'red' );

                      }

                  break;

                  case 'endRoundPlaceCrashedSaucer':
                  case 'askPreTurnToPlaceCrashedSaucer':

                      if( this.isCurrentPlayerActive() )
                      { // this player is active
                          var saucerButton = args.saucerButton;

                          var color = saucerButton['saucerColor'];
                          var buttonLabel = saucerButton['buttonLabel'];
                          var isDisabled = saucerButton['isDisabled'];
                          var hoverOverText = saucerButton['hoverOverText']; // hover over text or '' if we don't want a hover over
                          var actionName = saucerButton['actionName']; // such as selectSaucerToPlace
                          var makeRed = saucerButton['makeRed'];

                          //this.addButtonToActionBar(buttonLabel, isDisabled, hoverOverText, actionName, makeRed);
                          this.addActionButton( 'saucer_button_'+color, '<div class="saucer saucer_button saucer_color_'+color+'"></div>', 'onClick_'+actionName, null, null, 'gray');
                      }
                  break;

                  case 'chooseDirectionAfterPlacement':
                      if( this.isCurrentPlayerActive() )
                      { // this player is active
                          this.showDirectionButtons();
                      }
                  break;

                  case 'allCrashSitesOccupiedChooseSpaceEndRound':
                  case 'allCrashSitesOccupiedChooseSpacePreTurn':

                      if( this.isCurrentPlayerActive() )
                      { // this player is active
                          var validSpaces = args.validPlacements;
                          this.highlightAllTheseSpaces(validSpaces);
                      }

                  break;

                  case 'crashPenaltyAskWhichToSteal':
                      if ( this.isCurrentPlayerActive() )
                      { // we are the active player

                          // add a button for an ENERGY
                          this.showEnergyButton(args.saucerWhoCrashed);

                          // add a button for each offcolored crewmember they have
                          this.showStealableCrewmemberButtons(args.stealableCrewmembers);

                          // add a skip button in case they do not want to for some reason
                          this.addActionButton( 'skipButton', _('Skip'), 'onClick_skipStealCrewmember', null, false, 'red' );
                      }

                  break;

                  case 'crashPenaltyAskWhichToGiveAway':
                      if( this.isCurrentPlayerActive() )
                      { // this is the active player so they need to discard

                          this.showGiveAwayCrewmemberButtons(args.giveAwayCrewmembers, args.otherSaucers);

                          // add a skip button in case they do not want to for some reason
                          this.addActionButton( 'skipButton', _('Skip'), 'onClick_skipGiveAwayCrewmember', null, false, 'red' );
                      }
                  break;

                  case 'chooseCrewmembersToPass':

                      if ( this.isCurrentPlayerActive() )
                      { // we are the active player

                          // add a button for each offcolored crewmember they have
                          this.showPassableCrewmemberButtons(args.passableCrewmembers);

                          // add a skip button in case they do not want to activate an available upgrade
                          this.addActionButton( 'skipButton', _('Skip'), 'onClick_skipPassCrewmember', null, false, 'red' );
                      }
                  break;

                  case 'chooseCrewmembersToTake':

                      if ( this.isCurrentPlayerActive() )
                      { // we are the active player

                          // add a button for each offcolored crewmember they have
                          this.showTakeableCrewmemberButtons(args.takeableCrewmembers);

                          // add a skip button in case they do not want to activate an available upgrade
                          this.addActionButton( 'skipButton', _('Skip'), 'onClick_skipTakeCrewmember', null, false, 'red' );
                      }
                  break;

                  case 'placeCrewmemberChooseCrewmember':
                      if ( this.isCurrentPlayerActive() )
                      { // we are the active player

                          this.showLostCrewmemberButtons(args.lostCrewmembers);
                      }
                  break;

                  case 'chooseWhichSaucerGoesFirst':
                    if( this.isCurrentPlayerActive() )
                    { // this player is active
                        var saucerButtonList = args.saucerButtons;

                        const saucerButtonKeys = Object.keys(saucerButtonList);
                        for (const buttonKey of saucerButtonKeys)
                        { // go through each button

                            var color = saucerButtonList[buttonKey]['saucerColor'];
                            var buttonLabel = saucerButtonList[buttonKey]['buttonLabel'];
                            var isDisabled = saucerButtonList[buttonKey]['isDisabled'];
                            var hoverOverText = saucerButtonList[buttonKey]['hoverOverText']; // hover over text or '' if we don't want a hover over
                            var actionName = saucerButtonList[buttonKey]['actionName']; // selectSaucerToGoFirst
                            var makeRed = saucerButtonList[buttonKey]['makeRed'];


                            //this.addButtonToActionBar(buttonLabel, isDisabled, hoverOverText, actionName, makeRed);
                            this.addActionButton( 'saucer_button_'+color, '<div class="saucer saucer_button saucer_color_'+color+'"></div>', 'onClick_'+actionName, null, null, 'gray');
                        }


                    }
                  break;

                  case 'chooseCrashSiteRegenerationGateway':
                    if ( this.isCurrentPlayerActive() )
                    { // we are the active player

                        console.log('emptyCrashSites:');
                        console.log(args.emptyCrashSites);
                        this.showEmptyCrashSiteButtons(args.emptyCrashSites);
                    }
                  break;

                  case 'chooseCrashSiteSaucerTeleporter':
                    if ( this.isCurrentPlayerActive() )
                    { // we are the active player

                        console.log('emptyCrashSites:');
                        console.log(args.emptyCrashSites);
                        this.showEmptyCrashSiteButtons(args.emptyCrashSites);

                        // add a skip button in case they do not want to activate an available upgrade
                        var skipActivateUpgradeButtonLabel = 'Skip';
                        var skipActivateUpgradeIsDisabled = false;
                        var skipActivateUpgradeHoverOverText = ''; // hover over text or '' if we don't want a hover over
                        var skipActivateUpgradeActionName = 'skipActivateSpecificEndOfTurnUpgrade'; // such as selectSaucerToGoFirst
                        var skipActivateUpgradeMakeRed = true;
                        //this.addButtonToActionBar(skipActivateUpgradeButtonLabel, skipActivateUpgradeIsDisabled, skipActivateUpgradeHoverOverText, skipActivateUpgradeActionName, skipActivateUpgradeMakeRed);
                        this.addActionButton( 'skipButton_6', _('Skip'), 'onClick_skipActivateSpecificEndOfTurnUpgrade', null, false, 'red' );

                    }
                  break;

                  case 'chooseUpgradeSpace':
                    if ( this.isCurrentPlayerActive() )
                    { // we are the active player

                        this.showDirectionButtons();
                    }

                  break;

                  case 'chooseTimeMachineDirection':
                    if ( this.isCurrentPlayerActive() )
                    { // we are the active player

                        this.showDirectionButtons();
                    }

                  break;

                  case 'chooseWhetherToHyperdrive':
                      if ( this.isCurrentPlayerActive() )
                      { // we are the active player
                          // add a button for confirming the move
                          var finalizeButtonLabel = 'Hyperdrive';
                          var finalizeIsDisabled = false;
                          var finalizeHoverOverText = ''; // hover over text or '' if we don't want a hover over
                          var finalizeActionName = 'activateHyperdrive'; // such as selectSaucerToGoFirst
                          var finalizeMakeRed = false;
                          this.addButtonToActionBar(finalizeButtonLabel, finalizeIsDisabled, finalizeHoverOverText, finalizeActionName, finalizeMakeRed);

                          // add a button for undo'ing the move
                          var undoButtonLabel = 'Skip';
                          var undoIsDisabled = false;
                          var undoHoverOverText = ''; // hover over text or '' if we don't want a hover over
                          var undoActionName = 'skipHyperdrive'; // such as selectSaucerToGoFirst
                          var undoMakeRed = true;
                          this.addButtonToActionBar(undoButtonLabel, undoIsDisabled, undoHoverOverText, undoActionName, undoMakeRed);

                      }
                  break;

                  case 'claimZag':
                      console.log( "onUpdateActionButtons->claimZag" );

                      if( this.isCurrentPlayerActive() )
                      { // this player is active (they CAN claim a Zag but haven't said whether they will yet)

                          var allOstriches = args.allOstriches;
                          console.log("allOstriches:")
                          console.log(allOstriches);
                          console.log("my player ID:" + this.player_id);
                          var myOstriches = allOstriches[this.player_id];
                          for( var ostrich in myOstriches )
                          {
                              console.log("my ostrich:" + myOstriches[ostrich]);
                              var ostrichColor = myOstriches[ostrich]; // get color signifier like f6033b
                              var ostrichName = this.getOstrichName(ostrichColor); // convert f6033b into RED

                              var methodName = 'onDiscard3Claim'+ostrichName;
                              var buttonId = 'discard3Claim'+ostrichName+'_button';
                              var buttonText = 'Claim Zag for '+ostrichName;
                              if(myOstriches.length < 2)
                              { // this player only controls a single ostrich
                                  buttonText = 'Claim Zag'; // remove the ostrich color from the button text
                              }
                              this.addActionButton( buttonId, _(buttonText), methodName ); // add a claim button for this ostrich

                              var selectedCards = this.playerHand.getSelectedItems();
                              if(selectedCards.length < 3)
                              { // less than 3 cards are selected
                                    dojo.addClass( buttonId, 'disabled'); //disable the button
                              }

                          }

                          this.addActionButton( 'askClaimZagNo_button', _('Skip'), 'onNoClaimZag' );

                      }
                  break;

                  case 'chooseOstrich':
                  console.log( "onUpdateActionButtons for chooseOstrich" );

                  break;

                  case 'chooseDistanceDuringMoveReveal':
                      console.log( "onUpdateActionButtons for chooseDistanceDuringMoveReveal" );

                      if( this.isCurrentPlayerActive() )
                      { // this is the active player so we need to execute their move

                          // save the current locations of the saucer whose turn it is
                          this.startingXLocation = args.startingXLocation;
                          this.startingYLocation = args.startingYLocation;

                          this.showXValueButtons();

                          // highlight board space with the possible move destinations
                          this.highlightPossibleAcceleratorOrBoostMoveSelections(args.playerSaucerMoves, args.direction);
                      }

                  break;

                  case 'executeMove':
                      console.log( "onUpdateActionButtons for executeMove with isDizzy " + args.isDizzy );
                      console.log( "update args.isDizzy="+args.isDizzy );

                      if( this.isCurrentPlayerActive() )
                      { // this is the active player so we need to execute their move

                          if(args.isDizzy == 1)
                          { // we are dizzy
                            //this.showDirectionChoiceButtons();
                          }
                          else
                          {
                            this.showMoveButton();
                          }
                      }
                  break;

                  case 'askToRespawn':
                      console.log( "onUpdateActionButtons for askToRespawn" );
                      if( this.isCurrentPlayerActive() )
                      { // this is the active player
                          this.showAskToRespawnButtons();
                      }
                  break;

                  case 'askWhichGarmentToDiscard':
                    console.log( "onUpdateActionButtons for askWhichGarmentToDiscard where isCurrentPlayerActive="+this.isCurrentPlayerActive() );


                  break;

                  case 'discardTrapCards':
                      console.log( "onUpdateActionButtons for discardTrapCards where isCurrentPlayerActive="+this.isCurrentPlayerActive() );



                  break;

                  case 'chooseAcceleratorDirection':
                      console.log( "onUpdateActionButtons for chooseAcceleratorDirection with ostrichChosen="+this.ostrichChosen );

                      if( this.isCurrentPlayerActive() )
                      { // this is the active player so they need to discard

                          // show a button in the bar for each direction
                          this.showDirectionButtons();

                          // highlight all spaces available
                          this.highlightPossibleAcceleratorOrBoostMoveSelections(args.playerSaucerAcceleratorMoves);

                          this.highlightAllDirections();
                      }
                  break;

                  case 'chooseIfYouWillUseBooster':
                      console.log( "onUpdateActionButtons for chooseIfYouWillUseBooster" );
                      if( this.isCurrentPlayerActive() )
                      { // this is the active player so they need to discard

                          // show a button in the bar for each direction
                          this.showDirectionButtons();

                          // highlight all spaces available
                          this.highlightPossibleAcceleratorOrBoostMoveSelections(args.playerSaucerAcceleratorMoves);

                          // show a button to skip using a booster
                          this.showAskToUseBoosterButtons();

                          this.highlightAllDirections();
                      }
                  break;

                  case 'askStealOrDraw':
                      console.log( "onUpdateActionButtons for askStealOrDraw" );
                      if( this.isCurrentPlayerActive() )
                      { // this is the active player so they need to discard
                          var allStealableGarments = args.stealableGarments;
                          this.showAskStealOrDrawButtons(allStealableGarments.length); // show the buttons tha task if the player would like to steal or draw
                      }
                  break;

                  case 'askWhichGarmentToSteal':
                      console.log( "onUpdateActionButtons for askWhichGarmentToSteal" );
                      if( this.isCurrentPlayerActive() )
                      { // this is the active player so they need to discard

                      }
                  break;

                  case 'placeCrewmemberChooseCrewmember':
                  console.log( "onUpdateActionButtons for placeCrewmemberChooseCrewmember" );


                  break;

                  case 'endMoveTurn':
                      // game state where a player's turn is ending
                  break;

                  case 'endRoundPhase':
                      // game state where the round is ending
                  break;

            }

        },

        ///////////////////////////////////////////////////
        //// Utility methods

        /*

            Here, you can defines some utility methods that you can use everywhere in your javascript
            script.

        */

        /** Override this function to inject html into log items. This is a built-in BGA method.  */
        /* @Override */
        format_string_recursive : function(log, args) {
//          console.log('format_string_recursive');
            try {
                if (log && args && !args.processed) {
                    args.processed = true;


                    // list of special keys we want to replace with images
                    var keys = ['CREWMEMBERIMAGE'];

                    //console.log("Looking through keys:" + keys);
                    for ( var i in keys) {
                        var key = keys[i];
                        args[key] = this.getTokenDiv(key, args);
//                        console.log('key:'+key);
                    }
                }
            } catch (e) {
                console.error(log,args,"Exception thrown", e.stack);
            }
            return this.inherited(arguments);
        },

        getTokenDiv : function(key, args)
        {
            var token_id = args[key]; // CREWMEMBERIMAGE_engineer_f6033b
            if(!token_id)
              return '';


            var logid = "log" + (this.globalid++) + "_" + token_id.substring(0,3);

            switch (key) {
                case 'CREWMEMBERIMAGE':
                    var crewmemberType = token_id.split('_')[1]; // engineer
                    var crewmemberColor = token_id.split('_')[2]; // f6033b

                    var tokenDiv = this.format_block('jstpl_garment', {
                        "garment_type" : crewmemberType,
                        "color" : crewmemberColor,
                        "size" : "crewmember_small",
                        "small" : "small_"
                    });
                    console.log('getTokenDiv CREWMEMBERIMAGE token_id:'+token_id+' tokenDiv:'+tokenDiv);
                    return tokenDiv;


                default:
                    break;
            }

            return "'" + this.clienttranslate_string(token_id) + "'";
       },

        addButtonToActionBar: function(buttonLabel, isDisabled, hoverOverText, actionName, makeRed)
        {
            var buttonId = 'button_' + buttonLabel; // the html id for this button

            var clickMethod = 'onClick_' + actionName;
            if(makeRed == true)
            { // make this button red
                this.addActionButton( buttonId, _(buttonLabel), clickMethod, null, false, 'red' );
            }
            else
            { // keep this button the default blue
                this.addActionButton( buttonId, _(buttonLabel), clickMethod );
            }

            if (isDisabled == true)
            { // we want to disble this button
               dojo.addClass( buttonId, 'disabled'); // disable the button
            }

            if(hoverOverText && hoverOverText != '')
            { // there is a hover over text we want to add
                this.addTooltip( buttonId, _(hoverOverText), '' ); // add a tooltip to explain why it is disabled or how to use it
            }
        },

        hasSaucerChosenMoveAndDirection: function(saucerColor)
        {

            console.log("hasSaucerChosenMoveAndDirection");
            console.log("CHOSEN_MOVE_CARD_SAUCER_1 " + this.CHOSEN_MOVE_CARD_SAUCER_1);
            console.log("CHOSEN_DIRECTION_SAUCER_1 " + this.CHOSEN_DIRECTION_SAUCER_1);
            console.log("saucerColor " + saucerColor);
            console.log("this.gamedatas.saucer1 " + this.gamedatas.saucer1);
            console.log("CHOSEN_MOVE_CARD_SAUCER_2 " + this.CHOSEN_MOVE_CARD_SAUCER_2);
            console.log("CHOSEN_DIRECTION_SAUCER_2 " + this.CHOSEN_DIRECTION_SAUCER_2);
            console.log("this.gamedatas.saucer2 " + this.gamedatas.saucer2);


            if(saucerColor == '')
            {
              return true;
            }

            if(saucerColor == this.gamedatas.saucer1 && this.CHOSEN_MOVE_CARD_SAUCER_1 != '' && this.CHOSEN_DIRECTION_SAUCER_1 != '')
            {
              return true;
            }

            if(saucerColor == this.gamedatas.saucer2 && this.CHOSEN_MOVE_CARD_SAUCER_2 != '' && this.CHOSEN_DIRECTION_SAUCER_2 != '')
            {
              return true;
            }
console.log("return false");
            return false;
        },

        adjustCrewmemberLocationBasedOnUpgrades: function(matColor, crewmemberTypeString)
        {
            var matLocationHtmlId = 'player_board_saucer_mat_'+crewmemberTypeString+'_'+matColor; // example: player_board_saucer_mat_pilot_01b508
            console.log("ADJUSTING");
            if(this.gamedatas.saucer2 != '')
            { // each player controls two saucers

                // get the index of the saucer (1, 2, 3, 4, etc.)
                var saucerIndex = this.getSaucerIndexForSaucerColor(matColor);


                // determine if this is the first or second saucer for a particular player so we know how much to offset the position of their crew
                if(saucerIndex == 2 || saucerIndex == 4)
                { // this is the second saucer of the two

                    var teammateColor = this.getSaucerColorForIndex(saucerIndex - 1);

                    console.log("returning color "+teammateColor+" for index "+saucerIndex);

                    var numberOfUpgradesOfSaucer1 = Object.keys(this.gamedatas.playedUpgrades[teammateColor]).length;
                    if(numberOfUpgradesOfSaucer1 == 0)
                    {
                        // there is always a spot for at least 1 upgrade
                        numberOfUpgradesOfSaucer1 = 1;
                    }

                    var topOffset = 419;

                    if(crewmemberTypeString == "doctor" || crewmemberTypeString == "scientist")
                    { // second level
                          topOffset = 461;
                    }

                    var styleTop = topOffset+((numberOfUpgradesOfSaucer1 - 1)*(this.upgradecardheight + 4));

                    console.log("mat color ("+matColor+") has teammateColor("+teammateColor+") who has ("+numberOfUpgradesOfSaucer1+") upgrades so we are setting styleTop ("+styleTop+") going on matLocationHtmlId ("+matLocationHtmlId+")");

                    dojo.style(matLocationHtmlId, "top", styleTop+"px");
                }
            }
        },

        convertCrewmemberType: function(crewmemberType)
        {
          console.log("converting crewmemberType " + crewmemberType);
            switch( crewmemberType )
            {
                case "0":
                case "head":
                case "pilot":
                    return "pilot";
                case "1":
                case "body":
                case "engineer":
                    return "engineer";
                case "2":
                case "legs":
                case "doctor":
                    return "doctor";
                case "3":
                case "feet":
                case "scientist":
                    return "scientist";
            }
        },

        convertUsedStringToInt: function(usedAsString)
        {
            switch( usedAsString )
            {
                  case "unused":
                      return 0;
                  case "used":
                      return 1;
            }
        },

        getOstrichName: function(ostrich)
        {
            switch( ostrich )
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

            return "";
        },

        unhighlightAllSpaces: function()
        {
            dojo.query( '.highlighted_square' ).removeClass( 'highlighted_square' ); // remove highlights from all spaces (white)
            dojo.query( '.spaceHighlighted' ).removeClass( 'spaceHighlighted' ); // remove highlights from all spaces (green)
            this.unhighlightAllSpaceClicks(); // we do not want any board spaces to be clickable
        },

        unhighlightAllGarments: function()
        {
          dojo.query( '.highlighted_garment' ).removeClass( 'highlighted_garment' ); // remove highlights from all garments
        },



        selectSpecificSaucer: function(color)
        {
            this.unselectAllSaucers(); // unselect all saucers that may have been selected previously
            var htmlIdSaucer = "saucer_"+color;
            dojo.removeClass( htmlIdSaucer, 'saucerHighlighted' ); // unhighlight it
            dojo.addClass( htmlIdSaucer, 'saucerSelected' ); // select it

            var htmlIdOfSaucerButton = 'saucer_'+color+'_button'; // saucer_01b508_button
            dojo.addClass( htmlIdOfSaucerButton, 'saucerSelected' ); // select it
        },

        unhighlightAllSaucers: function()
        {
          dojo.query( '.saucer' ).removeClass( 'saucerHighlighted' );
        },

        unselectAllSaucers: function()
        {
          dojo.query( '.saucerSelected' ).removeClass( 'saucerSelected' );
        },

        highlightAllPlayerSaucers: function(playerId)
        {
            var htmlIdSaucer1 = "saucer_"+this.gamedatas.saucer1;
            dojo.removeClass( htmlIdSaucer1, 'saucerSelected' ); // unselect it
            dojo.addClass( htmlIdSaucer1, 'saucerHighlighted' ); // highlight it
            dojo.connect( $(htmlIdSaucer1), 'onclick', this, 'onClick_saucerDuringMoveCardSelection' ); // attached our saucer tokens to this onclick handler

            var htmlIdSaucer2 = "saucer_"+this.gamedatas.saucer2;
            if(document.getElementById(htmlIdSaucer2))
            { // this component exists
                dojo.removeClass( htmlIdSaucer2, 'saucerSelected' ); // unselect it
                dojo.addClass( htmlIdSaucer2, 'saucerHighlighted' ); // highlight it
                dojo.connect( $(htmlIdSaucer2), 'onclick', this, 'onClick_saucerDuringMoveCardSelection' ); // attached our saucer tokens to this onclick handler
            }

        },

        highlightPlayerSaucersWhoHaveNotChosen: function(playerId)
        {
            if(!this.hasSaucerChosenMoveAndDirection(this.gamedatas.saucer1))
            { // saucer 1 has not chosen their move and direction
                this.highlightSpecificPlayerSaucer(this.gamedatas.saucer1);
            }

            if(!this.hasSaucerChosenMoveAndDirection(this.gamedatas.saucer2))
            { // saucer 2 has not chosen their move and direction
                this.highlightSpecificPlayerSaucer(this.gamedatas.saucer2);
            }
        },

        highlightSpecificPlayerSaucer: function(saucer)
        {
            console.log("highlightSpecificPlayerSaucer");
            var htmlIdSaucer = "saucer_"+saucer;
            if(document.getElementById(htmlIdSaucer))
            { // this component exists
                dojo.removeClass( htmlIdSaucer, 'saucerSelected' ); // unselect it
                dojo.addClass( htmlIdSaucer, 'saucerHighlighted' ); // highlight it

                if(true)
                { // we are in the move card state
                    dojo.connect( $(htmlIdSaucer), 'onclick', this, 'onClick_saucerDuringMoveCardSelection' ); // attached our saucer tokens to this onclick handler
                }
            }
        },

        highlightDirectionsForSaucer: function(color)
        {
            this.unhighlightAllDirections();
            if(this.gamedatas.saucer1 == color)
            { // we are selecting for saucer 1

                if(this.CHOSEN_DIRECTION_SAUCER_1 != '')
                { // we have previously selected a direction
                    this.selectSpecificDirection(this.CHOSEN_DIRECTION_SAUCER_1);
                }
                else
                { // we have not yet selected a direction
                    //this.highlightAllDirections();
                }
            }
            else
            { // we are selecting for saucer 2
                if(this.CHOSEN_DIRECTION_SAUCER_2 != '')
                { // we have previously selected a direction
                    this.selectSpecificDirection(this.CHOSEN_DIRECTION_SAUCER_2);
                }
                else
                { // we have not yet selected a direction
                    //this.highlightAllDirections();
                }
            }

        },

        chooseAcceleratorDirection: function(direction)
        {
            this.ajaxcall( "/crashandgrab/crashandgrab/actClickedAcceleratorDirection.html", {
                                                                        direction: direction,
                                                                        lock: true
                                                                     },
                             this, function( result ) {

                                // What to do after the server call if it succeeded
                                // (most of the time: nothing)



                             }, function( is_error) {

                                // What to do after the server call in anyway (success or failure)
                                // (most of the time: nothing)

            } );
        },

        chooseMoveCardDirection: function(direction, saucerNumber)
        {
          var htmlIdOfDirectionToken = 'direction_'+direction;
          var htmlIdOfDirectionButton = direction+'_'+saucerNumber+'_button'; // asteroids_1_button
          if(this.SAUCER_SELECTED == '')
          { // no saucer is selected so it doesn't make sense to select a directions
              console.log("no saucer is selected");
              return;
          }

/*
          if(this.MOVE_CARD_SELECTED == '')
          { // no move card is selected so it doesn't make sense to select a directions
              console.log("no move card is selected");
              return;
          }
*/
          var saucerColor = this.gamedatas.saucer1;
          if(saucerNumber == 2)
          {
              saucerColor = this.gamedatas.saucer2;
          }

          if(saucerColor != this.SAUCER_SELECTED)
          { // no saucer is selected or a different saucer is selected
//                        console.log( "The saucer belonging to this card is not selected." );
//                        return;

                // select this saucer instead
                this.selectSaucer(saucerColor);
          }

          this.saveDirectionSelection(this.SAUCER_SELECTED, htmlIdOfDirectionToken);

          //this.highlightAllDirections(); // highlight all the directions because people can still change them
          this.unhighlightAllDirections(); // UNhighlight ALL directions
          this.selectSpecificDirection(htmlIdOfDirectionToken); // select this token

          this.highlightPlayerSaucersWhoHaveNotChosen(); // highlight this player's saucers that haven't chosen yet

          // set the available spaces (use the existing method with a new optional paramter for direction)
          var moveCardSelectedDistance = this.MOVE_CARD_SELECTED.split('_')[2]; // 0, 1, 2
          this.highlightPossibleMoveSelections(this.playerSaucerMoves, this.player_id, this.SAUCER_SELECTED, moveCardSelectedDistance, direction); // highlight possible destinations on board

          if(saucerNumber == '1')
          {
              //this.CHOSEN_DIRECTION_SAUCER_1 = 'direction_'+this.LEFT_DIRECTION;

              // remove all distance button highlights
              dojo.query( '.saucer1DirectionButtonSelected' ).removeClass( 'saucer1DirectionButtonSelected' );

              // highlight this distance button
              dojo.addClass( htmlIdOfDirectionButton, 'saucer1DirectionButtonSelected' );
          }
          else
          {
              //this.CHOSEN_DIRECTION_SAUCER_2 = 'direction_'+this.LEFT_DIRECTION;

              // remove all distance button highlights
              dojo.query( '.saucer2DirectionButtonSelected' ).removeClass( 'saucer2DirectionButtonSelected' );

              // highlight this distance button
              dojo.addClass( htmlIdOfDirectionButton, 'saucer2DirectionButtonSelected' );
          }


          // move the selected move card to its spot on the ship mat if it's not already there
          if( $(this.MOVE_CARD_SELECTED) )
          { // this card exists
              this.placeMoveCard(this.SAUCER_SELECTED, moveCardSelectedDistance, direction, true);
          }
        },

        unhighlightAllSpaceClicks: function()
        {
            dojo.query( '.space' ).removeClass( 'spaceClick_sun' );
            dojo.query( '.space' ).removeClass( 'spaceClick_meteor' );
            dojo.query( '.space' ).removeClass( 'spaceClick_constellation' );
            dojo.query( '.space' ).removeClass( 'spaceClick_asteroids' );
        },

        selectSpecificDirection: function(directionAsHtmlId)
        {
            console.log('selectSpecificDirection directionAsHtmlId:'+directionAsHtmlId);
            this.unselectAllDirections();
            dojo.removeClass( directionAsHtmlId, 'directionHighlighted' ); // unhighlight it
            dojo.addClass( directionAsHtmlId, 'directionSelected' ); // select it
        },

        unselectAllDirections: function()
        {
            dojo.query( '.direction_token' ).removeClass( 'directionSelected' );
        },

        highlightAllDirections: function()
        {
            console.log('highlightAllDirections');
            //dojo.addClass( 'direction_sun', 'directionHighlighted' );
            //dojo.addClass( 'direction_asteroids', 'directionHighlighted' );
            //dojo.addClass( 'direction_meteor', 'directionHighlighted' );
            //dojo.addClass( 'direction_constellation', 'directionHighlighted' );

            dojo.query( '.direction_token' ).addClass( 'directionHighlighted' );

            this.unselectAllDirections();
        },

        unhighlightAllDirections: function()
        {
            console.log('unhighlightAllDirections');

            dojo.query( '.direction_token' ).removeClass( 'directionHighlighted' );

            //dojo.removeClass( 'direction_sun', 'directionHighlighted' );
            //dojo.removeClass( 'direction_asteroids', 'directionHighlighted' );
            //dojo.removeClass( 'direction_meteor', 'directionHighlighted' );
            //dojo.removeClass( 'direction_constellation', 'directionHighlighted' );
        },

        highlightSpacesForSelectedSaucer: function(color)
        {
            this.unhighlightAllSpaces(); // unhighlight all board move spaces
            if(this.gamedatas.saucer1 == color)
            { // we are selecting for saucer 1

                if(this.CHOSEN_MOVE_CARD_SAUCER_1 != '')
                {
                    var chosenDirectionSolo = this.CHOSEN_DIRECTION_SAUCER_1.split('_')[1]; // asteroids
                    var moveCardSelected = this.CHOSEN_MOVE_CARD_SAUCER_1.split('_')[2]; // 0, 1, 2
                    this.highlightPossibleMoveSelections(this.playerSaucerMoves, this.player_id, color, moveCardSelected, chosenDirectionSolo);
                }
            }
            else
            { // we are selecting for saucer 2
                if(this.CHOSEN_MOVE_CARD_SAUCER_2 != '')
                {
                    var chosenDirectionSolo = this.CHOSEN_DIRECTION_SAUCER_2.split('_')[1]; // asteroids
                    var moveCardSelected = this.CHOSEN_MOVE_CARD_SAUCER_2.split('_')[2]; // 0, 1, 2
                    this.highlightPossibleMoveSelections(this.playerSaucerMoves, this.player_id, color, moveCardSelected, chosenDirectionSolo);
                }
            }
        },

        selectSelectedDirection: function(color)
        {
console.log("selectSelectedDirection of color: "+color);
            this.unselectAllDirections(); // unselect all other directions so we can select a different one

            if(this.gamedatas.saucer1 == color)
            { // we are selecting a direction for saucer1
                if(this.CHOSEN_DIRECTION_SAUCER_1 != '')
                { // this saucer has a previously selected direction

                        this.selectSpecificDirection(this.CHOSEN_DIRECTION_SAUCER_1);

                }
            }
            else
            { // we are selecting a direction for saucer2
                if(this.CHOSEN_DIRECTION_SAUCER_2 != '')
                { // this saucer has a previously selected direction

                        this.selectSpecificDirection(this.CHOSEN_DIRECTION_SAUCER_2);

                }
            }
        },

        selectSelectedMoveCard: function(color)
        {
console.log("selectSelectedMoveCard of color: "+color);
            this.unselectAllMoveCards(); // unselect all other move cards so we can select a different one

            if(this.gamedatas.saucer1 == color)
            { // we are selecting a move card in saucer 1's hand
                if(this.CHOSEN_MOVE_CARD_SAUCER_1 != '')
                { // this saucer has a previously selected move card
                    //if(this.CHOSEN_DIRECTION_SAUCER_1 == '')
                    //{ // it doesn't yet have a chosen direction (it's still in hand, not on player board... cause it's weird for a card on player board to be selected)
                        this.selectSpecificMoveCard(this.CHOSEN_MOVE_CARD_SAUCER_1);
                    //}
                }
            }
            else
            { // we are selecting a move card in saucer 2's hand
                if(this.CHOSEN_MOVE_CARD_SAUCER_2 != '')
                { // this saucer has a previously selected move card
                    //if(this.CHOSEN_DIRECTION_SAUCER_2 == '')
                    //{ // it doesn't yet have a chosen direction (it's still in hand, not on player board... cause it's weird for a card on player board to be selected)
                        this.selectSpecificMoveCard(this.CHOSEN_MOVE_CARD_SAUCER_2);
                    //}
                }
            }
        },

        unselectSpecificMoveCard: function(htmlIdOfCard)
        {
            this.MOVE_CARD_SELECTED = '';
            dojo.removeClass( htmlIdOfCard, 'moveCardSelected' ); // remove a CSS class from this element
        },

        returnMoveCardToHandOfSaucer: function(color)
        {
            if(color == this.gamedatas.saucer1 || color == this.gamedatas.saucer2)
            { // this is my saucer
                this.moveChosenMoveCardBackToHand(color, 0);
                this.moveChosenMoveCardBackToHand(color, 1);
                this.moveChosenMoveCardBackToHand(color, 2);

            }
            else
            { // this is an opponent saucer

                // destroy 2 card if it exists
                var twoId = 'move_card_1_'+color;
                console.log('considering destroying '+twoId);
                if(document.getElementById(twoId))
                {
                    console.log('destroying '+twoId);
                    dojo.destroy(twoId);
                }

                // destroy 3 card if it exists
                var threeId = 'move_card_2_'+color;
                console.log('considering destroying '+threeId);
                if(document.getElementById(threeId))
                {
                    console.log('destroying '+threeId);
                    dojo.destroy(threeId);
                }

                // destroy X if it exists
                var xId = 'move_card_0_'+color;
                console.log('considering destroying '+xId);
                if(document.getElementById(xId))
                {
                    console.log('destroying '+xId);
                    dojo.destroy(xId);
                }
            }

/*
            if(color == this.gamedatas.saucer1)
            {
                this.CHOSEN_DIRECTION_SAUCER_1 = '';
            }

            if(color == this.gamedatas.saucer2)
            {
                this.CHOSEN_DIRECTION_SAUCER_2 = '';
            }
*/
        },

        moveChosenMoveCardBackToHand: function(color, distanceType)
        {
            var htmlId = "move_card_"+ distanceType + "_"+color;
            var destination = "move_card_holder_" + distanceType + "_"+color;
            if(document.getElementById(htmlId))
            { // this component exists
                console.log('Move card FROM ' + htmlId + ' to player_board_move_card_holder_' + destination + '.');
                //this.placeOnObject( 'cardontable_'+player_id, 'myhand_item_'+card_id ); // teleport card FROM, TO

                this.attachToNewParent( htmlId, destination ); // needed so it doesn't slide under the player board
                this.slideToObject( htmlId, destination ).play(); // slide card FROM, TO
                this.rotateTo(htmlId, 0); // unrotate it
                dojo.connect( $(htmlId), 'onclick', this, 'onClick_moveCard' ); // attached our saucer tokens to this onclick handler (must do it after attaching to new parent)

                this.unselectSpecificMoveCard(htmlId);
                //this.unselectAllDirections();
                //this.highlightAllDirections();
            }
        },

        removeClickableFromAllDirectionTokens: function()
        {
            dojo.query( '.direction_token' ).removeClass( 'clickable' );
        },

        makeAllDirectionTokensClickable: function()
        {
            dojo.query( '.direction_token' ).addClass( 'clickable' );
        },

        removeClickableFromAllMoveCards: function()
        {
            dojo.query( '.move_card' ).removeClass( 'clickable' );
        },

        makeMoveCardsForSaucerClickable: function(color)
        {
            this.removeClickableFromAllMoveCards();

            var htmlId0 = "move_card_0_"+color;
            if(document.getElementById(htmlId0))
            { // this component exists

                    dojo.addClass( htmlId0, 'clickable' );
            }

            var htmlId1 = "move_card_1_"+color;
            if(document.getElementById(htmlId1))
            { // this component exists

                    dojo.addClass( htmlId1, 'clickable' );
            }

            var htmlId2 = "move_card_2_"+color;
            if(document.getElementById(htmlId2))
            { // this component exists

                    dojo.addClass( htmlId2, 'clickable' );
            }
        },

        highlightAllMoveCardsForSaucer: function(color)
        {
            this.unhighlightAllMoveCards(); // remove highlights from all

            var htmlId0 = "move_card_0_"+color;
            if(document.getElementById(htmlId0))
            { // this component exists
                if(this.CHOSEN_MOVE_CARD_SAUCER_1 != htmlId0 && this.CHOSEN_MOVE_CARD_SAUCER_2 != htmlId0)
                { // it's not already chosen
                    dojo.addClass( htmlId0, 'moveCardHighlighted' ); // highlight it
                }
            }

            var htmlId1 = "move_card_1_"+color;
            if(document.getElementById(htmlId1))
            { // this component exists
                if(this.CHOSEN_MOVE_CARD_SAUCER_1 != htmlId1 && this.CHOSEN_MOVE_CARD_SAUCER_2 != htmlId1)
                { // it's not already chosen
                    dojo.addClass( htmlId1, 'moveCardHighlighted' ); // highlight it
                }
            }

            var htmlId2 = "move_card_2_"+color;
            if(document.getElementById(htmlId2))
            { // this component exists
                if(this.CHOSEN_MOVE_CARD_SAUCER_1 != htmlId2 && this.CHOSEN_MOVE_CARD_SAUCER_2 != htmlId2)
                { // it's not already chosen
                    dojo.addClass( htmlId2, 'moveCardHighlighted' ); // highlight it
                }
            }
        },

        unhighlightAllMoveCards: function()
        {
            console.log("unhighlightAllMoveCards");
            dojo.query( '.move_card' ).removeClass( 'moveCardHighlighted' ); // remove highlights from all
        },

        unselectAllMoveCards: function()
        {
            dojo.query( '.move_card' ).removeClass( 'moveCardSelected' ); // remove highlights from all
        },

        unhighlightSpecificMoveCard: function(htmlIdOfCard)
        {
            console.log('unhighlightSpecificMoveCard htmlIdOfCard:'+htmlIdOfCard);
            dojo.removeClass( htmlIdOfCard, 'moveCardHighlighted' ); // remove a CSS class from this element
        },

        selectSpecificMoveCard: function(htmlIdOfCard)
        {
            this.unhighlightSpecificMoveCard(htmlIdOfCard); // remove highlighting if it has any
            dojo.addClass( htmlIdOfCard, 'moveCardSelected' ); // give this card a new CSS class
        },

        highlightSpace: function(htmlOfSpace)
        {
            //console.log('highlighting:'+htmlOfSpace);
            dojo.addClass( htmlOfSpace, 'spaceHighlighted' ); // give this card a new CSS class
        },

        addDirectionToSpace: function(htmlOfSpace, direction)
        {
            console.log('addDirectionToSpace htmlOfSpace:'+htmlOfSpace+' direction:'+direction);

            // give this card a new CSS class corresponding to the direction so we know which direction was chosen if it is clicked
            dojo.addClass( htmlOfSpace, 'spaceClick_'+direction );
        },

        highlightAllTheseSpaces: function(validSpaces)
        {
            this.unhighlightAllSpaces();
            //var countOfSpaces = count(validSpaces);
            //console.log("count spaces to highlight: " + countOfSpaces);

            for (const space of validSpaces)
            { // go through each space

                var htmlOfSpace = 'square_'+space; // square_6_5
                console.log("highlighting space: " + htmlOfSpace);
                this.highlightSpace(htmlOfSpace);

                // hook this space up to the right onClick event
            }
        },

        highlightPossibleAcceleratorOrBoostMoveSelections: function(playerSaucerMoves, direction='')
        {
            //console.log("highlightPossibleAcceleratorOrBoostMoveSelections with player "+playerId+" saucer "+saucerSelected+" move card "+ moveCardSelected + " direction " + direction);
            this.unhighlightAllSpaces();

            const playerKeys = Object.keys(playerSaucerMoves);

            for (const playerKey of playerKeys)
            { // go through each player

                    const saucerKeys = Object.keys(playerSaucerMoves[playerKey]);
console.log("saucerKeys:");
console.log(saucerKeys);
                    for (const saucerKey of saucerKeys)
                    { // go through each saucer (color)



                            const moveCardKeys = Object.keys(playerSaucerMoves[playerKey][saucerKey]);
console.log("moveCardKeys:");
console.log(moveCardKeys);

                            for (const moveCardKey of moveCardKeys)
                            { // go through each available move card for this saucer



                                    const directionKeys = Object.keys(playerSaucerMoves[playerKey][saucerKey][moveCardKey]);
                                    for (const directionKey of directionKeys)
                                    { // go through each direction for this move card

console.log("directionKey is " + directionKey + " and direction is " + direction);
                                        if(direction == '' || direction == directionKey)
                                        {

                                            var spaces = playerSaucerMoves[playerKey][saucerKey][moveCardKey][directionKey]; // array of spaces like 8_7, 3_4
console.log("spaces:");
console.log(spaces);
                                            for (const space of spaces)
                                            { // go through each direction for this move card

                                                console.log("for player " + playerKey+" saucer " + saucerKey + " move card " + moveCardKey + " direction " + directionKey + " we found a valid space of " + space);
                                                var htmlOfSpace = 'square_'+space; // square_6_5

                                                this.addDirectionToSpace(htmlOfSpace, directionKey);
                                                this.highlightSpace(htmlOfSpace);

                                            }
                                        }
                                    }

                            }

                    }

            }
        },

        highlightPossibleMoveSelections: function(playerSaucerMoves, playerId, saucerSelected, moveCardSelected, direction='')
        {
            console.log("highlightPossibleMoveSelections with player "+playerId+" saucer "+saucerSelected+" move card "+ moveCardSelected + " direction " + direction);
            this.unhighlightAllSpaces();

            const playerKeys = Object.keys(playerSaucerMoves);

            for (const playerKey of playerKeys)
            { // go through each player
                if(playerKey == playerId)
                { // these are the saucers for this player
                    const saucerKeys = Object.keys(playerSaucerMoves[playerKey]);
console.log("saucerKeys:");
console.log(saucerKeys);
                    for (const saucerKey of saucerKeys)
                    { // go through each saucer (color)
console.log("comparing saucerKey " + saucerKey + " to saucerSelected " + saucerSelected);
                        if(saucerKey == saucerSelected)
                        { // this is the saucer that is currently selected

                            const moveCardKeys = Object.keys(playerSaucerMoves[playerKey][saucerKey]);
console.log("moveCardKeys:");
console.log(moveCardKeys);
                            for (const moveCardKey of moveCardKeys)
                            { // go through each available move card for this saucer

console.log("comparing moveCardKey " + moveCardKey + " to moveCardSelected " + moveCardSelected);
                                if(moveCardKey == moveCardSelected)
                                { // this is the move card currently selected

                                    const directionKeys = Object.keys(playerSaucerMoves[playerKey][saucerKey][moveCardKey]);
                                    for (const directionKey of directionKeys)
                                    { // go through each direction for this move card

console.log("directionKey is " + directionKey + " and direction is " + direction);
                                        if(direction == '' || direction == directionKey)
                                        {

                                            var spaces = playerSaucerMoves[playerKey][saucerKey][moveCardKey][directionKey]; // array of spaces like 8_7, 3_4
                                            for (const space of spaces)
                                            { // go through each direction for this move card

                                                console.log("for player " + playerKey+" saucer " + saucerKey + " move card " + moveCardKey + " direction " + directionKey + " we found a valid space of " + space);
                                                var htmlOfSpace = 'square_'+space; // square_6_5

                                                this.addDirectionToSpace(htmlOfSpace, directionKey);
                                                this.highlightSpace(htmlOfSpace);

                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        },

        animateEvents: function(eventStack)
        {
            var nextEvent = eventStack.pop();
            var skipAnimation = false;
            console.log('nextEvent:'+nextEvent);
            if(nextEvent)
            {
                // until we have a reason to skip the animation for this event, let's do it
                skipAnimation = false;

                // default source and destination to event type saucerMove
                var eventType = nextEvent['event_type']; // saucerMove

                var saucerColor = 'unknown';
                var source = 'unknown';
                var destination = 'unknown';
                var crewmemberType = 'unknown';
                var animationSpeed = this.ANIMATION_SPEED_MOVING_SAUCER;

                console.log("eventType: " + eventType);


                if(eventType == 'saucerMove')
                { // the saucer picked up a crewmember
                    var saucerMoving = nextEvent['saucer_moving']; // ff0000
                    var destinationX = nextEvent['destination_X']; // 5
                    var destinationY = nextEvent['destination_Y']; // 7

                    source = 'saucer_'+saucerMoving;
                    destination = 'square_'+destinationX+'_'+destinationY;

                    // give it a new parent so it's no longer on the previous space
                    this.attachToNewParent(source, destination);

                    animationSpeed = this.ANIMATION_SPEED_MOVING_SAUCER;
                }
                else if(eventType == 'crewmemberPickup')
                { // the saucer picked up a crewmember
                    saucerColor = nextEvent['saucer_moving']; // ff0000
                    var crewmemberColor = nextEvent['crewmember_color']; // ff0000

                    crewmemberType = nextEvent['crewmember_type']; // pilot, engineer
                    source = 'crewmember_'+crewmemberType+'_'+crewmemberColor;

                    var convertedType = this.convertCrewmemberType(crewmemberType); // temp until i fix this weird issue switching these
                    destination = 'player_board_saucer_mat_'+convertedType+'_'+saucerColor; // player_board_saucer_mat_pilot_0090ff

                    // give it a new parent so it's no longer on the space
                    this.attachToNewParent(source, destination);

                    // give it a played class so it's rotated correctly
                    dojo.addClass(source, 'played_'+crewmemberType);

                    animationSpeed = this.ANIMATION_SPEED_CREWMEMBER_PICKUP;
                }
                else if(eventType == 'saucerCrashed')
                { // the saucer crashed

                    // do not animate this
                    skipAnimation = true;
                }
                else if(eventType == 'movedOntoAccelerator')
                { // the saucer moved onto an accelerator

                    // TODO: add a sparkle or pulse or something when it goes over it

                    // do not animate this
                    skipAnimation = true;
                }
                else if(eventType == 'pushedOntoAccelerator')
                { // the saucer

                    // TODO: add a sparkle or pulse or something when it goes over it

                    // do not animate this
                    skipAnimation = true;
                }
                else if(eventType == 'saucerPush')
                { // the saucer

                    // do not animate this
                    skipAnimation = true;
                }

                console.log("event eventType: " + eventType + " source: " + source + " destination: " + destination);

                if(skipAnimation)
                { // we do not need to animation this particular event

                    this.animateEvents(eventStack); // recursively call for the next event
                }
                else
                { // we haven't decided to skip the animation for this event
                    var animationId = this.slideToObject( source, destination, animationSpeed );
                    dojo.connect(animationId, 'onEnd', () => {

                        console.log('animating '+eventType);

                        this.animateEvents(eventStack); // recursively call for the next event

                        // we have to adjust the location of the crewmember based on the number of upgrades a saucer above them has
                        if(eventType == 'crewmemberPickup')
                        {

                            // in 2-player games, we must adjust the location of crewmembers because
                            // they get pushed down by the number of upgrades their teammat has
                            this.adjustCrewmemberLocationBasedOnUpgrades(saucerColor, crewmemberType);
                        }


                    });
                    animationId.play();
                }


            }
        },

        updateTurnOrder: function(turnOrder)
        {
            var x = 0;
            var y = 0;

            if( turnOrder == 1)
            { // we are going clockwise
                x=35;
                console.log("COUNTER-CLOCKWISE");
            }
            else if(turnOrder == 0) {
                x=0;
                console.log("CLOCKWISE");
            }
            else
            {
              x=70;
              console.log("DO NOT SHOW");
            }

            for( var player_id in this.gamedatas.players )
            {
                var player = this.gamedatas.players[player_id];
                console.log("turnOrder arrow player:"+player_id);
                this.setTurnDirectionArrow(x, y, player_id); // we don't want to show the turn direction arrow until it has been chosen for this round
            }
        },


        doCardsMatch: function(cards)
        {
            var onesInHand = 0;
            var twosInHand = 0;
            var threesInHand = 0;
            var xInHand = 0;
            for( var i in cards )
            {
                var cardType = cards[i].type;
                if(cardType == 0 || cardType == 4)
                    xInHand++;
                else if(cardType == 1 || cardType == 5)
                    onesInHand++;
                else if(cardType == 2 || cardType == 6)
                    twosInHand++;
                else if(cardType == 3 || cardType == 7)
                    threesInHand++;
            }
            //console.log("Ones: " + onesInHand + " Twos: " + twosInHand + " Threes: " + threesInHand + " Xs: " + xInHand);

            var countOnes = onesInHand + xInHand;
            var countTwos = twosInHand + xInHand;
            var countThrees = threesInHand + xInHand;
            //console.log("Ones: " + countOnes + " Twos: " + countTwos + " Threes: " + countThrees);
            if(countOnes > 2 || countTwos > 2 || countThrees > 2)
            { // we have matching cards enough to claim a zag
                return true;
            }
            else
            {
                return false;
            }
        },

        setPlayerInstructions: function(text) {
                    var main = $('pagemaintitletext');
                    main.innerHTML = text; // make sure text is translated before it is sent to this function
        },

        resetPlanPhaseVariables: function() {
            this.playedCardThisTurn = false;
            this.choseDirectionThisTurn = false;
        },

        //resetSetTrapPhaseVariables: function() {
        //    this.finishedTrapping = false;
        //},

        resetMovePhaseVariables: function() {
            this.ostrichChosen = false;
            this.mustSkateboard = false;
        },

        createUpgradeHandStock: function()
        {

            // insert an upgrade card holder div into the message area
            var holderDiv = $('generalactions');
            dojo.place( this.format_block( 'jstpl_upgradeCardHolder', {
                saucerNumber: 1
            } ) , holderDiv );
            var upgradeHolderHtmlId = 'upgradeCardHolder';

            this.upgradesAvailable = new ebg.stock();
            this.upgradesAvailable.create( this, $(upgradeHolderHtmlId), this.upgradecardwidth, this.upgradecardheight );
            this.upgradesAvailable.image_items_per_row = 4; // the number of card images per row in the sprite image
            this.upgradesAvailable.onItemCreate = dojo.hitch( this, 'setupNewCard' ); // add text to the card image

            // we are connecting onClickUpgradeCardInHand to each card in setupNewCard
            //dojo.connect( this.upgradesAvailable, 'onChangeSelection', this, 'onUpgradeHandSelectionChanged' ); // when the onChangeSelection event is triggered on the HTML, call our callback function onTrapHandSelectionChanged below

            this.upgradesAvailable.addItemType( 0, 0, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 0 );
            this.upgradesAvailable.addItemType( 1, 1, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 1 );
            this.upgradesAvailable.addItemType( 2, 2, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 2 );
            this.upgradesAvailable.addItemType( 3, 3, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 3 );
            this.upgradesAvailable.addItemType( 4, 4, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 4 );
            this.upgradesAvailable.addItemType( 5, 5, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 5 );
            this.upgradesAvailable.addItemType( 6, 6, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 6 );
            this.upgradesAvailable.addItemType( 7, 7, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 7 );
            this.upgradesAvailable.addItemType( 8, 8, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 8 );
            this.upgradesAvailable.addItemType( 9, 9, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 9 );
            this.upgradesAvailable.addItemType( 10, 10, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 10 );
            this.upgradesAvailable.addItemType( 11, 11, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 11 );
            this.upgradesAvailable.addItemType( 12, 12, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 12 );
            this.upgradesAvailable.addItemType( 13, 13, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 13 );
            this.upgradesAvailable.addItemType( 14, 14, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 14 );
            this.upgradesAvailable.addItemType( 15, 15, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 15 );
            this.upgradesAvailable.addItemType( 16, 16, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 16 );
            this.upgradesAvailable.addItemType( 17, 17, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 17 );
            this.upgradesAvailable.addItemType( 18, 18, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 18 );
            this.upgradesAvailable.addItemType( 19, 19, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 19 );
            this.upgradesAvailable.addItemType( 20, 20, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 20 );
        },

        placeUpgradeCard: function(collectorNumber, databaseId, upgradePosition)
        {
            // we only support 2 cards
            if(upgradePosition > 2)
              return;
//console.log('collectorNumber:'+collectorNumber);
            var upgradeRow = this.getUpgradeSpriteRow(collectorNumber); // get sprite row
            var upgradeColumn = this.getUpgradeSpriteColumn(collectorNumber); // get sprite column for this upgrade
console.log('upgradeRow:'+upgradeRow+' upgradewidth:'+this.upgradecardwidth);
            var cardHolderDiv = 'upgrade_holder_'+upgradePosition; // html ID of the card's container
//console.log('databaseId:'+databaseId);
            dojo.place(
                        this.format_block( 'jstpl_upgradeCard', {
                            x: this.upgradecardwidth * upgradeRow,
                            y: this.upgradecardheight * upgradeColumn,
                            databaseId: databaseId
                    } ), cardHolderDiv );

            var cardDiv = 'upgrade_card_'+databaseId; // upgrade_card_3
            this.setupNewCard( $(cardDiv), collectorNumber, databaseId );
        },

        getUpgradeSpriteColumn: function(collectorNumber)
        {
            var collectorNumberAsInt = Number(collectorNumber)
            switch(collectorNumberAsInt)
            {
                case 0:
                    return 0;
                case 1:
                    return 1;
                case 2:
                    return 2;
                case 3:
                    return 3;
                case 4:
                    return 0;
                case 5:
                    return 1;
                case 6:
                    return 2;
                case 7:
                    return 3;
                case 8:
                    return 0;
                case 9:
                    return 1;
                case 10:
                    return 2;
                case 11:
                    return 3;
                case 12:
                    return 0;
                case 13:
                    return 1;
                case 14:
                    return 2;
                case 15:
                    return 3;
                case 16:
                    return 0;
                case 17:
                    return 1;
                case 18:
                    return 2;
                case 19:
                    return 3;
                case 20:
                    return 0;
            }
        },

        getUpgradeSpriteRow: function(collectorNumber)
        {
//          console.log('collectorNum:'+collectorNumber);
            var collectorNumberAsInt = Number(collectorNumber)
            switch(collectorNumberAsInt)
            {
                case 0:
                case 1:
                case 2:
                case 3:
                    return 0;
                case 4:
                case 5:
                case 6:
                case 7:
                    return 1;
                case 8:
                case 9:
                case 10:
                case 11:
                    return 2;
                case 12:
                case 13:
                case 14:
                case 15:
                    return 3;
                case 16:
                case 17:
                case 18:
                case 19:
                    return 4;
                case 20:
                    return 5;
            }
        },

        placePlayerBoardForSaucer: function(owner, color)
        {
            var playerBoardDiv = $('player_board_' + owner);

            // get the index of the saucer (1, 2, 3, 4, etc.)
            var saucerIndex = this.getSaucerIndexForSaucerColor(color);

            // determine if this is the first or second saucer for a particular player so we know how much to offset the position of their crew
            var firstOrSecond = 1;
            if(this.gamedatas.saucer2 != '')
            { // each player controls two saucers
                if(saucerIndex == 2 || saucerIndex == 4)
                {
                    firstOrSecond = 2;
                }
            }

            dojo.place( this.format_block( 'jstpl_player_board_for_saucer', {
                color: color,
                owner: owner,
                firstOrSecond: firstOrSecond
            } ) , playerBoardDiv );

            // create an energy counter on the player board for this saucer
            this.energy_counters[color]=new ebg.counter();
            this.energy_counters[color].create('player_board_energy_count_value_'+color);


            // create a booster counter on the player board for this saucer
            this.booster_counters[color]=new ebg.counter();
            this.booster_counters[color].create('player_board_booster_count_value_'+color);

        },

        getSaucerIndexForSaucerColor: function(saucerColor)
        {
            var saucerIndex = 0;
            for( var i in this.gamedatas.ostrich )
            { // go through each saucer
                saucerIndex++;
                var saucer = this.gamedatas.ostrich[i];
                var color = saucer.color;

                if(color == saucerColor)
                {
                    return saucerIndex;
                }
            }
        },

        getSaucerColorForIndex: function(index)
        {
              var saucer = this.gamedatas.ostrich[index-1];
              return saucer.color;
        },

        getUpgradesPlayedStockForSaucerColor: function(saucerColor)
        {
            var saucerIndex = this.getSaucerIndexForSaucerColor(saucerColor);

            switch(saucerIndex)
            {
                case 1:
                  return this.upgradesPlayed_1;
                case 2:
                  return this.upgradesPlayed_2;
                case 3:
                  return this.upgradesPlayed_3;
                case 4:
                  return this.upgradesPlayed_4;
                case 5:
                  return this.upgradesPlayed_5;
                case 6:
                  return this.upgradesPlayed_6;
            }
        },

        getUpgradeHandStockForSaucerColor: function(saucerColor)
        {
            if(saucerColor == this.gamedatas.saucer2)
            {
                return this.upgradeHand_2;
            }
            else
            {
                return this.upgradeHand_1;
            }
        },

        initializeMoveCards: function()
        {

            // move cards in hand (just 3 for each player)
            console.log( "getting HAND move cards " );
            for( var i in this.gamedatas.hand )
            {
                console.log( "card in hand: " + i );
                var card = this.gamedatas.hand[i];
                var color = card.location; // saucer color like f6033b
                var distance = card.type_arg; // 0, 1, 2
                var used = card.type; // unused or used
                var usedInt = this.convertUsedStringToInt(used); // 0 or 1

                console.log( "with color " + color + " and distance " + distance + " used " + used );


                this.putMoveCardInPlayerHand(used,distance,color);
            }

            // get the move card this player chose this round for each saucer
            for( var saucer in this.gamedatas.chosenMoveCards )
            { // there will be one for each saucer the player owns
                console.log( "move card: " + card );
                var card = this.gamedatas.chosenMoveCards[saucer];
                var saucerColor = card.ostrich_color;
                var direction = card.ostrich_zig_direction;
                var distance = card.ostrich_zig_distance; // 0, 1, 2
                var revealed = card.card_chosen_state;


                this.placeMoveCard(saucerColor, distance, direction, revealed);


                console.log("chosenMoveCards saucerColor:"+saucerColor+" direction:"+direction+" distance:"+distance);
            }

            // First Param: css class to target
            // Second Param: type of events
            // Third Param: the method that will be called when the event defined by the second parameter happen
            this.addEventToClass('move_card', 'onclick', 'onClick_moveCard'); // add the click handler to all move cards
/*
            // zig cards played on table with both zig and direction chosen
            for( i in this.gamedatas.played )
            {
                var card = this.gamedatas.played[i];
                var cardId = card.id; // card ID
                var turnOrder = card.turn; // clockwise or counterclockwise
                var distance = card.distance; // 0, 1, 2, 3
                var player_id = card.player; // player number like 2342823
                var color = card.color; // ostrich color like f6033b
                var degreesRotated = this.getDegreesRotated(card.ostrich_last_direction); // the number of degrees rotated based on direction

                console.log( "Loading a played card with turn order " + turnOrder + " distance " + distance + " player " + player_id + " and color " + color + " and degrees rotated " + degreesRotated + "." );
                this.playRotatedZigOnMat( player_id, cardId, distance, turnOrder, color, degreesRotated );
            }


            // zig cards played on table with ONLY the zig chosen
            console.log( "getting PLAYED move cards " );
            for( i in this.gamedatas.zigChosen )
            {
                var card = this.gamedatas.zigChosen[i];
                var ostrichGettingZig = card.color; // ostrich color like f6033b
                var card_id = card.id; // card ID
                var player_id = card.player; // player number like 2342823
                var clockwise = card.turn; // clockwise or counterclockwise
                var distance = card.distance; // 0, 1, 2, 3

                var clockwiseAsInt = this.getClockwiseInteger(clockwise);

                console.log( "Loading a zigChosen card with clockwise " + clockwise + " distance " + distance + " player " + player_id + " and color " + ostrichGettingZig + "." );

                // create the movement card on the ostrich mat at the final destination
                dojo.place(
                        this.format_block( 'jstpl_mymovementcard', {
                            x: this.movementcardwidth*(distance),
                            y: this.movementcardheight*(clockwiseAsInt),
                            player_id: player_id
                } ), 'zig_holder_'+ostrichGettingZig );
            }
*/
        },

        initializePlayedUpgrades: function()
        {
            // create a stock for each saucer in the game
            var saucerIndex = 0;

            for( var i in this.gamedatas.ostrich )
            { // go through each saucer
                saucerIndex++;
                var saucer = this.gamedatas.ostrich[i];
                var saucerColor = saucer.color;
console.log("initializePlayedUpgrades owner:"+saucer.owner+" color:"+saucer.color+" saucerIndex:"+saucerIndex);

                // create a place to put played upgrades for each saucer

                if(saucerIndex == 1)
                {
                    this.upgradesPlayed_1 = new ebg.stock();
                    this.upgradesPlayed_1.create( this, $('player_board_played_upgrade_cards_'+saucerColor), this.upgradecardwidth, this.upgradecardheight );
                    this.upgradesPlayed_1.image_items_per_row = 4; // the number of card images per row in the sprite image
                    this.upgradesPlayed_1.onItemCreate = dojo.hitch( this, 'setupNewCard' ); // add text to the card image
                    //dojo.connect( this.upgradesPlayed_1, 'onChangeSelection', this, 'onUpgradeHandSelectionChanged' ); // when the onChangeSelection event is triggered on the HTML, call our callback function onTrapHandSelectionChanged below

                    this.upgradesPlayed_1.addItemType( 0, 0, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 0 );
                    this.upgradesPlayed_1.addItemType( 1, 1, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 1 );
                    this.upgradesPlayed_1.addItemType( 2, 2, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 2 );
                    this.upgradesPlayed_1.addItemType( 3, 3, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 3 );
                    this.upgradesPlayed_1.addItemType( 4, 4, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 4 );
                    this.upgradesPlayed_1.addItemType( 5, 5, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 5 );
                    this.upgradesPlayed_1.addItemType( 6, 6, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 6 );
                    this.upgradesPlayed_1.addItemType( 7, 7, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 7 );
                    this.upgradesPlayed_1.addItemType( 8, 8, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 8 );
                    this.upgradesPlayed_1.addItemType( 9, 9, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 9 );
                    this.upgradesPlayed_1.addItemType( 10, 10, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 10 );
                    this.upgradesPlayed_1.addItemType( 11, 11, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 11 );
                    this.upgradesPlayed_1.addItemType( 12, 12, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 12 );
                    this.upgradesPlayed_1.addItemType( 13, 13, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 13 );
                    this.upgradesPlayed_1.addItemType( 14, 14, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 14 );
                    this.upgradesPlayed_1.addItemType( 15, 15, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 15 );
                    this.upgradesPlayed_1.addItemType( 16, 16, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 16 );
                    this.upgradesPlayed_1.addItemType( 17, 17, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 17 );
                    this.upgradesPlayed_1.addItemType( 18, 18, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 18 );
                    this.upgradesPlayed_1.addItemType( 19, 19, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 19 );
                    this.upgradesPlayed_1.addItemType( 20, 20, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 20 );


                }
                else if(saucerIndex == 2)
                {
                    this.upgradesPlayed_2 = new ebg.stock();
                    this.upgradesPlayed_2.create( this, $('player_board_played_upgrade_cards_'+saucerColor), this.upgradecardwidth, this.upgradecardheight );
                    this.upgradesPlayed_2.image_items_per_row = 4; // the number of card images per row in the sprite image
                    this.upgradesPlayed_2.onItemCreate = dojo.hitch( this, 'setupNewCard' ); // add text to the card image
                    //dojo.connect( this.upgradesPlayed_1, 'onChangeSelection', this, 'onUpgradeHandSelectionChanged' ); // when the onChangeSelection event is triggered on the HTML, call our callback function onTrapHandSelectionChanged below

                    this.upgradesPlayed_2.addItemType( 0, 0, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 0 );
                    this.upgradesPlayed_2.addItemType( 1, 1, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 1 );
                    this.upgradesPlayed_2.addItemType( 2, 2, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 2 );
                    this.upgradesPlayed_2.addItemType( 3, 3, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 3 );
                    this.upgradesPlayed_2.addItemType( 4, 4, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 4 );
                    this.upgradesPlayed_2.addItemType( 5, 5, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 5 );
                    this.upgradesPlayed_2.addItemType( 6, 6, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 6 );
                    this.upgradesPlayed_2.addItemType( 7, 7, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 7 );
                    this.upgradesPlayed_2.addItemType( 8, 8, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 8 );
                    this.upgradesPlayed_2.addItemType( 9, 9, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 9 );
                    this.upgradesPlayed_2.addItemType( 10, 10, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 10 );
                    this.upgradesPlayed_2.addItemType( 11, 11, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 11 );
                    this.upgradesPlayed_2.addItemType( 12, 12, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 12 );
                    this.upgradesPlayed_2.addItemType( 13, 13, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 13 );
                    this.upgradesPlayed_2.addItemType( 14, 14, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 14 );
                    this.upgradesPlayed_2.addItemType( 15, 15, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 15 );
                    this.upgradesPlayed_2.addItemType( 16, 16, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 16 );
                    this.upgradesPlayed_2.addItemType( 17, 17, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 17 );
                    this.upgradesPlayed_2.addItemType( 18, 18, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 18 );
                    this.upgradesPlayed_2.addItemType( 19, 19, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 19 );
                    this.upgradesPlayed_2.addItemType( 20, 20, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 20 );
                }
                else if(saucerIndex == 3)
                {
                    this.upgradesPlayed_3 = new ebg.stock();
                    this.upgradesPlayed_3.create( this, $('player_board_played_upgrade_cards_'+saucerColor), this.upgradecardwidth, this.upgradecardheight );
                    this.upgradesPlayed_3.image_items_per_row = 4; // the number of card images per row in the sprite image
                    this.upgradesPlayed_3.onItemCreate = dojo.hitch( this, 'setupNewCard' ); // add text to the card image
                    //dojo.connect( this.upgradesPlayed_1, 'onChangeSelection', this, 'onUpgradeHandSelectionChanged' ); // when the onChangeSelection event is triggered on the HTML, call our callback function onTrapHandSelectionChanged below

                    this.upgradesPlayed_3.addItemType( 0, 0, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 0 );
                    this.upgradesPlayed_3.addItemType( 1, 1, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 1 );
                    this.upgradesPlayed_3.addItemType( 2, 2, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 2 );
                    this.upgradesPlayed_3.addItemType( 3, 3, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 3 );
                    this.upgradesPlayed_3.addItemType( 4, 4, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 4 );
                    this.upgradesPlayed_3.addItemType( 5, 5, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 5 );
                    this.upgradesPlayed_3.addItemType( 6, 6, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 6 );
                    this.upgradesPlayed_3.addItemType( 7, 7, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 7 );
                    this.upgradesPlayed_3.addItemType( 8, 8, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 8 );
                    this.upgradesPlayed_3.addItemType( 9, 9, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 9 );
                    this.upgradesPlayed_3.addItemType( 10, 10, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 10 );
                    this.upgradesPlayed_3.addItemType( 11, 11, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 11 );
                    this.upgradesPlayed_3.addItemType( 12, 12, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 12 );
                    this.upgradesPlayed_3.addItemType( 13, 13, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 13 );
                    this.upgradesPlayed_3.addItemType( 14, 14, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 14 );
                    this.upgradesPlayed_3.addItemType( 15, 15, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 15 );
                    this.upgradesPlayed_3.addItemType( 16, 16, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 16 );
                    this.upgradesPlayed_3.addItemType( 17, 17, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 17 );
                    this.upgradesPlayed_3.addItemType( 18, 18, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 18 );
                    this.upgradesPlayed_3.addItemType( 19, 19, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 19 );
                    this.upgradesPlayed_3.addItemType( 20, 20, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 20 );

                }
                else if(saucerIndex == 4)
                {
                    this.upgradesPlayed_4 = new ebg.stock();
                    this.upgradesPlayed_4.create( this, $('player_board_played_upgrade_cards_'+saucerColor), this.upgradecardwidth, this.upgradecardheight );
                    this.upgradesPlayed_4.image_items_per_row = 4; // the number of card images per row in the sprite image
                    this.upgradesPlayed_4.onItemCreate = dojo.hitch( this, 'setupNewCard' ); // add text to the card image
                    //dojo.connect( this.upgradesPlayed_1, 'onChangeSelection', this, 'onUpgradeHandSelectionChanged' ); // when the onChangeSelection event is triggered on the HTML, call our callback function onTrapHandSelectionChanged below

                    this.upgradesPlayed_4.addItemType( 0, 0, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 0 );
                    this.upgradesPlayed_4.addItemType( 1, 1, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 1 );
                    this.upgradesPlayed_4.addItemType( 2, 2, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 2 );
                    this.upgradesPlayed_4.addItemType( 3, 3, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 3 );
                    this.upgradesPlayed_4.addItemType( 4, 4, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 4 );
                    this.upgradesPlayed_4.addItemType( 5, 5, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 5 );
                    this.upgradesPlayed_4.addItemType( 6, 6, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 6 );
                    this.upgradesPlayed_4.addItemType( 7, 7, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 7 );
                    this.upgradesPlayed_4.addItemType( 8, 8, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 8 );
                    this.upgradesPlayed_4.addItemType( 9, 9, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 9 );
                    this.upgradesPlayed_4.addItemType( 10, 10, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 10 );
                    this.upgradesPlayed_4.addItemType( 11, 11, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 11 );
                    this.upgradesPlayed_4.addItemType( 12, 12, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 12 );
                    this.upgradesPlayed_4.addItemType( 13, 13, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 13 );
                    this.upgradesPlayed_4.addItemType( 14, 14, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 14 );
                    this.upgradesPlayed_4.addItemType( 15, 15, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 15 );
                    this.upgradesPlayed_4.addItemType( 16, 16, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 16 );
                    this.upgradesPlayed_4.addItemType( 17, 17, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 17 );
                    this.upgradesPlayed_4.addItemType( 18, 18, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 18 );
                    this.upgradesPlayed_4.addItemType( 19, 19, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 19 );
                    this.upgradesPlayed_4.addItemType( 20, 20, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 20 );

                }
                else if(saucerIndex == 5)
                {
                    this.upgradesPlayed_5 = new ebg.stock();
                    this.upgradesPlayed_5.create( this, $('player_board_played_upgrade_cards_'+saucerColor), this.upgradecardwidth, this.upgradecardheight );
                    this.upgradesPlayed_5.image_items_per_row = 4; // the number of card images per row in the sprite image
                    this.upgradesPlayed_5.onItemCreate = dojo.hitch( this, 'setupNewCard' ); // add text to the card image
                    //dojo.connect( this.upgradesPlayed_1, 'onChangeSelection', this, 'onUpgradeHandSelectionChanged' ); // when the onChangeSelection event is triggered on the HTML, call our callback function onTrapHandSelectionChanged below

                    this.upgradesPlayed_5.addItemType( 0, 0, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 0 );
                    this.upgradesPlayed_5.addItemType( 1, 1, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 1 );
                    this.upgradesPlayed_5.addItemType( 2, 2, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 2 );
                    this.upgradesPlayed_5.addItemType( 3, 3, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 3 );
                    this.upgradesPlayed_5.addItemType( 4, 4, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 4 );
                    this.upgradesPlayed_5.addItemType( 5, 5, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 5 );
                    this.upgradesPlayed_5.addItemType( 6, 6, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 6 );
                    this.upgradesPlayed_5.addItemType( 7, 7, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 7 );
                    this.upgradesPlayed_5.addItemType( 8, 8, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 8 );
                    this.upgradesPlayed_5.addItemType( 9, 9, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 9 );
                    this.upgradesPlayed_5.addItemType( 10, 10, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 10 );
                    this.upgradesPlayed_5.addItemType( 11, 11, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 11 );
                    this.upgradesPlayed_5.addItemType( 12, 12, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 12 );
                    this.upgradesPlayed_5.addItemType( 13, 13, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 13 );
                    this.upgradesPlayed_5.addItemType( 14, 14, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 14 );
                    this.upgradesPlayed_5.addItemType( 15, 15, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 15 );
                    this.upgradesPlayed_5.addItemType( 16, 16, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 16 );
                    this.upgradesPlayed_5.addItemType( 17, 17, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 17 );
                    this.upgradesPlayed_5.addItemType( 18, 18, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 18 );
                    this.upgradesPlayed_5.addItemType( 19, 19, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 19 );
                    this.upgradesPlayed_5.addItemType( 20, 20, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 20 );

                }
                else if(saucerIndex == 6)
                {
                    this.upgradesPlayed_6 = new ebg.stock();
                    this.upgradesPlayed_6.create( this, $('player_board_played_upgrade_cards_'+saucerColor), this.upgradecardwidth, this.upgradecardheight );
                    this.upgradesPlayed_6.image_items_per_row = 4; // the number of card images per row in the sprite image
                    this.upgradesPlayed_6.onItemCreate = dojo.hitch( this, 'setupNewCard' ); // add text to the card image
                    //dojo.connect( this.upgradesPlayed_1, 'onChangeSelection', this, 'onUpgradeHandSelectionChanged' ); // when the onChangeSelection event is triggered on the HTML, call our callback function onTrapHandSelectionChanged below

                    this.upgradesPlayed_6.addItemType( 0, 0, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 0 );
                    this.upgradesPlayed_6.addItemType( 1, 1, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 1 );
                    this.upgradesPlayed_6.addItemType( 2, 2, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 2 );
                    this.upgradesPlayed_6.addItemType( 3, 3, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 3 );
                    this.upgradesPlayed_6.addItemType( 4, 4, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 4 );
                    this.upgradesPlayed_6.addItemType( 5, 5, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 5 );
                    this.upgradesPlayed_6.addItemType( 6, 6, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 6 );
                    this.upgradesPlayed_6.addItemType( 7, 7, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 7 );
                    this.upgradesPlayed_6.addItemType( 8, 8, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 8 );
                    this.upgradesPlayed_6.addItemType( 9, 9, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 9 );
                    this.upgradesPlayed_6.addItemType( 10, 10, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 10 );
                    this.upgradesPlayed_6.addItemType( 11, 11, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 11 );
                    this.upgradesPlayed_6.addItemType( 12, 12, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 12 );
                    this.upgradesPlayed_6.addItemType( 13, 13, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 13 );
                    this.upgradesPlayed_6.addItemType( 14, 14, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 14 );
                    this.upgradesPlayed_6.addItemType( 15, 15, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 15 );
                    this.upgradesPlayed_6.addItemType( 16, 16, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 16 );
                    this.upgradesPlayed_6.addItemType( 17, 17, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 17 );
                    this.upgradesPlayed_6.addItemType( 18, 18, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 18 );
                    this.upgradesPlayed_6.addItemType( 19, 19, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 19 );
                    this.upgradesPlayed_6.addItemType( 20, 20, g_gamethemeurl+'img/ship_upgrades_230_164.jpg', 20 );

                }
            }

            // upgrade cards in player's hands
            for( var i in this.gamedatas.playedUpgrades )
            {
                //console.log("i:"+i);
                var saucer = this.gamedatas.playedUpgrades[i];

                for( var j in saucer )
                {
                    //console.log("j:"+j);
                    console.log( "playedUpgrades saucer:" );
                    console.log(saucer);

                    var card = saucer[j];

                    var collectorNumber = card.card_type_arg;
                    var saucerColor = card.card_location;
                    var databaseId = card.card_id;
                    var cardOwner = card.card_location_arg;


                    var upgradesPlayedStock = this.getUpgradesPlayedStockForSaucerColor(saucerColor);

                    // add to this saucer's played area
                    upgradesPlayedStock.addToStockWithId( collectorNumber, databaseId );
                }
            }
        },

        setupNewCard: function( card_div, card_type_id, card_id )
        {
             var title = this.getUpgradeTitle(card_type_id);
             var effect = this.getUpgradeEffect(card_type_id);
             var whatHappensWhenYouClickOnIt = '';

             console.log('title:'+title+' effect:'+effect);

             // Add a special tooltip on the card (Maybe replace this with full image to show off the art)
             this.addTooltip( card_div.id, title.toUpperCase() + ": " + effect, whatHappensWhenYouClickOnIt );

             // Note that "card_type_id" contains the type of the item, so you can do special actions depending on the item type

             // Add some custom HTML content INSIDE the Stock item:
             dojo.place( this.format_block( 'jstpl_upgradeCardText', {
                 title: title.toUpperCase(),
                 effect: effect
             } ), card_div.id );

             dojo.connect( $(card_div.id), 'onclick', this, 'onClickUpgradeCardInHand' );
        },

        playUpgradeCard: function( saucerColorPlayingCard, playerPlayingCard, collectorNumber, cardInHandDatabaseId )
        {
              var cardInAvailableUpgradesHtmlId = 'upgradeCardHolder_item_'+cardInHandDatabaseId; // example: upgradeCardHolder_item_3
//this.upgradesPlayed_1.addToStockWithId( collectorNumber, cardInHandDatabaseId );
              console.log('saucerColor('+saucerColorPlayingCard+') collectorNumber('+collectorNumber+') cardInAvailableUpgradesHtmlId('+cardInAvailableUpgradesHtmlId+').');

              // get this saucer's set of played upgrades
              var upgradesPlayedStock = this.getUpgradesPlayedStockForSaucerColor(saucerColorPlayingCard);

              if(this.player_id == playerPlayingCard)
              {
                  // move upgrade from available upgrades to saucer's player board
                  upgradesPlayedStock.addToStockWithId( collectorNumber, cardInHandDatabaseId, cardInAvailableUpgradesHtmlId);
              }
              else
              {
                  // just make the new upgrade appear rather than move
                  upgradesPlayedStock.addToStockWithId( collectorNumber, cardInHandDatabaseId );
              }

              // make this no longer clickable because otherwise you get an error if you try to click it while it's moving or in the played area
              this.disconnect( $(cardInAvailableUpgradesHtmlId), 'onUpgradeHandSelectionChanged');

             // Add a special tooltip on the card (Maybe replace this with full image to show off the art)
             //this.addTooltip( card_div.id, title.toUpperCase() + ": " + effect, whatHappensWhenYouClickOnIt );

/*
             // Add some custom HTML content INSIDE the Stock item:
             dojo.place( this.format_block( 'jstpl_upgradeCardText', {
                 title: title.toUpperCase(),
                 effect: effect
             } ), destination );
*/
        },

        getUpgradeTitle: function(collectorNumber)
        {
            // use gamedatas list of upgrades to pull the correct one based on the id
            return this.gamedatas.upgradeCardContent[collectorNumber]['name'];
        },

        getUpgradeEffect: function(collectorNumber)
        {
            // use gamedatas list of upgrades to pull the correct one based on the id
            return this.gamedatas.upgradeCardContent[collectorNumber]['effect'];
        },

        placeBoard: function(numberOfPlayers)
        {
          console.log("numberOfPlayers:"+numberOfPlayers);
            if(numberOfPlayers < 5)
            { // 1-4 players

                // hide the extra tiles
                //dojo.removeClass( 'board_tile_5', 'board_tile_image' );
                //dojo.removeClass( 'board_tile_6', 'board_tile_image' );

                dojo.destroy('board_tile_container_5');
                dojo.destroy('board_tile_container_6');

                // center the directions based on the number of players
                dojo.style('direction_constellation', "marginTop", "224px"); // move the left direction to where the extra tiles would have been
                dojo.style('direction_asteroids', "marginTop", "224px"); // move the right direction to where the extra tiles would have been
                dojo.style('direction_sun', "marginLeft", "280px");
                dojo.style('direction_meteor', "marginLeft", "280px");

                dojo.style('board_tile_column', "width", "685px"); // set the width of the board based on saucer count
            }
            else if(numberOfPlayers == 5)
            { // we are playing with 5 players

              dojo.destroy('board_tile_container_4');
              dojo.destroy('board_tile_container_6');

                // center the directions based on the number of players
                dojo.style('direction_constellation', "marginTop", "254px"); // move the right direction to where the extra tiles would have been
                dojo.style('direction_asteroids', "marginTop", "254px"); // move the right direction to where the extra tiles would have been
                dojo.style('direction_sun', "marginLeft", "305px");
                dojo.style('direction_meteor', "marginLeft", "305px");

                dojo.style('board_tile_column', "width", "750px"); // set the width of the board based on saucer count
            }
            else if(numberOfPlayers == 6)
            { // we are playing with 6 players

              dojo.destroy('board_tile_container_4');
              dojo.destroy('board_tile_container_5');

                // center the directions based on the number of players
                dojo.style('direction_constellation', "marginTop", "280px"); // move the right direction to where the extra tiles would have been
                dojo.style('direction_asteroids', "marginTop", "280px"); // move the right direction to where the extra tiles would have been
                dojo.style('direction_sun', "marginLeft", "330px");
                dojo.style('direction_meteor', "marginLeft", "330px");

                dojo.style('board_tile_column', "width", "790px"); // set the width of the board based on saucer count
            }
        },

        putSaucerOnTile: function( x, y, owner, color )
        {
            var saucerHtmlId = 'saucer_'+color;

            dojo.place( this.format_block( 'jstpl_saucer', {
                color: color
            } ) , 'square_'+x+'_'+y );

            //this.placeOnObject( 'disc_'+color, 'square_'+x+'_'+y );
            this.slideToObject( saucerHtmlId, 'square_'+x+'_'+y ).play();

            if(color == this.gamedatas.saucer1 || color == this.gamedatas.saucer2)
            { // this saucer is owned by this player

                  dojo.addClass( saucerHtmlId, 'clickable' );
            }
        },

        putProbeOnPlayerBoard: function( color )
        {
          dojo.place( this.format_block( 'jstpl_crown', {
              color: color
          } ) , 'player_board_crown_holder_'+color );

        },

        putArrowOnPlayerBoard: function( x, y, player_id )
        {
            var arrowHolder = 'player_board_direction_holder_'+player_id;
            console.log("placing arrow in:"+arrowHolder);
            dojo.place( this.format_block( 'jstpl_arrow', {
                x: x,
                y: y,
                id: player_id
            } ) , arrowHolder );
        },

        putMoveCardInPlayerHand: function( used, distance, saucer_color )
        {
            var divHolder = 'move_card_holder_'+distance+'_'+saucer_color;
            console.log("placing move card in:"+divHolder);
            var usedInt = this.convertUsedStringToInt(used);
            dojo.place( this.format_block( 'jstpl_moveCard', {
                x: distance * this.movementcardwidth,
                y: usedInt,
                color: saucer_color,
                distance: distance
            } ) , divHolder );
        },

        setTurnDirectionArrow: function(x, y, player_id)
        {
            dojo.style( 'player_board_arrow_'+player_id, 'backgroundPositionX', '-'+x+'px' );
            //dojo.style( 'my_element', 'display', 'none' );

            console.log('setting arrow background position x:'+x+' y:'+y+' player_id:'+player_id);
        },

        hideTurnDirection: function()
        {
            for( var player_id in this.gamedatas.players )
            {
                this.setTurnDirectionArrow(70, 0, player_id);
            }
        },

        moveCrownToPlayerBoard: function( color )
        {
            console.log('moving crown from (player_board_crown) to (player_board_crown_holder_'+color+')');
            this.slideToObject( 'player_board_crown', 'player_board_crown_holder_'+color ).play();
        },

        placeMoveCard: function(saucerColor, distance, direction, revealed)
        {
            var sourceHtmlId = 'move_card_'+distance+'_'+saucerColor;
            console.log('placeMoveCard sourceHtmlId:'+sourceHtmlId);

            // if it is unchosen, do nothing -- we shouldn't be calling this so skip this check
            // if chosen:
            //   if it is our card or it is revealed, show the distance and direction
            //   if it is an opponent card in chosen state, show the card back

            if(saucerColor == this.gamedatas.saucer1 || saucerColor == this.gamedatas.saucer2)
            { // this is MY card
                // put the selected border around the card
                this.selectSpecificMoveCard(sourceHtmlId);

                // save the selected card
                if(this.gamedatas.saucer1 == saucerColor)
                  this.CHOSEN_MOVE_CARD_SAUCER_1 = sourceHtmlId;
                else
                  this.CHOSEN_MOVE_CARD_SAUCER_2 = sourceHtmlId;



                if(distance != 20)
                { // we've chosen our card already

                }

                if( $(sourceHtmlId) )
                { // this card exists
                    this.slideMoveCard(sourceHtmlId, saucerColor, direction);
                }
            }
            else
            { // this is an opponent card

                if(revealed == 'revealed')
                { // the card is revealed

                    // place the front
                    dojo.place( this.format_block( 'jstpl_moveCard', {
                        x: distanceType * this.movementcardwidth,
                        y: 0,
                        color: saucerColor,
                        distance: distance
                    } ) , destinationHtmlId );
                }
                else
                { // it is still hidden

                    // place the back
                    dojo.place(
                            this.format_block( 'jstpl_moveCardBack', {
                                x: this.getMoveCardBackgroundX(saucerColor),
                                y: this.getMoveCardBackgroundY(saucerColor),
                                color: saucerColor
                    } ), 'player_board_move_card_holder_'+saucerColor );

                    this.rotateTo( 'move_card_back_'+saucerColor, 45 );
                }
            }
        },

        slideMoveCard: function(sourceHtmlId, saucerColor, direction)
        {

            console.log('Move card FROM ' + sourceHtmlId + ' to player_board_move_card_holder_' + saucerColor + '.');
            //this.placeOnObject( 'cardontable_'+player_id, 'myhand_item_'+card_id ); // teleport card FROM, TO

            var destinationHtmlId = 'player_board_move_card_holder_'+saucerColor;
            this.attachToNewParent( sourceHtmlId, destinationHtmlId ); // needed so it doesn't slide under the player board
            this.rotateTo( sourceHtmlId, this.getDegreesRotated(direction) );
            var animationId = this.slideToObject( sourceHtmlId, destinationHtmlId, this.ANIMATION_SPEED_CREWMEMBER_PICKUP );
            dojo.connect(animationId, 'onEnd', () => {

                // after sliding, the left and top properties have a non-zero value for some reason, making it just a little off on where it should be on the mat
                $(sourceHtmlId).style.removeProperty('left'); // remove left property
                $(sourceHtmlId).style.removeProperty('top'); // remove top property

            });
            animationId.play();

        },

        moveOstrichOnBoard: function( ostrichMoving, ostrichTakingTurn, xDestination, yDestination, spaceType, ostrichMovingHasZag )
        {
            console.log("moving ostrich to a space of type "+ spaceType + " with color " + ostrichMoving + " and x of " + xDestination + " and y of " + yDestination);
          /*
            dojo.place( this.format_block( 'jstpl_disc', {
                color: color
            } ) , 'discs' );
          */
            this.slideToObject( 'saucer_'+ostrichMoving, 'square_'+xDestination+'_'+yDestination ).play(); // should be ostrich_COLOR

            if(spaceType == "D")
            { // this ostrich fell off a cliff

            }
            else if(spaceType == "S")
            { // we are ending on a SKATEBOARD
                this.mustSkateboard = true; // make sure we display the skateboard buttons
                //this.showDirectionChoiceButtons();
            }
            else if(ostrichMoving == ostrichTakingTurn && ostrichMovingHasZag)
            { // it is the moving ostrich's turn and they have a zag
                this.showAskToUseBoosterButtons();
            }
            else {
              this.mustSkateboard = false;
            }
        },

        showAskToRespawnButtons: function()
        {
            this.addActionButton( 'ostrichRespawn_button', _('OK!'), 'onOstrichRespawn' );

        },

        showMoveButton: function()
        {
            this.addActionButton( 'move_button', _('Move'), 'onMoveClick' );
        },

        showEnergyButton: function(saucerWhoCrashed)
        {
            this.addActionButton( 'crashed_saucer_'+saucerWhoCrashed, '<div class="energy_cube"></div>', 'onClick_energyReward', null, null, 'gray');
        },

        showXValueButtons: function()
        {
          this.addActionButton( '0_button', _('0'), 'onXValueSelection' );
          this.addActionButton( '1_button', _('1'), 'onXValueSelection' );
          this.addActionButton( '2_button', _('2'), 'onXValueSelection' );
          this.addActionButton( '3_button', _('3'), 'onXValueSelection' );
          this.addActionButton( '4_button', _('4'), 'onXValueSelection' );
          this.addActionButton( '5_button', _('5'), 'onXValueSelection' );
//          this.addActionButton( '6_button', _('6'), 'onXValueSelection' );
//          this.addActionButton( '7_button', _('7'), 'onXValueSelection' );
//          this.addActionButton( '8_button', _('8'), 'onXValueSelection' );
//          this.addActionButton( '9_button', _('9'), 'onXValueSelection' );
//          this.addActionButton( '10_button', _('10'), 'onXValueSelection' );
//          this.addActionButton( '11_button', _('11'), 'onXValueSelection' );
        },

        showDirectionButtons: function()
        {
            this.addActionButton( this.LEFT_DIRECTION+'_button', '<div class="'+this.LEFT_DIRECTION+'"></div>', 'onClick_'+this.LEFT_DIRECTION+'Direction', null, null, 'gray');

            this.addActionButton( this.UP_DIRECTION+'_button', '<div class="'+this.UP_DIRECTION+'"></div>', 'onClick_'+this.UP_DIRECTION+'Direction', null, null, 'gray');

            this.addActionButton( this.DOWN_DIRECTION+'_button', '<div class="'+this.DOWN_DIRECTION+'"></div>', 'onClick_'+this.DOWN_DIRECTION+'Direction', null, null, 'gray');

            this.addActionButton( this.RIGHT_DIRECTION+'_button', '<div class="'+this.RIGHT_DIRECTION+'"></div>', 'onClick_'+this.RIGHT_DIRECTION+'Direction', null, null, 'gray');

            //dojo.addClass('sun_button','bgaimagebutton'); // remove the button outline

        },

        showMoveCardDirectionButtons: function(saucerNumber)
        {
            var saucerButtonHolder = 'saucer_'+saucerNumber+'_action_button_holder';
            this.addActionButton( this.LEFT_DIRECTION+'_'+saucerNumber+'_button', '<div class="'+this.LEFT_DIRECTION+'"></div>', 'onClick_MoveCard_'+this.LEFT_DIRECTION+'Direction', saucerButtonHolder, null, 'gray');
            this.addActionButton( this.UP_DIRECTION+'_'+saucerNumber+'_button', '<div class="'+this.UP_DIRECTION+'"></div>', 'onClick_MoveCard_'+this.UP_DIRECTION+'Direction', saucerButtonHolder, null, 'gray');
            this.addActionButton( this.DOWN_DIRECTION+'_'+saucerNumber+'_button', '<div class="'+this.DOWN_DIRECTION+'"></div>', 'onClick_MoveCard_'+this.DOWN_DIRECTION+'Direction', saucerButtonHolder, null, 'gray');
            this.addActionButton( this.RIGHT_DIRECTION+'_'+saucerNumber+'_button', '<div class="'+this.RIGHT_DIRECTION+'"></div>', 'onClick_MoveCard_'+this.RIGHT_DIRECTION+'Direction', saucerButtonHolder, null, 'gray');


            //dojo.addClass('sun_button','bgaimagebutton'); // remove the button outline

        },

        showChooseMoveCardButtons: function()
        {
            // SAUCER 1
            // show saucer image
            var saucer1ButtonHtmlId = 'saucer_'+this.gamedatas.saucer1+'_button';
            var saucer1ButtonHolder = 'saucer_1_action_button_holder';
            this.addActionButton( saucer1ButtonHtmlId, '<div class="saucer saucer_button saucer_color_'+this.gamedatas.saucer1+'"></div>', 'onClick_SaucerButton', saucer1ButtonHolder, null, 'gray');


            // remove the button border
            dojo.style( saucer1ButtonHtmlId, 'border', '0px' );

            // show distance buttons
            this.addActionButton( 'moveCard_1_distance_1_button', _('2'), 'onClickMoveCardDistance', saucer1ButtonHolder, null, 'gray' );
            this.addActionButton( 'moveCard_1_distance_2_button', _('3'), 'onClickMoveCardDistance', saucer1ButtonHolder, null, 'gray' );
            this.addActionButton( 'moveCard_1_distance_0_button', _('0-5'), 'onClickMoveCardDistance', saucer1ButtonHolder, null, 'gray' );

            // show direction buttons
            this.showMoveCardDirectionButtons('1');

            // SAUCER 2
            if(this.gamedatas.saucer2 != '')
            {
                // show saucer image
                var saucer2ButtonHtmlId = 'saucer_'+this.gamedatas.saucer2+'_button';
                var saucer2ButtonHolder = 'saucer_2_action_button_holder';
                this.addActionButton( saucer2ButtonHtmlId, '<div class="saucer saucer_button saucer_color_'+this.gamedatas.saucer2+'"></div>', 'onClick_SaucerButton', saucer2ButtonHolder, null, 'gray');

                // remove the button border
                dojo.style( saucer2ButtonHtmlId, 'border', '0px' );

                // show distance buttons
                this.addActionButton( 'moveCard_2_distance_1_button', _('2'), 'onClickMoveCardDistance', saucer2ButtonHolder, null, 'gray' );
                this.addActionButton( 'moveCard_2_distance_2_button', _('3'), 'onClickMoveCardDistance', saucer2ButtonHolder, null, 'gray' );
                this.addActionButton( 'moveCard_2_distance_0_button', _('0-5'), 'onClickMoveCardDistance', saucer2ButtonHolder, null, 'gray' );

                // show direction buttons
                this.showMoveCardDirectionButtons('2');
            }


        },

        showPassableCrewmemberButtons: function(crewmemberList)
        {
            if(!crewmemberList)
                return;

            for (const crewmember of crewmemberList)
            { // go through each crewmember
                var crewmemberColor = crewmember['crewmemberColor'];
                var crewmemberTypeString = crewmember['crewmemberType'];

                console.log("passable crewmember color:"+crewmemberColor+" lost crewmember type:"+crewmemberTypeString);

                //var htmlOfSpace = 'square_'+space; // square_6_5
                //console.log("highlighting space: " + htmlOfSpace);
                //this.highlightSpace(htmlOfSpace);

                this.addActionButton( 'passableCrewmember_'+crewmemberColor+'_'+crewmemberTypeString+'_button', '<div id="button_'+crewmemberTypeString+'_'+crewmemberColor+'" class="crewmember crewmember_'+crewmemberTypeString+'_'+crewmemberColor+'"></div>', 'onClickPassableCrewmember', null, null, 'gray');
            }
        },

        showTakeableCrewmemberButtons: function(crewmemberList)
        {
            if(!crewmemberList)
                return;

            for (const crewmember of crewmemberList)
            { // go through each crewmember
                var crewmemberColor = crewmember['crewmemberColor'];
                var crewmemberTypeString = crewmember['crewmemberType'];

                console.log("takeable crewmember color:"+crewmemberColor+" lost crewmember type:"+crewmemberTypeString);

                //var htmlOfSpace = 'square_'+space; // square_6_5
                //console.log("highlighting space: " + htmlOfSpace);
                //this.highlightSpace(htmlOfSpace);

                this.addActionButton( 'takeableCrewmember_'+crewmemberColor+'_'+crewmemberTypeString+'_button', '<div id="button_'+crewmemberTypeString+'_'+crewmemberColor+'" class="crewmember crewmember_'+crewmemberTypeString+'_'+crewmemberColor+'"></div>', 'onClickTakeableCrewmember', null, null, 'gray');
            }
        },

        showStealableCrewmemberButtons: function(stealableCrewmembers)
        {

            for (const crewmember of stealableCrewmembers)
            { // go through each crewmember
                var crewmemberColor = crewmember['crewmemberColor'];
                var crewmemberTypeString = crewmember['crewmemberType'];

                console.log("stealable crewmember color:"+crewmemberColor+" lost crewmember type:"+crewmemberTypeString);

                //var htmlOfSpace = 'square_'+space; // square_6_5
                //console.log("highlighting space: " + htmlOfSpace);
                //this.highlightSpace(htmlOfSpace);

                this.addActionButton( 'stealableCrewmember_'+crewmemberColor+'_'+crewmemberTypeString+'_button', '<div id="button_'+crewmemberTypeString+'_'+crewmemberColor+'" class="crewmember crewmember_'+crewmemberTypeString+'_'+crewmemberColor+'"></div>', 'onClickStealableCrewmember', null, null, 'gray');
            }
        },

        showTractorBeamCrewmemberButtons: function(crewmembers)
        {

            for (const crewmember of crewmembers)
            { // go through each crewmember
                var crewmemberColor = crewmember['garment_color'];
                var crewmemberTypeInt = crewmember['garment_type'];
                var crewmemberTypeString = this.convertCrewmemberType(crewmemberTypeInt);

                console.log("tractor beam crewmember color:"+crewmemberColor+" lost crewmember type:"+crewmemberTypeString);

                //var htmlOfSpace = 'square_'+space; // square_6_5
                //console.log("highlighting space: " + htmlOfSpace);
                //this.highlightSpace(htmlOfSpace);

                this.addActionButton( 'tractorBeamCrewmember_'+crewmemberColor+'_'+crewmemberTypeString+'_button', '<div id="button_'+crewmemberTypeString+'_'+crewmemberColor+'" class="crewmember crewmember_'+crewmemberTypeString+'_'+crewmemberColor+'"></div>', 'onClickTractorBeamCrewmember', null, null, 'gray');
            }
        },

        showGiveAwayCrewmemberButtons: function(giveAwayCrewmembers, otherSaucers)
        {

            for (const crewmember of giveAwayCrewmembers)
            { // go through each crewmember
                var crewmemberColor = crewmember['crewmemberColor'];
                var crewmemberTypeString = crewmember['crewmemberType'];

                console.log("give away crewmember color:"+crewmemberColor+" lost crewmember type:"+crewmemberTypeString);

                //var htmlOfSpace = 'square_'+space; // square_6_5
                //console.log("highlighting space: " + htmlOfSpace);
                //this.highlightSpace(htmlOfSpace);

                this.addActionButton( 'giveawayCrewmember_'+crewmemberColor+'_'+crewmemberTypeString+'_button', '<div id="button_'+crewmemberTypeString+'_'+crewmemberColor+'" class="crewmember crewmember_'+crewmemberTypeString+'_'+crewmemberColor+'"></div>', 'onClickGiveAwayCrewmember', null, null, 'gray');
            }

            for (const color of otherSaucers)
            { // go through each saucer

                console.log("give away saucer color:"+color);

                this.addActionButton( 'giveawaySaucer_'+color+'_button', _(color+' Saucer'), 'onClickGiveAwayToSaucer', null, null, 'gray' );
                //this.addActionButton( 'giveawaySaucer_'+color+'_button', '<div id="button_saucerColor_'+color+'" style="background-color:'+color+';">'+color+'</div>', 'onClickGiveAwayToSaucer', null, null, 'gray');

                var buttonNode = $('giveawaySaucer_'+color+'_button');
                //dojo.addClass( buttonNode, 'crewmemberButtonSelected' );
                buttonNode.style.backgroundColor=color;
            }

        },

        showEmptyCrashSiteButtons: function(emptyCrashSites)
        {
            const emptyCrashSitesKeys = Object.keys(emptyCrashSites);
//            console.log('keys:');
//            console.log(emptyCrashSitesKeys);
            for (const crashSiteIndex of emptyCrashSitesKeys)
            { // go through each crash site

                var crashSiteNumber = emptyCrashSites[crashSiteIndex];
                console.log("crash site number:"+crashSiteNumber);

                //var htmlOfSpace = 'square_'+space; // square_6_5
                //console.log("highlighting space: " + htmlOfSpace);
                //this.highlightSpace(htmlOfSpace);

                // show an image button:
                //this.addActionButton( 'crashSite_'+crashSiteNumber+'_button', '<div id="button_crash_site_'+crashSiteNumber+'" class="crashSite"></div>', 'onClickCrashSite', null, null, 'gray');
                this.addActionButton( 'crashSite_'+crashSiteNumber+'_button', _(crashSiteNumber), 'onClickCrashSite' ); // show text button for now
            }
        },

        showLostCrewmemberButtons: function(lostCrewmembers)
        {
            //this.unhighlightAllLostCrewmembers();
            //this.highlightAllLostCrewmembers();

            //var countOfSpaces = count(validSpaces);
            //console.log("count spaces to highlight: " + countOfSpaces);

            //const lostCrewmembersKeys = Object.keys(lostCrewmembers);
            console.log("lost crewmembers:");
            console.log(lostCrewmembers);

            for (const crewmember of lostCrewmembers)
            { // go through each crewmember
                var crewmemberColor = crewmember['garment_color'];
                var crewmemberType = crewmember['garment_type'];
                var crewmemberTypeString = this.convertCrewmemberType(crewmemberType);

                console.log("lost crewmember color:"+crewmemberColor+" lost crewmember type:"+crewmemberTypeString);

                //var htmlOfSpace = 'square_'+space; // square_6_5
                //console.log("highlighting space: " + htmlOfSpace);
                //this.highlightSpace(htmlOfSpace);

                this.addActionButton( 'lostCrewmember_'+crewmemberColor+'_'+crewmemberTypeString+'_button', '<div id="button_'+crewmemberTypeString+'_'+crewmemberColor+'" class="crewmember crewmember_'+crewmemberTypeString+'_'+crewmemberColor+'"></div>', 'onClickLostCrewmember', null, null, 'gray');
            }
        },

        showAskToUseBoosterButtons: function()
        {
            this.addActionButton( 'noBooster_button', _('Skip Booster'), 'skipBooster' );
        },

        showAskStealOrDrawButtons: function(countOfStealableGarments)
        {
            if(countOfStealableGarments > 0)
            {
                this.addActionButton( 'steal_button', _('Steal a Garment'), 'onChooseToStealGarment' );
            }

            this.addActionButton( 'draw_button', _('Draw Zigs'), 'onDraw2Zigs' );
        },

        sendXValue: function(value)
        {
            this.ajaxcall( "/crashandgrab/crashandgrab/actSelectXValue.html", { xValue: value, lock: true }, this, function( result ) {
            }, function( is_error) { } );
        },

        sendLostCrewmemberSelection: function(crewmemberTypeString, crewmemberColor)
        {
            if(crewmemberTypeString != null && crewmemberColor != null)
            { // the player has chosen both a destination space and a garment
                this.ajaxcall( "/crashandgrab/crashandgrab/actReplaceGarmentChooseGarment.html", { garmentType: crewmemberTypeString, garmentColor: crewmemberColor, lock: true }, this, function( result ) {}, function( is_error ) {} );
            }
        },

        sendSkipZag: function()
        {
          console.log("sendSkipZag");
          this.ajaxcall( "/crashandgrab/crashandgrab/actSkipZag.html", { lock: true }, this, function( result ) {
          }, function( is_error) { } );
        },

        sendZigChoice: function(ostrich, cardId)
        { // Tell the server which move was selected for this ostrich.
            console.log("sendZigChoice sending card_id " + cardId);
            this.ajaxcall( "/crashandgrab/crashandgrab/actChooseZigCard.html", { id: cardId, ostrich: ostrich, lock: true }, this, function( result ) {
            }, function( is_error) { } );
        },

        sendZigDirectionChoice: function(chosenCardDirection)
        { // Tell the server which move was selected for this ostrich.
            console.log("sendZigDirectionChoice sending in direction " + chosenCardDirection);
            this.ajaxcall( "/crashandgrab/crashandgrab/actChooseZigDirection.html", { direction: chosenCardDirection, lock: true }, this, function( result ) {
            }, function( is_error) { } );
        },

        sendStartZigPhaseOver: function()
        {
            console.log("sendStartZigPhaseOver");
            this.ajaxcall( "/crashandgrab/crashandgrab/actStartZigPhaseOver.html", { lock: true }, this, function( result ) {
            }, function( is_error) { } );
        },

        // The player is saying they want this ostrich to go next when they have more than 1.
        sendOstrichChoice: function(ostrich)
        {
          console.log("sendOstrichChoice sending ostrich " + ostrich);
          this.ajaxcall( "/crashandgrab/crashandgrab/actChooseOstrichToGoNext.html", { ostrich: ostrich, lock: true }, this, function( result ) {
          }, function( is_error) { } );
        },

        sendExecuteMove: function( ostrich )
        { // Tell the server which move was selected for this ostrich.
            console.log("sendExecuteMove sending in ostrich " + ostrich);
            this.ajaxcall( "/crashandgrab/crashandgrab/actExecuteMove.html", { ostrich: ostrich, ostrichTakingTurn: ostrich, lock: true }, this, function( result ) {
            }, function( is_error) { } );
        },

        sendZagMove: function( chosenDirection )
        { // Tell the server which move was selected for this ostrich.
            console.log("sendZagMove sending direction " + chosenDirection);
            this.ajaxcall( "/crashandgrab/crashandgrab/actExecuteZagMove.html", { direction: chosenDirection, lock: true }, this, function( result ) {
            }, function( is_error) { } );
        },

        onClickMoveCardDistance: function( evt )
        {
            var node = evt.currentTarget.id; // "button_crash_site_4
            console.log("onClickMoveCardDistance node:"+node);
            console.log("CHOSEN_MOVE_CARD_SAUCER_1:"+this.CHOSEN_MOVE_CARD_SAUCER_1+' this.CHOSEN_DIRECTION_SAUCER_1:'+this.CHOSEN_DIRECTION_SAUCER_1);

            var saucerNumber = node.split('_')[1]; // 1, 2
            var distanceType = node.split('_')[3]; // 0,1,2
            var color = 'unknown';

            if(saucerNumber == 1)
            {
                color = this.gamedatas.saucer1;
            }
            else
            {
                color = this.gamedatas.saucer2;
            }

            this.selectMoveCard(distanceType, color, saucerNumber);
        },

        sendDirectionClick: function( chosenDirection )
        {
            console.log("sendDirectionClick sending direction " + chosenDirection);
            this.ajaxcall( "/crashandgrab/crashandgrab/actExecuteDirectionClick.html", { direction: chosenDirection, lock: true }, this, function( result ) {
            }, function( is_error) { } );
        },

        onClickCrashSite: function( evt )
        {
            var node = evt.currentTarget.id; // "button_crash_site_4
            console.log("onClickCrashSite node:"+node);
            var crashSiteNumber = node.split('_')[1]; // 4

            this.ajaxcall( "/crashandgrab/crashandgrab/actExecuteChooseCrashSite.html", { crashSiteNumber: crashSiteNumber, lock: true }, this, function( result ) {
            }, function( is_error) { } );
        },

        onClickStealableCrewmember: function( evt )
        {
            var node = evt.currentTarget.id; // stealableCrewmember_01b508_pilot_button
            console.log("onClickStealableCrewmember node:"+node);
            var crewmemberType = node.split('_')[2]; // pilot
            var crewmemberColor = node.split('_')[1]; // 01b508

            this.ajaxcall( "/crashandgrab/crashandgrab/actExecuteStealCrewmember.html", { stolenType: crewmemberType, stolenColor: crewmemberColor, lock: true }, this, function( result ) {
            }, function( is_error) { } );
        },

        onClickTractorBeamCrewmember: function( evt )
        {
            var node = evt.currentTarget.id; // tractorBeamCrewmember_01b508_pilot_button
            console.log("onClickTractorBeamCrewmember node:"+node);
            var crewmemberType = node.split('_')[2]; // pilot
            var crewmemberColor = node.split('_')[1]; // 01b508

            this.ajaxcall( "/crashandgrab/crashandgrab/actExecuteTractorBeamCrewmember.html", { crewmemberType: crewmemberType, crewmemberColor: crewmemberColor, lock: true }, this, function( result ) {
            }, function( is_error) { } );
        },

        onClickPassableCrewmember: function( evt )
        {
            var node = evt.currentTarget.id; // passableCrewmember_01b508_pilot_button
            console.log("onClickPassableCrewmember node:"+node);
            var crewmemberType = node.split('_')[2]; // pilot
            var crewmemberColor = node.split('_')[1]; // 01b508

            this.ajaxcall( "/crashandgrab/crashandgrab/actExecutePassCrewmember.html", { stolenType: crewmemberType, stolenColor: crewmemberColor, lock: true }, this, function( result ) {
            }, function( is_error) { } );
        },

        onClickTakeableCrewmember: function( evt )
        {
            var node = evt.currentTarget.id; // takeableCrewmember_01b508_pilot_button
            console.log("onClickTakeableCrewmember node:"+node);
            var crewmemberType = node.split('_')[2]; // pilot
            var crewmemberColor = node.split('_')[1]; // 01b508

            this.ajaxcall( "/crashandgrab/crashandgrab/actExecuteTakeCrewmember.html", { stolenType: crewmemberType, stolenColor: crewmemberColor, lock: true }, this, function( result ) {
            }, function( is_error) { } );
        },

        onClickGiveAwayCrewmember: function( evt )
        {
            var node = evt.currentTarget.id; // stealableCrewmember_01b508_pilot_button
            console.log("onClickGiveAwayCrewmember node:"+node);
            var crewmemberType = node.split('_')[2]; // pilot
            var crewmemberColor = node.split('_')[1]; // 01b508

            this.GIVE_AWAY_TYPE = crewmemberType;
            this.GIVE_AWAY_COLOR = crewmemberColor;

            // remove all saucer button highlights
            dojo.query( '.crewmemberButtonSelected' ).removeClass( 'crewmemberButtonSelected' );

            // highlight this saucer button
            dojo.addClass( node, 'crewmemberButtonSelected' );

            if(this.GIVE_AWAY_TYPE != '' && this.GIVE_AWAY_COLOR != '' && this.GIVE_AWAY_SAUCER != '')
            { // we have selected both the crewmember and the saucer we're giving it to
                this.ajaxcall( "/crashandgrab/crashandgrab/actExecuteGiveAwayCrewmember.html", { stolenType: this.GIVE_AWAY_TYPE, stolenColor: this.GIVE_AWAY_COLOR, saucerColor: this.GIVE_AWAY_SAUCER, lock: true }, this, function( result ) {
                }, function( is_error) { } );
            }


        },

        onClickGiveAwayToSaucer: function( evt )
        {
            var node = evt.currentTarget.id; // stealableCrewmember_01b508_pilot_button
            console.log("onClickGiveAwayToSaucer node:"+node);
            var saucerColor = node.split('_')[1]; // 01b508

            this.GIVE_AWAY_SAUCER = saucerColor;

            // remove all saucer button highlights
            dojo.query( '.saucerButtonSelected' ).removeClass( 'saucerButtonSelected' );

            // highlight this saucer button
            dojo.addClass( node, 'saucerButtonSelected' );

            if(this.GIVE_AWAY_TYPE != '' && this.GIVE_AWAY_COLOR != '' && this.GIVE_AWAY_SAUCER != '')
            { // we have selected both the crewmember and the saucer we're giving it to
                this.ajaxcall( "/crashandgrab/crashandgrab/actExecuteGiveAwayCrewmember.html", { stolenType: this.GIVE_AWAY_TYPE, stolenColor: this.GIVE_AWAY_COLOR, saucerColor: this.GIVE_AWAY_SAUCER, lock: true }, this, function( result ) {
                }, function( is_error) { } );
            }
        },

        onClickLostCrewmember: function( evt )
        {
            var node = evt.currentTarget.id; // 'lostCrewmember_01b508_pilot_button'
            console.log("onClickLostCrewmember:"+node);
            var crewmemberColor = node.split('_')[1]; // 01b508
            var crewmemberType = node.split('_')[2]; // pilot

            // tell the server that the user selected this crewmember
            this.sendLostCrewmemberSelection(crewmemberType, crewmemberColor);
        },

        onClickUpgradeCardInHand: function( evt )
        { // a player clicked on an Upgrade Card in the player's hand

            dojo.stopEvent( evt ); // Preventing default browser reaction

            var node = evt.currentTarget.id; // upgradeCardHolder_item_10
            console.log("onClickUpgradeCardInHand:"+node);
            var databaseUniqueIdentifier = node.split('_')[2]; // 1, 2, 3 (not collector number)


                // Check that this action is possible (see "possibleactions" in states.inc.php)
                //if( !this.checkAction( 'clickMyIntegrityCard' ) )
                if( !this.checkPossibleActions('chooseUpgrade'))
                { // we can't click this card now
console.log("failed... onClickUpgradeCardInHand");
                }
                else
                { // we can click it
console.log("success... onClickUpgradeCardInHand");
                    this.ajaxcall( "/crashandgrab/crashandgrab/actClickUpgradeInHand.html", {
                                                                            lock: true,
                                                                            upgradeDatabaseId: databaseUniqueIdentifier
                                                                         },
                                 this, function( result ) {

                                    // What to do after the server call if it succeeded
                                    // (most of the time: nothing)
                                    //this.highlightComponent(node);  // highlight the card

                                 }, function( is_error) {

                                    // What to do after the server call in anyway (success or failure)
                                    // (most of the time: nothing)

                                 }
                    );
                }

        },

        sendClaimZag: function(ostrich, cardsDiscarded)
        {
            console.log("sendClaimZag for ostrich " + ostrich);
            this.ajaxcall( "/crashandgrab/crashandgrab/actClaimZag.html", { ostrich: ostrich, cardsDiscarded: cardsDiscarded, lock: true }, this, function( result ) {
            }, function( is_error) { } );
        },

        sendRespawnRequest: function()
        {
          console.log("sendRespawnRequest");
          this.ajaxcall( "/crashandgrab/crashandgrab/actRespawnOstrich.html", { lock: true }, this, function( result ) {
          }, function( is_error) { } );
        },

        sendDraw2ZigsRequest: function()
        {
          console.log("sendDraw2ZigsRequest");
          this.ajaxcall( "/crashandgrab/crashandgrab/actDraw2Zigs.html", { lock: true }, this, function( result ) {
          }, function( is_error) { } );
        },

        sendAskWhichGarmentToStealRequest: function()
        {
            console.log("sendStealGarmentRequest");
            this.ajaxcall( "/crashandgrab/crashandgrab/actAskWhichGarmentToSteal.html", { lock: true }, this, function( result ) {
            }, function( is_error) { } );
        },

        sendDiscardTrap: function(cardDiscarded, ostrichMoving, ostrichTakingTurn)
        {
            console.log("sendDiscardTrap card id " + cardDiscarded);
            this.ajaxcall( "/crashandgrab/crashandgrab/actDiscardTrap.html", { cardDiscarded: cardDiscarded, ostrichMoving: ostrichMoving, ostrichTakingTurn: ostrichTakingTurn, lock: true }, this, function( result ) {
            }, function( is_error) { } );
        },

        sendEndTurn: function()
        {
            this.ajaxcall( "/crashandgrab/crashandgrab/actNoZag.html", { lock: true }, this, function( result ) {
            }, function( is_error) { } );
        },

        getDegreesRotated: function(directionAsString)
        {
          console.log("rotating direction:"+directionAsString);
            switch( directionAsString )
            {
                case this.LEFT_DIRECTION:
                console.log("45");
                  return 45;
                case this.UP_DIRECTION:
                console.log("315");
                  return 315;
                case this.RIGHT_DIRECTION:
                console.log("225");
                  return 225;
                case this.DOWN_DIRECTION:
                console.log("135");
                  return 135;
            }
        },

        getClockwiseInteger: function( clockwiseText )
    		{
    			if(clockwiseText=="clockwise")
    			{
    				return 0;
    			}
    			else {
    				return 1;
    			}
    		},

        convertSelectedCardsToString: function()
        {
            var discards = this.playerHand.getSelectedItems();

            // if we have gotten here, the selected cards are valid to discard
            var cardsDiscarded = '';
            for( var i in discards )
            {
                cardsDiscarded += discards[i].id+';'; // put the card IDs in a semicolon-delimited list
            }

            return cardsDiscarded;
        },


        ///////////////////////////////////////////////////
        //// Player's action

        /*

            Here, you are defining methods to handle player's action (ex: results of mouse click on
            game objects).

            Most of the time, these methods:
            _ check the action is possible at this game state.
            _ make a call to the game server

        */

        /* Example:

        onMyMethodToCall1: function( evt )
        {
            console.log( 'onMyMethodToCall1' );

            // Preventing default browser reaction
            dojo.stopEvent( evt );

            // Check that this action is possible (see "possibleactions" in states.inc.php)
            if( ! this.checkAction( 'myAction' ) )
            {   return; }

            this.ajaxcall( "/crashandgrab/crashandgrab/myAction.html", {
                                                                    lock: true,
                                                                    myArgument1: arg1,
                                                                    myArgument2: arg2,
                                                                    ...
                                                                 },
                         this, function( result ) {

                            // What to do after the server call if it succeeded
                            // (most of the time: nothing)

                         }, function( is_error) {

                            // What to do after the server call in anyway (success or failure)
                            // (most of the time: nothing)

                         } );
        },
        */

        onNoClaimZag: function()
        {
            this.sendSkipZag();

            //this.mustChooseZagDiscards = false;
            //this.askedZag = true;
            //this.removeActionButtons(); // remove any action buttons that are currently showing
            //dojo.destroy('askClaimZagYes_button'); // destroy Yes button
            //dojo.destroy('askClaimZagNo_button'); // destroy No button
            //this.showChooseZigButtons();
        },

        onOstrichRespawn: function()
        {
            this.sendRespawnRequest(); // tell the server to respawn the active ostrich
        },

        onDraw2Zigs: function()
        {
            // make sure we are allowed to take this action
            if( ! this.checkAction( 'clickDraw2Zigs' ) )
            { return; }

            this.sendDraw2ZigsRequest(); // tell server this player wants to draw 2 cards
        },

        onChooseToStealGarment: function()
        {
            // make sure we are allowed to take this action
            if( ! this.checkAction( 'stealGarmentClick' ) )
            { return; }

            this.sendAskWhichGarmentToStealRequest();

            //this.removeActionButtons(); // remove any action buttons that are currently showing
            //dojo.destroy('steal_button'); // destroy Steal button
            //dojo.destroy('draw_button'); // destroy Draw 2 button

            //this.addActionButton( 'stealCancel_button', _('Cancel'), 'onStealGarmentCancel', null, false, 'red' );

            //this.setPlayerInstructions('Choose the garment you wish to steal.');
        },

        onDiscard3ClaimRED: function()
        {
            var cardsDiscarded = this.convertSelectedCardsToString();

            this.sendClaimZag(this.REDCOLOR, cardsDiscarded); // tell the server which cards we are discarding for the zag and which ostrich is doing it
        },

        onDiscard3ClaimBLUE: function()
        {
            var cardsDiscarded = this.convertSelectedCardsToString();

            this.sendClaimZag(this.BLUECOLOR, cardsDiscarded); // tell the server which cards we are discarding for the zag and which ostrich is doing it
        },

        onDiscard3ClaimGREEN: function()
        {
            var cardsDiscarded = this.convertSelectedCardsToString();

            this.sendClaimZag(this.GREENCOLOR, cardsDiscarded); // tell the server which cards we are discarding for the zag and which ostrich is doing it
        },

        onDiscard3ClaimYELLOW: function()
        {
            var cardsDiscarded = this.convertSelectedCardsToString();

            this.sendClaimZag(this.YELLOWCOLOR, cardsDiscarded); // tell the server which cards we are discarding for the zag and which ostrich is doing it
        },

        onDiscard3ClaimPURPLE: function()
        {
            var cardsDiscarded = this.convertSelectedCardsToString();

            this.sendClaimZag(this.PURPLECOLOR, cardsDiscarded); // tell the server which cards we are discarding for the zag and which ostrich is doing it
        },

        onDiscard3ClaimORANGE: function()
        {
            var cardsDiscarded = this.convertSelectedCardsToString();

            this.sendClaimZag(this.ORANGECOLOR, cardsDiscarded); // tell the server which cards we are discarding for the zag and which ostrich is doing it
        },

        onDiscard3Cancel: function()
        {
            this.mustChooseZagDiscards = false;
        },

        onClick_energyReward: function( evt )
        {
            console.log( "onClick_energyReward" );
            var node = evt.currentTarget.id;
            console.log( "node:"+node );
            var saucerWhoCrashed = node.split('_')[2];

            this.ajaxcall( "/crashandgrab/crashandgrab/actExecuteEnergyRewardSelection.html", { saucerWhoCrashed: saucerWhoCrashed, lock: true }, this, function( result ) {
            }, function( is_error) { } );
        },

        onClick_MoveCard_sunDirection: function( evt )
        {
            var node = evt.currentTarget.id; // sun_1_button
            console.log( "node:"+node );
            var saucerNumber = node.split('_')[1];

            this.chooseMoveCardDirection(this.UP_DIRECTION, saucerNumber);
        },

        onClick_MoveCard_asteroidsDirection: function( evt )
        {
            var node = evt.currentTarget.id; // sun_1_button
            console.log( "node:"+node );
            var saucerNumber = node.split('_')[1];

            this.chooseMoveCardDirection(this.RIGHT_DIRECTION, saucerNumber);
        },

        onClick_MoveCard_meteorDirection: function( evt )
        {
            var node = evt.currentTarget.id; // sun_1_button
            console.log( "node:"+node );
            var saucerNumber = node.split('_')[1];

            this.chooseMoveCardDirection(this.DOWN_DIRECTION, saucerNumber);
        },

        onClick_MoveCard_constellationDirection: function( evt )
        {
            var node = evt.currentTarget.id; // sun_1_button
            console.log( "node:"+node );
            var saucerNumber = node.split('_')[1];

            this.chooseMoveCardDirection(this.LEFT_DIRECTION, saucerNumber);
        },

        onClick_sunDirection: function()
        {
            console.log( "onClick_sunDirection" );
            this.sendDirectionClick(this.UP_DIRECTION);

        },

        onClick_asteroidsDirection: function()
        {
            console.log( "onClick_asteroidsDirection" );
            this.sendDirectionClick(this.RIGHT_DIRECTION);
        },

        onClick_meteorDirection: function()
        {
            console.log( "onClick_meteorDirection" );
            this.sendDirectionClick(this.DOWN_DIRECTION);
        },

        onClick_constellationDirection: function()
        {
            console.log( "onClick_constellationDirection" );
            this.sendDirectionClick(this.LEFT_DIRECTION);
        },

        onClick_skipGiveAwayCrewmember: function()
        {
            this.ajaxcall( "/crashandgrab/crashandgrab/actSkipGiveAwayCrewmember.html", { lock: true }, this, function( result ) {
            }, function( is_error) { } );
        },

        onClick_skipStealCrewmember: function()
        {
            this.ajaxcall( "/crashandgrab/crashandgrab/actSkipStealCrewmember.html", { lock: true }, this, function( result ) {
            }, function( is_error) { } );
        },

        onClick_skipPassCrewmember: function()
        {
            this.ajaxcall( "/crashandgrab/crashandgrab/actSkipPassCrewmember.html", { lock: true }, this, function( result ) {
            }, function( is_error) { } );
        },

        onClick_skipTakeCrewmember: function()
        {
            this.ajaxcall( "/crashandgrab/crashandgrab/actSkipTakeCrewmember.html", { lock: true }, this, function( result ) {
            }, function( is_error) { } );
        },

        // SKIP ALL START OF TURN UPGRADES
        onClick_skipActivateStartOfTurnUpgrade: function()
        {
            this.ajaxcall( "/crashandgrab/crashandgrab/actSkipActivateStartOfTurnUpgrade.html", { lock: true }, this, function( result ) {
            }, function( is_error) { } );
        },

        // SKIP A SPECIFIC START OF TURN UPGRADE AFTER YOU CLICKED TO USE IT
        onClick_skipActivateSpecificStartOfTurnUpgrade: function()
        {
            this.ajaxcall( "/crashandgrab/crashandgrab/actSkipActivateSpecificStartOfTurnUpgrade.html", { lock: true }, this, function( result ) {
            }, function( is_error) { } );
        },

        onClick_skipActivateEndOfTurnUpgrade: function()
        {
            this.ajaxcall( "/crashandgrab/crashandgrab/actSkipActivateEndOfTurnUpgrade.html", { lock: true }, this, function( result ) {
            }, function( is_error) { } );
        },

        onClick_skipActivateSpecificEndOfTurnUpgrade: function(evt)
        {
            var node = evt.currentTarget.id; // skipButton_2
            console.log( "node:"+node );
            var collectorNumber = node.split('_')[1];

            this.ajaxcall( "/crashandgrab/crashandgrab/actSkipActivateSpecificEndOfTurnUpgrade.html", { collectorNumber: collectorNumber, lock: true }, this, function( result ) {
            }, function( is_error) { } );
        },

        onClick_activateUpgrade: function( evt )
        {
            var node = evt.currentTarget.id; // upgradeButton_2
            console.log( "node:"+node );
            var collectorNumber = node.split('_')[1];

            this.ajaxcall( "/crashandgrab/crashandgrab/actActivateUpgrade.html", { collectorNumber: collectorNumber, lock: true }, this, function( result ) {
            }, function( is_error) { } );
        },

        onZagBridge: function()
        {
          console.log( "onZagBridge" );

          if(this.gamedatas.saucer2 == this.lastMovedOstrich)
          {
              this.saucer2HasZag = false; // take away the zag
          }
          else {
              this.saucer1HasZag = false; // take away the zag
          }

          dojo.destroy('zag_'+this.lastMovedOstrich); // destroy zag token

          this.sendZagMove("BRIDGE");
        },

        onZagCactus: function()
        {
          console.log( "onZagCactus" );

          if(this.gamedatas.saucer2 == this.lastMovedOstrich)
          {
              this.saucer2HasZag = false; // take away the zag
          }
          else {
              this.saucer1HasZag = false; // take away the zag
          }

          dojo.destroy('zag_'+this.lastMovedOstrich); // destroy zag token

          this.sendZagMove("CACTUS");
        },

        onZagRiver: function()
        {
          console.log( "onZagRiver" );

          if(this.gamedatas.saucer2 == this.lastMovedOstrich)
          {
              this.saucer2HasZag = false; // take away the zag
          }
          else {
              this.saucer1HasZag = false; // take away the zag
          }

          dojo.destroy('zag_'+this.lastMovedOstrich); // destroy zag token

          this.sendZagMove("RIVER");
        },

        onZagMountain: function()
        {
          console.log( "onZagMountain" );

          if(this.gamedatas.saucer2 == this.lastMovedOstrich)
          {
              this.saucer2HasZag = false; // take away the zag
          }
          else {
              this.saucer1HasZag = false; // take away the zag
          }

          dojo.destroy('zag_'+this.lastMovedOstrich); // destroy zag token

          this.sendZagMove("MOUNTAIN");
        },

        onDirectionZigChoiceBridge: function()
        {
          console.log( "onDirectionZigChoiceBridge" );

          this.sendZigDirectionChoice("BRIDGE");
        },

        onDirectionZigChoiceCactus: function()
        {
          console.log( "onDirectionZigChoiceCactus" );

          this.sendZigDirectionChoice("CACTUS");

        },

        onDirectionZigChoiceRiver: function()
        {
          console.log( "onDirectionZigChoiceRiver" );

          this.sendZigDirectionChoice("RIVER");
        },

        onDirectionZigChoiceMountain: function()
        {
          console.log( "onDirectionZigChoiceMountain" );

          this.sendZigDirectionChoice("MOUNTAIN");
        },

        onDirectionChoiceCancel: function()
        {
          console.log( "onDirectionChoiceCancel" );

          this.resetPlanPhaseVariables();


          //TODO: send the card back into your hand stock too

        },

        onStartZigPhaseOver: function()
        {
            this.sendStartZigPhaseOver();
        },

        onOstrichZigChoice_RED: function()
        {
            var selectedCards = this.playerHand.getSelectedItems(); // get the cards that were selected

            var cardId = 0;
            for( var i in selectedCards )
            { // go through cards but there should only be 1
                cardId = selectedCards[i].id;
            }

            this.sendZigChoice("f6033b", cardId);
        },

        onOstrichZigChoice_BLUE: function()
        {
            var selectedCards = this.playerHand.getSelectedItems(); // get the cards that were selected

            var cardId = 0;
            for( var i in selectedCards )
            { // go through cards but there should only be 1
                cardId = selectedCards[i].id;
            }

            this.sendZigChoice("0090ff", cardId);
        },

        onOstrichZigChoice_GREEN: function()
        {
            var selectedCards = this.playerHand.getSelectedItems(); // get the cards that were selected

            var cardId = 0;
            for( var i in selectedCards )
            { // go through cards but there should only be 1
                cardId = selectedCards[i].id;
            }

            this.sendZigChoice("01b508", cardId);
        },

        onOstrichZigChoice_YELLOW: function()
        {
            var selectedCards = this.playerHand.getSelectedItems(); // get the cards that were selected

            var cardId = 0;
            for( var i in selectedCards )
            { // go through cards but there should only be 1
                cardId = selectedCards[i].id;
            }

            this.sendZigChoice("fedf3d", cardId);
        },

        onOstrichZigChoice_PURPLE: function()
        {
            var selectedCards = this.playerHand.getSelectedItems(); // get the cards that were selected

            var cardId = 0;
            for( var i in selectedCards )
            { // go through cards but there should only be 1
                cardId = selectedCards[i].id;
            }

            this.sendZigChoice("b92bba", cardId);
        },

        onOstrichZigChoice_ORANGE: function()
        {
            var selectedCards = this.playerHand.getSelectedItems(); // get the cards that were selected

            var cardId = 0;
            for( var i in selectedCards )
            { // go through cards but there should only be 1
                cardId = selectedCards[i].id;
            }

            this.sendZigChoice("e77324", cardId);
        },

        onOstrichMoveChoiceRed: function()
        {
            this.ostrichChosen = true;
            this.sendExecuteMove("f6033b");
        },

        onOstrichMoveChoiceBlue: function()
        {
            this.ostrichChosen = true;
            this.sendExecuteMove("0090ff");
        },

        onOstrichMoveChoiceGreen: function()
        {
            this.ostrichChosen = true;
            this.sendExecuteMove("01b508");
        },

        onOstrichMoveChoiceYellow: function()
        {
            this.ostrichChosen = true;
            this.sendExecuteMove("fedf3d");
        },

        onOstrichMoveChoicePurple: function()
        {
            this.ostrichChosen = true;
            this.sendExecuteMove("b92bba");
        },

        onOstrichMoveChoiceOrange: function()
        {
            this.ostrichChosen = true;
            this.sendExecuteMove("e77324");
        },

        onMoveClick: function()
        {
            this.sendExecuteMove("");
        },

        onXValueSelection: function( evt )
        {
            var node = evt.currentTarget.id;
            var value = node.split('_')[0];

            this.sendXValue(value);
        },

        onTrapRed: function()
        { // the player would like to play a trap
            console.log( "DO set a trap on red" );

            if( this.checkAction( 'setTrap', false ) )
            {
                this.resetPlanPhaseVariables(); // now that we are in the trap phase, we can reset PLAN phase variables TODO: we probably don't need this... refactor to remove

                var items = this.trapHand.getAllItems(); // get all traps in this player's hand

                if( items.length > 0 )
                { // there is at least one card in hand
                      var card_id = items[0].id; // the id of the trap card

                      // SET THE TRAP
                      this.ajaxcall( "/crashandgrab/crashandgrab/actSetTrap.html", { ostrich: this.REDCOLOR, lock: true }, this, function( result )
                      { // we successfully set the trap

                          // slide the card from current player's hand to targeted ostrich's mat
                          this.slideToObject( 'trap_hand_'+this.player_id+'_item_'+card_id, 'ostrich_mat_'+this.REDCOLOR).play();

                      }, function( is_error) { } );
                }
            }
        },

        onTrapBlue: function()
        { // the player would like to play a trap
          console.log( "DO set a trap on blue" );

          if( this.checkAction( 'setTrap', false ) )
          {
              this.resetPlanPhaseVariables(); // now that we are in the trap phase, we can reset PLAN phase variables TODO: we probably don't need this... refactor to remove

              var items = this.trapHand.getAllItems(); // get all traps in this player's hand

              if( items.length > 0 )
              { // there is at least one card in hand
                    var card_id = items[0].id; // the id of the trap card

                    // SET THE TRAP
                    this.ajaxcall( "/crashandgrab/crashandgrab/actSetTrap.html", { ostrich: this.BLUECOLOR, lock: true }, this, function( result )
                    { // we successfully set the trap

                        // slide the card from current player's hand to targeted ostrich's mat
                        this.slideToObject( 'trap_hand_'+this.player_id+'_item_'+card_id, 'ostrich_mat_'+this.BLUECOLOR).play();

                    }, function( is_error) { } );
              }
          }
        },

        onTrapGreen: function()
        { // the player would like to play a trap
          console.log( "DO set a trap on green" );

          if( this.checkAction( 'setTrap', false ) )
          {
              this.resetPlanPhaseVariables(); // now that we are in the trap phase, we can reset PLAN phase variables TODO: we probably don't need this... refactor to remove

              var items = this.trapHand.getAllItems(); // get all traps in this player's hand

              if( items.length > 0 )
              { // there is at least one card in hand
                    var card_id = items[0].id; // the id of the trap card

                    // SET THE TRAP
                    this.ajaxcall( "/crashandgrab/crashandgrab/actSetTrap.html", { ostrich: this.GREENCOLOR, lock: true }, this, function( result )
                    { // we successfully set the trap

                        // slide the card from current player's hand to targeted ostrich's mat
                        this.slideToObject( 'trap_hand_'+this.player_id+'_item_'+card_id, 'ostrich_mat_'+this.GREENCOLOR).play();

                    }, function( is_error) { } );
              }
          }
        },

        onTrapYellow: function()
        { // the player would like to play a trap
          console.log( "DO set a trap" );

          if( this.checkAction( 'setTrap', false ) )
          {
              this.resetPlanPhaseVariables(); // now that we are in the trap phase, we can reset PLAN phase variables TODO: we probably don't need this... refactor to remove

              var items = this.trapHand.getAllItems(); // get all traps in this player's hand

              if( items.length > 0 )
              { // there is at least one card in hand
                    var card_id = items[0].id; // the id of the trap card

                    // SET THE TRAP
                    this.ajaxcall( "/crashandgrab/crashandgrab/actSetTrap.html", { ostrich: this.YELLOWCOLOR, lock: true }, this, function( result )
                    { // we successfully set the trap

                        // slide the card from current player's hand to targeted ostrich's mat
                        this.slideToObject( 'trap_hand_'+this.player_id+'_item_'+card_id, 'ostrich_mat_'+this.YELLOWCOLOR).play();

                    }, function( is_error) { } );
              }
          }
        },

        onTrapPurple: function()
        { // the player would like to play a trap
          console.log( "DO set a trap" );

          if( this.checkAction( 'setTrap', false ) )
          {
              this.resetPlanPhaseVariables(); // now that we are in the trap phase, we can reset PLAN phase variables TODO: we probably don't need this... refactor to remove

              var items = this.trapHand.getAllItems(); // get all traps in this player's hand

              if( items.length > 0 )
              { // there is at least one card in hand
                    var card_id = items[0].id; // the id of the trap card

                    // SET THE TRAP
                    this.ajaxcall( "/crashandgrab/crashandgrab/actSetTrap.html", { ostrich: this.PURPLECOLOR, lock: true }, this, function( result )
                    { // we successfully set the trap

                        // slide the card from current player's hand to targeted ostrich's mat
                        this.slideToObject( 'trap_hand_'+this.player_id+'_item_'+card_id, 'ostrich_mat_'+this.PURPLECOLOR).play();

                    }, function( is_error) { } );
              }
          }
        },

        onTrapOrange: function()
        { // the player would like to play a trap
          console.log( "DO set a trap" );

          if( this.checkAction( 'setTrap', false ) )
          {
              this.resetPlanPhaseVariables(); // now that we are in the trap phase, we can reset PLAN phase variables TODO: we probably don't need this... refactor to remove

              var items = this.trapHand.getAllItems(); // get all traps in this player's hand

              if( items.length > 0 )
              { // there is at least one card in hand
                    var card_id = items[0].id; // the id of the trap card

                    // SET THE TRAP
                    this.ajaxcall( "/crashandgrab/crashandgrab/actSetTrap.html", { ostrich: this.ORANGECOLOR, lock: true }, this, function( result )
                    { // we successfully set the trap

                        // slide the card from current player's hand to targeted ostrich's mat
                        this.slideToObject( 'trap_hand_'+this.player_id+'_item_'+card_id, 'ostrich_mat_'+this.ORANGECOLOR).play();

                    }, function( is_error) { } );
              }
          }
        },

        noTrap: function()
        { // the player would NOT like to play a trap
          console.log( "do NOT set a trap" );

          //this.finishedTrapping = true; // this player is done trapping

          this.resetPlanPhaseVariables(); // now that we are in the trap phase, we can reset PLAN phase variables

          this.ajaxcall( "/crashandgrab/crashandgrab/actNoTrap.html", { id: 0, lock: true }, this, function( result ) {
          }, function( is_error) { } );
        },

        onGiveCards: function()
        {
            if( this.checkAction( 'giveCards' ) )
            {
                var items = this.playerHand.getSelectedItems();

                if( items.length != 3 )
                {
                    this.showMessage( _("You must select exactly 3 cards"), 'error' );
                    return;
                }

                // Give these 3 cards
                var to_give = '';
                for( var i in items )
                {
                    to_give += items[i].id+';';
                }
                this.ajaxcall( "/crashandgrab/crashandgrab/giveCards.html", { cards: to_give, lock: true }, this, function( result ) {
                }, function( is_error) { } );
            }
        },

        useBooster: function()
        {
            console.log( "DO use a Booster" );
            // Check that this action is possible (see "possibleactions" in states.inc.php)
            if( ! this.checkAction( 'clickUseBooster' ) )
            {   return; }

            this.ajaxcall( "/crashandgrab/crashandgrab/actUseBooster.html", {
                                                                    lock: true
                                                                 },
                         this, function( result ) {

                            // What to do after the server call if it succeeded
                            // (most of the time: nothing)

                         }, function( is_error) {

                            // What to do after the server call in anyway (success or failure)
                            // (most of the time: nothing)

                         } );


        },

        skipBooster: function()
        {
            console.log( "do NOT use a Booster" );

            // Check that this action is possible (see "possibleactions" in states.inc.php)
            if( !this.checkAction( 'clickSkipBooster' ) )
            {   return; }

            this.ajaxcall( "/crashandgrab/crashandgrab/actSkipBooster.html", {
                                                                    lock: true
                                                                 },
                         this, function( result ) {

                            // What to do after the server call if it succeeded
                            // (most of the time: nothing)

                         }, function( is_error) {

                            // What to do after the server call in anyway (success or failure)
                            // (most of the time: nothing)

                         } );

        },


        // Get card unique identifier so we know at which position
        // in the sprite this card is located.
        // 0 = clockwise X
        // 1 = clockwise 1
        // 2 = clockwise 2
        // 3 = clockwise 3
        // 4 = counterclockwise X
        // 5 = counterclockwise 1
        // 6 = counterclockwise 2
        // 7 = counterclockwise 3
        getCardUniqueId: function( turnOrder, distance )
        {
          var turnOrderOffset = 0;

            if(turnOrder == 'counterclockwise' || turnOrder == 1)
              turnOrderOffset = 4; // we want to skip the first 4 clockwise cards

            var finalPosition = parseInt(turnOrderOffset)+parseInt(distance);
            return finalPosition;
        },

        playRotatedZigOnMat: function( player_id, card_id, distance, clockwise, color, degreesRotated )
        {
            console.log( "Entering playRotatedZigOnMat with player_id " + player_id + " and card_id " + card_id + " distance " + distance + " and clockwise " + clockwise + " and color " + color + " and degrees rotated " + degreesRotated + "." );

            var clockwiseAsInt = this.getClockwiseInteger(clockwise);

            if( player_id == this.player_id )
            { // You played a card. If it exists in your hand, move card from there and show it face-up.

                console.log( "I chose the direction. Clockwise:"+clockwiseAsInt);



                dojo.place(
                    this.format_block( 'jstpl_mymovementcard', {
                        x: this.movementcardwidth*(distance),
                        y: this.movementcardheight*(clockwiseAsInt),
                        player_id: player_id
                    } ), 'zig_holder_'+color );


                // corresponding item

                if( $('myhand_item_'+card_id) )
                { // this card is in my hand
                    console.log('Move card FROM myhand_item_'+card_id+' to mymovementcard_'+player_id+'.');
                    //this.placeOnObject( 'cardontable_'+player_id, 'myhand_item_'+card_id ); // teleport card FROM, TO
                    this.playerHand.removeFromStockById( card_id ); // remove card from the stock
                    this.slideToObject( 'myhand_item_'+card_id, 'mymovementcard_'+player_id ).play(); // slide card FROM, TO
                }

                this.rotateTo( 'mymovementcard_'+player_id, degreesRotated );
                $('mymovementcard_'+player_id).style.removeProperty('left'); // remove left property (doesn't seem to work)

            }
            else
            { // Some opponent played a card. Only show the back of the card on their mat.

              console.log( "Someone ELSE chose the direction. Clockwise:"+clockwiseAsInt);


              dojo.place(
                      this.format_block( 'jstpl_zigback', {
                          x: this.movementcardwidth*(distance),
                          y: this.movementcardheight*(clockwiseAsInt),
                          player_id: player_id
              } ), 'zig_holder_'+color );

              // Move card from player panel
              //this.placeOnObject( 'cardontable_'+player_id, 'overall_player_board_'+player_id );

              this.rotateTo( 'zigback_'+player_id, 45 );

            }

            // In any case: move it to its final destination
            //this.slideToObject( 'cardontable_'+player_id, 'playertablecard_'+player_id ).play();

        },

        drawZig: function( card )
        {
            console.log( "Draw a zig card." );

            var turnOrder = card.type; // clockwise or counterclockwise
            var distance = card.type_arg; // 0, 1, 2, 3
            var locationArg = card.location_arg;
            var typeID = this.getCardUniqueId( turnOrder, distance );

            console.log( "Adding a card with unique ID " + card.id + " and type ID " + typeID + " to the player hand with turnOrder " + turnOrder + " and distance " + distance + " and location_arg " + locationArg + "." );

            this.playerHand.addToStockWithId( typeID, card.id );

            // In any case: move it to its final destination
            //this.slideToObject( 'cardontable_'+player_id, 'playertablecard_'+player_id ).play();

        },

        drawTrap: function( card )
        {
            console.log( "Draw a trap card." );

            var cardName = card.type; // Twirlybird, Scrambler
            var cardID = card.type_arg; // unique id like 0, 1, 2, 3
            var color = card.location; // color like ff0000
            var owner = card.location_arg; // player ID

            console.log( "Adding a trap card with unique ID " + card.id + " and card ID " + cardID + " and name " + cardName + " and color " + color + " and owner " + owner + " to the player's hand." );

            this.trapHand.addToStockWithId( cardID, card.id );

            // In any case: move it to its final destination
            //this.slideToObject( 'cardontable_'+player_id, 'playertablecard_'+player_id ).play();

        },

        giveOtherPlayerTrapCard: function( playerWhoGetsIt )
        {
            // there is only one card in this trap back "sprite" and it is at 0,0
            var rowId = 0;
            var columnId = 0;

            var element = document.getElementById("trapBack_" + playerWhoGetsIt); // get this element in case it already exists

            if(typeof(element) != 'undefined' && element != null)
            { // the player is already showing a trap card so don't add another
                console.log("trapBack_" + playerWhoGetsIt + " already exists so we won't add another")
            }
            else
            { // it doesn't exist yet
                dojo.place(
                      this.format_block( 'jstpl_trapBack', {
                          x: this.upgradecardwidth*(rowId),
                          y: this.upgradecardheight*(columnId),
                          player_id: playerWhoGetsIt
                } ), 'upgrade_hand_f6033b' );
            }
        },



        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

        /*
            setupNotifications:

            In this method, you associate each of your game notifications with your local method to handle it.

            Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                  your crashandgrab.game.php file.

        */
        setupNotifications: function()
        {
            console.log( 'notifications subscriptions setup' );

            // TODO: here, associate your game notifications with local methods

            // Example 1: standard notification handling
            // dojo.subscribe( 'cardPlayed', this, "notif_cardPlayed" );

            // Example 2: standard notification handling + tell the user interface to wait
            //            during 3 seconds after calling the method in order to let the players
            //            see what is happening in the game.
            // dojo.subscribe( 'cardPlayed', this, "notif_cardPlayed" );
            // this.notifqueue.setSynchronous( 'cardPlayed', 3000 );
            //

            dojo.subscribe( 'moveCardChosen', this, "notif_moveCardChosen" );
            dojo.subscribe( 'iChoseDirection', this, "notif_iChoseDirection" );
            dojo.subscribe( 'otherPlayerPlayedZig', this, "notif_otherPlayerPlayedZig" );
            dojo.subscribe( 'iStartZigPhaseOver', this, "notif_iStartZigPhaseOver" );
            dojo.subscribe( 'discardPlayedZig', this, "notif_discardPlayedZig" );
            dojo.subscribe( 'drawZigs', this, "notif_drawZigs" );
            dojo.subscribe( 'moveOstrich', this, "notif_moveOstrich" );
            dojo.subscribe( 'zagClaimed', this, "notif_zagClaimed" );
            dojo.subscribe( 'acquireGarment', this, "notif_acquireGarment" );
            dojo.subscribe( 'replacementGarmentChosen', this, "notif_replacementGarmentChosen" );
            dojo.subscribe( 'replacementGarmentSpaceChosen', this, "notif_replacementGarmentSpaceChosen" );
            dojo.subscribe( 'zagUsed', this, "notif_zagUsed" );
            dojo.subscribe( 'xSelected', this, "notif_xSelected" );
            dojo.subscribe( 'crownAcquired', this, "notif_crownAcquired" );
            dojo.subscribe( 'someoneDrewNewTrapCard', this, "notif_someoneDrewNewTrapCard" );
            dojo.subscribe( 'iGetNewTrapCard', this, "notif_iGetNewTrapCard" );
            dojo.subscribe( 'trapDiscarded', this, "notif_trapDiscarded" );
            dojo.subscribe( 'garmentDiscarded', this, "notif_garmentDiscarded" );
            dojo.subscribe( 'otherPlayerTrapSet', this, "notif_otherPlayerTrapSet" );
            dojo.subscribe( 'myTrapSet', this, "notif_myTrapSet" );
            dojo.subscribe( 'executeTrapRotateZig', this, "notif_executeTrapRotateZig" );
            dojo.subscribe( 'executeTrapRotateTile', this, "notif_executeTrapRotateTile" );
            dojo.subscribe( 'moveGarmentToBoard', this, "notif_moveGarmentToBoard" );
            dojo.subscribe( 'updateScore', this, "notif_updateScore" );
            dojo.subscribe( 'updateTurnOrder', this, "notif_updateTurnOrder" );

            dojo.subscribe( 'animateMovement', this, "notif_animateMovement" );
            dojo.subscribe( 'energyAcquired', this, "notif_energyAcquired");
            dojo.subscribe( 'boosterAcquired', this, "notif_boosterAcquired");
            dojo.subscribe( 'upgradePlayed', this, "notif_upgradePlayed");
            dojo.subscribe( 'stealCrewmember', this, "notif_stealCrewmember");
            dojo.subscribe( 'moveCardChange', this, "notif_moveCardChange");
            dojo.subscribe( 'counter', this, "notif_counter");
            dojo.subscribe( 'cardChosen', this, "notif_cardChosen");
            dojo.subscribe( 'cardRevealed', this, "notif_cardRevealed");
            dojo.subscribe( 'confirmedMovement', this, "notif_confirmedMovement");
            dojo.subscribe( 'crewmemberPickup', this, "notif_crewmemberPickup");
        },

        // TODO: from this point and below, you can write your game notifications handling methods

        /*
        Example:

        notif_cardPlayed: function( notif )
        {
            console.log( 'notif_cardPlayed' );
            console.log( notif );

            // Note: notif.args contains the arguments specified during you "notifyAllPlayers" / "notifyPlayer" PHP call

            // TODO: play the card in the user interface.
        },

        */

        // This is sent only to the player who chooses the Move card for the round.
        notif_moveCardChosen: function( notif )
        {
            var ostrichGettingZig = notif.args.ostrich_color; // this is the ostrich moving
            var card_id = notif.args.card_id; // this is the ostrich taking its turn
            var player_id = notif.args.player_id;
            var distance = notif.args.distance;
            var clockwise = notif.args.clockwise;
            var clockwiseAsInt = this.getClockwiseInteger(clockwise);


            // slide the card from hand to mat and then remove it
            if( $('myhand_item_'+card_id) )
            { // this card is in my hand

                    this.playerHand.removeFromStockById( card_id ); // remove card from the stock
            }

            // create the movement card on the ostrich mat at the final destination
            dojo.place(
                    this.format_block( 'jstpl_mymovementcard', {
                        x: this.movementcardwidth*(distance),
                        y: this.movementcardheight*(clockwiseAsInt),
                        player_id: player_id
            } ), 'myhand' );

            this.slideToObject( 'mymovementcard_'+player_id, 'zig_holder_'+ostrichGettingZig ).play(); // slide card FROM hand to mat
        },

        notif_iStartZigPhaseOver: function( notif )
        {
            console.log("Entered notif_iStartZigPhaseOver.");
            var chosenCards = notif.args.chosenCards;
            var playedCards = notif.args.playedCards;

            console.log("chosenCards:" + chosenCards);
            console.log("playedCards:" + playedCards);

            for( var i in chosenCards )
            { // go through the cards that were chosen but their direction wasn't set
                var card = chosenCards[i];
                console.log("chosen:"+card);
                this.drawZig(card); // put card back in player's hand
            }
            for( var i in playedCards )
            { // go through the cards that were chosen and had their direction set
                var card = playedCards[i];
                console.log("played:"+card);
                this.drawZig(card); // put card back in player's hand
            }

            dojo.destroy('mymovementcard_'+this.player_id); // now that we put it back in hand, we can destroy the one that is played
        },

        notif_discardPlayedZig: function( notif )
        {
            console.log("Entered notif_discardPlayedZig.");
            var playerIdOfZig = notif.args.player_id;

            console.log("Destroying " + "mymovementcard_" + playerIdOfZig);
            dojo.destroy('mymovementcard_'+playerIdOfZig); // discard this player's zig if it's their own
            dojo.destroy('zigback_'+playerIdOfZig); // discard this player's zig if it is not their own

        },

        notif_drawZigs: function( notif )
        {
            console.log("Entered notif_drawZigs.");

            for( var i in notif.args.cards )
            { // go through the cards we want to draw
                var card = notif.args.cards[i];
                this.drawZig(card); // draw a zig into this player's hand
            }
        },

        notif_moveOstrich: function( notif )
        {
            console.log("Entered notif_moveOstrich.");

            var ostrichMoving = notif.args.color; // this is the ostrich moving
            var ostrichTakingTurn = notif.args.ostrichTakingTurn; // this is the ostrich taking its turn
            var x = notif.args.x;
            var y = notif.args.y;
            var spaceType = notif.args.spaceType;
            var ostrichMovingHasZag = notif.args.ostrichMovingHasZag;
            var ostrichMovingIsOffCliff = notif.args.ostrichMovingIsOffCliff;

            this.lastMovedOstrich = ostrichMoving; // save which ostrich last moved in case they hit a skateboard and we need to ask them which direction they want to go

            console.log("Moving ostrich " + ostrichMoving + " to X=" + x + " and Y=" + y + " which is space type " + spaceType + ".");
            this.moveOstrichOnBoard(ostrichMoving, ostrichTakingTurn, x, y, spaceType, ostrichMovingHasZag); // move the ostrich of a particular color to a particular space
        },

        notif_iChoseDirection: function( notif )
        {
            console.log("Entered notif_iChoseDirection.");

            var player_id = notif.args.player_id;
            var card_id = notif.args.card_id;
            var distance = notif.args.distance;
            var clockwise = notif.args.clockwise;
            var color = notif.args.color;
            var degreesRotated = notif.args.degreesRotated;

            var clockwiseAsInt = this.getClockwiseInteger(clockwise);

            console.log("rotating mymovementcard_"+player_id+" degrees "+degreesRotated);
            this.rotateTo( 'mymovementcard_'+player_id, degreesRotated );
/*
                dojo.place(
                    this.format_block( 'jstpl_mymovementcard', {
                        x: this.movementcardwidth*(distance),
                        y: this.movementcardheight*(clockwiseAsInt),
                        player_id: player_id
                    } ), 'ostrich_mat_'+color );


                // corresponding item

                if( $('myhand_item_'+card_id) )
                { // this card is in my hand
                    console.log('Move card FROM myhand_item_'+card_id+' to mymovementcard_'+player_id+'.');
                    //this.placeOnObject( 'cardontable_'+player_id, 'myhand_item_'+card_id ); // teleport card FROM, TO
                    this.playerHand.removeFromStockById( card_id ); // remove card from the stock
                    this.slideToObject( 'myhand_item_'+card_id, 'mymovementcard_'+player_id ).play(); // slide card FROM, TO
                }

                this.rotateTo( 'mymovementcard_'+player_id, degreesRotated );
  */
        },

        notif_otherPlayerPlayedZig: function( notif )
        {
            console.log("Entered notif_otherPlayerPlayedZig.");

            var player_id = notif.args.player_id;
            var color = notif.args.color;

            if( player_id != this.player_id )
            { // Some opponent played a card. Only show the back of the card on their mat.


              dojo.place(
                      this.format_block( 'jstpl_zigback', {
                          x: 0,
                          y: 0,
                          player_id: player_id
              } ), 'ostrich_mat_'+color );
            }
        },

        notif_zagClaimed: function( notif )
        {

            console.log("Entered notif_zagClaimed.");

            var player = notif.args.player_id;
            var discardedCards = notif.args.discardedCards;
            var newCards = notif.args.newCards;
            var ostrich = notif.args.ostrich;

            if(player == this.player_id)
            { // we are the player who claimed the zag

                // this ostrich has a zag so we should give them the option to use it after they move on their turn
                if(this.gamedatas.saucer2 == ostrich)
                {
                    this.saucer2HasZag = true; // save that this ostrich has a zag
                }
                else {
                    this.saucer1HasZag = true; // save that this ostrich has a zag
                }

                // DISCARD the cards used
                for( var i in notif.args.discardedCards )
                { // go through the cards we want to discard
                    var card = notif.args.discardedCards[i];
                    //dojo.destroy("myhand_item_" + card);
                    console.log("hiding card " + "myhand_item_" + card);

                    dojo.style("myhand_item_" + card, 'visibility', '');
                    this.playerHand.removeFromStockById( card );
                }

                // DRAW the new cards
                for( var i in notif.args.newCards )
                { // go through the cards we want to draw
                    var card = notif.args.newCards[i];
                    this.drawZig(card); // draw a zig into this player's hand
                }
           }
           else
           { // a different player claimed a zag

                // do we need to do anything?
           }

           // show the zag token on the mat of the ostrich who claimed it
           dojo.place( this.format_block( 'jstpl_zag', {
                 color: ostrich
           } ) , 'zag_holder_'+ostrich );

        },

        notif_energyAcquired: function( notif )
        {
            console.log("Entered notif_energyAcquired.");

            var player = notif.args.player_id;
            var energyPosition = notif.args.energyPosition;
            var saucerColor = notif.args.saucerColor;

            // update the player board
            this.energy_counters[saucerColor].setValue(energyPosition);

            // show the energy token on the mat of the saucer who acquired it
            dojo.place( this.format_block( 'jstpl_energy', {
                 location: saucerColor,
                 position: energyPosition
            } ) , 'energy_pile');


            var objectMovingId = 'energy_'+saucerColor+'_'+energyPosition;
            var destination = 'energy_acquired_'+saucerColor;

            var classToAdd = 'energy_'+energyPosition;
            dojo.addClass( objectMovingId, classToAdd );

            var animationId = this.slideToObject( objectMovingId, destination, this.ANIMATION_SPEED_CREWMEMBER_PICKUP );
            dojo.connect(animationId, 'onEnd', () => {

                // put it in the saucer mat energy holder instead of the energy pile
                this.attachToNewParent( objectMovingId, destination );

                // remove any leftover top or left changes from the sliding
                $(objectMovingId).style.removeProperty('top'); // remove
                $(objectMovingId).style.removeProperty('left'); // remove

                // if this is the active player, enable Move button?
            });
            animationId.play();
        },

        notif_boosterAcquired: function( notif )
        {
            console.log("Entered notif_boosterAcquired.");

            var player = notif.args.player_id;
            var boosterPosition = notif.args.boosterPosition;
            var saucerColor = notif.args.saucerColor;

            // update the player board
            this.booster_counters[saucerColor].setValue(boosterPosition);

            // show the booster token on the mat of the saucer who acquired it
            dojo.place( this.format_block( 'jstpl_booster', {
                 location: saucerColor,
                 position: boosterPosition
            } ) , 'booster_pile');

            var objectMovingId = 'booster_'+saucerColor+'_'+boosterPosition;
            var destination = 'booster_acquired_'+saucerColor;

            var animationId = this.slideToObject( objectMovingId, destination, this.ANIMATION_SPEED_CREWMEMBER_PICKUP );
            dojo.connect(animationId, 'onEnd', () => {

                // put it in the saucer mat energy holder instead of the energy pile
                this.attachToNewParent( objectMovingId, destination );

                // remove any leftover top or left changes from the sliding
                $(objectMovingId).style.removeProperty('top'); // remove
                $(objectMovingId).style.removeProperty('left'); // remove

                // if this is the active player, enable Move button?
            });
            animationId.play();
        },

        notif_moveCardChange: function( notif )
        {
            console.log("Entered notif_moveCardChange.");

            var saucerColor = notif.args.saucerColor;
            var newDirection = notif.args.newDirection;
            var newDistanceType = notif.args.newDistanceType; // 0, 1, 2
            var revealed = notif.args.revealed;

            this.placeMoveCard(saucerColor, newDistanceType, newDirection, revealed);
        },

        notif_upgradePlayed: function( notif )
        {
            console.log("Entered notif_upgradePlayed.");

            var saucerColorPlayingCard = notif.args.saucerColor;
            var playerPlayingCard = notif.args.playerId;
            var collectorNumber = notif.args.collectorNumber;
            var energyQuantity = notif.args.energyQuantity;

            var cardInHandDatabaseId = notif.args.databaseId;

            // update the player board with the value of how many boosters
            this.energy_counters[saucerColorPlayingCard].setValue(energyQuantity);

            this.playUpgradeCard(saucerColorPlayingCard, playerPlayingCard, collectorNumber, cardInHandDatabaseId);
        },

        notif_acquireGarment: function( notif )
        {
            console.log("Entered notif_acquireGarment.");

            var garmentType = notif.args.garmentType;
            var garmentColor = notif.args.garmentColor;
            var ostrichWhoAcquiredIt = notif.args.acquiringOstrich;
            var playerWhoAcquiredIt = notif.args.acquiringPlayer;
            var wearingOrBackpack = notif.args.wearingOrBackpack;
            var garmentX = notif.args.garmentX;
            var garmentY = notif.args.garmentY;
            var numberOfThisType = notif.args.numberOfThisType;

            var garmentHtmlId = 'crewmember_'+garmentType+'_'+garmentColor;
            var garmentLocationHtmlId = 'square_'+garmentX+'_'+garmentY;
            var matLocationHtmlId = 'mat_'+garmentType+"_"+wearingOrBackpack+"_"+numberOfThisType+"_"+ostrichWhoAcquiredIt;
            console.log("garmentHtmlId " + garmentHtmlId + " garmentLocationHtmlId " + garmentLocationHtmlId + " matLocationHtmlId " + matLocationHtmlId);

            // move garment to player's mat
            this.placeOnObject( garmentHtmlId, garmentLocationHtmlId ); // place it where it already is (required to overcome a bug with sliding)
            this.slideToObject( garmentHtmlId, matLocationHtmlId).play(); // slide it to where it goes on their mat
        },

        notif_stealCrewmember: function( notif )
        {
            console.log("Entered notif_stealCrewmember.");

            // get data you will need
            var crewmemberType = notif.args.crewmemberType;
            var crewmemberColor = notif.args.crewmemberColor;
            var saucerColorStealing = notif.args.saucerColorStealing;

            console.log("Initial variables crewmemberType:"+crewmemberType+" crewmemberColor:"+crewmemberColor+" saucerColorStealing:"+saucerColorStealing);

            // determine source and destinations
            var source = 'crewmember_'+crewmemberType+'_'+crewmemberColor;

            console.log('crewmemberType:'+crewmemberType);
            console.log('saucerColorStealing:'+saucerColorStealing);
            var destination = 'player_board_saucer_mat_'+crewmemberType+'_'+saucerColorStealing; // player_board_saucer_mat_pilot_0090ff

            console.log("source:"+source+" destination:"+destination);

            // give it a new parent so it's no longer on the previous saucer mat
            this.attachToNewParent(source, destination);

            // set the speed it will move
            var animationSpeed = this.ANIMATION_SPEED_CREWMEMBER_PICKUP;

            var animationId = this.slideToObject( source, destination, animationSpeed );
            dojo.connect(animationId, 'onEnd', () => {
                // anything we need to do after it slides

                // in 2-player games, we must adjust the location of crewmembers because
                // they get pushed down by the number of upgrades their teammat has
                this.adjustCrewmemberLocationBasedOnUpgrades(saucerColorStealing, crewmemberType);

            });
            animationId.play();

        },

        notif_crewmemberPickup: function( notif )
        {
            console.log("Entered notif_crewmemberPickup.");

            // get data you will need
            var crewmemberType = notif.args.crewmemberType;
            var crewmemberColor = notif.args.crewmemberColor;
            var saucerColor = notif.args.saucerColor;

            console.log("Initial variables crewmemberType:"+crewmemberType+" crewmemberColor:"+crewmemberColor+" saucerColorStealing:"+saucerColor);

            // determine source and destinations
            var source = 'crewmember_'+crewmemberType+'_'+crewmemberColor;

            console.log('crewmemberType:'+crewmemberType);
            console.log('saucerColor:'+saucerColor);
            var destination = 'player_board_saucer_mat_'+crewmemberType+'_'+saucerColor; // player_board_saucer_mat_pilot_0090ff

            console.log("source:"+source+" destination:"+destination);

            // give it a new parent so it's no longer on the previous saucer mat
            this.attachToNewParent(source, destination);

            // give it a played class so it's rotated correctly
            dojo.addClass(source, 'played_'+crewmemberType);

            // set the speed it will move
            var animationSpeed = this.ANIMATION_SPEED_CREWMEMBER_PICKUP;

            var animationId = this.slideToObject( source, destination, animationSpeed );
            dojo.connect(animationId, 'onEnd', () => {
                // anything we need to do after it slides

                // in 2-player games, we must adjust the location of crewmembers because
                // they get pushed down by the number of upgrades their teammat has
                this.adjustCrewmemberLocationBasedOnUpgrades(saucerColor, crewmemberType);

            });
            animationId.play();

        },

        notif_replacementGarmentChosen: function( notif )
        {
            console.log("Entered notif_replacementGarmentChosen.");

            var garmentType = notif.args.garmentType;
            var garmentColor = notif.args.garmentColor;

            var garmentHtmlId = 'crewmember_'+garmentType+'_'+garmentColor;

            console.log('moving ' + garmentHtmlId+' to replacement_garment_chosen_holder');

            this.placeOnObject( garmentHtmlId, 'garment_holder_'+garmentType+'_'+garmentColor ); // place it where it already is (required to overcome a bug with sliding)
            this.slideToObject( garmentHtmlId, 'replacement_garment_chosen_holder').play(); // slide it to the replacement holder
        },

        notif_replacementGarmentSpaceChosen: function( notif )
        {
            console.log("Entered notif_replacementGarmentSpaceChosen.");

            // reset all the garment-choosing values so they are ready for the next time we need to replace them... it's possible the player has to replace 2
            this.chosenGarmentType = null;
            this.chosenGarmentColor = null;


            var garmentType = notif.args.garmentType;
            var garmentColor = notif.args.garmentColor;
            var xDestination = notif.args.xDestination;
            var yDestination = notif.args.yDestination;



            var source = 'crewmember_'+garmentType+'_'+garmentColor;
            var destination = 'square_'+xDestination+'_'+yDestination;

            console.log("moving crewmember to board with source: " + source + " destination: " + destination);

            // give it a new parent so it's no longer in the lost crewmembers
            this.attachToNewParent(source, destination);

            var animationId = this.slideToObject( source, destination, this.ANIMATION_SPEED_CREWMEMBER_PICKUP );
            dojo.connect(animationId, 'onEnd', () => {
            });
            animationId.play();

            //var garmentHtmlId = 'crewmember_'+garmentType+'_'+garmentColor;
            //var garmentLocationHtmlId = 'square_'+xDestination+'_'+yDestination;
            //console.log('moving ' + garmentHtmlId + ' to ' + garmentLocationHtmlId);

            //this.placeOnObject( garmentHtmlId, 'replacement_garment_chosen_holder' ); // place it where it already is (required to overcome a bug with sliding)
            //this.slideToObject( garmentHtmlId, garmentLocationHtmlId).play(); // slide it to the board
        },

        notif_moveGarmentToBoard: function( notif )
        {
            console.log("Entered notif_moveGarmentToBoard.");

            var garmentType = notif.args.garmentType;
            var garmentColor = notif.args.garmentColor;
            var xDestination = notif.args.xDestination;
            var yDestination = notif.args.yDestination;

            var garmentHtmlId = 'crewmember_'+garmentType+'_'+garmentColor;
            var spaceHtmlId = 'square_'+xDestination+'_'+yDestination;

            console.log('moving ' + garmentHtmlId + ' to ' + spaceHtmlId);
            this.slideToObject( garmentHtmlId, spaceHtmlId).play();
            this.slideToObject( garmentHtmlId, spaceHtmlId).play(); // it flies off the screen if we don't do this twice... we could place it first but would need the original x/y passed in
        },

        notif_zagUsed: function( notif )
        {
            console.log("Entered notif_zagUsed.");
            var saucer = notif.args.ostrich;
            var boosterQuantityBeforeUsage = notif.args.boosterQuantity;

            var objectMovingId = 'booster_'+saucer+'_'+boosterQuantityBeforeUsage;
            var destination = 'booster_pile';

            console.log('objectMovingId:'+objectMovingId);

            if( $(objectMovingId ) )
            { // it exists

                this.slideToObjectAndDestroy( objectMovingId, destination, this.ANIMATION_SPEED_CREWMEMBER_PICKUP );

            }

            // update the player board with the value of how many boosters (must subtract one because it was the before usage quantity)
            this.booster_counters[saucer].setValue(boosterQuantityBeforeUsage - 1);
        },

        notif_xSelected: function( notif )
        {
            console.log("Entered notif_xSelected.");
            var ostrich = notif.args.ostrich;
            var value = notif.args.xValue;

            // I don't think we actually need to do anything... but having this puts a note in the message log with details
        },

        notif_crownAcquired: function( notif )
        {
            console.log("Entered notif_crownAcquired.");
            var ostrichColor = notif.args.color;

            this.moveCrownToPlayerBoard(ostrichColor);
        },

        notif_iGetNewTrapCard: function( notif )
        {
            console.log("Entered notif_iGetNewTrapCard.");

            for( var i in notif.args.cards )
            { // go through the cards we want to draw
                var card = notif.args.cards[i];
                this.drawTrap(card); // draw a zig into this player's hand
            }
        },

        notif_someoneDrewNewTrapCard: function( notif )
        {
            console.log("Entered notif_someoneDrewNewTrapCard.");

            var playerWhoAcquiredIt = notif.args.acquiringPlayer;
            if(playerWhoAcquiredIt != this.player_id)
            { // skip the player who acquired it because they were already notified

                this.giveOtherPlayerTrapCard(playerWhoAcquiredIt);
            }
        },

        notif_trapDiscarded: function( notif )
        {
            console.log("Entered notif_trapDiscarded.");

            var discardedCard = notif.args.discardedCard;
            var playerDiscarding = notif.args.playerDiscarding;
            if(playerDiscarding == this.player_id)
            { // this is the player who discarded it

                var idToDiscard = "trap_hand_" + playerDiscarding + "_item_" + discardedCard;
                //console.log("attempting to hide " + idToDiscard);
                //dojo.style(idToDiscard, 'visibility', '');
                this.trapHand.removeFromStockById( discardedCard );

                dojo.destroy(idToDiscard);
            }
            else
            { // another player is discarding it
                var trapBackIdToDiscard = "trapBack_" + playerDiscarding;
                dojo.destroy(trapBackIdToDiscard);
            }
        },

        notif_garmentDiscarded: function( notif )
        {
          console.log("Entered notif_garmentDiscarded.");

          var garmentColor = notif.args.garmentColor;
          var garmentType = notif.args.garmentType;

          this.slideToObject( 'crewmember_'+garmentType+'_'+garmentColor, 'garment_holder_'+garmentType+'_'+garmentColor).play();

        },

        notif_otherPlayerTrapSet: function( notif )
        {
            console.log("Entered notif_otherPlayerTrapSet.");
            var ostrichTargeted = notif.args.ostrichTargeted; // color of the ostrich targeted (f6033b)
            var nameOfOstrichTargeted = notif.args.nameOfOstrichTargeted; // the friendly name of the ostrich targted (red)
            var playerWhoPlayedTrap = notif.args.playerWhoPlayed; // the player who played the trap
            var player_name = notif.args.player_name; // the name of the player who played the trap on the ostrich

            // move the card back image from player_name's hand and put it on ostrichTargeted's mat
            var trapBackToMoveId = 'trapBack_'+playerWhoPlayedTrap;
            var originalCardLocation = 'trap_hand_'+playerWhoPlayedTrap;


            console.log("trapBackToMoveId:"+trapBackToMoveId+" and originalCardLocation:"+originalCardLocation+" and new location:ostrich_mat_"+ostrichTargeted);
            this.placeOnObject( trapBackToMoveId, originalCardLocation ); // place it where it already is (required to overcome a bug with sliding)
            this.slideToObject( trapBackToMoveId, 'ostrich_mat_'+ostrichTargeted).play(); // slide it to its destination

            //this.rotateTo( trapBackToMoveId, 45 ); // rotate it to show it's not in their hand
        },

        notif_myTrapSet: function( notif )
        {
            console.log("Entered notif_myTrapSet.");
            var ostrichTargeted = notif.args.ostrichTargeted; // color of the ostrich targeted (f6033b)
            var nameOfOstrichTargeted = notif.args.nameOfOstrichTargeted; // the friendly name of the ostrich targted (red)
            var cardId = notif.args.cardId; // the id of the card used
            var player_name = notif.args.player_name; // the name of the player who played the trap on the ostrich

            // my trap card is already on the player's mat, but we should put it in the correct order
        },

        notif_executeTrapRotateZig: function( notif )
        {
            console.log("Entered notif_executeTrapRotateZig.");
            //var degreesRotated = notif.args.degreesRotated; // the degrees rotated
            //var degreesRotatedAsInt = parseInt(degreesRotated);
            //var isRotationClockwise = notif.args.isRotationClockwise; // true if we rotate clockwise
            var newDirection = notif.args.newDirectionValue; // new direction we want showing
            var degreesRotated = this.getDegreesRotated(newDirection); // the number of degrees rotated based on direction

            var playerTrapped = notif.args.playerTrapped; // the player who had the trap played on them

            console.log("newDirection="+newDirection+" degreesRotated="+degreesRotated);

            if(playerTrapped == this.player_id)
            { // we are the player who had the trap played on them
                console.log("player trapped");
/*
                if(isRotationClockwise  === 'true')
                { // we want to rotate clockwise
                    console.log("rotating clockwise");
                }
                else
                { // we want to rotate counter-clockwise
                    console.log("rotating counter-clockwise");
                    degreesRotatedAsInt = -1*degreesRotatedAsInt; // rotate the other direction
                }
*/
                //this.rotateTo( 'mymovementcard_'+playerTrapped, degreesRotatedAsInt );
                this.rotateTo( 'mymovementcard_'+playerTrapped, degreesRotated );
            }
            else {
              console.log("NOT player trapped");
            }
        },

        notif_executeTrapRotateTile: function( notif )
        {
            console.log("Entered notif_executeTrapRotateTile.");
            var tileNumber = notif.args.tileNumber; // the tile number rotated
            var tilePosition = notif.args.tilePosition; // the tile position rotated
            var side = notif.args.tileSide;
            var oldRotation = notif.args.oldDegreeRotation;
            var newRotation = notif.args.newDegreeRotation;

            var tileId = 'board_tile_'+tilePosition;

            var classToRemove = 'board_tile_image_'+tileNumber+'_'+side+'_'+oldRotation;
            dojo.removeClass( tileId, classToRemove ); // remove existing style like board_tile_image_1_A_1

            var classToAdd = 'board_tile_image_'+tileNumber+'_'+side+'_'+newRotation;
            dojo.addClass( tileId, classToAdd ); // add style like board_tile_image_1_A_2

            console.log("removed class " + classToRemove + " and added class " + classToAdd);
        },

        notif_updateScore: function(notif)
        {
          console.log("Entered notif_updateScore.");
            this.scoreCtrl[notif.args.player_id].setValue(notif.args.player_score);
        },

        notif_updateTurnOrder: function(notif)
        {
            console.log("Entered notif_updateTurnOrder.");
            var turnOrder = notif.args.turnOrder;

            this.updateTurnOrder(turnOrder);
        },

        notif_cardChosen: function(notif)
        {
            console.log("Entered notif_cardChosen.");

            var saucerChoosing = notif.args.saucer_choosing;

            dojo.place(
                    this.format_block( 'jstpl_moveCardBack', {
                        x: this.getMoveCardBackgroundX(saucerChoosing),
                        y: this.getMoveCardBackgroundY(saucerChoosing),
                        color: saucerChoosing
            } ), 'player_board_move_card_holder_'+saucerChoosing );

            // Move card from player panel
            //this.placeOnObject( 'cardontable_'+player_id, 'overall_player_board_'+player_id );
console.log("notif_cardChosen ()"+'move_card_back_'+saucerChoosing+') is being rotated 45 degrees');
            this.rotateTo( 'move_card_back_'+saucerChoosing, 45 );
        },

        notif_cardRevealed: function(notif)
        {
            console.log("Entered notif_cardRevealed.");

            var saucerColor = notif.args.saucer_color;
            var distanceType = notif.args.distance_type;
            var direction = notif.args.direction;




            var moveCardBackHtmlId = 'move_card_back_'+saucerColor;
            if(document.getElementById(moveCardBackHtmlId))
            { // the move card back is there right now

                // destroy it
                dojo.destroy(moveCardBackHtmlId);
            }

            var destinationHtmlId = 'player_board_move_card_holder_'+saucerColor;
            var moveCardFrontHtmlId = 'move_card_'+distanceType+'_'+saucerColor;
            console.log("placing move card in:"+destinationHtmlId);

            if(saucerColor == this.gamedatas.saucer1 || saucerColor == this.gamedatas.saucer2)
            { // this is my saucer

                // should already be there

                // move the card
                //if( $(moveCardFrontHtmlId) )
                //{ // this card exists
                //    this.slideMoveCard(moveCardFrontHtmlId, saucerColor, direction);
                //}

            }
            else
            { // this is an opponent saucer

                // place the card

                dojo.place( this.format_block( 'jstpl_moveCard', {
                    x: distanceType * this.movementcardwidth,
                    y: 0,
                    color: saucerColor,
                    distance: distanceType
                } ) , destinationHtmlId );

                // Move card from player panel
                //this.placeOnObject( 'cardontable_'+player_id, 'overall_player_board_'+player_id );



            }

            // rotate it
            var degreesToRotate = this.getDegreesRotated(direction);
            console.log('cardRevealed:'+moveCardFrontHtmlId+' and direction:'+direction+' degrees:'+degreesToRotate);
            this.rotateTo( moveCardFrontHtmlId, this.getDegreesRotated(direction) );
        },

        notif_confirmedMovement: function(notif)
        {
          console.log("Entered notif_confirmedMovement.");
            var saucerColor = notif.args.saucer_color;
            console.log("notif_confirmedMovement saucerColor:"+saucerColor);

            // return cards to hand (mine) or destroy them (opponents)
            this.returnMoveCardToHandOfSaucer(saucerColor);
        },

        notif_animateMovement: function(notif)
        {
            var eventStack = notif.args.moveEventList;


            this.animateEvents(eventStack);
        },

        notif_counter: function(notif) {
            this.updateCounters(notif.args.counters);
        }

   });
});
