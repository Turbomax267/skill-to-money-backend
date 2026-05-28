<?php

namespace App\Contracts\Repositories;

use App\Models\ProfessionalProfile;
use App\Models\User;

interface ProfileRepositoryInterface
{
    public function findByUser(User $user): ?ProfessionalProfile;

    public function saveForUser(User $user, array $data): ProfessionalProfile;
}
