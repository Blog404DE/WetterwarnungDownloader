# Changelog

2020-03-01, 3.1.5 stable
----------
* Unterstützung für PHP 7.4 (Hinweis: unbedingt die Librarys via composer aktualisieren)
* (BUGFIX) versehentliche Debug-Ausgabe entfernt
* Code an diversen stellen optimiert
* Rückgabe-Parameter auf strict-types umgestellt

2019-12-31, 3.1.4 stable
----------
* Parser für die einzelnen Warnungen optimiert
* (BUGFIX) Potentielle Notices entfernt im "failsafe"-Mode bei der Erkennung der Warnmeldung-Dateinamen.

2019-12-31, 3.1.3 stable
----------

* (BUGFIX) Potentielle Notices entfernt im "failsafe"-Mode bei der Erkennung der Warnmeldung-Dateinamen.

2019-08-19, 3.1.2 stable
----------

* (BUGFIX) fetchManual.sh überarbeitet und auf neue Dateinamen angepasst

2019-08-18, 3.1.1 stable
----------

* (BUGFIX) Fehler in der composer.json korrigiert (require für PHP hinzugefügt)

2019-08-17, 3.1.0 stable
----------

* Vereinfachte Installation/Download des Scripts über composer
* Kompatiblität verbessert mit künftigen PHP Versionen
* (BUGFIX) ITFFF Unterstützung korrigiert (konnte zu Error 400 führen)
* (NEU) Aktivierung von strict_types=1
* Mehrere kleinere Korrekturen im zusammehang mit der strengen Typisierung

2018-03-26, 3.0.3 stable
----------

* (NEU) Unterstützung von zur Warnlage passenden Icons innerhalb der Action-Extensions (soweit vom Dienst unterstützt)
* (NEU) Direkte Unterstützung von PushOver via Action Extension ohne Umweg über IFFFT (inkl. Unterstützung der Warnlage-Icons als Attachments)
* (NEU) Konfigurationsoption für den Pfad mit den Icons hinzugefügt
* (BUGFIX) Kommandozeilen-Parameter für "force Update" wurde unter bestimmten Umständen nicht beachtet.
* (BUGFIX) Parsen des Start-/Endzeitpunkts der Warnung verbessert und an das neue CAP Profil angepasst.
* (NEU) API Dokumentation etwas überarbeitet

Die Warnlage-Icons werden vom [Deutschen Wetterdienst](https://www.dwd.de/DE/wetter/warnungen_aktuell/objekt_einbindung/piktogramm_node.html) bereitgestellt. Bitte beachten Sie für die Verwendung die beigefügten Lizenzbedinungen des Deutschen Wetterdienstes (siehe /icons/Quelle.md).

2018-02-02, 3.0.1 stable
----------

**Durch ein kompletter Rewrite des Programmcodes und der Implementierung vieler neuen Funktionen wurde es notwendig die Konfigurationsdateien zu überarbeiten bzw. zu erweitern.**

* (NEU) Anleitung auf das Github-Wiki für dieses Projekt umgestellt
* (NEU) Für einzelne Action-Module kann nun festgelegt werden, dass die erzwungene Ausführung ignoriert wird (interessant, sobald mehrere Action-Module aktiv sind (siehe README.md)
* (NEU) Weiterer Konfigurationsparameter *ignoreForceUpdate* für die Action-Module eingeführt.
* (BUGFIX) *SendToTwitter*-Klasse dahingehend korrigiert, dass sich der gemeldete Text bei jedem Tweet leicht ändert, da Twitter für 12h identische Tweets blockiert. Dies würde ein erzwungenes ausführen von *SendToTwitter* verhindern.
* (NEU) Kompletter Re-Write für eine deutlich größere Erweiterbarkeit des Scripts
* (NEU) Archiv-Erweiterung: Unterstützung der Archivierung vorhandener Wettergefahren in eine MySQL Datenbank
* (NEU) Action-Erweiterung 1: Bei einer Änderung der Warnwetter-Lage automatisch ein Tweet absetzen
* (NEU) Action-Erweiterung 2: Eine IFTTT-Aktion (http://www.ifttt.com) auslösen wie z.B. das versenden einer Push-Nachricht an eine Benutzergruppe oder das versenden einer SMS.
* (NEU) Möglichkeit die vorhandenen mittels "Action"- und "Archiv"-Klassen zu erweitern und mit neuen Funktionen versehen
* (NEU) Unterstützung mehrere Action-Klassen gleichzeitig ermöglicht
* (NEU) Deutlich verbesserte Fehlerbehandlung (z.B. Log-Datei anlegen oder per E-Mail informieren im Fehlerfall)
* (NEU) Script deutlich modularisierter erstellt und damit eine bessere Übersichtlichkeit gewährleistet über den Programmcode
* (NEU) Durchgehende Unterstützung des PSR-2 Coding-Standards
* (NEU) Integration von Travis-CI zur automatischen Prüfung des Programmcodes auf Fehler und Einhaltung diverser Coding-Standards
* (NEU) Verbessertes Ladeverhalten von neuen Warn-Dateien des DWD durch ein vom DWD gesetzten Symlink zur aktuellsten gültigen Warn-Datei (Falls Symlink fehlt erfolgt FallBack auf die bisherige Methode zum erkennen der aktuellsten Warn-Datei).

2018-01-04, 2.6.0 stable
----------
**Wichtig: Umstellung auf den frei zugänglichen OpenData Server des DWD. Dadurch sind Änderungen an der Konfigurationsdateien im Zusammenhang mit den FTP Daten notwendig.**

 * (NEU) Erste Stable Version mit Unterstützung des DWD OpenData Programm
 * (NEU) Anleitung erweitert mit Beispiel für das verarbeiten des DateTime-Felds
 * (NEU) Script zum herunterladen der DWD-Dokumentation und der WarnCellID-Datenbank verbessert


2017-08-26, 2.5.5 dev
----------
**Wichtig: Umstellung auf den frei zugänglichen OpenData Server des DWD. Dadurch sind Änderungen an der Konfigurationsdateien im Zusammenhang mit den FTP Daten notwendig.**

* (NEU) Verwendung des frei zugänglichen DWD OpenData Server. *Keine Anmeldung beim DWD mehr notwendig.*
* (NEU) Anpassung an der Logik zur Erkennung der neusten Wetterwarnung-Datei für ein schnelleres verarbeiten der Dateien (ermöglicht durch Änderung der Datei-Struktur auf dem DWD-Server)

2017-07-14, 2.5.3 dev
----------

**Wichtig: Anpassung an der Konfigurationsdatei beachten.**

- (BUGFIX) Zwingend notwendige Anpassung des Scripts an das CAP DWD Profil  v1.2

	-> Änderung zwingend notwendig, da neu als optional markierte Felder bereits nicht mehr durch den DWD ausgeliefert werden (z.B. Bundesland-Code)


2017-07-07, 2.5.1 dev
----------
**Wichtig: Umstellung die geänderten Warnprodukte (siehe Newsletter vom GDS-Newsletter 07.07.2017)**

- Neu: Optionales speichern der Wetterwarnungen in eine MySQL Datenbank
- Neu: Ausgabe beinhaltet Typ der Aussendung (Alert = Neue Meldung, Update = Aktualisierung einer bestehenden Meldung)
- Neu: Ausgabe beinhaltet den Identifier der Aussendung (bezieht sich auf eine Aussendung, nicht auf einzelne Wetterwarnungen und kann daher mehreren Meldungen zugeordnet sein)
- Neu: Ausgabe beinhaltet bei Update-Meldungen den Identifier der Meldung, die aktualisiert wurde
- Bugfix: Phrase für markantes Wetter korrigiert

2017-05-30, 2.0.2 stable
----------
- Bugfix: unter umständen wurde nicht die neuste Wetterwarnung verarbeitet

2017-03-24, 2.0.1 dev
----------
- Laden der Klassen etwas optimiert
- Code-Style auf PSR-0 geprüft und korrigiert
- Kleinere Bugfixes
- ErrorLog-Klasse ausgelagert in eigenständige Datei

2017-03-19, 2.0.0 dev
----------
- Unterstützung der genaueren Gemeinde-Wetterwarnungen (neu vom DWD - [Detail-Informationen](http://www.dwd.de/DE/wetter/warnungen_aktuell/neuerungen/gemeindewarnungen_node.html))
- Konfigurationsdatei vereinfacht *(install.md beachten!)*
- Suche nach einer einzelnen oder nach mehreren WarnCellID möglich
- Viele größere und kleinere Optimierungen für verbesserte Script-Laufzeit
- ***Kompletten Code-Rewrite***
- Diverse kleinere Bugfixes im Rahmen des Code-Rewrite
- Alle Bestandteile sind in eine Klasse ausgelagert
- Script ist nun auch innerhalb komplexerer Projekte nutzbar

**Geänderte Software-Vorraussetzungen und veränderte Konfigurationsdatei beachten:**

- PHP >=7.0.0
- ftp-, mhash und zip-Modul wird benötigt


2017-03-04, 1.5.1 stable
----------
- Erstes Release als eigenständiges Projekt

