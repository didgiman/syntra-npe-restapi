<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PasswordController extends Controller
{
    public function updatePassword(request $request) {
        try {
            // validate the request to ensure user_id and new password are provided
            $request->validate([
                'id' => 'required|integer|exists:users,id',
                'new_password' => 'required|string|min:6',
            ]);

            // get user_id and new_password from the request
            $id = $request->input('id');
            $new_password = $request->input('new_password');

            // find the user by their ID
            $user = DB::table('users')->where('id', $id)->first();

            // check if the user exists
            if(!$user){
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 404);
            }
            // hash the new password
            $hashedPassword = Hash::make($new_password);

            // update the password in the DB
            DB::table('users')
                ->where('id', $id)
                ->update(['password' => $hashedPassword]);

            // return a success response
            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occured.',
            ], 500);
        }
    }
}
