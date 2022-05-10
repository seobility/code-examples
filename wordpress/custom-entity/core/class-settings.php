<?php

namespace Vnet\Entity;


class Settings
{

    /**
     * - Уникальный ключ для внутреннего использования
     * @var string
     */
    private $key = '';

    /**
     * - Таблица в админке
     */
    private $table = '';

    /**
     * @var Labels
     */
    private $labels = null;

    /**
     * - Название таблицы родительской сущности
     * - Используется в админке
     */
    private $menuParent = '';

    /**
     * - Иконка пункта меню
     */
    private $menuIco = null;

    /**
     * - Расположение в админке
     * @var int|float|null
     */
    private $menuPosition = null;

    /**
     * @var Column[]
     */
    private $columns = [];

    /**
     * - Класс для выборки элементов
     */
    private $query = __NAMESPACE__ . '\Entity_Query';

    /**
     * - Класс сущности
     */
    private $entity = __NAMESPACE__ . '\Entity';

    /**
     * - Время в секундах для кэширования отдельного элемента
     * @var int
     */
    private $cacheTimeSingle = 0;

    /**
     * - Время в секундах для кэширования результатов выборки
     * @var int
     */
    private $cacheTimeQuery = 0;


    /**
     * @param string $itemKey уникальный ключ для внутреннего использования
     * @param array $params
     */
    function __construct($itemKey, $params)
    {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $this->key = $itemKey;

        foreach ($params as $key => $value) {

            if ($key === 'table') {
                $this->table = $prefix . $value;
                continue;
            }

            if ($key === 'columns') {
                foreach ($value as $colName => $colSets) {
                    $colSets['name'] = $colName;
                    $this->columns[$colName] = new Column($colSets);
                }
                continue;
            }

            if ($key === 'labels') {
                $this->labels = new Labels($value);
                continue;
            }

            if (!property_exists($this, $key)) {
                continue;
            }

            $this->$key = $value;
        }

        if (!$this->labels) {
            $this->labels = new Labels(['name' => 'Элемент']);
        }

        $this->entity::$settingsKey = $this->key;
        $this->query::$settingsKey = $this->key;
    }


    function getKey()
    {
        return $this->key;
    }


    /**
     * - Получает колонку значение которой используется
     *   в качестве названия элемента
     * 
     * @return Column|false
     */
    function getNameColumn()
    {
        foreach ($this->columns as $col) {
            if ($col->isName()) {
                return $col;
            }
        }
        return false;
    }


    /**
     * - Получает название таблицы
     * @return string
     */
    function getTable()
    {
        return $this->table;
    }


    /**
     * - Получает все колонки
     * @return Column[]
     */
    function getColumns()
    {
        return $this->columns;
    }


    /**
     * - Получает первичную колонку
     * @return Column|false
     */
    function getPrimaryCol()
    {
        foreach ($this->columns as $col) {
            if ($col->isPrimary()) {
                return $col;
            }
        }
        return false;
    }


    /**
     * - Получает колонку по name
     * @param string $colName название как в БД
     * @return Column|false
     */
    function getColumn($colName)
    {
        foreach ($this->columns as $col) {
            if ($col->getName() === $colName) {
                return $col;
            }
        }
        return false;
    }


    /**
     * - Получает колонку статусов
     * @return Column|false
     */
    function getStatusCol()
    {
        foreach ($this->columns as $col) {
            if ($col->isStatus()) {
                return $col;
            }
        }
        return false;
    }


    /**
     * - Получает название колонки статуса как в БД
     * @return string
     */
    function getStatusColName()
    {
        $col = $this->getStatusCol();
        if (!$col) {
            return '';
        }
        return $col->getName();
    }


    function getCacheTimeSingle()
    {
        return $this->cacheTimeSingle;
    }


    function getCacheTimeQuery()
    {
        return $this->cacheTimeQuery;
    }


    /**
     * - Получает статусы
     * @return array
     */
    function getStatuses()
    {
        foreach ($this->columns as $col) {
            if ($col->isStatus()) {
                return $col->getStatus();
            }
        }
        return [];
    }


    /**
     * - Получает колонки участвующие в поиске
     * @return Column[]
     */
    function getSearchColumns()
    {
        $res = [];
        foreach ($this->columns as $col) {
            if ($col->isSearch()) {
                $res[] = $col;
            }
        }
        return $res;
    }


    /**
     * - Получает колонки для вывода в списке в админке
     * @return Column[]
     */
    function getAdminColumns()
    {
        $res = [];
        foreach ($this->columns as $col) {
            if ($col->showAdmin()) {
                $res[] = $col;
            }
        }
        return $res;
    }


    function getMenuParent()
    {
        return $this->menuParent;
    }


    function getMenuIco()
    {
        return $this->menuIco;
    }


    function getMenuPosition()
    {
        return $this->menuPosition;
    }

    /**
     * @return Labels
     */
    function getLabels()
    {
        return $this->labels;
    }

    /**
     * @return string
     */
    function getLabel($key)
    {
        if (!property_exists($this->labels, $key)) {
            return '';
        }
        return $this->labels->$key;
    }


    function getEntityClass()
    {
        return $this->entity;
    }

    /**
     * @return Entity
     */
    function newEntity($id = 0)
    {
        return new $this->entity($id);
    }


    function getQueryClass()
    {
        return $this->query;
    }


    /**
     * @return Entity_Query
     */
    function newQuery()
    {
        return new $this->query();
    }


    function hasTrash()
    {
        foreach ($this->columns as $col) {
            if ($col->hasTrash()) {
                return true;
            }
        }
        return false;
    }


    function hasStatus()
    {
        foreach ($this->columns as $col) {
            if ($col->isStatus()) {
                return true;
            }
        }
        return false;
    }
}
