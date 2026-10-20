<p align="center">
    <img src="img/logo_niubiz.png" alt="Niubiz logo" />
</p>
<h3 align="center">Module payment to Prestashop</h3>
<p align="center">The most complete module payment with standard coding and syntax Prestashop 1.7</p>
<br />

### About module
This module provides integration to an Internet payment service with a VISA card and is used as a support for electronic commerce solutions, but not only allows this logical separation and dedication of resources, it also increases security and protects businesses from each other. through authentication and access control.
<br>

### Installation
#### Mode Backoffice
1. Download the [last version stable](https://github.com/cloyola/niubiz/releases)
1. Upload module from Backoffice
2. Select your limit currencies for countries in: modules > preferences
3. Complete settings module and enjoy

#### Mode SSH (Advanced)
1. In path `/modules` on your prestashop:
```bash
git clone https://github.com/cloyola/niubiz.git
```
2. Install module from Backoffice
3. Select your limit currencies for countries in: modules > preferences
4. Complete settings module and enjoy

### Settings
<img width="300" src="img/payments.png">
Add you parameters sent for Niubiz in form on Configure Module.

### Contributing
- **Victor Castro** <<victorcastro.io@icloud.com>>
- **Roger Pecho** <<integraciones@visanet.com.pe>>
- **Carlos Loyola** <<carlosloyolav@gmail.com>>

### Testing
Merchant codes [Niubiz Peru merchants](https://desarrolladores.niubiz.com.pe/docs/bot%C3%B3n-de-pago-1#c%C3%B3digo-de-comercio)
Credentials [Niubiz Peru credentials](https://desarrolladores.niubiz.com.pe/docs/bot%C3%B3n-de-pago-1#credenciales)
Credit cards [Niubiz Peru credit cards](https://desarrolladores.niubiz.com.pe/docs/bot%C3%B3n-de-pago-1#tarjetas-de-prueba)

### Compatibility
| PrestaShop | PHP | Status |
|---|---|---|
| 1.7.6+ and 8.x | PHP 7.2+ (as supported by that PrestaShop version) | declared compatible, not retested in 3.2.0 |
| 9.0 / 9.1 | 8.1 - 8.5 (per PrestaShop's system requirements chart) | target of 3.2.0, needs staging test |

Upgrading from an older module version: just overwrite the module folder (or upload the zip) and click *Upgrade* in Module Manager.
`upgrade/upgrade-3.2.1.php` keeps the existing configuration (`NBZ_*`), tables (`niubiz_*`), the callback URL given to Niubiz/PagoEfectivo and the logos stored in `img/`. Shops coming from a version before 3.2.0 also run `upgrade/upgrade-3.2.0.php`.


## [3.2.3] - 2026-10-20
Production release.
- FIX: front controllers called the protected `ModuleCore::trans()` (fatal error on /modulo/niubiz/validation); they now use `getTranslator()->trans()`
- FIX: `error.tpl` read an undefined `$errors` variable on module pages (now `$nbz_errors`)
- FIX: the security API answers HTTP 201: any 2xx is accepted (was rejected, so the payment could not start)
- IMP: translations follow the PrestaShop new system: `isUsingNewTranslationSystem()`, literal strings, placeholders, domains `Modules.Niubiz.Admin` / `Modules.Niubiz.Shop`
- IMP: API failures are written to the PrestaShop log (`NIUBIZ ::` prefix) with HTTP code / cURL error; nothing diagnostic is shown to customers
- SEC: removed the customer-visible debug dump of session data and the raw callback file in `logs/`; with the Debugger switch on, the callback payload goes to the PrestaShop log only
- ADD: `upgrade/upgrade-3.2.3.php` (cache clear, settings/tables/endpoints/img untouched)

## [3.2.1] - 2026-10-19
- FIX: PHP 8 fatal on install and on upgrade: `include (path) === false` was parsed as `include ''` (ValueError: Path cannot be empty)
- FIX: uploading the payment logo in the module configuration crashed the back office on PrestaShop 8/9 (FileNotFoundException: /tmp/phpXXXX does not exist) because the temporary upload was moved with `move_uploaded_file`. The image is now validated and re-encoded with `ImageManager`, the temp file is left to PHP, file names are random, only png/gif/jpg are accepted
- FIX: saving the form no longer erases the logos of other languages, and a failed upload no longer skips saving the rest of the settings
- ADD: internal upgrade `upgrade/upgrade-3.2.1.php` — keeps `NBZ_*` settings, `niubiz_*` tables, the callback URL and `img/` logos, and clears compiled templates so the configuration form is refreshed

## [3.2.0] - 2026-10-09
PrestaShop 9 compatibility release (same code base still installs on PrestaShop 1.7.6+ / PHP 7.2+).
- IMP: `ps_versions_compliancy` 1.7.6.0 - 9.99.99; code linted on PHP 8.3 and written without syntax newer than PHP 7.2