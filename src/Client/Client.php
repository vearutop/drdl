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
        $mock = new Mock(new Storage('serialized-file:///' . __DIR__ . '/../../mock.dat'));
        $this->http->mock($mock);

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
        //http://drd.fm/profile/soltpain/albums
        $response = $this->http->fetch('http://drd.fm/profile/' . $username . '/albums');
        //print_r($response);
        $parser = new Parser($response);

        $albums = array();

        foreach ($parser->innerAll('<div class="head">', '</div>') as $item) {
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

    public function getMusicLetters()
    {
        $response = $this->http->fetch('http://drd.fm/music/letter/other/');
        //print_r($response);
        $letters = array();
        foreach (Parser::create($response)
                     ->inner('<div id="letters">', 'nav_pre nav_bread')
                     ->innerAll('music/letter/', '/') as $item) {
            $letters[(string)$item] = (string)$item;
        }
        return $letters;
    }

    public function escapeDir($directory) {
        $ld = str_replace(array('"', '$'), array('\\"', '\\$'), $directory);
        return $ld;
    }

    public function getArtists($letter)
    {
        $response = $this->http->fetch('http://drd.fm/music/letter/' . $letter . '/');
        $result = array();
        foreach (Parser::create($response)->innerAll('/music/group/', '</div>') as $item) {
            $artist = new Artist();
            $artist->id = (string)$item->inner(null, '"');
            $artist->name = (string)$item->inner('>', '</a>');
            $genres = str_replace(array('<em>', '</em>'), '', trim($item->inner()));
            $artist->genres = $genres;
            if (($p = strpos($artist->genres, '<abbr')) !== false) {
                if (!$p) {
                    $artist->genresO = $genres;
                }

                foreach (Parser::create($artist->genres)->innerAll('<abbr', '</abbr>') as $lang) {
                    $artist->languages .= $lang->inner('>') . ', ';
                }
                $artist->languages = substr($artist->languages, 0, -2);
                $artist->genres = substr($artist->genres, 0, $p);
            }
            if (substr($artist->genres, 0, 5) === '<abbr') {
                var_dump($artist->genres);
                var_dump($genres);
                die('bitch');
            }

            $result[] = $artist;
        }

        foreach (Parser::create($response)->innerAll('list_item album_item', '</div>') as $item) {
            $album = new Album();
            $album->id = (string)$item->inner('/music/album/', '"');
            $album->title = (string)$item->inner('>', '</a>');
            $album->year = (string)$item->inner('<span class="year">[', ']</span>');
            $album->genres = (string)$item->inner('<span class="genre">', '</span>');
            $album->groupName = 'VA';
            foreach ($item->setOffset(0)
                         ->inner('<span class="language">', '</span>')
                         ->innerAll('<acronym', '</acronym') as $lang) {
                $album->languages .= $lang->inner('>') . ', ';
            }
            $album->languages = substr($album->languages, 0, -2);
            $result[] = $album;
        }

        return $result;
    }

    public function getPlaylistByArtist(Artist $artist)
    {
        //http://drd.fm/play/group/1
        $response = $this->http->fetch('http://drd.fm/play/group/' . $artist->id);

        //echo $response;

        return $response;
    }

    /**
     * @param $m3u
     * @return Album[]
     */
    public function getAlbumsFromPlaylist($m3u)
    {
        $m3u = new Parser($m3u);

        $sep = '#EXTINF:0, Альбом:';
        $head = (string)$m3u->inner(null, $sep);
        $groupName = (string)$m3u->setOffset(0)->inner('Группа: ', "\n");

        $albumRows = explode($sep, $m3u);
        if (count($albumRows) < 2) {
            var_dump($albumRows);
            throw new \Exception("AAAAAA!!!");
        }
        unset($albumRows[0]);

        $albums = array();
        foreach ($albumRows as $albumRow) {
            $album = new Album();
            $line = Parser::create($albumRow)->inner(' ', "\n");
            $p = strrpos($line, '(');
            $album->year = (string)Parser::create(substr($line, $p))->inner('(', ')');
            $album->title = trim(substr($line, 0, $p));
            $album->groupName = $groupName;
            $album->playlist = $head . $sep . $albumRow;

            foreach (Parser::create($albumRow)->innerAll('http:', '.mp3') as $url) {
                $url = 'http:' . $url . '.mp3';
                $filename = urldecode(basename($url));
                $album->cmdDl .= "wget -nc $url\n";
                $album->shDl .= "wget -nc $url\n";
            }
            $albums[] = $album;
        }

        return $albums;
    }

    public function getPlaylistByAlbum(Album $album)
    {
        //http://drd.fm/play/album/1
        $response = $this->http->fetch('http://drd.fm/play/album/' . $album->id);

        //echo $response;

        return $response;
    }

}