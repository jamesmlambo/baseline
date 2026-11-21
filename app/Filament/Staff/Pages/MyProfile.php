<?php

declare(strict_types=1);

namespace App\Filament\Staff\Pages;

use App\Models\Employee;
use App\Services\Staff\EmployeeProfiles;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * EMP-01: staff complete their own details; changes wait for a manager's approval.
 *
 * @property-read Schema $form
 */
class MyProfile extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?string $navigationLabel = 'My details';

    protected static ?string $title = 'My details';

    protected static ?string $slug = 'my-profile';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.staff.pages.my-profile';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->employee()->exists() ?? false;
    }

    public function mount(): void
    {
        $employee = $this->employee();
        $this->form->fill(array_merge(
            $employee->only(Employee::SELF_SERVICE_FIELDS),
            array_intersect_key($employee->pending_changes ?? [], array_flip(Employee::SELF_SERVICE_FIELDS)),
        ));
    }

    public function employee(): Employee
    {
        return auth()->user()->employee()->firstOrFail();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Text::make(fn (): string => $this->employee()->changes_submitted_at !== null
                    ? 'Your last changes are waiting for a manager to approve them.'
                    : 'Changes are checked by a manager before they take effect.'),
                TextInput::make('preferred_name')->label('Name you go by')->maxLength(255),
                TextInput::make('phone')->label('Mobile number')->tel()->maxLength(32),
                TextInput::make('emergency_contact_name')->label('Emergency contact')->maxLength(255),
                TextInput::make('emergency_contact_phone')->label('Emergency contact number')->tel()->maxLength(32),
                FileUpload::make('photo')->label('Photo (optional)')->image()->maxSize(4096)->disk('local')->directory('profile-uploads')->visibility('private'),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $photo = filled($data['photo'] ?? null) ? Storage::disk('local')->path($data['photo']) : null;

        try {
            app(EmployeeProfiles::class)->submit($this->employee(), $data, $photo, auth()->user());
        } catch (Throwable $exception) {
            Notification::make()->title('Not sent')->body($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('Sent for approval')->success()->send();
    }
}
