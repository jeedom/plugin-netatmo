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
        $eqLogic->setConfiguration('type','security');
        $eqLogic->setLogicalId($home['id']);
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
          $eqLogic->setConfiguration('type','security');
          $eqLogic->setConfiguration('home_id',$home['id']);
          $eqLogic->setConfiguration('device', $camera['type']);
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
              $eqLogic->setConfiguration('type','security');
              $eqLogic->setConfiguration('home_id',$home['id']);
              $eqLogic->setConfiguration('device', $module['type']);
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
          $eqLogic->setConfiguration('type','security');
          $eqLogic->setConfiguration('home_id',$home['id']);
          $eqLogic->setConfiguration('device', $smokedetectors['type']);
          $eqLogic->setLogicalId($smokedetectors['id']);
          $eqLogic->save();
        }
      }
      self::refresh($security);
    }
  }
  
  public static function createCamera($_security = null) {
    if(!class_exists('camera')){
      return;
    }
    $security = ($_security == null) ? netatmo::request('/gethomedata') : $_security;
    foreach ($security['homes'] as $home) {
      foreach ($home['cameras'] as $camera) {
        $eqLogic = eqLogic::byLogicalId($camera['id'], 'netatmo');
        if(!is_object($eqLogic)){
          continue;
        }
        $url_parse = parse_url($eqLogic->getCache('vpnUrl'). '/live/snapshot_720.jpg');
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
      }
    }
  }
  
  
  public static function refresh($_security = null) {
    $security = ($_security == null) ? netatmo::request('/gethomedata') : $_security;
    try {
      self::createCamera($security);
    } catch (\Exception $e) {
      
    }
    foreach ($security['homes'] as $home) {
      $home_eqLogic = eqLogic::byLogicalId($home['id'], 'netatmo');
      if (!is_object($home_eqLogic)) {
        continue;
      }
      foreach ($home['persons'] as $person) {
        $home_eqLogic->checkAndUpdateCmd('isHere' . $person['id'], ($person['out_of_sight'] != 1));
        $home_eqLogic->checkAndUpdateCmd('lastSeen' . $person['id'], date('Y-m-d H:i:s', $person['last_seen']));
      }
      foreach ($home['cameras'] as $camera) {
        $camera_eqLogic = eqLogic::byLogicalId($camera['id'], 'netatmo');
        if (!is_object($camera_eqLogic)) {
          continue;
        }
        if(!isset($camera['vpn_url']) || $camera['vpn_url'] == ''){
          continue;
        }
        try {
          $request_http = new com_http($camera['vpn_url'] . '/command/ping');
          $camera_eqLogic->setCache('vpnUrl',str_replace(',,','', json_decode(trim($request_http->exec(5, 1)), true)['local_url']));
        } catch (Exception $e) {
          log::add('netatmo','debug','Local error : '.$e->getMessage());
        }
        if(isset($camera['modules'])){
          foreach ($camera['modules'] as $module) {
            $module_eqLogic = eqLogic::byLogicalId($module['id'], 'netatmo');
            if (!is_object($module_eqLogic)) {
              continue;
            }
            if(isset($module['battery_percent'])){
              $module_eqLogic->batteryStatus($module['battery_percent']);
            }
          }
        }
      }
    }
  }
  
  public static function execCmd($_cmd,$_options = array()){
    $eqLogic = $_cmd->getEqLogic();
    if($_cmd->getLogicalId() == 'monitoringOff'){
      $request_http = new com_http($eqLogic->getCache('vpnUrl').'/command/changestatus?status=off');
      $request_http->exec(5, 1);
    }else if($_cmd->getLogicalId() == 'monitoringOn'){
      $request_http = new com_http($eqLogic->getCache('vpnUrl').'/command/changestatus?status=on');
      $request_http->exec(5, 1);
    }else if($_cmd->getLogicalId() == 'lighton'){
      $request_http = new com_http($eqLogic->getCache('vpnUrl').'/command/floodlight_set_config?config='.urlencode('{"mode":"on","intensity":"100"}'));
      $request_http->exec(5, 1);
    }else if($_cmd->getLogicalId() == 'lightoff'){
      $request_http = new com_http($eqLogic->getCache('vpnUrl').'/command/floodlight_set_config?config='.urlencode('{"mode":"off","intensity":"0"}'));
      $request_http->exec(5, 1);
    }else if($_cmd->getLogicalId() == 'lightintensity'){
      $request_http = new com_http($eqLogic->getCache('vpnUrl').'/command/floodlight_set_config?config='.urlencode('{"mode":"on","intensity":"'.$_options['slider'].'"}'));
      $request_http->exec(5, 1);
    }else if($_cmd->getLogicalId() == 'lightauto')){
      $request_http = new com_http($eqLogic->getCache('vpnUrl').'/command/floodlight_set_config?config='.urlencode('{"mode":"auto"}'));
      $request_http->exec(5, 1);
    }
  }
}
