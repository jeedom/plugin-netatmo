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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
  include_file('desktop', '404', 'php');
  die();
}
?>
<form class="form-horizontal">
  <fieldset>
    <div class="form-group">
      <label class="col-sm-2 control-label">{{Mode}}</label>
      <div class="col-sm-3">
        <select class="form-control configKey" data-l1key="mode">
          <option value="jeedom">{{Cloud}}</option>
          <option value="internal">{{Standalone}}</option>
        </select>
      </div>
    </div>
    <div class="form-group netatmomode jeedom">
      <?php
      try {
        $info =	netatmo::serviceInfo();
        echo '<label class="col-sm-2 control-label">{{Abonnement service Netatmo}}</label>';
        echo '<div class="col-sm-9">';
        if(isset($info['limit']) && $info['limit'] != -1 && $info['limit'] != ''){
          echo '<div>{{Votre abonnement au service Netatmo fini le }}'.$info['limit'].'.';
          echo ' {{Pour le prolonger, allez}} <a href="https://www.jeedom.com/market/index.php?v=d&p=profils#services" target="_blank">{{ici}}</a>';
        }else if($info['limit'] == -1){
          echo '<div>{{Votre abonnement au service Netatmo est illimité.}}';
        }else{
          echo '<div class="alert alert-warning">{{Votre abonnement au service Netatmo est fini.}}';
          echo ' {{Pour vous réabonner, allez}} <a href="https://www.jeedom.com/market/index.php?v=d&p=profils#services" target="_blank">{{ici}}</a>';
        }
        echo '</div>';
        echo '</div>';
      } catch (\Exception $e) {
        echo '<div class="alert alert-danger">'.$e->getMessage().'</div>';
      }
      ?>
    </div>
    <div class="form-group netatmomode jeedom">
      <label class="col-lg-2 control-label">{{Association}}</label>
      <div class="col-lg-4">
        <a class="btn btn-success" href="<?php echo config::byKey('service::cloud::url').'/frontend/login.html?service=netatmo' ?>" target="__blank"><i class="fas fa-link"></i> {{Liée}}</a>
      </div>
    </div>
    <div class="form-group netatmomode jeedom">
      <label class="col-sm-2 control-label">{{Envoyer configuration au market}}</label>
      <div class="col-sm-2">
        <a class="btn btn-default" id="bt_sendConfigToMarket"><i class="fa fa-paper-plane" aria-hidden="true"></i> {{Envoyer}}</a>
      </div>
    </div>
    <div class="form-group netatmomode internal">
      <label class="col-sm-2 control-label">{{Client ID}}</label>
      <div class="col-sm-3">
        <input type="text" class="configKey form-control" data-l1key="client_id" placeholder="Client ID"/>
      </div>
    </div>
    <div class="form-group netatmomode internal">
      <label class="col-sm-2 control-label">{{Client secret}}</label>
      <div class="col-sm-3">
        <input type="text" class="configKey form-control" data-l1key="client_secret" placeholder="Client Secret"/>
      </div>
    </div>
    <div class="form-group netatmomode internal">
      <label class="col-sm-2 control-label">{{Nom d'utilisateur}}</label>
      <div class="col-sm-3">
        <input type="text" class="configKey form-control" data-l1key="username" placeholder="Nom d'utilisateur"/>
      </div>
    </div>
    <div class="form-group netatmomode internal">
      <label class="col-sm-2 control-label">{{Mot de passe}}</label>
      <div class="col-sm-3">
        <input type="password" class="configKey form-control" data-l1key="password" placeholder="Mot de passe"/>
      </div>
    </div>
    <div class="form-group">
      <label class="col-lg-2 control-label">{{Synchronisation}}</label>
      <div class="col-lg-4">
        <a class="btn btn-default" id="bt_syncWithNetatmo"><i class="fas fa-sync"></i> {{Synchroniser mes équipements}}</a>
      </div>
    </div>
  </fieldset>
</form>

<script>
$('.configKey[data-l1key=mode]').off('change').on('change',function(){
  $('.netatmomode').hide();
  $('.netatmomode.'+$(this).value()).show();
});
$('#bt_sendConfigToMarket').on('click', function () {
  $.ajax({
    type: "POST",
    url: "plugins/netatmo/core/ajax/netatmo.ajax.php",
    data: {
      action: "sendConfig",
    },
    dataType: 'json',
    error: function (request, status, error) {
      handleAjaxError(request, status, error);
    },
    success: function (data) {
      if (data.state != 'ok') {
        $('#div_alert').showAlert({message: data.result, level: 'danger'});
        return;
      }
      $('#div_alert').showAlert({message: '{{Configuration envoyée avec succès}}', level: 'success'});
    }
  });
});
$('#bt_syncWithNetatmo').on('click', function () {
  $.ajax({
    type: "POST",
    url: "plugins/netatmo/core/ajax/netatmo.ajax.php",
    data: {
      action: "sync",
    },
    dataType: 'json',
    error: function (request, status, error) {
      handleAjaxError(request, status, error);
    },
    success: function (data) {
      if (data.state != 'ok') {
        $('#div_alert').showAlert({message: data.result, level: 'danger'});
        return;
      }
      $('#div_alert').showAlert({message: '{{Synchronisation réussie}}', level: 'success'});
    }
  });
});
</script>
