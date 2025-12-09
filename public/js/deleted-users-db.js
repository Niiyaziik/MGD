document.addEventListener("DOMContentLoaded", async () => {
    const API_URL = "/users/admin?format=json&deleted=1"; // удалённые пользователи

    const tbody = document.getElementById("users-tbody");
    const sortFieldSelect = document.getElementById("sort-field");
    const sortDirSelect = document.getElementById("sort-dir");
    const dateFromInput = document.getElementById("date-from");
    const dateToInput = document.getElementById("date-to");
    const authFilter = document.getElementById("auth-method-filter");
    const districtFilter = document.getElementById("district-filter");
    const surnameSearch = document.getElementById("surname-search");

    const foundCountEl = document.getElementById("found-count");
    const totalCountEl = document.getElementById("total-count");

    let allUsers = [];

    // Загружаем данные
    try {
        const res = await fetch(API_URL, { headers: { "Accept": "application/json" } });
        if (!res.ok) throw new Error("Ошибка загрузки");
        const data = await res.json();
        allUsers = Array.isArray(data) ? data : [];
    } catch (e) {
        console.error(e);
        tbody.innerHTML = `<tr><td colspan="11">Ошибка загрузки данных.</td></tr>`;
        return;
    }

    // Заполняем селекты "Метод авторизации" и "Округ" из данных
    fillFiltersFromData(allUsers);

    // Устанавливаем общее количество
    totalCountEl.textContent = allUsers.length.toString();

    // Первый рендер
    applyAndRender();

    // События фильтров
    [sortFieldSelect, sortDirSelect].forEach(el => {
        el.addEventListener("change", applyAndRender);
    });

    [dateFromInput, dateToInput, authFilter, districtFilter].forEach(el => {
        el.addEventListener("change", applyAndRender);
    });

    surnameSearch.addEventListener("input", applyAndRender);

    function fillFiltersFromData(users) {
        const authSet = new Set();
        const distSet = new Set();

        users.forEach(u => {
            if (u.auth_method) authSet.add(u.auth_method);
            if (u.district) distSet.add(u.district);
        });

        // методы авторизации
        authSet.forEach(method => {
            const opt = document.createElement("option");
            opt.value = method;
            opt.textContent = method;
            authFilter.appendChild(opt);
        });

        // округа
        Array.from(distSet)
            .sort((a, b) => String(a).localeCompare(String(b), "ru"))
            .forEach(d => {
                const opt = document.createElement("option");
                opt.value = d;
                opt.textContent = d;
                districtFilter.appendChild(opt);
            });
    }

    function applyAndRender() {
        let list = [...allUsers];

        // Фильтр по периоду регистрации
        const fromVal = dateFromInput.value;
        const toVal = dateToInput.value;

        if (fromVal) {
            const fromDate = new Date(fromVal + "T00:00:00");
            list = list.filter(u => {
                if (!u.registration_date) return false;
                const d = new Date(u.registration_date);
                return d >= fromDate;
            });
        }
        if (toVal) {
            const toDate = new Date(toVal + "T23:59:59");
            list = list.filter(u => {
                if (!u.registration_date) return false;
                const d = new Date(u.registration_date);
                return d <= toDate;
            });
        }

        // Фильтр по методу авторизации
        const authVal = authFilter.value;
        if (authVal) {
            list = list.filter(u => u.auth_method === authVal);
        }

        // Фильтр по округу
        const distVal = districtFilter.value;
        if (distVal) {
            list = list.filter(u => String(u.district) === String(distVal));
        }

        // Поиск по фамилии
        const q = surnameSearch.value.trim().toLowerCase();
        if (q) {
            list = list.filter(u => (u.surname || "").toLowerCase().includes(q));
        }

        // Сортировка
        const sortField = sortFieldSelect.value;
        const sortDir = sortDirSelect.value;

        list.sort((a, b) => compareUsers(a, b, sortField, sortDir));

        // Обновляем счётчик "найдено"
        foundCountEl.textContent = list.length.toString();

        // Рендер таблицы
        renderTable(list);
    }

    function compareUsers(a, b, field, dir) {
        const mul = dir === "asc" ? 1 : -1;

        if (field === "registration_date") {
            const da = a.registration_date ? new Date(a.registration_date) : null;
            const db = b.registration_date ? new Date(b.registration_date) : null;
            if (!da && !db) return 0;
            if (!da) return 1 * mul;
            if (!db) return -1 * mul;
            return (da - db) * mul;
        }

        const va = (a[field] ?? "").toString().toLowerCase();
        const vb = (b[field] ?? "").toString().toLowerCase();
        if (va < vb) return -1 * mul;
        if (va > vb) return 1 * mul;
        return 0;
    }

    function renderTable(list) {
        if (list.length === 0) {
            tbody.innerHTML = `<tr><td colspan="11">Записей не найдено.</td></tr>`;
            return;
        }

        tbody.innerHTML = list.map(userToRowHtml).join("");
    }

    function escapeHtml(str) {
        return String(str ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;");
    }

    function userToRowHtml(u) {
        const id = u.id ?? "";
        const regDate = u.registration_date ?? "";
        const authMethod = u.auth_method ?? "";
        const surname = u.surname ?? "";
        const name = u.name ?? "";
        const patronymic = u.patronymic ?? "";
        const phone = u.phone ?? "";
        const vkLink = u.link_vk ?? "";
        const street = u.street ?? "";
        const house = u.house ?? "";
        const district = u.district ?? "";

        return `
            <tr>
                <td>${escapeHtml(id)}</td>
                <td>${escapeHtml(regDate)}</td>
                <td>${escapeHtml(authMethod)}</td>
                <td>${escapeHtml(surname)}</td>
                <td>${escapeHtml(name)}</td>
                <td>${escapeHtml(patronymic)}</td>
                <td>${escapeHtml(phone)}</td>
                <td>${vkLink ? `<a href="${escapeHtml(vkLink)}" target="_blank">Профиль</a>` : ""}</td>
                <td>${escapeHtml(street)}</td>
                <td>${escapeHtml(house)}</td>
                <td>${escapeHtml(district)}</td>
            </tr>
        `;
    }
});
