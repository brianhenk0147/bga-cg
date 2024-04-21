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
    "ebg/stock"
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
            this.GRAYCOLOR = "c9d2db";

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

            // saucers this player controls
            this.saucer1 = "";
            this.saucer2 = "";
            this.lastMovedOstrich = ""; // this is the color of the ostrich that was last moved

            this.playerSaucerMoves = null; // save the list of players/saucers/move cards/spaces so it can be used elsewhere

            this.ANIMATION_SPEED = 300; // the speed of all the animations (lower is faster)

            // zig cards
            this.movementcardwidth = 82;
            this.movementcardheight = 82;

            // upgrade cards
            this.trapHand = null;
            this.upgradecardwidth = 82;
            this.upgradecardheight = 58;

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
                this.placePlayerBoardForSaucer(saucer.owner, saucer.color); // put everything for this saucer on the player's board
            }



            this.placeBoard(numberOfPlayers);


            // move cards in hand
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
            // First Param: css class to target
            // Second Param: type of events
            // Third Param: the method that will be called when the event defined by the second parameter happen
            this.addEventToClass('move_card', 'onclick', 'onClick_moveCard'); // add the click handler to all move cards

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

var color = 'f6033b';
            this.trapHand = new ebg.stock(); // create the place we will store the trap cards the player has drawn
            this.trapHand.create( this, $('upgrade_hand_'+color), this.upgradecardwidth, this.upgradecardheight );
            this.trapHand.image_items_per_row = 4; // the number of card images per row in the sprite image
            dojo.connect( this.trapHand, 'onChangeSelection', this, 'onTrapHandSelectionChanged' ); // when the onChangeSelection event is triggered on the HTML, call our callback function onTrapHandSelectionChanged below

            // Create one of each type of trap card so we can add them to the playerTrapHand stock as needed throughout
            // the game and it will know what we're talking about when we do.
            // ARGUMENTS:
            // type id
            // weight of the card (for sorting purpose)
            // the URL of our CSS sprite
            // the position of our card image in the CSS sprite
            this.trapHand.addItemType( 0, 0, g_gamethemeurl+'img/ship_upgrades_82_58.jpg', 0 );
            this.trapHand.addItemType( 1, 1, g_gamethemeurl+'img/ship_upgrades_82_58.jpg', 1 );
            this.trapHand.addItemType( 2, 2, g_gamethemeurl+'img/ship_upgrades_82_58.jpg', 2 );
            this.trapHand.addItemType( 3, 3, g_gamethemeurl+'img/ship_upgrades_82_58.jpg', 3 );
            this.trapHand.addItemType( 4, 4, g_gamethemeurl+'img/ship_upgrades_82_58.jpg', 4 );
            this.trapHand.addItemType( 5, 5, g_gamethemeurl+'img/ship_upgrades_82_58.jpg', 5 );
            this.trapHand.addItemType( 6, 6, g_gamethemeurl+'img/ship_upgrades_82_58.jpg', 6 );
            this.trapHand.addItemType( 7, 7, g_gamethemeurl+'img/ship_upgrades_82_58.jpg', 7 );
            this.trapHand.addItemType( 8, 8, g_gamethemeurl+'img/ship_upgrades_82_58.jpg', 8 );
            this.trapHand.addItemType( 9, 9, g_gamethemeurl+'img/ship_upgrades_82_58.jpg', 9 );
            this.trapHand.addItemType( 10, 10, g_gamethemeurl+'img/ship_upgrades_82_58.jpg', 10 );
            this.trapHand.addItemType( 11, 11, g_gamethemeurl+'img/ship_upgrades_82_58.jpg', 11 );

            // trap cards in player's hand
            for( var i in this.gamedatas.trapHands )
            {
                var card = this.gamedatas.trapHands[i];
                var owner = card.location_arg;

                if(owner == this.player_id)
                { // this is MY trap card
                    this.drawTrap(card); // draw a trap card into your hand
                }
                else
                { // this is someone else's trap card
                    this.giveOtherPlayerTrapCard(owner); // put the card back out by their player mat
                }
            }


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

            this.saucer1 = ""; // clear out the global variable for saucer1
            this.saucer2 = ""; // clear out the global variable for saucer2
            for( var i in gamedatas.ostrich )
            { // go through each ostrich
                var singleOstrich = gamedatas.ostrich[i];


                if(singleOstrich.owner == this.player_id)
                { // this is my ostrich

                    if(this.saucer1 == "")
                    { // we don't have our first ostrich yet
                        this.saucer1 = singleOstrich.color;
                        console.log("Our first ostrich will have color " + singleOstrich.color);
                        this.hasMultipleOstriches = false; // set the global variable that we are using a single ostrich that will be used everywhere

                        if(singleOstrich.has_zag == 1)
                        {
                            this.saucer1HasZag = true;
                        }
                        else {
                            this.saucer1HasZag = false;
                        }

                    }
                    else
                    { // we already set our first ostrich so this must be our second ostrich
                        this.saucer2 = singleOstrich.color;
                        this.hasMultipleOstriches = true; // set the global variable that we are using multiple ostriches that will be used everywhere
                        console.log("Our second ostrich will have color " + singleOstrich.color);

                        if(singleOstrich.has_zag == 1)
                        {
                            this.saucer2HasZag = true;
                        }
                        else {
                            this.saucer2HasZag = false;
                        }

                    }
                }

                if(singleOstrich.ostrich_has_crown == 1)
                { // this ostrich has the crown
                    console.log("gets crown:" + singleOstrich.color);
                    this.putCrownOnPlayerBoard(singleOstrich.color); // place it on their player board on the right

                    var arrowX = 0;
                    if(singleOstrich.ostrich_last_turn_order == 1)
                    { // we're going counter-clockwise
                      arrowX = 45;
                    }

                    for( var player_id in gamedatas.players )
                    {
                        this.putArrowOnPlayerBoard(arrowX, 0, player_id); // draw a zig into this player's hand
                        console.log("stateName:" + gamedatas.stateName);
                        if(gamedatas.stateName == 'chooseZigPhase' || gamedatas.stateName == 'claimZag' || gamedatas.stateName == 'askTrapBasic' || gamedatas.stateName == 'setTrapPhase')
                        { // hide the turn direction
                            console.log("we need to hideTurnDirection");
                            this.setTurnDirectionArrow(90, 0, player_id); // we don't want to show the turn direction arrow until it has been chosen for this round
                        }
                        else
                        {
                            console.log("we do NOT want to hideTurnDirection");
                        }
                    }
                }

                this.putSaucerOnTile( singleOstrich.x, singleOstrich.y, singleOstrich.owner, singleOstrich.color ); // add the ostrich to the board

                // add a zag token if they have one
                if(singleOstrich.has_zag == 1)
                { // this ostrich has acquired a zag

                    dojo.place( this.format_block( 'jstpl_zag', {
                          color: singleOstrich.color
                    } ) , 'zag_holder_'+singleOstrich.color );
                }
            }

            this.lastMovedOstrich = this.gamedatas.lastMovedOstrich; // this is the color of the ostrich that was last moved

            var currentLocation = "";
            var currentType = "";
            var currentTypeLocationCount = 0;
            for( var i in gamedatas.garment )
            {
                var garment = gamedatas.garment[i];
                var color = garment.garment_color;
                var location = garment.garment_location;
                var typeInt = garment.garment_type;
                var typeString = this.convertGarmentTypeIntToString(typeInt);
                var x = garment.garment_x;
                var y = garment.garment_y;
                var wearingOrBackpack = "wearing";

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
                          garment_type: typeString
                    } ) , 'square_'+x+'_'+y );
                }
                else if(location == "pile")
                { // this garment is in the garment pile
                    dojo.place( this.format_block( 'jstpl_garment', {
                          color: color,
                          garment_type: typeString
                    } ) , 'garment_holder_'+typeString+'_'+color );
                }
                else if(location == "chosen")
                { // this garment is chosen to be replaced but not yet placed
                    dojo.place( this.format_block( 'jstpl_garment', {
                          color: color,
                          garment_type: typeString
                    } ) , 'replacement_garment_chosen_holder' );
                }
                else
                { // this garment has been claimed by a player
                    var matLocationHtmlId = 'mat_'+typeString+"_"+wearingOrBackpack+"_"+currentTypeLocationCount+"_"+location;

                    dojo.place( this.format_block( 'jstpl_garment', {
                          color: color,
                          garment_type: typeString
                    } ) , matLocationHtmlId );
                }

            }

            this.updateTurnOrder(this.gamedatas.turnOrder);

            // First Param: css class to target
            // Second Param: type of events
            // Third Param: the method that will be called when the event defined by the second parameter happen
            this.addEventToClass( "garment", "onclick", "onClickGarment");
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

                onClick_startMove: function( evt )
                {
                    console.log( "Clicked start move." );

                    if(this.isCurrentPlayerActive() && this.checkAction( 'clickMove', true ))
                    { // player is allowed to confirm move (nomessage parameter is true so that an error message is not displayed)

                        this.ajaxcall( "/crashandgrab/crashandgrab/actClickedStartMove.html", {
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

                onClick_selectSaucerToPlace: function( evt )
                {
                    var htmlIdOfButton = evt.currentTarget.id;
                    console.log( "Clicked saucer to place with node "+htmlIdOfButton+"." );
                    var color = htmlIdOfButton.split('_')[1]; // BLUE, RED



                    //if(this.isCurrentPlayerActive() && this.checkAction( 'clickSaucer', true ))
                    //{ // player is allowed to confirm move (nomessage parameter is true so that an error message is not displayed)

                        this.ajaxcall( "/crashandgrab/crashandgrab/actClickedSaucerToPlace.html", {
                                                                                    colorAsFriendlyText: color,
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
                    var htmlIdOfButton = evt.currentTarget.id;
                    console.log( "Clicked saucer to go first with node "+htmlIdOfButton+"." );
                    var color = htmlIdOfButton.split('_')[1]; // BLUE, RED

                    if(this.isCurrentPlayerActive() && this.checkAction( 'clickSaucerToGoFirst', true ))
                    { // player is allowed to confirm move (nomessage parameter is true so that an error message is not displayed)

                        this.ajaxcall( "/crashandgrab/crashandgrab/actClickedSaucerToGoFirst.html", {
                                                                                    colorAsFriendlyText: color,
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

                onClick_confirmMove: function( evt )
                {
                    console.log( "Clicked confirm move button." );

                    if(this.isCurrentPlayerActive() && this.checkAction( 'confirmMove', true ))
                    { // player is allowed to confirm move (nomessage parameter is true so that an error message is not displayed)

                        var saucer1Color = this.saucer1; // 01b508
                        var saucer1Distance = this.CHOSEN_MOVE_CARD_SAUCER_1; // move_card_1_01b508
                        if(saucer1Distance != '')
                          saucer1Distance = saucer1Distance.split('_')[2]; // 0, 1, 2

                        var saucer1Direction = this.CHOSEN_DIRECTION_SAUCER_1; // direction_asteroids
                        if(saucer1Direction != '')
                          saucer1Direction = saucer1Direction.split('_')[1]; // asteroids

                        var saucer2Color = this.saucer2; // 01b508
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

                                            this.unselectAllSaucers();
                                            this.unhighlightAllSaucers();

                                            this.unselectAllMoveCards();
                                            this.unhighlightAllMoveCards();

                                            this.unselectAllDirections();
                                            this.unhighlightAllDirections();

                                            this.unhighlightAllSpaces();

                                         }, function( is_error) {

                                            // What to do after the server call in anyway (success or failure)
                                            // (most of the time: nothing)

                        } );
                    }
                },

                // The player is selecting a card for any of these reasons:
                //     A) They are choosing the move card they will play this round.
                //     B)
                onClick_moveCard: function( evt )
                {
                    var htmlIdOfCard = evt.currentTarget.id;
                    console.log( "A move card was clicked with node "+htmlIdOfCard+"." );
                    var color = htmlIdOfCard.split('_')[3]; // 0090ff

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

                    if(false)
                    { // this card has already been used
                        console.log( "This card was used last round and cannot be used this round." );
                        return;
                    }

                    if(color != this.SAUCER_SELECTED)
                    { // no saucer is selected or a different saucer is selected
                        console.log( "The saucer belonging to this card is not selected." );
                        return;
                    }

                    if( !this.checkPossibleActions('clickDistance') )
                    { // we are not allowed to select cards based on our current state
                        console.log( "The current state does not allow this card to be clicked." );
                        return;
                    }

                    if( (this.saucer1 == color && this.CHOSEN_MOVE_CARD_SAUCER_1 == htmlIdOfCard) ||
                      (this.saucer2 == color && this.CHOSEN_MOVE_CARD_SAUCER_2 == htmlIdOfCard) )
                    { // this move card is already set
                        console.log( "This move card is already set." );
                        return;
                    }

                    // if a different move card is chosen, bring that move card back to your hand
                    if( (this.CHOSEN_MOVE_CARD_SAUCER_1 != '' && this.saucer1 == color && this.CHOSEN_MOVE_CARD_SAUCER_1 != htmlIdOfCard) )
                    {
                        this.returnMoveCardToHandOfSaucer(color);
                        this.unselectAllDirections();
                        this.CHOSEN_MOVE_CARD_SAUCER_1 = '';
                    }

                    if(this.CHOSEN_MOVE_CARD_SAUCER_2 != '' && this.saucer2 == color && this.CHOSEN_MOVE_CARD_SAUCER_2 != htmlIdOfCard)
                    { // a different move card is currently set
                        this.returnMoveCardToHandOfSaucer(color);
                        this.unselectAllDirections();
                        this.CHOSEN_MOVE_CARD_SAUCER_2 = '';
                    }

                    this.MOVE_CARD_SELECTED = htmlIdOfCard; // this is the currently selected move card
                    this.saveMoveCardSelection(color, htmlIdOfCard); // save the move card for this saucer in case it is the final selection

                    this.unselectAllMoveCards(); // UNSELECT ALL other move cards so we can select a different one
                    this.selectSpecificMoveCard(htmlIdOfCard); // SELECT this SPECIFIC move card

                    //this.highlightAllMoveCardsForSaucer(color); // HIGHLIGHT ALL move cards for this saucer
                    //this.unhighlightSpecificMoveCard(htmlIdOfCard); // UNHIGHLIGHT this SPECIFIC move card
                    this.unhighlightAllMoveCards(); // UNhighlight ALL move cards

                    this.highlightDirectionsForSaucer(color); // HIGHLIGHT the DIRECTIONS for the selected saucer and move card
                    this.makeAllDirectionTokensClickable(); // make it clear you can click on directions

                    var moveCardSelected = this.MOVE_CARD_SELECTED.split('_')[2]; // 0, 1, 2
                    this.highlightPossibleMoveSelections(this.playerSaucerMoves, this.player_id, this.SAUCER_SELECTED, moveCardSelected); // highlight possible destinations on board
                },

                onClick_saucerDuringMoveCardSelection: function( evt )
                {
                    var htmlIdOfSaucer = evt.currentTarget.id;
                    console.log( "A saucer was clicked during move card selection with node "+htmlIdOfSaucer+"." );
                    var saucerColor = htmlIdOfSaucer.split('_')[1]; // b92bba

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

                    if((this.saucer1 == saucerColor && this.CHOSEN_MOVE_CARD_SAUCER_1 == '') ||
                       (this.saucer2 == saucerColor && this.CHOSEN_MOVE_CARD_SAUCER_2 == ''))
                    { // we have not yet chosen a move card for this saucer
                        this.highlightAllMoveCardsForSaucer(saucerColor); // highlight the move cards now that it's time to choose one
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

                        this.ajaxcall( "/crashandgrab/crashandgrab/actClickedAcceleratorDirection.html", {
                                                                                    direction: direction,
                                                                                    lock: true
                                                                                 },
                                         this, function( result ) {

                                            // What to do after the server call if it succeeded
                                            // (most of the time: nothing)

                                            this.unhighlightAllDirections(); // UNhighlight ALL directions
                                            this.unhighlightAllSpaces(); // unhighlight all board move spaces

                                         }, function( is_error) {

                                            // What to do after the server call in anyway (success or failure)
                                            // (most of the time: nothing)

                        } );
                    }
                    else if(this.checkPossibleActions('clickMoveDirection'))
                    { // we are clicking on a direction while selecting our move card for the round
                        //dojo.stopEvent( evt ); // Preventing default browser reaction

                        //this.showMessage( _("Move direction click."), 'error' );



                        if(this.MOVE_CARD_SELECTED == '')
                        { // no move card is selected so it doesn't make sense to select a directions
                            console.log("no move card is selected");
                            return;
                        }

                        this.saveDirectionSelection(this.SAUCER_SELECTED, htmlIdOfToken);

                        //this.highlightAllDirections(); // highlight all the directions because people can still change them
                        this.unhighlightAllDirections(); // UNhighlight ALL directions
                        this.selectSpecificDirection(htmlIdOfToken); // select this token

                        this.highlightPlayerSaucersWhoHaveNotChosen(); // highlight this player's saucers that haven't chosen yet

                        // set the available spaces (use the existing method with a new optional paramter for direction)
                        var moveCardSelected = this.MOVE_CARD_SELECTED.split('_')[2]; // 0, 1, 2
                        this.highlightPossibleMoveSelections(this.playerSaucerMoves, this.player_id, this.SAUCER_SELECTED, moveCardSelected, direction); // highlight possible destinations on board


                        // move the selected move card to its spot on the ship mat if it's not already there

                        if( $(this.MOVE_CARD_SELECTED) )
                        { // this card exists
                            console.log('Move card FROM ' + this.MOVE_CARD_SELECTED + ' to player_board_move_card_holder_' + this.SAUCER_SELECTED + '.');
                            //this.placeOnObject( 'cardontable_'+player_id, 'myhand_item_'+card_id ); // teleport card FROM, TO

                            var destinationHtmlId = 'player_board_move_card_holder_'+this.SAUCER_SELECTED;
                            this.attachToNewParent( this.MOVE_CARD_SELECTED, destinationHtmlId ); // needed so it doesn't slide under the player board
                            this.slideToObject( this.MOVE_CARD_SELECTED, destinationHtmlId ).play(); // slide card FROM, TO

                            this.rotateTo( this.MOVE_CARD_SELECTED, this.getDegreesRotated(direction) );
                            $(this.MOVE_CARD_SELECTED).style.removeProperty('left'); // remove left property (doesn't seem to work)
                        }
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

                saveMoveCardSelection: function(color, htmlIdOfCard)
                {
                    // set the move card for this saucer in case it is the final selection
                    if(this.saucer1 == color)
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
                    if(this.saucer1 == saucerColor)
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

                onClickGarment: function( evt )
                { // a player clicked on a garment
                    console.log('GARMENT CLICK check iscurrentplayeractive() ' + this.isCurrentPlayerActive());


                    if (this.checkPossibleActions( 'replaceGarmentClick', true ))
                    { // player clicks on a garment (it must be checkPossibleActions because they could be replacing the garment on another player's turn so we don't want it to check for active player)

                            var node = evt.currentTarget.id;
                            this.chosenGarmentType = node.split('_')[1]; // garment type
                            this.chosenGarmentColor = node.split('_')[2]; // garment color
                            console.log("clicked on garment " + this.chosenGarmentType + " " + this.chosenGarmentColor);

                            if(this.chosenGarmentType != null && this.chosenGarmentColor != null)
                            { // the player has chosen both a destination space and a garment
                                this.ajaxcall( "/crashandgrab/crashandgrab/actReplaceGarmentChooseGarment.html", { garmentType: this.chosenGarmentType, garmentColor: this.chosenGarmentColor, lock: true }, this, function( result ) {}, function( is_error ) {} );
                            }

                    }
                    else if (this.checkAction( 'stealGarmentClick', true ))
                    { // player clicks on a garment

                        if ( this.isCurrentPlayerActive() )
                        { // the active player is clicking

                            var node = evt.currentTarget.id; // get the node like garment_head_f6033b
                            var garmentType = node.split('_')[1]; // the type of garment like head, legs, etc.
                            var garmentColor = node.split('_')[2]; // the color of the owning ostrich
                            console.log("clicked on garment " + garmentType + " " + garmentColor);

                            this.ajaxcall( "/crashandgrab/crashandgrab/actStealGarment.html", { garmentType: garmentType, garmentColor: garmentColor, lock: true }, this, function( result ) {}, function( is_error ) {} );
                        }

                    }
                    else if (this.checkAction( 'discardGarmentClick', true ))
                    { // player clicks on a garment

                        if ( this.isCurrentPlayerActive() )
                        { // the active player is clicking

                            var node = evt.currentTarget.id; // get the node like garment_head_f6033b
                            var garmentType = node.split('_')[1]; // the type of garment like head, legs, etc.
                            var garmentColor = node.split('_')[2]; // the color of the owning ostrich
                            console.log("clicked on garment " + garmentType + " " + garmentColor);

                            this.ajaxcall( "/crashandgrab/crashandgrab/actDiscardGarment.html", { garmentType: garmentType, garmentColor: garmentColor, lock: true }, this, function( result ) {}, function( is_error ) {} );
                        }

                    }


                    return; // we cannot perform a garment-clicking action at this time so ignore the click



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


                onClickSpace: function( evt )
                { // a player clicked on a space

                    var node = evt.currentTarget.id;
                    var chosenSpaceX = node.split('_')[1]; // x location of the space
                    var chosenSpaceY = node.split('_')[2]; // y location of the space
                    console.log("clicked on space " + chosenSpaceX + " " + chosenSpaceY);

                    if (this.checkPossibleActions( 'spaceClick', true ))
                    { // player clicks on a garment (it must be checkPossibleActions because they could be replacing the garment on another player's turn so we don't want it to check for active player)

                            if(this.chosenSpaceX != 0 && this.chosenSpaceY != 0)
                            { // the player has chosen both a destination space and a garment
                                this.ajaxcall( "/crashandgrab/crashandgrab/actReplaceGarmentChooseSpace.html", {garmentDestinationX: chosenSpaceX, garmentDestinationY: chosenSpaceY, lock: true }, this, function( result ) {}, function( is_error ) {} );
                            }

                    }
                    else if (this.checkPossibleActions( 'chooseSaucerSpace', true ))
                    { // we are choosing a space to place a Saucer
                        if(this.chosenSpaceX != 0 && this.chosenSpaceY != 0)
                        { // the player has chosen both a destination space and a garment
                            this.ajaxcall( "/crashandgrab/crashandgrab/actChooseAnySpaceForSaucer.html", {garmentDestinationX: chosenSpaceX, garmentDestinationY: chosenSpaceY, lock: true }, this, function( result ) {}, function( is_error ) {} );
                            this.unhighlightAllSpaces();
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

        // onEnteringState: this method is called each time we are entering into a new game state.
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
                  case 'replaceGarmentChooseGarment':
                  console.log( "onEnteringState->replaceGarmentChooseGarment" );
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

                  break;

                  case 'replaceGarmentChooseSpace':
                  console.log( "onEnteringState->replaceGarmentChooseSpace" );
                  var playerIdRespawningGarmentSpace = args.args.playerIdRespawningGarment;
                  var playerNameRespawningGarmentSpace = args.args.playerNameRespawningGarment;

                  // they will just choose a garment and then choose an empty crate
                  if( this.player_id == playerIdRespawningGarmentSpace )
                  { // this player is the one who respawns this garment
                      var activePlayerText = _("You must choose where on the board this garment will go.");
                      this.setPlayerInstructions(activePlayerText);

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
                  }
                  else
                  {
                      var otherPlayerText = dojo.string.substitute( _("${playerNameReplacing} is choosing where the new garment will go."), {
                          playerNameReplacing: playerNameRespawningGarmentSpace
                      } );
                      this.setPlayerInstructions(otherPlayerText);
                  }

                  break;

                  case 'askStealOrDraw':
                      console.log( "onEnteringState->askStealOrDraw" );
                      if( this.isCurrentPlayerActive() )
                      { // this is the active player

                      }
                  break;

                  case 'askWhichGarmentToSteal':
                      console.log( "onEnteringState->askWhichGarmentToSteal" );
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
                              var htmlIdOfGarment = 'garment_'+garmentType+'_'+garmentColor;
                              dojo.addClass( htmlIdOfGarment, 'highlighted_garment' );
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
                          var htmlIdOfGarment = 'garment_'+garmentType+'_'+garmentColor;
                          dojo.addClass( htmlIdOfGarment, 'highlighted_garment' );
                      }
                  }

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
             case 'claimZag':
                 console.log( "onLeavingState->claimZag" );
             break;

            case 'chooseZigPhase':
            console.log( "onLeavingState->chooseZigPhase" );
            this.playedCardThisTurn = false; // true if I have chosen the Zig I will play this round
            this.choseDirectionThisTurn = false; // true if I have chosen the DIRECTION of the Zig I will play this round
            this.askedZag = false; // true if the player has declined to claim a Zag
            break;

            case 'setTrapsPhase':
            console.log( "onLeavingState->setTrapsPhase" );

            //this.finishedTrapping = false; // true if we have either set our trap or chosen not to set a trap or we don't have any traps

            this.resetMovePhaseVariables(); // before we go into the move phase, reset the variables for it
            break;

            case 'executeMove':
            console.log( "onLeavingState->executeMove" );

            //this.ostrichChosen = false; // true when the player selects which ostrich they will move this turn
            break;

            case 'replaceGarmentChooseGarment':
            console.log( "onLeavingState->replaceGarmentChooseGarment" );
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
                      this.playerSaucerMoves = args.playerSaucerMoves; // save the list of players/saucers/move cards/spaces so it can be used elsewhere
                      if( this.isCurrentPlayerActive() )
                      { // this player is active

                          if(this.SAUCER_SELECTED == '')
                          { // NO saucer is selected

                              if(!this.hasSaucerChosenMoveAndDirection(this.saucer1))
                              { // this saucer still needs to choose move card or direction
                                  this.highlightSpecificPlayerSaucer(this.saucer1); // highlight it
                              }

                              if(!this.hasSaucerChosenMoveAndDirection(this.saucer2))
                              { // this saucer still needs to choose move card or direction
                                  this.highlightSpecificPlayerSaucer(this.saucer2); // highlight it
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
                                  this.unhighlightAllMoveCards();
                                  this.highlightAllMoveCardsForSaucer(this.SAUCER_SELECTED);
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

                  case 'endRoundPlaceCrashedSaucer':
                  case 'askPreTurnToPlaceCrashedSaucer':
                  console.log( "onUpdateActionButtons->placingSaucer" );

                  if( this.isCurrentPlayerActive() )
                  { // this player is active
                      var saucerButton = args.saucerButton;

                      var buttonLabel = saucerButton['buttonLabel'];
                      var isDisabled = saucerButton['isDisabled'];
                      var hoverOverText = saucerButton['hoverOverText']; // hover over text or '' if we don't want a hover over
                      var actionName = saucerButton['actionName']; // selectSaucerToGoFirst
                      var makeRed = saucerButton['makeRed'];

                      this.addButtonToActionBar(buttonLabel, isDisabled, hoverOverText, actionName, makeRed);
                  }
                  break;

                  case 'chooseDirectionAfterPlacement':

                      this.showDirectionButtons();

                  break;

                  case 'allCrashSitesOccupiedChooseSpaceEndRound':
                  case 'allCrashSitesOccupiedChooseSpacePreTurn':

                      if( this.isCurrentPlayerActive() )
                      { // this player is active
                          var validSpaces = args.validPlacements;
                          this.highlightAllOccupiedSpaces(validSpaces);
                      }

                  break;

                  case 'chooseWhichSaucerGoesFirst':
                    console.log( "onUpdateActionButtons->chooseWhichSaucerGoesFirst" );
                    if( this.isCurrentPlayerActive() )
                    { // this player is active
                        var saucerButtonList = args.saucerButtons;

                        const saucerButtonKeys = Object.keys(saucerButtonList);
                        for (const buttonKey of saucerButtonKeys)
                        { // go through each button

                            var buttonLabel = saucerButtonList[buttonKey]['buttonLabel'];
                            var isDisabled = saucerButtonList[buttonKey]['isDisabled'];
                            var hoverOverText = saucerButtonList[buttonKey]['hoverOverText']; // hover over text or '' if we don't want a hover over
                            var actionName = saucerButtonList[buttonKey]['actionName']; // selectSaucerToGoFirst
                            var makeRed = saucerButtonList[buttonKey]['makeRed'];

                            this.addButtonToActionBar(buttonLabel, isDisabled, hoverOverText, actionName, makeRed);
                        }
                    }
                  break;

                  case 'playerTurnExecuteMove':
                      console.log( "onUpdateActionButtons->playerTurnExecuteMove" );

                      if( this.isCurrentPlayerActive() )
                      {
                          var buttonLabel = 'Move';
                          var isDisabled = false;
                          var hoverOverText = ''; // hover over text or '' if we don't want a hover over
                          var actionName = 'startMove'; // selectSaucerToGoFirst
                          var makeRed = false;

                          this.addButtonToActionBar(buttonLabel, isDisabled, hoverOverText, actionName, makeRed);
                      }

                  break;

                  case 'chooseXVal':
                  console.log( "onUpdateActionButtons->chooseXVal" );
                  var buttonList = args.moveCardButtons;
                  // USE THIS FRAMEWORK BUT NEED TO UPDATE FOR X VALUES
                    const buttonKeys = Object.keys(buttonList);
                    for (const buttonKey of buttonKeys)
                    { // go through each button

                        var buttonLabel = buttonList[buttonKey]['buttonLabel'];
                        var isDisabled = buttonList[buttonKey]['isDisabled'];
                        var hoverOverText = buttonList[buttonKey]['hoverOverText']; // hover over text or '' if we don't want a hover over
                        var actionName = buttonList[buttonKey]['actionName']; // shoot, useEquipment
                        var makeRed = buttonList[buttonKey]['makeRed'];

                        this.addButtonToActionBar(buttonLabel, isDisabled, hoverOverText, actionName, makeRed);
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

                  case 'chooseZigPhase':
                    this.canWeClaimZag = false;
                    console.log( "onUpdateActionButtons->chooseZigPhase with playedCardThisTurn="+this.playedCardThisTurn+" and this.choseDirectionThisTurn="+this.choseDirectionThisTurn + " askedZag="+this.askedZag+" canWeClaimZag:"+this.canWeClaimZag+" mustChooseZagDiscards="+this.mustChooseZagDiscards + " this.saucer1HasZag="+this.saucer1HasZag );

                    var allPlayersWithOstriches = args.allPlayersWithOstriches;
                    console.log("allPlayersWithOstriches:");
                    console.log(allPlayersWithOstriches);

/*
                    console.log("keys:")
                    const keys = Object.keys(allPlayersWithOstriches);
                    for (const key of keys) {
                      console.log(key);
                    }
*/
                    var showDirectionButtons = false; // true if the player needs to choose a direction
                    var showStartOverButton = false; // true if we want to display a Start Over button

                    const players = Object.keys(allPlayersWithOstriches);
                    for (const playerKey of players)
                    { // go through each player
                        var owner = playerKey;
                        console.log("owner:" + owner);

                        if(owner == this.player_id && this.isCurrentPlayerActive())
                        { // we are this player and we are active

                            const keysUnderPlayer = Object.keys(allPlayersWithOstriches[playerKey]);
                            for (const ostrichColorKey of keysUnderPlayer)
                            { // go through each ostrich
                                var ostrichColor = ostrichColorKey; // get color signifier like f6033b
                                console.log("color:" + ostrichColor);
                                var ostrichName = this.getOstrichName(ostrichColor); // convert f6033b into RED
                                var buttonText = 'Choose Zig for '+ostrichName;
                                if(keysUnderPlayer.length < 2)
                                { // this player only controls a single ostrich
                                    buttonText = 'Choose Selected Zig'; // remove the ostrich color from the button text
                                }

                                var direction = allPlayersWithOstriches[playerKey][ostrichColor]['zigDirection'];
                                console.log("direction:" + direction);
                                var distance = allPlayersWithOstriches[playerKey][ostrichColor]['zigDistance'];
                                console.log("distance:" + distance);


                                if(distance != 20)
                                { // this ostrich has its zig chosen
                                    showStartOverButton = true; // we should allow the player to start over
                                }

                                if(distance != 20 && direction == "")
                                {  // player has at least one ostrich who has zig chosen, but not direction
                                    showDirectionButtons = true;
                                }

                                if(showDirectionButtons)
                                { // we do want to show direction buttons
                                    this.removeActionButtons(); // remove any action buttons that are currently showing
                                    console.log("creating direction buttons");
                                    this.addActionButton( 'zigDirectionBridge_button', _('BRIDGE'), 'onDirectionZigChoiceBridge' );
                                    this.addActionButton( 'zigDirectionCactus_button', _('CACTUS'), 'onDirectionZigChoiceCactus' );
                                    this.addActionButton( 'zigDirectionRiver_button', _('RIVER'), 'onDirectionZigChoiceRiver' );
                                    this.addActionButton( 'zigDirectionMountain_button', _('MOUNTAIN'), 'onDirectionZigChoiceMountain' );
                                }
                                else
                                { // we want to show zig choice buttons
                                    console.log("creating ostrich button");
                                    console.log("ostrichName:" + ostrichName);
                                    console.log("buttonText:" + buttonText);
                                    console.log("ostrichName:" + ostrichName);
                                    this.addActionButton( 'ostrich'+ostrichName+'_button', _(buttonText), 'onOstrichZigChoice_'+ostrichName );

                                }
                            }
                        }
                    }

                    console.log("showStartOverButton:" + showStartOverButton);

                    if(showStartOverButton)
                    {
                        this.addActionButton( 'startOverZig_button', _('Start Over'), 'onStartZigPhaseOver', null, false, 'red' );
                    }


                  break;

                  case 'setTrapsPhase':
                    console.log( "onUpdateActionButtons for setTrapPhase" );

                    if( this.isCurrentPlayerActive() )
                    { // this player is active (they have a trap card and they haven't yet said they aren't playing it)

                      if(this.saucer1 != "f6033b" && this.saucer2 != "f6033b")
                          this.addActionButton( 'trapRed_button', _('RED'), 'onTrapRed' );

                      if(this.saucer1 != "fedf3d" && this.saucer2 != "fedf3d")
                          this.addActionButton( 'trapYellow_button', _('YELLOW'), 'onTrapYellow' );

                      if(this.saucer1 != "0090ff" && this.saucer2 != "0090ff")
                          this.addActionButton( 'trapBlue_button', _('BLUE'), 'onTrapBlue' );

                      if(this.saucer1 != "01b508" && this.saucer2 != "01b508")
                          this.addActionButton( 'trapGreen_button', _('GREEN'), 'onTrapGreen' );

                      if(this.saucer1 != "01b508" && this.saucer2 != "b92bba")
                          this.addActionButton( 'trapGreen_button', _('PURPLE'), 'onTrapGreen' );

                      if(this.saucer1 != "01b508" && this.saucer2 != "c9d2db")
                          this.addActionButton( 'trapGreen_button', _('GRAY'), 'onTrapGreen' );

                      this.addActionButton( 'noTrap_button', _('Do Not Trap'), 'noTrap', null, false, 'red' );

                    }

                  break;

                  case 'chooseOstrich':
                  console.log( "onUpdateActionButtons for chooseOstrich" );

                  break;

                  case 'chooseXValue':
                      console.log( "onUpdateActionButtons for chooseXValue" );

                      if( this.isCurrentPlayerActive() )
                      { // this is the active player so we need to execute their move
                          this.showXValueButtons();
                      }

                  break;

                  case 'askTrapBasic':
                      console.log( "onUpdateActionButtons for askTrapBasic" );
                      if( this.isCurrentPlayerActive() )
                      { // this is the active player  who is being trapped
                          this.addActionButton( 'executeTrap_button', _('Execute the Trap'), 'onExecuteTrap' );
                      }
                  break;

                  case 'executeMove':
                      console.log( "onUpdateActionButtons for executeMove with isDizzy " + args.isDizzy );
                      console.log( "update args.isDizzy="+args.isDizzy );

                      if( this.isCurrentPlayerActive() )
                      { // this is the active player so we need to execute their move

                          if(args.isDizzy == 1)
                          { // we are dizzy
                            this.showDirectionChoiceButtons();
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

                      if( this.isCurrentPlayerActive() )
                      { // this is the active player so they need to discard
                        var translatedText = _("You may only have 1 Trap Card. Please choose which you will discard.");
                        this.setPlayerInstructions(translatedText);
                      }

                  break;

                  case 'chooseAcceleratorDirection':
                      console.log( "onUpdateActionButtons for chooseAcceleratorDirection with ostrichChosen="+this.ostrichChosen );

                      if( this.isCurrentPlayerActive() )
                      { // this is the active player so they need to discard

                          //this.showDirectionChoiceButtons();

                          // highlight all spaces available
                          this.highlightPossibleAcceleratorOrBoostMoveSelections(args.playerSaucerAcceleratorMoves);

                          this.showRestartTurnButton();
                      }
                  break;

                  case 'askUseZag':
                      console.log( "onUpdateActionButtons for askUseZag" );
                      if( this.isCurrentPlayerActive() )
                      { // this is the active player so they need to discard
                          this.showAskToUseZagButtons(); // show the buttons tha task if the player would like to use their zag
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

                  case 'replaceGarmentChooseGarment':
                  console.log( "onUpdateActionButtons for replaceGarmentChooseGarment" );


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
            console.log("this.saucer1 " + this.saucer1);
            console.log("CHOSEN_MOVE_CARD_SAUCER_2 " + this.CHOSEN_MOVE_CARD_SAUCER_2);
            console.log("CHOSEN_DIRECTION_SAUCER_2 " + this.CHOSEN_DIRECTION_SAUCER_2);
            console.log("this.saucer2 " + this.saucer2);


            if(saucerColor == '')
            {
              return true;
            }

            if(saucerColor == this.saucer1 && this.CHOSEN_MOVE_CARD_SAUCER_1 != '' && this.CHOSEN_DIRECTION_SAUCER_1 != '')
            {
              return true;
            }

            if(saucerColor == this.saucer2 && this.CHOSEN_MOVE_CARD_SAUCER_2 != '' && this.CHOSEN_DIRECTION_SAUCER_2 != '')
            {
              return true;
            }
console.log("return false");
            return false;
        },

        convertGarmentTypeIntToString: function(typeAsInt)
        {
          console.log("converting type as int " + typeAsInt);
            switch( typeAsInt )
            {
                  case "0":
                      return "head";
                  case "1":
                      return "body";
                  case "2":
                      return "legs";
                  case "3":
                      return "feet";
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
                  case "c9d2db":
                      return "GRAY";
            }

            return "";
        },

        unhighlightAllSpaces: function()
        {
            dojo.query( '.highlighted_square' ).removeClass( 'highlighted_square' ); // remove highlights from all spaces (white)
            dojo.query( '.spaceHighlighted' ).removeClass( 'spaceHighlighted' ); // remove highlights from all spaces (green)

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

        },

        unhighlightAllSaucers: function()
        {
          dojo.query( '.saucer' ).removeClass( 'saucerHighlighted' );
        },

        unselectAllSaucers: function()
        {
          dojo.query( '.saucer' ).removeClass( 'saucerSelected' );
        },

        highlightAllPlayerSaucers: function(playerId)
        {
            var htmlIdSaucer1 = "saucer_"+this.saucer1;
            dojo.removeClass( htmlIdSaucer1, 'saucerSelected' ); // unselect it
            dojo.addClass( htmlIdSaucer1, 'saucerHighlighted' ); // highlight it
            dojo.connect( $(htmlIdSaucer1), 'onclick', this, 'onClick_saucerDuringMoveCardSelection' ); // attached our saucer tokens to this onclick handler

            var htmlIdSaucer2 = "saucer_"+this.saucer2;
            if(document.getElementById(htmlIdSaucer2))
            { // this component exists
                dojo.removeClass( htmlIdSaucer2, 'saucerSelected' ); // unselect it
                dojo.addClass( htmlIdSaucer2, 'saucerHighlighted' ); // highlight it
                dojo.connect( $(htmlIdSaucer2), 'onclick', this, 'onClick_saucerDuringMoveCardSelection' ); // attached our saucer tokens to this onclick handler
            }

        },

        highlightPlayerSaucersWhoHaveNotChosen: function(playerId)
        {
            if(!this.hasSaucerChosenMoveAndDirection(this.saucer1))
            { // saucer 1 has not chosen their move and direction
                this.highlightSpecificPlayerSaucer(this.saucer1);
            }

            if(!this.hasSaucerChosenMoveAndDirection(this.saucer2))
            { // saucer 2 has not chosen their move and direction
                this.highlightSpecificPlayerSaucer(this.saucer2);
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
            if(this.saucer1 == color)
            { // we are selecting for saucer 1

                if(this.CHOSEN_DIRECTION_SAUCER_1 != '')
                { // we have previously selected a direction
                    this.selectSpecificDirection(this.CHOSEN_DIRECTION_SAUCER_1);
                }
                else
                { // we have not yet selected a direction
                    this.highlightAllDirections();
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
                    this.highlightAllDirections();
                }
            }

        },

        selectSpecificDirection: function(directionAsHtmlId)
        {
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
            this.unselectAllDirections();
            dojo.query( '.direction_token' ).addClass( 'directionHighlighted' );
        },

        unhighlightAllDirections: function()
        {
            dojo.query( '.direction_token' ).removeClass( 'directionHighlighted' );
        },

        highlightSpecificDirection: function(directionAsHtmlId)
        {
            this.unselectAllDirections();
            this.unhighlightAllDirections();
            dojo.addClass( directionAsHtmlId, 'directionHighlighted' ); // highlight it
        },

        highlightSpacesForSelectedSaucer: function(color)
        {
            this.unhighlightAllSpaces(); // unhighlight all board move spaces
            if(this.saucer1 == color)
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

            if(this.saucer1 == color)
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

            if(this.saucer1 == color)
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
            this.moveChosenMoveCardBackToHand(color, 0);
            this.moveChosenMoveCardBackToHand(color, 1);
            this.moveChosenMoveCardBackToHand(color, 2);

            if(color == this.saucer1)
            {
                this.CHOSEN_DIRECTION_SAUCER_1 = '';
            }

            if(color == this.saucer2)
            {
                this.CHOSEN_DIRECTION_SAUCER_2 = '';
            }
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
                this.unselectAllDirections();
                this.highlightAllDirections();
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
            dojo.removeClass( htmlIdOfCard, 'moveCardHighlighted' ); // remove a CSS class from this element
        },

        selectSpecificMoveCard: function(htmlIdOfCard)
        {
            this.unhighlightSpecificMoveCard(htmlIdOfCard); // remove highlighting if it has any
            dojo.addClass( htmlIdOfCard, 'moveCardSelected' ); // give this card a new CSS class
        },

        highlightSpace: function(htmlOfSpace)
        {
            dojo.addClass( htmlOfSpace, 'spaceHighlighted' ); // give this card a new CSS class
        },

        highlightAllOccupiedSpaces: function(validSpaces)
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
                                            for (const space of spaces)
                                            { // go through each direction for this move card

                                                console.log("for player " + playerKey+" saucer " + saucerKey + " move card " + moveCardKey + " direction " + directionKey + " we found a valid space of " + space);
                                                var htmlOfSpace = 'square_'+space; // square_6_5
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
            if(nextEvent)
            {
                var eventType = nextEvent['event_type']; // saucerMove
                var saucerMoving = nextEvent['saucer_moving']; // ff0000
                var destinationX = nextEvent['destination_X']; // 5
                var destinationY = nextEvent['destination_Y']; // 7

                console.log("event eventType: " + eventType + " saucerMoving: " + saucerMoving + " destinationX: " + destinationX + " destinationY: " + destinationY);
                if(eventType == 'crewmemberPickup')
                { // the saucer picked up a crewmember

                    this.slideCrewmemberToSaucer();

                    // start a new animation where we don't wait for anything to finish to slide the crewmember to the saucer
                    var animationId = this.slideToObject( 'saucer_'+saucerMoving, 'square_'+destinationX+'_'+destinationY, this.ANIMATION_SPEED );
                    dojo.connect(animationId, 'onEnd', () => {
                    });
                    animationId.play();
                }

                var animationId = this.slideToObject( 'saucer_'+saucerMoving, 'square_'+destinationX+'_'+destinationY, this.ANIMATION_SPEED );
                dojo.connect(animationId, 'onEnd', () => {
                   this.animateEvents(eventStack); // recursively call for the next event
                });
                animationId.play();
            }
        },

        updateTurnOrder: function(turnOrder)
        {
            var x = 0;
            var y = 0;

            if( turnOrder == 1)
            { // we are going clockwise
                x=45;
                console.log("COUNTER-CLOCKWISE");
            }
            else if(turnOrder == 0) {
                x=0;
                console.log("CLOCKWISE");
            }
            else
            {
              x=90;
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

        placePlayerBoardForSaucer: function(owner, color)
        {
            var playerBoardDiv = $('player_board_' + owner);

            dojo.place( this.format_block( 'jstpl_player_board_for_saucer', {
                color: color,
                owner: owner
            } ) , playerBoardDiv );

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
                dojo.style('direction_sun', "marginLeft", "292px");
                dojo.style('direction_meteor', "marginLeft", "292px");

                dojo.style('board_tile_column', "width", "685px"); // set the width of the board based on saucer count
            }
            else if(numberOfPlayers == 5)
            { // we are playing with 5 players

              dojo.destroy('board_tile_container_4');
              dojo.destroy('board_tile_container_6');

                // center the directions based on the number of players
                dojo.style('direction_constellation', "marginTop", "254px"); // move the right direction to where the extra tiles would have been
                dojo.style('direction_asteroids', "marginTop", "254px"); // move the right direction to where the extra tiles would have been
                dojo.style('direction_sun', "marginLeft", "322px");
                dojo.style('direction_meteor', "marginLeft", "322px");

                dojo.style('board_tile_column', "width", "750px"); // set the width of the board based on saucer count
            }
            else if(numberOfPlayers == 6)
            { // we are playing with 6 players

              dojo.destroy('board_tile_container_4');
              dojo.destroy('board_tile_container_5');

                // center the directions based on the number of players
                dojo.style('direction_constellation', "marginTop", "280px"); // move the right direction to where the extra tiles would have been
                dojo.style('direction_asteroids', "marginTop", "280px"); // move the right direction to where the extra tiles would have been
                dojo.style('direction_sun', "marginLeft", "348px");
                dojo.style('direction_meteor', "marginLeft", "348px");

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

            if(color == this.saucer1 || color == this.saucer2)
            { // this saucer is owned by this player

                  dojo.addClass( saucerHtmlId, 'clickable' );
            }
        },

        putCrownOnPlayerBoard: function( color )
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
            dojo.style( 'player_board_arrow_'+player_id, 'backgroundPosition', '-'+x+'px -'+y+'px' );
            //dojo.style( 'my_element', 'display', 'none' );

            console.log('setting arrow background position x:'+x+' y:'+y+' player_id:'+player_id);
        },

        moveCrownToPlayerBoard: function( color )
        {
            console.log('moving crown from (player_board_crown) to (player_board_crown_holder_'+color+')');
            this.slideToObject( 'player_board_crown', 'player_board_crown_holder_'+color ).play();
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
                this.showDirectionChoiceButtons();
            }
            else if(ostrichMoving == ostrichTakingTurn && ostrichMovingHasZag)
            { // it is the moving ostrich's turn and they have a zag
                this.showAskToUseZagButtons();
            }
            else {
              this.mustSkateboard = false;
            }
        },

        showChooseZigButtons: function()
        {
            var translatedText = _("Choose which Zig card you will play.");
            this.setPlayerInstructions(translatedText);
        },

        showDirectionChoiceButtons: function()
        {
            this.addActionButton( 'newDirectionBridge_button', _('BRIDGE'), 'onNewDirectionBridge' );
            this.addActionButton( 'newDirectionCactus_button', _('CACTUS'), 'onNewDirectionCactus' );
            this.addActionButton( 'newDirectionRiver_button', _('RIVER'), 'onNewDirectionRiver' );
            this.addActionButton( 'newDirectionMountain_button', _('MOUNTAIN'), 'onNewDirectionMountain' );

        },

        showAskToRespawnButtons: function()
        {
            this.addActionButton( 'ostrichRespawn_button', _('OK!'), 'onOstrichRespawn' );

        },

        showMoveButton: function()
        {
            this.addActionButton( 'move_button', _('Move'), 'onMoveClick' );
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
            this.addActionButton( this.UP_DIRECTION+'_button', '<div class="'+this.UP_DIRECTION+'"></div>', 'onClick_'+this.UP_DIRECTION+'Direction', null, null, 'gray');
            this.addActionButton( this.RIGHT_DIRECTION+'_button', '<div class="'+this.RIGHT_DIRECTION+'"></div>', 'onClick_'+this.RIGHT_DIRECTION+'Direction', null, null, 'gray');
            this.addActionButton( this.DOWN_DIRECTION+'_button', '<div class="'+this.DOWN_DIRECTION+'"></div>', 'onClick_'+this.DOWN_DIRECTION+'Direction', null, null, 'gray');
            this.addActionButton( this.LEFT_DIRECTION+'_button', '<div class="'+this.LEFT_DIRECTION+'"></div>', 'onClick_'+this.LEFT_DIRECTION+'Direction', null, null, 'gray');


            //dojo.addClass('sun_button','bgaimagebutton'); // remove the button outline

        },

        showZagDirectionButtons: function()
        {
          this.removeActionButtons(); // remove any action buttons that are currently showing
          //dojo.destroy('useZag_button'); // destroy use zag button
          //dojo.destroy('noZag_button'); // destroy no zag button

          this.addActionButton( 'zagBridge_button', _('BRIDGE'), 'onZagBridge' );
          this.addActionButton( 'zagCactus_button', _('CACTUS'), 'onZagCactus' );
          this.addActionButton( 'zagRiver_button', _('RIVER'), 'onZagRiver' );
          this.addActionButton( 'zagMountain_button', _('MOUNTAIN'), 'onZagMountain' );
        },

        showRestartTurnButton: function()
        {
          this.addActionButton( 'restartTurn_button', _('Restart Turn'), 'restartTurn', null, false, 'red' );
        },

        showAskToUseZagButtons: function()
        {
          this.addActionButton( 'useZag_button', _('Use Zag'), 'useZag' );
          this.addActionButton( 'noZag_button', _('No'), 'noZag', null, false, 'red' );
        },

        showAskStealOrDrawButtons: function(countOfStealableGarments)
        {
            if(countOfStealableGarments > 0)
            {
                this.addActionButton( 'steal_button', _('Steal a Garment'), 'onChooseToStealGarment' );
            }

            this.addActionButton( 'draw_button', _('Draw Zigs'), 'onDraw2Zigs' );
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

        sendMoveInNewDirection: function( ostrich, chosenDirection )
        { // Tell the server which move was selected for this ostrich.
            console.log("sendMoveInNewDirection sending in ostrich " + ostrich + " with direction " + chosenDirection);
            this.ajaxcall( "/crashandgrab/crashandgrab/actExecuteMoveInNewDirection.html", { ostrich: ostrich, ostrichTakingTurn: ostrich, direction: chosenDirection, lock: true }, this, function( result ) {
            }, function( is_error) { } );
        },

        sendZagMove: function( chosenDirection )
        { // Tell the server which move was selected for this ostrich.
            console.log("sendZagMove sending direction " + chosenDirection);
            this.ajaxcall( "/crashandgrab/crashandgrab/actExecuteZagMove.html", { direction: chosenDirection, lock: true }, this, function( result ) {
            }, function( is_error) { } );
        },

        sendDirectionClick: function( chosenDirection )
        {
            console.log("sendDirectionClick sending direction " + chosenDirection);
            this.ajaxcall( "/crashandgrab/crashandgrab/actExecuteDirectionClick.html", { direction: chosenDirection, lock: true }, this, function( result ) {
            }, function( is_error) { } );
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

        onDiscard3ClaimGRAY: function()
        {
            var cardsDiscarded = this.convertSelectedCardsToString();

            this.sendClaimZag(this.GRAYCOLOR, cardsDiscarded); // tell the server which cards we are discarding for the zag and which ostrich is doing it
        },

        onDiscard3Cancel: function()
        {
            this.mustChooseZagDiscards = false;
        },

        onNewDirectionBridge: function()
        {
          console.log( "onNewDirectionBridge" );

          this.sendMoveInNewDirection(this.saucer1, "BRIDGE");
        },

        onNewDirectionCactus: function()
        {
          console.log( "onNewDirectionCactus" );

          this.sendMoveInNewDirection(this.saucer1, "CACTUS");
        },

        onNewDirectionRiver: function()
        {
          console.log( "onNewDirectionRiver" );

          this.sendMoveInNewDirection(this.saucer1, "RIVER");
        },

        onNewDirectionMountain: function()
        {
          console.log( "onNewDirectionMountain" );

          this.sendMoveInNewDirection(this.saucer1, "MOUNTAIN");
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

        onZagBridge: function()
        {
          console.log( "onZagBridge" );

          if(this.saucer2 == this.lastMovedOstrich)
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

          if(this.saucer2 == this.lastMovedOstrich)
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

          if(this.saucer2 == this.lastMovedOstrich)
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

          if(this.saucer2 == this.lastMovedOstrich)
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

        onOstrichZigChoice_GRAY: function()
        {
            var selectedCards = this.playerHand.getSelectedItems(); // get the cards that were selected

            var cardId = 0;
            for( var i in selectedCards )
            { // go through cards but there should only be 1
                cardId = selectedCards[i].id;
            }

            this.sendZigChoice("c9d2db", cardId);
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

        onOstrichMoveChoiceGray: function()
        {
            this.ostrichChosen = true;
            this.sendExecuteMove("c9d2db");
        },

        onMoveClick: function()
        {
            this.sendExecuteMove("");
        },

        onXValueSelection: function( evt )
        {
            var node = evt.currentTarget.id;
            var value = node.split('_')[0];

            this.ajaxcall( "/crashandgrab/crashandgrab/actSelectXValue.html", { xValue: value, lock: true }, this, function( result ) {
            }, function( is_error) { } );
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

        onTrapGray: function()
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
                    this.ajaxcall( "/crashandgrab/crashandgrab/actSetTrap.html", { ostrich: this.GRAYCOLOR, lock: true }, this, function( result )
                    { // we successfully set the trap

                        // slide the card from current player's hand to targeted ostrich's mat
                        this.slideToObject( 'trap_hand_'+this.player_id+'_item_'+card_id, 'ostrich_mat_'+this.GRAYCOLOR).play();

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
                this.ajaxcall( "/hearts/hearts/giveCards.html", { cards: to_give, lock: true }, this, function( result ) {
                }, function( is_error) { } );
            }
        },

        restartTurn: function()
        {
          console.log( "Restart turn" );

          this.showMessage( _("Not implemented yet"), 'error' );
          return;
        },

        useZag: function()
        {
          console.log( "DO use a Zag" );

          if(this.saucer2 == this.lastMovedOstrich)
          {
              this.saucer2HasZag = false; // take away the zag
          }
          else {
              this.saucer1HasZag = false; // take away the zag
          }

          this.showZagDirectionButtons(); // show buttons for each direction they can zag
        },

        noZag: function()
        {
            console.log( "do NOT use a Zag" );

            //this.resetSetTrapPhaseVariables(); // now that we're into the movement phase, we can reset the trap phase stuff for this player so they are ready for next round
            this.sendEndTurn(); // tell the server this player's turn is over

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
                if(this.saucer2 == ostrich)
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

            var garmentHtmlId = 'garment_'+garmentType+'_'+garmentColor;
            var garmentLocationHtmlId = 'square_'+garmentX+'_'+garmentY;
            var matLocationHtmlId = 'mat_'+garmentType+"_"+wearingOrBackpack+"_"+numberOfThisType+"_"+ostrichWhoAcquiredIt;
            console.log("garmentHtmlId " + garmentHtmlId + " garmentLocationHtmlId " + garmentLocationHtmlId + " matLocationHtmlId " + matLocationHtmlId);

            // move garment to player's mat
            this.placeOnObject( garmentHtmlId, garmentLocationHtmlId ); // place it where it already is (required to overcome a bug with sliding)
            this.slideToObject( garmentHtmlId, matLocationHtmlId).play(); // slide it to where it goes on their mat

        },

        notif_replacementGarmentChosen: function( notif )
        {
            console.log("Entered notif_replacementGarmentChosen.");

            var garmentType = notif.args.garmentType;
            var garmentColor = notif.args.garmentColor;

            var garmentHtmlId = 'garment_'+garmentType+'_'+garmentColor;

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

            var garmentHtmlId = 'garment_'+garmentType+'_'+garmentColor;
            var garmentLocationHtmlId = 'square_'+xDestination+'_'+yDestination;
            console.log('moving ' + garmentHtmlId + ' to ' + garmentLocationHtmlId);

            this.placeOnObject( garmentHtmlId, 'replacement_garment_chosen_holder' ); // place it where it already is (required to overcome a bug with sliding)
            this.slideToObject( garmentHtmlId, garmentLocationHtmlId).play(); // slide it to the board
        },

        notif_moveGarmentToBoard: function( notif )
        {
            console.log("Entered notif_moveGarmentToBoard.");

            var garmentType = notif.args.garmentType;
            var garmentColor = notif.args.garmentColor;
            var xDestination = notif.args.xDestination;
            var yDestination = notif.args.yDestination;

            var garmentHtmlId = 'garment_'+garmentType+'_'+garmentColor;
            var spaceHtmlId = 'square_'+xDestination+'_'+yDestination;

            console.log('moving ' + garmentHtmlId + ' to ' + spaceHtmlId);
            this.slideToObject( garmentHtmlId, spaceHtmlId).play();
            this.slideToObject( garmentHtmlId, spaceHtmlId).play(); // it flies off the screen if we don't do this twice... we could place it first but would need the original x/y passed in
        },

        notif_zagUsed: function( notif )
        {
            console.log("Entered notif_zagUsed.");
            var ostrich = notif.args.ostrich;

            dojo.destroy('zag_'+ostrich); // destroy the zag token
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
            var ostrichName = notif.args.ostrichName;

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

          this.slideToObject( 'garment_'+garmentType+'_'+garmentColor, 'garment_holder_'+garmentType+'_'+garmentColor).play();

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

        notif_animateMovement: function(notif)
        {
            var eventStack = notif.args.moveEventList;


            this.animateEvents(eventStack);
        }

   });
});
