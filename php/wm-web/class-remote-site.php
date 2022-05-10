<?php

namespace WM;

/**
 * - Класс для работы с удаленным сайтом
 */


class Remote_Site
{

    /**
     * @var self[]
     */
    private static $inst = [];

    private $protocol = 'ssh';
    private $path = '';
    private $host = '';
    private $user = '';
    private $port = '22';
    private $domain = '';



    /**
     * - Получает объект удаленного сайта
     * @param string $domain [optional] по умолчанию первый доступный
     * @return self|false
     */
    static function getSite($domain = '')
    {
        $remotes = App::getRemotes();

        if (!$remotes) {
            return false;
        }

        $sets = false;

        if (!$domain) {
            $sets = $remotes[0];
        } else {
            foreach ($remotes as $remote) {
                $rDomain = Helper::getArr($remote, 'domain', null);
                if (!$rDomain) {
                    continue;
                }
                if ($rDomain === $domain) {
                    $sets = $remote;
                    break;
                }
            }
        }

        if (!$sets) {
            return false;
        }

        if (isset(self::$inst[$domain])) {
            return self::$inst[$domain];
        }

        self::$inst[$domain] = new self($sets);

        return self::$inst[$domain];
    }


    /**
     * @param array $sets настрйоки удаленного сайта
     */
    private function __construct($sets)
    {
        foreach ($sets as $key => $val) {
            if (!$val) {
                continue;
            }
            if (property_exists($this, $key)) {
                $this->$key = $val;
            }
        }
    }


    function getName()
    {
        return $this->name;
    }


    function getProtocol()
    {
        return $this->protocol;
    }


    function getHost()
    {
        return $this->host;
    }


    function getUser()
    {
        return $this->user;
    }


    function getPort()
    {
        return $this->port;
    }


    function getUrl()
    {
        return 'http://' . $this->domain;
    }


    function getDomain()
    {
        return $this->domain;
    }


    function getPath()
    {
        return $this->path;
    }


    function getVscodeUrl()
    {
        return "vscode://vscode-remote/ssh-remote+{$this->user}@{$this->host}+{$this->path}";
    }


    /**
     * - Авторизует пользователя на удаленном сайте
     * @return Error|string ссылку на редирект
     */
    function loginCms()
    {
        if ($this->protocol !== 'ssh') {
            return new Error('Поддерживается только ssh авторизация', 'wrong_protocol');
        }

        $testSite = Site::getSite();

        $content = $testSite->getLoginFileContent();

        if (!$content) {
            return new Error('CMS система не определена', 'no_cms');
        }

        $host = $this->getHost();
        $user = $this->getUser();
        $port = $this->getPort();
        $path = $this->path;

        if (!$host || !$user || !$port || !$path) {
            return new Error('Не хватает данных для подключения', 'missing_connection_data');
        }

        $fileName = Helper::randomStr(20) . '.php';

        $localFile = $testSite->getPath() . '/' . $fileName;
        $remoteFile = $this->path . '/' . $fileName;

        file_put_contents($localFile, $content);

        exec("scp -P {$port} {$localFile} {$user}@{$host}:{$remoteFile}", $out, $code);

        @unlink($localFile);

        if ($code != 0) {
            return new Error('Ошибка при передачи файла авторизации', 'connection_error');
        }

        return $this->getUrl() . '/' . $fileName;
    }
}
