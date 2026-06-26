<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DashboardAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = config('error_reports.dashboard_user');
        $pass = config('error_reports.dashboard_password');

        if (empty($user) || empty($pass)) {
            return response('Dashboard not configured (set DASHBOARD_USER and DASHBOARD_PASSWORD).', 503);
        }

        $given = (string) $request->getUser();
        $givenPass = (string) $request->getPassword();

        if (! hash_equals($user, $given) || ! hash_equals($pass, $givenPass)) {
            return response('Authentication required', 401, [
                'WWW-Authenticate' => 'Basic realm="Aternix Error Receiver"',
            ]);
        }

        return $next($request);
    }
}
