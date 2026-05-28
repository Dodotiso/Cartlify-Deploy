<?php

$host = '0.0.0.0';
$port = 8081;

echo "Starting WebSocket server on port $port...\n";

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($socket, $host, $port);
socket_listen($socket);
socket_set_nonblock($socket);

echo "WebSocket server running on port $port!\n";
echo "Waiting for connections...\n";

$clients = [];
$userMap = []; // Maps socket ID to user ID

while (true) {
    // Accept new connections
    $newClient = @socket_accept($socket);
    
    if ($newClient !== false) {
        $clientId = (int)$newClient;
        $clients[$clientId] = $newClient;
        echo "New client connected (ID: $clientId)\n";
        
        // Send welcome message
        $welcome = "Connected to server";
        @socket_write($newClient, $welcome, strlen($welcome));
    }
    
    // Read from existing clients
    foreach ($clients as $clientId => $client) {
        $data = @socket_read($client, 4096);
        
        if ($data === false || $data === '') {
            // Client disconnected
            unset($clients[$clientId]);
            if (isset($userMap[$clientId])) {
                echo "User {$userMap[$clientId]} disconnected\n";
                unset($userMap[$clientId]);
            }
            @socket_close($client);
            continue;
        }
        
        $data = trim($data);
        
        if (!empty($data)) {
            echo "Received: $data\n";
            
            $json = json_decode($data, true);
            
            if ($json && isset($json['type'])) {
                // Authentication
                if ($json['type'] === 'auth' && isset($json['userId'])) {
                    $userMap[$clientId] = $json['userId'];
                    echo "User {$json['userId']} authenticated (Client: $clientId)\n";
                    
                    $response = json_encode([
                        'type' => 'connected',
                        'message' => 'Authenticated successfully'
                    ]);
                    @socket_write($client, $response, strlen($response));
                }
                
                // Send to specific user
                if (isset($json['toUserId'])) {
                    foreach ($userMap as $cid => $uid) {
                        if ($uid == $json['toUserId'] && isset($clients[$cid])) {
                            $response = json_encode($json);
                            @socket_write($clients[$cid], $response, strlen($response));
                            echo "Sent notification to User {$json['toUserId']}\n";
                            break;
                        }
                    }
                }
                
                // Broadcast to all
                if ($json['type'] === 'broadcast') {
                    foreach ($clients as $cid => $c) {
                        $response = json_encode($json);
                        @socket_write($c, $response, strlen($response));
                    }
                    echo "Broadcast sent to all clients\n";
                }
            }
        }
    }
    
    // Small sleep to prevent CPU overload
    usleep(50000); // 0.05 seconds
}