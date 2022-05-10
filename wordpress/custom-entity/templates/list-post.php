<?php

/**
 * - Шаблон списка элементов
 */

use Vnet\AdminPress\Main;
use Vnet\Core\Helper;
use Vnet\Entity\Admin;

/**
 * @var \Vnet\Entity\Settings $settings
 */
$settings = Admin::getCurrentSets();
$filter = Admin::getCurrentFilter();
/**
 * @var \Vnet\Entity\Entity_Query $query
 */
$query = $settings->newQuery()->filter($filter);

$statusLabels = [
    'publish' => 'Опукликованные',
    'draft' => 'Черновик',
    'trash' => 'Корзина'
];

$statusSingleLabels = [
    'publish' => 'Опубликован',
    'draft' => 'Черновик',
    'trash' => 'Корзина'
];

$order = Helper::getArr($filter, 'order');
$orderby = Helper::getArr($filter, 'orderby');
$currentStatus = Helper::getArr($filter, 'status');

$statuses = $settings->getStatuses();
$searchCols = $settings->getSearchColumns();
$adminCols = $settings->getAdminColumns();

?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?= $settings->getLabel('name'); ?></h1>
    <a href="admin.php?page=press_<?= $settings->getKey(); ?>_single" class="page-title-action">Добавить новую</a>

    <hr class="wp-header-end">

    <?php
    if ($statuses) {
        $active = $currentStatus;
    ?>
        <ul class="subsubsub">
            <li class="all"><a href="admin.php?page=press_<?= $settings->getKey(); ?>" class="<?= !$active ? 'current' : ''; ?>">Все</a> |</li>
            <?php
            $total = count($statuses);
            $i = 0;
            foreach ($statuses as $status => $val) {
                $last = '';
                if ($i < $total - 1) {
                    $last = ' |';
                }
            ?>
                <li class="<?= $status; ?>"><a href="admin.php?page=press_<?= $settings->getKey(); ?>&filter[status]=<?= $status; ?>" class="<?= $active === $status ? 'current' : ''; ?>"><?= Helper::getArr($statusLabels, $status); ?></a><?= $last; ?></li>
            <?php
                $i++;
            }
            ?>
        </ul>
    <?php
    }

    ?>
    <form id="posts-filter" method="get" action="admin.php">
        <input type="hidden" name="page" value="press_<?= $settings->getKey(); ?>">
        <?php
        if ($searchCols) {
        ?>
            <p class="search-box">
                <label class="screen-reader-text" for="post-search-input"><?= $settings->getLabel('searchItems'); ?>:</label>
                <input type="search" name="filter[search]" value="<?= Helper::getArr($filter, 'search'); ?>">
                <input type="submit" id="search-submit" class="button" value="<?= $settings->getLabel('searchItems'); ?>">
            </p>
        <?php
        }
        ?>

        <input type="hidden" name="filter[status]" class="post_status_page" value="<?= $currentStatus; ?>">

        <div class="tablenav top">
            <?php
            ob_start();
            ?>
            <div class="alignleft actions bulkactions">
                <select>
                    <option value="">Действия</option>
                    <?php
                    if ($settings->hasTrash() && $currentStatus === 'trash') {
                    ?>
                        <option value="restore">Восстановить</option>
                    <?php
                    }
                    ?>
                    <option value="delete">
                        <?php
                        if ($settings->hasTrash() && $currentStatus === 'trash') {
                            echo 'Удалить навсегда';
                        } else {
                            echo 'Удалить';
                        }
                        ?>
                    </option>
                </select>
                <button type="button" class="button action js-bulk-button">Применить</button>
            </div>
            <?php
            $bulkActions = ob_get_clean();
            echo $bulkActions;
            ?>
            <br class="clear">
        </div>
        <?php
        echo '<p>Найдено: <strong>' . number_format((float)$query->total, 0, '.', ' ') . '</strong></p>';
        ?>
        <table class="wp-list-table widefat fixed striped table-view-list pages">
            <thead>
                <tr>
                    <?php
                    ob_start();
                    ?>
                    <td id="cb" class="manage-column column-cb check-column">
                        <input type="checkbox">
                    </td>
                    <?php
                    foreach ($adminCols as $col) {
                        $colClass = ['manage-column'];

                        $colClass[] = 'column-' . $col->getName();

                        $sortDir = 'desc';

                        if ($col->isAdminMain()) {
                            $colClass[] = 'column-primary';
                        }

                        if ($col->isSort()) {
                            if ($col->getName() === $orderby) {
                                $colClass[] = 'sorted';
                                $sortDir = $order === 'asc' ? 'asc' : 'desc';
                            } else {
                                $colClass[] = 'sortable';
                            }
                            $colClass[] = $sortDir;
                        }
                        $url = $filter;
                        $url['order'] = $sortDir === 'asc' ? 'desc' : 'asc';
                        $url['orderby'] = $col->getName();
                        $url = 'admin.php?page=press_' . $settings->getKey() . '&' . http_build_query(['filter' => $url]);
                    ?>
                        <th scope="col" id="<?= $col->getName(); ?>" class="<?= implode(' ', $colClass); ?>" <?= $col->getWidth() ? 'style="width: ' . $col->getWidth() . ';"' : ''; ?>>
                            <?php
                            if ($col->isSort()) {
                            ?>
                                <a href="<?= $url; ?>">
                                    <span><?= $col->getLabel(); ?></span>
                                    <span class="sorting-indicator"></span>
                                </a>
                            <?php
                            } else {
                                echo $col->getLabel();
                            }
                            ?>
                        </th>
                    <?php
                    }
                    $heading = ob_get_clean();
                    echo $heading;
                    ?>
                </tr>
            </thead>

            <tbody id="the-list">
                <?php
                if (!$query->hasResults()) {
                ?>
                    <tr>
                        <td colspan="<?= count($adminCols) + 1; ?>"><?= $currentStatus === 'trash' ? $settings->getLabel('notFoundInTrash') : $settings->getLabel('notFound'); ?></td>
                    </tr>
                    <?php
                } else {
                    while ($row = $query->fetch()) {
                    ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="id[]" class="js-bulk-check" value="<?= $row->getId(); ?>">
                            </th>
                            <?php
                            foreach ($adminCols as $col) {

                                $colName = $col->getName();
                                $val = $row->getAdminValue($colName);

                                if ($col->isStatus() && isset($statusSingleLabels[$row->getStatus()])) {
                                    $val = $statusSingleLabels[$row->getStatus()];
                                }

                                if ($col->isAdminMain()) {
                                    $edit = 'admin.php?page=press_' . $settings->getKey() . '_single&id=' . $row->getId();
                                    $url = $row->getPublicUrl() ?? '';


                            ?>
                                    <td class="title column-title has-row-actions column-primary page-title">
                                        <div class="row-title">
                                            <a class="post-link" href="<?= $edit; ?>">
                                                <?= $val; ?>
                                            </a>
                                            <?php
                                            if ($row->isDraft() && $currentStatus !== 'draft') {
                                                echo " &mdash; <span class=\"post-state\">Черновик</span>";
                                            }
                                            ?>
                                        </div>
                                        <div class="row-actions">
                                            <span class="edit">
                                                <?php
                                                if ($settings->hasTrash() && $currentStatus === 'trash') {
                                                ?>
                                                    <a href="#" class="js-single-action" data-type="restore" data-id="<?= $row->getId(); ?>">Восстановить</a> |
                                                <?php
                                                } else {
                                                ?>
                                                    <a href="<?= $edit; ?>">Изменить</a> |
                                                    <?php if ($url) { ?>
                                                        <a href="<?= $url; ?>">Перейти</a> |
                                                    <?php } ?>
                                                <?php
                                                }
                                                ?>
                                            </span>
                                            <span class="trash">
                                                <a href="#" class="submitdelete js-single-action" data-type="delete" data-id="<?= $row->getId(); ?>" data-status="<?= !$settings->hasTrash() ? 'trash' : $row->getStatus(); ?>" aria-label="Удалить">
                                                    <?php
                                                    if ($settings->hasTrash() && $currentStatus === 'trash') {
                                                        echo 'Удалить навсегда';
                                                    } else {
                                                        echo 'Удалить';
                                                    }
                                                    ?>
                                                </a>
                                            </span>
                                        </div>
                                    </td>
                                <?php
                                } else if ($col->isFilter()) {
                                    $filterVal = $row->getAdminFilterValue($colName);
                                    $filterName = $row->getAdminFilterName($colName);
                                ?>
                                    <td>
                                        <a href="admin.php?page=press_<?= $settings->getKey(); ?>&<?= $filterName; ?>=<?= urlencode($filterVal); ?>">
                                            <?= $val; ?>
                                        </a>
                                    </td>
                                <?php
                                } else {
                                ?>
                                    <td>
                                        <?= $val; ?>
                                    </td>
                            <?php
                                }
                            }
                            ?>
                        </tr>
                <?php
                    }
                }
                ?>
            </tbody>

            <tfoot>
                <tr>
                    <?= $heading; ?>
                </tr>
            </tfoot>

        </table>
        <div class="tablenav bottom">
            <?php
            echo $bulkActions;
            ?>
            <div class="tablenav-pages">
                <span class="displaying-num"><?= $query->found; ?> элементов</span>
                <span class="pagination-links">
                    <?php
                    require __DIR__ . '/pagination.php';
                    ?>
                </span>
            </div>
            <br class="clear">
        </div>

    </form>

    <div id="ajax-response"></div>
    <div class="clear"></div>
</div>

<script>
    let loading = false;

    // действие над постом
    (function($, window, document) {
        $('.js-single-action').on('click', function(e) {
            e.preventDefault();

            if (loading) {
                return;
            }

            let id = this.dataset.id;
            let type = this.dataset.type;
            let status = this.dataset.status;

            if (status === 'trash') {
                if (!window.confirm('Запись будет удалена без возвратно. Продолжить?')) {
                    return;
                }
            }

            loading = true;

            $.ajax({
                url: '<?= Admin::getAjaxUrl('ajaxProcessAction'); ?>',
                data: {
                    id: id,
                    type: type
                },
                success: function(res) {
                    loading = false;
                    if (!res || res.status !== 'success') {
                        console.error(res);
                        window.alert(res.msg);
                        return;
                    }
                    window.location.reload();
                },
                error: function(res) {
                    loading = false;
                    console.error(res);
                    window.alert('Произошла серверная ошибка');
                }
            });
        });
    })(jQuery, window, document);

    // массовое действие
    (function($) {
        $('.js-bulk-button').on('click', function(e) {
            e.preventDefault();

            if (loading) {
                return;
            }

            let $btn = $(this);
            let $select = $btn.parent().find('select');
            let ids = [];

            $('.js-bulk-check:checked').each(function() {
                ids.push(this.value);
            });

            if (!$select.get(0) || !ids.length) {
                return;
            }

            let val = $select.val();

            if (!val) {
                return;
            }

            if (val === 'delete') {
                <?php
                if ($settings->hasTrash() && $currentStatus === 'trash') {
                ?>
                    if (!window.confirm('Записи будут удалена без возвратно. Продолжить?')) {
                        return;
                    }
                <?php
                }
                ?>
            }

            loading = true;

            $.ajax({
                url: '<?= Admin::getAjaxUrl('ajaxProcessAction'); ?>',
                data: {
                    id: ids.join(','),
                    type: val
                },
                success: function(res) {
                    loading = false;
                    if (!res || res.status !== 'success') {
                        console.error(res);
                        window.alert(res.msg);
                        return;
                    }
                    window.location.reload();
                },
                error: function(res) {
                    loading = false;
                    console.error(res);
                    window.alert('Произошла серверная ошибка');
                }
            });
        });
    })(jQuery, window, document);
</script>