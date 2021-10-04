<?php

declare(strict_types=1);

namespace Abc;

#[Attr(new Attr(max: 6))]
class Class11
{
	final public const FOO = 10;

	public Foo&Bar $foo;

	public readonly array $ro;

	public function foo(Foo&Bar $c): Foo&Bar
	{
	}

	public function bar($c = new \stdClass)
	{
	}
}


#[\Attribute]
class Attr
{
}
