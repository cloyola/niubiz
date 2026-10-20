## [3.2.2] - 2026-10-20
- FIX: front controllers called the protected `ModuleCore::trans()` (fatal error on /modulo/niubiz/validation); they now use the public `translate()` helper

## [3.2.0] - 2026-10-09
PrestaShop 9 compatibility release (same code base still installs on PrestaShop 1.7.6+ / PHP 7.2+).
- IMP: `ps_versions_compliancy` 1.7.6.0 - 9.99.99; code linted on PHP 8.3 and written without syntax newer than PHP 7.2
- FIX: PHP 8: removed use of undefined `limited_countries` (TypeError on install), declared all module properties (PHP 8.2 dynamic-property deprecation), guarded undefined indexes
- FIX: payment option / embedded form pointed to the non-existent `confirmation` controller (renamed to `return` in 3.1.0); now `validation` / `return` with `nbzheader`
- FIX: PrestaShop 9 removals: `Tools::displayPrice`, `Tools::substr/strtoupper`, `hookDisplayPaymentEU`, `addJS/addCSS` on front controllers (now `registerJavascript/registerStylesheet`), Smarty 4 `|print_r` modifier
- FIX: `die()` calls in credential/session errors replaced by PrestaShop log + friendly error page
- SEC: cURL now verifies TLS certificates, with timeouts; SQL values escaped/cast in admin hook, callback and return; session cookie is HttpOnly/Secure/SameSite; PagoEfectivo redirect only to https URLs; BO log output escaped
- SEC: `userTokenId` uses `random_bytes`
- FIX: PagoEfectivo callback now stores the order history row; amount comparison tolerant to float rounding
- IMP: tables use utf8mb4, `url` column is TEXT
- ADD: internal upgrade `upgrade/upgrade-3.2.0.php` - keeps NBZ_* settings, tables, Niubiz endpoints, callback URL and `img/` logos unchanged
- ADD: composer.json

## [3.1.1] - 2024-04-29
- IMP: Table log in detail order
- ADD: Button copy to transactiontoken


## [3.1.0] - 2024-01-15
- IMP: Security nbzheader to comunicate with niubiz button
- IMP: Encode nbzheader to transactions
- IMP: Add hook displayAdminOrderSideBottom in Order Detail
- IMP: Rename Confirmation.php to Return.php
- IMP: Clean comments and DIE code

## [1.0.8] - 2020-12-23

### Added

- Changelog [@victorcastro](https://github.com/victorcastro).

### Changed
- Concatenando idcart en urlReturn de Checkout y Niubiz
- Actualizacion de Version


## [1.0.7] - 2020-10-30

### Added

- Optimizacion de codigo
- Adicion de variables globales

### Fixed
- Correcion de URLReturn en backend