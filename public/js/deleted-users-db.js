document.addEventListener("DOMContentLoaded", async () => {
    const API_URL = "/users/admin?format=json&deleted=1"; // удалённые пользователи

    const tbody = document.getElementById("users-tbody");
    const dateFromInput = document.getElementById("date-from");
    const dateToInput = document.getElementById("date-to");
    const surnameSearch = document.getElementById("surname-search");

    const foundCountEl = document.getElementById("found-count");
    const totalCountEl = document.getElementById("total-count");

    let allUsers = [];
    let currentSortField = null;
    let currentSortDir = null;

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

    // Устанавливаем общее количество
    totalCountEl.textContent = allUsers.length.toString();

    // Инициализация обработчиков кликов на заголовки
    initSortableHeaders();

    // Первый рендер
    applyAndRender();

    // События фильтров
    [dateFromInput, dateToInput].forEach(el => {
        el.addEventListener("change", applyAndRender);
    });

    surnameSearch.addEventListener("input", applyAndRender);

    function initSortableHeaders() {
        const headers = document.querySelectorAll(".sortable-header");
        headers.forEach(header => {
            header.style.cursor = "pointer";
            header.addEventListener("click", () => {
                const field = header.dataset.field;
                handleHeaderClick(field, header);
            });
        });
    }

    function handleHeaderClick(field, headerElement) {
        // Если уже сортируем по этому полю, меняем направление
        if (currentSortField === field) {
            currentSortDir = currentSortDir === "asc" ? "desc" : "asc";
        } else {
            currentSortField = field;
            currentSortDir = "asc";
        }

        // Обновляем визуальные индикаторы
        updateSortIndicators();

        applyAndRender();
    }

    function updateSortIndicators() {
        const headers = document.querySelectorAll(".sortable-header");
        headers.forEach(header => {
            const indicator = header.querySelector(".sort-indicator");
            const field = header.dataset.field;

            if (currentSortField === field) {
                indicator.textContent = currentSortDir === "asc" ? " ↑" : " ↓";
                header.classList.add("sorted");
            } else {
                indicator.textContent = "";
                header.classList.remove("sorted");
            }
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

        // Поиск по фамилии
        const q = surnameSearch.value.trim().toLowerCase();
        if (q) {
            list = list.filter(u => (u.surname || "").toLowerCase().includes(q));
        }

        // Сортировка
        if (currentSortField) {
            list.sort((a, b) => compareUsers(a, b, currentSortField, currentSortDir));
        }

        // Обновляем счётчик "найдено"
        foundCountEl.textContent = list.length.toString();

        // Рендер таблицы
        renderTable(list);
    }

    function compareUsers(a, b, field, dir) {
        const mul = dir === "asc" ? 1 : -1;

        // ===== ДАТА =====
        if (field === "registration_date") {
            const da = a.registration_date ? new Date(a.registration_date) : null;
            const db = b.registration_date ? new Date(b.registration_date) : null;
            if (!da && !db) return 0;
            if (!da) return 1 * mul;
            if (!db) return -1 * mul;
            return (da - db) * mul;
        }

        const vaRaw = a[field];
        const vbRaw = b[field];

        // ===== ЧИСЛА =====
        const na = Number(vaRaw);
        const nb = Number(vbRaw);

        if (!Number.isNaN(na) && !Number.isNaN(nb)) {
            return (na - nb) * mul;
        }

        // ===== СТРОКИ =====
        const va = (vaRaw ?? "").toString().toLowerCase();
        const vb = (vbRaw ?? "").toString().toLowerCase();

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

document.addEventListener("DOMContentLoaded", () => {
    const downloadBtn = document.querySelector(".votes-download-btn");
    if (downloadBtn) {
        downloadBtn.addEventListener("click", () => {
            window.location.href = "/users/admin/export?deleted=1";
        });
    }
});
