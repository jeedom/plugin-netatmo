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

class netatmo extends eqLogic {
  /*     * *************************Attributs****************************** */
  
  /*     * ***********************Methode static*************************** */
  
  public static function cron15(){
    sleep(rand(0,120));
    try {
      self::refresh_weather();
    } catch (\Exception $e) {
      
    }
  }
  
  public static function request($_path,$_data = null,$_type='GET'){
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
    return $return;
  }
  
  public static function sync(){
    log::add('netatmo','debug','Sync weather device');
    $weather = self::request('/getstationsdata');
    if(isset($weather['body']['devices']) &&  count($weather['body']['devices']) > 0){
      foreach ($weather['body']['devices'] as $device) {
        $eqLogic = eqLogic::byLogicalId($device['_id'], 'netatmo');
        if (isset($device['read_only']) && $device['read_only'] === true) {
          continue;
        }
        if (!is_object($eqLogic)) {
          $eqLogic = new netatmo();
          $eqLogic->setIsVisible(1);
          $eqLogic->setIsEnable(1);
          $eqLogic->setName($device['station_name']);
          $eqLogic->setCategory('heating', 1);
        }
        $eqLogic->setConfiguration('mode','weather');
        $eqLogic->setEqType_name('netatmo');
        $eqLogic->setLogicalId($device['_id']);
        $eqLogic->setConfiguration('type', $device['type']);
        $eqLogic->save();
        if(isset($device['modules']) &&  count($device['modules']) > 0){
          foreach ($device['modules'] as $module) {
            $eqLogic = eqLogic::byLogicalId($module['_id'], 'netatmo');
            if (!is_object($eqLogic)) {
              $eqLogic = new netatmo();
              $eqLogic->setName($module['module_name']);
              $eqLogic->setIsEnable(1);
              $eqLogic->setCategory('heating', 1);
              $eqLogic->setIsVisible(1);
            }
            $eqLogic->setConfiguration('mode','weather');
            $eqLogic->setConfiguration('battery_type', self::getGConfig('weather',$module['type'].'::bat_type'));
            $eqLogic->setEqType_name('netatmo');
            $eqLogic->setLogicalId($module['_id']);
            $eqLogic->setConfiguration('type', $module['type']);
            $eqLogic->save();
          }
        }
      }
      self::refresh_weather($weather);
    }
  }
  
  
  public static function refresh_weather($_weather = null) {
    if($_weather == null){
      $weather = self::request('/getstationsdata');
    }else{
      $weather = $_weather;
    }
    if(isset($weather['body']['devices']) &&  count($weather['body']['devices']) > 0){
      foreach ($weather['body']['devices'] as $device) {
        $eqLogic = eqLogic::byLogicalId($device["_id"], 'netatmo');
        if (!is_object($eqLogic)) {
          continue;
        }
        $eqLogic->setConfiguration('firmware', $device['firmware']);
        $eqLogic->setConfiguration('wifi_status', $device['wifi_status']);
        $eqLogic->save(true);
        if(isset($device['dashboard_data']) && count($device['dashboard_data']) > 0){
          foreach ($device['dashboard_data'] as $key => $value) {
            if ($key == 'max_temp') {
              $collectDate = date('Y-m-d H:i:s', $device['dashboard_data']['date_max_temp']);
            } else if ($key == 'min_temp') {
              $collectDate = date('Y-m-d H:i:s', $device['dashboard_data']['date_min_temp']);
            } else if ($key == 'max_wind_str') {
              $collectDate = date('Y-m-d H:i:s', $device['dashboard_data']['date_max_wind_str']);
            } else {
              $collectDate = date('Y-m-d H:i:s', $device['dashboard_data']['time_utc']);
            }
            $eqLogic->checkAndUpdateCmd(strtolower($key),$value,$collectDate);
          }
        }
        if(isset($device['modules']) &&  count($device['modules']) > 0){
          foreach ($device['modules'] as $module) {
            $eqLogic = eqLogic::byLogicalId($module["_id"], 'netatmo');
            if(!is_object($eqLogic)){
              continue;
            }
            $eqLogic->setConfiguration('rf_status', $module['rf_status']);
            $eqLogic->setConfiguration('firmware', $module['firmware']);
            $eqLogic->save(true);
            $eqLogic->batteryStatus(round(($module['battery_vp'] - self::getGConfig('weather',$module['type'].'::bat_min')) / (self::getGConfig('weather',$module['type'].'::bat_max') - self::getGConfig('weather',$module['type'].'::bat_min')) * 100, 0));
            foreach ($module['dashboard_data'] as $key => $value) {
              if ($key == 'max_temp') {
                $collectDate = date('Y-m-d H:i:s', $module['dashboard_data']['date_max_temp']);
              } else if ($key == 'min_temp') {
                $collectDate = date('Y-m-d H:i:s', $module['dashboard_data']['date_min_temp']);
              } else if ($key == 'max_wind_str') {
                $collectDate = date('Y-m-d H:i:s', $module['dashboard_data']['date_max_wind_str']);
              } else {
                $collectDate = date('Y-m-d H:i:s', $module['dashboard_data']['time_utc']);
              }
              $eqLogic->checkAndUpdateCmd(strtolower($key),$value,$collectDate);
            }
          }
        }
      }
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
      $cmd = new netatmoWeatherCmd();
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
  
  
  // Exécution d'une commande
  public function execute($_options = array()) {
    if ($this->getLogicalId() == 'refresh') {
      if($this->getEqLogic()->getConfiguration('mode') == 'weather'){
        netatmo::refresh_weather();
      }
      
    }
  }
  
  /*     * **********************Getteur Setteur*************************** */
}
