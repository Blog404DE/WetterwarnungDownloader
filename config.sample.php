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

// Konfiguration für die Unwetter-Informationen

// Für passive FTP Verbindung aktivieren (falls FTP Transfer fehlschlägt)
$unwetterConfig['ftpmode']['passiv'] = true;

// Array mit den zu verarbeiteten Landkreisen
// - siehe Erklärung in: README.md
// - Beispiel: Stadt Karlsruhe (WarnCellID 808212000)
$unwetterConfig['WarnCells'] = [
    ['warnCellId' => 808212000, 'stateShort' => 'BW'],
];

// Speicherpfad für JSON Datei mit den aktuellen Wetterwarnungen
$unwetterConfig['localJsonWarnfile'] = '/pfad/zur/wetterwarnung.json';

// Speicherordner für die Orginal Wetterwarnungen vom DWD
$unwetterConfig['localFolder'] = '/pfad/zum/speicherordner/fuer/wetterWarnungen';

// Ordner mit Icons für die Wetterwarnungen (auskommentieren, falls keine Icons verwendet werden sollen)
// Standardmäßig ist es der Ordner "icons" Innerhalb des Projekt-Roots
$unwetterConfig['localIconFolder'] = __DIR__ . DIRECTORY_SEPARATOR . 'icons';

// Optionale Konfigurationsparameter für Fehler-Behandlung / Logging

// Fehler in Log-Datei schreiben
// $optFehlerLogfile    = [ "filename" => "/pfad/zur/log/error_log" ];

// Fehler per E-Mail melden
// $optFehlerMail       = [ "empfaenger" => "deine.email@example.org",
//                          "absender" => "deine.email@example.org" ];

// Optionale Module für Archivierung der Wetterwarnungen und senden der Warnung per Twitter

// Optional: Action-Module ür WetterWarnung aktivieren
$unwetterConfig['Archive'] = false;

// Optional: Konfiguration der Archiv-Klasse
$unwetterConfig['ArchiveConfig']['ArchiveToMySQL'] = [
    'host' => '',
    'username' => '',
    'password' => '',
    'database' => '',
];

// Optionale Action Module aktivieren, um Aktionen bei neuen Warnwetterlagen zu signalisieren

$unwetterConfig['Action'] = false;

// Optional: Action-Funktion "Pushover" für WetterWarnung aktivieren
/*
$unwetterConfig["ActionConfig"]["SendToPushover"] = [
    "apiKey"              => "",
    "userKey"             => "",
    "MessagePrefix"       => "",
    "MessagePostfix"      => "",
    "MessageURL"          => "http://www.neuthardwetter.de",
    "ignoreForceUpdate"   => true
];
 */

// Optional: Action-Funktion "IFTTT" für WetterWarnung aktivieren
/*
$unwetterConfig["ActionConfig"]["SendToITFFF"] = [
    "apiKey"              => "",
    "eventName"           => "",
    "MessagePrefix"       => "",
    "MessagePostfix"      => "",
    "ignoreForceUpdate"    => true
];
*/

// Optional: Action-Funktion "Twitter" für WetterWarnung aktivieren
/*
$unwetterConfig["ActionConfig"]["SendToTwitter"] = [
    "consumerKey"         => "",
    "consumerSecret"      => "",
    "oauthToken"          => "",
    "oauthTokenSecret"    => "",
    "TweetPlace"          => "",
    "MessagePrefix"       => "",
    "MessagePostfix"      => "",
    "ignoreForceUpdate"    => false
];
*/
