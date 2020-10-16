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
  
  public static function sync(){
    $energy = netatmo::request('/homesdata');
    if(isset($energy['homes']) &&  count($energy['homes']) > 0){
      foreach ($energy['homes'] as $home) {
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
        $eqLogic->setConfiguration('device', 'NAHome');
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
        if(isset($home['room']) &&  count($home['room']) > 0){
          foreach ($home['room'] as $room) {
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
            $eqLogic->setConfiguration('device', 'NARoom');
            $eqLogic->setConfiguration('home_id', $home['id']);
            $eqLogic->save();
          }
        }
        
      }
    }
  }
  
  public static function refresh(){
    $home_ids = array();
    foreach (eqLogic::byType('netatmo') as $netatmo) {
      if($netatmo->getConfiguration('device') != 'NAHome'){
        continue;
      }
      $home_ids[] = $netatmo->getLogicalId();
    }
    if(count($home_ids) == 0){
      return;
    }
    foreach ($home_ids as $home_id) {
      $energy = netatmo::request('/homestatus',array('home_id' => $home_id));
      if(isset($energy['home']) && isset($energy['home']['rooms']) &&  count($energy['home']['rooms']) > 0){
        foreach ($energy['home']['rooms'] as $room) {
          $eqLogic = eqLogic::byLogicalId($room['id'], 'netatmo');
          foreach ($eqLogic->getCmd('info') as $cmd) {
            if(!isset($room[$cmd->getLogicalId()])){
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
        'room_id' => $eqLogic->logicalId(),
        'mode' => 'manual',
        'temp' => $_options['slider'],
      ),'POST');
    }else if($_cmd->getLogicalId() == 'mode_auto'){
      netatmo::request('/setroomthermpoint',array(
        'home_id' => $eqLogic->getConfiguration('home_id'),
        'room_id' => $eqLogic->logicalId(),
        'mode' => 'schedule',
      ),'POST');
    }else if($_cmd->getLogicalId() == 'home_mode_away'){
      netatmo::request('/setthermpoint',array(
        'home_id' => $eqLogic->getConfiguration('home_id'),
        'mode' => 'away',
        'endtime' => strotime('now +'.$_options['slider'].' hours')
      ),'POST');
    }else if($_cmd->getLogicalId() == 'home_mode_hg'){
      netatmo::request('/setthermpoint',array(
        'home_id' => $eqLogic->getConfiguration('home_id'),
        'mode' => 'hg',
        'endtime' => strotime('now +'.$_options['slider'].' hours')
      ),'POST');
    }else if(strpos($_cmd->getLogicalId(),'schedule') !== false){
      netatmo::request('/setthermpoint',array(
        'home_id' => $eqLogic->getConfiguration('home_id'),
        'mode' => 'schedule',
        'schedule_id' => str_replace('schedule','',$_cmd->getLogicalId())
      ),'POST');
    }
    sleep(2);
    self::sync();
  }
}
