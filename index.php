<?php
// insert your patreon webhook secret here!
$secret_webhook_id = "secret";

// insert your discord webhook url here
$discord_webhook = "URL";

// post to discord snippet from https://www.reddit.com/r/discordapp/comments/58hry5/simple_php_function_for_posting_to_webhook/
function postToDiscord($message, $discord_webhook) {
    $data = array("content" => $message, "username" => "Patreon Bot");
    $curl = curl_init($discord_webhook);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    return curl_exec($curl);
}

// compat for older php versions
if(!function_exists('hash_equals')) {
    function hash_equals($str1, $str2) {
        if(strlen($str1) != strlen($str2)) {
            return false;
        } else {
            $res = $str1 ^ $str2;
            $ret = 0;
            for($i = strlen($res) - 1; $i >= 0; $i--) $ret |= ord($res[$i]);
            return !$ret;
        }
    }
}

// this saves the post data you get on your endpoint
$data = @file_get_contents('php://input');
// decode json post data to arrays
$event_data = json_decode($data, true);

// also get the headers patreon sends
$X_Patreon_Event     = $_SERVER['HTTP_X_PATREON_EVENT'];
$X_Patreon_Signature = $_SERVER['HTTP_X_PATREON_SIGNATURE'];

// verify signature
$signature = hash_hmac('md5', $data, $secret_webhook_id);
if (!hash_equals($X_Patreon_Signature, $signature)) {
    die("Patreon Signature didn't match, got: " . $X_Patreon_Signature . " expected: " . $signature);
}

// get all the user info
$pledge_amount = $event_data['data']['attributes']['amount_cents'];
$patron_id     = $event_data['data']['relationships']['patron']['data']['id'];
$campaign_id   = $event_data['data']['relationships']['campaign']['data']['id'];

foreach ($event_data['included'] as $included_data) {
    if ($included_data['type'] == 'user' && $included_data['id'] == $patron_id) {
        $user_data = $included_data;
    }
    if ($included_data['type'] == 'campaign' && $included_data['id'] == $campaign_id) {
        $campaign_data = $included_data;
    }
}

$patron_url = $user_data['attributes']['url'];
$patron_fullname = $user_data['attributes']['full_name'];

$campaign_sum    = $campaign_data['attributes']['pledge_sum'];
$patron_count    = $campaign_data['attributes']['patron_count'];

// send event to discord
if ($X_Patreon_Event == "pledges:create") {
    postToDiscord(":star: " . $patron_fullname . " just pledged for $" . number_format(($pledge_amount /100), 2, '.', ' ') . "! <" . $patron_url . "> - New total: $" . number_format(($campaign_sum / 100), 2, '.', ' ') . " by " . $patron_count . " patreons", $discord_webhook);
} else if ($X_Patreon_Event == "pledges:delete") {
    postToDiscord(":disappointed: " . $patron_fullname . " just removed their pledge! <" . $patron_url . "> - New total: $" . number_format(($campaign_sum / 100), 2, '.', ' ') . " by " . $patron_count . " patreons", $discord_webhook);
} else if ($X_Patreon_Event == "pledges:update") {
    postToDiscord(":open_mouth: " . $patron_fullname . " just updated their pledge to $" . number_format(($pledge_amount /100), 2, '.', ' ') . "! <" . $patron_url . "> - New total: $" . number_format(($campaign_sum / 100), 2, '.', ' ') . " by " . $patron_count . " patreons", $discord_webhook);
} else {
    postToDiscord($X_Patreon_Event . ": something happened with Patreon ¯\_(ツ)_/¯", $discord_webhook);
}

?>
