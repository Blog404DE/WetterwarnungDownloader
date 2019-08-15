[![Travis branch](https://img.shields.io/travis/Blog404DE/WetterwarnungDownloader/master.svg?style=flat-square)](https://travis-ci.org/Blog404DE/WetterwarnungDownloader) [![GitHub release](https://img.shields.io/github/release/Blog404DE/WetterwarnungDownloader.svg?style=flat-square)](https://github.com/Blog404DE/WetterwarnungDownloader) [![license](https://img.shields.io/github/license/Blog404DE/WetterwarnungDownloader.svg?style=flat-square)](https://github.com/Blog404DE/WetterwarnungDownloader)


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
- PHP 7.0.0 (oder neuer) 
- Folgende PHP Module werden benötigt: simplexml, json, ftp, pdo, zip, libxml, curl
- (optional) MySQL-Datenbank
- wget

## Installationsanleitung:

Eine Anleitung zur Installation des Scripts findet sich im Wiki für dieses GitHub Projekts:
[https://github.com/Blog404DE/WetterwarnungDownloader/wiki](https://github.com/Blog404DE/WetterwarnungDownloader/wiki)


## Abschluss

Solltet Ihr Fragen oder Anregungen haben, so steht euch offen sich jederzeit an mich per E-Mail (siehe http://www.NeuthardWetter.de) zu wenden. Selbstverständlich könnt Ihr euch an der Weiterentwicklung des Scripts beteiligen und entsprechend Push-Requests senden.


--
##### Lizenz-Information:

Copyright Jens Dutzi 2015-2019 / Stand: 15.08.2019 / Dieses Werk ist lizenziert unter einer [MIT Lizenz](http://opensource.org/licenses/mit-license.php)
