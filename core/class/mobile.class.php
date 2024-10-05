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
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
include_file('core', 'bellaMobile', 'class', 'mobile');

class mobile extends eqLogic
{

	/*     * ***********************Methode static*************************** */

	public static function cronDaily()
	{
		mobile::deleteFileImg();
	}

	/*public static function backupExclude()
	{
		// returns the directory not to be saved in the Jeedom backup
		return ['data'];
	}*/

	public static function whoIsIq($iq)
	{
		$search = eqLogic::byLogicalId($iq, 'mobile');
		if (is_object($search)) {
			return $search->getName();
		} else {
			return 'mobile non detecte';
		}
	}

	public static function cleaningNotifications($Iq, $retentionTime)
	{
		log::add('mobile', 'debug', '┌──────────▶︎ :fg-warning: Nettoyage des Notifications et Images :/fg: ──────────');
		log::add('mobile', 'debug', '| Durée de retention actuelle : '. $retentionTime . ' jours');

		$retentionSeconds = intVal($retentionTime) * 24 * 60 * 60; 
		$currentTime = time();

		$pathImages = dirname(__FILE__) . '/../../data/images/';
		if(is_dir($pathImages)){
			$images = glob($pathImages . '*.jpg');
			foreach ($images as $image) {
				$fileCreationTime = filemtime($image);
				if ($fileCreationTime < ($currentTime - $retentionSeconds)) {
					unlink($image); 
				}
			}
		}

		$filePath = dirname(__FILE__) . '/../data/notifications/' . $Iq . '.json';
		$notifications = 'noNotifications';
		if (file_exists($filePath)) {
			$notifications = file_get_contents($filePath);
			if ($notifications) {
				$notifications = json_decode($notifications, true);
				$notificationsModified = false;
	
				foreach ($notifications as $id => $value) {
					$notificationDate = strtotime($value['data']['date']); 
					if (($currentTime - $notificationDate) > $retentionSeconds) {
						unset($notifications[$id]); 
						$notificationsModified = true;
					} 
				}
				$notifications = json_encode($notifications);
				if ($notificationsModified) { 
					file_put_contents($filePath, $notifications);
				}
			}
		}
		log::add('mobile', 'debug', '| Fin du nettoyage des Notifications et Images');
		log::add('mobile', 'debug', '└───────────────────────────────────────────');
	}	

	public static function getQrCodeV2($userId)
	{
		require_once dirname(__FILE__) . '/../../3rdparty/phpqrcode/qrlib.php';
		$interne = network::getNetworkAccess('internal');
		$externe = network::getNetworkAccess('external');
		if ($interne == null || $interne == 'http://:80' || $interne == 'https://:80') {
			return 'internalError';
		}
		if ($externe == null || $externe == 'http://:80' || $externe == 'https://:80') {
			return 'externalError';
		}
		if (!is_object(user::byId($userId))) {
			return 'UserError';
		}
		$request_qrcode = array(
			'url_internal' => $interne,
			'url_external' => $externe
		);
		if (is_object($user = user::byId($userId))) {
			$request_qrcode['username'] = $user->getLogin();
			$request_qrcode['apikey'] = $user->getHash();
		}
		ob_start();
		QRcode::png(json_encode($request_qrcode));
		$imageString = base64_encode(ob_get_contents());
		ob_end_clean();
		return $imageString;
	}

	public static function jsonPublish($os, $titre, $message, $type, $idNotif, $answer, $timeout, $token, $photo, $version, $optionsNotif = [], $critical = false, $Iq = null)
	{
		log::add('mobile', 'debug', '||┌──:fg-success: jsonPublish :/fg:──');
		if(isset($Iq)) log::add('mobile', 'debug', '||| IQ for jsonPublish > ' . $Iq);

		$dateNotif = date("Y-m-d H:i:s");
		$newDate = date("Y-m-d");
		$horaireFormat = date("H:i");
		$badge = 0;
		if ($timeout != 'nok') {
			$timeout = date('Y-m-d H:i:s', strtotime("$dateNotif + $timeout SECONDS"));
		}
		$addAsk = '';
		if ($type == 'ask_Text') {
			$addAsk = '\"category\":\"TEXT_CATEGORY\",\"answer\":\"' . $answer . '\",\"timeout\":\"' . $timeout . '\",';
		}

		if ($token == null) {
			$message = preg_replace("# {2,}#", " ", preg_replace("#(\r\n|\n\r|\n|\r)#", "\\\\\\n", $message));
			if ($os == 'ios') {
				if ($badge == 'null') {
					$publish = '{"default": "test", "APNS": "{\"aps\":{\"content-available\":\"1\",' . $addAsk . '\"alert\": {\"title\":\"' . $titre . '\",\"body\":\"' . $message . '\"},\"badge\":\"0\",\"sound\":\"silence.caf\",\"date\":\"' . $dateNotif . '\",\"idNotif\":\"' . $idNotif . '\"}}"}';
				} else {
					$publish = '{"default": "test", "APNS": "{\"aps\":{\"content-available\":\"1\",' . $addAsk . '\"alert\": {\"title\":\"' . $titre . '\",\"body\":\"' . $message . '\"},\"badge\":\"' . $badge . '\",\"sound\":\"silence.caf\",\"date\":\"' . $dateNotif . '\",\"idNotif\":\"' . $idNotif . '\"}}"}';
				}
			} else if ($os == 'android') {
				$publish = '{"default": "Erreur de texte de notification", "GCM": "{ \"notification\": {\"e\":0,\"title\":\"test\",\"body\":\"NotficationTEST\"},\"data\":{\"ticker\":\"test\",\"android_channel_id\":\"JEEDOM_CHANNEL\",\"notificationId\":\"' . $idNotif . '\",\"title\":\"' . $titre . '\",\"text\":\"' . $message . '\",' . $addAsk . '\"sound\":\"default\",\"idNotif\":\"' . $idNotif . '\",\"date\":\"' . $dateNotif . '\",\"smallIcon\":\"notificon\",\"largeIcon\":\"appicon.png\"}}"}';
			} else if ($os == 'microsoft') {
			}
		} else {
			if ($os == 'android' && $version == 1) {
				$android = [
					'notification' => [
						'title' => $titre,
						'body' => $message,
						'channel_id' => 'default',
						'color' => '#0000FF'
					]
				];

				$data = [
					'title' => $titre,
					'text' => $message,
					'idNotif' => strval($idNotif),
					'channelId' => 'default',
					'date' => $dateNotif

				];

				if ($photo != null) {
					$notification = [
						'title' => $titre,
						'body' => $message,
						'image' => $photo
					];
				} else {
					$notification = [
						'title' => $titre,
						'body' => $message
					];
				}

				$publish = [
					'token' => $token,
					'notification' => $notification,
					'android' => $android,
					'data' => $data
				];
			}
			if ($version == 2) {

				if ($addAsk != '') {
					$askParams = [
						'choices' => $answer,
						'idVariable' => $optionsNotif['askVariable'],
						'boxName' => config::byKey('name'),
						'hwKey' => jeedom::getHardwareKey(),
						'timeout' => (strtotime($timeout) - time()) * 1000,
						'isBack' => false
					];
					$askParams = json_encode($askParams);
				} else {

					$askParams = 'noAsk';
					$optionsNotif['askVariable'] = 'rien';
				}

				$optionsNotif['askParams'] = $askParams;

				$channelId = "default";
				if ($os == 'android' && $critical == true) {
					$channelId = "critical";
				}
				if ($critical == true) {
					$criticalString = 'true';
				} else {
					$criticalString = 'false';
				}
				$customData = [
					'title' => $titre,
					'body' => $message,
					'idNotif' => strval($idNotif),
					'channelId' => $channelId,
					'date' => $dateNotif,
					'critical' => $criticalString,
					'boxName' => config::byKey('name'),
					'boxApiKey' => jeedom::getHardwareKey(),
					"askParams" => $askParams,
					'textToDisplay' => 'none',
					'newDate' => $newDate,
					'horaireFormat' => $horaireFormat
				];

				$notification = [
					'title' => $titre,
					'body' => $message,
				];

				$data = array_merge($customData, $optionsNotif);

				$android = [
					'data' => $data,
					'priority' => 'high'
				];

				$apns = [
					'headers' => [
						'apns-priority' => '5',
						'apns-collapse-id' => strval($idNotif),
						'apns-push-type' => 'alert',
						'apns-topic' => 'com.jeedom.jeedomobile'
					],
					'payload' => [
						'aps' => [
							'content-available' => true,
							'sound' => [
								'name' => 'default',
								'critical' => $critical
							],
							'alert' => [
								'subtitle' => config::byKey('name'),
								'title' => $titre,
								'body' => $message
							]
						],
						'notifee_options' => [
							'ios' => [
								'sound' => 'default',
								'critical' => $critical,
								'foregroundPresentationOptions' => [
									'alert' => true,
									'badge' => true,
									'sound' => true
								]
							]
						]
					]

				];


				if ($photo != null) {
					$data['image'] = $photo;
					$apns['payload']['notifee_options']['image'] = $photo;
					$apns['payload']['notifee_options']['ios']['attachments'] = [
						[
							'url' => $photo,
							'typeHint' => $optionsNotif['typeHint']
						]
					];
				}

				if ($os == 'android') {
					$publish = [
						'token' => $token,
						'android' => $android,
						'data' => $data,
					];
				} else {
					$publish = [
						'token' => $token,
						'data' => $data,
						'apns' => $apns
					];
				}

				$publishJson = [
					'token' => $token,
					'data' => $data,
				];


				if(isset($Iq)){
						// SAVE NOTIFS IN JSON
						$pathNotificationData = '/../data/notifications';
						if (!is_dir(dirname(__FILE__) . $pathNotificationData)) {
							mkdir(dirname(__FILE__) . $pathNotificationData, 0775, true);
						}
						$filePath = dirname(__FILE__) . $pathNotificationData . '/' . $Iq . '.json';

						if (!file_exists($filePath)) {
							file_put_contents($filePath, '');
						}
						$notificationsContent = file_get_contents($filePath);
						$notifications = json_decode($notificationsContent, true);

						if ($notifications === null) {
							$notifications = array();
						}

						foreach ($notifications as &$notification) {
							if (isset($notification['data']['askParams'])) {
								$askParams = json_decode($notification['data']['askParams'], true);
								if ($askParams !== null && isset($askParams['timeout'])) {
									//log::add('mobile', 'debug', 'Timeout Ask remis à zero');
									$askParams['timeout'] = 0;
									$notification['data']['askParams'] = json_encode($askParams);
								}
							}
						}

						$notifications[$idNotif] = $publishJson;
						log::add('mobile', 'debug', '||| [INFO] Notification enregistrée : ' . json_encode($notifications));
						file_put_contents($filePath, json_encode($notifications));
				}

			}
		}
		log::add('mobile', 'debug', '||| [INFO] JSON publish > ' . json_encode($publish));
		log::add('mobile', 'debug', '||└────────────────────');
		return $publish;
	}

	public static function notification($arn, $os, $titre, $message, $type, $idNotif, $answer,  $timeout, $token, $photo, $version = 1, $optionsNotif = [], $critical = false, $Iq = null)
	{
		log::add('mobile', 'debug', '|┌──:fg-success: Notification en cours ! :/fg:──');
		$publish = mobile::jsonPublish($os, $titre, $message, $type, $idNotif, $answer,  $timeout, $token, $photo, $version, $optionsNotif, $critical, $Iq);
		if ($token != null) {
			if ($token == 'notifsBGDisabled') {
				log::add('mobile', 'debug', '|| [ERROR] NOTIFICATION NON ENVOYEE : LE SERVICE NOTIF EST DESACTIVE SUR LE TELEPHONE');
				message::add(__CLASS__, 'Échec de l\'envoie de notification : le service est désactivé dans les paramètres du téléphone', 'notifsbgSend', 'alertNotifsSend');
				return;
			}
			if ($token == 'desactivate') {
				log::add('mobile', 'debug', '|| [ERROR] NOTIFICATION NON ENVOYEE : LES NOTIFICATIONS SONT DESACTIVEES DANS L\'APP : ');
				message::add(__CLASS__, 'Échec de l\'envoie de notification : le service est désactivé dans les paramètres de l\'application', 'notifsbgSend', 'alertNotifsSend');
				return;
			}
			$url = config::byKey('service::cloud::url', 'core', 'https://cloud.jeedom.com') . '/service/fcm';
			$options = [
				'contentAvailable' => true,
				'mutableContent' => true,
				'priority' => 'high',
				'collapseKey' => strval($publish['data']['idNotif'])
			];

			$post = ['message' => $publish, 'options' => $options];
			log::add('mobile', 'debug', '|| [INFO] JSON envoyé en mode FCM > ' . json_encode($post));
		} elseif ($token == null && $version == 2) {
			log::add('mobile', 'debug', '|| [ERROR] NOTIFICATION NON ENVOYEE : PAS DE TOKEN ENREGISTRE SUR LE TELEPHONE :  ');
			//message::removeAll(__CLASS__, 'noValidToken');
			message::add(__CLASS__, '| NOTIFICATION NON ENVOYÉE : PAS DE TOKEN ENREGISTRE SUR LE TÉLÉPHONE :', 'noValidTok', 'noValidToken');
			return;
		} else {
			log::add('mobile', 'debug', '|| [INFO] JSON envoyé : APN' . $publish);
			$post = [
				'arn' => $arn,
				'text' => $publish,
			];
			$url = config::byKey('service::cloud::url', 'core', 'https://cloud.jeedom.com') . '/service/notif';
		}

		$request_http = new com_http($url);
		$request_http->setHeader(array(
			'Content-Type: application/json',
			'Autorization: ' . sha512(strtolower(config::byKey('market::username')) . ':' . config::byKey('market::password'))
		));
		//$request_http->setLogError(true);
		$request_http->setPost(json_encode($post));
		$result = json_decode($request_http->exec(30,3), true);
		if (!isset($result['state']) || $result['state'] != 'ok') {
			log::add('mobile', 'info', '|| [WARNING] Echec Première Tentative d\'envoi de la notification');
			log::add('mobile', 'info', '|| Nouvelle tentative ....');
			sleep(rand(1,10));
			$result = json_decode($request_http->exec(30,3), true);
		}
		if (!isset($result['state']) || $result['state'] != 'ok') {
			if (isset($result['error']) && strpos($result['error'], 'Quotas exceeded') !== false) {
				log::add('mobile', 'error', __("Les quotas pour fcm sont dépassés. Le maximum autorisé est de 5 requêtes par minute.", __FILE__));
				log::add('mobile', 'debug', __('Echec de l\'envoi de la notification :', __FILE__) . json_encode($result));
			} else {
				throw new Exception(__('Echec de l\'envoi de la notification :', __FILE__) . json_encode($result));
			}
		}
		log::add('mobile', 'debug', '|└────────────────────');
	}

	public static function createCmdGeoLocV2($Iq, $geolocs)
	{
		log::add('mobile', 'debug', '|┌──:fg-success: GeoLocV2 :/fg:──');
		$mobile = eqLogic::byLogicalId($Iq, 'mobile');
		if (is_object($mobile)) {
			log::add('mobile', 'debug', '||  OK  Mobile existant > ' . $mobile->getName());
			log::add('mobile', 'debug', '|| [INFO] GEOLOCS > ' . $geolocs);

			$noExistCmd = 0;
			$decodedGeolocs = json_decode($geolocs, true);
			foreach ($decodedGeolocs as $index => $geoloc) {
				if (!isset($geoloc['name'])) continue;
				log::add('mobile', 'debug', '|| geoloc_' . $index . ' > ' . $geoloc['name']);
				$cmdgeoloc = cmd::byEqLogicIdAndLogicalId($mobile->getId(), 'geoloc_' . $index);

				if (!is_object($cmdgeoloc)) {
					$noExistCmd = 1;
					$cmdgeoloc = new mobileCmd();
					$cmdgeoloc->setLogicalId('geoloc_' . $index);
					$cmdgeoloc->setEqLogic_id($mobile->getId());
					$cmdgeoloc->setType('info');
					$cmdgeoloc->setSubType('binary');
					$cmdgeoloc->setIsVisible(1);
					$cmdgeoloc->setGeneric_type('PRESENCE');
					$cmdgeoloc->setIsHistorized(1);
					log::add('mobile', 'debug', '|| Ajout geofencing > ' . $geoloc['name']);
				}
				$cmdgeoloc->setName($geoloc['name']);
				$cmdgeoloc->setConfiguration('latitude', $geoloc['latitude']);
				$cmdgeoloc->setConfiguration('longitude', $geoloc['longitude']);
				$cmdgeoloc->setConfiguration('radius', $geoloc['radius']);
				$cmdgeoloc->save();
				if ($noExistCmd == 1) {
					$cmdgeoloc->event($geoloc['value']);
					log::add('mobile', 'debug', '|| Valeur enregistrée > ' . $geoloc['value']);
				}
				$noExistCmd = 0;
			}
		} else {
			log::add('mobile', 'debug', '| [ERROR] Mobile inexistant !');
		}
		log::add('mobile', 'debug', '|└────────────────────');
	}

	public static function deleteFileImg()
	{
		$directory = dirname(__FILE__) . '/../../data/images'; // Chemin vers le répertoire contenant les fichiers
		// Récupérer la liste des fichiers dans le répertoire
		$files = glob($directory . '/*');
		// Date actuelle
		$currentDate = time();
		// Parcourir tous les fichiers
		foreach ($files as $file) {
			// Vérifier la date de modification du fichier
			$modifiedDate = filemtime($file);
			$differenceInDays = floor(($currentDate - $modifiedDate) / (60 * 60 * 24));
			// Vérifier si le fichier a plus de 30 jours
			if ($differenceInDays > 30) {
				// Supprimer le fichier
				unlink($file);
			}
		}
	}

	public static function getMenuDefaultV2($nbIcones = 3)
	{
		$namesMenus =  ['home', 'overview', 'health', 'home'];
		$renamesIcons =  ['Accueil', 'Synthese', 'Santé', 'Accueil'];
		$spanIcons =  ['icon jeedomapp-in', 'fab fa-hubspot', 'fas fa-medkit', 'icon jeedomapp-in'];
		$urlUsers =  ['none', 'none', 'none', 'none'];
		$j = 0;
		$menuCustomArray = [];
		for ($i = 1; $i <= $nbIcones; $i++) {
			$menuCustomArray[$i]['selectNameMenu'] = $namesMenus[$j];
			$menuCustomArray[$i]['renameIcon'] = $renamesIcons[$j];
			$menuCustomArray[$i]['spanIcon'] = $spanIcons[$j];
			$menuCustomArray[$i]['urlUser'] = $urlUsers[$j];
			$j++;
		}
		return $menuCustomArray;
	}

	public static function handleMenuDefaultBySelect($eqId, $eqDefault)
	{
		if (!is_object($mobile = eqLogic::byId($eqId, 'mobile'))) return;

		// ATTRIBUTION DUN MENU PAR DEFAULT AU MOBILE
		if ($eqDefault == 'default') {
			$menuCustomArray = mobile::getMenuDefaultV2();
			$mobile->setConfiguration('menuCustomArray', $menuCustomArray);
			$mobile->setConfiguration('nbIcones', 3);
			$mobile->setConfiguration('defaultIdMobile', 'default');
			$mobile->save();
		} else if (is_object($mobileDefault = eqLogic::byId($eqDefault, 'mobile'))) {
		// ATTRIBUTION DU MENU DUN AUTRE MOBILE
			$mobile->setConfiguration('defaultIdMobile', $eqDefault);
			if ($mobile->getId() == $mobileDefault->getId()) {
              $mobile->save();
              return;
			}
			$nbIcones = $mobileDefault->getConfiguration('nbIcones', 3);
			$menuCustomArray = $mobileDefault->getConfiguration('menuCustomArray');
			$mobile->setConfiguration('nbIcones', $nbIcones);
			$mobile->setConfiguration('menuCustomArray', $menuCustomArray);
			$mobile->save();
        }
	}

	public static function configMenuCustom($eqId, $jeedomVersion)
	{
		log::add('mobile', 'debug', '|┌──:fg-success: CONFIGMENU CUSTOM JEEDOM ' . $jeedomVersion . ' :/fg:──');
		//if ($jeedomVersion < '4.4.0') {
			//return $defaultMenuArray = self::getDefaultMenuArray();
		//}
		$defaultMenuArray = self::getDefaultMenuArray();
		$pluginPanelMobile = config::byKey('pluginPanelMobile', 'mobile');

		if (is_object($eqLogic = eqLogic::byId($eqId))) {
			$eqLogics = eqLogic::byType('mobile');
			$menuCustomArray = $eqLogic->getConfiguration('menuCustomArray');

			// ATTRIBUTION MOBILE PAR DEFAUT A TOUS LES MOBILES
			foreach ($eqLogics as $mobile) {
				if ($mobile->getConfiguration('defaultIdMobile') == $eqId) {
					$countFor = intval($eqLogic->getConfiguration('nbIcones', 3)) + 1;
					$menuArrayTemp = [];
					for ($i = 1; $i < $countFor; $i++) {
						$menuArrayTemp[$i]['selectNameMenu'] = isset($menuCustomArray[$i]['selectNameMenu']) ? $menuCustomArray[$i]['selectNameMenu'] : 'none';
						$menuArrayTemp[$i]['renameIcon'] = isset($menuCustomArray[$i]['renameIcon']) ? $menuCustomArray[$i]['renameIcon'] : '';
						$menuArrayTemp[$i]['spanIcon'] = isset($menuCustomArray[$i]['spanIcon']) ? $menuCustomArray[$i]['spanIcon'] : 'none';
						$menuArrayTemp[$i]['urlUser'] = isset($menuCustomArray[$i]['urlUser']) ? $menuCustomArray[$i]['urlUser'] : 'none';
					}
					$mobile->setConfiguration('menuCustomArray', $menuArrayTemp);
					$mobile->save();
				};
			}
			$nbIcones = $eqLogic->getConfiguration('nbIcones', 3);
			$arrayElements = array();
			$j = 0;
			$count = 1;
			for ($i = 1; $i < 5; $i++) {

				// GENERATE TAB ICON LIBRARY AND RENAME BY USER
				$resultTabIcon = self::generateTabIcon($menuCustomArray, $i);
				$tabIconName = $resultTabIcon['tabIconName'];
				$tabLibName = $resultTabIcon['tabLibName'];
				$tabRenameInput = $resultTabIcon['tabRenameInput'];
				//$objectId = $menuCustomArray[$i]['selectNameMenu'];
				$objectId = isset($menuCustomArray[$i]['selectNameMenu']) ? $menuCustomArray[$i]['selectNameMenu'] : '';
				$isActive = true;
				$webviewUrl = 'd';

				log::add('mobile', 'debug', '|| - objectId > ' . $objectId);

				// GENERATE URLS FOR MENU CUSTOM 
				$result = self::generateTypeObject($objectId, $i, $webviewUrl, $pluginPanelMobile);
				$typeObject = $result['typeObject'];
				$typewebviewurl = $result['typewebviewurl'];
				$typeobjectId = $result['typeobjectId'];
				$tabUrl = $result['tabUrl'];

				if ($count > intval($nbIcones)) {
					$isActive = false;
				}
				//log::add('mobile', 'debug', '| - Construction jsonTemplate');
				$jsonTemplate = array(
					'active' => $isActive,
					'icon' => [
						'name' => $tabIconName,
						'type' => $tabLibName
					],
					'name' => $tabRenameInput,
					'options' => [
						'uri' => $tabUrl,
						'objectType' => $typeObject,
						'mobile' => $typewebviewurl,
						'objectId' => $typeobjectId
					],
					'type' =>  strpos($tabUrl, 'www') !== false ? 'urlwww' : 'WebviewApp'
				);
				$arrayElements['tab' . $j] =  $jsonTemplate;
				$j++;
				$count++;
			}
			log::add('mobile', 'debug', '|| [INFO] arrayElements > ' . json_encode($arrayElements));
			log::add('mobile', 'debug', '|└────────────────────');
			if (count($arrayElements) == 4) {
				$j = 0;
				for ($i = 0; $i < 4; $i++) {
					$isBool = is_bool($arrayElements['tab' . $i]['active']);
					if ($isBool) {
						if ($arrayElements['tab' . $i]['active'] == true) {
							$j++;
						}
					} else {
						return $defaultMenuArray;
					}
				}
				return ($j == 0) ? $defaultMenuArray : $arrayElements;
			}
			return $defaultMenuArray;
		}
		return $defaultMenuArray;
	}

	public static function generateTabIcon($menuCustomArray, $i)
	{
		$result = array();

		$tabIconName = isset($menuCustomArray[$i]['spanIcon']) ? $menuCustomArray[$i]['spanIcon'] : 'none';

		if ($tabIconName != 'none') {
			$arrayIcon = explode(' ', $tabIconName);
			$tabIconName = substr(strstr($arrayIcon[1], '-'), 1);
			$tabLibName = strstr($arrayIcon[1], '-', true);
			if ($tabLibName == 'mdi') {
				$tabLibName = 'Mdi';
			}
		} else {
			$tabIconName = 'in';
			$tabLibName = 'jeedomapp';
		}

		$tabRenameInput = (isset($menuCustomArray[$i]['renameIcon']) && $menuCustomArray[$i]['renameIcon'] != 'none') ? $menuCustomArray[$i]['renameIcon'] : 'Accueil';

		$result['tabIconName'] = $tabIconName;
		$result['tabLibName'] = $tabLibName;
		$result['tabRenameInput'] = $tabRenameInput;

		return $result;
	}

	public static function generateTypeObject($objectId, $i, $webviewUrl, $pluginPanelMobile)
	{
		$result = array();
		if ($objectId && $objectId != -1 && $objectId != 'none' && $objectId != 'url') {
			// SPECIFIC OBJETS FOR URL
			$excludedRefs = array('overview', 'health', 'home', 'timeline');
			if (!in_array($objectId, $excludedRefs)) {
				$arrayObjects = explode('_', $objectId);
				$objectId = $arrayObjects[0];
				$typeObject = $arrayObjects[1];

				$typewebviewurl = $webviewUrl;
				$typeobjectId = $objectId;

				switch ($typeObject) {
					case 'views':
						$tabUrl = "/index.php?v={$webviewUrl}&p=view&view_id={$objectId}";
						break;
					case 'dashboard':
						$tabUrl = "/index.php?v={$webviewUrl}&p=dashboard&object_id={$objectId}";
						break;
					case 'plan':
						$tabUrl = "/index.php?v={$webviewUrl}&p=plan&plan_id={$objectId}";
						break;
					case 'panel':
						$tabUrl = (isset($pluginPanelMobile[$objectId]) && $pluginPanelMobile[$objectId] == $objectId) ? "/index.php?v=m&p={$objectId}" : "/index.php?v=m&p={$objectId}&app_mode=1";
						break;
					default:
						break;
				}
			} else {
				$typeObject = $objectId;
				$typewebviewurl = $webviewUrl;
				$typeobjectId = '';

				switch ($objectId) {
					case 'overview':
						$tabUrl = "/index.php?v=m&p=overview";
						break;
					case 'home':
						$tabUrl = "/index.php?v=m&p=home";
						break;
					case 'health':
						$tabUrl = "/index.php?v=m&p=health";
						break;
					case 'timeline':
						$tabUrl = "/index.php?v=m&p=timeline";
						break;
					default:
						$typeObject = $objectId;
						$typewebviewurl = 'm';
						$typeobjectId = '';
						$tabUrl = '/index.php?v=m&app_mode=1';
						break;
				}
			}
		} elseif ($objectId == 'url') {
			$typeObject = $objectId;
			$typewebviewurl = $webviewUrl;
			$typeobjectId = 'url';
			$tabUrl = $menuCustomArray[$i]['urlUser'];
		} else {
			$typeObject = $objectId;
			$typewebviewurl = 'm';
			$typeobjectId = '';
			$tabUrl = '/index.php?v=m&app_mode=1';
		}

		$result['typeObject'] = $typeObject;
		$result['typewebviewurl'] = $typewebviewurl;
		$result['typeobjectId'] = $typeobjectId;
		$result['tabUrl'] = $tabUrl;

		return $result;
	}

	private static function getDefaultMenuArray()
	{
		$defaultMenuJson = '{"tab0":{"active":true,"icon":{"name":"in","type":"jeedomapp"},"name":"Accueil","options":{"uri":"\/index.php?v=m&p=home"},"type":"WebviewApp"},
                        "tab1":{"active":false,"icon":{"name":"hubspot","type":"fa"},"name":"Synthese","options":{"uri":"\/index.php?v=m&p=overview"},"type":"WebviewApp"},
                        "tab2":{"active":false,"icon":{"name":"medkit","type":"fa"},"name":"Sant\u00e9","options":{"uri":"\/index.php?v=m&p=health"},"type":"WebviewApp"},
                        "tab3":{"active":false,"icon":{"name":"in","type":"jeedomapp"},"name":"Accueil","options":{"uri":"\/index.php?v=m&p=home"},"type":"WebviewApp"}}';
		return json_decode($defaultMenuJson, true);
	}

  /*
  * Call by
    - jsonrpc -> nfc
    - jsonrpc -> qrcodemethod
  */
	public static function cmdForApi($Iq, $logicalId, $value, $name = "", $subtype = "string")
	{
		$mobile = eqLogic::byLogicalId($Iq, 'mobile');
		if (is_object($mobile)) {
			$cmd = $mobile->getCmd(null, $logicalId);
			if (!is_object($cmd)) {
				if ($name == "") {
					$name = $logicalId;
				}
				$cmd = new mobileCmd();
				$cmd->setLogicalId($logicalId);
				$cmd->setName($name);
				$cmd->setOrder(0);
				$cmd->setEqLogic_id($mobile->getId());
				$cmd->setType('info');
				$cmd->setSubType($subtype);
				$cmd->setIsVisible(1);
				if (in_array($logicalId, array('barrecodemethod', 'nfcPayload', 'nfcId'))) {
					$cmd->setConfiguration('repeatEventManagement', 'always');
				}
				$cmd->save();
			}
			$cmd->event($value);
		}
	}

	/*     * *********************Méthodes d'instance************************* */

	public function getQrCode()
	{
		require_once dirname(__FILE__) . '/../../3rdparty/phpqrcode/qrlib.php';
		$interne = network::getNetworkAccess('internal');
		$externe = network::getNetworkAccess('external');
		if ($interne == null || $interne == 'http://:80' || $interne == 'https://:80') {
			return 'internalError';
		}
		if ($externe == null || $externe == 'http://:80' || $externe == 'https://:80') {
			return 'externalError';
		}
		if ($this->getConfiguration('affect_user') == '') {
			return 'UserError';
		}
		$key = $this->getLogicalId();
		$request_qrcode = array(
			'eqLogic_id' => $this->getId(),
			'url_internal' => $interne,
			'url_external' => $externe,
			'Iq' => $key,
		);
		if ($this->getConfiguration('affect_user') != '') {
			$username = user::byId($this->getConfiguration('affect_user'));
			if (is_object($username)) {
				$request_qrcode['username'] = $username->getLogin();
				$request_qrcode['apikey'] = $username->getHash();
			}
		}
		ob_start();
		QRcode::png(json_encode($request_qrcode));
		$imageString = base64_encode(ob_get_contents());
		ob_end_clean();
		return $imageString;
	}

	public function postInsert()
	{
		if ($this->getLogicalId() == '') {
			$key = config::genKey(32);
			$this->setLogicalId($key);
			$this->save();
		}
	}

	public function postSave()
	{
		$cmdNotif = $this->getCmd(null, 'notif');
		if (!is_object($cmdNotif)) {
			$cmdNotif = new mobileCmd();
			$cmdNotif->setIsVisible(1);
			$cmdNotif->setName(__('Notification', __FILE__));
			$cmdNotif->setOrder(0);
		}
		$cmdNotif->setLogicalId('notif');
		$cmdNotif->setEqLogic_id($this->getId());
		$cmdNotif->setDisplay('generic_type', 'GENERIC_ACTION');
		$cmdNotif->setType('action');
		$cmdNotif->setSubType('message');
		$cmdNotif->save();

		if ($this->getConfiguration('appVersion', 1) == 2) {
			$cmdNotif = $this->getCmd(null, 'notifCritical');
			if (!is_object($cmdNotif)) {
				$cmdNotif = new mobileCmd();
				$cmdNotif->setIsVisible(1);
				$cmdNotif->setName(__('Notification Critique', __FILE__));
				$cmdNotif->setOrder(0);
			}
			$cmdNotif->setLogicalId('notifCritical');
			$cmdNotif->setEqLogic_id($this->getId());
			$cmdNotif->setDisplay('generic_type', 'GENERIC_ACTION');
			$cmdNotif->setType('action');
			$cmdNotif->setSubType('message');
			$cmdNotif->save();
		}

		$cmdaskText = $this->getCmd(null, 'ask_Text');
		if (is_object($cmdaskText)) {
			$cmdaskText->remove();
		}
		$cmdaskYN = $this->getCmd(null, 'ask_YN');
		if (is_object($cmdaskYN)) {
			$cmdaskYN->remove();
		}
	}

	public function preRemove()
	{
		log::add('mobile', 'debug', '┌──:fg-success: preRemove() :/fg:──');
		$Iq = $this->getId();
		/* App V2 */
		foreach (eqLogic::byType('mobile') as $mobile) {
			if ($Iq == $mobile->getId()) continue;
			if ($mobile->getConfiguration('defaultIdMobile', 'none') == $Iq) {
				$mobile->setConfiguration('defaultIdMobile', $mobile->getId());
				$mobile->save();
				log::add('mobile', 'debug', '| Modification du defaultIdMobile pour le mobile ' . $mobile->getHumanName(false) . ' ( ' . $mobile->getId() . ' ) ');
			}
		}
		$fileNotif = dirname(__FILE__) . '/../data/notifications/' . $this->getLogicalId() . '.json';
		if (file_exists($fileNotif)) {
			log::add('mobile', 'debug', '| Suppression du fichier des notifications : ' . $fileNotif);
			shell_exec('rm ' . $fileNotif);
		}
		/* App V1 */
		$path = dirname(__FILE__) . '/../../data/' . $this->getLogicalId();
		if (file_exists($path)) {
			log::add('mobile', 'debug', '| Suppression du dossier : ' . $path);
			shell_exec('rm -rf ' . $path);
		}
		log::add('mobile', 'debug', '└────────────────────');
	}

	/*	public function postRemove() {

	}*/

	/*     * **********************Getteur Setteur*************************** */

}

class mobileCmd extends cmd
{
	/*     * *************************Attributs****************************** */

	/*     * ***********************Methode static*************************** */

	/*     * *********************Methode d'instance************************* */

	/*public function dontRemoveCmd() {
		return true;
	}*/

	public static function fileInMessage($data)
	{
		log::add('mobile', 'debug', '|┌──:fg-success: fileInMessage :/fg:──');
		$dataArray = explode('|', $data);
		$result = array();
		foreach ($dataArray as $item) {

			$arg = explode('=', trim($item), 2);
			if (count($arg) == 2) {
				$result[trim($arg[0])] = trim($arg[1]);
			}
		}
		$result['message'] = $dataArray[0];
		log::add('mobile', 'debug', '|| [INFO] File Parse > ' . json_encode($result));
		if (array_key_exists('file', $result)) {
			log::add('mobile', 'debug', '|| file > ' . $result['file']);
			log::add('mobile', 'debug', '|└────────────────────');
			return $result;
		} else {
			log::add('mobile', 'debug', '|└────────────────────');
			//log::add('mobile', 'debug', '| null');
			return null;
		}
	}

	public function execute($_options = array())
	{
		if ($this->getType() != 'action') {
			return;
		}
		log::add('mobile', 'debug', '┌──:fg-success: execute :/fg:──');
		$optionsNotif = [];
		$eqLogic = $this->getEqLogic();

		if ($this->getLogicalId() == 'notif' || $this->getLogicalId() == 'notifCritical') {

			if ($_options['title'] == '' || $_options['title'] == $_options['message'] || $_options['title'] == ' ') {
				$_options['title'] = config::byKey('product_name');
			}

			$critical = false;

			if ($this->getLogicalId() == 'notifCritical') {
				$critical = true;
			}

			$file = mobileCmd::fileInMessage($_options['message']);
			if (!isset($_options['files']) && $file != null) {
				$_options['files'] = array();
				array_push($_options['files'], $file['file']);
				$_options['message'] = $file['message'];
				log::add('mobile', 'debug', '| file detected ' . json_encode($file));
			}

			$answer = (isset($_options['answer']) && $_options['answer']) ? join(';', $_options['answer']) : null;
			$askVariable = isset($_options['variable']) ? $_options['variable'] : null;
			$askType = isset($_options['answer']) && $_options['answer'] ? 'ask_Text' : 'notif';
			$timeout = isset($_options['timeout']) && $_options['timeout'] ? $_options['timeout'] : 'nok';
			$optionsNotif['askVariable'] = $askVariable;
			//log::add('mobile', 'debug', '|-----------------------------------');
			log::add('mobile', 'debug', '| Commande de notification : ' . $askType);
			if (($eqLogic->getConfiguration('notificationArn', null) != null || $eqLogic->getConfiguration('notificationRegistrationToken', null) != null) && $eqLogic->getConfiguration('type_mobile', null) != null) {
				$idNotif = $eqLogic->getConfiguration('idNotif', 0);
				$idNotif = $idNotif + 1;
				$eqLogic->setConfiguration('idNotif', $idNotif);
				$eqLogic->save();

				log::add('mobile', 'debug', '| [INFO] Notif > ' . json_encode($_options));
				log::add('mobile', 'debug', '| eqLogic > ' . $eqLogic->getId() . ' | LogicalId > ' . $this->getLogicalId() . ' | idNotif > ' . $idNotif);
				if (isset($options['file'])) {
					//log::add('mobile', 'debug', '| [NOTICE] FILE');
					//unset($data['file']);
					$_options['files'] = explode(',', $options['file']);
				}
				if (isset($_options['files']) && is_array($_options['files'])) {
					log::add('mobile', 'debug', '| [NOTICE] FILE');
					foreach ($_options['files'] as $file) {
						log::add('mobile', 'debug', '| FILES as FILE');
						if (trim($file) == '') {
							continue;
						}
						$ext = pathinfo($file, PATHINFO_EXTENSION);
						log::add('mobile', 'debug', '| ' . $ext . ' pour > ' . $file);
						if (in_array($ext, array('gif', 'jpeg', 'jpg', 'png'))) {
							log::add('mobile', 'debug', '| type photo !');
							if ($ext == "gif") {
								$typeHint = "com.compuserve.gif";
							} else if ($ext == "jpeg") {
								$typeHint = "public.jpeg";
							} else if ($ext == "jpg") {
								$typeHint = "public.jpeg";
							} else if ($ext == "png") {
								$typeHint = "public.png";
							} else {
								$typeHint = "public.jpeg";
							}
							$optionsNotif['typeHint'] = $typeHint;
							$url = network::getNetworkAccess('external');
							$url .= '/plugins/mobile/core/php/image.php?';
							$nameFile = base64_encode($file) . '.' . $ext;
							$path = dirname(__FILE__) . '/../../data/images';
							$newfile = $path . '/' . $nameFile;
							log::add('mobile', 'debug', '| copie sur > ' . $newfile);
							if (!file_exists($path)) {
								mkdir($path);
							}
							if (!copy($file, $newfile)) {
								log::add('mobile', 'error', 'la copie de l\'image a echoué');
							}
							$keyFile = md5_file($newfile);
							$url .= 'key=' . $keyFile . '&name=' . $nameFile;
							log::add('mobile', 'debug', '| url > ' . $url);
							mobile::notification($eqLogic->getConfiguration('notificationArn', null), $eqLogic->getConfiguration('type_mobile', null), $_options['title'], $_options['message'], $askType, $idNotif, $answer, $timeout, $eqLogic->getConfiguration('notificationRegistrationToken', null), $url, $eqLogic->getConfiguration('appVersion', 1), $optionsNotif, $critical, $eqLogic->getLogicalId());
						} else {
							mobile::notification($eqLogic->getConfiguration('notificationArn', null), $eqLogic->getConfiguration('type_mobile', null), $_options['title'], $_options['message'], $askType, $idNotif, $answer, $timeout, $eqLogic->getConfiguration('notificationRegistrationToken', null), null, $eqLogic->getConfiguration('appVersion', 1), $optionsNotif, $critical, $eqLogic->getLogicalId());
						}
					}
				} else {
					mobile::notification($eqLogic->getConfiguration('notificationArn', null), $eqLogic->getConfiguration('type_mobile', null), $_options['title'], $_options['message'], $askType, $idNotif, $answer,  $timeout, $eqLogic->getConfiguration('notificationRegistrationToken', null), null, $eqLogic->getConfiguration('appVersion', 1), $optionsNotif, $critical, $eqLogic->getLogicalId());
				}
			} else {
				log::add('mobile', 'debug', '| [ERROR] ARN non configuré ');
			}
			log::add('mobile', 'debug', '└────────────────────');
		}
	}

	/*     * **********************Getteur Setteur*************************** */
}
