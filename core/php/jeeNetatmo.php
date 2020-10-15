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

require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";
if(init('apikey') != ''){
  ob_end_clean();
  ignore_user_abort(true);
  ob_start();
  header('Content-Encoding: none');
  header("Content-Length: " . ob_get_length());
  header("Connection: close");
  ob_end_flush();
  @ob_flush();
  flush();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['apikey']) || !jeedom::apiAccess($data['apikey'], 'netatmo')) {
  if(init('apikey') == '' || !jeedom::apiAccess(init('apikey'), 'netatmo')){
    die();
  }
}
if (isset($data['data'])) {
  $data = $data['data'];
}
log::add('netatmo', 'debug','Webhook received : '. json_encode($data));
if(!isset($data['device_id'])){
  die();
}
$eqLogic = eqLogic::byLogicalId($data['device_id'], 'netatmo');
if(!is_object($eqLogic)){
  die();
}
if(isset($data['home_id'])){
  $home = eqLogic::byLogicalId($data['home_id'], 'netatmo');
}
switch ($data['event_type']) {
  case 'on':
  $eqLogic->checkAndUpdateCmd('monitoring',1);
  break;
  case 'off':
  $eqLogic->checkAndUpdateCmd('monitoring',0);
  break;
  case 'smoke':
  $eqLogic->checkAndUpdateCmd('smoke',$data['sub_type']);
  break;
  case 'outdoor':
  foreach ($data['event_list'] as $event) {
    switch ($event['event_type']) {
      case 'human':
      $eqLogic->checkAndUpdateCmd('lastHuman',$event['snapshot_url']);
      break;
      case 'vehicle':
      $eqLogic->checkAndUpdateCmd('lastVehicle',$event['snapshot_url']);
      break;
      case 'animal':
      $eqLogic->checkAndUpdateCmd('lastAnimal',$event['snapshot_url']);
      break;
    }
  }
  break;
  case 'human':
  $eqLogic->checkAndUpdateCmd('lastHuman',$data['snapshot_url']);
  break;
  case 'vehicle':
  $eqLogic->checkAndUpdateCmd('lastVehicle',$data['snapshot_url']);
  break;
  case 'animal':
  $eqLogic->checkAndUpdateCmd('lastAnimal',$data['snapshot_url']);
  break;
  case 'tag_big_move':
  $eqLogic->checkAndUpdateCmd('state',__('Mouvement',__FILE__));
  break;
  case 'tag_small_move':
  $eqLogic->checkAndUpdateCmd('state',__('Petit mouvement',__FILE__));
  break;
  case 'tag_uninstalled':
  $eqLogic->checkAndUpdateCmd('state',__('Enlevé',__FILE__));
  break;
  case 'tag_open':
  $eqLogic->checkAndUpdateCmd('state',__('Ouvert',__FILE__));
  break;
  case 'siren_sounding':
  $eqLogic->checkAndUpdateCmd('siren',$data['sub_type']);
  break;
  case 'movement':
  $eqLogic->checkAndUpdateCmd('movement',$data['snapshot_url']);
  break;
  case 'person':
  foreach ($data['persons'] as $person) {
    $home->checkAndUpdateCmd('isHere' . $person['id'], 1);
    $home->checkAndUpdateCmd('lastSeen' . $person['id'], date('Y-m-d H:i:s'));
  }
  break;
  case 'person_away':
  foreach ($data['persons'] as $person) {
    $home->checkAndUpdateCmd('isHere' . $person['id'], 0);
  }
  break;
}
