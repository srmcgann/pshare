<?
  include('config.php');
  $mode = 'listen';

  function init(){
    global $sock;
    if(!($sock = socket_create(AF_INET, SOCK_STREAM, 0))){
      $errorcode = socket_last_error();
      $errormsg = socket_strerror($errorcode);
      die("Couldn't create socket: [$errorcode] $errormsg \n");
    }
  }

  init();

  function encapsulate($command, $data, $file=''){
    switch($command){
      case 'sendText':
        $ret = "><><$command><><><><data><><$data";
      break;
      case 'sendFile':
        $filesize = strlen($data);
        $file = explode("/", $file);
        $file = $file[sizeof($file)-1];
        $ret = "><><$command><><><><filename><><$file><></filename><><><><filesize><><$filesize><></filesize><><><><data><><$data";
      break;
    }
    return $ret;
  }

  function scan($input){
    $commands = [
      '><><sendText><><',
      '><><sendFile><><',
      '><><filename><><',
      '><><filesize><><',
      "><><data><><"
    ];
    $m = [];
    $end = false;
    for($i=0; $i<sizeof($commands);++$i){
      $command = $commands[$i];
      if(strpos($input, $commands[$i])!==false){
        switch($i){
          case 0:
            array_push($m, ['command','sendText']);
          break;
          case 1:
            array_push($m, ['command', 'sendFile']);
          break;
          case 2:
            $filename = explode("><><filename><><", $input)[1];
            $filename = explode("><></filename><><", $filename)[0];
            array_push($m, ['filename', $filename]);
          break;
          case 3:
            $filesize = explode("><><filesize><><", $input)[1];
            $filesize = explode("><></filesize><><", $filesize)[0];
            array_push($m, ['filesize', $filesize]);
          break;
          case 4:
            $filedata = explode("><><data><><", $input)[1];
            array_push($m, ['data', $filedata]);
          break;
        }
      }
    }
    if(!sizeof($m)){
      array_push($m, ['data', $input]);
    }
    return $m;
  }

  function listen($ip, $port){
    global $sock, $saveLoc;
    //$fp = fopen('log', 'w');
    if( !socket_bind($sock, $ip, $port) ){
      $errorcode = socket_last_error();
      $errormsg = socket_strerror($errorcode);
      die("Could not bind socket : [$errorcode] $errormsg \n");
    }
    if(!socket_listen ($sock , 10)){
      $errorcode = socket_last_error();
      $errormsg = socket_strerror($errorcode);
      die("Could not listen on socket : [$errorcode] $errormsg \n");
    }
    echo "listening on $ip, port: $port\n";
    $client = socket_accept($sock);
    if(socket_getpeername($client , $address , $port)){
      echo "Client $address : $port is now connected. \n";
    }
    $mode='';
    $end = false;
    do{
      $bytes_received = 0;
      do{
        $input = socket_read($client, 1024000);
        $m = scan($input);
        $fileData = '';
        forEach($m as $entry){
          switch($entry[0]){
            case 'command':
              switch($entry[1]){
                case 'sendText':
                  echo ">>> incoming message <<<\n";
                  $mode = $entry[1];
                break;
                case 'sendFile':
                  if($mode == 'sendFile'){
                    fclose($rec_file);
                    $bytes_received=0;
                    echo "\r>>> received file $filename  <<<\n";
                  }
                  $mode = $entry[1];
                break;
              }
            break;
            case 'data':
              $data = $entry[1];
              if($mode == 'sendText'){
                echo $data;
                $bytes_received += strlen($data);
              }elseif($mode == 'sendFile' ){
                $bytes_received += strlen($data);
                fwrite($rec_file, $data);
                $perc = @min(100, (round($bytes_received / $filesize * 10000)/100)) . "% (" . min(round($filesize/10000)/100,round($bytes_received/1000)/100) . "MB / " . (round($filesize/10000)/100) . "MB)";
                echo "\r$perc        ";
              }
            break;
            case 'filesize':
              $filesize = $entry[1];
            break;
            case 'filename':
              $filename = $entry[1];
              $rec_file = fopen("$saveLoc/$filename", 'w');
            break;
          }
        }
      }while($input);
      if($mode == "sendText"){
        echo "\n>>> end of message <<<\n";
      }
      if($mode == "sendFile"){
        echo "\r>>> received file $filename  <<<\n";
      }
      $client = socket_accept($sock);
    }while(true);
  }

  function send($ip, $port, $files){
    global $sock;

    if(!socket_connect($sock , $ip, $port)){
      $errorcode = socket_last_error();
      $errormsg = socket_strerror($errorcode);
      die("Could not connect: [$errorcode] $errormsg \n");
    }
    echo "Connection established \n";
    
    forEach($files as $file){
      if($sendData = file_get_contents($file)){
        $filedata = encapsulate('sendFile', $sendData, $file);
        if( ! socket_send ( $sock , $filedata , strlen($filedata) , 0)){    
          $errorcode = socket_last_error();
          $errormsg = socket_strerror($errorcode);
          die("Could not send data: [$errorcode] $errormsg \n");
        }
        echo "sent file: \"$file\" \n";    
      }else{
        echo "file not found (\"$file\")";
      }
    }
  }

  function message($ip, $port, $message){
    global $sock;
    if(!socket_connect($sock , $ip, $port)){
      $errorcode = socket_last_error();
      $errormsg = socket_strerror($errorcode);
      die("Could not connect: [$errorcode] $errormsg \n");
    }
    echo "Connection established \n";
    $sendData = encapsulate("sendText", $message);
    if( ! socket_send ( $sock , $sendData , strlen($sendData) , 0)){
      $errorcode = socket_last_error();
      $errormsg = socket_strerror($errorcode);
      die("Could not send data: [$errorcode] $errormsg \n");
    }
    echo "sent: \"$message\" \n";
  }

  $message = 'test data';

  if(sizeof($argv) > 3) {
    $IP = $argv[2];
    //$port = $argv[3];
    $mode = strtolower($argv[1]);
    $ar = [];
    for($i = 3; $i < sizeof($argv); ++$i){
      $ar[] = $argv[$i];
    }
    $params = implode(' ', $ar);
  }

  switch($mode){
    case 'send': send($IP, $port, $ar); break;
    case 'message': message($IP, $port, $params); break;
    case 'listen':  listen($IP, $port); break;
  }
?>
