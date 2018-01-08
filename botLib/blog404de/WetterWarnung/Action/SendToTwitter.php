<?php
/**
 * WetterWarnung für neuthardwetter.de by Jens Dutzi - SendToTwitter.php
 *
 * @package    blog404de\WetterWarnung
 * @author     Jens Dutzi <jens.dutzi@tf-network.de>
 * @copyright  Copyright (c) 2012-2018 Jens Dutzi (http://www.neuthardwetter.de)
 * @license    https://github.com/Blog404DE/WetterwarnungDownloader/blob/master/LICENSE.md
 * @version    3.0.0-dev
 * @link       https://github.com/Blog404DE/WetterwarnungDownloader
 */

namespace blog404de\WetterWarnung\Action;

use Abraham\TwitterOAuth\TwitterOAuth;
use Exception;

/**
 * Action-Klasse für WetterWarnung Downloader zum senden eines Tweets bei einer neuen Nachricht
 *
 * @package blog404de\WetterWarnung\Action
 */

class SendToTwitter implements SendToInterface
{
    /** @var TwitterOAuth Twitter-Klasse */
    private $connectionId = null;

    /** @var array Twitter-OAUTH Schlüssel */
    private $config = [];

    /** @var array Twitter PlaceID */
    private $placeid = [];

    /** @var string Twitter ScreenName */
    private $screenname;

    /**
     *
     * SendToTwitter constructor.
     * @throws Exception
     */
    public function __construct()
    {
        try {
            // TwitterOAuth-Klasse Pfad ermitteln
            $composerAutoloader = realpath(
                dirname(__FILE__) . "/../../../../vendor/abraham/twitteroauth/autoload.php"
            );

            // Prüfen ob Source-Code existiert
            if ($composerAutoloader === false) {
                throw new Exception(
                    "TwitterOAuth-Library ist nicht im System vorhanden - " .
                    "zur Installation lesen Sie bitte readme.md"
                );
            }

            /** @noinspection PhpIncludeInspection */
            require_once $composerAutoloader;

            // Prüfe ob entsprechende Klasse geladen ist
            if (!class_exists("Abraham\\TwitterOAuth\\TwitterOAuth", true)) {
                throw new Exception(
                    "TwitterOAuth-Library wurde nicht erfolgreich geladen - " .
                    "zur Installation lesen Sie bitte readme.md"
                );
            }
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Action Ausführung starten (Tweet versenden)
     *
     * @param array $parsedWarnInfo
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
                    // Keine Warnwetter-Daten zum twittern vorhanden -> harter Fehler
                    throw new Exception("Die im Archiv zu speicherenden Wetter-Informationen sind ungültig");
                }

                // Status-Ausgabe der aktuellen Wetterwarnung
                echo("\t* Wetterwarnung über " . $parsedWarnInfo["event"] . " für " .
                    $parsedWarnInfo["area"] . PHP_EOL);

                // Aktiviere Verbindung zu Twitter
                if (!is_object($this->connectionId)) {
                    echo("\t\t-> Baue neue Verbindung zu Twitter auf: ");

                    $this->connectionId = new TwitterOAuth(
                        $this->config["consumerKey"],
                        $this->config["consumerSecret"],
                        $this->config["oauthToken"],
                        $this->config["oauthTokenSecret"]
                    );
                    if (!$this->connectionId) {
                        throw new Exception(
                            "Twitter API Anmeldung fehlgeschlagen: " .
                            $this->getErrorText($this->connectionId->getLastHttpCode()) . PHP_EOL
                        );
                    }

                    // UserAgent setzen
                    $this->connectionId->setUserAgent("WetterwarnngDownloader (+http://www.neuthardwetter.de");

                    // Ermittle ScreenName
                    $getScreenname = $this->connectionId->get(
                        "account/verify_credentials",
                        ['include_entities' => true,
                         'skip_status' => true,
                         'include_email' => false]
                    );
                    if ($this->connectionId->getLastHttpCode() != 200) {
                        throw new Exception(
                            "Fehler beim ermitteln des Account-Namen: " .
                            $this->getErrorText($this->connectionId->getLastHttpCode()) . PHP_EOL
                        );
                    }
                    if (!property_exists($getScreenname, "screen_name")) {
                        throw new Exception(
                            "Fehler beim ermitteln des Account-Namen " .
                             "(Account-Name in der API Rückmeldung nicht vorhanden)" . PHP_EOL
                        );
                    }
                    $this->screenname = $getScreenname->{"screen_name"};

                    // Status Ausgabe:
                    echo("Benutzername @" . $this->screenname . PHP_EOL);
                } else {
                    // Verbindung twitter besteht bereits
                    echo("\t\t -> Verwende bestehende Verbindung zu Twitter auf: ");
                    echo("Benutzername @" . $this->screenname . PHP_EOL);
                }

                if (!$warnExists) {
                    // Stelle Tweet zusammen
                    $tweetParameter = $this->composeMessage($parsedWarnInfo);

                    // Sende Tweet ab
                    $this->sendTweet($tweetParameter);
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
     * Tweet absenden an Twitter
     *
     * @param array $tweetParameter
     * @throws Exception
     */
    private function sendTweet(array $tweetParameter)
    {
        try {
            // Sende Tweet
            echo("\t\t -> Sende Tweet " .
                "'" . $tweetParameter["status"] . "' über " .
                "@" . $this->screenname  . ": ");

            $this->connectionId->post("statuses/update", $tweetParameter);
            if ($this->connectionId->getLastHttpCode() != 200) {
                throw new Exception(
                    "Verbindungsfehler zu Twitter: Befehl 'statuses/update' " .
                    $this->getErrorText($this->connectionId->getLastHttpCode())
                );
            }

            echo "erfolgreich" . PHP_EOL;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }


    /**
     * Ermittle die PlaceId bei Bedarf
     *
     * @throws Exception
     */
    private function updatePlaceIdInfo()
    {
        try {
            // Ort ermitteln?
            if (!empty($this->config["TweetPlace"]) && $this->config["TweetPlace"] !== false) {
                if (empty($this->placeid)) {
                    echo("\t\t -> Suche (einmalig) nach angegebener PlaceID für hinterlegten Ort: ");
                    $geoSearch = $this->connectionId->get(
                        "geo/search",
                        ["query" => $this->config["TweetPlace"], "granularity" => "city"]
                    );

                    if ($this->connectionId->getLastHttpCode() != 200) {
                        throw new Exception(
                            "Verbindungsfehler zu Twitter: Befehl 'geo/search' " .
                            $this->getErrorText($this->connectionId->getLastHttpCode())
                        );
                    }

                    if (!property_exists($geoSearch, "result")) {
                        throw new Exception(
                            "Fehler beim ermitteln der Twitter PlaceID anhang des angegebenen Ortes " .
                            "(Result fehlt in Antwort)"
                        );
                    }

                    if (!property_exists($geoSearch->{"result"}, "places")) {
                        throw new Exception(
                            "Fehler beim ermitteln der Twitter PlaceID anhang des angegebenen Ortes " .
                            "(Places fehlt in Antwort)"
                        );
                    }

                    if (count($geoSearch->{"result"}->{"places"}) > 0) {
                        // Kein Ort wurde gefunden
                        $this->placeid = [
                            "id"        => $geoSearch->{"result"}->{"places"}[0]->{"id"},
                            "full_name" => $geoSearch->{"result"}->{"places"}[0]->{"full_name"}
                        ];

                        echo($this->placeid["id"] . " / " . $this->placeid["full_name"]);
                    } else {
                        // Ort wurde gefunden
                        echo("Ort wurde nicht gefunden (ignoriere Ortsangabe für Tweet)");
                        $this->placeid = [];
                    }

                    echo(PHP_EOL);
                } else {
                    echo("\t\t -> Verwende bereits gesuchte PlaceID für hinterlegten Ort: ");
                    echo($this->placeid["id"] . " / " . $this->placeid["full_name"] . PHP_EOL);
                }
            }
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Länge des Tweet ermitteln
     *
     * @param string $tweet
     * @return int
     * @throws Exception
     */
    private function getTweetLength(string $tweet): int
    {
        try {
            // Länge des Tweets ermitteln und ersetze URL durch Dummy-Text mit der Länge des URL Short
            // Service von Twitter
            return mb_strlen(
                preg_replace(
                    "~https?://([^\s]*)~",
                    str_repeat("*", 23),
                    $tweet
                ),
                "UTF-8"
            );
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Tweet zusammenstellen
     *
     * @param array $parsedWarnInfo
     * @return array
     * @throws Exception
     */
    private function composeMessage(array $parsedWarnInfo): array
    {
        try {
            // Aktualisiere bei Bedarf die PlaceID Informationen
            $this->updatePlaceIdInfo();

            // Parameter für Tweet zusammenstellen
            $tweetParameter = [];
            if ($this->placeid !== null) {
                $tweetParameter["place_id"] = $this->placeid["id"];
                $tweetParameter["display_coordinates"] = true;
            }

            // Typ der Warnung ermitteln,Leerzeichen entfernen und daraus ein Hashtag erzeugen
            $severity = "#" .  preg_replace("/\\s+/m", "", $parsedWarnInfo["severity"]);
            $tweet = $severity . " des #DWD";

            // Gebiet einfügen
            $tweet = $tweet . " für " . $parsedWarnInfo["area"];

            // Uhrzeit einfügen
            $startZeit = unserialize($parsedWarnInfo["startzeit"]);
            $endzeit = unserialize($parsedWarnInfo["endzeit"]);
            $tweet = $tweet . " (" . $startZeit->format("H:i") . " bis " . $endzeit->format("H:i") . "):";

            // Haedline hinzufügen
            $tweet = $tweet . " " . $parsedWarnInfo["headline"] . ".";

            // Prä-/Postfix anfügen
            if (!empty($this->config["MessagePrefix"]) && $this->config["MessagePrefix"] !== false) {
                $tweet = $this->config["MessagePrefix"] . " " . $tweet;
            }
            if (!empty($this->config["MessagePostfix"]) && $this->config["MessagePostfix"] !== false) {
                // Postfix erst einmal für spätere Verwendung zwischenspeichernc
                $postfix = " " . $this->config["MessagePostfix"];
            } else {
                $postfix = "";
            }

            $length = $this->getTweetLength($tweet . $postfix);
            if ($length > 280) {
                $tweet = mb_substr(
                    $tweet,
                    0,
                    (280 - $this->getTweetLength($tweet . " ..." . $postfix))
                ) . " ...";
            }
            $tweet = $tweet . $postfix;
            $tweetParameter["status"] = $tweet;

            return $tweetParameter;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Setter für Twitter OAuth Zugangsschlüssel
     *
     * @param array $config
     * @throws Exception
     */
    public function setConfig(array $config)
    {
        try {
            // Alle Paramter verfügbar?
            if (!array_key_exists("consumerKey", $config)) {
                throw new Exception(
                    "Der Konfigurationsparamter [\"ActionConfig\"][\"consumerKey\"] wurde nicht gesetzt."
                );
            }
            if (!array_key_exists("consumerSecret", $config)) {
                throw new Exception(
                    "Der Konfigurationsparamter [\"ActionConfig\"][\"consumerSecret\"] wurde nicht gesetzt."
                );
            }
            if (!array_key_exists("oauthToken", $config)) {
                throw new Exception(
                    "Der Konfigurationsparamter [\"ActionConfig\"][\"oauthToken\"] wurde nicht gesetzt."
                );
            }
            if (!array_key_exists("oauthTokenSecret", $config)) {
                throw new Exception(
                    "Der Konfigurationsparamter [\"ActionConfig\"][\"oauthTokenSecret\"] wurde nicht gesetzt."
                );
            }
            if (!array_key_exists("TweetPlace", $config)) {
                throw new Exception(
                    "Der Konfigurationsparamter [\"ActionConfig\"][\"TweetPlace\"] wurde nicht gesetzt."
                );
            }
            if (!array_key_exists("MessagePrefix", $config)) {
                throw new Exception(
                    "Der Konfigurationsparamter [\"ActionConfig\"][\"MessagePrefix\"] wurde nicht gesetzt."
                );
            }
            if (!array_key_exists("MessagePostfix", $config)) {
                throw new Exception(
                    "Der Konfigurationsparamter [\"ActionConfig\"][\"MessagePostfix\"] wurde nicht gesetzt."
                );
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

    /**
     * Methode zum unwandeln der Error-Codes in Klartext-Fehlermeldungen von Twitter
     *
     * @param $code
     * @return string
     * @throws Exception
     */
    private function getErrorText($code): string
    {
        try {
            $error = [
                200 => "Success.",
                304 => "There was no new data to return.",
                400 => "The request was invalid or cannot be otherwise served. An accompanying error message " .
                        "will explain further. Requests without authentication are considered invalid and will " .
                        "yield this response.",
                401 => "Missing or incorrect authentication credentials. This may also returned in other undefined " .
                        "circumstances.",
                403 => "The request is understood, but it has been refused or access is not allowed. An accompanying " .
                        "error message will explain why. This code is used when requests are being denied due to " .
                        "update limits . Other reasons for this status being returned are listed alongside the error " .
                        "codes in the table below.",
                404 => "The URI requested is invalid or the resource requested, such as a user, does not exist.",
                406 => "Returned when an invalid format is specified in the request.",
                410 => "This resource is gone. Used to indicate that an API endpoint has been turned off.",
                420 => "The an application is being rate limited for making too many requests.",
                422 => "The data is unable to be processed (for example, if an image uploaded to POST account / " .
                        "update_profile_banner is not valid, or the JSON body of a request is badly-formed).",
                429 => "The request cannot be served due to the application’s rate limit having been exhausted " .
                        "for the resource.",
                500 => "Something is broken. This is usually a temporary error, for example in a high load situation " .
                        "or if an endpoint is temporarily having issues.",
                502 => "Twitter is down, or being upgraded.",
                503 => "The Twitter servers are up, but overloaded with requests. Try again later.",
                504 => "The Twitter servers are up, but the request couldn’t be serviced due to some failure within " .
                        "the internal stack. Try again later."
            ];


            if (array_key_exists($code, $error)) {
                return "(" . $code . ") " . $error[$code];
            } else {
                return "(" . $code . ") Unbekannter Fehler";
            }
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }
}
