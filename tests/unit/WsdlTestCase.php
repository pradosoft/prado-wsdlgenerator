<?php

namespace Prado\Wsdl\Test\Unit;

use DOMDocument;
use DOMNodeList;
use DOMXPath;
use PHPUnit\Framework\TestCase;
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
	 * @return DOMDocument The parsed document
	 */
	protected function generate($className, $serviceUri = 'http://example.com/soap')
	{
		$generator = new WsdlGenerator();
		$generator->generateWsdl($className, $serviceUri, 'UTF-8');

		$dom = new DOMDocument();
		$this->assertTrue($dom->loadXML($generator->getWsdl()), 'the generated wsdl parses');
		return $dom;
	}

	/**
	 * Runs an XPath query over a generated document.
	 * @param DOMDocument $dom The document to query
	 * @param string $query The XPath expression, using the wsdl, soap and xsd prefixes
	 * @return DOMNodeList The matching nodes
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
	 * @return array The attribute of each match, in document order
	 */
	protected function attributes(DOMDocument $dom, $query, $attribute)
	{
		$values = [];
		foreach ($this->query($dom, $query) as $node) {
			$values[] = $node->getAttribute($attribute);
		}
		return $values;
	}

	/**
	 * Names the complexTypes a document declares.
	 * @param DOMDocument $dom The document to read
	 * @return array The complexType names, in document order
	 */
	protected function complexTypes(DOMDocument $dom)
	{
		return $this->attributes($dom, '//xsd:complexType', 'name');
	}

	/**
	 * Reads the parts of a message as a name to type map.
	 * @param DOMDocument $dom The document to read
	 * @param string $message The message name
	 * @return array The type of each part, keyed by part name
	 */
	protected function parts(DOMDocument $dom, $message)
	{
		$parts = [];
		foreach ($this->query($dom, "//wsdl:message[@name='" . $message . "']/wsdl:part") as $part) {
			$parts[$part->getAttribute('name')] = $part->getAttribute('type');
		}
		return $parts;
	}

	/**
	 * Asserts that every type a schema element or a message part names in the
	 * target namespace is a type the document also declares. A dangling
	 * reference is well formed, and a schema validator rejects it.
	 * @param DOMDocument $dom The document to check
	 */
	protected function assertEveryLocalTypeResolves(DOMDocument $dom)
	{
		$declared = $this->complexTypes($dom);

		foreach ($this->query($dom, '//xsd:element[@type] | //wsdl:part[@type]') as $node) {
			$type = $node->getAttribute('type');
			if (!str_starts_with($type, 'tns:')) {
				continue;
			}
			$this->assertContains(substr($type, 4), $declared, $type . ' names a type the document declares');
		}
	}

	/**
	 * Calls a method the class does not expose.
	 * @param object $object The object to call on
	 * @param string $method The method to call
	 * @param array $arguments The arguments to pass
	 * @return mixed The return of the method
	 */
	protected function callProtected($object, $method, array $arguments = [])
	{
		$reflection = new \ReflectionMethod($object, $method);
		$reflection->setAccessible(true);
		return $reflection->invokeArgs($object, $arguments);
	}
}
