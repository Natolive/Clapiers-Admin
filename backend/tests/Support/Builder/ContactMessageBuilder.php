<?php

namespace App\Tests\Support\Builder;

use App\Entity\ContactMessage;
use Doctrine\ORM\EntityManagerInterface;

final class ContactMessageBuilder
{
    private static int $seq = 0;

    private ?string $firstName = null;
    private ?string $lastName = null;
    private ?string $email = null;
    private ?string $subject = null;
    private string $message = 'Bonjour, ceci est un message de test.';

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function fromSender(string $firstName, string $lastName, string $email): self
    {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;

        return $this;
    }

    public function about(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function saying(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function build(): ContactMessage
    {
        $n = ++self::$seq;

        $message = new ContactMessage();
        $message->setFirstName($this->firstName ?? sprintf('Expéditeur%03d', $n));
        $message->setLastName($this->lastName ?? sprintf('Contact%03d', $n));
        $message->setEmail($this->email ?? sprintf('contact%03d@test.fr', $n));
        $message->setSubject($this->subject ?? sprintf('Sujet %03d', $n));
        $message->setMessage($this->message);

        return $message;
    }

    public function persist(): ContactMessage
    {
        $message = $this->build();
        $this->em->persist($message);
        $this->em->flush();

        return $message;
    }
}
