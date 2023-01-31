<?php
namespace Agifsofyan\Noto\Controllers;

use Agifsofyan\Noto\Noto;

class NotoController
{
    public function __invoke(Noto $noto) {
        $quote = $noto->justDoIt();

        return view('noto::index', compact('quote'));
    }
}