document.addEventListener("DOMContentLoaded", async () => {
    const API_URL = "/districts/admin/index?format=json&deleted=1";

    const tbody = document.getElementById("districts-tbody");
    const streetSearch = document.getElementById("street-search");

    const foundCountEl = document.getElementById("found-count");
    const totalCountEl = document.getElementById("total-count");

    let allRows = [];
    let currentSortField = null;
    let currentSortDir = null;

    try {
        const res = await fetch(API_URL, { headers: { "Accept": "application/json" } });
        if (!res.ok) throw new Error("Ошибка загрузки");
        const data = await res.json();
        allRows = Array.isArray(data) ? data : [];
    } catch (e) {
        console.error(e);
        tbody.innerHTML = `<tr><td colspan="4">Ошибка загрузки данных.</td></tr>`;
        return;
    }

    totalCountEl.textContent = allRows.length.toString();

    // Инициализация обработчиков кликов на заголовки
    initSortableHeaders();

    applyAndRender();

    streetSearch.addEventListener("input", applyAndRender);

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
        let list = [...allRows];

        // Поиск по улице
        const q = streetSearch.value.trim().toLowerCase();
        if (q) {
            list = list.filter(r => (r.street || "").toLowerCase().includes(q));
        }

        // Сортировка
        if (currentSortField) {
            list.sort((a, b) => compareRows(a, b, currentSortField, currentSortDir));
        }

        foundCountEl.textContent = list.length.toString();
        renderTable(list);
    }

    function compareRows(a, b, field, dir) {
        const mul = dir === "asc" ? 1 : -1;

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
        if (!list.length) {
            tbody.innerHTML = `<tr><td colspan="4">Записей не найдено.</td></tr>`;
            return;
        }
        tbody.innerHTML = list.map(rowToHtml).join("");
    }

    function escapeHtml(str) {
        return String(str ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;");
    }

    function rowToHtml(r) {
        const id = r.id ?? "";
        const district = r.district ?? "";
        const street = r.street ?? "";
        const house = r.house ?? "";

        return `
            <tr>
                <td>${escapeHtml(id)}</td>
                <td>${escapeHtml(district)}</td>
                <td>${escapeHtml(street)}</td>
                <td>${escapeHtml(house)}</td>
            </tr>
        `;
    }
});
