<?php
require_once 'Token.php';

mb_internal_encoding( 'UTF-8' );

class ParsedJSON {
	private $tokens;
	private $token_idx;
	public $text;
	public $data;

	public function __construct( $text_or_object ) {
		$this->dom = null;
		$this->text = $text_or_object;
		$this->tokenize();
		$this->parse();
		//var_dump( $this->data );
	}

	// clarity
	private function text_char( $i ) {
		return mb_substr( $this->text, $i, 1, 'UTF-8' );
	}

	private function tokenize() {
		$this->tokens = [];

		$text_len = mb_strlen( $this->text );
		$i = 0;
		$bool_chars = array_unique( str_split( Token::BOOL_TRUE . Token::BOOL_FALSE ) );
		$null_chars = array_unique( str_split( Token::NULL_VALUE ) );

		while ( $i < $text_len ) {
			// Because we want multibyte/UTF8 strings, we can't use $this->text[ $i ]
			$current_char = $this->text_char( $i );
			
			// Skip whitespace
			if ( " "  === $current_char
			||	 "\t" === $current_char
			||   "\r" === $current_char
			||   "\n" === $current_char
			) {
				$i++;
				continue;
			}

			$syntax_tokens = array(
				Token::BRACE_OPEN,
				Token::BRACE_CLOSE,
				Token::BRACKET_OPEN,
				Token::BRACKET_CLOSE,
				Token::SEMI,
				Token::COMMA,	
			);
			if ( in_array( $current_char, $syntax_tokens, true ) ) {
				array_push( 
					$this->tokens, 
					new Token( Token::getTypeByText( $current_char ), $current_char ) 
				);	
				$i++;
				continue;
			}

			// Below this point $current_char shouldn't be used, 
			// since we're dealing with > 1 character

			// Bool tokens
			if ( $this->text_char( $i ) === Token::BOOL_TRUE[0]
			||	 $this->text_char( $i ) === Token::BOOL_FALSE[0]
			) {
				$token_text = '';
				while ( $i < $text_len 
				&&		in_array( $this->text_char( $i ), $bool_chars, true ) 
				) {
					$token_text .= $this->text_char( $i );
					$i++;	
				}
				if ( Token::BOOL_TRUE === $token_text || Token::BOOL_FALSE === $token_text ) {
					array_push(
						$this->tokens,
						new Token( Token::TYPE_BOOL, $token_text )
					);
				}
				continue;
			}

			// Null token
			if ( $this->text_char( $i ) === Token::NULL_VALUE[0] ) {
				$token_text = '';
				while ( $i < $text_len 
				&&		in_array( $this->text_char( $i ), $null_chars, true ) 
				) {
					$token_text .= $this->text_char( $i );
					$i++;	
				}
				if ( Token::NULL_VALUE === $token_text ) {
					array_push(
						$this->tokens,
						new Token( Token::TYPE_NULL, $token_text )
					);
				}
				continue;
			}

			// String tokens
			if ( Token::STRING_QUOTE === $this->text_char( $i ) ) {
				$token_text = Token::STRING_QUOTE;
				$i++;

				// Because of e.g. "\\", it's easy to get this wrong. We can't
				// check the previous character to determine if loop ends - instead, 
				// each escape "consumes" the next character.
				while ( $i < $text_len ) {
					switch ( $this->text_char( $i ) ) {
						case Token::STRING_QUOTE:
							// This must be end of string - escaped " would be consumed
							break 2;		// end while
						case Token::BACKSLASH:
							$token_text .= $this->text_char( $i );
							$i++;
							// Consume next character. This handles \f \r \n \t \\ \/ \" -
							// strictly speaking, it mishandles \u, but the overall
							// token_text ends up correct.
							// Deliberate fallthrough to consume next character.
						default:
							$token_text .= $this->text_char( $i );
							$i++;
							break;
					}
				}

				$token_text .= Token::STRING_QUOTE;
				$i++;
				array_push( 
					$this->tokens, 
					new Token( Token::TYPE_STRING, $token_text )
				);
				continue;
			}

			// Number tokens
			if ( Token::MINUS === $this->text_char( $i )
			||	 1 === preg_match( '/\d/', $this->text_char( $i ) ) 
			) {
				$token_text = $this->text_char( $i );
				$i++;
				while ( $i < $text_len 
				&&		1 === preg_match( '/-|\+|\d|\.|e|E/', $this->text_char( $i ) ) 
				) {
					$token_text .= $this->text_char( $i );
					$i++;
				}
				array_push( 
					$this->tokens, 
					new Token( Token::TYPE_NUMBER, $token_text )
				);
				// note: no increment
				continue;
			}
		}

		$this->token_idx = 0;
		$this->num_tokens = count( $this->tokens );

		//echo "num tokens: " . $this->num_tokens . "\n";
		//echo "tokens: \n";
		//print_r($this->tokens);

	}

	public function next_token() {
		if ( is_null( $this->tokens ) ) {
			$this->tokenize();			
		}	
		if ( $this->token_idx < $this->num_tokens ) {
			// Even though we can't index text characters, because of multibyte issues,
			// we can index tokens
			$ret = $this->tokens[ $this->token_idx ];
			$this->token_idx++;
			return $ret;
		} else {
			return null;
		}
	}

	private function trim_quotes_outermost( $s ) {
		if ( Token::STRING_QUOTE === mb_substr( $s, 0, 1, 'UTF-8' ) ) {
			$start = 1;
		} else { 
			$start = 0;
		}
		$s_len = mb_strlen( $s );
		if ( Token::STRING_QUOTE === mb_substr( $s, $s_len - 1, 1, 'UTF-8' ) ) {
			$length = $s_len - 1 - $start;
		}
		return mb_substr( $s, $start, $length, 'UTF-8' );	
	}

	public function process_string( $s ) {
		// Trim quotes and process escapes
		$trimmed = $this->trim_quotes_outermost( $s );
		$trimmed_len = mb_strlen( $trimmed );
		$escape_evaled = '';

		for ( $i = 0; $i < $trimmed_len; $i++ ) {
			if ( $i < $trimmed_len - 1 && Token::BACKSLASH === mb_substr( $trimmed, $i, 1, 'UTF-8' ) ) {
				switch ( mb_substr( $trimmed, $i + 1, 1, 'UTF-8' ) ) {
					case Token::BACKSLASH:
					case Token::FORWARDSLASH:
					case Token::STRING_QUOTE:
						// deliberate fall-through
						$escape_evaled .= mb_substr( $trimmed, $i + 1, 1, 'UTF-8' );
						$i++;
						break;
					// These escapes are useable in PHP
					case 'f':
						$escape_evaled .= "\f";
						$i++;
						break;
					case 'n':
						$escape_evaled .= "\n";
						$i++;
						break;
					case 'r':
						$escape_evaled .= "\r";
						$i++;
						break;
					case 't':
						$escape_evaled .= "\t";
						$i++;
						break;
					// \b needs special handling
					case 'b':
						$escape_evaled .= 0x08;		// backspace
						$i++;
						break;
					// Unicode escapes: convert next 4 hex characters to integer
					// codepoint and use that Unicode character
					case 'u':
						// XXYY is converted to a pair of bytes (XX, YY),
						// this is then converted to an integer with XX being the high byte
						if ( $i < $trimmed_len - 5 ) {
							$cp = base_convert( 
								mb_substr( $trimmed, $i + 2, 1, 'UTF-8' ) 
							  . mb_substr( $trimmed, $i + 3, 1, 'UTF-8' ),
							    16, 
								10 
							);
							$cp <<= 8;
							$cp |= base_convert( 
								mb_substr( $trimmed, $i + 4, 1, 'UTF-8' ) 
							  . mb_substr( $trimmed, $i + 5, 1, 'UTF-8' ),
								16, 
								10 
							);	
							// Convert codepoint to actual character
							$escape_evaled .= mb_chr( $cp, 'UTF-8' );
						}
						$i += 5;
						break;
				}	
			} else {
				$escape_evaled .= mb_substr( $trimmed, $i, 1, 'UTF-8' );
			}
		}
		return $escape_evaled;
	}

	public function process_number( $s ) {
		$adjust_sign_mult = 1;
		if ( Token::MINUS === mb_substr( $s, 0, 1, 'UTF-8' ) ) {
			$adjust_sign_mult = -1;
		}
		if ( false !== mb_strpos( $s, Token::PERIOD ) ) {
			return $adjust_sign_mult * (float) $s;
		} else {
			return $adjust_sign_mult * (int) $s;
		}
	}

	public function parse() {
		if ( ! $this->tokens ) {
			$this->tokenize();
		}
		$this->data = $this->parse_json();
	}

	public function parse_json() {
		return $this->parse_element();
	}

	public function parse_element() {
		// Whitespace is handled by tokeniser
		$t = $this->next_token();

		switch ( $t->type ) {
			case Token::TYPE_BRACE_OPEN:
				return $this->parse_object();
			case Token::TYPE_BRACKET_OPEN:
				return $this->parse_array();
			// Scalar values are acceptable JSON top-level objects
			case Token::TYPE_STRING:
				return $this->process_string( $t->text );
			case Token::TYPE_NUMBER:
				return $this->process_number( $t->text );
			case Token::TYPE_BOOL:
				return Token::BOOL_TRUE === $t->text ? true : false;
			case Token::TYPE_NULL:
				return null;
			default:
				// TODO eh
				break;
		}
	}

	public function parse_object() {
		$o = [];

		while ( $t = $this->next_token() ) {
			if ( Token::TYPE_BRACE_CLOSE === $t->type ) {
				break;
			}
			if ( Token::TYPE_STRING === $t->type ) {
				// Handle a key and its value
				$key = $this->process_string( $t->text );
				$expect_semi = $this->next_token();
				if ( ! $expect_semi || Token::TYPE_SEMI !== $expect_semi->type ) {
					return null; // TODO eh
				}
				$value_token = $this->next_token();
				if ( ! $value_token ) {
					return null; // TODO eh
				}
				switch ( $value_token->type ) {
					case Token::TYPE_BRACE_OPEN:
						$o[ $key ] = $this->parse_object();
						break;
					case Token::TYPE_BRACKET_OPEN:
						$o[ $key ] = $this->parse_array(); 
						break;						
					case Token::TYPE_STRING:
						$o[ $key ] = $this->process_string( $value_token->text );
						break;
					case Token::TYPE_NUMBER:
						$o[ $key ] = $this->process_number( $value_token->text );
						break;
					case Token::TYPE_BOOL:
						$o[ $key ] = Token::BOOL_TRUE === $value_token->text ? true : false;
						break;
					case Token::TYPE_NULL:
						$o[ $key ] = null;
						break;
				}
			}
		}	
		return $o;
	}

	public function parse_array() {
		$a = [];

		while ( $t = $this->next_token() ) {
			if ( Token::TYPE_BRACKET_CLOSE === $t->type ) {
				break;
			}
			switch ( $t->type ) {
				case Token::TYPE_BRACE_OPEN:
					$a[] = $this->parse_object();
					break;
				case Token::TYPE_BRACKET_OPEN:
					$a[] = $this->parse_array();
					break;
				case Token::TYPE_STRING:
					$a[] = $this->process_string( $t->text );
					break;
				case Token::TYPE_NUMBER:
					$a[] = $this->process_number( $t->text );
					break;
				case Token::TYPE_BOOL:
					$a[] = Token::BOOL_TRUE === $t->text ? true : false;
					break;
				case Token::TYPE_NULL:
					$a[] = null;
					break;
			}
		}	
		return $a;		
	}
}

// $p = new ParsedJSON( $argv[1] );


