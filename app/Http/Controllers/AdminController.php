<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{

    public function dashboard(){
    $pendingUsers = User::whereIn('role', ['landlord', 'tenant'])
                        ->where('verified_status', 'pending')
                        ->get();

    return view('admin.dashboard', compact('pendingUsers'));
    }

    public function updateStatus(Request $request)
    {
        $user = User::findOrFail($request->user_id);
        $user->verified_status = $request->verified_status;
        $user->save();

        return redirect()->back()->with('message', 'تم تحديث حالة المستخدم بنجاح');
    }

    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return redirect()->back()->with('message', 'تم حذف الحساب بنجاح');
    }
}
