#! /bin/bash

# HELP: Собирает скрипты, устанавливает зависимости composer

domain=$(get_current_site)
test_domain=$(get_current_test_site)

if [[ ! "$domain" || ! "$test_domain" ]]; then
    echo -e "${C_RED}Не удалось определить сайт. Убедитесь что команда вызывается с корня сайта.${C_RESET}"
    return 1
fi

site_path="/home/$CURRENT_USER/www/$domain"
test_site_path="/home/$MAIN_USER/www/$test_domain"

if [[ ! -f $test_site_path/.wm.config ]]; then
    echo -e "${C_RED}Отсутствует файл .wm.config в корне сайта${C_RESET}"
    return 1
fi

if [[ "$domain" = "$test_domain" ]]; then
    wm_lock $test_site_path
fi

source $test_site_path/.wm.config

build_all "$site_path"

if [[ "$domain" = "$test_domain" ]]; then
    wm_unlock $test_site_path
fi

return 0
