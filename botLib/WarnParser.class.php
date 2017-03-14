<?php
/**
 * Wetterwarnung-Downloader für neuthardwetter.de by Jens Dutzi
 *
 * @version 2.0-dev 2.0.0-dev
 * @copyright (c) tf-network.de Jens Dutzi 2012-2017
 * @license MIT
 *
 * @package blog404de\WetterScripts
 *
 * Stand: 05.03.2017
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

/**
 * Benötigte Module laden
 *
 * @TODO: Auslagern in autoload.php
 */

require_once "Toolbox.php";

use ZipArchive;
use Exception;
use blog404de\Toolbox;

/**
 * Parser für die Wetter-Warnungen des DWD
 *
 * @package blog404de\WetterScripts\WarnParser
 */
class WarnParser extends ErrorLogging {
	/** @var string Remote Folder auf dem DWD FTP Server mit den Wetterwarnungen */
	private $remoteFolder = "/gds/gds/specials/alerts/cap/GER/community_status_geometry";

	/** @var string Lokaler Ordner in dem die Wetterwarnungen gespeichert werden */
	private $localFolder = "";

	/** @var resource $ftpConnectionId Link identifier der FTP Verbindung */
	private $ftpConnectionId;

	/** @var string $localJsonFile Lokale Datei in der die verarbeiteten Wetterwarnungen gespeichert werden  */
	private $localJsonFile = "";

	/** @var string|bool $tmpFolder Ordner für temporäre Dateien */
	private $tmpFolder = false;

	/** @var array Array mit Bundesländer in Deutschland */
	static $regionames = [
		"BW" => "Baden-Würtemberg",		"BY" => "Bayern",
		"BE" => "Berlin",				"BB" => "Brandenburg",
		"HB" => "Bremen",				"HH" => "Hamburg",
		"HE" => "Hessen",				"MV" => "Mecklenburg-Vorpommern",
		"NI" => "Niedersachsen",		"NW" => "Nordrhein-Westfalen",
		"RP" => "Rheinland-Pfalz",		"SL" => "Saarland",
		"SN" => "Sachsen",				"ST" => "Sachsen-Anhalt",
		"TH" => "Schleswig-Holstein",	"DE" => "Bundesrepublik Deutschland"
	];

	/**
	 * WarnParser constructor.
	 */
	function __construct() {
		// Setze Location-Informationen für Deutschland
		setlocale(LC_TIME, "de_DE.UTF-8");
		date_default_timezone_set("Europe/Berlin");

		// Via CLI gestartet?
		if(php_sapi_name() !== "cli") {
			throw new Exception("Script darf ausschließlich über die Kommandozeile gestartet werden.");
		}

		// Root-User Check
		if (0 == posix_getuid()) {
			throw new Exception("Script darf nicht mit root-Rechten ausgeführt werden");
		}

		// FTP Modul vorhanden?
		if(!extension_loaded("ftp")) {
			throw new Exception("PHP Modul 'ftp' steht nicht zur Verfügung");
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
				throw new Exception( "FTP Verbindungsaufbau zu " . $host . " ist fehlgeschlagen" . PHP_EOL);
			}

			// Login mit Benutzername und Passwort
			$login_result = @ftp_login($this->ftpConnectionId, $username, $password);

			// Verbindung überprüfen
			if ((!($this->ftpConnectionId)) || (!$login_result)) {
				throw new Exception("Verbindungsaufbau zu zu " . $host . " mit Benutzername " . $username . " fehlgeschlagen.");
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

	/**
	 * Lade aktuelle Wetterwarnungen vom DWD FTP Server
	 */
	public function updateFromFTP() {
		try {
			// Starte Verarbeitung der Dateien
			echo PHP_EOL . "*** Verarbeite alle Dateien auf dem DWD FTP Server" . PHP_EOL;

			// Prüfe ob Verbindung aktiv ist
			if(!is_resource($this->ftpConnectionId)) {
				throw new Exception("FTP Verbindung steht nicht mehr zur Verfügung.");
			};

			// Versuche, in das benötigte Verzeichnis zu wechseln
			if (@ftp_chdir($this->ftpConnectionId, $this->remoteFolder)) {
				echo "-> Wechsle in das Verzeichnis: " . ftp_pwd($this->ftpConnectionId) . PHP_EOL;
			} else {
				throw new Exception("Fehler beim Wechsel in das Verzeichnis '" . $this->remoteFolder . "' auf dem DWD FTP-Server.");
			}

			// Verzeichnisliste auslesen und sortieren
			$arrFTPContent = @ftp_nlist($this->ftpConnectionId, ".");
			if ($arrFTPContent === FALSE) {
				throw new Exception("Fehler beim auslesen des Verezichnis " . $this->remoteFolder  . " auf dem DWD FTP-Server.");
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
						if(!$localFileHandle) throw new Exception("Kann " . $localFile . " nicht zum schreiben öffnen");

						if (ftp_fget($this->ftpConnectionId , $localFileHandle , $filename , FTP_BINARY , 0)) {
							if ($localFileMTime === -1) {
								echo sprintf("\tDatei %s wurde erfolgreich heruntergeladen (Remote: %s).", $localFile, date("d.m.Y H:i:s" , $remoteFileMTime)) . PHP_EOL;
							} else {
								echo sprintf("\tDatei %s wurde erneut erfolgreich heruntergeladen (Lokal: %s / Remote: %s).",
									$localFile, date("d.m.Y H:i:s" , $localFileMTime), date("d.m.Y H:i:s" , $remoteFileMTime)) . PHP_EOL;
							}
						} else {
							throw new Exception(sprintf("\tDatei %s wurde erneut erfolgreich heruntergeladen.", $localFile));
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

	/**
	 * Bereinige lokalen Cache-Ordner
	 */
	public function cleanLocalCache() {
		try {
			// Abschließende Arbeiten ausführen
			echo PHP_EOL . "*** Führe abschließende Arbeiten durch: " . PHP_EOL;

			if($this->tmpFolder !== FALSE) {
				echo "\tLösche angelegten temporären Ordner: ";
				if(!Toolbox::removeTempDir($this->tmpFolder)) {
					echo "fehlgeschlagen" . PHP_EOL;
					throw new Exception("Löschen des Temporären Ordner (" . $this->tmpFolder . ") ist fehlgeschlagen.");
				};
				echo "erfolgreich (" . $this->tmpFolder . ")" . PHP_EOL;
			} else {
				echo "\tKeine Arbeiten notwendig" . PHP_EOL;
			}

			// Starte Verarbeitung der Dateien
			echo "\tLösche veraltete Wetterwarnungen aus Cache-Ordner." . PHP_EOL;

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
					echo "\t - Lösche veraltete Wetterwarnung-Datei " . $filename . ": ";
					if (!@unlink($this->localFolder . DIRECTORY_SEPARATOR . $filename)) {
						throw new Exception(PHP_EOL . "Fehler beim aufräumen des Caches: '" . $this->localFolder . DIRECTORY_SEPARATOR . $filename . "'' konnte nicht erfolgreich gelöscht werden.");
					} else {
						echo "-> Datei gelöscht." . PHP_EOL;
					}
				}
			} else {
				echo("\t - Es muss keine Datei gelöscht werden" . PHP_EOL);
			}
		} catch (\Exception $e) {
			// Fehler-Handling
			$this->logError($e);
		}

	}

	/**
	 * Parse lokale Wetterwarnungen nach WarnCellID
	 */
	public function prepareWetterWarnungen() {
		try {
			// Starte verabeiten der Wetterwarnungen des DWD
			echo PHP_EOL . "*** Verarbeite die Wetterwarnungen:" . PHP_EOL;

			echo "-> Bereite das vearbeiten der Wetterwarnungen vor" . PHP_EOL;
			echo "\tPrüfe Konfiguration" . PHP_EOL;

			// Prüfe Existenz der lokalen Verzeichnisse
			if (! is_readable($this->localFolder)) {
				throw new Exception("Zugriff auf das Verzeichnis " . $this->localFolder . " mit den lokalen Wetterwarnungen fehlgeschlagen");
			}

			// Zugriff auf JSON Datei möglich
			if(empty($this->localJsonFile)) {
				throw new Exception("Es wurde keine JSON-Datei als Ziel für die Wetterwarnungen angegeben.");
			} else {
				if(file_exists($this->localJsonFile) && !is_writeable($this->localJsonFile)) {
					throw new Exception("Auf die JSON Datei " . $this->localJsonFile . " mit den geparsten Wetterwarnungen kann nicht schreibend zugegriffen werden");
				} else if (!is_writeable(dirname($this->localJsonFile))) {
					throw new Exception("JSON Datei für die geparsten Wetterwarnungen kann nicht in " . dirname($this->localJsonFile) . " geschrieben werden");
				}
			}

			echo "\tLege temporären Ordner an: ";
			$this->tmpFolder = Toolbox::tempdir();
			if(!$this->tmpFolder && is_string($this->tmpFolder)) {
				// Temporär-Ordner kann nicht angelegt werden
				echo "fehlgeschlagen" . PHP_EOL;
				throw new Exception("Temporär-Ordner kann nicht angelegt werden. Bitte prüfen Sie ob in der php.ini 'sys_tmp_dir' oder die Umgebungsvariable 'TMPDIR' gesetzt ist.");
			}
			echo "erfolgreich (" . $this->tmpFolder . ")" . PHP_EOL;

			// ZIP-Dateien in Temporär-Ordner entpacken
			$this->extractZipFiles();
		} catch (\Exception $e) {
			// Fehler an Logging-Modul übergeben
			$this->logError($e, $this->tmpFolder);
		}
	}

	/*
	 *  Private Methoden
	 */

	/**
	 * Entpacken der ZIP-Dateien von localFolder in tmpFolder in einem Ordner
	 */
	private function extractZipFiles() {
		try {
			echo "-> Entpacke die Wetterwarnungen des DWD" . PHP_EOL;
			// Erzeuge Array mit allen ZIP-Dateien in $this->localFolder
			$localZipFiles = array();
			$handle = opendir($this->localFolder);
			if ($handle) {
				while (false !== ($entry = readdir($handle))) {
					if (! is_dir($this->localFolder . DIRECTORY_SEPARATOR . $entry)) {
						$fileinfo = pathinfo($this->localFolder . DIRECTORY_SEPARATOR . $entry);
						if ($fileinfo["extension"] == "zip")
							$localZipFiles[] = $entry;
					}
				}
				closedir($handle);
			} else {
				throw new Exception("Fehler beim durchsuchen des lokale Wetterwarnung-Ordner nach DWD-ZIP Dateien");
			}

			// Eigentlich darf aktuell nur eine ZIP Datei vorhanden sein (vorbereitet aber für >1 ZIP Datei)
			if (count($localZipFiles) > 1) {
				throw new Exception("Mehr als eine Datei befindet sich im lokalen Wetterwarnung-Ordner und es dürfte nur eine vorhanden sein");
			}

			// Entpacke ZIP-Dateien
			foreach ($localZipFiles as $zipFile) {
				// Öffne ZIP Datei
				$zip = new ZipArchive();
				$res = $zip->open($this->localFolder . DIRECTORY_SEPARATOR . $zipFile);
				if ($res === true) {
					echo "\tEntpacke Wetterwarnung-Datei: " . $zipFile . " (" . $zip->numFiles . " Datei" . ($zip->numFiles > 1 ? "en" : "") . ")" . PHP_EOL;
					$zip->extractTo($this->tmpFolder);
					$zip->close();
				} else {
					throw new Exception("Fehler beim öffnen der ZIP Datei '" . $zipFile . "'. Fehlercode: " . $res . " / " . $this->getZipErrorMessage($res));
				}
			}
		} catch (\Exception $e) {
			// Fehler an Logging-Modul übergeben
			$this->logError($e, $this->tmpFolder);
		}
	}

	/*
	 *  Getter / Setter Funktionen
	 */

	/**
	 * Getter für $localFolder
	 * @return string
	 */
	public function getLocalFolder(): string {
		return $this->localFolder;
	}

	/**
	 * Setter für $localFolder
	 * @param string $localFolder
	 */
	public function setLocalFolder(string $localFolder) {
		try {
			if (is_dir($localFolder)) {
				if(is_writeable($localFolder)) {
					$this->localFolder = $localFolder;
				} else {
					throw new Exception("In den lokale Ordner " . $localFolder . " kann nicht geschrieben werden.");
				}
			} else {
				throw new Exception("In den lokale Ordner " . $localFolder . " existiert nicht.");
			}
		} catch (\Exception $e) {
			$this->logError($e);
		}
	}

	/**
	 * Getter für $localJsonFile
	 * @return string
	 */
	public function getLocalJsonFile(): string {
		return $this->localJsonFile;
	}

	/**
	 * Setter für $localJsonFile
	 * @param string $localJsonFile
	 */
	public function setLocalJsonFile(string $localJsonFile) {
		try {
			if(empty($localJsonFile)) {
				throw new Exception("Es wurde keine JSON-Datei zals Ziel für die Wetterwarnungen angegeben.");
			} else {
				if(file_exists($localJsonFile) && !is_writeable($localJsonFile)) {
					// Datei existiert - aber die Schreibrechte fehlen
					throw new Exception("Auf die JSON Datei " . $localJsonFile . " mit den geparsten Wetterwarnungen kann nicht schreibend zugegriffen werden");
				} else if (is_writeable(dirname($localJsonFile))) {
					// Datei existiert nicht - Schreibrechte auf den Ordner existieren
					if(!@touch($localJsonFile)) {
						// Leere Datei anlegen ist nicht erfolgreich
						throw new Exception("Leere JSON Datei für die geparsten Wetterwarnungen konnte nicht angelegt werden");
					}
				} else {
					// Kein Zugriff auf Datei möglich
					throw new Exception("JSON Datei für die geparsten Wetterwarnungen kann nicht in " . dirname($localJsonFile) . " geschrieben werden");
				}
			}

			// Variable setzen
			$this->localJsonFile = $localJsonFile;
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
 *
 * @package blog404de\WetterScripts\WarnParser
 */
class ErrorLogging {
	/** @var array E-Mail Absender/Empfänger in ["empfaenger"] und ["absender"] */
	private $logToMail = [];

	/** @var string Pfad zur Log-Datei */
	private $logToFile = "";

	/**
	 * Fehler innerhalb der Anwendung verarbeiten
	 *
	 * @param \Exception $e
	 * @param string $tmpPath
	 */
	protected function logError(\Exception $e, string $tmpPath = NULL) {
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

		// Lösche evntuell vorhandenes Temporäre Verzeichnis
		if($tmpPath !== FALSE && !is_null($tmpPath)) {
			$tmpclean = Toolbox::removeTempDir($tmpPath);
			if(!$tmpclean) {
				$shortText = $shortText . " - Cleanup: temporärer Ordner (" . $tmpPath . ") konnte nicht gelöscht werden";
				$longText = $longText . PHP_EOL . "\tCleanup: Fehler beim löschen des temporären Ordner (" . $tmpPath . ")";
			} else {
				$shortText = $shortText . " - Cleanup: temporärer Ordner gelöscht";
				$longText = $longText . PHP_EOL . "\tCleanup:temporärer Ordner gelöscht";
			}
		}

		// Loggen in Datei
		if(!empty($this->logToFile)) {
			$writeFile = file_put_contents($this->logToFile, $shortText . PHP_EOL, FILE_APPEND);
			if($writeFile === FALSE) {
				$longText = $longText . PHP_EOL . "\tLogdatei schreiben: Fehler beim schreiben der Log-Datei in: " . $this->logToFile . PHP_EOL;
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
					$longText = $longText . PHP_EOL . "\tE-Mail Versand: Fehler beim senden der Fehler E-Mail an: " . $this->logToMail["empfaenger"] . PHP_EOL;
				} else {
					$longText = $longText . PHP_EOL . "\tE-Mail Versand: Fehler E-Mail wurde erfolgreich an " . $this->logToMail["empfaenger"] . " versendet." . PHP_EOL;
				}
			}
		}

		// Ausgabe auf die Konsole
		fwrite(STDOUT, PHP_EOL);
		fwrite(STDERR, $longText);
		fwrite(STDOUT, PHP_EOL);

		exit(1);
	}

	/**
	 * Methode zum generieren der Klartext-Fehlermeldung beim Zugriff auf ZIP-Dateien
	 *
	 * @param $errCode
	 * @return string
	 */
	protected function getZipErrorMessage($errCode) {
		switch ($errCode) {
			case ZipArchive::ER_EXISTS:
				return "Datei existiert bereits.";
				break;
			case ZipArchive::ER_INCONS:
				return "Zip-Archiv ist nicht konsistent.";
				break;
			case ZipArchive::ER_INVAL:
				return "Ungültiges Argument.";
				break;
			case ZipArchive::ER_MEMORY:
				return "Malloc Fehler.";
				break;
			case ZipArchive::ER_NOENT:
				return "Datei nicht vorhanden.";
				break;
			case ZipArchive::ER_NOZIP:
				return "Kein Zip-Archiv.";
				break;
			case ZipArchive::ER_OPEN:
				return "Datei kann nicht geöffnet werden.";
				break;
			case ZipArchive::ER_READ:
				return "Lesefehler.";
				break;
			case ZipArchive::ER_SEEK:
				return "Seek Fehler.";
				break;
			default:
				return "Unknown error.";
		}
	}

	/*
	 * Getter / Setter-Methoden
	 */

	/**
	 * Setter-Methode für logToMail
	 *
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
						throw new Exception("LogToMail beinhaltet für die Array-Keys 'empfaenger' oder 'absender' keine gültige E-Mail Adresse");
					}
				} else {
					// Error-Logging Konfiguration ist falsch
					$this->logToMail = [];
					throw new Exception("LogToMail benötigt ein Array mit den Keys 'empfaenger' und 'absender'");
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
	 * Setter-Methode für LogToFile
	 *
	 * @param string $logToFile
	 * @return ErrorLogging
	 * @throws \Exception
	 */
	public function setLogToFile(string $logToFile): ErrorLogging {
		try {
			if(file_exists($logToFile) && is_writeable($logToFile)) {
				// Datei existiert und kann geschrieben werden
				$this->logToFile = $logToFile;
			} else if (!file_exists($logToFile) && is_writeable(dirname($logToFile))) {
				if(touch($logToFile)) {
					// Nicht existierende Log-Datei erfolgreich angelegt
					$this->logToFile = $logToFile;
				} else {
					// Log-Datei kann nicht neu angelegt werden
					throw new Exception("Fehler beim anlegen der Log-Datei in: " . $logToFile);
				}
			} else {
				throw new Exception("Fehler beim schreiben der Log-Datei in: " . $logToFile);
			}
		} catch (\Exception $e) {
			$this->logError($e);
		}

		return $this;
	}

	/**
	 * Getter-Methode für logToMail
	 * @return array
	 */
	public function getLogToMail(): array {
		return $this->logToMail;
	}

	/**
	 * Getter-Methode für LogToFile
	 * @return string
	 */
	public function getLogToFile(): string {
		return $this->logToFile;
	}
}