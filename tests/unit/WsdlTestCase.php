<?php

namespace Prado\Wsdl\Test\Unit;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use Prado\Wsdl\Wsdl;
use Prado\Wsdl\WsdlGenerator;

/**
 * Base for the tests, holding the helpers that read a generated document.
 */
abstract class WsdlTestCase extends TestCase
{
	public const WSDL_NS = 'http://schemas.xmlsoap.org/wsdl/';
	public const SOAP_NS = 'http://schemas.xmlsoap.org/wsdl/soap/';
	public const XSD_NS = 'http://www.w3.org/2001/XMLSchema';

	/**
	 * Generates the wsdl of a provider and parses it.
	 * @param string $className The provider to generate for
	 * @param string $serviceUri The service URI to write into the document
	 * @param string $style The binding style to generate
	 * @return DOMDocument The parsed document
	 */
	protected function generate($className, $serviceUri = 'http://example.com/soap', $style = Wsdl::STYLE_RPC)
	{
		$generator = new WsdlGenerator();
		$generator->setStyle($style);
		$generator->generateWsdl($className, $serviceUri, 'UTF-8');

		$dom = new DOMDocument();
		$this->assertTrue($dom->loadXML($generator->getWsdl()), 'the generated wsdl parses');
		return $dom;
	}

	/**
	 * Runs an XPath query over a generated document.
	 * @param DOMDocument $dom The document to query
	 * @param string $query The XPath expression, using the wsdl, soap and xsd prefixes
	 * @return DOMNodeList<DOMNode> The matching nodes
	 */
	protected function query(DOMDocument $dom, $query)
	{
		$xpath = new DOMXPath($dom);
		$xpath->registerNamespace('wsdl', self::WSDL_NS);
		$xpath->registerNamespace('soap', self::SOAP_NS);
		$xpath->registerNamespace('xsd', self::XSD_NS);
		return $xpath->query($query);
	}

	/**
	 * Collects an attribute of every node matching a query.
	 * @param DOMDocument $dom The document to query
	 * @param string $query The XPath expression
	 * @param string $attribute The attribute to read
	 * @return array<int, string> The attribute of each match, in document order
	 */
	protected function attributes(DOMDocument $dom, $query, $attribute)
	{
		$values = [];
		foreach ($this->query($dom, $query) as $node) {
			$this->assertInstanceOf(DOMElement::class, $node);
			$values[] = $node->getAttribute($attribute);
		}
		return $values;
	}

	/**
	 * Names the complexTypes a document declares by name.
	 * @param DOMDocument $dom The document to read
	 * @return array<int, string> The complexType names, in document order
	 */
	protected function complexTypes(DOMDocument $dom)
	{
		// A wrapper element holds an anonymous complexType, which names nothing.
		return $this->attributes($dom, '//xsd:complexType[@name]', 'name');
	}

	/**
	 * Reads the parts of a message as a name to type map.
	 * @param DOMDocument $dom The document to read
	 * @param string $message The message name
	 * @return array<string, string> The type of each part, keyed by part name
	 */
	protected function parts(DOMDocument $dom, $message)
	{
		$parts = [];
		foreach ($this->query($dom, "//wsdl:message[@name='" . $message . "']/wsdl:part") as $part) {
			$this->assertInstanceOf(DOMElement::class, $part);
			$parts[$part->getAttribute('name')] = $part->getAttribute('type');
		}
		return $parts;
	}

	/**
	 * The built-in types of XML Schema 1.0, which is every name a document may
	 * carry under the xsd prefix.
	 */
	public const XSD_TYPES = [
		'anyType', 'anySimpleType', 'string', 'boolean', 'decimal', 'float', 'double',
		'duration', 'dateTime', 'time', 'date', 'gYearMonth', 'gYear', 'gMonthDay',
		'gDay', 'gMonth', 'hexBinary', 'base64Binary', 'anyURI', 'QName', 'NOTATION',
		'normalizedString', 'token', 'language', 'NMTOKEN', 'NMTOKENS', 'Name',
		'NCName', 'ID', 'IDREF', 'IDREFS', 'ENTITY', 'ENTITIES', 'integer',
		'nonPositiveInteger', 'negativeInteger', 'long', 'int', 'short', 'byte',
		'nonNegativeInteger', 'unsignedLong', 'unsignedInt', 'unsignedShort',
		'unsignedByte', 'positiveInteger',
	];

	/**
	 * Asserts that every type a schema element or a message part names resolves:
	 * a type in the target namespace is one the document declares, and a type
	 * under the xsd prefix is one XML Schema defines. A dangling reference is
	 * well formed, and a schema validator rejects it.
	 * @param DOMDocument $dom The document to check
	 * @return void
	 */
	protected function assertEveryLocalTypeResolves(DOMDocument $dom)
	{
		$declared = $this->complexTypes($dom);

		foreach ($this->query($dom, '//xsd:element[@type] | //wsdl:part[@type]') as $node) {
			$this->assertInstanceOf(DOMElement::class, $node);
			$type = $node->getAttribute('type');
			if (str_starts_with($type, 'tns:')) {
				$this->assertContains(substr($type, 4), $declared, $type . ' names a type the document declares');
			} elseif (str_starts_with($type, 'xsd:')) {
				$this->assertContains(substr($type, 4), self::XSD_TYPES, $type . ' names a type XML Schema defines');
			}
		}
	}

	/**
	 * Returns the one element a query matches. A query matching nothing would
	 * otherwise reach the caller as a null, and read as an error rather than a
	 * failure.
	 * @param DOMDocument $dom The document to query
	 * @param string $query The XPath expression
	 * @return DOMElement The matching element
	 */
	protected function element(DOMDocument $dom, $query)
	{
		$node = $this->query($dom, $query)->item(0);
		$this->assertInstanceOf(DOMElement::class, $node, $query . ' matches an element');
		return $node;
	}

	/**
	 * Asserts that the schema a document carries compiles. A schema is only
	 * resolved where it is used, so every complexType is referenced by a global
	 * element first. This catches a type that does not exist, an occurrence count
	 * the compositor forbids, and a reference to a type nothing declares.
	 * @param DOMDocument $dom The document to check
	 * @return void
	 */
	protected function assertSchemaCompiles(DOMDocument $dom)
	{
		$schema = $dom->getElementsByTagNameNS(self::XSD_NS, 'schema')->item(0);
		if ($schema === null) {
			return;
		}

		$out = new DOMDocument();
		$copy = $out->importNode($schema, true);
		$this->assertInstanceOf(DOMElement::class, $copy);
		$out->appendChild($copy);
		$copy->setAttributeNS(
			'http://www.w3.org/2000/xmlns/',
			'xmlns:tns',
			$dom->documentElement->getAttribute('targetNamespace')
		);

		foreach ($this->complexTypes($dom) as $index => $type) {
			$probe = $out->createElementNS(self::XSD_NS, 'xsd:element');
			$probe->setAttribute('name', 'probe' . $index);
			$probe->setAttribute('type', 'tns:' . $type);
			$copy->appendChild($probe);
		}

		$previous = libxml_use_internal_errors(true);
		libxml_clear_errors();

		try {
			$probe = new DOMDocument();
			$probe->loadXML('<probe/>');
			@$probe->schemaValidateSource($out->saveXML());
			$fatal = [];
			foreach (libxml_get_errors() as $error) {
				$message = trim($error->message);
				if (str_contains($message, 'resolv') || str_contains($message, 'Invalid') || str_contains($message, 'not allowed')) {
					$fatal[] = $message;
				}
			}
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors($previous);
		}

		$this->assertSame([], array_values(array_unique($fatal)), 'the schema compiles');
	}

	/**
	 * Calls a method the class does not expose.
	 * @param object $object The object to call on
	 * @param string $method The method to call
	 * @param array<int, mixed> $arguments The arguments to pass
	 * @return mixed The return of the method
	 */
	protected function callProtected($object, $method, array $arguments = [])
	{
		$reflection = new \ReflectionMethod($object, $method);
		$reflection->setAccessible(true);
		return $reflection->invokeArgs($object, $arguments);
	}
}
