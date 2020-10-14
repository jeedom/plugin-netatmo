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
      <label class="col-lg-2 control-label">{{Association}}</label>
      <div class="col-lg-4">
        <a class="btn btn-success" href="<?php echo config::byKey('service::cloud::url').'/frontend/login.html?service=netatmo' ?>" target="__blank"><i class="fas fa-link"></i> {{Liée}}</a>
      </div>
    </div>
    <div class="form-group">
      <label class="col-lg-2 control-label">{{Synchronisation}}</label>
      <div class="col-lg-4">
        <a class="btn btn-success" id="bt_syncWithNetatmo"><i class="fas fa-sync"></i> {{Synchroniser mes équipements}}</a>
      </div>
    </div>
  </fieldset>
</form>

<script>
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
