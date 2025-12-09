<!doctype html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Удалённые пользователи</title>
    <link rel="stylesheet" href="/assets/main.css">
</head>

<body>
    <div class="site-body">
        <?php include __DIR__ . '/part/sidebar.php'; ?>

        <div class="users-page">

            <h1>Удалённые пользователи</h1>

            <!-- Панель фильтров и сортировки (оставляем такую же) -->
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
                            <!-- БЕЗ колонок Действия -->
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
                <a href="/users-db.php" class="btn btn-outline">Активные пользователи</a>
            </div>

        </div>
    </div>

    <script src="/js/deleted-users-db.js"></script>
</body>

</html>
