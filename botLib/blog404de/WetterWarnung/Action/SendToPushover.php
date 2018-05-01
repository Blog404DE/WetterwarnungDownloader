<?php
/**
 * WarnParser für neuthardwetter.de by Jens Dutzi - SendToPushover.php
 *
 * @package    blog404de\WetterWarnung
 * @author     Jens Dutzi <jens.dutzi@tf-network.de>
 * @copyright  Copyright (c) 2012-2018 Jens Dutzi (http://www.neuthardwetter.de)
 * @license    https://github.com/Blog404DE/WetterwarnungDownloader/blob/master/LICENSE.md
 * @version    v3.0.2
 * @link       https://github.com/Blog404DE/WetterwarnungDownloader
 */

namespace blog404de\WetterWarnung\Action;

use Exception;
use CURLFile;

/**
 * Action-Klasse für WetterWarnung Downloader zum senden eines Tweets bei einer neuen Nachricht
 *
 * @package blog404de\WetterWarnung\Action
 */
class SendToPushover implements SendToInterface
{
    /** @var array Konfigurationsdaten für die Action */
    private $config = [];

    /**
     * Prüfe System-Vorraussetzungen
     *
     * @throws Exception
     */
    public function __construct()
    {
        try {
            // Prüfe ob libCurl vorhanden ist
            if (!extension_loaded('curl')) {
                throw new Exception(
                    "libCurl bzw. die das libCurl-PHP Modul steht nicht zur Verfügung."
                );
            }

            if (!class_exists("CURLFile")) {
                throw new Exception(
                    "CURLFile steht nicht zur Verfügung / libCurl ggf. in einer zu alten Version."
                );
            }
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Action Ausführung starten (PushOver-Meldung versenden)
     *
     * @param array $parsedWarnInfo Inhalt der WetterWarnung
     * @param bool $warnExists Wetterwarnung existiert bereits
     * @return int
     * @throws Exception
     */
    public function startAction(array $parsedWarnInfo, bool $warnExists): int
    {
        try {
            // Prüfe ob alles konfiguriert ist
            if ($this->getConfig()) {
                if (!is_array($parsedWarnInfo)) {
                    // Keine Warnwetter-Daten zum senden an PushOver vorhanden -> harter Fehler
                    throw new Exception("Die im Archiv zu speicherenden Wetter-Informationen sind ungültig");
                }

                // Status-Ausgabe der aktuellen Wetterwarnung
                echo("\t* Wetterwarnung über " . $parsedWarnInfo["event"] . " für " .
                    $parsedWarnInfo["area"] . PHP_EOL);

                if (!$warnExists) {
                    // Stelle Parameter zusammen die an PushOver-API gesendet werden
                    $warnParameter = $this->composeMessage($parsedWarnInfo);
                    $attachment = $this->composeAttachment($parsedWarnInfo);
                    $message = array_merge($warnParameter, $attachment);

                    // URL zusammensetzen
                    $url = "https://api.pushover.net/1/messages.json";

                    // Sende Nachricht an PushOver-API ab
                    $curl = curl_init();
                    curl_setopt($curl, CURLOPT_URL, $url);
                    curl_setopt($curl, CURLOPT_FILETIME, true);
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($curl, CURLOPT_HEADER, false);
                    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($curl, CURLOPT_TIMEOUT, 5);
                    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    // POST Request
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $message);

                    // Nachricht absetzen
                    echo("\t\t -> Wetter-Warnung an Pushover API senden: ");
                    $result = curl_exec($curl);
                    if ($result === false) {
                        // Befehl wurde nicht abgesetzt
                        // Nachricht konnte nicht konvertiert werden
                        throw new Exception(
                            "Verbindung zur Pushover Fehlgeschlagen (" . curl_error($curl) . ")"
                        );
                    }

                    // Prüfe ob Befehl erfolgreich abgesetzt wurde
                    if (curl_getinfo($curl, CURLINFO_HTTP_CODE) !== 200) {
                        throw new Exception(
                            "IFFT Webhook API Liefert ein Fehler zurück (" .
                            curl_getinfo($curl, CURLINFO_HTTP_CODE) . " / " . $result . ")"
                        );
                    }

                    echo("erfolgreich (Dauer: " . curl_getinfo($curl, CURLINFO_TOTAL_TIME) . " sek.)" . PHP_EOL);
                } else {
                    echo("\t\t-> Wetter-Warnung existierte beim letzten Durchlauf bereits" . PHP_EOL);
                }

                return 0;
            } else {
                // Konfiguration ist nicht gsetzt
                throw new Exception(
                    "Die Action-Funktion wurde nicht erfolgreich konfiguriert"
                );
            }
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Attachment zusammenstellen
     *
     * @param array $parsedWarnInfo Inhalt der WetterWarnung
     * @return array
     * @throws Exception
     */
    private function composeAttachment(array $parsedWarnInfo): array
    {
        try {
            $message = [];

            //
            // Attachment mit Icon hinzufügen sofern vorhanden
            //
            if (!empty($parsedWarnInfo["eventicon"])) {
                // Stelle Pfad zusammen
                $filename = $this->getConfig()["localIconFolder"] . DIRECTORY_SEPARATOR .
                    $parsedWarnInfo["eventicon"];

                $attachment = new CURLFile(
                    $filename,
                    "image/png",
                    $parsedWarnInfo["event"]
                );
                $message["attachment"] = $attachment;
            }

            return $message;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Nachricht zusammenstellen
     *
     * @param array $parsedWarnInfo Inhalt der WetterWarnung
     * @return array
     * @throws Exception
     */
    private function composeMessage(array $parsedWarnInfo): array
    {
        try {
            //
            // Inhalt der Warnung zusammenstellen
            //

            // Haedline hinzufügen
            $message = $parsedWarnInfo["headline"] . " (" . $parsedWarnInfo["hoehenangabe"] . ")" . PHP_EOL . PHP_EOL;

            // Prä-/Postfix anfügen
            if (!empty($this->config["MessagePrefix"]) && $this->config["MessagePrefix"] !== false) {
                $message = $message . " " . $this->config["MessagePrefix"] . " ";
            }

            $message = $message . $parsedWarnInfo["description"];
            if (!empty($parsedWarnInfo["instruction"])) {
                $message = $message . " " . $parsedWarnInfo["instruction"];
            }

            $message = $message . PHP_EOL . PHP_EOL;

            $message = $message . "Quelle: " . $parsedWarnInfo["sender"] . " (" . $parsedWarnInfo["event"] .
                " / Stufe: " . $parsedWarnInfo["severity"] . ")";

            if (!empty($this->config["MessagePostfix"]) && $this->config["MessagePostfix"] !== false) {
                // Postfix erst einmal für spätere Verwendung zwischenspeichern
                $message = $message . " " . $this->config["MessagePostfix"];
            }

            //
            // Header zusammenstellen
            //

            // Uhrzeit ermitteln
            $startZeit = unserialize($parsedWarnInfo["startzeit"]);
            $endzeit = unserialize($parsedWarnInfo["endzeit"]);

            $header = $parsedWarnInfo["severity"] . " des DWD für " . $parsedWarnInfo["area"] .
                " vor " . $parsedWarnInfo["event"] .
                " (" . $startZeit->format("d.m.Y H:i") . " bis " . $endzeit->format("d.m.Y H:i") . ")";

            //
            // Array mit POST-Felder zusammenstellen
            //
            $postFields = [
                "token" => $this->config["apiKey"],
                "user" => $this->config["userKey"],
                "url" => $this->config["MessageURL"],
                "title" => $header,
                "message" => $message
            ];

            return $postFields;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Setter für Konfigurations-Array
     *
     * @param array $config Konfigurations-Array
     * @throws Exception
     */
    public function setConfig(array $config)
    {
        try {
            $configParameter = [
                "apiKey", "userKey", "localIconFolder",
                "MessagePrefix", "MessagePostfix", "MessageURL",
            ];

            // Alle Paramter verfügbar?
            foreach ($configParameter as $parameter) {
                if (!array_key_exists($parameter, $config)) {
                    throw new Exception(
                        "Der Konfigurationsparamter [\"ActionConfig\"][\"" . $parameter . "\"] wurde nicht gesetzt."
                    );
                }
            }

            // Werte setzen
            $this->config = $config;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Getter-Methode für das Konfigurations-Array
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
