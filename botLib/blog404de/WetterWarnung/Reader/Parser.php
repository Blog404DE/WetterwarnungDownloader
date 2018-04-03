<?php
/**
 * WarnParser für neuthardwetter.de by Jens Dutzi - Parser.php
 *
 * @package    blog404de\WetterWarnung
 * @author     Jens Dutzi <jens.dutzi@tf-network.de>
 * @copyright  Copyright (c) 2012-2018 Jens Dutzi (http://www.neuthardwetter.de)
 * @license    https://github.com/Blog404DE/WetterwarnungDownloader/blob/master/LICENSE.md
 * @version    v3.0.2-dev
 * @link       https://github.com/Blog404DE/WetterwarnungDownloader
 */

namespace blog404de\WetterWarnung\Reader;

use blog404de\WetterWarnung\Network\Network;
use Exception;
use DateTime;
use DateTimeZone;

/**
 * Haupt-Klasse des Parser - beinhaltet alle internen Methoden die zum parsen der Wetterwarnungen benötigt werden
 *
 * @package blog404de\WetterScripts
 */
class Parser extends Network
{
    use ParserAlert;
    use ParserGeo;
    use ParserHeader;

    /**
     * Prüfe Header der Warndatei auf Gültigkeit und übergebe ausschließlich
     * neue Nachrichten der angegebenen WarnCellID
     *
     * @param \SimpleXMLElement $xml XML-Datei des DWD mit den Wetterwarnungen
     * @param string $warnCellId WarnCellID für die eine Warnung ermittelt werden soll
     * @param string $stateCode Die Abkürzung des Bundesland in dem sich die WarnCellID befindet
     * @return array
     * @throws Exception
     */
    final protected function checkWarnFile(\SimpleXMLElement $xml, string $warnCellId, string $stateCode): array
    {
        try {
            // Rohwarnung
            $arrRohWarnungen = [];

            // Prüfe ob Info-Node existiert
            if (!property_exists($xml, "info")) {
                throw new Exception(
                    "Fehler beim parsen der Wetterwarnung: Die XML Datei beinhaltet kein 'info'-Node."
                );
            }

            // Prüfe ob die Warndatei verarbeitet werden muss
            $valid = $this->shouldParseWetterwarnung($xml);
            if ($valid) {
                // Da keine Test-Warnung: beginne Suche nach WarnCellID
                //$info = $xml->{"info"};
                $warnRegionFound = $this->searchForWarnArea($xml, $warnCellId, $stateCode);
                if (!empty($warnRegionFound)) {
                    // Treffer gefunden
                    echo(sprintf(
                        "\tTreffer für %s (%s) / WarnCellID %d gefunden",
                        $warnRegionFound->{"areaDesc"},
                        $warnRegionFound->{"state"},
                        $warnRegionFound->{"warncellid"}
                    ) . PHP_EOL);

                    $arrRohWarnungen = [
                        "warnung" => $xml->{"info"},
                        "region"  => $warnRegionFound
                    ];
                } else {
                    // Kein Treffer
                    echo "\tKein Treffer" . PHP_EOL;
                }
            } else {
                // Da Test-Warnung, diese Warnung nicht zur weiteren Verarbeitung übernehmen
                echo "\t\t-> Testwarnung (ignoriere Inhalt)" . PHP_EOL;
            }

            return $arrRohWarnungen;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Prüfe ob es sich um eine Test-Warnung handelt
     *
     * @param \SimpleXMLElement $eventCodeElement Event-Code Element
     * @return bool
     * @throws Exception
     */
    private function checkForTestWarnung(\SimpleXMLElement $eventCodeElement): bool
    {
        try {
            $testwarnung = false;
            foreach ($eventCodeElement as $eventCode) {
                if (!isset($eventCode->{"valueName"}) || !isset($eventCode->{"value"})) {
                    throw new Exception(
                        "Fehler beim parsen der Wetterwarnung: " .
                        "Die XML Datei beinhaltet kein 'eventCode'->'valueName' bzw. 'value'-Node."
                    );
                }

                // Schaue nach EventName = "II" und prüfe ob der Wert auf 98/99 steht (=Testwarnung)
                if ((string)$eventCode->{"valueName"} == "II" && in_array($eventCode->{"value"}, ["98", "99"])) {
                    // Da Test-Warnung, diese Warnung nicht zur weiteren Verarbeitung übernehmen
                    echo "\t\t-> Testwarnung (ignoriere Inhalt)" . PHP_EOL;
                    $testwarnung = false;
                } else {
                    $testwarnung = true;
                }
            }

            return $testwarnung;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Prüfe ob die Wetterwarnung verarbeitet werden muss
     *
     * @param \SimpleXMLElement $xml XML-Datei des DWD mit den Wetterwarnungen
     * @return boolean
     * @throws Exception
     */
    private function shouldParseWetterwarnung(\SimpleXMLElement $xml)
    {
        $readWarnfile = false;

        // Prüfe um welche Art von Wetter-Warnung es sich handelt (Alert oder Cancel)
        if (strtolower($xml->{"msgType"}) == "alert" || strtolower($xml->{"msgType"}) == "update") {
            // Verarbeite Inhalt der XML Datei (Typ: Alert)
            $wetterWarnung = $xml->{"info"};
            // Prüfe ob es sich um eine Testwarnung handelt
            if (!property_exists($wetterWarnung, "eventCode")) {
                throw new Exception(
                    "Fehler beim parsen der Wetterwarnung: Die XML Datei beinhaltet kein 'eventCode'-Node."
                );
            }

            $readWarnfile = $this->checkForTestWarnung($wetterWarnung->{"eventCode"});
        } elseif (strtolower($xml->{"msgType"}) == "cancel") {
            // Verarbeite Inhalt der XML Datei (Typ: Cancel)
            echo("\t\t -> Stoppe Verarbeitung der Warn-Datei (Cancel-Nachricht muss nicht versendet werden)" . PHP_EOL);
        }

        return $readWarnfile;
    }

    /**
     * Stelle Informationen für die Wetterwarnung in ein Array zusammen
     *
     * @param array $rawWarnung Array mit den relevanten Warn-Daten
     * @return array
     * @throws Exception
     */
    final protected function collectWarnArray(array $rawWarnung): array
    {
        try {
            $parsedWarnInfo = [];
            // Existieren alle benötigten Array-Elemente zum parsen?
            if (!array_key_exists("warnung", $rawWarnung) ||
                !array_key_exists("region", $rawWarnung) ||
                !array_key_exists("identifier", $rawWarnung) ||
                !array_key_exists("msgType", $rawWarnung)
            ) {
                throw new Exception(
                    "Die aktuell verarbeitete Roh-Wetterwarnung ist nicht vollständig - " .
                    "'warnung' oder 'geoinfo' fehlt."
                );
            }

            // Art der Warnung ermitteln
            $parsedWarnInfo["event"] = $this->getAlertEvent($rawWarnung["warnung"]);

            // Bei Bedarf das Event-Icon bestimmen
            if (!empty($this->getLocalIconFolder())) {
                $parsedWarnInfo["eventicon"] = $this->getAlertIcon($rawWarnung["warnung"]);
            }

            // Ermittle Start-/Endzeitpunkt der Wetterwarnung
            $objStartzeit = $this->getAlertStartzeit($rawWarnung["warnung"]);
            $objEndzeit = $this->getAlertEndzeit($rawWarnung["warnung"]);
            $dateCurrent = new DateTime("now", new DateTimeZone("Europe/Berlin"));

            // Prüfe ob Warnung bereits abgelaufen ist und übersprungen werden kann
            if ($objEndzeit->getTimestamp() <= $dateCurrent->getTimestamp() &&
                $objEndzeit->getTimestamp() != $objStartzeit->getTimestamp()
            ) {
                // Warnung ist bereits abgelaufen
                echo ("\t\t* Hinweis: Warnung über " . $parsedWarnInfo["event"] .
                    " ist bereits am " .
                    $objEndzeit->format("d.m.Y H:i:s") .
                    " abgelaufen und wird ingoriert" . PHP_EOL
                );

                return [];
            }

            // Warnung ist nicht abgelaufen, setze ermitteln der Daten fort
            $parsedWarnInfo["startzeit"] = (string)serialize($objStartzeit);
            $parsedWarnInfo["endzeit"] = (string)serialize($objEndzeit);

            // Ermittle Daten aus Header
            $parsedWarnInfo["identifier"] = (string)$rawWarnung["identifier"];
            $parsedWarnInfo["reference"] = (string)$rawWarnung["reference"];
            $parsedWarnInfo["msgType"] = (string)$rawWarnung["msgType"];

            // Ermittle Sonstige Informationen der Wetterwarnung
            $parsedWarnInfo["severity"] = $this->getAlertSeverity($rawWarnung["warnung"]);
            $parsedWarnInfo["urgency"] = $this->getAlertUrgency($rawWarnung["warnung"]);
            $parsedWarnInfo["warnstufe"] = $this->getAlertWarnstufe($rawWarnung["warnung"]);
            $parsedWarnInfo["headline"] = $this->getAlertHeadline($rawWarnung["warnung"]);
            $parsedWarnInfo["description"] = $this->getAlertDescription($rawWarnung["warnung"]);
            $parsedWarnInfo["instruction"] = $this->getAlertInstruction($rawWarnung["warnung"]);
            $parsedWarnInfo["sender"] = $this->getAlertSender($rawWarnung["warnung"]);
            $parsedWarnInfo["web"] = $this->getAlertWeb($rawWarnung["warnung"]);

            // Positionsdaten aus dem Geo-Feld ermitteln
            $parsedWarnInfo["warncellid"] = $this->getRegioWarncellid($rawWarnung["region"]);
            $parsedWarnInfo["area"] = $this->getRegionArea($rawWarnung["region"]);
            $parsedWarnInfo["stateShort"] = $this->getRegionStateShort($rawWarnung["region"]);
            $parsedWarnInfo["stateLong"] = $this->getRegionStateLong($rawWarnung["region"]);
            $parsedWarnInfo["altitude"] = $this->getRegionAltitude($rawWarnung["region"], true);
            $parsedWarnInfo["ceiling"] = $this->getRegionCeiling($rawWarnung["region"], true);
            $parsedWarnInfo["hoehenangabe"] = $this->getRegionHoehenangabe($rawWarnung["region"]);

            // MD5Hash erzeugen aus Angaben der Wetterwarnung
            $parsedWarnInfo["hash"]  = md5($parsedWarnInfo["msgType"] .
                $parsedWarnInfo["warnstufe"] .
                $parsedWarnInfo["event"] .
                $objStartzeit->getTimestamp() .
                $objEndzeit->getTimestamp() .
                $parsedWarnInfo["area"] .
                $parsedWarnInfo["headline"] .
                $parsedWarnInfo["description"] .
                $parsedWarnInfo["instruction"]);

            return $parsedWarnInfo;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }
}
