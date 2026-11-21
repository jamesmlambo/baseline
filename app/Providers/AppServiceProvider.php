<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Amenity;
use App\Models\Asset;
use App\Models\AttendanceDay;
use App\Models\AttendanceImport;
use App\Models\AttendancePunch;
use App\Models\CleaningEstimate;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Faq;
use App\Models\GuestMessage;
use App\Models\GuestRequest;
use App\Models\LeaveRequest;
use App\Models\MaintenanceSchedule;
use App\Models\MenuCategoryRule;
use App\Models\PublicHoliday;
use App\Models\RosterChange;
use App\Models\RosterPattern;
use App\Models\Service;
use App\Models\ServiceResource;
use App\Models\Shift;
use App\Models\TaskChecklistTemplate;
use App\Models\User;
use App\Policies\FrontDeskRecordPolicy;
use App\Policies\LeisureCatalogPolicy;
use App\Policies\OperationsRecordPolicy;
use App\Policies\PeopleRecordPolicy;
use App\Policies\WebsiteContentPolicy;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        RateLimiter::for('enquiries', fn (Request $request): Limit => Limit::perMinute(5)->by((string) $request->ip()));

        // Guest portal (GST-02): per-device limits on top of the per-stay limits in PortalAccess.
        RateLimiter::for('portal-codes', fn (Request $request): array => [
            Limit::perMinute(3)->by('portal-codes:'.$request->ip()),
            Limit::perHour(10)->by('portal-codes-hour:'.$request->ip()),
        ]);
        RateLimiter::for('portal-verify', fn (Request $request): Limit => Limit::perMinute(10)->by('portal-verify:'.$request->ip()));
        RateLimiter::for('portal-actions', fn (Request $request): Limit => Limit::perMinute(20)->by('portal-actions:'.$request->session()->getId()));

        Gate::policy(Faq::class, WebsiteContentPolicy::class);
        Gate::policy(Amenity::class, WebsiteContentPolicy::class);
        Gate::policy(GuestRequest::class, FrontDeskRecordPolicy::class);
        Gate::policy(GuestMessage::class, FrontDeskRecordPolicy::class);
        Gate::policy(Asset::class, OperationsRecordPolicy::class);
        Gate::policy(MaintenanceSchedule::class, OperationsRecordPolicy::class);
        Gate::policy(TaskChecklistTemplate::class, OperationsRecordPolicy::class);
        Gate::policy(CleaningEstimate::class, OperationsRecordPolicy::class);

        foreach ([Service::class, ServiceResource::class, MenuCategoryRule::class] as $leisureModel) {
            Gate::policy($leisureModel, LeisureCatalogPolicy::class);
        }

        foreach ([Employee::class, Department::class, Shift::class, RosterPattern::class, RosterChange::class, PublicHoliday::class, LeaveRequest::class, AttendanceDay::class, AttendancePunch::class, AttendanceImport::class] as $peopleModel) {
            Gate::policy($peopleModel, PeopleRecordPolicy::class);
        }

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
