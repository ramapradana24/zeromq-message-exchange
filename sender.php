<?php
/*
*  Hello World client
*  Connects REQ socket to tcp://localhost:5555
*  Sends "Hello" to server, expects "World" back
* @author Ian Barber <ian(dot)barber(at)gmail(dot)com>
*/

$SERVER_IP = "tcp://127.0.0.1";
$SERVER_PORT = 5555;

#mysql connection
$host = '127.0.0.1';
$username = 'rama';
$password = 'ramapradana24';
$db = 'db_server_mca';

$conn = new mysqli($host, $username, $password, $db);
if($conn->connect_error){
    die("Connection Failed:" . $conn->connect_error);
}
#################

$context = new ZMQContext();

//  Socket to talk to server
$sender = new ZMQSocket($context, ZMQ::SOCKET_REQ);

while(true){
    #count outbox in mysql database
    $countSql = 'SELECT * FROM outbox where outbox_status = 0';
    $result = $conn->query($countSql);


    if(mysqli_num_rows($result) > 0){
        while($row = $result->fetch_assoc()){
            //sending message to other host
            $IP = $row["outbox_client_ip"];
            $PORT = $row["outbox_client_port"];
            $msg = $row["outbox_message"];
            $msgType = $row["outbox_msg_type"];
            $msgId = $row["host_msg_id"];
            $outboxId = $row['outbox_id'];

            $sender->connect($IP .':'. $PORT);
            $packet = [
                'ip'    => $SERVER_IP,
                'port'  => $SERVER_PORT,
                'msg'   => $msg,
                'msg_type'  => $msgType,
                'req_type'  => 1,
                'msg_id'    => $msgId
            ];

            $sender->send(json_encode($packet));
            printf("[". $msgId ."] Packet sent\n");

            $reply = $sender->recv();
            $replyDecode = json_decode($reply);

            $ip = $replyDecode->ip;
            $port = $replyDecode->port;
            $msg = $replyDecode->msg;
            $msgType = $replyDecode->msg_type;
            $reqType = $replyDecode->req_type;
            $msgId = $replyDecode->msg_id;

            #ACK Reply from host receiver
            if($reqType == 2){
                $sql = "UPDATE outbox set outbox_status = 2 where outbox_id = " .$outboxId;
                $conn->query($sql);
            }

            #BAD ACK Reply
            else if($reqType == 3){
                echo "[". $msgId ."] Failed to send packet. Retrying..\n";
                $retry = true;
                $retryCount = 1;
                while($retry){
                    $sender->send(json_encode($packet));
                    
                    echo "[". $msgId ."] Retry sending again. " .$retryCount ." of 5 chance.\n";
                    
                    $afterRetryReply = $sender->recv();
                    $afterRetryReply = json_decode($afterRetryReply);

                    $afterRetryReqType = $afterRetryReply->req_type;
                    if($afterRetryReqType == 2 || $retryCount == 5){
                        $retry = false;
                    }
                    $retryCount++;
                }

                if(!$retry){
                    echo "[". $msgId ."] Failed to send packet.\n";
                }
            }
        }
        
        $updateStatusToProceed = "UPDATE outbox set outbox_status = 1 where outbox_status = 0";
        $conn->query($updateStatusToProceed);
    }

    else{
        sleep(1);
    }
    // exit;
    #################################
    
    // sleep(1);
}


// $sender->connect("tcp://127.0.0.1:5555");

// $request = [
//     'ip'    => '127.0.0.1',
//     'port'  => '5555',
//     'msg'   => '#CariDosen'
// ];

// $request = json_encode($request);
// $sender->send($request);
// $reply = $sender->recv();
// printf ("Received reply: [%s]\n", $reply);
