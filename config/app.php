<?php
define('APP_NAME', 'BDC Voicemail Campaign Portal');

$_railwayDomain = getenv('RAILWAY_PUBLIC_DOMAIN');
$_appUrl        = getenv('APP_URL');
if ($_appUrl) {
    $_baseUrl = rtrim($_appUrl, '/');
} elseif ($_railwayDomain) {
    $_baseUrl = 'https://' . $_railwayDomain;
} else {
    $_baseUrl = 'http://localhost:8000';
}
define('BASE_URL', $_baseUrl);
unset($_railwayDomain, $_appUrl, $_baseUrl);
define('STORAGE_PATH', getenv('STORAGE_PATH') ?: __DIR__ . '/../storage/recordings');
define('TIMEZONE',        'America/New_York');
define('MAX_UPLOAD_MB',   50);

date_default_timezone_set(TIMEZONE);
