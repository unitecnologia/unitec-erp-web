<?php

namespace App\Filament\Concerns;

use App\Support\Erp\ErpAccess;
use Illuminate\Support\Facades\Auth;

trait InteractsWithErpPermissions
{
  protected function erpAuthorize(string $permission): void
  {
    ErpAccess::authorize(Auth::user(), $permission);
  }

  protected function erpAuthorizeOrNotify(string $permission): bool
  {
    return ErpAccess::authorizeOrNotify(Auth::user(), $permission);
  }

  protected function erpCan(string $permission): bool
  {
    return ErpAccess::currentCan($permission);
  }
}
