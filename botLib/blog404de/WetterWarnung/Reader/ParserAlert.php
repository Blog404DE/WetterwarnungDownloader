<?php
/**
 * WarnParser für neuthardwetter.de by Jens Dutzi - ParserAlert.php
 *
 * @package    blog404de\WetterWarnung
 * @author     Jens Dutzi <jens.dutzi@tf-network.de>
 * @copyright  Copyright (c) 2012-2018 Jens Dutzi (http://www.neuthardwetter.de)
 * @license    https://github.com/Blog404DE/WetterwarnungDownloader/blob/master/LICENSE.md
 * @version    v3.0.2-dev
 * @link       https://github.com/Blog404DE/WetterwarnungDownloader
 */

namespace blog404de\WetterWarnung\Reader;

use blog404de\Standard\Toolbox;
use Exception;
use DateTime;
use DateTimeZone;

/**
 * Traint zum auslesen diverser Header-Elemente aus der Roh-XML Datei
 *
 * @package blog404de\WetterWarnung\Reader
 */
trait ParserAlert
{
    /** @var string $localIconFolder Ordner in denen sich die Warnlagen-Icons befinden */
    private $localIconFolder = "";

    /**
     * Startzeitpunkt der Wetterwarnung ermitteln
     *
     * @param \SimpleXMLElement $currentWarnAlert Alert-Teil der aktuellen Wetterwarnung
     * @return DateTime
     * @throws Exception
     */
    final protected function getAlertStartzeit(\SimpleXMLElement $currentWarnAlert): \DateTime
    {
        try {
            // Startzeitpunkt ermitteln
            if (!property_exists($currentWarnAlert, "onset")) {
                throw new Exception(
                    "Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'onset'."
                );
            }

            $strRawDate = str_replace("+00:00", "", (string)$currentWarnAlert->{"onset"});
            $objDateOnSet = new DateTime();
            $objDateOnset = $objDateOnSet->createFromFormat(
                'Y-m-d*H:i:s',
                $strRawDate,
                new DateTimeZone("UTC")
            );
            if (!$objDateOnset) {
                throw new Exception(
                    "Die aktuell verarbeitete Roh-Wetterwarnung beinhaltet ungültige Daten im " .
                    "XML-Node 'warnung'->'onset'."
                );
            } else {
                // Zeitzone auf Deutschland umstellen
                $objDateOnset->setTimezone(new DateTimeZone("Europe/Berlin"));
            }

            return $objDateOnset;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Endzeitpunkt der Wetterwarnung ermitteln
     *
     * @param \SimpleXMLElement $currentWarnAlert Alert-Teil der aktuellen Wetterwarnung
     * @return DateTime
     * @throws Exception
     */
    final protected function getAlertEndzeit(\SimpleXMLElement $currentWarnAlert): \DateTime
    {
        try {
            // Endzeitpunkt ermitteln
            if (!property_exists($currentWarnAlert, "expires")) {
                // Falls Expire-Erzeitpunkt fehlt, nehme den normalen Start-Zeitpunkt
                $objDateExpire = $this->getAlertStartzeit($currentWarnAlert);
            } else {
                // Expire-Element existiert
                $strRawDate = str_replace("+00:00", "", (string)$currentWarnAlert->{"expires"});
                $objDateExpire = new DateTime();
                $objDateExpire = $objDateExpire->createFromFormat(
                    'Y-m-d*H:i:s',
                    $strRawDate,
                    new DateTimeZone("UTC")
                );
                if (!$objDateExpire) {
                    throw new Exception(
                        "Die aktuell verarbeitete Roh-Wetterwarnung beinhaltet ungültige Daten im " .
                        "XML-Node 'warnung'->'expires'."
                    );
                } else {
                    // Zeitzone auf Deutschland umstellen
                    $objDateExpire->setTimezone(new DateTimeZone("Europe/Berlin"));
                }
            }

            return $objDateExpire;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Urgency-Typ aus der Wetterwarnung auslesen
     *
     * @param \SimpleXMLElement $currentWarnAlert Alert-Teil der aktuellen Wetterwarnung
     * @return String
     * @throws Exception
     */
    final protected function getAlertUrgency(\SimpleXMLElement $currentWarnAlert): String
    {
        try {
            // Prüfe ob Feld in XML Datei existiert
            if (!property_exists($currentWarnAlert, "urgency")) {
                throw new Exception(
                    "Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'urgency'."
                );
            }

            return (string)$currentWarnAlert->{"urgency"};
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Warnstufe (Text) der Meldung der Wetterwarnung auslesen
     *
     * @param \SimpleXMLElement $currentWarnAlert Alert-Teil der aktuellen Wetterwarnung
     * @return string
     * @throws Exception
     */
    final protected function getAlertSeverity(\SimpleXMLElement $currentWarnAlert): string
    {
        try {
            // Prüfe ob Feld in XML Datei existiert
            if (!property_exists($currentWarnAlert, "severity")) {
                throw new Exception(
                    "Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'severity'."
                );
            }

            if ($this->getAlertUrgency($currentWarnAlert) == "Future"
                || $this->getAlertStartzeit($currentWarnAlert)->getTimestamp() > time()
            ) {
                // Warnung liegt in der Zukunft -> daher Warnstufe auf 0 setzen
                $severity = "Vorwarnung";
            } else {
                // Warnung ist aktuell gültig -> daher Warnstufe ermitteln
                switch ((string)$currentWarnAlert->{"severity"}) {
                    case "Minor":
                        $severity = "Wetterwarnung";
                        break;
                    case "Moderate":
                        $severity = "Markantes Wetter";
                        break;
                    case "Severe":
                        $severity = "Unwetterwarnung";
                        break;
                    case "Extreme":
                        $severity = "Extreme Unwetterwarnung";
                        break;
                    default:
                        $severity = "Unbekannt";
                }
            }

            return (string)$severity;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Warnstufe (Zahl) der Meldung der Wetterwarnung auslesen
     *
     * @param \SimpleXMLElement $currentWarnAlert Alert-Teil der aktuellen Wetterwarnung
     * @return int
     * @throws Exception
     */
    final protected function getAlertWarnstufe(\SimpleXMLElement $currentWarnAlert): int
    {
        try {
            // Prüfe ob Feld in XML Datei existiert
            if (!property_exists($currentWarnAlert, "severity")) {
                throw new Exception(
                    "Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'severity'."
                );
            }

            if ($this->getAlertUrgency($currentWarnAlert) == "Future"
                || $this->getAlertStartzeit($currentWarnAlert)->getTimestamp() > time()
            ) {
                // Warnung liegt in der Zukunft -> daher Warnstufe auf 0 setzen
                $warnstufe = 0;
            } else {
                // Warnung ist aktuell gültig -> daher Warnstufe ermitteln
                switch ((string)$currentWarnAlert->{"severity"}) {
                    case "Minor":
                        $warnstufe = 1;
                        break;
                    case "Moderate":
                        $warnstufe = 2;
                        break;
                    case "Severe":
                        $warnstufe = 3;
                        break;
                    case "Extreme":
                        $warnstufe = 4;
                        break;
                    default:
                        $warnstufe = -1;
                }
            }

            return (int)$warnstufe;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Event-Typ aus der Wetterwarnung auslesen
     *
     * @param \SimpleXMLElement $currentWarnAlert Alert-Teil der aktuellen Wetterwarnung
     * @return String
     * @throws Exception
     */
    final protected function getAlertEvent(\SimpleXMLElement $currentWarnAlert): String
    {
        try {
            // Prüfe ob Feld in XML Datei existiert
            if (!property_exists($currentWarnAlert, "event")) {
                throw new Exception(
                    "Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'event'."
                );
            }

            return (string)$currentWarnAlert->{"event"};
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * headline aus der Wetterwarnung auslesen
     *
     * @param \SimpleXMLElement $currentWarnAlert Alert-Teil der aktuellen Wetterwarnung
     * @return String
     * @throws Exception
     */
    final protected function getAlertHeadline(\SimpleXMLElement $currentWarnAlert): String
    {
        try {
            // Prüfe ob Feld in XML Datei existiert
            if (!property_exists($currentWarnAlert, "headline")) {
                throw new Exception(
                    "Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'headline'."
                );
            }

            return (string)$currentWarnAlert->{"headline"};
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * description aus der Wetterwarnung auslesen
     *
     * @param \SimpleXMLElement $currentWarnAlert Alert-Teil der aktuellen Wetterwarnung
     * @return String
     * @throws Exception
     */
    final protected function getAlertDescription(\SimpleXMLElement $currentWarnAlert): String
    {
        try {
            // Prüfe ob Feld in XML Datei existiert
            if (!property_exists($currentWarnAlert, "headline")) {
                throw new Exception(
                    "Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'description'."
                );
            }

            return (string)$currentWarnAlert->{"description"};
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * instruction aus der Wetterwarnung auslesen
     *
     * @param \SimpleXMLElement $currentWarnAlert Alert-Teil der aktuellen Wetterwarnung
     * @return String
     * @throws Exception
     */
    final protected function getAlertInstruction(\SimpleXMLElement $currentWarnAlert): String
    {
        try {
            // Prüfe ob Feld in XML Datei existiert
            if (!property_exists($currentWarnAlert, "headline")) {
                throw new Exception(
                    "Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'instruction'."
                );
            }

            return (string)$currentWarnAlert->{"instruction"};
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * senderName aus der Wetterwarnung auslesen
     *
     * @param \SimpleXMLElement $currentWarnAlert Alert-Teil der aktuellen Wetterwarnung
     * @return String
     * @throws Exception
     */
    final protected function getAlertSender(\SimpleXMLElement $currentWarnAlert): String
    {
        try {
            // Prüfe ob Feld in XML Datei existiert
            if (!property_exists($currentWarnAlert, "headline")) {
                throw new Exception(
                    "Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'senderName'."
                );
            }

            return (string)$currentWarnAlert->{"senderName"};
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * web aus der Wetterwarnung auslesen
     *
     * @param \SimpleXMLElement $currentWarnAlert Alert-Teil der aktuellen Wetterwarnung
     * @return String
     * @throws Exception
     */
    final protected function getAlertWeb(\SimpleXMLElement $currentWarnAlert): String
    {
        try {
            // Prüfe ob Feld in XML Datei existiert
            if (!property_exists($currentWarnAlert, "web")) {
                throw new Exception(
                    "Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'web'."
                );
            }

            return (string)$currentWarnAlert->{"web"};
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Event-Code ermitteln für die spätere Icon-Auflösung
     *
     * @param \SimpleXMLElement $currentWarnAlert Alert-Teil der aktuellen Wetterwarnung
     * @return Int
     * @throws Exception
     */
    final private function getEventCode(\SimpleXMLElement $currentWarnAlert): Int
    {
        try {
            $eventcode = -1;
            foreach ($currentWarnAlert->children() as $child) {
                if ($child->getName() == "eventCode") {
                    if (property_exists($child, "valueName") && property_exists($child, "value")) {
                        if ($child->{"valueName"}[0] == "II") {
                            $eventcode = (int)$child->{"value"};
                        }
                    }
                }
            }

            return $eventcode;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Icon passend zum WarnEvent ermitteln
     *
     * @param \SimpleXMLElement $currentWarnAlert Alert-Teil der aktuellen Wetterwarnung
     * @return String
     * @throws Exception
     */
    final protected function getAlertIcon(\SimpleXMLElement $currentWarnAlert): String
    {
        try {
            // Zuordnungs-Tabelle öffnen
            $jsonZuordnung = file_get_contents(
                $this->getLocalIconFolder() . DIRECTORY_SEPARATOR . "zuordnung.json"
            );
            if ($jsonZuordnung === false) {
                throw new Exception(
                    "Die Datei zuordnung.json innerhalb des Icon-Ordners konnte nicht geöffnet werden."
                );
            }

            // JSON Daten in Array umwandeln
            $warnIcons = json_decode($jsonZuordnung, true);
            if (json_last_error()) {
                $toolbox = new Toolbox();
                throw new Exception(
                    "Fehler beim interpretieren Icon-Zuordnung Datei (" .
                    $toolbox->getJsonErrorMessage(json_last_error()) . ")"
                );
            }

            // Eventcode ermitteln
            $eventcode = $this->getEventCode($currentWarnAlert);

            $warnIconFilename = "";
            foreach ($warnIcons as $warnIconPart => $warnCodes) {
                if (in_array($eventcode, $warnCodes)) {
                    if ((int)$this->getAlertWarnstufe($currentWarnAlert) < 0) {
                        $warnIconFilename =  "warn_icons_" .
                            sprintf($warnIconPart, 0) . ".png";
                    } else {
                        $warnIconFilename =  "warn_icons_" .
                            sprintf($warnIconPart, (int)$this->getAlertWarnstufe($currentWarnAlert)) . ".png";
                    }
                }
            }

            // Prüfe ob WarnFile existiert
            if (empty($warnIconFilename)) {
                echo(
                    "\t\t* Warnung: Für den Warn-Event Code  " . $eventcode .
                    " wurde in der zuordnung.json noch kein Warn-Icon zugeordnet (ggf. neuer Warn-Code?)". PHP_EOL
                );
            } elseif (!is_readable($this->getLocalIconFolder() . DIRECTORY_SEPARATOR . $warnIconFilename)) {
                echo(
                    "\t\t* Warnung: Zugeordnetes Wetter-Icon " .
                    $warnIconFilename . " steht nicht zur Verfügung. Verwende daher kein Warn-Icon". PHP_EOL
                );
                $warnIconFilename = "";
            }

            return $warnIconFilename;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }


    /**
     * Setter für den Ordner in dem sich die Warnlagen-Icons befinden
     *
     * @param string $localIconFolder Pfad zum Ordner mit den Warnlagen-Icons
     * @throws Exception
     */
    public function setLocalIconFolder(string $localIconFolder)
    {
        try {
            if (!empty($localIconFolder) && $localIconFolder !== false) {
                if (!is_readable(
                    realpath($localIconFolder . DIRECTORY_SEPARATOR . "zuordnung.json")
                )) {
                    // Kein Zugriff auf Datei möglich
                    throw new Exception(
                        "JSON Datei mit der Zuordnung der Warnlagen-Icons kann nicht in " .
                        realpath($localIconFolder) . DIRECTORY_SEPARATOR .
                        "zuordnung.json geöffnet werden"
                    );
                }
            } else {
                $localIconFolder = "";
            }

            $this->localIconFolder = $localIconFolder;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Getter für den Ordner in dem sich die Warnlagen-Icons befinden
     *
     * @return string
     * @throws Exception
     */
    public function getLocalIconFolder() : string
    {
        try {
            return  $this->localIconFolder;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }
}
