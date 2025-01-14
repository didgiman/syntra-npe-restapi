<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
{
    try {
        // Validate the incoming request
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Get the email and password from the request
        $email = $request->input('email');
        $password = $request->input('password');

        // Check if the email exists in the database
        $user = DB::table('users')->where('email', $email)->first();

        // If the email doesn't exist in the DB, return error
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email address.',

            ], 401); // Unauthorized
        }

        // Verify the password
        if (!Hash::check($password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password.',
            ], 401); // Unauthorized
        }

        // remove fields you don't want to show in the response
        unset($user->password);
        unset($user->created_at);

        // When the credentials are valid, return success response
        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'user' => $user,
        ], 200); // OK
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'An unexpected error occurred.',
            'error' => $e->getMessage(), // Log the actual error message for debugging
        ], 500); // Internal Server Error
    }
}
}
