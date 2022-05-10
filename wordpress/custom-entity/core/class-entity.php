<?php

namespace Vnet\Entity;

use Vnet\Redis;

/**
 * 
 * - Базовый класс для работы с элементом сущности
 * 
 * - Методы которые необходимо переопределить в дочернем классе:
 *   - adminSetAcfValues() - вывод элемента на странице редактирования в админке
 * 
 * - Свойства которые необходимо переопределить в дочернем классе
 *   - $acfCompare - массив для сравнения ключей ACF с полями в админке
 * 
 */

class Entity
{

    private $settingsKey = '';

    private $table = '';

    private $primaryCol = '';

    /**
     * - Массив выборки с базы данных
     * @var array
     */
    private $dbResult = [];

    /**
     * - Префикс для ключа кэша
     * @var string
     */
    private $cacheKey = '';

    /**
     * - Время кэширования результатов
     * @var int
     */
    private $cacheTime = 0;

    /**
     * - Массив сравнения acf ключей с колонками элемента в БД
     * - ключ = acf
     * - значение = колонка в БД
     */
    protected $acfCompare = [];

    /**
     * - Массив с объектным кэшированием
     */
    private static $obCache = [];

    private $_fromCache = false;



    function __construct($settingsKey, $id = 0)
    {
        $this->settingsKey = $settingsKey;
        $settings = Main::get($this->settingsKey);
        $this->table = $settings->getTable();
        $this->primaryCol = $settings->getPrimaryCol()->getName();
        $this->cacheKey = 'single_' . $this->settingsKey;
        $this->cacheTime = $settings->getCacheTimeSingle();
        // $this->setupProperties();

        if ($id === 0) {
            return;
        }

        $info = is_array($id) ? $id : $this->getDbInfo($id);

        if (!$info) {
            return;
        }

        $this->dbResult = $info;
    }


    /**
     * - Записывает данные в $this->dbResult[]
     */
    function set($key, $value)
    {
        if ($key === 'id') {
            $key = $this->primaryCol;
        }
        if ($key === 'status') {
            $key = Main::get($this->settingsKey)->getStatusColName();
        }
        if ($key) {
            $this->dbResult[$key] = $value;
        }
        return $this;
    }


    function get($key, $def = null)
    {
        if ($key === 'id') {
            $key = $this->primaryCol;
        }
        if ($key === 'status') {
            $key = Main::get($this->settingsKey)->getStatusColName();
        }
        if (isset($this->dbResult[$key])) {
            return $this->dbResult[$key];
        }
        return $def;
    }


    /**
     * - Получает значение колонки которая используется
     *   в качестве названия элемента
     * @return string
     */
    function getName()
    {
        $col = Main::get($this->settingsKey)->getNameColumn();
        if (!$col) {
            return '';
        }
        return $this->get($col->getName(), '');
    }


    /**
     * - Получает информацию элемнета с БД
     * - Проверяет наличие в данных в объектном кэшировании
     * - Проверяет наличие данных в редисе
     * @return array
     */
    protected function getDbInfo($id)
    {
        $id = esc_sql($id);

        if ($res = $this->getCache($id)) {
            $this->_fromCache = true;
            return $res;
        }

        $res = $this->fetchData($this->table, $this->primaryCol, $id);

        if (is_wp_error($res)) {
            return [];
        }

        if (!$res) {
            $this->setCache($id, []);
            return [];
        }

        $this->setCache($id, $res);

        return $res;
    }


    /**
     * - Получает информацию элемента с базы данных
     * @param string $table
     * @param string $primaryCol
     * @param string $id обработана esc_sql()
     * @return array
     */
    protected function fetchData($table, $primaryCol, $id)
    {
        global $wpdb;

        $query = "SELECT * FROM `{$table}` WHERE `$primaryCol` = '$id' LIMIT 1";

        $res = $wpdb->get_results($query, ARRAY_A);

        $result = [];

        if (is_wp_error($res) || !$res) {
            return $result;
        }

        $result = $res[0];

        $this->filterDbData($result, $table, $primaryCol, $id);

        return $result;
    }


    /**
     * - Дополнительный фильтр для изменения данных с БД
     * - При необходимости переопределяется в дочернем методе
     * - Изменяет входящий массив $dbData
     * @param array $dbData результат выборки с БД
     * @param string $table
     * @param string $primaryCol
     * @param string $id обработан esc_sql()
     */
    protected function filterDbData(&$dbData, $table, $primaryCol, $id)
    {
    }


    protected function getCache($id)
    {
        if ($cache = $this->getObCache($id)) {
            return $cache;
        }
        if (!$this->cacheTime) {
            return false;
        }
        if ($cache = $this->getRedisCache($id)) {
            $this->setObCache($id, $cache);
            return $cache;
        }
        return false;
    }


    private function getObCache($id)
    {
        if (!isset(self::$obCache[$this->cacheKey])) {
            return false;
        }
        if (!isset(self::$obCache[$this->cacheKey][$id])) {
            return false;
        }
        return self::$obCache[$this->cacheKey][$id];
    }


    private function getRedisCache($id)
    {
        $res = Redis::get($this->cacheKey . $id);
        return $res ? $res : false;
    }


    private function setCache($id, $value)
    {
        $this->setObCache($id, $value);
        $this->setRedisCache($id, $value);
    }


    private function setObCache($id, $value)
    {
        if (!isset(self::$obCache[$this->cacheKey])) {
            self::$obCache[$this->cacheKey] = [];
        }
        self::$obCache[$this->cacheKey][$id] = $value;
    }


    private function setRedisCache($id, $value)
    {
        if (!$this->cacheTime) {
            return;
        }
        Redis::set($this->cacheKey . $id, $value, $this->cacheTime);
    }


    function flushCache($all = false)
    {
        if ($all) {
            self::$obCache = [];
            Redis::flush($this->cacheKey);
            return;
        }

        $id = $this->getId();

        if (!$id) {
            return;
        }

        if (isset(self::$obCache[$id])) {
            unset(self::$obCache[$id]);
        }

        Redis::delete($this->cacheKey . $id);
    }


    function isDraft()
    {
        return $this->getStatus() === 'draft';
    }

    function isPublish()
    {
        return $this->getStatus() === 'publish';
    }

    function isTrash()
    {
        return $this->getStatus() === 'trash';
    }


    /**
     * - Удаляет запись либо переносит в корзину
     * 
     * @return bool
     */
    function maybeDelete()
    {
        $settings = Main::get($this->settingsKey);

        if (!$settings) {
            return false;
        }

        if (!$settings->hasTrash() || $this->isTrash()) {
            return $this->delete();
        }

        return $this->setStatus('trash');
    }


    /**
     * - Публикует запись если поддерживаются статусы
     *   и есть статус publish
     * 
     * @return bool
     */
    function publish()
    {
        return $this->setStatus('publish');
    }


    /**
     * - Удаляет запись по ID
     * @return bool
     */
    function delete()
    {
        global $wpdb;

        $table = $this->table;
        $col = $this->primaryCol;
        $id = $this->getId();

        if (!$id) {
            return false;
        }

        if ($wpdb->query("DELETE FROM `$table` WHERE `$col` = '$id'")) {
            Main::flushCache($this->settingsKey, $id);
            return true;
        }

        return false;
    }


    /**
     * - Обновляет либо создает запись
     * @param array $values
     *   - ключ = название колонки в базе данных
     *     принимаются алиясы такие как id и status
     *     вместо вактических названий колонок
     *   - значение = значение для записи
     * @return bool
     */
    function save($values)
    {
        $filterValues = [];

        $statusCol = Main::get($this->settingsKey)->getStatusCol();
        $statusColName = '';
        if ($statusCol) {
            $statusColName = $statusCol->getName();
        }

        foreach ($values as $col => $val) {
            if ($col === 'id') {
                $filterValues[$this->primaryCol] = $val;
                continue;
            }
            if ($col === 'status') {
                if ($statusColName) {
                    $val = $statusCol->getStatusValue($val);
                    $filterValues[$statusColName] = $val;
                }
                continue;
            }
            $filterValues[$col] = $val;
        }

        return $this->update($filterValues);
    }


    /**
     * - Обновляет либо добавляет запись в бд и текущий объект
     * @param array $values
     *   ключ = название колонки в бд (не фильтрует альасы такие как id или status)
     *   значение = значение для записи
     * @return bool
     */
    function update($values)
    {
        global $wpdb;

        $id = $this->getId();

        $filterValues = $this->filterUpdateValues($values);

        if ($id) {
            $update = [];

            foreach ($filterValues as $col => $val) {
                if ($val === 'NULL') {
                    $update[] = "`$col` = NULL";
                } else {
                    $update[] = "`$col` = '$val'";
                }
            }

            $sqlUpdate = implode(', ', $update);

            $sql = "UPDATE `{$this->table}` SET {$sqlUpdate} WHERE `{$this->primaryCol}` = '{$id}'";
        } else {
            $cols = array_keys($filterValues);
            $vals = array_values($filterValues);

            foreach ($vals as &$valItem) {
                if ($valItem === 'NULL') {
                    $valItem = 'NULL';
                } else {
                    $valItem = "'$valItem'";
                }
            }

            $sqlCols = "`" . implode("`,`", $cols) . "`";
            $sqlValues = implode(',', $vals);

            $sql = "INSERT INTO `{$this->table}` ($sqlCols) VALUES ($sqlValues)";
        }

        if ($wpdb->query($sql) === false) {
            return false;
        }

        if (!$id) {
            $id = $wpdb->insert_id;
        }

        $this->hookAfterUpdate($id);

        Main::flushCache($this->settingsKey, $id);

        $this->dbResult = $this->getDbInfo($id);


        return true;
    }


    /**
     * - Хук после обновления / добавления сущности
     * - Вызывается перед сбросам кэша и заполнением объекта
     * @param int $id ID добавленно/обновленной сущности
     */
    protected function hookAfterUpdate($id)
    {
    }


    /**
     * - Подготавливает массив данных для сохранения в БД
     * - Вызывается при сохранении элемента в админке
     * @param array $values массив с данными ACF
     * @return array массив для записи в БД
     */
    protected function filterAcfValues($values)
    {
        $res = [];

        foreach ($values as $key => $val) {
            if (!is_array($val)) {
                if (isset($this->acfCompare[$key])) {
                    $res[$this->acfCompare[$key]] = $val;
                }
                continue;
            }
            foreach ($val as $secondKey => $val) {
                $cKey = $key . '/' . $secondKey;
                if (!is_array($val)) {
                    if (isset($this->acfCompare[$cKey])) {
                        $res[$this->acfCompare[$cKey]] = $val;
                    }
                    continue;
                }
            }
        }

        if ($statusColName = Main::get($this->settingsKey)->getStatusColName()) {
            if (isset($res[$statusColName])) {
                $res[$statusColName] = $this->getStatusValue($res[$statusColName]);
            }
        }

        return $res;
    }


    protected function getStatusValue($status)
    {
        $sets = Main::get($this->settingsKey);

        if (!$sets) {
            return false;
        }

        $col = $sets->getStatusCol();

        if (!$col) {
            return false;
        }

        return $col->getStatusValue($status);
    }


    /**
     * - Меняет статус
     * @param string $status publish|draft|trash
     */
    function setStatus($status)
    {
        // не меняем если статус уже установлен
        if ($status === $this->getStatus()) {
            return true;
        }

        $settings = Main::get($this->settingsKey);
        $statusCol = $settings->getStatusCol();
        $id = $this->getId();

        if (!$id || !$statusCol) {
            return false;
        }

        $name = $statusCol->getName();

        $value = $statusCol->getStatusValue($status);

        if ($value === false) {
            return false;
        }

        return $this->update([$name => $value]);
    }


    /**
     * - Сохраняет элемент с админки
     * @param array $values массив ACF полей со значениями
     */
    function adminSave($values)
    {
        $res = $this->filterAcfValues($values);
        $this->update($res);
    }


    /**
     * - Фильтрет значение которые пришли с ACF полей
     * @param array $values результат выполнения filterAcfValues()

     * @return array массив отфильтрованных элементов
     */
    protected function filterUpdateValues($values)
    {
        $filterValues = [];

        $sets = Main::get($this->settingsKey);

        foreach ($values as $colName => $val) {
            if ($colName === $this->primaryCol) {
                continue;
            }
            if (!$val && $col = $sets->getColumn($colName)) {
                if ($def = $col->getDefault()) {
                    $val = $def;
                }
            }
            if ($val === 'CURRENT_TIMESTAMP') {
                $val = date('Y-m-d H:i:s');
            }
            $filterValues[$colName] = $val;
        }

        return $filterValues;
    }


    /**
     * - Устанавливает значение ACF полям
     * - Используется на странице редактирования элемента
     * 
     * @param array $fields массив ACF полей
     * 
     * @return array поля со значениями
     */
    function adminSetAcfValues($fields)
    {
        foreach ($fields as &$field) {
            if (empty($field['name'])) {
                continue;
            }
            if (!isset($field['sub_fields'])) {
                if (empty($this->acfCompare[$field['name']])) {
                    continue;
                }
                $colName = $this->acfCompare[$field['name']];
                $value = $this->get($colName);
                if ($field['name'] === 'press_status') {
                    $value = $this->getStatus();
                }
                $field['value'] = $value;
                continue;
            }
            foreach ($field['sub_fields'] as &$subField) {
                if (empty($subField['name'])) {
                    continue;
                }
                $name = "{$field['name']}/{$subField['name']}";
                if (empty($this->acfCompare[$name])) {
                    continue;
                }
                if (!isset($field['value'])) {
                    $field['value'] = [];
                }
                $colName = $this->acfCompare[$name];
                $field['value'][$subField['key']] = $this->get($colName);
            }
        }
        return $fields;
    }


    /**
     * - Получает статус элемента
     * @return string publish|draft|trash
     */
    function getStatus()
    {
        $val = $this->get('status');

        if ($val === null) {
            return 'publish';
        }

        return Main::get($this->settingsKey)->getStatusCol()->getStatusKey($val);
    }


    /**
     * - Получает значение колонки для вывода в ячейки в админке
     * @return string
     */
    function getAdminValue($colName)
    {
        $val = $this->get($colName);
        if ($val ===  null) {
            return '';
        }
        if ($val === 'NULL' || $val === 'CURRENT_TIMESTAMP') {
            return '';
        }
        if (is_array($val)) {
            return implode(', ', $val);
        }
        return $val;
    }


    /**
     * - Получает значение для фильтра в админке
     * @param string $colName
     * @return string
     */
    function getAdminFilterValue($colName)
    {
        return $this->get($colName);
    }


    /**
     * - Получает ключ для фильтра в админке
     * @var string $colName
     * @return string
     */
    function getAdminFilterName($colName)
    {
        return "filter[{$colName}]";
    }


    /**
     * @return string|int|null
     */
    function getId()
    {
        return $this->get('id');
    }


    /**
     * - Получает ссылку на публичную часть элемента
     * - переопределяется в дочернем классе
     */
    function getPublicUrl()
    {
        return '';
    }


    /**
     * - Получает ссылку на редактирование в админке
     */
    function getAdminUrl()
    {
        $key = $this->settingsKey;
        return '/wp-admin/admin.php?page=press_' . $key . '_single&id=' . $this->getId();
    }


    /**
     * - Получает ключ настроек сущности
     * @return string
     */
    function getSettingsKey()
    {
        return $this->settingsKey;
    }
}
