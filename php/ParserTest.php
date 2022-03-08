<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

mb_internal_encoding( 'UTF-8' );

// in ParserTest.php

require 'Parser.php';

final class ParserTest extends TestCase {
    public function testParseArrayInsideObject() : void {
        $p = new Parser( '{ "key": [] }' );
		$this->assertEquals( [ 'key' => [] ], $p->data, "array inside object" );
    }

	public function testEscapeQuote() : void {
		$p = new Parser( '"\""' );	// perverse, but valid
		$this->assertEquals( '"', $p->data, "allow escaped quote in strings" );
	}

	public function testEscapeNonQuote() : void {
		$p = new Parser( '"\r"' );
		$this->assertEquals( "\r", $p->data, "process \ r escape" );

		$p = new Parser( '"\n"' );
		$this->assertEquals( "\n", $p->data, "process \ n escape" );

		$p = new Parser( '"\f"' );
		$this->assertEquals( "\f", $p->data, "process \ f escape" );

		$p = new Parser( '"\t"' );
		$this->assertEquals( "\t", $p->data, "process \ t escape" );


		$p = new Parser( '"\b"' );
		$this->assertEquals( 0x08, $p->data, "process \ b escape" );

		$p = new Parser( '"\\\\"' );
		$this->assertEquals( '\\', $p->data, "process \ \ escape" );

		$p = new Parser( '"\/"' );
		$this->assertEquals( "/", $p->data, "process \ / escape" );
	
		$p = new Parser( '"\u220f"' );
		$this->assertEquals( "∏", $p->data, "process \ u 220f escape" );
	}
}

