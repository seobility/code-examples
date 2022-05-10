<?php

/**
 * - Класс для работы с сессией
 */

namespace WM;

class Session
{

    /**
     * - Масси данных сессии
     * @var array
     */
    private static $data = [];

    /**
     * - Идентификатор сессии в куках
     * @var string
     */
    private static $sid = '';

    /**
     * - UNIX метка создания сессии
     * @var int
     */
    private static $created = 0;

    /**
     * - Папка для хранения файлов сессии
     * @var string
     */
    private static $path = '';

    /**
     * - Время жизни сессии в секундах
     * @var int
     */
    private static $time = 3600 * 3;


    /**
     * - Инициализирует сессию пользователя
     * - Регистрирует функцию для сохранения сессии
     *   по завершению скрипта
     */
    static function setup()
    {
        self::$path = realpath(__DIR__ . '/../') . '/session';
        self::init();
        register_shutdown_function([__CLASS__, '_saveSession']);
    }


    private static function init()
    {
        self::validateSession();

        // не ожидается большая нагрузка
        // можно чистить при запросе
        // по хорошему надо переписать на cron
        // но мне в лом
        self::deleteOldSessions();

        if (empty($_COOKIE['WMSID'])) {
            $sid = self::createSession();
        } else {
            $sid = $_COOKIE['WMSID'];
        }

        $arSession = self::fetchSession($sid);

        self::$sid = $sid;
        self::$created = $arSession['created'];
        self::$data = $arSession['data'];
    }


    /**
     * - Получает содержимое файла сесии
     * @return array ['created' => 'UNIX метка создания', 'data' => 'массив данных']
     */
    private static function fetchSession($sid)
    {
        $file = self::$path . '/' . $sid;

        if (!file_exists($file)) {
            return [
                'created' => time(),
                'data' => []
            ];
        }

        $content = file($file);

        $created = (int)trim($content[0]);

        unset($content[0]);

        $data = unserialize(implode(PHP_EOL, $content));

        return [
            'created' => $created,
            'data' => $data
        ];
    }


    /**
     * - Валидирует переданный идентификатор сесии
     * - Если время истекло - удаляет файл сессии
     *   и удаляет куку
     */
    private static function validateSession()
    {
        if (empty($_COOKIE['WMSID'])) {
            return;
        }

        $sid = $_COOKIE['WMSID'];

        $file = self::$path . '/' . $sid;

        if (!file_exists($file)) {
            return;
        }

        $time = trim(file($file)[0]);

        if (!$time) {
            self::destroy($sid);
            return;
        }

        $diff = time() - (int)$time;

        if ($diff >= self::$time) {
            self::destroy($sid);
        }
    }


    /**
     * - Уничтожает сессию пользователя
     */
    private static function destroy($sid)
    {
        $file = self::$path . '/' . $sid;

        @unlink($file);

        if (isset($_COOKIE['WMSID'])) {
            unset($_COOKIE['WMSID']);
        }

        setcookie('WMSID', null, time() - (self::$time * 2), '/', $_SERVER['HTTP_HOST']);
    }


    /**
     * - Удаляет все истекшие файлы сессии
     */
    private static function deleteOldSessions()
    {
        $scan = scandir(self::$path);

        $now = time();

        foreach ($scan as $sid) {
            if (in_array($sid, ['.', '..', '.gitkeep'])) {
                continue;
            }

            $file = self::$path . '/' . $sid;

            $time = (int)trim(file($file)[0]);

            if (!$time) {
                @unlink($file);
                continue;
            }

            $diff = $now - $time;

            if ($diff >= self::$time) {
                @unlink($file);
            }
        }
    }


    /**
     * - Создает идентификатор сессии
     * - Устанавливает в куках
     * @return string ID сессии
     */
    private static function createSession()
    {
        $domain = $_SERVER['HTTP_HOST'];

        $ip = $_SERVER['HTTP_X_REAL_IP'];

        $sid = md5($domain . $ip . uniqid());

        // устанавливаем время жизни куки в 2 раза больше
        // таким образом будем удалять файл сессии по истечению
        // основного времени жизни
        setcookie('WMSID', $sid, time() + (self::$time * 2), '/', $domain);

        return $sid;
    }


    /**
     * - Сохраняет сессию после выполнения скрипта
     */
    static function _saveSession()
    {
        $content = self::$created . PHP_EOL . serialize(self::$data);
        file_put_contents(self::$path . '/' . self::$sid, $content);
        chmod(self::$path . '/' . self::$sid, fileperms(self::$path . '/' . self::$sid) | 16);
    }


    /**
     * - Добавляет значение в сессию
     * @param string $key
     * @param mixed $value
     */
    static function add($key, $value)
    {
        self::$data[$key] = $value;
    }

    /**
     * - Удаляет из сессии
     * @param string $key
     */
    static function remove($key)
    {
        if (isset(self::$data[$key])) {
            unset(self::$data[$key]);
        }
    }


    /**
     * - Получает значение из сессии
     * @param string $key
     * @param mixed $def [optional]
     * @param bool $unset [optional] удалить после получения значения
     * @return mixed
     */
    static function get($key, $def = null, $unset = false)
    {
        if (!isset(self::$data[$key])) {
            return $def;
        }

        $val = self::$data[$key];

        if ($unset) {
            self::remove($key);
        }

        return $val;
    }


    /**
     * - Добавляет в сессию значение массива
     * @param string $key
     * @param mixed $value
     * @param string $arKey [optional] ключ в массиве для ассотиативных
     * 
     * @return array массив с добавленым значением
     */
    static function push($key, $value, $arKey = null)
    {
        if (!isset(self::$data[$key])) {
            self::$data[$key] = [];
        }
        if (!$arKey) {
            self::$data[$key][] = $value;
        } else {
            self::$data[$key][$arKey] = $value;
        }
        return self::$data[$key];
    }


    /**
     * - Получает из значения массива сессии
     * @param string $sesKey ключ в сессии
     * @param string $key ключ в массиве значения
     * @param mixed $def [optional] значение по умолчанию
     * @param bool $unset [optional] удалить значение после получения
     */
    static function getArr($sesKey, $key, $def = null, $unset = false)
    {
        if (!isset(self::$data[$sesKey][$key])) {
            return $def;
        }

        $val = self::$data[$sesKey][$key];

        if ($unset) {
            unset(self::$data[$sesKey][$key]);
        }

        return $val;
    }


    /**
     * - Получает все данные сессии
     * @return array
     */
    static function getAll()
    {
        return self::$data;
    }


    /**
     * - Генерирует новый ID сессии
     */
    static function refreshSid()
    {
        self::destroy(self::$sid);
        self::$sid = self::createSession();
    }
}
