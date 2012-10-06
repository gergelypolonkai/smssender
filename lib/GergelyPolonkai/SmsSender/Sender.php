<?php
namespace GergelyPolonkai\SmsSender;

use RuntimeException;

class Sender
{
    /**
     * The internal libCURL handle
     *
     * @var resource $curl_handle
     */
    private $curl_handle;

    /**
     * The Cookie Jar
     *
     * @var array $cookies
     */
    private $cookies;

    /**
     * The authentication token received from the server during login
     *
     * @var string $token
     */
    private $token;

    /**
     * Constructor
     *
     * @param string  $senderUrl
     * @param string  $contentType
     * @param string  $contentEncoding
     * @param boolean $verifySsl
     * @param boolean $verbose
     */
    public function __construct($senderUrl, $contentType = 'application/json', $contentEncoding = 'utf-8', $verifySsl = false, $verbose = false)
    {
        /*
         * Set up internal variables
         */
        $this->cookies = array();
        $this->token = null;

        /*
         * Set up the CURL handle based on configuration options
         */
        $this->curl_handle = curl_init();
        curl_setopt($this->curl_handle, CURLOPT_SSL_VERIFYPEER, $verifySsl);
        curl_setopt($this->curl_handle, CURLOPT_URL, $senderUrl);
        curl_setopt($this->curl_handle, CURLOPT_HTTPHEADER, array(
            'Content-Type: ' . $contentType,
            'Content-Encoding: ' . $contentEncoding,
        ));
        curl_setopt($this->curl_handle, CURLOPT_VERBOSE, $verbose);
        // TODO: Make this configurable
        curl_setopt($this->curl_handle, CURLOPT_PROXY, false);

        curl_setopt($this->curl_handle, CURLOPT_POST, true);
        curl_setopt($this->curl_handle, CURLOPT_HEADER, true);
        curl_setopt($this->curl_handle, CURLOPT_TRANSFERTEXT, false);
        curl_setopt($this->curl_handle, CURLOPT_RETURNTRANSFER, true);

    }

    /**
     * Set a cookie for the connection
     *
     * @param string $name  The name of the cookie
     * @param string $value The value of the cookie
     */
    public function setCookie($name, $value)
    {
        if ($value === null) {
            unset($this->cookies[$name]);
        } else {
            $this->cookies[$name] = $value;
        }
    }

    /**
     * Get the value of a cookie
     *
     * @param  string $name The name of the cookie to get
     * @return string       The value of the cookie, or null if the cookie is
     *                      not found
     */
    public function getCookie($name)
    {
        if (array_key_exists($name, $this->cookies)) {
            return $this->cookies[$name];
        }

        return null;
    }

    /**
     * Get all the cookies for CURLOPT_COOKIE
     *
     * @return string All the cookies in the form "name=value; name=value"
     */
    private function getCookies()
    {
        $ret = array();
        foreach ($this->cookies as $name => $value) {
            $ret[] = urlencode($name) . '=' . urlencode($value);
        }

        return implode(';', $ret);
    }

    /**
     * Send a JSON-RPC request to the server, and returns the result
     *
     * @param  string $method   The RPC method name
     * @param  array $data      The post data
     * @return mixed            The JSON data returned by the server
     * @throws RuntimeException Upon a HTTP (CURL) error
     * @throws RuntimeException Upon an invalid HTTP response
     * @throws RuntimeException Upon a JSON response containing an error
     * @throws RuntimeException Upon an invalid JSON response
     */
    private function jsonRequest($method, $data)
    {
        // TODO: Use JMSSerializer maybe?
        $postData = json_encode(array(
            'jsonrpc' => '1.0',
            'id' => $method . '-' . uniqid(),
            'method' => $method,
            'params' => $data,
        ));
        curl_setopt($this->curl_handle, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($this->curl_handle, CURLOPT_COOKIE, $this->getCookies());
        $result = curl_exec($this->curl_handle);

        if ($result === false) {
            throw new RuntimeException('CURL error: ' . curl_error($this->curl_handle));
        }

        $info = curl_getinfo($this->curl_handle);
        if ($info['http_code'] != 200) {
            throw new RuntimeException('Bad status code received (' . $info['http_code'] . ')!');
        }

        $m = array();
        if (!preg_match('/(.*?)\r\n\r\n(.*)/s', $result, $m)) {
            throw new RuntimeException('Invalid HTTP response!');
        }
        list($all, $headers, $body) = $m;

        $matches = array();
        preg_match_all('/Set-Cookie: ([^=]+)=(.*);/U', $headers, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $this->setCookie($match[1], $match[2]);
        }

        // TODO: use JMSSerializer maybe?
        $resultObj = json_decode($body, true);
        if (!is_array($resultObj)) {
            throw new RuntimeException('Result is not a JSON response!');
        } elseif (array_key_exists('error', $resultObj) && ($resultObj['error'] !== null)) {
            throw new RuntimeException('Result has an error: ' . $resultObj['error']);
        } elseif (!array_key_exists('result', $resultObj)) {
            throw new RuntimeException('Result has no "result" field');
        }

        return $resultObj['result'];
    }

    public function login($username, $password)
    {
        try {
            $this->token = $this->jsonRequest('login', array($username, $password));
        } catch (RuntimeException $e) {
            $this->token = null;
        }
        return ($this->token !== null);
    }

    public function sendMessage($recipient, $message, array $passwordLocations)
    {
        if ($this->token === null) {
            return false;
        }

        if ($passwordLocations === null) {
            $passwordLocations = array();
        }

        $this->jsonRequest('send', array($this->token, $recipient, $message, $passwordLocations));

        return true;
    }

    public function logout()
    {
        $this->jsonRequest('logout', array($this->token));

        return true;
    }
}
