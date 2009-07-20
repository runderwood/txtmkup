<?php

define(TXTMKUP_STA, 'start');
define(TXTMKUP_END, 'end');

define(TXTMKUP_HDR, 'header');
define(TXTMKUP_PARA, 'paragraph');

define(TXTMKUP_SUPER, 'superscript');
define(TXTMKUP_FOOT, 'foot');
define(TXTMKUP_FOOTNOTE, 'footnote');
define(TXTMKUP_FNLINK, 'footnote-link');

define(TXTMKUP_STRONG, 'strong');
define(TXTMKUP_CITE, 'cite');

function txtmkup($str) {
    return txtmkup_mkup(txtmkup_scan($str));
}

function txtmkup_mkup($stack) {
    $str = '';
    $in_foot = false;
    $bstack = array();
    while(($st = array_shift($stack)) !== null) {
        if(is_array($st)) {
            list($t, $ev) = $st;
            switch($t) {
                case TXTMKUP_HDR:
                    switch($ev) {
                        case TXTMKUP_END:
                            $str .= '<h'.$st[2].'>'.join('', $bstack).'</h'.$st[2].'>'."\n";
                            $bstack = array();
                            break;
                    }
                    break;
                case TXTMKUP_PARA:
                    switch($ev) {
                        case TXTMKUP_STA:
                            $str .= '<p>';
                            break;
                        case TXTMKUP_END:
                            $str .= join('', $bstack)."</p>\n";
                            $bstack = array();
                            break;
                    }
                    break;
                case TXTMKUP_SUPER:
                    if($in_foot) {
                        switch($ev) {
                            case TXTMKUP_STA:
                                $bstack[] = '<div class="fn" id="#fn-';
                                break;
                            case TXTMKUP_END:
                                $fnid = array_pop($bstack);
                                $bstack[] = $fnid.'"><span class="fn-label">'.$fnid.'</span>';
                                break;
                        }
                    } else {
                        switch($ev) {
                            case TXTMKUP_STA:
                                $bstack[] = '[<a href="#fn-';
                                break;
                            case TXTMKUP_END:
                                $fnid = array_pop($bstack);
                                $bstack[] = $fnid.'">'.$fnid.'</a>]';
                                break;
                        }
                    }
                    break;
                case TXTMKUP_FOOT:
                    switch($ev) {
                        case TXTMKUP_STA:
                            $bstack[] = '<div class="foot">'."\n";
                            $in_foot = true;
                            break;
                        case TXTMKUP_END:
                            $bstack[] = '</div>';
                            $in_foot = false;
                            break;
                    }
                    break;
                case TXTMKUP_FOOTNOTE:
                    switch($ev) {
                        case TXTMKUP_END:
                            $fn = array_pop($bstack);
                            $bstack[] = rtrim($fn).'</div>'."\n";
                            break;
                    }
                    break;
                case TXTMKUP_FNLINK:
                    switch($ev) {
                        case TXTMKUP_STA:
                            $bstack[] = '(<a href="';
                            break;
                        case TXTMKUP_END:
                            $url = array_pop($bstack);
                            $bstack[] = $url.'">'.$url.'</a>)';
                            break;
                    }
                default:
                    break;
            }
        } else {
            $bstack[] = $st;
        }
    }
    $str .= join('', $bstack);
    return $str;
}

function txtmkup_scan($str) {
    $buf = '';
    $prev = '';
    $stack = array();
    $state = array();
    $hdrlvl = 0;
    for($i=0; $i<strlen($str); $c=$str[$i++]) {
        if($c === null) continue;
        switch($c) {
            case '<':
                $buf .= '&lt;';
                break;
            case '>':
                $buf .= '&rt;';
                break;
            case '&':
                $buf .= '&amp;';
                break;
            case "\n":
                if($prev === "\n" && $state && $state[0] === TXTMKUP_PARA) {
                    array_push($stack, 
                        rtrim($buf), 
                        array(TXTMKUP_PARA, TXTMKUP_END)
                    );
                    array_shift($state);
                    $buf = '';
                } elseif(substr($buf, strlen($buf)-3) === '---') {
                    while(is_array($stack[count($stack)-1]) && $stack[count($stack)-1][1] === TXTMKUP_STA) {
                        array_pop($stack);
                    }
                    $state = array();
                    array_unshift($state, TXTMKUP_FOOT);
                    array_push($stack, 
                        substr($buf, 0, strlen($buf)-3),
                        array(TXTMKUP_FOOT, TXTMKUP_STA)
                    );
                    $buf = '';
                } elseif($state && $state[0] === TXTMKUP_FOOTNOTE 
                    && $buf && substr($buf, strlen($buf)-1) === "\n") {
                    if($buf) array_push($stack, $buf);
                    array_push($stack, array(TXTMKUP_FOOTNOTE, TXTMKUP_END));
                    $buf = '';
                    array_shift($state);
                } elseif($state && $state[0] === TXTMKUP_HDR) {
                    array_push($stack, $buf, array(TXTMKUP_HDR, TXTMKUP_END, $hdrlvl));
                    $buf = '';
                    array_shift($state);
                } else {
                    $buf .= $c;
                }
                break;
            case '[':
                array_push($stack, $buf, array(TXTMKUP_SUPER, TXTMKUP_STA));
                array_unshift($state, TXTMKUP_SUPER);
                $buf = '';
                break;
            case ']':
                array_push($stack,
                    $buf,
                    array(TXTMKUP_SUPER, TXTMKUP_END)
                );
                $buf = '';
                array_shift($state);
                if($state && $state[0] === TXTMKUP_FOOT) {
                    array_unshift($state, TXTMKUP_FOOTNOTE);   
                }
                break;
            case '(':
                if($state && $state[0] === TXTMKUP_FOOTNOTE) {
                    array_push($stack,
                        $buf,
                        array(TXTMKUP_FNLINK, TXTMKUP_STA)
                    );
                    array_unshift($state, TXTMKUP_FNLINK);
                    $buf = '';
                }
                break;
            case ')':
                if($state && $state[0] === TXTMKUP_FNLINK) {
                    array_push($stack,
                        $buf,
                        array(TXTMKUP_FNLINK, TXTMKUP_END)
                    );
                    array_shift($state);
                    $buf = '';
                }
                break;
            case '#':
                if($prev === "\n" || !$prev) {
                    $hdrlvl = 1;
                    array_push($stack, $buf, array(TXTMKUP_HDR, TXTMKUP_STA));
                    array_unshift($state, TXTMKUP_HDR);
                } elseif($prev === '#' && $state && $state[0] === TXTMKUP_HDR) {
                    $hdrlvl++;
                }
                break;
            default:
                if((!$prev || $prev === "\n") && (!$state || $state[0] != TXTMKUP_PARA)) {
                    if($buf) array_push($stack, $buf);
                    array_push($stack, array(TXTMKUP_PARA, TXTMKUP_STA));
                    $buf = '';
                    array_unshift($state, TXTMKUP_PARA);
                }
                $buf .= $c;
                break;
        }
        $prev = $c;
    }
    if($buf) array_push($stack, $buf);
    if($state) {
        while($state) {
            $st = array_shift($state);
            array_push($stack, array($st, TXTMKUP_END));
        }
    }
    return $stack;
}

if(php_sapi_name() === 'cli') {
    $input = file_get_contents('php://stdin');
    echo txtmkup($input);
}
