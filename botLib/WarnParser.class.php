<?php
/*
 * Wetterwarnung-Downloader für neuthardwetter.de by Jens Dutzi
 * Version 2.0-dev
 * 05.03.2017
 * (c) tf-network.de Jens Dutzi 2012-2017
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

namespace blog404de\WetterScripts;

class WarnParser extends ErrorLogging {
	/** @var string Remote Folder auf dem DWD FTP Server mit den Wetterwarnungen */
	private $remoteFolder = "/gds/gds/specials/alerts/cap/GER/community_status_geometry";

	/** @var string Local Folder in dem die Wetterwarnungen gespeichert werden */
	private $localFolder = "";

	/** @var resource $ftpConnectionId Link identifier der FTP Verbindung */
	private $ftpConnectionId;

	/**
	 * WarnParser constructor.
	 */
	function __construct() {
		// Setze Location-Informationen für Deutschland
		setlocale(LC_TIME, "de_DE.UTF-8");
		date_default_timezone_set("Europe/Berlin");

		// Root-User Check
		if (0 == posix_getuid()) {
			throw new \Exception("Script darf nicht mit root-Rechten ausgeführt werden");
		}

		// FTP Modul vorhanden?
		if(!extension_loaded("ftp")) {
			throw new \Exception("PHP Modul 'ftp' steht nicht zur Verfügung");
		}
	}


	/**
	 * Verbindung zum DWD FTP Server aufbauen
	 *
	 * @param $host
	 * @param $username
	 * @param $password
	 * @param $passiv
	 */
	public function connectToFTP($host, $username, $password, $passiv = false) {
		try {
			echo "*** Baue Verbindung zum DWD-FTP Server auf." . PHP_EOL;

			// FTP-Verbindung aufbauen
			$this->ftpConnectionId = ftp_connect($host);
			if($this->ftpConnectionId === false) {
				throw new \Exception( "FTP Verbindungsaufbau zu " . $host . " ist fehlgeschlagen" . PHP_EOL);
			}

			// Login mit Benutzername und Passwort
			$login_result = @ftp_login($this->ftpConnectionId, $username, $password);

			// Verbindung überprüfen
			if ((!($this->ftpConnectionId)) || (!$login_result)) {
				throw new \Exception("Verbindungsaufbau zu zu " . $host . " mit Benutzername " . $username . " fehlgeschlagen.");
			} else {
				echo "\t-> Verbindungsaufbau zu " . $host . " mit Benutzername " . $username . " erfolgreich" . PHP_EOL;
			}

			// Auf Passive Nutzung umschalten
			if($passiv == true) {
				echo "\t-> Schalte auf Passive Verbindung" . PHP_EOL;
				@ftp_pasv(($this->ftpConnectionId), true);
			}
		} catch (\Exception $e) {
			$this->logError($e);
		}
	}

	/**
	 * Verbindung vom FTP Server trennen
	 */
	public function disconnectFromFTP() {
		// Schließe Verbindung sofern notwendig
		if(is_resource($this->ftpConnectionId)) {
			echo PHP_EOL . "*** Schließe Verbindung zum DWD-FTP Server" . PHP_EOL;
			ftp_close($this->ftpConnectionId);
			echo "\t-> Verbindung zu erfolgreich geschlossen." . PHP_EOL;
		}
	}

	public function updateFromFTP() {
		try {
			// Starte Verarbeitung der Dateien
			echo PHP_EOL . "*** Verarbeite alle Dateien auf dem DWD FTP Server" . PHP_EOL;

			// Prüfe ob Verbindung aktiv ist
			if(!is_resource($this->ftpConnectionId)) {
				throw new \Exception("FTP Verbindung steht nicht mehr zur Verfügung.");
			};

			// Versuche, in das benötigte Verzeichnis zu wechseln
			if (@ftp_chdir($this->ftpConnectionId, $this->remoteFolder)) {
				echo "-> Wechsle in das Verzeichnis: " . ftp_pwd($this->ftpConnectionId) . PHP_EOL;
			} else {
				throw new \Exception("Fehler beim Wechsel in das Verzeichnis '" . $this->remoteFolder . "' auf dem DWD FTP-Server.");
			}

			// Verzeichnisliste auslesen und sortieren
			$arrFTPContent = @ftp_nlist($this->ftpConnectionId, ".");
			if ($arrFTPContent === FALSE) {
				throw new \Exception("Fehler beim auslesen des Verezichnis " . $this->remoteFolder  . " auf dem DWD FTP-Server.");
			} else {
				echo("-> Liste der auf dem DWD Server vorhandenen Wetterdaten herunterladen" . PHP_EOL);
			}

			// Filtern der Dateinamen um nicht für alle den Zeitstempel ermittelen zu müssen
			$searchTime = new \DateTime( "now", new \DateTimeZone('GMT'));
			$fileFilter = $searchTime->format("Ymd");

			// Ermittle das Datum für die Dateien
			if (count($arrFTPContent) > 0) {
				echo("-> Erzeuge Download-Liste für " . @ftp_pwd($this->ftpConnectionId) . ":" . PHP_EOL);
				foreach ($arrFTPContent as $filename) {
					// Filtere nach den Wetterwarnungen vom heutigen Tag
					if (strpos($filename , $fileFilter) !== FALSE) {
						// Übernehme Datei in zu-bearbeiten Liste
						if (preg_match('/^(?<Prefix>\w_\w{3}_\w_\w{4}_)(?<Datum>\d{14})(?<Postfix>_\w{3}_STATUS_AREA_UNION)(?<Extension>\.zip)$/' , $filename , $regs)) {
							$dateFileM = \DateTime::createFromFormat("YmdHis" , $regs['Datum'] , new \DateTimeZone("UTC"));
							if ($dateFileM === FALSE) {
								$fileDate = @ftp_mdtm($this->ftpConnectionId, $filename);
								$detectMode = "via FTP / Lesen des Datums fehlgeschlagen";
							} else {
								$fileDate = $dateFileM->getTimestamp();
								$detectMode = "via RegExp";
							}
						} else {
							$fileDate = @ftp_mdtm($this->ftpConnectionId , $filename);
							$detectMode = "via FTP / Lesen des Dateinamens fehlgeschlagen";
						}
						echo "\t" . $filename . " => " . date("d.m.Y H:i" , $fileDate) . " (" . $detectMode . ")" . PHP_EOL;
						$arrDownloadList[$filename] = $fileDate;
					}
				}
			}

			// Dateiliste sortieren
			arsort($arrDownloadList , SORT_NUMERIC);
			array_splice($arrDownloadList , 1);

			// Starte Download der aktuellsten Warn-Datei
			if (count($arrDownloadList) > 0) {
				// Beginne Download
				echo("-> Starte den Download der aktuellsten Warn-Datei:" . PHP_EOL);

				foreach ($arrDownloadList as $filename => $remoteFileMTime) {
					$localFile = $this->localFolder . DIRECTORY_SEPARATOR . $filename;

					// Ermittle Zeitpunkt der letzten Modifikation der lokalen Datei
					$localFileMTime = @filemtime($localFile);

					if ($localFileMTime === FALSE) {
						// Da keine lokale Datei existiert, Zeitpunkt in die Vergangenheit setzen
						$localFileMTime = -1;
					}

					if ($remoteFileMTime !== $localFileMTime) {
						// Öffne lokale Datei
						$localFileHandle = fopen($localFile , 'w');
						if(!$localFileHandle) throw new \Exception("Kann " . $localFile . " nicht zum schreiben öffnen");

						if (ftp_fget($this->ftpConnectionId , $localFileHandle , $filename , FTP_BINARY , 0)) {
							if ($localFileMTime === -1) {
								echo sprintf("\tDatei %s wurde erfolgreich heruntergeladen (Remote: %s).", $localFile, date("d.m.Y H:i:s" , $remoteFileMTime)) . PHP_EOL;
							} else {
								echo sprintf("\tDatei %s wurde erneut erfolgreich heruntergeladen (Lokal: %s / Remote: %s).",
									$localFile, date("d.m.Y H:i:s" , $localFileMTime), date("d.m.Y H:i:s" , $remoteFileMTime)) . PHP_EOL;
							}
						} else {
							throw new \Exception(sprintf("\tDatei %s wurde erneut erfolgreich heruntergeladen.", $localFile));
						}

						// Schließe Datei-Handle
						@fclose($localFileHandle);

						// Zeitstempel der lokalen Datei identisch zur Remote-Datei setzen (für Cache-Funktion)
						touch($localFile , $remoteFileMTime);
					} else {
						echo sprintf("\tDatei %s existiert bereits im lokalen Download-Ordner.", $localFile) . PHP_EOL;
					}


				}
			}
		} catch (\Exception $e) {
			$this->logError($e);
		}
	}

	public function cleanLocalCache() {
		try {
			// Starte Verarbeitung der Dateien
			echo PHP_EOL . "*** Räume den lokalen Cache auf:" . PHP_EOL;

			// Prüfe Existenz der lokalen Verzeichnisse
			if (!is_writeable($this->localFolder)) {
				throw new Exception("Zugriff auf den Cache-Ordner " . $this->localFolder . " für das automatische aufräumen fehlgeschlagen.");
			}

			// Erzeuge Array mit allen bereits vorhandenen Dateien
			$localFiles = [];
			$handle = opendir($this->localFolder);
			if ($handle) {
				while (FALSE !== ($entry = readdir($handle))) {
					if (!is_dir($this->localFolder . DIRECTORY_SEPARATOR . $entry)) {
						$fileinfo = pathinfo($this->localFolder . DIRECTORY_SEPARATOR . $entry);
						if ($fileinfo["extension"] == "zip") {
							$localFileMTime = @filemtime($this->localFolder . DIRECTORY_SEPARATOR . $entry);
							if ($localFileMTime !== FALSE) $localFiles[$localFileMTime] = $entry;
						}
					}
				}
				closedir($handle);
			} else {
				throw new Exception("Fehler beim aufräumen des Cache in " . $this->localFolder);
			}

			// Dateiliste sortieren
			asort($localFiles, SORT_NUMERIC);
			$localFiles = array_reverse($localFiles);

			// Array $localFiles aufsplitten in zu behaltende und zu löschende Dateien
			$obsoletFiles = array_splice($localFiles, 1);

			// Starte Löschvorgang
			if(count($obsoletFiles) > 0) {
				echo "-> Starte Löschvorgang " . PHP_EOL;
				foreach ($obsoletFiles as $filename) {
					echo "\tLösche veraltete Wetterwarnung-Datei " . $filename . ": ";
					if (!@unlink($this->localFolder . DIRECTORY_SEPARATOR . $filename)) {
						throw new Exception(PHP_EOL . "Fehler beim aufräumen des Caches: '" . $this->localFolder . DIRECTORY_SEPARATOR . $filename . "'' konnte nicht erfolgreich gelöscht werden.");
					} else {
						echo "-> Datei gelöscht." . PHP_EOL;
					}
				}
			} else {
				echo("-> Es muss keine Datei gelöscht werden");
			}
		} catch (Exception $e) {
			// Fehler-Handling
			$this->logError($e);
		}

	}

	/**
	 * @return string
	 */
	public function getLocalFolder(): string {
		return $this->localFolder;
	}

	/**
	 * @param string $localFolder
	 */
	public function setLocalFolder(string $localFolder) {
		try {
			if (is_dir($localFolder)) {
				if(is_writeable($localFolder)) {
					$this->localFolder = $localFolder;
				} else {
					throw new \Exception("In den lokale Ordner " . $localFolder . " kann nicht geschrieben werden.");
				}
			} else {
				throw new \Exception("In den lokale Ordner " . $localFolder . " existiert nicht.");
			}
		} catch (\Exception $e) {
			$this->logError($e);
		}
	}


	/**
	 * Getter für $remoteFolder
	 * @return string
	 */
	public function getRemoteFolder(): string {
		return $this->remoteFolder;
	}

	/**
	 * Setter für $remoteFolder
	 * @param string $remoteFolder
	 */
	public function setRemoteFolder(string $remoteFolder) {
		$this->remoteFolder = $remoteFolder;
	}
}

/**
 * Error-Logging Klasse
 */

class ErrorLogging {
	/** @var array E-Mail Absender/Empfänger in ["empfaenger"] und ["absender"] */
	private $logToMail = [];

	/** @var string Pfad zur Log-Datei */
	private $logToFile = "";

	/**
	 * @param array $logToMail
	 * @return ErrorLogging
	 */
	public function setLogToMail(array $logToMail): ErrorLogging {

		try {
			if (is_array($logToMail)) {
				if (array_key_exists("empfaenger" , $logToMail) && array_key_exists("absender", $logToMail )) {
					if (filter_var($logToMail["empfaenger"], FILTER_VALIDATE_EMAIL) && filter_var($logToMail["absender"], FILTER_VALIDATE_EMAIL)) {
						$this->logToMail = $logToMail;
					} else {
						throw new \Exception("LogToMail beinhaltet für die Array-Keys 'empfaenger' oder 'absender' keine gültige E-Mail Adresse");
					}
				} else {
					// Error-Logging Konfiguration ist falsch
					$this->logToMail = [];
					throw new \Exception("LogToMail benötigt ein Array mit den Keys 'empfaenger' und 'absender'");
				}
			} else {
				// Error Logging an E-Mail deaktivieren
				$this->logToMail = [];
			}
		} catch (\Exception $e) {
			$this->logError($e);
		}

		return $this;
	}

	/**
	 * @param string $logToFile
	 * @return ErrorLogging
	 * @throws \Exception
	 */
	public function setLogToFile(string $logToFile): ErrorLogging {
		try {
			if (is_writeable($logToFile)) {
				$this->logToFile = $logToFile;
			} else {
				throw new \Exception("Fehler beim schreiben der Log-Datei in: " . $logToFile);
			}
		} catch (\Exception $e) {
			$this->logError($e);
		}

		return $this;
	}

	/**
	 * @param \Exception $e
	 */
	protected function logError(\Exception $e) {
		// Zeitpunkt
		$strDate = date("Y-m-d H:i:s");

		// Fehler-Ausgabe erzeugen:
		$longText = sprintf("Fehler im Programmablauf:" . PHP_EOL .
							"\tZeitpunkt: %s" . PHP_EOL .
							"\tFehlermeldung: %s" . PHP_EOL .
							"\tPosition: %s:%d",
			$strDate, $e->getMessage(), $e->getFile(), $e->getLine()
		);


		$shortText = sprintf("%s - %s:%d - %s",
			date("Y-m-d H:i:s"),
			$e->getFile(),
			$e->getLine(),
			$e->getMessage()
		);

		// Loggen in Datei
		if(!empty($this->logToFile)) {
			echo "XX";
			$writeFile = file_put_contents($this->logToFile, $shortText, FILE_APPEND);
			if($writeFile === FALSE) {
				$longText = $longText . PHP_EOL . "Fehler beim schreiben der Log-Datei in: " . $this->logToFile . PHP_EOL;
			}
		}

		// Loggen per E-Mail
		if (is_array($this->logToMail)) {
			if (array_key_exists("empfaenger" , $this->logToMail) && array_key_exists("absender", $this->logToMail )) {
				$mailHeader = sprintf("From: Wetterwarn-Bot <%s>\r\n" .
					"Reply-To: Wetter-Bot <%s>\r\n" .
					"X-Mailer: Wetter-Bot by tfnApps.de\r\n" .
					"X-Priority: 1 (Higuest)\r\n" .
					"X-MSMail-Priority: High\r\n" .
					"Importance: High\r\n" ,
					$this->logToMail["absender"] ,
					$this->logToMail["empfaenger"]
				);
				$mailBetreff = "Fehler beim verarbeiten der Wetterwarndaten: " . $strDate;

				$sentMail = mail($this->logToMail["empfaenger"] , $mailBetreff , $longText , $mailHeader);
				if ($sentMail === FALSE) {
					$longText = $longText . PHP_EOL . "Fehler beim senden der Fehler E-Mail an: " . $this->logToMail["empfaenger"] . PHP_EOL;
				} else {
					$longText = $longText . PHP_EOL . "Fehler E-Mail wurde erfolgreich an " . $this->logToMail["empfaenger"] . " versendet." . PHP_EOL;
				}
			}
		}

		// Ausgabe auf die Konsole
		fwrite(STDERR, $longText);
		fwrite(STDOUT, PHP_EOL);

		exit(1);
	}

}