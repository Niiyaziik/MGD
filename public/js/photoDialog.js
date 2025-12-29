// photoDialog.js
// Кадрирование с фиксированной рамкой 1 : 1.5 (ширина : высота = 2 : 3)
// - Рамка по центру модалки
// - Изначально "как есть" (scale = 1, dx = 0, dy = 0)
// - Перетаскивание двигает фото
// - + / - кнопки рисуются ПРЯМО на фото (внутри canvas справа)
// - Apply сохраняет ТОЛЬКО область рамки (2:3) в полном качестве

document.addEventListener("DOMContentLoaded", () => {
    const upload = document.getElementById("photo-upload");
    const input = document.getElementById("photo-input");
    const preview = document.getElementById("photo-preview");
    const changeBtn = document.getElementById("photo-change-btn");

    if (!upload || !input || !preview) return;

    const modal = document.getElementById("cropper-modal");
    const canvas = document.getElementById("cropper-canvas");
    const ctx = canvas?.getContext("2d");

    const btnApply = document.getElementById("cropper-apply");
    const btnCancel = document.getElementById("cropper-cancel");
    const btnClose = document.getElementById("cropper-close");
    const backdrop = modal?.querySelector(".cropper-modal__backdrop");

    const cropperAvailable = !!(modal && canvas && ctx && btnApply);

    // Если модалки нет — просто ставим превью выбранного файла
    if (!cropperAvailable) {
        upload.addEventListener("click", () => {
            input.value = "";
            input.click();
        });
        if (changeBtn) {
            changeBtn.addEventListener("click", (e) => {
                e.preventDefault();
                e.stopPropagation();
                input.value = "";
                input.click();
            });
        }
        input.addEventListener("change", () => {
            const file = input.files?.[0];
            if (!file) return;
            if (!file.type.startsWith("image/")) return alert("Выберите изображение.");
            const url = URL.createObjectURL(file);
            preview.src = url;
            upload.classList.add("has-image");
        });
        return;
    }

    // UI max размеры области кадрирования на экране
    const UI_MAX_W = 560;
    const UI_MAX_H = 560;

    // Рамка 1 : 1.5 => 2 : 3 (ширина : высота)
    const FRAME_RATIO_W = 2;
    const FRAME_RATIO_H = 3;
    const FRAME_PADDING = 18;

    // UI кнопки +/- внутри canvas
    const btnUI = { size: 40, pad: 12, gap: 10, radius: 12 };
    const ZOOM_STEP = 0.12;

    const state = {
        img: null,

        // baseScale — как исходник вписан в UI-canvas
        baseScale: 1,

        // scale — пользовательский зум относительно baseScale
        scale: 1,
        minScale: 0.1,
        maxScale: 6,

        // смещение изображения в UI px
        dx: 0,
        dy: 0,

        // рамка кадрирования в UI px
        frame: { x: 0, y: 0, w: 0, h: 0 },

        isDragging: false,
        lastX: 0,
        lastY: 0,

        objectUrl: null,
    };

    function openFileDialog() {
        input.value = ""; // чтобы change сработал даже при выборе того же файла
        input.click();
    }

    upload.addEventListener("click", (e) => {
        e.preventDefault();
        openFileDialog();
    });

    if (changeBtn) {
        changeBtn.addEventListener("click", (e) => {
            e.preventDefault();
            e.stopPropagation();
            openFileDialog();
        });
    }

    input.addEventListener("change", () => {
        const file = input.files?.[0];
        if (!file) return;

        if (!file.type.startsWith("image/")) {
            alert("Выберите изображение.");
            input.value = "";
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                state.img = img;

                setupCanvasForImage(img);
                state.frame = computeFrame(canvas);

                initAsIs();
                // ВАЖНО: чтобы рамка была заполнена (без пустот) — поднимем minScale и при необходимости scale
                ensureCoverFrame();

                openModal();
                draw();
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });

    function setupCanvasForImage(img) {
        const iw = img.naturalWidth || img.width;
        const ih = img.naturalHeight || img.height;

        // UI canvas выбираем так, чтобы влезало в модалку
        const ratio = Math.min(UI_MAX_W / iw, UI_MAX_H / ih, 1);

        const cw = Math.max(320, Math.round(iw * ratio));
        const ch = Math.max(320, Math.round(ih * ratio));

        canvas.width = cw;
        canvas.height = ch;

        state.baseScale = cw / iw;
    }

    // рамка 2:3 по центру canvas, максимально большая
    function computeFrame(cnv) {
        const cw = cnv.width;
        const ch = cnv.height;

        const maxW = cw - FRAME_PADDING * 2;
        const maxH = ch - FRAME_PADDING * 2;

        let frameW = maxW;
        let frameH = (frameW * FRAME_RATIO_H) / FRAME_RATIO_W;

        if (frameH > maxH) {
            frameH = maxH;
            frameW = (frameH * FRAME_RATIO_W) / FRAME_RATIO_H;
        }

        frameW = Math.round(frameW);
        frameH = Math.round(frameH);

        return {
            x: Math.round((cw - frameW) / 2),
            y: Math.round((ch - frameH) / 2),
            w: frameW,
            h: frameH,
        };
    }

    function initAsIs() {
        state.scale = 0.1; // как есть
        state.dx = 0;
        state.dy = 0;
        state.minScale = 0.1;
        state.maxScale = 6;
    }

    // чтобы рамка была заполнена, задаём минимальный масштаб покрытия рамки
    function ensureCoverFrame() {
        const iw = state.img.naturalWidth || state.img.width;
        const ih = state.img.naturalHeight || state.img.height;

        const { w: fw, h: fh } = state.frame;

        // при scale=1 изображение на canvas имеет размер iw*baseScale, ih*baseScale
        const imgW1 = iw * state.baseScale;
        const imgH1 = ih * state.baseScale;

        // нужен такой scale, чтобы картинка покрывала рамку
        const needW = fw / imgW1;
        const needH = fh / imgH1;

        const minCover = Math.max(needW, needH);

        state.minScale = Math.max(0.1, minCover);
        state.maxScale = Math.max(state.minScale * 3, 6);

        // "как есть", если хватает, иначе увеличим до minScale
        state.scale = Math.max(1, state.minScale);

        clampOffsetToFrame();
    }

    function openModal() {
        modal.hidden = false;
        document.body.style.overflow = "hidden";
    }

    function closeModal() {
        modal.hidden = true;
        document.body.style.overflow = "";
        state.isDragging = false;
    }

    // ограничиваем dx/dy, чтобы рамка всегда была покрыта изображением (без пустот)
    function clampOffsetToFrame() {
        const { x: fx, y: fy, w: fw, h: fh } = state.frame;

        const iw = state.img.naturalWidth || state.img.width;
        const ih = state.img.naturalHeight || state.img.height;

        const scaleUI = state.baseScale * state.scale;
        const imgW = iw * scaleUI;
        const imgH = ih * scaleUI;

        const baseX = (canvas.width - imgW) / 2;
        const baseY = (canvas.height - imgH) / 2;

        const minDx = (fx + fw) - (baseX + imgW);
        const maxDx = fx - baseX;

        const minDy = (fy + fh) - (baseY + imgH);
        const maxDy = fy - baseY;

        state.dx = Math.max(minDx, Math.min(maxDx, state.dx));
        state.dy = Math.max(minDy, Math.min(maxDy, state.dy));
    }

    function draw() {
        if (!state.img) return;

        const cw = canvas.width;
        const ch = canvas.height;

        ctx.clearRect(0, 0, cw, ch);
        ctx.fillStyle = "#f3f4f6";
        ctx.fillRect(0, 0, cw, ch);

        const iw = state.img.naturalWidth || state.img.width;
        const ih = state.img.naturalHeight || state.img.height;

        const scaleUI = state.baseScale * state.scale;
        const w = iw * scaleUI;
        const h = ih * scaleUI;

        const x = (cw - w) / 2 + state.dx;
        const y = (ch - h) / 2 + state.dy;

        ctx.drawImage(state.img, x, y, w, h);

        // затемнение вокруг рамки (как в редакторах)
        const { x: fx, y: fy, w: fw, h: fh } = state.frame;
        ctx.save();
        ctx.fillStyle = "rgba(0,0,0,0.35)";
        ctx.beginPath();
        ctx.rect(0, 0, cw, ch);
        ctx.rect(fx, fy, fw, fh);
        ctx.fill("evenodd");
        ctx.restore();

        // рамка 2:3
        ctx.strokeStyle = "rgba(255,255,255,0.95)";
        ctx.lineWidth = 2;
        ctx.strokeRect(fx + 1, fy + 1, fw - 2, fh - 2);

        // кнопки +/- на фото
        drawZoomButtons();
    }

    // ====== кнопки +/- внутри canvas ======
    function getZoomButtonRects() {
        const cw = canvas.width;
        const ch = canvas.height;

        const size = btnUI.size;
        const pad = btnUI.pad;
        const gap = btnUI.gap;

        const x = cw - pad - size;
        const yPlus = Math.round(ch / 2 - size - gap / 2);
        const yMinus = Math.round(ch / 2 + gap / 2);

        return {
            plus: { x, y: yPlus, w: size, h: size },
            minus: { x, y: yMinus, w: size, h: size },
        };
    }

    function drawRoundedRect(x, y, w, h, r) {
        const rr = Math.min(r, w / 2, h / 2);
        ctx.beginPath();
        ctx.moveTo(x + rr, y);
        ctx.arcTo(x + w, y, x + w, y + h, rr);
        ctx.arcTo(x + w, y + h, x, y + h, rr);
        ctx.arcTo(x, y + h, x, y, rr);
        ctx.arcTo(x, y, x + w, y, rr);
        ctx.closePath();
    }

    function drawZoomButtons() {
        const { plus, minus } = getZoomButtonRects();
        const r = btnUI.radius;

        ctx.save();
        ctx.globalAlpha = 0.95;

        // plus
        ctx.fillStyle = "rgba(255,255,255,0.92)";
        ctx.strokeStyle = "rgba(0,0,0,0.12)";
        ctx.lineWidth = 1;
        drawRoundedRect(plus.x, plus.y, plus.w, plus.h, r);
        ctx.fill();
        ctx.stroke();

        ctx.fillStyle = "#111";
        ctx.font = "700 22px system-ui, -apple-system, Segoe UI, Arial";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillText("+", plus.x + plus.w / 2, plus.y + plus.h / 2 + 1);

        // minus
        ctx.fillStyle = "rgba(255,255,255,0.92)";
        ctx.strokeStyle = "rgba(0,0,0,0.12)";
        ctx.lineWidth = 1;
        drawRoundedRect(minus.x, minus.y, minus.w, minus.h, r);
        ctx.fill();
        ctx.stroke();

        ctx.fillStyle = "#111";
        ctx.font = "700 26px system-ui, -apple-system, Segoe UI, Arial";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillText("−", minus.x + minus.w / 2, minus.y + minus.h / 2 + 1);

        ctx.restore();
    }

    function pointInRect(px, py, r) {
        return px >= r.x && px <= r.x + r.w && py >= r.y && py <= r.y + r.h;
    }

    function getCanvasPoint(clientX, clientY) {
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;
        return {
            x: (clientX - rect.left) * scaleX,
            y: (clientY - rect.top) * scaleY,
        };
    }

    function zoom(delta) {
        state.scale = Math.max(
            state.minScale,
            Math.min(state.maxScale, +(state.scale + delta).toFixed(2))
        );
        clampOffsetToFrame();
        draw();
    }

    // мышь: клик по +/- или drag
    canvas.addEventListener("mousedown", (e) => {
        const p = getCanvasPoint(e.clientX, e.clientY);
        const { plus, minus } = getZoomButtonRects();

        if (pointInRect(p.x, p.y, plus)) return zoom(+ZOOM_STEP);
        if (pointInRect(p.x, p.y, minus)) return zoom(-ZOOM_STEP);

        state.isDragging = true;
        state.lastX = e.clientX;
        state.lastY = e.clientY;
    });

    window.addEventListener("mouseup", () => (state.isDragging = false));

    window.addEventListener("mousemove", (e) => {
        if (!state.isDragging) return;
        const dx = e.clientX - state.lastX;
        const dy = e.clientY - state.lastY;
        state.lastX = e.clientX;
        state.lastY = e.clientY;
        state.dx += dx;
        state.dy += dy;
        clampOffsetToFrame();
        draw();
    });

    // touch: tap по +/- или drag
    canvas.addEventListener(
        "touchstart",
        (e) => {
            if (!e.touches?.[0]) return;
            const t = e.touches[0];
            const p = getCanvasPoint(t.clientX, t.clientY);
            const { plus, minus } = getZoomButtonRects();

            if (pointInRect(p.x, p.y, plus)) return zoom(+ZOOM_STEP);
            if (pointInRect(p.x, p.y, minus)) return zoom(-ZOOM_STEP);

            state.isDragging = true;
            state.lastX = t.clientX;
            state.lastY = t.clientY;
        },
        { passive: true }
    );

    canvas.addEventListener(
        "touchmove",
        (e) => {
            if (!state.isDragging || !e.touches?.[0]) return;
            const t = e.touches[0];
            const dx = t.clientX - state.lastX;
            const dy = t.clientY - state.lastY;
            state.lastX = t.clientX;
            state.lastY = t.clientY;
            state.dx += dx;
            state.dy += dy;
            clampOffsetToFrame();
            draw();
        },
        { passive: true }
    );

    window.addEventListener("touchend", () => (state.isDragging = false));

    // закрытие модалки
    btnClose?.addEventListener("click", closeModal);
    btnCancel?.addEventListener("click", closeModal);
    backdrop?.addEventListener("click", closeModal);

    // Apply: экспортируем область рамки 2:3 в оригинальном качестве
    btnApply.addEventListener("click", async () => {
        const blob = await exportFrameAsBlob();
        if (!blob) {
            alert("Не удалось обработать изображение.");
            return;
        }

        if (state.objectUrl) URL.revokeObjectURL(state.objectUrl);
        state.objectUrl = URL.createObjectURL(blob);
        preview.src = state.objectUrl;
        upload.classList.add("has-image");

        const baseName = (input.files?.[0]?.name || "photo").replace(/\.[^.]+$/, "");
        const file = new File([blob], `${baseName}_cropped_1x1.5.jpg`, { type: "image/jpeg" });

        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;

        closeModal();
    });

    async function exportFrameAsBlob() {
        if (!state.img) return null;

        const iw = state.img.naturalWidth || state.img.width;
        const ih = state.img.naturalHeight || state.img.height;

        const { x: fx, y: fy, w: fw, h: fh } = state.frame;

        const scaleUI = state.baseScale * state.scale;
        const inv = 1 / scaleUI;

        const imgW_UI = iw * scaleUI;
        const imgH_UI = ih * scaleUI;

        const imgX_UI = (canvas.width - imgW_UI) / 2 + state.dx;
        const imgY_UI = (canvas.height - imgH_UI) / 2 + state.dy;

        // рамка в координатах исходного изображения
        let sx = Math.round((fx - imgX_UI) * inv);
        let sy = Math.round((fy - imgY_UI) * inv);
        let sw = Math.round(fw * inv);
        let sh = Math.round(fh * inv);

        // clamp в пределах картинки
        sx = Math.max(0, Math.min(iw - 1, sx));
        sy = Math.max(0, Math.min(ih - 1, sy));
        sw = Math.max(1, Math.min(iw - sx, sw));
        sh = Math.max(1, Math.min(ih - sy, sh));

        const out = document.createElement("canvas");
        out.width = sw;
        out.height = sh;

        const octx = out.getContext("2d");
        if (!octx) return null;

        octx.drawImage(state.img, sx, sy, sw, sh, 0, 0, sw, sh);

        return await new Promise((resolve) => out.toBlob(resolve, "image/jpeg", 0.92));
    }

    modal.addEventListener("click", (e) => e.stopPropagation());
});
