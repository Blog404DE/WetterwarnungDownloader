[![Build Status](https://travis-ci.org/Blog404DE/WetterwarnungDownloader.svg?branch=develop)](https://travis-ci.org/Blog404DE/WetterwarnungDownloader)

# Wetterwarnung Downloader Script

> **Wichtiger Hinweis:**
>
> Aufgrund der Überarbeitung zur Version 3.0 des WetterwarnungDownloader und des deutlich gestiegenen Programmumfangs wurde es notwendig, die Konfigurationsdatei deutlich zu erweitern. Teilweise waren auch eine Änderungen an den bestehenden Konfigurationsparameter notwendig, sodass eine Rückwärts-Kompatiblität nicht durchgehend besteht.
>
> Daher empfehlen wir die Konfigurationsdatei nach dem Upgrade anhand der Vorlage ```config.sample.php``` entsprechend anzupassen.


## Einleitung

Bei dem Wetterwarnung-Downloader handelt es sich um ein Tool zum automatischen herunterladen aktueller Wetterwarnungen für eine bestimmte Warnregion. Die Wetterwarnungen werden im Rahmen der OpenData-Initiative des DWD bereitgestellt. Details hierzu finden sich auf der [NeuthardWetterScripts Hauptseite](https://github.com/Blog404DE/NeuthardWetter-Scripts).

Das Tool erlaubt hierbei nicht nur das speichern der aktuellen Gefahrenlage in eine Datei, sondern auch das anlegen eines Archivs mit den bisherigen Wettergefahren und das auslösen einer benutzerdefinierten Aktion (genannt "Action"), sofern sich die Gefahrenlage verändert hat. Das Tool unterstützt hierbei bereits das versenden der Wettergefahren an Twitter und IFTTT. Zusätzlich besteht auch die Möglichkeit komplett eigene Action-Klassen anhand der beiliegenden Beispiel-Implementierungen zu entwickeln.

Bitte beachtet: es handelt sich um eine erste Vorab-Version des Scripts. Auch wenn das Script ausführlich getestet wurde, so kann niemand garantieren, dass keine Fehler oder Probleme auftreten.

## Anleitung zur Einrichtung des Wetterwarnung-Downloader

### Vorraussetzungen:

- Linux oder macOS (unter Debian und macOS 10.13.2 getestet)
- PHP 7.0.0 (oder neuer) inklusive ftp-, mhash, mbstring *(neu)* und zip-Modul aktiv
- (optional) MySQL-Datenbank
- wget

### Vorbereitung:

1. Download des Script-Pakets [https://github.com/Blog404DE/WetterwarnungDownloader/archive/master.zip](https://github.com/Blog404DE/WetterwarnungDownloader/archive/master.zip) und entpacken der ZIP Datei mittels unzip.

2. (optional) Sofern man das Twitter Action-Modul verwenden möchte, wird automatisch eine zusätzliche externe Library für die Twitter-Unterstützung benötigt. Diese Library kann einfach über  *composer* nachgeladen können.

	Falls *composer* noch nicht auf Ihrem System installiert ist, installieren Sie es bitte gemäß der Anleitung unter https://getcomposer.org/download/. Im Anschluss können die Library automatisch geladen werden mittels:

	```sh
	php composer.phar update --no-dev
	```

	Hinweis: Composer kann und solle (wie auch dieses Script selber) nicht mit Root-Rechten ausgeführt werden

3. (optional) Download der aktuellsten Liste mit den Warngebieten (WarnCellID) des Deutschen Wetterdienstes
	Um die WarnCellID Liste zusammen mit der Entwickler-Dokumentation zu erhalten ist es notwendig
	das ShellScript *fetchManual.sh* einmalig auszuführen.

	```sh
	cd docs
	./fetchManual.sh
	```

	Nach dem ausführen des Scripts befindet sich zusätzlich eine dbf-Datei (dBase), sowie mehrere PDF- und eine XLS-Datei im aktuellen Verzeichnis. Es handelt sich dabei um verschiedene Anleitungen und eine Liste der Warn-Regionen des DWD.

### WarnCell ID und das dazugehörige Bundesland ermitteln ermitteln

Wichtig für das Script ist zu ermitteln, für welche Kennung die Warnregion besitzt, für welche man die Wetterwarnungen ermitteln möchte. Eine Liste mit Ortschaften befindet sich bereits in [./docs/DWD-COMMUNITY.csv](docs/DWD-COMMUNITY.csv). Die CSV Datei können Sie mittels [LibreOffice](https://de.libreoffice.org/) (empfohlen) oder jedem Texteditor öffnen. Alternativ können Sie sich die Datei auch [hier](docs/DWD-COMMUNITY.csv) in GitHub darstellen lassen.

Sollten Sie im vorherigen Schritt eine neue Version der warnCellID-Datenbank heruntergeladen haben, so können Sie diese neue dBase-Datei ```./docs/DWD-COMMUNITY.dbf``` für die Suche verwenden. Die dBase-Datei kann ebenfalls mittels [LibreOffice](https://de.libreoffice.org/) geöffnet werden.

In beiden Fällen erhalten Sie eine Liste, in der Sie nach Ihrer Gemeinde/Stadt oder Regierungsbezirk suchen können. In der Spalte ```NAME``` befindet sich die Kurzform und in ```TYPED_NAME``` wiederum der voll ausgeschriebene Namen. In der Spalte ```STATE``` wiederum befindet sich die Kurzform des Bundeslandes, in dem sich die Gemeinde/Stadt bzw. der Regierungsbezirk befindet. In der nachfolgenden Tabelle findet sich die jeweils ausgeschriebene Name des Bundeslandes.

| Kürzel	| Bundesland   				  |
| --------- | --------------------------- |
| BB		| Brandenburg				  |
| BL		| Berlin					  |
| BW		| Baden-Württemberg			  |
| BY		| Bayern					  |
| HB		| Bremen					  |
| HE		| Hessen					  |
| HH		| Hamburg					  |
| MV		| Mecklenburg-Vorpommern      |
| NR		| Nordrhein-Westfalen		  |
| NS		| Niedersachsen			      |
| RP		| Rheinland-Pfalz			  |
| SA		| Sachsen-Anhalt			  |
| SH		| Schleswig-Holstein		  |
| SL		| Saarland					  |
| SN		| Sachsen					  |
| TH		| Thüringen					  |
| DE		| Bundesrepublik Deutschland  |

Um die ```WARNCELLID``` zu finden, schaut man in der Spalte ```NAME``` bzw. ```TYPED_NAME``` nach dem Ort oder Region, für welchen man die Wettergefahren
gemeldet bekommen möchte. Bitte beachtet, dass mehrere Ortschaftsnamen (z.B. Forst) in verschiedenen Bundesländern existiert. Daher sollte man unbedingt sicherstellen, den richtigen Eintrag wählen.

Für die Konfigurationsdatei wird im Anschluss der Wert aus der Spalte ```WARNCELLID``` sowie das Länderkürzel aus ```STATE``` benötigt.

### Konfiguration des Script zum abrufen der Wetter-Warnungen

Bei dem eigentlichen Script zum abrufen der Wetter-Warnungen handelt es sich um die Datei ```getWetterWarnung.php```. Das Script selber wird gesteuert über die ```config.local.php``` Datei. Um diese Datei anzulegen, kopieren Sie bitte [config.sample.php](config.sample.php) und nennen die neue Datei ```config.local.php```.

#### Pflicht-Angaben in der Konfigurationsdatei:

1. **(ÄNDERUNG)** Der erste Konfigurationsparameter steuert den Zugriff auf den DWD OpenData Server. Standardmäßig wird eine aktive FTP Verbindung aufgebaut. Sollte die Verbindungsaufbau oder der Download der Warndatei fehlschlagen bzw. aufhängen, kann eventuell das aktivieren des Passiv-Modus notwendig sein.

	```php
	// Für passive FTP Verbindung aktivieren (falls FTP Transfer fehlschlägt)
	$unwetterConfig["ftpmode"]["passiv"] = true;
	```

2. **(ÄNDERUNG)** Der folgende Parameter ist die Angabe für die Gemeinde bzw. Stadt oder Landkreis, für welche die Wetterwarnungen ermittelt werden sollen.

	Die ```warnCellId``` ist dabei die Nummer, welche im vorherigen Schritt ermittelt wurde. Der 	```stateShort``` beinhaltet den ebenfalls ermittelten Bundesland-Code.

	```php
		// Array mit den zu verarbeiteten Landkreisen
		// - siehe Erklärung in: README.md
		// - Beispiel: Stadt Karlsruhe (WarnCellID 808212000)
		$unwetterConfig["WarnCells"] = [
			[ "warnCellId" => 808212000, "stateShort" => "BW" ]
		];
	```

3. Speicherpfad für die JSON Datei mit den Wetterwarnung-Daten:

	```php
		// Speicherpfad für JSON Datei mit den aktuellen Wetterwarnungen
		$unwetterConfig["localJsonWarnfile"]= "/pfad/zur/wetterwarnung.json";
	```

	Der angegebene Pfad sollte - je nach Art der Verwendung der Wetterwarnungen - innerhalb eines vom Webserver zugänglichen Ordner sich befinden.

4. Speicherpfade für die vom DWD Server heruntergeladenen Wetterwarnung-Dateien:

	```php
		// Speicherordner für die Orginal Wetterwarnungen vom DWD
		$unwetterConfig["localFolder"] = "/pfad/zum/speicherordner/fuer/wetterWarnungen";
	```

	Bei *localFolder* handelt es sich um den Pfad, der die aktuellste Datei mit Wetterwarnungen beinhaltet, welche vom DWD FTP Server heruntergeladen wurde. Die alten Wetterwarnungen werden automatisch durch das Script in diesem gelöscht.

> **Hinweis:** Die bei 3 und 4 angegebenen Speicher-Pfade müssen auf Ihrem System bereits existieren, da es ansonsten zu einer Fehlermeldung beim ausführen des Scripts kommt.

#### Konfiguration der *optionalen* Logging/Error-Reporting Funktion:

Das Wetterwarnung Download-Script bietet die Möglichkeit im Fehlerfall den Benutzer zu informieren. Hierzu steht das Logging in eine Textdatei als auch das versenden einer E-Mail zur Wahl.

1. Logging in eine Text-Datei:
	Hierfür kommentieren Sie den folgenden Bereich in der Konfigurationsdatei aus und geben eine Log-Datei mit Pfad an

	```php
		// Fehler in Log-Datei schreiben
		$optFehlerLogfile    = [ "filename" => "/pfad/zur/log/error_log" ];
	```

2. Im Fehler-Fall den Nutzer per E-Mail informieren
	Als zweite Option besteht die Möglichkeit, sich per E-Mail informieren zu lassen, sobald der Ablauf des Scripts gestört ist. Es gilt zu beachten, dass dies zu vielen E-Mails führen kann. Zusätzlich muss PHP in der Lage sein E-Mails zu versenden.

	Um die Funktion zu aktivieren, müssen die folgenden Zeilen auskommentiert und ausgefüllt werden. Benötigt wird hierfür einmal eine E-Mail Adresse unter dessen Namen das Script seine E-Mails versendet und die E-Mail Adresse des Empfängers der Fehler E-Mails.

	```php
		// Fehler per E-Mail melden
		$optFehlerMail       = [ "empfaenger" => "deine.email@example.org",
		                         "absender" => "deine.email@example.org" ];
	```

#### *(NEU)* Konfiguration der *optionalen* MySQL-Archiv Funktion:

Dieses Script bietet die Möglichkeit alle Wetter-Warnungen in eine MySQL-Datenbank zu archivieren. Hierzu steht eine Archiv-Klasse mit dem Namen ["ArchiveToMySQL"](botLib/blog404de/WetterWarnung/Archive/ArchiveToMySQL.php) im Ordner ```./botLib/blog404de/WetterWarnung/Archive/``` zur Verfügung. Auf Wunsch kann auf Basis dieser Klasse auch eine eigene Implementierung verwendet werden. Hierfür kann unter Punkt 2. der Name *"ArchiveToMySQL"* gegen eine eigenen Klasse getauscht werden.

1. Mit dem folgenden Parameter kann die Archiv-Funktion aktiviert werden. Um die Archiv-Funktion zu aktivieren, ändern sie den Eintrag *false* in *true*.

	```php
        // Optional: Action-Module ür WetterWarnung aktivieren
        $unwetterConfig["Archive"] = false;
	```

2.		Sollten Sie die mitgelieferte Klasse zur Archivierung in eine MySQL Datenbank verwenden, müssen Sie ausschließlich die Zugangsdaten zum MySQL Server hinterlegen.

	```php
        // Optional: Konfiguration der Archiv-Klasse
        $unwetterConfig["ArchiveConfig"]["ArchiveToMySQL"] = [
            "host"      => "",
            "username"  => "",
            "password"  => "",
            "database"  => ""
        ];
	```

	Die angegebene Datenbank muss auf dem MySQL-Server bereits existieren. Mittels der SQL-Datei *doc/table.sql* kann im Anschluss die Datenbank-Tabelle angelegt werden.

	```bash
	mysql -h {hostname} -u {username} -p {datenbankname} < docs/table.sql
	```

	Selbstverständlich kann auch phpMyAdmin oder eine andere MySQL-Oberfläche hierfür verwendet werden.

#### *(NEU)* Konfiguration der *optionalen* Action -Funktion:

Der	 Wetterwarnung Downloader ist in der Lage bei einer Veränderung der Warnlage automatisch - wie eingangs beschrieben - eine oder mehrere Action auszulösen.

Als Beispiel-Implementierung liegt diesem Script eine [Twitter-](/botLib/blog404de/WetterWarnung/Action/SendToTwitter.php) und eine [IFTTT](/botLib/blog404de/WetterWarnung/Action/SendToITFFF.php) Unterstützung bei.

Um die Action-Unterstützung zu aktivieren, müssen Sie zuerst den nachfolgenden Konfigurationsparameter von *false* auf *true* ändern.

```php
	// Optinal: Action-Funktion für WetterWarnung aktivieren (beiliegend Twitter aka. SendToTwitter)
	$unwetterConfig["Action"] = true;
```

##### Action Modul *SendToTwitter*: Unterstützung zum senden von WetterGefahren an ein Twitter-Account

Um die Twitter-Unterstützung verwenden zu können, muss zuerst ein bei Twitter eine eigene sogenannte "App" angelegt werden um Zugriff auf die Twitter-API und mittels OAUTH Zugriff auf den eigenen Account zu bekommen.

1. Unter https://apps.twitter.com über *Sign in* mittels dem Twitter-Account anmelden, unter dem die Tweets versendet werden sollen.
2. Über *Create New App* liegen Sie nun eine neue App an und füllen Sie das Formular wie folgt aus:

	```
	| Wert          | Erklärung
	| ------------- | --------------------------------------------
	| Name          | Der Name Ihres Bots (frei wählbar)
	| Description   | Beschreibung der App-Funktion (frei wählbar)
	| Webseite      | Ihre Homepage
	| Callback URL  | Dieser Wert muss nicht ausgefüllt werden
	```

	Im Anschluss müssen Sie die Developer Agreement bestätigen und können danach über *Create your Twitter application* die eigene Twitter-Anwendung anlegen.
3. Um die benötigten Schlüssel zu erzeugen, klicken Sie im Tab-Menü auf *Keys and Access Tokens* und auf der nun angezeigten Seite auf *Create my access token*.

4. Im Anschluss bekommen Sie nun alle notwendigen Keys angezeigt.

	Benötigt werden unter Application Settings die *Consumer Key (API Key)* und *Consumer Secret (API Secret)* für den API Zugriff angezeigt.

	Unter Your Access Token finden sich mit *Access Token* und *Access Token Secret* wiederum die Schlüssel die Sie für den Zugriff auf Ihren Twitter-Account benötigt werden.

	***Halten Sie die Schlüssel auf jeden Fall geheim!***

Mit den Keys und Secrets können Sie nun die Twitter-Action in der ```config.local.php``` des Wetterwarnung Downloader konfigurieren. Hierfür sind folgende Konfigurationsparameter zuständig:

2.	Bei aktivierter Action-Funktion muss der Name der Action-Klasse und die für die Klasse benötigten Konfigrationsparameter angegeben werden. Die Twitter-Action Klasse befindet sich in [./botLib/blog404de/WetterWarnung/Action/SendToTwitter.php](/botLib/blog404de/WetterWarnung/Action/SendToTwitter.php) und kann als Referenz für eine eigene Implementierung verwendet werden.

3.	Bei Verwendung der Twitter-Action müssen Sie noch das Action entsprechend auskommentieren und konfigurieren. Hierfür sind folgende Konfigurationsparameter zuständig:

	```php
	$unwetterConfig["ActionConfig"]["SendToTwitter"] = [
	    "consumerKey"       => "FqfEXB8s71M3eqbA2G6ZHqpPN",
	    "consumerSecret"    => "Vnj58AjIBQpjonLl492YuHIYe7enhvwUM88olHNNGtRTiq4A6e",
	    "oauthToken"        => "476939176-KKeRHY3I6VsqLFGJlRl4VgavQhKBWjL7PN8LPyRX",
	    "oauthTokenSecret"  => "aDQPNlXaG4u0g9lP9cAj1ybjYu0RqHkNmcd455dmtBn5Y",
	    "TweetPlace"        => "12345 Musterstadt, Deutschland",
	    "MessagePrefix"       => "Vorsicht:",
	    "MessagePostfix"      => "Mustertext"
	];
    ```

	Der erste Parameter *["SendToTwitter"]* legt fest, dass Sie die SendToTwitter-Klasse verwenden möchten und die nachfolgenden Konfigurationsparameter für diese spezielle Klasse verwendet werden sollen.

	Den *consumerKey*, *consumerSecret* können Sie direkt vom vorherigen Schritt übernehmen. Bei *oauthToken* und *oauthTokenSecret* handelt es sich wiederum um die Access-Tokens aus dem vorherigen Schritt. Keine Angst, die hier beispielhaft angefügten Tokens sind nicht gültig ;-)

	Bei *TwitterPlace* handelt es sich um den Standort, der unterhalb jeder Twitter-Nachricht angezeigt werden soll. Damit dieser ermittelt werden kann, verwenden Sie bitte das hier beispielhaft angegebene Format, da hierüber die Twitter-interne PlaceID ermittelt wird und das ermitteln sonst unter umständen fehlschlägt oder ein falsches Ergebnis liefert.

	Über *MessagePrefix* und *MessagePostfix* können Sie angeben, welcher Text vor- und nach der Wetterwarnung erscheinen soll.

##### Action Modul *SendToTwitter*: Unterstützung zum senden von WetterGefahren an ein Twitter-Account

Beim zweiten Action-Modul handelt es sich um die Unterstützung des besonders unter den Makern bekannte Internetdienst [IFTTT](http://www.ifttt.com). Dieser Dienst bietet die Möglichkeit bestimmte benutzerdefinierte Aktionen auszuführen, nachdem diesem eine Wetterwarnung gemeldet wurde. Ausgelöst kann unter anderem eine Push-Nachricht (z.B. via [Pushover](https://www.pushover.net/) oder der Versand einer SMS via IFTTT direkt.

Um die IFTTT-Unterstützung nutzen zu können, müssen - ähnlich bei der Twitter-Unterstützung - einige Vorbereitungen getroffen werden. Hierzu gehört das anlegen eines Accounts als auch das einrichten eines "Applets". Die Schritte hierfür sind relativ einfach.

1. Zuerst müssen Sie sich auf [http://www.ifttt.com](http://www.ifttt.com) einen Account anlegen, indem Sie auf "Sign Up" klicken und die angezeigten Schritte durchführen. *Hinweis:* Sie müssen keinen der angebotenen Login-Provider verwenden. Etwas versteckt am Ende der angezeigten Liste findet sich "Or use your password to sign up or sign in".

2. Nach der erfolgreichen Anmeldung müssen Sie ein eigenes Applet anlegen über den Menüpunkt "My Applets" / "New Applet".

3. Auf der nachfolgenden Seite klicken Sie auf das blaue ```+this``` um das neue Applet zu konfigurieren.

4. Im Anschluss wird Ihnen ein Assistent angezeigt der mittels mehrere Schritte alle benötigten Parameter abfragt:

	1. Step 1: Nun suchen Sie über das Suchformular nach dem Service "Webhooks" und wählen dieses mit einem Klick darauf aus. Im Anschluss wird ihnen "Recieve a web request" als einziger Zur Auswahl stehender Trigger angezeigt.
	2. Step 2: Im Anschluss wird Ihnen der Trigger erklärt und es werden ein paar Konfigurationsparameter abfragt. Bei ```Event Name``` geben Sie einen Namen an, auf den die Aktion hören soll (Buchstaben/Zahlen/_ sind erlaubt / z.B. getWetterWarnung). Dieser Parameter wird später für die Konfiguration des WetterWarnung-Downloads benötigt.

5. Nachdem der Auslöser einer Aktion definiert ist, muss nun die Aktion selber ausgewählt werden. Hierzu klicken Sie auf das nun erscheinende "+that". In diesem Beispiel wird der Versand einer SMS gewählt, wobei Sie bei der ersten Nutzung der SMS-Unterstützung von IFTTT einmal Ihre SMS-Nummer registrieren müssen (ein Assistent für Sie automatisch durch die Registrierung/Bestätigung der SMS Nummer).

	1. Step 4: Wählen Sie die gewünschte Aktion (z.B. SMS) und bestätigen Sie die Wahl mit einem Klick auf den in in grün gehaltene Erklärungstext.
	2. Step 5: Nun konfigurieren Sie das Aussehen der SMS. Zuerst löschen Sie den vorgegebenen Text und mittels "Add ingredient" können Sie im Anschluss wählen, welcher Bestandteil der Benachrichtung im SMS-Text erscheinen soll.

		Zur Wahl stehen hierbei:

		*EventName:* Der zuvor gewählte EventName (weniger Interessant in einer SMS)
		*Value1:* Kurze Beschreibung der Warnung (identisch mit der Headline der Wetter-Warnung)
		*Value2:* Längere Informationen zur Warnung (Information zum Warngebiet, Art der Warnung und Start-/Endzeitpunkt der Warnung)

		Bitte beachten Sie, dass gerade bei einer SMS die erzeugten Texte länger als die 160 Zeichen sein können. Daher empfiehlt sich maximal *Value1* in Verbindung mit einer SMS zu verwenden. Alternativ können Sie auch anstatt SMS als Aktion auch ein Push-Dienst wie Pushover wählen, der keinerlei Längenbeschränkung beim Text besetzt.

	3. Step 6: Die angezeigte Darstellung des erstellten IFTTT-Ablaufs mit "Finish" bestätigen.

> Selbstverständlich können verschiedene Aktionen bei IFTTT ausgewählt werden. Abhängig von der gewählten Aktion, sind die Konfigurationsschritte etwas
> unterschiedlich. Wobei das Prinzip allerdings immer identisch ist - insbesondere bei der Wahl des Aussehens des Benachrichtungstextes.

6. Nachdem Sie bereits ein Applet angelegt haben, benötigen Sie noch ein Zugangs-Schlüssel um den angelegten Webhook ausführen zu können. Dieser dient dazu, dass Sie und nur Sie eine Nachricht absetzen können. Hierzu rufen Sie am einfachsten nochmals MyApplets auf und klicken auf das neu angelegte Applet. Etwas überhalb dem angezeigten Applet ist ein Link mit dem Namen [Webhook](https://ifttt.com/maker_webhooks), über den man die benötigten Informationen erhalten kann.

7. Im Anschluss werden alle Applets dargestellt, welche Webhooks als Auslöser haben. Hier klicken Sie bitte rechts/oben auf "Settings".

8. Auf der Settings-Seite bekommen Sie nun eine URL im Stil von  *https://maker.ifttt.com/use/J335GAd46fv40vxjnI5VW* angezeigt. Der letzte Teil nach dem "use/" ist der für die Konfigurationsdatei benötigte Zugriffs-Schlüssel.

***Halten Sie die Schlüssel auf jeden Fall geheim!***

Mit dem Zugriffs-Schlüssel und dem festgelegten Event-Name können Sie nun die IFTTT-Action in der ```config.local.php``` des Wetterwarnung Downloader konfigurieren. Hierfür sind folgende Konfigurationsparameter zuständig:

1.	Bei aktivierter Action-Funktion muss der Name der Action-Klasse und die für die Klasse benötigten Konfigurationsparameter angegeben werden. Die IFTTT-Action Klasse befindet sich in [./botLib/blog404de/WetterWarnung/Action/SendToIFTTT.php](/botLib/blog404de/WetterWarnung/Action/SendToIFTTT.php) und kann als Referenz für eine eigene Implementierung verwendet werden.

2.	Bei Verwendung der IFTTT-Action müssen Sie noch das Action entsprechend auskommentieren und konfigurieren. Hierfür sind folgende Konfigurationsparameter zuständig:

	```php
	$unwetterConfig["ActionConfig"]["SendToITFFF"] = [
		"apiKey"			=> "50f2c3cf1303548161efced97f3fdafd",
		"eventName"			=> "getWetterWarnung",
		"MessagePrefix"		=> "Vorsicht:",
		"MessagePostfix"	=> "Mustertext"
	];
    ```

	Der erste Parameter *["SendToITFFF"]* legt fest, dass Sie die SendToITFFF-Klasse verwenden möchten und die nachfolgenden Konfigurationsparameter für diese spezielle Klasse verwendet werden sollen.

	Den *apiKey* können Sie direkt vom vorherigen Schritt übernehmen. Es handelt sich hierbei um den im ersten Schritt ermittelten Zugriffs-Schlüssel. Keine Angst, die hier beispielhaft angefügten Tokens sind nicht gültig ;-)

	Bei *eventName* handelt es sich um den ebenfalls im letzten Schritt den für den WebHook zugeordneten Event-Namen.

	Über *MessagePrefix* und *MessagePostfix* können Sie angeben, welcher Text vor- und nach der Wetterwarnung erscheinen soll.

> **Noch ein abschließender Hinweis zu den Action-Klassen:**
>
> Das Script führt eine Action erst dann aus, sobald sich die Warnlage ändert. Hierzu werden die ermittelten Wetterwarnungen mit dem gespeicherten letzten Stand verglichen (über ein eindeutiger *Identifier* des DWD).
>
> Möchte man das ausführen der Action erzwingen, kann das Script mittels ```./getWetterWarnung.php -f``` aufgerufen werden.
> 
> Es besteht im übrigen die Möglichkeit, mehrere Action-Module zu verwenden. Hierzu muss einfach das Array ```$unwetterConfig["ActionConfig"]``` entsprechend um ein weiteren Eintrag erweitert werden, wobei der zusätzliche Eintrag den Namen der zu verwendeten Action-Klasse haben muss.

### Abschluss-Arbeiten: Das PHP-Script ausführbar machen und als Cronjob hinterlegen

1. Das konfigurierte Scripte startfähig machen

	```bash
	chmod +x getWetterWarnung.php
	```

2. Shell-Script für den Aufruf als Cronjob. Ein direkter Aufruf bietet sich nicht an, da es ansonsten zu parallelen Aufruf des Scripts kommen kann. Dies kann dabei zu unerwünschten Effekten führen bis zum kompletten hängen des Systems.

	Um dies zu verhindern bietet sich die Verwendung einer Lock-Datei an, wie in folgendem Beispiel exemplarisch gezeigt.

	Im Beispiel-Shell-Script müssen Sie noch den Pfad zur ```getWetterWarnung.php``` anpassen.

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
		/pfad/zum/script/getWetterWarnung.php
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
	```*/10 * * * * /pfad/zum/script/cron.WetterWarnung.sh >/dev/null```, wobei hier der Pfad zum Shell-Script aus Schritt 2 angepasst werden muss.

## Erklärung zur JSON Datei mit der Wetterwarnung

Das Script erzeugt eine JSON Datei mit, welche die Anzahl der Wetterwarnungen *(anzahl)* sowie die Informationen zu den entsprechenden Wetterwarnungen *(wetterwarnungen)* enthält. Hier zwei Beispiele für diese Datei (exemplarisch mit einer Meldung, wobei das Array mit den Wetterwarnungen durchaus mehrere Meldungen beinhalten kann sowie ein Beispiel einer Datei falls keine Wetterwarnung aktuell existiert):

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

⁴ Die Höhenangaben sind im Gegensatz zu der Original Wetterwarnung in Meter umgerechnet und können damit direkt verwendet werden. Zusätzliche Angaben zu den Höhenwerten findet sich ebenfalls in der [cap_dwd_profile_de_pdf.pdf](http://www.dwd.de/DE/leistungen/gds/help/warnungen/cap_dwd_profile_de_pdf.pdf?__blob=publicationFile).

⁵ Um z.B. unter PHP auf das serialisierte DateTime Objekt zugreifen zu können, muss es für die Verwendung erst mittels [unserialize](http://php.net/manual/de/function.unserialize.php) in ein DateTime-Objekt deserialisiert werden.
**Hinweis:** Ist *startzeit* identisch mit der *endzeit*, dann wurde keine Endzeit vom DWD für die Warnung angegeben und sollte daher bei einer Ausgabe ignoriert werden.

#### Beispiel zur Verwendung:

Vereinfachtes Beispiel für die Verwendung der gespeicherte JSON Daten und insbesondere des serialisierten DateTime-Objekts für den Start-/Endzeitpunkt der Wetterwarnungen:

```php
	// Wetterwarnungen einlesen (Todo: prüfen ob erfolgreich)
	$wetterwarnungen = json_decode( file_get_contents("warnungen.json"), true);
	
	// Alle Wetterwarnungen durchlaufen
	foreach ($wetterwarnungen["wetterwarnungen"] => $aktuelleWarnung ) {
		// Start-/Endzeit in DateTime-Objekte umwandeln
		$objStartzeit = unserialize($aktuelleWarnung["startzeit"]);
		$objEndzeit = unserialize($aktuelleWarnung["endzeit"]);
		
		// Test-Ausgabe
		echo("Wetterwarnung: " . $aktuelleWarnung["headline"]);
		echo("Beschreibung: " . $aktuelleWarnung["description"])
		echo("Startzeit: " . $objStartzeit->format("d.m.Y H:i:s"));
		echo("Endzeit: " . $objEndzeit->format("d.m.Y H:i:s"));
	}
```

## Abschluss

Solltet Ihr Fragen oder Anregungen haben, so steht euch offen sich jederzeit an mich per E-Mail (siehe http://www.NeuthardWetter.de) zu wenden. Selbstverständlich könnt Ihr euch an der Weiterentwicklung des Scripts beteiligen und entsprechend Push-Requests senden.


--
##### Lizenz-Information:

Copyright Jens Dutzi 2015-2018 / Stand: 28.01.2018 / Dieses Werk ist lizenziert unter einer [MIT Lizenz](http://opensource.org/licenses/mit-license.php)
