document.addEventListener("DOMContentLoaded", function () {
    const input = document.getElementById("address");
    if (!input) return;

    let lastValue = "";

    function capitalizeWords(str) {
        return str
            .toLowerCase()
            .replace(/(^|\s|[-/])([а-яё])/g, (m, before, ch) => before + ch.toUpperCase());
    }

    input.addEventListener("input", function (e) {
        const inputType = e.inputType || "";
        let value = e.target.value;

        // ---- 1. Разрешаем стереть всё, включая "г." ----
        // Если жмём backspace и до этого было только "г." (с пробелом/без),
        // то просто очищаем поле.
        const normalizedLast = lastValue.replace(/\s+/g, "");
        if (
            inputType === "deleteContentBackward" &&
            (normalizedLast === "г." || normalizedLast === "г")
        ) {
            lastValue = "";
            e.target.value = "";
            return;
        }

        // ---- 2. Фильтрация символов ----
        value = value
            .replace(/[A-Za-z]/g, "")                // убираем латиницу
            .replace(/[^а-яёА-ЯЁ0-9,\.\s\-\/]/g, "") // оставляем русские, цифры, запятую, пробел, . - /
            .replace(/\s+/g, " ");                   // нормализуем пробелы

        // ---- 3. Разбиваем на город / улицу / дом по запятым ----
        let parts = value.split(",");

        let city = parts[0] || "";
        let street = parts[1] || "";
        let house = parts[2] || "";

        // ---- ГОРОД ----
        city = city.replace(/^г\.?\s*/i, "");      // убираем старое "г."
        city = city.replace(/[^а-яё\- ]/gi, "");         // только русские, пробел, дефис
        city = capitalizeWords(city);

        if (city) {
            city = "г. " + city;
        } else {
            // Если вообще ничего нет — не подставляем снова "г."
            e.target.value = "";
            lastValue = "";
            return;
        }

        // ---- УЛИЦА ----
        // Если пользователь поставил хотя бы одну запятую, считаем, что начали улицу
        if (parts.length >= 2 || value.includes(",")) {
            street = street.replace(/^ул\.?\s*/i, "");
            street = street.replace(/[^а-яё\- ]/gi, ""); // только русские, пробел, дефис
            street = capitalizeWords(street);
            // если текст улицы пустой — вообще не добавляем "ул."

        }

        // ---- ДОМ ----
        // Если есть вторая запятая — считаем, что начали дом
        if (parts.length >= 3) {
            house = house.replace(/^д\.?\s*/i, "").trim();
            house = house.replace(/[^а-яё0-9\s\-\/]/gi, ""); // русские, цифры, пробел, -, /
            house = capitalizeWords(house);
            house = house ? "д. " + house : "д. ";
        } else {
            house = "";
        }

        // ---- 4. Сбор итоговой строки ----
        let result = city;
        if (street) {
            result += ", " + street;
        }
        if (house) {
            result += ", " + house;
        }

        e.target.value = result;
        lastValue = result;

        // Курсор всегда в конце — так не будет адской дерготни
        const pos = result.length;
        e.target.setSelectionRange(pos, pos);
    });
});