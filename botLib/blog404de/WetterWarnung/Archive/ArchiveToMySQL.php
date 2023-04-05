<?php

declare(strict_types=1);

/*
 *  WarnParser für neuthardwetter.de by Jens Dutzi
 *
 *  @package    blog404de\WetterWarnung
 *  @author     Jens Dutzi <jens.dutzi@tf-network.de>
 *  @copyright  Copyright (c) 2012-2020 Jens Dutzi (http://www.neuthardwetter.de)
 *  @license    https://github.com/Blog404DE/WetterwarnungDownloader/blob/master/LICENSE.md
 *  @version    v3.2.0
 *  @link       https://github.com/Blog404DE/WetterwarnungDownloader
 */

namespace blog404de\WetterWarnung\Archive;

use Exception;
use PDO;
use RuntimeException;

/**
 * Klasse für die Archiv-Anbindung des Warnparsers
 * unter Verwendung einer MySQL-Datenbank.
 */
class ArchiveToMySQL implements ArchiveToInterface {
    /** @var PDO MySQL Connection ResourceID */
    private PDO $connectionId;

    /** @var array MySQL-Konfiguration */
    private array $config = [];

    /**
     * Setter für MySQL Zugangsdaten.
     *
     * @param array $config Konfigurations-Array
     *
     * @throws Exception
     */
    public function setConfig(array $config): void {
        // MySQL Support vorhanden?
        if (!\extension_loaded('pdo_mysql')) {
            throw new RuntimeException('Für die Archiv-Funktion wird das mysqli-Modul in PHP benötigt');
        }

        // Alle Paramter verfügbar?
        if (!\array_key_exists('host', $config)) {
            throw new RuntimeException(
                'Der Konfigurationsparamter ["ArchiveConfig"]["host"] wurde nicht gesetzt.'
            );
        }
        if (!\array_key_exists('username', $config)) {
            throw new RuntimeException(
                'Der Konfigurationsparamter ["ArchiveConfig"]["username"] wurde nicht gesetzt.'
            );
        }
        if (!\array_key_exists('password', $config)) {
            throw new RuntimeException(
                'Der Konfigurationsparamter ["ArchiveConfig"]["username"] wurde nicht gesetzt.'
            );
        }
        if (!\array_key_exists('database', $config)) {
            throw new RuntimeException('Der Konfigurationsparamter ["ArchiveConfig"]["host"] wurde nicht gesetzt.');
        }

        // Verbindung aufbauen zur MySQL Datenbank
        $dsn = 'mysql:dbname=' . $config['database'] . ';host=' . $config['host'];
        $this->connectionId = new PDO($dsn, $config['username'], $config['password']);
        $this->connectionId->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Werte setzen
        $this->config = $config;
    }

    /**
     * Getter-Methode für das Konfigurations-Array.
     *
     * @return array Inhalt der WetterWarnung
     */
    public function getConfig(): array {
        return $this->config;
    }

    /**
     * Speichere Wetterwarnung in Archiv.
     *
     * @param array $parsedWarnInfo Inhalt der WetterWarnung
     *
     * @throws Exception
     */
    public function saveToArchive(array $parsedWarnInfo): void {
        if ($this->getConfig()) {
            // Status-Ausgabe der aktuellen Wetterwarnung
            echo "\t* Wetterwarnung über " . $parsedWarnInfo['event'] . ' für ' .
                $parsedWarnInfo['area'] . ' -> ';

            // Prüfe, ob die bereits in der Datenbank ist
            $sth = $this->connectionId->prepare(
                'SELECT count(id) FROM warnarchiv WHERE identifier = :identifier LIMIT 1',
                [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY]
            );
            $sth->bindParam(':identifier', $parsedWarnInfo['identifier'], PDO::PARAM_STR, 32);
            if (!$sth->execute()) {
                throw new RuntimeException(
                    'Fehler beim prüfen ob Warnung bereits im Archiv ist: ' . $this->connectionId->errorInfo()[2]
                );
            }

            // Prüfe ob Warnung bereits verarbeitet wurde
            if (0 === $sth->fetchColumn()) {
                // Wetterwarnung ist noch nicht im Archiv

                // Start-/Enddatum verarbeiten
                $startZeit = unserialize($parsedWarnInfo['startzeit'], ['allowed_classes' => true])->getTimestamp();
                $endzeit = unserialize($parsedWarnInfo['endzeit'], ['allowed_classes' => true])->getTimestamp();

                // Eingabe speichern
                $sql = 'INSERT INTO warnarchiv
                               (`datum`, `hash`, `identifier`,  `reference`, `event`, `msgType`, `startzeit`, `endzeit`,
                                `severity`, `warnstufe`, `urgency`, `headline`, `description`, `instruction`, `sender`,
                                `web`, `warncellid`, `area`, `stateLong`, `stateShort`, `altitude`, `ceiling`,
                                `hoehenangabe`)
                                VALUES (
                                    NOW(), :hash, :identifier, :reference, :event, :msgType, FROM_UNIXTIME(:startzeit),
                                    FROM_UNIXTIME(:endzeit), :severity, :warnstufe, :urgency, :headline, :description,
                                    :instruction, :sender, :web, :warncellid, :area, :stateLong, :stateShort,
                                    :altitude, :ceiling, :hoehenangabe
                                )';
                $sth = $this->connectionId->prepare($sql, [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY]);

                // Parameter binden
                $sth->bindParam(':hash', $parsedWarnInfo['hash'], PDO::PARAM_STR, 32);
                $sth->bindParam(':identifier', $parsedWarnInfo['identifier'], PDO::PARAM_STR, 1024);
                $sth->bindParam(':reference', $parsedWarnInfo['reference'], PDO::PARAM_STR, 1024);
                $sth->bindParam(':event', $parsedWarnInfo['event'], PDO::PARAM_STR, 255);
                $sth->bindParam(':msgType', $parsedWarnInfo['msgType'], PDO::PARAM_STR, 32);
                $sth->bindParam(':startzeit', $startZeit, PDO::PARAM_STR, 255);
                $sth->bindParam(':endzeit', $endzeit, PDO::PARAM_STR, 255);
                $sth->bindParam(':severity', $parsedWarnInfo['severity'], PDO::PARAM_STR, 255);
                $sth->bindParam(':warnstufe', $parsedWarnInfo['warnstufe'], PDO::PARAM_INT);
                $sth->bindParam(':urgency', $parsedWarnInfo['urgency'], PDO::PARAM_STR, 255);
                $sth->bindParam(':headline', $parsedWarnInfo['headline'], PDO::PARAM_STR, 1024);
                $sth->bindParam(':description', $parsedWarnInfo['description']);
                $sth->bindParam(':instruction', $parsedWarnInfo['instruction']);
                $sth->bindParam(':sender', $parsedWarnInfo['sender'], PDO::PARAM_STR, 255);
                $sth->bindParam(':web', $parsedWarnInfo['web'], PDO::PARAM_STR, 255);
                $sth->bindParam(':warncellid', $parsedWarnInfo['warncellid'], PDO::PARAM_INT);
                $sth->bindParam(':area', $parsedWarnInfo['area'], PDO::PARAM_STR, 255);
                $sth->bindParam(':stateLong', $parsedWarnInfo['stateLong'], PDO::PARAM_STR, 255);
                $sth->bindParam(':stateShort', $parsedWarnInfo['stateShort'], PDO::PARAM_STR, 3);
                $sth->bindParam(':altitude', $parsedWarnInfo['altitude'], PDO::PARAM_INT);
                $sth->bindParam(':ceiling', $parsedWarnInfo['ceiling'], PDO::PARAM_INT);
                $sth->bindParam(':hoehenangabe', $parsedWarnInfo['hoehenangabe'], PDO::PARAM_STR, 255);

                // In Datenbank eintragen
                if (!$sth->execute()) {
                    throw new RuntimeException(
                        'Fehler beim prüfen ob Warnung bereits im Archiv ist: ' .
                        $this->connectionId->errorInfo()[2]
                    );
                }

                echo 'Archivieren erfolgreich' . PHP_EOL;
            } else {
                // Eintrag schon in der Datenbank
                echo 'Bereits im Archiv' . PHP_EOL;
            }
        } else {
            // Konfiguration ist nicht gsetzt
            throw new RuntimeException(
                'Die Archiv-Funktion wurde nicht erfolgreich konfiguriert'
            );
        }
    }
}
