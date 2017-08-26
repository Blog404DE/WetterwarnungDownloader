# Wetterwarnung Downloader Script

> **Wichtige Änderung:** Die Konfigurations-Datei der aktuellste Version des Wetterwarnung Downloader ist nicht kompatibel zur Konfigurations-Datei der bisherigen Version 2.0.1. Weitere Informationen hierzu finden Sie hier in der README.md.
>  
> Die Änderung ist in der Dokumentation fett markiert. 

## Einleitung

Bei dem Wetterwarnung-Downloader handelt es sich um ein Script zum automatischen herunterladen aktueller Wetterwarnungen für eine bestimmte Warnregion. Die Wetterwarnungen werden im Rahmen der OpenData-Initiative des DWD bereitgestellt. Details hierzu finden sich auf der [NeuthardWetterScripts Hauptseite](https://github.com/Blog404DE/NeuthardWetter-Scripts).

Bitte beachtet: es handelt sich um eine erste Vorab-Version des Scripts. Auch wenn das Script ausführlich getestet wurde, so kann niemand garantieren, dass keine Fehler oder Probleme auftreten. 

## Anleitung zur Einrichtung des Wetterwarnung-Downloader

### Vorraussetzungen:

- Linux oder OS X (unter Debian und OS X getestet)
- PHP 7.0.0 (oder neuer) inklusive ftp-, mhash und zip-Modul aktiv
- (optional) MySQL-Datenbank
- wget

### Vorbereitung:

1. Download des Script-Pakets [https://github.com/Blog404DE/WetterwarnungDownloader/archive/master.zip](https://github.com/Blog404DE/WetterwarnungDownloader/archive/master.zip) und entpacken der ZIP Datei mittels unzip.

2. Download der Liste mit den Warngebieten (WarnCellID) des Deutschen Wetterdienstes
	Um die WarnCellID Liste zusammen mit der Entwickler-Dokumentation zu erhalten ist es notwendig 
	das ShellScript *fetchManual.sh* einmalig auszuführen.  
	
	```sh
	cd docs
	./fetchManual.sh
	```
	
	Nach dem ausführen des Scripts befindet sich eine CSV-Datei, sowie mehrere PDF- und eine XLS-Datei im aktuellen Verzeichnis. Es handelt sich dabei um verschiedene Anleitungen und eine Liste der Warn-Regionen des DWD, welche später unter anderem für die Konfiguration notwendig sind. Zusätzlich sind diese Dokumente auch interessant für eventuelle Anpassungen des Scripts.

### WarnCell ID und das dazugehörige Bundesland ermitteln ermitteln

Wichtig für das Script ist zu ermitteln, für welche Kennung die Warnregion besitzt, für welche man die Wetterwarnungen ermitteln möchte. Eine Liste der Regionen findet sich in der im vorherigen Schritt heruntergeladenen ```cap_warncellids_csv.csv```. Die CSV Datei können Sie mittels 
[LibreOffice](https://de.libreoffice.org/) (empfohlen), [OpenOffice](https://www.openoffice.org/de/) oder jedem Texteditor öffnen. 

Um die ```WARNCELLID``` zu finden, schaut man in der Spalte ```NAME``` bzw. ```KURZNAME``` nach dem Ort, für welchen man die Wettergefahren 
gemeldet bekommen möchte. In der ersten Spalte findet man die zum Ort gehörende ```WARNCELLID```, welche in der Konfigurationsdatei benötigt wird.

**ÄNDERUNG:** Da die Wetterwarnungen keine Informationen mehr ausliefert über den Code (Kürzel)  des Bundeslandes in dem sich die ```WARNCELLID``` befindet, muss der Code für die nachfolgende Konfigurationsdatei selber bestimmt werden. 

Den Bundesland-Code können Sie anhand der folgenden Tabelle bestimmen:

| Bundesland        				| Kürzel	|
| ------------------------------------- | ------------- |
| Brandenburg					| BB		|
| Berlin							| BL		|
| Baden-Württemberg			| BW		|
| Bayern						| BY		|
| Bremen						| HB		|
| Hessen						| HE		|
| Hamburg						| HH		|
| Mecklenburg-Vorpommern	| MV		|
| Nordrhein-Westfalen			| NRW		|
| Niedersachsen				| NS		|	
| Rheinland-Pfalz				| RP		|
| Sachsen-Anhalt				| SA		|
| Schleswig-Holstein			| SH		|
| Saarland						| SL		|
| Sachsen						| SN		|
| Thüringen					| TH		|
| Bundesrepublik Deutschland| DE		|


### Konfiguration des Script zum abrufen der Wetter-Warnungen:

Bei dem eigentlichen Script zum abrufen der Wetter-Warnungen handelt es sich um die Datei ```WetterBot.php```. Das Script selber wird gesteuert über die ```config.local.php``` Datei. Um diese Datei anzulegen, kopieren Sie bitte ```config.sample.php``` und nennen die neue Datei ```config.local.php```.

Die anzupassenden Konfigurationsparameter in der *config.local.php* lauten wie folgt:

1. **(ÄNDERUNG)** Der erste Konfigurationsparameter steuert den Zugriff auf den DWD OpenData Server. Standardmäßig wird eine aktive FTP Verbindung aufgebaut. Sollte die Verbindungsaufbau oder der Download der Warndatei fehlschlagen bzw. aufhängen, kann eventuell das aktivieren des Passiv-Modus notwendig sein.
 
	```php
	// Für passive FTP Verbindung aktivieren (falls FTP Transfer fehlschlägt)
	$unwetterConfig["ftpmode"]["passiv"]		= false;
	```

2. Mit dem zweiten Konfigurationsparameter kann die Archiv-Funktion (speichern in einer MySQL Datenbank) aktiviert werden (true oder false). 

	```php
	$unwetterConfig["Archive"] 			= false;
	```
	Bei aktivierter Archiv-Funktion müssen die darauffolgenden MySQL Zugangsdaten sowie den Namen der Datenbank  hinterlegt werden.
	
	```php
	$unwetterConfig["MySQL"] = [
		"host"			=> "mysql.example",
		"username"		=> "testuser",
		"password"		=> "testpasswort",
		"database"		=> "testdatenbank"
	];
	```
	
	Die angegebene Datenbank muss auf dem MySQL-Server bereits existieren. Mittels folgendem SQL Query (auch zu finden unter *doc/table.sql*) wird das Archiv-Table angelegt.
	
	```sql
	DROP TABLE IF EXISTS `warnarchiv`;
	CREATE TABLE `warnarchiv` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`datum` datetime NOT NULL,
		`hash` varchar(32) NOT NULL,
		`identifier` varchar(1024) NOT NULL,
		`reference` varchar(1024) DEFAULT NULL,
		`msgType` varchar(32) NOT NULL,
		`event` varchar(255) NOT NULL,
		`startzeit` datetime NOT NULL,
		`endzeit` datetime NOT NULL,
		`severity` varchar(255) NOT NULL,
		`warnstufe` int(11) NOT NULL,
		`urgency` varchar(255) NOT NULL,
		`headline` varchar(1024) NOT NULL,
		`description` text NOT NULL,
		`instruction` text NOT NULL,
		`sender` varchar(255) NOT NULL,
		`web` varchar(255) NOT NULL,
		`warncellid` int(10) unsigned NOT NULL,
		`area` varchar(255) NOT NULL,
		`stateLong` varchar(255) NOT NULL,
		`stateShort` varchar(3) NOT NULL,
		`altitude` int(10) unsigned NOT NULL,
		`ceiling` int(10) unsigned NOT NULL,
		`hoehenangabe` varchar(255) NOT NULL,
		PRIMARY KEY (`id`),
		UNIQUE KEY `hash` (`hash`),
		KEY `datum` (`datum`)
	) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
	```
	
	Zum anlegen der Tabelle können Sie neben einem Verwaltungstool Ihrer Wahl (z.B. phpMyAdmin, MySQL Workbench) als  auch die Konsole Ihres Systems verwenden.

	```bash
	mysql -h {hostname} -u {username} -p {datenbankname} < doc/table.sql
	```
	
3. **(ÄNDERUNG)** Der folgende Parameter ist die Angabe für die Gemeinde bzw. Stadt oder Landkreis, für welche die Wetterwarnungen ermittelt werden sollen. 

	Die ```warnCellId``` ist dabei die Nummer, welche im vorherigen Schritt ermittelt wurde. Der 	```stateShort``` beinhaltet den ebenfalls ermittelten Bundesland-Code.

	```php
		$unwetterConfig["WarnCells"] = [
			[ "warnCellId" => 808212000, "stateShort" => "BW" ]
		];
	```

4. Speicherpfad für die JSON Datei mit den Wetterwarnung-Daten:

	```php
	$unwetterConfig["localJsonWarnfile"]   = "/pfad/zur/wetterwarnung.json";
	```
	
	Der angegebene Pfad sollte - je nach Art der Verwendung der Wetterwarnungen - innerhalb eines vom Webserver zugänglichen Ordner sich befinden.
	
5. Speicherpfade für die vom DWD Server heruntergeladenen Wetterwarnung-Dateien:

	```php
	$unwetterConfig["localFolder"]			= "/pfad/zum/speicherordner/fuer/wetterWarnungen";
	```
	
	Bei *localFolder* handelt es sich um den Pfad, der die aktuellste Datei mit Wetterwarnungen beinhaltet, welche vom DWD FTP Server heruntergeladen wurde. Die alten Wetterwarnungen werden automatisch durch das Script in diesem gelöscht.
	
**Hinweise:** Die bei 4 und 5 angegebenen Speicher-Pfade müssen auf Ihrem System bereits existieren, da es ansonsten zu einer Fehlermeldung beim ausführen des Scripts kommt. 

### Das PHP-Script ausführbar machen und als Cronjob hinterlegen

1. Das konfigurierte Scripte startfähig machen

	```bash
	chmod +x WetterBot.php
	```
	
2. Shell-Script für den Aufruf als Cronjob. Ein direkter Aufruf bietet sich nicht an, da es ansonsten zu parallelen Aufruf des Scripts kommen kann. Dies kann dabei zu unerwünschten Effekten führen bis zum kompletten hängen des Systems. 

	Um dies zu verhindern bietet sich die Verwendung einer Lock-Datei an, wie in folgendem Beispiel exemplarisch gezeigt.
	
	Im Beispiel-Shell-Script müssen Sie noch den Pfad zur ```WetterBot.php``` anpassen.
	
	```bash
	#!/bin/bash
	TMPDIR=$(dirname $(mktemp tmp.XXXXXXXXXX -ut))
	LOCKDIR=${TMPDIR}/$(whoami)_$(basename $0).lock
	
	function cleanup {
		if rm $LOCKDIR; then
			echo "Cronjob-Lauf beendet"
		else
			echo "Fehler beim entfernen der Lock-Datei '$LOCKDIR'"
			exit 1
		fi
	}
	
	if touch $LOCKDIR; then
		trap "cleanup" EXIT
	
		echo "Lock erhalten, starte Cronjob"
		/pfad/zum/script/WetterBot.php
	else
		echo "Lock-Datei konnte nicht angelegt werden '$LOCKDIR'"
		exit 1
	fi
	```
	
3. Cronjob anlegen 

	```sh
	crontab -e
	```
	
	Als Update-Frequenz hat sich alle 10 bis 14 Minuten herausgestellt, auch wenn der DWD öfters die Wetterdaten aktualisiert (z.T. minütlich). Für ein ausführen des Cronjob alle 15 Minuten würde die Cronjob-Zeile wie folgt aussehen:
	```*/10 * * * * /pfad/zum/script/cron.WetterBot.sh >/dev/null```, wobei hier der Pfad zum Shell-Script aus Schritt 2 angepasst werden muss.
	
#### Loggen der Ausgabe und Fehler-Handling:

Es besteht die Möglichkeit die Ausgabe des Scripts in Log-Dateien aufzeichnen zu lassen oder sich im Fehler-Fall automatisch per E-Mail 
informieren lassen.

Um dies zu aktivieren müssen Sie in der ```config.local.php``` folgende Parameter auskommentieren: 


1. Fehler in eine Log-Datei aufzeichnen:

	```php
	$optFehlerLogfile	= "/pfad/zur/log/error_log";						
	```
	**Wichtig**: Der Speicher-Pfad zur Log-Datei muss bereits existieren. Zusätzlich sollten Sie z.B. mittels Log-Rotate die Log-Datei automatisch rotieren lassen um ein übergroße Log-Datei zu vermeiden.
	
2. Fehler per E-Mail melden:	

	```php
	$optFehlerMail		= [ "empfaenger" => "deine.email@example.org", "absender" => "deine.email@example.org" ];		
	```
	
	Dieses Array beinhaltet die Absender- und Empfänger-Adresse die für den Versand von E-Mails im Fehlerfall verwendet werden. Die E-Mail Unterstützung sollte *ausschließlich* zu *Test-Zwecken* aktiviert werden, da durchaus viele E-Mails generiert werden können. 

	
## Erklärung zur JSON Datei mit der Wetterwarnung

Das Script erzeugt eine JSON Datei mit, welche die Anzahl der Wetterrwarnungen *(anzahl)* sowie die Informationen zu den entsprechenden Wetterwarnungen *(wetterwarnungen)* enthält. Hier zwei Beispiele für diese Datei (exemplarisch mit einer Meldung, wobei das Array mit den Wetterwarnungen durchaus mehrere Meldungen beinhalten kann sowie ein Beispiel einer Datei falls keine Wetterwarnung aktuell existiert):

```json
{
    "anzahl": 1,
    "wetterwarnungen": [
        {
            "identifier": "2.49.0.1.276.0.DWD.PVW.1499440080000.c71497d9-4e95-4bed-afee-fed7c37f3215",
            "reference": "",
            "msgType": "Alert",
            "event": "HITZE",
            "startzeit": "O:8:\"DateTime\":3:{s:4:\"date\";s:26:\"2017-07-07 11:00:00.000000\";s:13:\"timezone_type\";i:3;s:8:\"timezone\";s:13:\"Europe\/Berlin\";}",
            "endzeit": "O:8:\"DateTime\":3:{s:4:\"date\";s:26:\"2017-07-08 19:00:00.000000\";s:13:\"timezone_type\";i:3;s:8:\"timezone\";s:13:\"Europe\/Berlin\";}",
            "severity": "Wetterwarnung",
            "warnstufe": 1,
            "urgency": "Immediate",
            "headline": "Amtliche WARNUNG vor HITZE",
            "description": "Am Freitag wird bis zu einer H\u00f6he von 400m eine starke W\u00e4rmebelastung erwartet.\nAm Samstag wird eine starke W\u00e4rmebelastung erwartet.\n",
            "instruction": " An beiden Tagen ist mit einer zus\u00e4tzlichen Belastung aufgrund verringerter n\u00e4chtlicher Abk\u00fchlung insbesondere im dicht bebauten Stadtgebiet von Karlsruhe zu rechnen.Heute ist der 2. Tag der Warnsituation in Folge.\n",
            "sender": "Zentrum f\u00fcr Medizin-Meteorologische Forschung",
            "web": "http:\/\/www.wettergefahren.de",
            "warncellid": "808215103",
            "area": "Gemeinde Karlsdorf-Neuthard",
            "stateLong": "Baden-W\u00fcrtemberg",
            "stateShort": "BW",
            "altitude": 0,
            "ceiling": 599,
            "hoehenangabe": "H\u00f6henlagen unter 599m",
            "hash": "9714fc3d39b291b561e12ad3494d24e3"
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

### Erklärung der einzelnen Werte des Wetterwarnung-Datei

| Wert        	| Erklärung     |
| ------------- | ------------- |
| identifier			| Die Kennung des DWD der ausgegebenen Wetterwarnung ¹ |
| reference			| Bei Update: Identifier des ehemaligen Identifier ¹ |
| msgType			| Typ der Meldung *Alert* = Neue Meldung, *Update* = Aktualisierung |
| event				| Art des Wettereignisses in Kurzform ² |
| startzeit			| Beginn der Wetterwarnung als *serialisiertes DateTime* Objekt ⁵ |
| endzeit			| Ablauf-Datum der Wetterwarnung als *serialisiertes* DateTime Objekt ⁵ |
| severity			| Warnstufe der Meldung ¹ |
| warnstufe			| Die Warnstufe (0 = Vorabinformation bis 4 = Extreme Unwetterwarnung) ² |
| urgency			| Soll Wetterwarnung sofort ausgegeben werden oder in der Zukunft ³  |
| headline			| Überschrift der Wetterwarnung ² |
| description		| Beschreibungstext der Warnung bzw. Vorwarnung ² |
| instruction		| Zusatztext zur Warnung und optional ein Verlängerungshinweis sowie Ersetzungshinweis ² |
| sender			| Ausstellendes Rechenzentrum des DWD |
| web				| URL mit Webseite für weitere Informationen zur Warnung |
| warncellid		| Zur Warnung gehörende WarnCellID |
| area				| Bezeichnung der Region auf die die Wetterwarnung zutrifft |
| stateLong			| Ausgeschriebener Name des Bundeslandes in dem sich die Warnregion befindet |
| stateShort		| Abkürzung des Bundesland in dem die Warnregion sich befindet |
| altitude			| Unterer Wert des Höhenbereichs in **Meter** ⁴ |
| ceiling			| Oberer Wert Wert des Höhenbereichs in **Meter** ⁴ |
| hoehenangabe	| Höhenangabe in Text-Form (ebenfalls in Meter) ⁴ |
| hash				| Automatisch erzeugte einmalige Kennung der Meldung |

¹ Jede Ausswendung des DWD kann aus mehreren einzelnen Wetterwarnungen bestehen. Dadurch kann der gleiche Identifier für mehrere Meldungen gleichzeitig gültig sein. Im Falle eines Updates (siehe msgType) ist unter *reference* der Identifier der Meldungen hinterlegt, die durch das Update ersetzt werden

² Bei diesen Angaben handelt es sich die direkte unveränderte Übernahme aus der Wetterwarnung des DWD. Eine Erklärung der jeweiligen Werte findet sich in der unter *Vorbereitung* heruntergeladenen Datei [cap_dwd_profile_de_pdf.pdf](http://www.dwd.de/DE/leistungen/gds/help/warnungen/cap_dwd_profile_de_pdf.pdf?__blob=publicationFile).

³ Die Warnstufen lauten wie folgt: 0 = Vorwarnung, 1 = Wetterwarnung, 2 = Markante Wetterwarnung, 3 = Unwetterwarnung, 4 = Extreme Unwetterwarnung, -1 = Unbekannter Warntyp

⁴ Die Höhenangaben sind im Gegensatz zu der Orginal Wetterwarnung in Meter umgerechnet und können damit direkt verwendet werden. Zusätzliche Angaben zu den Höhenwerten findet sich ebenfalls in der [cap_dwd_profile_de_pdf.pdf](http://www.dwd.de/DE/leistungen/gds/help/warnungen/cap_dwd_profile_de_pdf.pdf?__blob=publicationFile).

⁵ Um z.B. unter PHP auf das serialisierte DateTime Objekt zugreifen zu können, muss es für die Verwendung erst mittels [unserialize](http://php.net/manual/de/function.unserialize.php) in ein DateTime-Objekt deserialisiert werden. 
**Hinweis:** Ist *startzeit* identisch mit der *endzeit*, dann wurde keine Endzeit vom DWD für die Warnung angegeben und sollte daher bei einer Ausgabe ignoriert werden.

## Abschluss

Solltet Ihr Fragen oder Anregungen haben, so steht euch offen sich jederzeit an mich per E-Mail (siehe http://www.NeuthardWetter.de) zu wenden. Selbstverständlich könnt Ihr euch an der Weiterentwicklung des Scripts beteiligen und entsprechend Push-Requests senden. 


--
##### Lizenz-Information:

Copyright Jens Dutzi 2015-2017 / Stand: 14.07.2017 / Dieses Werk ist lizenziert unter einer [MIT Lizenz](http://opensource.org/licenses/mit-license.php)