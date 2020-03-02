<?php
/**
 * WarnParser für neuthardwetter.de by Jens Dutzi - SendToITFFF.php.
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

namespace blog404de\WetterWarnung\Action;

use Exception;
use RuntimeException;

/**
 * Action-Klasse für WetterWarnung Downloader zum senden eines Tweets bei einer neuen Nachricht.
 */
class SendToITFFF implements SendToInterface {
    /** @var array Konfigurationsdaten für die Action */
    private $config = [];

    /**
     * Prüfe System-Vorraussetzungen.
     *
     * @throws Exception
     */
    public function __construct() {
        try {
            // Prüfe ob libCurl vorhanden ist
            if (!\extension_loaded('curl')) {
                throw new RuntimeException(
                    'libCurl bzw. die das libCurl-PHP Modul steht nicht zur Verfügung.'
                );
            }
        } catch (RuntimeException | \Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Action Ausführung starten (Tweet versenden).
     *
     * @param array $parsedWarnInfo Inhalt der Wetterwarnungen
     * @param bool  $warnExists     Wetterwarnung existiert bereits
     *
     * @throws Exception
     */
    public function startAction(array $parsedWarnInfo, bool $warnExists): int {
        try {
            // Prüfe ob alles konfiguriert ist
            if ($this->getConfig()) {
                if (!\is_array($parsedWarnInfo)) {
                    // Keine Warnwetter-Daten zum senden an IFTTT vorhanden -> harter Fehler
                    throw new RuntimeException('Die im Archiv zu speicherenden Wetter-Informationen sind ungültig');
                }

                // Status-Ausgabe der aktuellen Wetterwarnung
                echo "\t* Wetterwarnung über " . $parsedWarnInfo['event'] . ' für ' .
                    $parsedWarnInfo['area'] . PHP_EOL;

                if (!$warnExists) {
                    // Stelle Parameter zusammen die an IFTTT gesendet werden
                    $message = $this->composeMessage($parsedWarnInfo);
                    $jsonMessage = json_encode($message, JSON_UNESCAPED_UNICODE);
                    if (false === $jsonMessage) {
                        // Nachricht konnte nicht konvertiert werden
                        throw new RuntimeException(
                            'Konvertieren der WetterWarnung als JSON Nachricht für IFFFT ist fehlgeschlagen'
                        );
                    }

                    // URL zusammensetzen
                    $url = 'https://maker.ifttt.com/trigger/' . $this->config['eventName'] .
                           '/with/key/' . $this->config['apiKey'];

                    // Sende Nachricht an IFTTT ab
                    $curl = curl_init();
                    curl_setopt_array($curl, [
                        CURLOPT_URL => $url,
                        CURLOPT_FILETIME => true,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HEADER => false,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_TIMEOUT => 5,
                        CURLOPT_CONNECTTIMEOUT => 5,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_POSTFIELDS => $jsonMessage,
                        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    ]);

                    // NAchricht absetzen
                    echo "\t\t -> Wetter-Warnung an IFTTT Webhook API senden: ";
                    $result = curl_exec($curl);
                    if (false === $result) {
                        // Befehl wurde nicht abgesetzt
                        // Nachricht konnte nicht konvertiert werden
                        throw new RuntimeException(
                            'Verbindung zur IFTTT Webhook API Fehlgeschlagen (' . curl_error($curl) . ')'
                        );
                    }

                    // Prüfe ob Befehl erfolgreich abgesetzt wurde
                    if (200 !== curl_getinfo($curl, CURLINFO_HTTP_CODE)) {
                        throw new RuntimeException(
                            'IFTTT  Webhook API Liefert ein Fehler zurück (' .
                            curl_getinfo($curl, CURLINFO_HTTP_CODE) . ' / ' . $result . ')'
                        );
                    }

                    echo 'erfolgreich (Dauer: ' . curl_getinfo($curl, CURLINFO_TOTAL_TIME) . ' sek.)' . PHP_EOL;
                } else {
                    echo "\t\t-> Wetter-Warnung existierte beim letzten Durchlauf bereits" . PHP_EOL;
                }

                return 0;
            }
            // Konfiguration ist nicht gsetzt
            throw new RuntimeException(
                'Die Action-Funktion wurde nicht erfolgreich konfiguriert'
            );
        } catch (RuntimeException | \Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Setter für Konfigurations-Array.
     *
     * @param array $config Konfigurations-Array
     *
     * @throws Exception
     */
    public function setConfig(array $config): void {
        try {
            $configParameter = [
                'apiKey', 'eventName', 'localIconFolder',
                'MessagePrefix', 'MessagePostfix',
            ];

            // Alle Paramter verfügbar?
            foreach ($configParameter as $parameter) {
                if (!\array_key_exists($parameter, $config)) {
                    throw new RuntimeException(
                        'Der Konfigurationsparamter ["ActionConfig"]["' . $parameter . '"] wurde nicht gesetzt.'
                    );
                }
            }

            // Werte setzen
            $this->config = $config;
        } catch (RuntimeException | \Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Getter-Methode für das Konfigurations-Array.
     */
    public function getConfig(): array {
        return $this->config;
    }

    /**
     * Nachricht zusammenstellen.
     *
     * @param array $parsedWarnInfo Inhalt der WetterWarnung
     *
     * @throws Exception
     */
    private function composeMessage(array $parsedWarnInfo): array {
        try {
            // Typ der Warnung ermitteln ,Leerzeichen entfernen und daraus ein Hashtag erzeugen
            $message = $parsedWarnInfo['severity'];

            // Gebiet einfügen
            $message .= ' des DWD für ' . $parsedWarnInfo['area'];

            // Uhrzeit einfügen
            $startZeit = unserialize($parsedWarnInfo['startzeit'], ['allowed_classes' => true]);
            $endzeit = unserialize($parsedWarnInfo['endzeit'], ['allowed_classes' => true]);
            $message .= ' (' . $startZeit->format('H:i') . ' bis ' . $endzeit->format('H:i') . '):';

            // Haedline hinzufügen
            $message .= ' ' . $parsedWarnInfo['headline'] . '.';

            // Prä-/Postfix anfügen
            if (!empty($this->config['MessagePrefix']) && false !== $this->config['MessagePrefix']) {
                $message = $this->config['MessagePrefix'] . ' ' . $message;
            }
            if (!empty($this->config['MessagePostfix']) && false !== $this->config['MessagePostfix']) {
                // Postfix erst einmal für spätere Verwendung zwischenspeichernc
                $message .= ' ' . $this->config['MessagePostfix'];
            }

            // Header zusammenstellen
            $header = $parsedWarnInfo['severity'] . ' des DWD vor ' . $parsedWarnInfo['event'];

            return ['value1' => $header, 'value2' => $message];
        } catch (RuntimeException | \Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }
}
