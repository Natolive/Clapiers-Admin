<?php

namespace App\Tests\Functional;

use App\Tests\Support\ApiTestCase;

/**
 * GET /api/contact-message — réservé à ROLE_VIEW_MESSAGE.
 */
class ContactMessageApiTest extends ApiTestCase
{
    public function testMessageViewerGetsPaginatedMessagesNewestFirst(): void
    {
        $old = $this->aContactMessage()->about('Ancien message')->persist();
        // Force distinct createdAt ordering (same-second inserts)
        $this->em()->createQuery(
            'UPDATE App\Entity\ContactMessage m SET m.createdAt = :d WHERE m.id = :id'
        )->execute(['d' => new \DateTimeImmutable('-1 day'), 'id' => $old->getId()]);

        $this->aContactMessage()->about('Nouveau message')->persist();

        $this->actingAsMessageViewer();
        $this->getJson('/api/contact-message');

        $body = $this->assertJsonResponse(200);
        $this->assertSame(2, $body['total']);
        $this->assertCount(2, $body['data']);
        $this->assertSame('Nouveau message', $body['data'][0]['subject']);
    }

    public function testSearchFiltersMessages(): void
    {
        $this->aContactMessage()->fromSender('Alice', 'Martin', 'alice@test.fr')->persist();
        $this->aContactMessage()->fromSender('Bob', 'Durand', 'bob@test.fr')->persist();

        $this->actingAsMessageViewer();
        $this->getJson('/api/contact-message?search=alice');

        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['total']);
        $this->assertSame('alice@test.fr', $body['data'][0]['email']);
    }

    public function testPaginationLimitsResults(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            $this->aContactMessage()->persist();
        }

        $this->actingAsMessageViewer();
        $this->getJson('/api/contact-message?page=1&limit=2');

        $body = $this->assertJsonResponse(200);
        $this->assertSame(3, $body['total']);
        $this->assertCount(2, $body['data']);
    }

    public function testSuperAdminInheritsViewMessageRole(): void
    {
        $this->actingAsSuperAdmin();
        $this->getJson('/api/contact-message');

        $this->assertJsonResponse(200);
    }

    public function testPlainUserIsForbidden(): void
    {
        $this->actingAsUser();
        $this->getJson('/api/contact-message');

        $this->assertJsonResponse(403);
    }

    public function testAdminIsForbidden(): void
    {
        $this->actingAsAdmin();
        $this->getJson('/api/contact-message');

        $this->assertJsonResponse(403);
    }

    public function testUnauthenticatedIsRejected(): void
    {
        $this->getJson('/api/contact-message');

        $this->assertJsonResponse(401);
    }
}
