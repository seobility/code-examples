<?php

/**
 * - Класс для работы с сайтом на сервере
 */

namespace WM;


class Site
{

    /**
     * @var self[]
     */
    private static $ints = [];

    private $domain = '';
    private $testDomain = '';
    private $path = '';
    private $url = '';
    private $_isTest = false;
    private $_isLocal = false;
    private $_isOnline = null;
    private $_hasGit = false;
    private $cms = '';
    private $modified = '';
    private $user = '';
    private $name = '';
    private $db = '';

    /**
     * - Массив доменов локальных копий
     * - Если текущий оснвной
     * @var string[]
     */
    private $localDomains = [];


    /**
     * - Получает объект сайта
     * @param string $domain [optional] домен сайта
     * @return self
     */
    static function getSite($domain = '')
    {
        if (!$domain) {
            $domain = $_SERVER['HTTP_HOST'];
        }
        if (isset(self::$ints[$domain])) {
            return self::$ints[$domain];
        }
        self::$ints[$domain] = new self($domain);
        return self::$ints[$domain];
    }



    private function __construct($domain)
    {
        $mainDomain = App::getMainDomain();
        $reg = "^[\w\d\-\_]+.$mainDomain\$";

        if (preg_match("/$reg/", $domain)) {
            $this->_isTest = true;
            $this->_isLocal = false;
        } else {
            $this->_isTest = false;
            $this->_isLocal = true;
        }

        $this->domain = $domain;

        $this->fillObject();
    }



    private function fillObject()
    {
        $user = App::getMainUser();
        $mainDomain = App::getMainDomain();

        if ($this->isLocal()) {
            // определяем пользователя и название сайта
            $reg = "^([\w\d\-\_]+).([\w\d\-\_]+).$mainDomain\$";
            preg_match("/$reg/", $this->domain, $matches);
            $name = Helper::getArr($matches, 1, '');
            $user = Helper::getArr($matches, 2, '');
            $this->testDomain = $name . '.' . $mainDomain;
        } else {
            // определяем название
            $reg = "^([\w\d\-\_]+).$mainDomain\$";
            preg_match("/$reg/", $this->domain, $matches);
            $name = Helper::getArr($matches, 1, '');
        }

        $this->name = $name;
        $this->user = $user;
        $this->url = 'http://' . $this->domain;

        // ошибка при определении пользователя
        if (!$this->user) {
            return;
        }

        $this->path = realpath($_SERVER['HOME'] . '/../') . '/' . $user . '/www/' . $this->domain;

        // передан некорректный домен
        if (!file_exists($this->path) || !is_dir($this->path)) {
            return;
        }

        $this->_hasGit = (file_exists($this->path . '/.git') && is_dir($this->path . '/.git'));
        $this->cms = $this->detectCms();
        // $this->_isOnline = $this->checkOnline();
        $this->modified = date('d-m-Y H:i:s', filemtime($this->path));

        $this->localDomains = $this->fetchLocalDomains();
        $this->db = $this->fetchDb();
    }


    function getDb()
    {
        return $this->db;
    }


    function isLocal()
    {
        return $this->_isLocal;
    }


    function isTest()
    {
        return $this->_isTest;
    }


    function isOnline()
    {
        if ($this->_isOnline !== null) {
            return $this->_isOnline;
        }
        $this->_isOnline = $this->checkOnline();
        return $this->_isOnline;
    }


    function getCms()
    {
        return $this->cms;
    }


    function hasCms()
    {
        return !!$this->cms;
    }


    function isBitrix()
    {
        return $this->cms === 'bitrix';
    }


    function isWp()
    {
        return $this->cms === 'wp';
    }


    function getModified()
    {
        return $this->modified;
    }


    function hasGit()
    {
        return $this->_hasGit;
    }


    function getDomain()
    {
        return $this->domain;
    }


    function getName()
    {
        return $this->name;
    }


    function getUser()
    {
        return $this->user;
    }


    function getUrl()
    {
        return $this->url;
    }


    function getPath()
    {
        return $this->path;
    }


    function getTestDomain()
    {
        return $this->testDomain;
    }


    function getVscodeUrl()
    {
        $mainDomain = App::getMainDomain();
        return "vscode://vscode-remote/ssh-remote+{$this->user}@{$mainDomain}+{$this->path}";
    }


    /**
     * @return string[]
     */
    function getLocalDomains()
    {
        return $this->localDomains;
    }


    /**
     * @return self[]
     */
    function getLocalSites()
    {
        if (!$this->localDomains) {
            return [];
        }
        $res = [];
        foreach ($this->localDomains as $domain) {
            $res[] = self::getSite($domain);
        }
        return $res;
    }


    function hasLocalDomains()
    {
        return !!$this->localDomains;
    }


    /**
     * - Определяет cms
     * @return string
     */
    private function detectCms()
    {
        if (file_exists($this->path . "/bitrix/modules/main/include/prolog_before.php")) {
            return 'bitrix';
        }

        if (file_exists($this->path . '/wp-load.php')) {
            return 'wp';
        }

        return '';
    }


    /**
     * - Проверяет доступен ли сайт
     * @return bool
     */
    private function checkOnline()
    {
        $ch = curl_init($this->url);

        curl_setopt_array($ch, [
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true
        ]);

        $res = curl_exec($ch);

        curl_close($ch);

        return !!$res;
    }


    private function fetchLocalDomains()
    {
        if (!$this->isTest()) {
            return [];
        }

        $mainUser = App::getMainUser();
        $mainDomain = App::getMainDomain();
        $home = realpath($_SERVER['HOME'] . '/../');
        $scan = scandir($home);

        $res = [];

        foreach ($scan as $userName) {
            if (in_array($userName, ['.', '..'])) {
                continue;
            }
            if ($userName === $mainUser) {
                continue;
            }
            $localDomain = $this->name . '.' . $userName . '.' . $mainDomain;
            $path = $home . '/' . $userName . '/www/' . $localDomain;
            if (!file_exists($path) || !is_dir($path)) {
                continue;
            }
            $res[] = $localDomain;
        }

        return $res;
    }


    /**
     * - Авторизует в CMS
     * @return bool
     */
    function loginCms()
    {
        $content = $this->getLoginFileContent();

        if (!$content) {
            return false;
        }

        $fileName = Helper::randomStr(20) . '.php';

        file_put_contents($this->path . '/' . $fileName, $content);

        return Router::$url . '/' . $fileName;
    }


    /**
     * - Получает содержимое файла для авторизации
     *   в зависимости от CMS
     * @return string
     */
    function getLoginFileContent()
    {
        $content = '';

        if (file_exists($this->path . "/bitrix/modules/main/include/prolog_before.php")) {
            $content = <<<END_CONTENT
            <?php
            require __DIR__ . "/bitrix/modules/main/include/prolog_before.php";
            global \$USER;
            \$USER->Authorize(1);
            Header('Location: /bitrix/');
            @unlink(__FILE__);
            END_CONTENT;
        }

        if (file_exists($this->path . '/wp-load.php')) {
            $content = <<<END_CONTENT
            <?php
            require __DIR__ . '/wp-load.php';
            wp_set_auth_cookie(1, true);
            Header('Location: /wp-admin/');
            @unlink(__FILE__);
            END_CONTENT;
        }

        return $content;
    }


    private function fetchDb()
    {
        if (!$this->hasCms()) {
            return;
        }

        if (file_exists($this->path . "/bitrix/php_interface/dbconn.php")) {
            return $this->getFileVar($this->path . "/bitrix/php_interface/dbconn.php", '\$DBName');
        }

        if (file_exists($this->path . '/wp-config.php')) {
            return $this->getFileVar($this->path . '/wp-config.php', 'DB_NAME');
        }

        return '';
    }


    /**
     * - Запускает php скрипт через shell оболочку
     * - Достает из php файла значение переменной или костанты
     * @param string $file абсолютный путь к файлу
     * @param string $varOrConstant строка с наименованием переменной или константы
     *   если переменная, должна начинаться с \$
     * @return string
     */
    private function getFileVar($file, $varOrConstant)
    {
        $phpCom = "require_once '{$file}'; echo '__VAR_VALUE_START__' . $varOrConstant . '__VAR_VALUE_END__'; exit;";
        $com = "php -r \"$phpCom\"";
        $res = shell_exec($com);
        preg_match("/__VAR_VALUE_START__(.*)__VAR_VALUE_END/m", $res, $matches);
        return Helper::getArr($matches, 1, '');
    }
}
