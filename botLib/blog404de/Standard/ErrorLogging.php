<?php
/**
 * Standard-Klassen für neuthardwetter.de by Jens Dutzi - ErrorLogging.php
 *
 * @package    blog404de\WetterWarnung
 * @author     Jens Dutzi <jens.dutzi@tf-network.de>
 * @copyright  Copyright (c) 2012-2018 Jens Dutzi (http://www.neuthardwetter.de)
 * @license    https://github.com/Blog404DE/WetterwarnungDownloader/blob/master/LICENSE.md
 * @version    v3.0.0
 * @link       https://github.com/Blog404DE/WetterwarnungDownloader
 */

namespace blog404de\Standard;

use Exception;

/**
 * Generische Error-Logging Klasse für NeuthardWetter.de-Scripte
 *
 * @package blog404de\WetterScripts
 * @SuppressWarnings("ExitExpression")
 */
class ErrorLogging
{
    /** @var array E-Mail Absender/Empfänger in ["empfaenger"] und ["absender"] */
    private $logToMailSettings = [];

    /** @var array Pfad zur Log-Datei */
    private $logToFileSettings = [];

    /** @var boolean Trace-Log sanfügen im Fehlerfall */
    private $withTrace = false;

    /**
     * Fehler innerhalb der Anwendung verarbeiten
     *
     * @param Exception $exception
     * @param string $tmpPath
     */
    public function logError(Exception $exception, string $tmpPath = "")
    {
        // Zeitpunkt
        $strDate = date("Y-m-d H:i:s");

        // Fehler-Ausgabe erzeugen:
        $longText = sprintf(
            PHP_EOL .
            "Fehler im Programmablauf:" . PHP_EOL .
            "\tZeitpunkt: %s" . PHP_EOL .
            "\tFehlermeldung: %s" . PHP_EOL .
            "\tDatei: %s" . PHP_EOL .
            "\tZeile: %d",
            $strDate,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );

        $shortText = sprintf(
            "%s - %s:%d - %s",
            date("Y-m-d H:i:s"),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getMessage()
        );

        // Temporär-Pfad bei Bedarf löschen
        if ($tmpPath !== "") {
            if (!$this->cleanTempFiles($tmpPath)) {
                $shortText = $shortText . " - Cleanup: temporärer Ordner (" . $tmpPath . ") kann nicht gelöscht werden";
                $longText = $longText .
                    PHP_EOL .
                    "\tCleanup Fehler beim löschen des temporären Ordner (" . $tmpPath . ")";
            } else {
                $shortText = $shortText . " - Cleanup: temporärer Ordner gelöscht";
                $longText = $longText . PHP_EOL . "\tCleanup: temporärer Ordner gelöscht";
            }
        }

        // Error-Trace noch hinzufügen?
        if ($this->withTrace) {
            // Trace bei Bedarf anfügen
            $longText = $longText . PHP_EOL . PHP_EOL .
                sprintf(
                    "\tError-Trace:" . PHP_EOL . "%s",
                    preg_replace("/^/m", "\t", $exception->getTraceAsString())
                );
        }

        // Loggen in Datei
        if (array_key_exists("filename", $this->logToFileSettings)) {
            $this->logToFile($shortText);
        }

        // Loggen per E-Mail
        if (is_array($this->logToMailSettings)) {
            if (array_key_exists("empfaenger", $this->logToMailSettings) &&
                array_key_exists("absender", $this->logToMailSettings)
            ) {
                $this->logToMail($longText, $strDate);
            }
        }

        // Ausgabe auf die Konsole
        fwrite(STDOUT, PHP_EOL);
        fwrite(STDERR, $longText);
        fwrite(STDOUT, PHP_EOL);

        exit(-1);
    }

    /**
     * Sende Log-Text per E-Mail
     *
     * @param string $longText
     * @param string $strDate
     * @return bool
     */
    private function logToMail(string $longText, string $strDate)
    {
        try {
            $mailHeader = sprintf(
                "From: Wetterwarn-Bot <%s>\r\n" .
                "Reply-To: Wetter-Bot <%s>\r\n" .
                "X-Mailer: Wetter-Bot by tfnApps.de\r\n" .
                "X-Priority: 1 (Higuest)\r\n" .
                "X-MSMail-Priority: High\r\n" .
                "Importance: High\r\n",
                $this->logToMailSettings["absender"],
                $this->logToMailSettings["empfaenger"]
            );
            $mailBetreff = "Fehler beim verarbeiten der Wetterwarndaten: " . $strDate;

            if (!mail($this->logToMailSettings["empfaenger"], $mailBetreff, $longText, $mailHeader)) {
                throw new Exception(
                    "Fehler beim senden der Fehler-Email an " . $this->logToMailSettings["empfaenger"]
                );
            }

            return true;
        } catch (\Exception $e) {
            $this->logError($e);
            return false;
        }
    }


    /**
     * Schreibe Log-Text in Datei
     *
     * @param string $shortText
     * @return bool
     */
    private function logToFile(string $shortText)
    {
        try {
            $writeFile = file_put_contents(
                $this->logToFileSettings["filename"],
                $shortText . PHP_EOL,
                FILE_APPEND
            );
            if (!$writeFile) {
                throw new Exception(
                    "Fehler beim schreiben der ErrorLog-Datei " . $this->logToFileSettings["filename"]
                );
            }

            return true;
        } catch (\Exception $e) {
            $this->logError($e);
            return false;
        }
    }

    /**
     * Entferne temporäre Dateien
     *
     * @param $tmpPath
     * @return boolean
     */
    private function cleanTempFiles($tmpPath)
    {
        try {
            // Lösche evntuell vorhandenes Temporäre Verzeichnis
            $tmpclean = true;
            if ($tmpPath !== false && !is_null($tmpPath)) {
                $toolbox = new Toolbox();
                $tmpclean = $toolbox->removeTempDir($tmpPath);
            }

            return $tmpclean;
        } catch (\Exception $e) {
            $this->logError($e);
            return false;
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
    public function setLogToMailSettings(array $logToMail): ErrorLogging
    {
        try {
            if (is_array($logToMail)) {
                if (array_key_exists("empfaenger", $logToMail) &&
                    array_key_exists("absender", $logToMail)
                ) {
                    if (filter_var($logToMail["empfaenger"], FILTER_VALIDATE_EMAIL) &&
                        filter_var($logToMail["absender"], FILTER_VALIDATE_EMAIL)
                    ) {
                        $this->logToMailSettings = $logToMail;
                    } else {
                        throw new Exception(
                            "LogToMail beinhaltet für die Array-Keys 'empfaenger' oder " .
                            "'absender' keine gültige E-Mail Adresse"
                        );
                    }
                } else {
                    // Error-Logging Konfiguration ist falsch
                    $this->logToMailSettings = [];
                    throw new Exception("LogToMail benötigt ein Array mit den Keys 'empfaenger' und 'absender'");
                }
            } else {
                // Error Logging an E-Mail deaktivieren
                $this->logToMailSettings = [];
            }
        } catch (\Exception $e) {
            $this->logError($e);
        }

        return $this;
    }

    /**
     * Setter-Methode für LogToFile
     *
     * @param array $logToFile
     * @return ErrorLogging
     * @throws \Exception
     */
    public function setLogToFileSettings(array $logToFile): ErrorLogging
    {
        try {
            $filename = $logToFile["filename"];

            if (file_exists($filename) && is_writeable($filename)) {
                // Datei existiert und kann geschrieben werden
                $this->logToFileSettings = $logToFile;
            } elseif (!file_exists($filename) && is_writeable(dirname($filename))) {
                if (touch($filename)) {
                    // Nicht existierende Log-Datei erfolgreich angelegt
                    $this->logToFileSettings = $logToFile;
                } else {
                    // Log-Datei kann nicht neu angelegt werden
                    $this->logToFileSettings = [];
                    throw new Exception("Fehler beim anlegen der Log-Datei in: " . $filename);
                }
            } else {
                throw new Exception(
                    "Es besteht kein Schreib-Zugriff um Log-Datei '" . basename($filename) . "''" .
                    " in den angegebenen Ordner zu speichern"
                );
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
    public function getLogToMailSettings(): array
    {
        return $this->logToMailSettings;
    }

    /**
     * Getter-Methode für LogToFile
     * @return array
     */
    public function getLogToFileSettings(): array
    {
        return $this->logToFileSettings;
    }

    /**
     * Getter-Methode für Tracelog-Anzeige
     * @return bool
     */
    public function isWithTrace(): bool
    {
        return $this->withTrace;
    }

    /**
     * Setter-Methode für Tracelog-Anzeige
     * @param bool $withTrace
     */
    public function setWithTrace(bool $withTrace)
    {
        $this->withTrace = $withTrace;
    }
}