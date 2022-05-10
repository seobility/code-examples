<?php

namespace Vnet\Entity;


class Column
{

    /**
     * - Название как в базе
     * @var string
     */
    private $name = '';

    /**
     * - Название для колонки в админке
     */
    private $label = '';

    /**
     * - колонка с ID
     * @var bool
     */
    private $primary = false;

    /**
     * - Значение по умолчанию
     * - Специальные значения
     *   - NULL = null
     *   - CURRENT_TIMESTAMP = текущая дата и время
     * @var string|false
     */
    private $default = false;

    /**
     * - Выводить в списке в админке
     * @var bool
     */
    private $admin = true;

    /**
     * - Разрешить сортировку
     * @var bool
     */
    private $sort = false;

    /**
     * - Разрешить фильтрацию
     * @var bool
     */
    private $filter = false;

    /**
     * - Поиск по данной колонке
     * @var bool
     */
    private $search = false;

    /**
     * - Сопоставление со статусами
     * - Ключ массива = статус
     * - Значение = значение в бд
     * - Возможные ключи:
     *   - publish опубликован
     *   - draft черновик
     *   - trash корзина
     * @var array
     */
    private $status = [];

    /**
     * - Шириа колонки в админке
     * - CSS строка (нфпример: 10%)
     * @var string
     */
    private $width = '';

    /**
     * @var bool
     */
    private $adminMain = false;

    /**
     * - Использовать значение данной колонки как название элемента
     * @var bool
     */
    private $_isName = false;


    function __construct($params)
    {
        foreach ($params as $key => $val) {
            if ($key === 'isName') {
                $this->_isName = $val;
                continue;
            }
            if (property_exists($this, $key)) {
                $this->$key = $val;
            }
        }
    }


    function isName()
    {
        return $this->_isName;
    }


    function isPrimary()
    {
        return $this->primary;
    }

    function getDefault()
    {
        return $this->default;
    }

    function showAdmin()
    {
        return $this->admin;
    }

    function isSort()
    {
        return $this->sort;
    }

    function isFilter()
    {
        return $this->filter;
    }

    function isSearch()
    {
        return $this->search;
    }

    function isAdminMain()
    {
        return $this->adminMain;
    }

    function getName()
    {
        return $this->name;
    }

    function getLabel()
    {
        return $this->label;
    }

    function isStatus()
    {
        if (isset($this->status['publish'])) {
            return true;
        }
        if (isset($this->status['draft'])) {
            return true;
        }
        if (isset($this->status['trash'])) {
            return true;
        }
        return false;
    }


    function hasTrash()
    {
        return isset($this->status['trash']);
    }


    function hasDraft()
    {
        return isset($this->status['draft']);
    }


    function hasPublish()
    {
        return isset($this->status['publish']);
    }


    function getTrashValue()
    {
        return isset($this->status['trash']) ? $this->status['trash'] : false;
    }


    function getDraftValue()
    {
        return isset($this->status['draft']) ? $this->status['draft'] : false;
    }


    function getPublishValue()
    {
        return isset($this->status['publish']) ? $this->status['publish'] : false;
    }

    function getStatusValue($status)
    {
        if ($status === 'publish') {
            return $this->getPublishValue();
        }
        if ($status === 'draft') {
            return $this->getDraftValue();
        }
        if ($status === 'trash') {
            return $this->getTrashValue();
        }
        return false;
    }


    function getStatusKey($value)
    {
        foreach ($this->status as $key => $val) {
            if ($val == $value) {
                return $key;
            }
        }
        return false;
    }


    /**
     * @return array
     */
    function getStatus()
    {
        return $this->status;
    }


    function getWidth()
    {
        return $this->width;
    }
}
