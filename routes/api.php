<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordController;
use Illuminate\Support\Facades\Http;

// Route for getting authenticated user (Sanctum)
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Get all tasks
Route::get('/tasks', function () {
    $tasks = DB::select('SELECT * FROM tasks');
    return response()->json($tasks);
});


// Route for authentication (login)
Route::post('/login', [AuthController::class, 'login']);

// Route to update the password
Route::put('/update-pw', [PasswordController::class, 'updatePassword']);
Route::put('/change-password', [PasswordController::class, 'changePassword']);

// Create a new task
Route::post('/tasks', function (\Illuminate\Http\Request $request) {

    try {

        $request->validate([
            'title' => 'required|string|max:255',
            'feeling' => 'required|integer|min:1',
            'estimate' => 'required|numeric|min:0',
            'user_id' => 'required|integer',  // validate the user ID from request
        ]);

        // logs the received data
        \Log::info('Received task data:', $request->all());

        $title = $request->input('title');
        $feeling = $request->input('feeling');
        $estimate = $request->input('estimate');
        $user_id = $request->input('user_id'); // get the user ID from the request | as noted above
        $deadline = $request->input('deadline') ?? null; // ?? null => makes this field optional
        $started_at = $request->input('started_at') ?? null; // ?? null => makes this field optional
        $ended_at = $request->input('ended_at') ?? null; // ?? null => makes this field optional
        $recurring = $request->input('recurring') ?? null; // ?? null => makes this field optional

        // with Query builder instead of raw SQL
        $taskId = DB::table('tasks')->insertGetId([
            'title' => $title,
            'feeling' => $feeling,
            'estimate' => $estimate,
            'user_id' => $user_id,
            'deadline' => $deadline,
            'started_at' => $started_at,
            'ended_at' => $ended_at,
            'recurring' => $recurring
        ]);   
        
        // raw SQL
        // DB::insert('INSERT INTO tasks (title, feeling, estimate, user_id, deadline, started_at, ended_at) VALUES (?, ?, ?, ?, ?, ?, ?)', [$title, $feeling, $estimate, $user_id, $deadline, $started_at, $ended_at]);

        // $lastTask = DB::select('SELECT * FROM tasks WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$user_id]); // this will sort the DB by ID (highest > lowest) but: returns an array of results (!)
        
        // Query builder
        $lastTask = DB::table('tasks')->where('id', $taskId)->first();

        
        return response()->json([
            'success' => true,
            'message' => 'Task created successfully.',
            // wih raw SQL
            // 'task' => $lastTask[0], // makes sure that we access the first (and only) element in the array., 
            
            // with Query builder
            'task' => $lastTask,
        ], 201);

        // with Query builder

        
    
    } catch (\illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Missing value in required field.',
            'errors' =>$e->errors()
        ], 400);

    } catch (\Exception $e) {
        // logging for debug
        \Log::error('Task creation failed: ' . $e->getMessage());
        \Log::error('Stack trace: ' . $e->getTraceAsString());

        return response()->json([
            'success' => false,
            'message' => 'An unexpected error occured: ' . $e->getMessage(),
            // for debugging
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ], 500);
    }
});

// Update a task by ID
Route::put('/tasks/{id}', function (\Illuminate\Http\Request $request, $id) {

    try {

        $request->validate([
            'title' => 'required|string|max:255',
            'feeling' => 'required|integer|min:1',
            'estimate' => 'required|numeric|min:0',
            'user_id' => 'required|integer',  // validate the user ID from request
    
        ]); 

        $title = $request->input('title');
        $feeling = $request->input('feeling');
        $estimate = $request->input('estimate');
        $user_id = $request->input('user_id');
        $deadline = $request->input('deadline');
        $started_at = $request->input('started_at');
        $ended_at = $request->input('ended_at');
        $recurring = $request->input('recurring');
    
    
        $affected = DB::update('UPDATE tasks SET title = ?, feeling = ?, estimate = ?, deadline = ?, started_at = ?, ended_at = ?, recurring = ? WHERE id = ?', [$title,$feeling, $estimate, $deadline, $started_at, $ended_at, $recurring, $id]);
        
        if ($affected === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No changes were made.'
            ], 404);
        }

        $updatedTask = DB::select('SELECT * FROM tasks WHERE id = ?', [$id]);

        return response()->json([
            'success' => true,
            'message' => 'Task updated successfully.',
            'task' => $updatedTask[0], // makes sure that we access the first (and only) element in the array.,
        ], 200);

    } catch (\illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Missing value in required field.',
            'errors' =>$e->errors()
        ], 400);
    
    }catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'An error occurred while processing your request. Please try again later.',
        ], 500);
    }
});

// Delete a task by ID
Route::delete('/tasks/{id}', function ($id) {

    try {
        $deleted = DB::delete('DELETE FROM tasks WHERE id = ?', [$id]);
        if ($deleted === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found.',
            ], 404);
    }
    return response()->json([
        'success' => true,
        'message' => 'Task deleted successfully.'
    ], 200);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'An error occurred while processing your request. Please try again later.'
        ], 500);
    }
    
});

// usertasks endpoint to GET all tasks for a specific user
Route::get('/usertasks/{user_id}', function ($user_id) {
    try {

        $finishedOnly = request()->query('finishedOnly', false);

        // // validate the user_id
        // if (!DB::table('users')->where('id', $user_id)->exists()) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'User does not exist.',
        //         'errors' =>$e->errors()
        //     ], 404);
        // }

        $query = 'SELECT * FROM tasks WHERE user_id = ?';
        $params = [$user_id];

        if ($finishedOnly) {
            $query .= ' AND ended_at IS NOT NULL';
        } else {
            $query .= ' AND ended_at IS NULL';
        }

        $tasks = DB::select($query, $params);

        if (empty($tasks)) {
            return response()->json([
                'success' => true,
                'message' => 'No tasks found for the specified user.',
                'tasks' => $tasks
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Retrieved tasks successfully.',
            'tasks' => $tasks
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'An error occurred while retrieving tasks. Please try again later.',
        ], 500);
    }
});

// talk to ChatGPT
Route::post('/chatgpt', function (Request $request) {
    // This method will perform a single request to ChatGPT
    // and return the response as recieved

    try {

        $apiUrl = 'https://api.openai.com/v1/chat/completions';
        $token = env('OPENAI_API_KEY');

        if (empty($token)) {
            throw new \Exception('No API key found.');
        }

        $jsonString = $request->getContent();

        $response = Http::withOptions([
            'verify' => false
        ])->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ])->withBody(
            $jsonString, 'application/json'
        )->post($apiUrl);

        if ($response->successful()) {
            // Process the response data
            $data = $response->json();
        } else {
            // Handle the error
            throw new \Exception('Failed to connect to ChatGPT: ' . $response->body());
        }

        return response()->json([
            'success' => true,
            'message' => 'Talked to ChatGPT.',
            'response' => $data
        ], 200);

    
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'An error occurred while processing your request. Please try again later.',
            'error' => $e->getMessage()
        ], 500);
    }
});

/* START USERS ENDPOINTS */

// Get all users
Route::get('/users', function() {
    $users = DB::select('SELECT * FROM users');
    return response()->json($users);
});

// Create a new user
Route::post('/users', function (\Illuminate\Http\Request $request) {

    try {

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|max:255|unique:users,email',
            'password' => 'required|string|max:255'
        ]);

        $first_name = $request->input('first_name');
        $last_name = $request->input('last_name');
        $email = $request->input('email');
        $password = $request->input('password');

        $password = bcrypt($request->input('password')); // hashes a users password using bcrypt (Laravel default)
        DB::insert('INSERT INTO users (first_name, last_name, email, password) VALUES (?, ?, ?, ?)', [$first_name, $last_name, $email, $password]);



        $lastUser = DB::select('SELECT * FROM users ORDER BY id DESC LIMIT 1'); // this will sort the DB by ID (highest > lowest) but: returns an array of results (!)
        $user = $lastUser[0];

        // remove fields you don't want to show in the response
        unset($user->password);
        unset($user->created_at);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'user' => $user,
        ], 201);

    
    } catch (\Illuminate\Validation\ValidationException $e) {
        $errors = $e->errors();
        
        // first check if any required fields are missing
        foreach ($errors as $field => $messages) {
            if (in_array('The '.$field.' field is required.', $messages)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing value in a required field.',
                    'errors' => $errors
                ], 400);
            }
        }

        // then check for duplicate email
        if (array_key_exists('email', $errors)) {
            return response()->json([
                'success' => false,
                'message' => 'A user with that email already exists.',
            ], 409);
        }

        // For any other validation errors
        return response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $errors
        ], 400);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Unexpected error.',
        ], 500);
    }
});

// Delete a user by ID
Route::delete('/users/{id}', function ($id) {

    try {
        $deleted = DB::delete('DELETE FROM users WHERE id = ?', [$id]);
        if ($deleted === 0) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
    }
    return response()->json([
        'success' => true,
        'message' => 'User deleted successfully.'
    ], 200);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'An error occurred while processing your request. Please try again later.'
        ], 500);
    }
    
});

// Get specific user by ID
Route::get('/users/{id}', function ($id) {
    try {
        $user = DB::select('SELECT * FROM users WHERE id = ?', [$id]);
        if (empty($user)) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }
        unset($user[0]->password); // Remove password
        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully.',
            'user' => $user[0]
        ], 200);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'An error occurred while processing your request. Please try again later.'
        ], 500);
    }
    
});

// Update existing user
Route::put('/users/{id}', function (\Illuminate\Http\Request $request, $id) {

    try {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|max:255',  
        ]); 

        $first_name = $request->input('first_name');
        $last_name = $request->input('last_name');
        $email = $request->input('email');
        $settings = $request->input('settings', null);

        // First check that there is no other user with the same email address
        $duplicateEmail = DB::select('SELECT id FROM users WHERE email = ? AND id != ?', [$email, $id]);

        if (!empty($duplicateEmail)) {
            return response()->json([
                'success' => false,
                'message' => 'A user with that email address allready exists.'
            ], 400);
        }
    
        // Continue with the update
        $affected = DB::update('
            UPDATE users 
            SET first_name = ?,
                last_name = ?,
                email = ?,
                settings = ? 
            WHERE id = ?
            ',
            [$first_name, $last_name, $email, $settings, $id]
        );
        
        // if ($affected === 0) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'No changes were made.'
        //     ], 404);
        // }

        $updatedUser = DB::select('SELECT * FROM users WHERE id = ?', [$id]);
        unset($updatedUser[0]->password); // Remove password

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'user' => $updatedUser[0], // makes sure that we access the first (and only) element in the array.,
        ], 200);

    } catch (\illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Missing value in required field.',
            'errors' =>$e->errors()
        ], 400);
    
    }catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'An error occurred while processing your request. Please try again later.',
            'error' => $e->getMessage()
        ], 500);
    }
});

/* END USERS ENDPOINTS */