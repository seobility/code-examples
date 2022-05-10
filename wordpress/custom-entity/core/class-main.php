<?php

namespace Vnet\Entity;



class Main
{

    /**
     * @var Settings[]
     */
    private static $entities = [];


    /**
     * @return bool
     */
    static function register($key, $sets)
    {
        if (empty($sets['table'])) {
            return false;
        }
        if (isset(self::$entities[$key])) {
            return true;
        }
        self::$entities[$key] = new Settings($key, $sets);
        return true;
    }


    /**
     * 
     * - Получает настройки сущности
     * @param string $key ключ сущности
     * 
     * @return false|Settings
     */
    static function get($key)
    {
        return isset(self::$entities[$key]) ? self::$entities[$key] : false;
    }


    /**
     * @return Settings[]
     */
    static function getAll()
    {
        return self::$entities;
    }

    /**
     * @return bool
     */
    static function has($key = null)
    {
        if ($key === null) {
            return !!self::$entities;
        }
        return !!self::$entities[$key];
    }


    /**
     * - Сбрасывает весь кэш сущности
     * @param string $key ключ сущности
     * @param int|string $id ID элемента
     *   если не передан сбросится кэш всех элеметов
     */
    static function flushCache($key, $id = 0)
    {
        $settings = self::get($key);

        if (!$settings) {
            return;
        }

        $settings->newQuery()->flushCache();
        if (!$id) {
            $settings->newEntity()->flushCache(true);
        } else {
            $settings->newEntity()->set('id', $id)->flushCache();
        }
    }
}
