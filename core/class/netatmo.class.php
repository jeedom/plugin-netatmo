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
require_once __DIR__  . '/../../../../core/php/core.inc.php';
if (!class_exists('netatmo_standalone_api')) {
  require_once __DIR__ . '/netatmo_standalone_api.class.php';
}
if (!class_exists('netatmo_weather')) {
  require_once __DIR__ . '/netatmo_weather.class.php';
}
if (!class_exists('netatmo_security')) {
  require_once __DIR__ . '/netatmo_security.class.php';
}

class netatmo extends eqLogic {
  /*     * *************************Attributs****************************** */
  
  private static $_client = null;
  private static $_globalConfig = array();
  
  /*     * ***********************Methode static*************************** */
  
  public static function sendJeedomConfig() {
    $market = repo_market::getJsonRpc();
    if (!$market->sendRequest('netatmo::config', array('netatmo::apikey' => jeedom::getApiKey('netatmo'),'netatmo::url' => network::getNetworkAccess('external')))) {
      throw new Exception($market->getError(), $market->getErrorCode());
    }
  }
  
  public static function serviceInfo() {
    $market = repo_market::getJsonRpc();
    if (!$market->sendRequest('netatmo::serviceInfo')) {
      throw new Exception($market->getError(), $market->getErrorCode());
    }
    return $market->getResult();
  }
  
  public static function getClient() {
    if (self::$_client == null) {
      self::$_client = new netatmo_standalone_api(array(
        'client_id' => config::byKey('client_id', 'netatmo'),
        'client_secret' => config::byKey('client_secret', 'netatmo'),
        'username' => config::byKey('username', 'netatmo'),
        'password' => config::byKey('password', 'netatmo'),
        'scope' => 'read_station read_camera access_camera read_presence access_presence read_smokedetector',
      ));
    }
    return self::$_client;
  }
  
  public static function cron15(){
    sleep(rand(0,120));
    try {
      netatmo_weather::refresh();
    } catch (\Exception $e) {
      
    }
    try {
      netatmo_security::refresh();
    } catch (\Exception $e) {
      
    }
  }
  
  public static function request($_path,$_data = null,$_type='GET'){
    if(config::byKey('mode', 'netatmo') == 'internal'){
      return self::getClient()->api(trim($_path,'/'));
    }
    $url = config::byKey('service::cloud::url').'/service/netatmo';
    $url .='?path='.urlencode($_path);
    if($_data !== null && $_type == 'GET'){
      $url .='&options='.urlencode(json_encode($_data));
    }
    $request_http = new com_http($url);
    $request_http->setHeader(array(
      'Autorization: '.sha512(mb_strtolower(config::byKey('market::username')).':'.config::byKey('market::password'))
    ));
    $datas = json_decode($request_http->exec(30,1),true);
    if(isset($datas['state']) && $datas['state'] != 'ok'){
      throw new \Exception(__('Erreur sur la récuperation des données : ',__FILE__).json_encode($datas));
    }
    $return = json_decode($datas,true);
    if(isset($return['body'])){
      return $return['body'];
    }
    return $return;
  }
  
  public static function getGConfig($_mode,$_key){
    $keys = explode('::',$_key);
    if(!isset(self::$_globalConfig[$_mode])){
      self::$_globalConfig[$_mode] = json_decode(file_get_contents(__DIR__.'/../config/config.json'),true);
    }
    $return = self::$_globalConfig[$_mode];
    foreach ($keys as $key) {
      if(!isset($return[$key])){
        return '';
      }
      $return = $return[$key];
    }
    return $return;
  }
  
  public static function sync(){
    netatmo_weather::sync();
    netatmo_security::sync();
    self::setWebhook();
  }
  
  public static function setWebhook(){
    if(config::byKey('mode', 'netatmo') != 'internal'){
      return;
    }
    try {
      self::getClient()->api('dropwebhook','POST',array('app_types' => 'jeedom'));
    } catch (\Exception $e) {
      log::add('netatmo','debug','Webhook drop error : '.print_r($e,true));
    }
    try {
      self::getClient()->api('addwebhook','POST',array('url' => network::getNetworkAccess('external') . '/plugins/netatmo/core/php/jeeNetatmo.php?apikey=' . jeedom::getApiKey('netatmo')));
    } catch (\Exception $e) {
      log::add('netatmo','debug','Webhook add error : '.print_r($e,true));
    }
  }
  
  public function getImage() {
    if(file_exists(__DIR__.'/../img/'.  $this->getConfiguration('type').'.png')){
      return 'plugins/netatmo/core/img/'.  $this->getConfiguration('type').'.png';
    }
    return false;
  }
  
  /*     * *********************Méthodes d'instance************************* */
  
  public function postSave() {
    if ($this->getConfiguration('applyType') != $this->getConfiguration('type')) {
      $this->applyType();
    }
    $cmd = $this->getCmd(null, 'refresh');
    if (!is_object($cmd)) {
      $cmd = new netatmoCmd();
      $cmd->setName(__('Rafraichir', __FILE__));
    }
    $cmd->setEqLogic_id($this->getId());
    $cmd->setLogicalId('refresh');
    $cmd->setType('action');
    $cmd->setSubType('other');
    $cmd->save();
  }
  
  public function applyType(){
    $this->setConfiguration('applyType', $this->getConfiguration('type'));
    $supported_commands = self::getGConfig($this->getConfiguration('mode'),$this->getConfiguration('type').'::cmd');
    $commands = array('commands');
    foreach ($supported_commands as $supported_command) {
      $commands['commands'][] = self::getGConfig($this->getConfiguration('mode'),'commands::'.$supported_command);
    }
    $this->import($commands);
  }
  
  
  /*     * **********************Getteur Setteur*************************** */
}

class netatmoCmd extends cmd {
  /*     * *************************Attributs****************************** */
  
  public function execute($_options = array()) {
    $eqLogic = $this->getEqLogic();
    if ($this->getLogicalId() == 'refresh') {
      if($eqLogic->getConfiguration('mode') == 'weather'){
        netatmo_weather::refresh();
      }
      if($eqLogic->getConfiguration('mode') == 'security'){
        netatmo_security::refresh();
      }
    }
    if($eqLogic->getConfiguration('mode') == 'security'){
      netatmo_security::execCmd($this);
    }
    
  }
  
  /*     * **********************Getteur Setteur*************************** */
}
