<?php

namespace Commerce\Payments;

use Ckassa\MainShop;
use Ckassa\TestMainShop;

class CkassaPayment extends Payment implements \Commerce\Interfaces\Payment
{
    private $shopToken;
    private $secKey;
    private $serviceCode;
    private $certificateFile;
    private $certificatePassword;
    private $test;
    private $shop;


    public function __construct($modx, array $params = [])
    {
        parent::__construct($modx, $params);
        $this->lang = $modx->commerce->getUserLanguage('ckassa');

        $this->shopToken = $this->getSetting('shopToken');
        $this->secKey = $this->getSetting('secKey');
        $this->serviceCode = $this->getSetting('serviceCode');
        $this->certificateFile = MODX_BASE_PATH . $this->getSetting('certificateFile');
        $this->certificatePassword = $this->getSetting('certificatePassword');
        $this->test = $this->getSetting('test');

        if ($this->test) {
            $this->shop = new TestMainShop($this->secKey, $this->shopToken, $this->certificateFile, $this->certificatePassword);
        } else {
            $this->shop = new MainShop($this->secKey, $this->shopToken, $this->certificateFile, $this->certificatePassword);
        }
    }

    public function getMarkup()
    {
        if (empty($this->shopToken) || empty($this->secKey) || empty($this->serviceCode) || empty($this->certificateFile) || empty($this->certificatePassword)) {
            return '<span class="error" style="color: red;">' . $this->lang['ckassa.error_empty_config'] . '</span>';
        }
    }


    public function getPaymentLink()
    {
        $processor = $this->modx->commerce->loadProcessor();
        $order = $processor->getOrder();

        $order_id = $order['id'];

        $amount = $order['amount'] * 100;
        $currency = ci()->currency->getCurrency($order['currency']);
        $payment = $this->createPayment($order['id'], ci()->currency->convertToDefault($order['amount'], $currency['code']));


        try {
            $CkassaPayment = $this->shop->createAnonymousPayment([
                'serviceCode' => $this->serviceCode,
                'amount' => $amount,
                'orderId' => $order_id,
                'comission' => 0,
                'enableSMSConfirm' => 'true',
                'properties' => [
                    ['name' => 'ФИО', 'value' => $order['name']],
                    ['name' => 'НОМЕР_ЗАКАЗА', 'value' => $order_id],
                    ['name' => 'НОМЕР_ТЕЛЕФОНА', 'value' => $order['phone']],
                    ['name' => 'ID_ПЛАТЕЖА', 'value' => $payment['id']],
                    ['name' => 'HASH_ПЛАТЕЖА', 'value' => $payment['hash']],
                ],
            ]);
            $url = $CkassaPayment->getPayUrl();


            $payment['meta']['ckass_payment_id'] = $CkassaPayment->getRegPayNum();
            $processor->savePayment($payment);
            return $url;

        } catch (\Exception $e) {
            $this->modx->logEvent(0, 3, 'Link is not received: ' . $e->getMessage(), 'Commerce Ckass Payment');
            return false;
        }

    }

    public function handleCallback()
    {

        $data = json_decode(file_get_contents('php://input'), true);
        if(empty($data['regPayNum'])){
            $this->modx->logEvent(0, 3, 'Payment process failed: regPayNum empty', 'Ckassa payment');
            return false;
        }
        $paymentStatus = [];
        try {
            $paymentStatus = $this->shop->getPaymentInfo($data['regPayNum']);
        }
        catch (\Exception $e) {
            $this->modx->logEvent(199, 3, 'Payment info get failed: '.$e->getMessage(), 'Ckassa payment');
            return false;
        }

        if (empty($paymentStatus['state']) || $paymentStatus['state'] != 'payed') {
            $this->modx->logEvent(199, 3, 'Payment error - '.json_encode($paymentStatus), 'Ckassa payment');
            return false;
        }


        //ищем платеж по $data['regPayNum']
        $eRegPayNum = $this->modx->db->escape($data['regPayNum']);
        $paymentsIds = $this->modx->db->makeArray($this->modx->db->select('id,order_id', $this->modx->getFullTableName('commerce_order_payments'), '`meta` like \'%"ckass_payment_id":"'.$eRegPayNum.'"%\''));
        if(count($paymentsIds) === 1){
            $paymentId = $paymentsIds[0]['id'];
            $orderId = $paymentsIds[0]['order_id'];
        }
        else{
            $this->modx->logEvent(199, 3, 'Payment search by regnum error - '.json_encode($paymentsIds), 'Ckassa payment');
            return false;
        }

        //доп проверка
        if ($paymentStatus['state'] == 'payed') {
            try {
                $this->modx->commerce->loadProcessor()->processPayment($paymentId, floatval($paymentStatus['totalAmount']) * 0.01);

                return true;
            } catch (\Exception $e) {
                $this->modx->logEvent(0, 3, 'Payment process failed: ' . $e->getMessage(), 'Ckassa payment');
                return false;
            }
        }
        return false;
    }
}
