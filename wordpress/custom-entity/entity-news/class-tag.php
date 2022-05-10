<?php

namespace Vnet\Entity;


class Tag extends Entity
{
    /**
     * - Устанавливается в классе Settings
     */
    static $settingsKey;

    protected $acfCompare = [
        'press_tag_title' => 'tag_title',
        'press_tag_slug' => 'tag_url',
        'press_tag_parent_tag_id' => 'parent_tag_id',
        'press_tag_is_visible_on_header' => 'is_visible_on_header',
    ];


    function __construct($id = 0)
    {
        parent::__construct(self::$settingsKey, $id);
    }

    function getAdminValue($colName)
    {
        if ($colName === 'parent_tag_id') {
            if ($parent = $this->get('parent_tag_id')) {
                $item = new self($parent);
                if ($item->getId()) {
                    return $item->getName();
                }
            }
            return "&mdash;";
        }
        return parent::getAdminValue($colName);
    }
}
