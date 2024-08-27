<?php
namespace Package\R3m\Io\Markdown\Service;

use R3m\Io\App;

use R3m\Io\Module\Core;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

use League\CommonMark\Exception\CommonMarkException;

class Markdown {

    /**
     * @throws CommonMarkException
     */
    public static function parse(App $object, $string=''): string
    {
        //options: App::options($object)
        //flags: App::flags($object)
        $config = [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ];
        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new AttributesExtension());
        $converter = new MarkdownConverter($environment);
        $comment_start = Core::uuid();
        $comment_end = Core::uuid();
        $string = str_replace(['<!--', '-->'], [$comment_start, $comment_end], $string);
        $anchor_start = Core::uuid();
        $anchor_end_start = Core::uuid();
        $anchor_end = Core::uuid();
        $anchor_double_quote = Core::uuid();
        $anchor_single_quote = Core::uuid();
        $anchor_is = Core::uuid();
        $string = Markdown::anchor(
            $object,
            $string,
            [
                'anchor_start' => $anchor_start,
                'anchor_end_start' => $anchor_end_start,
                'anchor_end' => $anchor_end,
                'anchor_double_quote' => $anchor_double_quote,
                'anchor_single_quote' => $anchor_single_quote,
                'anchor_is' => $anchor_is
            ]
        );
        $string = $converter->convert($string);
        $string =  str_replace([$comment_start, $comment_end], ['<!--', '-->'], $string);
        $string =  str_replace(
            [
                $anchor_start,
                $anchor_end_start,
                $anchor_end,
                $anchor_double_quote,
                $anchor_single_quote,
                $anchor_is
            ],
            [
                '<a',
                '</a',
                '>',
                '"',
                '\'',
                '='
            ],
            $string
        );
        $string = str_replace(['<p><!--', '--></p>'], ['<!--', '-->'], $string);
        $string = str_replace(['<p><a', '</a></p>'], ['<a', '</a>'], $string);
        ddd($string);
        return $string;
    }

    public static function anchor($object, $string='', $options=[]): string
    {
        $data = mb_str_split($string, 1);
        $anchor = false;
        $is_tag = false;
        $is_close_tag = false;
        $is_value = false;
        $collect = [];
        $record = [];
        foreach($data as $nr => $char){
            $previous = $data[$nr - 1] ?? null;
            $next = $data[$nr + 1] ?? null;
            $next_next = $data[$nr + 2] ?? null;
            if(
                $char === '<' &&
                $next === '/' &&
                $next_next === 'a'
            ){
                $is_close_tag = $nr;
            }
            elseif(
                $char == '<' &&
                $next === 'a' &&
                $next_next === ' ' &&
                $is_tag === false
            ){
                $is_tag = $nr;
            }
            elseif(
                $char == '>' &&
                $is_tag !== false
            ){
                if($is_close_tag === false){
                    $value = '';
                    $key = '';
                    $is_single_quote = false;
                    $is_double_quote = false;
                    foreach($collect as $collect_nr => $collect_value){
                        if($collect_value === '"'){
                            $is_double_quote = true;
                        }
                        elseif($collect_value === '\''){
                            $is_single_quote = true;
                        }
                        elseif($collect_value === '='){
                            $is_value = true;
                        }
                        elseif(
                            $collect_value === ' ' &&
                            $is_single_quote === false &&
                            $is_double_quote === false
                        ){
                            $is_value = false;
                            if($key !== ''){
                                $record[$key] = $value;
                            }
                            $key = '';
                            $value = '';
                        }
                        if(
                            $is_value === false &&
                            $is_single_quote === false &&
                            $is_double_quote === false
                        ){
                            if($collect_value !== ' '){
                                $key .= $collect_value;
                            }
                        } else {
                            if($collect_value === '='){
                                //nothing
                            }
                            elseif($collect_value === '"'){
                                $value .= $options['anchor_double_quote'];
                            }
                            elseif($collect_value === '\''){
                                $value .= $options['anchor_single_quote'];
                            } else {
                                $value .= $collect_value;
                            }
                        }
                    }
                    if($is_value === true) {
                        if ($key !== '') {
                            $record[$key] = $value;
                        }
                    }
                    for($i = $is_tag; $i <= $nr; $i++){
                        $data[$i] = null;
                    }
                } else {
                    $safe_record = [
                        'id' => $record['id'] ?? null,
                        'href' => $record['href'] ?? null,
                        'title' => $record['title'] ?? null,
                    ];
                    for($i = $is_close_tag; $i <= $nr; $i++){
                        $data[$i] = null;
                    }
                    $data[$is_tag] = $options['anchor_start'];
                    foreach($safe_record as $attribute => $value){
                        if($value){
                            $data[$is_tag] .= ' ' . $attribute . $options['anchor_is'] . $value;
                        }
                    }
                    $data[$is_tag] .= $options['anchor_end'];
                    $data[$is_close_tag] = $options['anchor_end_start'] . $options['anchor_end'];
                    /*
                    for($i = $is_tag + 1; $i < $is_close_tag; $i++){
                        d($data[$i]);
                    }
                    */
                    $is_tag = false;
                    $is_close_tag = false;
                    $is_value = false;
                    $anchor = false;
                    $collect = [];
                    $record = [];
                }
            }
            elseif(
                $previous !== '/' &&
                $char === 'a' &&
                $next === ' ' &&
                $is_tag !== false &&
                $anchor === false
            ){
                $anchor = true;
                continue;
            }
            if($anchor){
                $collect[] = $char;
            }
        }
        $string = implode('', $data);
        return $string;
    }

    public static function apply_anchor($object, $string=''): string
    {
        return $string;
    }

}
