document.addEventListener("DOMContentLoaded", async () => {
    // deleted=1 → только удалённые кандидаты
    const API_URL = "/candidates/admin/deleted?format=json&deleted=1";

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
        tbody.innerHTML = `<tr><td colspan="11">Ошибка загрузки данных.</td></tr>`;
        return;
    }

    // общее количество
    totalCountEl.textContent = allCandidates.length.toString();

    // Инициализация обработчиков кликов на заголовки
    initSortableHeaders();

    // первый рендер
    applyAndRender();

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

        // период регистрации
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

        // поиск по фамилии
        const q = surnameSearch.value.trim().toLowerCase();
        if (q) {
            list = list.filter(c => (c.surname || "").toLowerCase().includes(q));
        }

        // сортировка
        if (currentSortField) {
            list.sort((a, b) => compareCandidates(a, b, currentSortField, currentSortDir));
        }

        foundCountEl.textContent = list.length.toString();

        renderTable(list);
    }

    function compareCandidates(a, b, field, dir) {
        const mul = dir === "asc" ? 1 : -1;

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

        const na = Number(vaRaw);
        const nb = Number(vbRaw);

        if (!Number.isNaN(na) && !Number.isNaN(nb)) {
            return (na - nb) * mul;
        }

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

        return `
            <tr>
                <td>${escapeHtml(id)}</td>
                <td>${escapeHtml(regDate)}</td>
                <td>${escapeHtml(surname)}</td>
                <td>${escapeHtml(name)}</td>
                <td>${escapeHtml(patronymic)}</td>
                <td>${escapeHtml(phone)}</td>
                <td>
                    <img src="${escapeHtml(photoSrc)}" alt="Фото" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">
                </td>
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
            window.location.href = "/candidates/admin/export?deleted=1";
        });
    }
});
