<?php

/**
 * - Базовый класс для работы с выборкой
 * - Пример использования:
 * $query = $this
 *   ->setQuerySelect($sqlString)
 *   ->setQueryCount($sqlString)
 *   ->exec()
 */

namespace Vnet\Entity;

use Vnet\Redis;

class Entity_Query
{

    /**
     * - Ключ настроек сущности
     * @var string
     */
    private $settingsKey = '';

    public $table = '';

    /**
     * - Массив объектоного кэширования результатов
     * @var array
     */
    private static $obCache = [];

    /**
     * - Строка запроса для SELECT COUNT()
     * @var string [optional]
     */
    private $queryCount = '';

    /**
     * - Строка запроса для SELECT
     * @var string [optional]
     */
    private $querySelect = '';

    /**
     * - Время кеширования в редисе
     * @var int
     */
    private $cacheTime = 0;

    /**
     * - Наименование первичной колонки в бд
     * - Если установлен, при вызове класса $this->entity
     *   будет передано только значение
     * - Если не установлено, будет передан весь массив выборки
     * @var string [optional]
     */
    protected $primaryCol = '';

    private $_selectFromCache = false;
    private $_countFromCache = false;
    private $_cacheSelectKey = false;
    private $_cacheCountKey = false;

    /**
     * - Массив экземпляров класса $this->entity
     * @var array
     */
    private $result = [];

    /**
     * - Счеткчик для $this->fetch()
     * @param int
     */
    private $i = 0;

    /**
     * - Номер страницы
     * @param int [optiona]
     */
    public $page = 1;

    /**
     * - Кол-во на странице
     * @param int [optional]
     */
    public $perpage = 20;

    /**
     * - Строка поиска
     * @var string
     */
    public $search = '';

    /**
     * - Порядок сортировки
     * @var string ASC|DESC
     */
    public $order = 'DESC';

    /**
     * - Массив ID из которых делать выборку
     * @var array
     */
    public $idin = [];

    /**
     * - Колонка по которой сортировать
     * - По умолчанию $this->primaryCol
     * @var string
     */
    public $orderby = '';

    /**
     * - Статус
     * - any|publish|draft|trash
     * - если не установлен - будет выбрано все за исключением trash
     * - если any - будет выбрано все trash включительно
     * @var string|string[]
     */
    public $status = '';

    /**
     * - Общее кол-во
     * - Результат $this->queryCount
     * @param int [optional]
     */
    public $total = -1;

    /**
     * - Общее кол-во страниц
     * - Установится послу выполнения $this-exec()
     *   в том случае если есть:
     *      - $this->total > -1
     *      - $this->perpage > -1
     * @param int [optional]
     */
    public $totalPages = -1;

    /**
     * - Кол-во в $this->result
     */
    public $found = 0;

    private $_count = true;
    private $_select = true;




    function __construct($settingsKey)
    {
        $this->settingsKey = $settingsKey;
        $this->table = $this->getTable();
        $this->primaryCol = $this->getPrimaryCol();
        $this->cacheTime = $this->getCacheTime();
    }


    /**
     * - Выполняет запрос на выборку с учетом фильтра
     * @return self
     */
    function filter($filter = [])
    {
        $this->setupArgs($filter);
        $this->setupQueries($filter);
        return $this->exec();
    }

    /**
     * - Формирует строковые запросы
     * @param array $filter
     */
    protected function setupQueries($filter = [])
    {
        $sqlParts = $this->getSqlParts($filter);

        $select = "SELECT " . $sqlParts['select'];
        $selectCount = "SELECT " . $sqlParts['selectCount'];

        $query = '';

        if (!empty($sqlParts['join'])) {
            $query .= $sqlParts['join'];
        }

        if (!empty($sqlParts['where'])) {
            $query .= " {$sqlParts['where']}";
        }

        if (!empty($sqlParts['order'])) {
            $query .= " ORDER BY " . $sqlParts['order'];
        }

        $querySelect = "$select $query";
        $queryCount = "$selectCount $query";

        if (!empty($sqlParts['limit'])) {
            $querySelect .= " LIMIT {$sqlParts['limit']}";
        }

        if ($this->_count) {
            $this->setQueryCount($queryCount);
        }
        if ($this->_select) {
            $this->setQuerySelect($querySelect);
        }
    }


    /**
     * - Формирует части запроса такие как
     *   SELECT, SELECT COUNT(), WHERE, ORDER, LIMIT
     * 
     * @return array
     */
    protected function getSqlParts($filter =  [])
    {
        $select = $this->getSqlSelect($filter);
        $selectCount = $this->getSqlSelectCount($filter);
        $join = $this->getSqlJoin($filter);
        $where = $this->getSqlWhere($filter);
        $order = $this->getSqlOrder($filter);
        $limit = $this->getSqlLimit($filter);

        return [
            'select' => $select,
            'selectCount' => $selectCount,
            'join' => $join,
            'where' => $where,
            'order' => $order,
            'limit' => $limit
        ];
    }


    /**
     * - Формирует массив с запросами для WHERE
     * @return string
     */
    protected function getSqlWhere($filter = [])
    {
        $search = $this->getSqlSearch();
        $status = $this->getSqlStatus();
        $idin = $this->getSqlIdIn();
        return $this->formatSqlWhere([$idin, $search, $status]);
    }


    /**
     * - Устанавливает параметры фильтрации в объекте
     * @param array $filter
     */
    protected function setupArgs($filter)
    {
        if (!empty($filter['page'])) {
            $this->page = (int)esc_sql($filter['page']);
        }

        if (!empty($filter['perpage'])) {
            $this->perpage = (int)esc_sql($filter['perpage']);
        }

        if (!empty($filter['order'])) {
            $upOrder = strtoupper($filter['order']);
            $order = $upOrder === 'ASC' ? 'ASC' : 'DESC';
            $this->order = $order;
        }

        if (!empty($filter['orderby'])) {
            $this->orderby = esc_sql($filter['orderby']);
        }

        if (!empty($filter['search'])) {
            $this->search = esc_sql($filter['search']);
        }

        if (!empty($filter['status'])) {
            $status = esc_sql(strtolower($filter['status']));
            if (in_array($status, ['publish', 'draft', 'trash'])) {
                $this->status = $status;
            }
        }

        if (!empty($filter['idin'])) {
            $this->idin = esc_sql($filter['idin']);
        }

        if (!$this->orderby || $this->orderby === 'id') {
            $this->orderby = $this->primaryCol;
        }

        if (isset($filter['select'])) {
            $this->_select = !!$filter['select'];
        }

        if (isset($filter['count'])) {
            $this->_count = !!$filter['count'];
        }
    }



    /**
     * - Получает настройки сущности
     * @return Settings
     */
    protected function getSettings()
    {
        return Main::get($this->settingsKey);
    }

    /**
     * @return Column|false
     */
    protected function getPrimaryCol()
    {
        return Main::get($this->settingsKey)->getPrimaryCol()->getName();
    }

    protected function getCacheTime()
    {
        return Main::get($this->settingsKey)->getCacheTimeQuery();
    }

    /**
     * @return Column[]
     */
    protected function getSearchColumns()
    {
        return Main::get($this->settingsKey)->getSearchColumns();
    }

    /**
     * - Получает статусы сущности
     * - если они установлены в настройках
     * @return Column|false
     */
    protected function getStatusCol()
    {
        return Main::get($this->settingsKey)->getStatusCol();
    }


    protected function getTable()
    {
        return Main::get($this->settingsKey)->getTable();
    }


    protected function newEntity($params)
    {
        return Main::get($this->settingsKey)->newEntity($params);
    }


    /**
     * - Выполняет установленные запросы
     * - Заполняет массив результатами
     * @return self
     */
    function exec()
    {
        if (!$this->primaryCol) {
            return $this;
        }

        $this->total = $this->getSetCount();
        $result = $this->getSetSelect();

        if ($result) {
            $this->found = count($result);
            foreach ($result as $id) {
                $this->result[] = $this->newEntity($id);
            }
        }

        if ($this->total > -1 && $this->perpage > -1) {
            $this->totalPages = ceil($this->total / $this->perpage);
        }

        return $this;
    }


    /**
     * - Сбрасывает кэш
     * @return self
     */
    function flushCache()
    {
        global $wpdb;
        $table = $wpdb->options;

        $query = "DELETE FROM `$table` WHERE `option_name` LIKE 'count_{$this->settingsKey}%'";
        $wpdb->query($query);

        Redis::flush('select_' . $this->settingsKey);

        if (isset(self::$obCache[$this->settingsKey])) {
            self::$obCache[$this->settingsKey] = [];
        }

        return $this;
    }


    /**
     * - Получает слудующий доступный элемент выборки
     * @return Entity|null
     */
    function fetch()
    {
        $i = $this->i;
        if (isset($this->result[$i])) {
            $this->i++;
            return $this->result[$i];
        }
        return null;
    }


    function hasResults()
    {
        return !empty($this->result);
    }


    /**
     * @return Entity|null
     */
    function get($i = -1)
    {
        if ($i === -1) {
            return $this->result;
        }
        return isset($this->result[$i]) ? $this->result[$i] : null;
    }


    /**
     * - Устанавливает sql запрос для получения общего кол-во
     * @param string $query
     * 
     * @return self
     */
    protected function setQueryCount($query)
    {
        $this->queryCount = $query;
        return $this;
    }


    /**
     * - Устанавливает sql запрос для выборки
     * @param string $query
     * 
     * @return self
     */
    protected function setQuerySelect($query)
    {
        $this->querySelect = $query;
        return $this;
    }


    /**
     * - Получает кол-во из кеша
     * - Устанавливает кол-во если еще нет
     * @return int
     */
    private function getSetCount()
    {
        global $wpdb;

        if (!$this->queryCount) {
            return -1;
        }

        $cacheKey = $this->getCacheCountKey();

        if ($cacheKey) {
            $cacheCount = get_option($cacheKey);
            if ($cacheCount !== false) {
                $this->_countFromCache = true;
                return (int)$cacheCount;
            }
        }

        $count = $wpdb->get_var($this->queryCount);

        if ($count === null) {
            return -1;
        }

        if ($cacheKey) {
            update_option($cacheKey, $count);
        }

        return $count;
    }


    private function getSetSelect()
    {
        global $wpdb;

        if (!$this->querySelect) {
            return [];
        }

        $cacheKey = $this->getCacheSelectKey();

        if (isset(self::$obCache[$cacheKey])) {
            $this->_selectFromCache = true;
            return self::$obCache[$cacheKey];
        }

        if ($cacheKey) {
            if ($cache = Redis::get($cacheKey)) {
                $this->_selectFromCache = true;
                self::$obCache[$cacheKey] = $cache;
                return $cache;
            }
        }

        $res = $wpdb->get_results($this->querySelect, ARRAY_A);

        if (!$res || is_wp_error($res)) {
            return [];
        }

        $ids = array_column($res, $this->primaryCol);

        self::$obCache[$cacheKey] = $ids;

        if ($cacheKey) {
            Redis::set($cacheKey, $ids, $this->cacheTime);
        }

        return $ids;
    }


    /**
     * - Формирует ключ кеша с кол-вом
     * @return string
     */
    private function getCacheCountKey()
    {
        if (!$this->queryCount) {
            $this->_cacheCountKey = '';
        } else {
            $this->_cacheCountKey = 'count_' . $this->settingsKey . md5($this->queryCount);
        }
        return $this->_cacheCountKey;
    }


    private function getCacheSelectKey()
    {
        if (!$this->querySelect) {
            $this->_cacheSelectKey = '';
        } else {
            $this->_cacheSelectKey = 'select_' . $this->settingsKey . md5($this->querySelect);
        }
        return $this->_cacheSelectKey;
    }


    protected function getSqlSelect($filter)
    {
        return "`{$this->table}`.`{$this->primaryCol}` FROM `{$this->table}`";
    }

    protected function getSqlSelectCount($filter)
    {
        return "COUNT(`{$this->table}`.`{$this->primaryCol}`) FROM `{$this->table}`";
    }

    protected function getSqlJoin($filter)
    {
        return '';
    }


    /**
     * - Получает строку LIMIT
     * 
     * @return string
     */
    protected function getSqlLimit($filter)
    {
        if ($this->perpage === -1) {
            return '';
        }

        $offset = $this->perpage * ($this->page - 1);

        return "{$this->perpage} OFFSET {$offset}";
    }


    /**
     * - Формирует часть запроса WHERE для поиска
     * @return string
     */
    protected function getSqlSearch()
    {
        if (!$this->search) {
            return '';
        }

        $columns = $this->getSearchColumns();
        $table = $this->table;

        if (!$columns) {
            return '';
        }

        $sqlSearch = [];
        $str = explode(' ', $this->search);

        foreach ($columns as $col) {
            $name = $col->getName();
            $subSql = [];
            foreach ($str as $word) {
                $subSql[] = "`{$table}`.`$name` LIKE '%$word%'";
            }
            $sqlSearch[] = "(" . implode(' AND ', $subSql) . ")";
        }

        return implode(' OR ', $sqlSearch);
    }



    /**
     * - Формирует часть запроса со статусом для использованияв WHERE
     * 
     * @return string
     */
    protected function getSqlStatus()
    {
        $statusCol = $this->getStatusCol();
        $table = $this->table;

        if (!$statusCol) {
            return '';
        }

        $colName = $statusCol->getName();

        if (!$this->status) {
            $value = $statusCol->getTrashValue();
            if ($value !== false) {
                return "`{$table}`.`$colName` NOT LIKE '$value'";
            }
            return '';
        }

        if (is_string($this->status)) {
            $value = $statusCol->getStatusValue($this->status);
            if ($value !== false) {
                return "`$colName` = '{$value}'";
            }
            return '';
        }

        if (!is_array($this->status)) {
            return '';
        }

        $arStatuses = [];

        foreach ($this->status as $status) {
            $value = $statusCol->getStatusValue($status);
            if ($value !== false) {
                $arStatuses[] = $value;
            }
        }

        if (!$arStatuses) {
            return '';
        }

        $sqlStatuses = implode("','", $arStatuses);

        return "`$colName` NOT IN ('$sqlStatuses')";
    }


    /**
     * @return string
     */
    protected function getSqlIdIn()
    {
        if (!$this->idin) {
            return '';
        }
        $sqlIds = implode("','", $this->idin);
        $colName = $this->primaryCol;
        $table = $this->table;
        return "`{$table}`.`$colName` IN ('$sqlIds')";
    }


    /**
     * - Получает строку ORDER BY
     * 
     * @return string
     */
    protected function getSqlOrder($filter)
    {
        $order = $this->order;
        $orderby = $this->orderby;

        // добавляем первичную колонку в сортировке
        if ($orderby !== $this->primaryCol) {
            return "`{$this->table}`.`{$orderby}` {$order}, `{$this->table}`.`{$this->primaryCol}` {$order}";
        }

        return "`{$this->table}`.`{$orderby}` {$order}";
    }


    /**
     * - Формирует строку запроса WHERE
     * @param array $where массив sql строк
     * @param string $logic логика сравнения AND|OR
     * 
     * @return string
     */
    protected function formatSqlWhere($where, $logic = 'and')
    {
        $where = array_filter($where);

        if (!count($where)) {
            return '';
        }

        $logic = strtoupper($logic);

        return "WHERE (" . implode(") $logic (", $where) . ")";
    }


    /**
     * - Сбрасывает счетчик для $this->fetch()
     * 
     */
    function resetFetchCounter()
    {
        $this->i = 0;
    }
}
