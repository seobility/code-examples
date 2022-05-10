<?php

namespace Vnet\Entity;


class News_Query extends Entity_Query
{
    /**
     * - Устанавливается в классе Settings
     */
    static $settingsKey;

    private $tableRelTags = 'press_news_to_tags';


    function __construct()
    {
        global $wpdb;
        $this->tableRelTags = $wpdb->prefix . $this->tableRelTags;
        parent::__construct(self::$settingsKey);
    }


    protected function getSqlJoin($filter)
    {
        if (!isset($filter['tags'])) {
            return '';
        }
        return "INNER JOIN `{$this->tableRelTags}` ON 
        `{$this->table}`.`{$this->primaryCol}` = `{$this->tableRelTags}`.`article_id`";
    }


    protected function getSqlWhere($filter = [])
    {
        $search = $this->getSqlSearch();
        $status = $this->getSqlStatus();
        $idin = $this->getSqlIdIn();
        $rub = $this->getSqlRub($filter);
        $tag = $this->getSqlTag($filter);
        $top = $this->getSqlTop($filter);
        $created = $this->getSqlCreated($filter);
        $addedBy = $this->getSqlAdded($filter);
        return $this->formatSqlWhere([$idin, $search, $rub, $tag, $top, $status, $created, $addedBy]);
    }


    private function getSqlTop($filter)
    {
        if (empty($filter['topnews'])) {
            return '';
        }
        return "`topnews` = '1'";
    }


    private function getSqlAdded($filter)
    {
        if (empty($filter['added_by'])) {
            return '';
        }

        $addedBy = esc_sql($filter['added_by']);
        return "`added_by` = '$addedBy'";
    }


    private function getSqlCreated($filter)
    {
        if (empty($filter['created'])) {
            return '';
        }
        $date = date('Y-m-d', strtotime($filter['created']));
        return "`created` LIKE '$date%'";
    }


    private function getSqlRub($filter)
    {
        if (!isset($filter['rubid'])) {
            return '';
        }

        $rubid = $filter['rubid'];

        if (!is_array($rubid)) {
            $rubid = explode(',', $rubid);
        }

        $rubid = esc_sql($rubid);

        if (count($rubid) === 1) {
            return "`rubid` = '{$rubid[0]}'";
        }

        $sqlRubid = "'" . implode("','", $rubid) . "'";

        return "`rubid` IN ({$sqlRubid})";
    }


    private function getSqlTag($filter)
    {
        if (!isset($filter['tags'])) {
            return '';
        }

        $tags = $filter['tags'];

        if (is_string($tags)) {
            $tags = explode(',', $tags);
        }

        if (!is_array($tags) || count($tags) === 0) {
            return '';
        }

        $tags = esc_sql($tags);

        if (count($tags) === 1) {
            return "`{$this->tableRelTags}`.`tag_id` = '{$tags[0]}'";
        }

        $sqlTags = implode("','", $tags);

        return "`{$this->tableRelTags}`.`tag_id` IN ('{$sqlTags}')";
    }
}
