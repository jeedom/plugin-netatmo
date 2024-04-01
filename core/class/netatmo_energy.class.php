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

class netatmo_energy {
  
  public static function getRoomEnergyDevices($_modules,$_module_ids){
    foreach ($_modules as $module) {
      if(!in_array($module['id'],$_module_ids)){
        continue;
      }
      if(!in_array($module['type'],array('NRV','NATherm1','OTM'))){
        continue;
      }
      $return[] = ['type' => $module['type'], 'bridge' => $module['bridge']];
    }
    return $return;
  }
  
  public static function sync(){
    $homesdata = netatmo::request('/homesdata');
    if(isset($homesdata['homes']) &&  count($homesdata['homes']) > 0){
      foreach ($homesdata['homes'] as $home) {
        if(!isset($home['rooms']) || count($home['rooms']) == 0 || !isset($home['modules']) || count($home['modules']) == 0 || !isset($home['schedules'])){
          continue;
        }
        $eqLogic = eqLogic::byLogicalId($home['id'], 'netatmo');
        if (!is_object($eqLogic)) {
          $eqLogic = new netatmo();
          $eqLogic->setIsVisible(1);
          $eqLogic->setIsEnable(1);
          $eqLogic->setName($home['name']);
          $eqLogic->setCategory('heating', 1);
        }
        $eqLogic->setConfiguration('type','energy');
        $eqLogic->setEqType_name('netatmo');
        $eqLogic->setLogicalId($home['id']);
        $eqLogic->setConfiguration('home_id', $home['id']);
        $eqLogic->setConfiguration('device', 'NAEnergyHome');
        $eqLogic->save();
        if(isset($home['schedules']) &&  count($home['schedules']) > 0){
          foreach ($home['schedules'] as $schedule) {
            $cmd = $eqLogic->getCmd('action', 'schedule' . $schedule['id']);
            if (!is_object($cmd)) {
              $cmd = new netatmoCmd();
              $cmd->setEqLogic_id($eqLogic->getId());
              $cmd->setLogicalId('schedule' . $schedule['id']);
              $cmd->setType('action');
              $cmd->setSubType('other');
              $cmd->setName(__('Programmation',__FILE__).' '.$schedule['name']);
              $cmd->save();
            }
          }
        }

        $bridges = array();
        foreach ($home['modules'] as $module) {
          if(!in_array($module['type'],array('NAPlug','OTH'))){
          continue;
          }      
          $bridges[$module['id']] = $module['type'];
        }

        foreach ($home['rooms'] as $room) {
          if(count($room['module_ids']) == 0){
            continue;
          }
          $devices = self::getRoomEnergyDevices($home['modules'],$room['module_ids']);
          $room_devices_list = array();
          foreach ($devices as $device) {
            $room_devices_list[] = $device[type];
          }
          $room_devices_list = implode(", ",$room_devices_list);
          $eqLogic->setConfiguration('equipements',$devices_room_list);
          $device = $devices[0]['type'];
          
          if(!in_array($devices[0]['type'],array('NRV','NATherm1','OTM'))){
            continue;
          }
          $eqLogic = eqLogic::byLogicalId($room['id'], 'netatmo');
          if (!is_object($eqLogic)) {
            $eqLogic = new netatmo();
            $eqLogic->setIsVisible(1);
            $eqLogic->setIsEnable(1);
            $eqLogic->setName($room['name']);
            $eqLogic->setCategory('heating', 1);
          }
          $eqLogic->setConfiguration('type','energy');
          $eqLogic->setEqType_name('netatmo');
          $eqLogic->setLogicalId($room['id']);
          $eqLogic->setConfiguration('device', $device);
          $eqLogic->setConfiguration('home_id', $home['id']);
          $eqLogic->setConfiguration('devices-count', count($devices));
          $eqLogic->setConfiguration('bridge', $devices[0]['bridge']);
          $eqLogic->setConfiguration('bridge_type', $bridges[$devices[0]['bridge']]);
          $eqLogic->save();
        }
      }
    }
    self::refresh($homesdata);
  }
  
  public static function refresh($homesdata = null){
    if($homesdata == null) {
      $homesdata = netatmo::request('/homesdata');
    }
    $home_ids = array();
    if(isset($homesdata['homes']) &&  count($homesdata['homes']) > 0){
      foreach ($homesdata['homes'] as $home) {
        if(!isset($home['modules'])){
          continue;
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
  
  public static function execCmd($_cmd,$_options = array()){
    $eqLogic = $_cmd->getEqLogic();
    if($_cmd->getLogicalId() == 'setpoint'){
      if($eqLogic->getConfiguration('bridge_type') == 'OTH'){
        netatmo::request('/setstate',array(
          'home' => array(
            'id' => $eqLogic->getConfiguration('home_id'),
            'rooms' => array(
              array(
                'id' => $eqLogic->getLogicalId(),
                'therm_setpoint_mode' => 'manual',
                'therm_setpoint_temperature' => intval($_options['slider']),
              )
            )
          )
        ),'POST');
      }else{
        netatmo::request('/setroomthermpoint',array(
          'home_id' => $eqLogic->getConfiguration('home_id'),
          'room_id' => $eqLogic->getLogicalId(),
          'mode' => 'manual',
          'temp' => $_options['slider'],
        ),'POST');
      }
    }else if($_cmd->getLogicalId() == 'mode_auto'){
      if($eqLogic->getConfiguration('bridge_type') == 'OTH'){
        netatmo::request('/setstate',array(
          'home' => array(
            'id' => $eqLogic->getConfiguration('home_id'),
            'rooms' => array(
                array(
                'id' => $eqLogic->getLogicalId(),
                'therm_setpoint_mode' => 'home'
              )
            )
          )
        ),'POST');
      }else{
        netatmo::request('/setroomthermpoint',array(
          'home_id' => $eqLogic->getConfiguration('home_id'),
          'room_id' => $eqLogic->getLogicalId(),
          'mode' => 'home',
        ),'POST');
      }
    }else if($_cmd->getLogicalId() == 'mode_hg'){
      netatmo::request('/setstate',array(
        'home' => array(
          'id' => $eqLogic->getConfiguration('home_id'),
          'rooms' => array(
            array(
              'id' => $eqLogic->getLogicalId(),
              'therm_setpoint_mode' => 'hg'
            )
          )
        )
      ),'POST');     
    }else if($_cmd->getLogicalId() == 'mode_hg_endtime'){
      log::add('netatmo','debug','[netatmo energy] Mode HG');
      netatmo::request('/setstate',array(
        'home' => array(
          'id' => $eqLogic->getConfiguration('home_id'),
          'rooms' => array(
            array(
              'id' => $eqLogic->getLogicalId(),
              'therm_setpoint_mode' => 'hg',
              'therm_setpoint_end_time' => strtotime('now +'.$_options['slider'].' hours')
            )
          )
        )
      ),'POST');    
    }else if($_cmd->getLogicalId() == 'mode_off'){
      log::add('netatmo','debug','[netatmo energy] Mode OFF');
      netatmo::request('/setroomthermpoint',array(
        'home_id' => $eqLogic->getConfiguration('home_id'),
        'room_id' => $eqLogic->getLogicalId(),
        'mode' => 'off',
      ),'POST');
    }else if($_cmd->getLogicalId() == 'mode_off_endtime'){
      log::add('netatmo','debug','[netatmo energy] Mode OFF (heures)');
      netatmo::request('/setroomthermpoint',array(
        'home_id' => $eqLogic->getConfiguration('home_id'),
        'room_id' => $eqLogic->getLogicalId(),
        'mode' => 'off',
        'endtime' => strtotime('now +'.$_options['slider'].' hours')
      ),'POST');      
            
    }else if($_cmd->getLogicalId() == 'home_mode_away_endtime'){
      netatmo::request('/setthermmode',array(
        'home_id' => $eqLogic->getConfiguration('home_id'),
        'mode' => 'away',
        'endtime' => strtotime('now +'.$_options['slider'].' hours')
      ),'POST');
    }else if($_cmd->getLogicalId() == 'home_mode_hg_endtime'){
      netatmo::request('/setthermmode',array(
        'home_id' => $eqLogic->getConfiguration('home_id'),
        'mode' => 'hg',
        'endtime' => strtotime('now +'.$_options['slider'].' hours')
      ),'POST');
    }else if($_cmd->getLogicalId() == 'home_mode_schedule'){
      netatmo::request('/setthermmode',array(
        'home_id' => $eqLogic->getConfiguration('home_id'),
        'mode' => 'schedule'
      ),'POST');
    }else if($_cmd->getLogicalId() == 'home_mode_away'){
      netatmo::request('/setthermmode',array(
        'home_id' => $eqLogic->getConfiguration('home_id'),
        'mode' => 'away'
      ),'POST');
    }else if($_cmd->getLogicalId() == 'home_mode_hg'){
      netatmo::request('/setthermmode',array(
        'home_id' => $eqLogic->getConfiguration('home_id'),
        'mode' => 'hg'
      ),'POST');
    }else if(strpos($_cmd->getLogicalId(),'schedule') !== false){
      netatmo::request('/setthermmode',array(
        'home_id' => $eqLogic->getConfiguration('home_id'),
        'mode' => 'schedule',
        'schedule_id' => str_replace('schedule','',$_cmd->getLogicalId())
      ),'POST');
    }
    else {
      throw new \Exception('Erreur lors de l éxécution de la commande (commande '.$_cmd->getLogicalId().' inconnue)');
    }    
    sleep(10);
    self::refresh();
  }
}
