<?php

namespace DRDL;


use DRDL\Client\Client;
use Yaoi\Command;
use Yaoi\Io\Content\Heading;

class Favorite extends Command
{
    public $user;
    public $pass;
    public $storage = '.';

    public $tracksOnly;
    public $albumsOnly;

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

        if (!$this->albumsOnly) {
            $this->fetchFavoriteTracks();
        }

        if (!$this->tracksOnly) {
            $this->fetchFavoriteAlbums();
        }

        //http://drd.fm/play/album/40668
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