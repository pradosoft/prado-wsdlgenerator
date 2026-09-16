<?php

namespace Prado\Wsdl\Test\Unit;

use DOMDocument;
use Prado\Wsdl\WsdlMessage;

class WsdlMessageTest extends WsdlTestCase
{
	/**
	 * Builds a message element and returns the document holding it.
	 * @param array $parts The parts of the message
	 * @return DOMDocument The document holding the message
	 */
	protected function build(array $parts)
	{
		$dom = new DOMDocument();
		$message = new WsdlMessage('opRequest', $parts);
		$dom->appendChild($message->getMessageElement($dom));
		return $dom;
	}

	public function testGetNameReturnsTheName()
	{
		$message = new WsdlMessage('opRequest', []);
		$this->assertSame('opRequest', $message->getName());
	}

	public function testTheElementCarriesTheName()
	{
		$dom = $this->build([]);
		$this->assertSame('opRequest', $dom->documentElement->getAttribute('name'));
		$this->assertSame(self::WSDL_NS, $dom->documentElement->namespaceURI);
	}

	public function testEveryPartBecomesAnElement()
	{
		$dom = $this->build([
			['name' => 'a', 'type' => 'xsd:string'],
			['name' => 'b', 'type' => 'tns:Record'],
		]);
		$this->assertSame(['a' => 'xsd:string', 'b' => 'tns:Record'], $this->parts($dom, 'opRequest'));
	}

	public function testAPartWithoutANameIsLeftOut()
	{
		$dom = $this->build([['type' => 'xsd:string'], ['name' => 'b', 'type' => 'xsd:int']]);
		$this->assertSame(['b' => 'xsd:int'], $this->parts($dom, 'opRequest'));
	}

	/**
	 * A void return converts to an empty type, which is not a usable part.
	 */
	public function testAPartWithAnEmptyTypeIsLeftOut()
	{
		$dom = $this->build([['name' => 'return', 'type' => '']]);
		$this->assertSame([], $this->parts($dom, 'opRequest'));
	}

	public function testAPartWithoutATypeIsLeftOut()
	{
		$dom = $this->build([['name' => 'return']]);
		$this->assertSame([], $this->parts($dom, 'opRequest'));
	}
}
