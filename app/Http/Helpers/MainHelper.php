<?php

use App\Models\Location;
use App\Models\NewPermission;
use App\Models\role;
use App\Models\User;

function customResponse($result = null, $status = 200, $additional = []): \Illuminate\Http\JsonResponse
{

    if ($status == 200) {
        $success = true;
        $Key = "result";
    } else {
        $success = false;
        $Key = "error";
    }

    if (is_string($result)) {
        $result = [
            "message" => $result
        ];
    }

    $data = [
        "status" => $status,
        "success" => $success,
        $Key => $result,
    ];

    return response()->json(array_merge($data, $additional), $status);
}

function getRoles($locationId = null, $userId = null)
{
    if (is_null($userId)) {
        $isOwner = auth()->user()->user_type == 'owner';
        $ownerId = $isOwner ? auth()->user()->id : auth()->user()->owner_id;
    } else {
        $isOwner = User::find($userId)->user_type == 'owner';
        $ownerId = $isOwner ? $userId : User::find($userId)->owner_id;
    }
    $userId = $userId ?? auth()->user()->id;
    $owner = User::find($ownerId);
    $requestedLocations = Location::select('id', 'name')->where('id', $locationId)->get();
    $locations = $locationId ? $requestedLocations : $owner->locations()->select('business_locations.id', 'business_locations.name')->get();
    $allUserPermissions = [];
    $allUserRoles = [];
    if ($isOwner) {
        $allPermissions = NewPermission::select('id', 'name', 'url', 'method')->get()
        ->map(function ($permission) {
            $permission->name = explode(' ', $permission->name)[count(explode(' ', $permission->name)) - 1];
            return $permission;
        });
    } else {
        $allPermissions = null;
    }
    foreach ($locations as $location) {
        $roleIds = DB::table('user_roles')
            ->where('location_id', $location->id);
        if (!$isOwner) {
            $roleIds = $roleIds->where('user_id', $userId);
        }
        $roleIds = $roleIds->pluck('role_id');
        $roleQuery = role::where('owner_id', $ownerId)
            ->whereIn('id', $roleIds)
            ->with(['permissions' => function ($query) {
                $query->select('permissions.id', 'permissions.name', 'url', 'method');
            }])
            ->get()
            ->map(function ($role) {
                foreach ($role->permissions as $permission) {
                    $permission->name = explode(' ', $permission->name)[count(explode(' ', $permission->name)) - 1];
                }
                return $role;
            });

        foreach ($roleQuery as $role) {
            foreach ($role->permissions as $permission) {
                if (!in_array($permission, $allUserPermissions))
                    $allUserPermissions[] = $permission;
            }
            $allUserRoles[] = [
                'id' => $role->id,
                'name' => $role->name,
            ];
        }

        if (!$isOwner) {
            $roleCheck = DB::table('user_roles')
                ->where('user_id', $userId)
                ->where('location_id', $location->id)
                ->first();

            if (!$roleCheck) {
                $roleQuery = collect(); // Empty collection for non-authorized users
            }
        }

//        $location->roles = $roleQuery;
        if ($isOwner) {
            $location->roles = [
                [
                    "id" => 0,
                    "name" => "Admin",
                ]
            ];
            if (!is_null($allPermissions)) {
                $location->permissions = $allPermissions;
            } else {
                $location->permissions = $allUserPermissions;
            }
        } else {
            $location->roles = $allUserRoles;
            $location->permissions = $allUserPermissions;
        }
        $allUserPermissions = [];
        $allUserRoles = [];
    }
    return $locations;
}
