<?php
/**
 * WarnParser für neuthardwetter.de by Jens Dutzi - ArchiveToInterface.php
 *
 * @package    blog404de\WetterWarnung
 * @author     Jens Dutzi <jens.dutzi@tf-network.de>
 * @copyright  Copyright (c) 2012-2018 Jens Dutzi (http://www.neuthardwetter.de)
 * @license    https://github.com/Blog404DE/WetterwarnungDownloader/blob/master/LICENSE.md
 * @version    v3.0.1
 * @link       https://github.com/Blog404DE/WetterwarnungDownloader
 */

namespace blog404de\WetterWarnung\Archive;

use Exception;

/**
 * Interface für die Archiv-Unterstützung
 *
 * @package blog404de\WetterWarnung\Archive
 */
interface ArchiveToInterface
{
    /**
     * Setter für MySQL Zugangsdaten
     *
     * @param array $config
     * @throws Exception
     */
    public function setConfig(array $config);

    /**
     * Getter-Methode für das Konfigurations-Array
     *
     * @return array
     */
    public function getConfig(): array;

    /**
     * Speichere Wetterwarnung in Archiv
     *
     * @param array $parsedWarnInfo
     * @throws Exception
     */
    public function saveToArchive(array $parsedWarnInfo);
}
