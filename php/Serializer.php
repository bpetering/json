<?php
require_once 'Token.php';

mb_internal_encoding( 'UTF-8' );

class Serializer {
	public $serialized;
	public $from;

	public function __construct( $from ) {
		$this->from = $from;
		$this->max_depth = 2**20;
		$this->serialize();
	}

	public function serialize_int( $arg ) {
		return (string) $arg;
	}

	public function serialize_float( $arg ) {
		return (float) $arg;
	}

	public function serialize_bool( $arg ) {
		if ( $arg ) {
			return Token::BOOL_TRUE;
		} else {
			return Token::BOOL_FALSE;
		}
	}

	public function serialize_string( $arg ) {
		$out = Token::STRING_QUOTE;
		for ( $i = 0; $i < mb_strlen( $arg, 'UTF-8' ); $i++ ) {
			$char = mb_substr( $arg, $i, 1, 'UTF-8' );
			if ( in_array( $char, [ Token::BACKSLASH, Token::FORWARDSLASH, Token::STRING_QUOTE ], true ) ) {
				$out .= '\\' . $char;
				continue;
			}
			if ( "\f" === $char ) {
				$out .= '\\f';
				continue;
			} 
			if ( "\n" === $char ) {
				$out .= '\\n';
				continue;
			}
			if ( "\r" === $char ) {
				$out .= '\\r';
				continue;
			}
			if ( "\t" === $char ) {
				$out .= '\\t';
				continue;
			}
			if ( 0x08 === $char ) {
				$out .= '\\b';
				continue;
			}
			// TODO utf8 codepoints < 0xffff, > 0xffff (e.g. 0x10112)
			$out .= $char;	
		}		
		$out .= Token::STRING_QUOTE;
		return $out;
	}

	// Serialize a list, not a PHP array. Called _array so it matches corresponding
	// Parser _array() method and the grammar.
	public function serialize_array( $a, $depth ) {
		if ( $depth > $this->max_depth ) {
			// TODO error
		}

		$out = Token::BRACKET_OPEN;
		$first = true;
		foreach ( $a as $val ) {
			if ( $first ) {
				$first = false;
			} else {
				$out .= Token::COMMA;
			}
			$out .= $this->serialize_value( $val, $depth );
		}
		$out .= Token::BRACKET_CLOSE;
		return $out;
	}

	private function _array_is_list( $a ) {
		if ( function_exists( 'array_is_list' ) ) {		// PHP 8.1.0 and above
			return array_is_list( $a );
		} else {
			$keys = array_keys( $a );
			for ( $i = 0; $i < count( $keys ); $i++ ) {
				if ( ! is_int( $keys[ $i ] ) 
				||	 $i !== $keys[ $i ]
				) {
					return false;
				}
			}
			return true;
		}
	}

	public function serialize_object( $arg, $depth ) {
		if ( $depth > $this->max_depth ) {
			// TODO error
		}

		$out = Token::BRACE_OPEN;

		if ( is_object( $arg ) ) {
			$vars = get_object_vars( $arg );
		} else {
			$vars = $arg;
		}
		foreach ( $vars as $key => $val ) {
			$out .= Token::STRING_QUOTE . $this->serialize_string( $key ) . Token::STRING_QUOTE;
			$out .= Token::COLON;
			$out .= $this->serialize_value( $val, $depth );
		}

		$out .= Token::BRACE_CLOSE;
	}

	public function serialize_value( $arg, $depth = 1 ) {
		if ( is_null( $arg ) ) {
			return Token::NULL_VALUE;
		}
		if ( is_int( $arg ) ) {
			return $this->serialize_int( $arg );
		}
		if ( is_float( $arg ) ) {
			return $this->serialize_float( $arg );	
		}
		if ( is_bool( $arg ) ) {
			return $this->serialize_bool( $arg );
		}
		if ( is_string( $arg ) ) {
			return $this->serialize_string( $arg );
		}
		if ( is_array( $arg ) ) {
			// This doesn't handle circular references (e.g. it'll crash).

			// PHP arrays are not necessarily JSON arrays (they're sometimes objects). If they're
			// not convertible to JSON arrays, convert to objects (losing ordering information).
			if ( $this->_array_is_list( $arg ) ) {
				return $this->serialize_array( $arg, $depth + 1 );
			} else {
				return $this->serialize_object( $arg, $depth + 1 );
			}
		}
		if ( is_object( $arg ) ) {
			return $this->serialize_object( $arg, $depth + 1 );
		}
		if ( is_resource( $arg ) ) {
			// TODO errors
		}
	}

	// Symmetry with Parser
	public function serialize() {
		$this->serialized = $this->serialize_value( $this->from );
	}
}
