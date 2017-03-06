#!/usr/bin/env php
<?php
/*
 * Wetterwarnung-Downloader für neuthardwetter.de by Jens Dutzi
 * Version 2.0-dev
 * 05.03.2017
 * (c) tf-network.de Jens Dutzi 2012-2017
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
 *Konfiguration für die Unwetter-Informationen
 */
if(file_exists(dirname(__FILE__) . "/config.local.php")) {
	require_once(dirname(__FILE__) . "/config.local.php");
} else {
	die("Die Konfigurationsdatei config.local.php wurde nicht gefunden.");
}

/*
 * ======================================================
 * ======================================================
 * ==				Warnlage-Update Bot                ==
 * ======================================================
 * ======================================================
 */
try {
	// Notwendige Libs laden
	require_once(dirname(__FILE__) . "/botLib/functions.lib.php");
	require_once(dirname(__FILE__) . "/botLib/warnparser.lib.php");

/*
 * Script-Header ausgeben
 */

	echo(PHP_EOL);
	echo("Starte Warnlage-Update " . date("d.m.Y H:i:s") . ":" . PHP_EOL);
	echo("=====================================" . PHP_EOL);

	// Wetterwarnung auf via Twitter erzwingen?
	$forceWetterUpdate = false;

	echo("*** Aktualisiere die Wetterwarnungen: ***" . PHP_EOL);

	// FTP-Verbindung aufbauen
	$conn_id = ftp_connect($ftp["host"]);
	if($conn_id === false) {
	    fwrite(STDERR, "FTP Verbindungsaufbau zu " . $ftp["host"] . " ist fehlgeschlagen" . PHP_EOL);
	    exit(1);
	}

	// Login mit Benutzername und Passwort
	$login_result = @ftp_login($conn_id, $ftp["username"], $ftp["password"]);

	// Verbindung überprüfen
	if ((!$conn_id) || (!$login_result)) {
		throw new Exception("Verbindungsaufbau zu zu " . $ftp["host"] . " mit Benutzername " . $ftp["username"] . " fehlgeschlagen.");
	} else {
		echo "Verbunden zu " . $ftp["host"] . " mit Benutzername " . $ftp["username"] . PHP_EOL;
	}

	// Auf Passive Nutzung umschalten
	@ftp_pasv($conn_id, true);

	// Lade Wetterdaten
	echo("Lade aktuelle Wetterwarnungen von dem Grundversorgungsdaten des DWD (Pfad: Verzeichnis " . $unwetterConfig["localFolder"] . ")" . PHP_EOL);
	$remoteFile = fetchUnwetterDaten($unwetterConfig, $conn_id);
	echo("... Auftrag ausgeführt!" . PHP_EOL . PHP_EOL);

	// FTP Verbindung schließen
	ftp_close($conn_id);

	// Prüfe welche Wetterwarnungen gelöscht werden können
	echo("Prüfe welche Wetterwarnung-Dateien nicht mehr benötigt werden und veraltet sind:" . PHP_EOL);
	cleanupUnwetterDaten($unwetterConfig, $remoteFile);
	echo("... Auftrag ausgeführt!" . PHP_EOL . PHP_EOL);

	// Verarbeite die lokalen Wetterwarnungen zu der JSON Datei für die Homepage
	echo("Verarbeite die XML Wetterwarnungen vom DWD für die eigene Homepage (JSON-Format):" . PHP_EOL);
	$forceWetterUpdate = parseWetterWarnung($unwetterConfig, $optFehlerMail);
	echo("... Auftrag ausgeführt!" . PHP_EOL . PHP_EOL);
} catch (Exception $e) {
	// Fehler-Handling
	sendErrorMessage($optFehlerMail, $e->getMessage());
}
?>
