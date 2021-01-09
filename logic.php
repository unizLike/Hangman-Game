<?php

/**
 * Handle the logic of the game
 */

include_once __DIR__ . "/debug.php";

function generate_random_phrase() {
    /**
     * Generate a random phrase from the dictionary in dati.php.
     * Used in singleplayer games.
     */
    $dizionario = include_once __DIR__ . "/dati.php";

    return $dizionario[array_rand($dizionario)];
}

function initialize_game($gamemode, $phrase) {
    /**
     * Initialize the game's as PHP $_SESSION variable.
     */
    if ($gamemode == "singleplayer") {
       $phrase = generate_random_phrase();
    }

    /**
     * Could be singleplayer or multiplayer
     */
    $_SESSION["gamemode"] = $gamemode;

    /**
     * The actual phrase either choosen by the player on
     * multiplayer games or auto-generated by generate_random_choice()
     * on singleplayer games. Using a PHP $_SESSION instead of storing it
     * on the client memory helps preventing cheating.
     */
    $_SESSION["phrase"] = $phrase;

    /**
     * Stage of the game. Increase by one when the player makes
     * an error, like a wrong letter or phrase guess.
     * Also used to keep track of the images/stages/*.png
     * and determines when the game has been lost.
     */
    $_SESSION["stage"] = 0;

    /**
     * Game status, must be either "playing", "won" or "lost".
     */
    $_SESSION["status"] = "playing";

    /**
     * List of already tried letters
     */
    $_SESSION["tried_chars"] = array();

    /**
     * When the user has started playing the game, stored
     * in UNIX epoch (the amount of seconds passed from 1 Jan 1970).
     * This is used to measure how much time the player
     * has passed playing the current game.
     * Kept on the server to prevent leaderboard cheating.
     */
    $_SESSION["start_time"] = microtime(true);

    /**
     * Total attempts of the player, like "stage" but also including
     * the right attempts.
     */
    $_SESSION["attempts"] = 0;
}

function is_playing() {
    /**
     * Is the player associated with the current session currently
     * playing the game or has it won/lost or never started?
     */
    return isset($_SESSION["status"]) && $_SESSION["status"] === "playing";
}

function get_hidden_phrase() {
    /**
     * Compute on the fly the famous hidden version of the phrase, based
     * on the actual letters tried by the player.
     * 
     * Babbo Natale => _____ ______ on the first stage
     * Babbo Natale => B_bb_ ______ on the second stage if the user has guessed the letter B
     * and so on...
     */
    $actualPhrase = $_SESSION["phrase"];
    $lowerCasePhrase = strtolower($_SESSION["phrase"]);
    $triedChars = $_SESSION["tried_chars"];
    $hiddenPhrase = "";

    for ($i = 0; $i < strlen($actualPhrase); $i++) {
        $currentChar = $lowerCasePhrase[$i];

        // Spaces must not be converted
        if ($currentChar === " ") {
            $hiddenPhrase .= " ";
        
        } else {
            // If the char has been tried by the user and
            // exist in the phrase, append it to the hidden phrase string.
            if (
                in_array($currentChar, $triedChars) &&
                in_array($currentChar, str_split($lowerCasePhrase))
            ) {
                $hiddenPhrase .= $actualPhrase[$i];
            } else {
                $hiddenPhrase .= "_";
            }
        }
    }

    return $hiddenPhrase;
}

function get_condemned_image() {
    /**
     * Return the current condemned image as an absolute path for
     * the browser to render.
     */
    return "images/stages/" . ($_SESSION["stage"] + 1) . ".png";
}

function guess_phrase($phrase) {
    // Convert both strings to lowercase
    $userPhrase = strtolower($phrase);
    $gamePhrase = strtolower($_SESSION["phrase"]);

    $isGuessRight = $userPhrase === $gamePhrase;
    
    $_SESSION["attempts"] += 1;

    // User has won, set session status accordingly.
    if ($isGuessRight) {
        $_SESSION["status"] = "won";
        $_SESSION["duration"] = microtime(true) - $_SESSION["start_time"];
    } else {
        $_SESSION["stage"] += 1;
    }

    if ($_SESSION["stage"] === 6) {
        $_SESSION["status"] = "lost";
        $_SESSION["duration"] = microtime(true) - $_SESSION["start_time"];
    }

    return $isGuessRight;
}

function guess_letter($letter) {
    $userLetter = strtolower($letter);
    $gamePhrase = strtolower($_SESSION["phrase"]);
    $triedChars = $_SESSION["tried_chars"];

    // Don't do anything if the letter has been already tried before.
    if (in_array($userLetter, $triedChars)) {
        return;
    }

    $_SESSION["attempts"] += 1;

    $isGuessRight = false !== strpos($gamePhrase, $userLetter);

    array_push($_SESSION["tried_chars"], $userLetter);

    if (!$isGuessRight) {
        $_SESSION["stage"] += 1;
    }

    if ($_SESSION["stage"] === 6) {
        $_SESSION["status"] = "lost";
        $_SESSION["duration"] = microtime(true) - $_SESSION["start_time"];

    } else if (strtolower(get_hidden_phrase()) === $gamePhrase) {
        $_SESSION["status"] = "won";
        $_SESSION["duration"] = microtime(true) - $_SESSION["start_time"];
    }

    return $isGuessRight;
}
