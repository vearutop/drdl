<?php

namespace DRDL;


use DRDL\Client\Album;
use DRDL\Client\Artist;
use DRDL\Client\Client;
use Yaoi\Command;
use Yaoi\Io\Content\Heading;

class Favorite extends Command
{
    public $user;
    public $pass;
    public $storage = './drd.fm';

    public $tracksOnly;
    public $albumsOnly;
    public $allPlayLists;

    public $favUser;


    /** @var Client */
    private $client;

    /**
     * @param Command\Definition $definition
     * @param \stdClass|static $options
     */
    static function setUpDefinition(Command\Definition $definition, $options)
    {
        $definition->name = 'drdl';
        $definition->version = '0.1.0';
        $definition->description = 'DRD.FM favorite music downloader';

        $options->user = Command\Option::create()
            ->setType()
            //->setIsUnnamed()
            //->setIsRequired()
            ->setDescription('User name');
        $options->pass = Command\Option::create()
            ->setType()
            //->setIsUnnamed()
            //->setIsRequired()
            ->setDescription('Password');

        $options->storage = Command\Option::create()
            ->setIsUnnamed()
            //->setIsRequired()
            ->setDescription('Path to store files');

        $options->tracksOnly = Command\Option::create()->setDescription("Fetch only favorite tracks");
        $options->albumsOnly = Command\Option::create()->setDescription("Fetch only favorite albums");
        $options->allPlayLists = Command\Option::create()->setDescription("Fetch all artists playlists");

        $options->favUser = Command\Option::create()->setType()->setDescription("Profile name to fetch from, default: YOU, BITCH!");
    }

    function promptSilent($prompt = "Enter Password:")
    {
        if (preg_match('/^win/i', PHP_OS)) {
            $vbscript = sys_get_temp_dir() . 'prompt_password.vbs';
            file_put_contents(
                $vbscript, 'wscript.echo(InputBox("'
                . addslashes($prompt)
                . '", "", "password here"))');
            $command = "cscript //nologo " . escapeshellarg($vbscript);
            $password = rtrim(shell_exec($command));
            unlink($vbscript);
            return $password;
        } else {
            $command = "/usr/bin/env bash -c 'echo OK'";
            if (rtrim(shell_exec($command)) !== 'OK') {
                trigger_error("Can't invoke bash");
                return false;
            }
            $command = "/usr/bin/env bash -c 'read -s -p \""
                . addslashes($prompt)
                . "\" mypassword && echo \$mypassword'";
            $password = rtrim(shell_exec($command));
            echo "\n";
            return $password;
        }
    }

    public function performAction()
    {
        if (empty($this->user)) {
            echo "Your login, nigger:";
            $stdin = fopen('php://stdin', 'r');
            $this->user = trim(fgets($stdin));
        }

        if (empty($this->pass)) {
            $this->pass = $this->promptSilent();
        }

        $this->response->addContent(new Heading("Authenticating"));

        $this->client = new Client($this->user, $this->pass);

        if (!$this->favUser) {
            $this->favUser = $this->user;
        }

        if ($this->allPlayLists) {
            $this->fetchAllPlayLists();
            return;
        }

        if (!$this->albumsOnly) {
            $this->fetchFavoriteTracks();
        }

        if (!$this->tracksOnly) {
            $this->fetchFavoriteAlbums();
        }

        //http://drd.fm/play/album/40668
    }


    private function filterDir($directory)
    {
        return str_replace(array('?', '/', '\\', ':'), '_', $directory);
    }

    private function fetchAllPlayLists()
    {
        $this->response->addContent(new Heading("Fetching all playlists"));
        $letters = $this->client->getMusicLetters();
        $genresMap = array();

        foreach ($letters as $letter) {
            $this->response->addContent(new Heading("Letter: " . urldecode($letter)));

            $music = $this->client->getArtists($letter);
            if (empty($music)) {
                $this->response->error("No artists");
            }
            $letterDl = '';
            $letterShDl = "#!/bin/bash\n\n";


            foreach ($music as $item) {
                if ($item instanceof Artist) {
                    $this->response->addContent($item->name);
                    $directory =  $item->name
                        . ' (' . $item->genres . ') [' . $item->languages . ']';

                    $directory = $this->filterDir($directory);
                    $ld = $this->client->escapeDir($directory);
                    $letterDl .= "cd \"$ld\"\ndl.cmd\ncd ..\n";
                    $letterShDl .= "echo \"$ld\"\ncd \"$ld\"\n./dl.sh\ncd ..\n";

                    $genres = explode(', ', $item->genres);
                    foreach ($genres as $genre) {
                        $path = urldecode($letter) . '/' . $directory;
                        $genresMap[$genre][$path] = $item->name;
                    }

                    $directory = $this->storage . '/' . urldecode($letter) . '/' . $directory;
                    if (!file_exists($directory)) {
                        $bugName = $this->storage . '/' . urldecode($letter) . '/' . $this->filterDir($item->name
                                . ' (' . $item->genresO . ') []');
                        var_dump($bugName, $directory);

                        if (file_exists($bugName)) {
                            //die();
                            rename($bugName, $directory);

                        }
                        //die('!');
                    }


                    if (!file_exists($directory)) {
                        mkdir($directory, 0777, true);
                    }

                    $filePath = $directory . '/all.m3u';
                    unset($playlist);

                    if (!file_exists($filePath)) {
                        $playlist = $this->client->getPlaylistByArtist($item);
                        file_put_contents($filePath, $playlist);
                    }

                    if (!isset($playlist)) {
                        $playlist = file_get_contents($filePath);
                    }

                    $artistDl = '';
                    $artistShDl = "#!/bin/bash\n\n";


                    try {
                        $albums = $this->client->getAlbumsFromPlaylist($playlist);
                    } catch (\Exception $e) {
                        var_dump($filePath);
                        die();
                        unlink($filePath);
                    }
                    foreach ($albums as $album) {
                        $albumDir = $this->filterDir($album->year . ' - ' . $album->title);
                        $ld = $this->client->escapeDir($albumDir);
                        $artistDl .= "cd \"$ld\"\ndl.cmd\ncd ..\n";
                        $artistShDl .= "echo \"$ld\"\ncd \"$ld\"\n./dl.sh\ncd ..\n";
                        $albumDir = $directory . '/' . $albumDir;
                        //unlink($albumDir . ' /playlist.m3u');
                        //rmdir($albumDir . ' ');
                        //$this->response->addContent($albumDir);
                        //continue;
                        if (!file_exists($albumDir)) {
                            mkdir($albumDir, 0777, true);
                            file_put_contents($albumDir . '/playlist.m3u', $album->playlist);
                        }
                        file_put_contents($albumDir . '/dl.cmd', $album->cmdDl);
                        file_put_contents($albumDir . '/dl.sh', $album->shDl);
                        chmod($albumDir . '/dl.sh', 0777);
                    }

                    $filePath = $directory . '/dl.cmd';
                    file_put_contents($filePath, $artistDl);
                    $filePath = $directory . '/dl.sh';
                    file_put_contents($filePath, $artistShDl);
                    chmod($filePath, 0777);

                    //print_r($albums);
                }
                if ($item instanceof Album) {
                    $directory = $item->title
                        . ' (' . $item->genres . ') [' . $item->languages . ']';

                    $directory = str_replace(array('?', '/', '\\', ':'), '_', $directory);
                    $directory = $this->storage . '/ZBORNIKI/' . $directory;
                    if (!file_exists($directory)) {
                        mkdir($directory, 0777, true);
                    }

                    $filePath = $directory . '/all.m3u';
                    if (!file_exists($filePath)) {
                        $playlist = $this->client->getPlaylistByAlbum($item);
                        file_put_contents($filePath, $playlist);
                    }
                }
            }
            file_put_contents($this->storage . '/' . urldecode($letter) . '/dl.cmd', $letterDl);
            file_put_contents($this->storage . '/' . urldecode($letter) . '/dl.sh', $letterShDl);
            chmod($this->storage . '/' . urldecode($letter) . '/dl.sh', 0777);

            //return;
            //print_r($music);
            //break;
        }

        if (!file_exists($this->storage . '/GENRES/')) {
            mkdir($this->storage . '/GENRES/', 0777, true);
        }
        foreach ($genresMap as $genre => $items) {
            $list = '';
            $dl = '';
            $sh = "#!/bin/bash\n\n";
            foreach ($items as $path => $name) {
                $list .= $path . "\n";
                $path = $this->client->escapeDir($path);
                $dl .= "echo \"$path\"\ncd \"../$path\"\ndl.cmd\ncd ../../GENRES\n";
                $sh .= "echo \"$path\"\ncd \"../$path\"\n./dl.sh\ncd ../../GENRES\n";

            }
            file_put_contents($this->storage . '/GENRES/' . $genre . '.txt', $list);
            file_put_contents($this->storage . '/GENRES/' . $genre . '-dl.cmd', $dl);
            file_put_contents($this->storage . '/GENRES/' . $genre . '-dl.sh', $sh);
            chmod($this->storage . '/GENRES/' . $genre . '-dl.sh', 0777);
        }

    }

    private function fetchFavoriteAlbums()
    {
        $this->response->addContent(new Heading("Fetching favorite albums"));

        $albums = $this->client->getAlbums($this->favUser);
        if (empty($albums)) {
            $albums = $this->client->getAlbums($this->favUser);
        }
        $this->response->addContent(count($albums) . ' albums found');
        if (empty($albums)) {
            return;
        }

        $total = count($albums);
        $start = time();
        $percent = 0;

        foreach ($albums as $i => $album) {
            $i++;
            $percent = round(100 * $i / $total);

            $statusLine = " $percent%, $i/$total " . $album->groupName . ' - ' . $album->title;
            $statusLine = str_pad(substr($statusLine, 0, 100), 100, ' ');
            echo $statusLine, "\r";

            $tracks = $this->client->getTracks($album);
            $totalTracks = count($tracks);

            $directory = "{$album->groupName} - [$album->year] {$album->title} ($album->genres)";
            $directory = str_replace(array('/', '\\', ':', '?'), array('-', '-', '', ''), $directory);

            if (!file_exists($this->storage . '/' . $directory)) {
                mkdir($this->storage . '/' . $directory);
            }

            foreach ($tracks as $t => $track) {
                $path = $this->storage . '/' . $directory . '/' . $track->filename;
                $t++;
                $statusLine = " $percent%, $i/$total {$album->groupName} - {$album->title}, $t/$totalTracks $track->number. $track->artist - $track->title";
                $statusLine = str_pad(substr($statusLine, 0, 100), 100, ' ');
                echo $statusLine, "\r";
                if (file_exists($path)) {
                    continue;
                }

                $trackData = file_get_contents($track->url);
                file_put_contents($path, $trackData);
            }
            //break;
        }
        $this->response->addContent('');
        $this->response->success("Done");

    }

    private function fetchFavoriteTracks()
    {
        $this->response->addContent(new Heading("Fetching favorite tracks"));
        $tracks = $this->client->getFavoriteTracks($this->favUser);
        if (empty($tracks)) {
            $tracks = $this->client->getFavoriteTracks($this->favUser);
        }

        $this->response->addContent(count($tracks) . ' favorite tracks found');

        if (empty($tracks)) {
            return;
        }

        $directory = "Favorite Tracks";

        if (!file_exists($this->storage . '/' . $directory)) {
            mkdir($this->storage . '/' . $directory);
        }
        $percent = 0;
        $total = count($tracks);

        foreach ($tracks as $t => $track) {
            $percent = round(100 * $t / $total);

            $path = $this->storage . '/' . $directory . '/' . $track->filename;
            $t++;
            $statusLine = " $percent% $t/$total, Favorite tracks,  $track->artist - $track->title";
            $statusLine = str_pad(substr($statusLine, 0, 100), 100, ' ');
            echo $statusLine, "\r";
            if (file_exists($path)) {
                continue;
            }

            $trackData = file_get_contents($track->url);
            file_put_contents($path, $trackData);
        }

        $this->response->addContent('');
        $this->response->success("Done");
    }

}