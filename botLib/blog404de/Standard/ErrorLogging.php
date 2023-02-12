<?php

declare(strict_types=1);

/*
 *  WarnParser für neuthardwetter.de by Jens Dutzi
 *
 *  @package    blog404de\WetterWarnung
 *  @author     Jens Dutzi <jens.dutzi@tf-network.de>
 *  @copyright  Copyright (c) 2012-2020 Jens Dutzi (http://www.neuthardwetter.de)
 *  @license    https://github.com/Blog404DE/WetterwarnungDownloader/blob/master/LICENSE.md
 *  @version    v3.2.0
 *  @link       https://github.com/Blog404DE/WetterwarnungDownloader
 */

namespace blog404de\Standard;

use Exception;
use RuntimeException;

/**
 * Generische Error-Logging Klasse für NeuthardWetter.de-Scripte.
 *
 * @SuppressWarnings("ExitExpression")
 */
class ErrorLogging {
    /** @var array E-Mail Absender/Empfänger in ["empfaenger"] und ["absender"] */
    private array $logToMailSettings = [];

    /** @var array Pfad zur Log-Datei */
    private array $logToFileSettings = [];

    /** @var bool Trace-Log sanfügen im Fehlerfall */
    private bool $withTrace = false;

    /**
     * Fehler innerhalb der Anwendung verarbeiten.
     *
     * @param Exception   $exception Inhalt der Exception
     * @param string|null $tmpPath   Temporär-Pfad
     */
    public function logError(Exception $exception, ?string $tmpPath = ''): void {
        // Zeitpunkt
        $strDate = date('Y-m-d H:i:s');

        // Fehler-Ausgabe erzeugen:
        $longText = sprintf(
            PHP_EOL .
            'Fehler im Programmablauf:' . PHP_EOL .
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
            '%s - %s:%d - %s',
            date('Y-m-d H:i:s'),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getMessage()
        );

        // Temporär-Pfad bei Bedarf löschen
        if ('' !== $tmpPath) {
            if (!$this->cleanTempFiles($tmpPath)) {
                $shortText .= ' - Cleanup: temporärer Ordner (' . $tmpPath . ') kann nicht gelöscht werden';
                $longText .= PHP_EOL . "\tCleanup Fehler beim löschen des temporären Ordner (" . $tmpPath . ')';
            } else {
                $shortText .= ' - Cleanup: temporärer Ordner gelöscht';
                $longText .= PHP_EOL . "\tCleanup: temporärer Ordner gelöscht";
            }
        }

        // Error-Trace noch hinzufügen?
        if ($this->withTrace) {
            // Trace bei Bedarf anfügen
            $longText .= PHP_EOL . PHP_EOL . sprintf(
                "\tError-Trace:" . PHP_EOL . '%s',
                preg_replace('/^/m', "\t", $exception->getTraceAsString())
            );
        }

        // Loggen in Datei
        if (\array_key_exists('filename', $this->logToFileSettings)) {
            $this->logToFile($shortText);
        }

        // Loggen per E-Mail
        if (\is_array($this->logToMailSettings) && \array_key_exists('empfaenger', $this->logToMailSettings)
            && \array_key_exists('absender', $this->logToMailSettings)) {
            $this->logToMail($longText, $strDate);
        }

        // Ausgabe auf die Konsole
        fwrite(STDOUT, PHP_EOL);
        fwrite(STDERR, $longText);
        fwrite(STDOUT, PHP_EOL);

        exit(-1);
    }

    // Getter / Setter-Methoden

    /**
     * Setter-Methode für logToMail.
     *
     * @param array $logToMail Konfigurations-Array für die Log to E-Mail Funktion
     *
     * @return ErrorLogging
     */
    public function setLogToMailSettings(array $logToMail): self {
        try {
            if (!\is_array($logToMail)) {
                // Error Logging an E-Mail deaktivieren
                $this->logToMailSettings = [];

                return $this;
            }

            if (!\array_key_exists('empfaenger', $logToMail) || !\array_key_exists('absender', $logToMail)
            ) {
                // Error-Logging Konfiguration ist falsch
                $this->logToMailSettings = [];

                throw new RuntimeException("LogToMail benötigt ein Array mit den Keys 'empfaenger' und 'absender'");
            }

            if (!filter_var(
                $logToMail['empfaenger'],
                FILTER_VALIDATE_EMAIL
            ) || !filter_var($logToMail['absender'], FILTER_VALIDATE_EMAIL)
            ) {
                throw new RuntimeException(
                    "LogToMail beinhaltet für die Array-Keys 'empfaenger' oder " .
                    "'absender' keine gültige E-Mail Adresse"
                );
            }

            // Setze LogToMail-Einstellung
            $this->logToMailSettings = $logToMail;
        } catch (Exception $e) {
            $this->logError($e);
        }

        return $this;
    }

    /**
     * Setter-Methode für LogToFile.
     *
     * @param array $logToFile Konfigurationsparameter für die Log-To-File Funktion
     *
     * @throws Exception
     *
     * @return ErrorLogging
     */
    public function setLogToFileSettings(array $logToFile): self {
        try {
            $filename = $logToFile['filename'];

            if (file_exists($filename) && is_writable($filename)) {
                // Datei existiert und kann geschrieben werden
                $this->logToFileSettings = $logToFile;
            } elseif (!file_exists($filename) && is_writable(\dirname($filename))) {
                if (touch($filename)) {
                    // Nicht existierende Log-Datei erfolgreich angelegt
                    $this->logToFileSettings = $logToFile;
                } else {
                    // Log-Datei kann nicht neu angelegt werden
                    $this->logToFileSettings = [];

                    throw new RuntimeException('Fehler beim anlegen der Log-Datei in: ' . $filename);
                }
            } else {
                throw new RuntimeException(
                    "Es besteht kein Schreib-Zugriff um Log-Datei '" . basename($filename) . "''" .
                    ' in den angegebenen Ordner zu speichern'
                );
            }
        } catch (Exception $e) {
            $this->logError($e);
        }

        return $this;
    }

    /**
     * Getter-Methode für logToMail.
     */
    public function getLogToMailSettings(): array {
        return $this->logToMailSettings;
    }

    /**
     * Getter-Methode für LogToFile.
     */
    public function getLogToFileSettings(): array {
        return $this->logToFileSettings;
    }

    /**
     * Getter-Methode für Tracelog-Anzeige.
     */
    public function isWithTrace(): bool {
        return $this->withTrace;
    }

    /**
     * Setter-Methode für Tracelog-Anzeige.
     *
     * @param bool $withTrace
     */
    public function setWithTrace(bool $withTrace): void {
        $this->withTrace = $withTrace;
    }

    /**
     * Sende Log-Text per E-Mail.
     *
     * @param string $longText Komplette Fehlermeldung
     * @param string $strDate  Zeitpunkt der Fehlermeldung
     *
     * @return bool
     */
    private function logToMail(string $longText, string $strDate): bool {
        try {
            $mailHeader = sprintf(
                "From: Wetterwarn-Bot <%s>\r\n" .
                "Reply-To: Wetter-Bot <%s>\r\n" .
                "X-Mailer: Wetter-Bot by tfnApps.de\r\n" .
                "X-Priority: 1 (Higuest)\r\n" .
                "X-MSMail-Priority: High\r\n" .
                "Importance: High\r\n",
                $this->logToMailSettings['absender'],
                $this->logToMailSettings['empfaenger']
            );
            $mailBetreff = 'Fehler beim verarbeiten der Wetterwarndaten: ' . $strDate;

            if (!mail($this->logToMailSettings['empfaenger'], $mailBetreff, $longText, $mailHeader)) {
                throw new RuntimeException(
                    'Fehler beim senden der Fehler-Email an ' . $this->logToMailSettings['empfaenger']
                );
            }

            return true;
        } catch (Exception $e) {
            $this->logError($e);

            return false;
        }
    }

    /**
     * Schreibe Log-Text in Datei.
     *
     * @param string $shortText Kurzer Text zur Fehlermeldung
     *
     * @return bool
     */
    private function logToFile(string $shortText): bool {
        try {
            $writeFile = file_put_contents(
                $this->logToFileSettings['filename'],
                $shortText . PHP_EOL,
                FILE_APPEND
            );
            if (!$writeFile) {
                throw new RuntimeException(
                    'Fehler beim schreiben der ErrorLog-Datei ' . $this->logToFileSettings['filename']
                );
            }

            return true;
        } catch (Exception $e) {
            $this->logError($e);

            return false;
        }
    }

    /**
     * Entferne temporäre Dateien.
     *
     * @param string $tmpPath Temporär-Pfad
     *
     * @return bool
     */
    private function cleanTempFiles(string $tmpPath): bool {
        try {
            // Lösche evntuell vorhandenes temporäre Verzeichnis
            $tmpclean = true;
            if (false !== $tmpPath) {
                $toolbox = new Toolbox();
                $tmpclean = $toolbox->removeTempDir($tmpPath);
            }

            return $tmpclean;
        } catch (Exception $e) {
            $this->logError($e);

            return false;
        }
    }
}
