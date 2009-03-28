<?php

require_once 'net/http.inc';

define('CLOBBER', FALSE);
define('URL_JOLT_FORUM', 'http://forums.jolt.co.uk/archive/index.php/f-%d.html');
define('URL_JOLT_FORUM_PAGER', 'http://forums.jolt.co.uk/archive/index.php/f-%d-p-%d.html');
define('URL_JOLT_TOPIC', 'http://forums.jolt.co.uk/archive/index.php/t-%d.html');
define('URL_JOLT_TOPIC_PAGER', 'http://forums.jolt.co.uk/archive/index.php/t-%d-p-%d.html');
define('URL_JOLT_ROOT', 'http://forums.jolt.co.uk/archive/index.php/');

function index_jolt_forum($id, $path = 'files') {
  $start = microtime(TRUE);
  print "Creating path... ";
  r_mkdir("$path/$id/");
  print "$path/$id\n";
  print "Determining forum size... ";
  
  $data = download(sprintf(URL_JOLT_FORUM, $id), "$path/$id/f-$id.html");
  preg_match_all("/f-$id(-p-([0-9]+))?\.html/", $data, $pages);
  $pages = $pages[2][count($pages[2]) - 1];
  print "$pages pages, ~" . ($pages*250) . " topics\n";
  preg_match_all('%<a href="t-([0-9]+)\.html">(.+?)</a>%', $data, $topics_raw);
  $topics = array_combine($topics_raw[1], $topics_raw[2]);
  
  print "Saving forum index... ";
  print_c("1 / $pages, " . sprintf('%0.2f%%', 100 / $pages) . ", " . remaining($start, 1, $pages));
  for ($i = 2; $i <= $pages; $i++) {
    $data = download(sprintf(URL_JOLT_FORUM_PAGER, $id, $i), "$path/$id/f-$id-p-$i.html");
    
    preg_match_all('%<a href="t-([0-9]+)\.html">(.+?)</a>%', $data, $topics_raw);
    $topics += array_combine($topics_raw[1], $topics_raw[2]);
    print_c("$i / $pages, " . sprintf('%0.2f%%', $i * 100 / $pages) . ", " . remaining($start, $i, $pages));
  }

  print " done\n";
  print_c();
  print "Storing topic list in plain text... ";
  $topic_list = "";
  foreach ($topics as $tid => $title) $topic_list .= html_entity_decode("$tid\t$title\n");
  file_put_contents("$path/$id/topics.txt", $topic_list);
  print "done\n";
  $start = microtime(TRUE);
  print "Archiving topics... ";

  print_c("0 / " . count($topics) . ", 0.00%, " . remaining($start, 0, count($topics)));
  $done = 0;
  foreach ($topics as $tid => $title) {
    $data = download(sprintf(URL_JOLT_TOPIC, $tid), "$path/$id/t-$tid.html");
    preg_match_all("/t-$tid-p-([0-9]+)\.html/", $data, $pages);
    if ($pages[1]) {
      $pages = $pages[1][count($pages[1]) - 1];
      for ($i = 2; $i <= $pages; $i++) {
        $data = download(sprintf(URL_JOLT_TOPIC_PAGER, $tid, $i), "$path/$id/t-$id-p-$i.html");
      }
    }
    print_c(++$done . " / " . count($topics) . ", ". sprintf('%0.2f%%', $done*100/count($topics)) . ", " . remaining($start, $done, count($topics)));
  }
  print " done\n";
}

function print_c($current = FALSE) {
  static $last = "";
  if ($current) echo str_repeat( chr(8), strlen( $last ) ) . $current;
  $last = $current;
}

function r_mkdir($path) {
  $base = '.';
  foreach (explode("/", $path) as $token) {
    $base .= "/$token";
    if (!file_exists($base)) {
      if (!mkdir($base)) return FALSE;
    }
    else if (!is_dir($base)) return FALSE;
  }
  return TRUE;
}

function remaining($start, $done, $total) {
  $current = microtime(TRUE);
  
  $elapsed = $current - $start;
  if ($done) $remaining = $elapsed / ($done) * ($total - $done);
  else $remaining = 0;

  return format_time($remaining) . " remaining (" . format_time($elapsed) . ' elapsed)';
}

function format_time($remaining) {
  return 
    str_pad(floor($remaining / 3600), 2, 0, STR_PAD_LEFT) . ":" . 
    str_pad(floor(($remaining % 3600) / 60), 2, 0, STR_PAD_LEFT) . ":" . 
    str_pad(floor($remaining % 60), 2, 0, STR_PAD_LEFT);
}

function download($url, $local) {
  if (CLOBBER || !file_exists($local)) {
    $resp = http($url);
    $data = str_replace(URL_JOLT_ROOT, '', $resp->data);
    file_put_contents($local, $data);
    return $data;
  }
  else {
    return file_get_contents($local);
  }
}

////////////////////////

if (@$_SERVER['REMOTE_ADDR']) exit("<span style='color:red;font-weight:bold'>Error: This script can only be run in the command-line, not from the web.</span>");

if (!$forum = @$_SERVER['argv'][1]) exit("Usage: php archiver.php <forum-id>\n");

index_jolt_forum($forum);


