<?php
echo "Starting script...";
// Requires Peachy as dependency
// Config start
$year                   = "2018"; //POTY YEAR
$round                  = "2"; // POTY round
$accounteligibility     = "50"; // idenitifyer
$peachy                 = "/data/project/sbot/Peachy/Peachy/Init.php"; // dependency: location of PEACHY framework.
// Config end

echo "**** ".date('Y-m-d h:i:sa').": POTY Checker v.1. ****\n";
require( $peachy );
$site = Peachy::newWiki( "commons" );

function parser($url) {
        $con = curl_init();
        $to = 4;
        curl_setopt($con, CURLOPT_URL, $url);
        curl_setopt($con, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($con, CURLOPT_CONNECTTIMEOUT, $to);
        curl_setopt($con, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($con,CURLOPT_USERAGENT,'POTY VOTE USER CHECKER; POTY php tool; POTY web parser;');
        $data = curl_exec($con);
        curl_close($con);
        return $data;
}

$tools_pw = posix_getpwuid ( posix_getuid () );
$tools_mycnf = parse_ini_file( $tools_pw['dir'] . "/replica.my.cnf" );
$db = new mysqli( 'commonswiki.labsdb', $tools_mycnf['user'], $tools_mycnf['password'], 'commonswiki_p' );
if ( $db->connect_errno )
        die( "Failed to connect to labsdb: (" . $db->connect_errno . ") " . $db->connect_error );

$replag = $db->query( "SELECT lag FROM heartbeat_p.heartbeat WHERE shard = 's4';" )->fetch_object()->lag;
$replagmax = "50";

if ($replag > $replagmax ) {
  echo "Replication lag (bigger than 50 seconds) detected. Switching off script.\n";
  exit();
}
else {
  echo "Replication lag is OK (below 50 seconds), continuing...\n";
}

$row = $db->query( "SELECT DISTINCT rev_user_text AS user
FROM revision
INNER JOIN page
ON rev_page = page_id
WHERE page_title LIKE 'Picture_of_the_Year/".$year."/R".$round."/v/%'
;" ) or die("Error: Cannot fetch users from revion table.");

//$testrow = $db->query( "SELECT rc_user_text AS user
//FROM recentchanges
//LIMIT 10;" ) or die("Error: Cannot fetch users from revion table.");

unset($tools_mycnf, $tools_pw);

$yes = "Checker results for POTY ".$year." (Round ".$round."):\n\n";

while ($data = $row->fetch_row()) {

$rawresult = parser("https://tools.wmflabs.org/meta/accounteligibility/".$accounteligibility."/".urlencode($data[0])."?");
$resultyes = "/is eligible to vote in the/";
$resultno = "/is not eligible to vote in the/";

echo "Working on User:".$data[0]."...\n";

if(preg_match( $resultyes , $rawresult))
{
//  sleep(1); //don't ddos tool labs
//  $yes .= "# {{u|".$data[0]."}} {{done|Eligible}} (".date('Y-m-d h:i:sa').")\n";
  echo "Eligible";
}
else if (preg_match( $resultno , $rawresult))
{
  $yes .= "# {{u|".$data[0]."}} {{notdone|NOT Eligible}} (".date('Y-m-d h:i:sa').")\n";
  echo "NOT Eligible";
} else {
  $yes .= "# {{u|".$data[0]."}} Unable to fetch data. (".date('Y-m-d h:i:sa').")\n";
  echo "Error fetching data from labs!";
}
}
$site->set_runpage( null );
$title = "Commons:Picture_of_the_Year/".$year."/Voters";
$reason = "(POTY SCRIPT) Report for ".$year.".";
$site->initPage( $title )->edit( $yes, $reason );
?>
