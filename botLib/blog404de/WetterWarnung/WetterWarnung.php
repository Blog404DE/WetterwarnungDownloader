<?php
/**
 * WarnParser für neuthardwetter.de by Jens Dutzi - WetterWarnung.php
 *
 * @package    blog404de\WetterWarnung
 * @author     Jens Dutzi <jens.dutzi@tf-network.de>
 * @copyright  Copyright (c) 2012-2018 Jens Dutzi (http://www.neuthardwetter.de)
 * @license    https://github.com/Blog404DE/WetterwarnungDownloader/blob/master/LICENSE.md
 * @version    v3.0.2
 * @link       https://github.com/Blog404DE/WetterwarnungDownloader
 */

namespace blog404de\WetterWarnung;

use blog404de\WetterWarnung\Save;
use blog404de\Standard;
use Exception;

/**
 * Parser-Hauptklasse für die Wetter-Warnungen des DWD
 *
 * @package blog404de\WetterScripts
 */
class WetterWarnung extends Save\SaveToFile
{
    use Extensions;

    /** @var string $localJsonFile Lokale Datei in der die verarbeiteten Wetterwarnungen gespeichert werden  */
    private $localJsonFile = "";

    /** @var string|bool $tmpFolder Ordner für temporäre Dateien */
    private $tmpFolder = "";

    /** @var array $wetterWarnungen Aktuelle geparsten Wetterwarnungen */
    private $wetterWarnungen = [];

    /** @var Standard\Toolbox Instanz der generischen Toolbox-Klasse */
    private $toolbox;

    /** @var Network\Network Instanz der Warnparser/Netzwerk Klasse */
    public $network;

    /**
     * WarnParser constructor.
     *
     * @throws Exception
     */
    public function __construct()
    {
        // Parent Constructor aufrufen
        parent::__construct();

        // Setze Location-Informationen für Deutschland
        setlocale(LC_TIME, "de_DE.UTF-8");
        date_default_timezone_set("Europe/Berlin");

        // Via CLI gestartet?
        if (php_sapi_name() !== "cli") {
            throw new Exception("Script darf ausschließlich über die Kommandozeile gestartet werden.");
        }

        // FTP Modul vorhanden?
        if (!extension_loaded("ftp")) {
            throw new Exception("PHP Modul 'ftp' steht nicht zur Verfügung");
        }

        // FTP Modul vorhanden?
        if (!extension_loaded("zip")) {
            throw new Exception("PHP Modul 'zip' steht nicht zur Verfügung");
        }

        // FTP Modul vorhanden?
        if (!extension_loaded("mbstring")) {
            throw new Exception("PHP Modul 'mbstring' steht nicht zur Verfügung");
        }

        // PHP Version prüfen
        if (version_compare(PHP_VERSION, "7.0.0") < 0) {
            throw new Exception(
                "Für das Script wird mindestens PHP7 vorrausgesetzt " .
                "(PHP 5.6.x wird nur noch mit Sicherheitsupdates bis 31.12.2018 versorgt"
            );
        }

        // Array leeren
        $this->wetterWarnungen = [];

        // Toolbox instanzieren
        $this->toolbox = new Standard\Toolbox();
    }

    /**
     * Parse lokale Wetterwarnungen nach WarnCellID
     * @throws
     */
    public function prepareWetterWarnungen()
    {
        try {
            // Starte verabeiten der Wetterwarnungen des DWD
            echo PHP_EOL . "*** Starte Vorbereitungen::" . PHP_EOL;

            echo "-> Bereite das Vearbeiten der Wetterwarnungen vor" . PHP_EOL;

            echo "\tLege temporären Ordner an: ";
            $tmpdir = $this->toolbox->tempdir();
            if (!$tmpdir && is_string($tmpdir)) {
                // Temporär-Ordner kann nicht angelegt werden
                echo "fehlgeschlagen" . PHP_EOL;
                throw new Exception(
                    "Temporär-Ordner kann nicht angelegt werden. Bitte prüfen Sie ob in der php.ini " .
                    "'sys_tmp_dir' oder die Umgebungsvariable 'TMPDIR' gesetzt ist."
                );
            }
            $this->tmpFolder  = $tmpdir;
            echo "erfolgreich (" . $this->tmpFolder . ")" . PHP_EOL;

            // ZIP-Dateien in Temporär-Ordner entpacken
            echo "-> Entpacke die Wetterwarnungen des DWD" . PHP_EOL;
            $zipfiles = glob($this->getLocalFolder() . DIRECTORY_SEPARATOR . "*.zip");
            if (count($zipfiles) == 0 || $zipfiles === false) {
                throw new Exception("Es befindet sich keine ZIP Datei im Download-Ordner.");
            } elseif (count($zipfiles) > 10) {
                throw new Exception(
                    "Mehr als 10 ZIP-Datei im Download-Cache vorhanden - " .
                    "es sollten nie mehr als eine ZIP-Datei vorhanden sein."
                );
            }
            foreach ($zipfiles as $filename) {
                $this->toolbox->extractZipFile($filename, $this->tmpFolder);
            }
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Parsen der Wetterwarnungen
     *
     * @param int $warnCellId Warn-Region mittels WarnCellID festlegen
     * @param string $stateCode Kürzel des Bundeslandes
     * @throws Exception
     */
    public function parseWetterWarnungen(int $warnCellId, string $stateCode)
    {
        try {
            // Lade zuletzt verarbeitete Wetterwarnungen
            $this->loadLastWetterWarnungen($this->localJsonFile);

            // Starte parsen der Wetterwarnungen des DWD
            echo PHP_EOL . "*** Verarbeite die Wetterwarnungen:" . PHP_EOL;
            echo "-> Suche in heruntergeladenen Wetterwarnungen nach der WarnCellID " . $warnCellId . PHP_EOL;

            // Lese Verzeichnis mit XML Dateien ein
            $localXmlFiles = glob($this->tmpFolder . DIRECTORY_SEPARATOR . "*.xml");

            // Lege Array an für die ermittelten Roh-Warnungen
            $arrRohWarnungen = [];

            // Parse XML Dateien
            $xml = null;
            foreach ($localXmlFiles as $xmlFile) {
                // Öffne XML Datei und erzeuge ein XML Objekt aus Datei
                $xml = $this->readXmlFile($xmlFile);

                // Warn-File an Header-Parser übergeben
                $currentWarnData = ($this->checkWarnFile($xml, $warnCellId, $stateCode));
                if (!empty($currentWarnData)) {
                    // State noch hinzufügen
                    $currentWarnData["identifier"] = $this->getHeaderIdentifier($xml);
                    $currentWarnData["msgType"] = ucfirst($this->getHeaderMsgType($xml));
                    $currentWarnData["reference"] = (string)$this->getHeaderReference($xml);
                    $arrRohWarnungen[basename($xmlFile)] = $currentWarnData;
                }
            }

            echo "-> Verarbeite alle gefundenen Wetterwarnungen (Anzahl: " . count($arrRohWarnungen) . ")" . PHP_EOL;
            if (count($arrRohWarnungen) > 0) {
                // Durchlaufe alle Warnungen
                foreach ($arrRohWarnungen as $filename => $rawWarnung) {
                    echo("\tWetterwarnung aus " . $filename . ":" . PHP_EOL);
                    $parsedWarnInfo = $this->collectWarnArray($rawWarnung);
                    if (empty($parsedWarnInfo)) {
                        // geparste WetterWarnung ist nicht von belange und wird ignoriert
                        continue;
                    }

                    // Status-Ausgabe der aktuellen Wetterwarnung
                    echo(
                        "\t\t* Wetterwarnung über " . $parsedWarnInfo["event"] .
                        " für " .
                        $parsedWarnInfo["area"] . " verarbeitet" . PHP_EOL
                    );

                    // Wetterwarnung übernehmen und neu sortieren
                    $this->wetterWarnungen[$parsedWarnInfo["hash"]] = $parsedWarnInfo;
                    asort($this->wetterWarnungen);
                }
            } else {
                echo ("\tKeine Warnmeldungen zum verarbeiten vorhanden" . PHP_EOL);
            }
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * XML Datei einlesen und in XML Objekt einlesen
     *
     * @param string $xmlFile Pfad zur XML Datei mit den der WetterWarnung
     * @return \SimpleXMLElement
     * @throws Exception
     */
    private function readXmlFile(string $xmlFile)
    {
        echo("\tPrüfe " . basename($xmlFile) .
            " (" . number_format(round(filesize($xmlFile) / 1024, 2), 2) . " kbyte): ");

        // Öffne XML Datei zum lesen
        $content = @file_get_contents($xmlFile);
        if (!$content) {
            throw new Exception(PHP_EOL . "Fehler beim lesen der XML Datei " . $xmlFile);
        }

        // XML Datei in Parser laden
        $xml = new \SimpleXMLElement($content, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        if (!$xml) {
            throw new Exception("Fehler in parseWetterWarnung: Die XML Datei konnte nicht verarbeitet werden.");
        }

        return $xml;
    }

    /**
     * Speichere Wetterwarnungen
     *
     * @param boolean $cleanCache Cache nach dem speichern aufräumen
     * @return boolean
     * @throws Exception
     */
    public function saveToFile(bool $cleanCache): bool
    {
        // Speichere lokale JSON Datei
        echo(PHP_EOL . "*** Speichere Wetter-Warnungen in eine Datei: " . PHP_EOL);
        $status = $this->saveFile($this->wetterWarnungen, $this->localJsonFile);

        // Cache aufräumen?
        if ($cleanCache) {
            // Lösche Cache-Folder
            if ($this->tmpFolder !== "") {
                echo(PHP_EOL . "*** Lösche eventuell vorhandenen Cache und temporäre Daten:" . PHP_EOL);
                if (!$this->toolbox->removeTempDir($this->tmpFolder)) {
                    echo "\t* Löschen des Ordner " . $this->tmpFolder . " fehlgeschlagen" . PHP_EOL;
                    throw new Exception("Löschen des Temporären Ordner (" . $this->tmpFolder . ") ist fehlgeschlagen.");
                };
                echo "\t* Löschen des Ordner " . $this->tmpFolder . " erfolgreich" . PHP_EOL;
            }
        }

        return $status;
    }

    /*
     *  Getter / Setter Funktionen
     */

    /**
     * Setter für $localFolder an die Network-Klasse
     *
     * @param string $localFolder
     * @throws
     */
    public function setLocalFolder(string $localFolder)
    {
        if (is_dir($localFolder)) {
            if (is_writeable($localFolder)) {
                // Ordner an Parent-Klasse weitergeben
                parent::setLocalFolder($localFolder);
            } else {
                throw new Exception("In den lokale Ordner " . $localFolder . " kann nicht geschrieben werden.");
            }
        } else {
            throw new Exception("Der lokale Ordner " . $localFolder . " existiert nicht.");
        }
    }

    /**
     * Setter für $localJsonFile
     *
     * @param string $localJsonFile Pfad zur XML Datei mit den der WetterWarnung
     * @throws
     */
    public function setLocalJsonFile(string $localJsonFile)
    {
        if (empty($localJsonFile)) {
            throw new Exception("Es wurde keine JSON-Datei zals Ziel für die Wetterwarnungen angegeben.");
        } else {
            if (file_exists($localJsonFile) && !is_writeable($localJsonFile)) {
                // Datei existiert - aber die Schreibrechte fehlen
                throw new Exception(
                    "Auf die JSON Datei " . $localJsonFile .
                    " mit den geparsten Wetterwarnungen kann nicht schreibend zugegriffen werden"
                );
            } elseif (is_writeable(dirname($localJsonFile))) {
                // Datei existiert nicht - Schreibrechte auf den Ordner existieren
                if (!@touch($localJsonFile)) {
                    // Leere Datei anlegen ist nicht erfolgreich
                    throw new Exception(
                        "Leere JSON Datei für die geparsten Wetterwarnungen konnte nicht angelegt werden"
                    );
                }
            } else {
                // Kein Zugriff auf Datei möglich
                throw new Exception(
                    "JSON Datei für die geparsten Wetterwarnungen kann nicht in " .
                    dirname($localJsonFile) . " geschrieben werden"
                );
            }
        }

        // Variable setzen
        $this->localJsonFile = $localJsonFile;
    }

    /**
     * Getter-Methode für WetterWarnungen
     *
     * @return array Liste mit Wetterwarnungen
     */
    public function getWetterWarnungen(): array
    {
        return $this->wetterWarnungen;
    }

    /**
     * Getter-Methode für Zugriff auf evntl. angelegten Temporär-Ordner
     *
     * @return bool|string
     */
    public function getTmpFolder()
    {
        return $this->tmpFolder;
    }
}
