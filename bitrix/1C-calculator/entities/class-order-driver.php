<?php

/**
 * - Класс для работы с заявкой водителя
 */

namespace ITProfit\Caclulator\CrmOrders;

use ITProfit\Tools\Helper;
use ITProfit\Caclulator\Options;

class Order_Driver extends Order
{

    /**
     * - Сслыка на заявку для водителя
     * @var string
     */
    public $urlDriver;

    /**
     * - Данные логиста
     * @var Logistic
     */
    public $logistic = null;

    /**
     * - Данные по услугам
     * @var Calculation_Item[]
     */
    public $services = [];

    /**
     * - данные путевого листа
     * @var Vaybill
     */
    public $vaybill = null;

    /**
     * - Статус водителя
     * - Свободен|Отказ
     * @param string
     */
    public $statusDriver = '';

    /**
     * - Массив дополнительных услуг и оборудования
     * [
     *   'name' => 'Наименование для вывода',
     *   'param' => 'Ярлык для передачи на сервер',
     *   'type' => 'Тип' equipment|service
     * ]
     */
    public $additionalServices = [];

    /**
     * - Дополнительные требования к водителю
     * @var Additional_Terms
     */
    public $AdditionalTerms = null;


    /**
     * @param array $params
     */
    function __construct($params)
    {
        parent::__construct($params);

        $this->urlDriver = Order_Driver::getUrlDriver($this->number);

        if (!empty($params['vaybill'])) {
            $this->vaybill = new Vaybill($params['vaybill']);
        }

        if (!empty($params['services'])) {
            foreach ($params['services'] as $item) {
                $this->services[] = new Calculation_Item($item);
            }
        }

        if (!empty($params['logistic'])) {
            $this->logistic = new Logistic($params['logistic']);
        }

        if (!empty($params['statusdriver'])) {
            $this->statusDriver = $params['statusdriver'];
        }

        if (!empty($params['AdditionalTerms'])) {
            $this->AdditionalTerms = new Additional_Terms($params['AdditionalTerms']);
        }

        $this->setServices();
    }


    private function setServices()
    {
        $params = Options::getCarAdditionalParams();
        $res = [];
        foreach ($params['equipment'] as $item) {
            $res[] = [
                'name' => $item['NAME'],
                'param' => $item['CODE'],
                'type' => 'equipment'
            ];
        }
        $res[] = [
            'name' => 'Водитель грузчик',
            'param' => 'loader_driver',
            'type' => 'service'
        ];

        $this->additionalServices = $res;
    }


    static function getUrlDriver($number)
    {
        return Helper::getSiteUrl() . 'driver/?code=' . $number;
    }
}
