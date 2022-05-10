<?php

/**
 * - Базовый класс для работы с сущностями в админке
 */

namespace Vnet\Entity;

use Vnet\Core\Helper;
use Vnet\Router;
use WP_Admin_Bar;

class Admin
{

    private static $editId = null;
    private static $editKey = null;


    static function setup()
    {
        self::addHooks();
    }


    private static function addHooks()
    {
        if (!Main::has()) {
            return;
        }

        add_action('acf/form_data', [__CLASS__, 'renderEntityPublicUrl']);

        add_action('admin_menu', [__CLASS__, 'addListPages']);
        add_action('acf/init', [__CLASS__, 'addSinglePages']);
        add_action('acf/save_post', [__CLASS__, 'updateElement']);

        add_filter('acf/update_value', [__CLASS__, 'preventSaveOptions']);

        add_filter('acf/pre_render_fields', [__CLASS__, 'setAcfFieldsValues']);

        add_action('admin_head', [__CLASS__, 'hideEditPages']);

        add_action('wp_ajax_press_ajax_hook', [__CLASS__, 'processAjax']);

        add_action('acf/include_field_types', [__CLASS__, 'include_field']); // v5
        add_action('acf/register_fields', [__CLASS__, 'include_field']); // v4

        add_action('admin_bar_menu', [__CLASS__, 'addEditLink'], 100);

        add_filter('update_user_metadata', [__CLASS__, 'setMetaboxOrder'], 10, 4);
    }


    /**
     * - Устанавливает порядок мета боксов в админке
     *   на странице редактирования отдельного элемента сущности
     */
    static function setMetaboxOrder($val, $object_id, $meta_key, $meta_value)
    {
        if (!is_user_logged_in() || !is_admin()) {
            return null;
        }

        if (!preg_match("/^meta-box-order_.+_page_press_.*_single$/", $meta_key)) {
            return null;
        }

        return update_user_meta(get_current_user_id(), 'meta-box-order_acf_options_page', $meta_value);
    }


    static function renderEntityPublicUrl()
    {
        $sets = self::getCurrentSets();

        // это не страница редактирования элемента сущности
        if (!$sets) {
            return;
        }

        $url = $sets->newEntity($_GET['id'])->getPublicUrl();

        // сущность может не иметь публичной части
        if (!$url) {
            return;
        }
?>
        <div class="entity-url">
            <div id="edit-slug-box" style="padding-left: 0px;">
                <strong>Постоянная ссылка:</strong>
                <span id="sample-permalink">
                    <a href="<?= $url; ?>">
                        <?= $url; ?>
                    </a>
                </span>
            </div>
        </div>
        <?php
        // переносим под заголовок
        ?>
        <script>
            jQuery('.entity-url').parent().after(jQuery('.entity-url'));
        </script>
<?php
    }


    /**
     * - Добавляет пункт "редактировать" в toolbar на фронте
     * @param \WP_Admin_Bar $adminBar
     */
    static function addEditLink($adminBar)
    {
        if (!self::$editId || !self::$editKey) {
            return;
        }

        $sets = Main::get(self::$editKey);

        if (!$sets) {
            return;
        }

        $adminBar->add_menu([
            'id' => 'edit-entity',
            'title' => 'Изменить',
            'href' => $sets->newEntity(self::$editId)->getAdminUrl()
        ]);
    }


    /**
     * - Устанавливает ID сущности для кнопки "Изменить"
     *   в toolbar wp
     * @param string $key ключ настроек сущности
     * @param string|int $id ID элемента сущности
     */
    static function setEditId($key, $id)
    {
        self::$editKey = $key;
        self::$editId = $id;
    }


    /**
     * - Устанавливает значение acf полям
     * - используется при редактировании элемента
     * - Вызывается один раз на каждую групу полей
     * @param array $fields
     * @return array
     */
    static function setAcfFieldsValues($fields)
    {
        if (empty($_GET['id'])) {
            return $fields;
        }

        $settings = self::getCurrentSets();

        // это не страница редактирования сущности
        if (!$settings) {
            return $fields;
        }

        $entity = $settings->newEntity($_GET['id']);

        if (!$entity->getId()) {
            return $fields;
        }

        return $entity->adminSetAcfValues($fields);
    }


    /**
     * - Предотвращает сохранение данных
     *   на странице редактирования элемента
     */
    static function preventSaveOptions($value)
    {
        $settings = self::getCurrentSets(true);

        // это не страница редактирования элемента сущности
        if (!$settings) {
            return $value;
        }

        return null;
    }


    static function ajaxProcessAction()
    {
        $ids = Helper::getRequest('id');
        $type = Helper::getRequest('type');
        $settings = self::getCurrentSets(true);

        if (!$ids || !$type || !$settings) {
            self::theError();
        }

        $ids = explode(',', $ids);

        if ($type === 'delete') {
            foreach ($ids as $id) {
                $settings->newEntity($id)->maybeDelete();
            }
            self::theSuccess();
        }

        if ($type === 'restore') {
            foreach ($ids as $id) {
                $settings->newEntity($id)->publish();
            }
            self::theSuccess();
        }

        self::theError();
    }


    static function hideEditPages()
    {
        $entities = Main::getAll();
        echo '<style id="styleHideMenu">' . PHP_EOL;
        foreach ($entities as $entity) {
            $url = 'admin.php?page=press_' . $entity->getKey() . '_single';
            echo '.wp-submenu-wrap a[href="' . $url . '"] { display: none!important; }' . PHP_EOL;
        }
        echo '</style>' . PHP_EOL;
    }


    static function include_field($version = false)
    {
        // support empty $version
        if (!$version) $version = 4;

        // include
        include_once(ENTITY_PATH . '/acf-fields/class-relation-v' . $version . '.php');
        include_once(ENTITY_PATH . '/acf-fields/class-multi-relation-v' . $version . '.php');
    }


    static function processAjax()
    {
        $action = Helper::getRequest('press_action');

        if (!$action) {
            self::theError();
        }

        self::$action();
    }


    static function addListPages()
    {
        $entities = Main::getAll();

        foreach ($entities as $item) {
            $menuSlug = 'press_' . $item->getKey();
            $labels = $item->getLabels();

            if ($parent = $item->getMenuParent()) {
                add_submenu_page(
                    'press_' . $parent,
                    $labels->menuName,
                    $labels->name,
                    'edit_others_posts',
                    $menuSlug,
                    function () {
                        require ENTITY_PATH . '/templates/list-post.php';
                    }
                );
            } else {
                add_menu_page(
                    $labels->name,
                    $labels->menuName,
                    'edit_others_posts',
                    $menuSlug,
                    function () {
                        require ENTITY_PATH . '/templates/list-post.php';
                    },
                    $item->getMenuIco(),
                    $item->getMenuPosition()
                );
            }
        }
    }


    static function addSinglePages()
    {
        $entities = Main::getAll();

        foreach ($entities as $item) {
            $labels = $item->getLabels();
            $menuSlug = 'press_' . $item->getKey();
            $parentSlug = null;

            if ($parent = $item->getMenuParent()) {
                $parentSlug = 'press_' . $parent;
            }

            $acfSlug = $menuSlug . '_single';
            $acfParent = $menuSlug;

            if ($item->getMenuParent()) {
                $acfParent = $parentSlug;
            }

            acf_add_options_page([
                'page_title' => $labels->singluarName,
                'menu_title' => $labels->singluarName,
                'menu_slug' => $acfSlug,
                'parent_slug' => $acfParent
            ]);
        }
    }


    /**
     * - Обновляет|Создает элемент
     */
    static function updateElement()
    {
        if (empty($_POST['acf'])) {
            return;
        }

        $sets = self::getCurrentSets(true);

        if (!$sets) {
            return;
        }

        $values = self::prepareUpdateValues($_POST['acf']);

        $currentId = self::getCurrentElementId(true);

        $entity = $sets->newEntity()->set('id', $currentId);

        $entity->adminSave($values);

        $url = admin_url('admin.php') . '?page=press_' . $sets->getKey() . '_single&id=' . $entity->getId();
        // $url = admin_url('admin.php') . '?page=press_' . $sets->key . '_single';

        Header("Location: $url");

        exit;
    }


    /**
     * - Подготавливает массив с данными для обновления сущности
     * - Заменяет ключи в массиве $values
     *   на ключи соответственных acf полей
     * 
     * @param array $value
     * 
     * @return array
     */
    static function prepareUpdateValues($values)
    {
        global $wpdb;

        $keys = [];
        self::getAcfPostKeys($values, $keys);

        if (!$keys) {
            return [];
        }

        $keys = array_unique($keys);

        $sqlKeys = implode("','", $keys);

        $query = "SELECT `post_excerpt` as `name`, `post_name` as `key` FROM `{$wpdb->posts}` WHERE `post_name` IN ('{$sqlKeys}') AND `post_type` LIKE 'acf-field'";

        $res = $wpdb->get_results($query, ARRAY_A);

        if (!$res || is_wp_error($res)) {
            return [];
        }

        $compare = [];

        foreach ($res as $item) {
            $compare[$item['key']] = $item['name'];
        }

        self::replaceKeysAcfArray($values, $compare);

        return $values;
    }


    static function replaceKeysAcfArray(&$values, $replace)
    {
        foreach ($values as $key => &$val) {
            if (isset($replace[$key])) {
                $values[$replace[$key]] = $val;
                unset($values[$key]);
            }
            if (is_array($val)) {
                self::replaceKeysAcfArray($val, $replace);
            }
        }
    }


    static function getAcfPostKeys($values, &$keys)
    {
        foreach ($values as $key => $value) {
            if (preg_match("/^field_/", $key)) {
                $keys[] = $key;
            }
            if (is_array($value)) {
                self::getAcfPostKeys($value, $keys);
            }
        }
    }


    /**
     * - ПОлучает ID текущего элемента
     * @param bool $fromReferer
     * @return string|false
     */
    static function getCurrentElementId($fromReferer = false)
    {
        $params = self::getGetParams($fromReferer);
        return isset($params['id']) ? $params['id'] : false;
    }


    /**
     * - Получает настройки текущей сущности
     * @return Settings|false
     */
    static function getCurrentSets($fromReferer = false)
    {
        $key = self::getKeyFromPage($fromReferer);

        if (!$key) {
            return false;
        }

        return Main::get($key);
    }


    /**
     * - Получает текущие аргументы фильтра с GET запроса
     * @return array
     */
    static function getCurrentFilter()
    {
        return Helper::getRequest('filter', []);
    }


    /**
     * - Получает ключ сущности с гет запроса
     * @param bool $fromReferer для ajax запросов
     * @return string
     */
    private static function getKeyFromPage($fromReferer = false)
    {
        $params = self::getGetParams($fromReferer);

        if (!isset($params['page'])) {
            return '';
        }

        if (!preg_match("/^press_/", $params['page'])) {
            return '';
        }

        $page = preg_replace("/^press_/", '', $params['page']);
        $page = preg_replace("/_single$/", '', $page);

        return $page;
    }


    private static function getGetParams($fromReferer = false)
    {
        $url = $_SERVER['REQUEST_URI'];

        if ($fromReferer) {
            $url = $_SERVER['HTTP_REFERER'];
        }

        parse_str(preg_replace("/[^\?]+\?/", '', $url), $params);

        return $params;
    }



    private static function theError($res = [])
    {
        $res = self::getResponseArgs($res);
        $res['status'] = 'error';
        if (empty($res['msg'])) {
            $res['msg'] = 'Произошла серверная ошибка';
        }
        self::theResponse($res);
    }


    private static function theSuccess($res = [])
    {
        $res = self::getResponseArgs($res);
        $res['status'] = 'success';
        self::theResponse($res);
    }


    private static function theResponse($res = [])
    {
        header('Content-Type: application/json');
        echo json_encode($res, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }


    private static function getResponseArgs($res = [])
    {
        if (!$res) return [];

        if (!is_array($res)) {
            $msg = $res;
            $res = [];
            $res['msg'] = $msg;
        }

        if (!empty($res['msg'])) {
            $res['msg'] = $res['msg'];
        }

        return $res;
    }


    static function getAjaxUrl($action)
    {
        return AJAX_URL . '?action=press_ajax_hook&press_action=' . $action;
    }
}
