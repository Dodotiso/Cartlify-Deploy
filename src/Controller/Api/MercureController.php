<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class MercureController extends AbstractController
{
    #[Route('/api/notifications/stream', name: 'api_notifications_stream', methods: ['GET'])]
    public function notificationStream(Request $request): StreamedResponse
    {
        return new StreamedResponse(function () {
            // Send initial connection message
            echo "data: " . json_encode(['type' => 'connected', 'message' => 'SSE connection established']) . "\n\n";
            ob_flush();
            flush();

            // Keep connection alive
            while (true) {
                if (connection_aborted()) break;
                
                // Send heartbeat every 15 seconds
                echo "data: " . json_encode(['type' => 'heartbeat', 'time' => time()]) . "\n\n";
                ob_flush();
                flush();
                sleep(15);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'Access-Control-Allow-Origin' => '*',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}