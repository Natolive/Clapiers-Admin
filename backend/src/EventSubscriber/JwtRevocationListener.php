<?php

namespace App\EventSubscriber;

use App\Entity\AppUser;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

/**
 * Révocation des sessions : un JWT émis avant le dernier changement de mot de
 * passe est refusé (401). L'utilisateur est déjà rechargé par le provider à
 * chaque requête — aucune requête en plus.
 *
 * Granularité à la seconde (`iat` et la colonne le sont) : un jeton émis dans
 * la seconde même du changement passe encore. Négligeable.
 */
#[AsEventListener(event: Events::JWT_AUTHENTICATED)]
class JwtRevocationListener
{
    public function __invoke(JWTAuthenticatedEvent $event): void
    {
        $user = $event->getToken()->getUser();
        $changedAt = $user instanceof AppUser ? $user->getPasswordChangedAt() : null;
        $issuedAt = $event->getPayload()['iat'] ?? null;

        if ($changedAt !== null && (!is_int($issuedAt) || $issuedAt < $changedAt->getTimestamp())) {
            throw new CustomUserMessageAuthenticationException('Session expirée, reconnectez-vous.');
        }
    }
}
