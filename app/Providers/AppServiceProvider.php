<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        Event::listen(RoleAttachedEvent::class, function (RoleAttachedEvent $event): void {
            $this->logRoleChange($event->model, $event->rolesOrIds, 'role_attached');
        });

        Event::listen(RoleDetachedEvent::class, function (RoleDetachedEvent $event): void {
            $this->logRoleChange($event->model, $event->rolesOrIds, 'role_detached');
        });

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });
    }

    /**
     * Record role changes in the audit log (SEC-05, SEC-06).
     */
    private function logRoleChange(Model $subject, mixed $rolesOrIds, string $event): void
    {
        $ids = collect(is_iterable($rolesOrIds) ? $rolesOrIds : [$rolesOrIds])
            ->map(fn (mixed $role): mixed => $role instanceof Role ? $role->getKey() : $role)
            ->all();

        $names = Role::query()->whereIn('id', $ids)->pluck('name')->all();

        activity('roles')
            ->performedOn($subject)
            ->causedBy(auth()->user())
            ->event($event)
            ->withProperties(['roles' => $names])
            ->log($event === 'role_attached' ? 'Role assigned' : 'Role removed');
    }
}
