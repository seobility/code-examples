<?php

/**
 * @var \Vnet\Entity\Entity_Query $query
 * @var \Vnet\Entity\Settings $settings
 */

use Vnet\Entity\Admin;

$totalPages = $query->totalPages;
$page = $query->page;

$filter = Admin::getCurrentFilter();

if (isset($filter['page'])) {
    unset($filter['page']);
}

$url = 'admin.php?page=press_' . $settings->getKey();

if ($filter) {
    $url .= '&' . http_build_query(['filter' => $filter]);
}

$firtLink = $url;

$url .= '&filter[page]=%#%';

$args = [
    'base' => '%_%',
    'format' => $url,
    'total' => $totalPages,
    'current' => $page,
    'show_all' => false,
    'end_size' => 1,
    'mid_size' => 3,
    'prev_next' => true,
    'prev_text' => '«',
    'next_text' => '»',
    'type' => 'plain',
    'add_args' => [],
    'add_fragment' => '',
    'before_page_number' => '',
    'after_page_number'  => '',
    'aria_current'       => 'page',
];

$total = (int) $args['total'];
if ($total < 2) {
    return;
}

$current  = (int) $args['current'];

$end_size = (int) $args['end_size'];
if ($end_size < 1) {
    $end_size = 1;
}

$mid_size = (int) $args['mid_size'];
if ($mid_size < 0) {
    $mid_size = 2;
}

$add_args = [];
$r = '';
$page_links = [];
$dots = false;

if ($args['prev_next'] && $current && 1 < $current) :
    // $link = str_replace('%_%', 2 == $current ? '' : $args['format'], $args['base']);
    $link = str_replace('%#%', $current - 1, $url);

    $link .= $args['add_fragment'];

    $page_links[] = sprintf(
        '<a class="prev-page button" href="%s">%s</a>',
        esc_url($link),
        $args['prev_text']
    );
endif;

for ($n = 1; $n <= $total; $n++) :
    if ($n == $current) :
        $page_links[] = sprintf(
            '<span class="tablenav-pages-navspan current">%s</span>',
            $args['before_page_number'] . number_format_i18n($n) . $args['after_page_number']
        );

        $dots = true;
    else :
        if ($args['show_all'] || ($n <= $end_size || ($current && $n >= $current - $mid_size && $n <= $current + $mid_size) || $n > $total - $end_size)) :
            // $link = str_replace('%_%', 1 == $n ? '1' : $args['format'], $args['base']);
            if ($n === 1) {
                $link = $firtLink;
            } else {
                $link = str_replace('%#%', $n, $url);
            }

            $page_links[] = sprintf(
                '<a class="page-numbers button" href="%s">%s</a>',
                /** This filter is documented in wp-includes/general-template.php */
                esc_url($link),
                $args['before_page_number'] . number_format_i18n($n) . $args['after_page_number']
            );

            $dots = true;
        elseif ($dots && !$args['show_all']) :
            $page_links[] = '<span class="tablenav-pages-navspan">' . __('&hellip;') . '</span>';

            $dots = false;
        endif;
    endif;
endfor;

if ($args['prev_next'] && $current && $current < $total) :
    // $link = str_replace('%_%', $args['format'], $args['base']);
    $link = str_replace('%#%', $current + 1, $url);

    $page_links[] = sprintf(
        '<a class="prev-page button" href="%s">%s</a>',
        esc_url($link),
        $args['next_text']
    );
endif;

switch ($args['type']) {
    case 'array':
        return $page_links;

    case 'list':
        $r .= "<ul class='page-numbers'>\n\t<li>";
        $r .= implode("</li>\n\t<li>", $page_links);
        $r .= "</li>\n</ul>\n";
        break;

    default:
        $r = implode("\n", $page_links);
        break;
}

echo $r;
