<?php
/**
 * Wetterwarnung-Downloader für neuthardwetter.de by Jens Dutzi
 *
 * @package    blog404de\WetterScripts
 * @subpackage ConfigFile
 * @author     Jens Dutzi <jens.dutzi@tf-network.de>
 * @copyright  2012-2018 Jens Dutzi
 * @version    2.7.0-dev
 * @license    MIT
 *
 * Stand: 2018-01-06
 *
 * Lizenzinformationen (MIT License):
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this
 * software and associated documentation files (the "Software"), to deal in the Software
 * without restriction, including without limitation the rights to use, copy, modify,
 * merge, publish, distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies
 * or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A
 * PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

/*
spl_autoload_register(function ($class_name) {
    $filename = $class_name . ".php";
    include  dirname( __FILE__ ) . "/" . $filename;
});
*/

set_include_path(dirname(__FILE__).PATH_SEPARATOR.get_include_path());
spl_autoload_extensions(".php");
spl_autoload_register("spl_autoload");
