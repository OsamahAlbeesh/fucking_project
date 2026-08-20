<?php

namespace App\Http\Controllers;

use App\Mail\OtpMail;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthOtpController extends Controller
{
    /**
     * إرسال OTP
     */
    public function sendOtp(User $user, string $purpose): void    {
        // حذف أي OTP قديم لنفس الغرض
        OtpCode::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->delete();

        // إنشاء OTP من 6 أرقام
        $otp = (string) random_int(100000, 999999);

        OtpCode::create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'code' => Hash::make($otp),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ]);

        Mail::to($user->email)->send(
            new OtpMail($otp, $purpose)
        );
    }


    /**
     * إرسال OTP لتسجيل الحساب
     */
    public function sendRegisterOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        $this->sendOtp($user, 'register');

        return response()->json([
            'success' => true,
            'message' => 'OTP sent to your email.',
        ]);
    }


    /**
     * التحقق من OTP الخاص بالتسجيل
     */
    public function verifyRegisterOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'otp' => ['required', 'digits:6'],
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        $otpRecord = OtpCode::where('user_id', $user->id)
            ->where('purpose', 'register')
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'success' => false,
                'message' => 'OTP not found.',
            ], 404);
        }

        if ($otpRecord->expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'OTP expired.',
            ], 422);
        }

        if ($otpRecord->attempts >= 5) {
            return response()->json([
                'success' => false,
                'message' => 'Too many attempts.',
            ], 429);
        }

        if (!Hash::check($request->otp, $otpRecord->code)) {
            $otpRecord->increment('attempts');

            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP.',
            ], 422);
        }

        $otpRecord->update([
            'verified_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully. Your account is waiting for admin approval.',
            'verified_status' => $user->verified_status,
        ], 200);
    }


    /**
     * طلب إعادة تعيين كلمة المرور
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $user = User::where('email', $request->email)->first();

        $this->sendOtp($user, 'password_reset');

        return response()->json([
            'success' => true,
            'message' => 'OTP sent to your email.',
        ], 200);
    }


    /**
     * التحقق من OTP الخاص بإعادة تعيين كلمة المرور
     */
    public function verifyResetOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'otp' => ['required', 'digits:6'],
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        $otpRecord = OtpCode::where('user_id', $user->id)
            ->where('purpose', 'password_reset')
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'success' => false,
                'message' => 'OTP not found.',
            ], 404);
        }

        if ($otpRecord->expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'OTP expired.',
            ], 422);
        }

        if ($otpRecord->attempts >= 5) {
            return response()->json([
                'success' => false,
                'message' => 'Too many attempts.',
            ], 429);
        }

        if (!Hash::check($request->otp, $otpRecord->code)) {
            $otpRecord->increment('attempts');

            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP.',
            ], 422);
        }

        // لا نغيّر password هنا
        // فقط نسجل أن OTP صحيح
        $resetToken = Str::random(64);

        $otpRecord->update([
            'verified_at' => now(),
            'code' => Hash::make($resetToken),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully.',
            'reset_token' => $resetToken,
        ], 200);
    }


    /**
     * تغيير كلمة المرور بعد نجاح OTP
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'reset_token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        $otpRecord = OtpCode::where('user_id', $user->id)
            ->where('purpose', 'password_reset')
            ->whereNotNull('verified_at')
            ->latest()
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'success' => false,
                'message' => 'You must verify OTP first.',
            ], 403);
        }

        // يجب أن يكون التحقق حديثاً
        if ($otpRecord->verified_at->addMinutes(10)->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Reset session expired. Request a new OTP.',
            ], 422);
        }

        if (!Hash::check($request->reset_token, $otpRecord->code)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid reset token.',
            ], 403);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // جعل reset token غير صالح بعد الاستخدام
        $otpRecord->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully.',
        ], 200);
    }
}
