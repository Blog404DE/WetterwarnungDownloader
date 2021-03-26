<?php

declare(strict_types=1);

/*
 *  WarnParser für neuthardwetter.de by Jens Dutzi
 *
 *  @package    blog404de\WetterWarnung
 *  @author     Jens Dutzi <jens.dutzi@tf-network.de>
 *  @copyright  Copyright (c) 2012-2020 Jens Dutzi (http://www.neuthardwetter.de)
 *  @license    https://github.com/Blog404DE/WetterwarnungDownloader/blob/master/LICENSE.md
 *  @version    v3.1.7
 *  @link       https://github.com/Blog404DE/WetterwarnungDownloader
 */

namespace blog404de\WetterWarnung\Action;

use CURLFile;
use Exception;
use RuntimeException;

/**
 * Action-Klasse für WetterWarnung Downloader zum senden eines Tweets bei einer neuen Nachricht.
 */
class SendToPushover implements SendToInterface
{
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

            if (!class_exists('CURLFile')) {
                throw new RuntimeException(
                    'CURLFile steht nicht zur Verfügung / libCurl ggf. in einer zu alten Version.'
                );
            }
        } catch (RuntimeException | \Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Action Ausführung starten (PushOver-Meldung versenden).
     *
     * @param array $parsedWarnInfo Inhalt der WetterWarnung
     * @param bool  $warnExists     Wetterwarnung existiert bereits
     *
     * @throws Exception
     */
    public function startAction(array $parsedWarnInfo, bool $warnExists): int {
        try {
            // Prüfe ob alles konfiguriert ist
            if ($this->getConfig()) {
                if (!\is_array($parsedWarnInfo)) {
                    // Keine Warnwetter-Daten zum senden an PushOver vorhanden -> harter Fehler
                    throw new RuntimeException('Die im Archiv zu speicherenden Wetter-Informationen sind ungültig');
                }

                // Status-Ausgabe der aktuellen Wetterwarnung
                echo "\t* Wetterwarnung über " . $parsedWarnInfo['event'] . ' für ' .
                    $parsedWarnInfo['area'] . PHP_EOL;

                if (!$warnExists) {
                    // Stelle Parameter zusammen die an PushOver-API gesendet werden
                    $warnParameter = $this->composeMessage($parsedWarnInfo);
                    $attachment = $this->composeAttachment($parsedWarnInfo);
                    $jsonMessage = array_merge($warnParameter, $attachment);

                    // URL zusammensetzen
                    $url = 'https://api.pushover.net/1/messages.json';

                    // Sende Nachricht an PushOver-API ab
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
                    ]);

                    // Nachricht absetzen
                    echo "\t\t -> Wetter-Warnung an Pushover API senden: ";
                    $result = curl_exec($curl);
                    if (false === $result) {
                        // Befehl wurde nicht abgesetzt
                        // Nachricht konnte nicht konvertiert werden
                        throw new RuntimeException(
                            'Verbindung zur Pushover Fehlgeschlagen (' . curl_error($curl) . ')'
                        );
                    }

                    // Prüfe ob Befehl erfolgreich abgesetzt wurde
                    if (200 !== curl_getinfo($curl, CURLINFO_HTTP_CODE)) {
                        throw new RuntimeException(
                            'Pushover Webhook API Liefert ein Fehler zurück (' .
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
                'apiKey', 'userKey', 'localIconFolder',
                'MessagePrefix', 'MessagePostfix', 'MessageURL',
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
     * Attachment zusammenstellen.
     *
     * @param array $parsedWarnInfo Inhalt der WetterWarnung
     *
     * @throws Exception
     */
    private function composeAttachment(array $parsedWarnInfo): array {
        try {
            $message = [];

            //
            // Attachment mit Icon hinzufügen sofern vorhanden
            //
            if (!empty($parsedWarnInfo['eventicon'])) {
                // Stelle Pfad zusammen
                $filename = $this->getConfig()['localIconFolder'] . \DIRECTORY_SEPARATOR .
                    $parsedWarnInfo['eventicon'];

                $attachment = new CURLFile(
                    $filename,
                    'image/png',
                    $parsedWarnInfo['event']
                );
                $message['attachment'] = $attachment;
            }

            return $message;
        } catch (RuntimeException | \Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
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
            //
            // Inhalt der Warnung zusammenstellen
            //

            // Haedline hinzufügen
            $message = $parsedWarnInfo['headline'] . ' (' . $parsedWarnInfo['hoehenangabe'] . ')' . PHP_EOL . PHP_EOL;

            // Prä-/Postfix anfügen
            if (!empty($this->config['MessagePrefix']) && false !== $this->config['MessagePrefix']) {
                $message .= ' ' . $this->config['MessagePrefix'] . ' ';
            }

            $message .= $parsedWarnInfo['description'];
            if (!empty($parsedWarnInfo['instruction'])) {
                $message .= ' ' . $parsedWarnInfo['instruction'];
            }

            $message .= PHP_EOL . PHP_EOL;

            $message .= 'Quelle: ' . $parsedWarnInfo['sender'] . ' (' . $parsedWarnInfo['event'] . ' / Stufe: ' . $parsedWarnInfo['severity'] . ')';

            if (!empty($this->config['MessagePostfix']) && false !== $this->config['MessagePostfix']) {
                // Postfix erst einmal für spätere Verwendung zwischenspeichern
                $message .= ' ' . $this->config['MessagePostfix'];
            }

            //
            // Header zusammenstellen
            //

            // Uhrzeit ermitteln
            $startZeit = unserialize($parsedWarnInfo['startzeit'], ['allowed_classes' => true]);
            $endzeit = unserialize($parsedWarnInfo['endzeit'], ['allowed_classes' => true]);

            $header = $parsedWarnInfo['severity'] . ' des DWD für ' . $parsedWarnInfo['area'] .
                ' vor ' . $parsedWarnInfo['event'] .
                ' (' . $startZeit->format('d.m.Y H:i') . ' bis ' . $endzeit->format('d.m.Y H:i') . ')';

            //
            // Array mit POST-Felder zusammenstellen
            //
            return [
                'token' => $this->config['apiKey'],
                'user' => $this->config['userKey'],
                'url' => $this->config['MessageURL'],
                'title' => $header,
                'message' => $message,
            ];
        } catch (RuntimeException | \Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }
}
