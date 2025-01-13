<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Get all tasks
Route::get('/tasks', function () {
    $tasks = DB::select('SELECT * FROM tasks');
    return response()->json($tasks);
});

// Create a new task
Route::post('/tasks', function (\Illuminate\Http\Request $request) {

    try {

        $request->validate([
            'title' => 'required|string|max:255',
            'feeling' => 'required|integer|min:1',
            'estimate' => 'required|numeric|min:0',
            'user_id' => 'required|integer',  // validate the user ID from request
            // 'deadline' => 'string|max:255',
    
        ]);

        $title = $request->input('title');
        $feeling = $request->input('feeling');
        $estimate = $request->input('estimate');
        $user_id = $request->input('user_id'); // get the user ID from the request | as noted above
        $deadline = $request->input('deadline');

        DB::insert('INSERT INTO tasks (title, feeling, estimate, user_id, deadline) VALUES (?, ?, ?, ?, ?)', [$title, $feeling, $estimate, $user_id, $deadline]);

        $lastTask = DB::select('SELECT * FROM tasks WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$user_id]); // this will sort the DB by ID (highest > lowest) but: returns an array of results (!)
    
        return response()->json([
            'success' => true,
            'message' => 'Task created successfully.',
            'task' => $lastTask[0] // makes sure that we access the first (and only) element in the array.
        ], 201);

    
    } catch (\illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Missing value in required field.'
        ], 400);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Missing expected entry in a required field.'
        ], 400);
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
            // 'deadline' => 'string|max:255',
    
        ]); 

        $title = $request->input('title');
        $feeling = $request->input('feeling');
        $estimate = $request->input('estimate');
        $user_id = $request->input('user_id');
        $deadline = $request->input('deadline');
    
    
        $affected = DB::update('UPDATE tasks SET title = ?, feeling = ?, estimate = ?, deadline = ? WHERE id = ?', [$title,$feeling, $estimate, $deadline, $id]);
        
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
            'task' => $updatedTask[0] // makes sure that we access the first (and only) element in the array.
        ], 200);

    } catch (\illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Missing value in required field.'
        ], 400);
    
    }catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'An error occurred while processing your request. Please try again later.'
        ], 500);
    }
});

// Delete a task by ID
Route::delete('/tasks/{id}', function ($id) {

    try {
        $deleted = DB::delete('DELETE FROM tasks WHERE id = ?', [$id]);
        if ($deleted === 0) {
            return response()->json([
                'message' => 'Task not found.'
            ], 404);
    }
    return response()->json([
        'success' => true,
        'message' => 'Task deleted successfully.'
    ], 200);
    } catch (Exception $e) {
        return response()->json([
            'message' => 'An error occurred while processing your request. Please try again later.'
        ], 500);
    }
    
});

// usertasks endpoint to GET all tasks for a specific user
Route::get('/usertasks/{user_id}', function ($user_id) {
    try {
        // validate the user_id
        if (!DB::table('users')->where('id', $user_id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'User does not exist.'
            ], 404);
        }

        $tasks = DB::select('SELECT * FROM tasks WHERE user_id = ? AND ended_at IS NULL', [$user_id]);

        if (empty($tasks)) {
            return response()->json([
                'success' => false,
                'message' => 'No tasks found for the specified user.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'tasks' => $tasks
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'An error occurred while retrieving tasks. Please try again later.'
        ], 500);
    }
});