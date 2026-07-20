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
use App\Repositories\PracticeNoteRepository;
use App\Repositories\PracticeRepository;
use App\Repositories\PracticeTimelineRepository;
use App\Repositories\ReportAttachmentRepository;
use App\Repositories\ReportRepository;
use App\Repositories\RolePermissionRepository;
use App\Repositories\TokenRepository;
use App\Repositories\UnionMeetingRepository;
use App\Repositories\UnionMeetingNoteRepository;
use App\Repositories\UnionMeetingDocumentRepository;
use App\Repositories\UnionMeetingParticipantRepository;
use App\Repositories\UnionPermitRepository;
use App\Repositories\UserRepository;
use App\Repositories\VotingBallotRepository;
use App\Repositories\VotingOptionRepository;
use App\Repositories\VotingRepository;
use App\Repositories\VotingTokenRepository;
use App\Repositories\WorkersAssemblyRepository;
use App\Repositories\WorkersAssemblyParticipantRepository;
use App\Repositories\WorkersAssemblyDocumentRepository;
use App\Repositories\WorkersAssemblySessionRepository;
use App\Repositories\WorkersAssemblySessionNoteRepository;
use App\Services\AuthService;
use App\Services\AntiBotService;
use App\Services\ComunicatoPdfService;
use App\Services\ComunicatoDirectPdfService;
use App\Services\DocumentSignatureService;
use App\Services\DocumentStorageService;
use App\Services\DocumentThumbnailService;
use App\Services\DocumentVerificationMetadataService;
use App\Services\FpdiUploadedPdfService;
use App\Services\HostingDocumentReceiveService;
use App\Services\HostingDocumentUploadService;
use App\Services\OfficeFileService;
use App\Services\PendingComunicatoQueueService;
use App\Services\PendingOfficeQueueService;
use App\Services\ProtocolDocumentNameService;
use App\Services\PracticeService;
use App\Services\PdfConversionService;
use App\Services\PdfLayoutService;
use App\Services\PdfImageFitService;
use App\Services\PdfQrService;
use App\Services\PdfWriterService;
use App\Services\ReportPdfService;
use App\Services\ReportService;
use App\Services\ReportAttachmentStorageService;
use App\Services\RenderedPdfUploadService;
use App\Services\UploadedDocumentPdfService;
use App\Services\UnionPermitPdfService;
use App\Services\UnionLogoStorageService;
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
    public readonly PracticeNoteRepository $practiceNotes;
    public readonly PracticeRepository $practices;
    public readonly PracticeTimelineRepository $practiceTimeline;
    public readonly PracticeService $practiceService;
    public readonly ReportRepository $reports;
    public readonly ReportAttachmentRepository $reportAttachments;
    public readonly UnionMeetingRepository $unionMeetings;
    public readonly UnionMeetingNoteRepository $unionMeetingNotes;
    public readonly UnionMeetingDocumentRepository $unionMeetingDocuments;
    public readonly UnionMeetingParticipantRepository $unionMeetingParticipants;
    public readonly UnionPermitRepository $unionPermits;
    public readonly WorkersAssemblyRepository $workersAssemblies;
    public readonly WorkersAssemblyParticipantRepository $workersAssemblyParticipants;
    public readonly WorkersAssemblyDocumentRepository $workersAssemblyDocuments;
    public readonly WorkersAssemblySessionRepository $workersAssemblySessions;
    public readonly WorkersAssemblySessionNoteRepository $workersAssemblySessionNotes;
    public readonly ActivityLogRepository $activityLogs;
    public readonly AuthService $authService;
    public readonly AntiBotService $antiBot;
    public readonly ComunicatoPdfService $comunicatoPdf;
    public readonly ComunicatoDirectPdfService $comunicatoDirectPdf;
    public readonly ReportService $reportService;
    public readonly ReportAttachmentStorageService $reportAttachmentStorage;
    public readonly RenderedPdfUploadService $renderedPdfUpload;
    public readonly DocumentSignatureService $documentSignature;
    public readonly DocumentThumbnailService $documentThumbnail;
    public readonly DocumentVerificationMetadataService $documentVerificationMetadata;
    public readonly DocumentStorageService $documentStorage;
    public readonly OfficeFileService $officeFiles;
    public readonly PdfConversionService $pdfConversion;
    public readonly FpdiUploadedPdfService $fpdiUploadedPdf;
    public HostingDocumentReceiveService $hostingDocumentReceive;
    public readonly PendingComunicatoQueueService $pendingComunicatoQueue;
    public readonly PendingOfficeQueueService $pendingOfficeQueue;
    public readonly ProtocolDocumentNameService $protocolDocumentName;
    public readonly PdfLayoutService $pdfLayout;
    public readonly PdfImageFitService $pdfImageFit;
    public readonly PdfQrService $pdfQr;
    public readonly PdfWriterService $pdfWriter;
    public readonly ReportPdfService $reportPdf;
    public readonly UploadedDocumentPdfService $uploadedDocumentPdf;
    public readonly UnionPermitPdfService $unionPermitPdf;
    public readonly UnionLogoStorageService $unionLogoStorage;
    public readonly VotingRepository $votings;
    public readonly VotingOptionRepository $votingOptions;
    public readonly VotingTokenRepository $votingTokens;
    public readonly VotingBallotRepository $votingBallots;

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
        $this->renderedPdfUpload = new RenderedPdfUploadService();
        $this->documentSignature = new DocumentSignatureService($this->signingConfig);
        $this->documentThumbnail = new DocumentThumbnailService($this->basePath);
        $this->documentVerificationMetadata = new DocumentVerificationMetadataService($this->basePath);
        $this->officeFiles = new OfficeFileService();
        $this->pdfConversion = new PdfConversionService();
        $this->documentStorage = new DocumentStorageService(
            $this->basePath,
            $this->pdfConversion,
            new HostingDocumentUploadService($this->hostingConfig)
        );
        $this->pdfLayout = new PdfLayoutService();
        $this->pdfImageFit = new PdfImageFitService();
        $this->pdfQr = new PdfQrService();
        $this->pdfWriter = new PdfWriterService();
        $this->fpdiUploadedPdf = new FpdiUploadedPdfService($this->pdfQr);
        $this->protocolDocumentName = new ProtocolDocumentNameService();
        $this->comunicatoDirectPdf = new ComunicatoDirectPdfService($this->pdfLayout, $this->pdfWriter, $this->pdfQr);
        $this->reportPdf = new ReportPdfService($this->pdfLayout, $this->pdfQr);
        $this->uploadedDocumentPdf = new UploadedDocumentPdfService(
            $this->pdfLayout,
            $this->pdfWriter,
            $this->pdfQr,
            $this->fpdiUploadedPdf
        );
        $this->unionPermitPdf = new UnionPermitPdfService($this->pdfLayout, $this->pdfWriter, $this->pdfQr);
        $this->unionLogoStorage = new UnionLogoStorageService($this->basePath);
        $this->hostingDocumentReceive = new HostingDocumentReceiveService($this->basePath, $this->hostingConfig);
        $this->pendingComunicatoQueue = new PendingComunicatoQueueService(
            $this->hostingConfig,
            $this->documentStorage,
            $this->documentSignature,
            $this->comunicatoDirectPdf
        );
        $this->pendingOfficeQueue = new PendingOfficeQueueService(
            $this->hostingConfig,
            $this->pdfConversion,
            $this->uploadedDocumentPdf,
            $this->documentSignature
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
        $this->practiceNotes = new PracticeNoteRepository($pdo);
        $this->practices = new PracticeRepository($pdo);
        $this->practiceTimeline = new PracticeTimelineRepository($pdo);
        $this->practiceService = new PracticeService($this->practices, $this->users);
        $this->reports = new ReportRepository($pdo);
        $this->reportAttachments = new ReportAttachmentRepository($pdo);
        $this->unionMeetings = new UnionMeetingRepository($pdo);
        $this->unionMeetingNotes = new UnionMeetingNoteRepository($pdo);
        $this->unionMeetingDocuments = new UnionMeetingDocumentRepository($pdo);
        $this->unionMeetingParticipants = new UnionMeetingParticipantRepository($pdo);
        $this->unionPermits = new UnionPermitRepository($pdo);
        $this->workersAssemblies = new WorkersAssemblyRepository($pdo);
        $this->workersAssemblyParticipants = new WorkersAssemblyParticipantRepository($pdo);
        $this->workersAssemblyDocuments = new WorkersAssemblyDocumentRepository($pdo);
        $this->workersAssemblySessions = new WorkersAssemblySessionRepository($pdo);
        $this->workersAssemblySessionNotes = new WorkersAssemblySessionNoteRepository($pdo);
        $this->votings = new VotingRepository($pdo);
        $this->votingOptions = new VotingOptionRepository($pdo);
        $this->votingTokens = new VotingTokenRepository($pdo);
        $this->votingBallots = new VotingBallotRepository($pdo);
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
            $error = ['message' => $exception->getMessage()];
            if ($exception->status() >= 500) {
                $errorId = bin2hex(random_bytes(6));
                error_log(sprintf(
                    '[MyRSU %s] %s in %s:%d',
                    $errorId,
                    $exception->getMessage(),
                    $exception->getFile(),
                    $exception->getLine()
                ));
                $error['detail'] = $exception->getMessage();
                $error['source'] = basename($exception->getFile()) . ':' . $exception->getLine();
                $error['error_id'] = $errorId;
            }
            Response::json([
                'error' => $error,
            ], $exception->status())->send();
        } catch (Throwable $exception) {
            $debug = (bool)getenv('APP_DEBUG');
            $errorId = bin2hex(random_bytes(6));
            error_log(sprintf(
                '[MyRSU %s] %s in %s:%d',
                $errorId,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ));
            Response::json([
                'error' => [
                    'message' => $debug ? $exception->getMessage() : 'Errore interno.',
                    'detail' => $exception->getMessage(),
                    'source' => basename($exception->getFile()) . ':' . $exception->getLine(),
                    'error_id' => $errorId,
                ],
            ], 500)->send();
        }
    }
}
