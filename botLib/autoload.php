<?php
/**
 * WarnParser für neuthardwetter.de by Jens Dutzi - autoload.php
 *
 * @package    blog404de\WetterWarnung
 * @author     Jens Dutzi <jens.dutzi@tf-network.de>
 * @copyright  Copyright (c) 2012-2018 Jens Dutzi (http://www.neuthardwetter.de)
 * @license    https://github.com/Blog404DE/WetterwarnungDownloader/blob/master/LICENSE.md
 * @version    v3.0.2
 * @link       https://github.com/Blog404DE/WetterwarnungDownloader
 */

/**
 * Autoloader
 *
 * @param string $class fully-qualified class name.
 * @return void
 */

spl_autoload_register(function ($class) {
    // Basis-Verzeichnis ermitteln
    $base_dir = __DIR__ . DIRECTORY_SEPARATOR;

    // Klassen-Pfad anhand des Namespace hinzufügen
    $file = $base_dir . str_replace('\\', '/', $class) . '.php';

    // Prüfe ob Klassen-Datei vorhanden ist und falls ja, lade diese über den Autoloader,
    // ansonstne ignoriere den Autoloader
    if (file_exists($file)) {
        /** @noinspection PhpIncludeInspection */
        require $file;
    }
});
