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

namespace blog404de\WetterWarnung;

use blog404de\Standard;
use Exception;
use RuntimeException;
use SimpleXMLElement;

/**
 * Parser-Hauptklasse für die Wetter-Warnungen des DWD.
 */
class WetterWarnung extends Save\SaveToFile {
    use Extensions;

    /** @var Network\Network Instanz der Warnparser/Netzwerk Klasse */
    public Network\Network $network;

    /** @var string Lokale Datei in der die verarbeiteten Wetterwarnungen gespeichert werden */
    private string $localJsonFile = '';

    /** @var string Ordner für temporäre Dateien */
    private string $tmpFolder = '';

    /** @var array Aktuelle geparsten Wetterwarnungen */
    private array $wetterWarnungen = [];

    /** @var Standard\Toolbox Instanz der generischen Toolbox-Klasse */
    private Standard\Toolbox $toolbox;

    /**
     * WarnParser constructor.
     *
     * @throws Exception
     */
    public function __construct() {
        // Parent Constructor aufrufen
        parent::__construct();

        // Setze Location-Informationen für Deutschland
        setlocale(LC_TIME, 'de_DE.UTF-8');
        date_default_timezone_set('Europe/Berlin');

        // Via CLI gestartet?
        if (\PHP_SAPI !== 'cli') {
            throw new RuntimeException('Script darf ausschließlich über die Kommandozeile gestartet werden.');
        }

        // FTP Modul vorhanden?
        if (!\extension_loaded('ftp')) {
            throw new RuntimeException("PHP Modul 'ftp' steht nicht zur Verfügung");
        }

        // FTP Modul vorhanden?
        if (!\extension_loaded('zip')) {
            throw new RuntimeException("PHP Modul 'zip' steht nicht zur Verfügung");
        }

        // FTP Modul vorhanden?
        if (!\extension_loaded('mbstring')) {
            throw new RuntimeException("PHP Modul 'mbstring' steht nicht zur Verfügung");
        }

        // PHP Version prüfen
        if (version_compare(PHP_VERSION, '7.0.0') < 0) {
            throw new RuntimeException(
                'Für das Script wird mindestens PHP7 vorrausgesetzt ' .
                '(PHP 5.6.x wird nur noch mit Sicherheitsupdates bis 31.12.2018 versorgt'
            );
        }

        // Array leeren
        /** @noinspection PropertyInitializationFlawsInspection */
        $this->wetterWarnungen = [];

        // Toolbox instanzieren
        $this->toolbox = new Standard\Toolbox();
    }

    /**
     * Parse lokale Wetterwarnungen nach WarnCellID.
     *
     * @throws Exception
     */
    public function prepareWetterWarnungen(): void {
        // Starte verabeiten der Wetterwarnungen des DWD
        echo PHP_EOL . '*** Starte Vorbereitungen::' . PHP_EOL;

        echo '-> Bereite das Vearbeiten der Wetterwarnungen vor' . PHP_EOL;

        echo "\tLege temporären Ordner an: ";
        $tmpdir = $this->toolbox->tempdir();
        if (!$tmpdir && \is_string($tmpdir)) {
            // Temporär-Ordner kann nicht angelegt werden
            echo 'fehlgeschlagen' . PHP_EOL;

            throw new RuntimeException(
                'Temporär-Ordner kann nicht angelegt werden. Bitte prüfen Sie ob in der php.ini ' .
                "'sys_tmp_dir' oder die Umgebungsvariable 'TMPDIR' gesetzt ist."
            );
        }
        $this->tmpFolder = (string)$tmpdir;
        echo 'erfolgreich (' . $this->tmpFolder . ')' . PHP_EOL;

        // ZIP-Dateien in Temporär-Ordner entpacken
        echo '-> Entpacke die Wetterwarnungen des DWD' . PHP_EOL;
        $zipfiles = glob($this->getLocalFolder() . \DIRECTORY_SEPARATOR . '*.zip') ?: [];
        if (!\is_array($zipfiles)) {
            throw new RuntimeException('Es befindet sich keine ZIP Datei im Download-Ordner.');
        }
        if (\count($zipfiles) > 10) {
            throw new RuntimeException(
                'Mehr als 10 ZIP-Datei im Download-Cache vorhanden - ' .
                'es sollten nie mehr als eine ZIP-Datei vorhanden sein.'
            );
        }
        foreach ($zipfiles as $filename) {
            $this->toolbox->extractZipFile($filename, $this->tmpFolder);
        }
    }

    /**
     * Parsen der Wetterwarnungen.
     *
     * @param int    $warnCellId Warn-Region mittels WarnCellID festlegen
     * @param string $stateCode  Kürzel des Bundeslandes
     *
     * @throws RuntimeException|Exception
     */
    public function parseWetterWarnungen(int $warnCellId, string $stateCode): void {
        // Lade zuletzt verarbeitete Wetterwarnungen
        $this->loadLastWetterWarnungen($this->localJsonFile);

        // Starte parsen der Wetterwarnungen des DWD
        echo PHP_EOL . '*** Verarbeite die Wetterwarnungen:' . PHP_EOL;
        echo '-> Suche in heruntergeladenen Wetterwarnungen nach der WarnCellID ' . $warnCellId . PHP_EOL;

        // Lege Array an für die ermittelten Roh-Warnungen
        $arrRohWarnungen = [];

        // Lese Verzeichnis mit XML Dateien ein
        $localXmlFiles = glob($this->tmpFolder . \DIRECTORY_SEPARATOR . '*.xml');
        if (\is_array($localXmlFiles)) {
            // Parse XML Dateien
            foreach ($localXmlFiles as $xmlFile) {
                // Öffne XML Datei und erzeuge ein XML Objekt aus Datei
                $xml = $this->readXmlFile($xmlFile);

                // Warn-File an Header-Parser übergeben
                $currentWarnData = $this->checkWarnFile($xml, $warnCellId, $stateCode);
                if (!empty($currentWarnData)) {
                    // State noch hinzufügen
                    $currentWarnData['identifier'] = $this->getHeaderIdentifier($xml);
                    $currentWarnData['msgType'] = ucfirst($this->getHeaderMsgType($xml));
                    $currentWarnData['reference'] = $this->getHeaderReference($xml);
                    $arrRohWarnungen[basename($xmlFile)] = $currentWarnData;
                }
            }
        }

        echo '-> Verarbeite alle gefundenen Wetterwarnungen (Anzahl: ' . \count($arrRohWarnungen) . ')' . PHP_EOL;
        if (\count($arrRohWarnungen) > 0) {
            // Durchlaufe alle Warnungen
            foreach ($arrRohWarnungen as $filename => $rawWarnung) {
                echo "\tWetterwarnung aus " . $filename . ':' . PHP_EOL;
                $parsedWarnInfo = $this->collectWarnArray($rawWarnung);
                if (empty($parsedWarnInfo)) {
                    // geparste WetterWarnung ist nicht von belange und wird ignoriert
                    continue;
                }

                // Status-Ausgabe der aktuellen Wetterwarnung
                echo "\t\t* Wetterwarnung über " . $parsedWarnInfo['event'] .
                    ' für ' .
                    $parsedWarnInfo['area'] . ' verarbeitet' . PHP_EOL
                ;

                // Wetterwarnung übernehmen und neu sortieren
                $this->wetterWarnungen[$parsedWarnInfo['hash']] = $parsedWarnInfo;
                asort($this->wetterWarnungen);
            }
        } else {
            echo "\tKeine Warnmeldungen zum verarbeiten vorhanden" . PHP_EOL;
        }
    }

    /**
     * Speichere Wetterwarnungen.
     *
     * @param bool $cleanCache Cache nach dem Speichern aufräumen
     *
     * @throws Exception
     */
    public function saveToFile(bool $cleanCache): bool {
        // Speichere lokale JSON Datei
        echo PHP_EOL . '*** Speichere Wetter-Warnungen in eine Datei: ' . PHP_EOL;
        $status = $this->saveFile($this->wetterWarnungen, $this->localJsonFile);
        if (null === $status) {
            throw new RuntimeException('Fehler beim Speichern der Wetterwarnung in Datei');
        }

        // Cache aufräumen?
        // Lösche Cache-Folder
        if ($cleanCache && '' !== $this->tmpFolder) {
            echo PHP_EOL . '*** Lösche eventuell vorhandenen Cache und temporäre Daten:' . PHP_EOL;
            if (!$this->toolbox->removeTempDir($this->tmpFolder)) {
                echo "\t* Löschen des Ordner " . $this->tmpFolder . ' fehlgeschlagen' . PHP_EOL;

                throw new RuntimeException('Löschen des Temporären Ordner (' . $this->tmpFolder . ') ist fehlgeschlagen.');
            }
            echo "\t* Löschen des Ordner " . $this->tmpFolder . ' erfolgreich' . PHP_EOL;
        }

        return $status;
    }

    // Getter / Setter Funktionen

    /**
     * Setter für $localFolder an die Network-Klasse.
     *
     * @param string $localFolder Lokaler Ordner
     *
     * @throws RuntimeException
     */
    public function setLocalFolder(string $localFolder): void {
        if (is_dir($localFolder)) {
            if (is_writable($localFolder)) {
                // Ordner an Parent-Klasse weitergeben
                parent::setLocalFolder($localFolder);
            } else {
                throw new RuntimeException('In den lokale Ordner ' . $localFolder . ' kann nicht geschrieben werden.');
            }
        } else {
            throw new RuntimeException('Der lokale Ordner ' . $localFolder . ' existiert nicht.');
        }
    }

    /**
     * Setter für $localJsonFile.
     *
     * @param string $localJsonFile Pfad zur XML-Datei mit den der WetterWarnung
     *
     * @throws Exception
     */
    public function setLocalJsonFile(string $localJsonFile): void {
        if (empty($localJsonFile)) {
            throw new RuntimeException('Es wurde keine JSON-Datei zals Ziel für die Wetterwarnungen angegeben.');
        }
        if (file_exists($localJsonFile) && !is_writable($localJsonFile)) {
            // Datei existiert - aber die Schreibrechte fehlen
            throw new RuntimeException(
                'Auf die JSON Datei ' . $localJsonFile .
                    ' mit den geparsten Wetterwarnungen kann nicht schreibend zugegriffen werden'
            );
        }
        if (is_writable(\dirname($localJsonFile))) {
            // Datei existiert nicht - Schreibrechte auf den Ordner existieren
            if (!touch($localJsonFile)) {
                // Leere Datei anlegen ist nicht erfolgreich
                throw new RuntimeException(
                    'Leere JSON Datei für die geparsten Wetterwarnungen konnte nicht angelegt werden'
                );
            }
        } else {
            // Kein Zugriff auf Datei möglich
            throw new RuntimeException(
                'JSON Datei für die geparsten Wetterwarnungen kann nicht in ' .
                    \dirname($localJsonFile) . ' geschrieben werden'
            );
        }

        // Variable setzen
        $this->localJsonFile = $localJsonFile;
    }

    /**
     * Getter-Methode für WetterWarnungen.
     *
     * @return array Liste mit Wetterwarnungen
     */
    public function getWetterWarnungen(): array {
        return $this->wetterWarnungen;
    }

    /**
     * Getter-Methode für Zugriff auf evntl. angelegten Temporär-Ordner.
     *
     * @return string
     */
    public function getTmpFolder(): string {
        return $this->tmpFolder;
    }

    /**
     * XML Datei einlesen und in XML Objekt einlesen.
     *
     * @param string $xmlFile Pfad zur XML-Datei mit den der WetterWarnung
     *
     * @throws Exception
     * @throws RuntimeException
     *
     * @return SimpleXMLElement
     */
    private function readXmlFile(string $xmlFile): SimpleXMLElement {
        echo "\tPrüfe " . basename($xmlFile) .
            ' (' . number_format(round(filesize($xmlFile) / 1024, 2), 2) . ' kbyte): ';

        // Öffne XML Datei zum Lesen
        $content = file_get_contents($xmlFile);
        if (!$content) {
            throw new RuntimeException(PHP_EOL . 'Fehler beim lesen der XML Datei ' . $xmlFile);
        }

        // XML Datei in Parser laden
        $xml = new SimpleXMLElement($content, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        if (!$xml) {
            throw new RuntimeException('Fehler in parseWetterWarnung: Die XML Datei konnte nicht verarbeitet werden.');
        }

        return $xml;
    }
}
