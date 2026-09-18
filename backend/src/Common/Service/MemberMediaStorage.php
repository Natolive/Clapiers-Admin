<?php

namespace App\Common\Service;

use App\Common\Exception\UseCaseException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
 * Écritures et suppressions vont sur l'API Storage ; **toute lecture passe par
 * la pull zone CDN**, signée — y compris celles du backend (recopie, export).
 * Pas de repli sur l'API Storage : un seul chemin de lecture, donc un seul
 * comportement à connaître. Sans pull zone configurée, plus rien ne se lit
 * (502), comme pour une zone absente.
 *
 * Les fichiers ne sont JAMAIS exposés publiquement : la pull zone est
 * verrouillée par la Token Authentication Bunny. Le navigateur reçoit des URL
 * signées ({@see self::signedUrl()}) dans les payloads déjà protégés par les
 * routes API — une URL qui fuite expire toute seule.
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

    /** Validité d'une URL CDN signée : le temps d'un téléchargement, pas plus. */
    private const TOKEN_TTL = 300;

    /**
     * Vignettes et aperçus servis dans une page : assez long pour que le
     * navigateur garde l'image en cache le temps qu'on consulte une liste,
     * assez court pour qu'une URL recopiée ne survive pas à la session.
     */
    public const DISPLAY_TTL = 1800;

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
     * URL CDN signée du fichier : le seul moyen de le lire, pour le navigateur
     * comme pour le backend. Le `$ttl` se choisit selon la demande —
     * {@see self::DISPLAY_TTL} pour ce qui s'affiche dans une liste, le défaut
     * pour un fichier qu'on ouvre tout de suite.
     *
     * Null si le nœud n'a pas de fichier ou si aucune pull zone n'est
     * configurée : un payload renvoie alors `null` (le front n'affiche rien)
     * et une lecture serveur échoue en 502.
     */
    public function signedUrl(?string $storedName, int $ttl = self::TOKEN_TTL): ?string
    {
        $cdn = rtrim($this->config->get('cdnUrl'), '/');
        if ($storedName === null || $storedName === '' || $cdn === '') {
            return null;
        }

        $path = self::SUBDIR.'/'.$storedName;

        return $cdn.$path.$this->token($path, $ttl);
    }

    /**
     * Contenu brut du fichier, ou null s'il n'existe plus dans la zone. Pour
     * les cas où l'objet ne part pas tel quel au client : recopie entre
     * membres, archive d'export.
     */
    public function contents(string $storedName): ?string
    {
        $response = $this->bunny('GET', $storedName);

        return $response->getStatusCode() === 404 ? null : $response->getContent();
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

        // Bunny n'a pas d'API de copie côté serveur : relire puis réécrire. Les
        // pièces sont plafonnées à 10 Mo à l'upload, donc ça tient en mémoire.
        $content = $this->contents($storedName);
        if ($content === null) {
            // Rien à copier : le nom en base reste tel quel, comme avant.
            return $storedName;
        }

        $this->bunny('PUT', $target, ['body' => $content]);

        return $target;
    }

    /**
     * Suppression de ménage, après le commit : la base ne pointe plus sur
     * l'objet, donc un échec de la zone ne doit surtout pas faire échouer la
     * requête — au pire il reste un orphelin. `bunny()` a déjà journalisé
     * l'erreur, il n'y a rien à ajouter ici.
     *
     * À utiliser partout où l'on efface un fichier *remplacé* ou *détaché* ;
     * `delete()` reste pour les cas où l'appelant veut connaître l'échec.
     */
    public function deleteQuietly(?string $storedName): void
    {
        try {
            $this->delete($storedName);
        } catch (UseCaseException) {
            // Orphelin toléré : mieux qu'une 502 sur une opération réussie.
        }
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
     * Un appel à Bunny : lecture par la pull zone si elle est configurée,
     * écriture et suppression toujours par l'API Storage. Renvoie la réponse
     * pour les 2xx et les 404 ; tout le reste (réseau, 401, 5xx) devient un 502
     * propre côté appelant.
     *
     * @param array<string, mixed> $options
     */
    private function bunny(string $method, string $storedName, array $options = []): ResponseInterface
    {
        if ($method === 'GET') {
            $url = $this->signedUrl($storedName);
            $headers = [];
        } else {
            $zone = rtrim($this->config->get('storageUrl'), '/');
            $url = $zone !== '' ? $zone.self::SUBDIR.'/'.$storedName : null;
            $headers = ['AccessKey' => $this->config->get('storageKey')];
        }

        if ($url === null) {
            $this->logger->error('Stockage Bunny non configuré', ['method' => $method]);

            throw new UseCaseException('Stockage de fichiers non configuré', 502);
        }

        try {
            $response = $this->httpClient->request($method, $url, $options + [
                'headers' => $headers,
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

    /**
     * Query string de la Token Authentication Bunny : md5(clé + chemin +
     * expiration) en base64 url-safe, sans le bourrage `=`. Sans clé, la pull
     * zone est ouverte et l'URL part telle quelle.
     *
     * L'expiration est **alignée sur des fenêtres fixes** de `$ttl` : deux
     * rendus rapprochés de la même liste produisent la même URL, donc le
     * navigateur (et le cache du CDN) la réutilisent. Avec un `time() + ttl`
     * recalculé à chaque appel, chaque image était une URL neuve : plus aucun
     * cache possible, et tout le trombinoscope retéléchargé à chaque écran.
     * Validité effective : entre `$ttl` et `2 × $ttl`.
     */
    private function token(string $path, int $ttl): string
    {
        $key = $this->config->get('tokenKey');
        if ($key === '') {
            return '';
        }

        $expires = (intdiv(time(), $ttl) + 2) * $ttl;
        $token = rtrim(strtr(base64_encode(md5($key.$path.$expires, true)), '+/', '-_'), '=');

        return '?token='.$token.'&expires='.$expires;
    }
}
