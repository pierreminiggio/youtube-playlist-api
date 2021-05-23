<?php

namespace App;

use PierreMiniggio\DatabaseFetcher\DatabaseFetcher;

class App
{

    public function __construct(
        private DatabaseFetcher $fetcher
    )
    {
    }

    public function run(
        string $path,
        ?string $queryParameters
    ): void
    {
        if ($path === '/') {
            http_response_code(404);

            return;
        }
        
        $playlistId = substr($path, 1);

        $fetchedVideos = $this->fetcher->query(
            $this->fetcher->createQuery(
                'youtube_video'
            )->select(
                'channel_id',
                'youtube_id',
                'url',
                'title'
            )->where('playlist_id = :playlist_id'),
            ['playlist_id' => $playlistId]
        );

        if (empty($fetchedVideos)) {
            http_response_code(404);

            return;
        }

        http_response_code(200);
        echo json_encode(array_map(fn (array $fetchedVideo): array => [
            'id' => $fetchedVideo['youtube_id'],
            'channel_id' => $fetchedVideo['channel_id'],
            'url' => $fetchedVideo['url'],
            'title' => $fetchedVideo['title']
        ], $fetchedVideos));
    }
}
