document.addEventListener("DOMContentLoaded", async () => {
    const API_URL = "/districts/admin/index?format=json&deleted=1";

    const tbody = document.getElementById("districts-tbody");
    const sortFieldSelect = document.getElementById("sort-field");
    const sortDirSelect = document.getElementById("sort-dir");
    const districtFilter = document.getElementById("district-filter");
    const streetSearch = document.getElementById("street-search");

    const foundCountEl = document.getElementById("found-count");
    const totalCountEl = document.getElementById("total-count");

    let allRows = [];

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

    fillDistrictFilter(allRows);
    totalCountEl.textContent = allRows.length.toString();
    applyAndRender();

    [sortFieldSelect, sortDirSelect].forEach(el => {
        el.addEventListener("change", applyAndRender);
    });
    [districtFilter].forEach(el => {
        el.addEventListener("change", applyAndRender);
    });
    streetSearch.addEventListener("input", applyAndRender);

    function fillDistrictFilter(list) {
        const set = new Set();
        list.forEach(r => {
            if (r.district) set.add(r.district);
        });
        Array.from(set)
            .sort((a, b) => String(a).localeCompare(String(b), "ru"))
            .forEach(d => {
                const opt = document.createElement("option");
                opt.value = d;
                opt.textContent = d;
                districtFilter.appendChild(opt);
            });
    }

    function applyAndRender() {
        let list = [...allRows];

        const distVal = districtFilter.value;
        if (distVal) {
            list = list.filter(r => String(r.district) === String(distVal));
        }

        const q = streetSearch.value.trim().toLowerCase();
        if (q) {
            list = list.filter(r => (r.street || "").toLowerCase().includes(q));
        }

        const sortField = sortFieldSelect.value;
        const sortDir = sortDirSelect.value;
        list.sort((a, b) => compareRows(a, b, sortField, sortDir));

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
