<?php

namespace DRDL\Client;


class Album
{
    public $id;
    public $comments;
    public $groupName;
    public $groupId;
    public $title;
    public $year;
    public $genres;
    public $languages;

    public $playlist;
    public $cmdDl;
    public $shDl = "#!/bin/bash\n\n";
}