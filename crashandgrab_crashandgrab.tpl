{OVERALL_GAME_HEADER}

<!--
--------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- CrashAndGrab implementation : © <Your name here> <Your email address here>
--
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-------

    crashandgrab_crashandgrab.tpl

    This is the HTML template of your game.

    Everything you are writing in this file will be displayed in the HTML page of your game user interface,
    in the "main game zone" of the screen.

    You can use in this template:
    _ variables, with the format {MY_VARIABLE_ELEMENT}.
    _ HTML block, with the BEGIN/END format

    See your "view" PHP file to check how to set variables and control blocks

    Please REMOVE this comment before publishing your game on BGA
-->
<div id="board_area">

  <div id="board_tile_column">

      <div id="outer_board">
        <div id="outer_board_top_row">
          <div id="direction_up" class="direction_token"></div>
          <div id="replacement_garment_chosen_holder"></div>
        </div>
        <div id="middle_board_row">
          <div id="outer_board_left_column">
            <div id="direction_left" class="direction_token"></div>
          </div>

          <div id="board_tile_container_4" class="board_tile_container"><!-- needed to remove whitespace

            --><div class="row_of_tiles"><!-- needed to remove whitespace

                --><div class="board_space_1_1"><!-- needed to remove whitespace
                    --><div id="square_0_0" class="space"></div><!-- needed to remove whitespace
                --></div><!-- needed to remove whitespace

                --><div class="board_tile_4_1"><!-- needed to remove whitespace
                    --><div id="square_1_0" class="space"></div><!-- needed to remove whitespace
                    --><div id="square_2_0" class="space"></div><!-- needed to remove whitespace
                    --><div id="square_3_0" class="space"></div><!-- needed to remove whitespace
                    --><div id="square_4_0" class="space"></div><!-- needed to remove whitespace
                --></div><!-- needed to remove whitespace


                --><div class="board_tile_4_1"><!-- needed to remove whitespace
                    --><div id="square_5_0" class="space"></div><!-- needed to remove whitespace
                    --><div id="square_6_0" class="space"></div><!-- needed to remove whitespace
                    --><div id="square_7_0" class="space"></div><!-- needed to remove whitespace
                    --><div id="square_8_0" class="space"></div><!-- needed to remove whitespace
                --></div><!-- needed to remove whitespace


                --><div class="board_space_1_1"><!-- needed to remove whitespace
                    --><div id="square_9_0" class="space"></div><!-- needed to remove whitespace
                --></div><!-- needed to remove whitespace

            --></div><!-- needed to remove whitespace



            --><div class="row_of_tiles"><!-- needed to remove whitespace
                --><div class="single_column_board_tile"><!-- needed to remove whitespace
                    --><div class="board_tile_row"><!-- needed to remove whitespace
                    --><div id="square_0_1" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace
                    --><div class="board_tile_row"><!-- needed to remove whitespace
                    --><div id="square_0_2" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace
                    --><div class="board_tile_row"><!-- needed to remove whitespace
                    --><div id="square_0_3" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace
                    --><div class="board_tile_row"><!-- needed to remove whitespace
                    --><div id="square_0_4" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace
                --></div><!-- needed to remove whitespace

                --><div id="board_tile_1" class="board_tile board_tile_image board_tile_image_{TILE_1_NUMBER}_{TILE_1_SIDE}_{TILE_1_ROTATION}"><!-- needed to remove whitespace
                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_1_1" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_2_1" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_3_1" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_4_1" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_1_2" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_2_2" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_3_2" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_4_2" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_1_3" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_2_3" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_3_3" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_4_3" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_1_4" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_2_4" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_3_4" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_4_4" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace
                --></div><!-- needed to remove whitespace

                --><div id="board_tile_2" class="board_tile board_tile_image board_tile_image_{TILE_2_NUMBER}_{TILE_2_SIDE}_{TILE_2_ROTATION}"><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_5_1" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_6_1" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_7_1" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_8_1" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_5_2" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_6_2" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_7_2" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_8_2" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_5_3" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_6_3" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_7_3" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_8_3" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_5_4" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_6_4" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_7_4" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_8_4" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                --></div><!-- needed to remove whitespace


                --><div id="right_column_off_board_top_4" class="single_column_board_tile"><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_9_1" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_9_2" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_9_3" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_9_4" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                --></div><!-- needed to remove whitespace

            --></div><!-- needed to remove whitespace



            --><div class="row_of_tiles"><!-- needed to remove whitespace

                --><div class="single_column_board_tile"><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_0_5" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_0_6" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_0_7" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_0_8" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                --></div><!-- needed to remove whitespace

                --><div id="board_tile_3" class="board_tile board_tile_image board_tile_image_{TILE_3_NUMBER}_{TILE_3_SIDE}_{TILE_3_ROTATION}"><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_1_5" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_2_5" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_3_5" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_4_5" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_1_6" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_2_6" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_3_6" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_4_6" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_1_7" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_2_7" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_3_7" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_4_7" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_1_8" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_2_8" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_3_8" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_4_8" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                --></div><!-- needed to remove whitespace

                --><div id="board_tile_4" class="board_tile board_tile_image board_tile_image_{TILE_4_NUMBER}_{TILE_4_SIDE}_{TILE_4_ROTATION}"><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_5_5" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_6_5" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_7_5" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_8_5" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_5_6" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_6_6" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_7_6" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_8_6" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_5_7" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_6_7" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_7_7" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_8_7" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_5_8" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_6_8" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_7_8" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_8_8" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                --></div><!-- needed to remove whitespace

                --><div id="right_column_off_board_bottom_4" class="single_column_board_tile"><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_9_5" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_9_6" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_9_7" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_9_8" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                --></div><!-- needed to remove whitespace

            --></div><!-- needed to remove whitespace

            --><div class="row_of_tiles"><!-- needed to remove whitespace

                --><div class="board_space_1_1"><!-- needed to remove whitespace
                    --><div id="square_0_9" class="space"></div><!-- needed to remove whitespace
                --></div><!-- needed to remove whitespace

                --><div class="board_tile_4_1"><!-- needed to remove whitespace
                    --><div id="square_1_9" class="space"></div><!-- needed to remove whitespace
                    --><div id="square_2_9" class="space"></div><!-- needed to remove whitespace
                    --><div id="square_3_9" class="space"></div><!-- needed to remove whitespace
                    --><div id="square_4_9" class="space"></div><!-- needed to remove whitespace
                --></div><!-- needed to remove whitespace

                --><div class="board_tile_4_1"><!-- needed to remove whitespace
                    --><div id="square_5_9" class="space"></div><!-- needed to remove whitespace
                    --><div id="square_6_9" class="space"></div><!-- needed to remove whitespace
                    --><div id="square_7_9" class="space"></div><!-- needed to remove whitespace
                    --><div id="square_8_9" class="space"></div><!-- needed to remove whitespace
                --></div><!-- needed to remove whitespace

                --><div class="board_space_1_1"><!-- needed to remove whitespace
                    --><div id="square_9_9" class="space"></div><!-- needed to remove whitespace
                --></div><!-- needed to remove whitespace

            --></div><!-- needed to remove whitespace

          --></div><!--

********** 5 SAUCER BOARD **********

          --><div id="board_tile_container_5" class="board_tile_container"><!-- needed to remove whitespace

          --><div class="row_of_tiles"><!-- needed to remove whitespace

               --><div class="board_space_1_1"><!-- needed to remove whitespace
                  --><div id="square_0_0" class="space"></div><!-- needed to remove whitespace
               --></div><!-- needed to remove whitespace

                --><div class="board_tile_4_1"><!-- needed to remove whitespace
                    --><div id="square_1_0" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_2_0" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_3_0" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_4_0" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_space_1_1"><!-- needed to remove whitespace
                        --><div id="square_5_0" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_4_1"><!-- needed to remove whitespace
                        --><div id="square_6_0" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_7_0" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_8_0" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_9_0" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_space_1_1"><!-- needed to remove whitespace
                        --><div id="square_10_0" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace
             --></div><!-- needed to remove whitespace

           --><div class="row_of_tiles"><!-- needed to remove whitespace

               --><div class="single_column_board_tile"><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_0_1" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_0_2" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_0_3" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_0_4" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

               --></div><!-- this comment is just to remove whitespace

                --><div id="board_tile_1" class="board_tile board_tile_image board_tile_image_{TILE_1_NUMBER}_{TILE_1_SIDE}_{TILE_1_ROTATION}"><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_1_1" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_2_1" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_3_1" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_4_1" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_1_2" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_2_2" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_3_2" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_4_2" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_1_3" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_2_3" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_3_3" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_4_3" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_1_4" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_2_4" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_3_4" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_4_4" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                --></div><!-- this comment is just to remove whitespace

                --><div id="board_tile_6" class="single_column_board_tile board_tile_4_1 board_tile_6or8_5_player board_tile_image_6or8_{TILE_6_NUMBER}_{TILE_6_SIDE}_{TILE_6_ROTATION}"><!-- needed to remove whitespace

                    --><div class="single_column_board_tile"><!-- needed to remove whitespace
                        --><div id="square_5_1" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_5_2" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_5_3" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_5_4" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

              --></div><!-- this comment is just to remove whitespace

              --><div id="board_tile_2" class="board_tile board_tile_image board_tile_image_{TILE_2_NUMBER}_{TILE_2_SIDE}_{TILE_2_ROTATION}">
                    <div class="board_tile_row">
                        <div id="square_6_1" class="space"></div><!-- needed to remove whitespace
                          --><div id="square_7_1" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_8_1" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_9_1" class="space"></div><!-- needed to remove whitespace
                --></div>
                  <div class="board_tile_row">
                      <div id="square_6_2" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_7_2" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_8_2" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_9_2" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace
                --><div class="board_tile_row"><!-- needed to remove whitespace
                    --><div id="square_6_3" class="space"></div><!-- needed to remove whitespace
                    --><div id="square_7_3" class="space"></div><!-- needed to remove whitespace
                    --><div id="square_8_3" class="space"></div><!-- needed to remove whitespace
                    --><div id="square_9_3" class="space"></div><!-- needed to remove whitespace
                --></div><!-- needed to remove whitespace
                --><div class="board_tile_row"><!-- needed to remove whitespace
                    --><div id="square_6_4" class="space"></div><!-- needed to remove whitespace
                    --><div id="square_7_4" class="space"></div><!-- needed to remove whitespace
                    --><div id="square_8_4" class="space"></div><!-- needed to remove whitespace
                    --><div id="square_9_4" class="space"></div><!-- needed to remove whitespace
                --></div><!-- needed to remove whitespace
            --></div><!-- needed to remove whitespace

                --><div id="right_column_off_board_top_5" class="single_column_board_tile"><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_10_1" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_10_2" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_10_3" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_10_4" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                --></div><!-- needed to remove whitespace

                --></div><!-- needed to remove whitespace

           --><div class="row_of_tiles"><!-- needed to remove whitespace

               --><div class="board_space_1_1"><!-- needed to remove whitespace
                  --><div id="square_0_5" class="space"></div><!-- needed to remove whitespace
               --></div><!-- needed to remove whitespace

                --><div id="board_tile_5" class="board_tile_4_1 board_tile_5or7_5_player board_tile_image_5or7_{TILE_5_NUMBER}_{TILE_5_SIDE}_{TILE_5_ROTATION}"><!-- needed to remove whitespace
                    --><div id="square_1_5" class="space"></div><!-- needed to remove whitespace
                    --><div id="square_2_5" class="space"></div><!-- needed to remove whitespace
                    --><div id="square_3_5" class="space"></div><!-- needed to remove whitespace
                    --><div id="square_4_5" class="space"></div><!-- needed to remove whitespace
                --></div><!-- needed to remove whitespace


                --><div id="board_tile_9" class="board_tile_1_1 board_tile_9_5_player board_tile_image_9_{TILE_9_NUMBER}_{TILE_9_SIDE}_{TILE_9_ROTATION}"><!-- needed to remove whitespace
                    --><div id="square_5_5" class="space"></div><!-- needed to remove whitespace
                --></div><!-- needed to remove whitespace


                --><div id="board_tile_7" class="board_tile_4_1 board_tile_5or7_5_player board_tile_image_5or7_{TILE_7_NUMBER}_{TILE_7_SIDE}_{TILE_7_ROTATION}"><!-- needed to remove whitespace
                    --><div id="square_6_5" class="space"></div><!-- needed to remove whitespace
                    --><div id="square_7_5" class="space"></div><!-- needed to remove whitespace
                    --><div id="square_8_5" class="space"></div><!-- needed to remove whitespace
                    --><div id="square_9_5" class="space"></div><!-- needed to remove whitespace
                --></div><!-- needed to remove whitespace


                --><div class="board_space_1_1"><!-- needed to remove whitespace
                    --><div id="square_10_5" class="space"></div><!-- needed to remove whitespace
                --></div><!-- needed to remove whitespace


            --></div><!-- needed to remove whitespace


            --><div class="row_of_tiles"><!-- needed to remove whitespace

                --><div class="single_column_board_tile"><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_0_6" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_0_7" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_0_8" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_0_9" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                --></div><!-- this comment is just to remove whitespace

                --><div id="board_tile_3" class="board_tile board_tile_image board_tile_image_{TILE_3_NUMBER}_{TILE_3_SIDE}_{TILE_3_ROTATION}"><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_1_6" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_2_6" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_3_6" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_4_6" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_1_7" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_2_7" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_3_7" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_4_7" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_1_8" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_2_8" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_3_8" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_4_8" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_1_9" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_2_9" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_3_9" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_4_9" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                --></div><!-- this comment is just to remove whitespace
                --><div id="board_tile_8" class="single_column_board_tile board_tile_4_1 board_tile_6or8_5_player board_tile_image_6or8_{TILE_8_NUMBER}_{TILE_8_SIDE}_{TILE_8_ROTATION}"><!-- needed to remove whitespace
                    --><div class="single_column_board_tile"><!-- needed to remove whitespace
                        --><div id="square_5_6" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_5_7" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_5_8" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_5_9" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace
                --></div><!-- this comment is just to remove whitespace

                --><div id="board_tile_4" class="board_tile board_tile_image board_tile_image_{TILE_4_NUMBER}_{TILE_4_SIDE}_{TILE_4_ROTATION}"><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_6_6" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_7_6" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_8_6" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_9_6" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_6_7" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_7_7" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_8_7" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_9_7" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_6_8" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_7_8" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_8_8" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_9_8" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_6_9" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_7_9" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_8_9" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_9_9" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                --></div><!-- needed to remove whitespace

                --><div id="right_column_off_board_bottom_5" class="single_column_board_tile"><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_10_6" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_10_7" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_10_8" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_row"><!-- needed to remove whitespace
                        --><div id="square_10_9" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                --></div><!-- needed to remove whitespace


                --><div class="row_of_tiles"><!-- needed to remove whitespace
                    --><div class="board_space_1_1"><!-- needed to remove whitespace
                        --><div id="square_0_10" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_4_1"><!-- needed to remove whitespace
                        --><div id="square_1_10" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_2_10" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_3_10" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_4_10" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_space_1_1"><!-- needed to remove whitespace
                        --><div id="square_5_10" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_tile_4_1"><!-- needed to remove whitespace
                        --><div id="square_6_10" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_7_10" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_8_10" class="space"></div><!-- needed to remove whitespace
                        --><div id="square_9_10" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                    --><div class="board_space_1_1"><!-- needed to remove whitespace
                        --><div id="square_10_10" class="space"></div><!-- needed to remove whitespace
                    --></div><!-- needed to remove whitespace

                --></div><!-- needed to remove whitespace

        --></div><!-- needed to remove whitespace

    --></div><!-- needed to remove whitespace

********** 6 SAUCER BOARD **********
      --><div id="board_tile_container_6" class="board_tile_container"><!-- needed to remove whitespace

          --><div class="row_of_tiles"><!-- needed to remove whitespace
              --><div class="board_space_1_1"><!-- needed to remove whitespace
                  --><div id="square_0_0" class="space"></div><!-- needed to remove whitespace
              --></div><!-- needed to remove whitespace


              --><div class="board_tile_4_1"><!-- needed to remove whitespace
                  --><div id="square_1_0" class="space"></div><!-- needed to remove whitespace
                  --><div id="square_2_0" class="space"></div><!-- needed to remove whitespace
                  --><div id="square_3_0" class="space"></div><!-- needed to remove whitespace
                  --><div id="square_4_0" class="space"></div><!-- needed to remove whitespace
              --></div><!-- needed to remove whitespace

              --><div class="single_row_2_width_board_tile"><!-- needed to remove whitespace
                  --><div id="square_5_0" class="space"></div><!-- needed to remove whitespace
                  --><div id="square_6_0" class="space"></div><!-- needed to remove whitespace
              --></div><!-- needed to remove whitespace

              --><div class="board_tile_4_1"><!-- needed to remove whitespace
                  --><div id="square_7_0" class="space"></div><!-- needed to remove whitespace
                  --><div id="square_8_0" class="space"></div><!-- needed to remove whitespace
                  --><div id="square_9_0" class="space"></div><!-- needed to remove whitespace
                  --><div id="square_10_0" class="space"></div><!-- needed to remove whitespace
              --></div><!-- needed to remove whitespace


              --><div class="board_space_1_1"><!-- needed to remove whitespace
                  --><div id="square_11_0" class="space"></div><!-- needed to remove whitespace

              --></div><!-- needed to remove whitespace

          --></div><!-- needed to remove whitespace

          --><div class="row_of_tiles"><!-- needed to remove whitespace

              --><div class="single_column_board_tile"><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_0_1" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_0_2" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_0_3" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_0_4" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace
              --></div><!-- needed to remove whitespace

              --><div id="board_tile_1" class="board_tile board_tile_image board_tile_image_{TILE_1_NUMBER}_{TILE_1_SIDE}_{TILE_1_ROTATION}"><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_1_1" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_2_1" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_3_1" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_4_1" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_1_2" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_2_2" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_3_2" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_4_2" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_1_3" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_2_3" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_3_3" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_4_3" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_1_4" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_2_4" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_3_4" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_4_4" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

              --></div><!-- needed to remove whitespace

              --><div id="board_tile_6" class="board_tile_6or8_6_player board_tile_image_6or8_{TILE_6_NUMBER}_{TILE_6_SIDE}_{TILE_6_ROTATION}"><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_5_1" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_6_1" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_5_2" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_6_2" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_5_3" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_6_3" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_5_4" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_6_4" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

              --></div><!-- needed to remove whitespace

              --><div id="board_tile_2" class="board_tile board_tile_image board_tile_image_{TILE_2_NUMBER}_{TILE_2_SIDE}_{TILE_2_ROTATION}"><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace

                      --><div id="square_7_1" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_8_1" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_9_1" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_10_1" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_7_2" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_8_2" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_9_2" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_10_2" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_7_3" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_8_3" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_9_3" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_10_3" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_7_4" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_8_4" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_9_4" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_10_4" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

              --></div><!-- needed to remove whitespace

              --><div id="right_column_off_board_top_5" class="single_column_board_tile"><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_11_1" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_11_2" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_11_3" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_11_4" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

              --></div><!-- needed to remove whitespace

          --></div><!-- needed to remove whitespace

          --><div class="row_of_tiles"><!-- needed to remove whitespace

              --><div class="board_tile_1_2"><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_0_5" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_0_6" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

              --></div><!-- needed to remove whitespace

              --><div id="board_tile_5" class="board_tile_5or7_6_player board_tile_image_5or7_{TILE_5_NUMBER}_{TILE_5_SIDE}_{TILE_5_ROTATION}"><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_1_5" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_2_5" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_3_5" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_4_5" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_1_6" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_2_6" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_3_6" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_4_6" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

              --></div><!-- needed to remove whitespace

              --><div id="board_tile_9" class="board_tile_2_2 board_tile_9_6_player board_tile_image_9_{TILE_9_NUMBER}_{TILE_9_SIDE}_{TILE_9_ROTATION}"><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_5_5" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_6_5" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_5_6" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_6_6" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

              --></div><!-- needed to remove whitespace

              --><div id="board_tile_7" class="board_tile_5or7_6_player board_tile_image_5or7_{TILE_7_NUMBER}_{TILE_7_SIDE}_{TILE_7_ROTATION}"><!-- needed to remove whitespace
                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_7_5" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_8_5" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_9_5" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_10_5" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_7_6" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_8_6" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_9_6" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_10_6" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

              --></div><!-- needed to remove whitespace

              --><div class="board_tile_1_2"><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_11_5" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_11_6" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

              --></div><!-- needed to remove whitespace

          --></div><!-- needed to remove whitespace

          --><div class="row_of_tiles"><!-- needed to remove whitespace

              --><div class="single_column_board_tile"><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_0_7" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_0_8" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_0_9" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_0_10" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

              --></div><!-- needed to remove whitespace

              --><div id="board_tile_3" class="board_tile board_tile_image board_tile_image_{TILE_3_NUMBER}_{TILE_3_SIDE}_{TILE_3_ROTATION}"><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_1_7" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_2_7" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_3_7" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_4_7" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_1_8" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_2_8" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_3_8" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_4_8" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_1_9" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_2_9" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_3_9" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_4_9" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_1_10" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_2_10" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_3_10" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_4_10" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

              --></div><!-- needed to remove whitespace

              --><div id="board_tile_8" class="board_tile_6or8_6_player board_tile_image_6or8_{TILE_8_NUMBER}_{TILE_8_SIDE}_{TILE_8_ROTATION}"><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_5_7" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_6_7" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_5_8" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_6_8" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_5_9" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_6_9" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_5_10" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_6_10" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

              --></div><!-- needed to remove whitespace


              --><div id="board_tile_4" class="board_tile board_tile_image board_tile_image_{TILE_4_NUMBER}_{TILE_4_SIDE}_{TILE_4_ROTATION}"><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_7_7" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_8_7" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_9_7" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_10_7" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_7_8" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_8_8" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_9_8" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_10_8" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_7_9" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_8_9" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_9_9" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_10_9" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

                  --><div class="board_tile_row"><!-- needed to remove whitespace
                      --><div id="square_7_10" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_8_10" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_9_10" class="space"></div><!-- needed to remove whitespace
                      --><div id="square_10_10" class="space"></div><!-- needed to remove whitespace
                  --></div><!-- needed to remove whitespace

              --></div><!-- needed to remove whitespace

            --><div id="right_column_off_board_bottom_5" class="single_column_board_tile"><!-- needed to remove whitespace

                --><div class="board_tile_row"><!-- needed to remove whitespace
                    --><div id="square_11_7" class="space"></div><!-- needed to remove whitespace
                --></div><!-- needed to remove whitespace

                --><div class="board_tile_row"><!-- needed to remove whitespace
                    --><div id="square_11_8" class="space"></div><!-- needed to remove whitespace
                --></div><!-- needed to remove whitespace

                --><div class="board_tile_row"><!-- needed to remove whitespace
                    --><div id="square_11_9" class="space"></div><!-- needed to remove whitespace
                --></div><!-- needed to remove whitespace

                --><div class="board_tile_row"><!-- needed to remove whitespace
                    --><div id="square_11_10" class="space"></div><!-- needed to remove whitespace
                --></div><!-- needed to remove whitespace

            --></div><!-- needed to remove whitespace

        --></div><!-- needed to remove whitespace

        --><div class="row_of_tiles"><!-- needed to remove whitespace

            --><div class="board_space_1_1"><!-- needed to remove whitespace
                --><div id="square_0_11" class="space"></div><!-- needed to remove whitespace
            --></div><!-- needed to remove whitespace

            --><div class="board_tile_4_1"><!-- needed to remove whitespace
                --><div id="square_1_11" class="space"></div><!-- needed to remove whitespace
                --><div id="square_2_11" class="space"></div><!-- needed to remove whitespace
                --><div id="square_3_11" class="space"></div><!-- needed to remove whitespace
                --><div id="square_4_11" class="space"></div><!-- needed to remove whitespace
            --></div><!-- needed to remove whitespace

            --><div class="single_row_2_width_board_tile"><!-- needed to remove whitespace
                --><div id="square_5_11" class="space"></div><!-- needed to remove whitespace
                --><div id="square_6_11" class="space"></div><!-- needed to remove whitespace
            --></div><!-- needed to remove whitespace

            --><div class="board_tile_4_1"><!-- needed to remove whitespace
                --><div id="square_7_11" class="space"></div><!-- needed to remove whitespace
                --><div id="square_8_11" class="space"></div><!-- needed to remove whitespace
                --><div id="square_9_11" class="space"></div><!-- needed to remove whitespace
                --><div id="square_10_11" class="space"></div><!-- needed to remove whitespace
            --></div><!-- needed to remove whitespace

            --><div class="board_space_1_1"><!-- needed to remove whitespace
                --><div id="square_11_11" class="space"></div><!-- needed to remove whitespace
            --></div><!-- needed to remove whitespace

        --></div><!-- needed to remove whitespace

    --></div><!-- board_tile_container_6

    --><div id="outer_board_right_column"><!-- needed to remove whitespace
        --><div id="direction_right" class="direction_token"></div><!-- needed to remove whitespace
    --></div><!-- needed to remove whitespace

--></div> <!--middle_board_row-->

<div id="discs"></div>





      <div id="outer_board_bottom_row">
        <div id="direction_down" class="direction_token"></div>
      </div>
    </div> <!--outer_board-->
  </div> <!--board_tile_column -->

  <div id="garments_container">
      <!-- BEGIN lost_crewmembers -->
          <div class="available_garment_group {LEFT_OR_RIGHT}">
              <div id="garment_holder_head_{PLAYER_COLOR}" class="garment_holder"></div>
              <div id="garment_holder_body_{PLAYER_COLOR}" class="garment_holder"></div>
              <div id="garment_holder_legs_{PLAYER_COLOR}" class="garment_holder"></div>
              <div id="garment_holder_feet_{PLAYER_COLOR}" class="garment_holder"></div>
          </div>
      <!-- END lost_crewmembers -->
  </div>

          <!-- BEGIN saucer -->
              <div class="mat_and_stuff_row">
                  <div class="mat_row">
                      <div class="saucer_owner_{PLAYER_COLOR}" style="color:#{PLAYER_COLOR}">
                        {PLAYER_NAME}
                      </div>

                      <div id="upgrade_hand_{PLAYER_COLOR}" style="color:#{PLAYER_COLOR}">

                      </div>

                      <div id="move_card_hand_{PLAYER_COLOR}" class="move_card_hand" style="color:#{PLAYER_COLOR}">
                          <div id="move_card_1_{PLAYER_COLOR}" class="move_card_in_hand"></div>
                          <div id="move_card_2_{PLAYER_COLOR}" class="move_card_in_hand"></div>
                          <div id="move_card_0_{PLAYER_COLOR}" class="move_card_in_hand"></div>
                      </div>

                      <div id="saucer_mat_{PLAYER_COLOR}" class="opponent_mat_holder ostrichMat">
                          <div id="mat_head_row" class="mat_inner_row">
                              <div id="mat_head_backpack_2_{PLAYER_COLOR}" class="mat_head_backpack_holder"></div>
                              <div id="mat_head_backpack_3_{PLAYER_COLOR}" class="mat_head_backpack_holder"></div>
                              <div id="mat_head_backpack_4_{PLAYER_COLOR}" class="mat_head_backpack_holder"></div>
                              <div id="mat_head_wearing_1_{PLAYER_COLOR}" class="mat_head_wearing_holder"></div>
                          </div>
                          <div id="mat_body_row" class="mat_inner_row">
                              <div id="mat_body_backpack_2_{PLAYER_COLOR}" class="mat_body_backpack_holder"></div>
                              <div id="mat_body_backpack_3_{PLAYER_COLOR}" class="mat_body_backpack_holder"></div>
                              <div id="mat_body_backpack_4_{PLAYER_COLOR}" class="mat_body_backpack_holder"></div>
                              <div id="mat_body_wearing_1_{PLAYER_COLOR}" class="mat_body_wearing_holder"></div>
                          </div>
                          <div id="mat_legs_row" class="mat_inner_row">
                              <div id="mat_legs_backpack_2_{PLAYER_COLOR}" class="mat_legs_backpack_holder"></div>
                              <div id="mat_legs_backpack_3_{PLAYER_COLOR}" class="mat_legs_backpack_holder"></div>
                              <div id="mat_legs_backpack_4_{PLAYER_COLOR}" class="mat_legs_backpack_holder"></div>
                              <div id="mat_legs_wearing_1_{PLAYER_COLOR}" class="mat_legs_wearing_holder"></div>
                          </div>
                          <div id="mat_feet_row" class="mat_inner_row">
                              <div id="mat_feet_backpack_2_{PLAYER_COLOR}" class="mat_feet_backpack_holder"></div>
                              <div id="mat_feet_backpack_3_{PLAYER_COLOR}" class="mat_feet_backpack_holder"></div>
                              <div id="mat_feet_backpack_4_{PLAYER_COLOR}" class="mat_feet_backpack_holder"></div>
                              <div id="mat_feet_wearing_1_{PLAYER_COLOR}" class="mat_feet_wearing_holder"></div>
                          </div>
                      </div>
                  </div>

                  <div class="zig_and_zag_row">
                      <div id="zig_holder_{PLAYER_COLOR}" class="zig_holder"></div>
                      <div id="zag_holder_{PLAYER_COLOR}" class="zag_holder"></div>
                  </div>
              </div>

          <!-- END saucer -->




</div><!--board_area-->



<script type="text/javascript">

// Templates

var jstpl_saucer='<div class="saucer saucer_color_${color} saucer_float" id="saucer_${color}"></div>';

var jstpl_zag='<div class="zag component_circle" id="zag_${color}"></div>';

var jstpl_garment='<div class="garment" id="garment_${garment_type}_${color}"></div>';

<!-- match the class to the css class for the image location -->
<!-- match the id to the js file when you dojo.place it -->
var jstpl_mymovementcard = '<div class="move_card component_rounding" id="mymovementcard_${player_id}" style="background-position:-${x}px -${y}px">\
                        </div>';

<!-- match the class to the css class for the image location -->
<!-- match the id to the js file when you dojo.place it -->
var jstpl_zigback = '<div class="zigBack component_rounding" id="zigback_${player_id}" style="background-position:-${x}px -${y}px">\
                                                </div>';

<!-- match the class to the css class for the image location -->
<!-- match the id to the js file when you dojo.place it -->
var jstpl_myTrapInHand = '<div class="myTrapInHand component_rounding" id="myTrapInHand_${player_id}" style="background-position:-${x}px -${y}px">\
                            </div>';

<!-- match the class to the css class for the image location -->
<!-- match the id to the js file when you dojo.place it -->
var jstpl_trapBack = '<div class="myTrap component_rounding" id="trapBack_${player_id}" style="background-position:-${x}px -${y}px">\
                    </div>';


var jstpl_player_board_for_saucer = '<div id="player_board_for_saucer_${color}">\
                                        <div id="player_board_ostrich_and_crown_holder_${color}" class="player_board_ostrich_and_crown_holder">\
                                          <div id="player_board_crown_holder_${color}" class="player_board_crown_holder"></div>\
                                          <div id="player_board_ostrich_holder_${color}" class="player_board_ostrich_holder"></div>\
                                        </div>\
                                        <div id="player_board_saucer_mat_holder_${color}" class="player_board_saucer_mat_holder">\
                                          <div id="player_board_saucer_mat_pilot_${color}" class="player_board_saucer_mat_pilot"></div>\
                                          <div id="player_board_saucer_mat_engineer_${color}" class="player_board_saucer_mat_engineer"></div>\
                                          <div id="player_board_saucer_mat_doctor_${color}" class="player_board_saucer_mat_doctor"></div>\
                                          <div id="player_board_saucer_mat_scientist_${color}" class="player_board_saucer_mat_scientist"></div>\
                                        </div>\
                                        <div id="player_board_trap_and_zag_holder_${color}" class="player_board_trap_and_zag_holder">\
                                          <div id="player_board_trap_holder_${color}" class="player_board_trap_holder"></div>\
                                          <div id="player_board_zag_holder_${color}" class="player_board_zag_holder"></div>\
                                        </div>\
                                        <div id="player_board_direction_holder_${owner}" class="player_board_direction_holder"></div>\
                                        <div id="player_board_ostrich_and_crown_holder_${color}" class="player_board_ostrich_and_crown_holder">\
                                          <div id="player_board_crown_holder_${color}" class="player_board_crown_holder"></div>\
                                          <div id="player_board_ostrich_holder_${color}" class="player_board_ostrich_holder"></div>\
                                        </div>\
                                        <div id="player_board_trap_and_zag_holder_${color}" class="player_board_trap_and_zag_holder">\
                                          <div id="player_board_trap_holder_${color}" class="player_board_trap_holder"></div>\
                                          <div id="player_board_zag_holder_${color}" class="player_board_zag_holder"></div>\
                                        </div>\
                                    </div>';

var jstpl_crown = '<div id="player_board_crown" class="starting_color_${color}"></div>';
var jstpl_arrow = '<div id="player_board_arrow_${id}" class="player_board_arrow" style="background-position:-${x}px -${y}px"></div>';

var jstpl_moveCard = '<div id="move_card_${distance}_${color}" class="move_card component_rounding" style="background-position:-${x}px -${y}px"></div>';


</script>

{OVERALL_GAME_FOOTER}
