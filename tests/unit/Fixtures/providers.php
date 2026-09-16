<?php

/**
 * Service providers used by the generator tests.
 *
 * These classes are global for the same reason the complex types are: a
 * provider is named as a string and reflected on.
 */

class WsdlTestProvider
{
	/**
	 * Adds two numbers.
	 * @param int $a the first number
	 * @param int $b the second number
	 * @return int the sum
	 * @soapmethod
	 */
	public function add($a, $b)
	{
	}

	/**
	 * Discusses the @soapmethod tag without carrying it, so it is not an
	 * operation.
	 * @return string anything
	 */
	public function skipped()
	{
	}

	/**
	 * Is not public, so it is not an operation.
	 * @return string anything
	 * @soapmethod
	 */
	protected function hidden()
	{
	}
}

/**
 * Covers every type conversion the generator performs.
 */
class WsdlTestScalarProvider
{
	/**
	 * @param string $a a
	 * @param str $b b
	 * @param int $c c
	 * @param integer $d d
	 * @param float $e e
	 * @param double $f f
	 * @param boolean $g g
	 * @param bool $h h
	 * @return string the answer
	 * @soapmethod
	 */
	public function scalars($a, $b, $c, $d, $e, $f, $g, $h)
	{
	}

	/**
	 * @param date $a a
	 * @param time $b b
	 * @param dateTime $c c
	 * @param array $d d
	 * @param object $e e
	 * @param mixed $f f
	 * @return mixed the answer
	 * @soapmethod
	 */
	public function others($a, $b, $c, $d, $e, $f)
	{
	}
}

/**
 * Returns arrays of primitives and of a complex type.
 */
class WsdlTestArrayProvider
{
	/**
	 * @return string[] the names
	 * @soapmethod
	 */
	public function names()
	{
	}

	/**
	 * @return bool[] the flags
	 * @soapmethod
	 */
	public function flags()
	{
	}

	/**
	 * @return double[] the sizes
	 * @soapmethod
	 */
	public function sizes()
	{
	}

	/**
	 * @return WsdlTestAddress[] the addresses
	 * @soapmethod
	 */
	public function addresses()
	{
	}
}

/**
 * Returns nothing.
 */
class WsdlTestVoidProvider
{
	/**
	 * Does a thing.
	 * @return void
	 * @soapmethod
	 */
	public function doThing()
	{
	}
}

/**
 * Declares types no operation signature names.
 *
 * @soaptype WsdlTestPerson
 * @soaptype WsdlTestAddress[]
 */
class WsdlTestTypeTagProvider
{
	/**
	 * Looks a record up.
	 * @param string $key the key
	 * @return mixed a person or a list of addresses
	 * @soapmethod
	 */
	public function lookup($key)
	{
	}
}

/** @soaptype WsdlTestPerson */
class WsdlTestOneLineTagProvider
{
	/**
	 * Looks a record up.
	 * @return mixed a person
	 * @soapmethod
	 */
	public function lookup()
	{
	}
}

/**
 * Names the @soaptype tag in prose without declaring one, and spaces the
 * brackets of the tag it does declare.
 *
 * @soaptype WsdlTestAddress[ ]
 */
class WsdlTestProseProvider
{
	/**
	 * Looks a record up.
	 * @return mixed an address
	 * @soapmethod
	 */
	public function lookup()
	{
	}
}

/**
 * Declares a type that does not exist.
 *
 * @soaptype WsdlTestNoSuchClass
 */
class WsdlTestBadTagProvider
{
	/**
	 * @return string anything
	 * @soapmethod
	 */
	public function lookup()
	{
	}
}

/**
 * Declares no types.
 */
class WsdlTestNoTagProvider
{
	/**
	 * @return string anything
	 * @soapmethod
	 */
	public function lookup()
	{
	}
}

/**
 * Exercises the shapes of a doc comment the parser has to survive.
 */
class WsdlTestDocCommentProvider
{
	/**
	 * @soapmethod
	 * a continuation line before any param
	 * @param string $a the first argument
	 * which continues on the next line
	 * @return string the answer
	 * which also continues
	 */
	public function continuation($a)
	{
	}

	/**
	 * Has a description but no tags beyond the marker.
	 * @soapmethod
	 */
	public function bare()
	{
	}
}

/**
 * Declares a type whose own property is a complex type.
 *
 * @soaptype WsdlTestPerson
 */
class WsdlTestNestedProvider
{
	/**
	 * @return mixed a person
	 * @soapmethod
	 */
	public function lookup()
	{
	}
}

/**
 * Declares a type that exports no properties.
 *
 * @soaptype WsdlTestBlank
 */
class WsdlTestBlankTypeProvider
{
	/**
	 * @return mixed nothing much
	 * @soapmethod
	 */
	public function lookup()
	{
	}
}

// Carries no doc comment at all, so getDocComment() returns false.
class WsdlTestUndocumented
{
}

/**
 * Names the marker tags in prose without using them as tags.
 *
 * @soaptype WsdlTestOneLineType
 */
class WsdlTestProseMarkerProvider
{
	/**
	 * Discusses the @soapmethod tag rather than carrying it.
	 * @return string anything
	 */
	public function discussed()
	{
	}

	/** @soapmethod
	 * @return string anything
	 */
	public function oneLineMarker()
	{
	}
}
