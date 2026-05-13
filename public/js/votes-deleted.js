document.addEventListener("DOMContentLoaded", async () => {
    const container = document.getElementById("district-bounds");

    // 🔹 здесь другой URL — для удалённых
    const API_URL = "/votes/admin/deleted?format=json";

    function createEmptyTable() {
        container.innerHTML = "";

        const wrapper = document.createElement("div");
        wrapper.className = "votes-table-wrapper";

        const table = document.createElement("table");
        table.className = "votes-table";

        const thead = document.createElement("thead");
        thead.innerHTML = `
            <tr>
                <th>Номер округа</th>
                <th>ФИО избирателя</th>
                <th>Адрес</th>
                <th>Телефон</th>
                <th>Кандидат</th>
            </tr>
        `;

        const tbody = document.createElement("tbody");

        table.appendChild(thead);
        table.appendChild(tbody);
        wrapper.appendChild(table);
        container.appendChild(wrapper);

        return { table, tbody };
    }

    const { tbody } = createEmptyTable();

    let data = [];
    try {
        const res = await fetch(API_URL, {
            headers: { "Accept": "application/json" }
        });

        if (!res.ok) {
            throw new Error("Ошибка загрузки");
        }

        data = await res.json();
        if (!Array.isArray(data)) {
            data = [];
        }

        const votersInfo = document.createElement("div");
        votersInfo.className = "voters-count";

        const countSpan = document.createElement("span");
        countSpan.textContent = `Количество удалённых голосов: ${data.length}`;

        votersInfo.appendChild(countSpan);

        container.prepend(votersInfo);

    } catch (e) {
        console.error("Ошибка при запросе удалённых голосов:", e);
        const tr = document.createElement("tr");
        const td = document.createElement("td");
        td.colSpan = 5;
        td.textContent = "Ошибка загрузки данных.";
        tbody.appendChild(tr);
        tr.appendChild(td);
        return;
    }

    if (data.length === 0) {
        const tr = document.createElement("tr");
        const td = document.createElement("td");
        td.colSpan = 6;
        td.textContent = "Нет удалённых голосов.";
        tbody.appendChild(tr);
        tr.appendChild(td);
        return;
    }

    const grouped = new Map();
    data.forEach(row => {
        const district = row.district ?? row.district_id;
        if (district == null) return;

        if (!grouped.has(district)) {
            grouped.set(district, []);
        }
        grouped.get(district).push(row);
    });

    grouped.forEach((rows, districtNumber) => {
        const rowSpan = rows.length;

        rows.forEach((row, index) => {
            const tr = document.createElement("tr");

            if (index === 0) {
                const tdDistrict = document.createElement("td");
                tdDistrict.className = "votes-table__district";
                tdDistrict.rowSpan = rowSpan;
                tdDistrict.textContent = `Округ ${districtNumber}`;
                tr.appendChild(tdDistrict);
            }

            const tdName = document.createElement("td");
            tdName.textContent = row.user_name || row.fio || "";

            const tdAddress = document.createElement("td");
            tdAddress.textContent = row.address || "";

            const tdPhone = document.createElement("td");
            tdPhone.textContent = row.phone || "";

            const tdCandidate = document.createElement("td");
            tdCandidate.textContent = row.candidate || row.candidate_name || "";

            tr.appendChild(tdName);
            tr.appendChild(tdAddress);
            tr.appendChild(tdPhone);
            tr.appendChild(tdCandidate);

            tbody.appendChild(tr);
        });
    });
});
