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
            'email' => 'required|email|unique:users,email',

            'phone' => 'required|regex:/^09\d{8}$/|unique:users,phone',

            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',

            'photo_url' => 'required|image|mimes:jpg,png,jpeg|max:4096',
            'id_photo_front' => 'required|image|mimes:jpg,png,jpeg|max:4096',
            'id_photo_back' => 'required|image|mimes:jpg,png,jpeg|max:4096',

            'role' => 'required|in:customer,landlord',

            'birth_date' => [
                'required',
                'date',
                'before_or_equal:' . now()->subYears(18)->format('Y-m-d')
            ],

            'password' => 'required|min:8|string',
            'password_confirmation' => 'required|same:password',
        ]);

        $personalPath = null;
        $personalIdPathFront = null;
        $personalIdPathBack = null;

        if ($request->hasFile('photo_url')) {
            $personalPath =
                '/storage/' .
                $request->file('photo_url')->store('photos', 'public');
        }

        if ($request->hasFile('id_photo_front')) {
            $personalIdPathFront =
                '/storage/' .
                $request->file('id_photo_front')->store('photos', 'public');
        }

        if ($request->hasFile('id_photo_back')) {
            $personalIdPathBack =
                '/storage/' .
                $request->file('id_photo_back')->store('photos', 'public');
        }

        $user = User::create([
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),

            'first_name' => $request->first_name,
            'last_name' => $request->last_name,

            'photo_url' => $personalPath,
            'id_photo_front' => $personalIdPathFront,
            'id_photo_back' => $personalIdPathBack,

            'role' => $request->role,
            'birth_date' => $request->birth_date,

            'verified_status' => 'pending',
        ]);



        // إرسال OTP مرة واحدة فقط
        $otpController = app(\App\Http\Controllers\AuthOtpController::class);

        $otpController->sendOtp($user, 'register');

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully. OTP has been sent to your email.',
            'user_id' => $user->id,
            'verified_status' => $user->verified_status,
            'requires_otp' => true,
            'requires_admin_approval' => true,
        ], 201);
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
