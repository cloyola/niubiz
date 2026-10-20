<?php
/**
 * Internal upgrade to 3.2.3: code-only release (translations, error template, security API status handling,
 * removal of debug output). Settings, tables, Niubiz endpoints, callback URL and img/ logos are untouched.
 *
 * @license http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_3_2_3($module)
{
    // Raw callback file written by older versions: remove it if present (it may contain customer data).
    foreach (array('/../logs/niubiz_callback.txt', '/../logs/callback.txt') as $file) {
        $path = dirname(__FILE__).$file;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    if (method_exists('Tools', 'clearAllCache')) {
        Tools::clearAllCache();
    }

    return true;
}
