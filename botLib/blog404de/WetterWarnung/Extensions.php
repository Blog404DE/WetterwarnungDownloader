<?php

declare(strict_types=1);

/*
 *  WarnParser für neuthardwetter.de by Jens Dutzi
 *
 *  @package    blog404de\WetterWarnung
 *  @author     Jens Dutzi <jens.dutzi@tf-network.de>
 *  @copyright  Copyright (c) 2012-2019 Jens Dutzi (http://www.neuthardwetter.de)
 *  @license    https://github.com/Blog404DE/WetterwarnungDownloader/blob/master/LICENSE.md
 *  @version    v3.1.4
 *  @link       https://github.com/Blog404DE/WetterwarnungDownloader
 */

namespace blog404de\WetterWarnung;

use blog404de\WetterWarnung\Action\SendToInterface;
use blog404de\WetterWarnung\Archive\ArchiveToInterface;
use Exception;

/**
 * Trait für die Extensions-Unterstützung im WetterWarnung Parser.
 */
trait Extensions {
    /** @var ArchiveToInterface Klasse für Archiv-Unterstützung via MySQL */
    public $archiveClass;

    /** @var array Array mit Konfiguration für die Action-Unterstützung */
    public $actionConfig;

    /** @var array Array mit Konfiguration für das System */
    public $systemConfig;

    /** @var array Array mit Konfiguration für die Action-Unterstützung */
    public $archiveConfig;
    /** @var SendToInterface Action-Klasse */
    private $actionClass;

    /** @var bool Erzwinge ausführen der Action unabhängig davon ob es eine Aktualisierung gab */
    private $forceAction = false;

    /**
     * Starte ausführen der hinterlegten Archiv-Funktion.
     *
     * @param bool $archive Archiv-Funktion aufrufen
     * @param bool $action  Action-Funktion aufrufen
     *
     * @throws
     */
    public function startExtensions(bool $archive, bool $action) {
        try {
            if (0 === \count($this->wetterWarnungen)) {
                // Keine Wetterwarnungen vorhanden
                echo PHP_EOL . '*** Archiv/Action-Ausführung wird nicht gestartet, ' .
                    'da keine Wetterwarnungen vorhanden sind' . PHP_EOL;
            } else {
                // Führe Action/Archiv aus.
                if ($archive) {
                    $this->startExtensionArchive();
                }

                if ($action) {
                    $this->startExtensionAction();
                }
            }
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Setzen der Konfiguration der Action-Funktion.
     *
     * @param array $systemConfig Konfigurationsparameter über das System
     * @param array $actionConfig Konfigurationsparameter für ein spezifische Action
     * @param bool  $forceAction  Action-Ausführung erzwingen
     *
     * @throws Exception
     */
    public function setActionConfig(array $systemConfig, array $actionConfig, bool $forceAction) {
        try {
            // Prüfe ob actionConfig Parameter valid sind
            $actionParameter = [
                'MessagePrefix',
                'MessagePostfix',
                'ignoreForceUpdate',
            ];

            foreach ($actionParameter as $parameter) {
                if (!\array_key_exists($parameter, current($actionConfig))) {
                    throw new Exception(
                        'Der Konfigurationsparamter ["ActionConfig"]["' . $parameter . '"] wurde nicht gesetzt.'
                    );
                }
            }

            // Prüfe ob systemConfig-Parameter valid sind
            $systemParameter = [
                'localIconFolder',
            ];

            foreach ($systemParameter as $parameter) {
                if (!\array_key_exists($parameter, $systemConfig)) {
                    throw new Exception(
                        'Der Konfigurationsparamter ["localIconFolder"] wurde nicht gesetzt.'
                    );
                }
            }

            $this->actionConfig = $actionConfig;
            $this->systemConfig = $systemConfig;
            $this->forceAction = $forceAction;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Setzen der Konfiguration der Archive-Funktion.
     *
     * @param array $archiveConfig Konfigurations-Array für die Archiv-Extension
     */
    public function setArchiveConfig(array $archiveConfig) {
        $this->archiveConfig = $archiveConfig;
    }

    /**
     * Beginne mit der Archivierung der Daten.
     *
     * @throws Exception
     */
    private function startExtensionArchive() {
        try {
            // Durchlaufe alle definiertne Archive-Klassen
            foreach ($this->archiveConfig as $archiveClassName => $archiveConfig) {
                echo PHP_EOL . "*** Starte Archivierung der Wetterwarnungen mit '" . $archiveClassName .
                    "' Modul:" . PHP_EOL;

                // Instanziere Klasse
                $archiveClassPath = '\\blog404de\\WetterWarnung\\Archive\\' . $archiveClassName;
                $this->archiveClass = new $archiveClassPath();

                // Konfiguriere Action-Klasse
                $this->archiveClass->setConfig($archiveConfig);

                // Verarbeite jede Wetterwarnung
                foreach ($this->wetterWarnungen as $parsedWarnInfo) {
                    // Speichere Wetterwarnung in's Archiv
                    $this->archiveClass->saveToArchive($parsedWarnInfo);
                }
            }
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Beginne mit der Action-Ausführung.
     *
     * @throws Exception
     */
    private function startExtensionAction() {
        try {
            // Durchlaufe alle definiertne Action-Klassen
            foreach ($this->actionConfig as $actionClassName => $actionConfig) {
                echo PHP_EOL . "*** Starte Action-Funktion '" . $actionClassName . "' der Wetterdaten ";

                if (true === $this->forceAction && false === $actionConfig['ignoreForceUpdate']) {
                    echo '(alle Meldungen):' . PHP_EOL;
                    $warnExists = false;
                } elseif (true === $this->forceAction && true === $actionConfig['ignoreForceUpdate']) {
                    echo '(neue Meldungen / forceUpdate ignoriert):' . PHP_EOL;
                    $warnExists = null;
                } else {
                    echo '(neue Meldungen):' . PHP_EOL;
                    $warnExists = null;
                }
                // Instanziere Klasse
                $actionClassPath = '\\blog404de\\WetterWarnung\\Action\\' . $actionClassName;
                $this->actionClass = new $actionClassPath();

                // Konfiguriere Action-Klasse
                // -> füge System-Konfiguration und Konfiguration der Action-Klasse zusammen
                $this->actionClass->setConfig(array_merge($this->systemConfig, $actionConfig));

                // Verarbeite jede Wetterwarnung
                foreach ($this->wetterWarnungen as $parsedWarnInfo) {
                    // Wetterwarnung an Action übergeben, sofern sich etwas geändert hatte
                    if (null === $warnExists) {
                        // Prüfe ob Action ausgeführt werden muss
                        $warnExists = $this->didWetterWarnungExist($parsedWarnInfo);
                    }

                    // Warnmeldung existiert nicht und führe Action aus
                    $this->actionClass->startAction($parsedWarnInfo, $warnExists);
                }
            }
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }
}
