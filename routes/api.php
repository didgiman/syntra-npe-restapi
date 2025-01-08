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

    $request->validate([
        'title' => 'required|string|max:255',
        'feeling' => 'required|string|max:255',
        'estimate' => 'required|integer|min:1',
        'user_id' => 'required|integer',  // validate the user ID from request
        'deadline' => 'string|max:255',

    ]); 

    try {
        $title = $request->input('title');
        $feeling = $request->input('feeling');
        $estimate = $request->input('estimate');
        $user_id = $request->input('user_id'); // get the user ID from the request | as noted above
        $created_at = now();
        $deadline = $request->input('deadline');

        DB::insert('INSERT INTO tasks (title, feeling, estimate, created_by, created_at, deadline) VALUES (?, ?, ?, ?, ?, ?)', [$title, $feeling, $estimate, $user_id, $created_at, $deadline]);

        $lastTask = DB::select('SELECT * FROM tasks ORDER BY id DESC LIMIT 1');
    
        return response()->json([
            'success' => true,
            'message' => 'Task created successfully',
            'task' => $lastTask[0]
        ], 201);
    } catch (\Exception $e) {
        return response()->json(['succes' => 'Task creation failed', 'details' => $e->getMessage()], 400);
    }
});