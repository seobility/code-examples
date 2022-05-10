<?php

namespace Vnet\Entity;


class Category extends Entity
{
    /**
     * - Устанавливается в классе Settings
     */
    static $settingsKey;

    private $categoryPagePath = '/news/';

    protected $acfCompare = [
        'press_cat_title' => 'title',
        'press_cat_slug' => 'url'
    ];


    function __construct($id = 0)
    {
        parent::__construct(self::$settingsKey, $id);
    }


    /**
     * - Получает категорию по полю url
     * @param string $url
     * @return false|self
     */
    static function getByUrl($url)
    {
        global $wpdb;
        $table = Main::get(self::$settingsKey)->getTable();
        $url = esc_sql($url);
        $query = "SELECT * FROM `$table` WHERE `url` = '$url' LIMIT 1";
        $res = $wpdb->get_results($query, ARRAY_A);
        if (!$res || is_wp_error($res)) {
            return false;
        }
        return new self($res[0]);
    }


    /**
     * - Получает id категорию по полю title
     * @param string $url
     * @return int
     */
    static function getIdByTitle($title)
    {
        global $wpdb;
        $table = Main::get(self::$settingsKey)->getTable();
        $title = esc_sql($title);
        $query = "SELECT `id` FROM `$table` WHERE `title` = '$title' LIMIT 1";
        $res = $wpdb->get_results($query, ARRAY_A);
        if (!$res || is_wp_error($res)) {
            return false;
        }
        return $res[0];
    }


    /**
     * @return string
     */
    function getTitle()
    {
        return $this->title ? $this->title : '';
    }


    /**
     * @return string
     */
    function getCategoryPath()
    {
        return $this->getPublicUrl();
    }

    /**
     * @return string
     */
    function getPublicUrl()
    {
        return $this->categoryPagePath . $this->get('url');
    }
}
