<?php

require_once __DIR__.'/../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;

$app = new Silex\Application();
$app['debug'] = true;

$app->get('/time', function(Request $request) use($app) {

    $dotenv = new Dotenv\Dotenv(__DIR__ . "/..");
    $dotenv->load();
    $dotenv->required('REDMINE_API_KEY');
    $dotenv->required('REDMINE_URL');

    $key =  getenv('REDMINE_API_KEY');
    $redmine_url = getenv('REDMINE_URL');

    $start = $request->query->get('start');
    $end = $request->query->get('end');
    $user_id = $request->query->get('user') ?: 'me';

    $res = getDailySpentTime($redmine_url, $user_id, $start, $end, $key);
    $results = createDailyAggregate($redmine_url, $res, $app['url_generator']);

    return $app->json($results);
});

$app->run();

function getDailySpentTime($url, $user, $from, $to, $key) {

    $url = "$url/time_entries.json?key=$key&user_id=$user&from=$from&to=$to&limit=100";

    $times = json_decode(file_get_contents($url), true);

    $res = [];
    foreach ($times['time_entries'] as $time_entry) {
        if (!isset($time_entry['spent_on'])) {
            $res[$time_entry['spent_on']] = [];
        }

        $res[$time_entry['spent_on']][] = $time_entry;
    }

    return $res;
}

function createDailyAggregate($url, $spent_time, $url_generator) {

    $results = [];
    foreach ($spent_time as $date => $day) {
        $val = array_reduce($day, function($acc, $item) use ($url){
            $acc['hours'] += $item['hours'];


            $msg = <<<EOT
<br/>
<a href="$url/issues/{$item['issue']['id']}/time_entries">{$item['hours']}h</a>
{$item['project']['name']}
<a href="$url/issues/{$item['issue']['id']}">{$item['issue']['id']}</a><br/>
EOT;

            if ($item['comments']) {
               $msg .= "<span class='small'>-{$item['comments']}</span><br/>";
            }

            $acc['details'] .= $msg;
            return $acc;
        }, ['hours' => 0, 'details' => '']);

        $entry = [];
        $entry['title'] = $val['hours'];
        $entry['start'] = $date;
        $entry['className'] = ['event'];
        $entry['details'] = $val['details'];

        if ($val['hours'] >= 6 ) {
            $entry['className'][] = 'good';
        }

        if ($val['hours'] < 6 && $val['hours'] >= 4) {
            $entry['className'][] = 'warning';
        }

        if ($val['hours'] < 4) {
            $entry['className'][] = 'nogood';
        }

        $results[] = $entry;
    }

    return $results;
}