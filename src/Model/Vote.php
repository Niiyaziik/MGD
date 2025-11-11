<?php
namespace App\Model;

class Vote extends BaseModel {
    public ?int $id = null;
    public int $user_id;
    public int $candidate_id;
    public ?string $created_at = null;
    public ?string $deleted_at = null;
}
