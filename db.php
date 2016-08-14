<?php

// initialize the db if it does not exist
const DB_CONNECTION_ERROR = [ 'error' => 'could not connect to db' ];
const DB_CREATION_ERROR = [ 'error' => 'could not create tables in db' ];
const APP_REQUIRES_JSON = [ 'error' => 'request body was not valid json' ];
const STATEMENT_PREP_ERROR = [ 'error' => 'could not prepare statement' ];
const FIGHT_FETCH_ERROR = [ 'error' => 'fight does not exist' ];
const FIGHT_DNE_ERROR = [ 'error' => 'fight does not exist' ];
const FIGHT_AUTH_ERROR = [ 'error' => 'fight is not chill bro' ];
const FIGHT_SELECT_ERROR = [ 'error' => 'failed to select fights on a waifu' ];
const WAIFU_SELECT_ERROR = [ 'error' => 'failed to select a waifu' ];
const WAIFU_CREATE_ERROR = [ 'error' => 'could not create wife' ];
const FIGHT_CREATE_ERROR = [ 'error' => 'failed to insert a new fight' ];

function err($app, $error) {
    return $app->json([ 'error' => $error[1] ], $error[0]);
}

const CREATE_TABLE_QUERY = <<<EOD
CREATE TABLE IF NOT EXISTS waifus(
    id INTEGER,
    name STRING NOT NULL,
    description STRING NOT NULL,
    image_url STRING NOT NULL,
    mmr FLOAT NOT NULL,
    secret_hash STRING NOT NULL,
    dt DATETIME DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY(id ASC)
);
CREATE TABLE IF NOT EXISTS fights(
    id INTEGER,
    waifu_1 INT NOT NULL,
    waifu_2 INT NOT NULL,
    winner INT,
    secret_hash STRING NOT NULL,
    dt DATETIME DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY(id ASC),
    FOREIGN KEY(waifu_1) REFERENCES waifus(id),
    FOREIGN KEY(waifu_2) REFERENCES waifus(id)
    FOREIGN KEY(winner) REFERENCES waifus(id)
);
EOD;


// fetch from database
const NO_ERROR = 0;
const PREP_ERROR = 1;
const EXEC_ERROR = 2;
// prepares and executes a statement, escaping inputs
// returns an error code which the caller can use to return
// a context-dependent error to the client
function prep_exec_query(&$out, $db, $stmt, $fields) {
    $st = $db->prepare($stmt);
    if (!$st) { return PREP_ERROR; }

    foreach($fields as $key => $value) {
        $type_key = explode(' ', $key);
        switch ($type_key[0]) {
            case 'string':
            case 'str':
                $type = SQLITE3_TEXT;
                break;
            case 'float':
                $type = SQLITE3_FLOAT;
                break;
            case 'int':
            default:
                $type = SQLITE3_INTEGER;
                break;
        }
        $st->bindValue($type_key[1], $value, $type);
    }

    $rc = $st->execute();
    if (!$rc) { return EXEC_ERROR; }

    $out = $rc;
    return NO_ERROR;
}

function aggregate_assoc($result) {
    $aggregate = [];
    while ($w = $result->fetchArray(SQLITE3_ASSOC)) {
        $aggregate[] = $w;
    }

    return $aggregate;

}




const SELECT_WAIFU_QUERY = <<<EOD
SELECT * FROM waifus
WHERE id > :id
ORDER BY id
LIMIT :limit
EOD;

function fetch_waifus(&$error, $db, $limit, $start_id) {
    $rc = prep_exec_query($result, $db, SELECT_WAIFU_QUERY, [
        'int :limit'    =>  $limit,
        'int :id'       =>  $start_id
    ]);

    switch ($rc) {
    case PREP_ERROR: return $error = [500, 'failed to prepare statement'];
    case EXEC_ERROR: return $error = [500, 'failed to insert wife'];
    case NO_ERROR:
    default:
    }

    return aggregate_assoc($result);
}



const CREATE_WAIFU_QUERY = <<<EOD
INSERT INTO waifus (
    name, description, image_url, mmr, secret_hash
) VALUES (
    :name, :description, :image_url, :mmr, :secret_hash
);
EOD;

function insert_waifu(&$error, $db, $name, $description, $image_url, $secret_hash, $mmr) {
    $rc = prep_exec_query($result, $db, CREATE_WAIFU_QUERY, [
        'str :name'             =>  $name,
        'str :description'      =>  $description,
        'str :image_url'        =>  $image_url,
        'str :secret_hash'      =>  $secret_hash,
        'float :mmr'            =>  $mmr,
    ]);

    switch ($rc) {
    case PREP_ERROR: return $error = [500, 'failed to prepare statement'];
    case EXEC_ERROR: return $error = [500, 'failed to insert waifu'];
    case NO_ERROR:
    default:
    }

    return $db->lastInsertRowid();
}


const GET_RANDOM_WAIFU_IDS_QUERY = <<<EOD
SELECT id FROM waifus
ORDER BY RANDOM()
LIMIT 2
EOD;

function fetch_random_waifu_ids(&$error, $db) {
    $rc = $db->query(GET_RANDOM_WAIFU_IDS_QUERY);
    if (!$rc) { return $error = [500, 'failed to query']; }

    $w1 = $rc->fetchArray(SQLITE3_ASSOC)['id'];
    $w2 = $rc->fetchArray(SQLITE3_ASSOC)['id'];

    if ($w1 === false || $w2 === false) {
        return $error = [500, 'Not enough wives in the database to make a query'];
    }

    return [$w1, $w2];
}

const CREATE_NEW_FIGHT_QUERY = <<<EOD
INSERT INTO fights (waifu_1, waifu_2, secret_hash)
VALUES (:w1, :w2, :secret_hash);
EOD;

function insert_fight(&$error, $db, $w1, $w2, $secret_hash) {
    $rc = prep_exec_query($result, $db, CREATE_NEW_FIGHT_QUERY, [
        'int :w1' => $w1,
        'int :w2' => $w2,
        'str :secret_hash' => $secret_hash
    ]);

    switch ($rc) {
    case PREP_ERROR: return $error = [500, 'failed to prepare statement'];
    case EXEC_ERROR: return $error = [500, 'failed to create fight'];
    case NO_ERROR:
    default:
    }

    return $db->lastInsertRowid();
}

const FETCH_FIGHT_BY_WAIFU_QUERY = <<<EOD
SELECT * FROM fights
WHERE (waifu_1 = :waifu_id OR waifu_2 = :waifu_id) 
    AND id > :start_id
LIMIT :limit
EOD;
function fetch_fights_by_waifu(&$error, $db, $waifu_id, $limit, $start_fight_id, $type) {
    // TODO implement type filtering
    $rc = prep_exec_query($result, $db, FETCH_FIGHT_BY_WAIFU_QUERY, [
        'int :waifu_id' => $waifu_id,
        'int :limit' => $limit,
        'int :start_id' => $start_fight_id
    ]);

    switch ($rc) {
    case PREP_ERROR: return $error = [500, 'failed to prepare statement'];
    case EXEC_ERROR: return $error = [500, 'failed to select fights by waifu'];
    case NO_ERROR:
    default:
    }

    return aggregate_assoc($result);
}




const FETCH_FIGHT_QUERY = <<<EOD
SELECT * FROM fights
WHERE id = :id
LIMIT 1;
EOD;

function fetch_fight(&$error, $db, $id) {
    $rc = prep_exec_query($result, $db, FETCH_FIGHT_QUERY, [
        'int :id' => $id
    ]);

    switch ($rc) {
    case PREP_ERROR: return $error = [500, 'failed to prepare statement'];
    case EXEC_ERROR: return $error = [500, 'failed to fetch waifu'];
    case NO_ERROR:
    default:
    }

    $fight = $result->fetchArray(SQLITE3_ASSOC);
    if (!$fight) { return $error = [500, "fight $id does not exist"]; }
    return $fight;
}

// update the fight's winner
const UPDATE_FIGHT_WINNER_QUERY = <<<EOD
UPDATE fights
SET winner = :winner
WHERE id = :id
EOD;

function update_fight_winner($error, $db, $fight_id, $winner_id) {
    $out = null;
    $rc = prep_exec_query($out, $db, UPDATE_FIGHT_WINNER_QUERY, [
        'int :id' => $fight_id,
        'int :winner' => $winner_id
    ]);

    switch ($rc) {
    case PREP_ERROR: return $error = [500, 'Failed to prepare statement'];
    case EXEC_ERROR: return $error = [500, 'Failed to update fight winner'];
    case NO_ERROR:
    default:
    }

    return false;
}

// update the waifu's mmr
const UPDATE_WAIFU_MMR_QUERY = <<<EOD
UPDATE waifus 
SET mmr = :mmr
WHERE id = :id
EOD;

function update_waifu_mmr($error, $db, $id, $mmr) {
    $out = null;
    $rc = prep_exec_query($out, $db, UPDATE_WAIFU_MMR_QUERY, [
        'int :id' => $id,
        'float :mmr' => $mmr
    ]);

    switch ($rc) {
    case PREP_ERROR: return $error = [500, 'Failed to prepare statement'];
    case EXEC_ERROR: return $error = [500, 'Failed to update waifu mmr'];
    case NO_ERROR:
    default:
    }

    return false;
}

// fetch the waifu's mmr
const FETCH_WAIFU_MMR_QUERY = <<<EOD
SELECT mmr from waifus
WHERE id = :id
LIMIT 1
EOD;

function fetch_waifu_mmr(&$error, $db, $id) {
    $result = null;
    $rc = prep_exec_query($result, $db, FETCH_WAIFU_MMR_QUERY, [
        'int :id' => $id
    ]);

    switch($rc) {
    case PREP_ERROR: return $error = [500, 'Failed to prepare statement'];
    case EXEC_ERROR: return $error = [500, 'Failed to fetch waifu from db'];
    case NO_ERROR:
    default:
    }

    $waifu = $result->fetchArray(SQLITE3_ASSOC);
    if (!$waifu) { return $error = [500, "wife $id does not exist"]; }
    return $waifu['mmr'];
}

