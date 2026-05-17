// <!--document.addEventListener("DOMContentLoaded", () => {
//     if (!("VKIDSDK" in window)) {
//         console.warn("VKIDSDK не загрузился");
//         return;
//     }

//     const VKID = window.VKIDSDK;

//     VKID.Config.init({
//         app: 54388523, // твой app_id
//         redirectUrl: "https://freely-famous-tern.cloudpub.ru/auth/vk/callback",
//         responseMode: VKID.ConfigResponseMode.Callback,
//         source: VKID.ConfigSource.LOWCODE,
//         scope: "" // потом заполни нужными правами
//     });

//     const container = document.getElementById("vkid-onetap");
//     if (!container) return;

//     const oneTap = new VKID.OneTap();

//     oneTap
//         .render({
//             container,
//             showAlternativeLogin: true
//         })
//         .on(VKID.WidgetEvents.ERROR, vkidOnError)
//         .on(VKID.OneTapInternalEvents.LOGIN_SUCCESS, function (payload) {
//             const code = payload.code;
//             const deviceId = payload.device_id;

//             // Меняем стандартную обработку под твой бэкенд
//             VKID.Auth.exchangeCode(code, deviceId)
//                 .then(vkidOnSuccess)
//                 .catch(vkidOnError);
//         });

//     async function vkidOnSuccess(data) {
//         // data — это результат от VK (access token + профиль)
//         // Здесь ты отправляешь всё на свой backend,
//         // чтобы через AuthController/Auth.php создать/найти пользователя.

//         try {
//             const resp = await fetch("/auth/vk/onetap", {
//                 method: "POST",
//                 headers: {
//                     "Content-Type": "application/json",
//                     "Accept": "application/json"
//                 },
//                 body: JSON.stringify(data)
//             });

//             const out = await resp.json().catch(() => ({}));

//             if (!resp.ok || out.ok === false) {
//                 showMessage(out.error || "Ошибка авторизации через ВКонтакте", "Ошибка");
//                 return;
//             }

//             // Тут можно:
//             // - сохранить в JS prefill-данные (fio, phone, birth_year)
//             // - открыть твою дополнительную модалку (Отчество/Адрес/Телефон)
//             // - дальше запустить ту же цепочку: капча → модалка голосования
//             console.log("VK auth OK, данные:", out);

//         } catch (e) {
//             console.error("Ошибка /auth/vk/onetap:", e);
//             showMessage("Не удалось завершить авторизацию через ВК", "Ошибка");
//         }
//     }

//     // function vkidOnError(error) {
//     //     console.error("VKID error:", error);
//     //     showMessage("Ошибка при авторизации через ВК", "Ошибка");
//     // }
// });-->

(function () {
    const VK_CONTAINER_ID = "vkid-onetap";
    const MIDDLE_MODAL_ID = "vk-middle-name-modal";
  
    function waitForElement(selector, timeoutMs = 10000) {
      return new Promise((resolve, reject) => {
        const found = document.querySelector(selector);
        if (found) {
          resolve(found);
          return;
        }
  
        const started = Date.now();
        const timer = setInterval(() => {
          const el = document.querySelector(selector);
          if (el) {
            clearInterval(timer);
            resolve(el);
            return;
          }
  
          if (Date.now() - started > timeoutMs) {
            clearInterval(timer);
            reject(new Error("Не найден элемент " + selector));
          }
        }, 100);
      });
    }
  
    function openModal(modal) {
      if (!modal) return;
      modal.classList.add("modal--open");
      modal.setAttribute("aria-hidden", "false");
  
      const focusTarget = modal.querySelector("[data-autofocus], input, button");
      if (focusTarget) focusTarget.focus();
    }
  
    function closeModal(modal) {
      if (!modal) return;
      modal.classList.remove("modal--open");
      modal.setAttribute("aria-hidden", "true");
      if (document.activeElement) document.activeElement.blur();
    }
  
    function escapeHtml(value) {
      return String(value || "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
    }
  
    function formatPhoneForForm(phone) {
      let digits = String(phone || "").replace(/\D/g, "");
      if (!digits) return "";
  
      if (digits[0] === "8") digits = "7" + digits.slice(1);
      if (digits[0] !== "7") digits = "7" + digits;
      digits = digits.slice(0, 11);
  
      if (digits.length !== 11) return phone || "";
  
      return `+7 (${digits.slice(1, 4)}) ${digits.slice(4, 7)}-${digits.slice(7, 9)}-${digits.slice(9, 11)}`;
    }
  
    function createMiddleNameModal() {
      let modal = document.getElementById(MIDDLE_MODAL_ID);
      if (modal) return modal;
  
      const wrapper = document.createElement("div");
      wrapper.innerHTML = `
        <div class="modal" id="${MIDDLE_MODAL_ID}" aria-hidden="true">
          <div class="modal__backdrop" data-vk-middle-close></div>
          <div class="modal__dialog" role="dialog" aria-modal="true">
            <h2 class="modal__title">Завершение входа через VK</h2>
            <p class="modal__social-title" style="margin-top: 0;">
              VK передал фамилию, имя и телефон. Отчество нужно ввести вручную.
            </p>
  
            <form class="modal__form" id="vk-middle-name-form">
              <label class="modal__label">
                Фамилия
                <input class="modal__input" type="text" name="last_name" readonly>
              </label>
  
              <label class="modal__label">
                Имя
                <input class="modal__input" type="text" name="first_name" readonly>
              </label>
  
              <label class="modal__label">
                Отчество
                <input class="modal__input" type="text" name="middle_name" placeholder="Иванович" required data-autofocus>
              </label>
  
              <div class="modal__actions">
                <button type="submit" class="left-side__button">Продолжить</button>
              </div>
            </form>
          </div>
        </div>
      `;
  
      modal = wrapper.firstElementChild;
      document.body.appendChild(modal);
  
      modal.querySelector("[data-vk-middle-close]")?.addEventListener("click", () => closeModal(modal));
  
      return modal;
    }
  
    function fillMainVoteForm(prefill) {
      const form = document.getElementById("vote-form");
      if (!form) return;
  
      const fioInput = form.querySelector('input[name="fio"]');
      const phoneInput = form.querySelector('input[name="phone"]');
      const addressInput = form.querySelector('input[name="address"]');
  
      if (fioInput && prefill.fio) {
        fioInput.value = prefill.fio;
        fioInput.dispatchEvent(new Event("input", { bubbles: true }));
      }
  
      if (phoneInput && prefill.phone) {
        phoneInput.value = formatPhoneForForm(prefill.phone);
        phoneInput.dispatchEvent(new Event("input", { bubbles: true }));
      }
  
      if (addressInput) {
        addressInput.focus();
      }
    }
  
    async function showMiddleNameStep(vkPrefill) {
      const modal = createMiddleNameModal();
      const form = modal.querySelector("#vk-middle-name-form");
  
      form.querySelector('input[name="last_name"]').value = vkPrefill.last_name || "";
      form.querySelector('input[name="first_name"]').value = vkPrefill.first_name || "";
      form.querySelector('input[name="middle_name"]').value = "";
  
      form.onsubmit = async (event) => {
        event.preventDefault();
  
        const submitBtn = form.querySelector("button[type='submit']");
        const middleName = form.querySelector('input[name="middle_name"]').value.trim();
  
        if (!middleName) {
          alert("Введите отчество");
          return;
        }
  
        if (submitBtn) submitBtn.disabled = true;
  
        try {
          const resp = await fetch("/auth/vk/middle-name", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "Accept": "application/json"
            },
            credentials: "same-origin",
            body: JSON.stringify({ middle_name: middleName })
          });
  
          const out = await resp.json().catch(() => ({}));
          if (!resp.ok || out.ok === false) {
            alert(out.error || "Не удалось сохранить отчество");
            return;
          }
  
          closeModal(modal);
          fillMainVoteForm(out.prefill || {});
        } catch (error) {
          console.error("Ошибка /auth/vk/middle-name:", error);
          alert("Ошибка соединения с сервером");
        } finally {
          if (submitBtn) submitBtn.disabled = false;
        }
      };
  
      openModal(modal);
    }
  
    async function initVkOneTap(container) {
      if (!("VKIDSDK" in window)) {
        console.warn("VKIDSDK не загрузился");
        return;
      }
  
      const configResp = await fetch("/auth/vk/config", {
        headers: { "Accept": "application/json" },
        credentials: "same-origin"
      });
      const config = await configResp.json().catch(() => ({}));
  
      if (!config.ok || !config.enabled) {
        container.style.display = "none";
        return;
      }
  
      const VKID = window.VKIDSDK;
  
      VKID.Config.init({
        app: Number(config.app),
        redirectUrl: config.redirectUrl,
        responseMode: VKID.ConfigResponseMode.Callback,
        source: VKID.ConfigSource.LOWCODE,
        scope: config.scope || "phone email"
      });
  
      const oneTap = new VKID.OneTap();
  
      oneTap
        .render({
          container,
          showAlternativeLogin: true,
          styles: { width: 300 }
        })
        .on(VKID.WidgetEvents.ERROR, vkidOnError)
        .on(VKID.OneTapInternalEvents.LOGIN_SUCCESS, function (payload) {
          const code = payload.code;
          const deviceId = payload.device_id;
  
          VKID.Auth.exchangeCode(code, deviceId)
            .then(vkidOnSuccess)
            .catch(vkidOnError);
        });
    }
  
    async function vkidOnSuccess(data) {
      try {
        const resp = await fetch("/auth/vk/onetap", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "Accept": "application/json"
          },
          credentials: "same-origin",
          body: JSON.stringify(data)
        });
  
        const out = await resp.json().catch(() => ({}));
        if (!resp.ok || out.ok === false) {
          alert(out.error || "Ошибка авторизации через ВКонтакте");
          return;
        }
  
        const vkPrefill = out.prefill || {};
        await showMiddleNameStep(vkPrefill);
      } catch (error) {
        console.error("Ошибка /auth/vk/onetap:", error);
        alert("Не удалось завершить авторизацию через ВК");
      }
    }
  
    function vkidOnError(error) {
      console.error("VKID error:", error);
      alert("Ошибка при авторизации через ВК");
    }
  
    document.addEventListener("DOMContentLoaded", () => {
      waitForElement("#" + VK_CONTAINER_ID)
        .then(initVkOneTap)
        .catch((error) => console.warn(error.message));
    });
  })();
  
