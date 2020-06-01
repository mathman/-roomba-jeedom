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
require_once __DIR__  . '/../php/roomba.inc.php';

class roomba extends eqLogic {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */

    /*
     * Fonction exécutée automatiquement toutes les minutes par Jeedom
      public static function cron() {

      }
     */


    /*
     * Fonction exécutée automatiquement toutes les heures par Jeedom
      public static function cronHourly() {

      }
     */

    /*
     * Fonction exécutée automatiquement tous les jours par Jeedom
      public static function cronDaily() {

      }
     */

    public static function pull() {
		foreach (self::byType('roomba') as $eqLogic) {
			$eqLogic->refresh();
		}
	}

    public static function deamon_info() {
		$return = array();
		$return['state'] = 'nok';
		$pid_file = jeedom::getTmpFolder('roomba') . '/deamon.pid';
		if (file_exists($pid_file)) {
			if (posix_getsid(trim(file_get_contents($pid_file)))) {
				$return['state'] = 'ok';
			} else {
				shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
			}
		}
		$return['launchable'] = 'ok';
		return $return;
	}
	
	public static function deamon_start($_debug = false) {
		self::deamon_stop();
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') {
			throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
		}
		$gateway_path = dirname(__FILE__) . '/../../resources/roomba-gateway';
        $host = config::byKey('roomba::host', 'roomba');
        $user = config::byKey('roomba::login', 'roomba');
		$password = config::byKey('roomba::password', 'roomba');

		$cmd = 'node ' . $gateway_path . '/app.js ';
		$cmd .= $host . ' ';
        $cmd .= '8081 ';
		$cmd .= $user . ' ';
		$cmd .= $password . ' ';
		$cmd .= jeedom::getTmpFolder('roomba') . '/deamon.pid';
		
		log::add('roomba', 'info', 'Lancement démon roomba : ' . $cmd);
		exec($cmd . ' >> ' . log::getPathToLog('roomba') . ' 2>&1 &');
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
			log::add('roomba', 'error', 'Impossible de lancer le démon roomba', 'unableStartDeamon');
			return false;
		}
		message::removeAll('roomba', 'unableStartDeamon');
		log::add('roomba', 'info', 'Démon roomba lancé');
	}
	
	public static function deamon_stop() {
		try {
			$deamon_info = self::deamon_info();
			if ($deamon_info['state'] == 'ok') {
				try {
					roombaRequest('/stop');
				} catch (Exception $e) {
					
				}
			}
			$pid_file = jeedom::getTmpFolder('roomba') . '/deamon.pid';
			if (file_exists($pid_file)) {
				$pid = intval(trim(file_get_contents($pid_file)));
				system::kill($pid);
			}
			sleep(1);
		} catch (\Exception $e) {
			
		}
	}
	
	public static function dependancy_info($_refresh = false) {
		$return = array();
		$return['log'] = 'roomba_update';
		$return['progress_file'] = jeedom::getTmpFolder('roomba') . '/dependance';
		$return['state'] = (self::compilationOk()) ? 'ok' : 'nok';
		return $return;
	}

	public static function dependancy_install() {
		log::remove(__CLASS__ . '_update');
		return array('script' => dirname(__FILE__) . '/../../resources/install_#stype#.sh ' . jeedom::getTmpFolder('roomba') . '/dependance', 'log' => log::getPathToLog(__CLASS__ . '_update'));
	}
	
	public static function compilationOk() {
		if (shell_exec('ls /usr/bin/node 2>/dev/null | wc -l') == 0) {
			return false;
		}
		return true;
	}

    /*     * *********************Méthodes d'instance************************* */
    
    public function refresh() {
        if ($this->getIsEnable()) {
            $eqpNetwork = eqLogic::byTypeAndSearhConfiguration('networks', config::byKey('roomba::host', 'roomba'))[0];
            if (is_object($eqpNetwork)) {
                $statusCmd = $eqpNetwork->getCmd(null, 'ping');
                if (is_object($statusCmd) && $statusCmd->execCmd() == $statusCmd->formatValue(true)) {
                    $device_data = roombaRequest('info');
                    
                    $this->batteryStatus($device_data->batPct);
                    $cmdlogic = $this->getCmd(null,'battery');
                    if (is_object($cmdlogic)) {
                        if ($cmdlogic->formatValue($device_data->batPct) != $cmdlogic->execCmd()) {
                            $cmdlogic->setCollectDate('');
                            $cmdlogic->event($device_data->batPct);
                        }
                    }
                    
                    $cmdlogic = $this->getCmd(null,'status');
                    if (is_object($cmdlogic)) {
                        if ($cmdlogic->formatValue($device_data->cleanMissionStatus->phase) != $cmdlogic->execCmd()) {
                            $cmdlogic->setCollectDate('');
                            $cmdlogic->event($device_data->cleanMissionStatus->phase);
                        }
                    }

                    $cmdlogic = $this->getCmd(null,'binFull');
                    if (is_object($cmdlogic)) {
                        if ($cmdlogic->formatValue($device_data->bin->full) != $cmdlogic->execCmd()) {
                            $cmdlogic->setCollectDate('');
                            $cmdlogic->event($device_data->bin->full);
                        }
                    }

                    $cmdlogic = $this->getCmd(null,'binPresent');
                    if (is_object($cmdlogic)) {
                        if ($cmdlogic->formatValue($device_data->bin->present) != $cmdlogic->execCmd()) {
                            $cmdlogic->setCollectDate('');
                            $cmdlogic->event($device_data->bin->present);
                        }
                    }

                    $cmdlogic = $this->getCmd(null,'cycle');
                    if (is_object($cmdlogic)) {
                        if ($cmdlogic->formatValue($device_data->cleanMissionStatus->cycle) != $cmdlogic->execCmd()) {
                            $cmdlogic->setCollectDate('');
                            $cmdlogic->event($device_data->cleanMissionStatus->cycle);
                        }
                    }

                    $cmdlogic = $this->getCmd(null,'dockKnown');
                    if (is_object($cmdlogic)) {
                        if ($cmdlogic->formatValue($device_data->dock->known) != $cmdlogic->execCmd()) {
                            $cmdlogic->setCollectDate('');
                            $cmdlogic->event($device_data->dock->known);
                        }
                    }

                    $cmdlogic = $this->getCmd(null,'nMssn');
                    if (is_object($cmdlogic)) {
                        if ($cmdlogic->formatValue($device_data->bbmssn->nMssn) != $cmdlogic->execCmd()) {
                            $cmdlogic->setCollectDate('');
                            $cmdlogic->event($device_data->bbmssn->nMssn);
                        }
                    }

                    $cmdlogic = $this->getCmd(null,'nMssnOk');
                    if (is_object($cmdlogic)) {
                        if ($cmdlogic->formatValue($device_data->bbmssn->nMssnOk) != $cmdlogic->execCmd()) {
                            $cmdlogic->setCollectDate('');
                            $cmdlogic->event($device_data->bbmssn->nMssnOk);
                        }
					}

                    $cmdOnDock = $this->getCmd(null,'hOnDock');
                    if (is_object($cmdOnDock)) {
                        if ($cmdOnDock->formatValue($device_data->bbchg3->hOnDock) != $cmdOnDock->execCmd()) {
                            $cmdOnDock->setCollectDate('');
                            $cmdOnDock->event($device_data->bbchg3->hOnDock);
                        }
                    }

                    $cmdlogic = $this->getCmd(null,'hrRun');
                    if (is_object($cmdlogic)) {
                        if ($cmdlogic->formatValue($device_data->bbrun->hr) != $cmdlogic->execCmd()) {
                            $cmdlogic->setCollectDate('');
                            $cmdlogic->event($device_data->bbrun->hr);
                        }
                    }

                    $cmdlogic = $this->getCmd(null,'hrSys');
                    if (is_object($cmdlogic)) {
                        if ($cmdlogic->formatValue($device_data->bbsys->hr) != $cmdlogic->execCmd()) {
                            $cmdlogic->setCollectDate('');
                            $cmdlogic->event($device_data->bbsys->hr);
                        }
                    }

                    $cmdlogic = $this->getCmd(null,'nScrubs');
                    if (is_object($cmdlogic)) {
                        if ($cmdlogic->formatValue($device_data->bbrun->nScrubs) != $cmdlogic->execCmd()) {
                            $cmdlogic->setCollectDate('');
                            $cmdlogic->event($device_data->bbrun->nScrubs);
                        }
                    }

                    $refresh = $this->getCmd(null, 'updatetime');
                    if (is_object($refresh)) {
                        $refresh->event(date("d/m/Y H:i",(time())));
                    }

                    $mc = cache::byKey('roombaWidgetmobile' . $this->getId());
                    $mc->remove();
                    $mc = cache::byKey('roombaWidgetdashboard' . $this->getId());
                    $mc->remove();
                    $this->toHtml('mobile');
                    $this->toHtml('dashboard');
                    $this->refreshWidget();
                }
            }
        }
    }

    public function preInsert() {
        
    }

    public function postInsert() {
        
    }

    public function preSave() {
        
    }

    public function postSave() {
        
    }

    public function preUpdate() {
        $this->setLogicalId(config::byKey('roomba::login', 'roomba', ''));
    }

    public function postUpdate() {
        if ( $this->getIsEnable() )
		{
            $refresh = $this->getCmd(null, 'refresh');
            if (!is_object($refresh)) {
                $refresh = new roombaCmd();
            }
            $refresh->setName('Rafraichir');
            $refresh->setOrder(0);
            $refresh->setEqLogic_id($this->getId());
            $refresh->setLogicalId('refresh');
            $refresh->setType('action');
            $refresh->setSubType('other');
            $refresh->save();

            $cmdlogic = $this->getCmd(null,'binPresent');
            if (!is_object($cmdlogic)) {
                $cmdlogic = new roombaCmd();
            }
            $cmdlogic->setName('Bac présent');
            $cmdlogic->setEqLogic_id($this->getId());
            $cmdlogic->setLogicalId('binPresent');
            $cmdlogic->setDisplay('generic_type', 'GENERIC_INFO');
			$cmdlogic->setOrder(1);
            $cmdlogic->setType('info');
            $cmdlogic->setSubType('binary');
            $cmdlogic->setIsHistorized(1);
            $cmdlogic->save();
            
            $cmdlogic = $this->getCmd(null,'binFull');
            if (!is_object($cmdlogic)) {
                $cmdlogic = new roombaCmd();
            }
            $cmdlogic->setName('Bac plein');
            $cmdlogic->setEqLogic_id($this->getId());
            $cmdlogic->setLogicalId('binFull');
            $cmdlogic->setDisplay('generic_type', 'GENERIC_INFO');
			$cmdlogic->setOrder(2);
            $cmdlogic->setType('info');
            $cmdlogic->setSubType('binary');
            $cmdlogic->setIsHistorized(1);
            $cmdlogic->save();
            
            $cmdlogic = $this->getCmd(null,'dockKnown');
            if (!is_object($cmdlogic)) {
                $cmdlogic = new roombaCmd();
            }
            $cmdlogic->setName('Base connue');
            $cmdlogic->setEqLogic_id($this->getId());
            $cmdlogic->setLogicalId('dockKnown');
            $cmdlogic->setDisplay('generic_type', 'GENERIC_INFO');
			$cmdlogic->setOrder(3);
            $cmdlogic->setType('info');
            $cmdlogic->setSubType('binary');
            $cmdlogic->setIsHistorized(1);
            $cmdlogic->setDisplay('forceReturnLineAfter', '1');
            $cmdlogic->save();
            
            $cmdlogic = $this->getCmd(null,'battery');
            if (!is_object($cmdlogic)) {
                $cmdlogic = new roombaCmd();
            }
            $cmdlogic->setName('Batterie');
            $cmdlogic->setEqLogic_id($this->getId());
            $cmdlogic->setLogicalId('battery');
            $cmdlogic->setDisplay('generic_type', 'BATTERY');
            $cmdlogic->setUnite('%');
			$cmdlogic->setOrder(4);
            $cmdlogic->setType('info');
            $cmdlogic->setSubType('numeric');
            $cmdlogic->setIsHistorized(1);
            $cmdlogic->setDisplay('forceReturnLineAfter', '1');
            $cmdlogic->save();

            $cmdlogic = $this->getCmd(null,'status');
            if (!is_object($cmdlogic)) {
                $cmdlogic = new roombaCmd();
            }
            $cmdlogic->setName('Statut');
            $cmdlogic->setEqLogic_id($this->getId());
            $cmdlogic->setLogicalId('status');
            $cmdlogic->setDisplay('generic_type', 'MODE_STATE');
			$cmdlogic->setOrder(5);
            $cmdlogic->setType('info');
            $cmdlogic->setSubType('string');
            $cmdlogic->setDisplay('forceReturnLineAfter', '1');
            $cmdlogic->save();
            
            $cmdlogic = $this->getCmd(null,'cycle');
            if (!is_object($cmdlogic)) {
                $cmdlogic = new roombaCmd();
            }
            $cmdlogic->setName('Cycle');
            $cmdlogic->setEqLogic_id($this->getId());
            $cmdlogic->setLogicalId('cycle');
            $cmdlogic->setDisplay('generic_type', 'GENERIC_INFO');
			$cmdlogic->setOrder(6);
            $cmdlogic->setType('info');
            $cmdlogic->setSubType('string');
            $cmdlogic->setDisplay('forceReturnLineAfter', '1');
            $cmdlogic->save();

            $cmdlogic = $this->getCmd(null,'hrSys');
            if (!is_object($cmdlogic)) {
                $cmdlogic = new roombaCmd();
            }
            $cmdlogic->setName('Durée totale système');
            $cmdlogic->setEqLogic_id($this->getId());
            $cmdlogic->setLogicalId('hrSys');
            $cmdlogic->setDisplay('generic_type', 'GENERIC_INFO');
            $cmdlogic->setUnite('heures');
			$cmdlogic->setOrder(7);
            $cmdlogic->setType('info');
            $cmdlogic->setSubType('numeric');
			$cmdlogic->setTemplate('dashboard','tile');
            $cmdlogic->setTemplate('mobile','tile');
            $cmdlogic->save();

			$cmdlogic = $this->getCmd(null,'hOnDOck');
            if (!is_object($cmdlogic)) {
                $cmdlogic = new roombaCmd();
            }
            $cmdlogic->setName('Durée totale sur base');
            $cmdlogic->setEqLogic_id($this->getId());
            $cmdlogic->setLogicalId('hOnDOck');
            $cmdlogic->setDisplay('generic_type', 'GENERIC_INFO');
            $cmdlogic->setUnite('heures');
            $cmdlogic->setOrder(8);
            $cmdlogic->setType('info');
            $cmdlogic->setSubType('numeric');
            $cmdlogic->setTemplate('dashboard','tile');
			$cmdlogic->setTemplate('mobile','tile');
            $cmdlogic->save();

			$cmdlogic = $this->getCmd(null,'hrRun');
            if (!is_object($cmdlogic)) {
                $cmdlogic = new roombaCmd();
            }
            $cmdlogic->setName('Durée totale des tâches');
            $cmdlogic->setEqLogic_id($this->getId());
            $cmdlogic->setLogicalId('hrRun');
            $cmdlogic->setDisplay('generic_type', 'GENERIC_INFO');
            $cmdlogic->setUnite('heures');
			$cmdlogic->setOrder(9);
            $cmdlogic->setType('info');
            $cmdlogic->setSubType('numeric');
			$cmdlogic->setTemplate('dashboard','tile');
			$cmdlogic->setTemplate('mobile','tile');
            $cmdlogic->setDisplay('forceReturnLineAfter', '1');
            $cmdlogic->save();
            
            $cmdlogic = $this->getCmd(null,'nMssn');
            if (!is_object($cmdlogic)) {
                $cmdlogic = new roombaCmd();
            }
            $cmdlogic->setName('Nombre de tâches totale');
            $cmdlogic->setEqLogic_id($this->getId());
            $cmdlogic->setLogicalId('nMssn');
            $cmdlogic->setDisplay('generic_type', 'GENERIC_INFO');
            $cmdlogic->setUnite('tâches');
			$cmdlogic->setOrder(10);
            $cmdlogic->setType('info');
            $cmdlogic->setSubType('numeric');
			$cmdlogic->setTemplate('dashboard','tile');
			$cmdlogic->setTemplate('mobile','tile');
            $cmdlogic->save();
            
            $cmdlogic = $this->getCmd(null,'nMssnOk');
            if (!is_object($cmdlogic)) {
                $cmdlogic = new roombaCmd();
            }
            $cmdlogic->setName('Nombre de tâches terminées');
            $cmdlogic->setEqLogic_id($this->getId());
            $cmdlogic->setLogicalId('nMssnOk');
            $cmdlogic->setDisplay('generic_type', 'GENERIC_INFO');
            $cmdlogic->setUnite('tâches');
			$cmdlogic->setOrder(11);
            $cmdlogic->setType('info');
            $cmdlogic->setSubType('numeric');
			$cmdlogic->setTemplate('dashboard','tile');
			$cmdlogic->setTemplate('mobile','tile');
            $cmdlogic->setDisplay('forceReturnLineAfter', '1');
            $cmdlogic->save();

			$cmdlogic = $this->getCmd(null,'nScrubs');
            if (!is_object($cmdlogic)) {
                $cmdlogic = new roombaCmd();
            }
            $cmdlogic->setName('Nombre de détection de saletés');
            $cmdlogic->setEqLogic_id($this->getId());
            $cmdlogic->setLogicalId('nScrubs');
            $cmdlogic->setDisplay('generic_type', 'GENERIC_INFO');
            $cmdlogic->setUnite('saletés');
			$cmdlogic->setOrder(12);
            $cmdlogic->setType('info');
            $cmdlogic->setSubType('numeric');
			$cmdlogic->setTemplate('dashboard','tile');
			$cmdlogic->setTemplate('mobile','tile');
            $cmdlogic->setDisplay('forceReturnLineAfter', '1');
            $cmdlogic->save();
            
            $cmd = $this->getCmd(null, 'updatetime');
			if ( ! is_object($cmd)) {
				$cmd = new roombaCmd();
            }
            $cmd->setName('Dernier refresh');
            $cmd->setEqLogic_id($this->getId());
            $cmd->setLogicalId('updatetime');
			$cmd->setOrder(13);
            $cmd->setType('info');
            $cmd->setSubType('string');
            $cmd->save();
        }
    }

    public function preRemove() {
        
    }

    public function postRemove() {
        
    }

    /*
     * Non obligatoire mais permet de modifier l'affichage du widget si vous en avez besoin
      public function toHtml($_version = 'dashboard') {

      }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action après modification de variable de configuration
    public static function postConfig_<Variable>() {
    }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action avant modification de variable de configuration
    public static function preConfig_<Variable>() {
    }
     */

    /*     * **********************Getteur Setteur*************************** */
}

class roombaCmd extends cmd {
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
        $eqLogic = $this->getEqLogic();
        if (!is_object($eqLogic) || $eqLogic->getIsEnable() != 1) {
            throw new Exception(__('Equipement desactivé impossible d\éxecuter la commande : ' . $this->getHumanName(), __FILE__));
        }
		log::add('roomba','debug','get '.$this->getLogicalId());
		$option = array();
		switch ($this->getLogicalId()) {
            case "refresh":
                $eqLogic->refresh();
                return true;
		}
        return true;
    }

    /*     * **********************Getteur Setteur*************************** */
}


