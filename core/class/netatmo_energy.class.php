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
  
  public static function getRoomDevice($_modules,$_module_ids){
    $return  = '';
    foreach ($_modules as $module) {
      if(!in_array($module['id'],$_module_ids)){
        continue;
      }
      if($module['type'] == 'NRV'){
        return 'NRV';
      }else{
        $return = $module['type'];
      }
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
        foreach ($home['rooms'] as $room) {
          if(count($room['module_ids']) == 0){
            continue;
          }
          $device = self::getRoomDevice($home['modules'],$room['module_ids']);
          if(!in_array($device,array('NRV','NATherm1'))){
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
          $eqLogic->save();
        }
      }
    }
    self::refresh();
  }
  
  public static function refresh(){
    $homesdata = netatmo::request('/homesdata');
    $home_ids = array();
    if(isset($homesdata['homes']) &&  count($homesdata['homes']) > 0){
      foreach ($homesdata['homes'] as $home) {
        if(!isset($home['rooms'])){
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
          foreach ($home['schedules'] as $schedule) {
            if(!$schedule['selected']){
              continue;
            }
            $eqLogic->checkAndUpdateCmd('mode',$schedule['name']);
          }
        }
      }
    }
    if(count($home_ids) == 0){
      return;
    }
    foreach ($home_ids as $home_id) {
      $homestatus = netatmo::request('/homestatus',array('home_id' => $home_id));
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
      netatmo::request('/setroomthermpoint',array(
        'home_id' => $eqLogic->getConfiguration('home_id'),
        'room_id' => $eqLogic->getLogicalId(),
        'mode' => 'manual',
        'temp' => $_options['slider'],
      ),'POST');
    }else if($_cmd->getLogicalId() == 'mode_auto'){
      netatmo::request('/setroomthermpoint',array(
        'home_id' => $eqLogic->getConfiguration('home_id'),
        'room_id' => $eqLogic->getLogicalId(),
        'mode' => 'home',
      ),'POST');
    }else if($_cmd->getLogicalId() == 'home_mode_away'){
      netatmo::request('/setthermmode',array(
        'home_id' => $eqLogic->getConfiguration('home_id'),
        'mode' => 'away',
        'endtime' => strotime('now +'.$_options['slider'].' hours')
      ),'POST');
    }else if($_cmd->getLogicalId() == 'home_mode_hg'){
      netatmo::request('/setthermmode',array(
        'home_id' => $eqLogic->getConfiguration('home_id'),
        'mode' => 'hg',
        'endtime' => strotime('now +'.$_options['slider'].' hours')
      ),'POST');
    }else if(strpos($_cmd->getLogicalId(),'schedule') !== false){
      netatmo::request('/setthermmode',array(
        'home_id' => $eqLogic->getConfiguration('home_id'),
        'mode' => 'schedule',
        'schedule_id' => str_replace('schedule','',$_cmd->getLogicalId())
      ),'POST');
    }
    sleep(2);
    self::sync();
  }
}
