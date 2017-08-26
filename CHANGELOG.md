# Changelog

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

