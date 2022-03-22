<?php
mb_internal_encoding( 'UTF-8' );

require_once 'Token.php';
require_once 'Tokenizer.php';

class Parser {

	public $tokenizer;
	public $text;
	public $data;

	public function __construct( $text ) {
		$this->text = $text;
		$this->tokenizer = new Tokenizer( $text );
		$this->parse();
		// var_dump( $this->data );
	}

	private function trimQuotesOutermost( $s ) {
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

	private function processString( $s ) {
		// Trim quotes and process escapes
		$trimmed = $this->trimQuotesOutermost( $s );
		$trimmed_len = mb_strlen( $trimmed );
		$escape_evaled = '';

		for ( $i = 0; $i < $trimmed_len; $i++ ) {
			if ( $i < $trimmed_len - 1 && Token::BACKSLASH === mb_substr( $trimmed, $i, 1, 'UTF-8' ) ) {
				$escape = mb_substr( $trimmed, $i + 1, 1, 'UTF-8' );

				if ( in_array( $escape, 
					 [ Token::BACKSLASH, Token::FORWARDSLASH, Token::STRING_QUOTE ], 
					 true ) 
				) {
					// Copy these characters like normal - the eval'ed version is the character itself
					$escape_evaled .= $escape;
					$i++;
				}
				// PHP evaluates these escapes if they're included in strings
				if ( 'f' === $escape ) {
					$escape_evaled .= "\f";
					$i++;
				}
				if ( 'n' === $escape ) {
					$escape_evaled .= "\n";
					$i++;
				}
				if ( 'r' === $escape ) {
					$escape_evaled .= "\r";
					$i++;
				}
				if ( 't' === $escape ) {
					$escape_evaled .= "\t";
					$i++;
				}

				// \b needs special handling - PHP doesn't include it but JSON does
				if ( 'b' === $escape ) {
					$escape_evaled .= 0x08;		// backspace
					$i++;
				}

				if ( 'u' === $escape ) {
					if ( $i < $trimmed_len - 5 ) {
						// Get hex digits and convert them to integer codepoint
						$cp = hexdec( mb_substr( $trimmed, $i + 2, 4, 'UTF-8' ) );
						// Convert codepoint to encoded character
						$escape_evaled .= mb_chr( $cp, 'UTF-8' );
						$i += 5;
					}
				}

			} else {
				$escape_evaled .= mb_substr( $trimmed, $i, 1, 'UTF-8' );
			}
		}
		return $escape_evaled;
	}

	public function processNumber( $s ) {
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
		$this->data = $this->parseJson();
	}

	public function parseJson() {
		return $this->parseElement();
	}

	public function parseElement() {
		// Whitespace is handled by tokeniser
		$t = $this->tokenizer->nextToken();

		if ( Token::TYPE_BRACE_OPEN === $t->type ) {
			return $this->parseObject();
		}
		if ( Token::TYPE_BRACKET_OPEN === $t->type ) {
			return $this->parseArray();
		}

		// Scalar values are acceptable JSON top-level objects
		if ( Token::TYPE_STRING === $t->type ) {
			return $this->processString( $t->text );
		}
		if ( Token::TYPE_NUMBER === $t->type ) {
			return $this->processNumber( $t->text );
		}
		if ( Token::TYPE_BOOL === $t->type ) {
			return Token::BOOL_TRUE === $t->text ? true : false;
		}
		if ( Token::TYPE_NULL === $t->type ) {
			return null;
		}
	}

	public function parseObject() {
		$o = [];

		while ( $t = $this->tokenizer->nextToken() ) {
			if ( Token::TYPE_BRACE_CLOSE === $t->type ) {
				break;
			}
			if ( Token::TYPE_STRING === $t->type ) {
				// Handle a key and its value
				$key = $this->processString( $t->text );
				$expect_colon = $this->tokenizer->nextToken();
				if ( ! $expect_colon || Token::TYPE_COLON !== $expect_colon->type ) {
					return null; // TODO eh
				}
				$value_token = $this->tokenizer->nextToken();
				if ( ! $value_token ) {
					return null; // TODO eh
				}
				switch ( $value_token->type ) {
					case Token::TYPE_BRACE_OPEN:
						$o[ $key ] = $this->parseObject();
						break;
					case Token::TYPE_BRACKET_OPEN:
						$o[ $key ] = $this->parseArray(); 
						break;						
					case Token::TYPE_STRING:
						$o[ $key ] = $this->processString( $value_token->text );
						break;
					case Token::TYPE_NUMBER:
						$o[ $key ] = $this->processNumber( $value_token->text );
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

	public function parseArray() {
		$a = [];

		while ( $t = $this->tokenizer->nextToken() ) {
			if ( Token::TYPE_BRACKET_CLOSE === $t->type ) {
				break;
			}
			switch ( $t->type ) {
				case Token::TYPE_BRACE_OPEN:
					$a[] = $this->parseObject();
					break;
				case Token::TYPE_BRACKET_OPEN:
					$a[] = $this->parseArray();
					break;
				case Token::TYPE_STRING:
					$a[] = $this->processString( $t->text );
					break;
				case Token::TYPE_NUMBER:
					$a[] = $this->processNumber( $t->text );
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

// $p = new Parser( $argv[1] );


