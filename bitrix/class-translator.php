<?php

namespace CCI;

use CIBlockElement;
use CIBlockSection;

class Translator
{
    // таблица с переводами
    // структура таблицы:
    // ID (int) | ru (string) | en (string) | ...
    // обязатьельно должны присутствовать языковые колонки:
    //    - текущий язык (например ru)
    //    - язык на который надо перевести (например en)
    private static $table;

    // язык по умолчанию 
    // будет использоваться при переводе строк если не передать аргумен $from
    private static $defLang;

    // текущий язык
    // будет использоваться при переводе строк если не передать аргумент $to
    private static $currentLang;

    // массив всех языков проекта
    private static $allLangs;

    // использовать google api для перевода
    private static $useGoogle;

    // массив с переводами по ключу
    // используется в методе translateKey
    private static $keyTranslates = null;
    private static $keyTranslatesFile = null;
    private static $isKeyTranslatesChange = false;

    // массив переводов (['en' => 'string', 'ru' => 'string'])
    private static $translates;

    // Определяет изменился ли массив переводов в ходе выполнения кода
    private static $isChanged = false;


    /**
     * - Инициализация класса
     * @param string $defLang язык по умолчанию
     * @param string $currentLang текущий язык
     * @param array $allLangs массив всех используемых языков (например ['ru', 'en'])
     * @param string $table таблица с переводами
     * @param bool $useGoogle использовать google api для перевода
     * @param string $keyTranslatesFile абсолютный путь к json файлу с переводами по ключам
     */
    public static function setup($defLang, $currentLang, $allLangs, $table, $useGoogle = true, $keyTranslatesFile = null)
    {
        self::$defLang = $defLang;
        self::$currentLang = $currentLang;
        self::$allLangs = $allLangs;
        self::$table = $table;
        self::$useGoogle = $useGoogle;

        if ($keyTranslatesFile && file_exists($keyTranslatesFile)) {
            self::$keyTranslates = json_decode(file_get_contents($keyTranslatesFile, true), true);
            self::$keyTranslatesFile = $keyTranslatesFile;
        }

        // self::createTable();
        self::loadTransaltes();

        register_shutdown_function(['CCI\Translator', '__updateTranslates']);
    }

    public static function getCurrLanguage()
    {
        return self::$currentLang;
    }

    private static function createTable()
    {
        global $DB;
        $table = self::$table;

        $DB->Query("CREATE TABLE IF NOT EXISTS `$table` ( 
            `ID` BIGINT(255) NOT NULL AUTO_INCREMENT , 
            `ru` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL ,
            `en` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL ,
            `be` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL ,
            PRIMARY KEY (`ID`)) 
            ENGINE = InnoDB;");
    }


    /**
     * - Переводит строку
     * @param string/null $from - с какого перевести
     * @param string/null $to - на какой перевести
     * @param bool $clearText если true, пернет текст без отступов и лишних пробелов
     * @return string
     */
    public static function translate($str, $from = null, $to = null, $clearText = false)
    {
        if (!is_string($str)) return $str;

        if ($from === null) $from = self::$defLang;
        if ($to === null) $to = self::$currentLang;

        if ($from === $to) return $str;

        $index = -1;

        // находим индекс искомой строки
        foreach (self::$translates as $i => &$row) {
            if (!isset($row[$from])) continue;
            if ($row[$from] == $str) {
                $index = $i;
                break;
            }
        }

        // Если такой строки нет
        if ($index === -1) {
            if (!self::$useGoogle) {
                self::$translates[] = [
                    $from => $str,
                    'is_new' => true
                ];
                self::$isChanged = true;
                return $str;
            }
            $tr = Google_Translate::translate($from, $to, $str);
            self::$translates[] = [
                $from => $str,
                $to => $tr,
                'is_new' => true
            ];
            self::$isChanged = true;
            return self::maybeClearText($tr, $clearText);
        }

        // Строку нашли, проверяем есть ли перевод
        $tr = !empty(self::$translates[$index][$to]) ? self::$translates[$index][$to] : null;

        // Если перевода нет
        if ($tr === null) {
            if (!self::$useGoogle) return $str;
            $tr = Google_Translate::translate($from, $to, $str);

            self::$translates[$index][$to] = $tr;
            self::$translates[$index]['is_changed'] = true;
            self::$isChanged = true;
        }

        return self::maybeClearText($tr, $clearText);;
    }


    private static function maybeClearText($text, $clear = false)
    {
        if (!$clear) return $text;
        $text = strip_tags($text);
        $text = preg_replace("/[\r\n\t]+/", ' ', $text);
        $text = preg_replace("/[\s]+/", ' ', $text);
        return trim($text);
    }


    /**
     * - Переводит по ключу
     * - ищет совпадения в файле self::$keyTranslatesFile
     * @param string $key - кльч
     * @param null/string $to - на какой язык перевисти (по умолчанию: self::$currentLang)
     */
    public static function translateKey($key, $to = null)
    {
        if (!$key) return $key;
        if (self::$keyTranslates === null) return;
        if ($to === null) $to = self::$currentLang;

        if (!isset(self::$keyTranslates[$key])) {
            self::$keyTranslates[$key] = [$to => $key];
            self::$isKeyTranslatesChange = true;
            return $key;
        }

        if (!isset(self::$keyTranslates[$key][$to])) {
            self::$keyTranslates[$key][$to] = $key;
            self::$isKeyTranslatesChange = true;
            return $key;
        }

        return self::$keyTranslates[$key][$to];
    }



    /**
     * - Выгружает таблицу с переводами в сессию
     */
    private static function loadTransaltes()
    {
        global $DB;

        self::startSession();

        if (isset($_SESSON['TRANSLATOR_STRINGS'])) {
            self::$translates = $_SESSION['TRANSLATOR_STRINGS'];
            return;
        }

        $table = self::$table;

        $query = $DB->Query("SELECT * FROM $table");

        $res = [];

        while ($row = $query->Fetch()) {
            $res[] = $row;
        }

        self::$translates = $res;

        $_SESSION['TRANSLATOR_STRINGS'] = self::$translates;
    }



    /**
     * - Получает ссылку текущей страницы с учетом локализации
     */
    public function getCurrentUrl($lang = null)
    {
        if ($lang === null) $lang = self::$currentLang;

        $url = Helper::getServer('REQUEST_URI', '/');

        $regex = [];

        foreach (self::$allLangs as $_lang) {
            $regex[] = "(^\/$_lang\/)";
        }

        $regex = implode('|', $regex);
        $url = preg_replace('/' . $regex . '/', '/', $url);

        if ($lang === self::$defLang) return $url;

        return '/' . $lang . $url;
    }



    /**
     * - Формирует ссылку с учетом текущего языка сайта
     */
    public static function getUrl($path, $lang = null)
    {
        if ($lang === null) $lang = self::$currentLang;

        if (preg_match("/^https?:\/\/[^\/]+/", $path, $matches)) {
            if (preg_match("/^https?:\/\/[^\/]+\/{$lang}\//", $path)) {
                return $path;
            }
            return str_replace($matches[0], "{$matches[0]}/" . $lang, $path);
        }

        if (preg_match("/^\/{$lang}\//", $path)) return $path;

        $path = preg_replace("/^\//", '', $path);
        if ($lang === self::$defLang) return '/' . $path;
        return '/' . $lang . '/' . $path;
    }


    /**
     * - Получает урл текущей страницы без языкавого префикса
     * @return string/null
     */
    public static function getCurPage()
    {
        $uri = Helper::getServer('REQUEST_URI');
        if (!$uri) return null;

        $uri = preg_replace("/\?.*/", '', $uri);

        $allLangs = self::$allLangs;

        foreach ($allLangs as &$lang) $lang = "(^\/$lang\/)";

        $regex = implode('|', $allLangs);

        $uri = preg_replace("/{$regex}/", '', $uri);
        if (preg_match("/^\//", $uri)) {
            $uri = preg_replace("/^\/+/", '/', $uri);
        } else {
            $uri = "/$uri";
        }

        return $uri;
    }


    /**
     * - Проверяет находится ли в текущей директории
     */
    public static function inDir($dir)
    {
        $page = self::getCurPage();
        return (mb_substr($page, 0, mb_strlen($dir)) == $dir);
    }



    /**
     * 
     * - Переводит свойство на текущий язык
     * - Чтобы метод сработал в админке должно быть задано данное свойство в формате: 
     *   PROP_LANGID - для элментов
     *   UF_PROP_LANGID - для разделов
     * - Например чтобы перевести NAME на английский, в админке должно быть свойство NAME_EN либо UF_NAME_EN
     * @param array $arResult - результирующий массив элемента инфоблока или раздела
     * @param string $prop - ключ свойства
     * @param bool $isHtml [optional] если да - пропустит вывод через htmlspecialchars_decode
     * 
     * @return string - если перевод не найден, вернется значение свойства переданного в аргументе
     * @return false - если свойства не существет
     * 
     */
    public static function translateProperty(&$arResult, $prop, $isHtml = false, $debug = false)
    {
        // если начинается на тильду - возвращаем html
        if (preg_match("/^~/", $prop)) {
            $isHtml = true;
            $prop = preg_replace("/^~/", '', $prop);
        }

        // находим значение переданного свойства
        $orig = isset($arResult[$prop]) ? $arResult[$prop] : null;
        if ($orig === null && !empty($arResult['PROPERTIES'])) {

            if (isset($arResult['PROPERTIES'][$prop]['SHOW'])) {
                $orig = $arResult['PROPERTIES'][$prop]['SHOW'];
            } else if (isset($arResult['PROPERTIES'][$prop])) {
                $orig = $arResult['PROPERTIES'][$prop]['VALUE'];
            } else {
                $orig = null;
            }
            // $orig = isset($arResult['PROPERTIES'][$prop]) ? $arResult['PROPERTIES'][$prop]['VALUE'] : null;
        }

        // если его нет
        if ($orig === null) return false;

        if (is_array($orig) && isset($orig['TEXT'])) {
            $orig = $orig['TEXT'];
        }

        // если текущий язык является языком по умолчанию
        if (self::$currentLang === self::$defLang) return self::htmlValue($orig, $isHtml);

        // ищем свойство перевода
        $langProp = $prop . '_' . strtoupper(self::$currentLang);
        if (!preg_match("/^UF_.*$/", $prop)) {
            $sectionLangProp = 'UF_' . $prop . '_' . strtoupper(self::$currentLang);
        } else {
            $sectionLangProp = $langProp;
        }
        $value = isset($arResult[$langProp]) ? $arResult[$langProp] : null;

        if ($value === null) {
            $value = isset($arResult[$sectionLangProp]) ? $arResult[$sectionLangProp] : null;
        }

        if ($value === null && !empty($arResult['PROPERTIES'])) {

            if ($arResult['PROPERTIES'][$langProp]['WITH_DESCRIPTION'] == 'Y') {
                if (isset($arResult['PROPERTIES'][$langProp]['DESCRIPTION']) && is_array($arResult['PROPERTIES'][$langProp]['DESCRIPTION'])) {
                    foreach ($arResult['PROPERTIES'][$langProp]['DESCRIPTION'] as $key => $descItem) {
                        $arResult['PROPERTIES'][$langProp]['VALUE'][$key]['NAME'] = $descItem;
                    }
                }
            }
            $value = isset($arResult['PROPERTIES'][$langProp]) ? $arResult['PROPERTIES'][$langProp]['VALUE'] : null;
            if (is_array($value) && isset($value['TEXT'])) $value = $value['TEXT'];
        }

        if ($value === null && isset($arResult['PROPERTY_' . $langProp . '_VALUE'])) {
            $value = $arResult['PROPERTY_' . $langProp . '_VALUE'];
        }

        // если его нет либо значение пустое
        if (!$value) return self::htmlValue($orig, $isHtml);

        // возвращаем значение
        // если найденное свойство это пользовательское текстовое свойство
        if (is_array($value) && isset($value['TEXT'])) {
            return self::htmlValue($value['TEXT'], $isHtml);
        }

        return self::htmlValue($value, $isHtml);
    }

    private static function htmlValue($val, $isHtml = false)
    {
        if (!is_string($val)) return $val;
        return $isHtml ? htmlspecialchars_decode($val) : $val;
    }


    /**
     * - Переводит массив элементов/разделов
     * @param array $arr массив элементов/разделов (например $arResult['ITEMS'])
     * @param array $fields массив полей которые надо перевести (например ['NAME', 'UF_SHORT_NAME'])
     * @param bool $isSingle [optional] является ли $arrItems одним элементом/разделом
     * @return array
     */
    public static function translatePropertiesArray($arrItems, $fields, $isSingle = false, $debug = false)
    {
        if ($isSingle) {
            $arrItems = [$arrItems];
        }

        foreach ($arrItems as &$item) {
            foreach ($fields as $key) {

                $resKey = preg_replace("/^~/", '', $key);
                $tildaKey = "~$resKey";

                if (isset($item[$resKey])) {
                    $item[$resKey] = self::translateProperty($item, $key, false, $debug);
                    if (isset($item[$tildaKey])) {
                        $item[$tildaKey] = $item[$resKey];
                    }

                    continue;
                }

                // ищем в свойствах
                if (!isset($item['PROPERTIES'][$resKey]['VALUE'])) continue;

                if (isset($item['PROPERTIES'][$resKey]['VALUE']['TEXT'])) {

                    $item['PROPERTIES'][$resKey]['VALUE']['TEXT'] = self::translateProperty($item, $key, false, $debug);

                    if (isset($item['PROPERTIES'][$resKey]['~VALUE']['TEXT'])) {
                        $item['PROPERTIES'][$resKey]['~VALUE']['TEXT'] = $item['PROPERTIES'][$resKey]['VALUE']['TEXT'];
                    }
                } else {
                    $item['PROPERTIES'][$resKey]['VALUE'] = self::translateProperty($item, $key, false, $debug);

                    if (isset($item['PROPERTIES'][$resKey]['~VALUE'])) {
                        $item['PROPERTIES'][$resKey]['~VALUE'] = $item['PROPERTIES'][$resKey]['VALUE'];
                    }
                }

                continue;
            }
        }

        if ($isSingle) {
            return $arrItems[0];
        }

        return $arrItems;
    }



    /**
     * - Переводит свойства через google если нет в массиве
     * и если длина не привышает 50 символов
     * @param array $items массив элементов/разделов ($arResult['ITEMS'])
     * @param array $key массив ключей для перевода
     * 
     * @return array
     */
    public static function translatePropIfExistsArray($items, $keys)
    {
        $lang = self::$currentLang;
        $defLang = self::$defLang;

        if ($lang === $defLang) return $items;

        foreach ($items as &$arResult) {
            foreach ($keys as $key) {
                if (isset($arResult[$key])) {
                    $trValue = self::translateProperty($arResult, $key);
                    // если нет переведенного значения
                    if ($trValue === $arResult[$key]) {
                        $arResult[$key] = self::maybeTranslateGoogle($trValue);
                    } else {
                        $arResult[$key] = $trValue;
                    }
                    continue;
                }
                // ищем в свойствах
                if (isset($arResult['PROPERTIES'][$key]['VALUE'])) {
                    $trValue = self::translateProperty($arResult, $key);
                    // если нет переведенного значения
                    if ($trValue === $arResult['PROPERTIES'][$key]['VALUE']) {
                        $arResult['PROPERTIES'][$key]['VALUE'] = self::maybeTranslateGoogle($trValue);
                    } else {
                        $arResult['PROPERTIES'][$key]['VALUE'] = $trValue;
                    }
                }
            }
        }

        return $items;
    }


    /**
     * - Переводит свойство через google если его нет
     * - изменяет входной массив $arResult
     * @param array $arResult результирующий массив
     * @param string/array $keys свойство для перевода
     */
    public static function translatePropIfExists(&$arResult, $keys)
    {
        if (is_string($keys)) $keys = [$keys];

        foreach ($keys as $prop) {
            $trVal = self::translateProperty($arResult, $prop);

            if (isset($arResult[$prop])) {
                // если нет перевода
                if ($arResult[$prop] === $trVal) {
                    $val = self::maybeTranslateGoogle($trVal);
                } else {
                    $val = $trVal;
                }
                $arResult[$prop] = $val;
                return;
            }

            // ищем в свойствах
            if (isset($arResult['PROPERTIES'][$prop]['VALUE'])) {
                // если нет переведенного значения
                if ($trVal === $arResult['PROPERTIES'][$prop]['VALUE']) {
                    $val = self::maybeTranslateGoogle($trVal);
                } else {
                    $val = $trVal;
                }
                $arResult['PROPERTIES'][$prop]['VALUE'] = $val;
            }
        }
    }



    private static function maybeTranslateGoogle($str)
    {
        $trStr = trim(strip_tags($str));
        if (mb_strlen($trStr) > 4000) return $str;
        if (!$trStr) return $str;
        return self::translate($trStr);
    }




    /**
     * - Переводит строку в формате: [ru]ru text[ru][en]en text[en]
     * @param string $str строка для перевода
     * @param string/null $to на какой язык перевести, по умолчанию self::$currentLang
     */
    public static function translateString($str, $to = null)
    {
        if (!$str) return;

        $to = $to !== null ? $to : self::$currentLang;

        $regex = self::getLangRegex($to);

        preg_match("/$regex/s", $str, $matches);

        if (isset($matches[1])) return trim($matches[1]);

        $regex = self::getLangRegex(self::$defLang);

        preg_match("/$regex/s", $str, $matches);

        if (isset($matches[1])) return trim($matches[1]);

        return $str;
    }


    private static function getLangRegex($lang)
    {
        return "\[[\s]*${lang}[\s]*\](.*)\[[\s]*${lang}[\s]*\]";
    }


    /**
     * - Формирует языковую версию ключа свойства
     */
    public static function getLangPropKey($key)
    {
        if (self::$currentLang === self::$defLang) return $key;
        $lang = strtoupper(self::$currentLang);
        return "{$key}_{$lang}";
    }



    /**
     * - Меняет атрибут href всем тегам a на языковую ссылку
     */
    public static function replaceHref($str)
    {
        if (self::$currentLang === self::$defLang) return $str;

        $str = preg_replace_callback(
            "/(href=[\'\"])([^\'\"]+)([\'\"])/",
            function ($matches) {
                return $matches[1] . Translator::getUrl($matches[2]) . $matches[3];
            },
            $str
        );

        return $str;
    }



    /**
     * 
     * - Меняеть переданный путь к файлу на путь с языковым префиксом
     * - Используется при вызове компонента bitrix:main.include в ключе PATH
     * - Создаст языковой файл с содержимым оригинального если его еще нет
     * @return string - новый путь к файлу
     * 
     */
    public static function getIncludeFile($file)
    {
        // если текущий язык является основным
        if (self::$currentLang === self::$defLang) return $file;

        $file = str_replace(ABSPATH, '', $file);
        $file = preg_replace("/^\//", '', $file);
        $file = ABSPATH . $file;

        $file_name = basename($file);

        $lang_file = str_replace($file_name, self::$currentLang . '-' . $file_name, $file);

        if (!file_exists($lang_file)) {
            $content = file_exists($file) ? file_get_contents($file) : '';
            file_put_contents($lang_file, $content);
        }

        $lang_file = str_replace(ABSPATH, '', $lang_file);

        return '/' . $lang_file;
    }


    /**
     * - Оставляет поля для фильтрации с языковым префиксом
     * - Используется в компоненте smart filter в файле result_modifier.php
     * @param array $items массив элементов фильтрации ($arResult["ITEMS"])
     * @param bool $translateNames [optional] перевесть параметр ['NAME']
     * @param array $exclude [optional] коды свойств которые не надо переводить
     * 
     * @return array
     */
    public static function getFilterProperties(&$items, $translateNames = false, $exclude = [])
    {
        $langs = self::$allLangs;

        foreach ($langs as &$lang) $lang = "(_" . strtoupper($lang) . "$)";
        $langs = implode("|", $langs);

        $isDef = self::$currentLang === self::$defLang;

        $currentLang = strtoupper(self::$currentLang);

        $res = [];

        foreach ($items as $id => $item) {
            $code = Helper::getArr($item, 'CODE');

            if (in_array($code, $exclude)) {
                $res[$id] = $item;
                continue;
            }

            if ($isDef && preg_match("/{$langs}/", $code)) continue;
            if (!$isDef && !preg_match("/_{$currentLang}$/", $code)) continue;

            if ($translateNames) {
                self::translateFilterItemName($item, $items, $langs);
            }

            $res[$id] = $item;
        }


        return $res;
    }


    private static function translateFilterItemName(&$item, $items, $langs)
    {
        $code = Helper::getArr($item, 'CODE');
        if (!$code) return;

        $origItem = self::getFilterItemOrigVal($items, $code, $langs);
        if (!$origItem) return;

        $item['NAME'] = self::translate($origItem['NAME']);
    }


    private static function getFilterItemOrigVal(&$items, $code, $langs)
    {
        $code = preg_replace("/{$langs}/", '', $code);
        if (!$code) return null;

        foreach ($items as &$item) {
            $itemCode = Helper::getArr($item, 'CODE');
            if ($itemCode === $code) return $item;
        }

        return null;
    }



    /**
     * - Аналог метода $APPLICATION->showTitle()
     * @see bitrix/modules/main/classes/general/main.php
     */
    public static function showTitle($property_name = "title", $strip_tags = true, $google_translate = false)
    {
        global $APPLICATION;
        $APPLICATION->AddBufferContent('CCI\Translator::getTitle', $property_name, $strip_tags, $google_translate);
    }


    /**
     * - Аналог метода $APPLICATION->GetTitle()
     * - переводит строки согласно синтаксису: [ru]ru text[ru][en]en text[en]
     * @see bitrix/modules/main/classes/general/main.php
     * 
     * @return string
     */
    public static function getTitle($property_name = false, $strip_tags = false, $google_translate = false)
    {
        global $APPLICATION;

        if ($property_name !== false && $APPLICATION->GetProperty($property_name) <> '') {
            $res = $APPLICATION->GetProperty($property_name);
        } else {
            $res = $APPLICATION->sDocTitle;
        }

        if ($strip_tags) {
            $res = strip_tags($res);
        }

        if ($google_translate) {
            return self::translate($res);
        }

        return self::translateString($res);
    }


    /**
     * - Аналог метода $APPLICATION->ShowHead()
     * @see bitrix/modules/main/classes/general/main.php
     */
    public static function showHead($bXhtmlStyle = true)
    {
        global $APPLICATION;

        echo '<meta http-equiv="Content-Type" content="text/html; charset=' . LANG_CHARSET . '"' . ($bXhtmlStyle ? ' /' : '') . '>' . "\n";

        self::showMeta("robots", false, $bXhtmlStyle);
        self::showMeta("keywords", false, $bXhtmlStyle);
        self::showMeta("description", false, $bXhtmlStyle);

        $APPLICATION->ShowLink("canonical", null, $bXhtmlStyle);
        $APPLICATION->ShowCSS(true, $bXhtmlStyle);
        $APPLICATION->ShowHeadStrings();
        $APPLICATION->ShowHeadScripts();
    }


    /**
     * - Аналог метода $APPLICATION->ShowMeta()
     * @see bitrix/modules/main/classes/general/main.php
     */
    public static function showMeta($id, $meta_name = false, $bXhtmlStyle = true)
    {
        global $APPLICATION;

        $APPLICATION->AddBufferContent('CCI\Translator::getMeta', $id, $meta_name, $bXhtmlStyle);
    }



    /**
     * - Аналог метода $APPLICATION->GetMeta()
     * - переводит строки согласно синтаксису: [ru]ru text[ru][en]en text[en]
     * @see bitrix/modules/main/classes/general/main.php
     * 
     * @return string
     */
    public static function getMeta($id, $meta_name = false, $bXhtmlStyle = true)
    {
        global $APPLICATION;

        if ($meta_name == false) {
            $meta_name = $id;
        }

        $val = $APPLICATION->GetProperty($id);
        $val = htmlspecialcharsEx($val);
        $val = self::translateString($val);

        if (empty($val)) return '';

        return '<meta name="' . htmlspecialcharsbx($meta_name) . '" content="' . $val . '"' . ($bXhtmlStyle ? ' /' : '') . '>' . "\n";
    }



    /**
     * - Переводит результаты поиска
     * - используется в компоненте itprofit::search.page
     * @param array $items массив $arResult['SEARCH']
     * 
     * @return array
     */
    public static function translateSearchResults($items)
    {
        // if (self::$currentLang === self::$defLang) return $items;

        foreach ($items as &$item) {
            if (self::isSearchSection($item)) {
                self::translateSearchSection($item);
                continue;
            }
            if (self::isSearchElement($item)) {
                self::translateSearchElement($item);
                continue;
            }
        }

        return $items;
    }


    private static function isSearchElement(&$item)
    {
        $id = Helper::getArr($item, 'ITEM_ID');
        return preg_match("/^[\d]+$/", $id);
    }



    private static function isSearchSection(&$item)
    {
        $id = Helper::getArr($item, 'ITEM_ID');
        return preg_match("/^S/", $id);
    }



    private static function translateSearchSection(&$item)
    {
        if (!\Bitrix\Main\Loader::includeModule("iblock")) return;

        $sectionId = preg_replace("/^S/", '', $item['ITEM_ID']);
        $iblockCode = Helper::getArr($item, 'PARAM1');
        $iblockId = Helper::getArr($item, 'PARAM2');

        $lang = strtoupper(self::$currentLang);

        $section = CIBlockSection::GetList(["SORT" => "ASC"], ['IBLOCK_ID' => $iblockId, 'ID' => $sectionId], false, [
            'ID',
            'NAME',
            'DESCRIPTION',
            "UF_DESCRIPTION_$lang",
            "UF_NAME_$lang",
        ])->Fetch();

        if (!$section) return;

        $name = self::translateProperty($section, 'NAME');
        $desc = self::translateProperty($section, 'DESCRIPTION', true);

        self::setItemSearchInfo($item, $name, $desc);
    }


    private static function translateSearchElement(&$item)
    {
        if (!\Bitrix\Main\Loader::includeModule("iblock")) return;

        $id = Helper::getArr($item, 'ITEM_ID');
        $iblockType = Helper::getArr($item, 'PARAM1');
        $iblockId = Helper::getArr($item, 'PARAM2');

        $lang = self::$currentLang;

        $element = CIBlockElement::GetList(['SORT' => 'ASC'], ['ID' => $id, 'IBLOCK_ID' => $iblockId], false, false, [
            'ID',
            'IBLOCK_ID',
            'NAME',
            'PREVIEW_TEXT',
            'DETAIL_TEXT',
            "PROPERTY_NAME_$lang",
            "PROPERTY_PREVIEW_TEXT_$lang",
            "PROPERTY_DETAIL_TEXT_$lang"
        ])->Fetch();

        if (!$element) return;

        $name = self::translateProperty($element, 'NAME');
        $desc = self::translateProperty($element, 'PREVIEW_TEXT', true);
        if (!$desc) {
            $desc = self::translateProperty($element, 'DETAIL_TEXT', true);
        }

        self::setItemSearchInfo($item, $name, $desc);
    }


    private static function setItemSearchInfo(&$item, $name = '', $desc = '')
    {
        if ($name) {
            $item['TITLE'] = $name;
            $item['~TITLE'] = $name;
            $item['TITLE_FORMATED'] = $name;
            $item['~TITLE_FORMATED'] = $name;
        }

        if ($desc) {
            $desc = strip_tags($desc);
            $desc = TruncateText($desc, 300);

            $item['BODY_FORMATED'] = $desc;
            $item['~BODY_FORMATED'] = $desc;
        }
    }




    private static function startSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }


    public static function __updateTranslates()
    {
        self::updateTableTranslates();
        self::updateKeyTranslatesFile();
    }


    /**
     * - Обновляет файл переводов по ключу
     */
    private static function updateKeyTranslatesFile()
    {
        if (!self::$isKeyTranslatesChange) return;
        if (!self::$keyTranslatesFile) return;

        $arr = self::$keyTranslates;

        $content = @json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        @file_put_contents(self::$keyTranslatesFile, $content);
    }


    /**
     * - Обновляет таблицу переводов
     */
    private static function updateTableTranslates()
    {
        global $DB;

        if (!self::$isChanged) return;

        $update = [];
        $insert = [];

        foreach (self::$translates as &$row) {
            if (!empty($row['is_new'])) {
                unset($row['is_new']);
                $insert[] = $row;
                continue;
            }
            if (!empty($row['is_changed'])) {
                unset($row['is_changed']);
                $update[] = $row;
                continue;
            }
        }

        if (!$update && !$insert) return;

        $table = self::$table;

        if ($update) {
            foreach ($update as $row) {
                $id = $row['ID'];
                unset($row['ID']);
                foreach ($row as $key => &$val) {
                    $val = $DB->ForSql($val);
                    $val = "$key='$val'";
                }
                $row = implode(',', $row);
                $DB->Query("UPDATE $table SET $row WHERE `ID` = $id");
            }
        }

        if ($insert) {
            foreach ($insert as $row) {
                $cols = implode(',', array_keys($row));
                $values = array_values($row);
                foreach ($values as &$val) $val = $DB->ForSql($val);
                $values = "'" . implode("','", $values) . "'";
                $DB->Query("INSERT INTO $table ($cols) VALUES ($values)");
            }
        }

        self::startSession();

        $_SESSION['TRANSLATOR_STRINGS'] = self::$translates;
    }
}








class Google_Translate
{

    /**
     * @param string $source
     * @param string $target
     * @param string|array $text
     * @param int $attempts
     *
     * @return string|array With the translation of the text in the target language
     */
    public static function translate($source, $target, $text, $attempts = 5)
    {
        // Request translation
        if (is_array($text)) {
            // Array
            $translation = self::requestTranslationArray($source, $target, $text, $attempts = 5);
        } else {
            // Single
            $translation = self::requestTranslation($source, $target, $text, $attempts = 5);
        }

        return $translation;
    }

    /**
     * @param string $source
     * @param string $target
     * @param array $text
     * @param int $attempts
     *
     * @return array
     */
    protected static function requestTranslationArray($source, $target, $text, $attempts)
    {
        $arr = [];
        foreach ($text as $value) {
            // timeout 0.5 sec
            usleep(500000);
            $arr[] = self::requestTranslation($source, $target, $value, $attempts = 5);
        }

        return $arr;
    }

    /**
     * @param string $source
     * @param string $target
     * @param string $text
     * @param int $attempts
     *
     * @return string
     */
    protected static function requestTranslation($source, $target, $text, $attempts)
    {
        // Google translate URL
        $url = 'https://translate.google.com/translate_a/single?client=at&dt=t&dt=ld&dt=qca&dt=rm&dt=bd&dj=1&hl=uk-RU&ie=UTF-8&oe=UTF-8&inputm=2&otf=2&iid=1dd3b944-fa62-4b55-b330-74909a99969e';

        $fields = [
            'sl' => urlencode($source),
            'tl' => urlencode($target),
            'q' => urlencode($text),
        ];

        if (strlen($fields['q']) >= 5000) {
            throw new \Exception('Maximum number of characters exceeded: 5000');
        }
        // URL-ify the data for the POST
        $fields_string = self::fieldsString($fields);

        $content = self::curlRequest($url, $fields, $fields_string, 0, $attempts);

        if (null === $content) {
            //echo $text,' Error',PHP_EOL;
            return '';
        } else {
            // Parse translation
            return self::getSentencesFromJSON($content);
        }
    }

    /**
     * Dump of the JSON's response in an array.
     *
     * @param string $json
     *
     * @return string
     */
    protected static function getSentencesFromJSON($json)
    {
        $arr = json_decode($json, true);
        $sentences = '';

        if (isset($arr['sentences'])) {
            foreach ($arr['sentences'] as $s) {
                $sentences .= isset($s['trans']) ? $s['trans'] : '';
            }
        }

        return $sentences;
    }

    /**
     * Curl Request attempts connecting on failure.
     *
     * @param string $url
     * @param array $fields
     * @param string $fields_string
     * @param int $i
     * @param int $attempts
     *
     * @return string
     */
    protected static function curlRequest($url, $fields, $fields_string, $i, $attempts)
    {
        $i++;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, count($fields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        //curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AndroidTranslate/5.3.0.RC02.130475354-53000263 5.1 phone TRANSLATE_OPM5_TEST_1');

        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (false === $result || 200 !== $httpcode) {
            // echo $i,'/',$attempts,' Aborted, trying again... ',curl_error($ch),PHP_EOL;

            if ($i >= $attempts) {
                //echo 'Could not connect and get data.',PHP_EOL;
                return;
                //die('Could not connect and get data.'.PHP_EOL);
            } else {
                // timeout 1.5 sec
                usleep(1500000);

                return self::curlRequest($url, $fields, $fields_string, $i, $attempts);
            }
        } else {
            return $result; //self::getBodyCurlResponse();
        }
        curl_close($ch);
    }

    /**
     * Make string with post data fields.
     *
     * @param array $fields
     *
     * @return string
     */
    protected static function fieldsString($fields)
    {
        $fields_string = '';
        foreach ($fields as $key => $value) {
            $fields_string .= $key . '=' . $value . '&';
        }

        return rtrim($fields_string, '&');
    }
}
