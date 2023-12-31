<?php

require_once('../private/config.php');

function authenticate($username, $password) {
    global $config;

    $success = false;

    if ($config['username'] == $username && $config['password'] == $password) {
        $success = true;
    }

    return $success;
}


function _request($method, $endpoint, $headers=[], $payload=null) {
    global $config;

    $linode_api_token = $config['linode_api_token'];

    $api_root = 'https://api.linode.com/v4';
    $url = $api_root . $endpoint;
    $headers[] = 'Authorization: Bearer ' . $linode_api_token;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($payload != null) {
        $data = json_encode($payload);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }

    $response = curl_exec($ch);
    curl_close($ch);

    $obj = json_decode($response);

    return $obj->data;
}


function get_domain_id($domain_name) {
    $method = 'GET';
    $endpoint = '/domains';

    $domain_id = null;
    $domain_list = _request($method, $endpoint);

    foreach($domain_list as $domain) {
        if ($domain->domain == $domain_name) {
            $domain_id = $domain->id;
            break;
        }
    }

    return $domain_id;
}


function get_record($domain_id, $name, $type) {
    $method = 'GET';
    $endpoint = '/domains/' . $domain_id . '/records';

    $target_record = null;
    $record_list = _request($method, $endpoint);

    foreach($record_list as $record) {
        if ($record->name == $name && $record->type == $type) {
            $target_record = $record;
            break;
        }
    }

    return $target_record;
}

function validate_ip_address($ip_address) {
    return filter_var($ip_address, FILTER_VALIDATE_IP);
}


// TODO: More failure checks
function update_dns($domain_name, $ip_address) {
    if (validate_ip_address($ip_address) == false) {
        echo "fail";

        return false;
    }

    $domain_id = get_domain_id($domain_name);
    $record = get_record($domain_id, '', 'A');

    if ($record->target == $ip_address) {
        echo "nochg";

        return true;
    }

    $method = 'PUT';
    $headers = ['Content-Type: application/json'];
    $endpoint = '/domains/' . $domain_id . '/records/' . $record->id;
    $payload = [
        'name' => $domain_name,
        'target' => $ip_address
    ];

    _request($method, $endpoint, $headers, $payload);

    echo "good";

    return true;
}


function main() {
    global $config;

    $username = $_SERVER['PHP_AUTH_USER'];
    $password = $_SERVER['PHP_AUTH_PW'];

    if (authenticate($username, $password) != true) {
        http_response_code(401);
        echo '<h1>401 - Unauthorized</h1>';

        exit;
    }

    $domain_name = $config['domain_name'];
    $query_string = $_SERVER['QUERY_STRING'];

    $query = [];
    parse_str($query_string, $query);
    $ip_address = $query['myip'];

    update_dns($domain_name, $ip_address);
}


main();

