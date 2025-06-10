<?php

declare(strict_types = 1);

// insert your patreon webhook secret here!
const SECRET_WEBHOOK_ID = "secret";

// insert your discord webhook url here
const DISCORD_WEBHOOK = "URL";

function postToDiscord(string $message): void
{
    $data = ['content' => $message, 'username' => 'Patreon Bot'];
    $headers = ['Content-Type: application/json'];
    $curl = curl_init(DISCORD_WEBHOOK);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data, JSON_THROW_ON_ERROR));
    curl_exec($curl);
    curl_close($curl);
}

function formatUsd(float $cents): string
{
    return '$' . number_format($cents / 100, 2, '.', ' ');
}

// get get post data and decode json
$data = (string) @file_get_contents('php://input');
$eventData = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

// also get the headers patreon sends
$patreonEventHeader = ($_SERVER['HTTP_X_PATREON_EVENT'] ?? '');
$patreonSignatureHeader = ($_SERVER['HTTP_X_PATREON_SIGNATURE'] ?? '');
if (empty($data) || empty($patreonEventHeader) || empty($patreonSignatureHeader)) {
    error_log('Received empty data or headers from Patreon');
    header('HTTP/1.1 400 Bad Request');
    exit;
}

// verify signature
$signature = hash_hmac('md5', $data, SECRET_WEBHOOK_ID);
if (!hash_equals($patreonSignatureHeader, $signature)) {
    error_log(sprintf('Patreon signature didn\'t match, got: "%s" expected: "%s"', $patreonSignatureHeader, $signature));
    header('HTTP/1.1 403 Forbidden');
    exit;
}

// get all the user info
$pledgeAmount = (float) ($eventData['data']['attributes']['amount_cents'] ?? 0);
$patronId = $eventData['data']['relationships']['patron']['data']['id'];
$campaignId = $eventData['data']['relationships']['campaign']['data']['id'];

$userData = null;
$campaignData = null;
foreach ($eventData['included'] as $includedData) {
    if ($includedData['type'] === 'user' && $includedData['id'] === $patronId) {
        $userData = $includedData;
    }
    if ($includedData['type'] === 'campaign' && $includedData['id'] === $campaignId) {
        $campaignData = $includedData;
    }
}

$patronUrl = $userData['attributes']['url'] ?? '';
$patronName = $userData['attributes']['full_name'] ?? '';

$campaignSum = (float) ($campaignData['attributes']['pledge_sum'] ?? 0);
$patronCount = $campaignData['attributes']['patron_count'] ?? '';

// send event to discord
if ($patreonEventHeader === "pledges:create") {
    $text = sprintf(
        ":star: %s just pledged for $%s! <%s> - New total: %s (%s members)",
        $patronName,
        formatUsd($pledgeAmount),
        $patronUrl,
        formatUsd($campaignSum),
        $patronCount
    );
    postToDiscord($text);
} elseif ($patreonEventHeader === "pledges:delete") {
    $text = sprintf(
        ":disappointed: %s just removed their pledge! <%s> - New total: %s (%s members)",
        $patronName,
        $patronUrl,
        formatUsd($campaignSum),
        $patronCount
    );
} elseif ($patreonEventHeader === "pledges:update") {
    $text = sprintf(
        ":open_mouth: %s just updated their pledge to $%s! <%s> - New total: %s (%s members)",
        $patronName,
        formatUsd($pledgeAmount),
        $patronUrl,
        formatUsd($campaignSum),
        $patronCount
    );
} else {
    postToDiscord(sprintf("%s: something happened with Patreon ¯\_(ツ)_/¯", $patreonEventHeader));
}
