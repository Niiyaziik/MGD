document.addEventListener("DOMContentLoaded", async () => {
    const container = document.createElement("div");

    // грузим обе модалки
    const authRes = await fetch("/components/auth-modal.html");
    const successRes = await fetch("/components/vote-success-modal.html");
    const adminRes = await fetch("/components/modal-admin.html");

    const authHtml = await authRes.text();
    const successHtml = await successRes.text();
    const adminHtml = await adminRes.text();

    container.innerHTML = authHtml + successHtml + adminHtml;

    document.body.appendChild(container);
});
