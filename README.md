<p align="center">
    <img src="https://raw.githubusercontent.com/vhc-prestashop-peru/niubiz/master/.docs/niubiz.jpg" alt="Niubiz logo" />
</p>
<h3 align="center">Module payment to Prestashop</h3>
<p align="center">The most complete module payment with standard coding and syntax Prestashop 1.7</p>
<br />

### About module
This module provides integration to an Internet payment service with a VISA card and is used as a support for electronic commerce solutions, but not only allows this logical separation and dedication of resources, it also increases security and protects businesses from each other. through authentication and access control.
<br>

### Installation
#### Mode Backoffice
1. Download the [last version stable](https://github.com/vhc-prestashop-peru/niubiz/releases)
1. Upload module from Backoffice
2. Select your limit currencies for countries in: modules > preferences
3. Complete settings module and enjoy

#### Mode SSH (Advanced)
1. In path `/modules` on your prestashop:
```bash
git clone https://github.com/vhc-prestashop-peru/niubiz.git
```
2. Install module from Backoffice
3. Select your limit currencies for countries in: modules > preferences
4. Complete settings module and enjoy

### Settings
<img width="300" src="https://raw.githubusercontent.com/vhc-prestashop-peru/niubiz/master/.docs/form.png">
Add you parameters sent for Niubiz in form on Configure Module.

### Contributing
- **Victor Castro** <<victorcastro.io@icloud.com>>
- **Roger Pecho** <<integraciones@visanet.com.pe>>

### Compatibility
| PrestaShop | PHP | Status |
|---|---|---|
| 1.7.6+ and 8.x | PHP 7.2+ (as supported by that PrestaShop version) | declared compatible, not retested in 3.2.0 |
| 9.0 / 9.1 | 8.1 - 8.5 (per PrestaShop's system requirements chart) | target of 3.2.0, needs staging test |

Upgrading from an older module version: just overwrite the module folder (or upload the zip) and click *Upgrade* in Module Manager;
`upgrade/upgrade-3.2.0.php` keeps the existing configuration (`NBZ_*`), tables (`niubiz_*`), the callback URL given to Niubiz/PagoEfectivo and the logos stored in `img/`.
