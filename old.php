#!/usr/bin/env php
<?php
error_reporting(E_ALL);

/* Permitir al script esperar para conexiones. */
set_time_limit(0);

/* Activar el volcado de salida implícito, así veremos lo que estamo obteniendo
 * mientras llega. */
ob_implicit_flush();

$address = '127.0.0.1';
$port = 1234;

$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

echo $sock;

if ($sock === false) {
    echo "socket_create() Fail reason: " . socket_strerror(socket_last_error()) . "\n";
}

if (socket_bind($sock, $address, $port) === false) {
    echo "socket_bind() Fail reason: " . socket_strerror(socket_last_error($sock)) . "\n";
}

if (socket_listen($sock, 5) === false) {
    echo "socket_listen() Fail reason: " . socket_strerror(socket_last_error($sock)) . "\n";
}

//clients array
$clients = array();

do {
    $read = array();
    $read[] = $sock;

    $read = array_merge($read,$clients);
    $write = NULL;
    $except = NULL;


    // Set up a blocking call to socket_select
    if(socket_select($read,$write, $except, $tv_sec = 5) < 1)
    {
        continue;
    }

    // Handle new Connections
    if (in_array($sock, $read)) {

        if (($msgsock = socket_accept($sock)) === false) {
            echo "socket_accept() Fail reason: " . socket_strerror(socket_last_error($sock)) . "\n";
            break;
        }

        $clients[] = $msgsock;

        $key = array_keys($clients, $msgsock);

        echo "new client? $msgsock \n";

        perform_handshaking($header, $msgsock, $address, $port); //perform websocket handshake
        /* Enviar instrucciones. */
        $msg = "\nBienvenido al Servidor De Prueba de PHP. \n" .
        "Usted es el cliente numero: {$key[0]}\n" .
        "Para salir, escriba 'quit'. Para cerrar el servidor escriba 'shutdown'.\n";
        socket_write($msgsock, $msg, strlen($msg));

    }

    // Handle Input
    foreach ($clients as $key => $client) { // for each client
        if (in_array($client, $read)) {

            if (false === ($buf = socket_read($client, 2048, PHP_NORMAL_READ))) {

                echo "socket_read() Fail reason: " . socket_strerror(socket_last_error($client)) . "\n";

                unset($clients[$key]);
                socket_close($client);
                break;
            }
            if (!$buf = trim($buf)) {
                continue;
            }
            if ($buf == 'quit') {
                unset($clients[$key]);
                socket_close($client);
                break;
            }
            if ($buf == 'shutdown') {
                socket_close($client);
                break 2;
            }

            $talkback = "Cliente {$key}: Usted dijo '$buf'.\n";
            socket_write($client, $talkback, strlen($talkback));
            echo "$buf\n";
        }

    }
} while (true);

socket_close($sock);





//handshake new client.
function perform_handshaking($receved_header,$client_conn, $host, $port)
{
    $headers = array();
    $lines = preg_split("/\r\n/", $receved_header);
    foreach($lines as $line)
    {
        $line = chop($line);
        if(preg_match('/\A(\S+): (.*)\z/', $line, $matches))
        {
            $headers[$matches[1]] = $matches[2];
        }
    }

    $secKey = $headers['Sec-WebSocket-Key'];
    $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
    //hand shaking header
    $upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
    "Upgrade: websocket\r\n" .
    "Connection: Upgrade\r\n" .
    "WebSocket-Origin: $host\r\n" .
    "WebSocket-Location: ws://$host:$port/demo/shout.php\r\n".
    "Sec-WebSocket-Accept:$secAccept\r\n\r\n";
    socket_write($client_conn,$upgrade,strlen($upgrade));
}
?>