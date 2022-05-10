<?php

namespace Vnet\Entity;


class Labels
{

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $singluarName;

    /**
     * @var string
     */
    public $addNew;

    /**
     * @var string
     */
    public $addNewItem;

    /**
     * @var string
     */
    public $editItem;

    /**
     * @var string
     */
    public $newItem;

    /**
     * @var string
     */
    public $viewItem;

    /**
     * @var string
     */
    public $searchItems;

    /**
     * @var string
     */
    public $notFound;

    /**
     * @var string
     */
    public $notFoundInTrash;

    /**
     * @var string
     */
    public $parentItemColon;

    /**
     * @var string
     */
    public $menuName;


    /**
     * @param array $labels
     */
    function __construct($labels = [])
    {
        foreach ($labels as $key => $val) {
            if (property_exists($this, $key)) {
                $this->$key = $val;
            }
        }

        $props = get_class_vars(__CLASS__);

        foreach ($props as $prop => $val) {
            if (!$this->$prop) {
                $this->$prop = $this->name;
            }
        }
    }
}
