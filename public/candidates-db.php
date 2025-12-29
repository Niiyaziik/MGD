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

        <!-- Панель фильтров (только дата регистрации и поиск по фамилии) -->
        <div class="users-filters">
            <!-- Период регистрации -->
            <div class="users-filters__field">
                <label>Период регистрации (от):</label>
                <input type="date" id="date-from">
            </div>
            <div class="users-filters__field">
                <label>Период регистрации (до):</label>
                <input type="date" id="date-to">
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
                    <th class="sortable-header" data-field="id">
                        <span>ID</span>
                        <img src="/assets/icons/filter.svg" class="filter-icon" alt="Фильтр">
                        <span class="sort-indicator"></span>
                    </th>
                    <th>Дата регистрации</th>
                    <th class="sortable-header" data-field="surname">
                        <span>Фамилия</span>
                        <img src="/assets/icons/filter.svg" class="filter-icon" alt="Фильтр">
                        <span class="sort-indicator"></span>
                    </th>
                    <th class="sortable-header" data-field="name">
                        <span>Имя</span>
                        <img src="/assets/icons/filter.svg" class="filter-icon" alt="Фильтр">
                        <span class="sort-indicator"></span>
                    </th>
                    <th class="sortable-header" data-field="patronymic">
                        <span>Отчество</span>
                        <img src="/assets/icons/filter.svg" class="filter-icon" alt="Фильтр">
                        <span class="sort-indicator"></span>
                    </th>
                    <th class="sortable-header" data-field="phone">
                        <span>Телефон</span>
                        <img src="/assets/icons/filter.svg" class="filter-icon" alt="Фильтр">
                        <span class="sort-indicator"></span>
                    </th>
                    <th>Фото</th>
                    <th>ВК</th>
                    <th class="sortable-header" data-field="street">
                        <span>Улица</span>
                        <img src="/assets/icons/filter.svg" class="filter-icon" alt="Фильтр">
                        <span class="sort-indicator"></span>
                    </th>
                    <th class="sortable-header" data-field="house">
                        <span>Дом</span>
                        <img src="/assets/icons/filter.svg" class="filter-icon" alt="Фильтр">
                        <span class="sort-indicator"></span>
                    </th>
                    <th class="sortable-header" data-field="district">
                        <span>Округ</span>
                        <img src="/assets/icons/filter.svg" class="filter-icon" alt="Фильтр">
                        <span class="sort-indicator"></span>
                    </th>
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
