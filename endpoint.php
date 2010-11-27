<?php
require 'Superfeedr.php';

$s = new Superfeedr;

switch($_GET['hub_mode']) {
	case 'subscribe':
	case 'unsubscribe':
		$s->verify();
	break;
	
	default:
		if($s->callback() and $entries = $s->parse_entries($s->request)) {
			// $entries is an array
		}
	break;
}