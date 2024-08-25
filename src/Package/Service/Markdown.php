<?php
namespace Package\R3m\Io\Markdown\Service;

use R3m\Io\App;

use R3m\Io\Module\Core;

use League\CommonMark\CommonMarkConverter;

use League\CommonMark\Exception\CommonMarkException;

class Markdown {

    /**
     * @throws CommonMarkException
     */
    public static function parse(App $object, $string=''): string
    {
        //options: App::options($object)
        //flags: App::flags($object)
        $ldelim = Core::uuid();
        $rdelim = Core::uuid();
        $string = str_replace(['{', '}'], [$ldelim, $rdelim], $string);
        $converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $string = $converter->convert($string);
        return str_replace([$ldelim, $rdelim], ['{', '}'], $string);
    }
}
