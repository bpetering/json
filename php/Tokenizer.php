<?php
mb_internal_encoding( 'UTF-8' );

require_once 'Token.php';
require_once 'ParserError.php';

class Tokenizer implements ArrayAccess {

	public $tokens;
	public $text;
	private $token_idx;
	private $num_tokens;
	
	public function __construct( $text ) {
		$this->text = $text;
		$this->tokenize();
	}

    // clarity
    private function textChar( $i ) { 
        return mb_substr( $this->text, $i, 1, 'UTF-8' );
    }

	// Return current token (last returned), surrounded by up to $contextWidth
	// tokens surrounding it
	public function context( $contextWidth = 2 ) {
		$ctx = [];
		// Last returned is token_idx - 1
		for ( $i = $this->token_idx - 1 - $contextWidth; $i < $this->token_idx + 2; $i++ ) {
			if ( isset( $this->tokens[ $i ] ) ) {
				$ctx[] = $this->tokens[ $i ];
			}
		}
		return $ctx;
	}

    public function nextToken() { 
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

    public function tokenize() {
        $this->tokens = [];

        $text_len = mb_strlen( $this->text, 'UTF-8');
        $i = 0;
        $bool_chars = array_unique( str_split( Token::BOOL_TRUE . Token::BOOL_FALSE ) );
        $null_chars = array_unique( str_split( Token::NULL_VALUE ) );

        while ( $i < $text_len ) {
            // Because we want multibyte/UTF8 strings, we can't use $this->text[ $i ]
            $current_char = $this->textChar( $i );

            // Skip whitespace
            if ( " "  === $current_char
            ||   "\t" === $current_char
            ||   "\r" === $current_char
            ||   "\n" === $current_char
            ) {
                $i++;
                continue;
            }

            $syntax_tokens = [
                Token::BRACE_OPEN,
                Token::BRACE_CLOSE,
                Token::BRACKET_OPEN,
                Token::BRACKET_CLOSE,
                Token::COLON,
                Token::COMMA,   
            ];
            if ( in_array( $current_char, $syntax_tokens, true ) ) {
                $this->tokens[] = new Token( Token::getTypeByText( $current_char ), $current_char );
                $i++;
                continue;
            }

            // Below this point $current_char shouldn't be used, 
            // since we're dealing with > 1 character

            // Bool tokens
            if ( $this->textChar( $i ) === Token::BOOL_TRUE[0]
            ||   $this->textChar( $i ) === Token::BOOL_FALSE[0]
            ) {
                $token_text = '';
                while ( $i < $text_len 
                &&      in_array( $this->textChar( $i ), $bool_chars, true ) 
                ) {
                    $token_text .= $this->textChar( $i );
                    $i++;   
                }
                if ( Token::BOOL_TRUE === $token_text || Token::BOOL_FALSE === $token_text ) {
                    $this->tokens[] = new Token( Token::TYPE_BOOL, $token_text );
                }
                continue;
            }

            // Null token
            if ( $this->textChar( $i ) === Token::NULL_VALUE[0] ) {
                $token_text = '';
                while ( $i < $text_len 
                &&      in_array( $this->textChar( $i ), $null_chars, true ) 
                ) {
                    $token_text .= $this->textChar( $i );
                    $i++;   
                }
                if ( Token::NULL_VALUE === $token_text ) {
                    $this->tokens[] = new Token( Token::TYPE_NULL, $token_text );
                }
                continue;
			}

            // String tokens
            if ( Token::STRING_QUOTE === $this->textChar( $i ) ) {
                $token_text = Token::STRING_QUOTE;
                $i++;

                // Because of e.g. "\\", it's easy to get this wrong. We can't
                // check the previous character to determine if loop ends - instead, 
                // each escape "consumes" the next character.
                while ( $i < $text_len ) {
                    if ( Token::STRING_QUOTE === $this->textChar( $i ) ) {
                        // This must be end of string - escaped " would be consumed
                        break;
                    }
                    if ( Token::BACKSLASH === $this->textChar( $i ) ) {
                        $token_text .= $this->textChar( $i );
                        $i++;
                        // Consume next character. This handles \f \r \n \t \\ \/ \" -
                        // strictly speaking, it mishandles \u, but the overall
                        // token_text ends up correct.
                    }
                    $token_text .= $this->textChar( $i );
                    $i++;
                }

                $token_text .= Token::STRING_QUOTE;
				$this->tokens[] = new Token( Token::TYPE_STRING, $token_text );
                $i++;
                continue;
            }

            // Number tokens
            if ( Token::MINUS === $this->textChar( $i )
			||   in_array( $this->textChar( $i ), Token::DIGITS, true ) 
            ) {
                $token_text = $this->textChar( $i );
                $i++;
                while ( $i < $text_len 
				&&		(
							in_array( $this->textChar( $i ), Token::DIGITS, true )
							||
							in_array( $this->textChar( $i ), Token::NUM_NON_DIGITS, true )
						)
                ) {
                    $token_text .= $this->textChar( $i );
                    $i++;
                }
				$this->tokens[] = new Token( Token::TYPE_NUMBER, $token_text );
                // note: no increment
                continue;
            }

			throw new ParserError("Unprocessed character - i = $i, char=$current_char");

			$i++;
        }

        $this->token_idx = 0;
        $this->num_tokens = count( $this->tokens );
    }

	public function offsetExists( $offset ) {
		if ( is_null( $this->tokens ) ) {
			$this->tokenize();
		}
		return isset( $this->tokens[ $offset ] );
	}

	public function offsetGet( $offset ) {
		return isset( $this->tokens[ $offset ] ) ? $this->tokens[ $offset ] : null;
	}

	public function offsetSet( $offset, $value ) {
		if ( is_null( $offset ) ) {
			$this->tokens[] = $value;
		} else {
			$this->tokens[ $offset ] = $value;
		}
	}

	public function offsetUnset( $offset ) {
		unset( $this->tokens[ $offset ] );
	}
}

//$t = new Tokenizer( file_get_contents( $argv[1] ) );

