<?php
/*
 * Wetterwarn-Bot für neuthardwetter.de by Jens Dutzi
 * Version 1.0
 * 30.11.2015
 * (c) tf-network.de Jens Dutzi 2012-2015
 *
 * Lizenzinformationen (MIT License):
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this
 * software and associated documentation files (the "Software"), to deal in the Software
 * without restriction, including without limitation the rights to use, copy, modify,
 * merge, publish, distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies
 * or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A
 * PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Ermittle Name der Warnregion anhand des Bundeslandes und der WarnCellID
 *
 * @param	$WarnInfoNode
 * @param	$warncellid
 */

function getWarnAreaNameFromCAP($WarnInfoNode, $warncellid) {
	try {
		if (! isset($WarnInfoNode->{"area"})) {
			// Keine Area-Nodes gefunden
			throw new Exception("Fehler in getWarnAreaNameFromCAP: Die XML Datei beinhaltet kein 'area'-Nodes.");
		} else {
			$areaDesc = false;
			$state = false;
			$altitude = 0;
			$ceiling = 0;

			foreach ($WarnInfoNode->{"area"} as $area) {
				// Ermittle WarnCell-ID und State
				$currentState = array();
				$currentWarnCellID = false;
				$currentAltitude = false;
				$currentCeiling = false;

				// Speichere areaDesc
				if (! isset($area->{"areaDesc"})) {
					throw new Exception("Fehler in getWarnAreaNameFromCAP: Die XML Datei beinhaltet kein 'area'->'areaDesc'-Node.");
				} else {
					$currentAreaDesc = (string)$area->{"areaDesc"};
				}

				// Speichere Höhenangaben
				if (! isset($area->{"altitude"})) {
					throw new Exception("Fehler in getWarnAreaNameFromCAP: Die XML Datei beinhaltet kein 'area'->'altitude'-Node.");
				} else {
					$currentAltitude = (float)$area->{"altitude"};
				}
				if (! isset($area->{"ceiling"})) {
					throw new Exception("Fehler in getWarnAreaNameFromCAP: Die XML Datei beinhaltet kein 'area'->'ceiling'-Node.");
				} else {
					$currentCeiling = (float)$area->{"ceiling"};
				}

				if (! isset($area->{"geocode"})) {
					throw new Exception("Fehler in getWarnAreaNameFromCAP: Die XML Datei beinhaltet kein 'area'->'geocode'-Node.");
				} else {
					// Durchlaufe beide Geocode-Einträge um Bundesland und Ort zu ermitteln
					foreach ($area->{"geocode"} as $geocode) {
						// Speichere je nach Bedarf
						if (! isset($geocode->{"valueName"}) || ! isset($geocode->{"value"})) {
							throw new Exception("Fehler in getWarnAreaNameFromCAP: Die XML Datei beinhaltet kein 'area'->'geocode'->'valueName' oder 'value>-Node.");
						} else {
							if ($geocode->{"valueName"} == "STATE") {
								$currentState[] = (string)$geocode->{"value"};
							} else if ($geocode->{"valueName"} == "WARNCELLID") {
								$currentWarnCellID = (string)$geocode->{"value"};
							}
						}
					}
				}

				// Falls beide Werte ermittelt wurden -> Prüfe ob WarnCell-ID vorkommen
				if (count($currentState) == 0 || $currentWarnCellID === false) {
					throw new Exception("Ein Area-Eintrag in der XML Datei beinhaltete keine State/WarncellID Angabe");
				} else {
					// Gehört der Landkreis/Warnzelle zu den benötigten?
					if ($warncellid == $currentWarnCellID) {
						$areaDesc = $currentAreaDesc;
						$state = $currentState;
						$altitude = $currentAltitude;
						$ceiling = $currentCeiling;
					}
				}
			}
		}

		if ($areaDesc !== false) {
			$arrReturn = array("warncellid" => $warncellid, "areaDesc" => $areaDesc, "state" => $state, "altitude" => $altitude, "ceiling" => $ceiling);
			return $arrReturn;
		} else {
			return false;
		}
	} catch (Exception $e) {
		// Fehler-Handling
		$message = $e->getFile() . "(" . $e->getLine() . "): " . $e->getMessage();
		sendErrorMessage($message);
	}
}

/**
 * Funktion zum verarbeiten der lokalen XML Wetterwarnungen
 *
 * @param	$config
 * @param	$optFehlerMail
 * @return 	bool
 */
function parseWetterWarnung($config, $optFehlerMail) {
	try {
		// Neue Wetterwarnungen vorhanden?
		$forceWetterwarungUpdate = false;

		// Lege benötigte Arrays an
		$arrWetterWarnungenJson = array();

		// Prüfe Existenz der lokalen Verzeichnisse
		if (! is_readable($config["localFolder"])) {
			throw new Exception("Fehler in parseWetterWarnung: Benötigte Verzeichnisse " . $config["localFolder"] . " ist nicht lesbar");
		}

		// Wurde alles notwendige hinterlegt?
		if (! array_key_exists("WarnCellId", $config)) {
			throw new Exception("Fehler in parseWetterWarnung: Landkreis-Parameter fehlt in der Konfiguration");
		} else {
			if (!is_numeric($config["WarnCellId"])) {
				throw new Exception("Fehler in parseWetterWarnung: WarnCellId-Parameter besteht nicht aus einer Nummer");
			}
		}

		if (! array_key_exists("localJsonWarnfile", $config)) {
			throw new Exception("Fehler in parseWetterWarnung: json-Parameter fehlt in der Konfiguration");
		} else {
			if (! is_string($config["localJsonWarnfile"])) {
				throw new Exception("Fehler in parseWetterWarnung: localJsonWarnfile-Parameter in der Konfiguration besteht nicht aus einem String");
			} else {
				echo ("-> Konfiguration: localJsonWarnfile-Parameter erfolgreich geprüft" . PHP_EOL);
			}
		}

		echo (PHP_EOL);

		// Erzeuge Array mit allen nach der Bereinigung vorhandenen Dateien
		$localZipFiles = array();
		$handle = opendir($config["localFolder"]);
		if ($handle) {
			while (false !== ($entry = readdir($handle))) {
				if (! is_dir($config["localFolder"] . DIRECTORY_SEPARATOR . $entry)) {
					$fileinfo = pathinfo($config["localFolder"] . DIRECTORY_SEPARATOR . $entry);
					if ($fileinfo["extension"] == "zip")
						$localZipFiles[] = $entry;
				}
			}
			closedir($handle);
		} else {
			throw new Exception("Fehler in parseWetterWarnung: Fehler beim durchsuchen des lokale Wetterwarnung-Ordner");
		}

		if (count($localZipFiles) > 1) {
			throw new Exception("Fehler in parseWetterWarnung: Mehr als eine Datei befindet sich im lokalen Wetterwarnung-Ordner und es dürfte nur eine vorhanden sein -> Abbruch");
		}

		// Erzeuge TMP-Ordner:
		$tmpFolder = tempdir(null, "wetterWarnung");

		echo ("* Entpacke Wetterwarnung-Datei " . $localZipFiles[0] . ": " . PHP_EOL);
		echo ("  -> Extra Dateien in Temp-Folder: " . $tmpFolder . PHP_EOL);

		// Öffne ZIP Datei
		$zip = new ZipArchive();
		$res = $zip->open($config["localFolder"] . DIRECTORY_SEPARATOR . $localZipFiles[0]);
		if ($res === true) {
			$zip->extractTo($tmpFolder);
			$zip->close();
		} else {
			throw new Exception("Fehler beim öffnen der ZIP Datei '" . $localZipFiles[0] . "'. Fehlercode: " . $res . " / " . getZipErrorMessage($res));
		}

		echo PHP_EOL;

		// Verarbeite XML Dateien im Temp-Ordner
		echo ("Prüfe XML Dateien im TMP-Ordner nach Warnungen für die hinterlegten Warnkreise:" . PHP_EOL);

		// Lese Verzeichnis mit XML Dateien ein
		$localXmlFiles = array();
		$handle = opendir($tmpFolder);
		if ($handle) {
			while (false !== ($entry = readdir($handle))) {
				if (! is_dir($tmpFolder . DIRECTORY_SEPARATOR . $entry)) {
					$fileinfo = pathinfo($tmpFolder . DIRECTORY_SEPARATOR . $entry);
					if ($fileinfo["extension"] == "xml")
						$localXmlFiles[] = $entry;
				}
			}
			closedir($handle);
		} else {
			throw new Exception("Fehler in parseWetterWarnung: Fehler beim durchsuchen des temporären Ordner mit den entpackten Wetterwarnungen.");
		}


		// Lege Array an für die ermittelten Warnungen;
		$aktuelleWarnungen = array();

		// Verarbeite jede einzelne XML Datei
		foreach ($localXmlFiles as $xmlFile) {
			echo ("* Verarbeite Wetterwarnung-Datei " . $tmpFolder . DIRECTORY_SEPARATOR . $xmlFile . ": ");

			// Prüfe ob XML Datei geöffnet werden kann
			$filename = $tmpFolder . DIRECTORY_SEPARATOR . $xmlFile;
			if (! is_readable($filename)) {
				throw new Exception("Fehler in parseWetterWarnung: Die XML Datei konnte nicht geöffnet werden.");
			}

			// Öffne XML Datei zum lesen
			try {
				$xml = new SimpleXMLElement(file_get_contents($filename));
				if (! $xml) {
					throw new Exception("Fehler in parseWetterWarnung: Die XML Datei konnte nicht verarbeitet werden.");
				}
			} catch (Exception $e) {
				throw new Exception("Fehler in parseWetterWarnung: Die XML Datei konnte nicht verarbeitet werden (" . $e->getMessage() . ")");
			}

			// Prüfe ob die Warnung nicht vom Typ "cancel" ist:
			if (! isset($xml->{"msgType"})) {
				throw new Exception("Fehler in parseWetterWarnung: Die XML Datei beinhaltet kein 'msgType'-Node.");
			} else {
				$msgType = (string)$xml->{"msgType"};
			}

			// Verarbeite XML Datei, da Typ "Alert" ist
			if (strtolower($msgType) == "alert") {
				// Verarbeite Inhalt der XML Datei
				echo ("Art der Warnung: " . $msgType . PHP_EOL);

				// Prüfe ob Info-Node existiert
				if (! isset($xml->{"info"})) {
					throw new Exception("Fehler in parseWetterWarnung: Die XML Datei beinhaltet kein 'info'-Node.");
				} else {
					$info = $xml->{"info"};
				}

				foreach ($info as $wetterWarnung) {
					// Prüfe ob es sich um eine Testwarnung handelt
					$testWarnung = false;
					if (! isset($wetterWarnung->{"eventCode"})) {
						throw new Exception("Fehler in parseWetterWarnung: Die XML Datei beinhaltet kein 'eventCode'-Node.");
					} else {
						foreach ($wetterWarnung->{"eventCode"} as $eventCode) {
							if (! isset($eventCode->{"valueName"}) || ! isset($eventCode->{"value"})) {
								throw new Exception("Fehler in parseWetterWarnung: Die XML Datei beinhaltet kein 'eventCode'->'valueName' bzw. 'value'-Node.");
							} else {
								// Schaue nach EventName = "II"
								if ((string)$eventCode->{"valueName"} == "II") {
									if ((string)$eventCode->{"value"} == "98" || (string)$eventCode->{"value"} == "99") $testWarnung = true;
								}
							}
						}
					}

					// Prüfe ob die Wetterwarnung für eine der hinterlegte Warnzelle gültig ist
					// und ermittle den Namen der WarnRegion
					$speichereWarnung = false;
					if (! $testWarnung) {
						$warnRegonFound = getWarnAreaNameFromCAP($info, $config["WarnCellId"]);
						if ($warnRegonFound !== false) {
							// Treffer gefunden und breche daher Schleife ab und schaue nicht mehr nach weiteren Landkreisen
							echo ("\t-> Treffer für " . $warnRegonFound["areaDesc"] . " / " . implode(", ", $warnRegonFound["state"])  . " (WarnCellID: " . $warnRegonFound["warncellid"] . ") gefunden." . PHP_EOL);
							$speichereWarnung = true;
						} else {
							echo ("\t-> Kein Treffer für die angegebene WarnCellID " . $config["WarnCellId"] . PHP_EOL);
						}
					} else {
						echo ("Testwarnung (ignoriere Inhalt)" . PHP_EOL);
					}

					// Warnung gültig für die hinterlegten WarnCellIDs -> daher speichern
					if ($speichereWarnung === true) {
						$aktuelleWarnungen[$xmlFile] = $xml;
					}
				}
			} else if (strtolower($msgType) == "cancel") {
				// Da es sich um eine Cancel-Nachricht handelt, diese einfach ignorieren
				// -> Bei Cancel existieren in der Regel keine Wetterwarnungen für Deutschland
				echo ("Cancel -> nicht vearbeiten" . PHP_EOL);
			} else {
				// Sichere XML Datei zu Debug-Zwecken, da es sich um ein unbekannter Warn-Typ handelt
				if(array_key_exists("localDebugFolder", $config)) {
					echo ("Update -> zu Debug-Zwecken speichern" . PHP_EOL);
					if (! file_exists($config["localDebugFolder"] . DIRECTORY_SEPARATOR . basename($filename))) {
						echo ("-> Archiviere und sende Wetterwarnung zu Debug-Zwecken an " . $optFehlerMail["absender"] . PHP_EOL);

						if (! @copy($filename, $config["localDebugFolder"] . DIRECTORY_SEPARATOR . basename($filename))) {
							throw new Exception("-> Fehler beim kopieren der Update/Cancel Wetterwarnung in den Debug Ordner");
						}

						// Stelle E-Mail zusammen
						$mailBetreff = "Debug-Mail für Wetterseite: CANCEL/UPDATE für eine Wetterwarnung";
						$mailText = "Anbei eine neue Cancel/Update Nachricht vom deutschen Wetterdienst.\r\n";
						$mailText .= "\r\n";
						$mailText .= file_get_contents($filename);

						sendmail($optFehlerMail["absender"], $optFehlerMail["empfaenger"], $mailBetreff, $mailText);
					} else {
						echo ("-> Wetterwarnung wurde bereits zu Debug-Zwecken versendet und archiviert." . PHP_EOL);
					}
				}
			}
		}

		// Verarbeite ermittelte Warnkreise
		echo (PHP_EOL);
		echo ("Verarbeite Warnmeldungen (Anzahl: " . count($aktuelleWarnungen) . "):" . PHP_EOL);

		if (count($aktuelleWarnungen) > 0) {
			foreach ($aktuelleWarnungen as $filename => $xml) {
				echo ("Wetterwarnung aus " . $filename . ":" . PHP_EOL);

				// Prüfe ob Info-Node existiert
				if (! isset($xml->{"identifier"})) {
					throw new Exception("Fehler in parseWetterWarnung: Die XML Datei beinhaltet kein 'identifier'-Node.");
				} else {
					$identifier = $xml->{"identifier"};
				}

				// Ermittle-Warnkennung
				if (! isset($xml->{"info"})) {
					throw new Exception("Fehler in parseWetterWarnung: Die XML Datei beinhaltet kein 'info'-Node.");
				} else {
					$info = $xml->{"info"};
				}

				foreach ($info as $wetterWarnung) {
					if (! isset($wetterWarnung->{"event"})) {
						throw new Exception("Fehler in parseWetterWarnung: Die XML Datei beinhaltet kein 'event'-Node.");
					} else {
						$event = (string)$wetterWarnung->{"event"};
					}

					echo ("-> Wetterwarnung-Typ: " . $event . PHP_EOL);

					// Start- und Ablaufdatum ermitteln
					if (! isset($wetterWarnung->{"onset"})) {
						throw new Exception("Fehler in parseWetterWarnung: Die XML Datei beinhaltet kein 'onset'-Node.");
					} else {
						$strRawDate = str_replace("+00:00", "", (string)$wetterWarnung->{"onset"});
						$dateOnset = DateTime::createFromFormat('Y-m-d*H:i:s', $strRawDate, new DateTimeZone("UTC"));
						if (!$dateOnset) {
							throw new Exception("Fehler in parseWetterWarnung: Der Zeitpunkt im 'onset'-Node konnte nicht verarbeitet werden.");
						} else {
							$dateOnset->setTimezone(new DateTimeZone("Europe/Berlin"));
							$onset = $dateOnset->format("d.m.Y H:i");
						}
					}

					if (! isset($wetterWarnung->{"expires"})) {
						$expires = $onset;
						$dateExpires = $dateOnset;
					} else {
						$strRawDate = str_replace("+00:00", "", (string)$wetterWarnung->{"expires"});
						$dateExpires = DateTime::createFromFormat('Y-m-d*H:i:s', $strRawDate, new DateTimeZone("UTC"));
						if (!$dateExpires) {
							throw new Exception("Fehler in parseWetterWarnung: Der Zeitpunkt im 'expires'-Node konnte nicht verarbeitet werden.");
						} else {
							$dateExpires->setTimezone(new DateTimeZone("Europe/Berlin"));
							$expires = $dateExpires->format("d.m.Y H:i");
						}
					}

					// Aktuelle Uhrzeit
					$dateCurrent = new DateTime("now", new DateTimeZone("Europe/Berlin"));

					if ($dateExpires->getTimestamp() <= $dateCurrent->getTimestamp() && $dateExpires->getTimestamp() != $dateOnset->getTimestamp()) {
						// Warnung ist bereits abgelaufen
						echo ("--> Hinweis: Warnung über " . $event . " ist bereits am " . $expires . " abgelaufen und wird ingoriert" . PHP_EOL);
					} else {
						// Warnung komplett verarbeiten, da sie aktuell ist (bzw. kein Expire-Zeitpunkt existiert)

						// Dringlichkeit
						if (! isset($wetterWarnung->{"urgency"})) {
							throw new Exception("Fehler in parseWetterWarnung: Die XML Datei beinhaltet kein 'urgency'-Node.");
						} else {
							$urgency = (string)$wetterWarnung->{"urgency"};
						}

						// Warnstufe
						if (! isset($wetterWarnung->{"severity"})) {
							throw new Exception("Fehler in parseWetterWarnung: Die XML Datei beinhaltet kein 'severity'-Node.");
						} else {
							// Severity ermitteln und auf die DWD "Sprache" übersetzen
							$severity = (string)$wetterWarnung->{"severity"};
							switch ($severity) {
								case "Minor":
									$severity = "Wetterwarnung";
									$warnstufe = 1;
									break;
								case "Moderate":
									$severity = "Markante Wetterwarnung";
									$warnstufe = 2;
									break;
								case "Severe":
									$severity = "Unwetterwarnung";
									$warnstufe = 3;
									break;
								case "Extreme":
									$severity = "Extreme Unwetterwarnung";
									$warnstufe = 4;
									break;
								case "Minor":
									$severity = "Unbekannt";
									$warnstufe = 0;
							}
						}

						// Prüfe ob es sich ausschließlich um eine Vorhersage handelt (Urgency == Future)
						if ($urgency == "Future") {
							$warnstufe = 0;
						}

						// Ermittle die Werte für das erstellen der Wetterwarnung selber
						if (! isset($wetterWarnung->{"headline"})) {
							throw new Exception("Fehler in parseWetterWarnung: Die XML Datei beinhaltet kein 'headline'-Node.");
						} else {
							$headline = (string)$wetterWarnung->{"headline"};
						}
						if (! isset($wetterWarnung->{"description"})) {
							throw new Exception("Fehler in parseWetterWarnung: Die XML Datei beinhaltet kein 'description'-Node.");
						} else {
							$description = (string)$wetterWarnung->{"description"};
						}
						if (! isset($wetterWarnung->{"instruction"})) {
							throw new Exception("Fehler in parseWetterWarnung: Die XML Datei beinhaltet kein 'instruction'-Node.");
						} else {
							$instruction = (string)$wetterWarnung->{"instruction"};
						}
						if (! isset($wetterWarnung->{"senderName"})) {
							throw new Exception("Fehler in parseWetterWarnung: Die XML Datei beinhaltet kein 'senderName'-Node.");
						} else {
							$senderName = (string)$wetterWarnung->{"senderName"};
						}

						// Warnregion ermitteln samt des ausgeschriebenen Ländernamen
						$warnRegion = getWarnAreaNameFromCAP($info, $config["WarnCellId"]);
						if ($warnRegion === false) {
							throw new Exception("Fehler in parseWetterWarnung: areaDesc konnte nicht ermittelt werden");
						} else {
							$senderName = (string)$wetterWarnung->{"senderName"};
							$areaDesc = $warnRegion["areaDesc"];
							$state =  implode(", ", $warnRegion["state"]);
						}

						// Stelle Text für Höhenlage zusamnen und rechne in Meter um
						// abrunden, anstatt wie laut CAPS Doku aufruden -> das Ergebnis passt sonst nicht zum Text
						$altitude = floor($warnRegion["altitude"] * 0.3048);
						$ceiling = floor($warnRegion["ceiling"] * 0.3048 );

						if($warnRegion["altitude"] == 0 & $warnRegion["ceiling"] != 9842.5197) {
							$hoehenangabe = "Höhenlagen unter " . $ceiling . "m";
						} else if($warnRegion["altitude"] != 0 & $warnRegion["ceiling"] == 9842.5197) {
							$hoehenangabe = "Höhenlagen über " . $altitude . "m";
						} else {
							$hoehenangabe = "Alle Höhenlagen";
						}

						// MD5Hash erzeugen aus Angaben der Wetterwarnung
						$md5Hash = md5($warnstufe . $event . $dateOnset->getTimestamp() . $dateExpires->getTimestamp() . $areaDesc . $headline . $description . $instruction);

						// Füge ales in Array zusammen
						$tempWarnArray = array( "hash" => $md5Hash,
												"severity" => $severity,
												"urgency" => $urgency,
												"warnstufe" => $warnstufe,
												"startzeit" => serialize($dateOnset),
												"endzeit" => serialize($dateExpires),
												"headline" => $headline,
												"area" => $areaDesc,
												"stateShort" => $state,
												"stateLong" => getNameFromState($state),
												"altutude" => $altitude,
												"ceiling" => $ceiling,
												"hoehenangabe" => $hoehenangabe,
												"description" => $description,
												"instruction" => $instruction,
												"event" => $event,
												"sender" => $senderName
											);

						// Wetterwarnung übergeben
						$arrWetterWarnungenJson[$md5Hash] = $tempWarnArray;
					}
				}
			}
		} else {
			echo ("-> Keine Warnmeldungen zum verarbeiten vorhanden" . PHP_EOL);
		}

		// Sortiere Warnmeldungen mittels MD5Hash der Meldung und speichere Ergebnis erneut als Array ohne Keys
		// damit keine Änderungen der Reihenfolge bei Updates der DWD-Wetterwarnung erfolgt
		asort($arrWetterWarnungenJson);
		$tmpSortedWetterWarnungJson = array();
		foreach ($arrWetterWarnungenJson as $key => $value) {
			$tmpSortedWetterWarnungJson[] = $value;
		}

		// Speichere bei Bedarf die Wetterdaten
		echo (PHP_EOL . "Beginne mit dem Speichervorgang für die Wetterwarnungen in Temporär-Dateien. " . PHP_EOL);
		echo ("-> Anzahl der WetterWarnungen: " . count($tmpSortedWetterWarnungJson) . PHP_EOL);
		echo (PHP_EOL);

		// Temporär-Dateien erzeugen
		$tmpWetterWarnung = tempnam(sys_get_temp_dir(), 'WetterWarnung');

		if (count($tmpSortedWetterWarnungJson) == 0) {
			$arrFinal = array("anzahl" => 0, "wetterwarnungen" => array());
			file_put_contents($tmpWetterWarnung, json_encode($arrFinal));
		} else {
			$arrFinal = array("anzahl" => count($tmpSortedWetterWarnungJson), "wetterwarnungen" => $tmpSortedWetterWarnungJson);
			file_put_contents($tmpWetterWarnung, json_encode($arrFinal));
		}

		// Führe Vergleich durch
		echo ("Prüfe Wetterwarnungen auf Veränderungen:" . PHP_EOL);

		$forceWetterwarungUpdate = false;

		echo ("-> Wetterwarnung: ");
		if (file_exists($config["localJsonWarnfile"])) {
			$md5wetterwarnung_alt = md5_file($config["localJsonWarnfile"]);
			$md5wetterwarnung_neu = md5_file($tmpWetterWarnung);
			echo ("-> Alt (" . $md5wetterwarnung_alt . ") | Neu (" . $md5wetterwarnung_neu . ") -> ");
			if ($md5wetterwarnung_alt != $md5wetterwarnung_neu) {
				$forceWetterwarungUpdate = true;
				echo ("Aktualisierte Wetterwarnungen vorhanden" . PHP_EOL);
				if(!@rename($tmpWetterWarnung, $config["localJsonWarnfile"])) {
					throw new Exception("Fehler in parseWetterWarnung: Json-Warndatei " . $config["localJsonWarnfile"] . " konnte nicht überrschrieben werden");
				}
			} else {
				echo ("kein Update notwendig" . PHP_EOL);
			}
		} else {
			echo ("Kein Vergleich möglich, da keine bisherige Wetter-Warnung-Datei existiert." . PHP_EOL);
			if(!@rename($tmpWetterWarnung, $config["localJsonWarnfile"])) {
				throw new Exception("Fehler in parseWetterWarnung: Json-Warndatei " . $config["localJsonWarnfile"] . " konnte nicht überrschrieben werden");
			}
		}
		if (file_exists($tmpWetterWarnung))
			@unlink($tmpWetterWarnung);


		// Lösche Temporär-Order
		array_map('unlink', glob($tmpFolder . DIRECTORY_SEPARATOR . "*.xml"));
		if (! rmdir($tmpFolder)) {
			throw new Exception("Fehler in parseWetterWarnung: Löschen des Temp-Ordner " . $tmpFolder . " fehlgeschlagen.");
		}

		return $forceWetterwarungUpdate;
	} catch (Exception $e) {
		// Lösche Temporär-Order
		if(is_empty($tmpFolder)) {
			array_map('unlink', glob($tmpFolder . DIRECTORY_SEPARATOR . "*.xml"));
			if (! rmdir($tmpFolder)) {
				$message = $e->getFile() . "(" . $e->getLine() . "): " . $e->getMessage() . " / Temporär-Ordner " . $tmpFolder . " erfolgreich gelöscht.";
			} else {
				$message = $e->getFile() . "(" . $e->getLine() . "): " . $e->getMessage() . " / Temporär-Ordner " . $tmpFolder . " konnte zusätzlich nicht gelöscht werden.";
			}
		} else {
			$message = $e->getFile() . "(" . $e->getLine() . "): " . $e->getMessage();
		}

		// Fehler-Handling
		sendErrorMessage($message);

		return false;
	}
}

/**
 * Funktion zum aufräumen des lokalen Wetterwarnungs-Ordner
 * und zum entfernen verwalteter Wetterwarnungen
 *
 * @param	$config
 * @param	$remoteFiles
 * @return	bool
 */
function cleanupUnwetterDaten($config, $remoteFiles) {
	try {
		// Prüfe Existenz der lokalen Verzeichnisse
		if (! is_readable($config["localFolder"])) {
			throw new Exception("Fehler in cleanupUnwetterDaten: Benötigte Verzeichnisse " . $config["localFolder"] . " ist nicht lesbar");
		}

		// Erzeuge Array mit allen bereits vorhandenen Dateien
		$localFiles = array();
		$handle = opendir($config["localFolder"]);
		if ($handle) {
			while (false !== ($entry = readdir($handle))) {
				if (! is_dir($config["localFolder"] . DIRECTORY_SEPARATOR . $entry)) {
					$fileinfo = pathinfo($config["localFolder"] . DIRECTORY_SEPARATOR . $entry);
					if ($fileinfo["extension"] == "zip")
						$localFiles[] = $entry;
				}
			}
			closedir($handle);
		} else {
			throw new Exception("Fehler in cleanupUnwetterDaten: Verzeichnisstruktur in " . $config["localFolder"] . " ist nicht ermittelbar");
		}

		// Ermittle anhand der Local/Remote-Dateilisten welche lokal vorhandenen
		// Dateien nicht mehr auf dem DWD FTP Server vorhanden sind
		$obsoletFiles = array_diff($localFiles, array(
				$remoteFiles
		));

		foreach ($obsoletFiles as $filename) {
			echo ("- Lösche veraltete Wetterwarnung-Datei " . $filename . ":" . PHP_EOL);

			if (! @unlink($config["localFolder"] . DIRECTORY_SEPARATOR . $filename)) {
				throw new Exception("Fehler in cleanupUnwetterDaten: Die Datei " . $filename . " konnte nicht erfolgreich gelöscht werden.");
			} else {
				echo " -> Datei wird gelöscht." . PHP_EOL;
			}
		}
	} catch (Exception $e) {
		// Fehler-Handling
		$message = $e->getFile() . "(" . $e->getLine() . "): " . $e->getMessage();
		sendErrorMessage($message);
	}
}

/**
 * Funktion zum herunterladen der für den angegebenen Landkreis vorhandenen Wetterwarnungen
 *
 * @param	$config
 * @param	$conn_id
 * @return	array
 */
function fetchUnwetterDaten($config, $conn_id) {
	try {
		// Prüfe Existenz der lokalen Verzeichnisse
		if (! is_writable($config["localFolder"])) {
			throw new Exception("Fehler in fetchUnwetterDaten: Benötigte Verzeichnisse " . $config["localFolder"] . " ist nicht beschreibbar");
		}

		// Datei-Array anlegen
		$arrDownloadList = array();

		// FileStats Cache leeren
		clearstatcache();

		$remoteFolder = $config["remoteFolder"];

		// Versuche, in das benötigte Verzeichnis zu wechseln
		if (@ftp_chdir($conn_id, $remoteFolder)) {
			echo PHP_EOL . "Verarbeite Dateien in in folgendem Verzeichnis: " . ftp_pwd($conn_id) . PHP_EOL;
		} else {
			throw new Exception("Fehler in fetchUnwetterDaten: Verzeichniswechsel in '" . $remoteFolder . "' ist fehlgeschlagen.");
		}

		// Filtern der Dateinamen um nicht für alle den Zeitstempel ermittelen zu müssen
		$searchTime = new DateTime();
		$searchTime->setTimezone(new DateTimeZone('GMT'));
		$fileFilter = $searchTime->format("Ymd");

		// Verzeichnisliste auslesen und sortieren
		$arrFTPContent = ftp_nlist($conn_id, ".");
		if ($arrFTPContent === false) {
			throw new Exception("Fehler in fetchUnwetterDaten: Auslesen des Verezichnis " . $config["remoteFolder"] . " fehlgeschlagen.");
		} else {
			echo ("-> Verzeichnisliste erfolgreich heruntergeladen" . PHP_EOL);
		}

		// Ermittle das Datum für die Dateien
		if (count($arrFTPContent) > 0) {
			echo (PHP_EOL . "Erzeuge Download-Liste für " . ftp_pwd($conn_id) . ":" . PHP_EOL);
			foreach ($arrFTPContent as $filename) {
				// Filtere nach Landkreis-Array
				if (strpos($filename, $fileFilter) !== false) {
					// Übernehme Datei in zu-bearbeiten Liste
					if (preg_match('/^(?<Prefix>\w_\w{3}_\w_\w{4}_)(?<Datum>\d{14})(?<Postfix>_\w{3}_STATUS)(?<Extension>.zip)$/', $filename, $regs)) {
						$dateFileM = DateTime::createFromFormat("YmdHis", $regs['Datum'], new DateTimeZone("UTC"));
						if ($dateFileM === false) {
							$dateFileM->setTimezone(new DateTimeZone("Europe/Berlin"));
							$fileDate = ftp_mdtm($conn_id, $filename);
							$detectMode = "via FTP / Lesen des Datums fehlgeschlagen";
						} else {
							$fileDate = $dateFileM->getTimestamp();
							$detectMode = "via RegExp";
						}
					} else {
						$fileDate = ftp_mdtm($conn_id, $filename);
						$detectMode = "via FTP / Lesen des Dateinamens fehlgeschlagen";
					}
					echo ($filename . " => " . date("d.m.Y H:i", $fileDate) . " (" . $detectMode . ")" . PHP_EOL);
					$arrDownloadList[$filename] = $fileDate;
				}
			}
		}

		// Dateiliste sortieren
		arsort($arrDownloadList, SORT_NUMERIC);
		array_splice($arrDownloadList, 1);

		if (count($arrDownloadList) > 0) {
			// Beginne Download
			echo (PHP_EOL . "Starte den Download von der aktuellen Warn-Datei:" . PHP_EOL);

			foreach ($arrDownloadList as $filename => $filetime) {
				$localFile = $config["localFolder"] . DIRECTORY_SEPARATOR . $filename;

				foreach ($arrDownloadList as $filename => $filetime) {
					$localFile = $config["localFolder"] . DIRECTORY_SEPARATOR . $filename;

					// Ermittle Zeitpunkt der letzten Modifikation
					if (file_exists($localFile)) {
						// Zeitpunkt der letzten Veränderung der lokalen Datei speichern
						$localFileMTime = filemtime($localFile);
					} else {
						// Da keine lokale Datei existiert, Zeitpunkt in die Vergangenheit setzen
						$localFileMTime = - 1;
					}
					$remoteFileMTime = $filetime;

					if ($remoteFileMTime != $localFileMTime) {
						// Öffne lokale Datei
						$handle = fopen($localFile, 'w');

						if (ftp_fget($conn_id, $handle, $filename, FTP_BINARY, 0)) {
							if ($localFileMTime === - 1) {
								echo "Datei " . $localFile . " wurde erfolgreich heruntergeladen." . PHP_EOL;
							} else {
								echo "Datei " . $localFile . " wurde erneut erfolgreich heruntergeladen (Lokal: " . date("d.m.Y H:i:s", $localFileMTime) . " / Remote: " . date("d.m.Y H:i:s", $remoteFileMTime) . ")." . PHP_EOL;
							}
						} else {
							echo "Datei " . $filename . " Download ist fehlgeschlagen. " . PHP_EOL;
						}

						// Schließe Datei-Handle
						fclose($handle);

						// Zeitstempel setzen mtime um identisch mit der Remote-Datei zu sein (für Cache-Funktion)
						touch($localFile, $remoteFileMTime);
					} else {
						echo "Datei " . $localFile . " existiert bereits lokal mit dem gleichen Zeitstempel." . PHP_EOL;
					}
				}
			}
		}

		// Dateiname zurückgeben
		return array_keys($arrDownloadList)[0];
	} catch (Exception $e) {
		// Fehler-Handling
		$message = $e->getFile() . "(" . $e->getLine() . "): " . $e->getMessage();
		sendErrorMessage($message);

		return array();
	}
}

?>
