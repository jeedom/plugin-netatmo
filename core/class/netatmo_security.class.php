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

class netatmo_security {
  /*     * *************************Attributs****************************** */
  
  /*     * ***********************Methode static*************************** */
  
  public static function sync(){
    $security = netatmo::request('/gethomedata');
    if(isset($security['homes']) &&  count($security['homes']) > 0){
      foreach ($security['homes'] as &$home) {
        $eqLogic = eqLogic::byLogicalId($home['id'], 'netatmo');
        if(!isset($home['name']) || trim($home['name']) == ''){
          $home['name'] = $home['id'];
        }
        if (!is_object($eqLogic)) {
          $eqLogic = new netatmo();
          $eqLogic->setEqType_name('netatmo');
          $eqLogic->setIsEnable(1);
          $eqLogic->setName($home['name']);
          $eqLogic->setCategory('security', 1);
          $eqLogic->setIsVisible(1);
        }
        $eqLogic->setConfiguration('type', 'NAHome');
        $eqLogic->setLogicalId($home['id']);
        $eqLogic->setConfiguration('mode','security');
        $eqLogic->save();
        foreach ($home['persons'] as $person) {
          if (!isset($person['pseudo']) || $person['pseudo'] == '') {
            continue;
          }
          $cmd = $eqLogic->getCmd('info', 'isHere' . $person['id']);
          if (!is_object($cmd)) {
            $cmd = new netatmoCmd();
            $cmd->setEqLogic_id($eqLogic->getId());
            $cmd->setLogicalId('isHere' . $person['id']);
            $cmd->setType('info');
            $cmd->setSubType('binary');
            $cmd->setName(substr(__('Présence', __FILE__) . ' ' . $person['pseudo'].' - '.$person['id'],0,44));
            $cmd->save();
          }
          $cmd = $eqLogic->getCmd('info', 'lastSeen' . $person['id']);
          if (!is_object($cmd)) {
            $cmd = new netatmoCmd();
            $cmd->setEqLogic_id($eqLogic->getId());
            $cmd->setLogicalId('lastSeen' . $person['id']);
            $cmd->setType('info');
            $cmd->setSubType('string');
            $cmd->setName(substr(__('Derniere fois', __FILE__) . ' ' . $person['pseudo'].' - '.$person['id'],0,44));
            $cmd->save();
          }
        }
        foreach ($home['cameras'] as &$camera) {
          $eqLogic = eqLogic::byLogicalId($camera['id'], 'netatmo');
          if(!isset($camera['name']) || trim($camera['name']) == ''){
            $camera['name'] = $camera['id'];
          }
          if (!is_object($eqLogic)) {
            $eqLogic = new netatmo();
            $eqLogic->setEqType_name('netatmo');
            $eqLogic->setIsEnable(1);
            $eqLogic->setName($camera['name']);
            $eqLogic->setCategory('security', 1);
            $eqLogic->setIsVisible(1);
          }
          $eqLogic->setConfiguration('mode','security');
          $eqLogic->setConfiguration('type', $camera['type']);
          $eqLogic->setLogicalId($camera['id']);
          $eqLogic->save();
          if(isset($camera['modules'])){
            foreach ($camera['modules'] as &$module) {
              $eqLogic = eqLogic::byLogicalId($module['id'], 'netatmo');
              if(!isset($module['name']) || trim($module['name']) == ''){
                $module['name'] = $module['id'];
              }
              if (!is_object($eqLogic)) {
                $eqLogic = new netatmo();
                $eqLogic->setEqType_name('netatmo');
                $eqLogic->setIsEnable(1);
                $eqLogic->setName($module['name']);
                $eqLogic->setCategory('security', 1);
                $eqLogic->setIsVisible(1);
              }
              $eqLogic->setConfiguration('mode','security');
              $eqLogic->setConfiguration('type', $module['type']);
              $eqLogic->setLogicalId($module['id']);
              $eqLogic->save();
              
            }
          }
        }
        foreach ($home['smokedetectors'] as &$smokedetectors) {
          $eqLogic = eqLogic::byLogicalId($smokedetectors['id'], 'netatmo');
          if(!isset($smokedetectors['name']) || trim($smokedetectors['name']) == ''){
            $smokedetectors['name'] = $smokedetectors['id'];
          }
          if (!is_object($eqLogic)) {
            $eqLogic = new netatmo();
            $eqLogic->setEqType_name('netatmo');
            $eqLogic->setIsEnable(1);
            $eqLogic->setName($smokedetectors['name']);
            $eqLogic->setCategory('security', 1);
            $eqLogic->setIsVisible(1);
          }
          $eqLogic->setConfiguration('mode','security');
          $eqLogic->setConfiguration('type', $smokedetectors['type']);
          $eqLogic->setLogicalId($smokedetectors['id']);
          $eqLogic->save();
        }
      }
      self::refresh($security);
    }
  }
  
  public static function createCamera($_datas = null) {
    if(!class_exists('camera')){
      return;
    }
    if($_datas == null){
      $security = self::request('/gethomedata');
    }else{
      $security = $data;
    }
    foreach ($security['homes'] as $home) {
      foreach ($home['cameras'] as $camera) {
        $eqLogic = eqLogic::byLogicalId($camera['id'], 'netatmo');
        if(!is_object($eqLogic)){
          continue;
        }
        log::add('netatmo','debug',json_encode($camera));
        $url_parse = parse_url($eqLogic->getCache('vpnUrl'). '/live/snapshot_720.jpg');
        log::add('netatmo','debug','VPN URL : '.json_encode($url_parse));
        if (!isset($url_parse['host']) || $url_parse['host'] == '') {
          continue;
        }
        $plugin = plugin::byId('camera');
        $camera_jeedom = eqLogic::byLogicalId($camera['id'], 'camera');
        if (!is_object($camera_jeedom)) {
          $camera_jeedom = new camera();
          $camera_jeedom->setIsEnable(1);
          $camera_jeedom->setIsVisible(1);
          $camera_jeedom->setName($camera['name']);
        }
        $camera_jeedom->setConfiguration('home_id',$home['id']);
        $camera_jeedom->setConfiguration('ip', $url_parse['host']);
        $camera_jeedom->setConfiguration('urlStream', $url_parse['path']);
        $camera_jeedom->setConfiguration('cameraStreamAccessUrl', 'http://#ip#'.str_replace('snapshot_720.jpg','index.m3u8',$url_parse['path']));
        if ($camera['type'] == 'NOC') {
          $camera_jeedom->setConfiguration('device', 'presence');
        } else {
          $camera_jeedom->setConfiguration('device', 'welcome');
        }
        $camera_jeedom->setEqType_name('camera');
        $camera_jeedom->setConfiguration('protocole', $url_parse['scheme']);
        if ($url_parse['scheme'] == 'https') {
          $camera_jeedom->setConfiguration('port', 443);
        } else {
          $camera_jeedom->setConfiguration('port', 80);
        }
        $camera_jeedom->setLogicalId($camera['id']);
        $camera_jeedom->save(true);
        if(is_object($eqLogic)){
          foreach ($eqLogic->getCmd('info') as $cmdEqLogic) {
            if(!in_array($cmdEqLogic->getLogicalId(),array('lastOneEvent','lastEvents'))){
              continue;
            }
            $cmd = $camera_jeedom->getCmd('info', $cmdEqLogic->getLogicalId());
            if (!is_object($cmd)) {
              $cmd = new CameraCmd();
              $cmd->setEqLogic_id($camera_jeedom->getId());
              $cmd->setLogicalId($cmdEqLogic->getLogicalId());
              $cmd->setType('info');
              $cmd->setSubType($cmdEqLogic->getSubType());
              $cmd->setName($cmdEqLogic->getName());
              $cmd->setIsVisible(0);
            }
            $cmd->save();
          }
        }
      }
    }
  }
  
  
  public static function refresh($_security = null) {
    if($_security == null){
      $security = netatmo::request('/gethomedata');
    }else{
      $security = $_security;
    }
    try {
      //  self::createCamera($_datas);
    } catch (\Exception $e) {
      
    }
    foreach ($security['homes'] as $home) {
      $eqLogic = eqLogic::byLogicalId($home['id'], 'netatmo');
      if (!is_object($eqLogic)) {
        continue;
      }
      foreach ($home['persons'] as $person) {
        $eqLogic->checkAndUpdateCmd('isHere' . $person['id'], ($person['out_of_sight'] != 1));
        $eqLogic->checkAndUpdateCmd('lastSeen' . $person['id'], date('Y-m-d H:i:s', $person['last_seen']));
      }
      $events = $home['events'];
      if ($events[0] != null && isset($events[0]['event_list'])) {
        $details = $events[0]['event_list'][0];
        $message = date('Y-m-d H:i:s', $details['time']) . ' - ' . $details['message'];
        $eqLogic->checkAndUpdateCmd('lastOneEvent', $message);
      }
      $message = '';
      $eventsByEqLogic = array();
      foreach ($events as $event) {
        if(isset($event['module_id'])){
          $eventsByEqLogic[$event['module_id']][] = $event;
        }else{
          $eventsByEqLogic[$event['device_id']][] = $event;
        }
        if (!isset($event['event_list']) || !isset($event['event_list'][0])) {
          continue;
        }
        $details = $event['event_list'][0];
        if(!isset($details['snapshot']['url'])){
          $details['snapshot']['url'] = '';
        }
        $message .= '<span title="" data-tooltip-content="<img height=\'500\' class=\'img-responsive\' src=\''.self::downloadSnapshot($details['snapshot']['url']).'\'/>">'.date('Y-m-d H:i:s', $details['time']) . ' - ' . $details['message'] . '</span><br/>';
      }
      $eqLogic->checkAndUpdateCmd('lastEvent', $message);
      foreach ($eventsByEqLogic as $id => $events) {
        $eqLogic = eqLogic::byLogicalId($id, 'netatmo');
        if(!is_object($eqLogic)){
          continue;
        }
        $message = '';
        foreach ($events as $event) {
          if(isset($event['message'])){
            $message .= $event['message'].'<br/>';
            continue;
          }
          if (!isset($event['event_list']) || !isset($event['event_list'][0])) {
            continue;
          }
          $details = $event['event_list'][0];
          if(!isset($details['snapshot']['url'])){
            $details['snapshot']['url'] = '';
          }
          $message .= '<span title="" data-tooltip-content="<img height=\'500\' class=\'img-responsive\' src=\''.self::downloadSnapshot($details['snapshot']['url']).'\'/>">'.date('Y-m-d H:i:s', $details['time']) . ' - ' . $details['message'] . '</span><br/>';
        }
        if($message != ''){
          $eqLogic->checkAndUpdateCmd('lastEvent',$message);
        }
      }
      foreach ($home['cameras'] as &$camera) {
        $eqLogic = eqLogic::byLogicalId($camera['id'], 'netatmo');
        $eqLogic->checkAndUpdateCmd('state', ($camera['status'] == 'on'));
        $eqLogic->checkAndUpdateCmd('stateSd', ($camera['sd_status'] == 'on'));
        $eqLogic->checkAndUpdateCmd('stateAlim', ($camera['alim_status'] == 'on'));
        if(!isset($camera['vpn_url']) || $camera['vpn_url'] == ''){
          continue;
        }
        if (!is_object($eqLogic)) {
          continue;
        }
        $url = $camera['vpn_url'];
        try {
          $request_http = new com_http($camera['vpn_url'] . '/command/ping');
          $result = json_decode(trim($request_http->exec(5, 1)), true);
          $eqLogic->setCache('vpnUrl',str_replace(',,','', $result['local_url']));
        } catch (Exception $e) {
          log::add('netatmo','debug','Local error : '.$e->getMessage());
        }
      }
      foreach ($home['cameras'] as &$camera) {
        if(isset($camera['modules'])){
          foreach ($camera['modules'] as $module) {
            $eqLogic = eqLogic::byLogicalId($module['id'], 'netatmo');
            if (!is_object($eqLogic)) {
              continue;
            }
            if($module['type'] == 'NACamDoorTag'){
              $eqLogic->checkAndUpdateCmd('state', ($module['status'] == 'open'));
            }else if($module['type'] == 'NIS'){
              $eqLogic->checkAndUpdateCmd('state', $module['status']);
              $eqLogic->checkAndUpdateCmd('alim', $module['alim_source']);
              $eqLogic->checkAndUpdateCmd('monitoring', $module['monitoring']);
            }
            if(isset($module['battery_percent'])){
              $eqLogic->batteryStatus($module['battery_percent']);
            }
          }
        }
      }
    }
  }
  
  public static function downloadSnapshot($_snapshot){
    if($_snapshot == ''){
      return 'core/img/no_image.gif';
    }
    if(!file_exists(__DIR__.'/../../data')){
      mkdir(__DIR__.'/../../data');
    }
    $parts  = parse_url($_snapshot);
    $filename = basename($parts['path']).'.jpg';
    if($filename == 'getcamerapicture'){
      return 'core/img/no_image.gif';
    }
    if(!file_exists(__DIR__.'/../../data/'.$filename)){
      file_put_contents(__DIR__.'/../../data/'.$filename,file_get_contents($_snapshot));
    }
    return 'plugins/netatmo/data/'.$filename;
  }
  
  
}
