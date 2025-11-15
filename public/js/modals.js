document.addEventListener("DOMContentLoaded", () => {
  function qs(selector, parent = document) {
    return parent.querySelector(selector);
  }

  let authModal    = null;
  let successModal = null;
  let hiddenInput  = null;
  let form         = null;
  let confirmBtn   = null;

  function openModal(modal) {
    if (!modal) return;
    modal.classList.add("modal--open");
  }

  function closeModal(modal) {
    if (!modal) return;
    modal.classList.remove("modal--open");
  }

  // 🔹 ДЕЛЕГИРОВАНИЕ КЛИКОВ ПО КНОПКАМ "Проголосовать"
  document.addEventListener("click", (e) => {
    const btn = e.target.closest(".vote-btn");
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
  });

  document.addEventListener("click", (e) => {
    // открыть модалку администратора
        if (e.target.closest(".left-side__button")) {
            e.preventDefault();
            openModal("modal-admin");
        }

        // закрытие
        if (e.target.hasAttribute("data-close-modal")) {
            closeModal(e.target.closest(".modal").id);
        }
    });
    function openModal(id) {
        const modal = document.getElementById(id);
        if (modal) modal.classList.add("open");
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (modal) modal.classList.remove("open");
    }

  // 🔹 Ждём, пока модалки подгрузятся load-modals.js
  const waitForModals = setInterval(() => {
    authModal    = qs('#vote-modal');
    successModal = qs('#vote-success-modal');

    if (!authModal || !successModal) return;

    clearInterval(waitForModals);
    initModals();
  }, 50);

  function initModals() {
    const authBackdrop    = qs('.modal__backdrop', authModal);
    const authCloseBtn    = qs('.modal__close', authModal);
    const successBackdrop = qs('.modal__backdrop', successModal);
    const successCloseBtn = qs('.modal__close', successModal);

    hiddenInput = qs('#candidate-id');
    form        = qs('#vote-form');
    confirmBtn  = qs('#vote-confirm-btn');

    // закрытие модалки авторизации
    authCloseBtn.addEventListener("click", () => closeModal(authModal));
    authBackdrop.addEventListener("click", () => closeModal(authModal));

    // закрытие второй модалки
    successCloseBtn.addEventListener("click", () => closeModal(successModal));
    successBackdrop.addEventListener("click", () => closeModal(successModal));

    // ESC
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        closeModal(authModal);
        closeModal(successModal);
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
