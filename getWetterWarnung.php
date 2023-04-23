#!/usr/bin/env php
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

error_reporting(E_ERROR | E_PARSE);

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

use blog404de\Standard\ErrorLogging;
use blog404de\WetterWarnung\WetterWarnung;

// Root-Verzeichnis festlegen
$warnBot = null;
$logger = null;

try {
    // Zeitmessung starten
    $timeStart = microtime(true);

    // Script initialisieren
    /** @var array $unwetterConfig */
    $unwetterConfig = null;
    if (is_readable(__DIR__ . '/config.local.php')) {
        require_once __DIR__ . '/config.local.php';
    } else {
        throw new RuntimeException(
            "Konfigurationsdatei 'config.local.php' existiert nicht. Zur Konfiguration lesen Sie README.md"
        );
    }

    // Autoloader initialisieren
    require_once __DIR__ . '/vendor/autoload.php';

    // Error-Logger instanzieren
    $logger = new ErrorLogging();
    if (!empty($optFehlerMail)) {
        $logger->setLogToMailSettings($optFehlerMail);
    }
    if (!empty($optFehlerLogfile)) {
        $logger->setLogToFileSettings($optFehlerLogfile);
    }
    if (\defined('LOGWITHTRACE')) {
        $logger->setWithTrace(true);
    }

    // WarnParser instanzieren
    $warnBot = new WetterWarnung();

    // Prüfe auf Konsolen-Parameter

    // Hilfe ausgeben
    if (\in_array('--hilfe', $argv, true) || \in_array('-h', $argv, true) || \in_array('--help', $argv, true)) {
        echo 'tfnApps.de WarnWetter-Bot' . PHP_EOL;
        echo PHP_EOL;
        echo 'Parameter:' . PHP_EOL;
        echo "--help | -h\t\tDiese Hilfe anzeigen" . PHP_EOL;
        echo "--forceAction | -f\tAction auslösen unabhängig davon ob eine Änderung der Warnlage vorliegt" . PHP_EOL;

        exit(0);
    }

    // Auf unbekannter Paramter prüfen
    // Parameter wurden gesetzt -> prüfe ob Parameter gültig ist
    if ((\count($argv) > 1) && !\in_array('--forceAction', $argv, true) && !\in_array('-f', $argv, true)) {
        throw new RuntimeException(
            'Der angegebene Parameter ist unbekannt. Mit --hilfe bekommen Sie alle ' .
            'zur Verfügung stehenden Parameter angezeigt.'
        );
    }

    // Beginn des eigentlichen Scripts
    $header = 'Starte Warnlage-Update ' . date('d.m.Y H:i:s') . ':' . PHP_EOL;
    $header .= str_repeat('=', mb_strlen(trim($header))) . PHP_EOL;
    echo $header;

    // Prüfe Konfigurationsdatei auf Vollständigkeit
    $configKeysNeeded = ['WarnCells', 'localJsonWarnfile', 'localFolder', 'ftpmode', 'Action', 'Archive'];
    foreach ($configKeysNeeded as $configKey) {
        if (!\array_key_exists($configKey, $unwetterConfig)) {
            throw new RuntimeException(
                "Die Konfigurationsdatei config.local.php ist unvollständig ('" . $configKey . "' ist nicht vorhanden)"
            );
        }
    }

    // WarnParser starten

    // Konfiguriere Bot
    $warnBot->setLocalFolder($unwetterConfig['localFolder']);
    $warnBot->setLocalJsonFile($unwetterConfig['localJsonWarnfile']);

    if (!\array_key_exists('localIconFolder', $unwetterConfig) || false === $unwetterConfig['localIconFolder']) {
        $unwetterConfig['localIconFolder'] = '';
    }
    $warnBot->setLocalIconFolder($unwetterConfig['localIconFolder']);

    // Archiv-Support konfigurieren
    if ($unwetterConfig['Archive']) {
        if (!\array_key_exists('ArchiveConfig', $unwetterConfig)) {
            throw new RuntimeException(
                'Einer oder mehrere Konfigurationsparameter für die Archive-Klasse fehlen (siehe README.md)'
            );
        }

        if (!\is_array($unwetterConfig['ArchiveConfig'])) {
            throw new RuntimeException(
                'Bei dem Konfigurationsparameter für die Archive-Klasse handelt es sich um ' .
                'kein Array (siehe README.md)'
            );
        }

        // Setze Konfiguration für die Archive-Unterstützung
        $warnBot->setArchiveConfig($unwetterConfig['ArchiveConfig']);
    }

    // Action-Support konfigurieren
    if ($unwetterConfig['Action']) {
        if (!\array_key_exists('ActionConfig', $unwetterConfig)) {
            throw new RuntimeException(
                'Einer oder mehrere Konfigurationsparameter für die Action-Klasse fehlen ' .
                '(siehe README.md)'
            );
        }

        if (!\is_array($unwetterConfig['ActionConfig'])) {
            throw new RuntimeException(
                'Bei dem Konfigurationsparameter für die Action-Klasse handelt es sich um ' .
                'kein Array (siehe README.md)'
            );
        }

        if (\in_array('--forceAction', $argv, true) || \in_array('-f', $argv, true)) {
            $forceAction = true;
        } else {
            $forceAction = false;
        }

        $warnBot->setActionConfig(
            ['localIconFolder' => $unwetterConfig['localIconFolder']],
            $unwetterConfig['ActionConfig'],
            $forceAction
        );
    }

    if (!\array_key_exists('passiv', $unwetterConfig['ftpmode'])) {
        // Passiv/Aktive Verbindung zum FTP Server ist nicht konfiguriert -> daher aktive Verbindung
        $unwetterConfig['ftpmode']['passiv'] = false;
    }

    // Neue Wetterwarnungen vom DWD FTP Server holen
    if (!\defined('BLOCKFTP')) {
        $warnBot->setFtpPassiv($unwetterConfig['ftpmode']['passiv']);
        $warnBot->connectToFTP('opendata.dwd.de', 'Anonymous', 'Anonymous');
        $warnBot->updateFromFTP();
        $warnBot->disconnectFromFTP();
        $warnBot->cleanLocalDownloadFolder();
    }

    // Wetterwarnungen vorbereiten
    $warnBot->prepareWetterWarnungen();

    // Wetterwarnungen parsen
    if (\is_array($unwetterConfig['WarnCells']) && \count($unwetterConfig['WarnCells']) > 0) {
        foreach ($unwetterConfig['WarnCells'] as $warnCell) {
            if (\array_key_exists('warnCellId', $warnCell) && \array_key_exists('stateShort', $warnCell)) {
                $warnBot->parseWetterWarnungen($warnCell['warnCellId'], $warnCell['stateShort']);
            } else {
                // WarnCell-Konfiguration fehlen die benötigten Angaben
                throw new RuntimeException(
                    'Der Konfigurationsparameterfür die WarnCellId-Array beinhaltet nicht die ' .
                    'notwendigen Informationen (siehe README.md)'
                );
            }
        }
    } else {
        // Variablen-Typ ist falsch
        throw new RuntimeException(
            'Der Konfigurationsparameter für die WarnCellId ist kein Array oder Array beinhaltet kein Eintrag'
        );
    }

    // Speichere Wetterwarnungen
    $warnBot->saveToFile(true);

    // Archiv- und Action-Funktion Wetterwarnungen
    $warnBot->startExtensions($unwetterConfig['Archive'], $unwetterConfig['Action']);

    // Zeitmessung beenden
    $timeLaufzeit = microtime(true) - $timeStart;

    // Script-Abschluss
    echo PHP_EOL;
    echo '*** WetterWarnung-Script erfolgreich abgeschlossen (Laufzeit: ';
    echo sprintf('%.3f', $timeLaufzeit) . ' Sekunden) ***';
    echo PHP_EOL;
} catch (RuntimeException|\Exception $e) {
    // Fehler-Handling
    if (\is_object($logger)) {
        if (\is_object($warnBot)) {
            $logger->logError($e, $warnBot->getTmpFolder());
        } else {
            $logger->logError($e);
        }
    } else {
        fwrite(STDERR, 'Unbekannter Laufzeitfehler in ' . __FILE__ . ': ' . $e->getMessage() . PHP_EOL);
        fwrite(STDERR, $e->getTraceAsString());
    }

    exit(-1);
}
