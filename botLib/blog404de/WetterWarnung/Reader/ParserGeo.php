<?php

/** @noinspection PropertyCanBeStaticInspection */
declare(strict_types=1);

/*
 *  WarnParser für neuthardwetter.de by Jens Dutzi
 *
 *  @package    blog404de\WetterWarnung
 *  @author     Jens Dutzi <jens.dutzi@tf-network.de>
 *  @copyright  Copyright (c) 2012-2020 Jens Dutzi (http://www.neuthardwetter.de)
 *  @license    https://github.com/Blog404DE/WetterwarnungDownloader/blob/master/LICENSE.md
 *  @version    v3.3.1
 *  @link       https://github.com/Blog404DE/WetterwarnungDownloader
 */

namespace blog404de\WetterWarnung\Reader;

use Exception;
use RuntimeException;
use SimpleXMLElement;

/**
 * Traint zum Auslesen diverser Geo-Elemente aus der Roh-XML-Datei.
 */
trait ParserGeo {
    /**
     * @var array Array mit Bundesländern in Deutschland
     */
    private array $regionames = [
        'BB' => 'Brandenburg',
        'BL' => 'Berlin',
        'BW' => 'Baden-Württemberg',
        'BY' => 'Bayern',
        'HB' => 'Bremen',
        'HE' => 'Hessen',
        'HH' => 'Hamburg',
        'MV' => 'Mecklenburg-Vorpommern',
        'NR' => 'Nordrhein-Westfalen',
        'NS' => 'Niedersachsen',
        'RP' => 'Rheinland-Pfalz',
        'SA' => 'Sachsen-Anhalt',
        'SH' => 'Schleswig-Holstein',
        'SL' => 'Saarland',
        'SN' => 'Sachsen',
        'TH' => 'Thüringen',
        'DE' => 'Bundesrepublik Deutschland',
    ];

    /**
     * WarnCellID aus den Geo-Daten der Wetterwarnung auslesen.
     *
     * @param SimpleXMLElement $currentWarnGeo Geo-Teil der aktuellen Wetterwarnung
     *
     * @throws Exception
     */
    final protected function getRegioWarncellid(SimpleXMLElement $currentWarnGeo): string {
        // Prüfe, ob Feld in XML Datei existiert
        if (!property_exists($currentWarnGeo, 'warncellid')) {
            throw new RuntimeException(
                'Die aktuell verarbeitete Roh-Wetterwarnung fehlt die WarnCellID in den Region-Angaben.'
            );
        }

        return $currentWarnGeo->{'warncellid'}->__toString();
    }

    /**
     * areaDesc aus den Geo-Daten der Wetterwarnung auslesen.
     *
     * @param SimpleXMLElement $currentWarnGeo Geo-Teil der aktuellen Wetterwarnung
     *
     * @throws Exception
     */
    final protected function getRegionArea(SimpleXMLElement $currentWarnGeo): string {
        // Prüfe, ob Feld in XML Datei existiert
        if (!property_exists($currentWarnGeo, 'areaDesc')) {
            throw new RuntimeException(
                'Die aktuell verarbeitete Roh-Wetterwarnung fehlt der Ortsname in den Region-Angaben.'
            );
        }

        return $currentWarnGeo->{'areaDesc'}->__toString();
    }

    /**
     * State (Kurzform) aus den Geo-Daten der Wetterwarnung auslesen.
     *
     * @param SimpleXMLElement $currentWarnGeo Geo-Teil der aktuellen Wetterwarnung
     *
     * @throws Exception
     */
    final protected function getRegionStateShort(SimpleXMLElement $currentWarnGeo): string {
        // Prüfe, ob Feld in XML Datei existiert
        if (!property_exists($currentWarnGeo, 'state')) {
            throw new RuntimeException(
                "Die aktuell verarbeitete Roh-Wetterwarnung fehlt 'State' in den Region-Angaben."
            );
        }

        return $currentWarnGeo->{'state'}->__toString();
    }

    /**
     * State (Langform) aus den Geo-Daten der Wetterwarnung auslesen.
     *
     * @param SimpleXMLElement $currentWarnGeo Geo-Teil der aktuellen Wetterwarnung
     *
     * @throws Exception
     */
    final protected function getRegionStateLong(SimpleXMLElement $currentWarnGeo): string {
        // Prüfe, ob Feld in XML Datei existiert
        if (!property_exists($currentWarnGeo, 'state')) {
            throw new RuntimeException(
                "Die aktuell verarbeitete Roh-Wetterwarnung fehlt 'State' in den Region-Angaben."
            );
        }

        // Ermittle Event-Typ
        if (!\array_key_exists((string)$currentWarnGeo->{'state'}, $this->regionames)) {
            throw new RuntimeException(
                'Die Abkürzung des Bundeslandes ' . $currentWarnGeo->{'state'} . ' kann keinem ' .
                'deutschen Bundesland zugeordnet werden durch das Wetterwarnung-Script'
            );
        }

        return (string)$this->regionames[$currentWarnGeo->{'state'}->__toString()];
    }

    /**
     * altitude aus den Geo-Daten der Wetterwarnung auslesen.
     *
     * @param SimpleXMLElement $currentWarnGeo Geo-Teil der aktuellen Wetterwarnung
     * @param bool             $metric         Wert in metrischen Metern zurückgeben
     *
     * @throws Exception
     */
    final protected function getRegionAltitude(SimpleXMLElement $currentWarnGeo, bool $metric): float {
        // Prüfe, ob Feld in XML Datei existiert
        if (!property_exists($currentWarnGeo, 'altitude')) {
            throw new RuntimeException(
                "Die aktuell verarbeitete Roh-Wetterwarnung fehlt 'altitude' in den Region-Angaben."
            );
        }

        if ($metric) {
            $altitude = floor((int)$currentWarnGeo->{'altitude'}->__toString() * 0.3048);
        } else {
            $altitude = floor((int)$currentWarnGeo->{'altitude'}->__toString());
        }

        return $altitude;
    }

    /**
     * ceiling aus den Geo-Daten der Wetterwarnung auslesen.
     *
     * @param SimpleXMLElement $currentWarnGeo Geo-Teil der aktuellen Wetterwarnung
     * @param bool             $metric         Wert in metrischen Metern zurückgeben
     *
     * @throws Exception
     */
    final protected function getRegionCeiling(SimpleXMLElement $currentWarnGeo, bool $metric): float {
        // Prüfe, ob Feld in XML Datei existiert
        if (!property_exists($currentWarnGeo, 'ceiling')) {
            throw new RuntimeException(
                "Die aktuell verarbeitete Roh-Wetterwarnung fehlt 'ceiling' in den Region-Angaben."
            );
        }

        if ($metric) {
            $ceiling = floor((float)$currentWarnGeo->{'ceiling'}->__toString() * 0.3048);
        } else {
            $ceiling = floor((float)$currentWarnGeo->{'ceiling'}->__toString());
        }

        return $ceiling;
    }

    /**
     * Höehenangabe aus den Geo-Daten der Wetterwarnung ermitteln.
     *
     * @param SimpleXMLElement $currentWarnGeo Geo-Teil der aktuellen Wetterwarnung
     *
     * @throws Exception
     */
    final protected function getRegionHoehenangabe(SimpleXMLElement $currentWarnGeo): string {
        // Prüfe welche Höhenangabe verwendet werden muss für das Warngebiet (siehe Doku des DWD)
        if (0.0 === $this->getRegionAltitude($currentWarnGeo, false)
            && 9842.0 !== $this->getRegionCeiling($currentWarnGeo, false)
        ) {
            // Warngebiet unter angegebener Mindest-Höhe
            $hoehenangabe = 'Höhenlagen unter ' . $this->getRegionCeiling($currentWarnGeo, true) . 'm';
        } elseif (0.0 !== $this->getRegionAltitude($currentWarnGeo, false)
                  && 9842.0 === $this->getRegionCeiling($currentWarnGeo, false)
        ) {
            // Warngebiet über angegebener Höchstgebiet
            $hoehenangabe = 'Höhenlagen über ' . $this->getRegionAltitude($currentWarnGeo, true) . 'm';
        } else {
            // Alle höhenlagen
            $hoehenangabe = 'Alle Höhenlagen';
        }

        return $hoehenangabe;
    }
}
