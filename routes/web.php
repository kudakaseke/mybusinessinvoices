<?php

use App\Http\Controllers\CreditNoteController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\StatementController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('dashboard', [WorkspaceController::class, 'overview'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('credit-notes', [CreditNoteController::class, 'index'])->name('credit-notes.index');
    Route::get('credit-notes/create', [CreditNoteController::class, 'create'])->name('credit-notes.create');
    Route::post('invoices/{record}/credit-notes', [CreditNoteController::class, 'store'])->whereNumber('record')->name('credit-notes.store');
    Route::get('credit-notes/{note}/download', [CreditNoteController::class, 'download'])->whereNumber('note')->name('credit-notes.download');
    Route::get('analytics', [WorkspaceController::class, 'overview'])->name('analytics');
    Route::get('reports', [WorkspaceController::class, 'overview'])->name('reports');
    Route::get('calendar', [WorkspaceController::class, 'calendar'])->name('calendar');
    Route::get('statements', [StatementController::class, 'index'])->name('statements');
    Route::get('statements/download', [StatementController::class, 'download'])->name('statements.download');
    Route::get('statements/excel', [StatementController::class, 'excel'])->name('statements.excel');
    Route::get('settings', [WorkspaceController::class, 'settings'])->name('settings');
    Route::put('settings', [WorkspaceController::class, 'updateSettings'])->name('settings.update');
    Route::get('expenses/{record}/receipt', [WorkspaceController::class, 'receipt'])->whereNumber('record')->name('expenses.receipt');
    Route::get('{recordKind}', [WorkspaceController::class, 'index'])->where('recordKind', 'clients|products|expenses')->name('records.index');
    Route::get('{recordKind}/create', [WorkspaceController::class, 'form'])->where('recordKind', 'clients|products|expenses')->name('records.create');
    Route::get('{recordKind}/{record}/edit', [WorkspaceController::class, 'form'])->where('recordKind', 'clients|products|expenses')->whereNumber('record')->name('records.edit');
    Route::post('{recordKind}', [WorkspaceController::class, 'save'])->where('recordKind', 'clients|products|expenses')->name('records.store');
    Route::put('{recordKind}/{record}', [WorkspaceController::class, 'save'])->where('recordKind', 'clients|products|expenses')->whereNumber('record')->name('records.update');
    Route::get('{kind}', [DocumentController::class, 'index'])->where('kind', 'invoices|quotations')->name('documents.index');
    Route::get('{kind}/create', [DocumentController::class, 'create'])->where('kind', 'invoices|quotations')->name('documents.create');
    Route::post('{kind}', [DocumentController::class, 'store'])->where('kind', 'invoices|quotations')->name('documents.store');
    Route::post('{kind}/review', [DocumentController::class, 'review'])->where('kind', 'invoices|quotations')->name('documents.review');
    Route::post('{kind}/resume', [DocumentController::class, 'resume'])->where('kind', 'invoices|quotations')->name('documents.resume');
    Route::patch('invoices/{record}/payment', [DocumentController::class, 'updatePayment'])->whereNumber('record')->name('invoices.payment');
    Route::get('{kind}/{record}/download', [DocumentController::class, 'download'])->where('kind', 'invoices|quotations')->whereNumber('record')->name('documents.download');
    Route::get('{kind}/{record}/edit', [DocumentController::class, 'edit'])->where('kind', 'invoices|quotations')->whereNumber('record')->name('documents.edit');
    Route::get('{kind}/{record}', [DocumentController::class, 'show'])->where('kind', 'invoices|quotations')->whereNumber('record')->name('documents.show');
    Route::put('{kind}/{record}', [DocumentController::class, 'update'])->where('kind', 'invoices|quotations')->whereNumber('record')->name('documents.update');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
