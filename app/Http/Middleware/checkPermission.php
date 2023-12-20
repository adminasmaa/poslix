<?php

namespace App\Http\Middleware;

use App\Models\NewPermission;
use Closure;
use Illuminate\Http\Request;
use Route;
use Symfony\Component\HttpFoundation\Response;

class checkPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
        if (auth()->user()->user_type == 'owner') {
            return $next($request);
        }
        $skipArray = [
            'api.business.locations.locations/view',
            'api.business.business/view',
        ];
        if (isset(auth()->user()->id)) {
            if (in_array(Route::current()->getName(), $skipArray)) {
                return $next($request);
            }
            if (auth()->user()->roles()->first() == null) {
                return customResponse('You are not authorized to access this page', 401);
            }
            $roles = auth()->user()->roles()->get();//->permissions()->get();
            foreach ($roles as $role) {
                $permissions = $role->permissions()->get();
                foreach ($permissions as $permission) {
                    if ($permission->url == Route::current()->uri()) {
                        return $next($request);
                    }
                }
            }
        }
        return customResponse('You are not authorized to access this page', 401);
    }
}
