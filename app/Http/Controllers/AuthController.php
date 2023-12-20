<?php

namespace App\Http\Controllers;

use App\Http\Traits\GeneralTrait;
use App\Models\Business;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use GeneralTrait;

    public function __construct()
    {
        $this->middleware('jwt', ['except' => ['login', 'register']]);
    } // end of __construct

    public function login(Request $request)
    {
        $rules = [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ];

        $validator = $this->validationApiTrait($request->all(), $rules);
        if ($validator) {
            return $validator;
        }

        $user = User::where('email', $request->email)->first();

        if ($user && $user->password == $request->password) {
            $token = Auth::login($user);
        } else {
            return customResponse('Wrong email or password', 401);
        }
        // $token = Auth::attempt($credentials);

        if (!$token) {

            $data = [
                'message' => 'Unauthorized',
            ];
            return customResponse($data, 401);
        }

        $user = Auth::user();
        $business = Business::query()
            ->where('business.owner_id', $user->id)
            ->count();


        $user->locations = getRoles();
//        $permissions = (new NewPermissionController())->getUserPermissions($user->id);
        $data = [
            'user' => $user,
//            'user_permissions' => $permissions,
            'business' => $business,
            'authorization' => [
                'token' => $token,
                'type' => 'bearer',
            ]
        ];
        return customResponse($data, 200);
    } // end of login

    public function register(Request $request)
    {
        $rules = [
            'first_name' => 'required|string',
            'last_name' => 'nullable|string',
            'number' => 'required|string|unique:users,contact_number',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string',
        ];

        $validator = $this->validationApiTrait($request->all(), $rules);
        if ($validator) {
            return $validator;
        }

        $user = User::create([
            'user_type' => 'owner',
            'first_name' => $request->first_name,
            'last_name' => $request->last_name ?? null,
            'contact_number' => $request->number,
            'email' => $request->email,
            'password' => $request->password,
            'status' => 'active',
        ]);
        $user->password = $request->password;
        $user->save();

        $token = Auth::login($user);

        $data = [
            'user' => $user,
            'authorization' => [
                'token' => $token,
                'type' => 'bearer',
            ]
        ];
        return customResponse($data, 200);
    } // end of register

    public function logout()
    {
        Auth::logout();
        $data = [
            'message' => 'Successfully logged out',
        ];
        return customResponse($data, 200);
    } // end of logout

    public function refresh()
    {
//        $permissions = (new NewPermissionController())->getUserPermissions(Auth::user()->id);
        $user = Auth::user();

        $user->locations = getRoles();
        $data = [
            'user' => $user,
//            'user_permissions' => $permissions,
            'authorisation' => [
                'token' => Auth::refresh(),
                'type' => 'bearer',
            ]
        ];
        return customResponse($data, 200);
    } // end of refresh
}
