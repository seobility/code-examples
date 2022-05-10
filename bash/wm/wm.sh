#! /bin/bash

# определяем название текущей папки
# поддержка dev копии
SOURCE=${BASH_SOURCE[0]}
while [ -h "$SOURCE" ]; do
    DIR=$(cd -P "$(dirname "$SOURCE")" >/dev/null 2>&1 && pwd)
    SOURCE=$(readlink "$SOURCE")
    [[ $SOURCE != /* ]] && SOURCE=$DIR/$SOURCE
done
DIR=$(cd -P "$(dirname "$SOURCE")" >/dev/null 2>&1 && pwd)
FOLDER=$(basename -- $DIR)

com='wm'

if [[ "$FOLDER" = '.web-manager-dev' ]]; then
    com="wm-dev"
fi

# для некоторых команд требуются root права
# передаем текущего пользователя и групу для проверки
if [ "$USER" != "root" ]; then
    group=$(id -Gn)
    sudo $com $USER $group $@
    exit
fi

# подключаем основные переменные
source $DIR/config.sh

if [ "$2" != "$WEB_GROUP" ]; then
    echo -e "\e[31mТолько веб пользователи могут выполнять веб команды\e[0m"
    exit 1
fi

# пользователь который вызвал команду
CURRENT_USER="$1"

# вызванная команда
COMMAND="$3"

# путь к папке со скриптами web-manager
ABSPATH=$DIR

# смещаем переданные аргументы
# таким образом удаляем пользователя, групу и команду
shift
shift
shift

# общие функции и переменные
source $ABSPATH/functions.sh

# команда не передана
# выводим доступные команды с описанием
if [ -z "$COMMAND" ]; then
    echo
    for name in $(ls $ABSPATH/commands); do
        com=$(echo $name | sed "s/\.sh\$//")
        com_help=$(get_help $ABSPATH/commands/$name)
        com_perms=$(get_perms $ABSPATH/commands/$name)

        if [[ -z "$com_perms" ]]; then
            echo -e "${C_YELLOW}[${com}]${C_RESET}: ${com_help}"
            continue
        fi

        if [[ "$com_perms" = 'main' && "$CURRENT_USER" = "$MAIN_USER" ]]; then
            echo -e "${C_YELLOW}[${com}]${C_RESET}: ${com_help}"
            continue
        fi

        if [[ "$com_perms" = 'local' && "$CURRENT_USER" != "$MAIN_USER" ]]; then
            echo -e "${C_YELLOW}[${com}]${C_RESET}: ${com_help}"
            continue
        fi

        if [[ "$com_perms" = "$CURRENT_USER" ]]; then
            echo -e "${C_YELLOW}[${com}]${C_RESET}: ${com_help}"
            continue
        fi

    done
    echo
    exit 0
fi

# разрешения на выполнение команды
com_perms=$(get_perms $ABSPATH/commands/$COMMAND.sh)

# может выполнять только основной пользователь
if [[ "$com_perms" = "main" && "$CURRENT_USER" != "$MAIN_USER" ]]; then
    echo -e "${C_RED}Только пользователь ${MAIN_USER} может выполнять команду ${COMMAND}${C_RESET}"
    exit 1
fi

# могут выполнять только локальные пользователи
if [[ "$com_perms" = "local" && "$CURRENT_USER" = "$MAIN_USER" ]]; then
    echo -e "${C_RED}Только локальные пользователи могут выполнять команду ${COMMAND}${C_RESET}"
    exit 1
fi

# может выполнять только определенный пользователь
if [[ ! -z "$com_perms" && "$com_perms" != "local" && "$com_perms" != "main" && "$CURRENT_USER" != "$com_perms" ]]; then
    echo -e "${C_RED}Вам не разрешено выполнять данную команду${C_RESET}"
    exit 1
fi

# такой команды нет
if [[ ! -f $ABSPATH/commands/${COMMAND}.sh ]]; then
    echo -e "${C_RED}Команда ${COMMAND} не существует${C_RESET}"
    exit 1
fi

source $ABSPATH/commands/${COMMAND}.sh

if [[ "$?" != '0' ]]; then
    # error_command "$COMMAND"
    exit 1
fi

# success_command "$COMMAND"
# echo

exit 0
