<?php
require_once 'Superfeedr.php';

$username = 'XXXXXXXXXX'; // your superfeedr username
$password = 'XXXXXXXXXX'; // your superfeedr password
$callback = 'http//XXXX'; // your callback URL

/**
 * Example feed.
 *
 * Go to http://pubsubhubbub-example-app.appspot.com to update it.
 */
$feed = 'http://pubsubhubbub-example-app.appspot.com/feed';

$superfeedr = new Superfeedr($username, $password, $callback);

//
// Is it a verification callback?
//
if (!$res = $superfeedr->verify()) {
    //
    // It must be a regular callback, with content.
    //
    $json = $superfeedr->callback();
    
    //
    // Dump the content to a file.
    //
    file_put_contents('result.txt', print_r($json, true) . "\n", FILE_APPEND);
}

?>