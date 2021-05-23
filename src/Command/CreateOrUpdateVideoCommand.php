<?php

namespace App\Command;

use App\Entity\YoutubeVideo;
use PierreMiniggio\DatabaseFetcher\DatabaseFetcher;

class CreateOrUpdateVideoCommand
{

    public function __construct(
        private DatabaseFetcher $fetcher,
    )
    {}

    public function execute(YoutubeVideo $video): void
    {
        $videoYoutubeId = $video->getId();
        
        $selectVideoQuery = [
            $this->fetcher->createQuery('youtube_video')->select('id')->where('youtube_id = :id'),
            ['id' => $videoYoutubeId]
        ];
        $queriedVideos = $this->fetcher->query(...$selectVideoQuery);

        $insertOrUpdateParams = [
            'playlist_id' => $video->getPlaylistId(),
            'channel_id' => $video->getChannel(),
            'id' => $videoYoutubeId,
            'url' => $video->getUrl(),
            'thumbnail' => $video->getThumbnail(),
            'title' => $video->getTitle(),
            'description' => $video->getDescription(),
            'tags' => json_encode($video->getTags())
        ];
        
        if (! $queriedVideos) {
            $this->fetcher->exec(
                $this->fetcher
                    ->createQuery('youtube_video')
                    ->insertInto(
                        'playlist_id,channel_id,youtube_id,url,thumbnail,title,description,tags',
                        ':playlist_id,:channel_id,:id,:url,:thumbnail,:title,:description,:tags'
                    )
                ,
                $insertOrUpdateParams
            );
        } else {
            $this->fetcher->exec(
                $this->fetcher
                    ->createQuery(
                        'youtube_video'
                    )->update(
                        '
                            playlist_id = :playlist_id,
                            channel_id = :channel_id,
                            url = :url,
                            thumbnail = :thumbnail,
                            title = :title,
                            description = :description,
                            tags = :tags
                        '
                    )
                    ->where('youtube_id = :id')
                ,
                $insertOrUpdateParams
            );
        }
    }
}
