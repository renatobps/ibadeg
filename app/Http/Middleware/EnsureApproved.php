<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureApproved
{
    public function handle(Request $r, Closure $next)
    {
        abort_unless($r->user()?->canStudy(), 403, 'Aguarde a liberação do administrador para acessar as provas e apostilas.');

        return $next($r);
    }
}
