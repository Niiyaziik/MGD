document.addEventListener("DOMContentLoaded", async () => {
    const container = document.getElementById("district-bounds");
    const API_URL = "/votes/admin?format=json";

    function escapeHtml(str) {
        return String(str ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;");
    }

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
                <th>Действия</th>
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
    let countSpan = null;

    // ===== ЗАГРУЗКА ДАННЫХ =====
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

        countSpan = document.createElement("span");
        countSpan.textContent = `Количество проголосовавших: ${data.length}`;

        // Кнопка "Удалённые"
        const deletedBtn = document.createElement("button");
        deletedBtn.type = "button";
        deletedBtn.className = "votes-deleted-btn";
        deletedBtn.textContent = "Удалённые";
        deletedBtn.addEventListener("click", () => {
            window.location.href = "/votes/admin/deleted";
        });

        // Кнопка "Скачать Excel"
        const downloadBtn = document.createElement("button");
        downloadBtn.type = "button";
        downloadBtn.className = "votes-download-btn";
        downloadBtn.textContent = "Скачать Excel";
        downloadBtn.addEventListener("click", () => {
            window.location.href = "/votes/admin/export";
        });

        const actions = document.createElement("div");
        actions.className = "voters-actions";
        actions.appendChild(deletedBtn);
        actions.appendChild(downloadBtn);

        votersInfo.appendChild(countSpan);
        votersInfo.appendChild(actions);

        container.prepend(votersInfo);

    } catch (e) {
        console.error("Ошибка при запросе голосов:", e);
        const tr = document.createElement("tr");
        const td = document.createElement("td");
        td.colSpan = 6;
        td.textContent = "Ошибка загрузки данных.";
        tr.appendChild(td);
        tbody.appendChild(tr);
        return;
    }

    if (data.length === 0) {
        const tr = document.createElement("tr");
        const td = document.createElement("td");
        td.colSpan = 6;
        td.textContent = "Нет данных о проголосовавших.";
        tr.appendChild(td);
        tbody.appendChild(tr);
        return;
    }

    // ===== РЕНДЕР ТАБЛИЦЫ (группировка по округу) =====

    function renderTable() {
        tbody.innerHTML = "";

        if (countSpan) {
            countSpan.textContent = `Количество проголосовавших: ${data.length}`;
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
                tr.dataset.voteId = row.id != null ? String(row.id) : "";

                // первая колонка — только в первой строке округа
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

                const tdActions = document.createElement("td");
                tdActions.className = "votes-table__actions";
                tdActions.innerHTML = `
                    <button class="icon-btn vote-edit-btn" title="Редактировать">
                        <img src="/assets/icons/update.png" class="icon-png" alt="Редактировать">
                    </button>
                    <button class="icon-btn vote-delete-btn" title="Удалить">
                        <img src="/assets/icons/delete.png" class="icon-png" alt="Удалить">
                    </button>
                `;

                tr.appendChild(tdName);
                tr.appendChild(tdAddress);
                tr.appendChild(tdPhone);
                tr.appendChild(tdCandidate);
                tr.appendChild(tdActions);

                tbody.appendChild(tr);
            });
        });
    }

    renderTable();

    // ====== РЕДАКТИРОВАНИЕ СТРОКИ ГОЛОСА ======

    function startEditRow(tr) {
        if (!tr) return;
        tr.classList.add("editing");

        const voteId = tr.dataset.voteId || "";
        const vote = data.find(v => String(v.id) === String(voteId));
        if (!vote) {
            console.warn("Не найден голос с id", voteId);
            return;
        }

        const tds = tr.querySelectorAll("td");
        if (!tds.length) return;

        // Структура:
        // с ячейкой округа: [district, name, address, phone, candidate, actions] => length = 6
        // без ячейки округа: [name, address, phone, candidate, actions]          => length = 5

        const hasDistrict = tds.length === 6;
        let idx = 0;

        if (hasDistrict) {
            idx++; // пропускаем кол-ку округа (rowspan)
        }

        const tdName = tds[idx++];
        const tdAddress = tds[idx++];
        const tdPhone = tds[idx++];
        const tdCandidate = tds[idx++];
        const tdActions = tds[idx++];

        const voterName = vote.user_name || vote.fio || "";
        const addrVal = vote.address || "";
        const phoneVal = vote.phone || "";
        const candidateVal = vote.candidate || vote.candidate_name || tdCandidate.textContent || "";

        tdName.innerHTML = `
            <input type="text" class="edit-input edit-voter-name" value="${escapeHtml(voterName)}">
        `;
        tdAddress.innerHTML = `
            <input type="text" class="edit-input edit-voter-address" value="${escapeHtml(addrVal)}">
        `;
        tdPhone.innerHTML = `
            <input type="text" class="edit-input edit-voter-phone" value="${escapeHtml(phoneVal)}">
        `;
        // Кандидата не редактируем
        tdCandidate.textContent = candidateVal;

        tdActions.innerHTML = `
            <button class="icon-btn vote-save-btn" title="Сохранить">
                <img src="/assets/icons/save.png" class="icon-png" alt="Сохранить">
            </button>
            <button class="icon-btn vote-cancel-btn" title="Отменить">
                <img src="/assets/icons/cancel.png" class="icon-png" alt="Отменить">
            </button>
        `;
    }

    async function saveRow(tr) {
        const voteId = tr.dataset.voteId || "";
        if (!voteId) {
            alert("Не удалось определить ID голоса");
            return;
        }

        const nameInput = tr.querySelector(".edit-voter-name");
        const addrInput = tr.querySelector(".edit-voter-address");
        const phoneInput = tr.querySelector(".edit-voter-phone");

        const voterName = nameInput ? nameInput.value.trim() : "";
        const address = addrInput ? addrInput.value.trim() : "";
        const phone = phoneInput ? phoneInput.value.trim() : "";

        let out = {};
        let res;
        try {
            res = await fetch("/votes/admin/update", {
                method: "PUT",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json"
                },
                body: JSON.stringify({
                    id: voteId,
                    user_name: voterName,   // на всякий случай дублируем
                    voter_name: voterName,
                    address,
                    phone
                })
            });

            try {
                out = await res.json();
            } catch (e) {
                out = {};
            }

            if (!res.ok || out.ok === false) {
                alert("Ошибка сохранения: " + (out.error || "Неизвестная ошибка"));
                return;
            }

            const idx = data.findIndex(v => String(v.id) === String(voteId));
            if (idx !== -1) {
                data[idx] = {
                    ...data[idx],
                    user_name: voterName,
                    address: address,
                    phone: phone
                };
            }

            renderTable();
        } catch (err) {
            console.error("Ошибка /votes/admin/update:", err);
            alert("Не удалось сохранить изменения. Попробуйте позже.");
        }
    }

    async function deleteRow(tr) {
        const voteId = tr.dataset.voteId || "";
        if (!voteId) {
            alert("Не удалось определить ID голоса");
            return;
        }

        if (!confirm("Удалить этот голос?")) {
            return;
        }

        let out = {};
        let res;
        try {
            res = await fetch("/votes/admin/delete", {
                method: "DELETE",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json"
                },
                body: JSON.stringify({ id: voteId })
            });

            try {
                out = await res.json();
            } catch (e) {
                out = {};
            }

            if (!res.ok || out.ok === false) {
                alert("Ошибка удаления: " + (out.error || "Неизвестная ошибка"));
                return;
            }

            data = data.filter(v => String(v.id) !== String(voteId));
            renderTable();
        } catch (err) {
            console.error("Ошибка /votes/admin/delete:", err);
            alert("Не удалось удалить голос. Попробуйте позже.");
        }
    }

    // ====== ОБРАБОТКА КЛИКОВ ПО КНОПКАМ В ТАБЛИЦЕ ======
    document.addEventListener("click", async (e) => {
        const editBtn = e.target.closest(".vote-edit-btn");
        const saveBtn = e.target.closest(".vote-save-btn");
        const cancelBtn = e.target.closest(".vote-cancel-btn");
        const deleteBtn = e.target.closest(".vote-delete-btn");

        if (editBtn) {
            const tr = editBtn.closest("tr");
            if (tr) startEditRow(tr);
        }

        if (saveBtn) {
            const tr = saveBtn.closest("tr");
            if (tr) await saveRow(tr);
        }

        if (cancelBtn) {
            renderTable();
        }

        if (deleteBtn) {
            const tr = deleteBtn.closest("tr");
            if (tr) await deleteRow(tr);
        }
    });
});
