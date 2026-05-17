let voterAuthorized = false;

async function checkVoterStatus() {
  try {
    const resp = await fetch("/auth/status", {
      headers: { "Accept": "application/json" }
    });
    const out = await resp.json().catch(() => ({}));

    voterAuthorized = !!out.authorized;
    console.log("voterAuthorized =", voterAuthorized, out);
  } catch (e) {
    console.error("Не удалось проверить статус голосующего", e);
    voterAuthorized = false;
  }

  const logoutBtn = document.querySelector(".left-side__button--logout");
  if (logoutBtn) {
    logoutBtn.style.display = voterAuthorized ? "block" : "none";
  }
}

document.addEventListener("DOMContentLoaded", () => {
  function qs(selector, parent = document) {
    return parent.querySelector(selector);
  }

  let authModal = null;
  let successModal = null;
  let adminModal = null;
  let captchaModal = null;
  let warningModal = null;

  let hiddenInput = null;
  let form = null;
  let confirmBtn = null;

  let captchaForm = null; // форма с капчей
  let currentCandidateId = null; // id кандидата, за которого хотим голосовать
  let pendingAuthData = null;    // временно храним fio/phone/address до капчи

  let phoneModal = null;
  let phoneCodeForm = null;
  let phoneCodeInput = null;
  let phoneCodeSubmitBtn = null;

  // телефон, с которым работаем в цепочке капча → смс → голосование
  let lastAuthPhone = "";

  function openModal(modal) {
    if (!modal) {
      console.warn("openModal: modal = null");
      return;
    }
    modal.classList.add("modal--open");
    modal.setAttribute("aria-hidden", "false");

    // ставим фокус внутрь модалки (чтоб не ругался браузер)
    const focusTarget =
      modal.querySelector("[data-autofocus]") ||
      modal.querySelector("input, button, select, textarea");
    if (focusTarget) {
      focusTarget.focus();
    }
  }

  function closeModal(modal) {
    if (!modal) {
      console.warn("closeModal: modal = null");
      return;
    }
    modal.classList.remove("modal--open");
    modal.setAttribute("aria-hidden", "true");

    // убираем фокус, чтобы не было "aria-hidden with focused descendant"
    if (document.activeElement) {
      document.activeElement.blur();
    }
  }

  // 🔹 Клик по "Проголосовать" — открываем authModal (после того как он реально есть)
  document.addEventListener("click", (e) => {
    const btn = e.target.closest(".vote-btn, .vote-btn-candidate");
    if (!btn) return;

    e.preventDefault();

    // Берём id кандидата из data-id ИЛИ data-candidate-id
    const rawId = btn.dataset.id || btn.dataset.candidateId;
    const candidateId = rawId ? String(rawId).trim() : "";

    if (!candidateId) {
      console.warn("Не удалось определить ID кандидата для голосования", btn);
      showMessage("Не удалось определить кандидата для голосования.", "Ошибка");
      return;
    }

    currentCandidateId = candidateId;

    if (!hiddenInput) {
      hiddenInput = qs("#candidate-id");
    }
    if (hiddenInput) {
      hiddenInput.value = candidateId;
    }

    const modalPhoto = document.getElementById("vote-candidate-photo");
    const modalName = document.getElementById("vote-candidate-name");

    // Сначала пытаемся найти данные в DOM (быстро)
    const card = btn.closest(".candidate-card, .candidate-page, .candidate-item, .deputat, .my-deputat__item");

    // ==== ФОТО (из DOM) ====
    if (modalPhoto) {
      let img = null;
      if (card) {
        img = card.querySelector(
          ".candidates-card__photo, .candidate-photo, .candidate__photo, .deputat__image, .my-deputat__image"
        );
      }
      if (!img) {
        img = document.querySelector(".deputat__image, .my-deputat__image") ||
          document.querySelector(".candidates-card__photo");
      }
      if (img && img.src) {
        modalPhoto.src = img.src;
        modalPhoto.alt = img.alt || "Фото кандидата";
      } else {
        modalPhoto.src = "/assets/img/candidates/placeholder.jpeg";
        modalPhoto.alt = "Фото кандидата";
      }
    }

    // ==== ФИО (из DOM) ====
    if (modalName) {
      let nameEl = null;
      if (card) {
        nameEl = card.querySelector(
          ".candidate-card__name, .candidate-name, .candidate__name, .deputat__name, .my-deputat__name"
        );
      }
      if (!nameEl) {
        nameEl = document.querySelector(".deputat__name, .my-deputat__name") ||
          document.querySelector(".candidate-card__name") ||
          document.querySelector(".candidate-name");
      }
      if (nameEl) {
        const text = nameEl.textContent.replace(/\s+/g, " ").trim();
        const parts = text.split(" ");
        const lastname = parts[0] || "";
        const firstname = parts[1] || "";
        const middlename = parts.slice(2).join(" ");
        modalName.innerHTML =
          `${lastname} ${firstname}`.trim() +
          (middlename ? `<br>${middlename}` : "");
      } else {
        modalName.textContent = "Кандидат";
      }
    }

    // Затем загружаем данные по API и обновляем (если нужно)
    (async () => {
      try {
        const res = await fetch(`/candidate?format=json&id=${candidateId}`);
        if (!res.ok) throw new Error('Ошибка загрузки данных кандидата');
        const candidate = await res.json();

        // ==== ФОТО (обновляем из API) ====
        if (modalPhoto) {
          modalPhoto.src = candidate.photo || "/assets/img/candidates/placeholder.jpeg";
          const fio = `${candidate.surname || ''} ${candidate.name || ''} ${candidate.patronymic || ''}`.trim();
          modalPhoto.alt = fio || "Фото кандидата";
        }

        // ==== ФИО (обновляем из API) ====
        if (modalName) {
          const surname = candidate.surname || "";
          const name = candidate.name || "";
          const patronymic = candidate.patronymic || "";

          if (surname || name) {
            // "Фамилия Имя" на первой строке, отчество — на второй
            modalName.innerHTML =
              `${surname} ${name}`.trim() +
              (patronymic ? `<br>${patronymic}` : "");
          } else {
            modalName.textContent = "Кандидат";
          }
        }
      } catch (err) {
        console.error("Ошибка загрузки данных кандидата из API:", err);
        // Данные уже установлены из DOM, ничего не делаем
      }
    })();
    // ЕСЛИ уже авторизован (есть voter в сессии) — сразу модалка голосования
    if (voterAuthorized) {
      if (!successModal) {
        successModal = qs("#vote-success-modal");
      }
      if (!successModal) {
        console.warn("Не найдена модалка #vote-success-modal");
        return;
      }
      openModal(successModal);
      return;
    }

    // иначе — обычная цепочка: авторизация -> капча
    if (!authModal) {
      authModal = qs("#vote-modal");
    }
    if (!authModal) {
      console.warn("Не найдена модалка #vote-modal в DOM");
      return;
    }

    openModal(authModal);
  });

  // 🔹 Клик по кнопке "Вход для администратора" (вне модалки)
  document.addEventListener("click", async (e) => {
    const insideModal = e.target.closest(".modal");
    // 🔹 Выход из сессии голосующего
    const logoutBtn = e.target.closest(".left-side__button--logout");
    if (logoutBtn && !insideModal) {
      e.preventDefault();

      try {
        await fetch("/auth/logout", {
          method: "POST",
          headers: { "Accept": "application/json" }
        });
      } catch (err) {
        console.error("Ошибка при выходе из сессии голосующего:", err);
      }

      voterAuthorized = false;
      logoutBtn.style.display = "none";

      // опционально можно обновить страницу:
      // window.location.reload();

      return;
    }

    // закрытие по data-close-modal
    if (e.target.hasAttribute("data-close-modal")) {
      const modal = e.target.closest(".modal");
      closeModal(modal);
    }
  });


  // 🔹 Ждём модалки, которые подгружаются load-modals.js
  const waitForModals = setInterval(() => {
    authModal = qs("#vote-modal");
    successModal = qs("#vote-success-modal");
    adminModal = qs("#modal-admin");
    captchaModal = qs("#captcha-modal");
    finishModal = qs("#vote-finish-modal");
    phoneModal = qs("#phone-confirm-modal");
    warningModal = qs("#warning-modal");


    if (!authModal || !successModal || !captchaModal || !phoneModal) return;

    clearInterval(waitForModals);
    initModals();
  }, 50);

  function initModals() {
    const authBackdrop = qs(".modal__backdrop", authModal);
    const successBackdrop = qs(".modal__backdrop", successModal);
    const captchaBackdrop = qs(".modal__backdrop", captchaModal);
    const adminBackdrop = adminModal ? qs(".modal__backdrop", adminModal) : null;
    const finishBackdrop = finishModal ? qs(".modal__backdrop", finishModal) : null;
    const warningBackdrop = warningModal ? qs(".modal__backdrop", warningModal) : null;

    hiddenInput = qs("#candidate-id");
    form = qs("#vote-form");
    confirmBtn = qs("#vote-confirm-btn");
    captchaForm = qs("#captcha-form");

    const fioInput = qs('input[name="fio"]', form);
    const phoneInput = qs('input[name="phone"]', form);
    const addressInput = qs('input[name="address"]', form);
    const submitBtn = qs('#vote-form .left-side__button');

    const adminForm = qs("#admin-login-form");

    addressInput && (addressInput.dataset.valid = "0");

    phoneCodeForm = qs("#phone-code-form");
    phoneCodeInput = phoneCodeForm
      ? phoneCodeForm.querySelector(".modal__input")
      : null;
    phoneCodeSubmitBtn = qs("#phone-code-submit-btn");
    function updateSubmitDisabled() {
      let fioOk = false;

      if (fioInput) {
        const words = fioInput.value
          .trim()
          .split(/\s+/)
          .filter(Boolean);

        fioOk = (words.length === 3);
      }

      const phoneOk = phoneInput &&
        /^\+7 \(9\d{2}\) \d{3}-\d{2}-\d{2}$/.test(phoneInput.value.trim());

      const addressOk = addressInput && addressInput.dataset.valid === "1";

      const canSubmit = fioOk && phoneOk && addressOk;

      if (submitBtn) {
        submitBtn.disabled = !canSubmit;
        submitBtn.classList.toggle("btn-disabled", !canSubmit);
      }
    }

    document.addEventListener("click", (e) => {
      const adminBtn = e.target.closest("#admin-login-btn");
      if (!adminBtn) return;

      const insideModal = e.target.closest(".modal");
      if (insideModal) return;

      e.preventDefault();

      if (!adminModal) {
        adminModal = qs("#modal-admin");
      }

      if (!adminModal) {
        console.warn("Модалка #modal-admin ещё не загружена");
        return;
      }

      openModal(adminModal);
    });


    // --- ФИО: только русские, каждое слово с заглавной, ровно 3 слова ---
    if (fioInput) {
      fioInput.addEventListener("input", () => {
        let value = fioInput.value;

        value = value.replace(/[^А-Яа-яЁё\s]/g, "");

        const hasTrailingSpace = value.endsWith(" ");

        const parts = value
          .trim()
          .split(/\s+/)
          .filter(Boolean)
          .map(word =>
            word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
          );

        let newValue = parts.join(" ");
        if (hasTrailingSpace) {
          newValue += " ";
        }

        fioInput.value = newValue;

        updateSubmitDisabled();
      });
    }

    // --- Маска телефона ---
    if (phoneInput) {
      let lastDigits = "";

      function formatPhone(digits) {
        if (!digits.length) return "";

        if (digits[0] === "8") {
          digits = "7" + digits.slice(1);
        }
        if (digits[0] !== "7") {
          digits = "7" + digits;
        }

        digits = digits.slice(0, 11);

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

      function enforcePrefix(digits) {
        if (!digits.length) return "79";
        if (digits[0] === "8") digits = "7" + digits.slice(1);
        if (digits[0] !== "7") digits = "7" + digits;
        // Вторая цифра всегда 9
        if (digits.length >= 2 && digits[1] !== "9") {
          digits = "79" + digits.slice(2);
        }
        return digits;
      }

      phoneInput.addEventListener("focus", function (e) {
        if (!e.target.value) {
          lastDigits = "79";
          e.target.value = formatPhone(lastDigits);
          e.target.setSelectionRange(e.target.value.length, e.target.value.length);
        } else {
          lastDigits = enforcePrefix(e.target.value.replace(/\D/g, ""));
          e.target.value = formatPhone(lastDigits);
          e.target.setSelectionRange(e.target.value.length, e.target.value.length);
        }
      });

      phoneInput.addEventListener("input", function (e) {
        let newDigits = e.target.value.replace(/\D/g, "");
        const inputType = e.inputType || "";

        if (
          inputType === "deleteContentBackward" &&
          newDigits === lastDigits &&
          newDigits.length
        ) {
          newDigits = newDigits.slice(0, -1);
        }

        // Принудительно удерживаем префикс 79
        if (newDigits.length >= 1) {
          newDigits = enforcePrefix(newDigits);
        }

        newDigits = newDigits.slice(0, 11);
        lastDigits = newDigits;
        e.target.value = formatPhone(newDigits);

        const pos = e.target.value.length;
        e.target.setSelectionRange(pos, pos);

        updateSubmitDisabled();
      });
    }

    // --- Адрес с автодополнением ---
    if (addressInput) {
      addressInput.addEventListener("input", () => {
        addressInput.dataset.valid = "0";
        updateSubmitDisabled();
      });

      initAddressAutocomplete(addressInput, updateSubmitDisabled);
    }

    updateSubmitDisabled();

    // форма админа
    // форма входа администратора
    if (adminForm) {
      adminForm.addEventListener("submit", async (e) => {
        e.preventDefault();

        const loginInput = adminForm.querySelector('input[name="login"]');
        const passInput = adminForm.querySelector('input[name="password"]');
        const errorBox = document.getElementById("admin-login-error");

        const login = loginInput ? loginInput.value.trim() : "";
        const password = passInput ? passInput.value : "";

        if (errorBox) {
          errorBox.style.display = "none";
          errorBox.textContent = "";
        }

        if (!login || !password) {
          if (errorBox) {
            errorBox.textContent = "Укажите логин и пароль";
            errorBox.style.display = "block";
          } else {
            showMessage("Укажите логин и пароль", "Ошибка");
          }
          return;
        }

        try {
          const resp = await fetch("/admin/login", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "Accept": "application/json"
            },
            body: JSON.stringify({ login, password })
          });

          const out = await resp.json().catch(() => ({}));
          console.log("Ответ /admin/login =", out);

          if (!resp.ok || out.ok === false) {
            const msg = out.error || "Неверный логин или пароль";
            if (errorBox) {
              errorBox.textContent = msg;
              errorBox.style.display = "block";
            } else {
              showMessage(msg, "Ошибка");
            }
            return;
          }

          // успешный вход
          if (adminModal) closeModal(adminModal);

          // переходим в админку кандидатов
          window.location.href = "/candidates/admin";

        } catch (err) {
          console.error("Ошибка /admin/login:", err);
          if (errorBox) {
            errorBox.textContent = "Ошибка при входе администратора. Попробуйте позже.";
            errorBox.style.display = "block";
          } else {
            showMessage("Ошибка при входе администратора. Попробуйте позже.", "Ошибка");
          }
        }
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
    if (captchaBackdrop) {
      captchaBackdrop.addEventListener("click", () => closeModal(captchaModal));
    }
    if (finishBackdrop) {
      finishBackdrop.addEventListener("click", () => closeModal(finishModal));
    }
    if (warningBackdrop) {
      warningBackdrop.addEventListener("click", () => closeModal(warningModal));
    }

    // ESC закрывает все модалки
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        closeModal(authModal);
        closeModal(successModal);
        if (adminModal) closeModal(adminModal);
        if (captchaModal) closeModal(captchaModal);
        if (finishModal) closeModal(finishModal);
      }
    });

    // 🔹 Цепочка: Войти → reCAPTCHA → SMS → голосование

    // 1) Сабмит формы авторизации → открываем капчу
    form.addEventListener("submit", async (e) => {
      e.preventDefault();

      // const formData = new FormData(form);
      // lastAuthPhone = formData.get("phone") || "";

      // closeModal(authModal);
      // if (!captchaModal) {
      //   captchaModal = qs("#captcha-modal");
      // }
      // if (!captchaModal) {
      //   console.warn("Не найдена модалка #captcha-modal");
      //   return;
      // }
      // openModal(captchaModal);
      // openModal(successModal);

      if (!fioInput || !phoneInput || !addressInput) return;

      // собираем данные, но пока НЕ отправляем на сервер
      pendingAuthData = {
        fio: fioInput.value.trim(),
        phone: phoneInput.value.trim(),
        address: addressInput.value.trim(),
        candidate_id: currentCandidateId
      };

      lastAuthPhone = pendingAuthData.phone;
      console.log("auth form submit: pendingAuthData =", pendingAuthData);
      console.log("auth form submit: lastAuthPhone =", lastAuthPhone);


      try {
        const resp = await fetch("/auth/precheck", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "Accept": "application/json"
          },
          body: JSON.stringify({ phone: pendingAuthData.phone })
        });

        const out = await resp.json().catch(() => ({}));

        if (!resp.ok || out.ok === false) {
          showMessage(out.error || "Ошибка авторизации", "Ошибка");
          return; // НЕ открываем капчу
        }
      } catch (err) {
        console.error("Ошибка /auth/precheck:", err);
        showMessage("Не удалось выполнить проверку номера. Попробуйте позже.", "Ошибка");
        return;
      }
      console.log("Авторизация: подготовили данные, показываем капчу", pendingAuthData);

      // закрываем модалку авторизации, открываем капчу
      closeModal(authModal);

      if (captchaForm) {
        const captchaSubmitBtn =
          captchaForm.querySelector("button[type='submit'], .left-side__button");
        if (captchaSubmitBtn) {
          captchaSubmitBtn.disabled = false;
          captchaSubmitBtn.classList.remove("btn-disabled");
        }
      }

      openModal(captchaModal);
    });

    // 2) Сабмит капчи: проверка reCAPTCHA, отправка СМС
    if (captchaForm) {
      captchaForm.addEventListener("submit", async (e) => {
        e.preventDefault();

        // Находим кнопку отправки капчи:
        const captchaSubmitBtn = captchaForm.querySelector("button[type='submit'], .left-side__button");

        // Делаем кнопку некликабельной
        if (captchaSubmitBtn) {
          captchaSubmitBtn.disabled = true;
          captchaSubmitBtn.classList.add("btn-disabled"); // если используется твой стиль отключения
        }

        if (typeof grecaptcha === "undefined") {
          if (captchaSubmitBtn) {
            captchaSubmitBtn.disabled = false;
            captchaSubmitBtn.classList.remove("btn-disabled");
          }
          showMessage("Ошибка загрузки reCAPTCHA", "Ошибка");
          return;
        }

        const token = grecaptcha.getResponse();
        if (!token) {
          if (captchaSubmitBtn) {
            captchaSubmitBtn.disabled = false;
            captchaSubmitBtn.classList.remove("btn-disabled");
          }
          showMessage("Подтвердите, что вы не робот", "Ошибка");
          return;
        }

        try {
          const resp = await fetch("/captcha/verify", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "Accept": "application/json"
            },
            body: JSON.stringify({ token })
          });

          const out = await resp.json().catch(() => ({}));
          if (!resp.ok || out.ok === false) {

            // Делаем кнопку обратно кликабельной, т.к. ошибка
            if (captchaSubmitBtn) {
              captchaSubmitBtn.disabled = false;
              captchaSubmitBtn.classList.remove("btn-disabled");
            }

            showMessage(out.error || "Проверка reCAPTCHA не пройдена", "Ошибка");
            return;
          }

        } catch (err) {
          console.error(err);

          if (captchaSubmitBtn) {
            captchaSubmitBtn.disabled = false;
            captchaSubmitBtn.classList.remove("btn-disabled");
          }

          showMessage("Не удалось проверить капчу. Попробуйте позже", "Ошибка");
          return;
        }

        // Если капча пройдена — дальше выполняем логику login()

        if (window.grecaptcha) {
          window.grecaptcha.reset();
        }

        if (!pendingAuthData) {
          if (captchaSubmitBtn) {
            captchaSubmitBtn.disabled = false;
            captchaSubmitBtn.classList.remove("btn-disabled");
          }

          showMessage("Данные авторизации потеряны. Попробуйте ещё раз.", "Ошибка");
          closeModal(captchaModal);
          return;
        }

        closeModal(captchaModal);

        try {
          const resp = await fetch("/auth/login", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "Accept": "application/json"
            },
            body: JSON.stringify(pendingAuthData)
          });

          const out = await resp.json().catch(() => ({}));

          if (!resp.ok || out.ok === false) {
            showMessage(out.error || "Ошибка авторизации", "Ошибка");
            return;
          }

          // Авторизация успешна - открываем модалку голосования
          console.log("Авторизация успешна, открываем модалку голосования");
          closeModal(captchaModal);
          openModal(successModal);

        } catch (err) {
          console.error("Ошибка /auth/login:", err);
          showMessage("Не удалось выполнить авторизацию. Попробуйте позже.", "Ошибка");
        }
      });
    }

    async function sendVoteRequest() {
      const candidateId = hiddenInput ? hiddenInput.value : currentCandidateId;
      console.log("Подтверждаем голос за кандидата", candidateId);

      try {
        const resp = await fetch("/votes", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "Accept": "application/json"
          },
          body: JSON.stringify({ candidate_id: candidateId })
        });

        const out = await resp.json().catch(() => ({}));

        // 🔴 Ошибка при голосовании
        if (!resp.ok || out.ok === false) {
          // 1) если сессия истекла / не авторизован — отправляем на авторизацию
          if (resp.status === 401 || (out.error && out.error.toLowerCase().includes("не авторизованы"))) {
            console.warn("Сессия голосующего истекла, открываем модалку авторизации");

            voterAuthorized = false;
            closeModal(successModal);

            if (!authModal) {
              authModal = qs("#vote-modal");
            }
            if (authModal) {
              openModal(authModal);
            } else {
              showMessage(out.error || "Вы не авторизованы как голосующий", "Ошибка");
            }

            return;
          }


          // 3) любые остальные ошибки — просто показываем
          showMessage(out.error || "Ошибка при отправке голоса", "Ошибка");
          return;
        }

        // 🟢 Всё ок: голос принят на бэке
        // выходим (очищаем сессию голосующего на сервере)
        await fetch("/auth/logout", {
          method: "POST",
          headers: { "Accept": "application/json" }
        }).catch(() => { });

        // ⚠️ ВАЖНО: локально тоже считаем, что авторизации больше нет
        voterAuthorized = false;
        pendingAuthData = null;
        currentCandidateId = null;

        closeModal(successModal);

        // показываем красивую финальную модалку
        const finishModalEl = document.getElementById("vote-finish-modal");
        if (finishModalEl) {
          openModal(finishModalEl);
          setTimeout(async () => {
            closeModal(finishModalEl);
            await fetch("/auth/logout", {
              method: "POST",
              headers: { "Accept": "application/json" }
            }).catch(() => { });
          }, 3000);
        } else {
          showMessage("Ваш голос принят. Спасибо за участие!", "Успех");
        }

      } catch (err) {
        console.error("Ошибка при голосовании:", err);
        showMessage("Не удалось отправить голос. Попробуйте позже.", "Ошибка");
      }
    }

    if (confirmBtn) {
      confirmBtn.addEventListener("click", async () => {
        // Сначала проверяем округ перед отправкой SMS
        const candidateId = hiddenInput ? hiddenInput.value : currentCandidateId;
        if (!candidateId) {
          showMessage("Не удалось определить кандидата для голосования.", "Ошибка");
          return;
        }

        try {
          // Проверяем округ
          const districtCheckResp = await fetch("/auth/check-district", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "Accept": "application/json"
            },
            body: JSON.stringify({ candidate_id: candidateId })
          });

          const districtCheckOut = await districtCheckResp.json().catch(() => ({}));

          if (!districtCheckResp.ok || !districtCheckOut.can_vote) {
            // Округа не совпадают
            const userDistrict = districtCheckOut.user_district_num || districtCheckOut.user_district_id;

            closeModal(successModal);

            // Открываем модалку предупреждения
            if (!warningModal) {
              warningModal = qs("#warning-modal");
            }

            if (warningModal) {
              const warningMessage = qs("#warning-message", warningModal);
              const warningDistrict = qs("#warning-user-district", warningModal);

              if (warningMessage) {
                warningMessage.textContent =
                  "Вы не можете проголосовать за данного кандидата, поскольку он относится к другому округу.";
              }

              if (warningDistrict) {
                warningDistrict.textContent = userDistrict || "не определён";
              }

              openModal(warningModal);

              // После закрытия модалки перенаправляем на страницу кандидатов
              const warningOkBtn = qs("#warning-ok-btn", warningModal);
              if (warningOkBtn) {
                // Удаляем старые обработчики, если они есть
                const newBtn = warningOkBtn.cloneNode(true);
                warningOkBtn.parentNode.replaceChild(newBtn, warningOkBtn);

                newBtn.addEventListener("click", () => {
                  closeModal(warningModal);
                  if (userDistrict) {
                    window.location.href = "/candidates?district=" + encodeURIComponent(userDistrict);
                  } else {
                    window.location.href = "/candidates";
                  }
                });
              }
            } else {
              // Fallback на showMessage, если модалка не загрузилась
              showMessage(
                `Вы не можете проголосовать за данного кандидата, ` +
                `поскольку он относится к другому округу.\n\n` +
                `Ваш округ голосования: ${userDistrict || "не определён"}.`,
                "Ошибка округа"
              );

              if (userDistrict) {
                window.location.href = "/candidates?district=" + encodeURIComponent(userDistrict);
              } else {
                window.location.href = "/candidates";
              }
            }
            return;
          }

          // Округ совпадает - продолжаем с отправкой SMS
          // Определяем телефон: сначала из pendingAuthData, потом из lastAuthPhone, потом из сессии
          let phoneToUse = null;

          if (pendingAuthData && pendingAuthData.phone) {
            phoneToUse = pendingAuthData.phone;
          } else if (lastAuthPhone) {
            phoneToUse = lastAuthPhone;
          } else if (voterAuthorized) {
            // Если авторизован, получаем телефон из сессии
            try {
              const statusResp = await fetch("/auth/status", {
                headers: { "Accept": "application/json" }
              });
              const statusData = await statusResp.json().catch(() => ({}));
              if (statusData.voter && statusData.voter.phone) {
                phoneToUse = statusData.voter.phone;
              }
            } catch (err) {
              console.error("Ошибка получения статуса:", err);
            }
          }

          if (!phoneToUse) {
            showMessage("Не удалось определить номер телефона для отправки SMS.", "Ошибка");
            return;
          }

          const resp = await fetch("/auth/send-code", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "Accept": "application/json"
            },
            body: JSON.stringify({ phone: phoneToUse })
          });

          const out = await resp.json().catch(() => ({}));
          console.log("Ответ /auth/send-code =", out);

          // Код сохранен в БД, даже если SMS не отправилось - продолжаем процесс
          if (!resp.ok || out.ok === false) {
            // Проверяем, не связана ли ошибка с форматом телефона (это критично)
            const errorMsg = out.error || "";
            if (errorMsg.includes("формат телефона") || errorMsg.includes("Неверный формат")) {
              showMessage(errorMsg || "Неверный формат телефона.", "Ошибка");
              return;
            }
            // Для остальных ошибок (например, проблемы с отправкой SMS) - продолжаем
            // Код уже сохранен в БД, пользователь может ввести его вручную
            console.warn("SMS не отправлено, но код сохранен в БД:", errorMsg);
          }

          // Код сохранен в БД: закрываем модалку голосования и открываем ввод кода
          closeModal(successModal);

          if (phoneModal) {
            if (phoneCodeInput) {
              phoneCodeInput.value = "";
            }
            openModal(phoneModal);
          } else {
            showMessage("Не найдена модалка подтверждения по телефону.", "Ошибка");
          }
        } catch (err) {
          console.error("Ошибка /auth/send-code:", err);
          // При сетевой ошибке тоже продолжаем - возможно код уже сохранен
          // Пользователь может попробовать ввести код
          closeModal(successModal);
          if (phoneModal) {
            if (phoneCodeInput) {
              phoneCodeInput.value = "";
            }
            openModal(phoneModal);
          }
        }
      });
    }

    if (phoneCodeForm && phoneCodeSubmitBtn) {
      phoneCodeForm.addEventListener("submit", async (e) => {
        e.preventDefault();

        let phoneToUse = null;

        if (pendingAuthData && pendingAuthData.phone) {
          phoneToUse = pendingAuthData.phone.trim();
        } else if (lastAuthPhone) {
          phoneToUse = lastAuthPhone.trim();
        } else if (voterAuthorized) {
          // Если авторизован, получаем телефон из сессии
          try {
            const statusResp = await fetch("/auth/status", {
              headers: { "Accept": "application/json" }
            });
            const statusData = await statusResp.json().catch(() => ({}));
            if (statusData.voter && statusData.voter.phone) {
              phoneToUse = statusData.voter.phone.trim();
            }
          } catch (err) {
            console.error("Ошибка получения статуса:", err);
          }
        }

        if (!phoneToUse) {
          showMessage("Не удалось определить номер телефона.", "Ошибка");
          return;
        }

        const code = phoneCodeInput ? phoneCodeInput.value.trim() : "";
        if (!code) {
          if (phoneCodeInput) phoneCodeInput.focus();
          return;
        }

        phoneCodeSubmitBtn.disabled = true;
        phoneCodeSubmitBtn.classList.add("btn-disabled");

        try {
          const requestData = {
            phone: phoneToUse,
            code: code
          };
          console.log("Отправка запроса /auth/check-code:", requestData);

          const resp = await fetch("/auth/check-code", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "Accept": "application/json"
            },
            body: JSON.stringify(requestData)
          });

          console.log("Статус ответа /auth/check-code:", resp.status, resp.statusText);

          const out = await resp.json().catch((err) => {
            console.error("Ошибка парсинга JSON:", err);
            return {};
          });

          console.log("Ответ /auth/check-code:", out);

          if (!resp.ok || out.ok === false) {
            console.error("Ошибка проверки кода:", out.error || "Неизвестная ошибка");
            showMessage(out.error || "Неверный или просроченный код из SMS.", "Ошибка");
            phoneCodeSubmitBtn.disabled = false;
            phoneCodeSubmitBtn.classList.remove("btn-disabled");
            return;
          }

          // ✅ Код подтверждён — закрываем модалку телефона и отправляем голос
          closeModal(phoneModal);
          await sendVoteRequest();
        } catch (err) {
          console.error("Ошибка /auth/check-code:", err);
          showMessage("Не удалось проверить код. Попробуйте позже.", "Ошибка");
        } finally {
          phoneCodeSubmitBtn.disabled = false;
          phoneCodeSubmitBtn.classList.remove("btn-disabled");
        }
      });
    }
    // if (confirmBtn) {
    //   confirmBtn.addEventListener("click", async () => {
    //     const candidateId = hiddenInput ? hiddenInput.value : currentCandidateId;
    //     console.log("Подтверждаем голос за кандидата", candidateId);

    //     try {
    //       const resp = await fetch("/votes", {
    //         method: "POST",
    //         headers: {
    //           "Content-Type": "application/json",
    //           "Accept": "application/json"
    //         },
    //         body: JSON.stringify({
    //           candidate_id: candidateId
    //         })
    //       });

    //       const out = await resp.json().catch(() => ({}));

    //       // 🔴 Ошибка при голосовании
    //       if (!resp.ok || out.ok === false) {
    //         // 1) если сессия истекла / не авторизован — отправляем на авторизацию
    //         if (resp.status === 401 || (out.error && out.error.toLowerCase().includes("не авторизованы"))) {
    //           console.warn("Сессия голосующего истекла, открываем модалку авторизации");

    //           voterAuthorized = false;
    //           closeModal(successModal);

    //           if (!authModal) {
    //             authModal = qs("#vote-modal");
    //           }
    //           if (authModal) {
    //             openModal(authModal);
    //           } else {
    //             alert(out.error || "Вы не авторизованы как голосующий");
    //           }

    //           return;
    //         }

    //         // 2) если ошибка округа — закрываем модалку и уводим на страницу его округа
    //         if (resp.status === 422 && (out.user_district_num || out.user_district_id)) {
    //           const userDistrict =
    //             out.user_district_num ||
    //             out.user_district_id;

    //           // сначала закрываем модалку голосования
    //           closeModal(successModal);

    //           // показываем сообщение об ошибке
    //           alert(out.error || "Вы не можете голосовать за кандидата из другого округа.");

    //           // редирект на страницу кандидатов его округа
    //           if (userDistrict) {
    //             window.location.href =
    //               "/candidates?district=" + encodeURIComponent(userDistrict);
    //           } else {
    //             window.location.href = "/candidates";
    //           }

    //           return;
    //         }

    //         // 3) любые остальные ошибки — просто показываем
    //         alert(out.error || "Ошибка при отправке голоса");
    //         return;
    //       }

    //       // 🟢 Всё ок: голос принят на бэке
    //       // выходим (очищаем сессию голосующего на сервере)
    //       await fetch("/auth/logout", {
    //         method: "POST",
    //         headers: { "Accept": "application/json" }
    //       }).catch(() => { });

    //       // ⚠️ ВАЖНО: локально тоже считаем, что авторизации больше нет
    //       voterAuthorized = false;
    //       pendingAuthData = null;
    //       currentCandidateId = null;

    //       closeModal(successModal);

    //       // показываем красивую финальную модалку
    //       const finishModal = document.getElementById("vote-finish-modal");
    //       openModal(finishModal);

    //       // автоматически закрываем через 3 секунды
    //       setTimeout(() => {
    //         closeModal(finishModal);
    //       }, 2000);

    //     } catch (err) {
    //       console.error("Ошибка при голосовании:", err);
    //       alert("Не удалось отправить голос. Попробуйте позже.");
    //     }
    //   });
    // }
  }
  checkVoterStatus();
});

function initAddressAutocomplete(input, onChangeValid) {
  const wrapper = input.parentElement;
  if (!wrapper) return;

  const suggestBox = document.createElement("div");
  suggestBox.className = "address-suggest";
  suggestBox.style.display = "none";

  const hintAnchor = document.createElement("div");
  hintAnchor.style.cssText = "height:0;overflow:visible;position:relative;";
  const hintEl = document.createElement("div");
  hintEl.className = "address-hint";
  hintEl.textContent = "Выберите адрес с номером дома из списка";
  hintEl.style.display = "none";
  hintAnchor.appendChild(hintEl);

  wrapper.style.position = "relative";
  wrapper.appendChild(suggestBox);
  wrapper.appendChild(hintAnchor);

  function showHint(show) {
    hintEl.style.display = show ? "block" : "none";
  }

  let timer = null;
  const cache = new Map();

  input.addEventListener("input", () => {
    // как только пользователь что-то ручками меняет — адрес снова "не подтверждён"
    input.dataset.valid = "0";
    showHint(false);
    if (typeof onChangeValid === "function") {
      onChangeValid();
    }

    const withoutCity = input.value.replace(/^г\.?\s*ульяновск,?\s*/i, "").trim();

    if (timer) clearTimeout(timer);

    if (withoutCity.length < 2) {
      suggestBox.style.display = "none";
      suggestBox.innerHTML = "";
      return;
    }

    timer = setTimeout(async () => {
      try {
        let list;
        if (cache.has(withoutCity)) {
          list = cache.get(withoutCity);
        } else {
          const resp = await fetch(
            "/address/suggest?query=" + encodeURIComponent(withoutCity),
            { headers: { "Accept": "application/json" } }
          );
          if (!resp.ok) throw new Error("Ошибка запроса адреса");
          list = await resp.json();
          if (!Array.isArray(list)) list = [];
          cache.set(withoutCity, list);
        }

        if (!list.length) {
          suggestBox.style.display = "none";
          suggestBox.innerHTML = "";
          return;
        }

        suggestBox.innerHTML = list
          .map(item => {
            const street   = item.street || "";
            const house    = item.house  || "";
            const complete = item.complete ? "1" : "0";
            const label    = item.label  || (house ? `г. Ульяновск, ${street}, ${house}` : `г. Ульяновск, ${street}`);
            return `<div class="address-suggest__item"
                         data-street="${street.replace(/"/g, "&quot;")}"
                         data-house="${house.replace(/"/g, "&quot;")}"
                         data-complete="${complete}"
                    >${label}</div>`;
          })
          .join("");

        suggestBox.style.display = "block";
      } catch (e) {
        console.error(e);
        suggestBox.style.display = "none";
        suggestBox.innerHTML = "";
      }
    }, 120);
  });

  // ВЫБОР ПОДСКАЗКИ — НА MOUSEDOWN, ЧТОБЫ УСПЕТЬ ДО BLUR У INPUT
  suggestBox.addEventListener("mousedown", (e) => {
    const item = e.target.closest(".address-suggest__item");
    if (!item) return;

    // не даём браузеру сначала перевести фокус (blur input), а потом клик
    e.preventDefault();

    const street   = item.getAttribute("data-street")   || "";
    const house    = item.getAttribute("data-house")    || "";
    const complete = item.getAttribute("data-complete") === "1";

    if (complete) {
      input.value = `г. Ульяновск, ${street}, ${house}`;
      input.dataset.valid = "1";
      suggestBox.style.display = "none";
      suggestBox.innerHTML = "";
      showHint(false);
      if (typeof onChangeValid === "function") onChangeValid();
      input.focus();
    } else {
      // Только улица — подставляем и сразу запрашиваем дома
      input.value = `г. Ульяновск, ${street}, `;
      input.dataset.valid = "0";
      suggestBox.style.display = "none";
      suggestBox.innerHTML = "";
      input.focus();
      // Курсор в конец
      const len = input.value.length;
      input.setSelectionRange(len, len);
      // Запускаем поиск домов — программный set не триггерит input event
      input.dispatchEvent(new Event("input"));
    }
  });

  input.addEventListener("blur", () => {
    setTimeout(() => {
      suggestBox.style.display = "none";
      // Показываем предупреждение если что-то введено, но дом не выбран
      if (input.value.trim() && input.dataset.valid !== "1") {
        showHint(true);
      }
    }, 150);
  });

  // Форматирование значения при change — ТОЛЬКО если адрес не подтверждён кликом
  input.addEventListener("change", () => {
    if (input.dataset.valid === "1") {
      // значение выбрано из подсказки, не трогаем
      return;
    }

    const trimmed = input.value.trim();
    if (!trimmed) return;

    const rest = trimmed.replace(/^г\.?\s*ульяновск,?\s*/i, "").trim();
    input.value = rest ? `г. Ульяновск, ${rest}` : "г. Ульяновск";
  });
}

