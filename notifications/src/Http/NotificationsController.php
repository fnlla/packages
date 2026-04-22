<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Notifications\Http;

use Fnlla\Http\Request;
use Fnlla\Http\Response;
use Fnlla\Notifications\NotificationManager;
use Fnlla\Notifications\NotificationRepository;

final class NotificationsController
{
    public function __construct(
        private NotificationRepository $repository,
        private NotificationManager $manager
    ) {
    }

    public function index(Request $request): Response
    {
        $query = $request->getQueryParams();
        $limit = isset($query['limit']) ? (int) $query['limit'] : 50;
        $offset = isset($query['offset']) ? (int) $query['offset'] : 0;

        $items = $this->repository->list($limit, $offset);
        return Response::json(['data' => $items]);
    }

    public function show(Request $request): Response
    {
        $id = (int) $request->getAttribute('id');
        if ($id <= 0) {
            return Response::json(['error' => 'Invalid id.'], 422);
        }

        $item = $this->repository->find($id);
        if ($item === null) {
            return Response::json(['error' => 'Not found.'], 404);
        }

        return Response::json(['data' => $item]);
    }

    public function send(Request $request): Response
    {
        $body = $request->getParsedBody();
        $payload = is_array($body) ? $body : [];

        $channel = (string) ($payload['channel'] ?? '');
        $recipient = (string) ($payload['to'] ?? $payload['recipient'] ?? '');
        $subject = isset($payload['subject']) ? (string) $payload['subject'] : null;
        $text = (string) ($payload['text'] ?? $payload['body'] ?? '');
        $html = isset($payload['html']) ? (string) $payload['html'] : null;
        $metadata = $payload['metadata'] ?? [];

        if ($recipient === '' || $text === '') {
            return Response::json(['error' => 'Missing recipient or body.'], 422);
        }

        if (!is_array($metadata)) {
            $metadata = [];
        }

        $id = $this->manager->send($channel, $recipient, $subject, $text, $html, $metadata);
        return Response::json(['id' => $id], 201);
    }
}


