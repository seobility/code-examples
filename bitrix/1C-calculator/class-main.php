<?php

/**
 * - Класс родитель для работы с веб сервисами
 */

namespace ITProfit\Caclulator\Webservices;

use ITProfit\Caclulator\Calculator_Logs;
use ITProfit\Caclulator\Logger;
use ITProfit\Tools\Helper;

class Main
{

    /**
     * - основной адрес (с портом)
     * @var string
     */
    private static $baseUrl = '';

    /**
     * - Базовый путь в 1C (общий для всех веб сервисов)
     * @var string
     */
    private static $basePath = '';

    /**
     * - токен для подключения к веб сервису
     * @var string
     */
    private static $apiToken = '';

    /**
     * - Массив соответствия услуг/оборудования/типа кузова с ключами в 1С
     * @var array
     */
    protected static $equipmentsSlugs = [];

    /**
     * - ID тарифа межгорода в 1C
     * @var string
     */
    protected static $intercityTarId = '';

    /**
     * - Путь для запроса
     * @var string
     */
    protected static $pathRequest = '';

    /**
     * - Время ожидания в секундах
     * @var int
     */
    protected static $timeout = 60;



    /**
     * - Инициализирует класс
     * @param array $params
     */
    static function setup($params)
    {
        if (isset($params['baseUrl'])) {
            self::$baseUrl = $params['baseUrl'];
        }

        if (isset($params['basePath'])) {
            self::$basePath = $params['basePath'];
        }

        if (isset($params['apiToken'])) {
            self::$apiToken = $params['apiToken'];
        }

        if (isset($params['equipmentsSlugs'])) {
            self::$equipmentsSlugs = $params['equipmentsSlugs'];
        }

        if (isset($params['intercityTarId'])) {
            self::$intercityTarId = $params['intercityTarId'];
        }
    }


    /**
     * - Преобразует дату в формат 2021-06-08T11:51:18
     */
    protected static function formatDate($date = false, $utc = false, $format = null)
    {
        $defDate = '0001-01-01T00:00:00';

        if (!$date) {
            return $defDate;
        }

        $timeZone = date_default_timezone_get();

        if ($utc) {
            date_default_timezone_set('UTC');
        }

        $timestamp = is_string($date) ? strtotime($date) : $date;

        if (!$format) {
            $newDate = date('Y-m-d', $timestamp) . 'T' . date('H:i:s', $timestamp);
        } else {
            $newDate = date($format, $timestamp);
        }

        if ($utc) {
            date_default_timezone_set($timeZone);
        }

        return $newDate;
    }


    /**
     * - Получет name услуги или оборудования для передачи в 1C
     */
    protected static function getServiceEquipmentSlug($sectionCode)
    {
        return Helper::getArr(self::$equipmentsSlugs, $sectionCode, '');
    }



    protected static function get($params = [], $returnResult = true, $orderId = 0)
    {
        return self::sendRequest('GET', $params, $returnResult, $orderId);
    }


    protected static function post($params = [], $returnResult = true, $orderId = 0)
    {
        return self::sendRequest('POST', $params, $returnResult, $orderId);
    }


    protected static function put($params = [], $returnResult = true, $orderId = 0)
    {
        return self::sendRequest('PUT', $params, $returnResult, $orderId);
    }


    /**
     * - Отправляет зарос в 1С
     * @param string $method
     * @param array $data массив данных для передачи
     *   если метод GET данные будут добавлены к адресу как GET параметры
     * @param bool $returnResult вернуть результат
     *   если установлен - вернет результат фукнции json_decode
     *   если false - вернет результат проверки кода ответа на 200
     * @param string|int $orderId ID заявки. Используется для дебага
     * 
     * @return array|boolean
     */
    private static function sendRequest($method = 'GET', $data = [], $returnResult = true, $orderId = 0)
    {
        $url = self::getUrl();
        $method = strtoupper($method);

        if ($method === 'GET' && $data) {
            $url .= '?' . http_build_query($data);
        }

        $ch = curl_init($url);

        $chParams = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::$timeout,
            CURLOPT_TIMEOUT => self::$timeout
        ];

        $headers = [];

        if ($method !== 'GET') {
            if ($method === 'POST') {
                $chParams[CURLOPT_POST] = true;
            } else {
                $chParams[CURLOPT_CUSTOMREQUEST] = $method;
            }
        }

        if ($data && $method !== 'GET') {
            $chParams[CURLOPT_POSTFIELDS] = json_encode($data, JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json';
        }

        $headers[] = 'X-API-TOKEN: ' . self::$apiToken;
        $chParams[CURLOPT_HTTPHEADER] = $headers;

        curl_setopt_array($ch, $chParams);

        $res = curl_exec($ch);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);

        curl_close($ch);

        // Logger::save('1C_API', __METHOD__, basename(preg_replace("/\?.*$/", '', $url)), [
        //     'URL' => $url,
        //     'METHOD' => $method,
        //     'REQUEST_BODY' => $method === 'GET' ? [] : $data,
        //     'RESPONSE' => $res,
        //     'CURL_INFO' => $info,
        //     'CURL_ERROR' => $error
        // ]);

        $debug = [
            'URL' => $url,
            'METHOD' => $method,
            'REQUEST_BODY' => $method === 'GET' ? [] : $data,
            'RESPONSE' => $res,
            'CURL_INFO' => $info,
            'CURL_ERROR' => $error
        ];

        $debugRequest = basename(preg_replace("/\?.*$/", '', $url));

        Calculator_Logs::add(
            'webservice',
            ($info['http_code'] != 200) ? 'ERROR' : 'SUCCESS',
            __CLASS__,
            $debugRequest,
            $orderId,
            '',
            $debug
        );

        $res = @json_decode($res, true);

        $fnRes = null;

        if ($returnResult) {
            $fnRes = $res ? $res : [];
        } else {
            $fnRes = $info['http_code'] === 200;
        }

        return $fnRes;
    }


    private static function getUrl()
    {
        return self::$baseUrl . self::$basePath . self::$pathRequest;
    }
}
