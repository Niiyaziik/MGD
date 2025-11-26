document.addEventListener("DOMContentLoaded", function () {

    const form = document.getElementById("candidate-add-form");
    const saveBtn = document.querySelector(".save-btn-candidate");

    function showAlert(message) {
        const container = document.getElementById("alert-container");

        const alert = document.createElement("div");
        alert.className = "alert-message";
        alert.textContent = message;

        container.appendChild(alert);

        // исчезает через 4 секунды
        setTimeout(() => {
            alert.classList.add("hide");
            setTimeout(() => alert.remove(), 100);
        }, 4000);
    }

    saveBtn.addEventListener("click", function (e) {
        e.preventDefault();

        const fullName = form.querySelector('input[name="full_name"]').value.trim();
        const address = form.querySelector('input[name="address"]').value.trim();
        const email = form.querySelector('input[name="email"]').value.trim();
        const phone = form.querySelector('input[name="phone"]').value.trim();

        // --- Проверка ФИО ---
        if (!fullName) {
            showAlert("Введите ФИО.");
            return;
        }

        // const parts = fullName.split(/\s+/);
        // if (parts.length < 3) {
        //     showAlert("ФИО должно содержать фамилию, имя и отчество.");
        //     return;
        // }

        if (!address) {
            showAlert("Введите адрес.");
            return;
        }

        // --- Проверка формата адреса ---
        // Ожидаем: г. <город>, ул. <улица>, д. <дом>
        // if (!/^г\.\s*[А-ЯЁа-яё \-]+,\s*ул\.\s*[А-ЯЁа-яё \-]+,\s*д\.\s*[А-ЯЁа-яё0-9\/ \-]+$/.test(address)) {
        //     showAlert("Адрес должен быть в формате: г. <город>, ул. <улица>, д. <дом>");
        //     return;
        // }

        // --- Проверка Email ---
        if (!email) {
            showAlert("Введите e-mail.");
            return;
        }
        // if (!/^[\w\.-]+@[\w\.-]+\.\w+$/.test(email)) {
        //     showAlert("Введите корректный e-mail.");
        //     return;
        // }

        // --- Проверка телефона ---
        if (!phone) {
            showAlert("Введите телефон.");
            return;
        }
        // if (!/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/.test(phone)) {
        //     showAlert("Введите телефон в формате +7 (XXX) XXX-XX-XX");
        //     return;
        // }


        // Всё ок — отправляем форму
        form.submit();
    });

});