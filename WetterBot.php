#!/usr/bin/env php
<?php
/**
 * Wetterwarnung-Downloader für neuthardwetter.de by Jens Dutzi
 *
 * @package    blog404de\WetterScripts
 * @subpackage ConfigFile
 * @author     Jens Dutzi <jens.dutzi@tf-network.de>
 * @copyright  2012-2017 Jens Dutzi
 * @version    2.5.5-dev
 * @license    MIT
 *
 * Stand: 2017-08-26
 *
 * Lizenzinformationen (MIT License):
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this
 * software and associated documentation files (the "Software"), to deal in the Software
 * without restriction, including without limitation the rights to use, copy, modify,
 * merge, publish, distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies
 * or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A
 * PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

/*
 * ======================================================
 * ======================================================
 * ==				Warnlage-Update Bot                ==
 * ======================================================
 * ======================================================
 */
// Root-Verzeichnis festlegen
try {
	$unwetterConfig = [];
	if(is_readable(dirname( __FILE__ ) . "/config.local.php")) {
		require_once dirname( __FILE__ ) . "/config.local.php";
	} else {
		throw new Exception("Konfigurationsdatei 'config.local.php' existiert nicht. Zur Konfiguration lesen Sie README.md");
	}

	// Notwendige Libs laden
	require_once  dirname( __FILE__ ) . "/botLib/Toolbox.php";
	require_once  dirname( __FILE__ ) . "/botLib/ErrorLogging.php";
	require_once  dirname( __FILE__ ) . "/botLib/WarnParser.php";

	/*
 	 * Script-Header ausgeben
 	 */

	echo(PHP_EOL);
	$header  = "Starte Warnlage-Update " . date("d.m.Y H:i:s") . ":" . PHP_EOL;
	$header .= str_repeat("=", strlen(trim($header))) . PHP_EOL;
	echo $header;

	// Prüfe Konfigurationsdatei auf Vollständigkeit
	$configKeysNeeded = ["WarnCells", "localJsonWarnfile", "localFolder", "ftpmode"];
	foreach ($configKeysNeeded as $configKey) {
		if (array_key_exists($configKey, $unwetterConfig)) {
		} else {
			throw new Exception("Die Konfigurationsdatei config.local.php ist unvollständig ('" . $configKey . "' ist nicht vorhanden)");
		}
	}
	/*
	 *  WarnParser instanzieren
	 */
	$warnBot = new \blog404de\WetterScripts\WarnParser();


	// Konfiguriere Bot
	$warnBot->setLocalFolder($unwetterConfig["localFolder"]);
	$warnBot->setLocalJsonFile($unwetterConfig["localJsonWarnfile"]);

	if($unwetterConfig["Archive"]) {
		// MySQL Zugangsdaten setzen für Archiv-Funktion
		$warnBot->setMysqlConfig($unwetterConfig["MySQL"]);
	}

	if(!empty($optFehlerMail)) $warnBot->setLogToMail($optFehlerMail);
	if(!empty($optFehlerLogfile)) $warnBot->setLogToFile($optFehlerLogfile);

	if(!array_key_exists("passiv", $unwetterConfig["ftpmode"])) {
		// Passiv/Aktive Verbindung zum FTP Server ist nicht konfiguriert -> daher aktive Verbindung
		$unwetterConfig["ftpmode"]["passiv"] = false;
	}

	// Neue Wetterwarnungen vom DWD FTP Server holen
	if(!defined("BLOCKFTP")) {
		$warnBot->connectToFTP("opendata.dwd.de", "Anonymous", "Anonymous", $unwetterConfig["ftpmode"]["passiv"]);
		$warnBot->updateFromFTP();
		$warnBot->disconnectFromFTP();
		$warnBot->cleanLocalDownloadFolder();
	}

	// Wetterwarnungen vorbereiten
	$warnBot->prepareWetterWarnungen();

	// Wetterwarnungen parsen
    if(is_array($unwetterConfig["WarnCells"]) && count($unwetterConfig["WarnCells"]) > 0) {
        foreach ($unwetterConfig["WarnCells"] as $warnCell) {
            if(array_key_exists("warnCellId", $warnCell) && array_key_exists("stateShort", $warnCell)) {
                $warnBot->parseWetterWarnungen($warnCell["warnCellId"], $warnCell["stateShort"]);
            } else {
                // WarnCell-Konfiguration fehlen die benötigten Angaben
                throw new Exception(
                    "Der Konfigurationsparameterfür die WarnCellId-Array beinhaltet nicht die "  .
                    "notwendigen Informationen (siehe README.md)"
                );
            }
            }
    } else {
        // Variablen-Typ ist falsch
        throw new Exception(
            "Der Konfigurationsparameter für die WarnCellId ist kein Array oder Array beinhaltet kein Eintrag"
        );
    }

	// Speichere Wetterwarnungen
	$test = $warnBot->saveToLocalJsonFile();

	// Cache aufräumen
	$warnBot->cleanLocalCache();
} catch (Exception $e) {
	// Fehler-Handling
	fwrite(STDERR,"Fataler Fehler: " . $e->getFile() . ":" . $e->getLine() . " - " .  $e->getMessage());
	exit(-1);
}
?>
