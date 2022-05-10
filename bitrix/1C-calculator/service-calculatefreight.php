<?php

/**
 * - Класс для рассчета средней стоимости городского калькулятора
 */

namespace ITProfit\Caclulator\Webservices;

use ITProfit\Tools\Helper;

class CalculateFreight extends Main
{

    protected static $pathRequest = '/calculatefreight/';


    static function process($data)
    {
        parent::$pathRequest = self::$pathRequest;

        $res = parent::put($data);

        if (empty($res['rates'])) {
            return false;
        }

        return $res;
    }
}
