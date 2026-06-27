<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\ActivityLogRepository;
use App\Repositories\CallRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\DocumentCommentRepository;
use App\Repositories\GdprConsentRepository;
use App\Repositories\InstitutionalContactRepository;
use App\Repositories\ProtocolRepository;
use App\Repositories\PracticeLinkRepository;
use App\Repositories\PracticeRepository;
use App\Repositories\ReportAttachmentRepository;
use App\Repositories\ReportRepository;
use App\Repositories\RolePermissionRepository;
use App\Repositories\TokenRepository;
use App\Repositories\UnionMeetingRepository;
use App\Repositories\UnionMeetingNoteRepository;
use App\Repositories\UnionMeetingParticipantRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\AntiBotService;
use App\Services\ComunicatoPdfService;
use App\Services\ComunicatoDirectPdfService;
use App\Services\DocumentSignatureService;
use App\Services\DocumentStorageService;
use App\Services\DocumentThumbnailService;
use App\Services\DocumentVerificationMetadataService;
use App\Services\HostingDocumentReceiveService;
use App\Services\HostingDocumentUploadService;
use App\Services\PendingComunicatoQueueService;
use App\Services\PdfConversionService;
use App\Services\PdfLayoutService;
use App\Services\PdfImageFitService;
use App\Services\PdfWatermarkService;
use App\Services\PdfQrService;
use App\Services\PdfWriterService;
use App\Services\ReportPdfService;
use App\Services\ReportService;
use App\Services\ReportAttachmentStorageService;
use App\Services\UploadedDocumentPdfService;
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
    public readonly CallRepository $calls;
    public readonly DocumentRepository $documents;
    public readonly DocumentCommentRepository $documentComments;
    public readonly GdprConsentRepository $gdprConsents;
    public readonly InstitutionalContactRepository $institutionalContacts;
    public readonly ProtocolRepository $protocols;
    public readonly PracticeLinkRepository $practiceLinks;
    public readonly PracticeRepository $practices;
    public readonly ReportRepository $reports;
    public readonly ReportAttachmentRepository $reportAttachments;
    public readonly UnionMeetingRepository $unionMeetings;
    public readonly UnionMeetingNoteRepository $unionMeetingNotes;
    public readonly UnionMeetingParticipantRepository $unionMeetingParticipants;
    public readonly ActivityLogRepository $activityLogs;
    public readonly AuthService $authService;
    public readonly AntiBotService $antiBot;
    public readonly ComunicatoPdfService $comunicatoPdf;
    public readonly ComunicatoDirectPdfService $comunicatoDirectPdf;
    public readonly ReportService $reportService;
    public readonly ReportAttachmentStorageService $reportAttachmentStorage;
    public readonly DocumentSignatureService $documentSignature;
    public readonly DocumentThumbnailService $documentThumbnail;
    public readonly DocumentVerificationMetadataService $documentVerificationMetadata;
    public readonly DocumentStorageService $documentStorage;
    public HostingDocumentReceiveService $hostingDocumentReceive;
    public readonly PendingComunicatoQueueService $pendingComunicatoQueue;
    public readonly PdfLayoutService $pdfLayout;
    public readonly PdfImageFitService $pdfImageFit;
    public readonly PdfQrService $pdfQr;
    public readonly PdfWriterService $pdfWriter;
    public readonly ReportPdfService $reportPdf;
    public readonly UploadedDocumentPdfService $uploadedDocumentPdf;

    public function __construct(private readonly string $basePath)
    {
        $this->databaseConfig = require $this->basePath . '/config/database.php';
        $this->hostingConfig = require $this->basePath . '/config/hosting.php';
        $this->signingConfig = require $this->basePath . '/config/signing.php';

        $this->router = new Router();
        $this->antiBot = new AntiBotService();
        $this->comunicatoPdf = new ComunicatoPdfService();
        $this->reportService = new ReportService();
        $this->reportAttachmentStorage = new ReportAttachmentStorageService($this->basePath);
        $this->documentSignature = new DocumentSignatureService($this->signingConfig);
        $this->documentThumbnail = new DocumentThumbnailService($this->basePath);
        $this->documentVerificationMetadata = new DocumentVerificationMetadataService($this->basePath);
        $this->documentStorage = new DocumentStorageService(
            $this->basePath,
            new PdfConversionService(new PdfWatermarkService()),
            new HostingDocumentUploadService($this->hostingConfig)
        );
        $this->pdfLayout = new PdfLayoutService();
        $this->pdfImageFit = new PdfImageFitService();
        $this->pdfQr = new PdfQrService();
        $this->pdfWriter = new PdfWriterService();
        $this->comunicatoDirectPdf = new ComunicatoDirectPdfService($this->pdfLayout, $this->pdfWriter, $this->pdfQr);
        $this->reportPdf = new ReportPdfService($this->pdfLayout, $this->pdfQr);
        $this->uploadedDocumentPdf = new UploadedDocumentPdfService($this->pdfLayout, $this->pdfWriter, $this->pdfQr);
        $this->hostingDocumentReceive = new HostingDocumentReceiveService($this->basePath, $this->hostingConfig);
        $this->pendingComunicatoQueue = new PendingComunicatoQueueService(
            $this->hostingConfig,
            $this->documentStorage,
            $this->documentSignature,
            $this->comunicatoDirectPdf
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
        $this->calls = new CallRepository($pdo);
        $this->documents = new DocumentRepository($pdo);
        $this->documentComments = new DocumentCommentRepository($pdo);
        $this->gdprConsents = new GdprConsentRepository($pdo);
        $this->institutionalContacts = new InstitutionalContactRepository($pdo);
        $this->protocols = new ProtocolRepository($pdo);
        $this->practiceLinks = new PracticeLinkRepository($pdo);
        $this->practices = new PracticeRepository($pdo);
        $this->reports = new ReportRepository($pdo);
        $this->reportAttachments = new ReportAttachmentRepository($pdo);
        $this->unionMeetings = new UnionMeetingRepository($pdo);
        $this->unionMeetingNotes = new UnionMeetingNoteRepository($pdo);
        $this->unionMeetingParticipants = new UnionMeetingParticipantRepository($pdo);
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
