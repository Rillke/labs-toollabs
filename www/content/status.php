<?
  function humantime($secs) {
    if($secs < 120)
      return "$secs"."s";
    $secs = (int)$secs;
    $mins = (int)($secs / 60);
    $secs = $secs % 60;
    if($mins < 60)
      return "$mins"."m$secs"."s";
    $hours = (int)($mins / 60);
    $mins = $mins % 60;
    return "$hours"."h$mins"."m";
  }

  function humanmem($megs) {
    if($megs > 1024) {
      $megs = (int)($megs / 102.4);
      $megs /= 10.0;
      return "$megs"."G";
    }
    return "$megs"."M";
  }

  function toarray($XML) {
    $array = array();

    if(is_object($XML)) {
      $XML = get_object_vars($XML);
    }
    if(is_array($XML)) {
      foreach($XML as $i => $val) {
        if(is_object($val) || is_array($val)) {
          $val = toarray($val);
        }
        $array[$i] = $val;
      }
    }
    return $array;
  }

  function mmem($str) {
    if(preg_match("/M$/", $str)) {
      return 0+$str;
    }
    if(preg_match("/G$/", $str)) {
      return 1024*$str;
    }
    return -1;
  }

  $rawjobs = toarray(simplexml_load_string(`PATH=/bin:/usr/bin /usr/bin/qstat -u '*' -r -xml`));
  $rawhosts = toarray(simplexml_load_string(`PATH=/bin:/usr/bin /usr/bin/qhost -F h_vmem -xml`));
?>
<H1>Wikimedia Tool Labs</H1>
This is the web server for the Tool Labs project, the home of community-maintained external tools supporting Wikimedia projects and their users.
<H2>Grid status</H2>
<?
  $jobs = array();
  foreach($rawjobs['queue_info']['job_list'] as $jl) {
    $jobid = $jl['JB_job_number'];
    $job = toarray(simplexml_load_string(`PATH=/bin:/usr/bin /usr/bin/qstat -xml -j $jobid|sed -e 's/JATASK:[^>]*/jatask/g'`));
    $job = $job['djob_info']['element'];
    $j = array();
    $tool = $job['JB_owner'];
    $j['tool'] = preg_replace('/^local-(.*)$/', "$1", $tool);
    $j['sub'] = $job['JB_submission_time'];
    $j['name'] = $job['JB_job_name'];
    foreach($job['JB_hard_resource_list'] as $rval) {
      if($rval['CE_name'] == 'h_vmem') {
        $j['mem_alloc'] = intval($rval['CE_doubleval']/1048576);
      }
    }
    $j['tasks'] = 0;
    $j['mem_used'] = 0;
    $j['mem_max'] = 0;
    $j['cpu'] = 0;
    $j['state'] = $jl['@attributes']['state'];
    $j['start'] = $jl['JAT_start_time'];
    $j['slots'] = $jl['slots'];
    $host = $jl['queue_name'];
    $j['host'] = preg_replace('/^.*@([^\.]*)\..*$/', "$1", $host);
    $j['queue'] = preg_replace('/^(.*)@[^\.]*\..*$/', "$1", $host);
    foreach($job['JB_ja_tasks'] as $task) {
      $j['tasks']++;
      foreach($task['JAT_scaled_usage_list']['scaled'] as $usage) {
        switch($usage['UA_name']) {
        case 'cpu':
          $j['cpu'] += $usage['UA_value'];
          break;
        case 'vmem':
          $j['mem_used'] += intval($usage['UA_value']/1048576);
          break;
        case 'maxvmem':
          if(intval($usage['UA_value']/1048576) > $j['mem_max'])
            $j['mem_max'] = intval($usage['UA_value']/1048576);
          break;
        }
      }
    }
    $jobs[$job['JB_job_number']] = $j;
  }
  foreach($rawhosts['host'] as $hl) {
    $h = array();
    $host = $hl['@attributes']['name'];
    $hname = preg_replace('/^([^\.]*)\..*$/', "$1", $host);
    if($hname === 'global')
      continue;
    $h['arch'] = $hl['hostvalue'][0];
    $h['use'] = $hl['hostvalue'][2] / $hl['hostvalue'][1];
    $h['mem'] = mmem($hl['hostvalue'][4]) / mmem($hl['hostvalue'][3]);
    $hosts[$hname] = $h;
  }
  ksort($hosts);
  ksort($jobs);
  foreach($hosts as $host => $h):
      ?><DIV CLASS="hostline">
	  <SPAN CLASS="hostname"><?= $host ?></SPAN>
          <SPAN><B>Load:</B> <?= (int)($h['use']*1000)/10 ?>%</SPAN>
          <SPAN><B>Memory:</B> <?= (int)($h['mem']*1000)/10 ?>%</SPAN>
        </DIV>
      <TABLE CLASS="hostjobs"><?
      foreach($jobs as $jobid => $j):
      if($j['host'] != $host)
        continue;
          ?><TR CLASS="jobline-<?= $j['state'] ?>">
          <TD CLASS="jobno"><?= $jobid ?></TD>
          <TD CLASS="jobname"><SPAN><?= $j['name'] ?></SPAN></TD>
          <TD CLASS="jobtool"><A HREF="/?list#<?= $j['tool'] ?>"><?= $j['tool'] ?></A></TD>
          <TD CLASS="jobstate"><SPAN><?= ucfirst($j['queue']) ?> / <?= ucfirst($j['state']) ?></SPAN></TD>
          <TD CLASS="jobtime"><SPAN><?= strftime("%F %T", $j['sub']) ?></SPAN></TD>
          <TD CLASS="jobinfo">
            <SPAN><B>CPU:</B> <?= humantime($j['cpu']) ?></SPAN>
            <SPAN><B>VMEM:</B> <?= humanmem($j['mem_used']) ?>/<?= humanmem($j['mem_alloc']) ?>
              <? if($j['mem_max'] > $j['mem_used']): ?>
                (peak <?= humanmem($j['mem_max']) ?>)
              <? endif; ?>
            </SPAN>
          </TD>
        </TR><?
    endforeach;
    print "</TABLE>\n";
  endforeach;
?></TABLE>
