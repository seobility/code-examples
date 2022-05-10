<?php

namespace Vnet\Api\Entity;


class Message
{

    /**
     * @var string
     */
    public $id = '';

    /**
     * @var bool
     */
    public $byAdmin = false;

    /**
     * @var Date
     */
    public $createdAt = null;

    /**
     * @var string
     */
    public $targetId = '';

    /**
     * @var string
     */
    public $text = '';

    /**
     * - Просмотрено ли сообщение
     * @var bool
     */
    public $seen = false;


    /**
     * @param array $params
     */
    function __construct($params)
    {
        if (!empty($params['_id'])) {
            $this->id = $params['_id'];
        }

        $this->byAdmin = !empty($params['by_admin']);

        if (!empty($params['created_at'])) {
            $this->createdAt = new Date($params['created_at']);
        }

        if (!empty($params['target_id'])) {
            $this->targetId = $params['target_id'];
        }

        if (!empty($params['text'])) {
            $this->text = $params['text'];
        }

        $this->seen = !empty($params['seen']);
    }


    function isSeen()
    {
        return $this->seen;
    }


    function getId()
    {
        return $this->id;
    }
}
