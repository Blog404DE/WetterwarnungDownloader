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
				echo "Verbindungsaufbau zu " . $host . " mit Benutzername " . $username . " erfolgreich" . PHP_EOL;
			}

			// Auf Passive Nutzung umschalten
			if($passiv == true) {
				echo "Schalte auf Passive Verbindung" . PHP_EOL;
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
		}
	}

	public function updateFromFTP() {
		try {
			// Prüfe ob Verbindung aktiv ist
			if(!is_resource($this->ftpConnectionId)) {
				throw new \Exception("FTP Verbindung steht nicht mehr zur Verfügung.");
			};

			// Versuche, in das benötigte Verzeichnis zu wechseln
			if (@ftp_chdir($this->ftpConnectionId, $this->remoteFolder)) {
				echo "Wechsle in das Verzeichnis: " . ftp_pwd($this->ftpConnectionId) . PHP_EOL;
			} else {
				throw new \Exception("Fehler beim Wechsel in das Verzeichnis '" . $this->remoteFolder . "' auf dem DWD FTP-Server.");
			}

			// Starte Verarbeitung der Dateien
			echo PHP_EOL . "*** Verarbeite alle Dateien auf dem DWD FTP Server" . PHP_EOL;


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