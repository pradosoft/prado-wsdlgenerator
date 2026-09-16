<?php

namespace Prado\Wsdl\Test\Unit;

use DOMDocument;
use Prado\Wsdl\WsdlMessage;

class WsdlMessageTest extends WsdlTestCase
{
	/**
	 * Builds a message element and returns the document holding it.
	 * @param array<int, array<string, string>> $parts The parts of the message
	 * @return DOMDocument The document holding the message
	 */
	protected function build(array $parts)
	{
		$dom = new DOMDocument();
		$message = new WsdlMessage('opRequest', $parts);
		$dom->appendChild($message->getMessageElement($dom));
		return $dom;
	}

	public function testGetNameReturnsTheName(): void
	{
		$message = new WsdlMessage('opRequest', []);
		$this->assertSame('opRequest', $message->getName());
	}

	public function testTheElementCarriesTheName(): void
	{
		$dom = $this->build([]);
		$this->assertSame('opRequest', $dom->documentElement->getAttribute('name'));
		$this->assertSame(self::WSDL_NS, $dom->documentElement->namespaceURI);
	}

	public function testEveryPartBecomesAnElement(): void
	{
		$dom = $this->build([
			['name' => 'a', 'type' => 'xsd:string'],
			['name' => 'b', 'type' => 'tns:Record'],
		]);
		$this->assertSame(['a' => 'xsd:string', 'b' => 'tns:Record'], $this->parts($dom, 'opRequest'));
	}

	public function testAPartWithoutANameIsLeftOut(): void
	{
		$dom = $this->build([['type' => 'xsd:string'], ['name' => 'b', 'type' => 'xsd:int']]);
		$this->assertSame(['b' => 'xsd:int'], $this->parts($dom, 'opRequest'));
	}

	/**
	 * A void return converts to an empty type, which is not a usable part.
	 */
	public function testAPartWithAnEmptyTypeIsLeftOut(): void
	{
		$dom = $this->build([['name' => 'return', 'type' => '']]);
		$this->assertSame([], $this->parts($dom, 'opRequest'));
	}

	/**
	 * The properties carry types, and did not in 1.1. A caller passing something
	 * else still gets the message it always got.
	 */
	public function testANameThatIsNotAStringIsCoerced(): void
	{
		$this->assertSame('', (new WsdlMessage(null, []))->getName());
		$this->assertSame('7', (new WsdlMessage(7, []))->getName());
	}

	/**
	 * @dataProvider notPartsProvider
	 * @param mixed $parts The parts to pass
	 */
	public function testPartsThatAreNotAnArrayCarryNoPart($parts): void
	{
		$dom = new DOMDocument();
		$dom->appendChild((new WsdlMessage('opRequest', $parts))->getMessageElement($dom));

		$this->assertSame([], $this->parts($dom, 'opRequest'));
	}

	/**
	 * @return array<string, array{0: mixed}> The parts to pass
	 */
	public static function notPartsProvider(): array
	{
		return ['null' => [null], 'int' => [7], 'bool' => [true], 'string' => ['x']];
	}

	public function testAPartWithoutATypeIsLeftOut(): void
	{
		$dom = $this->build([['name' => 'return']]);
		$this->assertSame([], $this->parts($dom, 'opRequest'));
	}
}
