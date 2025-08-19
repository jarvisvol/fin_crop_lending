<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-mongo', function() {
    // Create test record
    $product = Product::create([
        'name' => 'New Test Product',
        'price' => 199.99,
        'description' => 'Official MongoDB package test'
    ]);
    
    return Product::all();
});
