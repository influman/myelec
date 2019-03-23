<?php  
           $xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\" ?>";  
	       //**********************************************************************************************************
            // V2.04 : Script de suivi de la consommation électrique
            //*************************************** ******************************************************************
            // recuperation des infos depuis la requete
            // API CONSO INSTANTANEE - VAR1
            $api_instant = getArg("apii", true, 'undefined');
            // API CONSO CUMULEE - VAR2
            $api_cumul = getArg("apic", true, 'undefined');
            // DELTA COMPTEUR REEL - VAR3
            $delta = getArg("delta", false, '0-0');
            // action
            $action = getArg("action", true, '');
            // type
            $type = getArg("type", false, '');
            // valeur passée en argument
            $arg_value = getArg("value", false, '');
			// état qui donne HP HC directement
            $eco = getArg("eco", false, '');
           // API DU PERIPHERIQUE APPELANT LE SCRIPT
            $api_script = getArg('eedomus_controller_module_id'); 
 
            $xml .= "<MYELEC>";
			if ($action == 'updatetarif' || $action == 'updateconso') {
				$maintenant = date("H").":".date("i");
				$xml .= "<APPEL>".$maintenant." ".$api_script."</APPEL>";
			}
            // LECTURE DS ECARTS DE COMPTEURS
            $delta_hp = 0;
            $delta_hc = 0;
            if (!strpos($delta, "-")) {
                $delta_global = $delta;
                if ($delta_global == '') {
                    $delta_global = 0;
                }
            } else {
                list($delta_hp, $delta_hc) = sscanf($delta, "%d-%d");
            }
            $xml .= "<DELTA_HP>".$delta_hp."</DELTA_HP>";
            $xml .= "<DELTA_HC>".$delta_hc."</DELTA_HC>";
 
			
			// voir le mode de calcul en fonction des états de compteurs disponibles
			// soit état consommation cumulée (par défaut si le deux dispos)
			// soit état consommation instantanée
			$type_cumul = false;
			$type_instant = false;
			if ($api_cumul != 'undefined' && $api_cumul != '' && $api_cumul != 'plugin.parameters.APIC') {
				$type_cumul = true;
				$api_compteur = $api_cumul;
				$xml .= "<COMPTEUR>CUMUL ".$api_compteur."</COMPTEUR>";
			}
			if ($api_instant != 'undefined' && $api_instant != '' && $api_instant != 'plugin.parameters.APII' && !$type_cumul) {
				$type_instant = true;
				$api_compteur = $api_instant;
				$xml .= "<COMPTEUR>INSTANT ".$api_compteur."</COMPTEUR>";
			}
			if (!$type_instant && !$type_cumul) {
				$xml .= "<COMPTEUR>INCONNU</COMPTEUR>";
			}
			
		// Un compteur a été paramétré
		// Initialisation des données
		if ($type_instant || $type_cumul) {
			if ($action == 'updatetarif' || $action == 'updateconso') {
            	// CHARGEMENT DES VARIABLES CODES API des périphériques Abonnement/tarif/hphc
            	// et définition du mode tarifaire en cours
            	$global = false;
            	$hp = false;
            	$hc = false;
            	$mesure = "PAS DE MESURE";
            	$api_abo = false;
            	$api_hphc = false;
				$abohphc = false;
				$aboglobal = false;
				$abo_ok = false;
            	$tarif_dev = 0;
				$abobase = '';
				//$tab_api_cpt_ok = false;
				$tab_api_cpt_init = array ("jour_hp" => 0, "jour_prec_hp" => 0, "jour_hc" => 0, "jour_prec_hc" => 0, 
								   "mois_hp" => 0, "mois_prec_hp" => 0, "mois_hc" => 0, "mois_prec_hc" => 0,
								   "annee_hp" => 0, "annee_prec_hp" => 0, "cpt_delta_hp" => 0, "annee_hc" => 0, "annee_prec_hc" => 0, "cpt_delta_hc" => 0);
            	if (loadVariable('MYELECAPI_ABO_'.$api_compteur) != '') {
				// charge le tableau des API abonnement de compteur
                    $api_abo = loadVariable('MYELECAPI_ABO_'.$api_compteur);
                    if (loadVariable('MYELEC_ABO_'.$api_compteur) != '') {
						$abobase = loadVariable('MYELEC_ABO_'.$api_compteur);
							$abo_ok = true;
							if (!strpos($abobase, "GLOBAL")) {
								$abohphc = true;
							} else {
								$aboglobal = true;
								$global = true;
								$mesure = "GLOBAL";
							}
						
					}
				}
				
				if (loadVariable('MYELECAPI_HPHC_'.$api_compteur) != '') {
					// charge le tableau des API horaires hp hc de compteur
                	$api_hphc = loadVariable('MYELECAPI_HPHC_'.$api_compteur);
                	$hphc_ok = true;
					if ($abohphc) {
						$value = getValue($api_hphc, true);
						if ($value != '') {
                            $hphc = substr($value['value'],0,2);
                            if ($hphc == 'HP') {
								$hp = true;
								$mesure ="HP";
							}
							if ($hphc == 'HC') {
								$hc = true;
								$mesure ="HC";
							}
					    }	
					}		
            	}
				
            	if (loadVariable('MYELECAPI_TARIF_'.$api_compteur) != '') {
					// charge le tableau des API tarif de compteur
                    	$api_tarif = loadVariable('MYELECAPI_TARIF_'.$api_compteur);
						$value = getValue($api_tarif, true);
                    	$tarif_dev = $value['value_text'];
				}
				
				/*if (loadVariable('MYELECAPI_CPT_'.$api_compteur) != '') {
				// charge le tableau des API des différents compeuts J, J-1...
                    $tab_api_current_cpt = loadVariable('MYELECAPI_CPT_'.$api_compteur);
                    if ($tab_api_current_cpt['jour_hp'] != 0 and $tab_api_current_cpt['mois_hp'] != 0 and $tab_api_current_cpt['annee_hp'] != 0 and $tab_api_current_cpt['jour_hc'] != 0 and $tab_api_current_cpt['mois_hc'] != 0 and $tab_api_current_cpt['annee_hc'] != 0) {
						$tab_api_cpt_ok = true;
					}
					else {
						$tab_api_current_cpt = $tab_api_cpt_init;
						saveVariable('MYELECAPI_CPT_'.$api_compteur, $tab_api_current_cpt);
					}
				}
				else {
					$tab_api_current_cpt = $tab_api_cpt_init;
					saveVariable('MYELECAPI_CPT_'.$api_compteur, $tab_api_current_cpt);
				} */
            }
			// ********************************************************************************************
            // lecture/maj des capteurs Abonnement/tarif/hphc associé à ce compteur (cumulé ou instantané)
            if ($action == 'updatetarif') {
            	// lui est un actionneur, on stocke son code API et on récupère la valeur
            	if ($type == 'abo' && $arg_value != '') {
            		if ($arg_value == 'poll') {
						if ($abo_ok) {
							$abo = $abobase;
						} else {
							$abo = "Sélectionner abonnement...";
						}
						$xml .= "<ABO>".$abo."</ABO>";
					} else {
						$abo = $arg_value;
						saveVariable('MYELECAPI_ABO_'.$api_compteur, $api_script);
						if (!strpos($abo, "kVA")) {
							$abo = '';
						} else {
							saveVariable('MYELEC_ABO_'.$api_compteur, $abo);
						}
						die();
					}
                }
                // lui est un capteur qui retourne le tarif en cours
                if ($type == 'tarif') {
					$abo = '';
					$actual_hphc = '';
					$actual_tarif = 0;
					$value = getValue($api_script);
                    if ($value != '') {
                    	$actual_tarif = $value['value'];
                    }
                    if ($abo_ok && $hphc_ok) {
                    	$value = getValue($api_abo);
						$abo = $abobase;
						if (!strpos($abo, "kVA")) {
							$abo = '';
						}
						$xml .= "<ABO>".$abo."</ABO>";
						$value = getValue($api_hphc);
						if ($value != '') {
                        	$actual_hphc = $value['value'];
                        	$xml .= "<HPHC>".$actual_hphc."</HPHC>";
						}	
                        if ($abo != '') {
							$tarif_abo = substr($abo, 0, 6);
                            $actual_tarif = $tarif_abo;
                            $tarif_abo_hphc = '';
                            if (!strpos($abo, "GLOBAL")) {
                            	$tarif_abo_hphc = '';
								if (substr($actual_hphc, 0, 2) == 'HC') { // dans l'horaire HC
									$tarif_abo_hphc = 'HC';
                                }
								if (substr($actual_hphc, 0, 2) == 'HP') { // dans l'horaire HP
                                	$tarif_abo_hphc = 'HP';
                                }
								$actual_tarif = $actual_tarif.$tarif_abo_hphc;
                            } else {
								$actual_tarif = $actual_tarif."GLOBAL";
							}
						}
					}
					saveVariable('MYELECAPI_TARIF_'.$api_compteur, $api_script);
					if ($actual_tarif == '') {
						$actual_tarif = 'En attente...';
					}
                    $xml .= "<TARIF>".$actual_tarif."</TARIF>";
                }
                // lui est un capteur qui se positionne à l'heure en cours et enregistre son code API
                if ($type == 'hphc') {
                	$api_hphc = $api_script;
                    $tab_hphc = getPeriphValueList($api_script);
                    $actual_hphc = '';
					$last_lect = '';
					$max_lect = '';
					$max_lect_value = '';
					foreach ($tab_hphc as $valeur_hphc) {
						$desclue = substr($valeur_hphc['state'], 0, 5);
						if ($desclue > $max_lect && $desclue != "99:99") {
							$max_lect = $desclue;
							$max_lect_value = $valeur_hphc['value'];
						}
                    	if ($maintenant >= $desclue) {
							if ($desclue > $last_lect) {
								$actual_hphc = $valeur_hphc['value'];
								$last_lect = $desclue;
							}
						}
                    }
                    saveVariable('MYELECAPI_HPHC_'.$api_compteur, $api_hphc);
					if ($actual_hphc == '') {
						if ($max_lect != '') {
							$actual_hphc = $max_lect_value;
						} else {
							$actual_hphc = "Non requis";
						}
					}
					if ($eco != '' && $eco != 'undefined' && $eco != 'plugin.parameters.ECO') {
						// un code api de périph donne la valeur HP ou HC
						$eco_api = getValue($eco, true);
						if ($eco_api['value'] == 'HP' || $eco_api['value_text'] == 'HP') {
							$actual_hphc = 'HP1';
						}
						if ($eco_api['value'] == 'HC' || $eco_api['value_text'] == 'HC') {
							$actual_hphc = 'HC1';
						}
					}
                    $xml .= "<HPHC>".$actual_hphc."</HPHC>";
                }
            }
			//**********************************************************************************
			// Mise à jour de la consommation
            if ($action == 'updateconso') {
            	// restitution de la valeur actuel du compteur
            	$value = getValue($api_compteur);
            	$etat_compteur = $value['value'];
            	$xml .= "<VALCOMPTEUR>".$etat_compteur."</VALCOMPTEUR>";
            	$releve_conso = 0;
            	// restitution du précédent relevé du compteur (si état cumul)
				$needupdate = false;
				if ($type_cumul) {
					$mesure .= " (CUMUL)";
					$preload = loadVariable('MYELEC_LASTRELEVE_'.$api_compteur);
					if ($preload == 0 || ($preload != '' && substr($preload, 0, 8) != "## ERROR")) {
						$dernier_releve = $preload;
					} else {
						$dernier_releve = $etat_compteur;
					}
					$xml .= "<LASTVALCOMPTEUR>".$dernier_releve."</LASTVALCOMPTEUR>";
					if ($etat_compteur < $dernier_releve) { // le compteur cumulé est plus bas que la dernière fois
						$releve_conso = round(($etat_compteur / 1000), 4); // on prend la totalité de la valeur du compteur comme consommation
						$needupdate = true;
					}
					else {
						if ($etat_compteur == $dernier_releve) { // pas de changement du compteur depuis le dernier relevé
							$releve_conso = 0;
						} else {
							$releve_conso = round((($etat_compteur - $dernier_releve) / 1000), 4); // sinon, on prend le delta de conso depuis le dernier relevé
							$needupdate = true;
						}
					}
					saveVariable('MYELEC_LASTRELEVE_'.$api_compteur, $etat_compteur);
					
					$preload = loadVariable('MYELEC_CPT_'.$api_compteur);
					if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
						$tab_cpt = $preload;					
					} else {
						$tab_cpt['hp'] = 0;
						$tab_cpt['hc'] = 0;
					}
					
				} else if ($type_instant) { // a priori des watt mesurés en 1 mn
					$mesure .= " (INSTANT)";
					if ($etat_compteur == 0) {
						$releve_conso = 0;
					} else {
						$releve_conso = round(($etat_compteur / 60000), 4);
						$needupdate = true;
					}
				}
			if ($needupdate) {	
				// chargement des mesures précédentes
				$preload = loadVariable('MYELEC_RELEVES_'.$api_compteur);
				if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
					$tab_releves = $preload;				
				} else {
					$tab_releves = array ("jour_hp" => 0.0000, "jour_hc" => 0.0000, "jour_prec_hp" => 0.0000, "jour_prec_hc" => 0.0000, 
											"mois_hp" => 0.0000, "mois_hc" => 0.0000, "mois_prec_hp" => 0.0000, "mois_prec_hc" => 0.0000, 
											"annee_hp" => 0.0000, "annee_hc" => 0.0000, "annee_prec_hp" => 0.0000, "annee_prec_hc" => 0.0000, "lastmesure" => date('d')."-00:00");
				}
				$lasttime = substr($tab_releves['lastmesure'], 3, 5);
				$lastday = substr($tab_releves['lastmesure'], 0, 2);
				$razday = false;
				$razmois = false;
				$razannee = false;
				// si dernière mesure veille
				if ($lastday != date('d')) {
					$razday = true;
					if (date('j') == 1) {
						$razmois = true;
					}
					if (date('n') == 1 && $razmois) {
						$razannee = true;
					}
				}
				
				$releve_jour_hp = $tab_releves['jour_hp'];
				$releve_jour_hc = $tab_releves['jour_hc'];
				$releve_jour_prec_hp = $tab_releves['jour_prec_hp'];
				$releve_jour_prec_hc = $tab_releves['jour_prec_hc'];
				$releve_mois_hp = $tab_releves['mois_hp'];
				$releve_mois_hc = $tab_releves['mois_hc'];
				$releve_mois_prec_hp = $tab_releves['mois_prec_hp'];
				$releve_mois_prec_hc = $tab_releves['mois_prec_hc'];
				$releve_annee_hp = $tab_releves['annee_hp'];
				$releve_annee_hc = $tab_releves['annee_hc'];
				$releve_annee_prec_hp = $tab_releves['annee_prec_hp'];
				$releve_annee_prec_hc = $tab_releves['annee_prec_hc'];
				
				// cout en kwh
				$cout = round(($releve_conso * (double)$tarif_dev), 6);
				
				$preload = loadVariable('MYELEC_COUTS_'.$api_compteur);
				if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
					$tab_couts = $preload;
				} else {
					$tab_couts = array ("jour_hp" => 0.000000, "jour_hc" => 0.000000, "jour_prec_hp" => 0.000000, "jour_prec_hc" => 0.000000, 
											"mois_hp" => 0.000000, "mois_hc" => 0.000000, "mois_prec_hp" => 0.000000, "mois_prec_hc" => 0.000000, 
											"annee_hp" => 0.000000, "annee_hc" => 0.000000, "annee_prec_hp" => 0.000000, "annee_prec_hc" => 0.000000);
				}
				
				// ajout de la consommation au compteur respectif, releve et cout
				if ($hp) {
					if ($type_cumul) {
						$tab_cpt['hp'] += $releve_conso * 1000;
					}
					$releve_jour_hp += $releve_conso;
					$tab_couts['jour_hp'] += $cout;
					$releve_mois_hp += $releve_conso;
					$tab_couts['mois_hp'] += $cout;
					$releve_annee_hp += $releve_conso;
					$tab_couts['annee_hp'] += $cout;
				}
				if ($hc) {
					if ($type_cumul) {
						$tab_cpt['hc'] += $releve_conso * 1000;
					}
					$releve_jour_hc += $releve_conso;
					$tab_couts['jour_hc'] += $cout;
					$releve_mois_hc += $releve_conso;
					$tab_couts['mois_hc'] += $cout;
					$releve_annee_hc += $releve_conso;
					$tab_couts['annee_hc'] += $cout;
				}
				
				// chargement prévisionnel annuel
				$prevannuel = "...";
				$preload = loadVariable('MYELEC_PREV_'.$api_compteur);
				if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
					$prevannuel = $preload;
				}
				// REMISES A ZERO
					
				if ($razday) {
					$nbprevcoutj = 0;
					$releve_jour_prec_hp = $releve_jour_hp;
					$prevcoutj = $tab_couts['jour_prec_hp'] + $tab_couts['jour_prec_hc'];
					if ($prevcoutj > 0) {
						$nbprevcoutj = 1;
					}
					$tab_couts['jour_prec_hp'] = $tab_couts['jour_hp'];
					$releve_jour_prec_hc = $releve_jour_hc;
					$tab_couts['jour_prec_hc'] = $tab_couts['jour_hc'];
					$prevcoutj = $prevcoutj + $tab_couts['jour_prec_hp'] + $tab_couts['jour_prec_hc'];
					$nbprevcoutj++;
					$releve_jour_hp = 0;
					$releve_jour_hc = 0;
					$tab_couts['jour_hp'] = 0;
					$tab_couts['jour_hc'] = 0;
				}
					
				if ($razmois) {
					$nbprevcout = 0;
					$releve_mois_prec_hp = $releve_mois_hp;
					$prevcout = $tab_couts['mois_prec_hp'] + $tab_couts['mois_prec_hc'];
					if ($prevcout > 0) {
						$nbprevcout = 1;
					}
					$tab_couts['mois_prec_hp'] = $tab_couts['mois_hp'];
					$releve_mois_prec_hc = $releve_mois_hc;
					$tab_couts['mois_prec_hc'] = $tab_couts['mois_hc'];
					$prevcout = $prevcout + $tab_couts['mois_prec_hp'] + $tab_couts['mois_prec_hc'];
					$nbprevcout++;
					$releve_mois_hp = 0;
					$releve_mois_hc = 0;
					$tab_couts['mois_hp'] = 0;
					$tab_couts['mois_hc'] = 0;
				}
				if ($razannee) {
					$releve_annee_prec_hp = $releve_annee_hp;
					$tab_couts['annee_prec_hp'] = $tab_couts['annee_hp'];
					$releve_annee_prec_hc = $releve_annee_hc;
					$tab_couts['annee_prec_hc'] = $tab_couts['annee_hc'];
					$releve_annee_hp = 0;
					$releve_annee_hc = 0;
					$tab_couts['annee_hp'] = 0;
					$tab_couts['annee_hc'] = 0;
				}
				$tab_releves['jour_hp'] = $releve_jour_hp;
				$tab_releves['jour_hc'] = $releve_jour_hc;
				$tab_releves['jour_prec_hp'] = $releve_jour_prec_hp;
				$tab_releves['jour_prec_hc'] = $releve_jour_prec_hc;
				$tab_releves['mois_hp'] = $releve_mois_hp;
				$tab_releves['mois_hc'] = $releve_mois_hc;
				$tab_releves['mois_prec_hp'] = $releve_mois_prec_hp;
				$tab_releves['mois_prec_hc'] = $releve_mois_prec_hc;
				$tab_releves['annee_hp'] = $releve_annee_hp;
				$tab_releves['annee_hc'] = $releve_annee_hc;
				$tab_releves['annee_prec_hp'] = $releve_annee_prec_hp;
				$tab_releves['annee_prec_hc'] = $releve_annee_prec_hc;
				$tab_releves['lastmesure'] = date('d')."-".$maintenant;
				saveVariable('MYELEC_RELEVES_'.$api_compteur, $tab_releves);
				saveVariable('MYELEC_COUTS_'.$api_compteur, $tab_couts);
				if ($type_cumul) {
					saveVariable('MYELEC_CPT_'.$api_compteur, $tab_cpt);
				}
				
				if ($hp) {
					$mesure .= " ".$releve_jour_hp." kwh";
				}
				if ($hc) {
					$mesure .= " ".$releve_jour_hc." kwh";
				}
				$prevannuel = "...";
				if ($nbprevcoutj > 0 && $prevannuel == "...") {
					$prevannuel = round($prevcoutj * 365 / $nbprevcoutj,2);
				}
				if ($nbprevcout > 0) {
					$prevannuel = round($prevcout * 12 / $nbprevcout,2);
					
				}
				saveVariable('MYELEC_PREV_'.$api_compteur, $prevannuel);
				$mesure .= " (prev. ".$prevannuel." eur/an)";
			} // needupdate
				$xml .= "<STATUT>".$mesure."</STATUT>";
					
				// Mise à jour hors polling des compteurs J, J-1...
				/* if ($tab_api_cpt_ok) {
					setValue($tab_api_current_cpt['jour_hp'], round($releve_jour_hp,3)."kWh (".round($tab_couts['jour_hp'],3)."eur", $update_only = true);
					setValue($tab_api_current_cpt['jour_hc'], round($releve_jour_hc,3)."kWh (".round($tab_couts['jour_hc'],3)."eur", $update_only = true);
					setValue($tab_api_current_cpt['mois_hp'], round($releve_mois_hp,3)."kWh (".round($tab_couts['mois_hp'],3)."eur", $update_only = true);
					setValue($tab_api_current_cpt['mois_hc'], round($releve_mois_hc,3)."kWh (".round($tab_couts['mois_hc'],3)."eur", $update_only = true);
					setValue($tab_api_current_cpt['annee_hp'], round($releve_annee_hp,3)."kWh (".round($tab_couts['annee_hp'],3)."eur", $update_only = true);
					setValue($tab_api_current_cpt['annee_hc'], round($releve_annee_hc,3)."kWh (".round($tab_couts['annee_hc'],3)."eur", $update_only = true);
					if ($tab_api_current_cpt['jour_prec_hp'] != 0) {
						setValue($tab_api_current_cpt['jour_prec_hp'], round($releve_jour_prec_hp,3)."kWh (".round($tab_couts['jour_prec_hp'],3)."eur", $update_only = true);
					}
					if ($tab_api_current_cpt['mois_prec_hp'] != 0) {
						setValue($tab_api_current_cpt['mois_prec_hp'], round($releve_mois_prec_hp,3)."kWh (".round($tab_couts['mois_prec_hp'],3)."eur", $update_only = true);
					}
					if ($tab_api_current_cpt['annee_prec_hp'] != 0) {
						setValue($tab_api_current_cpt['annee_prec_hp'], round($releve_annee_prec_hp,3)."kWh (".round($tab_couts['annee_prec_hp'],3)."eur", $update_only = true);
					}
					if ($tab_api_current_cpt['cpt_delta_hp'] != 0) {
							setValue($tab_api_current_cpt['cpt_delta_hp'], $tab_cpt['hp'] + $delta_hp, $update_only = true);
					}
					if ($tab_api_current_cpt['jour_prec_hc'] != 0) {
						setValue($tab_api_current_cpt['jour_prec_hc'], round($releve_jour_prec_hc,3)."kWh (".round($tab_couts['jour_prec_hc'],3)."eur", $update_only = true);
					}
					if ($tab_api_current_cpt['mois_prec_hc'] != 0) {
						setValue($tab_api_current_cpt['mois_prec_hc'], round($releve_mois_prec_hc,3)."kWh (".round($tab_couts['mois_prec_hc'],3)."eur", $update_only = true);
					}
					if ($tab_api_current_cpt['annee_prec_hc'] != 0) {
						setValue($tab_api_current_cpt['annee_prec_hc'], round($releve_annee_prec_hc,3)."kWh (".round($tab_couts['annee_prec_hc'],3)."eur", $update_only = true);
					}
					if ($tab_api_current_cpt['cpt_delta_hc'] != 0) {
						setValue($tab_api_current_cpt['cpt_delta_hc'], $tab_cpt['hc'] + $delta_hc, $update_only = true);
					}
		       	} */
            }	
	    } else if ($action == 'updateconso') {
	    		$xml .= "<STATUT>En attente compteur...</STATUT>";
	    } else if ($action == 'updatetarif' ) {
	    		if ($type == 'abo') {
	    			$xml .= "<ABO>En attente compteur...</ABO>";
	    		}
	    		if ($type == 'hphc') {
	    			$xml .= "<HPHC>En attente compteur...</HPHC>";
	    		}
	    		if ($type == 'tarif') {
	    			$xml .= "<TARIF>En attente compteur...</TARIF>";
	    		}
		}
		// ***********************************************************************************
        // lecture des capteurs
        if ($action == 'read') {
          	$cpt_hp = $delta_hp;
           	$cpt_hc = $delta_hc;
           	$tab_init = array ("jour_hp" => 0.0000, "jour_hc" => 0.0000, "jour_prec_hp" => 0.0000, "jour_prec_hc" => 0.0000, 
								"mois_hp" => 0.0000, "mois_hc" => 0.0000, "mois_prec_hp" => 0.0000, "mois_prec_hc" => 0.0000, 
								"annee_hp" => 0.0000, "annee_hc" => 0.0000, "annee_prec_hp" => 0.0000, "annee_prec_hc" => 0.0000, "lastmesure" => date('d')."-00:00");
			
			// restitution de la valeur actuel du compteur
			$preload = loadVariable('MYELEC_RELEVES_'.$api_compteur);
			if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
               	$tab_init = $preload;
            }			
           	
           	$xml .= "<JOUR_HP>".round($tab_init['jour_hp'],3)."</JOUR_HP>";
           	$xml .= "<JOUR_HC>".round($tab_init['jour_hc'],3)."</JOUR_HC>";
           	$xml .= "<MOIS_HP>".round($tab_init['mois_hp'],3)."</MOIS_HP>";
           	$xml .= "<MOIS_HC>".round($tab_init['mois_hc'],3)."</MOIS_HC>";
           	$xml .= "<ANNEE_HP>".round($tab_init['annee_hp'],3)."</ANNEE_HP>";
           	$xml .= "<ANNEE_HC>".round($tab_init['annee_hc'],3)."</ANNEE_HC>";
           	$xml .= "<ANNEE_PREC_HP>".round($tab_init['annee_prec_hp'],3)."</ANNEE_PREC_HP>";
           	$xml .= "<ANNEE_PREC_HC>".round($tab_init['annee_prec_hc'],3)."</ANNEE_PREC_HC>";
           	$xml .= "<JOUR_PREC_HP>".round($tab_init['jour_prec_hp'],3)."</JOUR_PREC_HP>";
           	$xml .= "<JOUR_PREC_HC>".round($tab_init['jour_prec_hc'],3)."</JOUR_PREC_HC>";
			$xml .= "<MOIS_PREC_HP>".round($tab_init['mois_prec_hp'],3)."</MOIS_PREC_HP>";
           	$xml .= "<MOIS_PREC_HC>".round($tab_init['mois_prec_hc'],3)."</MOIS_PREC_HC>";
				
           	if ($type_cumul) {
				$preload = loadVariable('MYELEC_CPT_'.$api_compteur);
				if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
					$tab_cpt = $preload;
					$cpt_hp = $tab_cpt['hp'] + $delta_hp;
					$cpt_hc = $tab_cpt['hc'] + $delta_hc;
				}
           	}
           	$xml .= "<CPT_DELTA_HP>".$cpt_hp."</CPT_DELTA_HP>";
           	$xml .= "<CPT_DELTA_HC>".$cpt_hc."</CPT_DELTA_HC>";
           	
           	$tab_initc = array ("jour_hp" => 0.000000, "jour_hc" => 0.000000, "jour_prec_hp" => 0.000000, "jour_prec_hc" => 0.000000, 
								"mois_hp" => 0.000000, "mois_hc" => 0.000000, "mois_prec_hp" => 0.000000, "mois_prec_hc" => 0.000000, 
								"annee_hp" => 0.000000, "annee_hc" => 0.000000, "annee_prec_hp" => 0.000000, "annee_prec_hc" => 0.000000);		
			
           	// restitution de la valeur actuel des couts
			$preload = loadVariable('MYELEC_COUTS_'.$api_compteur);
			if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
				$tab_initc = $preload;
            }
           	
           	$xml .= "<JOUR_HPC>".round($tab_initc['jour_hp'],3)."</JOUR_HPC>";
           	$xml .= "<JOUR_HCC>".round($tab_initc['jour_hc'],3)."</JOUR_HCC>";
           	$xml .= "<MOIS_HPC>".round($tab_initc['mois_hp'],3)."</MOIS_HPC>";
           	$xml .= "<MOIS_HCC>".round($tab_initc['mois_hc'],3)."</MOIS_HCC>";
           	$xml .= "<ANNEE_HPC>".round($tab_initc['annee_hp'],3)."</ANNEE_HPC>";
           	$xml .= "<ANNEE_HCC>".round($tab_initc['annee_hc'],3)."</ANNEE_HCC>";
           	$xml .= "<ANNEE_PREC_HPC>".round($tab_initc['annee_prec_hp'],3)."</ANNEE_PREC_HPC>";
           	$xml .= "<ANNEE_PREC_HCC>".round($tab_initc['annee_prec_hc'],3)."</ANNEE_PREC_HCC>";
           	$xml .= "<JOUR_PREC_HPC>".round($tab_initc['jour_prec_hp'],3)."</JOUR_PREC_HPC>";
           	$xml .= "<JOUR_PREC_HCC>".round($tab_initc['jour_prec_hc'],3)."</JOUR_PREC_HCC>";
			$xml .= "<MOIS_PREC_HPC>".round($tab_initc['mois_prec_hp'],3)."</MOIS_PREC_HPC>";
           	$xml .= "<MOIS_PREC_HCC>".round($tab_initc['mois_prec_hc'],3)."</MOIS_PREC_HCC>";
			
			/*if ($arg_value != '') {
				$preload = loadVariable('MYELECAPI_CPT_'.$api_compteur);
				if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
					// charge le tableau des API des différents compeurs J, J-1...
                    $tab_api_current_cpt = $preload;
				    $maj_tab_cpt = false;
                   	if ($arg_value == "jour_hp" and $tab_api_current_cpt['jour_hp'] != $api_script) {
						$tab_api_current_cpt['jour_hp'] = $api_script;
						$maj_tab_cpt = true;
					}
					if ($arg_value == "mois_hp" and $tab_api_current_cpt['mois_hp'] != $api_script) {
						$tab_api_current_cpt['mois_hp'] = $api_script;
						$maj_tab_cpt = true;
					}
					if ($arg_value == "annee_hp" and $tab_api_current_cpt['annee_hp'] != $api_script) {
						$tab_api_current_cpt['annee_hp'] = $api_script;
						$maj_tab_cpt = true;
					}		
					if ($arg_value == "jour_prec_hp" and $tab_api_current_cpt['jour_prec_hp'] != $api_script) {
						$tab_api_current_cpt['jour_prec_hp'] = $api_script;
						$maj_tab_cpt = true;
					}
					if ($arg_value == "mois_prec_hp" and $tab_api_current_cpt['mois_prec_hp'] != $api_script) {
						$tab_api_current_cpt['mois_prec_hp'] = $api_script;
						$maj_tab_cpt = true;
					}
					if ($arg_value == "annee_prec_hp" and $tab_api_current_cpt['annee_prec_hp'] != $api_script) {
						$tab_api_current_cpt['annee_prec_hp'] = $api_script;
						$maj_tab_cpt = true;
					}
					if ($arg_value == "cpt_delta_hp" and $tab_api_current_cpt['cpt_delta_hp'] != $api_script) {
						$tab_api_current_cpt['cpt_delta_hp'] = $api_script;
						$maj_tab_cpt = true;
					}
					if ($arg_value == "jour_hc" and $tab_api_current_cpt['jour_hc'] != $api_script) {
						$tab_api_current_cpt['jour_hc'] = $api_script;
						$maj_tab_cpt = true;
					}
					if ($arg_value == "mois_hc" and $tab_api_current_cpt['mois_hc'] != $api_script) {
						$tab_api_current_cpt['mois_hc'] = $api_script;
						$maj_tab_cpt = true;
					}
					if ($arg_value == "annee_hc" and $tab_api_current_cpt['annee_hc'] != $api_script) {
						$tab_api_current_cpt['annee_hc'] = $api_script;
						$maj_tab_cpt = true;
					}		
					if ($arg_value == "jour_prec_hc" and $tab_api_current_cpt['jour_prec_hc'] != $api_script) {
						$tab_api_current_cpt['jour_prec_hc'] = $api_script;
						$maj_tab_cpt = true;
					}
					if ($arg_value == "mois_prec_hc" and $tab_api_current_cpt['mois_prec_hc'] != $api_script) {
						$tab_api_current_cpt['mois_prec_hc'] = $api_script;
						$maj_tab_cpt = true;
					}
					if ($arg_value == "annee_prec_hc" and $tab_api_current_cpt['annee_prec_hc'] != $api_script) {
						$tab_api_current_cpt['annee_prec_hc'] = $api_script;
						$maj_tab_cpt = true;
					}
					if ($arg_value == "cpt_delta_hc" and $tab_api_current_cpt['cpt_delta_hc'] != $api_script) {
						$tab_api_current_cpt['cpt_delta_hc'] = $api_script;
						$maj_tab_cpt = true;
					}
					if ($maj_tab_cpt) {
						saveVariable('MYELECAPI_CPT_'.$api_compteur, $tab_api_current_cpt);
					}
				}
			} */
        }
	    // ***********************************************************************************
        // mise à zéro
        if ($action == 'raz') {
			if ($type_instant || $type_cumul) {
				$tab_init = array ("jour_hp" => 0.0000, "jour_hc" => 0.0000, "jour_prec_hp" => 0.0000, "jour_prec_hc" => 0.0000, 
															 "mois_hp" => 0.0000, "mois_hc" => 0.0000, "mois_prec_hp" => 0.0000, "mois_prec_hc" => 0.0000, 
															 "annee_hp" => 0.0000, "annee_hc" => 0.0000, "annee_prec_hp" => 0.0000, "annee_prec_hc" => 0.0000, "lastmesure" => date('d')."-00:00");
				$tab_initc = array ("jour_hp" => 0.000000, "jour_hc" => 0.000000, "jour_prec_hp" => 0.000000, "jour_prec_hc" => 0.000000, 
															 "mois_hp" => 0.000000, "mois_hc" => 0.000000, "mois_prec_hp" => 0.000000, "mois_prec_hc" => 0.000000, 
															 "annee_hp" => 0.000000, "annee_hc" => 0.000000, "annee_prec_hp" => 0.000000, "annee_prec_hc" => 0.000000);
			
				saveVariable('MYELEC_RELEVES_'.$api_compteur, $tab_init);
            	saveVariable('MYELEC_COUTS_'.$api_compteur, $tab_initc);
            	saveVariable('MYELEC_LASTRELEVE_'.$api_compteur, 0);
				
				$preload = loadVariable('MYELEC_CPT_'.$api_compteur);
				if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
					$tab_cpt = $preload;
					$tab_cpt['hp'] = 0;
					$tab_cpt['hc'] = 0;
					saveVariable('MYELEC_CPT_'.$api_compteur, $tab_cpt);
				}	
				$preload = loadVariable('MYELEC_PREV_'.$api_compteur);
				if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
					saveVariable('MYELEC_PREV_'.$api_compteur, "...");
				}
				die();
           	} 
		}
		
		// mise à jour manuelle
        if ($action == 'maj') {
				$tab_reinit = array ("jour_hp" => 0.0000, "jour_hc" => 0.0000, "jour_prec_hp" => 0.0000, "jour_prec_hc" => 0.0000, 
																 "mois_hp" => 0.0000, "mois_hc" => 0.0000, "mois_prec_hp" => 0.0000, "mois_prec_hc" => 0.0000, 
																 "annee_hp" => 0.0000, "annee_hc" => 0.0000, "annee_prec_hp" => 0.0000, "annee_prec_hc" => 0.0000, "lastmesure" => date('d')."-00:00");
				$tab_reinitc = array ("jour_hp" => 0.000000, "jour_hc" => 0.000000, "jour_prec_hp" => 0.000000, "jour_prec_hc" => 0.000000, 
																 "mois_hp" => 0.000000, "mois_hc" => 0.000000, "mois_prec_hp" => 0.000000, "mois_prec_hc" => 0.000000, 
																 "annee_hp" => 0.000000, "annee_hc" => 0.000000, "annee_prec_hp" => 0.000000, "annee_prec_hc" => 0.000000);		
				$xml .= "<MAJ>".$type." - ".$arg_value."</MAJ>";
				$preload = loadVariable('MYELEC_RELEVES_'.$api_compteur);
				if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
					$tab_reinit= $preload;
					$type = strtoupper($type);
					if ($type == 'JOUR_HP' && $arg_value != "") {
						$tab_reinit['jour_hp'] = $arg_value;
						$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
					}
					if ($type == 'JOUR_PREC_HP' && $arg_value != "") {
						$tab_reinit['jour_prec_hp'] = $arg_value;
						$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
					}
					if ($type == 'MOIS_HP' && $arg_value != "") {
						$tab_reinit['mois_hp'] = $arg_value;
						$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
					}
					if ($type == 'MOIS_PREC_HP' && $arg_value != "") {
						$tab_reinit['mois_prec_hp'] = $arg_value;
						$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
					}
					if ($type == 'ANNEE_HP' && $arg_value != "") {
						$tab_reinit['annee_hp'] = $arg_value;
						$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
					}
					if ($type == 'ANNEE_PREC_HP' && $arg_value != "") {
						$tab_reinit['annee_prec_hp'] = $arg_value;
						$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
					}
					if ($type == 'JOUR_HC' && $arg_value != "") {
						$tab_reinit['jour_hc'] = $arg_value;
						$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
					}
					if ($type == 'JOUR_PREC_HC' && $arg_value != "") {
						$tab_reinit['jour_prec_hc'] = $arg_value;
						$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
					}
					if ($type == 'MOIS_HC' && $arg_value != "") {
						$tab_reinit['mois_hc'] = $arg_value;
						$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
					}
					if ($type == 'MOIS_PREC_HC' && $arg_value != "") {
						$tab_reinit['mois_prec_hc'] = $arg_value;
						$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
					}
					if ($type == 'ANNEE_HC' && $arg_value != "") {
						$tab_reinit['annee_hc'] = $arg_value;
						$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
					}
					if ($type == 'ANNEE_PREC_HC' && $arg_value != "") {
						$tab_reinit['annee_prec_hc'] = $arg_value;
						$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
					}
							
					saveVariable('MYELEC_RELEVES_'.$api_compteur, $tab_reinit);
						
            	}	
					
				$preload = loadVariable('MYELEC_COUTS_'.$api_compteur);
				if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {	
					$tab_reinitc = $preload;
					$type = strtoupper($type);
					if ($type == 'JOUR_HPC' && $arg_value != "") {
						$tab_reinitc['jour_hp'] = $arg_value;
						$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
					}
					if ($type == 'JOUR_PREC_HPC' && $arg_value != "") {
						$tab_reinitc['jour_prec_hp'] = $arg_value;
						$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
					}
					if ($type == 'MOIS_HPC' && $arg_value != "") {
						$tab_reinitc['mois_hp'] = $arg_value;
						$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
					}
					if ($type == 'MOIS_PREC_HPC' && $arg_value != "") {
						$tab_reinitc['mois_prec_hp'] = $arg_value;
						$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
					}
					if ($type == 'ANNEE_HPC' && $arg_value != "") {
						$tab_reinitc['annee_hp'] = $arg_value;
						$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
					}
					if ($type == 'ANNEE_PREC_HPC' && $arg_value != "") {
						$tab_reinitc['annee_prec_hp'] = $arg_value;
						$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
					}
					if ($type == 'JOUR_HCC' && $arg_value != "") {
						$tab_reinitc['jour_hc'] = $arg_value;
						$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
					}
					if ($type == 'JOUR_PREC_HCC' && $arg_value != "") {
						$tab_reinitc['jour_prec_hc'] = $arg_value;
						$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
					}
					if ($type == 'MOIS_HCC' && $arg_value != "") {
						$tab_reinitc['mois_hc'] = $arg_value;
						$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
					}
					if ($type == 'MOIS_PREC_HCC' && $arg_value != "") {
						$tab_reinitc['mois_prec_hc'] = $arg_value;
						$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
					}
					if ($type == 'ANNEE_HCC' && $arg_value != "") {
						$tab_reinitc['annee_hc'] = $arg_value;
						$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
					}
					if ($type == 'ANNEE_PREC_HCC' && $arg_value != "") {
						$tab_reinitc['annee_prec_hc'] = $arg_value;
						$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
					}
					saveVariable('MYELEC_COUTS_'.$api_compteur, $tab_reinitc);
					
            	}	
            					
				
		} 
	    // migration v1 à v2
        if ($action == 'migrate') {
			$tab_reinit = array ("jour_hp" => 0.0000, "jour_hc" => 0.0000, "jour_prec_hp" => 0.0000, "jour_prec_hc" => 0.0000, 
																 "mois_hp" => 0.0000, "mois_hc" => 0.0000, "mois_prec_hp" => 0.0000, "mois_prec_hc" => 0.0000, 
																 "annee_hp" => 0.0000, "annee_hc" => 0.0000, "annee_prec_hp" => 0.0000, "annee_prec_hc" => 0.0000, "lastmesure" => date('d')."-00:00");
			$tab_reinitc = array ("jour_hp" => 0.000000, "jour_hc" => 0.000000, "jour_prec_hp" => 0.000000, "jour_prec_hc" => 0.000000, 
																 "mois_hp" => 0.000000, "mois_hc" => 0.000000, "mois_prec_hp" => 0.000000, "mois_prec_hc" => 0.000000, 
																 "annee_hp" => 0.000000, "annee_hc" => 0.000000, "annee_prec_hp" => 0.000000, "annee_prec_hc" => 0.000000);		
				
			$preload = loadVariable('MYELEC_RELEVES');
			if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {	
				$tab_releves = $preload;
				if (array_key_exists($api_compteur, $tab_releves)) {
					$tab_reinit = $tab_releves[$api_compteur];
					saveVariable('MYELEC_RELEVES_'.$api_compteur, $tab_reinit);
					
					$preload = loadVariable('MYELEC_COUTS');
					if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {	
						$tab_couts= $preload;
						if (array_key_exists($api_compteur, $tab_couts)) {
							$tab_reinitc = $tab_couts[$api_compteur];
						}
						saveVariable('MYELEC_COUTS_'.$api_compteur, $tab_reinitc);
					}
					
					$preload = loadVariable('MYELEC_CPT');
					if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {	
						$tab_cpt = $preload;
						if (array_key_exists($api_compteur, $tab_cpt)) {
							saveVariable('MYELEC_CPT_'.$api_compteur, $tab_cpt[$api_compteur]);
						}
					} 
					
					$preload = loadVariable('MYELEC_LASTRELEVE');
					if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {	
						$tab_dernierreleve = $preload;
						if (array_key_exists($api_compteur, $tab_dernierreleve)) {
							saveVariable('MYELEC_LASTRELEVE_'.$api_compteur, $tab_dernierreleve[$api_compteur]);
						}
					} 
					$xml .= "<STATUT>MIGRATION OK</STATUT>";
				}
			}
		}
		// copy api
        if ($action == 'copy') {
				$tab_reinit = array ("jour_hp" => 0.0000, "jour_hc" => 0.0000, "jour_prec_hp" => 0.0000, "jour_prec_hc" => 0.0000, 
																 "mois_hp" => 0.0000, "mois_hc" => 0.0000, "mois_prec_hp" => 0.0000, "mois_prec_hc" => 0.0000, 
																 "annee_hp" => 0.0000, "annee_hc" => 0.0000, "annee_prec_hp" => 0.0000, "annee_prec_hc" => 0.0000, "lastmesure" => date('d')."-00:00");
				$tab_reinitc = array ("jour_hp" => 0.000000, "jour_hc" => 0.000000, "jour_prec_hp" => 0.000000, "jour_prec_hc" => 0.000000, 
																 "mois_hp" => 0.000000, "mois_hc" => 0.000000, "mois_prec_hp" => 0.000000, "mois_prec_hc" => 0.000000, 
																 "annee_hp" => 0.000000, "annee_hc" => 0.000000, "annee_prec_hp" => 0.000000, "annee_prec_hc" => 0.000000);		
				$xml .= "<COPY>".$arg_value."</COPY>";
				$preload = loadVariable('MYELEC_RELEVES_'.$arg_value);
				if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {
					$tab_reinit= $preload;
					saveVariable('MYELEC_RELEVES_'.$api_compteur, $tab_reinit);
						
            	}	
					
				$preload = loadVariable('MYELEC_COUTS_'.$arg_value);
				if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {	
					$tab_reinitc = $preload;
					saveVariable('MYELEC_COUTS_'.$api_compteur, $tab_reinitc);
				}	
				
				$preload = loadVariable('MYELEC_CPT_'.$arg_value);
				if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {	
					$tab_cpt = $preload;
					if (array_key_exists($arg_value, $tab_cpt)) {
						saveVariable('MYELEC_CPT_'.$api_compteur, $tab_cpt[$arg_value]);
					}
				} 
				$preload = loadVariable('MYELEC_LASTRELEVE_'.$arg_value);
				if ($preload != '' && substr($preload, 0, 8) != "## ERROR") {	
					$tab_dernierreleve = $preload;
					if (array_key_exists($arg_value, $tab_dernierreleve)) {
						saveVariable('MYELEC_LASTRELEVE_'.$api_compteur, $tab_dernierreleve[$arg_value]);
					}
				} 
				$xml .= "<STATUT>COPY OK</STATUT>";
        } 
	    $xml .= "</MYELEC>";
		sdk_header('text/xml');
		echo $xml;
?>
