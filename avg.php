<?php

$logs = file('mysql-slow.log');
$total_time = 0;
$total_run = 0;
foreach($logs as $log){
  if(substr( $log, 0, 7 ) === '# Query'){
    $lock = explode('# Query_time: ',$log);
    $time = explode('  Lock_time',$lock[1]);
    $total_time+=(float)$time[0];
    $total_run++;
  }
}
$avg_time = (float)$total_time/$total_run;
echo 'Avg time: ' . $avg_time;
echo 'Total Queries: '. $total_run;
