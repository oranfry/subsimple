<?php
namespace period;

class day extends \Period
{
    public $id = 'd';
    public $navlabel = 'Day';
    public $step = '1 day';

    public function label($from)
    {
        return date('D j M Y', strtotime($from));
    }
}
