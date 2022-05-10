<?php

use Vnet\Entity\Main;

require __DIR__ . '/class-news.php';
require __DIR__ . '/class-news-query.php';
require __DIR__ . '/class-category.php';
require __DIR__ . '/class-category-query.php';
require __DIR__ . '/class-tag.php';
require __DIR__ . '/class-tag-query.php';


Main::register('news', [
    'table' => 'press_lenta_content',
    'query' => '\Vnet\Entity\News_Query',
    'entity' => '\Vnet\Entity\News',
    'cacheTimeSingle' => 3600,
    'cacheTimeQuery' => 3600,
    'menuIco' => 'dashicons-format-aside',
    'menuPosition' => 5.1,
    'labels' => [
        'name' => 'Новости'
    ],
    'columns' => [
        'title' => [
            'label' => 'Заголовок',
            'admin' => true,
            'sort' => true,
            'filter' => false,
            'search' => true,
            'adminMain' => true,
            'isName' => true
        ],
        'id' => [
            'primary' => true,
            'label' => 'ID',
            'sort' => true,
            'admin' => true,
            'width' => '12%'
        ],
        'state' => [
            'label' => 'Статус',
            'sort' => false,
            'admin' => true,
            'sort' => true,
            'status' => ['publish' => '1', 'draft' => '0', 'trash' => '2'],
            'width' => '12%'
        ],
        'rubid' => [
            'label' => 'Рубрика',
            'sort' => true,
            'admin' => true,
            'filter' => true,
            'default' => '0',
            'width' => '12%'
        ],
        'created' => [
            'label' => 'Создан',
            'admin' => true,
            'sort' => true,
            'filter' => false,
            'search' => false,
            'default' => 'CURRENT_TIMESTAMP',
            'width' => '15%'
        ],
        'tags' => [
            'label' => 'Метки',
            'admin' => true
        ],
        'text' => [
            'label' => 'Контент',
            'admin' => false
        ],
        'homepage_image' => [
            'admin' => false
        ],
        'topnews' => [
            'admin' => false
        ],
        'photo_ico' => [
            'admin' => false
        ],
        'video_ico' => [
            'admin' => false
        ],
        'live_ico' => [
            'admin' => false
        ],
        'hits' => [
            'admin' => false
        ],
        'created_by' => [
            'admin' => false
        ],
        'added_by' => [
            'admin' => false
        ],
        'last_changed_by' => [
            'admin' => false
        ],
        'yandex' => [
            'admin' => false
        ],
        'twitter' => [
            'admin' => false
        ],
        'meta_title' => [
            'admin' => false
        ],
        'meta_description' => [
            'admin' => false
        ]
    ]
]);



Main::register('category', [
    'table' => 'press_lenta_rubrics',
    'query' => '\Vnet\Entity\Category_Query',
    'entity' => '\Vnet\Entity\Category',
    'cacheTimeSingle' => 3600,
    'cacheTimeQuery' => 3600,
    'menuParent' => 'news',
    'labels' => [
        'name' => 'Рубрики'
    ],
    'columns' => [
        'title' => [
            'label' => 'Название',
            'admin' => true,
            'sort' => true,
            'search' => true,
            'adminMain' => true,
            'isName' => true
        ],
        'id' => [
            'label' => 'ID',
            'admin' => true,
            'sort' => true,
            'primary' => true
        ],
        'url' => [
            'label' => 'Ярлык',
            'admin' => true,
            'sort' => true
        ]
    ]
]);

Main::register('tag', [
    'table' => 'press_tags',
    'query' => '\Vnet\Entity\Tag_Query',
    'entity' => '\Vnet\Entity\Tag',
    'cacheTimeSingle' => 3600,
    'cacheTimeQuery' => 3600,
    'menuParent' => 'news',
    'labels' => [
        'name' => 'Метки'
    ],
    'columns' => [
        'tag_title' => [
            'label' => 'Заголовок',
            'admin' => true,
            'adminMain' => true,
            'search' => true,
            'isName' => true
        ],
        'tag_id' => [
            'label' => 'ID',
            'admin' => true,
            'search' => false,
            'sort' => true,
            'primary' => true
        ],
        'tag_url' => [
            'label' => 'Ярлык',
            'admin' => true,
            'sort' => false,
            'search' => false
        ],
        'parent_tag_id' => [
            'label' => 'Родительский',
            'filter' => true,
            'admin' => true,
            'sort' => true,
            'search' => false
        ],
        'tag_articles_count' => [
            'label' => 'Кол-во статей',
            'admin' => true,
            'sort' => true,
            'search' => false
        ],
        'tag_news_count' => [
            'admin' => false,
        ],
        'is_visible_on_header' => [
            'admin' => false
        ]
    ]
]);
