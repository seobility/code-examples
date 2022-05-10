<?php

/**
 * - класс для работы с гитом на тестовом
 */

namespace WM;


class Git
{

    /**
     * @var Git
     */
    private static $inst = null;

    private $mainBranch = '';
    private $currentBranch = null;
    private $changes = null;
    private $branches = null;
    private $changeBranches = null;
    private $_hasGit = true;



    static function setup()
    {
        $siteConf = App::getSiteConf();
        self::$inst = new self($siteConf);
    }


    static function getInst()
    {
        return self::$inst;
    }


    private function __construct($siteConf)
    {
        $this->mainBranch = Helper::getArr($siteConf, 'main_branch', '');
        $changeBranches = Helper::getArr($siteConf, 'change_branches', []);
        $this->changeBranches = array_intersect($changeBranches, $this->getBranches());
        $this->_hasGit = $this->checkHasGit();
    }


    /**
     * - Обновляет тестовый
     * @return true|Error
     */
    function update()
    {
        exec("cd {$_SERVER['DOCUMENT_ROOT']} && wm test-update -y -b {$this->getCurrentBranch()}", $out, $code);

        if ($code != '0') {
            return new Error('Ошибка при обновлении тестового сайта');
        }

        return true;
    }


    function checkHasGit()
    {
        $path = $_SERVER['DOCUMENT_ROOT'] . '/.git';
        return file_exists($path) && is_dir($path);
    }

    function hasGit()
    {
        return $this->_hasGit;
    }


    function getMainBranch()
    {
        return $this->mainBranch;
    }


    /**
     * - Получает ветки которые можно переключать с веб интерфейса
     */
    function getChangeBranches()
    {
        return $this->changeBranches;
    }


    function getCurrentBranch()
    {
        if ($this->currentBranch !== null) {
            return $this->currentBranch;
        }
        $this->currentBranch = $this->fetchCurrentBranch();
        return $this->currentBranch;
    }


    private function fetchCurrentBranch()
    {
        $res = shell_exec("cd {$_SERVER['DOCUMENT_ROOT']} && git branch");

        if (!$res) {
            return '';
        }

        preg_match("/^\*\s*([\w\d]+)/m", $res, $matches);

        if (empty($matches[1])) {
            return '';
        }

        return trim($matches[1]);
    }


    function getChanges()
    {
        if ($this->changes !== null) {
            return $this->changes;
        }
        $this->changes = $this->fetchChanges();
        return $this->changes;
    }


    private function fetchChanges()
    {
        $res = shell_exec("cd {$_SERVER['DOCUMENT_ROOT']} && git status --porcelain");
        if (!$res) {
            return [];
        }
        $res = explode(PHP_EOL, $res);
        foreach ($res as &$val) {
            $val = trim($val);
        }
        return array_values(array_filter($res));
    }


    function getBranches()
    {
        if ($this->branches !== null) {
            return $this->branches;
        }
        $this->branches = $this->fetchBranches();
        return $this->branches;
    }


    private function fetchBranches()
    {
        $res = shell_exec("cd {$_SERVER['DOCUMENT_ROOT']} && git branch");

        if (!$res) {
            return [];
        }

        $res = explode(PHP_EOL, $res);

        foreach ($res as &$val) {
            $val = trim($val);
            $val = preg_replace("/^\*\s*/", '', $val);
        }

        return array_values(array_filter($res));
    }


    /**
     * - Меняет текущую ветку
     * @param string $newBranch
     * @return true|Error в случае успеха
     */
    function changeBranch($newBranch)
    {
        $currentBranch = $this->getCurrentBranch();
        $branches = $this->getBranches();
        $changes = $this->getChanges();

        if ($changes) {
            return new Error('Нельзя поменять ветку с активными изменениями', 'has_changes');
        }

        if (!in_array($newBranch, $branches)) {
            return new Error('Переданная ветка не существует', 'no_branch');
        }

        if ($currentBranch === $newBranch) {
            return true;
        }

        exec("cd {$_SERVER['DOCUMENT_ROOT']} && git checkout {$newBranch}", $out, $code);

        if ($code != '0') {
            return new Error("Ошибка при выполнении команды: git checkout {$newBranch}", 'checkout_error');
        }

        return true;
    }
}
