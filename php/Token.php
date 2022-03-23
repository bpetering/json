<?php

mb_internal_encoding( 'UTF-8' );

class Token {

	public $text;
	public $type;

	const BRACE_OPEN = '{';
	const BRACE_CLOSE = '}';
	const BRACKET_OPEN = '[';
	const BRACKET_CLOSE = ']';
	const COLON = ':';
	const COMMA = ',';
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
	const TYPE_COLON = 5;
	const TYPE_COMMA = 6;

	const TYPE_STRING = 10;
	const TYPE_NUMBER = 11;
	const TYPE_BOOL = 12;
	const TYPE_NULL = 13;

	const INVALID = '____invalid____';
	const TYPE_INVALID = 99;	// not a valid token.

	const DIGITS = [ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ];
	const NUM_NON_DIGITS = [ '-', '+', '.', 'e', 'E' ];

	// Used to provide better error messages
	const ELEM_FIRST_TYPES = [ 
		self::TYPE_BRACE_OPEN, 
		self::TYPE_BRACKET_OPEN, 
		self::TYPE_STRING,
		self::TYPE_NUMBER,
		self::TYPE_BOOL,
		self::TYPE_NULL
	];

	public function __construct( $type, $text ) {
		$this->type = $type;
		$this->text = $text;
	}

	public static function getTypeByText( $text ) {
		if ( self::BRACE_OPEN === $text ) {
			return self::TYPE_BRACE_OPEN;
		}
		if ( self::BRACE_CLOSE === $text ) {
			return self::TYPE_BRACE_CLOSE;
		}
		if ( self::BRACKET_OPEN === $text ) {
			return self::TYPE_BRACKET_OPEN;
		}
		if ( self::BRACKET_CLOSE === $text ) {
			return self::TYPE_BRACKET_CLOSE;
		}
		if ( self::COLON === $text ) {
			return self::TYPE_COLON;
		}
		if ( self::COMMA === $text ) {
			return self::TYPE_COMMA;
		}
		if ( self::BOOL_TRUE === $text ) {
			return self::TYPE_BOOL;
		}
		if ( self::BOOL_FALSE === $text ) {
			return self::TYPE_BOOL;
		}
		if ( self::NULL_VALUE === $text ) {
			return self::TYPE_NULL;
		}

		if ( self::STRING_QUOTE === mb_substr( $text, 0, 1, 'UTF-8' ) ) {
			return self::TYPE_STRING;
		}
		if ( self::MINUS === mb_substr( $text, 0, 1, 'UTF-8' )
		||	 in_array( mb_substr( $text, 0, 1, 'UTF-8' ), self::DIGITS, true ) 
		) {
			return self::TYPE_NUMBER;
		}
		// If nothing returned yet, not valid. self::INVALID can never occur (unless tokenizing bug)
		return self::TYPE_INVALID;
	}

	public function __toString() {
		$r = new ReflectionClass( __CLASS__ );
		$constants = $r->getConstants();

		// Avoid a warning about array_flip() / strings and integers
		unset( $constants['BOOL_TRUE'] );	
		unset( $constants['BOOL_FALSE'] );	
		unset( $constants['NULL_VALUE'] );	
		unset( $constants['DIGITS'] );	
		unset( $constants['NUM_NON_DIGITS'] );	
		unset( $constants['ELEM_FIRST_TYPES'] );	

		$constants = array_flip( $constants );
		return $constants[ $this->type ] . " '" . $this->text . "'";
	}
}

