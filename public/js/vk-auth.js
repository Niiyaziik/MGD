document.addEventListener("DOMContentLoaded", () => {
    if (!("VKIDSDK" in window)) {
        console.warn("VKIDSDK не загрузился");
        return;
    }

    const VKID = window.VKIDSDK;

    VKID.Config.init({
        app: 54388523, // твой app_id
        redirectUrl: "https://freely-famous-tern.cloudpub.ru/auth/vk/callback",
        responseMode: VKID.ConfigResponseMode.Callback,
        source: VKID.ConfigSource.LOWCODE,
        scope: "" // потом заполни нужными правами
    });

    const container = document.getElementById("vkid-onetap");
    if (!container) return;

    const oneTap = new VKID.OneTap();

    oneTap
        .render({
            container,
            showAlternativeLogin: true
        })
        .on(VKID.WidgetEvents.ERROR, vkidOnError)
        .on(VKID.OneTapInternalEvents.LOGIN_SUCCESS, function (payload) {
            const code = payload.code;
            const deviceId = payload.device_id;

            // Меняем стандартную обработку под твой бэкенд
            VKID.Auth.exchangeCode(code, deviceId)
                .then(vkidOnSuccess)
                .catch(vkidOnError);
        });

    async function vkidOnSuccess(data) {
        // data — это результат от VK (access token + профиль)
        // Здесь ты отправляешь всё на свой backend,
        // чтобы через AuthController/Auth.php создать/найти пользователя.

        try {
            const resp = await fetch("/auth/vk/onetap", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json"
                },
                body: JSON.stringify(data)
            });

            const out = await resp.json().catch(() => ({}));

            if (!resp.ok || out.ok === false) {
                alert(out.error || "Ошибка авторизации через ВКонтакте");
                return;
            }

            // Тут можно:
            // - сохранить в JS prefill-данные (fio, phone, birth_year)
            // - открыть твою дополнительную модалку (Отчество/Адрес/Телефон)
            // - дальше запустить ту же цепочку: капча → модалка голосования
            console.log("VK auth OK, данные:", out);

        } catch (e) {
            console.error("Ошибка /auth/vk/onetap:", e);
            alert("Не удалось завершить авторизацию через ВК");
        }
    }

    function vkidOnError(error) {
        console.error("VKID error:", error);
        alert("Ошибка при авторизации через ВК");
    }
});
