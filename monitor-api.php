<?php 
# The nagios-dashboard was written by Morten Bekkelund & Jonas G. Drange in 2010
#
# Patched, modified and added to by various people, see README
# Maintained as merlin-dashboard by Mattias Bergsten <mattias.bergsten@op5.com>
#
# Parts copyright (C) 2010 Morten Bekkelund & Jonas G. Drange
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# See: http://www.gnu.org/copyleft/gpl.html

# Server details for accessing the op5 Monitor API
#
# If you want to use a different authentication module,
# suffix your username with $MyAuthModule - for example,
# if you're defaulting to LDAP but want to use built-in
# authentication with the user monitor, set $user to
# "monitor$Default" (you may need to \-escape the $)

$server = 'https://127.0.0.1';
$user   = 'monitor';
$pw     = 'monitor';

# Dashboard Title
$title = "op5 Monitor Dashboard";

# Unhandled Hosts
if ($_GET["hosts"] == NULL) {
	$uh_hosts        = urlencode('[hosts] all');
} else {
	$uh_hosts        = urlencode('[hosts] ' . $_GET["hosts"]);
}

$uh_hosts_filter = str_replace(' ', '%20', ' and last_hard_state!=0 and acknowledged=0 and scheduled_downtime_depth=0 and in_notification_period=1');

# Unhandled Services
if ($_GET["services"] == NULL) {
	$uh_services        = urlencode('[services] all');
} else {
	$uh_services        = urlencode('[services] ' . $_GET["services"]);
}

$uh_services_filter = str_replace(' ', '%20', ' and host.last_hard_state=0 and last_hard_state!=0 and acknowledged=0 and scheduled_downtime_depth=0 and host.scheduled_downtime_depth=0 and in_notification_period=1 and host.in_notification_period=1');

function api_query($arg) {
   global $user, $pw;
   $browse = curl_init($arg);
   curl_setopt($browse, CURLOPT_USERPWD, "$user:$pw");
   curl_setopt($browse, CURLOPT_RETURNTRANSFER, TRUE);
   curl_setopt($browse, CURLOPT_SSL_VERIFYPEER, false);
   $json = curl_exec($browse);
   $ref = json_decode($json, true);
   if ( ! empty($ref['error']) ) {
      if ( $ref['full_error'] == "connection failed, make sure your server details are correct\n" ) {
         exit;
      }
      print $ref['full_error'];
      exit;
   }
   return $ref;
}

function api_count($arg) {
   $ref = api_query($arg);
   return $ref['count'];
}

function _print_duration($start_time, $end_time) {
    $duration = $end_time - $start_time;
    $days = $duration / 86400;
    $hours = ($duration % 86400) / 3600;
    $minutes = ($duration % 3600) / 60;
    $seconds = ($duration % 60);
    $retval = sprintf("%dd %dh %dm %ds", $days, $hours, $minutes, $seconds);
    return($retval);
}

function sort_by_state($a, $b) {
   if ( $a['state'] == $b['state'] ) {
      if ( $a['host']['name'] > $b['host']['name'] ) {
         return 1;
      }
      else if ( $a['host']['name'] < $b['host']['name'] ) {
         return -1;
      }
      else {
         return 0;
      }
   }
   #else if ( $a['state'] == 3 ) {
   #   return 1;
   #}
   #else if ( $b['state'] == 3 ) {
   #   return -1;
   #}
   else if ( $a['state'] > $b['state'] ) {
      return -1;
   }
   else {
      return 1;
   }
}


#### Tactical Overview - Hosts

# Host down
$hosts_down = api_count("$server/api/filter/count?query=" . $uh_hosts . "%20and%20last_hard_state!=0");

# Host Unreachable
$hosts_unreach = api_count("$server/api/filter/count?query=" . $uh_hosts . "%20and%20last_hard_state=2");

# Hosts
$total_hosts = api_count("$server/api/filter/count?query=" . $uh_hosts);

$hosts_down_pct = round($hosts_down / $total_hosts * 100, 2);
$hosts_unreach_pct = round($hosts_unreach / $total_hosts * 100, 2);
$hosts_up = $total_hosts - ($hosts_down + $hosts_unreach);
$hosts_up_pct = round($hosts_up / $total_hosts * 100, 2);
            
#### Tactical Overview Services

# Services OK
$services_ok = api_count("$server/api/filter/count?query=" . $uh_services . "%20and%20last_hard_state=0");

# Services Warning
$services_warning = api_count("$server/api/filter/count?query=" . $uh_services . "%20and%20last_hard_state=1");

# Services Critical
$services_critical = api_count("$server/api/filter/count?query=" . $uh_services . "%20and%20last_hard_state=2");

# Services Unknown
$services_unknown = api_count("$server/api/filter/count?query=" . $uh_services . "%20and%20last_hard_state=3");

# Total Services
$total_services = api_count("$server/api/filter/count?query=" . $uh_services);

$services_critical_pct = round($services_critical / $total_services * 100, 2);
$services_warning_pct = round($services_warning / $total_services * 100, 2);
$services_unknown_pct = round($services_unknown / $total_services * 100, 2);
$services_ok_pct = round($services_ok / $total_services * 100, 2);

#### Unhandled Hosts

$hosts_limit     = 50;
$unhandled_hosts = "";
$uh_hosts_count  =  api_count("$server/api/filter/count?query=" . $uh_hosts . $uh_hosts_filter);

if ( $uh_hosts_count > 0 ) {
   $hosts = api_query("$server/api/filter/query?query=" . $uh_hosts . $uh_hosts_filter . "&columns=name,alias,last_hard_state_change,last_check&limit=$hosts_limit");
   asort($hosts);

   foreach($hosts as $row) {
      $duration = _print_duration($row['last_hard_state_change'], time());
      $date = date("Y-m-d H:i:s", $row['last_check']);

      $unhandled_hosts .=  "<tr class=\"critical\"><td>" . $row['name'] . "</td><td>" . $row['alias'] . "</td><td>" . $duration . "</td><td>" . $date . "</td></tr>";
   }
}

#### Unhandled Services

$services_limit     = 200;
$unhandled_services = "";
$uh_services_count  = api_count("$server/api/filter/count?query=" . $uh_services . $uh_services_filter);

if ( $uh_services_count > 0 ) {
   $services = api_query("$server/api/filter/query?query=" . $uh_services . $uh_services_filter . "&columns=host.name,description,state,plugin_output,last_hard_state_change,last_check&limit=$services_limit");
   usort($services, "sort_by_state");

   foreach($services as $row) ) {
       if ($row['state'] == 2) {
          $class = "critical";
       } elseif ($row['state'] == 1) {
          $class = "warning";
       } elseif ($row['state'] == 3) {
          $class = "unknown";
       }

       $duration = _print_duration($row['last_hard_state_change'], time());
       $date = date("Y-m-d H:i:s", $row['last_check']);

       $unhandled_services .= "<tr class=\"" . $class . "\"><td>" . $row['host']['name'] . "</td><td>" . $row['description'] . "</td><td>" . $row['plugin_output'] . "</td><td class=\"date date_statechange\">" . $duration . "</td><td class=\"date date_lastcheck\">" . $date . "</td></tr>\n";
   };
}

?>

<div class="dash_tactical_overview">
    <h2>Tactical overview</h2>
    <div class="dash_wrapper">
        <table class="dash_table_tactical_overview">
            <tr class="dash_table_head"><th>Type</th><th>Totals</th><th>%</th></tr>
            <tr class="ok">
                <td>Hosts up</td>
                <td><?php print $hosts_up ?>/<?php print $total_hosts ?></td>
                <td><?php print $hosts_up_pct ?></td>
            </tr>
            <tr class="ok">
	        <?php 
                if ($hosts_down > 0) {
                   print "<td class=\"critical\">Hosts down</td>";
                   print "<td class=\"critical\">$hosts_down/$total_hosts</td>";
                   print "<td class=\"critical\">$hosts_down_pct</td>";
                } else {
                   print "<td>Hosts down</td>";
                   print "<td>$hosts_down/$total_hosts</td>";
                   print "<td>$hosts_down_pct</td>";
                }
	        ?>
            </tr>
            <tr class="ok">
	        <?php 
                if ($hosts_unreach > 0) {
                   print "<td class=\"critical\">Hosts unreachable</td>";
                   print "<td class=\"critical\">$hosts_unreach/$total_hosts</td>";
                   print "<td class=\"critical\">$hosts_unreach_pct</td>";
	        } else {
                   print "<td>Hosts unreachable</td>";
                   print "<td>$hosts_unreach/$total_hosts</td>";
                   print "<td>$hosts_unreach_pct</td>";
	        };
	        ?>
            </tr>
            <tr class="ok">
                <td>Services OK</td>
                <td><?php print $services_ok ?>/<?php print $total_services ?></td>
                <td><?php print $services_ok_pct ?></td>
            </tr>
            <tr class="ok">
	        <?php 
	        if ($services_critical > 0) {
                   print "<td class=\"critical\">Services critical</td>";
                   print "<td class=\"critical\">$services_critical/$total_services</td>";
                   print "<td class=\"critical\">$services_critical_pct</td>";
                } else {
                   print "<td>Services critical</td>";
                   print "<td>$services_critical/$total_services</td>";
                   print "<td>$services_critical_pct</td>";
	        };
	        ?>
            </tr>
            <tr class="ok">
	        <?php 
	        if ($services_warning > 0) {
                   print "<td class=\"warning\">Services warning</td>";
                   print "<td class=\"warning\">$services_warning/$total_services</td>";
                   print "<td class=\"warning\">$services_warning_pct</td>";
                } else {
                   print "<td>Services warning</td>";
                   print "<td>$services_warning/$total_services</td>";
                   print "<td>$services_warning_pct</td>";
                }
	        ?>
            </tr>
            <tr class="ok">
	        <?php 
                if ($services_unknown > 0) {
                   print "<td class=\"unknown\">Services unknown</td>";
                   print "<td class=\"unknown\">$services_unknown/$total_services</td>";
                   print "<td class=\"unknown\">$services_unknown_pct</td>";
                } else {
                   print "<td>Services unknown</td>";
                   print "<td>$services_unknown/$total_services</td>";
                   print "<td>$services_unknown_pct</td>";
                }
	        ?>
            </tr>
        </table>
    </div>
</div>


<div class="dash_unhandled_hosts hosts dash">
    <h2>Unhandled host problems <?php if ($uh_hosts_count) print "($uh_hosts_count)"; ?></h2>
    <div class="dash_wrapper">
        <table class="dash_table">
            <?php
            if ($unhandled_hosts != "") {
               print "<tr class=\"dash_table_head\"><th>Hostname</th><th>Alias</th><th>Duration</th><th>Last check</th></tr>";
               print $unhandled_hosts;
               if ( $uh_hosts_count > $hosts_limit ) {
                  print "<tr class=\"warning\"><td>...</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>";
               }
            } else {
               print "<tr class=\"ok\"><td>No hosts down or unacknowledged</td></tr>";
            }
            ?>
        </table>
    </div>
</div>

<div class="clear"></div>

<div class="dash_unhandled_service_problems hosts dash">
    <h2>Unhandled service problems <?php if ($uh_services_count) print "($uh_services_count)"; ?></h2>
    <div class="dash_wrapper">
        <table class="dash_table">
            <?php
            if ($unhandled_services != "") {
               print "<tr class=\"dash_table_head\"><th>Host</th><th>Service</th><th>Output</th><th>Duration</th><th>Last check</th></tr>";
               print $unhandled_services;
               if ( $uh_services_count > $services_limit ) {
                  print "<tr class=\"warning\"><td>...</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>";
               }
            } else {
               print "<tr class=\"ok\"><td>No services in a problem state or unacknowledged</td></tr>";
            }
            ?>
        </table>
    </div>
</div>
</body>
</html>
