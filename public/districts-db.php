<!doctype html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Адреса по округам</title>
    <link rel="stylesheet" href="/assets/main.css">
</head>

<body>
<div class="site-body">
    <?php include __DIR__ . '/part/sidebar.php'; ?>

    <div class="users-page">
        <h1>Округа</h1>

        <!-- Фильтры (только поиск по улице) -->
        <div class="users-filters">
            <!-- Поиск по улице -->
            <div class="users-filters__field">
                <label for="street-search">Поиск по улице:</label>
                <input type="text" id="street-search" placeholder="Введите улицу">
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
                    <th class="sortable-header" data-field="district">
                        <span>Округ</span>
                        <img src="/assets/icons/filter.svg" class="filter-icon" alt="Фильтр">
                        <span class="sort-indicator"></span>
                    </th>
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
                    <th>Действия</th>
                </tr>
                </thead>
                <tbody id="districts-tbody">
                <!-- строки подставит JS -->
                </tbody>
            </table>
        </div>

        <!-- Итоги + кнопки -->
        <div class="users-footer">
            <div class="users-footer__info">
                <span>Всего найдено записей: <strong id="found-count">0</strong></span>
                <span>Всего адресов в базе: <strong id="total-count">0</strong></span>
            </div>
            <div class="users-footer__buttons">
                <button type="button" id="check-duplicates-btn" class="btn btn-outline">
                    Проверить базу на повторы
                </button>
                <a href="/deleted-districts.php" class="btn btn-outline">Удалённые</a>
            </div>
        </div>

        <div id="duplicates-result" style="margin-top: 12px; font-size: 14px; color: #a30000;"></div>
    </div>
</div>

<script src="/js/districts-db.js"></script>
</body>
</html>
