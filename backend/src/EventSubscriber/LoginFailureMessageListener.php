<?php

namespace App\EventSubscriber;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationFailureResponse;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

/**
 * Messages d'échec du login en français (affichés tels quels par le front) —
 * sans le composant translation, Lexik renvoie les clés anglaises de Symfony.
 * Le message reste le même que le compte existe ou non.
 */
#[AsEventListener(event: Events::AUTHENTICATION_FAILURE)]
class LoginFailureMessageListener
{
    public function __invoke(AuthenticationFailureEvent $event): void
    {
        $exception = $event->getException();

        $message = $exception instanceof TooManyLoginAttemptsAuthenticationException
            ? sprintf('Trop de tentatives de connexion. Réessayez dans %d minutes.', $exception->getMessageData()['%minutes%'] ?? 15)
            : 'Identifiants invalides.';

        $event->setResponse(new JWTAuthenticationFailureResponse($message, $event->getResponse()->getStatusCode()));
    }
}
