document.addEventListener("DOMContentLoaded", async () => {
    const titleEl = document.getElementById("district-title");
    const boundsEl = document.getElementById("district-bounds");

    // ---------- 1. Определяем id округа из query или из /district/admin/{id} ----------
    const params = new URLSearchParams(window.location.search);
    let district = Number(params.get("district"));

    if (!district) {
        const path = window.location.pathname; // например: "/district/admin/1"
        const match = path.match(/\/district\/admin\/(\d+)/);
        if (match) {
            district = Number(match[1]);
        }
    }

    if (!district || Number.isNaN(district)) {
        titleEl.textContent = "Округ не найден";
        boundsEl.textContent = "Некорректный параметр id.";
        return;
    }

    titleEl.textContent = `Округ ${district}`;

    // ---------- 2. Загружаем данные ----------
    let rawData = [];
    try {
        const res = await fetch(`/district/admin/${district}?format=json`, {
            headers: { "Accept": "application/json" }
        });
        if (!res.ok) throw new Error("Ошибка загрузки");
        rawData = await res.json();
        if (!Array.isArray(rawData)) {
            rawData = [];
        }
    } catch (err) {
        console.error(err);
        boundsEl.textContent = "Ошибка загрузки данных.";
        return;
    }

    // ---------- 3. Строим модель: группируем по street ----------
    // model = [ { street: 'Ленина', houses: ['10', '12', ...] }, ... ]
    const modelMap = new Map();
    rawData.forEach(row => {
        const street = (row.street || "").trim();
        const house = (row.house || "").trim();
        if (!street) return;

        if (!modelMap.has(street)) {
            modelMap.set(street, { street, houses: [] });
        }
        if (house) {
            modelMap.get(street).houses.push(house);
        }
    });
    let model = Array.from(modelMap.values());

    // Если улиц вообще нет — начинаем с одной пустой строки
    // if (model.length === 0) {
    //     model.push({ street: "", houses: [] });
    // }

    const MAX_HOUSES_PER_ROW = 20;

    // ---------- 3.5. API-методы для улиц/домов ----------

    async function createStreet(street) {
        if (!street) return;
        try {
            await fetch(`/district/${district}/streets`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ street })
            });
        } catch (e) {
            console.error("createStreet error", e);
        }
    }

    async function updateStreet(oldStreet, newStreet) {
        if (!oldStreet || !newStreet || oldStreet === newStreet) return;
        try {
            await fetch(`/district/${district}/streets`, {
                method: "PUT", // при необходимости поменяй метод / URL
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ old_street: oldStreet, new_street: newStreet })
            });
        } catch (e) {
            console.error("updateStreet error", e);
        }
    }

    async function createHouse(street, house) {
        if (!street || !house) return;
        try {
            await fetch(`/district/${district}/houses`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ street, house })
            });
        } catch (e) {
            console.error("createHouse error", e);
        }
    }

    async function updateHouse(street, oldHouse, newHouse) {
        if (!street || !oldHouse || !newHouse || oldHouse === newHouse) return;
        try {
            await fetch(`/district/${district}/houses`, {
                method: "PUT", // при необходимости поменяй
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ street, old_house: oldHouse, new_house: newHouse })
            });
        } catch (e) {
            console.error("updateHouse error", e);
        }
    }

    async function deleteHouse(street, house) {
        if (!street || !house) return;
        try {
            await fetch(`/district/${district}/houses`, {
                method: "DELETE", // при необходимости поменяй
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ street, house })
            });
        } catch (e) {
            console.error("deleteHouse error", e);
        }
    }

    // ---------- helper: подрезаем хвостовые пустые дома ----------
    function trimStreetHouses(streetIndex) {
        const houses = model[streetIndex].houses;
        while (houses.length > 0) {
            const last = houses[houses.length - 1];
            if (last && last.trim && last.trim() !== "") break;
            houses.pop();
        }
    }

    // ---------- 4. Рендер таблицы ----------
    function renderTable(scrollToLast = false) {
        boundsEl.innerHTML = "";

        const wrapper = document.createElement("div");
        wrapper.className = "address-table-wrapper";

        // --- Шапка с кнопкой "+ Добавить адрес" ---
        const header = document.createElement("div");
        header.className = "address-table-header";

        const addBtn = document.createElement("button");
        addBtn.type = "button";
        addBtn.id = "add-address-btn";
        addBtn.textContent = "+ Добавить адрес";
        header.appendChild(addBtn);

        wrapper.appendChild(header);

        // --- Область прокрутки и таблица ---
        const scroll = document.createElement("div");
        scroll.className = "address-table-scroll";

        const table = document.createElement("table");
        table.className = "address-table";

        // 21 колонка
        const colgroup = document.createElement("colgroup");
        const streetCol = document.createElement("col");
        streetCol.className = "street-col";
        colgroup.appendChild(streetCol);
        for (let i = 0; i < MAX_HOUSES_PER_ROW; i++) {
            const c = document.createElement("col");
            c.className = "house-col";
            colgroup.appendChild(c);
        }
        table.appendChild(colgroup);

        const tbody = document.createElement("tbody");

        // --- Строки по улицам ---
        model.forEach((streetItem, streetIndex) => {
            const houses = streetItem.houses;
            const totalHouses = houses.length;

            // вычисляем, сколько строк нужно для этой улицы
            const rowsCount = Math.max(1, Math.ceil((totalHouses + 1) / MAX_HOUSES_PER_ROW));
            const totalSlots = rowsCount * MAX_HOUSES_PER_ROW; // слоты под дома (вкл. пустые)

            for (let rowIndex = 0; rowIndex < rowsCount; rowIndex++) {
                const tr = document.createElement("tr");

                // первая строка для улицы — рисуем ячейку street с rowspan
                if (rowIndex === 0) {
                    const streetTd = document.createElement("td");
                    streetTd.className = "street-cell";
                    streetTd.rowSpan = rowsCount;

                    streetTd.style.verticalAlign = "middle";
                    streetTd.style.textAlign = "center";

                    const streetInput = document.createElement("input");
                    streetInput.type = "text";
                    streetInput.className = "address-input street-input";
                    streetInput.value = streetItem.street;
                    streetInput.placeholder = "Улица";

                    streetInput.dataset.streetIndex = String(streetIndex);

                    streetTd.appendChild(streetInput);
                    streetTd.addEventListener("click", () => {
                        streetInput.focus();
                    });
                    tr.appendChild(streetTd);

                    // обработчик сохранения улицы
                    attachStreetHandlers(streetInput);
                }

                // ячейки домов
                for (let colIndex = 0; colIndex < MAX_HOUSES_PER_ROW; colIndex++) {
                    const houseTd = document.createElement("td");

                    const globalIndex = rowIndex * MAX_HOUSES_PER_ROW + colIndex;
                    const value = globalIndex < totalHouses ? (houses[globalIndex] || "") : "";

                    const houseInput = document.createElement("input");
                    houseInput.type = "text";
                    houseInput.className = "address-input house-input";// около 5 символов
                    houseInput.value = value;
                    houseInput.placeholder = "";

                    houseInput.dataset.streetIndex = String(streetIndex);
                    houseInput.dataset.houseIndex = String(globalIndex);

                    houseTd.appendChild(houseInput);
                    houseTd.addEventListener("click", () => {
                        houseInput.focus();
                    });
                    tr.appendChild(houseTd);

                    attachHouseHandlers(houseInput);
                }

                tbody.appendChild(tr);
            }

            // синяя разделительная линия между улицами
            if (streetIndex < model.length - 1) {
                const sepTr = document.createElement("tr");
                sepTr.className = "street-separator";
                const sepTd = document.createElement("td");
                sepTd.colSpan = MAX_HOUSES_PER_ROW + 1;
                sepTr.appendChild(sepTd);
                tbody.appendChild(sepTr);
            }
        });

        table.appendChild(tbody);
        scroll.appendChild(table);
        wrapper.appendChild(scroll);
        boundsEl.appendChild(wrapper);

        function canAddNewStreet() {
            if (model.length === 0) return true;
            const last = model[model.length - 1];

            // Требуем хотя бы заполненное название улицы
            if (!last.street || !last.street.trim()) {
                return false;
            }

            // Если нужно требовать хотя бы один дом — раскомментируй:

            const hasHouse = (last.houses || []).some(h => h && h.trim());
            if (!hasHouse) {
                return false;
            }


            return true;
        }

        // обработчик для "+ Добавить адрес"
        addBtn.addEventListener("click", () => {
            if (model.length === 0) {
                const newStreetIndex = 0;
                model.push({ street: "", houses: [] }); // первая пустая улица

                renderTable(false);

                // фокус на первой ячейке улицы
                setTimeout(() => {
                    const newStreetInput = boundsEl.querySelector(
                        `input.street-input[data-street-index="${newStreetIndex}"]`
                    );
                    if (newStreetInput) {
                        newStreetInput.scrollIntoView({ behavior: "smooth", block: "center" });
                        newStreetInput.focus();
                    }
                }, 0);

                return;
            }
            const lastStreetIndex = model.length - 1;
            const lastStreet = model[lastStreetIndex];

            // можно ли создавать новую улицу?
            const canCreate =
                lastStreet.street.trim() !== "" &&
                lastStreet.houses.some(h => h && h.trim() !== "");

            if (!canCreate) {
                // Фокусируем последнюю строку (она пустая/незавершённая)
                renderTable(false);

                setTimeout(() => {
                    const lastStreetInput = boundsEl.querySelector(
                        `input.street-input[data-street-index="${lastStreetIndex}"]`
                    );
                    if (lastStreetInput) {
                        lastStreetInput.scrollIntoView({ behavior: "smooth", block: "center" });
                        lastStreetInput.focus();
                    }
                }, 0);

                return;
            }

            // Создаём новую строку
            const newStreetIndex = model.length;
            model.push({ street: "", houses: [] });

            renderTable(false);

            // Фокусируем новую строку
            setTimeout(() => {
                const newStreetInput = boundsEl.querySelector(
                    `input.street-input[data-street-index="${newStreetIndex}"]`
                );
                if (newStreetInput) {
                    newStreetInput.scrollIntoView({ behavior: "smooth", block: "center" });
                    newStreetInput.focus();
                }
            }, 0);
        });


        // после перерендера — прокрутка к последней улице, если нужно
        if (scrollToLast) {
            setTimeout(() => {
                const lastStreetInput = boundsEl.querySelector(
                    'input.street-input:last-of-type'
                );
                if (lastStreetInput) {
                    lastStreetInput.scrollIntoView({ behavior: "smooth", block: "center" });
                    lastStreetInput.focus();
                }
            }, 50);
        }
    }

    // ---------- 5. Обработчики для улиц ----------
    function attachStreetHandlers(input) {
        if (!input.dataset.originalStreet) {
            input.dataset.originalStreet = input.value.trim();
        }

        input.addEventListener("blur", async () => {
            const streetIndex = Number(input.dataset.streetIndex);
            const newValue = input.value.trim();
            const oldValue = (input.dataset.originalStreet || "").trim();

            // пустую улицу в БД не шлём
            model[streetIndex].street = newValue;
            if (!newValue) return;

            // создание
            if (!oldValue) {
                await createStreet(newValue);
            }
            // изменение
            else if (oldValue !== newValue) {
                await updateStreet(oldValue, newValue);
            }

            // обновим original, чтобы при следующем blur не слать лишние запросы
            input.dataset.originalStreet = newValue;
        });

        input.addEventListener("keydown", (e) => {
            const streetIndex = Number(input.dataset.streetIndex);

            if (e.key === "Enter" || e.key === "ArrowRight") {
                // переход к первому дому этой улицы
                e.preventDefault();
                const firstHouse = boundsEl.querySelector(
                    `input.house-input[data-street-index="${streetIndex}"][data-house-index="0"]`
                );
                if (firstHouse) {
                    firstHouse.focus();
                }
                return;
            }

            if (e.key === "ArrowDown") {
                e.preventDefault();
                const nextStreet = boundsEl.querySelector(
                    `input.street-input[data-street-index="${streetIndex + 1}"]`
                );
                if (nextStreet) {
                    nextStreet.focus();
                }
                return;
            }

            if (e.key === "ArrowUp") {
                e.preventDefault();
                const prevStreet = boundsEl.querySelector(
                    `input.street-input[data-street-index="${streetIndex - 1}"]`
                );
                if (prevStreet) {
                    prevStreet.focus();
                }
                return;
            }
        });
    }

    // ---------- 6. Обработчики для домов ----------
    // ---------- 6. Обработчики для домов ----------
    function attachHouseHandlers(input) {
        // флаг: если true — blur после стрелки НЕ будет вызывать renderTable
        let skipRenderOnBlur = false;

        if (!input.dataset.originalHouse) {
            input.dataset.originalHouse = input.value.trim();
        }

        input.addEventListener("blur", async () => {
            const streetIndex = Number(input.dataset.streetIndex);
            const houseIndex = Number(input.dataset.houseIndex);
            const value = input.value.trim();
            const streetName = model[streetIndex].street || "";
            const originalHouse = (input.dataset.originalHouse || "").trim();

            // стрелки могут запретить только ПЕРЕРИСОВКУ, но не сохранение
            const suppressRender = skipRenderOnBlur;
            skipRenderOnBlur = false;

            // если пусто — удаляем дом (если был) и перерисовываем
            if (!value) {
                // обнуляем текущее значение в модели
                model[streetIndex].houses[houseIndex] = "";

                // если раньше дом был, нужно удалить в БД
                if (streetName && originalHouse) {
                    await deleteHouse(streetName, originalHouse);
                }

                // подрежем хвост пустых домов (если это был конец улицы — строка исчезнет)
                trimStreetHouses(streetIndex);

                input.dataset.originalHouse = "";

                // при удалении нам важна корректная разметка — всегда перерисовываем
                renderTable(false);
                return;
            }

            // записываем в модель
            model[streetIndex].houses[houseIndex] = value;

            // создаём/обновляем дом в БД
            if (streetName) {
                if (!originalHouse) {
                    // создание
                    await createHouse(streetName, value);
                } else if (originalHouse !== value) {
                    // изменение
                    await updateHouse(streetName, originalHouse, value);
                }
            }

            // запоминаем новое "оригинальное" значение
            input.dataset.originalHouse = value;

            // если blur был после стрелки — ТОЛЬКО не перерисовываем, но сохранение уже сделано
            if (!suppressRender) {
                renderTable(false);
            }
        });

        input.addEventListener("keydown", (e) => {
            const key = e.key;
            const streetIndex = Number(input.dataset.streetIndex);
            const houseIndex = Number(input.dataset.houseIndex);

            const currentValue = input.value.trim();
            const currentEmpty = !currentValue;

            // по умолчанию — blur может перерисовывать
            skipRenderOnBlur = false;

            // --- Enter ---
            if (key === "Enter") {
                // если текущая ячейка пустая — никуда не идём
                if (currentEmpty) {
                    e.preventDefault();
                    input.classList.add("input-error");
                    setTimeout(() => input.classList.remove("input-error"), 500);
                    return;
                }

                const nextInStreet = boundsEl.querySelector(
                    `input.house-input[data-street-index="${streetIndex}"][data-house-index="${houseIndex + 1}"]`
                );

                // если это последний дом в последней строке (нет следующей ячейки)
                if (!nextInStreet) {
                    e.preventDefault();
                    // blur сохранит значение и через renderTable создаст новую строку
                    input.blur();
                    setTimeout(() => {
                        const newInput = boundsEl.querySelector(
                            `input.house-input[data-street-index="${streetIndex}"][data-house-index="${houseIndex + 1}"]`
                        );
                        if (newInput) {
                            newInput.focus();
                        }
                    }, 50);
                    return;
                }

                // есть следующая ячейка — проверяем, можно ли в неё
                const targetEmpty = !nextInStreet.value.trim();
                if (targetEmpty) {
                    e.preventDefault();
                    input.classList.add("input-error");
                    setTimeout(() => input.classList.remove("input-error"), 500);
                    return;
                }

                // дальше Enter ведёт себя как обычно (пусть просто blur произойдёт)
                return;
            }

            let target = null;

            // helper: проверка, можно ли переходить в target
            function canMoveToTarget(t) {
                if (!t) return false;
                if (!t.classList.contains("house-input")) return true; // улица — всегда можно

                const targetEmpty = !t.value.trim();
                // запрет: пустая текущая + пустая целевая = нельзя
                if (currentEmpty && targetEmpty) {
                    input.classList.add("input-error");
                    setTimeout(() => input.classList.remove("input-error"), 500);
                    return false;
                }
                return true;
            }

            // --- ArrowRight ---
            if (key === "ArrowRight") {
                e.preventDefault();

                target = boundsEl.querySelector(
                    `input.house-input[data-street-index="${streetIndex}"][data-house-index="${houseIndex + 1}"]`
                );

                if (!canMoveToTarget(target)) {
                    skipRenderOnBlur = false;
                    return;
                }

                if (target) {
                    // не хотим перерисовывать при стрелках, но blur всё равно сохранит дом
                    skipRenderOnBlur = true;
                    target.focus();
                }
                return;
            }

            // --- ArrowLeft ---
            if (key === "ArrowLeft") {
                e.preventDefault();

                if (houseIndex > 0) {
                    target = boundsEl.querySelector(
                        `input.house-input[data-street-index="${streetIndex}"][data-house-index="${houseIndex - 1}"]`
                    );
                } else {
                    // если это самый левый дом — перейти к названию улицы
                    target = boundsEl.querySelector(
                        `input.street-input[data-street-index="${streetIndex}"]`
                    );
                }

                if (!canMoveToTarget(target)) {
                    skipRenderOnBlur = false;
                    return;
                }

                if (target) {
                    skipRenderOnBlur = true;
                    target.focus();
                }
                return;
            }

            // --- ArrowUp ---
            if (key === "ArrowUp") {
                e.preventDefault();

                const upIndex = houseIndex - MAX_HOUSES_PER_ROW;
                if (upIndex >= 0) {
                    target = boundsEl.querySelector(
                        `input.house-input[data-street-index="${streetIndex}"][data-house-index="${upIndex}"]`
                    );
                } else {
                    // выше домов — название улицы
                    target = boundsEl.querySelector(
                        `input.street-input[data-street-index="${streetIndex}"]`
                    );
                }

                if (!canMoveToTarget(target)) {
                    skipRenderOnBlur = false;
                    return;
                }

                if (target) {
                    skipRenderOnBlur = true;
                    target.focus();
                }
                return;
            }

            // --- ArrowDown ---
            if (key === "ArrowDown") {
                e.preventDefault();

                const downIndex = houseIndex + MAX_HOUSES_PER_ROW;
                target = boundsEl.querySelector(
                    `input.house-input[data-street-index="${streetIndex}"][data-house-index="${downIndex}"]`
                );

                if (!canMoveToTarget(target)) {
                    skipRenderOnBlur = false;
                    return;
                }

                if (target) {
                    skipRenderOnBlur = true;
                    target.focus();
                }
                return;
            }
        });
    }


    // ---------- 7. Первый рендер ----------
    renderTable(false);
});