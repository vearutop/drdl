<?php

namespace DRDL\Client;

use Yaoi\Storage;
use Yaoi\Mock;
use Yaoi\String\Parser;

class Client
{
    /**
     * @var \Yaoi\Http\Client
     */
    public $http;

    public function __construct($user, $password)
    {
        $this->http = new \Yaoi\Http\Client();
        //$mock = new Mock(new Storage('serialized-file:///' . __DIR__ . '/../../mock.dat'));
        //$this->http->mock($mock);

        $post = array(
            'FormID' => 1,
            'login' => $user,
            'password' => $password,
        );

        $this->http->post = $post;
        $this->http->url = 'http://drd.fm/login.php';
        $response = $this->http->fetch();
        //print_r($response);
    }


    /**
     * @return Album[]
     */
    public function getAlbums($username)
    {
        $response = $this->http->fetch('http://drd.fm/profile/' . $username . '/albums/');
        //print_r($response);
        $parser = new Parser($response);

        $albums = array();

        foreach ($parser->innerAll('<tr><td class=\'item\'>', '</tr>') as $item) {
            //echo $item;
            $album = new Album();
            $album->id = trim($item->inner('/reply/album/', '"'));
            $album->comments = trim($item->inner('title="комментариев">', '</a>'));
            $album->groupId = trim($item->inner('/music/group/', '"'));
            $album->groupName = str_replace('&amp;', '&', trim($item->inner('>', '</a')));

            if (!$album->groupName) {
                $album->groupName = 'VA';
            }

            $album->title = trim(str_replace(
                'VA</acronym> &mdash; <a href="/music/album/' . $album->id . '">',
                '',
                (string)$item->inner('">', '</a>')));
            $album->title = str_replace('&amp;', '&', $album->title);
            $album->year = trim($item->inner('[', ']'));
            $album->genres = trim($item->inner('<em>', '</em>'));
            $albums[] = $album;
        }
        return $albums;
    }


    /**
     * @param Album $album
     * @return Track[]
     */
    public function getTracks(Album $album)
    {
        //http://drd.fm/play/album/40668
        $response = $this->http->fetch('http://drd.fm/play/album/' . $album->id);
        //print_r($response);
        return $this->parseM3u($response);
    }


    public function parseM3u($response)
    {
        $response = new Parser($response);
        $response->inner('#EXTINF', '0');
        $response->inner('#EXTINF', '0');
        $response = $response->inner();

        $tracks = array();

        foreach ($response->innerAll('#EXTINF:', '.mp3') as $item) {
            $track = new Track();
            $track->duration = (string)$item->inner(null, ', ');
            $track->artist = trim($item->inner(null, ' - '));
            $track->title = trim($item->inner(null, "\n"));
            $track->url = trim($item->inner() . '.mp3');
            $track->filename = urldecode(basename($track->url));
            $track->number = (string)Parser::create($track->filename)->inner(null, '.');

            $tracks[] = $track;
        }

        return $tracks;
    }


    public function getFavoriteTracks($username)
    {
        $response = $this->http->fetch('http://drd.fm/profile/' . $username . '/playlist/');
        $response = new Parser($response);

        $ids = array();
        foreach ($response->innerAll('<div class="item">', '</div>') as $item) {
            //echo '.';
            $id = trim($item->inner('href="/get/', '/'));
            if ($id) {
                $ids[] = $id;
            }
        }
        if (empty($ids)) {
            return array();
        }

        $ids[] = 'userfile';
        $this->http->post = array(
            'songlist' => implode('/', $ids)
        );
        $this->http->url = 'http://drd.fm/play/selection';
        $response = $this->http->fetch();
        return $this->parseM3u($response);
    }

}