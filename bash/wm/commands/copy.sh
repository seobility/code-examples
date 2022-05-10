#! /bin/bash

# HELP: копирует сайт
# PERMS: local

select_site "$MAIN_USER"
domain="$select_site_value"

sub_domain=$(echo $domain | sed "s/.$MAIN_DOMAIN//")

target_domain="${sub_domain}.${CURRENT_USER}.${MAIN_DOMAIN}"

target_path="/home/$CURRENT_USER/www/$target_domain"

if [[ -d $target_path || -f $target_path ]]; then
    echo -e "${C_RED}${target_domain} существует${C_RESET}"
    return 1
fi

if [[ "$(git_has_changes /home/$MAIN_USER/www/$domain $MAIN_USER)" = 'y' ]]; then
    echo -e "${C_RED}Основной сайт имеет изменения в гите${C_RESET}"
    return 1
fi

confirm "Будет скопирован сайт ${C_YELLOW}${domain}${C_RESET} Продолжить?"
if [[ "$confirm_value" != 'y' ]]; then
    return 0
fi

if ! rsync_exclude /home/$MAIN_USER/www/$domain $target_path; then
    echo -e "${C_RED}Ошибка при коипровании сайта${C_RESET}"
    return 1
fi

if ! create_virtual_host "$target_domain"; then
    echo -e "${C_RED}Ошибка при создании виртуальных хостов${C_RESET}"
    return 1
fi

# перезапускаем веб сервер
if ! reload_web_server; then
    echo -e "${C_YELLOW}Ошибка при перезапуске веб сервера${C_RESET}"
fi

show_wp_suggestions "$target_path" "$target_domain"

echo '--------------------------'
echo -e "Сайт: ${C_GREEN}https://${target_domain}/${C_RESET}"
echo -e "Каталог: ${C_GREEN}${target_path}${C_RESET}"
echo '--------------------------'

return 0
