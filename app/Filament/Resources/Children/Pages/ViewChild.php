<?php

namespace App\Filament\Resources\Children\Pages;

use App\Filament\Resources\Children\ChildResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewChild extends ViewRecord
{
    protected static string $resource = ChildResource::class;

    protected static ?string $title = 'Detail Data Anak';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
