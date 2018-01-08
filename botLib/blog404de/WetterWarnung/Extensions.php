<?php
/**
 * WetterWarnung für neuthardwetter.de by Jens Dutzi - Extensions.php
 *
 * @package    blog404de\WetterWarnung
 * @author     Jens Dutzi <jens.dutzi@tf-network.de>
 * @copyright  Copyright (c) 2012-2018 Jens Dutzi (http://www.neuthardwetter.de)
 * @license    https://github.com/Blog404DE/WetterwarnungDownloader/blob/master/LICENSE.md
 * @version    3.0.0-dev
 * @link       https://github.com/Blog404DE/WetterwarnungDownloader
 */

namespace blog404de\WetterWarnung;

use blog404de\WetterWarnung\Action\SendToInterface;
use blog404de\WetterWarnung\Archive\ArchiveToInterface;
use \Exception;

/**
 * Trait für die Extensions-Unterstützung im WetterWarnung Parser
 *
 * @package blog404de\WetterWarnung
 */
trait Extensions
{
    /** @var SendToInterface Action-Klasse */
    private $actionClass;

    /** @var ArchiveToInterface Klasse für Archiv-Unterstützung via MySQL */
    public $archiveClass;

    /** @var array Array mit Konfiguration für die Action-Unterstützung */
    public $actionConfig;

    /** @var array Array mit Konfiguration für die Action-Unterstützung */
    public $archiveConfig;

    /** @var bool  Erzwinge ausführen der Action unabhängig davon ob es eine Aktualisierung gab */
    private $forceAction = false;

    /**
     * Beginne mit der Archivierung der Daten
     *
     * @throws Exception
     */
    private function startExctensionArchive()
    {
        try {
            // Durchlaufe alle definiertne Archive-Klassen
            foreach ($this->archiveConfig as $archiveClassName => $archiveConfig) {
                echo(PHP_EOL . "*** Starte Archivierung der Wetterwarnungen mit '" . $archiveClassName .
                    "' Modul:" . PHP_EOL);

                // Instanziere Klasse
                $archiveClassPath = "\\blog404de\\WetterWarnung\\Archive\\" . $archiveClassName;
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
     * Beginne mit der Action-Ausführung
     *
     * @throws Exception
     */
    private function startExtensionAction()
    {
        try {
            // Durchlaufe alle definiertne Action-Klassen
            foreach ($this->actionConfig as $actionClassName => $actionConfig) {
                echo(PHP_EOL . "*** Starte Action-Funktion '" . $actionClassName . "' der Wetterdaten ");
                if ($this->forceAction === true) {
                    echo("(alle Meldungen):" . PHP_EOL);
                } else {
                    echo("(neue Meldungen):" . PHP_EOL);
                }

                // Instanziere Klasse
                $actionClassPath = "\\blog404de\\WetterWarnung\\Action\\" . $actionClassName;
                $this->actionClass = new $actionClassPath();

                // Konfiguriere Action-Klasse
                $this->actionClass->setConfig($actionConfig);

                // Verarbeite jede Wetterwarnung
                foreach ($this->wetterWarnungen as $parsedWarnInfo) {
                    // Wetterwarnung an Action übergeben, sofern sich etwas geändert hatte
                    if ($this->forceAction === false) {
                        // Prüfe ob Action ausgeführt werden muss
                        $warnExists = $this->didWetterWarnungExist($parsedWarnInfo);
                    } else {
                        // Action auf jeden Fall ausführen
                        $warnExists = false;
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

    /**
     * Starte ausführen der hinterlegten Archiv-Funktion
     *
     * @param bool $archive Archiv-Funktion aufrufen
     * @param bool $action Action-Funktion aufrufen
     * @throws
     */
    public function startExtensions(bool $archive, bool $action)
    {
        try {
            if (count($this->wetterWarnungen) == 0) {
                // Keine Wetterwarnungen vorhanden
                echo(PHP_EOL . "*** Archiv/Action-Ausführung wird nicht gestartet, " .
                    "da keine Wetterwarnungen vorhanden sind" . PHP_EOL);
            } else {
                // Führe Action/Archiv aus.
                if ($archive) {
                    $this->startExctensionArchive();
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
     * Setzen der Konfiguration der Action-Funktion
     *
     * @param array $actionConfig
     * @param bool $forceAction
     */
    public function setActionConfig(array $actionConfig, bool $forceAction)
    {
        $this->actionConfig = $actionConfig;
        $this->forceAction = $forceAction;
    }

    /**
     * Setzen der Konfiguration der Archive-Funktion
     *
     * @param array $archiveConfig
     */
    public function setArchiveConfig(array $archiveConfig)
    {
        $this->archiveConfig = $archiveConfig;
    }
}
