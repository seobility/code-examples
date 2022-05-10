<?php

namespace Vnet\Api\Entity;


class Question
{

    /**
     * - ID вопроса
     * @var string
     */
    private $id = '';

    /**
     * - ID пользователя кто задал вопрос
     * @var string
     */
    private $creatorId = '';

    /**
     * - ID сущности к которой относится вопрос
     * @var string
     */
    private $targetId = '';

    /**
     * - Тело вопроса
     * @var string
     */
    private $text = '';

    /**
     * - Дата добавления
     * @var Date|null
     */
    private $timestamp = null;

    /**
     * - Ответ
     * @var Answer|null
     */
    private $answer = null;


    /**
     * @param array $params
     */
    function __construct($params)
    {
        if (!empty($params['id'])) {
            $this->id = $params['id'];
        }

        if (!empty($params['creator_id'])) {
            $this->creatorId = $params['creator_id'];
        }

        if (!empty($params['target_id'])) {
            $this->targetId = $params['target_id'];
        }

        if (!empty($params['text'])) {
            $this->text = $params['text'];
        }

        if (!empty($params['timestamp'])) {
            $this->timestamp = new Date($params['timestamp']);
        }

        if (!empty($params['answer'])) {
            $this->answer = new Answer($params['answer']);
        }
    }


    function getText()
    {
        return $this->text;
    }


    function getStrDate()
    {
        return $this->timestamp ? $this->timestamp->date : '';
    }

    function getId()
    {
        return $this->id;
    }

    /**
     * @return Answer|null
     */
    function getAnswer()
    {
        return $this->answer;
    }
}
