<?php

namespace Vnet\Api\Request;

use Vnet\Core\Helper;

class Api_Response
{
    /**
     * - Код ответа
     * @var int
     */
    public $code = 0;

    /**
     * - Массив ответа API
     * @var array
     */
    public $response = [];

    /**
     * - Текст ошибки запроса
     * @var string
     */
    public $error = '';


    public static $messageDef = 'Ошибка соединения с сервером.';


    /**
     * @param string $response JSON
     */
    function __construct($response, $code, $error = null)
    {
        $this->code = $code;

        if ($error) {
            $this->error = $error;
        }

        $res = @json_decode($response, true);

        if ($res) {
            $this->response = $res;
        }
    }


    /**
     * - Получает значение из $this->response
     * @param string ключ в массиве $this->response
     */
    function getResponse($key, $def = null)
    {
        return Helper::getArr($this->response, $key, $def);
    }


    /**
     * - Получает значение $this->response['value'] если есть
     * @return string
     */
    function getMessage()
    {
        if (isset($this->response['value'])) {
            return $this->response['value'];
        }
        if (isset($this->response['message'])) {
            return $this->response['message'];
        }
        return Api_Response::$messageDef;
    }
}
