<?php

use App\Http\Controllers\Api\AdministrationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CashClosingController;
use App\Http\Controllers\Api\ClientActivationController;
use App\Http\Controllers\Api\ClientConnectionController;
use App\Http\Controllers\Api\ClientDemoController;
use App\Http\Controllers\Api\ClientSessionController;
use App\Http\Controllers\Api\CreditAuthorizationController;
use App\Http\Controllers\Api\CreditCollectionController;
use App\Http\Controllers\Api\CreditController;
use App\Http\Controllers\Api\CreditDisbursementController;
use App\Http\Controllers\Api\CreditPortfolioController;
use App\Http\Controllers\Api\CreditStatementController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentProfileController;
use App\Http\Controllers\Api\GuaranteeController;
use App\Http\Controllers\Api\IntakeController;
use App\Http\Controllers\Api\PaymentReceiptController;
use App\Http\Controllers\Api\PaymentReversalController;
use App\Http\Middleware\RequireClientAccount;
use App\Http\Middleware\RequireCreditAccess;
use App\Http\Middleware\RequireIntakeAccess;
use App\Http\Middleware\RequireStaffAccount;
use App\Http\Middleware\RequireSuperAdmin;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// Rutas protegidas de ejemplo
Route::middleware(['auth:sanctum', RequireStaffAccount::class])->group(function () {
    Route::get('/dashboard', DashboardController::class)->middleware('throttle:60,1');
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::middleware(['auth:sanctum', RequireStaffAccount::class, RequireIntakeAccess::class])->prefix('intake')->group(function () {
    $controller = IntakeController::class;
    Route::get('/options', [$controller, 'options']);
    Route::post('/promoters', [$controller, 'registerPromoter']);
    Route::get('/team-search', [$controller, 'searchTeams'])->middleware('throttle:120,1');
    Route::get('/teams', [$controller, 'teams']);
    Route::post('/teams', [$controller, 'saveTeam']);
    Route::get('/teams/{teamId}', [$controller, 'team']);
    Route::put('/teams/{teamId}', [$controller, 'saveTeam']);
    Route::patch('/teams/{teamId}/members/{membershipId}', [$controller, 'committee']);
    Route::post('/clients/lookup', [$controller, 'lookup'])->middleware('throttle:30,1');
    Route::get('/client-files', [$controller, 'applications']);
    Route::post('/client-files', [$controller, 'saveApplication']);
    Route::get('/applications', [$controller, 'applications']);
    Route::post('/applications', [$controller, 'saveApplication']);
});

Route::middleware(['auth:sanctum', RequireStaffAccount::class, RequireSuperAdmin::class])->prefix('administration')->group(function () {
    Route::get('/document-profiles/{scope}', [DocumentProfileController::class, 'show'])->where('scope', 'general|[0-9a-fA-F-]{36}');
    Route::put('/document-profiles/{scope}', [DocumentProfileController::class, 'save'])->where('scope', 'general|[0-9a-fA-F-]{36}')->middleware('throttle:20,1');
    $controller = AdministrationController::class;
    Route::get('/options', [$controller, 'options']);
    Route::get('/users', [$controller, 'users']);
    Route::post('/users', [$controller, 'saveUser']);
    Route::put('/users/{userId}', [$controller, 'saveUser'])->whereUuid('userId');
    Route::post('/roles', [$controller, 'saveRole']);
    Route::put('/roles/{roleId}', [$controller, 'saveRole'])->whereNumber('roleId');
});

Route::middleware(['auth:sanctum', RequireStaffAccount::class, RequireCreditAccess::class])->prefix('credit')->group(function () {
    $controller = CreditController::class;
    Route::get('/portfolio/export', [CreditPortfolioController::class, 'export'])->middleware('throttle:5,1');
    Route::get('/portfolio', CreditPortfolioController::class)->middleware('throttle:60,1');
    Route::post('/receipts/{id}/reverse', PaymentReversalController::class)->whereUuid('id')->middleware('throttle:10,1');
    Route::get('/receipts/{id}', [PaymentReceiptController::class, 'show'])->whereUuid('id')->middleware('throttle:60,1');
    Route::get('/applications', [$controller, 'index']);
    Route::post('/applications', [$controller, 'store'])->middleware('throttle:20,1');
    Route::get('/applications/{id}', [$controller, 'show'])->whereUuid('id');
    Route::get('/applications/{id}/statement', CreditStatementController::class)->whereUuid('id')->middleware('throttle:30,1');
    Route::get('/applications/{id}/payments', [CreditCollectionController::class, 'show'])->whereUuid('id');
    Route::post('/applications/{id}/payments', [CreditCollectionController::class, 'store'])->whereUuid('id')->middleware('throttle:30,1');
    Route::get('/applications/{id}/disbursement', [CreditDisbursementController::class, 'show'])->whereUuid('id');
    Route::post('/applications/{id}/disbursement', [CreditDisbursementController::class, 'store'])->whereUuid('id')->middleware('throttle:20,1');
    Route::get('/applications/{id}/authorization', [CreditAuthorizationController::class, 'show'])->whereUuid('id');
    Route::post('/applications/{id}/proposal', [CreditAuthorizationController::class, 'propose'])->whereUuid('id')->middleware('throttle:20,1');
    Route::post('/applications/{id}/authorization', [CreditAuthorizationController::class, 'decide'])->whereUuid('id')->middleware('throttle:20,1');
    Route::get('/applications/{id}/review', [$controller, 'review'])->whereUuid('id');
    Route::post('/applications/{id}/review', [$controller, 'updateReview'])->whereUuid('id')->middleware('throttle:30,1');
    Route::get('/teams/{teamId}/context', [$controller, 'context'])->whereUuid('teamId');
    Route::get('/rates', [$controller, 'rates']);
    Route::post('/rates', [$controller, 'saveRate']);
    Route::put('/rates/{id}', [$controller, 'saveRate'])->whereUuid('id');
});

Route::middleware(['auth:sanctum', RequireStaffAccount::class])->prefix('cash')->group(function () {
    $c = CashClosingController::class;
    Route::get('/options', [$c, 'options']);
    Route::get('/closings', [$c, 'index']);
    Route::get('/preview', [$c, 'preview']);
    Route::post('/closings', [$c, 'store'])->middleware('throttle:20,1');
    Route::get('/closings/{id}', [$c, 'show'])->whereUuid('id');
    Route::get('/closings/{id}/report', [$c, 'report'])->whereUuid('id')->middleware('throttle:20,1');
});

Route::middleware(['auth:sanctum', RequireStaffAccount::class])->prefix('documents')->group(function () {
    $c = DocumentController::class;
    Route::get('/sources/{type}/{source}', [$c, 'index'])->whereIn('type', ['file', 'credit'])->whereUuid('source');
    Route::post('/sources/{type}/{source}', [$c, 'issue'])->whereIn('type', ['file', 'credit'])->whereUuid('source')->middleware('throttle:20,1');
    Route::get('/versions/{id}', [$c, 'show'])->whereUuid('id');
    Route::post('/versions/{id}/signed', [$c, 'attach'])->whereUuid('id')->middleware('throttle:10,1');
    Route::get('/signed/{id}', [$c, 'download'])->whereUuid('id')->middleware('throttle:30,1');
});

Route::middleware(['auth:sanctum', RequireStaffAccount::class])->group(function () {
    Route::get('/credit/applications/{id}/guarantee', [GuaranteeController::class, 'show'])->whereUuid('id');
    Route::post('/credit/applications/{id}/guarantee', [GuaranteeController::class, 'store'])->whereUuid('id')->middleware('throttle:10,1');
    Route::post('/guarantees/{id}/reverse', [GuaranteeController::class, 'reverse'])->whereUuid('id')->middleware('throttle:10,1');
});

Route::middleware(['auth:sanctum', RequireStaffAccount::class])->prefix('client-access')->group(function () {
    Route::post('/search', [ClientActivationController::class, 'search'])->middleware('throttle:20,1');
    Route::post('/clients/{client}/activation', [ClientActivationController::class, 'issue'])->whereUuid('client')->middleware('throttle:5,1,client-activation-issue');
});
Route::post('/client/activate', [ClientActivationController::class, 'activate'])->middleware('throttle:5,1,client-activation-redeem');

Route::post('/client/login', [ClientSessionController::class, 'login'])->middleware('throttle:5,1,client-login');
Route::middleware(['auth:sanctum', RequireClientAccount::class])->prefix('client')->group(function () {
    Route::get('/me', [ClientSessionController::class, 'me']);
    Route::post('/logout', [ClientSessionController::class, 'logout']);
});

Route::get('/client/connection', ClientConnectionController::class)->middleware('throttle:10,1,client-connection');

Route::get('/client/demo', [ClientDemoController::class, 'show'])->middleware(['auth:sanctum', RequireClientAccount::class]);
Route::get('/client/demo-receipts/{receipt}', [ClientDemoController::class, 'receipt'])->name('client.demo.receipt')->middleware(['signed', 'throttle:30,1,demo-receipt']);
