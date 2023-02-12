![PHP CI](https://github.com/Blog404DE/WetterwarnungDownloader/workflows/PHP%20CI/badge.svg) [![GitHub release](https://img.shields.io/github/release/Blog404DE/WetterwarnungDownloader.svg?style=square)](https://github.com/Blog404DE/WetterwarnungDownloader) [![license](https://img.shields.io/github/license/Blog404DE/WetterwarnungDownloader.svg?style=square)](https://github.com/Blog404DE/WetterwarnungDownloader)
[![FOSSA Status](https://app.fossa.com/api/projects/git%2Bgithub.com%2FBlog404DE%2FWetterwarnungDownloader.svg?type=shield)](https://app.fossa.com/projects/git%2Bgithub.com%2FBlog404DE%2FWetterwarnungDownloader?ref=badge_shield)


# Wetterwarnung Downloader Script

## Einleitung

Bei dem Wetterwarnung-Downloader handelt es sich um ein Tool zum automatischen herunterladen aktueller Wetterwarnungen für eine bestimmte Warnregion. Die Wetterwarnungen werden im Rahmen der OpenData-Initiative des DWD bereitgestellt. Details hierzu finden sich auf der [NeuthardWetterScripts Hauptseite](https://github.com/Blog404DE/NeuthardWetter-Scripts).

Das Tool erlaubt hierbei nicht nur das speichern der aktuellen Gefahrenlage in eine Datei, sondern auch das anlegen eines Archivs mit den bisherigen Wettergefahren und das auslösen einer benutzerdefinierten Aktion (genannt "Action"), sofern sich die Gefahrenlage verändert hat. Das Tool unterstützt hierbei bereits das versenden der Wettergefahren an Twitter (veraltete) und IFTTT. Zusätzlich besteht auch die Möglichkeit komplett eigene Action-Klassen anhand der beiliegenden Beispiel-Implementierungen zu entwickeln.

Bitte beachtet: es handelt sich um eine erste Vorab-Version des Scripts. Auch wenn das Script ausführlich getestet wurde, so kann niemand garantieren, dass keine Fehler oder Probleme auftreten.

## Anleitung zur Einrichtung des Wetterwarnung-Downloader

### Vorraussetzungen:

- Linux oder macOS (unter Debian und macOS 10.13.2 getestet)
- PHP 8.0 (oder neuer - inkl. PHP 8.2)
- Folgende PHP Module werden benötigt: simplexml, json, ftp, pdo, zip, libxml, curl
- (optional) MySQL-Datenbank
- wget

> **Hinweis:** PHP 7.4 wird mit dieser Version nicht mehr unterstützt. PHP 7.4 ist EOL und wird von den Entwicklern nicht mehr unterstützt (auch keine Sicherheitsupdates). Dies bedeutet nicht, dass PHP 7.4 nicht mit diesem Projekt funktionieren wird, sondern, dass wir nicht mehr gegen PHP 7.4 prüfen.

## Installationsanleitung:

Eine Anleitung zur Installation des Scripts findet sich im Wiki für dieses GitHub Projekts:
[https://github.com/Blog404DE/WetterwarnungDownloader/wiki](https://github.com/Blog404DE/WetterwarnungDownloader/wiki)

## Twitter-Support veraltet:

Aufgrund der Änderungen bei Twitter im Zusammenhang mit der Twitter API habe ich mich entschieden die "Twitter-Action" als veraltete zu markieren. Das bedeutet, Twitter wird aktuell noch unterstützt, aber in es wird keine Änderungen mehr geben und bei Änderungen der Twitter API, wird diese "Action" endgültig entfernt werden.

## Abschluss

Solltet Ihr Fragen oder Anregungen haben, so steht euch offen sich jederzeit an mich per E-Mail (siehe http://www.NeuthardWetter.de) zu wenden. Selbstverständlich könnt Ihr euch an der Weiterentwicklung des Scripts beteiligen und entsprechend Push-Requests senden.


--
##### Lizenz-Information:

Copyright Jens Dutzi 2015-2023 / Stand: 11.02.2023 / Dieses Werk ist lizenziert unter einer [MIT Lizenz](http://opensource.org/licenses/mit-license.php)


## License
[![FOSSA Status](https://app.fossa.com/api/projects/git%2Bgithub.com%2FBlog404DE%2FWetterwarnungDownloader.svg?type=large)](https://app.fossa.com/projects/git%2Bgithub.com%2FBlog404DE%2FWetterwarnungDownloader?ref=badge_large)
