<?php

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

namespace blog404de\WetterWarnung\Archive;

use Exception;
use RuntimeException;

/**
 * Interface für die Archiv-Unterstützung.
 */
interface ArchiveToInterface {
    /**
     * Setter für MySQL Zugangsdaten.
     *
     * @param array $config Konfigurations-Array
     *
     * @throws Exception|RuntimeException
     */
    public function setConfig(array $config): void;

    /**
     * Getter-Methode für das Konfigurations-Array.
     */
    public function getConfig(): array;

    /**
     * Speichere Wetterwarnung in Archiv.
     *
     * @param array $parsedWarnInfo Inhalt der WetterWarnung
     *
     * @throws Exception|RuntimeException
     */
    public function saveToArchive(array $parsedWarnInfo): void;
}
