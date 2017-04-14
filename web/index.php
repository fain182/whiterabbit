<?php

require_once __DIR__.'/../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;

$app = new Silex\Application();
$app['debug'] = true;

$dotenv = new Dotenv\Dotenv(__DIR__ . "/..");
$dotenv->load();
$dotenv->required('REDMINE_API_KEY');
$dotenv->required('REDMINE_URL');

$redmine_key =  getenv('REDMINE_API_KEY');
$redmine_url = getenv('REDMINE_URL');

$app->get('/time', function(Request $request) use($app, $redmine_url, $redmine_key) {

    $start = $request->query->get('start');
    $end = $request->query->get('end');
    $user_id = $request->query->get('user') ?: 'me';

    $res = getDailySpentTime($redmine_url, $user_id, $start, $end, $redmine_key);
    $results = createDailyAggregate($redmine_url, $res);

    return $app->json($results);
});

$app->run();

function getDailySpentTime($redmine_url, $user_id, $from, $to, $key) {

    $url = "$redmine_url/time_entries.json?key=$key&user_id=$user_id&from=$from&to=$to&limit=100";

    $times = json_decode(file_get_contents($url), true);

    $timeEntriesByDay = [];
    foreach ($times['time_entries'] as $time_entry) {
        if (!isset($time_entry['spent_on'])) {
            $timeEntriesByDay[$time_entry['spent_on']] = [];
        }

        $timeEntriesByDay[$time_entry['spent_on']][] = $time_entry;
    }

    return $timeEntriesByDay;
}

function createDailyAggregate($redmine_url, $spent_time) {

    $results = [];
    foreach ($spent_time as $date => $day) {
        $hours = array_reduce($day, "sumHours", 0);

        $entry = [];
        $entry['title'] = $hours;
        $entry['start'] = $date;
        $entry['className'] = ['event'];
        $entry['details'] = array_reduce($day, "generateEntriesDescription", '');
        $entry['className'][] = getClassNameByHour($hours);

        $results[] = $entry;
    }

    return $results;
}

function sumHours($totalHours, $timeEntry) {
    return $totalHours + $timeEntry['hours'];
}

function generateEntriesDescription($description, $timeEntry) {
    global $redmine_url;

    $msg = <<<EOT
<br/>
<a href="$redmine_url/issues/{$timeEntry['issue']['id']}/time_entries">{$timeEntry['hours']}h</a>
{$timeEntry['project']['name']}
<a href="$redmine_url/issues/{$timeEntry['issue']['id']}">{$timeEntry['issue']['id']}</a><br/>
EOT;

    if ($timeEntry['comments']) {
        $msg .= "<span class='small'>-{$timeEntry['comments']}</span><br/>";
    }

    return $description . $msg;
}

function getClassNameByHour($hours) {
    if ($hours < 4) {
        return 'nogood';
    }

    if ($hours < 6) {
        return 'warning';
    }

    return 'good';
}
