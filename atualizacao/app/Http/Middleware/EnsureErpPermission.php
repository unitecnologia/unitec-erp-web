<?php

namespace App\Http\Middleware;

use App\Support\Erp\ErpAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureErpPermission
{
  /**
   * @param  Closure(Request): Response  $next
   */
  public function handle(Request $request, Closure $next, string $permission): Response
  {
    $user = $request->user();

    if (! ErpAccess::can($user, $permission)) {
      abort(403, 'Sem permissão para esta operação.');
    }

    return $next($request);
  }
}
