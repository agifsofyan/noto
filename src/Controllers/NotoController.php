<?php
namespace Agif\Noto\Controllers;

use Agif\Noto\Noto;

class NotoController
{
    public function __invoke(Noto $noto) {
        $quote = $noto->justDoIt();

        return view('noto::index', compact('quote'));
    }
}