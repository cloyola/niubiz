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
class NiubizReturnModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        if ((Tools::isSubmit('nbzheader') == false)) {
            $message = 'Error en modulo de pago: Algo ha fallado, intente nuevamente';
            Tools::redirect(Context::getContext()->link->getModuleLink($this->module->name, 'validation', ['nbz_error' => base64_encode($message)]));
        }
        
        $params_decoded = base64_decode(Tools::getValue('nbzheader'));
        $params = explode("|", $params_decoded);

        if (count($params) < 2) {
            Tools::redirect($this->context->link->getPageLink('order', true));
        }

        $module_name = $this->module->displayName;

        $id_cart = (int) $params[0];
        $secure_key = $params[1];
        $transactionToken = Tools::getValue('transactionToken');
        $currency_id = (int) Context::getContext()->currency->id;

        $cart = new Cart((int) $id_cart);
        $customer = new Customer((int) $cart->id_customer);

        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);

        // VALIDATIONS
        if ($cart->id == 0
            || $cart->id_customer == 0
            || $cart->id_address_delivery == 0
            || $cart->id_address_invoice == 0
            || $transactionToken == ''
            || $secure_key != $customer->secure_key
            || !$this->module->active) {
            
            Tools::redirect($this->context->link->getPageLink('order', true));
        }

        $authorized = false;

        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'niubiz') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->module->translate('This payment method is not available.', [], 'Modules.Niubiz.Return'));
        }

        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect($this->context->link->getPageLink('order', true));
        }


        // NIUBIZ VALIDATION
        $cookieName = 'niubizkey-'.(int) $cart->id;
        if (empty($_COOKIE[$cookieName])) {
            $message = 'Error en modulo de pago: la sesión de pago expiró, intente nuevamente';
            Tools::redirect($this->context->link->getModuleLink($this->module->name, 'validation', ['nbz_error' => base64_encode($message)]));
        }

        $authNiubizResponse = $this->authorization($_COOKIE[$cookieName], $total, $transactionToken, $cart->id, $cart->id_currency);
        if (!is_array($authNiubizResponse)) {
            $authNiubizResponse = [];
        }

        $dataInput = isset($authNiubizResponse['dataMap']) ? 'dataMap' : 'data';

        $sal = [];

        if (isset($_POST['transactionToken']) && isset($_POST['url']) && $this->isSafePagoEfectivoUrl($_POST['url'])) {
            $ps_os_payment = Configuration::get('NBZ_STATE_WAITING_CAPTURE');
            $message = "Esperando confirmación de PagoEfectivo";
        } else if (isset($authNiubizResponse[$dataInput]) && $authNiubizResponse[$dataInput]['ACTION_CODE'] == "000") {
            $ps_os_payment = Configuration::get('PS_OS_PAYMENT');
            $message = "Pago válido";
        } else {
            $errorCode = isset($authNiubizResponse['errorCode']) ? $authNiubizResponse['errorCode'] : '';
            $errorMessage = isset($authNiubizResponse['errorMessage']) ? $authNiubizResponse['errorMessage'] : 'Sin respuesta de Niubiz';
            $message = 'Error '.$errorCode.': '.$errorMessage;
            Tools::redirect(Context::getContext()->link->getModuleLink($this->module->name, 'validation', ['nbz_error' => base64_encode($message)]));
        } 



        // PRESTASHOP VALIDATION
        $this->module->validateOrder($cart->id, $ps_os_payment, $total, $module_name, $message, array(), $currency_id, false, $secure_key);

        $order = new Order($this->module->currentOrder);
        $order_id = $order->id;

        if ($order_id && ($secure_key == $customer->secure_key)) {
            
            unset($_COOKIE[$cookieName]);

            if ($ps_os_payment == Configuration::get('NBZ_STATE_WAITING_CAPTURE')) {
                $sal['data']['id_order'] = (int)$order_id;
                $urlPagoEfectivo = Tools::getValue('url');
                $explodeUrlPagoEfectivo = explode('/', $urlPagoEfectivo);
                $operationNumber = isset($explodeUrlPagoEfectivo[3]) ? strtolower(substr($explodeUrlPagoEfectivo[3], 0, 36)) : '';

                Db::getInstance()->insert('niubiz_pagoefectivo', [
                    'id_order' => (int)$order_id,
                    'id_cart' => (int)$cart->id,
                    'id_customer' => (int)$customer->id,
                    'operationNumber' => pSQL($operationNumber),
                    'channel' => pSQL(Tools::getValue('channel')),
                    'cip' => pSQL(Tools::getValue('transactionToken')),
                    'customerEmail' => pSQL(Tools::getValue('customerEmail')),
                    'url' => pSQL($urlPagoEfectivo),
                ]);
                Tools::redirect($urlPagoEfectivo);
            } else {
                $sal['data']['id_order'] = (int)$order_id;
                $sal['data']['id_cart'] = (int)$cart->id;
                $sal['data']['id_customer'] = (int)$customer->id;
                $data = $authNiubizResponse[$dataInput];
                $sal['data']['pan'] = pSQL(isset($data['CARD']) ? $data['CARD'] : '');
                $sal['data']['numorden'] = pSQL(isset($data['TRACE_NUMBER']) ? $data['TRACE_NUMBER'] : '');
                $sal['data']['dsc_cod_accion'] = pSQL(isset($data['ACTION_DESCRIPTION']) ? $data['ACTION_DESCRIPTION'] : '');
                $sal['data']['dsc_eci'] = pSQL(isset($data['BRAND']) ? $data['BRAND'] : '');
                $sal['data']['transactionToken'] = pSQL($transactionToken);
                $sal['data']['aliasName'] = pSQL(isset($data['SIGNATURE']) ? $data['SIGNATURE'] : '');

                Db::getInstance()->insert('niubiz_log', $sal['data']);
    
                $rdc = 'index.php?controller=order-confirmation&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id;

                Tools::redirect($rdc.'&id_order='.(int)$this->module->currentOrder.'&key='.urlencode($secure_key));
            }
            
        } else {
            $this->errors[] = $this->module->translate('An error occured. Please contact the merchant to have more informations', [], 'Modules.Niubiz.Return');

            $this->setTemplate('module:niubiz/views/templates/front/error.tpl');
        }
    }

    public function authorization($key, $amount, $transactionToken, $purchaseNumber, $currencyId)
    {
        $currency = new Currency($currencyId);

        $header = [
            "Content-Type: application/json",
            "Authorization: $key"
        ];

        $request_body = [
            "antifraud" => null,
            "captureType" => "manual",
            "cardHolder" => [
                "documentNumber" => "44444444",
                "documentType" => "1"
            ],
            "channel" => "web",
            "countable" => true,
            "order" => [
                "amount" => (float) $amount,
                "tokenId" => $transactionToken,
                "purchaseNumber" => $purchaseNumber,
                "currency" => $currency->iso_code,
                "productId" => "100"
            ],
            'recurrence' => null,
            'sponsored' => null
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->module->authorization_api);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_body));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        if ($response === false) {
            PrestaShopLogger::addLog('NIUBIZ :: authorization API error: '.curl_error($ch), 3);
        }
        curl_close($ch);

        return json_decode((string) $response, true);
    }

    /**
     * The PagoEfectivo redirect URL comes from the POST sent by the Niubiz checkout: only follow https URLs.
     */
    private function isSafePagoEfectivoUrl($url)
    {
        return is_string($url)
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }
}
