<?php

namespace App\Filament\Resources\TerminalResource\Pages;

use App\Filament\Resources\TerminalResource;
use App\Filament\Resources\TerminalResource\Pages\Concerns\ErpTerminalFormPage;
use Filament\Resources\Pages\CreateRecord;

class CreateTerminal extends CreateRecord
{
    use ErpTerminalFormPage;

    protected static string $resource = TerminalResource::class;

    public function mount(): void
    {
        $this->redirect(TerminalResource::getUrl('index'), navigate: false);
    }
}
