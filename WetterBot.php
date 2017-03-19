#!/usr/bin/env php
<?php
/**
 * Wetterwarnung-Downloader für neuthardwetter.de by Jens Dutzi
 *
 * @version 2.0-dev 2.0.0-dev
 * @copyright (c) tf-network.de Jens Dutzi 2012-2017
 * @license MIT
 *
 * @package blog404de\WetterScripts
 *
 * Stand: 05.03.2017
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
try {
	// Root-Verzeichnis festlegen
	define( 'ROOT_PATH', dirname( __FILE__ ) . "/" );

	// Notwendige Libs laden
	$unwetterConfig = [];
	require_once ROOT_PATH . "botLib/WarnParser.class.php";

	/*
 	 * Script-Header ausgeben
 	 */

	echo(PHP_EOL);
	$header  = "Starte Warnlage-Update " . date("d.m.Y H:i:s") . ":" . PHP_EOL;
	$header .= str_repeat("=", strlen(trim($header))) . PHP_EOL;
	echo $header;

	if(is_readable(ROOT_PATH . "config.local.php")) {
		require_once ROOT_PATH . "config.local.php";
	} else {
		throw new Exception("Konfigurationsdatei 'config.local.php' existiert nicht. Zur Konfiguration lesen Sie README.md");
	}

	// Prüfe Konfigurationsdatei auf Vollständigkeit
	$configKeysNeeded = ["WarnCellId", "localJsonWarnfile", "localFolder", "ftp"];
	foreach ($configKeysNeeded as $configKey) {
		if (array_key_exists($configKey, $unwetterConfig)) {
		} else {
			throw new Exception("Die Konfigurationsdatei config.local.php ist unvollständig ('" . $configKey . "' ist nicht vorhanden)");
		}
	}
	if (!array_key_exists("host", $unwetterConfig["ftp"]) || !array_key_exists("username", $unwetterConfig["ftp"]) || !array_key_exists("password", $unwetterConfig["ftp"])) {
		throw new Exception("FTP-Zugangsdaten sind nicht vollständig in config.local.php");
	}


	/*
	 *  WarnParser instanzieren
	 */
	$warnBot = new \blog404de\WetterScripts\WarnParser();

	// Konfiguriere Bot
	$warnBot->setLocalFolder($unwetterConfig["localFolder"]);
	$warnBot->setLocalJsonFile($unwetterConfig["localJsonWarnfile"]);

	if(!empty($optFehlerMail)) $warnBot->setLogToMail($optFehlerMail);
	if(!empty($optFehlerLogfile)) $warnBot->setLogToFile($optFehlerLogfile);

	if(!array_key_exists("passiv", $unwetterConfig["ftp"])) {
		// Passiv/Aktive Verbindung zum FTP Server ist nicht konfiguriert -> daher aktive Verbindung
		$unwetterConfig["ftp"]["passiv"] = FALSE;
	}

	// Neue Wetterwarnungen vom DWD FTP Server holen
	if(!defined("BLOCKFTP")) {
		$warnBot->connectToFTP($unwetterConfig["ftp"]["host"], $unwetterConfig["ftp"]["username"], $unwetterConfig["ftp"]["password"], $unwetterConfig["ftp"]["passiv"]);
		$warnBot->updateFromFTP();
		$warnBot->disconnectFromFTP();
		$warnBot->cleanLocalDownloadFolder();
	}

	// Wetterwarnungen vorbereiten
	$warnBot->prepareWetterWarnungen();

	// Wetterwarnungen parsen
	$warnBot->parseWetterWarnungen($unwetterConfig["WarnCellId"]);

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
