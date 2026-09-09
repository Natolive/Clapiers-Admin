<?php

namespace App\Common\Service;

use App\Common\Exception\UseCaseException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Stockage physique des fichiers de la médiathèque sur Bunny Storage, sous
 * /member-media/<id du membre>/<uuid>.<ext> : un dossier par membre, ce qui
 * rend la zone lisible depuis le tableau de bord Bunny. Le chemin relatif
 * complet est ce qui est enregistré en `storedName`, donc lecture et
 * suppression n'ont besoin de rien d'autre (les anciens noms à plat restent
 * résolus tels quels). Centralise nommage UUID, écriture, lecture et
 * suppression — pour les uploads licence / certificat comme pour l'arbre
 * médiathèque. Zone et clé viennent de l'administration
 * ({@see BunnyConfigProvider}), pas de l'environnement.
 *
 * Pas de stockage local : le backend tourne en plusieurs pods k8s, et un
 * fichier écrit sur le disque d'un pod est introuvable depuis les autres.
 *
 * Les fichiers ne sont JAMAIS exposés publiquement : aucune pull zone CDN sur
 * la zone, qui n'est lisible qu'avec l'AccessKey ; les seuls accès en lecture
 * passent par les routes authentifiées qui appellent {@see self::response()}.
 *
 * @phpstan-type FileMeta array{storedName: string, originalName: string, mimeType: ?string, size: ?int}
 */
class MemberMediaStorage
{
    /**
     * Formats acceptés, partout où un fichier entre (inscription publique et
     * médiathèque). Ce sont les types **détectés** par fileinfo qui comptent,
     * pas ceux annoncés par le navigateur : `Assert\File` compare le contenu.
     * heic/heif sont admis pour les photos d'iPhone d'origine — Chrome et
     * Firefox ne savent pas les prévisualiser, l'aperçu admin les téléchargera.
     *
     * Les plafonds de taille restent portés par chaque route, en unités
     * **binaires** (`6Mi`, pas `6M`) : pour Symfony `6M` vaut 6 000 000 octets
     * alors que le formulaire public compte en 1024. Le serveur doit rester
     * au-dessus du contrôle client, sinon la pièce est refusée après la
     * création de la demande — vécu avec `5M` face à un plafond de 5 MiB.
     */
    public const MIME_TYPES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/webp',
        'image/heic',
        'image/heif',
    ];

    private const SUBDIR = '/member-media';

    /** Un pod ne doit pas rester bloqué sur un incident Bunny. */
    private const TIMEOUT = 30;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly BunnyConfigProvider $config,
    ) {
    }

    /**
     * Envoie l'upload dans le dossier du membre, sous un nom UUID (le nom
     * d'origine reste en base).
     *
     * @return FileMeta
     */
    public function store(UploadedFile $file, int $memberId): array
    {
        return $this->storeIn((string) $memberId, $file);
    }

    /**
     * Même chose dans un dossier quelconque de la zone : le dossier d'un membre
     * n'est qu'un préfixe parmi d'autres. Sert au brouillon d'inscription, qui
     * reçoit ses pièces sous `drafts/<token>/` avant que le membre existe
     * ({@see \App\Entity\InscriptionDraft}).
     *
     * @return FileMeta
     */
    public function storeIn(string $prefix, UploadedFile $file): array
    {
        $extension = $file->guessExtension() ?? $file->getClientOriginalExtension();
        $storedName = trim($prefix, '/').'/'.Uuid::v4()->toRfc4122().($extension !== '' ? '.'.$extension : '');

        $size = $file->getSize();
        $meta = [
            'storedName' => $storedName,
            'originalName' => $file->getClientOriginalName(),
            'mimeType' => $file->getMimeType(),
            'size' => $size !== false ? $size : null,
        ];

        $handle = fopen($file->getPathname(), 'r');
        if ($handle === false) {
            throw new UseCaseException('Fichier uploadé illisible', 400);
        }

        // Corps en flux : un PDF de 10 Mo ne passe pas par la mémoire du pod.
        // L'appelant persiste l'entité APRÈS ce retour : un échec ici (502) ne
        // laisse donc pas de ligne en base sans fichier.
        $this->bunny('PUT', $storedName, ['body' => $handle]);

        return $meta;
    }

    /**
     * Réponse de téléchargement du fichier, ou null s'il n'existe pas — chaque
     * appelant garde sa propre forme de 404.
     */
    public function response(string $storedName, ?string $mimeType = null, ?string $downloadName = null): ?Response
    {
        $bunny = $this->bunny('GET', $storedName, ['buffer' => false]);
        if ($bunny->getStatusCode() === 404) {
            return null;
        }

        $response = new StreamedResponse(function () use ($bunny): void {
            foreach ($this->httpClient->stream($bunny) as $chunk) {
                echo $chunk->getContent();
            }
        });

        // Bunny renvoie tout en octet-stream : le vrai type vient de la base.
        $response->headers->set('Content-Type', $mimeType ?? 'application/octet-stream');

        $length = $bunny->getHeaders(false)['content-length'][0] ?? null;
        if ($length !== null) {
            $response->headers->set('Content-Length', $length);
        }

        if ($downloadName !== null) {
            // Le nom d'origine vient de l'utilisateur : « Certificat médical.pdf »
            // passe très bien en UTF-8 dans `filename*`, mais makeDisposition()
            // exige en plus un repli ASCII pour les vieux clients et jette si on
            // ne lui en donne pas. Substitution octet par octet (un caractère
            // accentué donne donc deux « _ ») : sans le modificateur `/u`,
            // preg_replace ne peut pas rendre null sur de l'UTF-8 invalide, et
            // un repli vide relancerait l'exception qu'on cherche à éviter.
            $response->headers->set(
                'Content-Disposition',
                HeaderUtils::makeDisposition(
                    HeaderUtils::DISPOSITION_ATTACHMENT,
                    $downloadName,
                    (string) preg_replace('/[^\x20-\x7e]|%/', '_', $downloadName),
                ),
            );
        }

        return $response;
    }

    /**
     * Recopie un fichier dans le dossier d'un autre membre — utilisé quand une
     * demande de licence est rattachée à une fiche existante. Renvoie le
     * nouveau `storedName` (l'ancien si rien n'a bougé).
     *
     * Copie et non déplacement : l'original ne doit disparaître qu'une fois le
     * nouveau nom commité en base, sinon un échec plus loin dans la requête
     * laisserait la base pointer sur un objet déjà supprimé. C'est à l'appelant
     * de supprimer l'ancien, après son flush ({@see self::delete()}).
     */
    public function copyTo(string $storedName, int $memberId): string
    {
        $target = $memberId.'/'.basename($storedName);
        if ($target === $storedName) {
            return $storedName;
        }

        $source = $this->bunny('GET', $storedName);
        if ($source->getStatusCode() === 404) {
            // Rien à copier : le nom en base reste tel quel, comme avant.
            return $storedName;
        }

        // Bunny n'a pas d'API de copie côté serveur : relire puis réécrire. Les
        // pièces sont plafonnées à 10 Mo à l'upload, donc ça tient en mémoire.
        $this->bunny('PUT', $target, ['body' => $source->getContent()]);

        return $target;
    }

    public function delete(?string $storedName): void
    {
        if ($storedName === null) {
            return;
        }

        // 404 toléré : supprimer un fichier déjà absent n'est pas une erreur.
        $this->bunny('DELETE', $storedName);
    }

    /**
     * Un appel à la zone Bunny. Renvoie la réponse pour les 2xx et les 404 ;
     * tout le reste (réseau, 401, 5xx) devient un 502 propre côté appelant.
     *
     * @param array<string, mixed> $options
     */
    private function bunny(string $method, string $storedName, array $options = []): ResponseInterface
    {
        $zone = $this->config->get('storageUrl');
        if ($zone === '') {
            $this->logger->error('Zone Bunny Storage non configurée', ['method' => $method]);

            throw new UseCaseException('Stockage de fichiers non configuré', 502);
        }

        $url = rtrim($zone, '/').self::SUBDIR.'/'.$storedName;

        try {
            $response = $this->httpClient->request($method, $url, $options + [
                'headers' => ['AccessKey' => $this->config->get('storageKey')],
                'timeout' => self::TIMEOUT,
            ]);
            $status = $response->getStatusCode();
        } catch (HttpExceptionInterface $e) {
            $this->logger->error('Bunny Storage injoignable', [
                'method' => $method,
                'storedName' => $storedName,
                'exception' => $e,
            ]);

            throw new UseCaseException('Stockage de fichiers indisponible', 502);
        }

        if ($status >= 400 && $status !== 404) {
            $this->logger->error('Bunny Storage a répondu en erreur', [
                'method' => $method,
                'storedName' => $storedName,
                'status' => $status,
            ]);

            throw new UseCaseException('Stockage de fichiers indisponible', 502);
        }

        return $response;
    }
}
