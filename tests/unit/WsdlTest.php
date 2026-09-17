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
		return $this->parseStrictly($wsdl->getWsdl());
	}

	public function testTheStyleDefaultsToRpcAndIsSettable(): void
	{
		$wsdl = $this->newWsdl();
		$this->assertSame(Wsdl::STYLE_RPC, $wsdl->getBindingStyle());

		$wsdl->setBindingStyle(Wsdl::STYLE_DOCUMENT);
		$this->assertSame(Wsdl::STYLE_DOCUMENT, $wsdl->getBindingStyle());

		$this->assertSame(
			Wsdl::STYLE_DOCUMENT,
			(new Wsdl('Service', 'http://example.com/soap', 'UTF-8', Wsdl::STYLE_DOCUMENT))->getBindingStyle()
		);
	}

	/**
	 * @dataProvider badStyleProvider
	 * @param mixed $style The style to set
	 */
	public function testAStyleThatIsNeitherIsRefused($style): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->newWsdl()->setBindingStyle($style);
	}

	/**
	 * @return array<string, array{0: mixed}> The style to set
	 */
	public static function badStyleProvider(): array
	{
		return ['literal' => ['literal'], 'empty' => [''], 'wrong case' => ['RPC'], 'encoded' => ['encoded']];
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
	 * A provider class is namespaced in any PSR-4 project, and a namespace
	 * separator is valid in neither a URI nor an NCName. The document used to
	 * carry it as it stood, so the parser warned about the target namespace,
	 * and PRADO's error handler turned the warning into an exception for every
	 * namespaced provider. Each separator is written as a dot instead.
	 */
	public function testANamespacedServiceNameIsWrittenWithDots(): void
	{
		$dom = $this->parse($this->newWsdl('Prado\\Wsdl\\Payments'));

		$this->assertSame('Prado.Wsdl.Payments', $dom->documentElement->getAttribute('name'));
		$this->assertSame('urn:Prado.Wsdl.Paymentswsdl', $dom->documentElement->getAttribute('targetNamespace'));
		$this->assertSame('urn:Prado.Wsdl.Paymentswsdl', $dom->documentElement->lookupNamespaceURI('tns'));
		$this->assertStringNotContainsString('\\', $dom->saveXML());
	}

	/**
	 * Every name derived from the service name is an NCName, and every QName
	 * referring to one names the element it stands for.
	 */
	public function testEveryNameDerivedFromANamespacedServiceNameResolves(): void
	{
		$dom = $this->parse($this->newWsdl('Prado\\Wsdl\\Payments'));

		$this->assertSame(['Prado.Wsdl.PaymentsPortType'], $this->attributes($dom, '//wsdl:portType', 'name'));
		$this->assertSame(['Prado.Wsdl.PaymentsBinding'], $this->attributes($dom, '//wsdl:binding', 'name'));
		$this->assertSame(['tns:Prado.Wsdl.PaymentsPortType'], $this->attributes($dom, '//wsdl:binding', 'type'));
		$this->assertSame(['Prado.Wsdl.PaymentsService'], $this->attributes($dom, '//wsdl:service', 'name'));
		$this->assertSame(['Prado.Wsdl.PaymentsPort'], $this->attributes($dom, '//wsdl:port', 'name'));
		$this->assertSame(['tns:Prado.Wsdl.PaymentsBinding'], $this->attributes($dom, '//wsdl:port', 'binding'));
		$this->assertSame(['urn:Prado.Wsdl.Paymentswsdl#op'], $this->attributes($dom, '//soap:operation', 'soapAction'));
		$this->assertSame(['urn:Prado.Wsdl.Paymentswsdl', 'urn:Prado.Wsdl.Paymentswsdl'], $this->attributes($dom, '//soap:body', 'namespace'));
	}

	/**
	 * @dataProvider documentNameProvider
	 * @param string $className The class name to map
	 * @param string $expected The name the document carries
	 */
	public function testDocumentNameReplacesEachSeparatorWithADot($className, $expected): void
	{
		$this->assertSame($expected, Wsdl::documentName($className));
	}

	/**
	 * @return array<string, array<int, string>> The class name and the name the document carries
	 */
	public static function documentNameProvider(): array
	{
		return [
			'global' => ['Payments', 'Payments'],
			'namespaced' => ['App\\Soap\\QuoteProvider', 'App.Soap.QuoteProvider'],
			'leading separator' => ['\\App\\Soap\\QuoteProvider', 'App.Soap.QuoteProvider'],
			'empty' => ['', ''],
			'not a class name' => ['Pay&Go', 'Pay&Go'],
		];
	}

	/**
	 * The parser accepts a namespace URI it finds invalid, warning about it,
	 * and the warning used to be hidden. The document is refused instead, naming
	 * what the parser said, and no warning reaches the error handler.
	 *
	 * @dataProvider unusableNameProvider
	 * @param string $name The service name
	 */
	public function testANameTheParserObjectsToIsRefusedWithoutAWarning($name): void
	{
		$raised = [];
		set_error_handler(function ($severity, $message) use (&$raised) {
			$raised[] = $message;
			return true;
		});

		try {
			$this->newWsdl($name)->getWsdl();
			$this->fail('the document was not refused');
		} catch (\RuntimeException $e) {
			$this->assertStringContainsString('"' . $name . '" service does not parse', $e->getMessage());
			$this->assertStringContainsString('is not a valid URI', $e->getMessage());
		} finally {
			restore_error_handler();
		}

		$this->assertSame([], $raised);
	}

	/**
	 * @return array<string, array<int, string>> The service name
	 */
	public static function unusableNameProvider(): array
	{
		return [
			'quote' => ['Pay"Go'],
			'less than' => ['Pay<Go'],
			'space' => ['Pay Go'],
		];
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

	/**
	 * The properties carry types, and did not in 1.1, where the document was
	 * assembled by interpolation. A caller passing something other than a string
	 * still gets the document that interpolation produced.
	 *
	 * @dataProvider coercedNameProvider
	 * @param mixed $name The service name to pass
	 * @param string $expected The name the document carries
	 */
	public function testANameThatIsNotAStringIsCoerced($name, $expected): void
	{
		$raised = [];
		set_error_handler(function ($severity, $message) use (&$raised) {
			$raised[] = $message;
			return true;
		});

		try {
			$dom = $this->parse(new Wsdl($name, 'http://example.com/soap', 'UTF-8'));
		} finally {
			restore_error_handler();
		}

		$this->assertSame($expected, $dom->documentElement->getAttribute('name'));
		$this->assertSame('urn:' . $expected . 'wsdl', $dom->documentElement->getAttribute('targetNamespace'));
	}

	/**
	 * @return array<string, array{0: mixed, 1: string}> The name to pass and the name it becomes
	 */
	public static function coercedNameProvider(): array
	{
		return [
			'null' => [null, ''],
			'int' => [7, '7'],
			'float' => [1.5, '1.5'],
			'bool' => [true, '1'],
			'array' => [['a'], 'Array'],
		];
	}

	public function testAnEncodingThatIsNotAStringIsCoerced(): void
	{
		$wsdl = new Wsdl('Service', 'http://example.com/soap', null);
		$this->assertStringStartsWith('<?xml version="1.0"?>', $wsdl->getWsdl());
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
			['name' => 'b', 'type' => 'xsd:int', 'nil' => 'true', 'minOc' => 0, 'maxOc' => 1],
		]);
		$dom = $this->parse($wsdl);

		$this->assertSame(['Record'], $this->complexTypes($dom));
		$this->assertCount(1, $this->query($dom, "//xsd:complexType[@name='Record']/xsd:all"));
		$this->assertSame(['a', 'b'], $this->attributes($dom, "//xsd:complexType[@name='Record']/xsd:all/xsd:element", 'name'));

		$b = $this->element($dom, "//xsd:complexType[@name='Record']/xsd:all/xsd:element[@name='b']");
		$this->assertSame('true', $b->getAttribute('nillable'));
		$this->assertSame('0', $b->getAttribute('minOccurs'));
		$this->assertSame('1', $b->getAttribute('maxOccurs'));

		$a = $this->element($dom, "//xsd:complexType[@name='Record']/xsd:all/xsd:element[@name='a']");
		$this->assertFalse($a->hasAttribute('nillable'));
		$this->assertFalse($a->hasAttribute('minOccurs'));
		$this->assertFalse($a->hasAttribute('maxOccurs'));
	}

	/**
	 * An xsd:all holds each element at most once, so a count above one is only
	 * valid in a sequence. A schema compiler rejects the element otherwise.
	 *
	 * @dataProvider occurrenceProvider
	 * @param mixed $minOccurs The minOccurs of the element
	 * @param mixed $maxOccurs The maxOccurs of the element
	 * @param string $compositor The compositor the type is expected to use
	 */
	public function testACountAboveOneMovesTheTypeToASequence($minOccurs, $maxOccurs, $compositor): void
	{
		$wsdl = $this->newWsdl();
		$wsdl->addComplexType('Record', [
			['name' => 'a', 'type' => 'xsd:int', 'nil' => false, 'minOc' => $minOccurs, 'maxOc' => $maxOccurs],
		]);
		$dom = $this->parse($wsdl);

		$this->assertCount(1, $this->query($dom, "//xsd:complexType[@name='Record']/" . $compositor));
	}

	/**
	 * @return array<string, array{0: mixed, 1: mixed, 2: string}> The counts and the compositor they require
	 */
	public static function occurrenceProvider(): array
	{
		return [
			'no counts' => [false, false, 'xsd:all'],
			'zero and one' => [0, 1, 'xsd:all'],
			'maxOccurs above one' => [false, 2, 'xsd:sequence'],
			'minOccurs above one' => [2, false, 'xsd:sequence'],
			'unbounded' => [0, 'unbounded', 'xsd:sequence'],
		];
	}

	public function testAnUnboundedMaxOccursReachesTheElement(): void
	{
		$wsdl = $this->newWsdl();
		$wsdl->addComplexType('Record', [
			['name' => 'a', 'type' => 'xsd:int', 'nil' => false, 'minOc' => 0, 'maxOc' => 'unbounded'],
		]);
		$dom = $this->parse($wsdl);

		$element = $this->element($dom, "//xsd:complexType[@name='Record']/xsd:sequence/xsd:element[@name='a']");
		$this->assertSame('unbounded', $element->getAttribute('maxOccurs'));
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
			['objectArray', 'xsd:anyType'],
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
