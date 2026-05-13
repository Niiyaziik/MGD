// photoDialog.js
// Кадрирование с фиксированной рамкой 1 : 1.5 (ширина : высота = 2 : 3)
// - Рамка по центру модалки
// - Изначально фото полностью помещается в рамку (fit)
// - Перетаскивание двигает фото
// - Масштаб регулируется колесиком мыши или pinch-zoom на телефоне
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
            if (!file.type.startsWith("image/")) {
                showMessage("Выберите изображение.", "Ошибка");
                return;
            }
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

    const ZOOM_STEP = 0.1;
    const WHEEL_ZOOM_SENSITIVITY = 0.001;

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

        // для pinch-zoom
        initialDistance: 0,
        initialScale: 1,
        isPinching: false,

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
            showMessage("Выберите изображение.", "Ошибка");
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

                // Изначально фото полностью помещается в рамку (fit)
                initFitToFrame();

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

    // Изначально фото полностью помещается в рамку (fit)
    function initFitToFrame() {
        const iw = state.img.naturalWidth || state.img.width;
        const ih = state.img.naturalHeight || state.img.height;

        const { w: fw, h: fh } = state.frame;

        // при scale=1 изображение на canvas имеет размер iw*baseScale, ih*baseScale
        const imgW1 = iw * state.baseScale;
        const imgH1 = ih * state.baseScale;

        // нужен такой scale, чтобы картинка полностью помещалась в рамку (fit)
        const needW = fw / imgW1;
        const needH = fh / imgH1;

        // выбираем меньший масштаб, чтобы фото полностью поместилось
        const fitScale = Math.min(needW, needH);

        // Минимальный масштаб = fitScale (нельзя отдалить дальше, чем само фото)
        state.minScale = fitScale;
        state.maxScale = Math.max(fitScale * 5, 6); // можно увеличить в 5 раз

        // устанавливаем начальный масштаб так, чтобы фото поместилось
        state.scale = fitScale;
        state.dx = 0;
        state.dy = 0;

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

    // ограничиваем dx/dy, чтобы изображение не выходило за рамку, но можно двигать внутри рамки
    function clampOffsetToFrame() {
        const { x: fx, y: fy, w: fw, h: fh } = state.frame;

        const iw = state.img.naturalWidth || state.img.width;
        const ih = state.img.naturalHeight || state.img.height;

        const scaleUI = state.baseScale * state.scale;
        const imgW = iw * scaleUI;
        const imgH = ih * scaleUI;

        // где был бы левый/верхний край изображения при dx=0,dy=0 (центрирование)
        const baseX = (canvas.width - imgW) / 2;
        const baseY = (canvas.height - imgH) / 2;

        // dx ограничиваем так, чтобы рамка была внутри изображения:
        // imgLeft <= frameLeft  и  imgRight >= frameRight
        if (imgW > fw) {
            const minDx = (fx + fw) - (baseX + imgW); // самый "влево" (чтобы правый край изображения дошёл до правого края рамки)
            const maxDx = fx - baseX;                 // самый "вправо" (чтобы левый край изображения дошёл до левого края рамки)
            state.dx = Math.max(minDx, Math.min(maxDx, state.dx));
        } else {
            state.dx = 0;
        }

        if (imgH > fh) {
            const minDy = (fy + fh) - (baseY + imgH);
            const maxDy = fy - baseY;
            state.dy = Math.max(minDy, Math.min(maxDy, state.dy));
        } else {
            state.dy = 0;
        }
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
    }

    // Вычисляет расстояние между двумя точками
    function getDistance(x1, y1, x2, y2) {
        return Math.sqrt((x2 - x1) ** 2 + (y2 - y1) ** 2);
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

    function zoom(delta, centerX = null, centerY = null) {
        const oldScale = state.scale;
        const newScale = Math.max(
            state.minScale,
            Math.min(state.maxScale, +(state.scale + delta).toFixed(2))
        );

        // Если указан центр зума, корректируем смещение для масштабирования относительно центра
        if (centerX !== null && centerY !== null && oldScale !== newScale) {
            const scaleChange = newScale / oldScale;
            const rect = canvas.getBoundingClientRect();
            const canvasX = (centerX - rect.left) * (canvas.width / rect.width);
            const canvasY = (centerY - rect.top) * (canvas.height / rect.height);

            // Позиция изображения до зума
            const iw = state.img.naturalWidth || state.img.width;
            const ih = state.img.naturalHeight || state.img.height;
            const oldScaleUI = state.baseScale * oldScale;
            const oldImgW = iw * oldScaleUI;
            const oldImgH = ih * oldScaleUI;
            const oldImgX = (canvas.width - oldImgW) / 2 + state.dx;
            const oldImgY = (canvas.height - oldImgH) / 2 + state.dy;

            // Относительная позиция точки зума в изображении
            const relX = (canvasX - oldImgX) / oldImgW;
            const relY = (canvasY - oldImgY) / oldImgH;

            // Новая позиция изображения после зума
            const newScaleUI = state.baseScale * newScale;
            const newImgW = iw * newScaleUI;
            const newImgH = ih * newScaleUI;
            const newImgX = canvasX - relX * newImgW;
            const newImgY = canvasY - relY * newImgH;

            // Новое смещение
            state.dx = newImgX - (canvas.width - newImgW) / 2;
            state.dy = newImgY - (canvas.height - newImgH) / 2;
        }

        state.scale = newScale;
        clampOffsetToFrame();
        draw();
    }

    // мышь: drag
    canvas.addEventListener("mousedown", (e) => {
        e.preventDefault();
        state.isDragging = true;
        state.isPinching = false;
        state.lastX = e.clientX;
        state.lastY = e.clientY;
    });

    window.addEventListener("mouseup", () => {
        state.isDragging = false;
        state.isPinching = false;
    });

    window.addEventListener("mousemove", (e) => {
        if (!state.isDragging || state.isPinching) return;
        e.preventDefault();
        const dx = e.clientX - state.lastX;
        const dy = e.clientY - state.lastY;
        state.lastX = e.clientX;
        state.lastY = e.clientY;
        state.dx += dx;
        state.dy += dy;
        clampOffsetToFrame();
        draw();
    });

    // Масштабирование колесиком мыши
    canvas.addEventListener("wheel", (e) => {
        e.preventDefault();
        const delta = -e.deltaY * WHEEL_ZOOM_SENSITIVITY * state.scale;
        zoom(delta, e.clientX, e.clientY);
    }, { passive: false });

    // touch: drag или pinch-zoom
    canvas.addEventListener(
        "touchstart",
        (e) => {
            if (e.touches.length === 1) {
                // Один палец - перетаскивание
                const t = e.touches[0];
                state.isDragging = true;
                state.isPinching = false;
                state.lastX = t.clientX;
                state.lastY = t.clientY;
            } else if (e.touches.length === 2) {
                // Два пальца - pinch-zoom
                e.preventDefault();
                state.isDragging = false;
                state.isPinching = true;
                const t1 = e.touches[0];
                const t2 = e.touches[1];
                state.initialDistance = getDistance(t1.clientX, t1.clientY, t2.clientX, t2.clientY);
                state.initialScale = state.scale;
            }
        },
        { passive: false }
    );

    canvas.addEventListener(
        "touchmove",
        (e) => {
            if (e.touches.length === 1 && state.isDragging && !state.isPinching) {
                // Один палец - перетаскивание
                const t = e.touches[0];
                const dx = t.clientX - state.lastX;
                const dy = t.clientY - state.lastY;
                state.lastX = t.clientX;
                state.lastY = t.clientY;
                state.dx += dx;
                state.dy += dy;
                clampOffsetToFrame();
                draw();
            } else if (e.touches.length === 2 && state.isPinching) {
                // Два пальца - pinch-zoom
                e.preventDefault();
                const t1 = e.touches[0];
                const t2 = e.touches[1];
                const currentDistance = getDistance(t1.clientX, t1.clientY, t2.clientX, t2.clientY);

                if (state.initialDistance > 0) {
                    const scaleChange = currentDistance / state.initialDistance;
                    const newScale = state.initialScale * scaleChange;
                    const delta = newScale - state.scale;

                    // Центр pinch-zoom
                    const centerX = (t1.clientX + t2.clientX) / 2;
                    const centerY = (t1.clientY + t2.clientY) / 2;

                    zoom(delta, centerX, centerY);
                }
            }
        },
        { passive: false }
    );

    window.addEventListener("touchend", () => {
        state.isDragging = false;
        state.isPinching = false;
        state.initialDistance = 0;
    });

    // закрытие модалки
    btnClose?.addEventListener("click", closeModal);
    btnCancel?.addEventListener("click", closeModal);
    backdrop?.addEventListener("click", closeModal);

    // Apply: экспортируем область рамки 2:3 в оригинальном качестве
    btnApply.addEventListener("click", async () => {
        const blob = await exportFrameAsBlob();
        if (!blob) {
            showMessage("Не удалось обработать изображение.", "Ошибка");
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
