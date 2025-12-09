<!doctype html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Удалённые адреса по округам</title>
    <link rel="stylesheet" href="/assets/main.css">
</head>

<body>
<div class="site-body">
    <?php include __DIR__ . '/part/sidebar.php'; ?>

    <div class="users-page">
        <h1>Удалённые адреса по округам</h1>

        <!-- Фильтры и сортировка -->
        <div class="users-filters">
            <div class="users-filters__field">
                <label for="sort-field">Сортировать по:</label>
                <select id="sort-field">
                    <option value="district">Округ</option>
                    <option value="street">Улица</option>
                </select>
            </div>

            <div class="users-filters__field">
                <label for="sort-dir">Порядок:</label>
                <select id="sort-dir">
                    <option value="asc">По возрастанию</option>
                    <option value="desc">По убыванию</option>
                </select>
            </div>

            <div class="users-filters__field">
                <label for="district-filter">Округ:</label>
                <select id="district-filter">
                    <option value="">Все округа</option>
                </select>
            </div>

            <div class="users-filters__field">
                <label for="street-search">Поиск по улице:</label>
                <input type="text" id="street-search" placeholder="Введите улицу">
            </div>
        </div>

        <div class="users-table-wrapper">
            <table class="users-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Округ</th>
                    <th>Улица</th>
                    <th>Дом</th>
                </tr>
                </thead>
                <tbody id="districts-tbody">
                </tbody>
            </table>
        </div>

        <div class="users-footer">
            <div class="users-footer__info">
                <span>Всего найдено записей: <strong id="found-count">0</strong></span>
                <span>Всего адресов в базе: <strong id="total-count">0</strong></span>
            </div>
            <div class="users-footer__buttons">
                <a href="/districts-db.php" class="btn btn-outline">Активные</a>
            </div>
        </div>
    </div>
</div>

<script src="/js/deleted-districts-db.js"></script>
</body>
</html>
