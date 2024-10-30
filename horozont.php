<?php
/*
Plugin Name: Horozont
Plugin URI: https://wordpress.org/plugins/horozont/
Description: Плагин для отображения ежедневного гороскопа по категориям.
Version: 1.0
Author: Trigur
License: GPLv2 or later
*/
/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.
This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

include dirname( __FILE__ ) . '/functions.php';

class Horozont
{
    const OPTION_KEY_DATA = 'wp-horozont-data';
    const OPTION_KEY_DATE_UPDATE = 'wp-horozont-dateUpdate';

    protected $types = [       // Типы гороскопов
        'com'   =>  'Общий',
        'ero'   =>  'Эротический',
        'anti'  =>  'Антигороскоп',
        'bus'   =>  'Бизнес',
        'hea'   =>  'Здоровье',
        'cook'  =>  'Кулинарный',
        'lov'   =>  'Любовный',
        'mob'   =>  'Мобильный',
    ];

    protected $marks = [       // Знаки зодиака
        'aries'       => 'Овен',
        'taurus'      => 'Телец',
        'gemini'      => 'Близнецы',
        'cancer'      => 'Рак',
        'leo'         => 'Лев',
        'virgo'       => 'Дева',
        'libra'       => 'Весы',
        'scorpio'     => 'Скорпион',
        'sagittarius' => 'Стрелец',
        'capricorn'   => 'Козерог',
        'aquarius'    => 'Водолей',
        'pisces'      => 'Рыбы',
    ];

    protected $days = [
        'today'      => 'Сегодня',
        'tomorrow'   => 'Завтра',
        'yesterday'  => 'Вчера',
    ];

    protected $dayShift = [
        'yesterday'  => 'today',
        'today'      => 'tomorrow',
        'tomorrow'   => 'tomorrow02',
    ];

    protected $dateUpdate;  // Дата последнего обновления
    protected $data;        // Данные горосокопов

    public function __construct()
    {
        add_shortcode('horozont', [$this, 'shortcodeHoroscope']);
        add_action('admin_menu', [$this, 'menuPluginHoroscope']);
    }

    /**
     * Проверяет наличие и актуальность данных на сайте. Возвращает массив с данными.
     * @return array  данные
     */
    protected function getData()
    {
        if ($this->data) {
            return $this->data;
        }

        $this->dateUpdate = (int) get_option(static::OPTION_KEY_DATE_UPDATE);

        if (!$this->dateUpdate || strtotime(date('Y-m-d')) > $this->dateUpdate) {
            $this->updateData();
        }
        else {
            $this->data = get_option(static::OPTION_KEY_DATA);

            if (! $this->data) {
                $this->updateData();
            }
            else {
                $this->data = json_decode($this->data, true);
            }
        }

        return $this->data;
    }

    /**
     * Получаем код страницы путём cURL
     * @param  string $url Ссылка на страницу
     * @return string      HTML код полученой старинцы
     */
    protected function getPageCode($url)
    {
        $wpHttp = new WP_Http();
        $result = $wpHttp->get($url, ['timeout' => 10]);
        return $result['body'];
    }

    /**
     * Приведение даты формата d.m.Y к timestamp
     * @param  string Дата
     * @return int    timestamp
     */
    protected function dateToString($date)
    {
        $date = explode('.', $date);
        $date = array_reverse($date);
        $date = join('-', $date);

        return strtotime($date);
    }

    /**
     * Получает url xml-источника и возвращает массив с данными
     * @param  string $url Ссылка на поток XML
     * @return array       Данные из XML
     */
    protected function parseXML($url)
    {
        $pageCode = $this->getPageCode($url);
        $pageCode = str_replace(array("\n", "\r", "\t"), '', $pageCode);
        $pageCode = trim(str_replace('"', "'", $pageCode));

        $xml      = simplexml_load_string($pageCode);

        return json_decode(json_encode($xml), true);
    }

    /**
     * Поиск ключей типов по их названиям
     * @param  array  $array  Массив типов для поиска
     * @param  string $search Строка содержащая текст для поиска
     * @return string         Ключ типа
     */
    protected function findInTypes($array, $search)
    {
        $searchString = mb_strtolower($search, 'utf8');

        foreach ($array as $id => $name) {
            $needType = mb_strtolower($name, 'utf8');

            if (stristr($searchString, $needType)) {
                return $id;
            }
        }
    }

    /**
     * Получает гороскоп по определённому типу
     * @param  string $type Идетификатор типа
     * @return array        Массив данных гороскопа
     */
    protected function getHoroscope($type)
    {
        $currentHoroscope = $this->parseXML(
            'http://img.ignio.com/r/export/utf/xml/daily/' . $type . '.xml'
        );

        $resultData = [];
        foreach ($this->marks as $key => $data) {
            $resultData[$key] = $currentHoroscope[$key];
        }

        return [
            'dates' => $currentHoroscope['date']['@attributes'],
            'data'  => $resultData
        ];
    }

    /**
     * Получает все гороскопы
     * @return array Массив данных гороскопа
     */
    protected function getHoroscopeAll()
    {
        $resultData = array();

        foreach ($this->types as $id => $name) {
            $resultData[$id] = $this->getHoroscope($id);
        }

        return $resultData;
    }
    /**
     * Обновление кеша данных
     * @return null
     */
    protected function updateData()
    {
        $this->dateUpdate = strtotime(date('Y-m-d'));
        $this->data = $this->getHoroscopeAll();
        update_option(static::OPTION_KEY_DATE_UPDATE, $this->dateUpdate);
        update_option(static::OPTION_KEY_DATA, json_encode($this->data));
    }

    protected function shiftDay($day)
    {
        return $this->dayShift[$day];
    }

    /**
     * Создание шорткода
     * @param  array $atts Параметры шорткода
     * @return html        HTML код шорткода
     */
    public function shortcodeHoroscope($atts)
    {
        extract(shortcode_atts(array(
            'type' => 'Общий',
            'mark' => 'Овен',
            'day' => 'today'
        ), $atts));

        $type = $this->findInTypes($this->types, $type);
        $mark = $this->findInTypes($this->marks, $mark);
        $day  = $this->findInTypes($this->days, $day);

        if (! ($type && $mark && $day)) {
            return '';
        }

        $data = $this->getData();

        $dateCheck = $this->dateToString($data[$type]['dates']['today']);

        if ($dateCheck !== $this->dateUpdate) {
            $day = $this->shiftDay($day);
        }

        return $data[$type]['data'][$mark][$day];
    }

    /**
     * Добавляем пункты в админ панель
     * @return null
     */
    public function menuPluginHoroscope()
    {
        add_submenu_page(
            'options-general.php',
            'Гороскоп',
            'Гороскоп',
            'manage_options',
            'horozont',
            [$this, 'indexPageHoroscope']
        );
    }

    /**
     * Генерация html options из массива
     * @param  array $arr Массив параметров
     * @return string
     */
    protected function makeOptionsHTML($arr)
    {
        $arr = array_map(function($item) {
            return '<option value="' . $item . '">' . $item . '</option>';
        }, $arr);

        return join('', $arr);
    }

    /**
     * Выводим страницу в админ панеле
     * @return html HTML код страницы
     */
    public function indexPageHoroscope()
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('У вас нет достаточных прав для доступа к этой странице.'));
        }
        ?>
        <div class="wrap">
            <h2 style="text-align:center;width: 574px;">
                Плагин WP Russian Horozont by Trigur
            </h2>

            <div class="card">
                <h3>
                    Пожертвование
                </h3>

                <p>Здравствуйте, спасибо что выбрали именно мой плагин для отображения гороскопов у себя на сайте. Если вы по-настоящему цените мой труд, то пожертвуйте небольшую сумму для того, чтобы я поддерживал и дальше разработку этого плагина. Спасибо 😊</p>

                <a href="https://money.yandex.ru/to/410014425162959" target="_blank">Отправить</a>
            </div>

            <div class="card">
                <h3>Общая информация <span style="float:right">Гороскоп от: <?php echo date('d.m.Y', get_option('wp-horozont-dateUpdate')); ?></span></h3>
                <p>Данный плагин отображает актуальный на текущую дату гороскоп по нескольким категориям. Которые вы можете регулировать и выводить информацию в шорткоде.</p>
                <h3>Структура шорткодов</h3>
                <p><code>[horozont type="%TYPE%" mark="%MARK%"]</code><br><br>
                    <b>%TYPE%</b> - Тип гороскопа. Например: <i>Любовный</i> <br>
                    <b>%MARK%</b> - Знак гороскопа. Например: <i>Бизнецы</i> <br>
                    <b>%DAY%</b> - День. Например: <i>Сегодня</i>
                </p>
                <h3>Генератор шорткодов</h3>
                <div style="text-align:center">
                    <select class="postform" id="type" onchange="generateCode()">
                        <?= $this->makeOptionsHTML($this->types); ?>
                    </select>

                    <select class="postform" id="mark" onchange="generateCode()">
                        <?= $this->makeOptionsHTML($this->marks); ?>
                    </select>

                    <select class="postform" id="day" onchange="generateCode()">
                        <?= $this->makeOptionsHTML($this->days); ?>
                    </select>
                    <br>
                    <p id="code" style="padding-top:5px;">
                        <code>[horozont type="Общий" mark="Овен" day="Сегодня"]</code>
                    </p>
                </div>
            </div>

            <div class="card">
                <h3>Источник гороскопов</h3>
                <a href="http://img.ignio.com/static/r/public/export/" target="_blank">
                    Условия использования
                </a>

                <br>

                <h3>Написать создателю плагина</h3>
                Email: <a href="mailto:trigur@yandex.ru">trigur@yandex.ru</a>
            </div>
        </div>

        <script>
            function getValue(id) {
                var element = document.getElementById(id);

                return element.options[element.selectedIndex].text;
            }

            function generateCode() {
                document.getElementById("code")
                    .innerHTML = "<code>[horozont type=\"" + getValue('type') +
                        "\" mark=\"" + getValue('mark') +
                        "\" day=\"" + getValue('day') +
                        "\"]</code>";
            }
        </script>
        <?php
    }
}

$horozont = new Horozont;