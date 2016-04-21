<?php
$sock = socket_create_listen(4040);
socket_getsockname($sock, $addr, $port);
print "Server Listening on $addr:$port\n";
while($c = socket_accept($sock)) {
  $fp = fopen("test/server_commands_received", 'w');
  // fwrite($fp, $port);
  // fclose($fp);

   /* do something useful */
  socket_getpeername($c, $raddr, $rport);
  print "Received Connection from $raddr:$rport\n";
  print_r($c);
  while($l = socket_read($c, 4096, PHP_NORMAL_READ)) {
    print "Received line: $l\n";
    fwrite($fp, $l);
    socket_write($c, "ok\n");
    print "Sent ok\n";
  }
}
socket_close($sock);


// client.php:
// <?php
// $fp = fopen($port_file, 'r');
// $port = fgets($fp, 1024);
// fclose($fp);
// $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
// socket_connect($sock, '127.0.0.1', $port);
// socket_close($sock);
