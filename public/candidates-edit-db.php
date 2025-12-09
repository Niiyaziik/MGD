<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактирование кандидата</title>
    <link rel="stylesheet" href="/assets/main.css">
    <style>
        .candidate-edit {
            max-width: 800px;
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
            width: 120px;
            height: 120px;
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
    </style>
</head>
<body>
<div class="site-body">
    <?php include __DIR__ . '/part/sidebar.php'; ?>

    <form class="candidate-edit" action="/candidates/admin/update" method="post" enctype="multipart/form-data">
        <?php
        $id = $_GET['id'] ?? null;
        if ($id): ?>
            <input type="hidden" name="id" value="<?= htmlspecialchars($id, ENT_QUOTES) ?>">
        <?php endif; ?>

        <h1>Редактирование кандидата #<?= htmlspecialchars($id ?? '', ENT_QUOTES) ?></h1>

        <!-- 1. Фамилия -->
        <div class="candidate-edit__field">
            <label for="surname">Фамилия *</label>
            <input type="text" id="surname" name="surname"
                   class="candidate-edit__input"
                   maxlength="30"
                   placeholder="Введите фамилию"
                   pattern="^[А-Яа-яЁё\-]{1,30}$"
                   required>
            <div class="candidate-edit__hint">
                Обязательное поле, до 30 знаков, только русские буквы.
            </div>
        </div>

        <!-- 2. Имя -->
        <div class="candidate-edit__field">
            <label for="name">Имя *</label>
            <input type="text" id="name" name="name"
                   class="candidate-edit__input"
                   maxlength="30"
                   placeholder="Введите имя"
                   pattern="^[А-Яа-яЁё\-]{1,30}$"
                   required>
            <div class="candidate-edit__hint">
                Обязательное поле, до 30 знаков, только русские буквы.
            </div>
        </div>

        <!-- 3. Отчество -->
        <div class="candidate-edit__field">
            <label for="patronymic">Отчество *</label>
            <input type="text" id="patronymic" name="patronymic"
                   class="candidate-edit__input"
                   maxlength="30"
                   placeholder="Введите отчество"
                   pattern="^[А-Яа-яЁё\-]{1,30}$"
                   required>
            <div class="candidate-edit__hint">
                Обязательное поле, до 30 знаков, только русские буквы.
            </div>
        </div>

        <!-- 4. Фото -->
        <div class="candidate-edit__field">
            <label>Фотография *</label>
            <img src="/assets/img/candidates/placeholder.jpeg"
                 alt="Фото кандидата"
                 class="candidate-edit__photo-preview"
                 id="photo-preview">
            <input type="file" name="photo" id="photo"
                   accept="image/*"
                   required>
            <div class="candidate-edit__hint">
                Обязательное поле. Фото до 2 МБ, желательно квадратное (кадрирование при загрузке).
            </div>
        </div>

        <!-- 5. Текст про кандидата -->
        <div class="candidate-edit__field">
            <label for="about">Текст про кандидата</label>
            <textarea id="about" name="about"
                      class="candidate-edit__textarea"
                      maxlength="3000"
                      placeholder="Текст про кандидата"></textarea>
            <div class="char-counter" id="about-counter">0 / 3000</div>
            <div class="candidate-edit__hint">
                До 3000 знаков, любые символы.
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
    document.getElementById('photo').addEventListener('change', function (e) {
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
    about.addEventListener('input', () => {
        aboutCounter.textContent = `${about.value.length} / 3000`;
    });
</script>
</body>
</html>
