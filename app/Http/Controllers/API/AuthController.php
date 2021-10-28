<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Auth;

class AuthController extends Controller {

    public $successStatus = 401;

    public function login(Request $request) {
        $success['success']     = false;
        $success['token']       = null;
        $success['user_detail'] = null;

        if (Auth::attempt(['email' => $request->email, 'password' => $request->password], $request->get('remember'))) {
            $this->successStatus    = 200;
            $success['success']     = true;
            $user                   = User::where('email', request('email'))->where('status', 1)->first();
            $success['token']       = $user->createToken('MyApp')->accessToken;
            $success['user_detail'] = $user;
            $success['user_role']   = $user->getRole;
        } else {
            $success['success']     = false;
            $success['token']       = null;
            $success['user_detail'] = null;
            $success['user_role']   = null;
            $success['message']     = 'User tidak ditemukan';
        }
        return response()->json($success, $this->successStatus);
    }
}
