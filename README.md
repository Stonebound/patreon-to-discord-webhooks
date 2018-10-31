# patreon-to-discord-webhooks

Patreon can send you events when someone adds, updates or deletes their pledge to you, I couldn't find anything that can process those. So here's a quick PHP script.

## Setup

1. download script
2. put it somewhere accessible from the web
3. create a Patreon webhook [here](https://www.patreon.com/portal/registration/register-webhooks) with the 3 triggers listed below and point them at the script location
    * `pledge:create`
    * `pledge:update`
    * `pledge:delete`
3. edit script and add your Discord webbhook URL and Patreon secret at the top
