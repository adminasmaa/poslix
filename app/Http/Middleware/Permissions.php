<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;

class Permissions
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
        
        $user = $request->user();
        if($user->user_type == "owner") {
            return $next($request);
        }
        $userAttachedPermissions = DB::table("user_stuff")->select("stuff_ids")->where("user_id", $user->id)->first();
        $permission_ids = explode(",", $userAttachedPermissions->stuff_ids);
        array_pop($permission_ids);
        $permissions = Permission::whereIn("id", $permission_ids)->get();
        $permissions = $permissions->map(function ($permission) {
            return explode(",", $permission->stuff);
        })->flatten()->unique()->toArray();
        array_pop($permissions);

        $route = $request->route()->computedMiddleware;
        $route = array_filter($route, function ($item) {
            return strpos($item, "permissions") !== false;
        });
        $route = array_map(function ($item) {
            return explode(":", $item);
        }, $route);

        try {
            $route = array_map(function ($item) {
                return $item[1];
            }, $route);
        } catch (\Exception $e) {
            return response()->json(["message" => "Please add permissions to this route"], 403);
        }
        $requestedPermissions = array_values($route);

        if (count(array_intersect($requestedPermissions, $permissions)) != count($requestedPermissions)) {
            return response()->json(["message" => "You don't have permission to access this route"], 403);
        }

        return $next($request);
    }
}
