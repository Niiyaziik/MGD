<!doctype html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Кандидаты</title>
    <link rel="stylesheet" href="/assets/main.css">
</head>

<body>
<div class="site-body">
    <?php include __DIR__ . '/part/sidebar.php'; ?>

    <div class="users-page">

        <h1>Кандидаты</h1>

        <!-- Панель фильтров и сортировки -->
        <div class="users-filters">

            <!-- Сортировка -->
            <div class="users-filters__field">
                <label for="sort-field">Сортировать по:</label>
                <select id="sort-field">
                    <option value="registration_date">Дата регистрации</option>
                    <option value="surname">Фамилия</option>
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
                    <th>Фамилия</th>
                    <th>Имя</th>
                    <th>Отчество</th>
                    <th>Телефон</th>
                    <th>Фото</th>
                    <th>ВК</th>
                    <th>Улица</th>
                    <th>Дом</th>
                    <th>Округ</th>
                    <th colspan="2" style="text-align:center;">Действия</th>
                </tr>
                </thead>
                <tbody id="candidates-tbody">
                <!-- строки будут добавлены скриптом -->
                </tbody>
            </table>
        </div>

        <!-- Итоги и кнопки -->
        <div class="users-footer">
            <div class="users-footer__info">
                <span>Всего найдено записей: <strong id="found-count">0</strong> человек</span>
                <span>Всего кандидатов в базе: <strong id="total-count">0</strong> человек</span>
            </div>
            <div class="users-footer__buttons">
                <a href="/candidates/admin/export" class="btn btn-outline">Скачать БД</a>
                <a href="/deleted-candidates-db.php" class="btn btn-outline">Удалённые</a>
            </div>
        </div>

    </div>
</div>

<script src="/js/candidates-db.js"></script>
</body>
</html>
