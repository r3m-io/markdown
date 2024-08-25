<?php
namespace Package\R3m\Io\Markdown\Service;

use R3m\Io\App;

use League\CommonMark\CommonMarkConverter;

use League\CommonMark\Exception\CommonMarkException;

class Markdown {

    /**
     * @throws CommonMarkException
     */
    public static function parse(App $object, $flags, $options, $string=''): string
    {
        $converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        return $converter->convert($string);
    }
}
