<?php

$IP = 'tcp://127.0.0.1';
$PORT = '5555';


$host = '127.0.0.1';
$username = 'rama';
$password = 'ramapradana24';
$db = 'db_server_mca';

$connection = new mysqli($host, $username, $password, $db);

if($connection->connect_error){
    die('Connection Failed: ' . $connection->connect_error);
}


$context = new ZMQContext(1);

//  Socket to talk to clients
$responder = new ZMQSocket($context, ZMQ::SOCKET_REP);
$responder->bind($IP.':'.$PORT);

while (true) {
    //  Wait for next request from client
    $request = $responder->recv();
    
    $incomingPacket = json_decode($request);

    $ip = $incomingPacket->ip;
    $port = $incomingPacket->port;
    $msg = $incomingPacket->msg;
    $msgType = $incomingPacket->msg_type;
    $reqType = $incomingPacket->req_type;
    $msgId = $incomingPacket->msg_id;

    printf ("Received Request: [%s:%d]\n", $ip, $port);

    if($reqType == 1){
        
    }
    // creating query and store it to database
    $sql = "INSERT INTO inbox(
            inbox_client_ip, 
            inbox_client_port, 
            inbox_message, 
            inbox_msg_type,
            host_msg_id,
            created_at) 
        VALUES (
            '". $ip ."', 
            ". $port .", 
            '". $msg ."',
            ". $msgType .",
            ". $msgId .",
            '". date('Y-m-d H:i:s') ."')";
    // printf ("SQL: [%s]\n", $sql);
    //reply message to client
    if($connection->query($sql)){
        $reply = [
            'ip'    => $IP,
            'port'  => $PORT,
            'msg'   => 'ACK',
            'msg_type'  => 1,
            'req_type'  => 2, #ACK
            'msg_id'    => $msgId
        ];
    }else{
        $reply = [
            'ip'    => $IP,
            'port'  => $PORT,
            'msg'   => 'BAD ACK',
            'msg_type'  => 1,
            'req_type'  => 3, #bad ACK
            'msg_id'    => $msgId
        ];
    }
    $responder->send(json_encode($reply));
}