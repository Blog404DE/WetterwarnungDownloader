<?php

declare(strict_types=1);

/*
 *  WarnParser für neuthardwetter.de by Jens Dutzi
 *
 *  @package    blog404de\WetterWarnung
 *  @author     Jens Dutzi <jens.dutzi@tf-network.de>
 *  @copyright  Copyright (c) 2012-2019 Jens Dutzi (http://www.neuthardwetter.de)
 *  @license    https://github.com/Blog404DE/WetterwarnungDownloader/blob/master/LICENSE.md
 *  @version    v3.1.2
 *  @link       https://github.com/Blog404DE/WetterwarnungDownloader
 */

namespace blog404de\WetterWarnung\Action;

use Exception;

/**
 * Definition der zwingend benötigten Methoden für eine Action-Klasse.
 */
interface SendToInterface {
    /**
     * Action Ausführung starten.
     *
     * @param array $parsedWarnInfo
     * @param bool  $warnExists     Wetterwarnung existiert bereits
     *
     * @throws Exception
     *
     * @return int
     */
    public function startAction(array $parsedWarnInfo, bool $warnExists): int;

    /**
     * Setter für die Konfiguration das Konfigurations-Array.
     *
     * @param array $config Konfigurations-Array
     *
     * @throws Exception
     */
    public function setConfig(array $config);

    /**
     * Getter-Methode für das Konfigurations-Array.
     *
     * @return array
     */
    public function getConfig(): array;
}
