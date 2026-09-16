<?php

namespace Prado\Wsdl\Test\Unit;

use DOMDocument;
use Prado\Wsdl\Wsdl;
use Prado\Wsdl\WsdlMessage;
use Prado\Wsdl\WsdlOperation;

class WsdlTest extends WsdlTestCase
{
	/**
	 * Builds a document holding one operation.
	 * @param string $name The service name
	 * @param string $encoding The character encoding
	 * @return Wsdl The document
	 */
	protected function newWsdl($name = 'Service', $encoding = 'UTF-8')
	{
		$wsdl = new Wsdl($name, 'http://example.com/soap', $encoding);
		$operation = new WsdlOperation('op', 'An operation.');
		$operation->setInputMessage(new WsdlMessage('opRequest', [['name' => 'a', 'type' => 'xsd:string']]));
		$operation->setOutputMessage(new WsdlMessage('opResponse', [['name' => 'return', 'type' => 'xsd:string']]));
		$wsdl->addOperation($operation);
		return $wsdl;
	}

	/**
	 * Parses the output of a document.
	 * @param Wsdl $wsdl The document to read
	 * @return DOMDocument The parsed document
	 */
	protected function parse(Wsdl $wsdl)
	{
		$dom = new DOMDocument();
		$this->assertTrue($dom->loadXML($wsdl->getWsdl()));
		return $dom;
	}

	public function testTheTargetNamespaceFollowsTheServiceName(): void
	{
		$dom = $this->parse($this->newWsdl('Payments'));
		$this->assertSame('urn:Paymentswsdl', $dom->documentElement->getAttribute('targetNamespace'));
		$this->assertSame('Payments', $dom->documentElement->getAttribute('name'));
	}

	public function testTheEncodingReachesTheDeclaration(): void
	{
		$this->assertStringContainsString('encoding="UTF-8"', $this->newWsdl('Service', 'UTF-8')->getWsdl());
	}

	public function testAnEmptyEncodingLeavesTheDeclarationBare(): void
	{
		$wsdl = $this->newWsdl('Service', '')->getWsdl();
		$this->assertStringStartsWith('<?xml version="1.0"?>', $wsdl);
		$this->assertStringNotContainsString('encoding=', substr($wsdl, 0, 40));
	}

	public function testTheServiceUriReachesTheAddress(): void
	{
		$dom = $this->parse($this->newWsdl());
		$this->assertSame(['http://example.com/soap'], $this->attributes($dom, '//soap:address', 'location'));
	}

	public function testAnEmptyServiceUriFallsBackToTheRequest(): void
	{
		$server = $_SERVER;
		$_SERVER['HTTPS'] = 'on';
		$_SERVER['HTTP_HOST'] = 'example.org';
		$_SERVER['PHP_SELF'] = '/soap.php';

		try {
			$wsdl = new Wsdl('Service', '', 'UTF-8');
			$dom = $this->parse($wsdl);
			$this->assertSame(['https://example.org/soap.php'], $this->attributes($dom, '//soap:address', 'location'));
		} finally {
			$_SERVER = $server;
		}
	}

	public function testAnOffHttpsFallsBackToHttp(): void
	{
		$server = $_SERVER;
		$_SERVER['HTTPS'] = 'off';
		$_SERVER['HTTP_HOST'] = 'example.org';
		$_SERVER['PHP_SELF'] = '/soap.php';

		try {
			$dom = $this->parse(new Wsdl('Service', '', 'UTF-8'));
			$this->assertSame(['http://example.org/soap.php'], $this->attributes($dom, '//soap:address', 'location'));
		} finally {
			$_SERVER = $server;
		}
	}

	public function testAnEncodedAmpersandIsRestoredInTheUri(): void
	{
		$wsdl = new Wsdl('Service', 'http://example.com/soap?a=1&amp;b=2', 'UTF-8');
		$dom = $this->parse($wsdl);
		$this->assertSame(['http://example.com/soap?a=1&b=2'], $this->attributes($dom, '//soap:address', 'location'));
	}

	public function testAServerWithoutARequestRaisesNoWarning(): void
	{
		$server = $_SERVER;
		unset($_SERVER['HTTPS'], $_SERVER['HTTP_HOST'], $_SERVER['PHP_SELF']);

		$raised = [];
		set_error_handler(function ($severity, $message) use (&$raised) {
			$raised[] = $message;
			return true;
		});

		try {
			$dom = $this->parse(new Wsdl('Service', '', 'UTF-8'));
			$this->assertSame(['http://'], $this->attributes($dom, '//soap:address', 'location'));
		} finally {
			restore_error_handler();
			$_SERVER = $server;
		}

		$this->assertSame([], $raised);
	}

	/**
	 * The opening of the document is assembled as text, so a name carrying a
	 * markup character used to reach the parser as markup. The document then
	 * failed to load and the build died dereferencing a null element.
	 */
	public function testAnAmpersandInTheServiceNameSurvivesTheDocument(): void
	{
		$dom = $this->parse($this->newWsdl('Pay&Go'));
		$this->assertSame('Pay&Go', $dom->documentElement->getAttribute('name'));
		$this->assertSame('urn:Pay&Gowsdl', $dom->documentElement->getAttribute('targetNamespace'));
	}

	/**
	 * Parses a document whose namespace URI the parser objects to, and returns
	 * both the document and what it said.
	 * @param Wsdl $wsdl The document to read
	 * @return array{0: DOMDocument, 1: array<int, string>} The parsed document and the distinct parser messages
	 */
	protected function parseWithComplaints(Wsdl $wsdl)
	{
		$previous = libxml_use_internal_errors(true);
		libxml_clear_errors();

		try {
			$dom = new DOMDocument();
			$this->assertTrue($dom->loadXML($wsdl->getWsdl()), 'the document still parses');
			$messages = array_values(array_unique(array_map(fn ($error) => trim($error->message), libxml_get_errors())));
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors($previous);
		}

		return [$dom, $messages];
	}

	public function testAQuoteInTheServiceNameSurvivesTheDocument(): void
	{
		[$dom] = $this->parseWithComplaints($this->newWsdl('Pay"Go'));
		$this->assertSame('Pay"Go', $dom->documentElement->getAttribute('name'));
	}

	/**
	 * A name holding a namespace separator reaches the document, and the urn
	 * built from it is not a URI the parser accepts. It has always been so, and
	 * every namespaced provider carries one.
	 */
	public function testANamespacedServiceNameSurvivesTheDocument(): void
	{
		[$dom, $messages] = $this->parseWithComplaints($this->newWsdl('Prado\\Wsdl\\Payments'));

		$this->assertSame('Prado\\Wsdl\\Payments', $dom->documentElement->getAttribute('name'));
		$this->assertSame(['xmlns:tns: \'urn:Prado\\Wsdl\\Paymentswsdl\' is not a valid URI'], $messages);
	}

	/**
	 * A namespace declaration is serialized as it was given, which escaping does
	 * not reach. The service is named rather than handing back a broken document.
	 */
	public function testAServiceNameNoDocumentCanHoldIsRefused(): void
	{
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('does not parse');
		$this->newWsdl('Pay<Go')->getWsdl();
	}

	/**
	 * @dataProvider encodingProvider
	 * @param string $encoding The encoding to declare
	 */
	public function testAnEncodingNameIsDeclared($encoding): void
	{
		$this->assertStringContainsString('encoding="' . $encoding . '"', $this->newWsdl('Service', $encoding)->getWsdl());
	}

	/**
	 * @return array<int, array<int, string>> The encoding to declare
	 */
	public static function encodingProvider(): array
	{
		return [['UTF-8'], ['utf8'], ['ISO-8859-1'], ['Shift_JIS'], ['windows-1252']];
	}

	/**
	 * The encoding sits in the declaration, where escaping would not help, so a
	 * name that is not an encoding name is refused.
	 * @dataProvider badEncodingProvider
	 * @param string $encoding The encoding to declare
	 */
	public function testAnEncodingThatIsNotAnEncodingNameIsRefused($encoding): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->newWsdl('Service', $encoding)->getWsdl();
	}

	/**
	 * @return array<string, array<int, string>> The encoding to declare
	 */
	public static function badEncodingProvider(): array
	{
		return [
			'closes the declaration' => ['UTF-8" foo="bar'],
			'holds a space' => ['UTF 8'],
			'opens with a digit' => ['8859'],
			'holds a quote' => ["UTF-8'"],
		];
	}

	public function testTheDocumentCarriesTheOperationThroughEverySection(): void
	{
		$dom = $this->parse($this->newWsdl());
		$this->assertSame(['op'], $this->attributes($dom, '//wsdl:portType/wsdl:operation', 'name'));
		$this->assertSame(['op'], $this->attributes($dom, '//wsdl:binding/wsdl:operation', 'name'));
		$this->assertSame(['opRequest', 'opResponse'], $this->attributes($dom, '//wsdl:message', 'name'));
		$this->assertSame(['ServiceService'], $this->attributes($dom, '//wsdl:service', 'name'));
		$this->assertSame(['ServicePort'], $this->attributes($dom, '//wsdl:port', 'name'));
	}

	public function testTheBindingCarriesTheStyleAndTransport(): void
	{
		$dom = $this->parse($this->newWsdl());
		$binding = $this->element($dom, '//wsdl:binding/soap:binding');
		$this->assertSame('rpc', $binding->getAttribute('style'));
		$this->assertSame('http://schemas.xmlsoap.org/soap/http', $binding->getAttribute('transport'));
	}

	public function testADocumentWithoutTypesDeclaresNoTypesSection(): void
	{
		$dom = $this->parse($this->newWsdl());
		$this->assertCount(0, $this->query($dom, '//wsdl:types'));
	}

	public function testAComplexTypeBecomesAnAllOfItsElements(): void
	{
		$wsdl = $this->newWsdl();
		$wsdl->addComplexType('Record', [
			['name' => 'a', 'type' => 'xsd:string', 'nil' => false, 'minOc' => false, 'maxOc' => false],
			['name' => 'b', 'type' => 'xsd:int', 'nil' => 'true', 'minOc' => 1, 'maxOc' => 4],
		]);
		$dom = $this->parse($wsdl);

		$this->assertSame(['Record'], $this->complexTypes($dom));
		$this->assertSame(['a', 'b'], $this->attributes($dom, "//xsd:complexType[@name='Record']/xsd:all/xsd:element", 'name'));

		$b = $this->element($dom, "//xsd:complexType[@name='Record']/xsd:all/xsd:element[@name='b']");
		$this->assertSame('true', $b->getAttribute('nillable'));
		$this->assertSame('1', $b->getAttribute('minOccurs'));
		$this->assertSame('4', $b->getAttribute('maxOccurs'));

		$a = $this->element($dom, "//xsd:complexType[@name='Record']/xsd:all/xsd:element[@name='a']");
		$this->assertFalse($a->hasAttribute('nillable'));
		$this->assertFalse($a->hasAttribute('minOccurs'));
		$this->assertFalse($a->hasAttribute('maxOccurs'));
	}

	public function testAnArrayTypeBecomesAnUnboundedSequence(): void
	{
		$wsdl = $this->newWsdl();
		$wsdl->addComplexType('RecordArray', '');
		$dom = $this->parse($wsdl);

		$element = $this->element($dom, "//xsd:complexType[@name='RecordArray']/xsd:sequence/xsd:element");
		$this->assertSame('Record', $element->getAttribute('name'));
		$this->assertSame('tns:Record', $element->getAttribute('type'));
		$this->assertSame('0', $element->getAttribute('minOccurs'));
		$this->assertSame('unbounded', $element->getAttribute('maxOccurs'));
	}

	/**
	 * An array of a primitive used to declare its element as tns:<type>, which
	 * resolves to nothing, so the client lost the type.
	 */
	public function testAnArrayOfAPrimitiveTakesTheXsdType(): void
	{
		$wsdl = $this->newWsdl();
		$wsdl->addComplexType('stringArray', '');
		$dom = $this->parse($wsdl);

		$element = $this->element($dom, "//xsd:complexType[@name='stringArray']/xsd:sequence/xsd:element");
		$this->assertSame('xsd:string', $element->getAttribute('type'));
	}

	/**
	 * @dataProvider elementTypeProvider
	 * @param string $type The array complexType name
	 * @param string $expected The type its element takes
	 */
	public function testGetArrayElementTypeResolvesEveryAlias($type, $expected): void
	{
		$this->assertSame($expected, $this->callProtected($this->newWsdl(), 'getArrayElementType', [$type]));
	}

	/**
	 * @return array<int, array<int, string>> The type and the type its element takes
	 */
	public static function elementTypeProvider(): array
	{
		return [
			['stringArray', 'xsd:string'],
			['strArray', 'xsd:string'],
			['intArray', 'xsd:int'],
			['integerArray', 'xsd:int'],
			['floatArray', 'xsd:float'],
			['doubleArray', 'xsd:float'],
			['booleanArray', 'xsd:boolean'],
			['boolArray', 'xsd:boolean'],
			['dateArray', 'xsd:date'],
			['timeArray', 'xsd:time'],
			['dateTimeArray', 'xsd:dateTime'],
			['mixedArray', 'xsd:anyType'],
			['objectArray', 'xsd:struct'],
			['RecordArray', 'tns:Record'],
		];
	}

	/**
	 * @dataProvider typePrefixProvider
	 * @param string $type The array complexType name
	 * @param string $expected The prefix its element takes
	 */
	public function testTheDeprecatedPrefixStillAnswersForBothKinds($type, $expected): void
	{
		$this->assertSame($expected, $this->callProtected($this->newWsdl(), 'getArrayTypePrefix', [$type]));
	}

	/**
	 * @return array<int, array<int, string>> The type and the prefix its element takes
	 */
	public static function typePrefixProvider(): array
	{
		return [
			['stringArray', 'xsd:'],
			['boolArray', 'xsd:'],
			['RecordArray', 'tns:'],
		];
	}
}
