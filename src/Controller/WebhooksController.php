<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Webhook;
use App\Error\ErrorHandlerInterface;
use App\Webhook\Handler\HandlerDelegator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

class WebhooksController extends AbstractController
{
    public function __construct(
        private SerializerInterface $serializer,
        private HandlerDelegator $handlerDelegator,
        private ErrorHandlerInterface $errorHandler,
    ) {
    }

    #[Route(path: '/webhook', name: 'webhook', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        try {
            $webhook = $this->serializer->deserialize($request->getContent(), Webhook::class, 'json');
            $webhook->setRawPayload($request->getContent());
            $this->handlerDelegator->delegate($webhook);
            return new Response(status: Response::HTTP_NO_CONTENT);
        } catch (\Throwable $throwable) {
            $this->errorHandler->handle($throwable);
            return new Response(status: Response::HTTP_BAD_REQUEST);
        }
    }
}
