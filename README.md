# Wetterwarnung Downloader Script

### HINWEIS: Das Repository 'CodeRewrite' beinhaltet eine von Grund auf neu entwickelt Version des Scriptes, welches sich noch in einer Beta-Fassung befindet.

## Einleitung

Bei dem Wetterwarnung-Downloader handelt es sich um ein Script zum automatischen herunterladen aktueller Wetterwarnungen für eine bestimmte Warnregion. Die Wetterwarnungen werden im Rahmen der Grundversorgung des DWD bereitgestellt. Details zur Grundversorgung finden sich auf der [NeuthardWetterScripts Hauptseite](https://github.com/Blog404DE/NeuthardWetter-Scripts).

Bitte beachtet: es handelt sich um eine erste Vorab-Version des Scripts. Auch wenn das Script ausführlich getestet wurde, so kann niemand garantieren, dass keine Fehler oder Probleme auftreten. 

##### Wichtige Änderungen:
- *21.03.2017:* Ein kompletter Code-Rewrite wurde durchgeführt. Aus diesem Grund hat sich auch die Konfigurationsdatei geändert. Unbedingt daher die Konfigurationsdatei nach der folgenden Anleitung neu anlegen. Zusätzlich haben sich auch die Software-Vorrausetzungen geändert. 
	

## Anleitung zur Einrichtung des Wetterwarnung-Downloader

### Vorraussetzungen:

- Linux oder OS X (unter Debian und OS X getestet)
- PHP 7.0.0 (oder neuer) inklusive ftp-, mhash und zip-Modul aktiv
- wget

### Vorbereitung:

1. Anmeldung für die Grundversorgungsdaten des Deutschen Wetterdienstes (siehe Einleitung)

2. Download des Script-Pakets [https://github.com/Blog404DE/WetterwarnungDownloader/archive/master.zip](https://github.com/Blog404DE/WetterwarnungDownloader/archive/master.zip) und entpacken der ZIP Datei mittels unzip.

3. Download der Liste mit den Warngebieten (WarnCellID) des Deutschen Wetterdienstes

	Um die WarnCellID Liste zusammen mit der Entwickler-Dokumentation zu erhalten ist es notwendig 
	das ShellScript *fetchManual.sh* einmalig auszuführen.  
	
	```sh
	cd doc
	./fetchManual.sh
	```
	
	Nach dem ausführen des Scripts befindet sich eine CSV-Datei, sowie mehrere PDF- und eine XLS-Datei im aktuellen Verzeichnis. Es handelt sich dabei um verschiedene Anleitungen und eine Liste der Warn-Regionen des DWD, welche später unter anderem für die Konfiguration notwendig sind. Zusätzlich sind diese Dokumente auch interessant für eventuelle Anpassungen des Scripts.

### WarnCell ID ermitteln

Wichtig für das Script ist zu ermitteln, für welche Kennung die Warnregion besitzt, für welche man die Wetterwarnungen ermitteln möchte. Eine Liste der Regionen findet sich in der im vorherigen Schritt heruntergeladenen ```cap_warncellids_csv.csv```. Die CSV Datei können Sie mittels 
[LibreOffice](https://de.libreoffice.org/) (empfohlen) bzw. [OpenOffice](https://www.openoffice.org/de/) oder jedem Texteditor öffnen. 

In dieser Datenbank sucht man in der Spalte ```NAME``` bzw. ```KURZNAME``` nach dem Ort, für welchen man die Wettergefahren gemeldet haben möchte. In der ersten Spalte findet man die zum Ort gehörende ```WARNCELLID```, welche in der Konfigurationsdatei benötigt wird.

### Konfiguration des Script zum abrufen der Wetter-Warnungen:

Bei dem eigentlichen Script zum abrufen der Wetter-Warnungen handelt es sich um die Datei ```WetterBot.php```. Das Script selber wird gesteuert über die ```config.local.php``` Datei. Um diese Datei anzulegen, kopieren Sie bitte ```config.sample.php``` und nennen die neue Datei ```config.local.php```.

Die anzupassenden Konfigurationsparameter in der *config.local.php* lauten wie folgt:

1. Damit das Script Zugriff auf die c des DWD bekommt müssen zuerst die FTP Zugangsdaten hinterlegt werden:

	```php
	// FTP Zugangsdaten:
	$ftp["host"]        = "ftp-outgoing2.dwd.de";
	$ftp["username"]    = "********************";
	$ftp["password"]    = "********************";
	```

	Die benötigten Zugangsdaten und den Hostnamen wird vom DWD per E-Mail nach der Registrierung (siehe Vorbereitung) mitgeteilt. Bei ```$ftp["username"]``` handelt es sich um den Benutzername und bei ```$ftp["password"]``` um das zugeteilte Passwort. Der Hostname ```$ftp["hostname"]``` muss in der Regel nicht angepasst werden.
	
2. Der zweite Konfigurationsparmaeter ist die Angabe für die Region, für welche die Wetterwarnungen ermittelt werden sollen. Die WarnCellID ist dabei die Nummer, welche im vorherigen Schritt ermittelt wurde. 

	```php
	$unwetterConfig["WarnCellIds"] = 908215999;  
	```
	
	Sie können auch mehrere WarnCellIDs angeben. Hierfür verwenden Sie ein einfaches Array für den Konfigurationsparameter.
	
	```php
	$unwetterConfig["WarnCellIds"] = [908215999, 108215000];  
	```

	
3. Speicherpfad für die JSON Datei mit den Wetterwarnung-Daten:

	```php
	$unwetterConfig["localJsonWarnfile"] = "/pfad/zur/wetterwarnung.json";
	```
	
	Der angegebene Pfad sollte - je nach Art der Auswertung der Wetterwarnung - innerhalb eines vom Webserver zugänglichen Ordner sich befinden.
	
4. Speicherpfade für die vom DWD Server heruntergeladenen Wetterwarnung-Dateien:

	```php
	$unwetterConfig["localFolder"] = "/pfad/zum/speicherordner/fuer/wetterWarnungen";
	```
	
	Bei *localFolder* handelt es sich um den Pfad, der die aktuellste Datei mit Wetterwarnungen beinhaltet, welche vom DWD FTP Server heruntergeladen wurde.
	
**Hinweise:** Die hin 3 und 4 angegebenen Speicher-Pfade müssen auf Ihrem System bereits existieren, da es ansonsten zu einer Fehlermeldung beim ausführen des Scripts kommt. 

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
    	    rm -f ${LOCKFILE}
	}

	trap "lock_file_loeschen ; exit 1" 2 9 15

	# Lock-Datei anlegen
	echo $$ > ${LOCKFILE}


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

Es besteht die Möglichkeit die Ausgabe des Scripts in Log-Dateien aufzeichnen zu lassen oder sich im Fehler-Fall automatisch per E-Mail informieren lassen.

Um dies zu aktivieren müssen Sie in der ```config.local.php``` folgende Parameter auskommentieren: 


1. Fehler in eine Log-Datei aufzeichnen:

	```php
	$optFehlerLogfile = "/pfad/zur/log/error_log";						
	```
	**Wichtig**: Der Speicher-Pfad zur Log-Datei muss bereits existieren. Zusätzlich sollten Sie z.B. mittels Log-Rotate die Log-Datei automatisch rotieren lassen um ein übergroße Log-Datei zu vermeiden.
	
2. Fehler per E-Mail melden:	

	```php
	$optFehlerMail    = [ "empfaenger" => "deine.email@example.org", "absender" => "deine.email@example.org" ];		
	$optFehlerLogfile = "/pfad/zur/log/error_log";						
	```
	
	Dieses Array beinhaltet die Absender- und Empfänger-Adresse die für den Versand von E-Mails im Fehlerfall verwendet werden. Die E-Mail Unterstützung sollte *ausschließlich* zu *Test-Zwecken* aktiviert werden, da durchaus viele E-Mails generiert werden können. 

	
## Erklärung zur JSON Datei mit der Wetterwarnung

Das Script erzeugt eine JSON Datei mit, welche die Anzahl der Wetterrwarnungen *(anzahl)* sowie die Informationen zu den entsprechenden Wetterwarnungen *(wetterwarnungen)* enthält. Hier zwei Beispiele für diese Datei (exemplarisch mit einer Meldung, wobei das Array mit den Wetterwarnungen durchaus mehrere Meldungen beinhalten kann sowie ein Beispiel einer Datei falls keine Wetterwarnung aktuell existiert):

```json
{
    "anzahl": 1,
    "wetterwarnungen": [
        {
            "event": "WINDB\u00d6EN",
            "startzeit": "O:8:\"DateTime\":3:{s:4:\"date\";s:26:\"2017-03-19 08:00:00.000000\";s:13:\"timezone_type\";i:3;s:8:\"timezone\";s:13:\"Europe\/Berlin\";}",
            "endzeit": "O:8:\"DateTime\":3:{s:4:\"date\";s:26:\"2017-03-19 19:00:00.000000\";s:13:\"timezone_type\";i:3;s:8:\"timezone\";s:13:\"Europe\/Berlin\";}",
            "severity": "Wetterwarnung",
            "warnstufe": 1,
            "urgency": "Immediate",
            "headline": "Amtliche WARNUNG vor WINDB\u00d6EN",
            "description": "Es treten Windb\u00f6en mit Geschwindigkeiten um 55 km\/h (15m\/s, 30kn, Bft 7) aus westlicher Richtung auf. In exponierten Lagen muss mit Sturmb\u00f6en bis 65 km\/h (18m\/s, 35kn, Bft 8) gerechnet werden.",
            "instruction": "",
            "sender": "DWD \/ Nationales Warnzentrum Offenbach",
            "web": "http:\/\/www.wettergefahren.de",
            "warncellid": "808215103",
            "area": "Gemeinde Karlsdorf-Neuthard",
            "stateLong": "Baden-W\u00fcrtemberg",
            "stateShort": "BW",
            "altitude": 0,
            "ceiling": 2999,
            "hoehenangabe": "Alle H\u00f6henlagen",
            "hash": "dade1be441248aa1c068230d2c5ab7e1"
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
| web			| URL mit Webseite für weitere Informationen zur Warnung |
| warncellid	| Zur Warnung gehörende WarnCellID |

¹ Bei diesen Angaben handelt es sich die direkte unveränderte Übernahme aus der Wetterwarnung des DWD. Eine Erklärung der jeweiligen Werte findet sich in der unter *Vorbereitung* heruntergeladenen Datei [cap_dwd_profile_de_pdf.pdf](http://www.dwd.de/DE/leistungen/gds/help/warnungen/cap_dwd_profile_de_pdf.pdf?__blob=publicationFile&v=2).

² Die Warnstufen lauten wie folgt: 0 = Vorwarnung, 1 = Wetterwarnung, 2 = Markante Wetterwarnung, 3 = Unwetterwarnung, 4 = Extreme Unwetterwarnung, -1 = Unbekannter Warntyp

³ Die Höhenangaben sind im Gegensatz zu der Orginal Wetterwarnung in Meter umgerechnet und können damit direkt verwendet werden. Zusätzliche Angaben zu den Höhenwerten findet sich ebenfalls in der [cap_dwd_profile_de_pdf.pdf](http://www.dwd.de/DE/leistungen/gds/help/warnungen/cap_dwd_profile_de_pdf.pdf?__blob=publicationFile&v=2).

⁴ Um z.B. unter PHP auf das serialisierte DateTime Objekt zugreifen zu können, muss es für die Verwendung erst mittels [unserialize](http://php.net/manual/de/function.unserialize.php) in ein DateTime-Objekt deserialisiert werden. 
**Hinweis:** Ist *startzeit* identisch mit der *endzeit*, dann wurde keine Endzeit vom DWD für die Warnung angegeben und sollte daher bei einer Ausgabe ignoriert werden.

## Abschluss

Solltet Ihr Fragen oder Anregungen haben, so steht euch offen sich jederzeit an mich per E-Mail (siehe http://www.NeuthardWetter.de) zu wenden. Selbstverständlich könnt Ihr euch an der Weiterentwicklung des Scripts beteiligen und entsprechend Push-Requests senden. 


--
##### Lizenz-Information:

Copyright Jens Dutzi 2015-2017 / Stand: 21.03.2017 / Dieses Werk ist lizenziert unter einer [MIT Lizenz](http://opensource.org/licenses/mit-license.php)

