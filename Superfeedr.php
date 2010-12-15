<?php
/*
The MIT License

Copyright (c) 2010 - Adrián Navarro, Bruno Pedro

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/

/**
 * Access to Superfeedr's pubsubhubbub functionalities.
 *
 * @author Bruno Pedro <bpedro@tarpipe.com>
 */
class Superfeedr {
    private $apiUrl = 'https://superfeedr.com/hubbub';
	private $callback = null;
	private $authentication = null;
	private $hubSecret = 'myHubSecret';

    /**
     * Superfeedr constructor
     *
     * Accepts a username, a password and a callback URL
     * and saves that information on private attributes.
     *
     * @param string $username Your Superfeedr username.
     * @param string $password Your Superfeedr password.
     * @param string $callback The callback URL.
     * @param string $hubSecret Optional hub secret.
     * @return void
     * @uses sha1
     **/
    public function __construct($username, $password, $callback = null, $hubSecret = null)
    {
        $this->authentication = $username . ':' . $password;
        if (!empty($callback)) {
            $this->callback = $callback;
        }
        if (!empty($hubSecret)) {
            $this->hubSecret = $hubSecret;
        } else {
            $this->hubSecret = sha1($this->hubSecret);
        }
    }

    /**
     * Set some common and needed curl options.
     *
     * @param string &$ch The curl resource (see http://php.net/manual/en/function.curl-exec.php)
     * @param string $authHeader The OAuth header
     * @return void
     * @uses curl_setopt
     */
    private function setCurlOptions(&$ch, $headers = array())
    {
        curl_setopt($ch, CURLOPT_USERPWD, $this->authentication);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_BUFFERSIZE, 4096);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 25);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    }

    /**
     * Wrapper to the curl_exec() function.
     *
     * Tries to execute a curl session and throws an exception
     * in case something goes wrong.
     *
     * @param string &$ch The curl resource (see http://php.net/manual/en/function.curl-exec.php)
     * @return mixed The curl execution result, typically the HTTP content.     
     * @uses curl_exec
     */
    private function curlExec(&$ch)
    {
        $res = curl_exec($ch);
        if (false === $res) {
            throw new Exception(curl_error($ch), curl_errno($ch)); 
        }
        return $res;
    }

    /**
     * Make a call to the Superfeedr API URL.
     *
     * Tries to make a specific call to the Superfeedr API URL
     * and returns its output.
     *
     * @param string $action The hub.mode parameter, usually one of (subscribe, unsubscribe, retrieve).
     * @param string $url The feed URL to act upon.
     * @param string $hubSecret Optional hub secret.
     * @return mixed The result of the call to the Superfeedr API
     * @uses curl_ini
     * @uses curl_setopt
     * @uses setCurlOptions
     * @uses curlExec
     * @uses urlencode
     * @uses sha1
     **/
    public function call($action, $url, $hubSecret = null)
    {
        if (!empty($hubSecret)) {
	        $this->hubSecret = $hubSecret;
	    }

        $params = array(
            'hub.callback' => $this->callback,
            'hub.mode' => $action,
            'hub.topic' => $url,
            'hub.verify' => 'sync',
            'hub.verify_token' => urlencode(md5($action . $this->hubSecret)),
            'hub.secret' => urlencode(sha1($this->hubSecret))
        );

        $ch = curl_init($this->apiUrl);
        $this->setCurlOptions($ch, array('Accept: application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        $res = $this->curlExec($ch);
        return array('info' => curl_getinfo($ch), 'data' => $res);
    }

    /**
     * Subscribe to a feed.
     *
     * @param string $url The URL to subscribe to.
     * @param string $hubSecret Optional hub secret.
     * @return mixed The result of the subscription call.
     * @uses call
     **/
    public function subscribe($url, $hubSecret = null)
    {
        $res = $this->call('subscribe', $url, $hubSecret);
        if (!empty($res['info']['http_code']) &&
            $res['info']['http_code'] == 204) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Unsubscribe from a feed.
     *
     * @param string $url The URL to unsubscribe from.
     * @param string $hubSecret Option hub secret.
     * @return mixed The result of the unsubscription call.
     * @uses call
     **/
    public function unsubscribe($url, $hubSecret = null)
    {
        $res = $this->call('unsubscribe', $url, $hubSecret);
        if (!empty($res['info']['http_code']) &&
            $res['info']['http_code'] == 204) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Retrieve information and data from a feed.
     *
     * @param string $url The feed URL.
     * @return array Information and data from a feed.
     * @uses curl_init
     * @uses setCurlOptions
     * @uses curlExec
     **/
    public function retrieve($url)
    {
        $params = array(
            'hub.mode' =>'retrieve',
            'hub.topic' => $url,
        );
        $ch = curl_init($this->apiUrl . '?' . http_build_query($params));
        $this->setCurlOptions($ch, array('Accept: application/json'));
        $res = $this->curlExec($ch);
        return array('info' => curl_getinfo($ch), 'data' => $res);
    }

    /**
     * Receives a verification call from Superfeedr and responds.
     *
     * @param string $hubSecret Optional hub secret.
     * @return array The data sent from Superfeedr
     **/
	function verify($hubSecret = null)
	{
	    if (!empty($hubSecret)) {
	        $this->hubSecret = $hubSecret;
	    }

		if (!empty($_GET['hub_verify_token']) &&
		    $_GET['hub_verify_token'] == md5($_GET['hub_mode'] . $this->hubSecret)) {
			echo $_GET['hub_challenge'];
			return $_GET;
		} else {
		    return false;
		}
	}

    /**
     * Receives a callback from Superfeedr and validate its authenticity.
     *
     * @param string $hubSecret Optional hub secret.
     * @return mixed false or an object representing the feed data.
     * @uses file_get_contents
     * @uses trim
     * @uses hash_hmac
     * @uses sha1
     * @uses json_decode
     **/
	function callback($hubSecret = null)
	{
	    if (!empty($hubSecret)) {
	        $this->hubSecret = $hubSecret;
	    }

		if ($input = file_get_contents('php://input')) {
			if($check = trim($_SERVER['HTTP_X_HUB_SIGNATURE'])) {
				$sum = hash_hmac('sha1', $input, sha1($this->hubSecret));
				if($check == 'sha1=' . $sum) {
					return json_decode($input);
				}
			}
		}
		return false;
	}
}
?>