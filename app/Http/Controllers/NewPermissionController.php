<?php

namespace App\Http\Controllers;

use App\Http\Traits\GeneralTrait;
use App\Models\NewPermission;
use App\Models\role;
use App\Models\User;
use App\Resources\PermissionsResource;
use DB;
use Illuminate\Http\Request;
use Route;

class NewPermissionController extends Controller
{
    private $flag = false;
    private $flag2 = true;
    use generalTrait;
    public function index()
    {
        $permissions = NewPermission::whereNull('method')->get();
        foreach ($permissions as $item) {
            // get latest word in name
            $parts = explode(' ', $item->name);
            $item->method = end($parts);
            $item->update();
        }
        $routeCollection = Route::getRoutes()->get();
        foreach ($routeCollection as $key => $item) {
            $method = $item->methods[0];
            $name = $item->action;
            if (!empty($name['as'])) {
                $permission = $name['as'];
                $permission = trim(strtolower($permission));
                $permission = preg_replace('/\./', ' ', $permission); // remove special characters
                // if $permission start with api. remove it
                if (strpos($permission, 'api ') === 0) {
                    $permission = substr($permission, 4);
                } else {
                    continue;
                }
                $permissionName = $permission;// . ' ' . $method;
                $permissionUrl = $item->uri;
                $checkPermission = NewPermission::where('method', $item->methods[0])
                    ->where(function ($query) use ($permissionName, $permissionUrl) {
                        $query->where('name', $permissionName)
                            ->orWhere('url', $permissionUrl);
                    })
                    ->first();
                if ($checkPermission) {
                    if (is_null($checkPermission->method)){
                        $checkPermission->method = $method;
                        $checkPermission->update();
                    }
                    continue;
                }
                NewPermission::create([
                    'name' => $permissionName,
                    'url' => $permissionUrl,
                    'guard_name' => 'api' . $key,
                    'method' => $method,
                ]);
            }
        }
        return $this->getPermissions();
    }

    public function getPermissions()
    {
        $permissions = NewPermission::select('id', 'name', 'url', 'method')->get();
        foreach ($permissions as $item) {
            // Extract the first word as the key and the rest as the value
            $parts = explode(' ', $item->name, 2);
            $item->name = $parts[1] ?? $parts[0];
            $key = $parts[0];
//            $value = $parts[1];

            // Add the value to the corresponding key in the grouped array
            if (!isset($groupedData[$key])) {
                $groupedData[$key] = [];
            }
            $groupedData[$key][] = $item;
        }
        // remove if key is empty
        $shouldRemove = [
            'permissions',
            'permission',
            'login',
            'logout',
            'refresh',
        ];
        foreach ($groupedData as $key => $value) {
            if (empty($key) || in_array($key, $shouldRemove)) {
                unset($groupedData[$key]);
            }
        }
        $groupedArr = [];
        foreach ($groupedData as $key => $items) {
            foreach ($items as $item) {
                // Split the "name" key into parts
                $nameParts = explode(' ', $item['name']);
                // if there is only one part, then add the item to the final level
                if (count($nameParts) === 1) {
                    $groupedArr[$key][] = $item;
                } else {

                    // Start building the nested structure
                    $nestedArray = &$groupedArr[$key];

                    foreach ($nameParts as $namePart) {
                        if (!isset($nestedArray[$namePart])) {
                            $nestedArray[$namePart] = [];
                        }
                        $nestedArray = &$nestedArray[$namePart];
                    }

                    // Add the item to the final level
                    $item->name = end($nameParts);
                    $nestedArray[] = $item;
                }
            }
        }
        return $this->arrayValuesToFinalLevel($groupedArr);
    }

    private function arrayValuesToFinalLevel($array) {
        $result = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                // Recursively process nested arrays
                $result[$key] = $this->arrayValuesToFinalLevel($value);
            } else {
                // Apply array_values to the final level arrays
                $result[$key] = $value;
            }
        }

        // Apply array_values only to the final level arrays
        if (is_array($array) && str_contains(key($array),'/')) {
            return array_values($this->oneDimensionalArray($result));
        }

        return $result;
    }

    private function oneDimensionalArray($array) {
        foreach ($array as $subArray) {
            foreach ($subArray as $item) {
                $flattenedArray[] = $item;
            }
        }
        return $flattenedArray;
    }

    public function getPermission($id)
    {
        return NewPermission::select('id', 'name', 'url', 'method')->where('id', $id)->first();
    }

    public function updatePermission(Request $request, $id)
    {
        $permission = NewPermission::where('id', $id)->first();
        $permission->name = $request->name;
        $permission->update();
        return $this->getPermission($id);
    }

    public function oldAssignPermission(Request $request)
    {
        // assign permissions to user id = 477
        $permissions = $request->stuff_ids;
        foreach ($permissions as $permission) {
            $checkPermission = DB::table('user_permissions')->where('permission_id', $permission)
                ->where('user_id', $request->user_id)
                ->first();
            if ($checkPermission) {
                $locationIds = json_decode($checkPermission->location) ?? [];
                DB::table('user_permissions')->where('permission_id', $permission)
                    ->where('user_id', $request->user_id)
                    ->update([
                        'location' => json_encode(array_unique(array_merge($locationIds, [$request->location_id]))),
                    ]);
                continue;
            }
            DB::table('user_permissions')->insert([
                'permission_id' => $permission,
                'user_id' => $request->user_id,
                'location' => json_encode([$request->location_id]),
            ]);
        }
        return true;
    }

    public function getUserPermissions($id)
    {
        $user = User::where('id', $id)->first();
        if ($user->user_type == 'owner') {
            return 'all';
        }
        $roles = $user->roles()->first();
        if ($roles) {
            return $user->roles()->first()->permissions()->get();
        }
        return [];
    }

    public function storeRole(Request $request)
    {
//        return $request->permissions;
        $validator = $this->validationApiTrait($request->all(), [
            'name' => 'required|string',
            'permissions' => 'required|array|exists:permissions,id',
        ]);
        if ($validator) {
            return $validator;
        }
        $isOwner = auth()->user()->user_type == 'owner';
        $role = role::create([
            'name' => $request->name,
            'owner_id' => $isOwner ? auth()->user()->id : auth()->user()->owner_id,
        ]);
        $permissions = $request->permissions;
        foreach ($permissions as $permission) {
            $role->permissions()->attach($permission);
        }
        return customResponse(__('Added Successfully'), 200);
    }

    public function getRoles(Request $request)
    {
        $isOwner = auth()->user()->user_type == 'owner';
        $ownerId = $isOwner ? auth()->user()->id : auth()->user()->owner_id;
        $roles = role::where('owner_id', $ownerId)
            ->with(['permissions'=>function($query){
                $query->select('permissions.id', 'permissions.name', 'url', 'method');
            }])
            ->get();

        foreach ($roles as $role) {
            foreach ($role->permissions as $permission) {
                $permission->name = explode(' ', $permission->name)[count(explode(' ', $permission->name)) - 1];
            }
        }
        return customResponse($roles, 200);
    }

    public function updateRole(Request $request, $id)
    {
        $validator = $this->validationApiTrait($request->all(), [
            'name' => 'required|string',
            'permissions' => 'required|array|exists:permissions,id',
        ]);
        if ($validator) {
            return $validator;
        }
        $role = role::where('id', $id)->first();
        if (!$role) {
            return customResponse(__('no data found'), 404);
        }
        $role->permissions()->detach();
        $role->update([
            'name' => $request->name,
        ]);
        $permissions = $request->permissions;
        foreach ($permissions as $permission) {
            $role->permissions()->attach($permission);
        }
        return customResponse(__('Updated Successfully'), 200);
    }

    public function deleteRole($id)
    {
        $role = role::where('id', $id)->first();
        if (!$role) {
            return customResponse(__('api.no_data_found'), 404);
        }
        $role->permissions()->detach();
        $role->users()->detach();
        $role->delete();
        return customResponse(__('Deleted Successfully'), 200);
    }

    public function assignRole(Request $request)
    {
        $validator = $this->validationApiTrait($request->all(), [
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id',
            'location_id' => 'required|exists:business_locations,id',
        ]);
        if ($validator) {
            return $validator;
        }
        $user = User::where('id', $request->user_id)->first();
        if (!$user) {
            return customResponse(__('api.no_data_found'), 404);
        }
        // get assigned role with location_id
        $getAssignedRole = DB::table('user_roles')
            ->where('user_id', $request->user_id)
            ->where('location_id', $request->location_id)
            ->first();
        if ($getAssignedRole) {
            // deassign role
            DB::table('user_roles')
                ->where('user_id', $request->user_id)
                ->where('location_id', $request->location_id)
                ->delete();
        }
        $user->roles()->attach($request->role_id, ['location_id' => $request->location_id]);
        return customResponse(__('Role Assigned Successfully'), 200);
    }

    public function deassignRole(Request $request)
    {
        $validator = $this->validationApiTrait($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);
        if ($validator) {
            return $validator;
        }
        $user = User::where('id', $request->user_id)->first();
        if (!$user) {
            return customResponse(__('no data found'), 404);
        }
        $user->roles()->detach();
        return customResponse(__('Role Designed Successfully'), 200);
    }
}
