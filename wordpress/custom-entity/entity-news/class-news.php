<?php

namespace Vnet\Entity;


class News extends Entity
{
    /**
     * - Устанавливается в классе Settings
     */
    static $settingsKey;

    protected $acfCompare = [
        'press_author' => 'created_by',
        'press_publish_date' => 'created',
        'press_news_cat' => 'rubid',
        'press_img' => 'homepage_image',
        'press_status' => 'state',
        'press_news_author' => 'added_by',
        'press_news_content/title' => 'title',
        'press_news_content/content' => 'text',
        'press_news_seo/meta_title' => 'meta_title',
        'press_news_seo/meta_desc' => 'meta_description'
    ];

    /**
     * - Используется для обновления связи с тегами после сохранения сущности
     * @var int[]
     */
    private $tagsToUpdate = null;

    private $newsPagePath = '/news/';
    private $newsAdminPath = '/wp-admin/admin.php?page=press_news_single';
    private $imagePath = '/images/stories/';

    /**
     * - Таблица связи новости => метки
     * @var string
     */
    private $tableRelTags = 'press_news_to_tags';


    function __construct($id = 0)
    {
        global $wpdb;
        $this->tableRelTags = $wpdb->prefix . $this->tableRelTags;

        parent::__construct(self::$settingsKey, $id);
    }


    protected function filterDbData(&$dbData, $table, $primaryCol, $id)
    {
        global $wpdb;

        $dbData['tags'] = [];

        $tagsRes = $wpdb->get_results("SELECT `tag_id` FROM `{$this->tableRelTags}` WHERE `article_id` = '$id'", ARRAY_A);

        if (!$tagsRes || is_wp_error($tagsRes)) {
            return;
        }

        $dbData['tags'] = array_column($tagsRes, 'tag_id');
    }


    protected function filterAcfValues($values)
    {
        $res = parent::filterAcfValues($values);

        $tags = [];

        if (!empty($values['news_tags'])) {
            $tags = $values['news_tags'];
        }

        $this->tagsToUpdate = $tags;

        return $res;
    }


    /**
     * - После обновления сущности
     *   обновляем связи с тегами
     */
    protected function hookAfterUpdate($id)
    {
        global $wpdb;

        if (!$id) {
            return;
        }

        if ($this->tagsToUpdate === null) {
            return;
        }

        $tagsToUpdate = $this->tagsToUpdate;
        $this->tagsToUpdate = null;

        $wpdb->query("DELETE FROM `{$this->tableRelTags}` WHERE `article_id` = '{$id}'");

        if ($wpdb->last_error) {
            return;
        }

        foreach ($tagsToUpdate as $tagId) {
            $wpdb->query("INSERT INTO `{$this->tableRelTags}` (`article_id`, `tag_id`) VALUES ('{$id}', '{$tagId}')");
        }
    }


    function adminSetAcfValues($values)
    {
        foreach ($values as &$val) {
            if ($val['name'] != 'news_tags') {
                continue;
            }

            $tags = $this->get('tags', []);
            $val['value'] = [];

            foreach ($tags as $tagId) {
                $val['value'][] = $tagId;
            }

            break;
        }

        return parent::adminSetAcfValues($values);
    }


    function getAdminValue($colName)
    {
        if ($colName === 'rubid') {
            if ($id = $this->get('rubid')) {
                return (new Category($id))->get('title');
            }
            return '';
        }

        if ($colName === 'tags') {
            $val = $this->get('tags');

            if (!$val) {
                return '';
            }

            $tagsQuery = (new Tag_Query)->filter(['idin' => $val]);

            $res = [];
            while ($tag = $tagsQuery->fetch()) {
                $res[] = sprintf(
                    '<a href="%s">%s</a>',
                    'admin.php?page=press_' . self::$settingsKey . '&filter[tags]=' . $tag->getId(),
                    $tag->getName()
                );
            }
            return implode(', ', $res);
        }

        return parent::getAdminValue($colName);
    }


    /**
     * @return string
     */
    function getCreatedDate($key)
    {
        if (!$this->getCreated()) {
            return '';
        }

        if ($key === 'time') {
            return date("H:i", strtotime($this->getCreated()));
        }

        if ($key === 'date') {
            return date_i18n("j F Y", strtotime($this->getCreated()));
        }

        return '';
    }


    /**
     * @return string
     */
    function getNewsPath()
    {
        return $this->getPublicUrl();
    }


    /**
     * @return string
     */
    function getPublicUrl()
    {
        if (!$this->getRubId()) {
            return '#';
        }

        $cat = new Category($this->getRubId());

        if (!$cat->getId()) {
            return '#';
        }

        return $this->newsPagePath . $cat->get('url') . '/' . $this->getId();
    }


    /**
     * @return string
     */
    function getNewsAdminPath()
    {
        return $this->newsAdminPath  . '&id=' . $this->getId();
    }


    /**
     * @return string
     */
    function getImagePath()
    {
        return $this->get('homepage_image') ? $this->imagePath . $this->get('homepage_image') : '';
    }


    /**
     * @return string
     */
    function getContent()
    {
        return $this->get('text') ? $this->get('text') : '';
    }


    /**
     * @return string
     */
    function getAuthor()
    {
        return $this->get('created_by') ? $this->get('created_by') : '';
    }


    /**
     * @return string[]|Tag[]
     */
    function getTags($full = false)
    {
        $val = $this->get('tags');
        if (!$val) {
            return [];
        }

        $tagsQuery = (new Tag_Query)->filter(['idin' => $val]);

        $res = [];
        while ($tag = $tagsQuery->fetch()) {
            $res[] = !$full ? $tag->getName() : $tag;
        }

        return $res ? $res : [];
    }


    /**
     * @return int
     */
    function getViews()
    {
        return $this->get('hits') ? (int)$this->get('hits') : 0;
    }


    /**
     * @return string
     */
    function getTitle()
    {
        return $this->get('title') ? $this->get('title') : '';
    }


    /**
     * @return bool
     */
    function isTop()
    {
        return !empty($this->get('topnews'));
    }


    /**
     * @return int|null
     */
    function getRubId()
    {
        return $this->get('rubid') ? (int)$this->get('rubid') : null;
    }

    /**
     * @return string
     */
    function getCreated()
    {
        return $this->get('created') ? $this->get('created') : '';
    }

    /**
     * @return string
     */
    function getRubTitle()
    {
        return $this->getRubId() ? $this->getAdminValue('rubid') : '';
    }

    /**
     * @return string
     */
    function getRubUrl()
    {
        $cat = new Category($this->getRubId());

        if (!$cat->getId()) {
            return '';
        }

        return $cat->get('url');
    }
}
