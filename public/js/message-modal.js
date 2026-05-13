/**
 * Универсальная функция для показа модального окна с сообщением
 * Заменяет стандартный alert()
 * 
 * @param {string} message - Текст сообщения
 * @param {string} title - Заголовок модалки (по умолчанию "Сообщение")
 * @param {function} onClose - Callback функция, вызываемая после закрытия модалки
 */
function showMessage(message, title = "Сообщение", onClose = null) {
    // Ждем загрузки DOM, если модалка еще не загружена
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => showMessage(message, title, onClose));
        return;
    }

    const modal = document.getElementById("message-modal");
    if (!modal) {
        // Если модалка еще не загружена, используем fallback на alert
        console.warn("message-modal не найдена, используем alert");
        alert(message);
        if (onClose) onClose();
        return;
    }

    const messageText = document.getElementById("message-text");
    const messageTitle = document.getElementById("message-title");
    const okBtn = document.getElementById("message-ok-btn");
    const backdrop = modal.querySelector(".modal__backdrop");
    const closeBtn = modal.querySelector(".modal__close");

    if (!messageText || !messageTitle || !okBtn) {
        console.error("Не найдены элементы message-modal");
        alert(message);
        if (onClose) onClose();
        return;
    }

    // Устанавливаем текст и заголовок
    messageText.textContent = message;
    messageTitle.textContent = title;

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


