<?php

namespace App\Http\Controllers;

use App\Http\Traits\GeneralTrait;
use App\Models\Business;
use App\Models\Location;
use App\Models\PrintSetting;
use App\Models\PaymentMethod;
use App\Models\role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BusinessController extends Controller
{
    use GeneralTrait;

    public function getTypes()
    {
        $types = DB::table('business_types')->get();
        return customResponse($types, 200);
    } // end of getTypes

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @description Get all businesses of the logged-in user
     */
    public function index(Request $request)
    {
        if (auth()->user()->user_type == 'owner') {
            $ownerId = auth()->user()->id;
        } else {
            $ownerId = auth()->user()->owner_id;
        }
        $businessesData = Business::query()
            ->where('business.owner_id', $ownerId)
            ->where('business.is_active', 1)
            ->join('business_types', 'business_types.id', '=', 'business.type_id')
            ->select(
                'business.id as id',
                'business_types.name as type',
                'business_types.id as type_id',
                'business.name as name',
                'business.email_settings as email',
                'business.status as status',
            )
            ->get();
        foreach ($businessesData as $business) {
            $businessesLLocationData = Location::where('business_id', $business->id)
                ->join('currencies', 'currencies.id', '=', 'business_locations.currency_id')
                ->select(
                    'business_locations.id as location_id',
                    'business_locations.name as location_name',
                    'business_locations.decimal_places as location_decimal_places',
                    'business_locations.status as location_status',
                    'business_locations.currency_id as currency_id',
                    'currencies.currency as currency_name',
                    'currencies.code as currency_code',
                    'currencies.symbol as currency_symbol',

                )
                ->get();
            $business->locations = $businessesLLocationData;
        }

        return customResponse($businessesData, 200);
    }

    /**
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     * @description Get a specific business of the logged-in user
     */
    public function show(Request $request, $id)
    {
        $user = auth()->user()->id;
        $businessesData = Business::query()
            ->where('business.owner_id', $user)
            ->where('business.id', $id)
            ->where('business.is_active', 1)
            ->join('business_types', 'business_types.id', '=', 'business.type_id')
            ->select(
                'business.id as id',
                'business_types.name as type',
                'business_types.id as type_id',
                'business.name as name',
                'business.email_settings as email',
                'business.status as status',
            )
            ->get();
        foreach ($businessesData as $business) {
            $businessesLLocationData = Location::where('business_id', $business->id)
                ->join('currencies', 'currencies.id', '=', 'business_locations.currency_id')
                ->select(
                    'business_locations.id as location_id',
                    'business_locations.name as location_name',
                    'business_locations.decimal_places as location_decimal_places',
                    'business_locations.status as location_status',

                    'business_locations.currency_id as currency_id',
                    'currencies.currency as currency_name',
                    'currencies.code as currency_code',
                    'currencies.symbol as currency_symbol',
                )
                ->get();
            $business->locations = $businessesLLocationData;
        }
        return customResponse($businessesData, 200);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @description update a business of the logged-in user
     */
    public function update(Request $request, $id)
    {
        $validate = $this->validationApiTrait($request->all(), [
            'name' => 'required',
            'email' => 'required|email',
            'status' => 'string',
        ]);
        if ($validate) {
            return $validate;
        }
        $user = auth()->user()->id;
        $business = Business::query()
            ->where('business.owner_id', $user)
            ->where('business.id', $id)
            ->where('business.is_active', 1)
            ->first();
        if (!$business) {
            return customResponse('Business not found', 404);
        }
        $business->name = $request->name;
        $business->email_settings = $request->email;
        $business->status = $request->status ?? 'active';
        $business->save();
        return customResponse($business, 200, ['message' => 'Business updated successfully']);
    }

    public function store(Request $request)
    {
        $validate = $this->validationApiTrait($request->all(), [
            'name' => 'required|unique:business,name',
            'mobile' => 'required|numeric',
            'email' => 'required|email',
            'business_type_id' => 'required|exists:business_types,id',
            'status' => 'string',
        ]);
        if ($validate) {
            return $validate;
        }
        $user = auth()->user()->id;
        $business = new Business();
        $business->name = $request->name;
        $business->email_settings = $request->email;
        $business->type_id = $request->business_type_id;
        $business->owner_id = $user;
        $business->status = $request->status ?? 'active';

        $business->save();
        return customResponse($business, 200, ['message' => 'Business created successfully']);
    }

    public function getBusinessLocations(Request $request)
    {
        $businessesLLocationData = Location::query();
            $validate = $this->validationApiTrait($request->all(), [
                'business_id' => 'required|exists:business,id',
            ]);
            if ($validate) {
                return $validate;
            }
            $businessId = $request->business_id;
            $businessesLLocationData = $businessesLLocationData->where('business_locations.business_id', $businessId);
        if (auth()->user()->user_type == 'owner') {
            $ownerId = auth()->user()->id;
        } else {
            $ownerId = auth()->user()->owner_id;
        }
        $businessesLLocationData = $businessesLLocationData->where('business_locations.owner_id', $ownerId)
            ->with('printSetting')
            ->join('currencies', 'currencies.id', '=', 'business_locations.currency_id')
            ->select(
                'business_locations.id as location_id',
                'business_locations.name as location_name',
                'business_locations.decimal_places as location_decimal_places',
                'business_locations.currency_id as currency_id',
                'business_locations.status as status',
                'currencies.currency as currency_name',
                'currencies.code as currency_code',
                'currencies.symbol as currency_symbol',
            )
            ->get();
        return customResponse($businessesLLocationData, 200);
    }

    public function showBusinessLocation(Request $request, $locationId)
    {
        if (auth()->user()->user_type == 'owner') {
            $ownerId = auth()->user()->id;
        } else {
            $ownerId = auth()->user()->owner_id;
            $userLocations = DB::table('user_stuff')->where('user_id', auth()->user()->id)->pluck('location_id')->toArray();
            if (!in_array($locationId, $userLocations)) {
                return customResponse('Business location not found or not accessible', 404);
            }
        }
        $businessesLLocationData = Location::where('business_locations.owner_id', $ownerId)
            ->with('printSetting')
            ->with('paymentMethods')
            ->where('business_locations.id', $locationId)
            ->join('currencies', 'currencies.id', '=', 'business_locations.currency_id')
            ->select(
                'business_locations.id as location_id',
                'business_locations.name as location_name',
                'business_locations.decimal_places as location_decimal_places',
                'business_locations.currency_id as currency_id',
                'business_locations.status as status',
                'business_locations.is_multi_language as is_multi_language',
                'currencies.currency as currency_name',
                'currencies.code as currency_code',
                'currencies.symbol as currency_symbol',
            )
            ->first();
        if (!$businessesLLocationData) {
            return customResponse('Business location not found', 404);
        }
        return customResponse($businessesLLocationData, 200);
    }

    public function updateBusinessLocation(Request $request, $locationId)
    {
        $validate = $this->validationApiTrait($request->all(), [
            'name' => 'required',
            'currency_id' => 'required|exists:currencies,id',
            'decimal' => 'required|numeric',
            "status" => "required|string",
            "is_multi_language" => "required|boolean",

        ]);
        if ($validate) {
            return $validate;
        }
        $user = auth()->user()->id;
        $businessesLLocationData = Location::where('business_locations.owner_id', $user)
            ->where('business_locations.id', $locationId);
        if (!$businessesLLocationData->first()) {
            return customResponse('Business location not found', 404);
        }
        // if($request->has('status')){
        //     $stattus = $request->status;
        // }
        $businessesLLocationData->update([
            'name' => $request->name,
            'currency_id' => $request->currency_id,
            'decimal_places' => $request->decimal,
            'status' => $request->status,
            'is_multi_language' => $request->is_multi_language,
        ]);
        return customResponse($businessesLLocationData->first(), 200, ['message' => 'Business location updated successfully']);
    }

    public function storeBusinessLocation(Request $request)
    {
        $validate = $this->validationApiTrait($request->all(), [
            'business_id' => 'required|exists:business,id',
            'name' => 'required',
            'currency_id' => 'required|exists:currencies,id',
            'decimal' => 'required|numeric',
            "status" => "required|string",
            "is_multi_language" => "boolean",
        ]);
        if ($validate) {
            return $validate;
        }
        $user = auth()->user()->id;
        $businessesLLocationData = Location::where('business_locations.owner_id', $user)
            ->where('business_locations.business_id', $request->business_id)
            ->where('business_locations.name', $request->name);
        if ($businessesLLocationData->first()) {
            return customResponse('Business location already exists', 400);
        }

        $langs = ['ar', 'en'];
        // Initialize an empty JSON object
        $appearance = (object) [];

        foreach ($langs as $lang){
            // update json data
            if (isset($request->logo) && $request->logo != '') {
                $appearance->logo = $request->logo;
            } else {
                $appearance->logo = 'https://app.poslix.com/images/logo1.png';
            }
            $appearance->website = 'poslix.com';
            $appearance->instagram = 'instagram.com';
            $appearance->whatsapp = 'whatsapp.com';
            $appearance->{$lang} = (object) [
                'name' => 'Poslix',
                'tell' => '09123456789',
                'txtCustomer' => ($lang == 'ar' ? 'العميل' : 'Customer'),
                'orderNo' => ($lang == 'ar' ? 'رقم الطلب' : 'Order No'),
                'txtDate' => ($lang == 'ar' ? 'التاريخ' : 'Date'),
                'txtQty' => ($lang == 'ar' ? 'الكمية' : 'Qty'),
                'txtItem' => ($lang == 'ar' ? 'الصنف' : 'Item'),
                'txtAmount' => ($lang == 'ar' ? 'السعر' : 'Amount'),
                'txtTax' => ($lang == 'ar' ? 'الضريبة' : 'Tax'),
                'txtTotal' => ($lang == 'ar' ? 'الاجمالي' : 'Total'),
                'footer' => ($lang == 'ar' ? 'شكرا' : 'Thanks'),
                'email' => 'info@poslix.com',
                'address' => ($lang == 'ar' ? 'العنوان' : 'Address'),
                'vatNumber' => ($lang == 'ar' ? 'الرقم الضريبي' : 'VAT No'),
                'customerNumber' => ($lang == 'ar' ? 'رقم العميل' : 'Customer No'),
                'description' => ($lang == 'ar' ? 'الوصف' : 'Description'),
                'unitPrice' => ($lang == 'ar' ? 'سعر الوحدة' : 'Unit Price'),
                'subTotal' => ($lang == 'ar' ? 'المجموع' : 'Sub Total'),
            ];
        }

        $businessesLLocationData = Location::create([
            'business_id' => $request->business_id,
            'owner_id' => $user,
            'name' => $request->name,
            'currency_id' => $request->currency_id,
            'decimal_places' => $request->decimal,
            'state' => $request->state,
            'is_active' => 1,
            'status' => $request->status,
            'invoice_details' => json_encode($appearance),
            'is_multi_language' => isset($request->is_multi_language) ? $request->is_multi_language : 0,
        ]);
        $businessesLLocationData->paymentMethods()->createMany([
            [
                'name' => 'cash',
                'enable_flag' => 1,
            ],
            [
                'name' => 'card',
                'enable_flag' => 1,
            ],
            [
                'name' => 'cheque',
                'enable_flag' => 1,
            ],
            [
                'name' => 'bank',
                'enable_flag' => 1,
            ],
        ]);
        $printSetting = PrintSetting::create([
            'name' =>'default_printer',
            'connection' =>'Wifi',
            'ip' => '192.168.0.1',
            'print_type' => 'receipt',
            'status' =>1,
            'location_id' => $businessesLLocationData->id,
        ]);
        $printSetting->save();
        $businessesLLocationData->permissions = getRoles();
        return customResponse($businessesLLocationData, 200, ['message' => 'Business location created successfully']);
    }

    public function addUser(Request $request)
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
            'user_type' => 'user',
            'first_name' => $request->first_name,
            'last_name' => $request->last_name ?? null,
            'contact_number' => $request->number,
            'email' => $request->email,
            'status' => 'active',
            'password' => $request->password,
            'owner_id' => auth()->user()->id,
        ]);

        if (!$user) {
            return customResponse('Error while creating user', 400);
        }
        return customResponse($user, 201);
    }

    public function getUsers(Request $request)
    {
        // validate $request->location_id
        $validator = $this->validationApiTrait($request->all(), [
            'location_id' => 'nullable|exists:business_locations,id',
        ]);
        if ($validator) {
            return $validator;
        }
        $users = User::query()
            ->where('owner_id', auth()->user()->id)
            ->where('user_type', 'user')->get();
        $allPerm = [];
        $userPerms = [];
        if ($request->location_id) {
            foreach ($users as $user) {
                // old permissions
                /*$permissions = \DB::table('user_stuff')->where('location_id', $request->location_id)
                    ->where('user_id', $user->id)
                    ->get();
                // $permissions to array
                $permissions = $permissions->map(function ($permission) {
                    $permission->stuff_ids = explode(",", $permission->stuff_ids);
                    array_pop($permission->stuff_ids);
                    return $permission->stuff_ids;
                });
                $allPerm = $permissions;
                foreach ($allPerm as $key => $perm) {
                    $permResult = (new PermissionController())->getPermission($perm);
                    $userPerms[] = $permResult->original['success'] ? $permResult->original['result'][0] : [];
                }*/
                $user->locations = getRoles($request->location_id, $user->id);
            }
        } else {
            foreach ($users as $user) {
                $user->locations = getRoles(null, $user->id);
            }
        }
        return customResponse($users, 200);
    }

    public function deleteUser(Request $request, $id)
    {
        $user = User::query()
            ->where('owner_id', auth()->user()->id)
            ->where('user_type', 'user')
            ->where('id', $id)->first();
        if (!$user) {
            return customResponse('User not found', 404);
        }
        $user->delete();
        DB::table('user_stuff')->where('user_id', $id)->delete();
        return customResponse('User removed', 200);
    }

    public function updateUser(Request $request, $id)
    {
        $rules = [
            'first_name' => 'nullable|string',
            'last_name' => 'nullable|string',
            'number' => 'nullable|string|unique:users,contact_number,' . $id,
            'email' => 'nullable|string|email|unique:users,email,' . $id,
            'password' => 'nullable|string',
        ];

        $validator = $this->validationApiTrait($request->all(), $rules);
        if ($validator) {
            return $validator;
        }

        $user = User::query()
            ->where('owner_id', auth()->user()->id)
            ->where('user_type', 'user')
            ->where('id', $id)->first();
        if (!$user) {
            return customResponse('User not found', 404);
        }
        $user->first_name = $request->first_name ?? $user->first_name;
        $user->last_name = $request->last_name ?? $user->last_name;
        $user->contact_number = $request->number ?? $user->contact_number;
        $user->email = $request->email ?? $user->email;
        if ($request->password) {
            $user->password = $request->password ?? $user->password;
        }
        $user->save();
        return customResponse($user, 200);
    }

    public function updatePrintType(Request $request, $id)
    {
        $validate = $this->validationApiTrait($request->all(), [
            'print_type' => 'required|in:A4,receipt',
        ]);
        if ($validate) {
            return $validate;
        }
        $location = Location::find($id);
        if (!$location) {
            return customResponse('Location not found', 404);
        }
        $location->update([
            'print_type' => $request->print_type,
        ]);
        return $this->showBusinessLocation($request, $id);
    }

    public function destroy($id)
    {
        $user = auth()->user()->id;
        $business = Business::query()
            ->where('business.owner_id', $user)
            ->where('business.id', $id)
            ->where('business.is_active', 1)
            ->first();
        if (!$business) {
            return customResponse('Business not found', 404);
        }

        $business->locations()->delete();
        $business->delete();

        return customResponse(['message' => 'Business deleted successfully']);
    }

    public function destroyBusinessLocation($id)
    {
        $user = auth()->user()->id;
        $businessesLLocationData = Location::where('business_locations.owner_id', $user)
            ->where('business_locations.id', $id);
        if (!$businessesLLocationData->first()) {
            return customResponse('Business location not found', 404);
        }
        $businessesLLocationData->delete();
        return customResponse(['message' => 'Business location deleted successfully']);
    }
}
