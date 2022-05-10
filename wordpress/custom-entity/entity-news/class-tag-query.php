<?php

namespace Vnet\Entity;


class Tag_Query extends Entity_Query
{

    /**
     * - Устанавливается в классе Settings
     */
    static $settingsKey;


    function __construct()
    {
        parent::__construct(self::$settingsKey);
    }


    protected function getSqlWhere($filter = [])
    {
        return $this->formatSqlWhere([
            $this->getSqlIdIn(),
            $this->getSqlSearch(),
            $this->getWhereVisibleOnHeader($filter),
            $this->getWhereParent($filter)
        ]);
    }


    private function getWhereVisibleOnHeader($filter)
    {
        if (!isset($filter['is_visible_on_header'])) {
            return '';
        }
        $isVisible = esc_sql($filter['is_visible_on_header']);
        return "`is_visible_on_header` = '$isVisible'";
    }


    private function getWhereParent($filter)
    {
        if (!isset($filter['parent_tag_id'])) {
            return '';
        }
        $parent = esc_sql($filter['parent_tag_id']);
        if (!$parent) {
            return "`parent_tag_id` = '0'";
        }
        return "`parent_tag_id` = '$parent'";
    }
}
