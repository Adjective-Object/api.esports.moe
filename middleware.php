<?php
function bind_middleware($app) {

    // force all results to be json
    $app->after(function($request, $response) {
        $response->headers->set('Content-Type', 'application/json');
    });

    // close the db connection
    $app->finish(function($request, $response) use ($app) {
        $app['db']->close();
    });

    // ensure the db always exists
    $app->before(function($request, $app) use ($app) {
        $app['db'] = new Sqlite3('waifu_fight.sqlite');
        if (!$app['db']) {
            return err($app, [500, DB_CONNECTION_ERROR]);
        }

        $rc = $app['db']->exec(CREATE_TABLE_QUERY);
        if (!$rc) {
            return err($app, [500, CREATE_TABLE_ERROR]);
        }
    });

    //jsonify the request
    $app->before(function($request, $app) {
        if ($request->getMethod() !== 'GET') {
            if (0 !== strpos($request->headers->get('Content-Type'), 'application/json')) {
                return err($app, [400, APP_REQUIRES_JSON]);
            }

            $data = json_decode($request->getContent(), true);
            if (!$data){
                return err($app, [400, APP_REQUIRES_JSON]);
            }

            $request->request->replace(is_array($data) ? $data : array());
        }
    });

}
