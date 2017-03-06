<?php
/*
 * Wetterwarnung-Downloader für neuthardwetter.de by Jens Dutzi
 * Version 2.0
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

// FTP Zugangsdaten:
$ftp["host"]        = "ftp-outgoing2.dwd.de";
$ftp["username"]    = "********************";
$ftp["password"]    = "********************";

// Array mit den zu verarbeiteten Landkreisen
// - siehe: Warngebiets-Liste in legend_warnings.pdf / Spalte "WarnCellId ab Ende 2015 (cap)"
// - Beispiel: Landkreis und Stadt Karlsruhe
$unwetterConfig["WarnCellId"]      = "908215999";

// Speicherpfad für JSON Datei mit den aktuellen Wetterwarnungen
$unwetterConfig["localJsonWarnfile"]= "/pfad/zur/wetterwarnung.json";

// Speicherordner für die Orginal Wetterwarnungen vom DWD (localDebugFolder = optionaler Sicherungsordner für Wetterwarnungen mit unbekanntem Nachrichten-Typ wie z.B. "update")
$unwetterConfig["localFolder"]		= "/pfad/zum/speicherordner/fuer/wetterWarnungen";
//$unwetterConfig["localDebugFolder"]	= $unwetterConfig["localFolder"] . "/debug";

// Pfad zu den XML Wetterdaten auf dem DWD FTP Server
$unwetterConfig["remoteFolder"] = "/gds/gds/specials/alerts/cap/GER/status";

/* Sonstige Konfigurationsparameter */

/* Konfiguration für das zusenden von E-Mails sofern der Cronjob nicht komplett durchläuft (optional) */
$optFehlerMail		= array("empfaenger" => "deine.email@example.org",										//Empfänger der Fehler-Mail
	"absender"	 => "deine.email@example.org");										//Absender der Fehler-Mail

// Locals und Zeitzone setzen
setlocale(LC_TIME, "de_DE.UTF-8");
date_default_timezone_set("Europe/Berlin");
?>