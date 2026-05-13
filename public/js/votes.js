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
                <th class="sortable-header" data-field="user_name">
                    <span>ФИО избирателя</span>
                    <img src="/assets/icons/filter.svg" class="filter-icon" alt="Фильтр">
                    <span class="sort-indicator"></span>
                </th>
                <th class="sortable-header" data-field="address">
                    <span>Адрес</span>
                    <img src="/assets/icons/filter.svg" class="filter-icon" alt="Фильтр">
                    <span class="sort-indicator"></span>
                </th>
                <th class="sortable-header" data-field="phone">
                    <span>Телефон</span>
                    <img src="/assets/icons/filter.svg" class="filter-icon" alt="Фильтр">
                    <span class="sort-indicator"></span>
                </th>
                <th class="sortable-header" data-field="candidate">
                    <span>Кандидат</span>
                    <img src="/assets/icons/filter.svg" class="filter-icon" alt="Фильтр">
                    <span class="sort-indicator"></span>
                </th>
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
    let currentSortField = null;
    let currentSortDir = null;
    let columnFilters = {}; // Фильтры для каждого столбца
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
        const deletedBtn = document.createElement("button");
        deletedBtn.type = "button";
        deletedBtn.className = "votes-deleted-btn";
        deletedBtn.textContent = "Удалённые";
        deletedBtn.addEventListener("click", () => {
            window.location.href = "/votes/admin/deleted";
        });
        const downloadBtn = document.createElement("button");
        downloadBtn.type = "button";
        downloadBtn.className = "votes-download-btn";
        downloadBtn.textContent = "Excel";
        downloadBtn.style.marginRight = "10px";
        downloadBtn.style.backgroundColor = "#28a745"; // Зелёный
        downloadBtn.style.color = "#fff";
        downloadBtn.addEventListener("click", () => {
            window.location.href = "/votes/admin/export";
        });

        // Кнопка "Образец" - скачивает шаблон Excel
        const templateBtn = document.createElement("button");
        templateBtn.type = "button";
        templateBtn.className = "votes-download-btn";
        templateBtn.textContent = "Образец";
        templateBtn.style.marginRight = "10px";
        templateBtn.addEventListener("click", () => {
            window.location.href = "/votes/admin/export-template";
        });

        // Кнопка "Внести данные" - загружает и импортирует Excel
        const importBtn = document.createElement("button");
        importBtn.type = "button";
        importBtn.className = "votes-download-btn";
        importBtn.textContent = "Внести данные";
        importBtn.style.marginRight = "10px";
        importBtn.addEventListener("click", () => {
            const fileInput = document.createElement("input");
            fileInput.type = "file";
            fileInput.accept = ".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";
            fileInput.addEventListener("change", async (e) => {
                const file = e.target.files[0];
                if (!file) return;

                if (!file.name.toLowerCase().endsWith('.xlsx')) {
                    showMessage("Пожалуйста, выберите файл в формате .xlsx", "Ошибка");
                    return;
                }

                const formData = new FormData();
                formData.append('file', file);

                try {
                    importBtn.disabled = true;
                    importBtn.textContent = "Загрузка...";

                    const res = await fetch("/votes/admin/import", {
                        method: "POST",
                        body: formData
                    });

                    const result = await res.json().catch(() => ({}));

                    if (!res.ok || result.ok === false) {
                        // Если есть массив ошибок, показываем их через модалку ошибок
                        if (result.errors && Array.isArray(result.errors) && result.errors.length > 0) {
                            showErrors(result.errors, "Ошибки импорта");
                        } else {
                            showMessage("Ошибка импорта: " + (result.error || "Неизвестная ошибка"), "Ошибка");
                        }
                        importBtn.disabled = false;
                        importBtn.textContent = "Внести данные";
                        return;
                    }

                    // Если есть ошибки, но импорт частично успешен, показываем и успех, и ошибки
                    if (result.errors && Array.isArray(result.errors) && result.errors.length > 0) {
                        showMessage(
                            `Успешно импортировано ${result.imported || 0} записей. Есть ошибки при импорте некоторых строк.`,
                            "Импорт завершен с ошибками",
                            () => {
                                // После закрытия сообщения об успехе показываем ошибки
                                setTimeout(() => {
                                    showErrors(result.errors, "Ошибки импорта");
                                }, 300);
                            }
                        );
                    } else {
                        showMessage(`Успешно импортировано ${result.imported || 0} записей`, "Успех");
                    }

                    // Перезагружаем данные только если нет ошибок или если пользователь закрыл модалку ошибок
                    if (!result.errors || result.errors.length === 0) {
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    }
                } catch (err) {
                    console.error("Ошибка импорта:", err);
                    showMessage("Не удалось загрузить файл. Попробуйте позже.", "Ошибка");
                    importBtn.disabled = false;
                    importBtn.textContent = "Внести данные";
                }
            });
            fileInput.click();
        });

        // Кнопка "Скачать PDF"
        const pdfBtn = document.createElement("button");
        pdfBtn.type = "button";
        pdfBtn.className = "votes-download-btn";
        pdfBtn.textContent = "PDF";
        pdfBtn.style.marginRight = "10px";
        pdfBtn.style.backgroundColor = "#dc3545"; // Красный
        pdfBtn.style.color = "#fff";
        pdfBtn.addEventListener("click", () => {
            window.location.href = "/votes/admin/export-pdf";
        });

        // Кнопка "Скачать CSV"
        const csvBtn = document.createElement("button");
        csvBtn.type = "button";
        csvBtn.className = "votes-download-btn";
        csvBtn.textContent = "CSV";
        csvBtn.style.backgroundColor = "#28a745"; // Зелёный
        csvBtn.style.color = "#fff";
        csvBtn.addEventListener("click", () => {
            window.location.href = "/votes/admin/export-csv";
        });

        const actions = document.createElement("div");
        actions.className = "voters-actions";
        actions.appendChild(templateBtn);
        actions.appendChild(importBtn);
        actions.appendChild(downloadBtn);
        actions.appendChild(pdfBtn);
        actions.appendChild(csvBtn);
        votersInfo.appendChild(countSpan);
        votersInfo.appendChild(actions);
        container.prepend(votersInfo);

        // Инициализация обработчиков кликов на заголовки
        initSortableHeaders();
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
    function initSortableHeaders() {
        const headers = document.querySelectorAll(".votes-table .sortable-header");
        headers.forEach(header => {
            header.style.cursor = "pointer";

            // Клик на заголовок - сортировка
            header.addEventListener("click", (e) => {
                // Если клик был по input фильтра, не сортируем
                if (e.target.tagName === "INPUT") return;
                const field = header.dataset.field;
                handleHeaderClick(field, header);
            });

            // Клик на иконку фильтра - показываем/скрываем input
            const filterIcon = header.querySelector(".filter-icon");
            if (filterIcon) {
                filterIcon.addEventListener("click", (e) => {
                    e.stopPropagation();
                    toggleFilterInput(header);
                });
            }
        });
    }

    function toggleFilterInput(header) {
        const field = header.dataset.field;
        let filterInput = header.querySelector(".filter-input");

        if (filterInput) {
            // Если input уже есть, удаляем его
            filterInput.remove();
            delete columnFilters[field];
            renderTable();
        } else {
            // Создаем input для фильтрации
            filterInput = document.createElement("input");
            filterInput.type = "text";
            filterInput.className = "filter-input";
            filterInput.placeholder = "Фильтр...";
            filterInput.value = columnFilters[field] || "";
            filterInput.style.cssText = "width: 100px; padding: 2px 4px; margin-left: 4px; font-size: 12px; border: 1px solid #ccc; border-radius: 3px;";

            filterInput.addEventListener("input", (e) => {
                const value = e.target.value.trim();
                if (value) {
                    columnFilters[field] = value;
                } else {
                    delete columnFilters[field];
                }
                renderTable();
            });

            filterInput.addEventListener("click", (e) => {
                e.stopPropagation();
            });

            header.appendChild(filterInput);
            filterInput.focus();
        }
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

        renderTable();
    }

    function updateSortIndicators() {
        const headers = document.querySelectorAll(".votes-table .sortable-header");
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

        // Применяем фильтры и сортировку внутри каждого округа
        grouped.forEach((rows, districtNumber) => {
            // Фильтрация внутри округа
            let filteredRows = rows.filter(row => {
                // Применяем фильтры для каждого столбца
                for (const [field, filterValue] of Object.entries(columnFilters)) {
                    if (filterValue && filterValue.trim() !== "") {
                        const rowValue = String(row[field] || "").toLowerCase();
                        if (!rowValue.includes(filterValue.toLowerCase())) {
                            return false;
                        }
                    }
                }
                return true;
            });

            // Сортировка внутри округа
            if (currentSortField && filteredRows.length > 0) {
                filteredRows.sort((a, b) => {
                    const va = String(a[currentSortField] || "").toLowerCase();
                    const vb = String(b[currentSortField] || "").toLowerCase();
                    const mul = currentSortDir === "asc" ? 1 : -1;
                    if (va < vb) return -1 * mul;
                    if (va > vb) return 1 * mul;
                    return 0;
                });
            }

            const rowSpan = filteredRows.length;
            filteredRows.forEach((row, index) => {
                const tr = document.createElement("tr");
                tr.dataset.voteId = row.id != null ? String(row.id) : "";
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
        const hasDistrict = tds.length === 6;
        let idx = 0;
        if (hasDistrict) {
            idx++;
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
        tdName.innerHTML = `<input type="text" class="edit-input edit-voter-name" value="${escapeHtml(voterName)}">`;
        tdAddress.innerHTML = `<input type="text" class="edit-input edit-voter-address" value="${escapeHtml(addrVal)}">`;
        tdPhone.innerHTML = `<input type="text" class="edit-input edit-voter-phone" value="${escapeHtml(phoneVal)}">`;
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
            showMessage("Не удалось определить ID голоса", "Ошибка");
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
                    user_name: voterName,
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
                showMessage("Ошибка сохранения: " + (out.error || "Неизвестная ошибка"), "Ошибка");
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
            showMessage("Не удалось сохранить изменения. Попробуйте позже.", "Ошибка");
        }
    }
    async function deleteRow(tr) {
        const voteId = tr.dataset.voteId || "";
        if (!voteId) {
            showMessage("Не удалось определить ID голоса", "Ошибка");
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
                showMessage("Ошибка удаления: " + (out.error || "Неизвестная ошибка"), "Ошибка");
                return;
            }
            data = data.filter(v => String(v.id) !== String(voteId));
            renderTable();
        } catch (err) {
            console.error("Ошибка /votes/admin/delete:", err);
            showMessage("Не удалось удалить голос. Попробуйте позже.", "Ошибка");
        }
    }
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
