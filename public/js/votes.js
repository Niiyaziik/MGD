document.addEventListener("DOMContentLoaded", async () => {
    const container = document.getElementById("district-bounds");

    // URL эндпоинта, который отдаёт JSON с проголосовавшими
    // ПОДМЕНИ под свой реальный роут, например `/votes?format=json`
    const API_URL = "/votes?format=json";

    // вспомогательная функция: создаёт базовую разметку таблицы
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

    // 1. Рисуем пустую таблицу (шапка есть даже если данных нет)
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
    } catch (e) {
        console.error("Ошибка при запросе голосов:", e);
        const tr = document.createElement("tr");
        const td = document.createElement("td");
        td.colSpan = 5;
        td.textContent = "Ошибка загрузки данных.";
        tbody.appendChild(tr);
        tr.appendChild(td);
        return;
    }

    // 2. Если данных нет — показываем сообщение, шапка уже есть
    if (data.length === 0) {
        const tr = document.createElement("tr");
        const td = document.createElement("td");
        td.colSpan = 5;
        td.textContent = "Нет данных о проголосовавших.";
        tbody.appendChild(tr);
        tr.appendChild(td);
        return;
    }

    // 3. Группируем по номеру округа
    // ОЖИДАЕМ ФОРМАТ КАЖДОЙ ЗАПИСИ:
    // {
    //   district: 1,
    //   voter_name: "ФИО",
    //   address: "Адрес",
    //   phone: "Телефон",
    //   candidate: "ФИО кандидата"
    // }
    const grouped = new Map();
    data.forEach(row => {
        const district = row.district ?? row.district_id; // подстраховка на имя поля
        if (district == null) return;

        if (!grouped.has(district)) {
            grouped.set(district, []);
        }
        grouped.get(district).push(row);
    });

    // 4. Рисуем строки: первая строка округа с rowspan, остальные — без
    grouped.forEach((rows, districtNumber) => {
        const rowSpan = rows.length;

        rows.forEach((row, index) => {
            const tr = document.createElement("tr");

            // первая колонка — только в первой строке округа
            if (index === 0) {
                const tdDistrict = document.createElement("td");
                tdDistrict.className = "votes-table__district";
                tdDistrict.rowSpan = rowSpan;
                tdDistrict.textContent = `Округ ${districtNumber}`;
                tr.appendChild(tdDistrict);
            }

            const tdName = document.createElement("td");
            tdName.textContent = row.voter_name || row.fio || "";

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
