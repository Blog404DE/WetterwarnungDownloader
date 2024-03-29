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

namespace blog404de\WetterWarnung\Action;

use Exception;
use RuntimeException;

/**
 * Action-Klasse für WetterWarnung Downloader zum Senden eines Tweets bei einer neuen Nachricht.
 */
class SendToITFFF implements SendToInterface {
    /** @var array Konfigurationsdaten für die Action */
    private array $config = [];

    /**
     * Prüfe System-Vorraussetzungen.
     *
     * @throws Exception
     */
    public function __construct() {
        // Prüfe ob libCurl vorhanden ist
        if (!\extension_loaded('curl')) {
            throw new RuntimeException(
                'libCurl bzw. die das libCurl-PHP Modul steht nicht zur Verfügung.'
            );
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
        // Prüfe, ob alles konfiguriert ist
        if ($this->getConfig()) {
            // Status-Ausgabe der aktuellen Wetterwarnung
            echo "\t* Wetterwarnung über " . $parsedWarnInfo['event'] . ' für ' .
                $parsedWarnInfo['area'] . PHP_EOL;

            if ($warnExists) {
                echo "\t\t-> Wetter-Warnung existierte beim letzten Durchlauf bereits" . PHP_EOL;
            } else {
                // Stelle Parameter zusammen die an IFTTT gesendet werden
                $message = $this->composeMessage($parsedWarnInfo);
                $jsonMessage = json_encode($message, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                if (!\is_string($jsonMessage)) {
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

                // Prüfe, ob Befehl erfolgreich abgesetzt wurde
                if (200 !== curl_getinfo($curl, CURLINFO_HTTP_CODE)) {
                    throw new RuntimeException(
                        'IFTTT  Webhook API Liefert ein Fehler zurück (' .
                        curl_getinfo($curl, CURLINFO_HTTP_CODE) . ' / ' . $result . ')'
                    );
                }

                echo 'erfolgreich (Dauer: ' . curl_getinfo($curl, CURLINFO_TOTAL_TIME) . ' sek.)' . PHP_EOL;
            }

            return 0;
        }
        // Konfiguration ist nicht gsetzt
        throw new RuntimeException(
            'Die Action-Funktion wurde nicht erfolgreich konfiguriert'
        );
    }

    /**
     * Setter für Konfigurations-Array.
     *
     * @param array $config Konfigurations-Array
     *
     * @throws Exception
     */
    public function setConfig(array $config): void {
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
        // Typ der Warnung ermitteln, Leerzeichen entfernen und daraus ein Hashtag erzeugen
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
    }
}
