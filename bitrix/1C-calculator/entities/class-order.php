<?php

/**
 * - Класс для работы с заявкой для клиента
 */


namespace ITProfit\Caclulator\CrmOrders;

use ITProfit\Caclulator\Orders\Moscow;
use ITProfit\Tools\Helper;

class Order
{

    public $isCrm = true;

    /**
     * - Ссылка для детального просмотра
     * @var string
     */
    public $url;

    /**
     * - Номер заказа
     * @var string
     */
    public $number = '';

    /**
     * - Дата формирования заказа
     * @var string
     */
    public $date = '';

    /**
     * - Источник заказа
     * - (Сайт, Телефонный звонок, Электронное письмо)
     * @var string
     */
    public $source = '';

    /**
     * - Данные менеджера
     * @var Manager
     */
    public $manager = null;

    /**
     * - Данные организации Шмель
     * @var Organization
     */
    public $organization = null;

    /**
     * - Тип клиента
     * - 0 Физ лицо
     * - 1 ИП
     * - 2 Юр. Лицо
     * @var int
     */
    public $clientType = 0;

    /**
     * - Данные организации клиента
     * @var Organization
     */
    public $client = null;

    /**
     * - Данные контактного лица
     * @var Client_Contact
     */
    public $contact = null;

    /**
     * - Данные об оплате
     * @var Payment
     */
    public $payment = null;

    /**
     * - Данные маршрута
     * @var Route
     */
    public $route = null;

    /**
     * - Данные по грузу
     * @var Cargo
     */
    public $cargo = null;

    /**
     * - Данные по транспорту
     * @var Transport
     */
    public $transport = null;

    /**
     * - Статус заявки
     * - (Новый|В работе|Выполнен|Отказ|Закрыт)
     * @var string
     */
    public $status = '';

    /**
     * - Данные по расчету
     * @var Calculation
     */
    public $calculation = null;

    /**
     * - HTML договора
     * @var string
     */
    public $contractHtml = '';

    /**
     * - Данные по отзыву
     * @var Review
     */
    public $review = null;

    /**
     * - Массив файлов загруженные пользователем
     * @var File[]
     */
    public $userFiles = [];

    /**
     * - Масси файлов загруженные компанией
     * @var File[]
     */
    public $companyFiles = [];

    /**
     * - Вариант работы (Только для юр лиц)
     * - 0  - Не установлено
     * - 1  - Договор заявка
     * - 2  - Договор оферты
     * @var int
     */
    public $workoption = 0;


    /**
     * @param array $params
     */
    function __construct($params)
    {
        if (!empty($params['number'])) {
            $this->number = $params['number'];
        }

        $this->url = Order::getDetailUrl($this->number);

        if (!empty($params['date'])) {
            $this->date = Order::formatDate($params['date']);
        }

        if (!empty($params['source'])) {
            $this->source = $params['source'];
        }

        if (!empty($params['manager'])) {
            $this->manager = new Manager($params['manager']);
        }

        if (!empty($params['organization'])) {
            $this->organization = new Organization($params['organization']);
        }

        if (!empty($params['client_type']) || $params['client_type'] === 0) {
            $this->clientType = $params['client_type'];
        }

        if (!empty($params['client'])) {
            $this->client = new Organization($params['client']);
        }

        if (!empty($params['contact'])) {
            $this->contact = new Client_Contact($params['contact']);
        }

        if (!empty($params['payment'])) {
            $this->payment = new Payment($params['payment']);
        }

        if (!empty($params['route'])) {
            $this->route = new Route($params['route']);
        }

        if (!empty($params['cargo'])) {
            $this->cargo = new Cargo($params['cargo']);
        }

        if (!empty($params['transport'])) {
            $this->transport = new Transport($params['transport']);
        }

        if (!empty($params['status'])) {
            $this->status = $params['status'];
        }

        if (!empty($params['calculation'])) {
            $this->calculation = new Calculation($params['calculation']);
        }

        if (!empty($params['review']) && !empty($params['review']['description'])) {
            $this->review = new Review($params['review']);
        }

        if (!empty($params['files'])) {
            $this->setupFiles($params['files']);
        }

        if (!empty($params['workoption'])) {
            $this->workoption = $params['workoption'];
        }

        $this->contractHtml = Helper::getTemplate('template-contract', ['order' => $this], true);
    }


    /**
     * @param array $files массив файлов
     */
    private function setupFiles($files)
    {
        $userFiles = [];
        $companyFiles = [];

        foreach ($files as $params) {
            if (empty($params['file'])) {
                continue;
            }

            $file = $params['file'];

            if ($file['type'] == 0) {
                $userFiles[] = new File($file);
            } else {
                $companyFiles[] = new File($file);
            }
        }

        $this->userFiles = $userFiles;
        $this->companyFiles = $companyFiles;
    }


    /**
     * - Переводит дату в формат для фронта
     * @param string $date дата в формате CRM (ДДММГГГГЧЧмм)
     * 
     * @return string
     */
    static function formatDate($date)
    {
        //              день    месяц    год      часы     минуты
        preg_match("/([\d]{2})([\d]{2})([\d]{4})([\d]{2})?([\d]{2})?/", $date, $matches);

        $res = "{$matches[1]}.{$matches[2]}.{$matches[3]}";

        if (!empty($matches[4]) && !empty($matches[5])) {
            $res .= ", {$matches[4]}:{$matches[5]}";
        }

        return $res;
    }


    /**
     * - Формирует цену
     * 
     * @param float|int $price
     * @param bool $currency
     * 
     * @return string
     */
    static function formatPrice($price, $currency = false)
    {
        $res = number_format($price, 2, '.', ' ');
        if (preg_match("/\.00$/", $res)) {
            $res = preg_replace("/\.00$/", '', $res);
        }
        if ($currency) {
            return $res . ' ₽';
        }
        return $res;
    }


    /**
     * @param string $orderId
     */
    static function getDetailUrl($orderId)
    {
        return Moscow::getDetailedUrl($orderId);
    }


    function isIndividual()
    {
        return $this->clientType === 0;
    }


    function isIp()
    {
        return $this->clientType === 1;
    }


    function isLegal()
    {
        return $this->clientType === 2;
    }

    function isContract()
    {
        return $this->workoption === 1;
    }

    function isOffer()
    {
        return $this->workoption === 2;
    }

    /**
     * - Получает e-mail клиента
     * @return string
     */
    function getClientEmail()
    {
        if (!$this->contact) {
            return '';
        }
        return $this->contact->getEmail();
    }
}
