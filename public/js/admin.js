document.addEventListener("DOMContentLoaded", () => {
    const logoutBtn = document.getElementById("admin-logout-btn");
    if (!logoutBtn) return;

    logoutBtn.addEventListener("click", async () => {
        if (!confirm("Выйти из аккаунта администратора?")) return;

        try {
            const resp = await fetch("/admin/logout", {
                method: "POST",
                headers: { "Accept": "application/json" }
            });

            const out = await resp.json().catch(() => ({}));

            if (!resp.ok || out.ok === false) {
                showMessage(out.error || "Ошибка выхода администратора", "Ошибка");
                return;
            }

            window.location.href = "/"; // редирект после выхода

        } catch (err) {
            console.error("Logout error:", err);
            showMessage("Ошибка сети при выходе администратора.", "Ошибка");
        }
    });
});
