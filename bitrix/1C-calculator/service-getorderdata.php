<?php

/**
 * - Получает данные по заказу
 */

namespace ITProfit\Caclulator\Webservices;

use ITProfit\Tools\Helper;

class GetOrderData extends Main
{

    protected static $pathRequest = '/getorderdata/';


    /**
     * @param string $order ID заказа на сайте
     * 
     * @return \ITProfit\Caclulator\CrmOrders\Order
     */
    static function process($orderId)
    {
        parent::$pathRequest = self::$pathRequest;

        if (Helper::getConst('TEST_CRM_ORDER')) {
            $res = json_decode(file_get_contents(__DIR__ . '/service-getorderdata-order.json'), true);
            $res['number'] = (string)$orderId;
            return new \ITProfit\Caclulator\CrmOrders\Order($res);
        }

        $res = parent::get([
            'number' => (string)$orderId
        ], true, $orderId);

        return new \ITProfit\Caclulator\CrmOrders\Order($res);
    }
}
