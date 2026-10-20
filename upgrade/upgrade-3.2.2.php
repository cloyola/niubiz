<?php
/**
 * Internal upgrade to 3.2.2: code-only fix (front controllers translation helper).
 * Settings, tables, endpoints, callback URL and img/ logos are untouched; only the cache is cleared.
 *
 * @license http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_3_2_2($module)
{
    if (method_exists('Tools', 'clearAllCache')) {
        Tools::clearAllCache();
    }

    return true;
}
