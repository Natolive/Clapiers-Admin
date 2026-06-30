<?php

namespace App\Entity;

use App\Entity\Enum\PaymentState;
use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampableTrait;
use App\Repository\PaymentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Payment
{
    use IdTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: License::class)]
    #[ORM\JoinColumn(nullable: false)]
    private License $license;

    /** Montant en centimes. */
    #[ORM\Column]
    private int $amount;

    #[ORM\Column(length: 20, enumType: PaymentState::class)]
    private PaymentState $state;

    #[ORM\Column(nullable: true)]
    private ?int $helloAssoCheckoutIntentId = null;

    #[ORM\Column(nullable: true)]
    private ?int $helloAssoOrderId = null;

    /** Clé d'idempotence du webhook HelloAsso. */
    #[ORM\Column(unique: true, nullable: true)]
    private ?int $helloAssoPaymentId = null;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $paymentReceiptUrl = null;

    /** Payload brut du webhook, conservé pour audit. */
    #[ORM\Column(type: Types::JSON)]
    private array $rawPayload = [];

    public function getLicense(): License
    {
        return $this->license;
    }

    public function setLicense(License $license): static
    {
        $this->license = $license;

        return $this;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getState(): PaymentState
    {
        return $this->state;
    }

    public function setState(PaymentState $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getHelloAssoCheckoutIntentId(): ?int
    {
        return $this->helloAssoCheckoutIntentId;
    }

    public function setHelloAssoCheckoutIntentId(?int $id): static
    {
        $this->helloAssoCheckoutIntentId = $id;

        return $this;
    }

    public function getHelloAssoOrderId(): ?int
    {
        return $this->helloAssoOrderId;
    }

    public function setHelloAssoOrderId(?int $id): static
    {
        $this->helloAssoOrderId = $id;

        return $this;
    }

    public function getHelloAssoPaymentId(): ?int
    {
        return $this->helloAssoPaymentId;
    }

    public function setHelloAssoPaymentId(?int $id): static
    {
        $this->helloAssoPaymentId = $id;

        return $this;
    }

    public function getPaymentReceiptUrl(): ?string
    {
        return $this->paymentReceiptUrl;
    }

    public function setPaymentReceiptUrl(?string $url): static
    {
        $this->paymentReceiptUrl = $url;

        return $this;
    }

    public function getRawPayload(): array
    {
        return $this->rawPayload;
    }

    public function setRawPayload(array $rawPayload): static
    {
        $this->rawPayload = $rawPayload;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'amount' => $this->getAmount(),
            'state' => $this->getState()->value,
            'helloAssoCheckoutIntentId' => $this->getHelloAssoCheckoutIntentId(),
            'helloAssoOrderId' => $this->getHelloAssoOrderId(),
            'helloAssoPaymentId' => $this->getHelloAssoPaymentId(),
            'paymentReceiptUrl' => $this->getPaymentReceiptUrl(),
            'createdAt' => $this->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $this->getUpdatedAt()?->format(DATE_ATOM),
        ];
    }
}
