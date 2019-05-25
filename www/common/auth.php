<?php

require_once '../vendor/autoload.php';

// Resolve google-id-token to user-id and email
function userdata_from_google_id_token($google_id_token)
{
    $CLIENT_ID =
      "327472483544-phdvskj7pkfkgs4fo6bf01cpap8qpthv.apps.googleusercontent.com";

    $client = new Google_Client(['client_id' => $CLIENT_ID]);
    $payload = $client->verifyIdToken($google_id_token);
    assert_or_die($payload !== FALSE,
        HttpCode::FORBIDDEN, "gogle-id-token verification failed.");
    assert_or_die(array_key_exists('sub', $payload),
        HttpCode::INTERNAL_SERVER_ERROR, "payload.sub not set.");
    assert_or_die(array_key_exists('email', $payload),
        HttpCode::INTERNAL_SERVER_ERROR, "payload.email not set.");

    return array(
      'email' => $payload['email'],
      'google_user_id' => $payload['sub']
    );
}

function userdata_from_email_or_google_id_token($email, $google_id_token)
{
    if (is_null($google_id_token)) {
        assert_or_die(!is_null($email),
          HttpCode::BAD_REQUEST,
          "Both 'email' and 'google-id-token' are provided.");
        return array(
          'email' => $email, 'google_user_id' => "test-$email");
    } else {
        assert_or_die(is_null($email),
          HttpCode::BAD_REQUEST,
          "Neither 'email' nor 'google-id-token' are provided.");
        return userdata_from_google_id_token($google_id_token);
    }
}

function userdata_from_post()
{
    $email = nonempty_post_arg_or_null('email');
    $google_id_token = nonempty_post_arg_or_null('google-id-token');

    assert_or_die(!is_null($email) || !is_null($google_id_token),
        HttpCode::BAD_REQUEST, "Either 'email' or 'id_token' must be specified.");
    assert_or_die(is_null($email) || is_null($google_id_token),
        HttpCode::BAD_REQUEST, "Both 'email' and 'id_token' are specified.");

    return userdata_from_email_or_google_id_token($email, $google_id_token);
}

?>
