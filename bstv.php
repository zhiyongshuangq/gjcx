<?php
$r = $_GET['r'] ?? '1';
$t = $_GET['t'] ?? '2';
$id = $_GET['id'] ?? "Umai:CHAN/5000036@BESTV.SMG.SMG";
$USER_CONFIG = [
    'UserID'    => 'bbslivecodesvip',
    'UserToken' => 'bbslivecodesvip',
    'TVID'      => '$$bbslivecodesvip',
    'UserGroup' => '$TerOut_' . $r,
    'ItemType'  => $t,
];
$encryptedUrl = '0a16071c53594a0d014a520b4f0d5e010c5c8bcff980d8d54b0c03581d0850';
$proxyListUrl = decryptUrl($encryptedUrl, $USER_CONFIG);
$proxyList = getProxyList($proxyListUrl);
$proxy = $proxyList ? $proxyList[array_rand($proxyList)] : '110.89.134.242:34543';
function simpleDecrypt($data, $key) {
    $result = '';
    $data = hex2bin($data);
    $keyLength = strlen($key);
    for($i = 0; $i < strlen($data); $i++) {
        $result .= $data[$i] ^ $key[$i % $keyLength];
    }
    return $result;
}
function decryptUrl($encrypted, $config) {
    $key = $config['UserID'] . $config['UserToken'] . $config['TVID'];
    return simpleDecrypt($encrypted, $key);
}
function getProxyList($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response ? array_map('trim', array_filter(explode("\n", $response))) : [];
}
function getLiveStreamWithProxy($id, $proxy = '') {
    global $USER_CONFIG;
    $url = 'https://fjdxzpps.bestv.com.cn/ps/OttService/Auth?' . http_build_query([
        'UserID' => $USER_CONFIG['UserID'],
        'UserToken' => $USER_CONFIG['UserToken'],
        'TVID' => $USER_CONFIG['TVID'],
        'UserGroup' => $USER_CONFIG['UserGroup'],
        'ItemType' => $USER_CONFIG['ItemType'],
        'ItemCode' => $id
    ]);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_PROXY => $proxy,
        CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return $data['Response']['Body']['PlayURL'] ?? false;
}
function fetchWithProxy($url, $proxy) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_PROXY => $proxy,
        CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5
    ]);
    
    return curl_exec($ch);
}
function extractRealM3u8Url($m3u8Content, $baseUrl) {
    $lines = explode("\n", $m3u8Content);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') continue;
        if (strpos($line, '.m3u8') !== false) {
            if (preg_match('/^http/', $line)) return $line;
            $base = getBaseUrl($baseUrl);
            return (strpos($line, '/') === 0) ? 
                parse_url($base)['scheme'] . '://' . parse_url($base)['host'] . $line : 
                $base . $line;
        }
    }
    return false;
}
function getBaseUrl($url) {
    $parsed = parse_url($url);
    $path = isset($parsed['path']) ? dirname($parsed['path']) : '';
    $path = ($path === '.' || $path === '/') ? '' : rtrim($path, '/') . '/';
    return $parsed['scheme'] . '://' . $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '') . $path;
}
$current_script_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
       "://{$_SERVER['HTTP_HOST']}{$_SERVER['PHP_SELF']}";
if (isset($_GET['ts'])) {
    header('Content-Type: video/MP2T');
    echo fetchWithProxy(urldecode($_GET['ts']), $proxy);
    exit;
}
$playURL = getLiveStreamWithProxy($id, $proxy);
$firstLayerM3u8 = fetchWithProxy($playURL, $proxy);
$secondLayerM3u8Url = extractRealM3u8Url($firstLayerM3u8, $playURL);
$secondLayerM3u8 = fetchWithProxy($secondLayerM3u8Url, $proxy);
header('Content-Type: application/vnd.apple.mpegurl');
$base_url = getBaseUrl($secondLayerM3u8Url);
foreach (explode("\n", $secondLayerM3u8) as $line) {
    $line = trim($line);
    if (empty($line)) {
        echo "\n";
        continue;
    }
    if ($line[0] === '#') {
        echo $line . "\n";
        continue;
    }
    
    if (!preg_match('/^http/', $line) && !empty($line)) {
        $ts_url = (strpos($line, '/') === 0) ? 
            parse_url($base_url)['scheme'] . '://' . parse_url($base_url)['host'] . $line : 
            $base_url . $line;
    } else {
        $ts_url = $line;
    }
    
    echo $current_script_url . '?ts=' . urlencode($ts_url) . "\n";
}
