<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MercureController extends AbstractController
{
    #[Route('/api/notifications/stream', name: 'api_notifications_stream', methods: ['GET'])]
    public function stream(Request $request): Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('Access-Control-Allow-Origin', '*');

        // Send initial connection message
        $response->setContent("data: " . json_encode(['type' => 'connected', 'message' => 'SSE connection established']) . "\n\n");
        $response->send();

        // Keep connection alive
        while (true) {
            if (connection_aborted()) break;
            
            // Send heartbeat every 15 seconds
            echo "data: " . json_encode(['type' => 'heartbeat', 'time' => time()]) . "\n\n";
            ob_flush();
            flush();
            sleep(15);
        }

        return $response;
    }
}