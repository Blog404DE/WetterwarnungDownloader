<?php

declare(strict_types=1);

/*
 *  WarnParser für neuthardwetter.de by Jens Dutzi
 *
 *  @package    blog404de\WetterWarnung
 *  @author     Jens Dutzi <jens.dutzi@tf-network.de>
 *  @copyright  Copyright (c) 2012-2020 Jens Dutzi (http://www.neuthardwetter.de)
 *  @license    https://github.com/Blog404DE/WetterwarnungDownloader/blob/master/LICENSE.md
 *  @version    v3.3.1
 *  @link       https://github.com/Blog404DE/WetterwarnungDownloader
 */

namespace blog404de\WetterWarnung\Network;

use DateTime;
use DateTimeZone;
use Exception;
use RuntimeException;

/**
 * Netzwerk-Funktionen dem WarnParser für den Download
 * und aufbereiten der Wetterwarnungen vom DWD Open Data
 * FTP Server.
 */
class Network {
    /** @var mixed Link identifier der FTP Verbindung */
    private mixed $ftpConnectionId;

    /** @var bool Verwende eine passive Verbindung zum FTP Server */
    private bool $ftpPassiv = false;

    /** @var string Remote Folder auf dem DWD FTP Server mit den Wetterwarnungen */
    private string $remoteFolder = '/weather/alerts/cap/COMMUNEUNION_DWD_STAT';

    /** @var string Lokaler Ordner in dem die Wetterwarnungen gespeichert werden */
    private string $localFolder = '';

    /**
     * Verbindung vom FTP Server trennen.
     *
     * @throws Exception
     */
    public function disconnectFromFTP(): void {
        // Schließe Verbindung, sofern notwendig
        if ($this->ftpConnectionId) {
            echo PHP_EOL . '*** Schließe Verbindung zum DWD-FTP Server' . PHP_EOL;
            ftp_close($this->ftpConnectionId);
            echo "\t-> Verbindung zu erfolgreich geschlossen." . PHP_EOL;
        }
    }

    /**
     * Verbindung zum DWD FTP Server aufbauen.
     *
     * @param string $host     FTP Host des DWD
     * @param string $username FTP Username des DWD
     * @param string $password FTP Passwort des DWD
     *
     * @throws Exception
     */
    public function connectToFTP(string $host, string $username, string $password): void {
        echo '*** Baue Verbindung zum DWD-FTP Server auf.' . PHP_EOL;

        // FTP-Verbindung aufbauen
        $this->ftpConnectionId = ftp_connect($host);
        if (false === $this->ftpConnectionId) {
            throw new RuntimeException(
                'FTP Verbindungsaufbau zu ' . $host . ' ist fehlgeschlagen' . PHP_EOL
            );
        }

        // Login mit Benutzername und Passwort
        $loginResult = ftp_login($this->ftpConnectionId, $username, $password);

        // Verbindung überprüfen
        if (!$loginResult) {
            throw new RuntimeException(
                'Verbindungsaufbau zu zu ' . $host . ' mit Benutzername ' . $username . ' fehlgeschlagen.'
            );
        }
        echo "\t-> Verbindungsaufbau zu " . $host . ' mit Benutzername ' . $username . ' erfolgreich' . PHP_EOL;

        // Auf passive Nutzung umschalten
        if (true === $this->ftpPassiv) {
            echo "\t-> Schalte auf Passive Verbindung" . PHP_EOL;
            ftp_pasv($this->ftpConnectionId, true);
        }
    }

    /**
     * Lade aktuelle Wetterwarnungen vom DWD FTP Server.
     *
     * @throws Exception
     */
    public function updateFromFTP(): void {
        // Starte Verarbeitung der Dateien
        echo PHP_EOL . '*** Verarbeite alle Dateien auf dem DWD FTP Server' . PHP_EOL;

        // Prüfe ob Verbindung aktiv ist
        if (!$this->ftpConnectionId) {
            throw new RuntimeException('FTP Verbindung steht nicht mehr zur Verfügung.');
        }

        // Versuche, in das benötigte Verzeichnis zu wechseln
        echo '-> Wechsle in das Verzeichnis: ' . ftp_pwd($this->ftpConnectionId) . PHP_EOL;
        if (!ftp_chdir($this->ftpConnectionId, $this->remoteFolder)) {
            throw new RuntimeException(
                "Fehler beim Wechsel in das Verzeichnis '" . $this->remoteFolder . "' auf dem DWD FTP-Server."
            );
        }

        // Verzeichnisliste auslesen und sortieren
        $arrFTPContent = ftp_nlist($this->ftpConnectionId, '.');
        if (false === $arrFTPContent) {
            throw new RuntimeException(
                'Fehler beim auslesen des Verezichnis ' . $this->remoteFolder . ' auf dem DWD FTP-Server.'
            );
        }
        echo '-> Liste der auf dem DWD Server vorhandenen Wetterdaten herunterladen' . PHP_EOL;

        // Erstelle Download-Liste
        $arrDownloadList = [];
        if (\count($arrFTPContent) > 0) {
            $defaultfilename = 'Z_CAP_C_EDZW_LATEST_PVW_STATUS_PREMIUMDWD_COMMUNEUNION_DE.zip';
            if (\in_array($defaultfilename, $arrFTPContent, true)) {
                // Verwende vom DWD erzeugte Symlink
                echo '-> Erzeuge Download-Liste im Standard-Modus:' . PHP_EOL;

                $fileDate = ftp_mdtm($this->ftpConnectionId, $defaultfilename);
                $detectMode = 'via FTP';
                if (!$fileDate) {
                    throw new RuntimeException(
                        'Fehler beim ermitteln des Änderungs-Zeitpunkts der Datei ' . $defaultfilename
                    );
                }

                echo "\t" . $defaultfilename . ' => ' . date('d.m.Y H:i', $fileDate) .
                    ' (DE / ' . $detectMode . ')' . PHP_EOL;
                $arrDownloadList[$defaultfilename] = $fileDate;
            } else {
                // Fall-Back: Symlink zur aktuellsten Besser
                echo '-> Erzeuge Download-Liste im Fail-Safe Modus:' . PHP_EOL;
                $arrDownloadList = $this->getCurrentWetterwarnungViaFallback($arrFTPContent);
            }
        }

        // Starte Download der Liste
        $this->downloadFromFTP($arrDownloadList);
    }

    /**
     * Lösche nicht mehr benötigte Dateien.
     *
     * @throws Exception
     */
    public function cleanLocalDownloadFolder(): void {
        // Starte Verarbeitung der Dateien
        echo PHP_EOL . '*** Lösche veraltete Wetterwarnungen aus Cache-Ordner.' . PHP_EOL;

        // Erzeuge Array mit allen bereits vorhandenen Dateien und bestimmte das Alter der Datei
        $localFiles = [];
        $localZipFiles = glob($this->localFolder . \DIRECTORY_SEPARATOR . '*.zip');
        if (\is_array($localZipFiles)) {
            foreach ($localZipFiles as $filename) {
                $createtime = filectime($filename);
                if (false !== $createtime && is_file($filename)) {
                    // Übertrage Datei in Array
                    $localFiles[filectime($filename)] = basename($filename);
                } else {
                    // Zeitpunkt der Datei kann nicht festgestellt werden
                    throw new RuntimeException(
                        'Fehler beim ermitteln des alters der Datei ' . basename($filename) .
                        ' im Download-Cache Ordner oder der Datei-Typ kann nicht bestimmt werden.'
                    );
                }
            }

            // Dateiliste sortieren
            ksort($localFiles, SORT_NUMERIC);
            $localFiles = array_reverse($localFiles);
        }

        // Array $localFiles aufsplitten um zu löschenende Dateien zu ermitteln (alle bis auf die älteste Datei)
        $obsoletFiles = array_splice($localFiles, 1);

        // Starte Löschvorgang
        if (\count($obsoletFiles) > 0) {
            echo '-> Starte Löschvorgang ' . PHP_EOL;
            foreach ($obsoletFiles as $filename) {
                echo "\tLösche veraltete Wetterwarnung-Datei " . $filename . ': ';
                if (!unlink($this->localFolder . \DIRECTORY_SEPARATOR . $filename)) {
                    throw new RuntimeException(
                        "Fehler beim aufräumen des Caches: '" .
                        $this->localFolder . \DIRECTORY_SEPARATOR . $filename .
                        "'' konnte nicht erfolgreich gelöscht werden."
                    );
                }
                echo '-> Datei gelöscht.' . PHP_EOL;
            }
        } else {
            echo "\t-> Es muss keine Datei gelöscht werden" . PHP_EOL;
        }
    }

    /**
     * Getter-Methode um zu prüfen ob FTP-Passiv Modus aktiviert ist.
     */
    public function isFtpPassiv(): bool {
        return $this->ftpPassiv;
    }

    /**
     * Setter-Methode um FTP-Passiv Modus zu aktivieren.
     *
     * @param bool $ftpPassiv Konfigurationsparameter für passive FTP Nutzung
     */
    public function setFtpPassiv(bool $ftpPassiv): void {
        $this->ftpPassiv = $ftpPassiv;
    }

    /**
     * Setter für $remoteFolder.
     *
     * @param string $remoteFolder Ordner auf dem DWD FTP Server in dem sich die Warnungen befinden
     */
    public function setRemoteFolder(string $remoteFolder): void {
        $this->remoteFolder = $remoteFolder;
    }

    /**
     * Getter für $localFolder.
     */
    public function getLocalFolder(): string {
        return $this->localFolder;
    }

    /**
     * Setter für $localFolder.
     *
     * @param string $localFolder Ordner in denen die Dateien vom DWD-FTP Server gespeichert werden
     */
    public function setLocalFolder(string $localFolder): void {
        $this->localFolder = $localFolder;
    }

    /**
     * Ermittle die aktuellste Wetterwarnung-Datei durch Parsen der Dateiliste (FallBack-Modus).
     *
     * @param array $arrFTPContent Inhalt des Verzeichnisses auf dem DWD FTP Server
     *
     * @throws Exception
     */
    private function getCurrentWetterwarnungViaFallback(array $arrFTPContent): array {
        // Filter erzeugen, um die Dateien zu ermitteln, die heute erzeugt wurden
        $searchTime = new DateTime('now', new DateTimeZone('GMT'));
        $fileFilter = $searchTime->format('Ymd');

        // Ermittle das Datum für die Dateien
        $arrDownloadList = [];
        foreach ($arrFTPContent as $filename) {
            // Filtere nach den Wetterwarnungen vom heutigen Tag
            if (str_contains($filename, $fileFilter)) {
                // Übernehme Datei in zu-bearbeiten Liste
                $regs = [];
                $extractFileInfo = preg_match(
                    '/^(?<Prefix>\w_\w{3}_\w_\w{4}_)(?<Datum>\d{14})' .
                    '(?<Postfix>_\w{3}_STATUS_PREMIUMDWD_COMMUNEUNION_)' .
                    '(?<Language>DE|EN)(?<Filetype>.zip)$/',
                    $filename,
                    $regs
                );

                if ($extractFileInfo) {
                    $dateTimeFormater = new DateTime();
                    $dateFileM = $dateTimeFormater::createFromFormat('YmdHis', $regs['Datum'], new DateTimeZone('UTC'));
                    if (false !== $dateFileM) {
                        $fileDate = $dateFileM->getTimestamp();
                        $detectMode = 'via RegExp';
                    } else {
                        $fileDate = ftp_mdtm($this->ftpConnectionId, $filename);
                        $detectMode = 'via FTP / Lesen des Datums fehlgeschlagen';
                    }
                } else {
                    $fileDate = ftp_mdtm($this->ftpConnectionId, $filename);
                    $detectMode = 'via FTP / Lesen des Dateinamens fehlgeschlagen';
                }

                // Sprache feststellen
                $language = $regs['Language'];

                // Bei deutscher Sprache → verarbeiten
                if ('DE' === mb_strtoupper($language)) {
                    echo "\t" . $filename . ' => ' . date('d.m.Y H:i', $fileDate) . ' (' . $language . ' / ' . $detectMode . ')' . PHP_EOL;
                    $arrDownloadList[$filename] = $fileDate;
                }
            }
        }

        // Dateiliste sortieren
        arsort($arrDownloadList, SORT_NUMERIC);
        array_splice($arrDownloadList, 1);

        return $arrDownloadList;
    }

    /**
     * Lade die ermittelte Wetterwarnung-Datei(en) herunter.
     *
     * @param array $arrDownloadList Array mit zu herunterladenden Dateien
     *
     * @throws Exception
     */
    private function downloadFromFTP(array $arrDownloadList): void {
        // Starte Download der aktuellsten Warn-Datei
        if (\count($arrDownloadList) > 0) {
            // Beginne Download
            echo '-> Starte den Download der aktuellsten Warn-Datei:' . PHP_EOL;

            foreach ($arrDownloadList as $filename => $remoteFileMTime) {
                $localFile = $this->localFolder . \DIRECTORY_SEPARATOR . $filename;

                // Ermittle Zeitpunkt der letzten Modifikation der lokalen Datei
                $localFileMTime = filemtime($localFile);

                if (false === $localFileMTime) {
                    // Da keine lokale Datei existiert, Zeitpunkt in die Vergangenheit setzen
                    $localFileMTime = -1;
                }

                if ($remoteFileMTime !== $localFileMTime) {
                    // Öffne lokale Datei
                    /** @noinspection FopenBinaryUnsafeUsageInspection */
                    $localFileHandle = fopen($localFile, 'w');
                    if (!$localFileHandle) {
                        throw new RuntimeException('Kann ' . $localFile . ' nicht zum schreiben öffnen');
                    }

                    /*
                     *   Reserved for PHP 8.1 and later:
                     *
                     *   if (!\is_resource($this->ftpConnectionId) && !$this->ftpConnectionId instanceof FTP/Connection) {
                     *    throw new RuntimeException('Verbindung zum FTP Server unterbrochen');
                     *   }
                    */

                    if (ftp_fget($this->ftpConnectionId, $localFileHandle, $filename)) {
                        if (-1 === $localFileMTime) {
                            echo "\tDatei " . basename($localFile) . ' wurde erfolgreich heruntergeladen ' .
                                '(Remote: ' . date('d.m.Y H:i:s', $remoteFileMTime) . ').' . PHP_EOL;
                        } else {
                            echo "\tDatei " . basename($localFile) . ' wurde erneut erfolgreich heruntergeladen ' .
                                '(Lokal: ' . date('d.m.Y H:i:s', $localFileMTime) . ' / ' .
                                'Remote: ' . date('d.m.Y H:i:s', $remoteFileMTime) . ').' . PHP_EOL;
                        }
                    } else {
                        throw new RuntimeException(sprintf("\tDatei %s war nicht erfolgreich.", $localFile));
                    }

                    // Schließe Datei-Handle
                    fclose($localFileHandle);

                    // Zeitstempel der lokalen Datei identisch zur Remote-Datei setzen (für Cache-Funktion)
                    touch($localFile, $remoteFileMTime);
                } else {
                    echo sprintf("\tDatei %s existiert bereits im lokalen Download-Ordner.", $localFile) . PHP_EOL;
                }
            }
        }
    }
}
