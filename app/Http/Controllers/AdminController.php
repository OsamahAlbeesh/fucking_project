<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;


class AdminController extends Controller
{


//    public function dashboard(){
//    $pendingUsers = User::whereIn('role', ['landlord', 'customer'])
//                        ->where('verified_status', 'pending')
//                        ->get();
//
//    return view('admin.dashboard', compact('pendingUsers'));
//    }

    public function updateStatus(Request $request)
    {
        $user = User::findOrFail($request->user_id);
        $user->verified_status = $request->verified_status;
        $user->save();

        return redirect()->back()->with('message', 'تم تحديث حالة المستخدم بنجاح');
    }
    public function verifyUser(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'verified_status' => ['required', Rule::in(['approved', 'rejected'])],
        ]);

        $user = User::query()
            ->whereKey($validated['user_id'])
            ->whereIn('role', ['customer', 'landlord'])
            ->firstOrFail();

        if ($user->verified_status !== 'pending') {
            return response()->json([
                'message' => 'This user has already been reviewed.',
                'verified_status' => $user->verified_status,
            ], 409);
        }

        $user->update([
            'verified_status' => $validated['verified_status'],
        ]);

        return response()->json([
            'message' => 'User verification status updated successfully.',
            'user' => $user->only([
                'id',
                'first_name',
                'last_name',
                'phone',
                'role',
                'verified_status',
            ]),
        ], 200);
    }

    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return redirect()->back()->with('message', 'تم حذف الحساب بنجاح');
    }


}
