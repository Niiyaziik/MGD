<?php
namespace App\Model;

class Admin extends BaseModel {
    public ?int $id = null;
    public string $login;
    public string $password_hash;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?string $deleted_at = null;
}
