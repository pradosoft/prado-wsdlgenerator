<?php

namespace Prado\Wsdl\Test\Unit;

use DOMDocument;
use Prado\Wsdl\WsdlMessage;
use Prado\Wsdl\WsdlOperation;

class WsdlOperationTest extends WsdlTestCase
{
	/**
	 * Builds an operation carrying a request and a response message.
	 * @param string $doc The documentation of the operation
	 * @return WsdlOperation The operation
	 */
	protected function newOperation($doc = 'An operation.')
	{
		$operation = new WsdlOperation('op', $doc);
		$operation->setInputMessage(new WsdlMessage('opRequest', [['name' => 'a', 'type' => 'xsd:string']]));
		$operation->setOutputMessage(new WsdlMessage('opResponse', [['name' => 'return', 'type' => 'xsd:string']]));
		return $operation;
	}

	/**
	 * Wraps an element in a document so it can be queried.
	 * @param \DOMDocument $dom The document the element belongs to
	 * @param \DOMElement $element The element to hold
	 * @return DOMDocument The document holding the element
	 */
	protected function hold(DOMDocument $dom, $element)
	{
		$dom->appendChild($element);
		return $dom;
	}

	public function testThePortOperationCarriesItsMessages()
	{
		$dom = new DOMDocument();
		$dom = $this->hold($dom, $this->newOperation()->getPortOperation($dom));

		$this->assertSame('op', $dom->documentElement->getAttribute('name'));
		$this->assertSame(['tns:opRequest'], $this->attributes($dom, '/wsdl:operation/wsdl:input', 'message'));
		$this->assertSame(['tns:opResponse'], $this->attributes($dom, '/wsdl:operation/wsdl:output', 'message'));
	}

	public function testThePortOperationCarriesItsDocumentation()
	{
		$dom = new DOMDocument();
		$dom = $this->hold($dom, $this->newOperation('Adds two numbers.')->getPortOperation($dom));

		$this->assertSame('Adds two numbers.', $this->query($dom, '/wsdl:operation/wsdl:documentation')->item(0)->textContent);
	}

	public function testTheDocumentationIsEscaped()
	{
		$dom = new DOMDocument();
		$dom = $this->hold($dom, $this->newOperation('Uses <b> & "quotes".')->getPortOperation($dom));

		$this->assertSame('Uses <b> & "quotes".', $this->query($dom, '/wsdl:operation/wsdl:documentation')->item(0)->textContent);
	}

	public function testTheBindingOperationCarriesTheActionAndStyle()
	{
		$dom = new DOMDocument();
		$dom = $this->hold($dom, $this->newOperation()->getBindingOperation($dom, 'urn:Servicewsdl'));

		$soapOperation = $this->query($dom, '/wsdl:operation/soap:operation')->item(0);
		$this->assertSame('urn:Servicewsdl#op', $soapOperation->getAttribute('soapAction'));
		$this->assertSame('rpc', $soapOperation->getAttribute('style'));
	}

	public function testTheBindingStyleIsSettable()
	{
		$dom = new DOMDocument();
		$dom = $this->hold($dom, $this->newOperation()->getBindingOperation($dom, 'urn:Servicewsdl', 'document'));

		$this->assertSame(['document'], $this->attributes($dom, '/wsdl:operation/soap:operation', 'style'));
	}

	public function testTheBindingOperationCarriesABodyOnBothSides()
	{
		$dom = new DOMDocument();
		$dom = $this->hold($dom, $this->newOperation()->getBindingOperation($dom, 'urn:Servicewsdl'));

		$bodies = $this->query($dom, '/wsdl:operation/*/soap:body');
		$this->assertCount(2, $bodies);
		foreach ($bodies as $body) {
			$this->assertSame('encoded', $body->getAttribute('use'));
			$this->assertSame('urn:Servicewsdl', $body->getAttribute('namespace'));
			$this->assertSame('http://schemas.xmlsoap.org/soap/encoding/', $body->getAttribute('encodingStyle'));
		}
	}

	public function testSetMessageElementsAppendsBothMessages()
	{
		$dom = new DOMDocument();
		$definitions = $dom->createElementNS(self::WSDL_NS, 'wsdl:definitions');
		$dom->appendChild($definitions);

		$this->newOperation()->setMessageElements($definitions, $dom);

		$this->assertSame(['opRequest', 'opResponse'], $this->attributes($dom, '//wsdl:message', 'name'));
	}
}
