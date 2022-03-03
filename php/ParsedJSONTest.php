<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

mb_internal_encoding( 'UTF-8' );

// in ParsedJSONTest.php

require 'ParsedJSON.php';

final class ParsedJSONTest extends TestCase {
    public function testParseArrayInsideObject() : void {
        $p = new ParsedJSON( '{ "key": [] }' );
        $this->assertEquals( [ 'key' => [] ], $p->data, "array inside object" );
    }

    public function testEscapeQuote() : void {
        $p = new ParsedJSON( '"\""' );  // perverse, but valid
        $this->assertEquals( '"', $p->data, "allow escaped quote in strings" );
    }

    public function testEscapeNonQuote() : void {
        $p = new ParsedJSON( '"\r"' );
        $this->assertEquals( "\r", $p->data, "process \ r escape" );

        $p = new ParsedJSON( '"\n"' );
        $this->assertEquals( "\n", $p->data, "process \ n escape" );

        $p = new ParsedJSON( '"\f"' );
        $this->assertEquals( "\f", $p->data, "process \ f escape" );

        $p = new ParsedJSON( '"\t"' );
        $this->assertEquals( "\t", $p->data, "process \ t escape" );


        $p = new ParsedJSON( '"\b"' );
        $this->assertEquals( 0x08, $p->data, "process \ b escape" );

        $p = new ParsedJSON( '"\\\\"' );
        $this->assertEquals( '\\', $p->data, "process \ \ escape" );

        $p = new ParsedJSON( '"\/"' );
        $this->assertEquals( "/", $p->data, "process \ / escape" );
    
        $p = new ParsedJSON( '"\u220f"' );
        $this->assertEquals( "∏", $p->data, "process \ u 220f escape" );
    }
}

