document.addEventListener('DOMContentLoaded', () => {
    const upload = document.getElementById('photo-upload');
    const input = document.getElementById('photo-input');
    const preview = document.getElementById('photo-preview');
    const changeBtn = document.getElementById('photo-change-btn');
    const placeholderSrc = '/assets/img/candidates/placeholder.jpeg';

    function openDialog() {
        input.click();
    }

    // клик по всей области с фото
    upload.addEventListener('click', (e) => {
        // если нажали именно кнопку "Изменить фото" — тоже просто открываем диалог
        openDialog();
    });

    // когда выбрали файл
    input.addEventListener('change', () => {
        const file = input.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = (e) => {
            preview.src = e.target.result;      // показываем выбранное фото
            upload.classList.add('has-image');  // включаем кнопку "Изменить фото"
        };
        reader.readAsDataURL(file);
    });
});
