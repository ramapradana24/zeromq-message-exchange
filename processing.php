<?php

include('db.php');

$newInboxQuery = 'SELECT * FROM inbox 
    WHERE inbox_status = 0 
    AND inbox_req_type = 1
    AND inbox_msg_type = 1';

while(true){
    $result = $connection->query($newInboxQuery);

    #there is message in inbox
    if(mysqli_num_rows($result) > 0){
        while($row = $result->fetch_assoc()){
            
            #process the message
            $inboxId = $row['inbox_id'];
            $msg = $row['inbox_message'];
            $ip = $row['inbox_client_ip'];
            $port = $row['inbox_client_port'];
            $hostMsgId = $row['host_msg_id'];

            echo "[". $inboxId ."] Request: ". $msg .".\n";

            #update inbox set proceed
            $updateInbox = "UPDATE inbox SET inbox_status = 1, proceed_at = '". date('Y-m-d H:i:s') ."' WHERE inbox_id = " . $inboxId;
            if($connection->query($updateInbox)) echo "[". $inboxId ."] Status updated to PROCEED\n";
            else{
                echo "[". $inboxId ."] Failed to set status to PROCEED. Skip this inbox.\n";
                continue;
            }

            #parsing message
            $param = explode("#", $msg);
            
            $searchProcessQuery = "SELECT * FROM process WHERE name = '". $param[0] ."' LIMIT 1";
            $process = $connection->query($searchProcessQuery);

            #if there is a process match
            if(mysqli_num_rows($process) > 0){
                while($row = $process->fetch_assoc()){
                    $processQuery = $row['query'];
                    $guide = $row['guide'];
                }
                
                
                #split query into pieces of word and replace ? with param
                $parsedQuery = explode(" ", $processQuery);
                
                $paramIndex = 1;
                foreach($parsedQuery as $index => $value){
                    if($paramIndex >= sizeof($param)){
                        break;
                    }else{
                        $parsedQuery[$index] = str_replace("?", substr($param[$paramIndex], 1), $parsedQuery[$index], $count);
                        if($count >= 1){
                            $paramIndex++;
                        }
                    }
                }

                $parsedQuery = implode(" ", $parsedQuery);

                #execute query
                $result = $connection->query($parsedQuery);
                if(mysqli_num_rows($result) > 0){
                    #get all column name
                    $fields = array();
                    while($fieldName = mysqli_fetch_field($result))
                        array_push($fields, $fieldName->name);
                    

                    #get the column value
                    $itemListString = "";
                    
                    while($row = $result->fetch_assoc()){
                        $item = array();
                        foreach($fields as $column){
                            $item[$column] = $row[$column];
                            $itemListString .= $column .": ".$row[$column] ." \n";
                        }
                    }

                    #insert to outbox
                    $toOutbox = "INSERT INTO outbox(
                        inbox_id,
                        outbox_message,
                        outbox_client_ip,
                        outbox_client_port,
                        outbox_msg_type,
                        outbox_req_type,
                        host_msg_id
                    ) VALUES(
                        ". $inboxId .",
                        '". $itemListString ."',
                        '". $ip ."',
                        ". $port .",
                        1,
                        3,
                        ". $hostMsgId ."
                    )";
                    
                    if($connection->query($toOutbox)) echo "[". $inboxId ."] Reply: " . json_encode($itemListString) ."\n";
                    else echo "[". $inboxId ."] Failed to send to outbox. Reply: " . json_encode($itemListString) ."\n";
                }

                #no result, insert to outbox
                else{
                    $toOutbox = "INSERT INTO outbox(
                        inbox_id,
                        outbox_message,
                        outbox_client_ip,
                        outbox_client_port,
                        outbox_msg_type,
                        outbox_req_type,
                        host_msg_id
                    ) VALUES(
                        ". $inboxId .",
                        'No Result',
                        '". $ip ."',
                        ". $port .",
                        1,
                        3,
                        ". $hostMsgId ."
                    )";
                    
                    if($connection->query($toOutbox)) echo "[". $inboxId ."] Reply: No Result\n";
                    else echo "[". $inboxId ."] Failed to insert to OUTBOX. Reply: No Result\n";
                }

            }
            
            #if it is not match
            else{
                $toOutbox = "INSERT INTO outbox(
                    inbox_id,
                    outbox_message,
                    outbox_client_ip,
                    outbox_client_port,
                    outbox_msg_type,
                    outbox_req_type,
                    host_msg_id
                ) VALUES(
                    ". $inboxId .",
                    'Invalid Keyword!',
                    '". $ip ."',
                    ". $port .",
                    1,
                    3,
                    ". $hostMsgId ."
                )";

                $connection->query($toOutbox);
                echo "[". $inboxId ."] Invalid Keyword\n";
            }

            #update inbox status to outboxed
            $updateInbox = "UPDATE inbox SET inbox_status = 2, outbox_at = '". date('Y-m-d H:i:s') ."' WHERE inbox_id = " . $inboxId;
            if($connection->query($updateInbox)) echo "[". $inboxId ."] Status set to OUTBOXED\n";
            else echo "[". $inboxId ."] Failed to set status to OUTBOXED\n";

            exit;
        }
    }
    
    #no message found, wait 1 second before find it again
    else{
        sleep(1);
    }
}
