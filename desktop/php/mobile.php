<?php
ini_set('display_errors', 0);
if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
sendVarToJS('eqType', 'mobile');
$eqLogics = eqLogic::byType('mobile');
$plugins = plugin::listPlugin(true);
$plugin_compatible = mobile::Pluginsuported();
$plugin_widget = mobile::PluginWidget();
?>

<div class="row row-overflow">
    <div class="col-lg-2 col-md-3 col-sm-4">
        <div class="bs-sidebar">
            <ul id="ul_eqLogic" class="nav nav-list bs-sidenav">
                <a class="btn btn-default eqLogicAction" style="width : 100%;margin-top : 5px;margin-bottom: 5px;" data-action="add"><i class="fa fa-plus-circle"></i> {{Ajouter un mobile}}</a>
                <li class="filter" style="margin-bottom: 5px;"><input class="filter form-control input-sm" placeholder="{{Rechercher}}" style="width: 100%"/></li>
                <?php
foreach ($eqLogics as $eqLogic) {
	echo '<li class="cursor li_eqLogic" data-eqLogic_id="' . $eqLogic->getId() . '"><a>' . $eqLogic->getHumanName(true) . '</a></li>';
}
?>
           </ul>
       </div>
   </div>

   <div class="col-lg-10 col-md-9 col-sm-8 eqLogicThumbnailDisplay" style="border-left: solid 1px #EEE; padding-left: 25px;">
    <legend><i class="icon techno-listening3"></i>  {{Mes Téléphones Mobiles}}
    </legend>

    <div class="eqLogicThumbnailContainer">
      <div class="cursor eqLogicAction" data-action="add" style="background-color : #ffffff; height : 200px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;" >
         <center>
            <i class="fa fa-plus-circle" style="font-size : 7em;color:#94ca02;"></i>
        </center>
        <span style="font-size : 1.1em;position:relative; top : 23px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#94ca02"><center>Ajouter</center></span>
	</div>
	 <?php
				foreach ($eqLogics as $eqLogic) {
					$opacity = ($eqLogic->getIsEnable()) ? '' : jeedom::getConfiguration('eqLogic:style:noactive');
                    echo '<div class="eqLogicDisplayCard cursor" data-eqLogic_id="' . $eqLogic->getId() . '" style="background-color : #ffffff; height : 200px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;' . $opacity . '" >';
                    echo "<center>";
					$file = 'plugins/mobile/doc/images/' . $eqLogic->getConfiguration('type_mobile') . '.png';
                    if (file_exists($file)) {
                        $path = 'plugins/mobile/doc/images/' . $eqLogic->getConfiguration('type_mobile') . '.png';
						echo '<img src="'.$path.'" height="105" width="105" />';
                    } else {
                        $path = 'plugins/mobile/doc/images/mobile_icon.png';
						echo '<img src="'.$path.'" height="105" width="105" />';
                    }
                    echo "</center>";
                    echo '<span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;"><center>' . $eqLogic->getHumanName(true, true) . '</center></span>';
                    echo '</div>';
                }
    ?>
</div>
    <legend><i class="fa fa-check-circle-o"></i>  {{Le(s) Plugin(s) Compatible(s)}}
    </legend>
    <div class="eqLogicThumbnailContainer">
    	<?php
    	foreach ($plugins as $plugin){
    		if($plugin->getId() != 'mobile'){
    		if(in_array($plugin->getId(), $plugin_compatible)){
    		?>
    		<div class="cursor eqLogicAction" onclick="clickplugin('<?php echo $plugin->getId(); ?>','<?php echo $plugin->getName(); ?>')" style="background-color : #ffffff; height : 200px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;">
				<center>
				<?php
					if (file_exists(dirname(__FILE__) . '/../../../../' . $plugin->getPathImgIcon())) {
		echo '<img class="img-responsive" style="width : 120px;" src="' . $plugin->getPathImgIcon() . '" />';
		echo "</center>";
	} else {
		echo '<i class="' . $plugin->getIcon() . '" style="font-size : 6em;margin-top:20px;"></i>';
		echo "</center>";
		echo '<span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;"><center>' . $plugin->getName() . '</center></span>';
	}
	if(in_array($plugin->getId(), $plugin_widget)){
		echo '<span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;"><center>{{Plugin Spécial}}</center></span>';
	}else{
		echo '<span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;"><center>{{Via Type générique}}</center></span>';
	}
				?>
			</div>
			<?php
			}
			}
    	}
		?>
</div>
<legend><i class="fa fa-times-circle-o"></i>  {{Le(s) Plugin(s) Non Testé(s)}}
    </legend>
    </legend>
    <div class="eqLogicThumbnailContainer">
    	<?php
    	foreach ($plugins as $plugin){
    		if($plugin->getId() != 'mobile'){
    		if(!in_array($plugin->getId(), $plugin_compatible)){
    		?>
    		<div class="cursor eqLogicAction" style="background-color : #ffffff; height : 200px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;">
				<center>
				<?php
					if (file_exists(dirname(__FILE__) . '/../../../../' . $plugin->getPathImgIcon())) {
		echo '<img class="img-responsive" style="width : 120px;" src="' . $plugin->getPathImgIcon() . '" />';
		echo "</center>";
	} else {
		echo '<i class="' . $plugin->getIcon() . '" style="font-size : 6em;margin-top:20px;"></i>';
		echo "</center>";
		echo '<span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;"><center>' . $plugin->getName() . '</center></span>';
	}
	
	echo '<span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;"><center>{{Non transmis à l\'app}}</center></span>';
				?>
			</div>
			<?php
			}
			}
    	}
		?>
</div>
</div>

<div class="col-lg-10 col-md-9 col-sm-8 eqLogic" style="border-left: solid 1px #EEE; padding-left: 25px;display: none;">
    <div class="row">
        <div class="col-lg-6">
            <form class="form-horizontal">
                <fieldset>
                    <legend><i class="fa fa-arrow-circle-left eqLogicAction cursor" data-action="returnToThumbnailDisplay"></i> {{Général}}  <i class='fa fa-cogs eqLogicAction pull-right cursor expertModeVisible' data-action='configure'></i></legend>
                    <div class="form-group">
                        <label class="col-sm-3 control-label">{{Nom de l'équipement mobile}}</label>
                        <div class="col-sm-3">
                            <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
                            <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement template}}"/>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label" >{{Objet parent}}</label>
                        <div class="col-sm-3">
                            <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                                <option value="">{{Aucun}}</option>
                                <?php
foreach (object::all() as $object) {
	echo '<option value="' . $object->getId() . '">' . $object->getName() . '</option>';
}
?>
                           </select>
                       </div>
                   </div>
                   <div class="form-group">
                       <label class="col-sm-3 control-label"></label>
                       <div class="col-sm-8">
                           <input type="checkbox" class="eqLogicAttr bootstrapSwitch" data-label-text="{{Activer}}" data-l1key="isEnable" checked/>
                           <input type="checkbox" class="eqLogicAttr bootstrapSwitch" data-label-text="{{Visible}}" data-l1key="isVisible" checked/>
                       </div>
                   </div>
                   <div class="form-group">
                    <label class="col-sm-3 control-label">{{Type de Mobile}}</label>
                    <div class="col-sm-3">
                        <select class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="type_mobile">
                           <option value="ios">iPhone</option>
                           <option value="android">Android</option>
                           <!--<option value="windows">Windows</option>-->
                       </select>
                   </div>
               </div>
               <div class="form-group">
                    <label class="col-sm-3 control-label">{{Utilisateurs}}</label>
                    <div class="col-sm-3">
                        <select class="eqLogicAttr configuration form-control" data-l1key="configuration" data-l2key="affect_user">
                        <option value="">{{Aucun}}</option>
                          <?php
foreach (user::all() as $user) {
	echo '<option value="' . $user->getId() . '">' . ucfirst($user->getLogin()) . '</option>';
}
?>
                       </select>
                   </div>
               </div>
           </fieldset>
       </form>
   </div>
   <div class="col-lg-6">
    <form class="form-horizontal">
        <fieldset>
            <legend><i class="icon techno-listening3"></i>  {{Mobile}}</legend>
            <center>
               <div class="qrCodeImg"></div>
           </center>
       </div>
   </fieldset>
</form>
</div>
<div class="form-actions">
    <a class="btn btn-danger eqLogicAction" data-action="remove"><i class="fa fa-minus-circle"></i> {{Supprimer}}</a>
    <a class="btn btn-success eqLogicAction" data-action="save"><i class="fa fa-check-circle"></i> {{Sauvegarder}}</a>
</div>
</div>
</div>
<?php include_file('desktop', 'mobile', 'js', 'mobile');?>
<?php include_file('core', 'plugin.template', 'js');?>
