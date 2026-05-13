/**
 * Функция для показа модального окна со списком ошибок
 * 
 * @param {string[]} errors - Массив строк с ошибками
 * @param {string} title - Заголовок модалки (по умолчанию "Ошибки импорта")
 * @param {function} onClose - Callback функция, вызываемая после закрытия модалки
 */
function showErrors(errors, title = "Ошибки импорта", onClose = null) {
    // Ждем загрузки DOM, если модалка еще не загружена
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => showErrors(errors, title, onClose));
        return;
    }

    const modal = document.getElementById("errors-modal");
    if (!modal) {
        // Если модалка еще не загружена, используем fallback на showMessage
        console.warn("errors-modal не найдена, используем showMessage");
        const errorText = errors.length > 0 ? errors.slice(0, 5).join('\n') : "Неизвестная ошибка";
        showMessage(errorText, title);
        if (onClose) onClose();
        return;
    }

    const errorsList = document.getElementById("errors-list");
    const errorsTitle = document.getElementById("errors-title");
    const okBtn = document.getElementById("errors-ok-btn");
    const backdrop = modal.querySelector(".modal__backdrop");
    const closeBtn = modal.querySelector(".modal__close");

    if (!errorsList || !errorsTitle || !okBtn) {
        console.error("Не найдены элементы errors-modal");
        const errorText = errors.length > 0 ? errors.slice(0, 5).join('\n') : "Неизвестная ошибка";
        showMessage(errorText, title);
        if (onClose) onClose();
        return;
    }

    // Устанавливаем заголовок
    errorsTitle.textContent = title;

    // Очищаем список ошибок
    errorsList.innerHTML = "";

    // Добавляем ошибки в список
    if (errors && errors.length > 0) {
        errors.forEach((error, index) => {
            const errorItem = document.createElement("div");
            errorItem.style.cssText = "padding: 8px 12px; margin-bottom: 8px; background-color: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px; font-size: 14px; line-height: 1.5;";
            errorItem.textContent = `${index + 1}. ${error}`;
            errorsList.appendChild(errorItem);
        });

        // Если ошибок больше 10, показываем сообщение
        if (errors.length >= 10) {
            const moreInfo = document.createElement("div");
            moreInfo.style.cssText = "padding: 8px 12px; margin-top: 8px; background-color: #f8f9fa; border-radius: 4px; font-size: 13px; color: #666; font-style: italic;";
            moreInfo.textContent = "Показаны первые 10 ошибок. Остальные ошибки можно увидеть в логах сервера.";
            errorsList.appendChild(moreInfo);
        }
    } else {
        const noErrors = document.createElement("div");
        noErrors.style.cssText = "padding: 8px 12px; text-align: center; color: #666; font-style: italic;";
        noErrors.textContent = "Ошибок не найдено";
        errorsList.appendChild(noErrors);
    }

    // Функция закрытия
    const closeHandler = () => {
        modal.classList.remove("modal--open");
        modal.setAttribute("aria-hidden", "true");
        
        // Удаляем обработчики
        okBtn.removeEventListener("click", closeHandler);
        backdrop.removeEventListener("click", closeHandler);
        closeBtn.removeEventListener("click", closeHandler);
        document.removeEventListener("keydown", escapeHandler);

        if (onClose) onClose();
    };

    // Обработка Escape
    const escapeHandler = (e) => {
        if (e.key === "Escape") {
            closeHandler();
        }
    };

    // Добавляем обработчики
    okBtn.addEventListener("click", closeHandler);
    backdrop.addEventListener("click", closeHandler);
    closeBtn.addEventListener("click", closeHandler);
    document.addEventListener("keydown", escapeHandler);

    // Открываем модалку
    modal.classList.add("modal--open");
    modal.setAttribute("aria-hidden", "false");

    // Фокус на кнопку ОК
    okBtn.focus();
}


