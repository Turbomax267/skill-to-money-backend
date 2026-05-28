<?php

namespace App\Repositories;

use App\Contracts\Repositories\ProfileRepositoryInterface;
use App\Models\ProfessionalProfile;
use App\Models\User;

class EloquentProfileRepository implements ProfileRepositoryInterface
{
    public function findByUser(User $user): ?ProfessionalProfile
    {
        return ProfessionalProfile::query()->where('user_id', $user->id)->first();
    }

    public function saveForUser(User $user, array $data): ProfessionalProfile
    {
        return ProfessionalProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            $data
        );
    }
}
