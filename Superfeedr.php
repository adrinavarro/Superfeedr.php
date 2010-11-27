<?php
class Superfeedr {
	var $callback = 'http://domain/endpoint.php';
	var $authentication = 'user:password';
	var $password = 'signature';
	
	function action($mode = 'subscribe', $url, $secret = null) {
		$secret = ($secret?$secret:$this->password);
		$post = '';
		$post .= 'hub.callback='.urlencode($this->callback);
		$post .= '&hub.mode='.urlencode($mode);
		$post .= '&hub.topic='.urlencode($url);
		$post .= '&hub.verify=sync';
		$post .= '&hub.verify_token='.urlencode(md5($mode.$this->password));
		$post .= '&hub.secret='.urlencode(sha1($secret));
		
		return $this->request('http://superfeedr.com/hubbub', 'post', $post);
	}
	
	function verify() {
		// For some reason, PHP doesn't take in account dots in GET elements
		if($_GET['hub_verify_token'] == md5($_GET['hub_mode'].$this->password)) {
			echo $_GET['hub_challenge'];
		}
	}
	
	function callback() {
		$input = file_get_contents('php://input');
		if($input) {
			if($check = trim($_SERVER['HTTP_X_HUB_SIGNATURE'])) {
				$sum = hash_hmac('sha1', $input, sha1($this->element_secret($input)));
				if($check == "sha1=".$sum) {
					$this->request = trim($input);
					return true;
				} else {
					return false;
				}
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
	
	function element_secret($element) {
		// Here, you can parse the element then get its secret key (the one sent when subscribing), for example, from a database
		return $this->password;
	}
	
	function request($target, $type = 'get', $parameters = null, $auth = true) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $target);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		if($type == 'post') {
			curl_setopt($ch, CURLOPT_POST, 1);
			if($parameters) {
				curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
			}
		}
		if($auth) {
			curl_setopt($ch, CURLOPT_USERPWD, $this->authentication);
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		}
		$response = curl_exec($ch);
		$info = curl_getinfo($ch);
		curl_close($ch);
		return array('info' => $info, 'response' => $response);
	}
	
	function clean_string($string) {
		$string = preg_replace("/(\<script)(.*?)(script>)/si", '', (string)$string);
		$string = preg_replace("/(\<style)(.*?)(style>)/si", '', $string);
		$string = str_replace("><", "> <", $string);
		$string = strip_tags($string);
		while(strstr($string, "\n") or strstr($string, "  ")) {
			$string = str_replace(array("\n", "  "), " ", $string);
		}
		$string = trim($string);
		return $string;
	}
	
	function parse_entries($xml) {
		$xml = simplexml_load_string($xml);
		if($xml) {
			$response = array();
			
			$feedid = md5($xml->id);
			$feedtitle = $this->clean_string($xml->title);
			$entries = ((is_array($xml->entry))?$xml->entry:(array($xml->entry)));
			
			foreach($entries as $entry) {
				$link = false;
				foreach($entry->link as $entry_link) {
					$attrs = $entry_link->attributes();
					if($attrs['rel'] == 'alternate') {
						$link = (string)$attrs['href'];
					}
				}
				if($link) {
					if($title = (string)($entry->title) and $time = strtotime($entry->published)) {
						$summary = (string)($entry->content?$entry->content:$entry->summary);
						
						$tags = array();
						foreach($entry->category as $category) {
							if($tag = current($category->attributes())) {
								$tag = mb_strtolower($this->clean_string(current($tag)));
								$tags[$tag] = 1;
							} else {
								continue;
							}
						}
						
						$tags = array_keys($tags);
						
						$title = $this->clean_string($title);
						$summary = $this->clean_string($summary);
						
						if($time > time()) $time = time();
						
						$response[] = array('source' => $feedtitle, 'feed' => $feedid, 'link' => $link, 'title' => trim($title), 'summary' => trim($summary), 'tags' => $tags, 'time' => $time);
					} else {
						continue;
					}
				} else {
					continue;
				}
			}
			
			if($response) {
				return $response;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
}