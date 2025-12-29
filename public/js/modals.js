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
      alert("Не удалось определить кандидата для голосования.");
      return;
    }

    currentCandidateId = candidateId;

    if (!hiddenInput) {
      hiddenInput = qs("#candidate-id");
    }
    if (hiddenInput) {
      hiddenInput.value = candidateId;
    }

    const card = btn.closest(".candidate-card, .candidate-page, .candidate-item, .deputat");

    const modalPhoto = document.getElementById("vote-candidate-photo");
    const modalName = document.getElementById("vote-candidate-name");

    // ==== ФОТО ====
    if (modalPhoto) {
      let img = null;

      if (card) {
        img = card.querySelector(
          ".candidates-card__photo, .candidate-photo, .candidate__photo, .deputat__image"
        );
      }

      // запасной вариант: ищем глобально на странице кандидата
      if (!img) {
        img =
          document.querySelector(".deputat__image") ||
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

    // ==== ФИО ====
    if (modalName) {
      let nameEl = null;

      if (card) {
        nameEl = card.querySelector(
          ".candidate-card__name, .candidate-name, .candidate__name, .deputat__name"
        );
      }

      // запасной вариант: ищем заголовок кандидата глобально
      if (!nameEl) {
        nameEl =
          document.querySelector(".deputat__name") ||
          document.querySelector(".candidate-card__name") ||
          document.querySelector(".candidate-name");
      }

      if (nameEl) {
        // Берём текст, чистим пробелы
        const text = nameEl.textContent.replace(/\s+/g, " ").trim();
        const parts = text.split(" ");

        const lastname = parts[0] || "";
        const firstname = parts[1] || "";
        const middlename = parts.slice(2).join(" "); // всё, что осталось, считаем отчеством

        // Пишем сразу в модалку:
        // "Фамилия Имя" на первой строке, отчество — на второй
        modalName.innerHTML =
          `${lastname} ${firstname}` +
          (middlename ? `<br>${middlename}` : "");
      } else {
        modalName.textContent = "Кандидат";
      }
    }
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
        /^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/.test(phoneInput.value.trim());

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

      phoneInput.addEventListener("focus", function (e) {
        if (!e.target.value) {
          lastDigits = "7";
          e.target.value = formatPhone(lastDigits);
          e.target.setSelectionRange(e.target.value.length, e.target.value.length);
        } else {
          lastDigits = e.target.value.replace(/\D/g, "");
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
            alert("Укажите логин и пароль");
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
              alert(msg);
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
            alert("Ошибка при входе администратора. Попробуйте позже.");
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
          alert(out.error || "Ошибка авторизации");
          return; // НЕ открываем капчу
        }
      } catch (err) {
        console.error("Ошибка /auth/precheck:", err);
        alert("Не удалось выполнить проверку номера. Попробуйте позже.");
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
          alert("Ошибка загрузки reCAPTCHA");
          return;
        }

        const token = grecaptcha.getResponse();
        if (!token) {
          if (captchaSubmitBtn) {
            captchaSubmitBtn.disabled = false;
            captchaSubmitBtn.classList.remove("btn-disabled");
          }
          alert("Подтвердите, что вы не робот");
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

            alert(out.error || "Проверка reCAPTCHA не пройдена");
            return;
          }

        } catch (err) {
          console.error(err);

          if (captchaSubmitBtn) {
            captchaSubmitBtn.disabled = false;
            captchaSubmitBtn.classList.remove("btn-disabled");
          }

          alert("Не удалось проверить капчу. Попробуйте позже");
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

          alert("Данные авторизации потеряны. Попробуйте ещё раз.");
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
            alert(out.error || "Ошибка авторизации");
            return;
          }

          // можно ли голосовать за выбранного кандидата?
          if (out.can_vote) {
            console.log("Округ совпадает, открываем модалку голосования");
            closeModal(captchaModal);
            openModal(successModal);
          } else {
            // округа не совпадают
            const userDistrict = out.user_district_num || out.user_district_id;
            alert(
              `Вы не можете проголосовать за данного кандидата, ` +
              `поскольку он относится к другому округу.\n\n` +
              `Ваш округ голосования: ${userDistrict}.`
            );

            closeModal(captchaModal);

            // перенаправляем на /candidates с фильтром по округу пользователя
            if (userDistrict) {
              window.location.href =
                "/candidates?district=" + encodeURIComponent(userDistrict);
            } else {
              window.location.href = "/candidates";
            }
          }

        } catch (err) {
          console.error("Ошибка /auth/login:", err);
          alert("Не удалось выполнить авторизацию. Попробуйте позже.");
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
              alert(out.error || "Вы не авторизованы как голосующий");
            }

            return;
          }

          // 2) если ошибка округа — закрываем модалку и уводим на страницу его округа
          if (resp.status === 422 && (out.user_district_num || out.user_district_id)) {
            const userDistrict =
              out.user_district_num ||
              out.user_district_id;

            // сначала закрываем модалку голосования
            closeModal(successModal);

            // показываем сообщение об ошибке
            alert(out.error || "Вы не можете голосовать за кандидата из другого округа.");

            // редирект на страницу кандидатов его округа
            if (userDistrict) {
              window.location.href =
                "/candidates?district=" + encodeURIComponent(userDistrict);
            } else {
              window.location.href = "/candidates";
            }

            return;
          }

          // 3) любые остальные ошибки — просто показываем
          alert(out.error || "Ошибка при отправке голоса");
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
          alert("Ваш голос принят. Спасибо за участие!");
        }

      } catch (err) {
        console.error("Ошибка при голосовании:", err);
        alert("Не удалось отправить голос. Попробуйте позже.");
      }
    }

    if (confirmBtn) {
      confirmBtn.addEventListener("click", async () => {
        if (!pendingAuthData || !pendingAuthData.phone) {
          alert("Не удалось определить номер телефона для отправки SMS.");
          return;
        }

        try {
          const resp = await fetch("/auth/send-code", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "Accept": "application/json"
            },
            body: JSON.stringify({ phone: pendingAuthData.phone })
          });

          const out = await resp.json().catch(() => ({}));
          console.log("Ответ /auth/send-code =", out);

          if (!resp.ok || out.ok === false) {
            alert(out.error || "Не удалось отправить SMS с кодом подтверждения.");
            return;
          }

          // Код отправлен: закрываем модалку голосования и открываем ввод кода
          closeModal(successModal);

          if (phoneModal) {
            if (phoneCodeInput) {
              phoneCodeInput.value = "";
            }
            openModal(phoneModal);
          } else {
            alert("Не найдена модалка подтверждения по телефону.");
          }
        } catch (err) {
          console.error("Ошибка /auth/send-code:", err);
          alert("Не удалось отправить SMS. Попробуйте позже.");
        }
      });
    }

    if (phoneCodeForm && phoneCodeSubmitBtn) {
      phoneCodeForm.addEventListener("submit", async (e) => {
        e.preventDefault();

        if (!pendingAuthData || !pendingAuthData.phone) {
          alert("Не удалось определить номер телефона.");
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
          const resp = await fetch("/auth/check-code", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "Accept": "application/json"
            },
            body: JSON.stringify({
              phone: pendingAuthData.phone,
              code: code
            })
          });

          const out = await resp.json().catch(() => ({}));

          if (!resp.ok || out.ok === false) {
            alert(out.error || "Неверный или просроченный код из SMS.");
            phoneCodeSubmitBtn.disabled = false;
            phoneCodeSubmitBtn.classList.remove("btn-disabled");
            return;
          }

          // ✅ Код подтверждён — закрываем модалку телефона и отправляем голос
          closeModal(phoneModal);
          await sendVoteRequest();
        } catch (err) {
          console.error("Ошибка /auth/check-code:", err);
          alert("Не удалось проверить код. Попробуйте позже.");
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
  const suggestBox = document.createElement("div");
  suggestBox.className = "address-suggest";
  suggestBox.style.display = "none";

  wrapper.style.position = "relative";
  wrapper.appendChild(suggestBox);

  let timer = null;

  input.addEventListener("input", () => {
    // как только пользователь что-то ручками меняет — адрес снова "не подтверждён"
    input.dataset.valid = "0";
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
        const m = withoutCity.match(/^([^,\d]+)[, ]*(.*)$/);
        const streetPart = m && m[1] ? m[1].trim() : "";
        const housePart = m && m[2] ? m[2].trim() : "";

        if (!streetPart || streetPart.length < 2) {
          suggestBox.style.display = "none";
          suggestBox.innerHTML = "";
          return;
        }

        const resp = await fetch(
          "/address/suggest?query=" + encodeURIComponent(streetPart),
          { headers: { "Accept": "application/json" } }
        );
        if (!resp.ok) throw new Error("Ошибка запроса адреса");
        let list = await resp.json();
        if (!Array.isArray(list)) list = [];

        if (housePart) {
          const digits = housePart.replace(/\D/g, "");
          if (digits) {
            list = list.filter(item =>
              String(item.house || "").includes(digits)
            );
          }
        }

        if (!list.length) {
          suggestBox.style.display = "none";
          suggestBox.innerHTML = "";
          return;
        }

        suggestBox.innerHTML = list
          .map(item => {
            const street = item.street || "";
            const house = item.house || "";
            const label = `${street}, ${house}`;
            return `
              <div class="address-suggest__item"
                   data-street="${street.replace(/"/g, "&quot;")}"
                   data-house="${house.replace(/"/g, "&quot;")}">
                г. Ульяновск, ${label}
              </div>
            `;
          })
          .join("");

        suggestBox.style.display = "block";
      } catch (e) {
        console.error(e);
        suggestBox.style.display = "none";
        suggestBox.innerHTML = "";
      }
    }, 300);
  });

  // ВЫБОР ПОДСКАЗКИ — НА MOUSEDOWN, ЧТОБЫ УСПЕТЬ ДО BLUR У INPUT
  suggestBox.addEventListener("mousedown", (e) => {
    const item = e.target.closest(".address-suggest__item");
    if (!item) return;

    // не даём браузеру сначала перевести фокус (blur input), а потом клик
    e.preventDefault();

    const street = item.getAttribute("data-street") || "";
    const house = item.getAttribute("data-house") || "";

    input.value = `г. Ульяновск, ${street}, ${house}`;
    input.dataset.valid = "1";

    suggestBox.style.display = "none";
    suggestBox.innerHTML = "";

    if (typeof onChangeValid === "function") {
      onChangeValid();
    }

    // можно вернуть фокус в input, если нужно:
    input.focus();
  });

  input.addEventListener("blur", () => {
    // просто спрячем подсказки чуть позже, чтобы не мешать mousedown
    setTimeout(() => {
      suggestBox.style.display = "none";
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

