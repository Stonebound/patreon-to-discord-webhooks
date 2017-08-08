<?php

$discord_webhook = "YOURLINKHERE https://discordapp.com/api/webhooks/....";

// post to discord snippet from https://www.reddit.com/r/discordapp/comments/58hry5/simple_php_function_for_posting_to_webhook/
function postToDiscord($message)
{
    $data = array("content" => $message, "username" => "Patreon Bot");
    $curl = curl_init($discord_webhook);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    return curl_exec($curl);
}


// this saves the post data you get on your endpoint
$data = @file_get_contents('php://input');
// decode json post data to arrays
$event_data = json_decode($data, true);

// also get the headers patreon sends
$event = $_SERVER['HTTP_X_PATREON_EVENT']; // pledges:create pledges:delete pledges:update
$sig = $_SERVER['HTTP_X_PATREON_SIGNATURE']; // you should probably check those, oh well

// get all the user info
$pledge_amount = $event_data['data']['attributes']['amount_cents'];
$patron_id = $event_data['data']['relationships']['patron']['data']['id'];

foreach ($event_data['included'] as $included_data) {
    if ($included_data['type'] == 'user' && $included_data['id'] == $patron_id) {
        $user_data = $included_data;
    }
}

$patron_url = $user_data['attributes']['url'];
$patron_fullname = $user_data['attributes']['full_name'];


// send event to discord
if ($event == "pledges:create") {
    postToDiscord(":star: " . $patron_fullname . " just pledged for $" . number_format(($pledge_amount /100), 2, '.', ' ') . "! <" . $patron_url . ">");
} else if ($event == "pledges:delete") {
    postToDiscord(":disappointed: " . $patron_fullname . " just removed their pledge! <" . $patron_url . ">");
} else if ($event == "pledges:update") {
    postToDiscord(":open_mouth: " . $patron_fullname . " just updated their pledge to $" . number_format(($pledge_amount /100), 2, '.', ' ') . "! <" . $patron_url . ">");
} else {
    postToDiscord($event . ": something happened with patreon that phit forgot to implement");
}

?>
