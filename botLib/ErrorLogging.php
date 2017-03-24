<?php
/**
 * Wetterwarnung-Downloader für neuthardwetter.de by Jens Dutzi
 *
 * @package    blog404de\WetterScripts
 * @subpackage ErrorLogging
 * @author     Jens Dutzi <jens.dutzi@tf-network.de>
 * @copyright  2012-2017 Jens Dutzi
 * @version    2.0.1-dev
 * @license    MIT
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

use Exception;

/**
 * Error-Logging Klasse
 */
class ErrorLogging {
	/** @var array E-Mail Absender/Empfänger in ["empfaenger"] und ["absender"] */
	private $logToMail = [];

	/** @var string Pfad zur Log-Datei */
	private $logToFile = "";

	/**
	 * Fehler innerhalb der Anwendung verarbeiten
	 *
	 * @param Exception $e
	 * @param string $tmpPath
	 */
	protected function logError(Exception $e, string $tmpPath = NULL) {
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
		if($tmpPath !== false && !is_null($tmpPath)) {
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
			if($writeFile === false) {
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
				if ($sentMail === false) {
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