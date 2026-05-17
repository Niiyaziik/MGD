<?php
namespace App\Model;

class Vote extends BaseModel {
    public int $user_id;
    public int $candidate_id;
}
