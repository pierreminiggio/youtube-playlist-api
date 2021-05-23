<?php

use App\Entity\YoutubeVideo;
use App\Repository\YoutubeVideoRepository;
use PierreMiniggio\ConfigProvider\ConfigProvider;
use PierreMiniggio\DatabaseConnection\DatabaseConnection;
use PierreMiniggio\DatabaseFetcher\DatabaseFetcher;
use PierreMiniggio\GoogleTokenRefresher\AccessTokenProvider;

require __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$playlistIds = ['PLMzEhcF7dipBCm4BuWvS5sVvO7eg0GwS8'];

$configProvider = new ConfigProvider(__DIR__ . DIRECTORY_SEPARATOR);
$config = $configProvider->get();
$apiConfig = $config['api'];

$provider = new AccessTokenProvider();
$accessToken = $provider->get($apiConfig['client_id'], $apiConfig['client_secret'], $apiConfig['refresh_token']);

$dbConfig = $config['db'];
$repository = new YoutubeVideoRepository(new DatabaseFetcher(new DatabaseConnection(
    $dbConfig['host'],
    $dbConfig['database'],
    $dbConfig['username'],
    $dbConfig['password'],
    DatabaseConnection::UTF8_MB4
)));

$authorization = "Authorization: Bearer " . $accessToken;
$headers = ['Content-Type: application/json' , $authorization];

foreach ($playlistIds as $playlistId) {
    echo PHP_EOL . PHP_EOL . 'Playlist : ' . $playlistId;

    $playlistVideosCurl = curl_init();
    curl_setopt_array($playlistVideosCurl, [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => 'https://www.googleapis.com/youtube/v3/playlistItems?playlistId=' . $playlistId . '&part=snippet&order=date&maxResults=3',
        CURLOPT_HTTPHEADER => $headers
    ]);

    $playlistVideosCurlResult = curl_exec($playlistVideosCurl);
    $playlistVideosJsonResponse = json_decode($playlistVideosCurlResult, true);

    if (! empty($playlistVideosJsonResponse['error'])) {
        echo 'Error ' . $playlistVideosJsonResponse['error']['code'] . ': ' . $playlistVideosJsonResponse['error']['message'];
        die;
    }

    $videoIds = array_values(array_reverse(array_filter(array_map(
        fn ($playlistVideoJsonResponse) => $playlistVideoJsonResponse['snippet']['resourceId']['videoId'] ?? null,
        $playlistVideosJsonResponse['items']
    ), fn ($playlistVideoId) => $playlistVideoId !== null)));
    
    foreach ($videoIds as $videoId) {
        echo PHP_EOL . 'Inserting/updating ' . $videoId . ' from playlist ' . $playlistId . ' ...';

        $videoCurl = curl_init();
        curl_setopt_array($videoCurl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => 'https://www.googleapis.com/youtube/v3/videos?id=' . $videoId . '&part=snippet',
            CURLOPT_HTTPHEADER => $headers
        ]);

        $videoCurlResult = curl_exec($videoCurl);

        $videoJsonResponse = json_decode($videoCurlResult, true);
        curl_close($videoCurl);

        if (! empty($videoJsonResponse['error'])) {
            echo 'Error ' . $videoJsonResponse['error']['code'] . ': ' . $videoJsonResponse['error']['message'];
            die;
        }

        $snippet = $videoJsonResponse['items'][0]['snippet'];
        $youtubeVideo = new YoutubeVideo(
            $playlistId,
            $snippet['channelId'],
            $videoId,
            'https://www.youtube.com/watch?v=' . $videoId,
            $snippet['thumbnails']['high']['url'],
            $snippet['title'],
            $snippet['description'],
            $snippet['tags'] ?? []
        );
        $repository->addIfMissing($youtubeVideo);
        echo PHP_EOL . $youtubeVideo->getId() . ' inserted/updated !';
    }
}

echo PHP_EOL . PHP_EOL . 'Done !';
