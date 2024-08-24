<?php
namespace R3m\Io\Markdown;

#
#
# Parsedown
# http://parsedown.org
#
# (c) Emanuil Rusev
# http://erusev.com
#
# For the full license information, view the LICENSE file that was distributed
# with this source code.
#
#

class Markdown {
    const version = '1.7.4';

    private static array $instances = [];

    #
    # Fields
    #

    protected array $definition_data = [];

    #
    # Read-Only

    protected array $special_characters = [
        '\\', '`', '*', '_', '{', '}', '[', ']', '(', ')', '>', '#', '+', '-', '.', '!', '|',
    ];

    protected array $strong_regex = [
        '*' => '/^[*]{2}((?:\\\\\*|[^*]|[*][^*]*[*])+?)[*]{2}(?![*])/s',
        '_' => '/^__((?:\\\\_|[^_]|_[^_]*_)+?)__(?!_)/us',
    ];

    protected array $em_regex = [
        '*' => '/^[*]((?:\\\\\*|[^*]|[*][*][^*]+?[*][*])+?)[*](?![*])/s',
        '_' => '/^_((?:\\\\_|[^_]|__[^_]*__)+?)_(?!_)\b/us',
    ];

    protected string $regex_html_attribute = '[a-zA-Z_:][\w:.-]*(?:\s*=\s*(?:[^"\'=<>`\s]+|"[^"]*"|\'[^\']*\'))?';

    protected array $void_element = [
        'area',
        'base',
        'br',
        'col',
        'command',
        'embed',
        'hr',
        'img',
        'input',
        'link',
        'meta',
        'param',
        'source',
    ];

    protected array $text_level_element = [
        'a', 'br', 'bdo', 'abbr', 'blink', 'nextid', 'acronym', 'basefont',
        'b', 'em', 'big', 'cite', 'small', 'spacer', 'listing',
        'i', 'rp', 'del', 'code',          'strike', 'marquee',
        'q', 'rt', 'ins', 'font',          'strong',
        's', 'tt', 'kbd', 'mark',
        'u', 'xm', 'sub', 'nobr',
        'sup', 'ruby',
        'var', 'span',
        'wbr', 'time',
    ];

    protected array $inline_type = [
        '"' => ['special_character'],
        '!' => ['image'],
        '&' => ['special_character'],
        '*' => ['emphasis'],
        ':' => ['url'],
        '<' => ['url_tag', 'email_tag', 'markup', 'special_character'],
        '>' => ['special_character'],
        '[' => ['link'],
        '_' => ['emphasis'],
        '`' => ['code'],
        '~' => ['strikethrough'],
        '\\' => ['escape_sequence'],
    ];

    # ~

    protected string $inline_marker_list = '!"*_&[:<>`~\\';

    protected bool $breaks_enabled;

    protected bool $markup_escaped;

    protected bool $url_linked = true;

    protected bool $safe_mode = false;

    function text(string $text): string
    {
        # make sure no definitions are set
        $this->definition_data = [];
        # standardize line breaks
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        # remove surrounding line breaks
        $text = trim($text, "\n");
        # split text into lines
        $lines = explode("\n", $text);
        # iterate through lines to identify blocks
        $markup = $this->lines($lines);
        # trim line breaks
        return trim($markup, "\n");
    }

    #
    # Setters
    #

    function set_breaks_enabled($breaks_enabled): Markdown
    {
        $this->breaks_enabled = $breaks_enabled;
        return $this;
    }

    function set_markup_escaped($markup_escaped): Markdown
    {
        $this->markup_escaped = $markup_escaped;
        return $this;
    }

    function set_url_linked($url_linked): Markdown
    {
        $this->url_linked = $url_linked;
        return $this;
    }

    function set_safe_mode($safe_mode): Markdown
    {
        $this->safe_mode = (bool) $safe_mode;
        return $this;
    }

    protected array $safe_link_whitelist = [
        'http://',
        'https://',
        'ftp://',
        'ftps://',
        'mailto:',
        'data:image/png;base64,',
        'data:image/gif;base64,',
        'data:image/jpeg;base64,',
        'irc:',
        'ircs:',
        'git:',
        'ssh:',
        'news:',
        'steam:',
    ];

    #
    # Lines
    #

    protected array $block_type = [
        '#' => ['header'],
        '*' => ['rule', 'list'],
        '+' => ['list'],
        '-' => ['text_header', 'table', 'rule', 'list'],
        '0' => ['list'],
        '1' => ['list'],
        '2' => ['list'],
        '3' => ['list'],
        '4' => ['list'],
        '5' => ['list'],
        '6' => ['list'],
        '7' => ['list'],
        '8' => ['list'],
        '9' => ['list'],
        ':' => ['table'],
        '<' => ['comment', 'markup'],
        '=' => ['text_header'],
        '>' => ['quote'],
        '[' => ['reference'],
        '_' => ['rule'],
        '`' => ['fenced_code'],
        '|' => ['table'],
        '~' => ['fenced_code'],
    ];

    protected array $unmarked_block_type = [
        'Code',
    ];

    #
    # Blocks
    #

    protected function lines(array $lines): string
    {
        $current_block = null;
        foreach ($lines as $line) {
            if (chop($line) === '') {
                if (isset($current_block)) {
                    $current_block['interrupted'] = true;
                }
                continue;
            }
            if (strpos($line, "\t") !== false) {
                $parts = explode("\t", $line);
                $line = $parts[0];
                unset($parts[0]);
                foreach ($parts as $part) {
                    $shortage = 4 - mb_strlen($line, 'utf-8') % 4;
                    $line .= str_repeat(' ', $shortage);
                    $line .= $part;
                }
            }
            $indent = 0;
            while (isset($line[$indent]) && $line[$indent] === ' ') {
                $indent ++;
            }
            $text = $indent > 0 ? substr($line, $indent) : $line;
            $line = [
                'body' => $line,
                'indent' => $indent,
                'text' => $text
            ];
            if (isset($current_block['continuable'])) {
                $block = $this->{'block'.$current_block['type'].'_continue'}($line, $current_block);
                if (isset($block)) {
                    $current_block = $block;
                    continue;
                } else {
                    if ($this->is_block_completable($current_block['type'])) {
                        $current_block = $this->{'block'.$current_block['type'].'_complete'}($current_block);
                    }
                }
            }
            $marker = $text[0];
            $block_type = $this->unmarked_block_type;
            if (isset($this->block_type[$marker])) {
                foreach ($this->block_type[$marker] as $blockType) {
                    $block_type []= $blockType;
                }
            }
            foreach ($block_type as $blockType) {
                $block = $this->{'block'.$blockType}($line, $current_block);
                if (isset($block)) {
                    $block['type'] = $blockType;
                    if ( ! isset($block['identified'])) {
                        $blocks []= $current_block;
                        $block['identified'] = true;
                    }
                    if ($this->is_block_continuable($blockType)) {
                        $block['continuable'] = true;
                    }
                    $current_block = $block;
                    continue 2;
                }
            }
            if (
                isset($current_block) && !
                isset($current_block['type']) && !
                isset($current_block['interrupted'])
            ) {
                $current_block['element']['text'] .= "\n".$text;
            } else {
                $blocks []= $current_block;
                $current_block = $this->paragraph($line);
                $current_block['identified'] = true;
            }
        }

        if (
            isset($current_block['continuable']) &&
            $this->is_block_completable($current_block['type'])
        ) {
            $current_block = $this->{'block'.$current_block['type'].'_complete'}($current_block);
        }
        $blocks []= $current_block;
        unset($blocks[0]);
        $markup = '';
        foreach ($blocks as $block) {
            if (isset($block['hidden']))
            {
                continue;
            }
            $markup .= "\n";
            $markup .= $block['markup'] ?? $this->element($block['element']);
        }
        $markup .= "\n";
        return $markup;
    }

    protected function is_block_continuable(string $type): bool
    {
        return method_exists($this, 'block_'.$type.'_continue');
    }

    protected function is_block_completable(string $type): bool
    {
        return method_exists($this, 'block_'.$type.'_complete');
    }

    #
    # Code

    protected function block_code(array $line, array $block = null): ?array
    {
        if (
            isset($block) &&
            ! isset($block['type']) &&
            ! isset($block['interrupted'])
        ) {
            return null;
        }
        if ($line['indent'] >= 4) {
            $text = substr($line['body'], 4);
            return [
                'element' => [
                    'name' => 'pre',
                    'handler' => 'element',
                    'text' => [
                        'name' => 'code',
                        'text' => $text,
                    ],
                ],
            ];
        }
        return null;
    }

    protected function block_code_continue(array $line, array $block): ?array
    {
        if ($line['indent'] >= 4) {
            if (isset($block['interrupted'])) {
                $block['element']['text']['text'] .= "\n";
                unset($block['interrupted']);
            }
            $block['element']['text']['text'] .= "\n";
            $text = substr($line['body'], 4);
            $block['element']['text']['text'] .= $text;
            return $block;
        }
        return null;
    }

    protected function block_code_complete(array $block): array
    {
        $text = $block['element']['text']['text'];
        $block['element']['text']['text'] = $text;
        return $block;
    }

    #
    # Comment

    protected function block_comment(array $line): ?array
    {
        if (
            $this->markup_escaped ||
            $this->safe_mode
        ) {
            return null;
        }
        if (
            isset($line['text'][3]) &&
            $line['text'][3] === '-' &&
            $line['text'][2] === '-' &&
            $line['text'][1] === '!'
        ) {
            $block = array(
                'markup' => $line['body'],
            );
            if (preg_match('/-->$/', $line['text'])) {
                $block['closed'] = true;
            }
            return $block;
        }
        return null;
    }

    protected function block_comment_continue(array $line, array $block): ?array
    {
        if (isset($block['closed'])) {
            return null;
        }
        $block['markup'] .= "\n" . $line['body'];
        if (preg_match('/-->$/', $line['text'])) {
            $block['closed'] = true;
        }
        return $block;
    }

    #
    # Fenced Code

    protected function block_fenced_code(array $line): ?array
    {
        if (preg_match('/^['.$line['text'][0].']{3,}[ ]*([^`]+)?[ ]*$/', $line['text'], $matches)) {
            $element = [
                'name' => 'code',
                'text' => '',
            ];
            if (isset($matches[1])) {
                /**
                 * https://www.w3.org/TR/2011/WD-html5-20110525/elements.html#classes
                 * Every HTML element may have a class attribute specified.
                 * The attribute, if specified, must have a value that is a set
                 * of space-separated tokens representing the various classes
                 * that the element belongs to.
                 * [...]
                 * The space characters, for the purposes of this specification,
                 * are U+0020 SPACE, U+0009 CHARACTER TABULATION (tab),
                 * U+000A LINE FEED (LF), U+000C FORM FEED (FF), and
                 * U+000D CARRIAGE RETURN (CR).
                 */
                $language = substr($matches[1], 0, strcspn($matches[1], " \t\n\f\r"));
                $class = 'language-'.$language;
                $element['attributes'] = [
                    'class' => $class,
                ];
            }
            return [
                'char' => $line['text'][0],
                'element' => [
                    'name' => 'pre',
                    'handler' => 'element',
                    'text' => $element,
                ],
            ];
        }
        return null;
    }

    protected function block_fenced_code_continue(string $line, array $block): ?array
    {
        if (isset($block['complete'])) {
            return null;
        }
        if (isset($block['interrupted'])) {
            $block['element']['text']['text'] .= "\n";
            unset($block['interrupted']);
        }
        if (preg_match('/^'.$block['char'].'{3,}[ ]*$/', $line['text'])) {
            $block['element']['text']['text'] = substr($block['element']['text']['text'], 1);
            $block['complete'] = true;
            return $block;
        }
        $block['element']['text']['text'] .= "\n".$line['body'];
        return $block;
    }

    protected function block_fenced_code_complete(array $block): array
    {
        $text = $block['element']['text']['text'];
        $block['element']['text']['text'] = $text;
        return $block;
    }

    #
    # Header

    protected function block_header(array $line): ?array
    {
        if (isset($line['text'][1])) {
            $level = 1;
            while (isset($line['text'][$level]) && $line['text'][$level] === '#') {
                $level ++;
            }
            if ($level > 6) {
                return null;
            }
            $text = trim($line['text'], '# ');
            return [
                'element' => [
                    'name' => 'h' . min(6, $level),
                    'text' => $text,
                    'handler' => 'line',
                ]
            ];
        }
        return null;
    }

    #
    # List

    protected function block_list(array $line): ?array
    {
        list($name, $pattern) = $line['text'][0] <= '-' ? ['ul', '[*+-]'] : ['ol', '[0-9]+[.]'];
        if (preg_match('/^('.$pattern.'[ ]+)(.*)/', $line['text'], $matches)) {
            $block = [
                'indent' => $line['indent'],
                'pattern' => $pattern,
                'element' => [
                    'name' => $name,
                    'handler' => 'elements',
                ]
            ];
            if($name === 'ol') {
                $listStart = stristr($matches[0], '.', true);
                if($listStart !== '1') {
                    $block['element']['attributes'] = ['start' => $listStart];
                }
            }
            $block['li'] = [
                'name' => 'li',
                'handler' => 'li',
                'text' => [
                    $matches[2],
                ]
            ];
            $block['element']['text'] []= & $block['li'];
            return $block;
        }
        return null;
    }

    protected function block_list_continue(array $line, array $block): array
    {
        if (
            $block['indent'] === $line['indent'] &&
            preg_match('/^'.$block['pattern'].'(?:[ ]+(.*)|$)/', $line['text'], $matches)
        ) {
            if (isset($block['interrupted'])) {
                $block['li']['text'] []= '';
                $block['loose'] = true;
                unset($block['interrupted']);
            }
            unset($block['li']);
            $text = $matches[1] ?? '';
            $block['li'] = [
                'name' => 'li',
                'handler' => 'li',
                'text' => [
                    $text,
                ]
            ];
            $block['element']['text'] []= & $block['li'];
            return $block;
        }
        if (
            $line['text'][0] === '[' &&
            $this->block_reference($line)
        ) {
            return $block;
        }
        if ( ! isset($block['interrupted'])) {
            $text = preg_replace('/^[ ]{0,4}/', '', $line['body']);
            $block['li']['text'] []= $text;
            return $block;
        }
        if ($line['indent'] > 0) {
            $block['li']['text'] []= '';
            $text = preg_replace('/^[ ]{0,4}/', '', $line['body']);
            $block['li']['text'] []= $text;
            unset($block['interrupted']);
            return $block;
        }
        return $block;
    }

    protected function block_list_complete(array $block): array
    {
        if (isset($block['loose'])) {
            foreach ($block['element']['text'] as &$li) {
                if (end($li['text']) !== '') {
                    $li['text'] []= '';
                }
            }
        }
        return $block;
    }

    #
    # Quote

    protected function block_quote(array $line): ?array
    {
        if (preg_match('/^>[ ]?(.*)/', $line['text'], $matches)) {
            return [
                'element' => [
                    'name' => 'blockquote',
                    'handler' => 'lines',
                    'text' => (array) $matches[1],
                ]
            ];
        }
        return null;
    }

    protected function block_quote_continue(array $line, array $block): array
    {
        if ($line['text'][0] === '>' && preg_match('/^>[ ]?(.*)/', $line['text'], $matches)) {
            if (isset($block['interrupted'])) {
                $block['element']['text'] []= '';
                unset($block['interrupted']);
            }
            $block['element']['text'] []= $matches[1];
            return $block;
        }
        if ( ! isset($block['interrupted'])) {
            $block['element']['text'] []= $line['text'];
            return $block;
        }
        return $block;
    }

    #
    # Rule

    protected function block_rule(array $line): ?array
    {
        if (preg_match('/^(['.$line['text'][0].'])([ ]*\1){2,}[ ]*$/', $line['text'])) {
            return [
                'element' => [
                    'name' => 'hr'
                ],
            ];
        }
        return null;
    }

    #
    # Setext

    protected function block_text_header(array $line, array $block = null): ?array
    {
        if (
            ! isset($block) ||
            isset($block['type']) ||
            isset($block['interrupted'])
        ) {
            return null;
        }
        if (chop($line['text'], $line['text'][0]) === '') {
            $block['element']['name'] = $line['text'][0] === '=' ? 'h1' : 'h2';
            return $block;
        }
        return null;
    }

    #
    # Markup

    protected function block_markup($line): ?array
    {
        if (
            $this->markup_escaped ||
            $this->safe_mode
        ) {
            return null;
        }
        if (preg_match('/^<(\w[\w-]*)(?:[ ]*'.$this->regex_html_attribute.')*[ ]*(\/)?>/', $line['text'], $matches)) {
            $element = strtolower($matches[1]);
            if (in_array($element, $this->text_level_element, true)) {
                return null;
            }
            $block = [
                'name' => $matches[1],
                'depth' => 0,
                'markup' => $line['text'],
            ];
            $length = strlen($matches[0]);
            $remainder = substr($line['text'], $length);
            if (trim($remainder) === '') {
                if (
                    isset($matches[2]) ||
                    in_array($matches[1], $this->void_element, true)
                ) {
                    $block['closed'] = true;
                    $block['void'] = true;
                }
            } else {
                if (
                    isset($matches[2]) ||
                    in_array($matches[1], $this->void_element, true)
                ) {
                    return null;
                }
                if (preg_match('/<\/'.$matches[1].'>[ ]*$/i', $remainder)) {
                    $block['closed'] = true;
                }
            }
            return $block;
        }
        return null;
    }

    protected function block_markup_continue(array $line, array $block): ?array
    {
        if (isset($block['closed'])) {
            return null;
        }
        if (preg_match('/^<'.$block['name'].'(?:[ ]*'.$this->regex_html_attribute.')*[ ]*>/i', $line['text'])){
            # open
            $block['depth'] ++;
        }

        if (preg_match('/(.*?)<\/'.$block['name'].'>[ ]*$/i', $line['text'], $matches)){
            # close
            if ($block['depth'] > 0) {
                $block['depth'] --;
            } else {
                $block['closed'] = true;
            }
        }
        if (isset($block['interrupted'])) {
            $block['markup'] .= "\n";
            unset($block['interrupted']);
        }
        $block['markup'] .= "\n".$line['body'];
        return $block;
    }

    #
    # reference

    protected function block_reference($line): ?array
    {
        if (preg_match('/^\[(.+?)\]:[ ]*<?(\S+?)>?(?:[ ]+["\'(](.+)["\')])?[ ]*$/', $line['text'], $matches)) {
            $id = strtolower($matches[1]);
            $data = [
                'url' => $matches[2],
                'title' => null,
            ];
            if (isset($matches[3])) {
                $data['title'] = $matches[3];
            }
            $this->definition_data['reference'][$id] = $data;
            return [
                'hidden' => true,
            ];
        }
        return null;
    }

    #
    # Table

    protected function block_table($line, array $block = null): ?array
    {
        if (
            ! isset($block) ||
            isset($block['type']) ||
            isset($block['interrupted'])
        ) {
            return null;
        }
        if (
            strpos($block['element']['text'], '|') !== false &&
            chop($line['text'], ' -:|') === ''
        ) {
            $alignments = [];
            $divider = $line['text'];
            $divider = trim($divider);
            $divider = trim($divider, '|');
            $divider_cells = explode('|', $divider);
            foreach ($divider_cells as $divider_cell) {
                $divider_cell = trim($divider_cell);
                if ($divider_cell === '') {
                    continue;
                }
                $alignment = null;
                if ($divider_cell[0] === ':') {
                    $alignment = 'left';
                }
                if (substr($divider_cell, - 1) === ':') {
                    $alignment = $alignment === 'left' ? 'center' : 'right';
                }
                $alignments []= $alignment;
            }
            $header_elements = [];
            $header = $block['element']['text'];
            $header = trim($header);
            $header = trim($header, '|');
            $header_cells = explode('|', $header);
            foreach ($header_cells as $index => $header_cell) {
                $header_cell = trim($header_cell);
                $header_element = [
                    'name' => 'th',
                    'text' => $header_cell,
                    'handler' => 'line',
                ];
                if (isset($alignments[$index])) {
                    $alignment = $alignments[$index];
                    $header_element['attributes'] = [
                        'style' => 'text-align: '.$alignment.';',
                    ];
                }
                $header_elements []= $header_element;
            }
            $block = [
                'alignments' => $alignments,
                'identified' => true,
                'element' => [
                    'name' => 'table',
                    'handler' => 'elements',
                ],
            ];
            $block['element']['text'] []= [
                'name' => 'thead',
                'handler' => 'elements',
            ];
            $block['element']['text'] []= [
                'name' => 'tbody',
                'handler' => 'elements',
                'text' => [],
            ];
            $block['element']['text'][0]['text'] []= [
                'name' => 'tr',
                'handler' => 'elements',
                'text' => $header_elements,
            ];
            return $block;
        }
        return null;
    }

    protected function block_table_continue(array $line, array $block): ?array
    {
        if (isset($block['interrupted'])) {
            return null;
        }
        if (
            $line['text'][0] === '|' ||
            strpos($line['text'], '|')
        ) {
            $elements = [];
            $row = $line['text'];
            $row = trim($row);
            $row = trim($row, '|');
            preg_match_all('/(?:(\\\\[|])|[^|`]|`[^`]+`|`)+/', $row, $matches);
            foreach ($matches[0] as $index => $cell) {
                $cell = trim($cell);
                $element = [
                    'name' => 'td',
                    'handler' => 'line',
                    'text' => $cell,
                ];
                if (isset($block['alignments'][$index])) {
                    $element['attributes'] = [
                        'style' => 'text-align: '.$block['alignments'][$index].';',
                    ];
                }
                $elements []= $element;
            }
            $element = [
                'name' => 'tr',
                'handler' => 'elements',
                'text' => $elements,
            ];
            $block['element']['text'][1]['text'] []= $element;
            return $block;
        }
        return null;
    }
    
    protected function paragraph(array $line): array
    {
        return [
            'element' => [
                'name' => 'p',
                'text' => $line['text'],
                'handler' => 'line',
            ],
        ];
    }
    
    public function line(string $text, array $non_nestable=[]): string
    {
        $markup = '';
        # $excerpt is based on the first occurrence of a marker
        while ($excerpt = strpbrk($text, $this->inline_marker_list)) {
            $marker = $excerpt[0];
            $marker_position = strpos($text, $marker);
            $excerpt_array = ['text' => $excerpt, 'context' => $text];
            foreach ($this->inline_type[$marker] as $inline_type) {
                # check to see if the current inline type is nestable in the current context
                if (
                    ! empty($non_nestable) &&
                    in_array($inline_type, $non_nestable, true)
                ) {
                    continue;
                }
                $inline = $this->{'inline'.$inline_type}($excerpt_array);
                if ( ! isset($inline)) {
                    continue;
                }
                # makes sure that the inline belongs to "our" marker
                if (isset($inline['position']) && $inline['position'] > $marker_position) {
                    continue;
                }
                # sets a default inline position
                if ( ! isset($inline['position'])) {
                    $inline['position'] = $marker_position;
                }
                # cause the new element to 'inherit' our non nestables
                foreach ($non_nestable as $non_nestable_record) {
                    $inline['element']['non_nestables'][] = $non_nestable_record;
                }
                # the text that comes before the inline
                $unmarked_text = substr($text, 0, $inline['position']);
                # compile the unmarked text
                $markup .= $this->unmarked_text($unmarked_text);
                # compile the inline
                $markup .= $inline['markup'] ?? $this->element($inline['element']);
                # remove the examined text
                $text = substr($text, $inline['position'] + $inline['extent']);
                continue 2;
            }
            # the marker does not belong to an inline
            $unmarked_text = substr($text, 0, $marker_position + 1);
            $markup .= $this->unmarked_text($unmarked_text);
            $text = substr($text, $marker_position + 1);
        }
        $markup .= $this->unmarked_text($text);
        return $markup;
    }

    protected function inline_code(array $excerpt): ?array
    {
        $marker = $excerpt['text'][0];
        if (preg_match('/^('.$marker.'+)[ ]*(.+?)[ ]*(?<!'.$marker.')\1(?!'.$marker.')/s', $excerpt['text'], $matches)) {
            $text = $matches[2];
            $text = preg_replace("/[ ]*\n/", ' ', $text);
            return [
                'extent' => strlen($matches[0]),
                'element' => [
                    'name' => 'code',
                    'text' => $text,
                ],
            ];
        }
        return null;
    }

    protected function inline_email_tag(array $excerpt): ?array
    {
        if (
            strpos($excerpt['text'], '>') !== false &&
            preg_match('/^<((mailto:)?\S+?@\S+?)>/i', $excerpt['text'], $matches)
        ) {
            $url = $matches[1];
            if ( ! isset($matches[2])) {
                $url = 'mailto:' . $url;
            }
            return [
                'extent' => strlen($matches[0]),
                'element' => [
                    'name' => 'a',
                    'text' => $matches[1],
                    'attributes' => [
                        'href' => $url,
                    ],
                ],
            ];
        }
        return null;
    }

    protected function inline_emphasis(array $excerpt): ?array
    {
        if ( ! isset($excerpt['text'][1])) {
            return null;
        }
        $marker = $excerpt['text'][0];
        if (
            $excerpt['text'][1] === $marker &&
            preg_match($this->strong_regex[$marker], $excerpt['text'], $matches)
        ) {
            $emphasis = 'strong';
        }
        elseif (preg_match($this->em_regex[$marker], $excerpt['text'], $matches)) {
            $emphasis = 'em';
        } else {
            return null;
        }
        return [
            'extent' => strlen($matches[0]),
            'element' => [
                'name' => $emphasis,
                'handler' => 'line',
                'text' => $matches[1],
            ],
        ];
    }

    protected function inline_escape_sequence(array $excerpt): ?array
    {
        if (
            isset($excerpt['text'][1]) &&
            in_array($excerpt['text'][1], $this->special_characters, true)
        ) {
            return [
                'markup' => $excerpt['text'][1],
                'extent' => 2,
            ];
        }
        return null;
    }

    protected function inline_image(array $excerpt): ?array
    {
        if (
            ! isset($excerpt['text'][1]) ||
            $excerpt['text'][1] !== '['
        ) {
            return null;
        }
        $excerpt['text']= substr($excerpt['text'], 1);
        $Link = $this->inline_link($excerpt);
        if ($Link === null) {
            return null;
        }
        $inline = [
            'extent' => $Link['extent'] + 1,
            'element' => [
                'name' => 'img',
                'attributes' => [
                    'src' => $Link['element']['attributes']['href'],
                    'alt' => $Link['element']['text'],
                ],
            ],
        ];
        $inline['element']['attributes'] += $Link['element']['attributes'];
        unset($inline['element']['attributes']['href']);
        return $inline;
    }

    protected function inline_link(array $excerpt): ?array
    {
        $element = [
            'name' => 'a',
            'handler' => 'line',
            'non_nestables' => ['url', 'link'],
            'text' => null,
            'attributes' => [
                'href' => null,
                'title' => null,
            ],
        ];
        $extent = 0;
        $remainder = $excerpt['text'];
        if (preg_match('/\[((?:[^][]++|(?R))*+)\]/', $remainder, $matches)) {
            $element['text'] = $matches[1];
            $extent += strlen($matches[0]);
            $remainder = substr($remainder, $extent);
        } else {
            return null;
        }
        if (preg_match('/^[(]\s*+((?:[^ ()]++|[(][^ )]+[)])++)(?:[ ]+("[^"]*"|\'[^\']*\'))?\s*[)]/', $remainder, $matches)) {
            $element['attributes']['href'] = $matches[1];
            if (isset($matches[2])) {
                $element['attributes']['title'] = substr($matches[2], 1, - 1);
            }
            $extent += strlen($matches[0]);
        } else {
            if (preg_match('/^\s*\[(.*?)\]/', $remainder, $matches)) {
                $definition = strlen($matches[1]) ? $matches[1] : $element['text'];
                $definition = strtolower($definition);
                $extent += strlen($matches[0]);
            } else {
                $definition = strtolower($element['text']);
            }
            if ( ! isset($this->definition_data['reference'][$definition])) {
                return null;
            }
            $definition_array = $this->definition_data['reference'][$definition];
            $element['attributes']['href'] = $definition_array['url'];
            $element['attributes']['title'] = $definition_array['title'];
        }
        return [
            'extent' => $extent,
            'element' => $element,
        ];
    }

    protected function inline_markup(array $excerpt): ?array
    {
        if (
            $this->markup_escaped ||
            $this->safe_mode ||
            strpos($excerpt['text'], '>') === false
        ) {
            return null;
        }
        if (
            $excerpt['text'][1] === '/' &&
            preg_match('/^<\/\w[\w-]*[ ]*>/s', $excerpt['text'], $matches)
        ) {
            return [
                'markup' => $matches[0],
                'extent' => strlen($matches[0]),
            ];
        }
        if (
            $excerpt['text'][1] === '!' &&
            preg_match('/^<!---?[^>-](?:-?[^-])*-->/s', $excerpt['text'], $matches)
        ) {
            return [
                'markup' => $matches[0],
                'extent' => strlen($matches[0]),
            ];
        }
        if (
            $excerpt['text'][1] !== ' ' &&
            preg_match('/^<\w[\w-]*(?:[ ]*'.$this->regex_html_attribute.')*[ ]*\/?>/s', $excerpt['text'], $matches)
        ) {
            return [
                'markup' => $matches[0],
                'extent' => strlen($matches[0]),
            ];
        }
        return null;
    }

    protected function inline_special_character($excerpt)
    {
        if (
            $excerpt['text'][0] === '&' &&
            ! preg_match('/^&#?\w+;/', $excerpt['text'])
        ) {
            return [
                'markup' => '&amp;',
                'extent' => 1,
            ];
        }
        $special_character = [
            '>' => 'gt',
            '<' => 'lt',
            '"' => 'quot'
        ];
        if (isset($special_character[$excerpt['text'][0]]))
        {
            return [
                'markup' => '&' . $special_character[$excerpt['text'][0]].';',
                'extent' => 1,
            ];
        }
    }

    protected function inline_strikethrough($excerpt)
    {
        if ( ! isset($excerpt['text'][1]))
        {
            return;
        }

        if ($excerpt['text'][1] === '~' && preg_match('/^~~(?=\S)(.+?)(?<=\S)~~/', $excerpt['text'], $matches))
        {
            return array(
                'extent' => strlen($matches[0]),
                'element' => array(
                    'name' => 'del',
                    'text' => $matches[1],
                    'handler' => 'line',
                ),
            );
        }
    }

    protected function inline_url(array $excerpt): ?array
    {
        if (
            $this->url_linked !== true || !
            isset($excerpt['text'][2]) ||
            $excerpt['text'][2] !== '/'
        ) {
            return null;
        }
        if (preg_match('/\bhttps?:[\/]{2}[^\s<]+\b\/*/ui', $excerpt['context'], $matches, PREG_OFFSET_CAPTURE)) {
            $url = $matches[0][0];
            return [
                'extent' => strlen($matches[0][0]),
                'position' => $matches[0][1],
                'element' => [
                    'name' => 'a',
                    'text' => $url,
                    'attributes' => [
                        'href' => $url,
                    ],
                ],
            ];
        }
        return null;
    }

    protected function inline_url_tag(array $excerpt): ?array
    {
        if (
            strpos($excerpt['text'], '>') !== false &&
            preg_match('/^<(\w+:\/{2}[^ >]+)>/i', $excerpt['text'], $matches)
        ) {
            $url = $matches[1];
            return [
                'extent' => strlen($matches[0]),
                'element' => [
                    'name' => 'a',
                    'text' => $url,
                    'attributes' => [
                        'href' => $url,
                    ],
                ],
            ];
        }
        return null;
    }

    # ~

    protected function unmarked_text(string $text): string
    {
        if ($this->breaks_enabled) {
            $text = preg_replace('/[ ]*\n/', "<br />\n", $text);
        } else {
            $text = preg_replace('/(?:[ ][ ]+|[ ]*\\\\)\n/', "<br />\n", $text);
            $text = str_replace(" \n", "\n", $text);
        }
        return $text;
    }

    #
    # Handlers
    #

    protected function element(array $element): string
    {
        if ($this->safe_mode) {
            $element = $this->sanitise_element($element);
        }
        $markup = '<'.$element['name'];

        if (isset($element['attributes'])) {
            foreach ($element['attributes'] as $name => $value) {
                if ($value === null)
                {
                    continue;
                }
                $markup .= ' '.$name.'="'.self::escape($value).'"';
            }
        }
        $permitRawHtml = false;
        if (isset($element['text'])) {
            $text = $element['text'];
        }
        // very strongly consider an alternative if you're writing an
        // extension
        elseif (isset($element['rawHtml'])) {
            $text = $element['rawHtml'];
            $allow_raw_html_in_safe_mode = isset($element['allow_raw_html_in_safe_mode']) && $element['allow_raw_html_in_safe_mode'];
            $permitRawHtml = !$this->safe_mode || $allow_raw_html_in_safe_mode;
        }

        if (isset($text)) {
            $markup .= '>';

            if (!isset($element['non_nestables'])) {
                $element['non_nestables'] = [];
            }
            if (isset($element['handler'])) {
                $markup .= $this->{$element['handler']}($text, $element['non_nestables']);
            }
            elseif (!$permitRawHtml) {
                $markup .= self::escape($text, true);
            }
            else {
                $markup .= $text;
            }
            $markup .= '</'.$element['name'].'>';
        } else {
            $markup .= ' />';
        }
        return $markup;
    }

    protected function elements(array $elements): string
    {
        $markup = '';
        foreach ($elements as $element) {
            $markup .= "\n" . $this->element($element);
        }
        $markup .= "\n";
        return $markup;
    }

    # ~

    protected function li(array $lines): string
    {
        $markup = $this->lines($lines);
        $trimmedMarkup = trim($markup);
        if (
            ! in_array('', $lines, true) &&
            substr($trimmedMarkup, 0, 3) === '<p>'
        ) {
            $markup = $trimmedMarkup;
            $markup = substr($markup, 3);
            $position = strpos($markup, "</p>");
            $markup = substr_replace($markup, '', $position, 4);
        }
        return $markup;
    }

    #
    # Deprecated Methods
    #

    function parse(string $text): string
    {
        $markup = $this->text($text);
        return $markup;
    }

    protected function sanitise_element(array $element): array
    {
        static $good_attribute = '/^[a-zA-Z0-9][a-zA-Z0-9-_]*+$/';
        static $safe_url_name_to_attribute  = [
            'a'   => 'href',
            'img' => 'src',
        ];

        if (isset($safe_url_name_to_attribute[$element['name']])) {
            $element = $this->filter_unsafe_url_in_attribute($element, $safe_url_name_to_attribute[$element['name']]);
        }

        if ( ! empty($element['attributes'])) {
            foreach ($element['attributes'] as $attribute => $val) {
                # filter out badly parsed attribute
                if ( ! preg_match($good_attribute, $attribute)) {
                    unset($element['attributes'][$attribute]);
                }
                # dump onevent attribute
                elseif (self::stri_at_start($attribute, 'on')) {
                    unset($element['attributes'][$attribute]);
                }
            }
        }
        return $element;
    }

    protected function filter_unsafe_url_in_attribute(array $element, string | int $attribute): array
    {
        foreach ($this->safe_link_whitelist as $scheme) {
            if (self::stri_at_start($element['attributes'][$attribute], $scheme)) {
                return $element;
            }
        }
        $element['attributes'][$attribute] = str_replace(':', '%3A', $element['attributes'][$attribute]);
        return $element;
    }

    #
    # Static Methods
    #

    protected static function escape(string $text, bool $allow_quotes = false): string
    {
        return htmlspecialchars($text, $allow_quotes ? ENT_NOQUOTES : ENT_QUOTES, 'UTF-8');
    }

    protected static function stri_at_start(string $string, string $needle): bool
    {
        $len = strlen($needle);
        if ($len > strlen($string)) {
            return false;
        } else {
            return strtolower(substr($string, 0, $len)) === strtolower($needle);
        }
    }

    static function instance($name = 'default')
    {
        if (isset(self::$instances[$name])) {
            return self::$instances[$name];
        }
        $instance = new static();
        self::$instances[$name] = $instance;
        return $instance;
    }
}
