<?php

declare(strict_types=1);

use App\Controllers\Api\ActivityController;
use App\Controllers\Api\AuthController;
use App\Controllers\Api\CallController;
use App\Controllers\Api\ComunicatoController;
use App\Controllers\Api\ContactController;
use App\Controllers\Api\DocumentController;
use App\Controllers\Api\DocumentCommentController;
use App\Controllers\Api\DocumentVerificationController;
use App\Controllers\Api\GdprConsentController;
use App\Controllers\Api\HostingDocumentController;
use App\Controllers\Api\PendingComunicatoQueueController;
use App\Controllers\Api\ProfileController;
use App\Controllers\Api\PracticeController;
use App\Controllers\Api\ProtocolController;
use App\Controllers\Api\ReportController;
use App\Controllers\Api\RoleController;
use App\Controllers\Api\RsuElectionController;
use App\Controllers\Api\UnionPermitController;
use App\Controllers\Api\UnionPermitRequestController;
use App\Controllers\Api\UnionMeetingController;
use App\Controllers\Api\UserController;
use App\Controllers\Api\WorkersAssemblyController;
use App\Controllers\Api\VotingController;
use App\Core\Response;

$auth = new AuthController($app);
$activity = new ActivityController($app);
$calls = new CallController($app);
$comunicati = new ComunicatoController($app);
$contacts = new ContactController($app);
$documents = new DocumentController($app);
$comments = new DocumentCommentController($app);
$documentVerification = new DocumentVerificationController($app);
$hostingDocuments = new HostingDocumentController($app);
$pendingQueue = new PendingComunicatoQueueController($app);
$profile = new ProfileController($app);
$practices = new PracticeController($app);
$protocol = new ProtocolController($app);
$reports = new ReportController($app);
$rsuElections = new RsuElectionController($app);
$unionPermits = new UnionPermitController($app);
$unionPermitRequests = new UnionPermitRequestController($app);
$unionMeetings = new UnionMeetingController($app);
$users = new UserController($app);
$workersAssemblies = new WorkersAssemblyController($app);
$votings = new VotingController($app);
$roles = new RoleController($app);
$gdpr = new GdprConsentController($app);

$app->router->get('/api/v1/health', static fn () => Response::json([
    'data' => [
        'status' => 'ok',
        'app' => 'myrsu-api',
    ],
]));

$app->router->post('/api/v1/auth/login', [$auth, 'login']);
$app->router->post('/api/v1/auth/logout', [$auth, 'logout']);
$app->router->get('/api/v1/me', [$auth, 'me']);

$app->router->get('/api/v1/profile', [$profile, 'show']);
$app->router->patch('/api/v1/profile', [$profile, 'update']);
$app->router->patch('/api/v1/profile/password', [$profile, 'password']);

$app->router->get('/api/v1/users', [$users, 'index']);
$app->router->post('/api/v1/users', [$users, 'store']);
$app->router->get('/api/v1/users/{id}', [$users, 'show']);
$app->router->patch('/api/v1/users/{id}', [$users, 'update']);
$app->router->post('/api/v1/users/{id}/union-logo', [$users, 'unionLogo']);
$app->router->delete('/api/v1/users/{id}', [$users, 'destroy']);

$app->router->get('/api/v1/roles', [$roles, 'roles']);
$app->router->get('/api/v1/permissions', [$roles, 'permissions']);
$app->router->post('/api/v1/users/{id}/roles', [$roles, 'replaceUserRoles']);

$app->router->get('/api/v1/gdpr/consents', [$gdpr, 'index']);
$app->router->post('/api/v1/gdpr/consents', [$gdpr, 'store']);
$app->router->get('/api/v1/users/{id}/gdpr/consents', [$gdpr, 'userIndex']);
$app->router->get('/api/v1/users/{id}/activity', [$activity, 'userIndex']);
$app->router->delete('/api/v1/activity/{id}', [$activity, 'destroy']);

$app->router->get('/api/v1/calls', [$calls, 'index']);
$app->router->post('/api/v1/calls', [$calls, 'store']);
$app->router->get('/api/v1/calls/{id}', [$calls, 'show']);
$app->router->patch('/api/v1/calls/{id}', [$calls, 'update']);
$app->router->delete('/api/v1/calls/{id}', [$calls, 'destroy']);
$app->router->post('/api/v1/calls/{id}/link-practice', [$calls, 'linkPractice']);

$app->router->get('/api/v1/documents', [$documents, 'index']);
$app->router->get('/api/v1/public/documents', [$documents, 'publicIndex']);
$app->router->get('/api/v1/documents/private', [$documents, 'privateIndex']);
$app->router->post('/api/v1/documents', [$documents, 'store']);
$app->router->post('/api/v1/comunicati', [$comunicati, 'store']);
$app->router->post('/api/v1/comunicati/{id}/generate', [$comunicati, 'generate']);
$app->router->get('/api/v1/documents/{id}/thumbnail', [$documents, 'thumbnail']);
$app->router->get('/api/v1/documents/{id}/comments', [$comments, 'publicIndex']);
$app->router->post('/api/v1/documents/{id}/comments', [$comments, 'store']);
$app->router->get('/api/v1/documents/{id}/preview', [$documents, 'preview']);
$app->router->get('/api/v1/documents/{id}/download', [$documents, 'download']);
$app->router->get('/api/v1/documents/{id}/private-preview', [$documents, 'privatePreview']);
$app->router->get('/api/v1/documents/{id}/private-download', [$documents, 'privateDownload']);
$app->router->get('/api/v1/documents/{id}/verify', [$documentVerification, 'show']);
$app->router->post('/api/v1/documents/{id}/verify-file', [$documentVerification, 'file']);
$app->router->get('/api/v1/documents/{id}', [$documents, 'show']);
$app->router->patch('/api/v1/documents/{id}', [$documents, 'update']);
$app->router->delete('/api/v1/documents/{id}', [$documents, 'destroy']);
$app->router->post('/api/v1/hosting/documents', [$hostingDocuments, 'store']);
$app->router->get('/api/v1/hosting/comunicati/pending', [$hostingDocuments, 'pendingComunicati']);
$app->router->get('/api/v1/hosting/comunicati/{id}', [$hostingDocuments, 'showPendingComunicato']);
$app->router->post('/api/v1/hosting/comunicati/{id}/complete', [$hostingDocuments, 'completeComunicato']);
$app->router->get('/api/v1/hosting/documents/pending-office', [$hostingDocuments, 'pendingOffice']);
$app->router->get('/api/v1/hosting/documents/{id}/original', [$hostingDocuments, 'pendingOfficeOriginal']);
$app->router->post('/api/v1/hosting/documents/{id}/complete', [$hostingDocuments, 'completeOffice']);
$app->router->get('/api/v1/local/comunicati/pending', [$pendingQueue, 'index']);
$app->router->post('/api/v1/local/comunicati/process', [$pendingQueue, 'process']);

$app->router->get('/api/v1/reports', [$reports, 'index']);
$app->router->get('/api/v1/reports/stats', [$reports, 'stats']);
$app->router->post('/api/v1/reports', [$reports, 'store']);
$app->router->get('/api/v1/reports/attachments/{id}/preview', [$reports, 'attachment']);
$app->router->get('/api/v1/reports/attachments/{id}/shared', [$reports, 'sharedAttachment']);
$app->router->get('/api/v1/reports/{id}', [$reports, 'show']);
$app->router->patch('/api/v1/reports/{id}/moderation', [$reports, 'moderate']);

$app->router->get('/api/v1/comments', [$comments, 'index']);
$app->router->get('/api/v1/comments/stats', [$comments, 'stats']);
$app->router->get('/api/v1/comments/document/{id}', [$comments, 'approvedByDocument']);
$app->router->patch('/api/v1/comments/{id}/moderation', [$comments, 'moderate']);

$app->router->get('/api/v1/practices', [$practices, 'index']);
$app->router->post('/api/v1/practices', [$practices, 'store']);
$app->router->get('/api/v1/practices/assignees', [$practices, 'assignees']);
$app->router->get('/api/v1/practices/{id}', [$practices, 'show']);
$app->router->patch('/api/v1/practices/{id}', [$practices, 'update']);
$app->router->post('/api/v1/practices/{id}/notes', [$practices, 'addNote']);
$app->router->post('/api/v1/practice-links', [$practices, 'link']);

$app->router->post('/api/v1/rsu-elections/analyze', [$rsuElections, 'analyze']);
$app->router->post('/api/v1/union-permits/analyze', [$unionPermits, 'analyze']);
$app->router->get('/api/v1/union-permits/allocations', [$unionPermitRequests, 'allocations']);
$app->router->post('/api/v1/union-permits/allocations', [$unionPermitRequests, 'saveAllocation']);
$app->router->get('/api/v1/union-permits/delegates', [$unionPermitRequests, 'delegates']);
$app->router->get('/api/v1/union-permits/requests', [$unionPermitRequests, 'requests']);
$app->router->post('/api/v1/union-permits/requests', [$unionPermitRequests, 'issue']);
$app->router->patch('/api/v1/union-permits/requests/{id}', [$unionPermitRequests, 'update']);
$app->router->delete('/api/v1/union-permits/requests/{id}', [$unionPermitRequests, 'destroy']);

$app->router->get('/api/v1/contacts', [$contacts, 'index']);
$app->router->post('/api/v1/institutional-contacts', [$contacts, 'storeInstitutional']);
$app->router->patch('/api/v1/institutional-contacts/{id}', [$contacts, 'updateInstitutional']);

$app->router->get('/api/v1/union-meetings', [$unionMeetings, 'index']);
$app->router->post('/api/v1/union-meetings', [$unionMeetings, 'store']);
$app->router->post('/api/v1/union-meetings/{id}/documents', [$unionMeetings, 'storeDocument']);
$app->router->post('/api/v1/union-meetings/{id}/documents/link', [$unionMeetings, 'linkDocument']);
$app->router->delete('/api/v1/union-meetings/{id}/documents/{document_id}', [$unionMeetings, 'destroyDocument']);
$app->router->get('/api/v1/union-meetings/{id}', [$unionMeetings, 'show']);
$app->router->patch('/api/v1/union-meetings/{id}', [$unionMeetings, 'update']);
$app->router->post('/api/v1/union-meetings/{id}/public-comunicato', [$unionMeetings, 'publicComunicato']);
$app->router->post('/api/v1/union-meetings/{id}/minutes', [$unionMeetings, 'minutes']);
$app->router->get('/api/v1/union-meetings/{id}/notes', [$unionMeetings, 'notes']);
$app->router->post('/api/v1/union-meetings/{id}/notes', [$unionMeetings, 'storeNote']);

$app->router->get('/api/v1/workers-assemblies', [$workersAssemblies, 'index']);
$app->router->post('/api/v1/workers-assemblies', [$workersAssemblies, 'store']);
$app->router->post('/api/v1/workers-assemblies/{id}/documents', [$workersAssemblies, 'storeDocument']);
$app->router->post('/api/v1/workers-assemblies/{id}/documents/link', [$workersAssemblies, 'linkDocument']);
$app->router->delete('/api/v1/workers-assemblies/{id}/documents/{document_id}', [$workersAssemblies, 'destroyDocument']);
$app->router->post('/api/v1/workers-assemblies/{id}/sessions/{session_id}/notes', [$workersAssemblies, 'storeSessionNote']);
$app->router->post('/api/v1/workers-assemblies/{id}/public-convocation', [$workersAssemblies, 'publicConvocation']);
$app->router->post('/api/v1/workers-assemblies/{id}/minutes', [$workersAssemblies, 'minutes']);
$app->router->patch('/api/v1/workers-assemblies/{id}/final-statement', [$workersAssemblies, 'updateFinalStatement']);
$app->router->get('/api/v1/workers-assemblies/{id}', [$workersAssemblies, 'show']);
$app->router->patch('/api/v1/workers-assemblies/{id}', [$workersAssemblies, 'update']);
$app->router->delete('/api/v1/workers-assemblies/{id}', [$workersAssemblies, 'destroy']);

$app->router->get('/api/v1/votings', [$votings, 'index']);
$app->router->post('/api/v1/votings', [$votings, 'store']);
$app->router->get('/api/v1/votings/{id}', [$votings, 'show']);
$app->router->patch('/api/v1/votings/{id}', [$votings, 'update']);
$app->router->delete('/api/v1/votings/{id}', [$votings, 'destroy']);
$app->router->post('/api/v1/votings/{id}/tokens', [$votings, 'generateTokens']);
$app->router->post('/api/v1/votings/{id}/tokens/{tokenId}/cancel', [$votings, 'cancelToken']);
$app->router->post('/api/v1/votings/{id}/close', [$votings, 'close']);
$app->router->post('/api/v1/votings/{id}/reopen', [$votings, 'reopen']);
$app->router->post('/api/v1/votings/{id}/manual-vote', [$votings, 'manualVote']);
$app->router->get('/api/v1/public/votings/open', [$votings, 'publicOpen']);
$app->router->get('/api/v1/public/votings/token/{token}', [$votings, 'publicToken']);
$app->router->post('/api/v1/public/votings/token/{token}/vote', [$votings, 'voteByToken']);

$app->router->get('/api/v1/protocol', [$protocol, 'index']);
$app->router->post('/api/v1/protocol', [$protocol, 'store']);
$app->router->get('/api/v1/protocol/{id}', [$protocol, 'show']);
$app->router->patch('/api/v1/protocol/{id}', [$protocol, 'update']);
$app->router->delete('/api/v1/protocol/{id}', [$protocol, 'destroy']);
