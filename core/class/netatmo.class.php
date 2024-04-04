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
if (!class_exists('netatmo_energy')) {
  require_once __DIR__ . '/netatmo_energy.class.php';
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
  
  public static function cron10(){
    if(config::byKey('mode', 'netatmo') != 'internal'){
      sleep(rand(0,120));
    }
    try {
      netatmo_weather::refresh();
    } catch (\Exception $e) {
      log::add('netatmo','debug','Weather : '.$e->getMessage());
    }
    try {
      netatmo::refreshClassNetatmo();
    } catch (\Exception $e) {
      log::add('netatmo','debug','Energy : '.$e->getMessage());
    }
  }
  
  public static function refreshClassNetatmo($homesdata = null){
    if($homesdata == null) {
      $homesdata = netatmo::request('/homesdata');
    }
    $home_ids = array();
    if(isset($homesdata['homes']) &&  count($homesdata['homes']) > 0){
      foreach ($homesdata['homes'] as $home) {
        if(!isset($home['modules'])){
          continue;
        }
        if(isset($home['modules']) &&  count($home['modules']) > 0){
          foreach ($home['modules'] as $module) {
             $moduleid = $module['id'];
             $ArrayAssocModuleIDtoRoom[$moduleid]= $module['room_id'];
          }
        }
        $home_ids[] = $home['id'];
        if(!isset($home['therm_mode'])){
          continue;
        }
        $eqLogic = eqLogic::byLogicalId($home['id'], 'netatmo');
        if(!is_object($eqLogic)){
          continue;
        }
       if($home['therm_mode'] != 'schedule'){
          $eqLogic->checkAndUpdateCmd('mode',$home['therm_mode']);
          continue;
        }
        if(isset($home['schedules']) &&  count($home['schedules']) > 0){
          $mode = '';
          foreach ($home['schedules'] as $schedule) {
            if(!$schedule['selected']){
              continue;
            }
            $mode .= $schedule['name'].',';
          }
          $eqLogic->checkAndUpdateCmd('mode',trim($mode,','));
        }
      }
    }
    if(count($home_ids) == 0){
      return;
    }
    foreach ($home_ids as $home_id) {
      $homestatus = netatmo::request('/homestatus',array('home_id' => $home_id));
      if(isset($homestatus['home']) && isset($homestatus['home']['modules']) &&  count($homestatus['home']['modules']) > 0){
          foreach ($homestatus['home']['modules'] as $module) {
            if ($module['type']=="OTM" || $module['type']=="NATherm1") {
              $eqLogic = eqLogic::byLogicalId($ArrayAssocModuleIDtoRoom[$module['id']], 'netatmo');
              if(is_object($eqLogic)){
                foreach ($eqLogic->getCmd('info') as $cmd) {
                  $logicalId = $cmd->getLogicalId();
                  if(isset($module[$logicalId]) && $cmd->getLogicalID()!="reachable"){
                    $eqLogic->checkAndUpdateCmd($cmd,$module[$logicalId]);
                  }
                }
               }
            }
            $eqLogic = eqLogic::byLogicalId($module['id'], 'netatmo');
               if(!is_object($eqLogic)){
                  continue;
               }
               foreach ($eqLogic->getCmd('info') as $cmd) {
                    $logicalId = $cmd->getLogicalId();
                    if($logicalId == 'state'){
                        $logicalId = 'status';
                    }
                    if(!isset($module[$logicalId])){
                      continue;
                    }
                    $eqLogic->checkAndUpdateCmd($cmd,$module[$logicalId]);
              }
          }
        }
      if(isset($homestatus['home']) && isset($homestatus['home']['rooms']) &&  count($homestatus['home']['rooms']) > 0){
        foreach ($homestatus['home']['rooms'] as $room) {
          $eqLogic = eqLogic::byLogicalId($room['id'], 'netatmo');
          if(!is_object($eqLogic)){
            continue;
          }
          foreach ($eqLogic->getCmd('info') as $cmd) {
            if(!isset($room[$cmd->getLogicalId()])){
              continue;
            }
            if($cmd->getLogicalId() == 'therm_setpoint_mode' && $room[$cmd->getLogicalId()] != 'schedule' && isset($room['therm_setpoint_end_time'])){
              $eqLogic->checkAndUpdateCmd($cmd,$room[$cmd->getLogicalId()].' ('.__('fini à',__FILE__).' '.date('H:i',$room['therm_setpoint_end_time']).')');
              continue;
            }
            $eqLogic->checkAndUpdateCmd($cmd,$room[$cmd->getLogicalId()]);
          }
        }
      }
     }    
  }
  
  public static function cronHourly(){
    if(config::byKey('mode', 'netatmo') != 'internal'){
      sleep(rand(0,120));
    }
    try {
      netatmo_security::refresh();
    } catch (\Exception $e) {
      log::add('netatmo','debug','Security : '.$e->getMessage());
    }
  }
  
  public static function request($_path,$_data = null,$_type='GET'){
    if(config::byKey('mode', 'netatmo') == 'internal'){
      return self::getClient()->api(trim($_path,'/'),$_type,$_data);
    }
    $url = config::byKey('service::cloud::url').'/service/netatmo';
    $url .='?path='.urlencode($_path);
    if($_data !== null && $_type == 'GET'){
      $url .='&options='.urlencode(json_encode($_data));
    }
    $request_http = new com_http($url);
    $request_http->setHeader(array(
      'Content-Type: application/json',
      'Autorization: '.sha512(mb_strtolower(config::byKey('market::username')).':'.config::byKey('market::password'))
    ));
    if($_type == 'POST'){
      log::add('netatmo','debug','[netatmo cloud] request : '.$_path);
      log::add('netatmo','debug','[netatmo cloud] request (POST json): '.json_encode($_data));
      $request_http->setPost(json_encode($_data));
    }
    else {
      if($_data !== null) log::add('netatmo','debug','[netatmo cloud] request : '.$_path.'?options='.json_encode($_data));
      else log::add('netatmo','debug','[netatmo cloud] request : '.$_path);
    }
    $return = json_decode($request_http->exec(30,1),true);
    log::add('netatmo','debug','[netatmo cloud] response : '.json_encode($return));
    $return = is_json($return,$return);
    if(isset($return['state']) && $return['state'] != 'ok'){
      throw new \Exception(__('Erreur lors de la requete à Netatmo : ',__FILE__).json_encode($return));
    }
    if(isset($return['error'])){
      throw new \Exception(__('Erreur lors de la requete à Netatmo : ',__FILE__).json_encode($return));
    }
    if(isset($return['body'])){
      $return_temp = $return['body'];
      if(isset($return_temp['errors'])){
        foreach ($return_temp['errors'] as $error) {
          $eqLogicError = eqLogic::byLogicalId($error[id], 'netatmo');
          if(!is_object($eqLogicError)){
            continue;
          }
          $error_desc[1] = "Unknown error";
          $error_desc[2] = "Internal error";
          $error_desc[3] = "Parser error";
          $error_desc[4] = "Command unknown node module error";
          $error_desc[5] = "Command invalid params";
          $error_desc[6] = "Unreachable";
          message::add('netatmo','L\'équipement '.$eqLogicError->getName().' est en erreur : '.$error_desc[$error[code]].'('.$error[code].')');
        }
      }
      return $return['body'];
    }
    return $return;
  }
  
  public static function sync(){
    netatmo_weather::sync();
    netatmo_security::sync();
    netatmo_energy::sync();
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
  
  public static function devicesParameters($_device = '') {
    $return = array();
    $files = ls(__DIR__.'/../config/devices', '*.json', false, array('files', 'quiet'));
    foreach ($files as $file) {
      try {
        $return[str_replace('.json','',$file)] = is_json(file_get_contents(__DIR__.'/../config/devices/'. $file),false);
      } catch (Exception $e) {
        
      }
    }
    if (isset($_device) && $_device != '') {
      if (isset($return[$_device])) {
        return $return[$_device];
      }
      return array();
    }
    return $return;
  }
  
  /*     * *********************Méthodes d'instance************************* */
  
  public function postSave() {
    if ($this->getConfiguration('applyDevice') != $this->getConfiguration('device')) {
      $this->applyModuleConfiguration();
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
  
  public function applyModuleConfiguration() {
    $this->setConfiguration('applyDevice', $this->getConfiguration('device'));
    $this->save();
    if ($this->getConfiguration('device') == '') {
      return true;
    }
    $device = self::devicesParameters($this->getConfiguration('device'));
    if (!is_array($device)) {
      return true;
    }
    $this->import($device,true);
  }
  
  public function getImage() {
    if(file_exists(__DIR__.'/../config/devices/'.  $this->getConfiguration('device').'.png')){
      return 'plugins/netatmo/core/config/devices/'.  $this->getConfiguration('device').'.png';
    }
    return 'plugins/netatmo/plugin_info/netatmo_icon.png';
  }
  
  /*     * **********************Getteur Setteur*************************** */
}

class netatmoCmd extends cmd {
  /*     * *************************Attributs****************************** */
  
  public function formatValueWidget($_value) {
    if(in_array($this->getLogicalId(),array('lastHuman','lastVehicle','lastAnimal','movement'))){
      return '<img style="display: block;max-width: 100%;height: auto;" src="'.$_value.'" />';
    }
    return $_value;
  }
  
  public function execute($_options = array()) {
    $eqLogic = $this->getEqLogic();
    if ($this->getLogicalId() == 'refresh') {
      if($eqLogic->getConfiguration('type') == 'weather'){
        netatmo_weather::refresh();
      }
      if($eqLogic->getConfiguration('type') == 'security'){
        netatmo_security::refresh();
      }
      if($eqLogic->getConfiguration('type') == 'energy'){
        netatmo::refreshClassNetatmo();
      }
      return;
    }
    if($eqLogic->getConfiguration('type') == 'security'){
      netatmo_security::execCmd($this,$_options);
    }
    if($eqLogic->getConfiguration('type') == 'energy'){
      netatmo_energy::execCmd($this,$_options);
    }
  }
  
  /*     * **********************Getteur Setteur*************************** */
}
