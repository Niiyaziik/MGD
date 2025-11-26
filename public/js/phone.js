document.addEventListener("DOMContentLoaded", function () {
    const phoneInput = document.getElementById("phone");
    if (!phoneInput) return;

    let lastDigits = ""; // запоминаем состояние цифр до изменения

    function formatPhone(digits) {
        if (!digits.length) return "";

        // первая цифра: 7
        if (digits[0] === "8") {
            digits = "7" + digits.slice(1);
        }
        if (digits[0] !== "7") {
            digits = "7" + digits;
        }

        digits = digits.slice(0, 11); // максимум 11 цифр

        let result = "+7";

        if (digits.length > 1) {
            result += " (" + digits.slice(1, 4);
        }
        if (digits.length >= 4) {
            result += ")";
        }
        if (digits.length >= 5) {
            result += " " + digits.slice(4, 7);
        }
        if (digits.length >= 7) {
            result += "-" + digits.slice(7, 9);
        }
        if (digits.length >= 9) {
            result += "-" + digits.slice(9, 11);
        }

        return result;
    }

    phoneInput.addEventListener("focus", function (e) {
        if (!e.target.value) {
            // при фокусе, если пусто — начинаем с +7
            lastDigits = "7";
            e.target.value = formatPhone(lastDigits);
            // ставим курсор в конец
            e.target.setSelectionRange(e.target.value.length, e.target.value.length);
        } else {
            lastDigits = e.target.value.replace(/\D/g, "");
        }
    });

    phoneInput.addEventListener("input", function (e) {
        let newDigits = e.target.value.replace(/\D/g, "");
        const inputType = e.inputType || "";

        // если жали Backspace по символу маски: цифры не поменялись
        if (
            inputType === "deleteContentBackward" &&
            newDigits === lastDigits &&
            newDigits.length
        ) {
            // принудительно удаляем последнюю цифру
            newDigits = newDigits.slice(0, -1);
        }

        lastDigits = newDigits;
        e.target.value = formatPhone(newDigits);

        // всегда ставим курсор в конец — иначе с какетой будет ад
        const pos = e.target.value.length;
        e.target.setSelectionRange(pos, pos);
    });
});
