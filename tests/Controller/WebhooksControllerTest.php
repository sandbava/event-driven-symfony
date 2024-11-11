<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\CDP\Analytics\Model\Subscription\Identify\IdentifyModel;
use App\CDP\Analytics\Model\Subscription\Track\TrackModel;
use App\CDP\Http\CdpClientInterface;
use App\Error\Exception\WebhookException;
use App\Tests\TestDoubles\CDP\Http\FakeCdpClient;
use App\Tests\TestDoubles\Error\FakeErrorHandler;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

class WebhooksControllerTest extends WebTestCase
{
    private KernelBrowser $webTester;
    private ContainerInterface $container;
    private FakeCdpClient $cdpClient;
    private FakeErrorHandler $errorHandler;

    protected function setUp(): void
    {
        $this->webTester = static::createClient();
        $this->container = $this->webTester->getContainer();
        $this->cdpClient = $this->container->get(CdpClientInterface::class);
        $this->errorHandler = $this->container->get(FakeErrorHandler::class);
    }

    public function testWebhooksAreHandled(): void
    {
        /** @var @phpcs:disable */
        $incomingWebhookPayload = '{"event":"newsletter_subscribed","id":"12345","origin":"www","timestamp":"2024-12-12T12:00:00Z","user": {"client_id":"4a2b342d-6235-46a9-bc95-6e889b8e5de1","email":"email@example.com","region":"EU"},"newsletter": {"newsletter_id":"newsletter-001","topic":"N/A","product_id":"TechGadget-3000X"}}';
        /** @var @phpcs:enable */

        $this->postJson($incomingWebhookPayload);

        // Assert CdpClient::identify() called once
        $this->assertSame(1, $this->cdpClient->getIdentifyCallCount());

        // Assert correct IdentifyModel is passed to CdpClient::identify() method
        $identifyModel = $this->cdpClient->getIdentifyModel();
        assert($identifyModel instanceof IdentifyModel);

        // Assert IdentifyModel::toArray() organizes data into format expected by CDP
        $this->assertSame([
            'type' => 'identify',
            'context' => [
                'product' => 'TechGadget-3000X',
                'event_date' => '2024-12-12'
            ],
            'traits' => [
                'subscription_id' => '12345',
                'email' => 'email@example.com'
            ],
            'id' => '4a2b342d-6235-46a9-bc95-6e889b8e5de1'
        ], $identifyModel->toArray());

        // Assert CdpClient::track() called once
        $this->assertSame(1, $this->cdpClient->getTrackCallCount());

        // Assert correct TrackModel is passed to CdpClient::track() method
        $trackModel = $this->cdpClient->getTrackModel();
        assert($trackModel instanceof TrackModel);

        // Assert TrackModel::toArray() organizes data into format expected by CDP
        $this->assertSame([
            'type' => 'track',
            'event' => 'newsletter_subscribed',
            'context' => [
                'product' => 'TechGadget-3000X',
                'event_date' => '2024-12-12',
                'traits' => [
                    'subscription_id' => '12345',
                    'email' => 'email@example.com',
                ],
            ],
            'properties' => [
                'requires_consent' => true,
                'platform' => 'web',
                'product_name' => 'newsletter-001',
                'renewal_date' => '2025-12-12',
                'start_date' => '2024-12-12',
                'status' => 'subscribed',
                'type' => 'newsletter',
                'is_promotion' => false,
            ],
            'id' => '4a2b342d-6235-46a9-bc95-6e889b8e5de1'
        ], $trackModel->toArray());

        $this->assertSame(Response::HTTP_NO_CONTENT, $this->webTester->getResponse()->getStatusCode());
    }

    public function testExecutionIsStoppedIfMandatoryInfoCanNotBeMapped(): void
    {
        /** @var @phpcs:disable */
        $incomingWebhookPayload = '{"event":"newsletter_subscribed","origin":"www","timestamp":"2024-12-12T12:00:00Z","user": {"client_id":"4a2b342d-6235-46a9-bc95-6e889b8e5de1","email":"email@example.com","region":"EU"},"newsletter": {"newsletter_id":"newsletter-001","topic":"N/A","product_id":"TechGadget-3000X"}}';
        /** @var @phpcs:enable */ // should not map with id missing

        $this->postJson($incomingWebhookPayload);

        $webhookException = $this->errorHandler->getError();
        assert($webhookException instanceof WebhookException);

        $this->assertSame(1, $this->errorHandler->getHandleCallCount());

        $this->assertStringContainsString(
            'Could not map App\DTO\Newsletter\NewsletterWebhook to IdentifyModel',
            $webhookException->getMessage()
        );

        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->webTester->getResponse()->getStatusCode());
    }

    public function testWebhookExceptionThrownIfIdentifyModelValidationFails(): void
    {
        $this->markTestIncomplete('wip');
    }

    private function postJson(string $payload): void
    {
        $this->webTester->request(
            method: 'POST',
            uri: '/webhook',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => '*/*',
            ],
            content: $payload
        );
    }
}
