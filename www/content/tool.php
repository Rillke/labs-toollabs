<H1>Wikimedia Tool Labs</H1>
Welcome to the Tool Labs project, the home of community-maintained external tools supporting Wikimedia projects and their users.

  <TABLE CLASS="tool-info" COLS=2 WIDTH="95%">
<?  $users = shell_exec("/usr/bin/getent group|/bin/grep ^local-");
    foreach(split("\n", $users) as $ln) {
      $fields = split(":", $ln);
      if(array_key_exists(3, $fields)) {
        list($user, $pass, $gid, $members) = $fields;
	if($user = "local-$param") {
          $u = posix_getpwuid($gid);
          $home = $u['dir'];
          $indices = glob("$home/public_html/index.*");
          $user = preg_replace("/^local-/", '', $user);
          $tool = array( 'home' => $home );
          $tool['maints'] = array();
          foreach(split(",", $members) as $uid) {
            $u = posix_getpwnam($uid);
            $tool['maints'][] = $u['gecos'];
          }
          if(array_key_exists(0, $indices))
            $tool['uri'] = "/$user/";
          if(is_dir("$home/public_html"))
            $tools[$user] = $tool;
        }
      }
    }
    ksort($tools);
    foreach($tools as $tool => $t): ?>
    <TR><TH class="tool-name"><?
      if(array_key_exists('uri', $t)) {
        print "<a href=\"" . $t['uri'] . "\">$tool</a>";
      } else {
        print $tool;
      }
?>
    </TH><TD></TD></TR>
      <TR><TH>Description</TH>
        <TD><?
        if(is_readable($t['home']."/.description")) {
          $desc = file_get_contents($t['home']."/.description", false, NULL, 0, 2048);
          print  $purifier->purify($desc);
        }
      ?></TD></TR>
      <TR><TH>Maintainers</TH>
      <TD><?
        foreach($t['maints'] as $maint):
          ?><A HREF="https://wikitech.wikimedia.org/wiki/User:<?= $maint ?>"><?= ucfirst($maint) ?></A><?
        endforeach;
?>      <p><span class="mw-editsection" style="display:block;">[
        <a href="https://wikitech.wikimedia.org/w/index.php?title=Special:NovaServiceGroup&action=addmember&projectname=tools&servicegroupname=local-<?=$tool?>">add</a> / 
	<a href="https://wikitech.wikimedia.org/w/index.php?title=Special:NovaServiceGroup&action=deletemember&projectname=tools&servicegroupname=local-<?=$tool?>">remove</a> maintainers]</span>
      </TD></TR>
<?  endforeach; ?>
  </TABLE>
