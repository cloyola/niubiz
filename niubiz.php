<?php
/**
* 2007-2023 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2023 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Niubiz extends PaymentModule
{
    protected $config_form = false;

    // Declared explicitly: dynamic properties are deprecated since PHP 8.2.
    public $views;
    public $domain;
    public $url_return;
    public $callback;
    public $email;
    public $github;
    public $limited_currencies = array('USD', 'PEN');
    public $psVersion;
    public $merchantid = '';
    public $vsauser = '';
    public $vsapassword = '';
    public $security_api = '';
    public $session_api = '';
    public $authorization_api = '';
    public $urlScript = '';
    public $acceptedCurrency = array();
    public $html = '';

    public function __construct()
    {
        $this->name = 'niubiz';
        $this->tab = 'payments_gateways';
        $this->version = '3.2.0';
        $this->author = 'Victor Castro';
        $this->need_instance = 0;
        $this->controllers = array('validation', 'return', 'callback');

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        $this->views = _MODULE_DIR_.$this->name.'/views/';
        $this->domain = Tools::getShopDomainSsl(true, true).__PS_BASE_URI__;
        $this->url_return = $this->domain.'index.php?fc=module&module='.$this->name.'&controller=return';
        $this->callback = $this->domain.'index.php?fc=module&module='.$this->name.'&controller=callback';

        parent::__construct();

        $this->displayName = $this->l('Niubiz - Pagos con tarjeta de crédito y débito');
        $this->description = $this->l('Realiza pagos con tu tarjeta de crédito y débito en Perú');
        $this->email = "integraciones.niubiz@necomplus.com";
        $this->github = "https://github.com/IntegracionesVisaNet";

        // Same module code runs on PrestaShop 1.7.6 (PHP 7.2) up to PrestaShop 9.x (PHP 8.1-8.5).
        $this->ps_versions_compliancy = array('min' => '1.7.6.0', 'max' => '9.99.99');
        $this->psVersion = _PS_VERSION_;

        if (function_exists('curl_init') == false) {
            $this->warning = $this->trans('In order to use this module, activate cURL (PHP extension).', array(), 'Modules.Niubiz.Admin');
        }


        $currency = $this->getActiveCurrency();

        switch ($currency->iso_code) {
            case 'PEN':
                $this->merchantid = Configuration::get('NBZ_MERCHANTID_PEN');
                $this->vsauser = Configuration::get('NBZ_USER_PEN');
                $this->vsapassword = Configuration::get('NBZ_PASSWORD_PEN');
                break;

            case 'USD':
                $this->merchantid = Configuration::get('NBZ_MERCHANTID_USD');
                $this->vsauser = Configuration::get('NBZ_USER_USD');
                $this->vsapassword = Configuration::get('NBZ_PASSWORD_USD');
                break;

            default:
                $this->merchantid = '';
                $this->vsauser = '';
                $this->vsapassword = '';
                break;
        }

        switch (Configuration::get('NBZ_ENVIROMENT')) {
            case 'PRD':
                $this->security_api = 'https://apiprod.vnforapps.com/api.security/v1/security';
                $this->session_api = 'https://apiprod.vnforapps.com/api.ecommerce/v2/ecommerce/token/session/'.$this->merchantid;
                $this->authorization_api = 'https://apiprod.vnforapps.com/api.authorization/v3/authorization/ecommerce/'.$this->merchantid;
                $this->urlScript = 'https://static-content.vnforapps.com/v2/js/checkout.js';
                break;

            case 'DEV':
            default:
                $this->security_api = 'https://apisandbox.vnforappstest.com/api.security/v1/security';
                $this->session_api = 'https://apisandbox.vnforappstest.com/api.ecommerce/v2/ecommerce/token/session/'.$this->merchantid;
                $this->authorization_api = 'https://apisandbox.vnforappstest.com/api.authorization/v3/authorization/ecommerce/'.$this->merchantid;
                $this->urlScript = 'https://static-content-qas.vnforapps.com/v2/js/checkout.js?qa=true';
                break;
        }
    }

    /**
     * Currency currently used by the visitor. Falls back to the context currency / shop default,
     * because the cookie has no id_currency in CLI, webservice or upgrade contexts.
     */
    public function getActiveCurrency()
    {
        $idCurrency = 0;
        if (isset($this->context->cookie) && isset($this->context->cookie->id_currency)) {
            $idCurrency = (int) $this->context->cookie->id_currency;
        }
        if (!$idCurrency && isset($this->context->currency) && Validate::isLoadedObject($this->context->currency)) {
            $idCurrency = (int) $this->context->currency->id;
        }
        if (!$idCurrency) {
            $idCurrency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
        }

        return new Currency($idCurrency);
    }

    /**
     * Format a price on every supported PrestaShop version (Tools::displayPrice was removed in 9).
     */
    public function formatPrice($amount, $currency = null)
    {
        $currency = $currency ? $currency : $this->context->currency;
        if (method_exists($this->context, 'getCurrentLocale') && $this->context->getCurrentLocale()) {
            return $this->context->getCurrentLocale()->formatPrice($amount, $currency->iso_code);
        }

        return Tools::displayPrice($amount, $currency, false);
    }

    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        if (include(_PS_MODULE_DIR_ . 'niubiz/sql/install.php') === false) {
            return false;
        }

        if (!is_dir(_PS_MODULE_DIR_ . 'niubiz/img')) {
            @mkdir(_PS_MODULE_DIR_ . 'niubiz/img', 0755, true);
        }

        $link = new Link;
        Configuration::updateValue('NBZ_LOGO', $link->getMediaLink(_PS_IMG_.Configuration::get('PS_LOGO')));
        Configuration::updateValue('NBZ_PAYMENT_OPTION_TEXT', $this->displayName);

        $this->createStateIfNotExist();

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('displayBackOfficeHeader') &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('displayAdminOrderSideBottom') &&
            $this->registerHook('displayPaymentReturn');
    }

    public function createStateIfNotExist()
    {
        if (!Configuration::get('NBZ_STATE_WAITING_CAPTURE')
            || !Validate::isLoadedObject(new OrderState((int) Configuration::get('NBZ_STATE_WAITING_CAPTURE')))) {
            $order_state = new OrderState();
            $order_state->name = array();
            foreach (Language::getLanguages() as $language) {
              $order_state->name[$language['id_lang']] = 'En espera de pago por Niubiz';
            }
            $order_state->module_name = $this->name;
            $order_state->color = '#4169E1';
            $order_state->send_email = false;
            $order_state->hidden = false;
            $order_state->paid = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;
            $order_state->pdf_invoice = false;
            if ($order_state->add()) {
                Configuration::updateValue('NBZ_STATE_WAITING_CAPTURE', (int)$order_state->id);
            }
        }
    }

    public function uninstall()
    {
        if (!Configuration::deleteByName('NBZ_LOGO')
        || !Configuration::deleteByName('NBZ_PAYMENT_OPTION_TEXT')
        || !Configuration::deleteByName('NBZ_PAYMENT_OPTION_LOGO')
         || !Configuration::deleteByName('NBZ_MERCHANTID_PEN')
         || !Configuration::deleteByName('NBZ_USER_PEN')
         || !Configuration::deleteByName('NBZ_PEN')
         || !Configuration::deleteByName('NBZ_USD')
         || !Configuration::deleteByName('NBZ_DEBUG')
         || !Configuration::deleteByName('NBZ_PASSWORD_PEN')
         || !Configuration::deleteByName('NBZ_MERCHANTID_USD')
         || !Configuration::deleteByName('NBZ_ACCESSKEY_USD')
         || !Configuration::deleteByName('NBZ_SECRETKEY_USD')
         || !Configuration::deleteByName('NBZ_USER_USD')
         || !Configuration::deleteByName('NBZ_PASSWORD_USD')
         || !parent::uninstall()) {
            return false;
        }

        return true;
    }
    
    public function hookDisplayAdminOrderSideBottom($params)
    {
        $orderId = (int) $params['id_order'];

        $request = 'SELECT * FROM `'._DB_PREFIX_.'niubiz_log` WHERE id_order = '.$orderId;

        $row = Db::getInstance()->getRow($request);

        $this->context->smarty->assign(array(
            'logNbz' => $row,
            'params' => $params,
            'logoNiubiz' => $this->_path.'logo.png'
        ));

        return $this->display(__FILE__, 'displayAdminOrderSideBottom.tpl');
    }

    private function postValidation()
    {
        $errors = array();

        if ((bool)Tools::isSubmit('submitNiubizModule')) {
            if (empty(Tools::getValue('NBZ_LOGO'))) {
                $errors[] = $this->trans('El Logo es obligatorio', array(), 'Modules.Niubiz.Admin');
            }
        }

        if (count($errors)) {
            $this->html .= $this->displayError(implode('<br />', $errors));
            return false;
        }

        return true;
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        $output = '';
        if ($this->postValidation()) {
            $output .= (string) $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $this->html.$output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitNiubizModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm($this->getConfigForm());
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {

        $fields_form = array();

        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->trans('FRONT CONFIGURATION', array(), 'Modules.Niubiz.Admin'),
                'icon' => 'icon-cogs'
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->trans('Logo URL', array(), 'Modules.Niubiz.Admin'),
                    'desc' => 'La url tiene que ir sin '.Tools::getShopProtocol(). ', solo coloque el dominio y la ruta de la imagen',
                    'name' => 'NBZ_LOGO',
                    'required' => true,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('Nombre de pago', array(), 'Modules.Niubiz.Admin'),
                    'desc' => $this->trans('Nombre que saldra en las opciones de pago', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_PAYMENT_OPTION_TEXT',
                    'required' => true,
                    'lang' => true,
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->trans('Mostrar logo', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_PAYMENT_OPTION_LOGO_SHOW',
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->trans('Enabled', array(), 'Modules.Niubiz.Admin')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->trans('Disabled', array(), 'Modules.Niubiz.Admin')
                        )
                    ),
                ),
                array(
                    'type' => 'file_lang',
                    'label' => $this->trans('Logo de pago', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_PAYMENT_OPTION_LOGO',
                    'desc' => 'Si estás usando el tema predeterminado las dimensiones recomendadas son 26x220 px.',
                    'lang' => true,
                ),
            ),
        );

        $fields_form[1]['form'] = array(
            'legend' => array(
                'title' => $this->trans('Configuracion de Modal', array(), 'Modules.Niubiz.Admin'),
                'icon' => 'icon-credit-card'
            ),
            'input' => array(
                array(
                    'type' => 'select',
                    'label'=>  $this->trans('Enviroment', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_ENVIROMENT',
                    'options' => array(
                        'query' => array(
                            array('id' => 'DEV', 'name' => $this->trans('Integration', array(), 'Modules.Niubiz.Admin')),
                            array('id' => 'PRD', 'name' => $this->trans('Production', array(), 'Modules.Niubiz.Admin')),
                        ),
                        'id' => 'id',
                        'name' => 'name'
                    )
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->trans('Show pop-up in PaymentOption', array(), 'Modules.Niubiz.Admin'),
                    'desc' => $this->trans('Mostrará la opción de pagar dentro del resumen del pedido al seleccionarlo', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_ENABLE_PAYMENTOPTION',
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->trans('Enabled', array(), 'Modules.Niubiz.Admin')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->trans('Disabled', array(), 'Modules.Niubiz.Admin')
                        )
                    ),
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('URL PagoEfectivo', array(), 'Modules.Niubiz.Admin'),
                    'desc' => $this->trans('Send this url to Niubiz to capture the remote payment of PagoEfectivo.', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_CALLBACK',
                    'required' => false,
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->trans('Debugger', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_DEBUG',
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->trans('Enabled', array(), 'Modules.Niubiz.Admin')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->trans('Disabled', array(), 'Modules.Niubiz.Admin')
                        )
                    ),
                ),
            ),
            'submit' => array(
                'title' => $this->trans('Save', array(), 'Modules.Niubiz.Admin'),
            )
        );

        $fields_form[2]['form'] = array(
            'legend' => array(
                'title' => $this->trans('SOLES CONFIGURATION', array(), 'Modules.Niubiz.Admin'),
            ),
            'input' => array(
                array(
                    'type' => 'switch',
                    'label' => $this->trans('Active', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_PEN',
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->trans('Enabled', array(), 'Modules.Niubiz.Admin')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->trans('Disabled', array(), 'Modules.Niubiz.Admin')
                        )
                    ),
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('Commerce', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_MERCHANTID_PEN',
                    'required' => false
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('Email', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_USER_PEN',
                    'required' => false
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('Password', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_PASSWORD_PEN',
                    'required' => false
                ),
            ),
            'submit' => array(
                'title' => $this->trans('Save', array(), 'Modules.Niubiz.Admin'),
            )
        );

        $fields_form[3]['form'] = array(
            'legend' => array(
                'title' => $this->trans('DOLARES CONFIGURATION', array(), 'Modules.Niubiz.Admin'),

            ),
            'input' => array(
                array(
                    'type' => 'switch',
                    'label' => $this->trans('Active', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_USD',
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->trans('Enabled', array(), 'Modules.Niubiz.Admin')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->trans('Disabled', array(), 'Modules.Niubiz.Admin')
                        )
                    ),
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('Commerce', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_MERCHANTID_USD',
                    'required' => false
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('Email', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_USER_USD',
                    'required' => false
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('Password', array(), 'Modules.Niubiz.Admin'),
                    'name' => 'NBZ_PASSWORD_USD',
                    'required' => false
                ),
            ),
            'submit' => array(
                'title' => $this->trans('Save', array(), 'Modules.Niubiz.Admin'),
            )
        );

        return $fields_form;
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        $languages = Language::getLanguages(false);
        $fields = array();

        $fields['NBZ_DEBUG'] = Tools::getValue('NBZ_DEBUG', Configuration::get('NBZ_DEBUG'));
        $fields['NBZ_LOGO'] = Tools::getValue('NBZ_LOGO', Configuration::get('NBZ_LOGO'));
        $fields['NBZ_ENVIROMENT'] = Tools::getValue('NBZ_ENVIROMENT', Configuration::get('NBZ_ENVIROMENT'));
        $fields['NBZ_MERCHANTID_PEN'] = Tools::getValue('NBZ_MERCHANTID_PEN', trim(Configuration::get('NBZ_MERCHANTID_PEN')));
        $fields['NBZ_USER_PEN'] = Tools::getValue('NBZ_USER_PEN', trim(Configuration::get('NBZ_USER_PEN')));
        $fields['NBZ_PASSWORD_PEN'] = Tools::getValue('NBZ_PASSWORD_PEN', trim(Configuration::get('NBZ_PASSWORD_PEN')));
        $fields['NBZ_MERCHANTID_USD'] = Tools::getValue('NBZ_MERCHANTID_USD', trim(Configuration::get('NBZ_MERCHANTID_USD')));
        $fields['NBZ_USER_USD'] = Tools::getValue('NBZ_USER_USD', trim(Configuration::get('NBZ_USER_USD')));
        $fields['NBZ_PASSWORD_USD'] = Tools::getValue('NBZ_PASSWORD_USD', Configuration::get('NBZ_PASSWORD_USD'));
        $fields['NBZ_PEN'] = Tools::getValue('NBZ_PEN', Configuration::get('NBZ_PEN'));
        $fields['NBZ_USD'] = Tools::getValue('NBZ_USD', Configuration::get('NBZ_USD'));
        $fields['FREE'] = Tools::getValue('FREE', Configuration::get('FREE'));
        $fields['NBZ_CALLBACK'] = Tools::getValue('NBZ_CALLBACK', $this->callback);
        $fields['NBZ_PAYMENT_OPTION_LOGO_SHOW'] = Tools::getValue('NBZ_PAYMENT_OPTION_LOGO_SHOW', Configuration::get('NBZ_PAYMENT_OPTION_LOGO_SHOW'));
        $fields['NBZ_ENABLE_PAYMENTOPTION'] = Tools::getValue('NBZ_ENABLE_PAYMENTOPTION', Configuration::get('NBZ_ENABLE_PAYMENTOPTION'));

        foreach ($languages as $lang) {
            $fields['NBZ_PAYMENT_OPTION_TEXT'][$lang['id_lang']] = Tools::getValue('NBZ_PAYMENT_OPTION_TEXT_'.$lang['id_lang'], Configuration::get('NBZ_PAYMENT_OPTION_TEXT', $lang['id_lang']));
            $fields['NBZ_PAYMENT_OPTION_LOGO'][$lang['id_lang']] = Tools::getValue('NBZ_PAYMENT_OPTION_LOGO_'.$lang['id_lang'], Configuration::get('NBZ_PAYMENT_OPTION_LOGO', $lang['id_lang']));
        }

        return $fields;
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $languages = Language::getLanguages(false);
      $values = array();
      $update_images_values = false;

        if (Tools::isSubmit('submitNiubizModule')) {
            if (!is_dir(dirname(__FILE__).DIRECTORY_SEPARATOR.'img')) {
                @mkdir(dirname(__FILE__).DIRECTORY_SEPARATOR.'img', 0755, true);
            }
            Configuration::updateValue('NBZ_LOGO', Tools::getValue('NBZ_LOGO'));
            Configuration::updateValue('NBZ_ENVIROMENT', Tools::getValue('NBZ_ENVIROMENT'));
            Configuration::updateValue('NBZ_MERCHANTID_PEN', Tools::getValue('NBZ_MERCHANTID_PEN'));
            Configuration::updateValue('NBZ_DEBUG', Tools::getValue('NBZ_DEBUG'));
            Configuration::updateValue('NBZ_USER_PEN', Tools::getValue('NBZ_USER_PEN'));
            Configuration::updateValue('NBZ_PASSWORD_PEN', Tools::getValue('NBZ_PASSWORD_PEN'));
            Configuration::updateValue('NBZ_MERCHANTID_USD', Tools::getValue('NBZ_MERCHANTID_USD'));
            Configuration::updateValue('NBZ_USER_USD', Tools::getValue('NBZ_USER_USD'));
            Configuration::updateValue('NBZ_PASSWORD_USD', Tools::getValue('NBZ_PASSWORD_USD'));
            Configuration::updateValue('NBZ_PEN', Tools::getValue('NBZ_PEN'));
            Configuration::updateValue('NBZ_USD', Tools::getValue('NBZ_USD'));
            Configuration::updateValue('NBZ_CALLBACK', $this->callback);
            Configuration::updateValue('NBZ_PAYMENT_OPTION_LOGO_SHOW', Tools::getValue('NBZ_PAYMENT_OPTION_LOGO_SHOW'));
            Configuration::updateValue('NBZ_ENABLE_PAYMENTOPTION', Tools::getValue('NBZ_ENABLE_PAYMENTOPTION'));

            foreach ($languages as $lang) {
                if (isset($_FILES['NBZ_PAYMENT_OPTION_LOGO_'.$lang['id_lang']])
                    && isset($_FILES['NBZ_PAYMENT_OPTION_LOGO_'.$lang['id_lang']]['tmp_name'])
                    && !empty($_FILES['NBZ_PAYMENT_OPTION_LOGO_'.$lang['id_lang']]['tmp_name'])) {
                    if ($error = ImageManager::validateUpload($_FILES['NBZ_PAYMENT_OPTION_LOGO_'.$lang['id_lang']], 4000000)) {
                        return $error;
                    } else {
                        $ext = substr($_FILES['NBZ_PAYMENT_OPTION_LOGO_'.$lang['id_lang']]['name'], strrpos($_FILES['NBZ_PAYMENT_OPTION_LOGO_'.$lang['id_lang']]['name'], '.') + 1);
                        $file_name = md5($_FILES['NBZ_PAYMENT_OPTION_LOGO_'.$lang['id_lang']]['name']).'.'.$ext;
                        if (!move_uploaded_file($_FILES['NBZ_PAYMENT_OPTION_LOGO_'.$lang['id_lang']]['tmp_name'], dirname(__FILE__).DIRECTORY_SEPARATOR .'img' . DIRECTORY_SEPARATOR.$file_name)) {
                            return $this->displayError($this->trans('An error occurred while attempting to upload the file.', array(), 'Admin.Notifications.Error'));
                        } else {
                            if (Configuration::hasContext('NBZ_PAYMENT_OPTION_LOGO', $lang['id_lang'], Shop::getContext())
                                && Configuration::get('NBZ_PAYMENT_OPTION_LOGO', $lang['id_lang']) != $file_name) {
                                @unlink(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . Configuration::get('NBZ_PAYMENT_OPTION_LOGO', $lang['id_lang']));
                            }

                            $values['NBZ_PAYMENT_OPTION_LOGO'][$lang['id_lang']] = $file_name;
                        }
                    }

                    $update_images_values = true;
                }

                $values['NBZ_PAYMENT_OPTION_TEXT'][$lang['id_lang']] = Tools::getValue('NBZ_PAYMENT_OPTION_TEXT_'.$lang['id_lang']);
            }

            if ($update_images_values) {
                Configuration::updateValue('NBZ_PAYMENT_OPTION_LOGO', $values['NBZ_PAYMENT_OPTION_LOGO']);
            }

            Configuration::updateValue('NBZ_PAYMENT_OPTION_TEXT', $values['NBZ_PAYMENT_OPTION_TEXT']);

            return $this->displayConfirmation($this->trans('The settings have been updated.', array(), 'Admin.Notifications.Success'));
        }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $controller = $this->context->controller;
        if (method_exists($controller, 'registerJavascript')) {
            // PrestaShop 1.7.x - 9.x
            $controller->registerJavascript('module-niubiz-front', 'modules/'.$this->name.'/views/js/front.js', array('position' => 'bottom', 'priority' => 150));
            $controller->registerStylesheet('module-niubiz-front', 'modules/'.$this->name.'/views/css/front.css', array('media' => 'all', 'priority' => 150));
        } else {
            $controller->addJS($this->_path.'views/js/front.js');
            $controller->addCSS($this->_path.'views/css/front.css');
        }
    }

    /**
     * Return payment options (PrestaShop 1.7.6 - 9.x)
     *
     * @param array Hook parameters
     *
     * @return array|null
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $cart = $this->context->cart;
        $customer = Context::getContext()->customer;
        $currency = $this->getActiveCurrency();

        $this->acceptedCurrency = array();
        if (Configuration::get('NBZ_USD')) {
            $this->acceptedCurrency[] = 'USD';
        }
        if (Configuration::get('NBZ_PEN')) {
            $this->acceptedCurrency[] = 'PEN';
        }

        $isModuleConfigured = in_array($currency->iso_code, $this->acceptedCurrency);

        $option = new PaymentOption();
        $option->setModuleName($this->name)
            ->setCallToActionText($isModuleConfigured
                ? Configuration::get('NBZ_PAYMENT_OPTION_TEXT', $this->context->language->id)
                : $this->l('WARNING!! Niubiz OPTION_TEXT is not configured for current languaje'))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true));

        if ($isModuleConfigured && Configuration::get('NBZ_ENABLE_PAYMENTOPTION')) {
            // Only talk to Niubiz when the pop-up is embedded in the checkout summary.
            $amount = number_format($cart->getOrderTotal(true, Cart::BOTH), 2, '.', '');
            $securityKey = $this->securityKey();
            $sessionToken = $securityKey ? $this->createToken($amount, $securityKey) : false;

            if ($sessionToken) {
                $this->setSecurityCookie($cart->id, $securityKey);

                $variables = array(
                    'userTokenId' => $this->userTokenId(),
                    'sessionToken' => $sessionToken,
                    'merchantId' => $this->merchantid,
                    'urlScript' => $this->urlScript,
                    'numOrden' => (int)$cart->id,
                    'monto' => $amount,
                    'firstname' => $customer->firstname,
                    'lastname' => $customer->lastname,
                    'email' => $customer->email,
                );

                // return.php expects nbzheader = base64(id_cart|secure_key)
                $linkReturn = $this->context->link->getModuleLink(
                    $this->name,
                    'return',
                    array('nbzheader' => base64_encode($cart->id.'|'.$customer->secure_key)),
                    true
                );

                $this->context->smarty->assign(array(
                    'logo' => Configuration::get('NBZ_LOGO'),
                    'debug' => Configuration::get('NBZ_DEBUG'),
                    'psVersion' => $this->psVersion,
                    'var' => $variables,
                    'checkTotal' => $this->formatPrice($cart->getOrderTotal(true, Cart::BOTH)),
                    'linkReturn' => $linkReturn,
                ));

                $option->setAdditionalInformation($this->fetch('module:niubiz/views/templates/hook/paymentoption.tpl'));
            }
        }

        if (Configuration::get('NBZ_PAYMENT_OPTION_LOGO_SHOW')) {
            $logo = Configuration::get('NBZ_PAYMENT_OPTION_LOGO', $this->context->language->id);
            if ($logo) {
                $option->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/img/'.$logo));
            }
        }

        return array($option);
    }

    public function hookDisplayPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        $state = $params['order']->getCurrentState();

        $in_array = in_array(
            $state,
            array(
                Configuration::get('PS_OS_PAYMENT'),
                Configuration::get('PS_OS_OUTOFSTOCK'),
                Configuration::get('PS_OS_OUTOFSTOCK_UNPAID')
            )
        );

        if ($in_array) {
            $this->smarty->assign('status', 'ok');
        } else {
            $this->smarty->assign('status', 'failed');
        }

        $cms_condiions = new CMS((int) Configuration::get('PS_CONDITIONS_CMS_ID'), $this->context->language->id);

        $this->context->smarty->assign(array(
            // 'customerName' => $this->context->customer->firstname.' '.$this->context->customer->lastname,
            // 'total_to_pay' => $total_to_pay,
            // 'moneda' => Currency::getCurrencyInstance($this->context->currency->id)->name,
            'link_conditions' => $this->context->link->getCMSLink($cms_condiions, $cms_condiions->link_rewrite, Configuration::get('PS_SSL_ENABLED')),
            // 'products' => $cart->getProducts(),
            'order_id' => $params['order']->id,
            // 'result' => $result,
            // 'total' => $cart->getOrderTotal(),
        ));

        return $this->display(__FILE__, 'confirmation.tpl');
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);
        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Stores the Niubiz security key for the cart (read back by controllers/front/return.php).
     * Same cookie name as before, now HttpOnly + Secure + SameSite (PHP 7.3+ options array, with a PHP 7.2 fallback).
     */
    public function setSecurityCookie($idCart, $securityKey)
    {
        $name = 'niubizkey-'.(int) $idCart;
        $secure = (bool) Tools::usingSecureMode();
        $expires = time() + 3600;
        $path = defined('__PS_BASE_URI__') ? __PS_BASE_URI__ : '/';

        if (PHP_VERSION_ID >= 70300) {
            setcookie($name, $securityKey, array(
                'expires' => $expires,
                'path' => $path,
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ));
        } else {
            setcookie($name, $securityKey, $expires, $path.'; samesite=Lax', '', $secure, true);
        }
        $_COOKIE[$name] = $securityKey;
    }

    /**
     * Niubiz credentials (access key / secret key) -> security token.
     * Returns false instead of die() so a misconfiguration can never white-screen the checkout.
     */
    public function securityKey()
    {
        $currency = $this->getActiveCurrency();

        if ($this->vsauser == '' || $this->vsapassword == '') {
            PrestaShopLogger::addLog('NIUBIZ :: credentials not found for currency '.$currency->iso_code, 3);
            return false;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->security_api);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $this->vsauser.':'.$this->vsapassword);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $key = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($key === false || $key === '' || $key === 'Unauthorized access') {
            PrestaShopLogger::addLog('NIUBIZ :: security API error: '.($error ? $error : (string) $key), 3);
            return false;
        }

        return $key;
    }

    /**
     * Creates the Niubiz session token. Returns false on failure (details go to the PrestaShop log).
     */
    public function createToken($amount, $key)
    {
        $clientIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

        $request_body = json_encode(array(
            'amount' => (float) $amount,
            'channel' => 'web',
            'antifraud' => array(
                'clientIp' => $clientIp,
                'merchantDefineData' => array(
                    'MDD1' => 'web',
                    'MDD2' => 'Canl',
                    'MDD3' => 'Canl',
                ),
            ),
        ));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->session_api);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: '.$key));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request_body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        $json = json_decode((string) $response);
        if (!isset($json->sessionKey)) {
            PrestaShopLogger::addLog('NIUBIZ :: session API error ('.$this->session_api.'): '.($error ? $error : (string) $response), 3);
            return false;
        }

        return $json->sessionKey;
    }

    /**
     * Random UUID v4-like identifier (mt_srand((double) microtime()) was deprecated and not secure).
     */
    public function userTokenId()
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = strtoupper(bin2hex($bytes));

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20, 12);
    }
}
