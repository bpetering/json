<?php

mb_internal_encoding( 'UTF-8' );

class Token {
    public $text;
    public $type;

    const BRACE_OPEN = '{';
    const BRACE_CLOSE = '}';
    const SEMI = ':';
    const COMMA = ',';
    const BRACKET_OPEN = '[';
    const BRACKET_CLOSE = ']';
    const BOOL_TRUE = 'true';
    const BOOL_FALSE = 'false';
    const NULL_VALUE = 'null';

    const STRING_QUOTE = '"';
    const MINUS = '-';
    const PERIOD = '.';
    const BACKSLASH = '\\';
    const FORWARDSLASH = '/';

    // Syntax types
    const TYPE_BRACE_OPEN = 1;
    const TYPE_BRACE_CLOSE = 2;
    const TYPE_BRACKET_OPEN = 3;
    const TYPE_BRACKET_CLOSE = 4;
    const TYPE_SEMI = 5;
    const TYPE_COMMA = 6;

    const TYPE_NUMBER = 10;
    const TYPE_STRING = 11;
    const TYPE_BOOL = 12;
    const TYPE_NULL = 13;

    const INVALID = '____invalid____';
    const TYPE_INVALID = 99;    // not a valid token.

    public function __construct( $type, $text ) {
        $this->type = $type;
        $this->text = $text;
    }

    public static function getTypeByText( $text ) {
        switch ( $text ) {
            case self::BRACE_OPEN:
                return self::TYPE_BRACE_OPEN;
            case self::BRACE_CLOSE:
                return self::TYPE_BRACE_CLOSE;
            case self::BRACKET_OPEN:
                return self::TYPE_BRACKET_OPEN;
            case self::BRACKET_CLOSE:
                return self::TYPE_BRACKET_CLOSE;
            case self::SEMI:
                return self::TYPE_SEMI;
            case self::COMMA:
                return self::TYPE_COMMA;
            case self::BOOL_TRUE:
                return self::TYPE_BOOL;
            case self::BOOL_FALSE:
                return self::TYPE_BOOL;
            case self::NULL_VALUE:
                return self::TYPE_NULL;
        }
        if ( self::STRING_QUOTE === mb_substr( $text, 0, 1, 'UTF-8' ) ) {
            return self::TYPE_STRING;
        }
        if ( self::MINUS === mb_substr( $text, 0, 1, 'UTF-8' )
        ||   1 === preg_match( '/\d/', mb_substr( $text, 0, 1, 'UTF-8' ) ) ) {
            return self::TYPE_NUMBER;
        }
        // If nothing returned yet, not valid. self::INVALID can never occur (unless tokenizing bug)
        return self::TYPE_INVALID;
    }

    public function __toString() {
        $r = new ReflectionClass( __CLASS__ );
        $constants = array_flip( $r->getConstants() );
        return $constants[ $this->type ] . " '" . $this->text . "'";
    }
}


