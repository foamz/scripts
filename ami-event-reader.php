<?php
/*  
 -- Run this script in a screen session. This updates the database table our wallboard reads from.
 -- You will need to edit this script to work for you.
*/

// turn off errors & notices -- this script appear to throw some notices...
error_reporting(0);

//DEBUG - Set to TRUE to display a bunch more stuff on the screen
$debug = false;

//Script should run forever, so prevent it from timing out
set_time_limit(0);

// list trunks in this array
$trunkarr = array("SIP/provider1", "SIP/provider2", "SIP/provider3");

// functions
class qm_queues {
  protected $db = null;
  public function __construct() {
    $this->asteriskcdrdb_db = new PDO('mysql:dbname=asteriskcdrdb;host=127.0.0.1', 'username', 'password');
  }

  public function do_status($extension,$agent,$status,$paused) {
    $sql = "UPDATE extensionmap SET status = :status, paused = :paused WHERE extension = :extension AND agent = :agent ";
    $params = [
      ':extension' => $extension,
      ':agent' => $agent,
      ':status' => $status,
      ':paused' => $paused,
    ];
    $handle = $this->asteriskcdrdb_db->prepare($sql);
    $handle->execute($params);
  }

  public function do_call($extension,$channel,$call_id) {
    $sql = "UPDATE extensionmap SET current_chan = :channel, call_id = :call_id WHERE extension = :extension ";
    $params = [
      ':extension' => $extension,
      ':channel' => $channel,
      ':call_id' => $call_id,
    ];
    $handle = $this->asteriskcdrdb_db->prepare($sql);
    $handle->execute($params);
  }

  public function do_ibcall($extension,$channel,$call_id,$queue) {
    $sql = "UPDATE extensionmap SET current_chan = :channel, call_id = :call_id, current_queue = :queue WHERE extension = :extension ";
    $params = [
      ':extension' => $extension,
      ':channel' => $channel,
      ':call_id' => $call_id,
      ':queue' => $queue,
    ];
    $handle = $this->asteriskcdrdb_db->prepare($sql);
    $handle->execute($params);
  }

  public function do_hangup($extension) {
    $sql = "UPDATE extensionmap SET current_chan = '', call_id = '', current_queue = '' WHERE extension = :extension ";
    $params = [
      ':extension' => $extension,
    ];
    $handle = $this->asteriskcdrdb_db->prepare($sql);
    $handle->execute($params);
  }

}

//Use fsockopen to connect the same way you would with Telnet
$fp = fsockopen("127.0.0.1", 1234, $errno, $errstr, 30);

//Unsuccessful connect
if (!$fp) {
    echo "$errstr ($errno)\n";

//Successful connect
} else {

    //LOOP FOREVER - continuously read data
    $line = '';
    $event_array = array();
    while(1) {
        $read = fread($fp,1); //Read one byte at a time from the socket
        $line .= $read;

        //Check if we are at the end of a line
        if ("\n" == $read) {

            // Determine when we have reached a blank line which
            // signals the end of the events info
            if ("\r\n" == $line) {

                //Filter for data related to extensionstatus, linking, unlinking and hangup

/* lets leave out ringing and hangup for now
                // catch ringing
                if ('Newstate'==$event_array['Event'] && substr($event_array['Channel'], 0, 5)=='SIP/1' && 'Ringing'==$event_array['ChannelStateDesc']) {

                    if (false) {
                        echo "RINGING ARRAY \n";
                        print_r($event_array);
                        echo "\n";
                    }
                    flush($fp);

                    // sql here
                    echo "RINGING - EXT: " . $event_array['CallerIDNum'];
                    echo "\n";

                }
*/
                // catch hangups
                if ('Hangup'==$event_array['Event'] && substr($event_array['Channel'], 0, 5)=='SIP/1') {

                    if (false) {
                        echo "HANGUP ARRAY \n";
                        print_r($event_array);
                        echo "\n";
                        // formatted output
                        echo "HANGUP  - EXT: " . $event_array['CallerIDNum'];
                        echo "\n";
                    }
                    flush($fp);

                    // sql here.
                    $dosomething = new qm_queues();
                    $dosomething->do_hangup($event_array['CallerIDNum']);

                }

/* I actually don't need to catch pause/unpause here
                // catch pauses
                if ('Newexten'==$event_array['Event'] && strtok($event_array['Channel'], '-')=='Local/32@queuemetrics' && strpos($event_array['AppData'], "sequence")) {

                    if (true) {
                        echo "PAUSE ARRAY \n";
                        print_r($event_array);
                        echo "\n";
                    }
                    flush($fp);

                    // sql here.
                    echo "PAUSE  - AGENT: agent/" . preg_replace("/[^0-9,.]/", "", $event_array['AppData']);
                    echo "\n";

                }

                // catch unpauses
                if ('Newexten'==$event_array['Event'] && strtok($event_array['Channel'], '-')=='Local/33@queuemetrics' && strpos($event_array['AppData'], "Unpausing")) {

                    if (true) {
                        echo "UNPAUSE ARRAY \n";
                        print_r($event_array);
                        echo "\n";
                    }
                    flush($fp);

                    // sql here.
                    echo "UNPAUSE  - AGENT: agent/" . substr(preg_replace("/[^0-9,.]/", "", $event_array['AppData']), 0, 4);
                    echo "\n";

                }
*/

                // catch OB calls
                if ('BridgeEnter'==$event_array['Event'] && in_array(strtok($event_array['Channel'], '-'), $trunkarr) && 'QDIALAGI'==$event_array['AccountCode']) {

                    if (false) {
                        echo "CONNECT ARRAY \n";
                        print_r($event_array);
                        echo "\n";
                        // formatted output
                        echo "OB CONNECTED - EXT: " . $event_array['ConnectedLineName'] . ", CHAN: " . $event_array['Channel'] . ", CALL_ID: " . $event_array['Linkedid'];
                        echo "\n";
                    }
                    flush($fp);

                    // sql here.
                    $dosomething = new qm_queues();
                    $dosomething->do_call($event_array['ConnectedLineName'],$event_array['Channel'],$event_array['Linkedid']);

                }

                // catch OB calls, but get that queue number as well
                if ('Newexten'==$event_array['Event'] && in_array(strtok($event_array['Channel'], '-'), $trunkarr) && 'QDIALAGI'==$event_array['AccountCode'] && 'QueueLog'==$event_array['Application']) {

                    if (false) {
                        echo "OB WITH QUEUE CONNECT ARRAY \n";
                        print_r($event_array);
                        echo "\n";
                    }
                    flush($fp);

                    // sql here. check condition if OB call
                    $parts = explode( ',', $event_array['AppData']);
                    if($parts[3] == 'CONNECT') {
                      $dosomething = new qm_queues();
                      $dosomething->do_ibcall($event_array['ConnectedLineName'],$event_array['Channel'],$event_array['Linkedid'],$parts[0]);
                    }
                }

                // catch IB calls
                if ('BridgeEnter'==$event_array['Event'] && in_array(strtok($event_array['Channel'], '-'), $trunkarr) && 'QINBOUND'==$event_array['AccountCode']) {
                    if (false) {
                        echo "CONNECT ARRAY \n";
                        print_r($event_array);
                        echo "\n";
                        // formatted output
                        echo "IB CONNECTED - EXT: " . $event_array['ConnectedLineName'] . ", CHAN: " . $event_array['Channel'] . ", CALL_ID: " . $event_array['Linkedid'] . ", QUEUE: " . $event_array['Exten'];
                        echo "\n";
                    }
                    flush($fp);

                    // sql here.
                    $dosomething = new qm_queues();
                    $dosomething->do_ibcall($event_array['ConnectedLineName'],$event_array['Channel'],$event_array['Linkedid'],$event_array['Exten']);

                }

                // catch status changes. we monitor queue 9998
                if ('QueueMemberStatus'==$event_array['Event'] && '9998'==$event_array['Queue']) {

                    if (false) {
                        echo "MEMBER STATE ARRAY \n";
                        print_r($event_array);
                        echo "\n";
                        // formatted output
                        echo "QMEMBER - EXT: " . substr($event_array['StateInterface'], 4) . ", AGENT: " . strtolower($event_array['MemberName']) . ", STATUS: " . $state . ", PAUSED: " . $event_array['Paused'] ;
                        echo "\n";
                    }
                    flush($fp);

                    if($event_array['Status']=='0'){ $state = 'Unknown state'; }
                    if($event_array['Status']=='1'){ $state = 'NOT_INUSE'; }
                    if($event_array['Status']=='2'){ $state = 'INUSE'; }
                    if($event_array['Status']=='3'){ $state = 'BUSY'; }
                    if($event_array['Status']=='4'){ $state = 'INVALID'; }
                    if($event_array['Status']=='5'){ $state = 'UNAVAILABLE'; }
                    if($event_array['Status']=='6'){ $state = 'RINGING'; }
                    if($event_array['Status']=='7'){ $state = 'RINGINUSE'; }
                    if($event_array['Status']=='8'){ $state = 'ONHOLD'; }

                    // sql here.
                    $dosomething = new qm_queues();
                    $dosomething->do_status(substr($event_array['StateInterface'], 4),strtolower($event_array['MemberName']),$state,$event_array['Paused']);

                }

                unset($event_array);

            } else {
                $line_expl = explode(": ", $line, 2);
                $event_array[$line_expl[0]] = trim($line_expl[1]);
            }
            $line = '';

        } //end IF -> Check if we are at the end of a line
    } //end WHILE -> LOOP FOREVER

    fclose($fp); //Will never get here, but looks good to have it!
} //end ELSE -> Successful connect

?>
