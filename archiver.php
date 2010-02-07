<?php

/******************************************************
 *               Jolt Forum Archiver                  *
 *           an Ermarian Network production           *
 *        Arancaytar <arancaytar@ermarian.net>        *
 *                                                    *
 * This application can be modified and distributed   *
 * under the GNU General Public License 3.0 or later. *
 * Libraries based on the Drupal project (GPL 2) are  *
 * included.                                          *
 ******************************************************/


require_once 'net/http.inc';

// Set to TRUE to force download of files already stored.
define('CLOBBER', FALSE);

// Set to TRUE to force re-parsing of local files. Needed only in rare cases of malfunction.
define('REPARSE', FALSE);

define('URL_JOLT_ROOT', 'http://forums.joltonline.com/archive/index.php/');
define('URL_JOLT_FORUM', URL_JOLT_ROOT . 'f-%d.html');
define('URL_JOLT_FORUM_PAGER', URL_JOLT_ROOT . 'f-%d-p-%d.html');
define('URL_JOLT_TOPIC', URL_JOLT_ROOT . 't-%d.html');
define('URL_JOLT_TOPIC_PAGER', URL_JOLT_ROOT . 't-%d-p-%d.html');

function index_jolt_forum($id, $path = 'files') {
  $start = microtime(TRUE);
  print "Creating path... ";
  r_mkdir("$path/$id/");
  print "$path/$id\n";

  $topics = array();
  
  print "Determining forum size... ";
  
  $data = download(sprintf(URL_JOLT_FORUM, $id), "$path/$id/f-$id.html", TRUE); // force parse.
  preg_match_all("/f-$id(-p-([0-9]+))?\.html/", $data, $pages);
  $pages = $pages[2] ? $pages[2][count($pages[2]) - 1] : 1;
  print "$pages pages, ~" . ($pages*250) . " topics\n";
 
  if (file_exists("$path/$id/topics.txt")) {
    print "Detected existing archive. Reload topics from plain-text index... ";
    $topic_index = explode("\n", trim(file_get_contents("$path/$id/topics.txt")));
    foreach ($topic_index as $line) {
      $line = explode("\t", $line);
      $topics[$line[0]] = $line[1];
    }
    print "reloaded " . count($topics) . " topics.\n";
  }
  $before = count($topics);
  
  preg_match_all('%<a href="t-([0-9]+)\.html">(.+?)</a>%', $data, $topics_raw);
  if ($topics_raw[1]) $topics += array_combine($topics_raw[1], $topics_raw[2]);
  
  print "Saving forum index... ";
  print_c("1 / $pages, " . sprintf('%0.2f%%', 100 / $pages) . ", " . remaining($start, 1, $pages));
  for ($i = 2; $i <= $pages; $i++) {
    $data = download(sprintf(URL_JOLT_FORUM_PAGER, $id, $i), "$path/$id/f-$id-p-$i.html");
    
    preg_match_all('%<a href="t-([0-9]+)\.html">(.+?)</a>%', $data, $topics_raw);
    if ($topics_raw[1]) $topics += array_combine($topics_raw[1], $topics_raw[2]);
    print_c("$i / $pages, " . sprintf('%0.2f%%', $i * 100 / $pages) . ", " . remaining($start, $i, $pages));
  }

  print " done\n";
  print_c();
  
  if ($before != count($topics)) {
    print "Storing topic list in plain text... ";
    $topic_list = "";
    foreach ($topics as $tid => $title) $topic_list .= html_entity_decode("$tid\t$title\n");
    file_put_contents("$path/$id/topics.txt", $topic_list);
    print "done\n";
  }
  else print "Skipping topic list, as there were no changes.\n";
  
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
        $data = download(sprintf(URL_JOLT_TOPIC_PAGER, $tid, $i), "$path/$id/t-$tid-p-$i.html");
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

function download($url, $local, $reparse = REPARSE) {
  if (CLOBBER || !file_exists($local)) {
    $resp = http($url);
    $data = str_replace(URL_JOLT_ROOT, '', $resp->data);
    file_put_contents($local, $data);
    return $data;
  }
  else if ($reparse) {
    return file_get_contents($local);
  }
}

////////////////////////

if (@$_SERVER['REMOTE_ADDR']) exit("<span style='color:red;font-weight:bold'>Error: This script can only be run in the command-line, not from the web.</span>");

if (!$forum = @$_SERVER['argv'][1]) exit("Usage: php archiver.php <forum-id>\n");

index_jolt_forum($forum);


