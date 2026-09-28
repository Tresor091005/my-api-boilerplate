<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Commitment\Http\Controllers\GuestAccessController;
use Lahatre\Commitment\Http\Controllers\GuestCommitmentController;
use Lahatre\Commitment\Http\Controllers\GuestReviewController;
use Lahatre\Commitment\Http\Controllers\ServiceCommitmentController;
use Lahatre\Commitment\Http\Controllers\ServiceDeliverableController;
use Lahatre\Commitment\Http\Controllers\ServiceEvidenceController;
use Lahatre\Commitment\Http\Middleware\ResolveGuestAccess;

Route::group([
    'as'         => 'lahatre.commitment.',
    'prefix'     => 'v1/commitment',
    'middleware' => 'api',
], function (): void {
    Route::group(['middleware' => 'auth.api'], function (): void {
        Route::get('service-commitments', [ServiceCommitmentController::class, 'index'])->name('service-commitments.index');
        Route::post('service-commitments', [ServiceCommitmentController::class, 'store'])->name('service-commitments.store');
        Route::get('service-commitments/{commitment}', [ServiceCommitmentController::class, 'show'])->name('service-commitments.show');
        Route::patch('service-commitments/{commitment}', [ServiceCommitmentController::class, 'update'])->name('service-commitments.update');
        Route::get('service-commitments/{commitment}/proposals', [ServiceCommitmentController::class, 'proposals'])->name('service-commitments.proposals.index');
        Route::post('service-commitments/{commitment}/proposals', [ServiceCommitmentController::class, 'submitProposal'])->name('service-commitments.proposals.store');
        Route::post('service-commitments/{commitment}/closure-requests', [ServiceCommitmentController::class, 'requestClosure'])->name('service-commitments.closure-requests.store');
        Route::post('service-commitments/{commitment}/reopening-requests', [ServiceCommitmentController::class, 'requestReopening'])->name('service-commitments.reopening-requests.store');
        Route::get('service-commitments/{commitment}/report', [ServiceCommitmentController::class, 'report'])->name('service-commitments.report.show');
        Route::get('service-commitments/{commitment}/events', [ServiceCommitmentController::class, 'events'])->name('service-commitments.events.index');
        Route::get('service-commitments/{commitment}/deliverables', [ServiceDeliverableController::class, 'index'])->name('service-commitments.deliverables.index');
        Route::post('service-commitments/{commitment}/deliverables', [ServiceDeliverableController::class, 'store'])->name('service-commitments.deliverables.store');
        Route::get('service-commitments/{commitment}/deliverables/{unit}', [ServiceDeliverableController::class, 'show'])->name('service-commitments.deliverables.show');
        Route::patch('service-commitments/{commitment}/deliverables/{unit}', [ServiceDeliverableController::class, 'update'])->name('service-commitments.deliverables.update');
        Route::get('service-commitments/{commitment}/deliverables/{unit}/evidence', [ServiceEvidenceController::class, 'index'])->name('service-commitments.deliverables.evidence.index');
        Route::post('service-commitments/{commitment}/deliverables/{unit}/evidence', [ServiceEvidenceController::class, 'store'])->name('service-commitments.deliverables.evidence.store');
    });

    Route::post('guest/access-challenges', [GuestAccessController::class, 'challenge'])->middleware('throttle:auth')->name('guest.access-challenges.store');
    Route::post('guest/access-sessions', [GuestAccessController::class, 'session'])->middleware('throttle:auth')->name('guest.access-sessions.store');
    Route::group(['middleware' => [ResolveGuestAccess::class]], function (): void {
        Route::get('guest/service-commitments/{reference}', [GuestCommitmentController::class, 'show'])->name('guest.service-commitments.show');
        Route::get('guest/service-commitments/{reference}/deliverables', [GuestCommitmentController::class, 'deliverables'])->name('guest.service-commitments.deliverables.index');
        Route::get('guest/service-commitments/{reference}/evidence', [GuestCommitmentController::class, 'evidence'])->name('guest.service-commitments.evidence.index');
        Route::get('guest/service-commitments/{reference}/reviews', [GuestCommitmentController::class, 'reviews'])->name('guest.service-commitments.reviews.index');
        Route::get('guest/service-commitments/{reference}/evidence/{evidence}/files', [GuestCommitmentController::class, 'attachments'])->name('guest.service-commitments.evidence.files.index');
        Route::get('guest/service-commitments/{reference}/report', [GuestCommitmentController::class, 'report'])->name('guest.service-commitments.report.show');
        Route::get('guest/service-commitments/{reference}/evidence/{evidence}/files/{attachment}/content', [GuestCommitmentController::class, 'content'])->name('guest.service-commitments.evidence.files.content');
        Route::post('guest/service-commitments/{reference}/proposal-reviews', [GuestReviewController::class, 'proposal'])->name('guest.service-commitments.proposal-reviews.store');
        Route::post('guest/service-commitments/{reference}/evidence-reviews', [GuestReviewController::class, 'evidence'])->name('guest.service-commitments.evidence-reviews.store');
        Route::post('guest/service-commitments/{reference}/closure-reviews', [GuestReviewController::class, 'closure'])->name('guest.service-commitments.closure-reviews.store');
        Route::post('guest/service-commitments/{reference}/reopening-reviews', [GuestReviewController::class, 'reopening'])->name('guest.service-commitments.reopening-reviews.store');
    });
});
