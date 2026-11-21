<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Staff\SyncStaffRoles;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $roleNames = $data['role_names'] ?? [];
        unset($data['role_names']);

        return DB::transaction(function () use ($data, $roleNames): User {
            $user = User::query()->create($data);
            app(SyncStaffRoles::class)->handle($user, array_values($roleNames), auth()->user(), 'data.role_names');

            return $user;
        });
    }
}
