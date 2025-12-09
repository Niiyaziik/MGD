<div class="modal" id="phone-confirm-modal" aria-hidden="true">
  <div class="modal__backdrop"></div>
  <div class="modal__dialog" role="dialog" aria-modal="true">
    <h2 class="modal__title">Подтверждение телефона</h2>

    <p class="modal__text">
      На ваш номер отправлен код подтверждения.
    </p>

    <form id="phone-code-form" class="modal__form">
      <label class="modal__label">
        Код из SMS
        <input
          type="text"
          class="modal__input"
          id="sms-code-input"
          maxlength="6"
          placeholder="Введите код"
          required
          data-autofocus
        >
      </label>

      <div class="modal__actions">
        <button type="submit" id="phone-code-submit-btn" class="left-side__button">
          Подтвердить
        </button>
      </div>
    </form>
  </div>
</div>
