<?php

/**
 * - Основной класс для работы с запросами api
 */

namespace Vnet\Api\Request;

use Vnet\Core\Helper;
use Vnet\Request_Logger;

class Main
{

    /**
     * - Кешировать запросы в БД
     */
    private static $cache = true;
    private static $prevCache = null;

    /**
     * - Сохранять отправленные запросы в файл _DEBUG_.requests 
     */
    private static $debugRequests = false;

    /**
     * - Базовый адрес апи
     * @var string
     */
    public static $urlBase = API_BASE;

    /**
     * - Базовый адрес апи для бизнеса
     * @var string
     */
    public static $urlBaseBusiness = API_CASHBACK_BASE;

    /**
     * - Пути запросов для бизнеса
     */
    public static $pathsBusiness = [
        'business/registration'
    ];

    /**
     * - Ссылка для статических файлов
     */
    public static $urlStatic = API_STATIC_BASE;

    /**
     * - Время ожидания в секундах
     * @var int
     */
    private static $timeout = 15;

    /**
     * - Версия приложения
     * @var string
     */
    private static $appVersion = '1.0';

    /**
     * - Заголовки по умолчанию
     * @var array
     */
    private static $defHeaders = null;


    /**
     * @see self::$send
     * 
     * @return Api_Response
     */
    protected static function get($path, $getParams = [], $headers = [], $token = null, $isBusinessRequest = false)
    {
        return self::send($path, 'GET', $getParams, [], $headers, $token, false, $isBusinessRequest);
    }

    /**
     * - Скачивает файл
     * @return array ['contentType' => 'тип содержимого', 'file' => 'путь к файлу']
     * @return false ошибка при загрузке
     */
    protected static function getFile($path, $getParams = [], $headers = [], $token = null, $isBusinessRequest = false)
    {
        $defHeaders = self::getDefHeaders($token);
        $headers = array_merge($defHeaders, $headers);

        foreach ($headers as $key => $val) {
            $headers[$key] = "$key: $val";
        }

        $url = self::getUrl($path, $getParams, $isBusinessRequest);

        $ch = curl_init($url);

        $fp = tmpfile();

        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => 1,
            // CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER => $headers
            // CURLOPT_HEADER => 1
        ]);

        curl_exec($ch);
        $info = curl_getinfo($ch);

        curl_close($ch);

        $type = $info['content_type'];
        $status = $info['http_code'];

        if ($status !== 200) {
            fclose($fp);
            return false;
        }

        $ext = Helper::mimeToExtension($type);

        $file = THEME_PATH . 'temp/' . md5(date('d-m-Y H:i:s', time()) . Helper::randomStr(5));

        if ($ext) {
            $file .= '.' . $ext;
        }

        copy(stream_get_meta_data($fp)['uri'], $file);

        fclose($fp);

        return ['contentType' => $type, 'file' => $file];
    }

    /**
     * @see self::$send
     * 
     * @return Api_Response
     */
    protected static function post($path, $body = [], $headers = [], $getParams = [], $token = null)
    {
        return self::send($path, 'POST', $getParams, $body, $headers, $token);
    }


    /**
     * @see self::$send
     * - Загружает файл на сервер
     * 
     * @return Api_Response
     */
    protected static function postFile($path, $file, $key, $token = null)
    {
        $curlFile = self::makeCurlFile($file);
        $data = [$key => $curlFile];
        return self::send($path, 'POST', [], $data, [], $token, true);
    }

    /**
     * @see self::$send
     * 
     * @return Api_Response
     */
    protected static function put($path, $body = [], $headers = [], $getParams = [], $token = null, $isBusinessRequest = false)
    {
        return self::send($path, 'PUT', $getParams, $body, $headers, $token, false, $isBusinessRequest);
    }

    /**
     * @see self::$send
     * 
     * @return Api_Response
     */
    protected static function delete($path, $getParams = [], $headers = [], $token = null)
    {
        return self::send($path, 'DELETE', $getParams, [], $headers, $token);
    }


    /**
     * @return Api_Response[]
     */
    protected static function multiSend(...$args)
    {
        $arCh = [];
        $arMethods = [];
        $arHeaders = [];
        $arBody = [];

        foreach ($args as $params) {
            $chParams = self::getChParams(...$params);

            $method = 'get';
            if (isset($params[1])) {
                $method = $params[1];
            }
            $arMethods[] = $method;

            $body = [];
            if (isset($chParams[CURLOPT_POSTFIELDS])) {
                $body = $chParams[CURLOPT_POSTFIELDS];
            }
            $arBody[] = $body;

            $headers = [];
            if (isset($chParams[CURLOPT_HTTPHEADER])) {
                $headers = $chParams[CURLOPT_HTTPHEADER];
            }
            $arHeaders[] = $headers;

            $ch = curl_init();
            curl_setopt_array($ch, $chParams);
            $arCh[] = $ch;
        }

        $mh = curl_multi_init();

        foreach ($arCh as $ch) {
            curl_multi_add_handle($mh, $ch);
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
        } while ($running);

        foreach ($arCh as $ch) {
            curl_multi_remove_handle($mh, $ch);
        }

        curl_multi_close($mh);

        $arResult = [];

        foreach ($arCh as $i => $ch) {
            $response = curl_multi_getcontent($ch);
            $info = curl_getinfo($ch);
            $error = curl_error($ch);
            Request_Logger::add(
                $info,
                $error,
                'business',
                $arMethods[$i],
                $response,
                $arHeaders[$i],
                $arBody[$i]
            );
            $arResult[] = new Api_Response($response, $info['http_code'], $error);
        }

        return $arResult;
    }


    /**
     * - Отправляет запрос по API
     * @param string $path путь запроса
     * @param string $method [optional] тип запроса GET|POST|PUT|DELETE
     * @param array $getParams [optional] массив параметров GET
     * @param array $body [optional] тело запроса
     * @param array $headers [optional] массив дополнительных заголовков
     * @param null|string $token
     * 
     * @return Api_Response ответ с API
     */
    protected static function send($path, $method = 'GET', $getParams = [], $body = [], $headers = [], $token = null, $isFile = false, $isBusinessRequest = false)
    {
        $ch = curl_init();
        $chParams = self::getChParams(
            $path,
            $method,
            $getParams,
            $body,
            $headers,
            $token,
            $isFile,
            $isBusinessRequest
        );
        $url = self::getUrl($path, $getParams, $isBusinessRequest);
        $method = strtoupper($method);

        curl_setopt_array($ch, $chParams);

        $debugStr = date('d-m-Y H:i:s', time()) . " $method $url";

        $response = curl_exec($ch);

        $info = curl_getinfo($ch);
        $error = curl_error($ch);

        curl_close($ch);

        Request_Logger::add(
            $info,
            $error,
            'business',
            $method,
            $response,
            $headers,
            $body
        );

        $res = new Api_Response($response, $info['http_code'], $error);

        if (self::$debugRequests) {
            file_put_contents(__DIR__ . '/_DEBUG_.REQUESTS', print_r($debugStr . ' ' . $res->code, true) . PHP_EOL, FILE_APPEND);
        }

        return $res;
    }


    private static function getChParams($path, $method = 'GET', $getParams = [], $body = [], $headers = [], $token = null, $isFile = false, $isBusinessRequest = false)
    {

        $url = self::getUrl($path, $getParams, $isBusinessRequest);

        $method = strtoupper($method);

        $defHeaders = self::getDefHeaders($token);
        $headers = array_merge($defHeaders, $headers);

        $chParams = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => self::$timeout,
            CURLOPT_TIMEOUT => self::$timeout
        ];

        if ($method !== 'GET') {
            if ($method === 'POST') {
                $chParams[CURLOPT_POST] = true;
            } else {
                $chParams[CURLOPT_CUSTOMREQUEST] = $method;
            }
        }

        if ($body) {
            if (!$isFile) {
                if (!is_string($body) && !is_numeric($body)) {
                    $chParams[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
                    $headers['Content-Type'] = 'application/json';
                } else {
                    $chParams[CURLOPT_POSTFIELDS] = (string)$body;
                }
            } else {
                $chParams[CURLOPT_POSTFIELDS] = $body;
            }
        }

        if ($headers) {
            foreach ($headers as $key => $val) {
                $headers[$key] = "$key: $val";
            }
            $headers = array_values($headers);
            $chParams[CURLOPT_HTTPHEADER] = $headers;
        }

        return $chParams;
    }



    /**
     * - Формирует ссылку для запроса
     * @param string $path путь запроса
     * @param array $getParams [optional] массив параметров GET
     * @param bool $isBusinessRequest [optional] отправлять запрос на адрес бизнеса
     * 
     * @return string
     */
    private static function getUrl($path, $getParams = [], $isBusinessRequest = false)
    {
        $base = self::$urlBase;

        if (in_array($path, self::$pathsBusiness)) {
            $base = self::$urlBaseBusiness;
        }

        if ($isBusinessRequest) {
            $base = self::$urlBaseBusiness;
        }

        $url = $base . $path;

        if ($getParams) {
            $url .= '?' . http_build_query($getParams);
        }

        return $url;
    }


    /**
     * - Получает заголовки по умолчанию
     * @return array
     */
    private static function getDefHeaders($token = null)
    {
        $headers = [];

        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }


    /**
     * - Формирует объект CURLFile для отправки файлов
     * @param string $file абсолютный путь к файлу
     * 
     * @return \CURLFile
     */
    private static function makeCurlFile($file)
    {
        $mime = mime_content_type($file);
        $info = pathinfo($file);
        $name = $info['basename'];
        $output = new \CURLFile($file, $mime, $name);
        return $output;
    }


    /**
     * - Формирует ссылку к статическому ресурсу
     * @param $path относительный путь к ресуру
     *   должен начинаться с /
     * 
     * @return string
     */
    public static function getStaticUrl($path)
    {
        return self::$urlStatic . $path;
    }


    /**
     * - Отключает кеширование для 1 следующего запроса
     */
    static function disableCache()
    {
        if (self::$prevCache === null) {
            self::$prevCache = self::$cache;
        }
        self::$cache = false;
    }

    /**
     * - Включает кеширование для 1 следующего запроса
     */
    static function enableCache()
    {
        if (self::$prevCache === null) {
            self::$prevCache = self::$cache;
        }
        self::$cache = true;
    }

    /**
     * - Восстанавливат кеширование если оно было изменено
     *   методами disableCache или enableCache
     */
    static function restoreCache()
    {
        if (self::$prevCache === null) {
            return;
        }
        self::$cache = self::$prevCache;
        self::$prevCache = null;
    }


    static function getSocialsLoginBaseUrl()
    {
        return self::$urlBase;
    }
}
