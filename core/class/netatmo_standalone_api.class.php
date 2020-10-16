<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes********************************* */
require_once __DIR__ . '/../../../../core/php/core.inc.php';

class netatmo_standalone_api {
  const BACKEND_BASE_URI=  "https://api.netatmo.net/";
  const BACKEND_SERVICES_URI=  "https://api.netatmo.net/api";
  const BACKEND_ACCESS_TOKEN_URI=  "https://api.netatmo.net/oauth2/token";
  const BACKEND_AUTHORIZE_URI=  "https://api.netatmo.net/oauth2/authorize";
  
  protected $conf = array();
  protected $refresh_token;
  protected $access_token;
  
  public static $CURL_OPTS = array(
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_HEADER         => TRUE,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_USERAGENT      => 'netatmoclient',
    CURLOPT_SSL_VERIFYPEER => TRUE,
    CURLOPT_HTTPHEADER     => array("Accept: application/json"),
  );
  
  public function getVariable($name, $default = NULL){
    return isset($this->conf[$name]) ? $this->conf[$name] : $default;
  }
  
  public function getRefreshToken(){
    return $this->refresh_token;
  }
  
  public function setVariable($name, $value){
    $this->conf[$name] = $value;
    return $this;
  }
  
  public function __construct($config = array()){
    if(isset($config["access_token"])){
      $this->access_token = $config["access_token"];
      unset($access_token);
    }
    if(isset($config["refresh_token"])){
      $this->refresh_token = $config["refresh_token"];
    }
    $uri = array("base_uri" => self::BACKEND_BASE_URI, "services_uri" => self::BACKEND_SERVICES_URI, "access_token_uri" => self::BACKEND_ACCESS_TOKEN_URI, "authorize_uri" => self::BACKEND_AUTHORIZE_URI);
    foreach($uri as $key => $val){
      if(isset($config[$key])){
        $this->setVariable($key, $config[$key]);
        unset($config[$key]);
      }else{
        $this->setVariable($key, $val);
      }
    }
    foreach ($config as $name => $value){
      $this->setVariable($name, $value);
    }
    if($this->getVariable("code") == null && isset($_GET["code"])){
      $this->setVariable("code", $_GET["code"]);
    }
  }
  
  public function makeRequest($path, $method = 'GET', $params = array()){
    $ch = curl_init();
    $opts = self::$CURL_OPTS;
    if ($params)  {
      switch ($method){
        case 'GET':
        $path .= '?' . http_build_query($params, NULL, '&');
        break;
        default:
        if ($this->getVariable('file_upload_support')){
          $opts[CURLOPT_POSTFIELDS] = $params;
        }else{
          $opts[CURLOPT_POSTFIELDS] = http_build_query($params, NULL, '&');
        }
        break;
      }
    }
    $opts[CURLOPT_URL] = $path;
    if (isset($opts[CURLOPT_HTTPHEADER]))  {
      $existing_headers = $opts[CURLOPT_HTTPHEADER];
      $existing_headers[] = 'Expect:';
      $ip = $this->getVariable("ip");
      if($ip)
      $existing_headers[] = 'CLIENT_IP: '.$ip;
      $opts[CURLOPT_HTTPHEADER] = $existing_headers;
    }else{
      $opts[CURLOPT_HTTPHEADER] = array('Expect:');
    }
    curl_setopt_array($ch, $opts);
    $result = curl_exec($ch);
    $errno = curl_errno($ch);
    if ($errno == 60 || $errno == 77) {
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
      $result = curl_exec($ch);
    }
    if ($result === FALSE)  {
      $e = new Exception(curl_errno($ch).' | '.curl_error($ch));
      curl_close($ch);
      throw $e;
    }
    curl_close($ch);
    list($headers, $body) = explode("\r\n\r\n", $result);
    $headers = explode("\r\n", $headers);
    if(strpos($headers[0], 'HTTP/1.1 2') !== FALSE){
      $decode = json_decode($body, TRUE);
      if(!$decode){
        if (preg_match('/^HTTP\/1.1 ([0-9]{3,3}) (.*)$/', $headers[0], $matches)){
          throw new Exception($matches[1].' | '. $matches[2]);
        }else {
          throw new Exception("OK");
        }
      }
      return $decode;
    }else  {
      if (!preg_match('/^HTTP\/1.1 ([0-9]{3,3}) (.*)$/', $headers[0], $matches)){
        $matches = array("", 400, "bad request");
      }
      $decode = json_decode($body, TRUE);
      if(!$decode){
        throw new Exception($body);
      }
      throw new Exception($body);
    }
  }
  
  public function getAccessToken(){
    if($this->access_token) return array("access_token" => $this->access_token);
    if($this->getVariable('code')){
      return $this->getAccessTokenFromAuthorizationCode($this->getVariable('code'));
    } else if($this->refresh_token) {
      return $this->getAccessTokenFromRefreshToken($this->refresh_token);
    }else if($this->getVariable('username') && $this->getVariable('password')) {
      return $this->getAccessTokenFromPassword($this->getVariable('username'), $this->getVariable('password'));
    }
    throw new Exception("No access token stored");
  }
  
  private function getAccessTokenFromRefreshToken(){
    if ($this->getVariable('access_token_uri') && ($client_id = $this->getVariable('client_id')) != NULL && ($client_secret = $this->getVariable('client_secret')) != NULL && ($refresh_token = $this->refresh_token) != NULL){
      if($this->getVariable('scope') != null){
        $ret = $this->makeRequest($this->getVariable('access_token_uri'),'POST',array(
          'grant_type' => 'refresh_token',
          'client_id' => $this->getVariable('client_id'),
          'client_secret' => $this->getVariable('client_secret'),
          'refresh_token' => $refresh_token,
          'scope' => $this->getVariable('scope'),
        ));
      } else {
        $ret = $this->makeRequest($this->getVariable('access_token_uri'),'POST',array(
          'grant_type' => 'refresh_token',
          'client_id' => $this->getVariable('client_id'),
          'client_secret' => $this->getVariable('client_secret'),
          'refresh_token' => $refresh_token,
        ));
      }
      $this->setTokens($ret);
      return $ret;
    }
    throw new Exception("missing args for getting refresh token grant");
  }
  
  private function getAccessTokenFromAuthorizationCode($code) {
    $redirect_uri = $this->getRedirectUri();
    $scope = $this->getVariable('scope');
    if($this->getVariable('access_token_uri') && ($client_id = $this->getVariable('client_id')) != NULL && ($client_secret = $this->getVariable('client_secret')) != NULL && $redirect_uri != NULL)  {
      $ret = $this->makeRequest($this->getVariable('access_token_uri'),'POST',array(
        'grant_type' => 'authorization_code',
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'code' => $code,
        'redirect_uri' => $redirect_uri,
        'scope' => $scope,
      ));
      $this->setTokens($ret);
      return $ret;
    }
    throw new Exception("missing args for getting authorization code grant");
  }
  
  private function setTokens($value){
    if(isset($value["access_token"])){
      $this->access_token = $value["access_token"];
      $update = true;
    }
    if(isset($value["refresh_token"])){
      $this->refresh_token = $value["refresh_token"];
      $update = true;
    }
    if(isset($update)) $this->updateSession();
  }
  
  private function updateSession(){
    $cb = $this->getVariable("func_cb");
    $object = $this->getVariable("object_cb");
    if($object && $cb){
      if(method_exists($object, $cb)){
        call_user_func_array(array($object, $cb), array(array("access_token"=> $this->access_token, "refresh_token" => $this->refresh_token)));
      }
    }else if($cb && is_callable($cb)){
      call_user_func_array($cb, array(array("access_token" => $this->access_token, "refresh_token" => $this->refresh_token)));
    }
  }
  
  private function getAccessTokenFromPassword($username, $password){
    $scope = $this->getVariable('scope');
    if ($this->getVariable('access_token_uri') && ($client_id = $this->getVariable('client_id')) != NULL && ($client_secret = $this->getVariable('client_secret')) != NULL){
      $ret = $this->makeRequest($this->getVariable('access_token_uri'),'POST',array(
        'grant_type' => 'password',
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'username' => $username,
        'password' => $password,
        'scope' => $scope,
      ));
      $this->setTokens($ret);
      return $ret;
    }
    throw new Exception("missing args for getting password grant");
  }
  
  protected function getRedirectUri()  {
    $redirect_uri = $this->getVariable("redirect_uri");
    if(!empty($redirect_uri)) return $redirect_uri;
    else return $this->getCurrentUri();
  }
  
  protected function getCurrentUri(){
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https://' : 'http://';
    $current_uri = $protocol . $_SERVER['HTTP_HOST'] . $this->getRequestUri();
    $parts = parse_url($current_uri);
    $query = '';
    if (!empty($parts['query'])) {
      $params = array();
      parse_str($parts['query'], $params);
      $params = array_filter($params);
      if (!empty($params)) {
        $query = '?' . http_build_query($params, NULL, '&');
      }
    }
    $port = isset($parts['port']) && ($protocol === 'https://' && $parts['port'] !== 443) ? ':' . $parts['port'] : '';
    return $protocol . $parts['host'] . $port . $parts['path'] . $query;
  }
  
  public function api($path, $method = 'GET', $params = array()){
    if($params == null){
      $params = array();
    }
    if (is_array($method) && empty($params)){
      $params = $method;
      $method = 'GET';
    }
    foreach ($params as $key => $value){
      if (!is_string($value)){
        $params[$key] = json_encode($value);
      }
    }
    $res = $this->makeOAuth2Request($this->getUri($path, array()), $method, $params);
    if(isset($res["body"])) return $res["body"];
    else return $res;
  }
  
  protected function makeOAuth2Request($path, $method = 'GET', $params = array(), $reget_token = true){
    $res = $this->getAccessToken();
    $params["access_token"] = $res["access_token"];
    try{
      $res = $this->makeRequest($path, $method, $params);
      return $res;
    }catch(NAApiErrorType $ex){
      if($reget_token == true)  {
        switch($ex->getCode()){
          case 2:
          case 3:
          if($this->refresh_token){
            try{
              $this->getAccessTokenFromRefreshToken();
            }catch(Exception $ex2){
              throw $ex;
            }
          }
          throw $ex;
          return $this->makeOAuth2Request($path, $method, $params, false);
          break;
          default:
          throw $ex;
        }
      }
      throw $ex;
    }
    return $res;
  }
  
  protected function getUri($path = '', $params = array()){
    $url = $this->getVariable('services_uri') ? $this->getVariable('services_uri') : $this->getVariable('base_uri');
    if(!empty($path)){
      if (substr($path, 0, 4) == "http"){
        $url = $path;
      }else if(substr($path, 0, 5) == "https"){
        $url = $path;
      }else{
        $url = rtrim($url, '/') . '/' . ltrim($path, '/');
      }
    }
    if (!empty($params)){
      $url .= '?' . http_build_query($params, NULL, '&');
    }
    return $url;
  }
  
}
