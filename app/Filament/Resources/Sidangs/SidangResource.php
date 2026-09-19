<?php

namespace App\Filament\Resources\Sidangs;

use App\Filament\Resources\Sidangs\Pages\ListSidangs;
use App\Filament\Resources\Sidangs\Tables\SidangsTable;
use App\Models\Sidang;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Panel oversight (baca-saja) bagi Administrator. Siklus hidup sebuah
 * sidang sepenuhnya dikelola melalui SidangWorkflowService dan dashboard
 * masing-masing peran, sehingga resource ini tidak menyediakan aksi
 * Create/Edit manual agar mesin status (SidangStatus) tetap konsisten
 * dan setiap perubahan tetap tercatat di sidang_riwayats.
 */
class SidangResource extends Resource
{
    protected static ?string $model = Sidang::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'id';

    public static function table(Table $table): Table
    {
        return SidangsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSidangs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
