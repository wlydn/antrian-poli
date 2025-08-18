<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DisplayController;
use App\Http\Controllers\Cms\ContentController;

// Display publik (tv antrian)
Route::get('/', [DisplayController::class,'index'])->name('display');
Route::get('/partial/queue', [DisplayController::class,'partialQueue'])->name('display.partial.queue');
Route::get('/partial/current', [DisplayController::class,'partialCurrent'])->name('display.partial.current');

// CMS sederhana (tambahkan auth sesuai kebutuhan)
Route::prefix('cms')->name('cms.')->group(function(){
    Route::resource('contents', ContentController::class)->except(['show']);
});
