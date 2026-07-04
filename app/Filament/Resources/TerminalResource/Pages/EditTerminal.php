<?php

namespace App\Filament\Resources\TerminalResource\Pages;

use App\Filament\Resources\TerminalResource;
use App\Filament\Resources\TerminalResource\Pages\Concerns\ErpTerminalFormPage;
use Filament\Resources\Pages\EditRecord;

class EditTerminal extends EditRecord
{
    use ErpTerminalFormPage;

    protected static string $resource = TerminalResource::class;

    public function mount(int | string $record): void
    {
        $this->redirect(TerminalResource::getUrl('index'), navigate: false);
    }
}
