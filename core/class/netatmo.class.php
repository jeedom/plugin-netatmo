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
  
  /*     * ***********************Methode static*************************** */
  
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
    return json_decode($datas,true);
  }
  
  public static function getGConfig($_mode,$_key){
    $keys = explode('::',$_key);
    $return = json_decode(file_get_contents(__DIR__.'/../config/'.$_mode.'.json'),true);
    foreach ($keys as $key) {
      if(!isset($return[$key])){
        return '';
      }
      $return = $return[$key];
    }
    if(isset($return['body'])){
      return $return['body'];
    }
    return $return;
  }
  
  public static function sync(){
    netatmo_weather::sync();
    netatmo_security::sync();
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
    if(strpos($this->getLogicalId(),'monitoringOff') !== false){
      $request_http = new com_http($this->getCache('vpnUrl').'/command/changestatus?status=off');
      $request_http->exec(5, 1);
    }else if(strpos($this->getLogicalId(),'monitoringOn') !== false){
      $request_http = new com_http($this->getCache('vpnUrl').'/command/changestatus?status=on');
      $request_http->exec(5, 1);
    }else if(strpos($this->getLogicalId(),'light') !== false){
      $vpn = $eqLogic->getCache('vpnUrl');
      $command = '/command/floodlight_set_config?config=';
      if($this->getSubType() == 'slider'){
        $config = '{"mode":"on","intensity":"'.$_options['slider'].'"}';
      }else{
        if($this->getConfiguration('mode')=='on'){
          $config = '{"mode":"on","intensity":"100"}';
        }else if($this->getConfiguration('mode')=='auto'){
          $config = '{"mode":"auto"}';
        }else{
          $config = '{"mode":"off","intensity":"0"}';
        }
      }
      $request_http = new com_http($vpn.$command.urlencode($config));
      $request_http->exec(5, 1);
    }
  }
  
  /*     * **********************Getteur Setteur*************************** */
}
