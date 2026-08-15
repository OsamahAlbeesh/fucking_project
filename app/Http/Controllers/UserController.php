<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{

    public function register(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|regex:/^09\d{8}$/',
            'first_name'=>'required|string|max:50',
            'last_name'=>'required|string|max:50',
            'photo_url'=>'required|image|mimes:jpg,png,jpeg|max:4096',
            'id_photo'=>'required|image|mimes:jpg,png,jpeg|max:4096',
            'role'=>'required|in:tenant,landlord',
            'birth_date'=>'required|date|before_or_equal:'.now()->subYears(18)->format('Y-m-d'),
            'password'=>'required|min:8|string'
        ],
        [
        'birth_date.before_or_equal' => 'يجب أن يكون عمرك 18 سنة على الأقل.',
        'birth_date.date' => 'يرجى إدخال تاريخ ميلاد صالح.',
       ]);

        $user = User::where('phone',$request->phone)->first();

        if($user){
            return response()->json([
                'message'=>'The Phone Number is Already Registered '
            ],403 );
        }

        if ($request->hasFile('photo_url'))
            $personalPath = '/storage/' . $request->file('photo_url')->store('photos','public');

        if ($request->hasFile('photo_url'))
        $personalIdPath = '/storage/' . $request->file('id_photo')->store('photos','public');

            $user = User::create([
            'phone'=>$request->phone,
            'password'=>Hash::make($request->password),
            'first_name'=>$request->first_name,
            'last_name'=>$request->last_name,
            'photo_url'=>$personalPath,
            'id_photo'=>$personalIdPath,
            'role'=>$request->role,
            'birth_date'=>$request->birth_date,
        ]);

        return response()->json([
            'message'=>'User Registered Successfully',
             'User: '=>$user
        ],201 );
    }

    public function login(Request $request)
    {
        $request->validate([
            'phone'=>'required|string|regex:/^09\d{8}$/',
            'password'=>'required|string|min:8'
        ]);

        if (!Auth::attempt($request->only('phone','password')))
        {
            return response()->json([
                'message: Invalid Phone or Password ):'
            ],401);
        }

        $user = User::where('phone', $request->phone)->FirstOrFail();

        $token= $user->createToken('auth_Token')->plainTextToken;

        return response()->json([
            'message'=>'Welcome Back',
            'User'=>$user,
            'Token'=>$token
        ],201);
    }

    public function  logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'message '=>'Come Back Soon '
        ],200);
    }

    public function getUsers(Request $request)
    {
        $data =  User::whereNotIn('phone', ['0900000000'])->get();

        return response()->json($data,200);
    }

}
