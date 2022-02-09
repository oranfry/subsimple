<?php
namespace period;

class alltime extends \Period
{
    public $id = 'a';
    public $navlabel = 'All Time';
    public $graphdiv = '1 month';
    public $suppress_nav = true;
    public $suppress_custom = true;

    public function label($from)
    {
        return 'All Time';
    }

    public function rawstart($date)
    {
    }

    public function start($date)
    {
    }
}
