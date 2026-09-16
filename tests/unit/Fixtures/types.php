<?php

/**
 * Complex types used by the provider fixtures.
 *
 * These classes are global because the generator reflects on the unqualified
 * name written in a doc comment.
 */

class WsdlTestAddress
{
	/**
	 * @soapproperty
	 * @var string $street
	 */
	public $street;

	/**
	 * @soapproperty
	 * @var int $zip {nillable=1, minOccurs=0, maxOccurs=2}
	 */
	public $zip;

	/**
	 * Discusses the @soapproperty tag without carrying it, so it stays out of
	 * the wsdl.
	 * @var string $internal
	 */
	public $internal;
}

class WsdlTestOneLineType
{
	/** @soapproperty
	 * @var string $a
	 */
	public $a;
}

class WsdlTestPerson
{
	/**
	 * @soapproperty
	 * @var string $name
	 */
	public $name;

	/**
	 * @soapproperty
	 * @var WsdlTestAddress $address
	 */
	public $address;
}

/**
 * Declares no properties at all.
 */
class WsdlTestBlank
{
}
