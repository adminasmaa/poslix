<?php

namespace App\Http\Controllers;

use App\Http\Traits\GeneralTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PermissionController extends Controller
{
    use GeneralTrait;

    public function getPermissions()
    {
        $permissions = (new NewPermissionController())->getPermissions();
        return customResponse($permissions, 200);
        /*$permissions = DB::table('stuffs')
            ->select("id", "name", "stuff")
            ->get();

        $permissions = $permissions->map(function ($permission) {
            $permission->stuff = explode(",", $permission->stuff);
            return $permission;
        });

        foreach ($permissions as $permission) {
            $array = [];
            unset($permission->stuff[count($permission->stuff) - 1]);
            foreach ($permission->stuff as $key => $stuff) {
                $arr = explode("/", $stuff);
                $array[$arr[0]][] = $arr[1];
            }
            $permission->stuff = $array;
        }


        return customResponse($permissions, 200);*/
    } // end of getPermissions

    public function getPermission($stuff_id)
    {
        $permission = (new NewPermissionController())->getPermission($stuff_id);
        return customResponse($permission, 200);
        /*$permissions = DB::table('stuffs')
            ->select("id", "name", "stuff")
            ->where("id", $stuff_id)
            ->get();

        $permissions = $permissions->map(function ($permission) {
            $permission->stuff = explode(",", $permission->stuff);
            return $permission;
        });

        foreach ($permissions as $permission) {
            $array = [];
            unset($permission->stuff[count($permission->stuff) - 1]);
            foreach ($permission->stuff as $key => $stuff) {
                $arr = explode("/", $stuff);
                $array[$arr[0]][] = $arr[1];
            }
            $permission->stuff = $array;
        }

        return customResponse($permissions, 200);*/
    } // end of getPermission

    public function storePermission(Request $request)
    {
        return customResponse('You can not add permission', 401);
        /*$validator = $this->validationApiTrait($request->all(), [
            'name' => 'required|string',
            'stuff' => 'required|string|regex:/^[a-zA-Z0-9\/\,]+$/', // it must be in this format: sales/view,sales/edit,sales/delete,sales/insert,
        ]);

        if ($validator) {
            return $validator;
        }

        $permission = DB::table('stuffs')
            ->insert([
                'owner_id' => auth()->user()->id,
                'name' => $request->name,
                'stuff' => $request->stuff,
            ]);

        return customResponse($permission, 200);*/
    } // end of storePermission

    public function updatePermission(Request $request, $stuff_id)
    {
        $validator = $this->validationApiTrait($request->all(), [
            'name' => 'required|string',
        ]);
        if ($validator) {
            return $validator;
        }
        $permission = (new NewPermissionController())->updatePermission($request, $stuff_id);
        return customResponse($permission, 200);
        /*$validator = $this->validationApiTrait($request->all(), [
            'name' => 'required|string',
            'stuff' => 'required|string|regex:/^[a-zA-Z0-9\/\,]+$/', // it must be in this format: sales/view,sales/edit,sales/delete,sales/insert,
        ]);

        if ($validator) {
            return $validator;
        }

        $permission = DB::table('stuffs')
            ->where('id', $stuff_id)
            ->update([
                'name' => $request->name,
                'stuff' => $request->stuff,
            ]);

        return customResponse($permission, 200);*/
    } // end of updatePermission

    public function deletePermission($stuff_id)
    {
        return customResponse('You can not delete permission', 401);
        /*$permission = DB::table('stuffs')
            ->where('id', $stuff_id)
            ->delete();

        return customResponse($permission, 200);*/
    } // end of deletePermission

    public function getPermissionsList()
    {
        $categories = [
            "sales",
            "quotations",
            "products",
            "purchases",
            "category",
            "taxes",
            "discounts",
            "expanses",
            "pos"
        ];

        $permissions = [
            "view",
            "edit",
            "delete",
            "insert"
        ];

        return customResponse([
            "categories" => $categories,
            "permissions" => $permissions
        ], 200);
    } // end of premissionList

    public function assignPermissions(Request $request)
    {
        $validator = $this->validationApiTrait($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'stuff_ids' => 'required|array|min:1|exists:permissions,id',
//            'location_id' => 'required|integer|exists:business_locations,id',
//            'business_id' => 'required|integer|exists:business,id',
        ]);

        if ($validator) {
            return $validator;
        }
        $assign = (new NewPermissionController())->assignPermission($request);
        return customResponse($assign, 200);
        /*$stuff_ids = $request->stuff_ids;
        $stuff_ids = implode(",", $stuff_ids) . ",";

        $check = DB::table('user_stuff')
            ->where('user_id', $request->user_id)
            ->where('location_id', $request->location_id)
            ->where('business_id', $request->business_id)
            ->first();
        if ($check) {
            $permission = DB::table('user_stuff')
                ->where('user_id', $request->user_id)
                ->where('location_id', $request->location_id)
                ->where('business_id', $request->business_id)
                ->update([
                    'stuff_ids' => $stuff_ids,
                ]);
            return customResponse($permission, 200);
        }
        $permission = DB::table('user_stuff')
            ->insert([
                'user_id' => $request->user_id,
                'stuff_ids' => $stuff_ids,
                'location_id' => $request->location_id,
                'business_id' => $request->business_id,
            ]);

        return customResponse($permission, 200);*/
    } // end of attachPermissionUser

    /*public function assignPermissions(Request $request)
    {
        $validator = $this->validationApiTrait($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'stuff_ids' => 'required|array|min:1|exists:stuffs,id',
            'location_id' => 'required|integer|exists:business_locations,id',
            'business_id' => 'required|integer|exists:business,id',
        ]);

        if ($validator) {
            return $validator;
        }
        $stuff_ids = $request->stuff_ids;

        $check = DB::table('user_stuff')
            ->where('user_id', $request->user_id)
            ->where('location_id', $request->location_id)
            ->where('business_id', $request->business_id)
            ->first();
        if ($check) {
            $existStuff = explode(",", $check->stuff_ids);
            unset($existStuff[count($existStuff) - 1]);
            $stuff_ids = array_unique(array_merge($stuff_ids, $existStuff));
            $stuff_ids = implode(",", $stuff_ids) . ",";
            $permission = DB::table('user_stuff')
                ->where('user_id', $request->user_id)
                ->where('location_id', $request->location_id)
                ->where('business_id', $request->business_id)
                ->update([
                    'stuff_ids' => $stuff_ids,
                ]);
            return customResponse($permission, 200);
        }
        $permission = DB::table('user_stuff')
            ->insert([
                'user_id' => $request->user_id,
                'stuff_ids' => $stuff_ids,
                'location_id' => $request->location_id,
                'business_id' => $request->business_id,
            ]);

        return customResponse($permission, 200);
    } // end of attachPermissionUser*/
}
