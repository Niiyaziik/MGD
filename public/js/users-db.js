document.addEventListener("DOMContentLoaded", async () => {
    const API_URL = "/users/admin?format=json&deleted=0";

    const tbody = document.getElementById("users-tbody");
    const dateFromInput = document.getElementById("date-from");
    const dateToInput = document.getElementById("date-to");
    const surnameSearch = document.getElementById("surname-search");

    const foundCountEl = document.getElementById("found-count");
    const totalCountEl = document.getElementById("total-count");

    let allUsers = [];
    let currentSortField = null;
    let currentSortDir = null;

    try {
        const res = await fetch(API_URL, { headers: { "Accept": "application/json" } });
        if (!res.ok) throw new Error("Ошибка загрузки");
        const data = await res.json();
        allUsers = Array.isArray(data) ? data : [];
    } catch (e) {
        console.error(e);
        tbody.innerHTML = `<tr><td colspan="13">Ошибка загрузки данных.</td></tr>`;
        return;
    }

    totalCountEl.textContent = allUsers.length.toString();

    // Инициализация обработчиков кликов на заголовки
    initSortableHeaders();

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
        let list = [...allUsers];

        // Фильтр по дате регистрации
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

        foundCountEl.textContent = list.length.toString();
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
            tbody.innerHTML = `<tr><td colspan="13">Записей не найдено.</td></tr>`;
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

        const deleteUrl = `/users/admin/delete?id=${encodeURIComponent(id)}`;

        return `
            <tr data-id="${escapeHtml(id)}">
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
                <td class="users-table__actions">
                    <button class="icon-btn edit-btn" data-id="${escapeHtml(id)}" title="Редактировать">        
                        <img src="/assets/icons/update.png" class="icon-png" alt="Редактировать">
                    </button>
                </td>
                <td class="users-table__actions">
                    <button class="icon-btn delete-btn" data-id="${escapeHtml(id)}" title="Удалить">
                        <img src="/assets/icons/delete.png" class="icon-png" alt="Удалить">
                    </button>
                </td>
            </tr>
        `;
    }

    function startEditRow(tr) {
        tr.classList.add("editing");

        const id = tr.dataset.id || tr.querySelector("td").textContent.trim();
        const user = allUsers.find(u => String(u.id) === String(id));

        const regDate = user.registration_date ?? "";
        const authMethod = user.auth_method ?? "";
        const surname = user.surname ?? "";
        const name = user.name ?? "";
        const patronymic = user.patronymic ?? "";
        const phone = user.phone ?? "";
        const vkLink = user.link_vk ?? "";
        const street = user.street ?? "";
        const house = user.house ?? "";
        const district = user.district ?? "";

        tr.innerHTML = `
        <td>${escapeHtml(id)}</td>
        <td>${escapeHtml(regDate)}</td>

        <td>
            <select class="edit-select edit-auth">
                ${createAuthSelectOptions(authMethod)}
            </select>
        </td>

        <td><input type="text" value="${escapeHtml(surname)}" class="edit-input edit-surname"></td>
        <td><input type="text" value="${escapeHtml(name)}" class="edit-input edit-name"></td>
        <td><input type="text" value="${escapeHtml(patronymic)}" class="edit-input edit-patronymic"></td>
        <td><input type="text" value="${escapeHtml(phone)}" class="edit-input edit-phone"></td>
        <td><input type="text" value="${escapeHtml(vkLink)}" class="edit-input edit-vk"></td>
        <td><input type="text" value="${escapeHtml(street)}" class="edit-input edit-street"></td>
        <td><input type="text" value="${escapeHtml(house)}" class="edit-input edit-house"></td>
        <td><input type="text" value="${escapeHtml(district)}" class="edit-input edit-district"></td>

        <td>
            <button class="save-btn" data-id="${id}" title="Сохранить">
                <img src="/assets/icons/save.png" class="icon-png" alt="Сохранить">
            </button>
        </td>
        <td>
            <button class="cancel-btn" title="Отменить">
                <img src="/assets/icons/cancel.png" class="icon-png" alt="Отменить">
            </button>
        </td>
    `;
    }

    function createAuthSelectOptions(selected) {
        const methods = ["Телефон", "ВК", "МАКС"];
        return methods
            .map(m => `<option value="${m}" ${m === selected ? 'selected' : ''}>${m}</option>`)
            .join("");
    }

    async function saveRow(tr, id) {
        const payload = {
            id: id,
            auth_method: tr.querySelector(".edit-auth")?.value ?? "",
            surname: tr.querySelector(".edit-surname")?.value ?? "",
            name: tr.querySelector(".edit-name")?.value ?? "",
            patronymic: tr.querySelector(".edit-patronymic")?.value ?? "",
            phone: tr.querySelector(".edit-phone")?.value ?? "",
            link_vk: tr.querySelector(".edit-vk")?.value ?? "",
            street_id: tr.querySelector(".edit-street")?.value ?? "",
            house_id: tr.querySelector(".edit-house")?.value ?? "",
            district_id: tr.querySelector(".edit-district")?.value ?? "",
        };

        const res = await fetch("/users/admin/update", {
            method: "PUT",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify(payload)
        });

        const out = await res.json().catch(() => ({}));

        if (!res.ok || out.ok === false) {
            showMessage("Ошибка сохранения: " + (out.error || "Неизвестная ошибка"), "Ошибка");
            return;
        }

        const idx = allUsers.findIndex(u => String(u.id) === String(id));
        if (idx !== -1) {
            allUsers[idx] = {
                ...allUsers[idx],
                auth_method: payload.auth_method,
                surname: payload.surname,
                name: payload.name,
                patronymic: payload.patronymic,
                phone: payload.phone,
                link_vk: payload.link_vk,
                street: payload.street_id,
                house: payload.house_id,
                district: payload.district_id,
            };
        }

        applyAndRender();
    }

    async function deleteUser(id) {
        const res = await fetch("/users/admin/deleted", {
            method: "DELETE",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify({ id: id })
        });

        const out = await res.json().catch(() => ({}));

        if (!res.ok || out.ok === false) {
            showMessage("Ошибка удаления: " + (out.error || "Неизвестная ошибка"), "Ошибка");
            return;
        }

        allUsers = allUsers.filter(u => String(u.id) !== String(id));
        const totalCountEl = document.getElementById("total-count");
        if (totalCountEl) {
            totalCountEl.textContent = allUsers.length.toString();
        }
        applyAndRender();
    }


    document.addEventListener("click", async (e) => {
        const editBtn = e.target.closest(".edit-btn");
        const saveBtn = e.target.closest(".save-btn");
        const cancelBtn = e.target.closest(".cancel-btn");
        const deleteBtn = e.target.closest(".delete-btn");

        if (editBtn) {
            const tr = editBtn.closest("tr");
            if (tr) startEditRow(tr);
        }

        if (saveBtn) {
            const tr = saveBtn.closest("tr");
            const id = saveBtn.dataset.id;
            if (tr && id) {
                await saveRow(tr, id);
            }
        }

        if (cancelBtn) {
            applyAndRender();
        }

        if (deleteBtn) {
            const id = deleteBtn.dataset.id;
            if (id && confirm("Удалить пользователя?")) {
                await deleteUser(id);
            }
        }
    });
});
document.addEventListener("DOMContentLoaded", () => {
    const downloadBtn = document.querySelector(".votes-download-btn");
    if (downloadBtn) {
        downloadBtn.addEventListener("click", () => {
            window.location.href = "/users/admin/export";
        });
    }
});
