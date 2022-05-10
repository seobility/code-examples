#! /bin/bash

# HELP: создает новый сайт
# PERMS: main

read_new_domain
domain=$read_new_domain_value

confirm "Создать базу данных?"
createdb=$confirm_value

confirm "Выпустить ssl сертификат?"
usessl=$confirm_value

has_db='n'
has_ssl='n'
has_dir='y'
is_up='y'

# создаем базу данных
if [[ "$createdb" = 'y' ]]; then
    read_new_db "$domain"
    if create_db "$read_new_db_value"; then has_db='y'; fi
fi

# выпускаем сертификат
if [[ "$usessl" = 'y' ]]; then
    if create_ssl "$domain"; then has_ssl='y'; fi
fi

# создаем каталог если его нет
# устанавливаем владельца
if [[ ! -d /home/${CURRENT_USER}/www/${domain} ]]; then
    if ! mkdir /home/${CURRENT_USER}/www/${domain}; then
        has_dir='n'
    else
        chown ${CURRENT_USER}:${WEB_GROUP} /home/${CURRENT_USER}/www/${domain} -R
    fi
fi

# формируем конфиги apache и nginx
if ! create_virtual_host "$domain" "$has_ssl"; then is_up='n'; fi

# перезапускаем веб сервер
if ! reload_web_server; then is_up='n'; fi

# формируем отчет
echo
echo '--------------------------'

if [[ "$is_up" = 'y' ]]; then
    if [[ "$has_ssl" = 'y' ]]; then
        echo -e "Сайт: ${C_GREEN}https://${domain}/${C_RESET}"
    else
        echo -e "Сайт: ${C_GREEN}http://${domain}/${C_RESET}"
    fi
else
    echo -e "Сайт: ${C_RED}нет${C_RESET}"
fi

if [[ "$has_dir" = 'y' ]]; then
    echo -e "Каталог: ${C_GREEN}/home/${CURRENT_USER}/www/${domain}${C_RESET}"
else
    echo -e "Каталог: ${C_RED}нет${C_RESET}"
fi

if [[ "$has_db" = 'y' ]]; then
    echo -e "База данных: ${C_GREEN}${dbname}${C_RESET}"
else
    echo -e "База данных: ${C_RED}нет${C_RESET}"
fi

if [[ "$has_ssl" = 'y' ]]; then
    echo -e "SSL: ${C_GREEN}да${C_RESET}"
else
    echo -e "SSL: ${C_RED}нет${C_RESET}"
fi

echo '--------------------------'

return 0
