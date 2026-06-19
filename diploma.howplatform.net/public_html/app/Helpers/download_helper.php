<?php
// app/Helpers/download_helper.php
declare(strict_types=1);

function download_url(string $relativePath): string
{
    $relativePath = ltrim($relativePath, "/\\");
    $b64 = rtrim(strtr(base64_encode($relativePath), '+/', '-_'), '=');
    return site_url(route_to('download')) . '?p=' . $b64;
}
