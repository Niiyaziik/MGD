document.addEventListener("DOMContentLoaded", async () => {
    // 0 – только активные кандидаты
    const API_URL = "/candidates/admin/index?format=json&deleted=0";

    const tbody = document.getElementById("candidates-tbody");
    const dateFromInput = document.getElementById("date-from");
    const dateToInput = document.getElementById("date-to");
    const surnameSearch = document.getElementById("surname-search");

    const foundCountEl = document.getElementById("found-count");
    const totalCountEl = document.getElementById("total-count");

    let allCandidates = [];
    let currentSortField = null;
    let currentSortDir = null;

    // Загружаем данные
    try {
        const res = await fetch(API_URL, { headers: { "Accept": "application/json" } });
        if (!res.ok) throw new Error("Ошибка загрузки");
        const data = await res.json();
        allCandidates = Array.isArray(data) ? data : [];
    } catch (e) {
        console.error(e);
        tbody.innerHTML = `<tr><td colspan="13">Ошибка загрузки данных.</td></tr>`;
        return;
    }

    // Общее количество
    totalCountEl.textContent = allCandidates.length.toString();

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
        let list = [...allCandidates];

        // Фильтр по периоду регистрации
        const fromVal = dateFromInput.value;
        const toVal = dateToInput.value;

        if (fromVal) {
            const fromDate = new Date(fromVal + "T00:00:00");
            list = list.filter(c => {
                if (!c.registration_date) return false;
                const d = new Date(c.registration_date);
                return d >= fromDate;
            });
        }
        if (toVal) {
            const toDate = new Date(toVal + "T23:59:59");
            list = list.filter(c => {
                if (!c.registration_date) return false;
                const d = new Date(c.registration_date);
                return d <= toDate;
            });
        }

        // Поиск по фамилии
        const q = surnameSearch.value.trim().toLowerCase();
        if (q) {
            list = list.filter(c => (c.surname || "").toLowerCase().includes(q));
        }

        // Сортировка
        if (currentSortField) {
            list.sort((a, b) => compareCandidates(a, b, currentSortField, currentSortDir));
        }

        // Обновляем "найдено"
        foundCountEl.textContent = list.length.toString();

        // Рендер таблицы
        renderTable(list);
    }

    function compareCandidates(a, b, field, dir) {
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
            tbody.innerHTML = `<tr><td colspan="13">Записей не найдено.</td></tr>`;
            return;
        }

        tbody.innerHTML = list.map(candidateToRowHtml).join("");
    }

    function escapeHtml(str) {
        return String(str ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;");
    }

    function candidateToRowHtml(c) {
        const id = c.id ?? "";
        const regDate = c.registration_date ?? "";
        const surname = c.surname ?? "";
        const name = c.name ?? "";
        const patronymic = c.patronymic ?? "";
        const phone = c.phone ?? "";
        const photo = c.photo ?? "";
        const vkLink = c.link_vk ?? "";
        const street = c.street ?? "";
        const house = c.house ?? "";
        const district = c.district ?? "";

        const photoSrc = photo || "/assets/img/candidates/placeholder.jpeg";

        // страница визуального редактора кандидата
        const editUrl = `/candidates/${encodeURIComponent(id)}/edit`;

        return `
    <tr data-id="${escapeHtml(id)}">
        <td>${escapeHtml(id)}</td>
        <td>${escapeHtml(regDate)}</td>
        <td>${escapeHtml(surname)}</td>
        <td>${escapeHtml(name)}</td>
        <td>${escapeHtml(patronymic)}</td>
        <td>${escapeHtml(phone)}</td>
        <td>
            <img src="${escapeHtml(photoSrc)}" alt="Фото" style="width:40px;height:40px;object-fit:cover;">
        </td>
        <td>${vkLink ? `<a href="${escapeHtml(vkLink)}" target="_blank">Профиль</a>` : ""}</td>
        <td>${escapeHtml(street)}</td>
        <td>${escapeHtml(house)}</td>
        <td>${escapeHtml(district)}</td>
        <td class="users-table__actions">
            <a href="${editUrl}" class="icon-btn" title="Редактировать">
                <img src="/assets/icons/update.png" class="icon-png" alt="Редактировать">
            </a>
        </td>
        <td class="users-table__actions">
            <button class="icon-btn delete-candidate-btn" data-id="${escapeHtml(id)}" title="Удалить">
                <img src="/assets/icons/delete.png" class="icon-png" alt="Удалить">
            </button>
        </td>
    </tr>
`;

    }
    document.addEventListener("click", async (e) => {
        const delBtn = e.target.closest(".delete-candidate-btn");
        if (!delBtn) return;

        const id = delBtn.dataset.id;
        if (!id) return;

        if (!confirm("Удалить кандидата?")) return;

        const res = await fetch("/candidates/admin/delete", {
            method: "DELETE",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify({ id })
        });

        const out = await res.json().catch(() => ({}));

        if (!res.ok || out.ok === false) {
            showMessage("Ошибка удаления: " + (out.error || "Неизвестная ошибка"), "Ошибка");
            return;
        }

        // убираем из локального массива и перерисовываем
        allCandidates = allCandidates.filter(c => String(c.id) !== String(id));
        totalCountEl.textContent = allCandidates.length.toString();
        applyAndRender();
    });
});

document.addEventListener("DOMContentLoaded", () => {
    const downloadBtn = document.querySelector(".votes-download-btn");
    if (downloadBtn) {
        downloadBtn.addEventListener("click", () => {
            window.location.href = "/candidates/admin/export";
        });
    }
});