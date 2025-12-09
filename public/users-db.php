<!doctype html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Пользователи</title>
    <link rel="stylesheet" href="/assets/main.css">
</head>

<body>
    <div class="site-body">
        <?php include __DIR__ . '/part/sidebar.php'; ?>
        <!-- <div class="main"> -->
            <div class="users-page">

                <h1>Пользователи</h1>

                <!-- Панель фильтров и сортировки -->
                <div class="users-filters">

                    <!-- Сортировка -->
                    <div class="users-filters__field">
                        <label for="sort-field">Сортировать по:</label>
                        <select id="sort-field">
                            <option value="registration_date">Дата регистрации</option>
                            <option value="auth_method">Метод авторизации</option>
                            <option value="surname">Фамилия</option>
                            <option value="street">Улица проживания</option>
                            <option value="district">Округ</option>
                        </select>
                    </div>

                    <div class="users-filters__field">
                        <label for="sort-dir">Порядок:</label>
                        <select id="sort-dir">
                            <option value="desc">По убыванию</option>
                            <option value="asc">По возрастанию</option>
                        </select>
                    </div>

                    <!-- Период регистрации -->
                    <div class="users-filters__field">
                        <label>Период регистрации (от):</label>
                        <input type="date" id="date-from">
                    </div>
                    <div class="users-filters__field">
                        <label>Период регистрации (до):</label>
                        <input type="date" id="date-to">
                    </div>

                    <!-- Метод авторизации -->
                    <div class="users-filters__field">
                        <label for="auth-method-filter">Метод авторизации:</label>
                        <select id="auth-method-filter">
                            <option value="">Все методы</option>
                            <!-- опции заполним из данных -->
                        </select>
                    </div>

                    <!-- Округ -->
                    <div class="users-filters__field">
                        <label for="district-filter">Округ:</label>
                        <select id="district-filter">
                            <option value="">Все округа</option>
                            <!-- опции заполним из данных -->
                        </select>
                    </div>

                    <!-- Поиск по фамилии -->
                    <div class="users-filters__field">
                        <label for="surname-search">Поиск по фамилии:</label>
                        <input type="text" id="surname-search" placeholder="Введите фамилию">
                    </div>
                </div>

                <!-- Таблица -->
                <div class="users-table-wrapper">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Дата регистрации</th>
                                <th>Метод авторизации</th>
                                <th>Фамилия</th>
                                <th>Имя</th>
                                <th>Отчество</th>
                                <th>Телефон</th>
                                <th>ВК</th>
                                <th>Улица</th>
                                <th>Дом</th>
                                <th>Округ</th>
                                <th colspan="2" style="text-align:center;">Действия</th>
                            </tr>
                        </thead>
                        <tbody id="users-tbody">
                            <!-- строки будут добавлены скриптом -->
                        </tbody>
                    </table>
                </div>

                <!-- Итоги и кнопка -->
                <div class="users-footer">
                    <div class="users-footer__info">
                        <span>Всего найдено записей: <strong id="found-count">0</strong> человек</span>
                        <span>Всего пользователей в базе: <strong id="total-count">0</strong> человек</span>
                    </div>
                    <a href="/deleted-users-db.php" class="btn btn-outline">Удалённые пользователи</a>
                </div>

            </div>
        <!-- </div> -->
    </div>

    <script src="/js/users-db.js"></script>
    <footer class="footer">

    <div class="footer__body">
            <ul class="footer__menu">
                    <li class="footer__menu-item"><span class="title">О думе</span> <img
                                    src="/local/templates/ugd/img/blocks/bley-arrow.png"
                                    class="footer__arrow" alt="" title="">
                    </li>
                    <li class="footer__menu-item"><a class="footer__menu-link" href="/about/"
                                    onclick="openTab2(event, 'history')">История</a></li>
                    <li class="footer__menu-item"><a class="footer__menu-link"
                                    href="/about/#structure"
                                    onclick="openTab2(event, 'structure')">Структура</a></li>
                    <li class="footer__menu-item"><a class="footer__menu-link"
                                    href="/about/#apparat"
                                    onclick="openTab2(event, 'apparat')">Аппарат УГД</a></li>
                    <li class="footer__menu-item"><a class="footer__menu-link"
                                    href="/about/#reglament"
                                    onclick="openTab2(event, 'reglament')">Регламент</a></li>
                    <li class="footer__menu-item"><a class="footer__menu-link"
                                    href="/about/#service"
                                    onclick="openTab2(event, 'service')">Муниципальная служба</a>
                    </li>
                    <li class="footer__menu-item"><a class="footer__menu-link"
                                    href="/main_news/">Новости</a></li>
                    <br>
                    <li class="footer__menu-item"><span class="title">Депутаты</span></li>
                    <li class="footer__menu-item"><a class="footer__menu-link" href="/dep/">Состав
                                    думы VI созыва</a>
                    </li>
                    <!--<li class="footer__menu-item"><a class="footer__menu-link" href="#">Благодарности депутатам</a></li>-->
            </ul>
            <ul class="footer__menu">
                    <li class="footer__menu-item"><span class="title">Деятельность</span> <img
                                    src="/local/templates/ugd/img/blocks/bley-arrow.png"
                                    class="footer__arrow" alt="" title="">
                    </li>
                    <li class="footer__menu-item"><a class="footer__menu-link"
                                    href="/docs/#solutions">Решения</a></li>
                    <li class="footer__menu-item"><a class="footer__menu-link"
                                    href="/docs/#resolutions">Постановления</a></li>
                    <li class="footer__menu-item"><a class="footer__menu-link"
                                    href="/docs/#glava-gor-resolutions">Постановления Главы
                                    города</a></li>
                    <li class="footer__menu-item"><a class="footer__menu-link"
                                    href="/docs/#glava-gor-orders">Распоряжения Главы города</a>
                    </li>
                    <li class="footer__menu-item"><a class="footer__menu-link"
                                    href="/docs/#appealnpa">Порядок
                                    обжалования НПА УГД</a></li>
                    <li class="footer__menu-item"><a class="footer__menu-link"
                                    href="/docs/#other-documents">Документы</a></li>
                    <li class="footer__menu-item"><a class="footer__menu-link"
                                    href="/docs/#ocherednoe-zasedanie">Очередное заседание</a></li>
                    <li class="footer__menu-item"><a class="footer__menu-link"
                                    href="/docs/#plan-raboty-2022">План
                                    работы на I полугодие 2023 г.</a></li>
                    <li class="footer__menu-item"><a class="footer__menu-link"
                                    href="/docs/#plan_raboty2">План работы на
                                    октябрь 2023 г.</a></li>
                    <li class="footer__menu-item"><a class="footer__menu-link"
                                    href="/about/#korrupt">Противодействие
                                    коррупции</a></li>
                    <li class="footer__menu-item"><a class="footer__menu-link"
                                    href="/docs/#projecty_documentov">Проекты
                                    документов</a></li>
            </ul>
            <ul class="footer__menu">
                    <li class="footer__menu-item"><span class="title">Приемная</span> <img
                                    src="/local/templates/ugd/img/blocks/bley-arrow.png"
                                    class="footer__arrow" alt="" title="">
                    </li>
                    <li class="footer__menu-item"><a class="footer__menu-link"
                                    href="/reception/">Работа с обращениями
                                    граждан</a></li>
                    <li class="footer__menu-item"><a class="footer__menu-link"
                                    href="/reception/#graphik">График приема
                                    избирателей депутатами УГД в общественной приёмной</a></li>
                    <br>
                    <li class="footer__menu-item"><span class="title">Комитеты</span></li>
                    <li class="footer__menu-item"><a class="footer__menu-link"
                                    href="/committees/">Состав комитетов</a>
                    </li>
                    <li class="footer__menu-item"><a class="footer__menu-link"
                                    href="/committees/#rabota-komitetov">Работа комитетов</a></li>
                    <li class="footer__menu-item"><a class="footer__menu-link"
                                    href="/committees/#arhiv-povestok">Архив
                                    повесток заседаний комитетов</a></li>
            </ul>
            <ul class="footer_contact_all">
                    <p class="footer_copyrigt">© 2025 Ульяновская Городская Дума</p>
                    <p class="footer_addr">ул. Кузнецова, 7</p>
                    <p class="footer_phone">Телефон: (8422) 41-38-00</p>
                    <p class="footer_mail">e-mail: <a href="mailto:duma@ugd.ru">duma@ugd.ru</a></p>
                    <p class="footer_personal"><a target="_blank"
                                    href="/upload/politica-2018.pdf">Политика в отношении
                                    обработки персональных данных</a></p>
            </ul>
            <!--
<ul class="footer__menu">
<li class="footer__menu-item"><span class="title">О городе</span> <img src="/local/templates/ugd/img/blocks/bley-arrow.png" class="footer__arrow" alt="" title=""></li>
</ul>
-->
    </div>
</footer>
<div class="footer-copyright-design">
    <div class="container">Разработано в <a href="http://agatech.ru/" target="_blank"
                    title="agatech.ru">AGATECH</a></div>
</div>

</body>

</html>