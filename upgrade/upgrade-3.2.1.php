<?php
/**
 * Internal upgrade to 3.2.1.
 *
 * Bugfix release on top of 3.2.0. Nothing external changes: same Niubiz endpoints / checkout.js,
 * same NBZ_* configuration keys, same niubiz_* tables, same callback URL
 * (index.php?fc=module&module=niubiz&controller=callback) and same img/ folder for uploaded
 * payment logos. This script only repairs module internals and clears compiled templates so the
 * configuration form (views/templates/admin) is picked up after the upgrade.
 *
 * The install SQL is included into a variable first. On PHP 8, `include (path) === false` is
 * parsed as `include ''` and throws ValueError: Path cannot be empty.
 *
 * @license http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_3_2_1($module)
{
    $ok = true;

    // 1. Make sure tables exist (idempotent: CREATE TABLE IF NOT EXISTS).
    $installFile = dirname(__FILE__).'/../sql/install.php';
    $installed = false;
    if (is_file($installFile)) {
        $installed = include $installFile;
    }
    if ($installed === false) {
        $ok = false;
    }

    // 2. Hooks (re-register is a no-op when already hooked). displayPaymentEU no longer exists.
    foreach (array('header', 'displayBackOfficeHeader', 'paymentOptions', 'displayAdminOrderSideBottom', 'displayPaymentReturn') as $hook) {
        if (!$module->isRegisteredInHook($hook)) {
            $ok = $module->registerHook($hook) && $ok;
        }
    }
    if ($module->isRegisteredInHook('displayPaymentEU')) {
        $module->unregisterHook('displayPaymentEU');
    }

    // 3. Folder where the uploaded payment logos live (unchanged location).
    $imgDir = dirname(__FILE__).'/../img';
    if (!is_dir($imgDir)) {
        @mkdir($imgDir, 0755, true);
    }
    if (is_dir($imgDir) && !file_exists($imgDir.'/index.php') && file_exists(dirname(__FILE__).'/../index.php')) {
        @copy(dirname(__FILE__).'/../index.php', $imgDir.'/index.php');
    }

    // 4. Waiting-for-PagoEfectivo order state: recreate only if the stored id points to a deleted state.
    $module->createStateIfNotExist();

    // 5. Environment defaults to the sandbox only when it was never configured.
    if (Configuration::get('NBZ_ENVIROMENT') === false) {
        Configuration::updateValue('NBZ_ENVIROMENT', 'DEV');
    }

    // 6. Callback URL stored for the BO form always follows the current shop domain/protocol.
    Configuration::updateValue('NBZ_CALLBACK', $module->callback);

    // 7. Columns added after the first release (no-op on shops that already have them).
    $table = _DB_PREFIX_.'niubiz_pagoefectivo';
    $col = Db::getInstance()->executeS('SHOW COLUMNS FROM `'.bqSQL($table).'` LIKE "url"');
    if ($col && isset($col[0]['Type']) && stripos($col[0]['Type'], 'text') === false) {
        Db::getInstance()->execute('ALTER TABLE `'.bqSQL($table).'` MODIFY `url` TEXT');
    }

    // 8. Old compiled templates / cache (Smarty 3 -> 4 on PS 9).
    if (method_exists('Tools', 'clearAllCache')) {
        Tools::clearAllCache();
    }

    return $ok;
}
