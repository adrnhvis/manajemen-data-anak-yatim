<?php

namespace App\Filament\Resources\Children;

use App\Filament\Resources\Children\Pages\CreateChild;
use App\Filament\Resources\Children\Pages\EditChild;
use App\Filament\Resources\Children\Pages\ListChildren;
use App\Filament\Resources\Children\Pages\ViewChild;
use App\Filament\Resources\Children\RelationManagers\ApplicationsRelationManager;
use App\Filament\Resources\Children\RelationManagers\RelationshipsRelationManager;
use App\Filament\Resources\Children\Schemas\ChildForm;
use App\Filament\Resources\Children\Schemas\ChildInfolist;
use App\Filament\Resources\Children\Tables\ChildrenTable;
use App\Models\Child;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ChildResource extends Resource
{
    protected static ?string $model = Child::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Data Anak';

    protected static ?string $modelLabel = 'Data Anak';

    protected static ?string $pluralModelLabel = 'Data Anak';

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        if (! $user) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->forUser($user)
            ->with(['household', 'address.village.district', 'latestApplication', 'relationships.person']);
    }

    public static function form(Schema $schema): Schema
    {
        return ChildForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ChildInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChildrenTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationshipsRelationManager::class,
            ApplicationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChildren::route('/'),
            'create' => CreateChild::route('/create'),
            'view' => ViewChild::route('/{record}'),
            'edit' => EditChild::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
