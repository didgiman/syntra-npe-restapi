<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function updatePassword(Request $request) {
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
            // this check is redundant because we're using exists:users,id in validation
            // keeping it for extra safety
            if(!$user){
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 404);
            }

            // function to handle duplicate response code
            $samePasswordResponse = function() {
                return response()->json([
                    'success' => false,
                    'message' => 'New Password cannot be the same as old password.',
                ], 422);
            };

            // Check if the stored password is hashed
            if (password_get_info($user->password)['algo'] !== 0) { // algo = 0 means no hashing
            try {
                // password IS hashed, verify using Hash::check
                if (Hash::check($new_password, $user->password)) {
                return $samePasswordResponse();
                }
            } catch (\Exception $e){
                // log the hash check failure
                \Log::error('Hash check failed: ' . $e->getMessage());
                
                // if Hash:: check fails, fall back to direct comparison
            if ($user->password === $new_password) {
                return $samePasswordResponse();
            }
        }
           
        } else {
            // password is NOT hashed, compare directly
            if ($user->password === $new_password) {
                return $samePasswordResponse();
            }
        }

            // hash the new password
            $hashedPassword = Hash::make($new_password);

            // update the password in the DB
            DB::table('users')
                ->where('id', $id)
                ->update(['password' => $hashedPassword]);

            // get updated user info for the response
            $user = DB::table('users')->where('id', $id)->first();
            
            // return a success response
            return response()->json([
                'success' => true,
                'message' => "successfully updated the password for {$user->first_name} {$user->last_name}.",
            ], 200);
        } catch (ValidationException $e) {
            // Custom error message for validation errors
            return response()->json([
                'success' => false,
                'message' => 'The new password must be at least 6 characters.',
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occured.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function changePassword(Request $request) {
        try {
            // validate the request to ensure user_id and new password are provided
            $request->validate([
                'id' => 'required|integer|exists:users,id',
                'old_password' => 'required|string|min:6',
                'new_password' => 'required|string|min:6',
            ]);

            // get user_id and new_password from the request
            $id = $request->input('id');
            $old_password = $request->input('old_password');
            $new_password = $request->input('new_password');

            // hash the new password
            $hashedPassword = Hash::make($new_password);

            // Hash the old password
            $hashedOldPassword = Hash::make($old_password);

            // find the user by their ID
            $user = DB::table('users')->where('id', $id)->first();

            // check if the user exists 
            // this check is redundant because we're using exists:users,id in validation
            // keeping it for extra safety
            if(!$user){
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 404);
            } elseif (!Hash::check($old_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Old password is incorrect.',
                ], 400);
            } elseif ($old_password == $new_password){
                return response()->json([
                    'success' => false,
                    'message' => 'New Password cannot be the same as old password.',
                ], 422);
            }

            // function to handle duplicate response code
            $samePasswordResponse = function() {
                return response()->json([
                    'success' => false,
                    'message' => 'New Password cannot be the same as old password.',
                ], 422);
            };

            // update the password in the DB
            DB::table('users')
                ->where('id', $id)
                ->update(['password' => $hashedPassword]);

            // get updated user info for the response
            $user = DB::table('users')->where('id', $id)->first();
            
            // return a success response
            return response()->json([
                'success' => true,
                'message' => "Successfully updated the password for {$user->first_name} {$user->last_name}",
            ], 200);
        } catch (ValidationException $e) {
            // Custom error message for validation errors
            return response()->json([
                'success' => false,
                'message' => 'The new password must be at least 6 characters.',
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occured.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
