document.addEventListener("DOMContentLoaded", async () => {
    const container = document.createElement("div");

    // Загружаем все модалки
    const authRes = await fetch("/components/auth-modal.html");
    const successRes = await fetch("/components/vote-success-modal.html");
    const adminRes = await fetch("/components/modal-admin.html");
    const captchaRes = await fetch("/components/reCaptcha.php");
    const phoneRes = await fetch("/components/phone.php");
    const finishRes = await fetch("/components/success.php");

    const authHtml = await authRes.text();
    const successHtml = await successRes.text();
    const adminHtml = await adminRes.text();
    const captchaHtml = await captchaRes.text();
    const phoneHtml = await phoneRes.text();
    const finishHtml = await finishRes.text();

    // добавляем в DOM все модалки
    container.innerHTML =
        authHtml +
        successHtml +
        adminHtml +
        captchaHtml +
        phoneHtml +
        finishHtml;

    document.body.appendChild(container);

    console.log("Модалки загружены. Есть captcha-modal?",
        !!document.querySelector("#captcha-modal")
    );
    console.log("Есть div.g-recaptcha?",
        !!document.querySelector(".g-recaptcha")
    );
});
