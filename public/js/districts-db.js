document.addEventListener("DOMContentLoaded", async () => {
    const API_URL = "/districts/admin/index?format=json&deleted=0";

    const tbody = document.getElementById("districts-tbody");
    const streetSearch = document.getElementById("street-search");

    const foundCountEl = document.getElementById("found-count");
    const totalCountEl = document.getElementById("total-count");
    const checkBtn = document.getElementById("check-duplicates-btn");
    const duplicatesResult = document.getElementById("duplicates-result");

    let allRows = [];
    let currentSortField = null;
    let currentSortDir = null;

    // загрузка
    try {
        const res = await fetch(API_URL, { headers: { "Accept": "application/json" } });
        if (!res.ok) throw new Error("Ошибка загрузки");
        const data = await res.json();
        allRows = Array.isArray(data) ? data : [];
    } catch (e) {
        console.error(e);
        tbody.innerHTML = `<tr><td colspan="5">Ошибка загрузки данных.</td></tr>`;
        return;
    }

    totalCountEl.textContent = allRows.length.toString();

    // Инициализация обработчиков кликов на заголовки
    initSortableHeaders();

    applyAndRender();

    // события фильтров
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
        const va = (a[field] ?? "").toString().toLowerCase();
        const vb = (b[field] ?? "").toString().toLowerCase();
        if (va < vb) return -1 * mul;
        if (va > vb) return 1 * mul;
        return 0;
    }

    function renderTable(list) {
        if (!list.length) {
            tbody.innerHTML = `<tr><td colspan="5">Записей не найдено.</td></tr>`;
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

    // ❗❗❗ — РЕДАКТИРОВАНИЯ НЕТ — оставлена только кнопка удаления
    function rowToHtml(r) {
        const id = r.id ?? "";
        const district = r.district ?? "";
        const street = r.street ?? "";
        const house = r.house ?? "";

        return `
            <tr data-id="${escapeHtml(id)}">
                <td>${escapeHtml(id)}</td>
                <td>${escapeHtml(district)}</td>
                <td>${escapeHtml(street)}</td>
                <td>${escapeHtml(house)}</td>
                <td class="users-table__actions">
                    <button class="icon-btn delete-district-btn" data-id="${escapeHtml(id)}" title="Удалить">
                        <img src="/assets/icons/delete.png" class="icon-png" alt="Удалить">
                    </button>
                </td>
            </tr>
        `;
    }

    // обработчик кликов — УБРАН editBtn
    document.addEventListener("click", async (e) => {
        const delBtn = e.target.closest(".delete-district-btn");

        if (delBtn) {
            const id = delBtn.dataset.id;
            if (!id) return;
            if (!confirm("Удалить адрес?")) return;

            const res = await fetch("/districts/admin/delete", {
                method: "DELETE",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json"
                },
                body: JSON.stringify({ id })
            });

            const out = await res.json().catch(() => ({}));

            if (!res.ok || out.ok === false) {
                alert("Ошибка удаления: " + (out.error || "Неизвестная ошибка"));
                return;
            }

            allRows = allRows.filter(r => String(r.id) !== String(id));
            totalCountEl.textContent = allRows.length.toString();
            applyAndRender();
        }
    });

    // проверка дублей
    if (checkBtn) {
        checkBtn.addEventListener("click", async () => {
            duplicatesResult.textContent = "Проверка базы...";
            try {
                const res = await fetch("/districts/admin/check-duplicates", {
                    headers: { "Accept": "application/json" }
                });
                const out = await res.json();
                if (!res.ok || out.ok === false) {
                    duplicatesResult.textContent = "Ошибка проверки: " + (out.error || "Неизвестная ошибка");
                    return;
                }

                const duplicates = out.data || [];
                if (!duplicates.length) {
                    duplicatesResult.style.color = "#1a7a0a";
                    duplicatesResult.textContent = "Повторов не найдено. База в порядке.";
                    return;
                }

                duplicatesResult.style.color = "#a30000";
                const lines = duplicates.map(d => {
                    return `Строка базы <${d.row_ids}>: улица "${d.street}", дом "${d.house}" встречается в округах: ${d.districts}`;
                });
                duplicatesResult.innerHTML = lines.join("<br>");
            } catch (e) {
                console.error(e);
                duplicatesResult.textContent = "Ошибка проверки базы.";
            }
        });
    }
});
