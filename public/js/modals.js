document.addEventListener("DOMContentLoaded", () => {
  function qs(selector, parent = document) {
    return parent.querySelector(selector);
  }

  let authModal = null;
  let successModal = null;
  let adminModal = null;
  let hiddenInput = null;
  let form = null;
  let confirmBtn = null;

  function openModal(modal) {
    console.log("Проверка модалки:", modal);
    if (!modal) {
      console.warn("openModal: modal = null");
      return;
    }
    modal.classList.add("modal--open");
    console.log("Открыли модалку:", modal.id);
  }

  function closeModal(modal) {
    if (!modal) {
      console.warn("closeModal: modal = null");
      return;
    }
    modal.classList.remove("modal--open");
    console.log("Закрыли модалку:", modal.id);
  }

  // 🔹 ДЕЛЕГИРОВАНИЕ КЛИКОВ ПО КНОПКАМ "Проголосовать"
  document.addEventListener("click", (e) => {
    const btn = e.target.closest(".vote-btn, .vote-btn-candidate");
    if (!btn) return;

    // всегда отменяем переход по href="#"
    e.preventDefault();

    const candidateId = btn.dataset.id;
    console.log("Клик по Проголосовать, id =", candidateId);

    // если скрытое поле уже есть — запишем id
    if (hiddenInput) {
      hiddenInput.value = candidateId;
    }

    // пробуем открыть модалку (если она уже успела загрузиться)
    openModal(authModal);
    console.log("Клик по Проголосовать");
  });

  document.addEventListener("click", (e) => {
    // открыть модалку администратора
    const adminBtn = e.target.closest(".left-side__button");
    const insideModal = e.target.closest(".modal"); // <- клик внутри модалки?

    if (adminBtn && !insideModal) {
      // срабатывает ТОЛЬКО если нажали кнопку админа СНАРУЖИ модалок
      e.preventDefault();

      // если ещё не нашли модалку администратора — попробуем найти
      if (!adminModal) {
        adminModal = qs("#modal-admin");
      }

      openModal(adminModal);
      return;
    }

    // закрытие по data-close-modal
    if (e.target.hasAttribute("data-close-modal")) {
      const modal = e.target.closest(".modal"); // передаём элемент, а не id
      closeModal(modal);
    }
  });

  // 🔹 Ждём, пока модалки подгрузятся load-modals.js
  const waitForModals = setInterval(() => {
    authModal = qs("#vote-modal");
    successModal = qs("#vote-success-modal");
    adminModal = qs("#modal-admin"); // тоже подцепим, если уже есть

    if (!authModal || !successModal) return;

    clearInterval(waitForModals);
    initModals();
  }, 50);

  function initModals() {
    const authBackdrop = qs(".modal__backdrop", authModal);
    const successBackdrop = qs(".modal__backdrop", successModal);
    const adminBackdrop = adminModal ? qs(".modal__backdrop", adminModal) : null;

    hiddenInput = qs("#candidate-id");
    form = qs("#vote-form");
    confirmBtn = qs("#vote-confirm-btn");

    const adminForm = qs("#admin-login-form");

    // Если модалка администратора есть — вешаем обработчик
    if (adminForm) {
      adminForm.addEventListener("submit", (e) => {
        e.preventDefault(); // отключаем стандартную отправку формы

        console.log("Вход администратора...");

        // Закрываем модалку (необязательно, но красиво)
        if (adminModal) closeModal(adminModal);

        // ПЕРЕХОД НА candidate-admin.html
        window.location.href = "/candidates/admin";
      });
    }

    if (authBackdrop) {
      authBackdrop.addEventListener("click", () => closeModal(authModal));
    }

    if (successBackdrop) {
      successBackdrop.addEventListener("click", () => closeModal(successModal));
    }

    if (adminBackdrop) {
      adminBackdrop.addEventListener("click", () => closeModal(adminModal));
    }

    // ESC
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        closeModal(authModal);
        closeModal(successModal);
        if (adminModal) closeModal(adminModal);
      }
    });

    // сабмит формы авторизации
    form.addEventListener("submit", async (e) => {
      e.preventDefault();

      const formData = new FormData(form);
      console.log("Отправка формы авторизации:", Object.fromEntries(formData));

      // TODO: здесь реальная авторизация
      // const resp = await fetch('/vote-login', { method:'POST', body: formData });

      closeModal(authModal);
      openModal(successModal);
    });

    // подтверждение голоса
    confirmBtn.addEventListener("click", async () => {
      const candidateId = hiddenInput ? hiddenInput.value : null;
      console.log("Подтверждаем голос за кандидата", candidateId);

      // TODO: запрос на /vote

      closeModal(successModal);
      alert("Голос отправлен!");
    });
  }
});
