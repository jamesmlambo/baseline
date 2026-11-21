<?php

declare(strict_types=1);

namespace App\Filament\Resources\Employees;

use App\Enums\EmployeeStatus;
use App\Filament\Actions\PaymentActions;
use App\Filament\Resources\Employees\Pages\ManageEmployees;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Services\Staff\Attendance\PunchRecorder;
use App\Services\Staff\EmployeeProfiles;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Employee profiles (EMP-01). The owner and managers only (EMP-03).
 */
class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 10;

    public static function getNavigationBadge(): ?string
    {
        $waiting = Employee::query()->where(fn (Builder $query) => $query->where('status', EmployeeStatus::Pending)->orWhereNotNull('changes_submitted_at'))->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Profiles waiting for approval';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Employee')->columns(2)->schema([
                TextInput::make('full_name')->required()->maxLength(255),
                TextInput::make('preferred_name')->maxLength(255),
                TextInput::make('employee_number')->maxLength(32)->unique(ignoreRecord: true),
                TextInput::make('job_title')->maxLength(255),
                Select::make('department_id')->label('Department')->options(fn (): array => Department::query()->orderBy('name')->pluck('name', 'id')->all())->searchable(),
                Select::make('user_id')->label('Login account')
                    ->options(fn (?Employee $record): array => User::query()
                        ->where(fn (Builder $query) => $query->whereDoesntHave('employee')->when($record?->user_id, fn (Builder $inner) => $inner->orWhere('id', $record->user_id)))
                        ->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->helperText('Lets them see their own shifts, leave and attendance in /staff.'),
                DatePicker::make('starts_on'),
                DatePicker::make('ends_on')->afterOrEqual('starts_on'),
                Select::make('status')->options(EmployeeStatus::options())->default(EmployeeStatus::Pending->value)->required(),
                SpatieMediaLibraryFileUpload::make('photo')->collection('photo')->disk('local')->visibility('private')->image()->maxSize(4096),
            ]),
            Section::make('Contact (encrypted)')->columns(2)->schema([
                TextInput::make('phone')->tel()->maxLength(32),
                TextInput::make('emergency_contact_name')->maxLength(255),
                TextInput::make('emergency_contact_phone')->tel()->maxLength(32),
            ]),
            Section::make('Clocking')->schema([
                TextInput::make('device_person_id')->label('ID on the clock terminal')
                    ->helperText('The person / employee number used on the Hikvision terminal or in CSV exports. Earlier unmatched punches are linked when you save.')
                    ->maxLength(64)
                    ->unique(ignoreRecord: true),
                Textarea::make('notes')->rows(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['department', 'user']))
            ->defaultSort('full_name')
            ->columns([
                TextColumn::make('full_name')->label('Name')->searchable()->description(fn (Employee $record): ?string => $record->job_title),
                TextColumn::make('department.name')->label('Department')->placeholder('—'),
                TextColumn::make('user.email')->label('Login')->placeholder('No login'),
                TextColumn::make('device_person_id')->label('Clock ID')->placeholder('—'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (EmployeeStatus $state): string => $state->label())
                    ->color(fn (EmployeeStatus $state): string => $state->color()),
                TextColumn::make('changes_submitted_at')->label('Changes to approve')->since()->placeholder('—')->color('warning'),
            ])
            ->filters([
                SelectFilter::make('status')->options(EmployeeStatus::options()),
                SelectFilter::make('department_id')->label('Department')->relationship('department', 'name'),
            ])
            ->recordActions([
                Action::make('review')
                    ->label('Review')
                    ->icon('heroicon-o-check-badge')
                    ->color('warning')
                    ->visible(fn (Employee $record): bool => $record->status === EmployeeStatus::Pending || $record->changes_submitted_at !== null)
                    ->authorize(fn (Employee $record): bool => auth()->user()->can('update', $record))
                    ->modalHeading(fn (Employee $record): string => 'Approve '.$record->displayName())
                    ->schema(fn (Employee $record): array => [
                        TextEntry::make('changes')->label('Changes the employee asked for')->listWithLineBreaks()->placeholder('No changes — new profile only')
                            ->state(collect($record->pending_changes ?? [])->map(fn (mixed $value, string $field): string => str_replace('_', ' ', ucfirst($field)).': '.($field === 'photo' ? 'new photo' : ($value ?? '(remove)')))->values()->all()),
                    ])
                    ->modalSubmitActionLabel('Approve')
                    ->action(fn (Employee $record) => PaymentActions::run(fn () => app(EmployeeProfiles::class)->approve($record, auth()->user()), 'Approved')),
                Action::make('rejectChanges')
                    ->label('Don\'t accept changes')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn (Employee $record): bool => $record->changes_submitted_at !== null)
                    ->authorize(fn (Employee $record): bool => auth()->user()->can('update', $record))
                    ->schema([Textarea::make('reason')->label('Tell them why')->required()->rows(2)])
                    ->action(fn (Employee $record, array $data) => PaymentActions::run(fn () => app(EmployeeProfiles::class)->reject($record, $data['reason'], auth()->user()), 'Changes not accepted')),
                EditAction::make()
                    ->after(fn (Employee $record) => app(PunchRecorder::class)->linkUnmatched($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmployees::route('/'),
        ];
    }
}
