<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Auth;
use App\Models\User;
use Hash;
use DB;

class PabrikUserController extends Controller
{
    public $successStatus = 401;

    function __construct() {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            return $next($request);
        });
    }

    public function listUser() {
        $this->successStatus = 200;
        $success['success'] = true;
        $success['listUser']   = User::where('role', '!=', 'admin')->orderBy('created_at', 'desc')->get();

        return response()->json($success, $this->successStatus);
    }

    public function addUser(Request $request) {
        $user = new User;
        $user->username = $request->username;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->role = $request->role;
        $user->status = $request->status;
        $user->save();

        $this->successStatus = 200;
        $success['success']  = true;
        $success['data']     = $user;

        return response()->json($success, $this->successStatus);
    }
}
