<?php

$socket = stream_socket_client("tcp://127.0.0.1:8081", $errno, $errstr);

if (!$socket) {
    die("Error: $errstr ($errno)\n");
}

// Send a test notification to user ID 14 (or whatever your user ID is)
$notification = json_encode([
    'toUserId' => 14,
    'type' => 'order_update',
    'title' => 'Test Notification',
    'message' => 'Your order has been updated!'
]);

fwrite($socket, $notification);
fclose($socket);

echo "Test notification sent!\n";