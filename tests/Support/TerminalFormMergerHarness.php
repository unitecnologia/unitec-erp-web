<?php

namespace Tests\Support;

use App\Filament\Resources\TerminalResource\Pages\Concerns\ManagesTerminalMasterDetail;

class TerminalFormMergerHarness
{
    use ManagesTerminalMasterDetail;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function merge(array $data): array
    {
        return $this->mergeTerminalFormData($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultFormData(): array
    {
        return static::defaultTerminalFormData();
    }
}
