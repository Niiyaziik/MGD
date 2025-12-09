<?php
/** @var array $candidateData */
$c = $candidateData ?? [];
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактирование кандидата #<?= e($c['id'] ?? '') ?></title>
    <link rel="stylesheet" href="/assets/main.css">
    <style>
        .candidate-edit {
            max-width: 900px;
            margin: 20px auto;
            background: #fff;
            padding: 20px 24px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        }
        .candidate-edit h1 {
            margin-bottom: 16px;
        }
        .candidate-edit__field {
            margin-bottom: 14px;
        }
        .candidate-edit__field label {
            display: block;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .candidate-edit__hint {
            font-size: 12px;
            color: #777;
            margin-top: 2px;
        }
        .candidate-edit__input,
        .candidate-edit__textarea {
            width: 100%;
            padding: 6px 8px;
            border-radius: 4px;
            border: 1px solid #b7c2d0;
            box-sizing: border-box;
            font-size: 14px;
        }
        .candidate-edit__textarea {
            min-height: 140px;
            resize: vertical;
        }
        .candidate-edit__photo-preview {
            width: 140px;
            height: 140px;
            border-radius: 8px;
            border: 1px solid #ccc;
            object-fit: cover;
            display: block;
            margin-bottom: 8px;
        }
        .candidate-edit__actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        .btn-primary {
            background: #3654db;
            color: #fff;
            border-radius: 4px;
            padding: 8px 16px;
            border: none;
            cursor: pointer;
        }
        .btn-secondary {
            background: #e0e4f0;
            color: #333;
            border-radius: 4px;
            padding: 8px 16px;
            border: none;
            cursor: pointer;
        }
        .char-counter {
            font-size: 12px;
            text-align: right;
            color: #777;
        }
        .candidate-edit__grid {
            display: grid;
            grid-template-columns: 1.1fr 1.9fr;
            gap: 16px 24px;
            align-items: flex-start;
        }
        @media (max-width: 768px) {
            .candidate-edit__grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="site-body">
    <?php include __DIR__ . '/part/sidebar.php'; ?>

    <form
        class="candidate-edit"
        action="/candidates/<?= e($c['id'] ?? 0) ?>/update"
        method="post"
        enctype="multipart/form-data"
    >
        <h1>Редактирование кандидата #<?= e($c['id'] ?? '') ?></h1>

        <div class="candidate-edit__grid">
            <div>
                <!-- ФИО -->
                <div class="candidate-edit__field">
                    <label for="surname">Фамилия *</label>
                    <input type="text"
                           id="surname"
                           name="surname"
                           class="candidate-edit__input"
                           maxlength="30"
                           placeholder="Введите фамилию"
                           pattern="^[А-Яа-яЁё\\-]{1,30}$"
                           required
                           value="<?= e($c['surname'] ?? '') ?>">
                    <div class="candidate-edit__hint">
                        Обязательное поле, до 30 знаков, только русские буквы.
                    </div>
                </div>

                <div class="candidate-edit__field">
                    <label for="name">Имя *</label>
                    <input type="text"
                           id="name"
                           name="name"
                           class="candidate-edit__input"
                           maxlength="30"
                           placeholder="Введите имя"
                           pattern="^[А-Яа-яЁё\\-]{1,30}$"
                           required
                           value="<?= e($c['name'] ?? '') ?>">
                    <div class="candidate-edit__hint">
                        Обязательное поле, до 30 знаков, только русские буквы.
                    </div>
                </div>

                <div class="candidate-edit__field">
                    <label for="patronymic">Отчество *</label>
                    <input type="text"
                           id="patronymic"
                           name="patronymic"
                           class="candidate-edit__input"
                           maxlength="30"
                           placeholder="Введите отчество"
                           pattern="^[А-Яа-яЁё\\-]{1,30}$"
                           required
                           value="<?= e($c['patronymic'] ?? '') ?>">
                    <div class="candidate-edit__hint">
                        Обязательное поле, до 30 знаков, только русские буквы.
                    </div>
                </div>

                <!-- Телефон и VK -->
                <div class="candidate-edit__field">
                    <label for="phone">Телефон</label>
                    <input type="text"
                           id="phone"
                           name="phone"
                           class="candidate-edit__input"
                           placeholder="+7 (___) ___-__-__"
                           value="<?= e($c['phone'] ?? '') ?>">
                    <div class="candidate-edit__hint">
                        Формат телефона будет проверяться на сервере.
                    </div>
                </div>

                <div class="candidate-edit__field">
                    <label for="link_vk">Ссылка на VK</label>
                    <input type="url"
                           id="link_vk"
                           name="link_vk"
                           class="candidate-edit__input"
                           placeholder="https://vk.com/..."
                           value="<?= e($c['link_vk'] ?? '') ?>">
                </div>

                <!-- Улица / Дом / Округ — пока как текст/ID -->
                <div class="candidate-edit__field">
                    <label for="street_id">Улица</label>
                    <input type="text"
                           id="street_id"
                           name="street_id"
                           class="candidate-edit__input"
                           value="<?= e($c['street_id'] ?? '') ?>">
                    <div class="candidate-edit__hint">
                        Здесь можно вывести название улицы или ID — как хранится в БД.
                    </div>
                </div>

                <div class="candidate-edit__field">
                    <label for="house_id">Дом</label>
                    <input type="text"
                           id="house_id"
                           name="house_id"
                           class="candidate-edit__input"
                           value="<?= e($c['house_id'] ?? '') ?>">
                </div>

                <div class="candidate-edit__field">
                    <label for="district_id">Округ</label>
                    <input type="text"
                           id="district_id"
                           name="district_id"
                           class="candidate-edit__input"
                           value="<?= e($c['district_id'] ?? '') ?>">
                </div>
            </div>

            <div>
                <!-- Фото -->
                <div class="candidate-edit__field">
                    <label>Фотография *</label>
                    <?php
                    $photo = $c['photo'] ?? '';
                    $photoUrl = $photo ? $photo : '/assets/img/candidates/placeholder.jpeg';
                    ?>
                    <img src="<?= e($photoUrl) ?>"
                         alt="Фото кандидата"
                         class="candidate-edit__photo-preview"
                         id="photo-preview">
                    <input type="file"
                           name="photo"
                           id="photo"
                           accept="image/*">
                    <div class="candidate-edit__hint">
                        Фото до 2 МБ, желательно квадратное. Кадрирование при загрузке выполняется на сервере.
                    </div>
                </div>

                <!-- Текст про кандидата -->
                <div class="candidate-edit__field">
                    <label for="about">Текст про кандидата</label>
                    <textarea
                        id="about"
                        name="about"
                        class="candidate-edit__textarea"
                        maxlength="3000"
                        placeholder="Текст про кандидата"
                    ><?= e($c['about'] ?? '') ?></textarea>
                    <div class="char-counter" id="about-counter">
                        <?= strlen((string)($c['about'] ?? '')) ?> / 3000
                    </div>
                    <div class="candidate-edit__hint">
                        До 3000 знаков, любые символы.
                    </div>
                </div>
            </div>
        </div>

        <div class="candidate-edit__actions">
            <button type="submit" class="btn-primary">Сохранить</button>
            <button type="button" class="btn-secondary" onclick="history.back()">Отмена</button>
        </div>
    </form>
</div>

<script>
    // превью фото
    document.getElementById('photo')?.addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (!file) return;
        if (file.size > 2 * 1024 * 1024) {
            alert('Размер файла больше 2 МБ');
            e.target.value = "";
            return;
        }
        const reader = new FileReader();
        reader.onload = function (ev) {
            document.getElementById('photo-preview').src = ev.target.result;
        };
        reader.readAsDataURL(file);
    });

    // счётчик символов
    const about = document.getElementById('about');
    const aboutCounter = document.getElementById('about-counter');
    if (about && aboutCounter) {
        const updateCounter = () => {
            aboutCounter.textContent = `${about.value.length} / 3000`;
        };
        about.addEventListener('input', updateCounter);
        updateCounter();
    }
</script>
</body>
</html>
