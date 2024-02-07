<?php
if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
$plugin = plugin::byId('netatmo');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
?>

<div class="row row-overflow">
	<div class="col-xs-12 eqLogicThumbnailDisplay">
		<legend><i class="fas fa-cog"></i> {{Gestion}}</legend>
		<div class="eqLogicThumbnailContainer">
			<div class="cursor logoSecondary" id="bt_syncEqLogicNetatmo">
				<i class="fas fa-sync-alt"></i>
				<br>
				<span>{{Synchronisation}}</span>
			</div>
			<div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
				<i class="fas fa-wrench"></i>
				<br>
				<span>{{Configuration}}</span>
			</div>
			<div class="cursor logoSecondary" id="bt_healthNetatmo">
				<i class="fas fa-medkit"></i>
				<br />
				<span>{{Santé}}</span>
			</div>
			<?php
			$jeedomVersion  = jeedom::version() ?? '0';
			$displayInfo = version_compare($jeedomVersion, '4.4.0', '>=');
			if ($displayInfo) {
				echo '<div class="cursor eqLogicAction info" data-action="createCommunityPost">';
				echo '<i class="fas fa-ambulance"></i><br>';
				echo '<span>{{Community}}</span>';
				echo '</div>';
			}
			?>
		</div>
		<legend><i class="fas fa-table"></i> {{Mes équipements Netatmo}}</legend>
		<div class="input-group" style="margin:5px;">
			<input class="form-control roundedLeft" placeholder="{{Rechercher}}" id="in_searchEqlogic" />
			<div class="input-group-btn">
				<a id="bt_resetSearch" class="btn" style="width:30px"><i class="fas fa-times"></i>
				</a><a class="btn hidden roundedRight" id="bt_pluginDisplayAsTable" data-coreSupport="1" data-state="0"><i class="fas fa-grip-lines"></i></a>
			</div>
		</div>
		<div class="eqLogicThumbnailContainer">
			<?php
			// Affiche la liste des équipements
			foreach ($eqLogics as $eqLogic) {
				$opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
				echo '<div class="eqLogicDisplayCard cursor ' . $opacity . '" data-eqLogic_id="' . $eqLogic->getId() . '">';
				if ($eqLogic->getImage() !== false) {
					echo '<img src="' . $eqLogic->getImage() . '"/>';
				} else {
					echo '<img src="' . $plugin->getPathImgIcon() . '"/>';
				}
				echo '<br>';
				echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
				echo '<span class="hidden hiddenAsCard displayTableRight">';
				echo ($eqLogic->getIsVisible() == 1) ? '<i class="fas fa-eye" title="{{Équipement visible}}"></i>' : '<i class="fas fa-eye-slash" title="{{Équipement non visible}}"></i>';
				echo '</span>';
				echo '</div>';
			}
			?>
		</div>
	</div>

	<div class="col-xs-12 eqLogic" style="display: none;">
		<div class="input-group pull-right" style="display:inline-flex">
			<span class="input-group-btn">
				<a class="btn btn-sm btn-default eqLogicAction roundedLeft" data-action="configure"><i class="fas fa-cogs"></i> {{Configuration avancée}}
				</a><a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}
				</a><a class="btn btn-sm btn-danger eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}
				</a>
			</span>
		</div>
		<ul class="nav nav-tabs" role="tablist">
			<li role="presentation"><a class="eqLogicAction cursor" aria-controls="home" role="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
			<li role="presentation" class="active"><a href="#eqlogictabin" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Equipement}}</a></li>
			<li role="presentation"><a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-list"></i> {{Commandes}}</a></li>
		</ul>
		<div class="tab-content">
			<div role="tabpanel" class="tab-pane active" id="eqlogictabin">
				<form class="form-horizontal">
					<fieldset>
						<div class="col-lg-6">
							<legend><i class="fas fa-wrench"></i> {{Paramètres généraux}}</legend>
							<div class="form-group">
								<label class="col-sm-4 control-label">{{Nom de l'équipement}}</label>
								<div class="col-sm-6">
									<input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display:none;">
									<input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement}}">
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-4 control-label">{{Objet parent}}</label>
								<div class="col-sm-6">
									<select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
										<option value="">{{Aucun}}</option>
										<?php
										$options = '';
										foreach ((jeeObject::buildTree(null, false)) as $object) {
											$options .= '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
										}
										echo $options;
										?>
									</select>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-4 control-label">{{Catégorie}}</label>
								<div class="col-sm-6">
									<?php
									foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
										echo '<label class="checkbox-inline">';
										echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" >' . $value['name'];
										echo '</label>';
									}
									?>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-4 control-label">{{Options}}</label>
								<div class="col-sm-6">
									<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked>{{Activer}}</label>
									<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked>{{Visible}}</label>
								</div>
							</div>
							<legend><i class="fas fa-cogs"></i> {{Paramètres spécifiques}}</legend>
							<div class="form-group">
								<label class="col-sm-4 control-label">{{Type}}</label>
								<div class="col-sm-6">
									<select disabled class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="device" disabled>
										<option value="NAMain">{{Station}}</option>
										<option value="NAModule1">{{Module extérieur}}</option>
										<option value="NAModule4">{{Module intérieur}}</option>
										<option value="NAModule3">{{Module pluie}}</option>
										<option value="NAModule2">{{Anémomètre}}</option>
										<option value="NACamera">{{Welcome}}</option>
										<option value="NOC">{{Présence}}</option>
										<option value="NSD">{{Détecteur de fumée}}</option>
										<option value="NIS">{{Sirène}}</option>
										<option value="NACamDoorTag">{{Capteur de porte}}</option>
										<option value="NAEnergyHome">{{Maison}}</option>
										<option value="NASecurityHome">{{Maison}}</option>
										<option value="NATherm1">{{Thermostat}}</option>
										<option value="OTM">{{Thermostat Opentherm}}</option>
										<option value="NRV">{{Vanne}}</option>
									</select>
								</div>
							</div>
						</div>
						<div class="col-lg-6">
							<legend><i class="fas fa-info"></i> {{Informations}}</legend>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Identifiant}}</label>
								<div class="col-sm-7">
									<span class="eqLogicAttr tooltips label label-default" data-l1key="logicalId" style="font-size : 1em"></span>
								</div>
							</div>                   
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Firmware}}</label>
								<div class="col-sm-7">
									<span class="eqLogicAttr tooltips label label-default" data-l1key="configuration" data-l2key="firmware" style="font-size : 1em"></span>
								</div>
							</div>                                
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Maison}}</label>
								<div class="col-sm-7">
									<span class="eqLogicAttr tooltips label label-default" data-l1key="configuration" data-l2key="home_id" style="font-size : 1em"></span>
								</div>
							</div>    

							<div class="form-group">
								<label class="col-sm-3 control-label">{{Bridge type}}</label>
								<div class="col-sm-7">
									<span class="eqLogicAttr tooltips label label-default" data-l1key="configuration" data-l2key="bridge_type" style="font-size : 1em"></span>
								</div>
							</div>    

							<div class="form-group">
								<label class="col-sm-3 control-label">{{Bridge}}</label>
								<div class="col-sm-7">
									<span class="eqLogicAttr tooltips label label-default" data-l1key="configuration" data-l2key="bridge" style="font-size : 1em"></span>
								</div>
							</div>  
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Nombre d'équipement}}</label>
								<div class="col-sm-7">
									<span class="eqLogicAttr tooltips label label-default" data-l1key="configuration" data-l2key="devices-count" style="font-size : 1em"></span>
								</div>
							</div>    
							
							<br>
							<div>
								<center>
									<img src="<?php echo $plugin->getPathImgIcon(); ?>" id="img_netatmoModel" style="width:120px" />
								</center>
							</div>
					</fieldset>
				</form>
			</div>
			<div role="tabpanel" class="tab-pane" id="commandtab">
				<a class="btn btn-success btn-sm cmdAction pull-right" data-action="add" style="margin-top:5px;"><i class="fas fa-plus-circle"></i> {{Commandes}}</a>
				<br /><br />
				<div class="table-responsive">
					<table id="table_cmd" class="table table-bordered table-condensed">
						<thead>
							<tr>
								<th class="hidden-xs" style="min-width:50px;width:70px;">ID</th>
								<th style="width:450px;">{{Nom}}</th>
								<th style="width:250px;">{{Type}}</th>
								<th style="width:300px;">{{Options}}</th>
								<th>{{Valeur}}</th>
								<th style="min-width:80px;width:100px;">{{Actions}}</th>
							</tr>
						</thead>
						<tbody>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Inclusion du fichier javascript du plugin (dossier, nom_du_fichier, extension_du_fichier, nom_du_plugin) -->
<?php include_file('desktop', 'netatmo', 'js', 'netatmo'); ?>
<!-- Inclusion du fichier javascript du core - NE PAS MODIFIER NI SUPPRIMER -->
<?php include_file('core', 'plugin.template', 'js'); ?>
