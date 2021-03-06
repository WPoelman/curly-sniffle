<?php
/**
 * Controller
 * User: Eindopdracht Webprogramming
 * Date: 23-05-2019
 */

include 'model.php';

// routes should be with _ instead of camelCase, because they are transformed to URL's

function do_action($info) {
	// let the server know which action the client takes and with what parameters
	$newgamestate = null;
	if (!isset($_POST['action']) or !isset($_POST['parameter'])) {
		return error("invalid action");
	} else {
		$action    = $_POST['action'];
		$parameter = $_POST['parameter'];
		$gamestate = getGamestate();
		$round     = $gamestate['round'];

		if (isset($gamestate['winner'])) {
			$winner = $gamestate['winner'];

			return error("There is already a winner: $winner");
		}
		if ($round < 1) {
			return error('you cannot choose an action yet');
		}
		$player = $_SESSION['playernum']; // player1 or player2

		if (isset($gamestate["round-$round"][$player])) {
			return error('you already played this round');
		}

		if ($action == 'attack') {
			$newgamestate = attack($gamestate[$player], $round, $parameter);
			// continue
		} elseif ($action == 'switch') {
			$newgamestate = switchTo($gamestate, $player, $round, $parameter);
			// continue
		} else {
			return error("invalid action");
		}

		// continued if no errors
		if ($newgamestate) {
			// if both players have filled in their action, calculate who goes first and how many damage the attacks do
			if (sizeof($newgamestate["round-$round"]) == 2) {
				// both players have played
				// calculate order and damage stuff here

				calculateRoundResults($newgamestate, $round);
			}
		}

	}
}

$routes->newRoute('do_action', 'post');

function start_game($info) {
	// let the server know that the client has selected a name and some pokemon
	if (!getSessionVar('gameid')) {
		$username     = $_POST['username'];
		$pokemon      = $_POST['pokemon'];
		$added_player = addPlayer($username, $pokemon);

		if (sizeof($pokemon) != 3) {
			return error('Invalid amount of pokemon chosen!');
		}

		if (strlen($username > 30)) {
			return error('Username is too long, max 30 characters!');
		}

		if (!$added_player) {
			// game is full, should not happen but should still be checked (to prevent over-full sessions)
			session_destroy();
			unset($_SESSION);

			return error('game is full');
		} else {
			send(getGameInfo());

			return true;
		}
	}

	return error('you are already in a game');
}

$routes->newRoute('start_game', 'post');

// used at the end of a game, when the old data can be removed
function reset_player() {
	session_destroy();
	unset($_SESSION);
}

$routes->newRoute('reset_player', 'post');


// used when a player leaves the window and comes back
function resume_game($info) {
	$gamestate = getGamestate();
	send([
		'function' => 'roundchange',
		'data'     => $gamestate,
		'me'       => getSessionVar("playernum"),
	]);
}

$routes->newRoute('resume_game', 'get');


// this is to check if the other player has done an action in a round
function game_info($info) {
	$gamestate = getGamestate();

	$prevround = getSessionVar('round') or 0;
	$round = $_SESSION['round'] = $gamestate['round'];

	if ($round > $prevround) {

		if (isset($gamestate['winner'])) {
			sendWinner($gamestate);
		} else {
			send([
				'function' => 'roundchange',
				'data'     => $gamestate,
				'me'       => getSessionVar("playernum"),
			]);
		}

	}
	else {
		if(isset($gamestate["round-$round"])){
			// if there even is a round yet
			
			// no new round -> check if it has been too long
			$lastplayed = time() - reset($gamestate["round-$round"])['time'];
			$playing_user = key($gamestate["round-$round"]);
			if($lastplayed > 60){
				// if only one player does something, they automatically win

				$gamestate['winner'] = $gamestate[$playing_user]['username'];

				sendWinner($gamestate);
			}
		}
	}

}

$routes->newRoute('game_info', 'get');


function get_profile($info) {
	send(getGameInfo());
}

$routes->newRoute('get_profile', 'get');


$routes->start();