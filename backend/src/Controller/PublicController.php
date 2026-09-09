<?php

namespace App\Controller;

use App\Application\UseCase\ContactMessage\CreateContactMessage\CreateContactMessageCommand;
use App\Application\UseCase\ContactMessage\CreateContactMessage\CreateContactMessageUseCase;
use App\Application\UseCase\Inscription\CreateInscriptionDraft\CreateInscriptionDraftCommand;
use App\Application\UseCase\Inscription\CreateInscriptionDraft\CreateInscriptionDraftUseCase;
use App\Application\UseCase\Inscription\DeleteInscriptionDraft\DeleteInscriptionDraftCommand;
use App\Application\UseCase\Inscription\DeleteInscriptionDraft\DeleteInscriptionDraftUseCase;
use App\Application\UseCase\Inscription\DeleteInscriptionDraftDocument\DeleteInscriptionDraftDocumentCommand;
use App\Application\UseCase\Inscription\DeleteInscriptionDraftDocument\DeleteInscriptionDraftDocumentUseCase;
use App\Application\UseCase\Inscription\GetInscriptionDraft\GetInscriptionDraftCommand;
use App\Application\UseCase\Inscription\GetInscriptionDraft\GetInscriptionDraftUseCase;
use App\Application\UseCase\Inscription\SaveInscriptionDraft\SaveInscriptionDraftCommand;
use App\Application\UseCase\Inscription\SaveInscriptionDraft\SaveInscriptionDraftUseCase;
use App\Application\UseCase\Inscription\UploadInscriptionDraftDocument\UploadInscriptionDraftDocumentCommand;
use App\Application\UseCase\Inscription\UploadInscriptionDraftDocument\UploadInscriptionDraftDocumentUseCase;
use App\Application\UseCase\License\CreateCheckout\CreateCheckoutCommand;
use App\Application\UseCase\License\CreateCheckout\CreateCheckoutUseCase;
use App\Application\UseCase\License\GetLicenseForPayment\GetLicenseForPaymentCommand;
use App\Application\UseCase\License\GetLicenseForPayment\GetLicenseForPaymentUseCase;
use App\Application\UseCase\License\HandleHelloAssoWebhook\HandleHelloAssoWebhookCommand;
use App\Application\UseCase\License\HandleHelloAssoWebhook\HandleHelloAssoWebhookUseCase;
use App\Application\UseCase\License\SubmitLicenseRequest\SubmitLicenseRequestCommand;
use App\Application\UseCase\License\SubmitLicenseRequest\SubmitLicenseRequestUseCase;
use App\Application\UseCase\License\UploadLicenseRequestDocument\UploadLicenseRequestDocumentCommand;
use App\Application\UseCase\License\UploadLicenseRequestDocument\UploadLicenseRequestDocumentUseCase;
use App\Common\Service\InscriptionsStatusProvider;
use App\Common\Service\MemberMediaStorage;
use App\Common\Service\SeasonProvider;
use App\Entity\Enum\MemberNationality;
use App\Repository\GameRepository;
use App\Repository\SalleClosureRepository;
use App\Repository\TeamRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/api/public', name: 'api_public_')]
class PublicController extends AbstractController
{
    /** Token de brouillon : 32 octets aléatoires en hexa, comme l'accessToken d'une licence. */
    private const DRAFT_TOKEN = ['token' => '[0-9a-f]{64}'];

    /** Slots déposables publiquement à l'inscription. */
    private const DOCUMENT_KEYS = ['systemKey' => 'identity_photo|id_card|medical_certificate|attestation'];

    #[Route('/contact-message', name: 'contact_message_create', methods: ['POST'])]
    public function createContactMessage(
        #[MapRequestPayload] CreateContactMessageCommand $command,
        CreateContactMessageUseCase $useCase
    ): Response {
        return $useCase->execute($command);
    }

    // ── Brouillon d'inscription ─────────────────────────────────────────────
    //
    // Les pièces sont déposées sur le brouillon, dès qu'elles sont choisies, et
    // rattachées au membre à la soumission. Le token du brouillon est le seul
    // secret qui autorise ces routes (même schéma que le magic link), d'où le
    // `requirements` : un token mal formé est écarté par le routeur.

    #[Route('/inscription-draft', name: 'inscription_draft_create', methods: ['POST'])]
    public function createInscriptionDraft(
        #[MapRequestPayload] CreateInscriptionDraftCommand $command,
        CreateInscriptionDraftUseCase $useCase,
    ): Response {
        return $useCase->execute($command);
    }

    #[Route('/inscription-draft/{token}', name: 'inscription_draft_get', methods: ['GET'], requirements: self::DRAFT_TOKEN)]
    public function getInscriptionDraft(
        string $token,
        GetInscriptionDraftUseCase $useCase,
    ): Response {
        return $useCase->execute(new GetInscriptionDraftCommand($token));
    }

    #[Route('/inscription-draft/{token}', name: 'inscription_draft_save', methods: ['PUT'], requirements: self::DRAFT_TOKEN)]
    public function saveInscriptionDraft(
        string $token,
        Request $request,
        SaveInscriptionDraftUseCase $useCase,
    ): Response {
        // Corps libre (le formulaire évolue) et lu à la main : `toArray()`
        // jetterait une JsonException sur un corps invalide, donc une 500. Le
        // use case borne et filtre ce qu'il accepte.
        $body = json_decode((string) $request->getContent(), true);
        $payload = \is_array($body) && \is_array($body['payload'] ?? null) ? $body['payload'] : [];

        return $useCase->execute(new SaveInscriptionDraftCommand($token, $payload));
    }

    #[Route('/inscription-draft/{token}', name: 'inscription_draft_delete', methods: ['DELETE'], requirements: self::DRAFT_TOKEN)]
    public function deleteInscriptionDraft(
        string $token,
        DeleteInscriptionDraftUseCase $useCase,
    ): Response {
        return $useCase->execute(new DeleteInscriptionDraftCommand($token));
    }

    #[Route(
        '/inscription-draft/{token}/document/{systemKey}',
        name: 'inscription_draft_document_upload',
        methods: ['POST'],
        requirements: self::DRAFT_TOKEN + self::DOCUMENT_KEYS,
    )]
    public function uploadInscriptionDraftDocument(
        string $token,
        string $systemKey,
        #[MapUploadedFile([new Assert\File(maxSize: '6Mi', mimeTypes: MemberMediaStorage::MIME_TYPES)])]
        UploadedFile $file,
        UploadInscriptionDraftDocumentUseCase $useCase,
    ): Response {
        return $useCase->execute(new UploadInscriptionDraftDocumentCommand($token, $systemKey, $file));
    }

    #[Route(
        '/inscription-draft/{token}/document/{systemKey}',
        name: 'inscription_draft_document_delete',
        methods: ['DELETE'],
        requirements: self::DRAFT_TOKEN + self::DOCUMENT_KEYS,
    )]
    public function deleteInscriptionDraftDocument(
        string $token,
        string $systemKey,
        DeleteInscriptionDraftDocumentUseCase $useCase,
    ): Response {
        return $useCase->execute(new DeleteInscriptionDraftDocumentCommand($token, $systemKey));
    }

    #[Route('/license-request', name: 'license_request', methods: ['POST'])]
    public function submitLicenseRequest(
        #[MapRequestPayload] SubmitLicenseRequestCommand $command,
        SubmitLicenseRequestUseCase $useCase
    ): Response {
        return $useCase->execute($command);
    }

    #[Route('/license-request/{token}/document/{systemKey}', name: 'license_document', methods: ['POST'], requirements: self::DOCUMENT_KEYS)]
    public function uploadLicenseRequestDocument(
        string $token,
        string $systemKey,
        #[MapUploadedFile([new Assert\File(maxSize: '6Mi', mimeTypes: MemberMediaStorage::MIME_TYPES)])]
        UploadedFile $file,
        UploadLicenseRequestDocumentUseCase $useCase
    ): Response {
        return $useCase->execute(new UploadLicenseRequestDocumentCommand($token, $systemKey, $file));
    }

    #[Route('/license/{token}', name: 'license_for_payment', methods: ['GET'])]
    public function licenseForPayment(
        string $token,
        GetLicenseForPaymentUseCase $useCase
    ): Response {
        return $useCase->execute(new GetLicenseForPaymentCommand($token));
    }

    #[Route('/license/{token}/checkout', name: 'license_checkout', methods: ['POST'])]
    public function createCheckout(
        string $token,
        CreateCheckoutUseCase $useCase
    ): Response {
        return $useCase->execute(new CreateCheckoutCommand($token));
    }

    #[Route('/helloasso/webhook', name: 'helloasso_webhook', methods: ['POST'])]
    public function helloAssoWebhook(
        Request $request,
        HandleHelloAssoWebhookUseCase $useCase
    ): Response {
        $payload = json_decode($request->getContent(), true);

        return $useCase->execute(new HandleHelloAssoWebhookCommand(is_array($payload) ? $payload : []));
    }

    #[Route('/home-games', name: 'home_games', methods: ['GET'])]
    public function homeGames(GameRepository $gameRepository): Response
    {
        $games = $gameRepository->findUpcomingHomeGames(10);

        return $this->json(array_map(fn ($g) => $g->toArray(), $games));
    }

    #[Route('/season', name: 'season', methods: ['GET'])]
    public function season(SeasonProvider $seasonProvider): Response
    {
        return $this->json(['season' => $seasonProvider->current()]);
    }

    #[Route('/inscriptions-status', name: 'inscriptions_status', methods: ['GET'])]
    public function inscriptionsStatus(InscriptionsStatusProvider $provider): Response
    {
        return $this->json(['open' => $provider->isOpen(), 'formOpen' => $provider->isFormOpen()]);
    }

    #[Route('/nationalities', name: 'nationalities', methods: ['GET'])]
    public function nationalities(): Response
    {
        return $this->json(MemberNationality::values());
    }

    #[Route('/closures', name: 'closures', methods: ['GET'])]
    public function closures(SalleClosureRepository $salleClosureRepository): Response
    {
        $closures = $salleClosureRepository->findAllOrderedByDate();

        return $this->json(array_map(fn ($c) => $c->toArray(), $closures));
    }

    #[Route('/teams', name: 'teams', methods: ['GET'])]
    public function teams(TeamRepository $teamRepository): Response
    {
        $teams = $teamRepository->findBy([], ['name' => 'ASC']);

        return $this->json(array_map(fn ($t) => $t->toArray(), $teams));
    }

    #[Route('/games', name: 'games', methods: ['GET'])]
    public function games(
        #[MapQueryString] Input\GetGamesInput $input,
        GameRepository $gameRepository
    ): Response {
        // Public endpoint: never serve an unbounded range (missing or invalid
        // params fall back to a +/- 1 year window around today)
        $start = $this->parseDate($input->start) ?? new \DateTimeImmutable('-1 year');
        $end = $this->parseDate($input->end) ?? new \DateTimeImmutable('+1 year');

        $games = $gameRepository->findAllByDateRange($start->format('Y-m-d'), $end->format('Y-m-d'));

        return $this->json(array_map(fn ($g) => $g->toArray(), $games));
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', substr($value, 0, 10));

        return $date ?: null;
    }
}
