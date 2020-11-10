//<?php
/**
 * Payment Ckassa
 *
 * Ckassa payments processing
 *
 * @category    plugin
 * @version     0.1
 * @author      dzhuryn
 * @internal    @events OnRegisterPayments
 * @internal    @properties &title=Name;text; &shopToken=Organization ID (shopToken);text; &secKey=Secret key (secKey);text; &serviceCode=Service type (servCode);text; &certificateFile=Path to the certificate;text; &certificatePassword=Password to the certificate;text; &test=Test access;list;Yes==1||No==0;1
 * @internal    @modx_category Commerce
 * @internal    @disabled 0
 * @internal    @installset base
*/

if (empty($modx->commerce) && !defined('COMMERCE_INITIALIZED')) {
    return;
}

switch ($modx->event->name) {
    case 'OnRegisterPayments': {
        $class = new \Commerce\Payments\CkassaPayment($modx, $params);

        if (empty($params['title'])) {
        $lang = $modx->commerce->getUserLanguage('ckassa');
        $params['title'] = $lang['ckassa.caption'];
        }
        $modx->commerce->registerPayment('ckass', $params['title'], $class);
    break;
    }
}
