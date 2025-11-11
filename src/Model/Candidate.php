<?php
namespace App\Model;

use App\App\Database;

class Candidate{
    public ?int $id = null;
    public string $surname;
    public string $name;
    public ?string $patronymic = null;
    public string $phone;
    public string $vk_id;
    public int $district_id;
    public ?string $photo = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?string $deleted_at = null;
}
