<?php

namespace Vnet\Api\Entity;

use Vnet\Api\Request\Rates;
use Vnet\Api\Request\Stocks;
use Vnet\Business_User;

class Stock
{

    /**
     * @var string
     */
    public $id = '';

    /**
     * @var string
     *   - checking на модерации
     *   - valid прошла модерацию но не оплачена
     *   - payed прошла модерацию и оплачена
     *   - denied не прошла модерацию
     */
    public $status = '';

    /**
     * @var string
     */
    public $creatorId = '';

    /**
     * @var string
     */
    public $title = '';

    /**
     * @var Location[]
     */
    public $location = [];

    /**
     * @var string
     */
    public $description = '';

    /**
     * @var string[]
     */
    public $tags = [];

    /**
     * - Промо код
     * @var string
     */
    public $secret = '';

    /**
     * @var File[]
     */
    public $images = [];

    /**
     * - Дата начала
     * @var Date
     */
    public $start = '';

    /**
     * - Дата окончания
     * @var Date
     */
    public $end = '';

    /**
     * - Дата апрува
     * @var Date
     */
    public $approvedAt = '';

    /**
     * - Рейтинг акции (1-5)
     * @var float
     */
    public $rate = 0.0;

    /**
     * - Кол-во голосов в рейтинге
     * @var int
     */
    public $ratesAmount = 0;

    /**
     * - Стоимость акции
     * @var Price
     */
    public $price = null;

    /**
     * - Кол-во лайков
     * @var int
     */
    public $likes = 0;

    /**
     * - Кол-во просмотров
     * @var int
     */
    public $views = 0;

    /**
     * - Кол-во просмотров промокода
     * @var int
     */
    public $codeViews = 0;

    /**
     * @var \Vnet\Api\Entity\Statistic[]
     */
    private $statistics = null;

    /**
     * - Причина отклонения акции
     * @var string
     */
    private $moderationMessage = null;


    /**
     * @param array $static статическая информация
     * @param array $dynamic динамическая информаця (кол-во просмотров, лайки и т.д.)
     * @param float|int|string $price стоимость акции
     */
    function __construct($static, $dynamic, $price)
    {
        if (!empty($static['id'])) {
            $this->id = $static['id'];
        }

        if (!empty($static['status'])) {
            $this->status = $static['status'];
        }

        if (!empty($static['creator_id'])) {
            $this->creatorId = $static['creator_id'];
        }

        if (!empty($static['title'])) {
            $this->title = $static['title'];
        }

        if (!empty($static['location'])) {
            foreach ($static['location'] as $loc) {
                $this->location[] = new Location($loc);
            }
        }

        if (!empty($static['description'])) {
            $this->description = $static['description'];
        }

        if (!empty($static['tags'])) {
            $this->tags = $static['tags'];
        }

        if (!empty($static['secret'])) {
            $this->secret = $static['secret'];
        }

        if (!empty($static['images'])) {
            foreach ($static['images'] as $img) {
                $this->images[] = new File($img);
            }
        }

        if (!empty($static['start'])) {
            $this->start = new Date($static['start']);
        }

        if (!empty($static['end'])) {
            $this->end = new Date($static['end']);
        }

        if (!empty($static['approved_at'])) {
            $this->approvedAt = new Date($static['approved_at']);
        }

        if (!empty($dynamic['rate'])) {
            $this->rate = $dynamic['rate'];
        }

        if (!empty($dynamic['rates_amount'])) {
            $this->ratesAmount = $dynamic['rates_amount'];
        }

        if (!empty($dynamic['likes'])) {
            $this->likes = $dynamic['likes'];
        }

        if (!empty($dynamic['views'])) {
            $this->views = $dynamic['views'];
        }

        if (!empty($dynamic['promocode_view'])) {
            $this->codeViews = $dynamic['promocode_view'];
        }

        $this->price = new Price($price);
    }


    /**
     * @return string
     */
    function getMainImageUrl()
    {
        if (!$this->images) {
            return '';
        }
        return $this->images[0]->url;
    }


    /**
     * @return \Vnet\Api\Entity\File[]
     */
    function getImages()
    {
        return $this->images;
    }


    /**
     * - Проеряет актуальная ли акция
     * @return bool
     */
    function isActual()
    {
        if (!$this->end->unix) {
            return false;
        }
        return $this->end->unix >= time();
    }


    /**
     * - Проверяет завершена ли акция
     * @return bool
     */
    function isFinish()
    {
        if (!$this->end->api) {
            return false;
        }
        return $this->end->unix < time();
    }


    /**
     * - Проверяет находится ли акция на модерации
     * @return bool
     */
    function isOnModeration()
    {
        return $this->status === 'checking';
    }


    /**
     * - Проверяет прошла ли акция модерацию
     * @return bool
     */
    function isModerated()
    {
        return $this->status === 'valid';
    }


    function hasStartDate()
    {
        return !!$this->start->api;
    }


    function isPayed()
    {
        return $this->status === 'payed';
    }

    function isDenied()
    {
        return $this->status === 'denied';
    }


    function getId()
    {
        return $this->id;
    }


    /**
     * - Проверяет надо ли оплатить акцию
     */
    function needPay()
    {
        $profile = Business_User::getProfile();

        // если не прошла модерацию
        if (!$this->isModerated()) {
            return false;
        }

        // если нет даты публикации
        if ($this->hasStartDate()) {
            return false;
        }

        // партнерам оплачивать не надо
        if ($profile->hasPartnership() || $profile->hasFreeStock()) {
            return false;
        }

        return true;
    }


    /**
     * - Необходимо установить дату начала и окончания
     */
    function needSetDate()
    {
        $profile = Business_User::getProfile();

        if ($profile->hasPartnership() && $this->isModerated() && !$this->hasStartDate()) {
            return true;
        }

        if (($this->isPayed() && !$this->hasStartDate())) {
            return true;
        }

        if ($profile->hasFreeStock() && $this->isModerated() && !$this->hasStartDate()) {
            return true;
        }

        return false;
    }


    /**
     * - Получает сообщение модерации
     * @return string
     */
    function getModerationAnswer()
    {
        if (!$this->isDenied()) {
            return '';
        }
        if ($this->moderationMessage === null) {
            $this->moderationMessage = Stocks::getDeniedMessage($this->id, Business_User::getToken());
        }
        return $this->moderationMessage;
    }


    /**
     * - Проверяет опубликована ли акция
     * @return bool
     */
    function isPublished()
    {
        // если не установлено время начала акции
        if (!$this->start->api) {
            return false;
        }

        // если еще на модерации
        if ($this->status === 'checking') {
            return false;
        }

        // если не прошла модерацию
        if ($this->status === 'denied') {
            return false;
        }

        return $this->isActual();
    }


    /**
     * - Получает кол-во дней до завершения акции
     * @return int
     */
    function getRemainingDays()
    {
        if (!$this->end->unix) {
            return 0;
        }
        return Date::getDaysDiff($this->end->unix, time());
    }


    function getTitle()
    {
        return $this->title;
    }


    function getDescription()
    {
        return $this->description;
    }

    function getSecret()
    {
        return $this->secret;
    }

    function getTagsStr()
    {
        if (!$this->tags) {
            return '';
        }
        return implode(', ', $this->tags);
    }


    /**
     * @return string
     */
    function getDurationRange()
    {
        $data = [];

        if ($this->start->api) {
            $data[] = $this->start->shortDatetime;
        }

        if ($this->end->api) {
            $data[] = $this->end->shortDatetime;
        }

        return implode(' - ', $data);
    }


    /**
     * - Формирует массив аргументов из данныз объекта
     * - Используется для обновления акции
     */
    function getArguments()
    {
        $addr = $this->location;

        $addresses = [];

        if ($addr) {
            foreach ($addr as $item) {
                $addresses[] = [
                    'additional_info' => $item->additionalInfo,
                    'city' => $item->city,
                    'coordinates' => $item->coordinates,
                    'country' => $item->country,
                    'street' => $item->street,
                    'title' => $item->title,
                    'type' => $item->type
                ];
            }
        }

        $params = [
            'approved_at' => $this->approvedAt->api,
            'creator_id' => $this->creatorId,
            'description' => $this->description,
            'end' => $this->end->api,
            'id' => $this->id,
            'images' => $this->images,
            'location' => $addresses,
            'secret' => $this->secret,
            'start' => $this->start->api,
            'status' => $this->status,
            'tags' => $this->tags,
            'title' => $this->title
        ];

        return $params;
    }


    /**
     * - Получение статистики акции
     */
    function getStatistics($token, $from = null, $to = null)
    {
        if ($this->statistics === null) {
            $this->statistics = Stocks::getStatistic($this->id, $token, $from, $to);
        }
        return $this->statistics;
    }
}
