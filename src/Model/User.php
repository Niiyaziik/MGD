<?php
namespace App\Model;

class User {
    public ?int $id = null;
    public string $surname;
    public string $name;
    public ?string $patronymic = null;
    public string $phone;
    public string $link_vk;
    public ?int $district_id = null;
    public string $auth_method; // 'ВК', 'МАКС', 'Телефон'
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?string $deleted_at = null;
}
