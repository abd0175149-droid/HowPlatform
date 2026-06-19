<?php
use CodeIgniter\HTTP\IncomingRequest;

/** توليد بصمة جهاز ثابتة نسبيًا (IP + User-Agent + Accept + سر داخلي) */
function make_device_fingerprint(IncomingRequest $request): string
{
    $secret = getenv('security.deviceSecret') ?: 'device-secret-change-me';
    $raw = implode('|', [
        $request->getIPAddress() ?? '0.0.0.0',
        $request->getUserAgent() ? $request->getUserAgent()->getAgentString() : 'UA',
        $request->getHeaderLine('Accept') ?: '*/*'
    ]);
    return hash('sha256', $raw . '|' . $secret);
}
