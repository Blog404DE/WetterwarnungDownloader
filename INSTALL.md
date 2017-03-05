# Wetterwarnung Downloader Script

## Einleitung

Bei dem Wetterwarnung-Downloader handelt es sich um ein Script zum automatischen herunterladen aktueller Wetterwarnungen für eine bestimmte Warnregion. Die Wetterwarnungen werden im Rahmen der Grundversorgung des DWD bereitgestellt. Details zur Grundversorgung finden sich auf der [NeuthardWetterScripts Hauptseite](https://github.com/Blog404DE/NeuthardWetterScripts).

Bitte beachtet: es handelt sich um eine erste Vorab-Version des Scripts. Auch wenn das Script ausführlich getestet wurde, so kann niemand garantieren, dass keine Fehler oder Probleme auftreten. 

##### Wichtige Änderungen:
- *08.11.2015:* Die Struktur der Ziel-JSON Datei wurde vereinheitlicht und um ein Zähler mit der Anzahl der Wetterwarnungen erweitert. Anpassungen an den Scripten sind daher notwendig.
	

## Anleitung zur Einrichtung des Wetterwarnung-Downloader

### Vorraussetzungen:

- Linux (unter Debian getestet)
- PHP 5.5 (oder neuer) mit FTP-Modul einkompiliert
- wget

### Vorbereitung:

1. Anmeldung für die Grundversorgungs-Daten des Deutschen Wetterdienstes (siehe Einleitung)

2. Download des Script-Pakets https://github.com/Blog404DE/NeuthardWetterScripts/archive/master.zip und entpacken der ZIP Datei mittels unzip.

3. Wechseln in den Ordner mit den benötigten Scripten:
	```sh
	cd WetterwarnungDownloader
	```

4. Download der Liste mit den Warngebieten (WarnCellID) des Deutschen Wetterdienstes

	Hierzu ist es zuerst notwendig das ShellScript *fetchManual.sh* nut einem beliebigen Editor zu öffnen und in folgenden beiden Zeilen die vom DWD übermittelten Zugangsdaten einzutragen.
	
	```bash
	# Zugangsdaten zum DWD Grundversorgungs-Server
	FTPUSER="**********"
	FTPPASSWD="********"
	```
	
	Danach führen wir das Shell-Script aus:
	
	```sh
	./fetchManual.sh
	```
	
	Nach dem ausführen des Scripts befinden sich mehrere PDF-Dateien und eine CSV Datei im aktuellen Verzeichnis. Es handelt sich dabei um verschiedene Anleitungen und eine Liste der Warn-Regionen des DWD, welche später unter anderem für die Konfiguration notwendig sind. Zusätzlich sind diese Dokumente auch interessant für eventuelle Anpassungen des Scripts.
	

> **Wichtiger Hinweis:**
> Im Anschluss können wir das Shell-Script ```fetchManual.sh``` löschen. Kopieren Sie dieses Script *niemals auf Ihren Webserver*, da ansonsten Ihre Zugangsdaten zum DWD FTP Server herausgelesen werden können
	
### WarnCell ID ermitteln

Wichtig für das Script ist zu ermitteln, für welche Kennung die Warnregion besitzt, für welche man die Wetterwarnungen ermitteln möchte. Eine Liste der Regionen findet sich in der im vorherigen Schritt heruntergeladenen ```legend_warnings_CAP_WarnCellsID.csv``` oder ```legend_warnings.pdf```. In der PDF Datei befindet sich die Liste ab Seite 14 und relativ ist die Spalte ```Warngebiet im DWD``` (Name des Gebiets) sowie ```WarnCellIdab Ende 2015 (cap)``` (die zwingend für die Konfiguration benötigte WarnCellID). Die CSV Datei können Sie in einer beliebigen Tabellenkalkulationsprogramm (OpenOffice, LibreOffice, Microsoft Excel oder Numbers) öffnen und relevant sind hier die Spalten ```WARNCELLID``` sowie ```NAME```. 

> **Anmerkung:** Nutzen Sie ausschließlich die Nummer aus den genannten Spalten. Die Zahlen in den restlichen beiden Spalten sind für andere Warnmeldungen in einem anderen Format bzw. seit dem 10/2015 nicht mehr aktiv. Sollten Sie die falsche Nummer verwenden, so kann das Script keine Wetterwarnungen finden.
	

### Konfiguration des Script zum abrufen der Wetter-Warnungen:

Bei dem eigentlichen Script zum abrufen der Wetter-Warnungen handelt es sich um die Datei ```WetterBot.php```. Das Script selber wird gesteuert über die ```config.inc.php``` Datei, welche sich im gleichen Verzeichnis befindet.

Die anzupassenden Konfigurationsparameter in der *config.inc.php* lauten wie folgt:

1. Damit das Script Zugriff auf die Grundversorgungsdaten des DWD bekommt müssen zuerst die FTP Zugangsdaten hinterlegt werden:

	```php
	// FTP Zugangsdaten:
	$ftp["host"]        = "ftp-outgoing2.dwd.de";
	$ftp["username"]    = "********************";
	$ftp["password"]    = "********************";
	```

	Die benötigten Zugangsdaten und den Hostnamen wird vom DWD per E-Mail nach der Registrierung (siehe Vorbereitung) mitgeteilt. Bei ```$ftp["username"]``` handelt es sich um den Benutzername und bei ```$ftp["password"]``` um das zugeteilte Passwort. Der Hostname ```$ftp["hostname"]``` muss in der Regel nicht angepasst werden.
	
2. Der zweite Konfigurationsparmaeter ist die Angabe für die Region, für welche die Wetterwarnungen ermittelt werden sollen:

	```php
	$unwetterConfig["WarnCellId"]      = "908215999";  
	```
	
	Die WarnCellID ist dabei die Nummer, welche im vorherigen Schritt ermittelt wurde.
	
3. Speicherpfad für die JSON Datei mit den Wetterwarnung-Daten:

	```php
	$unwetterConfig["localJsonWarnfile"]= "/pfad/zur/wetterwarnung.json";
	```
	
	Der angegebene Pfad sollte - je nach Art der Auswertung der Wetterwarnung - innerhalb eines vom Webserver zugänglichen Ordner sich befinden.
	
4. Speicherpfade für die vom DWD Server heruntergeladenen Wetterwarnung-Dateien:

	```php
	$unwetterConfig["localFolder"]		= "/pfad/zum/speicherordner/fuer/wetterWarnungen";
	//$unwetterConfig["localDebugFolder"]	= $unwetterConfig["localFolder"] . "/debug";
	```
	
	Bei *localFolder* handelt es sich um den Pfad, der die aktuellste Datei mit Wetterwarnungen beinhaltet, welche vom DWD FTP Server heruntergeladen wurde.
	
	Der zweite (optionale) Konfigurationsparameter beinhaltet die Wetterwarnungen, welche das Script aufgrund des Nachrichten-Typs (z.B. Update) nicht verarbeitet werden konnte. Sie können zu Testzwecken wie in diesem Beispiel ein Unterordner angeben. Im normalen Betriebsverlauf können Sie *localDebugFolder* allerdings auskommentiert lassen.

5. Ordner auf dem DWD FTP Server mit den zu verarbeitenden Wetterwarnungen:

	```php
	$unwetterConfig["remoteFolder"] = "/gds/gds/specials/alerts/cap/GER/status";
	```
	
	Dieser Konfigurationsparameter muss in der Regel nicht angepasst werden, sofern sich die Verzeichnisstruktur auf dem FTP Server nicht ändert. Normalerweise werden solche Änderungen vom DWD per E-Mail vorher auch mitgeteilt.  

6. (optional) Für Fehler-Debugging: im Fehlerfall eine E-Mail zustellen:

	```php
	$optFehlerMail		= array("empfaenger" => "deine.email@example.org",
							"absender"	 => "deine.email@example.org");										
	```
	
	Dieses Array beinhaltet die Absender- und Empfänger-Adresse die für den Versand von E-Mails im Fehlerfall verwendet werden. Die E-Mail Unterstützung sollte *ausschließlich* zu *Debug-Zwecken* aktiviert werden, da durchaus viele E-Mails generiert werden können. Es besteht auch die Möglichkeit die Fehler in eine Log-Datei aufzeichnen zu lassen. Näheres hierzu bei der Erklärung zur EInrichtung als Cronjob.
	

> **Hinweise:** Die hin 3 und 4 angegebenen Pfade müssen auf Ihrem System bereits existieren, da es ansonsten zu einer Fehlermeldung beim ausführen des Scripts kommt. 

### Das PHP-Script ausführbar machen und als Cronjob hinterlegen

1. Das konfigurierte Scripte startfähig machen

	```sh
	chmod +x WetterBot.php
	```
	
2. Shell-Script für den Aufruf als Cronjob. Ein direkter Aufruf bietet sich nicht an, da es ansonsten zu parallelen Aufruf des Scripts kommen kann. Dies kann dabei zu unerwünschten Effekten führen bis zum kompletten hängen des Systems. 

	Um dies zu verhindern bietet sich die Verwendung einer Lock-Datei an, wie in folgendem Beispiel exemplarisch gezeigt:
	
	```bash
	#!/bin/bash
	LOCKFILE=/tmp/$(whoami)_$(basename $0).lock
	[ -f $LOCKFILE ] && { echo "$(basename $0) läuft schon"; exit 1; }

	lock_file_loeschen() {
    	    rm -f $LOCKFILE
	}

	trap "lock_file_loeschen ; exit 1" 2 9 15

	# Lock-Datei anlegen
	echo $$ > $LOCKFILE


	# Starte Script
	/pfad/zum/script/WetterBot.php

	# Lösche Lockfile
	lock_file_loeschen
	exit 0
	```

	In diesem Script müssen Sie selbstverständlich den Pfad zum WetterBot-Script entsprechend anpassen.
	
3. Cronjob anlegen 

	```sh
	crontab -e
	```
	
	Als Update-Frequenz hat sich alle 10 bis 14 Minuten herausgestellt, auch wenn der DWD öfters die Wetterdaten aktualisiert (z.T. minütlich). Für ein ausführen des Cronjob alle 15 Minuten würde die Cronjob-Zeile wie folgt aussehen:
	```*/10 * * * * /pfad/zum/script/cron.WetterBot.sh >/dev/null```, wobei hier der Pfad zum Shell-Script aus Schritt 2 angepasst werden muss.
	
#### Loggen der Ausgabe und Fehler-Handling:

Es besteht die Möglichkeit die Ausgabe des Scripts in Log-Dateien aufzeichnen zu lassen. Hierfür existieren mehrere Möglichkeiten. 

1. Sie können die komplette Ausgabe des Scripts aufzeichnen, indem Sie in der Cronjob-Zeile folgendes hinzufügen:

	```*/10 * * * * /pfad/zum/script/cron.WetterBot.sh 2>&1 >>/var/log/wetterwarnbot_log```

	*2>&1* bewirkt, dass die Fehlermeldungen umgeleitet und zusammen mit der Standardmeldungen ausgegeben werden. *>>* wiederum bewirkt, dass die Standardmeldungen in die danachfolgende Datei fortlaufend geschrieben werden.

2. Es besteht aber auch die Möglichkeit nur Fehler aufzuzeichnen, indem Sie folgende Änderung vornehmen:

	```*/10 * * * * /pfad/zum/script/cron.WetterBot.sh 2>>/var/log/wetterwarnbot_log >/dev/null```

	*2>>* leitet in diesem Fall die Fehlermeldungen fortlaufend in die angegebene Datei um, während die Standard-Ausgaben über *>/dev/null* in den virtuellen Papierkorb übermittelt werden.
	
Die zweite Variante empfiehlt sich dabei für den Live-Betrieb, während die erste Variante primär für Debug-Zwecke geeignet ist. Man sollte desweiteren beachten, dass die Log-Dateien regelmäßig bereinigt werden z.B. mittels logrotate (ähnlich den System-Logs). 

	
## Erklärung zur JSON Datei mit der Wetterwarnung

Das Script erzeugt eine JSON Datei mit, welche die Anzahl der Wetterrwarnungen *(anzahl)* sowie die Informationen zu den entsprechenden Wetterwarnungen *(wetterwarnungen)* enthält. Hier zwei Beispiele für diese Datei (exemplarisch mit einer Meldung, wobei das Array mit den Wetterwarnungen durchaus mehrere Meldungen beinhalten kann sowie ein Beispiel einer Datei falls keine Wetterwarnung aktuell existiert):

```json
{  
   "anzahl":1,
   "wetterwarnungen":[  
      {  
         "severity":"Markante Wetterwarnung",
         "urgency":"Immediate",
         "warnstufe":0,
         "startzeit":"O:8:\"DateTime\":3:{s:4:\"date\";s:26:\"2015-11-09 00:00:00.000000\";s:13:\"timezone_type\";i:3;s:8:\"timezone\";s:13:\"Europe\/Berlin\";}",
         "endzeit":"O:8:\"DateTime\":3:{s:4:\"date\";s:26:\"2015-11-10 00:00:00.000000\";s:13:\"timezone_type\";i:3;s:8:\"timezone\";s:13:\"Europe\/Berlin\";}",
         "headline":"Amtliche WARNUNG vor STURMB\u00d6EN",
         "area":"Kreis Rastatt",
         "stateShort":"BW",
         "stateLong":"Baden-W\u00fcrtemberg",
         "altutude":1000,
         "ceiling":3000,
         "hoehenangabe":"H\u00f6henlagen \u00fcber 1000m",
         "description":"Es treten oberhalb 1000 m Sturmb\u00f6en mit Geschwindigkeiten um 75 km\/h (21m\/s, 41kn, Bft 9) aus s\u00fcdwestlicher Richtung auf.",
         "instruction":"ACHTUNG! Hinweis auf m\u00f6gliche Gefahren: Es k\u00f6nnen zum Beispiel einzelne \u00c4ste herabst\u00fcrzen. Achten Sie besonders auf herabfallende Gegenst\u00e4nde.",
         "event":"STURMB\u00d6EN",
         "sender":"DWD \/ Nationales Warnzentrum Offenbach"
      }
   ]
}
```

Liegt aktuell keine Wetterwarnung für die aktuelle Warnregion vor, so sieht die JSON Datei wie folgt aus:

```json
{  
   "anzahl":0,
   "wetterwarnungen":[  

   ]
}
```

### Erklärung der einzelnen Werte des Wetterwarnung-Array

| Wert        	| Erklärung     |
| ------------- | ------------- |
| hash			| md5-Hash erzeugt aus den Angaben der Meldung |
| severity      | Warnstufe der Meldung ¹ |
| urgency       | Soll Wetterwarnung sofort ausgegeben werden oder in der Zukunft ¹  |
| warnstufe		| Die Warnstufe (0 = Vorabinformation bis 4 = Extreme Unwetterwarnung) ² |
| startzeit		| Beginn der Wetterwarnung als *serialisiertes DateTime* Objekt ⁴|
| endzeit		| Ablauf-Datum der Wetterwarnung als *serialisiertes* DateTime Objekt. **Wichtig:** Ist *startzeit und *endzeit* identisch, dann existiert keine Endzeit und der Wert sollte in der Ausgabe ignoriert werden) ⁴|
| headline		| Überschrift der Wetterwarnung|
| area			| Bezeichnung der Region auf die die Wetterwarnung zutrifft |
| stateShort	| Abkürzung des Bundesland in dem die Warnregion sich befindet |
| stateLong		| Ausgeschriebener Name des Bundeslandes in dem sich die Warnregion befindet |
| altitude		| Unterer Wert des Höhenbereichs in **Meter** ³ |
| ceiling		| Oberer Wert Wert des Höhenbereichs in **Meter** ³ |
| hoehenangabe	| Höhenangabe in Text-Form (ebenfalls in Meter) ³ |
| description	| Beschreibungstext der Warnung bzw. Vorwarnung ¹ |
| instruction	| Zusatztext zur Warnung und optional ein Verlängerungshinweis sowie Ersetzungshinweis ¹ |
| event			| Art des Wettereignisses in Kurzform ¹ |
| sender		| Ausstellendes Rechenzentrum des DWD |

¹ Bei diesen Angaben handelt es sich die direkte unveränderte Übernahme aus der Wetterwarnung des DWD. Eine Erklärung der jeweiligen Werte findet sich in der unter *Vorbereitung* heruntergeladenen Datei [legend_warnings_CAP.pdf](https://werdis.dwd.de/infos/legend_warnings_CAP.pdf).

² Die Warnstufen lauten wie folgt: 0 = Vorwarnung, 1 = Wetterwarnung, 2 = Markante Wetterwarnung, 3 = Unwetterwarnung, 4 = Extreme Unwetterwarnung

³ Die Höhenangaben sind im Gegensatz zu der Orginal Wetterwarnung in Meter umgerechnet und können damit direkt verwendet werden. Zusätzliche Angaben zu den Höhenwerten findet sich ebenfalls in der [legend_warnings_CAP.pdf](https://werdis.dwd.de/infos/legend_warnings_CAP.pdf).

⁴ Um z.B. unter PHP auf das serialisierte DateTime Objekt zugreifen zu können, muss es für die Verwendung erst mittels [unserialize](http://php.net/manual/de/function.unserialize.php) in ein DateTime-Objekt deserialisiert werden. **Hinweis:** Ist *startzeit* identisch mit der *endzeit*, dann wurde keine Endzeit vom DWD für die Warnung angegeben und sollte daher bei einer Ausgabe ignoriert werden.

## Abschluss

Es handelt sich bei dem Script um eine erste Vorab-Version des Downloader. Solltet Ihr Fragen oder Anregungen haben, so steht euch offen sich jederzeit an mich per E-Mail (siehe http://www.NeuthardWetter.de) zu wenden. Selbstverständlich könnt Ihr euch an der Weiterentwicklung des Scripts beteiligen und entsprechend Push-Requests senden. 


--
##### Lizenz-Information:

Copyright Jens Dutzi 2015 / Stand: 04.11.2015 / Dieses Werk ist lizenziert unter einer [MIT Lizenz](http://opensource.org/licenses/mit-license.php)

