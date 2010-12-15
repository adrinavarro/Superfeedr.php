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

if ($superfeedr->subscribe($feed)) {
    echo 'Subscribed' . "\n";
}

?>