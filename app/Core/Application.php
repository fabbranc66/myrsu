<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\ActivityLogRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\GdprConsentRepository;
use App\Repositories\ProtocolRepository;
use App\Repositories\RolePermissionRepository;
use App\Repositories\TokenRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\ComunicatoPdfService;
use App\Services\DocumentHeaderService;
use App\Services\DocumentSignatureService;
use App\Services\DocumentStorageService;
use App\Services\DocumentVerificationPageService;
use App\Services\DocumentVerificationMetadataService;
use App\Services\HostingDocumentReceiveService;
use App\Services\HostingDocumentUploadService;
use App\Services\PendingComunicatoQueueService;
use App\Services\PdfConversionService;
use App\Services\PdfWatermarkService;
use Throwable;

final class Application
{
    private array $databaseConfig;
    private array $hostingConfig;
    private array $signingConfig;
    private bool $databaseReady = false;

    public readonly Router $router;
    public readonly Database $database;
    public readonly Auth $auth;
    public readonly UserRepository $users;
    public readonly TokenRepository $tokens;
    public readonly RolePermissionRepository $roles;
    public readonly DocumentRepository $documents;
    public readonly GdprConsentRepository $gdprConsents;
    public readonly ProtocolRepository $protocols;
    public readonly ActivityLogRepository $activityLogs;
    public readonly AuthService $authService;
    public readonly ComunicatoPdfService $comunicatoPdf;
    public readonly DocumentHeaderService $documentHeader;
    public readonly DocumentSignatureService $documentSignature;
    public readonly DocumentVerificationPageService $documentVerificationPage;
    public readonly DocumentVerificationMetadataService $documentVerificationMetadata;
    public readonly DocumentStorageService $documentStorage;
    public HostingDocumentReceiveService $hostingDocumentReceive;
    public readonly PendingComunicatoQueueService $pendingComunicatoQueue;

    public function __construct(private readonly string $basePath)
    {
        $this->databaseConfig = require $this->basePath . '/config/database.php';
        $this->hostingConfig = require $this->basePath . '/config/hosting.php';
        $this->signingConfig = require $this->basePath . '/config/signing.php';

        $this->router = new Router();
        $this->comunicatoPdf = new ComunicatoPdfService($this->basePath);
        $this->documentHeader = new DocumentHeaderService();
        $this->documentSignature = new DocumentSignatureService($this->signingConfig);
        $this->documentVerificationPage = new DocumentVerificationPageService();
        $this->documentVerificationMetadata = new DocumentVerificationMetadataService($this->basePath);
        $this->documentStorage = new DocumentStorageService(
            $this->basePath,
            new PdfConversionService(new PdfWatermarkService()),
            new HostingDocumentUploadService($this->hostingConfig)
        );
        $this->hostingDocumentReceive = new HostingDocumentReceiveService($this->basePath, $this->hostingConfig);
        $this->pendingComunicatoQueue = new PendingComunicatoQueueService(
            $this->hostingConfig,
            $this->documentStorage,
            $this->documentSignature,
            $this->documentVerificationPage,
            $this->comunicatoPdf
        );
    }

    public function bootDatabase(): void
    {
        if ($this->databaseReady) {
            return;
        }

        $this->database = new Database($this->databaseConfig);

        $pdo = $this->database->pdo();

        $this->users = new UserRepository($pdo);
        $this->tokens = new TokenRepository($pdo);
        $this->roles = new RolePermissionRepository($pdo);
        $this->documents = new DocumentRepository($pdo);
        $this->gdprConsents = new GdprConsentRepository($pdo);
        $this->protocols = new ProtocolRepository($pdo);
        $this->activityLogs = new ActivityLogRepository($pdo);
        $this->auth = new Auth($this->users, $this->tokens, $this->roles);
        $this->authService = new AuthService($this->users, $this->tokens, $this->activityLogs);
        $this->hostingDocumentReceive = new HostingDocumentReceiveService($this->basePath, $this->hostingConfig, $this->documents);
        $this->databaseReady = true;
    }

    public function run(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        try {
            $app = $this;
            $request = Request::capture();

            if (
                !in_array($request->path(), ['/api/v1/health', '/api/v1/hosting/documents'], true)
                && !preg_match('#^/api/v1/documents/[0-9]+/verify(-file)?$#', $request->path())
            ) {
                $this->bootDatabase();
            }

            require $this->basePath . '/routes/api.php';

            $response = $this->router->dispatch($request);
            $response->send();
        } catch (HttpException $exception) {
            Response::json([
                'error' => [
                    'message' => $exception->getMessage(),
                ],
            ], $exception->status())->send();
        } catch (Throwable $exception) {
            $debug = (bool)getenv('APP_DEBUG');
            Response::json([
                'error' => [
                    'message' => $debug ? $exception->getMessage() : 'Errore interno.',
                ],
            ], 500)->send();
        }
    }
}
