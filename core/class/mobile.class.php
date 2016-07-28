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
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class mobile extends eqLogic {
	/*     * *************************Attributs****************************** */

	//private static $_PLUGIN_COMPATIBILITY = array('openzwave', 'rfxcom', 'edisio', 'ipx800', 'mySensors', 'Zibasedom', 'virtual', 'camera','apcupsd', 'btsniffer', 'dsc', 'h801', 'rflink', 'mysensors', 'relaynet', 'remora', 'unipi', 'playbulb', 'doorbird','netatmoThermostat');

	/*     * ***********************Methode static*************************** */

	public static function Pluginsuported() {
		
		$Pluginsuported = ['openzwave','rfxcom','edisio','mpower', 'ipx800', 'mySensors', 'Zibasedom', 'virtual', 'camera','weather','philipsHue','enocean','wifipower','alarm','mode','apcupsd', 'btsniffer','dsc','rflink','mysensors','relaynet','remora','unipi','eibd','ipx800','ipx800v2','boxio','thermostat','netatmoThermostat','espeasy'];
		
		return $Pluginsuported;
		
	}
	
	public static function PluginWidget() {
		$PluginWidget = ['alarm','camera','thermostat','netatmoThermostat','weather'];	
		return $PluginWidget;
	}

	/**************************************************************************************/
	/*                                                                                    */
	/*                        Permet d'installer les dépendances                          */
	/*                                                                                    */
	/**************************************************************************************/
	public static function check_ios() {
		$ios = 0;
		foreach (eqLogic::byType('mobile') as $mobile){
			if($mobile->getConfiguration('type_mobile') == "ios"){
				$ios = 1;
			}
		}
		return $ios;
	}
	
	public static function dependancy_info() {
		$return = array();
		$return['log'] = 'homebridge_update';
		$return['progress_file'] = '/tmp/homebridge_in_progress';
		$state = '';
		$no_ios = 1;
		foreach (eqLogic::byType('mobile') as $mobile){
			if($mobile->getConfiguration('type_mobile') == "ios"){
				$no_ios = 0;
				if (shell_exec('ls /usr/bin/homebridge 2>/dev/null | wc -l') == 1 || shell_exec('ls /usr/local/bin/homebridge 2>/dev/null | wc -l') == 1) {
					$state = 'ok';
				}else{
					$state = 'nok';
				}
			}
		}
		if($no_ios == 1){
			$state = 'ok';
		}
		$return['state'] = $state;
		return $return;
	}
	
	public static function dependancy_install() {
		if (file_exists('/tmp/homebridge_in_progress')) {
			return;
		}
		if(self::check_ios() == 0){
			config::save('deamonAutoMode',0,'mobile');
			return;
		}
		
		log::remove('mobile_homebridge_update');
		$cmd = 'sudo /bin/bash ' . dirname(__FILE__) . '/../../resources/install_homebridge.sh';
		$cmd .= ' >> ' . log::getPathToLog('mobile_homebridge_update') . ' 2>&1 &';
		exec($cmd);
		self::generate_file();
		
	}
	
	public static function generate_file(){
		self::deamon_stop();
		$response = array();
		$response['bridge'] = array();
		$response['bridge']['name'] = "Jeedom";
		$response['bridge']['username'] = "CC:22:3D:E3:CE:30";
		$response['bridge']['port'] = 51826;
		$response['bridge']['pin'] = config::byKey('pin_homebridge','mobile','031-45-154',true);
		
		$response['description'] = "Autogenerated config file by Jeedom";
		
		$plateform['platform'] = "Jeedom";
		$plateform['name'] = "Jeedom";
		$plateform['url'] = network::getNetworkAccess('internal');
		$plateform['apikey'] = config::byKey('api');
		$plateform['pollerperiod'] = 5;
		$response['platforms'] = array();
		$response['platforms'][] = $plateform;
		exec('sudo chown -R www-data:www-data ' . dirname(__FILE__) . '/../../resources');
		$fp = fopen(dirname(__FILE__) . '/../../resources/homebridge/config.json', 'w');
		fwrite($fp, json_encode($response));
		fclose($fp);
		self::deamon_start();
	}
	
	public static function deamon_info() {
		$return = array();
		$return['log'] = 'homebridge';
		$return['state'] = 'nok';
		if(self::check_ios() == 0){
			$return['state'] = 'ok';
			$return['launchable'] = 'ok';
			return $return;
		}
		$result = exec("ps -eo pid,command | grep 'homebridge' | grep -v grep | awk '{print $1}'");
		if ($result <> 0) {
            $return['state'] = 'ok';
        }
		$return['launchable'] = 'ok';
		return $return;
	}
	public static function deamon_start($_debug = false) {
		self::deamon_stop();
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') {
			if(self::check_ios() == 0){
				return false;
			}else{
				throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
			}
		}else{
			if(self::check_ios() == 0){
				return false;
			}
		}
		$cmd = 'homebridge -D -U '.dirname(__FILE__) . '/../../resources/homebridge';
		log::add('mobile_homebridge', 'info', 'Lancement démon homebridge : ' . $cmd);
		exec($cmd . ' >> ' . log::getPathToLog('mobile_homebridge') . ' 2>&1 &');
		$i = 0;
		while ($i < 30) {
			$deamon_info = self::deamon_info();
			if ($deamon_info['state'] == 'ok') {
				break;
			}
			sleep(1);
			$i++;
		}
		if ($i >= 30) {
			log::add('mobile_homebridge', 'error', 'Impossible de lancer le démon homebridge, relancer le démon en debug et vérifiez la log', 'unableStartDeamon');
			return false;
		}
		message::removeAll('homebridge', 'unableStartDeamon');
		log::add('mobile_homebridge', 'info', 'Démon homebridge lancé');
	}
	public static function deamon_stop() {
		$deamon_info = self::deamon_info();
		if ($deamon_info['state'] <> 'ok') {
            return true;
        }
        $pid = exec("ps -eo pid,command | grep 'homebridge' | grep -v grep | awk '{print $1}'");
        exec('kill ' . $pid);
        $check = self::deamon_info();
        $retry = 0;
        while ($deamon_info['state'] == 'ok') {
           $retry++;
            if ($retry > 10) {
                return;
            } else {
                sleep(1);
            }
        }

        return self::deamon_info();
	}
	
	/**************************************************************************************/
	/*                                                                                    */
	/*            Permet de supprimer le cache Homebridge   					          */
	/*                                                                                    */
	/**************************************************************************************/
	
	public static function eraseHomebridgeCache() {
		self::deamon_stop();
		$cmd = 'sudo rm -Rf '.dirname(__FILE__) . '/../../resources/homebridge/accessories';
		exec($cmd);
		$cmd = 'sudo rm -Rf '.dirname(__FILE__) . '/../../resources/homebridge/persist';
		exec($cmd);
		self::deamon_start();
	}
		
	/**************************************************************************************/
	/*                                                                                    */
	/*            Permet de decouvrir tout les modules de la Jeedom compatible            */
	/*                                                                                    */
	/**************************************************************************************/

	public static function discovery_eqLogic($plugin = array()){
		$return = array();
		foreach ($plugin as $plugin_type) {
			$eqLogics = eqLogic::byType($plugin_type, true);
			if (is_array($eqLogics)) {
				foreach ($eqLogics as $eqLogic) {
                  if(($eqLogic->getIsVisible() == 1 && $eqLogic->getObject_id() !== null) || $eqLogic->getEqType_name() == 'camera' || $eqLogic->getEqType_name() == 'netatmoThermostat' || $eqLogic->getEqType_name() == 'thermostat' || $eqLogic->getEqType_name() == 'alarm' || $eqLogic->getEqType_name() == 'weather'){
					$eqLogic_array = utils::o2a($eqLogic);
                    	$return[] = $eqLogic_array;
				}
                }
			}
		}
		return $return;
	}
	
	public static function discovery_cmd($plugin = array()){
		$return = array();
		foreach ($plugin as $plugin_type) {
			$eqLogics = eqLogic::byType($plugin_type, true);
			if (is_array($eqLogics)) {
				foreach ($eqLogics as $eqLogic) {
                  	$i = 0;
                  if($eqLogic->getObject_id() !== null && ($eqLogic->getIsVisible() == 1 || $eqLogic->getEqType_name() == 'camera' || $eqLogic->getEqType_name() == 'netatmoThermostat' || $eqLogic->getEqType_name() == 'thermostat' || $eqLogic->getEqType_name() == 'alarm' || $eqLogic->getEqType_name() == 'weather')){
					foreach ($eqLogic->getCmd() as $cmd) {
                    	if($cmd->getDisplay('generic_type') != 'GENERIC_ERROR' && $cmd->getDisplay('generic_type') != null && $cmd->getDisplay('generic_type') != 'DONT'){
                      		$cmd_array[] = $cmd->exportApi();
                      		$i++;
                      	}
					}
                  	if($i > 0){
                    	$return = $cmd_array;
                    }
				}
                }
			}
		}
		return $return;
	}

	/**************************************************************************************/
	/*                                                                                    */
	/*                         Permet de creer le Json du QRCode                          */
	/*                                                                                    */
	/**************************************************************************************/

	public function getQrCode() {
		$key = $this->getLogicalId();
		$request_qrcode = array(
          	'eqLogic_id' => $this->getId(),
			'url_internal' => network::getNetworkAccess('internal'),
			'url_external' => network::getNetworkAccess('external'),
			'Iq' => $key
          	);
      	if ($this->getConfiguration('affect_user') != '') {
		$username = user::byId($this->getConfiguration('affect_user'));
		if (is_object($username)) {
			$request_qrcode['username'] = $username->getLogin();
			$request_qrcode['apikey'] = $username->getHash();
		}
	}
      	$retour = 'https://chart.googleapis.com/chart?cht=qr&chs=300x300&chl='.json_encode($request_qrcode);
	return $retour;
	}
	
	/**************************************************************************************/
	/*                                                                                    */
	/*                         Permet de creer l'ID Unique du téléphone                   */
	/*                                                                                    */
	/**************************************************************************************/
	
	public function postInsert() {
		$key = config::genKey(32);
		$this->setLogicalId($key);
		$this->save();
	
	}

	/*     * *********************Méthodes d'instance************************* */

	/*     * **********************Getteur Setteur*************************** */
}

class mobileCmd extends cmd {
	/*     * *************************Attributs****************************** */

	/*     * ***********************Methode static*************************** */

	/*     * *********************Methode d'instance************************* */

	/*
											 * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
											public function dontRemoveCmd() {
											return true;
											}
											 */

	public function execute($_options = array()) {
		return false;
	}

	/*     * **********************Getteur Setteur*************************** */
}

?>
