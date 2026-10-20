<?php

namespace App\Filament\Resources\Users;

use App\Enums\Peran;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Pengelolaan akun untuk EMPAT role saja. Akun tidak dihapus, hanya dinonaktifkan,
 * supaya riwayat tindakan (log aktivitas, bimbingan, SK) tetap utuh.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Pengguna';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'pengguna';

    protected static ?string $pluralModelLabel = 'pengguna';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required()->maxLength(255),
            TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('password')
                ->label('Password')
                ->password()
                ->revealable()
                ->minLength(8)
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state))
                ->helperText('Saat mengubah akun, kosongkan bila password tidak diganti.'),
            Select::make('peran')
                ->label('Peran')
                ->options(collect(Peran::cases())->mapWithKeys(fn (Peran $p) => [$p->value => $p->label()])->all())
                ->required()
                ->live()
                ->dehydrated(false) // disimpan lewat syncRoles di halaman Create/Edit
                ->afterStateHydrated(fn (Select $component, ?User $record) => $component->state($record?->peranUtama()?->value)),
            Toggle::make('aktif')
                ->label('Akun aktif')
                ->default(true)
                ->disabled(fn (?User $record) => $record !== null && $record->is(auth()->user()))
                ->helperText('Akun nonaktif tidak dapat login. Anda tidak dapat menonaktifkan akun sendiri.'),
            Toggle::make('semua_prodi')
                ->label('Operator melayani semua program studi')
                ->visible(fn (Get $get) => $get('peran') === Peran::OperatorAkademik->value),
            Select::make('prodiOperator')
                ->label('Cakupan program studi Operator')
                ->relationship('prodiOperator', 'nama')
                ->multiple()
                ->preload()
                ->visible(fn (Get $get) => $get('peran') === Peran::OperatorAkademik->value && ! $get('semua_prodi'))
                ->helperText('Operator tanpa cakupan tidak dapat memproses pengajuan apa pun.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('roles'))
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('roles.name')
                    ->label('Peran')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Peran::tryFrom($state)?->label() ?? $state),
                IconColumn::make('aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('peran')
                    ->label('Peran')
                    ->options(collect(Peran::cases())->mapWithKeys(fn (Peran $p) => [$p->value => $p->label()])->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($q, $v) => $q->role($v))),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
