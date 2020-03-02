<?php
/**
 * WarnParser für neuthardwetter.de by Jens Dutzi - ParserAlert.php.
 *
 * @author     Jens Dutzi <jens.dutzi@tf-network.de>
 * @copyright  Copyright (c) 2012-2020 Jens Dutzi (http://www.neuthardwetter.de)
 * @license    https://github.com/Blog404DE/WetterwarnungDownloader/blob/master/LICENSE.md
 *
 * @version    v3.1.5
 *
 * @see       https://github.com/Blog404DE/WetterwarnungDownloader
 */

declare(strict_types=1);

/*
 *  WarnParser für neuthardwetter.de by Jens Dutzi
 *
 *  @package    blog404de\WetterWarnung
 *  @author     Jens Dutzi <jens.dutzi@tf-network.de>
 *  @copyright  Copyright (c) 2012-2019 Jens Dutzi (http://www.neuthardwetter.de)
 *  @license    https://github.com/Blog404DE/WetterwarnungDownloader/blob/master/LICENSE.md
 *  @version    v3.1.4
 *  @link       https://github.com/Blog404DE/WetterwarnungDownloader
 */

namespace blog404de\WetterWarnung\Reader;

use blog404de\Standard\Toolbox;
use DateTime;
use DateTimeZone;
use Exception;
use RuntimeException;

/**
 * Traint zum auslesen diverser Header-Elemente aus der Roh-XML Datei.
 */
trait ParserAlert {
    /** @var string $localIconFolder Ordner in denen sich die Warnlagen-Icons befinden */
    private $localIconFolder = '';

    /**
     * Setter für den Ordner in dem sich die Warnlagen-Icons befinden.
     *
     * @param string $localIconFolder Pfad zum Ordner mit den Warnlagen-Icons
     *
     * @throws Exception
     */
    public function setLocalIconFolder(string $localIconFolder): void {
        try {
            if (!empty($localIconFolder) && false !== $localIconFolder) {
                if (!is_readable(
                    realpath($localIconFolder . \DIRECTORY_SEPARATOR . 'zuordnung.json')
                )) {
                    // Kein Zugriff auf Datei möglich
                    throw new RuntimeException(
                        'JSON Datei mit der Zuordnung der Warnlagen-Icons kann nicht in ' .
                        realpath($localIconFolder) . \DIRECTORY_SEPARATOR .
                        'zuordnung.json geöffnet werden'
                    );
                }
            } else {
                $localIconFolder = '';
            }

            $this->localIconFolder = $localIconFolder;
        } catch (RuntimeException | \Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Getter für den Ordner in dem sich die Warnlagen-Icons befinden.
     *
     * @throws Exception
     */
    public function getLocalIconFolder(): string {
        try {
            return  $this->localIconFolder;
        } catch (RuntimeException | \Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Startzeitpunkt der Wetterwarnung ermitteln.
     *
     * @param \SimpleXMLElement $currentWarnAlert Alert-Teil der aktuellen Wetterwarnung
     *
     * @throws Exception
     */
    final protected function getAlertStartzeit(\SimpleXMLElement $currentWarnAlert): DateTime {
        try {
            // Startzeitpunkt ermitteln
            if (!property_exists($currentWarnAlert, 'onset')) {
                throw new RuntimeException(
                    "Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'onset'."
                );
            }

            $objDateOnSet = new DateTime();
            $objDateOnset = $objDateOnSet::createFromFormat(
                'Y-m-d*H:i:sT',
                $currentWarnAlert->{'onset'}->__toString()
            );
            if (!$objDateOnset) {
                throw new RuntimeException(
                    'Die aktuell verarbeitete Roh-Wetterwarnung beinhaltet ungültige Daten im ' .
                    "XML-Node 'warnung'->'onset'."
                );
            }
            // Zeitzone auf Deutschland umstellen
            $objDateOnset->setTimezone(new DateTimeZone('Europe/Berlin'));

            return $objDateOnset;
        } catch (RuntimeException | \Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Endzeitpunkt der Wetterwarnung ermitteln.
     *
     * @param \SimpleXMLElement $currentWarnAlert Alert-Teil der aktuellen Wetterwarnung
     *
     * @throws Exception
     */
    final protected function getAlertEndzeit(\SimpleXMLElement $currentWarnAlert): DateTime {
        try {
            // Endzeitpunkt ermitteln
            if (!property_exists($currentWarnAlert, 'expires')) {
                // Falls Expire-Erzeitpunkt fehlt, nehme den normalen Start-Zeitpunkt
                $objDateExpire = $this->getAlertStartzeit($currentWarnAlert);
            } else {
                // Expire-Element existiert
                $objDateExpire = new DateTime();
                $objDateExpire = $objDateExpire::createFromFormat(
                    'Y-m-d*H:i:sT',
                    $currentWarnAlert->{'expires'}->__toString()
                );
                if (!$objDateExpire) {
                    throw new RuntimeException(
                        'Die aktuell verarbeitete Roh-Wetterwarnung beinhaltet ungültige Daten im ' .
                        "XML-Node 'warnung'->'expires'."
                    );
                }
                // Zeitzone auf Deutschland umstellen
                $objDateExpire->setTimezone(new DateTimeZone('Europe/Berlin'));
            }

            return $objDateExpire;
        } catch (RuntimeException | \Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Urgency-Typ aus der Wetterwarnung auslesen.
     *
     * @param \SimpleXMLElement $currentWarnAlert Alert-Teil der aktuellen Wetterwarnung
     *
     * @throws Exception
     */
    final protected function getAlertUrgency(\SimpleXMLElement $currentWarnAlert): string {
        try {
            // Prüfe ob Feld in XML Datei existiert
            if (!property_exists($currentWarnAlert, 'urgency')) {
                throw new RuntimeException(
                    "Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'urgency'."
                );
            }

            return $currentWarnAlert->{'urgency'}->__toString();
        } catch (RuntimeException | \Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Warnstufe (Text) der Meldung der Wetterwarnung auslesen.
     *
     * @param \SimpleXMLElement $currentWarnAlert Alert-Teil der aktuellen Wetterwarnung
     *
     * @throws Exception
     */
    final protected function getAlertSeverity(\SimpleXMLElement $currentWarnAlert): string {
        try {
            // Prüfe ob Feld in XML Datei existiert
            if (!property_exists($currentWarnAlert, 'severity')) {
                throw new RuntimeException(
                    "Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'severity'."
                );
            }

            if ('Future' === $this->getAlertUrgency($currentWarnAlert)
                || $this->getAlertStartzeit($currentWarnAlert)->getTimestamp() > time()
            ) {
                // Warnung liegt in der Zukunft -> daher Warnstufe auf 0 setzen
                $severity = 'Vorwarnung';
            } else {
                // Warnung ist aktuell gültig -> daher Warnstufe ermitteln
                switch ($currentWarnAlert->{'severity'}->__toString()) {
                    case 'Minor':
                        $severity = 'Wetterwarnung';

                        break;
                    case 'Moderate':
                        $severity = 'Markantes Wetter';

                        break;
                    case 'Severe':
                        $severity = 'Unwetterwarnung';

                        break;
                    case 'Extreme':
                        $severity = 'Extreme Unwetterwarnung';

                        break;
                    default:
                        $severity = 'Unbekannt';
                }
            }

            return $severity;
        } catch (RuntimeException | \Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Warnstufe (Zahl) der Meldung der Wetterwarnung auslesen.
     *
     * @param \SimpleXMLElement $currentWarnAlert Alert-Teil der aktuellen Wetterwarnung
     *
     * @throws Exception
     */
    final protected function getAlertWarnstufe(\SimpleXMLElement $currentWarnAlert): int {
        try {
            // Prüfe ob Feld in XML Datei existiert
            if (!property_exists($currentWarnAlert, 'severity')) {
                throw new RuntimeException(
                    "Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'severity'."
                );
            }

            if ('Future' === $this->getAlertUrgency($currentWarnAlert)
                || $this->getAlertStartzeit($currentWarnAlert)->getTimestamp() > time()
            ) {
                // Warnung liegt in der Zukunft -> daher Warnstufe auf 0 setzen
                $warnstufe = 0;
            } else {
                // Warnung ist aktuell gültig -> daher Warnstufe ermitteln
                switch ($currentWarnAlert->{'severity'}->__toString()) {
                    case 'Minor':
                        $warnstufe = 1;

                        break;
                    case 'Moderate':
                        $warnstufe = 2;

                        break;
                    case 'Severe':
                        $warnstufe = 3;

                        break;
                    case 'Extreme':
                        $warnstufe = 4;

                        break;
                    default:
                        $warnstufe = -1;
                }
            }

            return $warnstufe;
        } catch (RuntimeException | \Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Event-Typ aus der Wetterwarnung auslesen.
     *
     * @param \SimpleXMLElement $currentWarnAlert Alert-Teil der aktuellen Wetterwarnung
     *
     * @throws Exception
     */
    final protected function getAlertEvent(\SimpleXMLElement $currentWarnAlert): string {
        try {
            // Prüfe ob Feld in XML Datei existiert
            if (!property_exists($currentWarnAlert, 'event')) {
                throw new RuntimeException(
                    "Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'event'."
                );
            }

            return $currentWarnAlert->{'event'}->__toString();
        } catch (RuntimeException | \Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * headline aus der Wetterwarnung auslesen.
     *
     * @param \SimpleXMLElement $currentWarnAlert Alert-Teil der aktuellen Wetterwarnung
     *
     * @throws Exception
     */
    final protected function getAlertHeadline(\SimpleXMLElement $currentWarnAlert): string {
        try {
            // Prüfe ob Feld in XML Datei existiert
            if (!property_exists($currentWarnAlert, 'headline')) {
                throw new RuntimeException(
                    "Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'headline'."
                );
            }

            return $currentWarnAlert->{'headline'}->__toString();
        } catch (RuntimeException | \Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * description aus der Wetterwarnung auslesen.
     *
     * @param \SimpleXMLElement $currentWarnAlert Alert-Teil der aktuellen Wetterwarnung
     *
     * @throws Exception
     */
    final protected function getAlertDescription(\SimpleXMLElement $currentWarnAlert): string {
        try {
            // Prüfe ob Feld in XML Datei existiert
            if (!property_exists($currentWarnAlert, 'headline')) {
                throw new RuntimeException(
                    "Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'description'."
                );
            }

            return $currentWarnAlert->{'description'}->__toString();
        } catch (RuntimeException | \Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * instruction aus der Wetterwarnung auslesen.
     *
     * @param \SimpleXMLElement $currentWarnAlert Alert-Teil der aktuellen Wetterwarnung
     *
     * @throws Exception
     */
    final protected function getAlertInstruction(\SimpleXMLElement $currentWarnAlert): string {
        try {
            // Prüfe ob Feld in XML Datei existiert
            if (!property_exists($currentWarnAlert, 'headline')) {
                throw new RuntimeException(
                    "Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'instruction'."
                );
            }

            return $currentWarnAlert->{'instruction'}->__toString();
        } catch (RuntimeException | \Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * senderName aus der Wetterwarnung auslesen.
     *
     * @param \SimpleXMLElement $currentWarnAlert Alert-Teil der aktuellen Wetterwarnung
     *
     * @throws Exception
     */
    final protected function getAlertSender(\SimpleXMLElement $currentWarnAlert): string {
        try {
            // Prüfe ob Feld in XML Datei existiert
            if (!property_exists($currentWarnAlert, 'headline')) {
                throw new RuntimeException(
                    "Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'senderName'."
                );
            }

            return $currentWarnAlert->{'senderName'}->__toString();
        } catch (RuntimeException | \Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * web aus der Wetterwarnung auslesen.
     *
     * @param \SimpleXMLElement $currentWarnAlert Alert-Teil der aktuellen Wetterwarnung
     *
     * @throws Exception
     */
    final protected function getAlertWeb(\SimpleXMLElement $currentWarnAlert): string {
        try {
            // Prüfe ob Feld in XML Datei existiert
            if (!property_exists($currentWarnAlert, 'web')) {
                throw new RuntimeException(
                    "Die aktuell verarbeitete Roh-Wetterwarnung fehlt in 'warnung' das XML-Node 'web'."
                );
            }

            return $currentWarnAlert->{'web'}->__toString();
        } catch (RuntimeException | \Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Icon passend zum WarnEvent ermitteln.
     *
     * @param \SimpleXMLElement $currentWarnAlert Alert-Teil der aktuellen Wetterwarnung
     *
     * @throws Exception
     */
    final protected function getAlertIcon(\SimpleXMLElement $currentWarnAlert): string {
        try {
            // Zuordnungs-Tabelle öffnen
            $jsonZuordnung = file_get_contents(
                $this->getLocalIconFolder() . \DIRECTORY_SEPARATOR . 'zuordnung.json'
            );
            if (false === $jsonZuordnung) {
                throw new RuntimeException(
                    'Die Datei zuordnung.json innerhalb des Icon-Ordners konnte nicht geöffnet werden.'
                );
            }

            // JSON Daten in Array umwandeln
            $warnIcons = json_decode($jsonZuordnung, true);
            if (json_last_error()) {
                $toolbox = new Toolbox();

                throw new RuntimeException(
                    'Fehler beim interpretieren Icon-Zuordnung Datei (' .
                    $toolbox->getJsonErrorMessage(json_last_error()) . ')'
                );
            }

            // Eventcode ermitteln
            $eventcode = $this->getEventCode($currentWarnAlert);

            $warnIconFilename = '';
            foreach ($warnIcons as $warnIconPart => $warnCodes) {
                if (\in_array((string)$eventcode, $warnCodes, true)) {
                    if ($this->getAlertWarnstufe($currentWarnAlert) < 0) {
                        $warnIconFilename = 'warn_icons_' .
                            sprintf($warnIconPart, 0) . '.png';
                    } else {
                        $warnIconFilename = 'warn_icons_' .
                            sprintf($warnIconPart, $this->getAlertWarnstufe($currentWarnAlert)) . '.png';
                    }
                }
            }

            // Prüfe ob WarnFile existiert
            if (empty($warnIconFilename)) {
                echo
                    "\t\t* Warnung: Für den Warn-Event Code  " . $eventcode .
                    ' wurde in der zuordnung.json noch kein Warn-Icon zugeordnet (ggf. neuer Warn-Code?)' . PHP_EOL
                ;
            } elseif (!is_readable($this->getLocalIconFolder() . \DIRECTORY_SEPARATOR . $warnIconFilename)) {
                echo
                    "\t\t* Warnung: Zugeordnetes Wetter-Icon " .
                    $warnIconFilename . ' steht nicht zur Verfügung. Verwende daher kein Warn-Icon' . PHP_EOL
                ;
                $warnIconFilename = '';
            }

            return $warnIconFilename;
        } catch (RuntimeException | \Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Event-Code ermitteln für die spätere Icon-Auflösung.
     *
     * @param \SimpleXMLElement $currentWarnAlert Alert-Teil der aktuellen Wetterwarnung
     *
     * @throws Exception
     */
    private function getEventCode(\SimpleXMLElement $currentWarnAlert): int {
        try {
            $eventcode = -1;
            foreach ($currentWarnAlert->children() as $child) {
                /** @noinspection NotOptimalIfConditionsInspection */
                if (('eventCode' === $child->getName()) &&
                    property_exists($child, 'valueName') && property_exists(
                        $child,
                        'value'
                    ) && 'II' === (string)$child->{'valueName'}[0]->__toString()) {
                    $eventcode = (int)($child->{'value'}->__toString());
                }
            }

            return $eventcode;
        } catch (RuntimeException | \Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }
}
