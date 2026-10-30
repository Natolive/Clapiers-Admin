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
use App\Application\UseCase\User\AcceptInvitation\AcceptInvitationCommand;
use App\Application\UseCase\User\AcceptInvitation\AcceptInvitationUseCase;
use App\Application\UseCase\User\GetInvitation\GetInvitationCommand;
use App\Application\UseCase\User\GetInvitation\GetInvitationUseCase;
use App\Common\Service\InscriptionsStatusProvider;
use App\Controller\Input\AcceptInvitationInput;
use App\Common\Service\MemberMediaStorage;
use App\Common\Service\SeasonProvider;
use App\Entity\Enum\GameVenue;
use App\Entity\Enum\MemberNationality;
use App\Entity\Game;
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
        //
        // `null` quand le corps est illisible, et surtout PAS un tableau vide :
        // une requête coupée en plein vol — le scénario mobile que ce brouillon
        // existe pour encaisser — effacerait tout ce que la personne a saisi.
        $body = json_decode((string) $request->getContent(), true);
        $payload = \is_array($body) && \is_array($body['payload'] ?? null) ? $body['payload'] : null;

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

    #[Route('/invitation/{token}', name: 'invitation_get', methods: ['GET'], requirements: self::DRAFT_TOKEN)]
    public function getInvitation(
        string $token,
        GetInvitationUseCase $useCase
    ): Response {
        return $useCase->execute(new GetInvitationCommand($token));
    }

    #[Route('/invitation/{token}', name: 'invitation_accept', methods: ['POST'], requirements: self::DRAFT_TOKEN)]
    public function acceptInvitation(
        string $token,
        #[MapRequestPayload] AcceptInvitationInput $input,
        AcceptInvitationUseCase $useCase
    ): Response {
        return $useCase->execute(new AcceptInvitationCommand($token, $input->password));
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

    /**
     * Flux iCalendar (RFC 5545) des matchs, pour s'abonner depuis Google Agenda,
     * Apple Calendrier, Outlook… Tout le club, ou une équipe avec ?teamId=, et
     * un rappel optionnel avec ?alarm=<minutes>. Fenêtre : 3 mois passés → 1 an
     * à venir. Paramètre invalide → 422 (MapQueryString répond 404 par défaut).
     */
    #[Route('/games.ics', name: 'games_ics', methods: ['GET'])]
    public function gamesIcs(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ?Input\GetGamesIcsInput $input,
        GameRepository $gameRepository,
        TeamRepository $teamRepository,
    ): Response {
        $input ??= new Input\GetGamesIcsInput();
        $start = (new \DateTimeImmutable('-3 months'))->format('Y-m-d');
        $end = (new \DateTimeImmutable('+1 year'))->format('Y-m-d');
        $name = 'Clapiers VB';

        if ($input->teamId !== null) {
            $team = $teamRepository->find($input->teamId);
            if (!$team) {
                throw $this->createNotFoundException('Team not found');
            }
            $games = $gameRepository->findByTeamAndDateRange($team, $start, $end);
            $name .= ' - '.$team->getName();
        } else {
            $games = $gameRepository->findAllByDateRange($start, $end);
        }

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Clapiers Volley Ball//Calendrier des matchs//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->icsText($name),
            'X-WR-TIMEZONE:Europe/Paris',
            // Indication de rafraîchissement (Google l'ignore, Apple/Outlook la suivent)
            'REFRESH-INTERVAL;VALUE=DURATION:PT6H',
            'X-PUBLISHED-TTL:PT6H',
        ];
        foreach ($games as $game) {
            array_push($lines, ...$this->icsEvent($game, $input->alarm));
        }
        $lines[] = 'END:VCALENDAR';

        $body = implode("\r\n", array_map($this->icsFold(...), $lines))."\r\n";

        return new Response($body, Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="clapiers-matchs.ics"',
        ]);
    }

    /** @return list<string> */
    private function icsEvent(Game $game, ?int $alarmMinutes): array
    {
        $isHome = $game->getVenue() === GameVenue::HOME;
        $utc = new \DateTimeZone('UTC');
        $time = $this->parseMeetingTime($game->getMeetingTime());

        if ($time !== null) {
            // L'heure saisie est celle de Paris ; on publie en UTC (pas de VTIMEZONE à fournir)
            $start = new \DateTimeImmutable($game->getDate()->format('Y-m-d').' '.$time, new \DateTimeZone('Europe/Paris'));
            // ponytail: durée fixe de 2 h, un match de volley n'a pas d'heure de fin connue
            $when = [
                'DTSTART:'.$start->setTimezone($utc)->format('Ymd\THis\Z'),
                'DTEND:'.$start->modify('+2 hours')->setTimezone($utc)->format('Ymd\THis\Z'),
            ];
        } else {
            $when = [
                'DTSTART;VALUE=DATE:'.$game->getDate()->format('Ymd'),
                'DTEND;VALUE=DATE:'.$game->getDate()->modify('+1 day')->format('Ymd'),
            ];
        }

        $description = $isHome ? 'Match à domicile' : 'Match à l\'extérieur';
        if ($game->getMeetingTime()) {
            $description .= ' - RDV '.$game->getMeetingTime();
        }

        return [
            'BEGIN:VEVENT',
            'UID:game-'.$game->getId().'@clapiersvb.fr',
            'DTSTAMP:'.($game->getUpdatedAt() ?? new \DateTimeImmutable())->setTimezone($utc)->format('Ymd\THis\Z'),
            ...$when,
            'SUMMARY:'.$this->icsText(sprintf('%s %s %s', $game->getTeam()->getName(), $isHome ? 'vs' : '@', $game->getOpponent())),
            ...($game->getLocation() ? ['LOCATION:'.$this->icsText($game->getLocation())] : []),
            'DESCRIPTION:'.$this->icsText($description),
            ...($alarmMinutes ? [
                'BEGIN:VALARM',
                'ACTION:DISPLAY',
                'DESCRIPTION:'.$this->icsText('Match : '.$game->getTeam()->getName()),
                'TRIGGER:-PT'.$alarmMinutes.'M',
                'END:VALARM',
            ] : []),
            'END:VEVENT',
        ];
    }

    /** "20:30", "20h30", "20h" → "20:30" ; tout autre texte libre → null (évènement sur la journée) */
    private function parseMeetingTime(?string $value): ?string
    {
        if ($value === null || !preg_match('/^\s*(\d{1,2})\s*[h:]\s*(\d{2})?\s*$/i', $value, $m)) {
            return null;
        }
        $hours = (int) $m[1];
        $minutes = (int) ($m[2] ?? 0);

        return $hours < 24 && $minutes < 60 ? sprintf('%02d:%02d', $hours, $minutes) : null;
    }

    /** Échappement des valeurs TEXT (RFC 5545 §3.3.11) */
    private function icsText(string $value): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\\;', '\\,', '\\n', '\\n'], $value);
    }

    /** Repli des lignes à 75 octets (RFC 5545 §3.1), sans couper un caractère UTF-8 */
    private function icsFold(string $line): string
    {
        $folded = '';
        $limit = 75;
        while (strlen($line) > $limit) {
            $chunk = mb_strcut($line, 0, $limit, 'UTF-8');
            $folded .= $chunk."\r\n ";
            $line = substr($line, strlen($chunk));
            $limit = 74; // la ligne de continuation commence par une espace
        }

        return $folded.$line;
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
