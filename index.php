<?php
require_once __DIR__.'/vendor/autoload.php'; 
require_once __DIR__.'/middleware.php'; 
require_once __DIR__.'/db.php'; 

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// init app and bind middleware
$app = new Silex\Application(); 
$app['debug'] = true;
bind_middleware($app);


$app->put('/waifus', function(Request $request) use($app) {
    // TODO hash secret
    // TODO mmr
    // TODO check image url
    $mmr = 1000.0;
    $secret_hash = 'todo';

    $wife_id = insert_waifu($error, $app['db'],
        $request->request->get('name'),
        $request->request->get('description'),
        $request->request->get('image_url'),
        $secret_hash,
        $mmr);

    if($error) { return err($app, $error); }

    return $app->json([
        'id' => $wife_id,
        'creator_secret' => $secret_hash
    ]);
});

$app->get('/waifus', function(Request $request) use($app) {
    // sanatize params
    $requestedLimit = $request->query->get('limit') ?: 50;
    $limit = min(50, intval($requestedLimit));
    $start_id = intval($request->query->get('start_id'));

    // perform query
    $waifus = fetch_waifus($error, $app['db'], $limit, $start_id);
    if ($error) { return err($app, $error); }

    // convert list of waifus to keyed array
    $output = [];
    foreach ($waifus as $w) {
        $id = $w['id'];
        $output[$id] = [
            'description' => $w['description'],
            'image_url' => $w['image_url'],
            'name'=> $w['name']
        ];
    }

    return $app->json($output);
}); 


$app->get('/waifus/{id}', function(Request $request, $id) use($app) {
    // sanatize input
    $requestLimit = $request->query->get('limit') ?: 50;
    $limit= min(50, intval($requestLimit));
    $start_id = intval($request->query->get('start_id'));

    $type_filter = $request->query->get('type') ?: 'all';
    if (!in_array($type_filter, ['all', 'win', 'loss'])) {
        return err($app, ['invalid filter, must be one of \'all\', \'win\', or \'loss\'']);
    }

    $fights = fetch_fights_by_waifu($error, $app['db'], $id, $limit, $start_id, $type_filter);
    if ($error) { return err($app, $error); }

    $output_fights = [];
    foreach($fights as &$fight) {
        $output_fights[$fight['id']] = [
            'waifus' => [
                $fight['waifu_1'],
                $fight['waifu_2']
            ],
            'winner' => $fight['winner']
        ];
    }

    return $app->json( [
        'fights' => $output_fights
    ]);
}); 


$app->post('/fights', function(Request $request) use($app) {

    // sanitize parameters
    $requestLimit = $request->query->get('limit') ?: 50;
    $limit= min(50, intval($requestLimit));
    $start_id = intval($request->query->get('start_id'));

    // generate a secret key to resolve this fight
    // TODO hash with the requesting user's user ID to
    // ensure the user making the request is cool
    $secret = rand();
    $secret_hash = md5($secret);
    $error = null;

    // get 2 random wives
    $wives = fetch_random_waifu_ids($error, $app['db']);
    if ($error) { return err($app, $error); }

    // insert fight between wives
    $fight_id = insert_fight($error, $app['db'], $wives[0], $wives[1], md5($secret));
    if ($error) { return err($app, $error); }

    return json_encode([
        'fight_id' => $fight_id,
        'secret' => $secret,
        'wives' => $wives
    ]);
}); 

// resolve the waifu
$app->post('/fights/{id}', function(Request $request, $id) use($app) { 
    // sanitize parameters
    $secret = $request->request->get('secret');
    $winner = $request->request->get('winner');

    // get the queried fight
    $fight = fetch_fight($error, $app['db'], $id);
    if ($error) { return err($app, $error); }

    // check that the hash of the provided user secret matches
    // expected one in the database
    // TODO cross ref with jwt token (see above)
    $secret_hash = md5($secret);
    if ($fight['secret_hash'] !== $secret_hash) {
        return err($app, [503, "secret hash mismatch $secret_hash vs {$fight['secret_hash']}"]);
    }

    // check that the fight is not already resolved
    if ($fight['winner'] !== null) {
        return err($app, [503, "fight has already been resolved (victor {$fight['winner']})"]);
    }

    // calculate mmr change
    $waifu_1 = $fight['waifu_1'];
    $waifu_2 = $fight['waifu_2'];

    if ($winner != $waifu_1 && $winner != $waifu_2) {
        return err($app, [400, "waifu $winner is not part of fight $id ($waifu_1 vs $waifu_2)"]);
    }

    $waifu_1_mmr = fetch_waifu_mmr($error, $app['db'], $waifu_1);
    if ($error) { return err($app, $error); }
    $waifu_2_mmr = fetch_waifu_mmr($error, $app['db'], $waifu_2);
    if ($error) { return err($app, $error); }

    if ($winner === $waifu_1) {
        $waifu_1_mmr += 10;
        $waifu_2_mmr -= 10;
    } else {
        $waifu_1_mmr += 10;
        $waifu_2_mmr -= 10;
    }

    // update winner of the fight
    update_fight_winner($error, $app['db'], $fight['id'], $winner);
    if ($error) { return err($app, $error); }

    // update mmr of either waifu
    update_waifu_mmr($error, $app['db'], $waifu_1, $waifu_1_mmr);
    if ($error) { return err($app, $error); }
    update_waifu_mmr($error, $app['db'], $waifu_2, $waifu_2_mmr);
    if ($error) { return err($app, $error); }


    return $app->json([
        'waifu_1_mmr' => $waifu_1_mmr,
        'waifu_2_mmr' => $waifu_2_mmr,
    ]);
}); 



$app->run(); 

